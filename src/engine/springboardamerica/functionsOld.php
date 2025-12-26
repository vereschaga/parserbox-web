<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSpringboardamerica extends TAccountChecker
{
//    public static function GetAccountChecker($accountInfo) {
//        require_once __DIR__."/TAccountCheckerSpringboardamericaSelenium.php";
//        return new TAccountCheckerSpringboardamericaSelenium();
//    }

    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.springboardamerica.com/");
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.springboardamerica.com/");

        if (!$this->http->ParseForm("login")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('signup', 'Login');
        $this->http->SetInputValue('remember', '1');

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindSingleNode("//span[@id = 'ErrorMessageLabel']", null, false)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Springboard America is currently undergoing maintenance
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Springboard America is currently undergoing maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'We are performing emergency maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The application you are trying to access is currently down for scheduled maintenance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The application you are trying to access is currently down for scheduled maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * We are working on the last of our changes as we bring you your new and improved Springboard America Community.
         * We expect our maintenance to be completed by noon on April 9th or sooner, please try again after this time.
         */
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We expect our maintenance to be completed by')]")) {
            throw new CheckException("We are working on the last of our changes as we bring you your new and improved Springboard America Community. " . $message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Sparq is currently unavailable
        if ($message = $this->http->FindPreg("/(Sparq is currently unavailable)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application.
        if ($message = $this->http->FindPreg("/(Server Error in \'\/\' Application\.)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        // Looks like you’re having trouble. Please click forgot password to reset your password and gain access to the system.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We could not log you in with those details, please try again.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The email address or password you entered is incorrect. Please try again.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "You have entered an incorrect email address or password. Please try again.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry your account is registered as "unsubscribed", which means that the system cannot log you in.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry your account is registered as \"unsubscribed\", which means that the system cannot log you in.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Please complete the security verification question
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Please complete the security verification question")]')) {
            $this->logger->error(">>> " . $message);

            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.springboardamerica.com/profile/basic");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[@id = 'profile_basic_firstname']/@value") . " " . $this->http->FindSingleNode("//input[@id = 'profile_basic_surname']/@value")));

        $this->http->GetURL("https://www.springboardamerica.com/points/incentives/account/PAC-vlt1-1");
        // Balance - Rewards Balance
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'Rewards Balance')]/span"));

        if ($this->ErrorCode === ACCOUNT_ENGINE_ERROR) {
            $this->SetWarning($this->http->FindSingleNode("//div[contains(text(), 'The reward system is currently undergoing scheduled maintenance. Please try again later.')]"));
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'login']//div[@class = 'g-recaptcha']/@data-sitekey");

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }
}
