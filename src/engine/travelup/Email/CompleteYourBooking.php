<?php

namespace AwardWallet\Engine\travelup\Email;

use AwardWallet\Engine\MonthTranslate;

class CompleteYourBooking extends \TAccountChecker
{
    public $mailFiles = "travelup/it-11210693.eml, travelup/it-11223509.eml, travelup/it-6569323.eml, travelup/it-6640023.eml, travelup/it-6724186.eml, travelup/it-6725013.eml, travelup/it-6733554.eml, travelup/it-8918664.eml, travelup/it-8934270.eml, travelup/it-8964270.eml, travelup/it-9090555.eml";
    public $reFrom = "bookings@travelup.co.uk";
    public $reSubject = [
        "en"  => "Complete your booking with Travelup",
        "en2" => "Your Booking Details from TravelUp",
    ];
    public $reBody = 'travelup';
    public $reBody2 = [
        "en" => ["Flight Segments", 'Thank you for booking with travelup', 'Flight Details', 'Hotel Information'],
    ];

    public static $dictionary = [
        "en" => [
            "Total Price (Including Tax)" => ["Total Price (Including Tax)", "Total Payable to TravelUp"],
        ],
    ];

    public $total;

    public $lang = "en";

    private $date = null;

    public function parseHtml(&$itineraries)
    {
        // total
        $nodes = $this->http->XPath->query("//text()[" . $this->eq($this->t("Total Price (Including Tax)")) . "]/ancestor::tr[1]/preceding-sibling::tr");
        $sum = [];
        $len = $nodes->length;

        foreach ($nodes as $i => $root) {
            $amount = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("#\d#", $amount)) {
                $n = trim($this->http->FindSingleNode("./td[1]", $root));
                $sum[$n] = $this->amount($amount);

                if ($i == $len - 1) {
                    $total[$name] = $sum;
                }
            } else {
                if (!empty($sum)) {
                    $total[$name] = $sum;
                }
                $name = trim($this->http->FindSingleNode("./td[1]", $root));
                $sum = [];
            }
        }
        $total['all'] = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Price (Including Tax)")) . "]/ancestor::tr[1]/td[2]"));
        $total['Currency'] = $this->currency($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Price (Including Tax)")) . "]/ancestor::tr[1]/td[2]"));

        if (empty($total['Currency'])) {
            $total['Currency'] = $this->currency($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Total Price (Including Tax)")) . "]/ancestor::table[1]//text()[starts-with(normalize-space(), 'Payable')])[1]", null, true, "#Payable\s*\((.+)\)#"));
        }

        $xpath = "//text()[" . $this->eq(["Depart:", "Departure:"]) . "]/ancestor::*[contains(., \"From\") and contains(., \"Flight No\")][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->logger->info("Flight segments root not found: $xpath");
        } else {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            // Status
            $it['TripNumber'] = $this->getNode('Booking No');

            if (empty($it['TripNumber'])) {
                $it['TripNumber'] = $this->http->FindSingleNode("//tr[contains(., 'Your Booking No') and not(.//tr)]/following-sibling::tr[1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");
            }

            $it['RecordLocator'] = $this->getNode('Reservation No', true, "#^\s*([A-Z\d]{5,})\s*$#");

            if (empty($it['RecordLocator']) && $this->http->FindSingleNode("(//text()[contains(normalize-space(), 'card is not charged') or contains(normalize-space(), 'error message received from our payment gateway')])[1]")) {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
                $it['Status'] = "payment error";
            }

            if (empty($it['RecordLocator']) && $this->http->FindSingleNode("(//text()[contains(normalize-space(), 'opportunity to complete the booking now')])[1]")) {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
                $it['Status'] = "not completed";
            }

            // TripNumber
            // Passengers
            $it['Passengers'] = array_filter($this->http->FindNodes("//td[starts-with(normalize-space(.), 'Name:') and not(.//td)]/following-sibling::td[1]"));

            // TicketNumbers
            // AccountNumbers
            $it['AccountNumbers'] = array_filter($this->http->FindNodes("//td[starts-with(normalize-space(.), 'Frequent Flyer:') and not(.//td)]/following-sibling::td[1]"));

            // Cancelled
            // TotalCharge
            if (isset($total['Flight'])) {
                $it['TotalCharge'] = array_sum($total['Flight']);
            }
            $it['Currency'] = $total['Currency'];

            // Tax
            // SpentAwards
            // EarnedAwards
            // ReservationDate
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->getNode('Booking Date', true)));
            // NoItineraries
            // TripCategory
        }

        foreach ($nodes as $root) {
            $itsegment = [];

            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#^[A-Z\d]{2}\s*(\d+)#", $this->nextText("Flight No:", $root));

            // DepCode
            $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", $this->nextText("From:", $root));

            // DepName
            $itsegment['DepName'] = $this->re("#(.*?)\s*\([A-Z]{3}\)#", $this->nextText("From:", $root));

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->re("#Terminal\s*[:\s]*([A-Z\d]{1,3})#", $this->nextText(["Depart:", "Departure:"], $root));

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#(.*?)(?:\s+Terminal|$)#", $this->nextText(["Depart:", "Departure:"], $root))));

            // ArrCode
            $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})\)#", $this->nextText("To:", $root));

            // ArrName
            $itsegment['ArrName'] = $this->re("#(.*?)\s*\([A-Z]{3}\)#", $this->nextText("To:", $root));

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->re("#Terminal\s*[:\s]*([A-Z\d]{1,3})#", $this->nextText(["Arrival:", 'Arrive:'], $root));

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->re("#(.*?)(?:\s+Terminal|$)#", $this->nextText(["Arrival:", 'Arrive:'], $root))));

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#^([A-Z\d]{2})\s*\d+#", $this->nextText("Flight No:", $root));

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#[A-Z\d]{2}\s*\d+\s+([A-Z\s]+)\s*(?:\(\w\))?#i", $this->nextText("Flight No:", $root));

            if (empty($itsegment['Cabin'])) {
                $itsegment['Cabin'] = $this->re("#([A-Z\s]+)\s*(?:\s*\(\w\))?#i", $this->nextText("Flight No:", $root, 2));
            }

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#\((\w)\)#", $this->nextText("Flight No:", $root, 2));

            if (empty($itsegment['BookingClass'])) {
                $itsegment['BookingClass'] = $this->re("#\((\w)\)#", $this->nextText("Flight No:", $root));
            }

            // PendingUpgradeTo
            // Seats
            // Duration
            $itsegment['Duration'] = $this->nextText(["Duration", 'Duration:'], $root);
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }

        if (!empty($it)) {
            $itineraries[] = $it;
        }

        /****** CARS ******/
        $xpath = "//text()[" . $this->eq("Car Booking ID:") . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->info("Car segments root not found: $xpath");
        }

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "L";

            // Number
            $it['Number'] = $this->nextText("Car Booking ID:", $root);

            if (trim($it['Number']) == 'Reservation Failed') {
                // Status
                // Cancelled
                $it['Status'] = $it['Number'];
                $it['Cancelled'] = true;
                $it['Number'] = CONFNO_UNKNOWN;
            }

            // TripNumber
            $it['TripNumber'] = $this->nextText("Booking No:");

            // PickupDatetime
            $it['PickupDatetime'] = strtotime($this->re("#(.*?)/#", $this->nextText("Pick up:", $root)));

            // PickupLocation
            $it['PickupLocation'] = $this->re("#/(.+)#", $this->nextText("Pick up:", $root));

            // DropoffDatetime
            $it['DropoffDatetime'] = strtotime($this->re("#(.*?)/#", $this->nextText("Drop off:", $root)));

            // DropoffLocation
            $it['DropoffLocation'] = $this->re("#/(.+)#", $this->nextText("Drop off:", $root));

            // PickupPhone
            // PickupFax
            // PickupHours
            // DropoffPhone
            // DropoffHours
            // DropoffFax
            // RentalCompany
            // CarType
            $it['CarType'] = $this->nextText("Transmission:", $root);

            // CarModel
            $it['CarModel'] = $this->nextText("Car Model:", $root);

            // CarImageUrl
            // RenterName
            // PromoCode

            // TotalCharge
            // Currency
            // Fees

            if (isset($total['Car Rental'])) {
                $it['TotalCharge'] = array_sum($total['Car Rental']);
                $it['Currency'] = $total['Currency'];
            }
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
            // ReservationDate
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->getNode('Booking Date', true)));
            // NoItineraries
            $itineraries[] = $it;
        }

        /****** HOTELS ******/
        $xpath = "//text()[" . $this->eq("Room Details:") . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->info("Hotel segments root not found: $xpath");
        }

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "R";
            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->nextText("Supplier Reference:", $root);
            // TripNumber
            $it['TripNumber'] = $this->nextText("Booking Reservation:");
            // ConfirmationNumbers
            // HotelName
            $it['HotelName'] = $this->nextText("Property Name:", $root);
            // Address
            $it['Address'] = $it['HotelName'];

            // 2ChainName
            // CheckInDate
            $it['CheckInDate'] = strtotime($this->nextText("Check in Date:", $root));
            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->nextText("Check out Date:", $root));
            // DetailedAddress
            // Phone
            // Fax
            // GuestNames
            $it['GuestNames'] = array_filter($this->http->FindNodes("//text()[normalize-space() = 'Guest Details']/following::table[1]//td[contains(normalize-space(), 'Guest Name') and not(.//td)]/following-sibling::td[1]"));

            // Guests
            $it['Guests'] = count($it['GuestNames']);

            // Kids
            // Rooms
            $it['Rooms'] = (int) $this->nextText("No. of Rooms:", $root);
            // Rate
            // RateType
            // CancellationPolicy
            $it['CancellationPolicy'] = $this->http->FindSingleNode("//text()[normalize-space() = 'Cancellation Policy']/ancestor::*[position()<5][local-name()='tr'][1]/following-sibling::tr[normalize-space()][1]");

            // RoomType
            $it['RoomType'] = $this->nextText("Room Details:", $root);
            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            // Currency
            if (isset($total['Hotel'])) {
                $it['Total'] = array_sum($total['Hotel']);
                $it['Currency'] = $total['Currency'];
            }
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // Cancelled
            // ReservationDate
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->getNode('Booking Date:', true)));
            // NoItineraries
            $itineraries[] = $it;
        }

        if (count($itineraries) == 1 && isset($itineraries[0]['Kind'])) {
            switch ($itineraries[0]['Kind']) {
                case 'T':
                case 'L':
                    $itineraries[0]['TotalCharge'] = $total['all'];
                    $itineraries[0]['Fees'] = $total['Other Charges'] ?? null;
                    $itineraries[0]['Currency'] = $total['Currency'];

                    break;

                case 'R':
                    $itineraries[0]['Total'] = $total['all'];
                    $itineraries[0]['Currency'] = $total['Currency'];

                    break;
            }
        } elseif (count($itineraries) > 1) {
            $this->total['Total'] = $total['all'];
            $this->total['Currency'] = $total['Currency'];
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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
            if (is_string($re) && stripos($body, $re) !== false) {
                return true;
            } elseif (is_array($re)) {
                foreach ($re as $r) {
                    if (stripos($body, $r) !== false) {
                        return true;
                    }
                }
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

        foreach ($this->reBody2 as $lang => $re) {
            if (is_string($re) && strpos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            } elseif (is_array($re)) {
                foreach ($re as $r) {
                    if (stripos($body, $r) !== false) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }
        }

        $this->parseHtml($itineraries);
        $result = [
            'emailType'  => 'CompleteYourBooking' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if (!empty($this->total)) {
            $result['parsedData']['TotalCharge'] = [
                "Amount"   => $this->total['Total'],
                "Currency" => $this->total['Currency'],
            ];
        }

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

    private function getNode($str, $allowTr = false, $regexp = null)
    {
        if (!$allowTr) {
            return $this->http->FindSingleNode("//td[contains(., '{$str}') and not(.//td)]/following-sibling::td[1]", null, true, $regexp);
        } else {
            return $this->orval(
                $this->http->FindSingleNode("//td[contains(., '{$str}') and not(.//td)]/following-sibling::td[1]", null, true, $regexp),
                $this->http->FindSingleNode("//tr[contains(., '{$str}') and not(.//tr)]/following-sibling::tr[1]", null, true, $regexp)
            );
        }
    }

    private function orval(...$nodes)
    {
        foreach ($nodes as $node) {
            if (!empty($node)) {
                return $node;
            }
        }

        return null;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
        // $this->http->log($str);
        $in = [
            "#^(\d+)\s+([^\d\s]+)\s+(\d{4})\s+(\d+:\d+)$#", // 04 Sep 2014 12:35
            '/^(\d+)\/(\D+)\/(\d+)\s+(\d+:\d+)$/', // 11/Jul/2017 16:20
        ];
        $out = [
            "$1 $2 $3, $4",
            '$1 $2 $3, $4',
        ];
        $str = preg_replace($in, $out, $str);

        if ($this->lang !== 'en' && preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
