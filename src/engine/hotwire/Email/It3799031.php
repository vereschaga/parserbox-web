<?php

namespace AwardWallet\Engine\hotwire\Email;

class It3799031 extends \TAccountCheckerExtended
{
    public $mailFiles = "hotwire/it-3799031.eml";
    public $reBody = 'Hotwire';
    public $reBody2 = [
        "en"=> "Thank you for booking your trip",
    ];

    public $reSubject = [
        "en"=> "Hotwire Vacations",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            "html" => function (&$itineraries) {
                //#################
                //##   FLIGHT   ###
                //#################

                if ($container = $this->http->XPath->query("//*[normalize-space(text())='Flight summary']/ancestor::tr[2]/following-sibling::tr[1]")->item(0)) {
                    $it = [];

                    $it['Kind'] = "T";

                    // RecordLocator
                    $it['RecordLocator'] = re("#Hotwire\s+Vacations\s+itinerary\s+number\s*:\s*(\w+)#", $this->text());

                    // TripNumber
                    // Passengers
                    $it['Passengers'] = $this->http->FindNodes("//td[normalize-space(.)='Adult']/preceding-sibling::td[1]");

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

                    $xpath = ".//text()[normalize-space(.)='Flight:']/ancestor::tr[1]";
                    $nodes = $this->http->XPath->query($xpath, $container);

                    if ($nodes->length == 0) {
                        $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                    }

                    $year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]", null, true, "#(\d+)$#");

                    foreach ($nodes as $root) {
                        $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[2]", $root)));

                        $itsegment = [];
                        // FlightNumber
                        $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[5]", $root, true, "#Flight\s*:\s*(\d+)#");

                        // DepCode
                        $itsegment['DepCode'] = $this->http->FindSingleNode("./td[1]", $root, true, "#([A-Z]{3})#");

                        // DepName
                        // DepDate
                        $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[1]", $root, true, "#\d+:\d+\s*[ap]m#"), $date);

                        // ArrCode
                        $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]", $root, true, "#([A-Z]{3})#");

                        // ArrName
                        // ArrDate
                        $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]", $root, true, "#\d+:\d+\s*[ap]m#"), $date);

                        // AirlineName
                        $itsegment['AirlineName'] = $this->http->FindSingleNode("(./td[5]//text()[normalize-space()])[1]", $root);

                        // Aircraft
                        $itsegment['Aircraft'] = $this->http->FindSingleNode("./following-sibling::tr[2]//*[@id='planetype']", $root);

                        // TraveledMiles
                        $itsegment['TraveledMiles'] = $this->http->FindSingleNode("(./td[4]//text()[normalize-space()])[1]", $root);

                        // Cabin
                        $itsegment['Cabin'] = $this->http->FindSingleNode("./following-sibling::tr[2]", $root, true, "#(\w+)/Coach#");

                        // BookingClass
                        // PendingUpgradeTo
                        // Seats
                        // Duration
                        $itsegment['Duration'] = $this->http->FindSingleNode("(./td[4]//text()[contains(.,'Duration')])[1]", $root, true, "#Duration\s*:\s*(.+)#");

                        // Meal
                        // Smoking
                        // Stops
                        $it['TripSegments'][] = $itsegment;
                    }
                    $itineraries[] = $it;
                }

                //##############
                //##   CAR   ###
                //##############

                if ($container = $this->http->XPath->query("//*[normalize-space(text())='Car rental summary']/ancestor::tr[2]/following-sibling::tr[1]/..")->item(0)) {
                    $it = [];

                    $it['Kind'] = "L";

                    // Number
                    $it['Number'] = re("#Car\s+confirmation\s+number\s*:\s*(\w+)#", $this->text());

                    // TripNumber
                    // PickupDatetime
                    $it['PickupDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[normalize-space(.)='Pick up:']/following::text()[normalize-space()][1]", $container)));

                    // PickupLocation
                    $it['PickupLocation'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='Location:']/following::span[1]", $container);

                    // DropoffDatetime
                    $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[normalize-space(.)='Drop off:']/following::text()[normalize-space()][1]", $container)));

                    // DropoffLocation
                    $it['DropoffLocation'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='Location:']/following::span[1]", $container);

                    // PickupPhone
                    // PickupFax
                    // PickupHours
                    $it['PickupHours'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='Hours of operation:']/following::text()[normalize-space()][1]", $container, true, "#\d+/\d+/\d+:\s+(.*?)\s+\d+/\d+/\d+#");

                    // DropoffPhone
                    // DropoffHours
                    $it['DropoffHours'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='Hours of operation:']/following::text()[normalize-space()][1]", $container, true, "#\d+/\d+/\d+:\s+.*?\s+\d+/\d+/\d+:\s+(.+)#");

                    // DropoffFax
                    // RentalCompany
                    $it['RentalCompany'] = $this->http->FindSingleNode(".//text()[contains(.,'Car:')]/preceding::text()[normalize-space(.)][1]", $container);

                    // CarType
                    $it['CarType'] = $this->http->FindSingleNode(".//text()[contains(.,'Car:')]", $container, true, "#(\w+)\s+Car#");

                    // CarModel
                    // CarImageUrl
                    // RenterName
                    // PromoCode
                    // TotalCharge
                    // Currency
                    // TotalTaxAmount
                    // SpentAwards
                    // EarnedAwards
                    // AccountNumbers
                    // Status
                    // ServiceLevel
                    // Cancelled
                    // PricedEquips
                    // Discount
                    // Discounts
                    // Fees
                    // ReservationDate
                    // NoItineraries
                    $itineraries[] = $it;
                }
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

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $lang=>$rule) {
            if (strpos($headers["subject"], $rule) !== false) {
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
                'TotalCharge' => [
                    'Amount'   => cost($this->http->FindSingleNode("//text()[contains(.,'Total amount charged')]/following::text()[normalize-space()][1]")),
                    "Currency" => currency($this->http->FindSingleNode("//text()[contains(.,'Total amount charged')]/following::text()[normalize-space()][1]")),
                ],
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

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\s+(\d+)-(\w+)-(\d{2})$#",
        ];
        $out = [
            "$1 $2 $3",
        ];

        return en(preg_replace($in, $out, $str));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
