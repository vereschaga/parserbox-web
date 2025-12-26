<?php

class TAccountCheckerRewardtv extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.rewardtv.com/account/details.sdo");
        /*
         * IMPORTANT ANNOUNCEMENT FROM REWARDTV
         *
         * After 14 years of providing fun TV trivia, RewardTV has closed.
         * We would like to thank all our members for their participation on RewardTV.
         *
         * Thanks for playing!
         */
        if ($this->http->FindPreg("/<p>After 14 years of providing fun TV trivia, <b>RewardTV has closed/")) {
            throw new CheckException("After 14 years of providing fun TV trivia, RewardTV has closed. We would like to thank all our members for their participation on RewardTV. Thanks for playing!", ACCOUNT_PROVIDER_ERROR);
        }

        if (!$this->http->ParseForm("standardLoginForm")) {
            return false;
        }
        $this->http->Form = [];
//        $this->http->SetInputValue("userName", $this->AccountFields['Login']);
//        $this->http->SetInputValue("initialPassword", $this->AccountFields['Pass']);
//        $this->http->SetInputValue("password", '');
//        $this->http->SetInputValue("submitSignIn.x", '27');
//        $this->http->SetInputValue("submitSignIn.y", '10');
        $this->http->SetFormText("userName=" . $this->AccountFields['Login'] . "&initialPassword=Password&password=" . $this->AccountFields['Pass'] . "&submitSignIn.x=51&submitSignIn.y=5", "&");

        return true;
    }

    public function checkErrors()
    {
        // maintenance
        if ($this->http->FindSingleNode("//img[@src = '/images/play/please_wait_animated_both.gif']/@src")) {
            throw new CheckException("Thanks for visiting RewardTV. We will be making some scheduled maintenance enhancements to our site.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        $t = json_decode($this->http->Response["body"]);

        if (isset($t->response->status) && ($t->response->status != 'success')) {
            if (isset($t->response->content->errors[0]->errorMessage)) {
                $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
                $this->ErrorMessage = $t->response->content->errors[0]->errorMessage;
            }// if (isset($t->response->content->errors[0]->errorMessage))
        }// if (isset($t->response->status) && ($t->response->status != 'success'))

        $this->http->GetURL('http://www.rewardtv.com/account/details.sdo');

        if (!$this->http->ParseForm("loginForm")) {
            return false;
        }
        $this->http->Form = [];
        $this->http->SetFormText("securePageLogin=true&userName=" . $this->AccountFields['Login'] . "&password=" . $this->AccountFields['Pass'] . "&x=10&y=5", "&");

        if (!$this->http->PostForm()) {
            return false;
        }
        $this->http->GetURL('http://www.rewardtv.com/account/details.sdo');

        if ($this->http->FindSingleNode("//a[contains(text(), 'Sign Out')]")) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Points Available
        $this->SetBalance($this->http->FindSingleNode("//h3[contains(text(), 'Points Available:')]/parent::div[1]", null, true, '/:\s*([^<]+)/ims'));
        // Points Earned Today
        $this->SetProperty("EarnedToday", $this->http->FindSingleNode("//h3[contains(text(), 'Points Earned Today:')]/parent::div[1]", null, true, '/:\s*([^<]+)/ims'));
        // Points Held for Auction
        $this->SetProperty("HeldForAuction", $this->http->FindSingleNode("//h3[contains(text(), 'Points Held for Auction:')]/parent::div[1]", null, true, '/:\s*([^<]+)/ims'));
        // Name
        $this->http->GetURL('http://www.rewardtv.com/account/profile.sdo');
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[@name = 'firstName']/@value") . ' ' . $this->http->FindSingleNode("//input[@name = 'lastName']/@value")));
    }
}
