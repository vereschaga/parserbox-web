<?php

namespace AwardWallet\Engine\airasia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightPDF extends \TAccountChecker
{
    public $mailFiles = "airasia/it-808285327.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".+pdf";
    public $flightOrder = 0;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, "within the airasia") !== false
                && (strpos($text, 'Booking date:') !== false)
                && (strpos($text, 'Guest details') !== false)
                && (strpos($text, 'Flight summary') !== false)
                && (strpos($text, 'Depart :') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/{$this->opt($this->t('Booking no.'))}\n([A-Z\d]{6})\n/", $text))
            ->date(strtotime(str_replace(',', '', $this->re("#{$this->opt($this->t('Booking date'))}[\s\:]+(\d+\s*\w+\s*\d{4})#", $text))));

        $travellerTextTemp = preg_replace("/\(\w+\)/", "", $this->re("/Guest details\n+(.+)\n+\s*Flight summary/s", $text));
        $travellerText = implode("\n", $this->splitCols($travellerTextTemp));

        $travellers = [];

        if (preg_match_all("/([[:alpha:]][-&.\'’[:alpha:] ]*[[:alpha:]])/u", $travellerText, $m)) {
            $f->general()
                ->travellers($travellers = $m[1], true);
        }

        $allCodesText = $this->re("/^([ ]*Depart :.+)\n\n\n[ ]+Depart/msu", $text);
        $codeParts = $this->splitText($allCodesText, "/^([ ]+\d+\:\d+.+)/mu", true);

        $allSegmentsText = $this->re("/\n([ ]*Depart:\s+\w+\,\s+\d+\s*\w+\s+\d{4}.+)\n[ ]*Add-ons\n/su", $text);

        $segments = $this->splitText($allSegmentsText, "/(?:^|\n)((?:[ ]*Depart:.+|[ ]*Return:.+)|[ ]*Layover.+)/u", true);

        $depName = '';
        $arrName = '';

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $year = $this->re("/Depart\:\s+\w+\,\s+\d+\s*\w+\s*(\d{4})/", $segment);

            if (preg_match("/[ ]+.+\,\s+(?<aName>([A-Z][A-Z\d]|[A-Z\d][A-Z]))\s+(?<fNumber>\d{2,4})\n\s*(?<duration>\d+(?:h|m).*)\n[ ]+(?<cabin>\w+\s?\w+?)\n/", $segment, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->extra()
                    ->duration($m['duration'])
                    ->cabin($m['cabin']);
            }

            if (preg_match("/(?:^.+\:|Layover).+\n+\s*(?<depTime>\d+\:\d+)\s+(?<name1>.+)\n\s*(?<date>\d+\s*\w+)\s*(?<name2>.+)\s*\,\s*Terminal\s+(?<depTerminal>.+)/m", $segment, $m)
            || preg_match("/(?:^.+\:|Layover).+\n+\s*(?<depTime>\d+\:\d+)\s+(?<name1>.+)\n\s*(?<date>\d+\s*\w+)\s*(?<name2>.+)/m", $segment, $m)) {
                $depName = $m['name1'];
                $s->departure()
                    ->name($m['name1'] . ', ' . $m['name2'])
                    ->date(strtotime($m['date'] . "$year, " . $m['depTime']));

                if (isset($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }

                foreach ($codeParts as $codePart) {
                    $code = $this->re("/^[ ]+{$m['depTime']}\s+\d+\:\d+.+\n\s+([A-Z]{3})/", $codePart);

                    if (!empty($code)) {
                        $s->departure()
                            ->code($code);
                    }
                }

                if (empty($s->getDepCode())) {
                    $s->departure()
                        ->noCode();
                }
            }

            if (preg_match("/\n+\s*(?<arrTime>\d+\:\d+)\s+(?<name1>.+)\n\s*(?<date>\d+\s*\w+)\s*(?<name2>.+)\s*\,\s*Terminal\s+(?<arrTerminal>.+)\s*$/", $segment, $m)
                || preg_match("/\n+\s*(?<arrTime>\d+\:\d+)\s+(?<name1>.+)\n\s*(?<date>\d+\s*\w+)\s*(?<name2>.+)\s*$/", $segment, $m)) {
                $arrName = $m['name1'];
                $s->arrival()
                    ->name($m['name1'] . ', ' . $m['name2'])
                    ->date(strtotime($m['date'] . "$year, " . $m['arrTime']));

                if (isset($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }

                foreach ($codeParts as $codePart) {
                    $code = $this->re("/^[ ]+\d+\:\d+\s+{$m['arrTime']}.+\n\s+[A-Z]{3}\s+([A-Z]{3})/", $codePart);

                    if (!empty($code)) {
                        $s->arrival()
                            ->code($code);
                    }
                }

                if (empty($s->getArrCode())) {
                    $s->arrival()
                        ->noCode();
                }
            }

            $passengerText = $this->re("/Add-ons\n+(.+)/s", $text);
            $passengerTextParts = $this->splitText($passengerText, "/^([ ]*[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]\s+to\s+[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]\n)/m", true);

            foreach ($passengerTextParts as $part) {
                if (stripos($part, $depName . ' to ' . $arrName) !== false) {
                    $partArray = $this->splitCols($this->re("/^[ ]*[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]\s+to\s+[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]\n+(.+)/s", $part));
                    $partText = implode("\n", $partArray);

                    foreach ($travellers as $pax) {
                        $seat = $this->re("/{$pax}\n+(?:.+\n+){2,5}Seat\s+(\d+[A-Z])/", $partText);

                        if (!empty($seat)) {
                            $s->addSeat($seat, true, true, $pax);
                        }
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseFlightPDF($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\,\s*(\w+)\s(\d+)\,\s*(\d{4})\,\s*([\d\:]+)$#u", //Sunday, October 16, 2022, 13:15
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
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

    private function rowColsPos($row): array
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

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
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
}
