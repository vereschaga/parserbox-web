<?php

class TAccountCheckerNanoosa extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.nanoosa.com/login.php");

        if (!$this->http->ParseForm(null, 1, true, "//form[contains(@action, 'login.php')]")) {
            return false;
        }
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        /*
        $this->http->FormURL = "http://www.nanoosa.com/login.php";
        $this->http->SetFormText('username=test&password=test&action=login&login=Login','&',true,true);
        $this->http->Form['username'] = $this->AccountFields['Login'];
        $this->http->Form['password'] = $this->AccountFields['Pass'];
        */
        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]")) {
            return true;
        }
        /*
        // The website you were trying to reach is temporarily unavailable.
        if($this->http->FindSingleNode("//h1[contains(text(),'website you were trying to reach is temporarily')]")){
            $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
            $this->ErrorMessage = "The website you were trying to reach is temporarily unavailable.";
            return false;
        }
        */
        // Invalid Email or Password!
        if ($message = $this->http->FindSingleNode("//div[@class='error_msg' and contains(text(), 'Invalid Email or Password!')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->getURL('http://www.nanoosa.com/mybalance.php');
        //# Balance - Available Balance
        $AvailableBalance = $this->http->FindSingleNode("//tr[td/font/b[contains(text(), 'Available Balance')]]/td[position() = 2]/font/b", null, true, '/\$([\d\.]+)/ims');
        $this->SetBalance($AvailableBalance);
        //#
        $MyReferrals = $this->http->FindSingleNode('//div[@id="links"]/b');
        $this->SetProperty("MyReferrals", $MyReferrals);
        //# Pending Cashback
        $PendingCashback = $this->http->FindSingleNode("//tr[td[font[b[contains(text(), 'Pending Cashback')]]]]/td[position() = 2]//b");
        $this->SetProperty("PendingCashback", $PendingCashback);
        //# Lifetime Cashback
        $LifetimeCashback = $this->http->FindSingleNode("//tr[td/b[contains(text(), 'Lifetime Cashback')]]/td[position() = 2]/b");
        $this->SetProperty("LifetimeCashback", $LifetimeCashback);
        //# Declined Cashback
        $this->SetProperty("DeclinedCashback", $this->http->FindSingleNode("//tr[td[font[b[contains(text(), 'Declined Cashback')]]]]/td[position() = 2]//b"));
        //# Cash Out Requested
        $this->SetProperty("CashOutRequested", $this->http->FindSingleNode("//td[contains(text(), 'Cash Out Requested')]/following-sibling::td[1]"));
        //# Cash Out Processed
        $this->SetProperty("CashOutProcessed", $this->http->FindSingleNode("//td[contains(text(), 'Cash Out Processed')]/following-sibling::td[1]"));

        //# Full Name
        $this->http->GetURL("http://www.nanoosa.com/myprofile.php");
        $name = CleanXMLValue($this->http->FindSingleNode("//input[@id = 'fname']/@value")
                . ' ' . $this->http->FindSingleNode("//input[@id = 'lname']/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }
}
