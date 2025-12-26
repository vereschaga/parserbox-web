<?php

namespace AwardWallet\Engine\goibibo\Email;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "goibibo/it-10460872.eml, goibibo/it-10465582.eml, goibibo/it-10483034.eml, goibibo/it-10493025.eml, goibibo/it-10493105.eml, goibibo/it-11156030.eml, goibibo/it-11356588.eml, goibibo/it-6849181.eml, goibibo/it-6856353.eml, goibibo/it-6988886.eml, goibibo/it-6988887.eml, goibibo/it-6988898.eml, goibibo/it-7007220.eml, goibibo/it-7007249.eml, goibibo/it-7013030.eml, goibibo/it-7046081.eml, goibibo/it-7080929.eml, goibibo/it-7098206.eml, goibibo/it-7098586.eml, goibibo/it-7104669.eml, goibibo/it-7163141.eml, goibibo/it-7209253.eml, goibibo/it-7271228.eml, goibibo/it-7278130.eml, goibibo/it-9041558.eml";
    public $reFrom = "goibibo.com";
    public $reSubject = [
        "Your Ticket Has Been Booked",
        "Your Flight is Rescheduled",
    ];

    /** @var \HttpBrowser */
    protected $pdf;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $arr = [];
        $pdfs = $parser->searchAttachmentByName('.+\.pdf');

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                } else {
                    return null;
                }
            }
            $NBSP = chr(194) . chr(160);
            $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));
            // check pdf format, simple test
            if ($this->pdf->XPath->query("//text()[normalize-space()='Date']/ancestor::p[1]/following-sibling::p[normalize-space()!=''][1][normalize-space()='Time']/following-sibling::p[normalize-space()!=''][1][normalize-space()='From']/following-sibling::p[normalize-space()!=''][1][normalize-space()='To']")->length > 0) {
                $arr = $this->parseEmailPDF();
            }
        }

        if (!empty($arr['parsedData']['Itineraries'])) {
            $findedRL = true;

            foreach ($arr['parsedData']['Itineraries'] as $value) {
                if (empty($value['RecordLocator']) && !empty($value['TripSegments'])) {
                    $findedRL = false;
                }
            }

            if ($findedRL) {
                return $arr;
            }
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(.),'PremierMiles Ref No')]")->length > 0) {
            return $this->parseEmailHTML4();
        } elseif ($this->http->XPath->query("//img[(contains(@src,'travel-time')) or (@height='21' and @width='23')]/ancestor::tr[1]")->length > 0) {
            return $this->parseEmailHTML();
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(.),'Please find a link to view/print your eTicket below')]")->length > 0) {
            return $this->parseEmailHTML2();
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(.),'We have charged you')]")->length > 0) {
            return $this->parseEmailHTML5();
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(.),'We have received a schedule')]")->length > 0) {
            return $this->parseEmailHTML6();
        } else {
            return $this->parseEmailHTML3();
        }
    }

    public function detectEmailFromProvider($from)
    {
        return isset($this->reFrom) && strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (isset($headers["from"]) && stripos($headers["from"], $this->reFrom) !== false && isset($headers["subject"]) && stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.+\.pdf');

        if (!empty($pdf)) {
            $pdfText = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));

            if (stripos($pdfText, 'goibibo') !== false && stripos($pdfText, 'E-Ticket Numbers') !== false) {
                return true;
            }
        }

        return $this->http->XPath->query("//text()[contains(normalize-space(.),'Thank you for booking with goibibo') or contains(normalize-space(.),'Thank you for choosing goibibo') or contains(normalize-space(.),'Thank you for choosing Goibibo') or contains(normalize-space(.),'Thank you for booking with Goibibo')] | //a[contains(@href,'goibibo.com')] | //img[contains(@alt,'PremierMiles')]")->length > 0;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 7;
    }

    private function parseEmailPDF()
    {
        $tripNum = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Booking Id')]/following::text()[normalize-space(.)][1]");
        $resDate = strtotime($this->http->FindSingleNode("//text()[contains(normalize-space(.),'Booked on')]", null, true, "#Booked on\s*:\s*(.*)#"));

        // Passengers
        $i = 1;

        while ($i < 15) {
            $passengers = $this->pdf->FindSingleNode("//p[starts-with(normalize-space(),'Date')]/preceding::p[starts-with(normalize-space(),'" . $i . ".')]");

            if (!empty($passengers)) {
                $name = trim(preg_replace("#\d+\.(.*)#", "$1", $passengers));

                if (empty($name)) {
                    $pax[] = $this->pdf->FindSingleNode("//p[starts-with(normalize-space(),'Date')]/preceding::p[starts-with(normalize-space(),'" . $i . ".')]/following::p[1]");
                    $tickets[] = $this->pdf->FindSingleNode("//p[starts-with(normalize-space(),'Date')]/preceding::p[starts-with(normalize-space(),'" . $i . ".')]/following::p[1]/following::p[3]", null, true, "#^\s*(\d+)\s*$#");
                } else {
                    $pax[] = $name;
                    $tickets[] = $this->pdf->FindSingleNode("//p[starts-with(normalize-space(),'Date')]/preceding::p[starts-with(normalize-space(),'" . $i . ".')]/following::p[3]", null, true, "#^\s*(\d+)\s*$#");
                }
            } else {
                break;
            }
            $i++;
        }
        // TotalCharge
        // Currency
        $totalcharge = $this->pdf->FindSingleNode("//p[contains(.,'Total Amount Paid')]/following::p[1]");

        if (preg_match('#\s*([A-Z]{2,3})\.?\s*(\d[0-9\s\.,]+)\s*#i', $totalcharge, $m)) {
            if ($m[1] == 'Rs') {
                $resCur = 'INR';
            } else {
                $resCur = $m[1];
            }
            $m[2] = str_replace(',', '', $m[2]);
            $resTot = $m[2];
        }

        $xpath = "//p[contains(.,'Airline')]/following::p[contains(.,'PNR No') or (contains(.,'LAYOVER'))]";
        $nodes = $this->pdf->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $c = $this->countP('|', $root);
            $rl = $this->pdf->FindSingleNode("(./following::p[position()<={$c}])[last()-1]", $root, true, "#^\s*[A-Z\d]{5,7}\s*$#");
            $airs[$rl][] = $root;
        }
        $its = [];

        foreach ($airs as $rl => $nodes) {
            $result['Kind'] = 'T';
            $result['RecordLocator'] = $rl;
            $result['TripNumber'] = $tripNum;
            $result['ReservationDate'] = $resDate;

            if (isset($pax) && count($pax) > 0) {
                $result['Passengers'] = array_unique($pax);
            }

            foreach ($nodes as $root) {
                $itsegment = [];
                $c = $this->countP('|', $root);
                $node = implode("\n", $this->pdf->FindNodes("./following::p[position()<={$c}]", $root));

                if (preg_match("#(\d+\s+\w{3}\s+\d+\s+\d+:\d+).*?\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s+(?:(\w)\s+)?.*?{$rl}\n(.*?)\s*\|\s*(.+?),\s+([A-Z]{3})[\s\-]+(.+?),\s+([A-Z]{3})#s", $node, $m)) {
                    $itsegment['DepDate'] = strtotime($this->normalizeDate($m[1]));
                    $itsegment['AirlineName'] = $m[2];
                    $itsegment['FlightNumber'] = $m[3];

                    if (isset($m[4]) && !empty($m[4])) {
                        $itsegment['DepartureTerminal'] = $m[4];
                    }

                    if (isset($m[5]) && !empty($m[5])) {
                        $itsegment['Duration'] = $m[5];
                    }
                    $arrtime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$m[2]}')]/ancestor::*[1][substring(normalize-space(.), string-length(normalize-space(.)) - string-length('{$m[3]}') +1) = '{$m[3]}']/following::text()[contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')][2]");

                    if ($arrtime) {
                        $itsegment['ArrDate'] = strtotime($arrtime, $itsegment['DepDate']);
                        $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$m[2]}')]/ancestor::*[1][contains(.,'{$m[3]}')]/following::text()[contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')][2]/preceding::text()[normalize-space(.)][1]", null, true, "#Terminal\s*(\w+)#i");
                    } else {
                        $itsegment['ArrDate'] = MISSING_DATE;
                    }
                    $itsegment['DepName'] = $m[6];
                    $itsegment['DepCode'] = $m[7];
                    $itsegment['ArrName'] = $m[8];
                    $itsegment['ArrCode'] = $m[9];
                }
                $result['TripSegments'][] = $itsegment;
            }
            $its[] = $result;
        }

        if (isset($resTot) && isset($resCur)) {
            if (count($its) > 1) {
                return [
                    'emailType'  => 'ETicketPDF',
                    'parsedData' => ['Itineraries' => $its, 'TotalCharge' => [
                        'Amount'   => $resTot,
                        'Currency' => $resCur,
                    ]],
                ];
            } elseif (count($its) === 1) {
                $its[0]['TotalCharge'] = $resTot;
                $its[0]['Currency'] = $resCur;

                if (isset($tickets)) {
                    $its[0]['TicketNumbers'] = array_filter($tickets);
                }
            }
        }

        return [
            'emailType'  => 'ETicketPDF',
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    private function parseEmailHTML()//6988887
    {
        $resDate = strtotime($this->http->FindSingleNode("(//text()[contains(normalize-space(.),'Booked on')])[1]", null, true, "#Booked on\s*:\s*(.*)#"));
        $tripNum = $this->http->FindSingleNode("(//text()[contains(normalize-space(.),'Booking Id')]/following::text()[normalize-space(.)][1])[1]");

        $pax = array_values(array_unique($this->http->FindNodes("//text()[normalize-space(.)='Name']/ancestor::td[1]//text()[not(contains(.,'Name'))][normalize-space(.)]")));
        $tickets = array_values(array_unique($this->http->FindNodes("//text()[contains(.,'E-Ticket No')]/ancestor::td[1]//text()[not(contains(.,'E-Ticket No'))][normalize-space(.)]")));

        $totalcharge = $this->http->FindSingleNode("(//text()[contains(.,'You have paid')]/following::text()[normalize-space(.)][1])[1]");

        if (preg_match('#\s*([A-Z]{2,3})\.?\s*(\d[0-9\s\.,]+)\s*#i', $totalcharge, $m)) {
            if ($m[1] == 'Rs') {
                $resCur = 'INR';
            } else {
                $resCur = $m[1];
            }
            $m[2] = str_replace(',', '', $m[2]);
            $resTot = $m[2];
        }

        $its = [];
        $result['Kind'] = 'T';
        $result['TripNumber'] = $tripNum;
        $result['RecordLocator'] = $this->http->FindSingleNode("(//text()[contains(.,'PNR')])[1]", null, true,
            "#PNR[\s-]+([A-Z\d]+)#");

        if (empty($result['RecordLocator']) && ($this->http->XPath->query("//text()[contains(normalize-space(.),'Your booking is reserved and would be confirmed once the payment is made ')]")->length > 0)) {
            $result['RecordLocator'] = CONFNO_UNKNOWN;
        }
        $result['ReservationDate'] = $resDate;

        if (isset($pax) && count($pax) > 0) {
            $result['Passengers'] = array_unique($pax);
        }

        $xpath = "//img[contains(@src,'travel-time')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//img[@height='21' and @width='23']/ancestor::tr[1]";
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::td[.//img and count(./following-sibling::td)=2][1]/following::td[1]", $root)));
            $node = implode("\n", $this->http->FindNodes("./td[1]//text()[normalize-space(.)]", $root));

            if (preg_match("#^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s*(.*)(?:\n|$)#m", $node, $m)) {
                $itsegment['AirlineName'] = $m[1];
                $itsegment['FlightNumber'] = $m[2];

                if (!empty($m[3])) {
                    $itsegment['Cabin'] = $m[3];
                }
            }
            $node = implode("\n", $this->http->FindNodes("./td[2]//text()[normalize-space(.)]", $root));

            if (preg_match("#([A-Z]{3})\s+Terminal\s*(.*)\n(\d+:\d+)#", $node, $m)) {
                $itsegment['DepDate'] = strtotime($m[3], $date);
                $itsegment['DepCode'] = $m[1];

                if (!empty($m[2])) {
                    if (($term = explode(",", $m[2])) && count($term) == 3 and preg_match("#^\s*(T\s*\w{1,6}|Terminal\s+.+)\s*$#", $term[1], $mat)) {
                        $itsegment['DepartureTerminal'] = $mat[1];
                    } else {
                        $itsegment['DepartureTerminal'] = $m[2];
                    }
                }
            }
            $node = implode("\n", $this->http->FindNodes("./td[4]//text()[normalize-space(.)]", $root));

            if (preg_match("#([A-Z]{3})\s+Terminal\s*(.*)\n(\d+:\d+)#", $node, $m)) {
                $itsegment['ArrDate'] = strtotime($m[3], $date);
                $itsegment['ArrCode'] = $m[1];

                if (!empty($m[2])) {
                    if (($term = explode(",", $m[2])) && count($term) == 3 and preg_match("#^\s*(T\s*\w{1,6}|Terminal\s+.+)\s*$#", $term[1], $mat)) {
                        $itsegment['ArrivalTerminal'] = $mat[1];
                    } else {
                        $itsegment['ArrivalTerminal'] = $m[2];
                    }
                }
            }
            $itsegment['Duration'] = $this->http->FindSingleNode("./td[3]", $root);
            $result['TripSegments'][] = $itsegment;
        }
        $result['TripSegments'] = array_map("unserialize", array_unique(array_map("serialize", $result['TripSegments'])));
        $its[] = $result;

        if (isset($resTot) && isset($resCur)) {
            if (count($its) > 1) {
                return [
                    'emailType'  => 'ETicketHTML',
                    'parsedData' => ['Itineraries' => $its, 'TotalCharge' => [
                        'Amount'   => $resTot,
                        'Currency' => $resCur,
                    ]],
                ];
            } elseif (count($its) === 1) {
                $its[0]['TotalCharge'] = $resTot;
                $its[0]['Currency'] = $resCur;

                if (isset($tickets)) {
                    $its[0]['TicketNumbers'] = array_filter($tickets);
                }
            }
        }

        return [
            'emailType'  => 'ETicketHTML',
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    private function parseEmailHTML2()//6988886
    {
        $resDate = strtotime($this->http->FindSingleNode("//text()[contains(normalize-space(.),'Booking date')]", null, true, "#Booking date\s*:\s*(.*)#"));
        $tripNum = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Booking ID')]/following::text()[normalize-space(.)][1]");

        $pax = $this->http->FindNodes("//text()[contains(.,\"Traveller's Details\")]/following::table[1]/descendant::td[count(descendant::td)=0 and img]");

        $totalcharge = $this->http->FindSingleNode("//text()[contains(.,'The total amount paid while booking is')]/ancestor::*[1]");

        if (preg_match('#booking\s+is\s*([A-Z]{2,3})\.?\s*(\d[0-9\s\.,]+)\s*#i', $totalcharge, $m)) {
            if ($m[1] == 'Rs') {
                $resCur = 'INR';
            } else {
                $resCur = $m[1];
            }
            $m[2] = str_replace(',', '', $m[2]);
            $resTot = $m[2];
        }

        $xpath = "//text()[contains(.,\"Package Itinerary\")]/following::table[1]/descendant::tr[count(td)=2]";
        $nodes = $this->http->XPath->query($xpath);
        $its = [];
        $flights = [];

        foreach ($nodes as $root) {
            $itsegment = [];

            if (!empty($this->http->FindSingleNode(".//text()[contains(normalize-space(),'Check-In')][1]", $root))) {
                // hotel
                $itsegment['Kind'] = 'R';
                $itsegment['ConfirmationNumber'] = CONFNO_UNKNOWN;
                $itsegment['TripNumber'] = $tripNum;
                $itsegment['HotelName'] = $this->http->FindSingleNode("./td[1]//td[normalize-space(.) and not(.//td)][1]/p[1]", $root);
                $itsegment['Address'] = $this->http->FindSingleNode("./td[1]//td[normalize-space(.) and not(.//td)][1]/p[2]", $root);
                $itsegment['RoomType'] = $this->http->FindSingleNode("./td[1]//td[normalize-space(.) and not(.//td)][1]/p[3]", $root);
                $itsegment['CheckInDate'] = strtotime($this->http->FindSingleNode(".//text()[contains(.,'Check-In')]/following::text()[normalize-space()][1]", $root));
                $itsegment['CheckOutDate'] = strtotime($this->http->FindSingleNode(".//text()[contains(.,'Check-Out')]/following::text()[normalize-space()][1]", $root));

                if (!empty($pax)) {
                    $itsegment['GuestNames'] = $pax;
                    $itsegment['Guests'] = count($pax);
                }
                $itsegment['ReservationDate'] = $resDate;
                $its[] = $itsegment;
            } else {
                // flight
                $node = implode("\n", $this->http->FindNodes("./td[1]//text()[normalize-space(.)]", $root, "#[\w\d\s]+#"));

                if (preg_match("#^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)#m", $node, $m)) {
                    $itsegment['AirlineName'] = $m[1];
                    $itsegment['FlightNumber'] = $m[2];
                }
                $node = implode("\n", $this->http->FindNodes("./td[2]/descendant::td[1]//text()[normalize-space(.)]", $root));

                if (preg_match("#(.+)\n\s*(.+)\s+\(([A-Z]{3})\)\s+(\d+:\d+)#", $node, $m)) {
                    $itsegment['DepDate'] = strtotime($m[4], strtotime($m[1]));
                    $itsegment['DepName'] = $m[2];
                    $itsegment['DepCode'] = $m[3];
                }
                $node = implode("\n", $this->http->FindNodes("./td[2]/descendant::td[3]//text()[normalize-space(.)]", $root));

                if (preg_match("#(.+)\n\s*(.+)\s+\(([A-Z]{3})\)\s+(\d+:\d+)#", $node, $m)) {
                    $itsegment['ArrDate'] = strtotime($m[4], strtotime($m[1]));
                    $itsegment['ArrName'] = $m[2];
                    $itsegment['ArrCode'] = $m[3];
                }
                $flights[] = $itsegment;
            }
        }

        if (!empty($flights)) {
            $result['Kind'] = 'T';
            $result['TripNumber'] = $tripNum;
            $result['RecordLocator'] = CONFNO_UNKNOWN;
            $result['ReservationDate'] = $resDate;

            if (isset($pax) && count($pax) > 0) {
                $result['Passengers'] = array_unique($pax);
            }
            $result['TripSegments'] = $flights;
            $its[] = $result;
        }

        if (isset($resTot) && isset($resCur)) {
            if (count($its) > 1) {
                return [
                    'emailType'  => 'ETicketHTML2',
                    'parsedData' => ['Itineraries' => $its, 'TotalCharge' => [
                        'Amount'   => $resTot,
                        'Currency' => $resCur,
                    ]],
                ];
            } elseif (count($its) === 1) {
                $its[0]['TotalCharge'] = $resTot;
                $its[0]['Currency'] = $resCur;

                if (isset($tickets)) {
                    $its[0]['TicketNumbers'] = array_filter($tickets);
                }
            }
        }

        return [
            'emailType'  => 'ETicketHTML2',
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    private function parseEmailHTML3()//7098206
    {
        $resDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(.),'Booked on')]", null, true, "#Booked on\s*:\s*(.*)#")));
        $tripNum = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Booking ID') or starts-with(normalize-space(),'Booking Id')]/following::text()[normalize-space()][1]", null, true, '/^[:\s-]*([-A-Z\d]+)$/');

        $pax = $this->http->FindNodes('//text()[contains(normalize-space(),"Passenger name") or contains(normalize-space(),"Passanger name") or contains(normalize-space(),"Passanger Name")]/following::text()[normalize-space()][1]', null, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
        $pax = array_filter($pax);

        $totalcharge = $this->http->FindSingleNode("//text()[contains(.,'You have paid')]", null, true, "#You have paid\s+(.+?)[,.;?!]*$#m");

        if (preg_match('#\s*([A-Z]{2,3})\.?\s*(\d[0-9\s\.,]+)\s*#i', $totalcharge, $m)) {
            if ($m[1] == 'Rs') {
                $resCur = 'INR';
            } else {
                $resCur = $m[1];
            }
            $m[2] = str_replace(',', '', $m[2]);
            $resTot = $m[2];
        }

        $bookingType = 'flight';
        $xpath = "//img[contains(@src,'goibibo') and contains(@src,'carrier')]/ancestor::table[1]/ancestor::td[1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->debug('Flight segments not found!');
            $xpath = "//text()[starts-with(normalize-space(),'Train No:')]/ancestor::table[1]/ancestor::td[1]";
            $segments = $this->http->XPath->query($xpath);

            if ($segments->length > 0) {
                $bookingType = 'train';
                $this->logger->debug('Found train segments: ' . $segments->length);
            }
        }

        $its = [];
        $result['Kind'] = 'T';

        if ($bookingType === 'train') {
            $result['TripCategory'] = TRIP_CATEGORY_TRAIN;
        }
        $result['TripNumber'] = $tripNum;
        $result['RecordLocator'] = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'PNR')][1]", null, true, "#PNR[\s-]+([A-Z\d]+)(?:[\s()]|$)#");

        if (!$result['RecordLocator']) {
            $result['RecordLocator'] = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'PNR')][1]/following::text()[normalize-space()][1]", null, true, '/^[:\s-]*([A-Z\d]{5,})$/');
        }

        if (!$result['RecordLocator']) {
            $result['RecordLocator'] = CONFNO_UNKNOWN;
        }
        $result['ReservationDate'] = $resDate;

        if (count($pax) > 0) {
            $result['Passengers'] = array_unique($pax);
        }

        foreach ($segments as $root) {
            $itsegment = [];
            $node = implode("\n", $this->http->FindNodes("descendant::text()[normalize-space()]", $root));

            if ($bookingType === 'flight' && preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s+(.+)/m', $node, $m)) {
                // SU 2014 Economy
                $itsegment['AirlineName'] = $m[1];
                $itsegment['FlightNumber'] = $m[2];
                $itsegment['Cabin'] = $m[3];
            } elseif ($bookingType === 'train' && preg_match('/^Train No[:\s]+(\d+)$/m', $node, $m)) {
                // Train No: 12658
                $itsegment['FlightNumber'] = $m[1];
            }
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]", $root)));
            $node = implode("\n", $this->http->FindNodes("./following::td[1]/descendant::td[1]//text()[normalize-space(.)]", $root));

            if (preg_match("#(.+)\n+(\d+:\d+)#", $node, $m)) {
                $itsegment['DepDate'] = strtotime($m[2], $date);

                if (preg_match('/^[A-Z]{3}$/', $m[1])) {
                    $itsegment['DepCode'] = $m[1];
                } else {
                    $itsegment['DepName'] = $m[1];
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                }
            }
            $node = implode("\n", $this->http->FindNodes("./following::td[1]/descendant::td[3]//text()[normalize-space(.)]", $root));

            if (preg_match("#(.+)(?:\n+(\d+:\d+))?#", $node, $m)) {
                if (!empty($m[2])) {
                    $itsegment['ArrDate'] = strtotime($m[2], $date);
                } else {
                    $itsegment['ArrDate'] = MISSING_DATE;
                }

                if (preg_match('/^[A-Z]{3}$/', $m[1])) {
                    $itsegment['ArrCode'] = $m[1];
                } else {
                    $itsegment['ArrName'] = $m[1];
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
            }
            $result['TripSegments'][] = $itsegment;
        }
        $its[] = $result;

        if (isset($resTot) && isset($resCur)) {
            if (count($its) > 1) {
                return [
                    'emailType'  => 'ETicketHTML3',
                    'parsedData' => ['Itineraries' => $its, 'TotalCharge' => [
                        'Amount'   => $resTot,
                        'Currency' => $resCur,
                    ]],
                ];
            } elseif (count($its) === 1) {
                $its[0]['TotalCharge'] = $resTot;
                $its[0]['Currency'] = $resCur;

                if (isset($tickets)) {
                    $its[0]['TicketNumbers'] = array_filter($tickets);
                }
            }
        }

        return [
            'emailType'  => 'ETicketHTML3',
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    private function parseEmailHTML4()//7098583
    {
        $resDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(.),'Booked on')]/following::text()[normalize-space(.)][1]")));
        $tripNum = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Premier Miles Ref No') or contains(normalize-space(.),'PremierMiles Ref No')]/following::text()[normalize-space(.)][1]");

        $pax = $this->http->FindNodes("//text()[normalize-space(.)='Name']/ancestor::tr[1]/following-sibling::tr/td[1]");

        $totalcharge = $this->http->FindSingleNode("//text()[normalize-space(.)='Total Price']/ancestor::tr[1]/following-sibling::tr/td[normalize-space(.)][last()]");

        if (preg_match('#\s*([A-Z]{2,3})\.?\s*(\d[0-9\s\.,]+)\s*#i', $totalcharge, $m)) {
            if ($m[1] == 'Rs') {
                $resCur = 'INR';
            } else {
                $resCur = $m[1];
            }
            $m[2] = str_replace(',', '', $m[2]);
            $resTot = $m[2];
        }

        $xpath = "//text()[normalize-space(.)='Flight']/ancestor::tr[1][contains(.,'Depart')]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);
        $its = [];
        $airs = [];

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./td[last()]", $root);
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $nodes) {
            $result['Kind'] = 'T';
            $result['TripNumber'] = $tripNum;
            $result['RecordLocator'] = $rl;

            if (!$result['RecordLocator']) {
                $result['RecordLocator'] = CONFNO_UNKNOWN;
            }
            $result['ReservationDate'] = $resDate;

            if (isset($pax) && count($pax) > 0) {
                $result['Passengers'] = array_unique($pax);
            }

            foreach ($nodes as $root) {
                $itsegment = [];
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root)));
                $node = $this->http->FindSingleNode("./td[2]", $root);

                if (preg_match("#\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])[\-\s]+(\d+)#m", $node, $m)) {
                    $itsegment['AirlineName'] = $m[1];
                    $itsegment['FlightNumber'] = $m[2];
                }
                $node = implode("\n", $this->http->FindNodes("./td[3]//text()[normalize-space(.)]", $root));

                if (preg_match("#(\d+:\d+)\n(.+)(?:\nTerminal\s+(.+))?#", $node, $m)) {
                    $itsegment['DepDate'] = strtotime($m[1], $date);
                    $itsegment['DepName'] = $m[2];

                    if (isset($m[3]) && !empty($m[3])) {
                        $itsegment['DepartureTerminal'] = $m[3];
                    }
                }
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                $node = implode("\n", $this->http->FindNodes("./td[4]//text()[normalize-space(.)]", $root));

                if (preg_match("#(\d+:\d+)\n(.+)(?:\nTerminal\s+(.+))?#", $node, $m)) {
                    $itsegment['ArrDate'] = strtotime($m[1], $date);
                    $itsegment['ArrName'] = $m[2];

                    if (isset($m[3]) && !empty($m[3])) {
                        $itsegment['ArrivalTerminal'] = $m[3];
                    }
                }
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                $result['TripSegments'][] = $itsegment;
            }
            $its[] = $result;
        }

        if (isset($resTot) && isset($resCur)) {
            if (count($its) > 1) {
                return [
                    'emailType'  => 'ETicketHTML4',
                    'parsedData' => ['Itineraries' => $its, 'TotalCharge' => [
                        'Amount'   => $resTot,
                        'Currency' => $resCur,
                    ]],
                ];
            } elseif (count($its) === 1) {
                $its[0]['TotalCharge'] = $resTot;
                $its[0]['Currency'] = $resCur;

                if (isset($tickets)) {
                    $its[0]['TicketNumbers'] = array_filter($tickets);
                }
            }
        }

        return [
            'emailType'  => 'ETicketHTML4',
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    private function parseEmailHTML5()//7007249
    {
        $resDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(.),'Booking date')]", null, true, "#Booking date\s*:\s*(.+)#")));
        $tripNum = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Your Booking No')]/following::text()[normalize-space(.)][1]");

        $pax = $this->http->FindNodes("//text()[normalize-space(.)='Passenger Name' or normalize-space(.)='Passanger Name']/ancestor::tr[1][contains(.,'Type')]/following-sibling::tr/td[1]");

        $totalcharge = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'We have charged you')]", null, true, "#We have charged you\s+(.+)#");

        if (preg_match('#\s*([A-Z]{2,3})\.?\s*(\d[0-9\s\.,]+)\s*#i', $totalcharge, $m)) {
            if ($m[1] == 'Rs') {
                $resCur = 'INR';
            } else {
                $resCur = $m[1];
            }
            $m[2] = str_replace(',', '', $m[2]);
            $resTot = $m[2];
        }

        $xpath = "//text()[normalize-space(.)='Flight']/ancestor::tr[1][contains(.,'Depart')]/following-sibling::tr[1]";
        $nodes = $this->http->XPath->query($xpath);
        $its = [];
        $airs = [];

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./td[last()-1]", $root);
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $nodes) {
            $result['Kind'] = 'T';
            $result['TripNumber'] = $tripNum;
            $result['RecordLocator'] = $rl;
            $result['TicketNumbers'] = array_filter(array_values(array_unique($this->http->FindNodes("//text()[normalize-space(.)='{$rl}']/ancestor::td[1]/following-sibling::td[1]"))));

            if (!$result['RecordLocator']) {
                $result['RecordLocator'] = CONFNO_UNKNOWN;
            }
            $result['ReservationDate'] = $resDate;

            if (isset($pax) && count($pax) > 0) {
                $result['Passengers'] = array_unique($pax);
            }

            foreach ($nodes as $root) {
                $itsegment = [];
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root)));
                $node = $this->http->FindSingleNode("./td[2]", $root);

                if (preg_match("#(.+)\s+(\d+)#", $node, $m)) {
                    $itsegment['AirlineName'] = $m[1];
                    $itsegment['FlightNumber'] = $m[2];
                }
                $node = implode("\n", $this->http->FindNodes("./td[3]//text()[normalize-space(.)]", $root));

                if (preg_match("#(\d+:\d+)\s+([A-Z]{3})#", $node, $m)) {
                    $itsegment['DepDate'] = strtotime($m[1], $date);
                    $itsegment['DepCode'] = $m[2];
                }
                $node = implode("\n", $this->http->FindNodes("./td[4]//text()[normalize-space(.)]", $root));

                if (preg_match("#(\d+:\d+)\s+([A-Z]{3})#", $node, $m)) {
                    $itsegment['ArrDate'] = strtotime($m[1], $date);
                    $itsegment['ArrCode'] = $m[2];
                }
                $result['TripSegments'][] = $itsegment;
            }
            $its[] = $result;
        }

        if (isset($resTot) && isset($resCur)) {
            if (count($its) > 1) {
                return [
                    'emailType'  => 'ETicketHTML5',
                    'parsedData' => ['Itineraries' => $its, 'TotalCharge' => [
                        'Amount'   => $resTot,
                        'Currency' => $resCur,
                    ]],
                ];
            } elseif (count($its) === 1) {
                $its[0]['TotalCharge'] = $resTot;
                $its[0]['Currency'] = $resCur;

                if (isset($tickets)) {
                    $its[0]['TicketNumbers'] = array_filter($tickets);
                }
            }
        }

        return [
            'emailType'  => 'ETicketHTML5',
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    private function parseEmailHTML6()//11356588
    {
        $resDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(.),'Booked on')]", null, true, "#Booked on\s*:\s*(.*)#")));
        $tripNum = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Booking ID')]/following::text()[normalize-space(.)][1]");

        $xpath = "//text()[starts-with(normalize-space(.),'We have received a schedule change')]";
        $nodes = $this->http->XPath->query($xpath);
        $its = [];

        foreach ($nodes as $root) {
            $result['Kind'] = 'T';
            $result['TripNumber'] = $tripNum;
            $result['RecordLocator'] = CONFNO_UNKNOWN;
            $result['ReservationDate'] = $resDate;
            $node = $this->http->FindSingleNode(".", $root);
            $itsegment = [];

            if (preg_match("#for your flight, ([A-Za-z\d]{2})\s*(\d+), from ([A-Z]{3}) to ([A-Z]{3}).+?depart at (\d+:\d+(?:(?i)\s*[ap]m)?) on (\d+)\/(\d+)\/(\d+)#", $node, $m)) {
                if (preg_match("#^(\d+)$#", $m[1])) {
                    $itsegment['FlightNumber'] = $m[1] . $m[2];
                } else {
                    $itsegment['AirlineName'] = strtoupper($m[1]);
                    $itsegment['FlightNumber'] = $m[2];
                }
                $itsegment['DepCode'] = $m[3];
                $itsegment['ArrCode'] = $m[4];

                $date = strtotime($m[8] . '-' . $m[6] . '-' . $m[7]);
                $itsegment['DepDate'] = strtotime($m[5], $date);
                $itsegment['ArrDate'] = MISSING_DATE;
            }
            $result['TripSegments'][] = $itsegment;
            $its[] = $result;
        }

        return [
            'emailType'  => 'ETicketHTML6',
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    private function countP($first, $root = null)
    {
        if ($pos1 = $this->pdf->XPath->query("./preceding::p", $root)->length) {
            if ($pos2 = $this->pdf->XPath->query("./following::p[contains(.,'{$first}')][1]/preceding::p", $root)->length) {
                return $pos2 - $pos1;
            }
        }

        return 0;
    }

    private function normalizeDate($date)
    {
        //	    $this->logger->alert("Date: {$date}");
        $in = [
            '#(\d+)\s+(\w{3})\s+(\d+)\s+(\d+:\d+)#',
            //Sept. 20, 2016, 4:54 p.m.
            '#^\s*(\w+)\.?\s+(\d+),\s+(\d+)\s+(\d+:\d+)\s+([ap])\.?(m)\.?\s*$#i',
            '#^\s*(\d+)\/(\d+)\/(\d+)\s*$#',
        ];
        $out = [
            '$1 $2 $3 $4',
            '$2 $1 $3 $4 $5$6',
            '$3-$2-$1',
        ];
        $str = preg_replace($in, $out, $date);

        return $str;
    }
}
