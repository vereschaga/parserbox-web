<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerZoompanel extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://flare.oneopinion.com/api/1/respondent?_cache=';

    private $headers = [
        'Accept'        => 'application/json; charset=utf-8',
        'Content-Type'  => 'text/plain',
        'panelDomainId' => '96090',
        'Origin'        => 'https://www.oneopinion.com',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL . date('UB'), $this->headers, 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.oneopinion.com/login");

        $panelId = $this->http->FindPreg('/panelId:\s*([^,]+)/ims');
        $panelDomainId = $this->http->FindPreg('/panelDomainId:\s*([^,]+)/ims');

        if (!$this->http->ParseForm("loginForm") || !$panelDomainId || !$panelId) {
            return false;
        }

        $data = [
            'username'  => $this->AccountFields['Login'],
            'password'  => $this->AccountFields['Pass'],
            'panelId'   => intval($panelId),
            'keepLogin' => true,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://flare.oneopinion.com/api/1/respondent/login?_cache=' . date("UB"), json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        // maintenance
        if ($message = $this->http->FindSingleNode("
                //strong[contains(text(), 'We are currently performing scheduled maintenance on our site.')]
                | //p[contains(text(), 'Sorry, but we are down for maintenance until')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->response->sessionId)) {
            $this->http->setCookie("corona_session", $response->response->sessionId);
            $this->http->GetURL(self::REWARDS_PAGE_URL . date('UB'), $this->headers, 20);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $response = $this->http->JsonLog(null, 0);
        $errorCode = $response->errors[0]->errorCode ?? null;

        if ($errorCode) {
            if (
                strstr($errorCode, 'error_invalidCredentials')
            ) {
                throw new CheckException("Incorrect login. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }// if ($errorCode)

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $response = $this->http->JsonLog(null, 0);
        $this->SetProperty("Name", beautifulName($response->response->firstName . " " . $response->response->lastName));

        $this->http->GetURL("https://flare.oneopinion.com/api/1/respondent/balance?_cache=" . date("UB"));
        $response = $this->http->JsonLog();
        // Balance - points
        $this->SetBalance($response->response->amount);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);

        if ($response->response->emailAddress ?? null) {
            return true;
        }

        return false;
    }
}
