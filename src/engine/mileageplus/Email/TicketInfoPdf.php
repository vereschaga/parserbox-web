<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketInfoPdf extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-13335892.eml, mileageplus/it-13335894.eml, mileageplus/it-13335899.eml, mileageplus/it-13335906.eml, mileageplus/it-13711379.eml, mileageplus/it-28437139.eml";

    public static $dictionary = [
        'en' => [],
    ];

    public $lang = 'en';

    private $reFrom = 'united';
    private $reSubject = [
        'en' => 'Ticket and Invoice',
    ];
    private $reBody = 'MileagePlus';
    private $reBody2 = [
        'en' => 'FLIGHT INFORMATION',
    ];
    private $pdfPattern = '.*\.pdf';
    private $date = null;
    private $textPdf = '';
    private $textPdf2 = '';

    //detects
    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && strpos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (isset($headers['subject']) && stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $text = '';

        foreach ($pdfs as $pdf) {
            if (($text1 = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }
            $text .= $text1;
        }
        // TODO: $pdfs[0]    ->    foreach ($pdfs as $pdf) { $pdf; }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->textPdf = $this->textPdf2 = null;
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }

            if (strpos($textPdf, 'FLIGHT INFORMATION') !== false) {
                if (isset($this->textPdf)) {
                    $this->logger->debug('need to check parsing. this parser cannot parse more than one attach of this format (1)');

                    return null;
                }
                $this->textPdf = $textPdf;

                continue;
            }

            $textPdf2 = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (
                strpos($textPdf2, 'Departing At:') !== false
                && strpos($textPdf2, 'Arriving At:') !== false
                && (strpos($textPdf2, 'Aircraft:') !== false || strpos($textPdf2, 'Duration:') !== false)
            ) {
                if (isset($this->textPdf2)) {
                    $this->logger->debug('need to check parsing. this parser cannot parse more than one attach of this format (2)');

                    return null;
                }
                $this->textPdf2 = $textPdf;

                continue;
            }
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->textPdf, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $email->setProviderCode('mileageplus');
        $email->setType($a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang));
        $this->flight($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function flight(Email $email)
    {
        $text = $this->textPdf;
        $text2 = $this->textPdf2;

        $tttext = explode("\n", $this->re("#\n([ ]*Traveler +eTicket Number.+\s*\n(?:.+\n){1,10}?)(?:\n|[ ]*FLIGHT INFORMATION)#", $text));
        array_shift($tttext);
        $pos = $this->colsPos(implode("\n", $tttext));

        $ttable = $this->splitCols(implode("\n", $tttext), $pos);

        //Traveler	eTicket Number	Frequent FlyerNumber	Seats		or		Traveler	eTicket Number	Seats
        if (count($ttable) != 3 && count($ttable) != 4) {
            $this->logger->info("incorrect parse traveler table");

            return;
        }

        if (count($ttable) == 4) {
            $seatsText = trim($ttable[3]);
        } else {
            $seatsText = trim($ttable[2]);
        }

        $fttext = $this->re("#FLIGHT INFORMATION\n(.*?)\n(?:\n\n|\n*FARE INFORMATION)#s", $text);

        $head = $this->re("#(?:\s*\n)*([ ]*\S.+)#", $fttext);
        $cnames = ['Day, Date', 'Departure City and Time', 'Arrival City and Time', 'Aircraft'];
        //          0			1			2							3						4
        $pos = [];

        foreach ($cnames as $cn) {
            $pos[] = mb_strpos($head, $cn, 0, 'UTF-8');
        }

        $segments = [];
        $segments = $this->split("#(?:^|\n)([ ]{0,10}\w{2,4},[ ]*\d{1,2}[ ]?[A-Z]{3}\d{2}[ ]*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d{1,5})#", $fttext);

        if (!empty($seatsText)) {
            $seatsRow = explode("\n", $seatsText);
            $seats = [];

            foreach ($seatsRow as $value) {
                $seatsEx = explode("/", $value);

                if (count($seatsEx) == count($segments)) {
                    foreach ($seatsEx as $key => $value) {
                        $seats[$key][] = $this->re("#^\s*(\d{1,3}[A-Z])\s*$#", $value);
                    }
                } else {
                    $seats = [];

                    break;
                }
            }
        }

        $flightSegments = $this->splitText($text2, '/^[ ]*Flight \d{1,3}$/m');

        $f = $email->add()->flight();
        $f->issued()
            ->tickets(array_filter(explode("\n", $ttable[1])), false)
            ->confirmation($this->re("#Confirmation:\s+([A-Z\d]+)\n#s", $text));
        $f->program()
            ->code('mileageplus');
        // ->phone('+1-2345-67890', 'Customer support');
        if (count($ttable) == 4 && !empty(trim($ttable[2]))) {
            $f->program()
                ->accounts(array_filter(preg_replace("#^\s*(?:UA\W{1,3}([X\d]{5,})\b.*|.*)\s*$#", '$1', explode("\n", $ttable[2]))), true);
        }

        // Price
        if (preg_match("#eTicket Total:[ ]+(\d[\d\,\.]+)([A-Z]{3})\s*\n#", $text, $m)) {
            $f->price()
                ->total(str_replace(',', '', $m[1]))
                ->currency($m[2]);
        }

        // General
        $f->general()
            ->confirmation($this->re("#Confirmation:\s+([A-Z\d]+)\n#s", $text))
            ->date2(date('Y-m-d', $this->normalizeDate($this->re("#Issue Date:\s+(.+)#", $text))))
            ->travellers(array_filter(explode("\n", $ttable[0])), true);

        foreach ($segments as $i => $stext) {
            $segment = $this->splitCols($this->re("#^(.+\n.+)#", $stext), $pos);

            if (count($segment) != 4) {
                $this->logger->info("incorrect parse flight table");

                return;
            }

            $s = $f->addSegment();

            if (preg_match("#^\s*([^\d\s]*\s*\d{1,2}[A-Z]{3,5}\d{2})#", $segment[0], $m)) {
                $date = $this->normalizeDate(trim($m[1]));
            }

            if (preg_match('/(\d+:\d+ [AP]M)\s+[(]\D+[)]\s+(?:(\d+:\d+ [AP]M\s+[(]\d{2}\D{3}[)])|(\d+:\d+ [AP]M))/', $stext, $m)) {
                $timeDepart = trim($m[1]);

                if (!empty($m[2])) {
                    $timeArriv = trim($m[2]);
                }

                if (!empty($m[3])) {
                    $timeArriv = trim($m[3]);
                }
            }

            $s->departure()
                ->code($this->re("#\(([A-Z]{3})(?:\)| -)#", $segment[1]))
                ->date2(date('Y-m-d H:i:s', $this->normalizeDate($timeDepart, $date)));

            $s->arrival()
                ->code($this->re("#\(([A-Z]{3})(?:\)| -)#", $segment[2]))
                ->date2(date('Y-m-d H:i:s', $this->normalizeDate($timeArriv, $date)));

            //sometimes there is a shift, then it is better concat strings  it-
            if (preg_match("#\b(\w{2})(\d+)\s+([A-Z]{1,2})(?:\s|\n|$)#", trim($segment[0]) . ' ' . trim($segment[1]), $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
                $s->extra()->bookingCode($m[3]);
            }

            if (preg_match("#Flight operated by (.+?)( doing business as|\.\s*$|\.\s*\n)#", $stext, $m)) {
                $s->airline()->operator($m[1]);
            }

            if (!empty(trim($segment[3]))) {
                $aircraftMeal = trim($segment[3]);
                $this->logger->debug('$aircraftMeal ' . $aircraftMeal);

                if (preg_match("#(?:(.+\d{3})|(^\d{3}))#", $aircraftMeal, $m)) {
                    if (isset($m[1]) && !empty(trim($m[1]))) {
                        $s->extra()->aircraft(trim($m[1]));
                    }
                    //$this->logger->debug('1- '.trim($m[1]));
                    //$this->logger->debug('2- '.trim($m[2]));
                    if (isset($m[2]) && !empty(trim($m[2]))) {
                        $s->extra()->aircraft(trim($m[2]));
                    }
                }

                if (preg_match("#([A-Z][a-z]+)#", $aircraftMeal, $m)) {
                    $s->extra()
                        ->meal(trim($m[1]));
                }
            }

            $seats[$i] = array_diff($seats[$i], [null]);

            if (isset($seats[$i])) {
                $s->extra()->seats($seats[$i]);
            }

            if (!empty($flightSegments[$i]) && preg_match('/\b' . $s->getDepCode() . '[ ]+' . $s->getArrCode() . '\b/', $flightSegments[$i])) {
                $tableHeadersPos = [0];

                if (preg_match('/(.+)Departing At:/', $flightSegments[$i], $matches)) {
                    $tableHeadersPos[1] = mb_strlen($matches[1]);
                }

                if (preg_match('/(.+)Arriving At:/', $flightSegments[$i], $matches)) {
                    $tableHeadersPos[2] = mb_strlen($matches[1]);
                }

                if (preg_match('/(.+)Aircraft:/', $flightSegments[$i], $matches)) {
                    $tableHeadersPos[3] = mb_strlen($matches[1]);
                } elseif (preg_match('/(.+)Duration:/', $flightSegments[$i], $matches)) {
                    $tableHeadersPos[3] = mb_strlen($matches[1]);
                }
                $segmentTable = $this->splitCols($flightSegments[$i], $tableHeadersPos);
                // depTerminal
                if (preg_match('/^[ ]*TERMINAL[ ]+([A-Z\d]+)[ ]*$/im', $segmentTable[1], $matches)) {
                    $s->setDepTerminal($matches[1]);
                }
                // arrTerminal
                if (preg_match('/^[ ]*TERMINAL[ ]+([A-Z\d]+)[ ]*$/im', $segmentTable[2], $matches)) {
                    $s->setArrTerminal($matches[1]);
                }
                // aircraft
                if (empty($s->getAircraft()) && preg_match('/^[ ]*Aircraft:[ ]*\n[ ]*([^:\n]+)/m', $segmentTable[3], $matches)) {
                    $s->setAircraft($matches[1]);
                }
                // duration
                if (preg_match('/^[ ]*Duration:[ ]*\n[ ]*(\d[\d hm]+)/m', $segmentTable[3], $matches)) { // 5h 57m
                    $s->setDuration($matches[1]);
                }
                // cabin
                if (preg_match('/^[ ]*(\w+)[ ]+CLASS[ ]*$/imu', $segmentTable[3], $matches)) { // BUSINESS CLASS
                    $s->setCabin($matches[1]);
                }
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        $in = [
            "#^([^\s\d]+) (\d+), (\d{4})$#", //August 22, 2017
            "#^[^\s\d]+, (\d+)([^\s\d]+)(\d{2})$#", //Wed, 23AUG17
            "#^(\d+:\d+ [AP]M) \((\d+)([^\s\d]+)\)$#", //9:15 PM (31AUG)
        ];
        $out = [
            "$2 $1 $3",
            "$1 $2 20$3",
            "$2 $3 %Y%, $1",
        ];

        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            $str = str_replace("%Y%", date('Y', $relDate), $str);
        }

        return strtotime($str, $relDate);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function rowColsPos($row)
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

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i=> $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
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
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
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
}
