<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerMultiplus extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->TimeLimit = 350;
        //$this->setProxyBrightData(null, "static", 'br');
        $this->http->SetProxy($this->proxyReCaptcha());
        //$this->http->setRandomUserAgent();
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->LogHeaders = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.pontosmultiplus.com.br/home", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        /*
         * Please check tamair expiration date  // refs #6852
         */
        $this->http->removeCookies();
        $this->http->setMaxRedirects(7);
        $this->http->GetURL("https://www.pontosmultiplus.com.br/login");

        if (!$this->http->ParseForm("login")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("user", $this->AccountFields['Login']);
        $this->http->Form["password"] = $this->AccountFields['Pass'];
//        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $captcha = $this->parseCaptcha();

        if ($captcha) {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }

        $data = [
            "user"                 => $this->AccountFields['Login'],
            "password"             => $this->AccountFields['Pass'],
            "g-recaptcha-response" => $captcha,
            "TARGET"               => "https://www.pontosmultiplus.com.br",
            "CAPTCHA_APRESENTADO"  => "true",
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/json; charset=utf-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->PostURL("https://www.pontosmultiplus.com.br/login/autenticar", json_encode($data), $headers);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.pontosmultiplus.com.br";
        // prevent provider bug "Error 401--Unauthorized"
//        $arg["SuccessURL"] = "https://portal.pontosmultiplus.com.br/portal/pages/home.html";

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg("/Neste momento,\s*o site da <font[^>]+>Multiplus Fidelidade<\/font>\s*est.+ em manuten.+o para melhor atend.+-lo\./ims")) {
            throw new CheckException("Neste momento, o site da Multiplus Fidelidade está em manutenção para melhor atendê-lo.", ACCOUNT_PROVIDER_ERROR);
        }
        // SITE EM MANUTENÇÃO
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'SITE EM MANUTENÇÃO')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Ops, no momento o site está cheio
        if ($message = $this->http->FindSingleNode("//div[span[contains(text(), 'Ops, no momento o site está cheio')]]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Ocorreu uma falha no sistema. Por favor tente novamente.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Ocorreu uma falha no sistema. Por favor tente novamente.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Site temporariamente indisponível
        if ($message = $this->http->FindPreg("/Site temporariamente indisponível/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sistema indispon?vel no momento
        if ($message = $this->http->FindPreg("/Sistema indispon\?vel no momento/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Opa! Nosso site está com muitos #PontoLovers no momento!
        if ($message = $this->http->FindPreg("/Opa! Nosso site está com muitos\s*#PontoLovers no momento!/")) {
            throw new CheckRetryNeededException(1, 5, $message);
        }
        // provider error
        if ($this->http->Response['code'] == 503
            || $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] == 404
            && $this->http->FindSingleNode("//title[contains(text(), '404 Not Found')]")) {
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
        }

        // 404 after authorization
        if ($this->http->Response['code'] == 404
            && $this->http->FindPreg("/<H2>Error 404--Not Found<\/H2>/")
            && $this->http->FindPreg("/From RFC 2068 <i>Hypertext Transfer Protocol -- HTTP\/1\.1<\/i>/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->Response['code'] == 0
            || ($this->http->Response['code'] == 302 && $this->http->currentUrl() == 'https://www.pontosmultiplus.com.br/home')
            || ($this->http->Response['code'] == 401 && $this->http->currentUrl() == 'https://portal.pontosmultiplus.com.br/portal/pages/home.html')) {
            throw new CheckRetryNeededException(3, 10);
        }

        return false;
    }

    public function Login()
    {
//        if (!$this->http->PostForm())
//            return $this->checkErrors();
        $response = $this->http->JsonLog();

        if (isset($response->data)) {
            $redirect = $response->data;
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);
        }// if (isset($response->data))

        // Precisamos que você esteja de acordo com o nosso regulamento.
        // Para continuar, leia o texto até o final e clique em aceitar.
        if ($this->http->FindSingleNode("//strong[text() = 'leia o texto até o final e clique em aceitar']")) {
            $this->logger->notice("Skip accepting new Terms and Conditions");

            if ($later = $this->http->FindSingleNode('//a[@class = "read-later"]/@href')) {
                $this->http->GetURL($later);
            }
        }// if ($this->http->FindSingleNode("//strong[text() = 'leia o texto até o final e clique em aceitar']"))

        // provider bug fix
        if ($this->http->Response['code'] == 403 && strstr($this->http->currentUrl(), 'www.pontosmultiplus.com.br//home?_requestid=')) {
            $this->logger->notice("provider bug fix");
            $this->http->GetURL("https://portal.pontosmultiplus.com.br/portal/pages/home.html");
            // ??
            /*
            if ($this->http->FindNodes("//a[contains(text(), 'Sair') or contains(text(), 'Exit') or contains(text(), 'Salir')]"))
                return true;
            */
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        //  Login e/ou senha de acesso incorretos
        if (isset($response->exception, $response->message) && $response->exception == 'LOGIN_ERRO_NEGOCIO' && $response->message == '') {
            throw new CheckRetryNeededException(3, 5, "Login e/ou senha de acesso incorretos", ACCOUNT_INVALID_PASSWORD);
        }
        // Senha de acesso expirada
        if (isset($response->exception, $response->message) && $response->exception == 'LOGIN_USUARIO_BLOQUEADO' && $response->message == '') {
            throw new CheckException('Por favor cadastre uma nova senha. Agora você acessa os sites da Multiplus e da LATAM com seu cpf e uma única senha de acesso.', ACCOUNT_INVALID_PASSWORD);
        }
        // IDENTIFICAMOS NO SEU CADASTRO QUE, DE ACORDO COM O SEU PAÍS DE RESIDÊNCIA, AGORA SEU PROGRAMA É O LATAM PASS.
        if (isset($response->exception, $response->message) && $response->exception == 'LOGIN_USUARIO_MIGRADO' && $response->message == '') {
            throw new CheckException('IDENTIFICAMOS NO SEU CADASTRO QUE, DE ACORDO COM O SEU PAÍS DE RESIDÊNCIA, AGORA SEU PROGRAMA É O LATAM PASS.', ACCOUNT_PROVIDER_ERROR);
        }

        if (isset($response->exception, $response->message) && $response->exception == 'LOGIN_ERRO_TECNICO' && $response->message == '') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->setMaxRedirects(15);
        $this->http->RetryCount = 0;

        if ($this->http->currentUrl() != "https://www.pontosmultiplus.com.br/portal/") {
            $this->http->GetURL("https://www.pontosmultiplus.com.br/portal/");
        }

        // provider bug fix
        if ($this->http->FindSingleNode("//h2[contains(text(), 'www.pontosmultiplus.com.br')]/following-sibling::p[contains(text(), 'Service Unavailable')]")) {
            sleep(3);
            $this->http->GetURL("https://www.pontosmultiplus.com.br/portal/");
        }

        $this->http->RetryCount = 2;
        // Balance - Saldo Atual
        $this->SetBalance($this->http->FindSingleNode("//strong[@class = 'multiplus-header__points-value']"));
        // Pontos a Vencer
        $this->SetProperty("PointsToExpire", $this->http->FindSingleNode("//span[@id = 'lblPontosVencer']"));
        // Expiration date
        $exp = $this->http->FindSingleNode("//b[span[contains(text(), 'Pontos a Vencer') or contains(text(), 'Expiring points')]]/following-sibling::span", null, true, "/(?:Em|On)\s*([^<]+)/ims");

        if (isset($exp)) {
            $exp = $this->ModifyDateFormat($exp);
        }

        if ($exp = strtotime($exp)) {
            $this->SetExpirationDate($exp);
        }

        // LATAM Airlines (Fidelidade) info
        // Categoria
        $this->SetProperty("EliteLevel", $this->http->FindSingleNode("//p[contains(text(), 'Categoria')]/span"));
        // Nº Cartão Fidelidade
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//p[contains(text(), 'Nº Cartão Fidelidade:')]/span[1]"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // retries
            if (in_array($this->http->Response['code'], [302, 401, 403, 404, 500, 502, 503]) || $this->http->ParseForm("login") || strstr($this->http->Response['errorMessage'], 'Operation timed out after')) {
                throw new CheckRetryNeededException(3, 10);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR || $this->http->currentUrl() == 'https://www.pontosmultiplus.com.br/portal/errorPage.html')

        sleep(2);
        $this->http->GetURL("https://www.pontosmultiplus.com.br/portal/pages/meusDados/MeusDados.html?fromHome=true");

        if ($this->http->ParseForm("formMenu")) {
            $this->http->SetInputValue("javax.faces.partial.ajax", "true");
            $this->http->SetInputValue("javax.faces.source", "tabView");
            $this->http->SetInputValue("javax.faces.partial.execute", "tabView");
            $this->http->SetInputValue("javax.faces.partial.render", "tabView:formMeusDadosPessoais:btnSalvar tabView:formMeusDadosContato:btnSalvarContatos tabView");
            $this->http->SetInputValue("javax.faces.behavior.event", "tabChange");
            $this->http->SetInputValue("javax.faces.partial.event", "tabChange");
            $this->http->SetInputValue("tabView_contentLoad", "true");
            $this->http->SetInputValue("tabView_newTab", "tabView:tabDadosPessoais");
            $this->http->SetInputValue("tabView_tabindex", "1");
            $this->http->PostForm();
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//td[span[contains(text(), 'Nome') or contains(text(), 'First Name')]]/following-sibling::td[1]/input/@value") . ' ' . $this->http->FindSingleNode("//td[span[contains(text(), 'Sobrenome') or contains(text(), 'Last Name')]]/following-sibling::td[1]/input/@value")));
        // CPF
        $this->SetProperty("CPF", $this->http->FindSingleNode("//td[span[contains(text(), 'Número do documento')]]/following-sibling::td[1]/input/@value"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Ocorreu um erro
            if ($messgae = $this->http->FindSingleNode("//span[contains(text(), 'Ocorreu um erro.')]")) {
                throw new CheckException($messgae, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // LATAM Airlines (Fidelidade) info
        sleep(2);
        $this->http->GetURL("https://www.pontosmultiplus.com.br/portal/pages/LatamFidelidade.html?fromHome=true");
        // Nº Cartão Fidelidade
        $cardNumber = $this->http->FindSingleNode("//label[contains(text(), 'Número LATAM Fidelidade:')]/following-sibling::span[1]");

        if (!empty($cardNumber)) {
            $this->SetProperty("CardNumber", $cardNumber);
        }
        // Categoria
        $eliteLevel = $this->http->FindSingleNode("//label[contains(text(), 'Categoria:') or contains(text(), 'Category:')]/following-sibling::span[1]");

        if (!empty($eliteLevel)) {
            $this->SetProperty("EliteLevel", $eliteLevel);
        }
        // Points Accumulates
        $this->SetProperty('PointsAccumulated', $this->http->FindSingleNode("//span[contains(text(), 'Pontos válidos') or contains(text(), 'Valid points')]/preceding-sibling::span[1]"));
        // Points Missing Upgrade
        $this->SetProperty('PointsMissingForUpgrade', $this->http->FindSingleNode("//span[contains(text(), 'Pontos restantes') or contains(text(), 'Remaining points')]/preceding-sibling::span[1]"));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/\'sitekey\'\s*:\s*\'([^\']+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        return false;
    }
}
