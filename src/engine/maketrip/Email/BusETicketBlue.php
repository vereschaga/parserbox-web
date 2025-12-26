<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Engine\MonthTranslate;

// TODO: merge with parsers maketrip/ATicket (in favor of maketrip/BusETicketBlue)

class BusETicketBlue extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-10086006.eml, maketrip/it-6835798.eml, maketrip/it-6849248.eml, maketrip/it-6887721.eml, maketrip/it-122399361.eml";
    public $reFrom = "admin@makemytrip.com";
    public $reSubject = [
        "en"=> "E-Ticket for Booking ID",
    ];
    public $reBody = 'MakeMyTrip';
    public $reBody2 = [
        "en"=> "Bus Type:",
    ];

    public static $dictionary = [
        'en' => [
            'ticket'    => ['PNR/Ticket Number:', 'Ticket Number:'],
            'busId'     => ['MakeMyTrip Bus Id:', 'MakeMyTrip Bus ID:'],
            'dateDep'   => ['Bus Departure:', 'Boarding Date and Time:'],
            'dropPoint' => ['Dropping Point:', 'Dropping Point', 'Drop Point:', 'Drop Point'],
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries): void
    {
        $xpathCell = '(self::td or self::th)';

        $it = [];
        $it['Kind'] = "T";

        $ticketAndPnr = $this->nextCell($this->t('ticket'));

        if (preg_match("/^([-A-Z\d\|]{5,})(?:\s*[(]|$)/", $ticketAndPnr, $m)) {
            // 49865673-1020557    |    20160902000084|510
            $it['TicketNumbers'] = [str_replace('|', '-', $m[1])];
        }

        if (preg_match("/\bPNR[:\s]+([-A-Z\d]{5,})(?:\s*[)]|$)/", $ticketAndPnr, $m)) {
            // TS-MMT18770685CFS (Operator PNR:1829780)
            $it['RecordLocator'] = $m[1];
        } elseif ($ticketAndPnr && stripos($ticketAndPnr, 'PNR') === false
            && strpos($ticketAndPnr, '(') === false && strpos($ticketAndPnr, ')') === false
        ) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        $it['TripNumber'] = $this->nextText($this->t('busId'));

        // Passengers
        $it['Passengers'] = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq("Name") . "]/ancestor::tr[1]/following-sibling::tr/td[position()=2 or position()=6]")));

        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->nextText("Total Fare:");

        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $it['TripCategory'] = TRIP_CATEGORY_BUS;

        $itsegment = [];

        $cityDep = $this->nextText($this->t('From:'));
        $cityArr = $this->nextText($this->t('To:'));

        // Mcd Toll Gurgaon, 07442340097,07442423097,8696238097,9549638097,6376821419
        $patterns['badPoint'] = '/^(.{3,}?)[ ]*,[ ]*\d{7,}[ ]*,[ ]*\d{7,}.*$/';

        $boardingPoint = $this->nextCell($this->t('Boarding Point:'))
            ?? $this->http->FindSingleNode("//tr[{$this->eq($this->t('Boarding Point Details'))}]/following-sibling::tr[normalize-space()][1]/descendant::*[{$xpathCell} and {$this->eq($this->t('Location:'))}]/following-sibling::*[{$xpathCell}][1]")
        ;

        if ($boardingPoint !== null) {
            $boardingPoint = preg_replace($patterns['badPoint'], '$1', $boardingPoint);
        }
        $dropPoint = $this->nextCell($this->t('dropPoint'));

        if ($dropPoint !== null) {
            $dropPoint = preg_replace($patterns['badPoint'], '$1', $dropPoint);
        }

        if ($boardingPoint && $cityDep && strcasecmp($boardingPoint, $cityDep) !== 0) {
            $itsegment['DepName'] = $boardingPoint . ', ' . $cityDep;
        } elseif ($cityDep) {
            $itsegment['DepName'] = $cityDep;
        }

        if ($dropPoint && $cityArr && strcasecmp($dropPoint, $cityArr) !== 0) {
            $itsegment['ArrName'] = $dropPoint . ', ' . $cityArr;
        } elseif ($cityArr) {
            $itsegment['ArrName'] = $cityArr;
        }

        // DepDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate($this->nextCell($this->t('dateDep'))));

        // ArrDate
        if (!empty($itsegment['DepDate'])) {
            $itsegment['ArrDate'] = MISSING_DATE;
        }

        // Type
        $itsegment['Type'] = $this->nextText("Bus Type:");

        // TraveledMiles
        // Cabin
        // BookingClass
        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'] = array_filter($this->http->FindNodes("//text()[" . $this->eq("Seat") . "]/ancestor::tr[1]/following-sibling::tr/td[position()=3 or position()=7]"));

        if (!empty($itsegment['DepName']) && !empty($itsegment['ArrName']) && !empty($itsegment['DepDate'])) {
            $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        // Duration
        // Meal
        // Smoking
        // Stops
        $it['TripSegments'][] = $itsegment;
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@makemytrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'MakeMyTrip') === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'BusETicketBlue' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4})$#",
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function nextCell($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("descendant-or-self::*[(self::td or self::th) and {$rule}]/following-sibling::*[(self::td or self::th) and normalize-space()][1]", $root);
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[({$rule}) and ancestor::tr])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }
}
