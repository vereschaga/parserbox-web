<?php

namespace AwardWallet\Engine\peninsula\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class FolioPdf extends \TAccountChecker
{
    public $mailFiles = "peninsula/it-236183075.eml";

    private $detectFrom = '@peninsula.com';
    private $detectSubject = [
        // en
        ' - Guest Folio for your stay from '
    ];

    private $detectCompany = ['The Peninsula'];
    private $detectBody = [
        'en' => [
            'INFORMATION INVOICE'
        ],
    ];
    private $pdfNamePattern = ".*\.pdf";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs)) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]), false);

            if (empty($text)) {
                return false;
            }

            if ($this->detectBody($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectBody($body): bool
    {
        $foundCompany = false;
        foreach ($this->detectCompany as $dCompany) {
            if (stripos($body, $dCompany) !== false) {
                $foundCompany = true;

                break;
            }
        }

        if ($foundCompany == false) {
            return false;
        }
        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false) {
                    $this->lang = $lang;
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf), false)) !== null) {
                if ($this->detectBody($text)) {
                    $this->parseEmailPdf($email, $text);
                }
            }
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

    private function parseEmailPdf(Email $email, $text)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->re("/(?: {3,}|\n\s*)Confirmation(?: +| *: *)(\d+)(?: {3,}|\n)/i", $text))
            ->traveller(preg_replace("/^\s*(Mr|Ms|Mrs|Miss|Mstr|Dr)[\.\s]+/", '',
                $this->re("/^(?: {20,}.*\n+)* {0,5}(.+?)(?: {2,}|\n)/", $text)))
        ;

        // Hotel
        $h->hotel()
            ->name(preg_replace('/\s+/', ' ', trim($this->re("/We wish to thank you for choosing (?:to stay at )?(The\s*Peninsula\s*.*?)\./s", $text))))
            ->noAddress();

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->re("/\s{2,}Arrival *: *(.+)\n/", $text)))
            ->checkOut($this->normalizeDate($this->re("/\s{2,}Departure *: *(.+)\n/", $text)))
            ->guests($this->re("/\s{2,}Person\(s\) *: *(.+)\n/", $text))
        ;

        // Price
        $fees = [];
        $feesRows = array_filter(explode("\n", $this->re("/\n\s*INFORMATION INVOICE[\s\S]*?((?:\n {0,10}\d{2}-\d{2}-\d{2} .*)+)\n/", $text)));
        foreach ($feesRows as $ftext) {
            if (preg_match("/^ {0,10}\d{2}-\d{2}-\d{2} (?<name>.*) {2,}(?<amount>\d[\d., ]*)$/", $ftext, $m) ) {
                if (!preg_match("/^\s*(?:Room Charge|Negotiated Rate)/", $m['name'])) {
                    $fees[strlen($ftext)][] = ['name' => $m['name'], 'amount' => PriceHelper::cost($m['amount'])];
                }
            } else {
                $fees = [];
            }
        }
        if (count($fees) == 2) {
            ksort($fees);
            foreach (array_shift($fees) as $frow) {
                $h->price()
                    ->fee($frow['name'], $frow['amount']);
            }
        }

        $total = $this->re("/ {3,}Total {2,}(\d[\d., ]*) {2,}/", $text);
        $h->price()
            ->total(PriceHelper::cost($total));

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }
        return self::$dictionary[$this->lang][$s];
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
//            // 11-15-22
            '/^\s*(\d{2})-(\d{2})-(\d{2})\s*$/iu',
        ];
        $out = [
            '$2.$1.20$3',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));


//        $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array)$field;
        if (empty($field)) {
            $field = ['false'];
        }
        return '(?:' . implode("|", array_map(function ($s) use($delimiter) {
                return str_replace(' ', '\s+', preg_quote($s, $delimiter));
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

}