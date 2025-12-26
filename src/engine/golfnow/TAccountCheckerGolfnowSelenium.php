<?php
class TAccountCheckerGolfnowSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    /**
     * @var HttpBrowser
     */
    public $browser;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->InitSeleniumBrowser();
        $this->useGoogleChrome();
        $this->disableImages();

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->useCache();
        }
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://www.golfnow.com/customer/login");
//        $loginForm = $this->waitForElement(WebDriverBy::id('fmlogin'), 15);
//        if (empty($loginForm)) {
//            $this->saveResponse();
//            if($this->http->FindSingleNode("//message[contains(text(), 'The specified blob does not exist')]"))
//                throw new CheckRetryNeededException(3, 7);
//            return false;
//        }

        $loginInput = $this->waitForElement(WebDriverBy::id('username'), 5);
        $passwordInput = $this->waitForElement(WebDriverBy::id('password'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Login")]'), 0);

        if (!$loginInput || !$passwordInput || !$button) {
            $this->logger->error('something went wrong');
            $this->saveResponse();

            return false;
        }// if (!$loginInput || !$passwordInput)
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $button->click();

//        $this->parseWithCurl();
//        $this->browser->removeCookies();
//        $this->browser->GetURL("https://www.golfnow.com/customer/login");
//        if (!$this->browser->ParseForm("fmlogin"))
//            return $this->checkErrors();
//        $this->browser->SetInputValue("UserName", $this->AccountFields["Login"]);
//        $this->browser->SetInputValue("Password", $this->AccountFields["Pass"]);

        return true;
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->browser->LogHeaders = true;
        $this->browser->setProxyParams($this->http->getProxyParams());
        $this->browser->GetURL($this->http->currentUrl());
    }

    public function checkErrors()
    {
        //# We are currently undergoing system maintenance
        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'We are currently undergoing system maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The page you requested is in the water hazard.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'The page you requested is in the water hazard.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // form submission
//        if (!$this->browser->PostForm())
//            return $this->checkErrors();
        // check for invalid password
        if ($message = $this->http->FindSingleNode("//div[@id='errorList']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // login successful
        $logout = $this->waitForElement(WebDriverBy::xpath("//a[@id='login-menu-pic-link']"), 10, false);
        $this->saveResponse();

        if ($logout) {
            return true;
        }
        // The credentials provided are incorrect.
        if ($message = $this->http->FindSingleNode("//small[contains(text(), 'The credentials provided are incorrect.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The Email address field is not a valid e-mail address.
        if ($message = $this->http->FindSingleNode("//small[contains(text(), 'The Email address field is not a valid e-mail address.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The account associated with this email address has been temporarily locked.
        if ($message = $this->http->FindSingleNode("//small[contains(text(), 'The account associated with this email address has been temporarily locked.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.golfnow.com/my/profile#tabRewards");
        // set Name
        $firstName = $this->http->FindPreg("/\"firstName\":\"([^\"]+)\",\"lastName\":\"[^\"]+\",\"status\":/");
        $lastName = $this->http->FindPreg("/\",\"lastName\":\"([^\"]+)\",\"status\":/");
        $this->SetProperty("Name", beautifulName("$firstName $lastName"));

        $hash = $this->http->FindPreg("/var hash = \'([^\']+)/");
        $public_key = $this->http->FindPreg("/var public_key = \'([^\']+)/");
        $sa_mem_id = $this->http->FindPreg("/var sa_mem_id = \'([^\']+)/");

        if (isset($hash, $public_key, $sa_mem_id)) {
            $this->browser->GetURL("https://s15.socialannex.net/advancedashoard/rewardtab/7459651/{$sa_mem_id}?access_token=dTQ5cwMQON8YaLnDzydZ&random=" . $this->random() . "&hash={$hash}&public_key={$public_key}");
        }

        // Balance - Points Earned
        $this->SetBalance($this->browser->FindSingleNode("//div[@id = 'graph']/@data-percent"));
        // Points Until Next Reward
        $this->SetProperty("PointsUntilNextReward", $this->browser->FindPreg("/var\s*req_Points\s*=\s*([^\;]+)/"));
        // Total Reservations
        $this->SetProperty("RoundsBooked", $this->browser->FindSingleNode("//div[contains(., 'Total Reservations')]/span"));
        // Rewards Available
        $this->SetProperty("RewardsAvailable", $this->browser->FindSingleNode("//div[contains(., 'Rewards Available')]/span"));
        // Lifetime Points Earned
        $this->SetProperty("LifetimePointsEarned", intval($this->browser->FindPreg("/\{\s*var\s*s15_totalPoints\s*=\s*([^\;]+)/")));

        // Rewards
        $rewards = $this->browser->XPath->query("//table[@id = 'rewarddata']//tr[td[position() = 5 and contains(text(), 'Available')]]");
        $this->logger->debug("Total {$rewards->length} available rewards were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($rewards as $reward) {
            // Reward Earned
            $displayName = $this->browser->FindSingleNode("td[contains(@class, 'sa_reward_manage')]", $reward);
            // Promo Code
            $code = $this->browser->FindSingleNode("td[contains(@class, 'sa_code_manage')]", $reward);
            // Expires On
            $exp = $this->browser->FindSingleNode("td[contains(@class, 'sa_expire_manage')]", $reward);
            $this->AddSubAccount([
                'Code'           => 'golfnow' . $code,
                'DisplayName'    => $displayName,
                'Balance'        => null,
                // Promo Code
                'PromoCode'      => $code,
                'ExpirationDate' => strtotime($exp),
            ]);
        }// foreach ($rewards as $reward)

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->browser->FindSingleNode("//div[contains(text(), 'You have not yet accrued any loyalty rewards')]")) {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }
}
