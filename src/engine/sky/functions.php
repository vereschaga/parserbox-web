<?php

class TAccountCheckerSky extends TAccountCheckerExtended
{
    /**
     * @var HttpBrowser
     */
    public $http;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.sky.com.br/vivasky/meu-viva-sky/extrato-de-pontos');

        if (!$this->http->ParseForm("form1")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$txtLogin', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$txtSenha', $this->AccountFields['Pass']);
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$btnLogar', 'Entrar');

        return true;
    }

    public function checkErrors()
    {
        return true;

        if ($message = $this->http->FindPreg("#Login\s+ou\s+senha\s+inválidos#ims")) {
            throw new CheckException("Sign in was not successful", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['PreloadAsImages'] = true;
        $arg['SuccessURL'] = "https://www.sky.com.br/vivasky/meu-viva-sky/extrato-de-pontos";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        // redirect does not work without js
        $this->http->GetURL("https://www.sky.com.br/vivasky/meu-viva-sky/extrato-de-pontos");

        if (!$this->http->FindSingleNode("//*[contains(text(), 'ESCOLHA A FORMA DE VISUALIZAR SEU EXTRATO:')]")) {
            return $this->checkErrors();
        }

        return true;

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Get accaunt data. Json with post
        if (!($text = $this->getXml("https://www.sky.com.br/vivasky/Autenticacao/VerificarAutenticacao"))) {
            return false;
        }
        // and hare
        if (!($text2 = $this->getXml("https://www.sky.com.br/vivasky/VivaSky/ObterMeusPontos"))) {
            return false;
        }

        // VOCÊ POSSUI 15811 PONTOS
        $this->SetBalance(re("#<b:QtdePontos>(\d+)</b:QtdePontos>#", $text));

        // Olá, DOUGLAS FERREIRA LEITE DE ANDRADE,
        $this->SetProperty("Name", beautifulName(re("#<b:Nome>(.*?)</b:Nome>#", $text)));

        // SUA MÉDIA DE FATURA SKY É DE: R$ 453  - Sky Balance
        $this->SetProperty("SkyBalance", re("#<a:TicketMedio>(\d+)</a:TicketMedio>#", $text2));

        preg_match_all("#<a:PontosExpirar\d+dias>(\d+)</a:PontosExpirar\d+dias>#", $text2, $m, PREG_PATTERN_ORDER);

        // exp. date notifiction
        if (isset($m[1])) {
            foreach ($m[1] as $d) {
                if ($d > 0) {
                    TAccountChecker::sendNotification("reason: account have exp. date; code: sky; refs: 5731");

                    break;
                }
            }
        }
    }

    public function getXml($url)
    {
        $http = clone $this->http;
        // Set content type application/json
        $http->setDefaultHeader('Content-Type', 'application/json');
        // Set origin
        $http->setDefaultHeader('Origin', 'https://www.sky.com.br');

        if (!$http->PostURL($url, "{}")) {
            return false;
        }

        return $http->Response['body'];
    }
}
