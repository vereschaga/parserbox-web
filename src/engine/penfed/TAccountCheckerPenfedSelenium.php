<?php

class TAccountCheckerPenfedSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const XPATH_LOGIN_INPUT = '//input[@name = "username" or @id = "username-input"]';
    private const XPATH_PASSWORD_INPUT = '//input[@name = "password" or @id = "password-input" or @id = "password"]';
    private const XPATH_LOGIN_BUTTON = '//button[contains(text(), "LOG IN")] | //a[@id= "signOnButton"]';
    /** @var HttpBrowser */
    public $browser;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency']) && ($properties['Currency'] == '$')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $this->useChromePuppeteer();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
//        $this->useCache();
//        $this->usePacFile(false);
        $this->keepCookies(false);
        $this->http->saveScreenshots = true;
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->browser->LogHeaders = true;
        $this->browser->setProxyParams($this->http->getProxyParams());
        $this->logger->debug("[Curl URL]: " . $this->http->currentUrl());
        $this->logger->debug("[Selenium URL]: " . $this->getWebDriver()->getCurrentURL());

        return;

        $this->browser->GetURL($this->http->currentUrl());
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://www.penfed.org/");

        $this->waitForElement(WebDriverBy::xpath('
            //a[contains(text(), "Log In")]
            | //a[contains(text(), "Login")]
            | //input[@name = "username"]
        '), 10);
        $this->saveResponse();

        if ($back =
                $this->waitForElement(WebDriverBy::xpath('//a[@data-id="LogIn"]'), 0)
        ) {
            $back->click();
        }

        $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_INPUT . ' | //a[contains(., "Logout")] | //a[contains(text(), "Redeem Rewards:")] | //h2[contains(text(), "Checking & Savings")]'), 35);

        $login = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_INPUT), 0);
        $this->saveResponse();

        if (!$login) {
            $this->driver->executeScript("let login = document.querySelector('input[name = \"username\"], input[id = \"username-input\"]'); if (login) login.style.zIndex = '100003';");
            $this->driver->executeScript("let pass = document.querySelector('input[name = \"password\"], input[id = \"password-input\"], input[id = \"password\"]'); if (pass) pass.style.zIndex = '100003';");
            $this->saveResponse();
            $login = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_INPUT), 0);
            $this->saveResponse();
        }

        if (!$login) {
            if ($this->waitForElement(WebDriverBy::xpath('//a[contains(., "Logout")] | //a[contains(text(), "Redeem Rewards:")] | //h2[contains(text(), "Checking & Savings")] | //span[contains(text(), \'Text \')] | //*[contains(@d, \'M20,2A2,2 0 0,1\')]/ancestor::div[contains(@class, \'tile-button-\')]'), 0)) {
                return true;
            }

            // TODO
            if ($this->http->FindSingleNode('//title[contains(text(), "Challenge Validation")]')) {
                $this->http->GetURL("https://www.penfed.org/");
                $this->waitForElement(WebDriverBy::xpath('
                    //a[contains(text(), "Log In")]
                    | //a[contains(text(), "Login")]
                    | //input[@name = "username"]
                '), 10);
                $this->saveResponse();

                if ($back =
                    $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Log In")]'), 0)
                    ?? $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Login")]'), 0)
                ) {
                    $back->click();
                }

                $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_INPUT . ' | //a[contains(., "Logout")] | //a[contains(text(), "Redeem Rewards:")] | //h2[contains(text(), "Checking & Savings")]'), 35);

                $login = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_INPUT), 0);
                $this->saveResponse();
            }// if ($this->http->FindSingleNode('//title[contains(text(), "Challenge Validation")]'))

            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $this->waitForElement(WebDriverBy::xpath(self::XPATH_PASSWORD_INPUT), 0)->sendKeys($this->AccountFields['Pass']);

        $continueButton = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_BUTTON), 5);
        $this->saveResponse();

        if (!$continueButton) {
            $this->logger->error('Failed to find continue button');

            return false;
        }

        $this->logger->debug('Click "Sign On" button');
        $continueButton->click();

        return true;
    }

    public function Login()
    {
        $sleep = 70;
        $startTime = time();
        $clickOmeMoreTime = false;

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
            $logout = $this->waitForElement(WebDriverBy::xpath('//a[contains(., "Logout")] | //a[contains(text(), "Redeem Rewards:")]'), 3);
            $this->saveResponse();

            if ($logout) {
                return true;
            }

            if (!isset($send) && ($send = $this->waitForElement(WebDriverBy::xpath('//input[@value = "Send"]'), 0))) {
                $send->click();
            }

            // security questions
            if ($this->parseQuestion()) {
                return false;
            }

            if (
                (time() - $startTime) > 40
                && $clickOmeMoreTime === false
                && ($continueButton = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_BUTTON), 0))
                && $this->waitFor(function () {
                    return !$this->waitForElement(WebDriverBy::id("sec-cpt-if"), 0);
                }, 30)
            ) {
                $clickOmeMoreTime = true;

                $login = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_INPUT), 0);
                $login->clear();
                $login->sendKeys($this->AccountFields['Login']);
                $pass = $this->waitForElement(WebDriverBy::xpath(self::XPATH_PASSWORD_INPUT), 0);
                $pass->clear();
                $pass->sendKeys($this->AccountFields['Pass']);

                $continueButton->click();
                $this->saveResponse();
            }

            // Action Required: You have the option to update your delivery preferences to online only for the account(s) below.
            if ($error = $this->waitForElement(WebDriverBy::xpath('
                    //span[contains(@class, "toastMessage") and normalize-space() != ""]
                    | //div[contains(@class, "ping-error") and normalize-space() != ""]
                    | //slot[contains(text(), "We are experiencing difficulties with your phone number.")]
                    | //span[contains(text(), "An unexpected error happened. We are unable to complete the login process.")]
                    | //span[contains(text(), "We have identified suspicious login attempts associated with your PenFed Online profile.")]
                    | //h2[contains(text(), "Some of PenFed\'s systems are currently down for maintenance")]
                    | //h1[contains(text(), "Access Denied")]
                    | //div[contains(text(), "Your account is locked.")]
                '), 0)
            ) {
                $message = $error->getText();
                $this->logger->error("[Error]: {$message}");

                if (
                    strstr($message, 'We\'re sorry. The username and password you have entered do not match.')
                    || strstr($message, 'We\'re sorry. We didn\'t recognize the username or password you entered. Please try again.')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    strstr($message, 'We are experiencing difficulties with your phone number.')
                    || strstr($message, 'An unexpected error happened. We are unable to complete the login process.')
                    || strstr($message, 'We have identified suspicious login attempts associated with your PenFed Online profile.')// re-enroll
                    || strstr($message, 's systems are currently down for maintenance')
                    || strstr($message, 'The login service is unavailable.')
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if (
                    strstr($message, 'Your account is locked due to incorrect password attempts.')
                    || strstr($message, 'Your account is locked.')
                    || strstr($message, 'Your online access is currently blocked.')
                ) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                $this->DebugInfo = $message;

                return false;
            }

            $this->saveResponse();
        }

        if (
            $this->http->FindSingleNode('//h2[contains(text(), "Checking & Savings")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "WELCOME,")]')
        ) {
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h1[contains(text(), "WELCOME,")]', null, true, "/Welcome,\s*([^\|]+)/ims")));

            if (!empty($this->Properties['Name'])) {
                $this->SetWarning("You have no any rewards in your account"); /*review*/

                return false;
            }
        }

        if ($message = $this->http->FindSingleNode('//*[contains(text(), "We are experiencing difficulties with your phone number.")]')) {
            throw new CheckException(str_replace(' RETURN', '', $message), ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        return $this->enterVerificationCode();
    }

    public function Parse()
    {
        $this->logger->debug("[Current URL]: " . $this->http->currentUrl());

        /*
        $penfed = $this->getPenfed();
        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $penfed->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $penfed->http->FilterHTML = false;
        $penfed->http->GetURL($this->http->currentUrl(), [], 40);
        $penfed->http->FilterHTML = true;
        $penfed->Parse();

        $this->SetBalance($penfed->Balance);
        $this->Properties = $penfed->Properties;
        $this->ErrorCode = $penfed->ErrorCode;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorMessage = $penfed->ErrorMessage;
            $this->DebugInfo = $penfed->DebugInfo;
        }
        */
        //*[//h2[contains(text(), "Credit Cards")]]

        $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Credit Cards")]'), 15); //todo
        $this->saveResponse();

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h1[contains(text(), "WELCOME,")]', null, true, "/Welcome,\s*([^\|]+)/ims")));

        // parse properties
        $cards = $this->http->XPath->query('//h2[contains(text(), "Credit Cards")]/parent::node()//*[@class = "slds-card"]');
        $this->logger->debug("Total " . $cards->length . " cards were found");

        foreach ($cards as $card) {
            // Card/Account #
            $number = $this->http->FindSingleNode('.//a[@data-lastdigit]/@data-lastdigit', $card);
            $displayName = $this->http->FindSingleNode('.//span[contains(@class, "digit-color")]/preceding-sibling::node()[1]', $card);

            if (!$displayName) {
                continue;
            }

            $detectedCards = [
                'Code'            => 'penfed' . $number,
                'DisplayName'     => "{$displayName} ending in {$number}",
                "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
            ];
            $balance = $this->http->FindSingleNode('.//a[contains(@class, "slds-p-top_xx-small") and not(contains(text(), ":"))]', $card);

            if (!isset($balance)) {
                $this->AddDetectedCard($detectedCards);
                // AccoiuntID: 890565
                if (strstr($detectedCards['DisplayName'], 'Regular Savings')) {
                    $this->SetBalanceNA();
                }

                continue;
            }

            $detectedCards['CardDescription'] = C_CARD_DESC_ACTIVE;
            $this->AddDetectedCard($detectedCards);

            $subAccount = [
                'Code'        => 'penfed' . $number,
                'DisplayName' => "{$displayName} ending in {$number}",
                // Balance - Point Balance
                'Balance'     => $balance,
                // Card/Account #
                "Number"      => $number,
                "Currency"    => strstr($balance, '$') ? '$' : null,
            ];

            $this->AddSubAccount($subAccount);
        }

        // Set SubAccounts Properties
        if (
            !empty($this->Properties['SubAccounts'])
            // AccountID: 4650910
            || $this->http->FindNodes('//h2[contains(text(), "Credit Cards")]/parent::node()//*[@class = "slds-card"]')
        ) {
            $this->SetBalanceNA();
        }

        $this->logger->info('FICO® Score', ['Header' => 3]);
        $this->http->GetURL("https://home.penfed.org/v2/s/fico-score");
        $this->waitForElement(WebDriverBy::xpath("//div[@id = 'lastUpdated']"), 15); //todo
        $this->saveResponse();
        // FICO® SCORE
        $fcioScore =
            $this->http->FindPreg("/var\s*num\s*=\s*([^\;]+)/ims")
            ?? $this->http->FindSingleNode("//slot[contains(@class, 'slds-align_absolute-center ficoScoreHeadCss') and not(contains(., \"(\"))]")
        ;
        // FICO Score updated on
        $fcioUpdatedOn =
            $this->http->FindSingleNode("//div[@id = 'lastUpdated']", null, true, "/last \s*updated\s*(?:on\s*|)([^<\.]+)/ims")
            ?? $this->http->FindSingleNode("//slot[contains(text(), 'Updated ')]", null, true, "/Updated\s*(?:on\s*|)([^<\.]+)/ims")
        ;

        if ($fcioScore && $fcioUpdatedOn) {
            if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1) {
                foreach ($this->Properties['SubAccounts'][0] as $key => $value) {
                    if (in_array($key, ['Code', 'DisplayName'])) {
                        continue;
                    } elseif ($key == 'Balance') {
                        $this->SetBalance($value);
                    } elseif ($key == 'ExpirationDate') {
                        $this->SetExpirationDate($value);
                    } else {
                        $this->SetProperty($key, $value);
                    }
                }// foreach ($this->Properties['SubAccounts'][0] as $key => $value)
                unset($this->Properties['SubAccounts']);
            }// if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1)
            $this->SetProperty("CombineSubAccounts", false);
            $this->AddSubAccount([
                "Code"               => "penfedFICO",
                "DisplayName"        => "FICO® Score (TransUnion)",
                "Balance"            => $fcioScore,
                "FICOScoreUpdatedOn" => $fcioUpdatedOn,
            ]);
        }// if ($fcioScore && $fcioUpdatedOn)
    }

    protected function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//h4[contains(text(), "PenFed.org is experiencing intermittent availability issues.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    protected function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[Current URL]: " . $this->http->currentUrl());
        $q =
            $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Text ')] | //*[contains(@d, 'M20,2A2,2 0 0,1')]/ancestor::div[contains(@class, 'tile-button-')]"), 0)
            ?? $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Call ')] | //div[contains(text(), 'Cell Phone')]/ancestor::div[contains(@class, 'tile-button-')]"), 0)
            ?? $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Work Phone')]/ancestor::div[contains(@class, 'tile-button-')]"), 0)
        ;
        $this->saveResponse();

        if (!isset($q)) {
            return false;
        }

        $q->click();

        if ($personalDevice = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Yes, this is a personal device. Remember it.')]"), 0)) {
            $personalDevice->click();
        }

        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        if ($sendCode = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Send Verification Code')]"), 0)) {
            $sendCode->click();
        }

        return $this->enterVerificationCode();
    }

    /** @return TAccountCheckerPenfed */
    private function getPenfed()
    {
        if (!isset($this->penfed)) {
            $this->penfed = new TAccountCheckerPenfed();
            $this->penfed->http = new HttpBrowser("none", new CurlDriver());
            $this->penfed->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->penfed->http);
            $this->penfed->AccountFields = $this->AccountFields;
            $this->penfed->HistoryStartDate = $this->HistoryStartDate;
            $this->penfed->historyStartDates = $this->historyStartDates;
            $this->penfed->http->LogHeaders = $this->http->LogHeaders;
            $this->penfed->ParseIts = $this->ParseIts;
            $this->penfed->ParsePastIts = $this->ParsePastIts;
            $this->penfed->WantHistory = $this->WantHistory;
            $this->penfed->WantFiles = $this->WantFiles;
            $this->penfed->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->penfed->http->setDefaultHeader($header, $value);
            }

            $this->penfed->globalLogger = $this->globalLogger;
            $this->penfed->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        return $this->penfed;
    }

    private function enterVerificationCode()
    {
        $this->logger->notice(__METHOD__);
        $q = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Please enter the verification code sent')] | //div[contains(text(), 'Enter the passcode that you received')] | //div[contains(text(), 'Enter the passcode you heard')]"), 5);
        $this->saveResponse();

        if (!$q) {
            return false;
        }

        $question = trim($q->getText());

        if (!isset($this->Answers[$question])) {
            $this->logger->debug("Ask question");
            $this->holdSession();
            $this->AskQuestion($question, null, "enterVerificationCode");

            return true;
        }

        $input = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'verificationCode' or @name = 'otp']"), 0);
        $this->saveResponse();

        if (!$input) {
            $this->logger->error('Failed to find input field for "answer"');

            return false;
        }

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);
        $input->clear();
        $input->sendKeys($answer);
        // do not keep Advanced Access Code
        $this->logger->notice("Submit form");

        $submitButton = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "VERIFY & CONTINUE") or @id = "sign-on"]'), 0);
        $this->saveResponse();

        if (!$submitButton) {
            $this->logger->error('Failed to find submit button');

            return false;
        }

        $submitButton->click();
        // Invalid code. Please try again.
        $error = $this->waitForElement(WebDriverBy::xpath("//div[@role = 'alert' and not(contains(., 'Loading...'))] | //div[contains(@class, 'feedback--error')]/div[contains(@class, 'feedback__message')]"), 5);

        if ($error) {
            $this->saveResponse();
            $message = $error->getText();
            $this->logger->debug("Ask question. Wrong Code.");
            $this->logger->error("[Error]: '{$message}'");
            $this->holdSession();

            if (
                strstr($message, 'The verification code is incorrect. Please try again.')
                || strstr($message, 'This passcode is invalid or has expired.')
            ) {
                $this->AskQuestion($question, $message, "AdvancedAccessCode");
            }

            if (
                strstr($message, 'Too many unsuccessful attempts with this passcode.')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return true;
        }

        if (!isset($send) && ($send = $this->waitForElement(WebDriverBy::xpath('//input[@value = "Send"]'), 0))) {
            $send->click();
        }

        $this->waitForElement(WebDriverBy::xpath('//a[contains(., "Logout")] | //a[contains(text(), "Redeem Rewards:")]'), 10);

        $this->saveResponse();

        return true;
    }
}
