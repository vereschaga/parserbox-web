<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSingaporecorp extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.singaporeair.com/corporateBooking-flow.form?execution=e1s1';
    /** @var CaptchaRecognizer */
    private $recognizer;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""          => "Please select your login type",
            "Traveller" => "Corporate Traveller",
            "Manager"   => "Corporate Travel Manager",
        ];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->setProxyBrightData();
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
        $this->getCookiesFromSelenium();
//        $this->http->GetURL('https://www.singaporeair.com/en_UK/sq-corporate/travel-programme/');
        $this->http->GetURL('https://www.singaporeair.com/kfHome.form');

        if ($this->AccountFields['Login2'] == 'Manager') {
            if (!$this->http->ParseForm('ctmloginForm')) {
                return $this->checkErrors();
            }

            $this->http->SetInputValue('userID', $this->AccountFields['Login']);
            $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        } else {
            if (!$this->http->ParseForm('ctLoginForm')) {
                return $this->checkErrors();
            }

            if (!is_numeric($this->AccountFields['Login']) && filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
                throw new CheckException("Enter valid Krisflyer number / Email address", ACCOUNT_INVALID_PASSWORD);
            }

            $this->http->SetInputValue('kfNumber', $this->AccountFields['Login']);
            $this->http->SetInputValue('pin', $this->AccountFields['Pass']);
        }

        $this->http->SetInputValue('rememberMe', "true");

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm(['Referer' => 'https://www.singaporeair.com/en_UK/us/sq-corporate/'])) {
            return $this->checkErrors();
        }

        // Challenge Validation
        if (
            /*$this->http->ParseForm("sec_chlge_form")
            && */ ($key = $this->http->FindSingleNode("//iframe[@id = 'sec-cpt-if']/@data-key"))
        ) {
            $form = $this->http->Form;
            $formURL = $this->http->FormURL;
            $token = $this->parseReCaptcha($key);

            if ($token) {
                $this->http->GetURL("https://www.singaporeair.com/_sec/cp_challenge/verify?cpt-token={$token}");

                sleep(1);

                $this->http->Form = $form;
                $this->http->FormURL = $formURL;
                $this->http->PostForm();

                if ($this->http->Response['code'] == 403) {
                    $this->captchaReporting($this->recognizer, false);

                    throw new CheckRetryNeededException(3, 0);
                }

                $this->captchaReporting($this->recognizer);
            }
        }

        if ($cookieTestURL = $this->http->FindPreg("/document\.cookie = 'cookietest=1; expires=Thu, 01-Jan-1970 00:00:01 GMT';\s+document\.location\.href = decodeURIComponent\('([^']+)/")) {
            $cookieTestURL = rawurldecode($cookieTestURL);
            $this->http->setCookie('cookietest', '1', $this->http->getCurrentHost(), '/', 1);
            $this->http->NormalizeURL($cookieTestURL);
            $this->http->GetURL($cookieTestURL);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        // GetURL - https://www.singaporeair.com/en_UK/us/sq-corporate/
        if (strstr($this->http->currentUrl(), 'sq-corporate') && $this->parseQuestion()) {
            return false;
        }

        // https://www.singaporeair.com/en_UK/us/home?errorKey=null&errorCategory=login&coporateLoginRedirect=true#corporateLogin
        if (strstr($this->http->currentUrl(), 'errorKey=')) {
            $query = parse_url($this->http->currentUrl(), PHP_URL_QUERY);
            $this->http->GetURL("https://www.singaporeair.com/home/getNonCacheableData.form?{$query}");
            $response = $this->http->JsonLog();
        }

        $errorJson = $response->errorJson ?? null;
        $message = $this->http->JsonLog($errorJson)->messageList[0]->description ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Your User ID or Password is incorrect, please try again.')
            ) {
                throw new CheckException("Your User ID or Password is incorrect, please try again.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'The User ID and/or password you have entered do not match our records')
            ) {
                throw new CheckException("The User ID and/or password you have entered do not match our records.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'The User ID and/or password you have entered do not match our records. Please check and try again later.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'The KrisFlyer membership number/ email address and/or password you have entered do not match our records')) {
                throw new CheckException("The KrisFlyer membership number/ email address and/or password you have entered do not match our records.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'We cannot process your request right now.')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $data = [
            'otp' => $answer,
        ];
        $headers = [
            "Accept"              => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"        => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Sec-Clge-Req-Type" => "ajax",
            "X-Requested-With"    => "XMLHttpRequest",
        ];
        $this->http->PostURL("https://www.singaporeair.com/sqcLoginVerifyOTP.form", $data, $headers);
        $response = $this->http->JsonLog();

        $error = $response->errorList[0]->errorMsg ?? null;
        $flowExecutionUrl = $response->flowExecutionUrl ?? null;

        if ($error == 'Input OTP validation failed.') {
            $this->AskQuestion($this->Question, "The OTP you've entered is incorrect. Please try again.", "Question");

            return false;
        }

        if ($flowExecutionUrl) {
            $this->http->NormalizeURL($flowExecutionUrl);
            $this->http->PostURL($flowExecutionUrl, "");
        }

        return true;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // Balance - HIGHFLYER points
        $this->SetBalance($this->http->FindSingleNode('//p[contains(text(), "HIGHFLYER")]/following-sibling::p[1]', null, true, "/(.+)\s*point/ims"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//p[contains(text(), "HIGHFLYER")]/preceding-sibling::p[2]')));
        // Company Name
        $this->SetProperty('CompanyName', $this->http->FindSingleNode('//p[contains(text(), "HIGHFLYER")]/preceding-sibling::p[1]'));
        // Corporate Code
        $this->SetProperty('CorporateCode', $this->http->FindSingleNode('//p[contains(., "Corporate Code:")]/node()[contains(., "Corporate Code:")]', null, true, "/:\s*(.+)/"));
        // Account created on
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//p[contains(., "Corporate Code:")]/node()[contains(., "Account created on:")]', null, true, "/:\s*(.+)/"));

        $this->http->GetURL("https://www.singaporeair.com/en_UK/sq-corporate-landing/upcoming-flights-ctm/"); //todo: may be useless page
    }

    public function ParseItineraries()
    {
        $result = [];

        return $result;

        $this->http->GetURL('https://www.singaporeair.com/');
        $itineraries = $this->http->XPath->query("//its");
        $this->logger->debug("Total {} itineraries were found");

        foreach ($itineraries as $itinerary) {
            $this->http->GetURL($itinerary->nodeValue);
            $it = $this->parseItinerary();
            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($it, true), ['pre' => true]);
            $result[] = $it;
        }

        return $result;
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

//        $postData = [
//            "type"         => "RecaptchaV2TaskProxyless",
//            "websiteURL"   => $this->http->currentUrl(),
//            "websiteKey"   => $key,
//        ];
//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
//        $this->recognizer->RecognizeTimeout = 120;
//
//        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(), //"https://www.singaporeair.com/en_UK/us/sq-corporate/#corporateLogin",//
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//p[contains(text(), "HIGHFLYER")]/preceding-sibling::p[2]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        // get https://www.singaporeair.com/resendOtpToCorporateEmail.form
        $this->http->GetURL("https://www.singaporeair.com/kfHome.form");
        $firstThreeChar = $this->http->FindSingleNode('//input[@id = "firstThreeChar"]/@value');
        $corporateMailDomain = $this->http->FindSingleNode('//input[@id = "corporateMailDomain"]/@value');

        if (!isset($firstThreeChar) || !isset($corporateMailDomain)) {
            return false;
        }

        if ($this->getWaitForOtc()) {
            $this->sendNotification("2fa - need to find email in queue // RR");
        }

        $question = "We've sent an OTP to your e-mail address {$firstThreeChar}•••••@{$corporateMailDomain}";
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = 'Question';

        return true;
    }

    private function parseItinerary()
    {
        $result = [];
        $bookNumber = '';
        $this->logger->info("Parse itinerary #{$bookNumber}", ['Header' => 3]);

        return $result;
    }

    private function getCookiesFromSelenium(): void
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();

            $selenium->http->driver->dontSaveStateOnStop();
            $selenium->http->saveScreenshots = true;

            /** @var \AwardWallet\Common\Entity\Fingerprint $fp */
            $fp = $this->services->get(\AwardWallet\Common\Selenium\FingerprintFactory::class)->getOne([\AwardWallet\Common\Selenium\FingerprintRequest::mac()]);
            $selenium->seleniumOptions->fingerprint = $fp->getFingerprint();

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL('https://www.singaporeair.com/en_UK/us/sq-corporate/#corporateLogin');
            $selenium->waitForElement(WebDriverBy::xpath('//input[@name="login-type" and @value="Business"]'), 5);
            $this->savePageToLogs($selenium);

            foreach ($selenium->driver->manage()->getCookies() as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            $selenium->http->cleanup();
        }
    }
}
