<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerDufry extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://sso.clubavolta.com/welcome';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->useChromePuppeteer();
    }

    /*
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
    */

    public function LoadLoginForm()
    {
        $this->logger->notice(__METHOD__);

        // Login must be correct email
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('There is not a user that matches this email/password.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://sso.clubavolta.com/login');

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'identifierEmail']"), 7);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'password']"), 3);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign in")]'), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$btn) {
            $this->logger->error("something went wrong");

            return false;
        }

        $this->logger->debug("set login");
        $login->sendKeys($this->AccountFields['Login']);
        $this->logger->debug("set pass");
        $pass->click();
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $this->logger->debug("click btn");
        $btn->click();

        /*

        if (!$this->http->ParseForm(null, '//form[contains(@class, "login-form")]')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('identifier', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('remember-me', "true");
        */

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//a[contains(@href, "logout")] | //form[@action="/api/authentication?"]/preceding-sibling::div[contains(@class,"error-text")] | //div[@class="error-title"]'), 10);
        $this->saveResponse();

        /*
        if (!$this->http->PostForm()) {
            if (
                empty($this->http->Response['body'])
                && in_array($this->AccountFields['Login'], [
                    "comercial@aprixs.com",
                    "susan.hanjess@sky.com",
                ])
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }
        */

        if ($this->loginSuccessful()) {
            return true;
        }

        $message = $this->http->FindSingleNode('//form[@action="/api/authentication?"]/preceding-sibling::div[contains(@class,"error-text")]
        | //div[@class="error-title" and contains(text(),"Failed to sign in! Please check your credentials or activate your account and try again.")]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message === "Failed to sign in! Please check your credentials or activate your account and try again.") {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - points
        $this->SetBalance($this->http->FindSingleNode("//span[contains(@class, 'current-points')]/span[last()]", null, true, "/([\d,.]+)\spoints/"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//h2[contains(text(), 'You are signed in as:')]", null, true, "/:\s*([^<]+)/")));
        // Number
        $this->SetProperty('Number', $this->http->FindSingleNode('//div[contains(@class, "customer-id")]'));
        // Silver - 0 points
        $this->SetProperty('Status', $this->http->FindSingleNode("//span[contains(@class, 'tier')]"));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(@href, "logout")]/@href')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
