<?php

class TAccountCheckerStonyfield extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.stonyfield.com/user");

        if (!$this->http->ParseForm('user-login')) {
            return $this->CheckErrors();
        }
        // Login and Password
        $this->http->SetInputValue('name', $this->AccountFields['Login']);
        $this->http->SetInputValue('pass', $this->AccountFields['Pass']);

        return true;
    }

    public function CheckErrors()
    {
        //# The site is currently not available
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The site is currently not available due to technical problems')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // Submitting form data
        if (!$this->http->PostForm()) {
            return $this->CheckErrors();
        }

        // If wrong login or password: "your login is incorrect"
        if ($message = $this->http->FindSingleNode("//div[@class='messages error']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, '/logout')]/@href")) {
            return true;
        }

        return $this->CheckErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('http://www.stonyfield.com/coupons-offers/mystonyfield-rewards');

        if (!preg_match("/^https?:\/\/(?:www\.)?stonyfield\.com/ims", $this->http->currentUrl())) {
            $this->http->GetURL('http://www.stonyfield.com/coupons-offers/mystonyfield-rewards');
        }

        if ($this->http->currentUrl() == 'http://www.stonyfield.com/msr/login.htm' && $this->http->ParseForm("user-login")) {
            $this->http->SetInputValue('name', $this->AccountFields['Login']);
            $this->http->SetInputValue('pass', $this->AccountFields['Pass']);
            $this->http->PostForm();
        }

        // message on the site: "Your last day to enter and redeem points on this site is April 12, 2013."
        $this->SetExpirationDate(strtotime("April 12, 2013"));

        //# Balance - My Points
        if (!$this->SetBalance($this->http->FindSingleNode('//div[normalize-space(@class)="accountPoints"]/span[@class="accountPointsTotal"]'))) {
            if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'My Stonyfield Rewards Temporarily Unavailable')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode("//font[contains(text(), 'An unexpected error has occurred')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            //# myStonyfield Rewards may be over
            if ($message = $this->http->FindSingleNode('//h1[contains(text(), "myStonyfield Rewards may be over, but we\'re not done rewarding our most loyal fans!")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }
        //# Name
        $this->http->GetURL("http://www.stonyfield.com/user");
        $this->SetProperty("Name", $this->http->FindSingleNode("//th[contains(text(), 'Name')]/following-sibling::td"));

        //# Rewards is temporarily unavailable
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, myStonyfield Rewards is temporarily unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
    }
}
