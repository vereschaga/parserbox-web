<?php

namespace AwardWallet\Engine\nordic\Email;

use AwardWallet\Engine\MonthTranslate;

class ConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $reSubject = [
        "en"=> "Confirmation",
    ];
    public $reBody = 'choice.no';
    public $reBody2 = [
        "no"=> "Reservasjonsnummer",
    ];
    public $pdfPattern = "Res Confirmation - \d+-\d+.pdf";

    public static $dictionary = [
        "no" => [],
    ];

    public $lang = "no";
    private $text;

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->re("#Reservasjonsnummer:\s+(\w+)#", $text);

        // TripNumber
        // ConfirmationNumbers

        // HotelName
        $it['HotelName'] = $this->re("#Med vennlig hilsen\n([^\n]+)#", $text);

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Ankomstdato:\s+(.+)#", $text)));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Avreisedato:\s+(.+)#", $text)));

        // Address
        $it['Address'] = trim(preg_replace("#\s+#", " ", $this->re("#\n\n\s*{$it['HotelName']}\n(.*?)Phone:#ms", $text)));

        // DetailedAddress

        // Phone
        $it['Phone'] = $this->re("#Phone:\s+(.+)#", $text);

        // Fax
        $it['Fax'] = $this->re("#Fax:\s+(.+)#", $text);

        // GuestNames
        $it['GuestNames'] = array_unique([$this->re("#Gjestens navn:\s+(.+)#", $text)]);

        // Guests
        $it['Guests'] = $this->re("#Personer/rom:\s+(\d+)#", $text);

        // Kids
        // Rooms
        $it['Rooms'] = $this->re("#Antall rom:\s+(.+)#", $text);

        // Rate
        // RateType
        // CancellationPolicy
        // RoomType
        $it['RoomType'] = $this->re("#Romtype:\s+(.+)#", $text);

        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        $it['Total'] = $this->re("#Total pris:\s+([\d\.\,]+)#", $text);

        // Currency
        $it['Currency'] = $this->re("#Total pris:\s+[\d\.\,]+\s+([A-Z]{3})#", $text);

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        // ReservationDate
        // NoItineraries
        $itineraries[] = $it;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers["from"], '@choice.no') === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (isset($headers["subject"]) && stripos($headers["subject"], $re) !== false) {
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
            "#^(\d+)\.(\d+).(\d{2})$#", //11.06.17
        ];
        $out = [
            "$1.$2.20$3",
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
