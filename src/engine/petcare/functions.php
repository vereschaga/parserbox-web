<?php

// Feature #4433

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPetcare extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.petcarerx.com/myaccount/mypoints.aspx';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->setKeepProfile(true);
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        /*
        $this->useFirefox();
        $this->setKeepProfile(true);
        */
        $this->useCache();
        $this->http->saveScreenshots = true;
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
        $this->http->removeCookies();
        $this->http->GetURL('https://www.petcarerx.com/login/?p=/myaccount/mypoints.aspx');

//        $this->challengeCaptchaForm();

        $this->waitForElement(WebDriverBy::xpath("//input[@id = 'floatingInput'] | //input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe"), 7);

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $this->saveResponse();
        }

        if (!$this->http->ParseForm("petcarerx_mainform")) {
            if ($this->http->FindPreg("#cpo\.src = \"/cdn-cgi/challenge-platform/\w/\w/orchestrate/captcha/v1\";#s")) {
                $this->DebugInfo = 'defence script';
            }

            return false;
        }
        $this->http->FormURL = 'https://www.petcarerx.com/login/default.aspx';
        $this->http->SetInputValue('ctl00$MainContent$tbUsername', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$MainContent$tbPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('ctl00$MainContent$btnSignIn', "Sign+In");

        $loginInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'floatingInput']"), 7);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'floatingPassword']"), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//input[@id = "btnSignIn"]/preceding-sibling::a'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            return false;
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $button->click();

        /*
        $captcha = $this->parseCaptcha();
        if ($captcha == false) {
            return false;
        }
        $this->http->SetInputValue("g-recaptcha-response", $captcha);
        $this->http->SetInputValue("h-captcha-response", $captcha);
        */

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath("
            //h1[contains(text(), 'My PetCareRx Points')]
            | //div[@id = 'login-error-msg' and not(@style)]
            | //p[@class = 'alert alert-danger' and not(@style)]
        "), 7);
        $this->saveResponse();

//        if (!$this->http->PostForm()) {
//            return false;
//        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//div[@id = 'login-error-msg' and not(@style)] | //p[@class = 'alert alert-danger' and not(@style)]")) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Please enter a valid email address or password')) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return false;
    }

    public function Parse()
    {
        // Balance - YOUR POINT BALANCE (PetCareRx Points available)
        $this->SetBalance($this->http->FindSingleNode('//h5[contains(text(), "YOUR POINT BALANCE")]/following-sibling::div/span'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(text(), 'HI, ')]", null, true, '/Hi,\s*([^<\-\!]+)/ims')));
        // You have $... in PetCareRx Points Available
        $this->SetProperty("BalanceWorth", $this->http->FindSingleNode("//p[contains(text(), 'in PetCareRx Points available')]", null, true, "/have (.+) in PetCareRx/"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Membership Status - Not Active
            if ($this->http->FindSingleNode("//small[contains(text(), 'Membership Status')]/following-sibling::strong") == 'Not Active') {
                $this->SetBalanceNA();
            }
        }

        // Name
        $this->http->GetURL("https://www.petcarerx.com/myaccount/aboutme.aspx");
        $name = $this->http->FindPreg("/\"full_name\":\"([^\"]+)/");

        if (!empty($name)) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    protected function parseCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);
        $key = $key ?? $this->http->FindSingleNode('//div[contains(@class, "h-captcha")]/@data-sitekey');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => "hcaptcha",
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a/span[text() = 'Sign Out']")) {
            return true;
        }

        return false;
    }

    private function challengeCaptchaForm()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->ParseForm("challenge-form")) {
            return false;
        }
        $key = "33f96e6a-38cd-421b-bb68-7806e1764460";
        $captcha = $this->parseCaptcha($key);

        if ($captcha == false) {
            return false;
        }
        $this->http->SetInputValue("g-recaptcha-response", $captcha);
        $this->http->SetInputValue("h-captcha-response", $captcha);
        $this->http->PostForm();

        return true;
    }
}
