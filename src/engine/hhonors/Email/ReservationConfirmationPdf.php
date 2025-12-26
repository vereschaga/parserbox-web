<?php

namespace AwardWallet\Engine\hhonors\Email;

use AwardWallet\Engine\MonthTranslate;

class ReservationConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "hhonors/it-6865924.eml"; // +1 bcdtravel(pdf)[en]

    public $reFrom = "hilton.com";
    public $reSubject = [
        "en"=> "Hilton reservation",
    ];
    public $reBody = ['hilton.com', 'House Hilton'];
    public $langDetectorsPdf = [
        "en"=> ["Reservation Confirmation"],
    ];
    public $pdfPattern = '.*pdf';

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = '';

    public function parsePdf(&$itineraries, $text)
    {
        $patterns = [
            'time'  => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?', // 4:19PM
            'phone' => '[+)(\d][-\s\d)(]{5,}[\d)(]', // +377 (93) 15 48 52
        ];

        $table = $this->splitCols($this->re("#\n(\s*(?:Hotel|Hilton Honors Rewards).*?)(?:Manage Reservation|Page \d{1,3} of \d{1,3}|Driving directions\n)#s", $text));

        $it = [];
        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->re("#Reservation Confirmation (?:\#|-)[ ]+(\d{5,})\b#", $text);

        // HotelName
        $it['HotelName'] = $this->re("#Hotel(?:\n|\n\n)(.+)#", $table[0]);

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Arrival:\s+(.+)#", $table[1])));
        $checkInTime = $this->re('/Hotel\s+check-in\s+time\s+is\s*(' . $patterns['time'] . ')/', $table[1]);

        if (!empty($it['CheckInDate']) && $checkInTime) {
            $it['CheckInDate'] = strtotime(preg_replace('/\s+/', ' ', $checkInTime), $it['CheckInDate']);
        }

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Departure:\s+(.+)#", $table[1])));
        $checkOutTime = $this->re('/and\s+check-out\s+is\s+at\s*(' . $patterns['time'] . ')/', $table[1]);

        if (!empty($it['CheckOutDate']) && $checkOutTime) {
            $it['CheckOutDate'] = strtotime(preg_replace('/\s+/', ' ', $checkOutTime), $it['CheckOutDate']);
        }

        // Address
        $it['Address'] = str_replace("\n", ", ", $this->re("#Hotel(?:\n|\n\n)[^\n]+\n(.*?)\nPhone:#ms", $table[0]));

        // Phone
        $it['Phone'] = $this->re("#Phone:\s+({$patterns['phone']})#", $table[0]);

        // GuestNames
        $it['GuestNames'] = array_filter([$this->re("#Guest name:\s+(.+)#", $table[1])]);

        // AccountNumbers
        $hiltonHonorsNumber = $this->re('/Hilton Honors #:[ ]*(\d{5,})$/m', $table[1]);

        if ($hiltonHonorsNumber) {
            $it['AccountNumbers'] = [$hiltonHonorsNumber];
        }

        // Guests
        $it['Guests'] = $this->re("#\b(\d{1,3}) adults?\b#i", $table[1]);

        // Kids
        $children = $this->re("#\b(\d{1,3}) children\b#i", $table[1]);

        if ($children) {
            $it['Kids'] = $children;
        }

        // Rooms
        $it['Rooms'] = $this->re('/\b(\d{1,3}) rooms?\b/i', $table[1]);

        // RoomType
        if (preg_match_all('/DETAILS.*\s+(.+)/', $table[0], $roomMatches)) {
            $it['RoomType'] = implode('; ', $roomMatches[1]);
        }

        // Total
        $total = $this->re("#Total for stay:\s+[^\d\s]+(\d[,.\d]*)#", $table[0]);

        if ($total !== null) {
            $it['Total'] = $this->normalizeAmount($total);
        }
        $awards = $this->re("#Total for stay:\s+.*?(\d[,.\d]*\s*points)#s", $table[0]);

        if ($awards !== null) {
            $it['SpentAwards'] = $awards;
        }

        // Currency
        $it['Currency'] = $this->re("#\(([A-Z]{3})\)#", $table[0]);

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'],$headers['subject'])) {
            return false;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true) {
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

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->arrikey($textPdf, $this->reBody) === false) {
                continue;
            }

            if ($this->assignLangPdf($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $textPdfFull = '';

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLangPdf($textPdf)) {
                $textPdfFull .= $textPdf;
            }
        }

        if (!$textPdfFull) {
            return false;
        }

        $itineraries = [];
        $this->parsePdf($itineraries, $textPdfFull);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
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

    private function assignLangPdf($text = ''): bool
    {
        foreach ($this->langDetectorsPdf as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)\s+([^\d\s]+)\s+(\d{4}),\s+(\d+:\d+)$#", //08 June 2017, 09:45
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

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $string): string
    {
        $string = preg_replace('/\s+/', '', $string);             // 11 507.00  ->  11507.00
        $string = preg_replace('/[,.\'](\d{3})/', '$1', $string); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);    // 18800,00   ->  18800.00

        return $string;
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

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $key;
            }
        }

        return false;
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
            $pos = $this->TableHeadPos(array_values(array_filter($rows))[0]);
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
}
