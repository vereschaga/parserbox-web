<?php

namespace AwardWallet\Engine\maketrip\Email;

class ETicket4Booking extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "maketrip/it-2822914.eml, maketrip/it-5096010.eml, maketrip/it-5109994.eml";

    public $reSubject = [
        'MakeMyTrip E-Ticket for Booking',
        'E-Ticket & Invoice for your',
    ];
    public $lang = 'en';
    public $pdf;
    public static $dict = [
        'en' => [
            'Passenger(s)' => 'Passenger(s)',
            'Departure'    => 'Departure',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ETicket4Booking" . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'makemytrip.com')]")->length > 0) {
            $body = $parser->getHTMLBody();

            foreach (self::$dict as $lang => $reBody) {
                if (stripos($body, $reBody['Passenger(s)']) !== false && stripos($body, $reBody['Departure']) !== false) {
                    return true;
                }
            }
        }

        return false;
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

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "makemytrip.com") !== false;
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
        $its = [];
        $dateRes = $this->http->FindSingleNode("//*[contains(text(),'Booking Date')]", null, true, "#\-\s*(.+)#");
        $dateRes = str_replace(",", " ", $dateRes);
        $recLocs = array_unique($this->http->FindNodes("//text()[contains(.,'Passenger(s)')]/ancestor::tr[1]/following-sibling::tr/td[1]/span[2]"));

        foreach ($recLocs as $recLoc) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = str_replace(" ", "", $recLoc);
            $it['ReservationDate'] = strtotime($dateRes);
            $it['Passengers'] = array_unique($this->http->FindNodes("//text()[contains(.,'{$recLoc}')]/ancestor::span[1]/preceding-sibling::span"));
            $xpath = "//text()[contains(.,'{$recLoc}')]/ancestor::tr[2]/preceding-sibling::tr[1]";
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $seg = [];
                $seg['Operator'] = $this->http->FindSingleNode("./preceding-sibling::tr[1]//td[2]/span[1]", $root);
                $node = $this->http->FindSingleNode("./preceding-sibling::tr[1]//td[2]/span[2]", $root);

                if (preg_match("#([A-Z\d]{2})\s*-?\s*(\d+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                $dateDep = $this->http->FindSingleNode("./descendant::tr[td[contains(text(),'Departure')]]/following-sibling::tr[3]/td[1]", $root);
                $dateArr = $this->http->FindSingleNode("./descendant::tr[td[contains(text(),'Departure')]]/following-sibling::tr[3]/td[3]", $root);
                $node = $this->http->FindSingleNode("./descendant::tr[td[contains(text(),'Departure')]]/following-sibling::tr[1]/td[1]", $root);

                if (preg_match("#([A-Z]{3})\s*\(\s*(\d+)\s*:\s*(\d+)\s*\)#", $node, $m)) {
                    $seg['DepDate'] = strtotime($dateArr . ' ' . $m[2] . ':' . $m[3]);
                    $seg['DepCode'] = $m[1];
                }
                $node = $this->http->FindSingleNode("./descendant::tr[td[contains(text(),'Departure')]]/following-sibling::tr[1]/td[3]", $root);

                if (preg_match("#([A-Z]{3})\s*\(\s*(\d+)\s*:\s*(\d+)\s*\)#", $node, $m)) {
                    $seg['ArrDate'] = strtotime($dateDep . ' ' . $m[2] . ':' . $m[3]);
                    $seg['ArrCode'] = $m[1];
                }
                $seg['DepName'] = $this->http->FindSingleNode("./descendant::tr[td[contains(text(),'Departure')]]/following-sibling::tr[2]/td[1]", $root);
                $seg['ArrName'] = $this->http->FindSingleNode("./descendant::tr[td[contains(text(),'Departure')]]/following-sibling::tr[2]/td[3]", $root);

                $seg['Cabin'] = implode(",", array_unique($this->http->FindNodes("./following-sibling::tr[1]//tr[position()>1]/td[3]", $root)));

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
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
        foreach (self::$dict as $lang => $reBody) {
            if (stripos($body, $reBody['Passenger(s)']) !== false && stripos($body, $reBody['Departure']) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        return true;
    }
}
