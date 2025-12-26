<?php

class TAccountCheckerWinecellarage extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://winecellarage.com/my-account/");

        if (!$this->http->ParseForm(null, "//form[contains(@class, 'login')]")) {
            return false;
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('rememberme', 'forever');
        $this->http->SetInputValue('login', 'Login');

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://winecellarage.com/my-account/';
        //		$arg['SuccessURL'] = 'http://www.winecellarage.com/reward/customer/info/';

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindNodes("//a[contains(@href, 'logout')]")) {
            return true;
        }
        // ERROR: The password you entered for the email address .... is incorrect.
        if ($message = $this->http->FindSingleNode("//ul[contains(@class, 'woocommerce-error')]/li")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // no errors, no auth (AccountID: 6570881)
        if ($this->AccountFields['Login'] == 'i88mas@aol.com') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//p[starts-with(normalize-space(text()), 'Hello')]/strong[1]"));
        // Balance - You have 0 Points
        $this->SetBalance($this->http->FindSingleNode('//p[contains(.,"You have") and contains(.,"Points")]/strong'));
    }
}
