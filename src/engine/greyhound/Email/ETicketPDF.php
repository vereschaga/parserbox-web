<?php

namespace AwardWallet\Engine\greyhound\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicketPDF extends \TAccountChecker
{
    public $mailFiles = "greyhound/it-498680707.eml";
    public $lang = 'en';

    public $pdfNamePattern = "eTicket[\_\d]+.*pdf";

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

            if (stripos($text, 'Greyhound') !== false
                && stripos($text, 'BOOKING DETAILS') !== false
                && stripos($text, 'COACH TRAVEL INCLUDED ON THIS TICKET') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]greyhound\.com\.au/', $from) > 0;
    }

    public function ParseBus(Email $email, string $text)
    {
        $b = $email->add()->bus();

        $b->general()
            ->traveller($this->re("/PASSENGER NAME\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:[ ]{10,}|\n)/", $text), true)
            ->confirmation($this->re("/BOOKING NUMBER\s*(\d{7,})/", $text));

        if (preg_match("/DATE OF ISSUE\s*(\d+)\-(\w+)\-(\d{4})/", $text, $m)) {
            $b->general()
                ->date(strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3]));
        }

        $price = $this->re("/(Total\s*\D{1,3}\s*[\d\.\,]+)\n/", $text);

        if (preg_match("/^Total\s*(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)$/", $price, $m)) {
            $currency = $this->re("/All ([A-Z]{3}) items include/u", $text);

            if (empty($currency)) {
                $currency = $m['currency'];
            }

            $b->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $segmentsText = $this->re("/\n+\s+COACH TRAVEL INCLUDED ON THIS TICKET\n+(.+)\n+\s+IMPORTANT INFORMATION/su", $text);
        $segmentsText = str_replace('Seat Seat allocated by driver QLD', '', $segmentsText);
        $segments = splitter("/(\s*SERVICE NO\s*DEPARTURE POINT\s*ARRIVAL POINT\n)/u", $segmentsText);

        foreach ($segments as $seg) {
            $s = $b->addSegment();

            $segTable = $this->splitCols($seg, [0, 15, 90]);

            $s->setNumber($this->re("/SERVICE NO\s*([A-Z\d]+)/su", $segTable[0]));

            if (preg_match("/DEPARTURE POINT\s+(?<depName>.+)\n(?<depTime>\d+\:\d+)\s+\(.*\)\s*\w+\,\s+(?<depDate>\d+\s*\w+\s*\d{4})/su", $segTable[1], $m)) {
                $s->departure()
                    ->name(str_replace("\n", " ", $m['depName']))
                    ->date(strtotime($m['depDate'] . ' ' . $m['depTime']));
            }

            if (preg_match("/ARRIVAL POINT\s+(?<arrName>.+)\n(?<arrTime>\d+\:\d+)\s+\(.*\)\s*\w+\,\s+(?<arrDate>\d+\s*\w+\s*\d{4})/su", $segTable[2], $m)) {
                $s->arrival()
                    ->name(str_replace("\n", " ", $m['arrName']))
                    ->date(strtotime($m['arrDate'] . ' ' . $m['arrTime']));
            }

            $seats = array_filter(explode(",", $this->re("/Seat\s*(.+)/", $segTable[0])));

            if (count($seats) > 0) {
                $s->setSeats($seats);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->ParseBus($email, $text);
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

    public function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
