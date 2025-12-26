<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerIpic extends TAccountChecker
{
    use ProxyList;

    private $headers = [
        "Content-Type" => "application/json;charset=utf-8",
        "Accept"       => "application/json, text/plain, */*",
    ];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyDOP());
        $proxy = $this->http->getLiveProxy("https://www.ipictheaters.com/#/account", 5);
        $this->http->SetProxy($proxy);
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['apikey'])) {
            return false;
        }
        $this->http->setDefaultHeader('apikey', $this->State['apikey']);

        return $this->loginSuccessful();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        unset($this->State['apikey']);
        $this->http->unsetDefaultHeader('apikey');

        $this->http->GetURL("https://www.ipic.com/");

        if ($this->http->Response['code'] !== 200) {
            return false;
        }
        $this->http->GetURL("https://api.ipic.com/iPicAPI2/registersession", $this->headers);
        $response = $this->http->JsonLog();

        if (!isset($response->sessionId)) {
            return false;
        }
        $this->http->setDefaultHeader('apikey', $response->sessionId);
        $data = [
            "emailAddress"    => $this->AccountFields['Login'],
            "password"        => $this->AccountFields['Pass'],
            "stayLoggedIn"    => true,
            "withoutRedirect" => true,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.ipic.com/iPicAPI2/member/login", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // Access is allowed
        if (isset($response->memberId)) {
            $this->State['apikey'] = $this->http->getDefaultHeader('apikey');

            return $this->loginSuccessful();
        }

        $message = $response->errorMessage ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Member email and/or password invalid.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'A verification code was sent to')
                || strstr($message, 'A new verification code was sent to')
            ) {
                $this->State['apikey'] = $this->http->getDefaultHeader('apikey');
                $this->AskQuestion($message, null, "Question");

                return false;
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $captcha = $this->parseReCaptcha();

        if ($captcha == false) {
            return false;
        }

        $this->http->setDefaultHeader('apikey', $this->State['apikey']);

        $data = [
            "emailAddress"                 => $this->AccountFields['Login'],
            "password"                     => $this->AccountFields['Pass'],
            "twoFactorCode"                => $answer,
            "allowTwoFactorAuthentication" => true,
            "reCaptchaToken"               => $captcha,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://legacy-cdn.ipic.com/iPicAPI2/member/login", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();

        return $this->loginSuccessful();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance - IPIC ACCESS POINTS
        $balance = $response->points->balance ?? null;

        if ($balance !== null) {
            $this->SetBalance(floor($balance));
        }
        // Name
        $this->SetProperty('Name', beautifulName($response->fullName ?? null));
        // AccountID: 4461824
        if (empty($this->Properties['Name']) && empty($response->lastName) && !empty($response->firstName)) {
            $this->SetProperty('Name', beautifulName($response->firstName));
        }

        // Member ID
        $this->SetProperty('MemberNumber', $response->memberID ?? null);
        // Redeemable Dollars
        if (isset($response->points->valueInDollars)) {
            $this->SetProperty('RedeemableDollars', "$" . $response->points->valueInDollars);
        }
        // Account Level
        $this->SetProperty('Status', $response->level->name ?? null);
        // Your membership expires
        $memberExpires = $response->memberExpiresUtc ?? null;

        if ($memberExpires) {
            $this->SetProperty('StatusExpiration', date("F d, Y", strtotime($memberExpires)));
        }
        // Member Card
        $this->SetProperty('CardNumber', $response->primaryCardNumber ?? null);
        // Tickets Earned
        $this->SetProperty('TicketsEarned', $response->qualifyingTickets->count ?? null);
        // Tickets until your reach Platinum
        $this->SetProperty('TicketsUntilNextLevel', $response->qualifyingTickets->ticketsToPlatinumLevel ?? null);
        // Member since
        $this->SetProperty('MemberSince', date("Y", strtotime($response->memberCreationUtc)));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Account: 3538634, 4511408
            if (
                empty($response->points)
                && empty($response->qualifyingTickets)
                && !empty($this->Properties['Name'])
                && !empty($this->Properties['MemberNumber'])
                && !empty($this->Properties['Status'])
                && !empty($this->Properties['StatusExpiration'])
                && (!empty($this->Properties['CardNumber']) || $response->primaryCardNumber === '')
                && !empty($this->Properties['MemberSince'])
            ) {
                $this->SetBalance(0);
                $this->SetProperty('TicketsEarned', 0);
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api.ipic.com/iPicAPI2/member", $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->email) && strtolower($response->email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = '6Lc5nL0jAAAAAGIxQXCTB0lbktunN4GOYtAxoJFx';

        if (!$key) {
            return false;
        }

//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
//        $this->recognizer->RecognizeTimeout = 120;
//        $parameters = [
//            "pageurl"   => $this->http->currentUrl(),
//            "proxy"     => $this->http->GetProxy(),
//            "invisible" => 1,
//            "action"    => "Login",
//            "version"   => "enterprise",
//        ];
//
//        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.9,
            "pageAction"   => "Login",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }
}
