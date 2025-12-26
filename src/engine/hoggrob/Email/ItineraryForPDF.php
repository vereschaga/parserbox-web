<?php

namespace AwardWallet\Engine\hoggrob\Email;

class ItineraryForPDF extends \TAccountChecker
{
    public $mailFiles = "hoggrob/it-5504935.eml, hoggrob/it-5504943.eml";

    public $date;
    public $reFrom = "@hrgworldwide.com";
    public $reSubject = [
        "Itinerary for", //en
    ];
    public $reBody = ['Hogg Robinson Germany'];
    public $reBody2 = [
        "en" => ['Airline Code', 'Amadeus-Code'],
    ];

    public static $dictionary = [
        "en" => [
        ],
    ];

    public $lang = "en";
    public $pdf;

    public $dateTimeToolsMonths = [
        "en" => [
            "january"   => 0,
            "february"  => 1,
            "march"     => 2,
            "april"     => 3,
            "may"       => 4,
            "june"      => 5,
            "july"      => 6,
            "august"    => 7,
            "september" => 8,
            "october"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    public function parseHtml(&$itineraries)
    {
        $tripNumber = $this->pdf->FindSingleNode("//text()[normalize-space(.)='Amadeus-Code']/following::p[1]", null, true, "#[A-Z\d]{5,}#");
        $node = implode(" ", $this->pdf->FindNodes("//text()[normalize-space(.)='Itinerary for']/following::p[position()=1 or position()=2 and normalize-space(.)!='Cost centre']"));

        if (preg_match("#(.+?)(?:\s*Frequent flyers No|$)#", $node, $m)) {
            $pax = $m[1];
        }

        if (preg_match("#Frequent flyers No[\.\:\s]*(.+)#", $node, $m)) {
            $ff = $m[1];
        }
        $dateRes = strtotime($this->pdf->FindSingleNode("//text()[normalize-space(.)='Travel details']/preceding::p[1]"));

        if ($dateRes) {
            $this->date = $dateRes;
        }

        //##################
        //##   FLIGHTS   ###
        //##################
        $xpath = "//text()[normalize-space(.)='Flight']";
        $airs = [];
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            if ($rl = $this->pdf->FindSingleNode("./following::p[3 and contains(.,'Airline Code')]", $root, true, "#:\s*([A-Z\d]{5,})\s*#")) {
                $airs[$rl][] = $root;
            } else {
                $airs[$tripNumber][] = $root;
            }
        }
        /*		if (count($airs) == 1){
                    $node = $this->pdf->FindSingleNode("//*[{$ruleTot}]",null,true,"#:\s+(.+?)\s*$#");
                    $res = $this->getTotalCurrency($node);
                    $tot = $res['Total'];
                    $cur = $res['Currency'];
                }
        */
        foreach ($airs as $rl => $roots) {
            $it = [];

            $it['Kind'] = "T";
            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $tripNumber;
            $it['ReservationDate'] = $dateRes;

            if (isset($pax)) {
                $it['Passengers'] = $pax;
            }

            if (isset($ff)) {
                $it['AccountNumbers'] = $ff;
            }
            /*			if (isset($tot) && isset($cur)) {
                            $it['TotalCharge'] = $tot;
                            $it['Currency'] = $cur;
                        }
            */
            foreach ($roots as $root) {
                $itsegment = [];

                $node = $this->pdf->FindSingleNode("./following::p[2]", $root);

                if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                    $itsegment['FlightNumber'] = $m[2];
                    $itsegment['AirlineName'] = $m[1];
                }

                $nodes = $this->getFields("from", $root, 1, "Latest");

                if (isset($nodes[0])) {
                    $itsegment['DepName'] = $nodes[0];
                }

                if (isset($nodes[1])) {
                    $itsegment['DepartureTerminal'] = $nodes[1];
                }

                $nodes = $this->getFields("Arrival", $root, 1, "Date");

                if (isset($nodes[0])) {
                    $itsegment['ArrName'] = $nodes[0];
                }

                if (isset($nodes[1])) {
                    $itsegment['ArrivalTerminal'] = $nodes[1];
                }

                $node = $this->getField("seat", $root);

                if (preg_match("#(\d+[A-Z])(\s*Nonsmoke)?#i", $node, $m)) {
                    $itsegment['Seats'] = $m[1];

                    if (isset($m[2]) && stripos($m[2], "Nonsmoke") !== false) {
                        $itsegment['Smoking'] = false;
                    }
                }
                $itsegment['Duration'] = $this->getField("Duration", $root);
                $it['Status'] = $this->getField("Status", $root);
                $node = $this->getField("Stops", $root);
                $itsegment['Stops'] = (strtolower($node) == $this->t('non-stop')) ? 0 : (preg_match("#\d#", $node, $n) ? $n[0] : null);
                $itsegment['Aircraft'] = $this->getField("Aircraft type", $root);
                $node = $this->getField("Date/Time", $root, 1);
                $itsegment['DepDate'] = strtotime($this->normalizeDate($node));
                $node = $this->getField("Date/Time", $root, 2);
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($node));
                $node = $this->getField("Booking Class", $root);

                if (preg_match("#^\s*(.+?)\s*\(\s*([A-Z]{1,2})\s*\)\s*$#", $node, $m)) {
                    $itsegment['Cabin'] = $m[1];
                    $itsegment['BookingClass'] = $m[2];
                }

                if (isset($itsegment['AirlineName']) && isset($itsegment['FlightNumber'])) {
                    $xp = "(//text()[starts-with(normalize-space(.),'{$itsegment['AirlineName']}') and contains (.,'{$itsegment['FlightNumber']}')])[1]/preceding::p[1]";
                    $node = $this->pdf->FindSingleNode($xp);

                    if (preg_match("#^\s*([A-Z]{3})\s*-\s*([A-Z]{3})\s*$#", $node, $m)) {
                        $itsegment['DepCode'] = $m[1];
                        $itsegment['ArrCode'] = $m[2];
                    }
                }

                $it['TripSegments'][] = $itsegment;
            }

            $it = array_filter($it);
            $itineraries[] = $it;
        }

