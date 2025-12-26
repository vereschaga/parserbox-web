<?php

namespace AwardWallet\Engine\saudisrabianairlin\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OneTimeCode extends \TAccountChecker
{
    public $mailFiles = "saudisrabianairlin/statements/it-541480045.eml";
    public $subjects = [
        // ar
        'تفعيل عضوية',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "ar" => [
            'We look forward to welcoming you soon on board one of SAUDIA flights.' => 'نتطلع للترحيب بك قريباً على متن إحدى رحلات "السعودية".',
            'Please find the one-time password code below:'                         => 'يرجى العثور على رمز كلمة المرور لمرة واحدة أدناه:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@saudia.com') !== false) {
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
        if ($this->http->XPath->query("//a[contains(@href, '.saudia.com')]")->length > 0) {
            foreach (self::$dictionary as $lang => $dict) {
                if (!empty($dict['We look forward to welcoming you soon on board one of SAUDIA flights.']) && !empty($dict['Please find the one-time password code below:'])
                    // && $this->http->XPath->query("//text()[{$this->contains($dict['We look forward to welcoming you soon on board one of SAUDIA flights.'])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($dict['Please find the one-time password code below:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]saudia\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) == true) {
            $st = $email->add()->statement();
            $st->setMembership(true);

            $otc = $email->add()->oneTimeCode();
            $otc->setCode($this->http->FindSingleNode("//text()[{$this->eq($this->t('Please find the one-time password code below:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{6})\s*$/"));
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
            return "starts-with(normalize-space(.), '{$s}')";
        }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return "normalize-space(.)='" . $s . "'"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), '{$s}')";
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
