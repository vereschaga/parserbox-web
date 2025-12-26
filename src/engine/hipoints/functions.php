<?php

class TAccountCheckerHipoints extends TAccountChecker
{
    use SeleniumCheckerHelper;
    /**
     * @var HttpBrowser
     */
    public $browser;

    public $regionOptions = [
        ""        => "Select your country",
        "Germany" => "Germany",
        "USA"     => "USA",
    ];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function UpdateGetRedirectParams(&$arg)
    {
        $redirectURL = "https://www.harrispollonline.com/#login";

        if ($this->AccountFields['Login2'] == 'Germany') {
            $redirectURL = "https://survey1.hi-epanel.com/index.php?pageID=login";
        }
        $arg["RedirectURL"] = $redirectURL;
    }

    /*
    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }
    */

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if ($this->AccountFields['Login2'] != 'Germany') {
            $this->UseSelenium();
            $this->useChromium();
            $this->http->saveScreenshots = true;
        }// if ($this->AccountFields['Login2'] != 'Germany')
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->logger->notice("Region => {$this->AccountFields['Login2']}");

        if ($this->AccountFields['Login2'] == 'Germany') {
            throw new CheckException("Nach vielen Jahren treuer Dienste geben wir schweren Herzens die Schließung unseres HI-epanels am 15.04.2023 bekannt.", ACCOUNT_PROVIDER_ERROR);
            $this->http->GetURL("https://survey1.hi-epanel.com/index.php?languageID=1");

            if (!$this->http->ParseForm("loginBoxForm")) {
                return $this->checkErrors();
            }
            $this->http->FormContentType = null;
            $this->http->SetInputValue('user', $this->AccountFields['Login']);
            $this->http->SetInputValue('pass', $this->AccountFields['Pass']);
            $this->http->SetInputValue('command', "doLogin");
        } else {
            $this->http->GetURL("https://www.harrispollonline.com/#login");
            $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "UserID"]'), 10);
            $pass = $this->waitForElement(WebDriverBy::xpath('//input[@name = "Pwd"]'), 0);
            $signIn = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Sign In")]'), 0);

