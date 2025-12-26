<?php

class TAccountCheckerTridentSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useChromium();
        $this->keepCookies(false);

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("http://www.tridentprivilege.com/index.aspx");
        $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'SIGN IN')] | //input[@name = 'txtUserName']"), 30);

        if ($signInBtn = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'SIGN IN')]"), 0)) {
            $signInBtn->click();
        }

        $usernameInput = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'txt_memberid']"), 3);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'txt_passwordlogin']"), 0);
        $submitButton = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'tpmembersubmit']"), 0);

        if (!$usernameInput || !$passwordInput || !$submitButton) {
            $this->logger->error('something went wrong');
            $this->saveResponse();

            return $this->checkErrors();
        }

        $usernameInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $submitButton->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            // HTTP Error 404. The requested resource is not found.
            || $this->http->FindSingleNode('//p[contains(text(), "HTTP Error 404. The requested resource is not found.")]')
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//img[@alt = "Under Maintenance"]/@alt')) {
            throw new CheckException("Trident Website not available due to system upgrade. We apologize for any inconvenience caused.", ACCOUNT_PROVIDER_ERROR); /*review*/
        }

        return false;
    }

    public function Login()
    {
        // Access is allowed
        $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'Logout')] | //span[@id = 'lbl_Message' or (contains(@class, 'error-msg') and not(@style=\"display: none;\"))]"), 20);
        $this->saveResponse();

        if ($this->http->FindSingleNode("//a[contains(@href, 'Logout')]")) {
            return true;
        }

        $message = $this->http->FindSingleNode("//span[@id = 'lbl_Message' or (contains(@class, 'error-msg') and not(@style=\"display: none;\"))]");

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Your Trident Privilege Membership has lapsed.')
                || $message == 'Invalid credential'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.tridenthotels.com/trident-privilege/dashboard/");
        $this->waitForElement(WebDriverBy::xpath("//span[@id = 'spn_member_tier']"));
        $this->saveResponse();
        // Member Tier
        $this->SetProperty("MemberTier", $this->http->FindSingleNode("//span[@id = 'spn_member_tier']"));
        // Reach Silver tier with ... more qualifying nights till ...
        $this->SetProperty('StaysNeededToNextLevel', $this->http->FindSingleNode("//div[@id = 'div_NextTier']/span"));

        $this->http->GetURL("https://www.tridenthotels.com/trident-privilege/my-account-statement/");
        $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Point Balance')]/preceding-sibling::div"));
        $this->saveResponse();
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//span[contains(text(), 'Member Since')]/preceding-sibling::div"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[contains(@class, "tp-member-name")]/a')));
        // Points Balance
        $this->SetBalance($this->http->FindSingleNode("//div[@id = 'div_points_balance']"));

        // Expiration date
        if ($this->Balance > 0) {
            // Please note: ... Points will lapse by ...
            $expire = $this->http->FindSingleNode('//div[@id = "div_points_note"]/strong', null, true, "/lapse\s*by\s*([^\*]+)/ims");
            $this->logger->debug("Expiry Date: " . var_export($expire, true));
            // refs #23620
            if ($expire = strtotime($expire)) {
                $this->SetExpirationDate($expire);
            }

            // Points lapsing
            $this->SetProperty("PointsExpiring", $this->http->FindSingleNode('//div[@id = "div_points_note"]/span'));
        }
    }
}
