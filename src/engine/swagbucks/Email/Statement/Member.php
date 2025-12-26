<?php

namespace AwardWallet\Engine\swagbucks\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// emails from noreply@swagbucks.com
class Member extends \TAccountChecker
{
    public $mailFiles = "swagbucks/statements/it-108263495.eml, swagbucks/statements/it-109460969.eml, swagbucks/statements/it-392460869.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (stripos($parser->getHeader('from'), 'noreply@swagbucks.com') === false
            || $this->http->XPath->query("//a[contains(@href, '.swagbucks.com')]")->length === 0
        ) {
            return false;
        }

        if ($this->http->XPath->query("//tr[count(*) = 2 and *[1][not(normalize-space())]//img[@alt = 'Swagbucks']][starts-with(normalize-space(), 'Hi, ')]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]swagbucks\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $node = implode("\n", $this->http->FindNodes("//tr[count(*) = 2 and *[1][not(normalize-space())]//img[@alt = 'Swagbucks']][starts-with(normalize-space(), 'Hi, ')]//text()[normalize-space()]"));
        // $this->logger->debug('$node = '.print_r( $node,true));

        // Hi,
        // Reagan!
        // Available Balance:
        // 311 SB

        // Hi, Vito
        // 123 SB
        $re = "/^\s*Hi\s*,\s*(?<name>[[:alpha:] \-]+)(!)?\s*\n\s*(?:Available Balance:\s*)?(?<balance>\d[\d,]*)\s*SB\s*$/u";

        if (preg_match($re, $node, $m)) {
            $st = $email->add()->statement();

            $st->setBalance(str_replace(',', '', $m['balance']));

            if (stripos($m['name'], 'Swagbucks') === false || stripos($m['name'], 'Member') === false) {
                $st->addProperty('Name', trim($m['name']));
            }
        }

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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
