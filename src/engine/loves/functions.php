<?php

class TAccountCheckerLoves extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public const WAIT_TIMEOUT = 7;

    public $loginTypeOptions = [
        ""       => "Select your login type",
        "fleet"  => "Business/Fleet",
        "driver" => "Professional Driver",
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->loginTypeOptions;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $this->useFirefoxPlaywright();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;


        $this->http->saveScreenshots = true;

        if (empty($this->AccountFields["Login2"])) {
            $this->AccountFields["Login2"] = 'driver';
        }
    }

//    public function IsLoggedIn()
//    {
//        if ($this->loginSuccessful()) {
//            return true;
//        }
//        return false;
//    }

//    private function loginSuccessful()
//    {
//        $this->logger->notice(__METHOD__);
//        $this->saveResponse();
//
//        return false;
//    }

    public function LoadLoginForm()
    {
        $typeSettings = [
            'fleet'  => [
                'buttonId'   => '//button[contains(., "Login")]',
                'usernameId' => '//input[@placeholder = "Username"]',
                'passwordId' => '//input[@placeholder = "Password"]',
                'loginBtn'   => 'Login/FleetAuth0Login',
            ],
            'driver' => [
//                'buttonId'   => '//button[@name="action"]',
//                'usernameId' => '//input[@id = "username"]',
//                'passwordId' => '//input[@id = "password"]',
//                'loginBtn'   => 'Login/LoyaltyAuth0Login',
                'buttonId'   => '//button[contains(., "Login")]',
                'usernameId' => '//input[@placeholder = "Username"]',
                'passwordId' => '//input[@placeholder = "Password"]',
                'loginBtn'   => 'Login/FleetAuth0Login',
            ],
        ];

        $this->http->removeCookies();
        $this->http->GetURL('https://www.loves.com/en/lovesconnect');
        $login = $this->waitForElement(WebDriverBy::xpath($typeSettings[$this->AccountFields["Login2"]]['usernameId']), self::WAIT_TIMEOUT);
        $this->driver->executeScript('try { $(\'.reveal-modal-bg, #welcomePopup\').hide(); } catch (e) {}');
        $this->saveResponse();

        if (!$login && $this->clickCloudFlareCheckboxByMouse($this)) {
            $login = $this->waitForElement(WebDriverBy::xpath($typeSettings[$this->AccountFields["Login2"]]['usernameId']), 10);
            $this->saveResponse();
        }

        $pass = $this->waitForElement(WebDriverBy::xpath($typeSettings[$this->AccountFields["Login2"]]['passwordId']), 0);
        $button = $this->waitForElement(WebDriverBy::xpath($typeSettings[$this->AccountFields["Login2"]]['buttonId']), 0);

        if (!$login || !$pass || !$button) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
             //h2[starts-with(normalize-space(),"Update Your Account")]
             | //span[contains(@class, "error-message")]
             | //div[contains(@class, "error")]/text()[1]
             | //a[contains(text(), "Log Out")]
             | //p[contains(text(), "We\'ve sent an email with your code to")]
        '), self::WAIT_TIMEOUT * 4);
        $this->saveResponse();

        if ($this->http->FindNodes('//a[contains(text(), "Log Out")]')) {
            return true;
        }

        if ($question = $this->http->FindSingleNode('//p[contains(text(), "We\'ve sent an email with your code to")]')) {
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        $message = $this->http->FindSingleNode('
            //span[contains(@class, "error-message") and normalize-space() != ""]
            | //div[contains(@class, "error")]/text()[1]
        ');
        $this->logger->error("Message: {$message}");

        if ($message) {
            if (
                strstr($message, 'Wrong email or password')
                || strstr($message, 'Username or password not valid')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }

        if ($this->http->FindSingleNode("//h2[starts-with(normalize-space(),'Update Your Account')]")) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindPreg("/<body>The page cannot be displayed because an internal server error has occurred\.</")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $verificationCode = $this->waitForElement(WebDriverBy::xpath('//input[@name="code"]'), 5);
        $contBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue")]'), 0);
        $this->saveResponse();

        if (!$verificationCode || !$contBtn) {
            return false;
        }

        $verificationCode->sendKeys($this->AccountFields['Login']);
        $this->saveResponse();
        $contBtn->click();
        $this->saveResponse();
        $this->logger->debug("wait results");
        sleep(5);
        $this->waitForElement(WebDriverBy::xpath('
            //a[contains(text(), "Log Out")]
        '), 5);
        $this->saveResponse();

        return true;
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.loves.com/api/sitecore/LoyaltyDashboardAccounts/GetLoyaltyDetail");
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));
        // Balance - Points Balance
        $this->SetBalance($response->PointBalance ?? null);
        // Account Number
        $this->SetProperty('AccountNumber', $response->CardNumber ?? null);
        // Drink Refills
        $this->SetProperty('DrinkRefills', $response->DrinkCredits ?? null);
        // Shower Credits
        $this->SetProperty('ShowerCredits', $response->ShowerCredits ?? null);
        // TirePass Credits
        $this->SetProperty('TirePassCredits', $response->TirePassCredits ?? null);
        // Loyalty Status
        $this->SetProperty('Status', $response->Tier ?? null);
        // Gallons this month
        $this->SetProperty('GallonsThisMonth', $response->MonthToDateGallons ?? null);

        $this->http->GetURL("https://www.loves.com/api/sitecore/LoyaltyManageUsers/GetProfile");
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));
        // Name
        $this->SetProperty('Name', beautifulName(($response->FirstName ?? null) . " " . ($response->LastName ?? null)));
    }
}
