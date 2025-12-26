<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerTangocard extends TAccountChecker
{
//    public static function GetAccountChecker($accountInfo) {
//        require_once __DIR__."/TAccountCheckerTangocardSelenium.php";
//        return new TAccountCheckerTangocardSelenium();
//    }
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyStaticIpDOP());
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL("https://www.tangocard.com/user/settings");

        if ($this->http->FindSingleNode('//a[@href="/user/logout"]')) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // makes it easier to parse an invalid HTML
        $this->http->FilterHTML = false;
        $this->http->removeCookies();
        $this->http->GetURL('https://www.tangocard.com/user/login');
        // parsing form on the page
        if (!$this->http->ParseForm(null, 1)) {
            return false;
        }
        // enter the login and password
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function Login()
    {
        // form submission
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode('//div[contains(@class, "errorMessage") and contains(., "I\'m not a robot") and contains(., "box and then resubmit your request to continue.")]')) {
            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
        }

        // look for logout link
        if ($this->http->FindSingleNode('//a[@href="/user/logout"]')) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "errorMessage")]/div')) {
            if (!strstr($message, "We're sorry, your answer to the security challenge was not correct")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            } else {
                $this->http->Log($message, LOG_LEVEL_ERROR);
            }
//            throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//input[@name="full_name"]/@value')));
        // Balance - Your Tango Card Balance
        $this->SetBalance($this->http->FindSingleNode('//div[@id="my-cards"]/h2/strong', null, null, '/\$(.+)/ims'));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode("//h3[contains(text(), 'Enter Your Tango Card Here')]")) {
                throw new CheckException("Tangocard.com (My Tango card) website is asking you to add your Tango Card, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    protected function parseCaptcha()
    {
        $this->logger->debug(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }
}
