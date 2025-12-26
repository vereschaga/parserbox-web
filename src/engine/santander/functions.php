<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSantander extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $captcha = "";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        if (empty($this->State['headers'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.esfera.com.vc/");

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Estamos arrumando nosso site para vc")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] != 200) {
            if ($this->http->FindSingleNode('//h1[contains(text(), "308 Permanent Redirect")]')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $headers = [
            "Accept-Encoding"  => "gzip, deflate, br",
            "Content-Type"     => "application/json",
            "X-CCProfileType"  => "storefrontUI",
            "X-CCViewport"     => "lg",
            "x-ccsite"         => "esfera",
            "X-Requested-With" => "XMLHttpRequest",
        ];
//        X-CCVisitId: -6c02aa33:16dc8d9382d:-30bc-129.80.155.72
        //X-CCVisitorId: 139CjI0DdDvVcjXcPCrTVwY62CP2gOpeveHyBVjjyHYb9EgC06A
        //x-dtpc: 1$234287031_450h22vWXXPQODDRSSUQXMYESQHMDXYBNTVASDM

        $this->http->PostURL("https://www.esfera.com.vc/ccstoreui/v1/samlAuthnRequest?encode=true", "{}", $headers);
        $response = $this->http->JsonLog();
        $authnRequestTarget = $response->authnRequestTarget ?? null;
        $authnRequest = $response->authnRequest ?? null;

        if (!$authnRequestTarget || !$authnRequest) {
            if ($this->http->FindSingleNode('//h1[contains(text(), "Service Unavailable - Zero size object")]')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (empty($this->http->Response['body'])) {
                throw new CheckRetryNeededException();
            }

            return false;
        }
        $data = [
            "SAMLRequest" => $authnRequest,
            "RelayState"  => "",
        ];
        $this->http->PostURL($authnRequestTarget, $data);

        if (!$this->http->ParseForm("kc-form-login")) {
            if (
                $this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)
                && ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION
            ) {
                throw new CheckRetryNeededException(3);
            }

            return $this->checkErrors();
        }

        $this->getCookiesFromSelenium($this->http->currentUrl());

        return true;

        $captchaKey = $this->http->FindSingleNode('(//form[@id = "kc-form-login"]//div[@class = "g-recaptcha"]/@data-sitekey)[1]');
        $captcha = $this->parseCaptcha($captchaKey);

        if ($captcha === false) {
            return false;
        }

        $this->captcha = $captcha;
        // not needed
        /*
        $data = [
            "captcha" => $captcha,
            "cpf"     => $this->AccountFields['Login'],
            "cnpj"    => "",
        ];
        $headers = [
            "Origin"          => "https://auth.esfera.site",
            "Content-Type"    => "application/json",
            "Accept"          => "application/json, text/javascript, *
        /*; q=0.01",
            "Accept-Encoding" => "gzip, deflate, br",
        ];
        $this->http->PostURL("https://api.esfera.site/public/v1/profiles/exist", json_encode($data), $headers);
        $response = $this->http->JsonLog();
        $this->http->RetryCount = 2;
        // {"firstAccess":true,"hasEmail":true,"userDisabled":null,"numFailures":0,"lastFailure":0,"occValid":true,"email":"********@*****","profileStatus":"P"}
        /*
         * Novo site, nova senha
         * Você precisa de um código para criar uma nova senha e acessar a nova Esfera.
         * /
        $profileStatus = $response->profileStatus ?? null;
        if ($profileStatus == 'P') {
            $this->throwProfileUpdateMessageException();
        }
        // success
        if ($profileStatus == 'A') {
        }
        */
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("g-recaptcha-response", $captcha);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Desculpe, não foi possível prosseguir neste momento. Por favor, tente novamente mais tarde. (Código Z25)
        if ($message = $this->http->FindSingleNode("//div[@id = 'error_explanation' and contains(., 'Desculpe, não foi possível prosseguir neste momento.')]/text()[last()] | //h2[contains(text(), 'Desculpe, não foi possível prosseguir neste momento.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Estamos arrumando nosso site para vc
        if ($message = $this->http->FindSingleNode('
                //div[contains(text(), "Estamos arrumando nosso site para vc")]
                | //title[contains(text(), "We\'re sorry, but something went wrong")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // provider error
        if (
            ($this->http->currentUrl() == 'https://resgates.pontosesfera.com.br/extract' && $this->http->Response['code'] == 500)
            || $this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "503 Service Temporarily Unavailable")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $oauth_token = $this->http->getCookieByName("oauth_token_secret-storefrontUI", "www.esfera.com.vc");

        if ($oauth_token) {
            $accessToken = trim(urldecode("{$oauth_token}"), '"');

            $this->State['headers'] = [
                "Accept"           => "application/json, text/javascript, */*; q=0.01",
                "Accept-Encoding"  => "gzip, deflate, br",
                "Authorization"    => "Bearer {$accessToken}",
                "Content-Type"     => "application/json",
                "X-Requested-With" => "XMLHttpRequest",
            ];
            // Access is allowed
            if ($this->loginSuccessful()) {
                return true;
            }
        }

        // CPF/CNPJ ou senha inválida.
        if ($message = $this->http->FindSingleNode("//span[@id = 'template-error' and normalize-space(.) != ''] | //div[@id = 'message-error-login' and not(contains(@class, 'util-hidden'))] | //span[contains(text(), 'Utilize um CPF ou CNPJ válido.') and not(contains(@class, 'util-hidden'))]")) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'CPF/CNPJ ou senha inválida.'
                || $message == 'Utilize um CPF ou CNPJ válido.'
                || $message == 'Confira as informações e tente novamente'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }// if ($message = $this->http->FindSingleNode("//span[@id = 'error_explanation']"))

        if ($this->http->FindSingleNode("//div[@id = 'terms-and-conditions' and not(contains(@class, 'util-hidden'))]//p[contains(text(), 'Você precisa ler e aceitar os Termos e Condições de uso para entrar no Site.')]")
            || $this->http->FindSingleNode("//div[@id = 'terms-and-conditions' and not(contains(@class, 'util-hidden'))]//h2[contains(text(), 'Termos e Condições')]")
        ) {
            $this->throwAcceptTermsMessageException();
        }

        // block or captcha issue?
        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "Algo deu errado.")]')) {
            $this->DebugInfo = 'Algo deu errado';
            // on some accounts error always exist: $this->AccountFields['Login'] == '05693812154', '18636168880'
            throw new CheckRetryNeededException(3, 0, $message, ACCOUNT_PROVIDER_ERROR);
        }

        // har dcode, temporarily
        // AccountID: 6652379
        if ($this->AccountFields['Login'] == '397555535860') {
            throw new CheckException("Utilize um CPF ou CNPJ válido.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();

        if (!$this->http->PostForm()) {
            if ($this->http->Response['code'] == 400) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(0, 3, self::CAPTCHA_ERROR_MSG); //todo
            }

            if ($this->http->FindSingleNode('//p[contains(text(), "Erro inesperado ao tentar efetuar a autenticação.")]')) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 0);
            }

            return $this->checkErrors();
        }

        if ($this->http->ParseForm(null, '//form[contains(@action, "SAML/post")]')) {
            $this->http->PostForm();
        }

        $saml = $this->http->FindPreg("/getSamlResponse\(\)\s*\{\s*return\s*\"([^\"]+)/");

        if ($saml) {
            $data = [
                "grant_type"    => "saml_credentials",
                "saml_response" => $saml,
                "relay_state"   => "",
            ];
            $this->http->PostURL("https://www.esfera.com.vc/ccstoreui/v1/login/", $data);
            $response = $this->http->JsonLog();
            $accessToken = $response->access_token ?? null;

            if ($accessToken) {
                $this->captchaReporting($this->recognizer);
                $this->State['headers'] = [
                    "Accept"           => "application/json, text/javascript, */*; q=0.01",
                    "Accept-Encoding"  => "gzip, deflate, br",
                    "Authorization"    => "Bearer {$accessToken}",
                    "Content-Type"     => "application/json",
                    "X-Requested-With" => "XMLHttpRequest",
                ];
                $this->http->setCookie("oauth_token_secret-storefrontUI", urlencode("\"{$accessToken}\""), "www.esfera.com.vc");
                // Access is allowed
                if ($this->loginSuccessful()) {
                    return true;
                }
            } else {
                $message = $response->error ?? null;
                // Log-on malsucedido. Os detalhes informados não correspondem aos nossos registros, tente novamente.
                if ($message == 'invalid_request' && $this->http->Response['code'] == 401) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException("Log-on malsucedido. Os detalhes informados não correspondem aos nossos registros, tente novamente.", ACCOUNT_INVALID_PASSWORD);
                }
            }
        }
        // CPF/CNPJ ou senha inválida.
        if ($message = $this->http->FindSingleNode("//span[@id = 'template-error']")) {
            if (
                $message == 'CPF/CNPJ ou senha inválida.'
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }// if ($message = $this->http->FindSingleNode("//span[@id = 'error_explanation']"))

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);

        // fix for AccountID: 5732162
        if (strstr($response->firstName, 'CANTINA SP PIZZARIA E RESTAURANTE EIRELI')) {
            $response->firstName = 'CANTINA SP PIZZARIA E RESTAURANTE EIRELI';
        }
        // fix for AccountID: 6825814
        if (strstr($response->firstName, $response->lastName)) {
            $response->firstName = $response->lastName;
        }

        // Name
        $this->SetProperty("Name", beautifulName($response->firstName . " " . $response->lastName));

        // AccountID: 5637127
        if ($response->firstName === $response->lastName) {
            $this->SetProperty("Name", beautifulName($response->firstName));
        }

        $profileId = $response->repositoryId ?? null;

        if (!$profileId) {
            $this->logger->error("profileId no found");

            return;
        }
        $data = [
            "op"        => "inquireBalance",
            "payments"  => [
                [
                    "type"   => "loyaltyPoints",
                    "seqNum" => "0",
                ],
            ],
            "profileId" => $profileId,
        ];
        $this->http->PostURL("https://www.esfera.com.vc/ccstoreui/v1/payment", json_encode($data), $this->State['headers']);
        $response = $this->http->JsonLog();
        /*
          'loyaltyPointDetails' =>
          array (
            0 =>
            stdClass::__set_state(array(
               'membershipType' => 'Base points',
               'pointsBalance' => '111',
               'pointsType' => 'BASE',
            )),
            1 =>
            stdClass::__set_state(array(
               'membershipType' => 'Diff points',
               'pointsBalance' => '222',
               'pointsType' => 'DIFF',
            )),
         */
        $loyaltyPointDetails = $response->paymentResponses[0]->loyaltyPrograms[0]->loyaltyPointDetails ?? [];

        foreach ($loyaltyPointDetails as $loyaltyPointDetail) {
            if (!isset($balance)) {
                $balance = $loyaltyPointDetail->pointsBalance;
            } else {
                $balance += $loyaltyPointDetail->pointsBalance;
            }
        }// foreach ($loyaltyPointDetails as $loyaltyPointDetail)

        if (
            isset($response->paymentResponses) && count($response->paymentResponses) > 1
            || isset($response->paymentResponses[0]->loyaltyPrograms) && count($response->paymentResponses[0]->loyaltyPrograms) > 1
        ) {
            $this->logger->error("may be mistake in balance, need to check on the website");

            return;
        }
        // Balance - Saldo Atual de Bônus
        if (isset($balance)) {
            $this->SetBalance($balance);
        }

        $this->logger->info('Expiration date', ['Header' => 3]);
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        // Expiring Balance
        $dateFrom = date('d/m/Y', strtotime("-4 month"));
        $dateTo = date('d/m/Y', strtotime("+1 month"));
        $this->http->GetURL("https://www.esfera.com.vc/ccstorex/custom/compasso/general/customer/sum-monthly-expiration-forecast?includePersonalInfo=true&Identifier={$this->AccountFields['Login']}&IdentifierType=cpf&dateFrom={$dateFrom}&dateTo={$dateTo}&g-recaptcha-response={$this->captcha}", $headers);
        $response = $this->http->JsonLog();

        if (isset($response->monthly)) {
            foreach ($response->monthly as $month) {
                $date = strtotime("+1 month", strtotime($this->ModifyDateFormat("01/" . $month->period)));

                if (!isset($exp) || $exp > $date) {
                    $exp = $date;
                    $this->SetExpirationDate($exp);
                    $this->SetProperty("ExpiringBalance", number_format($month->points, 0, ",", "."));
                }
            }
        }

        $dateFrom = date('d/m/Y', strtotime("-3 month +3 day"));
        $dateTo = date('d/m/Y');
        $this->http->GetURL("https://www.esfera.com.vc/ccstorex/custom/compasso/general/customer/expired-points?includePersonalInfo=true&Identifier={$this->AccountFields['Login']}&IdentifierType=cpf&dateFrom={$dateFrom}&dateTo={$dateTo}&g-recaptcha-response={$this->captcha}", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        // Expirados (nos últimos 90 dias)
        if (isset($response->total)) {
            $this->SetProperty("Expired", number_format($response->total, 0, ",", "."));
        }
    }

    protected function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
            "version"   => "enterprise",
            "action"    => "authenticationLogin",
            "min_score" => 0.9,
        ];

//        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.7,
            "pageAction"   => "authenticationLogin",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.esfera.com.vc/ccstoreui/v1/profiles/current", $this->State['headers']);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        return $response->login ?? false;
    }

    private function pontoses()
    {
        $this->logger->notice(__METHOD__);
        // retries
        if (
            $this->http->Response['code'] == 504
            || $this->http->currentUrl() == 'https://resgates.pontosesfera.com.br/error/unavailable_for_pf'
        ) {
            if ($this->http->FindSingleNode("//h2[contains(text(), 'Este site é exclusivo para PJ, para acessar o site PF clique abaixo.')]")) {
                throw new CheckException("Please enter your CPF instead of email address in order to get your account updated", ACCOUNT_INVALID_PASSWORD);
            }

            throw new CheckRetryNeededException(3, 1);
        }
    }

    private function getCookiesFromSelenium($url)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->setProxyGoProxies(null, 'br'); // "Algo deu errado" workaround

            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            /*
            $selenium->useFirefox();
            $selenium->setKeepProfile(true);
            */

