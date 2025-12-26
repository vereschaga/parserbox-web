<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerUsaaSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const XPATH_SUCCESS = "
        //span[contains(text(), 'My Accounts Summary')]
        | //h2[contains(text(), 'USAA rewards balance')]
        | //h2[contains(text(), 'Rewards Balance')]
        | //h2[contains(text(), 'personal financial assessment')]
        | //div[contains(text(), 'Logged on as')]
        | //dt[contains(text(), 'USAA Rewards')]
        | //h2[contains(@class, 'section-title') and contains(text(), 'Banking')]
    ";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        /*
        if ($this->attempt == 1) {
        */
        $this->useChromePuppeteer();
        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        /*
        } else {
            $this->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
            $this->setKeepProfile(true);
        }
        */
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://www.usaa.com");
        $this->http->GetURL("https://www.usaa.com/my/logon");
        $this->saveResponse();
        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "memberId"]'), 10);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "submit-btn")]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$button) {
            $this->checkProviderErrors();

            return $this->checkErrors();
        }
        $loginInput->sendKeys($this->AccountFields['Login']);
        $button->click();

        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password" or @name = "pintoken"]'), 10);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "pass-submit-btn") or contains(@class, "pin-token-submit-btn")]'), 0);
        $this->saveResponse();

        if (!$passwordInput || !$button) {
            if ($message = $this->http->FindSingleNode('//div[contains(@class, "fieldWrapper-errorMessage--active")]/span/text()[last()]')) {
                $this->logger->error("[Error]: {$message}");

                if (strstr($message, 'Invalid Character')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (strstr($message, 'Must be 20 characters or less')) {
                    throw new CheckException("Online ID must be 20 characters or less", ACCOUNT_INVALID_PASSWORD);
                }

                if (strstr($message, 'Must be 5 characters or more')) {
                    throw new CheckException("Online ID must be 5 characters or more", ACCOUNT_INVALID_PASSWORD);
                }

                if (strstr($message, 'Due to system maintenance, some account information and actions may be unavailable beginning Saturday')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }

            if ($message = $this->http->FindSingleNode('//p[contains(text(), "For your protection, your account has been locked.")]')) {
                throw new CheckException("For your protection, your account has been locked.", ACCOUNT_LOCKOUT);
            }

            $this->checkProviderErrors();

            return $this->checkErrors();
        }
        $this->driver->executeScript('if (document.querySelector(\'input[class = "usaa-checkbox-input"]\')) document.querySelector(\'input[class = "usaa-checkbox-input"]\').checked = true;');
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $button->click();

        return true;
    }

    // do not use it in checkErrors
    public function checkProviderErrors()
    {
        $this->logger->notice(__METHOD__);
        /*
         * We are unable to complete your request.
         *
         * Our system is currently unavailable.
         * Please try again later.
         */
        if ($message = $this->http->FindSingleNode('//div[@class = "usaa-dialogModal3-summary" and div[@class = "system-down-modal-text-contain"]/h2[contains(text(), "We are unable to complete your request")]]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $res = $this->waitForElement(WebDriverBy::xpath("
            //a[contains(text(), 'Use my PIN')]
            | //button[contains(., 'Use my PIN')]
            | //h2[contains(text(), 'Enter Your PIN')]
            | //a[contains(text(), ' security code to:')]
            | //p[contains(text(), 'Enter the 6-digit security code from ')]
            | //p[contains(text(), 'Enter the 6-digit security code from ')]
            | //div[contains(@class, 'usaa-alert-message')]
            | //h2[contains(@class, 'Confirm Your Contact Information')]
            | //span[contains(text(), 'Pardon our interruption')]
            | 
        "
            . self::XPATH_SUCCESS
        ), 10);
        $this->saveResponse();

        if ($this->http->FindSingleNode("//a[contains(text(), 'I need more options')]")) {
            $moreOptions = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'I need more options')]"), 10);
            $moreOptions->click();

            $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Use my PIN')] | //button[contains(., 'Use my PIN')]"), 5);
            $this->saveResponse();
        }

        if ($this->http->FindNodes("//a[contains(text(), 'Use my PIN')] | //h2[contains(text(), 'Enter Your PIN')] | //button[contains(., 'Use my PIN')]")) {
            if (!$this->http->FindSingleNode("//h2[contains(text(), 'Enter Your PIN')]")) {
                $pinBtn = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Use my PIN')] | //button[contains(., 'Use my PIN')]"), 0);
                $pinBtn->click();
            }

            $pinInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "pin"]'), 5);
            $cont = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "miam-btn-next")]'), 0);

            if (!$pinInput || !$cont) {
                return false;
            }
            $pinInput->sendKeys($this->AccountFields['Login2']);
            $cont->click();

            $this->waitForElement(WebDriverBy::xpath(
                self::XPATH_SUCCESS .
                "
                | //div[contains(@class, 'usaa-alert-message')]
                | //span[contains(text(), 'Remind Me Later')]
                | //button[contains(text(), 'Remind me later')]
                | //p[contains(text(), 'Please complete the following pages of information about you and your accounts to continue with digital access.')]
                | //p[contains(text(), 'We need your permission to contact you about your USAA accounts or membership with an automated dialing system')]
                | //h1[contains(text(), 'Occupation Information')]
                | //h1[contains(text(), 'Prior Express Written Consent')]
                | //iframe[contains(@class, 'modal-iframe')]
                | //p[contains(text(), 'Action Required')]
                | //h2[contains(@class, 'section-title') and contains(text(), 'Banking')]
                | //p[contains(text(), 'Our system is currently unavailable.')]
            "), 10);
            $this->saveResponse();

            if ($message = $this->http->FindSingleNode("//div[contains(@class, 'usaa-alert-message')]")) {
                $this->logger->error("[Error]: {$message}");

                if ($message == 'Sorry, that is not your pin') {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message != 'You can change how you view this page using the "Customize This Page" link.'
                    && !strstr($message, 'Due to system maintenance, ')
                    && !strstr($message, 'System Maintenance')
                    && !strstr($message, 'Some members are experiencing problems with Transfer Funds')
                    && !strstr($message, 'We\'re making it easier for you to find what you\'re looking for on usaa.com.')
                    && !strstr($message, 'The Tennessee Department of Commerce and Insurance has issued a moratorium on cancellation and nonrenewal')
                    && !strstr($message, 'Scammers are posing as USAA.')
                    && !strstr($message, 'Due to scheduled maintenance some of your insurance accounts will not be available.')
                    && !strstr($message, 'You may see some change')
                    && !strstr($message, 'Some members are experiencing problems with debit card transaction')
                    && !strstr($message, 'We are currently unable to assist some members with insurance and banking services')
                    && !strstr($message, 'USAA stands ready')
                    && !strstr($message, 'Good news ')
                ) {
                    $this->logger->notice("Unknown error");
                    $this->DebugInfo = $message;

                    return false;
                }
            }

            if ($this->http->FindSingleNode("//span[contains(text(), 'Remind Me Later')]")) {
                $remindMeLater = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), \'Remind Me Later\')]'), 0);
                $remindMeLater->click();

                $this->waitForElement(WebDriverBy::xpath(
                    self::XPATH_SUCCESS .
                    "
                    | //div[contains(@class, 'usaa-alert-message')]
                "), 10);
                $this->saveResponse();
            }

            if ($this->loginSuccessful()) {
                return true;
            }

            if ($iframe = $this->waitForElement(WebDriverBy::xpath("//iframe[contains(@class, 'modal-iframe')]"), 0)) {
                $this->logger->notice("switch to iframe");
                $this->driver->switchTo()->frame($iframe);
                $this->saveResponse();
            }

            if ($this->http->FindSingleNode('
                    //p[
                        contains(text(), "Please complete the following pages of information about you and your accounts to continue with digital access.")
                        or contains(text(), "To help us comply with regulatory requirements, please tell us about your job so we can continue serving your financial needs.")
                        or contains(text(), "To help us comply with regulatory requirements, we are required to obtain and confirm additional information about you ")
                        or contains(text(), "We need your permission to contact you about your USAA accounts or membership with an automated dialing system")
                        or contains(text(), "We need you to review some key information and make updates if needed.")
                    ]
                    | //h1[contains(text(), "Occupation Information")]
                    | //h1[contains(text(), "Prior Express Written Consent")]
                    | //h1[contains(., "Missing Youth Account Information")]
                    | //span[contains(text(), \'Pardon our interruption\')]
                ')
                || $this->http->FindSingleNode('//p[
                        contains(text(), "To comply with federal law and regulations, we are required to obtain and confirm additional information")
                    ]
                ')
                || $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Confirm Your Contact Information")]'), 0)
            ) {
                $this->throwProfileUpdateMessageException();
            }

            $this->saveResponse();

            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->http->FindNodes("//a[contains(text(), ' security code to:')] | //button[contains(., ' security code to:') or contains(., 'Get the 6-digit security code')]")) {
            $codeBtn =
                $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Email security code to:')] | //button[contains(., 'Email security code to:')]"), 0)
                ?? $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Text security code to:')] | //button[contains(., 'Text security code to:')]"), 0)
                ?? $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Get the 6-digit security code')]"), 0)
            ;

            if (!$codeBtn) {
                return false;
            }

            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            $codeBtn->click();
            $this->process2FA();

            return false;
        }

        if ($this->http->FindSingleNode("//p[contains(text(), 'Enter the 6-digit security code from ')]")) {
            $this->process2FA();

            return false;
        }// if ($this->http->FindSingleNode("//p[contains(text(), 'Enter the 6-digit security code from ')]"))

        if ($message = $this->http->FindSingleNode("
                //div[contains(@class, 'usaa-alert-message')]
                | //div[contains(@class, 'fieldWrapper-errorMessage--active')]/span/text()[last()]
            ")
        ) {
            $this->logger->error("[Error]: {$message}");

            if (in_array($message, [
                "Sorry, the password you entered doesn't match what we have on file.",
                "Sorry, the information you entered doesn't match what we have on file.",
            ])) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'PIN + Token must be 10 digits')) {
                throw new CheckException("PIN + Token must be 10 digits", ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");

        /*
        if ($this->http->FindSingleNode('//h2[contains(text(), "We are unable to complete your request.")]')) {
            throw new CheckRetryNeededException(2, 0);
        }
        */

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($step == "process2FA") {
            return $this->process2FA();
        }

        return true;
    }

    public function Parse()
    {
        $balance = $this->http->FindSingleNode('//div[@caption="Reward Balance"]//div[contains(@class, "content-row-title")]');
        $savingsCards = $this->http->FindNodes('//span[@class="product-name" and (contains(text(), "USAA SAVINGS") or contains(text(), "Savings") or contains(text(), " CHECKING") or contains(text(), "Checking"))] | //div[contains(text(), "Usaa") and contains(text(), "Insurance")]');

        $usaa = $this->getUsaa();
        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $usaa->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $usaa->http->RetryCount = 0;
        $usaa->http->GetURL($this->http->currentUrl(), [], 40);
        $usaa->http->RetryCount = 2;

        $usaa->Parse();
        $this->SetBalance($usaa->Balance);
        $this->Properties = $usaa->Properties;
        $this->ErrorCode = $usaa->ErrorCode;

        // refs #24735
        if ($this->ErrorCode != ACCOUNT_CHECKED || $this->Balance === null) {
            $this->SetBalance($balance);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && count($savingsCards) && !empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorMessage = $usaa->ErrorMessage;
            $this->DebugInfo = $usaa->DebugInfo;
        }
    }

    protected function process2FA()
    {
        $this->logger->notice(__METHOD__);
        $otp = $this->waitForElement(WebDriverBy::xpath('//input[@name = "inputValue"]'), 10);
        $question = $this->waitForElement(WebDriverBy::xpath('
            //p[contains(text(), "We sent a 6-digit security code to")]
            | //p[contains(text(), "Enter the 6-digit security code from ")]
        '), 0);
        $this->saveResponse();

        if (!$otp || !$question) {
            $this->logger->error("something went wrong");

            return false;
        }
        $q = $question->getText();
        // refs #24735
        $this->logger->debug("[Question]: '{$q}'");
        $q = Html::cleanXMLValue($q);
        $this->logger->debug("[Question]: '{$q}'");

        if (!isset($this->Answers[$q])) {
            $this->holdSession();
            $this->AskQuestion($q, null, "process2FA");

            return false;
        }// if (!isset($this->Answers[$q]))
        $otp->clear();
        $otp->sendKeys($this->Answers[$q]);
        unset($this->Answers[$q]);
        // Next button
        $cont = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "miam-btn-next")]'), 0);
        $this->saveResponse();

        if (!$cont) {
            $this->logger->error("something went wrong");

            return false;
        }
        $cont->click();
        $this->saveResponse();

        // if wrong answer wa entrered first time
        sleep(5);

        try {
            $this->waitForElement(WebDriverBy::xpath("
                //span[contains(text(), 'My Accounts Summary')]
                | //div[contains(@class, 'usaa-alert-message')]
                | //div[contains(@class, 'fieldWrapper-errorMessage--active')]/span/text()[last()]
            "), 5);
            $this->saveResponse();

            // Sorry, your code doesn't match
            // Code must be 6 digits
            if ($error = $this->http->FindSingleNode('
                    //div[contains(@class, "usaa-alert-message")]
                    | //div[contains(@class, "fieldWrapper-errorMessage--active")]/span/text()[last()]
                ')
            ) {
                $otp->clear();
                $this->logger->notice("error: " . $error);
                $this->holdSession();
                $this->AskQuestion($q, $error, "process2FA");

                return false;
            }// if (!empty($error))
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            sleep(2);
            $this->saveResponse();
        }

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        // selenium bugfix
        if (strstr($this->http->currentUrl(), 'https://www.usaa.com/utils/oauth/auth-callback/?code=')) {
            $this->http->GetURL("https://www.usaa.com/my/usaa");

            $this->waitForElement(WebDriverBy::xpath("
                //span[contains(text(), 'My Accounts Summary')]
                | //div[contains(@class, 'usaa-alert-message')]
                | //div[contains(@class, 'fieldWrapper-errorMessage--active')]/span/text()[last()]
            "), 5);
            $this->saveResponse();

            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        }

        return true;
    }

    protected function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindNodes(self::XPATH_SUCCESS . ' | //h2[contains(text(), "Banking")]')
            || $this->waitForElement(WebDriverBy::xpath(self::XPATH_SUCCESS), 0)
        ) {
            return true;
        }

        return false;
    }

    /** @return TAccountCheckerUsaa */
    protected function getUsaa()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->usaa)) {
            $this->usaa = new TAccountCheckerUsaa();
            $this->usaa->http = new HttpBrowser("none", new CurlDriver());
            $this->usaa->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->usaa->http);
            $this->usaa->AccountFields = $this->AccountFields;
            $this->usaa->HistoryStartDate = $this->HistoryStartDate;
            $this->usaa->http->LogHeaders = $this->http->LogHeaders;
            $this->usaa->ParseIts = $this->ParseIts;
            $this->usaa->ParsePastIts = $this->ParsePastIts;
            $this->usaa->WantHistory = $this->WantHistory;
            $this->usaa->WantFiles = $this->WantFiles;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->usaa->http->setDefaultHeader($header, $value);
            }

            $this->usaa->globalLogger = $this->globalLogger;
        }

        return $this->usaa;
    }
}
