<?php

class TAccountCheckerViarail extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://reservia.viarail.ca/en/booking/profile/via-preference/activity";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;

        $this->UseSelenium();
        $this->useGoogleChrome();
        $this->useCache();
        $this->http->saveScreenshots = true;
    }

//    public function IsLoggedIn()
//    {
//        $this->http->RetryCount = 0;
//        $this->http->GetURL("https://www.viapreference.com/en/home", [], 20);
//        $this->http->RetryCount = 2;
//
//        if ($this->loginSuccessful()) {
//            return true;
//        }
//
//        return false;
//    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please use a valid email address format', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        /*
        if (!$this->http->ParseForm(null, '//form[contains(@class, "login-form")]')) {
            return $this->checkErrors();
        }
        */

        $loginInput = $this->waitForElement(WebDriverBy::id('loginUsername2'), 10);
        $passwordInput = $this->waitForElement(WebDriverBy::id('loginPassword2'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "login-container")]//button[contains(., "Sign in")]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            $this->logger->error("something went wrong");

            return false;
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        $x = $button->getLocation()->getX();
        $y = $button->getLocation()->getY() - 200;
        $this->driver->executeScript("window.scrollBy($x, $y)");
        $this->saveResponse();
        $button->click();

//        $this->http->SetInputValue('vpr', $this->AccountFields['Login']);
//        $this->http->SetInputValue('pwd', $this->AccountFields['Pass']);
//        $this->http->SetInputValue('login', "Sign In");
//        $this->http->SetInputValue('rememberMe', "on");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindPreg("/(We are doing a quick upgrade and will have the site up and running for you again as fast as we can\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our web site is down for routine maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our web site is down for routine maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg('/currently under maintenance/ims')) {
            throw new CheckException('The site is currently under maintenance. It will be available shortly.', ACCOUNT_PROVIDER_ERROR);
        }
        // ERROR 500
        if ($this->http->FindSingleNode("//b[contains(text(), 'ERROR 500')]")
            //# Proxy Error
            || $this->http->FindSingleNode("//h3[contains(text(), 'Proxy Error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // That message is showing after login
        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'An error occurred with the page you requested.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Membership #:')] | //span[contains(@class, 'alert-message') or contains(@class, 'error-')] | //strong[contains(text(), 'I want to become a VIA Préférence member.')]"), 10);
        $this->saveResponse();

        /*
        if (!$this->http->PostForm()) {
            // provider error
            if ($this->http->Response['code'] == 502) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }
        */

        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");

        if (
            $this->http->FindSingleNode('//button[contains(text(), "Sign Out")]')
            && $currentUrl == 'https://reservia.viarail.ca/en/booking/profile/log-in'
        ) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
            $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Membership #:')] | //span[contains(@class, 'alert-message') or contains(@class, 'error-')] | //strong[contains(text(), 'I want to become a VIA Préférence member.')]"), 10);
            $this->saveResponse();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//span[contains(@class, 'alert-message') or contains(@class, 'error-')]")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, "Invalid email or password.")
                || strstr($message, "The temporary password you entered is not valid or has expired.")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }// if ($message = $this->http->FindSingleNode("//span[contains(@class, 'alert-message') or contains(@class, 'error-')]"))

        if ($this->http->FindSingleNode("//strong[contains(text(), 'I want to become a VIA Préférence member.')]")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        /*
        if ($message = $this->http->FindPreg("/(The VIA Preference membership number or the password you have entered is invalid\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Please enter your seven-digit (numbers only) membership number, i.e. 1234567. If your membership number contains one or more letters, please call 1 888 VIA-PREF (1 888 842-7733).
        if ($message = $this->http->FindPreg("/(Please enter your seven-digit (numbers only) membership number, i\.e\. 1234567\. If your membership number contains one or more letters, please call 1 888 VIA-PREF \(1 888 842-7733\)\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // may be wrong error
        // Username, VIA Préférence # or password are incorrect OR your account has been temporarily locked due to the exceeded amount of permitted attempts.
        if ($message = $this->http->FindPreg("/(Username, VIA Préférence \# or password are incorrect OR your account has been temporarily locked due to the exceeded amount of permitted attempts\.)/ims")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // VIA Préférence # or password are incorrect OR your account has been temporarily locked due to the exceeded amount of permitted attempts
        if ($message = $this->http->FindPreg("/data-error-generic=\"(VIA Préférence # or password are incorrect OR your account has been temporarily locked due to the exceeded amount of permitted attempts\.)/ims")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if (
            $this->http->Response['code'] == 403
            && strstr($this->AccountFields['Pass'], '/*')
            && strstr($this->AccountFields['Pass'], '~l')
            && strstr($this->AccountFields['Pass'], '^')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        */

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Current balance (pts)
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'Current balance:')]/following-sibling::p/span", null, false, '/(.+)\s*point/ims'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[contains(text(), 'Member since')]/preceding-sibling::h2")));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//p[contains(text(), 'Current level:')]/following-sibling::p"));
        // Membership number
        $this->SetProperty("MembershipNumber", $this->http->FindSingleNode("//p[contains(text(), 'Membership #:')]/following-sibling::p"));
        // Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//p[contains(text(), 'Member since')]", null, true, "/:\s*([^<]+)/"));

        // Expiration date  // refs #11229
        $this->waitForElement(WebDriverBy::xpath("(//div[contains(@class, \"accordion-item\")]//div[//p[contains(@class, \"points\") and . != '0 points']]/following-sibling::div[contains(@class, \"activity-accordion\")]/p)[1]"), 5);
        $this->saveResponse();

        if ($lastActivity = $this->http->FindSingleNode('(//div[contains(@class, "accordion-item")]//div[//p[contains(@class, "points") and . != \'0 points\']]/following-sibling::div[contains(@class, "activity-accordion")]/p)[1]')) {
            $this->SetProperty("LastActivity", $lastActivity);
            $this->SetExpirationDate(strtotime("+3 year", strtotime($lastActivity)));
        }

        $this->http->GetURL("https://reservia.viarail.ca/en/booking/profile/bookings");
        $this->waitForElement(WebDriverBy::xpath("//table[caption[contains(text(), \"Upcoming trips\")]]//tr[td]"), 5);
        $this->saveResponse();
        $its = $this->http->FindNodes('//table[caption[contains(text(), "Upcoming trips")]]//tr[td]');

        if (count($its) > 0) {
            $this->sendNotification("itineraries were found: " . count($its));
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//p[contains(text(), 'Membership #:')]")) {
            return true;
        }

        return false;
    }
}
