<?php

namespace AwardWallet\Engine\yes2you\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class EGiftCard extends \TAccountChecker
{
    public $mailFiles = "yes2you/it-79764085.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $lang = 'en';
    private $detectUniqueSubject = [
        'has viewed your e-Gift Card from Kohl\'s!',
    ];

    private $detectBody = [
        'USD Kohl\'s e-Gift Card that you sent on',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.kohls.com') !== false || stripos($from, '@kohls.com') !== false || stripos($from, 'kohlsgiftcards@cashstar.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        // sent from: kohlsgiftcards@cashstar.com
        foreach ($this->detectUniqueSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains(['.kohls.com'], '@href') . "]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($dBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $st
            ->setMembership(true)
            ->setNoBalance(true)
        ;

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
        return 0;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
