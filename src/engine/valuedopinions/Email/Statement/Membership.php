<?php

namespace AwardWallet\Engine\valuedopinions\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Membership extends \TAccountChecker
{
    public $mailFiles = "valuedopinions/statements/it-114472894.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = '@valuedopinions.com';

    private $detectBody = [
        'You have received this email as you are a member of Valued Opinions'
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->striposAll($headers['from'], $this->detectFrom) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailByHeaders($parser->getHeaders()) !== true) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if (!empty($this->http->XPath->query("//text()[" . $this->contains($dBody) . "]")->length > 0)) {
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

        $name = $this->http->FindSingleNode("//text()[".$this->contains([', You have new surveys available', ', you have new surveys available.'])."]", null, true,
            "/^\s*([[:alpha:]]+), You have new surveys available/i");
        if (!empty($name)) {
            $st->addProperty('Name', $name);
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
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
