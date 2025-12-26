<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerMemolink extends TAccountChecker
{
    use ProxyList;

    // error: Network error 52 - Empty reply from server
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->SetProxy($this->proxyDOP());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.memolink.com/index.cfm");
        $this->http->GetURL("http://www.memolink.com/xhr/gLogin.cfm?campID=19");
        $this->http->GetURL("http://www.memolink.com/index.cfm/design.gLogin");

        if (!$this->http->ParseForm("login")) {
            return $this->checkErrors();
        }
        $this->http->Form['username'] = $this->AccountFields['Login'];
        $this->http->Form['password'] = $this->AccountFields['Pass'];

        return true;
    }

    public function checkErrors()
    {
        if ($this->http->FindPreg("/The web site you are accessing has experienced an unexpected error\./")
            || $this->http->FindSingleNode("//div[contains(text(), 'The specified URL cannot be found.')]")
            || $this->http->FindSingleNode("//h2[contains(text(), 'The requested URL could not be retrieved')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // The requested service is temporarily unavailable. It is either overloaded or under maintenance. Please try later.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'The requested service is temporarily unavailable. It is either overloaded or under maintenance. Please try later.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($redirect = $this->http->FindPreg("/parent.window.location = \"([^\"]+)/ims")) {
            $this->http->GetURL($redirect);
        }

        if ($this->http->FindSingleNode('//a[contains(@href, "logout") or contains(@href, "Logout") or contains(text(), "logout")]')) {
            return true;
        }
        //# Invalid credentials
        if ($message = $this->http->FindSingleNode('//font/b[contains(text(),"login is incorrect")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Username or Password not found')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Username
        $this->SetProperty("Name", $this->http->FindSingleNode("//div[@id = 'user']/span[1]"));
        // Balance
        $this->SetBalance($this->http->FindSingleNode("//div[@id = 'user']/span[2]", null, true, '/(\d+.\d+|\d+)/im'));
        // Membership Status
        $status = $this->http->FindSingleNode("//img[contains(@src, '/assets/images/memolink')]/@src", null, true, null, 0);

        if (preg_match("/memolink([^<]+)Logo\.png/ims", $status, $matches)) {
            $status = $matches[1];
        }

        if ($status == 'Gold') {
            $this->SetProperty("Status", "Gold");
        }

        $this->http->GetURL("http://www.memolink.com/index.cfm/earn.easyPoints");
        // Your Referrals
        $this->SetProperty("Referrals", $this->http->FindSingleNode("//a[contains(text(), 'Your Referrals')]/following::span[1]"));
        // Pending Referrals
        $this->SetProperty("PendingReferrals", $this->http->FindSingleNode("//a[contains(text(), 'Pending Referrals')]/following::span[1]"));
    }
}
