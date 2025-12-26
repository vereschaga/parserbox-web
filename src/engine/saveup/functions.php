<?php

class TAccountCheckerSaveup extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.saveup.com");

        if (!$this->http->ParseForm(null, 1, true, '//form[@action="/users/sign_in"]')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("user[email]", $this->AccountFields['Login']);
        $this->http->SetInputValue("user[password]", $this->AccountFields['Pass']);
        $this->http->SetInputValue("commit", "Sign In");

        return true;
    }

    public function checkErrors()
    {
        // SaveUp is currently undergoing site enhancements
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'SaveUp is currently undergoing site enhancements')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // An unexpected error occurred.
        if ($this->http->FindSingleNode("//h2[contains(text(), 'An unexpected error occurred.')]")
            // 500 Internal Server Error
            || $this->http->FindPreg("/500 Internal Server Error/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        //# Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, '/users/sign_out')]/@href")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//form[@action='/users/sign_in']/p[contains(@class, 'note')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'body']/a[contains(@href, '/users')]")));
        // plays
        $this->SetProperty("Plays", $this->http->FindSingleNode("//b[contains(@class, 'plays')]"));
        // level
        $this->SetProperty("Level", $this->http->FindSingleNode("//b[contains(@class, 'level')]"));

        // credits
        $this->SetBalance($this->http->FindSingleNode("//b[contains(@class, 'credits')]"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //		$arg["SuccessURL"] = "https://www.saveup.com/";
        return $arg;
    }
}
