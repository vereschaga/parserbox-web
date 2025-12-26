<?php

class TAccountCheckerYoudata extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://youdata.qlarius.com/users/sign_in");

        if (!$this->http->ParseForm("new_user")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("user[username]", $this->AccountFields['Login']);
        $this->http->SetInputValue("user[password]", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("
                //h1[contains(text(), 'Service Temporarily Unavailable')]
                | //title[contains(text(), 'Offline for Maintenance')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindNodes("//a[contains(@href, 'sign_out')]/@href")) {
            return true;
        }

        //# Invalid login or password
        if ($message = implode(' ', $this->http->FindNodes("//div[contains(@class, 'alert-error')]/text()"))) {
            if (strstr($message, 'Invalid MeFile ID or password.')) {
                throw new CheckException("Invalid MeFile ID or password. If you created your account before 5/1/2013, you will need to reset your password. Your MeFile and all its previous data is still here waiting. Welcome to Version 2.0", ACCOUNT_INVALID_PASSWORD);
            } else {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Username
        $this->SetProperty("Name", $this->http->FindSingleNode("//li[contains(@class, 'non-link-menu-item')]//strong"));
        //# Active Since
        $this->SetProperty("ActiveSince", $this->http->FindSingleNode("//td[strong[contains(text(), 'Active Since:')]]/following-sibling::td[1]"));
        //# Offers
        $this->SetProperty("CurrentOffers", $this->http->FindSingleNode("//td[strong[contains(text(), 'Offers:')]]/following-sibling::td[1]"));
        // Stash (Cashable)
        $this->SetProperty("Payable", $this->http->FindSingleNode("//td[strong[contains(text(), 'Stash (Cashable):')]]/following-sibling::td[1]"));
        // Balance - Stash
        $this->SetBalance($this->http->FindSingleNode("//td[strong[contains(text(), 'Stash:')]]/following-sibling::td[1]"));
    }
}
