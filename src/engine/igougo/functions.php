<?php

class TAccountCheckerIgougo extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->getURL('http://www.igougo.com/loginframe.aspx');

        if (!$this->http->ParseForm("form1")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('LoginEmailAddress', $this->AccountFields['Login']);
        $this->http->SetInputValue('LoginPassword', $this->AccountFields['Pass']);
        $this->http->Form['LoginBtn'] = '';
        $this->http->Form['LoginSubmit'] = 'Log In';

        return true;
    }

    public function checkErrors()
    {
        //# Problems with servers
        if ($message = $this->http->FindSingleNode('//div[contains(text(),"Our servers may be experiencing temporary problems")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Scheduled maintenance
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re tending to some scheduled maintenance")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Error in '/' Application
        if ($message = $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $t = $this->http->PostForm();

        if (!$t) {
            //code=500
            $this->http->SetBody($this->http->Response['body']);
        }

        if ($message = $this->http->FindSingleNode('//div[@id="messageError"]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//div[@class="messageWarning"]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL("http://www.igougo.com/register/redirect.aspx?SrcUrl=/profile/profile.aspx");

        //# Access is allowed
        if ($this->http->FindPreg("/function RedirectSoftPopup/")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("http://www.igougo.com/profile/profile.aspx");
        //# Member Name
        $this->SetProperty("Name", $this->http->FindPreg('/h2 class=\"member\">([^<]+)/ims'));
        // Balance - Current balance
        $this->SetBalance($this->http->FindPreg("/Current balance\:<\/b>\s*([^<]+)/ims"));
        // Joined
        $this->SetProperty("Joined", $this->http->FindSingleNode("//p[contains(text(), 'Joined:')]", null, true, "/Joined:\s*([^<]+)/ims"));
        // IgoUgo Rank
        $this->SetProperty("Rank", $this->http->FindPreg("/IgoUgo Rank\:<\/b>\s*([^<]+)/ims"));
        // IgoUgo Level
        $this->SetProperty("Level", $this->http->FindPreg("/IgoUgo Level\:<\/b>\s*([^<]+)/ims"));
        // Lifetime earned points
        $this->SetProperty("LifetimePoints", $this->http->FindPreg("/Lifetime earned points\:<\/b>\s*([^<]+)/ims"));
    }
}
