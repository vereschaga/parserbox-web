<?php
class TAccountCheckerRecompensas extends TAccountChecker
{
    public $regionOptions = [
        ""  => "Select your login type",
        "U" => "Username",
        "A" => "Account number",
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://esfera.brasilct.com/login.jsp");

        if (!$this->http->ParseForm("LoginForm")) {
            return false;
        }
        $this->http->SetInputValue("user_id", $this->AccountFields['Login']);
        $this->http->SetInputValue("login_type", $this->AccountFields['Login2']);
        $this->http->SetInputValue("user_pwd", $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.santander.com.mx/NuevaVersion/index.html";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Error message')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        // Balance -
        $this->SetBalance($this->http->FindSingleNode("//li[contains(@id, 'balance')]"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//li[contains(@id, 'name')]")));
    }

    protected function checkRegionSelection($region)
    {
        if (empty($region) || !in_array($region, array_flip($this->regionOptions))) {
            $region = 'U';
        }

        return $region;
    }
}
