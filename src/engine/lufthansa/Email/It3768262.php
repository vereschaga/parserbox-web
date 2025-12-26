<?php

namespace AwardWallet\Engine\lufthansa\Email;

class It3768262 extends \TAccountCheckerExtended
{
    public $reBody = 'Lufthansa';
    public $reBody2 = [
        "de"=> "Ihre Fluginformationen",
    ];

    public static $dictionary = [
        "de" => [],
    ];

    public $lang = "de";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            "html" => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("//img[contains(@alt, '" . $this->t("BUCHUNGSCODE") . "')]/@alt", null, true, "#BUCHUNGSCODE\s*[¬]*\s*(\w+)#");

                // TripNumber
                // Passengers
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

                $xpath = "//img[contains(@alt, 'BUCHUNGSCODE')]/following::table[1]|//img[contains(@alt, 'BUCHUNGSCODE')]/following::table[1]/following-sibling::table";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                foreach ($nodes as $root) {
                    $text = text($root->nodeValue);

                    $date = strtotime(re("#\d+\.\d+\.\d{4}#", $text));

                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = re("#\d+\.\d+\.\d{4}\s*\n*\s*[A-Z\s]{2}\s*(\d+)#", $text);

                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $itsegment['DepName'] = re("#\n*\s*\d+\s*:\s*\d+\s*\n*\s*(.*?)\s*\n*\s*\d+\s*:\s*\d+\s*\n*\s*(.+?)\s*Gepäck\n*#", $text);

                    // DepDate
                    $itsegment['DepDate'] = strtotime(preg_replace("#\s+#", "", uberTime($text)), $date);

                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                    // ArrName
                    $itsegment['ArrName'] = re("#\n*\s*\d+\s*:\s*\d+\s*\n*\s*(.*?)\s*\n*\s*\d+\s*:\s*\d+\s*\n*\s*(.*?)\s*Gepäck\n*#", $text, 2);

                    // ArrDate
                    $itsegment['ArrDate'] = strtotime(preg_replace("#\s+#", "", uberTime($text, 2)), $date);

                    // AirlineName
                    $itsegment['AirlineName'] = re("#\d+\.\d+\.\d{4}\s*\n*\s*([A-Z\d]{2})\s*\d+#", $text);

                    // Aircraft
                    // TraveledMiles
                    // Cabin
                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    // Duration
                    // Meal
                    // Smoking
                    // Stops
                    $it['TripSegments'][] = $itsegment;
                }
                $itineraries[] = $it;
            },
        ];
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

        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();
        //		$body = str_replace('&zwj;', '', $body);
        $body = str_replace(chr(226) . chr(128) . chr(141), '', $body);

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $body = str_replace(chr(226) . chr(128) . chr(141), '', $body);
            $this->http->SetBody($body);
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }

        $result = [
            'emailType'  => 'Flight',
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

    private function getField($field)
    {
        return $this->http->FindSingleNode("//td[not(.//td) and normalize-space(.)='{$field}']/following-sibling::td[1]");
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
