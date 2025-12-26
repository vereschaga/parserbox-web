<?php

namespace AwardWallet\Engine\sony\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "sony/statements/it-436425696.eml";

    public $detectSubject = [
        // en
        'Your Sony Sign-in Verification Code',
        // fr
        'Votre code de vérification de connexion Sony',
        // pt
        'Seu código de verificação de início de sessão da Sony',
        'O seu código de confirmação de início de sessão da Sony',
        // da
        'Din bekræftelseskode til Sony-logon',
        // de
        'Dein Sony-Verifizierungscode für die Anmeldung',
        // ar
        'رمز التحقق لتسجيل الدخول إلى Sony‏',
        // ru
        'Ваш код подтверждения для входа в сеть Sony',
        // es
        'Su código de verificación de inicio de sesión de Sony',
        'Su código de verificación de inicio de sesión en Sony',
        // it
        'Codice di verifica per l\'accesso a Sony',
        // pl
        'Twój kod weryfikacyjny wpisu do konta Sony',
        // zh
        '您的Sony登入驗證代碼',
        '您的Sony登录验证码',
        // uk
        'Ваш код підтвердження входу до Sony',
        // tr
        'Sony Giriş Doğrulama Kodunuz',
        // sv
        'Din verifieringskod för inloggning hos Sony',
        // ko
        'Sony 로그인 인증 코드',
        // nl
        'Je Sony-verificatiecode',
        // ja
        'ソニーのサインイン確認コード',
        // no
        'Bekreftelseskoden for Sony-pålogging',
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
            'is your verification code' => 'is your verification code',
        ],
        'fr' => [
            'is your verification code' => ['est le code de vérification', 'est votre code de vérification'],
        ],
        'pt' => [
            'is your verification code' => ['é o código de verificação', 'O código de confirmação da sua conta da Sony é'],
        ],
        'da' => [
            'is your verification code' => 'er din bekræftelseskode',
        ],
        'de' => [
            'is your verification code' => 'ist der Verifizierungscode',
        ],
        'ar' => [
            'is your verification code' => 'هو رمز التحقق لحساب',
        ],
        'ru' => [
            'is your verification code' => '— код подтверждения',
        ],
        'es' => [
            'is your verification code' => [
                'El código de verificación para su cuenta de Sony es:',
                'es el código de verificación',
            ],
        ],
        'it' => [
            'is your verification code' => 'è il codice di verifica',
        ],
        'pl' => [
            'is your verification code' => 'Oto Twój kod weryfikacyjny do konta Sony:',
        ],
        'zh' => [
            'is your verification code' => [
                '是您的Sony帳戶驗證代碼',
                '是您的Sony账号的验证码。',
            ],
        ],
        'uk' => [
            'is your verification code' => '– це код підтвердження',
        ],
        'tr' => [
            'is your verification code' => 'Sony hesabınız için doğrulama kodunuzdur',
        ],
        'sv' => [
            'is your verification code' => 'är verifieringskoden för',
        ],
        'ko' => [
            'is your verification code' => '회원님의 Sony 계정 인증 코드는',
        ],
        'nl' => [
            'is your verification code' => 'De verificatiecode voor je Sony-account is',
        ],
        'ja' => [
            'is your verification code' => 'あなたのソニーアカウントの確認コードは',
        ],
        'no' => [
            'is your verification code' => 'er bekreftelseskoden',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!preg_match('/\bsony@(?:[^@\s]+\.)?account\.sony\.com$/i', rtrim($headers['from'], '> '))) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".sony.com/") or contains(@href,"account.sony.com") or contains(@href,"rewards.sony.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") or contains(normalize-space(),"Sony Corporation")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]sony\.com$/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $verificationCode = $this->http->FindSingleNode('.', $root, true, "/^\s*(\d{3,})\s*{$this->opt($this->t('is your verification code'))}/i");

        if (empty($verificationCode)) {
            $verificationCode = $this->http->FindSingleNode('.', $root, true,
                "/^\s*{$this->opt($this->t('is your verification code'))}\s*(\d{3,})\s*(?:\.|\D+$)/ui");
        }

        if ($verificationCode) {
            $otc = $email->add()->oneTimeCode();
            $otc->setCode($verificationCode);

            $st = $email->add()->statement();
            $st->setMembership(true);
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
        if (!empty($this->lang)) {
            return $this->http->XPath->query("descendant::*[{$this->contains($this->t('is your verification code'))}][last()]");
        } else {
            foreach (self::$dictionary as $lang => $dict) {
                if (!empty($dict['is your verification code'])
                    && $this->http->XPath->query("descendant::*[{$this->contains($dict['is your verification code'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return $this->http->XPath->query("descendant::*[{$this->contains($dict['is your verification code'])}][last()]");
                }
            }
        }

        return new \DOMNodeList();
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

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
