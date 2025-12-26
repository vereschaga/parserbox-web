<?php

namespace AwardWallet\Engine\hoggrob\Email;

class It6128685 extends \TAccountChecker
{
    public $mailFiles = "hoggrob/it-10014938.eml, hoggrob/it-10014946.eml, hoggrob/it-6128685.eml";

    public $reFrom = "HRGWORLDWIDE.COM";
    public $reSubject = [
        "en"=> " E_TICKET_CONFIRMATION",
    ];
    public $reBody = 'HRG';
    public $reBody2 = [
        "en"=> "Information for Trip Locator:",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    /** @var \HttpBrowser */
    private $pdf;

    public function parsePdf(&$itineraries)
    {
        $text = $this->pdf->Response["body"];
        // echo $text;
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#Trip Locator:\s+(\w+)#", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter([$this->re("#Passengers\n(.+)#", $text)]);

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

        //		$this->logger->info($text);
        $re = "#AIR\s+-\s+(?<Date>.+)\n(?<AirlineName>\w{2})\s+(?<FlightNumber>\d+)\n(?<Cabin>\w+)\n\nFrom:\nEquipment:\n(?<Aircraft>.+)\n(?<DepName>.+)\nDuration:\n(?<Duration>.+)\n(?<DepDate>.+)\n(?<DepartureTerminal>.+)\nFOOD AND BEVERAGES\nFOR PURCHASE\n\nMeals:\nTo:\nStatus:\n(?<Status>.+)\n(?<ArrName>.+)\n(?<ArrDate>.+)\n(?<ArrivalTerminal>.+)\n\nSeats:\n(?<Seats>.+)\n#";

        $re2 = '/AIR\s+[-−]*\s+(?<Date>.+)\n(?<AirlineName>.+)\s+Flight\s+(?<FlightNumber>\d+)[\n\s]*(?<Cabin>\w+)[\n]{1,}From:\n(?<DepName>.+)\nEquipment:\n(?<Aircraft>.+)\n(?<DepDate>.+)\n(?<DepartureTerminal>.+)\nDuration:\n(?<Duration>.+)\nMeals:\n(?<Meal>.+)\nTo:\n(?<ArrName>.+)\nStatus:\n(?<Status>.+)\n(?<ArrDate>.+)\n(?<ArrivalTerminal>.+)/';

        $re3 = '/AIR\s+[-−]*\s+(?<Date>.+)\n(?<AirlineName>.+)\s+Flight\s+(?<FlightNumber>\d+)[\n\s]*(?<Cabin>\w+)[\n]{1,}From:\n(?<DepName>.+)\nEquipment:\n(?<Aircraft>.+)\n(?<DepDate>.+)\n(?<DepartureTerminal>.+)\nDuration:\n(?<Duration>.+)\nStatus:\n(?<Status>.+)\nTo:\n(?<ArrName>.+)\n(?<ArrDate>.+)\n(?<ArrivalTerminal>.+)/';

        preg_match_all($re, $text, $segments, PREG_SET_ORDER) || preg_match_all($re2, $text, $segments, PREG_SET_ORDER) || preg_match_all($re3, $text, $segments, PREG_SET_ORDER);

        foreach ($segments as $segment) {
            $it['Status'] = $segment['Status'];

            $date = strtotime($this->normalizeDate($segment["Date"]));

            $keys = [
                "AirlineName",
                "FlightNumber",
                "DepName",
                "ArrName",
                "DepartureTerminal",
                "ArrivalTerminal",
                "Cabin",
                "Aircraft",
                "Duration",
                "Seats",
            ];
            $itsegment = [];

            foreach ($keys as $key) {
                if (isset($segment[$key]) && !empty($segment[$key])) {
                    $itsegment[$key] = $segment[$key];
                }

                if (stripos($key, 'Terminal') !== false) {
                    $itsegment[$key] = trim(str_ireplace(['Unspecified', 'Terminal'], ['', ''], $itsegment[$key]));
                }
            }

            if (!empty($itsegment['FlightNumber']) && !empty($itsegment['DepName']) && !empty($itsegment['ArrName'])) {
                $itsegment['DepCode'] = $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $itsegment['DepDate'] = strtotime($this->normalizeDate($segment['DepDate']));
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($segment['ArrDate']));

            if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
            }

            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (isset($headers['subject']) && strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        if (!$this->sortedPdf($parser)) {
            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->pdf->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($itineraries);

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
            "#^(\d{1,2})(\d{2})\s+hrs\s*,\s+[^\d\s]+,\s+([^\d\s]+)\s+(\d+)$#", //1855 hrs , Friday, December 02
        ];
        $out = [
            "$4 $3 $year, $1:$2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function sortedPdf(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetBody($html);
        $res = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $cols = [];
            $grid = [];

            foreach ($nodes as $node) {
                $text = implode("\n", $this->pdf->FindNodes("./descendant::text()[normalize-space(.)]", $node));
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $grid[$top][$left] = $text;
            }

            foreach ($grid as $row=>&$c) {
                ksort($c);
                $r = "";

                foreach ($c as $t) {
                    $r .= $t . "\n";
                }
                $c = $r;
            }

            ksort($grid);

            foreach ($grid as $r) {
                $res .= $r;
            }
        }
        $this->pdf->setBody($res);

        return true;
    }
}
