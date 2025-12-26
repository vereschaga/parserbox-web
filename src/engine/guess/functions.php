<?php

class TAccountCheckerGuess extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.guess.com/us/en/guessList/';

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && str_starts_with($properties['SubAccountCode'], 'guessRewardBalance')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
        $this->http->saveScreenshots = true;
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
        $this->http->removeCookies();

        $this->http->GetURL('https://www.guess.com/us/en/login/?rurl=1');
        $this->saveResponse();

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="login-form-email"]'), 5);
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@id="form-password"]'), 0);
        $rememberMe = $this->waitForElement(WebDriverBy::xpath('//input[@id="rememberMe"]'), 0);

        if (!$login || !$password) {
            return $this->checkErrors();
        }
        $login->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);

        if ($rememberMe) {
            $rememberMe->click();
        }

        $this->saveResponse();

        $message = $this->http->FindSingleNode('//div[@id="form-email-login-account-error"]/text() | //div[@id="form-password-error"]/text() | //div[contains(@class,"js-form-alert")]//p/text()');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr(
                $message,
                'Please check your email address'
            )) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr(
                $message,
                'Password must be at least 8 characters with an uppercase letter, lowercase letter, number, and special character'
            )) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $submit = $this->waitForElement(WebDriverBy::xpath('//form[@name="login-form"]//button[@type="submit"]'), 0);
        $submit->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class,"js-form-alert")]//p/text() | //a[normalize-space()="Sign out"][@aria-label="Sign out"]'), 10);

        $this->saveResponse();

        $message = $this->http->FindSingleNode('//div[@id="form-password-error"]/text() | //div[contains(@class,"js-form-alert")]//p/text()');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr(
                $message,
                'Invalid login or password. Remember that password is case-sensitive. Please try again.'
            )) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - ... / XX points
        $this->SetBalance($this->http->FindSingleNode('//span[contains(@class,"percentage")][not(contains(@class,"d-none"))]/span'));
        // Number
        $this->SetProperty(
            'CardNumber',
            $this->http->FindSingleNode('//p[contains(@class,"account-banner__cardnumber")]/@data-loyaltynum')
        );
        // Status
        $this->SetProperty(
            'Status',
            $this->http->FindSingleNode('//h2[@class="card--info__title"]', null, true, "/Status: ([\w ]+)/ims")
        );
        // Points to next reward
        $this->SetProperty(
            'PointsTillNextReward',
            $this->http->FindSingleNode(
                '//div[@class="card--info__description"]/p[2]',
                null,
                true,
                "/^You’re (\d+) points? away from your next/ims"
            )
        );
        // Reward balance
        $RewardBalance = $this->http->FindSingleNode('//p[@class="card-balance__value"]/span');

        if ($RewardBalance) {
            $expirationDate = $this->http->FindSingleNode('//p[contains(text(),"Reward balance")]/span');

            $this->AddSubAccount([
                "Code"           => "guessRewardBalance",
                "DisplayName"    => "Reward balance",
                "Balance"        => $RewardBalance,
                "ExpirationDate" => strtotime($expirationDate),
            ]);

            $RewardBalance = str_replace('$', '', $RewardBalance);

            if ((float) $RewardBalance > 0.0) {
                $this->logger->debug(">>> RewardBalance: " . $RewardBalance);
                $this->sendNotification("refs #7299 Reward Balance > 0: $RewardBalance // MI");
            }
        } else {
            $this->logger->debug("RewardBalance undefined");
            $this->sendNotification("refs #7299 RewardBalance undefined // MI");
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.guess.com/us/en/account/?registration=false', [], 20);
        $this->http->RetryCount = 2;
        // Name
        $this->SetProperty(
            'Name',
            beautifulName($this->http->FindSingleNode('//dt[normalize-space()="Name"]/following-sibling::dd'))
        );
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode('//a[normalize-space()="Sign out"][@aria-label="Sign out"]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Our website is currently unavailable while we make upgrades to improve your experience")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