//            $selenium->useCache();// it's broke auth
//            $selenium->disableImages();
            $selenium->usePacFile(false); // it's broke auth

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www.esfera.com.vc/");

            $loginBtn = $selenium->waitForElement(WebDriverBy::xpath("//a[@id = 'esfera-login-btn-occ']"), 30);
            $this->savePageToLogs($selenium);

            if (!$loginBtn) {
                if ($this->http->FindSingleNode("//a[@id = 'esfera-login-btn-occ']/@id")) {
                    $retry = true;
                }

                return $this->checkErrors();
            }

            try {
                $loginBtn->click();
            } catch (Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
                $this->logger->error("ElementClickInterceptedException: {$e->getMessage()}");
                $selenium->driver->executeScript("document.getElementById('esfera-login-btn-occ').click();");
            }
//            $selenium->http->GetURL($url);

            // wait loading capthca
            $selenium->waitForElement(WebDriverBy::xpath('//div[@class = "grecaptcha-logo"]'), 15);

            // retries, provider bug fix
            if ($selenium->waitForElement(WebDriverBy::xpath("//a[@id = 'esfera-login-btn-occ']"), 0)) {
                $this->savePageToLogs($selenium);
                $retry = true;

                return false;
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "username"]'), 5);
            $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$pass) {
                if (
                    $this->http->FindSingleNode('//h1[contains(text(), "The connection has timed out")]')
                    || !$this->http->FindPreg("/<body/")
                ) {
                    $selenium->markProxyAsInvalid();
                    $retry = true;
                }

                return $this->checkErrors();
            }

            $this->logger->debug("login");
            $mover = new MouseMover($selenium->driver);
            /*
            $mover->logger = $this->logger;
            $mover->duration = rand(300, 1000);
            $mover->steps = rand(10, 20);
            */

            $login->click();
            $login->clear();
            /*
            $mover->moveToElement($login);
            */
            $mover->sendKeys($login, $this->AccountFields['Login'], 7);
