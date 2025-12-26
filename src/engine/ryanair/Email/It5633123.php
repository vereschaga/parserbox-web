<?php

namespace AwardWallet\Engine\ryanair\Email;

class It5633123 extends \TAccountChecker
{
    public $mailFiles = "ryanair/it-5633123.eml, ryanair/it-5636099.eml";
    public $reBody = 'Ryanair.com';
    public $reBody2 = [
        "en"=> "FLIGHT(S) SUMMARY",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $airs = [];
        $total = [];
        $passengers = [];
        $pdfs = $this->parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $text = $this->textPdf($pdf);
            preg_match_all("#\n\s*(?<DepName>.*?)\s+\((?<DepCode>[A-Z]{3})\)\s+to\s+(?<ArrName>.*?)\s+\((?<ArrCode>[A-Z]{3})\)\s*\n\s*" .
                    "(?<Date>\d+\s+[^\d\s]+\s+\d{4})\s+-\s+(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s*\n\s*" .
                    "›\s*\n\s*" .
                    "[A-Z]{3}\s*\n\s*" .
                    "[A-Z]{3}\s*\n\s*" .
                    "(?<DepTime>\d+:\d+)\s+hrs\s*\n\s*" .
                    "(?<ArrTime>\d+:\d+)\s+hrs\s*\n\s*#", $text, $segments, PREG_SET_ORDER);

            if (!($rl = $this->re("#RESERVATION NUMBER\s*\n\s*(\w+)\s*\n\s*#", $text))) {
                return;
            }

            foreach ($segments as $segment) {
                $airs[$rl][] = $segment;
            }

            $total[$rl][] = $this->re("#Total paid\s*\n\s*([\d\.\,]+\s+[A-Z]{3})#", $text);
            $passengers[$rl] = explode("\n", $this->re("#PASSENGER\(S\)\s*\n\s*Add bag\s*\n\s*(.*?)\s*\n\s*Passengers can add up#ms", $text));
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = $passengers[$rl];

            // AccountNumbers
            // Cancelled
            if (count($total[$rl]) == 1) {
                // TotalCharge
                $it['TotalCharge'] = $this->amount($this->re("#^([\d\.\,]+)#", $total[$rl][0]));

                // Currency
                $it['Currency'] = $this->re("#([A-Z]{3})$#", $total[$rl][0]);
            }
            // BaseFare
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory

            foreach ($segments as $segment) {
                $date = strtotime($this->normalizeDate($segment['Date']));

                $keys = [
                    'DepName',
                    'DepCode',
                    'ArrName',
                    'ArrCode',
                    'AirlineName',
                    'FlightNumber',
                ];

                $itsegment = [];

                foreach ($keys as $key) {
                    $itsegment[$key] = $segment[$key];
                }

                $itsegment['DepDate'] = strtotime($segment['DepTime'], $date);
                $itsegment['ArrDate'] = strtotime($segment['ArrTime'], $date);

                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }
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
        $this->parser = $parser;
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($itineraries);

        $result = [
            'emailType'  => 'reservations',
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
            "#^(\d+\s+[^\d\s]+\s+\d{4})$#",
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

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

    private function textPdf($pdf)
    {
        if (($html = \PDF::convertToHtml($this->parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetEmailBody($html);
        $outtext = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $cols = [];
            $grid = [];

            foreach ($nodes as $node) {
                $text = $this->pdf->FindSingleNode(".", $node);
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $grid[$top][$left] = $text;
            }

            foreach ($grid as $row=>&$c) {
                ksort($c);
                $a = [];

                foreach ($c as $t) {
                    $a[] = $t;
                }
                $c = implode("\n", $a);
            }

            ksort($grid);

            foreach ($grid as $t) {
                $outtext .= "\n" . $t;
            }
        }

        return $outtext;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }
}
