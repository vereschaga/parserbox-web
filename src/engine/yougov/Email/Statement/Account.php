<?php

namespace AwardWallet\Engine\yougov\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Account extends \TAccountChecker
{
    public $mailFiles = "yougov/statements/it-112257795.eml, yougov/statements/it-80199084.eml, yougov/statements/it-81780745.eml, yougov/statements/it-82028887.eml, yougov/statements/it-82117152.eml, yougov/statements/it-82463708.eml, yougov/statements/it-82583767.eml, yougov/statements/it-82719297.eml, yougov/statements/it-82845125.eml, yougov/statements/it-82851075.eml, yougov/statements/it-82957286.eml, yougov/statements/it-83129485.eml, yougov/statements/it-83134430.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [ // it-82028887.eml, it-81780745.eml
            'mentionsAccount' => [
                'para fazer login na sua conta YouGov:',
                'Você está recebendo esse código porque seu e-mail foi cadastrado no site do YouGov',
                'Insira este código de verificação para iniciar sessão na sua conta YouGov:',
            ],

            'surveyDetect' => [
                'Você foi escolhido(a) para responder a uma pesquisa especial',
                'Você foi escolhido para responder uma pesquisa YouGov',
            ],
            'surveyButton' => 'Iniciar pesquisa',
            'unsubscribe'  => 'Cancelar inscrição',
            'emailStart'   => 'Este e-mail foi enviado para',
            // 'emailEnd' => '',
            'Enter this verification code to log in to your YouGov account:' => 'Insira este código de verificação para iniciar sessão na sua conta YouGov:',
        ],
        'ar' => [ // it-82851075.eml
            // 'mentionsAccount' => '',

            'surveyDetect' => [
                'لقد تم اختيارك لاستبيان خاص',
                'تم اختيارك لتأدية استطلاع يوجوف',
            ],
            'surveyButton' => 'ابدأ الاستطلاع',
            'unsubscribe'  => 'إلغاء الاشتراك',
            'emailStart'   => 'هذه الرسالة الإلكترونية مخصصة لـ',
            // 'emailEnd' => '',
            // 'Enter this verification code to log in to your YouGov account:' => '',
        ],
        'pl' => [ // it-82957286.eml
            // 'mentionsAccount' => '',

            'surveyDetect' => [
                'Chcielibyśmy zaprosić Cię do udziału w nowej ankiecie',
            ],
            'surveyButton' => 'Rozpocznij ankietę',
            'unsubscribe'  => 'Zrezygnuj',
            'emailStart'   => 'Ten email skierowany jest do',
            // 'emailEnd' => '',
            // 'Enter this verification code to log in to your YouGov account:' => '',
        ],
        'zh' => [ // it-82719297.eml
            // 'mentionsAccount' => '',

            'surveyDetect' => [
                '您有一份新YouGov问卷',
            ],
            'surveyButton' => '开始问卷',
            'unsubscribe'  => '退订',
            'emailStart'   => '收到这封邮件的原因是您已注册',
            // 'emailEnd' => '',
            // 'Enter this verification code to log in to your YouGov account:' => '',
        ],
        'nl' => [ // it-82463708.eml
            'mentionsAccount' => [
                'Activeer nu uw account om te beginnen met het invullen van enquêtes en het verdienen van punten.',
                'U heeft deze e-mail ontvangen omdat u zich heeft aangemeld om enquêtes van YouGov te ontvangen',
            ],

            // 'surveyDetect' => [
            //     '',
            // ],
            // 'surveyButton' => '',
            // 'unsubscribe' => '',
            'emailStart' => 'Deze e-mail is bedoeld voor',
            // 'emailEnd' => '',
            // 'Enter this verification code to log in to your YouGov account:' => '',
        ],
        'de' => [ // it-82117152.eml
            'mentionsAccount' => 'Sie erhalten diesen Code, weil Ihre E-Mail-Adresse auf der YouGov',

            'surveyDetect' => [
                'Sie wurden für eine YouGov Umfrage ausgewählt!',
                'Sie wurden für die Teilnahme an einer besonderen Umfrage ausgewählt',
                'Sie haben diese E-Mail erhalten, weil Sie sich dazu angemeldet haben, Umfragen von YouGov zu erhalten',
            ],
            'surveyButton' => 'Start Umfrage',
            'unsubscribe'  => 'Kündigen',
            'emailStart'   => 'Diese Email war für',
            'emailEnd'     => 'bestimmt',

            'Enter this verification code to log in to your YouGov account:' => 'Geben Sie diesen Bestätigungscode ein, um sich bei Ihrem YouGov-Konto anzumelden:',
        ],
        'fr' => [ // it-83134430.eml
            // 'mentionsAccount' => '',

            'surveyDetect' => [
                'Vous avez été sélectionné(e) pour un sondage YouGov',
            ],
            'surveyButton' => 'Démarrer le sondage',
            'unsubscribe'  => 'Vous désabonner',
            'emailStart'   => 'Ce message était destiné à',
            // 'emailEnd' => '',
            // 'Enter this verification code to log in to your YouGov account:' => '',
        ],
        'es' => [
            'mentionsAccount' => [
                'Ha recibido este correo electrónico porque se registró para recibir encuestas de YouGov',
                'YouGov no envía correos electrónicos que no haya solicitado. Recibió este mensaje porque se suscribió para recibir nuestras encuestas.',
                'Introduce el siguiente código de verificación para iniciar sesión en tu cuenta de YouGov:',
            ],

            'surveyDetect' => [
                '¡Podrá participar en una encuesta de YouGov!',
                '!Usted ha sido seleccionado/a para compartir sus opiniones en una nueva encuesta de YouGov!',
            ],
            'surveyButton' => ['Iniciar encuesta', 'Empezar encuesta'],
            'unsubscribe'  => 'Cancelar suscripción',
            'emailStart'   => ['Este correo fue enviado a', 'Este mensaje está dirigido a'],
            // 'emailEnd' => '',
            'Enter this verification code to log in to your YouGov account:' => 'Introduce el siguiente código de verificación para iniciar sesión en tu cuenta de YouGov:',
        ],
        'it' => [
            'mentionsAccount' => [
                'Hai ricevuto questa e-mail perchè ti sei iscritto/a per partecipare ai sondaggi di YouGov',
                'Inserisci questo codice di verifica per accedere al tuo account YouGov:',
            ],

            'surveyDetect' => [
                'Sei stato/a selezionato/a per partecipare ad un sondaggio di YouGov!',
            ],
            'surveyButton' => ['Inizia sondaggio'],
            'unsubscribe'  => 'Cancella Iscrizione',
            'emailStart'   => ['Questa e-mail è destinata a'],
            // 'emailEnd' => '',
            'Enter this verification code to log in to your YouGov account:' => 'Inserisci questo codice di verifica per accedere al tuo account YouGov:',
        ],
        'sv' => [
            'mentionsAccount' => [
                'Ange den här verifieringskoden för att skapa ditt YouGov-konto:',
            ],

            // 'surveyDetect' => [
            //     'Sei stato/a selezionato/a per partecipare ad un sondaggio di YouGov!',
            // ],
            // 'surveyButton' => ['Inizia sondaggio'],
            // 'unsubscribe'  => 'Cancella Iscrizione',
            // 'emailStart'   => ['Questa e-mail è destinata a'],
            // 'emailEnd' => '',
            'Enter this verification code to log in to your YouGov account:' => 'Ange den här verifieringskoden för att skapa ditt YouGov-konto:',
        ],
        'en' => [ // it-80199084.eml, it-82583767.eml, it-82845125.eml
            'mentionsAccount' => [
                'to log in to your YouGov account:',
                'reset the password associated with your YouGov account',
                'Are you trying to log in to your YouGov account',
                'You have received this email due to having registered to be a member of the YouGov',
                'Activate your account now to start taking surveys',
                'Your YouGov account is about to expire',
                'Thank you for redeeming your YouGov points',
                'your account will automatically close down', 'Reactivate my account now!',
                'You received this email because you signed up to receive surveys from YouGov',
                'You received this email because you signed up to receive surveys and messages from YouGov',
            ],

            'surveyDetect' => [
                'You have been selected for a special survey',
                'You have been selected for a YouGov survey!',
                'Click the link below to confirm your email address. That will start you on your first survey',
                'You have been specially selected for this survey opportunity.',
                "Today's short survey will take no more than",
                'The survey should take',
                'New survey!',
            ],
            'surveyButton' => 'Start survey',
            'unsubscribe'  => 'Unsubscribe',
            'emailStart'   => 'This email was intended for',
            // 'emailEnd' => '',
            // 'Enter this verification code to log in to your YouGov account:' => '',
        ],
    ];

    private $subjects = [
        'pt' => ['Seu código de login', 'O seu código de login'],
        'en' => ['Your login code'],
        'es' => ['Tu código de inicio de sesión'],
        'it' => ['il tuo codice d’accesso'],
        'sv' => ['Din inloggningskod'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@yougov.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".yougov.com/") or contains(@href,"account.yougov.com") or contains(@href,"yougov.zendesk.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"start.yougov.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();

        $name = $login = null;

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/^{$this->opt($this->t('Hi'))}\s+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:[ ]*[,:;!?]|$)/u");

        if ($name) {
            // it-83129485.eml
            $st->addProperty('Name', $name);
        }

        $login = $this->http->FindSingleNode("descendant::*[{$this->starts($this->t('emailStart'))}][1]", null, true, "/^{$this->opt($this->t('emailStart'))}\s*(\S+@\S+\.\w+)(?:[ ]*{$this->opt($this->t('emailEnd'))}|[.،])/");

        if (!$login) {
            // it-82719297.eml
            $login = $this->http->FindSingleNode("descendant::p[{$this->contains($this->t('emailStart'))}][1]", null, true, "/^(\S+@\S+\.[A-z\d]+)\s*{$this->opt($this->t('emailStart'))}/");
        }

        if ($login) {
            $st->setLogin($login);
        }

        if ($name || $login) {
            $st->setNoBalance(true);
        } elseif ($this->isMembership()) {
            $st->setMembership(true);
        }

        $code = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Enter this verification code to log in to your YouGov account:'))}]/following::text()[normalize-space()][1]");

        if (!empty($code)) {
            $otc = $email->add()->oneTimeCode();
            $otc->setCode($code);
        } else {
            $link = $this->http->FindSingleNode("//a[contains(normalize-space(), 'Log me in')]/@href");

            if (!empty($link)) {
                $this->logger->error($link);
                $otc = $email->add()->oneTimeCode();
                $otc->setCodeAttr("#https\:\/\/mena\.yougov\.com\/[a-z]+\/account\/mfa\/[a-z\-\d]+\/#ui", 3000);
                $otc->setCode($link);
            }
        }
        $email->setType('Account' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function isMembership(): bool
    {
        $patterns['unsubscribeUrl'] = ".+\/account\/unsubscribe(?:\/|\?|$)";

        return $this->http->XPath->query("//*[{$this->contains($this->t('mentionsAccount'))}]")->length > 0

            || $this->http->XPath->query("//*[{$this->contains($this->t('surveyDetect'))}]")->length > 0
            && $this->http->XPath->query("//a[{$this->eq($this->t('surveyButton'))}]")->length === 1
            && ($this->http->FindSingleNode("//a[{$this->eq($this->t('unsubscribe'))}]/@href", null, true, "/{$patterns['unsubscribeUrl']}/i") !== null || $this->http->FindSingleNode("//text()[{$this->contains($this->t('To unsubscribe click on your country:'))}]/following::a[normalize-space()][1]/@href", null, true, "/{$patterns['unsubscribeUrl']}/i") !== null);
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang)) {
                continue;
            }

            if (!empty($phrases['mentionsAccount']) && $this->http->XPath->query("//*[{$this->contains($phrases['mentionsAccount'])}]")->length > 0
                || !empty($phrases['emailStart']) && $this->http->XPath->query("//*[{$this->contains($phrases['emailStart'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
