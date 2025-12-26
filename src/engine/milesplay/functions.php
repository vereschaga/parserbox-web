<?php
/**
 * Class TAccountCheckerMilesplay
 * Display name: dbName
 * Database ID: dbID
 * Author: MTomilov
 * Created: 04.06.2015 13:40.
 */
class TAccountCheckerMilesplay extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.milesplay.com/en/etihad/Home");

        if (!$this->http->ParseForm("loginform")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("UserName", $this->AccountFields['Login']);
        $this->http->SetInputValue("Password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("login-submit", 'Login');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Thank you for visiting MilesPlay.
        // We are upgrading our site and look forward to welcoming you back soon.
        // In the interim we invite you to CLICK HERE to visit our new site at gamemiles.com
        if ($message = $this->http->FindSingleNode("//p[contains(., 'We are upgrading our site and look forward to welcoming you back soon.')]")) {
            throw new CheckException('We are upgrading our site and look forward to welcoming you back soon.', ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.milesplay.com";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logoff')]/@href)[1]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//*[@class = 'validation-summary-errors']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Game Points
        $this->SetBalance($this->http->FindSingleNode("//*[@id = 'spanBalance']"));

        $this->http->GetURL('http://www.milesplay.com/en/milesplay/Account/myaccount');

        // Name
        $first = $this->http->FindSingleNode('//*[@id = "FirstName"]/@value');
        $last = $this->http->FindSingleNode('//*[@id = "LastName"]/@value');
        $name = sprintf('%s %s', $first, $last);
        $this->http->Log(sprintf('[DEBUG] name: %s', $name));
        $this->SetProperty('Name', beautifulName($name));
    }
}
