<?php

namespace AwardWallet\Engine\tiger\Email;

class ConfirmationOfBookingPdf extends \TAccountChecker
{
    public $mailFiles = "tiger/it-3943785.eml, tiger/it-3943834.eml, tiger/it-3951251.eml, tiger/it-4030416.eml, tiger/it-4030418.eml, tiger/it-6163940.eml, tiger/it-6812590.eml, tiger/it-8387278.eml";

    public $reFrom = "itinerary@tigerair.com";
    public $reSubject = [
        "en"  => "Tigerair - Confirmation of booking",
        "zh"  => "台灣虎航 - 機票確認單",
        "zh2" => "虎航訂位",
    ];
    public $reBody = 'Tigerair';
    public $reBody2 = [
        "zh" => "機場服務費",
        "en" => "This is not a boarding pass",
    ];

    public static $dictionary = [
        "zh" => [
            "訂位代碼" => ["訂位代碼", "booking reference"],
            "出 發"  => ["出 發", "Depart"],
        ],
        "en" => [
            "訂位代碼" => "booking reference",
            "出 發"  => "Depart",
        ],
    ];

    public $lang = "zh";

    public function parsePdf(&$itineraries)
    {
        $text = $this->pdf->Response["body"];
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#\n(\w+)\n" . $this->opt($this->t("訂位代碼")) . "#", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = array_unique(preg_match_all("#\n(.*?)\nServices:#", $text, $m) ? $m[1] : []);

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->re("#Total Paid\n[A-Z]{3}\n([\d\,\.]+)\n#", $text));

        // BaseFare
        $it['BaseFare'] = $this->amount($this->re("#Fare\n([\d\,\.]+)\n#", $text));

        // Currency
        $it['Currency'] = $this->re("#Total Paid\n([A-Z]{3})\n#", $text);

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        preg_match_all("#\n(?<Date>.*?)\n" .
            ".*?\s+\((?<DepCode>[A-Z]{3})\)\n" .
            "(?<AirlineName>\w{2})(?<FlightNumber>\d+)\n" .
            "(?<DepTime>\d+:\d+)\n" .
            $this->opt($this->t("出 發")) . "\n" .
            ".*?\n" .
            ".*?\s+\((?<ArrCode>[A-Z]{3})\)\n" .
            "(?<ArrTime>\d+:\d+)\n" .
            "#", $text, $segments, PREG_SET_ORDER);
        preg_match_all("#\n(?<AirlineName>\w{2})(?<FlightNumber>\d+)\n" .
                "(?<Date>.*?)\n" .
                ".*?\s+\((?<DepCode>[A-Z]{3})\)\n" .
                "(?<DepTime>\d+:\d+)\n" .
                $this->opt($this->t("出 發")) . "\n" .
                ".*?\n" .
                ".*?\s+\((?<ArrCode>[A-Z]{3})\)\n" .
                "(?<ArrTime>\d+:\d+)\n" .
                "#", $text, $s, PREG_SET_ORDER);

        $segments = array_merge($segments, $s);

        $check = false;

        foreach ((array) $this->t("出 發") as $re) {
            if (count($segments) == substr_count($text, "\n" . $re . "\n")) {
                $check = true;
            }
        }

        if (!$check) {
            return;
        }

        foreach ($segments as $segment) {
            $date = strtotime($this->normalizeDate($segment["Date"]));

            $keys = [
                "AirlineName",
                "FlightNumber",
                "DepCode",
                "ArrCode",
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
        $pdfs = $parser->searchAttachmentByName('.+\.pdf.*');

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

        $a = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($a) . ucfirst($this->lang),
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

    private function sortedPdf($parser)
    {
        $pdfs = $parser->searchAttachmentByName('.+\.pdf.*');

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