        //#################
        //##    Cars    ###
        //#################
        $xpath = "//text()[normalize-space(.)='Rental car']";
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];
            $it['Kind'] = "L";
            $it['TripNumber'] = $tripNumber;
            $it['RentalCompany'] = implode(" ", $this->pdf->FindNodes("./following::p[(position()=1 or position()=2) and not(starts-with(normalize-space(.),'Name'))]", $root));

            $it['RenterName'] = $this->getFields("Name", $root);
            $it['Number'] = $this->getField("Confirmation Nr", $root);

            $it['PickupLocation'] = $this->getField("Pick-up location", $root);
            $it['PickupDatetime'] = strtotime($this->normalizeDate($this->getField("Pickup-at", $root)));
            $it['DropoffLocation'] = $it['PickupLocation'];
            $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->getField("Latest Drop-off", $root)));
            $it['Status'] = $this->getField("Status", $root);

            $node = $this->getField("Estimated Total", $root);
            $res = $this->getTotalCurrency($node);
            $it['TotalCharge'] = $res['Total'];
            $it['Currency'] = $res['Currency'];
            $it['CarType'] = $this->getField("Vehicle Type", $root);

            $itineraries[] = $it;
        }
        //###################
        //##    HOTELS    ###
        //###################
        $xpath = "//text()[normalize-space(.)='Hotel']";
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "R";
            $it['TripNumber'] = $tripNumber;

            $it['ConfirmationNumber'] = $this->getField("Confirmation Nr", $root);
            $it['HotelName'] = $this->pdf->FindSingleNode("./following::p[1]", $root);
            $it['Address'] = implode(" ", $this->pdf->FindNodes("./following::p[(position()=2 or position()=3) and not(starts-with(normalize-space(.),'Reservation'))]", $root));

            $it['CheckInDate'] = strtotime($this->normalizeDate($this->getField("Arrival", $root)));
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->getField("Departure", $root)));

            $it['Phone'] = $this->getField("Phone", $root);
            $it['Fax'] = $this->getField("Fax", $root);
            $it['Status'] = $this->getField("Room status", $root);
            $it['RoomType'] = $this->getField("Roomtype", $root);
            $it['Rate'] = $this->getField("Rate", $root, 1);
            $it['RoomTypeDescription'] = $this->getField("Rate Description", $root);
            $it['CancellationPolicy'] = $this->getField("Cancellation Policy", $root);
            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false) {
            foreach ($this->reSubject as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getField($field, $root = null, $num = 1)
    {
        return $this->pdf->FindSingleNode("./following::p[starts-with(normalize-space(.),'{$field}')][{$num}]/following::p[1]", $root);
    }

    public function getFields($field, $root = null, $num = 1, $notfield = "------")
    {
        return $this->pdf->FindNodes("./following::p[starts-with(normalize-space(.),'{$field}')][{$num}]/following::p[(position()=1 or position()=2) and not(starts-with(normalize-space(.),'{$notfield}'))]", $root);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('[\w\d]+\.pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            $flag = false;

            foreach ($this->reBody as $reBody) {
                if (stripos($text, $reBody) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reBody2 as $re) {
                    foreach ($re as $r) {
                        if (stripos($text, $r) !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        if (!$this->getPDFs($parser)) {
            return false;
        }

        $itineraries = [];

        foreach ($this->reBody2 as $lang => $re) {
            foreach ($re as $r) {
                if (strpos($this->pdf->Response["body"], $r) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'ItineraryForPDF',
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
    }

    private function getPDFs(\PlancakeEmailParser $parser)
    {
        if (isset($this->pdf)) {
            return true;
        }
        $pdfs = $parser->searchAttachmentByName('[\w\d]+\.pdf');

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html_ = '';

            foreach ($pdfs as $pdf) {
                if (($html_ .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                } else {
                    return null;
                }
            }
            $NBSP = chr(194) . chr(160);
            $ht = html_entity_decode($html_);
            $html_ = str_replace($NBSP, ' ', $ht);
            $this->pdf->SetBody($html_);

            return true;
        } else {
            return null;
        }
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
        $year = date('Y', $this->date);
        $in = [
            "#^(\d+)\s*(\w+)\s*(\d+:\d+)$#",
            "#^(\d+)\s*(\w+)$#",
        ];
        $out = [
            "$1 $2 $year $3",
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s-\./]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        $dd = $str;

        return $dd;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(' ', '', $m['t']);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
