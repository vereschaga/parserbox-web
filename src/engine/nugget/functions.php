<?php

class TAccountCheckerNugget extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'nuggetAtlanticCity')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();

        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(true);

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            $this->http->GetURL('https://www.goldennugget.com/24k-club/login-membership-card/');

            $this->waitForElement(WebDriverBy::xpath('//input[@id="LoginWithCardFormViewModel_AccountNumber" or @id = "LoginForm_Username"] | //input[@value = \'Verify you are human\'] | //div[@id = \'turnstile-wrapper\']//iframe'), 5);

            if ($this->clickCloudFlareCheckboxByMouse($this)) {
                $this->saveResponse();
            }

            $login = $this->waitForElement(WebDriverBy::id('LoginWithCardFormViewModel_AccountNumber'), 5);
            $pass = $this->waitForElement(WebDriverBy::id('LoginWithCardFormViewModel_PIN'), 0);
        } else {
            $this->http->GetURL('https://www.goldennugget.com/24k-club/login/');

            $this->waitForElement(WebDriverBy::xpath('//input[@id="LoginWithCardFormViewModel_AccountNumber" or @id = "LoginForm_Username"] | //input[@value = \'Verify you are human\'] | //div[@id = \'turnstile-wrapper\']//iframe'), 5);

            if ($this->clickCloudFlareCheckboxByMouse($this)) {
                $this->saveResponse();
            }

            $login = $this->waitForElement(WebDriverBy::id('LoginForm_Username'), 5);
            $pass = $this->waitForElement(WebDriverBy::id('LoginForm_Password'), 0);
        }
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@type="submit"]'), 0);

        if (!isset($login, $pass, $btn)) {
            $this->saveResponse();

            return $this->checkErrors();
        }
        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $btn->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindPreg("/Server Error in \'\/\' Application\./ims")
            || $this->http->FindSingleNode('//h1[contains(text(), "Server Error in")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Welcome, ")] | //div[@id = "validationSummary"]/ul/li'), 17);
        $this->saveResponse();

        // Invalid username or password. Please try again.
        if ($this->http->FindSingleNode("//li[contains(text(),'Invalid username or password. Please try again.')]")) {
            throw new CheckException("Invalid username or password. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid credentials
        if ($this->http->FindPreg("/Did you enter in your account number correctly?/ims")) {
            throw new CheckException("Incorrect account number or pin", ACCOUNT_INVALID_PASSWORD);
        }
        // The UserName field is not a valid e-mail address.
        if ($message = $this->http->FindSingleNode("//li[
                contains(text(), 'The UserName field is not a valid e-mail address.')
                or contains(text(), 'Account Number or PIN is invalid. Please try again.')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, your pin has been disabled or locked.
        // Your PIN has been locked. Please contact Golden Nugget to have it reset.
        if ($message = $this->http->FindSingleNode("//li[
                contains(text(), 'Sorry, your pin has been disabled or locked.')
                or contains(text(), 'Your PIN has been locked.')
                or contains(text(), 'Your account is locked. Please use the link below to reset your password.')
        ]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // An unknown error has occurred while attempting to log you in. Please try again.
        if ($message = $this->http->FindSingleNode('//li[
                contains(text(), "An unknown error has occurred while attempting to log you in. Please try again.")
                or contains(text(), "We\'re sorry, an unknown error has occurred when trying to log you in.")
                or (contains(text(), "Value cannot be null.") and contains(., "Parameter name: source"))
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // success login
        $this->http->GetURL("https://www.goldennugget.com/24k-club/dashboard/GetDashboard/");
        $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'));

        if ($this->http->FindPreg("/\"IsLoggedIn\":true/")) {
            return true;
        }
        // hard code, AccountID: 4164625
        if (
            in_array($this->AccountFields['Login'], [
                '31200200224382',
                '31200003254197',
                '31300200019279',
                'GREEKWEEK200450@HOTMAIL.COM',
                '31200200364549',
                /*
                'lisa.tolliver@yahoo.com',
                '31200200364549',
                '31200200124595',
                'lei.cong@gmail.com',
                '31200200209604',
                'LBOTELHO@YAHOO.COM',
                */
            ])
            && (
                $this->http->Response['body'] == '{"PageTopHeading":null,"WelcomeText":null,"LocationDropdownText":null,"AccountInfo":null,"TierLevelInfo":null,"CompBalanceInfo":null,"PropertySpecificInfo":null,"IsLoggedIn":false,"LoginToken":"","LoginPageUrl":"/24k-club/login/"}'
                || $this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]') == '{"PageTopHeading":null,"WelcomeText":null,"LocationDropdownText":null,"AccountInfo":null,"TierLevelInfo":null,"CompBalanceInfo":null,"PropertySpecificInfo":null,"IsLoggedIn":false,"LoginToken":"","LoginPageUrl":"/24k-club/login/"}'
            )
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'), 0);
        // Name
        if (isset($response->AccountInfo->FullName)) {
            $this->SetProperty('Name', beautifulName($response->AccountInfo->FullName));
        }
        // Account #
        $this->SetProperty('AccountNumber', $this->http->FindPreg('/Account\s*\#\:\s*(\d+)/'));
        // Balance - Point Balance
        if (isset($response->TierLevelInfo->TierBalanceText)) {
            $this->SetBalance($this->http->FindPreg(self::BALANCE_REGEXP, false, $response->TierLevelInfo->TierBalanceText));
        } elseif ($this->http->FindPreg("/\"LevelName\":null,\"ExpirationText\":null,\"TierBalanceText\":null,\"CurrentTierPoints\":0,\"NextTierText\":null,/")) {
            $this->SetBalance(0);
        }
        // Resets on ...
        if (isset($response->TierLevelInfo->TierPointResetText)) {
            $exp = $this->http->FindPreg('/Resets\s*on\s*([^<]+)/', false, $response->TierLevelInfo->TierPointResetText);

            if ($exp && ($exp = strtotime($exp))) {
                $this->SetExpirationDate($exp);
            }
        }// if (isset($response->TierLevelInfo->TierPointResetText))
        // Comp Balance
        if (isset($response->CompBalanceInfo->CompBalanceText)) {
            $this->SetProperty('CompBalance', $response->CompBalanceInfo->CompBalanceText);
        }
        // Tier Credits Balance
        if (isset($response->TierLevelInfo->CurrentTierPoints)) {
            $this->SetProperty('CurrentTierPoints', $response->TierLevelInfo->CurrentTierPoints);
        }
        // Current Tier Level
        if (isset($response->TierLevelInfo->LevelName)) {
            $this->SetProperty('CurrentTierLevel', $response->TierLevelInfo->LevelName);
        }
        // NeededToNextTier
        if (isset($response->TierLevelInfo->PointsToNextTier)) {
            $this->SetProperty('NeededToNextTier', $response->TierLevelInfo->PointsToNextTier);
        }
        // Tier Level Expires
        if (isset($response->TierLevelInfo->ExpirationText)) {
            $this->SetProperty('StatusExpiration', $this->http->FindPreg("/Until\s*([^<]+)/ims", false, $response->TierLevelInfo->ExpirationText));
        }

        if (empty($response->PropertySpecificInfo)) {
            return;
        }

        foreach ($response->PropertySpecificInfo as $location) {
            $this->logger->debug($location->DropdownDisplayName);
            unset($balance);
            $shopPoints = null;
            $totalCashBack = null;
            $cashBack = null;

            foreach ($location->AccountBalances as $accountBalance) {
                if ($accountBalance->DisplayName == "Points Balance" || $accountBalance->DisplayName == 'Cashback Available') {
                    $balance = $accountBalance->Value;
                }

                if ($accountBalance->DisplayName == "Golden Gift Shop Points") {
                    $shopPoints = $accountBalance->Value;
                }

                if ($accountBalance->DisplayName == "Total Cashback Balance") {
                    $totalCashBack = $accountBalance->Value;
                }

                if ($accountBalance->DisplayName == "Future Cashback") {
                    $cashBack = $accountBalance->Value;
                }
            }// foreach ($location->AccountBalances as $accountBalance)

            if (isset($balance)) {
                $this->AddSubAccount([
                    'Code'        => 'nugget' . str_replace(' ', '', $location->DropdownDisplayName),
                    'DisplayName' => $location->DropdownDisplayName,
                    'Balance'     => $balance,
                    // Golden Gift Shop Points
                    'ShopPoints' => $shopPoints,
                    // Total Cashback Balance
                    'TotalCashbackBalance' => $totalCashBack,
                    // Future Cashback
                    'FutureCashback' => $cashBack,
                ], true);
            }
        }// foreach ($response->PropertySpecificInfo as $location)
    }
}
