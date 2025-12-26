<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerNordstrom extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public const WAIT_TIMEOUT = 30;

    private const REWARDS_PAGE_URL = 'https://www.nordstrom.com/my-account/rewards';

    private const LOGOUT_XPATH = '//a[@id="controls-account-links" and starts-with(normalize-space(),"Hi,")] | //span[@id="header-rewards-id"]';
    private const INPUT_CODE_XPATH = "//input[@name='code-entry' or @name = 'code']";

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'nordstromNote')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $this->useFirefox();
        $this->setKeepProfile(true);
        $request = FingerprintRequest::firefox();
        $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 5;
        $request->platform = 'Linux x86_64';
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if ($fingerprint !== null) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->http->setUserAgent($fingerprint->getUseragent());
        }

        $this->disableImages();
        $this->useCache();
        $this->http->saveScreenshots = true;
        $this->http->FilterHTML = false;

        $this->setProxyGoProxies();

        /*
        if ($this->attempt !== 0) {
            $this->setProxyDOP(Settings::DATACENTERS_NORTH_AMERICA);
        }
        */
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;

        try {
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        } catch (
            NoSuchWindowException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | Facebook\WebDriver\Exception\JavascriptErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage());
        }
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful() && !$this->waitForElement(WebDriverBy::name('email'), 0)) {
            return true;
        }

        $this->logger->debug("IsLoggedIn: false");

        if ($this->http->FindSingleNode("//title[contains(text(), 'http://localhost:4448/start')]")) {
            throw new CheckRetryNeededException(3, 0);
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email.", ACCOUNT_INVALID_PASSWORD);
        }

        try {
            $this->http->removeCookies();
            $this->http->GetURL('https://secure.nordstrom.com/signin');
        } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();

            if ($this->waitForElement(WebDriverBy::xpath("
                | //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                | //title[contains(text(), 'http://localhost:4448/start?text={$this->AccountFields['ProviderCode']}+%7C+{$this->AccountFields['Login']}+%7C+')]
                | //p[contains(text(), 'Health check')]
            "), 0)
                || $this->http->FindSingleNode("
                    //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                    | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                ")
            ) {
                $this->http->GetURL('https://secure.nordstrom.com/signin');

                /*
                $this->saveResponse();
                throw new CheckRetryNeededException(3, 5);
                */
            }
        } catch (
            NoSuchDriverException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\JavascriptErrorException
            $e
        ) {
            $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 1);
        }

        try {
            $this->logger->debug("first try");

            $this->logger->debug("find login");
            $this->waitForElement(WebDriverBy::xpath('//input[@name = "email"] | //h1[contains(text(), "We\'ve noticed some unusual activity")]'), self::WAIT_TIMEOUT);
            $login = $this->waitForElement(WebDriverBy::name('email'), 2); // timeout required, element disappears frequently
            $this->saveResponse();

            if (!$login) {
                $this->logger->error("something went wrong");

                return $this->checkErrors();
            }

            $this->logger->debug("set Login");
            $this->saveResponse();

            // login field sometimes not filled correctly because of slow page loading, so we repeat it
            $inputAttempts = 0;
            $value = '';

            do {
                $inputAttempts++;

                try {
                    $value = $login->click()
                        ->clear()
                        ->sendKeys($this->AccountFields['Login'])
                        ->getAttribute('value');
                } catch (Facebook\WebDriver\Exception\StaleElementReferenceException | StaleElementReferenceException $e) {
                    $this->logger->error("StaleElementReferenceException: " . $e->getMessage());
                    $login = $this->waitForElement(WebDriverBy::name('email'), self::WAIT_TIMEOUT);
                }
            } while ($login && $inputAttempts < 4 && $value != $this->AccountFields['Login']);

            $pass = $this->waitForElement(WebDriverBy::name('password'), 0);

            if (!$pass) {
                $this->logger->notice("Form with login");
                $button = $this->waitForElement(WebDriverBy::xpath('//button[descendant::text()[normalize-space() = "Next"]]'), 0);
                $this->saveResponse();

                if (!$button) {
                    $this->logger->error("something went wrong");

                    return $this->checkErrors();
                }
                $button->click();
                $pass = $this->waitForElement(WebDriverBy::name('password'), self::WAIT_TIMEOUT);

                // 'Did you mean ...@gmail.com' - message under login field workwround
                if (
                    !$pass
                    && ($button = $this->waitForElement(WebDriverBy::xpath('//button[descendant::text()[normalize-space() = "Next"]]'), 0))
                ) {
                    $this->logger->notice('Did you mean ...@gmail.com');
                    $this->saveResponse();
                    $button->click();
                    $pass = $this->waitForElement(WebDriverBy::name('password'), self::WAIT_TIMEOUT);
                }

                $this->saveResponse();
            }
            $button = $this->waitForElement(WebDriverBy::xpath('//button[descendant::text()[normalize-space() = "Sign in"] or normalize-space() = "Sign In"]'), 0);

            if (!$pass || !$button) {
                if ($this->parseQuestion()) {
                    return false;
                }

                $email = $this->http->FindSingleNode('//form[@id="create-account"]/descendant::span[strong[normalize-space() = "Email"]]/text()');
                $this->logger->debug("[Email]: {$email}");

                if (
                    $this->http->FindSingleNode('//h3[normalize-space() = "Create Account"] | //h1[span[normalize-space() = "Create Account"]]')
                    && $this->http->FindSingleNode('//p[contains(text(), "By creating an account, you agree to our")]')
                    && strtolower($email) == strtolower($this->AccountFields['Login'])
                ) {
                    throw new CheckException("You do not have an account yet.", ACCOUNT_INVALID_PASSWORD);
                }

                if (
                $this->http->FindSingleNode('//div[@id = "banner-error"]') === ''
                || $this->http->FindSingleNode('//*[self::b or self::div][normalize-space() = "We hit a snag, but we’re working on it."]')
            ) {
                    if ($userName = $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_XPATH), 0)) {
                        $this->driver->executeScript('document.querySelector(\'a#controls-account-links\').click();');

                        if ($signOut = $this->waitForElement(WebDriverBy::xpath('//div[span[contains(text(), "Sign Out")]]'), self::WAIT_TIMEOUT)) {
                            $signOut->click();
                            sleep(3);
                            $this->saveResponse();
                        }

                        $login = $this->waitForElement(WebDriverBy::name('email'), self::WAIT_TIMEOUT);
                        $this->saveResponse();

                        if (!$login) {
                            $this->logger->error("something went wrong");

                            return $this->checkErrors();
                        }
                        $this->logger->debug("set Login");
                        $login->click();
                        $login->sendKeys($this->AccountFields['Login']);
                        $button = $this->waitForElement(WebDriverBy::xpath('//button[descendant::text()[normalize-space() = "Next"]]'), 0);
                        $this->saveResponse();

                        if (!$button) {
                            $this->logger->error("something went wrong");

                            return $this->checkErrors();
                        }
                        $button->click();
                        $pass = $this->waitForElement(WebDriverBy::name('password'), self::WAIT_TIMEOUT);
                        $this->saveResponse();
                    } elseif ($message = $this->http->FindSingleNode('//*[self::b or self::div][normalize-space() = "We hit a snag, but we’re working on it."]')) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }
                }

                return $this->checkErrors();
            }
            $this->logger->debug("set Password");
            $pass->sendKeys($this->AccountFields['Pass']);
            $this->logger->debug("set Remember Me");
            $this->driver->executeScript('document.querySelector(\'input[type = "checkbox"]\').checked = true;');
            $this->saveResponse();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 1);
        }

        if ($contBtn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "meet-new-account-next-button"]'), 0)) {
            $contBtn->click();
            sleep(1);
            $this->saveResponse();
        }

        $button->click();

        return true;
    }

    public function Login()
    {
        sleep(3);

        try {
            $this->waitForElement(WebDriverBy::xpath(
                self::LOGOUT_XPATH . '
                | //div[@id="banner-error"]
                | //button[descendant::text()[normalize-space() = "Send Code"]]
                | //text()[normalize-space() = "Your account has been locked"]
                | //span[@data-cy="wrong-user-credentials-error"]
                | //h3[normalize-space() = "Create Account"]

            '), 15);
        } catch (StaleElementReferenceException | Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            sleep(3);
            $this->waitForElement(WebDriverBy::xpath(
                self::LOGOUT_XPATH . '
                | //div[@id="banner-error"]
                | //button[descendant::text()[normalize-space() = "Send Code"]]
                | //text()[normalize-space() = "Your account has been locked"]
                | //span[@data-cy="wrong-user-credentials-error"]
                | //h3[normalize-space() = "Create Account"]
                | //span[@data-cy="hit-a-snag-error"]
            '), 15);
        }
        $this->saveResponse();

        $this->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));

        $message = $this->http->FindSingleNode('
            //div[@id="banner-error"]
            | //text()[normalize-space() = "Your account has been locked"]
            | //span[@data-cy="wrong-user-credentials-error"]
            | //span[@data-cy="hit-a-snag-error"]
        ');
        $this->logger->error("Message: {$message}");

        if ($message) {
            if (
                strstr($message, 'Your email or password wasn’t recognized')
                || strstr($message, 'Your email or password wasn\'t recognized')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Your account has been locked')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, 'We hit a snag, but we’re working on it')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//h3[contains(text(), "Something’s wrong with our site. We’re on it!")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        try {
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        } catch (NoSuchDriverException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 5);
        }

        if (!strstr($this->http->currentUrl(), "rewards")) {
            try {
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            } catch (NoSuchDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage());

                throw new CheckRetryNeededException(2, 5);
            }

            if ($this->parseQuestion()) {
                return false;
            }

            $this->http->RetryCount = 0;

            try {
                $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
                $this->saveResponse();
            } catch (
                NoSuchWindowException
                | Facebook\WebDriver\Exception\WebDriverCurlException
                | Facebook\WebDriver\Exception\UnknownErrorException
                | Facebook\WebDriver\Exception\JavascriptErrorException
                $e
            ) {
                $this->logger->error("Exception: " . $e->getMessage());
            }
            $this->http->RetryCount = 2;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->http->FindSingleNode('//span[normalize-space() = "JOIN NOW"]')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $input = $this->waitForElement(WebDriverBy::xpath(self::INPUT_CODE_XPATH), self::WAIT_TIMEOUT);
        $buttonSend = $this->waitForElement(WebDriverBy::xpath("//button[descendant::text()[normalize-space() = 'Verify and Sign in'] or descendant::text()[normalize-space() = 'Next']]"), 0);
        $this->saveResponse();

        if (!$input || !$buttonSend) {
            return false;
        }
        $input->clear();
        $input->sendKeys($this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);
        $buttonSend->click();

        sleep(5);

        $this->waitForElement(WebDriverBy::xpath(
            self::LOGOUT_XPATH . '
            | //div[@id="banner-error"]
            | //p[contains(normalize-space(), "Please check your code and try again.")]
            | //p[contains(normalize-space(), "Check the code we sent you and try again.")]
        '), 0);

        $this->saveResponse();

        $error = $this->http->FindSingleNode('
            //div[@id="banner-error"] 
            | //p[contains(normalize-space(), "Please check your code and try again.")]
            | //p[contains(normalize-space(), "Check the code we sent you and try again.")]
        ');

        if ($this->http->FindSingleNode('//div[contains(text(), "Enter a password to finish creating your account")]')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($error) {
            $this->logger->error("error: {$error}");
            $input->clear();

            if (
                $error == "Please check your code and try again."
                || $error == "Check the code we sent you and try again."
            ) {
                $this->holdSession();
                $this->AskQuestion($this->Question, $error, 'Question');
            }

            return false;
        }

        return true;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        $name = $this->waitForElement(WebDriverBy::xpath($nameXpath = '//a[@href="/my-account/landing"]//p[contains(text(), "Account")]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if (!$name && $this->waitForElement(WebDriverBy::name('email'), 0)) {
            throw new CheckRetryNeededException(3, 1);
        }

        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode($nameXpath, null, true, '/(.*)\'s Account/')));

        try {
            $this->http->GetURL('https://www.nordstrom.com/my-account/rewards');
        } catch (Facebook\WebDriver\Exception\TimeoutException | TimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        $this->waitForElement(WebDriverBy::xpath(
            '//*[starts-with(text(),"Your Nordy Club ID:")]
        '), 15);
        $this->saveResponse();

        // Balance - Your Points
        $balance =
            $this->http->FindSingleNode('//*[@id="d3-arc"]/g/text[starts-with(normalize-space(),"Get a ") and contains(normalize-space()," Note")]/preceding-sibling::text[1]')
            ?? $this->http->FindSingleNode('//div[contains(text(), "Your points:")]', null, true, "/Your\s*points:\s*([^<]+)/");

        if (isset($balance)) {
            $this->SetBalance($balance);
        } elseif ($this->http->FindSingleNode('//span[normalize-space() = "JOIN NOW"]')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        } else {
            $pointsAwayFrom = PriceHelper::parse($this->http->FindSingleNode('//div/b[not(contains(text(), "$"))]/text()'));
            $pointsNeedTo = PriceHelper::parse($this->http->FindSingleNode('//div[contains(text(), "points away from a")]/../../../following-sibling::div/div/div[contains(text(), ",")]/text()'));

            if (!isset($pointsAwayFrom, $pointsNeedTo)) {
                $this->sendNotification('refs #24888 nordstrom - need to check balance // IZ');

                return;
            }

            $balance = $pointsNeedTo - $pointsAwayFrom;

            if (isset($balance) && $balance >= 0) {
                $this->SetBalance($balance);
            }
        }

        // Your Nordy Club ID:
        $this->SetProperty('Number', $this->http->FindSingleNode('//text()[starts-with(normalize-space(),"Your Nordy Club ID:")]', null, true, "/Club ID:\s?(.+)$/"));
        // ... points away from a $20 Note - points away from a rewards
        $this->SetProperty('PointsAwayFromRewards', $this->http->FindSingleNode('//text()[contains(normalize-space(),"points away from a") and contains(normalize-space(),"Note")]', null, true, "/^([\d,.]+)\spoints/"));
        // You're an Influencer
        $this->SetProperty('Status', $this->http->FindSingleNode('//div[contains(text(),"You\'re") and contains(.," an ")]', null, false, '/ an (.+)/'));
        // Influencer   Ambassador
        $this->SetProperty('NextLevel', $this->http->FindSingleNode('//div[@id="level-gauge-container"]/div/div[2]', null, false, '/^[A-z]+$/'));
        // Spend $... by Dec .. to become an
        $this->SetProperty('SpentToNextLevel', $this->http->FindSingleNode('//div[@id="level-gauge-container"]/following-sibling::div[1]', null, true, "/^Spend ([$\d,.]+) by .+? to become an /"));
        // Personal Double Points Days
        $this->SetProperty('PersonalDoublePointsDays', $this->http->FindSingleNode('//a[contains(text(),"Personal Double Points Days")]/following-sibling::div[1] and not(text()="-")'));
        // Alterations Credit
        $this->SetProperty('AlterationsCredit', $this->http->FindSingleNode('//a[@id="alterations-credit"]/following-sibling::div[1]'));
        // 453 points until $10 Note
        $this->SetProperty('PointsToTheNextNote', $this->http->FindSingleNode('//p[contains(text(),"in Notes") and contains(.,"points until")]', null, false, '/([\d+,]+) points until/'));

        $notes = $this->waitForElement(WebDriverBy::xpath('//a[span[contains(text(),"Your Notes")]]'), self::WAIT_TIMEOUT);

        if (!$notes) {
            return;
        }
        $notes->click();
        sleep(1);
        $this->saveResponse();

        $code = $this->http->FindSingleNode($noteXpath = '//div[@id="dialog-description"]/descendant::text()[contains(translate(normalize-space(), "0123456789", "dddddddddd"), "Note - dddd")]', null, true, "/^[$][\d,.]+\s?Note - (\d{4})/");
        $subBalance = $this->http->FindSingleNode($noteXpath, null, true, "/^[$]([\d,.]+)\s?Note - \d{4}/");
        $expirationDate = $this->http->FindSingleNode('//div[@id="dialog-description"]/descendant::text()[starts-with(normalize-space(),"Exp.") and contains(normalize-space(),"/")]', null, true, "/Exp\.\s?(\d+\/\d+\/\d+)/");

        if (isset($code, $subBalance, $expirationDate)) {
            $this->logger->info("Note #" . $code, ['Header' => 3]);
            $this->AddSubAccount([
                "Code"           => "nordstromNote" . $code,
                "DisplayName"    => "\$$subBalance Note - " . $code,
                "Balance"        => $subBalance,
                "ExpirationDate" => strtotime($expirationDate),
            ]);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        try {
            $logout = $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_XPATH), self::WAIT_TIMEOUT);
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            sleep(1);
            $logout = $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_XPATH), self::WAIT_TIMEOUT);
        }

        $this->logger->debug($this->http->currentUrl());

        $this->saveResponse();

        if ($logout) {
            return true;
        }

        $this->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));

        if ($this->http->FindSingleNode(self::LOGOUT_XPATH)) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        $this->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));

        $message = $this->http->FindSingleNode('
            //div[@id="banner-error"]
            | //text()[normalize-space() = "Your account has been locked"]
            | //span[@data-cy="wrong-user-credentials-error"]
            | //span[@data-cy="hit-a-snag-error"]
        ');

        $this->logger->error("Message: {$message}");

        if ($message) {
            if (
                strstr($message, 'Your email or password wasn’t recognized')
                || strstr($message, 'Your email or password wasn\'t recognized')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Your account has been locked')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, 'We hit a snag, but we’re working on it. Please try signing in again later.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, "We hit a snag, but we’re working on it")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Something isn’t working right now, but we’re fixing it. For immediate help,")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "We\'ve noticed some unusual activity")]')) {
            throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            /*
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            */
        }

        if ($message = $this->http->FindSingleNode('//h3[contains(text(), "Something’s wrong with our site. We’re on it!")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg('/Resource Allocated/')) {
            throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        //s**********ss@gmail.com
        $email = $this->http->FindSingleNode('
            //text()[normalize-space() = "We’ll send a code to:"]/following::p[contains(normalize-space(), "@")]
            | //div[normalize-space() = "We’ll send a code to:"]/following::div//strong[contains(normalize-space(), "@")]
            | //*[@name="delivery-method"]/descendant::text()[contains(normalize-space(), "@")]
        ', null, false);
        //(0**) ***-0000
        $phone = $this->http->FindSingleNode(
            '//text()[normalize-space() = "We’ll send a code to:"]/following::text()[contains(translate(normalize-space(), "0123456789", "dddddddddd"), "**-dddd")]
            |//*[@name="delivery-method"]/descendant::text()[contains(translate(normalize-space(), "0123456789", "dddddddddd"), "**-dddd")]');

        if (!$email && !$phone) {
            $this->logger->error("question nt found");

            return false;
        }

        if ($this->http->FindSingleNode('//text()[normalize-space() = "How should we send your code?"]')) {
            $this->driver->executeScript('document.querySelector(\'input[type = "radio"][value="email"], input[type = "radio"][value="EMAIL"]\').checked = true;');
        }
        $buttonSend = $this->waitForElement(WebDriverBy::xpath("//button[descendant::text()[normalize-space() = 'Send Code']]"), 0);

        if (!$buttonSend) {
            return false;
        }

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $this->saveResponse();
        $buttonSend->click();

        $input = $this->waitForElement(WebDriverBy::xpath(self::INPUT_CODE_XPATH), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if (!$input) {
            if ($this->http->FindSingleNode('//strong[contains(text(), "Attempt limit exceeded")]')) {
                throw new CheckException("Attempt limit exceeded. Try again after 24 hours or call customer service.", ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        if ($email) {
            $question = "Please enter Code which was sent to the following email address: $email. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
        } else {
            $question = "Please enter Code which was sent to the following phone number: $phone. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
        }

        $this->holdSession();
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }
}
