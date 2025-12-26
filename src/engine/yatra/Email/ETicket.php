<?php

namespace AwardWallet\Engine\yatra\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ETicket extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "yatra/it-4942587.eml, yatra/it-8949509.eml, yatra/it-9781932.eml";

    public $reBody = [
        'en' => ['Download Yatra App', 'PASSENGERS DETAILS'],
    ];
    public $reSubject = [
        'Your Yatra e-Ticket for booking',
        'Yatra MyBookings',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $tripNumber = array_unique($this->http->FindNodes("//*[text()='YATRA REF NUMBER']/ancestor::tr[1]/descendant::td[4]"));
        $pax = $this->http->FindNodes("//*[contains(text(),'" . $this->t('PASSENGERS DETAILS') . "')]/ancestor::table[2]/following-sibling::table[1]/descendant::table[1]//td[position()=1 and string-length(.)>2]//strong[not(contains(., ':'))]");
        $its = $this->parseEmail();

        $email->setType('ETicket');
        $r = $email->add()->flight();

        if (!empty($tripNumber[0])) {
            $r->general()->confirmation($tripNumber[0], "YATRA REF NUMBER");
        }

        if (!empty($pax)) {
            $r->general()
                ->travellers($pax, true);
        }

        foreach ($its as $it) {
            if (!empty($it)) {
                $s = $r->addSegment();
                $s->airline()->confirmation($it['RecordLocator']);
                $airline = $s->airline();

                if (!empty($it["AirlineName"])) {
                    $airline->name($it["AirlineName"]);
                }

                if (!empty($it["FlightNumber"])) {
                    $airline->number($it["FlightNumber"]);
                }

                $dep = $s->departure();

                if (!empty($it["DepName"])) {
                    $dep->name($it["DepName"]);
                }

                if (!empty($it["DepDate"])) {
                    $dep->date($it["DepDate"]);
                }

                if (!empty($it["DepartureTerminal"])) {
                    $dep->terminal($it["DepartureTerminal"]);
                }

                if (!empty($it["DepCode"])) {
                    $dep->code($it["DepCode"]);
                }

                $arr = $s->arrival();

                if (!empty($it["ArrName"])) {
                    $arr->name($it["ArrName"]);
                }

                if (!empty($it["ArrDate"])) {
                    $arr->date($it["ArrDate"]);
                }

                if (!empty($it["ArrivalTerminal"])) {
                    $arr->terminal($it["ArrivalTerminal"]);
                }

                if (!empty($it["ArrCode"])) {
                    $arr->code($it["ArrCode"]);
                }

                $duration = $s->extra();

                if (!empty($it["Duration"])) {
                    $duration->duration($it['Duration']);
                }
            }
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $this->AssignLang($body);

        return stripos($body, $this->reBody[$this->lang][0]) !== false && stripos($body,
                $this->reBody[$this->lang][1]) !== false;
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
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $its = [];
        $flights = $this->http->FindNodes("//img[contains(@src,'Flight.png') or contains(@src,'flight.png')]/ancestor::tr[1]");
        $flight_codes = "(//*[contains(text(),'" . $this->t('PASSENGERS DETAILS') . "')])[1]/ancestor::table[2]/following-sibling::table[1]/descendant::table[1]//tr[not(contains(.,'" . $this->t('DESTINATION') . "'))"; //and position()=1]/td[2]
        $xpath = "//*[text()='PNR']/ancestor::table[1]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $segment) {
            $seg = [];
            $recLoc = $this->http->FindSingleNode("./descendant::tr[2]/td[5]", $segment);

            if (!empty($recLoc)) {
                $seg['RecordLocator'] = $recLoc;
            }

            $node = $this->http->FindSingleNode(".//tr[3]/td[1]", $segment);

            if (preg_match("#([A-Z\d]{2})\s*-\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $depCity = $this->http->FindSingleNode(".//tr[2]/td[2]", $segment);
            $arrCity = $this->http->FindSingleNode(".//tr[2]/td[3]", $segment);

            foreach ($flights as $i => $fl) {
                if (preg_match("#{$depCity}.*{$arrCity}#", $fl)) {
                    $numseg = $i + 1;

                    break;
                }
            }

            $node = $this->http->FindSingleNode($flight_codes . "and position()={$numseg}]/td[2]");

            if (preg_match("#([A-Z]{3})\s*-\s*([A-Z]{3})#", $node, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['ArrCode'] = $m[2];
            }

            $node = $this->http->FindSingleNode(".//tr[3]/td[2]", $segment);

            if (preg_match("#,\s*(\w+\s+\d+\s+\d+\s+\d+:\d+)#", $node, $m)) {
                $seg['DepDate'] = strtotime($m[1]);
            }

            $depNode = $this->http->FindSingleNode(".//tr[4]/td[2]", $segment, true);

            if (preg_match("#(.+?)(?:,[\s]?[T]?[-]?(.+)|$)#", $depNode, $m)) {
                if (!empty($m[1])) {
                    $seg['DepName'] = $m[1];
                }

                if (!empty($m[2])) {
                    $seg['DepartureTerminal'] = $m[2];
                }
            }
            $node = $this->http->FindSingleNode(".//tr[3]/td[3]", $segment);

            if (preg_match("#,\s*(\w+\s+\d+\s+\d+\s+\d+:\d+)#", $node, $m)) {
                $seg['ArrDate'] = strtotime($m[1]);
            }

            $arrNode = $this->http->FindSingleNode(".//tr[4]/td[3]", $segment);

            if (preg_match("#(.+?)(?:,[\s]?[T]?[-]?(.+)|$)#", $arrNode, $m)) {
                if (!empty($m[1])) {
                    $seg['ArrName'] = $m[1];
                }

                if (!empty($m[2])) {
                    $seg['ArrivalTerminal'] = $m[2];
                }
            }
            $seg['Duration'] = $this->http->FindSingleNode(".//tr[2]/td[4]", $segment);
            $node = $this->http->FindSingleNode(".//tr[3]/td[4]", $segment);
            $seg['Stops'] = ($node == 'Non Stop') ? 0 : $node;

            $seg = array_filter($seg);

            $its[] = $seg;
        }
        $its = array_map("unserialize", array_unique(array_map("serialize", $its)));

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
