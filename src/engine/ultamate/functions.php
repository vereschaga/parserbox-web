<?php

class TAccountCheckerUltamate extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.ulta.com/ulta/myaccount/login.jsp");

        if (!$this->http->ParseForm("loginForm")) {
            return false;
        }

        $this->http->Form["userID"] = $this->AccountFields['Login'];
        $this->http->Form["userPassword"] = $this->AccountFields['Pass'];

        $this->http->Form["/atg/b2cblueprint/profile/SessionBean.values.loginSuccessURL"] = './?omnitureLogin=1';
        $this->http->Form["/atg/userprofiling/B2CProfileFormHandler.login"] = 'Login';
        $this->http->Form["/atg/userprofiling/B2CProfileFormHandler.loginErrorURL"] = '/ulta/myaccount/login.jsp';
        $this->http->Form["/atg/userprofiling/B2CProfileFormHandler.loginSuccessURL"] = './?omnitureLogin=1';
        $this->http->Form["/atg/userprofiling/B2CProfileFormHandler.logoutSuccessURL"] = 'login.jsp';
        $this->http->Form["_DARGS"] = '/ulta/myaccount/login.jsp.login';

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();

        $access = $this->http->FindSingleNode("//a[contains(text(), 'Logout')]");

        if (isset($access)) {
            return true;
        }
        $error = $this->http->FindSingleNode("//p[@class ='error']");

        if (isset($error)) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = 'Invalid Username or Password';

            return false;
        }

        return false;
    }

    public function Parse()
    {
        $this->SetProperty("Name", $this->http->FindPreg("/>Name, Address<[^<]+>\s*<[^>]+>([^<]+)/ims"));
        $this->SetProperty("Number", $this->http->FindPreg("/Member Number:([^<]+)/ims"));

        $this->SetBalance($this->http->FindPreg("/Point Total:([^<]+)/ims"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://www.ulta.com/ulta/myaccount/index.jsp';

        return $arg;
    }
}
