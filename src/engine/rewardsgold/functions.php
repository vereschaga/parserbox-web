<?php

/**
 * Class TAccountCheckerRewardsgold
 * Display name: RewardsGold
 * Database ID: 690
 * Author: AKolomiytsev
 * Created: 14.10.2014 4:56.
 */
class TAccountCheckerRewardsgold extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->ParseMetaRedirects = false;
        $this->http->removeCookies();
        $this->http->GetURL("http://www.rewardsgold.com/members/login.php");

        if (!$this->http->ParseForm("theForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("email_id", $this->AccountFields['Login']);
        $this->http->SetInputValue("pwd", $this->AccountFields['Pass']);
        $this->http->SetInputValue("login", "on");
        $this->http->SetInputValue("x", "83");
        $this->http->SetInputValue("y", "36");

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.rewardsgold.com";

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Something awesome is coming soon!
        $this->http->GetURL("http://www.rewardsgold.com/reward/index.html");

        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Something awesome is coming soon!')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("//a[@href='logout.php']")) {
            return true;
        }

        if ($message = $this->http->FindPreg("/Invalid Email or Password.  Please try again./")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance
        $balance = $this->http->FindSingleNode("//div/text()[contains(.,'BALANCE')]", null, true, "/[0-9]+/");
        $this->SetBalance($balance);

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//td[@width='80%']")));
    }
}
