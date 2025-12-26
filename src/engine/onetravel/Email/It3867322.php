<?php

namespace AwardWallet\Engine\onetravel\Email;

class It3867322 extends \TAccountCheckerExtended
{
    public $reBody = "OneTravel";
    public $reBody2 = [
        "en"=> "hank you for choosing",
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
                $xpath = "//*[normalize-space(.)='Departing Flight' or normalize-space(.)='Return Flight']/ancestor::tr[1]/following-sibling::tr";
                $nodes = $this->http->XPath->query($xpath);
                $airs = [];

                foreach ($nodes as $root) {
                    if ($rl = $this->http->FindSingleNode(".//text()[normalize-space(.)='Airline Confirmation:']/following::text()[normalize-space(.)][1]", $root)) {
                        $airs[$rl][] = $root;
                    }
                }

                foreach ($airs as $rl=>$roots) {
                    $it = [];

                    $it['Kind'] = "T";

                    // RecordLocator
                    $it['RecordLocator'] = $rl;

                    // TripNumber
                    // Passengers
                    $it['Passengers'] = array_unique(array_filter($this->http->FindNodes("//*[normalize-space(text())='Traveler Information']/ancestor::tr[1]/following-sibling::tr[position()>1][normalize-space(./td[5])]/td[3]")));

                    // AccountNumbers
                    // Cancelled
                    if (count($airs) == 1) {
                        // TotalCharge
                        $it['TotalCharge'] = cost($this->getField("Flight Total"));

                        // BaseFare
                        $it['BaseFare'] = cost($this->http->FindSingleNode("//tr[normalize-space(.)='Flight Price Details']/following-sibling::tr[./td[1][normalize-space(.)='Subtotal']][1]/td[2]"));

                        // Currency
                        $it['Currency'] = currency($this->getField("Flight Total"));

                        // Tax
                        $it['Tax'] = cost($this->http->FindSingleNode("//tr[normalize-space(.)='Flight Price Details']/following-sibling::tr[./td[1][normalize-space(.)='Taxes and Fees']][1]/td[2]"));
                    }

                    // SpentAwards
                    // EarnedAwards
                    // Status
                    // ReservationDate
                    // NoItineraries
                    // TripCategory

                    foreach ($roots as $root) {
                        $date = strtotime($this->http->FindSingleNode("./td[2]//tr[5]/../tr[1]", $root));

                        $itsegment = [];
                        // FlightNumber
                        $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]//tr[3]/../tr[1]/td[2]", $root, true, "#Flight (\d+)#");

                        // DepCode
                        $itsegment['DepCode'] = $this->http->FindSingleNode("./td[2]//tr[5]/../tr[3]", $root, true, "#[A-Z]{3}#");

                        // DepName
                        // DepDate
                        $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]//tr[5]/../tr[3]", $root, true, "#\d+:\d+\s+[ap]m#"), $date);

                        // ArrCode
                        $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[2]//tr[5]", $root, true, "#[A-Z]{3}#");

                        // ArrName
                        // ArrDate
                        $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[2]//tr[5]", $root, true, "#\d+:\d+\s+[ap]m#"), $date);

                        // AirlineName
                        $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]//tr[3]/../tr[1]/td[2]/strong", $root);

                        // Operator
                        $itsegment['Operator'] = $this->http->FindSingleNode("./td[1]//tr[3]//text()[normalize-space(.)='Operated by']/following::text()[normalize-space(.)][1]", $root);

                        // Aircraft
                        $itsegment['Aircraft'] = $this->http->FindSingleNode("./td[1]//tr[3]/../tr[1]/td[2]", $root, true, "#Aircraft\s*:\s*(.+)#");

                        // TraveledMiles
                        // Cabin
                        // BookingClass
                        // PendingUpgradeTo
                        // Seats
                        // Duration
                        $itsegment['Duration'] = $this->http->FindSingleNode("./preceding::tr[1]/td[3]", $root, true, "#Travel Time\s*:\s*(.+)#");

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

                $xpath = "//*[normalize-space(.)='Car Details']/ancestor::tr[2]/following-sibling::tr[1]";
                $nodes = $this->http->XPath->query($xpath);

