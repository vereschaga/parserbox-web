<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerUpromise extends TAccountChecker
{
    use ProxyList;
//    use OtcHelper;
    /*
        public static function GetAccountChecker($accountInfo)
        {
            if (!empty($accountInfo['ConfNo']) && !empty($accountInfo['Code'])) {
                return new static();
            } else {
                require_once __DIR__ . '/TAccountCheckerUpromiseSelenium.php';
                return new TAccountCheckerUpromiseSelenium();
            }
        }
    */
    private $headers = [
        "Accept"          => "*/*",
        "Accept-Language" => "en-US,en;q=0.5",
        "Accept-Encoding" => "gzip, deflate, br",
        "Content-Type"    => "application/x-www-form-urlencoded; charset=UTF-8",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        /*
                $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        */
        $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.upromise.com/login/');
        $this->http->RetryCount = 1;
        $this->http->PostURL('https://api.upromise.com/?cmd=mp-gn-member-status', '_ajax=1', $this->headers);
        $response = $this->http->JsonLog();
        $value = $response->authToken->value ?? null;
        $name = $response->authToken->name ?? null;

        if (!$name || !$value) {
            return false;
        }
        $params = [
            "RefererUrl"           => "",
            "LandedUrl"            => "https://www.upromise.com/#",
            "sessionExpiration"    => str_replace('+00:00', '.000Z', date('c')),
            "persist"              => "on",
            "rv"                   => "2i",
            "email"                => $this->AccountFields['Login'],
            "password"             => $this->AccountFields['Pass'],
            "g-recaptcha-response" => "03AGdBq26VySOmY0180LC6wvfn7tnsnk2iNo7YNkKtsnJbtR4FAqMFWX_LPWfSiLCR8RGz8WTQBqjrWrCGmYa1Pt3DAT4SWHeFaxct_AA16VpxsHPdmTu0v6QIGUEYQLmLi0ZXfEWrEiwD5QllxMqJo7-IOPYfQ3w3KFloj6corRFZqZwynpjBOQT3pDQi9qJjCFAjOTRykIZkY2bt3eVk011du--IZbs1ct5RaZSQ902wc1ZlEYU4Rh8hQB2x0yrJLGJM4oml0wYZLJKJY_XzQFEVdkX5ZL1sEtr1dj7QhL6RzJ4176Ro67Ugc6xNUJueA7OYTYbaW_sT5z_kLOdkOUUmxp0xBNAHt9vHDpRe61bxRcsgX5LGYiydi4pOmfYhnBkQlxZ5zKEmJwZYvjWJNvA86f3FIYodN5vbQCxzTzUKzYHQWUDXtKjbjvp3K2Czm90AyJ4GcFubO4hDLU4ws1soceEK7QzswA",
            "_ajax"                => "1",
            $name                  => $value,
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://api.upromise.com/?cmd=mp-gn-login255', $params, $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    /*
            function parseQuestion() {
            $this->logger->notice(__METHOD__);
            $question = $this->http->FindSingleNode("//text()[contains(.,'Enter the verification code we emailed to ')]");
             if (empty($question))
                 return true;
            if (!$this->http->ParseForm('editPage'))
                return false;

            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            if ($this->getWaitForOtc()) {
                $this->sendNotification("2fa - refs #20505 // RR");
            }

            $this->State['verifyUrl'] = $this->http->currentUrl();
            $this->logger->debug("question found: " . $question);
            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";
            return true;
        }

        function ProcessStep($step) {
            $this->logger->notice(__METHOD__);
    //        $this->http->GetURL($this->State['verifyUrl']);
    //        if (!$this->http->ParseForm('editPage'))
    //            return false;
            $this->http->SetInputValue('emc', $this->Answers[$this->Question]);
            $this->http->SetInputValue('save', 'Verify');
            unset($this->Answers[$this->Question]);
            if (!$this->http->PostForm([
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,* / *;q=0.8',
                'Referer' => $this->State['verifyUrl'],
                'Origin' => 'https://upromise.force.com',
                'Pragma' => 'no-cache',
                'Upgrade-Insecure-Requests' => '1',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive'
            ]))
                return false;
            //$url = $this->http->Form['retURL'];
            // Invalid or expired verification code. Try again.
            if ($error = $this->http->FindSingleNode("//div[contains(text(), 'Invalid or expired verification code. Try again.')]")) {
                $this->http->ParseForm('editPage');
                $this->AskQuestion($this->Question, $error);
                return false;
            }

            $this->selenium();
            return true;
        }
     */

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);

        $response = $this->http->JsonLog();
        $success = $response->success ?? null;

        if ($success == false) {
            $login = $response->errors->login[0] ?? null;

            if ($login == "Login_IsValid") {
                throw new CheckException('That email and password combination does not match our records. Please double-check and try again.', ACCOUNT_INVALID_PASSWORD);
            }

            if ($login == "Login_InactiveAccount") {
                throw new CheckException('We closed your account after 30 months of inactivity.', ACCOUNT_INVALID_PASSWORD);
            }

            if ($login == "Login_IsDeactivated") {
                throw new CheckException('Your account has been deactivated. Please contact Customer Support.', ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $login;

            return false;
        }

        if ($success == true && $this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $this->SetProperty('Name', beautifulName($response->member->name ?? null));

        $memberSince = $response->member->memberSince ?? null;

        if (isset($memberSince)) {
            $memberSince = date("m-d-Y", strtotime($memberSince));
            $this->SetProperty("MemberSince", $memberSince);
        }

        $data = [
            "period"          => "all",
            "include-summary" => "true",
            "_ajax"           => "1",
            //   "__mBfuPAgT"      => "3d0b84b2271683ece0005d6bd1fb38cefe421b03581d5ad28c6be9c71cc8a47b",
        ];
        $this->http->RetryCount = 1;
        $this->http->PostURL("https://api.upromise.com/?cmd=upm-activity-summary", $data, $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        // Transferred
        if (isset($response->data->transferredPoints)) {
            $this->SetProperty('TotalTransferred', "$" . number_format($response->data->transferredPoints / 100, 2, '.', ''));
        }
        // Upromise Mastercard®
        if (isset($response->data->cardPoints)) {
            $this->SetProperty('CardPoints', "$" . number_format($response->data->cardPoints / 100, 2, '.', ''));
        }
        // Online Shopping
        if (isset($response->data->shopPoints)) {
            $this->SetProperty('ShopPoints', "$" . number_format($response->data->shopPoints / 100, 2, '.', ''));
        }
        // Restaurant Programs
        if (isset($response->data->diningPoints)) {
            $this->SetProperty('DiningPoints', "$" . number_format($response->data->diningPoints / 100, 2, '.', ''));
        }
        // Pending Rewards
        if (isset($response->summary->pending)) {
            // Pending
            $pending = $response->summary->pending / 100;
            $this->AddSubAccount([
                "Code"              => "upromisePending",
                "DisplayName"       => "Pending",
                "Balance"           => $pending,
                "BalanceInTotalSum" => true,
            ]);
        }
        // Earned Rewards
        if (isset($response->summary->available)) {
            // Confirmed
            $available = $response->summary->available / 100;
            $this->AddSubAccount([
                "Code"              => "upromiseEarned",
                "DisplayName"       => "Earned",
                "Balance"           => $available,
                "BalanceInTotalSum" => true,
            ]);
        }
        // Balance = Earned + Pending   // https://redmine.awardwallet.com/issues/19878#note-8
        if (isset($available, $pending)) {
            $this->SetBalance($available + $pending);
        }
        // Transferred Rewards
        if (isset($response->summary->transferred)) {
            // Payable
            $this->AddSubAccount([
                "Code"        => "upromiseTransferred",
                "DisplayName" => "Transferred",
                "Balance"     => $response->summary->transferred / 100,
            ]);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 1;
        $this->http->PostURL('https://api.upromise.com/?cmd=mp-gn-member-status', '_ajax=1', $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 0);
        $loggedIn = $response->member->loggedIn ?? null;
        $memberID = $response->member->memberID ?? null;

        if ($memberID && $loggedIn == true) {
            return true;
        }

        return false;
    }
}
