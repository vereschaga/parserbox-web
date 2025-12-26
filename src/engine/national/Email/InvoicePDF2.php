<?php

namespace AwardWallet\Engine\national\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class InvoicePDF2 extends \TAccountChecker
{
    public $mailFiles = "national/it-148090355.eml";
    public $subjects = [
        'Invoice from National Car Rental',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public $company;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@ehi.com') !== false) {
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

            if (!empty($this->company = $this->re("/(National) Car Rental/", $parser->getSubject()))
                && strpos($text, 'Rental Location') !== false
                && strpos($text, 'Return Location') !== false
                && strpos($text, 'Rate Info') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ehi\.com$/', $from) > 0;
    }

    public function ParseRentalPDF(Email $email, $text)
    {
        $r = $email->add()->rental();

        $r->general()
            ->traveller($this->re("/{$this->opt($this->t('Renter Name'))}\s*([[:alpha:]][-.\,'[:alpha:] ]*[[:alpha:]])/", $text))
            ->confirmation($this->re("/{$this->opt($this->t('RA #'))}\s*(\d{6,})/", $text));

        if (preg_match("/Total Charges\s*([A-Z]{3})\s*([\d\.\,]+)/", $text, $m)) {
            if (preg_match("/^\d+\,\d+$/", $m[2])) {
                $r->price()
                    ->total(PriceHelper::cost($m[2], '.', ','));
            } else {
                $r->price()
                    ->total(PriceHelper::cost($m[2], ',', '.'));
            }
            $r->price()
                ->currency($m[1]);
        }

        $dateIn = $this->re("/[ ]{10,}(\d+\-\w+\-\d{4}\s*[\d\:]+\s*A?P?M)/u", $text);
        $dateOut = $this->re("/{$this->opt($this->t('Return Location'))}.+[ ]{10,}(\d+\-\w+\-\d{4}\s*[\d\:]+\s*A?P?M)/su", $text);

        $locationTable = $this->SplitCols($text, [0, 40]);

        if (preg_match("/{$this->opt($this->t('Rental Location'))}(.+){$this->opt($this->t('Return Location'))}(.+){$this->opt($this->t('Vehicle #'))}/su", $locationTable['0'], $m)) {
            $r->pickup()
                ->location(str_replace("\n", ' ', $m[1]))
                ->date($this->normalizeDate($dateIn));

            $r->dropoff()
                ->location(str_replace("\n", ' ', $m[2]))
                ->date($this->normalizeDate($dateOut));
        }

        $phone = $this->re("/{$this->opt($this->t('Phone'))}\s*([\(\)\d]+)/u", $text);

        if (!empty($phone)) {
            $r->pickup()
                ->phone($phone);
        }

        $carModel = $this->re("/{$this->opt($this->t('Model'))}\s*([A-Z\s]+)[ ]{30,}/", $text);

        if (!empty($carModel)) {
            $r->car()
                ->model($carModel);
        }

        if (!empty($this->company)) {
            $r->setCompany($this->company);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $this->emailSubject = $parser->getSubject();

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseRentalPDF($email, $text);
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

    private function normalizeDate($date)
    {
        //$this->logger->debug($date);
        $in = [
            // 18-MAR-2022 06:04 AM
            "/^(\d+)\-(\w+)\-(\d{4})\s*([\d\:]+\s*A?P?M)$/u",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function detectLang($text)
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if (!empty($this->re("/($word)/", $text))) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function TableHeadPos($text)
    {
        $row = explode("\n", $text)[0];
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
