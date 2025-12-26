<?php

class TAccountCheckerUci extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->FilterHTML = false;
        $this->http->removeCookies();
        $this->http->GetURL("http://www.skinucicard.it");

        if (!$this->http->ParseForm("login-form")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("_usr", $this->AccountFields['Login']);
        $this->http->SetInputValue("_pwd", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        // System maintenance
        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        // Invalid login or password
        if ($message = $this->http->FindSingleNode("(//div[contains(text(), 'email non esistente')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("http://www.skinucicard.it/reservedArea/saldo-punti");
        // Balance - Il tuo saldo punti (Balance *)
        $this->SetBalance(trim($this->http->FindSingleNode("//span[@class = 'box_totale_punti']")));
        // IL TUO SALDO SCONTI SUPERSKIN È
        $this->SetProperty('SuperSKINTotal', $this->http->FindSingleNode("//span[contains(text(), 'IL TUO SALDO SCONTI SUPERSKIN')]/following-sibling::span"));

        $this->http->GetURL("http://www.skinucicard.it/reservedArea/i-tuoi-dati");
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//input[contains(@name, 'first_name')]/@value") . " " . $this->http->FindSingleNode("//input[contains(@name, 'last_name')]/@value")));
    }
}
