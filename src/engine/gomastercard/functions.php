<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerGomastercard extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    /*public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = [];
        $arg['CookieURL'][] = 'https://gomastercard-online.gomastercard.com.au/access/do?TYPE=33554432&REALMOID=06-3fe34332-eb0a-4216-93c3-7aaeb02cbf4e&GUID=&SMAUTHREASON=0&METHOD=GET&SMAGENTNAME=-SM-3cc09SeaMX%2fDeO1Uu%2bGjhso%2bT3ytRzlL5tniPfCLm1QCCOLAhKncYRb7Tjd70zUCs9B7BbEi1a0UxVbyuak6fu6baskCY9WE&TARGET=-SM-%2fwps%2fmyportal%2fgomc';
        $arg['CookieURL'][] = 'https://gomastercard-online.gomastercard.com.au/access/login';

        return $arg;
    }
    */

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyReCaptchaIt7());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->selenium();

        return false;

        $this->http->GetURL("https://gomastercard-online.gomastercard.com.au/wps/myportal/gomc");
        $this->http->GetURL("https://gomastercard-online.gomastercard.com.au/access/login");

        if (!$this->http->ParseForm(null, "//form[@action = '/fcc/ealogin.fcc']")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("USER", $this->AccountFields['Login']);
        $this->http->SetInputValue("PASSWORD", $this->AccountFields['Pass']);
        $this->http->Form['SUBMIT.x'] = '45';
        $this->http->Form['SUBMIT.y'] = '17';

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Scheduled maintenance
        if ($message = $this->http->FindSingleNode("//title[contains(text(), 'Scheduled maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, this service is currently unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Sorry, this service is currently unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
//        if (!$this->http->PostForm()) {
//            return $this->checkErrors();
//        }
        $this->incapsula();

        $error = $this->http->FindSingleNode('//h2[contains(text(),"To continue, please provide the answer to your secret question")]');

        if (isset($error)) {
            $this->Question = $this->http->FindSingleNode('//input[@id="SecurityQuestion_answer"]/../../../../tr[1]/td');
            $this->logger->debug("Question:[" . $this->Question . "]");

            if (!isset($this->Question)) {
                $this->ErrorCode = ACCOUNT_ENGINE_ERROR;

                return false;
            }
            $this->http->Log(var_export($this->Answers, true));

            if (isset($this->Answers[$this->Question])) {
                return $this->ProcessStep("AnswerQuestion");
            }
            $this->Step = "AnswerQuestion";
            $this->ErrorCode = ACCOUNT_QUESTION;

            return false;
        }

        if ($this->http->FindSingleNode('//h1[span[contains(text(), "New card number required")]]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindSingleNode("//a[contains(text(), 'Log Out')]")
            || $this->http->FindNodes("//a[contains(text(), 'Logout')]")) {
            return true;
        }

        //# Sorry, your account has been locked.
        if ($message = $this->http->FindPreg('/(Sorry, your account has been locked\.)/ims')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        /*
         * Sorry, but you're unable to access the Online Service Centre.
         * This is most likely due to the status of your account.
         */
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Sorry, but you\'re unable to access the Online Service Centre")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

//        $this->http->GetURL("https://gomastercard-online.gomastercard.com.au/access/login");

        if ($message = $this->http->FindSingleNode('//span[@class = "errors"]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        switch ($step) {
            case "AnswerQuestion":
                $this->logger->notice("answering to question: " . $this->Question);
                $this->http->Form['RESPONSE'] = $this->Answers[$this->Question];
                $this->http->Form['BINDDEVICE'] = 'false';
                $this->http->PostForm();

                if (preg_match("/The answer to your secret question is incorrect./ims", $this->http->Response['body'])) {
                    $this->Step = "AnswerQuestion";
                    $this->ErrorCode = ACCOUNT_QUESTION;
                    $this->ErrorMessage = "Please enter the correct answer to the security question";
                    // you have three attempts to answer this correctly before your account is suspended.
                    return false;
                }
                $this->logger->notice("AnswerQuestionOK");

                return true;

            default:
                parent::ProcessStep($step);
        }
    }

    public function Parse()
    {
        // Skip reminder
        if (($formUrl = $this->http->FindSingleNode("//input[@name = 'lnProceed']/ancestor::form[1]//a/@href"))
            && $this->http->FindPreg("/(?:Interest free promotion reminder|Overdue payment reminder)/ims")) {
            $this->logger->notice(">>> Skip reminder");
            $this->http->NormalizeURL($formUrl);
            $this->http->FormURL = $formUrl;
            $this->http->Form = [];
            $this->http->SetInputValue('lnProceed', 'Ok');
            $this->http->PostForm();

            if ($this->http->ParseForm("AUTOSUBMIT")) {
                $this->http->setDefaultHeader("Content-Type", "application/x-www-form-urlencoded");
                sleep(1);
                $this->http->PostForm();
            }
        }
        // Card activation reminder
        if ($this->http->FindSingleNode("//span[contains(text(), 'Card activation reminder')]")
            && ($homeLink = $this->http->FindSingleNode("//a[contains(@id, 'wpf_action_ref_4appaccountcardActivationportletscardActivationPortlet')]/@href"))) {
            $this->logger->notice(">>> Skip Card activation reminder");
            $this->http->NormalizeURL($homeLink);
            $this->http->GetURL($homeLink);
        }

        // Current Balance
        $this->SetProperty("CashBalance", $this->http->FindSingleNode("(//span[@id = 'current-expenses-value'])[1]"));
        // Available Credit
        $this->SetProperty("AvailableCredit", $this->http->FindSingleNode("(//span[@id = 'available-balance-value'])[1]"));
        // Credit Limit
        $this->SetProperty("CreditLimit", $this->http->FindSingleNode("(//span[@name = 'credit-limit-value'])[1]"));

        // Click on the "Rewards" item in the menu
        $this->logger->info("Rewards page", ['Header' => 3]);

        if ($rewardsLink = $this->http->FindSingleNode("(//li/a[contains(@href, '/wps/myportal/gomc/public/rewards')]/@href)[1]")) {
            $this->logger->debug("Link: " . $rewardsLink);
//            $this->http->NormalizeURL($rewardsLink);
            $rewardsLink = 'https://gomastercard-online.gomastercard.com.au' . $rewardsLink;
            $this->http->GetURL($rewardsLink);

            // Click on the 'Rewards' button to visit the GO MasterCard Rewards site.
            $link = $this->http->FindSingleNode("//a[contains(@id, 'wpf_action_ref_0apprewardsgoMasterCardmanageRewardsDetailsportletsmanageMyGoRewardsPortlet_Default')]/@href");

            // provider bug fix, if open empty "Rewards" page
            if (!$link) {
                $this->logger->notice("provider bug fix, empty \"Rewards\" page workaround");
                sleep(5);

                if ($rewardsLink = $this->http->FindSingleNode("(//li/a[contains(@href, '/wps/myportal/gomc/public/rewards')]/@href)[1]")) {
                    $this->logger->debug("Link: " . $rewardsLink);
                    $this->http->NormalizeURL($rewardsLink);
                    $this->http->GetURL($rewardsLink);
                    $link = $this->http->FindSingleNode("//a[contains(@id, 'wpf_action_ref_0apprewardsgoMasterCardmanageRewardsDetailsportletsmanageMyGoRewardsPortlet_Default')]/@href");
                }// if ($rewardsLink = $this->http->FindSingleNode("(//li/a[contains(@href, '/wps/myportal/gomc/public/rewards')]/@href)[1]"))
            }// if (!$link)

            // Balance - Rewards points balance (AccountID: 4086352)
            $balance = $this->http->FindSingleNode("//td[b[contains(text(), 'Rewards points balance:')]]/following-sibling::td/b");

            if ($link) {
                $this->logger->debug("Link: " . $link);
                $this->http->setDefaultHeader('User-Agent', HttpBrowser::PROXY_USER_AGENT);
                $this->http->GetURL($link);
            }

            //# Balance - You have ... points available
            $this->SetBalance($this->http->FindSingleNode("//span[@class = 'points_balance_431']/nobr/strong") ?? $balance);
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//td[contains(text(), "Welcome")]', null, true, '/Welcome\s*([^\.]+)\./')));
        }// if ($rewardsLink = $this->http->FindSingleNode("//li/a[contains(@href, '/wps/myportal/gomc/public/rewards')]/@href"))

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            //# We're sorry to hear your card was recently lost or stolen.
            if ($this->http->FindSingleNode("//h1/span[contains(text(), 'New card number required')]")) {
                throw new CheckException("GO Mastercard (GO Rewards) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }
            // AccountID: 3111369, 3383168, 3552783, 4112270
            $accountID = ArrayVal($this->AccountFields, 'RequestAccountID', $this->AccountFields['AccountID']);

            if (in_array($accountID, [3111369, 3383168, 3552783, 3582001, 4112270])) {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    protected function incapsula()
    {
        $this->logger->notice(__METHOD__);
        $referer = $this->http->currentUrl();
        $incapsula = $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src");

        if (isset($incapsula)) {
            sleep(2);
            $incapsula = 'https://gomastercard-online.gomastercard.com.au' . $incapsula;
//            $this->http->NormalizeURL($incapsula);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($incapsula);
            $this->http->RetryCount = 2;
        }// if (isset($distil))
        $this->logger->debug("parse captcha form");
        $formURL = $this->http->FindPreg("/\"POST\", \"(\/_Incapsula_Resource\?[^\"]+)\"\s*,/");

        if (!$formURL) {
            return false;
        }
        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->NormalizeURL($formURL);
        $this->http->FormURL = $formURL;
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
//        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        sleep(2);
        $this->logger->notice("sssssssss -> {$referer}");
        $this->http->GetURL("https://gomastercard-online.gomastercard.com.au/access/login");
        $this->logger->notice("sssssssss");

        if (!$this->http->ParseForm(null, "//form[@action = '/fcc/ealogin.fcc']")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("USER", $this->AccountFields['Login']);
        $this->http->SetInputValue("PASSWORD", $this->AccountFields['Pass']);
        $this->http->Form['SUBMIT.x'] = '45';
        $this->http->Form['SUBMIT.y'] = '17';

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        return true;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class = 'form_container']/div[@class = 'g-recaptcha']/@data-sitekey");

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->increaseTimeLimit(300);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, true, 3, 1);
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = null;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useFirefox();
            $selenium->setKeepProfile(true);
            $selenium->http->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();

            $selenium->usePacFile(false);

            $selenium->disableImages();
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL('https://gomastercard-online.gomastercard.com.au/wps/myportal/gomc');
            $login = $selenium->waitForElement(WebDriverBy::id("AccessToken_Username"), 10);
            $this->savePageToLogs($selenium);
            $pass = $selenium->waitForElement(WebDriverBy::id("AccessToken_Password"), 0);
            $signInButton = $selenium->waitForElement(WebDriverBy::id("submit-reg-button"), 0);

            if (!$login || !$pass || !$signInButton) {
                return $this->checkErrors();
            }

            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);
            $signInButton->click();

            $result = $selenium->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Current Balance')] | //input[@id = 'goHomeButton']"), 10);
            $this->savePageToLogs($selenium);

            if ($goHomeButton = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'goHomeButton']"), 0)) {
                $goHomeButton->click();
                $result = $selenium->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Current Balance')] | //input[@id = 'goHomeButton']"), 10);
                $this->savePageToLogs($selenium);
            }

            if ($result) {
                // Current Balance
                $this->SetProperty("CashBalance", $this->http->FindSingleNode("(//span[@id = 'current-expenses-value'])[1]"));
                // Available Credit
                $this->SetProperty("AvailableCredit", $this->http->FindSingleNode("(//span[@id = 'available-balance-value'])[1]"));
                // Credit Limit
                $this->SetProperty("CreditLimit", $this->http->FindSingleNode("(//span[@name = 'credit-limit-value'])[1]"));

                // Click on the "Rewards" item in the menu
                $this->logger->info("Rewards page", ['Header' => 3]);

                if ($rewardsLink = $this->http->FindSingleNode("(//li/a[contains(@href, '/wps/myportal/gomc/public/rewards')]/@href)[1]")) {
                    $this->logger->debug("Link: " . $rewardsLink);
//                    $rewardsLink = 'https://gomastercard-online.gomastercard.com.au' . $rewardsLink;
                    $selenium->http->NormalizeURL($rewardsLink);
                    $selenium->http->GetURL($rewardsLink);

                    // Click on the 'Rewards' button to visit the GO MasterCard Rewards site.
                    $link = $this->http->FindSingleNode("//a[contains(@id, 'wpf_action_ref_0apprewardsgoMasterCardmanageRewardsDetailsportletsmanageMyGoRewardsPortlet_Default')]/@href");

                    // provider bug fix, if open empty "Rewards" page
                    if (!$link) {
                        $this->logger->notice("provider bug fix, empty \"Rewards\" page workaround");
                        sleep(5);

                        if ($rewardsLink = $this->http->FindSingleNode("(//li/a[contains(@href, '/wps/myportal/gomc/public/rewards')]/@href)[1]")) {
                            $this->logger->debug("Link: " . $rewardsLink);
                            $selenium->http->NormalizeURL($rewardsLink);
                            $selenium->http->GetURL($rewardsLink);
                            $link = $this->http->FindSingleNode("//a[contains(@id, 'wpf_action_ref_0apprewardsgoMasterCardmanageRewardsDetailsportletsmanageMyGoRewardsPortlet_Default')]/@href");
                        }// if ($rewardsLink = $this->http->FindSingleNode("(//li/a[contains(@href, '/wps/myportal/gomc/public/rewards')]/@href)[1]"))
                    }// if (!$link)

                    $selenium->waitForElement(WebDriverBy::xpath("//td[b[contains(text(), 'Rewards points balance:')]]/following-sibling::td/b"), 10);
                    $this->savePageToLogs($selenium);

                    // Balance - Rewards points balance (AccountID: 4086352)
                    $balance = $this->http->FindSingleNode("//td[b[contains(text(), 'Rewards points balance:')]]/following-sibling::td/b");

                    if ($link) {
                        $this->logger->debug("Link: " . $link);
                        $this->http->setDefaultHeader('User-Agent', HttpBrowser::PROXY_USER_AGENT);
                        $this->http->GetURL($link);
                    }

                    // Balance - You have ... points available
                    $this->SetBalance($this->http->FindSingleNode("//span[@class = 'points_balance_431']/nobr/strong") ?? $balance);
                    // Name
                    $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//td[contains(text(), "Welcome")]', null, true, '/Welcome\s*([^\.]+)\./')));
                }
            } else {
                $this->Login();
            }

//            $cookies = $selenium->driver->manage()->getCookies();
//
//            foreach ($cookies as $cookie) {
//                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
//            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo
        }

        return $currentUrl;
    }
}
