<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerShell extends TAccountChecker
{
    use ProxyList;
    use OtcHelper;

    protected const COUNTRIES_CACHE_KEY = 'shell_countries';

    // very slow authorization on this account (login: '70041411794488117')
    private const SLOW_ACCOUNTS = ['70041411794488117', '70041420410521011', '370995923'];

    private $cookieLanguage = null;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $es = false;
    // very slow authorization on this account (login: '70041411794488117')
    private $curlTimeout = null;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'shellRewards')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if ($this->AccountFields['Login2'] == '/smart/index.html?site=ru-ru') {
            $this->setProxyBrightData(null, "dc_ips_ru", "ru");
        } else {
            $this->http->SetProxy($this->proxyDOP());
        }
    }

    public function IsLoggedIn()
    {
        if (strstr($this->AccountFields['Login2'], '/smart/index.html?site=en-en')) {
            $this->http->GetURL("https://www.goplus.shell.com/en-gb/account/main");

            if ($this->loginSuccessful()) {
                return true;
            }
        }
        /*
        if (strstr($this->AccountFields['Login2'], '/smart/index.html?site=en-en')
            || strstr($this->AccountFields['Login2'], '/smart/index.html?site=cz-cs')
            || strstr($this->AccountFields['Login2'], '/smart/index.html?site=pl-pl')
            || strstr($this->AccountFields['Login2'], '/smart/index.html?site=sk-sk')
            || strstr($this->AccountFields['Login2'], '/smart/index.html?site=hu-hu')) {
            // set cookies
            $this->setCookieLanguage();
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.shellsmart.com/smart/index.html?site={$this->cookieLanguage}", [], 20);
            if (isset($this->State['cookies'])) {
                foreach ($this->State['cookies'] as $cookie) {
                    $this->logger->debug("{$cookie['name']}={$cookie['value']}, {$cookie['domain']}");
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain']);
                }
            }
            $this->http->GetURL("https://www.shellsmart.com/smart/account/overview?site={$this->cookieLanguage}", [], 20);
            //$this->http->GetURL("https://www.shellsmart.com/smart/sso/login/return?site={$this->cookieLanguage}&accessCode={$this->State['accessCode']}", [], 120);
            $this->http->RetryCount = 2;
            if ($this->loginSuccessful())
                return true;
        }
        */

        return false;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields['Login2']['Options'] = [
            ""                                    => "Select your country",
            "/smart/index.html?site=bg-bg"        => "Bulgaria",
            "/smart/index.html?site=de-de"        => "Deutschland",
            "/smart/index.html?site=cz-cs"        => "Česká republika",
            //            "http://www.tarjetashellclubsmart.es" => "España",// there is no valid account
            "https://www.allsmart.gr"             => "Greece",
            "/smart/index.html?site=hk-en"        => "Hong Kong",
            "/smart/index.html?site=mo-en"        => "Macau",
            "/smart/index.html?site=hu-hu"        => "Magyarország",
            "/smart/index.html?site=pl-pl"        => "Polska",
            //            "/smart/index.html?site=sg-en"        => "Singapore",
            "/smart/index.html?site=sk-sk"        => "Slovenská republika",
            "/smart/index.html?site=tr-tr"        => "Türkiye",
            //            "/smart/index.html?site=th-en"        => "Thailand",
            "/smart/index.html?site=en-en"        => "United Kingdom",
        ];

        /*
        $result = Cache::getInstance()->get(self::COUNTRIES_CACHE_KEY);

        if (($result !== false) && (count($result) > 1)) {
            $arFields["Login2"]["Options"] = $result;
        } else {
            $arFields["Login2"]["Options"] = [
                "" => "Select your country",
            ];
            $browser = new HttpBrowser("none", new CurlDriver());
            $browser->SetProxy($this->proxyStaticIpDOP());
            $browser->GetURL("https://www.shellsmart.com/smart/index.html?site=tr-tr");
            $nodes = $browser->XPath->query("//select[@id = 'site_selector']/option");

            for ($n = 0; $n < $nodes->length; $n++) {
                $val = $nodes->item($n)->nodeValue;
                $href = $nodes->item($n)->getAttribute('value');

                if (
                    isset($href)
                    && $href != "/"
                    && $href != "?spider.country.home.page.url?"
                    && $href != "/smart/index.html?site=sg-en"
                ) {
                    $arFields['Login2']['Options'][$href] = Html::cleanXMLValue($val);
                }
            }

            if (count($arFields['Login2']['Options']) > 1) {
                // site bug fix
                if (
                    !isset($arFields['Login2']['Options']["/smart/index.html?site=en-en"])
                    && !isset($arFields['Login2']['Options']["/smart/index.html?site=en-gb"])
                ) {
                    $arFields['Login2']['Options']["/smart/index.html?site=en-en"] = "United Kingdom";
                }

                Cache::getInstance()->set(self::COUNTRIES_CACHE_KEY, $arFields['Login2']['Options'], 3600 * 24);
            } else {
                $this->sendNotification("regions are not found", 'all', true, $browser->Response['body']);
            }
        }
        */
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (empty($this->AccountFields['Login2'])) {
            throw new CheckException("To update this Shell Clubsmart account you need to select your country. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/
        // Italy maybe has been closed
        if ($this->AccountFields['Login2'] == '/smart/index.html?site=it-it') {
            throw new CheckException("It seems that Shell Clubsmart for Italy region no longer exists.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        if ($this->AccountFields['Login2'] == '/smart/index.html?site=sg-en') {
            throw new CheckException("It seems that you are no longer able to log in to your profile on the Shell website. The program has switched completely to the app.", ACCOUNT_PROVIDER_ERROR); /*review*/
        }

        if ($this->AccountFields['Login2'] == '/smart/index.html?site=th-en') {
            throw new CheckException("It seems that you are no longer able to log in to your profile on the Shell website. The program has switched completely to the app.", ACCOUNT_PROVIDER_ERROR); /*review*/
        }

        // Austria has been closed at the end of 2016
        if ($this->AccountFields['Login2'] == '/smart/index.html?site=at-de' || $this->AccountFields['Login2'] == 'https://www.shellsmart.com/smart/index.html?site=at-de') {
            throw new CheckException("Sehr geehrter Shell CLUBSMART Kunde, Sie haben versucht, die Seiten des Shell CLUBSMART Programms in Österreich aufzurufen. Das Shell CLUBSMART Programm wird in Österreich zum Jahresende 2016 beendet – eine Einlösung Ihrer Shell CLUBSMART Punkte war bis zum 12.12.2016 möglich, die geplante Schließung hatten wir mehrere Monate zuvor sowohl direkt an unseren Tankstellen als auch über verschiedene digitale Kanäle angekündigt.", ACCOUNT_PROVIDER_ERROR);
        }
        // Switzerland has been closed at the end of 2016
        if ($this->AccountFields['Login2'] == 'https://www.shellsmart.com/smart/index.html?site=ch-de') {
            throw new CheckException("Sehr geehrter Shell CLUBSMART Kunde, Sie haben versucht, die Seiten des Shell CLUBSMART Programms in der Schweiz aufzurufen. Das Shell CLUBSMART Programm wird  in der Schweiz zum Jahresende 2016 beendet – eine Einlösung Ihrer Shell CLUBSMART Punkte war bis zum 12.12.2016 möglich, die geplante Schließung hatten wir mehrere Monate zuvor sowohl direkt an unseren Tankstellen als auch über verschiedene digitale Kanäle angekündigt.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->AccountFields['Login2'] == '/smart/index.html?site=ch-fr') {
            throw new CheckException("Cher client Shell CLUBSMART, chère cliente Shell CLUBSMART, Vous avez essayé de consulter les pages du programme Shell CLUBSMART en Suisse. Le programme Shell CLUBSMART s’arrêtera en Suisse à la fin de l’année 2016. Vous aviez la possibilité d’échanger vos points Shell CLUBSMART jusqu’au 12 décembre 2016. Nous avions par ailleurs annoncé la fin prévue du programme plusieurs mois à l’avance aussi bien directement à nos stations-service que via différents canaux numériques.", ACCOUNT_PROVIDER_ERROR);
        }

        if (in_array($this->AccountFields['Login2'], ["/smart/index.html?site=ru-ru"])) {
            throw new CheckException("Уважаемые клиенты! С сожалением сообщаем, что с 20 мая 2022 года программа лояльности Shell ClubSmart прекращает свое действие.", ACCOUNT_PROVIDER_ERROR);
        }

        // there is no valid account
        if (in_array($this->AccountFields['Login2'], ["http://www.tarjetashellclubsmart.es"])) {
            throw new CheckException('Unfortunately, we currently do not support this region.', ACCOUNT_PROVIDER_ERROR);
        }

        if (in_array($this->AccountFields['Login2'], ['http://www.tarjetaclubsmart.es', 'http://www.tarjetashellclubsmart.es'])) {
            $this->es = true;
            $this->http->GetURL("https://clientes.disagrupo.es/sso/login?ReturnUrl=http%3a%2f%2fwww.tarjetashellclubsmart.es%2f");

            if (!$this->http->ParseForm(null, "//form[@action='/sso/login']")) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue('UserName', $this->AccountFields['Login']);
            $this->http->SetInputValue('Password', $this->AccountFields['Pass']);
        } else {
            // set cookies
            $this->setCookieLanguage();
            $this->http->setCookie("SplashCookie", $this->cookieLanguage, "www.shellsmart.com", "/smart/", strtotime("Wed, 18-11-2029 10:57:52 GMT"));

            /*
            // proxy
            if (in_array($this->AccountFields['Login2'], ["https://www.shellsmart.com/smart/index.html?site=de-de", "/smart/index.html?site=de-de", "/smart/index.html?site=ru-ru"]))
                $this->http->SetProxy($this->proxyReCaptcha());
            */

            if (in_array($this->AccountFields['Login2'], ["/smart/index.html?site=ru-ru"])) {
                $this->http->setUserAgent("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.2 Safari/605.1.15");
            }

            // new site
            if ($this->AccountFields['Login2'] == 'https://www.wing.gr/smart/index.html?site=gr-el') {
                $this->AccountFields['Login2'] = 'https://www.allsmart.gr';
            }

            if (strstr($this->AccountFields['Login2'], '/smart/index.html?site=bg-bg')) {
                $this->http->GetURL('https://www.clubsmart.shell.bg/sso/login/start');

                return true;
            }

            if (strstr($this->AccountFields['Login2'], '/smart/index.html?site=cz-cs')) {
                $this->http->GetURL('https://www.clubsmart.shell.cz/sso/login/start');

                return true;
            }

            if (strstr($this->AccountFields['Login2'], '/smart/index.html?site=de-de')) {
                $this->http->GetURL('https://login.consumer.shell.com/?market=de-DE&clientId=7pmdzq5k5djp2f8pu8xz74ev9sgh8yz3');

                return true;
            }

            if (strstr($this->AccountFields['Login2'], '/smart/index.html?site=sk-sk')) {
                $this->http->GetURL('https://www.clubsmart.shell.sk/sso/login/start');

                return true;
            }

            // refs #18430
            if (strstr($this->AccountFields['Login2'], '/smart/index.html?site=en-en')) {
                $this->http->GetURL('https://www.goplus.shell.com/en-gb/sso/login/start?mode=LOGIN');

                if ($this->http->Response['code'] != 200 && $this->http->Response['code'] != 404) {
                    if ($this->http->FindSingleNode('//h1[contains(text(), "It’s not you, it’s us.")]')) {
                        throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    if ($message = $this->http->FindSingleNode('//p[contains(text(), "Unfortunately the service is currently undergoing scheduled maintenance.")]')) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    return false;
                }

                return true;
            }// if (strstr($this->AccountFields['Login2'], '/smart/index.html?site=en-en'))

            if (!strstr($this->AccountFields['Login2'], 'http://') && !strstr($this->AccountFields['Login2'], 'https://')) {
                $timeout = 60;

                if (in_array($this->AccountFields['Login2'], ["/smart/index.html?site=ru-ru"])) {
                    $timeout = 120;
                }
                $this->http->GetURL('https://www.shellsmart.com' . $this->AccountFields['Login2'], [], $timeout);

                // bad proxy?
                if (strstr($this->http->currentUrl(), '?site=de-de') && $this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)) {
                    $this->markProxyAsInvalid();

                    throw new CheckRetryNeededException(2, 1);
                }
            } else {
                $this->http->GetURL($this->AccountFields['Login2']);
            }

            $captcha = false;

            if ($this->http->currentUrl() == 'https://www.shellsmart.com/smart/index.html?site=ru-ru') {
                $captcha = true;
            }

            if (!$this->http->ParseForm("login_form") && !$this->http->ParseForm("loginForm") && !$this->http->ParseForm("form0")
                // site=en-en
                && !$this->http->ParseForm("ssoSignInForm")
                // site=hu-hu
                && !$this->http->FindSingleNode('//a[@id = "login_or_register_text_part_1"]/@href')
            ) {
                return $this->checkErrors();
            }

            if ($this->AccountFields['Login2'] == 'https://www.allsmart.gr') {
                //$this->http->GetURL('https://www.allsmart.gr/account/login/');
                $this->http->FormURL = 'https://www.allsmart.gr/ajax/Atcom.Sites.Coral.Components.Account.Login?languageId=1&view=LoginForm&returnUrl=https://www.allsmart.gr/account/';
                $this->http->SetInputValue('Email', $this->AccountFields['Login']);
                $this->http->SetInputValue('Password', $this->AccountFields['Pass']);
                $this->http->SetInputValue('RememberMe', 'false');
                $this->http->SetInputValue('AcceptTerms', 'false');
                $this->http->SetInputValue('X-Requested-With', 'XMLHttpRequest');
            }// if ($this->AccountFields['Login2'] == 'https://www.allsmart.gr/')
            else {
                if (strstr($this->AccountFields['Login2'], '/smart/index.html?site=pl-pl')) {
                    $this->http->SetInputValue('ssoSignInButton', 'Logowanie');

                    return true;
                }

                if (strstr($this->AccountFields['Login2'], '/smart/index.html?site=sk-sk')) {
                    $this->http->SetInputValue('ssoSignInButton', 'Prihl%E1senie');

                    return true;
                }

                if (strstr($this->AccountFields['Login2'], '/smart/index.html?site=de-de')) {
                    $this->http->SetInputValue('ssoSignInButton', 'Login');

                    return true;
                }

                $this->http->SetInputValue('cardnumber', $this->AccountFields['Login']);
                $this->http->SetInputValue('password', $this->AccountFields['Pass']);

                if (strstr($this->AccountFields['Login2'], '/smart/index.html?site=hu-hu')) {
                    $this->http->GetURL('www.clubsmart.shell.hu' . $this->http->FindSingleNode('//a[@id = "login_or_register_text_part_1"]/@href'));

                    return true;
                }

                // Russia
                if ($captcha && ($captcha = $this->parseCaptcha())) {
                    $this->http->SetInputValue('g-recaptcha-response', $captcha);
                }
            }
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We are just setting things up for you, one moment
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'We are just setting things up for you, one moment')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# Unfortunately the Server is currently undergoing scheduled maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Unfortunately the Server is currently undergoing scheduled maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($this->http->FindPreg("/Aufgrund von Serverarbeiten steht unsere Internetseite gegenw.rtig leider nicht zur Verf.gung\./ims")
            || $this->http->FindPreg("/(>Aufgrund von Serverarbeiten steht unsere Internetseite gegenw)/ims")) {
            throw new CheckException("Unfortunately, the server is currently undergoing a scheduled maintenance.", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/

        //# 500 Internal Server Error
        if ($this->http->FindPreg("/500 Internal Server Error/ims")
            || $this->http->FindSingleNode("//h1[@id = 'statusCode' and normalize-space(text()) = '500']")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function setCookieLanguage()
    {
        $this->logger->notice(__METHOD__);
        $this->cookieLanguage = 'de-de';

        if ($cookieLanguage = $this->http->FindPreg("/\/smart\/index\.html\?site=(.*)/ims", false, $this->AccountFields['Login2'])) {
            $this->cookieLanguage = $cookieLanguage;
        } elseif (trim($this->AccountFields['Login2']) != '' && !$this->es) {
            $this->sendNotification("shell. New country as found");
        }
    }

    public function Login()
    {
        if (in_array($this->AccountFields['Login'], self::SLOW_ACCOUNTS)) {
            $this->logger->notice("Very slow authorization on this account");
            $this->curlTimeout = 180;
            $this->http->RetryCount = 0;

            if ($this->AccountFields['Login'] == 70041411794488117) {
                $this->curlTimeout = 300;
            }
            $this->increaseTimeLimit($this->curlTimeout);
        }// if (in_array($this->AccountFields['Login'], self::SLOW_ACCOUNTS))

        if (
            !strstr($this->AccountFields['Login2'], '/smart/index.html?site=en-en')
            && !strstr($this->AccountFields['Login2'], '/smart/index.html?site=bg-bg')
            && !strstr($this->AccountFields['Login2'], '/smart/index.html?site=hu-hu')
            && !strstr($this->AccountFields['Login2'], '/smart/index.html?site=cz-cs')
            && !strstr($this->AccountFields['Login2'], '/smart/index.html?site=de-de')
            && !strstr($this->AccountFields['Login2'], '/smart/index.html?site=sk-sk')
            && !$this->http->PostForm([], $this->curlTimeout)
        ) {
            return $this->checkErrors();
        }

        if (strstr($this->AccountFields['Login2'], '/smart/index.html?site=en-en')
            || strstr($this->AccountFields['Login2'], '/smart/index.html?site=cz-cs')
            || strstr($this->AccountFields['Login2'], '/smart/index.html?site=pl-pl')
            || strstr($this->AccountFields['Login2'], '/smart/index.html?site=sk-sk')
            || strstr($this->AccountFields['Login2'], '/smart/index.html?site=hu-hu')
            || strstr($this->AccountFields['Login2'], '/smart/index.html?site=de-de')
            || strstr($this->AccountFields['Login2'], '/smart/index.html?site=bg-bg')
            || strstr($this->AccountFields['Login2'], '/smart/index.html?site=cz-cs')
            || strstr($this->AccountFields['Login2'], '/smart/index.html?site=sk-sk')
        ) {
            $lang = $this->http->FindPreg('/site=([a-z]{2}-[a-z]{2})/', false, $this->AccountFields['Login2']);
            // api_auth
            $headers = [
                "Accept"       => "application/json, text/plain, */*",
                "Content-Type" => "application/json",
                "x-sso-market" => "en-GB",
                "channel"      => "Web",
            ];

            if ($this->AccountFields['Login2'] == '/smart/index.html?site=cz-cs') {
                $headers['x-sso-market'] = 'cs-CZ';
            }

            if ($this->AccountFields['Login2'] == '/smart/index.html?site=pl-pl') {
                $headers['x-sso-market'] = 'pl-PL';
            }

            if ($this->AccountFields['Login2'] == '/smart/index.html?site=sk-sk') {
                $headers['x-sso-market'] = 'sk-SK';
            }

            if ($this->AccountFields['Login2'] == '/smart/index.html?site=cz-cs') {
                $headers['x-sso-market'] = 'cs-CZ';
            }

            if ($this->AccountFields['Login2'] == '/smart/index.html?site=hu-hu') {
                $headers['x-sso-market'] = 'hu-HU';
            }

            if ($this->AccountFields['Login2'] == '/smart/index.html?site=de-de') {
                $headers['x-sso-market'] = 'de-DE';
            }

            if ($this->AccountFields['Login2'] == '/smart/index.html?site=bg-bg') {
                $headers['x-sso-market'] = 'bg-BG';
            }

            if ($this->AccountFields['Login2'] == '/smart/index.html?site=hu-hu') {
                $headers['x-sso-market'] = 'hu-HU';
            }

            if ($this->AccountFields['Login2'] == '/smart/index.html?site=sk-sk') {
                $headers['x-sso-market'] = 'sk-SK';
            }

            $authCode = $this->http->FindPreg("/authCode=([^&]+)/ims", false, $this->http->currentUrl());

            if ($authCode) {
                $data = [
                    "authCode" => $authCode,
                ];
                $this->http->PostURL("https://id.consumer.shell.com/api/v2/auth/exchangeAuthCode", json_encode($data), $headers);
                $response = $this->http->JsonLog(null, 1, true);
                $this->http->setDefaultHeader("Authorization", "Basic " . ArrayVal($response, 'api_token'));
            // AccountID: AccountID: 2670488
//            } elseif (strstr($this->http->currentUrl(), 'utm_source=SOL_de_SIMenu_SignIn')) {
//                $this->http->setDefaultHeader("Authorization", "Basic 0175bfbc3f6913152144d65a84a47449958556a47bb853c1e8db4a8a8c094a3d");
            } else {
                $this->logger->error("authCode not found");

                $this->http->PostURL("https://id.consumer.shell.com/api/v2/auth/token", '{"digest":"51cd11bde6a5e5f450e2622e87304e3eb04a61a6a3aad1550735b2a40a9689c7"}', $headers);
                $response = $this->http->JsonLog();

                if (!isset($response->token)) {
                    return false;
                }

                $this->http->setDefaultHeader("Authorization", "Basic {$response->token}");
            }

            // login
            $data = [
                "email"    => $this->AccountFields['Login'],
                "password" => $this->AccountFields['Pass'],
                "udid"     => "no-udid-provided",
            ];

            if (
                $this->AccountFields['Login2'] == '/smart/index.html?site=hu-hu'
                || strstr($this->AccountFields['Login2'], '/smart/index.html?site=cz-cs')
                || $this->AccountFields['Login2'] == '/smart/index.html?site=sk-sk'
                || strstr($this->AccountFields['Login2'], '/smart/index.html?site=de-de')
                || strstr($this->AccountFields['Login2'], '/smart/index.html?site=en-en')
            ) {
                $captcha = $this->parseReCaptcha('6LejkmwcAAAAAM9TpwIETWtPysog09SLF6Oi0uuX', true);
            } else {
                $captcha = $this->parseReCaptcha('6LfJA6AUAAAAAPMbwru2U04uINSwWSD6JQtTl0Pe', true);
            }

            if ($captcha !== false) {
                $data['recaptchaToken'] = $captcha;
            }

            $this->http->RetryCount = 0;
            $this->http->PostURL("https://id.consumer.shell.com/api/v2/account/mfa/login", json_encode($data), $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog(null, 5, true);
            $errors = ArrayVal($response, 'errors', []);
            $detail = $title = $otp_required = $data = '';
            $this->logger->debug("detail {$detail}");

            if (!empty($errors) && isset($errors[0])) {
                $detail = ArrayVal($errors[0], 'detail');
                $title = ArrayVal($errors[0], 'title');
                $otp_required = ArrayVal($errors[0], 'otp_required');
                $data = ArrayVal($errors[0], 'data');
            }
            $this->logger->debug("detail {$detail}");

            if ($otp_required == true && $data) {
                $this->captchaReporting($this->recognizer);

                if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                    $this->Cancel();
                }

                $this->State['headers'] = $this->http->getDefaultHeaders() + $headers;
                $this->http->RetryCount = 0;
                $this->http->PostURL("https://id.consumer.shell.com/api/v2/account/mfa/otp/send", json_encode(["otp_token" => $data['otp_token']]), $this->State['headers']);
                $this->http->RetryCount = 2;

                if ($this->http->Response['code'] == 204) {
                    $email = $data['emailAddress'];
                    $question = "Please enter the Code which was sent to the following email address: {$email}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
                    $this->State['otp_token'] = $data['otp_token'];
                    $this->State['lang'] = $lang;
                    $this->AskQuestion($question, null, "Question");
                }

                return false;
            }// if ($otp_required == true && $data)

            if (isset($response['uuid'], $response['accessToken'], $response['loyalty'], $response['loyalty']['activated'])
                && $response['loyalty']['activated'] == true
            ) {
                $this->captchaReporting($this->recognizer);
                $this->State['lang'] = $lang;
                $this->completeAuth($response);
            }// if (isset($response['uuid']))
            // This card number is not recognised. Please try again, making sure you have no spaces.
            elseif ($detail === 'invalid_email_address') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("This card number is not recognised. Please try again, making sure you have no spaces.", ACCOUNT_INVALID_PASSWORD);
            } // The credentials are invalid, please try again
            elseif ($this->http->FindPreg('/\{"code":400,"fields":\{"signInForm":\["incorrect_username_or_password_please_try_again"\]\}/', false, $detail)) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException('The credentials are invalid, please try again', ACCOUNT_INVALID_PASSWORD);
            } elseif ($this->http->FindPreg('/,"fields":\{"signInForm":\["sso_forms_errors_incorrect_username_or_password_please_try_again"\]\}/', false, $detail)
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException('The credentials are invalid, please try again', ACCOUNT_INVALID_PASSWORD);
            } elseif ($detail === 'account_locked' || $detail === '{"message":"account_locked","code":400}') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Account locked", ACCOUNT_LOCKOUT);
            } // We’ve emailed ... . To confirm please follow the instructions within the email.
            elseif ($detail === 'email_not_verified') {
                $this->captchaReporting($this->recognizer);
                $this->throwProfileUpdateMessageException();
            } elseif (
                $detail === 'some_inputs_are_invalid'
                || $detail === 'sso_forms_errors_invalid_email_address'
            ) {
                $this->captchaReporting($this->recognizer);

                $data = [
                    "username" => $this->AccountFields['Login'],
                    "password" => $this->AccountFields['Pass'],
                    "market"   => $headers['x-sso-market'],
                ];
                $this->http->PostURL("https://id.consumer.shell.com/api/sol/v1/sso/migration", json_encode($data), $headers);
                $response = $this->http->JsonLog();

                if (!isset($response->fields->email)) {
                    if (isset($response->response_status)) {
                        if (in_array($response->response_status, [-10000, -10001])) {
                            throw new CheckException("The username and password don’t match. Please try again.", ACCOUNT_INVALID_PASSWORD);
                        }
                        // Account already migrated
                        if ($response->response_status == -2501) {
                            throw new CheckException("Account already migrated", ACCOUNT_INVALID_PASSWORD);
                        }
                        // The username and password don’t match. Please try again.
                        if ($response->response_status == -11) {
                            throw new CheckException("The username and password don’t match. Please try again.", ACCOUNT_INVALID_PASSWORD);
                        }
                        // Card blocked
                        if ($response->response_status == -32) {
                            throw new CheckException("Card blocked", ACCOUNT_LOCKOUT);
                        }
                        // Your card has been blocked for security reasons. Please try again in 1 hour.
                        if ($response->response_status == -33) {
                            throw new CheckException("Your card has been blocked for security reasons.", ACCOUNT_INVALID_PASSWORD);
                        }
                    }// if (isset($response->response_status))
                    // AccountID: 1658827
                    if (isset($response->fault->detail->errorcode) && $response->fault->detail->errorcode == "messaging.adaptors.http.flow.GatewayTimeout") {
                        throw new CheckException("The email address and password do not match. Please try again.", ACCOUNT_INVALID_PASSWORD);
                    }
                    // The email address and password do not match. Please try again.
                    if ($this->http->FindPreg("/\{\"code\":400,\"message\":\"some_inputs_are_invalid\",\"fields\":\{\"market\":\[\"market_is_not_valid\"\]\}\}/")) {
                        throw new CheckException("The email address and password do not match. Please try again.", ACCOUNT_INVALID_PASSWORD);
                    }

                    // strange provider response
                    if ($this->http->FindPreg("/^\"\"$/")) {
                        $this->captchaReporting($this->recognizer);
                        $this->sendNotification("empty response // RR");
                        $this->http->PostURL("https://id.consumer.shell.com/api/sol/v1/sso/migration", json_encode($data), $headers);
                        $response = $this->http->JsonLog();
                        /*
                        throw new CheckRetryNeededException(3, 5);
                        */
                    }

                    return false;
                }// if (!isset($response->fields->email))
                $data = [
                    "email"  => $response->fields->email,
                    "market" => "en-GB",
                ];

                if (isset($response->version) && $response->version == "2.0") {
                    $this->http->PostURL("https://id.consumer.shell.com/api/account/email/check", json_encode($data), $headers);
                } else {
                    $this->http->PostURL("https://id.consumer.shell.com/api/sso/check_email", json_encode($data), $headers);
                }
                $response = $this->http->JsonLog(null, 1, true);

                if ($this->http->FindPreg("/\{\"stat\":\"(ok|error)\",\"exists\":(true|false)\}/")
                    || $this->http->FindPreg("/\{\"code\":400,\"message\":\"some_inputs_are_invalid\",\"fields\":\{\"email\":\[\"email_address_is_required\",\"invalid_email_address\"\]\}\}/")
                    || $this->http->FindPreg("/\{\"code\":400,\"message\":\"some_inputs_are_invalid\",\"fields\":\{\"email\":\[\"invalid_email_address\"\]\}\}/")
                    || $this->http->FindPreg("/\{\"status\":\"2\"\}/")) {
                    $this->throwProfileUpdateMessageException();
                }
            }// elseif ($message == 'some_inputs_are_invalid')
            // Please enter your Shell Drivers' Club card number
            elseif (isset($response['loyalty']['activated']) && $response['loyalty']['activated'] == false
                && $response['loyalty']['cardId'] == null && $response['loyalty']['cardStatus'] == null
            ) {
                $this->captchaReporting($this->recognizer);
                $this->throwProfileUpdateMessageException();
            } elseif (// AccountID: 4100302
                isset($response['loyalty']['activated']) && $response['loyalty']['activated'] == false
                && $this->http->FindPreg("/\"userStatus\":\"UNVERIFIED\",\"isAccountBlocked\":false/")
                && $this->http->FindPreg("/\"cardId\":\"\d+\",\"cardStatus\":\"ACTIVE\",\"migrationOption\":\"MIGRATE\",/")
            ) {
                $this->captchaReporting($this->recognizer);
                $this->throwAcceptTermsMessageException();
            // AccountID: 6181396
            } elseif ($detail === '{"message":"login_failed","emailAddress":"' . $this->AccountFields['Login'] . '","fields":{"marketCode":["invalid_market_code"]}}') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Login failed. You do not have an account in this market. Please register a new account or change the language.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->FindPreg("/\{\"status\":401,\"title\":\"Unauthorized\",\"source\":\{\"pointer\":\"recaptchaToken\"\},\"detail\":0/")) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }
        }// if (strstr($this->AccountFields['Login2'], '/smart/index.html?site=en-en'))
        else {
            if ($this->es) {
                if ($this->http->FindSingleNode("//a[contains(text(), 'Desconectar')]")) {
                    return true;
                }
                // The username or password are incorrect.
                // <span class="field-validation-error text-danger" data-valmsg-for="" data-valmsg-replace="true">Credenciales incorrectas</span>
                if ($message = $this->http->FindPreg('/<span class="field-validation-error.+?>(Credenciales incorrectas)</us')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                $this->setCookieLanguage();
            }// elseif ($this->es)
            // new site
            elseif ($this->AccountFields['Login2'] == 'https://www.allsmart.gr') {
                // Το πεδίο Email δεν είναι έγκυρο
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Το πεδίο Email δεν είναι έγκυρο')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // GDPR
                if ($this->http->FindSingleNode("//p[contains(text(), 'Συμμόρφωση με τον νέο Γενικό Κανονισμό Προστασίας Δεδομένων (GDPR)')]")
                    && $this->http->FindSingleNode("//h3[contains(text(), 'Επιλέξτε για να συνεχίσετε')]")) {
                    $this->throwAcceptTermsMessageException();
                }

                $this->http->GetURL($this->AccountFields['Login2'] . '/account/o-logariasmos-mou/');

                return !empty($this->http->getCookieByName('UserContext'));
            }
        }

        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//div[contains(@id, 'error_message_container_')]")) {
            if (strstr($message, 'Введите символы, изображенные на картинке')
                || strstr($message, 'Мы не смогли распознать введённые вами данные. Пожалуйста, введите проверочный код с картинки.')) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 1);
            }
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }// if ($message = $this->http->FindSingleNode("//div[contains(@id, 'error_message_container_')]"))
        $message = $this->http->FindSingleNode("//div[@id = 'system_message']");
        $this->logger->error("[Error]: {$message}");
        // Ihre Karte wurde gesperrt. Bitte rufen Sie den Kundenservice an
        if (strstr($message, 'Ihre Karte wurde gesperrt. Bitte rufen Sie den Kundenservice an')
            // Ваша карта заблокирована. Обратитесь в службу поддержки.
            || strstr($message, 'Ваша карта заблокирована. Обратитесь в службу поддержки.')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Your card has been disabled. Please call Customer Services
        if (strstr($message, 'Your card has been disabled. Please call Customer Services')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // captcha error
        if (
            strstr($message, 'Ihr Login Versuch war leider nicht erfolgreich. Bitte geben Sie das CAPTCHA zur Verifizierung ein und versuchen Sie es erneut.')
            || strstr($message, 'Мы не смогли распознать введённые вами данные. Пожалуйста, введите проверочный код с картинки.')
        ) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
        }
        // Operation failed
        if ($message == 'Operation failed') {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Login
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        /*
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error') and not(@style)]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        */

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->AccountFields['Login2'] == 'https://www.wing.gr/smart/index.html?site=gr-el') {
            $this->http->GetURL('https://www.wing.gr/smart/AccountDetail.html?site=gr-el&mod=SMART');

            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//p[@id = "name_label"]')));

            if ($Reserviert = $this->http->FindNodes("//table//td[@id='data_value_cell2']/span")) {
                if ($this->AccountFields['Login2'] == 'https://www.wing.gr/smart/index.html?site=gr-el') {
                    $this->SetProperty("AvailableBalance", $Reserviert[0]);
                } else {
                    $this->SetProperty("ReservedForCallPremiums", $Reserviert[0]);
                }
            }

            if (isset($Reserviert[1])) {
                $this->SetProperty("ReservedForAuctions", $Reserviert[1]);
            }
            // Balance
            if ($Balance = $this->http->FindNodes("//table//td[@id='data_value_cell1']/span")) {
                $this->SetBalance($Balance[0]);

                if (isset($Balance[1])) {
                    $this->SetProperty("AvailableBalance", $Balance[1]);
                }
            }

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                if ($message = $this->http->FindPreg("/Thank you for registering to SMART Club Online\.\s*Your registration is currently being processed[^<]+/ims")) {
                    throw new CheckException($message, ACCOUNT_WARNING);
                }
            }
        } elseif ($this->AccountFields['Login2'] == 'https://www.allsmart.gr') {
            $userContext = urldecode($this->http->getCookieByName('UserContext'));
            $userContext = $this->http->JsonLog($userContext, false);

            // Balance - οι πόντοι σου
            if (isset($userContext->Identity->PointsValue)) {
                $this->SetBalance($userContext->Identity->PointsValue);
            }
            // Name
            if (isset($userContext->Identity->Name)) {
                $this->SetProperty("Name", beautifulName($userContext->Identity->Name));
            }
        } elseif ($this->es) {
            // Balance - Mis puntos
            $this->SetBalance($this->http->FindSingleNode("//span[@id = 'ctl00_ContenidoCentral_lblPuntos']"));

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                if ($this->http->FindSingleNode("//h1[contains(text(), 'Es necesario validar su cuenta para continuar')]")) {
                    $this->throwProfileUpdateMessageException();
                }
            }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

            $this->http->GetURL("http://www.tarjetashellclubsmart.es/Private/ModificarDatos.aspx");
            // Name
            $this->SetProperty("Name", beautifulName(Html::cleanXMLValue($this->http->FindSingleNode('//input[@name = "ctl00$ContenidoCentral$txtNombreRegistro"]/@value') . ' ' . $this->http->FindSingleNode('//input[@name = "ctl00$ContenidoCentral$txtPrimerApellido"]/@value') . ' ' . $this->http->FindSingleNode('//input[@name = "ctl00$ContenidoCentral$txtSegundoApellido"]/@value'))));
            // Número de tarjeta
            $this->SetProperty("AccountNumber", $this->http->FindSingleNode('//input[@name = "ctl00$ContenidoCentral$txtNumeroTarjetaRegistro"]/@value'));
        }// elseif ($this->es)
        elseif (strstr($this->AccountFields['Login2'], '/smart/index.html?site=en-en')) {
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//label[@id = "loggedin_activator_label"]')));
            // Balance - ... visits
            $visits = $this->http->FindNodes('//div[@id = "chartGauge"]/div/div[contains(@id, "bar") and not(contains(@class, "empty"))]');

            if ($visits || count($this->http->FindNodes('//div[@id = "chartGauge"]/div/div[contains(@id, "bar") and contains(@class, "empty")]')) == 10) {
                $this->SetBalance(count($visits));
            }
            // Visits Until Next Reward
            $this->SetProperty("VisitsUntilNextReward", $this->http->FindSingleNode('//span[@id = "acc_site_visit_awarded_text_bold"]', null, true, "/\d+\s*visits?/"));
            // Total Saved with Shell Go+ : £2.00
            $this->SetProperty("TotalSaved", $this->http->FindSingleNode('//div[@id = "user_total_saved_amount"]/text()[last()]'));

            // YOUR REWARDS
            $rewards = $this->http->XPath->query("//div[@id = 'offerDataContainer']");
            $this->logger->debug("Total {$rewards->length} rewards were found");

            foreach ($rewards as $reward) {
                $balance = $this->http->FindSingleNode('.//div[@id = "fuelOffAmount"]', $reward);
                $displayName = $this->http->FindSingleNode('.//div[@id = "offerText"]', $reward, true, "/^£[\d\.\s]+(.+)/");
                $exp = $this->http->FindSingleNode('.//div[@id = "offerDetailText"]', $reward, true, "/Expires?\s*(.+)/");
                $this->AddSubAccount([
                    'Code'           => 'shellRewards' . md5($displayName . $balance) . strtotime($exp),
                    'DisplayName'    => $displayName,
                    'Balance'        => $balance,
                    'ExpirationDate' => strtotime($exp),
                ]);
            }
        } else {
            // Expiration date  // refs #17445

            // Expiring balance
            $expiringBalance = $this->http->FindSingleNode('//span[contains(@id, "sum_points_")]', null, false, self::BALANCE_REGEXP);
            $this->SetProperty("ExpiringBalance", $expiringBalance);

            if (!empty($expiringBalance)) {
                $expDate = $this->http->FindSingleNode('//span[contains(@id, "detail_name_")]/span[1]');
                $this->logger->debug("[Exp date]: {$expDate}");

                if (!$this->expDateVerified($expDate)) {
                    $this->sendNotification("refs #17445. exp date was found");
                } else {
                    $this->SetExpirationDate(strtotime($expDate));
                }
            }

            if ($this->AccountFields['Login2'] == "/smart/index.html?site=it-it") {
                $this->http->GetURL("https://loyalty.shell-italia.it/smart/account/manage_cards?site=it-it");
            } else {
//                $this->http->getURL('https://www.shellsmart.com/smart/account/manage_cards?site=' . $this->cookieLanguage);
                $url = '/smart/account/manage_cards?site=' . $this->cookieLanguage;
                $this->http->NormalizeURL($url);

                if (in_array($this->AccountFields['Login'], self::SLOW_ACCOUNTS)) {
                    $this->increaseTimeLimit(180);
                }

                $this->http->GetURL($url, [], $this->curlTimeout); // very slow authorization on this account (login: '70041411794488117')
            }
            //# Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[@id = "customer_name"]')));
            //# Account Number
            $number = $this->http->FindSingleNode('//div[@id = "card_no"]');

            if (stristr($number, 'Shell')) {
                $this->logger->notice("fixed wrong AccountNumber: {$number}");
                $number = $this->http->FindPreg('/\:\s*(.+)/', false, $number);
            }
            $this->SetProperty("AccountNumber", $number);
            //# Balance
            $this->SetBalance($this->http->FindSingleNode('//span[@id = "point_amount"]', null, true, self::BALANCE_REGEXP_EXTENDED));
        }
    }

    public function ProcessStep($step)
    {
        $data = [
            "code"      => $this->Answers[$this->Question],
            "otp_token" => $this->State['otp_token'],
        ];
        unset($this->Answers[$this->Question]);

        if ($this->AccountFields['Login2'] == '/smart/index.html?site=hu-hu') {
            $this->sendNotification('refs #21824 Hungary - entered one time code from email');
        }
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://id.consumer.shell.com/api/v2/account/2fa/otp/verify", json_encode($data), $this->State['headers']);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 5, true);
        $errors = ArrayVal($response, 'errors', []);
        $detail = $title = '';
        $this->logger->debug("detail {$detail}");

        if (!empty($errors) && isset($errors[0])) {
            $detail = ArrayVal($errors[0], 'detail');
            $title = ArrayVal($errors[0], 'title');
        }

        if ($detail == 'Code is invalid or expired') {
            $this->AskQuestion($this->Question, "Invalid code, please try again.", "Question");

            return false;
        }

        return $this->completeAuth($response);
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@name = 'login_form']//div[@class = 'g-recaptcha']/@data-sitekey");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function parseReCaptcha($key, $isV3 = false)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;

        if (
            $this->AccountFields['Login2'] == '/smart/index.html?site=hu-hu'
            || strstr($this->AccountFields['Login2'], '/smart/index.html?site=cz-cs')
            || $this->AccountFields['Login2'] == '/smart/index.html?site=sk-sk'
            || strstr($this->AccountFields['Login2'], '/smart/index.html?site=de-de')
            || strstr($this->AccountFields['Login2'], '/smart/index.html?site=en-en')
        ) {
            $postData = [
                "type"       => "RecaptchaV3TaskProxyless",
                "websiteURL" => "https://login.consumer.shell.com/login",
                "websiteKey" => $key,
                "minScore"   => 0.7,
                //                "pageAction" => "login",
            ];
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $this->recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        }

        $parameters = [
            "pageurl" => "https://login.consumer.shell.com/login",
            "proxy"   => $this->http->GetProxy(),
        ];

        if ($isV3) {
            $parameters += [
                "version"   => "v3",
                //"action"    => "undefined",
                "min_score" => 0.7,
            ];
        }

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            strstr($this->AccountFields['Login2'], '/smart/index.html?site=en-en')
            && $this->http->FindSingleNode("//a[@id = 'menuLogoutLink']/@id")
        ) {
            return true;
        } elseif (
            $this->http->FindSingleNode("//a[@id='logout_link']")
            || $this->http->FindSingleNode("//a[contains(@href,'mylogout')]/@href")
        ) {
            return true;
        }

        return false;
    }

    private function expDateVerified(&$date)
    {
        $this->logger->notice(__METHOD__);
        $supportedRegions = [
            "https://www.shellsmart.com/smart/index.html?site=de-de",
            "/smart/index.html?site=de-de", // Germany
            "/smart/index.html?site=ru-ru", // Russia
            "/smart/index.html?site=th-en", // Thailand
            "/smart/index.html?site=pl-pl", // Poland
            "/smart/index.html?site=sk-sk", // Slovakia
            "/smart/index.html?site=sg-en", // Singapore
            "/smart/index.html?site=cz-cs", // Czech
        ];

        $covertDateForRegions = [
            "/smart/index.html?site=th-en", // Thailand
            "/smart/index.html?site=sg-en", // Singapore
        ];

        if (in_array($this->AccountFields['Login2'], $covertDateForRegions)) {
            $date = $this->ModifyDateFormat($date);
        }

        return in_array($this->AccountFields['Login2'], $supportedRegions);
    }

    private function completeAuth($response)
    {
        $this->logger->notice(__METHOD__);

        if (isset($response['uuid'], $response['accessToken'], $response['loyalty'], $response['loyalty']['activated'])
            && $response['loyalty']['activated'] == true
        ) {
            // refs #18430
            if (strstr($this->AccountFields['Login2'], '/smart/index.html?site=en-en')) {
                if ($this->http->FindPreg("/\"loyalty\":\{\"accounts\":\[\],\"activated\":true,/")) {
                    $this->logger->notice("need to accept 'Terms and Conditions'");
                    $this->throwAcceptTermsMessageException();
                }

                $headers['Authorization'] = "Bearer {$response['accessToken']}";
                $this->http->GetURL("https://id.consumer.shell.com/api/v2/auth/accessCode", $headers);
                $accessCodeResponse = $this->http->JsonLog();
                $accessToken = $accessCodeResponse->accessCode ?? null;

                if (!$accessToken) {
                    $this->logger->error("accessCode not found");

                    return false;
                }

                $this->http->GetURL("https://www.goplus.shell.com/en-gb/sso/login/return?lod_state=MY_REWARDS&accessCode={$accessToken}");

                if ($this->loginSuccessful()) {
                    return true;
                }

                return false;
            }

            $headers['Authorization'] = 'Bearer ' . $response['accessToken'];
            $this->http->GetURL("https://id.consumer.shell.com/api/v2/auth/accessCode", $headers);
            $response = $this->http->JsonLog(null, 1, true);
            $accessCode = ArrayVal($response, 'accessCode');

            if (!$accessCode) {
                $this->logger->error("accessCode not found");

                return false;
            }
            // increase timeout for AccountID: 1875971
            $this->http->GetURL("https://www.shellsmart.com/smart/sso/login/return?site={$this->State['lang']}&accessCode={$accessCode}", [], 120);
        }

        return $this->loginSuccessful();
    }
}
