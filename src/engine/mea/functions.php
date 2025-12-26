<?php

class TAccountCheckerMea extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://www.mea.com.lb/english/my-cedar-miles/my-account";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->setHttp2(true);

        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->useFirefoxPlaywright();
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
        $this->http->removeCookies();
        $this->http->GetURL("https://www.mea.com.lb/english/cedar-miles/my-account");

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'MembershipNumber']"), 7);

        if ($accept = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "I Accept")]'), 0)) {
            $accept->click();
            $this->saveResponse();
        }

        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'LoginPassword']"), 3);
        $btn = $this->waitForElement(WebDriverBy::xpath('//a[@id = "myAccountSubmitButton"]'), 0);
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
        if (!$this->http->ParseForm("myAccountForm")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("MembershipNumber", $this->AccountFields["Login"]);
        $this->http->SetInputValue("LoginPassword", $this->AccountFields["Pass"]);
        $this->http->SetInputValue("RememberMe", 'true');
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        if ($accept = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "I Accept")]'), 5)) {
            $accept->click();
            $this->saveResponse();
        }

        $this->waitForElement(WebDriverBy::xpath('//li[contains(@class, "loggedIn") and not(contains(@class, "displayNone"))]//a[contains(@href, "logout")] | //p[contains(text(), "Due to our new rules, and for the security of your account, you must change your password.")] | //label[@id = "signInErrorMsg"]'), 10);
        $this->saveResponse();

        /*
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $response = $this->http->JsonLog();

        if (isset($response->redirectUrl)) {
            $redirectUrl = $response->redirectUrl;
            $this->http->NormalizeURL($redirectUrl);
            $this->http->GetURL($redirectUrl);
        }
        */

        // Change Password
        if ($this->http->FindSingleNode('//p[contains(text(), "Due to our new rules, and for the security of your account, you must change your password.")]')) {
            $this->throwProfileUpdateMessageException();
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }

        $message =
            $response->error
            ?? $this->http->FindSingleNode('//label[@id = "signInErrorMsg"]')
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Your credentials are invalid or incorrect.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'The account has been locked')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, 'An internal error has occured, please check again or feel free to contact us if the error persists.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }// if ($message)

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Balance - Total Balance
        $this->SetBalance($this->http->FindSingleNode('//h5[contains(text(), "Total Balance")]/following-sibling::h4'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[contains(@class, "cardHolder")]/div[1]')));
        // Card Number
        $this->SetProperty("Number", $this->http->FindSingleNode('//div[contains(@class, "cardHolder")]/div[2]'));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode('//div[contains(@class, "cardHolder")]/img/@src', null, true, "/([a-z]+)Card\.png/ims"));
        // Total Qualifying Miles
        $this->SetProperty("QualifyingMiles", $this->http->FindSingleNode('//h5[contains(text(), "Q-Miles")]/following-sibling::h4'));
        // Qualifying Sectors
        $this->SetProperty('QualifyingSectors', $this->http->FindSingleNode('//h5[contains(text(), "Q-Sectors")]/following-sibling::h4'));
        // You still need to earn 20,000 QMiles by end of this year to reach the Silver Card status
        $this->SetProperty('NeededToNextLevel', $this->http->FindPreg('/You still need to earn ([\d.,]+) QMiles/iU'));

        // Miles Expiry by Year
        $nodes = $this->http->XPath->query('//h3[contains(text(), "Miles Expiry by Year")]/following-sibling::div[contains(@class, "halfOnMob")]');
        $this->logger->debug("Total {$nodes->length} exp nodes were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            // Date to Expire
            $date = $this->http->FindSingleNode('.//div[contains(text(), "Date to Expire")]/following-sibling::div', $nodes->item($i));
            $date = $this->ModifyDateFormat($date);
            // Miles to Expire
            $balanceToExpire = $this->http->FindSingleNode('.//div[contains(text(), "Miles to Expire")]/following-sibling::div', $nodes->item($i));

            if ($balanceToExpire > 0 && strtotime($date)) {
                $this->SetProperty("ExpiringBalance", $balanceToExpire);
                $this->SetExpirationDate(strtotime($date));

                break;
            }// if ($balanceToExpire > 0 && strtotime($date))
        }// for ($i = 0; $i < $nodes->length; $i++)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//li[contains(@class, "loggedIn") and not(contains(@class, "displayNone"))]//a[contains(@href, "logout")]/@href')) {
            return true;
        }

        return false;
    }
}
