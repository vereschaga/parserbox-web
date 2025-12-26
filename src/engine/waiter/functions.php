<?php

class TAccountCheckerWaiter extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.waiter.com/");
        $this->http->GetURL("https://www.waiter.com/login");

        if (!$this->http->ParseForm("login_form")) {
            return false;
        }
        $this->http->SetInputValue("user_session[login]", $this->AccountFields['Login']);
        $this->http->SetInputValue("user_session[password]", $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'flash-error')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://www.waiter.com/home/header");
        $this->http->Response['body'] = json_decode($this->http->Response['body']);

        if (property_exists($this->http->Response['body'], 'top')) {
            $this->http->SetBody($this->http->Response['body']->top);
            $this->http->SaveResponse();

            if ($this->http->FindSingleNode("//a[contains(@href, 'signout') and contains(text(), 'Sign Out')]")) {
                return true;
            }
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//li[contains(text(), 'Welcome')]", null, true, "/^Welcome: (.+) \(/ims"));

        $this->http->GetURL("https://www.waiter.com/api/v1/me");
        $response = $this->http->JsonLog();

        if (isset($response->first_name, $response->last_name)) {
            $this->SetProperty("Name", beautifulName($response->first_name . ' ' . $response->last_name));
        }
        // Balance - WaiterPoints available
        if (isset($response->waiterpoints_balance)) {
            $this->SetBalance($response->waiterpoints_balance);
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.waiter.com/login';
        $arg['SuccessURL'] = 'https://www.waiter.com/prizes';

        return $arg;
    }
}
