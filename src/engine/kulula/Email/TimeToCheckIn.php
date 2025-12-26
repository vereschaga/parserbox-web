<?php

namespace AwardWallet\Engine\kulula\Email;

use AwardWallet\Engine\MonthTranslate;

class TimeToCheckIn extends \TAccountChecker
{
    public $mailFiles = "kulula/it-26482097.eml, kulula/it-8156431.eml";

    public $reSubject = [
        'It’s time to check-in',
        'Get ready for your kulula flight',
    ];

    public $lang = '';

    public $date;

    public static $dict = [
        'en' => [
            'Booking number' => ['Booking number', 'kulula booking reference'],
        ],
    ];

    private $reFrom = 'msg@experience.kulula.com';

    private $reProvider = 'kulula.com';

    private $reBody = 'kulula.com';

    private $reBody2 = [
        "en" => [
            "we look forward to welcoming you onboard soon",
            "to start running around to rush for your flight now",
            'Your payment has been received and your booking is confirmed',
            'Thank you for choosing to fly with us, we look forward to welcoming you on-board soon',
            'You don\'t have to start running around to rush for',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach ($this->reBody2 as $lang => $re) {
            $reBody = (array) $re;

            foreach ($reBody as $r) {
                if (strpos($body, $r) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $this->date = strtotime($parser->getHeader('date'));
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "TimeToCheckIn" . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            $reBody = (array) $re;

            foreach ($reBody as $r) {
                if ($this->http->XPath->query("//text()[{$this->contains($r)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (strpos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reProvider) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function calculateDate($dateStr)
    {
        $in = [
            "#^\s*([a-z]+)\s*(\d{1,2})\s*([a-z]+)\s*$#ui", // Tue22August
        ];
        $out = [
            "$1 $2 $3",
        ];
        $dateStr = preg_replace($in, $out, $dateStr);

        if (preg_match("#\d+\s+([^\d\s]+)#", $dateStr, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $dateStr = str_replace($m[1], $en, $dateStr);
            }
        }

        if (preg_match('#^(\w+)\s+(\d{1,2})\s+(\d{2}|\w+)\s*(\d+:\d+)?\s*$#u', $dateStr, $m) > 0) {
            if (empty($m[4])) {
                $m[4] = '';
            }
            $current = (int) date('Y', $this->date);

            for ($i = 0; $i < 3; $i++) {
                $foundDate = strtotime(sprintf('%s.%s.%s %s', $m[2], $m[3], $current + $i, $m[4]));

                if (strcasecmp($m[1], date('D', $foundDate)) === 0) {
                    break;
                }
                $foundDate = strtotime(sprintf('%s.%s.%s %s', $m[2], $m[3], $current - $i, $m[4]));

                if (strcasecmp($m[1], date('D', $foundDate)) === 0) {
                    break;
                }
                unset($foundDate);
            }

            if (isset($foundDate)) {
                return $foundDate;
            }
        }

        return strtotime($dateStr);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Booking number')) . "]/following::text()[normalize-space(.)][1]", null, true, "#([A-Z\d]+)#");

        if ($pax = $this->http->FindNodes("//tr[contains(normalize-space(.), 'Full Name') and not(.//tr)]/following-sibling::tr[normalize-space(.)]")) {
            $it['Passengers'] = $pax;
        }

        if ($pax = $this->http->FindNodes("//tr[contains(normalize-space(.), 'Travelling passengers') and not(.//tr)]/ancestor::table[1]/following-sibling::table[1]//td")) {
            $it['Passengers'] = $pax;
        }

        if (empty($it['Passengers'])) {
            $it['Passengers'][] = $this->http->FindSingleNode("//text()[starts-with(.,'" . $this->t('Hi ') . "')][1]", null, true, "#Hi\s*(.+),#");
        }

        if ($tn = $this->http->FindNodes("//tr[contains(normalize-space(.), 'Ticket Number') and not(.//tr)]/following-sibling::tr[normalize-space(.)]")) {
            $it['TicketNumbers'] = $tn;
        }

        $xpath = "//img[contains(@src, 'flight-depart')]/ancestor::table[contains(normalize-space(.), 'Departure')][1]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->info("Segments did not found by xpath: {$xpath}");
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $from = $this->http->FindSingleNode("descendant::img[contains(@src,'arrow-to')]/ancestor::td[1]/preceding-sibling::td[1]", $root);

            if (preg_match("#(.+)\s*\(([A-Z]{3})\)#", $from, $m)) {
                $seg['DepCode'] = $m[2];
                $seg['DepName'] = trim($m[1]);
            }
            $to = $this->http->FindSingleNode("descendant::img[contains(@src,'arrow-to')]/ancestor::td[1]/following-sibling::td[1]", $root);

            if (preg_match("#(.+)\s*\(([A-Z]{3})\)#", $to, $m)) {
                $seg['ArrCode'] = $m[2];
                $seg['ArrName'] = trim($m[1]);
            }
            $xp = "descendant::img[contains(@src,'arrow-to')]/ancestor::tr[1]/following-sibling::tr[1]/";

            if (empty($seg['DepName']) && ($depName = $this->http->FindSingleNode($xp . 'td[1]', $root))) {
                $seg['DepName'] = $depName;
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            if (empty($seg['ArrName']) && ($arrName = $this->http->FindSingleNode($xp . 'td[2]', $root))) {
                $seg['ArrName'] = $arrName;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $date = $this->calculateDate($this->http->FindSingleNode("descendant::text()[contains(.,'Departure')]/preceding::td[normalize-space(.)!=''][1]", $root, true, '/(\D+\s*\d{1,2}\s*\D+)/'));
            $seg['DepDate'] = strtotime($this->http->FindSingleNode("descendant::td[contains(.,'Departure') and not(.//td)]", $root, true, '/(\d{1,2} \D+ \d{2,4}\s+\d{1,2}:\d{2})/'));

            if (!$seg['DepDate']) {
                $seg['DepDate'] = strtotime($this->http->FindSingleNode("descendant::text()[contains(.,'Departure')]/following::text()[normalize-space(.)!=''][1]", $root), $date);
            }
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("descendant::td[contains(.,'Arrival') and not(.//td)][1]", $root, true, '/(\d{1,2} \D+ \d{2,4}\s+\d{1,2}:\d{2})/'));

            if (!$seg['ArrDate']) {
                $seg['ArrDate'] = strtotime($this->http->FindSingleNode("descendant::text()[contains(.,'Arrival')]/following::text()[normalize-space(.)!=''][1]", $root), $date);
            }

            $flight = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(.),'Flight Number') or contains(normalize-space(.), 'Flight number')]/following::text()[normalize-space(.)!=''][1]", $root);

            if (preg_match("#^\s*([A-Z\d]{2})\s*(\d{1,5})\s*$#", $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $class = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(.), 'Class')]/following::text()[normalize-space(.)!=''][1]", $root);

            if ($class) {
                $seg['Cabin'] = $class;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
