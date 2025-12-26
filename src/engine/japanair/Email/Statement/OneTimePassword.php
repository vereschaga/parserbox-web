<?php

namespace AwardWallet\Engine\japanair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OneTimePassword extends \TAccountChecker
{
    public $mailFiles = "japanair/statements/it-712231749.eml, japanair/statements/it-712536058.eml";

    public $detectFrom = "jmb_confirmation@jal.com";
    public $detectSubject = [
        // en
        '[JAL] One-Time Password Notification',
        // ja
        '[JAL] ワンタイムパスワード通知',
        // zh
        '[JAL] 一次性密碼通知',
        // ko
        '[JAL] 일회용 비밀번호 알림',
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
            'Please enter the above one-time password on the screen to verify your identity.'
                             => 'Please enter the above one-time password on the screen to verify your identity.',
            'Japan Airlines' => 'Japan Airlines',
        ],
        'ja' => [
            'Please enter the above one-time password on the screen to verify your identity.'
                             => 'ご本人様確認のため、上記のワンタイムパスワードを画面にご入力ください。',
            'Japan Airlines' => '日本航空',
        ],
        'zh' => [
            'Please enter the above one-time password on the screen to verify your identity.'
                             => '請在畫面上輸入上方的一次性密碼，以驗證您的身分。',
            'Japan Airlines' => '日本航空',
        ],
        'ko' => [
            'Please enter the above one-time password on the screen to verify your identity.'
                             => '본인 확인을 위해 위의 일회용 비밀번호를 입력하십시오.',
            'Japan Airlines' => '일본항공',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[{$this->contains(['Japan Airlines'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Japan Airlines'])
                && !empty($dict['Please enter the above one-time password on the screen to verify your identity.'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Japan Airlines'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Please enter the above one-time password on the screen to verify your identity.'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Please enter the above one-time password on the screen to verify your identity.'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Please enter the above one-time password on the screen to verify your identity.'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email)
    {
        $code = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Please enter the above one-time password on the screen to verify your identity.")) . "]",
            null, true, "/^\s*(\d{6})\s*{$this->opt($this->t('Please enter the above one-time password on the screen to verify your identity.'))}/");

        if (empty($code)) {
            $code = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Please enter the above one-time password on the screen to verify your identity.")) . "]/preceding::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{6})\s*$/");
        }

        if (!empty($code)) {
            $otc = $email->add()->oneTimeCode();
            $otc->setCode($code);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
