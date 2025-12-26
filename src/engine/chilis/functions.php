<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerChilis extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.chilis.com/account/rewards';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_USA));
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.chilis.com/login');

        if (
            !$this->http->FindSingleNode('//title[contains(text(), "Chili\'s")]')
            || $this->http->Response['code'] != 200
        ) {
            return $this->checkErrors();
        }

        return true;
    }

    public function Login()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            $this->AccountFields['Login'] = str_replace(['(', ')', '+', '-', ' '], '', $this->AccountFields['Login']);
        }

        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];
        $data = [
            'username' => $this->AccountFields['Login'],
        ];
        $captcha = $this->parseReCaptcha();

        if ($captcha) {
            $data['recaptchaToken'] = $captcha;
        }

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.chilis.com/api/v1/loyalty/getAccountStatus', json_encode($data), $headers);
        $accountStatusData = $this->http->JsonLog();

        if (isset($accountStatusData->error, $accountStatusData->message)) {
            $message = $accountStatusData->message;

            if (
                strstr($message, "Incorrect password. Please try again.")
                || strstr($message, "Loyalty account not found.")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $this->captchaReporting($this->recognizer);

        $phone = $accountStatusData->phone ?? null;

        if (!isset($phone)) {
            return $this->checkErrors();
        }

        $data = [
            'username'	=> $this->AccountFields['Login'],
            'password'	=> $this->AccountFields['Pass'],
        ];

        if ($captcha) {
            $data['recaptchaToken'] = $captcha;
        }

        $this->http->PostURL('https://www.chilis.com/api/v1/loyalty/login', json_encode($data), $headers);
        $authResult = $this->http->JsonLog();

        if (isset($authResult->error, $authResult->message)) {
            $message = $authResult->message;

            if (
                strstr($message, "Incorrect password. Please try again.")
                || strstr($message, "Loyalty account not found.")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $data = [
            'phone'    => $phone,
            'password' => $this->AccountFields['Pass'],
        ];

        $this->http->PostURL('https://www.chilis.com/login/dge', json_encode($data), $headers);
        $response = $this->http->JsonLog();

        if (isset($response->loggedIn) && $response->loggedIn && $this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->RetryCount = 2;

        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // Balance - YOU HAVE ... REWARDS
        $this->SetBalance($this->http->FindSingleNode('//a[@id = "rewards-logged-in-summary-rewards"]'));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[@class = "profile-name"]')));
        // REWARDS MEMBER ID
        $this->SetProperty('Number', str_replace(['(', ')', ' ', '-'], '', $this->http->FindSingleNode('//div[@class = "rewards-number"]')));
        // SubAccounts
        $rewards = $this->http->XPath->query('//div[@class = "rewards-active-item"]');
        $this->logger->debug("Total {$rewards->length} rewards were found");

        foreach ($rewards as $reward) {
            $time = $this->http->FindSingleNode('.//div[contains(@class, "rewards-active-expiration")]/b', $reward);
            $date = DateTime::createFromFormat('M. d, Y', $time);
            $displayName = $this->http->FindSingleNode('.//div[contains(@class, "rewards-active-title")]', $reward);

            $this->AddSubAccount([
                'Code'           => 'chilis' . md5($displayName),
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => $date ? intval($date->format('U')) : false,
            ]);
        }
    }

    private function loginSuccessful(): bool
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if (
            $this->http->Response['code'] === 200
            && $this->http->FindSingleNode('//form[contains(@action, "logout")]/@action')
            || $this->http->FindSingleNode('//div[contains(text(), "Welcome, ")]')
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg('/An error occurred while fetching account status: Error: An error occurred while fetching account status./')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/recaptcha\/enterprise\.js\?render=([^\"\'\&]+)/");

        if (!$key) {
            return false;
        }

        /*
        $postData = [
            "type"       => "RecaptchaV3TaskProxyless",
            "websiteURL" => $this->http->currentUrl(),
            "websiteKey" => $key,
            "minScore"   => 0.3,
            "pageAction" => "accountStatus",
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        */

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            "action"    => "accountStatus",
            "min_score" => 0.3,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
