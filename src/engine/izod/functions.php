<?php

class TAccountCheckerIzod extends TAccountChecker
{
    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
//        $arg['CookieURL'] = 'https://vanheusen.com/rewards/overview';
        $arg['SuccessURL'] = 'https://vanheusen.partnerbrands.com/PreferredLoyaltyProgramView?currentSelection=preferredProgramSlct&catalogId=15802&langId=-1&storeId=12501';

        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://vanheusen.partnerbrands.com/LogonForm?myAcctMain=1&catalogId=15802&langId=-1&storeId=12501");

        if (!$this->http->ParseForm('Logon')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('logonId', $this->AccountFields['Login']);
        $this->http->SetInputValue('logonPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('myAcctMain', "1");
        $this->http->SetInputValue('rememberMe', "true");
        $this->http->SetInputValue('URL', "RESTOrderCalculate?URL=https://vanheusen.partnerbrands.com/LogonForm?currentSelection=accountSummarySlct&myAcctUpdate=1&catalogId=15802&langId=-1&storeId=12501&calculationUsageId=-1&calculationUsageId=-2&deleteCartCookie=true&page=");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Access is allowed
        if (
            !$this->http->FindSingleNode('//div[@id = "pageLevelMessage"]')
            && $this->http->FindNodes("//a[contains(@href, 'Logoff')]")
        ) {
            return true;
        }
        // Your password has been reset. Please retrieve the temporary password from your e-mail and login again.
        if (
            ($message = $this->http->FindSingleNode('//div[@id = "pageLevelMessage" and contains(., "Your password has been reset. Please retrieve the temporary password")]'))
            || ($message = $this->http->FindSingleNode('//div[@id = "pageLevelMessage" and contains(., "PLEASE RESET YOUR PASSWORD")]'))
            // Error: The email or password you entered does not match our records. Please re-enter your sign in information or create an account.
            || ($message = $this->http->FindSingleNode('//div[@id = "pageLevelMessage" and contains(., "The email or password you entered does not match our records.")]'))
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(text(), 'Name')]/following-sibling::div[1]")));
        // Balance - You Currently have ... Points
        $this->SetBalance($this->http->FindSingleNode("//div[@class = 'rewardsAndPoints']/span[@class = 'strong']"));
        // You are ... points away from your next $10 reward.
        $this->SetProperty("PointsToNextReward", $this->http->FindSingleNode("//div[@id = 'earnPoints']/span[@class = 'strong' and position() = 1]"));
        // $... in rewards available to redeem.
        $this->SetProperty("RewardsBalance", $this->http->FindSingleNode("//div[@class = 'rewardsAndPoints']/a[@class = 'rewardsCertificates']/span[@class = 'strong']"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode('//p[contains(text(), "You have not verified your Rewards Account")]')) {
                $this->SetBalanceNA();
            }

            if ($this->http->FindSingleNode('//p[contains(text(), "You are not currently enrolled")]')) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        }

        $this->http->GetURL("https://vanheusen.partnerbrands.com/UserRegistrationForm?userRegistrationStyle=strong&editRegistration=Y&catalogId=15802&langId=-1&storeId=12501");
        // Member ID
        $this->SetProperty("RewardsCard", $this->http->FindSingleNode("//div[contains(text(), 'Member ID')]/following-sibling::div[1]"));
    }
}
