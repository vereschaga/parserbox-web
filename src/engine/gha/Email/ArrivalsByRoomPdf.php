<?php

namespace AwardWallet\Engine\gha\Email;

use AwardWallet\Engine\MonthTranslate;

class ArrivalsByRoomPdf extends \TAccountChecker
{
    public $mailFiles = "gha/it-7971799.eml, gha/it-7971838.eml";

    public $reFrom = "@kempinski.com";
    public $reSubject = [
        "en"=> "No Posting",
    ];
    public $reBody = 'Kempinski';
    public $reBody2 = [
        "en"=> "Arrivals By Room",
    ];
    public $pdfPattern = "Arrivals By Room.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        // echo $this->re("#Arrival Date[^\n]+(.*?)Arrival Date Total#ms", $text);
        // die();
        $parts = $this->split("#\n(\d+)#", $this->re("#Arrival Date[^\n]+(.*?)Arrival Date Total#ms", $text));
        $pos = $this->TableHeadPos(explode("\n", $parts[0])[0]);

        foreach ($parts as $stext) {
            $table = $this->splitCols($stext, $pos);

            if (count($table) < 10) {
                $this->http->log("incorrect columns count");

                return;
            }
            // print_r($table);
            // die();
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->re("#\n(\d+)#", $table[1]);

            // TripNumber
            // ConfirmationNumbers

            // Hotel Name
            $it['HotelName'] = $this->re("#(.*?)\s{2,}#", $text);

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#^(\d+-\d+-\d+)#", $table[3])));

            if ($time = $this->re("#(\d+:\d+)#", $table[3])) {
                $it['CheckInDate'] = strtotime($time, $it['CheckInDate']);
            }

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#\s+(\d+-\d+-\d+)#", $table[3])));

            // Address
            $it['Address'] = $it['HotelName'];

            // DetailedAddress

            // Phone
            // Fax
            // GuestNames
            $it['GuestNames'] = $this->re("#(.*?)\n#", $table[1]);

            // Guests
            $it['Guests'] = $this->re("#(\d+)#", $table[4]);

            // Kids
            $it['Kids'] = $this->re("#(\d+)#", $table[5]);

            // Rooms
            // Rate
            // RateType
            // CancellationPolicy
            // RoomType
            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            // Currency
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // Cancelled
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }
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
            "#^(\d+)-(\d+)-(\d{2})$#", //17-09-16
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,\s](\d{3})#", "$1", $s));
    }
}
