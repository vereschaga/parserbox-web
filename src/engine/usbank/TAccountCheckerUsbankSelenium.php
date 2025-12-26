<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\usbank\QuestionAnalyzer;

class TAccountCheckerUsbankSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const XPATH_TWO_FA_HEADERS = '
        //p[contains(text(), "To help protect your accounts, we\'ll send a notification to your")]
        | //h2[contains(text(), "We need to verify your identity")]
    ';

    private $accesstoken = null;
    private $aftokenvalue = null;
    private $baseURL = null;

    public function InitBrowser()
    {
        TAccountChecker::InitBrowser();
        $this->UseSelenium();

        $this->useFirefox();
        $this->setKeepProfile(true);

        if ($this->attempt > 0) {
            $this->setKeepProfile(false);
        }

        if (!isset($this->State['Fingerprint']) || $this->attempt > 0) {
            $this->logger->notice("set new Fingerprint");

            $fp = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::firefox()]);

            if ($fp !== null) {
                $this->logger->info("selected fingerprint {$fp->getId()}, {{$fp->getBrowserFamily()}}:{{$fp->getBrowserVersion()}}, {{$fp->getPlatform()}}, {$fp->getUseragent()}");
                $this->State['Fingerprint'] = $fp->getFingerprint();
//                    $this->State['UserAgent'] = $fp->getUseragent();
                $this->State['Resolution'] = [$fp->getScreenWidth(), $fp->getScreenHeight()];
            }
        }

        if (isset($this->State['Fingerprint'])) {
            $this->logger->debug("set fingerprint");
            $this->seleniumOptions->fingerprint = $this->State['Fingerprint'];
        }

//        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);

        $this->usePacFile(false);
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->driver->manage()->window()->maximize();
        $this->http->removeCookies();

        try {
//            $this->http->GetURL("https://www.usbank.com/index.html");
            $this->http->GetURL("https://onlinebanking.usbank.com/auth/login/?channel=mobileweb");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        } catch (TimeOutException | ScriptTimeoutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }
//        $this->http->GetURL("https://onlinebanking.usbank.com/auth/login/");

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'Username']"), 20);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'Password']"), 0);
        $submitButton = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'login-button-continue']"), 0);
        $this->saveResponse();

        if (empty($login) || empty($pass) || empty($submitButton)) {
            $this->logger->error('something went wrong');

            if ($this->http->FindPreg("/<input name=\"Username\"/")) {
                throw new CheckRetryNeededException(3, 0);
            }

            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);

        $this->logger->debug("clicking submit");

        try {
            $this->saveResponse();
            $submitButton->click();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        try {
            $this->waitSomeUI();
            $this->saveResponse();

            if ($this->waitSignoutButton()) {
                return true;
            }

            // switch to sq instead sms
            if ($this->waitForElement(WebDriverBy::xpath('//div[
                    contains(text(), "Choose the mobile number you’d like us to send your passcode to.")
                    or contains(text(), "Choose how you\'d like us to verify your identity.")
                    or contains(text(), "Where should we send your code?")
                ]
                '), 0)
            ) {
                if ($differentMethod = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Verify with a different method") or @data-testid="usb-link-"]'), 0)) {
                    $differentMethod->click();
                }

                $asqSQ = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Ask me an ID Shield question.")]'), 5);
                $this->saveResponse();
                $nextXpath = '//button[@id = "shield-continue"]';

                if (!$asqSQ) {
                    $asqSQ =
                        $this->waitForElement(WebDriverBy::xpath('//span[@id = "emailradio0--text"]'), 0)
                        ?? $this->waitForElement(WebDriverBy::xpath('//span[@id = "smsradio0--text"]'), 0)
                    ;
                    $nextXpath = '//button[@id = "otp-cont-button"]';

                    if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                        $this->Cancel();
                    }
                }

                $asqSQ->click();
                $this->logger->debug("ready to click");
                $next = $this->waitForElement(WebDriverBy::xpath($nextXpath), 0);
                $this->saveResponse();

                if (!$next) {
                    $this->logger->error("btn not found");

                    return false;
                }

                $next->click();

                $this->waitSomeUI();
                $this->saveResponse();
            }// if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Choose the mobile number you’d like us to send your passcode to.")]'), 0))

            try {
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            } catch (NoSuchDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage());

                throw new CheckRetryNeededException(2, 0);
            }

            if ($this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Please confirm the following is your primary")]'), 0)) {
                if ($contBtn = $this->waitForElement(WebDriverBy::xpath('//button[@name="CONTINUE" or @name="continue"]'), 0)) {
                    $contBtn->click();
                    // We need to verify your identity.
                    $contBtn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "otp-submit-button"]'), 5);
                    $this->saveResponse();

                    if ($contBtn) {
                        $contBtn->click();
                    } else {
                        $this->driver->executeScript("try { document.getElementById('otp-submit-button').click(); } catch (e) {}");
                    }
                }

                $error = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "enter-passcode-help-text")] | //p[@id = "error-single-notification--text"]'), 7);
                $this->saveResponse();
