<?php

class TAccountCheckerSnh extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.greenpoints.com/greenpoints/login.php");

        if (!$this->http->ParseForm("login_form")) {
            return false;
        }
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("x", "51");
        $this->http->SetInputValue("y", "14");
        $this->http->SetInputValue("action", "login");

        return true;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm([], 120)) {
            return false;
        }
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }
        // Invalid login or password
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Unable to find user for this username')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // User unable to be found for this username
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'User unable to be found for this username')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Unexpected error occurred
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Unexpected error occurred')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/Welcome,([^.<]+)/ims")));
        // S&H Member #
        $this->SetProperty("MemberNumber", $this->http->FindSingleNode("//font[contains(@class, 'cardtext')]/text()"));
        // Balance - You have ... greenpoints
        $this->SetBalance($this->http->FindSingleNode("//b[contains(text(), 'You have')]", null, true, "/have\s*(.*)\s*greenpoints/ims"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.greenpoints.com/greenpoints/login.php';

        return $arg;
    }
}
