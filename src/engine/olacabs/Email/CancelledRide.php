<?php

namespace AwardWallet\Engine\olacabs\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CancelledRide extends \TAccountChecker
{
    public $mailFiles = "olacabs/it-494165322.eml, olacabs/it-494223161.eml";
    public $detectFrom = '@olacabs.com';
    public $detectSubject = [
        'Fee for your cancelled ride',
    ];
    public $detectBody = [
        'en' => ['A fee has been charged as per our cancellation policy.'],
    ];

    public static $dictionary = [
        'en' => [
            'afterConf' => ['A fee has been charged as per our cancellation policy', 'We\'re here the next time you step out'],
        ],
    ];

    public $lang = 'en';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseTransfer($email);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers["from"], $headers["subject"]) || stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (strpos($headers["subject"], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        //  only detect by header

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

    private function parseTransfer(Email $email)
    {
        $t = $email->add()->transfer();
        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('afterConf'))}]/preceding::text()[normalize-space()][1]", null, false, '/^\s*([A-Z]{3}\d{5,})\s*$/'))
            ->status('cancelled')
            ->cancelled()
        ;
    }

    private function assignLang()
    {
        if ($this->http->XPath->query("//*[{$this->contains('olacabs.com', '@href')}]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'normalize-space(' . $node . ')="' . $s . '"';
                }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'contains(normalize-space(' . $node . '),"' . $s . '")';
                }, $field))
            . ')';
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
}
