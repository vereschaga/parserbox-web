<?php

class TAccountCheckerShoprite extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        /*
        $this->useFirefox();
        $this->setKeepProfile(true);
        $this->disableImages();
        */

        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->useCache();
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://profile.shoprite.com/ShopRite/EditProfile");

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "Email"]'), 10);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In")]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            return $this->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //span[@id = "ProfileSummaryFsn"]
            | //div[contains(@class, "mwg-validation-message")]
            | //span[contains(@class, "field-validation-error")]
            | //input[@value = "Verify you are human"] | //div[@class = "hcaptcha-box"]/iframe
        '), 40);
        $this->saveResponse();

        if ($this->cloudFlareworkaround()) {
            $this->waitForElement(WebDriverBy::xpath('
                //span[@id = "ProfileSummaryFsn"]
                | //div[contains(@class, "mwg-validation-message")]
                | //span[contains(@class, "field-validation-error")]
                | //input[@value = "Verify you are human"] | //div[@class = "hcaptcha-box"]/iframe
            '), 10);
            $this->saveResponse();
        }

        if ($message = $this->http->FindSingleNode("//*[self::div or self::span][contains(@class, 'mwg-validation-message')]/ul/li | //span[contains(@class, 'field-validation-error')]")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Incorrect email or password. Try again or select "Forgot your password?"')
                || strstr($message, 'The Email field is not a valid e-mail address.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Your Customer Profile can not be found')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//span[@id = "ProfileSummaryAccountNo"]')) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//span[@id = 'ProfileSummaryFirstName']") . " " . $this->http->FindSingleNode("//span[@id = 'ProfileSummaryLastName']")));
        // Account Number
        $this->SetProperty('CardNumber', $this->http->FindSingleNode('//span[@id = "ProfileSummaryAccountNo"]'));
        // Home Store
        $this->SetProperty('HomeStore', $this->http->FindSingleNode('//span[@id = "ProfileSummaryStore"]'));

        if (!empty($this->Properties['Name']) && !empty($this->Properties['CardNumber'])) {
            $this->SetBalanceNA();
        }
    }

    /* @deprecated */
    private function cloudFlareworkaround()
    {
        $this->logger->notice(__METHOD__);

        $res = false;

        if ($verify = $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human']"), 0)) {
            $verify->click();
            $res = true;
        }

        if ($iframe = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'hcaptcha-box']/iframe"), 5)) {
            $this->driver->switchTo()->frame($iframe);
            $this->saveResponse();

            if ($captcha = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'ctp-checkbox-container']/label"), 10)) {
                $this->saveResponse();
                $captcha->click();
                $this->logger->debug("delay -> 15 sec");
                $this->saveResponse();
                sleep(15);

                $this->driver->switchTo()->defaultContent();
                $this->saveResponse();
                $res = true;
            }
        }

        return $res;
    }
}
