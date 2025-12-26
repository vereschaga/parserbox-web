<?php

namespace AwardWallet\Engine\solmelia\Email;

use AwardWallet\Engine\MonthTranslate;

class ConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "@MELIA.COM";
    public $reSubject = [
        "de"=> "Reservierungsbestätigung",
    ];
    public $reBody = 'Sol Melià';
    public $reBody2 = [
        "de"=> "RESERVIERUNGSBESTÄTIGUNG",
    ];

    public static $dictionary = [
        "de" => [],
    ];

    public $lang = "de";

    public function parsePdf(&$itineraries)
    {
        $text = $this->pdf->Response["body"];

        $it = [];
        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->re("#Bestätigungsnummer\s*:\s*(\w+)#", $text);

        // HotelName
        $it['HotelName'] = $this->re("#(.*?),#", $text);

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->re("#Anreise\s*:\s*(.+)#", $text));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->re("#Abreise\s*:\s*(.+)#", $text));

        // Address
        $it['Address'] = $this->re("#.*?,\s+(.+)#", $text);

        // Phone
        $it['Phone'] = $this->re("#Tel.\s+(.+)#", $text);

        // Fax
        $it['Fax'] = $this->re("#Fax.\s+(.+)#", $text);

        // GuestNames
        $it['GuestNames'] = [$this->re("#Gastname\s*:\s*(.+)#", $text)];

        // Rooms
        $it['Rooms'] = $this->re("#Zimmer\s*:\s*(\d+)#", $text);

        // RoomType
        $it['RoomType'] = $this->re("#Zimmer\s*:\s*\d+\s+(.*?)\s+\(#", $text);

        // Total
        $it['Total'] = $this->re("#Zimmerpreis\s*:\s*[A-Z]{3}\s+([\d\,\.]+)#", $text);

        // Currency
        $it['Currency'] = $this->re("#Zimmerpreis\s*:\s*([A-Z]{3})\s+[\d\,\.]+#", $text);

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
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
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
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
            "#^(\d+)([^\d\s]+)$#",
        ];
        $out = [
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetEmailBody($html);
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
        $this->pdf->SetEmailBody($res);

        return true;
    }
}
