<?php

namespace AwardWallet\Engine\norwegian\Email;

class It4445776 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "norwegian/it-4248443.eml, norwegian/it-4445776.eml, norwegian/it-4532988.eml";

    public $reFrom = "info@sc.norwegian.no";
    public $reSubject = [
        "en"=> "Schedule change to your Norwegian reservation",
        "pl"=> "Zmiana rozkładu lotów, zmiana Państwa rezerwacji",
    ];
    public $reBody = 'Norwegian';
    public $reBody2 = [
        "en"=> "Your booking reference remains:",
        "pl"=> "Państwa numer rezerwacji to:",
    ];

    public static $dictionary = [
        "en" => [],
        "pl" => [
            "Your booking reference remains:"                       => "Państwa numer rezerwacji to:",
            "Passengers:"                                           => "Pasażer/Pasażerowie:",
            "PLEASE CLICK BELOW TO ACCEPT THIS FLIGHT CHANGE ONLINE"=> "PROSZĘ KLIKNĄĆ LINK PONIŻEJ, ABY ZAAKCEPTOWAĆ ZMIANY",
            "From"                                                  => "Wylot z",
            "to"                                                    => "przylot do",
            "Flight"                                                => "Lot",
            "Depart"                                                => "Wylot",
            "at"                                                    => "at",
            "and arrive"                                            => "Przylot",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#" . $this->t("Your booking reference remains:") . "\s+(\w+)#");

        // TripNumber
        // Passengers
        if (preg_match_all("#\d\.\s+(\w+\s+\w+)#", $this->re("#" . $this->t("Passengers:") . "\s+(.*?)\s+" . $this->t("PLEASE CLICK BELOW TO ACCEPT THIS FLIGHT CHANGE ONLINE") . "#ms"), $passangers)) {
            $it['Passengers'] = $passangers[1];
        }

        // AccountNumbers
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

        preg_match_all("#" . $this->t("From") . "\s+(?<DepName>.*?)\s+" . $this->t("to") . "\s+(?<ArrName>.*?),?\s+(?:\w+,\s+)?(?<Date>\d+/\d+/\d{4})\s+" . $this->t("Flight") . "\s+(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s+" .
                        $this->t("Depart") . "\s+(?<DepCode>[A-Z]{3})\s+" . $this->t("at") . "\s+(?<DepHours>\d{2})(?<DepMins>\d{2})\s+" . $this->t("and arrive") . "\s+(?<ArrCode>[A-Z]{3})\s+" . $this->t("at") . "\s+(?<ArrHours>\d{2})(?<ArrMins>\d{2})#ms", $this->text, $segments, PREG_SET_ORDER);

        foreach ($segments as $segment) {
            $date = strtotime($this->normalizeDate($segment["Date"]));

            $itsegment = [];

            $keys = [
                'FlightNumber',
                'AirlineName',
                'DepName',
                'ArrName',
                'DepCode',
                'ArrCode',
            ];

            foreach ($keys as $key) {
                $itsegment[$key] = $segment[$key];
            }

            $keys = [
                "Dep",
                "Arr",
            ];

            foreach ($keys as $key) {
                $itsegment[$key . "Date"] = strtotime($segment[$key . "Hours"] . ':' . $segment[$key . "Mins"], $date);
            }

            if ($itsegment["ArrDate"] < $itsegment["DepDate"]) {
                $itsegment["ArrDate"] = strtotime("+1 day", $itsegment["ArrDate"]);
            }

            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
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
        $body = $parser->getPlainBody();

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

        if (!($this->text = $parser->getPlainBody())) {
            $this->text = implode("\n", $this->http->FindNodes("./descendant::text()"));
        }
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations',
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
            "#^(\d+)/(\d+)/(\d{4})$#",
        ];
        $out = [
            "$2/$1/$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function re($re, $str = false, $c = 1)
    {
        if ($str === false) {
            $str = $this->text;
        }
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
