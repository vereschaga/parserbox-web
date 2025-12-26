<?php

class TAccountCheckerJunkySelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_100);
        $this->setKeepProfile(true);

        $this->disableImages();
        $this->useCache();

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://www.activejunky.com/");

        if ($signIn = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Sign In")]'), 5)) {
            $this->saveResponse();
//            $signIn->click();
            $this->driver->executeScript('
                try {
                    document.querySelector(\'#sign_up button[aria-label="Close"]\').click();
                } catch ($e) {}
                document.querySelector(\'a[data-target="#authenticationModal"], a.login-button, a[href="/login"]\').click();
            ');
            sleep(3);
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "member[email]" or @name="email"]'), 3);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "member[password]" or @name="password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//input[@value = "Sign In"] | //button[div[contains(text(), "Sign In")]]'), 0);

        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            // instead IsLoggedIn
            if ($lifetime = $this->http->FindSingleNode("//div[contains(text(), 'Lifetime Cash Back')]/following-sibling::div[1]")) {
                $this->logger->notice("Lifetime Cash Back was found");

                return true;
            }

            return false;
        }

        $this->logger->debug('set credentials');
//        $this->driver->executeScript('
//            var form = $(\'#authenticationForm\');
//            $(\'input[name="member[email]"]\', form).val("' . $this->AccountFields['Login'] . '");
//            $(\'input[name="member[password]"]\', form).val("' . str_replace('"', '\"', $this->AccountFields['Pass']) . '");
//        ');
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->logger->debug('click btn');
//        $this->driver->executeScript('
//            var form = $(\'#authenticationForm\');
//            $(\'input[type="submit"]\', form).click();
//        ');
        $button->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //a[text() = "Log Out"]
            | //span[contains(@class, "signInError")]
            | //span[contains(@class, "truncate")]
            | //div[contains(@class, "bg-error-red")]//span
        '), 10);
        $this->saveResponse();

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[contains(@class, "signInError")] | //div[contains(@class, "bg-error-red")]//span')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Invalid email/password')
                || $message == 'Account deactivated, please contact support'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'There has been a communication error')) {
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
                $this->DebugInfo = self::ERROR_REASON_BLOCK;

                return false;
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->AccountFields['Login'] == 'timothywerickson@gmail.com') {
            throw new CheckException("Invalid email/password", ACCOUNT_INVALID_PASSWORD);
        }

        /*
        // Invalid email/password
        if (false !== $response && !empty($response->errors) && $response->errors == 'Invalid email/password') {
            throw new CheckException($response->errors, ACCOUNT_INVALID_PASSWORD);
        }
        // Account login temporarily locked because of too many login attempts. Try logging in again later or reset your password.
        if (false !== $response && !empty($response->errors) && $response->errors == 'Account login temporarily locked because of too many login attempts. Try logging in again later or reset your password.') {
            throw new CheckException($response->errors, ACCOUNT_LOCKOUT);
        }
        // Please try again. If this persists, please contact support.
        if (false !== $response && !empty($response->errors) && $response->errors == 'Please try again. If this persists, please contact support.') {
            throw new CheckRetryNeededException(3, 0);
        }
        */
        /*
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        */
        // We're sorry, but something went wrong (500)
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "We\'re sorry, but something went wrong")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function internalRedirect($url)
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (UnknownServerException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 5);
        }
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.activejunky.com/my/profile");
        $this->waitForElement(WebDriverBy::xpath('//h4[contains(text(), "Current Payout Balance")]'), 10);
        sleep(2);
        $this->saveResponse();

        // -$0.28
        $this->SetBalance(str_replace('$', '', $this->http->FindSingleNode('//h4[normalize-space(text())="Current Payout Balance"]/../following-sibling::div//h1')));

        if ($this->http->FindSingleNode("//text()[contains(.,'Pending Orders')]")) {
            $this->sendNotification('check pending // MI');
        }

        /*$cashBackPendingXpath = "//div[h4[contains(text(), "Current Payout Balance")]]/following-sibling::div/h1';
        // Balance - Current Payout Cash
        // if exist only sections: Previous Payout, Current Payout
        $cashBackPending = str_replace('$', '', $this->http->FindSingleNode($cashBackPendingXpath, null, false, "/(\-?\\$.+)/"));
        $pendingOrdersCashback = str_replace('$', '', $this->http->FindSingleNode('//h4[normalize-space(text()) = "Current Payout:"]/following-sibling::h4[contains(., "Pending Orders Cashback:")]/span', null, false, "/(\-?\\$.+)/"));

        if ($cashBackPending !== null || $pendingOrdersCashback !== null) {
            $cashBackPending = trim(str_replace(',', '', $cashBackPending));
            $this->logger->debug("cashBackPending: {$cashBackPending}");
            $this->logger->debug("pendingOrdersCashback: {$pendingOrdersCashback}");
            $pendingOrdersCashback = trim($pendingOrdersCashback);
            $this->SetBalance(($cashBackPending ?: 0) + ($pendingOrdersCashback ?: 0));
        }*/

        // Current Payout Date
        $this->SetProperty('CurrentPayoutDate',
            $this->http->FindSingleNode('//div[h4[contains(text(), "Current Payout Balance")]]/following-sibling::div/div/h4', null, true, "/\:\s*(.+)/")
        );
        // Previous Payout
        $this->SetProperty('PrevPayoutDate', $this->http->FindSingleNode('//div[h4[contains(text(), "Previous Period")]]/following-sibling::div/div//h4', null, true, "/\:\s*(.+)/"));
        // Cash Paid
        $this->SetProperty('PrevPayoutCash', $this->http->FindSingleNode('//div[h4[contains(text(), "Previous Period")]]/following-sibling::div/div[contains(., "Paid")]/text()[1]'));
        // Name
        $this->SetProperty('Name', beautifulName(trim($this->http->FindSingleNode('//h1[contains(text(), "Hello, ")]', null, true, "/Hello,\s*(.+)/"))));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//a[text() = "Log Out"] | //span[contains(@class, "truncate")]')) {
            return true;
        }

        return false;
    }
}
