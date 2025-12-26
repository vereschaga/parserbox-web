<?php

namespace AwardWallet\Engine\yes2you\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class StatementCharge extends \TAccountChecker
{
    public $mailFiles = "yes2you/it-79174936.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $lang = 'en';
    private $detectUniqueSubject = [
        'Your Kohl\'s Charge Statement and Annual Privacy Notice Is Available Online',
    ];

    private $detectBody = [
        'Kohl\'s Charge statement',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.kohls.com') !== false || stripos($from, '@kohls.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectUniqueSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains(['.kohlscorporation.com', '.mykohlscharge.com', '.kohls.com'], '@href') . "]")->length === 0
            && $this->http->XPath->query("//text()[" . $this->contains(['Kohl\'s Customer Service']) . "]")->length === 0
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

        $name = $this->http->FindSingleNode("//text()[" . $this->starts("Dear") . "]", null, true,
            "/^\s*Dear ([[:alpha:] \-]+),\s*$/");
        $st->addProperty("Name", $name);
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
