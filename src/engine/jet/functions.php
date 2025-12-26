<?php

class TAccountCheckerJet extends TAccountChecker
{
    public const REWARDS_PAGE_URL = 'https://jet.com/account';

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://jet.com';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $success = $this->loginSuccessful();
        $this->http->RetryCount = 2;

        if ($success) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);
        $csrfToken = $this->http->FindPreg('/clientCsrfToken":"([^\"]+)/');

        if (!$this->http->Response['code'] == 200 || !$csrfToken) {
            return $this->checkErrors();
        }
        $headers = [
            "jet-referer"             => '/register?continue=%2Faccount',
            "Content-Type"            => 'application/json;charset=utf-8',
            "Accept"                  => 'application/json, text/plain, */*',
            "x-csrf-token"            => trim($csrfToken, '"'),
        ];
        $this->http->GetURL("https://jet.com/proxy/login/ticket?experienceId=21", $headers);
        $response = $this->http->JsonLog();
        $ticket = $response->result->ticket ?? null;

        if (!$ticket) {
            return $this->checkErrors();
        }

        $data = [
            'email'        => $this->AccountFields['Login'],
            'password'     => $this->AccountFields['Pass'],
            'experienceId' => '21',
            'ticket'       => trim($ticket, '"'),
        ];
        $headers = [
            "jet-referer"             => '/register?continue=%2Faccount',
            "Content-Type"            => 'application/json;charset=utf-8',
            "Accept"                  => 'application/json, text/plain, */*',
            "x-csrf-token"            => trim($csrfToken, '"'),
            "X-Authentication-Ticket" => trim($ticket, '"'),
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://jet.com/v4/auth/login?experienceId=21', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $success = $response->success ?? null;

        if ($success === true && $this->loginSuccessful()) {
            return true;
        }

        if (isset($response->error->message)) {
            if ($response->error->code == 400 && $response->error->message == 'downstream request received bad code.') {
                throw new CheckException('This combination of email and password does not match an account on record. Please try again.', ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $response->error->code == 'locked'
                && (
                    $response->error->message == 'Having trouble logging in? Please wait a little while before you try again.'
                    || strstr($response->error->message, 'We\'re having some trouble with your account. Please email verification@jet.com')
                )
            ) {
                throw new CheckException('Having trouble logging in? Please wait a little while before you try again.', ACCOUNT_LOCKOUT);
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//span[contains(@class,'base__BaseStyledComponent') and contains(text(),'Name:')]", null, true, '/^Name:\s+([\S\s]*)/')));
        // You've Saved
        $this->SetProperty("YouSaved", $this->http->FindSingleNode('//span[contains(text(),"You\'ve saved")]/following-sibling::span/span'));
        $balAvailable = $this->http->FindSingleNode("//span[contains(text(),'JetCash (Spend Now)')]/following-sibling::span/span", null, true, "/\\$(.+)/");
        $balPending = $this->http->FindSingleNode("//span[contains(text(),'JetCash (Available Later)')]/following-sibling::span/span", null, true, "/\\$(.+)/");
        $balCredits = $this->http->FindSingleNode("//span[normalize-space(text())='Credits']/following-sibling::span/span", null, true, "/\\$(.+)/");
        // Available
        $this->AddSubAccount([
            "Code"              => "jetAvailable",
            "DisplayName"       => "Available",
            "Balance"           => $balAvailable,
            "BalanceInTotalSum" => true,
        ]);
        // Pending
        $this->AddSubAccount([
            "Code"              => "jetPending",
            "DisplayName"       => "Pending",
            "Balance"           => $balPending,
            "BalanceInTotalSum" => true,
        ]);
        // Credits
        $this->AddSubAccount([
            "Code"              => "jetCredits",
            "DisplayName"       => "Credits",
            "Balance"           => $balCredits,
            "BalanceInTotalSum" => true,
        ]);

        if (isset($balAvailable, $balPending, $balCredits)) {
            // Balance - (Available + Pending + Credits)
            $this->SetBalance($balAvailable + $balPending + $balCredits);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);

        if ($this->http->FindSingleNode("//a[contains(text(),'Log Out')]")) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
