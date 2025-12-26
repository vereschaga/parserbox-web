<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirindiaSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const WAIT_TIMEOUT = 10;

    private const XPATH_NUMBER = '//span[@class="user-ffn" and contains(text(), "ID- ") and not(text()="ID- ")] | //input[@id="username"]';

    private $airindia;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->http->saveScreenshots = true;
        $this->usePacFile(false);

        $this->setProxyBrightData();
//        $this->setProxyGoProxies();

        $this->useFirefoxPlaywright();
//        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL('https://www.airindia.com/in/en/maharaja-club/account-summary.html');
        $this->waitForElement(WebDriverBy::xpath(self::XPATH_NUMBER), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm(): bool
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.airindia.com/in/en/sign-in?connection=email');
        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="username"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if (!$login) {
            return $this->checkErrors();
        }

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = rand(50000, 90000);
        $mover->steps = rand(50, 70);

        $mover->moveToElement($login);
        $mover->click();
        $mover->sendKeys($login, $this->AccountFields['Login'], 5);

        $submit = $this->waitForElement(webdriverby::xpath('//button[@type="submit"]'), 0);
        $this->saveResponse();

        if (!$submit) {
            return $this->checkErrors();
        }

        $submit->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath(
            self::XPATH_NUMBER
            .' | //p[contains(text(), "OTP sent")]'
            .' | //form[@id = "chlge"]'
            .' | //h1[contains(text(), "Access Denied")]'
            .' | //div[@id="prompt-alert"]/p'
        ), self::WAIT_TIMEOUT * 3);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//div[@id="prompt-alert"]/p/text()')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "Could not find an account associated with this email. Please")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->processQuestion()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        // TODO: not completed
        if ($this->http->FindSingleNode('//form[@id = "chlge"]/@id')) {
            $this->DebugInfo = 'captcha';

            return false;
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
            $this->DebugInfo = 'Access Denied, broken account';

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function processQuestion()
    {
        $this->logger->notice(__METHOD__);

        $question = $this->http->FindSingleNode('//p[contains(text(), "OTP sent")]/text()');
        $code = $this->waitForElement(WebDriverBy::xpath('//input[@id="code"]'), 0);
        $submit = $this->waitForElement(WebDriverBy::xpath('//button[@type="submit" and @value="default"]'), 0);
        $this->saveResponse();

        if (!$submit || !$question || !$code) {
            $this->logger->debug('something went wrong');
            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        $code->clear();
        $code->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);

        $this->logger->debug("Submit question");
        $submit->click();
        $this->waitForElement(WebDriverBy::xpath('//span[@id="error-element-code"]'), 5);
        $this->saveResponse();

        if ($error = $this->http->FindSingleNode('//span[@id="error-element-code"]/text()')) {
            $this->logger->error("[Error]: {$error}");
            $this->holdSession();
            $this->AskQuestion($question, $error, "Question");

            return false;
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        $this->processQuestion();

        $this->waitForElement(WebDriverBy::xpath(self::XPATH_NUMBER), self::WAIT_TIMEOUT);
        $this->saveResponse();

        return $this->loginSuccessful();
    }

    public function Parse()
    {
        $airindia = $this->getAirindia();
        $airindia->Parse();
        $this->SetBalance($airindia->Balance ?? $this->Balance);
        $this->Properties = $airindia->Properties;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorCode = $airindia->ErrorCode;
            $this->ErrorMessage = $airindia->ErrorMessage;
            $this->DebugInfo = $airindia->DebugInfo;
        }
    }

    public function ParseItineraries()
    {
        return $this->getAirindia()->ParseItineraries();
    }

    protected function getAirindia()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->airindia)) {
            $this->airindia = new TAccountCheckerAirindia();
            $this->airindia->http = new HttpBrowser("none", new CurlDriver());
            $this->airindia->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->airindia->http);
            $this->airindia->State = $this->State;
            $this->airindia->AccountFields = $this->AccountFields;
            $this->airindia->itinerariesMaster = $this->itinerariesMaster;
            $this->airindia->HistoryStartDate = $this->HistoryStartDate;
            $this->airindia->historyStartDates = $this->historyStartDates;
            $this->airindia->http->LogHeaders = $this->http->LogHeaders;
            $this->airindia->ParseIts = $this->ParseIts;
            $this->airindia->ParsePastIts = $this->ParsePastIts;
            $this->airindia->WantHistory = $this->WantHistory;
            $this->airindia->WantFiles = $this->WantFiles;
            $this->airindia->strictHistoryStartDate = $this->strictHistoryStartDate;
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->airindia->http->setDefaultHeader($header, $value);
            }

            $this->airindia->globalLogger = $this->globalLogger;
            $this->airindia->logger = $this->logger;
            $this->airindia->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->airindia->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->airindia;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function getToken()
    {
        $script = "
            for (const key of Object.keys(localStorage)) {
                if (key.indexOf('https://api-loyalty-prod.airindia.com/api') === -1) {
                    continue;
                };
                return localStorage.getItem(key);
            };        
        ";

        $tokenDataRaw = $this->driver->executeScript($script);
        $tokenData = $this->http->JsonLog($tokenDataRaw);

        if (isset($tokenData, $tokenData->body->access_token)) {
            return $tokenData->body->access_token;
        }

        return null;
    }
    
    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        if (!$this->http->FindSingleNode(self::XPATH_NUMBER)) {
            return false;
        }

        if (!$token = $this->getToken()) {
            return false;
        }

        $this->State['token'] = $token;

        return $this->getAirindia()->loginSuccessful();
    }
}
