<?php

// Feature #5659

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSportmaster extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public const REWARDS_PAGE_URL = "https://www.sportmaster.ru/user/profile/bonus.do";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $headers = [
        "Accept"               => "application/json, text/plain, */*",
        "Content-Type"         => "application/json;charset=utf-8",
        "X-SM-Tracing-Id"      => "8edcab76-697344",
        "X-SM-Accept-Language" => "ru-RU",
        "Referer"              => "https://www.sportmaster.ru/profile/bonuses/",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
//        $this->http->SetProxy($this->proxyReCaptchaIt7());
        $this->setProxyBrightData(null, "dc_ips_ru", "ru");
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false || $this->AccountFields['Login'] == 1) {
            throw new CheckException("Неверный формат телефона", ACCOUNT_INVALID_PASSWORD);
        }

        if (str_starts_with($this->AccountFields['Login'], "375")) {
            throw new CheckException("К сожалению, в данный момент мы поддерживаем программу Спортмастер только через этот сайт: https://www.sportmaster.ru/", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->selenium();
//        $this->http->GetURL("https://www.sportmaster.ru/profile/bonuses/");
        $this->http->RetryCount = 2;

//        $captcha = $this->parseReCaptcha();
//
//        if ($captcha === false) {
//            return false;
//        }

        $data = [
            "phone"       => str_replace('+', '', $this->AccountFields['Login']),
            "typeChannel" => "SMS",
            //            "token"       => $captcha,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.sportmaster.ru/web-api/v1/auth/phone/codes/', json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->codeLength)) {
            $this->captchaReporting($this->recognizer);
            $this->AskQuestion("Введите код подтверждения", null, "Question");

            return false;
        }

        return false;
    }

    public function ProcessStep($step)
    {
//        $captcha = $this->parseReCaptcha($this->State['key']);
//
//        if ($captcha === false) {
//            return false;
//        }

        $ansver = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $data = [
            "code"  => $ansver,
            "phone" => $this->AccountFields['Login'],
            //            "token" => $captcha,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.sportmaster.ru/web-api/v1/auth/phone/codes/_verify/', json_encode($data), $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->step) || $response->step != 'AUTHORIZATION') {
            if (isset($response->reason) && $response->reason == 'INCORRECT_CODE') {
                $this->captchaReporting($this->recognizer);
                $this->AskQuestion($this->Question, "Неверный код подтверждения", "Question");
            }

            if (isset($response->step) && $response->step == 'REGISTRATION') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $this->captchaReporting($this->recognizer);

        $data = [
            "login" => $this->AccountFields['Login'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.sportmaster.ru/web-api/v1/auth/phone/_login/", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->ga->userId)) {
            return false;
        }

        return $this->loginSuccessful();
    }

    public function Parse()
    {
        // Имя, Фамилия
        $response = $this->http->JsonLog(null, 0);
        $this->SetProperty("Name", beautifulName($response->firstName . " " . $response->lastName));

        $this->http->GetURL("https://www.sportmaster.ru/web-api/v1/profiles/current/bonuses/", $this->headers);
        $response = $this->http->JsonLog();

        // Уровень вашей бонусной программы
        $this->SetProperty("Status", $response->currentLevel);
        // № ...
        $this->SetProperty("Number", $response->cardNumber);
        // До слудующего уровня
        if (isset($response->toNextLevelSum)) {
            $this->SetProperty("ToNextLevel", number_format($response->toNextLevelSum));
        }
        // Потрачено
        $this->SetProperty("Spent", number_format($response->buySum));
        // Balance - доступно к использованию общих бонусов
        $this->SetBalance($response->details->total ?? null);
        // Кэшбэк
        $this->SetProperty("PointsCashBack", number_format($response->details->cashback->amount));
        // Промо
        $this->SetProperty("Promo", number_format($response->details->promo->amount));

        // Expiration Date

        // кэшбэк
        if (
            isset($response->details->cashback->amountToBeExpired)
            && $response->details->cashback->amountToBeExpired > 0
        ) {
            $exp = strtotime($response->details->cashback->dateEnd);
            $expiringBalance = $response->details->cashback->amountToBeExpired;
        }
        // промо
        if (
            isset($response->details->promo->amountToBeExpired)
            && $response->details->promo->amountToBeExpired > 0
            && (
                !isset($exp)
                || strtotime($response->details->promo->dateEnd) < $exp
            )
        ) {
            $exp = strtotime($response->details->promo->dateEnd);
            $expiringBalance = $response->details->promo->amountToBeExpired;
        }

        if (isset($exp, $expiringBalance)) {
            $this->SetExpirationDate($exp);
            // ... Б  сгорят ...
            $this->SetProperty('ExpiringBalance', number_format($expiringBalance));
        } elseif (
            isset($response->details->cashback->amountToBeExpired, $response->details->promo->amountToBeExpired)
            && $response->details->cashback->amountToBeExpired == 0
            && $response->details->promo->amountToBeExpired == 0
        ) {
            $this->ClearExpirationDate();
        }
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
//        /** @var TAccountChecker $selenium */
        $selenium = clone $this;
        $xKeys = [];
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
//            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
//            $selenium->usePacFile(false);

            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->setKeepProfile(true);
            $selenium->disableImages();
            $selenium->http->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT);

            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL("https://www.sportmaster.ru/profile/bonuses/");

            $selenium->waitForElement(WebDriverBy::xpath('//button[@data-selenium="smButton"] | //h1[contains(text(), "Ваши действия признаны автоматическими")]'), 10);
            // save page to logs
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return $xKeys;
    }

    private function parseReCaptcha($key = null)
    {
        $this->http->RetryCount = 0;
        $key = $key ?? $this->http->FindPreg('/,"reCaptchaSiteKey":\{"v2":"([^\"]+)/');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->State['key'] = $key;
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => "https://www.sportmaster.ru/user/profile/bonus.do", //$this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.sportmaster.ru/web-api/v1/profiles/current/", $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $phone = $response->phone ?? null;
        $this->logger->debug("[Phone]: {$phone}");

        if ($phone && $phone == $this->AccountFields['Login']) {
            return true;
        }

        return false;
    }
}
