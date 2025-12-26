<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerHarrods extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    private $apiKey = "4_Q-Kvb3Xgr6Gw8oFqD00flA";
    private $responseData = null;
    private $state = null;
    private $headers = [
        "Accept"        => "application/json, text/plain, */*",
        "Content-Type"  => "application/json",
        "Connection"    => "keep-alive",
        "FF-Country"    => "GB",
        "FF-Currency"   => "GBP",
        "Referer"       => "https://www.harrods.com/",
        "Origin"        => "https://www.harrods.com",
        "X-NewRelic-ID" => "VQUCV1ZUGwIFVlBRDgcA",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        /*
        $this->setProxyBrightData(null, 'static', 'uk');
        */
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setHttp2(true);
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
        $this->selenium();

        $this->http->RetryCount = 0;
        $this->http->setMaxRedirects(2);
        $this->http->GetURL('https://www.harrods.com/en-gb/account');
        $this->http->setMaxRedirects(5);
        $this->http->RetryCount = 2;

        $this->state = $this->http->FindPreg("/state=([^\&\"\']+)/", false, $this->http->currentUrl());
        $this->http->GetURL($this->http->Response['headers']['location']);

        $this->State['context'] = $this->http->FindPreg("/context=([^\&\"\']+)/", false, $this->http->currentUrl());

        $headers = [
            'Accept'        => '*/*',
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ];

        $data = [
            'loginID'           => $this->AccountFields['Login'],
            'password'          => $this->AccountFields['Pass'],
            'sessionExpiration' => 86400,
            'targetEnv'         => 'jssdk',
            'include'           => 'profile,data,emails,subscriptions,preferences,',
            'includeUserInfo'   => true,
            'loginMode'         => 'standard',
            'lang'              => 'en',
            'riskContext'       => '{"b0":21457,"b1":[65,60,41,51],"b2":4,"b3":[],"b4":2,"b5":1,"b6":"Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0","b7":[{"name":"PDF Viewer","filename":"internal-pdf-viewer","length":2},{"name":"Chrome PDF Viewer","filename":"internal-pdf-viewer","length":2},{"name":"Chromium PDF Viewer","filename":"internal-pdf-viewer","length":2},{"name":"Microsoft Edge PDF Viewer","filename":"internal-pdf-viewer","length":2},{"name":"WebKit built-in PDF","filename":"internal-pdf-viewer","length":2}],"b8":"9:19:17 AM","b9":-300,"b10":{"state":"prompt"},"b11":false,"b12":null,"b13":[null,"2560|1440|24",false,true]}',
            'APIKey'            => $this->apiKey,
            'source'            => 'showScreenSet',
            'sdk'               => 'js_latest',
            'authMode'          => 'cookie',
            'pageURL'           => 'https://identity.harrods.com/login?gig_client_id=ASQYeQi8uUQ4CiQZC17LCK7y',
            'sdkBuild'          => 16851,
            'format'            => 'json',
        ];

        $this->http->PostURL('https://id.identity.harrods.com/accounts.login', $data, $headers);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//title[contains(text(), 'Service Unavailable')]")
            || ($this->http->FindPreg("/The service is unavailable\./") && $this->http->Response['code'] == 503)
            || ($this->http->FindPreg("/Unfortunately, this page is unavailable\./") && in_array($this->http->Response['code'], [404, 500]))) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently performing scheduled maintenance to harrods.com.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'FPS Application Maintenance')]")) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 3, $message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//main/p[@class="subtitle" and contains(text(), "Captcha challenge")]')) {
            throw new CheckRetryNeededException();
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // Access is allowed
        if (!empty($response->username) /*&& $this->loginSuccessful()*/) {
            return true;
        }
        $message = $response->errorMessage ?? null;
        $errorCode = $response->errorCode ?? null;
        $this->logger->error("[Error]: {$message}");
        // Your Login attempt was not successful. Please try again
        if ($errorCode == 14 && $message == 'Could not authenticate') {
            throw new CheckException("Your email address or password were not correct. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if (
            $message === "Account Pending TFA Verification"
            && !empty($response->regToken)
        ) {
            $this->parseQuestion();

            return false;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $code = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $headers = [
            'Accept'  => '*/*',
            'Origin'  => 'https://identity.harrods.com',
            'Referer' => 'https://identity.harrods.com/',
        ];

        $this->http->GetURL("https://id.identity.harrods.com/accounts.tfa.email.completeVerification?gigyaAssertion={$this->State['gigyaAssertion']}&phvToken={$this->State['phvToken']}&code=$code&regToken={$this->State['regToken']}&APIKey={$this->apiKey}&sdk=js_latest&pageURL=https%3A%2F%2Fidentity.harrods.com%2Flogin%3Fgig_client_id%3DASQYeQi8uUQ4CiQZC17LCK7y&sdkBuild=16851&format=json", $headers);
        $this->http->JsonLog();
        $errorMessage = $this->http->FindPreg("/\"errorMessage\":\s*\"([^\"]+)/");
        $errorDetails = $this->http->FindPreg("/\"errorDetails\":\s*\"([^\"]+)/");

        if (
            $errorMessage === "Invalid parameter value"
            && $errorDetails === "Wrong verification code"
        ) {
            $this->AskQuestion($this->Question, $errorDetails, 'Question');

            return false;
        }

        $providerAssertion = $this->http->FindPreg("/\"providerAssertion\":\s*\"([^\"]+)/");

        if (empty($providerAssertion)) {
            if ($errorMessage == 'Invalid parameter value' && $errorDetails == 'Invalid jwt') {
                throw new CheckRetryNeededException(2, 0);
            }

            return false;
        }

        // for cookies
        $this->http->GetURL("https://id.identity.harrods.com/accounts.tfa.finalizeTFA?gigyaAssertion={$this->State['gigyaAssertion']}&providerAssertion={$providerAssertion}&tempDevice=false&regToken={$this->State['regToken']}&APIKey={$this->apiKey}&source=showScreenSet&sdk=js_latest&pageURL=https%3A%2F%2Fidentity.harrods.com%2Flogin%3Fgig_client_id%3DASQYeQi8uUQ4CiQZC17LCK7y&format=json", $headers);
        $this->http->JsonLog();

        $params = [
            'regToken'        => $this->State['regToken'],
            'targetEnv'       => 'jssdk',
            'include'         => 'profile,data,emails,subscriptions,preferences,',
            'includeUserInfo' => 'true',
            'APIKey'          => $this->apiKey,
            'source'          => 'showScreenSet',
            'sdk'             => 'js_latest',
            'pageURL'         => 'https://identity.harrods.com/login?gig_client_id=ASQYeQi8uUQ4CiQZC17LCK7y',
            'sdkBuild'        => '16851',
            'format'          => 'json',
        ];

        // it helps
        sleep(rand(1, 3));
        $this->http->GetURL('https://id.identity.harrods.com/accounts.finalizeRegistration?' . http_build_query($params), $headers);

        // it helps
        if ($this->http->FindPreg("/\"statusCode\": 403,\s*\"statusReason\":\s*\"Forbidden\"/")) {
            throw new CheckRetryNeededException(2, 0);
        }

        unset($this->State['phvToken']);
        unset($this->State['gigyaAssertion']);
        unset($this->State['regToken']);

        $authResult = $this->http->JsonLog();
        $loginToken = $authResult->sessionInfo->login_token ?? null;

        $params = [
            'context'     => $this->State['context'],
            'login_token' => $loginToken,
        ];

        $this->http->GetURL('https://id.identity.harrods.com/oidc/op/v1.0/4_Q-Kvb3Xgr6Gw8oFqD00flA/authorize/continue?' . http_build_query($params), $this->headers);
        $code = $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl());

        $data = [
            'payload' => [
                'code' => $code,
            ],
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        $this->http->PostURL('https://www.harrods.com/api/rpc/handleIDPLoginCallback', json_encode($data), $headers);
        $this->http->JsonLog();

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $userInfo = $this->http->JsonLog();

        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
        ];

        $this->http->GetURL('https://www.harrods.com/api/en-gb/harrods/rewards', $headers);
        $rewardsInfo = $this->http->JsonLog();

        // Name
        $this->SetProperty("Name", beautifulName($userInfo->user->firstName . ' ' . $userInfo->user->lastName));
        // Rewards Card Number
        $this->SetProperty("CardNumber", $rewardsInfo->cards->number);
        // Balance
        $this->SetBalance($rewardsInfo->rewards->pointsBalance);
        // Your current Harrods Rewards status
        $this->SetProperty("Membership", $rewardsInfo->rewards->currentTier);
        // Redeemable Points Value
        $this->SetProperty("CashBalance", $rewardsInfo->rewards->cashAmount->displayValue);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'Origin'       => 'https://www.harrods.com',
            'x-shop-id'    => '10001',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.harrods.com/api/rpc/getUser", '{}', $headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (
            !empty($response->user->email)
            && strtolower($response->user->email) === strtolower($this->AccountFields['Login'])
        ) {
            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;

            $selenium->useFirefoxPlaywright();
            /*
            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->setKeepProfile(true);
            $selenium->http->setUserAgent(HttpBrowser::FIREFOX_USER_AGENT);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            */

            $selenium->disableImages();
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL('https://www.harrods.com/en-gb/account/login');
            $selenium->waitForElement(WebDriverBy::xpath("//input[@id = \"loginForm-email\"] | //form[@id=\"gigya-login-form\"]//input[@name=\"username\"] | //div[@id = \"challenge-stage\"]/div/input[@value = \"Verify you are human\"]"), 10);
            $loginXpath = '//input[@id = "loginForm-email"] | //form[@id="gigya-login-form"]//input[@name="username"]';
            $login = $selenium->waitForElement(WebDriverBy::xpath($loginXpath), 0);
            $this->savePageToLogs($selenium);

            if ($closePopup = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(., 'Agree and close')]"), 0)) {
                $closePopup->click();
                $this->savePageToLogs($selenium);
            }

            if (!$login) {
                $challengeBtn = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "challenge-stage"]/div/input[@value = "Verify you are human"]'), 0);

                if (!$challengeBtn) {
                    return $this->checkErrors();
                }
                $mouse = new MouseMover($selenium->driver);
                $mouse->logger = $this->logger;
                $mouse->duration = rand(100000, 120000);
                $mouse->steps = rand(50, 70);

                $mouse->moveToElement($challengeBtn);
                $mouse->click();

                $login = $selenium->waitForElement(WebDriverBy::xpath($loginXpath), 5);

                if (!$login) {
                    $this->sendNotification('challenge failed // BS');
                    $challengeBtn = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "challenge-stage"]/div/input[@value = "Verify you are human"]'), 0);

                    if (!$challengeBtn) {
                        $this->checkErrors();
                    }
                    $challengeBtn->click();
                    $login = $selenium->waitForElement(WebDriverBy::xpath($loginXpath), 5);
                }
                $this->savePageToLogs($selenium);
            }

            $signInButton = $selenium->waitForElement(WebDriverBy::xpath("//button[@data-test = 'loginForm-submitButton'] | //form[@id=\"gigya-login-form\"]//input[@value=\"Sign in\"]"), 0);

            $signInButton->click();

            sleep(3);
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            return true;
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 0);
        } finally {
            $selenium->http->cleanup(); //todo
        }
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);
        $this->http->GetURL("https://id.identity.harrods.com/accounts.tfa.initTFA?provider=gigyaEmail&mode=verify&regToken={$response->regToken}&APIKey={$this->apiKey}&source=showScreenSet&sdk=js_latest&pageURL=https%3A%2F%2Fidentity.harrods.com%2Flogin%3Fgig_client_id%3DASQYeQi8uUQ4CiQZC17LCK7y&sdkBuild=16851&format=json");
        $gigyaAssertion = $this->http->FindPreg("/\"gigyaAssertion\": \"([^\"]+)/");
        $this->http->setCookie('gmid',
            'gmid.ver4.AtLtAnS-_g.O6W2peg7BBsDkq4lP6ulX1m_BIzYZITxys20uYsktvFKunLuTLPpEw7VzE4ArKGJ._Cj_mV4EegA3x0WSEHL4Jm-8FX8ELtqikTsbQyiDfVoFHS8ZTKp4iwgSdl2IbkgM71BnkD77hX7EMJwrkjfK7Q.sc3');
        $this->http->setCookie('ucid', 'Q9mx8hsYEfuGmkpSU8tyyg');
        $this->http->setCookie('hasGmid', 'ver4');

        if (empty($gigyaAssertion)) {
            return false;
        }

        // get email list
        $this->logger->notice("get email list");
        $this->http->GetURL("https://id.identity.harrods.com/accounts.tfa.email.getEmails?gigyaAssertion={$gigyaAssertion}&APIKey={$this->apiKey}&sdk=js_latest&pageURL=https%3A%2F%2Fidentity.harrods.com%2Flogin%3Fgig_client_id%3DASQYeQi8uUQ4CiQZC17LCK7y&sdkBuild=16851&format=json");
        $this->http->JsonLog($this->http->FindPreg("/gigya\.callback\(([\w\W]+)\);\s*$/ims"), 3);
        $id = $this->http->FindPreg("/\"id\":\s*\"([^\"]+)/");
        $obfuscatedEmail = $this->http->FindPreg("/\"obfuscated\":\s*\"([^\"]+)/");

        if (empty($id) || empty($obfuscatedEmail)) {
            return false;
        }

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        // sending verification code to email
        $this->logger->notice("sending verification code to email");
        $this->http->GetURL("https://id.identity.harrods.com/accounts.tfa.email.sendVerificationCode?emailID={$id}&gigyaAssertion={$gigyaAssertion}&lang=en&regToken={$response->regToken}&APIKey={$this->apiKey}&sdk=js_latest&pageURL=https%3A%2F%2Fidentity.harrods.com%2Flogin%3Fgig_client_id%3DASQYeQi8uUQ4CiQZC17LCK7y&sdkBuild=16851&format=json");
        $phvToken = $this->http->FindPreg("/\"phvToken\":\s*\"([^\"]+)/");

        if (empty($phvToken)) {
            return false;
        }

        $this->State['phvToken'] = $phvToken;
        $this->State['gigyaAssertion'] = $gigyaAssertion;
        $this->State['regToken'] = $response->regToken;

        $text = "To verify your account type in the verification code that has been sent to {$obfuscatedEmail}.";

        $this->Question = $text;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = 'Question';

        return true;
    }
}
