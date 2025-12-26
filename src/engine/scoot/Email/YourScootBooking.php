<?php

namespace AwardWallet\Engine\scoot\Email;

class YourScootBooking extends \TAccountCheckerExtended
{
    public $mailFiles = "scoot/it-1977779.eml, scoot/it-2051290.eml, scoot/it-2405929.eml, scoot/it-2583169.eml, scoot/it-2782512.eml, scoot/it-5509945.eml, scoot/it-5597156.eml, scoot/it-5597161.eml, scoot/it-5597163.eml";

    private $detects = [
        'Thanks for booking on Scoot',
        'Your Scoot booking confirmation',
        // from pdf attachment
        'Please check your flight and note the departure time',
    ];

    /** @var \HttpBrowser */
    private $pdf;

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            "pdf" => function (&$itineraries) {
                $processor = $this->processors["html"];
                $processor($itineraries);

                $it = &$itineraries[0];

                $pdf = text($this->pdf->Response["body"]);

                if (!isset($it['RecordLocator']) || empty($it['RecordLocator'])) {
                    $it['RecordLocator'] = re("#Scoot\s+Booking\s+Reference\s+(\w+)#s", $pdf);
                }

                $it['TotalCharge'] = cost(preg_replace("#[.,](\d{3})#", "$1", re("/Total\s+Amount\s*:\s+([^\n]+)\s*\n/ui", $pdf)));
                $it['Currency'] = currency(re("/Total\s+Amount\s*:\s+([^\n]+)\s*\n/ui", $pdf));
                $it['Status'] = strtolower(re("/Booking\s+Status\s*:\s+([^\n]+)\s*\n/ui", $pdf));
                $it['ReservationDate'] = strtotime(re("/Booking\s+Date\s*:\s+([^\n]+)\s*\n/ui", $pdf));

                $psngrsRaw = re("#Passenger(.+?)(?:Extra\s+Cabin\s+Bag|Meal\s+Details|Receipt)#uis", $pdf);

                $itineraryRaw = re("#Itinerary\s+Details(.+?)(?:Passenger)#uis", $pdf);

                preg_match_all("#\n\s*(M[ris]+[^\n]+)\n\s*[\-\sa-z\d]+?\n\s*\b([A-Z\d]{1,3})\b#msi", $psngrsRaw, $matches);
                $passengers = [];
                $seats = [];

                foreach ($matches[1] as $psngr) {
                    $passengers[] = beautifulName($psngr);
                }

                if (count($matches[2]) > 0) {
                    $seats = $matches[2];
                }
                $passengers = array_unique($passengers);
                $count = count($passengers);

                if ($count > 0) {
                    $it['Passengers'] = $passengers;
                }

                if (count($seats)) {
                    $seatsForSegments = array_chunk($seats, $count);
                }

                if (preg_match_all("#Flight\s+No\s*:\s*\w{2}\s+\d+#", $psngrsRaw, $m)) {
                    for ($i = 0; $i < count($m[0]); $i++) {
                        $it["TripSegments"][$i]["FlightNumber"] = ure("#Flight\s+No\s*:\s*\w{2}\s+(\d+)#", $pdf, $i + 1);
                        $it["TripSegments"][$i]["AirlineName"] = ure("#Flight\s+No\s*:\s*(\w{2})\s+\d+#", $pdf, $i + 1);
                        $it["TripSegments"][$i]["DepCode"] = ure("#Flight\s+No\s*:\s*\w{2}\s+\d*\s*([A-Z]{3})\s+[-]*\s*[A-Z]{3}#ms", $pdf, $i + 1);
                        $it["TripSegments"][$i]["ArrCode"] = ure("#Flight\s+No\s*:\s*\w{2}\s+\d*\s*[A-Z]{3}\s+[-]*\s*([A-Z]{3})#ms", $pdf, $i + 1);

                        if ($i & 1) {//odd
                            $fN1 = $it["TripSegments"][$i - 1]["FlightNumber"];
                            $fN2 = $it["TripSegments"][$i]["FlightNumber"];
                            $aN1 = $it["TripSegments"][$i - 1]["AirlineName"];
                            $aN2 = $it["TripSegments"][$i]["AirlineName"];

                            if (preg_match("#Terminal(.*?)\n\s*Terminal(.*?)\n\s*Terminal(.*?)\n\s*{$aN1}\s+{$fN1}\s*\n\s*{$aN2}\s+{$fN2}\s*\n\s*(\d+:\d+)\s+(\d+:\d+)\s*\n\s*(\d+:\d+)\s+(\d+:\d+)\s*\n\s*(.+?)\s*\n\s*(.+?)\s*\n#", $itineraryRaw, $v)) {
                                if (!empty($v[1])) {
                                    $it["TripSegments"][$i - 1]["DepartureTerminal"] = $v[1];
                                }

                                if (!empty($v[2])) {
                                    $it["TripSegments"][$i - 1]["ArrivalTerminal"] = $v[2];
                                }

                                if (!empty($v[2])) {
                                    $it["TripSegments"][$i]["DepartureTerminal"] = $v[2];
                                }

                                if (!empty($v[3])) {
                                    $it["TripSegments"][$i]["ArrivalTerminal"] = $v[3];
                                }
                                $it["TripSegments"][$i - 1]["DepDate"] = strtotime($v[8] . ' ' . $v[4]);
                                $it["TripSegments"][$i - 1]["ArrDate"] = strtotime($v[8] . ' ' . $v[5]);
                                $it["TripSegments"][$i]["DepDate"] = strtotime($v[9] . ' ' . $v[6]);
                                $it["TripSegments"][$i]["ArrDate"] = strtotime($v[9] . ' ' . $v[7]);
                            } elseif (preg_match("#\n(.+?)\n+(.+?)\n+(\d{4})(\d{2})\s+Flight No\s*:\s+{$aN1}\s+{$fN1}.*?\s*\n\s*(\d+:\d+\s*(?:[ap]m)?)\s+(\d+:\d+\s*(?:[ap]m)?)\s*\n\s*(\d{4})(\d{2})\s+Flight No\s*:\s+{$aN2}\s+{$fN2}.*?\s*\n\s*(\d+:\d+\s*(?:[ap]m)?)\s+(\d+:\d+\s*(?:[ap]m)?)\s*\n\s*.+?\n.+?\n.+?\n(.+?)\n(.+?)\n(.+?)\n\s*Terminal(.*?)\n\s*Terminal(.*?)\n\s*Terminal(.*?)\n#i", $itineraryRaw, $v)) {
                                $v[1] = str_replace(" ", "", $v[1]);
                                $v[2] = str_replace(" ", "", $v[2]);
                                $v[5] = $this->correctTime($v[5]);
                                $v[6] = $this->correctTime($v[6]);
                                $v[9] = $this->correctTime($v[9]);
                                $v[10] = $this->correctTime($v[10]);
                                $it["TripSegments"][$i - 1]["DepDate"] = strtotime($v[4] . ' ' . $v[1] . ' ' . $v[3] . ', ' . $v[5]);
                                $it["TripSegments"][$i - 1]["ArrDate"] = strtotime($v[4] . ' ' . $v[1] . ' ' . $v[3] . ', ' . $v[6]);
                                $it["TripSegments"][$i - 1]["DepName"] = $v[11];
                                $it["TripSegments"][$i - 1]["ArrName"] = $v[12];

                                if (!empty($v[14])) {
                                    $it["TripSegments"][$i - 1]["DepartureTerminal"] = $v[14];
                                }

                                if (!empty($v[15])) {
                                    $it["TripSegments"][$i - 1]["ArrivalTerminal"] = $v[15];
                                }
                                $it["TripSegments"][$i]["DepDate"] = strtotime($v[8] . ' ' . $v[2] . ' ' . $v[7] . ', ' . $v[9]);
                                $it["TripSegments"][$i]["ArrDate"] = strtotime($v[8] . ' ' . $v[2] . ' ' . $v[7] . ', ' . $v[10]);
                                $it["TripSegments"][$i]["DepName"] = $v[12];
                                $it["TripSegments"][$i]["ArrName"] = $v[13];

                                if (!empty($v[15])) {
                                    $it["TripSegments"][$i]["DepartureTerminal"] = $v[15];
                                }

                                if (!empty($v[16])) {
                                    $it["TripSegments"][$i]["ArrivalTerminal"] = $v[16];
                                }

                                if (!empty($seatsForSegments)) {
                                    $it['TripSegments'][$i]['Seats'] = array_shift($seatsForSegments);
                                }
                            }
                        }

                        if (!isset($it["TripSegments"][$i]["DepDate"]) || empty($it["TripSegments"][$i]["DepDate"])) {
                            $fN1 = $it["TripSegments"][$i]["FlightNumber"];
                            $aN1 = $it["TripSegments"][$i]["AirlineName"];

                            if (
                                preg_match("#\n\s*Terminal(?<DTerm>.*?)\n\s*Terminal(?<ATerm>.*?)\n\s*{$aN1}\s+{$fN1}\s*\n\s*(?<DTime>\d+:\d+)\s+[-]*\s*(?<ATime>\d+:\d+)\s*\n\s*(?<Date>.+?)\s*\n#", $itineraryRaw, $v)
                                || preg_match("/(?<DTime>\d+:\d+)\s+[-]*\s*(?<ATime>\d+:\d+)\s*\n\s*(?<Date>.+?)\s*\n\s*{$aN1}\s+{$fN1}\s+[\S\s]+\nTerminal\s+(?<DTerm>[A-Z\d]{1,3})?\n\s*Terminal\s+\b(?<ATerm>[A-Z\d]{1,3})?\b/", $itineraryRaw, $v)
                            ) {
                                if (!empty($v['DTerm'])) {
                                    $it["TripSegments"][$i]["DepartureTerminal"] = $v['DTerm'];
                                }

                                if (!empty($v['ATerm'])) {
                                    $it["TripSegments"][$i]["ArrivalTerminal"] = $v['ATerm'];
                                }

                                $it["TripSegments"][$i]["DepDate"] = strtotime($v['Date'] . ' ' . $v['DTime']);
                                $it["TripSegments"][$i]["ArrDate"] = strtotime($v['Date'] . ' ' . $v['ATime']);
                            } elseif (preg_match("#\n(.+?)\n+(\d{4})(\d{2})\s+Flight No\s*:\s+{$aN1}\s+{$fN1}.*?\s*\n\s*(\d+:\d+\s*(?:[ap]m)?)\s+(\d+:\d+\s*(?:[ap]m)?)\s*\n\s*.+?\n.+?\n(.+?)\n(.+?)\n\s*Terminal(.*?)\n\s*Terminal(.*?)\n#i", $itineraryRaw, $v)) {
                                $v[1] = str_replace(" ", "", $v[1]);
                                $v[5] = $this->correctTime($v[5]);
                                $v[6] = $this->correctTime($v[6]);
                                $v[9] = $this->correctTime($v[9]);
                                $v[10] = $this->correctTime($v[10]);
                                $it["TripSegments"][$i]["DepDate"] = strtotime($v[3] . ' ' . $v[1] . ' ' . $v[2] . ', ' . $v[4]);
                                $it["TripSegments"][$i]["ArrDate"] = strtotime($v[3] . ' ' . $v[1] . ' ' . $v[2] . ', ' . $v[5]);
                                $it["TripSegments"][$i]["DepName"] = $v[6];
                                $it["TripSegments"][$i]["ArrName"] = $v[7];

                                if (!empty($v[8])) {
                                    $it["TripSegments"][$i]["DepartureTerminal"] = $v[8];
                                }

                                if (!empty($v[9])) {
                                    $it["TripSegments"][$i]["ArrivalTerminal"] = $v[9];
                                }
                            }
                        }

                        if (!isset($it["TripSegments"][$i]["DepCode"]) || empty($it["TripSegments"][$i]["DepCode"])) {
                            $it["TripSegments"][$i]["DepCode"] = TRIP_CODE_UNKNOWN;
                        }

                        if (!isset($it["TripSegments"][$i]["ArrCode"]) || empty($it["TripSegments"][$i]["ArrCode"])) {
                            $it["TripSegments"][$i]["ArrCode"] = TRIP_CODE_UNKNOWN;
                        }

                        if (!empty($seatsForSegments)) {
                            $it['TripSegments'][$i]['Seats'] = array_shift($seatsForSegments);
                        }
                    }
                }
            },

