<?php

class TAccountCheckerOpinionmiles extends TAccountChecker
{
    /* parser like as airmilessurvey, perspectives, valuedopinions, opinionmiles, erewards (com.au) */

    private const REWARDS_PAGE_URL = 'https://flare.opinionmilesclub.com/api/1/respondent?_cache=';

    private $headers = [
        'Accept'        => 'application/json; charset=utf-8',
        'Content-Type'  => 'text/plain',
        'panelDomainId' => '22781',
        'Origin'        => 'https://www.opinionmilesclub.com',
    ];

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
        $this->http->GetURL('https://www.opinionmilesclub.com/login');

        $this->http->RetryCount = 0;

        $data = [
            "AuthFlow"       => "USER_PASSWORD_AUTH",
            "ClientId"       => "6s05i6qrug1mtcfksddbrmt2o9",
            "AuthParameters" => [
                "USERNAME" => $this->AccountFields['Login'],
                "PASSWORD" => $this->AccountFields['Pass'],
            ],
            "ClientMetadata" => [
                "brand_id" => "54",
                "panel_id" => "2278",
            ],
        ];

        $headers = [
            'content-type' => 'application/x-amz-json-1.1',
            'Accept'       => '*/*',
            'x-amz-target' => 'AWSCognitoIdentityProviderService.InitiateAuth',
        ];

        $this->http->PostURL('https://cognito-idp.us-east-1.amazonaws.com/', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(); // the site sends a jwt token, a session cookie is encoded in it

        if (isset($response->__type) && isset($response->message)) {
            if ($response->__type == 'PasswordResetRequiredException') {
                $this->throwProfileUpdateMessageException();
            }

            if ($response->__type == 'NotAuthorizedException') {
                throw new CheckException($response->message, ACCOUNT_INVALID_PASSWORD);
            }
        }

        if (!isset($response->AuthenticationResult->IdToken)) {
            return $this->checkErrors();
        }

        $payload = $this->jwtExtractPayload($response->AuthenticationResult->IdToken);

        if (isset($payload->corona_session)) {
            $this->http->setCookie("corona_session", $payload->corona_session, '.opinionmilesclub.com');
            $this->http->GetURL(self::REWARDS_PAGE_URL . date('UB'), $this->headers, 20);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $response = $this->http->JsonLog(null, 0);
        $errorCode = $response->errors[0]->errorCode ?? null;

        if ($errorCode) {
            $this->sendNotification('refs #23685 - opinionmails. Need to check login flow // IZ');

            return false;
        }// if ($errorCode)

        return $this->checkErrors();
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Parse()
    {
        /*
        if (!stristr($this->http->currentUrl(), self::REWARDS_PAGE_URL)) {
            $this->http->GetURL(self::REWARDS_PAGE_URL.date('UB'), $this->headers);
        }
        */
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", beautifulName($response->response->firstName . " " . $response->response->lastName));

        $panelId = $response->response->panelId ?? null;

        if (!$panelId) {
            return;
        }

        $this->http->GetURL('https://flare.opinionmilesclub.com/api/1/respondent/balance?_cache=' . date("UB"));
        $response = $this->http->JsonLog();
        // Balance - Lifetime Earnings with Opinion Miles Club ... total miles collected to date!
        $this->SetBalance($response->response->debits ?? null);

        $this->http->GetURL("https://flare.opinionmilesclub.com/api/1/form/panel/{$panelId}/blueprint/nectarCanvass2/locale/en_US/type/account_myInformation?_cache=" . date("UB"), $this->headers);
        // MileagePlus Number
        $this->SetProperty("Number", $this->http->FindPreg("/MileagePlus Number is already in use\"},\"defaultValue\":\"([^\"]+)/"));

        $this->http->GetURL('https://flare.opinionmilesclub.com/api/1/badge/respondent?_cache=' . date("UB"));
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

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();

        if ($response->response->emailAddress ?? null) {
            return true;
        }

        return false;
    }

    private function convertBase64UrlToBase64(string $input): string
    {
        $remainder = \strlen($input) % 4;

        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= \str_repeat('=', $padlen);
        }

        return \strtr($input, '-_', '+/');
    }

    private function jwtExtractPayload(string $jwt)
    {
        $this->logger->debug('DECODING JWT: ' . $jwt);
        $bodyb64 = explode('.', $jwt)[1];
        $this->logger->debug('JWT BODY: ' . $bodyb64);
        $bodyb64Prepared = $this->convertBase64UrlToBase64($bodyb64);
        $this->logger->debug('JWT BODY B64 PREPARED: ' . $bodyb64Prepared);
        $payloadRaw = base64_decode($bodyb64Prepared);
        $this->logger->debug('JWT PAYLOAD RAW: ' . $bodyb64Prepared);

        return $this->http->JsonLog($payloadRaw);
    }
}
