<?php

namespace AwardWallet\Engine\ichotelsgroup\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AccountStatement extends \TAccountChecker
{
    public $mailFiles = "ichotelsgroup/statements/it-61223250.eml, ichotelsgroup/statements/it-61327511.eml, ichotelsgroup/statements/it-61394646.eml, ichotelsgroup/statements/it-61418594.eml, ichotelsgroup/statements/it-62058901.eml, ichotelsgroup/statements/it-62439081.eml, ichotelsgroup/statements/it-62545737.eml, ichotelsgroup/statements/it-62659521.eml, ichotelsgroup/statements/it-72865057.eml, ichotelsgroup/statements/it-73005072.eml";
    private $lang = '';

    private $reProvider = ['IHG'];
    private $reSubject = [
        '4X Bonus Points',
        'Your Welcome Amenity points are available',
        'There’s a room for you in ',
        'Your password was successfully updated',
        'Reset your IHG Rewards Club password',
        'Plan now with flexible rates in ',
        'Your mailing address was successfully updated',
        '市享受住宿',
        ' wartet ein ',
        'Your email address was successfully updated',
        'You’re in. Welcome to IHG One Rewards,',
        // zh
        '您的密码已成功更新',
        '重置您的 IHG One Rewards 优悦会 优悦会账户密码',
        // es
        'Su contraseña se actualizó correctamente',
        // ko
        '전화번호가 성공적으로 업데이트되었습니다',
        // fr
        'Votre adresse postale a bien été mise à jour',
        'Félicitations. Bienvenue dans le programme IHG One Rewards',
        // ja
        'パスワードの更新が完了しました。',
        // pt
        'Sua senha foi atualizada corretamente',
    ];
    private $reBody = [
        'en' => [
            '2. Log in to your account profile with your IHG',
            'Thank you for being a loyal IHG',
            'Rewards Club email address has been successfully changed',
            'Rewards Club password has been successfully changed',
            'Rewards Club earning preference has been successfully changed',
            'Account change confirmation',
            'Complete your reservation when the time is right',
            'We’re excited to have you in IHG',
            'Confirmed: You\'re Registered',
            'Rewards Club password',
            'Plan now, stay when you’re ready',
            'Enhance your next stay with these points',
            'valued IHG Rewards member',
            'Your IHG Rewards password',
            'We received a request',
            'Thank you for registering',
            'As a valued IHG One Rewards member, your',
            'Your IHG One Rewards password',
            'By joining IHG One Rewards, you’ve opened up',
            'yourIHG® One Rewards account',
            'Dining points have been deposited into your account',
            'Thank you for being an IHG® One Rewards member',
            'Thank you for registering for Earn up to 4X',
            'YOUR NEW CLUB BENEFITS ARE READY FOR YOU, ',
            'As an IHG® Rewards member, we have a host of special offers',
        ],
        'zh' => [
            '优悦会会员号码和密码密码登录您的账户；',
            '即刻规划，享受您在深圳的特惠。',
            '在适当时完成您的预订',
            '即刻计划，适时体验',
            '您的 IHG One Rewards 优悦会 优悦会账户',
            '我们已收到您重置 IHG One Rewards 优悦会 优悦会 账户密码的请求',
        ],
        'de' => [
            'Rewards Club-Mitgliedsnummer und Ihre Passwort Ihr Kontoprofil auf.',
            'Jetzt planen, später übernachten',
            'Schließen Sie die Reservierung',
            'Herzlich willkommen bei IHG® One Rewards',
            'Wir haben eine Anfrage zur Änderung Ihres IHG One Rewards-Passworts erhalten',
            '2. Rufen Sie über Ihre IHG One Rewards-Mitgliedsnummer und Ihre Passwort Ihr Kontoprofil auf.',
        ],
        'es' => [
            '2. Inicie una sesión con su número de socio de IHG',
            '2. Entre en el perfil de su cuenta con su número IHG',
            'Su contraseña de IHG One Rewards se ha cambiado correctamente',
            'Hemos recibido una solicitud para restablecer su contraseña de IHG One Rewards',
        ],
        'ru' => [
            '2. Войдите в свой аккаунт при помощи номера участника IHG',
        ],
        'pt' => [
            'Conclua sua reserva quando chegar o momento',
            'Sua senha do IHG One Rewards foi alterada corretamente',
            'Recebemos uma solicitação para redefinir sua senha no IHG One Rewards',
            'Em breve, você estará onde quiser. Você se inscreveu no IHG One Rewards',
            'SEUS NOVOS BENEFÍCIOS CLUB ESTÃO À SUA ESPERA, ',
            '2. Faça login e acesse o perfil da sua conta, usando seu número de associado IHG One Rewards e sua senha.',
        ],
        'ko' => [
            '지금 계획하시고 원하실 때 숙박하세요',
            '원하시는 적절한 시점에 숙박을 완료하세요.',
            'IHG One Rewards 전화번호가 성공적으로 변경되었습니다',
            'IHG One Rewards 암호가 성공적으로 변경되었습니다',
            '귀하의 IHG One Rewards 암호 재설정 요청이 접수되었습니다',
            'IHG® One Rewards 회원이시면 ihg.com에서 전 세계',
            '2. IHG One Rewards 번호 및 비밀번호를 사용하여 계정 프로필로 로그인합니다.',
        ],
        'fr' => [
            'IHG One Rewards a bien été modifiée',
            'Bienvenue dans le programme IHG® One Rewards',
            '2. Vous connecter à votre profil de compte à l\'aide de votre numéro IHG One Rewards et de votre mot de passe.',
            '2. Accédez au profil de votre compte en utilisant votre numéro IHG One Rewards et votre mot de passe',
        ],
        'ja' => [
            'IHGリワーズのパスワードの変更が完了しました',
            'IHGリワーズのパスワードリセットのリクエストを受信しました',
            '2. IHG®リワーズクラブ会員番号とパスワーを使ってアカウントにログインしてください。',
        ],
        'it' => [
            '2. Accedi al tuo profilo di account tramite il tuo numero IHG One Rewards e password.',
        ],
        'ar' => [
            '2. تسجيل الدخول على حسابك باستخدام رقم عضويتك في برنامج المكافآت IHG Rewards وكلمة المرور',
        ],
        'nl' => [
            '2. Meld u aan bij uw profiel. Gebruik daarvoor uw nummer uw wachtwoord van IHG One Rewards.',
        ],
    ];
    private static $dictionary = [
        'en' => [],
        'zh' => [
            'Sign In' => '登录',
            //            'Member #:' => '',
            //            'Membership Level:' => '',
            //            'Points Balance' => '',
            //            'as of' => '',
        ],
        'de' => [
            'Sign In' => 'Anmelden',
            //            'Member #:' => '',
            //            'Membership Level:' => '',
            //            'Points Balance' => '',
            //            'as of' => '',
        ],
        'es' => [
            'Sign In' => 'Iniciar sesión',
            //            'Member #:' => '',
            //            'Membership Level:' => '',
            //            'Points Balance' => '',
            //            'as of' => '',
        ],
        'ru' => [
            'Sign In' => 'Войти',
            //            'Member #:' => '',
            //            'Membership Level:' => '',
            //            'Points Balance' => '',
            //            'as of' => '',
        ],
        'pt' => [
            'Sign In' => 'Login',
            //            'Member #:' => '',
            //            'Membership Level:' => '',
            //            'Points Balance' => '',
            //            'as of' => '',
        ],
        'ko' => [
            'Sign In' => '로그인',
            //            'Member #:' => '',
            //            'Membership Level:' => '',
            //            'Points Balance' => '',
            //            'as of' => '',
        ],
        'fr' => [
            'Sign In' => 'Se connecter',
            //            'Member #:' => '',
            //            'Membership Level:' => '',
            //            'Points Balance' => '',
            //            'as of' => '',
        ],
        'ja' => [
            'Sign In' => 'ログイン',
            //            'Member #:' => '',
            //            'Membership Level:' => '',
            //            'Points Balance' => '',
            //            'as of' => '',
        ],
        'it' => [
            'Sign In' => 'Accedi',
            //            'Member #:' => '',
            //            'Membership Level:' => '',
            //            'Points Balance' => '',
            //            'as of' => '',
        ],
        'ar' => [
            'Sign In' => 'تسجيل الدخول',
            //            'Member #:' => '',
            //            'Membership Level:' => '',
            //            'Points Balance' => '',
            //            'as of' => '',
        ],
        'nl' => [
            'Sign In' => 'Aanmelden',
            //            'Member #:' => '',
            //            'Membership Level:' => '',
            //            'Points Balance' => '',
            //            'as of' => '',
        ],
    ];

