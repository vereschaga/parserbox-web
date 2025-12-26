<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerLanpassSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $lanpass;
    private $responseData;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->useSelenium();
        $this->KeepState = true;
        $this->seleniumOptions->recordRequests = true;
        $this->http->saveScreenshots = true;

//        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
//        $this->setProxyGoProxies();
        $this->setProxyNetNut();
        $this->useChromePuppeteer();

        $this->http->setRandomUserAgent();

        if (
            empty($this->State['chosenResolution'])
            || $this->attempt > 1
        ) {
            $resolutions = [
                [1024, 768],
                [1152, 864],
                [1280, 800],
                [1440, 900],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $this->State['chosenResolution'] = $chosenResolution;
        }
        $this->setScreenResolution($this->State['chosenResolution']);
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->GetURL("https://www.latamairlines.com/us/en/my-account");
        $this->waitForElement(WebDriverBy::xpath("//div[contains(@data-testid, 'header__profile-dropdown-dropdown-menu')]"), 7);
        $this->saveResponse();

        $userId = $this->http->FindPreg("/\"userId\":\"([^\"]+)/");

        if (!isset($userId)) {
            $this->logger->error("userId not found");

            if ($this->http->FindPreg('/(?:Network error 56 - Proxy CONNECT aborted|Received HTTP code 503 from proxy after CONNECT)/', false, $this->http->Error)) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 1);
            }

            return false;
        }

        $this->State["userId"] = $userId;
        $this->State["user"] = $this->http->JsonLog($this->http->FindPreg("/__NEXT_DATA__[^>]*>(\{.+\})<\/script>/"), 3, false, 'ffNumber')->props->user ?? null;
        $headers = [
            "Accept"                           => "application/json, text/plain, */*",
            "Connection"                       => null,
            "Content-Type"                     => null,
            "x-latam-action-name"              => "account-status-profile-web.home.get-user",
            "x-latam-app-session-id"           => $this->State["x-latam-app-session-id"] ?? "4d5bf21b-e12a-4786-9060-083956b06bfc",
            "x-latam-application-country"      => "us",
            "x-latam-application-lang"         => "en",
            "x-latam-application-name"         => "web-userprofile",
            "x-latam-application-oc"           => "us",
            "x-latam-client-name"              => "web-userprofile",
            "x-latam-country"                  => "us",
            "x-latam-lang"                     => "en",
            "x-latam-request-id"               => "8e4ea507-a016-4ca1-a27e-933555051895",
            "x-latam-track-id"                 => "9593800b-28b5-4719-990a-da159ba21574",
        ];
        $data = $this->getApi("https://www.latamairlines.com/bff/web-profile/v1/user/{$userId}/profile", $headers);

        if ($data) {
            $balance = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'lnk-headerMiles']//b | //span[@id = 'yourAccount-milesCard-accumulated-miles']"), 7);
            $this->saveResponse();

            if ($balance) {
                $this->SetBalance($balance->getText());
            }
        }

        $this->http->SetBody($data);
        $response = $this->http->JsonLog(null, 3, false, "ffNumber");

        $email =
            $response->userProfile->emails[0]->contactCode
            ?? $response->email
            ?? $this->State["user"]->email
            ?? null
        ;
        $this->logger->debug("[Email]: {$email}");
        $number =
            $response->userProfile->loyalty->ffNumber
            ?? $response->userProfile->ffNumber
            ?? $response->ffNumber
            ?? $this->State["user"]->ffNumber
            ?? null
        ;
        $this->logger->debug("[Number]: {$number}");

        if (
            strtolower($email) == strtolower($this->AccountFields['Login'])
            || $number == $this->AccountFields['Login']
            || ($number == '27371893816' && strtolower($this->AccountFields['Login']) == 'fabricio.miyasato@gmail.com')
            || str_replace('DEL-', '', $number) == $this->AccountFields['Login']
            || in_array($email, [
                'CELIAFATIMADUARTE@HOTMAIL.COM',
                'PJBLUESMAN+JU@GMAIL.COM',
            ])
        ) {
            return true;
        }

        return false;
    }

    public function IsLoggedIn()
    {
        return $this->loginSuccessful();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.latamairlines.com/us/en/my-account");
        $this->http->RetryCount = 2;
        // access is allowed
        if ($this->waitForElement(WebDriverBy::xpath("//div[contains(@data-testid, 'header__profile-dropdown-dropdown-menu')]"), 7)) {
            $lanpass = $this->getLanpass();

            if ($lanpass->loginSuccessful()) {
                //$this->markProxySuccessful();

                return true;
            }

            return false;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->GetURL('https://www.latamairlines.com/us/en');
        $continue = $this->waitForElement(WebDriverBy::id("country-suggestion-body-reject-change"), 7);

        if ($continue) {
            $continue->click();
        }
        $this->saveResponse();
        $csrf = $this->http->FindPreg("/csrf\":\"([^\"]+)/");

        if (!$csrf) {
            return $this->checkErrors();
        }

        $this->delay();

        /* $loginForm = $this->waitForElement(\WebDriverBy::id("header__profile__lnk-sign-in"), 15);

         if (!$loginForm) {
             return $this->checkErrors();
         }
         $loginForm->click();*/

        $this->http->GetURL('https://www.latamairlines.com/en-us/login?returnTo=https%3A%2F%2Fwww.latamairlines.com%2Fus%2Fen&csrfToken=' . $csrf);

        $this->delay(5);

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'form-input--alias']"), 7);
        $btn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'primary-button']"), 0);
        $this->saveResponse();

        if (!$login || !$btn) {
            $this->logger->error("something went wrong");

            $lanpass = $this->getLanpass();

            if ($lanpass->loginSuccessful()) {
                $this->markProxySuccessful();

                return true;
            }

            return $this->checkErrors();
        }

        $loginInfo = $this->http->JsonLog(base64_decode($this->http->FindPreg("/JSON.parse\(decodeURIComponent\(escape\(window.atob\(\"([^\"]+)/")));
        $csrf = $loginInfo->extraParams->_csrf ?? null;

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->steps = rand(10, 30);

        try {
            $mover->moveToElement($login);
            $mover->click();
            $mover->sendKeys($login, $this->AccountFields['Login'], 5);
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
            $mouse = $this->driver->getMouse();
            $mouse->mouseMove($login->getCoordinates());
            $mouse->click();
            $login->sendKeys($this->AccountFields['Login']);
        }

        $this->delay();
        $this->saveResponse();
        $btn->click();
        $this->waitForElement(WebDriverBy::xpath("//p[contains(text(),'Verify the email or membership number.')]| //input[@id='form-input--password'] | //h5[contains(text(),'We blocked your account for safety')] | //div[@class = 'xp-Alert-Title'] | //*[contains(text(), 'You can try again later.')]"), 15);

        if ($this->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'You can try again later.')]"), 0)) {
            $this->saveResponse();
            $this->markProxyAsInvalid();
            $this->logger->notice('>>> Retry login');
            $this->http->GetURL('https://www.latamairlines.com/en-us/');
            $loginForm = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'header__profile__lnk-sign-in']"), 7);

            if ($loginForm) {
                $this->acceptCookies();

                try {
                    $loginForm->click();
                } catch (UnrecognizedExceptionException $e) {
                    $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);
                }
            }
            $this->saveResponse();

            $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'form-input--alias']"), 10);
            $btn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'primary-button']"), 0);

            if (!$login || !$btn) {
                $this->logger->error("something went wrong");
                $this->saveResponse();

                throw new CheckRetryNeededException(3, 5);

                return false;
            }
            $currentUrl = $this->driver->executeScript('return document.location.href;');

            $mover->moveToElement($login);
            $mover->click();
            $mover->sendKeys($login, $this->AccountFields['Login'], 10);
            //$login->sendKeys($this->AccountFields['Login']);
            $this->waitForElement(WebDriverBy::xpath("//p[contains(text(),'Verify the email or membership number.')]| //input[@id = 'form-input--password'] | //h5[contains(text(),'We blocked your account for safety')] | //div[@class = 'xp-Alert-Title'] | //span[contains(text(), 'Enter a valid email or membership number')]"),
                3);
            $this->saveResponse();
            $this->delay();
            $btn->click();
            $this->waitForElement(WebDriverBy::xpath("//input[@id = 'form-input--password']"), 7);
        }
        $this->saveResponse();

        // pass
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'form-input--password']"), 5);
        $btn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'primary-button']"), 0);
        $this->saveResponse();

        if (!$pass || !$btn) {
            $message = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'xp-Alert-Content'] | //span[contains(text(), 'Enter a valid email or membership number')]"), 0);
            $this->saveResponse();

            if ($message) {
                $message = $message->getText();
                $this->logger->error("[Login Error]: {$message}");

                if (strstr($message, 'We were unable to enter your user ID')) {
                    throw new CheckRetryNeededException(2, 0);
                }

                if (
                    $message == 'We can’t find your user ID'
                    || $message == 'Verify the email or membership number.'
                    || $message == 'Enter a valid email or membership number'
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (strstr($message, 'We were unable to log in. Wait')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                return false;
            }

            if ($this->http->FindSingleNode("//h5[contains(text(), 'We blocked your account for safety')]")) {
                throw new CheckException("We blocked your account for safety", ACCOUNT_LOCKOUT);
            }

            if ($this->http->FindSingleNode("//div/p[contains(text(), 'Check the entered password.')]")) {
                throw new CheckException('Incorrect password. Check the entered password.', ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->FindSingleNode("//p[contains(text(), 'Verify the email or membership number.')]")) {
                throw new CheckException('We can’t find your user ID. Verify the email or membership number.', ACCOUNT_INVALID_PASSWORD);
            }

            return $this->checkErrors();
        }

        $pass->sendKeys($this->AccountFields['Pass']);
        $btn->click();

        $this->waitForElement(WebDriverBy::xpath("//button[@id='change-country'] | //div[@id='header__profile-dropdown'] | //button[@id='cookies-politics-button'] | //div/p[contains(text(),'Check the entered password.')] | //span[contains(text(), 'Your password must be less than 30 characters')]"),
            15);
        $this->saveResponse();

        if ($this->waitForElement(WebDriverBy::xpath("//div/p[contains(text(),'Check the entered password.')]"), 0)) {
            throw new CheckException('Incorrect password. Check the entered password.', ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Your password must be less than 30 characters')]"),
            0)) {
            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }

        $this->acceptCookies();
        /*
        if ($btn = $this->waitForElement(\WebDriverBy::xpath("//button[@id='cookies-politics-button']"), 0)) {
            $btn->click();

            $this->saveResponse();
            // Complete seus dados e viva esta nova experiência
            if ($this->waitForElement(\WebDriverBy::id("documentCode"),
                    0) && $this->waitForElement(\WebDriverBy::id("genders"), 0)) {
                $this->throwProfileUpdateMessageException();
            }
        }
        */

        try {
            $requests = $this->http->driver->browserCommunicator->getRecordedRequests();
        } catch (AwardWallet\Common\Selenium\BrowserCommunicatorException $e) {
            $this->logger->error("BrowserCommunicatorException: " . $e->getMessage(), ['HtmlEncode' => true]);

            $requests = [];
        }
        $responseData = null;

        foreach ($requests as $n => $xhr) {
            $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));

            if (strpos($xhr->request->getUri(), '/usernamepassword/login') !== false) {
                //$this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                $responseData = json_encode($xhr->response->getBody());
            }
        }

        if (!empty($responseData) && is_string($responseData) && !strstr($responseData, 'hiddenform')) {
            $this->logger->debug("xhr response success");
            $this->responseData = $responseData;
            //$this->http->SetBody($responseData);
            $this->saveResponse();
        }

        return true;
    }

    public function Login()
    {
        $lanpass = $this->getLanpass();

        $lanpass->http->SetBody($this->responseData);
        $response = $lanpass->http->JsonLog(null, 5);
        $name = $response->name ?? null;
        $responseCode = $response->code ?? null;

        if (in_array($name, [
            'Error',
            'AnomalyDetected',
        ])
            || !empty($response->description)
        ) {
            $description = $response->description ?? null;
            $this->logger->error("[description]: {$description}");

            if (isset($response->message) && $response->message == 'Invalid hash') {
                $this->sendNotification('Invalid hash');

                throw new CheckRetryNeededException(2, 0, self::CAPTCHA_ERROR_MSG);
            }

            if (isset($response->message) && strstr($response->message, 'INVALID_CAPTCHA')) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            if (
                isset($response->message)
                && (
                    strstr($response->message, '2FA Required')
                    || strstr($response->message, 'Proxy Authentication Required')
            )) {
                if ($this->parseQuestion()) {
                    return false;
                }
            }

            if (
                $description == "We have detected suspicious login behavior and further attempts will be blocked. Please contact the administrator."
                && $responseCode == 'too_many_attempts'
            ) {
                $this->DebugInfo = 'ip locked';
                $this->ErrorReason = self::ERROR_REASON_BLOCK;

                return false;
            }

            if (
                ($description == "Error." && $responseCode == 500)
                || ($description == "Error." && $responseCode == 403)
                || ($name == "Error" && $responseCode == 403)
            ) {
                throw new CheckException("We had a problem. We were unable to log in. Wait a few minutes and try again.", ACCOUNT_PROVIDER_ERROR);
            }

            if (is_string($description)) {
                $this->DebugInfo = $description . " / " . $responseCode;
            }

            return false;
        }

        if (!isset($response->description->data->userId)) {
            $this->logger->error("userId not found");

            $this->markProxyAsInvalid();

            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: '{$currentUrl}'");

            // AccountID: 4088353 etc
            if (
                strstr($currentUrl, '/verificar-conta')
                || strstr($currentUrl, '/verify-account')
                || strstr($currentUrl, '/verifica-account')
                || strstr($currentUrl, '/verificar-cuenta')
                || strstr($currentUrl, '/konto-verifizieren')
                || strstr($currentUrl, '/verifier-compte')
                || strstr($currentUrl, '/user-verification')
                || $currentUrl === 'https://www.latamairlines.com/us/en'
            ) {
                //$this->State["x-latam-app-session-id"] = $this->sessionId; //todo

                if ($lanpass->loginSuccessful()) {
                    $this->markProxySuccessful();

                    return true;
                }

                if (
                    $this->http->Response['code'] == 503
                    && $this->http->FindPreg('/\{"code":503,"message":"Service Unavailable"\}/')
                ) {
                    $this->throwProfileUpdateMessageException();
                }

                return false;
            }

            $message = $response->message ?? null;

            if ($message) {
                if ($message == "Request to Webtask exceeded allowed execution time") {
                    throw new CheckRetryNeededException(2, 10, self::PROVIDER_ERROR_MSG);
                }

                $this->DebugInfo = $message;
            }

            if (
                $this->waitForElement(WebDriverBy::xpath("//button[@id='change-country']"), 0)
                || $this->waitForElement(WebDriverBy::xpath("//div[@id='header__profile-dropdown']"), 0)
            ) {
                //$this->State["x-latam-app-session-id"] = $this->sessionId; //todo
//                $this->State["x-latam-app-session-id"] = $this->generate_uuid();

                if ($lanpass->loginSuccessful()) {
                    $this->markProxySuccessful();

                    return true;
                }

                if (
                    $this->http->Response['code'] == 500
                    && $this->http->FindPreg("#<body><div id=\"react\" data-errorcode=\"500\"></div><script#")
                ) {
                    throw new CheckException("We are working to improve your experience. While we make these adjustments, this service will not be available. Thanks for your understanding.", ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }

            // selenium auth workaround
            if ($this->http->FindPreg("/\"userId\":\"([^\"]+)/")) {
                //$this->State["x-latam-app-session-id"] = $this->sessionId; //todo
                //                $this->State["x-latam-app-session-id"] = $this->generate_uuid();

                if ($lanpass->loginSuccessful()) {
                    $this->markProxySuccessful();

                    return true;
                }
            }// if ($this->http->FindPreg("/\"userId\":\"([^\"]+)/"))

            return false;
        }

        return $this->checkErrors();
    }

    public function parseQuestion($errorMessage = null)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $type = 'Email';
        $radioType = $this->waitForElement(WebDriverBy::xpath("//input[contains(@id, 'radio-EMAIL')]/ancestor::label"), 0);
        $labelType = $this->waitForElement(WebDriverBy::xpath("//input[contains(@id, 'radio-EMAIL')]/ancestor::label//span[contains(text(),'Email')]/following-sibling::span"), 0);

        if (!$radioType) {
            $type = 'SMS';
            $radioType = $this->waitForElement(WebDriverBy::xpath("//input[contains(@id, 'radio-SMS')]/ancestor::label"), 10);
            $labelType = $this->waitForElement(WebDriverBy::xpath("//input[contains(@id, 'radio-SMS')]/ancestor::label//span[contains(text(),'SMS')]/following-sibling::span"), 0);
        }

        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "form-button--primaryAction"]'), 0);

        if (!$labelType || !$radioType || !$button) {
            $this->logger->error("Email not found");

            return false;
        }

        $radioType->click();

        if ($type == 'Email') {
            $question = "Please enter the Code which was sent to the following email address: %s. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
        } else {
            $question = "Please enter the Code which was sent to the following phone number: %s. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
        }

        $question = sprintf($question, $labelType->getText());
        $this->logger->debug("Question to -> {$question}");

        $this->logger->notice("Button enter");
        $button->click();

        $result =
            $this->waitForElement(WebDriverBy::xpath("//span[contains(text(),'We sent you a code of 6 numbers to')]/b[contains(text(),'@')]"), 20)
            ?? $this->http->FindSingleNode("//span[contains(text(),'We sent you a code of 6 numbers to')]/b[contains(text(),'@')]")
            ?? $this->waitForElement(WebDriverBy::xpath("//span[contains(text(),'We sent you a code of 6 numbers to')]/b[contains(text(),'SMS')]"), 0)
            ?? $this->http->FindSingleNode("//span[contains(text(),'We sent you a code of 6 numbers to')]/b[contains(text(),'SMS')]")
        ;
        $this->saveResponse();

        if (!$result) {
            if ($this->http->FindSingleNode('//h1[contains(text(), "We had a problem")]')) {
                throw new CheckRetryNeededException();
            }

            return false;
        }

        $this->holdSession();
        $this->AskQuestion($question, $errorMessage, "Question");

        return true;
    }

    public function processSecurityCheckpoint(): bool
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->logger->debug("Question to -> {$this->Question}");

        $result = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(),'We sent you a code of 6 numbers to')]/b[contains(text(),'@')]"), 0)
        ?? $this->waitForElement(WebDriverBy::xpath("//span[contains(text(),'We sent you a code of 6 numbers to')]/b[contains(text(),'SMS')]"), 0);

        if (!$result) {
            $this->logger->error("2fa not found");

            return false;
        }

        for ($i = 0; $i < strlen($answer); $i++) {
            $code = $this->waitForElement(WebDriverBy::id("form-input--code-$i"), 0);

            if ($code) {
                $code->clear();
                $code->sendKeys($answer[$i]);
            } else {
                $this->logger->error("code not found");
            }
        }
        $this->saveResponse();

        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "form-button--primaryAction"]'), 0);

        if (!$button) {
            $this->logger->error("Button not found");
            $this->saveResponse();

            return false;
        }
        $button->click();
        sleep(5);
        $this->waitForElement(WebDriverBy::xpath("
            //p[contains(text(),'You have 2 attempts left')]
            | //p[contains(text(),'You have 1 try left. Make sure you enter the correct code or request a new one')]
            | //span[contains(@data-testid,'header__profile__lnk-logout--menuitem__label-content')]
        "), 17);

        $message = $this->waitForElement(WebDriverBy::xpath("
            //p[contains(text(),'You have 2 attempts left')]
            | //p[contains(text(),'You have 1 try left. Make sure you enter the correct code or request a new one')]
        "), 0);
        $this->saveResponse();

        if ($message) {
            $message = $message->getText();
            $this->logger->error("resetting answer: " . $message);
            //$this->holdSession();
            // Reset enter code
            $linkReset = $this->waitForElement(WebDriverBy::id("link--reset"), 5);

            if (!$linkReset) {
                $this->logger->error("Link reset not found");

                return false;
            }
            $linkReset->click();

            return $this->parseQuestion($message);
        }

        if ($this->waitForElement(WebDriverBy::xpath('//button[@id = "form-button--primaryAction" and @progress="loading"]'), 0)) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == 'Question') {
            $this->saveResponse();

            if ($this->processSecurityCheckpoint()) {
                $this->saveResponse();

                //$lanpass = $this->getLanpass();
                //$lanpass->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML'));

                return $this->loginSuccessful();
            }
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $lanpass = $this->getLanpass();
        //$this->stopSeleniumBrowser();
        $lanpass->Parse($response);
        $this->SetBalance($lanpass->Balance ?? $this->Balance);
        $this->Properties = $lanpass->Properties;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorCode = $lanpass->ErrorCode;
            $this->ErrorMessage = $lanpass->ErrorMessage;
            $this->DebugInfo = $lanpass->DebugInfo;
        }
    }

    public function ParseItineraries()
    {
        $lanpass = $this->getLanpass();
        $lanpass->ParseItineraries();
    }

    protected function getLanpass()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->lanpass)) {
            $this->lanpass = new TAccountCheckerLanpass();
            $this->lanpass->http = new HttpBrowser("none", new CurlDriver());
            $this->lanpass->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->lanpass->http);
            $this->lanpass->State = $this->State;
            $this->lanpass->AccountFields = $this->AccountFields;
            $this->lanpass->itinerariesMaster = $this->itinerariesMaster;
            $this->lanpass->HistoryStartDate = $this->HistoryStartDate;
            $this->lanpass->historyStartDates = $this->historyStartDates;
            $this->lanpass->http->LogHeaders = $this->http->LogHeaders;
            $this->lanpass->ParseIts = $this->ParseIts;
            $this->lanpass->ParsePastIts = $this->ParsePastIts;
            $this->lanpass->WantHistory = $this->WantHistory;
            $this->lanpass->WantFiles = $this->WantFiles;
            $this->lanpass->strictHistoryStartDate = $this->strictHistoryStartDate;
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->lanpass->http->setDefaultHeader($header, $value);
            }

            $this->lanpass->globalLogger = $this->globalLogger;
            $this->lanpass->logger = $this->logger;
            $this->lanpass->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->lanpass->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->lanpass;
    }

    private function acceptCookies()
    {
        $this->logger->notice(__METHOD__);
        $acceptCookies = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'cookies-politics-button']"), 5);
        $this->saveResponse();

        if ($acceptCookies) {
//            $acceptCookies->click();
            $this->driver->executeScript("
                try { document.getElementById('cookies-politics-button').click(); } catch (e) {}
                try { document.getElementById('country-suggestion-reject-change').click(); } catch (e) {}
            ");
            sleep(2);
            $this->saveResponse();
        }
    }

    private function getApi($url, $headers)
    {
        $this->logger->notice(__METHOD__);
        $response = $this->sendApi($url, $headers);

        if ($this->http->FindPreg('#"message":"Forbidden", "url":#', false, $response) || empty($response)) {
            sleep(random_int(1, 3));
            $response = $this->sendApi($url, $headers);
        }

        // it helps
        if ($this->http->FindPreg('#You don\'t have permission to access#', false, $response) || empty($response)) {
            throw new CheckRetryNeededException(3, 0);
        }

        return $response;
    }

    private function sendApi($url, $headers)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[URL]: $url");

        try {
            $headersStr = "";

            foreach ($headers as $key => $val) {
                $headersStr .= "xhr.setRequestHeader('$key', '$val');\n";
            }
            $this->driver->executeScript("
                var xhr = new XMLHttpRequest();
                xhr.open('GET', '$url');
                $headersStr
    
                xhr.onreadystatechange = function() {
                    if (this.readyState != 4) {
                        return;
                    }
                    localStorage.setItem('responseText', this.responseText);
                }
                xhr.send();     
            ");
            sleep(3);
            $response = $this->driver->executeScript("return localStorage.getItem('responseText')");

            if (empty($response)) {
                sleep(3);
                $response = $this->driver->executeScript("return localStorage.getItem('responseText')");

                if (empty($response)) {
                    sleep(3);
                    $response = $this->driver->executeScript("return localStorage.getItem('responseText')");

                    if (empty($response)) {
                        sleep(3);
                        $response = $this->driver->executeScript("return localStorage.getItem('responseText')");
                    }
                }
            }
            $this->driver->executeScript("localStorage.removeItem('responseText')");
            //$this->logger->info("[Form response]: $response");
        } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $response = null;
        }

        return $response;
    }

    private function delay($maxDelay = 3)
    {
        $this->logger->notice(__METHOD__);
        $delay = random_int(1, $maxDelay);
        $this->logger->debug("sleep -> {$delay}");
        sleep($delay);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//p[contains(text(), 'You can try again later.')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'This page isn’t working')]")
            || $this->waitForElement(WebDriverBy::id("form-input--alias"), 0)
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Estamos trabajando para mejorar tu experiencia")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
