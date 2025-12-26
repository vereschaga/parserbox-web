<?php

use AwardWallet\Engine\myer\QuestionAnalyzer;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerMyer extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
        $this->http->saveScreenshots = true;

//        $this->useFirefoxPlaywright();
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        $this->setProxyBrightData(null, 'static', 'au');
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
    }

    /*
    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.myerone.com.au/dashboard", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }
    */

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false) {
            throw new CheckException('The MYER one number entered is incorrect or not registered for online access.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.myerone.com.au/signin");

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "username"]'), 0);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@name="action"]'), 0);
        $this->saveResponse();

        if (!$loginInput && !$passwordInput && !$button) {
            $this->logger->error("password not found");

            return false;
        }

        $loginInput->clear();
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->clear();
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $button->click();

        /*
        if (!$this->http->ParseForm(null, "//form[@data-form-primary = 'true']")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("action", "default");
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Sorry for the inconvenience, we are performing scheduled maintenance.
        if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'Sorry for the inconvenience, we are performing scheduled maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')] | //span[contains(@class, 'ulp-input-error-message') and normalize-space(.) != ''] | //span[contains(@class, 'ulp-authenticator-selector-text')]"), 10);
        $this->saveResponse();

        if ($this->processSecurityQuestion()) {
            return false;
        }

        /*
        if (!$this->http->PostForm()) {
            return false;
        }
        */

        // Verify my Account
        if ($this->http->FindPreg("/<meta\s*name=\"Location\" content=\"\/verifymyaccount\?cardNumber={$this->AccountFields['Login']}\"/ims")) {
            $this->throwProfileUpdateMessageException();
        }

        /*
        if ($location = $this->http->FindPreg("/<meta\s*name=\"Location\" content=\"([^\"]+)/")) {
            $this->logger->notice("Change location to -> {$location}");
            $this->http->NormalizeURL($location);
            $this->http->GetURL($location);
        }

        if ($redirect = $this->http->FindPreg("/click <a href=\"(\/apex\/CommunitiesLanding)\">here<\/a> to continue\./")) {
            $this->logger->notice("Redirect to -> {$redirect}");
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);
        }

        // invalid credentials
        if ($message = $this->http->FindPreg("/>(The MYER one member number and\/or password is incorrect\.[^<]+)/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The MYER one number entered is incorrect
        if ($message = $this->http->FindPreg("/>(The MYER one number entered is incorrect[^<]+)/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your registration has not yet been activated.
        if ($message = $this->http->FindPreg("/>(Your registration has not yet been activated\.[^<]+)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The number of attempts to log in to this MYER one account have been exceeded.
        if ($message = $this->http->FindPreg("/>(The number of attempts to log in to this MYER one account have been exceeded\.[^<]+)/")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($redirect = $this->http->FindPreg("/window\.location\.href ='([^']+)/ims")) {
            $this->logger->notice("Redirect to -> {$redirect}");
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);

            if ($redirect = $this->http->FindPreg("/window\.location\.replace\(\"([^\"]+)\"/ims")) {
                $this->logger->notice("Redirect to -> {$redirect}");
                $this->http->NormalizeURL($redirect);
                $this->http->GetURL($redirect);

                if ($redirect = $this->http->FindPreg("/window\.location\.href ='([^']+)/ims")) {
                    $this->logger->notice("Redirect to -> {$redirect}");
                    $this->http->NormalizeURL($redirect);
                    $this->http->GetURL($redirect);
                }
            }
        }
        // UPDATE ACCOUNT
        if ($this->http->FindPreg('/setupid=ChangePassword\">here<\/a> to continue\./')
            || $this->http->FindSingleNode("//input[contains(@value, 'UPDATE ACCOUNT')]/@value")) {
            throw new CheckException("Myer (Myer one Gift Cards) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }
        */

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//span[contains(@class, 'ulp-input-error-message') and normalize-space(.) != '']")) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Please try again, this information doesn\'t match our records. Or select Forgot Password.'
                || $message == 'Sorry, something went wrong. Please try again later.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Please give us a call on 13 69 37 to update your details as we currently have no contact methods for Two-Step Verification') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'Your MYER one account have been Frozen.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode("//div[contains(text(), 'Earned so far since joining')]/following-sibling::div[1]")) {
            throw new CheckRetryNeededException(2, 7);
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        return $this->processSecurityQuestion();
    }

    public function Parse()
    {
        $balanceEmpty = false;
        // Balance - Your current shopping credits
        if (!$this->SetBalance($this->http->FindPreg("/elId:\s*'credits'\s*,\s*count:\s*([\-\d]+)\s*,/ims"))) {
            if ($this->http->FindSingleNode("//span[contains(@id, 'lblPointTotal')]") === '') {
                $this->logger->notice("Credits are not found");
//                $balanceEmpty = true;
            }
        }
        // Earned since joining
        $this->SetProperty("EarnedSinceJoining", $this->http->FindSingleNode("//div[contains(text(), 'Earned so far since joining')]/following-sibling::div[1]"));
        // Earned last quarter
        $this->SetProperty("LastQuarter", $this->http->FindSingleNode("//div[contains(text(), 'Earned last quarter')]/following-sibling::div[1]"));
        // Earned this quarter
        $this->SetProperty("ThisQuarter", $this->http->FindSingleNode("//div[contains(text(), 'Earned so far this quarter')]/following-sibling::div[1]"));
        // Status
        $status = $this->http->FindSingleNode("(//div[contains(@class, 'membership-level')]/@class)[1]", null, true, "/membership-level(\d+)/");
        $this->logger->debug(">>> Status: {$status}");

        switch ($status) {
            case '1':
                $this->SetProperty("Status", "Member");

                break;

            case '2':
                $this->SetProperty("Status", "Silver");

                break;

            case '3':
                $this->SetProperty("Status", "Gold");

                break;

            case '4':
                $this->SetProperty("Status", "Platinum");

                break;

            default:
                if (!empty($status)) {
                    $this->ArchiveLogs = true;
                    $this->sendNotification("myer - Unknown Status: $status");
                }

                break;
        }// switch ($status)
        // Current Purchases
        $this->SetProperty("CurrentPurchases", $this->http->FindSingleNode("//div[contains(text(), 'Current Purchases')]/following-sibling::div[1]"));
        // Last Year Purchases
        $this->SetProperty("LastYearPurchases", $this->http->FindSingleNode('//div[contains(text(), "Last Year\'s Purchases")]/following-sibling::div[1]'));
        // Untill the next level
        $this->SetProperty("UtillNextLevel", $this->http->FindSingleNode("(//div[contains(text(), 'to become a MYER one')])[1]", null, true, "/Spend\s*(.+)\s*(:?at\s*any|by)/ims"));

        //# if Shopping Credits are not exist in profile
        if ($this->ErrorCode === ACCOUNT_ENGINE_ERROR && !$this->http->FindPreg("/Current balance/ims") && $balanceEmpty) {
            $this->SetBalanceNA();
        }

        $this->http->GetURL("https://www.myerone.com.au/AccountDetail");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[contains(@name, 'firstName')]/@value") . " " . $this->http->FindSingleNode("//input[contains(@name, 'lastName') and @value != '__Unknown__']/@value")));
    }

    protected function processSecurityQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $questionObject = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We’ve sent a code to your ")]'), 5);
        $this->saveResponse();

        if (!isset($questionObject)) {
            $this->logger->notice("question not foun");

            return false;
        }

        $question = trim($questionObject->getText());
        $this->logger->debug("Question -> {$question}");

        $target = $this->http->FindSingleNode("//span[contains(@class, 'ulp-authenticator-selector-text')]");

        if (!$target) {
            $this->logger->error("target not found");

            return false;
        }

        $question .= " " . $target;
        $this->logger->debug("Question -> {$question}");

        if (strstr($question, '@') && !QuestionAnalyzer::isOtcQuestion($question)) {
            $this->sendNotification("need to check QuestionAnalyzer");
        }

        if (!isset($this->Answers[$question]) || !is_numeric($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return true;
        }

        $this->logger->debug("Entering answer on question -> {$question}...");
        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        $answerInput = $this->driver->findElement(WebDriverBy::xpath('//input[@name="code"]'));
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@name="action"]'), 0);
        $this->saveResponse();

        if (!$answerInput || !$button) {
            $this->logger->error("something went wrong");

            return false;
        }

        $answerInput->clear();
        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->sendKeys($answerInput, $answer, 5);
        $button->click();

        sleep(10);

        $error = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Sorry. That code is incorrect.') or contains(text(), 'Incorrect code. You have')]"), 0); // TODO: fake
        $this->saveResponse();

        if ($error) {
            $this->holdSession();
            $answerInput->clear();
            $this->AskQuestion($question, $error->getText(), "Question");
            $this->logger->error("answer was wrong");

            return false;
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        return false;
    }
}
