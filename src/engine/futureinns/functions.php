<?php

class TAccountCheckerFutureinns extends TAccountChecker
{
    private const REWARDS_PAGE_URL = "https://www.futureinns.co.uk/rewards/club/";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->FilterHTML = false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if (!$this->http->ParseForm("login")) {
            return false;
        }

        $this->http->FormURL = self::REWARDS_PAGE_URL;
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
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

        // Invalid login or password
        if ($message = $this->http->FindPreg("/Email Address and password combination do not match\!/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // Balance - Points Total
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'Points Total')]/following-sibling::h5"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//div[contains(@class, 'author')]/h5[1]")));
        // Points Earned
        $this->SetProperty('PointsEarned', $this->http->FindSingleNode("//p[contains(text(), 'Points Earned')]/following-sibling::h5"));
        // Points Redeemed
        $this->SetProperty('PointsRedeemed', $this->http->FindSingleNode("//p[contains(text(), 'Points Redeemed')]/following-sibling::h5"));
    }
}
