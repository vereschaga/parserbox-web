<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerValuedopinions extends TAccountChecker
{
    use ProxyList;

    public $regionOptions = [
        "USA" => "USA",
        "UK"  => "United Kingdom",
    ];

    /* parser like as airmilessurvey, perspectives, valuedopinions, opinionmiles, erewards (com.au) */

    private $domain = 'com';

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public static function FormatBalance($fields, $properties)
    {
        switch ($fields['Login2']) {
            case 'UK':
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");

            case 'USA': default:
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        if ($this->AccountFields['Login2'] == "UK") {
            $arg["RedirectURL"] = 'https://www.valuedopinions.co.uk/login';
        } else {
            $arg["RedirectURL"] = 'https://www.valuedopinions.com/login';
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        switch ($this->AccountFields['Login2']) {
            case "UK":
                $this->http->setHttp2(true);
                $this->domain = 'co.uk';
                $this->setProxyGoProxies(null, 'uk');
                $this->http->setDefaultHeader("User-Agent", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/604.4.7 (KHTML, like Gecko) Version/11.0.2 Safari/604.4.7");

                break;

            case "USA": default:
                // 404 workaround
                $this->domain = 'com';

            break;
        }// switch ($this->AccountFields['Login2'])
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://flare.valuedopinions.{$this->domain}/api/1/respondent?_cache=" . date("UB"), [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.valuedopinions.{$this->domain}/login", [], 40);
        $this->http->RetryCount = 2;

        // retries
        if ($this->domain == 'co.uk' && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
            && (
                strstr($this->http->Response['errorMessage'], 'Could not resolve proxy: uk-s')
                || strstr($this->http->Response['errorMessage'], 'Connection timed out after')
                || strstr($this->http->Response['errorMessage'], 'OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to www.valuedopinions.co.uk:443')
            )
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 3);
        }

        $panelId = $this->http->FindPreg("/panelId:\s*(\d+),/");
        $brandId = $this->http->FindPreg("/brandId:\s*(\d+),/");
        $passwordClientId = $this->http->FindPreg("/passwordClientId:\s*\"([^\"]+)/");

        if (!$this->http->FindSingleNode("//div[contains(@class, 'loginForm')]/@class") || !$panelId || !$brandId || !$passwordClientId) {
            return $this->checkErrors();
        }

//        if ($this->domain == 'com') {
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
            "Referer"               => "https://www.valuedopinions.{$this->domain}/",
            //            "amz-sdk-invocation-id" => "bd97c0be-2a58-49c2-b514-91ff7c0f328d",
            "amz-sdk-request"       => "attempt=1; max=3",
            "content-type"          => "application/x-amz-json-1.1",
            "x-amz-target"          => "AWSCognitoIdentityProviderService.InitiateAuth",
            "x-amz-user-agent"      => "aws-sdk-js/3.388.0 ua/2.0 os/macOS#10.15 lang/js md/browser#Firefox_116.0 api/cognito-identity-provider#3.388.0",
            "Origin"                => "https://www.valuedopinions.{$this->domain}",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://cognito-idp.us-east-1.amazonaws.com/", json_encode($data), $headers);
        $this->http->RetryCount = 1;
//        } else {
//            $data = [
//                "username"  => $this->AccountFields['Login'],
//                "password"  => $this->AccountFields['Pass'],
//                "panelId"   => intval($panelId),
//                "keepLogin" => true,
//            ];
//            $this->http->PostURL("https://flare.valuedopinions.{$this->domain}/api/1/respondent/login?_cache=" . time() . date("B"), json_encode($data));
//        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Valued Opinions is temporarily unavailable due to maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The service you are trying to access is temporarily unavailable
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The service you are trying to access is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The page you have requested was not found on the website
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The page you have requested was not found on the website')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, 3, true);

//        if ($this->domain == 'com') {
        $jsResponse = ArrayVal($response, 'AuthenticationResult');
        $IdToken = ArrayVal($jsResponse, 'IdToken');
        $str = base64_decode(explode('.', $IdToken)[1] ?? null);
        $this->logger->debug($str);
        $sessionId = $this->http->FindPreg('/"corona_session":"(.+?)"/', false, $str);
//        } else {
//        $jsResponse = ArrayVal($response, 'response');
//        $sessionId = ArrayVal($jsResponse, 'sessionId');
//        }

        if ($sessionId) {
            $this->http->setCookie("corona_session", $sessionId, '.valuedopinions.' . $this->domain);
            $this->http->GetURL("https://flare.valuedopinions.{$this->domain}/api/1/respondent?_cache=" . date("UB"));

            return $this->loginSuccessful();
        }// if (isset($response->response))
        // Incorrect login. Please try again.
        if ($this->http->FindPreg("/\{\"errors\":\[\{\"errorCode\":\"error_invalidCredentials\"\}\],\"statusCode\":400\}/")) {
            throw new CheckException("Incorrect login. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        $message = ArrayVal($response, 'message');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Incorrect username or password.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'PreTokenGeneration failed with error error_invalidCredentials.') {
                throw new CheckException("Incorrect login. Please try again", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Password reset required for user due to security reasons') {
                throw new CheckException('Valued Opinions website is asking you to reset your password, until you do so we would not be able to retrieve your account information.', ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $response = $this->http->JsonLog(null, 0, true);
        $this->SetProperty("Name", beautifulName(ArrayVal($response['response'], 'firstName') . " " . ArrayVal($response['response'], 'lastName')));

        $this->http->GetURL("https://flare.valuedopinions.{$this->domain}/api/1/respondent/balance?_cache=" . date("UB"));
        $response = $this->http->JsonLog(null, 3, true);
        // Balance
        $this->SetBalance(ArrayVal($response['response'], 'amount'));

        $this->http->GetURL("https://flare.valuedopinions.{$this->domain}/api/1/badge/respondent?_cache=" . date("UB"));
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

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions))) {
            $region = 'USA';
        }

        return $region;
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
