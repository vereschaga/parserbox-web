<?php

namespace AwardWallet\Engine\sony\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class AccountMembership extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang;
    public static $dictionary = [
        'en' => [
            'last sing-in' => [
                'subject'    => 'Your Sony Sign-in Verification Code',
                'detectText' => 'Your Sony sign-in was just used as detailed below.',
            ],
            '2step Auth' => [
                'subject' => ['2-Step Verification Is Now Deactivated For Your Account',
                    '2-Step Verification is now activated for your account',
                ],
                'detectText' => ['You have deactivated 2-Step Verification for your account.',
                    'Your account is now more secure than ever.', ],
            ],
            'change password' => [
                'subject'    => 'Change Your Password',
                'detectText' => 'Password Change Notification',
            ],
        ],
        'it' => [
            'last sing-in' => [
                'subject'    => "Codice di verifica per l'accesso all'account Sony",
                'detectText' => 'Le tue credenziali di accesso Sony sono state appena utilizzate.',
            ],
            '2step Auth' => [
                'subject'    => 'La verifica in 2 passaggi è ora attiva per il tuo account',
                'detectText' => 'Il tuo account è ora più sicuro che mai.',
            ],
            'change password' => [
                'subject'    => 'La tua password è stata aggiornata',
                'detectText' => 'Notifica di modifica della password',
            ],
        ],
        'pt' => [
            'last sing-in' => [
                'subject' => ['Seu código de verificação de início de sessão da Sony',
                    'Código de confirmação do seu início de sessão da Sony', ],
                'detectText' => [
                    'Sua ID de início de sessão da Sony acabou de ser usada conforme descrito a seguir.',
                    'O seu início de sessão da Sony acabou de ser utilizado tal como especificado abaixo.',
                ],
            ],
            '2step Auth' => [
                'subject' => ['Agora a verificação em duas etapas está ativada para a sua conta',
                    'A confirmação de 2 etapas está agora ativada para a sua conta', ],
                'detectText' => ['Agora sua conta está mais segura do que nunca.',
                    'A sua conta está agora mais protegida do que nunca.',
                    'Sua conta agora está mais segura do que nunca.',
                ],
            ],
            'change password' => [
                'subject'    => 'Passwort ändern',
                'detectText' => 'Mitteilung über Passwortänderung',
            ],
        ],
        'de' => [
            'last sing-in' => [
                'subject'    => 'Dein Verifizierungscode für die Anmeldung bei Sony',
                'detectText' => 'Deine Sony-Anmeldedaten wurden eben wie folgt verwendet.',
            ],
            '2step Auth' => [
                'subject'    => 'Die zweistufige Verifizierung ist jetzt in deinem Konto aktiv',
                'detectText' => 'Dein Konto ist jetzt noch sicherer als je zuvor.',
            ],
            'change password' => [
                // 'subject' => '',
                // 'detectText' => '',
            ],
        ],
        'sv' => [
            'last sing-in' => [
                'subject'    => 'Din verifieringskod för Sony-inloggning',
                'detectText' => 'Dina inloggningsuppgifter hos Sony användes nyss enligt nedan.',
            ],
            '2step Auth' => [
                'subject'    => 'Nu är 2-stegsverifiering aktiverad på ditt konto',
                'detectText' => 'Ditt konto är nu säkrare än någonsin.',
            ],
            'change password' => [
                'subject'    => 'Ändra ditt lösenord',
                'detectText' => 'Avisering om ändring av lösenord',
            ],
        ],
        'es' => [
            'last sing-in' => [
                'subject' => ['Tu código de verificación para iniciar sesión en Sony',
                    'Tu código de verificación de inicio de sesión de Sony', ],
                'detectText' => ['Alguien acaba de usar su inicio de sesión de Sony tal y como se detalla a continuación.',
                    'Alguien acaba de usar su inicio de sesión de Sony como se detalla a continuación.', ],
            ],
            '2step Auth' => [
                'subject' => ['Se activó la verificación en dos pasos en tu cuenta',
                    'La verificación en dos pasos está desactivada en tu cuenta',
                    'La verificación en dos pasos está ahora activada en tu cuenta',
                ],
                'detectText' => ['Tu cuenta ahora está más segura que nunca.',
                    'Has desactivado la verificación en dos pasos de tu cuenta.',
                    'Tu cuenta está ahora más protegida que nunca.',
                ],
            ],
            'change password' => [
                'subject'    => ['Cambia tu contraseña', 'Solicitud para restablecer tu contraseña'],
                'detectText' => ['Notificación de cambio de contraseña', 'Solicitud para restablecer tu contraseña'],
            ],
        ],
        'fr' => [
            'last sing-in' => [
                'subject'    => 'Votre code de vérification de connexion Sony',
                'detectText' => ['Votre connexion Sony vient d\'être utilisée comme expliqué ci-dessous.',
                    'Vos données de connexion Sony viennent d’être utilisées de la façon détaillée ci-dessous', ],
            ],
            '2step Auth' => [
                // 'subject' => '',
                // 'detectText' => '',
            ],
            'change password' => [
                'subject'    => ['Changez votre mot de passe', 'Votre mot de passe a été changé'],
                'detectText' => ['Notification de changement du mot de passe',
                    'Notification de changement du mot de passe', ],
            ],
        ],

        'ko' => [
            'last sing-in' => [
                'subject'    => 'Sony 로그인 인증 코드',
                'detectText' => '회원님의 Sony 로그인이 밑에 표시된 바와 같이 지금 막 사용되었습니다.',
            ],
            '2step Auth' => [
                // 'subject' => '',
                // 'detectText' => '',
            ],
            'change password' => [
                // 'subject' => '',
                // 'detectText' => '',
            ],
        ],
        'ar' => [
            'last sing-in' => [
                'subject'    => 'رمز التحقق لتسجيل الدخول إلى Sony‏',
                'detectText' => 'تم استخدام بيانات تسجيل دخولك إلى Sony كما هو مفصل أدناه.',
            ],
            '2step Auth' => [
                // 'subject' => '',
                // 'detectText' => '',
            ],
            'change password' => [
                // 'subject' => '',
                // 'detectText' => '',
            ],
        ],
        'ru' => [
            'last sing-in' => [
                'subject'    => 'Ваш код подтверждения для входа в сеть Sony',
                'detectText' => 'Только что был выполнен вход в сеть Sony с вашими данными, как указано ниже.',
            ],
            '2step Auth' => [
                // 'subject' => '',
                // 'detectText' => '',
            ],
            'change password' => [
                // 'subject' => '',
                // 'detectText' => '',
            ],
        ],
        'pl' => [
            'last sing-in' => [
                'subject'    => 'Kod weryfikacyjny wpisu do usług Sony',
                'detectText' => 'Ktoś właśnie użył Twojego wpisu do konta Sony — szczegóły znajdziesz poniżej.',
            ],
            '2step Auth' => [
                'subject'    => 'Aktywowano weryfikację dwuetapową na koncie',
                'detectText' => 'Twoje konto jest teraz jeszcze bezpieczniejsze.',
            ],
            'change password' => [
                'subject'    => 'Zmiana hasła',
                'detectText' => 'Powiadomienie o zmianie hasła',
            ],
        ],
        'nl' => [
            'last sing-in' => [
                'subject'    => 'Je verificatiecode voor je Sony-aanmelding',
                'detectText' => 'Je hebt je zojuist bij Sony aangemeld.',
            ],
            '2step Auth' => [
                // 'subject' => '',
                // 'detectText' => '',
            ],
            'change password' => [
                'subject'    => 'Je wachtwoord wijzigen',
                'detectText' => 'Melding over wachtwoordwijziging',
            ],
        ],
        'fi' => [
            'last sing-in' => [
                'subject'    => 'Sony-sisäänkirjautumisen varmistuskoodi',
                'detectText' => 'Sony-sisäänkirjautumistunnustasi käytettiin juuri.',
            ],
            '2step Auth' => [
                // 'subject' => '',
                // 'detectText' => '',
            ],
            'change password' => [
                'subject'    => 'Vaihda salasana',
                'detectText' => 'Ilmoitus salasanan vaihtamisesta',
            ],
        ],
        'no' => [
            'last sing-in' => [
                'subject'    => 'Din Sony bekreftelseskode for pålogging',
                'detectText' => 'Sony-påloggingen din ble nettopp brukt som beskrevet nedenfor.',
            ],
            '2step Auth' => [
                // 'subject' => '',
                // 'detectText' => '',
            ],
            'change password' => [
                'subject'    => 'Endre passordet ditt',
                'detectText' => 'Varsel om passordendring',
            ],
        ],
        'zh' => [
            'last sing-in' => [
                'subject'    => ['您的Sony登入驗證代碼', ' 索尼登陆最新资讯', '您的Sony登录验证码'],
                'detectText' => [
                    '剛才有人登入了您的Sony帳戶，詳情如下。',
                    '您刚刚的索尼登陆详情如下。',
                    '您的Sony登录信息刚刚被使用过，详情如下。',
                ],
            ],
            '2step Auth' => [
                // 'subject' => '',
                // 'detectText' => '',
            ],
            'change password' => [
                'subject'    => '變更您的密碼',
                'detectText' => '密碼變更通知',
            ],
        ],
        'tr' => [
            'last sing-in' => [
                'subject'    => 'Sony Giriş Doğrulama Kodunuz',
                'detectText' => 'Sony şifreniz az önce kullanıldı. Buna ilişkin ayrıntıları aşağıda görebilirsiniz',
            ],
            '2step Auth' => [
                // 'subject' => '',
                // 'detectText' => '',
            ],
            'change password' => [
                'subject'    => 'Şifrenizi Değiştirin',
                'detectText' => 'Şifre Değiştirme Bildirimi',
            ],
        ],
        'ja' => [
            'last sing-in' => [
                'subject'    => 'ソニーアカウントのサインイン確認コード',
                'detectText' => 'あなたのソニーアカウントのサインイン情報が以下の通り使用されました。',
            ],
            '2step Auth' => [
                // 'subject' => '',
                // 'detectText' => '',
            ],
            'change password' => [
                // 'subject' => '',
                // 'detectText' => '',
            ],
        ],
        // '' => [
        //     'last sing-in' => [
        //         // 'subject' => '',
        //         // 'detectText' => '',
        //     ],
        //     '2step Auth' => [
        //         // 'subject' => '',
        //         // 'detectText' => '',
        //     ],
        //     'change password' => [
        //         // 'subject' => '',
        //         // 'detectText' => '',
        //     ],
        // ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!preg_match('/\bsony@(?:[^@\s]+\.)?account\.sony\.com$/i', rtrim($headers['from'], '> '))) {
            return false;
        }

        foreach (self::$dictionary as $lang => $types) {
            foreach ($types as $type) {
                if (!empty($type['subject'])
                    && $this->containsText($headers['subject'], $type['subject']) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectText($parser);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]sony\.com$/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectText($parser) === true) {
            $st = $email->add()->statement();
            $st->setMembership(true);

            $class = explode('\\', __CLASS__);
            $email->setType(end($class) . ucfirst($this->lang));
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

    private function detectText($parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".sony.com/") or contains(@href,"account.sony.com") or contains(@href,"rewards.sony.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") or contains(normalize-space(),"Sony Corporation")]')->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $lang => $types) {
            foreach ($types as $type) {
                if (!empty($type['subject'])
                    && $this->containsText($parser->getSubject(), $type['subject']) !== false
                    && !empty($type['detectText'])
                    && $this->http->XPath->query("//text()[{$this->contains($type['detectText'])}]")->length > 0
                    && empty(array_filter($this->http->FindNodes("//text()[{$this->contains($type['detectText'])}]/preceding::text()[normalize-space()][not(ancestor::style)][position() < 3]", null, "/\d{6,7}/")))
                    && empty(array_filter($this->http->FindNodes("//text()[{$this->contains($type['detectText'])}]/following::text()[normalize-space()][not(ancestor::style)][position() < 3]", null, "/\d{6,7}/")))
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
