<?php

namespace AwardWallet\Engine\hoggrob\Email;

class It4587885 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "hoggrob/it-10013146.eml, hoggrob/it-1787398.eml, hoggrob/it-1899234.eml, hoggrob/it-2490530.eml, hoggrob/it-4238789.eml, hoggrob/it-4865035.eml, hoggrob/it-4878884.eml, hoggrob/it-5083186.eml, hoggrob/it-5156411.eml, hoggrob/it-6042743.eml, hoggrob/it-6283424.eml, hoggrob/it-8025007.eml, hoggrob/it-8045016.eml";

    public $reFrom = "@hrgworldwide.com";

    public $reSubject = [
        "en"  => "Turkey trip",
        "en1" => "Confirmation:",
    ];

    public $reBody = ['hrgworldwide.com', 'HRGWORLDWIDE.COM'];

    public $reBody2 = [
        "en" => "Itinerary Summary",
    ];

    public static $dictionary = [
        "en" => [
            'Booking reference'  => ['Booking reference', 'Booking Reference'],
            'Trip locator'       => ['Trip locator', 'Trip Locator'],
            'Date/Time:'         => ['Date/Time:', 'Date/time:'],
            'Check In Date:'     => ['Check In Date:', 'Check In Date :', 'Check in date:'],
            'Check Out Date:'    => ['Check Out Date:', 'Check out date:'],
            'CancellationPolicy' => ['CANCELLATION POLICY', 'Cancellation Information'],
        ],
    ];

    public $lang = "en";

    private $pdfText = '';

    private $pdfDetects = [
        'Please rate your overall satisfaction with the service you received from HRG', // it-8025007.eml
    ];

    private $pdfAirTripSegments = [];

    public function parseHtml(&$itineraries)
    {
        $w = $this->t('Trip locator');

        if (!is_array($w)) {
            $w = [$w];
        }
        $ruleTL = implode(' or ', array_map(function ($s) {
            return "starts-with(normalize-space(.), '{$s}')";
        }, $w));
        $tripNumber = $this->http->FindSingleNode("//text()[{$ruleTL}]/following::text()[normalize-space(.)][1]");

        $w = $this->t('Booking reference');

        if (!is_array($w)) {
            $w = [$w];
        }
        $ruleBR = implode(' or ', array_map(function ($s) {
            return "contains(.,'{$s}')";
        }, $w));

        //##################
        //##   FLIGHTS   ###
        //##################

        //$xpath = "//text()[normalize-space(.)='Air']/ancestor::tr[2]/following-sibling::tr[1]";
        $xpath = "//text()[normalize-space(.)='Air' or starts-with(normalize-space(.),'Flight')]/ancestor::tr[1]/descendant-or-self::tr[{$ruleBR}][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length < 1) {
            $xpath = "//text()[normalize-space(.)='Air' or starts-with(normalize-space(.),'Flight')]/ancestor::tr[1]/following::tr[{$ruleBR}][1]";
            $nodes = $this->http->XPath->query($xpath);
        }
        //		$this->logger->info('XPATH: '. $xpath);
        $airs = [];

        foreach ($nodes as $root) {
            if ($rl = $this->http->FindSingleNode("./descendant::text()[{$ruleBR}]", $root, true, "#:\s*(\w+)#")) {
                //if($rl = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $root, true, "#Booking Reference:\s+(\w+)#"))
                $airs[$rl][] = $root;
            }
        }

        foreach ($airs as $rl => $roots) {
            $it = [];
            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;
            // TripNumber
            $it['TripNumber'] = $tripNumber;
            // Passengers
            $it['Passengers'] = $this->http->FindNodes("//text()[{$ruleTL}]/following::text()[starts-with(normalize-space(.), 'Traveller')]/following::text()[normalize-space(.)][1]");

            // AccountNumbers
            // Cancelled
            // TotalCharge
            $it['TotalCharge'] = $this->cost($this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'TICKETING AMOUNT:')]", null, true, "#TICKETING AMOUNT:\s*(.+)#"));

            // BaseFare
            $it['BaseFare'] = $this->cost($this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'FARE AMOUNT:')]", null, true, "#:\s*(.+)#"));

            // Currency
            $it['Currency'] = $this->currency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'TICKETING AMOUNT:')]", null, true, "#:\s*(.+)#"));

            // Tax
            $it['Tax'] = array_sum(array_filter(array_map([$this, 'cost'], $this->http->FindNodes("//text()[starts-with(normalize-space(.), 'TAX')]", null, "#:\s*(.+)#"))));

            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory

            foreach ($roots as $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $itsegment */
                $itsegment = [];

                // FlightNumber
                // AirlineName
                $node = $this->http->FindSingleNode("./preceding-sibling::tr[1]/descendant::text()[contains(normalize-space(.),'Flight')]", $root);

                if (empty($node)) {
                    $node = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(.),'Flight')]", $root);
                }

                if (preg_match("#Flight\s+\#\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)$#", $node, $m)) {
                    $itsegment['FlightNumber'] = $m[2];
                    $itsegment['AirlineName'] = $m[1];
                }

                // DepCode
                $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", $this->getField(1, "Departing:", $root));

                // DepName
                $itsegment['DepName'] = $this->re("#(.*?)\s*\([A-Z]{3}\)#", $this->getField(1, "Departing:", $root));

                $itsegment['DepartureTerminal'] = $this->re("#\([A-Z]{3}\)\s*,\s*Terminal\s+([A-Z\d]{1,4})#", $this->getField(1, "Departing:", $root));

                // ArrCode
                $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})\)#", $this->getField(3, "Arriving:", $root));

                // ArrName
                $itsegment['ArrName'] = $this->re("#(.*?)\s*\([A-Z]{3}\)#", $this->getField(3, "Arriving:", $root));

                $itsegment['ArrivalTerminal'] = $this->re("#\([A-Z]{3}\)\s*,\s*Terminal\s*([A-Z\d]{1,4})#", $this->getField(3, "Arriving:", $root));

                // DepDate
                $w = $this->t('Date/Time:');

                if (!is_array($w)) {
                    $w = [$w];
                }

                foreach ($w as $item) {
                    $itsegment['DepDate'] = $this->normalizeDate($this->getField(2, $item, $root));

                    if ($itsegment['DepDate'] !== false) {
                        break;
                    }
                }

                // ArrDate
                foreach ($w as $item) {
                    $itsegment['ArrDate'] = $this->normalizeDate($this->getField(4, $item, $root));

                    if ($itsegment['ArrDate'] !== false) {
                        break;
                    }
                }

                if ($itsegment['ArrDate'] === $itsegment['DepDate']) {
                    unset($itsegment['ArrDate']);
                }

                if (!empty($this->pdfText) && empty($itsegment['ArrDate']) && empty($itsegment['ArrCode']) && empty($itsegment['ArrName'])) {
                    $this->pdfAirTripSegments = $this->getAirSegmentsFromPdf($this->pdfText, $itsegment['FlightNumber']);
                }

                // Operator
                // Aircraft
                $itsegment['Aircraft'] = $this->getField(6, "Aircraft:", $root);

                // TraveledMiles

                // Cabin
                // BookingClass
                $w = $this->t('Cabin Class:');

                if (!is_array($w)) {
                    $w = [$w];
                }

                foreach ($w as $item) {
                    $node = $this->getField(7, $item, $root);

                    if (!empty($node)) {
                        $itsegment['Cabin'] = $this->re("#(.*?)\s+-\s+\w$#", $node);
                        $itsegment['BookingClass'] = $this->re("#.*?\s+-\s+(\w)$#", $node);

                        break;
                    }
                }

                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = [$this->getField(8, "Seat:", $root)];
                // Duration
                $itsegment['Duration'] = $this->getField(5, "Duration:", $root);
                // Meal
                $itsegment['Meal'] = $this->getField(8, "Meal:", $root);
                // Smoking
                // Stops
                $itsegment = array_filter($itsegment);
                $it['TripSegments'][] = $itsegment;
            }

            foreach ($this->pdfAirTripSegments as $i => $pdfAirTripSegment) {
                if (
                (!isset($it['TripSegments'][$i]['ArrCode']) || !isset($it['TripSegments'][$i]['ArrName']) || !isset($it['TripSegments'][$i]['ArrDate']))
                && !empty($it['TripSegments'][$i]['FlightNumber'])
                ) {
                    $it['TripSegments'][$i] = $pdfAirTripSegment;
                }
            }

            if (!empty($this->pdfAirTripSegments)) {
                $diffs = $this->arrayDiff($this->pdfAirTripSegments, $it['TripSegments']);
            }

            if (!empty($diffs) && is_array($diffs)) {
                foreach ($diffs as $diff) {
                    $it['TripSegments'][] = $diff;
                }
            }

            // fees for multiple flight reservations
            if (!$it['TotalCharge']) {
                $codes = [];

                foreach ($it['TripSegments'] as $s) {
                    $codes[] = $s['DepCode'];
                    $codes[] = $s['ArrCode'];
                }
                $rule = implode(" or ", array_map(function ($c) {
                    return "contains(., '{$c}')";
                }, array_unique($codes)));

                $nodes = $this->http->XPath->query("//text()[normalize-space(.)='Fare Routing:']/ancestor::td[1]/following-sibling::td[1][
					({$rule}) and
					substring(normalize-space(.), string-length(normalize-space(.))-2, 3)='" . (end($it['TripSegments'])['ArrCode']) . "'
				]/ancestor::tr[1]");

                if ($nodes->length == 1) {
                    $root = $nodes->item(0);
                    $it['Tax'] = $this->cost($this->http->FindSingleNode("./preceding-sibling::tr[1][normalize-space(./td)='Taxes and Fees:']/td[2]", $root));
                    $it['TotalCharge'] = $this->cost($this->http->FindSingleNode("./preceding-sibling::tr[2][normalize-space(./td)='Fare Accepted:']/td[2]", $root));
                    $it['Currency'] = $this->currency($this->http->FindSingleNode("./preceding-sibling::tr[2][normalize-space(./td)='Fare Accepted:']/td[2]", $root));
                }
            }
            //if steel empty Total
            if (count($it['Passengers']) == 1 && empty($it['TotalCharge']) && empty($it['BaseFare'])) {
                $it['BaseFare'] = $this->cost($this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Fare accepted:')]/following::text()[normalize-space(.)][1]"));
                $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Total amount:')]/following::text()[normalize-space(.)][1]");
                $it['TotalCharge'] = $this->cost($node);
                $it['Currency'] = $this->currency($node);
            }
            $it = array_filter($it);
            $itineraries[] = $it;
        }

        //###################
        //##    Trains    ###
        //###################

        $xpath = "//text()[normalize-space(.)='Rail']/ancestor::tr[1]/following::tr[{$ruleBR}][1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            if ($rl = $this->http->FindSingleNode("./descendant::text()[{$ruleBR}]", $root, true, "#:\s*(\w+)#")) {
                $airs[$rl][] = $root;
            }
        }

        foreach ($airs as $rl => $roots) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T'];

            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

            $it['RecordLocator'] = $rl;

            $it['Passengers'] = $this->http->FindNodes("//text()[{$ruleTL}]/following::text()[starts-with(normalize-space(.), 'Traveller')]/following::text()[normalize-space(.)][1]");

            $tickets = [];
            $status = '';

            foreach ($roots as $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                if (preg_match('/LEGTYPE:\w+:\s*(.+)\s*DATE OF TRAVEL/i', $this->http->FindSingleNode("following-sibling::tr[contains(., 'LEGTYPE') and contains(., 'Remarks')][1]", $root), $m)) {
                    $seg['AirlineName'] = $m[1];
                }

                //				if( empty($seg['AirlineName']) && $this->getNode($root, 'LEGTYPE', '/LEGTYPE:(false)/') )
                //					$seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

                $seg['DepName'] = $this->getNode($root, 'DEP STATION', '/DEP STATION\s*:\s*(.+)/');

                $date = $this->getNode($root, 'DATE OF TRAVEL', '/DATE OF TRAVEL\s*:\s*(.+)/');

                $seg['DepDate'] = $this->normalizeDate($date . ', ' . $this->getNode($root, 'DEP TIME', '/DEP TIME\s*:\s*(.+)/'));

                $seg['ArrName'] = $this->getNode($root, 'ARR STATION', '/ARR STATION\s*:\s*(.+)/');

                $seg['ArrDate'] = $this->normalizeDate($date . ', ' . $this->getNode($root, 'ARR TIME', '/ARR TIME\s*:\s*(.+)/'));

                $seg['Cabin'] = $this->getNode($root, 'CLASS OF SERVICE', '/CLASS OF SERVICE\s*:\s*(.+)/');

                $seg['Seats'] = $this->getNode($root, 'SEAT NOS', '/SEAT NOS\s*:\s*\b([A-Z]{1,4})\b/');

                if (preg_match('/Status\s*:\s*(\w+)/', $root->nodeValue, $m)) {
                    $status = $m[1];
                }

                if (!empty($seg['AirlineName']) && !empty($seg['DepName']) && !empty($seg['ArrName'])) {
                    $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }

                $tickets[] = $this->getNode($root, 'TICKET REF', '/TICKET REF\s*:\s*(.+)/');

                $it['TripSegments'][] = $seg;
            }

            $it['TicketNumbers'] = array_unique($tickets);

            $it['Status'] = $status;

            $itineraries[] = $it;
        }

        //#################
        //##    Cars    ###
        //#################

        $xpath = "//img[contains(@src,'Car')]/ancestor::tr[1]/following-sibling::tr[{$ruleBR}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];
            $it['Kind'] = "L";
            $it['Number'] = $this->http->FindSingleNode("./descendant::text()[{$ruleBR}]", $root, true, "#:\s*(.+)#");
            $it['TripNumber'] = $tripNumber;
            $it['Status'] = $this->http->FindSingleNode("./descendant::text()[contains(.,'Status')]", $root, true, "#:\s*(.+)#");
            $it['RentalCompany'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $root);
            $it['PickupLocation'] = $this->getField(1, "Pickup location:", $root);
            $w = $this->t('Date/Time:');

            if (!is_array($w)) {
                $w = [$w];
            }

            foreach ($w as $item) {
                $it['PickupDatetime'] = $this->normalizeDate($this->getField(2, $item, $root));

                if ($it['PickupDatetime'] !== false) {
                    break;
                }
            }
            $it['DropoffLocation'] = $this->getField(3, "Dropoff location:", $root);

            foreach ($w as $item) {
                $it['DropoffDatetime'] = $this->normalizeDate($this->getField(4, $item, $root));

                if ($it['DropoffDatetime'] !== false) {
                    break;
                }
            }
            $it['CarType'] = $this->getField(5, "Car type:", $root) . '-' . $this->getField(7, "Transmission type:", $root);
            $it['PricedEquips'] = ["Air conditioning" => $this->getField(6, "Air conditioning:", $root)];
            $it['AccountNumbers'] = $this->getField(11, "Membership:", $root);
            $node = $this->getField(9, "Estimated total cost:", $root);
            $it['TotalCharge'] = $this->cost($node);
            $it['Currency'] = $this->currency($node);

            $it = array_filter($it);
            $itineraries[] = $it;
        }

        //###################
        //##    HOTELS    ###
        //###################

        $xpath = "//text()[normalize-space(.)='Hotel' or starts-with(normalize-space(.),'Hotel')]/ancestor::tr[1]/descendant-or-self::tr[{$ruleBR}][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//text()[normalize-space(.)='Hotel' or starts-with(normalize-space(.),'Hotel')]/ancestor::tr[2]/following::tr[{$ruleBR}][1]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length == 0) {
            $xpath = "//text()[normalize-space(.)='Check in date:']/ancestor::tr[1]/../tr[1]";
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $root) {
            $it = [];
            $it['Kind'] = "R";

            $it['ConfirmationNumber'] = $this->http->FindSingleNode("./descendant::text()[{$ruleBR}]", $root, true, "#:\s*(\w+)#");
            $it['TripNumber'] = $tripNumber;
            $it['Status'] = $this->http->FindSingleNode("./descendant::text()[contains(.,'Status')]", $root, true, "#:\s*(\w+)#");

            if (empty($this->http->FindSingleNode(".", $root, true, "#Hotel#")) && count($this->http->FindNodes(".//img", $root)) === 0) {
                $it['HotelName'] = $this->http->FindSingleNode("./preceding::tr[2]/descendant::text()[normalize-space(.)][2]", $root);
                $it['Address'] = $this->http->FindSingleNode("./preceding::tr[2]/descendant::text()[normalize-space(.)][3]", $root);
            } elseif ($this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $root, true, "#^Hotel$#")) {
                $it['HotelName'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][2]", $root);
                $it['Address'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][3]", $root);
            } else {
                $it['HotelName'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $root);
                $it['Address'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][2]", $root);
            }
            $w = $this->t('Check In Date:');

            if (!is_array($w)) {
                $w = [$w];
            }

            foreach ($w as $item) {
                $it['CheckInDate'] = $this->normalizeDate($this->getField(1, $item, $root));

                if ($it['CheckInDate'] !== false) {
                    break;
                }
            }
            $w = $this->t('Check Out Date:');

            if (!is_array($w)) {
                $w = [$w];
            }

            foreach ($w as $item) {
                $it['CheckOutDate'] = $this->normalizeDate($this->getField(2, $item, $root));

                if ($it['CheckOutDate'] !== false) {
                    break;
                }
            }
            $it['Guests'] = $this->getField(3, "Occupancy:", $root);
            $it['Phone'] = $this->getField(4, "Phone number:", $root);
            $it['Fax'] = $this->getField(5, "Fax number:", $root);
            $it['GuestNames'] = $this->http->FindNodes("//text()[{$ruleTL}]/following::text()[starts-with(normalize-space(.), 'Traveller')]/following::text()[normalize-space(.)][1]");
            $it['Rate'] = $this->getField(5, "Room Rate:", $root);
            $node = $this->getField(5, "Estimated total rate:", $root);
            $it['Total'] = $this->cost($node);
            $it['Currency'] = $this->currency($node);
            $it['RoomType'] = $this->getField(6, "Room type:", $root);

            $w = $this->t('CancellationPolicy');

            if (!is_array($w)) {
                $w = [$w];
            }

            foreach ($w as $item) {
                $it['CancellationPolicy'] = $this->http->FindSingleNode("./following-sibling::tr[contains(.,'{$item}')]//text()[contains(.,'{$item}')]", $root, true, "#:\s*(.+)#");

                if (!empty($it['CancellationPolicy'])) {
                    break;
                }
            }

            $w = $this->t('Room Details');

            if (!is_array($w)) {
                $w = [$w];
            }

            foreach ($w as $item) {
                $it['RoomTypeDescription'] = $this->http->FindSingleNode("./following-sibling::tr[contains(.,'{$item}')]//text()[contains(.,'{$item}')]", $root, true, "#:\s*(.+)#");

                if (!empty($it['RoomTypeDescription'])) {
                    break;
                }
            }
            $it = array_filter($it);
            $itineraries[] = $it;
        }

        //####################
        //##   TRANSFERS   ###
        //####################

        $xpath = "//text()[normalize-space(.)='Taxi/Transfer']/ancestor::tr[2]/following-sibling::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $it = [];
            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = CONFNO_UNKNOWN;

            // TripNumber
            $it['TripNumber'] = $tripNumber;
            // Passengers
            $it['Passengers'] = $this->http->FindNodes("//text()[{$ruleTL}]/following::text()[starts-with(normalize-space(.), 'Traveller')]/following::text()[normalize-space(.)][1]");

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
            $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;
            $itsegment = [];

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->getField(1, "Start Location:", $root);

            // DepDate
            if (strpos($this->getField(2, "Date/Time:", $root), "DATA FOR THIS SECTOR IS UNAVAILABLE") === false) {
                $itsegment['DepDate'] = $this->normalizeDate($this->getField(2, "Date/Time:", $root));
            } else {
                $itsegment['DepDate'] = MISSING_DATE;
            }

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->getField(3, "End Location:", $root);

            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            if (empty($itsegment['DepName']) || empty($itsegment['ArrName'])) {
                continue;
            }

            $it['TripSegments'][] = $itsegment;

            $itineraries[] = $it;
        }
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
        $htmlBody = $parser->getHTMLBody();

        if (empty($htmlBody)) {
            $htmlBody = $parser->getPlainBody();
            $this->http->SetEmailBody($htmlBody);
        }

        if ($this->http->XPath->query('//node()[' . $this->contains($this->reBody) . ']')->length === 0 && $this->http->XPath->query('//img[' . $this->contains($this->reBody, '@src') . ']')->length === 0) {
            return false;
        }

        $texts = implode("\n", $parser->getRawBody());
        $posBegin1 = stripos($texts, "Content-Type: text/html");
        $i = 0;

        while ($posBegin1 !== false && $i < 30) {
            $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
            $posEnd = stripos($texts, "\n\n", $posBegin);

            if (preg_match("#filename=.*\.htm.*base64#s", substr($texts, $posBegin1, $posBegin - $posBegin1))) {
                $t = substr($texts, $posBegin, $posEnd - $posBegin);
                $htmlBody .= base64_decode($t);
            }
            $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
            $i++;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($htmlBody, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $htmlBody = $this->http->Response['body'];

        if (empty($htmlBody)) {
            $htmlBody = $parser->getPlainBody();
        }
        $this->http->SetEmailBody(str_replace(" ", " ", $htmlBody)); // bad fr char " :"

        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;

        if (!stripos($this->http->Response["body"], 'Itinerary Summary')) {
            $text = $parser->getHTMLBody();
            $texts = implode("\n", $parser->getRawBody());
            $posBegin1 = stripos($texts, "Content-Type: text/html");
            $i = 0;

            while ($posBegin1 !== false && $i < 30) {
                $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                $posEnd = stripos($texts, "\n\n", $posBegin);

                if (preg_match("#filename=.*\.htm.*base64#s", substr($texts, $posBegin1, $posBegin - $posBegin1))) {
                    $t = substr($texts, $posBegin, $posEnd - $posBegin);
                    $text .= base64_decode($t);
                }
                $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                $i++;
            }
            $this->http->SetEmailBody($text);
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->getPdfText($parser);

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations' . ucfirst($this->lang),
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

    private function arrayDiff(array $array1, array $array2)
    {
        $diffs = [];

        foreach ($array1 as $i => $arr1) {
            if (is_array($arr1)) {
                if (!isset($array2[$i]) || !is_array($array2[$i])) {
                    $diffs[] = $arr1;
                }
            }
        }

        return $diffs;
    }

    private function getAirSegmentsFromPdf($text, $flightNumber = null)
    {
        $segs = [];
        $itText = $this->findCutSection($text, 'Itinerary Summary', 'GENERAL INFORMATION');
        $its = preg_split('/(?:\bAir\b|\bHotel\b)/', $itText);
        $shortDescriptionAirs = array_shift($its);
        $hotel = '';
        $seg = [];

        foreach ($its as $i => $it) {
            if (stripos($it, 'Check In Date') !== false) {
                $hotel = $it;
                unset($its[$i]);

                continue;
            }
            $seg['DepName'] = $this->re('/Departing\s*:\s*(.*?)\s*\([A-Z]{3}\)/', $it);
            $seg['DepCode'] = $this->re('/Departing\s*:\s*.*?\s*\(([A-Z]{3})\)/', $it);
            $seg['DepartureTerminal'] = $this->re('/Departing\s*:\s*.*?\s*\([A-Z]{3}\)\s*,\s*Terminal\s*([A-Z\d]{1,4})/', $it);
            $seg['AirlineName'] = $this->re('/Flight #\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+/', $it);
            $seg['FlightNumber'] = $this->re('/Flight #\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/', $it);
            $seg['DepDate'] = $this->normalizeDate($this->re('/Date\/Time\s*:\s*(.+)/', $it));
            $seg['ArrDate'] = $this->normalizeDate($this->re('/Arriving\s*:\s*[\s\S]+\s+Date\/Time\s*:\s*(.+)/', $it));
            $seg['ArrName'] = $this->re('/Arriving\s*:\s*(.*?)\s*\([A-Z]{3}\)/', $it);
            $seg['ArrCode'] = $this->re('/Arriving\s*:\s*.*?\s*\(([A-Z]{3})\)/', $it);
            $seg['ArrivalTerminal'] = $this->re('/Arriving\s*:\s*.*?\s*\([A-Z]{3}\)\s*,\s*Terminal\s*([A-Z\d]{1,4})/', $it);
            $seg['Duration'] = $this->re('/Duration\s*:\s*(.+)/', $it);
            $seg['Aircraft'] = $this->re('/Aircraft\s*:\s*(.+)/', $it);
            $seg['Cabin'] = $this->re('/Cabin Class\s*:\s*(.+)\s*-\s*[A-Z]/', $it);
            $seg['BookingClass'] = $this->re('/Cabin Class\s*:\s*.+\s*-\s*([A-Z])/', $it);
            $seg['Seats'] = [$this->re('/Seat\s*:\s*(.+)/', $it)];
            $seg['Meal'] = $this->re('/Meal\s*:\s*(.+)/', $it);
            $segs[] = $seg;
        }
        sort($its);

        return $segs;
    }

    private function getPdfText(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');
        $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

        foreach ($this->pdfDetects as $detect) {
            if (stripos($body, $detect) !== false) {
                $this->pdfText = $body;
            }
        }
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    private function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return $inputResult;
    }

    private function getField($n, $field, $root)
    {
        $node = $this->http->FindSingleNode("./following-sibling::tr[{$n}][normalize-space(./td[1])='{$field}']/td[2]", $root);

        if (empty($node)) {//for Seats, Meal etc. (not fixed order)
            $node = $this->http->FindSingleNode("./following-sibling::tr[contains(.,'{$field}')]/td[2]", $root);
        }

        return $node;
    }

    private function getNode(\DOMNode $root, $str, $re = null)
    {
        return $this->http->FindSingleNode("following-sibling::tr[contains(., 'LEGTYPE') and contains(., 'Remarks')][1]/descendant::text()[contains(., '{$str}')]", $root, true, $re);
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
            "#^(\d+\s+\w+\s+\d{4},\s+\d+:\d+)$#",
            "#^(\d+\s+\w+\s+\d{4})$#",
            "#^(\d+\s+\w+\s+\d{4}),\s+to\s+meet\s+\w{2}\d+$#",
            '/^\w+\s+(\d+\s+\w+\s+\d{4},\s+\d+:\d+)$/',
            '/^\w+\s+(\d+\s+\w+\s+\d{4})$/',
            '/^(\d+)\s*([a-z]+),\s+(\d{1,2})(\d{2})$/i',
        ];
        $out = [
            "$1",
            "$1",
            "$1",
            '$1',
            '$1',
            "$1 $2 {$year}, $3:$4",
        ];
        $str = preg_replace($in, $out, $str);

        if ($this->lang !== 'en' && preg_match("#[^\d\s-\./]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

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

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }
}
