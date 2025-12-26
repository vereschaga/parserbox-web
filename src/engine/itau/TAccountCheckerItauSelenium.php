<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerItauSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use DateTimeTools;
    use ProxyList;

    private $logoutXpath = "//a[contains(@title, 'Encerrar sessão') or @onclick='GA.pushHeader(\"botaoSair\");'] | //p[@class = 'info-conta']";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useChromium();
        $this->http->SetProxy($this->proxyReCaptchaIt7());

        if ($this->attempt > 0) {
            $this->setProxyBrightData(null, 'static', 'br');
        }
//        $this->useCache();
        $this->keepCookies(false);
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function LoadLoginForm()
    {
        $this->AccountFields['Login'] = preg_replace('/\D/ims', '', $this->AccountFields['Login']);
        $this->logger->debug($this->AccountFields['Login']);

        if (empty($this->AccountFields['Login']) || strlen($this->AccountFields['Login']) != 10) {
            throw new CheckException("Aviso: Agência/Conta inválida. Verifique se o número digitado está correto. É preciso incluir o dígito verificador.", ACCOUNT_INVALID_PASSWORD);
        }
        $this->logger->debug("strlen -> " . strlen($this->AccountFields['Pass']));

        if (
            (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG)
            && (strlen($this->AccountFields['Pass']) < 6 || !is_numeric($this->AccountFields['Pass']))
        ) {
            throw new CheckException("Por favor, preencha corretamente o campo 'senha eletrônica'", ACCOUNT_INVALID_PASSWORD);
        }

        try {
            $this->driver->manage()->window()->setSize(new WebDriverDimension(1280, 900));
            $this->http->GetURL("https://www.itau.com.br/conta-corrente/");
        } catch (TimeOutException $e) {
            $this->logger->debug("TimeoutException: " . $e->getMessage());

            throw new CheckRetryNeededException();
        }

        $this->waitForElement(WebDriverBy::xpath('//form[@aria-label="login"]'), 3);
        $this->saveResponse();
        if (
            !$this->http->ParseForm(null, '//form[@aria-label="login"]')
            && !$this->http->FindPreg("/aria-label=\"login\" method=\"POST\"/")
        ) {
            $this->saveResponse();

            if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
                throw new CheckRetryNeededException();
            }

            return false;
        }

        // Informe seu CPF
        $this->logger->debug("[Step 1]: send login");
        $this->logger->debug(substr($this->AccountFields['Login'], 0, 4));

        /*
        if ($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "abra sua conta")]'), 5)) {
            $this->driver->executeScript('$(\'li[data-select-form="agencia-conta"]\').click()');
        }
        */

        $login1Input = $this->waitForElement(WebDriverBy::xpath('//input[@id = "campo_agencia" or @id = "agencia"]'), 10, true);
        $this->saveResponse();

        if (!$login1Input) {
            $this->logger->error('Failed to find first "login" input');

            return false;
        }
        $login1Input->click();
        $login1Input->clear();
        $login1Input->sendKeys(substr($this->AccountFields['Login'], 0, 4));

        $login2Input = $this->waitForElement(WebDriverBy::xpath('//input[@id = "campo_conta" or @id = "conta"]'), 0, true);
        $this->saveResponse();

        if (!$login2Input) {
            $this->logger->error('Failed to find second "login" input');

            return false;
        }
        $login2Input->click();
        $login2Input->clear();
        $login2Input->sendKeys(substr($this->AccountFields['Login'], 4));

        $this->logger->debug(substr($this->AccountFields['Login'], 4));
        $submitButtonXpath = "
            //a[@class = 'btnSubmit']
            | //button[@id = 'btnLoginSubmit' and contains(@class, 'send active')]
            | //button[@id = 'loginButton' and contains(@class, 'enabled')]
        ";
        $submitButton = $this->waitForElement(WebDriverBy::xpath($submitButtonXpath), 5);
        $this->saveResponse();

        // provider bug fix - works
        if (!$submitButton) {
            $login1Input->click();
            $login1Input->clear();
            $login1Input->sendKeys(substr($this->AccountFields['Login'], 0, 4));
            $this->saveResponse();
            $login2Input->click();
            $login2Input->clear();
            $login2Input->sendKeys(substr($this->AccountFields['Login'], 4));
            $this->saveResponse();
            $submitButton = $this->waitForElement(WebDriverBy::xpath($submitButtonXpath), 5);
            $this->saveResponse();
        }

        if (!$submitButton) {
            $this->logger->error('Failed to find submit button');
            return false;
        }

        try {
            $submitButton->click();
            $this->driver->switchTo()->alert()->accept();
        } catch (NoAlertOpenException $e) {
            $this->logger->debug("no alert, skip");
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException: " . htmlspecialchars($e->getMessage()));

            throw new CheckRetryNeededException(2, 3);
        }

        sleep(3);
        $this->logger->debug("saveResponse");
        $this->saveResponse();
        //  Clique no seu nome para acessar o teclado virtual.
        $this->logger->debug("[Step 2]: Click 'Name'");
        $this->waitForElement(WebDriverBy::xpath("(//a[@class = 'MSGTexto8' or @class = 'MSGNome' or contains(@class, ' btn-fluxo')])[1] | //span[@class = 'MsgTxt']"), 7, true);
        $name = $this->waitForElement(WebDriverBy::xpath("(//a[@class = 'MSGTexto8' or @class = 'MSGNome' or contains(@class, ' btn-fluxo')])[1]"), 0, true);

        if ($name) {
//            $name->click();
            $this->driver->executeScript('$(\'a.MSGTexto8, a.MSGNome, a[class *= " btn-fluxo"]\').get(0).click();');
        } else {
            $this->logger->error('Failed to find "name" button');

            if ($this->askToken()) {
                return false;
            }
        }
        $this->saveResponse();
        $this->waitForElement(WebDriverBy::xpath("//a[img[contains(@title, '1')]] | //a[contains(@aria-label, '1')] | //span[@class = 'MsgTxt']"), 20);

        try {
            $this->saveResponse();
        } catch (TimeOutException $e) {
            $this->logger->error("TimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        $this->logger->debug("[Step 3]: Sending 'Senha'");
        $pass = $this->AccountFields['Pass'];

        for ($i = 0; $i < strlen($pass); $i++) {
            $elem = $this->waitForElement(WebDriverBy::xpath("//a[img[contains(@title, '{$pass[$i]}')]] | //a[contains(@aria-label, '{$pass[$i]}')]"), 0);

            if ($elem) {
                try {
                    $this->driver->executeScript('$(\'div.blockPage\').remove();');
                    $this->driver->executeScript('$(\'div.blockOverlay\').remove();');
                    $elem->click();
                    $this->saveResponse();
                } catch (UnknownServerException $e) {
                    $this->logger->error("UnknownServerException exception: " . htmlspecialchars($e->getMessage()));
                    $this->DebugInfo = "UnknownServerException";
                    // retries
                    if (strstr($e->getMessage(), 'unknown error: Element')) {
                        $retry = true;
                    }
                }
            } else {
                $this->logger->error('Failed to find input element for password symbol');

                // retries
                if ($this->http->FindSingleNode("//*[self::h1 or self::span][contains(text(), 'This site can’t be reached')]")) {
                    throw new CheckRetryNeededException(3, 1);
                }

                return $this->checkErrors();
            }
        }

        if (!isset($elem)) {
            $this->logger->error('Fail');

            return false;
        }
        $validatePasswordButton = $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'ValidaSenha') or @id = 'acessar']"), 3);

        if (!$validatePasswordButton) {
            $this->logger->error('Failed to find "validate password" button');

            return false;
        }
        $validatePasswordButton->click();

        return true;
    }

    public function Login()
    {
        $this->logger->notice("waiting link");
        $sleep = 30;
        $startTime = time();

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
            $logout = $this->waitForElement(WebDriverBy::xpath($this->logoutXpath), 0);
            /*
             * Olá!
             *
             * O Itaú na Internet mudou para que sua experiência seja ainda melhor.
             *
             * Quer conhecer as novidades?
             */
            if (
                !$logout
                && (
                    $this->waitForElement(WebDriverBy::id("divOverlayHopscotch"), 0)
                    || $this->waitForElement(WebDriverBy::id("divAcessTour"), 0)
                    || $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Não, obrigado')]"), 0)
                )
            ) {
                $this->driver->executeScript("$('div#divOverlayHopscotch').remove();");
                $logout = $this->waitForElement(WebDriverBy::xpath($this->logoutXpath), 0);
            }
            $this->saveResponse();

            if (!$logout) {
                $logout = $this->http->FindPreg("/a onclick=\"GA.pushHeader\('botaoSair'\);\"/");
            }

            if ($logout) {
                return true;
            }

            if ($question = $this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Validação do iToken Itaú')]"), 0)) {
                $this->logger->notice($question->getText());
                $this->processErrorsAndQuestions();

                return false;
            }
            // sq, v.2
            if ($this->askToken()) {
                return false;
            }

            $this->logger->debug("strlen -> " . strlen($this->AccountFields['Pass']));

            if (strlen($this->AccountFields['Pass']) < 6) {
                throw new CheckException("Por favor, preencha corretamente o campo 'senha eletrônica'", ACCOUNT_INVALID_PASSWORD);
            }
        }// while ((time() - $startTime) < $sleep)

        return $this->checkErrors();
    }

    public function processTokenCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $token = $this->waitForElement(WebDriverBy::xpath('//input[@id = "app-entraCodigo"]'), 0);
        $this->saveResponse();

        if (!$token) {
            return false;
        }
        $token->sendKeys($this->Answers[$this->Question]);
        $this->logger->debug("click button...");
        $this->driver->executeScript('$(\'#formContinuar\').submit();');

        $this->logger->notice("resetting answers");
        $this->Answers = [];
        $this->logger->debug("success");
        // Access is allowed
        $logout = $this->waitForElement(WebDriverBy::xpath($this->logoutXpath), 0);
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        switch ($step) {
            case "SecurityCheckpoint":
                return $this->processSecurityCheckpoint();

                break;

            case "Token":
                return $this->processTokenCheckpoint();

                break;
        }

        return false;
    }

    public function Parse()
    {
        // Disponível p/ saque [Available for withdrawal]
        $disponivel = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Disponível p/ saque')]/following-sibling::span[1] | //span[div[contains(text(), 'Disponível para saque')]]/following-sibling::div[1]"), 10);

        if (!$disponivel) {
            $disponivel = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Disponível p/ saque')]/following-sibling::span[1] | //span[div[contains(text(), 'Disponível para saque')]]/following-sibling::div[1]"), 0, false);
        }

        try {
            $this->saveResponse();
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("TimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            sleep(1);
            $this->saveResponse();
        }

        if ($disponivel && !empty($disponivel->getText())) {
            $this->SetProperty("AvailableForWithdrawal", $disponivel->getText());
        } elseif ($disponivel = $this->http->FindPreg("/Dispon.+vel para saque<\/div>\s*<\/span>\s*<div class=\"TRNHOnum\">([^<]+)<\/div>/ims")) {
            $this->SetProperty("AvailableForWithdrawal", Html::cleanXMLValue($disponivel));
        }
        // LIS (suj. encargos)
        $this->SetProperty("Charges", $this->http->FindSingleNode("//span[contains(text(), 'LIS')]/following-sibling::span[1]"));
        // Total p/ saque
        $totalEarned = $this->http->FindSingleNode("//span[b[contains(text(), 'Total p/ saque')]]/following-sibling::span[1]");

        if (!$totalEarned) {
            $totalEarned = $this->http->FindPreg("/Total\s*para\s*saque<\/div>\s*<\/span>\s*<div[^>]*>([^<]+)/ims");
        }
        $this->SetProperty("TotalEarned", $totalEarned);
        // Agência
        $numAgencia = $this->http->FindSingleNode("//p[contains(@class, 'numAgencia')]/span[@class = 'dadosCliente']");

        if (!$numAgencia) {
            $numAgencia = $this->http->FindSingleNode("//p[contains(@class, 'numAgencia')]/text()[last()]");
        }

        if (!$numAgencia) {
            $numAgencia = $this->http->FindSingleNode("//span[contains(text(), 'AgÃªncia:')]/following-sibling::span[1]");
        }

        if (!$numAgencia) {
            $numAgencia = $this->http->FindPreg("/>ag:\s*(\d+)/ims");
        }
        // Conta
        $numConta = $this->http->FindSingleNode("//p[contains(@class, 'numConta')]/span[@class = 'dadosCliente']");

        if (!$numConta) {
            $numConta = $this->http->FindSingleNode("//p[contains(@class, 'numConta')]/text()[last()]");
        }

        if (!$numConta) {
            $numConta = $this->http->FindSingleNode("//span[contains(text(), 'Conta:')]/following-sibling::span[1]");
        }

        if (!$numConta) {
            $numConta = $this->http->FindPreg("#c/c:\s*([\d-]+)#ims");
        }

        if (isset($numAgencia, $numConta)) {
            $this->SetProperty("AccountNumber", $numAgencia . "/" . $numConta);
        } else {
            $this->SetProperty("AccountNumber", preg_replace('/[^\d\-\/]+/', '', $this->http->FindSingleNode("//p[@class = 'info-conta'] | //select[@id = 'contasCliente']/option[@selected]")));
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("(//p[contains(@class, 'nomeCliente') or contains(@class, 'clientePers')])[1] | //span[@class = 'TOPnomecliente'] | //span[@class = 'gerente']/strong | //p[@id = 'nomeCliente']")));

        // Header -> Cartões
        $cardsButton = $this->waitForElement(WebDriverBy::xpath("//a[@title = 'cartões' or normalize-space(text()) = 'cartões']"), 5, false);

        if (!$cardsButton) {
            $cardsButton = $this->waitForElement(WebDriverBy::xpath("//a[@title = 'Cartões']"), 0);
        }

        if (!$cardsButton && !$this->http->FindNodes("//p[@class = 'info-conta'] | //p[@id = 'nomeCliente']")) {
            $this->logger->error('Failed to find "cards" button');

            return;
        }
        // $('div#overlayHOWarnings').remove()
        // skip warning: "Transferência Automática de salário"
        $this->driver->executeScript('
            var cardsLink = $(\'a:contains("cartões"), a:contains("Cartões")\');
            if (cardsLink.length)
                cardsLink.get(0).click();
        ');
        //		$cardsButton->click();

        // Block "Programa de Pontos" -> Consultar saldo e extrato
        // ff
//        $program = $this->waitForElement(WebDriverBy::xpath("//a[@title = 'Cartoes-Programa-de-pontos-Consultar-saldo-e-extrato']"), 10);
        // chrome
        $this->waitForElement(WebDriverBy::xpath("//p[a[@title = 'Cartoes-Programa-de-pontos-Consultar-saldo-e-extrato']]
            | //a[contains(text(), 'Consultar saldo e extrato')]
            | //a[@title = 'programa de pontos' or normalize-space(text()) = 'programa de pontos' or normalize-space(text()) = 'programa de pontos iupp']
            | //strong[contains(text(), 'programa de pontos')]/following-sibling::a
            | //a[contains(@onclick, 'cartoes/programaFidelidade/consultarSaldoExtrato')]
            | //div[contains(@class, 'warningViewAdobe')]
        "), 10);
        $this->saveResponse();

        try {
            if ($this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'warningViewAdobe')]"), 0)) {
                $this->driver->executeScript('$(\'div.mfp-close-btn-in:visible, div.mfp-bg\').remove();');
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            sleep(2);

            if ($this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'warningViewAdobe')]"), 0)) {
                $this->driver->executeScript('$(\'div.mfp-close-btn-in:visible, div.mfp-bg\').remove();');
            }
        }

        $program = $this->waitForElement(WebDriverBy::xpath("//p[a[@title = 'Cartoes-Programa-de-pontos-Consultar-saldo-e-extrato']]
            | //a[contains(text(), 'Consultar saldo e extrato')]
            | //a[@title = 'programa de pontos' or normalize-space(text()) = 'programa de pontos' or normalize-space(text()) = 'programa de pontos iupp']
            | //a[contains(@onclick, 'cartoes/programaFidelidade/consultarSaldoExtrato')]
            | //strong[contains(text(), 'programa de pontos')]/following-sibling::a
        "), 0);
        $this->saveResponse();

        if (!$program) {
            $this->logger->notice("provider bug workaround");
//            $this->driver->executeScript('
//                var cardsLink = $(\'a:contains("cartões"), a:contains("Cartões")\');
//                if (cardsLink.length)
//                    cardsLink.get(0).click();
//            ');
            $program = $success = $this->waitFor(function () {
                return
                    $this->waitForElement(WebDriverBy::xpath("//p[a[@title = 'Cartoes-Programa-de-pontos-Consultar-saldo-e-extrato']]
                        | //a[contains(text(), 'Consultar saldo e extrato')]
                        | //a[@title = 'programa de pontos' or normalize-space(text()) = 'programa de pontos' or normalize-space(text()) = 'programa de pontos iupp']
                        | //a[contains(@onclick, 'cartoes/programaFidelidade/consultarSaldoExtrato')]
                    "), 0, false)
                    || $this->waitForElement(WebDriverBy::xpath("//p[a[@title = 'Cartoes-Programa-de-pontos-Consultar-saldo-e-extrato']]
                        | //a[contains(text(), 'Consultar saldo e extrato')]
                        | //a[@title = 'programa de pontos' or normalize-space(text()) = 'programa de pontos' or normalize-space(text()) = 'programa de pontos iupp']
                        | //a[contains(@onclick, 'cartoes/programaFidelidade/consultarSaldoExtrato')]
                    "), 0);
            }, 0);
        }

        // no links in menu
        if (!$program) {
            $this->logger->notice("provider bug workaround");
            if ($this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Aderir a um programa de pontos')]"), 0)) {
                $this->driver->executeScript('$(\'a:contains("Aderir a um programa de pontos")\').get(0).click();');
                sleep(2);
                $this->saveResponse();

                $program = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'ver programa de pontos')]"), 0);
            }
        }

        if ($program) {
            $this->logger->debug("Go to -> 'Programa de Pontos' -> Consultar saldo e extrato");

            try {
                $this->saveResponse();
            } catch (ScriptTimeoutException | TimeOutException $e) {
                $this->logger->error("TimeoutException: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
                sleep(1);
                $this->saveResponse();
            }

//            $program->click();
            // prevent trace in chrome
            try {
                if ($program = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'programa de pontos') and not(contains(text(), 'Aderir a um'))]"), 0)) {
                    $this->driver->executeScript('$(\'a:contains("Consultar saldo e extrato"), a[title = "programa de pontos"], a:contains("programa de pontos")\').get(0).click()');
                    sleep(2);
                    $this->saveResponse();
                    $programa =
                        $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'ver programa de pontos')]"), 1)
                        ?? $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'ver programa de pontos')]"), 0, false)
                    ;
                    $this->saveResponse();

                    if ($programa) {
//                        $programa->click();
                        $this->driver->executeScript('$(\'a:contains("ver programa de pontos")\').get(0).click()');
                        sleep(2);
                    }

                    $this->saveResponse();
                } else {
                    $this->driver->executeScript('$(\'a.btn-nav\').click();');
                    sleep(2);
                    $this->saveResponse();
                    $this->driver->executeScript('
                        let progr = $(\'a:contains("Consultar saldo e extrato"), a[title = "programa de pontos"], a:contains("programa de pontos")\');                        if (progr) {
                            progr.get(0).click();
                        }
                        else {
                            document.querySelectorAll(\'.card-list__item:not(.ng-hide) a[aria-label="detalhes"]\').forEach((button) => {
                                button.click();
                            });
                        }
                    ');
                }
            } catch (ScriptTimeoutException | TimeOutException $e) {
                $this->logger->error("TimeoutException: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
                $this->saveResponse();
            }
            // PROGRAMA SEMPRE PRESENTE
            // ff
//            $input = $this->waitForElement(WebDriverBy::xpath("//td[contains(., 'PROGRAMA SEMPRE PRESENTE')]/input"), 10);
            // chrome
            $pointsBlock = $this->waitForElement(WebDriverBy::xpath("
                //frame[@name = 'CORPO']
                | //td[input[@id = 'RadioCart1']]
                | //label[contains(text(), 'total de pontos')]/following-sibling::p
                | //p[contains(@class, 'your-points__values')]
                | //p[contains(text(), 'total de pontos')]
            "), 10);
            $this->logger->debug("before pointsBlock");

            try {
                $this->saveResponse();
            } catch (TimeOutException $e) {
                $this->logger->error("TimeoutException: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
            }
            $this->logger->debug("after pointsBlock");

            // too long loading
            if (!$pointsBlock && $this->waitForElement(WebDriverBy::xpath('//div[@id = "voxel-loading-circle-0"]'), 0)) {
                $pointsBlock = $this->waitForElement(WebDriverBy::xpath("
                    //frame[@name = 'CORPO']
                    | //td[input[@id = 'RadioCart1']]
                    | //label[contains(text(), 'total de pontos')]/following-sibling::p
                "), 10);

                try {
                    $this->saveResponse();
                } catch (TimeOutException $e) {
                    $this->logger->error("TimeoutException: " . $e->getMessage());
                    $this->driver->executeScript('window.stop();');
                }
            }

            // hide menu
            $this->driver->executeScript('$(\'div.sub-mnu\').hide();');
        }
        $this->saveResponse();

        // Balance - SALDO DE PONTOS NO PROGRAMA SEMPRE PRESENTE
        $this->SetBalance($this->http->FindSingleNode('(//div[@class = "card-order" and contains(., "Sempre Presente")]//label[contains(text(), "total de pontos")]/following-sibling::p)[1] | //p[contains(@class, "your-points__values")]')); // AccountID: 2954902

        $cards = $this->http->XPath->query('//div[@class = "card-order" and not(contains(., "Sempre Presente"))]');
        $this->logger->debug("Total {$cards->length} cards not Sempre Presente were found");

        foreach ($cards as $card) {
            $displayName = $this->http->FindSingleNode('.//h3[contains(@class, "card-order__title")]', $card);
            $balance = $this->http->FindSingleNode('.//label[contains(text(), "total de pontos")]/following-sibling::p', $card);
            $expiringBalance = $this->http->FindSingleNode('.//label[contains(text(), "pontos a expirar em")]/following-sibling::p', $card);
            $exp = $this->http->FindSingleNode('.//label[contains(text(), "pontos a expirar em")]/b', $card);
            $this->logger->debug("[ExpirationDate]: " . strtotime($this->ModifyDateFormat($exp)));
            $subAcc = [
                'Code'            => 'itau' . md5($displayName),
                'DisplayName'     => $displayName,
                'Balance'         => $balance,
                'ExpiringBalance' => $expiringBalance,
            ];

            if ($expiringBalance > 0) {
                $subAcc['ExpirationDate'] = strtotime($this->ModifyDateFormat($exp));
            }

            if (!isset($balance)) {
                $this->logger->notice("skip card without balance");
                $this->logger->debug(var_export($subAcc, true), ["pre" => true]);

                continue;
            }
            $this->AddSubAccount($subAcc);
        }

        // Expiration Date    // refs #17314
        $this->logger->info('Expiration date', ['Header' => 3]);

        if ($pontosExpirar = $this->waitForElement(WebDriverBy::xpath('//div[@class = "card-order" and contains(., "Sempre Presente")]//button[contains(., "consultar extrato")]'), 0)) {
            $this->saveResponse();
//            $pontosExpirar->click();
            $this->logger->debug("click");
            $this->driver->executeScript('$(\'div.card-order:contains("Sempre Presente") button:contains("consultar extrato")\').get(0).click()');
            $this->logger->debug("wait pontos a expirar");
            $this->waitForElement(WebDriverBy::xpath("
                //*[@id = 'tabs-nav-item-2']//div[contains(., 'não há pontos a expirar para o período selecionado')]
                | //button[contains(text(), 'pontos a expirar')]
            "), 10);
            $pontosAexpirar = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'pontos a expirar')]"), 0);

            try {
                $this->saveResponse();
            } catch (ScriptTimeoutException | TimeOutException $e) {
                $this->logger->error("TimeoutException: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
            }

            if (!$pontosAexpirar) {
                $this->logger->error("Exp date not found");

                return;
            }
//            $pontosAexpirar->click();
            $this->driver->executeScript('$(\'button:contains("pontos a expirar")\').get(0).click()');

            sleep(2);
            $select = $this->waitForElement(WebDriverBy::xpath('//*[@label = "filtrar por período"]'), 8);

            try {
                $this->saveResponse();
            } catch (ScriptTimeoutException | TimeOutException $e) {
                $this->logger->error("TimeoutException: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
            }

            // Pending Points
            $this->SetProperty("PendingPoints", $this->http->FindSingleNode("//label[contains(text(), 'pontos a liberar')]/following-sibling::p"));

            if (!$select) {
                $this->logger->error("select not found");

                return;
            }

            $this->driver->executeScript('$(\'[label *= "filtrar por período"] label[for *= "voxel-select__input-"]\').get(0).click();');
            $this->logger->error("sleep 1");
            sleep(1);
            $this->driver->executeScript('$(\'span:contains("12 meses")\').click();');

            $this->waitForElement(WebDriverBy::xpath("
                //*[@id = 'tabs-nav-item-2']//div[contains(., 'não há pontos a expirar para o período selecionado')]
                | //td[contains(text(), 'pontos a expirar')]
            "), 10);

            try {
                $this->saveResponse();
            } catch (ScriptTimeoutException | TimeOutException $e) {
                $this->logger->error("TimeoutException: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
                $this->saveResponse();
            }

            if ($this->waitForElement(WebDriverBy::xpath("//td[contains(text(), 'pontos a expirar')]"), 0)) {
                $nearestExpDateYear = $this->http->FindSingleNode('//label[contains(text(), "pontos a expirar em")]/b', null, true, "/(\/\d{4})$/");
                $xpathExp = '(//table[contains(@class, "balance-table")]//td[contains(text(), "pontos a expirar")])[1]';
                // Expiring Balance
                $this->SetProperty("ExpiringBalance", $this->http->FindSingleNode($xpathExp . '/following-sibling::td[1]'));
                $expDate = $this->http->FindSingleNode($xpathExp . '/preceding-sibling::td');
                $expDate .= $nearestExpDateYear;
                $this->logger->debug("Exp date: {$expDate}");

                if (substr_count($expDate, '/') == 1) {
                    $expDate = preg_replace('/(\d+)\/(\d+)/', '$2/$1', $expDate);
                } else {
                    $expDate = $this->ModifyDateFormat($expDate);
                }
                $this->logger->debug("Exp date: {$expDate}");

                if ($exp = strtotime($expDate)) {
                    $this->SetExpirationDate($exp);

                    if ($exp < time()) {
                        $this->logger->notice("correcting exp date");
                        $this->SetExpirationDate(strtotime("+1 year", $exp));
                    }
                }
            }// if (!$noExpDate)
        }// if ($this->waitForElement(WebDriverBy::xpath("//td[contains(text(), 'pontos a expirar')]"), 0))

        // Cadastro do iToken por SMS
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($message = $this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Cadastro do iToken por SMS')]"), 0)) {
                $this->logger->debug($message->getText());
                $this->throwProfileUpdateMessageException();
            }

            // no Sempre Presente, but have several other balances
            if (
                !empty($this->Properties['AccountNumber'])
                && !empty($this->Properties['Name'])
                && (
                    !empty($this->Properties['SubAccounts'])// AccountID: 4821700, no Sempre Presente, but have several other balances
                    || (
                        // AccountID: 2905469 - only 'Latam Pass 1' program without balance
                        count($this->http->FindNodes('//div[@class = "card-order" and not(contains(., "Sempre Presente"))]')) == 1
                        && count($this->http->FindNodes('//div[@class = "card-order" and contains(., "Latam Pass 1")]')) == 1
                    )
                    || (
                        // AccountID: 2715410, 4086045 - only 'Latam Pass 2' program without balance
                        count($this->http->FindNodes('//div[@class = "card-order" and not(contains(., "Sempre Presente"))]')) == 1
                        && count($this->http->FindNodes('//div[@class = "card-order" and contains(., "Latam Pass 2")]')) == 1
                    )
                    || (
                        // AccountID: 3421654, 2765840 - only 'Tudoazul Itaucard' program without balance
                        count($this->http->FindNodes('//div[@class = "card-order" and not(contains(., "Sempre Presente"))]')) == 1
                        && count($this->http->FindNodes('//div[@class = "card-order" and contains(., "Tudoazul Itaucard")]')) == 1
                    )
                    || (
                        // AccountID: 4972459 - only 'Latam Pass 1', 'Tudoazul Itaucard' program without balance
                        count($this->http->FindNodes('//div[@class = "card-order" and not(contains(., "Sempre Presente"))]')) == 2
                        && count($this->http->FindNodes('//div[@class = "card-order" and contains(., "Latam Pass 1")]')) == 1
                        && count($this->http->FindNodes('//div[@class = "card-order" and contains(., "Tudoazul Itaucard")]')) == 1
                    )
                    || (
                        // AccountID: 4172463 - only 'Latam Pass 1', 'Latam Pass 2' program without balance
                        count($this->http->FindNodes('//div[@class = "card-order" and not(contains(., "Sempre Presente"))]')) == 2
                        && count($this->http->FindNodes('//div[@class = "card-order" and contains(., "Latam Pass 1")]')) == 1
                        && count($this->http->FindNodes('//div[@class = "card-order" and contains(., "Latam Pass 2")]')) == 1
                    )
                    || (
                        // AccountID: 5005498 - only 'Latam Pass 2', 'Tudoazul Itaucard' program without balance
                        count($this->http->FindNodes('//div[@class = "card-order" and not(contains(., "Sempre Presente"))]')) == 2
                        && count($this->http->FindNodes('//div[@class = "card-order" and contains(., "Latam Pass 2")]')) == 1
                        && count($this->http->FindNodes('//div[@class = "card-order" and contains(., "Tudoazul Itaucard")]')) == 1
                    )
                    || (
                        // AccountID: 4972459 - only 'Latam Pass 2', 'Latam Pass 1', 'Tudoazul Itaucard' program without balance
                        count($this->http->FindNodes('//div[@class = "card-order" and contains(., "Latam Pass 1")]')) == 1
                        && count($this->http->FindNodes('//div[@class = "card-order" and contains(., "Latam Pass 2")]')) == 1
                        && count($this->http->FindNodes('//div[@class = "card-order" and contains(., "Tudoazul Itaucard")]')) == 1
                    )
                )
            ) {
                $this->SetBalanceNA();

                return;
            }

            // No momento esta função encontra-se indisponível. Por favor, aguarde alguns minutos e tente novamente.
            // Você não possui programa de pontos contratado.
            /*
             * No momento o Extrato de pontos encontra-se indisponível.
             * Por favor, aguarde alguns minutos e tente novamente.
             * Caso necessite de mais informações, contate a central de atendimento.
             */
            if ($message = $this->waitForElement(WebDriverBy::xpath("
                    //span[contains(text(), 'No momento esta função encontra-se indisponível.')]
                    | //span[contains(text(), 'Você não possui programa de pontos contratado.')]
                    | //td[contains(text(), 'No momento o Extrato de pontos encontra-se indisponível. Por favor, aguarde alguns minutos e tente novamente.')]
                "), 0)
            ) {
                $this->logger->debug($message->getText());

                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            /*
             * Если правила применимы к программе лояльности вашей карты, то при закрытии счета все баллы и бонусы программы
             * автоматически перечисляются партнеру после хотя бы минимальной оплаты счета.
             * Чтобы увидеть текущий баланс или выкупить баллы и бонусы, посетите сайт партнера.
             * Если есть какие-то проблемы, позвоните в сервисный центр карты
             */
            if (($message = $this->waitForElement(WebDriverBy::xpath("//td[contains(text(), 'SE APLICÁVEL PARA O PROGRAMA DE PONTOS DO SEU CARTÃO, APÓS O FECHAMENTO DA SUA FATURA OS PONTOS E BÔNUS DO SEU PROGRAMA SÃO AUTOMATICAMENTE ENVIADOS PARA O PARCEIRO MEDIANTE EFETIVAÇÃO DE AO MENOS O PAGAMENTO MÍNIMO DA FATURA.')]"), 0))) {
                if (empty($this->Properties['Name']) && empty($this->Properties['AccountNumber'])) {
                    // Agência
                    $number = $this->http->FindNodes("//span[@class = 'numAgenciaContaCliente']");

                    if (count($number) == 2) {
                        $this->SetProperty("AccountNumber", $number[0] . "/" . $number[1]);
                    }
                    // Name
                    $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("(//p[contains(@class, 'nomeCliente') or contains(@class, 'clientePers')])[1] | //span[@class = 'TOPnomecliente'] | //span[@class = 'gerente']/strong | //p[@id = 'nomeCliente']")));
                }

                $this->logger->debug($message->getText());

                if (!empty($this->Properties['Name']) && !empty($this->Properties['AccountNumber'])) {
                    $this->SetBalanceNA();
                }
            }
            // AN ERROR HAS OCCURRED IN ITS AUTHENTICATION. PLEASE TRY AGAIN
            if ($message = $this->waitForElement(WebDriverBy::xpath("//td[contains(text(), 'OCORREU UM ERRO NA SUA AUTENTICACAO. POR FAVOR, TENTE NOVAMENTE') or contains(text(), 'No momento esta função encontra-se indisponível. Por favor, aguarde alguns minutos e tente novamente.')]"), 0)) {
                $this->logger->error($message->getText());

                throw new CheckRetryNeededException(3, 1);
            }

            // 502 Bad Gateway
            if ($this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    protected function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        // Aviso: Agência/Conta inválida. Verifique se o número digitado está correto.É preciso incluir o dígito verificador.
        if ($message = $this->waitForElement(WebDriverBy::xpath("//span[@class = 'MsgTxt']"), 0)) {
            if (
                strstr($message->getText(), 'Não foi possível completar o acesso')
                || strstr($message->getText(), 'O acesso está indisponível temporariamente')
            ) {
                $countOfRetries = 2;

                if (strstr($message->getText(), 'Não foi possível completar o acesso')) {
                    $countOfRetries = 3;
                }

                $this->saveResponse();

                throw new CheckRetryNeededException($countOfRetries, 7, $message->getText(), ACCOUNT_PROVIDER_ERROR);
            } else {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
        }
        // A senha eletrônica foi bloqueada!
        if ($message = $this->waitForElement(WebDriverBy::xpath("
                //td[contains(., 'A senha eletr') and contains(., 'bloqueada')]
                | //div[contains(., 'bloqueada') and contains(., ' senha eletr')]
            "), 0)
        ) {
            throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
        }
        // Sessão Finalizada
        // Não foi possível completar o acesso. Por favor, tente novamente.
        /*
         * Desculpe, ocorreu um erro na sua solicitação. Por favor, aguarde alguns minutos e tente novamente.
         * Caso esteja fazendo alguma operação, verifique em comprovantes se foi realizado com sucesso.
         * Se precisar de ajuda ou de mais informações, veja as opções de atendimento para você.
         */
        /*
         * No momento esta função encontra-se indisponível. Por favor, aguarde alguns minutos e tente novamente.
         * Caso necessite de mais informações, contate o SOS Internet:
         * Para qualquer localidade: 0300 100 1213
         * Em dias úteis, das 7h às 22h e em finais de semana e feriados das 8h às 22h.
         */
        // Service Unavailable
        // Desculpe, ocorreu um erro na sua solicitação. Por favor,aguarde alguns minutos e tente novamente.
        if ($message = $this->waitForElement(WebDriverBy::xpath("
                //td[contains(text(), 'Sessão Finalizada')]
                | //span[contains(text(), 'Não foi possível completar o acesso. Por favor, tente novamente.')]
                | //p[contains(text(), 'Desculpe, ocorreu um erro na sua solicitação. Por favor, aguarde alguns minutos e tente novamente.')]
                | //p[contains(text(), 'No momento esta função encontra-se indisponível. Por favor, aguarde alguns minutos e tente novamente.')]
                | //h2[contains(text(), 'Service Unavailable')]
                | //p[contains(text(), 'Desculpe, ocorreu um erro na sua solicitação. Por favor,aguarde alguns minutos e tente novamente.')]
                | //p[contains(text(), 'Desculpe, estamos melhorando nossos serviços. Tente novamente mais tarde.')]
            "), 0)
        ) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }
        // Não foi possível completar o acesso. Por favor, tente novamente.
        if ($message = $this->waitForElement(WebDriverBy::xpath("//span[contains(., 'NÃ£o foi possÃ­vel completar o acesso. Por favor, tente novamente.')]"), 0)) {
            throw new CheckException("Não foi possível completar o acesso. Por favor, tente novamente.", ACCOUNT_PROVIDER_ERROR);
        }
        // Para acessar sua conta na internet você precisa criar sua senha eletrônica
        if ($message = $this->waitForElement(WebDriverBy::xpath("
                //td[
                    contains(., 'Para acessar sua conta na internet voc')
                    or contains(., 'A partir de agora, para realizar o acesso ao Itaú 30 horas Empresas na internet é obrigatória a instalação do Guardião Itaú 30 horas.')
                    or contains(., 'Para instalá-lo você precisa ter o perfil de administrador do computador e ter autorização para executar o arquivo.')
                ]
                | //p[contains(text(), 'Por medida de segurança, para fazer essa transação na internet você precisa instalar o app Itaú no computador ou o guardião Itaú 30 horas. Confira as instruções.')]
                | //h1[contains(text(), 'Cadastro de senha eletrônica')]
            "), 0)
        ) {
            $this->logger->error($message->getText());
            $this->throwProfileUpdateMessageException();
        }
        // A senha eletrônica digitada ainda estÃ¡ errada!
        // Favor preencher o campo 'AgÃªncia/Conta' corretamente.
        if ($message = $this->waitForElement(WebDriverBy::xpath('
                //*[span[contains(text(), "errada!")] and contains(., "A senha eletr")]
                | //td[contains(text(), "Favor preencher o campo \'AgÃªncia/Conta\' corretamente.")]
                | //h1[contains(text(), "A senha eletrônica digitada  está errada")]
                | //p[contains(text(), "Antes de criar sua nova senha eletrônica você precisa desbloquear a senha do seu cartão de débito ou múltiplo.")]
                | //p[contains(text(), "Agência / conta inválida. Verifique e tente novamente. Não se esqueça de inserir o dígito verificador da conta.")]
                | //p[contains(text(), "Esta conta está encerrada. Em caso de dúvidas procure uma agência")]
            '), 0)
        ) {
            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->waitForElement(WebDriverBy::xpath('
                //p[contains(text(), "Acesso bloqueado, para desbloquear entre em contato com seu gerente e informe que se trata de bloqueio 408X.")]
            '), 0)
        ) {
            throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
        }
        /*
         * strange error ("A senha eletrônica digitada está errada.", "A senha eletrônica digitada ainda está errada.", "Sua senha eletrÃ´nica foi bloqueada.") on a valid accounts
         */
        if ($message = $this->waitForElement(WebDriverBy::xpath("//*[@class = 'MSGTexto5' and contains(., 'A senha eletrônica digitada está errada.') or contains(text(), 'A senha eletrônica digitada ainda está errada.')] | //td[@class = 'MSGTexto5' and contains(., ' senha') and contains(., 'bloqueada')]"), 0)) {
            $this->logger->error($message->getText());

            throw new CheckRetryNeededException(2, 7, $message->getText(), ACCOUNT_INVALID_PASSWORD);
        }
        // Esta funcionalidade encontra-se indisponivel no momento.
        if ($message = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Esta funcionalidade encontra-se indisponivel no momento.')]"), 0)) {
            $this->logger->error($message->getText());

            throw new CheckRetryNeededException(2, 7, $message->getText());
        }

        $this->logger->notice("Last saved screen");
        $this->saveResponse();

        // debug
        $logout = $this->waitForElement(WebDriverBy::xpath($this->logoutXpath), 0);

        if (!$logout && $this->waitForElement(WebDriverBy::id("divOverlayHopscotch"), 0) || $this->waitForElement(WebDriverBy::id("divAcessTour"), 0)
            || $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Não, obrigado')]"), 0)) {
            $this->driver->executeScript("$('div#divOverlayHopscotch').remove();");
            $logout = $this->waitForElement(WebDriverBy::xpath($this->logoutXpath), 0);
        }
        $this->saveResponse();

        if (!$logout) {
            $logout = $this->http->FindPreg("/a onclick=\"GA.pushHeader\('botaoSair'\);\"/");
        }

        if ($logout) {
            return true;
        }

        // Sessão finalizada
        if ($question = $this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Sessão finalizada')]"), 0)) {
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
        }

        return false;
    }

    protected function processErrorsAndQuestions()
    {
        $this->logger->notice(__METHOD__);
        $startTime = time();

        while ((time() - $startTime) < 30) {
            // login error
//            $error = $this->getErrorText();
//            if(!empty($error)){
//                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
//            }
//            // success - account shown
//            if($this->waitForElement(WebDriverBy::cssSelector('h3.cH-accountSummaryHead'), 0, true)){
//                return true;
//            }
            // Security checkpoint, two question (Mother/city typically)
            if ($question = $this->waitForElement(WebDriverBy::xpath("//span[@class = 'TRNtexto' and contains(text(), 'do seu iToken final')]"), 0, true)) {
                return $this->processSecurityCheckpoint();
            }
            sleep(1);
        }

        return false;
    }

    protected function getErrorText()
    {
        $this->logger->notice(__METHOD__);
//        $result = $this->waitForElement(WebDriverBy::xpath("//font[@class = 'err-new']"), 0, true);
//        if(empty($result))
//            $result = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'allBadAccountsSpan2Msg']"), 0, true);
//        if(!empty($result)){
//            $result = $result->getText();
//            $result = str_ireplace("Please choose one of the two options below to continue.", "", $result);
//        }
//        return $result;
    }

    /**
     * @deprecated
     *
     * @return bool
     */
    protected function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $q = $this->waitForElement(WebDriverBy::xpath("//span[@class = 'TRNtexto' and contains(text(), 'do seu iToken final')]"), 0, true);

        if ($q) {
            $question = trim($q->getText());
        }

        if (!isset($question)) {
            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "SecurityCheckpoint");

            return false;
        } else {
            $this->logger->debug("Answer: " . $this->Answers[$question]);
            $input = $this->waitForElement(WebDriverBy::xpath("//span[@class = 'TRNtexto' and contains(text(), 'do seu iToken final')]/preceding::input[@title = 'iToken no Aplicativo']"), 0, true);
            $input->sendKeys($this->Answers[$question]);
        }

        if (!empty($question)) {
            $button = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'bt_confirmar']"), 0, true);

            if ($button) {
                $button->click();
            }
            // Código de acesso do iToken inválido.
            $error = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'digo de acesso do iToken inv')]"), 10);

            if (!empty($error)) {
                $error = $error->getText();
                $this->logger->error("error: " . $error);
                $this->logger->debug("removing question: " . $question);
                unset($this->Answers[$question]);
                $this->AskQuestion($question, $error, "SecurityCheckpoint");
            }// if (!empty($error))
            $logout = $this->waitForElement(WebDriverBy::xpath($this->logoutXpath), 0);
            $this->saveResponse();

            if ($logout) {
                return true;
            }
        }

        return false;
    }

    private function askToken()
    {
        $this->logger->notice(__METHOD__);
        // código de segurança
        $token = $this->waitForElement(WebDriverBy::xpath("//label[@id = 'labelCodigoDispApp']"), 0);
        $this->saveResponse();

        if (!$token) {
            return false;
        }
        $final = $this->http->FindSingleNode("//input[@title = 'digite aqui o código gerado pelo aplicativo']/following-sibling::span");
        $question = "digite aqui o código gerado pelo aplicativo";

        if ($final) {
            $question .= " ({$final})";
        }
        $this->holdSession();
        $this->AskQuestion($question, null, "Token");

        return true;
    }
}
