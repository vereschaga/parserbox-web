<?php
class TAccountCheckerItau extends TAccountChecker
{
    /**
     * @var HttpBrowser
     */
    public $http;

//    function TuneFormFields(&$arFields, $values = NULL) {
//        parent::TuneFormFields($arFields);
//        $arFields["Login2"]["Options"] = array(
//            ""                 => "Select your account type",
//            "AgencyAndAccount" => "Agency and Account",
//            "CreditCard"       => "Credit Card",
//        );
//    }

    public static function GetAccountChecker($accountInfo)
    {
//        if ($accountInfo['Login2'] == 'AgencyAndAccount') {
        require_once __DIR__ . "/TAccountCheckerItauSelenium.php";

        return new TAccountCheckerItauSelenium();
//        }
//        else
//            return new static();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.itau.com.br/conta-corrente/");

        switch ($this->AccountFields['Login2']) {
            case 'CreditCard':
                if (!$this->http->ParseForm("banklinecartao")) {
                    return false;
                }
                $this->http->SetInputValue("cartao", $this->AccountFields['Login']);

                if (!$this->http->PostForm()) {
                    return false;
                }

//                $this->http->SetInputValue("conta", $this->AccountFields['Pass']);
//                $this->http->SetInputValue("dac", '4');
//                unset($this->http->Form['banklineAgConta']);
                break;

            default:
                if (!$this->http->ParseForm("banklineAgConta")) {
                    return false;
                }

                $this->AccountFields['Login'] = preg_replace('/\D/ims', '', $this->AccountFields['Login']);

                $this->http->SetInputValue("agencia", substr($this->AccountFields['Login'], 0, 4));
                $this->http->SetInputValue("conta", substr($this->AccountFields['Login'], 4, 5));
//                $this->http->SetInputValue("conta", $this->AccountFields['Pass']);
                $this->http->SetInputValue("dac", substr($this->AccountFields['Login'], 5, 1));
                unset($this->http->Form['banklineAgConta']);

                if (!$this->http->PostForm()) {
                    return false;
                }

                if (!$this->http->ParseForm("bankline")) {
                    return false;
                }
                $pass = $this->AccountFields['Pass'];
                $value = '';

                for ($i = 0; $i < strlen($pass); $i++) {
                    $v = $this->http->FindSingleNode("//a[img[contains(@title, '{$pass[$i]}')]]/@onclick", null, true, "/click\('([^\']+)/");
                    $value .= $v;
                }
                $this->http->SetInputValue('senha', $value);

                break;
        }

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.itau.com.br/conta-corrente/";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        switch ($this->AccountFields['Login2']) {
            case 'CreditCard':
                break;

            default:
                if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
                    return true;
                }
                // invalid credentials
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Agência/Conta inválida')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->http->FindSingleNode('//td[contains(text(), "Favor preencher o campo \'Agência/Conta\' corretamente.")]')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                break;
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Disponível p/ saque
        $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Disponível p/ saque')]/following-sibling::span[1]"));
        // LIS (suj. encargos)
        $this->SetProperty("Charges", $this->http->FindSingleNode("//span[contains(text(), 'LIS')]/following-sibling::span[1]"));
        // Total p/ saque
        $this->SetProperty("TotalEarned", $this->http->FindSingleNode("//span[contains(text(), 'Total p/ saque')]/following-sibling::span[1]"));
        // Agência
        $numAgencia = implode('', $this->http->FindNodes("//p[contains(@class, 'numAgencia')]/text()"));
        // Conta
        $numConta = implode('', $this->http->FindNodes("//p[contains(@class, 'numConta')]/text()"));

        if (isset($numAgencia, $numConta)) {
            $this->SetProperty("AccountNumber", $numAgencia . "/", $numConta);
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[contains(@class, 'nomeCliente')]")));
    }
}
