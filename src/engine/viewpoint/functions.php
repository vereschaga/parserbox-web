<?php

class TAccountCheckerViewpoint extends TAccountChecker
{
    public const REWARDS_PAGE_URL = 'https://viewpointforum.com/member/home';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
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
        $this->http->GetURL("https://viewpointforum.com/access/loginform");

        if (!$this->http->ParseForm('login')) {
            return false;
        }
        $this->http->SetInputValue("uname", $this->AccountFields['Login']);
        $this->http->SetInputValue("pword", $this->AccountFields['Pass']);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->http->SetInputValue("g-recaptcha-response", $captcha);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode('//p[@class="err"]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Account is not active
        if ($message = $this->http->FindSingleNode('//div[@class = "alert alert-danger" and normalize-space() = "Account is not active"]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Account Verification
        if ($this->http->FindSingleNode('//h1[contains(text(), "Account Verification")]') && $this->parseQuestion()) {
            return false;
        }
        // Invalid username or password
        if ($msg = $this->http->FindSingleNode('//div[contains(text(), "Invalid username or password")]')) {
            throw new CheckException($msg, ACCOUNT_INVALID_PASSWORD);
        }

        throw new CheckRetryNeededException();

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->State["securityQuestions"] = [];
        $inputs = $this->http->FindNodes("//form[@name = 'info']//input[@required]/@name");

        foreach ($inputs as $name) {
            $question = $this->http->FindSingleNode("//form[@name = 'info']//input[@required and @name = '{$name}']/preceding-sibling::label");

            if (!empty($question)) {
                $this->State["securityQuestions"][$name] = $question;
            }
        }// foreach ($inputs as $name)

        if (empty($question) || !$this->http->ParseForm("info")) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->State["securityQuestions"])) {
            return false;
        }

        foreach ($this->State["securityQuestions"] as $name => $question) {
            // collect answers
            if (!isset($this->Answers[$question])) {
                $this->AskQuestion($question);

                return false;
            }// if (!isset($this->Answers[$question]))
            else {
                $this->http->SetInputValue($name, $this->Answers[$question]);
            }
        }// foreach ($this->State["securityQuestions"] as $name => $question)
        $this->http->SetInputValue("info-submit", "Continue");

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($error = $this->http->FindSingleNode('//div[contains(text(), "Account information did not match. Please re-try your login.")]')) {
            foreach ($this->State["securityQuestions"] as $name => $question) {
                unset($this->Answers[$question]);
            }// foreach ($this->State["securityQuestions"] as $question)

            if (isset($question)) {
                $this->AskQuestion($question, $error);
            }

            return false;
        }// if ($this->http->FindSingleNode('//div[contains(text(), "Account information did not match. Please re-try your login.")]'))

        if ($this->http->FindPreg("/form name=\"redirect\" action=\"\/member\/home\"/")) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        return true;
    }

    public function Parse()
    {
        // Points Pending Redemption
        $this->SetProperty("PointsPendingRedemption", $this->http->FindSingleNode('//td[contains(text(), "Points Pending Redemption")]/following-sibling::td[1]'));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//div[@id = 'member-info']//h2[@class = 'panel-title']")));

        $this->http->GetURL('https://viewpointforum.com/member/cashoutform');
        // Balance - Available Points
        $this->SetBalance($this->http->FindSingleNode('//li[contains(text(), "Available Points")]/b'));
        // Cash Value
        $this->SetProperty('CashValue', $this->http->FindSingleNode('//li[contains(text(), "Cash Value")]/b'));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@name = 'login']//div[@class = 'h-captcha']/@data-sitekey");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => "hcaptcha",
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]")) {
            return true;
        }

        return false;
    }
}
