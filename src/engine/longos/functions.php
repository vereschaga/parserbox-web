<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerLongos extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $headers = [
        "Accept"          => "*/*",
        "Accept-Encoding" => "gzip, deflate, br",
        'Origin'          => 'https://www.longos.com',
        'X-API-Key'       => '24c43ae2-1eac-4e79-8924-cf5270c49242',
        'User-Agent'      => 'Longos Web SPA',
    ];
    private $customerID = null;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
//        $this->http->SetProxy($this->proxyDOP(['tor1']));// refs #18611
//        $this->setProxyBrightData(null, "static", "ca"); // see details, refs #18611 // todo: broken since 05 may 2023
//        $this->setProxyGoProxies();
        $this->setProxyMount();
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }

        $access_token = $this->State['Authorization'];

        if ($this->loginSuccessful($access_token)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.longos.com');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        if ($script = $this->http->FindSingleNode('//link[@rel = "preload" and contains(@href, "/app-ecd")]/@href')) {
            $this->http->NormalizeURL($script);
            $this->http->GetURL($script);
        }
        $siteKey = $this->http->FindPreg("/recaptcha_sitekey=\"([^\"]+)/") ?? '6LczgcQUAAAAABSNksYopW4kceR6LYFfL19pa4H8';

        if ($this->http->Response['code'] != 200 || !$siteKey) {
            return $this->checkErrors();
        }

        $this->getCookiesFromSelenium();

        return true;

        $captcha = $this->parseReCaptcha($siteKey);

        if (!$captcha) {
            return false;
        }

        $data = [
            "grant_type"       => "password",
            "scope"            => "basic",
            "username"         => $this->AccountFields['Login'],
            "password"         => $this->AccountFields['Pass'],
            "client_id"        => "client-side",
            "client_secret"    => "secret",
            "recaptcha-action" => "login",
            "recaptcha-token"  => $captcha,
        ];

        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Referer'      => 'https://www.longos.com/',
            'Origin'       => 'https://www.longos.com',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.longos.com/authorizationserver/oauth/token", $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We are unable to process your request, please try again.
        if ($this->http->FindSingleNode("//h2[contains(text(),'Web server is down')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We’re currently undergoing required maintenance in an effort to serve you better.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $accessToken =
            $response->access_token
            ?? $response->token->access_token
            ?? null
        ;
        // Access is allowed
        if ($accessToken) {
            $this->captchaReporting($this->recognizer);
            $authorization = "bearer {$accessToken}";
            $this->State['Authorization'] = $authorization;

            return $this->loginSuccessful($authorization);
        }
        $message =
            $response->error_description
            ?? $response->message
            ?? $this->http->FindSingleNode('//div[contains(@class, "sign-in__error")]')
            ?? $this->http->FindSingleNode('//p[contains(@class, "text-error")]')
            ?? null
        ;

        if ($message) {
            $this->logger->error($message);

            if ($message == 'The Username or Password is invalid.') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Your username / password are invalid.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'Your password is at least 8 characters.'
                || $message == 'The email and/or password entered does not match our records.'// may be false/positive
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Bad credentials') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("The email and/or password entered does not match our records.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Internal Server Error') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Please change your password as our website has been updated. To change your password, select Forgot Your Password and follow the instructions provided.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == "You're most probably a bot"
                || $message == "The recaptcha token is invalid"
            ) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if (
            ($this->http->Response['code'] == 500 && empty($this->http->Response['body']))
            || ($this->http->Response['code'] == 504 && $this->http->FindPreg('/An error occurred while processing your request.<p>/'))
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckRetryNeededException(3, 5, "We are unable to process your request, please try again.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", beautifulName($response->firstName . " " . $response->lastName));
        // Thank you rewards card #
        $this->SetProperty("Number", $response->tyrCardNumber ?? null);
        // Balance - You have ... Reward Points
        if (!$this->SetBalance($response->tyrPoints ?? null)) {
            if (isset($response->ResponseBody) && $response->ResponseBody == 'The Customer does not have any Active TYR Card.') {//todo: need to change
                $this->SetBalanceNA();
            }
        }
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => 'https://www.longos.com',
            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            "action"    => "login",
            "min_score" => 0.9,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        $postData = array_merge(
            [
                "type"       => "NoCaptchaTask",
                //                "type"         => "RecaptchaV3TaskProxyless",
                "websiteURL"   => $this->http->currentUrl(),
                "websiteKey"   => $key,
                //                "minScore"     => 0.9,
                //                "pageAction"   => "login",
            ],
            $this->getCaptchaProxy()
        );
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }

    private function loginSuccessful($authorization)
    {
        $this->logger->notice(__METHOD__);
        $this->http->setDefaultHeader("Authorization", $authorization);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api.longos.com/ggcommercewebservices/v2/groceryGatewaySpa/users/current?fields=DEFAULT,onAccountApproved,companyName,business,businessPhone,businessMobileNumberExt,tyrStatus,tyrPoints,tyrCardNumber,crmId,customerCredits,defaultAddress(region(name,isocodeShort),country(name))&lang=en&curr=CAD&lang=en&curr=CAD&defaultStoreName=Longos%20Leaside%20Pickup", $this->headers);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        $email = $response->uid ?? null;

        if (strtolower($email) === strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useFirefox();

            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->setKeepProfile(true);

//            $request = FingerprintRequest::chrome();
//            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
//            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);
//
//            if ($fingerprint !== null) {
//                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
//                $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
//                $selenium->http->setUserAgent($fingerprint->getUseragent());
//            }

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www.longos.com/my-account/cards-and-points");

            if ($btnClose = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "close-button")]'), 5)) {
                $btnClose->click();
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::id('email'), 5);
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('current-password'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput) {
                return $this->checkErrors();
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            sleep(1);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "sign-in__btn-container")]//button'), 2);
            $this->savePageToLogs($selenium);

            if (!$button) {
                return $this->checkErrors();
            }

            $this->logger->debug("click");
//            $button->click();
            $selenium->driver->executeScript("document.querySelector('div.sign-in__btn-container button').click();");

            sleep(3);
            $selenium->waitForElement(WebDriverBy::xpath('
                //div[contains(@class, "sign-in__error") and normalize-space(.) != ""]
            '), 5);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('spartacus⚿⚿auth');");
            $this->logger->info("[Form responseData]: " . $responseData);

            if (!empty($responseData) && $responseData != '{"token":{},"userId":"anonymous","redirectUrl":"/"}') {
                $this->http->SetBody($responseData);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }
}
