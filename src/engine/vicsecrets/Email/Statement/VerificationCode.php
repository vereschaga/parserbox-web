<?php

namespace AwardWallet\Engine\vicsecrets\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "vicsecrets/statements/it-149553741.eml";
    public $subjects = [
        "Account Sign In: Confirm Your Identity",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Secret Victoria Silver Credit Card account is' => [
                "Secret Victoria Silver Credit Card account is",
                "Victoria's Secret Victoria Credit Card account is",
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && preg_match("/victoriassecret\D+[@.]account\.comenity\.net/", $headers['from'])) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Secret Credit Card Accounts')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Please confirm your identity')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Secret Victoria Silver Credit Card account is'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/victoriassecret\D+[@.]account\.comenity\.net$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Account Ending in')]/ancestor::tr[1]")->length > 0) {
            $st = $email->add()->statement();
            $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Account Ending in')]/ancestor::tr[1]", null, true, "/Account Ending in\s*(\d+)/");
            $st->setNumber('**' . $number)->masked('left');
            $st->setNoBalance(true);
        }

        $otc = $email->add()->oneTimeCode();
        $otc->setCode($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'To confirm your identity and authorize')]/preceding::text()[normalize-space()][1]", null, true, "/^(\d{8})$/"));

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
        return count(self::$dictionary);
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
}
