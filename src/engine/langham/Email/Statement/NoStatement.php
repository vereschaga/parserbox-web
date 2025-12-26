<?php

namespace AwardWallet\Engine\langham\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NoStatement extends \TAccountChecker
{
    public $mailFiles = "langham/statements/it-649968843.eml, langham/statements/it-658357015.eml";

    public static $dictionary = [
        "en" => [
        ],
        "zh" => [
            'This email was sent by: Brilliant Loyalty Program Limited'   => '朗廷卓逸會的所有網上通訊及相關的市場推廣電郵由 Brilliant Loyalty Program Limited 發出',
            'Brilliant Loyalty Program Limited. All Rights Reserved'      => 'Brilliant Loyalty Program Limited 版權所有。',
            'This verification code allows you to sign in to the account' => '使用此验证码登录账户。该密码仅供您查看，请勿与他人分享。',
            'Verification Code:'                                          => '验证码:',
        ],
    ];

    private $subjects = [
        'en' => ['Your One-Time Password', 'Your One-Time Verification Code'],
        'zh' => ['一次性密码 (OTP)'],
    ];

    private $detectLang = [
        "en" => ['Verification Code:'],
        "zh" => ['验证码:'],
    ];

    private $lang = '';

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query("//a[contains(@href,'.e-langhamhotels.com/') or contains(@href,'view.e-langhamhotels.com') or contains(@href,'click.e-langhamhotels.com')]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains($this->t('This email was sent by: Brilliant Loyalty Program Limited'))}]")->length === 0
            && $this->http->XPath->query("//text()[starts-with(normalize-space(),'©') and {$this->contains($this->t('Brilliant Loyalty Program Limited. All Rights Reserved'))}]")->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@e-brilliantbylangham.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $verificationCode = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Verification Code:'))}]", null, true, "/^{$this->opt($this->t('Verification Code:'))}[:\s]*(\d{3,})$/i");

        if ($verificationCode !== null) {
            $code = $email->add()->oneTimeCode();
            $code->setCode($verificationCode);
        }

        if ($this->isMembership()) {
            $st = $email->add()->statement();
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        $phrases = [
            'This verification code allows you to sign in to the account',
            '使用此验证码登录账户。该密码仅供您查看，请勿与他人分享。',
        ];

        foreach ($phrases as $phrase) {
            if ($this->http->XPath->query("//text()[{$this->contains($phrase)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
