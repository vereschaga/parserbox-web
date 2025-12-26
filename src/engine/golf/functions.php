<?php

// todo: looks like as dicks

class TAccountCheckerGolf extends TAccountChecker
{
    private $headers = [
        'Accept'       => 'application/json, text/plain, */*',
        'Content-Type' => 'application/json',
        'x-apikey'     => 'SgqA9WFTm7EamEfL1EwP3ObfFmau6ctZ',
    ];

    private $client_id = 'j0bk5JdMWGTJ6AANIwL1j7ZmQyFIYi8N';
    private $auth0Client = 'eyJuYW1lIjoiYXV0aDAtc3BhLWpzIiwidmVyc2lvbiI6IjEuMTAuMCJ9';

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerSeleniumGolf.php";

        return new TAccountCheckerSeleniumGolf();
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'golfAvailableRewards')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $login = $this->loginSuccessful();
        $this->http->RetryCount = 2;

        if ($login) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.golfgalaxy.com/MyAccount/AccountSummary');
        $this->http->GetURL("https://sso.golfgalaxy.com/authorize?client_id={$this->client_id}&redirect_uri=https%3A%2F%2Fwww.golfgalaxy.com%2FLogonRedirect&audience=gg-jwts&scope=openid%20profile%20email%20read%3Aidentity%20offline_access&response_type=code&response_mode=query&state=QU5VaWJOTjZLanVIelhROUdKRFdXNGNLcEtDMWJVQzZJc0V0WTltcDZUMw%3D%3D&nonce=eHhSRkVkSFVvaHZwaWpabktlSGxEMnMzaTlCR2FmejBVNklEVU1oUE1TSg%3D%3D&code_challenge=r-UAEAMPqnPRQRBvtVidYfZPed_Io1sXLpvCDnI6rCI&code_challenge_method=S256&auth0Client={$this->auth0Client}");

        $this->http->RetryCount = 0;
        $currentUrl = $this->http->currentUrl();
        $client_id = $this->http->FindPreg("/client=([^&]+)/", false, $currentUrl);
        $state = $this->http->FindPreg("/state=([^&]+)/", false, $currentUrl);
        $scope = $this->http->FindPreg("/scope=([^&]+)/", false, $currentUrl);

        if (!$client_id || !$state || !$scope) {
            return $this->checkErrors();
        }

        $data = [
            "client_id"     => $client_id,
            "redirect_uri"  => "https://www.golfgalaxy.com/LogonRedirect",
            "tenant"        => "gg-athlete",
            "response_type" => "code",
            "scope"         => $scope, // "openid profile email read:identity offline_access"
            "audience"      => "gg-jwts",
            "_csrf"         => $this->http->getCookieByName("_csrf"),
            "state"         => $state,
            "_intstate"     => "deprecated",
            "nonce"         => "dTBTQmNFWGE5WHFSZDZUTnRvcTNZYVo1bjF0TkJURFdwZmgxM29BNG9WeQ==",
            "username"      => $this->AccountFields['Login'],
            "password"      => $this->AccountFields['Pass'],
            "connection"    => "dsg-username-password-auth",
        ];
        $headers = [
            'Accept'          => '*/*',
            'Content-Type'    => 'application/json',
            'Auth0-Client'    => 'eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTIuMiJ9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Origin'          => 'https://auth.airmiles.ca',
        ];
        $this->http->PostURL('https://sso.golfgalaxy.com/usernamepassword/login', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode("(//img[contains(@alt, 'SITE DOWN FOR MAINTENANCE.')]/@alt)[1]")) {
            throw new CheckException("Our site is temporarily unavailable due to scheduled maintenance. We will back shortly. Thank you for your patience.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->http->ParseForm("hiddenform")) {
            $this->http->PostForm();
        }

        $code = $this->http->FindPreg("/\?code=([^&]+)/", false, $this->http->currentUrl());

        if ($code) {
            $data = [
                "grant_type"    => "authorization_code",
                "client_id"     => $this->client_id,
                "code_verifier" => "JF4kVrL5UdmdaPT_75oas~-HYbxKpX~hck8SzmBneLU",
                "code"          => $code,
                "redirect_uri"  => "https://www.golfgalaxy.com",
            ];
            $headers = [
                'Accept'       => '*/*',
                'Content-Type' => 'application/json',
            ];

            $this->http->GetURL("https://sso.golfgalaxy.com/authorize?client_id={$this->client_id}&redirect_uri=https%3A%2F%2Fwww.golfgalaxy.com&audience=gg-jwts&scope=openid%20profile%20email%20read%3Aidentity%20offline_access&response_type=code&response_mode=web_message&state=UEJIYjk0UkVOUzZoeVViZXNHWlJGeG4wc3JnZnRHVFFrNGlXelBSflhaWQ%3D%3D&nonce=b3JUZ1hUZGxxenEuS0ZUQWpCb3hrWVhtRjFoLm1JaWZuQ1MuSklXQ1pYTg%3D%3D&code_challenge=RVzUFyF-1CBAtsXHSaTbJOsIFY5sbxMmPsQRSh_Gzio&code_challenge_method=S256&prompt=none&auth0Client={$this->auth0Client}");

            $this->http->PostURL("https://sso.golfgalaxy.com/oauth/token", json_encode($data), $headers);
            $response = $this->http->JsonLog();

            if (isset($response->access_token)) {
                return true;
            }

            return false;
        }

        $response = $this->http->JsonLog();

        if (isset($response->content->wcToken) && isset($response->content->wcTrustedToken)) {
            return $this->loginSuccessful();
        }

        if ($this->http->FindPreg('/Your password has been reset\. Please retrieve the temporary password from your email and login again/')
            || $this->http->FindPreg('/The current password has expired\.\s+A new password must be specified/')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindPreg('/The specified logon ID or password are not correct\. Verify the information provided and log in again/')) {
            throw new CheckException('To sign in, please enter a valid email address and password.', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg('/The user account is disabled. Contact your site administrator regarding your access/')) {
            throw new CheckException('You have exceeded the number of password attempts. Please Reset Password.', ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->setDefaultHeader('partner_key', 'myaccount_ui');
        $this->http->setDefaultHeader('secret_key', 'ESETXC1V1Zim2jwUL1lw');
        $this->http->GetURL("https://www.golfgalaxy.com/myaccount/services/redirectingservice/pointservice/v2/pointSummary/gg");
        $response = $this->http->JsonLog();

        if (!isset($response->currentPointBalance)) {
            return;
        }
        // Balance - Point Balance
        $this->SetBalance(intval($response->currentPointBalance));
        // Available Rewards
        $this->SetProperty('AvailableRewards', '$' . ($response->rewardAmount ?? null));

        // Available Rewards
        if ($response->rewardAmount > 0) {
            $this->logger->info('Available Rewards', ['Header' => 3]);
            $this->SetProperty("CombineSubAccounts", false);
            $this->http->GetURL("https://www.golfgalaxy.com/myaccount/services/redirectingservice/athleteservice/v2/reward/gg?activeOnly=true");

            if ($this->http->Response['code'] == 403) {
                throw new CheckRetryNeededException();
            }

            $rewards = $this->http->JsonLog(null, true, true);

            foreach ($rewards as $reward) {
                // Expiration Date
                $expirationDate = ArrayVal($reward, 'expirationDate', null);
                // Online Code
                $code = ArrayVal($reward, 'onlineCode', null);
                $redeemed = ArrayVal($reward, 'redeemed', null);
                $active = ArrayVal($reward, 'active', null);
                $balance = ArrayVal($reward, 'amount', null);

                if (isset($code, $balance) && ($exp = strtotime($expirationDate)) && $active == true && $redeemed == false) {
                    $this->AddSubAccount([
                        'Code'           => 'golfAvailableRewards' . $code,
                        'DisplayName'    => "Reward {$code}",
                        'Balance'        => $balance,
                        'ExpirationDate' => $exp,
                        'BarCode'        => ArrayVal($reward, 'storeCode', null),
                        "BarCodeType"    => BAR_CODE_CODE_128,
                    ]);
                }// if (isset($code) && ($exp = strtotime($expirationDate)) && $active == true && $redeemed == false)
            }// foreach ($discounts as $discount)
        }// if ($response->rewardAmount > 0)

        //# Expiration Date  // refs #18746
        if ($this->Balance <= 0) {
            return;
        }
        $points = $this->Balance;
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $this->http->GetURL("https://www.golfgalaxy.com/myaccount/services/redirectingservice/pointservice/v2/PointsHistory/gg");
        $transactions = $this->http->JsonLog();
        $this->logger->debug("Total " . count($transactions) . " transactions were found");

        foreach ($transactions as $i => $transaction) {
            $historyPoints = $transaction->points;
            $historyPoints = str_replace(',', '', $historyPoints);
            $this->logger->debug("Node # $i - Balance: $points, HistoryPoints: $historyPoints");

            if ($historyPoints > 0) {
                $points -= $historyPoints;
            }
            $this->logger->debug("Node # $i - Balance: $points / round: " . round($points, 2));

            if ($points <= 0) {
                $date = $transaction->transactionDate;

                if (isset($date)) {
                    $this->SetProperty("EarningDate", date('M d, Y', strtotime($date)));
                    $this->SetExpirationDate(strtotime("+1 year", strtotime($date)));
                }
                // Expiring balance
                $this->SetProperty("ExpiringBalance", ($points + $historyPoints));

                break;
            }// if ($points <= 0)
        }// foreach ($transactions as $transaction)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.golfgalaxy.com/myaccount/services/redirectingservice/athleteservice/v2/athlete/personalinformation/gg", [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->loyaltyNumber)) {
            return false;
        }
        // Name
        $this->SetProperty('Name', beautifulName(($response->firstName ?? null) . ' ' . ($response->lastName ?? null)));
        // CardNumber - ScoreCard Number
        $this->SetProperty('CardNumber', $response->loyaltyNumber ?? null);

        return true;
    }
}
