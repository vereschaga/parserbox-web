<?php

// refs #2054, huggies
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerHuggies extends TAccountChecker
{
    use ProxyList;

    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->LogHeaders = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Invalid email format', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->RetryCount = 1;
        $this->http->setMaxRedirects(7);
        $this->http->GetURL("https://www.huggies.com/en-us/?modal=true");

        if (!$this->http->ParseForm(null, 1, true, '//form[@class="consumer-form"]')) {
            return false;
        }

        $this->http->FormURL = 'https://www.huggies.com/signin.sso?ReturnUrl=%2fen-us%2f%3fmodal%3dsignin';
        $this->http->SetInputValue('consumer_email', $this->AccountFields['Login']);
        $this->http->SetInputValue('consumer_password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('consumer_rememberme', 'true');

        if ($key = $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey")) {
            $captcha = $this->parseCaptcha($key);

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue("g-recaptcha-response", $captcha);
        }

        return true;
    }

    /*function GetRedirectParams($targetURL = NULL) {
        $arg = parent::GetRedirectParams($targetURL);
        return $arg;
    }*/

    public function checkErrors()
    {
        // Invalid crenetials
        if (strstr($this->http->currentUrl(), 'errorMessage=Invalid')) {
            $this->logger->notice(">>> errorMessage=Invalid");

            if ($matches = $this->http->FindPreg("/errorMessage=([^<]+)/ims", false, $this->http->currentUrl())) {
                throw new CheckException(NameToText(str_replace('+', ' ', $matches)), ACCOUNT_INVALID_PASSWORD);
            }
        }// if (strstr($this->http->currentUrl(), 'errorMessage=Invalid'))

        if (strstr($this->http->currentUrl(), 'errorMessage=Adresse+%c3%a9lectronique+ou+mot+de+passe+invalide.')) {
            $this->http->Log(">>> Invalid e-mail address or password. Please enter a valid e-mail address and password.");

            throw new CheckException("Invalid e-mail address or password. Please enter a valid e-mail address and password.", ACCOUNT_INVALID_PASSWORD);
        }// if (strstr($this->http->currentUrl(), 'errorMessage=Invalid'))

        if (strstr($this->http->currentUrl(), 'errorMessage=Please+enter+a+valid+email+address')
            || strstr($this->http->currentUrl(), 'errorMessage=Please%20enter%20a%20valid%20email%20address')) {
            $this->logger->notice(">>> Please enter a valid email address");

            throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }// if (strstr($this->http->currentUrl(), 'errorMessage=Invalid'))
        //# The Huggies site is currently undergoing maintenance
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'This section of the Huggies site is currently undergoing maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $message = $this->http->FindSingleNode("//label[@id = 'ui-message']");
        $this->logger->error("error -> '{$message}'");
        // Your account has been locked. To unlock your account, reset your password.
        if (strstr($message, 'Your account has been locked. To unlock your account, reset your password.')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // reCaptcha issue
        if (strstr($message, 'Invalid code entered. Please try again.||recaptcha_area')) {
            throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
        }

        //# The Huggies site is currently undergoing maintenance
        if ($message = $this->http->FindSingleNode("//title[contains(text(), 'The Huggies site is currently undergoing maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindPreg("/site is currently down for a nap/ims")) {
            throw new CheckException("Sorry — the HUGGIES&reg; site is currently down for a nap.<br/>We're working now to get it up and running. Please check back soon!", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/need updated profile information/ims")) {
            throw new CheckException('Action Required. Please login to Huggies (Enjoy the Ride Rewards) and respond to a message that you will see after your login.', ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//div[@class = "server-error"]/div/p[contains(text(), "is temporarily down for maintenance")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Error
        if ($message = $this->http->FindSingleNode("//body[contains(text(), 'an internal server error has occurred')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'Service Unavailable')])[1]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# 403 - Forbidden: Access is denied.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), '403 - Forbidden: Access is denied')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Error in '/' Application
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Hi, our Website is currently undergoing scheduled maintenance. Please check back soon.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Hi, our Website is currently undergoing scheduled maintenance. Please check back soon.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // maintenance
        if ($message = $this->http->FindSingleNode('//h3[contains(text(), "re making some exciting changes to the Huggies® Rewards program to make your experience even better.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->currentUrl() == 'https://www.huggies.com/en-US/Register') {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Oops, there was a hiccup!
        if ($this->http->FindSingleNode("//p[contains(text(), 'Oops, there was a hiccup!')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->currentUrl() == 'https://www.huggies.com/en-us/?modal=signin&modal=true') {
            throw new CheckRetryNeededException(2);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->ParseForm("samlform")) {
            $this->http->PostForm();
        }
        // Reset Password
        elseif (($this->http->FindPreg('#var getCulture = \'en-us\';\s*if \(\$\(".consumer-signin-forgotpassword"\).is\(#')
                && $this->http->FindPreg('#<a class="consumer-signin-forgotpassword" href="/forgotpassword\.sso">Forgot Password\?</a>#'))
                || ($this->http->FindPreg('#/en-US/forgotpassword#', false, $this->http->currentUrl())
                    && ($this->http->FindSingleNode("//h1[contains(text(),'Reset Huggies')]")
                    || $this->http->FindSingleNode("//h1[contains(text(),'Create Huggies')]")))) {
            $this->throwProfileUpdateMessageException();
        }
        // Update your profile to keep the fun going
        if ($this->http->FindSingleNode("//h3[contains(text(),'Update your profile to keep the fun going')]")
            && ($message = $this->http->FindSingleNode("//p[contains(text(),'Complete your huggies member profile for a more customized website experience')]"))) {
            $this->throwProfileUpdateMessageException();
        }

        // success
        if ($this->loginSuccessful()) {
            return true;
        }
        // Invalid credentials
        $message = $this->http->FindSingleNode('//label[@id="ui-message"]');

        if (strstr($message, 'Invalid email address or password')) {
            throw new CheckException('Invalid email address or password', ACCOUNT_INVALID_PASSWORD);
        }
        // For your protection, your account has been locked after several unsuccessful log-in attempts. To unlock your account, reset your password.
        if (strstr($message, 'For your protection, your account has been locked after several unsuccessful log-in attempts.')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - You have ... points!
        $this->SetBalance($this->http->FindSingleNode("//header//span[contains(@id, '_RewardsBalanceSpan')]//span[@class = 'points']"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->http->GetURL('https://www.huggies.com/en-us/rewards-history');

            if ($this->http->FindSingleNode("//a[@id = 'main_0_CTARedeemUnauthenticated' and contains(text(), 'Join Today') or contains(text(), 'GET STARTED')]")) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->SetBalance($this->http->FindSingleNode("//div[contains(@class, 'have-points')]/span"));
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        if ($this->http->currentUrl() != 'https://www.huggies.com/en-US/profile') {
            $this->http->GetURL('https://www.huggies.com/en-US/profile');
        }
        // Full Name
        $name = $this->http->FindSingleNode("//input[contains(@name, 'firstname')]/@value") . ' ' . $this->http->FindSingleNode("//input[contains(@name, 'lastname')]/@value");

        if (strlen(Html::cleanXMLValue($name)) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    /*
    function IsLoggedIn() {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.huggies.com/en-US/profile', [], 20);
        $this->http->RetryCount = 2;
        if ($this->loginSuccessful())
            return true;

        return false;
    }
    */

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(text(), \"Sign Out\") and not(contains(@href, 'lbRegisteredSignOutPP'))]")) {
            return true;
        }

        return false;
    }

    private function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters, true, 3, 1);

        return $captcha;
    }
}
