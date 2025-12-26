<?php

// Feature #5787

class TAccountCheckerRewardscom extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.rewards.com/flyout_signin");

        if (!$this->http->ParseForm("login")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('j_username', $this->AccountFields['Login']);
        $this->http->SetInputValue('j_password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('button', "Sign In");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->Response['code'] == 404) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindPreg("/(Invalid Username or Password)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Successful login
        if (!$this->http->ParseForm("login")) {
            $this->http->GetURL("https://www.rewards.com/myRewards");
        }

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.rewards.com/myBalances');
        // Balance - Total Rewards Cash Balance
        $this->SetBalance($this->http->FindSingleNode("//text()[contains(.,'Total Rewards Cash Balance:')]/following-sibling::span"));
        // Member Number
        $this->SetProperty("MemberNumber", $this->http->FindSingleNode("//text()[contains(.,'Member Number:')]/following-sibling::span[1]"));
        // Rewards Cash
        $this->SetProperty("RWRDBalance", $this->http->FindSingleNode("//table[@id='tablePreview']//td[normalize-space(text())='Rewards Cash']/following-sibling::td[1]"));
        // DASH
        $this->SetProperty("DASHBalance", $this->http->FindSingleNode("//table[@id='tablePreview']//td[normalize-space(text())='DASH'][2]/following-sibling::td[1]"));
        // ETH
        $this->SetProperty("ETHBalance", $this->http->FindSingleNode("//table[@id='tablePreview']//td[normalize-space(text())='Ethereum']/following-sibling::td[1]"));
        // BTC
        $this->SetProperty("BTCBalance", $this->http->FindSingleNode("//table[@id='tablePreview']//td[normalize-space(text())='Bitcoin']/following-sibling::td[1]"));
        // USD
        $this->SetProperty("USDBalance", $this->http->FindSingleNode("//table[@id='tablePreview']//td[normalize-space(text())='USD'][2]/following-sibling::td[1]"));

        $this->http->GetURL('https://www.rewards.com/myInfo');
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h3[contains(text(),'Hi,')]/b")));
    }
}
