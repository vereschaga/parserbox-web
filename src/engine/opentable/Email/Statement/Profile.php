<?php

namespace AwardWallet\Engine\opentable\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Profile extends \TAccountChecker
{
    public $mailFiles = "opentable/statements/it-63791546.eml, opentable/statements/it-64258345.eml, opentable/statements/it-64259292.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = ["@opentable.", ".opentable."];
    private $detectSubjects = [
        "Your midyear dining stats check in!",
        " dining stats: How'd you fare?",
        ", welcome to OpenTable!",
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubjects) {
            if (stripos($headers['subject'], $dSubjects) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getCleanFrom()) === false) {
            return false;
        }

        if (!empty($this->http->FindSingleNode("//td[not(normalize-space()) and .//img[contains(@src, '/Metros_Light.png') or contains(@src, 'opentable.com/INTL/Icons/header_icon.png')]]/following-sibling::td[1][contains(.,'Update')]", null, true, "/Update\)?\s*$/"))
                && !empty($this->http->FindSingleNode("//a[normalize-space() = 'Update your profile' and contains(@href, '.opentable.com')]"))) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $st->setMembership(true);

        $points = $this->http->FindSingleNode("//text()[contains(., ', you have') and contains(., 'Dining Points.')]", null, true, "/^\w+, you have (\d[\d,]*) Dining Points\./");

        if ($points !== null) {
            $st->setBalance(str_replace(',', '', $points));
            $st->addProperty('Name', $this->http->FindSingleNode("//text()[contains(., ', you have') and contains(., 'Dining Points.')]", null, true, "/^(\w+), you have \d[\d,]* Dining Points\./"));
        }
        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class));

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

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
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
