<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerAirmilesca extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const WAIT_TIMEOUT = 10;

    private const XPATH_VERIFY = '//button[contains(text(), "Try a different method")]';
    private const XPATH_PHONE_NOT_ADDED = '//button[@value="phone::0"] | //p[contains(text(), "Enter your") and contains(text(), "phone number to receive a 6-digit code for account verification.")]';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->setHttp2(true);

        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->useChromePuppeteer();
        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;

        /*
        $this->setProxyGoProxies(null, 'ca');

        if ($this->attempt !== 0) {
            $this->http->SetProxy($this->proxyDOP()); // The server encountered an internal error or misconfiguration and was unable to complete your request.
        }
        */

        $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));
    }

    public function IsLoggedIn()
    {
        return $this->loginSuccessful();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.airmiles.ca/en/login");

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="login-page-user-id-field"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if (!$login) {
            $this->clickCloudFlareCheckboxByMouse($this);
            $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="login-page-user-id-field"]'), self::WAIT_TIMEOUT);
            $this->saveResponse();
        }

        if (!$login) {
            $this->saveResponse();

            return $this->checkErrors();
        }

        /*
        $this->driver->executeScript("setInterval(function() { $('button#onetrust-accept-btn-handler').click(); }, 500);");
        sleep(2);
        */

        $login->sendKeys($this->AccountFields['Login']);

        if (
            $cookieButton = $this->waitForElement(WebDriverBy::xpath('//button[@id="onetrust-accept-btn-handler"]'), self::WAIT_TIMEOUT)
        ) {
            $cookieButton->click();
        }

        $continue = $this->waitForElement(WebDriverBy::xpath('//button[@id="login-submit-btn"]'), 0);

        if (!$continue) {
            $this->saveResponse();

            return $this->checkErrors();
        }

        /*
        $continue->click();
        */
        $this->driver->executeScript("document.getElementById('login-submit-btn').click();");
        // $this->driver->executeScript("$('button#login-submit-btn').click();");
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@id="login-page-password-field"]'), self::WAIT_TIMEOUT);

        if (!$password) {
            $this->saveResponse();

            return $this->checkErrors();
        }

        $password->sendKeys($this->AccountFields['Pass']);
        $continue = $this->waitForElement(WebDriverBy::xpath('//button[@id="login-submit-btn"]'), 0);
        $this->saveResponse();

        if (!$continue) {
            return $this->checkErrors();
        }

        /*
        $continue->click();
        */
        // $this->driver->executeScript("$('button#login-submit-btn').click();");
        $this->driver->executeScript("document.getElementById('login-submit-btn').click();");
        $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "V2Alert__content__paragraph")]/span | //span[@class="collector-name"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();
        sleep(3);

        if ($this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "V2Alert__content__paragraph")]/span[contains(text(), "Please wait...")]'), self::WAIT_TIMEOUT)) {
            $continue = $this->waitForElement(WebDriverBy::xpath('//button[@id="login-submit-btn"]'), 0);
            // $this->driver->executeScript("$('button#login-submit-btn').click();");
            $this->driver->executeScript("document.getElementById('login-submit-btn').click();");
            /*
            $continue->click();
            */
        }

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //p[contains(@class, "V2Alert__content__paragraph")]/span[not(contains(text(), "Please wait..."))]
            | //span[@class="collector-name"]
            | //iframe[contains(@title, "recaptcha challenge")]
            | //h1[contains(text(),"Your account has been locked")]
            | //p[contains(text(),"Your PIN must be only 4 digits.")]
            | //p[contains(text(), "Enter your") and contains(text(), "phone number to receive a 6-digit code for account verification.")]
            | //p[contains(text(), "We will send a 6-digit code to the following")]
            | //h1[contains(text(), "Switch the way you sign in")]
        '), self::WAIT_TIMEOUT * 3);
        $this->saveResponse();
        $this->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));

        if ($verify = $this->waitForElement(WebDriverBy::xpath(self::XPATH_VERIFY), 0)) {
            $verify->click();
            $sendCode =
                $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Email"]'), 10)
                ?? $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Phone Call or Text Message"] | //input[@id = "sms"]'), 0)
                ?? $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Phone"]'), 0)
            ;
            $this->saveResponse();

            if (!$sendCode) {
                return false;
            }

            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            $sendCode->click();

            return $this->processSecurityCheckpoint();
        }

        // Add a phone number to verify your account.
        if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_PHONE_NOT_ADDED . ' | //h1[contains(text(), "Switch the way you sign in")]'), 0)) {
            $this->throwProfileUpdateMessageException();
        }

        $this->saveResponse();

        if (
            $message = $this->http->FindSingleNode('//p[contains(@class, "V2Alert__content__paragraph")]/span[not(contains(text(), "Please wait..."))]
            | //h1[contains(text(),"Your account has been locked")]
            | //p[contains(text(),"Your PIN must be only 4 digits.")]')
        ) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'What you’ve entered does not match our records. You may not be set up to sign in with your email address. ')
                || strstr($message, 'This account has enabled sign-in with email. Please sign in using email address and password.')
                || strstr($message, 'Your PIN must be only 4 digits.')
                || strstr($message, 'The sign-in credentials you\'ve entered do not match our records.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Your account has been locked')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, "Something went wrong")) {
                throw new CheckRetryNeededException(3, 3, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return $this->checkErrors();
        }

        if (strstr($this->http->currentUrl(), 'intercept.html')) {
            $this->http->GetURL('https://www.airmiles.ca/en/profile.html');
        }

        if (
            $this->waitForElement(WebDriverBy::xpath('//div[@data-testid="Collector Number"] | //span[@class="collector-name"]'), self::WAIT_TIMEOUT)
            && $this->loginSuccessful()
        ) {
            return true;
        }

        if ($this->http->FindSingleNode('//p[contains(@class, "V2Alert__content__paragraph")]/span[contains(text(), "Please wait...")]')) {
            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == 'Question2fa' && $this->processSecurityCheckpoint()) {
            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
            $this->saveResponse();

            return true;
        }

        return false;
    }

    private function processSecurityCheckpoint(): bool
    {
        $this->logger->notice(__METHOD__);

        if ($sms = $this->waitForElement(WebDriverBy::xpath('//label[@for="sms"]'), 5)) {
            $this->saveResponse();
            $sms->click();
            $this->saveResponse();
            $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "CONTINUE")]'), 0);

            if (!$button) {
                $this->logger->error("Button not found");
                $this->saveResponse();

                return false;
            }

            $button->click();
        }

        $destination = $this->waitForElement(WebDriverBy::xpath("//span[contains(@class, 'ulp-authenticator-selector-text')]"), 5);
        $this->saveResponse();

        if (!$destination) {
            $this->logger->error("something went wrong");

            return false;
        }

        $question = "We've sent an email with your code to: {$destination->getText()}";

        if (strstr($destination->getText(), 'XXXXXXX')) {
            $question = "We've sent a text message to: {$destination->getText()}";
        }

        /*
        if (strstr($destination->getText(), '@') && !QuestionAnalyzer::isOtcQuestion($question)) {
            $this->sendNotification("question has been changed");
        }
        */

        if (!isset($question)) {
            $this->logger->error("something went wrong");

            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question2fa");

            return false;
        }

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);
        $code = $this->waitForElement(WebDriverBy::xpath('//input[@name="code"]'), 0);

        if (!$code) {
            return false;
        }

        $code->clear();
        $code->sendKeys($answer);
        $this->driver->executeScript('try { document.querySelector(\'input[name="rememberBrowser"]\').checked = true; } catch (e) {}');
        $this->saveResponse();

        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "CONTINUE")]'), 0);

        if (!$button) {
            $this->logger->error("Button not found");
            $this->saveResponse();

            return false;
        }

        $button->click();
        sleep(5);
        $this->saveResponse();

        /*
        $this->waitForElement(WebDriverBy::xpath(
            self::XPATH_ERRORS
            . " | " . self::XPATH_PROFILE
        ), 5);

        $message = $this->waitForElement(WebDriverBy::xpath(self::XPATH_ERRORS), 0);
        $this->saveResponse();

        if ($message) {
            $message = $message->getText();
            $this->logger->error("resetting answer: " . $message);

            if (
                strstr($message, 'The code you entered is not valid.')
                || strstr($message, 'We\'re sorry, we couldn\'t verify the code')
            ) {
                $this->holdSession();
                $this->AskQuestion($question, $message, "Question2fa");
            }

            if (strstr($message, 'Not Authorized!')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = "[ProcessStep]: " . $message;

            return false;
        }

        $this->waitForElement(WebDriverBy::xpath('//span[@data-binding="AccountInfo.PointBalance"]'), 5);
        $this->saveResponse();
        */

        return true;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));
        $profile = $response->profile;
        // Name
        $name = ($profile->personalDetails->firstName ?? null) . ' ' . ($profile->personalDetails->lastName ?? null);
        $this->SetProperty("Name", beautifulName($name));
        // Collector Number
        $this->SetProperty('Number', $profile->cardNumber ?? null);

        // Status
        $status = '';

        switch ($profile->tier) {
            case 'B':
                $status = 'Blue';

                break;

            case 'G':
                $status = 'Gold';

                break;

            case 'O':
                $status = 'Onyx®';

                break;

            default:
                $this->sendNotification("Unknown status: {$profile->tier}");
        }
        $this->SetProperty('Status', $status);
        // Current status (until ...)
        $this->SetProperty("StatusExpiration", $this->http->FindSingleNode("//p[contains(@class, 'current-status-label')]", null, true, "/until\s*([^\)]+)/"));

        // You've collected: ... Miles this year
        //		$this->http->GetURL("https://www.airmiles.ca/en/profile/transaction-history.html");
        $from = str_replace('+00:00', '.000Z', date('c', strtotime("-1 year -1 day"))); // 2019-12-14
        $to = str_replace('+00:00', '.000Z', date('c')); // 2020-12-15T18:59:59.999Z
        $this->http->GetURL("https://bff.api.airmiles.ca/dombff-contents/services/airmiles/sling/no-cache/transactions?page=1&size=19999&from={$from}&to={$to}&sort=transactionDate,desc&locale=en");
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'), 3, false, 'cashMilesEarned');
        // refs#23697
        $lastActivity = 0;
        $genericActivityDtoList = $response->_embedded->genericActivityDtoList ?? [];

        foreach ($genericActivityDtoList as $activity) {
            $expDate = strtotime($this->http->FindPreg('/^(.+?)T/', false, $activity->transactionDate));

            if ($expDate && $expDate > $lastActivity) {
                $this->logger->debug("Expiration Date: $expDate");
                $lastActivity = $expDate;
            }
        } // foreach ($genericActivityDtoList as $activity)

        if ($lastActivity > 0) {
            // Last Activity
            $this->SetProperty("LastActivity", date('F j, Y', $lastActivity));
            // Expiration Date
            $this->SetExpirationDate(strtotime('+2 year', $lastActivity));
        }

        if (isset($response->_embedded)) {
            $this->SetProperty("YTDMiles", $response->_embedded->transactionSummary->cashMilesEarned + $response->_embedded->transactionSummary->dreamMilesEarned);
        }

        // Sub Accounts  // refs #4470
        $this->http->GetURL('https://bff.api.airmiles.ca/dombff-profile/services/airmiles/sling/no-cache/member-banner');
        $this->waitForElement(WebDriverBy::xpath('//pre[not(@id)]'), self::WAIT_TIMEOUT);
        $this->saveResponse();
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));

        $nodes = [
            'Cash Miles'  => 'cashBalance',
            'Dream Miles' => 'dreamBalance',
        ];
        $i = 0;

        foreach ($nodes as $key => $value) {
            $balance = $response->{$value} ?? null;
            $displayName = $key;

            if (isset($balance, $displayName) && ($balance != 0 || $i == 0)) {
                $this->AddSubAccount([
                    'Code'        => 'airmilesca' . str_replace([' ', 'ê'], ['', 'e'], $displayName),
                    'DisplayName' => $displayName,
                    'Balance'     => $balance,
                ]);
            } // if (isset($balance))
            else {
                $this->logger->notice("Skip -> {$displayName}: {$balance}");
            }
            $i++;
        } // for ($i = 0; $i < $nodes->length; $i++)

        if (!empty($this->Properties['SubAccounts'])) {
            $this->SetBalanceNA();
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($message = $this->http->FindSingleNode('//title[contains(text(), "We apologize, we are experiencing technical difficulties")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            $message = $this->http->FindSingleNode('//p[contains(@class, "page_title")]/following-sibling::p[1]');

            if ($this->http->FindPreg("/Until 06:00 on Aug 09 2020,we'll be making some improvements atairmiles\.ca to better serve you in the future\./", false, $message)) {
                $cleanedMessage = "Until 06:00 on Aug 09 2020, we'll be making some improvements at airmiles.ca to better serve you in the future.";

                throw new CheckException($cleanedMessage, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindSingleNode('//p[contains(normalize-space(),"Until 06:00 on Dec 13 2020,we\'ll be making some improvements atairmiles.ca to better serve you in the future.In the meantime, check out what\'s new")]')) {
                $cleanedMessage = "Until 06:00 on Dec 13 2020, we'll be making some improvements at airmiles.ca to better serve you in the future.";

                throw new CheckException($cleanedMessage, ACCOUNT_PROVIDER_ERROR);
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;

        $this->http->GetURL('https://bff.api.airmiles.ca/dombff-profile/profile?language=ENGLISH');
        $this->waitForElement(WebDriverBy::xpath('//pre[not(@id)]'), self::WAIT_TIMEOUT);
        $this->saveResponse();
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));

        $this->http->RetryCount = 2;

        if (!empty($response->profile->cardNumber)) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //  From Sunday, June 30 at 8:00 p.m. ET to Monday, July 1 at 11:00 p.m. ET, we will be upgrading our systems to better serve you.
        if ($message = $this->http->FindSingleNode('
                //p[contains(., "will be upgrading our systems to better serve you.")]
                | //p[contains(text(), "We\'re undergoing some maintenance right now, but we\'ll be up and running shortly.")]
            ')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Scheduled maintenance
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'are unavailable due to scheduled maintenance until')]", null, true, "/(.+)Thank you for/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Scheduled maintenance
        if ($message = $this->http->FindPreg("/(Thanks for visiting our site. We are currently performing scheduled[^<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Website is currently unavailable
        if ($message = $this->http->FindPreg("/Website is currently unavailable/ims")) {
            throw new CheckException("The airmiles.ca Website is currently unavailable. We apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }
        // An error occurred while processing your request
        if ($message = $this->http->FindPreg("/An error occurred while processing your request./ims")) {
            throw new CheckException("Site is temporarily down. Please try to access it later.", ACCOUNT_PROVIDER_ERROR);
        }

        // Website is temporarily unavailable
        if ($message = $this->http->FindPreg("/Website is temporarily unavailable/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/Airmiles.ca is temporarily unavailable [^<]+/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Site outage /*checked*/
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'sites are experiencing slow response times or site outages')]", null, true, "/([^-<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Airmiles.ca is experiencing a site outage
        if ($message = $this->http->FindPreg("/Airmiles.ca is experiencing a site outage[^<]+/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // HTTP Status 500
        if (
            $this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 500')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 404')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - Zero size object')]")
            || $this->http->FindSingleNode("//h2[contains(text(), 'The request could not be satisfied.')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '504 Gateway Time-out')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // The server is temporarily unable to service your request. Please try again later.
        if ($message = $this->http->FindPreg("/(The server is temporarily unable to service your request.\s*Please try again later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * wrong error
        ##  The server encountered an internal error or misconfiguration and was unable to complete your request.
        if ($message = $this->http->FindPreg("/(The server encountered an internal error or misconfiguration and was unable\s*to\s*complete your request\.)/ims"))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        */
        // Maintenance
        if (
            $this->http->FindSingleNode("//title[contains(text(), 'Site Outage (Planned Outage')]")
            && ($message = $this->http->FindSingleNode("//span[@class = 'return_time']/parent::p"))
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($this->http->FindSingleNode("//title[contains(text(), 'Site Outage (Volume Outage)')]")) {
            throw new CheckException("With so many Collectors visiting airmiles.ca we sure are feeling the love. Although our site is currently unavailable, we will be back up again soon!", ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Although airmiles.ca needs a short break')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We apologize, we are experiencing technical difficulties
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'We apologize, we are experiencing technical difficulties')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if (
            in_array($this->http->Response['code'], [0, 500])
            // Service Temporarily Unavailable
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")
        ) {
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "The operation cannot be completed")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
