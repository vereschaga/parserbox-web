<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSavingstar extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.coupons.com/?utm_source=instapage&utm_medium=web&crid=instapage';

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerSavingstarSelenium.php";

        return new TAccountCheckerSavingstarSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
        // makes it easier to parse an invalid HTML
        $this->http->FilterHTML = false;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//a[contains(@href,'/sign_out')]")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->http->GetURL(self::REWARDS_PAGE_URL);
        $this->http->GetURL('https://www.coupons.com/store-loyalty-card-coupons/getSignInSavingStar/');

        if (!$this->http->ParseForm(null, '//div[contains(@class, "signin-signup-form")]/form')) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://www.coupons.com/ajax/signin/';
        $this->http->SetInputValue("email", $this->AccountFields["Login"]);
        $this->http->SetInputValue("pwd", $this->AccountFields["Pass"]);
        $this->http->SetInputValue("action", "doSignin");
        $this->http->SetInputValue("remember", "Y");
        $this->http->SetInputValue("pid", "13306");
        $this->http->SetInputValue("nid", "10");
        $this->http->SetInputValue("zid", "iq37");

        $captcha = $this->parseCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('grecaptcha', $captcha);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }
        $response = $this->http->JsonLog();
        // login successful
        if (isset($response->Status, $response->uToken) && $response->Status == 'OK') {
            return true;
        }
        // check for invalid password
        if ($message = $this->http->FindSingleNode("//div[@id='login_user_errors']")) {
            if ($message != 'Please verify you are not a robot') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            } else {
                $this->logger->error("Error -> Please verify you are not a robot");

                throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Balance
        $this->SetBalance($this->http->FindSingleNode("//div[@id='balance']/strong", null, true, "/\\$([\-\d\.\,]+)$/"));
        // lifetime
        $this->SetProperty("Lifetime", $this->http->FindSingleNode("//div[@id='lifetime_savings']/strong", null, true, "/(\\$[\-\d\.\,]+)$/"));

        if ($this->ErrorCode === ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode("//div[@id='balance']/strong") === 'N/A') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode === ACCOUNT_ENGINE_ERROR)

        // Name
        $this->http->GetURL('https://www.coupons.com/user-profile/account-info');
        $firstName = $this->http->FindSingleNode('//span[contains(@class, "first_name_field")]');
        $lastName = $this->http->FindSingleNode('//span[contains(@class, "last_name_field")]');
        $name = beautifulName(sprintf('%s %s', $firstName, $lastName));

        if ($name) {
            $this->SetProperty('Name', $name);
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
//        $key = $this->http->FindSingleNode('//div[contains(@class, "signin-signup-form")]/form//div[contains(@class, "g-recaptcha")]/@data-sitekey');
        $key = '6LdD6LUZAAAAANct1-5bWpPzzZ0XBhmvA09D8ojz';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => "https://www.coupons.com/?utm_source=instapage&utm_medium=web&crid=instapage", //$this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }
}
