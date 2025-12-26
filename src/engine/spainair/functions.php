<?php

class TAccountCheckerSpainair extends TAccountChecker
{
    public function LoadLoginForm()
    {
        //# Invalid email or password
        if (!strpos($this->AccountFields['Login'], '@')) {
            throw new CheckException("Invalid email or password", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        //$this->ShowLogs = true;
        $this->http->getURL('http://www.spanair.com/es_pos/es_ES/Spanairstar');

        if (!$this->http->ParseForm('formLogin')) {
            $this->checkForUnavailable();

            return false;
        }

        //$this->http->FormURL = "http://www.spanair.com/es_pos/es_Es/SpanairStar/Account/LogOn";
        $this->http->Form['Email'] = $this->AccountFields['Login'];
        //$this->http->Form['Password'] =  $this->AccountFields['Pass'];
        $this->http->SetInputValue("Password", $this->AccountFields['Pass']);
        $this->http->Form['RecordarDatos'] = 'false';

        return true;
    }

    public function checkForUnavailable()
    {
        $this->http->FindPreg("/Server Error/ims");
        $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
        $this->ErrorMessage = "Site is temporarily down. Please try to access it later.";

        return false;
    }

    public function Login()
    {
        //$this->http->_maxRedirects = 0;
        $this->http->PostForm();

        //# TEMP
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Introduce el email con el que quieres identificarte y recibir las comunicaciones del Programa')]")) {
            throw new CheckException("Please, update your account", ACCOUNT_PROVIDER_ERROR);
        }

        //# Validation error
        if ($message = $this->http->FindSingleNode("//span[contains(@class, 'validation-error')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        //$this->http->getURL('http://www.spanair.com/es_pos/es_Es/SpanairStar/');
        //# Alternative Balance
        $this->SetBalance($this->http->FindSingleNode("//li[@class = 'usuLog']/span/strong/label"));

        $this->http->getURL('http://www.spanair.com/es_pos/es_Es/SpanairStar/Movement/BalanceAndMovements');
        //# Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'LogOff')]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(),"HTTP 500")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg('/user or invalid password/ims')) {
            throw new CheckException("Inexistent user or invalid password", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//script[contains(text(),"alert(\'You must enter the")]', null, true, "/alert\('([^']+)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        //$this->http->getURL('http://www.spanair.com/es_pos/es_Es/SpanairStar/Movement/BalanceAndMovements');
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[@class = "titul"]')));
        //# Account Number
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//strong[@class =  'colorTar']", null, true, "/(\d+)/ims"));
        //# Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//strong[@class =  'colorTar']", null, true, "/([a-zA-Z]+)/ims"));

        //# Balance
        $this->SetBalance($this->http->FindSingleNode("//table[@class = 'resuPuntos']/thead/tr/th[2]"));
        //# Star Points
        $this->SetProperty("StarPoints", $this->http->FindSingleNode("//table[@class = 'resuPuntos']/tbody/tr[3]/td[2]"));
        //# Star Flights
        $this->SetProperty("StarFlights", $this->http->FindSingleNode("//table[@class = 'resuPuntos']/tbody/tr[4]/td[2]"));
        //# Account status as of
        //$this->SetProperty("StatusAsOf",$this->http->FindSingleNode("//td[@class = 'cabecBody']/span"));
        //# Points Transferred
        $this->SetProperty("Transferred", $this->http->FindSingleNode("//table[@class = 'resuPuntos']/tbody/tr[1]/td[2]"));
        //# Points Expiring
        $expPoints = $this->http->FindSingleNode("//table[@class = 'resuPuntos']/tbody/tr[5]/td[2]");

        if ($expPoints != '0') {
            $this->SetProperty("ExpiringPoints", $expPoints);
            //# Points expire at
            $exp = $this->http->FindSingleNode("//tr[@class = 'puntCadu']/td[1]/span");
            $exp = str_replace("/", ".", $exp);
            $this->http->Log(var_export($exp, true), true);

            if ($exp = strtotime($exp)) {
                $this->SetExpirationDate($exp);
            }
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["SuccessURL"] = "http://www.spanair.com/es_pos/es_Es/SpanairStar/Movement/BalanceAndMovements";

        return $arg;
    }
}
