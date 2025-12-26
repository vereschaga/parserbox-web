<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerNetpoints extends TAccountChecker
{
    use ProxyList;
    use DateTimeTools;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->TimeLimit = 600;
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        //$this->http->SetProxy($this->proxyDOP());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://netpoints.com.br/extrato", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (empty($this->AccountFields['Login2'])) {
            throw new CheckException("To update this NETPoints account you need to fill in the 'Birth month' field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        $this->http->FilterHTML = false;
        $this->http->removeCookies();
        $this->http->GetURL("https://netpoints.com.br/login");

        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("j_username", $this->AccountFields['Login']);
        $this->http->SetInputValue("j_control_pergunta", $this->AccountFields['Login2']);
        $this->http->SetInputValue("j_password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("j_captcha_process", "false");
        $this->http->SetInputValue("j_control", "2");
//        $this->http->SetInputValue("j_control", "1");

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://netpoints.com.br";

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Service Temporarily Unavailable
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")
            // Internal Error
            || $this->http->FindPreg("/<faultstring>Internal Error<\/faultstring>/")
            // hard code (AccountID: 3247074)
            || (empty($this->http->Response['body']) && $this->AccountFields['Login'] == '54945461600')
            || $this->http->FindPreg('/The Web Application Firewall has denied your transaction due to a violation of policy./')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Estamos realizando melhorias em nossos site. Agradecemos sua compreensão
        if ($this->http->FindSingleNode('//p[contains(text(), "The requested URL /login was not found on this server.")]')) {
            $this->http->GetURL("https://netpoints.com.br/");

            if ($this->http->FindSingleNode('//img[@src = "tela_site_netpoints.jpg"]/@src')) {
                throw new CheckException('Estamos realizando melhorias em nossos site. Agradecemos sua compreensão', ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->http->FindSingleNode('//p[contains(text(), "The requested URL /login was not found on this server.")]'))

        /*
         * not needed! provider bug
         *
        // The Web Application Firewall has denied your transaction due to a violation of policy.
        // You may want to clear the cookies in your browser.
        if ($this->http->FindSingleNode("//text()[contains(.,'The Web Application Firewall has denied your transaction due to a violation of policy.')]"))
            throw new CheckRetryNeededException(2);
        */
        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm([], 120)) {
            // wrong password
            if (empty($this->http->Response['body']) && !is_numeric($this->AccountFields['Pass']) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckException("Desculpe, não foi possível fazer o login. Nas próximas tentativas seu login será bloqueado.", ACCOUNT_INVALID_PASSWORD);
            }

            // provider bug fix
            if (empty($this->http->Response['body'])) {
                $this->http->GetURL("https://netpoints.com.br/extrato");

                if ($this->loginSuccessful()) {
                    return true;
                }
            }

            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[@id='popMsgSenha']//p")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Seu usúario está bloqueado
        if ($message = $this->http->FindSingleNode("//h5[contains(text(), 'Seu usúario está bloqueado')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // The Web Application Firewall has denied your transaction due to a violation of policy.
        if ($message = $this->http->FindPreg("/The Web Application Firewall has denied your transaction due to a violation of policy\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Você tem
        $this->SetBalance($this->http->FindSingleNode("//div[@class='headerLogin']/div[@class='pontos']", null, true, self::BALANCE_REGEXP));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class='headerLogin']/div[@class='sauda']", null, true, '/Olá\s*([^\,]+)\,/i')));

        if (!$this->Balance) {
            return;
        }
        $this->http->GetURL("https://netpoints.com.br/extrato/pontos/expirar/");
        $expNodes = $this->http->XPath->query("//tbody/tr");
        $this->logger->debug("Total {$expNodes->length} exp nodes were found");

        foreach ($expNodes as $expNode) {
            $date = $this->http->FindSingleNode('td[1]', $expNode, true, "/expirar em\s*(.+)/");
            $dateInEnglish = $this->dateStringToEnglish(str_replace(" de ", " ", $date));
            $points = $this->http->FindSingleNode('td[2]', $expNode, true, self::BALANCE_REGEXP_EXTENDED);
            $this->logger->debug("[{$date} - {$dateInEnglish}]: {$points}");

            if ($points > 0) {
                // Expiration date
                $this->SetExpirationDate(strtotime($dateInEnglish, false));
                // Expiring Balance
                $this->SetProperty("ExpiringBalance", $points);

                break;
            }// if ($points > 0)
        }// foreach ($expNodes as $expNode)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        return false;
    }
}
