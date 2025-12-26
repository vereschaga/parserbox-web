<?php

namespace AwardWallet\Engine\qmiles\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code extends \TAccountChecker
{
    public $mailFiles = "qmiles/it-636091293.eml, qmiles/statements/it-651715369.eml";
    public $subjects = [
        'Your OTP has arrived',
        'Your one-time pin',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Here\'s your OTP'                        => ['Here\'s your OTP', 'Here’s the OTP'],
            'please use the below one-time pin (OTP)' => ['please use the below one-time pin (OTP)', 'Please use the below one-time pin (OTP)'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@qatarairways.com.qa') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Qatar Airways')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t("Here's your OTP"))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('please use the below one-time pin (OTP)'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]qatarairways\.com\.qa$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $code = $this->http->FindSingleNode("//text()[{$this->contains($this->t('please use the below one-time pin (OTP)'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d{5,6})$/");

        if (!empty($code)) {
            $oc = $email->add()->oneTimeCode();
            $oc->setCode($code);
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
