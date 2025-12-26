<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerUobbank extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->setProxyMount();
        $this->useFirefox(SeleniumFinderRequest::FIREFOX_59);
        $this->setKeepProfile(true);
//        $this->disableImages();
//        $this->useCache();
        $this->http->saveScreenshots = true;
    }

    /*
    public function IsLoggedIn() {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://pib.uob.com.sg/Rewards/2FA/landing.do', [], 20);
        $this->http->RetryCount = 2;
        if ($this->loginSuccessful())
            return true;

        return false;
    }
    */

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        try {
            try {
                $this->http->GetURL('https://pib.uob.com.sg/Rewards/');
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $this->driver->executeScript('window.stop();');
            }

            $formLink = $this->waitForElement(WebDriverBy::id('lnkOpenLoginDialog'), 10);
            $this->saveResponse();

            if ($this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "small-overlay opaque")]'), 0)) {
                $delay = 5;
                $this->logger->notice("delay -> {$delay}");
                sleep($delay);

                $this->waitFor(function () {
                    try {
                        return !$this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "small-overlay opaque")]'), 0);
                    } catch (NoSuchElementException $e) {
                        $this->logger->error("NoSuchElementException: " . $e->getMessage());

                        return true;
                    }
                }, 60);
                $this->saveResponse();

                $this->logger->notice("delay -> {$delay}");
                sleep($delay);
                $formLink = $this->waitForElement(WebDriverBy::id('lnkOpenLoginDialog'), 5);
                $this->saveResponse();
            }

            if (!$formLink) {
                $this->logger->error("something went wrong");

                return $this->checkErrors();
            }
            $formLink->click();
        } catch (UnknownServerException | NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException();
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $error = $this->driver->switchTo()->alert()->getText();
            $this->logger->debug("alert -> {$error}");
            $this->driver->switchTo()->alert()->accept();
            $this->logger->debug("alert, accept");
        }// catch (UnexpectedAlertOpenException $e)
        catch (NoAlertOpenException $e) {
            $this->logger->debug("no alert, skip");
        }

        $login = $this->waitForElement(WebDriverBy::xpath('//input[contains(@id, "-user")]'), 10);
        $pass = $this->waitForElement(WebDriverBy::xpath('//input[contains(@id, "-pass")]'), 0);
        $sbm = $this->waitForElement(WebDriverBy::xpath('//button[span[contains(text(), "Login")]]'), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$sbm) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }
        $this->logger->debug("set login");
        $login->sendKeys($this->AccountFields['Login']);
        $this->logger->debug("set pass");
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->logger->debug("click btn");
        $this->saveResponse();
        sleep(1);
        $this->logger->debug("delay");
        $this->saveResponse();

        if ($this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "small-overlay opaque")]'), 0)) {
            $delay = 5;
            $this->logger->notice("delay -> {$delay}");
            sleep($delay);

            $this->waitFor(function () {
                try {
                    return !$this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "small-overlay opaque")]'), 0);
                } catch (NoSuchElementException $e) {
                    $this->logger->error("NoSuchElementException: " . $e->getMessage());

                    return true;
                }
            }, 40);
            $this->saveResponse();

            $this->logger->notice("delay -> {$delay}");
            sleep($delay);
            $this->saveResponse();
        }

        $sbm->click();

        return true;
    }

    public function Login()
    {
        $sleep = 20;
        $startTime = time();

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
            // invalid credentials
            if ($error = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "display-error")] | //p[@class = "msg-error"]'), 0)) {
                $message = $error->getText();
                $this->logger->error($message);
                /**
                 * Login failed. Please enter a valid Username/Password and submit again
                 * If you have forgotten your Username/Password, please click here.
                 */
                if (
                    strstr($message, 'Login failed. Please enter a valid Username/Password')
                    || strstr($message, '8 to 16 alphanumeric - at least 1 alphabet')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Login page has expired due to inactivity. Please login again.
                if (strstr($message, 'Login page has expired due to inactivity. Please login again.')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // Your Personal Internet Banking access is locked as you have exceeded the maximum number of login retries allowed. To reactivate your access, please reset your password here
                if (strstr($message, 'Your Personal Internet Banking access is locked as you have exceeded the maximum number of login retries allowed.')) {
                    throw new CheckException("Your Personal Internet Banking access is locked as you have exceeded the maximum number of login retries allowed.", ACCOUNT_LOCKOUT);
                }
            }// if ($message = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "display-error")]'), 0))

            if ($this->loginSuccessful()) {
                return true;
            }

            if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "2FA Login")]'), 0)) {
                unset($this->Answers['ENTER 6-DIGIT OTP']);

                return $this->processOTP();
            }
            /**
             * We have sent a notification to your Mighty Secure-enabled device. Ensure you have a stable connection to receive it.
             *
             * Confirm the request within the next 60 seconds to proceed.
             */
            if ($this->waitForElement(WebDriverBy::xpath('
                    //p[
                    contains(text(), "Confirm the request within the next")
                    or contains(text(), "1. Open UOB Mighty and select SECURE")
                    or contains(., "We have sent a notification to your digital token-enabled device.")
                    or contains(., "We are unable to receive your confirmation.")
                ]'), 0)
            ) {
                return $this->processDeviceOTP();
            }

            sleep(1);
            $this->saveResponse();
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        switch ($step) {
            case "Question":
                return $this->processOTP();

                break;

            case "DeviceOTP":
                return $this->processDeviceOTP();

                break;
        }// switch ($step)

        return true;
    }

    public function Parse()
    {
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        // Balance - UNI$
        $this->SetBalance($this->http->FindSingleNode('//li[contains(@class, "amt")]/span'));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//span[contains(@class, "username")]')));
        // Expiring Balanace
        $this->SetProperty('ExpiringBalance', $this->http->FindSingleNode('//div[@class = "expiry"]', null, true, "/(.+)\s+expire/"));
        $exp = str_replace("'", "", $this->http->FindSingleNode('//div[@class = "expiry"]', null, true, "/expires?\s*([^<]+)/"));

        if (strtotime($exp)) {
            $this->SetExpirationDate(strtotime($exp));
        }
    }

    protected function processDeviceOTP()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $this->waitFor(function () {
            return
                $this->waitForElement(WebDriverBy::xpath("//input[contains(@class, 'submit-btn')]"), 0)
                || $this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'You have been logged out.')]"), 0)
                || $this->waitForElement(WebDriverBy::xpath("//a[span[contains(text(), 'Logout')]]"), 0, false);
        }, 60);
        $submit = $this->waitForElement(WebDriverBy::xpath("//input[contains(@class, 'submit-btn')]"), 0);
        $otpFiled = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "submission-otp")]/input[@type = "password"]'), 0);
        $this->saveResponse();

        if (!$submit || !$otpFiled) {
            if ($this->loginSuccessful()) {
                return true;
            }

            $this->logger->error("something went wrong");

            // provider bug fix
            if ($this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'You have been logged out.')]"), 0)) {
                throw new CheckRetryNeededException(2, 1);
            }

            return false;
        }
        $question = 'Enter One-Time Password (OTP) generated by Mighty Secure-enabled device'; /*review*/
        $this->holdSession();
        $this->saveResponse();

        if (!isset($this->Answers[$question])) {
            $this->AskQuestion($question, null, 'DeviceOTP');

            return false;
        }
        $otpFiled->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);
        $this->logger->debug("click button...");
        $submit->click();
        sleep(5);
        /**
         * The Token-OTP verification has failed due to invalid/expired OTP entered.
         * Please request for another Token-OTP and try again.
         */
        $error = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'The Token-OTP verification has failed due to invalid/expired OTP entered.')]"), 0);
        $this->saveResponse();

        if ($error) {
            $this->logger->notice("resetting answers");
            $this->AskQuestion($question, $error->getText(), 'DeviceOTP');

            return false;
        }
        $this->logger->debug("success");
        // Access is allowed
        $this->loginSuccessful();

        // provider bug fix
        if (
            !$this->http->FindSingleNode('//li[contains(@class, "amt")]/span')
            && $this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'You have been logged out.')]"), 0)
        ) {
            $this->logger->error("something went wrong");

            throw new CheckRetryNeededException(2, 1);
        }

        return true;
    }

    protected function processOTP()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $this->saveResponse();
        $question = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "selected")]//span[contains(text(), "ENTER 6-DIGIT OTP")]'), 5);
        $this->saveResponse();

        if (!$question) {
            $question = $this->http->FindSingleNode('//div[contains(@class, "selected")]//span[contains(text(), "ENTER 6-DIGIT OTP")]');
        }

        if (!$question) {
            $this->logger->error("something went wrong");

            return false;
        }
        $question = $question->getText();

        $otpField = $this->waitForElement(WebDriverBy::xpath('//form[contains(@id, "form-otp-sms-")]//input[@type="password"]'), 0);

        if ($otpField) {
            $requestBtn = $this->waitForElement(WebDriverBy::xpath('//button[span[contains(text(), "Click to request OTP via SMS") or contains(text(), "Get Another SMS-OTP")]]'), 0);
            $button = $this->waitForElement(WebDriverBy::xpath('//button[span[contains(text(), "Submit")]]'), 0);

            if (!$requestBtn || !$button) {
                $this->logger->error("btn not found");
                $this->saveResponse();

                return false;
            }// if (!$requestBtn || !$btn)

            if (!isset($this->Answers[$question])) {
                $requestBtn->click();
            }
        } else {
            $otpField = $this->waitForElement(WebDriverBy::xpath('//form[contains(@id, "form-otp-token-")]//input[@type="password"]'), 0);
        }

        if (!$question || !$otpField) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return false;
        }
        $this->holdSession();
        $this->saveResponse();

        if (!isset($this->Answers[$question])) {
            $this->AskQuestion($question, null, 'Question');
            $this->saveResponse();

            return false;
        }
        $otpField->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);
        $this->logger->debug("click button...");

        $button = $this->waitForElement(WebDriverBy::xpath('//button[span[contains(text(), "Submit")] and not(@disabled)]'), 10);

        if (!$button) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return false;
        }
        $button->click();
        sleep(5);
        /**
         * The SMS-OTP verification has failed due to invalid/expired OTP entered.
         * Please click on "Get Another SMS-OTP" and try again. For assistance, please call 1800 222 2121.
         */
        $error = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'The SMS-OTP verification has failed due to invalid/expired OTP entered.')]"), 0);
        $this->saveResponse();

        if ($error) {
            $this->logger->notice("resetting answers");
            $this->AskQuestion($question, $error->getText(), 'Question');

            return false;
        }
        $this->logger->debug("success");
        // Access is allowed
        $this->loginSuccessful();

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $logout = $this->waitForElement(WebDriverBy::xpath("//a[span[contains(text(), 'Logout')]]"), 5, false);
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        return false;
    }
}
