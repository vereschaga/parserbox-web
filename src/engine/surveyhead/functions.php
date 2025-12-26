<?php

class TAccountCheckerSurveyhead extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://flare.ipoll.com/api/1/respondent?_cache=';

    private $headers = [
        'Accept'        => 'application/json; charset=utf-8',
        'Content-Type'  => 'text/plain',
        'panelDomainId' => '23331',
        'Origin'        => 'https://www.ipoll.com',
    ];

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.ipoll.com/';
        $arg['SuccessURL'] = 'https://www.ipoll.com/en/secured/my-account';

        return $arg;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['TotalEarned']) && preg_match('/^€/ims', $properties['TotalEarned'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
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
        $this->http->GetURL('https://www.ipoll.com/login');

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
        $this->http->PostURL('https://flare.ipoll.com/api/1/respondent/login?_cache=' . date("UB"), json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

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
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", beautifulName($response->response->firstName . " " . $response->response->lastName));

        $panelId = $response->response->panelId ?? null;

        if (!$panelId) {
            return;
        }

        $this->http->GetURL('https://flare.ipoll.com/api/1/respondent/balance?_cache=' . date("UB"));
        $response = $this->http->JsonLog();
        // Balance - points
        $this->SetBalance(($response->response->amount / 100));

        $this->http->GetURL('https://flare.ipoll.com/api/1/badge/respondent?_cache=' . date("UB"));
        $response = $this->http->JsonLog(null, 1);

        if (isset($response->response)) {
            foreach ($response->response as $row) {
                if (!isset($row->parentId, $row->priority) && isset($row->granted, $row->name) && $row->granted
                    && (!isset($priority) || $row->priority < $priority)) {
                    $priority = $row->priority;
                    // Level
                    $this->SetProperty("Level", $row->name);
                }
            }// foreach ($response->response as $row)

            if (!isset($this->Properties['Level'])) {
                $this->SetProperty("Level", "Bronze");
            }
        }// if (isset($response->response))
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();

        if ($response->response->emailAddress ?? null) {
            return true;
        }

        return false;
    }
}
