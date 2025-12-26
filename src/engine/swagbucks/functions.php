<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSwagbucks extends TAccountChecker
{
    use ProxyList;

    public const REWARDS_PAGE_URL = "https://www.swagbucks.com/account/summary";

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = self::REWARDS_PAGE_URL;

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->TimeLimit = 500;

        if ($this->attempt > 0) {
            $this->http->SetProxy($this->proxyReCaptcha());
        }// had been blocked?

        $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.swagbucks.com/p/login");
//        $this->http->FilterHTML = true;
        if (
            !($tbLoginAuthToken = $this->http->FindPreg("/name=\"__mBfuPAgT\" value=\"([^\"]+)/ims"))
            && !$this->http->ParseForm("loginForm")
        ) {
            return $this->checkErrors();
        }

        if (!$this->http->FormURL) {
            $this->logger->error("FormURL not found");
            $this->http->FormURL = $this->http->FindSingleNode('//form[@id = "loginForm"]/@action');
        }

        if (!$this->http->FormURL) {
            $this->logger->error("FormURL not found");

            return $this->checkErrors();
        }

        $this->http->NormalizeURL($this->http->FormURL);

        $this->http->SetInputValue("emailAddress", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("isLoginPage", "true");
        $this->http->SetInputValue("persist", "on");
        $this->http->SetInputValue("__mBfuPAgT", $tbLoginAuthToken);

        $captcha = $this->parseCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue("g-recaptcha-response", $captcha);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Swagbucks Maintenance
        if ($message = $this->http->FindSingleNode("//title", null, true, "/Swagbucks Maintenance/ims")) {
            throw new CheckException('We are currently undergoing routine maintenance. Please check back shortly.', ACCOUNT_PROVIDER_ERROR);
        }
        // The service is unavailable
        if ($message = $this->http->FindPreg("/(The service is unavailable\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 500 - Internal server error.
        if (
            $this->http->FindSingleNode("
                //h2[contains(text(), '500 - Internal server error.')]
                | //h1[contains(text(), '503 Service Temporarily Unavailable')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->Response['code'] == 200 && empty($this->http->Response['body'])) {
            throw new CheckRetryNeededException(3, 10);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindPreg('/(Your account has been deactivated!)<br/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (strstr($this->http->currentUrl(), 'invalid-login=2')) {
            throw new CheckException('Invalid login, please try again.', ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been deactivated!
        if (
            strstr($this->http->currentUrl(), 'invalid-login=1')
            /*
            || $this->http->FindSingleNode("//div[@id = 'divErLandingPage']/@id") // false positive on AccountID: 4535075
            */
        ) {
            throw new CheckException('Your account has been deactivated!', ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->currentUrl() == 'https://www.swagbucks.com/help-deactivated') {
            throw new CheckException('Your account has been suspended. Please provide additional information to confirm and reactivate your account.', ACCOUNT_PROVIDER_ERROR);
        }
        // I agree to the Privacy Policy and Terms of Use
        if ($this->http->FindPreg('/<noscript>\s*<meta http-equiv="refresh" content="0; url=\/your-privacy-matters\?from=[^\"]+">/')) {
            $this->throwAcceptTermsMessageException();
        }

        if ($this->http->currentUrl() == 'https://www.swagbucks.com/profile-restore') {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->currentUrl() == 'https://www.swagbucks.com/account-renew') {
            throw new CheckException('We closed your account after 12 months of inactivity.', ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->currentUrl() == 'https://www.swagbucks.com/p/login') {
            // Incorrect Email/Password Combination
            if ($this->attempt == 1 && $this->http->FindSingleNode("//div[@id = 'divErLandingPage']/@id")) {
                throw new CheckException('Incorrect Email/Password Combination', ACCOUNT_INVALID_PASSWORD);
            }

            throw new CheckRetryNeededException(2, 1);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Swag Bucks
        if (!$this->SetBalance($this->http->FindSingleNode("//span[@id = 'amntSpan']"))) {
            if (!$this->SetBalance($this->http->FindSingleNode("//span[@id = 'tbar-sbAmount']"))) {
                $this->SetBalance($this->http->FindSingleNode("//var[@id = 'sbBalanceAmount']", null, false, self::BALANCE_REGEXP));
            }
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id = 'topbarUserName']")));

        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//li[@id = 'sbGlobalNavUsername']")));
        }

        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@class = 'topbarAccountUsername']")));
        }

        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@id = 'sbUserSwagName']")));
        }

        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // Balance - Swag Bucks (Total)
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->SetBalance($this->http->FindSingleNode("//span[@id = 'sbDisplayCurrent']", null, false, self::BALANCE_REGEXP));
        }

        // AccountID: 5179035, 4172444, 3572225, 672701, 3630358
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->http->FindSingleNode('//var[@id = "sbBalanceAmount"]') == '- SB') {
            $this->SetBalanceNA();
        }

        //# Lifetime
        $this->SetProperty("LifetimePoints", $this->http->FindSingleNode("//span[@id ='sbDisplayLifetime']"));

        $this->http->GetURL("https://www.swagbucks.com/account/shop-ledger");
        //# S&E Pending
        $this->SetProperty("Pending", $this->http->FindSingleNode("//span[@id ='spnSumSBLife']"));
        //# S&E Lifetime
        $this->SetProperty("Lifetime", $this->http->FindSingleNode("//span[@id ='spnSumSB']"));

        $this->http->GetURL("https://www.swagbucks.com/account/settings");
        // Full Name
        if ($name = $this->http->FindSingleNode("//input[@id = 'fullName']/@value")) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/sbGlbl\.captchaRequired = true;\s*sbGlbl\.captchaSitekey = '([^\']+)/ims");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//a[contains(text(), 'Logout')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'Log Out')]")
            || $this->http->FindSingleNode("//button[@id = 'sbLogOutCta']")
            || $this->http->FindSingleNode("//var[@id = 'sbBalanceAmount']")
        ) {
            return true;
        }

        if ($this->http->FindSingleNode("//span[@id ='tbLogoutTxt']/@id")) {
            return true;
        }

        return false;
    }
}
