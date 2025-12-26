<?php

class TAccountCheckerVirginflyingco extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://flyingcoportal.virginatlantic.com/Account/Login');

        if (!$this->http->ParseForm(null, 1, true, '//form[@action = "/Account/Login"]')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('FlyingCodeId', $this->AccountFields['Login']);
        $this->http->SetInputValue('EmailAddress', $this->AccountFields['Login2']);
        $this->http->SetInputValue('Password', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindPreg("/(We are doing a quick upgrade and will have the site up and running for you again as fast as we can\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("(//a[contains(@href, '/Home/LogOff')]/@href)[1]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//*[@id='errMsg' and contains(text(),'The Company Id, email address or password provided is incorrect.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your Password has expired
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Your Password has expired')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Balance:
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'Balance:')]", null, false, '/:\s*(.+?)\s*miles/'));
        // CompanyName - Company:
        $this->SetProperty('CompanyName', $this->http->FindSingleNode("//p[contains(text(), 'Company:')]", null, false, '/:\s*(.+)/'));
        // DateSummary - Date of Summary
        $this->SetProperty('DateSummary', $this->http->FindSingleNode("//p[contains(text(), 'Date of Summary:')]", null, false, '/:\s*(.+)/'));
    }
}
