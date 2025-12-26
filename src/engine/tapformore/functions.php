<?php

class TAccountCheckerTapformore extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://tapformore.com.sg/");

        if (!$this->http->ParseForm("customer-login-form")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("FormLogin[username]", $this->AccountFields['Login']);
        $this->http->SetInputValue("FormLogin[password]", $this->AccountFields['Pass']);
        $this->http->SetInputValue("FormLogin[remember]", '0');

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://tapformore.com.sg/";

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Sorry, we're down for maintenance.
        if ($message = $this->http->FindSingleNode('//div[p[contains(text(), "Sorry, we\'re down for maintenance.")]]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            if ($message = $this->http->FindSingleNode("//div[contains(text(), '404')]/following-sibling::div[contains(text(), 'The page cannot be found.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'alert-danger')]", null, true, '/×(.+)$/i')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $name = $this->http->FindSingleNode("//input[@id='CustomerRegister_firstname']/@value") . ' ' .
                $this->http->FindSingleNode("//input[@id='CustomerRegister_lastname']/@value");
        $this->SetProperty("Name", beautifulName($name));
        // PAssion Card CAN No
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//input[@id='CustomerRegister_passion_card_id']/@value"));

        $this->http->GetURL('https://tapformore.com.sg/account/tfmpoints');
        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode("//td[@id='lblPassionCardPoints']", null, false));

        if (!isset($this->Balance) && $this->http->FindSingleNode("//td[@id='lblPassionCardPoints']") === '' && isset($this->Properties['Name'], $this->Properties['AccountNumber'])) {
            $this->SetBalanceNA();
        }

        // Redeemable Miles
        $this->SetProperty("RedeemableMiles", $this->http->FindSingleNode("//td[@id='lblPassionCardRedeemable']"));
    }
}
