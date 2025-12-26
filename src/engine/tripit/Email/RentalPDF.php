<?php

namespace AwardWallet\Engine\tripit\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalPDF extends \TAccountChecker
{
    public $mailFiles = "tripit/it-694507629.eml, tripit/it-694688208.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $subjects = [
        'Your car rental booking has been',
    ];

    public $confs = [];

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@trip.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, "TRIP.COM TRAVEL") !== false
                && strpos($text, 'Rental Voucher') !== false
                && (strpos($text, 'Pick-up') !== false)
            ) {
                return true;
            }

            if (strpos($text, "Trip.com") !== false
                && strpos($text, 'Rental Voucher') !== false
                && (strpos($text, 'Pick-up') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]trip\.com$/', $from) > 0;
    }

    public function ParseRentalPDF(Email $email, $text)
    {
        $textArray = splitter("/(Booking No.:)/u", $text);

        foreach ($textArray as $textSegment) {
            $conf = $this->re("/Booking No\.\:\s*(\d{5,})\s+/u", $textSegment);

            if (in_array($conf, $this->confs) === true) {
                continue;
            }

            $this->confs[] = $conf;

            $r = $email->add()->rental();

            $r->general()
                ->confirmation($conf);

            $paxsText = $this->re("/^(\s+Car Rental Supplier.+\n+Car Details)/msu", $textSegment);
            $paxTable = $this->splitCols($paxsText, [0, 50, 80]);

            $r->general()
                ->traveller(preg_replace("/\s+/", " ", $this->re("/Main Driver Name\s+(.+)/su", $paxTable[2])));

            $rentalInfo = $this->re("/(Pick-up[ ]{5,}.+)\nTotal/su", $textSegment);
            $rentalTable = $this->splitCols($rentalInfo);

            $r->car()
                ->type($this->re("/Car Type\s+(.+)\s+Transmission/u", $textSegment));

            $r->pickup()
                ->location(preg_replace("/\s+/", " ", $this->re("/Branch Address\s+(.+)\n*Pick-up Method/su", $rentalTable[0])))
                ->date(strtotime($this->re("/^\s*(\d{4}\-\d+\-\d+\s*\d+\:\d+)/mu", $rentalTable[0])))
                ->phone($this->re("/Contact Number\s+([\d\-\(\)]+)/u", $rentalTable[0]))
                ->openingHours($this->re("/Business Hours\s+(.+)\n*Branch Address/su", $rentalTable[0]));

            $r->dropoff()
                ->location(preg_replace("/\s+/", " ", $this->re("/Branch Address\s+(.+)\n*Drop-off Method/su", $rentalTable[1])))
                ->date(strtotime($this->re("/^\s*(\d{4}\-\d+\-\d+\s*\d+\:\d+)/mu", $rentalTable[1])))
                ->phone($this->re("/Contact Number\s+([\d\-\(\)]+)/u", $rentalTable[1]))
                ->openingHours($this->re("/Business Hours\s+(.+)\n*Branch Address/su", $rentalTable[1]));

            if (preg_match("/Total\s*(?<currency>[A-Z]{3})\s+(?<total>[\d\.\,]+)/u", $text, $m)) {
                $r->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            }
        }
    }

    public function ParseRental2PDF(Email $email, $text)
    {
        $textArray = splitter("/(Booking No.)/u", $text);

        foreach ($textArray as $textSegment) {
            $conf = $this->re("/Booking No\.\S\s*(\d{5,})\s+/u", $textSegment);

            if (in_array($conf, $this->confs) === true) {
                continue;
            }

            $this->confs[] = $conf;

            $r = $email->add()->rental();

            $email->ota()
                ->confirmation($conf);

            $paxsText = $this->re("/\n+^(\s+Confirmation No\..+Main Driver's Name.+)\n+\s+Pick-up Location/msu", $textSegment);
            $paxTable = $this->splitCols($paxsText, [0, 50, 87]);

            $r->general()
                ->confirmation($this->re("/Confirmation No\.\s+([A-Z]*\d{5,})/su", $paxTable[0]))
                ->traveller($this->re("/Main Driver's Name\s*(.+)/su", $paxTable[1]));

            $r->setCompany($this->re("/Car Supplier\s*(.+)/su", $paxTable[2]));

            if (preg_match("#Car Info\:\s+(?<model>.+or Similar)\s+\/\s+(?<type>.+)#", $textSegment, $m)) {
                $r->car()
                    ->model($m['model'])
                    ->type($m['type']);
            }

            $rentalInfo = $this->re("/\n+^(\s+Pick-up Location.+)Your Package Includes/msu", $textSegment);
            $rentalTable = $this->splitCols($rentalInfo, [0, 75]);

            if (preg_match("/\s+(?<depTime>\d+\:\d+)\:\d+\,\s*(?<depDate>\w+\s*\d+\,\s*\d{4})/u", $rentalTable[0], $m)
                || preg_match("/\s*(?<depDate>\d+\s*\w+\s*\d{4})\,\s+(?<depTime>\d+\:\d+)\:\d+/u", $rentalTable[0], $m)) {
                $r->pickup()
                    ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']));
            }

            if (preg_match("/\s+(?<arrTime>\d+\:\d+)\:\d+\,\s*(?<arrDate>\w+\s*\d+\,\s*\d{4})/u", $rentalTable[1], $m)
                || preg_match("/\s*(?<arrDate>\d+\s*\w+\s*\d{4})\,\s+(?<arrTime>\d+\:\d+)\:\d+/u", $rentalTable[1], $m)) {
                $r->dropoff()
                    ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']));
            }

            $r->pickup()
                ->location(preg_replace("/\s+/", " ", $this->re("/Branch Address\s+(?:PLEASE GO TO)?(.+)\n+\s+Contact Number/su", $rentalTable[0])))
                ->phone($this->re("/Contact Number\s+([\d\-\s\(\)]+)\n/u", $rentalTable[0]))
                ->openingHours($this->re("/Business Hours\s+(.+)\n/u", $rentalTable[0]));

            $r->dropoff()
                ->location(preg_replace("/\s+/", " ", $this->re("/Branch Address\s+(?:PLEASE GO TO)?(.+)\n+\s+Contact Number/su", $rentalTable[1])))
                ->phone($this->re("/Contact Number\s+([\d\-\s\(\)]+)\n/u", $rentalTable[1]))
                ->openingHours($this->re("/Business Hours\s+(.+)\n/u", $rentalTable[1]));

            if (preg_match("/^\s+Total\s+Already Paid.+\n\s+(?<currency>\D{1,3})\s*(?<total>[\d\.\,\']+)/mu", $text, $m)) {
                $r->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $text = '';

        foreach ($pdfs as $pdf) {
            $text = $text . "\n" . \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        if (preg_match("/Confirmation No.\s+Main Driver's Name\s+Car Supplier/", $text)) {
            $this->ParseRental2PDF($email, $text);
        } else {
            $this->ParseRentalPDF($email, $text);
        }

        if (preg_match("/Total Invoice Amount\s*([\d\.]+)\s*([A-Z]{3})/u", $text, $m)) {
            $email->price()
                ->total($m[1])
                ->currency($m[2]);
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

    private function re($re, $str, $c = 1): ?string
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\s*(\w+)\,\s*(\d{4})\,\s*([\d\:]+)$#u", //Thu 9 Mar, 2023, 16:40
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\,\s*([\d\:]+)$#u", //Thu 9 Mar, 2023, 16:40
        ];
        $out = [
            "$1 $2 $3, $4",
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

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
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
