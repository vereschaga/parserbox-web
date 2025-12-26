<?php

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSncf extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $headers = [
        'Accept'          => 'application/json, text/plain, */*',
        'X-App'           => 'ECE',
        'X-Type'          => 'application/json',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $this->useFirefox();
//        $this->disableImages();

        $this->setProxyMount();

        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(true);

        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;

        try {
            $this->http->GetUrl('https://tgvinoui.sncf/home', [], 20);
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException | UnexpectedAlertOpenException $e) {
                $this->logger->error("IsLoggedIn -> exception: " . $e->getMessage());
            } finally {
                $this->logger->debug("IsLoggedIn -> finally");

                throw new CheckRetryNeededException(3, 3);
            }
        }
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//a[contains(@href, '/logout')]")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Your e-mail address must be in xxx@yyy.zz format", ACCOUNT_INVALID_PASSWORD);
        }

        try {
            $this->http->GetURL('https://monidentifiant.sncf/login?login_hint=' . $this->AccountFields['Login'] . '&client_id=ECE_01006&redirect_uri=https:%2F%2Ftgvinoui.sncf%2FredirectAuthentication');
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException | UnexpectedAlertOpenException $e) {
                $this->logger->error("IsLoggedIn -> exception: " . $e->getMessage());
            } finally {
                $this->logger->debug("IsLoggedIn -> finally");

                throw new CheckRetryNeededException(4, 3);
            }
        }

        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "pass1"]'), 10);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "validate"]'), 0);
        $this->saveResponse();

        if (!$passwordInput || !$button) {
            return false;
        }

        $this->logger->notice('remember Me');
        $this->driver->executeScript("document.querySelector('input[id = \"rememberme\"]').checked = true;");

        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();

        $button->click();

        /*
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://tgvinoui.sncf/prehome');

        /*
         * ,"unavailability.content":{"html":"Cher client,\n\nvotre site est actuellement en maintenance. Il sera à nouveau disponible à partir du 28 septembre.\n\nMerci de votre compréhension."
        */
        /*
        if ($message = $this->http->FindPreg("/,\"unavailability.content\":\{\"html\":\"(Cher client,....votre site est actuellement en maintenance. Il sera à nouveau disponible à partir du \d+ \w+\.....Merci de votre compréhension.)/ims")) {
            throw new CheckException(str_replace('\n\n', '', $message), ACCOUNT_PROVIDER_ERROR);
        }

        // Recaptcha
        if ($this->http->Response['code'] == 429) {
            $key = $this->http->FindSingleNode("(//form/div[@class='g-recaptcha']/@data-sitekey)[1]");

            if ($key && !$this->http->ParseForm()) {
                return $this->checkErrors();
            }
            $captcha = $this->parseCaptcha($key);

            if ($captcha !== false) {
                $this->http->SetInputValue('g-recaptcha-response', $captcha);

                if (!$this->http->PostForm()) {
                    return $this->checkErrors();
                }
            }
        }

//        $this->http->GetURL('https://monidentifiant.sncf/login?client_id=ECE_01006_PR1&redirect_uri=https:%2F%2Ftgvinoui.sncf%2FredirectAuthentication');

        $headers = $this->headers + [
            'X-SNCF-Password'    => $this->AccountFields['Pass'],
            'X-SNCF-Username'    => $this->AccountFields['Login'],
        ];
        $this->http->PostURL('https://monidentifiant.sncf/SvcECZ/json/sncfconnect/authenticate?authIndexType=service&authIndexValue=sncfauthpersistent', [], $headers);
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // "alerts":[{"status": true, "id": "131", "content": "Pendant cette maintenance, votre ligne dédiée reste ouverte uniquement pour vos demandes d’achats, échanges et annulations de billets. Le site sera de nouveau disponible le 10/01 à partir de 13h00. Merci de votre compréhension. ", "bgColor": "plum",
        if ($message = $this->http->FindPreg('/"alerts":\[\{"status":\s*true,\s*"id":\s*"131",\s*"content":\s*"(Pendant cette maintenance, votre ligne dédiée reste ouverte uniquement pour vos demandes.+?)",\s*"bgColor":/u')) {
            throw new CheckException(str_replace("\n", '', $message), ACCOUNT_PROVIDER_ERROR);
        }

        // Cher client, votre site est actuellement en maintenance. Veuillez réessayer ultérieurement. Merci de votre compréhension.
        if ($message = $this->http->FindSingleNode("//div[@class='wysiwyg' and contains(text(),'votre site est actuellement en maintenance. Veuillez réessayer ultérieurement.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindPreg("/(LE\s*SERVICE\s*EST\s*TEMPORAIREMENT\s*INDISPONIBLE\s*POUR\s*CAUSE\s*DE\s*MAINTENANCE)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindPreg("/(Votre site est\s*<font[^>]+>actuellement<\/font>\s*en maintenance\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindPreg("/(Le site Programme Voyageur est actuellement indisponible\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // LE SITE MON COMPTE SNCF EST EN MAINTENANCE.
        if ($message = $this->http->FindSingleNode("//h1[contains(., 'est en maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Cher client,
         * L'accès à votre compte est momentanément indisponible.
         * Veuillez réessayer ultérieurement.
         * Merci de votre compréhension.
         */
        if ($message = $this->http->FindSingleNode('//div[@class = "login-account--unavailable__content"]/div[contains(., "L\'accès à votre compte est momentanément indisponible.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("(//span[@id = 'service-indisponible'])[2]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 504 Gateway Time-out
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), '504 Gateway Time-out')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Le site du Programme Voyageur est en maintenance.
        if ($message = $this->http->FindPreg("/Le site du\s*<strong>Programme Voyageur<\/strong>\s*<br\/?>\s*est en maintenance\./ims")) {
            throw new CheckException("Le site du Programme Voyageur est en maintenance.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //h2[contains(text(), "Bonjour")]
            | //span[contains(text(), "Se déconnecter")]
            | //p[contains(text(), "In order to confirm your identity, a verification code has been sent to")]
            | //h1[contains(text(), "Update of our usage rules")]
            | //p[@id = "AuthValidationError"]
            | //p[@id = "PwdValidationError"]
        '), 10);
        $this->saveResponse();

        if ($question = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "In order to confirm your identity, a verification code has been sent to")]'), 0)) {
            $this->holdSession();
            $this->AskQuestion($question->getText(), null, "Question");

            return false;
        }

        if ($this->waitForElement(WebDriverBy::xpath('
                //h2[contains(text(), "Bonjour")]
                | //span[contains(text(), "Se déconnecter")]
            '), 0)
            || $this->http->FindNodes('
                //h2[contains(text(), "Bonjour")]
                | //span[contains(text(), "Se déconnecter")]')
        ) {
            return true;
        }

        $message = $this->http->FindSingleNode('//p[@id = "AuthValidationError"] | //p[@id = "PwdValidationError"]');

        if (!$message) {
            $error = $this->waitForElement(WebDriverBy::xpath('//p[@id = "AuthValidationError"] | //p[@id = "PwdValidationError"]'), 0);

            if ($error) {
                $message = $error->getText();
            }
        }

        if ($message) {
            $this->logger->error("[Error]: '{$message}'");

            if (
                strstr($message, 'Your password or email is incorrect')
                || strstr($message, 'Incorrect password')
            ) {
                throw new CheckException("Your password or email is incorrect", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'Votre mot de passe ou e-mail est incorrect'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return $this->checkErrors();
        }

        if ($this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Update of our usage rules")]'), 0)) {
            $this->throwAcceptTermsMessageException();
        }

        /*
        $response = $this->http->JsonLog();

        if (isset($response->tokenId)) {
            return $this->authComplete();
        }

        if (!isset($response->authId)) {
            $message = $response->message ?? null;
            $this->logger->error("[Error]: '{$message}'");

            if ($message == 'Login failure') {
                throw new CheckException("Your password or email is incorrect", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return $this->checkErrors();
        }

        $this->State['authId'] = $response->authId;
        $this->AskQuestion("You are connecting from an unknown or unregistered device. In order to confirm your identity, a verification code has been sent to {$this->AccountFields['Login']}.", null, "Question");
        */

        return false;
    }

    public function ProcessStep($step)
    {
        if (!isset($this->Answers[$this->Question])) {
            $this->holdSession();
            $this->AskQuestion($this->Question, null, "Question");

            return false;
        }

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $otp = $this->waitForElement(WebDriverBy::xpath('//input[@id = "otpCode"]'), 0);
        $sendOtp = $this->waitForElement(WebDriverBy::xpath('//input[@id = "sendOTP"]'), 0);
        $this->saveResponse();
        $question = $this->http->FindSingleNode('//p[contains(text(), "In order to confirm your identity, a verification code has been sent to")]');

        if (!$question || !$otp || !$sendOtp) {
            if ($this->waitForElement(WebDriverBy::xpath("
                    //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                    | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                    | //title[contains(text(), 'http://localhost:4448/start?text={$this->AccountFields['ProviderCode']}+%7C+{$this->AccountFields['Login']}+%7C+')]
                    | //p[contains(text(), 'Health check')]
                    | //span[contains(text(), 'This site can’t be reached')]
                "), 0)
            ) {
                $this->saveResponse();

                return $this->LoadLoginForm() && $this->Login();
            }

            return false;
        }

        $otp->sendKeys($answer);
        $this->saveResponse();
        $sendOtp->click();

        $this->logger->debug("wait 5 sec");
        sleep(5);
        $this->saveResponse();

        $this->waitForElement(WebDriverBy::xpath('
            //input[@id = "accessAccount"]
            | //p[@id = "invalidOTP"]
            | //h2[contains(text(), "Bonjour")]
        '), 7);
        $this->saveResponse();

        if ($error = $this->waitForElement(WebDriverBy::xpath('//p[@id = "invalidOTP"]'), 0)) {
            $otp->clear();
            $this->holdSession();
            $this->AskQuestion($question, $error->getText(), "Question");

            return false;
        }

        if ($accessAccount = $this->waitForElement(WebDriverBy::xpath('//input[@id = "accessAccount"]'), 0)) {
            $accessAccount->click();

            $this->waitForElement(WebDriverBy::xpath('
                //h2[contains(text(), "Bonjour")]
            '), 10);
            $this->saveResponse();
        }

        return true;
        /*
        $data = [
            "authId"    => $this->State['authId'],
            "callbacks" => [
                [
                    "type"   => "PasswordCallback",
                    "output" => [
                        [
                            "name"  => "prompt",
                            "value" => "One Time Password",
                        ],
                    ],
                    "input"  => [
                        [
                            "name"  => "IDToken1",
                            "value" => $this->Answers[$this->Question],
                        ],
                    ],
                ],
            ],
        ];

        unset($this->Answers[$this->Question]);

        $headers = $this->headers + [
            "Content-Type" => "application/json",
        ];
        $this->http->PostURL('https://monidentifiant.sncf/SvcECZ/json/sncfconnect/authenticate?authIndexType=service&authIndexValue=sncfauthpersistent', json_encode($data), $headers);

        return $this->authComplete();
        */
    }

    public function authComplete()
    {
        $this->logger->notice(__METHOD__);

        $response = $this->http->JsonLog();

        if (!isset($response->tokenId)) {
            return false;
        }

        $this->http->setCookie('iPlanetDirectoryPro', $response->tokenId, '.sncf.com');
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://monidentifiant.sncf/api/accounts/{$response->tokenId}/rememberMe", '{"rememberMe":true}', [
            "Accept"       => "application/json",
            "Content-type" => "application/json",
            "X-App"        => "ECE",
        ]);
        $this->http->RetryCount = 2;

        $this->http->PostURL('https://monidentifiant.sncf/SvcECZ/oauth2/authorize?response_type=code&scope=openid%20profile%20&client_id=ECE_01006&realm=sncfconnect&redirect_uri=https://tgvinoui.sncf/redirectAuthentication&decision=allow&output=embed&csrf=' . $response->tokenId, [], [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);

        // "isAuthenticating":false,"error":{"isTechnical":true,"message":"{\"timestamp\":\"2020-01-27T05:35:51.945+0000\",\"status\":500,\"error\":\"Internal Server Error\",\"message\":\"Erreur à l'interrogation du service ICC.aggregateCustomerInfos de ACW \"
        if ($this->http->FindPreg("/Erreur à l'interrogation du service ICC.aggregateCustomerInfos de ACW/")) {// AccountID: 2978299
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL("https://tgvinoui.sncf/");

        if ($this->http->FindPreg("/compteurFid\":/")) {
            return true;
        }

        if ($this->http->FindPreg("/Votre fidélité est récompensée/")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - vos points
        $balance = $this->http->FindPreg("/,\"compteurFid\":([\-\d]+)/");
        $this->logger->notice("[Balance]: {$balance}");

        $noBalance = $this->http->FindPreg("/canalRenvoie\":\"(?:AGV|VSC|GBLD|)\",\"compteurFid\":null,/") ?? $this->http->FindPreg("/\"(globalBalance\":null),/");

        $flight = true;

        if (!$this->http->FindPreg("/canalRenvoie\":\"VSC\",\"compteurFid\":0,/")) {
            $flight = false;
            $this->SetBalance($balance);
        }

        try {
            $this->http->GetURL('https://tgvinoui.sncf/programme/cartes');
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 0);
        }
        $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "program__points")]'), 5);
        $this->saveResponse();

        if (!isset($balance) && !$noBalance) {
            // Balance - vos points
            $balance = $this->http->FindPreg("/,\"compteurFid\":([\-\d]+)/");
            $this->logger->notice("[Balance]: {$balance}");

            $noBalance = $this->http->FindPreg("/canalRenvoie\":\"(?:AGV|VSC|GBLD|)\",\"compteurFid\":null,/") ?? $this->http->FindPreg("/\"(globalBalance\":null),/");

            if (!$this->http->FindPreg("/canalRenvoie\":\"VSC\",\"compteurFid\":0,/")) {
                $flight = false;
                $this->SetBalance($balance);
            }
        }

        // Trajet(s)
        if ($flight === true) {
            $this->SetProperty('Flights', $this->http->FindPreg("/\"trajet\":\{\"compteur\":(\d+)/"));
        }
        // Status Points
        $this->SetProperty("StatusPoints", $this->http->FindPreg("/soldePointsStatut\":(\d+)/"));
        // Status Expiration
        $this->SetProperty("StatusExpiration", $this->http->FindPreg("/\"dateExpiration\":\"([^\"]+)/"));

        $this->logger->notice("[Exp date]: {$this->http->FindPreg("/\"datePointExpiration\":\"([^\"]+)/")}");

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/infoReducer\":\{\"civility\":\"[^\"]+\",\"lastName\":\"[^\"]+\"\,\"firstName\":\"([^\"]+)/") . " " . $this->http->FindPreg("/infoReducer\":\{\"civility\":\"[^\"]+\",\"lastName\":\"([^\"]+)\"\,/")));
        // Card Number - N° Carte
        $this->SetProperty("CardNumber", $this->http->FindPreg("/numFid\":\"(\d+)/"));
        // Status
        if ($status = $this->http->FindPreg("/statut\":\"([^\"]+)/")) {
            $status = basename($status);

            switch ($status) {
                case 'Voyageur':
                case 'GV Transitoire':
                    $this->SetProperty('Status', "Voyageur");

                    break;

                case 'Grand Voyageur':
                    $this->SetProperty('Status', "Grand Voyageur");

                    break;

                case 'GV Plus':
                    $this->SetProperty('Status', "Grand Voyageur Plus");

                    break;

                case 'GV Le Club':
                case 'Grand Voyageur Le Club':
                    $this->SetProperty('Status', "Grand Voyageur Le Club");

                    break;

                // not status
                case 'En cours':
                case 'Expiré':
                case 'Futur':
                    break;

                default:
                    $this->logger->notice("Status: {$status}");
                    $this->sendNotification("New status was found: {$status}");
            }
        }

        // Discount cards, // refs #21511
        // Ma Carte de Réduction -> Carte Avantage Adulte
        $discountCards = $this->http->XPath->query('//div[contains(@class, "my-essential--desktop")]//div[@class = "essential-thumbnail--card__info__content"]');
        $this->logger->debug("Total {$discountCards->length} discount cards were found");

        foreach ($discountCards as $discountCard) {
            $name = $this->http->FindSingleNode('.//p[contains(@class, "essential-thumbnail--card__type")]', $discountCard);
            $cardNumber = $this->http->FindSingleNode('.//p[contains(@class, "essential-thumbnail--card__number")]', $discountCard);
            $exp = $this->http->FindSingleNode('.//p[contains(@class, "essential-thumbnail--card__expiration-date")]', $discountCard, true, "/(\d{2}\/\d{2}\/\d{4})$/");
            $expDate = strtotime($this->ModifyDateFormat($exp), false);

            if (isset($cardNumber, $name, $exp) && $expDate) {
                $this->AddSubAccount([
                    'Code'           => 'DiscountCards' . str_replace(' ', '', $cardNumber),
                    'DisplayName'    => $name . ' - ' . $cardNumber,
                    'Balance'        => null,
                    'CardNumber'     => $cardNumber,
                    'ExpirationDate' => $expDate,
                ], true);
            }
        }// foreach ($discountCards as $discountCard)

        // Vouchers
        $this->http->GetURL('https://tgvinoui.sncf/compte/avantages/bons-achat');
        $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Vous n\'avez pas de bons d\'achat en cours de validité")]'), 3);
        $this->saveResponse();
        $nodes = $this->http->FindPreg('/(\{"vouchers":\[.+\}\]\}),\"updateInfosReducer/ims');
//        $this->logger->debug(var_export($nodes, true), ['pre' => true]);
        $vouchers = $this->http->JsonLog($nodes);

        if (isset($vouchers->vouchers)) {
            $this->logger->debug("Total " . count($vouchers->vouchers) . " vouchers were found");
            $this->SetProperty("CombineSubAccounts", false);

            foreach ($vouchers->vouchers as $voucher) {
//                $this->logger->debug(var_export($voucher, true), ['pre' => true]);
                $code = $voucher->code;
                $name = $voucher->longLabel . " ({$voucher->technicalID})";
                $exp = $voucher->endValidityDate;
                $expDate = strtotime($this->ModifyDateFormat($exp), false);

                if (isset($code, $name, $exp) && $expDate) {
                    $this->AddSubAccount([
                        'Code'           => 'sncfVouchers' . $code,
                        'DisplayName'    => $name . ' - ' . $code,
                        'Balance'        => null,
                        'ExpirationDate' => $expDate,
                    ], true);
                }
            }
        }

        // todo: table with exp dates not exist since 28 Nov 2019

        // Expiration Date  // refs #8942, 17635
        $this->http->GetURL('https://tgvinoui.sncf/recompense/vos-points');
        sleep(3);
        $this->saveResponse();
        /*
        $expNodes = $this->http->XPath->query("//ul[@id = 'my-points__accordion__content']/li");
        $this->logger->debug("Total {$expNodes->length} nodes were found");
        for ($i = 0; $i < $expNodes->length; $i++) {
            $matches = $this->http->FindPregAll("/(?<month>\w+)\s*(?<year>\d{4})\s*\:\s*(?<points>[\d\.\,]+)/ims", $expNodes->item($i)->nodeValue, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $match['month'] = MonthTranslate::translate($match['month'], 'fr');
                $this->logger->debug('MonthTranslate: ' . $match['month'].' '.$match['year']);
                $month = date("m", strtotime($match['month'].' '.$match['year']));
                $exp = mktime(0, 0, 0, ($month + 1), 0, $match['year']);
                // Expiring Balance
                $this->SetProperty("ExpiringBalance", $match['points']);
                if ($match['points'] > 0) {
                    $this->SetExpirationDate($exp);
                    break;
                }// if ($match['points'] > 0)
            }
        }// for ($i = 0; $i < $expNodes->length; $i++)
        */

        // AccountID: 2699589
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (
                !empty($this->Properties['Name'])
                /*
                && !empty($this->Properties['Status'])
                && !empty($this->Properties['CardNumber'])
                && ((isset($this->Properties['Flights']) && $this->http->currentUrl() == 'https://tgvinoui.sncf/prehome') || $noBalance)
                */
                && $noBalance
            ) {
                $this->SetBalanceNA();

                return;
            }

            try {
                $currentUrl = $this->http->currentUrl();
            } catch (NoSuchDriverException $e) {
                $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(3, 0);
            }

            // AccountID: 5690633
            if (
                !empty($this->Properties['Name'])
                && $currentUrl == 'https://tgvinoui.sncf/prehome'
                && $noBalance
            ) {
                $this->SetBalanceNA();

                return;
            }

            if (
                /*empty($this->Properties['Name'])
                &&*/ empty($this->Properties['Status'])
                && empty($this->Properties['CardNumber'])
                && empty($this->Properties['Flights'])
                && $this->http->currentUrl() == 'https://tgvinoui.sncf/prehome'
                && ($this->http->FindPreg("/Votre fidélité est récompensée/")
                    || in_array($this->AccountFields['Login'], [
                        "mimery7@gmail.com", // Account ID: 5205029
                        "aloiselier@gmail.com", // Account ID: 2187925
                        "potts@outlook.fr", // Account ID: 4153042
                        "chris.marra@gmail.com", // Account ID: 5395292
                        "chazrick46@mac.com", // Account ID: 4857767
                        "pmcarrion@gmail.com", // Account ID: 4513387
                        "deborah.acker@gmail.com", // Account ID: 5452079
                    ]))
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_WARNING);
            }
        }
    }

    protected function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }
}
