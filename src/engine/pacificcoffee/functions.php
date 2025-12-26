<?php

class TAccountCheckerPacificcoffee extends TAccountChecker
{
    public $regionOptions = [
        ""      => "Select your login type",
        "Login" => "Login ID",
        "Card"  => "Card No.",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams();

        return $arg;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.cardadministration.com/trDetail.jsp', [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.cardadministration.com/login.jsp?mID=PCC');

        if (!$this->http->ParseForm('login_form')) {
            return false;
        }

        if ($this->AccountFields['Login2'] == 'Card') {
            $this->http->SetInputValue('txt_master_card_no', $this->AccountFields['Login']);
            $login = str_split($this->AccountFields['Login'], 4);

            for ($i = 0; $i < count($login); $i++) {
                $this->http->SetInputValue("txt_master_card_no" . ($i + 1), $login[$i]);
            }
        } else {
            $this->http->SetInputValue('txt_otherId', $this->AccountFields['Login']);
        }
        $this->http->SetInputValue('requestLogin', 'true');
        $this->http->SetInputValue('txt_pwd', $this->AccountFields['Pass']);
        $this->http->SetInputValue('loginBtn', 'Login');

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // Incorrect User ID or Password.
        if ($this->http->FindSingleNode("//div[contains(text(),'Incorrect User ID or Password.')]")) {
            throw new CheckException('Incorrect User ID or Password.', ACCOUNT_INVALID_PASSWORD);
        }
        // Incorrect Card No. or PIN
        if ($this->http->FindSingleNode("//div[contains(text(),'Incorrect Card No. or PIN')]")) {
            throw new CheckException('Incorrect Card No. or PIN', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode("//div[contains(text(), 'Login is not allowed for unregistered card.')]")) {
            throw new CheckException('Login is not allowed for unregistered card.', ACCOUNT_INVALID_PASSWORD);
        }
        // Incorrect Login ID or Password.
        if ($this->http->FindSingleNode("//div[contains(text(),'Incorrect Login ID or Password.')]")) {
            throw new CheckException('Incorrect Login ID or Password.', ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Point Balance
        $this->SetBalance($this->http->FindSingleNode("//div[contains(text(),'Point Balance')]/following-sibling::div"));
        // Card Balance
        $this->SetProperty('TotalCardBalance', $this->http->FindSingleNode("//div[contains(text(),'Card Balance')]/following-sibling::div"));
        // Card Expiry Date
        $this->SetProperty('CardExpiryDate', $this->http->FindSingleNode("//div[contains(text(),'Card Expiry Date')]/following-sibling::div"));
        // MemberSince - Registration Date
        $this->SetProperty('MemberSince', $this->http->FindSingleNode("//div[contains(text(),'Registration Date')]/following-sibling::div"));
        // Expiry Date
        $expNodes = $this->http->XPath->query("//tr[td[contains(text(), \"Expiry Date\")]]/ancestor::table[1]//tr[@id and td[2]]");
        $this->logger->debug("Total {$expNodes->length} exp date were found");

        foreach ($expNodes as $expNode) {
            $points = $this->http->FindSingleNode("td[1]", $expNode);
            $date = $this->http->FindSingleNode("td[2]", $expNode);

            if (!isset($exp) || $exp > strtotime($date)) {
                $this->SetProperty('ExpiringBalance', $points);
                $exp = strtotime($date);
                $this->SetExpirationDate($exp);
            }// if (!isset($exp) || $exp > strtotime($date))
        }// foreach ($expNodes as $expNode)

        $this->http->GetURL('https://www.cardadministration.com/change_details.jsp');
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//input[@id='txt_family_name']/@value") . ' '
            . $this->http->FindSingleNode("//input[@id='txt_given_name']/@value")));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//input[@name = 'requestLogout' and @value = 'true']/@value")) {
            return true;
        }

        return false;
    }
}
