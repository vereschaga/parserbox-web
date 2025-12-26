<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TransferVoucherPdf extends \TAccountChecker
{
    public $mailFiles = "mta/it-234066021.eml";

    public $detectBodyProvider = [
        // en
        'MTA Travel - ',
    ];
    public $detectBody = [
        'en'  => ['Prepaid Voucher -'],
    ];
    public $lang = 'en';
    public $pdfNamePattern = ".*\.pdf";
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $detectedProvider = false;

            foreach ($this->detectBodyProvider as $dbProv) {
                if (stripos($text, $dbProv) !== false) {
                    $detectedProvider = true;

                    break;
                }
            }

            if ($detectedProvider == false) {
                continue;
            }

            foreach ($this->detectBody as $lang => $ldBody) {
                foreach ($ldBody as $dBody) {
                    if (strpos($text, $dBody) !== false && strpos($text, 'Transfer From') !== false) {
                        $this->lang = $lang;
                        $this->parseEmailPdf($text, $email);

                        continue 2;
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $detectedProvider = false;

            foreach ($this->detectBodyProvider as $dbProv) {
                if (stripos($text, $dbProv) !== false) {
                    $detectedProvider = true;

                    break;
                }
            }

            if ($detectedProvider == false) {
                continue;
            }

            foreach ($this->detectBody as $ldBody) {
                foreach ($ldBody as $dBody) {
                    if (strpos($text, $dBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "mtatravel.com.au") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmailPdf($text, Email $email)
    {
//        $this->logger->debug('$text = '.print_r( $text,true));
        $email->ota()
            ->confirmation($this->re("/Prepaid Voucher - *([\d\-]{5,})\s*\n/", $text));

        $t = $email->add()->transfer();

        // General
        $t->general()
            ->confirmation($this->re("/\n\s*Confirmation Number *([A-Z\d\-]{5,})\s*\n/", $text))
            ->travellers(array_filter(preg_replace(["/^\s*(Mr|Miss|Mrs|Ms) /", "/^\s*\d+\s+more\s*$/i"], '',
                preg_split("/\s*[\|&]\s*/", trim($this->re("/\n *Guest Name\(s\) *(\S.+(?:\n {20,}\S.+)*)\s*\n/", $text))), true)))
        ;

        $address1 = preg_replace("/\s+/", ' ', trim($this->re("/\n\s*Transfer From\s+([\S\s]+?)\n\s*Transfer To/", $text)));
        $address2 = preg_replace("/\s+/", ' ', trim($this->re("/\n\s*Transfer To\s+([\S\s]+?)\n *Guest Name\(s\)/", $text)));

        $phone = $this->re("/\n *Supplier Contact +([+]\s*[\d \-\.]{5,})\n/", $text);

        if (!empty($phone)) {
            $t->addProviderPhone($phone, 'Supplier Contact');
        }

        $s = $t->addSegment();
        $s->departure()
            ->name($address1)
            ->date($this->normalizeDate($this->re("/\n\s*Journey 1 *(.+)\s*\n/", $text)));

        if (preg_match("/\(([A-Z]{3})\)\s*$/u", $s->getDepName(), $m)) {
            $s->departure()
                ->code($m[1]);
        }
        $s->arrival()
            ->name($address2)
            ->noDate()
        ;

        if (preg_match("/\(([A-Z]{3})\)\s*$/u", $s->getArrName(), $m)) {
            $s->arrival()
                ->code($m[1]);
        }

        $s->setDuration($this->re("/\n *Estimated Transfers (.+)\n *Time/", $text));

        if (!preg_match("/{$this->opt($this->t('Transfer Type One Way'))}/", $text)) {
            $s = $t->addSegment();
            $s->departure()
                ->name($address2)
                ->date($this->normalizeDate($this->re("/\n\s*Journey 2 *(.+)\s*\n/", $text)));

            if (preg_match("/\(([A-Z]{3})\)\s*$/u", $s->getDepName(), $m)) {
                $s->departure()
                    ->code($m[1]);
            }
            $s->arrival()
                ->name($address1)
                ->noDate()
            ;

            if (preg_match("/\(([A-Z]{3})\)\s*$/u", $s->getArrName(), $m)) {
                $s->arrival()
                    ->code($m[1]);
            }

            $s->setDuration($this->re("/\n *Estimated Transfers (.+)\n *Time/", $text));
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug("date in: " . $date);
        $in = [
            //03 Dec 2022 20:35 (Pick up 20:35)
            '/^\s*(\d+) (\w+) (\d{4})\b.*\(Pick up (\d{1,2}:\d{2}(?:\s*[ap]m)?)\)\s*$/us',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug("date out: " . $date);

        return strtotime($date);
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '#'));
        }, $field)) . ')';
    }
}
