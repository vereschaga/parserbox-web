<?php

namespace AwardWallet\Engine\germanwings\Email;

class It3702372 extends \TAccountCheckerExtended
{
    public $reBody = 'eurowings';
    public $reBody2 = [
        "de"=> "IHRE FLUGBUCHUNG",
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
                $text = text($this->http->Response["body"]);
                $it = [];

                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = re("#" . $this->t("Buchungscode:") . "\s*(\w+)#", $text);

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

                $xpath = "//img[contains(@src, 'images/icon_flug_40x24.gif') and not(contains(@style, 'display:none'))]/ancestor::tr[contains(., '‍:‍')][1]";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                $year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]", null, true, "#(\d+)$#");

                foreach ($nodes as $root) {
                    $date = strtotime($this->normalizeDate(implode(".", $this->http->FindNodes("(./td[1]//text()[string-length(normalize-space(.))>1])[position()=1 or position()=2 or position()=3]", $root))));

                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("(./td[1]//text()[string-length(normalize-space(.))>1])[4]", $root, true, "#\w{2}(\d+)#");

                    // DepCode
                    $itsegment['DepCode'] = ure("#\(([A-Z]{3})\)#", $this->http->FindSingleNode("./td[2]", $root));

                    // DepName
                    // DepDate
                    $itsegment['DepDate'] = strtotime(preg_replace("#[^\w\s:]+#", "", ure("#(\d+[^\w\s]*:[^\w\s]*\d+)#", $this->http->FindSingleNode("./td[2]", $root))), $date);

                    // ArrCode
                    $itsegment['ArrCode'] = ure("#\(([A-Z]{3})\)#", $this->http->FindSingleNode("./td[2]", $root), 2);

                    // ArrName
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime(preg_replace("#[^\w\s:]+#", "", ure("#(\d+[^\w\s]*:[^\w\s]*\d+)#", $this->http->FindSingleNode("./td[2]", $root), 2)), $date);

                    // AirlineName
                    $itsegment['AirlineName'] = $this->http->FindSingleNode("(./td[1]//text()[string-length(normalize-space(.))>1])[4]", $root, true, "#(\w{2})\d+#");

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

        if (empty($body)) {
            $body = $parser->getPlainBody();
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

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+,\s+(\d+)\.(\d+)\.(\d{4})$#",
        ];
        $out = [
            "$1.$2.$3",
        ];

        return en(preg_replace($in, $out, $str));
    }
}
