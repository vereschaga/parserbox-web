<?php

class TAccountCheckerPetrobras extends TAccountChecker
{
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $domain = "www.petrobraspremmia.com.br";
//    private $domain = "accstorefront.cqfhr7oo4l-petrobras1-p1-public.model-t.cc.commerce.ondemand.com";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setUserAgent(\HttpBrowser::PROXY_USER_AGENT);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://{$this->domain}/", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://{$this->domain}/");

        if (!$this->http->ParseForm("topFormLogin")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("j_username", $this->AccountFields['Login']);
        $this->http->SetInputValue("j_password", $this->AccountFields['Pass']);
        $this->http->Form["j_password"] = $this->generatePass();

        if ($key = $this->http->FindSingleNode("(//form[@id='topFormLogin']//div[@class='g-recaptcha']/@data-sitekey)[1]")) {
            $captcha = $this->parseReCaptcha($key);

            if ($captcha !== false) {
                $this->http->SetInputValue('g-recaptcha-response', $captcha);
            }
        }

        return true;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;

        if ($this->parseQuestion()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }
        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");

        if ($currentUrl == 'https://www.petrobraspremmia.com.br/premmiastorefront/premmia/pt/?disabled=true'
            || $currentUrl == 'https://www.petrobraspremmia.com.br/?disabled=true'
            || $this->http->FindSingleNode("//form[@id='topFormLogin']//div[contains(@id, 'errorLoginSenha')]", null, true, "/(Usuário ou Senha incorreta\.)/u")
            || $this->http->FindSingleNode("//div[@class = 'global-alerts']//div[contains(@class, 'alert-danger')]", null, true, "/(Usuário ou Senha incorreta\.)/u")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Usuário ou Senha incorreta.", ACCOUNT_INVALID_PASSWORD);
        }
        // A network-related or instance-specific error occurred while establishing a connection to SQL Server
        if ($this->http->FindPreg('/A network-related or instance-specific error occurred while establishing a connection to SQL Server/i')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Timeout expired.  The timeout period elapsed prior to completion of the operation or the server is not responding.
        if ($this->http->FindPreg('/Timeout expired\.  The timeout period elapsed prior to completion of the operation or the server is not responding\./i')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Connection Timeout Expired.  The timeout period elapsed while attempting to consume the pre-login handshake acknowledgement.
        if ($this->http->FindPreg('/Connection Timeout Expired\.  The timeout period elapsed while attempting to consume the pre-login handshake acknowledgement\./')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // no errors (AccountID: 4090265)
        if ($currentUrl == 'https://www.petrobraspremmia.com.br/?expired=true'
            // AccountID: 4312341, 4158690, 3153391, 3111472
            || $currentUrl == 'https://www.petrobraspremmia.com.br/?error=true') {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        $question = $this->http->FindSingleNode('//div[contains(@class, "smstoken-subtitle")]');
        $CSRFToken = $this->http->FindPreg("/ACC.config.CSRFToken\s*=\s*\"([^\"]+)/");

        if (!$this->http->ParseForm("loginTokenForm") || !$question || !$CSRFToken) {
            return false;
        }

        $this->State['CSRFToken'] = $CSRFToken;

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->SetInputValue("loginTokenTyped", $this->Answers[$this->Question]);
        $this->http->SetInputValue("CSRFToken", $this->State['CSRFToken']);
        unset($this->Answers[$this->Question]);
        unset($this->State['CSRFToken']);
        $this->http->RetryCount = 0;
        $this->http->PostForm();
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        // Invalid answer
        if (isset($response->message) && in_array($response->message, [/*'Tempo limite atingido. Token expirado', */ 'Token Incorreto.'])) {
            $this->AskQuestion($this->Question, $response->message, 'Question');

            return false;
        }

        if (!empty($response->redirectURL)) {
            $redirectURL = $response->redirectURL;
            $this->http->NormalizeURL($redirectURL);
            $this->http->GetURL($redirectURL);
        } else {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://{$this->domain}/", [], 20);
            $this->http->RetryCount = 2;
        }

        return true;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@class = 'nomeUsuarioHeaderOnline']")));
        // Balance - Saldo de pontos
        $this->SetBalance($this->http->FindSingleNode('//span[span[contains(text(), "Saldo de pontos:")]]/text()[last()]'));

        $this->http->GetURL("https://{$this->domain}/my-account/extratosimplificado");
        $response = $this->http->JsonLog();
        // Balance - Saldo de pontos
        $this->SetBalance($response->saldoPremmia ?? null);

        $this->http->GetURL("https://{$this->domain}/my-account/extratopontos");
        $expNodes = $this->http->XPath->query("//div[contains(text(), 'Pontuação a expirar nos próximos meses')]/following-sibling::div/table//tr[td and not(contains(@class, 'page_extrato_tb_th'))]");
        $this->logger->debug("Total {$expNodes->length} exp nodes were found");
        $zeroPointsToExpire = 0;

        for ($i = 0; $i < $expNodes->length; $i++) {
            $node = $expNodes->item($i);
            $date = $this->ModifyDateFormat($this->http->FindSingleNode('td[2]', $node, true, "/(\d{2}\/\d{2}\/\d{4})/"));
            $pointsToExpire = $this->http->FindSingleNode('td[3]', $node);
            $this->logger->debug("Date: {$date} / {$pointsToExpire}");

            if ((!isset($exp) || strtotime($date) < $exp) && $pointsToExpire > 0) {
                $exp = strtotime($date);
                // Expiration Date
                $this->SetExpirationDate($exp);
                // Expiring balance
                $this->SetProperty("PointsToExpire", $pointsToExpire);
            }// if ((!isset($exp) || strtotime($date) < $exp) && $pointsToExpire > 0)
            elseif ($pointsToExpire == 0) {
                $zeroPointsToExpire++;
            }
        }// for ($i = 0; $i < $expNodes->length; $i++)

        if ($expNodes->length == 3 && $zeroPointsToExpire == 3 && !isset($this->Properties['PointsToExpire'])) {
            $this->ClearExpirationDate();
        }
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            (
                $this->http->FindSingleNode("//*[self::h1 or self::title][contains(text(), '502 Proxy Error')]")
                && $this->http->Response['code'] == 502
            )
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//img[contains(@src, 'error/aviso.jpg')]/@id")) {
            throw new CheckException("Oops, estamos em manutenção", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "Our services aren\'t available right now")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // maintenance
        $this->http->GetURL("https://www.petrobraspremmia.com.br");

        if ($message = $this->http->FindSingleNode("//img[contains(@alt, 'Estamos em manutenção')]/@alt")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "Our services aren\'t available right now")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            ($this->http->FindSingleNode("//p[contains(text(), 'Server Error')]") && $this->http->Response['code'] == 500)
            // 504 Gateway Time-out
            || ($this->http->FindSingleNode("//h1[contains(text(), '504 Gateway Time-out')]") && $this->http->Response['code'] == 504)
            // 502 Bad Gateway
            || ($this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]") && $this->http->Response['code'] == 502)
            || ($this->http->Response['body'] == 'ERRO' && $this->http->Response['code'] == 500)
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Temos muitos participantes buscando as ofertas do Premmia.
        if (($message = $this->http->FindSingleNode("//h3[contains(text(), 'Temos muitos participantes buscando as ofertas do Premmia.')]"))
            && $this->http->Response['code'] == 500
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'Nosso site está com um número recorde de acessos!')]")) {
            throw new CheckException("Ops! " . $message . " Por favor, continue tentando!", ACCOUNT_PROVIDER_ERROR);
        }
        // VOLTAREMOS EM BREVE!
        if ($message = $this->http->FindSingleNode('//h3[normalize-space() = "Estamos preparando uma experiência ainda melhor e mais personalizada para você. Por isso, o site do Premmia voltará a ficar disponível em breve. Aguarde!"]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    /*
     * @link https://accstorefront.cqfhr7oo4l-petrobras1-p1-public.model-t.cc.commerce.ondemand.com/wro/all_responsive.js
     */
    private function generatePass()
    {
        $this->logger->notice(__METHOD__);

        $script = /** @lang JavaScript */
            "    
            var testArray = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];
            testArray = Shuffle(testArray);
            var pwdSend = '';
            
            var input = '" . $this->AccountFields['Pass'] . "';
            
            function Shuffle(o) {
                for (var j, x, i = o.length; i; j = parseInt(Math.random() * i),
                x = o[--i],
                o[i] = o[j],
                o[j] = x)
                    ;
                return o;
            }
            
            for (var i = 0; i < input.length; i++) {
                var outputSign = input[i];  

                if (outputSign == testArray[0] || outputSign == testArray[1]) {
                    pwdSend = pwdSend + testArray[0] + testArray[1] + '';
                }
                
                if (outputSign == testArray[2] || outputSign == testArray[3]) {
                    pwdSend = pwdSend + testArray[2] + testArray[3] + '';
                }
                
                if (outputSign == testArray[4] || outputSign == testArray[5]) {
                    pwdSend = pwdSend + testArray[4] + testArray[5] + '';
                }
                
                if (outputSign == testArray[6] || outputSign == testArray[7]) {
                    pwdSend = pwdSend + testArray[6] + testArray[7] + '';
                }
                
                if (outputSign == testArray[8] || outputSign == testArray[9]) {
                    pwdSend = pwdSend + testArray[8] + testArray[9] + '';
                }
            }

            sendResponseToPhp(pwdSend);
        ";
        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
        $pwdSend = $jsExecutor->executeString($script);

        $this->logger->debug("[Pass]: {$pwdSend}");

        return $pwdSend;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@class, 'sair') or contains(@href, 'logout')]")) {
            return true;
        }

        return false;
    }
}
