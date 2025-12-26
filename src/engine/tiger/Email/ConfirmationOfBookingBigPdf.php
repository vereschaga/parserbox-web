<?php

namespace AwardWallet\Engine\tiger\Email;

class ConfirmationOfBookingBigPdf extends \TAccountChecker
{
    public $mailFiles = "tiger/it-1705075.eml, tiger/it-6140284.eml, tiger/it-6188199.eml";

    public $reFrom = "itinerary@tigerair.com";
    public $reSubject = [
        "en" => "Tigerair - Confirmation of booking",
    ];
    public $reBody = 'Tigerair';
    public $reBody2 = [
        "en" => "Depart",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "zh";

    /** @var \HttpBrowser */
    private $pdf;

    public function parsePdf(&$itineraries)
    {
        $text = $this->pdf->Response["body"];
        //		$this->logger->info($text);
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#your flight confirmation\n(\w+)\n#", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = array_unique(preg_match_all("#\n\d+\)\s+(.+)\n#", $text, $m) ? $m[1] : []);

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->re("#Total Price\n[A-Z]{3}\n([\d\,\.]+)\n#", $text));

        // BaseFare
        $it['BaseFare'] = $this->amount($this->re("#\n([\d\,\.]+)\nFare#", $text));

        // Currency
        $it['Currency'] = $this->re("#Total Price\n([A-Z]{3})\n#", $text);

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $re = "#\n(?<AirlineName>\w{2})\s+(?<FlightNumber>\d+)\n" .
            "(?<Date>.*?)\n" .
            ".*?\s+\(\s+(?<DepCode>[A-Z]{3})\s+\)\s+" .
            "(?<DepTime>\d+:\d+\s+[AP]M)\n" .
            "Depart\n" .
            "(?<DepName>.*?)(?:\s+-\s+(?<DepartureTerminal>.+))?\n" .
            ".*?\s+\(\s+(?<ArrCode>[A-Z]{3})\s+\)\s+" .
            "(?<ArrTime>\d+:\d+\s+[AP]M)\n" .
            "Arrive\n" .
            "Check-in:\n" .
            ".*?\n" .
            "(?<ArrName>.*?)(?:\s+-\s+(?<ArrivalTerminal>.+))?\s+" .
            "#";
        preg_match_all($re, $text, $segments, PREG_SET_ORDER);
        //		$this->logger->info($re);

        $re2 = "#\n(?<AirlineName>\w{2})\s+(?<FlightNumber>\d+)\n" .
            "(?<Date>.*?)\n" .
            ".*?\s+\(\s+(?<DepCode>[A-Z]{3})\s+\)\n" .
            "(?<DepTime>\d+:\d+\s+[AP]M)\n" .
            "Depart\n" .
            "(?<DepName>.*?)(?:\s+-\s+(?<DepartureTerminal>.+))?\n" .
            ".*?\n" .
            "(?<ArrTime>\d+:\d+\s+[AP]M)\n" .
            "Arrive\n" .
            "\(\s+(?<ArrCode>[A-Z]{3})\s+\)\n" .
            "Check-in:\n" .
            ".*?\n" .
            "(?<ArrName>.*?)(?:\s+-\s+(?<ArrivalTerminal>.+))?\n" .
            "#";
        preg_match_all($re2, $text, $s, PREG_SET_ORDER);
        //		$this->logger->info($re2);
        $segments = array_merge($segments, $s);

        $re3 = "#\n(?<AirlineName>\w{2})\s+(?<FlightNumber>\d+)\n" .
            "(?<Date>.*?)\n" .
            ".*?\n" .
            "(?<DepTime>\d+:\d+\s+[AP]M)\n" .
            "Depart\n" .
            "\(\s+(?<DepCode>[A-Z]{3})\s+\)\n" .
            "(?<DepName>.*?)(?:\s+-\s+(?<DepartureTerminal>.+))?\n" .
            ".*?\s+\(\s+(?<ArrCode>[A-Z]{3})\s+\)\n" .
            "(?<ArrTime>\d+:\d+\s+[AP]M)\n" .
            "Arrive\n" .
            "Check-in:\n" .
            ".*?\n" .
            "(?<ArrName>.*?)(?:\s+-\s+(?<ArrivalTerminal>.+))?\n" .
            "#";
        preg_match_all($re3, $text, $s, PREG_SET_ORDER);
        //		$this->logger->info($re3);
        $segments = array_merge($segments, $s);

        if (count($segments) != substr_count($text, "\nDepart\n")) {
            return [];
        }

        $seats = [];

        if (stripos($text, 'Prepaid Baggage') !== false) {
            preg_match_all('/\d+\)\s+.+\n([A-Z\d]{1,3})\nPrepaid Baggage/', $text, $m);

            if (!empty($m[1])) {
                $seats[] = $m[1];
            }
            preg_match_all('/\|\n([A-Z\d]{1,3})\nPrepaid Baggage/', $text, $math);

            if (!empty($math[1])) {
                $seats[] = $math[1];
            }
        }

        foreach ($segments as $i => $segment) {
            $date = strtotime($this->normalizeDate($segment["Date"]));

            $keys = [
                "AirlineName",
                "FlightNumber",
                "DepCode",
                "DepName",
                "DepartureTerminal",
                "ArrCode",
                "ArrName",
                "ArrivalTerminal",
            ];
            $itsegment = [];

            foreach ($keys as $key) {
                if (isset($segment[$key])) {
                    $itsegment[$key] = $segment[$key];
                }
            }

            $itsegment['DepDate'] = strtotime($segment['DepTime'], $date);
            $itsegment['ArrDate'] = strtotime($segment['ArrTime'], $date);

            if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
            }

            if (isset($seats[$i])) {
                $itsegment['Seats'] = $seats[$i];
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
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('[A-Z\d]+\.pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (stripos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($text, $re) !== false) {
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
            if (stripos($this->pdf->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($itineraries);

        $result = [
            'emailType'  => 'ConfirmationOfBookingBigPdf' . ucfirst($this->lang),
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
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#", //週四, 25 八月 2016
        ];
        $out = [
            "$1 $2 $3",
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
        $this->pdf->SetBody($res);

        return true;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
