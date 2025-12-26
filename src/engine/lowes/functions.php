<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerLowes extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const XPATH_SUCCESS = '//h4[contains(text(), "Welcome")] | //span[@id="account-name"]';
    private const REWARDS_PAGE_URL = "https://www.lowes.com/loyalty/mylowesrewards";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->usePacFile(false);
        $this->setProxyGoProxies();

        $this->useFirefoxPlaywright();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL(self::REWARDS_PAGE_URL);
        $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Redirecting")] | //input[@id="email"] | //input[@id="user-password"] | //span[contains(text(), "This site can") and contains(text(), "t be reached")] | //h1[contains(text(), "Access Denied")]'), 15);

        $this->saveResponse();
        $this->logger->debug('[CURRENT URL]: ' . $this->http->currentUrl());

        if ($this->http->FindSingleNode('//div[contains(text(), "Redirecting")]')) {
            $this->waitFor(function () {
                return is_null($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Redirecting")]'), 0));
            }, 55);
            $this->saveResponse();
            $this->logger->debug('[CURRENT URL]: ' . $this->http->currentUrl());
        }

        $email = $this->waitForElement(WebDriverBy::xpath('//input[@id="email"]'), 5);

        if (!$email) {
            $this->logger->error('Failed to find form fields');
            $this->saveResponse();

            if ($message = $this->http->FindSingleNode('//span[contains(text(), "Something went wrong please try again.")]')) {
                $this->DebugInfo = $message;
            }

            if ($this->proxyIsInvalid()) {
                throw new CheckRetryNeededException(3, 0);
            }

            if ($this->loginSuccessful()) {
                return true;
            }

            return $this->checkErrors();
        }

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->sendKeys($email, $this->AccountFields['Login'], 5);
        $this->driver->executeScript('let rememberMe = document.querySelector(\'#rememberMe\'); if (rememberMe && rememberMe.checked == false) rememberMe.click();');

        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Continue")]'), 0);
        $this->saveResponse();

        if (!$button) {
            return false;
        }

        $button->click();

        $this->waitForElement(WebDriverBy::xpath('//input[@id = "password"] | //span[contains(text(), "Something went wrong please try again.")] | //span[contains(text(), "Your account has been locked")]'), 10);
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Continue")]'), 0);
        $this->saveResponse();

        if ($password && $button) {
            $mover->sendKeys($password, $this->AccountFields['Pass'], 5);
            $button->click();
        }

        $this->processSecOverlay();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        // The site is currently offline and will be available within the next hour.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The site is currently offline and will be available within the next hour.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath(self::XPATH_SUCCESS . ' | //span[contains(text(), "Your account has multi-factor authentication enabled")] | //h2[contains(text(), "Complete Your Profile")] | //div[contains(text(), "You are being redirected, please wait")] | //span[contains(text(), "At least 1 letter and 1 number with no spaces")] | //p[contains(text(), "It looks like") and contains(., "is linked to both a Personal and Business account")] | //div[@class="error-message"] | //span[contains(text(), "Something went wrong please try again.")] | //span[contains(text(), "Your account has been locked")] | //button[contains(., "Request verification code")]'), 20); // the first redirect is very slow
        $this->saveResponse();

        try {
            $this->logger->debug('[CURRENT URL]: ' . $this->http->currentUrl());
        } catch (UnknownServerException | NoSuchWindowException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 0);
        }

        if ($this->http->FindSingleNode('//div[contains(text(), "You are being redirected, please wait")]')) {
            $this->waitFor(function () {
                return is_null($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "You are being redirected, please wait")]'), 0));
            }, 40);
            $this->saveResponse();

            try {
                $this->logger->debug('[CURRENT URL]: ' . $this->http->currentUrl());
            } catch (UnknownServerException | NoSuchWindowException | NoSuchDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(3);
            }
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Something went wrong please try again')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Your credentials do not match our records. Please try again or reset your password')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//span[contains(text(), \"The email address or password you entered doesn't match our records\")] | //div[contains(@class, \"desktop\")]//h2[contains(text(), \"We didn't recognize that email address.\")]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (
            $this->http->FindSingleNode('//h1[contains(text(), "Your Sign In Email is Linked to Two Accounts")]')
            || $this->http->FindSingleNode('//h2[contains(text(), "Complete Your Profile")]')
            || $this->http->FindSingleNode('//*[contains(text(), "need to update your info")]')
        ) {
            $this->throwProfileUpdateMessageException();
        }

        if ($message = $this->http->FindSingleNode('//span[contains(text(), "At least 1 letter and 1 number with no spaces")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->parseQuestion()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode('//div[@class="error-message"]')) {
            $this->logger->error("[Error]: [$message}");

            if (
                strstr($message, 'Your credentials do not match our records.')
                || strstr($message, 'Looks like you’re having trouble. You may want to try resetting your password')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Your account has been locked")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // It looks like ... is linked to both a Personal and Business account. Before you can proceed, you’ll need to update your info. Don’t worry, this won’t take too long
        if ($this->http->FindSingleNode('//p[contains(text(), "It looks like") and contains(., "is linked to both a Personal and Business account")]')) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function parseQuestionForm()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->FindSingleNode('//span[contains(text(), "Your account has multi-factor authentication enabled")]')) {
            return false;
        }

        $emailLabel = $this->waitForElement(WebDriverBy::xpath('//label[@for="email"]'), 0);
        $submit = $this->waitForElement(WebDriverBy::xpath('//button[@type="submit"]'), 0);

        if (!$emailLabel || !$submit) {
            $this->saveResponse();

            return false;
        }

        $emailLabel->click();
        $this->saveResponse();

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $submit->click();

        // We've sent a code to ******... . It will expire in 10 minutes
        // We've sent a one-time passcode to alexgmull@gmail.com. It will expire in 10 minutes.
        $q = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We\'ve sent a code to")] | //div[contains(text(), "We\'ve sent a one-time passcode to")] | //p[span[contains(text(), "To help protect your account.")] and contains(., "We\'ve sent a one-time passcode")]'), 5);
        $this->saveResponse();

        if (!$q) {
            return false;
        }

        $this->holdSession();
        // Please verify your device by clicking on Verify Device link sent to your email
        $question = $q->getText();
        $this->AskQuestion($question, null, 'Question');

        return true;
    }

    public function parseQuestionPopup()
    {
        $this->logger->notice(__METHOD__);

        $sendCodeTo = $this->waitForElement(WebDriverBy::xpath("//p[text()=\"{$this->AccountFields['Login']}\"]"), 0);
        $submit = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Request verification code")]'), 0);
        $this->saveResponse();
        $delay = 0;

        // TODO: WTF?
        if ($sendCodeTo || $submit) {
            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            $submit->click();
            $delay = 5;
        }

        // We've sent a code to ******... . It will expire in 10 minutes
        // We've sent a one-time passcode to alexgmull@gmail.com. It will expire in 10 minutes.
        $q = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We\'ve sent a ")] | //div[contains(text(), "We\'ve sent a one-time passcode to")] | //p[span[contains(text(), "To help protect your account.")] and contains(., "We\'ve sent a one-time passcode")]'), $delay);
        $this->saveResponse();

        if (!$q) {
            return false;
        }

        $this->holdSession();
        // Please verify your device by clicking on Verify Device link sent to your email
        $question = $q->getText();
        $this->AskQuestion($question, null, 'Question');

        return true;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        $this->driver->executeScript("document.getElementById('footerApp').style.display='none';");
        $this->saveResponse();

        if ($this->parseQuestionForm()) {
            return true;
        }

        if ($this->parseQuestionPopup()) {
            return true;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $verificationCodeInput = $this->waitForElement(WebDriverBy::xpath('(//input[@id = "verificationCode"] | //label[contains(text(), "One-Time Passcode")]/following-sibling::div/input)'), 5);
        $this->saveResponse();

        if (!$verificationCodeInput) {
            $this->logger->error('failed to find otp form fields');

            return false;
        }

        $verificationCodeInput->clear();
        $verificationCodeInput->sendKeys($answer);
        sleep(5);

        $submit = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class,"ModalDialog")]//button[contains(text(), "Verify & Continue")] | //span[@class="submit-btn-otp"]//button[@type="submit"] | //button[contains(., "Verify")]'), 0);
        $this->saveResponse();

        if (!$submit) {
            $this->logger->error('failed to find btn');

            return false;
        }
        $this->logger->error('debug');

        $submit->click();

        //sleep(10);
        //$this->saveResponse();

        if ($error = $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "ErrorMessage")]/text() | //span[contains(@class, "error")]/span'), 10)) {
            if (
                strstr($error->getText(), 'This code is invalid. Please try again. You have a maximum of')
                || strstr($error->getText(), 'This code is invalid. Please check the code and try again.')
            ) {
                $this->holdSession();
                $this->AskQuestion($this->Question, $error->getText(), 'Question');

                return false;
            }

            $this->DebugInfo = $error->getText();

            return false;
        }// if ($error = $this->http->FindSingleNode('//span[contains(@class, "ErrorMessage")]/text()'))
        $this->saveResponse();
        if ($this->http->FindSingleNode('//div[contains(text(), "You are being redirected, please wait")]')) {
            $this->waitFor(function () {
                return is_null($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "You are being redirected, please wait")]'), 0));
            }, 40);
            $this->saveResponse();

            try {
                $this->logger->debug('[CURRENT URL]: ' . $this->http->currentUrl());
            } catch (UnknownServerException | NoSuchWindowException | NoSuchDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            }
        }

        // TODO: debug
        $submit = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class,"ModalDialog")]//button[contains(text(), "Verify & Continue")] | //span[@class="submit-btn-otp"]//button[@type="submit"]'), 0);
        //$this->saveResponse();

        if ($submit) {
            $submit->click();

            sleep(10);
            //$this->saveResponse();
        }

        return true;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->logger->debug('[CURRENT URL]: ' . $this->http->currentUrl());
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "points-desc")]'), 10);
        $this->saveResponse();

        $membershipType = $this->http->FindPreg('/"membershipType"\s*:\s*"(.+?)"/');
        $this->logger->debug('MEMBERSHIP TYPE: ' . $membershipType);

        if ($membershipType == 'DIY') {
            $this->logger->debug('PARSING DYI ACCOUNT');
            // Name
            $this->SetProperty('Name', $this->http->FindSingleNode('//span[@id="account-name"]/text()'));
            // Status
            $this->SetProperty('Status', $this->http->FindPreg('/"currentTier".*?"name"\s*:\s*"(.*?)"/'));
            // Spend to next level
            $spendToNextLevel = $this->http->FindPreg('/"membershipType".*?"spendToNextTier"\s*:\s*(\d+)/');

            if (isset($spendToNextLevel)) {
                $this->SetProperty('SpendToNextLevel', '$' . $spendToNextLevel);
            }

            $rewardBalance = $this->http->FindPreg('/"rewardBalance"\s*:\s*(\d+)/');
            $rewardIssuanceThreshold = $this->http->FindPreg('/"rewardIssuanceThreshold"\s*:\s*(\d+)/');

            if (isset($rewardBalance, $rewardIssuanceThreshold)) {
                // Balance - points
                $this->SetBalance($rewardBalance);
                // Points to unlock next reward
                $pointsToNextReward = intval($rewardIssuanceThreshold) - intval($rewardBalance);
                $this->SetProperty('PointsToNextReward', $pointsToNextReward);
            }

            $rewardIssuanceValue = $this->http->FindPreg('/"rewardIssuanceValue"\s*:\s*(\d+)/');

            if (isset($rewardIssuanceValue)) {
                $this->AddSubAccount([
                    "Code"        => "MyLowesMoney",
                    "DisplayName" => "MyLowe's Money",
                    "Balance"     => $rewardIssuanceValue,
                    "Currency"    => "USD",
                ]);
            }

            $this->http->GetURL('https://www.lowes.com/mylowes/profile/wallet/promotions');
            $this->waitForElement(WebDriverBy::xpath('//div[@id="columns"]'), 10);
            $this->saveResponse();
        } elseif ($membershipType == 'PRO') {
            $this->logger->debug('PARSING PRO ACCOUNT');
            // Name
            $firstName = $this->http->FindPreg('/"firstName"\s*:\s*"(.+?)"/');
            $lastName = $this->http->FindPreg('/"lastName"\s*:\s*"(.+?)"/');
            $this->SetProperty('Name', beautifulName("{$firstName} {$lastName}"));
            // Pro Account Number
            $proAccountNumber = $this->http->FindPreg('/"proAccountId"\s*:\s*"(.+?)"/');
            $this->SetProperty('ProAccountNumber', $proAccountNumber);
            // Status
            $this->SetProperty('Status', $this->http->FindPreg('/"currentTier".*?"name"\s*:\s*"(.*?)"/'));
            // Balance - points
            $availablePoints = $this->http->FindPreg('/"availablePoints"\s*:\s*"?(.+?)"?,/');
            $this->SetBalance($availablePoints);
            // Spend to next level
            $spendToNextLevel = $this->http->FindPreg('/"spendToNextTier"\s*:\s*(\d+)/');

            if (isset($spendToNextLevel)) {
                $this->SetProperty('SpendToNextLevel', '$' . $spendToNextLevel);
            }

            $this->http->GetURL('https://www.lowes.com/account/profile/wallet/promotions');
            $this->waitForElement(WebDriverBy::xpath('//div[@id="columns"]'), 10);
            $this->saveResponse();

            /*
            $offersIsNotPresent = $this->http->FindSingleNode('//p[contains(text(), "You currently do not have any offers available to you")]');

            if (!$offersIsNotPresent) {
                $this->sendNotification('refs #23743 - need to check offers in PRO account // IZ');
            }
            */
        }

        $offersDataRaw = $this->http->FindPreg('/"offers".*?"data"\s*:\s*(\[.*?\]),/');
        $offersData = $this->http->JsonLog($offersDataRaw);

        if (isset($offersData) && is_iterable($offersData) && count($offersData) > 0) {
            foreach ($offersData as $offer) {
                $barCode = $offer->barCodeNumber ?? null;
                $displayName = $offer->promotionHeadline ?? null;
                $expiration = $offer->expiration ?? null;
                $status = $offer->status ?? null;

                if (!isset($barCode, $displayName, $expiration, $status) || $status == "EXPIRED") {
                    continue;
                }

                $this->AddSubAccount([
                    "Code"           => $barCode,
                    "DisplayName"    => $displayName,
                    "Balance"        => null,
                    "ExpirationDate" => strtotime($expiration),
                ]);
            }
        }
    }

    private function processSecOverlay()
    {
        $secOverlay = $this->waitForElement(WebDriverBy::xpath('//*[@id="sec-text-container"]'), 3);
        $this->saveResponse();

        if (!$secOverlay) {
            $this->logger->debug('sec-overlay not found');

            return false;
        }

        // sec-text-container workaround
        if ($secOverlay) {
            $this->logger->notice("sec-text-container workaround");

            $this->waitFor(function () {
                return !$this->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0);
            }, 120);
            $this->saveResponse();

            if ($button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In")]'), 0)) {
                $button->click();
                $this->logger->debug("wait results");
                sleep(5);
            }
        }

        $this->saveResponse();

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $logout = $this->waitForElement(WebDriverBy::xpath('//h4[contains(text(), "Welcome")] | //span[@id="account-name"]'), 5);
        $this->saveResponse();

        if ($logout || $this->http->FindSingleNode('//h4[contains(text(), "Welcome")] | //span[@id="account-name"]')) {
            return true;
        }

        return false;
    }

    private function proxyIsInvalid()
    {
        $this->waitFor(function () {
            return is_null($this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Health check")]'), 0));
        }, 5);

        $this->saveResponse();

        if (
            $this->http->FindSingleNode('
                //h1[contains(text(), "Access Denied")]
                | //p[contains(text(), "Health check")]
                | //span[contains(text(), "This site can") and contains(text(), "t be reached")]')
        ) {
            $this->markProxyAsInvalid();

            return true;
        }

        return false;
    }
}
