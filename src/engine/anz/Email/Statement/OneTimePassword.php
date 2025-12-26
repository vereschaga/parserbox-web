<?php

namespace AwardWallet\Engine\anz\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OneTimePassword extends \TAccountChecker
{
	public $mailFiles = "anz/statements/it-882978712.eml";
    public $subjects = [
        'Here’s your One-Time Password',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'detectPhrase' => "Here is a One-Time Password (OTP) from ANZ Rewards. Please use it to proceed:",
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'anzrewards.com') !== false) {
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
        if ($this->http->XPath->query("//a/@href[{$this->contains(['anz.com'])}]")->length === 0
            || $this->http->XPath->query("//*[{$this->contains(['Australia and New Zealand Banking Group Limited', 'ANZ', 'ANZ Rewards'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['detectPhrase']) && $this->http->XPath->query("//*[{$this->contains($dict['detectPhrase'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]anzrewards\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $code = $this->http->FindSingleNode("//text()[{$this->eq($this->t('detectPhrase'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d{6})\s*$/");

        if (!empty($code)) {
            $otc = $email->add()->oneTimeCode();
            $otc->setCode($code);
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

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
                return 'contains(' . $text . ',"' . $s . '")';
            }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
                return 'normalize-space(.)="' . $s . '"';
            }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
                return 'starts-with(normalize-space(.),"' . $s . '")';
            }, $field)) . ')';
    }
}
