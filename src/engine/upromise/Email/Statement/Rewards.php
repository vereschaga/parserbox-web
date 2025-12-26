<?php

namespace AwardWallet\Engine\upromise\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Rewards extends \TAccountChecker
{
    public $mailFiles = "upromise/it-80122364.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = '@upromise.com';

    private $detectBody = [
        'Available Cash Back Rewards',
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@upromise.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains('.upromise.com', '@href') . "]")->length === 0) {
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

        $cash = $this->http->FindSingleNode("//text()[" . $this->contains('Available Cash Back Rewards') . "]", null, true,
            '/^\s*([-]?\\$\s*\d[\d\.]+)\s*Available Cash Back Rewards\s*$/');

        $name = $this->http->FindSingleNode("//text()[" . $this->contains('Available Cash Back Rewards') . "]/preceding::text()[normalize-space()][1][" . $this->starts('Hi,') . "]", null, true,
            '/^\s*Hi, ([[:alpha:] \-]+)\s*$/');

        if (!empty($name) && !empty($cash)) {
            $st
                ->addProperty("Name", $name)
                ->setBalance(preg_replace('/\s*\\$\s*/', '', $cash))
            ;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
