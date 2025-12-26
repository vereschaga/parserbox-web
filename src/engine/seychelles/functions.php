<?php

class TAccountCheckerSeychelles extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->FilterHTML = false;
        // "Membership number not found."
        if (strstr($this->AccountFields['Login'], "@")) {
            throw new CheckException("Membership number not found.", ACCOUNT_INVALID_PASSWORD);
        }
        // reset cookie
        $this->http->removeCookies();
        // getting the HTML code
        $this->http->getURL('http://www.airseychelles.com/en/seychelles_plus/my_account.php');
        // parsing form on the page
        if (!$this->http->ParseForm('LoginForm')) {
            return $this->checkErrors();
        }
        // enter the login and password
        $this->http->SetInputValue('User_Name', $this->AccountFields['Login']);
        $this->http->SetInputValue('Pass_Word', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        //# The selected page is currently not available
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'The selected page is currently not available')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Trace on the site after submit form
        if ($message = $this->http->FindPreg("/(A connection attempt failed because the connected party did not properly respond after a period of time, or established connection failed because connected host has failed to respond\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // form submission
        if ($this->http->PostForm()) {
            return $this->checkErrors();
        }

        // check for invalid password
        if (($message = $this->http->FindSingleNode("//span[@class='error']")) && $this->http->FindSingleNode("//form[@id='LoginForm']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // login successful
        if ($this->http->FindSingleNode("//input[contains(@src,'logout.png')]/@src")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id='cntTable']//div[@class='loginicon']/div")));
        $this->SetProperty("Tier", $this->http->FindSingleNode("//div[@id='tabs-2']//table/tr[2]/td[2]/img/@title", null, true, '/^([^\s]+)/is'));
        // set balance
        $this->SetBalance($this->http->FindSingleNode("//div[@id='tabs-2']//table/tr[5]/td", null, true, '/[^=]+=(.*)/is'));
    }
}
