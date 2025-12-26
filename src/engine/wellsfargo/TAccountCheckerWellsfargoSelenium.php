<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerWellsfargoSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const XPATH_SECURITY_QUESTIONS = '
                //h1[
                    contains(text(), "Please confirm your identity")
                    or contains(text(), "Please Confirm Your Identity")
                    or contains(text(), "Verify Your Identity")
                ]
                | //h2[span[
                        contains(text(), "Verify Your Identity")
                        or contains(text(), "Let\'s make sure it\'s you")
                        or contains(text(), "For your security, let\'s make sure it\'s you")
                        or contains(text(), "Para su seguridad, confirmemos su identidad")
                    ]
                ]
                | //span[contains(text(), "Help us confirm your")]
            ';
    /** @var HttpBrowser */
    public $browser;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->setProxyBrightData();
        /*
         * todo: 46:~$ curl https://connect.secure.wellsfargo.com/auth/login/rewards
         * curl: (35) Unknown SSL protocol error in connection to connect.secure.wellsfargo.com:443
         *
         * Vladimir: это что то про протоколы, похоже, в принципе коннект проходита, Прокси берет на себя https, надо фиксить
         */
        $this->useFirefoxPlaywright();

        if ($this->AccountFields['Login'] == 'dpatel2019') {
            $this->http->SetProxy($this->proxyStaticIpDOP());
        }
//        $this->useCache();
        $this->http->saveScreenshots = true;
