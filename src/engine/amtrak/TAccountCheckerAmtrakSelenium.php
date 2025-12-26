<?php

use AwardWallet\Common\Parsing\LuminatiProxyManager\Port;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAmtrakSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use PriceTools;
    use ProxyList;

    private HttpBrowser $browser;
    protected $amtrak;

    private const XPATH_SUCCESS = "//*[@id='lblUserFirstName'] | //a[@id = 'guest-reward-desktop' and not(normalize-space(.) = 'Guest Rewards')]";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        //$this->setProxyNetNut();

        $this->UseSelenium();
        $this->http->saveScreenshots = true;
        $this->useGoogleChrome();


        $userAgentKey = "User-Agent";

        if (!isset($this->State[$userAgentKey]) || $this->attempt > 1) {
            $this->http->setRandomUserAgent(5);
            $agent = $this->http->getDefaultHeader("User-Agent");
            if (!empty($agent)) {
                $this->State[$userAgentKey] = $agent;
            }
        } else {
            $this->http->setUserAgent($this->State[$userAgentKey]);
        }

        $this->seleniumOptions->recordRequests = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();

        try {
            $this->http->GetURL("https://www.amtrak.com/home");
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("UnknownErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        $accept = $this->waitForElement(WebDriverBy::id('onetrust-accept-btn-handler'), 5);

        if ($accept) {
            $accept->click();
        }

        $login = $this->waitForElement(WebDriverBy::xpath("//button[@amt-auto-test-id='header-sign-in'] | //a[@id = 'guest-reward-desktop']"), 2);

        if ($login) {
            $this->saveResponse();
            $login->click();
            sleep(3);
        }

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "signInName"]'), 10);

        // provider bug fix
        if (!$login) {
            $this->logger->notice("click one more time");
//            $this->saveResponse();
//            $this->driver->executeScript('try { document.querySelector(\'[data-href="https://www.amtrak.com/"]\').click(); } catch (e) {}');
//            $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "English")]'), 5);
//            $this->saveResponse();

            $login = $this->waitForElement(WebDriverBy::xpath("//button[@amt-auto-test-id='header-sign-in'] | //a[@id = 'header-sign-in']"), 5);
            $this->saveResponse();

            if ($login) {
                $login->click();
                sleep(3);
            }

            $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "signInName"]'), 15);
        }

        $password = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
        $loginButton = $this->waitForElement(WebDriverBy::xpath('//button[@id = "next"]'), 0);
        $this->saveResponse();

        if (!$login || !$password || !$loginButton) {
            if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
                throw new CheckRetryNeededException(3, 0);
            }
            return false;
        }

        $login->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $loginButton->click();

        return true;
    }

    public function Login()
    {
        sleep(2);
        $this->waitForElement(WebDriverBy::xpath("
           //*[@id='signin_tnc-btn-b2c']
           | ". self::XPATH_SUCCESS ."
           | //div[contains(@class, 'error') and @style = 'display: block;']
           | //*[@id='forgotpassword-simple--subheading']/*[self::div or self::h3][contains(text(),'Please select how you would like to receive your code')]
           | //*[@id='forgotpassword-simple--subheading']/*[self::div or self::h3][contains(text(),'A verification code has been sent to your email.')]
           | //div[@id = 'emailVerificationControl_error_message' and @style=\"display: inline;\" and @role='alert']
        "), 10);
        $this->saveResponse();

        if (!$this->parseQuestion()) {
            return false;
        }

        $agreeTerms = $this->waitForElement(WebDriverBy::id('signin_tnc-btn-b2c'), 0);

        if ($agreeTerms) {
            $agreeTerms->click();
            $this->waitForElement(WebDriverBy::xpath(self::XPATH_SUCCESS), 5);
            $this->saveResponse();
        }

        $lblUserFirstName = $this->http->FindSingleNode(self::XPATH_SUCCESS);
        $modalClose = $this->waitForElement(WebDriverBy::id('email-opt-modal-close'), 0);

        if ($lblUserFirstName || $modalClose) {
            return true;
        }

        if ($message =
                $this->http->FindSingleNode("//div[contains(@class, 'error') and @style = 'display: block;']")
                ?? $this->http->FindSingleNode("//div[@id = 'emailVerificationControl_error_message' and @role='alert' and @style=\"display: inline;\"]")
        ) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'The username or password provided in the request are invalid')
                || $message == 'Password has invalid input.'
                || $message == 'We cannot find an account matching the email/Guest Rewards number.'
                || $message == 'Enter a valid Guest Rewards number or email address.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Use "Forgot Password" to reset your login and access your account.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if ($message == 'Something wrong with our System. Please try again') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        $choice = $this->waitForElement(WebDriverBy::xpath("//*[@id='forgotpassword-simple--subheading']/*[self::div or self::h3][contains(text(),'Please select how you would like to receive your code')]"), 1);
        $delay = 0;

        if ($choice) {
            sleep(1);
            $this->saveResponse();
            $emailOption = $this->waitForElement(WebDriverBy::id('email_option'), 0);
            $continue = $this->waitForElement(WebDriverBy::id('continue'), 0);
            if ($emailOption && $continue) {
                $this->saveResponse();
                $emailOption->click();
                sleep(1);
                $continue->click();
                $delay = 10;
            }
        }

        $q = $this->waitForElement(WebDriverBy::xpath("//*[@id='forgotpassword-simple--subheading' and not(@style=\"display: none;\")]/*[self::div or self::h3][contains(text(),'A verification code has been sent to your email.')]"), $delay);

        if ($q) {
            $question = $q->getText();
        } else {
            $q = $this->waitForElement(WebDriverBy::xpath("(//div[contains(@id,'phoneVerificationControl_success_message')])[1]"), 0);
            if (!$q) {
                $this->saveResponse();

                return true;
            }
            $phone = $this->waitForElement(WebDriverBy::xpath("(//div[contains(@id,'phoneVerificationControl_success_message')])[1]/following-sibling::div"), 0);
            $question = $q->getText() . ' ' . $phone->getText();
        }

        $this->saveResponse();

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $this->logger->debug("question: $question");
        $this->holdSession();
        $this->AskQuestion($question, null, 'question');

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == 'question') {
            if ($this->processSecurityCheckpoint()) {
                $this->saveResponse();
                return true;
            }
        }

        return false;
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->logger->debug("Question to -> $this->Question=$answer");

        $verificationCode = $this->waitForElement(WebDriverBy::xpath('//input[@id = "VerificationCode"]'), 0);
        $this->saveResponse();

        if ($verificationCode) {
            $verificationCode->clear();
            $verificationCode->sendKeys($answer);

            $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "emailVerificationControl_but_verify_code" or @id = "phoneVerificationControl_but_verify_code"]'), 0);
            $this->saveResponse();

            if ($button) {
                $button->click();
            }
        }
        else {
            $otpInput = $this->waitForElement(WebDriverBy::xpath('//input[@autocomplete="one-time-code"]'), 0);

            if (!$otpInput) {
                $this->saveResponse();

                return false;
            }

            $this->logger->debug("entering code...");
            $elements = $this->driver->findElements(WebDriverBy::xpath('//input[@autocomplete="one-time-code"]'));

            foreach ($elements as $key => $element) {
                $this->logger->debug("#{$key}: {$answer[$key]}");
                $element->click();
                $element->sendKeys($answer[$key]);
                $this->saveResponse();
            }
        }

        $this->waitForElement(WebDriverBy::xpath('//*[@id = "emailVerificationControl_error_message" or @id = "phoneVerificationControl_error_message" or @id = "verificationCode-error"] | ' . self::XPATH_SUCCESS), 7);
        $error = $this->waitForElement(WebDriverBy::xpath('//*[@id = "emailVerificationControl_error_message" or @id = "phoneVerificationControl_error_message" or @id = "verificationCode-error"]'), 0);
        $this->saveResponse();

        // To ensure your account is secure, your password must be changed.
        if ($this->http->FindSingleNode("//h3[contains(text(),'To ensure your account is secure, your password must be changed.')]")) {
            $this->throwProfileUpdateMessageException();
        }

        if ($error) {
            $this->logger->error("resetting answers");
            $this->AskQuestion($this->Question, $error->getText(), 'question');

            return false;
        }

        $this->logger->debug("success");

        return true;
    }

    protected function getAmtrak()
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->amtrak)) {
            $this->amtrak = new TAccountCheckerAmtrak();
            $this->amtrak->http = new HttpBrowser("none", new CurlDriver());
            $this->amtrak->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->amtrak->http);
            $this->amtrak->AccountFields = $this->AccountFields;
            $this->amtrak->http->SetBody($this->http->Response['body']);
            $this->amtrak->itinerariesMaster = $this->itinerariesMaster;
            $this->amtrak->HistoryStartDate = $this->HistoryStartDate;
            $this->amtrak->historyStartDates = $this->historyStartDates;
            $this->amtrak->http->LogHeaders = $this->http->LogHeaders;
            $this->amtrak->ParseIts = $this->ParseIts;
            $this->amtrak->ParsePastIts = $this->ParsePastIts;
            $this->amtrak->WantHistory = $this->WantHistory;
            $this->amtrak->WantFiles = $this->WantFiles;
            $this->amtrak->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->amtrak->http->setDefaultHeader($header, $value);
            }

            $this->amtrak->globalLogger = $this->globalLogger;
            $this->amtrak->logger = $this->logger;
            $this->amtrak->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->amtrak->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->amtrak;
    }

    public function Parse()
    {
        /*$this->sendNotification('check Parse');
        $this->setLpmProxy((new Port)
            ->setExternalProxy([$this->http->getProxyUrl()])
        );
        try {
            $har = $this->getHarFromLpm('#/dotcom/consumers/profile#');
            $this->logger->info("recorder request: ");
            $this->logger->debug(var_export($har->log->entries, true), ['pre' => true]);
            $har = $this->getHarFromLpm('\?agrNumber=');
            $this->logger->info("recorder request: ");
            $this->logger->debug(var_export($har->log->entries, true), ['pre' => true]);
            $har = $this->getHarFromLpm('/dotcom/consumers/profile');
            $this->logger->info("recorder request: ");
            $this->logger->debug(var_export($har->log->entries, true), ['pre' => true]);
        } catch (Exception $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
        }*/
        try {
            $this->http->GetURL('https://www.amtrak.com/guestrewards/account-overview.html');
            sleep(7);
            $seleniumDriver = $this->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
        } catch (Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
            $this->logger->error("InvalidSessionIdException: " . $e->getMessage(), ['HtmlEncode' => true]);
            throw new CheckRetryNeededException(2, 0);
        }

        foreach ($requests as $n => $xhr) {
            $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
            if (strstr($xhr->request->getUri(), 'dotcom/consumers/profile?agrNumber=')) {
                //$this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                $headers = $this->http->JsonLog(json_encode($xhr->request->getHeaders()));
                if (isset($headers->{'x-b2c-auth-token'})) {
                    $this->http->setDefaultHeader('x-b2c-auth-token', $headers->{'x-b2c-auth-token'});
                }
                $this->http->SetBody(json_encode($xhr->response->getBody()));
                break;
            }
        }
        $amtrak = $this->getAmtrak();
        $amtrak->Parse();
        $this->SetBalance($amtrak->Balance);
        $this->Properties = $amtrak->Properties;
        $this->ErrorCode = $amtrak->ErrorCode;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorMessage = $amtrak->ErrorMessage;
            $this->DebugInfo = $amtrak->DebugInfo;
        }

    }

    public function ParseItineraries()
    {
        $amtrak = $this->getAmtrak();
        return $amtrak->ParseItineraries();
    }
}
