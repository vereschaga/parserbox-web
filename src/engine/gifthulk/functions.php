<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerGifthulk extends TAccountChecker
{
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
        $this->http->GetURL('https://gifthulk.me/dashboard');
        $this->http->RetryCount = 2;

        if ($this->http->currentUrl() == 'https://gifthulk.me/dashboard') {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://gifthulk.me/');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json;charset=utf-8",
        ];
        $data = [
            "email"    => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];

        $this->http->GetURL('https://gifthulk.me/views/section1.html');

        if ($key = $this->http->FindPreg("/window.__gifthulk_signin_captcha__ = grecaptcha.render\('recaptcha-signin', \{'sitekey' : '([^\']+)/")) {
            $captcha = $this->parseCaptcha($key);

            if ($captcha !== false) {
                $data['captcha'] = $captcha;
            }
        }

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://gifthulk.me/account/signin", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'New GiftHulk coming soon!')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# The page you are looking for is temporarily unavailable
        if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'The page you are looking for is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Error establishing a database connection
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Error establishing a database connection')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->loginUrl)) {
            $this->http->GetURL($response->loginUrl);
        }
        // Success login
        if (strstr($this->http->currentUrl(), 'https://gifthulk.me/dashboard')) {
            return true;
        }
        // Invalid credentials
        if (isset($response->errors[0]->password) && $response->errors[0]->password == 'Invalid password') {
            throw new CheckException("Invalid password", ACCOUNT_INVALID_PASSWORD);
        }
        // Account was deleted
        if (isset($response->errors[0]->email) && $response->errors[0]->email == 'Account was deleted') {
            throw new CheckException("Account was deleted", ACCOUNT_INVALID_PASSWORD);
        }
        // User with such email or username doesn't exist
        if (isset($response->errors[0]->email) && $response->errors[0]->email == "User with such email or username doesn't exist") {
            throw new CheckException("User with such email or username doesn't exist", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindPreg("/lastName\":\"([^\"]+)/") . " " . $this->http->FindPreg("/firstName\":\"([^\"]+)/")));
        // Hulk points - balance
        $this->SetBalance($this->http->FindPreg("/hulkCoins\":([\-\d]+)/"));
        // Lifetime Hulk Coins
        $this->SetProperty('LifetimeHulkCoins', $this->http->FindPreg("/earnedHulkCoins\":(\d+)/"));
        // Your Level
        $this->SetProperty('YourLevel', beautifulName($this->http->FindPreg("/level\":\"([^\"]+)/")));

        // Daily Bonus
        $headers = [
            "Authorization" => "Bearer " . $this->http->getCookieByName("access_token", "gifthulk.me"),
            "Accept"        => "application/json",
        ];
        $this->http->GetURL('https://gifthulk.me/users/today-earned-coins?', $headers);
        $this->SetProperty('Bonus', $this->http->FindPreg("/^([\-\d]+)$/"));
    }

    protected function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        //$key = $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => 'https://gifthulk.me/',
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }
}
