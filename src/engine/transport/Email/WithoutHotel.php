<?php

namespace AwardWallet\Engine\transport\Email;

class WithoutHotel extends \TAccountChecker
{
    public $mailFiles = ""; // +1 bcdtravel(html)[en]

    public $reFrom = '@travelandtransport.com';
    public $reSubject = [
        'en' => ['Upcoming Trip: Overnight Stay without Hotel Booking', 'Upcoming Trip Summary Details'],
    ];
    public $langDetectors = [
        'en' => ['Confirmation Number:'],
    ];

    public $lang = '';
    public static $dict = [
        'en' => [],
    ];

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Travel and Transport on Twitter") or contains(normalize-space(.),"please contact Travel and Transport") or contains(.,"@travelandtransport.com")]')->length === 0;

        if ($condition1) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $its = [];
        $pax = $this->http->FindNodes("//text()[normalize-space(.)='{$this->t('TRAVELER')}']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!='']/td[1]");
        $tripNum = $this->http->FindSingleNode("//text()[normalize-space(.)='{$this->t('TRAVELER')}']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][1]/td[2]");
        $xpath = "//text()[normalize-space(.)='DEPART']/ancestor::tr[./following-sibling::tr][1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./ancestor::table[2]/descendant::text()[starts-with(normalize-space(.),'Confirmation Number:')]/following::text()[normalize-space(.)!=''][1]", $root);
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $nodes) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['Passengers'] = $pax;
            $it['TripNumber'] = $tripNum;
            $ffNumbers = [];

            foreach ($nodes as $root) {
                $seg = [];
                $node = $this->http->FindSingleNode('./preceding::tr[normalize-space(.)][1]', $root);

                if (preg_match('/(.+)\s+(?:FLIGHT|Flight)\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d+)[-\s]+(.+)/', $node, $m)) {
                    $seg['AirlineName'] = $m[2];
                    $seg['FlightNumber'] = $m[3];
                    $seg['Cabin'] = $m[4];
                }

                $xpathFragment1 = './following-sibling::tr[normalize-space(.)][1]/descendant::tr[ ./td[3] ][1]/td[normalize-space(.)]';

                $node = $this->http->FindSingleNode($xpathFragment1 . '[1]', $root);

                if (preg_match('#(.+)\s+\(([A-Z]{3})\).+?,\s+(\w+\s\d+\S+\s+\d+.+)#', $node, $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepCode'] = $m[2];
                    $seg['DepDate'] = strtotime($this->normalizeDate($m[3]));
                }

                $node = $this->http->FindSingleNode($xpathFragment1 . '[2]', $root);

                if (preg_match('#(.+)\s+\(([A-Z]{3})\).+?,\s+(\w+\s\d+\S+\s+\d+.+)#', $node, $m)) {
                    $seg['ArrName'] = $m[1];
                    $seg['ArrCode'] = $m[2];
                    $seg['ArrDate'] = strtotime($this->normalizeDate($m[3]));
                }

                // Duration
                $seg['Duration'] = $this->http->FindSingleNode($xpathFragment1 . '[3]/descendant::text()[normalize-space(.)][1]', $root, true, '/^\d.+/');

                // Stops
                $stopsText = $this->http->FindSingleNode($xpathFragment1 . '[3]/descendant::text()[normalize-space(.)][2]', $root);

                if (preg_match('/non[-\s]*stop/i', $stopsText)) {
                    $seg['Stops'] = 0;
                } elseif (preg_match('/\b(\d{1,3})\s+stops?\b/i', $stopsText, $matches)) {
                    $seg['Stops'] = $matches[1];
                }

                $xpathFragment2 = './following-sibling::tr/descendant::text()[contains(normalize-space(.),"SEAT")][1]/ancestor::tr[./following-sibling::tr][1]/following-sibling::tr[normalize-space(.)][1][count(./td)=5]';

                // Seats
                $seat = $this->http->FindSingleNode($xpathFragment2 . '/td[1]', $root, true, '/^(\d{1,3}[A-Z])$/');

                if ($seat) {
                    $seg['Seats'] = [$seat];
                }

                // Aircraft
                $equipment = $this->http->FindSingleNode($xpathFragment2 . '/td[3]', $root);

                if ($equipment) {
                    $seg['Aircraft'] = $equipment;
                }

                $ffNumber = $this->http->FindSingleNode($xpathFragment2 . '/td[5]', $root, true, '/^([A-Z\d][-A-Z\d\s]{5,}[A-Z\d])$/');

                if ($ffNumber) {
                    $ffNumbers[] = $ffNumber;
                }

                $it['TripSegments'][] = $seg;
            }

            // AccountNumbers
            if (count($ffNumbers)) {
                $it['AccountNumbers'] = array_unique($ffNumbers);
            }

            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\w+)\s(\d+)\S+\s+(\d+)\s+(\d+:\d+(?:\s*[ap]m)?)$#', // Jun 27th, 2017 10:15 AM
        ];
        $out = [
            '$2 $1 $3 $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
