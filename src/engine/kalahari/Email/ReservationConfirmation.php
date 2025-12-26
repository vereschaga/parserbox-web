<?php

namespace AwardWallet\Engine\kalahari\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "kalahari/it-576259301.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public $flightOrder = 0;

    public static $dictionary = [
        "en" => [
            'Welcome to the Kalahari' => ['Welcome to the Kalahari', 'Thank you for your reservation at'],
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

            if (strpos($text, "www.kalahariresorts.com") !== false
                && preg_match("/{$this->opt($this->t('Welcome to the Kalahari'))}/", $text)
                && (strpos($text, 'Street Address:') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]kalahariresorts\.com$/', $from) > 0;
    }

    public function ParseHotelPDF(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/{$this->opt($this->t('Confirmation #:'))}\s*([A-Z\d]{6,})/", $text))
            ->cancellation($this->re("/Cancellation Policy\n+(.+)/", $text));

        $address = $this->re("/{$this->opt($this->t('Street Address:'))}\s*(.*)\n/", $text);

        if (stripos($address, "|") !== false) {
            $address = $this->re("/^(.+)\|/", $address);
        }

        $h->hotel()
            ->name($this->re("/^(Kalahari.*)\n/", $text))
            ->address($address);

        $hotelInfo = $this->re("/\n\s+(Confirmation #:.+)\n+\s+Stay Date/su", $text);
        $table = $this->splitCols($hotelInfo);

        $h->general()
            ->traveller($this->re("/Confirmation #:.+\n+([[:alpha:]][-.\'’,[:alpha:] ]*[[:alpha:]])\n/", $table[0]));

        $h->booked()
            ->checkIn(strtotime($this->re("/Arrival Date:\s*(.*[\d\/]+)/", $table[1])))
            ->checkOut(strtotime($this->re("/Departure Date:\s*(.*[\d\/]+)/", $table[1])))
            ->guests($this->re("/# of Total Guests:\s*(\d+)/", $table[1]));

        $rateInfo = $this->re("/\n+(\s+Stay\s*Date.*Room Rate.*{$this->opt($this->t('Welcome to the Kalahari'))})/us", $text);

        if (preg_match_all("/(\w+\s*[\d\/]+\d{2}\s*\D{1,3}[\d\.\,]+)/", $rateInfo, $m)) {
            $h->addRoom()->setRates(preg_replace("/[ ]{4,}/", " - ", $m[1]));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseHotelPDF($email, $text);
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{10,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }
}
