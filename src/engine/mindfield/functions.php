<?php

class TAccountCheckerMindfield extends TAccountChecker
{
    public function SetFormParam($a, $b)
    {
        if (isset($this->http->Form[$a])) {
            if ($this->http->Form[$a] == $b) {
                $this->http->Log("Param[$a]:same[$b]");
            } else {
                $this->http->Log("Param[$a]:[{$this->http->Form[$a]}]=>[$b]");
            }
        }
        $this->http->Form[$a] = $b;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://mindfieldonline.com/user/login'); //[text/html; charset=utf-8]

        if (!$this->http->ParseForm('login')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('username', $this->AccountFields["Login"]);
        $this->http->SetInputValue('password', $this->AccountFields["Pass"]);
        $this->http->FormURL = 'https://mindfieldonline.com/user/login';

        return true;
    }

    public function checkErrors()
    {
        // maintenance
        if ($message = $this->http->FindPreg("/MindFieldOnline.com is currently offline for maintenance and upgrades\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // provider error
        if ($this->http->Response['code'] == 500
                || $this->http->FindPreg("/all to a member function quote\(\) on a non-object in/")
                || $this->http->FindPreg('/Could not resolve host/', false, $this->http->Response['errorMessage'])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if (($message = $this->http->FindSingleNode('//b[contains(text(),"Invalid username or password")]'))
            || ($message = $this->http->FindSingleNode('//b[contains(text(),"Invalid email address or password")]'))) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//b[text() = "Account has not been activated."]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (
            $this->http->FindPreg("#/user/accountupdate#", false, $this->http->currentUrl())
            && $this->http->FindSingleNode('//h3[contains(text(), "Update Account Security")]')
        ) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->SetBody($this->http->Response['body'], true);
        //# Name
        $this->SetProperty('Name', $this->http->FindSingleNode('//div[contains(text(), "Welcome back,")]', null, true, '/Welcome back, (.*)!/'));
        //# Member Since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode("//span[contains(text(), 'Member Since')]/following-sibling::span[1]"));
        //# Surveys Credited
        $this->SetProperty('SurveysCredited', $this->http->FindSingleNode("//span[contains(text(), 'Surveys Credited')]/following-sibling::span[1]"));
        //# Current Balance
        $this->SetBalance($this->http->FindSingleNode("//div[contains(text(), 'Current Balance:')]", null, true, self::BALANCE_REGEXP));
        //# Earnings to Date
        $this->SetProperty('EarningstoDate', $this->http->FindSingleNode("//span[contains(text(), 'Earnings to Date')]/following-sibling::span[1]"));
        //# Cashout Threshold
        $this->SetProperty('CashoutThreshold', $this->http->FindPreg("/Cashout Threshold:\s*([^<]+)/ims"));

        //# Get Full Name
        if (
            $this->http->currentUrl() != "https://mindfieldonline.com/user/preferences"
            && $this->http->currentUrl() != 'https://mindfieldonline.com/user/accountupdate'
        ) {
            $this->http->GetURL("https://mindfieldonline.com/user/preferences");
        }
        $name = CleanXMLValue($this->http->FindSingleNode("//input[@name = 'firstname']/@value")
            . ' ' . $this->http->FindSingleNode("//input[@name = 'lastname']/@value"));

        if (strlen($name) == 0) {
            $name = $this->http->FindSingleNode('//font[contains(text(), "Welcome Back, ")]', null, true, '/Welcome back, ([^!]*)/ims');
        }

        if (strlen($name) > 2) {
            $this->SetProperty('Name', beautifulName($name));
        }

        // TODO:
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            //# Current Balance
            $this->SetBalance($this->http->FindPreg("/Current Balance:\s*([^<]+)/ims"));
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }
}
