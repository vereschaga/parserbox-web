<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerJersey extends TAccountChecker
{
    use ProxyList;
    private const REWARDS_PAGE_URL = "https://www.jerseymikes.com/account";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $auth0Client = "eyJuYW1lIjoiYXV0aDAtdnVlIiwidmVyc2lvbiI6IjIuMy4xIn0=";
    private $client_id = "yFt5EkcaIS1tz2ypxdk81ZyWuKVhkaU2";

    private $headers = [
        "Accept"           => "*/*",
        "Accept-Language"  => "en-US,en;q=0.5",
        "x-client-name"    => "WEBSITE",
        "x-client-version" => "2.0.3-200",
        "Origin"           => "https://www.jerseymikes.com",
        "Referer"          => "https://www.jerseymikes.com/",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if ($this->attempt == 1) {
            $this->setProxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA);
        }
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://login.jerseymikes.com/authorize?client_id=yFt5EkcaIS1tz2ypxdk81ZyWuKVhkaU2&scope=openid+profile+email&audience=https%3A%2F%2Fprd.bedrock.jerseymikes.com%2Fapi&redirect_uri=https%3A%2F%2Fwww.jerseymikes.com%2Fauth0%2Fcallback&response_type=code&response_mode=query&state=TnNWQm9ucVJOVWwwTm1NUnMyVWxsbDNjbFNvblZhdE51bU03Ql84S1cxcg%3D%3D&nonce=ek9%2BOVBjYndOQmp5UWVldEp5c0xaSDQxRlVCUmxIWTFaOEg2ai0yb1BPTw%3D%3D&code_challenge=IKdxp9GH-xEOH78ePYYs7CDRtuowcxAIVJ3YHvcWQEs&code_challenge_method=S256&auth0Client=eyJuYW1lIjoiYXV0aDAtdnVlIiwidmVyc2lvbiI6IjIuMy4xIn0%3D');
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm(null, "//form[descendant::input[@name='username']]")) {
            if ($this->http->Response['code'] == 403 && $this->http->FindPreg("/error code: 1020/")) {
                $this->DebugInfo = 'error code: 1020';
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(2, 1);
            }

            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $captcha = $this->parseCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            $this->http->SetInputValue('captcha', $captcha);
        }

        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && $this->http->Response['code'] != 400) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        if ($this->http->ParseForm(null, "//form[contains(@class, '_form-login-password')]")) {
            $this->http->SetInputValue('username', $this->AccountFields['Login']);
            $this->http->SetInputValue('password', $this->AccountFields['Pass']);

            $this->http->RetryCount = 0;

            if (!$this->http->PostForm() && $this->http->Response['code'] != 400) {
                return $this->checkErrors();
            }

            $this->http->RetryCount = 2;
        }

        return true;
    }

    public function Login()
    {
        $code = $this->http->FindPreg("/\?code=([^&]+)/", false, $this->http->currentUrl());

        if ($code) {
            $this->captchaReporting($this->recognizer);

            $data = [
                "grant_type"    => "authorization_code",
                "client_id"     => $this->client_id,
                "code_verifier" => "Xig9bfUn23o-ymZmI8AKRoaodhlIrwOkINhQ5l66f5N",
                "code"          => $code,
                "redirect_uri"  => "https://www.jerseymikes.com/auth0/callback",
            ];
            $headers = [
                'Accept'       => '*/*',
                'Content-Type' => 'application/json',
                'Auth0-Client' => $this->auth0Client,
            ];

            $this->http->PostURL("https://login.jerseymikes.com/oauth/token", json_encode($data), $headers);
            $response = $this->http->JsonLog();

            if (isset($response->access_token)) {
                $this->State['Authorization'] = "Bearer {$response->access_token}";

                return $this->loginSuccessful();
            }

            return false;
        }

        if ($message = $this->http->FindSingleNode('//form[descendant::input[@name = "username"]]//span[normalize-space() = "Wrong email or password"]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//p[@class = "ulp-alert-text"] | //div[@data-error-code]/p')) {
            $this->captchaReporting($this->recognizer);
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'We periodically ask customers to update their password to keep their account secure. Your login will be blocked until your password is changed.')
                || strstr($message, 'We have detected a potential security issue with this account. To protect your account, we have prevented this login.')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//li[contains(text(), "Select the checkbox to verify you are not a robot.")]')) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(2, 1);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance - Points
        $this->SetBalance($response->data->pointsBalance ?? null);
        // Name
        $this->SetProperty('Name', beautifulName($response->data->name ?? null));

//        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
//            $this->SetWarning($this->http->FindSingleNode("//div[contains(text(), 're sorry, the account page is temporarily disabled. Please try again later.')]"));
//        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//form[descendant::input[@name = "username"]]//div[@data-captcha-sitekey]/@data-captcha-sitekey');

        if (!$key) {
            return false;
        }

        $postData = [
            "type"       => "TurnstileTaskProxyless",
            "websiteURL" => $this->http->currentUrl(),
            "websiteKey" => $key,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => "turnstile",
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://bapi.prd.jerseymikes.com/api/v0/customers?withTextingStatus=true", $this->headers + ["Authorization" => $this->State['Authorization']], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, 'email');

        $email = $response->data->email ?? null;
        $this->logger->debug("[email]: {$email}");

        if (
            $email
            && strtolower($email) == strtolower($this->AccountFields['Login'])
        ) {
            $this->headers += ["Authorization" => $this->State['Authorization']];

            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "re sorry, you have attempted this action too many times and will be temporarily blocked")]')) {
            $this->ErrorReason = self::ERROR_REASON_BLOCK;
            $this->DebugInfo = $message;

            throw new CheckRetryNeededException(2, 0);
//            return false;
        }

        return false;
    }
}
