<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerGassistant extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $data = [
        "_ApplicationId"  => "RkhLEyTZT4NTJ5PrQTQuxXqNhmetgv1KMMCltHCR",
        "_ClientVersion"  => "js2.11.0",
        "_InstallationId" => "8a533a1a-9ffc-4d26-abc7-04d53633d71b",
        "_JavaScriptKey"  => "TQ12gSoYgfEiJHLQRZiPxCSmmfDyKzxCc2BxSZls",
        "_method"         => "GET",
    ];

    private $headers = [
        "Accept"          => "*/*",
        "Accept-Encoding" => "gzip, deflate, br",
        "Content-Type"    => "text/plain",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if (empty($this->State['_SessionToken'])) {
            return false;
        }

        $sessionToken = [
            '_SessionToken' => $this->State['_SessionToken'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.givingassistant.org/ps/sessions/me", json_encode($this->data + $sessionToken), $this->headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (empty($response->user->objectId)) {
            return false;
        }

        $this->http->RetryCount = 0;
        $success = $this->loginSuccessful($sessionToken, $response->user->objectId);
        $this->http->RetryCount = 2;

        if ($success) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://givingassistant.org/');

        if (!$this->http->FindSingleNode("//form")) {
            return $this->checkErrors();
        }

        $this->http->GetURL('https://givingassistant.org/script/load_modal.php?m=%23myModal_signin');
        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $pretzels = [
            'username' => "" . $this->AccountFields['Login'] . "||" . $captcha . "",
            'password' => "" . $this->AccountFields['Pass'] . "",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.givingassistant.org/v5/parse/signin", json_encode($this->data + $pretzels), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (!empty($response->sessionToken) && !empty($response->objectId)) {
            $sessionToken = [
                '_SessionToken' => $response->sessionToken,
            ];
            $this->captchaReporting($this->recognizer);
            $this->State['_SessionToken'] = $response->sessionToken;

            return $this->loginSuccessful($sessionToken, $response->objectId);
        }

        $message = $response->error ?? null;

        if ($message) {
            $this->logger->error($message);

            if ($message === "Invalid username/password.") {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        $message = $response->response ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message === "Internal Server Error") {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("There was a problem with the server", ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        if (
            in_array($this->http->Response['code'], [504, 503, 504])
            && $this->http->FindSingleNode("//h1[
                contains(text(), '504 Gateway Time-out')
                or contains(text(), '503 Service Temporarily Unavailable')
                or contains(text(), '504 ERROR')
            ]")
        ) {
            throw new CheckException("There was a problem with the server", ACCOUNT_PROVIDER_ERROR);
        }

        if (
            in_array($this->http->Response['code'], [503])
            && empty($this->http->Response['body'])
        ) {
            throw new CheckException("There was a problem with the server", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog();
        // Name
        $firstName = $response->first_name ?? '';
        $lastName = $response->last_name ?? '';
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));
        // Total cash back earned
        $this->SetProperty('TotalCashBackEarned', "$" . str_replace(".00", "", number_format($response->alltime_earned ?? 0, 2, '.', '')));
        // Giving $... donation
        $this->SetProperty('Donated', "$" . str_replace(".00", "", number_format($response->alltime_donated ?? 0, 2, '.', '')));
        // Pending earnings
        $pending = number_format($response->pending_balance ?? 0, 2, '.', '');

        if ($pending > 0) {
            $this->AddSubAccount([
                "Code"        => "gassistantPending",
                "DisplayName" => "Pending earnings",
                "Balance"     => $pending,
            ]);
        }
        // Giving Assistant member since 2044
        $this->SetProperty('MemberSince', date('Y', strtotime($response->createdAt)));
        // Balance - Payable earnings
        $this->SetBalance($response->current_balance ?? null);
        // Membership
//        if (isset($response->earning_tier)) {// AccountID: 5215843
        // json membership_plan: "locking" in html "Power User"
        // json membership_plan: "default-ndm" in html empty
        // json membership_plan: "givva" in html empty
        if (isset($response->membership_plan)) {
            $tier = $response->membership_plan;
//            $tier = $response->earning_tier;
            switch ($tier) {
//                case 'power':
                case 'power-free':
                case 'legacy':
                case 'locking':
                case 'locking-0599-12':
                    $this->SetProperty('Status', "Power User");

                    break;

                case 'givva':
                case 'default-ndm':
                    $this->SetProperty('Status', "Savvy Shopper");

                    break;

                default:
                    $this->sendNotification("refs #11526: Membership plan has {$response->membership_plan}");
            }
        }
        // AccountID: 5029583
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (
                 !isset($response->current_balance)
                 && !isset($response->alltime_donated)
                 && !isset($response->alltime_earned)
                 && isset($response->objectId)
                 && isset($response->createdAt)
                 && isset($response->email)
                 && isset($response->humanVerified)
                 && isset($response->membership_plan)
                 && isset($response->sessionToken)
                 && (
                     $response->membership_plan == 'locking'
                     || $response->membership_plan == 'legacy'
                 )
            ) {
                $this->SetBalance(0);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//form[contains(@id,"signin-form")]/descendant::div[contains(@class,"g-recaptcha")]/@data-sitekey');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful($sessionToken, $objectId)
    {
        $this->logger->notice(__METHOD__);

        $this->http->PostURL("https://api.givingassistant.org/ps/classes/_User/" . $objectId . "", json_encode($this->data + $sessionToken), $this->headers, 20);
        $response = $this->http->JsonLog();

        if (
            isset($response->sessionToken, $response->username, $response->email)
            && (
                strtolower($response->username) === strtolower($this->AccountFields['Login'])
                || strtolower($response->email) === strtolower($this->AccountFields['Login'])
            )
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
