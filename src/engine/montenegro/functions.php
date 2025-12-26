<?php

class TAccountCheckerMontenegro extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->ParseForms = false;
        $this->http->removeCookies();
        //Send password in get parametrs ????
        //This is site logic )), last parametr sid = js random
        $this->http->GetURL("http://visionteam.mgx.me/lib/checkCardNoPass.php?cardNo={$this->AccountFields['Login']}&pin={$this->AccountFields['Pass']}&lng=EN&sid=0.5002508598356732");
        //Check errors on response
        if ($this->http->Response['code'] != 200) {
            return false;
        }
        /*
        if ($this->http->Response['body'] != 'connect')
            throw new CheckException(CleanXMLValue($this->http->FindSingleNode('/*')), ACCOUNT_INVALID_PASSWORD);
        */
        //Set post url
        $this->http->FormURL = 'http://visionteam.mgx.me/login.php?lang=en';
        //Set login information
        $this->http->Form['cardNo'] = $this->AccountFields['Login'];
        $this->http->Form['pin'] = $this->AccountFields['Pass'];
        $this->http->Form['Submt'] = 0;

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        //Check that login successful
        if ($this->http->FindSingleNode('//a[contains(@href, "logoff")]')) {
            return true;
        }

        //If error then page contains one paragraph
        /*
        if ($message = $this->http->FindSingleNode('//p[1]'))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        */

        return false;
    }

    public function Parse()
    {
        //ToDo Need to check with other languages
        //Account number
        $this->SetProperty('Number', $this->http->FindSingleNode('//*[contains(text(), "Card No.")]', null, false, '/Card No.\s*(\d+)/i'));
        //Account name, will be error in the future((
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//td[contains(@background, "table_header.jpg")]')));
        //Additional miles
        $this->SetProperty('InitialMiles', $this->http->FindSingleNode('//*[contains(text(), "Initial")]/following::td[1]/input/@value'));
        $this->SetProperty('StatusMiles', $this->http->FindSingleNode('//*[contains(text(), "Status")]/following::td[1]/input/@value'));
        $this->SetProperty('AssociatedMiles', $this->http->FindSingleNode('//*[contains(text(), "Associated")]/following::td[1]/input/@value'));
        $this->SetProperty('BenefitMiles', $this->http->FindSingleNode('//*[contains(text(), "Benefit")]/following::td[1]/input/@value'));
        $this->SetProperty('FamilyMiles', $this->http->FindSingleNode('//*[contains(text(), "Family")]/following::td[1]/input/@value'));
        $this->SetProperty('BuyingMiles', $this->http->FindSingleNode('//*[contains(text(), "Buying")]/following::td[1]/input/@value'));
        $this->SetProperty('GratisMiles', $this->http->FindSingleNode('//*[contains(text(), "Gratis")]/following::td[1]/input/@value'));
        //Balance
        $this->SetBalance($this->http->FindSingleNode('//*[contains(text(), "Total")]/following::td[1]/input/@value'));
        // Status
        $this->SetProperty('Status', $this->http->FindSingleNode('//tr[td[b[contains(text(),"Card")]]]/following-sibling::tr[1]/td/table/tr[2]', null, true, '/(.*)\s+Status/ims'));
    }
}
