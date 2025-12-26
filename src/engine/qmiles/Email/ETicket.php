<?php

namespace AwardWallet\Engine\qmiles\Email;

class ETicket extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "qmiles/it-8564705.eml, qmiles/it-8687462.eml";

    public $reProvider = 'qatarairways.com';
    public $reFrom = 'traveldocument@qatarairways.com.qa';
    public $reSubject = [
        'E-Ticket',
    ];
    public $reBody = [
        ["Flight No.", "Departure / Arrival"],
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ETicket",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'qatarairways.com')]")->length > 0 || $this->http->XPath->query("//img[contains(@src,'www.qatarairways.com')]")->length > 0) {
            $text = $parser->getHTMLBody();

            foreach ($this->reBody as $reBody) {
                if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (strpos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reProvider) !== false;
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
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(.,'PNR:')]", null, true, "#PNR:\s*([A-Z\d]+)#");

        $it['Passengers'] = array_unique($this->http->FindNodes("//text()[contains(.,'Passenger Name')]/ancestor::tr[contains(.,'Ticket No')][1]/following-sibling::tr/td[1][not(./@colspan)]"));

        $it['TicketNumbers'] = array_filter(array_unique($this->http->FindNodes("//text()[contains(.,'Passenger Name')]/ancestor::tr[contains(.,'Ticket No')][1]/following-sibling::tr/td[3]", null, "#^\s*([\d\-\s]+)\s*$#")));

        $BaseFare = $this->http->FindNodes("//text()[contains(.,'Passenger Name')]/ancestor::tr[contains(.,'Ticket No')][1]/following-sibling::tr/td[6]", null, "#^\s*(\d+)\s*$#");
        $it['BaseFare'] = 0.0;

        foreach ($BaseFare as $value) {
            $it['BaseFare'] = (float) $value;
        }

        $Tax = $this->http->FindNodes("//text()[contains(.,'Passenger Name')]/ancestor::tr[contains(.,'Ticket No')][1]/following-sibling::tr/td[7]", null, "#^\s*(\d+)\s*$#");
        $it['Tax'] = 0.0;

        foreach ($Tax as $value) {
            $it['Tax'] = (float) $value;
        }
        $TotalCharge = $this->http->FindNodes("//text()[contains(.,'Passenger Name')]/ancestor::tr[contains(.,'Ticket No')][1]/following-sibling::tr/td[8]", null, "#^\s*(\d+)\s*$#");
        $it['TotalCharge'] = 0.0;

        foreach ($TotalCharge as $value) {
            $it['TotalCharge'] = (float) $value;
        }
        $it['Currency'] = $this->http->FindSingleNode("//text()[contains(.,'Passenger Name')]/ancestor::tr[contains(.,'Ticket No')][1]/td[8][1]", null, true, "#.*\(([A-Z]{3})\)#");

        $rows = $this->http->XPath->query("//text()[contains(.,'Flight No.')]/ancestor::tr[contains(.,'Departure / Arrival')][1]/following-sibling::tr");

        foreach ($rows as $row) {
            $seg = [];
            $node = $this->http->FindSingleNode("./td[1]", $row);

            if (preg_match("#(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m['AirlineName'];
                $seg['FlightNumber'] = $m['FlightNumber'];
            } elseif (preg_match("#(?<AirlineName>[A-Z\d]{2})\s*OPEN#", $node, $m)) {
                $seg['AirlineName'] = $m['AirlineName'];
                $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            }

            $route = implode("\n", $this->http->FindNodes("./td[2]//text()", $row));

            if (preg_match("#(?<DepName>.*)\((?<DepCode>[A-Z]{3})\)\s+\w+,\s*(?<DepDate>\d{1,2}\s*\w+)\s*(?<DepTime>\d{2},\s*\d+:\d+)\s+"
                    . "(?<ArrName>.*)\((?<ArrCode>[A-Z]{3})\)\s+\w+,\s*(?<ArrDate>\d{1,2}\s*\w+)\s*(?<ArrTime>\d{2},\s*\d+:\d+)#", $route, $m)) {
                $seg['DepName'] = trim($m['DepName']);
                $seg['DepCode'] = $m['DepCode'];
                $seg['DepDate'] = strtotime($m['DepDate'] . ' 20' . $m['DepTime']);
                $seg['ArrName'] = trim($m['ArrName']);
                $seg['ArrCode'] = $m['ArrCode'];
                $seg['ArrDate'] = strtotime($m['ArrDate'] . ' 20' . $m['ArrTime']);
            }
            $seg['Cabin'] = $this->http->FindSingleNode("./td[3]", $row);

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

    //	private function AssignLang($body)
//	{
//		foreach (self::$dict as $lang => $reBody) {
//			if (stripos($body, $reBody['Flight']) !== false && stripos($body, $reBody['Departs']) !== false) {
//				$this->lang = $lang;
//				break;
//			}
//		}
//		return true;
//	}
}
