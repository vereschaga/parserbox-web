<?php

class TAccountCheckerLawrys extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->Log('form finder');
        // reset cookie
        $this->http->removeCookies();
        //$this->ShowLogs = true;
        $this->http->getURL('http://pow.custcon.com/wry/pow_default.asp');

        if (!$this->http->ParseForm('frmPOW')) {
            return false;
        }
        $this->http->SetInputValue('memberNumber', $this->AccountFields['Login']);
        $this->http->SetInputValue('memberName', $this->AccountFields['Pass']);
        $this->http->Form['Submit'] = 'Check My Points';
        $this->http->Form['formAction'] = 'CheckPoints';

        $clientCode = $this->http->FindSingleNode('//script[@language="javascript"][3]', null, true, '/var clientID\s+=\s+"([^"]*)"/ims');
        $this->http->Form['clientCode'] = $clientCode;
        $this->http->Form['postUrl'] = 'http://10.1.1.152/pow.asmx';

        return true;
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindPreg("/Error Encountered Processing Request/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The 'ReportStats' start tag on line '1' does not match the end tag of 'Status'. Line 1, position 66.
        if ($message = $this->http->FindPreg("/The 'ReportStats' start tag on line \'1\' does not match the end tag of \'Status\'\. Line 1, position 66\./ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode('//b[contains(text(), "Membership Number")]')) {
            return true;
        }

        if ($message = $this->http->FindPreg('/Error Encountered:\s+([^<]*)/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // set Balance
        $this->SetBalance($this->http->FindSingleNode('//tr[td[b[contains(text(), "Point Balance")]]]/following-sibling::tr[1]/td[2]'));
        // set Membership number
        $this->SetProperty('MembershipNumber', $this->http->FindSingleNode('//tr[td[b[contains(text(), "Membership Number")]]]/following-sibling::tr[1]/td[1]'));
        // set Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//tr[td[b[contains(text(), "Card Holder Name")]]]/following-sibling::tr[1]/td[1]')));
        // set Last Activity Date
        $this->SetProperty('LastActivity', $this->http->FindSingleNode('//tr[td[b[contains(text(), "Last Activity Date")]]]/following-sibling::tr[1]/td[2]'));
    }
}
