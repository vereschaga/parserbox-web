<?php

namespace AwardWallet\Engine\hhonors\Email;

use AwardWallet\Engine\MonthTranslate;

class CKPUHotelChangePdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "hilton.com";
    public $reSubject = [
        "en"=> "CKPU - Hotel Change",
    ];
    public $reBody = 'Hilton';
    public $reBody2 = [
        "en"=> "Your Room Information:",
    ];
    public $pdfPattern = "Conf #\d+ - \w \w+ [\d-]+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $pdfs = $this->parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($this->parser->getAttachmentBody($pdf));
            $table = $this->splitCols($this->re("#\n([^\n\S]*Your Room Information:.*?\n\n\n)#ms", $text));

            if (count($table) != 2) {
                $this->http->log("incorrect columns count table");

                return;
            }
            $table2 = $this->splitCols($this->re("#\n([^\n]+\n[^\n]+Confirmation Number:.*?)\n\n#ms", $text));

            if (count($table2) != 2) {
                $this->http->log("incorrect columns count table2");

                return;
            }
            $this->date = strtotime($this->normalizeDate($this->re("#(.*?)\s*–#", $table2[1])));

            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->re("#Confirmation Number:\s+(.+)#", $table2[1]);

            // TripNumber
            // ConfirmationNumbers

            // Hotel Name

            $it['HotelName'] = explode("\n", $table2[0])[0];

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Check In:\s+(.+)#", $table[0])));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Check Out:\s+(.+)#", $table[0])));

            // Address
            $it['Address'] = isset(explode("\n", $table2[0])[1]) ? explode("\n", $table2[0])[1] : null;

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->re("#T:\s+(.+)#", $table2[0]);

            // Fax
            // GuestNames
            $GuestNames = $this->http->FindNodes("//*[contains(text(), 'Guest')][contains(text(),'name')]/parent::*/following-sibling::*");

            if (count($GuestNames) > 0) {
                $it['GuestNames'] = array_unique($GuestNames);
            }

            // Guests
            $it['Guests'] = $this->re("#(\d+)\s+Adult#", $table[0]);

            // Kids
            // Rooms
            $it['Rooms'] = $this->re("#Rooms:\s+(\d+)#", $table[0]);

            // Rate
            $it['Rate'] = $this->re("#Rate per night :\s+(.+)#", $table[1]);

            // RateType

            // CancellationPolicy
            $it['CancellationPolicy'] = str_replace("\n", " ", $this->re("#(Free Cancellation:.+)#ms", $table[0]));

            // RoomType
            $it['RoomType'] = $this->re("#Your Room Information:\n(.*?),#", $table[0]);

            // RoomTypeDescription
            // Cost
            $it['Cost'] = $this->amount($this->re("#\nRate:\s+(.+)#", $table[1]));

            // Taxes
            $it['Taxes'] = $this->amount($this->re("#\Taxes:\s+(.+)#", $table[1]));

            // Total
            $it['Total'] = $this->amount($this->re("#\Total:\s+(.+)#", $table[1]));

            // Currency
            $it['Currency'] = $this->currency($this->re("#\Total:\s+(.+)#", $table[1]));

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
        if (!isset($headers['from'],$headers['subject'])) {
            return false;
        }

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
        $this->parser = $parser;
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
            "#^([^\s\d]+)\.\s+(\d+),\s+(\d{4})$#", //Aug. 24, 2017
            "#^([^\s\d]+)\s+(\d+)\s+(\d+:\d+\s+[AP]M)$#", //Aug 24 4:00 PM
        ];
        $out = [
            "$2 $1 $3",
            "$2 $1 $year, $3",
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

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
