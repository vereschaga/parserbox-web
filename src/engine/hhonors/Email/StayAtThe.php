<?php

namespace AwardWallet\Engine\hhonors\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class StayAtThe extends \TAccountChecker
{
    public $mailFiles = "hhonors/it-76338795.eml, marriott/it-55157300.eml, marriott/it-55157328.eml, marriott/it-56130758.eml";

    public static $dictionary = [
        'en' => [],
    ];
    private $detectFrom = "hilton.com";
    private $detectSubject = [
        'en' => "Stay at the", //Stay at the Hilton Tokyo Odaiba - Feb 1, 2021 to Apr 2, 2021
    ];

    private $detectBody = [
        'en' => ["TRAVEL DATES:"],
    ];

    private $lang = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang($this->http->Response['body']);

        $emailText = $this->htmlToText($this->http->Response['body']);

        $this->parseText($email, $emailText);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseText(Email $email, string $emailText): void
    {
//        $this->logger->debug('$emailText = '.print_r( $emailText,true));

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
        ;

        // Hotel
        $h->hotel()
            ->name(preg_replace("/\s+/", ' ', trim($this->re("/HOTEL NAME:\s*([\s\S]+?)\s+TRAVEL DATES:/", $emailText))))
            ->address(preg_replace("/\s+/", ' ', trim($this->re("/ADDRESS:\s*([\s\S]+?)\s+Check-in:/", $emailText))))
            ->phone(trim($this->re("/PHONE:\s*([\s\S]+)\s+ADDRESS:/", $emailText)))
        ;

        //Booked
        if (preg_match("/TRAVEL DATES:\s*(.+) to (.+?)\s+PHONE:/", $emailText, $m)) {
            $ciTime = $this->re("/Check-in:\s*(\d{1,2}:\d{2}(?: [pa]m))\s+/i", $emailText);
            $coTime = $this->re("/Check-out:\s*(\d{1,2}:\d{2}(?: [pa]m))\s+/i", $emailText);
            $h->booked()
                ->checkIn($this->normalizeDate($m[1] . (($ciTime) ? ', ' . $ciTime : '')))
                ->checkOut($this->normalizeDate($m[2] . (($coTime) ? ', ' . $coTime : '')))
            ;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
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
        $body = $parser->getHTMLBody();

        if (strpos($body, 'www.hilton.com') === false) {
            return false;
        }

        return $this->assignLang($body);
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

    private function assignLang(?string $text): bool
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($text, $dBody) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug("Date: {$date}");
        $in = [
            // Feb 1, 2021, 03:00 PM
            "#^\s*([[:alpha:]]+)\s+(\d{1,2}),\s*(\d{4}),\s*(\d{1,2}:\d{2}(?:\s*[ap]m))\s*$#ui",
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
