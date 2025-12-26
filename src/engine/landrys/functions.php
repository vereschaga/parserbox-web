<?php

// refs #2063, landrys

use AwardWallet\Engine\ProxyList;

class TAccountCheckerLandrys extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'landrysRewards')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

//        $this->setProxyMount();
        $this->setProxyGoProxies();

        if ($this->attempt == 0) {
            $this->useFirefox();
            $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $this->seleniumOptions->addHideSeleniumExtension = false;
            $this->setKeepProfile(true);
        } else {
            $this->useChromePuppeteer();
            $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
            $this->seleniumOptions->addHideSeleniumExtension = false;
            $this->seleniumOptions->userAgent = null;
        }

        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        try {
            $this->http->GetURL("https://www.landrysselect.com/summary/", [], 30);
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("UnknownErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('The Email field is not a valid e-mail address.', ACCOUNT_INVALID_PASSWORD);
        }
        $this->http->GetURL('https://www.landrysselect.com/login/');

        if ($acceptCookiesBtn = $this->waitForElement(WebDriverBy::xpath('//button[starts-with(@class, "eupopup-button")]'), 5)) {
            $acceptCookiesBtn->click();
        }

        $this->waitForElement(WebDriverBy::xpath('
            //input[@id = "LoginPostbackData_Email"]
            | //span[contains(text(), "We are checking your browser...")]
            | //input[@value = \'Verify you are human\'] | //div[@id = \'turnstile-wrapper\']//iframe
        '), 10);

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $this->waitForElement(WebDriverBy::xpath('
                //input[@id = "LoginPostbackData_Email"]
                | //span[contains(text(), "We are checking your browser...")]
                | //input[@value = \'Verify you are human\'] | //div[@id = \'turnstile-wrapper\']//iframe
            '), 10);
            $this->saveResponse();
        }

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Ex. pat@example.com"]'), 0);
        $pwd = $this->waitForElement(WebDriverBy::xpath('//input[@type="password"]'), 0);

        if (!isset($login, $pwd)) {
            $this->saveResponse();

            return $this->checkErrors();
        }

        $x = $login->getLocation()->getX();
        $y = $login->getLocation()->getY() - 200;
        $this->driver->executeScript("window.scrollBy($x, $y)");
        $this->saveResponse();

        $login->clear();
        $login->sendKeys($this->AccountFields['Login']);
        $pwd->clear();
        $pwd->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        /*
        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->driver->executeScript("
            document.getElementById('LoginPostbackData_AccessToken').value = '$captcha';
        ");
        */
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Login") and not(@disabled)]'), 2);

        if (!$btn) {
            return false;
        }

        $btn->click();

        try {
            sleep(1);
            $this->logger->notice("one more click");
            $btn->click();
        } catch (Exception $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        return true;
        $apiKey = $this->http->FindPreg("/apiKey:\s*\"([^\"]+)/");
        $body = $this->http->Response['body'];

        if (!$apiKey) {
            return $this->checkErrors();
        }

        $data = [
            "returnSecureToken" => true,
            "email"             => $this->AccountFields['Login'],
            "password"          => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"           => "application/json",
            "Content-Type"     => "application/json",
            "Origin"           => "https://www.landrysselect.com",
            "x-client-version" => "Chrome/JsCore/9.5.0/FirebaseCore-web",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key={$apiKey}", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->idToken)) {
            $message = $response->error->message ?? null;

            if ($message == 'INVALID_EMAIL') {
                throw new CheckException("Couldn't login. Please contact support", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'EMAIL_NOT_FOUND') {
                throw new CheckException("User email not found", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'INVALID_PASSWORD') {
                throw new CheckException("Wrong password", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return $this->checkErrors();
        }

        $this->http->SetBody($body);

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'Login')]") && !$apiKey) {
            return $this->checkErrors();
        }

        $this->http->setCookie('token', $response->idToken, 'www.landrysselect.com', '/login');

        $this->http->FormURL = 'https://www.landrysselect.com/login/Login/';

        $this->http->SetInputValue('LoginPostbackData.Email', $this->AccountFields['Login']);
        $this->http->SetInputValue('LoginPostbackData.Password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('LoginPostbackData.RememberMe', 'true');
        $this->http->SetInputValue('LoginPostbackData.AccessToken', $response->idToken);
        $this->http->unsetInputValue('ExternalLoginPostbackData.Email');
        $this->http->unsetInputValue('ExternalLoginPostbackData.AccessToken');
        $this->http->unsetInputValue('ExternalLoginPostbackData.Provider');
        $this->http->unsetInputValue('ExternalLoginPostbackData.FirstName');
        $this->http->unsetInputValue('ExternalLoginPostbackData.LastName');

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->http->SetInputValue("LoginPostbackData.Token", $captcha);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.landrysselect.com/';

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $selectors = [
            '//a[@id = "logout"]',
            '//span[contains(text(), "Sign Out")]',
            '//span[contains(@id, "-error-msg-")]',
            '//span[contains(@class, "text-danger") and normalize-space(.) != ""]',
            '//li[contains(text(), "Incorrect Username/Password. Please try again.")]',
            '//span[contains(text(), "The Email field is not a valid e-mail address.")]',
            '//p[contains(text(), "You will soon be a Landry\'s Select Club Member and be able to take advantage of all the perks.") or contains(text(), "There was an error processing your request.")]',
            '//h2[contains(text(), "Create Profile Information") or contains(text(), "Complete your registration with a Membership number!")]',
            '//h5[contains(text(), "Your password has expired.")]',
        ];
        $cloudflare = [
            ...$selectors,
            '//input[@value = "Verify you are human"]',
            '//div[@id = "turnstile-wrapper"]//iframe',
        ];
        $el = $this->waitForElement(WebDriverBy::xpath(implode(" | \n", $cloudflare)), 12);
        $this->saveResponse();

        if ($verify = $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human']"), 0)) {
            $verify->click();
        }

        if ($iframe = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'turnstile-wrapper']//iframe"), 0)) {
            $this->driver->switchTo()->frame($iframe);

            $this->saveResponse();

            if ($captcha = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'ctp-checkbox-container']/label"), 10)) {
                $this->saveResponse();
                $captcha->click();
                $this->logger->debug("delay -> 15 sec");
                $this->saveResponse();
                sleep(15);

                $this->driver->switchTo()->defaultContent();
                $this->saveResponse();
            }
        }

        $this->waitForElement(WebDriverBy::xpath(implode(" | \n", $selectors)), 20);
        $this->saveResponse();
        // Access is successful
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "mainRewardCard")]//p[contains(., "away from your")]/strong[contains(text(), "point")]'), 10);
            $this->saveResponse();

            return true;
        }

        if ($error = $this->http->FindSingleNode('(//span[contains(@id, "-error-msg-") and string-length(normalize-space()) > 2])[1] | //span[contains(@class, "text-danger") and normalize-space(.) != ""]')) {
            $this->captchaReporting($this->recognizer);
            $this->logger->error("[Error]: {$error}");

            if (str_contains($error, "Couldn't login. Please contact support")
                || str_contains($error, 'User email not found')
                || str_contains($error, 'Wrong password')
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, "Access to this account has been temporarily disabled due to many failed login attempts.")) {
                throw new CheckException($error, ACCOUNT_LOCKOUT);
            }

            if (strstr($error, "We are sorry, an error occurred, please try again later!")) {
                throw new CheckRetryNeededException(2, 10, $error);
            }

            $this->DebugInfo = $error;

            return false;
        }

        // Incorrect Username/Password. Please try again.
        if ($message = $this->http->FindSingleNode('//li[contains(text(), "Incorrect Username/Password. Please try again.")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The Email field is not a valid e-mail address.
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "The Email field is not a valid e-mail address.")] | //h5[contains(text(), "Your password has expired.")]')) {
            $this->captchaReporting($this->recognizer);

            // refs #23563
            if (strstr($error, "Your password has expired.")) {
                throw new CheckRetryNeededException(2, 0, $error, ACCOUNT_INVALID_PASSWORD);
            }

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // You will soon be a Landry's Select Club Member and be able to take advantage of all the perks.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "You will soon be a Landry\'s Select Club Member and be able to take advantage of all the perks.")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "There was an error processing your request.")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("There was an error processing your request.", ACCOUNT_PROVIDER_ERROR);
        }

        // Create Profile Information
        if (
            $this->http->FindSingleNode('//h2[contains(text(), "Create Profile Information")]')
            || $this->http->FindSingleNode('//h2[contains(text(), "Complete your registration with a Membership number!")]')
        ) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h1[contains(text(), "Welcome")]', null, true, "/Welcome\s*([^!]+)/")));
        // Account #
        $this->SetProperty("Number", $this->http->FindSingleNode('//p[contains(@class, "accountNumber")]'));
        // Balance - LSC Points/BTC Points
        $balance = $this->http->FindSingleNode('//div[contains(@class, "mainRewardCard")]//p[contains(@class, "points")]', null, true, "/([^\(]+)/");
        $this->SetBalance($balance);
        // You are ... away from your next reward!
        $this->SetProperty("NeededForNextReward", $this->http->FindSingleNode('//div[contains(@class, "mainRewardCard")]//p[contains(., "away from your")]/strong[contains(text(), "point")]', null, false, "/(.+) point/ims"));
        // Your Rewards Available
        $rewardBalance = $this->http->FindSingleNode('//p[contains(@class, "available")]/preceding-sibling::p[1]');
        $this->SetProperty("CombineSubAccounts", false);

        if (isset($rewardBalance) && $rewardBalance > 0) {
            $this->AddSubAccount([
                'Code'        => 'landrysRewards',
                'DisplayName' => "Reward Balance",
                'Balance'     => $rewardBalance,
            ], true);
        }

        // Expiration Date   // refs #6318
        if ($this->Balance > 0) {
            $this->logger->info('Expiration date', ['Header' => 3]);
            // Get all transactions
            $transactions = $this->http->XPath->query('//div[contains(@class, "rewards-points-container")]');
            $this->logger->debug("Total {$transactions->length} transactions were found");
            $i = 0;

            foreach ($transactions as $transaction) {
                $type = $this->http->FindSingleNode(".//div[div[@class = 'points-rewards value-earned']]", $transaction);
                $this->logger->debug($type);

                if (!stristr($type, 'point')) {
                    $this->logger->notice("skip not Points transaction");

                    continue;
                }// if (stristr($type, 'point'))
                $date = $this->http->FindSingleNode(".//p[@class = 'date']", $transaction);
                $points = $this->http->FindSingleNode(".//div[@class = 'points-rewards value-earned']/p", $transaction);
                $this->logger->debug("[{$date}]: $points");

                if (isset($date) && isset($points) && $points > 0) {
                    $date = DateTime::createFromFormat('M, d Y', $date);

                    if (!$date) {
                        $this->logger->notice("Skip bade date");

                        continue;
                    }// if (!$date)
                    $pointsEarned[$i] = [
                        'date'   => $date->getTimestamp(),
                        'points' => $points,
                    ];
                    $balance -= $pointsEarned[$i]['points'];
                    $this->logger->debug("Balance -> $balance");

                    if ($balance <= 0) {
                        $this->logger->debug("Date: {$pointsEarned[$i]['date']} ");
                        $this->logger->debug("Expiration Date: ".strtotime("+12 month", $pointsEarned[$i]['date'])." ");
                        //# Earning Date     // refs #4936
                        $this->SetProperty("EarningDate", date("F, d Y", $pointsEarned[$i]['date']));
                        //# Expiration Date
                        $this->SetExpirationDate(strtotime("+12 month", $pointsEarned[$i]['date']));
                        //# Points to Expire
                        $balance += $pointsEarned[$i]['points'];

                        for ($k = $i - 1; $k >= 0; $k--) {
                            if (isset($pointsEarned[$k]['date'])
                                && $pointsEarned[$i]['date'] == $pointsEarned[$k]['date']) {
                                $balance += $pointsEarned[$k]['points'];
                            }
                        }
                        $this->SetProperty("ExpiringBalance", $balance);

                        break;
                    }// if ($balance <= 0)
                    $i++;
                }//if (isset($date) && isset($points) && $points > 0)
            }// foreach ($transactions as $transaction)
        }//if ($this->Balance > 0)
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/grecaptcha.enterprise.execute\('([^\']+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 30;
        $parameters = [
            "pageurl"   => 'https://www.landrysselect.com/login/',
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
            "version"   => "v3",
            "action"    => "homepage",
            "min_score" => $this->attempt == 0 ? 0.3 : 0.9,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[@id = 'logout'] | //span[contains(text(), \"Sign Out\")]")) {
            return true;
        }

        return false;
    }
}
