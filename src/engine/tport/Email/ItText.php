<?php

namespace AwardWallet\Engine\tport\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class ItText extends \TAccountChecker
{
    public $mailFiles = "tport/it-1.eml, tport/it-12675382.eml, tport/it-12675441.eml, tport/it-1550889.eml, tport/it-1550890.eml, tport/it-1550893.eml, tport/it-1550894.eml, tport/it-1673135.eml, tport/it-1675259.eml, tport/it-1706759.eml, tport/it-2.eml, tport/it-2107962.eml, tport/it-2165578.eml, tport/it-2240358.eml, tport/it-3.eml, tport/it-3703316.eml, tport/it-4.eml, tport/it-5.eml, tport/it-6.eml, tport/it-6261128.eml, tport/it-7.eml";

    public $lang = "en";
    private $reFrom = "@travelport.com";
    private $reSubject = [
        "en"=> "Agency confirmation number",
    ];
    private $reBody = 'Travelport';
    private $reBody2 = [
        "en"=> "Itinerary Information",
    ];

    private static $dictionary = [
        "en" => [
            "Pick-Up:" => ["Pick-Up:", "Pick Up:"],
            "Drop-Off:"=> ["Drop-Off:", "Drop Off:"],
        ],
    ];
    private $date = null;
    private $subject = "";

