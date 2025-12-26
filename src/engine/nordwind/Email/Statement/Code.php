<?php

namespace AwardWallet\Engine\nordwind\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Code extends \TAccountChecker
{
    public $mailFiles = "nordwind/statements/it-126708566.eml, nordwind/statements/it-187141406.eml";

    public $lang;
    public static $dictionary = [
        'ru' => [
            //            'Confirmation' => 'Confirmation',
        ],

        'en' => [
            'Подтвердите свою электронную почту с помощью кода' => 'Confirm your email with the following code',
        ],
    ];

    private $detectFrom = "@nordwindairlines."; //n4.support@nordwindairlines.ru
    private $detectSubject = [
        // ru
        'Код для входа: ',
        'Auth code:',
    ];

    private $detectBody = [
        'ru' => [
            'Подтвердите свою электронную почту с помощью кода',
        ],
        'en' => [
            'Confirm your email with the following code',
        ],
    ];

    // Main Detects Methods
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[{$this->contains(['© Nordwind Airlines. All rights reserved'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
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
        $code = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Подтвердите свою электронную почту с помощью кода")) . "]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{6})\s*$/");

        if (!empty($code)) {
            $st = $email->add()->statement();
            $st
                ->setMembership(true);

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
}
