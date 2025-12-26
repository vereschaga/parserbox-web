<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerBuildabear extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = "https://www.buildabear.com/on/demandware.store/Sites-buildabear-us-Site/en_US/WorkshopRewards-Show";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        //$this->setProxyGoProxies();
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
        $this->selenium();
        //$this->http->GetURL("https://www.buildabear.com/on/demandware.store/Sites-buildabear-us-Site/en_US/Login-Show?original=%2fon%2fdemandware%2estore%2fSites-buildabear-us-Site%2fen_US%2fAccount-Show");

        if (!$this->http->ParseForm("login-form")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('loginEmail', $this->AccountFields['Login']);
        $this->http->SetInputValue('loginPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('loginRememberMe', "true");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Our website is currently down for maintenance.
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Our website is currently down for maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useChromePuppeteer();// TODO: now roking now
//            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
//            $selenium->seleniumOptions->userAgent = null;

            $selenium->disableImages();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL("https://www.buildabear.com/on/demandware.store/Sites-buildabear-us-Site/en_US/Login-Show?original=%2fon%2fdemandware%2estore%2fSites-buildabear-us-Site%2fen_US%2fAccount-Show");
            $loginInput = $selenium->waitForElement(WebDriverBy::id("login-form-email"), 10);
            /*$passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "loginButton"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                if ($this->http->FindSingleNode('//*[self::h1 or self::span][contains(text(), "This site can’t be reached")]')) {
                    $retry = true;
                }

                return $this->checkErrors();
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);*/



            $cookies = $selenium->driver->manage()->getCookies();
            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
            $this->savePageToLogs($selenium);
        } catch (NoSuchWindowException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } finally {
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 7);
            }
        }

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm([
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'x-requested-with' => 'XMLHttpRequest',
            'x-queueit-ajaxpageurl' => 'https%3A%2F%2Fwww.buildabear.com%2Flogin%3Foriginal%3D%252Fon%252Fdemandware.store%252FSites-buildabear-us-Site%252Fen_US%252FAccount-Show'
        ])) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();

        if (isset($response->redirectUrl)) {
            $redirectUrl = $response->redirectUrl;
            $this->http->NormalizeURL($redirectUrl);
            $this->http->GetURL($redirectUrl);
        }

        // success
        if ($this->loginSuccessful()) {
            return true;
        }
        // Invalid credentials
        if ($message =
            $this->http->FindSingleNode("//form[contains(@class, 'form-horizontal')]//div[@class = 'error-form']")
            ?? $response->error[0]
            ?? null
        ) {
            // Sorry, this does not match our records. Check your spelling and try again.
            if (strstr($message, 'Sorry, this does not match our records.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->logger->error("[Error]: {$message}");

            return false;
        }

        if (!$this->http->FindNodes("//div[@class = 'error-form']") && !strstr($this->http->currentUrl(), 'Account-Show')) {
            /*
             * Existing Account
             *
             * We Noticed That You Had an Existing Account from Our Previous Web Store
             *
             * Please click Verify Account to reset password.
             */
            $this->http->GetURL("https://www.buildabear.com/on/demandware.store/Sites-buildabear-us-Site/en_US/Account-FirstTimePasswordResetDialog?username=" . urlencode($this->AccountFields['Login']) . "&format=ajax");

            if ($this->http->FindSingleNode("//p[contains(text(), 'Please click Verify Account to reset password.')]")) {
                throw new CheckException('Build-A-Bear website is asking you to update your password,
                until you do so we would not be able to retrieve your account information.', ACCOUNT_PROVIDER_ERROR);
            }/*checked*/
        }// if (!$this->http->FindNodes("//div[@class = 'error-form']") && !strstr($this->http->currentUrl(), 'Account-Show'))

        // no errors, no auth
        if (in_array($this->AccountFields['Login'], [
            '822724213', // AccountID: 204672
            'dannileifer@yahoo.com', // AccountID: 4049970
            'learnhard22@yahoo.com', // AccountID: 5012046
            '823296150', // AccountID: 715089
            'davebbetts@gmail.com', // AccountID: 5324628
            "Zubi2kute@yahoo.com", // AccountID: 6056976
            "weeezie04@gmail.com", // AccountID: 6090233
            "deecarolanpaltz@gmail.com", // AccountID: 4333176
            "vierson@gmail.com", // AccountID: 2161062
        ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // Balance - Points Balance
        $this->SetBalance($this->http->FindSingleNode("//h3[contains(text(), 'Points Balance:')]/following-sibling::span[contains(@class, 'points-value')]"));
        // Rewards #
        $this->SetProperty('AccountNumber', $this->http->FindSingleNode("//h3[contains(text(), 'Rewards #:')]/following-sibling::p[contains(@class, 'number')]"));
        // Name
        $this->http->GetURL("https://www.buildabear.com/on/demandware.store/Sites-buildabear-us-Site/en_US/Account-EditProfile");
        $name = Html::cleanXMLValue($this->http->FindSingleNode("//input[contains(@name, 'firstname')]/@value")
            . ' ' . $this->http->FindSingleNode("//input[contains(@name, 'lastname')]/@value"));
        $this->SetProperty("Name", beautifulName($name));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'Logout')]")) {
            return true;
        }

        return false;
    }
}
