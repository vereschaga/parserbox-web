<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerPcpoints extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const WAIT_TIMEOUT = 7;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
//        $this->useCache();
        $this->usePacFile(false);
        $this->http->saveScreenshots = true;

//        $this->setProxyGoProxies(null, 'ca');
//        $this->setProxyMount();

        $this->setProxyBrightData();
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
        $this->seleniumOptions->userAgent = null;

        /*
        if ($this->attempt == 1) {
            $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
            $this->seleniumOptions->addHideSeleniumExtension = false;
            $this->seleniumOptions->userAgent = null;
        } else {
            $this->useFirefox();
            $this->setKeepProfile(true);

            $request = FingerprintRequest::firefox();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 5;
            $request->platform = 'Linux x86_64';
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            $this->http->setUserAgent($fingerprint->getUseragent());
        }
        */
    }

    public function LoadLoginForm()
    {
        $this->Answers = [];
        $this->http->removeCookies();

        try {
            $this->http->GetURL("https://www.pcoptimum.ca/login");
        } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        } catch (UnknownServerException | Facebook\WebDriver\Exception\UnknownErrorException | UnexpectedJavascriptException | Facebook\WebDriver\Exception\JavascriptErrorException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        } catch (NoSuchWindowException | UnexpectedAlertOpenException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 1);
        }
        $login = $this->waitForElement(WebDriverBy::id('email'), 10);

        if (!$login) {
            $this->callRetries();

            $this->saveResponse();
            sleep(5);
            $login = $this->waitForElement(WebDriverBy::id('email'), 5);
        }

        $pass = $this->waitForElement(WebDriverBy::id('password'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath("//form[@id = 'login']//button[contains(@class, 'button') and contains(., 'Sign In')]"), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$btn) {
            $this->logger->error("something went wrong");

            if ($message = $this->http->FindSingleNode('//span[contains(text(), "Oops, something went wrong")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->waitForElement(WebDriverBy::xpath("
                    //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                    | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                    | //span[contains(text(), 'Loading')]
                "), 0)
            ) {
                throw new CheckRetryNeededException(2, 1);
            }

            try {
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            } catch (UnknownServerException $e) {
                $this->logger->error("exception: " . $e->getMessage());

                throw new CheckRetryNeededException(3, 0);
            }

            $success = $this->waitBalance(0);

            if ($success) {
                try {
                    $this->http->GetURL("https://www.pcoptimum.ca/dashboard");
                } catch (TimeOutException $e) {
                    $this->logger->error("TimeOutException exception: " . $e->getMessage());
                    $this->driver->executeScript('window.stop();');
                    $this->saveResponse();
                }

                if ($this->waitBalance()) {
                    $this->markProxySuccessful();

                    return true;
                }

                $login = $this->waitForElement(WebDriverBy::id('email'), 5);
                $pass = $this->waitForElement(WebDriverBy::id('password'), 0);

                $btn = $this->waitForElement(WebDriverBy::xpath("//form[@id = 'login']//button[contains(@class, 'button') and contains(., 'Sign In')]"), 0);
                $this->saveResponse();
            }

            if (!$login || !$pass || !$btn) {
                return false;
            }
        }
        $login->clear();
        $login->sendKeys($this->AccountFields['Login']);
        $pass->clear();
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->driver->executeScript('setTimeout(function(){
            document.getElementsByClassName(\'button--theme-base\')[0].click();
        }, 500)');

        sleep(4);

        if ($btn = $this->waitForElement(WebDriverBy::xpath("//form[@id = 'login']//button[contains(@class, 'button') and contains(., 'Sign In')]"), 0)) {
            $btn->click();
        }

        return true;
    }

    public function Login()
    {
        $startTime = time();
        $time = time() - $startTime;
        $sleep = 40;
        $this->saveResponse();

        while ($time < $sleep) {
            $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");
            // look for logout link
            if ($this->waitBalance(0)) {
                $this->markProxySuccessful();

                return true;
            }
            $this->logger->notice("check errors");
            // Invalid credentials
            if ($error = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'form-error__error-text' or contains(@class, 'form-message--error')]"), 0)) {
                $message = $error->getText();
                $this->logger->error($message);

                if (
                    strstr($message, "Hmm something doesn't look right.")
                    || strstr($message, "Your email or password was incorrect.")
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    strstr($message, "Our apologies! An unexpected error has occurred. Please try refreshing the page, or contact us if the problem persists.")
                    || strstr($message, "Our apologies, we're having trouble connecting with the server. Please try refreshing the page, or contact us if the problem persists.")
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                // request has been blocked
                if (strstr($message, "Oops, something went wrong. Please try again. (429)")) {
                    $this->markProxyAsInvalid();
                    $this->DebugInfo = $message;

                    throw new CheckRetryNeededException(2, 0);
                }

                $this->DebugInfo = $message;

                return false;
            }
            // Invalid email address
            if ($error = $this->waitForElement(WebDriverBy::xpath("//label[contains(@class, 'text-group__error')]"), 0)) {
                $message = $error->getText();
                $this->logger->error($message);

                if (
                    strstr($message, "Invalid email address")
                    || strstr($message, "Please enter a valid email address.")
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $message == "Oops, something went wrong. Please try again. (429)"
                ) {
                    $this->markProxyAsInvalid();

                    throw new CheckRetryNeededException(2, 0);

                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }
            // Create Your PC Optimum Account
            if ($this->waitForElement(WebDriverBy::xpath("
                    //div[span[contains(text(), 'Looks like you need to create your online')]]
                    | //h2[span[contains(text(), 'We just need a few more details to complete your ')]]
                "), 0)
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // Too many failed login attempts
            if ($message = $this->waitForElement(WebDriverBy::xpath('//div[span[contains(text(), "Too many failed login attempts, please try again in")]]'), 0)) {
                throw new CheckException($this->http->FindPreg("/([^.]+)/", false, $message->getText()), ACCOUNT_INVALID_PASSWORD);
            }
            // You've exceeded the number of attempts allowed. Please reset your password.
            if ($message = $this->waitForElement(WebDriverBy::xpath('//div[span[contains(text(), "You\'ve exceeded the number of attempts allowed.")]]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            // Our apologies, an unknown error has occurred
            if ($message = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'system-message__message') and span[contains(text(), 'Our apologies, an unknown error has occurred')]] | //div[contains(@class, 'system-message__message')]//span[contains(text(), 'Sorry, something went wrong')]"), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            // Account Locked
            if ($message = $this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Account Locked')]"), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
            }
            /**
             * As part of our ongoing security enhancements, we’ve updated our password policy.
             * Customers who participate in any service that uses PC™ id will be required to create a longer and stronger password.
             */
            if ($this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Update your password')]"), 0)) {
                $this->throwProfileUpdateMessageException();
            }
            /**
             * Check your inbox.
             *
             * Your verification code will arrive in your inbox shortly.
             * This extra step shows it's really you trying to access your account.
             */
            if ($this->waitForElement(WebDriverBy::xpath('//label[contains(., "Enter the code")]'), 0)) {
                $this->markProxySuccessful();

                return $this->processOTP();
            }
            /**
             * Something went wrong.
             * Sorry, this page doesn't exist!
             */
            if ($this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'not-found--somethingWrong')]/span[contains(text(), 'Something went wrong.')]"), 0)
                || $this->waitForElement(WebDriverBy::xpath('/span[contains(text(), "Sorry, this page doesn\'t exist!")]'), 0)) {
                throw new CheckRetryNeededException(2, 1);
            }

            sleep(1);
            $this->saveResponse();

            $this->callRetries();

            $time = time() - $startTime;
        }// while ($time < $sleep)

        $success = $this->waitBalance(20);

        if ($success) {
            return true;
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
            return $this->processOTP();
        }

        return true;
    }

    public function Parse()
    {
        try {
            if ($link = $this->waitForElement(WebDriverBy::xpath('//a[@id = "desktop-menu-points"]'), 5)) {
                $link->click();
            } else {
                $this->http->GetURL("https://www.pcoptimum.ca/points");
            }
            $balanceXpath = "
                //div[contains(@class, 'point-summary__inner--balance')]/h2/span
                | //p[span[contains(text(), 'You don’t have any points, but that can change right away by using your personalized offers.')]]
                | //p[span[contains(text(), '’s nice to see you too. Your points balance is at 0, browse your offers feed to start earning points.')]]
            ";
            $balance = $this->waitForElement(WebDriverBy::xpath($balanceXpath), 10);
            $this->saveResponse();

            if ($balance && $balance->getText() == 'You don’t have any points, but that can change right away by using your personalized offers.') {
                $this->logger->notice("You don’t have any points");
            } else {
                sleep(3);
                $this->waitForElement(WebDriverBy::xpath($balanceXpath), 0);
                $this->saveResponse();
                $balance1 = $this->http->FindSingleNode("//div[contains(@class, 'point-summary__inner--balance')]/h2/span");

                sleep(2);
                $this->waitForElement(WebDriverBy::xpath($balanceXpath), 0);
                $this->saveResponse();
                $balance2 = $this->http->FindSingleNode("//div[contains(@class, 'point-summary__inner--balance')]/h2/span");
                // refs #18289
                if ($balance1 != $balance2) {
                    $this->logger->error("balance may be incorrect");

                    return;
                }
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        } catch (WebDriverCurlException | NoSuchWindowException | WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(2, 0);
        }

        // Current Balance
        if (!$this->SetBalance($this->http->FindSingleNode("//div[contains(@class, 'point-summary__inner--balance')]/h2/span"))) {
            if ($this->http->FindSingleNode("//p[span[contains(text(), 'You don’t have any points, but that can change right away by using your personalized offers.')]]")) {
                $this->SetBalanceNA();
            } elseif ($this->http->FindSingleNode("//p[span[contains(text(), 's nice to see you too. Your points balance is at 0, browse your offers feed to start earning points.')]]")) {
                $this->SetBalance(0);
            }
        }

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && ($message = $this->http->FindSingleNode('//div[contains(@class, "")]/span[contains(text(), "Our apologies, an unknown error has occurred")]'))
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Redeemable value
        $this->SetProperty("RedeemableValue", $this->http->FindSingleNode("//div[contains(@class, 'point-summary__inner--redeemable')]/h2/span"));
        // Expiration Date  // refs #8909
        $lastActivity = $this->http->FindSingleNode("//ul[contains(@class, 'point-events__list')]/li[1]//div[@class = 'point-event__subtitle' and position()> 1]", null, true, "/^\w+\s*\•\s*(\w+\s*\d+)/");
        // Last Activity
        if ($lastActivity) {
            $this->SetProperty("LastActivity", $lastActivity);

            if (strtotime($lastActivity)) {
                $this->SetExpirationDate(strtotime("+2 year", strtotime($lastActivity)));
            }
        }// if ($lastActivity)

        if ($menu = $this->waitForElement(WebDriverBy::xpath('//span[@id = "desktop-menu-account"]'), 0)) {
            $menu->click();
        }
        $this->saveResponse();

        if ($link = $this->waitForElement(WebDriverBy::xpath('//a[@id = "desktop-menu-account-settings"]'), 1)) {
            $link->click();
        } else {
            try {
                $this->http->GetURL("https://www.pcoptimum.ca/account/settings");
                $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Full Name')]"), 5);
                $this->saveResponse();
            } catch (NoSuchDriverException | UnknownServerException | NoSuchDriverException | NoSuchWindowException | Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            }
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[span[contains(text(), 'Full Name')]]/following-sibling::span[@class = 'account-setting__value']")));
    }

    protected function processOTP()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $this->saveResponse();
//        $question = $this->waitForElement(WebDriverBy::xpath('//label[span[contains(text(), "Enter the code")]]'), 5);
        $question = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "__displayName")]'), 0);
        $this->saveResponse();

        if (!$question) {
            $this->logger->error("something went wrong");

            return false;
        }

        $device = $question->getText();

        $question = "Please enter verification code which was sent to your email: {$device}";

        if (!strstr($device, '@')) {
            $question = "Please enter verification code which was sent to your phone: {$device}";
        }

        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Verify")]'), 10);
        $otpFiled = $this->waitForElement(WebDriverBy::xpath('//input[@name = "code"]'), 0);

        if (!$question || !$button || !$otpFiled) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return false;
        }
        $this->holdSession();
        $this->saveResponse();

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, 'Question');

            return false;
        }
        // Don't ask again on this computer
        $remember = $this->waitForElement(WebDriverBy::xpath('//label[@id = "trustedDevice__label"]'), 0);

        if ($remember) {
            $remember->click();
        }

        $otpFiled->clear();
        $otpFiled->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);
        $this->logger->debug("click button...");
        $button->click();
        /**
         * The code you provided is incorrect.
         *
         * Your max attempts have been reached. Previous codes are no longer active.
         */
        $error = $this->waitForElement(WebDriverBy::xpath("//span[
                contains(text(), 'The code you provided is incorrect.')
                or contains(text(), 'Your max attempts have been reached')
                or contains(text(), 'The code is incorrect. Please enter the correct code')
                or contains(text(), 'Please enter numbers only.')
            ]
            | //*[self::span or self::div][contains(text(), 'Oops, something went wrong. Please try again. (429')]
            | //div[contains(text(), 'The code is incorrect.')]
        "), 5);
        $this->saveResponse();

        if ($error) {
            $this->logger->notice("resetting answers");

            if (strstr($error->getText(), 'Oops, something went wrong. Please try again. (429')) {
                throw new CheckException($error->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            $otpFiled->clear();
            $this->holdSession();
            $this->AskQuestion($question, $error->getText(), 'Question');

            return false;
        }
        $this->waitBalance();
        $this->logger->debug("success");

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function waitBalance($delay = 10)
    {
        $this->logger->notice(__METHOD__);
        $balanceXpath = "
            //div[span[contains(text(), 'Your points balance')]]
            | //div[span[contains(text(), 'Points balance')]]
            | //div[contains(@class, 'points-overview-balance')]
            | //div[contains(@class, 'header-points__points-balance')]//span[contains(text(), 'Your points balance')]
            | //div[contains(@class, 'header-points__empty-state')]//span[contains(text(), 'Your points balance')]
            | //span[contains(text(), 'points, redeemable for')]
            | //p[span[contains(text(), 'You don’t have any points, but that can change right away by using your personalized offers.')]]
            | //p[span[contains(text(), 's nice to see you too. Your points balance is at 0, browse your offers feed to start earning points.')]]
        ";
        $success = $this->waitForElement(WebDriverBy::xpath($balanceXpath), $delay);
        $this->saveResponse();

        if ($success) {
            return true;
        }

        return false;
    }

    private function callRetries()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//span[
                    contains(text(), "This site can’t be reached")
                    or contains(text(), "This page isn’t working")
                    or contains(text(), "This site can’t be reached")
                ]')
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(2, 1);
        }
    }
}
