<?php

class TAccountCheckerVoila extends TAccountChecker
{
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("http://www.vhr.com/default.aspx", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }
        // provider error
        if ($this->http->Response['code'] == 503 && $this->http->FindSingleNode("//b[contains(text(), 'Unable to determine IP address from host name')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // do not show captcha
        $this->http->setCookie("isMemberNewVoila", "old", "www.vhr.com");
        $this->http->GetURL("https://www.vhr.com/login.aspx");

        if (!$this->http->ParseForm('aspnetForm')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('ctl00$MainContent$txtEMail', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$MainContent$txtPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('ctl00$MainContent$btnSignIn', "Submit");
        $this->http->SetInputValue('ctl00$MainContent$chkRemember', "on");

        if ($this->http->InputExists('ctl00$MainContent$captchaAnswer') && $this->http->FindSingleNode("//div[@id = 'loginForm']//img[@alt = 'Captcha']/@src")) {
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue('ctl00$MainContent$captchaAnswer', $captcha);
        } else {
            $this->http->unsetInputValue('ctl00$MainContent$captchaAnswer');
        }

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.vhr.com/default.aspx";

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindPreg("/The system is down for scheduled maintenance/i")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($message = $this->http->FindSingleNode('
                //h1[contains(text(), "Service Unavailable")]
                | //p[contains(text(), "The web app you have attempted to reach is currently stopped and does not accept any requests. Please try to reload the page or visit it again soon.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindSingleNode("//b[contains(text(), 'Unable to determine IP address from host name')]")
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            || $this->http->FindPreg("/(The page cannot be displayed because an internal server error has occurred\.)/ims")
            || $this->http->FindPreg('#/errorhandler\.aspx\?aspxerrorpath=/login\.aspx#', false, $this->http->currentUrl())) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            // provider bug fix
            if ($this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)) {
                throw new CheckRetryNeededException(3);
            }

            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;

        //# There was a problem processing your request.
        if ($message = $this->http->FindPreg('/(There was a problem processing your request\.)/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Access is allowed
        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")
            || $this->http->FindSingleNode("//span[@id = 'ctl00_MainContent_ctl00_TierMeterUC_lblName']")) {
            return true;
        }
        //# Login Failed
        if ($message = $this->http->FindSingleNode('//span[contains(@id, "lblLoginFailed")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Login Failed
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Login Failed")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid email address
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Invalid email address")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // The characters you entered did not match the word verification. Please try again.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "The characters you entered did not match the word verification.")]')) {
            if ($this->recognizer) {
                $this->recognizer->reportIncorrectlySolvedCAPTCHA();
            }

            throw new CheckRetryNeededException(3, 1);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("(//span[contains(@id, 'lblMemberName')])[1]")));

        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("(//span[contains(@id, 'lblName')])[1]")));
        }
        //# Balance - Current Balance
        if ($balance = $this->http->FindSingleNode("//span[contains(@id, 'LblPoints')]", null, false, "/[\d\.\,]+/ims")) {
            $this->SetBalance($balance);
        }
        //# balance in header
        else {
            $this->SetBalance($this->http->FindSingleNode("//span[contains(@id, 'lblMemberPoints')]", null, false, "/([\d\.\,]+)/"));
        }
        //# Last Stay Date
        $this->SetProperty("Laststaydate", $this->http->FindSingleNode('//span[contains(@id, "lblLastStayDate")]'));
        //# Last Stay Hotel
        $this->SetProperty("Laststayhotel", $this->http->FindSingleNode("//a[contains(@id, 'hlLastStayHotel')]"));
        //# Status (For example: PHOENIX ACTIVITY)
        $status = trim($this->http->FindSingleNode("(//span[contains(@id, 'lblMemberTier')])[1]", null, true, "/(.+)member/ims"));

        if (empty($status)) {
            $status = trim($this->http->FindSingleNode("(//span[contains(@id, 'lblMemberTier')])[1]", null, true, "/(.+)회원/ims"));
        }

        // temporarily measure
        if (empty($status)) {
            $http2 = clone $this->http;
            $http2->GetURL("http://www.vhr.com/login.aspx");
            $status = trim($http2->FindSingleNode("(//span[contains(@id, 'lblMemberTier')])[1]", null, true, "/(.+)member/ims"));
        }

        if (!empty($status)) {
            $this->SetProperty('Status', $status);
            $this->SetProperty('NightsToNextLevel', $this->http->FindSingleNode('//table[@id="ctl00_MainContent_ctl00_TierMeterUC_tblMessages"]//td[text()!=""][1]', null, true, '/(\d+)\s+nights needed.*to attain/ims'));
        }

        //# Member since
        $this->SetProperty("Since", $this->http->FindSingleNode("//span[contains(@id, 'lblActivity')]/b"));

        $this->http->GetURL("http://www.vhr.com/replacementCard.aspx");
        //# Account Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//span[contains(@id, 'CardNumber')]"));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);
        $captchaURL = $this->http->FindSingleNode("//div[@id = 'loginForm']//img[@alt = 'Captcha']/@src");

        if (!$captchaURL) {
            return false;
        }
        $http2->NormalizeURL($captchaURL);
        $file = $http2->DownloadFile($captchaURL, "jpeg");
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file);
        unlink($file);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")
            || $this->http->FindSingleNode("//span[@id = 'ctl00_MainContent_ctl00_TierMeterUC_lblName']")
        ) {
            return true;
        }

        return false;
    }
}
