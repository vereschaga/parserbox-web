<?php

class TAccountCheckerSurveysavvy extends TAccountChecker
{
    // because incapsula
    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerSurveysavvySelenium.php";

        return new TAccountCheckerSurveysavvySelenium();
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //        $arg['CookieURL'] = 'https://www.surveysavvy.com/ss/ss_index.php';
        $arg['SuccessURL'] = 'https://www.surveysavvy.com/member/account';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
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
        $this->http->removeCookies();
        $this->http->FilterHTML = false;
        $this->http->GetURL('https://www.surveysavvy.com/user/login');

        if (!$this->http->ParseForm('user-login') && $incapsulaLink = $this->http->FindPreg("/src=\"(\/_Incapsula_Resource\?[^\"]+)/")) {
            $this->http->NormalizeURL($incapsulaLink);
            $this->http->GetURL($incapsulaLink);
//            $v8 = new \V8Js();
//            $incapsula = $v8->executeString($this->http->Response['body'], 'basic.js');
//            $this->logger->debug($incapsula);
        }

        if (!$this->http->ParseForm('user-login')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("name", $this->AccountFields['Login']);
        $this->http->SetInputValue("pass", $this->AccountFields['Pass']);

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
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // access = true
        if ($this->loginSuccessful()) {
            return true;
        }
        // SurveySavvy Privacy Preferences
        if ($message = $this->http->FindSingleNode("//div[contains(., 'Please review your Privacy Preferences below. You must consent to allowing the collection of \"Registration and Survey Data\" in order to remain opted into SurveySavvy.')]")) {
            $this->throwAcceptTermsMessageException();
        }

        //# Sorry, unrecognized username or password.
        if ($message = $this->http->FindPreg("/(Sorry\, unrecognized username or password\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Your account is currently closed
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Your account is currently closed')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
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
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[contains(text(), 'Welcome')]/following-sibling::h2[1]")));
        // Member ID
        $this->SetProperty("MemberID", $this->http->FindSingleNode("//p[contains(text(), 'Member ID')]/following-sibling::h2[1]"));
        // Balance - Available Balance
        $this->SetBalance($this->http->FindSingleNode("//div[contains(text(), 'Available Balance:')]", null, true, '/:\s*([^<]+)/ims'));
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
        // SurveySavvy Privacy Preferences
        if ($this->http->FindSingleNode("//div[contains(@class, 'messages') and contains(., 'Please review your Privacy Preferences below. You must consent to allowing the collection of \"Registration and Survey Data\" in order to remain opted into SurveySavvy.')]")) {
            $this->throwAcceptTermsMessageException();
        }

        if ($this->http->FindSingleNode('//div[@id = "block-menu-menu-member-menu"]//a[contains(@href, "logout")]/@href')) {
            return true;
        }

        return false;
    }
}
