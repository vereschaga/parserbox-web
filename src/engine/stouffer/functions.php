<?php

class TAccountCheckerStouffer extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->http->GetURL("http://dinnerclub.stouffers.com/index.tbapp?page=my_account");
//        if (!$this->http->ParseForm("login_form"))
//            return $this->checkErrors();
//        $this->http->SetInputValue('email', $this->AccountFields['Login']);
//        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        // second login form
        $this->http->GetURL("https://www.stouffers.com/user.aspx");

        if (!$this->http->ParseForm("form1")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('ctl00$ctl00$ctl00$ContentPlaceHolderDefault$ContentPlaceHolder1$ctl00$UserPage_1$txtUserName', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$ctl00$ctl00$ContentPlaceHolderDefault$ContentPlaceHolder1$ctl00$UserPage_1$txtPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('ctl00$ctl00$ctl00$ContentPlaceHolderDefault$ContentPlaceHolder1$ctl00$UserPage_1$Login.x', 21);
        $this->http->SetInputValue('ctl00$ctl00$ctl00$ContentPlaceHolderDefault$ContentPlaceHolder1$ctl00$UserPage_1$Login.y', 9);
        // set cookie
        $this->http->setCookie("cr3_reset", "1", ".stouffers.com");
//        $this->http->setCookie("decipherinc_seen_popup", "set", "www.stouffers.com");
        return true;
    }

    public function checkErrors()
    {
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our servers are currently unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'This site is currently down for maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // This promotion is down for maintenance. Please try again later.
        if ($message = $this->http->FindPreg("/(This promotion is down for maintenance\.\s*Please try again later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server is too busy
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Server is too busy')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Internal server error
        if ($message = $this->http->FindPreg("/(The page cannot be displayed because an internal server error has occurred.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# The service is unavailable
        if ($message = $this->http->FindPreg("/(The service is unavailable.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Error in '/' Application
        if ($this->http->FindPreg("/Server Error in \'\/\' Application/ims")
            // Page not found
            || $this->http->FindSingleNode("//h1[contains(text(), 'Page not found')]")
            // Gateway Timeout
            || $this->http->FindPreg("/The proxy server did not receive a timely response/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We can't seem to find what you're looking for or there's an error!
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We can\'t seem to find what you\'re looking for or there\'s an error!")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // hard code
        $accountIDs = ArrayVal($this->AccountFields, 'RequestAccountID', $this->AccountFields['AccountID']);

        if ($this->http->FindSingleNode('//input[@name = "ctl00$ctl00$ctl00$ContentPlaceHolderDefault$ContentPlaceHolder1$ctl00$UserPage_1$txtUserName"]/@value') == $this->AccountFields["Login"]
            && $this->http->ParseForm('form1')
            && in_array($accountIDs, [493177])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->ParseForm("home_form") or $this->http->ParseForm("b3_login")) {
            $this->http->PostForm();
        }

        if (preg_match("/EditInformation\.aspx/ims", $this->http->currentUrl())) {
            throw new CheckException("Action Required. Please login to Stouffer's Dinner Club and respond to a message that you will see after your login.", ACCOUNT_PROVIDER_ERROR);
        }

        //# Access is allowed
        if ($this->http->FindSingleNode("//div[@id = 'account_info']//span[@id = 'log_out_btn']")) {
            return true;
        }

        // second login form
        if ($this->http->FindSingleNode("(//a[contains(@href, 'SignOut')]/@href)[1]")) {
            return true;
        }

//        ## Email address or password is incorrect
//        if ($message = $this->http->FindSingleNode("(//span[@class = 'error'])[1]", null, false))
//            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // It appears that you have an incorrect password/login. (second login form)
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'It appears that you have an incorrect password/login')]", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We're sorry. Either the email address does not match an existing registered user or the password you entered was not valid, please try again.
        if ($message = $this->http->FindPreg("/We\'re sorry\.\s*Either the email address does not match an existing registered user or the password you entered was not valid\,\s*please try again\./ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // You have exceeded the number of login attempts and your account has been locked
        if ($message = $this->http->FindPreg("/You have exceeded the number of login attempts and your account has been locked\./ims")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // To all of our valued Dinner Club members, we sincerely apologize for closing the website at this time.
        if ($message = $this->http->FindPreg("/(To all of our valued Dinner Club members, we sincerely apologize for\s*closing\s*the website at this time\.[^<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // hard code
        $accountIDs = ArrayVal($this->AccountFields, 'RequestAccountID', $this->AccountFields['AccountID']);

        if ($this->http->currentUrl() == 'https://www.stouffers.com/user.aspx'
            && in_array($accountIDs, [195815, 1225357, 1231233, 930357])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://dinnerclub.stouffers.com/");

        // The STOUFFER'S® Dinner Club program ended
        if ($this->http->FindPreg("/Dinner\&nbspClub program ended 12\/31\/14/ims")) {
            throw new CheckException("The STOUFFER'S® Dinner Club program ended 12/31/14.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Balance - My Points Balance
        $this->SetBalance($this->http->FindSingleNode("//div[@id = 'points_balance']"));
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h2[@id = 'welcome']", null, true, "/Welcome\s*([^<\.]+)/ims")));

        // Page: My Account
        if ($this->http->ParseForm("account_form")) {
            $this->http->PostForm();
        }
        //# Lifetime Points Earned
        $this->SetProperty("LifetimePoints", $this->http->FindSingleNode("//*[contains(text(), 'Lifetime Points Earned')]/following::span[1]"));
        //# Points Donated
        $this->SetProperty("PointsDonated", $this->http->FindSingleNode("//*[contains(text(), 'Points Donated')]/following::span[1]"));
        //# Lifetime Points Spent on Game
        $this->SetProperty("LifetimePointsSpent", $this->http->FindSingleNode("//*[contains(text(), 'Lifetime Points Spent on Game')]/following::span[1]"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->FindSingleNode("//div[@id = 'login_btn']/a[contains(text(), 'Log in')]")) {
            $this->ErrorCode = ACCOUNT_WARNING;
            $this->ErrorMessage = self::NOT_MEMBER_MSG;
        }
    }

    //	function GetRedirectParams($targetURL = NULL){
//		$arg = parent::GetRedirectParams($targetURL);
//		$arg["CookieURL"] = "http://dinnerclub.stouffers.com/";
//		return $arg;
//	}
}
