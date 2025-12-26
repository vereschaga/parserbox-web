<?php

class TAccountCheckerPrincehotels extends TAccountChecker
{
    private const REWARDS_PAGE_URL = "https://www.princepreferred.com/AccountActivity.aspx";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.princepreferred.com/Login.aspx");
        // our form doesn't have action
        if (!$this->http->ParseForm("Form1")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('ctl00$ctl00$contentplaceholder_parts$contentplaceholder_interior_body$txtUsername', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$ctl00$contentplaceholder_parts$contentplaceholder_interior_body$txtPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('ctl00$ctl00$contentplaceholder_parts$contentplaceholder_interior_body$btnSubmit', 'Login');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Provider error
        if ($this->http->currentUrl() == 'https://www.princepreferred.com/Error.aspx') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm([], 120)) {
            if ($this->http->FindPreg("/Unable to connect to CRM Organization Service at CRMProxyWCF.CRMProxySVC/")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }

        // Invalid login or password
        if ($message = $this->http->FindSingleNode("//h1[contains(text(),'Invalid Account or Password')]")) {
            throw new CheckException(trim($message), ACCOUNT_INVALID_PASSWORD);
        }
        // The user trying to login is '...'. You could not be logged in with that username and password.
        if ($message = $this->http->FindSingleNode('//div[@id = "contentplaceholder_parts_contentplaceholder_interior_body_pnlLoginAlert" and contains(., "The user trying to login is \'' . $this->AccountFields['Login'] . '\'. You could not be logged in with that username and password.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // provider error
        if ($this->http->FindPreg("/(Issues with fetchXml for neuguest_loyaltyprogram entity; Current Key:\s*at CRMProxyWCF.CRMProxySVC\.ExecutePagedFetch\(String entity,|Unable to connect to CRM Organization Service\s+at CRMProxyWCF|Unable to connect to CRM Discovery Service:\s*Data\[0\] = \"The provided uri did not return any Service Endpoints!)/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Total Available Program Points (Balance *)
        $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Total Available Program Points')]/../following-sibling::td"));
        // 1. Name (Name *)
        $this->SetProperty('Name', $this->http->FindSingleNode("//span[@id='contentplaceholder_parts_contentplaceholder_interior_body_lblMemberName']"));
        // 2. Current Membership Level (Status *)
        $this->SetProperty('MembershipLevel', $this->http->FindSingleNode("//span[contains(text(), 'Current Membership Level')]/../following-sibling::td"));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@href, 'Logout')]/@href")) {
            return true;
        }

        return false;
    }
}
