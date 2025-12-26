<?php

class TAccountCheckerNaturemade extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->getURL('https://pharmavite.promo.eprize.com/wellnessrewards/?auth_action=login');

        if (!$this->http->ParseForm('intro_login_form')) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://pharmavite.promo.eprize.com/wellnessrewards/';
        $this->http->SetInputValue('email', $this->AccountFields["Login"]);
        //		$this->http->SetInputValue('pagecolumns_0$contentcolumn_0$login$textPassword', $this->AccountFields["Pass"]);
//        $this->http->Form['__EVENTTARGET'] = 'pagecolumns_0$contentcolumn_0$login$button';

        return true;
    }

    public function checkErrors()
    {
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // form submission
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // check for invalid password
        if ($message = $this->http->FindSingleNode("//div[@class='error']/text()[last()]")) {
            if (CleanXMLValue($message) == 'Invalid username or password.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            } else {
                $this->http->Log(">>> $message");
            }
        }
        // login successful
        if ($this->http->FindSingleNode("//a[contains(text(), 'Sign Out')]")) {
            return true;
        }
        // Welcome to the new Wellness Rewards!
        if ($this->http->FindPreg("/please register for the new program by filling out the fields below\./")) {
            throw new CheckException("Nature Made (Wellness Rewards) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName(CleanXMLValue($this->http->FindSingleNode("//li[contains(text(), 'Welcome,')]/span"))));
        // Balance - Sweepstakes entries earned
        $this->SetBalance($this->http->FindSingleNode("//span[@class = 'counter_text']"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.naturemade.com/login";

        return $arg;
    }
}
