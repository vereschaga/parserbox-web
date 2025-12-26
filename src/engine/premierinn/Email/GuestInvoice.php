<?php

namespace AwardWallet\Engine\premierinn\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class GuestInvoice extends \TAccountChecker
{
    public $mailFiles = "premierinn/it-72118196.eml, premierinn/it-73001398.eml";
    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = '.premierinn.com';
    private $detectSubject = [
        // en
        'Invoice for your stay',
    ];
    private $detectBody = [
        'en' => ['Your invoice is attached below'],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.premierinn.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking reference")) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*([A-Z\d]{5,})(?: - .*)?\s*$/"),
                $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking reference")) . "]"))
        ;

        $invoiceText = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Address")) . "]/ancestor::*[" . $this->contains($this->t("Arrive")) . "][1]//text()"));
//        $this->logger->debug($invoiceText);

        if (preg_match("/" . $this->preg_implode($this->t("Names INVOICE")) . "(?:\s+\([^\)]+\))?\s+((?:.*\n){1,10}?)(?:" . $this->preg_implode($this->t("Address")) . "|" . $this->preg_implode($this->t("Transaction Statement")) . ")/u", $invoiceText, $m)) {
            $h->general()
                ->travellers(explode("\n", trim($m[1])), true);
        }

        // Hotel
        if (preg_match("/^\s*(?<name>.+)\s+(?<address>(?:.*\n){1,7}.*) " . $this->preg_implode($this->t("Tel")) . " (?<phone>[\d \-\(\)\+]{5,})\n/u", $invoiceText, $m)) {
            $h->hotel()
                ->name(trim($m['name']))
                ->address(preg_replace('/\s*\n\s*/', ', ', trim($m['address'])))
                ->phone(trim($m['phone']))
            ;
        }

        // Booked
        if (preg_match("/\s+" . $this->preg_implode($this->t("Arrive")) . "\s*:\s*([\d\/]{6,})\s+/", $invoiceText, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m[1]));
        }

        if (preg_match("/\s+" . $this->preg_implode($this->t("Depart")) . "\s*:\s*([\d\/]{6,})\s+/", $invoiceText, $m)) {
            $h->booked()
                ->checkOut($this->normalizeDate($m[1]));
        }

        if (preg_match("/\s+" . $this->preg_implode($this->t("Guests")) . "\s*:\s*(\d+)\s+/", $invoiceText, $m)) {
            $h->booked()
                ->guests($m[1]);
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 26/09/20
            "/^\s*(\d{1,2})\/(\d{2})\/(\d{2})\s*$/iu",
        ];
        $out = [
            "$1.$2.20$3",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
