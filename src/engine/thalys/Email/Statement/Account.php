<?php

namespace AwardWallet\Engine\thalys\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Account extends \TAccountChecker
{
    public $mailFiles = "thalys/it-229718898.eml, thalys/statements/it-65481399.eml, thalys/statements/it-65483740.eml, thalys/statements/it-65483792.eml, thalys/statements/it-65895136.eml, thalys/statements/it-68087002.eml";

    public static $dictionary = [
        'en' => [
            "My account" => ["My account", "My Account"],
            //            "You have" => "",
            "account creation" => "account creation",
            //            "Your number:" => "",
            //            "Start earning Miles" => "",
        ],
        'fr' => [
            "My account" => "Mon compte",
            "You have"   => "Vous avez",
            //            "account creation" => "",
            //            "Your number:" => "",
        ],
        'nl' => [
            "My account" => "Mijn account",
            "You have"   => "U heeft",
            //            "account creation" => "",
            //            "Your number:" => "",
        ],
    ];

    private $detectFrom = ["thalys@campaigns.thalys.com"];

    private $detectSubjects = [
        // en
        "You can count on us",
        // fr
        "Vous pouvez compter sur nous",
        // nl
        "U kunt op ons rekenen",
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

        foreach (self::$dictionary as $lang => $t) {
            if (!empty($t['My account']) && !empty($this->http->FindSingleNode("(//*[self::td or self::th][" . $this->eq(preg_replace("/(.+)/", '$1 >', $t["My account"])) . "])[1]"))) {
                return true;
            }

            if (!empty($t['account creation']) && !empty($this->http->FindSingleNode("//text()[" . $this->eq($t["account creation"]) . "]"))) {
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
        foreach (self::$dictionary as $lang => $t) {
            if (!empty($t['My account']) && !empty($this->http->FindSingleNode("//text()[" . self::eq($t["My account"]) . "]"))) {
                $this->lang = $lang;

                break;
            }

            if (!empty($t['account creation']) && !empty($this->http->FindSingleNode("//text()[" . self::eq($t["account creation"]) . "]"))) {
                $this->lang = $lang;

                break;
            }
            if (!empty($t['Start earning Miles']) && !empty($t['N°']) && !empty($this->http->FindSingleNode("//text()[" . self::eq($t["Start earning Miles"]) . "]/preceding::text()[normalize-space()][][" . self::starts(['N°']) . "]"))) {
                $this->lang = $lang;

                break;
            }
        }

        // used in thalys/YourTrip
        $this->parseStatement($email, $this);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseStatement(Email $email, \TAccountChecker $checker)
    {
        foreach (self::$dictionary as $klang => $t) {
            if (!empty($t['My account']) && !empty($checker->http->FindSingleNode("//text()[" . self::eq($t["My account"]) . "]"))) {
                $lang = $klang;

                break;
            }

            if (!empty($t['account creation']) && !empty($checker->http->FindSingleNode("//text()[" . self::eq($t["account creation"]) . "]"))) {
                $lang = $klang;

                break;
            }
        }

        if (empty($lang)) {
            $checker->logger->debug('Lang not detected');

            return false;
        }

        $st = $email->add()->statement();

        if (!empty($checker->http->FindSingleNode("//text()[" . self::eq(self::t("account creation", $lang)) . "]"))) {
            // Number
            $number = $checker->http->FindSingleNode("//text()[" . self::eq(self::t("Your number:", $lang)) . "]/following::text()[normalize-space()][1]",null, true,
                "/^\s*(\d{5,})\s*$/");
            $st->setNumber($number);
            $st->setNoBalance(true);

            return $email;
        }

        // Number
        $number = $checker->http->FindSingleNode("//a[" . self::eq(self::t("My account", $lang)) . "]/ancestor::*[self::td or self::th][1]/preceding-sibling::*[normalize-space()]//text()[" . self::starts(['N°']) . "]",null, true,
            "/^\s*N°\s*(\d{5,})\s*$/");

        if (empty($number)) {
            $number = $checker->http->FindSingleNode("//a[" . self::eq(preg_replace("/(.+)/", '$1 >', self::t("My account", $lang))) . "]/ancestor::*[self::td or self::th][2]/preceding-sibling::*[normalize-space()]//text()[" . self::starts(['N°']) . "]", null, true,
                "/^\s*N°\s*(\d{5,})\s*$/");
        }
        $st->setNumber($number);

        // Balance
        $balance = $checker->http->FindSingleNode("//a[" . self::eq(self::t("My account", $lang)) . "]/ancestor::*[self::td or self::th][1]/preceding-sibling::*[normalize-space()]//text()[" . self::eq(self::t("You have", $lang)) . "]/following::text()[normalize-space()][1]",null, true,
            "/^\s*(\d[\d,. ]*) Miles\s*$/");

        if (empty($balance)) {
            $balance = $checker->http->FindSingleNode("//a[" . self::eq(preg_replace("/(.+)/", '$1 >', self::t("My account", $lang))) . "]/ancestor::*[self::td or self::th][2]/preceding-sibling::*[normalize-space()]//text()[" . self::eq(self::t("You have", $lang)) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*(\d[\d,. ]*) Miles\s*$/");
        }

        if (strlen($balance) > 0) {
            $st->setBalance(str_replace([',', '.', ' '], '', $balance));
        } else {
            $st->setNoBalance(true);
        }

        // Name
        $name = $checker->http->FindSingleNode("//a[" . self::eq(self::t("My account", $lang)) . "]/ancestor::*[self::td or self::th][1]/preceding-sibling::*[normalize-space()]//text()[" . self::starts(['N°']) . "]/preceding::text()[normalize-space()][1]",null, true,
                "/^\s*([[:alpha:] \-]+)\s*$/u");

        if (empty($name)) {
            $name = $checker->http->FindSingleNode("//a[" . self::eq(preg_replace("/(.+)/", '$1 >', self::t("My account", $lang))) . "]/ancestor::*[self::td or self::th][2]/preceding-sibling::*[normalize-space()]//text()[" . self::starts(['N°']) . "]/preceding::text()[normalize-space()][1]",null, true,
                "/^\s*([[:alpha:] \-]+)\s*$/u");
        }
        $st->addProperty('Name', $name);

        return $email;
    }

    private static function t($word, $lang)
    {
        if (empty($lang)) {
            return null;
        }

        if (!isset(self::$dictionary[$lang]) || !isset(self::$dictionary[$lang][$word])) {
            return $word;
        }

        return self::$dictionary[$lang][$word];
    }

    private static function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private static function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private static function contains($field)
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
