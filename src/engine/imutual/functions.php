<?php

class TAccountCheckerImutual extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.imutual.co.uk/ls?url=%2Fstatement");

        if (!$this->http->ParseForm("login")) {
            return false;
        }
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('login', 'true');
        $this->http->SetInputValue('submit', 'Login');

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.imutual.co.uk/ls?url=%2Fstatement";

        return $arg;
    }

    public function checkErrors()
    {
        return false;
    }

    public function Login()
    {
        // getting an intermediate page
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//*[@id='tab-login']/*[contains(text(),'Incorrect login details, please try again')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        // Available balance
        $this->SetBalance($this->http->FindSingleNode("//*[@id='content']/.//th[contains(text(),'Available balance')]/../td[1]/strong"));

        if ($this->Balance && strstr($this->Balance, "p")) {
            $this->Balance = str_replace("p", '', $this->Balance);
            $this->Balance = $this->Balance / 100;
        }

        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode('//p[contains(text(), " has")]', null, true, "/(.+) has /"));
        // Shares pending
        $this->SetProperty("SharesPending", $this->http->FindSingleNode("//*[@id='content']/.//th[contains(text(),'Shares pending')]/../td[1]"));
        // Shares awarded
        $this->SetProperty("SharesAwarded", $this->http->FindSingleNode("//*[@id='content']/.//th[contains(text(),'Shares awarded')]/../td[1]"));
        // Cashback pending
        $this->SetProperty("CashbackPending", $this->http->FindSingleNode("//*[@id='content']/.//th[contains(text(),'Cashback pending')]/../td[1]"));
        // Cashback awarded
        $this->SetProperty("CashbackAwarded", $this->http->FindSingleNode("//*[@id='content']/.//th[contains(text(),'Cashback awarded')]/../td[1]"));
        // Total redeemed
        $this->SetProperty("TotalRedeemed", $this->http->FindSingleNode("//*[@id='content']/.//th[contains(text(),'Total redeemed')]/../td[1]"));

        $accountNumber = $this->http->getCookieByName("user_id");

        if (!empty($accountNumber)) {
            $this->SetProperty("AccountNumber", $accountNumber);
        }
    }
}
