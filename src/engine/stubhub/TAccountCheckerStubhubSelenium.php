<?php

class TAccountCheckerStubhubSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->InitSeleniumBrowser();
        $this->useChromium();
        $this->disableImages();
        $this->keepCookies(false);
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://myaccount.stubhub.com/myaccount/rewards");

        if (!$this->http->ParseForm(null, 1, true, '//form[contains(@action, "/login/signin.logincomponent_0.signinform")]')) {
            return $this->checkErrors();
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'loginEmail']"), 0, true);

        if (!$loginInput) {
            $this->logger->error('Failed to find "login" input');

            return false;
        }
        $loginInput->sendKeys($this->AccountFields['Login']);

        $passwordInput = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'loginPassword']"), 0, true);

        if (!$passwordInput) {
            $this->logger->error('Failed to find "password" input');

            return false;
        }
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'performing scheduled maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, but the StubHub website is temporarily unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $loginButton = $this->waitForElement(WebDriverBy::xpath("//button[@name = 'signIn']"), 0, true);

        if (!$loginButton) {
            $this->logger->error('Failed to find login button');

            return false;
        }
        $loginButton->click();
        $this->saveResponse();

        // check for invalid password
        if ($message = $this->http->FindSingleNode("//div[@class='errorMsg']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // login successful
        $this->waitForElement(WebDriverBy::xpath("//a[text() = 'Sign out']"), 15, true);
        $this->saveResponse();

        if ($this->http->FindSingleNode("//a[text() = 'Sign out']")) {
            return true;
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Agree to our terms for StubHub U.S.')]")) {
            $this->throwAcceptTermsMessageException();
        }

        $this->http->GetURL("https://myaccount.stubhub.com/login/Signin");
        $this->saveResponse();

        if ($message = $this->http->FindPreg("/You were so quiet, we signed you out \(just to be safe\)\./ims")) {
            $this->http->Log(">>>> Bug of provider: " . $message);
            $this->http->GetURL("https://myaccount.stubhub.com/myaccount/rewards");
            $this->saveResponse();
            // login successful
            if ($this->http->FindSingleNode("//a[text() = 'Sign out']")) {
                return true;
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // set Status
        $this->SetProperty("Status", trim($this->http->FindSingleNode("//div[span[contains(text(), 'Status') and contains(@class, 'statusHeading')]]/following-sibling::div[1]")));
        // set Earning
        $this->SetProperty("Earning", $this->http->FindSingleNode("//div[@class = 'summarySubImp' and contains(text(), 'reward')]", null, true, '/\s*([\d\.\,\%]+)/ims'));
        // set Balance
        $this->SetBalance($this->http->FindPreg("/var\s*rewardsEarned\s*=\s*([^\;]+)/ims"));
        // set Lifetime
        $this->SetProperty("Lifetime", $this->http->FindSingleNode("//div[contains(text(), 'Lifetime rewards earned')]", null, true, "/earned\s*:\s*([^<]+)/ims"));
        // set MemberSince
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//div[contains(text(), 'member since')]", null, true, "/since\s*:\s*([^<]+)/ims"));
        // set NextStatus
        $this->SetProperty("NextStatus", $this->http->FindSingleNode("//div[contains(text(), 'Become a')]", null, true, "/Become\s*a\s([^\s]+)\s/ims"));
        // set Available Rewards
        if (!$this->SetProperty("AvailableRewards", $this->http->FindSingleNode("//div[span[contains(text(), 'Available FanCodes')]]/following-sibling::div[1]"))) {
            if ($this->http->FindPreg("/(Join now and receive a FanCode for a free electronic delivery)/ims")) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }
        // Purchases
        $this->SetProperty('Purchases', $this->http->FindPreg('/var purchasedToNextTier\s*=\s*(\d+)\s*;/ims'));
        // Amount Spent
        $this->SetProperty('AmountSpent', $this->http->FindSingleNode('//td[@class="ordertotal"]'));

        // set Name
        $this->http->GetURL("https://myaccount.stubhub.com/myaccount/yourinfo");
        $this->saveResponse();
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h2[contains(text(), 'Primary contact')]/following-sibling::div/text()[1]")));
    }
}