                foreach ($nodes as $root) {
                    $it = [];

                    $it['Kind'] = "L";

                    // Number
                    $it['Number'] = $this->getField("Car Confirmation:", $root);
                    // TripNumber
                    // PickupDatetime
                    $it['PickupDatetime'] = strtotime(str_replace(" - ", ", ", $this->getField("Car Pick-Up", $root)));

                    // PickupLocation
                    $it['PickupLocation'] = $this->getField("Location:", $root);

                    // DropoffDatetime
                    $it['DropoffDatetime'] = strtotime(str_replace(" - ", ", ", $this->getField("Car Drop-Off", $root)));

                    // DropoffLocation
                    $it['DropoffLocation'] = $it['PickupLocation'];

                    // PickupPhone
                    $it['PickupPhone'] = $this->getField("Phone", $root);

                    // PickupFax
                    // PickupHours
                    // DropoffPhone
                    // DropoffHours
                    // DropoffFax
                    // RentalCompany
                    $it['RentalCompany'] = $this->http->FindSingleNode(".//text()[contains(., 'Vehicle Provider:')]", $root, true, "#Vehicle Provider:\s*(.+)#");

                    // CarType
                    $it['CarType'] = $this->http->FindSingleNode("(.//*[normalize-space(.)='Driver']/ancestor::tr[1]/following-sibling::tr[1]/td[1]//text()[normalize-space(.)])[1]", $root);

                    // CarModel
                    $it['CarModel'] = $this->http->FindSingleNode("(.//*[normalize-space(.)='Driver']/ancestor::tr[1]/following-sibling::tr[1]/td[1]//text()[normalize-space(.)])[2]", $root);

                    // CarImageUrl
                    $it['CarImageUrl'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='Phone']//following::img[1]/@src", $root);

                    // RenterName
                    $it['RenterName'] = $this->http->FindSingleNode(".//*[normalize-space(.)='Driver']/ancestor::tr[1]/following-sibling::tr[1]/td[2]", $root);

                    // PromoCode
                    // TotalCharge
                    $it['TotalCharge'] = cost($this->http->FindSingleNode(".//text()[normalize-space(.)='Amount to be paid:']/following::strong[1]", $root));

                    // Currency
                    $it['Currency'] = currency($this->http->FindSingleNode(".//text()[normalize-space(.)='Amount to be paid:']/following::strong[1]", $root));

                    // TotalTaxAmount
                    $it['TotalTaxAmount'] = cost($this->http->FindSingleNode("//tr[normalize-space(.)='Car Rental Details']/following-sibling::tr[./td[1][normalize-space(.)='Taxes and Surcharges']][1]/td[2]"));

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

                //################
                //##   HOTEL   ###
                //################

                $xpath = "//*[normalize-space(.)='Hotel Details']/ancestor::tr[2]/following-sibling::tr[1]";
                $nodes = $this->http->XPath->query($xpath);

                foreach ($nodes as $root) {
                    $it = [];

                    $it['Kind'] = "R";

                    // ConfirmationNumber
                    $it['ConfirmationNumber'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='Check-In Confirmation']/following::strong[1]", $root);

                    // TripNumber
                    // ConfirmationNumbers

                    // HotelName
                    $it['HotelName'] = $this->http->FindSingleNode("(.//text()[normalize-space(.)])[1]", $root);

                    // 2ChainName

                    // CheckInDate
                    $it['CheckInDate'] = strtotime(str_replace(" - ", ", ", $this->getField("Hotel Check-In", $root)));

                    // CheckOutDate
                    $it['CheckOutDate'] = strtotime(str_replace(" - ", ", ", $this->getField("Hotel Check-Out", $root)));

                    // Address
                    $it['Address'] = $this->http->FindSingleNode(".//text()[contains(., 'Phone:')]/ancestor::td[1]", $root, true, "#(.*?)\s*Phone:#");

                    // DetailedAddress

                    // Phone
                    $it['Phone'] = $this->http->FindSingleNode(".//text()[contains(., 'Phone:')]", $root, true, "#Phone:\s*(.+)#");

                    // Fax
                    // GuestNames
                    $it['GuestNames'] = [$this->http->FindSingleNode(".//*[normalize-space(text())='Guest Name']/ancestor::tr[1]/following-sibling::tr[1]/td[2]", $root)];

                    // Guests
                    $it['Guests'] = $this->getField("Guest(s):", $root);

                    // Kids
                    // Rooms
                    $it['Rooms'] = $this->getField("Room(s):", $root);

                    // Rate
                    // RateType

                    // CancellationPolicy
                    $it['CancellationPolicy'] = str_replace("\n", " ", $this->http->FindSingleNode("//*[contains(text(), 'Cancellation information')]/parent::*/parent::*/following-sibling::*[1]/td/p"));

                    // RoomType
                    $it['RoomType'] = $this->http->FindSingleNode(".//*[normalize-space(text())='Guest Name']/ancestor::tr[1]/following-sibling::tr[2]", $root);

                    // RoomTypeDescription
                    // Cost
                    $it['Cost'] = cost($this->http->FindSingleNode("//tr[normalize-space(.)='Hotel Price Details']/following-sibling::tr[./td[1][normalize-space(.)='Subtotal']][1]/td[2]"));

                    // Taxes
                    $it['Taxes'] = cost($this->http->FindSingleNode("//tr[normalize-space(.)='Hotel Price Details']/following-sibling::tr[./td[1][normalize-space(.)='Taxes & Fees']][1]/td[2]"));

                    // Total
                    $it['Total'] = cost($this->http->FindSingleNode("//td[normalize-space(.)='Hotel Total']/following-sibling::td[1]"));

                    // Currency
                    $it['Currency'] = currency($this->http->FindSingleNode("//td[normalize-space(.)='Hotel Total']/following-sibling::td[1]"));

                    // SpentAwards
                    // EarnedAwards
                    // AccountNumbers
                    // Status
                    // Cancelled
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

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        // get html by query
        $root = $this->http->XPath->query("(//*[normalize-space(.)='Booking Confirmation']/ancestor::tr[2])[1]")->item(0);

        if (isset($root->ownerDocument)) {
            $body = $root->ownerDocument->saveHTML($root);
            $this->http->SetBody($body);
        }
        $processor = $this->processors["html"];
        $processor($itineraries);

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

    private function getField($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}']/following::text()[normalize-space(.)][1])[{$n}]", $root);
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
            "#^\w+\s+(\d+)\s+(\w+)\s+(\d{4}),\s+(\d+:\d+)$#",
            "#[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4}),\s+(\d+:\d+)#",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
        ];

        return en(preg_replace($in, $out, $str));
    }

    private function re($re, $str = null, $c = 1)
    {
        if (is_int($re) && $str === null) {
            if (isset($this->lastre[$re])) {
                return $this->lastre[$re];
            } else {
                return null;
            }
        }

        preg_match($re, $str, $m);
        $this->lastre = $m;

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
