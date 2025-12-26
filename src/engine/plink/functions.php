<?php

class TAccountCheckerPlink extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://points.plink.com/account");

        if (!$this->http->ParseForm("organic-sign-in-form")) {
            return $this->checkErrors();
        }
        $this->http->setDefaultHeader("X-CSRF-Token", $this->http->Form['authenticity_token']);
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
        $this->http->Form = [];
        $this->http->SetInputValue("user_session[email]", $this->AccountFields['Login']);
        $this->http->SetInputValue("user_session[password]", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        // Server Error
        if ($message = $this->http->FindPreg("/(The server encountered an internal error and was unable to complete your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // With much sadness and heavy hearts, we are sorry to tell you that Plink is closing its doors
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'With much sadness and heavy hearts, we are sorry to tell you that Plink is closing its doors')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.plink.com/";
        $arg["SuccessURL"] = "https://www.plink.com/";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $response = json_decode($this->http->Response["body"]);

        if (isset($response->redirect_path)) {
            $this->http->NormalizeURL($response->redirect_path);
            $this->http->GetURL($response->redirect_path);

            if ($this->http->FindSingleNode("//a[contains(text(), 'Log Out')]")) {
                return true;
            }
        }
        // Sorry, the email and password do not match for this account.
        if ($message = $this->http->FindPreg("/Sorry, the email and password do not match for this account\./ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://points.plink.com/account");
        // Balance - Plink Point
        $this->SetBalance($this->http->FindPreg("/You have ([\d]+) Plink Point/ims"));
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//h2[@class = 'bold']"));
        // Lifetime Plink Points
        $this->SetProperty("LifetimeRewards", $this->http->FindSingleNode("//div[h2[contains(text(), 'Lifetime Stats')]]/following-sibling::div/h1"));
    }
}
