<?php

namespace AwardWallet\Engine\priceline\Email;

use AwardWallet\Schema\Parser\Email\Email;

class RentalCarFlightReceiptJunk extends \TAccountChecker
{
    public $mailFiles = "priceline/it-653693977.eml, priceline/it-654319060.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Your receipt from Priceline' => 'Your receipt from Priceline',
            'Your rental car on'          => ['Your rental car on', 'Your flight on'],
            ' • Pick-up:'                 => [' • Pick-up:', ' • Departure:'],
            'Payment Summary'             => 'Payment Summary',
        ],
    ];

    private $detectFrom = "info@travel.priceline.com";
    private $detectSubject = [
        // en
        'Your rental car receipt from Priceline (Trip#',
        'Your flight receipt from Priceline (Trip#',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]priceline\.com\b/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Priceline') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['priceline.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['priceline.com LLC'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (
                !empty($dict['Your receipt from Priceline']) && !empty($dict['Your rental car on']) && !empty($dict['Payment Summary'])
                && $this->http->XPath->query("//*[{$this->eq($dict['Your receipt from Priceline'])}]")->length > 0
                && $this->http->XPath->query("//div[not(.//div)][{$this->starts($dict['Your rental car on'])}]/following::div[not(.//div)][position() = 6 or position() = 9][{$this->eq($dict['Payment Summary'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email)
    {
        if ($this->http->XPath->query("//*[{$this->eq($this->t('Your receipt from Priceline'))}]")->length === 0) {
            return false;
        }

        $rowXpath = "//div[not(.//div)][{$this->starts($this->t('Your rental car on'))}]/following::div[not(.//div)][normalize-space()]";

        if (
            !empty($this->http->FindSingleNode("//div[not(.//div)][{$this->starts($this->t('Your rental car on'))}]", null, true,
                "/^\s*{$this->opt($this->t('Your rental car on'))} [\w,.\- ]*\b20\d{2}\b[\w,.\- ]* is confirmed\s*$/"))
            && !empty($this->http->FindSingleNode($rowXpath . "[1][{$this->starts($this->t('Total Cost:'))}]"))
            && !empty($this->http->FindSingleNode($rowXpath . "[2][{$this->starts($this->t('Priceline Trip Number:'))}]"))
            && !empty($this->http->FindSingleNode($rowXpath . "[3]/preceding::img[1]/@src[contains(., '/cars.png') or contains(., '/flights_blue.png')]"))
            && !empty($this->http->FindSingleNode($rowXpath . "[4][{$this->contains($this->t(' • Pick-up:'))}]", null, true,
                "/^\s*\w+ \w+(\s*-\s*\w+ \w+)?\s*{$this->opt($this->t(' • Pick-up:'))}\s*\d{1,2}:\d{2}(?: ?[ap]m)?\s*$/i"))
            && !empty($this->http->FindSingleNode($rowXpath . "[5][{$this->starts($this->t('Confirmation #:'))}]"))
            && !empty($this->http->FindSingleNode($rowXpath . "[position() = 6 or position() = 9][{$this->eq($this->t('Payment Summary'))}]"))
        ) {
            $email->setIsJunk(true);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
