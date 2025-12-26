<?php

class TAccountCheckerMogl extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->FilterHTML = false;
        $this->http->removeCookies();
        $this->http->GetURL("https://www.mogl.com/login");

        if (!$this->http->ParseForm(null, "//form[@action = '/login_check']")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

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
        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        // Invalid login or password
        if ($message = $this->http->FindSingleNode("//strong[contains(text(),'The email or password you entered is incorrect')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($profileURL = $this->http->FindSingleNode("(//a[contains(text(), 'Profile')]/@href)[1]")) {
            $this->logger->debug("Loading profile page...");
            $this->http->NormalizeURL($profileURL);
            $this->http->GetURL($profileURL, [], 120);
        }
        // Balance - Current Points Balance
        $this->SetBalance($this->http->FindSingleNode("//a[@href = '#modal-cashback']/span"));
        // 1. Name (Name + Surname)
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//h1[contains(@class, 'user-name')]")));
        // 2. Jackpots
        $this->SetProperty('Jackpots', $this->http->FindSingleNode("//a[@href = '#modal-jackpots-won']/span"));
        // 3. Meals donated
        $this->SetProperty('MealsDonated', $this->http->FindSingleNode("//span[contains(@class, 'user-meals')]"));
        // 4. Cash Back
        $this->SetProperty('CashBack', $this->http->FindSingleNode("//a[@href = '#modal-cashback']/span"));
        // 6. Places
        $this->SetProperty('Places', $this->http->FindSingleNode("//a[@href = '#modal-places']/span"));
    }
}
