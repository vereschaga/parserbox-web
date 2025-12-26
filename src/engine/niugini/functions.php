<?php

class TAccountCheckerNiugini extends TAccountChecker
{
    private const REWARDS_PAGE_URL = "https://www.airniugini.com.pg/destinations-loyalty/loyalty-hub/profile/";

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
        $this->http->GetURL("https://www.airniugini.com.pg/login/");
        $nonce = $this->http->FindPreg("/\"nonce\":\"([^\"]+)/");

        if (!$this->http->ParseForm("login") || !$nonce) {
            return $this->checkErrors();
        }

        $headers = [
            "Accept"           => "*/*",
            "X-Requested-With" => "XMLHttpRequest",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
        ];
        $data = [
            "action"   => "login_member",
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
            "nonce"    => $nonce,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.airniugini.com.pg/wp-admin/admin-ajax.php', $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->member_id)) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            if ($this->loginSuccessful()) {
                return true;
            }
        }

        $message = $response->error->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == "Invalid Credentials") {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[contains(text(), 'Member ID')]/preceding-sibling::h1")));
        // Membership Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//p[contains(text(), 'Member ID')]", null, true, "/ID\s*(.+)/"));
        // Balance - Destination Points
        $balance = $this->http->FindSingleNode("//p[contains(text(), 'Destination Point')]", null, true, "/Points?\s*(.+)/");
        $this->SetBalance($balance);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//p[contains(text(), 'Member ID')]")) {
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
