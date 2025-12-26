<?php

class TAccountCheckerKoa extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://koa.com/account/";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
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
        if (!strstr($this->AccountFields["Login"], '@')) {
            throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->getCookiesFromSelenium("https://koa.com/account/");

        return true;

        $this->http->removeCookies();
        $this->http->setMaxRedirects(10);
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://koa.com/account/");
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm(null, "//form[//input[@name = 'Username']]")) {
            return false;
        }

        $this->http->SetInputValue('Username', $this->AccountFields['Login']);
        $this->http->SetInputValue('Password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('RememberLogin', "true");
        $this->http->SetInputValue('button', "login");

        return true;
    }

    public function Login()
    {
        /*
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->ParseForm(null, "//form[//input[@name = 'session_state']]")) {
            $this->http->PostForm();
        }
        */

        if ($this->loginSuccessful()) {
            return true;
        }
        // invalid credentials
        if ($message = $this->http->FindSingleNode("(//div[contains(@class, 'validation-summary-errors')])[1]")) {
            throw new CheckException(preg_replace("/\s+Learn More.*$/ims", '', $message), ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, it looks like your password has expired. Please change your password to continue. Your new password must be at least 8 characters and include at least one number and it can not be the same as your old password.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, it looks like your password has expired.')]")) {
            throw new CheckException('Sorry, it looks like your password has expired. Please change your password to continue. ', ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        // Name & Address
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[normalize-space(text()) = 'Name']/following-sibling::div[1]", null, false) ?? $this->http->FindSingleNode("//div[normalize-space(text()) = 'Display Name']/following-sibling::div[1]")));
        // Level
        $this->SetProperty("Status", beautifulName($this->http->FindSingleNode('//div[contains(@class,"account-header-outer-wrapper")]//div[normalize-space(.)="Level"]/following-sibling::div/span')));
        // Points Used
        // $this->SetProperty("PointsUsed", $this->http->FindSingleNode("//span[contains(@id, 'vkr-summary-used')]"));
        // Points Available
        $this->SetProperty("PointsAvailable", $this->http->FindSingleNode('//div[contains(@class,"account-header-outer-wrapper")]//div[normalize-space(.)="Points Available"]/following-sibling::div/span'));
        // Lifetime Points Earned
        $this->SetProperty("LifetimePoints", $this->http->FindSingleNode('//div[contains(@class,"account-header-outer-wrapper")]//div[normalize-space(.)="Lifetime Points Earned"]/following-sibling::div/span'));
        // Account Number
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//h2[contains(text(), 'KOA REWARDS ACCOUNT')]/following-sibling::h4[1]"));
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//h4[contains(text(),'Enrolled Since')]", null, true, "/Since\s*([^\']+)/"));
        // VKR Expiration Date
        $exp = $this->http->FindSingleNode("//p[contains(text(), 'Expires:')]", null, true, "/:\s*([^<]+)/");

        if ($exp = strtotime($exp)) {
            $this->SetExpirationDate($exp);
        }

        // Current Points
        $this->SetBalance($this->http->FindSingleNode("//div[contains(@class, 'account-list-points')]", null, true, "/\-\s*(\d+)\s*PTS/"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Sorry, your Value Kard Rewards account information is unavailable at this time.  Please try again later.
            if ($this->SetWarning($this->http->FindSingleNode("//li[contains(normalize-space(text()), 'Sorry, your KOA Rewards account information is unavailable at this time. Please try again later.')]"))) {
                return;
            }

            if (
                count($this->http->FindNodes("//a[contains(text(), 'Edit')]")) >= 2
                && $this->http->FindSingleNode("//p[contains(text(), 'Enroll in KOA Rewards for')]")
                && $this->http->FindSingleNode("//h4[contains(text(), 'Enrolled Since')]")
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                count($this->http->FindNodes("//a[contains(text(), 'Edit')]")) < 3
                && $this->http->FindSingleNode("//p[contains(text(), 'Add KOA Rewards Account')]")
                && !empty($this->Properties['Name'])
                && !empty($this->Properties['MemberSince'])
            ) {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")
            || $this->http->FindSingleNode("//label[normalize-space(text()) = 'Display Name']/following-sibling::div[1]")
        ) {
            return true;
        }

        return false;
    }

    private function getCookiesFromSelenium($url)
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL($url);
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }
            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "Username"]'), 5);
            $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "Password"]'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@value = "login"]'), 0);

            $this->savePageToLogs($selenium);

            if (!$login || !$pass/* || !$btn*/) {
                $this->logger->error("something went wrong");

                return false;
            }

            $this->logger->debug("enter Login");
            $login->click();
            $login->clear();
            $login->sendKeys($this->AccountFields['Login']);
            $this->logger->debug("enter Password");
            $pass->clear();
            $pass->sendKeys($this->AccountFields['Pass']);
            $selenium->driver->executeScript('let rememberMe = document.querySelector(\'#RememberLogin\'); if (rememberMe) rememberMe.checked = true;');
            $this->logger->debug("click");
            $this->savePageToLogs($selenium);
            $btn->click();

            $selenium->waitForElement(WebDriverBy::xpath('
                //a[contains(@href, "logout")]
                | //h1[contains(text(), "MY ACCOUNT")]
                | //div[contains(@class, "validation-summary-errors")]
            '), 10, false);

            $this->savePageToLogs($selenium);

            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return null;
    }
}