    public function parseHtml()
    {
        $itineraries = [];
        $travelers = [$this->http->FindSingleNode("//*[contains(text(), 'Traveler Name:') or contains(text(), 'Traveller Name:')]", null, true, "#:\s(.+)#")];
        $tripNo = $this->re("#Agency confirmation number (.*?) -#", $this->subject);

        $flightNodes = $this->http->XPath->query("//text()[" . $this->eq("Flight Information") . "]/ancestor::*[" . $this->contains("Departure Flight") . "][1]");
        $carNodes = $this->http->XPath->query("//text()[" . $this->eq("Car Information") . "]/ancestor::*[" . $this->contains($this->t("Pick-Up:")) . "][1]");
        $hotelNodes = $this->http->XPath->query("//text()[" . $this->eq("Hotel Information") . "]/ancestor::*[" . $this->contains("Hotel name:") . "][1]");

        //##################
        //##   FLIGHTS   ###
        //##################

        $airs = [];

        foreach ($flightNodes as $itRoot) {
            $itText = $this->toText($itRoot->ownerDocument->saveHTML($itRoot));

            $segments = $this->split("#(Departure Flight|Changes Plane)#", $itText);

            foreach ($segments as $stext) {
                if (!$airline = $this->re("#\n(.*?) \#\d+(?:\s+Operated by:|\n)#", $stext)) {
                    $this->logger->info("airline not matched");

                    return;
                }

                if (!$rl = $this->re("#$airline Confirmation Number:\s+([A-Z\d]+)\n#s", $itText)) {
                    $this->logger->info("RL not matched for " . $airline);
                    $rl = CONFNO_UNKNOWN;
                }
                $airs[$rl][] = $stext;
            }
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $tripNo;

            // Passengers
            $it['Passengers'] = $travelers;

            // TicketNumbers
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

            foreach ($segments as $stext) {
                $it['Status'] = $this->re("#Status:\s*([^\n]+)#", $stext);

                $itsegment = [];

                if (preg_match("#\n(?<AirlineName>.*?) \#(?<FlightNumber>\d+)(?:\s+Operated by:\s+(?<Operator>.+)|\n)#", $stext, $m)) {
                    // FlightNumber
                    $itsegment['FlightNumber'] = $m['FlightNumber'];

                    // AirlineName
                    $itsegment['AirlineName'] = $m['AirlineName'];

                    // Operator
                    if (isset($m['Operator'])) {
                        $itsegment['Operator'] = $m['Operator'];
                    }
                }

                if (preg_match("#Leaves\s+(?<Name>\S.*?)\s+\((?<Code>[A-Z]{3})\)\s+" .
                "(?<Date>\S[^\n]+)\s+" .
                "(?<Time>\d+:\d+(?: [AP]M)?)#s", $stext, $m)) {
                    // DepCode
                    $itsegment['DepCode'] = $m['Code'];

                    // DepName
                    $itsegment['DepName'] = $m['Name'];

                    // DepartureTerminal
                    // DepDate
                    $itsegment['DepDate'] = $this->normalizeDate($m['Date'] . ' ' . $m['Time']);
                }

                if (preg_match("#Arrives\s+(?<Name>\S.*?)\s+\((?<Code>[A-Z]{3})\)\s+" .
                "(?<Date>\S[^\n]+)\s+" .
                "(?<Time>\d+:\d+(?: [AP]M)?)#s", $stext, $m)) {
                    // ArrCode
                    $itsegment['ArrCode'] = $m['Code'];

                    // ArrName
                    $itsegment['ArrName'] = $m['Name'];

                    // ArrivalTerminal
                    // ArrDate
                    $itsegment['ArrDate'] = $this->normalizeDate($m['Date'] . ' ' . $m['Time']);
                }

                // Aircraft
                $itsegment['Aircraft'] = $this->re("#Equipment Type:[^\n\S]*([^\n]+)#", $stext);

                // TraveledMiles
                // AwardMiles
                if (preg_match("#\nClass:\s+(?<Cabin>.*?) \((?<BookingClass>[A-Z]) Class\)\n#", $stext, $m)) {
                    // Cabin
                    $itsegment['Cabin'] = $m['Cabin'];

                    // BookingClass
                    $itsegment['BookingClass'] = $m['BookingClass'];
                }
                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = [$this->re("#Seat:\s*(\d+[A-Z])\s#", $stext)];

                // Duration
                $itsegment['Duration'] = $this->re("#Total Travel Time:\s*([^\n]+)#", $stext);

                // Meal
                $itsegment['Meal'] = $this->re("#Meal:[^\n\S]*([^\n]+)#", $stext);

                // Smoking
                // Stops

                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }

        //#################
        //##   HOTELS   ###
        //#################
        foreach ($hotelNodes as $itRoot) {
            $itText = $this->toText($itRoot->ownerDocument->saveHTML($itRoot));

            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->re("#Confirmation Number:\s+(.+)#", $itText);

            // TripNumber
            $it['TripNumber'] = $tripNo;

            // ConfirmationNumbers

            // HotelName
            $it['HotelName'] = $this->re("#Hotel name:\s+(.+)#", $itText);

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = $this->normalizeDate($this->re("#Check in:\s+(.+)#", $itText));

            // CheckOutDate
            $it['CheckOutDate'] = $this->normalizeDate($this->re("#Check out:\s+(.+)#", $itText));

            // Address
            $it['Address'] = $this->re("#Address:\s+(.+)#", $itText);

            // DetailedAddress

            if (preg_match("#Phone/Fax:\s+(?<Phone>.*)\s*/\s*(?<Fax>.*)#", $itText, $m)) {
                // Phone
                $it['Phone'] = $m['Phone'];

                // Fax
                $it['Fax'] = $m['Fax'];
            }

            // GuestNames
            $it['GuestNames'] = $travelers;

            // Guests
            $it['Guests'] = $this->re("#Number of persons:\s+(.+)#", $itText);

            // Kids
            // Rooms
            // Rate
            $it['Rate'] = $this->re("#Room\s+price:\s+(.*\s+Per\s+Night)#i", $itText);

            // RateType

            // CancellationPolicy
            $it['CancellationPolicy'] = $this->re('#(.*if\s+cancelled\s+within.*|.*cancel\s+day(?:(?s).*?)or\s+partial.*)#i', $itText);

            // RoomType
            $it['RoomType'] = $this->re("#Vendor Note:\s+(.+)#", $itText);

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            // Currency
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            $it['Status'] = $this->re("#Status:\s*([^\n]+)#", $itText);

            // Cancelled
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        //###############
        //##   CARS   ###
        //###############
        foreach ($carNodes as $itRoot) {
            $itText = $this->toText($itRoot->ownerDocument->saveHTML($itRoot));

            $it = [];

            $it['Kind'] = "L";

            // Number
            $it['Number'] = str_replace(" ", "-", $this->re("#Confirmation Number:\s+(.+)#", $itText));

            // TripNumber
            $it['TripNumber'] = $tripNo;

            if (preg_match("#" . $this->opt($this->t("Pick-Up:")) . "\s+(?<Date>[^\n]+)\s+(?<Location>[^\n]+)#s", $itText, $m)) {
                // PickupDatetime
                $it['PickupDatetime'] = $this->normalizeDate($m['Date']);

                // PickupLocation
                $it['PickupLocation'] = $m['Location'];
            }

            if (preg_match("#" . $this->opt($this->t("Drop-Off:")) . "\s+(?<Date>[^\n]+)\s+(?<Location>[^\n]+)#s", $itText, $m)) {
                // DropoffDatetime
                $it['DropoffDatetime'] = $this->normalizeDate($m['Date']);

                // DropoffLocation
                $it['DropoffLocation'] = $m['Location'];

                if ($it['DropoffLocation'] == 'Same Location' && isset($it['PickupLocation'])) {
                    $it['DropoffLocation'] = $it['PickupLocation'];
                }
            }

            // PickupPhone
            // PickupFax
            // PickupHours
            // DropoffPhone
            // DropoffHours
            // DropoffFax
            // RentalCompany
            $it['RentalCompany'] = $this->re("#Agency Name:\s*([^\n]+)#", $itText);

            // CarType
            $it['CarType'] = $this->re("#Car class:\s*([^\n]+)#", $itText);

            // CarModel
            // CarImageUrl
            // RenterName
            if (isset($travelers[0])) {
                $it['RenterName'] = $travelers[0];
            }

            // PromoCode
            // TotalCharge
            $it['TotalCharge'] = $this->amount($this->re("#Price:\s*([^\n]+)#", $itText));

            // Currency
            $it['Currency'] = $this->currency($this->re("#Price:\s*([^\n]+)#", $itText));

            // TotalTaxAmount
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            $it['Status'] = $this->re("#Status:\s*([^\n]+)#", $itText);

            // Cancelled
            // ServiceLevel
            // PricedEquips
            // Discount
            // Discounts
            // Fees
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        return $itineraries;
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
        $this->subject = $parser->getSubject();

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseHtml(),
                'TotalCharge' => [
                    "Amount"   => $this->amount($this->http->FindSingleNode("//text()[" . $this->eq("Itinerary Information") . "]/following::text()[" . $this->starts("Price:") . "]")),
                    "Currency" => $this->currency($this->http->FindSingleNode("//text()[" . $this->eq("Itinerary Information") . "]/following::text()[" . $this->starts("Price:") . "]")),
                ],
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

    public function toText($html)
    {
        $html = preg_replace("#<br/*>#uims", "\n", $html);
        $html = preg_replace("#</?(?:div|p|li)>#", "\n", $html);
        $html = strip_tags($html);
        $html = implode("\n", array_map('trim', explode("\n", $html)));

        return $html;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        $instr = preg_replace("# {2,}#", " ", $instr);
        // $this->http->log($instr);
        $in = [
            "#^[^\s\d]+, ([^\s\d]+) (\d+), (\d{4}) at (\d+:\d+ [AP]M)$#", //Tuesday, Jan 21 2014 at 05:13 PM
            "#^[^\s\d]+, (\d+) ([^\s\d]+), (\d{4}) at (\d+:\d+ [AP]M)$#", //Tuesday, 25 Mar, 2014 at 08:55 AM
            "#^[^\s\d]+, (\d+) ([^\s\d]+), (\d{4}) at (\d+:\d+)$#", //Monday, 03 Nov, 2014 at 07:00
            "#^[^\s\d]+, (\d+) ([^\s\d]+), (\d{4})$#", //Wednesday, 26 Mar, 2014
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $instr);
        // $this->http->log($str);
        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function amount($s)
    {
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