    private $enDatesInverted = false;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('AccountStatement' . ucfirst($this->lang));

        $st = $email->add()->statement();

        $xpathMainLogo = "(contains(@alt,'IHG') or contains(@src,'/IHG') or contains(@src,'_IHG'))";

        $rows = [];
        $rowsNodes = $this->http->XPath->query("descendant::tr[ count(*)=2 and *[1][normalize-space()=''] and (*[1]/descendant::img[{$xpathMainLogo}] or *[2][{$this->contains($this->t('Member #:'))} or descendant::text()[{$this->eq($this->t('Sign In'))}]]) ][1]/*[2]/descendant::tr[not(.//tr) and normalize-space()]");

        if ($rowsNodes->length === 0) {
            // it-62659521.eml
            $rowsNodes = $this->http->XPath->query("descendant::tr[ count(*)=4 and *[2]/descendant::text()[{$this->starts($this->t('Member #:'))}] ]/*[position()=2 or position()=3]/descendant::tr[not(.//tr) and normalize-space()]");
        }

        foreach ($rowsNodes as $root) {
            $rowHtml = $this->http->FindHTMLByXpath('.', null, $root);
            $rows[] = $this->htmlToText($rowHtml);
        }
        $text = implode("\n", $rows);
        $this->logger->debug($text);

        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]](?: *[Jj]r.)?';

        /*
            Joyce Lewis
            Gold Elite Member #: 755594025
            1-888-897-0083 | Stay | Offers | Meetings | Our Brands | Sign In

                [OR]

            Todd Mcchurch
            Member #: 424733922
            Membership Level: Gold Elite
            Gold Elite
        */
        $patterns['v1'] = "/^"
            . "\s*(?<name>{$patterns['travellerName']})[ ]*\n+"
            . "[ ]*(?:(?<level>.{4,}?)[ ]+)?{$this->opt($this->t('Member #:'))}[ ]+(?<number>\d{5,})[ ]*(?:\n|$)"
            . "(?:[ ]*(?:{$this->opt($this->t('Membership Level:'))}[ ]+)?(?<level2>[\w ]{4,}?)[ ]*(?:\n|$))?"
            . "/u";

        /*
            David Cockbaine
            Club 445373269
            Sign In

                [OR]

            Galina Poltaeva
            212779020
            Войти
        */
        $patterns['v2'] = "/^"
            . "\s*(?<name>{$patterns['travellerName']})[ ]*\n+"
            . "[ ]*(?:(?<level>.{2,}?)[ ]+)?(?<number>\d{5,})[ ]*\n+"
            . "[ ]*{$this->opt($this->t('Sign In'))}\s*"
            . "$/u";

        /*
            Amanda Van Deutekom
            326253177
            Platinum Elite
        */
        $patterns['v3'] = "/^"
            . "\s*(?<name>{$patterns['travellerName']})[ ]*\n+"
            . "[ ]*(?<number>\d{5,})[ ]*\n+"
            . "[ ]*(?<level>.{4,}?)\s*"
            . "$/u";

        if (preg_match($patterns['v1'], $text, $m) || preg_match($patterns['v2'], $text, $m)
            || preg_match($patterns['v3'], $text, $m)
        ) {
            $st->addProperty('Name', $m['name'])
                ->addProperty('Number', $m['number'])
                ->addProperty('Login', $m['number']);

            if (!empty($m['level']) && !preg_match("/{$this->opt($this->t('Sign In'))}/i", $m['level'])) {
                $level = $m['level'];
            } elseif (!empty($m['level2']) && !preg_match("/{$this->opt($this->t('Sign In'))}/i", $m['level2'])) {
                $level = $m['level2'];
            } else {
                $level = null;
            }

            if ($level !== null) {
                // Club    |    Клубный (Club) статус
                $st->addProperty('Level', preg_replace('/^[^(]*\(\s*([^)(]{4,}?)\s*\)[^)]*$/', '$1', $level));
            }
        }

        // it-62659521.eml
        $pointsBalanceCell = implode(' ', $this->http->FindNodes("//tr/*[2][{$this->starts($this->t('Points Balance'))} and {$this->contains($this->t('as of'))}]/descendant::text()[normalize-space()]"));

        if (preg_match("/^{$this->opt($this->t('Points Balance'))}[: ]+(\d[,.\'\d ]*)[ ]+{$this->opt($this->t('as of'))}[ ]+(.{6,})$/", $pointsBalanceCell, $m)) {
            // Points Balance : 22,169 as of 09/13/2019

            if (preg_match_all('/\b(\d{1,2})\s*\/\s*\d{1,2}\s*\/\s*\d{4}\b/', $m[2], $dateMatches)) {
                foreach ($dateMatches[1] as $simpleDate) {
                    if ($simpleDate > 12) {
                        $this->enDatesInverted = true;

                        break;
                    }
                }
            }

            $st->setBalance($this->normalizeAmount($m[1]))
                ->parseBalanceDate($this->normalizeDate($m[2]));

            return $email;
        }

        // it-62545737.eml
        $urlData = implode("\n", $this->http->FindNodes("//img[contains(@src,'points_balance') or contains(@src,'asofdate')]/@src"));

        if (preg_match_all("/points_balance=(\d\S*?)(?:&|\s|$)/i", $urlData, $m)
            && count(array_unique($m[1] = array_map('urldecode', $m[1]))) === 1
        ) {
            $st->setBalance($this->normalizeAmount($m[1][0]));
        } elseif (empty($urlData)) {
            $st->setNoBalance(true);
        }

        if (preg_match_all("/asofdate=(\d\S*?)(?:&|\s|$)/i", $urlData, $m)
            && count(array_unique($m[1] = array_map('urldecode', $m[1]))) === 1
        ) {
            // 7/24/2020

            if (preg_match_all('/\b(\d{1,2})\s*\/\s*\d{1,2}\s*\/\s*\d{4}\b/', $m[1][0], $dateMatches)) {
                foreach ($dateMatches[1] as $simpleDate) {
                    if ($simpleDate > 12) {
                        $this->enDatesInverted = true;

                        break;
                    }
                }
            }

            $st->parseBalanceDate($this->normalizeDate($m[1][0]));
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@[a-z]+\.ihg\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $value) {
            $this->logger->debug(' = '.print_r( "//text()[{$this->contains($value)}]",true));
            if ($this->http->XPath->query("//node()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        // 09/13/2019
        $in[0] = '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/';
        $out[0] = $this->enDatesInverted ? '$2/$1/$3' : '$1/$2/$3';

        return preg_replace($in, $out, $text);
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
