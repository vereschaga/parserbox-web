<?php

namespace AwardWallet\Engine\bookeo\Email;

use AwardWallet\Schema\Parser\Email\Email;

class NewBookinkJunk extends \TAccountChecker
{
    public $mailFiles = "bookeo/it-764912792.eml, bookeo/it-768223070.eml, bookeo/it-768988539.eml";

    public $detectSubjects = [
        // en
        'New booking - ',
        'Booking canceled - ',
        'Booking changed - ',
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'You have just received a new booking!' => ['You have just received a new booking!', ' has changed a booking!', 'has canceled a booking'],
            'View booking'                          => 'View booking',
            'Customer'                              => 'Customer',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@bookeo.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        // detect only in detectEmailByBody
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getCleanFrom()) !== true) {
            return false;
        }

        $detectedSubject = false;

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($parser->getSubject(), $dSubject) !== false) {
                $detectedSubject = true;

                break;
            }
        }

        if ($detectedSubject === false) {
            return false;
        }

        if ($this->http->XPath->query('//a[contains(@href,"//bookeo.com/") or contains(@href,"www.bookeo.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"powered by Bookeo")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (!empty($this->lang)) {
            $email->setSentToVendor(true);
            $email->setIsJunk(true, 'no address');
        }

        $email->setType('NewBookinkJunk' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!empty($phrases['You have just received a new booking!'])
                && $this->http->XPath->query("//node()[{$this->contains($phrases['You have just received a new booking!'])}]")->length > 0
                && !empty($phrases['View booking'])
                && $this->http->XPath->query("//a[{$this->eq($phrases['View booking'])}]/@href[contains(., 'signin.bookeo.com')]")->length > 0
                && !empty($phrases['Customer'])
                && $this->http->XPath->query("//node()[{$this->eq($phrases['Customer'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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
}