//
//                if (
//                    $error
//                    && strstr($error->getText(), "We couldn't verify your phone number. Please enter a different number or try another way.")
//                    && ($tryAnotherWay = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Try another way")]'), 0))
//                ) {
//                    $tryAnotherWay->click();
//
//                    sleep(5);
//                    $this->saveResponse();
//                }
            }

            if ($this->http->FindSingleNode('//div[@id = "shield-input" or @id = "kba-input"]//label[@id]')) {
                return $this->processQuestion();
            }

            if ($this->http->FindSingleNode('//p[contains(@class, "enter-passcode-help-text")]')) {
                return $this->process2fa();
            }

            if ($this->http->FindSingleNode(self::XPATH_TWO_FA_HEADERS)) {
                if ($button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "otp-cont-button"]'), 0)) {
                    $button->click();

                    $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "enter-passcode-help-text")]'), 10);
                }

                return $this->process2fa();
            }

            if ($message = $this->http->FindSingleNode('
                    //h2[@id = "top-error-msg-single-notification--text"]
                    | //div[@id = "top-error-msg-children-notification"]
                    | //p[@id = "error-single-notification--text"]/text()[last()]
                ')
                ?? $this->http->FindSingleNode('//div[@role="alert" and not(contains(@class, "hide"))]/span')
                ?? $this->http->FindSingleNode('//div[@role="alert" and not(contains(@class, "hide"))]/p')
            ) {
                $this->logger->error("[Error]: {$message}");

                $message = preg_replace("/^Alert /", "", $message);
                $message = preg_replace("/^Warning Error\s*/", "", $message);

                // false positive error
                if (strstr($message, 'We\'re experiencing some issues on our end. Please try again later.')) {
                    throw new CheckRetryNeededException(2, 0, $message);
                }

                // TODO:
                if (strstr($message, 'Its look like this email address belongs to another customer, please enter a different email address.')) {
                    if ($tryAnotherWay = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Try another way")]'), 0)) {
                        $tryAnotherWay->click();

                        $contBtn = $this->waitForElement(WebDriverBy::xpath('//button[@name="CONTINUE" or @name="continue"]'), 5);
                        $this->saveResponse();

                        if ($contBtn) {
                            $contBtn->click();
                            // We need to verify your identity.
                            $contBtn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "otp-submit-button"]'), 5);
                            $this->saveResponse();

                            if ($contBtn) {
                                $contBtn->click();
                            } else {
                                $this->driver->executeScript("try { document.getElementById('otp-submit-button').click(); } catch (e) {}");
                            }

                            $error = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "enter-passcode-help-text")] | //p[@id = "error-single-notification--text"] | //h1[contains(text(), "We\'re missing something important.")]'), 7);
                            $this->saveResponse();

                            // AccountID: 3304401
                            if ($error
                                && (
                                    strstr($error->getText(), "We couldn't verify your phone number. Please enter a different number or try another way.")
                                    || strstr($error->getText(), "We're missing something important.")
                                )
                                && ($this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Try another way")]'), 0))
                            ) {
                                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                            }
                        }

                        if ($this->http->FindSingleNode('//p[contains(@class, "enter-passcode-help-text")]')) {
                            return $this->process2fa();
                        }
                    }
                }

                if (
                    strstr($message, 'Enter your username again. ')
                    || strstr($message, 'We\'re sorry. It looks like your username has been disabled. Please contact ')
                    || strstr($message, 'Your username and/or password are incorrect. Please try again.')
                    || strstr($message, 'Something you entered is incorrec')
                    || $message == 'Your username or password are incorrect. Please try again.'
                    || $message == 'Your username or password is incorrect. Please try again.'
                    || $message == 'Try again. Passwords are between 8 and 24 characters.'
                    || $message == 'Something you entered is incorrect. Please try again.'
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    strstr($message, 'Your account is locked.')
                    || strstr($message, 'Your login is disabled')
                    || strstr($message, 'We had to lock your account after too many attempts')
                ) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                if (
                    $message == "We couldn't confirm your phone number. Please try again."
                    || $message == "We couldn't verify your phone number. Please enter a different number or try another way."
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                return false;
            }

            $this->saveResponse();

            if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Reset your username and password to log in.")]')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            // sensor_data issue
            if ($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "We are logging you in. It won\'t take much time.")]'), 0)) {
                throw new CheckRetryNeededException(3);
            }

            if ($this->http->FindSingleNode('//h1[contains(text(), "We\'re missing something important.")]')) {
                $this->throwProfileUpdateMessageException();
            }

            if ($this->http->FindSingleNode('//h1[contains(text(), "Accept U.S. Bank terms and agreements.")]')) {
                $this->throwAcceptTermsMessageException();
            }
        } catch (Exception | NoSuchDriverException $e) {
            $this->logger->error("[Exception]: {$e->getMessage()}");

            if (
                strstr($e->getMessage(), 'Tried to run command without establishing a connection Build info: version')
            ) {
                throw new CheckRetryNeededException(2, 0);
            }

            throw $e;
        }

        return false;
    }

    public function process2fa()
    {
        $this->logger->notice(__METHOD__);

        $q = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "enter-passcode-help-text")]'), 0);
        $questionInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "idshield-input"]'), 0);

        if (!$q || !$questionInput) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return false;
        }

        $question = $q->getText();

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $question = str_replace("Tu código vencerá en 5 minutos.", "", $question);

            if (!QuestionAnalyzer::isOtcQuestion($question) && !strstr($question, 'XXX-')) {
                $this->sendNotification("question has been changed");
            }

            $this->AskQuestion($question, null, "2fa");

            return false;
        }

        $questionInput->clear();
        $questionInput->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);
        $this->logger->debug("ready to click");
        $this->saveResponse();
        $next = $this->waitForElement(WebDriverBy::xpath('//button[@id = "otp-cont-button"]'), 5);
        $this->saveResponse();

        if (!$next) {
            $this->logger->error("btn not found");

            return false;
        }

        $this->logger->debug("clicking Continue");
        $next->click();

        sleep(5);
        $this->saveResponse();

        if ($this->waitSignoutButton()) {
            return true;
        } else {
            sleep(3);
            $this->saveResponse();
        }

        if ($error = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "That code doesn\'t match. Please try again.")]'), 0)) {
            $message = $error->getText();
            $this->logger->error("[Question Error]: {$message}");

            if (strstr($message, 'That code doesn\'t match. Please try again.')) {
                unset($this->Answers[$question]);
                $this->holdSession();
                $this->AskQuestion($question, $message, "2fa");
            }

            return false;
        }

        $this->waitSomeUI();

        $logout = $this->waitSignoutButton();

        try {
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        } catch (UnknownServerException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        if (!$logout) {
            sleep(5);
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            $this->saveResponse();

            $myAccounts = $this->waitForElement(WebDriverBy::xpath('//*[self::a or self::button][contains(text(), "My accounts")]'), 10);
            $this->saveResponse();

            if (!$myAccounts && $this->http->currentUrl() == 'https://onlinebanking.usbank.com/digital/servicing/customer-dashboard') {
                $this->http->GetURL("https://onlinebanking.usbank.com/digital/servicing/customer-dashboard");
                $this->waitForElement(WebDriverBy::xpath('//*[self::a or self::button][contains(text(), "My accounts")]'), 10);
                $this->saveResponse();
            }
        }

        return $this->waitSignoutButton();
    }

    public function processQuestion()
    {
        $this->logger->notice(__METHOD__);

        $q = $this->waitForElement(WebDriverBy::xpath('//div[@id = "shield-input" or @id = "kba-input"]//label[@id]'), 0);
        $questionInput = $this->waitForElement(WebDriverBy::xpath('//div[@id = "shield-input" or @id = "kba-input"]//label[@id]/following-sibling::input'), 0);

        if (!$q || !$questionInput) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return false;
        }

        $question = $q->getText();

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        $questionInput->clear();
        $questionInput->sendKeys($this->Answers[$question]);
        $this->logger->debug("ready to click");
        $this->saveResponse();
        $next = $this->waitForElement(WebDriverBy::xpath('//button[@id = "shield-continue" or @id = "kba-continue"]'), 5);
        $this->saveResponse();

        if (!$next) {
            $this->logger->error("btn not found");

            return false;
        }

        $this->logger->debug("clicking next");
        $next->click();

        sleep(5);
        $this->saveResponse();

        if ($error = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "error-text__error")]'), 0)) {
            $message = $error->getText();
            $this->logger->error("[Question Error]: {$message}");

            if (strstr($message, 'That answer doesn\'t match. Please try again.')) {
                unset($this->Answers[$question]);
                $this->holdSession();
                $this->AskQuestion($question, $message, "Question");
            }

            return false;
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "Please log in at usbankfocus.com to access and manage your Focus Card account online.")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if (!$this->waitSignoutButton()) {
            sleep(5);

            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            $this->saveResponse();

            if ($this->http->currentUrl() == 'https://onlinebanking.usbank.com/digital/servicing/customer-dashboard') {
                $this->http->GetURL("https://onlinebanking.usbank.com/digital/servicing/customer-dashboard");
                $myAccounts = $this->waitForElement(WebDriverBy::xpath('//*[self::a or self::button][contains(text(), "My accounts")]'), 10);
                $this->saveResponse();
            }
        }

        // AccountID: 5220842
        if ($this->AccountFields['Login'] == 'allensun888') {
            throw new CheckException("We're experiencing some issues on our end. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->waitSignoutButton();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $input = $this->waitForElement(WebDriverBy::xpath('//div[@id = "shield-input"]//label[@id]'), 0);
        $this->saveResponse();

        if (
            !$input
            && (
                $this->waitForElement(WebDriverBy::xpath("
                    //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                    | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                    | //p[contains(text(), 'Health check')]
                "), 0)
            )
        ) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == 'Question') {
            $this->saveResponse();

            return $this->processQuestion();
        }

        if ($step == '2fa') {
            $this->saveResponse();

            return $this->process2fa();
        }

        return true;
    }

    public function Parse()
    {
        // return to old design
        try {
            try {
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            } catch (UnknownServerException $e) {
                $this->logger->error("exception: " . $e->getMessage());

                throw new CheckRetryNeededException(3, 0);
            }
            //        $this->http->GetURL("https://onlinebanking.usbank.com/USB/CustomerDashboard/Index");
            $myAccounts = $this->waitForElement(WebDriverBy::xpath('//*[self::a or self::button][contains(text(), "My accounts") or text() = "Accounts"]'), 5);
            $this->closePopups(3);
            $this->saveResponse();

            if (!$myAccounts && $this->http->currentUrl() == 'https://onlinebanking.usbank.com/digital/servicing/customer-dashboard') {
                $this->http->GetURL("https://onlinebanking.usbank.com/digital/servicing/customer-dashboard");
                $myAccounts = $this->waitForElement(WebDriverBy::xpath('//*[self::a or self::button][contains(text(), "My accounts") or text() = "Accounts"]'), 10);
                $this->saveResponse();
            }

            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h2[@data-testid="heading"] | //p[@class = "bannerMain"]', null, true, '/,\s*([^.!]+)/ims')));

            $this->skipSettingUpdate();

            $this->accesstoken = $this->driver->executeScript("return sessionStorage.getItem('AccessToken');");
            $this->logger->debug("[Form accesstoken]: " . $this->accesstoken);

            $this->aftokenvalue = $this->driver->executeScript("return sessionStorage.getItem('AFTokenValue');");
            $this->logger->debug("[Form aftokenvalue]: " . $this->aftokenvalue);

            if (!$myAccounts) {
                return;
            }

            $this->saveResponse();

            try {
                $myAccounts->click();
            } catch (Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
                $this->logger->error("exception: " . $e->getMessage());
                $this->closePopups();
                $myAccounts->click();
            }

            $myRewards = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "My rewards")]'), 5);
            $rewardsButton = $this->waitForElement(WebDriverBy::xpath('//button[normalize-space(.) = "Rewards"]'), 0);

            // refs #23979
            if (!$myRewards) {
                $myAccounts->click();
                $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "listing@rewards")]'), 0);
            }

            if (!$myRewards && $rewardsButton) {
                $rewardsButton->click();
                sleep(1);
            }

            $this->saveResponse();

            $this->closePopups();

            if (!$myRewards) {
                $cards = $this->http->XPath->query('//div[contains(@class, "rewards-cards-widget__card-wrapper")]');
                $this->logger->debug("[New version]: Total cards {$cards->length} were found");

                if ($cards->length == 0) {
                    $cards = $this->http->XPath->query('//div[@role="listitem"] | //div[contains(@data-testid, "creditcard-phase")]');
                    $this->logger->debug("[Old version]: Total cards {$cards->length} were found");
                }

                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h1[@class="welcome-message-title"]', null, true, '/,\s*([^.!]+)/ims')));

                if ($cards->length) {
                    foreach ($cards as $card) {
                        // Detected cards
                        $number = $this->http->FindSingleNode('.//p[@class = "listing@details"] | .//h4[contains(@class, "header")]//span[contains(text(), "ending in")]/following-sibling::span[1] | .//a[@aria-label="Account name"] | .//p[contains(@class, "__last-four")]', $card, true, "/\.\.\.(\d{4})/");
                        $this->logger->debug("[Card number]: {$number}");
                        $displayName =
                            $this->http->FindSingleNode('.//span[@class = "account-type"]', $card)
                            ?? $this->http->FindSingleNode('.//h4[contains(@class, "header")]', $card, true, "/(.+) ending in/")
                            ?? $this->http->FindSingleNode('.//p[contains(@class, "__card-name")]', $card)
                            ?? $this->http->FindSingleNode('.//a[@aria-label="Account name"]', $card, true, "/(.+) \.\.\./")
                        ;
                        $detectedCard = [
                            "Code"            => $number,
                            "DisplayName"     => $displayName . " ending in " . $number,
                            "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                        ];

                        $this->AddDetectedCard($detectedCard);

                        if (!$number) {
                            continue;
                        }

                        $balance =
                            $this->http->FindSingleNode('.//p[@class = "listing@rewards__points"]', $card, false, "/Rewards? (?:balance|points?)\s*(.+)/")
                            ?? $this->http->FindSingleNode('.//p[contains(@class, "summary_container__rewardpoint")]/text()[1]', $card, false, "/(.+)\s+Rewards? (?:balance|points?)/")
                            ?? $this->http->FindSingleNode('.//span[contains(@class, "__balance")]', $card)
                            ?? $this->http->FindSingleNode('.//p[contains(text(), "Reward")]', $card, false, "/:\s*(.+)/")
                        ;
                        $this->logger->debug("[Balance]: {$balance}");

                        if ($balance === null) {
                            continue;
                        }

                        $subAccount = [
                            "Code"        => $detectedCard['Code'],
                            "DisplayName" => $detectedCard['DisplayName'],
                            "Balance"     => $balance,
                        ];

                        if (strstr($balance, '$') || strstr($detectedCard['DisplayName'], 'Cash')) {
                            $subAccount['Balance'] = str_replace('$', '', $subAccount['Balance']);
                            $subAccount['Currency'] = 'cash';
                        }

                        $this->AddSubAccount($subAccount, true);
                        // Detected cards
                        $this->AddDetectedCard([
                            "Code"            => $subAccount['Code'],
                            "DisplayName"     => $subAccount['DisplayName'],
                            "CardDescription" => C_CARD_DESC_ACTIVE,
                        ], true);
                    }// foreach ($cards as $card)

                    if (!empty($this->Properties['SubAccounts'])) {
                        $this->SetBalanceNA();
                    }

                    $headers = [
                        "Accept"          => "*/*",
                        "aftokenvalue"    => $this->aftokenvalue,
                        "routingKey"      => "",
                        "authorization"   => "Bearer {$this->accesstoken}",
                        "Service-Version" => 2,
                        "Content-Type"    => "application/json",
                    ];
                    $this->logger->debug("history start dates: " . json_encode($this->historyStartDates));
                    $usbank = $this->getUsbank();
                    $cookies = $this->driver->manage()->getCookies();
                    $this->logger->debug("set cookies");

                    foreach ($cookies as $cookie) {
                        if ($cookie['name'] == 'FN') {
                            // Name
                            $this->SetProperty("Name", beautifulName($cookie['value']));
                        }

                        $usbank->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                    }

                    // AccountID: 6154867
                    if (
                        $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                        && !empty($this->Properties['Name'])
                        && empty($this->Properties['DetectedCards'])
                        && empty($this->Properties['SubAccounts'])
                        && $this->http->FindSingleNode('//span[contains(text(), "Help is just a step away. Use one of the methods below or go to our")]')
                    ) {
                        $this->SetBalanceNA();

                        return;
                    } elseif (
                        // AccountID: 3244111, 6087145
                        $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                        && !empty($this->Properties['Name'])
                        && !empty($this->Properties['DetectedCards'])
                        && empty($this->Properties['SubAccounts'])
                        && $this->http->FindNodes('//span[contains(text(), "View rewards")]')
                    ) {
                        $this->http->GetURL("https://onlinebanking.usbank.com/USB/{$this->aftokenvalue}/RewardsCenterDashboard/RewardsCenterDashboard.aspx");
                        $this->parseRewardsCenter();

                        return;
                    }

                    // FICO
                    $this->logger->info('FICO® Score', ['Header' => 3]);
                    $usbank->http->PostURL("https://api.usbank.com/customer-management/graphql/v1", '{"query":"query {\n  getCreditScoreService {\n\t\tscoreDifference\n    valCurr\n    isEnrolledForCS\n    enrollmentStatus\n    isDropped\n    scoreTrendPoints {\n      score\n      scoreDate\n    }\n  }\n}\n"}', $headers);
                    $ficoInfo = $usbank->http->JsonLog();

                    if (
                        isset($ficoInfo->data->getCreditScoreService->isEnrolledForCS, $ficoInfo->data->getCreditScoreService->enrollmentStatus)
                        && $ficoInfo->data->getCreditScoreService->isEnrolledForCS == true
                        && $ficoInfo->data->getCreditScoreService->enrollmentStatus == 'Enrolled'
                    ) {
                        $fcioUpdatedOn = null;

                        foreach ($ficoInfo->data->getCreditScoreService->scoreTrendPoints as $scoreTrendPoint) {
                            if (!isset($fcioUpdatedOn) || strtotime($fcioUpdatedOn) < strtotime($scoreTrendPoint->scoreDate)) {
                                $fcioUpdatedOn = $scoreTrendPoint->scoreDate;
                            }
                        }

                        if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1) {
                            foreach ($this->Properties['SubAccounts'][0] as $key => $value) {
                                if (in_array($key, ['Code', 'DisplayName'])) {
                                    continue;
                                } elseif ($key == 'Balance') {
                                    $this->SetBalance($value);
                                } elseif ($key == 'ExpirationDate') {
                                    $this->SetExpirationDate($value);
                                } else {
                                    $this->SetProperty($key, $value);
                                }
                            }// foreach ($this->Properties['SubAccounts'][0] as $key => $value)
                            unset($this->Properties['SubAccounts']);
                        }// if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1)
                        $this->SetProperty("CombineSubAccounts", false);
                        $this->AddSubAccount([
                            "Code"               => "usbankFICO",
                            "DisplayName"        => "VantageScore® 3.0 (TransUnion)",
                            "Balance"            => $ficoInfo->data->getCreditScoreService->valCurr,
                            "FICOScoreUpdatedOn" => $fcioUpdatedOn,
                        ]);

                        return;
                    }
                }// if ($cards->length)

                if ($message = $this->http->FindSingleNode('//p[contains(text(), "An error occured while displaying accounts.")]')) {
                    $this->SetWarning($message);

                    return;
                }

                /*
                if (
                    count($this->http->FindNodes('//div[@aria-labelledby="loanandleases"]/div[@role="listitem"]')) > 0
                    || count($this->http->FindNodes('//div[@aria-labelledby="deposits"]/div[@role="listitem"]')) > 0
                    || count($this->http->FindNodes('//div[@aria-labelledby="checking-and-savings"]/div[@role="listitem"]')) > 0//AccountID: 3995501
                ) {
                    $this->SetBalanceNA();

                    return;
                }
                */

                if ($message = $this->http->FindSingleNode('//p[contains(text(), "No accounts to display")]')) {
                    $this->logger->notice("{$message}");
                    $this->SetBalanceNA();

                    return;
                }

                return;
            }

            $myRewards->click();
        } catch (NoSuchDriverException | NoSuchWindowException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        $this->parseRewardsCenter();
    }

    /** @return TAccountCheckerUsbank */
    protected function getUsbank()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->usBank)) {
            $this->usBank = new TAccountCheckerUsbank();
            $this->usBank->http = new HttpBrowser("none", new CurlDriver());
            $this->usBank->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->usBank->http);
            $this->usBank->AccountFields = $this->AccountFields;
            $this->usBank->HistoryStartDate = $this->HistoryStartDate;
            $this->usBank->historyStartDates = $this->historyStartDates;
            $this->usBank->http->LogHeaders = $this->http->LogHeaders;
            $this->usBank->ParseIts = $this->ParseIts;
            $this->usBank->ParsePastIts = $this->ParsePastIts;
            $this->usBank->WantHistory = $this->WantHistory;
            $this->usBank->WantFiles = $this->WantFiles;
            $this->usBank->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->usBank->http->setDefaultHeader($header, $value);
            }

            $this->usBank->globalLogger = $this->globalLogger;
            $this->usBank->logger = $this->logger;
            $this->usBank->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        return $this->usBank;
    }

    private function parseRewardsCenter()
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Rewards Center")]'), 5);
            $this->saveResponse();
            // https://onlinebanking.usbank.com/USB/af(LYbS4UXwlF2FwBwxowv3)/RewardsCenterDashboard/RewardsCenterDashboard.aspx
            try {
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            } catch (UnknownServerException $e) {
                $this->logger->error("exception: " . $e->getMessage());

                throw new CheckRetryNeededException(3, 0);
            }
            $url = str_replace('/RewardsCenterDashboard/RewardsCenterDashboard.aspx', '/CustomerDashboard/Index', $this->http->currentUrl());
            $this->logger->debug("[URL]: {$url}");

            try {
                $this->http->GetURL($url);
                $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "My Accounts")]'), 5);
                $this->saveResponse();
            } catch (UnknownServerException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "My Accounts")]'), 5);
                $this->saveResponse();
            }
        } catch (NoSuchDriverException | NoSuchWindowException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        try {
            $this->baseURL = $this->driver->executeScript("return sessionStorage.getItem('ReturnURL');");
            $this->logger->info("[Form ReturnURL]: " . $this->baseURL);
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        $this->logger->debug("history start dates: " . json_encode($this->historyStartDates));
        $usbank = $this->getUsbank();
        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $usbank->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $usbank->http->RetryCount = 0;
        $usbank->http->GetURL($this->http->currentUrl(), [], 40);
        $usbank->http->RetryCount = 2;

        $usbank->Parse();
        $this->SetBalance($usbank->Balance);
        $this->Properties = array_merge($this->Properties, $usbank->Properties);
        $this->ErrorCode = $usbank->ErrorCode;

        // refs #22297
        if (!empty($this->Properties['DetectedCards'])) {
            $this->http->GetURL("https://onlinebanking.usbank.com/digital/servicing/enhanced-customer-dashboard");
            $myAccounts = $this->waitForElement(WebDriverBy::xpath('//*[self::a or self::button][contains(text(), "My accounts")]'), 10);
            $this->saveResponse();

            foreach ($this->Properties['DetectedCards'] as $detectedCard) {
                if ($detectedCard['CardDescription'] != C_CARD_DESC_DO_NOT_EARN) {
                    continue;
                }

                $number = explode(' - ', $detectedCard['DisplayName'])[1] ?? null;
                $this->logger->debug("[Card number]: {$number}");

                if (!$number) {
                    continue;
                }

                $balance = $this->http->FindSingleNode('//div[@class = "listing" and .//span[@id = "acc-' . $number . '"]]//p[@class = "listing@rewards__points"]', null, false, "/Reward balance\s*(.+)/");
                $this->logger->debug("[Balance]: {$balance}");

                if (!isset($balance)) {
                    continue;
                }

                $subAccount = [
                    "Code"        => $detectedCard['Code'],
                    "DisplayName" => $detectedCard['DisplayName'],
                    "Balance"     => $balance,
                ];

                if (strstr($balance, '$') || strstr($detectedCard['DisplayName'], 'Cash')) {
                    $subAccount['Currency'] = 'cash';
                }

                $this->AddSubAccount($subAccount, true);
                // Detected cards
                $this->AddDetectedCard([
                    "Code"            => $subAccount['Code'],
                    "DisplayName"     => $subAccount['DisplayName'],
                    "CardDescription" => C_CARD_DESC_ACTIVE,
                ], true);
            }// foreach ($this->Properties['DetectedCards'] as $detectedCard)
        }// if (!empty($this->Properties['DetectedCards']))

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorMessage = $usbank->ErrorMessage;
            $this->DebugInfo = $usbank->DebugInfo;
        }
    }

    private function waitSignoutButton(): bool
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        // selenium bug workaround // AccountID: 5659892
        if ($this->http->FindSingleNode('//input[@id = "btnRedirectNew"]/@value')) {
//            $this->logger->notice("force form submit");
//            $this->driver->executeScript('$("#form1").submit();');
//            $this->waitSomeUI();
//            $this->saveResponse();
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $result = $this->http->FindSingleNode('
            //a[@data-text = "Log out"]
            | //button[contains(text(), "Log out")]
        ');

        $notAtThisTime = $this->waitForElement(WebDriverBy::xpath('//button[
            contains(text(), "No, not at this time")
            or contains(text(), "Ask me later")
            or contains(text(), "Got it")
        ]'), 0);

        if ($notAtThisTime) {
            $this->logger->debug("Close popup");
            $notAtThisTime->click();
            sleep(2);
        }

        if ($this->skipSettingUpdate()) {
            $result = $this->waitForElement(WebDriverBy::xpath('
                //a[@data-text = "Log out"]
                | //button[contains(text(), "Log out")]
            '), 10);
            $this->saveResponse();
        }

        return $result !== null;
    }

    private function closePopups($timeout = 0)
    {
        $this->logger->notice(__METHOD__);

        $notAtThisTime = $this->waitForElement(WebDriverBy::xpath('//button[
            contains(text(), "No, not at this time")
            or contains(text(), "Ask me later")
            or contains(text(), "Got it")
        ]'), $timeout);

        if ($notAtThisTime) {
            try {
                $notAtThisTime->click();
            } catch (Facebook\WebDriver\Exception\ElementNotInteractableException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            }
            sleep(2);
        }

        $this->saveResponse();
    }

    private function skipSettingUpdate()
    {
        $this->logger->notice(__METHOD__);

        if ($remindMeLater = $this->waitForElement(WebDriverBy::xpath('
                //button[contains(text(), "Remind me later")]
                | //button[contains(text(), "Maybe later")]
                | //button[contains(text(), "No thanks")]
                | //div[@id = "pendo-guide-container"]//button[@aria-label="Close"]
                | //div[@role = "alertdialog"]//button[@aria-label="Close"]
            '), 0)
        ) {
            try {
                if ($closePopupBtn = $this->waitForElement(WebDriverBy::xpath('//div[@id = "pendo-base"]//button[@aria-label="Close"]'), 0)) {
                    $closePopupBtn->click();
                    sleep(1);
                    $this->saveResponse();
                }

                try {
                    $remindMeLater->click();
                } catch (Facebook\WebDriver\Exception\StaleElementReferenceException | StaleElementReferenceException $e) {
                    $this->logger->error("StaleElementReferenceException: " . htmlspecialchars($e->getMessage()), ['pre' => true]);
                }
            } catch (Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
                $this->logger->error("Exception: " . htmlspecialchars($e->getMessage()), ['pre' => true]);
                $this->saveResponse();
            }

            return true;
        }

        return false;
    }

    private function waitSomeUI()
    {
        $this->logger->notice(__METHOD__);
        $result = $this->waitForElement(WebDriverBy::xpath('
            //a[@data-text = "Log out"]
            | //div[@id = "shield-input"]//label[@id]
            | //p[contains(@class, "enter-passcode-help-text")]
            | //h2[contains(text(), "Welcome to your new dashboard")]
            | //h2[@id = "top-error-msg-single-notification--text"]
            | //div[@id = "top-error-msg-children-notification"]
            | //div[@role="alert"]/span
            | //p[contains(text(), "Please log in at usbankfocus.com to access and manage your Focus Card account online.")]
            | //div[contains(text(), "Choose the mobile number you’d like us to send your passcode to.")]
            | //p[contains(text(), "Please confirm the following is your primary email address.")]
            | //h1[contains(text(), "We\'re missing something important.")]
            | //h1[contains(text(), "Accept U.S. Bank terms and agreements.")]
            | //h1[contains(text(), "Reset your username and password to log in.")]
            | ' . self::XPATH_TWO_FA_HEADERS
        ), 20);
        $this->saveResponse();

        return $result;
    }
}
