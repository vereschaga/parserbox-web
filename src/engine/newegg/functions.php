<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerNewegg extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://secure.newegg.com/account/eggpoints';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $this->UseSelenium();
        $this->useFirefox(SeleniumFinderRequest::FIREFOX_53);
        $this->setKeepProfile(true);
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;

        try {
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        } catch (WebDriverCurlException $e) {
            throw new CheckRetryNeededException(2, 0);
        }
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        /*
        // for cookies
        $this->http->GetURL('https://www.newegg.com/');
        // for 302
        */
        if (!$this->waitForElement(WebDriverBy::xpath('//input[@name = "signEmail"]'), 0)) {
            $this->http->GetURL('https://secure.newegg.com/NewMyAccount/AccountLogin.aspx');
        }

        $this->waitForElement(WebDriverBy::xpath('
            //input[@name = "signEmail"]
            | //h2[contains(text(), "Are you a human?")]
            | //input[@name = "UserName"]
        '), 15);
        $this->saveResponse();

        if (
            $this->waitForElement(WebDriverBy::xpath('//input[@name = "UserName"]'), 0)
            && $this->waitForElement(WebDriverBy::xpath('//a[contains(., "Sign in / Register")]'), 0)
        ) {
            $this->logger->notice("open a normal login form");
            $this->waitForElement(WebDriverBy::xpath('//a[contains(., "Sign in / Register")]'), 0)->click();
        }

        if (
            $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Are you a human?")]'), 0)
            || !$this->waitForElement(WebDriverBy::xpath('//input[@name = "signEmail"]'), 0)
        ) {
            $this->http->GetURL('https://secure.newegg.com/NewMyAccount/AccountLogin.aspx');
        }

        $signEmail = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'signEmail']"), 15);
        $signInSubmit = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'signInSubmit']"), 0);
        $this->saveResponse();

        if (!$signEmail || !$signInSubmit) {
            if ($this->parseQuestion()) {
                return true;
            }

            $this->checkLoginErrors();

            return $this->checkErrors();
        }

        $signEmail->clear();
        $signEmail->sendKeys($this->AccountFields['Login']);
        $signInSubmit->click();

        $password = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 5);
        $signInSubmit = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'signInSubmit']"), 0);
        $this->saveResponse();

        if (!$password || !$signInSubmit) {
            $this->checkLoginErrors();

            // selenium bug fix
            if ($signInSubmit = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'signInSubmit']"), 0)) {
                $signInSubmit->click();
                sleep(3);
                $this->saveResponse();
                $this->checkLoginErrors();
            }

            if ($this->parseQuestion()) {
                return true;
            }

            $password = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 5);

            if (!$password) {
                if ($this->http->FindSingleNode('//div[*[self::div or self::strong][contains(text(), "To continue, approve the notification sent to")]]')) {
                    return true;
                }

                return $this->checkErrors();
            }
        }

        $password->sendKeys($this->AccountFields['Pass']);

        try {
            $signInSubmit->click();
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $signInSubmit = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'signInSubmit']"), 0);
            $this->saveResponse();
            $signInSubmit->click();
        }

        /*
        if (isset($this->http->Response['headers']['location'])) {
            $location = $this->http->Response['headers']['location'];
            $this->http->NormalizeURL($location);
            $this->http->GetURL($location);
        }

        $tk = $this->http->FindPreg('/"query":\{"tk":"([^\"]+?)"\},/');

        if (!$tk) {
            return false;
        }

        $headers = [
            "Accept" => "application/json, text/plain, * /*",
        ];
        $this->http->GetURL("https://secure.newegg.com/identity/api/InitSignIn?ticket={$tk}", $headers);
        $response = $this->http->JsonLog();

        $lNK = $response->LNK ?? null;
        $siteKey = $response->SiteKey ?? null;
        // $this->parseReCaptcha($siteKey);
        $fuzzyChain = $response->FuzzyChain ?? null;
        $data = [
            $lNK                           => $this->AccountFields['Login'],
            "k4gukkRpmWWX-WDgW0_s"         => "",
            "FuzzyChain"                   => $fuzzyChain,
            "S"                            => "",
            "AccertifyIdentityInfo"        => [
                "eventSource"         => "web",
                "deviceTransactionID" => "NEWEGG406451611923060170094",
                "uBAID"               => "94384fa6a472ea9daae2d5392142e7321f9f",
                "uBAEvents"           => "",
                "uBASessionID"        => "",
                "pageID"              => "8701533396099621",
            ],
        ];

        $this->http->PostURL("https://secure.newegg.com/identity/api/SignIn?ticket={$tk}", $data, $headers);
        */

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //div[@class = "nav-complex-title"]
            | //a[@title="My Account"]//div[contains(@class, "header2021-nav-subtitle")]
            | //div[*[self::div or self::strong][contains(text(), "To continue, approve the notification sent to")]]
            | //div[div[contains(text(), "Enter the code generated by your") or contains(text(), "Enter the code that has been sent to")]]
            | //div[@class = "form-error-message"]
            | //p[@class = "color-red"]
            | //p[contains(text(), "Security experts recommend changing your password before you can proceed.")]
            | //button[contains(text(), "Skip")]
        '), 10);
        $this->saveResponse();

        if ($skip = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Skip")]'), 0)) {
            $skip->click();
            $this->waitForElement(WebDriverBy::xpath('
                //div[@class = "nav-complex-title"]
                | //a[@title="My Account"]//div[contains(@class, "header2021-nav-subtitle")]
                | //div[*[self::div or self::strong][contains(text(), "To continue, approve the notification sent to")]]
                | //div[div[contains(text(), "Enter the code generated by your") or contains(text(), "Enter the code that has been sent to")]]
                | //div[@class = "form-error-message"]
                | //p[@class = "color-red"]
                | //p[contains(text(), "Security experts recommend changing your password before you can proceed.")]
            '), 10);
            $this->saveResponse();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->parseQuestion()) {
            return true;
        }

        if ($this->parseCode()) {
            return false;
        }

        $this->checkLoginErrors();

        if ($this->http->FindSingleNode('//p[contains(text(), "Security experts recommend changing your password before you can proceed.")]')) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($step == 'AuthenticatorApp') {
            $this->logger->info('Security Question: verification via code generated by Authenticator App', ['Header' => 3]);

            $answerInput = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'form-v-code']//input[@type='number']"), 0);
//            $answerInputs = $this->driver->findElement(WebDriverBy::xpath("//div[@class = 'form-v-code']//input[@type='number']"));
            $btn = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Sign In')]"), 0);
            $this->saveResponse();

            if (!$answerInput || !$btn) {
                return false;
            }

            $answer = $this->Answers[$this->Question];

            for ($i = 0; $i < 6; $i++) {
                $answerInput = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'form-v-code']//input[@type='number' and @aria-label = 'verify code " . ($i + 1) . "']"), 0);
                $answerInput->clear();
                $answerInput->sendKeys($answer[$i]);
//                $answerInputs[$i]->clear();
//                $answerInputs[$i]->sendKeys($answer[$i]);
            }

            $this->saveResponse();
            $this->driver->executeScript('document.querySelector(\'label.form-checkbox input\').checked = true;');
            $this->saveResponse();

            unset($this->Answers[$this->Question]);
            $this->logger->debug("click 'Submit'...");
            $btn->click();
            $this->logger->debug("find errors...");

            sleep(5);

            $error = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Hmm...the code is incorrect.')]"), 0);
            $this->saveResponse();

            if ($error) {
                $this->holdSession();
                $this->logger->error("answer was wrong");
                $this->AskQuestion($this->Question, $error->getText(), "AuthenticatorApp");

                return false;
            }

            return true;
        }

        $this->logger->info('Security Question: verification via Link', ['Header' => 3]);

        if (!filter_var($this->Answers[$this->Question], FILTER_VALIDATE_URL)) {
            unset($this->Answers[$this->Question]);
            $this->AskQuestion($this->Question, "The link you entered seems to be incorrect", "Question"); /*review*/

            return false;
        }// if (!filter_var($this->Answers[$this->Question], FILTER_VALIDATE_URL))

        $this->http->GetURL($this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);
        $this->logger->debug("success");
        // Access is allowed
        $this->waitForElement(WebDriverBy::xpath('
            //div[@class = "nav-complex-title"]
            | //p[contains(text(), "You can close this page.")]
        '), 10);
        $this->saveResponse();

        return true;
    }

    public function Parse()
    {
        if (!strstr($this->http->currentUrl(), self::REWARDS_PAGE_URL)) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        $this->waitForElement(WebDriverBy::xpath('//div[@class = "eggpoints-activity-score"]/strong'), 5);
        $this->saveResponse();

        // Balance - Current Balance
        $this->SetBalance($this->http->FindSingleNode('//div[@class = "eggpoints-activity-score"]/strong'));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('
            //div[@class = "nav-complex-title" and not(contains(., "Item"))]/text()[1]
            | (//div[contains(@class, "header2021-nav-subtitle")])[1]
        ')));

        if (!$this->http->FindSingleNode('//p[contains(text(), "You have no pending points.")]')) {
            $this->sendNotification("pending points were found - refs #8798");
        }
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

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

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('
                //div[@class = "nav-complex-title" and not(contains(., "Item"))]
                | (//div[contains(@class, "header2021-nav-subtitle")])[1]
            ')
            || $this->waitForElement(WebDriverBy::xpath('
                //div[@class = "nav-complex-title"]
                | //a[@title="My Account"]//div[contains(@class, "header2021-nav-subtitle")]
            '), 0)
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function checkLoginErrors()
    {
        $this->logger->notice(__METHOD__);

        $message = $this->http->FindSingleNode('
            //div[@class = "form-error-message"]
            | //p[@class = "color-red"]
        ');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Please enter a valid email address.'
                || $message == 'We didn\'t find an account for this email address.'
                || $message == 'The email and password do not match. Try again, please.'
                || $message == 'The email and password do not match, please try again or click here to reset.'
                || $message == 'We didn\'t find any matches, please try again.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "Your session ended because there was no activity.")]')) {
            throw new CheckRetryNeededException(2, 0);
        }

        return false;
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $xpath = '//div[div[contains(., "To continue, approve the notification sent to")]]/following-sibling::div//div[contains(., "Email Address")]/following-sibling::div';
        $this->saveResponse();
        $email = $this->http->FindSingleNode($xpath);

        if (!isset($email)) {
            return false;
        }

        if (
            $this->http->FindSingleNode($xpath)
        ) {
            $sleep = 120;
            $startTime = time();

            do {
                $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
                sleep(5);
                $this->saveResponse();
            } while (
                ((time() - $startTime) < $sleep)
                && $this->waitForElement(WebDriverBy::xpath($xpath), 0)
            );

            if ($this->http->FindSingleNode($xpath)) {
                throw new CheckException("To complete the sign-in, you should respond to the notification that was sent to you email {$email}.", ACCOUNT_PROVIDER_ERROR);
            }
        }

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        /*
        $this->holdSession();
        $this->Question = "Please copy-paste an authorization link which was sent to your email {$email} to continue the authentication process.";
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = 'Question';

         */
        return true;
    }

    private function parseCode()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode('//div[div[contains(text(), "Enter the code generated by your") or contains(text(), "Enter the code that has been sent to")]]');

        if (!isset($question)) {
            return false;
        }

        $this->holdSession();
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = 'AuthenticatorApp';

        return true;
    }
}
