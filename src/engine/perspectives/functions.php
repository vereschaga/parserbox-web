<?php

class TAccountCheckerPerspectives extends TAccountChecker
{
    /* parser like as airmilessurvey, perspectives, valuedopinions, opinionmiles, erewards (com.au) */

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.webperspectives.ca/login");

        $panelId = $this->http->FindPreg('/panelId:\s*([^,]+)/ims');
        $panelDomainId = $this->http->FindPreg('/panelDomainId:\s*([^,]+)/ims');
        $brandId = $this->http->FindPreg("/brandId:\s*(\d+),/");
        $passwordClientId = $this->http->FindPreg("/passwordClientId:\s*\"([^\"]+)/");

        if ($this->http->Response['code'] !== 200 || !$panelDomainId || !$panelId || !$brandId || !$passwordClientId) {
            return false;
        }

        $data = [
            "AuthFlow"       => "USER_PASSWORD_AUTH",
            "ClientId"       => $passwordClientId,
            "AuthParameters" => [
                "USERNAME" => $this->AccountFields['Login'],
                "PASSWORD" => $this->AccountFields['Pass'],
            ],
            "ClientMetadata" => [
                "brand_id" => $brandId,
                "panel_id" => $panelId,
            ],
        ];
        $headers = [
            "Accept"                => "*/*",
            "Accept-Language"       => "en-US,en;q=0.5",
            "Accept-Encoding"       => "gzip, deflate, br",
            "Referer"               => "https://www.webperspectives.ca/",
            "amz-sdk-request"       => "attempt=1; max=3",
            "content-type"          => "application/x-amz-json-1.1",
            "x-amz-target"          => "AWSCognitoIdentityProviderService.InitiateAuth",
            "x-amz-user-agent"      => "aws-sdk-js/3.490.0 ua/2.0 os/macOS#10.15.7 lang/js md/browser#Chrome_125.0.0.0 api/cognito-identity-provider#3.490.0",
            "Origin"                => "https://www.webperspectives.ca",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://cognito-idp.us-east-1.amazonaws.com/", json_encode($data), $headers);
        $this->http->RetryCount = 1;
        //		$this->http->SetInputValue('username', $this->AccountFields['Login']);
        //		$this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        // Maintenance
        if ($message = $this->http->FindPreg("/Web Perspectives is temporarily unavailable due to maintenance. Please visit us later\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // login successful
        $response = $this->http->JsonLog(null, 3, true);
        $jsResponse = ArrayVal($response, 'AuthenticationResult');
        $IdToken = ArrayVal($jsResponse, 'IdToken');
        $str = base64_decode(explode('.', $IdToken)[1] ?? null);
        $this->logger->debug($str);
        $sessionId = $this->http->FindPreg('/"corona_session":"(.+?)"/', false, $str);

        if ($sessionId) {
            $this->http->setCookie("corona_session", $sessionId, ".webperspectives.ca");
            // Name
            if (isset($response->response->firstName, $response->response->lastName)) {
                $this->SetProperty("Name", beautifulName($response->response->firstName . " " . $response->response->lastName));
            }

            return true;
        }// if ($sessionId)

        $message = ArrayVal($response, 'message');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Incorrect username or password.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://flare.webperspectives.ca/api/1/respondent/balance?_cache=" . time() . date("B"));
        $response = $this->http->JsonLog(null, 3, true);
        // Balance
        $balance = ArrayVal($response['response'], 'amount', null);

        if (!is_null($balance)) {
            $this->SetBalance(round($balance));
        }

        $this->http->GetURL("https://flare.webperspectives.ca/api/1/badge/respondent?_cache=" . time() . date("B"));
        $response = $this->http->JsonLog();

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
}
