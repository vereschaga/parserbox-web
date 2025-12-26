<?php

// refs #15574
class TAccountCheckerOwner extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public $regionOptions = [
        ""   => "Select your region",
        'us' => 'USA',
        'ca' => 'Canada',
    ];

    private $applicationId = '4f5eebbd-97ba-4ca6-a2d3-1d2d7bfe5a1a';
    private $code_challenge = 'SfJSyUsGemBuxxxIsC_NttCjNtDdLgsIG21gzsW3D-s';
    private $code_verifier = 'VEFvdUVVajdkUDMyb3R0dW9CbVpKbHRSN1BXcEp3Ylg';
    private $client_id = '77d5d23f-7d9a-4850-aaee-ddb496bb27dd';
    private $scope = '77d5d23f-7d9a-4850-aaee-ddb496bb27dd openid';
    private $redirectUri = 'https://www.ford.ca/support/';
    private $csrf = null;
    private $policy = null;
    private $transId = null;
    private $tenant = null;
    private $token = null;
    private $code = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if ($this->AccountFields['Login2'] == 'ca') {
            $this->http->SetHttp2(true);
        }

        if ($this->AccountFields['Login2'] == 'us') {
            $this->UseSelenium();
            $this->useFirefox();
            // $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
            $this->useCache();

            $this->http->saveScreenshots = true;
        }
    }

    public function IsLoggedIn()
    {
        if (empty($this->State['headers'])) {
            return false;
        }

        $this->http->RetryCount = 0;

        switch ($this->AccountFields['Login2']) {
            case 'ca':
                if ($this->loginSuccessfulCa()) {
                    return true;
                }

                return false;

            case 'us':
                $this->http->GetURL("https://www.ford.com/support/fordpass/fordpass-rewards/dashboard");

                if ($this->loginSuccessfulUsa()) {
                    return true;
                }

                return false;
        }
        $this->http->RetryCount = 2;

        return false;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields['Login2']['Options'] = $this->regionOptions;
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        if ($this->AccountFields['Login2'] == 'ca') {
            $redirectURL = 'https://www.ford.ca/support/fordpass/fordpass-rewards/dashboard';
        } else {
            $redirectURL = 'https://owner.ford.com/sign-in.html';
        }

        $arg["RedirectURL"] = $redirectURL;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        switch ($this->AccountFields['Login2']) {
            case 'ca':
                $authParams = [
                    'redirect_uri'          => $this->redirectUri,
                    'response_type'         => 'code',
                    'state'                 => '{"policy":"email_susi_policy","lang":"en_ca","state":"L3ZlaGljbGUtZGFzaGJvYXJk","queryHash":"","forwardUrl":""}',
                    'client_id'             => $this->client_id,
                    'scope'                 => $this->scope,
                    'code_challenge'        => $this->code_challenge,
                    'code_challenge_method' => 'S256',
                    'ui_locales'            => 'en-CA',
                    'template_id'           => 'Ford-MFA-Authentication',
                    'ford_application_id'   => $this->applicationId,
                    'country_code'          => 'CAN',
                    'language_code'         => 'en-CA',
                ];

                $authUrl = "https://login.ford.ca/4566605f-43a7-400a-946e-89cc9fdb0bd7/B2C_1A_SignInSignUp_en-CA/oauth2/v2.0/authorize?" . http_build_query($authParams);
                $this->http->GetURL($authUrl);

                $this->csrf = $this->http->FindPreg('/\"csrf\":\"([^"]+)\"/');
                $this->policy = $this->http->FindPreg('/\"policy\":\"([^"]+)\"/');
                $this->transId = $this->http->FindPreg('/\"transId\":\"([^"]+)\"/');
                $this->tenant = $this->http->FindPreg('/\"tenant\":\"([^"]+)\"/');

                if (!$this->transId || !$this->tenant || !$this->policy || !$this->csrf) {
                    return $this->checkErrors();
                }

                $data = [
                    'request_type' => 'RESPONSE',
                    'signInName'   => $this->AccountFields['Login'],
                    'password'     => $this->AccountFields['Pass'],
                ];

                $headers = [
                    "Accept"           => "application/json",
                    "Accept-Encoding"  => "gzip, deflate, br",
                    "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
                    "X-CSRF-TOKEN"     => $this->csrf,
                    "X-Requested-With" => "XMLHttpRequest",
                ];

                $this->http->PostURL("https://login.ford.ca$this->tenant/SelfAsserted?tx=$this->transId&p=$this->policy", $data, $headers);

                return true;

            case 'us':
                try {
                    $this->driver->manage()->window()->maximize();

                    try {
                        $this->http->GetURL('https://www.ford.com/');
                    } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                        $this->logger->error("Exception: " . $e->getMessage());
                        $this->driver->executeScript('window.stop();');
                    }

                    $this->driver->executeScript('var btn = document.querySelector(\'button[data-testid="undefined-button"], a[href="#$userSignIn"]\').click(); if (btn) btn.click();');

                    $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="signInName"]'), 10);
                    $pass = $this->waitForElement(WebDriverBy::xpath('//input[@id="password"]'), 0);
                    $btn = $this->waitForElement(WebDriverBy::xpath('//button[@id="next"]'), 0);

                    $this->saveResponse();

                    if (!$login || !$pass || !$btn) {
                        return false;
                    }

                    $login->sendKeys($this->AccountFields['Login']);
                    $pass->sendKeys($this->AccountFields['Pass']);
                    $btn->click();
                } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                    $this->logger->error("TimeOutException exception: " . $e->getMessage());
                    $this->DebugInfo = "TimeOutException";
                    // retries
                    if (strstr($e->getMessage(), 'timeout') && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                        throw new CheckRetryNeededException(3, 5);
                    }
                } // catch (TimeOutException $e)

                break;
        }

        return true;
    }

    public function Login()
    {
        switch ($this->AccountFields['Login2']) {
            case 'ca':
                $data = $this->http->JsonLog(null, 3, true);
                $status = $data['status'] ?? null;
                $errorCode = $data['errorCode'] ?? null;
                $message = $data['message'] ?? null;

                if ($status != 200 && $errorCode && $message) {
                    $this->logger->error("[Error]: {$message}");

                    if (
                        strstr($message, 'We do not recognize this email and password. Please try again.')
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    return false;
                }

                $param = [];
                $param['rememberMe'] = "true";
                $param['csrf_token'] = $this->csrf;
                $param['tx'] = $this->transId;
                $param['p'] = $this->policy;
                $param['diags'] = '{"pageViewId":"258541d1-06ff-4ec3-8faf-8438b78e842e","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1705494832,"acD":2},{"ac":"T021 - URL:https://prodb2cuicontentdelivery-d0bbevfjaxfmedda.z01.azurefd.net/b2cui/ui/ford/en-CA/unified.html?ver=20240111.4&SessionId=4e315adb-7b3f-4de7-bb2a-b76c577278b3&InstanceId=c990bb7a-51f4-439b-bd36-9c07fb1041c0","acST":1705494832,"acD":787},{"ac":"T019","acST":1705494833,"acD":25},{"ac":"T004","acST":1705494833,"acD":4},{"ac":"T003","acST":1705494833,"acD":2},{"ac":"T035","acST":1705494833,"acD":0},{"ac":"T030Online","acST":1705494833,"acD":0},{"ac":"T002","acST":1705494868,"acD":0},{"ac":"T018T010","acST":1705494867,"acD":1166}]}';

                $this->http->GetURL("https://login.ford.ca$this->tenant/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));

                $this->code = $this->http->FindPreg("/\&code=([^&]+)/", false, $this->http->currentUrl());

                if (!$this->code) {
                    $this->logger->notice("code not found");

                    return $this->checkErrors();
                }

                $data = [
                    "client_id"     => $this->client_id,
                    "grant_type"    => "authorization_code",
                    "scope"         => $this->scope,
                    "redirect_uri"  => $this->redirectUri,
                    "code"          => $this->code,
                    "code_verifier" => $this->code_verifier,
                ];

                $headers = [
                    "Accept"          => "application/json",
                    "Accept-Encoding" => "gzip, deflate, br",
                    "content-type"    => "application/x-www-form-urlencoded;charset=utf-8",
                ];

                $this->http->PostURL("https://login.ford.ca$this->tenant/oauth2/v2.0/token", $data, $headers);
                $data = $this->http->JsonLog(null, 3, true);
                $token = $data['access_token'] ?? null;

                $headers = [
                    "Application-Id"  => $this->applicationId,
                    "Content-Type"    => "application/json",
                    "Accept"          => "application/json",
                ];
                $data = [
                    "idpToken" => $token,
                ];

                $this->http->PostURL("https://api.pd01e.gcp.ford.com/api/token/v2/cat-with-b2c-access-token", json_encode($data), $headers);

                $data = $this->http->JsonLog(null, 3, true);
                $this->token = $data['access_token'] ?? null;

                if (!$this->token && $this->http->Response['code'] != 200) {
                    return $this->checkErrors();
                }

                if (!$this->loginSuccessfulCa()) {
                    return $this->checkErrors();
                }

                $data = $this->http->JsonLog(null, 3, true);

                if (strtolower($data['profile']["email"]) !== strtolower($this->AccountFields['Login'])) {
                    $this->logger->error("the data does not match the requested account");

                    return false;
                }

                return true;

            case 'us':
                if ($this->loginSuccessfulUsa()) {
                    return true;
                }

                if ($this->processQuestion()) {
                    return false;
                }

                // provider bug fix, data not loaded after auth
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

                if ($this->http->FindSingleNode('//div[contains(text(), "Incorrect user ID or password. Enter a valid user ID or password.")]')) {
                    throw new CheckException('Incorrect user ID or password. Enter a valid user ID or password.', ACCOUNT_INVALID_PASSWORD);
                }
                // Incorrect email (username) or password. Please enter a valid email (username) or password.
                if ($message = $this->http->FindSingleNode('//div[
                        contains(text(), "Incorrect email (username) or password")
                        or contains(text(), "The email/username and password didn’t match our records. Please try again.")
                    ]
                    | //span[contains(text(), "The email/username and password didn’t match our records. Please try again.")]
                ')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error') and @style = 'display: block;']")) {
                    $this->logger->error("[Error]: {$message}");

                    if (
                        strstr($message, 'We do not recognize this email and password. Please try again.')
                        || strstr($message, 'Too many login attempts. Please try again later.')
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                // The provider requires you to link your email address
                if (
                    $this->http->FindSingleNode('//div[@class="announcer-pretext"]/text()')
                    && $this->http->FindSingleNode('//div[@class="announcer-subject"]/text()')
                    && !$this->http->FindSingleNode('//input[@id="verificationCode"]/@id')
                    && $message = $this->http->FindSingleNode('//p[contains(text(), "We are updating the way you sign in to your account")]')
                ) {
                    $this->logger->error("[Error]: {$message}");

                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if ($message = $this->http->FindSingleNode('//p[contains(text(), "Your current password has either expired or no longer meets security requirements.")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                return true;
        }

        return $this->checkErrors();
    }

    public function processQuestion()
    {
        $this->logger->notice(__METHOD__);

        $this->saveResponse();
        $question = $this->http->FindSingleNode('//div[@class="announcer-pretext"]/text()');
        $email = $this->http->FindSingleNode('//div[@class="announcer-subject"]/text()');

        if (!$question) {
            $this->logger->notice("question not found");

            return false;
        }

        $question = $question . ' ' . $email;

        $questionInput = $this->waitForElement(WebDriverBy::xpath('//input[@id="verificationCode"]'), 0);

        if (!$questionInput) {
            $this->logger->error("question input not found");

            return false;
        }

        if ($this->getWaitForOtc()) {
            $this->sendNotification('refs #23459 user with mailbox was found // IZ');
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return true;
        }

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        $questionInput->clear();
        $questionInput->sendKeys($answer);
        $this->logger->debug("ready to click");
        $this->saveResponse();
        $aceptar2fa = $this->waitForElement(WebDriverBy::xpath('//button[@class = "verifyCode"]'), 0);
        $this->saveResponse();

        if (!$aceptar2fa) {
            $this->logger->error("btn not found");

            return false;
        }

        $this->logger->debug("clicking next");
        $aceptar2fa->click();

        sleep(5);
        $this->saveResponse();

        if ($error = $this->waitForElement(WebDriverBy::xpath('//div[@class="error new-pageLevel fma-visible"]'), 5)) {
            $message = $error->getText();
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Please enter a valid verification code')) {
                $this->holdSession();
                $this->AskQuestion($question, $message, "Question");
            }
            $this->DebugInfo = $message;
        }

        $this->saveResponse();

        return true;
    }

    public function Parse()
    {
        switch ($this->AccountFields['Login2']) {
            case 'ca':
                $this->ParseCa();

                break;

            case 'us':
                $this->ParseUSA();

                break;
        }
    }

    private function loginSuccessfulUsa()
    {
        $this->logger->notice(__METHOD__);
        $logoutItemXpath = '//a[@href="#$userSignOut"]';
        $this->waitForElement(WebDriverBy::xpath($logoutItemXpath), 7, false);
        $this->saveResponse();

        if ($this->http->FindSingleNode($logoutItemXpath, null, true)) {
            return true;
        }

        return false;
    }

    private function loginSuccessfulCa()
    {
        $this->logger->notice(__METHOD__);

        $data = $this->getData('https://api.mps.ford.com/api/users', 3, true);
        $error = $data['error'];

        if ($error === null) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        switch ($this->AccountFields['Login2']) {
            case 'ca':
                break;

            case 'us':
                if ($this->http->FindSingleNode('//img[@src = "http://owner.ford.com/failover/ford-down-no-contact.png"]/@src')) {
                    throw new CheckException("This website will be or currently is being refreshed. Some content may not be available. Thank you for your patience during this maintenance period.", ACCOUNT_PROVIDER_ERROR);
                }

                break;
        }

        return false;
    }

    private function ParseCa()
    {
        $this->logger->notice(__METHOD__);

        $data = $this->http->JsonLog(null, 3, true);

        // Name
        $this->SetProperty('Name', beautifulName(($data['profile']['firstName'] ?? '') . ' ' . ($data['profile']['middleName'] ?? '') . ' ' . ($data['profile']['lastName'] ?? '')));
        // Account Number - Member #
        $this->SetProperty('Number', $data['profile']['memberId']);

        $data = $this->getData('https://api.mps.ford.com/api/rewards-account-info/v1/customer/points/totals?rewardProgram=F&programCountry=CAN', 3, true);

        // Balance - Total Points
        $this->setBalance($data['pointsTotals']['F']['points']);
    }

    private function ParseUSA()
    {
        try {
            $this->http->GetURL("https://www.ford.com/support/fordpass/fordpass-rewards/dashboard");

            $acceptNewTermsBtn = $this->waitForElement(WebDriverBy::xpath('//span[text()="Accept new terms"]/../'), 10);

            if ($acceptNewTermsBtn) {
                $acceptNewTermsBtn->click();
            }

            $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Member ID:")]'), 0);

            $this->saveResponse();
            // Balance - TOTAL POINTS
            $this->SetBalance($this->http->FindSingleNode('
            //div[contains(text(), "TOTAL POINTS")]/../span[1]
            | //div[contains(text(), "Total Points")]/../span[1]
            '));

            // Tier
            $this->SetProperty("Status", $this->http->FindSingleNode('//div[@id="loyalty-webpages-container"]//span[text()="Tier"]/../text()[1]', null, true));

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                $balance = $this->waitForElement(WebDriverBy::xpath('//span[@id = "pilot-pts-txt" and text() != "" and text() != "NA"]'), 5);

                if ($balance) {
                    $this->SetBalance($balance->getText());
                } elseif ($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Accept new terms")]'), 0)) {
                    $this->throwAcceptTermsMessageException();
                }

                $this->saveResponse();
            }

            // Tier Activities
            $this->SetProperty('TierActivities', $this->http->FindSingleNode('//img[contains(@src, "speed")]/../div/text()'));
            // Tier Activities to next status
            $this->SetProperty('TierActivitiesToNextStatus', $this->http->FindSingleNode('//span[contains(text(), "Complete") and contains(text(), "more Tier Activities by")]', null, false, '/Complete\s(\d)/'));

            $this->http->GetURL("https://www.ford.com/myaccount/account-settings");
            $this->waitForElement(WebDriverBy::xpath('//h3[normalize-space(text()) = "NAME"]/following-sibling::div[1]'), 5);

            $this->saveResponse();

            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h3[normalize-space(text()) = "Name"]/following-sibling::div[1]')));
            // Member ID
            $this->SetProperty("Number", $this->http->FindSingleNode('//div[contains(text(), "Member ID:")]', null, true, "/:\s*(\d+)/"));

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                if ($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Accept new terms")]'), 0)) {
                    $this->throwAcceptTermsMessageException();
                }
            }
        } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->DebugInfo = "TimeOutException";

            // retries
            if (strstr($e->getMessage(), 'timeout') && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 5);
            }
        }
    }

    private function getData(string $url, $logs = 0, $convertToArray = false)
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL($url, [
            'Accept'         => 'application/json, text/plain, */*',
            'Auth-Token'     => $this->token,
            'Application-Id' => $this->applicationId,
        ]);

        return $this->http->JsonLog(null, $logs, $convertToArray);
    }
}
