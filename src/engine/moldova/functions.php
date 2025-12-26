<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerMoldova extends TAccountChecker
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
        $this->http->LogHeaders = true;
//        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['x-csrf-token'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->setDefaultHeader('X-CSRF-TOKEN', $this->State['x-csrf-token']);
        $this->http->setDefaultHeader('Accept', 'application/json, text/plain, */*');
        $this->http->setDefaultHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->http->PostURL('https://ffp.airmoldova.md/mdclublk/loyaltyInfo/statusInfo', [], [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->cardNumber)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://ffp.airmoldova.md/privilege/#/login/');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $this->http->GetURL('https://ffp.airmoldova.md/privilege/components/login/login.html');

        if (!$this->http->ParseForm('loginForm')) {
            return $this->checkErrors();
        }
        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->setDefaultHeader('Accept', 'application/json, text/plain, */*');
        $this->http->setDefaultHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->http->PostURL('https://ffp.airmoldova.md/mdclublk/spring-auth', [
            'username'    => $this->AccountFields['Login'],
            'password'    => $this->AccountFields['Pass'],
            'captchaCode' => $captcha,
        ]);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        $this->http->GetURL("https://www.airmoldova.md/club-private-en/");

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Dear passengers, due to transition to the new booking system, the Air Moldova Club program is temporarily unavailable.')]")) {
            throw new CheckException(ucfirst(strtolower($message)), ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->name, $response->email)) {
            if (!isset($this->http->Response['headers']['x-csrf-token'])) {
                return false;
            }

            $this->http->setDefaultHeader('X-CSRF-TOKEN', $this->http->Response['headers']['x-csrf-token']);

            $this->http->setCookie('CSRF-TOKEN', $this->http->Response['headers']['x-csrf-token']);
            $this->State['x-csrf-token'] = $this->http->Response['headers']['x-csrf-token'];

            return true;
        }// if (isset($response->name, $response->email))
        // INVALID USERNAME OR PASSWORD OR ACCOUNT NOT ACTIVATED
        if ($message = $this->http->FindPreg('/:"(INVALID USERNAME OR PASSWORD OR ACCOUNT NOT ACTIVATED)"/i')) {
            throw new CheckException(ucfirst(strtolower($message)), ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != 'https://ffp.airmoldova.md/mdclublk/loyaltyInfo/statusInfo') {
            $this->http->PostURL('https://ffp.airmoldova.md/mdclublk/loyaltyInfo/statusInfo', []);
        }
        $response = $this->http->JsonLog();

        if (!isset($response->activeMiles, $response->levelName, $response->profileStatus)) {
            return;
        }

        // Balance - ... Miles
        $this->SetBalance('' . $response->activeMiles);
        // card number
        $this->SetProperty('CardNumber', $response->cardNumber);
        // level
        if ($response->levelName == 'BASE') {
            $response->levelName = 'BASIC';
        } else {
            $this->sendNotification('refs #5229, moldova - new level');
        }
        $this->SetProperty('EliteLevel', $response->levelName);
        // status:
        $this->SetProperty('AccountStatus', $response->profileStatus);
        // expires: ...
        if ($exp = strtotime($response->expirationDate, false)) {
            $this->SetExpirationDate($exp);
        }

        $this->http->PostURL('https://ffp.airmoldova.md/mdclublk/profile/getMyProfile', []);
        $response = $this->http->JsonLog();
        // Name
        if (isset($response->profile->name)) {
            $this->SetProperty('Name', beautifulName("{$response->profile->name} {$response->profile->surname}"));
        }
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@name = 'loginForm']//div[contains(@class, 'g-recaptcha')]/@key", null, false, "/'(.+?)'/");
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
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
    }
}
