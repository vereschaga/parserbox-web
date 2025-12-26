<?php

class TAccountCheckerWachovia extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.wachoviapossibilities.com");

        if (!$this->http->ParseForm("loginForm")) {
            return false;
        }
        $this->http->Form['cardNum'] = $this->AccountFields['Login'];
        $this->http->Form['password'] = $this->AccountFields['Pass'];
        //$this->http->Form['__EVENTTARGET'] = '';
        return true;
    }

    public function Login()
    {
        $this->http->PostForm();
        $error = $this->http->FindSingleNode("//p[contains(text(), 'There is an error with the log in information you typed')]", null, false);

        if (isset($error)) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = $error;

            return false;
        }
        $this->http->GetURL("https://www.wachoviapossibilities.com/pointsMain.jspx");

        return true;
    }

    public function Parse()
    {
        $balance = $this->http->FindSingleNode("//a[@class = 'points']");

        if (isset($balance)) {
            $balance = preg_replace("/[^\d]/ims", "", $balance);
            $this->SetBalance($balance);
        }
        $this->SetProperty("BasePoints", $this->http->FindSingleNode("//dd[contains(text(), 'Base points:')]/span")); // Base points
        $this->SetProperty("BonusPoints", $this->http->FindSingleNode("//dd[contains(text(), 'Bonus points:')]/span")); // Bonus points
        $this->SetProperty("Adjustments", $this->http->FindSingleNode("//dt[contains(text(), 'Adjustments')]/span")); // Adjustments
        $this->SetProperty("Expired", $this->http->FindSingleNode("//dt[contains(text(), 'Expired points:')]/span")); // Expired points
        $this->SetProperty("AuctionReserve", $this->http->FindSingleNode("//dt[contains(text(), 'Points in auction reserve:')]/span")); // Points in auction reserve
        $this->SetProperty("Redeemed", $this->http->FindSingleNode("//dt[contains(text(), 'Points redeemed this month:')]/span")); // Points redeemed this month
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.wachoviapossibilities.com";

        return $arg;
    }
}
