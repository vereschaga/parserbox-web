<?php

class TAccountCheckerSurveysavvySelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];
        $this->setScreenResolution($resolutions[array_rand($resolutions)]);
        $this->useChromium();
        $this->http->FilterHTML = false;
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.surveysavvy.com/member/home", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL('https://www.surveysavvy.com/user/login');
        $loginInput = $this->waitForElement(WebDriverBy::id('email-address'), 0);
        $this->saveResponse();

        if (!$loginInput) {
            return $this->checkErrors();
        }

        $passwordInput = $this->waitForElement(WebDriverBy::id('password'), 0);
        $loginButton = $this->waitForElement(WebDriverBy::xpath('//input[@value="Log In"]'), 0);

        if (empty($loginInput) || empty($passwordInput) || empty($loginButton)) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields["Login"]);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $loginButton->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Service is temporarily down
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "is temporarily down")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# SurveySavvy is currently under maintenance
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'SurveySavvy is currently under maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // This Site Is Undergoing Scheduled Maintenance
        if ($this->http->Response['code'] == 503) {
            throw new CheckException('This Site Is Undergoing Scheduled Maintenance', ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }
        //# Sorry, unrecognized username or password.
        if ($message = $this->http->FindPreg("/(Sorry\, unrecognized username or password\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account is currently closed
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Your account is currently closed')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Your email address or password is invalid')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // This request was blocked by the security rules
        if ($message = $this->http->FindPreg("/(?:Request unsuccessful\. Incapsula incident ID: \d+-\d+|Sorry, too many failed login attempts from your IP address\. This IP address is temporarily blocked\.)/")
        ) {
            $this->logger->error($message);
            $this->DebugInfo = $message;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Member ID')]/following-sibling::h2[1]"), 10);
        $this->saveResponse();
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[contains(text(), 'Welcome')]/following-sibling::h2[1]")));
        // Member ID
        $this->SetProperty("MemberID", $this->http->FindSingleNode("//p[contains(text(), 'Member ID')]/following-sibling::h2[1]"));
        // Balance - Available Balance
        $this->SetBalance($this->http->FindSingleNode("//p[strong[contains(text(), 'Available Balance')]]/following-sibling::p"));
        // Direct Referrals
        $this->SetProperty("DirectReferrals", $this->http->FindSingleNode("//div[contains(text(), 'Direct Referrals')]/strong"));
        // Indirect Referrals
        $this->SetProperty("IndirectReferrals", $this->http->FindSingleNode("//div[contains(text(), 'Indirect Referrals')]/strong"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->SetWarning($this->http->FindSingleNode("//span[contains(text(), 'Balance details and payment requests are temporarily unavailable while we perform system maintenance.')]"));
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $logoutLink = $this->waitForElement(WebDriverBy::xpath('//a[contains(@href,"logout")]'), 5);
        $this->saveResponse();
        // SurveySavvy Privacy Preferences
        if ($this->waitForElement(WebDriverBy::xpath("//div[contains(., 'Please review your Privacy Preferences below. You must consent to allowing the collection of \"Registration and Survey Data\" in order to remain opted into SurveySavvy.')]"),
            0)) {
            $this->throwAcceptTermsMessageException();
        }

        if ($logoutLink || $this->http->FindSingleNode('//div[@id = "block-menu-menu-member-menu"]//a[contains(@href, "logout")]/@href')) {
            return true;
        }

        return false;
    }
}
