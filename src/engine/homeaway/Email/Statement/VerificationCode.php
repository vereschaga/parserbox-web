<?php

namespace AwardWallet\Engine\homeaway\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "homeaway/statements/it-620551428.eml";
    private $detectSubjects = [
        // en
        'is your secure sign in code',
    ];

    private $lang = 'en';

    private static $dictionary = [
        'en' => [
            "Your secure code expires in 15 minutes." => "Your secure code expires in 15 minutes.",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        // foreach (self::$dictionary as $lang => $dict) {
        //     if (!empty($dict["Your secure code expires in 15 minutes."]) && $this->http->XPath->query("//text()[" . $this->starts($dict["Your secure code expires in 15 minutes."]) . "]")->length > 0) {
        //         $this->lang = $lang;
        //
        //         break;
        //     }
        // }
        //
        // if (empty($this->lang)) {
        //     return $email;
        // }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $detectedSubject = false;

        foreach ($this->detectSubjects as $subject) {
            if (stripos($parser->getSubject(), $subject) !== false) {
                $detectedSubject = true;
            }
        }

        if ($detectedSubject === false) {
            return $email;
        }

        $code = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your secure code expires in 15 minutes.'))}]/following::text()[normalize-space()][1]", null, true,
            "/^\s*(\d{6})\s*$/u");

        if (!empty($code)) {
            $email->add()->oneTimeCode()->setCode($code);

            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]", null, true,
                "/^\s*{$this->opt($this->t('Hi '))}([[:alpha:] \-]+),\s*$/u");

            if (!empty($name)) {
                $st = $email->add()->statement();
                $st
                    ->setMembership(true)
                    ->setNoBalance(true)
                    ->addProperty('Name', $name);
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]vrbo\.com\b/i', $from) > 0;
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
                $this->http->XPath->query("//img[contains(@src, '.homeaway.com')]")->length > 0
                && !empty($dict["Your secure code expires in 15 minutes."]) && $this->http->XPath->query("//text()[" . $this->contains($dict["Your secure code expires in 15 minutes."]) . "]")->length > 0
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
