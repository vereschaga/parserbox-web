<?php

namespace AwardWallet\Engine\turkish\Email;

class OnlineCheckIn extends \TAccountChecker
{
    public $mailFiles = "turkish/it-8148592.eml";
    public $detectLang = [
        'en'   => ['Reservation Code', 'FLIGHT INFORMATION'],
        'tr'   => ['Rezervasyon Kodu', 'UÇUŞ BİLGİLERİ'],
        'tr2'  => ['RezervasyonKodu', 'UÇUŞ BİLGİLERİ'],
        'tr3'  => ['Rezervasyon Kodu', 'KOLTUK SEÇİMLERİNİZ'],
    ];
    public static $dict = [
        'en' => [
            'Your Reservation Code' => ['Your Reservation Code', 'Reservation Code'],
            //            'DIRECT' => '',
        ],
        'tr' => [
            'Your Reservation Code' => ['Rezervasyon Kodu', 'RezervasyonKodu'],
            'Dear'                  => 'Sayın',
            'FLIGHT INFORMATION'    => ['UÇUŞ BİLGİLERİNİZ', 'UÇUŞ BİLGİLERİ', 'KOLTUK SEÇİMLERİNİZ'],
            'DIRECT'                => 'DİREKT',
            'Ticket No'             => 'Bilet No',
        ],
    ];

    private $detects = [
        'Thank you for choosing AnadoluJet.',
        "AnadoluJet'i tercih ettiğiniz için teşekkür ederiz.", //tr
    ];

    private $lang = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return null;
        }

        $name = explode('\\', __CLASS__);

        return [
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
            'emailType' => end($name) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'turkishairlines.com') or contains(@href,'anadolujet.com')]")->length === 0) {
            return false;
        }
//        $body = $parser->getHTMLBody();
        foreach ($this->detects as $detect) {
            if ($this->http->XPath->query("//*[{$this->contains($detect)}]")->length > 0) {
//            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thy.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headres['from']) && stripos($headers['from'], '@thy.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

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

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode("//td[{$this->contains($this->t('Your Reservation Code'))}]/following-sibling::td[1]",
            null, true, '/([A-Z\d]{5,7})/');

        if ($this->http->XPath->query("//text()[normalize-space()='Bilet No:']")->length > 0) {
            $it['TicketNumbers'] = $this->http->FindNodes("//text()[normalize-space()='Bilet No:']/following::text()[normalize-space(.)!=''][1]",
                null, "#^\d{5,}$#");
            $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space()='Bilet No:']/preceding::text()[normalize-space(.)!=''][1]",
                null, "#^\w+ \w+$#");
        } else {
            $it['Passengers'][] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]/following::text()[normalize-space(.)!=''][1]");
        }

        $tickets = $this->http->FindNodes("//text()[{$this->eq($this->t('Ticket No'))}]/following::text()[normalize-space(.)][1]",
            null, "/^\d{8,}$/");

        if (!empty($tickets)) {
            $it['TicketNumbers'] = $tickets;
        }

        $xpath = "//tr[({$this->contains($this->t('FLIGHT INFORMATION'))}) and not(.//tr)]/following-sibling::tr[contains(translate(.,'0123456789','dddddddddd'),'dd:dd')]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $seg['DepName'] = implode(" ", $this->http->FindNodes("descendant::tr[1]/descendant::*[count(td)>2]/td[1]//text()[normalize-space()]", $root));

            $seg['ArrName'] = implode(" ", $this->http->FindNodes("descendant::tr[1]/descendant::*[count(td)>2]/td[3]//text()[normalize-space()]", $root));

            $date = $this->normalizeDate($this->http->FindSingleNode("descendant::tr[1]/following::tr[normalize-space(.)!=''][1]/descendant::tr[1]",
                $root));

            $times = $this->http->FindSingleNode("descendant::tr[1]/following::tr[normalize-space(.)!=''][1]/descendant::tr[normalize-space(.)!=''][2]",
                $root);

            if (preg_match('/(\d+:\d{2})\s*(\d+:\d+)/', $times, $m)) {
                $seg['DepDate'] = strtotime($date . ', ' . $m[1]);
                $seg['ArrDate'] = strtotime($date . ', ' . $m[2]);
            }

            $flight = $this->http->FindSingleNode("descendant::tr[1]/following::tr[normalize-space(.)!=''][1]/descendant::tr[normalize-space(.)!=''][2]/following::tr[normalize-space(.)!=''][not({$this->contains($this->t('DIRECT'))})][1]",
                $root);

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (!empty($seg['FlightNumber']) && !empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            if (preg_match("#^(.+)\s+([A-Z]{3})$#", $seg['DepName'], $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
            }

            if (preg_match("#^(.+)\s+([A-Z]{3})$#", $seg['ArrName'], $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];
            }
            $flight = $this->http->FindSingleNode("descendant::tr[1]/following::tr[normalize-space(.)!=''][1]/descendant::tr[normalize-space(.)!=''][2]/following::tr[normalize-space(.)!=''][{$this->contains($this->t('DIRECT'))}][1]",
                $root);

            if (preg_match('/, (\d+)s (\d+)d\d*$/', $flight, $m)) {
                $seg['Duration'] = $m[1] . 'h ' . $m[2] . 'm';
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($str)
    {
        $in = [
            '/^(\d{2,4})\-(\d+)\-(\d+)\s*,\s*\w+$/',
            //24 Kasım 2022 , Perşembe
            '/^\s*(\d{1,2})\s+(\w+)\s+(\d{4})\s*,\s*\w+\s*$/u',
        ];
        $out = [
            '$2/$3/$1',
            '$1 $2 $3',
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->detectLang)) {
            foreach ($this->detectLang as $lang => $reBody) {
                $r1 = str_replace(' ', '', $reBody[0]);
                $r2 = str_replace(' ', '', $reBody[1]);

                if ($this->http->XPath->query("//*[contains(translate(normalize-space(.),' ',''),'{$r1}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(translate(normalize-space(.),' ',''),'{$r2}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }
}
