<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerGiant extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public const WAIT_TIMEOUT = 7;

    // like as giant, stopshop

    public $REWARDS_PAGE_URL = 'https://giantfood.com';

    public static function FormatBalance($fields, $properties)
    {
        if (
            isset($properties['SubAccountCode'])
            && (
                strstr($properties['SubAccountCode'], "ASchoolRewards")
                || strstr($properties['SubAccountCode'], "Savings")
            )
        ) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        // Selenium settings

        $this->UseSelenium();
//        $this->disableImages();
        $this->usePacFile(false);
        $this->http->saveScreenshots = true;

//        $this->useGoogleChrome();

        $this->setProxyGoProxies();
        // it can works
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
        $this->setKeepProfile(true);
//        $this->http->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT);

//        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
//        $this->seleniumOptions->addHideSeleniumExtension = false;
//        $this->seleniumRequest->setOs("mac");
        ////        $this->http->SetProxy($this->proxyReCaptcha());
//        $this->http->setUserAgent(null);

        /*
        unset($this->State['Resolution']);
        unset($this->State['Fingerprint']);
        unset($this->State['UserAgent']);
        */

        if (!isset($this->State['UserAgent']) || !isset($this->State['Fingerprint']) || $this->attempt > 0) {
            $request = AwardWallet\Common\Selenium\FingerprintRequest::firefox();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(AwardWallet\Common\Selenium\FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $this->State['Fingerprint'] = $fingerprint->getFingerprint();
                $this->State['UserAgent'] = $fingerprint->getUseragent();
            }
        }

        if (isset($this->State['UserAgent'])) {
            $this->http->setUserAgent($this->State['UserAgent']);
        }

        if (isset($this->State['Fingerprint'])) {
            $this->logger->debug("set Fingerprint");
            $this->seleniumOptions->fingerprint = $this->State['Fingerprint'];
        }

//        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
//        $this->http->setDefaultHeader("User-Agent", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/604.5.6 (KHTML, like Gecko) Version/11.0.3 Safari/604.5.6");
//        $this->http->setUserAgent("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/604.4.7 (KHTML, like Gecko) Version/11.0.2 Safari/604.4.7");
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
//        $this->http->removeCookies();

        $this->driver->manage()->window()->maximize();

        $this->http->GetURL($this->REWARDS_PAGE_URL);

        $this->waitForElement(WebDriverBy::xpath('
            //button[contains(normalize-space(), "Sign In / Create Account")]
            | //button[contains(normalize-space(), "Sign In")]
            | //iframe[contains(@src, "https://geo.captcha-delivery.com")]
        '), 20);
        $this->saveResponse();

        $signInButton = $this->waitForElement(WebDriverBy::xpath('//button[contains(normalize-space(), "Sign In / Create Account")]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if (!$signInButton) {
            $signInButton = $this->waitForElement(WebDriverBy::xpath('//button[contains(normalize-space(), "Sign In")]'), 0);
            $this->saveResponse();
            $this->driver->executeScript("var popup = document.getElementsByClassName('optly-modal'); if (popup.length) popup[0].style.display = 'none';");

            if ($this->waitForElement(WebDriverBy::xpath('//iframe[contains(@src, "https://geo.captcha-delivery.com")]'), 0)) {
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
                $this->DebugInfo = self::ERROR_REASON_BLOCK;

                throw new CheckRetryNeededException(2, 0); // block workaround

                return false;
            }

            if ($signInButton) {
                $signInButton->click();
            }

            $signInButton = $this->waitForElement(WebDriverBy::xpath('//button[@id = "nav-sign-in"]'), self::WAIT_TIMEOUT);
        }

        if (!$signInButton) {
            return $this->checkErrors();
        }

        sleep(1);

        $signInButton->click();

        sleep(3);

        $login = $this->waitForElement(WebDriverBy::name('username'), self::WAIT_TIMEOUT);
        $pass = $this->waitForElement(WebDriverBy::name('password'), 0);
        $button = $this->waitForElement(WebDriverBy::id('sign-in-button'), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$button) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }
        sleep(1);
        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();

//        $mover = new MouseMover($this->driver);
//        $mover->logger = $this->logger;
//        $mover->enableCursor();
//        $mover->duration = rand(90000, 120000);
//        $mover->steps = rand(50, 70);
//        $mover->moveToElement($button);
//        $mover->click();

        sleep(3);

        $button->click();

        sleep(5);

        /*
        $i = 0;

        while (
            ($button = $this->waitForElement(WebDriverBy::id('sign-in-button'), 0))
            && $i < 2
        ) {
            $message = $this->http->FindSingleNode('
                //form[@name = "stLoginForm"]/descendant::p[contains(@class, "message-box_message")]/descendant::span
                | //form[descendant::input[@id="login-password"]]/descendant::p[contains(@class, "message-box_message")]/descendant::span
            ');

            if ($message) {
                $this->logger->error("Message: {$message}");
            }

            $this->saveResponse();
//            $mover->click();
            $button->click();
            $i++;
            sleep(5);
        }
        */

        $this->waitForElement(WebDriverBy::xpath('
            //div[@id="aria_alert-body"]/span
            | //form[@name = "stLoginForm"]/descendant::p[contains(@class, "message-box_message")]/descendant::span
            | //p[@class="st_sub-title"]
            | //div[@id="aria_alert-body"]/span[normalize-space() = "You have been logged in and we have saved all items in your cart."]
        '), 15);

        // save page to logs
        $this->saveResponse();

        if ($this->waitForElement(WebDriverBy::xpath('//p[
                    contains(normalize-space(text()), " and Peapod have merged. Sign in to Peapod to migrate any in-progress orders, past purchases and account information.")
                    or contains(normalize-space(text()), " and Peapod have merged. Enter your Peapod password to migrate any in-progress orders, past purchase history, and account information.")
                ]'), 0)
        ) {
            $this->throwProfileUpdateMessageException();
        }

        if (
            $this->http->FindSingleNode('//div[@id="aria_alert-body"]/span[normalize-space() = "You have been logged in and we have saved all items in your cart."]')
            || $this->http->FindPreg('/You\'ve been logged in and your cart items have been restored\./')
            || $this->waitForElement(WebDriverBy::xpath('//div[@id="aria_alert-body"]/span[normalize-space() = "You have been logged in and we have saved all items in your cart."]'), 0)
        ) {
            if ($okBtn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "alert-button_primary-button"]'), 0)) {
                sleep(3);
                $this->driver->executeScript("document.getElementById('alert-button_primary-button').click()");

                $this->waitForElement(WebDriverBy::xpath('
                    //h2[contains(text(), "Welcome,")]
                '), 10);

                $this->saveResponse();
            }

            $cookies = $this->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        }

        /*
        if ($this->http->Response['code'] == 403) {
            return false;
        }

        $this->selenium();
        */

        return true;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            switch (rand(0, 1)) {
                case 0:
                    $this->DebugInfo = 'CHROME 84';
                    $selenium->useGoogleChrome();

                    break;

                case 1:
                    $this->DebugInfo = 'CHROMIUM 80';
                    $selenium->useChromium();

                    break;
            }

            $selenium->http->SetProxy($this->proxyReCaptcha());

            if ($this->attempt > 0) {
                $selenium->http->setRandomUserAgent(10, true, true, false, false, false);
            }

            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->usePacFile(false);

            $selenium->http->saveScreenshots = true;
            $selenium->http->removeCookies();
            $selenium->http->start();
            $selenium->Start();
            /*
            $selenium->driver->manage()->window()->maximize();
            */
            $selenium->http->GetURL($this->REWARDS_PAGE_URL);

            $signInButton = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(normalize-space(),"Sign In / Create Account")]'),
                self::WAIT_TIMEOUT);
            $selenium->saveResponse();
            sleep(1);

            if (!$signInButton) {
                // save page to logs
                $this->saveToLogs($selenium);

                return $this->checkErrors();
            }
            $signInButton->click();

            $login = $selenium->waitForElement(WebDriverBy::name('username'), self::WAIT_TIMEOUT);
            $pass = $selenium->waitForElement(WebDriverBy::name('password'), 0);
            $button = $selenium->waitForElement(WebDriverBy::id('sign-in-button'), 0);

            if (!$login || !$pass || !$button) {
                $this->logger->error("something went wrong");
                // save page to logs
                $this->saveToLogs($selenium);

                return $this->checkErrors();
            }
            sleep(1);
            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);
            $this->saveToLogs($selenium);
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('
                //div[@id="aria_alert-body"]/span
                | //form[@name = "stLoginForm"]/descendant::p[contains(@class, "message-box_message")]/descendant::span
                | //p[@class="st_sub-title"]
                //div[@id="aria_alert-body"]/span[contains(normalize-space(), "ve been logged in and ")]
            '), 15);

            // save page to logs
            $this->saveToLogs($selenium);

            if ($selenium->waitForElement(WebDriverBy::xpath('//p[
                    contains(normalize-space(text()), " and Peapod have merged. Sign in to Peapod to migrate any in-progress orders, past purchases and account information.")
                    or contains(normalize-space(text()), " and Peapod have merged. Enter your Peapod password to migrate any in-progress orders, past purchase history, and account information.")
                ]'), 0)
            ) {
                $this->throwProfileUpdateMessageException();
            }

            if ($this->http->FindSingleNode('//div[@id="aria_alert-body"]/span[contains(normalize-space(), "ve been logged in and ")]')) {
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo
        }

        return true;
    }

    public function Login()
    {
        $message = $this->http->FindSingleNode('
            //form[@name = "stLoginForm"]/descendant::p[contains(@class, "message-box_message")]/descendant::span
            | //form[descendant::input[@id="login-password"]]/descendant::p[contains(@class, "message-box_message")]/descendant::span
        ');

        if ($message) {
            $this->logger->error("Message: {$message}");

            if (
                strstr($message, 'Please enter a valid email or username.')
                || strstr($message, 'The sign in information you entered does not match our records. Please re-enter your information and try again.')
                || $message == 'Invalid username or password. Please try again.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Your account is currently not active. Please call Stop & Shop Customer Care at ')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        if (
            !$this->http->FindPreg('/ve been logged in/')
            && $this->http->FindPreg('/<span class="loading-spinner" style="">/')
        ) {
            return false;
        }

        if (
            ($this->http->getCookieByName('_uetsid') || $this->http->FindPreg('/ve been logged in/') || $this->http->FindSingleNode('//button[@id = "header-account-button" and contains(., "Account")]'))
            && $this->loginSuccessful()
        ) {
            return true;
        }

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
//            throw new CheckRetryNeededException(2, 1); // block workaround
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'), 0);
        $cardNumber = $response->response->retailerCard->cardNumber ?? null;

        if (!isset($cardNumber)) {
            return;
        }

        $storeNumber = $response->response->refData->deliveryServiceLocation->storeNumber ?? null;

        $this->SetProperty("Number", $cardNumber);
        $this->http->GetURL($this->REWARDS_PAGE_URL . "/apis/loyaltyaccount/v3/GNTL/{$cardNumber}");
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));

        //Name
        $firstName = $response->firstName ?? '';
        $lastName = $response->lastName ?? '';
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));

        // AccountID: 396420, 511937, 1293957, 4433115
        if (
            // from where?
            !isset($storeNumber)
            && isset($response->storeNumber)
            && $response->storeNumber == '0000'
        ) {
            $storeNumber = '0662';
        }

        if (!isset($storeNumber)) {
            $this->logger->error("store number not found");

            return;
        }

        if (strlen($storeNumber) === 3) {
            $storeNumber = '0' . $storeNumber;
        } elseif (strlen($storeNumber) === 2) {
            $storeNumber = '00' . $storeNumber;
        }

        // detects program
        $this->http->GetURL($this->REWARDS_PAGE_URL . "/apis/rewards/v1/preferences/GNTL/{$cardNumber}");
        $programInfo = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));

        if (!isset($programInfo->value)) {
            $this->logger->error("program not found");

            return;
        }

        $program = $programInfo->value;

        if (!in_array($program, ["flex", "fuel"])) {
            $this->sendNotification("refs #19166: Unknown program : {$program}");

            return;
        }

        $this->http->GetURL($this->REWARDS_PAGE_URL . "/apis/balances/program/v1/balances/{$cardNumber}?details=true&storeNumber={$storeNumber}");
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));
        $balances = $response->balances ?? [];
        $i = 0;

        if ($program == "fuel") {
            $this->SetBalanceNA();
        }

        foreach ($balances as $reward) {
            $name = $reward->name ?? null;
            $balance = $reward->balance ?? null;
            // Balance - Available Points
            if (
                $program == "flex"
                && (in_array($name, [
                    "Flex Points",
                    "SS GO Points",
                    "Flex Rewards for Stop &amp; Shop",
                ])
                )
            ) {
                if ($i > 0) {
                    $this->sendNotification('Multiple balances');

                    return;
                }

                // Balance - Available Points
                $this->SetBalance($balance);
                // Points Expiring
                $this->SetProperty('ExpiringBalance', $reward->detail->gasPoints[0]->balance ?? null);
                // Expiration Date
                if (
                    !empty($reward->detail->gasPoints[0]->expirationDate)
                    && strtotime($reward->detail->gasPoints[0]->expirationDate)
                ) {
                    $this->SetExpirationDate(strtotime($reward->detail->gasPoints[0]->expirationDate));
                }

                $i++;
            }

            // Grocery Savings
            // SS Grocery Dollars / Flex Grocery Dollars
            if (strstr($name, " Grocery Dollars")) {
                if (empty($balance)) {
                    $this->logger->notice("[Grocery Savings]: do not collect zero balance");

                    return;
                }

                $savings = [
                    "Code"            => $this->AccountFields['ProviderCode'] . "GrocerySavings",
                    "DisplayName"     => "Grocery Savings",
                    "Balance"         => $balance / 100,
                    "ExpiringBalance" => $reward->detail->gasPoints[0]->balance ?? null,
                ];

                if (
                    !empty($reward->detail->gasPoints[0]->expirationDate)
                    && strtotime($reward->detail->gasPoints[0]->expirationDate)
                ) {
                    $savings["ExpirationDate"] = strtotime($reward->detail->gasPoints[0]->expirationDate);
                }

                $this->AddSubAccount($savings);
            }// if ($name === "Flex Grocery Dollars")
        }// foreach ($balances as $reward)

        // A+ School Rewards
        $this->http->RetryCount = 0;
        $this->http->GetURL($this->REWARDS_PAGE_URL . "/apis/aplus/v1/designated/schools/{$cardNumber}");
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));
        $schools = $response->schools ?? [];
        $balance = null;

        foreach ($schools as $school) {
            if (!empty($school)) {
                $this->http->GetURL($this->REWARDS_PAGE_URL . "/apis/aplus/v1/school/details/{$school}");
                $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));
                $yearToDateTotal = $response->yearToDateTotal ?? null;

                if (!empty($yearToDateTotal)) {
                    $balance += $yearToDateTotal;
                }
            }
        }

        if (!empty($balance)) {
            $this->AddSubAccount([
                "Code"        => $this->AccountFields['ProviderCode'] . "ASchoolRewards",
                "DisplayName" => "A+ School Rewards",
                "Balance"     => $balance,
            ]);
        }

        // Gas Rewards
        $this->http->GetURL($this->REWARDS_PAGE_URL . "/apis/balances/program/v1/gas/points/{$cardNumber}?details=true&storeNumber={$storeNumber}");
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));

        if (empty($response->calculatedRate)) {
            $this->logger->notice("[Gas Savings]: do not collect zero balance");

            return;
        }

        $gasSavings = [
            "Code"            => $this->AccountFields['ProviderCode'] . "GasSavings",
            "DisplayName"     => "Gas Savings",
            "Balance"         => $response->calculatedRate,
        ];

        if (!empty($response->gasPoints)) {
            $gasSavings["ExpiringBalance"] = $response->gasPoints[0]->balanceToExpire;
            $gasSavings["ExpirationDate"] = strtotime($response->gasPoints[0]->expirationDate);
        }

        $this->AddSubAccount($gasSavings);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;

        $this->http->GetURL($this->REWARDS_PAGE_URL . "/api/v5.0/user/profile");
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));

        if (!empty($response->response->retailerCard->cardNumber)) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We’re working to bring you an even better shopping experience. Please check back soon or call
        if (
            $message = $this->http->FindSingleNode('//p[
                    contains(normalize-space(), "We\'re working hard to bring you an even better shopping experience")
                    or contains(normalize-space(), "We’re working hard to correct a technical issue. Please check back later.")
                    or contains(normalize-space(), "We\'ve encountered a server issue and we\'re working to correct it. Please check back soon.")
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function saveToLogs($selenium)
    {
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
}
