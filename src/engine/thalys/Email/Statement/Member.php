<?php

namespace AwardWallet\Engine\thalys\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Member extends \TAccountChecker
{
    public $mailFiles = "thalys/statements/it-65798682.eml";

    public static $dictionary = [
        'en' => [
            "Thalys No." => ["Thalys No.", "Thalys Nr."],
        ],
    ];

    private $detectFrom = ["thalysthecard@campaigns.thalys.com"];

    private $detectSubjects = [
        // en
        "Let’s improve your experience together",
    ];

    private $detectBody = [
        'en' => [
            "Thalys No.", "Thalys Nr.",
        ],
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return self::detectEmailFromProvider($headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getCleanFrom()) !== true) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($detectBody) . "]")->length > 0) {
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
        return 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
//        foreach (self::$dictionary as $lang => $t) {
//            if (!empty($t['Membership Number:']) && !empty($this->http->FindSingleNode("//text()[" . $this->eq($t["Membership Number:"]) . "]"))) {
//                $this->lang = $lang;
//                break;
//            }
//        }

        $st = $email->add()->statement();

        // Number
        $number = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Thalys No.")) . "]/following::text()[normalize-space()][1]",null, true,
            "/^\s*(\d{5,})\s*$/");
        $st->setNumber($number);

        // Balance
        $st->setNoBalance(true);

        // Name
        $name = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Thalys No.")) . "]/preceding::text()[normalize-space()][1]",null, true,
                "/^\s*(?:Mrs|Mr)\s+(.+)\s*$/");
        $st->addProperty('Name', $name);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
