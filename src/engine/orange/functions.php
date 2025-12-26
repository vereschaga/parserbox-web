<?php

// Feature #4063

class TAccountCheckerOrange extends TAccountChecker
{
    public const REWARDS_PAGE_URL = 'https://www.gasbuddy.com/account/profile';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        /*
        $this->http->setHttp2(true);
        */
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
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if (!$this->http->ParseForm(null, "//form[@aria-label=\"form\"]")) {
            return $this->checkErrors();
        }

        $data = [
            'identifier' => $this->AccountFields['Login'],
            'password'   => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://iam.gasbuddy.com/login?return_url=https://www.gasbuddy.com/account/profile', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//p[contains(text(), 'There is an unknown connection issue between Cloudflare and the origin web server. As a result, the web page can not be displayed.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $message = $response->message ?? null;
        $destination = $response->response->destination ?? null;

        if ($destination) {
            $this->http->GetURL($destination);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Forgot your password? Send yourself a login link.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'This account requires email verification to login. Use the Login Link functionality') {
                $this->throwProfileUpdateMessageException();
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // All-Time Points
        $this->SetProperty("AllTimePoints", $this->http->FindSingleNode('//h4[span[contains(text(), "All-Time Points")]]/following-sibling::span[1]'));

        foreach (['Daily Prize Draw', 'Consecutive Days'] as $v) {
            $this->SetProperty(preg_replace('#[\s\']#', '', $v), $this->http->FindSingleNode('//h4[span[contains(text(), "' . $v . '")]]/following-sibling::span[1]'));
        }
        // Joined
        $this->SetProperty("JoinDate", $this->http->FindSingleNode('//span[contains(text(), "Joined:")]', null, true, "/Joined:\s*([^<]+)/"));
        $this->SetProperty("Username", $this->http->FindSingleNode('//h2[contains(@class, "ProfileMemberName-module__userName___")]'));

        $this->http->GetURL("https://www.gasbuddy.com/account/savings");
        // Balance - Available GasBack
        $this->SetBalance($this->http->FindSingleNode('//p[contains(text(), "Available GasBack")]/preceding-sibling::span'));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->SetWarning($this->http->FindSingleNode("//span[contains(text(), 'Error loading your pay account information')]"));
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg("/Your Member Profile/ims")) {
            return true;
        }

        return false;
    }
}
