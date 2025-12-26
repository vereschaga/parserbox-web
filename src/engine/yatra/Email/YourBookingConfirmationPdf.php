<?php

namespace AwardWallet\Engine\yatra\Email;

use AwardWallet\Engine\MonthTranslate;

class YourBookingConfirmationPdf extends \TAccountChecker
{
    public $reFrom = "corporatetravel@yatra.com";
    public $reSubject = [
        "en" => "Your Booking Confirmation Mail for Cart Id",
    ];
    public $reBody = 'yatra.com';
    public $reBody2 = [
        "en" => "HOTEL DETAILS",
    ];
    public $pdfPattern = "HOTELVOUCHER\(\d+\)\.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->re("#Hotel Conf. No.\s+(.*?)\s{2,}#", $text);

        // TripNumber
        $it['TripNumber'] = str_replace(" - ", "", $this->re("#Booking Ref\s+(.+)#", $text));

        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = $this->re("#Hotel Name\s+(.+)#", $text);

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Check-in\s+(.*?)\s{2,}#", $text)));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Check-out\s+(.+)#", $text)));

        // Address
        $address = implode(" ", array_map('trim', explode("\n", trim($this->re("#Hotel Address\s+(.*?)Hotel Conf. No.#ms", $text)))));
        $it['Address'] = $this->re("#(.*?)(?:\s+-\s+|Hotel Contact No.|Care\s*Taker\s*-|$)#", $address);

        // DetailedAddress

        // Phone
        $it['Phone'] = trim($this->re("#(?:Ph\s+\#1:|Hotel Contact No.|Mobile no)\s+([\d\s\-\+]{5,})#", $address));

        // Fax
        // GuestNames
        $it['GuestNames'] = [$this->re("#Name -\s+(.+?)\s*(?:\(.+\)|\n)#", $text)];

        // Guests
        $it['Guests'] = $this->re("#Adult\s*:\s*(\d+)#", $text);

        // Kids
        $it['Kids'] = $this->re("#Child\s*:\s*(\d+)#", $text);

        // Rooms
        // Rate
        // RateType

        // CancellationPolicy
        $it['CancellationPolicy'] = trim($this->re("#HOTEL CANCELLATION AND AMENDMENT POLICY(.*?)space#ms", $text));

        // RoomType
        if (trim($this->re("#Room Type\s+(.*?)\s{2,}#", $text)) != trim($this->re("#Room Category\s+(.+)#", $text))) {
            $it['RoomType'] = $this->re("#Room Type\s+(.*?)\s{2,}#", $text) . ", " . $this->re("#Room Category\s+(.+)#", $text);
        } else {
            $it['RoomType'] = $this->re("#Room Type\s+(.*?)\s{2,}#", $text);
        }

        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        $it['Total'] = $this->amount($this->re("#NET AMOUNT\s+(.+)#", $text));

        // Currency
        $it['Currency'] = $this->currency($this->re("#NET AMOUNT\s+(.+)#", $text));

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        $it['Status'] = $this->re("#Status\s+(.+)#", $text);

        // Cancelled
        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->re("#Booking Date\s+(.+)#", $text)));

        // NoItineraries
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
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

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

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->text, $re) !== false) {
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
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+\s+[^\d\s]+\s+\d{4})\s+:\s+(\d+:\d+)$#", //20 JUL 2017 : 12:00
        ];
        $out = [
            "$1, $2",
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
