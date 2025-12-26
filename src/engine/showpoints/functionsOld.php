<?php

// refs #1795
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerShowpoints extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'deflate');
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.audiencerewards.com/member/profile#edit-name', [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
//        /** @var TAccountChecker $selenium */
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
//            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
//            $selenium->usePacFile(false);

            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_100);
            //$selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            //$selenium->seleniumOptions->addHideSeleniumExtension = false;
            //$selenium->setKeepProfile(true);
            //$selenium->disableImages();
            $selenium->http->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT);

            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL("https://www.audiencerewards.com");

            $navSignIn = $selenium->waitForElement(WebDriverBy::id('nav-sign-in-link'), 15);
            $this->savePageToLogs($selenium);

            if (!$navSignIn) {
                return false;
            }
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->selenium();
        //$this->http->GetURL("https://www.audiencerewards.com");

        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("redirect_action", '/');
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.audiencerewards.com/Member/Activity';

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Our site is temporarily down for maintenance. So grab a drink at concessions, flip through your Playbill ... and we’ll see you at the top of Act II!
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our site is temporarily down for maintenance. So grab a drink at concessions, flip through your')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Don't worry. It's a 500 Server Error only.
         *
         * Actually, the Director is backstage giving our tech team some notes for the next performance.
         * Something is broken. We will fix it soon.
         */
        if ($message = $this->http->FindSingleNode("//h1[big[contains(text(), '500 Server Error')]]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Exception in onError
         *
         * The action login.authenticate failed.
         *
         * can't call method [track] on object, object is null
         */
        if ($this->http->Response['code'] == 500
            && $this->http->FindPreg("/<h1>Exception in onError<\/h1><p>The action login\.authenticate failed\.<\/p><h2>can't call method \[track\] on object\, object is null<\/h2><p> (expression)<\/p>/")) {
            throw new CheckRetryNeededException(2, 10);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Success
        if ($this->loginSuccessful()) {
            return true;
        }
        // Sorry, this does not look like a valid email address
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Sorry, this does not look like a valid email address')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid Email Address / Password combination. Try Again.
        if ($message = $this->http->FindPreg("/(Oops\&\#x21; We don\&\#x27;t have an account matching that info\. Please try again\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We're unable to log you in with those user credentials.
        if ($message = $this->http->FindPreg("/(We\'re unable to log you in with those user credentials\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We're unable to locate an account with this email address.
        if ($message = $this->http->FindPreg("/(We\'re unable to locate an account with this email address\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, this e-mail is associated with more than one account. Please try using your Account Number to login.
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Sorry, this e-mail is associated with more than one account.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, the number you have entered is not a valid account number
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Sorry, the number you have entered is not a valid account number')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry! Incorrect password entered. Please try again.
        if ($message = $this->http->FindPreg("/(Sorry! Incorrect password entered. Please try again\.)/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Sorry, this account has been deactivated or merged with a different profile.
        if ($message = $this->http->FindSingleNode("//text()[contains(., 'Sorry, this account has been deactivated or merged with a different profile.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $name = Html::cleanXMLValue($this->http->FindSingleNode("//input[@name = 'firstName']/@value")
            . ' ' . $this->http->FindSingleNode("//input[@name = 'memberMiddleName']/@value")
            . ' ' . $this->http->FindSingleNode("//input[@name = 'lastName']/@value")
        );
        $this->SetProperty("Name", beautifulName($name));
        // Audience Rewards Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//h3[contains(text(), 'Your Account Number:')]", null, true, "/:\s*([^<]+)/"));

        if (empty($this->Properties['Name']) && empty($this->Properties['Number'])) {
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(., 'You are currently logged in as')]/span | //p[contains(text(), 'You are currently logged in as')]/span")));
            // Audience Rewards Number
            $this->SetProperty("Number", $this->http->FindSingleNode("//p[contains(text(), 'Audience Rewards Number')]/following-sibling::p/span"));
        }// if (empty($this->Properties['Name']) && empty($this->Properties['Number']))

        $this->http->GetURL('https://www.audiencerewards.com/member/dashboard');
        // Balance - Current Point Balance
        $this->SetBalance($this->http->FindSingleNode("//span[@id = 'signed-in-point-balance']/preceding-sibling::span[contains(@class, 'signed-in-show-points')]"));
        // Status
        $status = Html::cleanXMLValue($this->http->FindSingleNode('//b[contains(text(), "VIP Status:")]/following-sibling::node()[1]'));

        if ($status == 'Not Yet Attained') {
            $this->SetProperty("Status", 'Member');
        } elseif ($status == 'Achieved') {
            $this->SetProperty("Status", 'Vip');
            // You're a VIP until ...!
            $this->SetProperty("StatusExpiration", $this->http->FindSingleNode('//b[contains(text(), "VIP until ")]', null, true, "/until\s*([^!]+)/"));
        }
        // Qualifying ShowPoints
        $this->SetProperty("QualifyingPoints", $this->http->FindSingleNode('//div[contains(@class, "progressBar")]/@aria-valuenow'));

        // Expiration Date - refs #17593
        if ($this->Balance <= 0) {
            return;
        }
        $this->logger->info("Expiration date", ['Header' => 3]);
        $this->http->PostURL('https://www.audiencerewards.com/member/activity', [
            'DateRange' => '1095',
            'catID'     => '0',
        ], [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'     => 'application/x-www-form-urlencoded',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $response = $this->http->JsonLog(null, 0);

        if (is_null($response) && !$this->http->FindSingleNode('//h2[contains(text(), "Actually, the Director is backstage giving our tech team some notes for the next performance.")]')) {
            $this->sendNotification('check expiration - refs #17593');
            // debug
            if ($this->http->Response['code'] == 500) {
                throw new CheckRetryNeededException(2, 0);
            }
        } elseif ($response) {
            $maxDate = 0;

            foreach ($response as $item) {
                if (isset($item->ACTIVITYDATE)) {
                    $lastActivity = $item->ACTIVITYDATE;
                    $this->logger->debug("Last Activity: {$lastActivity}");
                    $expDate = strtotime($lastActivity, false);

                    if ($expDate && $expDate > $maxDate) {
                        $maxDate = $expDate;
                        $this->SetExpirationDate(strtotime('+24 month', $maxDate));
                        $this->SetProperty("LastActivity", $lastActivity);
                    }
                }
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(text(), 'Sign Out')]")) {
            return true;
        }

        return false;
    }
}
