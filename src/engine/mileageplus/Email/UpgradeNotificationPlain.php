<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Engine\MonthTranslate;

class UpgradeNotificationPlain extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-11265437.eml";
    public $reFrom = "@united.com";
    public $reSubject = [
        "en" => "Premier Upgrade Notification",
    ];
    public $reBody = 'United';
    public $reBody2 = [
        "en" => "You've been upgraded from",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries)
    {
        $text = strip_tags(preg_replace("#<br[^>]*>#", "\n", $this->text));
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#Confirmation Number:\s+([A-Z\d]+)\b#", $text);

        // TripNumber
        // Passengers
        $it['Passengers'][] = $this->re("#recap of your flight information:\s*\n\s*([A-Z\- ]+)\n#", $text);

        // TicketNumbers
        // AccountNumbers
        $it['AccountNumbers'][] = $this->re("#Frequent Flyer:\s+([A-Z\d]+)\b#", $text);

        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $itsegment = [];

        if (preg_match("#(.+)\n(.+)[ ]*flight[ ]*(\d{1,5})\s+Depart#", $text, $m)) {
            $date = trim($m[1]);

            // FlightNumber
            $itsegment['FlightNumber'] = $m[3];

            // AirlineName
            $itsegment['AirlineName'] = trim($m[2]);
        }

        if (preg_match("#Depart (.+) from (.+)\(([A-Z]{3})(?:\s*-\s*(.+))?\)#", $text, $m)) {
            // DepCode
            $itsegment['DepCode'] = $m[3];

            // DepName
            if (isset($m[4])) {
                $itsegment['DepName'] = trim($m[4]) . ', ' . trim($m[2]);
            } else {
                $itsegment['DepName'] = trim($m[2]);
            }
            // DepartureTerminal

            // DepDate
            if (!empty($date)) {
                $itsegment['DepDate'] = $this->normalizeDate($date . ', ' . $m[1]);
            }
        }

        if (preg_match("#Arrives (.+) into (.+)\(([A-Z]{3})(?:\s*-\s*(.+))?\)#", $text, $m)) {
            // ArrCode
            $itsegment['ArrCode'] = $m[3];

            // DepName
            if (isset($m[4])) {
                $itsegment['ArrName'] = trim($m[4]) . ', ' . trim($m[2]);
            } else {
                $itsegment['ArrName'] = trim($m[2]);
            }
            // DepartureTerminal

            // DepDate
            if (!empty($date)) {
                $itsegment['ArrDate'] = $this->normalizeDate($date . ', ' . $m[1]);
            }
        }

        // Aircraft
        // TraveledMiles
        // AwardMiles
        // Cabin
        // BookingClass
        // PendingUpgradeTo
        // Seats
        if (preg_match("#Your new seat is\s*(\d{1,3}[A-Z])\b#", $text, $m)) {
            $itsegment['Seats'][] = $m[1];
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
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
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
        $itineraries = [];
        $this->text = $parser->getHTMLBody();

        //		foreach($this->reBody2 as $lang=>$re){
        //			if(strpos($this->text, $re) !== false){
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $this->parsePlain($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
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
        // $this->logger->info($str);
        $in = [
            "#^\s*[^\s\d]+[., ]+([^\s\d\.]+)[., ]+(\d+),\s+(\d{4})\s+(\d+:\d+)\s+([ap])\.m\.$#", //Tue., Feb. 20, 2018 3:23 p.m.
        ];
        $out = [
            "$2 $1 $3, $4 $5m",
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|Y)#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
