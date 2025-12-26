<?php

namespace AwardWallet\Engine\hhonors\Email;

class It5994205 extends \TAccountChecker
{
    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";
    private $reFrom = "@hilton.com";
    private $reBody = 'Hilton';
    private $reBody2 = [
        "en"=> "Reservation",
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'],$headers['subject'])) {
            return false;
        }

        if (strpos($headers["from"], $this->reFrom) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('Manage Reservation - \d+.*.pdf');

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

        //		if(!$this->sortedPdf($parser)) return null;
        $pdfs = $parser->searchAttachmentByName('Manage Reservation - \d+.*\.pdf');

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

        $this->parsePdf($itineraries, $text);

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

    private function parsePdf(&$itineraries, $text)
    {
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->re("#Reservation Confirmation \# (\d+)#", $text);

        // TripNumber
        // ConfirmationNumbers
        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Arrival:[ ]*(.+)#", $text) . ', ' . ($this->re("#check[\­\-]in time is (\d+:\d+\s+[ap]m)#", $text))));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Departure:[ ]*(.+)#", $text) . ', ' . ($this->re("#check[\­\-]out is at (\d+:\d+\s+[ap]m)#", $text))));

        // Address
        $textColumns = $this->re("#\n([ ]*Hotel[ ]+Stay Information\n[\s\S]+?)Phone:#", $text);
        $pos = strpos($textColumns, "Stay Information");

        if (!empty($pos)) {
            $rows = explode("\n", $textColumns);
            $textHotel = '';

            foreach ($rows as $row) {
                $textHotel .= "\n" . trim(substr($row, 0, $pos));
            }
        }

        if (!empty($textHotel)) {
            $it['HotelName'] = $this->re("#Hotel\s*\n\s*(.+)#", $textHotel);
            $it['Address'] = str_replace("\n", ", ", trim($this->re("#Hotel\s*\n\s*\S[^\n]+\n(.*)#s", $textHotel)));
        }

        // DetailedAddress

        // Phone
        $it['Phone'] = $this->re("#Phone:\s(.+?)(\s{2,}|\n)#", $text);

        // Fax
        // GuestNames
        $it['GuestNames'] = array_filter((array) $this->re("#Guest name:[ ]*(.+)#", $text));

        // Guests
        $it['Guests'] = $this->re("#(\d+)\s+adult#", $text);

        // Kids
        // Rooms
        $it['Rooms'] = $this->re("#(\d+)\s+room#", $text);

        // Rate
        // RateType
        // CancellationPolicy
        // RoomType
        $it['RoomType'] = $this->re("#Guest name:[ ]*.+\n\s*(.+)#", $text);

        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        $it['Total'] = $this->amount($this->re("#Total for stay:\n\D+([\d\,\.]+)#", $text));

        // Currency
        $it['Currency'] = $this->currency($this->re("#Total for stay:\n(.+)#", $text));

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        // ReservationDate
        // NoItineraries
        $itineraries[] = $it;
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
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+(\d{4}),\s+(\d+:\d+\s+[ap]m)$#",
        ];
        $out = [
            "$1 $2 $3, $4",
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
