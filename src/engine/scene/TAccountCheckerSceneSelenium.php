<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSceneSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use PriceTools;
    use ProxyList;

    public const WAIT_TIMEOUT = 7;
    private $clientId = "0f9c6cf3-0649-40d7-b803-684a3dbc2e89";
    private $clientRequestId = "79d9b844-9b2c-422f-aac3-48f37d010875";

    private $retry = 0;
    private $browser;

    private $currentItin = 0;

    private array $response = [];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();

        $this->http->saveScreenshots = true;
        $this->seleniumOptions->recordRequests = true;
        $resolutions = [
            [1360, 768],
        ];
        $chosenResolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($chosenResolution);
        $this->useCache();
        if ($this->attempt == 1) {
            $this->setProxyBrightData(null, 'static', 'ca');
        } else {
            $this->setProxyGoProxies(null, 'ca');
        }

        $this->useGoogleChrome();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function LoadLoginForm()
    {
        $sLogin = $this->AccountFields['Login'];

        if (is_numeric($sLogin)) {
            if (!$this->http->FindPreg("/^604646\d{9,}/ims", false, $sLogin)) {
                $sLogin = "604646" . $sLogin;
            }
            // stupid user fix
            $sLogin = str_replace('.', '', $sLogin);
        }
        /*
        if (strlen($sLogin) != 16 || !is_numeric($sLogin) || !strstr($sLogin, "604646")) {
            throw new CheckException("The SCENE membership card number and password you have entered do not match.", ACCOUNT_INVALID_PASSWORD);
        }
        */
        $this->http->removeCookies();

        $this->http->GetURL('https://www.sceneplus.ca/');
        $btn = $this->waitForElement(WebDriverBy::xpath("(//button[span[contains(text(),'Sign in')]])[1]"), self::WAIT_TIMEOUT);

        if (!$btn) {
            return $this->checkErrors();
        }

        $btn->click();

//        $url = "https://sceneplusb2c.b2clogin.com/sceneplusb2c.onmicrosoft.com/b2c_1a_signin/oauth2/v2.0/authorize?client_id={$this->clientId}&scope=https%3A%2F%2Fsceneplusb2c.onmicrosoft.com%2F{$this->clientId}%2FUser.Read%20openid%20profile%20offline_access&redirect_uri=https%3A%2F%2Fwww.sceneplus.ca%2Fen-ca&client-request-id={$this->clientRequestId}&response_mode=fragment&response_type=code&x-client-SKU=msal.js.browser&x-client-VER=2.18.0&x-client-OS=&x-client-CPU=&client_info=1&code_challenge=RU57tM5M2ZxjSdO-XZbmtfUTUO2IP0doubCLYEnfqVw&code_challenge_method=S256&nonce=460d92d8-bbfe-4d4f-a3fd-a8b486b63c34&state=eyJpZCI6IjYzNzIxYmUxLTFiNDctNDNhNy05NDk0LTc0ZjBhN2JiZWZhNyIsIm1ldGEiOnsiaW50ZXJhY3Rpb25UeXBlIjoicmVkaXJlY3QifX0%3D&ui_locales=en-ca";
//        $this->http->GetURL($url);
        sleep(2);
        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'signInName']"), self::WAIT_TIMEOUT);
        $cookie = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'action-button' or @id = 'ok-button' or contains(.,'Accept Cookies')]"), 0);

        if ($cookie) {
            $cookie->click();
            sleep(1);
            $this->saveResponse();
        }

        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'password']"), 0);
        $submitButton = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Sign in')]"), 0);

        if (!$login || !$pass || !$submitButton) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }
        $login->sendKeys($sLogin);
        sleep(random_int(1, 2));
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $this->driver->executeScript("window.scrollTo(0, 200);");
        sleep(random_int(2, 4));

        $submitButton->click();

        return true;
    }

    public function Login()
    {
        // Where should we send your 2-step verification code?
        if ($this->AccountFields['Login'] == '6046462941627742') {
            $message = $this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Check your phone…')]"), self::WAIT_TIMEOUT);
            $input = $this->waitForElement(WebDriverBy::xpath("//input[@id='extension_SCENE_PhoneNumber']"), 0);
            if (strstr($message->getText(), 'Check your phone…') && $input) {
                $this->throwProfileUpdateMessageException();
            }
        }

        $error = $this->waitForElement(WebDriverBy::xpath("
            //div[contains(@class, 'error pageLevel')] 
            "), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($error) {
            $message = $error->getText();
            $this->logger->error("[Error]: {$message}");
            // Please check your email, Scene+ number, or password and try again.
            if (
                strstr($message, 'Please check your email, Scene+ number, or password and try again.')
                || strstr($message, 'Please check your Scene+ number or password and ensure you have registered your card.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Your account has been disabled')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // Step send 2fa
        $questionSend = $this->waitForElement(WebDriverBy::xpath("//button[@id='sendCodeToEmail']"), self::WAIT_TIMEOUT);

        if (!$questionSend) {
            $questionSend = $this->waitForElement(WebDriverBy::xpath("//button[@id='sendCodeToPhone']"), 0);
        }

        if ($questionSend) {
            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }
            $this->logger->debug("Question Send: " . $questionSend->getText());
            $this->State['questionTarget'] = $this->http->FindPreg('/\w+:\s*(.+)/', false, $questionSend->getText());
            $this->State['questionType'] = strtolower($this->http->FindPreg('/(\w+):\s*.+/', false, $questionSend->getText()));

            sleep(random_int(2, 4));
            $questionSend->click();
        }

        if ($this->parseQuestion()) {
            return false;
        }
       /*$data = $this->driver->executeScript('return sessionStorage.getItem("__LSM__")');
        $this->logger->debug($data);
        $data = $this->http->JsonLog($data);

        if (isset($data->session->accessToken)) {
            $this->State['Authorization'] = $data->session->accessToken;
        }*/

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = null;
        //$message = $this->waitForElement(WebDriverBy::xpath("//div[@id='verifyEmailOrPhoneControl_success_message']"), self::WAIT_TIMEOUT);

        if (isset($this->State['questionType'], $this->State['questionTarget'])) {
            if ($this->State['questionType'] == 'email') {
                $question = "A verification code has been sent to your email: {$this->State['questionTarget']}";
            } elseif ($this->State['questionType'] == 'text') {
                $question = "A verification code has been sent to your phone: {$this->State['questionTarget']}";
            }
            $this->logger->debug("Question to -> {$question}");
        }

        if (!$question) {
            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");
            $this->logger->debug('return false');

            return true;
        }
        $input = $this->waitForElement(WebDriverBy::id("verificationCodeLayer"), self::WAIT_TIMEOUT);

        if (!$input) {
            $this->logger->error("Can not find answer input");
            $this->logger->debug('return true');
            unset($this->Answers[$question]);

            return true;
        }
        $input->clear();
        $input->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);

        $btn = $this->waitForElement(WebDriverBy::id("verifyEmailOrPhoneControl_but_verify_code"), 0);

        if (!$btn) {
            $this->logger->error("Can not find answer continue button");
            $this->logger->debug('return true');

            return false;
        }
        $btn->click();

        // waiting an error
        $this->logger->debug("waiting an error...");
        $error = $this->waitForElement(WebDriverBy::xpath("//div[
            contains(text(), 'Wrong code entered, please try again.')
        ]"), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($error) {
            $error = $error->getText();
            $this->logger->error("Error -> {$error}");
            $this->holdSession();
            $this->AskQuestion($question, $error, "Question");

            $this->logger->debug('return false');

            return false;
        }

//        if ($error = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'For your protection, your account has been locked.')]"), 0)) {
//            throw new CheckException($error->getText(), ACCOUNT_LOCKOUT);
//        }

        sleep(10);
        $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'GreetingStyles__BalanceTicker')]/div/div"), self::WAIT_TIMEOUT);
        $this->saveResponse();

        foreach ($this->http->driver->browserCommunicator->getRecordedRequests() as $xhr) {
            $this->logger->debug("<br>xhr request {$xhr->request->getVerb()} {$xhr->request->getUri()}<br>headers: " . json_encode($xhr->request->getHeaders()) . "<br>body: " . htmlspecialchars(json_encode($xhr->response->getBody())));

            if (str_ends_with($xhr->request->getUri(), '/api/customer')) {
                $this->response['customer'] = json_encode($xhr->response->getBody());
                $this->logger->info('xhr response body: ' . $this->response['customer']);

            } elseif (str_ends_with($xhr->request->getUri(), '/api/customer/portfolio-balance')) {
                $this->response['portfolio-balance'] = json_encode($xhr->response->getBody());
                $this->logger->info('xhr response body: ' . $this->response['portfolio-balance']);
            }

        }

        $this->logger->debug('return true');
        unset($this->State['questionTarget'], $this->State['questionType']);

        /*$data = $this->driver->executeScript('return sessionStorage.getItem("__LSM__")');
        $this->logger->debug($data);
        $data = $this->http->JsonLog($data, 1);

        if (isset($data->session->accessToken)) {
            $this->State['Authorization'] = $data->session->accessToken;
        }*/

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        switch ($step) {
            case "Question":
                return $this->parseQuestion();
        }

        return false;
    }

    public function Parse()
    {

        /*$this->parseWithCurl();
        $headers = [
            "Accept"             => "application/json, text/plain, * / *",
            'Accept-Encoding'    => 'gzip, deflate, br, zstd',
            "Origin"             => "https://www.sceneplus.ca",
            "Referer"            => "https://www.sceneplus.ca/",
            "Authorization"      => 'Bearer ' . $this->State['Authorization'],
            'Connection'         => null,
            'Sec-Ch-Ua'          => '"Chromium";v="122", "Not(A:Brand";v="24", "Google Chrome";v="122"',
            'Sec-Ch-Ua-Mobile'   => '?0',
            'Sec-Ch-Ua-Platform' => 'Linux',
            'Sec-Fetch-Dest'     => 'empty',
            'Sec-Fetch-Mode'     => 'cors',
            'Sec-Fetch-Site'     => 'cross-site',
        ];
        $this->browser->GetURL('https://sceneplus.webapis.loyaltysite.ca/api/customer', $headers);*/
        $response = $this->http->JsonLog($this->response['customer'] ?? null);
        if ($response == null) {
            $this->sendNotification('check parse // MI');
            sleep(7);
            $this->saveResponse();
            $cookie = $this->waitForElement(WebDriverBy::xpath("//img[@src='/assets/close-x.svg']"), self::WAIT_TIMEOUT);
            if ($cookie) {
                $cookie->click();
                sleep(1);
                $this->saveResponse();
            }
            $cookie = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'action-button' or @id = 'ok-button' or contains(., 'Accept Cookies')]"), 0);
            if ($cookie) {
                $cookie->click();
                sleep(1);
                $this->saveResponse();
            }

            $this->waitFor(function ()  {
                return !$this->waitForElement(WebDriverBy::xpath('//*[@id = "mainContent"]//span[contains(text(), "Loading")]'), 0);
            }, 120);
            $this->saveResponse();

            if ($this->waitForElement(WebDriverBy::xpath('//*[@id = "mainContent"]//span[contains(text(), "Loading")]'), 0)) {
                $this->saveResponse();

                return;
            }

            // Name
            $name = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(),'Good ')]/.."), self::WAIT_TIMEOUT);
            if ($name) {
                $this->SetProperty("Name", beautifulName($this->http->FindPreg('/,\s*(.+)/', false, $name->getText())));
            }
            // Balance - PTS
            $balance = $this->waitForElement(WebDriverBy::xpath("//div[@class='counter']/div[@class='number']/span[@class='primary']"), self::WAIT_TIMEOUT);

            if (!isset($balance)) {
                return;
            }

            $this->saveResponse();
            $this->SetBalance($this->http->FindPreg(self::BALANCE_REGEXP, false, $balance->getText()));

            $this->sendNotification('check parse // MI');
            return;
        }
        // Name
        $this->SetProperty("Name",
            beautifulName($response->data->customer->firstName . " " . $response->data->customer->lastName));
        // Card #
        $this->SetProperty("CardNumber", $response->data->customer->sceneCardNumber);


        // Balance - PTS
        // $this->http->GetURL("https://sceneplus.webapis.loyaltysite.ca/api/customer/portfolio-balance", $headers);
        $response = $this->http->JsonLog($this->response['portfolio-balance'] ?? null);
        $this->SetBalance($response->data->customer->points);
        /*$balance = 0;

        foreach ($response->data as $account) {
            $balance += $account->pointsBalance;
        }

        $this->SetBalance($balance);*/
        // Expiration date  // refs #8905
        /*$data = [
            "Types"      => ["ALL"],
            "Categories" => ["ALL"],
            "Cards"      => ["ALL"],
            "FromDate"   => date("Y-m-d", strtotime("-1 month")) . "T00:00:00+00:00",
            "ToDate"     => date("Y-m-d") . "T00:00:00+00:00",
            "Page"       => 1,
            "Sort"       => "DESC",
        ];
        $this->http->PostURL("https://sceneplus.webapis.loyaltysite.ca/api/customer/points/history", json_encode($data), $headers);
        $response = $this->http->JsonLog();
        $transactions = $response->data->pointsHistory ?? [];
        $this->logger->debug("Total " . count($transactions) . " nodes were found");

        foreach ($transactions as $transaction) {
            $date = substr($transaction->pointDate, 0, strpos($transaction->pointDate, 'T'));
            $points = $transaction->points;
            $this->logger->debug("Date: {$date} / Points: {$points}");

            if (strtotime($date) && !empty($points)) {
                // Last Activity
                $this->SetProperty("LastActivity", $date);
                // Expiration date
                $this->SetExpirationDate(strtotime("+2 year", strtotime($date)));

                break;
            }// if (strtotime($date) && (!empty($redeemed) || !empty($earned)))
        }// foreach ($transactions as $transaction)*/
    }

    private function checkErrors()
    {
        $this->logger->debug(__METHOD__);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently making improvements to our site')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // _Incapsula_Resource - What happened? This request was blocked by our security service
        if ($this->waitForElement(WebDriverBy::xpath("//iframe[@id='main-iframe']"), 0)) {
            throw new CheckRetryNeededException(2, 5);
        }
        return false;
    }

    private function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->browser->setHttp2(true);
    }
}
