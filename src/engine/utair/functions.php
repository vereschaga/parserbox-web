<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerUtair extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    //use OtcHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->setProxyBrightData(null, 'static', 'ru');
//        $this->setProxyBrightData(null, "dc_ips_ru", "ru");
        $this->setProxyGoProxies();
//        $this->setProxyBrightData(null, "rotating_residential", "ru");
//        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State["authorization"])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        //$this->http->GetURL("https://www.utair.ru/status/about");

        /* if ($this->http->Response['code'] != 200) {
             return $this->checkErrors();
         }*/

        $data = [
            //"application_access" => "true",
            "client_id"          => "website_client",
            //"client_secret"      => 'nA2REtuw$a-uZ?R3sw&s5A!UW2veDU3U', // a.CLIENT_SECRET <- https://beta.utair.ru/static/js/script.20171004-00.bundle.js
            "grant_type"         => "client_credentials",
        ];
        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "application/x-www-form-urlencoded",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://b.utair.ru/oauth/token", $data, $headers); // not required
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, true);
        $access_token = ArrayVal($response, 'access_token');
        $token_type = ArrayVal($response, 'token_type');

        $this->State["authorization"] = "{$token_type} {$access_token}";
        $this->selenium();

        $data = [
            "login"             => $this->AccountFields['Login'],
            "confirmation_type" => "standard",
        ];

        return true;

        $captcha = $this->parseReCaptcha('6Lc_4asUAAAAANLBRFZfS9kcsu5BhW3bxsS5TZo9');

        if ($captcha === false) {
            return $this->checkErrors();
        }

        $headers = [
            "Accept"               => "*/*",
            "Content-Type"         => "application/json",
            "g-recaptcha-response" => $captcha,
            "Origin"               => "https://www.utair.ru",
            "traceparent"          => "00-20a2e0ace7133d15f96db9fab1ceaedc-bb280ddfca3c1017-01",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://b.utair.ru/api/v1/login/", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function parseReCaptcha($key)
    {
        $this->http->RetryCount = 0;
        // https://www.utair.ru/static/js/main.de556a83.js
        //$key = $this->http->FindPreg('/recaptcha\/api\.js\?render=([^&"\']+)/');

        if (!$key) {
            return false;
        }

//        $postData = [
//            "type"         => "RecaptchaV3TaskProxyless",
//            "websiteURL"   => $currentURL ?? $this->http->currentUrl(),
//            "websiteKey"   => $key,
//            "minScore"     => 0.9,
//            "pageAction"   => "homepage",
//            "isEnterprise" => true,
//        ];
//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
//        $this->recognizer->RecognizeTimeout = 120;
//
//        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => 'https://www.utair.ru/status/about',
            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            "action"    => "homepage",
            "min_score" => 0.3,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        // На почту {$email} отправлена ссылка для авторизации.
        if ($this->http->FindPreg("/\"channel\":\s*\"email\"/")) {
            $question = "Please enter the confirmation code which was sent to your email."; /*review*/
        }
        // Мы отправили код подтверждения вам на телефон
        elseif ($this->http->FindPreg("/\"channel\":\s*\"phone\"/")) {
//            $question = "Please enter the confirmation code which was sent to your phone";/*review*/
            $question = "Please enter the last 4 digits of the incoming call number"; /*review*/
        } else {
            return false;
        }
        $response = $this->http->JsonLog(null, 0, true);
        $this->State['attempt_id'] = ArrayVal($response, 'attempt_id');
        $this->State['confirm_location'] = ArrayVal($response, 'confirm_location');

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $data = [
            "attempt_id" => $this->State['attempt_id'],
            "code"       => $this->Answers[$this->Question],
        ];
        $headers = [
            "Content-Type"      => "application/json",
            "Accept"            => "*/*",
            "authorization"     => $this->State["authorization"],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($this->State['confirm_location'], json_encode($data), $headers);
        $this->http->RetryCount = 2;
        unset($this->Answers[$this->Question]);
        $response = $this->http->JsonLog(null, 3, true);
        $session = ArrayVal($response, 'session', null);

        if (!$session) {
            $error_message = ArrayVal(ArrayVal($response, 'meta', null), 'error_message', null);

            if ($error_message == 'Invalid confirm credentials') {
                $this->AskQuestion($this->Question, "Verify your confirmation number", "Question");

                return false;
            }

            return false;
        }
        $data = [
            "grant_type" => "password",
            "username"   => $this->AccountFields['Login'],
            "password"   => $session,
        ];
        $headers = [
            "content-type"  => "application/x-www-form-urlencoded",
            "Accept"        => "*/*",
            // website_client:nA2REtuw$a-uZ?R3sw&s5A!UW2veDU3U <- base 64 client_secret
            "authorization" => "Basic d2Vic2l0ZV9jbGllbnQ6bkEyUkV0dXckYS11Wj9SM3N3JnM1QSFVVzJ2ZURVM1U=",
        ];
        $this->http->PostURL("https://b.utair.ru/oauth/token", $data, $headers);

        $this->checkProviderErrors();

        $response = $this->http->JsonLog(null, 3, true);
        $access_token = ArrayVal($response, 'access_token');
        $token_type = ArrayVal($response, 'token_type');

        $this->State["authorization"] = "{$token_type} {$access_token}";

        return $this->loginSuccessful();
    }

    public function Login()
    {
        $this->http->JsonLog();

        if ($this->parseQuestion()) {
            return false;
        }
        $this->checkProviderErrors();
        // Access is allowed
        if ($this->http->FindNodes("//a[contains(@href, 'signout')]")) {
            $this->captchaReporting($this->recognizer);

            $response = $this->http->JsonLog(null, 3, true);
            $access_token = ArrayVal($response, 'access_token');
            $token_type = ArrayVal($response, 'token_type');

            $this->State["authorization"] = "{$token_type} {$access_token}";

            return true;
        }
        // К сожалению, не удалось найти пользователя с таким номером карты, телефона или email.
        if ($message = $this->http->FindPreg("/\{\"errorCode\":404,\"error_message\":\"User not Found!\"\}/")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("К сожалению, не удалось найти пользователя с таким номером карты, телефона или email.", ACCOUNT_INVALID_PASSWORD);
        }
        // Пользователь с таким логином не найден
        if ($this->http->FindPreg("/\"error_code\":\s*40101,(?:\s*\"error_data\":null,|)\s*\"error_message\":\s*\"Invalid user credentials\"/")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Пользователь с таким логином не найден", ACCOUNT_INVALID_PASSWORD);
        }
        // Пользователь отключил авторизацию через SMS
        if ($message = $this->http->FindPreg("/\"error_code\":\s*40104,(?:\s*\"error_data\":null,|)\s*\"error_message\":\s*\"Phone login disabled by user\"/")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Пользователь отключил авторизацию через SMS", ACCOUNT_INVALID_PASSWORD);
        }
        // Слишком много попыток. Попробуйте позднее
        if ($message = $this->http->FindPreg("/\"error_code\":\s*40301,(?:\s*\"error_data\":null,|)\s*\"error_message\":\s*\"Too many attempts!\"/")) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(2, 5, "Слишком много попыток. Попробуйте позднее", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/\"error_code\":40304,(?:\s*\"error_data\":null,|)\"error_message\":\"Not human behavior\"/")) {
            $this->captchaReporting($this->recognizer, false);
            $this->ErrorReason = self::ERROR_REASON_BLOCK;
            $this->DebugInfo = self::ERROR_REASON_BLOCK;

            throw new CheckRetryNeededException(2, 10);
        }

        // Невозможно отправить СМС на номер в вашем профиле
        if ($message = $this->http->FindPreg("/\"error_code\":\s*40030,(?:\s*\"error_data\":null,|)\s*\"error_message\":\s*\"Invalid phone number\"/")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Слишком много попыток. Попробуйте позднее", ACCOUNT_PROVIDER_ERROR);
        }
        // Ваша учетная запись заблокирована
        if ($message = $this->http->FindPreg("/\{\s*\"error_code\":\s*40302,(?:\s*\"error_data\":null,|)\s*\"error_message\":\s*\"account status: Suspended\"/")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Ваша учетная запись заблокирована.", ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0, true);
        $status = ArrayVal($response, 'status');
        // Card number
        $this->SetProperty("Number", ArrayVal($status, 'cardNo'));
        // Name
        $initials = ArrayVal($response, 'initials');
        $international = ArrayVal($initials, 'international');
        $this->SetProperty("Name", Html::cleanXMLValue(beautifulName(ArrayVal($international, 'name') . " " . ArrayVal($international, 'secondName') . " " . ArrayVal($international, 'surname'))));

        $headers = [
            "content-type"      => "application/json",
            "Accept"            => "*/*",
            "authorization"     => $this->State["authorization"],
            "x-utair-signature" => "AVOIhouVxqlmT+vCn5K2fgVnsHpX3YtOe1bNe8jMw7w=",
        ];
        $this->http->GetURL("https://b.utair.ru/api/v1/profile/", $headers);
        $response = $this->http->JsonLog(null, 3, true);
        $status = ArrayVal($response, 'status');
        // Card number
        $this->SetProperty("Number", ArrayVal($status, 'cardNo'));
        // Name
        $initials = ArrayVal($response, 'initials');
        $international = ArrayVal($initials, 'international');
        $this->SetProperty("Name", Html::cleanXMLValue(beautifulName(ArrayVal($international, 'name') . " " . ArrayVal($international, 'secondName') . " " . ArrayVal($international, 'surname'))));

        $headers = [
            "content-type"      => "application/json",
            "Accept"            => "*/*",
            "authorization"     => $this->State["authorization"],
            "x-utair-signature" => "J9CLgdNoXgW+qkventcFaEYyPXbmhRIfh7HrZStIZRo=",
        ];
        $this->http->GetURL("https://b.utair.ru/api/v1/bonus/balance/", $headers);
        $response = $this->http->JsonLog(null, 3, true);
        // Balance - ... миль
        $this->SetBalance(ArrayVal($response, 'redemption'));
        // Tier level
        $this->SetProperty("Tier", beautifulName(ArrayVal($response, 'level')));
        /*
        // Miles to next level
        $this->SetProperty("MilesToNextLevel", ArrayVal($response, 'mileToNextLevel'));
        // Flights to next level
        $this->SetProperty("FlightsToNextLevel", ArrayVal($response, 'flightsToNextLevel'));
        */
        // Spend to next level
        $this->SetProperty("SpendToNextLevel", ArrayVal($response, 'rubleToNextLevel'));
        // Spent to next level
        $this->SetProperty("SpentToNextLevel", ArrayVal($response, 'redemption'));

        // Status expiration
        $levelExpire = ArrayVal($response, 'levelExpire', null);

        if ($levelExpire) {
            $this->SetProperty("StatusExpiration", date("d M Y", strtotime($levelExpire)));
        }

        // Expiration Date - Дата аннулирования миль
//        $exp = $this->http->FindSingleNode("//small[contains(text(), 'Дата аннулирования миль')]", null, true, "/\:\s*([^<]+)/");
//        if ($exp = strtotime($exp))
//            $this->SetExpirationDate($exp);
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;

            $selenium->useChromePuppeteer();
            $selenium->seleniumOptions->recordRequests = true;

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->removeCookies();
            $selenium->http->GetURL('https://www.utair.ru/bonuses');

            $email = $selenium->waitForElement(WebDriverBy::xpath('//label[@for="email"]'), 7);
            $this->savePageToLogs($selenium);

            if ($email) {
                $email->click();
                sleep(2);
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@data-testid="InputSelector-input"]'), 7);
            $this->savePageToLogs($selenium);

            if (!$login) {
                return false;
            }

            $login->sendKeys($this->AccountFields['Login']);

            $button = $selenium->waitForElement(WebDriverBy::xpath('//form[contains(@class, "Account-module__form")]//button[normalize-space(text()) = "Войти" and not(@disabed)]'), 5);
            $this->savePageToLogs($selenium);

            if (!$button) {
                return false;
            }

            $button->click();
            $selenium->waitForElement(WebDriverBy::xpath('//h3[contains(text(), "Введите код")]'), 7);
            $this->savePageToLogs($selenium);

            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));

                if (strpos($xhr->request->getUri(), 'api/v1/login/') !== false) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $responseData = json_encode($xhr->response->getBody());
                }
            }

            if (!empty($responseData)) {
                $this->http->SetBody($responseData);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return null;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $headers = [
            "content-type"      => "application/json",
            "Accept"            => "*/*",
            "authorization"     => $this->State["authorization"],
            "x-utair-signature" => "AVOIhouVxqlmT+vCn5K2fgVnsHpX3YtOe1bNe8jMw7w=",
        ];
        $this->http->GetURL("https://b.utair.ru/api/v1/profile/", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, true);
        $email = ArrayVal($response, 'email');
        $phone = ArrayVal($response, 'phone');
        $status = ArrayVal($response, 'status');
        $number = ArrayVal($status, 'cardNo');

        $this->logger->debug("[email]: {$email}");
        $this->logger->debug("[phone]: {$phone}");
        $this->logger->debug("[number]: {$number}");

        if (
            $response
            && (
                ($email && strtolower($email) == strtolower($this->AccountFields['Login']))
                || ($phone && preg_replace(['/^\+7/', '/^8/'], '', $phone) == preg_replace(['/^\+7/', '/^7/', '/^8/'], '', $this->AccountFields['Login']))
                || ($number && $number == $this->AccountFields['Login'])
            )
        ) {
            return true;
        }

        return false;
    }

    private function checkProviderErrors()
    {
        $this->logger->notice(__METHOD__);
        // Ваша почта осталась прежней?
        if ($this->http->FindSingleNode("//p[contains(text(), 'Пожалуйста, подтвердите свой адрес для доступа в личный кабинет СТАТУС:')]")
            // Подтвердить номер телефона
            || $this->http->FindSingleNode("//p[contains(text(), 'Это нужно, чтобы мы могли отправлять смс об операциях по вашему счету и узнавать вас при звонке в контакт центр.')]")) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }

        return false;
    }
}
