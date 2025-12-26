<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSavingstarSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.coupons.com/?utm_source=instapage&utm_medium=web&crid=instapage';
    private $retry = false;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useFirefox();
        $this->http->SetProxy($this->proxyReCaptcha());
//        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL || $this->retry === true) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        $this->logger->debug("find login input");

        /*
        if ($error = $this->http->FindPreg("/(?:Secure Connection Failed|Unable to connect|The proxy server is refusing connections|Failed to connect parent proxy|The connection has timed out|Request unsuccessful. Incapsula incident ID:\s*[\d\-]+)/")) {
            $this->DebugInfo = $error;
            $this->http->Log($error, LOG_LEVEL_ERROR);
            throw new CheckRetryNeededException(5, 10);
        }
        */

        $login = $this->waitForElement(WebDriverBy::id('nav-signin-prof'), 10, false);
        $this->saveResponse();

        if (!$login) {
            return $this->checkErrors();
        }
        $this->driver->executeScript('document.querySelector(\'#nav-signin-prof > a\').click()');

        $loginField = $this->waitForElement(WebDriverBy::id('signin-email'), 10);
        $passwordInput = $this->waitForElement(WebDriverBy::id('signin-password'), 0);
        $this->saveResponse();

        if (!$loginField || !$passwordInput) {
            return $this->checkErrors();
        }
//        $loginField->sendKeys($this->AccountFields["Login"]);
//        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = 50000;
        $mover->steps = 50;

        $mover->moveToElement($loginField);
        $mover->click();
        $mover->sendKeys($loginField, $this->AccountFields['Login'], 5);

        $mover->moveToElement($passwordInput);
        $mover->click();
        $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 5);

        $loginButton = $this->waitForElement(WebDriverBy::id('couponscom-brandpage-signin'), 10);

        if (!$loginButton) {
            return false;
        }
        $loginButton->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        if ($this->retry === false) {
            $recaptcha = $this->waitForElement(WebDriverBy::xpath("//div[@id='grecap_v2']//iframe"), 5);

            if ($recaptcha) {
                $this->logger->notice('Run LoadLoginForm');
                $this->retry = true;
                $this->LoadLoginForm();
            }
        }

        // login successful
        if ($this->loginSuccessful()) {
            return true;
        }

        $this->waitForElement(WebDriverBy::xpath("
            //p[@class = 'error-message']
            | //div[contains(@class, 'signin-signup-form')]/form//p[contains(@class, 'errmsg') and @style = 'display: block;']
        "), 0);
        $this->saveResponse();
        // check for invalid password
        if ($message = $this->http->FindSingleNode("
                (//p[@class = 'error-message'])[1]
                | //div[contains(@class, 'signin-signup-form')]/form//p[contains(@class, 'errmsg') and @style = 'display: block;']
            ")
        ) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Incorrect email or password.'
                || $message == 'Email and password not authenticated.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'We\'re sorry, the server encountered a temporary internal error while processing your request.') {
                $this->http->GetURL(self::REWARDS_PAGE_URL);

                if ($this->loginSuccessful()) {
                    return true;
                }
            }

            if ($message == 'Sorry we are experiencing technical difficulty.') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        $this->saveResponse();
        // Balance
        $this->SetBalance($this->http->FindSingleNode("//div[contains(@class, 'savings-bar')]", null, true, "/\\$([\-\d\.\,]+)$/"));
        // Available Savings
        $this->SetProperty('AvailableSavings', $this->http->FindSingleNode('//div[@class = "clip-stats"]/h3/em'));

        // Name
        $this->http->GetURL('https://www.coupons.com/user-profile/account-info');
        $firstName = $this->http->FindSingleNode('//span[contains(@class, "first_name_field")]');
        $lastName = $this->http->FindSingleNode('//span[contains(@class, "last_name_field")]');
        $name = beautifulName(sprintf('%s %s', $firstName, $lastName));

        if ($name) {
            $this->SetProperty('Name', $name);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Hi,')]"), 10);
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        return false;
    }
}
