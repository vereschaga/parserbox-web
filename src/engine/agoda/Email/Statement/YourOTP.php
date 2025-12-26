<?php

namespace AwardWallet\Engine\agoda\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourOTP extends \TAccountChecker
{
    public $mailFiles = "agoda/statements/it-340104515.eml";

    public $detectFrom = "no-reply@agoda.com";
    public $detectSubject = [
        'Email OTP',
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
            'your Agoda OTP is' => 'your Agoda OTP is',
        ],
        'ja' => [
            'your Agoda OTP is' => 'アゴダのワンタイムパスワードは',
        ],
        'zh' => [
            'your Agoda OTP is' => ['你好！你的Agoda驗證碼為', '你的Agoda OTP驗證碼為'],
        ],
        'ko' => [
            'your Agoda OTP is' => '[아고다] 고객님의 아고다 OTP 번호:',
        ],
        'es' => [
            'your Agoda OTP is' => 'Hola, tu OTP de Agoda es',
        ],
        'pt' => [
            'your Agoda OTP is' => 'Olá, a sua OTP Agoda é',
        ],
        'tr' => [
            'your Agoda OTP is' => 'Merhaba, Agoda Tek Kullanımlık Şifreniz',
        ],
        'ru' => [
            'your Agoda OTP is' => 'Привет! Ваш одноразовый пароль Agoda:',
        ],
        'nl' => [
            'your Agoda OTP is' => 'Hallo, uw Agoda-verificatiecode is',
        ],
        'de' => [
            'your Agoda OTP is' => 'Hi, das OTP von Agoda lautet',
        ],
        'id' => [
            'your Agoda OTP is' => 'Halo, OTP Agoda Anda adalah',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return $this->detectEmailFromProvider($headers['from']) && strpos($headers['subject'], 'Email OTP') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query("/a/@href[{$this->contains(['.agoda.com/', 'www.agoda.com'])}] | //img/@src[{$this->contains(['.agoda.com/', '.agoda.net/images'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Agoda Company Pte. Ltd., 30 Cecil Street,'])}]")->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@security.agoda.com') !== false || stripos($from, 'no-reply@agoda.com') !== false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('OTP not found!');

            return $email;
        }
        $root = $roots->item(0);

        $otpValue = $this->http->FindSingleNode('.', $root, true, "/{$this->opt($this->t('your Agoda OTP is'))}\s*(\d+)(?:\s*[,.。\(，]|$|です。)/u");

        if ($otpValue) {
            $otp = $email->add()->oneTimeCode();
            $otp->setCode($otpValue);
        }

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

    private function findRoot(): \DOMNodeList
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['your Agoda OTP is'])) {
                $roots = $this->http->XPath->query("//tr[not(.//tr) and {$this->contains($dict['your Agoda OTP is'])}]");

                if ($roots->length > 0) {
                    $this->lang = $lang;

                    return $roots;
                }
            }
        }

        return new \DOMNodeList();
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

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
