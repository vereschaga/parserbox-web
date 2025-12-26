<?php

class TAccountCheckerPanvel extends TAccountCheckerExtended
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.panvel.com/panvel/meusDados.do';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 1;
        $this->http->GetURL('https://www.panvel.com/panvel/login.do');
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm('login-form')) {
            return false;
        }
        $this->selenium();
        $this->http->SetInputValue('usuario', $this->AccountFields['Login']);
        $this->http->SetInputValue('senha', $this->AccountFields['Pass']);

//        $this->http->setDefaultHeader('Accept', 'application/json, text/plain, */*');
//        $this->http->setDefaultHeader('Content-Type', 'application/json;charset=UTF-8');
//        $this->http->RetryCount = 0;
//        $this->http->PostURL('https://www.panvel.com/panvel/api/autenticacao/login', json_encode([
//            'usuario' => $this->AccountFields['Login'],
//            'senha' => $this->AccountFields['Pass']
//        ]), [
//            'Accept' => 'application/json, text/plain, */*',
//            'Content-Type' => 'application/json;charset=UTF-8'
//        ]);
//        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        return false;
    }

    public function Login()
    {
//        if (!$this->http->PostForm())
//            return $this->checkErrors();
        $response = $this->http->JsonLog();

//        if (isset($response->conteudo->codigo))
        if ($this->loginSuccessful()) {
            return true;
        }
        // Invalid credentials
//        if (isset($response->mensagem)) {
        if ($message = $this->http->FindPreg("/<div class=\"login-error\">([^<]+)/") ?? $this->http->FindPreg('/<p class="message__box-content">([^<]+)/')) {
//            $message = trim(html_entity_decode($response->mensagem));
            $this->logger->error($message);

            if (in_array($message, [
                'Usuário ou senha inválidos',
                'Usuário ou Senha inválidos',
            ])
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // Zero length BigInteger
            if ($message == 'Zero length BigInteger') {
                throw new CheckException("Usuário ou senha inválidos", ACCOUNT_INVALID_PASSWORD);
            }
        }// if (isset($response->mensagem))

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//p[contains(@class, "minha-conta-avatar-dados-nome")]')));

        $this->http->GetURL('https://www.panvel.com/panvel/bem-panvel.do');

        // Programa Bem Panvel
        if ($this->http->FindSingleNode('//div[@class = "bem-panvel__rodape-cadastro" and contains(text(), "Faça seu cadastro e ganhe em cada compra.")]')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return;

        $this->http->GetURL('https://www.panvel.com/panvel/extratoFidelidadeCadastro.do?secao=fidelidade&subSecao=extrato');
        // Saldo Atual
        $this->SetBalance($this->http->FindSingleNode('//span[contains(normalize-space(.), "Saldo Atual:")]/following-sibling::p[1]', null, true, self::BALANCE_REGEXP) ?? $this->http->FindPreg("/Saldo Atual:<\/span>\s*<p>([^<]+) pont[^<]+<\/p>/"));
        // Valor para resgate
        $this->SetProperty("ValueForRedemption", $this->http->FindSingleNode('//span[contains(normalize-space(.), "Valor para resgate:")]/following-sibling::p[1]', null, true, self::BALANCE_REGEXP) ?? $this->http->FindPreg("/Valor para resgate:<\/span>\s*<p>([^<*]+)\*<\/p>/"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // provider error
            if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'No momento essa opção está indisponível.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Saldo Atual: pontos <- may be provider bug
            if (isset($this->Properties['ValueForRedemption']) && $this->Properties['ValueForRedemption'] == 'R$ 0,00'
                && $this->http->FindSingleNode("//div[contains(@class, 'boxSaldo')]/child::p") == 'pontos') {
                $this->SetBalanceNA();
            } else {
                // Saldo Atual
                $this->SetBalance($this->http->FindSingleNode('//span[contains(text(), "Saldo")]/following-sibling::span', null, true, self::BALANCE_REGEXP));
                // Valor para resgate
                $this->SetProperty("ValueForRedemption", $this->http->FindSingleNode('//span[contains(text(), "Valor")]/following-sibling::span', null, true, self::BALANCE_REGEXP));
            }
        } // if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // Expiration Date  // refs #10910
        $expNodes = $this->http->XPath->query("//th[contains(text(), 'pontos a expirar')]/ancestor::table[1]//tr[td]");
        $this->logger->debug("Total {$expNodes->length} expNodes were found");

        for ($i = 0; $i < $expNodes->length; $i++) {
            $date = explode(" / ", $this->http->FindSingleNode('td[1]', $expNodes->item($i)));

            if (count($date) != 2) {
                $this->logger->notice("Skip bad date");

                continue;
            }
            $this->logger->debug("Date: {$date[0]} {$date[1]}");
            $date = en($date[0]) . " " . $date[1];
            $this->logger->debug("Date: {$date}");
            $points = $this->http->FindSingleNode("td[2]", $expNodes->item($i));

            if (strtotime('01 ' . $date) && $points > 0) {
                $this->SetExpirationDate(strtotime("+1 month -1 day", strtotime('01 ' . $date)));
                // Expiring balance
                $this->SetProperty("ExpiringBalance", $points);

                break;
            }// if (strtotime($date) && $points > 0)
        }// for ($i = 0; $i < $expNodes->length; $i++)
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->disableImages();
            $selenium->useCache();

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.panvel.com');
            $selenium->http->GetURL(self::REWARDS_PAGE_URL);
            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'usuario']"), 7);
            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'senha']"), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'btn-next-step']"), 0);
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if (!$login || !$pass || !$btn) {
                $this->logger->error("something went wrong");

                return false;
            }
            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);
            $btn->click();
            $selenium->waitForElement(WebDriverBy::xpath("//p[contains(@class, 'message__box-content')]"), 6);

            if ($selenium->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logoff.do')]"), 0, false)) {
                $result = true;
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            }
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup();
        }

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg("/logoff\.do/")) {
            return true;
        }

        return false;
    }
}
