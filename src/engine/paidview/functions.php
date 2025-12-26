<?php

class TAccountCheckerPaidview extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://app.paidviewpoint.com/earnings';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://paidviewpoint.com/");
        $this->http->GetURL("https://app.paidviewpoint.com/login/password");

        $login = $this->waitForElement(WebDriverBy::xpath('//a[contains(@class, "nav--login")]'), 5);
        $this->saveResponse();

        if ($login) {
            $login->click();
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "email"]'), 5);
//        $btn = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "LOG IN")]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@data-testid="button|login|submit|" or @aria-label="Log In"]'), 0);
        $this->saveResponse();
        /*

        if (!$loginInput || !$btn) {
            return $this->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $btn->click();

        if ($signInWithPassword = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "in with password")]'), 5)) {
            $signInWithPassword->click();
*/
        $passInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 5);
//        $btn = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "LOG IN")]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passInput || !$btn) {
            return $this->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passInput->sendKeys($this->AccountFields['Pass']);
        $btn->click();
//        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // PaidViewPoint Maintenance
        if ($this->http->FindPreg("/<title>PaidViewpoint Maintenance<\/title>/")) {
            throw new CheckException("We are busy making PaidViewpoint.com even more awesome! We should be back shortly!", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        $question = $this->http->FindSingleNode('//div[contains(text(), "We just sent you a magic link—if you registered,")]');
        $elements = $this->driver->findElements(WebDriverBy::xpath("//div[contains(@class, 'SegmentedInput_display__')]"));
        $verify = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "show-element")]//button[contains(text(), "Authenticate")]'), 0);
        $this->saveResponse();

        if (!$verify || !$elements) {
            return false;
        }

        if (!isset($this->Answers[$question]) || strlen($this->Answers[$question]) != 6) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        $this->logger->debug("entering code...");
        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        foreach ($elements as $key => $element) {
            $this->logger->debug("#{$key}: {$answer[$key]}");
            $input = $this->driver->findElement(WebDriverBy::xpath("//div[contains(@class, 'SegmentedInput_display__') and position() = '" . ($key + 1) . "']"));
            $input->clear();
            $input->click();
            $input->sendKeys($answer[$key]);
            $this->saveResponse();
        }// foreach ($elements as $key => $element)

        $verify->click();

        $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'Logoff')]"), 5); //todo
        $this->saveResponse();

        return true;
    }

    public function Login()
    {
        sleep(5);
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "welcome-msg")]//a[@href = "/logout"] | //span[contains(text(), "Login failed!")] | //div[contains(text(), "We just sent you a magic link—if you registered,")]'), 10);
        $this->saveResponse();

        if ($question = $this->http->FindSingleNode('//div[contains(text(), "We just sent you a magic link—if you registered,")]')) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        if ($message = $this->waitForElement(WebDriverBy::xpath('//small[contains(text(), "Invalid credentials or no user found")]'), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }

        /*
        $this->http->setDefaultHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
        $this->http->setDefaultHeader("Accept", "application/json, text/javascript, *
        /*; q=0.01");

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        */
        $status = $this->http->FindPreg("/\{\"status\":\"OK\"\}/");

        if (
            $this->http->FindSingleNode("//span[contains(text(), 'Login failed!')]")
            || $this->http->FindPreg("/\{\"status\":\"FAIL\"\}/ims")
        ) {
            throw new CheckException("Login failed!", ACCOUNT_INVALID_PASSWORD);
        }

        if (!strstr($this->AccountFields['Login'], '@')) {
            throw new CheckException("Login failed!", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // Closed account
        if ($status && $this->http->Response['code'] == 403) {
            throw new CheckException("Closed account", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance
        $balance = $this->http->FindSingleNode('//div[contains(@class, "app-header__menu")]//a[@aria-label="earnings"]');
        $this->SetBalance(str_replace('$', '', $balance));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[contains(@class, "p-avatar")]/following-sibling::span')));
        // TraitScore
        $this->SetProperty("TraitScore", $this->http->FindSingleNode("//span[contains(text(), 'TraitScore')]/following-sibling::span"));
        // Lifetime earnings
        $lifetimeEarnings = $this->http->FindSingleNode("//h3/text()[contains(.,'Lifetime')]/../following-sibling::p[1]");
        $this->SetProperty("LifetimeEarnings", $lifetimeEarnings);
        // Year-to-date earnings
        $thisYearEarnings = $this->http->FindSingleNode("//h3/text()[contains(.,'Year-to-date')]/../following-sibling::p[1]");
        $this->SetProperty("ThisYearEarnings", $thisYearEarnings);
        // Earnings to cashout
        $earningsToCashout = $this->http->FindSingleNode("//p[@class='note']/strong");
        $this->SetProperty("EarningsToCashout", $earningsToCashout);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "app-header__menu")]//a[@aria-label="earnings" and not(text() = "$0.00")]'), 5);
        $this->saveResponse();

        if ($this->http->FindSingleNode('//div[contains(@class, "app-header__menu")]//a[@aria-label="earnings"]')) {
            return true;
        }

        return false;
    }
}
