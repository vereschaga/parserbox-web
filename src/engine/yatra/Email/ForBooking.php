<?php

namespace AwardWallet\Engine\yatra\Email;

class ForBooking extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "yatra/it-5017234.eml, yatra/it-5017248.eml, yatra/it-5017280.eml, yatra/it-5017295.eml";

    public $reBody = [
        'en' => ['Yatra booking n', 'Journey Detail'],
    ];
    public $reSubject = [
        'Your Yatra e-Ticket for booking',
        'Yatra MyBookings',
    ];
    public $lang = 'en';
    public $dateEmail;
    public $dateRes;
    public static $TypesCount = 3;
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);
        $this->dateEmail = $parser->getDate();
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ForBooking",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $this->AssignLang($body);

        return stripos($body, $this->reBody[$this->lang][0]) !== false && stripos($body, $this->reBody[$this->lang][1]) !== false;
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
        return stripos($from, "yatra.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) * (self::$TypesCount);
    }

    private function parseEmailType1($recLocs)
    {   //echo 1;
        $pax = array_unique($this->http->FindNodes("//*[normalize-space(text())='" . $this->t('Passenger Name') . "']/ancestor::tr[1]/following-sibling::tr//td[4]"));
        $tickets = array_unique($this->http->FindNodes("//*[normalize-space(text())='" . $this->t('Passenger Name') . "']/ancestor::tr[1]/following-sibling::tr//td[5]", null, "#([\d\- ]{5,})#"));

        $its = [];

        foreach ($recLocs as $recLoc) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $recLoc;
            $it['Passengers'] = $pax;

            if (!empty($tickets)) {
                $it['TicketNumbers'] = $tickets;
            }

            if ($this->dateRes !== null) {
                $it['ReservationDate'] = $this->dateRes;
            }
            $xpath = "//*[normalize-space(text())='{$recLoc}']/ancestor::table[2]/descendant::tr[1]";
            $roots = $this->http->XPath->query($xpath);

            foreach ($roots as $root) {
                $seg = [];
                $node = $this->http->FindSingleNode("./td[2]", $root);

                if (preg_match("#([A-Z\d]{2})\s*-\s*(\d+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $node = $this->http->FindSingleNode("./td[3]", $root);

                if (preg_match("#Departure:\s*(?<DepName>.+?)\s*(?:T-(?<DepartureTerminal>.+?)|\s*-\s*)\s*(?<DepDate>\d+\s*\w+\s*\d+\s+\d+:\d+)\s+Hrs#", $node, $m)) {
                    $seg['DepName'] = $m['DepName'];
                    $seg['DepartureTerminal'] = $m['DepartureTerminal'];
                    $seg['DepDate'] = strtotime($m['DepDate']);
                }
                $node = $this->http->FindSingleNode("./td[4]", $root);

                if (preg_match("#Arrival:\s*(?<ArrName>.+?)\s*(?:T-(?<ArrivalTerminal>.+?)|\s*-\s*)\s*(?<ArrDate>\d+\s*\w+\s*\d+\s+\d+:\d+)\s+Hrs#", $node, $m)) {
                    $seg['ArrName'] = $m['ArrName'];
                    $seg['ArrivalTerminal'] = $m['ArrivalTerminal'];
                    $seg['ArrDate'] = strtotime($m['ArrDate']);
                }

                $seg = array_filter($seg);
                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function parseEmailType2($recLocs)
    {   //echo 2;
        $pax = array_unique($this->http->FindNodes("//*[normalize-space(text())='" . $this->t('Passenger Name') . "']/ancestor::div[1]/following-sibling::div//div[4]"));
        $tickets = array_unique($this->http->FindNodes("//*[normalize-space(text())='" . $this->t('Passenger Name') . "']/ancestor::div[1]/following-sibling::div//div[5]", null, "#([\d\- ]{5,})#"));

        $its = [];

        foreach ($recLocs as $recLoc) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $recLoc;
            $it['Passengers'] = $pax;

            if (!empty($tickets)) {
                $it['TicketNumbers'] = $tickets;
            }

            if ($this->dateRes !== null) {
                $it['ReservationDate'] = $this->dateRes;
                $year = date('Y', $this->dateRes);
            } else {
                $year = date('Y', strtotime($this->dateEmail));
            }
            $xpath = "//*[normalize-space(text())='{$recLoc}']/ancestor::div[2]/preceding-sibling::div[1]";
            $roots = $this->http->XPath->query($xpath);

            foreach ($roots as $root) {
                $seg = [];
                $node = $this->http->FindSingleNode("./div[2]/div[1]", $root);

                if (preg_match("#([A-Z\d]{2})\s*-\s*(\d+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $node = $this->http->FindSingleNode("./div[2]/div[2]", $root);

                if (preg_match("#,(\s*\d+\s*\w+)\s*(\d+\:\d+)#", $node, $m)) {
                    $seg['DepDate'] = strtotime($m[1] . ' ' . $year . ' ' . $m[2]);
                }

                if (isset($it['ReservationDate'])) {
                    if ($it['ReservationDate'] > $seg['DepDate']) {
                        $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);
                        $year++;
                    }
                } else {
                    if (strtotime($this->dateEmail) > $seg['DepDate']) {
                        $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);
                        $year++;
                    }
                }
                $node = $this->http->FindSingleNode("./div[2]/div[3]", $root);

                if (preg_match("#,(\s*\d+\s*\w+)\s*(\d+\:\d+)#", $node, $m)) {
                    $seg['ArrDate'] = strtotime($m[1] . ' ' . $year . ' ' . $m[2]);
                }

                $node = $this->http->FindSingleNode("./div[3]/div[2]", $root);

                if (preg_match("#(?<DepName>.+?)(?:,\s*Terminal\s*(?<DepartureTerminal>.+)|$)#", $node, $m)) {
                    $seg['DepName'] = $m['DepName'];

                    if (isset($m['DepartureTerminal'])) {
                        $seg['DepartureTerminal'] = $m['DepartureTerminal'];
                    }
                }
                $node = $this->http->FindSingleNode("./div[3]/div[3]", $root);

                if (preg_match("#(?<ArrName>.+?)(?:,\s*Terminal\s*(?<ArrivalTerminal>.+)|$)#", $node, $m)) {
                    $seg['ArrName'] = $m['ArrName'];

                    if (isset($m['ArrivalTerminal'])) {
                        $seg['ArrivalTerminal'] = $m['ArrivalTerminal'];
                    }
                }

                $seg = array_filter($seg);
                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function parseEmailType3($recLocs)
    {   //echo 3;
        $pax = array_unique($this->http->FindNodes("//*[normalize-space(text())='" . $this->t('Passenger Name') . "']/ancestor::div[1]/following-sibling::div//div[4]"));
        $tickets = array_unique($this->http->FindNodes("//*[normalize-space(text())='" . $this->t('Passenger Name') . "']/ancestor::div[1]/following-sibling::div//div[5]", null, "#([\d\- ]{5,})#"));

        $its = [];

        foreach ($recLocs as $recLoc) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $recLoc;
            $it['Passengers'] = $pax;

            if (!empty($tickets)) {
                $it['TicketNumbers'] = $tickets;
            }

            if ($this->dateRes !== null) {
                $it['ReservationDate'] = $this->dateRes;
                $year = date('Y', $this->dateRes);
            } else {
                $year = date('Y', strtotime($this->dateEmail));
            }
            $xpath = "//*[contains(normalize-space(text()),'" . $this->t('Departure') . "')]/ancestor::div[2]";
            $roots = $this->http->XPath->query($xpath);

            foreach ($roots as $root) {
                $seg = [];
                $node = $this->http->FindSingleNode("./div[2]", $root);

                if (preg_match("#([A-Z\d]{2})\s*-\s*(\d+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $node = $this->http->FindSingleNode("./div[3]", $root);

                if (preg_match("#,(\s*\d+\s*\w+)\s*(\d+\:\d+)#", $node, $m)) {
                    $seg['DepDate'] = strtotime($m[1] . ' ' . $year . ' ' . $m[2]);
                }

                if (isset($it['ReservationDate'])) {
                    if ($it['ReservationDate'] > $seg['DepDate']) {
                        $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);
                        $year++;
                    }
                } else {
                    if (strtotime($this->dateEmail) > $seg['DepDate']) {
                        $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);
                        $year++;
                    }
                }
                $node = $this->http->FindSingleNode("./div[4]", $root);

                if (preg_match("#,(\s*\d+\s*\w+)\s*(\d+\:\d+)#", $node, $m)) {
                    $seg['ArrDate'] = strtotime($m[1] . ' ' . $year . ' ' . $m[2]);
                }

                $node = $this->http->FindSingleNode("./div[6]", $root);

                if (preg_match("#(?<DepName>.+?)(?:,\s*Terminal\s*(?<DepartureTerminal>.+)|$)#", $node, $m)) {
                    $seg['DepName'] = $m['DepName'];

                    if (isset($m['DepartureTerminal'])) {
                        $seg['DepartureTerminal'] = $m['DepartureTerminal'];
                    }
                }
                $node = $this->http->FindSingleNode("./div[7]", $root);

                if (preg_match("#(?<ArrName>.+?)(?:,\s*Terminal\s*(?<ArrivalTerminal>.+)|$)#", $node, $m)) {
                    $seg['ArrName'] = $m['ArrName'];

                    if (isset($m['ArrivalTerminal'])) {
                        $seg['ArrivalTerminal'] = $m['ArrivalTerminal'];
                    }
                }

                $seg = array_filter($seg);
                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function parseEmail()
    {
        $its = [];

        $node = $this->http->FindSingleNode("//*[contains(normalize-space(text()),'" . $this->t('Booking Date') . "')]", null, true, '#:\s*(.+)#');
        $node = str_replace('Hrs', ' ', str_replace(',', ' ', $node));

        if (preg_match('#(\d+\s*\w+\s*\d+(?:\s*\d+:\d+)?)#', $node, $m)) {
            $this->dateRes = strtotime($m[1]);
        }

        $recLocs = array_unique($this->http->FindNodes("//*[normalize-space(text())='" . $this->t('Airline PNR') . "']/ancestor::tr[1]", null, "#" . $this->t('Airline PNR') . "\s*(\w+)#"));

        if (count($recLocs) == 0) {
            $recLocs = array_unique($this->http->FindNodes("//*[normalize-space(text())='" . $this->t('Airline PNR') . "']/ancestor::div[1]", null, "#" . $this->t('Airline PNR') . "\s*(\w+)#"));

            if ($this->http->XPath->query("//*[contains(normalize-space(text()),'" . $this->t('Your Airline PNR for the below itinerary') . "')]")->length > 0) {
                $parserType = 3;
            } else {
                $parserType = 2;
            }
        } else {
            $parserType = 1;
        }

        switch ($parserType) {
            case 1:
                $its = $this->parseEmailType1($recLocs);

                break;

            case 2:
                $its = $this->parseEmailType2($recLocs);

                break;

            break;

            case 3:
                $its = $this->parseEmailType3($recLocs);

                break;

            break;
        }

        return $its;
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