            if (!$login || !$pass || !$signIn) {
                $this->logger->error("something went wrong");
                $this->saveResponse();

                return $this->checkErrors();
            }
            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);
            $this->saveResponse();
            $signIn->click();
            // Additional Security
            $elem = $this->waitForElement(WebDriverBy::xpath("//div[contains(@id, 'component-')]"), 5);
            $captchaInput = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'captchaInput']"), 0);
            $validate = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Validate")]'), 0);

            if ($elem && $captchaInput && $validate) {
                $this->saveResponse();
                $captcha = $this->parseCaptcha($elem);

                if ($captcha === false) {
                    return false;
                }
                $captchaInput->sendKeys($captcha);
                $validate->click();
                // Invalid captcha
                if ($this->waitForElement(WebDriverBy::xpath('//label[contains(text(), "Invalid captcha")]'), 3)) {
                    $this->recognizer->reportIncorrectlySolvedCAPTCHA();

                    throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
                }// if ($this->waitForElement(WebDriverBy::xpath('//label[contains(text(), "Invalid captcha")]'), 3))
                $this->saveResponse();
            }// if ($elem && $captchaInput)
            else {
                $captchaInput = $this->waitForElement(WebDriverBy::xpath("//iframe[contains(@src, 'recaptcha')]"), 5);

                if ($elem && $captchaInput && $validate) {
                    $this->saveResponse();
//                    // captcha
//                    $this->driver->executeScript("
//                        var jq = document.createElement('script');
//                        jq.src = \"https://ajax.googleapis.com/ajax/libs/jquery/1.8/jquery.js\";
//                        document.getElementsByTagName('head')[0].appendChild(jq);
//                    ");
//                    $captcha = $this->parseReCaptcha($captchaInput->getAttribute('src'));
//                    if ($captcha === false)
//                        return false;
//                    $this->logger->notice("Remove iframe");
//                    $this->driver->executeScript("$('div iframe[src *= \"recaptcha\"]').remove();");
//                    $this->driver->executeScript("$('#g-recaptcha-response-1').val('".$captcha."');");
//                    $validate->click();
//                    // Invalid captcha
//                    if ($this->waitForElement(WebDriverBy::xpath('//label[contains(text(), "Invalid captcha")]'), 3)) {
//                        $this->recognizer->reportIncorrectlySolvedCAPTCHA();
//                        throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
//                    }// if ($this->waitForElement(WebDriverBy::xpath('//label[contains(text(), "Invalid captcha")]'), 3))
//                    $this->saveResponse();

//                    $this->driver->switchTo()->frame($captchaInput);
//                    $recaptchaAnchor = $this->waitForElement(WebDriverBy::id("recaptcha-anchor"), 20);
//                    if (!$recaptchaAnchor) {
//                        $this->logger->error('Failed to find reCaptcha "I am not a robot" button');
//                        throw new CheckRetryNeededException(3, 7);
//                    }// if (!$recaptchaAnchor)
//                    $recaptchaAnchor->click();
//                    $this->logger->notice("wait captcha iframe");
//                    $this->driver->switchTo()->defaultContent();
//                    $iframe2 = $this->waitForElement(WebDriverBy::xpath("(//iframe[@title = 'recaptcha challenge'])[last()]"), 10, true);
//                    $this->saveResponse();
//
//                    if ($iframe2) {
//                        $status = '';
//                        if (!$status) {
//                            $this->logger->error('Failed to pass captcha');
//                            throw new CheckRetryNeededException(3, 2, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
//                        }// if (!$status)
//                    }// if ($iframe2)

//                    $captcha = $this->parseReCaptcha($captchaInput->getAttribute('src'));
//                    if ($captcha === false)
//                        return false;
//                    $this->browser = $this->http;
//                    // use curl
//                    $this->parseWithCurl();
//                    $headers = [
//                        "Accept" => "*/*",
//                        "Content-Type" => "application/x-www-form-urlencoded; application/json",
//                        "X-Requested-With" => "XMLHttpRequest",
//                    ];
//                    $data = [
//                        "response" => $captcha,
//                        "secret" => "6Ld9ryEUAAAAAP3MpV9ObgZql3fy_MNXACYalk03"
//                    ];
//                    $this->browser->PostURL("https://www.harrispollonline.com/recaptcha/api/siteverify", $data, $headers);
//                    $this->browser->JsonLog();
//
//                    // get cookies from curl
//                    $allCookies = array_merge($this->http->GetCookies("www.harrispollonline.com"), $this->http->GetCookies("www.harrispollonline.com", "/", true));
//                    $allCookies = array_merge($allCookies, $this->http->GetCookies(".harrispollonline.com"), $this->http->GetCookies(".harrispollonline.com", "/", true));
//
//                    foreach ($allCookies as $key => $value) {
//                        $this->http->setCookie($key, $value);
//                        $this->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".harrispollonline.com"]);
//                    }
//
//                    $this->http->GetURL($this->http->currentUrl());

                    sleep(5);
                    $validate->click();
                    sleep(5);
                    $this->saveResponse();

                    $this->http->GetURL("https://www.harrispollonline.com/#login");
                    $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "UserID"]'), 10);
                    $pass = $this->waitForElement(WebDriverBy::xpath('//input[@name = "Pwd"]'), 0);
                    $signIn = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Sign In")]'), 0);

                    if (!$login || !$pass || !$signIn) {
                        $this->logger->error("something went wrong");
                        $this->saveResponse();

                        return $this->checkErrors();
                    }
                    $login->sendKeys($this->AccountFields['Login']);
                    $pass->sendKeys($this->AccountFields['Pass']);
                    $this->saveResponse();
                    $signIn->click();
                }// if ($elem && $captchaInput)
            }
        }

        return true;
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

        $this->curl = true;

        $this->browser->LogHeaders = true;
        $this->browser->setProxyParams($this->http->getProxyParams());
