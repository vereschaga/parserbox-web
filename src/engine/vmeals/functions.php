<?php

class TAccountCheckerVmeals extends TAccountChecker
{
    public function LoadLoginForm()
    {
        // reset cookie
        $this->http->removeCookies();
        // getting the HTML code
        $this->http->getURL('http://www.vmeals.com/index.cfm?fuseaction=main.login');
        // parsing form on the page
        if (!$this->http->ParseForm('login')) {
            return $this->checkErrors();
        }
        // enter the login and password
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        // form submission
        if (!$this->http->PostForm()) {
            $this->checkErrors();
        }

        // Invalid credentials
        if ($message = $this->http->FindSingleNode('//text()[contains(., "The sign in information you entered did not match any record in our database")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Sign in successful
        if ($this->http->FindSingleNode('//a[normalize-space(.) = "My VCAP"]')) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Go to total VCAP points page
        $this->http->GetURL('http://www.vmeals.com/index.cfm?fuseaction=myAccount.showVCAP');

        $this->SetBalance($this->http->FindSingleNode('//*[@id="statusLabel"]', null, true, '#(.*)\s+pts#'));

        $this->SetProperty('Name', beautifulName($this->http->FindPreg('#Welcome,\s+([^<]+)#')));

        $subj = $this->http->FindSingleNode('//p[contains(., ", you are") and contains(., "away from") and not(.//p)]');
        $regex = '#you\s+are\s+(.*?)\s+points\s+away\s+from\s+a\s+(.*?VCAP\s+Reward)\.\s+Order\s+today!#i';

        if (preg_match($regex, $subj, $m)) {
            $this->SetProperty('PointsToNextReward', $m[1]);
            $this->SetProperty('NextReward', $m[2]);
        }
    }

    public function checkErrors()
    {
        // here will be error handling
        return false;
    }
}
