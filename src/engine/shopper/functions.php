<?php

class TAccountCheckerShopper extends TAccountChecker
{
    // Network error 28 - Operation timed out after 60001 milliseconds with 0 bytes received
//    function InitBrowser() {
//        parent::InitBrowser();
//        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
//                $this->http->SetProxy($this->proxyDOP());
//        }
//    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.shopperdiscountsandrewards.com/Home/Default.rails");

        if (!$this->http->ParseForm("frmLogin")) {
            return false;
        }
        $this->http->SetInputValue("emailAddress", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("rememberMe", 'yes');

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//span[contains(text(), 'Logout')]")) {
            return true;
        }

        if ($message = $this->http->FindPreg("/<br>\s*Please Try Again/ims")) {
            throw new CheckException('The Email or password you entered was invalid', ACCOUNT_INVALID_PASSWORD);
        }
        //# Invalid email or password
        if ($message = $this->http->FindPreg("/(There was an error in processing your Email address and Password combination)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Membership Cancelled
        if ($message = $this->http->FindPreg("/(Your Email Address and Password combination is no longer active because your membership has been cancelled\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Savings earned to date
        if (($find = $this->http->FindPreg("/Savings earned to date:[~\s]*([^<]+)/ims")) && ($find != null)) {
            $this->SetBalance($find);
        } elseif ($this->http->FindPreg("/Start shopping today/ims")) {
            $this->SetBalanceNA();
        }
        // Member #
        $this->SetProperty("Member", $this->http->FindPreg("/Member #:[~\s]*([^<]+)/ims"));
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindPreg("/Member Since:[~\s]*([^<]+)/ims"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/Hello[^\s]*([^<!]+)/ims")));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.autozonerewards.com/viewLogin.htm';

        return $arg;
    }
}
