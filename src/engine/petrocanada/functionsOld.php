<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerPetrocanada extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        // $this->http->SetProxy($this->proxyReCaptchaIt7());
        // $this->setProxyBrightData(null, "static", "ca");
        // $this->setProxyGoProxies(null, 'ca');
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.petro-canada.ca/en/personal/my-petro-points", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.petro-canada.ca/en/personal/login?returnUrl=%2Fen%2Fpersonal%2Fmy-petro-points");
        $token = $this->http->FindSingleNode("//input[@name = '__RequestVerificationToken']/@value");

        if (!$this->http->ParseForm(null, "//form[contains(@class, 'sign-in__form')]") || !$token) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return $this->checkErrors();
        }

        $data = [
            "email"             => $this->AccountFields['Login'],
            "password"          => $this->AccountFields['Pass'],
            "recaptchaResponse" => $captcha,
        ];
        $headers = [
            "__RequestVerificationToken" => $token,
            "Accept"                     => "*/*",
            "content-type"               => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.petro-canada.ca/en/api/petrocanadaaccounts/signin?ds=C29039EA9E1C49A5BEC96D19CF0FEED4", json_encode($data), $headers, 80);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Web site is experiencing a technical glitch
        if ($message = $this->http->FindSingleNode('//p[contains(text(),"possible that our Web site is experiencing a technical glitch")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Petro-Points™ login is temporarily unavailable due to scheduled maintenance.
        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "Petro-Points™ login is temporarily unavailable due to scheduled maintenance.")]
                | //div[contains(@class, "content") and contains(., "Petro-Points login will be unavailable due to scheduled maintenance on")]
                | //h1[contains(text(), "We\'re working on our site right now.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            // Service Unavailable
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '504 Gateway Time-out')]")
            || $this->http->FindPreg("/(Service Unavailable)/ims")
            || $this->http->FindPreg("/(The service is unavailable\.)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $redirect = $response->RedirectUrl ?? null;

        if ($redirect) {
            $this->http->NormalizeURL($redirect);
            $this->http->RetryCount = 0;

            if (!$this->http->GetURL($redirect)) {
                $this->http->GetURL("https://www.petro-canada.ca/en/personal/");
                sleep(2);

                if (!$this->http->GetURL("https://www.petro-canada.ca/en/personal/my-petro-points/account")) {
                    sleep(2);

                    if (!$this->http->GetURL("https://www.petro-canada.ca/en/personal/my-petro-points/transactions")) {
                        sleep(2);
                        $this->http->GetURL("https://www.petro-canada.ca/en/personal/my-petro-points");
                    }// if (!$this->http->GetURL("https://www.petro-canada.ca/en/personal/my-petro-points/transactions"))
                }// if (!$this->http->GetURL("https://www.petro-canada.ca/en/personal/my-petro-points/account"))
            }// if (!$this->http->GetURL($redirect))
            $this->http->RetryCount = 2;
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }

        $message = $response->ErrorMessage ?? null;
        $resultCode = $response->AccountValidationResultCode ?? null;
        $this->logger->error("[ErrorMessage]: {$message}");
        $this->logger->error("[AccountValidationResultCode]: {$resultCode}");

        if (!$resultCode || !$message) {
            if (strstr($message, 'Sorry, the login function has been disabled for 30 minutes')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }
        // The email or password you entered is not correct. Please try again. Passwords must have 8-16 characters and at least one number, one lowercase letter, one uppercase letter and one special character (from @$!%*#?& ).
        if ($resultCode == 'invalid.password') {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, but your account has been temporarily locked-out. Please try again in 30 minutes, or contact our customer service team for help.
        if ($resultCode == 'profile.soft.locked') {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($resultCode == 'invalid.recaptcha') {
            throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
        }
        // Sorry, an error occurred on our servers. Our tech team has been notified.
        if ($resultCode == 'internal.error') {
            throw new CheckRetryNeededException(3, 7, $message);
        }
        // empty div with error
        $this->logger->debug(">>" . $this->http->Response['body'] . "<<");

        if (
            Html::cleanXMLValue($this->http->Response['body']) == '{"AccountValidationResultCode":null,"ErrorMessage":null,"WarningMessage":null,"IsLoginDisabled":false,"RedirectUrl":null,"UserId":null}'
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[@class = 'account-well__name'] | //div[@class = 'user-info__full-name']")));
        // Account #
        $this->SetProperty("Number", $this->http->FindSingleNode("//span[(@class = 'user-info__card-number')]"));
        // Balance - Petro-Points
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'You have')]/following-sibling::strong[1]"));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[div[form[contains(@class, 'sign-in__form')]]]/following-sibling::div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "type"       => "RecaptchaV3TaskProxyless",
            "websiteURL" => $this->http->currentUrl(),
            "websiteKey" => $key,
            "minScore"   => 0.3,
        ];

        return $this->recognizeAntiCaptcha($this->recognizer, $parameters);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//button[contains(text(), 'Sign out')]")) {
            if ($this->http->FindPreg('/<h1 class="content__headline">It\'s time to create a new password.<\/h1>/')) {
                throw new CheckException("{$this->AccountFields['DisplayName']} website is asking you to create a new password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }

            return true;
        }

        return false;
    }
}
