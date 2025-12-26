<?php

class TAccountCheckerUtalkback extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.utalkback.com/home.do?ln=en&cc=US");

        if (!$this->http->ParseForm("Login")) {
            if ($message = $this->http->FindSingleNode('//td[strong[contains(text(), "We\'re sorry.")]]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }
        $this->http->Form["logonHelper.email"] = $this->AccountFields['Login'];
        $this->http->Form["logonHelper.password"] = $this->AccountFields['Pass'];

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();

        $access = $this->http->FindSingleNode("//a[@href = 'Logout.do']");

        if (isset($access)) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//span[@id='login-error-msg']/text()[1]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        if (preg_match("/you are logged in as[^>]+>\s*<[^>]+>\s*<[^>]+>([^<]+)/ims", $this->http->Response['body'], $matches)) {
            $name = $matches[1];
        }
        $this->SetProperty("Name", $name);

        if (preg_match("/account balance[^>]+>\s*<[^>]+>\s*<[^>]+>([^<a-zA-Z]+)/ims", $this->http->Response['body'], $matches)) {
            $name = $matches[1];
        }
        $this->SetBalance($name);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'http://www.utalkback.com/home.do?ln=en&cc=US';

        return $arg;
    }
}