            "html" => function (&$itineraries) {
                $text = text($this->http->Response["body"]);
                $it = [];

                $it['Kind'] = "T";
                // RecordLocator

                $it['RecordLocator'] = re("#Booking\s+Ref\s*:\s*(\w+)#", $text);
                // TripNumber
                // Passengers
                $it['Passengers'] = [re("#Hi\s+(.*?),#", $text)];
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

                $xpath = "//*[normalize-space(text())='DEPARTS']/ancestor::tr[1]/following-sibling::tr[normalize-space(./td[2])]";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                $year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]", null, true, "#(\d+)$#");

                foreach ($nodes as $root) {
                    $date = strtotime($this->http->FindSingleNode("./td[2]", $root));

                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $itsegment['DepName'] = $this->http->FindSingleNode("./td[3]", $root);

                    // DepDate
                    $itsegment['DepDate'] = strtotime(re("#\d+:\d+#", $this->http->FindSingleNode("./following-sibling::tr[1]/td[3]", $root)), $date);

                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                    // ArrName
                    $itsegment['ArrName'] = $this->http->FindSingleNode("./td[4]", $root);

                    // ArrDate
                    $itsegment['ArrDate'] = strtotime(re("#\d+:\d+#", $this->http->FindSingleNode("./following-sibling::tr[1]/td[4]", $root)), $date);

                    if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                        $itsegment['ArrDate'] = strtotime('+1 day', $itsegment['ArrDate']);
                    }

                    // AirlineName
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

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'your-itinerary@flyscoot.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) > 0) {
            $body .= \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));
            $body .= str_replace(chr(194) . chr(160), chr(32), $body);
        }

        if (stripos($body, 'Scoot') === false) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers["from"], 'your-itinerary@flyscoot.com') !== false
            || stripos($headers["subject"], 'Your Scoot Booking') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName('[A-Z0-9]{6}\.pdf');
        $s = '';

        if (isset($pdfs[0])) {
            $pdf = $pdfs[0];

            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                $this->pdf = clone $this->http;
                $this->pdf->SetBody($html);
                $processor = $this->processors["pdf"];
                $s = 'pdf';
            } else {
                $processor = $this->processors["pdf"];
                $s = 'pdf';
            }
        } else {
            $processor = $this->processors["html"];
            $s = 'html';
        }

        $processor($itineraries);

        $result = [
            'emailType'  => 'FlightEn' . ucfirst($s),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    //15:20 PM -> 15:20
    private function correctTime($str)
    {
        $t = $str;

        if (preg_match("#((\d+):\d+)\s*[ap]m#i", $str, $h) && $h[2] > 12) {
            $t = $h[1];
        }

        return $t;
    }
}
