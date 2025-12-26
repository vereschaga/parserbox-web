<?php

// refs #6168
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerWoolworthsSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const LOGOUT_LINK_XPATH = '//div[
            (
                contains(text(), "POINTS EARNED")
                or contains(text(), "Points collected")
                or contains(text(), "Everyday Rewards points collected")
            )
            and not(contains(@class, "hide"))]/preceding-sibling::div[not(contains(@class, "hide"))]
            | //div[contains(@class, "points-balance_component_pointsSummary")]/p/strong
        ';

    private const XPATH_PHONE_NOT_ADDED = '//p[contains(text(), "Enter your phone number. We will send you a verification code")]';

    private const REWARDS_PAGE_URL = "https://www.everyday.com.au/";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->KeepState = true;
        $this->setProxyBrightData(null, 'static', 'au');

        /*
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        */
        $this->useFirefoxPlaywright();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->usePacFile(false);
//        $this->useCache();// todo: do not use cache, browser window gets stuck
        $this->disableImages();
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();

        try {
//            $this->http->GetURL('https://www.everyday.com.au');
            $this->http->GetURL('https://www.everyday.com.au/index.html#/my-offers');
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage());
        } catch (TimeOutException $e) {
            $this->logger->error("TimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        $iframe = $this->waitForElement(WebDriverBy::id("WXLoginIFrameObject"), 5);

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if (!$iframe/* && $this->http->currentUrl() === 'https://www.everyday.com.au/how-it-works.html.html'*/) {
            $this->saveResponse();

            if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                return $this->checkErrors();
            }

            $this->logger->debug("force open login frame");
            $this->driver->executeScript('try { toggleSideSheet(\'login\'); } catch (e) {}');
            sleep(2);
        }

        $iframe = $this->waitForElement(WebDriverBy::id("WXLoginIFrameObject"), 15);
        $this->saveResponse();

        if (!$iframe) {
            if ($this->http->FindPreg('/fingerprint\/script\/kpf\.js\?url=/')) {
                if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                    return false;
                }

                throw new CheckRetryNeededException(3, 5);
            }

//            return $this->checkErrors();
        } else {
            $this->driver->switchTo()->frame($iframe);
        }

        $this->waitForElement(WebDriverBy::xpath("//form[@action = 'er-login/validate-user']//input[@name='emailCardNumber'] | //input[@name = 'username'] | //h1[contains(text(), 'Access Denied')]"), 45);
        $login = $this->waitForElement(WebDriverBy::xpath("//form[@action = 'er-login/validate-user']//input[@name='emailNumber'] | //input[@name = 'username']"), 0);
        /*
        $btn = $this->waitForElement(WebDriverBy::xpath("//form[@action = 'er-login/validate-user']//button[@type='submit']"), 0);
        */
        $this->saveResponse();

        if (!$login/* || !$btn*/) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $pass = $this->waitForElement(WebDriverBy::xpath("//form//input[@name='password']"), 5);
        $this->saveResponse();

        if (!$pass) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }

        $pass->sendKeys($this->AccountFields['Pass']);

        $btnPass = $this->waitForElement(WebDriverBy::xpath("//button[@type='submit' and not(@disabled) and contains(text(), 'Login')]"), 3);
        $this->saveResponse();

        if (!$btnPass) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }
        sleep(1);
        $btnPass->click();
        sleep(3);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'We are currently busy performing maintenance on our website.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 502 Bad Gateway
        if ($this->http->FindSingleNode("
                //h1[contains(text(), '502 Bad Gateway')]
            ")
        ) {
            if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                return false;
            }

            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(2, 5, self::PROVIDER_ERROR_MSG);
        }
        // retries
        if (
            $this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")
        ) {
            $this->markProxyAsInvalid();

            if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
                $this->DebugInfo = 'Access Denied';
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
            }

            if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                return false;
            }

            throw new CheckRetryNeededException(3, 0);
        }

        return false;
    }

    public function Login()
    {
        $startTime = time();
        $time = time() - $startTime;
        $sleep = 30;

        while ($time < $sleep) {
            $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");

            $this->logger->debug('[Current URL]: ' . $this->http->currentUrl());
            // strange behavior on some accounts
            if ($this->http->currentUrl() == 'https://www.everyday.com.au/index.html#/cards-account') {
                try {
                    $this->http->GetURL(self::REWARDS_PAGE_URL);
                } catch (UnrecognizedExceptionException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                }
            }

            // look for logout link
            $logout = $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_LINK_XPATH), 0);
            $this->saveResponse();

            if ($logout || $this->http->FindSingleNode(self::LOGOUT_LINK_XPATH)) {
                $this->markProxySuccessful();

                return true;
            }

            // 2fa via sms
            if ($anotherMethod = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Try another method')]"), 0)) {
                $anotherMethod->click();
                // try to find email option
                $codeOption =
                    $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Email')]"), 3)
                    ?? $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Phone')]"), 0)
                ;
                $this->saveResponse();

                if ($codeOption) {
                    $codeOption->click();
                }

                $contBtn = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Continue')]"), 5);

                if (!$contBtn) {
                    return false;
                }

                $contBtn->click();
                $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "We\'ve sent a one time verification code to")] | //p[contains(text(), "We\'ve sent a verification code via")]'), 5);
                $this->saveResponse();
            } elseif ($codeOption = $this->waitForElement(WebDriverBy::xpath("//label[contains(., 'SMS')]"), 0)) {
                $this->saveResponse();
                $codeOption->click();

                $contBtn = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Continue')]"), 5);

                if (!$contBtn) {
                    return false;
                }

                $contBtn->click();
                $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "We\'ve sent a one time verification code to")] | //p[contains(text(), "We\'ve sent a verification code via")]'), 5);
                $this->saveResponse();
            }

            if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "We\'ve sent a one time verification code to")] | //p[contains(text(), "We\'ve sent a verification code via")]'), 0)) {
                if ($sendCodeToEmail = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Email me the code instead")]'), 0)) {
                    $sendCodeToEmail->click();
                    sleep(4);
                    $this->saveResponse();
                }

                return $this->processSecurityQuestion();
            }

            // Add a phone number to verify your account.
            if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_PHONE_NOT_ADDED), 0)) {
                $this->throwProfileUpdateMessageException();
            }

            $this->logger->notice("check errors");
            // The username or password provided were incorrect.
            if ($message = $this->waitForElement(WebDriverBy::xpath('//*[self::span or self::div][
                    contains(text(), "Email is not valid.")
                    or contains(text(), "The username or password provided were incorrect.")
                    or contains(text(), "Multiple accounts were found for the email address entered")
                    or contains(text(), "Cannot login with email as the user name")
                    or contains(text(), "Your password has expired")
                    or contains(text(), "You do not have an active EDR card. Please register.")
                    or contains(text(), "Your account has been disabled. Please call our contact centre")
                    or contains(text(), "The email address or card number & password combination you have entered is incorrect.")
                    or contains(text(), "The email address or card number &amp; password combination you have entered is incorrect.")
                    or contains(text(), "Cannot login with this email address. Please use your card number instead.")
                    or contains(text(), "Not able to login. Please try again later.")
                    or contains(text(), "Please reset your password by following the instructions sent to")
                    or contains(text(), "We’re unable to log you in.")
                ]
                    | //div[contains(@class, "emailCardNumber__error")]
                    | //*[contains(@id, "mat-error-") and contains(., "Please enter a valid email address")]
                    | //*[contains(@class, "ulp-input-error-message") and contains(., "Wrong email or password")]
                '), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            // There have been too many unsuccessful login attempts. Please contact us to unlock your account.
            if ($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "There have been too many unsuccessful login attempts.")]'), 0)) {
                throw new CheckException('Account temporarily locked. There have been too many unsuccessful login attempts.', ACCOUNT_LOCKOUT);
            }
            // Your account has been locked as a security precaution.
            if ($message = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Your account has been locked as a security precaution.")] | //div[contains(@class, "__error") and contains(., "Your account has been blocked after multiple consecutive login attempts.")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
            }
            // Apologies, we are unable to process your request at the moment
            if ($message = $this->waitForElement(WebDriverBy::xpath('//*[self::span or self::div][
                    contains(text(), "Apologies, we are unable to process your request at the moment")] | //h1[contains(text(), "Change your password")
                    or contains(., "Something went wrong! We were unable to log you in.")
                ]'), 0)
            ) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Access Denied")]'), 0)) {
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
                $this->DebugInfo = "Access Denied";

                return false;
            }

            sleep(1);
            $time = time() - $startTime;
        }// while ($time < $sleep)
        $currentUrl = $this->http->currentUrl();
        $this->logger->debug('[Current URL]: ' . $currentUrl);
        $this->saveResponse();

        if (in_array($this->AccountFields['Login'], ['bradmartin1992@hotmail.com', 'yktay2@gmail.com', 'tjadams88@gmail.com'])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($currentUrl == 'https://www.everyday.com.au/index.html#/my-offers') {
            $logout = $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_LINK_XPATH), 10);

            if (!$logout && $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Woolworths Dollars redeemed") and not(contains(@class, "hide"))]'), 0)) {
                $this->saveResponse();
                $this->http->GetURL(self::REWARDS_PAGE_URL);
                $logout = $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_LINK_XPATH), 10);
            }
            $this->saveResponse();

            if ($logout || $this->http->FindSingleNode(self::LOGOUT_LINK_XPATH)) {
                return true;
            }
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "password__error") and contains(., "Something went wrong! We were unable to log you in.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == "Question") {
            return $this->processSecurityQuestion();
        }

        return false;
    }

    public function Parse()
    {
        $this->logger->debug('[Current URL]: ' . $this->http->currentUrl());

        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
            $this->logger->debug('[Current URL]: ' . $this->http->currentUrl());
            $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_LINK_XPATH), 10);
            $this->saveResponse();
        }

        // Balance - Points earned
        $this->SetBalance($this->http->FindSingleNode(self::LOGOUT_LINK_XPATH));
        // Woolworths Dollars redeemed
        $this->SetProperty('AmountRedeemed', $this->http->FindSingleNode("//div[
            (
                contains(text(), 'Woolworths Dollars redeemed')
                or contains(text(), 'Everyday Rewards points enjoyed')
            )
            and not(contains(@class, 'hide'))]/preceding-sibling::div[not(contains(@class, 'hide'))]"));
        // CURRENT FUEL DISCOUNTS
        $this->SetProperty('FuelDiscounts', $this->http->FindSingleNode("//div[
            (
                contains(text(), 'CURRENT FUEL')
                or contains(text(), 'Current Fuel')
            )
            and not(contains(@class, 'hide'))]/preceding-sibling::div[not(contains(@class, 'hide'))]"));
        // WOOLWORTHS DOLLARS TO CONVERT
        $this->SetProperty('DollarsToConvert', $this->http->FindSingleNode("//div[not(contains(@class, 'hide'))]/div[contains(text(), 'WOOLWORTHS DOLLARS TO CONVERT') and not(contains(@class, 'hide'))]/preceding-sibling::div[not(contains(@class, 'hide'))]"));

        try {
            $this->http->GetURL("https://www.everyday.com.au/index.html#/cards-account");
        } catch (WebDriverException | \Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->driver->switchTo()->defaultContent();
            $this->saveResponse();
            $this->sendNotification('refs #23070 errors with page loading // BS');

            return;
        }
        $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, \'card-number\') and normalize-space(text()) != ""]'), 10);
        $this->saveResponse();
        // Primary card
        $this->SetProperty('CardNumber', $this->http->FindSingleNode("//span[contains(@class, 'card-number')]"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//span[contains(@class, 'card-number')]/preceding-sibling::span[contains(@class, 'name')]")));

        // Expiration Date // refs #6168, https://redmine.awardwallet.com/issues/6168#note-21
        if ($this->Balance <= 0) {
            return;
        }

        $this->logger->info('Expiration date', ['Header' => 3]);
        $this->http->GetURL("https://www.everyday.com.au/index.html#/my-activity?icmpid=wr-banner-trackpoints-myoffersselfservice");
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "date-display")]'), 10);
        $this->saveResponse();
        $transactionsByMonths = $this->http->XPath->query('//div[contains(@class, "row month-name-heading")]');
        $this->logger->debug("Total {$transactionsByMonths->length} months with transactions were found");

        foreach ($transactionsByMonths as $transactionsByMonth) {
            $month = $this->http->FindSingleNode('.//div[contains(@class, "month-name-heading-col")]', $transactionsByMonth);
            $year = $this->http->FindPreg('/\s+(\d{4})$/', false, $month);
            $transactions = $this->http->XPath->query('following-sibling::div[1]//div[div[contains(@class, "date-display")]]', $transactionsByMonth);
            $this->logger->debug("Total {$transactions->length} transactions in {$month} month were found");

            foreach ($transactions as $transaction) {
                $date = $this->http->FindSingleNode('div[contains(@class, "date-display")]', $transaction);
                $this->SetProperty('LastActivity', $date . " " . $year);
                $this->SetExpirationDate(strtotime("+18 months", strtotime($date . " " . $year)));

                break 2;
            }
        }
    }

    protected function processSecurityQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $questionObject = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "We\'ve sent a one time verification code to")] | //p[contains(text(), "We\'ve sent a verification code via")]'), 5);
        $this->saveResponse();

        if (!isset($questionObject)) {
            $this->logger->error("something went wrong");

            return false;
        }

        $question = trim($questionObject->getText());
        $number = $this->http->FindSingleNode('//span[@class = "ulp-authenticator-selector-text"]');

        if ($number) {
            $question = Html::cleanXMLValue($question. " ". $number);
        }

        $this->logger->debug("Question -> {$question}");

        if (!is_numeric($this->Answers[$question] ?? null)) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }
        $this->logger->debug("Entering answer on question -> {$question}...");
        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        try {
            $answerInput = $this->driver->findElement(WebDriverBy::xpath("//form[@action = 'er-login/validate-user']//input[@type='tel'] | //input[@name = 'code']"));
        } catch (NoSuchElementException | \Facebook\WebDriver\Exception\NoSuchElementException $e) {
            $answerInput = null;
        }

        if (empty($answerInput)) {
            $this->logger->error("answerInput not found");
        }

        $this->driver->executeScript('try { document.querySelector(\'input[name="rememberBrowser"]\').checked = true; } catch (e) {}');
        $btn = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Verify') or contains(text(), 'Continue')]"), 0);

        try {
            if (!empty($question) && $answerInput && $btn) {
//            for ($i = 0; $i < strlen($answer); $i++) {
//                $answerInput[$i]->sendKeys($answer[$i]);
//            }
                $answerInput->clear();
                $this->saveResponse();
                $mover = new MouseMover($this->driver);
                $mover->logger = $this->logger;
                $mover->sendKeys($answerInput, $answer, 5);
//                $answerInput->sendKeys($answer);
                $this->saveResponse();
                $this->logger->debug("click 'Submit'...");

                try {
                    $btn->click();
                } catch (UnknownServerException $e) {
                    $this->logger->error("UnknownServerException: " . $e->getMessage(), ['HtmlEncode' => true]);
                }

                $this->logger->debug("find errors...");

                sleep(10);

                $error = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Sorry. That code is incorrect.') or contains(text(), 'Incorrect code. You have')]"), 0);
                $this->saveResponse();

                if ($error) {
                    $this->holdSession();
                    $answerInput->clear();
                    $this->AskQuestion($question, $error->getText(), "Question");
                    $this->logger->error("answer was wrong");

                    return false;
                }

                $currentUrl = $this->http->currentUrl();
                // strange behavior on some accounts
                if ($this->http->currentUrl() == 'https://www.everyday.com.au/index.html#/cards-account') {
                    $this->logger->debug("[Current URL]: " . $currentUrl);
                    $this->http->GetURL(self::REWARDS_PAGE_URL);
                    $currentUrl = $this->http->currentUrl();
                }
                $this->logger->debug("[Current URL]: " . $currentUrl);

                $this->logger->debug("done");
                $this->driver->switchTo()->defaultContent();
                $this->saveResponse();

                $logout = $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_LINK_XPATH), 10);

                if (!$logout && $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Woolworths Dollars redeemed") and not(contains(@class, "hide"))]'), 0)) {
                    $this->saveResponse();
                    $this->http->GetURL(self::REWARDS_PAGE_URL);
                    $logout = $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_LINK_XPATH), 10);
                }
                $this->saveResponse();

                if ($logout || $this->http->FindSingleNode(self::LOGOUT_LINK_XPATH)) {
                    return true;
                }

                return true;
            }
        } catch (WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->DebugInfo = "WebDriverCurlException";

            throw new CheckRetryNeededException(3, 0);
        }

        $this->saveResponse();

        return false;
    }
}