//        $this->browser->GetURL($this->http->currentUrl());
    }

    public function checkErrors()
    {
        if ($this->AccountFields['Login2'] != 'Germany') {
            //# Maintenance
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'is currently undergoing a minor upgrade to improve performance')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // The Harris Panel member's website is currently undergoing maintenance.
            if ($message = $this->http->FindSingleNode('//h2[contains(text(), "The Harris Panel member\'s website is currently undergoing maintenance.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            //# Site is currently Unavailable
            if ($message = $this->http->FindSingleNode("//h1[contains(text(),  'This site is currently Unavailable')]")) {
                throw new CheckException("The site is currently unavailable. Please check back later", ACCOUNT_PROVIDER_ERROR);
            } /*checked*/
            //# The page cannot be displayed because an internal server error has occurred.
            if ($message = $this->http->FindPreg("/The page cannot be displayed because an internal server error has occurred\./ims")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // We are currently upgrading our Rewards site to bring you new and exciting opportunities. Please check back on Wednesday
            if ($message = $this->http->FindPreg("/We are currently upgrading our Rewards site to bring you new and exciting opportunities\.\s*Please check back[^<]/ims")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // We’re updating our rewards site for a new and improved user experience.
            if ($message = $this->http->FindPreg("/We\&rsquo;re updating our rewards site for a new and improved user experience\./ims")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // We are in the process of implementing certain upgrades to this website.
            if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'We are in the process of implementing certain upgrades to this website.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // The site is currently unavailable.
            if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'The site is currently unavailable.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

//            $this->http->GetURL("https://www.harrispollonline.com/");
            // The Harris Panel members website is currently undergoing maintenance.
            if ($message = $this->http->FindSingleNode("//h2[contains(., 'The Harris Panel members website is currently undergoing maintenance.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Server Error
            if ($this->http->FindSingleNode("//div[contains(text(), 'Server Error')]")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->AccountFields['Login2'] != 'Germany')
        else {
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == "Question") {
            return $this->processSecurityQuestion();
        }

        return false;
    }

    public function Login()
    {
        if ($this->AccountFields['Login2'] == 'Germany') {
            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }

            if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
                return true;
            }
            // Invalid credentials
            if ($message = $this->http->FindSingleNode("//div[@class = 'box_error_login']")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        } else {
            $round = 0;

            do {
                $this->logger->notice("Round #{$round}");

                if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'SET-UP')]"), 5)) {
                    $this->throwProfileUpdateMessageException();
                }
                $this->saveResponse();
                // Invalid credentials
                // We've noticed you are signing in with an email address. If you have updated your user ID already, please use UserID for login.
                if ($message = $this->waitForElement(WebDriverBy::xpath('
                        //label[contains(text(), "User ID and password do not match")]
                        | //label[contains(text(), "Invalid input data")]
                        | //label[contains(text(), "Unable to access account")]
                        | //label[contains(text(), "We\'ve noticed you are signing in with an email address.")]                
                    '), 0)
                ) {
                    throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
                }
                // Unable to process your request
                if ($message = $this->waitForElement(WebDriverBy::xpath("//label[contains(text(), 'Unable to process your request')]"), 0)) {
                    throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
                }

                //            if($this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Your session has expired and')]"), 0))
                //                throw new CheckException('Your session has expired and you have been logged out', ACCOUNT_PROVIDER_ERROR);

                if ($this->waitForElement(WebDriverBy::xpath("//div[label/span[contains(text(), 'Answer:')]]/preceding-sibling::label[contains(text(), '?')]"), 0)) {
                    return $this->processSecurityQuestion();
                }

                $balance = $this->waitForElement(WebDriverBy::xpath("//label[h3[contains(text(), 'Your HIpoints Balance')]]/following-sibling::label[1]/div"), 0);
                $this->saveResponse();

                if ($balance) {
                    return true;
                }

                $round++;
            } while (
                $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Please wait...')]"), 0)
                && $round < 3
            );

            /*##
            if ($message = $this->http->FindSingleNode("//div[@class='msg_panel error']"))
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            if ($message = $this->http->FindSingleNode("//span[contains(@id, 'Login_lblError')]"))
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Login Failed')]"))
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);

            // Incorrect Security Code
            if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Incorrect Security Code')]")) {
                $this->recognizer->reportIncorrectlySolvedCAPTCHA();
                throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
            }

            // We are sorry but we do not have a rewards center for you.
            if ($message = $this->http->FindPreg("/We are sorry but we do not have a rewards center for you\.\s*Please contact the help desk so that we can adjust your account settings\./ims"))
                throw new CheckException(CleanXMLValue($message), ACCOUNT_PROVIDER_ERROR);

            if ($url = $this->http->FindPreg('/window\.location\.replace\(\"([^\"]+)/ims'))
                $this->http->GetURL($url);

            // for maintenance
            $rewardsLink = $this->http->FindSingleNode("//a[span[contains(text(), 'Rewards')]]/@href");

            if (!$this->http->GetURL("https://www.harrisrewards.com/en-US/RewardsHome.aspx") && $rewardsLink)
                $this->http->GetURL($rewardsLink);
            if ($url = $this->http->FindPreg('/window\.location\.replace\(\"([^\"]+)/ims'))
                $this->http->GetURL($url);

            if ($this->http->FindPreg("/Logout/ims"))
                return true;

            ## Site requires reset username and password
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our new member site requires that we reset your username and password')]"))
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);*/
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->AccountFields['Login2'] == 'Germany') {
            // Name
            $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//strong[text() = "Hallo" or text() = "Hello"]/parent::p[1]/text()[2]')));
            // Balance - Ihr Kontostand
            $this->SetBalance($this->http->FindSingleNode("//div[@class = 'account_balance']", null, true, self::BALANCE_REGEXP));
        }// if ($this->AccountFields['Login2'] == 'Germany')
        else {
            if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'SET-UP')]"), 0)) {
                $this->throwProfileUpdateMessageException();
            }

            // Name
            $name = $this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Welcome')]"), 5);

            if ($name) {
                $this->SetProperty("Name", beautifulName($this->http->FindPreg("/Welcome\s*([^\!]+)/", false, $name->getText())));
            }
            // Balance - HIpoints balance
            $balance = $this->waitForElement(WebDriverBy::xpath("//label[h3[contains(text(), 'Your HIpoints Balance')]]/following-sibling::label[1]/div"), 5);

            if ($balance) {
                $this->SetBalance($balance->getText());
            }
            // Last Activity
            $lastActivityDate = $this->http->FindSingleNode("(//div[@id = 'panel-1108_header' and //div[contains(text(), 'HIpoints Summary')]]/following-sibling::div[contains(@class, 'body') and contains(@class, 'panel')]//table//tr[td]/td[1])[1]");

            if (isset($lastActivityDate)) {
                $tms = strtotime($lastActivityDate);

                if (!empty($tms)) {
                    $this->SetProperty("LastActivity", $lastActivityDate);
                    $this->SetExpirationDate(strtotime('+1 year', $tms));
                }// if (!empty($tms))
            }// if (isset($lastActivityDate))
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        if ($this->AccountFields['Login2'] != 'Germany') {
            $arg['SuccessURL'] = 'https://www.harrisrewards.com/en-us/home.aspx';
        }

        return $arg;
    }

    protected function parseReCaptcha($src)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("src: {$src}");
        $key = $this->http->FindPreg("/k=([^\&\"]+)/", false, $src);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
    }

    protected function parseCaptcha($elem)
    {
        $this->logger->debug("take screenshot");
        $file = $this->takeScreenshotOfElement($elem);
        $this->logger->debug('Path to captcha screenshot ' . $file);
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file, ["regsense" => 1]);
        unlink($file);

        return $captcha;
    }

    protected function processSecurityQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $questionObject = $this->waitForElement(WebDriverBy::xpath("//div[label/span[contains(text(), 'Answer:')]]/preceding-sibling::label[contains(text(), '?')]"), 0);
        $this->saveResponse();

        if (!$questionObject) {
            return false;
        }

        $question = trim($questionObject->getText());
        $this->logger->debug("Question -> {$question}");

        if (empty($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question);

            return false;
        }

        $this->logger->debug("Entering answer on question -> {$question}...");
        $answerInput = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'answer']"), 0);
        $validate = $this->waitForElement(WebDriverBy::xpath("//a[contains(@id, 'button-109') and contains(., 'Validate')]"), 0);

        if (!empty($question) && $answerInput && $validate) {
            $answerInput->sendKeys($this->Answers[$question]);
            $this->saveResponse();
            $this->logger->debug("click 'Submit'...");
            $validate->click();
            $this->logger->debug("find errors...");
//            $error = $this->waitForElement(WebDriverBy::xpath("//ul[@class = 'redAlert']/li/span"), 10);// todo: fake selector
            $this->waitForElement(WebDriverBy::xpath("//label[h3[contains(text(), 'Your HIpoints Balance')]]/following-sibling::label[1]/div"), 10);
            $this->logger->debug("[Current URL]: " . $this->http->currentUrl());

            $error = $this->waitForElement(WebDriverBy::xpath("(//label[normalize-space(text()) = 'Invalid data input'])[1]"), 5);

            if (!$error) {
                $error = $this->waitForElement(WebDriverBy::xpath("//div[not(contains(@class, 'x-hidden-offsets'))]/div/div/div/div/label[normalize-space(text()) = 'Invalid data input']"), 0);
            }
            $this->saveResponse();

            if ($error) {
                $this->holdSession();
                $this->AskQuestion($question, $error->getText(), "Question");
                $this->logger->error("answer was wrong");

                return false;
            }

            /*if (!empty($error)) {
                $error = $error->getText();
                $this->logger->error("error: ".$error);
                $this->logger->notice("removing question: ".$question);
                unset($this->Answers[$question]);
                $this->holdSession();
                $this->AskQuestion($question, $error/*, "Question"*);
                return false;
            }*/
            $this->logger->debug("done");

            return true;
        }// if (!empty($questions))

        return false;
    }
}
