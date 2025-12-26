<?php

class TAccountCheckergrandguest extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        //	$this->http->FormURL = "https://www.grandguest.com/s/welcome.asp";
        $this->http->GetURL("http://www.grandliferewards.com/");

        if (!$this->http->ParseForm()) {
            return $this->checkErrors();
        }
        /*
        $this->http->Form['txtUserName'] =  $this->AccountFields['Login'];
        $this->http->Form['txtPassword'] =  $this->AccountFields['Pass'];
        $this->http->Form['submit1'] = 'Login>';
        $this->http->Form['_method'] = '';
        $this->http->Form['_txtUserName_state'] = '_nColumnCount=30&_nMaxLength=80';
        $this->http->Form['_txtPassword_state'] = '_nStyle=2&_nColumnCount=30&_nMaxLength=20';
        $this->http->Form['_thisPage_state'] = '';
        */
        $this->http->Form = [];
        $this->http->FormURL = "http://www.grandliferewards.com/login/index.php";
        $this->http->Form['login'] = $this->AccountFields['Login'];
        $this->http->Form['password'] = $this->AccountFields['Pass'];

        return true;
    }

    public function checkErrors()
    {
        if ($this->http->FindPreg("/Fatal error\s*<?\/?b?>?\s*:\s*SOAP-ERROR\:\s*Parsing\s*WSDL:\s*Couldn\'t load from \'https\:\/\/guestware\.grandliferewards\.com/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // The service is temporarily unavailable.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The service is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($error = $this->http->FindPreg("/Your username and password do not match/ims")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        if ($success = $this->http->FindSingleNode('//a[contains(text(), "LOG OUT")]')) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//em[contains(text(), "Hello")]/following-sibling::node()[1]')));
        // Profile Number
        $this->SetProperty("Number", $this->http->FindSingleNode('//em[contains(text(), "Profile Number:")]/following-sibling::node()[1]', null, true, '/([0-9]+)/ims'));
        //
        //		$this->SetProperty("Status", $this->http->FindSingleNode('//h2[contains(text(), "Rewards Status")]/following::p[1]/a', null, true, '/([A-Za-z]+)\s*Rewards Member/ims'));
        // Visits
        $this->SetProperty("TotalVisits", $this->http->FindSingleNode('//em[contains(text(), "Total Points:")]/following-sibling::node()[1]', null, true, '/([^\,]+)/ims'));
        // Balance - Total Points
        $this->SetBalance($this->http->FindSingleNode('//em[contains(text(), "Total Points:")]/following-sibling::node()[1]', null, true, '/\,\s*([^<]+)/ims'));

//        $this->http->GetURL("http://www.grandliferewards.com/account/rewards/?__utma=92743693.967313453.1412309942.1413450234.1413518774.8&__utmb=92743693.3.9.1413519223203&__utmc=92743693&__utmx=-&__utmz=92743693.1412317201.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none)&__utmv=-&__utmk=45700908");
//        // Visits
//        $this->SetProperty("TotalVisits", $this->http->FindSingleNode('//p[contains(text(), "visit")]', null, true, '/([0-9]+)\s*visit/ims'));
//        // Balance - Total Points
//        $this->SetBalance($this->http->FindSingleNode('//p[contains(text(), "points accumulated")]', null, true, '/([0-9]+)\s*point/ims'));

        /*
        $this->SetProperty("Number",$this->http->FindPreg('/account\s*number\s*is\s*<b>([^<]*)</ims'));
        $this->SetProperty("Name",$this->http->FindPreg('/Hello\s*([^\.]*)/ims'));
        $this->SetProperty("TotalVisits",$this->http->FindPreg('/Total\s*Visits\s*:\s*([^<]*)</ims'));
        $this->SetProperty("TotalNights",$this->http->FindPreg('/Total\s*Nights\s*:\s*([^<]*)</ims'));
        $balance = $this->http->FindPreg('/Total\s*Points\s*:\s*([^<]*)</ims');
        if(empty($balance))
            $balance = 0;
        $this->SetBalance($balance);
        */
    }
}
