<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerLegovipSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public const WAIT_TIMEOUT = 7;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $this->useChromePuppeteer();
        $this->setProxyGoProxies();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        /*
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(true);
        $this->disableImages();
        */
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->http->GetURL("https://identity.lego.com/en-US/login/?returnUrl=%2Fconnect%2Fauthorize%2Fcallback%3FappContext%3Dfalse%26adultexperience%3Dtrue%26hideheader%3Dtrue%26scope%3Dopenid%2520email%2520profile%2520dob%26response_type%3Did_token%2520token%26client_id%3D316ad352-6573-4df0-b707-e7230ab7e0c7%26redirect_uri%3Dhttps%253A%252F%252Fwww.lego.com%252Fidentity%252Fcallback%26ui_locales%3Den-us%26state%3Dy09MZV9vsVeieO-U%26nonce%3DwgReXjcjEUdyfBCB");
        $this->http->GetURL("https://www.lego.com/en-us/vip/join");

        $loginLink = $this->waitForElement(WebDriverBy::xpath('//a[@data-test="vip-join-login-button"] | //p[contains(text(), "An error occurred on client")]'), 25);

        // provuder bug fix
        if ($loginLink && strstr($loginLink->getText(), 'An error occurred on client')) {
            $this->http->GetURL("https://www.lego.com/en-us/vip/join");
        }

        $loginLink = $this->waitForElement(WebDriverBy::xpath('//a[@data-test="vip-join-login-button"]'), 15);
        $this->closePopups();
        $this->saveResponse();

        if (!$loginLink) {
            if ($this->waitForElement(WebDriverBy::xpath('//div[@data-test="loading-page-wrapper"]'), 0)) {
                throw new CheckRetryNeededException(2, 0);
            }

            return $this->checkErrors();
        }

        $this->closePopups();
        $this->saveResponse();
        $loginLink = $this->waitForElement(WebDriverBy::xpath('//a[@data-test="vip-join-login-button"]'), self::WAIT_TIMEOUT);

        try {
            $loginLink->click();
        } catch (UnrecognizedExceptionException $e) {
            $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), self::WAIT_TIMEOUT * 2);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "loginBtn" or @data-testid="loginBtn"]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$button) {
            if ($this->goToMyAccountPage()) {
                return true;
            }

            return $this->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields['Login']);

        if (!$passwordInput) {
            $button->click();

            $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 10);
            $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "loginBtn" or @data-testid="loginBtn"]'), 0);
            $this->saveResponse();

            if (!$passwordInput || !$button) {
                if ($this->goToMyAccountPage()) {
                    return true;
                }

                if ($this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Create your adult LEGO® account")]'), 0)) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                if ($message = $this->waitForElement(WebDriverBy::xpath("
                    //span[contains(text(), 'Whoops, we don’t recognise that username.')]
                "), 0)
                ) {
                    throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
                }

                return $this->checkErrors();
            }
        }

        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Shop is currently down for maintenance
        if ($message = $this->http->FindSingleNode("//img[@src = '/akamai_internal/errors/site_down.jpg']/@src")) {
            throw new CheckException("Shop is currently down for maintenance", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function goToMyAccountPage($timeout = 0)
    {
        $this->logger->notice(__METHOD__);
        // open header menu
        $link = $this->waitForElement(WebDriverBy::xpath('
            //button[@data-test = "util-bar-account-dropdown"]
            | //a[@data-test="header-account-cta"]
        '), $timeout);
        $this->saveResponse();

        if ($link) {
            $this->logger->debug("close cookies popup");
            $this->driver->executeScript("if (document.querySelector('[data-test=\"cookie-accept-all\"]')) document.querySelector('[data-test=\"cookie-accept-all\"]').click();");
            $this->saveResponse();
            $this->logger->debug("open menu");
            $this->driver->executeScript("document.querySelector('button[data-test=\"util-bar-account-dropdown\"], a[data-test=\"header-account-cta\"]').click()");
            $this->waitForElement(WebDriverBy::xpath("//*[self::a or self::span][contains(text(), 'Join VIP') or contains(text(), 'Join now')] | //a[contains(text(), 'My VIP Account') or contains(text(), 'VIP werden')]"), 0);

            try {
                $this->saveResponse();
            } catch (UnknownServerException $e) {
                $this->logger->error("UnknownServerException exception: " . $e->getMessage());
            }

            if ($this->waitForElement(WebDriverBy::xpath("//*[self::a or self::span][contains(text(), 'Join VIP') or contains(text(), 'Join now') or contains(text(), 'Become a LEGO® Insider!')]"), 0)) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return true;
        }// if ($link)

        return false;
    }

    public function Login()
    {
        $startTime = time();
        $time = time() - $startTime;
        $sleep = 20;
        $retry = false;

        while ($time < $sleep) {
            $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");
            // look for logout link
            $this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Change shipping country to United States')]"), 0);

            $this->closePopups();

            // ff bug fix
            if (
                $retry === false
                && $time > 10
                && $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "loginBtn" or @data-testid="loginBtn"]'), 0)
            ) {
                $retry = true;
                $button->click();
            }

            if ($this->goToMyAccountPage()) {
                return true;
            }

            $this->logger->notice("check errors");

            if ($this->parseQuestion()) {
                return false;
            }

            if ($message = $this->waitForElement(WebDriverBy::xpath("
                    //div[@id='invalidPasswordCnt']/p[contains(text(), 'Invalid credentials')]
                    | //*[self::span or self::div][contains(text(), 'Wrong username or password.')]
                    | //div[@id='unknownUsernameCnt']/p[contains(text(), 'That username is not known.')]
                    | //div[@id='invalidPasswordCnt']/p[contains(text(), 'Wrong password. Please check it and try again.')]
                    | //*[self::span or self::div][contains(text(), 'Your username and/or password do not match our records.')]
                "), 0)
            ) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            // Your username or password doesn't match our records.
            if ($message = $this->waitForElement(WebDriverBy::xpath('//div[@id="invalidUsernameOrPasswordCnt"]/p[contains(text(), "Your username or password doesn\'t match our records.")]'), 0)) {
                throw new CheckRetryNeededException(3, 10, $message->getText(), ACCOUNT_INVALID_PASSWORD);
            }// sometimes it's lie - provider bug fix
            // Your account has been locked!
            if ($message = $this->waitForElement(WebDriverBy::xpath('//div[@id="passwordLockedCnt"]/p[contains(text(), "Your account has been locked!")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
            }
            // Sorry! An unexpected error has occurred.
            if ($message = $this->waitForElement(WebDriverBy::xpath('//li[contains(text(), "Sorry! An unexpected error has occurred.")] | //p[contains(text(), "Unfortunately, something seems to have gone wrong with our LEGO")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            // Our LEGO ID® service is currently unavailable. Please check again later.
//            if ($this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "The link to this page may be incorrect or out of date.")]'), 0))
//                throw new CheckRetryNeededException(2, 10, "Our LEGO ID® service is currently unavailable. Please check again later.");

            //        $this->http->GetURL("https://shop.lego.com/en-US/");
            if ($this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Log out")] | //span[@data-test="vip-points"]'), 0)) {
                try {
                    $this->http->GetURL('https://shop.lego.com/en-US/MyAccount');
                } catch (UnexpectedJavascriptException $e) {
                    $this->logger->error("UnexpectedJavascriptException exception on saveResponse: " . $e->getMessage());
                    sleep(3);
                    $this->saveResponse();
                }

                return true;
            }

            sleep(1);
            $this->saveResponse();
            $time = time() - $startTime;
        }// while ($time < $sleep)

        $this->logger->notice("Last saved screen");
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if ($this->goToMyAccountPage()) {
            return true;
        }

        // retries
        if ($this->http->currentUrl() == 'https://account2.lego.com/en-us/login?ReturnUrl=http%3A%2F%2Fshop.lego.com%3A80%2Fen-US%2FVip2%2F%3FopenVipOptIn%3Dtrue'
            || $this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Points towards valuable rewards!')]"), 0)
        ) {
            throw new CheckRetryNeededException(3);
        }

        $this->saveResponse();
        // Unfortunately, something seems to have gone wrong with our LEGO® ID system.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Unfortunately, something seems to have gone wrong with our LEGO® ID system.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "WE CHANGED OUR TERMS OF SERVICE")]')) {
            $this->throwAcceptTermsMessageException();
        }

        if ($this->http->currentUrl() == 'https://www.lego.com/en-us') {
            try {
                $this->http->GetURL("https://www.lego.com/en-us/vip");
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("UnexpectedJavascriptException exception on saveResponse: " . $e->getMessage());
                sleep(3);
                $this->saveResponse();
            }

            if ($this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'I am already a VIP member')]"), 5)) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->saveResponse();
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $q = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We’ve sent an activation code to") or contains(text(), "We have sent a two-factor authentication code to your email.") or contains(text(), "We’ve sent a two-factor code to your email.") or contains(text(), "To keep your account safe, we want to make sure it")]'), 0);

        if (!$q) {
            $this->saveResponse();
        }

        $question = $this->http->FindSingleNode('//p[contains(text(), "We’ve sent an activation code to") or contains(text(), "We have sent a two-factor authentication code to your email.") or contains(text(), "We’ve sent a two-factor code to your email.") or contains(text(), "To keep your account safe, we want to make sure it")]');
        $otp = $this->waitForElement(WebDriverBy::xpath('//input[@id = "otp" or @id = "token" or @id = "code" or @name = "token"]'), 3);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "activate_submit" or @id = "twofactorcode_submit" or @data-testid="twofactorCodeSubmitButton" or contains(., "Continue")]'), 0);
        $this->saveResponse();

        if (!$otp || (!$q && !$question) || !$btn) {
            $this->logger->error("something went wrong");

            return false;
        }

        $question = $q ? $q->getText() : $question;

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }// if (!isset($this->Answers[$question]))

        $otp->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);
        $btn->click();

        sleep(5);
        $this->waitForElement(WebDriverBy::xpath('//a[@data-test="util-bar-rewards-center-link"] | //span[@data-testid = "twofactor-error"]'), 0);
        $this->saveResponse();

        if ($error = $this->http->FindSingleNode('//span[@data-testid = "twofactor-error"]')) {
            $this->holdSession();
            $this->AskQuestion($question, $error, "Question");

            return false;
        }

        return $this->goToMyAccountPage(self::WAIT_TIMEOUT);
    }

    public function ProcessStep($step)
    {
        return $this->parseQuestion();
    }

    public function Parse()
    {
        /**
         * @var $browser HttpBrowser
         */
        $browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
        $data = '{"page_data":{"text":["link.log out","link.log out link","text.lifetime points","text.redeemable points acct overview widget","text.max fan level","word.to","cms.month:january","cms.month:february","cms.month:march","cms.month:april","cms.month:may","cms.month:june","cms.month:july","cms.month:august","cms.month:september","cms.month:october","cms.month:november","cms.month:december","text.points display","text.point display","text.days","text.hours","text.minutes","text.seconds","error.unknown","button.submit"]},"model_data":{"user":{"me":{"properties":["country","date_created_iso","email_address","encrypted_ct_id","facebook_user_id","fan_rank","first_name","last_name","language","mobile_phone_number","photo_url","redeemable_points","segments","third_party_id","tier","total_points","username"],"query":{"type":"me"}}},"client":{"current":{"properties":["fan_levels"],"query":{"type":"current"}}}}}';
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json;charset=utf-8",
            "x-ct-app"     => "widget",
        ];
        $browser->PostURL('https://ct-prod.lego.com/request?widgetId=7566', $data, $headers);
        $response = $browser->JsonLog(null, 3, false, "redeemable_points");
        $user = $response->model_data->user->me[0] ?? null;

        if (!$user) {
            $this->logger->error("profile not found");

            if ($message = $browser->FindSingleNode('//p[contains(text(), "We’re currently performing site maintenance at the moment.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }

        if ($user->tier->currentLevel->title != 'Member') {
            $this->sendNotification("refs #17060. Need to check tier // RR");
        }

        // Balance - VIP POINTS
        $this->SetBalance($user->redeemable_points);
        // Points Earned Since Joining
        $this->SetProperty('LifetimePoints', number_format($user->total_points));
        // Name
        $this->SetProperty('Name', beautifulName("{$user->first_name} {$user->last_name}"));
        // VIP Card Number
        $this->SetProperty('Number', $user->mobile_phone_number ?? null);

        // Expiration Date  // refs #21125
        if ($this->Balance <= 0) {
            return;
        }

        $this->logger->info("Expiration date", ['Header' => 3]);

        try {
            $this->http->GetURL("https://www.lego.com/en-us/vip/rewards-center/account");
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage());
            $this->DebugInfo = "UnknownServerException";
        }
        $this->waitForElement(WebDriverBy::xpath('//th[contains(text(), "Points")]/ancestor::table[1]//tr[1]'), 5);
        $this->saveResponse();
        $transactions = $this->http->XPath->query('//th[contains(text(), "Points")]/ancestor::table[1]//tr[td]');
        $this->logger->debug("Total {$transactions->length} transactions were found");

        foreach ($transactions as $transaction) {
            // Last Activity
            $lastActivity = $this->http->FindSingleNode('td[1]/span[2]/span', $transaction);
            $this->SetProperty('LastActivity', $lastActivity);
            // Expiration Date
            $this->SetExpirationDate(strtotime("+18 months", strtotime($this->ModifyDateFormat($lastActivity))));

            break;
        }// foreach ($transactions as $transaction)
    }

    private function closePopups()
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->driver->executeScript("var cont = document.querySelector('button[data-test=\"age-gate-grown-up-cta\"]'); if (cont) cont.click();");
            $this->logger->debug("close cookies popup");
            $this->driver->executeScript("if (document.querySelector('[data-test=\"cookie-accept-all\"]')) document.querySelector('[data-test=\"cookie-accept-all\"]').click();");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3);
        }
    }
}
