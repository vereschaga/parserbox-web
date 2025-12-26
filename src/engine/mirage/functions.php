<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerMirage extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use PriceTools;
    use ProxyList;

    private $futureItinsData = null;
    private $pastItinsData = null;
    private $accessToken = null;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'mirageSlotDollars')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (!strstr($this->AccountFields['Login'], '@')) {
            throw new CheckException("Please enter a valid e-mail address.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->RetryCount = 1;
        $this->http->GetURL('https://www.mgmresorts.com/en.html');
        /*
        $this->http->GetURL('https://www.mgmresorts.com/identity?client_id=mgm_app_web&redirect_uri=https%3A%2F%2Fwww.mgmresorts.com%2Fen%2Fauth%2Flogin.html%3FreturnUri%3D%252Fen%252Fsign-in%252Fprofile.html&scopes=loyalty:profile:read');

        $headers = [
            "Accept"                     => "application/json",
            'Content-Type'               => 'application/json',
//            'User-Agent'                 => 'Bilt/1 CFNetwork/976 Darwin/18.2.0',
            'X-Okta-User-Agent-Extended' => 'okta-auth-js/4.9.2',
        ];

        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
            "options"  => [
                "multiOptionalFactorEnroll" => true,
                "warnBeforePasswordExpired" => true,
            ],
        ];
        $this->http->PostURL("https://identity.mgmresorts.com/api/v1/authn", json_encode($data), $headers);

        return true;
        */

//        if (!$this->http->ParseForm("signin-widget-form")) {
        if ($this->http->Response['code'] !== 200 && stripos($this->http->Error, 'stream 0 was not closed cleanly') === false) {
            return $this->checkErrors();
        }
        /*$this->http->FormURL = 'https://www.mgmresorts.com/mgm-web/authentication/en/v1/login';
        $this->http->SetInputValue("customerEmail", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("propertyId", "mgmresorts");*/

        $xKeys = $this->selenium();

        if (!$xKeys) {
            return false;
        }

//        $data = [
//            "customerEmail" => $this->AccountFields['Login'],
//            "password"      => $this->AccountFields['Pass'],
//            //"flow"          => "signInPage",
//            "propertyId"    => "mgmresorts"
//        ];
//        $headers = [
//            //"csrf-token"   => $this->http->getCookieByName("csrf-token"),
//            "Content-Type" => "application/x-www-form-urlencoded;charset=utf-8",
//            "Accept"       => "application/json, text/plain, */*"
//        ];
//        foreach ($xKeys as $xKey) {
//            if (isset($xKey['name'], $xKey['value'])) {
//                $headers[$xKey['name']] = $xKey['value'];
//            }
//        }
//        foreach ($headers as $header => $value)
//            $this->http->setDefaultHeader($header, $value);
//        $this->http->PostURL('https://www.mgmresorts.com/mgm-web/authentication/en/v1/login', $data, $headers);

        return true;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $xKeys = true;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 3;
            $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

            $selenium->http->start();
            $selenium->Start();
            $selenium->keepCookies(false);
            $selenium->http->saveScreenshots = true;
            $selenium->http->GetURL("https://www.mgmresorts.com/");
            sleep(7);
            $selenium->http->GetURL("https://www.mgmresorts.com/identity/?client_id=mgm_app_web&redirect_uri=https%3A%2F%2Fwww.mgmresorts.com%2Frewards%2Fauth%2Flogin%3Fpath%3D%2Frewards%2F&scopes=");
            $loginInput = $selenium->waitForElement(WebDriverBy::id('email'), 10);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput) {
                return $this->checkErrors();
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-testid="sign-in-or-join" and not(@disabled)]'), 0);
            $this->savePageToLogs($selenium);

            if (!$btn) {
                if ($message = $this->http->FindSingleNode('//span[@id = "email-hint"]')) {
                    $this->logger->error("[Error]: {$message}");

                    if ($message == 'Please enter a valid email') {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                return $this->checkErrors();
            }

            $btn->click();

            $passwordInput = $selenium->waitForElement(WebDriverBy::id('password'), 10);
            $this->savePageToLogs($selenium);
            $message = $this->http->FindSingleNode('//div[contains(@class, "identity__alert-text")]/span');
            $this->logger->error("[Message]: {$message}");

            // it helps
            if (!$passwordInput
                && (
                    strstr($message, "Cannot read property 'toLowerCase' of undefined")
                    || strstr($message, "Cannot read properties of undefined")
                    || strstr($message, "can't access property \"toLowerCase\", ")
                )
            ) {
                sleep(2);
                $btn->click();
                $passwordInput = $selenium->waitForElement(WebDriverBy::id('password'), 10);
            }

            if (!$passwordInput) {
                $this->savePageToLogs($selenium);
                $message = $this->http->FindSingleNode('//div[contains(@class, "identity__alert-text")]/span');

                if (isset($message)) {
                    $this->logger->error("[Error]: {$message}");

                    if (
                        stristr($message, 'Internal server error')
                        || stristr($message, 'Oops! We have encountered an unknown error, please contact support.')
                        || stristr($message, 'Oops! Something went wrong, please try again')
                    ) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }
                    if (
                        strstr($message, "We're sorry, but this account is locked. ")
                    ) {
                        throw new CheckException($message, ACCOUNT_LOCKOUT);
                    }

                    if (
                        strstr($message, "Cannot read property 'toLowerCase' of undefined")
                        || strstr($message, "can't access property \"toLowerCase\", ")
                        || strstr($message, "Cannot read properties of undefined")
                    ) {
                        $retry = true;
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                if ($this->http->FindSingleNode('//h1[contains(text(), "Welcome! We found your account but need some extra info to get you signed in.") or contains(text(), "Reset Password")]')) {
                    $this->throwProfileUpdateMessageException();
                }

                return $this->checkErrors();
            }

            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-testid="sign-in"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$btn) {
                if ($this->http->FindSingleNode('//span[contains(text(), "Please provide the following information required in accordance with gaming regulations.")]')) {
                    $this->throwProfileUpdateMessageException();
                }

                return $this->checkErrors();
            }

            sleep(1);

            try {
                $btn->click();
            } catch (UnknownServerException | UnrecognizedExceptionException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            }
            // save page to logs
            $this->savePageToLogs($selenium);

            $welcomeText = $selenium->waitForElement(WebDriverBy::xpath("//div[@data-testid = 'account number']/div[1] | //h6[contains(text(), 'Welcome, ')] | //h3[contains(text(),'My Profile')]"), 15);
            // save page to logs
            $this->savePageToLogs($selenium);

            if ($selenium->waitForElement(WebDriverBy::xpath("
                    //div[contains(text(), 'Logging in...') or contains(text(), 'Loading your information...')]
                    | //button[@data-testid = 'sign-in']//*[@data-testid = 'loading-spinner-icon']
                    | //*[contains(text() , 'loading...')]
                "), 0)
            ) {
                $welcomeText = $selenium->waitForElement(WebDriverBy::xpath("//div[@data-testid = 'account number']/div[1] | //h6[contains(text(), 'Welcome, ')] | //h3[contains(text(),'My Profile')]"), 25);
                // save page to logs
                $this->savePageToLogs($selenium);

                if ($selenium->waitForElement(WebDriverBy::xpath("
                    //div[contains(text(), 'Logging in...') or contains(text(), 'Loading your information...')]
                    | //button[@data-testid = 'sign-in']//*[@data-testid = 'loading-spinner-icon']
                    | //*[contains(text() , 'loading...')]
                "), 0)) {
                    $welcomeText = $selenium->waitForElement(WebDriverBy::xpath("//div[@data-testid = 'account number']/div[1] | //h6[contains(text(), 'Welcome, ')] | //h3[contains(text(),'My Profile')]"), 45);
                    // save page to logs
                    $this->savePageToLogs($selenium);
                }
            }

            if (!$welcomeText && !$this->http->FindSingleNode("//div[@data-testid = 'account number']/div[1] | //h6[contains(text(), 'Welcome, ')]")) {
                // Incorrect email-password combination. Please make sure your information is correct.
                // Unfortunately, we could not find your account. For assistance, please call 866.761.7111.
                if ($message = $selenium->waitForElement(WebDriverBy::xpath("
                        //p[contains(text(), 'Incorrect email-password combination. Please make sure your information is correct.')]
                        | //span[contains(text(), 'Failed to authenticate, check your email and password and try again')]
                        | //p[contains(text(), 'Unfortunately, we could not find your account.')]
                        | //p[contains(text(), 'We could not find a matching account with the information provided.')]
                        | //span[contains(text(), 'Failed to authenticate, check your email and password and try again')]
                        | //span[contains(text(), 'Please enter a valid email')]
                    "), 0)
                ) {
                    throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
                }
                // Please enter a valid email address.
                if ($selenium->waitForElement(WebDriverBy::xpath("//*[self::a or self::p][contains(text(),'Please enter a valid email address.')]"), 0)) {
                    throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
                }
                // Sorry, there was a system error. Please call 866.761.7111 for assistance.
                if ($message = $selenium->waitForElement(WebDriverBy::xpath("
                        //p[contains(text(),'Sorry, there was a system error. Please call')]
                        | //div[@class = 'form-errors show' and not(contains(@style, 'display: block;'))]/descendant::p[text() = 'Sorry, there was a system error. Please try again later. [error MLR001]']
                        | //h2[contains(text(), 'The MGM Resorts website is currently undergoing maintenance.')]
                        | //div[contains(text(), 'There was a problem accessing your account information.')]
                        | //*[self::p or self::span][
                            contains(text(), 'We are performing maintenance on the M life Rewards account system. Sign-up and log-in')
                            or contains(text(), 'Account not verified. Please click the activation link in the M life Rewards sign-up confirmation email.')
                            or contains(text(), 'We are performing maintenance on the MGM Rewards account system. Sign-up and log-in functions are not available.')
                        ]
                        | //span[contains(text(), 'Unable to sign in')]
                    "), 0)
                ) {
                    throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
                }

                if ($selenium->waitForElement(WebDriverBy::xpath("
                        //h2[contains(text(), 'Create your MGM Digital marketing portal account')]
                    "), 0)
                ) {
                    $this->throwProfileUpdateMessageException();
                }

                $this->savePageToLogs($selenium);

                if ($message = $this->http->FindSingleNode("
                        //div[@class = 'form-errors show' and not(contains(@style, 'display: block;'))]
                        | //div[contains(@class, 'error') and @data-testid = 'sign-in-error']/*[self::span or self::div][contains(@class, 'identity__alert-text')]
                    ")
                ) {
                    $this->logger->error("[Error]: {$message}");

                    if (
                        strstr($message, 'Unfortunately, we could not find your account.')
                        || strstr($message, 'We could not find a matching account with the information provided. ')
                        || strstr($message, 'Your email-password combination may be incorrect. If you continue to experience issues, please reset your password.')
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    if (
                        strstr($message, 'We\'re sorry, but this account is locked. ')
                        || strstr($message, 'Your account has been locked due to too many failed sign in attempts.')
                    ) {
                        throw new CheckException($message, ACCOUNT_LOCKOUT);
                    }

                    if ($message == 'Failed to authenticate, please try again later') {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                // debug
                if (
                    $this->http->FindSingleNode('//div[@class="wrapper home-wrapper page-load" and @loading-animation="page-load"]')
                    || $selenium->waitForElement(WebDriverBy::id('email'), 0)
                ) {
                    $retry = true;
                }

                $this->logger->error("something went wrong");

                if (in_array($this->AccountFields['Login'], [
                    'iiro@mantere.info',
                ])) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                /*if (!$selenium->waitForElement(WebDriverBy::xpath("//div[@data-testid = 'account number']/div[1] | //h1[contains(text(), 'Welcome, ')]"), 0)
                || $selenium->waitForElement(WebDriverBy::xpath("//div[@data-testid = 'account number']/div[1] | //h1[contains(text(), 'Welcome, ')]"), 0, false)) {
                    return false;
                }*/
                return true;
            }

//            $selenium->http->GetURL('https://www.mgmresorts.com/en/sign-in/profile.html#/welcome');
//            $selenium->http->GetURL('https://www.mgmresorts.com/account/rewards/');

            /*
             $rewardsLinkClicked = $selenium->driver->executeScript(/ ** @lang JavaScript * / '
                let rewardsLink = document.querySelector(`nav[aria-label="My Rewards"] a[href="/rewards/"]`);
                if (rewardsLink) rewardsLink.click();
                else return false;
            ');

            if ($rewardsLinkClicked === false) {
                $this->logger->error('no rewards link found');

                return false;
            }
            */
            $selenium->waitForElement(WebDriverBy::xpath('//h4[normalize-space(text()) = "Borgata SLOT DOLLARS"]/following-sibling::p//div[@class = "widget-balance"]/span'), 15);

            if ($selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Loading your information...')]"), 0)) {
                $this->savePageToLogs($selenium);
                $selenium->waitForElement(WebDriverBy::xpath('//h4[contains(normalize-space(text()), "SLOT DOLLARS")]/following-sibling::p//div[@class = "widget-balance"]/span'), 15);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] === 'test') {
                    continue;
                }
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            // save page to logs
            $this->savePageToLogs($selenium);

            $this->ParseSelenium($selenium);

            if ($this->ParseIts) {
                $this->futureItinsData = $this->loadItinerariesNew($selenium);

                if ($this->ParsePastIts) {
                    $this->pastItinsData = $this->loadItinerariesNew($selenium, 'PAST');
                }

                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    if ($cookie['name'] === 'test') {
                        continue;
                    }
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            }
        } catch (
            NoSuchDriverException
            | NoSuchWindowException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            StatLogger::getInstance()->info("mirage login attempt", [
                "success"      => !$retry,
                "browser"      => $selenium->seleniumRequest->getBrowser() . ":" . $selenium->seleniumRequest->getVersion(),
                "userAgentStr" => $selenium->http->userAgent,
                "resolution"   => ($selenium->seleniumOptions->resolution[0] ?? 0) . "x" . ($selenium->seleniumOptions->resolution[1] ?? 0),
                "attempt"      => $this->attempt,
                "isWindows"    => stripos($selenium->http->userAgent, 'windows') !== false,
            ]);

            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(2, 5);
            }
        }

        return $xKeys;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://www.mgmresorts.com';

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We are performing maintenance on our M life Rewards account system.
        if ($message = $this->http->FindSingleNode('//*[self::p or self::span][
                contains(normalize-space(text()), "We are performing maintenance on our M life Rewards account system.")
                or contains(normalize-space(text()), "We are performing maintenance on the M life Rewards account system.")
                or contains(normalize-space(text()), "We are performing maintenance on the MGM Rewards account system.")
                or contains(text(), "Sorry! We are upgrading our website to provide you with the best experience, so a few pages are down")
                or contains(text(), "Some of our systems are temporarily unavailable.")
            ]
            | //h2[contains(text(), "The MGM Resorts website is currently undergoing maintenance")
                or contains(text(), "MGM Rewards account balances are currently unavailable")
                or contains(text(), "The MGM Resorts website is currently unavailable")
                or contains(text(), "MGM Rewards accounts are currently unavailable")]
        ')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are performing system upgrades.
        if ($message = $this->http->FindSingleNode("(//p[contains(text(), 'We are performing system upgrades.')])[1]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Reservation System Down
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Our online reservation is temporarily down')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The M life Rewards website is currently undergoing maintenance.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'The M life Rewards website is currently undergoing maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'This site is temporarily unavailable as it undergoes')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(This site is temporarily unavailable)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // HTTP Status 404
        if ($this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 404')]")
            // Internal Server Error
            || $this->http->FindSingleNode("//h2[contains(text(), 'Server Error')]")
            // Internal Server Error
            || $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")
            // ???
            || $this->http->FindSingleNode("//title[normalize-space(text()) = '502 Proxy Error']")
            // File not found."
            || $this->http->FindPreg("/File not found\.\"/")
            // provider bug
            || ($this->http->Response['code'] == 503 && empty($this->http->Response['body']))
            || ($this->http->Response['code'] == 0 && $this->http->currentUrl() == 'https://www.mgmresorts.com/mgm-web/authentication/en/v1/login')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        /*
        $response = $this->http->JsonLog();
        $sessionToken = $response->sessionToken ?? null;

        if (!$sessionToken) {
            // We do not recognize this email / password combination.
            $message = $response->errorSummary ?? null;

//            if ($message == 'Authentication failed') {
//                throw new CheckException('We do not recognize this email / password combination.', ACCOUNT_INVALID_PASSWORD);
//            }

            return $this->checkErrors();
        }

        $clientId = 'mgm_app_web';
        $state = 'avg62Q90hsIc8B0iMMFXPiiyAB0zpbUXgYznXrjoKeJOF1WOQbtfLlki0gFyzyMT';
        $nonce = 'YunTBSjy2fGPsPB98qZoy21R8B8ai3qYBKfdZRtENdZ57nXzo79PHPQIxSMvDMsF';
        $code_verifier = 'o233v2FBWlTvtuHRRIiGxoUXgx4inB9vuNJ_wA4Fmhs';
        /*
        $o = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~";

        for ($r = 0; $r < 128; $r++) {
            $pos = (int) (floor((float) rand() / (float) getrandmax() * mb_strlen($o)));
            $code_verifier .= $o[$pos];
        }

        $this->logger->debug("code_verifier: {$code_verifier}");
        $hash = hash('sha256', $code_verifier);
        $code_challenge = $this->base64url_encode(pack('H*', $hash));
        $this->logger->debug("code_challenge: {$code_challenge}");
        * /
        $code_challenge = "ti582mZ_rox3ILFQ-IW6aDp3aSSx2-RHNNlHlDg8dfM";

        $param = [];
        $param['client_id'] = $clientId;
        $param['response_mode'] = 'okta_post_message';
        $param['response_type'] = 'code';
        $param['code_challenge_method'] = 'S256';
        $param['code_challenge'] = $code_challenge;
        $param['redirect_uri'] = 'https://www.mgmresorts.com/en/auth/login.html';
        $param['nonce'] = $nonce;
        $param['state'] = $state;
        $param['sessionToken'] = $response->sessionToken;
        $param['prompt'] = 'none';
        $param['scope'] = 'loyalty:profile:read openid profile email';

        $headers = [
            "Accept"          => "*
        /*",
            "Accept-Encoding" => "gzip, deflate",
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://identity.mgmresorts.com/oauth2/ausdz02gi5cZ8h8NP1t7/v1/authorize?" . http_build_query($param), $headers);
        $this->http->RetryCount = 2;
        $code = $this->http->FindPreg("/data.code = '([^']+)/");

        if (!$code) {
            $this->logger->error("code not found");

            return $this->checkErrors();
        }

        $headers = [
            "Accept"                     => "application/json",
            "Content-Type"               => "application/x-www-form-urlencoded",
            "X-Okta-User-Agent-Extended" => "okta-auth-js/4.9.2",
            "Origin"                     => "https://www.mgmresorts.com",
        ];
        $data = [
            "client_id"     => $clientId,
            "grant_type"    => "authorization_code",
            "redirect_uri"  => "https://www.mgmresorts.com/en/auth/login.html",
            "code_verifier" => $code_verifier,
            "code"          => $code,
        ];
        $this->http->PostURL("https://azdeapi.mgmresorts.com/identity/authorization/v1/default/token", $data, $headers);
        $response = $this->http->JsonLog();

        if (isset($response->id_token)) {
            $data = [
                "accessToken" => $response->access_token,
                "idToken"     => $response->id_token,
            ];
//            $this->http->PostURL("https://www.mgmresorts.com/mgm-web/profile/en/v1/detail.sjson", "propertyId=mgmresorts", $headers);
            $this->http->PostURL("https://www.mgmresorts.com/mgm-web/authentication/en/v2/login", $data, $headers);

            return true;
        }
        */

//        //$this->http->GetURL('https://mgmdmp.okta.com/login/sessionCookieRedirect?token=20111Lpdgvuu1dqCIv0dCZBJiIEvH7CgKda4-EcFGDdhwH9iavf4qDw&redirectUrl=https://www.mgmresorts.com/content/mgmresorts/en/sign-in/profile.html');
//
//        $this->http->GetURL('https://www.mgmresorts.com/content/mgmresorts/en/sign-in/profile.html');
//        $headers = [
//            //'x-requested-with' => 'XMLHttpRequest',
//        ];
//        $this->http->GetURL('https://www.mgmresorts.com/mgm-web/authentication/en/v1/identifyloggedinuser?date=1540470659319', $headers);
//        $headers = [
//            'csrf-token' => $this->http->getCookieByName('csrf-token'),
//            //'x-requested-with' => 'XMLHttpRequest',
//        ];
//        $data = [
//            'propertyId' => 'mgmresorts',
//        ];
//        $this->http->PostURL("https://www.mgmresorts.com/mgm-web/profile/en/v1/detail.sjson", $data, $headers);
//        $response = $this->http->JsonLog();

        return true;
        $response = $this->http->JsonLog();
        // Access is allowed
        if (isset($response->response)) {
            return true;
        }
        // catch errors
        if (isset($response->messages[0]->code)) {
            switch ($response->messages[0]->code) {
                case '_system_error':
                    throw new CheckException("Due to a System error, we can't process your request at this time. Please try again later. ", ACCOUNT_PROVIDER_ERROR);

                    break;

                case '_invalid_credentials':
                case '_existing_patron_profile':
                    throw new CheckException("Incorrect email-password combination. Please make sure your information is correct.", ACCOUNT_INVALID_PASSWORD);

                    break;

                case '_account_not_found':
                    throw new CheckException("Sorry, we were unable to update your account (profile mismatch). Please contact M life Member Services at 866.761.7111 for further assistance.", ACCOUNT_INVALID_PASSWORD);

                    break;

                case '_more_than_one_account_found':
                    throw new CheckException("Your information matches multiple accounts. If you have your M life number, please enter it and try again. The number is on your M life card and in emails from M life. For assistance, please call 866-761-7111.", ACCOUNT_PROVIDER_ERROR);

                    break;

                case '_account_is_not_active':
                    throw new CheckException("Account not verified. Please click the activation link in the M life sign-up confirmation email.", ACCOUNT_PROVIDER_ERROR);

                    break;

                default:
                    $this->logger->notice("Unknown error code: {$response->messages[0]->code}");
            }
        }// if (isset($response->messages[0]->code))

        return $this->checkErrors();
    }

    public function ParseSelenium($selenium)
    {
        $this->logger->notice(__METHOD__);

        if ($skip = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "skip-button")] | //button[span[normalize-space(text()) = "Skip"]] | //button[@data-testid="onboarding-modal-icon"]'), 0)) {
            $skip->click();

            $selenium->waitForElement(WebDriverBy::xpath('//button[@data-testid="card-container"]/following-sibling::p[contains(text(),"#")]'), 10);
            $this->savePageToLogs($selenium);
        }

        $this->http->FilterHTML = true;
        // Balance - MGM Rewards Points
        if (
            !$this->SetBalance($this->http->FindSingleNode('//div[button[contains(.,"MGM Rewards Points")]]/following-sibling::div/p[1]', null, false, self::BALANCE_REGEXP))
        ) {
            if ($this->http->FindSingleNode("//span[contains(text(), 'Book a stay to begin earning toward new tiers of exclusive benefits.')]")) {
                $this->SetBalance(0);
            }
            // AccountID: 4361734, 3813456
            elseif ($this->http->FindSingleNode('//span[@data-testid = "card-name-tier"]') == 'NOIR') {
                $this->SetBalanceNA();
            } elseif (
                // AccountID: 1977572
                $this->http->FindSingleNode('//span[@data-testid = "card-name-tier"]') == 'Platinum'
                && in_array($this->AccountFields['Login'], [
                    'naresh100@yahoo.com',
                    'travishaynes@yahoo.com',
                ])
            ) {
                $this->SetBalanceNA();
            } elseif (
                ($canvasOverlay = $selenium->waitForElement(WebDriverBy::xpath('//div[@class = "canvas-overlay"]'), 0))
                && $canvasOverlay->getText() == ""
//                in_array($this->AccountFields['Login'], [
//                    'david@levinecentral.com',
//                    'martin@mjwassociates.com',
//                    'vbentley01@gmail.com',
//                    'chabria.manish@gmail.com',
//                    'sam@thehis.com',
//                    'angesuh2000@gmail.com',
//                    'sammysweets21@yahoo.com',
//                ])
            ) {
                $this->SetBalanceNA();
            } elseif ($message = $this->http->FindSingleNode('//h2[contains(text(), "MGM Rewards account balances are currently unavailable")]')) {
                $this->SetWarning($message);

                return;
            }
        }

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Something went wrong.")]'), 0)
        ) {
            throw new CheckRetryNeededException(2, 5, 'Something went wrong.');
        }

        // in comps + 0 Pending
        $this->SetProperty('PendingPoints', $this->http->FindSingleNode('//div[button[contains(.,"MGM Rewards Points")]]/following-sibling::div/p[contains(text(),"Pending")]', null, true, "/\+\s*(.+)\s+Pending/"));
        // Status - Tier Level
        $this->SetProperty("Status", $this->http->FindSingleNode('(//button[@data-testid="card-container"]/div/span)[1]',
            null, false, '/^(\w{3,15})$/'));
        // NEXT TIER 15,712 to advance to Pearl
        //$this->SetProperty("CreditsToNextTier", $this->http->FindSingleNode("(//span[contains(@class, 'credits-to-go') and contains(text(), 'advance')])[1]", null, true, "/(.+)\s+to advance/"));
        // 68,237 to maintain Gold
        $this->SetProperty('CreditsToMaintainTier', $this->http->FindSingleNode("//div[contains(text(),' to maintain ')]", null, true, "/^(.+?)\s+to maintain/"));

        // refs #22673
        if (is_numeric($statusPoints = str_replace(',', '', $this->http->FindSingleNode('//p[contains(text(),"Tier Credits")]/preceding-sibling::p')))) {
            $this->SetProperty('TierPoints', $statusPoints);
        }

        // Account - Account number
        $this->SetProperty("Account", $this->http->FindSingleNode('//button[@data-testid="card-container"]/following-sibling::p[contains(text(),"#")]',
            null, false, '/#\s*(.+)/'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h6[contains(text(), 'Welcome, ')]", null, true, "/,\s*([^!]+)/")));
        // Holiday Gift Points
        $balance = $selenium->waitForElement(WebDriverBy::xpath('//div[button[contains(.,"Holiday Gift Points")]]/following-sibling::div/p[1]'),
            0);
        if ($balance) {
            $this->AddSubAccount([
                'Code' => 'mirageHGSPoints',
                'DisplayName' => 'Holiday Gift Points',
                'Balance' => $this->http->FindPreg("/([^.]+)/", false, $balance->getText()),
            ]);
        } else {
            $this->logger->error('Not found "Holiday Gift Points" balance');
        }
        // FREEPLAY® (Total)
        $freePlay = $selenium->waitForElement(WebDriverBy::xpath('//div[button[contains(.,"FREEPLAY®")]]/following-sibling::div/p[1]'), 0)->getText();
        $this->SetProperty("FREEPLAY", $freePlay);
        // SLOT DOLLARS
        $balance = $selenium->waitForElement(WebDriverBy::xpath('//div[button[contains(.,"SLOT DOLLARS®")]]/following-sibling::div/p[1]'), 0);

        if ($balance) {
            $this->AddSubAccount([
                'Code'        => 'mirageSlotDollars',
                'DisplayName' => 'Slot Dollars',
                'Balance'     => $this->http->FindPreg('/\$(.+?)/', false, $balance->getText()),
            ]);
        } else {
            $this->logger->error('Not found "slot dollars balance"');
        }
        $this->savePageToLogs($selenium);

        $exp = $this->http->FindSingleNode('//div[contains(text(), "Credits Expire:")]', null, true, "/: (.+)/"); //todo: not found after program changes
        $this->logger->debug("[Exp date]: {$exp}");

        if ($exp && !in_array($exp, ['null', 'undefined']) && $this->Properties['Status'] == 'Sapphire') {
            $this->SetExpirationDate(strtotime($exp));
        } else if (in_array($this->Properties['Status'], ['Pearl', 'Gold', 'Platinum', 'NOIR'])) {
            $this->SetExpirationDateNever();
            $this->SetProperty('AccountExpirationWarning', 'do not expire with elite status');
            $this->ClearExpirationDate();
        } elseif($this->Properties['Status'] !== 'Sapphire') $this->sendNotification('new status ' . $this->Properties['Status']);
    }

    public function Parse()
    {
        return;
//        ## Security Question
//        if ($this->http->FindSingleNode("//p[contains(text(), 'Please select a secret question and provide an answer below')]"))
//            throw new CheckException("MGM Resorts (M life Players Club) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR); /*checked*/

        $response = $this->http->JsonLog(null, 0, true);
        // Balance - Tier Credits
        $this->SetBalance(ArrayVal($response['response'], 'tierCredits'));
        // Name
        $this->SetProperty("Name", beautifulName(ArrayVal($response['response'], 'title') . " " . ArrayVal($response['response'], 'firstName') . " " . ArrayVal($response['response'], 'lastName')));
        // Account
        $this->SetProperty("Account", ArrayVal($response['response'], 'mlifeNo'));
        // Status
        $this->SetProperty("Status", ArrayVal($response['response'], 'tier'));

        $balanceInfos = ArrayVal($response['response'], 'balanceInfos', []);

        foreach ($balanceInfos as $balanceInfo) {
            switch ($balanceInfo['balanceType']) {
                case 'ExpressComps':
                    // Express Comps
                    $this->SetProperty("ExpressCopms", ArrayVal($balanceInfo['balanceAmount'], 'curr') . ArrayVal($balanceInfo['balanceAmount'], 'note') . ArrayVal($balanceInfo['balanceAmount'], 'delim') . ArrayVal($balanceInfo['balanceAmount'], 'cent'));

                    break;

                case 'HGSPoints':
                    // HGS POINTS - HOLIDAY GIFT SHOPPE POINTS
                    $this->SetProperty("HGSPoints", ArrayVal($balanceInfo['balanceAmount'], 'value'));

                    break;

                case 'FreePlay':
                    // FREEPLAY® (Total)
                    $this->SetProperty("FREEPLAY", ArrayVal($balanceInfo['balanceAmount'], 'curr') . ArrayVal($balanceInfo['balanceAmount'], 'note') . ArrayVal($balanceInfo['balanceAmount'], 'delim') . ArrayVal($balanceInfo['balanceAmount'], 'cent'));

                    break;

                case 'PointPlay':
                    // POINTPlay
                    $this->SetProperty("POINTPlay", ArrayVal($balanceInfo['balanceAmount'], 'value'));

                    break;

                default:
                    $this->logger->notice("Unknown reward type: {$balanceInfo['balanceType']}");
            }// switch ($balanceInfo['balanceType']) {
        }// foreach ($balanceInfos as $balanceInfo)

//        $this->http->GetURL("https://www.mgmresorts.com/mgm-web/authentication/en/v1/getauthuser.sjson?propertyId=mgmresorts&_=".time().date("B"));
//        $response = $this->http->JsonLog(null, 3, true);

        // Expiration Date  // refs #9416
        /*
        $exp = strtotime("30 Sep");

        if ($exp < time()) {
            $exp = $exp = strtotime("+1 year", $exp);
        }
        $this->SetExpirationDate($exp);
        */

        // Credits to Next Tier - refs#16364
        $this->http->GetURL('https://www.mgmresorts.com/en/sign-in/profile.html#/?section=https://www.mgmresorts.com/en/sign-in/profile.html#/?section=summary');
        $tierData = $this->http->JsonLog($this->http->FindPreg('/accounts:\{tierData:(\{.+?\})\},/s'), 3, true);
        $currentTier = ArrayVal($tierData, $this->Properties['Status']);

        if ($nextTierLabel = ArrayVal($currentTier, 'nextTier')) {
            $this->logger->debug("nextTier: {$nextTierLabel}");
            $nextTier = ArrayVal($tierData, $nextTierLabel);
            $minCredits = ArrayVal($nextTier, 'minCredits', 0);
            $tierCredits = ArrayVal($response['response'], 'tierCredits');
            $this->logger->debug("MinCredits {$minCredits} - tierCredits {$tierCredits} = " . ($minCredits - $tierCredits));

            if ($minCredits > 0 && ($minCredits - $tierCredits) > 0) {
                $this->SetProperty("CreditsToNextTier",
                    $minCredits - $tierCredits);
            }
        }
    }

    public function ParseItineraries()
    {
        if ($this->ParsePastIts) {
            $this->parseTypeItineraries($this->pastItinsData);
        }

        $noUpcoming = (bool) $this->http->FindPreg('/"trips":\[\]/', false, $this->futureItinsData);
        $this->logger->info("noUpcoming = {$noUpcoming}");

        if ($noUpcoming) {
            if (empty($this->itinerariesMaster->getItineraries())) {
                return $this->noItinerariesArr();
            } else {
                return [];
            }
        }

        //$this->parseAnyItineraries($this->futureItinsData);
        $this->parseTypeItineraries($this->futureItinsData);

        return [];
    }

    public function parseItinerary($itinerary)
    {
        $this->logger->notice(__METHOD__);
        $h = $this->itinerariesMaster->add()->hotel();

        $traveler = trim(beautifulName(ArrayVal($itinerary['customer'], 'firstName') . " " . ArrayVal($itinerary['customer'], 'lastName')));

        if (!empty($traveler)) {
            $h->general()->traveller($traveler, true);
        }

        $h->general()
            ->confirmation(ArrayVal($itinerary, 'confirmationNumber'), "Confirmation Number", true)
            ->status(ArrayVal($itinerary, 'reservationState'));

        if (in_array($h->getStatus(), ['Canceled', 'Cancelled'])) {
            $h->general()->cancelled();
        }

        $currency = ArrayVal(ArrayVal($itinerary, 'totalBasePrice', null), 'curr', null) ?? ArrayVal(ArrayVal($itinerary, 'totalReservationAmount'), 'curr', null);

        if ($currency == '$') {
            $currency = 'USD';
        }

        $h->price()
            ->currency($currency, false, true)
            ->total(PriceHelper::cost(ArrayVal($itinerary['totalReservationAmount'], 'note') . "." . ArrayVal($itinerary['totalReservationAmount'], 'cent')), false, true)
            ->discount(PriceHelper::cost(ArrayVal($itinerary['totalDiscount'], 'note') . "." . ArrayVal($itinerary['totalDiscount'], 'cent')), false, true)
            ->cost(PriceHelper::cost(ArrayVal(ArrayVal($itinerary, 'totalBasePrice', null), 'note') . "." . ArrayVal(ArrayVal($itinerary, 'totalBasePrice', null), 'cent')), false, true);
        // Taxes
        $taxes = ArrayVal($itinerary['taxes'], 'note') . "." . ArrayVal($itinerary['taxes'], 'cent');

        if ($taxes) {
            $h->price()->tax(PriceHelper::cost($taxes));
        }

        $hotelAddress = ArrayVal($itinerary, 'propAddress', null);

        if (empty($hotelAddress)) {
            $hotelAddress = ArrayVal($itinerary['roomDetail'], 'propAddress', null);
        }

        if (empty($hotelAddress) && ($detailsURL = ArrayVal($itinerary['roomDetail'], 'detailPageUrl'))) {
            $http2 = clone $this->http;
            $this->http->brotherBrowser($http2);
            $http2->NormalizeURL($detailsURL);
            $http2->GetURL($detailsURL);
            $hotelAddress = $http2->FindSingleNode("//span[contains(@class, 'address-text')]");
        }// if ($detailsURL)
        // get address from google maps
        if (empty($hotelAddress) && ($detailsURL = ArrayVal($itinerary['roomDetail'], 'propertyDirectionUrl'))) {
            $http2 = clone $this->http;
            $this->http->brotherBrowser($http2);
            $http2->NormalizeURL($detailsURL);
            $http2->GetURL($detailsURL);
            $hotelAddress = $http2->FindSingleNode("//meta[@itemprop = 'description']/@content");

            if (strstr($hotelAddress, 'view maps and get driving directions')) {
                $this->logger->debug('Bad address "' . $hotelAddress . '"');
                $hotelAddress = urldecode($http2->FindPreg("/dir\/\/([^\/]+)/"));
                $hotelAddress = str_replace("+", ' ', $hotelAddress);
                $hotelAddress = str_replace("%26", ' ', $hotelAddress);
                $hotelAddress = Html::cleanXMLValue($hotelAddress);
            }// if (strstr($eventAddress, 'view maps and get driving directions'))

            if ($this->http->FindPreg('/^[★☆]+/u', false, $hotelAddress) || !$hotelAddress) {
                $this->logger->debug('Bad address "' . $hotelAddress . '"');
                $hotelAddress = urldecode($http2->FindPreg('/http:\/\/www.google.com\/search\?q\\\\\\\\u003d(.+?)\\\\\\\\u0026/u'));
                $hotelAddress = str_replace("+", ' ', $hotelAddress);
                $hotelAddress = str_replace("%26", ' ', $hotelAddress);
                $hotelAddress = Html::cleanXMLValue($hotelAddress);
            }
        }// if (empty($hotelAddress) && $detailsURL = ArrayVal($itinerary['roomDetail'], 'propertyDirectionUrl'))

        $h->hotel()
            ->name(ArrayVal($itinerary, 'propName', null) ?? ArrayVal($itinerary['roomDetail'], 'propertyName'))
            ->address($hotelAddress)
            ->phone(ArrayVal($itinerary['roomDetail'], 'phoneCallCenter', null), true, true);

        $h->booked()
            ->checkIn2(ArrayVal($itinerary['tripDetails'], 'checkInDate'))
            ->checkOut2(ArrayVal($itinerary['tripDetails'], 'checkOutDate'))
            ->kids(ArrayVal($itinerary['tripDetails'], 'numChildren'))
            ->guests(ArrayVal($itinerary['tripDetails'], 'numAdults'))
            ->rooms(ArrayVal($itinerary, 'numRooms'));

        $rooms = $this->http->XPath->query('//div[contains(@class, "tile-original-booking-")]/div');
        $this->logger->debug("Total {$rooms->length} rooms were found");

        foreach ($rooms as $room) {
            $h->addRoom()
                ->setRate($this->http->FindSingleNode(".//div[span[contains(text(), 'Avg. / Night')]]", $room), false, true)
                ->setRateType($this->http->FindSingleNode('.//a[contains(text(), "Cancellation Policy")]/preceding-sibling::h4', $room))
                ->setType($this->http->FindSingleNode('.//li[contains(text(), "Guaranteed:")]/following-sibling::li[1]', $room), false, true)
                ->setDescription($this->http->FindSingleNode('.//span[contains(text(), "Room Details")]/preceding-sibling::h2/span', $room));
        }// foreach ($rooms as $room)

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function parseTypeItineraries($its)
    {
        $its = $this->http->JsonLog($its);
        $trips = $its->data->me->trips ?? [];

        foreach ($trips as $trip) {
            foreach ($trip->productsMetadata as $metadata) {
                $confNo = $metadata->confirmationNumber;
                $headers = [
                    "Accept"                    => "*/*",
                    "Content-Type"              => "application/json",
                    "Origin"                    => "https://www.mgmresorts.com",
                    "Referer"                   => "https://www.mgmresorts.com",
                    "apollographql-client-name" => "mfe-trips",
                    "Authorization"             => "Bearer {$this->accessToken}",
                    "x-mgm-apollo-client"       => "mfe-trips",
                    "x-mgm-channel"             => "web",
                    "x-mgm-correlation-id"      => "sNEMbxbTOB6ghq3dm3Ejs|dYl9vw5sKVQfm_yWomMtK",
                    "x-mgm-source"              => "mgmri",
                ];
                $data = '{"operationName":"RoomReservation","variables":{"confirmationNumber":"' . $confNo . '","reservationType":"ROOM"},"query":"query RoomReservation($confirmationNumber: String!, $reservationType: BookingReservationType!, $firstName: String, $lastName: String) {\n  bookingReservation(\n    confirmationNumber: $confirmationNumber\n    reservationType: $reservationType\n    firstName: $firstName\n    lastName: $lastName\n  ) {\n    roomExtensionVariables {\n      amountDue\n      operaState\n      roomTypeDetails {\n        name\n        __typename\n      }\n      bookings {\n        date\n        basePrice\n        price\n        isComp\n        __typename\n      }\n      ratesSummary {\n        roomSubtotal\n        discountedSubtotal\n        programDiscount\n        adjustedRoomSubtotal\n        roomChargeTax\n        resortFeeAndTax\n        resortFeePerNight\n        occupancyFee\n        tourismFeeAndTax\n        casinoSurchargeAndTax\n        reservationTotal\n        balanceUponCheckIn\n        __typename\n      }\n      purchasedComponents {\n        shortDescription\n        tripPrice\n        active\n        code\n        __typename\n      }\n      programDetails {\n        name\n        id\n        descriptions {\n          long\n          short\n          __typename\n        }\n        images {\n          overview {\n            url\n            __typename\n          }\n          __typename\n        }\n        phoenixTags\n        __typename\n      }\n      chargesAndTaxes {\n        charges {\n          itemized {\n            item\n            amount\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    numGuests\n    confirmationNumber\n    propertyDetails {\n      images {\n        overview {\n          url\n          __typename\n        }\n        __typename\n      }\n      name\n      shortName\n      checkinTime\n      checkoutTime\n      timezone\n      detailPageCorpURL\n      garageInfo\n      location {\n        address\n        __typename\n      }\n      phoneNumber {\n        generalNumber\n        reservationsNumber\n        frontDeskNumber\n        __typename\n      }\n      rideShare {\n        pickupLocation\n        __typename\n      }\n      resortFee {\n        description\n        __typename\n      }\n      __typename\n    }\n    startsAt\n    endsAt\n    hdePackage\n    f1Package\n    __typename\n  }\n}\n"}';
                $this->http->RetryCount = 0;
                $this->http->PostURL('https://api.mgmresorts.com/graphql-next', $data, $headers);

                if (strstr($this->http->Error, 'Network error 92')) {
                    $this->http->SetProxy($this->proxyReCaptcha());
                    $this->http->PostURL('https://api.mgmresorts.com/graphql-next', $data, $headers);

                    if (strstr($this->http->Error, 'Network error 92')) {
                        $this->sendNotification('need a repeat // MI');
                    }
                }
                $this->http->RetryCount = 2;
                $response = $this->http->JsonLog();

                if ($this->http->FindPreg('/\{"errors":\[\{"message":"<_reservation_not_found>/')) {
                    $this->logger->error('Reservation not found');

                    continue;
                }

                if (!isset($response->data->bookingReservation)) {
                    $this->logger->error('Something went wrong');

                    continue;
                }
                $this->parseHotel($metadata, $response);
            }
        }
    }

    private function parseHotel($metadata, $data)
    {
        $this->logger->notice(__METHOD__);
        $booking = $data->data->bookingReservation;
        $this->logger->info("Parse Itinerary #{$booking->confirmationNumber}", ['Header' => 3]);

        $h = $this->itinerariesMaster->add()->hotel();

        if (!is_null($booking->confirmationNumber)) {
            $h->general()->confirmation($booking->confirmationNumber, 'Reservation Number', true);
        } elseif (isset($metadata->confirmationNumber)) {
            $h->general()->confirmation($metadata->confirmationNumber, '', true);
        } else {
            $h->general()->noConfirmation();
        }

        $h->hotel()->name($booking->propertyDetails->name)->address($booking->propertyDetails->location->address);

        $h->booked()->guests($booking->numGuests);

        if (isset($booking->roomExtensionVariables->ratesSummary->roomSubtotal) && $booking->roomExtensionVariables->ratesSummary->roomSubtotal > 0) {
            $h->price()->total($booking->roomExtensionVariables->ratesSummary->roomSubtotal);
        }

        if (isset($booking->roomExtensionVariables->roomTypeDetails->name)) {
            $r = $h->addRoom();
            $r->setDescription($booking->roomExtensionVariables->roomTypeDetails->name);
            $r->setRate($booking->roomExtensionVariables->ratesSummary->adjustedRoomSubtotal);
        }

        if (isset($metadata->status) && $metadata->status == 'CANCELLED') {
            $h->general()->status(beautifulName($metadata->status));
            $h->general()->cancelled();
        } else {
            $h->booked()->checkIn2("$booking->startsAt {$booking->propertyDetails->checkinTime}")
                ->checkOut2("$booking->endsAt {$booking->propertyDetails->checkoutTime}");
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function loadItineraries($selenium)
    {
        $this->logger->notice(__METHOD__);

        try {
            $selenium->http->GetURL('https://www.mgmresorts.com/account/trips/');
        } catch (TimeoutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $selenium->driver->executeScript('window.stop();');
        }

        $selenium->waitForElement(WebDriverBy::xpath("//li/div[contains(text(),'Upcoming')]"), 7);
        /* if (!$selenium->waitForElement(WebDriverBy::xpath($xpath = '//div[contains(@class, "reservations__upcoming--group")] | //span[contains(text(), "No upcoming reservation")]'), 15)) {
             $selenium->http->GetURL('https://www.mgmresorts.com/account/trips/');
             $selenium->waitForElement(WebDriverBy::xpath($xpath), 10);
         }*/
        $selenium->SaveResponse();

        $xhr = "var xhr = new XMLHttpRequest();
        xhr.open('POST', 'https://www.mgmresorts.com/mgm-web/itinerary/en/v1/all/upcoming.sjson');
        
        xhr.setRequestHeader('Accept', 'application/json, text/plain, */*');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onload = function() {
            localStorage.setItem('response', xhr.responseText);
        }
        xhr.onerror = function() { 
            localStorage.setItem('response', xhr.responseText);
        };
        
        xhr.send('propertyId=mgmresorts');";

        /* $.ajax({
                     url: 'https://www.mgmresorts.com/mgm-web/itinerary/en/v1/all/upcoming.sjson',
                     data: {'propertyId': 'mgmresorts'},
                     type: 'POST',
                     beforeSend: function(request) {
         request.setRequestHeader('Accept', 'application/json, text/plain, * / *');
    },
                     success: function(data) {
         localStorage.setItem('response', JSON.stringify(data));
    }
                 }).fail(function(jqXHR, textStatus, error) {
         localStorage.setItem('response', textStatus);
    });*/

        try {
            $selenium->driver->executeScript($xhr);
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage());
            $selenium->driver->executeScript($xhr);
        }
        sleep(3);
        $retry = 0;

        do {
            $this->logger->debug('retry load ' . $retry);
            sleep(3);
            $response = $selenium->driver->executeScript("return localStorage.getItem('response');");
            $retry++;
        } while ($retry < 10 && empty($response));
        $this->logger->debug('=response:');
        $this->logger->debug($response);

        return $response;
    }

    private function loadItinerariesNew($selenium, $type = 'FUTURE')
    {
        $this->logger->notice(__METHOD__);

        try {
            $selenium->http->GetURL('https://www.mgmresorts.com/account/trips/');
        } catch (TimeoutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $selenium->driver->executeScript('window.stop();');
        }

        $selenium->waitForElement(WebDriverBy::xpath("//li/span[contains(text(),'Upcoming')]"), 7);
        /* if (!$selenium->waitForElement(WebDriverBy::xpath($xpath = '//div[contains(@class, "reservations__upcoming--group")] | //span[contains(text(), "No upcoming reservation")]'), 15)) {
             $selenium->http->GetURL('https://www.mgmresorts.com/account/trips/');
             $selenium->waitForElement(WebDriverBy::xpath($xpath), 10);
         }*/
        $selenium->SaveResponse();
        $js = 'let storage = JSON.parse(localStorage.getItem("mgm_token-storage"));
        return storage.accessToken.accessToken';

        try {
            $this->accessToken = $selenium->driver->executeScript($js);
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage());
        }

        $filter = '{"startDate":"' . date('Y-m-d') . '","endDate":"' . date('Y-m-d', strtotime('+2 year')) . '","queryType":"FUTURE"}';

        if ($type != 'FUTURE') {
            $filter = '{"startDate":"' . date('Y-m-d', strtotime('-1 months')) . '","endDate":"' . date('Y-m-d') . '","queryType":"PAST"}';
        }

        $xhr = '
        let storage = JSON.parse(localStorage.getItem("mgm_token-storage"));
        fetch("https://api.mgmresorts.com/graphql-next", {
            method: "POST",
            headers: {
                "Accept": "*/*",
                "Content-Type": "application/json",
                "Origin": "https://www.mgmresorts.com",
                "Referer": "https://www.mgmresorts.com",
                "apollographql-client-name": "mfe-trips",
                "authorization": "Bearer " + storage.accessToken.accessToken,
                "x-mgm-apollo-client": "mfe-trips",
                "x-mgm-channel": "web",
                "x-mgm-correlation-id": "fXNU-CxeUoAVI4Y-B29VU|b5064122-8476-47e9-a236-fa7bbd20e2ee",
                "x-mgm-source": "mgmri",
             },
            body: \'{"operationName":"CustomerTrips","variables":{"tripSearchFilter":' . $filter . '},"query":"query CustomerTrips($tripSearchFilter: CustomerTripSearchFilter) {  me {    trips(tripSearchFilter: $tripSearchFilter) {      tripId      tripShareId      location      startDate      productsMetadata {        bookingDomain        confirmationNumber        status        hidden        numGuests        propertyDetails {          name          images {            overview {              url              metadata {                description                __typename              }              __typename            }            __typename          }          __typename        }        product {          __typename          ... on RoomProductDetails {            checkInDate            checkOutDate            roomDetails {              name              property {                name                timezone                __typename              }              __typename            }            __typename          }          ... on DiningProductDetails {            restaurantId            reservationDateTime            restaurantDetails {              name              property {                name                timezone                __typename              }              images {                overview {                  url                  metadata {                    description                    __typename                  }                  __typename                }                __typename              }              __typename            }            __typename          }          ... on ShowProductDetails {            showId            showEventId            eventDateTime            showDetails {              name              property {                name                timezone                __typename              }              images {                overview {                  url                  metadata {                    description                    __typename                  }                  __typename                }                __typename              }              __typename            }            __typename          }          ... on FreebieProductDetails {            reservationEndDateTime            reservationStartDateTime            freebieDetails {              message              moreInfo              timings              __typename            }            freebieActivityId            __typename          }        }        __typename      }      __typename    }    sharedTrips(tripSearchFilter: $tripSearchFilter) {      tripName      tripShareId      location      startDate      endDate      productsMetadata {        bookingDomain        sharedConfirmationNumber        propertyId        propertyDetails {          propertyId          name          images {            overview {              url              __typename            }            __typename          }          __typename        }        hidden        product {          __typename          ... on SharedRoomProductDetails {            checkInDate            roomTypeId            checkInDate            checkOutDate            roomDetails {              id              name              __typename            }            __typename          }          ... on SharedDiningProductDetails {            restaurantId            reservationDateTime            restaurantDetails {              id              name              images {                overview {                  url                  __typename                }                __typename              }              __typename            }            __typename          }          ... on SharedShowProductDetails {            showId            showEventId            eventDateTime            showDetails {              id              name              images {                overview {                  url                  __typename                }                __typename              }              __typename            }            showEventDetails {              id              eventDate              __typename            }            __typename          }          ... on SharedFreebieProductDetails {            freebieActivityId            reservationStartDateTime            reservationEndDateTime            freebieDetails {              message              moreInfo              timings              __typename            }            __typename          }        }        __typename      }      __typename    }    __typename  }}"}\'
        }).then(
            response => response.text()  
        ).then(
            text => localStorage.setItem("response", text)
        );
        ';
        $this->logger->debug(var_export($xhr, true));

        /* $.ajax({
                     url: 'https://www.mgmresorts.com/mgm-web/itinerary/en/v1/all/upcoming.sjson',
                     data: {'propertyId': 'mgmresorts'},
                     type: 'POST',
                     beforeSend: function(request) {
         request.setRequestHeader('Accept', 'application/json, text/plain, * / *');
    },
                     success: function(data) {
         localStorage.setItem('response', JSON.stringify(data));
    }
                 }).fail(function(jqXHR, textStatus, error) {
         localStorage.setItem('response', textStatus);
    });*/

        try {
            $selenium->driver->executeScript($xhr);
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage());
            $selenium->driver->executeScript($xhr);
        }
        sleep(3);
        $retry = 0;

        do {
            $this->logger->debug('retry load ' . $retry);
            sleep(3);
            $response = $selenium->driver->executeScript("return localStorage.getItem('response');");
            $retry++;
        } while ($retry < 10 && empty($response));
        $this->logger->debug('=response:');
        $this->logger->debug($response);

        return $response;
    }

    private function loadPastItineraries($selenium)
    {
        $this->logger->notice(__METHOD__);
        $selenium->http->GetURL('https://www.mgmresorts.com/en/sign-in/profile.html#/itinerary/completed');
        $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "reservations__completed")]//div[contains(@class, "reservation__tile")] | //span[contains(text(), "No completed reservation")]'), 15);
        $selenium->SaveResponse();
        $selenium->driver->executeScript("var jq = document.createElement('script');
        jq.src = 'https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js';
        document.getElementsByTagName('head')[0].appendChild(jq);");
        sleep(4);
        $dateRangeStart = date('m/d/Y', strtotime('now'));
        $dateRangeEnd = date('m/d/Y', strtotime('-1 year', strtotime('now')));
        $selenium->driver->executeScript("
            $.ajax({
                url: 'https://www.mgmresorts.com/mgm-web/itinerary/en/v1/all/completed.sjson',
                data: {
                    'propertyId': 'mgmresorts',
                    'dateRangeStart': '$dateRangeStart',
                    'dateRangeEnd': '$dateRangeEnd'
                },
                type: 'POST',
                beforeSend: function(request) {
                    request.setRequestHeader('Accept', 'application/json, text/plain, */*');
                },
                success: function(data) {
                    localStorage.setItem('response', JSON.stringify(data));
                }
            }).fail(function(jqXHR, textStatus, error) {
                localStorage.setItem('response', textStatus);
            });
        ");
        sleep(6);
        $response = $selenium->driver->executeScript("return localStorage.getItem('response');");
        $this->logger->info('=response:');
        $this->logger->info($response);

        return $response;
    }

    private function parseAnyItineraries($itins)
    {
        $response = $this->http->JsonLog($itins, 3, true);

        $response = ArrayVal($response, 'response', []);
        $itineraryList = ArrayVal($response, 'itinerary', []);
        $itineraries = ArrayVal($itineraryList, 'upcoming', []) ?: ArrayVal($itineraryList, 'completed', []);

        foreach ($itineraries as $itinerary) {
            // conf #
            $confNo = ArrayVal($itinerary, 'confirmationNumber');
            $this->logger->info('Parse Itinerary #' . $confNo, ['Header' => 3]);
            // json
            if (!isset($itinerary['type'])) {
                $this->logger->debug("mirage. Wrong Itinerary?");
                $this->sendNotification("mirage. Wrong Itinerary?");

                continue;
            }// if (!isset($itinerary['type']))

            $this->logger->debug("Itinerary type {$itinerary['type']}");

            switch ($itinerary['type']) {
                case 'ROOM':
                    $this->parseItinerary($itinerary);

                    break;

                case 'DINING':
                case 'SHOW':
                    $this->parseEvent($itinerary);

                    break;

                default:
                    $this->sendNotification("mirage. Unknown Itinerary type -> {$itinerary['type']}");

                    break;
            }// switch ($itinerary['type'])
        }// foreach ($itineraries as $itinerary)
    }

    private function parseEvent($itinerary)
    {
        $this->logger->notice(__METHOD__);

        if (strstr(ArrayVal($itinerary, 'error'), 'Page not found:') && empty(ArrayVal($itinerary, 'name',
                null)) && empty(ArrayVal($itinerary, 'shareTitle'))) {
            $this->logger->error('Skip: bug itinerary');

            return;
        }

        $e = $this->itinerariesMaster->add()->event();
        $e->general()
            ->confirmation(strtoupper(ArrayVal($itinerary, 'confirmationNumber')), "Confirmation Number", true)
            ->status(ArrayVal($itinerary, 'reservationState'));

        if (in_array($e->getStatus(), ['Canceled', 'Cancelled'])) {
            $e->general()->cancelled();
        }

        if ($itinerary['type'] === 'SHOW') {
            $e->setEventType(EVENT_SHOW);
        } else {
            $e->setEventType(EVENT_RESTAURANT);
        }

        $currency = ArrayVal(ArrayVal($itinerary, 'totTicketprice'), 'curr', null);

        if ($currency === '$') {
            $currency = 'USD';
        }

        $e->price()
            ->currency($currency, false, true)
            ->total(PriceHelper::cost(ArrayVal(ArrayVal($itinerary, 'totTicketprice', null), 'note', null) . "." . ArrayVal(ArrayVal($itinerary, 'totTicketprice', null), 'cent', null)), false, true);

        // Taxes
        $taxes = ArrayVal(ArrayVal($itinerary, 'entertainmentFee', null), 'note') . "." . ArrayVal(ArrayVal($itinerary, 'entertainmentFee', null), 'cent');

        if ($taxes !== '.') {
            $e->price()->tax(PriceHelper::cost($taxes));
        }

        // get address from google maps
        $eventAddress = ArrayVal($itinerary, 'propAddress', null);

        if (!$eventAddress) {
            if ($detailsURL = ArrayVal($itinerary, 'propertyDirectionUrl')) {
                $http2 = clone $this->http;
                $this->http->brotherBrowser($http2);
                $http2->NormalizeURL($detailsURL);
                $http2->GetURL($detailsURL);
                $eventAddress = $http2->FindSingleNode("//meta[@itemprop = 'description']/@content");

                if (strpos($eventAddress, 'view maps and get driving directions') !== false) {
                    $this->logger->debug('Bad address "' . $eventAddress . '"');
                    $eventAddress = urldecode($http2->FindPreg("/dir\/\/([^\/]+)/"));
                    $eventAddress = str_replace(["+", "%26"], [' ', ' '], $eventAddress);
                    $eventAddress = Html::cleanXMLValue($eventAddress);
                } // if (strstr($eventAddress, 'view maps and get driving directions'))
            }// if ($detailsURL = ArrayVal($itinerary, 'propertyDirectionUrl'))
        }
        $e->place()
            ->name(ArrayVal($itinerary, 'name', null) ?? ArrayVal($itinerary, 'shareTitle'))
            ->address($eventAddress)
            ->phone(ArrayVal($itinerary, 'phoneCallCenter', null), true, true);

        // StartDate
        $startDate = null;
        $date = ArrayVal($itinerary, 'date');
        $time = ArrayVal($itinerary, 'timeStr') ?: ArrayVal($itinerary, 'time');

        if ($date && $time) {
            $startDate = strtotime("$date, $time");
        }

        if (!$startDate) {
            $date = ArrayVal($itinerary, 'displayDate');
            $time = ArrayVal($itinerary, 'displayTime');

            if ($date && $time) {
                $startDate = strtotime("$date, $time");
            }
        }
        $e->booked()->start($startDate);
        // ReservationDate
        $bookDate = ArrayVal($itinerary, 'bookDate');

        if ($bookDate) {
            $e->setReservationDate(strtotime($bookDate));
        }

        $guests = ArrayVal($itinerary, 'numAdults');

        if (!$guests && count(ArrayVal($itinerary, 'tickets'))) {
            $guests = count(ArrayVal($itinerary, 'tickets'));
        }
        $e->booked()
            ->noEnd()
            ->guests($guests);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($e->toArray(), true), ['pre' => true]);
    }

    private function base64url_encode($plainText)
    {
        $this->logger->notice(__METHOD__);

        $base64 = base64_encode($plainText);
        $base64 = trim($base64, "=");
        $base64url = strtr($base64, '+/', '-_');

        return $base64url;
    }
}