//            $login->sendKeys($this->AccountFields['Login']);
            /*
            $mover->moveToElement($pass);
            */
            $mover->sendKeys($pass, $this->AccountFields['Pass'], 7);
//            $pass->sendKeys($this->AccountFields['Pass']);

            $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'loginButton' and not(@disabled)]"), 5);
            $this->savePageToLogs($selenium);

            if (!$btn && $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Utilize um CPF ou CNPJ válido.')]"), 0)) {
                $login->clear();
                $mover->sendKeys($login, $this->AccountFields['Login'], 7);
                $pass->clear();
                $mover->sendKeys($pass, $this->AccountFields['Pass'], 7);
                $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'loginButton' and not(@disabled)]"), 5);
                $this->savePageToLogs($selenium);
            // recpatcha loading issue
            } elseif ($selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'loginButton' and @disabled]"), 0)) {
                $retry = true;
            }

            if (!$btn) {
                return $this->checkErrors();
            }

            try {
                /*
                $mover->moveToElement($btn);
                $mover->click();
                */
                $btn->click();
            } catch (Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
                sleep(2);

                $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'loginButton' and not(@disabled)]"), 5);
                $this->savePageToLogs($selenium);
                $btn->click();
            }

            $res = $selenium->waitFor(function () use ($selenium) {
                return
                    $selenium->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Olá,')] | //div[@id = 'message-error-login'] | //h2[contains(text(), 'Algo deu errado.')] | //div[@id = 'terms-and-conditions' and not(contains(@class, 'util-hidden'))] | //label[contains(text(), 'Declaro que li e aceito os')] | //div[@id='terms-and-conditions']"), 0)
                    || $selenium->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Olá,')] | //h2[contains(text(), 'Algo deu errado.')]"), 0, false);
            }, 40);
            $this->savePageToLogs($selenium);

            if ($this->http->FindSingleNode('//div[contains(text(), "Aguarde, estamos carregando as melhores opções pra vc")]')) {
                $res = $selenium->waitFor(function () use ($selenium) {
                    return
                        $selenium->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Olá,')] | //div[@id = 'message-error-login'] | //h2[contains(text(), 'Algo deu errado.')] | //div[@id = 'terms-and-conditions' and not(contains(@class, 'util-hidden'))] | //h1[contains(text(), 'The connection has timed out')] | //div[@id='terms-and-conditions']"), 0)
                        || $selenium->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Olá,')] | //h2[contains(text(), 'Algo deu errado.')]"), 0, false);
                }, 40);
                $this->savePageToLogs($selenium);
            }

            if ($this->http->FindSingleNode('//h2[contains(text(), "Algo deu errado.")]')) {
                $selenium->markProxyAsInvalid();
            } elseif (
                $this->http->FindSingleNode('//h1[contains(text(), "The connection has timed out")]')
                || (
                    !$res
                    && $this->http->FindSingleNode('//button[contains(text(), "Acessar")]')
                    && !$this->http->FindSingleNode("//label[contains(text(), 'Declaro que li e aceito os')]")
                    && !$this->http->FindSingleNode("//div[@id='terms-and-conditions' and not(contains(@class, 'hidden'))]")
                )
            ) {
                $selenium->markProxyAsInvalid();
                $retry = true;
            // empty page, auth failed
            } elseif (
                !$res
                && $this->http->FindPreg('/<body><\w+ id=\"[^\"]+\" style="display: none;"><\/\w+><\w+ id=\"[^\"]+\" style="display: none;"><\/\w+><\/body>/')
            ) {
                $retry = true;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (UnknownServerException | SessionNotCreatedException | Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage(), ['pre' => true]);
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }
}
