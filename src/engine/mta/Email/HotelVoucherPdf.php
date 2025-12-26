<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class HotelVoucherPdf extends \TAccountChecker
{
    public $mailFiles = "mta/it-153141434.eml";

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
                    if (strpos($text, $dBody) !== false) {
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

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->re("/Prepaid Voucher - *([\d\-]{5,})\s*\n/", $text))
            ->travellers( preg_replace("/^\s*(Mr|Miss|Mrs|Ms) /", '',
                preg_split("/\s+\|\s+/", trim($this->re("/\n *Guest Name\(s\) *(\S.+(?:\n {20,}\S.+)*)\s*\n/", $text))), true))
        ;

        // Hotel
        $h->hotel()
            ->name($this->re("/(.+)\n *Address +/", $text))
            ->address($this->re("/\n *Address +(.+)/", $text))
            ->phone($this->re("/\n *Address +.+\n\s*Phone (.+)/", $text), true, true)
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->re("/Check In (.+?) \|/", $text)))
            ->checkOut($this->normalizeDate($this->re("/Check Out (.+) \|/", $text)))
        ;

        $h->addRoom()
            ->setType($this->re("/\n *Room Type\s*(.+)/", $text));

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
            //18 jul, 2019
//            '#^(\d+)\-(\w+)\-(\d{4})$#u',
        ];
        $out = [
//            '$1 $2 $3',
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
