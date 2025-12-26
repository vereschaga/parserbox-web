<?php

class TAccountCheckerSafeway extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public $regionOptions = [
        ""             => "Select your brand",
        "safeway"      => "Safeway",
        "acmemarkets"  => "Acme",
        // refs #7743
        //        "dominicks" => "Dominick's",
        "vons"     => "Vons",
        "tomthumb" => "Tom Thumb",
    ];
    private $headers = [
        'X-IBM-Client-Id'      => '306b9569-2a31-4fb9-93aa-08332ba3c55d',
        'X-IBM-Client-Secret'  => 'N4tK3pW7pP6nB4kL6vN4kW0rS5lE4qH2fY0aB2rK1eP5gK4yV5',
        'x-swy-client-id'      => 'web-portal',
        'x-swy-correlation-id' => 'dc1ddfce-39ed-11e8-b467-0ed5f89f718b',
        'Accept'               => "application/vnd.safeway.v1+json", // static
        'Content-Type'         => "application/vnd.safeway.v1+json", // static
    ];

    private $timeout = 120;
    private $gid = null;

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields);
        $fields["Login2"]["Options"] = $this->regionOptions;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);
        $this->http->setDefaultHeader("X-SWY_BANNER", $this->AccountFields['Login2']);

        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www." . $this->AccountFields['Login2'] . ".com/home.html", [], $this->timeout);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        return $this->selenium();

        $this->http->setDefaultHeader("Accept", "application/json");
        $this->http->setDefaultHeader('Content-Type', 'application/json;charset=utf-8');

        $this->http->RetryCount = 1;
        $this->http->GetURL("https://www." . $this->AccountFields['Login2'] . ".com/home.html", [], $this->timeout);
        $this->http->RetryCount = 2;
        $redirect_uri = $this->http->FindPreg("/wwwLoginRedirectURI\":\"([^\"]+)/");

        if (!$redirect_uri) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://albertsons.okta.com/api/v1/authn", json_encode([
            'username' => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ]), [
            'X-Okta-User-Agent-Extended' => 'okta-auth-js-1.15.0',
        ]);
        $this->http->RetryCount = 1;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg("/Service Unavailable/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function getCookie($name, $domain)
    {
        $this->logger->notice(__METHOD__);
        $cookie = $this->http->GetCookies($domain);

        if (isset($cookie[$name])) {
            return $cookie[$name];
        }

        return null;
    }

    public function genRandomString($a)
    {
        for ($b = "", $d = 0; $d < $a; ++$d) {
            $b .= "abcdefghijklnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"[intval(floor(61 * $this->random()))];
        }

        return $b;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (
            !empty($response->sessionToken)
            || !empty($response->profile)
            || !empty($response->personalInfo)
            || !empty($response->loginInfo)
        ) {
            return true;
        }

        /*
        $token = $this->http->FindPreg('/"sessionToken":"(.+?)"/');

        if ($token) {
            $nonce = $this->http->getCookieByName('okta-oauth-nonce') ?? $this->genRandomString(64);
            $this->http->setCookie("okta-oauth-nonce", $nonce);

            if (!$nonce) {
                return false;
            }
            $query = [
                "client_id"     => "0oap6ku01XJqIRdl42p6",
                "redirect_uri"  => "https://www.{$this->AccountFields['Login2']}.com/bin/safeway/unified/sso/authorize",
                "response_type" => "code",
                "response_mode" => "query",
                "state"         => "orange-fear-perry-roasted",
                "nonce"         => $nonce,
                "prompt"        => "none",
                "sessionToken"  => $token,
                "scope"         => "openid profile email offline_access used_credentials",
            ];
            $this->http->GetURL("https://albertsons.okta.com/oauth2/ausp6soxrIyPrm8rS2p6/v1/authorize?" . http_build_query($query), [], $this->timeout);

            if (
                $this->http->currentUrl() == "https://www.{$this->AccountFields['Login2']}.com/account/sign-in.error-IACUA0006.html"
                || $this->http->currentUrl() == "https://www.{$this->AccountFields['Login2']}.com/account/sign-in.error-SLSC003.html"
                || $this->http->currentUrl() == "https://www.{$this->AccountFields['Login2']}.com/account/sign-in.error-IACUA0065.html"
                || $this->http->FindPreg('/An error occurred while processing your request\.<p>/')
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode('//div[contains(text(), "Sorry, we\'re having technical difficulties and working on fixing them, please check back later.- Error: OSEC00004")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->loginSuccessful();
        }
        */

        // The email address or password you entered does not match our records. Please try again.
        if ($this->http->FindPreg('/"errorCode":"E0000004".+"errorSummary":"Authentication failed"/')) {
            throw new CheckException("The email address or password you entered does not match our records. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been disabled.
        if ($this->http->FindPreg("/\"errors\":\[\{\"code\":\"RSS01025E\"/ims")
            /*|| $this->http->FindPreg("/<errors><code>RSS01025E<\/code>/ims")*/) {
            throw new CheckException("Your account has been disabled.", ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($this->http->FindPreg("/\"errors\":\[\{\"code\":\"RSS01100E\"/ims")
            /*|| $this->http->FindPreg("/<errors><code>RSS01100E<\/code>/ims")*/) {
            throw new CheckException("We're sorry for inconvenience, but our site content is currently unavailable due to maintenance", ACCOUNT_PROVIDER_ERROR);
        }
        // Password must be 8-12 characters long.
        if ($this->http->FindPreg("/\"errors\":\[\{\"code\":\"RSS01024E\"/ims")
            /*|| $this->http->FindPreg("/<errors><code>RSS01024E<\/code>/ims")*/) {
            throw new CheckException("Password must be 8-12 characters long.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg("/\"status\":\"LOCKED_OUT\"/ims")) {
            throw new CheckException("Because of multiple login attempts your account has been temporarily locked for security reasons.", ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "popup-light-error alert fade show")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'The password entered doesn\'t match our records.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Sorry, we\'re experiencing technical difficulties.') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'The account has been locked out due to multiple attempts of login with incorrect password')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog($this->responseDataProfile ?? null);
        // Name
        $firstName =
            $response->profile->personalInfo->name->firstName
            ?? $response->personalInfo->name->firstName
            ?? $response->_embedded->user->profile->firstName
            ?? $response->firstName ?? null;
        $lastName =
            $response->profile->personalInfo->name->lastName
            ?? $response->personalInfo->name->lastName
            ?? $response->_embedded->user->profile->lastName
            ?? $response->lastName ?? null;
        $this->SetProperty('Name', beautifulName("$firstName $lastName"));
        // Club Card Number
        if (is_array($response->loyaltyPrograms ?? null)) {
            foreach ($response->loyaltyPrograms as $lp) {
                if (isset($lp->name) && $lp->name == 'CLUBCARD' && is_numeric($lp->value ?? null)) {
                    $this->SetProperty("CardNumber", $lp->value);
                }
            }
        }

        /*
        $this->http->RetryCount = 0;
        $this->headers['Accept'] = "application/vnd.safeway.v2+json";
        $this->http->GetURL("https://www." . $this->AccountFields['Login2'] . ".com/abs/pub/api/uca/customers/{$this->gid}/rewards", $this->headers);
        $this->http->RetryCount = 1;
        */
        $response = $this->http->JsonLog($this->responseDataRewards ?? null);
        // Balance - Rewards Points
        if (isset($response->scorecards[0]->balance)) {
            $this->SetBalance($response->scorecards[0]->balance);
        } else {
            // We are sorry for the inconvenience, but our site content is currently unavailable due to maintenance.
            if ($message = $this->http->FindPreg("/We are sorry for the inconvenience, but our site content is currently unavailable due to maintenance\.\s*We're working hard\s*to make sure that it is available to you as soon as possible\./", false, $this->responseDataRewards ?? '')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        $this->SetProperty('ExpiringBalance', $response->scorecards[0]->willExpire ?? null);

        // Rewards Expire (Rewards Expire this Month)
        if (isset($response->scorecards[0]->points)) {
            foreach ($response->scorecards[0]->points as $points) {
                if (
                    $points->value > 0
                    && (
                        !isset($exp)
                        || strtotime($exp) < strtotime($points->validityEndDate)
                    )
                ) {
                    $exp = strtotime($points->validityEndDate);
                    $this->SetProperty('ExpiringBalance', $points->value);
                    $this->SetExpirationDate($exp);
                }
            }// foreach ($response->scorecards[0]->points as $points)
        }// if (isset($response->scorecards[0]->points))

        $this->http->GetURL("https://www." . $this->AccountFields['Login2'] . ".com/abs/pub/dce/mycard/J4UProgram1/services/mycard/savings");
        $savings = $this->http->JsonLog();

        // Lifetime Savings
        $lifetimeSavings =
            $response->savings->lifetimeSavings
            ?? $this->responseDataRewards->savings->lifetimeSavings
            ?? $savings->lifetimeSavings
            ?? null
        ;
        $this->SetProperty("LifetimeSavings", isset($lifetimeSavings) ? '$' . $lifetimeSavings : '');
        // [THIS_YEAR] Savings to Date
        $currentYearSavings =
            $response->savings->currentYearSavings
            ?? $this->responseDataRewards->savings->currentYearSavings
            ?? $savings->currentYearSavings
            ?? null
        ;
        $this->SetProperty("YTDSavings", isset($currentYearSavings) ? '$' . $currentYearSavings : '');

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (isset($response->ack) && $response->ack == 1
                && isset($response->errors[0]->code, $response->errors[0]->message)
                && $response->errors[0]->code == 5001
                && $response->errors[0]->message == 'HouseHold Id is missing in the request header') {
                throw new CheckException("We apologize our system is not available at this time. We hope to be back online soon.", ACCOUNT_PROVIDER_ERROR);
            }

            if (
                isset($response->errorCode) && $response->errorCode == 'IACUA0016'
                && isset($response->error, $response->message)
                && $response->error == 'Service Unavailable'
                && $response->message == 'Remote EMMD service not available'
            ) {
                throw new CheckException("We apologize our system is not available at this time. We hope to be back online soon.", ACCOUNT_PROVIDER_ERROR);
            }

            if (
                isset($response->errors[0]->code, $response->errors[0]->message)
                && $response->errors[0]->code == 'IAUC0066'
                && in_array($response->errors[0]->message, [
                    'Remote EMRB Service is not available',
                    'Remote OCRP service is not available',
                ])
            ) {
                // Balance - Rewards Points
                $this->SetBalance(0);
                // Rewards Expire (Rewards Expire this Month)
                $this->SetProperty("ExpiringBalance", 0);
            }

            if (isset($response->errors[0]->code, $response->errors[0]->message)
                && in_array($response->errors[0]->code, [
                    5001,
                    140409,
                    140035,
                ])
                && in_array($response->errors[0]->message, [
                    'Sorry, No reward points existing for your HouseholdId',
                    'No points found for the household id',
                    'No corresponding data found for the requested program',
                ])
            ) {
                // Balance - Rewards Points
                $this->SetBalance(0);
                // Rewards Expire (Rewards Expire this Month)
                $this->SetProperty("ExpiringBalance", 0);
            }

            // AccountID: 4783571
            if (
                isset($response->errorCode, $response->message)
                && $response->errorCode == 'IACUA0016'
                && $response->message == 'Remote Nimbus service not available'
            ) {
                // Balance - Rewards Points
                $this->SetBalance(0);
                // Rewards Expire (Rewards Expire this Month)
                $this->SetProperty("ExpiringBalance", 0);
            } elseif (
                isset($response->errorCode, $response->message, $response->status)
                && $response->status == 403
                && $response->errorCode == 'IACUA0001'
                && $response->message == 'The request requires user authentication.'
            ) {
                throw new CheckRetryNeededException(3);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    protected function checkRegionSelection($region)
    {
        // refs #7743
        if ($this->AccountFields['Login2'] == 'dominicks') {
            throw new CheckException("Dominic's stores were closed in January, 2014", ACCOUNT_PROVIDER_ERROR);
        }

        if (empty($region) || !in_array($region, array_flip($this->regionOptions))) {
            $region = 'safeway';
        }

        return $region;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $customerId = urldecode($this->http->getCookieByName('SWY_SHARED_PII_SESSION_INFO'));
        $customerId = $this->http->JsonLog($customerId);

        $session = urldecode($this->http->getCookieByName('SWY_SHARED_SESSION'));
        $session = $this->http->JsonLog($session);

        if (!isset($customerId->gid) || !isset($session->accessToken)) {
            return false;
        }

        $cncSubscriptionKey = $this->http->FindPreg('/cncSubscriptionKey":"([^\"]+)/');

        if (!$cncSubscriptionKey) {
            return false;
        }

        $this->headers['Ocp-Apim-Subscription-Key'] = $cncSubscriptionKey;
        $headers = $this->headers;
        $headers['Accept'] = 'application/vnd.safeway.v1+json';
        unset($headers['Content-Type']);

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.{$this->AccountFields['Login2']}.com/abs/pub/cnc/ucaservice/api/uca/customers/{$customerId->gid}/profile", $headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (
            isset($response->firstName, $response->lastName)
            || isset($response->emailId, $response->customerId)// AccountID: 4902804
        ) {
            $this->gid = $customerId->gid;
            $this->responseDataProfile = $this->http->Response['body'];

            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.{$this->AccountFields['Login2']}.com/abs/pub/cnc/ucaservice/api/uca/customers/{$customerId->gid}/rewards", $headers);
            $this->http->RetryCount = 2;
            $this->responseDataRewards = $this->http->Response['body'];

            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice('Running Selenium...');

            $selenium->UseSelenium();
            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $selenium->seleniumOptions->recordRequests = true;
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL("https://www.{$this->AccountFields['Login2']}.com/customer-account/rewards");

            $login = $selenium->waitForElement(WebDriverBy::id('label-email'), 10);
            $pwd = $selenium->waitForElement(WebDriverBy::id('label-password'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::id('btnSignIn'), 0);

            if (!isset($login, $pwd, $btn)) {
                $this->saveToLogs($selenium);

                $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "enterUsername"]'), 0);

                if (!$login) {
                    return false;
                }

                $login->sendKeys($this->AccountFields['Login']);
                $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign in with password") and not(@disabled)]'), 3);

                if (!$btn) {
                    $this->saveToLogs($selenium);

                    return false;
                }

                $btn->click();

                /*
                $usePass = $selenium->waitForElement(WebDriverBy::xpath('//*[self::a or self::button][contains(text(), "Use password")]'), 5);

                if ($usePass) {
                    $usePass->click();
                }
                */

                $pwd = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 10);
                $this->saveToLogs($selenium);

                if (!$pwd) {
                    if ($el = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "popup-light-error alert fade show")]'), 0)) {
                        $message = $el->getText();
                        $this->logger->error("[Error]: {$message}");

                        if (strstr($message, 'We couldn\'t find an account with this email address.')) {
                            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                        }

                        $this->DebugInfo = $message;
                    }

                    if (
                        in_array($this->AccountFields['Login'], [
                            'ereiner83@alum.mit.edu',
                            'dhrebec@gmail.com',
                            'jwells.per@gmail.com',
                            'jtmoneymeyerz@gmail.com',
                            'dave@thekregs.com',
                        ])
                    ) {
                        throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    return false;
                }

                $pwd->sendKeys($this->AccountFields['Pass']);
                $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Sign in" and not(@disabled)]'), 3);

                if (!$btn) {
                    $this->saveToLogs($selenium);

                    return false;
                }

                $btn->click();
            } else {
                $login->sendKeys($this->AccountFields['Login']);
                $pwd->sendKeys($this->AccountFields['Pass']);
                $this->saveToLogs($selenium);

                // sometimes credentials are not inserted by sendKeys, strange bug
                if ($login->getAttribute('value') != $this->AccountFields['Login']
                    || $pwd->getAttribute('value') != $this->AccountFields['Pass']
                ) {
                    $selenium->driver->executeScript("document.getElementById('label-password').value = '{$this->AccountFields['Pass']}';");
                    $login->click();
                    $selenium->driver->executeScript("document.getElementById('label-email').value = '{$this->AccountFields['Login']}';");
                    $pwd->click();
                }

                if ($validationError = $selenium->waitForElement(WebDriverBy::cssSelector('.form-group.has-error .errorMessage li'), 1)) {
                    throw new CheckException($validationError->getText(), ACCOUNT_INVALID_PASSWORD);
                }

                $btn->click();
            }

            $el = $selenium->waitForElement(WebDriverBy::xpath('
                //span[contains(@class, "dst-sign-in-up user-greeting") and starts-with(text(), "Hi, ")]
                | //div[contains(@class, "errorMessage")]/ul/li
                | //div[@id = "error-message"]
                | //p[contains(text(), "Sorry, we\'re having technical difficulties, please check back later")]
                | //p[contains(text(), "Lifetime Savings:")]
                | //div[contains(@class, "popup-light-error alert fade show")]
            '), 10);
            $this->saveToLogs($selenium);

            if ($providerError = $selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), \"Sorry, we're having technical difficulties, please check back later.\")]"), 0)) {
                throw new CheckException($providerError->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            try {
                $success = $el
                    && (stripos($el->getText(), 'Hi, ') !== false || stripos($el->getText(), 'Lifetime Savings:') !== false);
            } catch (StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException: " . $e->getMessage());
                sleep(3);
                $el = $selenium->waitForElement(WebDriverBy::xpath('//span[contains(@class, "dst-sign-in-up user-greeting") and starts-with(text(), "Hi, ")]| //p[contains(text(), "Lifetime Savings:")]'), 0);
                $success = $el
                    && (stripos($el->getText(), 'Hi, ') !== false || stripos($el->getText(), 'Lifetime Savings:') !== false);
            }

            if ($success) {
                $selenium->driver->executeScript('document.querySelector(".dst-sign-in-up.user-greeting").click();');
                $btnToProfile = $selenium->waitForElement(WebDriverBy::xpath('//a[@href = "/customer-account/account-settings"]'), 2);

                if ($btnToProfile) {
                    $selenium->driver->executeScript('document.querySelector(\'a[href="/customer-account/account-settings"]\').click();');
                    sleep(3);
                }
            }

            foreach ($selenium->driver->manage()->getCookies() as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            foreach ($selenium->http->driver->browserCommunicator->getRecordedRequests() as $n => $xhr) {
                if (stripos($xhr->request->getUri(), '/api/v1/authn') !== false) {
                    $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                    $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
                    $this->responseDataAuth = json_encode($xhr->response->getBody());
                }

                if (stripos($xhr->request->getUri(), '/rewards') !== false) {
                    $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                    $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
                    $this->responseDataRewards = json_encode($xhr->response->getBody());
                }

                if (stripos($xhr->request->getUri(), '/profile') !== false) {
                    $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                    $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
                    $this->responseDataProfile = json_encode($xhr->response->getBody());
                }
            }

            $this->logger->info('[Auth responseData]: ' . htmlspecialchars($this->responseDataAuth ?? null));
            $this->logger->info('[Rewards responseData]: ' . htmlspecialchars($this->responseDataRewards ?? null));
            $this->logger->info('[Profile responseDataProfile]: ' . htmlspecialchars($this->responseDataProfile ?? null));
            $this->logger->info('[Current URL]: ' . $selenium->http->currentUrl());
            $this->saveToLogs($selenium);

            if (!empty($this->responseDataAuth) || !empty($this->responseDataProfile)) {
                $this->http->SetBody($this->responseDataAuth ?? $this->responseDataProfile);
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage());
            $retry = true;
        } finally {
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    private function saveToLogs($selenium)
    {
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
}
