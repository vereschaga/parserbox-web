<?php

namespace AwardWallet\Engine\gmrewards\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "gmrewards/statements/it-649119587.eml";
    private $detectSubjects = [
        'GM Account: Verification Code',
    ];

    private $lang = '';

    private static $dictionary = [
        'en' => [
            "Here's your verification code:" => "Here's your verification code:",
            "providerDetect"                 => ["GM Security Team", "General Motors. All Rights Reserved"],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Here's your verification code:"]) && $this->http->XPath->query("//text()[" . $this->starts($dict["Here's your verification code:"]) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $code = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Here's your verification code:"))}]/following::text()[normalize-space()][1]", null, true,
            "/^\s*(\d{6})\s*$/u");

        if (!empty($code)) {
            $email->add()->oneTimeCode()->setCode($code);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]account\.gm\.com\b/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from'])) {
            foreach ($this->detectSubjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (
                !empty($dict["providerDetect"]) && $this->http->XPath->query("//text()[" . $this->contains($dict["providerDetect"]) . "]")->length > 0
                && !empty($dict["Here's your verification code:"]) && $this->http->XPath->query("//text()[" . $this->contains($dict["Here's your verification code:"]) . "]")->length > 0
            ) {
                return true;
            }
        }

        return false;
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
