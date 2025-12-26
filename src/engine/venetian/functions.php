<?php

class TAccountCheckerVenetian extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.venetianlasvegas.com/rewards/dashboard.html';

    private $apikey = '3_aumVsUFFm-b2cfQkVcBABnlAMIKSdSobZ10osfTk31M3vljNja1ooJJ97TWfTXZa';
    private $sdk = "js_latest";
    private $context = 'R4096243960';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.venetianlasvegas.com/rewards/login.html");

        $this->http->GetURL("https://grazielogin.venetianlasvegas.com/accounts.getScreenSets?screenSetIDs=gg-login&include=html%2Ccss%2Cjavascript%2Ctranslations%2C&lang=en&APIKey={$this->apikey}&source=showScreenSet&sdk={$this->sdk}&pageURL=https%3A%2F%2Fwww.venetianlasvegas.com%2Frewards%2Flogin.html&format=jsonp&callback=gigya.callback&context={$this->context}");

        if (!$this->http->FindPreg("/form class=.\"gigya-login-form/")) {
            return $this->checkErrors();
        }

        // for cookies
        $this->http->GetURL("https://grazielogin.venetianlasvegas.com/accounts.webSdkBootstrap?apiKey={$this->apikey}&pageURL=https%3A%2F%2Fwww.venetianlasvegas.com%2Frewards%2Flogin.html&format=jsonp&callback=gigya.callback&context={$this->context}");
        $this->http->JsonLog($this->http->FindPreg("/gigya\.callback\(([\w\W]+)\);\s*$/ims"), 0);

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://grazielogin.venetianlasvegas.com/accounts.login?context={$this->context}&saveResponseID={$this->context}", [
            'loginID'           => $this->AccountFields['Login'],
            'password'          => $this->AccountFields['Pass'],
            'sessionExpiration' => '0',
            'targetEnv'         => 'jssdk',
            'include'           => 'profile,data,emails,subscriptions,preferences,',
            'includeUserInfo'   => 'true',
            'loginMode'         => 'standard',
            'lang'              => 'en',
            'APIKey'            => $this->apikey,
            'source'            => 'showScreenSet',
            'sdk'               => $this->sdk,
            'authMode'          => 'cookie',
            'pageURL'           => 'https://www.venetianlasvegas.com/rewards/login.html',
            'format'            => 'jsonp',
            'callback'          => 'gigya.callback',
            'context'           => $this->context,
            'utf8'              => '✓', //&#x2713;
        ]);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($this->http->FindSingleNode("//div[contains(text(), 'We are currently undergoing maintenance.')]")) {
            throw new CheckException("We are currently undergoing maintenance.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($this->http->FindSingleNode("//img[contains(@src, 'system_down_error')]/@src")) {
            throw new CheckException("System temporarily down for maintenance", ACCOUNT_PROVIDER_ERROR);
        }
        // This site is currently under construction
        if ($message = $this->http->FindSingleNode("
                //h1[contains(text(), 'This site is currently under construction')]
                | //h3[contains(text(), 'Our Website Is Currently Being Updated')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'We are currently under construction')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@id, 'spanGeneralError')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            || $this->http->FindPreg("/(Failed connect to clubgrazie\.venetianlasvegas\.com\:443)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $data = http_build_query([
            'APIKey'         => $this->apikey,
            'callback'       => "gigya.callback",
            'context'        => $this->context,
            'format'         => 'jsonp',
            'noAuth'         => 'true',
            'saveResponseID' => $this->context,
            'sdk'            => $this->sdk,
        ]);
        $this->http->GetURL("https://grazielogin.venetianlasvegas.com/socialize.getSavedResponse?{$data}");
        $response = $this->http->JsonLog($this->http->FindPreg("/gigya\.callback\(([\w\W]+)\);\s*$/ims"), 3);

        if (isset($response->profile->email, $response->sessionInfo->login_token, $response->UID, $response->data->patronID)) {
            $patronID = $response->data->patronID;
            $profile = "{\"firstName\":\"{$response->profile->firstName}\",\"lastName\":\"{$response->profile->lastName}\",\"zip\":\"{$response->profile->zip}\",\"birthYear\":{$response->profile->birthYear},\"birthMonth\":null,\"birthDay\":null}";
            $login_token = $response->sessionInfo->login_token;

            $this->http->RetryCount = 0;
            $this->http->setCookie("glt_{$this->apikey}", $login_token, '.venetianlasvegas.com');
//            $this->http->setCookie("glt_{$this->apikey}", $login_token, '.www.venetianlasvegas.com');
            $data = [
                "timestamp" => time(),
                "uid"       => $response->UID,
                "data"      => [
                    "patronID" => $patronID,
                ],
                "profile"   => [
                    "firstName" => $response->profile->firstName,
                    "lastName"  => $response->profile->lastName,
                ],
            ];

            $this->logger->notice("get first token");
            $headers = [
                "Accept"          => "*/*",
                "Accept-Encoding" => "gzip, deflate, br",
            ];
            $query = http_build_query([
                'fields'      => "data.patronID",
                'expiration'  => 60,
                'APIKey'      => $this->apikey,
                'sdk'         => $this->sdk,
                'login_token' => $login_token,
                'authMode'    => 'cookie',
                'pageURL'     => 'https://www.venetianlasvegas.com/rewards/login.html',
                'format'      => 'jsonp',
                'callback'    => "gigya.callback",
                'context'     => $this->context,
            ]);
            $this->http->GetURL("https://grazielogin.venetianlasvegas.com/accounts.getJWT?" . $query, $headers);
            $response = $this->http->JsonLog($this->http->FindPreg("/gigya\.callback\(([\w\W]+)\);\s*$/ims"));

            if (!isset($response->id_token)) {
                return false;
            }
//            $headers = [
//                "Accept"       => "application/json, text/plain, */*",
//                "Content-Type" => "application/json;charset=utf-8",
//                "Id-Token"     => $response->id_token,
//                "Origin"       => "https://www.venetianlasvegas.com",
//                "Referer"      => "https://www.venetianlasvegas.com/rewards/login.html"
//            ];
//            $this->http->PostURL("https://www.venetianlasvegas.com/services/patron/get", json_encode(["patronId" => $patronID]), $headers);//todo: not working
//            $this->http->JsonLog();
//            if ($this->http->Response['code'] == 403) {
//                return false;
//            }

            $this->http->PostURL("https://grazielogin.venetianlasvegas.com/accounts.setAccountInfo?context={$this->context}&&saveResponseID={$this->context}", [
                'profile'     => urlencode(json_encode(json_decode($profile))),
                'data'        => urlencode('{"middleName":"","initialLogin":false,"isVerifiedAcsc":true,"sendUpdateEmail":false,"creditPatron":false,"excludedPatron":false}'),
                'lang'        => 'en',
                'APIKey'      => $this->apikey,
                'sdk'         => $this->sdk,
                'login_token' => $login_token,
                'authMode'    => 'cookie',
                'pageURL'     => 'https://www.venetianlasvegas.com/rewards/login.html',
                'format'      => 'jsonp',
                'callback'    => 'gigya.callback',
                'context'     => $this->context,
                'utf8'        => '✓', //&#x2713;
            ]);
            $this->http->GetURL("https://grazielogin.venetianlasvegas.com/socialize.getSavedResponse?APIKey={$this->apikey}&saveResponseID={$this->context}&pageURL=https%3A%2F%2Fwww.venetianlasvegas.com%2Frewards%2Flogin.html&noAuth=true&sdk={$this->sdk}&format=jsonp&callback=gigya.callback&context={$this->context}");
            $this->http->JsonLog($this->http->FindPreg("/gigya\.callback\(([\w\W]+)\);\s*$/ims"));

            $this->logger->notice("get second token");
            $headers = [
                "Accept"          => "*/*",
                "Accept-Encoding" => "gzip, deflate, br",
            ];
            $query = http_build_query([
                'fields'      => "data.patronID",
                'expiration'  => 60,
                'APIKey'      => $this->apikey,
                'sdk'         => $this->sdk,
                'login_token' => $login_token,
                'authMode'    => 'cookie',
                'pageURL'     => 'https://www.venetianlasvegas.com/rewards/login.html',
                'format'      => 'jsonp',
                'callback'    => "gigya.callback",
                'context'     => $this->context,
            ]);
            $this->http->GetURL("https://grazielogin.venetianlasvegas.com/accounts.getJWT?" . $query, $headers);
            $response = $this->http->JsonLog($this->http->FindPreg("/gigya\.callback\(([\w\W]+)\);\s*$/ims"));

            if (!isset($response->id_token)) {
                return false;
            }

            $this->logger->notice("create cookie");
            $headers = [
                "Accept"       => "application/json, text/plain, */*",
                "Content-Type" => "application/json;charset=utf-8",
                "Id-Token"     => $response->id_token,
                "Origin"       => "https://www.venetianlasvegas.com",
                "Referer"      => "https://www.venetianlasvegas.com/rewards/login.html",
            ];
            $this->http->PostURL("https://www.venetianlasvegas.com/services/profile/authentication.create", json_encode($data), $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (!isset($response[0]->token)) {
                return false;
            }

            return true;
        }
        // Invalid credentials
        $errorDetails = $response->errorDetails ?? null;

        if ($errorDetails) {
            $this->logger->error("[Error]: {$errorDetails}");

            if ($errorDetails == 'invalid loginID or password') {
                throw new CheckException('Invalid login or password', ACCOUNT_INVALID_PASSWORD);
            }

            if ($errorDetails == 'Old Password Used') {
                throw new CheckException('It seems like you\'re trying to log in with a password that was changed. If you don\'t remember the new one, reset your password.', ACCOUNT_INVALID_PASSWORD);
            }

            if ($errorDetails == 'Account temporarily locked out') {
                throw new CheckException('For your privacy, your account has been locked temporarily. Please try again later, or call the Grazie desk at 1-877-247-2943.', ACCOUNT_LOCKOUT);
            }

            if ($errorDetails == 'Registration was not finalized') {
                throw new CheckException('There appears to be an issue with your request. Please contact Grazie Rewards Services at: 1-877-247-2943', ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//p[contains(@class, "navigation__greeting")]', null, true, "/Hi,\s*([^!.]+)/ims")));
        // Venetian Rewards Number
        $this->SetProperty("Number", $this->http->FindSingleNode('//p[contains(text(), "Rewards Number")]/following-sibling::p'));
        // Current Tier
        $this->SetProperty("Level", $this->http->FindSingleNode('(//span[contains(text(), "Current Tier")]/following-sibling::h4)[1]'));
        // Status Expiration
        $this->SetProperty("StatusExpiration", beautifulName($this->http->FindSingleNode('//p[contains(text(), "Tier level through")]', null, true, "/through\s*(.+)/")));
        // Tier Points Earned
        $this->SetProperty("TierPoints", $this->http->FindSingleNode('//h5[contains(text(), "Tier Points Earned")]', null, true, "/(.+)\s+Tier/"));
        // Points to Next Level
        $this->SetProperty("ToNextLevel", $this->http->FindSingleNode('//p/strong[contains(text(), " to unlock") and contains(text(), "tier level")]', null, true, "/(.+) point/"));
        // Grazie Gift Points
        $this->SetProperty("GrazieGift", $this->http->FindSingleNode('//h6[contains(text(), "Gifts Points")]', null, true, "/(.+)\s+\w+\s+Gifts/"));
        // Balance - Rewards Points
        $this->SetBalance($this->http->FindSingleNode('//h6[contains(text(), "Rewards Points")]', null, true, "/(.+)\s+Total/ims"));
        // Rewards Value
        $this->SetProperty("RewardsValue", $this->http->FindSingleNode('//p[contains(text(), "Rewards Value")]', null, true, "/(.+)\s+Reward/"));

        $exp = $this->http->FindSingleNode('//div[h6[contains(text(), "Rewards Points")]]/following-sibling::p[contains(text(), "Expires")]', null, true, "/Expires\s*(.+)/");

        if ($exp) {
            $this->SetExpirationDate(strtotime($exp));
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "We’re polishing up your Venetian Rewards experience.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//a[contains(@class, "logout")]')
        ) {
            return true;
        }

        return false;
    }
}
