<?php

class TAccountCheckerNigeria extends TAccountChecker
{
    public function LoadLoginForm()
    {
        // reset cookie
        $this->http->removeCookies();
        // getting the HTML code
        $this->http->getURL("http://www.myairnigeria.com/en/ng/LogIn/tabid/146/language/en-US/Default.aspx");
        // parsing form on the page
        if (!$this->http->ParseForm("Form")) {
            return false;
        }
        // enter the login and password
        $this->http->SetInputValue("dnn\$ctr526\$Login\$Login_DNN\$txtUsername", $this->AccountFields["Login"]);
        $this->http->SetInputValue("dnn\$ctr526\$Login\$Login_DNN\$txtPassword", $this->AccountFields["Pass"]);
        $this->http->Form["dnn\$ctr526\$Login\$Login_DNN\$cmdLogin"] = "Login";
        $this->http->Form["ScrollTop"] = "79";
        // where to send the form
        $this->http->FormURL = "http://www.myairnigeria.com/en/ng/LogIn/tabid/146/language/en-US/Default.aspx";

        return true;
    }

    public function Login()
    {
        // form submission
        if (!$this->http->PostForm()) {
            return false;
        }
        // check for invalid password
        if ($message = $this->http->FindSingleNode("//span[@id='dnn_ctr526_ctl00_lblMessage']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // login successful
        if ($this->http->FindSingleNode("//a[@id='dnn_samTopDash_dnnLOGIN_cmdLogin']")) {
            return true;
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = "http://www.myairnigeria.com/en/ng/HOME/tabid/36/language/en-US/Default.aspx";
        $arg["NoCookieURL"] = true;
        $arg["PreloadAsImages"] = true;

        return $arg;
    }

    public function Parse()
    {
        // set Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@id='dnn_ctr805_EagleFlierlandingpage_lblname']")));
        // set Email
        $this->SetProperty("Email", $this->http->FindSingleNode("//span[@id='dnn_ctr805_EagleFlierlandingpage_lblEmail']"));
        // set AccountNumber (Membership Number)
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//span[@id='dnn_ctr805_EagleFlierlandingpage_lblNo']"));
        // set MembershipLevel
        $this->SetProperty("MembershipLevel", $this->http->FindSingleNode("//span[@id='dnn_ctr805_EagleFlierlandingpage_lblLevel']"));
        // set RewardMiles
        $this->SetProperty("RewardMiles", $this->http->FindSingleNode("//span[@id='dnn_ctr805_EagleFlierlandingpage_lblRewards']"));
        // set RedeemedMiles
        $this->SetProperty("RedeemedMiles", $this->http->FindSingleNode("//span[@id='dnn_ctr805_EagleFlierlandingpage_lblRedm']"));
        // set Balance
        if (!$this->SetBalance($this->http->FindSingleNode("//span[@id='dnn_ctr805_EagleFlierlandingpage_lblMiles']"))) {
            if (isset($this->Properties["MembershipLevel"]) && isset($this->Properties["AccountNumber"]) && isset($this->Properties['Name'])) {
                $this->SetBalanceNA();
            }
        }
    }
}