//        $this->disableImages();
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->browser->LogHeaders = true;
        $this->browser->setProxyParams($this->http->getProxyParams());

        $this->logger->debug("[Curl URL]: " . $this->http->currentUrl());
        $this->logger->debug("[Selenium URL]: " . $this->getWebDriver()->getCurrentURL());

        return;

        $this->browser->GetURL($this->http->currentUrl());
    }

    public function LoadLoginForm()
    {
        // AccointID: 7073358
        if (strlen($this->AccountFields['Login']) < 3) {
            throw new CheckException("We do not recognize your username and/or password. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->driver->manage()->window()->setSize(new WebDriverDimension(1280, 900));
        $this->http->GetURL("https://www.wellsfargo.com/");
        /*
        $this->http->GetURL("https://gofarrewards.wf.com/#/Welcome");
        $signOn = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Sign On')]"), 5);
        $this->saveResponse();
        if ($signOn) {
            $signOn->click();
        }
        else
            $this->http->GetURL("https://connect.secure.wellsfargo.com/auth/login/rewards");
        */

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "j_username"]'), 15, false);
        $this->saveResponse();
        /*
        if (!$this->http->ParseForm("Signon") || !$login)
        */
        if (!$this->http->ParseForm("frmSignon") || !$login) {
            return $this->checkErrors();
        }
        /*
        $login->sendKeys($this->AccountFields['Login']);
        $this->waitForElement(WebDriverBy::xpath('//input[@name = "j_password"]'), 0, true)->sendKeys($this->AccountFields['Pass']);
        */
        $this->driver->executeScript("
            document.getElementById('userid').value = '{$this->AccountFields['Login']}';
            document.getElementById('password').value = '" . addcslashes($this->AccountFields['Pass'], "'\\") . "';
        ");
        $continueButton = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'continue' or @name = 'btnSignon']"), 5);
        $this->saveResponse();

        if (!$continueButton) {
            $this->logger->error('Failed to find continue button');

            return false;
        }

        try {
            $this->logger->debug('Click "Sign On" button');
            $continueButton->click();
            $this->driver->switchTo()->alert()->accept();
            sleep(3);
            $this->saveResponse();
        }
//        catch (UnexpectedAlertOpenException $e) {
//            $this->handleSecurityException($e);
//        }
        catch (WebDriverException $e) {
            $this->logger->error('[WebDriverException]: ' . $e->getMessage(), ['pre' => true]);
        }
        catch (NoAlertOpenException $e) {
            $this->logger->error("no alert, skip");
        }

        return true;
    }

    public function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
//        $url = $this->waitForElement(WebDriverBy::xpath("//img[@id = 'nucaptcha-media']"), 0);
        $url = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'media-container']"), 0);

        if (!$url) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        /*
        $this->logger->debug("Captcha URL -> ".$url->getAttribute('src'));
        $captcha = $this->recognizeCaptchaByURL($this->recognizer, $url->getAttribute('src'), "gif");
        */
        $pathToScreenshot = $this->takeScreenshotOfElement($url);
        $captcha = $this->recognizeCaptcha($this->recognizer, $pathToScreenshot);
        unlink($pathToScreenshot);

        return $captcha;
    }

    public function Login()
    {
        $res = $this->waitResult();

        if ($res || $this->ErrorCode === ACCOUNT_QUESTION) {
            return $res;
        }

        // it helps
        if (
            strstr($this->http->currentUrl(), 'https://connect.secure.wellsfargo.com/accounts/start?p1=yes&SAMLart=')
            || strstr($this->http->currentUrl(), 'https://connect.secure.wellsfargo.com/accounts/start?SAMLart=')
        ) {
            $this->http->GetURL("https://connect.secure.wellsfargo.com/accounts/start");
            $logout = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Sign Off")]'), 10);
            $this->saveResponse();

            if ($logout) {
                return true;
            }
        }

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "j_username"]'), 0);
        $pass = $this->waitForElement(WebDriverBy::xpath('//input[@name = "j_password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[@data-testid = 'signon-button']"), 0);

        if ($login && $pass && $button) {
            $this->saveResponse();
            $login->clear();
            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);

            $captcha = $this->parseCaptcha();

            if ($captcha !== false) {
                $this->logger->debug("Input captcha");
                $captchaInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'nucaptcha-answer']"), 0);

                if ($captchaInput) {
                    $captchaInput->sendKeys($captcha);
                } else {
                    $this->logger->error("captcha input not found");
                }
            }

            $this->saveResponse();

            $button->click();

            return $this->waitResult();
        }

        return $this->checkErrors();
    }

    public function waitResult()
    {
        $this->logger->notice(__METHOD__);

        $sleep = 20;
        $startTime = time();

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
            $logout = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Sign Off")]'), 0);
            $this->saveResponse();

            if ($logout) {
                return true;
            }

            // skip reminder/offer
            if ($remindMeLater = $this->waitForElement(WebDriverBy::xpath('text()
                //div[@id = "splashBackground"]//input[@name = "Considering" and (contains(@value, "Remind Me Later") or contains(@value, "Continue To Online Banking"))]
                | //div[@id = "online_wellsfargo_com_splash"]//input[@value = "Remind Me Later" or @value = "Remind me later"]
                | //*[contains(text(), "Remind Me Later")]
                | //*[contains(text(), "Remind me later")]
                | //a[contains(text(), "Continue to Account Summary Page")]
                | //a[contains(text(), "Continue to online banking")]
                | //button[contains(., "Change it later")]
                | //button[contains(., "Continue to online banking")]
            '), 0)) {
                $this->logger->debug("skip reminder/offer");
                $remindMeLater->click();
            }

            if (
                ($remindMeLater = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Try another method") or contains(., "Intente con otro método")]'), 0))
                && $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Push notice and delivery agreement")]'), 0)
            ) {
                $this->logger->debug("skip reminder/offer");
                $remindMeLater->click();
                sleep(1);
            }

            // security questions
            if ($this->parseQuestion()) {
                return false;
            }

            // Action Required: You have the option to update your delivery preferences to online only for the account(s) below.
            if ($this->waitForElement(WebDriverBy::xpath('
                    //div[@class="marquee-title" and contains(text(), "Action Required")]
                    | //span[contains(text(), "Time to strengthen your password")]
                    | //h1[contains(@class, "page-title") and contains(text(), "Action Required: Change Password")]
                    | //h2[contains(text(), "You have the option to update your delivery preferences to online only for the account(s) below.")]
                    | //p[contains(text(), "You’ll need to create a new password to access your accounts.")]
                    | //h1[div[span[contains(text(), "Let\'s change your username and password")]]]
                    | //h3[contains(., "Benefits of digitally delivered statements:")]
                '), 0)
            ) {
                $this->throwProfileUpdateMessageException();
            }
            // Online access is currently unavailable.
            if ($message = $this->http->FindSingleNode("
                    //h1[contains(text(), 'Online access is currently unavailable')]
                    | //p[contains(text(), 'Please try again later. Wells Fargo Online is temporarily unavailable.')]
                    | //h1[contains(text(), 'We have temporarily prevented online access to your account')]
                ")
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // We do not recognize your username and/or password.
            if ($message = $this->http->FindSingleNode('//*[self::strong or self::div][contains(text(), "We do not recognize your username and/or password.")]')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->checkCredentials();

            // We've temporarily suspended your online access because the maximum number of unsuccessful sign on attempts has been reached.
            if ($message = $this->http->FindPreg("/<strong>(We've temporarily suspended your online access because the maximum number of unsuccessful sign on attempts has been reached\.)/ims")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $logout = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Sign Off")]'), 0);
            $this->saveResponse();

            if ($logout) {
                return true;
            }

            if ($message =
                $this->http->FindSingleNode('//div[contains(text(), "We\'ve temporarily suspended your online access because the maximum number of unsuccessful sign on attempts has been reached.")]')
                ?? $this->http->FindPreg("/<p>(We've temporarily blocked online access to protect your accounts\.)<\/p>/")
                ?? $this->http->FindPreg("/div class=\"ErrorMessage__errorMessageText___[^\"]+\">(We've temporarily suspended your online access because the maximum number of unsuccessful sign on attempts has been reached\.) To restore your access, please/")
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->saveResponse();
        }

        return false;
    }

    public function checkCredentials()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "ErrorMessage__errorMessageText___")]/p')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'That combination doesn\'t match our records. ')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return true;
    }

    public function ProcessStep($step)
    {
        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        switch ($step) {
            case "SecurityCheckpoint":
                return $this->parseQuestion();

                break;

            case "AdvancedAccessCode":
                return $this->advancedAccessCode($this->Question);

                break;
        }

        return false;
    }

    public function advancedAccessCode($question)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Entering Advanced Access Code");
        // notifications
//        $this->sendNotification("wellsfargo - Entering Advanced Access Code", 'awardwallet');
        $input = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'indict_code' or @id = 'passcode' or @id = 'otp']"), 0);
        $this->saveResponse();

        if (!$input) {
            $this->logger->error('Failed to find input field for "answer"');

            return false;
        }
        $input->clear();
        $input->sendKeys($this->Answers[$question]);
        // do not keep Advanced Access Code
        unset($this->Answers[$this->Question]);
        $this->logger->notice("Submit form");
        $submitButton = $this->waitForElement(WebDriverBy::xpath('
            //button[@id = "indict_submit" or @id = "submitcode" or contains(., "Submit") or contains(., "Continúe")]
            | //form[@id = "command"]//input[@name = "submit"]
            | //form[@id = "validate-code-form"]//button[@type = "submit"]
            | //button[@type = "submit" and span[contains(text(), "Submit") or contains(., "Continúe")]]
            | //form[@id = "auth-enter-code"]//button[@type = "submit"]
        '), 0);
        $this->saveResponse();

        if (!$submitButton) {
            $this->logger->error('Failed to find submit button');

            return false;
        }
        $submitButton->click();
        // Invalid code. Please try again.
        $error = $this->waitForElement(WebDriverBy::xpath("//strong[contains(text(), 'Invalid code. Please try again.')]"), 5, true);

        if ($error) {
            $this->logger->debug("Ask question. Wrong Code.");
            $input->clear();
            $this->holdSession();
            $this->AskQuestion($question, $error->getText(), "AdvancedAccessCode");

            return false;
        }

        if ($this->Login()) {
            $this->Parse();
        }

        return false;
    }

    public function Parse()
    {
        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");

        $brokenAccounts = [
            'foxc304cam', // AccountID: 5524940
            'adindemasi', // AccountID: 4289855
            'jthyman', // AccountID: 5537042
            'IAGodwin', // AccountID: 5598462
            'annanpoust', // AccountID: 5708230
        ];

        $rewardsXpath = '//span[
            contains(text(), "GO FAR REWARD")
            or contains(text(), "Go Far Reward")
            or contains(text(), "WF REWARD")
            or contains(text(), "Rewards")
            or contains(text(), "WELLS FARGO CASH WISE VISA SIGNATURE® CARD")
            or contains(text(), "WELLS FARGO REWARDS")
            or contains(text(), "WELLS FARGO BUSINESS REWARDS")
            or contains(text(), "YoYo Spare CC Reward")
        ]
            | //a[contains(@class, "account-title") and contains(@class, "use-rewards")]
            | //span[@data-testid="Cash Back-balance"]
            | //span[contains(@data-testid, "RSB Debit ")]
        ';

        try {
            $rewardsPage = $this->waitForElement(WebDriverBy::xpath($rewardsXpath), 0);
            /*
            $this->logger->debug("hide popup");
            $this->driver->executeScript('jQuery(\'.__acs\').hide()');
            */
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage());
            sleep(3);
            $rewardsPage = $this->waitForElement(WebDriverBy::xpath($rewardsXpath), 0);
        }

        // Open portfolio after sign in // AccountID: 5425004
        if (
            !$rewardsPage
            && $this->waitForElement(WebDriverBy::xpath('//li[contains(@class, "portfolio active")]'), 0)
            && ($profile = $this->waitForElement(WebDriverBy::xpath('//a[@id = "goacctsummary"]'), 0))
        ) {
            $profile->click();
            sleep(3);
            $rewardsPage = $this->waitForElement(WebDriverBy::xpath($rewardsXpath), 0);
        }

        // Open personal profile instead business etc // AccountID: 4421666
        if (
            !$rewardsPage
            && ($selectAccount = $this->waitForElement(WebDriverBy::xpath('//button[contains(@data-testid, "view-selector-button")]'), 0))
        ) {
            $selectAccount->click();
            sleep(3);
            $this->saveResponse();

            if ($personalAccount =
                $this->waitForElement(WebDriverBy::xpath('//li[button[contains(text(), "Personal Account") and not(contains(text(), "Business"))]]'), 0)
                ?? $this->waitForElement(WebDriverBy::xpath('//li[button[contains(., "(Default)")]]'), 0)
            ) {
                $personalAccount->click();
                sleep(3);
                $this->saveResponse();
            }

            $rewardsPage = $this->waitForElement(WebDriverBy::xpath($rewardsXpath), 0);
        }
        $this->saveResponse();

        // Name
        $this->SetProperty("Name", beautifulName(
            $this->http->FindSingleNode("//span[@class = 'name']")
            ?? $this->http->FindSingleNode("//span[contains(text(), 'Welcome, ')]", null, true, "/Welcome,\s*([^!.]+)/")
        ));

        if ($rewardsPage) {
            $this->saveResponse();
            $this->logger->debug("click");

            try {
                $rewardsPage->click();
            } catch (StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException: " . $e->getMessage());
                sleep(1);
                $rewardsPage = $this->waitForElement(WebDriverBy::xpath($rewardsXpath), 0);
                $this->saveResponse();
                $rewardsPage->click();
            }
            $this->logger->debug("wait auth");
            $resultXpath = "
                //p[contains(@class, 'name')]
                | //a[contains(text(), 'Cash rewards details')]
                | //button[contains(text(), 'Get Code')]
                | //span[contains(@class, 'points')]
                | //h1[@ng-bind-html = 'vm.title' and contains(., 'website is temporarily unavailable')]
            ";

            try {
                $res = $this->waitForElement(WebDriverBy::xpath($resultXpath), 10);
                $this->saveResponse();
            } catch (StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException: " . $e->getMessage());
                sleep(1);
                $res = $this->waitForElement(WebDriverBy::xpath($resultXpath), 10);
                $this->saveResponse();
            }

            if ($getCode = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Get Code')]"), 0)) {
                $getCode->click();

                if ($this->parseQuestion()) {
                    return false;
                }
            } elseif ($this->http->FindSingleNode(self::XPATH_SECURITY_QUESTIONS)) {
                if ($this->parseQuestion()) {
                    return false;
                }
            }

            if (!$res && ($rewardsPageEstimated = $this->http->FindNodes("
                //p[contains(@class, 'name')]
                | //a[contains(text(), 'Cash rewards details')]
                | //span[contains(@class, 'points')]
            "))) {
                $this->logger->notice("success");
            }

            // provider bug fix
            if (!$res && !isset($rewardsPageEstimated) && ($rewardsPage = $this->waitForElement(WebDriverBy::xpath($rewardsXpath), 0))) {
                try {
                    $this->logger->notice("provider bug fix");
                    $this->http->GetURL($currentUrl);
                } catch (TimeOutException $e) {
                    $this->logger->error("TimeOutException exception: " . $e->getMessage());
                    $this->driver->executeScript('window.stop();');
                    $this->saveResponse();
                }
                sleep(1);
                $rewardsPage = $this->waitForElement(WebDriverBy::xpath($rewardsXpath), 5);
                $extSession = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Are you sure you want to sign off?")]/following-sibling::footer//button[contains(text(), "No")]'), 0);

                if ($extSession) {
                    $this->saveResponse();
                    $extSession->click();
                    sleep(1);
                }
                $this->saveResponse();

                if ($rewardsPage) {
                    $rewardsPage->click();
                    $this->logger->debug("wait auth");
                    $this->waitForElement(WebDriverBy::xpath("//p[contains(@class, 'name')]| //a[contains(text(), 'Cash rewards details')]"), 10);
                    $this->saveResponse();
                }
            }

            // The Go Far® Rewards website is temporarily unavailable. We apologize for this inconvenience. Please try again later.
            if ($message = $this->http->FindSingleNode("//h1[@ng-bind-html = 'vm.title' and contains(., 'website is temporarily unavailable')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($cashCard = $this->waitForElement(WebDriverBy::xpath("//*[self::a or self::div][contains(text(), 'Cash rewards details')]"), 0)) {
                $cashCard->click();
                $this->waitForElement(WebDriverBy::xpath("//p[contains(@class, 'name')]"), 10);

                $this->saveResponse();

                if ($getCode = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Get Code')]"), 0)) {
                    $getCode->click();

                    if ($this->parseQuestion()) {
                        return false;
                    }
                } elseif ($this->http->FindSingleNode(self::XPATH_SECURITY_QUESTIONS)) {
                    if ($this->parseQuestion()) {
                        return false;
                    }
                }
            }

            $this->saveResponse();
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id = 'gfr-sub-nav']//p[contains(@class, 'name')]")));

            // AccountID: 5524940
            if (
                (
                    in_array($this->AccountFields['Login'], $brokenAccounts)
                    || $this->http->FindSingleNode('//div[contains(text(), "For your security, we have temporarily locked Advanced Access for your account.")]')
                )
                && empty($this->Properties['Name'])
            ) {
                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[contains(text(), "Welcome, ")]', null, true, "/Welcome, (.+)/")));
                // Balance - GO FAR REWARDS
                $this->SetBalance($this->http->FindSingleNode('//span[@data-testid = "GO FAR REWARDS-balance" or @data-testid = "WELLS FARGO REWARDS-balance"]'));
            }
        } elseif (($this->waitForElement(WebDriverBy::xpath("//a[contains(@class, 'account-title')]/i[
                    contains(@class, 'checking')
                    or contains(@class, 'brokerage')
                    or contains(@class, 'credit')
                    or contains(@class, 'student-loan')
                    or contains(@class, 'mortgage')
                ]
                | //li[@data-testid = 'AUTO LOAN']
                | //li[@data-testid = 'BROKERAGE']
                | //li[@data-testid = 'BROKERAGE IRA']
                | //li[@data-testid = 'EVERYDAY CHECKING']
                | //li[@data-testid = 'CHECKING']
                | //li[@data-testid = 'PREFERRED CHECKING']
                | //li[@data-testid = 'BUSINESS CHECKING']
                | //li[@data-testid = 'MORTGAGE']
                | //li[@data-testid = 'WELLS FARGO CLEAR ACCESS BANKING']
                | //li[@data-testid = 'WELLS FARGO BUSINESS SECURED CREDIT CARD']
                | //li[@data-testid = 'WELLS FARGO ACTIVE CASH CARD']
                | //li[@data-testid = 'PLATINUM CARD']
                | //li[@data-testid = 'BUSINESS CARD']
                | //li[@data-testid = 'HOTELS.COM REWARDS VISA® SIGNATURE']
                | //li[@data-testid = 'WAY2SAVE® SAVINGS']
                | //li[@data-testid = 'BILT WORLD ELITE MASTERCARD®']
                | //li[@data-testid = 'WELLS FARGO AUTOGRAPH VISA® CARD']
                | //li[@data-testid = 'Europadian Visa']
                | //li[@data-testid = 'HOTELS.COM REWARDS VISA® PLATINUM']
                | //li[@data-testid = 'PRIME CHECKING']
                | //li[@data-testid = 'WELLS FARGO ACTIVE CASH VISA® CARD']
                | //li[normalize-space(@data-testid) = 'HOME FURNISHINGS']
                | //li[normalize-space(@data-testid) = 'HOME PROJECTS']
                | //li[normalize-space(@data-testid) = 'WF Biz Plat CC']
                | //li[normalize-space(@data-testid) = 'CHOICE PRIVILEGES® MASTERCARD®']
                | //li[normalize-space(@data-testid) = 'CHOICE PRIVILEGES® SELECT MASTERCARD®']
                | //li[normalize-space(@data-testid) = 'Checking Small']
                | //li[normalize-space(@data-testid) = 'Monthly Bills-title']
                | //span[contains(text(), 'WF Checking')]
                | //span[contains(text(), 'WAY2SAVE® CHECKING')]
                | //span[contains(text(), 'BUSINESS PLATINUM SAVINGS')]
                | //span[contains(text(), 'WELLS FARGO REFLECT VISA')]
                | //span[contains(text(), 'WF HEALTH ADVANTAGE')]
                | //p[contains(text(), 'Note: This account is Closed, but you can still make online payments to pay down the balance.')]
                | //p[contains(text(), 'This account is dormant. Call us at 1-800-869-3557 to reactivate or close your account.')]
            "), 0)
                ?? $this->waitForElement(WebDriverBy::xpath("//a[contains(@class, 'account-title')]/i[contains(@class, 'brokerage')]"), 0, false)
            )
            && $this->http->FindSingleNode('
                //div[contains(@class, "page-title-content")]/h1[@aria-hidden="true" and contains(text(), "Account Summary")]
                | //div[@data-page-content]//h1[span[contains(text(), "Account Summary")]]
            ')
        ) {
            /*
            } elseif (in_array($this->AccountFields['Login'], [// hard code
                'saamtalebi',
                'sdrummer1',
                'vlnunez',
                'mightyyoshi12',
                'birdboy01',
                'EliHarrison21',
                'jjgreeley',
                'coddus1',
            ])) {
            */
            $this->SetBalanceNA();
        } elseif (in_array($this->AccountFields['Login'], [// hard code
            'dianelibman', // only platinum card
        ])) {
            $this->SetBalanceNA();
        }

        $this->saveResponse();
        $this->parseWithCurl();

//        $this->browser->PostURL("https://gofarrewards.wf.com/Home/GetRans?n=" . $this->random(), []);
//        $this->browser->PostURL("https://consumercard.wellsfargorewards.com/Home/GetProfile?n=" . $this->random(), []);
        $domain = 'consumercard';

        if ($this->getWebDriver()->getCurrentURL() == 'https://businesscard.wellsfargorewards.com/#/WelcomeAuthenticated') {
            $domain = 'businesscard';
        }

        $this->driver->executeScript("
            if (window.jQuery)
            $.ajax({
                url: 'https://{$domain}.wellsfargorewards.com/Home/GetRans?n={$this->random()}',
                type: 'POST',
                data: JSON.stringify({}),
                dataType: 'json',
                success: function (data) {
                    console.log('response: ' + JSON.stringify(data));
                    localStorage.setItem('response', JSON.stringify(data));
                },
                error: function (data, textStatus, error) {
                    console.log('Error response: ' + JSON.stringify(error));
                    localStorage.setItem('responseError', JSON.stringify(error));
                    console.log('Error response: ' + JSON.stringify(data));
                    localStorage.setItem('responseData', JSON.stringify(data));
                }
            });
        ");
        sleep(5);

        try {
            $response = $this->driver->executeScript("return localStorage.getItem('response');");
        } catch (NoScriptResultException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        }
        $this->logger->info("[Form response]: " . $response);
        $responseError = $this->driver->executeScript("return localStorage.getItem('responseError');");
        $this->logger->info("[Form responseError]: " . $responseError);
        $responseCaptchaData = $this->driver->executeScript("return localStorage.getItem('responseData');");
        $this->logger->info("[Form responseCaptchaData]: " . $responseCaptchaData);
        $this->logger->debug(var_export($responseCaptchaData, true), ["pre" => true]);
        $this->browser->SetBody($response, false);

        $response = $this->browser->JsonLog();
        // We’re sorry. Our system is temporarily unavailable
        if ($this->browser->FindPreg("/\"Success\":false/ims")
            && $this->browser->FindPreg("/Exception when calling a Web API: System.AggregateException: One or more errors occurred\./ims")) {
            throw new CheckException("Our system is currently unavailable, and we apologize for any inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }
        // if curl parsing failed
        if ($this->browser->FindPreg("/\"Success\":false/ims")
            && /*(*/ $this->browser->FindPreg("/User is not logged in, please log-in first\./ims")
//                || $this->browser->FindPreg("/Requested information cannot be found\. Related Rewards accounts cannot be found for the given CustomerKey/ims"))
            && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->parseSelenium();

            return;
        }

        $this->logger->debug("parse json");

        if (isset($response->Rans[0]->AccountNumber)) {
            $this->logger->debug("parse all cards");

            foreach ($response->Rans as $ran) {
                $account = [];
                // Rewards ID
                if (isset($ran->AccountNumber)) {
                    $account["RewardsID"] = $ran->AccountNumber;
                } else {
                    $this->logger->debug(">>> RewardsID not found");
                }
                // CurrencyType
                if (isset($ran->CurrencyType)) {
                    $account["CurrencyType"] = $ran->CurrencyType;
                } else {
                    $this->logger->debug(">>> CurrencyType not found");
                }
                // Current Balance
                if (isset($ran->CurrentBalance)) {
                    $account["Balance"] = $ran->CurrentBalance;

                    if (isset($account["CurrencyType"]) && strtolower($account["CurrencyType"]) == 'cash') {
                        $account["Balance"] = $account["Balance"] / 100;
                    }
                } else {
                    $this->logger->debug(">>> CurrentBalance not found");
                }
                // DisplayName, Code
                if (isset($ran->EarningMechanisms[0]->AccountNumber, $ran->EarningMechanisms[0]->Description)) {
                    $account["DisplayName"] = $ran->EarningMechanisms[0]->Description
                        . " " . $ran->EarningMechanisms[0]->AccountNumber;

                    if ($code = $this->http->FindPreg("/(\d{4})/ims", false, $account["DisplayName"])) {
                        $account["Code"] = "wellsfargo" . $code;
                    }
                    // Account Number
                    $account["AccountNumber"] = $ran->EarningMechanisms[0]->AccountNumber;
                } // AccountID: 1594469
//                elseif (count($response->Rans) == 1 && isset($ran->AccountNumber)) {
//                    $account["DisplayName"] = "Wells Fargo Rewards";
//                    $account["Code"] = "wellsfargoMain" . $ran->AccountNumber;
//                }
                // AccountID: 2680379
                // card name not exist for this account, only Rewards ID
                elseif ((!isset($account["DisplayName"]) || $account["DisplayName"] == 'Go Far Rewards Available Balance')
                    && isset($account["RewardsID"], $account["Balance"])) {
                    $account["DisplayName"] = 'Rewards ID ' . $account["RewardsID"];
                    $account["Code"] = "wellsfargo" . $account["RewardsID"];
                }
                // Base Rewards
                if (isset($ran->BasePoints)) {
                    $account["BaseRewards"] = $ran->BasePoints;
                } else {
                    $this->logger->debug(">>> BasePoints not found");
                }
                // Bonus Rewards
                if (isset($ran->BonusPoints)) {
                    $account["BonusRewards"] = $ran->BonusPoints;
                } else {
                    $this->logger->debug(">>> BonusPoints not found");
                }
                // Earn More Mall Rewards
                if (isset($ran->EmmPoints)) {
                    $account["EarnMoreMallRewards"] = $ran->EmmPoints;
                } else {
                    $this->logger->debug(">>> EmmPoints not found");
                }
                // Rewards adjusted to date
                if (isset($ran->AdjustmentsToDate)) {
                    $account["RewardsAdjustedToDate"] = $ran->AdjustmentsToDate;
                } else {
                    $this->logger->debug(">>> AdjustmentsToDate not found");
                }
                // Rewards Pending
                if (isset($ran->PendingPoints)) {
                    $account["RewardsPending"] = $ran->PendingPoints;
                } else {
                    $this->logger->debug(">>> PendingPoints not found");
                }

                $this->logger->debug(var_export($account, true), ["pre" => true]);

                if (isset($account["DisplayName"], $account["Code"], $account["Balance"])) {
                    $subAccounts[] = $account;
                }
            }// foreach ($response->Rans as $ran)

            // Set SubAccounts
            if (isset($subAccounts) && count($subAccounts) > 0) {
                $this->SetProperty("SubAccounts", $subAccounts);
                $this->SetBalanceNA();
            }// if (isset($subAccounts) && count($subAccounts) > 0)

//            if (isset($subAccounts) && count($subAccounts) > 2) {
//                $this->sendNotification("wellsfargo - refs #12045. SubAccounts > 2", 'awardwallet');
//                $this->ArchiveLogs = true;
//            }

            // this not working anymore
            /*
            $this->browser->PostURL("https://gofarrewards.wf.com/Home/GetConfigurations?n=".$this->random(), array());
            $this->browser->JsonLog();
//            $this->browser->PostURL("https://gofarrewards.wf.com/Home/GetConfigurations?n=".$this->random(), array());
//            $this->browser->JsonLog();
//            $this->browser->PostURL("https://gofarrewards.wf.com/Home/GetSonar?n=".$this->random(), array());
//            $this->browser->JsonLog();
//            $this->browser->GetURL("https://gofarrewards.wf.com/angularRoot/modules/selectRan/selectRanModal.html");
//            $this->browser->PostURL("https://gofarrewards.wf.com/Home/GetProfile?n=".$this->random(), array());
//            $this->browser->JsonLog();
            $this->browser->PostURL("https://gofarrewards.wf.com/Home/GetSonar?n=".$this->random(), array());
//            $this->browser->JsonLog();

            $headers = [
                "Accept" => "application/json, text/plain, *
            /*",
                "Content-Type" => "application/json;charset=utf-8"
            ];
            $data = [
                "accountNumber" => $response->Rans[0]->AccountNumber
            ];
            $this->browser->PostURL("https://gofarrewards.wf.com/Home/SetRan?n=".$this->random(), json_encode($data), $headers);
            $this->browser->JsonLog();
            $this->browser->PostURL("https://gofarrewards.wf.com/Home/GetConfigurations?n=".$this->random(), array());
            $this->browser->JsonLog();
//            $this->browser->GetURL("https://gofarrewards.wf.com/angularRoot/modules/welcome/welcome.html");
            $this->browser->PostURL("https://gofarrewards.wf.com/Home/GetProfile?n=".$this->random(), array());
            $this->browser->JsonLog();

            // Get full json
            $this->browser->PostURL("https://gofarrewards.wf.com/Home/GetProfile?n=".$this->random(), array());
            $response = $this->browser->JsonLog();
            if (isset($response->Profile))
                $this->logger->debug(var_export($response->Profile, true), ['pre' => true]);
            // Name
            if (isset($response->Profile->FirstName, $response->Profile->LastName))
                $this->SetProperty("Name", beautifulName($response->Profile->FirstName . " " . $response->Profile->LastName));
            else
                $this->logger->notice(">>> Name not found");
            */
        } // if (isset($response->Rans[0]->AccountNumber))
        elseif ($this->browser->FindPreg("/\"Success\":true/ims") && empty($response->Rans)
            && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            throw new CheckException("Our system is currently unavailable, and we apologize for any inconvenience.", ACCOUNT_PROVIDER_ERROR);
        } else { // Maintenance
            $this->checkErrors();
        }

        $this->logger->info('FICO® Score', ['Header' => 3]);

        try {
            $this->http->GetURL($currentUrl);
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException: {$e->getMessage()}");
            $this->driver->executeScript('window.stop();');
        }
        sleep(3);
        $fico = $this->waitForElement(WebDriverBy::xpath('//*[self::a or self::button][contains(., "View your FICO")]'), 2);
        $this->saveResponse();

        if (!$fico) {
            return;
        }
        $this->driver->executeScript('
            try {
                $(\'div.__acs\').hide();
                $(\'div.mwf-modal-overlay\').hide();
                $(\'div.mwf-modal\').hide();
                var overlay = document.querySelector(\'[data-testid="overlay"]\');
                if (overlay && typeof (overlay[0]) != \'undefined\') {
                    overlay[0].style.display = "none";
                }
            } catch ($e) {
            }
        ');
        $this->saveResponse();

        if (in_array($this->AccountFields['Login'], $brokenAccounts)) {
            $this->driver->executeScript('
                document.querySelector(\'[data-testid="fico-link"]\').click();
            ');
        } else {
            $fico->click();
        }

        $this->waitForElement(WebDriverBy::xpath('//div[@class = "bureau-version"]/span/span[2] | //p[contains(text(), "In order to obtain your FICO")] | //span[@data-testid="fico-score-ver"]'), 10);
        $this->waitForElement(WebDriverBy::xpath('//div[@class = "score-meter-text-score"]'), 0);
        $this->saveResponse();
        $this->increaseTimeLimit();
        // FICO® Score 9 (Experian)
        $fcioScore =
            $this->http->FindSingleNode('//div[@class = "score-meter-text-score"]')
            ?? $this->http->FindPreg('/"score":"(\d+)"/')
        ;
        // FICO Score updated on
        $fcioUpdatedOn =
            $this->http->FindSingleNode('//div[@class = "score-meter-text-score-date"]', null, true, "/as\s*of\s*(.+)/")
            ?? $this->http->FindSingleNode('//span[normalize-space(text()) = "Updated"]/following-sibling::span[1]')
        ;

        if (!$fcioScore || !$fcioUpdatedOn) {
            return;
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
            "Code"               => "wellsfargoFICO",
            "DisplayName"        => $this->http->FindSingleNode('//div[@class = "bureau-version"]/span/span[2] | //span[@data-testid="fico-score-ver"]/following-sibling::div[1]/span') . " (" . str_replace(' Data', '', $this->http->FindSingleNode('//div[@class = "bureau-version"]/span/span[4] | //span[@data-testid="fico-score-ver"]')) . ")",
            "Balance"            => $fcioScore,
            // As of
            "FICOScoreUpdatedOn" => $fcioUpdatedOn,
        ]);
    }

    public function parseSelenium()
    {
        $this->logger->notice(__METHOD__);
        // Rewards ID
        $account["RewardsID"] = $this->http->FindSingleNode("//span[contains(text(), 'Rewards ID')]", null, true, "/ID\s*x([\d]+)/");
        // Balance
        $account["Balance"] = $this->http->FindSingleNode("//div[@id = 'gfr-sub-nav']//span[contains(@class, 'points')]");
        // CurrencyType
        if (strstr($account["Balance"], '$')) {
            $account["CurrencyType"] = 'CASH';
        }
        // DisplayName
        $displayName = $this->http->FindSingleNode("(//p[@ng-hide = 'em.IsSuppressedCreditCard' and not(contains(@class, 'ng-hide'))])[1]");

        if ($displayName) {
            $account["DisplayName"] = $displayName;
            // Code
            if (preg_match("/(\d{4})\s*$/ims", $account["DisplayName"], $matches)) {
                $account["Code"] = "wellsfargo" . $matches[1];
            }
            // Account Number
            unset($matches);

            if (preg_match("/\s(XXXX-.+)/ims", $account["DisplayName"], $matches)) {
                $account["AccountNumber"] = $matches[1];
            }
        } // AccountID: 1594469
        elseif (isset($account["RewardsID"], $account["Balance"])) {
            $account["DisplayName"] = "Wells Fargo Rewards";
            $account["Code"] = "wellsfargoMain" . $account["RewardsID"];
        }

        if (isset($account["DisplayName"], $account["Code"], $account["Balance"])) {
            $subAccounts[] = $account;
        } elseif ($accounts = $this->http->XPath->query("//div[contains(@class, 'form-row-item')]")) {
            $this->http->Log("Total {$accounts->length} cards were found");

            foreach ($accounts as $acc) {
                $account = [];
                // Rewards ID
                $account["RewardsID"] = $this->http->FindSingleNode(".//span[@class = 'id-details']/label", $acc);
                // DisplayName
                $displayName = $this->http->FindSingleNode(".//span[@class = 'id-details']/following-sibling::span[1]/span[1]", $acc);

                if ($displayName) {
                    $account["DisplayName"] = $displayName;
                    // Code
                    if ($code = $this->http->FindPreg("/(\d{4})\s*$/ims", false, $account["DisplayName"])) {
                        $account["Code"] = "wellsfargo" . $code;
                    }
                    // Account Number
                    unset($code);

                    if ($code = $this->http->FindPreg("/\s(XXXX-.+)/ims", false, $account["DisplayName"])) {
                        $account["AccountNumber"] = $code;
                    }
                }
                // Balance
                $account["Balance"] = $this->http->FindSingleNode("span[contains(@class, 'reward-currency')]", $acc);
                // CurrencyType
                if (strstr($account["Balance"], '$')) {
                    $account["CurrencyType"] = 'CASH';
                }

                // card name not exist for this account, only Rewards ID
                elseif ((!isset($account["DisplayName"]) || $account["DisplayName"] == 'Go Far Rewards Available Balance')
                        && isset($account["RewardsID"], $account["Balance"])) {
                    $account["DisplayName"] = 'Rewards ID ' . $account["RewardsID"];
                    $account["Code"] = "wellsfargo" . $account["RewardsID"];
                }

                $this->logger->debug(var_export($account, true), ["pre" => true]);

                if (isset($account["DisplayName"], $account["Code"], $account["Balance"])) {
                    $subAccounts[] = $account;
                }
            }// foreach ($accounts as $acc)

            // Load first account for getting Name
            $firstAccount = $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Select']"), 5, true);

            if (!empty($firstAccount) && $accounts->length > 0) {
                $this->logger->debug("Go to first account");

                try {
                    $firstAccount->click();
                    $button = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'selectRanPopup']//a[@class = 'f-button']"), 5);
                    //					if (!$button)
                    //						$this->logger->error('Failed to find "f-button" link');
//                    else {
//                        $this->http->Log("button click");
//                        $button->click();
                    $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Rewards ID')]"), 15);
//                    }
                    $this->saveResponse();
                } catch (ElementNotVisibleException $e) {
                    $this->logger->error("exception: " . $e->getMessage());
                }
            }
        }// elseif ($accounts = $this->http->FindNodes("//div[@id = 'selectRanPopup']//table[@class = 'table-box']//tr[td]"))

        // Set SubAccounts
        if (isset($subAccounts) && count($subAccounts) > 0) {
            $this->SetProperty("SubAccounts", $subAccounts);
            $this->SetBalanceNA();
        }// if (isset($subAccounts) && count($subAccounts) > 0)

        // Name
        $name = $this->http->FindSingleNode("//span[@class = 'name']");

        if ($name) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    protected function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        // Maintenance
        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "We\'re sorry, the page you are looking for is temporarily unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Online Banking is temporarily unavailable
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Online Banking is temporarily unavailable")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "We\'re sorry. This service is temporarily unavailable.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Site Is Down Due to Scheduled Maintenance
        if ($message = $this->http->FindSingleNode('//h3[contains(., "Site Is Down Due to Scheduled Maintenance")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // website is currently unavailable due to routine scheduled maintenance
        if ($this->http->FindPreg('/(website is currently unavailable due to (?:a temporary outage|routine scheduled maintenance))/ims')) {
            throw new CheckException("The Wells Fargo Rewards® website is currently unavailable due to routine scheduled maintenance. We are sorry for any inconvenience this may have caused. The website will be back up shortly. Please check back soon.", ACCOUNT_PROVIDER_ERROR);
        }

        // The proxy server is refusing connections
        if ($this->http->FindSingleNode("//h1[contains(text(), 'The proxy server is refusing connections')]")) {
            // retries
            throw new CheckRetryNeededException(3, 7);
        }

        return false;
    }

    protected function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[Current URL]: " . $this->http->currentUrl());
        $q = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Security Question')]/following-sibling::div[1]"), 0, true);

        if (!$q) {
            $q =
                $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Your code was sent to')]"), 0, false)
                ?? $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'The code was sent to')]"), 0, false)
            ;
        }
        $this->saveResponse();

        if ($q) {
            $question = trim($q->getText());
            $this->logger->debug("Question -> {$question}");
        }

        if (!isset($question) && $this->http->FindSingleNode(self::XPATH_SECURITY_QUESTIONS)) {
            $this->logger->info('Security questions', ['Header' => 3]);

            $sendEmail = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Send me an email')]"), 0);

            if (
                !$sendEmail
                && ($tryAnotherMethod = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Try another method') or contains(., \"Intente con otro método\")]"), 0))
                && $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'll text you with a one-time code.')] | //span[contains(text(), 'Enter code from your RSA SecurID') or contains(text(), 'Le enviaremos un mensaje de texto para entregarle un código por única vez.')]"), 0)
            ) {
                $this->saveResponse();
                $tryAnotherMethod->click();

                $sendEmail = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Send me an email')]"), 3);
                $this->saveResponse();
            }

            if ($sendEmail || $this->waitForElement(WebDriverBy::xpath("//button[contains(., '@')]"), 0)) {
                if ($sendEmail) {
                    $sendEmail->click();
                }
                // Select Your Email
                $emailBtn = $this->waitForElement(WebDriverBy::xpath("//button[contains(., '@')]"), 5);
                $this->saveResponse();

                if (!$emailBtn) {
                    return false;
                }
                $email = $this->http->FindSingleNode("(//button[contains(., '@')]/span)[1]");
                $emailBtn->click();

                $getCode = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Get Code') or contains(., 'Continue')]"), 5);
                $this->saveResponse();

                if (!$getCode) {
                    return false;
                }

                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }

                $getCode->click();
                // notifications
//                $this->sendNotification("wellsfargo - ask Advanced Access Code", 'awardwallet');
                $this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Submit Code')] | //h2[span[contains(text(), 'Enter code')]]"), 5);
                $this->saveResponse();

                $question = "Please enter Advanced Access Code which was sent to the following email: {$email}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
                $this->logger->debug("Question -> {$question}");
                $this->holdSession();
                $this->AskQuestion($question, null, "AdvancedAccessCode");

                return true;
            } elseif (
                ($textMeCode = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Text me a code')]"), 0))
                || ($phoneBtn = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Mobile') or contains(., 'Móvil')]"), 0))
            ) {
                if ($textMeCode) {
                    $textMeCode->click();
                }
                // Select Your Phone Number
                $phoneBtn =
                    $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Mobile') or contains(., 'Móvil')]"), 5)
                    ?? $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Home') or contains(., 'Casa')]"), 0)
                ;
                $this->saveResponse();

                if (!$phoneBtn) {
                    return false;
                }
                $phone = $this->http->FindSingleNode("((//button[contains(., '*')]/span)[contains(text(), '*')])[1]");
                $phoneBtn->click();

                $getCode = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Get Code')]"), 5);
                $this->saveResponse();

                if ($getCode) {
                    // prevent code spam    // refs #6042
                    if ($this->isBackgroundCheck()) {
                        $this->Cancel();
                    }

                    $getCode->click();
                    // notifications
//                $this->sendNotification("wellsfargo - ask Advanced Access Code", 'awardwallet');
                    $this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Submit Code')]"), 5);
                    $this->saveResponse();
                } elseif (!$this->http->FindSingleNode('//form[@id = "auth-enter-code"]//button[@type = "submit"]/@id')) {
                    return false;
                }

                if (isset($email)) {
                    $question = "Please enter Advanced Access Code which was sent to the following email: {$email}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
                } else {
                    $question = "Please enter Advanced Access Code which was sent to the following phone: {$phone}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
                }
                $this->logger->debug("Question -> {$question}");
                $this->holdSession();
                $this->AskQuestion($question, null, "AdvancedAccessCode");

                return true;
            }

            $q = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'indict_phoneNumber' or @id = 'contactInfo']/div[@select] | //div[contains(text(), '***')]"), 0);
            $getCode = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'indict_getCode' or contains(text(), 'Get Code')]"), 0);

            if ($q && $getCode) {
                $question = $this->http->FindPreg("/([X*]{3}-[X*]{3}-\d+)/", false, $q->getText());

                if ($question) {
                    $question = "Please enter Advanced Access Code which was sent to the following phone number: {$question}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
                } else {
                    return false;
                }
                // remove old Advanced Access Code
                unset($this->Answers[$question]);

                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }

                $getCode->click();
                // notifications
//                $this->sendNotification("wellsfargo - ask Advanced Access Code", 'awardwallet');
                $this->waitForElement(WebDriverBy::xpath("//input[@id = 'otp']"), 5);
                $this->saveResponse();

                $this->logger->debug("Question -> {$question}");
                $this->holdSession();
                $this->AskQuestion($question, null, "AdvancedAccessCode");

                return true;
            }

            return false;
        }

        if (!isset($question)) {
            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->logger->debug("Ask question");
            $this->holdSession();
            $this->AskQuestion($question, null, "SecurityCheckpoint");

            return true;
        } else {
            $this->logger->debug("Entering answer");
            $input = $this->waitForElement(WebDriverBy::xpath('
                //form[@id = "command"]//input[@name = "answer"]
                | //input[@name = "otp"]
            '), 0);

            if (!$input) {
                $this->logger->error('Failed to find input field for "answer"');

                return false;
            }
            $input->clear();
            $input->sendKeys($this->Answers[$question]);

            // remove old Code
            if (strstr($question, 'Your code was sent to')) {
                unset($this->Answers[$question]);
            }

            $this->logger->debug("Submit form");
            $submitButton = $this->waitForElement(WebDriverBy::xpath('
                //form[@id = "command"]//input[@name = "submit"]
                | //form[@id = "validate-code-form"]//button[@type = "submit"]
                | //button[@type = "submit" and span[contains(text(), "Submit")]]
                | //form[@id = "auth-enter-code"]//button[@type = "submit"]
            '), 0);

            if (!$submitButton) {
                $this->logger->error('Failed to find submit button');

                return false;
            }

            $submitButton->click();

            sleep(5);
            $error = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "global-msg-error")]'), 0);

            if ($error && strstr($error->getText(), "Invalid code")) {
//                $input->clear();
                $this->holdSession();
                $this->AskQuestion($question, $error->getText(), "AdvancedAccessCode");

                return false;
            }

            if ($this->Login()) {
                $this->Parse();
            }
        }

        return true;
    }
}
