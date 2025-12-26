<?php

use AwardWallet\Engine\jetblue\QuestionAnalyzer;
use AwardWallet\Engine\ProxyList;
use Facebook\WebDriver\Exception\InvalidSessionIdException;
use Facebook\WebDriver\Exception\UnknownErrorException;
use AwardWallet\Engine\Settings;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;

class TAccountCheckerJetblueSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    private const WAIT_TIMEOUT = 10;

    private TAccountCheckerJetblue $jetblue;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->http->FilterHTML = false;
        $this->http->saveScreenshots = true;

        if ($this->attempt == 10) {
            $this->logger->notice("no Proxy");
        } elseif ($this->attempt == 2) {
            $this->http->SetProxy($this->proxyReCaptchaIt7());
        } else {
            $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));
        }

        if ($this->attempt == 2) {
            $this->useFirefoxPlaywright();
            $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
            $this->seleniumOptions->addHideSeleniumExtension = false;
            $this->seleniumOptions->userAgent = null;
        } else {
            $this->useFirefox(SeleniumFinderRequest::FIREFOX_100);
            $this->setKeepProfile(true);

            $request = FingerprintRequest::firefox();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 3;
            $request->platform = 'Linux x86_64';
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            $this->usePacFile(false);
            $this->seleniumOptions->addHideSeleniumExtension = false;

            if (
                $fingerprint !== null
                && (!isset($this->State['UserAgent']) || $this->attempt > 0)
            ) {
                $this->logger->info("selected fingerprint {$fingerprint->getId()}, {{$fingerprint->getBrowserFamily()}}:{{$fingerprint->getBrowserVersion()}}, {{$fingerprint->getPlatform()}}, {$fingerprint->getUseragent()}");
                $this->State['Fingerprint'] = $fingerprint->getFingerprint();
                $this->State['Resolution'] = [$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()];
                $this->State['UserAgent'] = $fingerprint->getUseragent();
            }
        }

        if ($this->attempt == 2) {
            $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        } else {
            if (isset($this->State['UserAgent'])) {
                $this->http->setUserAgent($this->State['UserAgent']);
            }
        }
    }

    public function IsLoggedIn()
    {
        $this->driver->manage()->window()->maximize();
        $this->http->GetURL('https://trueblue.jetblue.com/my-dashboard');

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        if (strlen($this->AccountFields['Pass']) < 8) {
            throw new CheckException('Please enter a valid password.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
        $this->http->GetURL("https://www.jetblue.com/signin?returnUrl=https:%2F%2Fwww.jetblue.com%2F");

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), self::WAIT_TIMEOUT);
        $this->closePopups();
        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 0);
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@name="password"]'), 0);
        $submit = $this->waitForElement(WebDriverBy::xpath('//input[@id="okta-signin-submit"]'), 0);
        $this->saveResponse();

        if (!$login || !$password || !$submit) {
            $this->logger->error("Failed to find form fields");
            $this->emptyBodyWorkaround();

            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);
        $submit->click();

        return true;
    }

    private function emptyBodyWorkaround()
    {
        $this->logger->notice(__METHOD__);

        // selenium bug workaround
        if (empty($this->http->Response['body'])) {
            throw new CheckRetryNeededException(3, 0);
        }
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //div[contains(@class, "form-error") and contains(@class, "visible")]
            | //span[(@class="user-info__card-number")]
            | //div[@class="mfa-verify"]
            | //form[@data-se="factor-sms"]//a[@data-se="sms-send-code"]
            | //form[@data-se="factor-email"]//input[@type="submit" and not(@value="Verify")]
            | //div[@data-se="okta_email"]/a
            | //div[contains(@class, "points-info") and span[contains(text(), "TrueBlue #")]]//span[not(contains(text(), "TrueBlue #"))]
            | //p[contains(text(), " pts")]
            | //p[contains(text(), "Please complete the following mandatory fields")]
            | //div[contains(@class, "okta-form-infobox-error")]//p
            | //p[@id="invalid-email"]
            | //p[@id="invalid-password"]
        '), self::WAIT_TIMEOUT);
        $this->saveResponse();
//        $this->closePopups();

        if ($this->loginSuccessful(0)) {
            return true;
        }

        $this->processSelectDeviceForm();

        $switch2faTypeButton = $this->waitForElement(WebDriverBy::xpath('//a[@class="mfa-type-selection-link"]'), 0);

        if ($switch2faTypeButton && $this->waitForElement(WebDriverBy::xpath('//form[@data-se="factor-sms"]//a[@data-se="sms-send-code"]'), 0)) { // switching from sms to email
            $this->saveResponse();
            $switch2faTypeButton->click();
        }

        $sendCodeBtn = $this->waitForElement(WebDriverBy::xpath('//form[@data-se="factor-email"]//input[@type="submit" and not(@value="Verify")] | //form[@data-se="factor-sms"]//a[@data-se="sms-send-code"]'), 0);

        if ($sendCodeBtn) {
            $this->saveResponse();
            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            try {
                $sendCodeBtn->click();
            } catch (Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
                sleep(1);
                $sendCodeBtn = $this->waitForElement(WebDriverBy::xpath('//form[@data-se="factor-email"]//input[@type="submit" and not(@value="Verify")] | //form[@data-se="factor-sms"]//a[@data-se="sms-send-code"]'), 0);
                $this->saveResponse();
                $sendCodeBtn->click();
            }
        }

        if ($this->processQuestion()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "okta-form-infobox-error")]//p/text()')) {
            $this->logger->error("[ErrorMessage]: {$message}");

            if (
                strstr($message, "Unable to sign in")
                || strstr($message, 'There was an unsupported response from server')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//p[@id="invalid-email"]/text()')) {
            $this->logger->error("[ErrorMessage]: {$message}");

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//p[@id="invalid-password"]/text()')) {
            $this->logger->error("[ErrorMessage]: {$message}");

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://trueblue.jetblue.com/my-dashboard');
        $this->loginSuccessful();

        $oktaAccessToken = $this->driver->executeScript('return localStorage.getItem("okta-token-storage");');
        $this->logger->debug($oktaAccessToken);

        if (!$oktaAccessToken) {
            $this->loginSuccessful();
            $oktaAccessToken = $this->driver->executeScript('return localStorage.getItem("okta-token-storage");');
            $this->logger->debug($oktaAccessToken);
        }

        $oktaAccessToken = $this->http->JsonLog($oktaAccessToken);

        if (empty($oktaAccessToken->accessToken->accessToken)) {
            $this->logger->error("semothing went wrong");
            $this->DebugInfo = "oktaAccessToken not found";

            throw new CheckRetryNeededException(3, 0);
        }

        $this->State['oktaAccessToken'] = $oktaAccessToken->accessToken->accessToken;

        try {
            $this->http->GetURL('https://travelbank.jetblue.com/tbank/user/main.html');
            $this->waitForElement(WebDriverBy::xpath('//span[@id="accountNumber" and text()] | //input[@formcontrolname="email" or @id = "okta-signin-username" or @id = "username"]'), self::WAIT_TIMEOUT);
            $this->saveResponse();

            if (!$this->http->FindSingleNode('//span[@id="accountNumber" and text()]')) {
                $this->JetBlueTravelBankSelenium(false);
            }

            $this->getJetblue();
            $this->jetblue->Parse();
            $this->SetBalance($this->jetblue->Balance);
            $this->Properties = $this->jetblue->Properties;

            if ($this->ErrorCode != ACCOUNT_CHECKED) {
                $this->ErrorCode = $this->jetblue->ErrorCode;
                $this->ErrorMessage = $this->jetblue->ErrorMessage;
                $this->DebugInfo = $this->jetblue->DebugInfo;
            }
        } catch (
            WebDriverCurlException
            | UnknownErrorException
            | InvalidSessionIdException
            | UnknownServerException $e
        ) { // unknown error: session deleted because of page crash
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 0);
        }

        try {
            $this->http->GetURL('https://managetrips.jetblue.com/dx/B6DX/#/home?tabIndex=1&locale=en-US');
            $this->waitForElement(WebDriverBy::xpath("//h3/span[contains(text(), 'Upcoming trips')]"), self::WAIT_TIMEOUT);
            $this->saveResponse();
            $this->State['itinerariesUrl'] = $this->http->currentUrl();
            $this->logger->notice("Itineraries Url: " . $this->State['itinerariesUrl']);
        } catch (WebDriverCurlException | UnknownErrorException | InvalidSessionIdException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
    }

    public function ParseItineraries()
    {
        try {
            $this->http->GetURL('https://www.jetblue.com/manage-trips');
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
        $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Change or cancel flights, add bags, & more")]'), self::WAIT_TIMEOUT * 2);
        $this->saveResponse();

        $oktaToken = $this->driver->executeScript('return localStorage.getItem("okta-token-storage");');
        $this->logger->debug($oktaToken);
        $oktaToken = $this->http->JsonLog($oktaToken);
        $this->getJetblue();
        $this->jetblue->ParseItineraries($oktaToken);
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "Error 503 backend read error")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        /*
        if ($this->http->FindSingleNode('//p[contains(text(), "Please complete the following mandatory fields")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "Please review the following fields")]')) {
            $this->throwProfileUpdateMessageException();
        }
        */

        if ($msg = $this->http->FindPreg("/You don't have permission to access/")) {
            $this->logger->notice($msg);
            $this->DebugInfo = $msg;
            $this->http->GetURL("https://www.jetblue.com/signin");
        }

        // Planned TrueBlue maintenance
        if (
            $message = $this->http->FindSingleNode("
                //title[contains(text(), 'Planned TrueBlue maintenance')]
                | //h1[contains(text(), 'TrueBlue scheduled upgrade in progress.')]
                | //p[
                        contains(text(), 'jetblue.com is currently undergoing maintenance. Full service to the website should be restored shortly')
                        or contains(text(), 'jetblue.com is currently undergoing maintenance. Full service to the website should be back shortly')
                        or contains(., 'TrueBlue sign in is currently unavailable. You can still manage your upcoming bookings by visiting')
                        or contains(., 'TrueBlue is currently undergoing scheduled maintenance.')
                    ]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Jetblue.com is temporarily unavailable. Full service to the website should be restored shortly. We apologize for the inconvenience.
        if ($this->http->currentUrl() == 'https://ucampaign.usablenet.net/jetblue/Maintenance+R9/') {
            throw new CheckException("Jetblue.com is temporarily unavailable. Full service to the website should be restored shortly. We apologize for the inconvenience. We promise we'll be back soon! Thanks for your patience.", ACCOUNT_PROVIDER_ERROR);
        }
        //# We are currently experiencing technical difficulties
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'We are currently experiencing technical difficulties')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Message: "We are experiencing technical difficulties now"
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'We are experiencing technical difficulties now')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're currently undergoing planned maintenance.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re currently undergoing planned maintenance.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# TrueBlue is currently undergoing routine maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'TrueBlue is currently undergoing routine maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // JetBlue.com is currently undergoing scheduled maintenance
        if ($message = $this->http->FindPreg("/(JetBlue\.com is currently undergoing scheduled maintenance and is temporarily unavailable\. Full service to the website should be restored shortly\. Please check back soon. We apologize for the inconvenience\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're currently experiencing technical issues with booking a flight.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re currently experiencing technical issues with booking a flight.")]')) {
            throw new CheckException("We are currently experiencing technical difficulties. We promise we'll be back soon! Thanks for your patience.", ACCOUNT_PROVIDER_ERROR);
        }
        // JetBlue.com is currently undergoing scheduled maintenance
        if (isset($this->http->Response['code']) && $this->http->Response['code'] == 301 && strstr($this->http->currentUrl(), 'maintenance/default1.aspx')) {
            throw new CheckException("JetBlue.com is currently undergoing scheduled maintenance and is temporarily unavailable. Full service to the website should be restored shortly. Please check back soon. We apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Error 404
        if (isset($this->http->Response['code']) && $this->http->Response['code'] == 404) {
            throw new CheckException('The website is currently unavailable. Please try again later.', ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        //# The parameter is incorrect
        if ($this->http->FindPreg("/(The parameter is incorrect\.)/ims")) {
            throw new CheckException('The website is currently unavailable. Please try again later.', ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        // The service is unavailable.
        if ($message = $this->http->FindPreg("/(The service is unavailable\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Page unavailable
        if ($message = $this->http->FindPreg('#There was an issue with the page you were visiting. Our technicians have been notified and are looking into the problem\.#i')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error - Read
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - Zero size object')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")
            || $this->http->FindSingleNode("//title[contains(text(), '503 Service Temporarily Unavailable')]")
            || ($this->http->currentUrl() == 'https://book.jetblue.com/error/generic/' && isset($this->http->Response['code']) && $this->http->Response['code'] == 302)) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "I acknowledge that I am the parent or legal guardian of the child associated with this TrueBlue account.")]')) {
            $this->throwProfileUpdateMessageException();
        }

        // retries
        if (!$msg
            && (
            $this->http->FindSingleNode("
                //h1[contains(text(), 'This site can’t be reached')]
                | //pre[contains(text(), '403 Forbidden')]
            ")
            || $this->http->FindPreg('/page isn’t working/ims')
            || $this->http->FindPreg('/<(?:span|div|a|pre) id="[^\"]+" style="display: none;"><\/(?:span|div|a|pre)><(?:span|div|a|pre) id="[^\"]+" style="display: none;"><\/(?:span|div|a|pre)><\/body>/ims')
        )) {
            throw new CheckRetryNeededException(3, 0);
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        try {
            $this->logger->debug("Current URL: " . $this->http->currentUrl());
            return $this->processQuestion();
        } catch (WebDriverCurlException | UnknownErrorException | InvalidSessionIdException $e) {
            $this->logger->debug("error: {$e->getMessage()}");

            throw new CheckRetryNeededException(3, 0);
        }
    }

    public function loginSuccessful($timeout = self::WAIT_TIMEOUT * 2)
    {
        $this->logger->notice(__METHOD__);
        $logout = $this->waitForElement(WebDriverBy::xpath('
            //div[contains(@class, "points-info") and span[contains(text(), "TrueBlue #")]]//span[not(contains(text(), "TrueBlue #"))]
            | //p[contains(text(), " pts")]
            | //a[contains(@class, "profile-container")]/p
        '), $timeout);

        if (!$logout) {
            $this->saveResponse();
        }

        if (
            $logout
            || $this->http->FindNodes('
                //div[contains(@class, "points-info") and span[contains(text(), "TrueBlue #")]]//span[not(contains(text(), "TrueBlue #"))]
                | //p[contains(text(), " pts")]
                | //a[contains(@class, "profile-container")]/p
            ')
        ) {
            return true;
        }

        return false;
    }

    public function JetBlueTravelBankSelenium($evaluateMouse)
    {
        $this->logger->info('Travel Bank (Selenium Auth)', ['Header' => 3]);

        try {
            $this->http->GetURL("https://accounts.jetblue.com/oauth2/aus63a5bs52M8z9aE2p7/v1/authorize?prompt=login&response_type=code&nonce=abcdefg&scope=offline_access&idp=0oa6qe03vy6TWGN9o2p7&client_id=0oabozrr37UgDFHS32p7&redirect_uri=https://travelbank.jetblue.com/tbank/okta/oktaServlet.do&state={$this->AccountFields['Login']}");
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("TimeOutException Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
        sleep(2);

        $loginInput =
            $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @id = "username"]'), 15, false)
            ?? $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @id = "username"]'), 0)
        ;
        $this->saveResponse();

        // long form loading
        if (!$loginInput && ($loader = $this->waitForElement(WebDriverBy::xpath('
                //svg[@class = "loader"]
                | //div[@class = "spinner-icon"]
            '), 0))
        ) {
            $this->saveResponse();
            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @id = "username"]'), 10, false);
        }

        $this->logger->debug("find pass");
        // save page to logs
        $this->saveResponse();
        $passwordInput =
            $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="password" or @id = "okta-signin-password" or @id = "password"]'), 0, false)
            ?? $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="password" or @id = "okta-signin-password" or @id = "password"]'), 0)
        ;
        $this->logger->debug("find Sign in btn");
        $button =
            $this->waitForElement(WebDriverBy::xpath('//button[span[contains(., "Sign in") or contains(., "sign in")]] | //input[@id = "okta-signin-submit" or @id = "submit"]'), 0, false)
            ?? $this->waitForElement(WebDriverBy::xpath('//button[span[contains(., "Sign in") or contains(., "sign in")]] | //input[@id = "okta-signin-submit" or @id = "submit"]'), 0)
        ;

        $this->closePopups();
        $this->saveResponse();

        if (!$loginInput && $passwordInput && $button) {
            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @id = "username"]'), 0, false);
        }

        if (!$loginInput || !$passwordInput || !$button) {
            /*
            // TODO: ???
            // too long loading?
            if ($this->waitForElement(WebDriverBy::xpath('//div[@class = "spinner-icon"]'), 0)) {
                $retry = true;
            }
            */

            if ($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Account Summary")]'), 0)) {
                $cookies = $this->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }

                return true;
            }

            if ($this->waitForElement(WebDriverBy::xpath('
                        //h1[
                            contains(text(), "Page not found")
                            or contains(text(), "Error 503 backend read error")
                        ]
                    '), 0)
            ) {
                $this->http->GetURL("https://www.jetblue.com/signin?returnUrl=https:%2F%2Fwww.jetblue.com%2F");

                $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="email" or @id = "okta-signin-username" or @id = "username"]'), 5);
            }
            $this->saveResponse();

            return $this->checkErrors();
        }

        if ($this->seleniumRequest->getBrowser() === SeleniumFinderRequest::BROWSER_FIREFOX) {
            // firefox does not support getting browser logs
            // https://github.com/mozilla/geckodriver/issues/284
            $this->logger->info("setting FF exception handler");

            try {
                // do not work actually, should debug it
                $this->driver->executeScript('
                    var ul = null;
                    function createErrorList() {
                        ul = document.createElement(\'ul\');
                        ul.setAttribute(\'id\', \'js_error_list\');
                        ul.style.display = \'none\';
                        document.body.appendChild(ul);
                    }
                    window.onerror = function(msg){
                        if (ul === null)
                            createErrorList();
                        var li = document.createElement("li");
                        li.appendChild(document.createTextNode(msg));
                        ul.appendChild(li);
                    };
                ');
            } catch (Exception $exception) {
                $this->logger->warning("could not set exception handler: " . substr($exception->getMessage(), 0, 50));
            }
        }

        if ($evaluateMouse) {
            $mover = new MouseMover($this->driver);
            $mover->logger = $this->logger;
            $mover->duration = 100000;
            $mover->steps = 50;
        }

        $this->logger->debug("enter login");

        if ($evaluateMouse) {
            $mover->moveToElement($loginInput);
            $mover->click();
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);
        } else {
            /*
            try {
                $loginInput->sendKeys($this->AccountFields['Login']);
            } catch (StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $this->logger->debug('trying to enter login using js');
                $this->driver->executeScript("document.querySelector('input#username').value = '{$this->AccountFields['Login']}';");
            }
            */
            $this->driver->executeScript("try { document.querySelector('input#username').value = '{$this->AccountFields['Login']}'; } catch (e) {}");
        }

        $this->logger->debug("enter password");

        $this->closePopups();

        if ($evaluateMouse) {
            $mover->moveToElement($passwordInput);
            $this->saveResponse();
            $mover->click();
            $this->saveResponse();
            $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="password" or @id = "okta-signin-password" or @id = "password"]'), 0);

            if (!$passwordInput) {
                return false;
            }
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 10);
        } else {
            $this->saveResponse();
            /*
            try {
                $passwordInput->sendKeys($this->AccountFields['Pass']);
            } catch (StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $this->logger->debug('trying to enter password using js');
                $this->driver->executeScript("document.querySelector('input#password').value = '{$this->AccountFields['Pass']}';");
            }
            */
            $this->driver->executeScript("try { document.querySelector('input#password').value = '".str_replace("'", "\'", $this->AccountFields['Pass'])."'; } catch(e) { }");
        }
        // Sign In
        $this->logger->debug("click 'Sign In'");
        $this->closePopups();
        $this->saveResponse();

        // provider bug fix
        $this->driver->executeScript('
                    let oldXHROpen = window.XMLHttpRequest.prototype.open;
                    window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                        this.addEventListener("load", function() {
                            if (/\/iam\/login/g.exec( url )) {
                                localStorage.setItem("responseData", this.responseText);
                            }
                        });
                        return oldXHROpen.apply(this, arguments);
                    };
                ');

        try {
            $button->click();
        } catch (StaleElementReferenceException | Exception $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->saveResponse();

            $button =
                $this->waitForElement(WebDriverBy::xpath('//button[span[contains(., "Sign in") or contains(., "sign in")]] | //input[@id = "okta-signin-submit" or @id = "submit"]'), 0, false)
                ?? $this->waitForElement(WebDriverBy::xpath('//button[span[contains(., "Sign in") or contains(., "sign in")]] | //input[@id = "okta-signin-submit" or @id = "submit"]'), 0)
            ;

            if ($button) {
                $button->click();
            }
        }

        sleep(3);
        $this->waitForElement(WebDriverBy::xpath('
            //div[@id = "errorBlock"]
            | //span[contains(text(), "Account Summary")]
        '), 10);
        $this->saveResponse();

        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return true;
    }

    public function closePopups()
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->driver->executeScript('var overlay = document.getElementsByClassName(\'truste_overlay\'); if (overlay && typeof (overlay[0]) != \'undefined\') overlay[0].style.display = "none";');
            $this->driver->executeScript('var overlay = document.getElementsByClassName(\'truste_box_overlay\'); if (overlay && typeof (overlay[0]) != \'undefined\') overlay[0].style.display = "none";');
            $this->driver->executeScript('var overlay = document.getElementsByClassName(\'browserWarningOverlay-bg\'); if (overlay && typeof (overlay[0]) != \'undefined\') overlay[0].style.display = "none";');
            $this->driver->executeScript('var overlay = document.getElementsByClassName(\'jb-overlay-cont\'); if (overlay && typeof (overlay[0]) != \'undefined\') overlay[0].style.display = "none";');
            $this->driver->executeScript('var overlay = document.getElementById(\'bw-close-button\'); if (overlay) overlay.click();');
            sleep(1);
            $this->saveResponse();
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
    }

    protected function getJetblue()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->jetblue)) {
            $this->jetblue = new TAccountCheckerJetblue();
            $this->jetblue->http = new HttpBrowser("none", new CurlDriver());
            $this->jetblue->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->jetblue->http);
            $this->jetblue->State = $this->State;
            $this->jetblue->AccountFields = $this->AccountFields;
            $this->jetblue->itinerariesMaster = $this->itinerariesMaster;
            $this->jetblue->HistoryStartDate = $this->HistoryStartDate;
            $this->jetblue->historyStartDates = $this->historyStartDates;
            $this->jetblue->http->LogHeaders = $this->http->LogHeaders;
            $this->jetblue->ParseIts = $this->ParseIts;
            $this->jetblue->ParsePastIts = $this->ParsePastIts;
            $this->jetblue->WantHistory = $this->WantHistory;
            $this->jetblue->WantFiles = $this->WantFiles;
            $this->jetblue->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->jetblue->http->setDefaultHeader($header, $value);
            }

            $this->jetblue->globalLogger = $this->globalLogger;
            $this->jetblue->logger = $this->logger;
            $this->jetblue->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->jetblue->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->jetblue;
    }

    private function processSelectDeviceForm()
    {
        $this->logger->notice(__METHOD__);
        $selectOption =
            $this->waitForElement(WebDriverBy::xpath('//div[@data-se="okta_email"]/a'), 0)
            ?? $this->waitForElement(WebDriverBy::xpath('//div[@data-se="phone_number"]/a'), 0)
        ;

        if (!$selectOption) {
            $this->logger->debug('Select device form not found');

            return false;
        }

        $this->saveResponse();

        $this->sendNotification("refs #23832 - need to check processSelectDeviceForm // IZ");

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $selectOption->click();

        return true;
    }

    private function processQuestion()
    {
        $emailQuestionElement = $this->waitForElement(WebDriverBy::xpath('//div[@class="mfa-email-sent-content"]'), self::WAIT_TIMEOUT);
        $smsQuestionElement = $this->waitForElement(WebDriverBy::xpath('//h2[@data-se="o-form-head"]'), 0);
        $smsPhoneNumber = $this->waitForElement(WebDriverBy::xpath('//p[@class="okta-form-subtitle"]'), 0);
        $this->saveResponse();

        if (!$emailQuestionElement || (!$smsQuestionElement && !$smsPhoneNumber)) {
            $this->logger->error("mfa form fields not found");

            return false;
        }

        if ($emailQuestionElement) {
            $question = $emailQuestionElement->getText();
        } elseif ($smsQuestionElement && $smsPhoneNumber) {
            $question = $smsQuestionElement->getText() . ' ' . $smsPhoneNumber->getText();
        }

        $input = $this->waitForElement(WebDriverBy::xpath('//input[@name="answer"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if (!$question || !$input) {
            $this->logger->error("mfa form fields not found");

            return false;
        }

        if (!QuestionAnalyzer::isOtcQuestion($question)) {
            $this->sendNotification("refs #23832 - need to check QuestionAnalyzer // IZ");
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return true;
        }

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        $input->clear();
        $input->sendKeys($answer);

        $this->logger->debug("ready to click");
        $this->saveResponse();

        $verifyCodeButton = $this->waitForElement(WebDriverBy::xpath('//form[@data-se="factor-email"]//input[@type="submit" and @value="Verify"] | //form[@data-se="factor-sms"]//input[@type="submit" and @value="Verify"]'), 0);
        $this->saveResponse();

        if (!$verifyCodeButton) {
            $this->logger->error("btn not found");

            return false;
        }

        $this->logger->debug("clicking next");
        $verifyCodeButton->click();
        sleep(5);
        $this->saveResponse();
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($error = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "okta-form-infobox-error")]//p'), 0)) {
            $message = $error->getText();
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Your code doesn\'t match our records. Please try again.')
                || strstr($message, 'We found some errors. Please review the form and make corrections.')
                || strstr($message, 'Your token doesn\'t match our records. Please try again.')
            ) {
                $this->holdSession();
                $this->AskQuestion($question, $message, "Question");
            }

            if (
                strstr($message, 'Your session has expired. Please try to sign in again.')
                || strstr($message, 'Invalid session')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'Your account is locked because of too many authentication attempts.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->loginSuccessful();
    }
}
