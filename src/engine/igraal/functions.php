<?php

class TAccountCheckerIgraal extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use AwardWallet\Engine\ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $region = 'fr';

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""   => "Select your country",
            "FR" => "France",
            "DE" => "Germany",
        ];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->getRegionSettings();

        /*
        $this->setProxyGoProxies(null, 'de');
        */
    }

    public function getRegionSettings()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('Region => ' . $this->AccountFields['Login2']);

        if ($this->AccountFields['Login2'] == 'DE') {
            $this->region = 'de';
            $this->setProxyGoProxies(null, 'de');
        } else {
            $this->region = 'fr';
            $this->setProxyGoProxies(null, 'fr');
        }
    }

    public function IsLoggedIn()
    {
        /*
        $this->http->GetURL('https://' . $this->region . '.igraal.com/', [], 20);
        */

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $WAIT_TIMEOUT = 10;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $selenium->UseSelenium();
        $selenium->useFirefoxPlaywright();
        $selenium->seleniumOptions->addHideSeleniumExtension = false;
        $selenium->seleniumOptions->userAgent = null;
        $selenium->http->saveScreenshots = true;
        $selenium->http->start();
        $selenium->Start();

        try {
            $selenium->http->removeCookies();
            /*
            foreach (['fr', 'de', 'es'] as $region) {
                $selenium->http->setCookie('country_picker_selected', '1-year', ".{$region}.igraal.com", '/', null, true);
                $selenium->http->setCookie('country_picker_closed', '30-days', ".{$region}.igraal.com", '/', null, true);
            }
            */
            $selenium->http->GetURL('https://' . $this->region . '.igraal.com/');
            $closeRegionPopup = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "country-picker") and contains(@class, "pointer")]'), $WAIT_TIMEOUT);
            $this->savePageToLogs($selenium);

            if ($closeRegionPopup) {
                $closeRegionPopup->click();
            }
            $closeCookiesPopup = $selenium->waitForElement(WebDriverBy::xpath('//span[@id="cookies-banner-btn-accept"]'), $WAIT_TIMEOUT);
            $this->savePageToLogs($selenium);

            if ($closeCookiesPopup) {
                $closeCookiesPopup->click();
            }
            $this->savePageToLogs($selenium);
            $loginButton = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-ig-toggle-id="menu-user"]'), $WAIT_TIMEOUT);
            $this->savePageToLogs($selenium);

            if (!isset($loginButton)) {
                return $this->checkErrors();
            }
            $loginButton->click();
            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@id="login_user_email"]'), $WAIT_TIMEOUT);
            $password = $selenium->waitForElement(WebDriverBy::xpath('//input[@id="login_user_password"]'), $WAIT_TIMEOUT);
            $submit = $selenium->waitForElement(webdriverBy::xpath('//div[@id="sub-div"]/button'), $WAIT_TIMEOUT);
            $this->savePageToLogs($selenium);

            if (!isset($login, $password, $submit)) {
                return $this->checkErrors();
            }
            $login->sendKeys($this->AccountFields['Login']);
            $password->sendKeys($this->AccountFields['Pass']);
            $submit->click();
            $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@data-ig-connect-form-type, "error") and (contains(text(), "email ou mot de passe incorrect") or contains(text(), "Benutzername oder Passwort ist falsch"))]'), $WAIT_TIMEOUT);
            $selenium->http->GetURL('https://' . $this->region . '.igraal.com/ws/token');
            sleep(5);
            $this->savePageToLogs($selenium);

            $tokenData = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));
            $this->State['token'] = $tokenData->token;
            $selenium->http->GetURL('https://' . $this->region . '.igraal.com/');
            $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@href, "/logout")]/@href'), $WAIT_TIMEOUT);
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->savePageToLogs($selenium);

            return true;
        } finally {
            $selenium->http->cleanup();
        }
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "504 Gateway Time-out")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//div[contains(text(), " Oups... Une erreur vient de se produire ")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($message = $this->http->FindSingleNode('//div[contains(@data-ig-connect-form-type, "error")
													and (contains(text(), "email ou mot de passe incorrect")
														 or contains(text(), "Benutzername oder Passwort ist falsch"))]')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $result = $this->http->JsonLog(null, 0);

        if (isset($result->valid_captcha) && $result->valid_captcha == false) {
            throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $result = $this->http->JsonLog();
        // Balance - VALIDÉS / Kontostand
        $this->SetBalance($result->balanceTotal ?? null);
        // Name
        $name = $result->civility->firstname ?? '' . ' ' . $result->civility->lastname ?? '';
        $this->SetProperty('Name', beautifulName(trim($name)));
        // Status
        $this->SetProperty('Status', $result->status ?? null);
        // Pending Cashback - EN ATTENTE / Vorgemerkt
        if (isset($result->balancePending)) {
            if (is_float($result->balancePending)) {
                $result->balancePending = number_format(round($result->balancePending, 2), 2, ',', '');
            }
            $this->SetProperty('PendingCashback', '€' . $result->balancePending);
        }
        // Reviews - Mes avis / Meine Bewertungen
        if (isset($result->balanceReviews)) {
            if (is_float($result->balanceReviews)) {
                $result->balanceReviews = number_format(round($result->balanceReviews, 2), 2, ',', '');
            }
            $this->SetProperty('Reviews', '€' . $result->balanceReviews);
        }
        // Invitations - Mes filleuls / Meine Einladungen
        if (isset($result->balanceSponsorship)) {
            if (is_float($result->balanceSponsorship)) {
                $result->balanceSponsorship = number_format(round($result->balanceSponsorship, 2), 2, ',', '');
            }
            $this->SetProperty('Invitations', '€' . $result->balanceSponsorship);
        }
    }

    /*
    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(@href, "/logout")]/@href')) {
            return true;
        }

        return false;
    }
    */

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (empty($this->State['token'])) {
            $this->logger->notice('no token');

            return false;
        }

        $headers = [
            'Accept'        => '*/*',
            'Authorization' => $this->State['token'],
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://public-api-' . $this->region . '.igraal.com/v1/user/me', $headers);
        $this->http->RetryCount = 2;
        $result = $this->http->JsonLog();

        $email = $result->email ?? null;

        if (strtolower($email) !== strtolower($this->AccountFields['Login'])) {
            $this->logger->notice('Incorrect email in json');

            return false;
        }

        return true;
    }

    private function parseReCaptcha($recaptchaKey)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$recaptchaKey}");

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $recaptchaKey, $parameters);
        $this->logger->debug("captcha: {$captcha}");

        return $captcha;
    }
}
