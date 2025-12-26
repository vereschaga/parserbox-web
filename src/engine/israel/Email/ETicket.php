<?php

namespace AwardWallet\Engine\israel\Email;

class ETicket extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "israel/it-2292986.eml, israel/it-2421479.eml, israel/it-2421480.eml, israel/it-2427344.eml, israel/it-4888555.eml, israel/it-4935137.eml, israel/it-5399630.eml";

    public $reBody = [
        'en' => ['Reservation Code', 'Please print your ticket as you may need'],
    ];
    public $reSubject = [
        'EL AL E-TICKET',
        'UP E-TICKET',
    ];
    public $lang = 'en';
    public $pdf;
    public static $dict = [
        'en' => [
        ],
    ];

    private $regExp = [
        '2' => '#.+\([A-Z]{3}\)#', //From
        '3' => '#.+\([A-Z]{3}\)#', //To
        '4' => '#[\dA-Z]{2}\s*\d+#', // Flight
        '5' => '#[A-Z]{1,2}#',  //Class
        '6' => '#\d{2}\s*\w+#',     //Date
        '7' => '#[0-2]?[0-9]\:[0-5][0-9](?:[PA]M)?#', //Time
        '8' => '#.+#',          //Status
        '9' => '#.+#',                 //Baggage
    ];
    private $regExpOld = [ //mapping
        '7' => '#[0-2]?[0-9]\:[0-5][0-9](?:[PA]M)?#', //Departure '8'
        '8' => '#[0-2]?[0-9]\:[0-5][0-9](?:[PA]M)?#', //Arrival     '10'
        '9' => '#.+#',          //Status                             '11'
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ETicket",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"elal.co.il")]')->length > 0) {
            $body = text($parser->getHTMLBody());

            foreach ($this->reBody as $value) {
                if (stripos($body, $value[0]) !== false && stripos($body, $value[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "elal-ticketing.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("(//td[contains(.,'Reservation Code')])[1]/ancestor::tr[1]/following::tr[normalize-space(.)][1]/td[2]");
        $it['Passengers'] = $this->http->FindNodes("//td[contains(.,'Passenger')]/ancestor::tr[1]/following::tr[normalize-space(.)][1]/td[2]", null, "#(.+)\s*\(#");
        $it['TicketNumbers'] = $this->http->FindNodes("//td[contains(.,'E-Ticket Number')]/ancestor::tr[1]/following::tr[normalize-space(.)][1]/td[4]");
        $it['AccountNumbers'] = $this->http->FindNodes("//td[contains(.,'Frequent flyer number')]/ancestor::tr[1]/following::tr[normalize-space(.)][1]/td[6]");
        $it['ReservationDate'] = strtotime($this->http->FindSingleNode("(//td[contains(.,'Issue Date')])[1]/ancestor::tr[1]/following::tr[normalize-space(.)][1]/td[4]"));
        $node = $this->http->FindSingleNode("//tr[contains(.,'Total Amount')]", null, true, "#Total Amount\s*\:\s+(.+)#");

        if ($node != null) {
            $it['TotalCharge'] = cost($node);
            $it['Currency'] = currency($node);
        }
        $node = $this->http->FindSingleNode("//tr[contains(.,'Fare')]", null, true, "#Fare\s*\:\s+(.+)#");

        if ($node != null) {
            $it['BaseFare'] = cost($node);
            $it['Currency'] = currency($node);
        }

        $flOld = ($this->http->XPath->query("(//text()[normalize-space(.)='Flight'])[1]/ancestor::tr[1][contains(., 'Arrival')]/following-sibling::tr[td[10]]")->length > 0);

        $rows = $this->http->XPath->query("(//text()[normalize-space(.)='Flight'])[1]/ancestor::tr[1][contains(., 'Arrival')]/following-sibling::tr[td[10] and normalize-space(.)]");

        foreach ($rows as $row) {
            $seg = [];
            $seekstr = "";
            $dateFly = "";

            foreach ($this->regExp as $i => $value) {
                switch ($i) {
                    case '2':
                        $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value);

                        if (($node != null) && (preg_match("#(.+)\(([A-Z]{3})\)#", $node, $m))) {
                            $seg['DepName'] = $m[1];
                            $seg['DepCode'] = $m[2];
                        }

                        break;

                    case '3':
                        $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value);

                        if (($node != null) && (preg_match("#(.+)\(([A-Z]{3})\)#", $node, $m))) {
                            $seg['ArrName'] = $m[1];
                            $seg['ArrCode'] = $m[2];
                        }

                        break;

                    case '4':
                        $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value);

                        if (($node != null) && (preg_match("#([\dA-Z]{2})\s*(\d+)#", $node, $m))) {
                            $seekstr = trim($node);
                            $seg['AirlineName'] = $m[1];
                            $seg['FlightNumber'] = $m[2];
                        }

                        break;

                    case '5':
                        $seg['BookingClass'] = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value);

                        break;

                    case '6':
                        $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value);

                        if ($node !== null) {
                            $dateFly = $node;
                            $seekstr = trim($node) . ' ' . str_replace(' ', '', $seekstr);
                        }

                        break;

                    case '7':
                        if ($flOld) {
                            $node = $this->http->FindSingleNode("./td[8]", $row, true, $this->regExpOld[$i]);

                            if ($node !== null) {
                                $year = date('Y', $it['ReservationDate']);
                                $seg['DepDate'] = strtotime($dateFly . $year . " " . $node);

                                if ($seg['DepDate'] < $it['ReservationDate']) {
                                    $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);
                                }
                            }
                        } else {
                            $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value);

                            if ($node !== null) {
                                $year = date('Y', $it['ReservationDate']);
                                $seg['DepDate'] = strtotime($dateFly . $year . " " . $node);

                                if ($seg['DepDate'] < $it['ReservationDate']) {
                                    $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);
                                }
                            }
                            $seg['ArrDate'] = MISSING_DATE;
                        }

                        break;

                    case '8':
                        if (!$flOld) {
                            $it['Status'] = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value);
                        } else {
                            $node = $this->http->FindSingleNode("./td[10]", $row, true, $this->regExpOld[$i]);

                            if ($node !== null) {
                                $year = date('Y', $it['ReservationDate']);
                                $seg['ArrDate'] = strtotime($dateFly . $year . " " . $node);

                                if ($seg['ArrDate'] < $it['ReservationDate']) {
                                    $seg['ArrDate'] = strtotime("+1 year", $seg['ArrDate']);
                                }
                            }
                        }

                        break;

                    case '9':
                        if ($flOld) {
                            $it['Status'] = $this->http->FindSingleNode("./td[11]", $row, true, $this->regExpOld[$i]);
                        }

                        break;
                }
            }
            $node = $this->http->FindSingleNode("//tr[contains(.,'{$seekstr}')]");

            if ($node != null) {
                if (preg_match("#DEPARTURE TERMINAL\s+(.+)\s+\/#", $node, $m)) {
                    $seg['DepartureTerminal'] = $m[1];
                }

                if (preg_match("#ARRIVAL TERMINAL\s+(.+)\s*\/?#", $node, $m)) {
                    $seg['ArrivalTerminal'] = $m[1];
                }
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        return true;
    }
}
