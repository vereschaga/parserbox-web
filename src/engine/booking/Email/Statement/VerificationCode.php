<?php

namespace AwardWallet\Engine\booking\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "booking/statements/it-825021709.eml, booking/statements/it-825846139.eml, booking/statements/it-825851043.eml";

    private $detectSubjects = [
        // en
        'is your verification code',
        // fr
        'Booking.com - Voici votre code de vérification :',
        // pt
        'é o seu código de verificação',
        'Booking.com: seu código de verificação é',
        // pl
        'to Twój kod weryfikacyjny',
        // it
        'è il tuo codice di verifica',
        // es
        'es tu código de verificación',
        'Tu código de verificación de Booking.com:',
        // zh
        'Booking.com - 你的验证码为',
        'Booking.com－您的驗證碼是',
        // de
        'ist Ihr Bestätigungscode',
        // nl
        'is je verificatiecode',
        // id
        'adalah kode verifikasi Anda',
        // ko
        'Booking.com - 고객님의 인증 코드는',
        // ar
        'Booking.com: رمز التحقق الخاص بك هو',
        // he
        '‏Booking.com‏ – זה קוד האימות שלכם:',
        // lv
        'Booking.com – Jūsu verifikācijas kods ir',
        // hu
        'Booking.com – az Ön hitelesítő kódja:',
        // ru
        'Booking.com - Ваш код подтверждения —',
        // ja
        'Booking.com - 認証コードは',
        // el
        'Booking.com - ο κωδικός επαλήθευσής σας είναι',
        // sv
        'är din verifieringskod',
        // th
        'Booking.com - รหัสยืนยันของท่านคือ',
        // lt
        'yra jūsų patvirtinimo kodas',
        // da
        'er din verificeringskode',
        // ro
        'este codul dvs. de verificare',
        // bg
        'е вашият код за потвърждение',
        // fi
        'Booking.com – vahvistuskoodisi on',
        // sk
        'Booking.com – Váš overovací kód je',
        // no
        'er verifiseringskoden din',
        // tr
        'doğrulama kodunuzdur',
        // uk
        'Booking.com – ваш код підтвердження:',
    ];
    private $lang = '';

    private static $dictionary = [
        'en' => [
            "uniqueCodeToSingInText" => [
                "This is your unique code, which you can use to securely sign in to your Booking.com account without using a password.",
                "This is your unique code – use it to securely sign in to your Booking.com account without a password.",
                "If you’re unable to update your Booking.com app and need to access your account urgently, use the verification code below to sign in. Enter this code in the password field on the sign-in screen:",
            ],
            "accountLinkedToText" => [
                "If you didn’t request a verification code for the account linked to",
                "If you didn't try signing in to the account linked to ",
            ],
            // "Dear " => "",
        ],
        'fr' => [
            "uniqueCodeToSingInText" => [
                "Voici votre code unique, que vous pouvez utiliser pour vous connecter en toute sécurité à votre compte Booking.com sans utiliser de mot de passe.",
            ],
            "accountLinkedToText" => "Si vous n'avez pas demandé de code de vérification pour le compte lié à l’adresse",
            "Dear "               => "Bonjour ",
        ],
        'pt' => [
            "uniqueCodeToSingInText" => [
                "Este é o seu código único, que pode utilizar para iniciar sessão de forma segura na sua conta sem utilizar uma palavra-passe.",
                "Este é o seu código exclusivo. Use o código para fazer login com segurança em sua conta da Booking.com sem usar uma senha.",
                "Se não conseguir atualizar o aplicativo da Booking.com e precisar acessar sua conta com urgência, use o código de verificação abaixo para fazer login. Digite este código no campo de senha da tela de login:",
            ],
            "accountLinkedToText" => [
                "Se não solicitou um código de verificação para a conta associada a",
                "Se você não solicitou um código de verificação para a conta vinculada a",
                "Caso não tenha tentado entrar na conta vinculada a",
            ],
            "Dear " => "Olá,",
        ],
        'pl' => [
            "uniqueCodeToSingInText" => [
                "Jest to Twój unikalny kod, którego możesz użyć do bezpiecznego zalogowania się na swoje konto Booking.com bez użycia hasła.",
            ],
            "accountLinkedToText" => [
                "Jeśli ta prośba nie została wysłana przez Ciebie dla konta powiązanego z adresem e-mail",
            ],
            "Dear " => "Witaj,",
        ],
        'it' => [
            "uniqueCodeToSingInText" => [
                "Questo è il tuo codice univoco, che puoi utilizzare per accedere in modo sicuro al tuo account Booking.com senza usare una password.",
            ],
            "accountLinkedToText" => [
                "Se non hai richiesto un codice di verifica per l'account collegato a questo indirizzo (",
            ],
            "Dear " => "Ciao ",
        ],
        'es' => [
            "uniqueCodeToSingInText" => [
                "Este es tu código único para que inicies sesión de forma segura en tu cuenta de Booking.com sin usar una contraseña.",
                "Este es tu código único, que puedes usar para iniciar sesión de forma segura en tu cuenta de Booking.com sin usar una contraseña.",
            ],
            "accountLinkedToText" => [
                "Si no solicitaste un código de verificación para la cuenta vinculada a",
                "Si no has solicitado un código de verificación de la cuenta vinculada a",
            ],
            "Dear " => "Hola,",
        ],
        'zh' => [
            "uniqueCodeToSingInText" => [
                "这是你的专属验证码，无需输入密码即可安全地登录你的Booking.com帐号。",
                "這是您獨一無二的驗證碼，用來安全地登入您的 Booking.com 帳戶，無需用到密碼。",
            ],
            "accountLinkedToText" => [
                "的关联帐号请求验证码，请忽略这封邮件。",
                '若您並未替與此信箱（',
            ],
            "Dear " => ["亲爱的", "親愛的"],
        ],
        'de' => [
            "uniqueCodeToSingInText" => [
                "dies ist Ihr individueller Code, mit dem Sie sich ohne Passwort sicher bei Ihrem Booking.com-Konto anmelden können.",
                "Wenn Sie Ihre Booking.com-App nicht aktualisieren können und dringend auf Ihr Konto zugreifen müssen, können Sie sich mit dem unten stehenden Bestätigungscode anmelden. Geben Sie diesen Code in das Passwortfeld des Anmeldebildschirms ein:",
            ],
            "accountLinkedToText" => [
                "Wenn Sie keinen Bestätigungscode für das mit",
                'Wenn Sie nicht versucht haben, sich bei dem Konto anzumelden, das mit',
            ],
            "Dear " => "Guten Tag ",
        ],
        'nl' => [
            "uniqueCodeToSingInText" => [
                "Dit is je unieke code, die je kunt gebruiken om veilig in te loggen op je Booking.com-account zonder een wachtwoord te gebruiken.",
            ],
            "accountLinkedToText" => [
                "Als je geen verificatiecode hebt aangevraagd voor het account dat is gekoppeld aan",
            ],
            "Dear " => "Beste ",
        ],
        'id' => [
            "uniqueCodeToSingInText" => [
                "Ini adalah kode unik yang dapat Anda gunakan untuk login ke akun Booking.com dengan aman tanpa menggunakan kata sandi.",
            ],
            "accountLinkedToText" => [
                "Jika tidak meminta kode verifikasi untuk akun yang terhubung ke",
            ],
            "Dear " => "Hai ",
        ],
        'ko' => [
            "uniqueCodeToSingInText" => [
                "고객님께 고유 코드를 전송해 드렸습니다. 이 코드는 비밀번호 없이 Booking.com 계정에 안전하게 로그인하는 데 사용하실 수 있습니다.",
            ],
            "accountLinkedToText" => [
                ")와 연동된 계정에서 코드 전송을 요청하신 적이 없는 경우, 이 이메일은 무시하셔도 좋습니다.",
            ],
            "Dear " => " 님, 안녕하세요.",
        ],
        'ar' => [
            "uniqueCodeToSingInText" => [
                "هذا هو الرمز الفريد الخاص بك، والذي يمكنك استخدامه لتسجيل الدخول بشكل آمن إلى حساب Booking.com الخاص بك دون استخدام كلمة مرور.",
                "إذا كنت غير قادر على تحديث تطبيق Booking.com الخاص بك وتحتاج إلى الوصول إلى حسابك بشكل عاجل، يمكنك استخدام رمز التحقق أدناه لتسجيل الدخول. أدخِل هذا الرمز في حقل كلمة المرور الخاص بشاشة تسجيل الدخول:",
            ],
            "accountLinkedToText" => [
                "إذا لم تطلب رمز تحقق للحساب المرتبط بـ",
                "إذا لم تحاول تسجيل الدخول إلى الحساب المرتبط بـ",
            ],
            "Dear " => "مرحباً ",
        ],
        'he' => [
            "uniqueCodeToSingInText" => [
                "זה הקוד הייחודי שלכם, ואפשר להשתמש בו כדי להתחבר באופן מאובטח לחשבון ב-Booking.com, ללא צורך בסיסמה.",
            ],
            "accountLinkedToText" => [
                "אם לא ביקשתם קוד אימות עבור החשבון שמשויך לכתובת ",
            ],
            "Dear " => "",
        ],
        'lv' => [
            "uniqueCodeToSingInText" => [
                "Šis ir Jūsu unikālais kods, ar kuru varat droši ienākt savā Booking.com profilā, neizmantojot paroli.",
            ],
            "accountLinkedToText" => [
                "Ja neesat pieprasījis apstiprināšanas kodu ar e-pasta adresi",
            ],
            "Dear " => "Cien. ",
        ],
        'hu' => [
            "uniqueCodeToSingInText" => [
                "Ez az Ön egyedi kódja, amellyel jelszó használata nélkül is biztonságosan beléphet Booking.com fiókjába.",
            ],
            "accountLinkedToText" => [
                "Ha nem kért hitelesítő kódot",
            ],
            "Dear " => "Kedves ",
        ],
        'ru' => [
            "uniqueCodeToSingInText" => [
                "Это ваш уникальный код, который вы можете использовать для безопасного входа в аккаунт Booking.com без использования пароля.",
            ],
            "accountLinkedToText" => [
                "Если вы не запрашивали код подтверждения для аккаунта, связанного с",
            ],
            "Dear " => "Здравствуйте,",
        ],
        'ja' => [
            "uniqueCodeToSingInText" => [
                "こちらはお客様固有のコードであり、お客様のBooking.comのアカウントにパスワードを使用せず安全にログインするために使用することができます。",
            ],
            "accountLinkedToText" => [
                "に紐づくアカウントの認証コードをリクエストしていない場合、このメールはご放念ください。",
            ],
            "Dear " => "様",
        ],
        'el' => [
            "uniqueCodeToSingInText" => [
                "Αυτός είναι ο μοναδικός κωδικός σας, τον οποίο μπορείτε να χρησιμοποιήσετε για να συνδεθείτε με ασφάλεια στον λογαριασμό σας στην Booking.com χωρίς να χρησιμοποιήσετε κωδικό πρόσβασης.",
            ],
            "accountLinkedToText" => [
                "Εάν δεν ζητήσατε κωδικό επαλήθευσης για τον λογαριασμό που είναι συνδεδεμένος με τη διεύθυνση",
            ],
            "Dear " => "Γεια σας ",
        ],
        'sv' => [
            "uniqueCodeToSingInText" => [
                "Det här är din unika kod som du kan använda för att säkert logga in på ditt Booking.com-konto utan att använda ett lösenord.",
            ],
            "accountLinkedToText" => [
                "Om du inte har begärt en verifieringskod för kontot som är kopplat till",
            ],
            "Dear " => "Hej ",
        ],
        'th' => [
            "uniqueCodeToSingInText" => [
                "นี่คือรหัสเฉพาะสำหรับท่าน ซึ่งท่านสามารถนำไปใช้เข้าสู่ระบบแอคเคาท์ Booking.com ได้อย่างปลอดภัยโดยที่ไม่ต้องใช้รหัสผ่าน",
            ],
            "accountLinkedToText" => [
                "หากท่านไม่ได้ขอรหัสยืนยันสำหรับแอคเคาท์ที่เชื่อมกับ",
            ],
            "Dear " => "เรียน คุณ ",
        ],
        'lt' => [
            "uniqueCodeToSingInText" => [
                "tai jūsų unikalus kodas, su kuriuo galite saugiai prisijungti prie savo Booking.com paskyros nenaudodami slaptažodžio.",
            ],
            "accountLinkedToText" => [
                "Jei neprašėte patvirtinimo kodo paskyrai, su kuria susietas el. pašto adresas",
            ],
            "Dear " => "Gerb.",
        ],
        'da' => [
            "uniqueCodeToSingInText" => [
                "Dette er din unikke kode som du kan bruge til at logge sikkert ind på din Booking.com-konto uden at bruge en adgangskode.",
            ],
            "accountLinkedToText" => [
                "Hvis du ikke har anmodet om en verificeringskode for den konto der er knyttet til",
            ],
            "Dear " => "Hej ",
        ],
        'ro' => [
            "uniqueCodeToSingInText" => [
                "Acesta este codul dvs. unic, pe care îl puteți utiliza pentru a vă conecta în siguranță la contul dvs. Booking.com fără a utiliza o parolă.",
            ],
            "accountLinkedToText" => [
                "Dacă nu ați solicitat un cod de verificare pentru contul asociat cu",
            ],
            "Dear " => "Hej ",
        ],
        'bg' => [
            "uniqueCodeToSingInText" => [
                "Това е вашият уникален код, който може да използвате за сигурно влизане във вашия профил в Booking.com, без да използвате парола.",
            ],
            "accountLinkedToText" => [
                "Ако не сте поискали код за потвърждение за профила, свързан с",
            ],
            "Dear " => "Здравейте,",
        ],
        'fi' => [
            "uniqueCodeToSingInText" => [
                "Tämä on yksilöllinen koodisi, jonka avulla voit kirjautua turvallisesti sisään Booking.comin käyttäjätilillesi ilman salasanaa.",
            ],
            "accountLinkedToText" => [
                "Jos et ole pyytänyt vahvistuskoodia sähköpostiosoitteeseen",
            ],
            "Dear " => "Hei ",
        ],
        'sk' => [
            "uniqueCodeToSingInText" => [
                "toto je Váš jedinečný kód, ktorý môžete použiť na bezpečné prihlásenie do svojho účtu na Booking.com bez použitia hesla.",
            ],
            "accountLinkedToText" => [
                "Ak ste o overovací kód pre účet prepojený s adresou",
            ],
            "Dear " => "Dobrý deň, ",
        ],
        'no' => [
            "uniqueCodeToSingInText" => [
                "Dette er en unik kode som du bruker til å logge inn på Booking.com-kontoen din på en trygg måte, uten at du trenger passord.",
            ],
            "accountLinkedToText" => [
                "Hvis du ikke har bedt om noen verifiseringskode for kontoen som er registrert på",
            ],
            "Dear " => "Hei, ",
        ],
        'tr' => [
            "uniqueCodeToSingInText" => [
                "Bu kod şifre kullanmadan Booking.com hesabınıza güvenli bir şekilde giriş yapmak için kullanabileceğiniz benzersiz kodunuzdur.",
            ],
            "accountLinkedToText" => [
                "ile bağlı olduğunuz hesap için bir doğrulama kodu talep etmediyseniz bu e-postayı görmezden gelebilirsiniz.",
            ],
            "Dear " => "Merhaba ",
        ],
        'uk' => [
            "uniqueCodeToSingInText" => [
                "Це ваш унікальний код, який ви можете використати для безпечного входу в акаунт Booking.com без використання пароля.",
            ],
            "accountLinkedToText" => [
                "Якщо ви не просили код підтвердження для акаунта, повʼязаного з",
            ],
            "Dear " => "Вітаємо, ",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["uniqueCodeToSingInText"])
                && $this->http->XPath->query("//node()[" . $this->contains($dict["uniqueCodeToSingInText"]) . "]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $otc = $email->add()->oneTimeCode();
        $otc
            ->setCode($this->http->FindSingleNode("//text()[" . $this->eq($this->t("uniqueCodeToSingInText")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{4,})\s*$/u"))
        ;

        $login = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("accountLinkedToText")) . "]",
        null, true, "/{$this->opt($this->t("accountLinkedToText"))}\s*([\w.\-_]{2,}@[\w.\-_]{2,})\b/u");

        if (empty($login) && in_array($this->lang, ['zh', 'ko', 'ja', 'tr'])) {
            $login = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("accountLinkedToText")) . "]",
                null, true, "/([a-z\d.\-_]{2,}@[\w.\-_]{2,})\s*{$this->opt($this->t("accountLinkedToText"))}/iu");
        }

        if (!empty($login)) {
            $st = $email->add()->statement();

            $st
                ->setMembership(true)
                ->setNoBalance(true)
            ;

            $st->setLogin($login);

            $name = trim($this->http->FindSingleNode("//tr[count(*) = 2][*[1][not(normalize-space())][.//img]//a[@title='Booking.com']][*[2]//img]"));

            if (empty($name)) {
                $name = trim($this->http->FindSingleNode("//tr[count(*) = 2][*[2][not(normalize-space())][.//img]//a[@title='Booking.com']][*[1]//img]"));
            }

            if (empty($name)) {
                $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, true,
                    "/{$this->opt($this->t('Dear '))}\s*(\w.+?\w)(?:，您好)?\W?\s*$/u");
            }

            if (empty($name) && in_array($this->lang, ['ja', 'ko'])) {
                $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Dear '))}]", null, true,
                    "/^\s*(.+?){$this->opt($this->t('Dear '))}\s*$/u");
            }

            if (!empty($name) && stripos($name, '@') === false) {
                $st->addProperty('Name', $name);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'noreply@booking.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && stripos($headers['from'], 'Booking.com') === false) {
            return false;
        }

        foreach ($this->detectSubjects as $subject) {
            if (mb_strpos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["uniqueCodeToSingInText"])
                && $this->http->XPath->query("//node()[" . $this->contains($dict["uniqueCodeToSingInText"]) . "]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
