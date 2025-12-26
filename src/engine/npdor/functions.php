<?php

class TAccountCheckerNpdor extends TAccountChecker
{
    public function LoadLoginForm()
    {
        // reset cookie
        $this->http->removeCookies();
        // getting the HTML code
        $this->http->GetURL('http://www.sweepland.com/Toluna.MR.TrafficUI/MSCUI/Page.aspx?pgtid=1&utcoffset=-6');
        // parsing form on the page
        if (!$this->http->ParseForm('form1')) {
            return $this->checkErrors();
        }
        // enter the login and password
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1_2$ctl00$m_txtEmail', $this->AccountFields["Login"]);
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1_2$ctl00$m_txtPassword', $this->AccountFields["Pass"]);
        $this->http->SetInputValue('__EVENTTARGET', 'ctl00$ContentPlaceHolder1_2$ctl00$m_btnLogin');

        return true;
    }

    public function checkErrors()
    {
        // maintenance
        if ($message = $this->http->FindPreg("/We are currently in the process of updating our website. Your opinion is very important to us. Please visit the site again in a few hours\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // form submission
        if (!$this->http->PostForm()) {
            return false;
        }

        // redirect
        /*if ($redirect = $this->http->FindSingleNode("//div[@id = 'header']", null, true, "/top\.location\s*=\s*'([^\']+)'/")) {
            $this->http->Log("Redirect: {$redirect}");
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);
        }*/

        // check for invalid password
        if ($this->http->FindSingleNode("//input[@id='ctl00_imgLogin']/@id")) {
            throw new CheckException('Invalid Email Address or Password', ACCOUNT_INVALID_PASSWORD);
        }
        // login successful
        if ($this->http->FindSingleNode("(//a[contains(@href, 'act=lgt')]/@href)[1]")) {
            return true;
        }
        // Our records indicate that you tried to register with us in the past but never activated your account.
        if ($message = $this->http->FindPreg("/(Our records indicate that you tried to register with us in the past but never activated your account\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Your previous account with us has been deactivated
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Your previous account with us has been deactivated')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are having difficulty contacting you by email.
        if ($message = $this->http->FindPreg("/(We are having difficulty contacting you by email\.[^<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Get started as soon as you complete this short, one-time registration
        if ($this->http->FindPreg("/(You have already earned points which can be used as entries into great prize drawings! Get started as soon as you complete this short, one-time registration\.)/ims")) {
            throw new CheckException('Please update your profile', ACCOUNT_PROVIDER_ERROR);
        }
        // You have previously closed this account.
        if ($message = $this->http->FindPreg("/(You have previously closed this account\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // This email and password combination is not recognized. Please try again.
        if ($message = $this->http->FindPreg("/(This email and password combination is not recognized\.\s*Please try again\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        // refs #6812
        $this->http->GetURL('http://www.sweepland.com/Toluna.MR.TrafficUI/MSCUI/Page.aspx?pgtid=3');

        if ($href = $this->http->FindSingleNode("(//a[contains(@href, 'http://vipvoicerewards.com/bidland/loginredirect') and contains(@class, 'bidlandboxtop')]/@href)[1]")) {
            $this->http->GetURL($href);
            // Provider error
            if ($this->http->Response['code'] == 500) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }
        //# Balance - Points Available
        $this->SetBalance($this->http->FindSingleNode("//span[normalize-space(text())='Points Available:']/following-sibling::span"));
        //# SweepLand Points in Play
        $this->SetProperty("InPlay", $this->http->FindSingleNode("//span[contains(text(),'SweepLand')]/following-sibling::span"));
        //# BidLand Points in Play
        $this->SetProperty("InPlayBidLand", $this->http->FindSingleNode("//span[contains(text(),'BidLand')]/following-sibling::span"));
        //# Total Points
        if (isset($this->Balance, $this->Properties['InPlay'], $this->Properties['InPlayBidLand'])) {
            $this->SetProperty("TotalPoints", $this->Balance + $this->Properties['InPlay'] + $this->Properties['InPlayBidLand']);
        }

        $this->http->GetURL('http://www.sweepland.com/Toluna.MR.TrafficUI/MSCUI/Page.aspx?pgtid=11&pt=1000001');
        // set Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[@id='ContentPlaceHolder1_1_ctl00_m_questionSheet_ctl02_1001003_2224500_2']/@value") . " " .
            $this->http->FindSingleNode("//input[@id='ContentPlaceHolder1_1_ctl00_m_questionSheet_ctl02_1001004_2224501_3']/@value")));
    }
}
