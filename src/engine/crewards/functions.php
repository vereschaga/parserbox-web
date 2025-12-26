<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerCrewards extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.cashrewards.com.au/my-rewards';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyAustralia());
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['token'])) {
            return false;
        }

        if ($this->loginSuccessful($this->State['token'])) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $this->http->GetURL("https://www.cashrewards.com.au/account/api/auth/csrf");
        $response = $this->http->JsonLog();
        $csrfToken = $response->csrfToken ?? null;

        if (!$csrfToken) {
            return $this->checkErrors();
        }

        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/x-www-form-urlencoded",
        ];
        $data = [
            "email"       => $this->AccountFields['Login'],
            "password"    => $this->AccountFields['Pass'],
            "callbackUrl" => "/account/token?action=login",
            "redirect"    => "false",
            "csrfToken"   => $csrfToken,
            "json"        => "true",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.cashrewards.com.au/account/api/auth/callback/Credentials?", $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $url = $response->url ?? null;
        $this->logger->debug("[URL]: [$url}");
        parse_str(parse_url($url, PHP_URL_QUERY), $output);
        $this->logger->debug(var_export($output, true), ["pre" => true]);
        $message = $this->http->JsonLog($output['error'] ?? null)->message ?? null;

        if ($this->finalizeAuth($url)) {
            return true;
        }

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'The Cashrewards verification code has been sent to')) {
                $this->AskQuestion($message, null, "Question");

                return false;
            }

            if (
                strstr($url, 'error?error=Invalid%20credentials')
                || strstr($message, 'Incorrect email or password.')
            ) {
                throw new CheckException("Incorrect email or password. Please double check and try again.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'You need to reset your password to continue') {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, 'Unable to login because of security reasons.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    private function finalizeAuth($url)
    {
        $this->logger->notice(__METHOD__);

        if (!$url || !strstr($url, 'token')) {
            return false;
        }
//        $this->http->GetURL($url);
//        $this->http->JsonLog();

        $this->http->GetURL("https://www.cashrewards.com.au/account/api/auth/session");
        $token = $this->http->JsonLog()->user->accessToken ?? null;

        if (!$token) {
            return false;
        }

        $headers = [
            "Authorization" => "Bearer {$token}",
        ];
        $this->http->GetURL("https://www.cashrewards.com.au/api/accounts/v1/member/SetCredentials", $headers);
        $this->http->JsonLog();

        if ($this->loginSuccessful($token)) {
            $this->State['token'] = $token;

            return true;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $otpCode = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $this->http->GetURL("https://www.cashrewards.com.au/account/api/auth/csrf");
        $response = $this->http->JsonLog();
        $csrfToken = $response->csrfToken ?? null;

        if (!$csrfToken) {
            return $this->checkErrors();
        }

        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/x-www-form-urlencoded",
        ];
        $data = [
            "email"                 => $this->AccountFields['Login'],
            "password"              => $this->AccountFields['Pass'],
            "otpCode"               => $otpCode,
            "callbackUrl"           => "/account/token?action=login",
            "redirect"              => "false",
            "respondingToChallenge" => "SMS_MFA",
            "csrfToken"             => $csrfToken,
            "json"                  => "true",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.cashrewards.com.au/account/api/auth/callback/Credentials?", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $url = $response->url ?? null;
        $this->logger->debug("[URL]: [$url}");
        parse_str(parse_url($url, PHP_URL_QUERY), $output);
        $this->logger->debug(var_export($output, true), ["pre" => true]);
        $message = $this->http->JsonLog($output['error'] ?? null)->message ?? null;

        if ($this->finalizeAuth($url)) {
            return true;
        }

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Unable to login because of security reasons. Please contact with Cashrewards Member Service.') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance - Available Rewards
        $this->SetBalance($response->availableBalance);
        // Rewards Balance
        $this->SetProperty("RewardsBalance", "$" . $response->balance);
        //Lifetime Rewards
        $this->SetProperty("LifetimeRewards", "$" . $response->lifetimeRewards);
        // Name
        $this->SetProperty("Name", beautifulName($response->firstName . " " . $response->lastName));
    }

    private function loginSuccessful($token)
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"        => "application/json, text/plain, */*",
            "Content-Type"  => "application/json",
            "Authorization" => "Bearer {$token}",
            "Origin"        => "https://www.cashrewards.com.au",
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://member-api.cashrewards.com.au/api/member", $headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if ($email && strstr(strtolower($this->AccountFields['Login']), strtolower(str_replace('*', '', $email)))) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "We are currently doing some upgrades.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
