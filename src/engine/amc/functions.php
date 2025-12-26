<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerAmc extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.amctheatres.com/my-amc';

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'amcRewards')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->http->saveScreenshots = true;
        $this->useFirefoxPlaywright();
        //$this->setProxyBrightData();
        $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_EU));
//        $this->seleniumOptions->addHideSeleniumExtension = false;
       //$this->seleniumOptions->userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    }

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

    public function LoadLoginForm()
    {
        // INVALID EMAIL ADDRESS
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("INVALID EMAIL ADDRESS", ACCOUNT_INVALID_PASSWORD);
        }
        // INVALID PASSWORD
        if (strlen($this->AccountFields['Pass']) < 8) {
            throw new CheckException("INVALID PASSWORD", ACCOUNT_INVALID_PASSWORD);
        }

        try {
            $this->http->removeCookies();

            $this->driver->manage()->window()->maximize();
            $this->http->GetURL('https://www.amctheatres.com/');

            $this->http->GetURL(self::REWARDS_PAGE_URL);

            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Email" or @name="email"]'), 15);
            $this->saveResponse();

            if (!$loginInput) {
                if ($this->clickCloudFlareCheckboxByMouse(
                    $this,
                )) {
                    $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Email" or @name="email"]'), 5);
                    $this->saveResponse();
                }

                if (!$loginInput) {
                    $loginFormBtn = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Sign In to AMC Stubs Account"]'),
                        0);

                    if (!$loginFormBtn) {
                        return $this->checkErrors();
                    }

                    $loginFormBtn->click();
                }
            }

            $this->saveResponse();

            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Email" or @name="email"]'), 5);
            $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Password" or @name="password"]'), 0);

            if (!$loginInput || !$passwordInput) {
                return $this->checkErrors();
            }


            /*$mover = new MouseMover($this->driver);
            $mover->logger = $this->logger;
            $mover->duration = rand(100, 300);
            $mover->steps = rand(50, 60);

            $this->logger->debug("set login");
            $this->saveResponse();
            try {
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 30);
            $mover->click();
            } catch (Facebook\WebDriver\Exception\ElementNotInteractableException $e) {
                $this->logger->error('ElementNotInteractableException: ' . $e->getMessage(), ['HtmlEncode' => true]);
                sleep(10);
                $mover->sendKeys($loginInput, $this->AccountFields['Login'], 30);
                $mover->click();
            }
            $this->logger->debug("set pass");
            $passwordInput->click();
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 30);
            $mover->click();
            $mover->moveToElement($loginInput);
            $mover->click();*/


            try {
                $loginInput->sendKeys($this->AccountFields['Login']);
            } catch (Facebook\WebDriver\Exception\ElementNotInteractableException $e) {
                $this->logger->error('ElementNotInteractableException: ' . $e->getMessage(), ['HtmlEncode' => true]);
                sleep(10);
                $this->saveResponse();
                $loginInput->sendKeys($this->AccountFields['Login']);
            }
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->saveResponse();


            $loginBtn = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "Login--Submission-buttons")]//button[not(@disabled) and contains(text(), "Sign In")] | //button[not(@disabled) and contains(text(), "Sign in")]'), 5);

            if (!$loginBtn) {
                return $this->checkErrors();
            }

            $loginBtn->click();

            sleep(3); // handling incorrect click

            /*
            $loginBtn = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "Login--Submission-buttons")]//button[not(@disabled) and contains(text(), "Sign In")] | //button[not(@disabled) and contains(text(), "Sign in")]'), 5);

            $this->saveResponse();

            if ($loginBtn) {
                $this->logger->debug('retry click');
                $loginBtn->click();
            }
            */

            return true;
        } catch (UnknownServerException | NoSuchWindowException | NoSuchDriverException $e) {
            $this->logger->error('Exception: ' . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3);
        }
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // provider error
        if (
            $this->http->FindSingleNode("//h2[contains(text(), '500 - Internal server error.')]")
            || $this->http->FindPreg("/The service is unavailable\./")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message =
            $this->http->FindSingleNode("//span[contains(text(), 'Thank you for your patience as we perform some planned maintenance on our website and app.')]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->FindSingleNode("//body[contains(text(), 'The requested URL was rejected. Please consult with your administrator.')]")) {
            throw new CheckRetryNeededException(3);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class,"member-info")]/img | //div[contains(text(), "Current Plan:")]/following-sibling::div[1] | //span[contains(@class, "Form--error-message")]/text() | //div[@role="alert" and contains(@class, "ErrorMessageAlert")]/p/text() | //p[@role = "alert"]'), 10);
        $this->saveResponse();

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[contains(@class, "Form--error-message")]/text() | //div[@role="alert" and contains(@class, "ErrorMessageAlert")]/p/text() | //p[@role = "alert"]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "The information you entered doesn't match what we have on file. Please check the information you entered or create a new AMC Stubs Account.")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            if ($message === 'Invalid form submission.') {
                throw new CheckRetryNeededException(3, 7, self::PROVIDER_ERROR_MSG);
            }

            return false;
        }

        if ($message = $this->http->FindSingleNode('//h4[contains(text(), "Account Locked")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        try {
            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");

            if ($currentUrl != self::REWARDS_PAGE_URL) {
                $this->http->GetURL(self::REWARDS_PAGE_URL);
            }

            $this->waitForElement(WebDriverBy::xpath('//div[contains(@class,"member-info")]/img | //div[contains(text(), "Current Plan:")]/following-sibling::div[1]'), 10);
            $this->saveResponse();

            $statusText = $this->http->FindSingleNode('//div[contains(@class,"member-info")]/img/@alt | //div[contains(text(), "Current Plan:")]/following-sibling::div[1]');
            $status = $this->http->FindPreg('/Stubs\s([A-z]+)/', false, $statusText);

            if (!$status) {
                $status = $this->http->FindPreg('/A M C (.*) Member/', false, $statusText);
            }

            // Status
            $this->SetProperty('Status', $status);
            // Member Since
            $memberSinceRaw = $this->http->FindSingleNode('//div[contains(@class,"member-info__dates")]//h4[@class="member-info__info-callout"]/text() | //div[contains(text(), "Member Since")]/following-sibling::div[1]');

            if (strtolower($status) == 'insider' && isset($memberSinceRaw)) {
                // If user in status "insider" then date is MemberSince else date is StatusExpiration
                $memberSince = DateTime::createFromFormat('F d, Y', $memberSinceRaw);
                // Member since
                $this->SetProperty('MemberSince', $memberSince->getTimestamp());
            }

            $this->http->GetURL("https://www.amctheatres.com/amcstubs/account");
            $this->saveResponse();

            $firstName = $this->http->FindSingleNode('//input[@name="firstName"]/@value');
            $lastName = $this->http->FindSingleNode('//input[@name="lastName"]/@value');
            // Name
            $this->SetProperty('Name', beautifulName("$firstName $lastName"));

            $this->http->GetURL("https://www.amctheatres.com/amcstubs/wallet");
            $this->waitForElement(WebDriverBy::xpath('//span[contains(., "Number")]/following-sibling::span[1]'), 10);
            $this->saveResponse();

            if ($status && strtolower($status) != 'insider') {
                // Expiration date
                $this->SetExpirationDateNever();
                $this->SetProperty('AccountExpirationWarning', 'do not expire with elite status');

                $statusExpirationRaw = $this->http->FindSingleNode('//div[@class="StubsCard-Info"]/span[contains(@class, "italic")]/text()', null, false, '/\w+\s\d+,\s\d+/');

                if (isset($statusExpirationRaw)) {
                    // If user in status "insider" then date is MemberSince else date is StatusExpiration
                    $statusExpiration = DateTime::createFromFormat('F d, Y', $statusExpirationRaw);
                    // Status expiration
                    $this->SetProperty('StatusExpiration', $statusExpiration->getTimestamp());
                }
            }

            // Number
            $this->SetProperty('Number', $this->http->FindSingleNode('//span[contains(., "Number")]/following-sibling::span[1]'));
            // Balance - points
            $this->SetBalance($this->http->FindSingleNode('//li[contains(., "Points Available")]/strong'));
            // Points till next reward
            $this->SetProperty('UntilNextReward', $this->http->FindSingleNode('//li[contains(., "to Next")]/strong'));

//            $this->http->GetURL('https://www.amctheatres.com/amcstubs/rewards');
//            $this->waitForElement(WebDriverBy::xpath('//div[@class="MyAMCDashboard-extra-info"]//span[contains(@aria-label, "USD")]'), 10);
//            $this->saveResponse();
            // Total Rewards Available
            if ($rewardBalance = $this->http->FindSingleNode('//li[contains(., "Available Rewards")]/strong')) {
                $amcRewards = [
                    'Code'        => 'amcRewards',
                    'DisplayName' => "Rewards",
                    'Balance'     => $rewardBalance,
                ];
                $this->AddSubAccount($amcRewards);
            }
            // Points Pending
            $this->SetProperty('PendingPoints', $this->http->FindSingleNode('//li[contains(., "Points Pending")]/strong'));
        } catch (UnknownServerException | NoSuchWindowException | NoSuchDriverException $e) {
            $this->logger->debug("error: {$e->getMessage()}");

            throw new CheckRetryNeededException(3);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        try {
            $logoutItemXpath = '//h1[contains(text(), "My AMC")]';
            $success = $this->waitForElement(WebDriverBy::xpath($logoutItemXpath), 10);
            $this->saveResponse();

            return $success;
        } catch (UnknownServerException | NoSuchWindowException | NoSuchDriverException $e) {
            $this->logger->debug("error: {$e->getMessage()}");

            throw new CheckRetryNeededException(3);
        }
    }
}
