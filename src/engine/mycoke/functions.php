<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerMycoke extends TAccountChecker
{
    use ProxyList;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerMycokeSelenium.php";

        return new TAccountCheckerMycokeSelenium();
    }

    protected $accessToken = null;
    protected $apiKey = "KLDfgNvkHWagw64CPZFXj2hRBVcltwWm1liFw6Kf";

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'])) {
            return parent::FormatBalance($fields, $properties);
        }

        $balance = $fields['Balance'];

        if ($balance == 1) {
            return '1 reward';
        } else {
            return sprintf('%d rewards', $balance);
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->FilterHTML = false;
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
//        $this->http->GetURL('https://us.coca-cola.com/');
        $this->http->GetURL('https://api.us.coca-cola.com/api/v5/sessionUUID', ["x-access-token" => "99cc16db6d42a0b8def361585640b60d253b16d1"]);
        $response = $this->http->JsonLog();

        if (!isset($response->sessionUUID)) {
            return $this->checkErrors();
        }

        $this->http->setDefaultHeader("session-uuid", $response->sessionUUID);
        $clientId = "9519ac13-06df-4a5a-ba3a-af8664d426bd";

        $this->http->GetURL("https://login.us.coca-cola.com/a62739e6-ccaa-4a4a-831c-416177dae1ea/b2c_1a_accountmerge_susi_web/oauth2/v2.0/authorize?client_id={$clientId}&scope=https%3A%2F%2Fccnaprod.onmicrosoft.com%2Fccna-b2c-authorizer-service%2Foauth.coke%20openid%20profile%20offline_access&redirect_uri=https%3A%2F%2Fus.coca-cola.com%2Ftoken_exchange&client-request-id=486beafb-083d-46ec-af44-98f677a8960c&response_mode=fragment&response_type=code&x-client-SKU=msal.js.browser&x-client-VER=2.19.0&x-client-OS=&x-client-CPU=&client_info=1&code_challenge=yZRBabig1mwyd5oZ-BRmPO4AJihEplDWlm9_665DHxU&code_challenge_method=S256&nonce=b2945b3f-da35-467a-9fdb-858d80aa54a1&state=eyJpZCI6IjI0Nzg4MGZkLTJhNTYtNDU4Mi04OGU5LWI1NjhmNGI0M2M3NSIsIm1ldGEiOnsiaW50ZXJhY3Rpb25UeXBlIjoicmVkaXJlY3QifX0%3D&isWebBrowser=true&kmsi_text_label=Keep%20me%20signed%20in&guest_uri=https://us.coca-cola.com/token_exchange?continue_as_guest=true&analyticsSessionUUID={$response->sessionUUID}&analyticsProfileId=undefined");

        $transId = $this->http->FindPreg("/\"transId\":\s*\"([^\"]+)/");
        $tenant = $this->http->FindPreg("/\"tenant\":\s*\"([^\"]+)/");
        $policy = $this->http->FindPreg("/\"policy\":\s*\"([^\"]+)/");

        if (!$transId || !$tenant || !$policy) {
            return $this->checkErrors();
        }

        $this->http->FormURL = "https://login.us.coca-cola.com{$tenant}/SelfAsserted?tx={$transId}&p={$policy}";
        $this->http->SetInputValue('signInName', strtolower($this->AccountFields['Login']));
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('request_type', 'RESPONSE');

        $csrf = $this->http->FindPreg("/\"csrf\":\s*\"([^\"]+)/");

        if (!$csrf) {
            return $this->checkErrors();
        }
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-CSRF-TOKEN"     => $csrf,
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm($headers) && $this->http->Response['code'] != 400) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $message = $response->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                in_array($message, [
                    'We don’t recognize your email address or password.',
                    'Sorry, this email is not valid',
                ])
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        $param = [];
        $param['rememberMe'] = true;
        $param['csrf_token'] = $csrf;
        $param['p'] = $policy;
        $param['tx'] = $transId;
        $param['diags'] = '{"pageViewId":"08d6157c-bd85-4e47-b55b-17842dcd9c84","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1618829748,"acD":3},{"ac":"T021 - URL:https://template.ccnag.com/signinTemplate.html","acST":1618829748,"acD":1078},{"ac":"T019","acST":1618829749,"acD":5},{"ac":"T004","acST":1618829749,"acD":15},{"ac":"T003","acST":1618829749,"acD":2},{"ac":"T035","acST":1618829749,"acD":0},{"ac":"T030Online","acST":1618829749,"acD":0},{"ac":"T002","acST":1618829758,"acD":0},{"ac":"T018T010","acST":1618829757,"acD":1271}]}';
        $this->http->GetURL("https://login.us.coca-cola.com{$tenant}/api/CombinedSigninAndSignup/confirmed?958003" . http_build_query($param));

        $code = $this->http->FindPreg("/code=(.+?)$/", false, $this->http->currentUrl());

        if (!$code) {
            $this->logger->notice("code not found");

            return $this->checkErrors();
        }
        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br",
            "content-type"    => "application/x-www-form-urlencoded;charset=utf-8",
        ];

        $data = [
            "client_id"         => $clientId,
            "redirect_uri"      => "https://account.us.coca-cola.com/authenticate.html",
            "scope"             => "https://ccnaprod.onmicrosoft.com/oauth-service/oauth.coke openid profile",
            "code"              => $code,
            "code_verifier"     => "i-KKdnwwLh6hQNFKqhnW8ecgJ-B4CNQLf7ba4hh0h58", // works in parse with code_challenge
            "grant_type"        => "authorization_code",
            "client_info"       => "1",
            "client-request-id" => "e7a194d6-adc1-4af9-b905-0ef896ec5b94",
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://login.us.coca-cola.com{$tenant}/oauth2/v2.0/token", $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //#  My Coke Rewards is temporarily unavailable
        if ($message = $this->http->FindPreg("/My Coke Rewards is temporarily\s+unavailable./ims")) {
            throw new CheckException("My Coke Rewards is temporarily unavailable. We are sorry for the inconvenience. Please come back soon!", ACCOUNT_PROVIDER_ERROR);
        }

        if ($e = $this->http->FindPreg('#Looks like your privacy or security settings are[^<]+#i')) {
            $this->http->Log($e, LOG_LEVEL_ERROR);
        }
        // Site is undergoing scheduled maintenance
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'This site is undergoing scheduled maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 404 - Page Not Found
        if ($this->http->FindSingleNode("//h1[contains(text(), '404 - Page Not Found')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")
            || $this->http->FindPreg("/The proxy server received an invalid\s*response from an upstream/ims")
            // An error occurred while processing your request.
            || $this->http->FindPreg("/An error occurred while processing your request\./ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // We officially bid farewell to My Coke Rewards on June 30th, 2017.
        $message = 'We officially bid farewell to My Coke Rewards on June 30th, 2017. Thank you for all the support—we had fun with you over the years.';

        if ($this->http->FindPreg(sprintf('/%s/', 'We officially bid farewell to'))) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function ArrayVal($ar, $indices)
    {
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return null;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $accessToken = $response->access_token ?? null;

        // Access is allowed
        if ($accessToken) {
            $this->auth($accessToken);
            $result = $this->http->JsonLog();
            $accessToken = $this->http->FindPreg('/"accesstoken":"(.+?)"/'); // different one

            if ($accessToken) {
                $this->http->setDefaultHeader("authorization", $accessToken);
                $this->http->setDefaultHeader("x-api-key", $this->apiKey);

                return true;
            }

            $errorCode = $result->errorCode ?? null;
            $errorDescription = $result->errorDescription ?? null;
            // There was an error! Please try again later.
            if ($errorCode == 'GEN_INVALID_ARGUMENT' && $errorDescription == "THE INSERT VALUE OF FOREIGN KEY IS INVALID FOR MEMBER_ID") {
                throw new CheckException("There was an error! Please try again later.", ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($accessToken)

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $response = $this->http->JsonLog(null, 0);
        $this->SetProperty('Name', beautifulName($response->givenName ?? null));
        // Balance
        $headers = [
            "Accept"          => "application/json, text/plain, */*",
            "Accept-Language" => "en-US,en;q=0.5",
            "Accept-Encoding" => "gzip, deflate, br",
            "Referer"         => "https://myaccount.us.coca-cola.com/",
            "content-type"    => "application/json",
            "Origin"          => "https://myaccount.us.coca-cola.com",
        ];
        $this->http->GetURL('https://prod.apig.ccnag.com/rewards/v1/activeRewards', $headers);

        if ($this->http->FindPreg('/^\[\]$/')) {
            $this->logger->notice("No rewards found on the website");
            $this->SetBalanceNA();

            return;
        }

        // SubAccounts
        $this->parseSubAccounts();
    }

    protected function auth($accessToken)
    {
        $this->logger->notice(__METHOD__);

        $headers = [
            "Accept"         => "application/json, text/plain, */*",
            "x-access-token" => "Bearer {$accessToken}",
            "Origin"         => "https://us.coca-cola.com",
            "Referer"        => "https://us.coca-cola.com/",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.us.coca-cola.com/api/v5/auth", '{"action":"traditionalSignin","source":"us.coca-cola.com","authProvider":"email"}', $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    protected function parseSubAccounts()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            'Accept'          => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept-Language' => 'en-US,en;q=0.8,ru;q=0.6',
            'Connection'      => 'keep-alive',
            'DNT'             => '1',
            'Host'            => 'api.us.coca-cola.com',
            'If-None-Match'   => 'W/"GyUhpNn603a0b+b4IgPZ0g=="',
            'Origin'          => 'https://us.coca-cola.com',
            'Referer'         => 'https://us.coca-cola.com/rewards/',
            //            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36',
        ];
        $this->http->GetURL('https://api.us.coca-cola.com/api/v5/unclaimedItems?catalogId=All', $headers);
        $data = $this->http->JsonLog(null, 3, true);
        $awards = ArrayVal($data, 'campaignAwards', []);

        foreach ($awards as $award) {
            $item = ArrayVal($award, 'item');

            if (!$item) {
                $this->logger->info('Empty award, skipping');

                continue;
            }

            $name = ArrayVal($item, 'name');
            $exp = strtotime(ArrayVal($item, 'endDate'));

            if ($name && $exp) {
                $subAccounts[] = [
                    'Code'           => "mycoke_{$name}_{$exp}",
                    'DisplayName'    => $name,
                    'ExpirationDate' => $exp,
                ];
            }
        }

        if (isset($subAccounts)) {
            // Set Sub Accounts
            $this->SetProperty("CombineSubAccounts", false);
            $this->logger->info("Total subAccounts: " . count($subAccounts));
            // Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }
    }
}
