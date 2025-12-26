<?php

namespace AwardWallet\Engine\rydges\Email;

use AwardWallet\Engine\MonthTranslate;

class HotelReservationPdf extends \TAccountChecker
{
    public $mailFiles = "rydges/it-1.eml, rydges/it-6805042.eml";
    public $reFrom = "reservations_rydgessydneyairport@evt.com";
    public $reSubject = [
        "en"=> "Hotel Reservation Booking",
    ];
    public $reBody = 'Rydges';
    public $reBody2 = [
        "en"=> "Your Hotel:",
    ];
    public $pdfPattern = "\w+_confirmation\d+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $hoteltable = $this->SplitCols(substr($text, $s = strpos($text, "Your Hotel:"), strpos($text, "About Your Booking:") - $s));
        // print_r($hoteltable);
        // die();
        if (count($hoteltable) < 2) {
            $this->http->log("incorrect hoteltable parse");

            return;
        }
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->re("#Your Booking Number:\s+(\w+)#", $text);

        // TripNumber
        // ConfirmationNumbers

        // HotelName
        $it['HotelName'] = $this->re("#Your Hotel:\n([^\n]+)#", $hoteltable[0]);

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Check-in:\s+(.*?)\s{2,}#", $text) . ', ' . $this->re("#Check-in Time:\s+(.*?)\s{2,}#", $text)));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Check-out:\s+(.+)#", $text) . ', ' . $this->re("#Check-out Time:\s+(.+)#", $text)));

        // Address
        $it['Address'] = str_replace("\n", " ", $this->re("#Your Hotel:\n[^\n]+\n(.*?)\nPhone:#ms", $hoteltable[0]));

        // DetailedAddress

        // Phone
        $it['Phone'] = $this->re("#Phone:\s*(.+)#", $hoteltable[0]);

        // Fax
        // GuestNames
        $it['GuestNames'] = [$this->re("#Guest Name:\s+(.*?)(?:\s{2,}|\n)#", $text)];

        // Guests
        $it['Guests'] = $this->re("#Adults/Children:\s+(\d+)/\d+#", $text);

        // Kids
        $it['Kids'] = $this->re("#Adults/Children:\s+\d+/(\d+)#", $text);

        // Rooms
        // Rate
        $it['Rate'] = $this->re("#Daily Rate in [A-Z]{3}:\s+(.*?)(?:\s{2,}|\n)#", $text);

        // RateType
        $it['RateType'] = $this->re("#Rate Booked:\s+(.*?)(?:\s{2,}|\n)#", $text);

        // CancellationPolicy
        $it['CancellationPolicy'] = $this->re("#\n\s*Cancellations:\s*\n([\s\S]+?)\n\n#", $text);

        // RoomType
        $it['RoomType'] = $this->re("#Room Type:\s+(.*?)(?:\s{2,}|\n)#", $text);

        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        $it['Total'] = $this->re("#TOTAL\*:\s+([\d\,\.]+)\s+[A-Z]{3}#", $text);

        // Currency
        $it['Currency'] = $this->re("#TOTAL\*:\s+[\d\,\.]+\s+([A-Z]{3})#", $text);

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        $account = $this->re("#PriorityGUEST Rewards Number:[ ]*([A-Z\d]+)\s*\n#", $text);

        if (!empty($account)) {
            $it["AccountNumbers"][] = $account;
        }

        // Status
        // Cancelled
        // ReservationDate
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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($itineraries);

        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
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
            "#^(\d+)-([^\d\s]+)-(\d{4}),\s+(?:After|Before)\s+(\d+:\d+\s+[ap]m)$#", // 04-JUN-2017, After 02:00 pm
        ];
        $out = [
            "$1 $2 $3, $4",
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
            foreach ($pos as $k=>$p) {
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
}
