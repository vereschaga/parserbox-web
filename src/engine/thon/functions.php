<?php

class TAccountCheckerThon extends TAccountChecker
{
    private const REWARDS_PAGE_URL = "https://www.thonhotels.com/mypage/my-membership/";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
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
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if (!$this->http->ParseForm(null, "//form[contains(@action, '/Login')]")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("Username", $this->AccountFields["Login"]);
        $this->http->SetInputValue("Password", $this->AccountFields["Pass"]);
        $this->http->SetInputValue("RememberLogin", "true");
        $this->http->SetInputValue("button", "login");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The website is not available at the moment due to technical maintenance.
        if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'The website is not available at the moment due to technical maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // An error occurred when trying to log you in. It might be a temporary problem, so please try again.
        if ($this->http->Response['code'] == 500
            && $this->http->FindSingleNode("//p[contains(text(), 'An unexpected error has occurred on our browser.')]")) {
            throw new CheckException("An error occurred when trying to log you in. It might be a temporary problem, so please try again.", ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application.
        if ($message = $this->http->FindPreg('/Server Error in \'\/\' Application\./')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($this->http->FindSingleNode("//h2[contains(text(), '404 - File or directory not found.')]")) {
            $this->http->GetURL("https://www.thonhotels.com/");

            if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'We are updating our systems')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->http->FindSingleNode("//h2[contains(text(), '404 - File or directory not found.')]"))

        return false;
    }

    public function Login()
    {
        $this->http->FilterHTML = false;

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->ParseForm(null, "//form[@action='https://me.thonhotels.no/' or @action = 'https://me.thonhotels.no/signin-oidc']")) {
            $this->http->PostForm();
        }

        if ($this->parseQuestion()) {
            return false;
        }

        $this->http->FilterHTML = true;

        if ($message = $this->http->FindSingleNode('//div[@class = "alert-danger"]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Invalid username or password')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($skip = $this->http->FindSingleNode('//a[normalize-space(text()) = "Skip"]/@href')) {
            $this->http->NormalizeURL($skip);
            $this->http->GetURL($skip);
        }

        if (
            $this->http->FindSingleNode('//button[contains(text(), "Click to continue")]')
            && $this->http->ParseForm(null, "//form[@action='https://me.thonhotels.no/' or @action = 'https://me.thonhotels.no/signin-oidc']")
        ) {
            $this->http->PostForm();
        }

        // login successful
        if ($this->loginSuccessful()) {
            return true;
        }

        // We cannot find any accounts with this user name and password. Please check that you have entered it correctly.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'We cannot find any accounts with this user name and password') and not(contains(@class, 'hidden'))]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        /*
         * We are currently experiencing technical difficulties with our booking system.
         * Unfortunately you will not be able to log in until this is fixed.
         */
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'We are currently experiencing technical difficulties with our booking system.') and not(contains(@class, 'hidden'))]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Do we have the correct contact information?')]")) {
            $this->throwProfileUpdateMessageException();
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        $question = $this->http->FindSingleNode('//p[contains(text(), "Enter the code from your authentication app (e.g. Google Authenticator or Microsoft Authenticator).") or contains(text(), "SMS sent to")]');

        if (!$this->http->ParseForm(null, '//form[@action = "/identity/account/login-with-two-factor" or @action = "/identity/account/login-with-two-factor-app" or @action = "/identity/VerificationCode/VerificationCode"]')) {
            return false;
        }

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->SetInputValue("TwoFactorCode", $this->Answers[$this->Question]);
        $this->http->SetInputValue("VerificationCode", $this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode("//span[@class = 'field-validation-error']")) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Code is not valid')) {
                $this->AskQuestion($this->Question, $message, "Question");

                return false;
            }

            $this->DebugInfo = $message;

            return false;
        }

        return true;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            if ($this->http->FindSingleNode('//button[contains(text(), "Click to continue")]')) {
                $this->http->PostForm();
            }
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[@class = "global-header__util-btn-name"]')));
        // membership number
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//span[contains(text(), 'Member number')]/following-sibling::span/span/@data-value"));
        // Your bonus level
        $this->SetProperty("Status", beautifulName($this->http->FindSingleNode("//div[span[contains(text(), 'Member number')]]/preceding-sibling::div")));

        $signUp = $this->http->FindSingleNode('//h2[contains(text(), "Join THON")]');

        $this->http->GetURL("https://www.thonhotels.com/mypage/my-membership/bonus-points/");
        // Balance  - Bonus points
        $this->SetBalance($this->http->FindSingleNode("//main//div[contains(text(), 'You have')]", null, true, "/have (.+) bonus/"));
        // Expiring Balance
        // TODO
        $this->SetProperty("ExpiringBalance", $this->http->FindSingleNode("//dt[contains(text(), 'Bonus points expiring')]/following-sibling::dd[1]/text()[1]"));
        $expText = $this->http->FindSingleNode("//dt[contains(text(), 'Bonus points expiring')]", null, false, '/expiring\s*(.+)/');
        // 2000 points expire 31. December 2022
        if ($exp = strtotime($expText)) {
            $this->SetExpirationDate($exp);
        }

        // AccountID: 1080187
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->FindSingleNode('//div[contains(text(), "You do not have any bonus points")]')
            && !empty($this->Properties['AccountNumber'])
            && !empty($this->Properties['Name'])
            && !empty($this->Properties['Status'])
        ) {
            $this->SetBalanceNA();
        } elseif (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && !empty($this->Properties['Name'])
            && $signUp
        ) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        } elseif (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && !empty($this->Properties['Name'])
            && ($maintenance = $this->http->FindSingleNode('//span[contains(text(), "Due to the upgrade of our systems, there will be limited functionality until approximately")]'))
        ) {
            $this->SetWarning($maintenance);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//form[contains(@action, '/logout')]/@action")) {
            return true;
        }

        return false;
    }
}
