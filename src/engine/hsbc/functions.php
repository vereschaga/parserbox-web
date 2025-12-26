<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerHsbc extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    private const XPATH_LOGOUT = '
        (//a[contains(@href, "Logoff")]/@href)[1]
        | (//a[contains(@title, "Log off")]/@href)[1]
        | //button[@data-event-name = "log off"]
        | //button[@data-event-name = "logoff"]
    ';

    private const XPATH_ONE_TIME_CODE = '//*[self::p or self::span][
        contains(text(), "A one-time code has been sent to your ")
        or contains(text(), "An activation code has been sent to your ")
        or contains(text(), "A one-time verification code has been sent to your email address")
        or contains(text(), "A code has been sent to your email address")
        or contains(text(), "A one-time verification code has been sent to your")
    ]';

    protected $newAuthorization = false;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency']) && $properties['Currency'] == 'CAD') {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "CA$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    /*
    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""          => "Select your region",
            //            "Canada"    => "Canada", closed on March 28, 2024
            //            "Singapore" => "Singapore", // refs #22666
            //            "UK"     => "United Kingdom",
            "USA"       => "United States",
        ];
    }
    */

    public function UpdateGetRedirectParams(&$arg)
    {
        switch ($this->AccountFields['Login2']) {
            case 'Canada':
                $url = 'https://www.services.online-banking.hsbc.ca/gpib/';

                break;

            case 'UK':
                $url = 'https://www.services.online-banking.hsbc.co.uk/gpib/';

                break;

            case 'Singapore':
                $url = 'https://www.hsbc.com.sg/security/';

                break;

            default:
                $url = "https://www.services.online-banking.us.hsbc.com/gpib/";

                break;
        }
        $arg['RedirectURL'] = $url;
    }

    /*
    function IsLoggedIn()
    {
        if ($this->AccountFields['Login2'] == 'Brasil') {
            return false;
        }

        if ($this->AccountFields['Login2'] == 'Canada') {
            $this->http->GetURL("https://www.services.online-banking.hsbc.ca/gpib/group/gpib/cmn/layouts/default.html?uid=dashboard");
        }
        else {
            $this->http->GetURL("https://www.services.online-banking.us.hsbc.com/gpib/group/gpib/cmn/layouts/default.html?uid=dashboard");
        }

        $success = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGOUT), 0);
        $this->saveResponse();
        if ($success) {
            return true;
        }

        return false;
    }
    */

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (in_array($this->AccountFields['Login2'], [
            'Canada',
            'Brasil',
            'Singapore',
        ])) {
            return;
        }

        $this->UseSelenium();

        if ($this->AccountFields['Login2'] == 'Canada') {
//            $this->useGoogleChrome();
            $this->useChromium();
        } else {
            $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
//            $this->seleniumOptions->addHideSeleniumExtension = false;
//            $this->seleniumOptions->userAgent = null;
        }
        /*
        $this->keepCookies(false);
        */
        $this->http->saveScreenshots = true;

        if ($this->AccountFields['Login2'] == 'UK') {
            $this->http->SetProxy($this->proxyUK()); // https://redmine.awardwallet.com/issues/17790#note-7
        } elseif (in_array($this->AccountFields['Login2'], ['Canada'])) {
            /*
            $this->http->SetProxy($this->proxyReCaptchaIt7());
            */
            $this->setProxyGoProxies();
        } else {
            if ($this->attempt == 0) {
                $this->setProxyGoProxies();
            } elseif ($this->attempt == 1) {
                $this->setProxyMount();
            }
        }

        $this->usePacFile(false);
        //$this->disableImages();
        $this->http->FilterHTML = false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        if ($this->AccountFields['Login2'] == 'Singapore') {
            throw new CheckException('Unfortunately, we currently do not support this region.', ACCOUNT_PROVIDER_ERROR);
        }
        // support for old accounts
        if ($this->AccountFields['Login2'] == 'Brasil') {
            throw new CheckException("Anúncio importante para clientes HSBC Bank Brasil S.A. – Banco Múltiplo e HSBC Serviços e Participações Ltda.<br><br>Em prosseguimento aos anúncios anteriores, o banco comercial e de varejo do HSBC no Brasil - HSBC Bank Brasil S.A. - Banco Múltiplo e HSBC Serviços e Participações Ltda. teve sua venda concretizada no dia 01 de Julho de 2016.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->AccountFields['Login2'] == 'Canada') {
            throw new CheckException("The sale of HSBC Bank Canada and its subsidiaries to Royal Bank of Canada (“RBC”) was completed on March 28, 2024. Further customer guidance on the migration of products and services is available directly from RBC via their website (www.rbc.com/hsbc-canada) or their other contact channels. Thank you to our customers and employees for their support over the years.", ACCOUNT_PROVIDER_ERROR);
        }

        if (strlen($this->AccountFields['Pass']) < 8) {
            throw new CheckException('The password must comprise 8 to 30 alpha-numeric characters', ACCOUNT_INVALID_PASSWORD);
        }

        if (strlen($this->AccountFields['Login']) < 5) {
            throw new CheckException("Your username must be between 5 and 76 characters. It can contain numbers, letters and these characters: @ _ ' . -.", ACCOUNT_INVALID_PASSWORD);
        }

        try {
            /*
            if ($this->AccountFields['Login2'] == 'Singapore') {
                $this->http->GetURL("https://www.hsbc.com.sg/security/");
            } else
            */
            if ($this->AccountFields['Login2'] == 'UK') {
                $this->http->GetURL("https://www.services.online-banking.hsbc.co.uk/gpib/");
            } elseif ($this->AccountFields['Login2'] == 'Canada') {
                $this->http->GetURL("https://personal.hsbc.ca/online/dashboard/");
            } else {
                $this->http->GetURL("https://www.us.hsbc.com/1/2/home/personal-banking");

                try {
//                    $logIn = $this->waitForElement(WebDriverBy::xpath('//div[@aria-label = "Log on options"]'), 0);
//                    if ($logIn) {
//                        $logIn->click();
//                    }
//                    else {
//                    $this->http->GetURL("https://www.services.online-banking.us.hsbc.com/gpib/");
                    $this->http->GetURL("https://www.us.hsbc.com/online/dashboard");
//                    }
                } catch (UnexpectedJavascriptException $e) {
                    $this->logger->error("UnexpectedJavascriptException exception: " . $e->getMessage());
                    $this->DebugInfo = "UnexpectedJavascriptException";
                } catch (ScriptTimeoutException $e) {
                    $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
                    $this->DebugInfo = "ScriptTimeoutException";
                    $this->http->GetURL("https://www.security.us.hsbc.com/gsa/SECURITY_LOGON_PAGE/");
                }
            }

            if ($this->AccountFields['Login2'] == 'UK') {
                $this->driver->executeScript("PC_7_42KKM2620G7D10IDC6FIL03OV2_form_submit()");
            }

            // login
            $loginID = 'username';

            if ($this->AccountFields['Login2'] == 'UK') {
                $loginID = 'Username1';
            }
            $loginInput = $this->waitForElement(WebDriverBy::id($loginID), 10);
            $this->saveResponse();

            if (!$loginInput) {
                return $this->checkErrors();
            }

            try {
                $loginInput->sendKeys($this->AccountFields['Login']);
            } catch (UnrecognizedExceptionException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                $this->saveResponse();
            } catch (Facebook\WebDriver\Exception\ElementNotInteractableException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                $this->saveResponse();

                sleep(10);
                $loginInput = $this->waitForElement(WebDriverBy::id($loginID), 0);

                try {
                    $loginInput->sendKeys($this->AccountFields['Login']);
                } catch (Facebook\WebDriver\Exception\ElementNotInteractableException $e) {
                    $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

                    throw new CheckRetryNeededException(3, 0);
                }

                $this->saveResponse();
            } catch (
                Facebook\WebDriver\Exception\InvalidSessionIdException
                | Facebook\WebDriver\Exception\UnknownErrorException
                $e
            ) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(3, 0);
            }

            $this->injectJQ();

            // Sign In
            sleep(2);

            /*
            if ($this->AccountFields['Login2'] == 'Singapore') {
                $button = $this->waitForElement(WebDriverBy::id('username_submit_btn'), 0);
            } else
            */
            if ($this->AccountFields['Login2'] == 'UK') {
                /*
                $button = $this->waitForElement(WebDriverBy::xpath('//input[@value = "Continue"]'), 0);
                */
                $this->driver->executeScript("$('input[value=\"Continue\"]').click();");
            } else {
                /*
                $button = $this->waitForElement(WebDriverBy::id('//input[@id="formSubmitButton"]'), 0);
                */
                $this->driver->executeScript("$('input#formSubmitButton, #username_submit_btn').click();");
            }
            sleep(3);
            $this->saveResponse();

            /*
            if (!$button) {
                return $this->checkErrors();
            }
            */

            // reCaptcha
            if ($key = $this->http->FindSingleNode("//input[@id = 'formSubmitButton']/@data-sitekey")) {
                $this->logger->notice('reCAPTCHA');
                $captcha = $this->parseReCaptcha($key);

                if ($captcha === false) {
                    return false;
                }

                try {
                    $this->driver->executeScript('onSubmit("' . $captcha . '");');
                } catch (UnexpectedAlertOpenException $e) {
                    $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $this->logger->debug($this->driver->switchTo()->alert()->getText());
                    // captcha problems workaround
                    if (strstr($this->driver->switchTo()->alert()->getText(), 'Cannot contact reCAPTCHA')) {
                        throw new CheckRetryNeededException(3, 0);
                    }
                    $this->driver->switchTo()->alert()->accept();
                    $this->logger->notice("alert, accept");
                }
            }// if ($key = $this->http->FindSingleNode("//input[@id = 'formSubmitButton']/@data-sitekey"))
            else {
                $this->saveResponse();
                $this->logger->debug("delay");
                sleep(2);
                $this->saveResponse();
                //$button->click();
            }
            $this->logger->notice("Sending form with login...");
            $this->saveResponse();

            $this->logger->notice("Waiting form with password...");
            // skip entering your security code
            $this->waitForElement(WebDriverBy::xpath("//label[contains(text(), 'Enter your security code') or contains(text(), 'Enter your Security Code') or contains(text(), 'Please enter your security code')] | //span[contains(text(), 'Security code locked')] | //label[contains(text(), 'Enter your password') or contains(text(), 'Please enter your password')] | //input[@id = 'idPassword']"), 25);
            $this->saveResponse();

            if ($this->waitForElement(WebDriverBy::xpath("//label[contains(text(), 'Enter your security code') or contains(text(), 'Enter your Security Code') or contains(text(), 'Please enter your security code')] | //span[contains(text(), 'Security code locked')]"), 0)) {
                $this->logger->notice("skip entering your security code...");

                if ($closePopup = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Close')]"), 0)) {
                    $closePopup->click();
                    sleep(2);
                    $this->saveResponse();
                }

                $passOption = $this->waitForElement(WebDriverBy::xpath("//a[@id='switch_password']"), 0);
                if (!$passOption) {
                    $passOption = $this->waitForElement(WebDriverBy::xpath("//*[self::span or self::a][contains(text(), 'Log on using password') or contains(text(), 'Continue using password')] | //input[@id = 'OTP_LOCKED_P']"),
                        0);
                }

                if ($passOption) {
                    $passOption->click();
                    /*$this->saveResponse();
                    $this->driver->executeScript('document.getElementById("switch_password").click()');
                    */
                    sleep(5);
                    $this->saveResponse();
                    $passOption = $this->waitForElement(WebDriverBy::xpath("//a[@id='switch_password']"), 0);
                    if ($passOption) {
                        $this->logger->notice("The link to enter the password is not clicked, a bug, but most likely a block");
                        //$passOption->click();
                        //sleep(5);
                        throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG);
                    }
                } else {
                    $this->logger->error("'Log on using password' button not found");
                }
            }// if ($this->waitForElement(WebDriverBy::xpath("//label[contains(text(), 'Enter your security code')]"), 5))
            $this->saveResponse();
            $this->waitForElement(WebDriverBy::xpath("//form[contains(@action, 'gsa/?idv_cmd=idv.Authentication') or @id = 'frm_dobpassword']"), 5);
            // save page to logs
            $this->saveResponse();

            // Your Online Banking session has been terminated. You need to log on again before attempting this process.
            if ($error = $this->http->FindSingleNode('
                    //h2[contains(text(), "Online Banking session terminated")]
                ')
            ) {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }
            // The username you entered does not match our records.
            if ($error = $this->http->FindSingleNode("
                    //div[@id = 'usernameError' and contains(text(), 'The username you entered does not match our records.')]
                    | //div[@id = 'usernameError' and contains(text(), 'The username must be between 5 and 76 characters.')]
                    | //div[@id = 'hsbcwidget_AlertBox_0']//p[contains(text(), 'The details you have provided do not match our records.')]
                    | //div[@class = 'errorText' and contains(., ' Your username was not recognised.')]/text()[last()]
                ")
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
            // Your credentials are suspended
            if ($error = $this->http->FindSingleNode("//div[not(@aria-hidden='true')]/div/h2[contains(text(), 'Your credentials are suspended') or contains(., 'Password locked') or contains(text(), 'Account suspended')]")) {
                throw new CheckException($error, ACCOUNT_LOCKOUT);
            }

            // captcha error

            // The information you entered does not match our records. Please try to log on again.
            if ($error = $this->http->FindSingleNode("//div[@id = 'usernameError' and contains(., 'The information you entered does not match our records')]/text()[last()]")) {
                //throw new CheckRetryNeededException(2, 1/*, self::CAPTCHA_ERROR_MSG*/);
            }

            if (!$this->questionAboutBirthDate()) {
                return false;
            }
            // save page to logs
            $this->saveResponse();

            // Your session has timed out
            if ($error = $this->http->FindSingleNode("//h2[contains(text(), 'Your session has timed out')]")) {
                $this->DebugInfo = $error;
            }
            /*
                throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
            */
            if ($error = $this->http->FindSingleNode('//div[@id = "errorMessage"]', null, true, "/We're having some system issues and are trying to fix them as quickly as possible. Please try again later. Sorry for the inconvenience./")) {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }

            if ($error = $this->http->FindSingleNode('//div[contains(text(), "Your username must be between 5 and 76 characters.")]')) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
            $this->DebugInfo = "UnknownServerException";
            // retries
            throw new CheckRetryNeededException(3, 7);
        }

        if ($this->newAuthorization) {
            return true;
        }

        // Select log on access method
        if ($link = $this->http->FindSingleNode("//a[contains(@href, '/gsa/IDV_CAM10TO30_AUTHENTICATION/?__USER=withOutSecKey')]/@href")) {
            $this->logger->notice("[Select log on access method]: Without Security Device");
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
        }
        // parse login form
        $this->http->Form = $this->parseLoginForm();

        if (empty($this->http->Form)) {
            $this->logger->error("something went wrong");

            if (
                $this->waitForElement(WebDriverBy::xpath('//input[@id = "formSubmitButton" and @class = "gusPrimary submit_input inline_interstitial processing" and @disabled = "disabled"]'), 0)
                /*
                in_array(
                $this->AccountFields['Login'],
                [
                    'mrainone7',
                    'humhu',
                    'pfennig11',
                    'liangderong1992',
                    'ssnnandan',
                    'sweet0me',
                    'vprathap',
                    'minamikato',
                    'sbedelman',
                ])
                */
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkCredentials();
        }
        // Memorable Answer
        if ($this->parseQuestion()) {
            return false;
        }

        return true;
    }

    public function questionAboutBirthDate()
    {
        $this->logger->notice(__METHOD__);
        // new authorization
        if (
            (
                $this->AccountFields['Login2'] == 'Canada'
                || $this->waitForElement(WebDriverBy::xpath('//div[@id = "sliderContentPassword"]/h3[contains(., "Help with your password")]'), 0, false)
                || $this->waitForElement(WebDriverBy::xpath('//input[@id = "idPassword"]'), 0)
            )
            && $this->waitForElement(WebDriverBy::xpath('//label[contains(text(), "Enter your password") or contains(text(), "Please enter your password")]'), 0)
        ) {
            $this->logger->notice("New authorization");
            $this->newAuthorization = true;
            // password
            $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password" or @id = "idPassword"]'), 0);

            if (!$passwordInput) {
                return $this->checkErrors();
            }
            $passwordInput->clear();
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            // Security question
            $question = $this->waitForElement(WebDriverBy::xpath("//*[self::p or self::label][contains(text(), 'Enter your date of birth')] | //p[contains(text(), 'To further protect your account please verify your date of birth')]"), 3);
            $this->saveResponse();

            if ($message = $this->http->FindSingleNode('//div[@class = "errorText"and contains(., "You have entered an invalid character. Please remove the character before continuing.")]/text()[last()]')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($question) {
                $this->logger->notice("Find answer...");
                $sq = "Please enter your date of birth (DD/MM/YYYY)";

                if (!isset($this->Answers[$sq])) {
                    $this->AskQuestion($sq, null, 'BirthDate');

                    return false;
                }// if (!isset($this->Answers[$sq]))
                $this->logger->notice("Entering answer on security question...");
                $answer = explode('/', $this->Answers[$sq]);

                if (count($answer) != 3 || strlen($answer[2]) != 4 || $answer[1] > 12) {
                    $this->AskQuestion($sq, 'Please enter a valid date of birth.', 'BirthDate');

                    return false;
                }// if (count($answer) != 3 || strlen($answer[2]) != 4)
                $dateMonth = $this->waitForElement(WebDriverBy::xpath('//input[@id = "dateMonth" or @id = "dobMonth"]'), 0);
                $dateDay = $this->waitForElement(WebDriverBy::xpath('//input[@id = "dateDay" or @id = "dobDate"]'), 0);
                $dateYear = $this->waitForElement(WebDriverBy::xpath('//input[@id = "dateYear" or @id = "dobYear"]'), 0);

                if (!$dateMonth || !$dateDay || !$dateYear) {
                    $this->logger->error("Birth fields weren't found");

                    return false;
                }// if (!$dateMonth || !$dateDay || !$dateYear)
                $dateDay->clear();
                $dateDay->sendKeys($answer[0]);
                $dateMonth->clear();
                $dateMonth->sendKeys($answer[1]);
                $dateYear->clear();
                $dateYear->sendKeys($answer[2]);
                // Sign In
                sleep(1);
            }// if ($question)
            $button = $this->waitForElement(WebDriverBy::xpath('//input[@id = "formSubmitButton2"] | //button[@id = "loginBtn"]'), 0);

            if (!$button) {
                return $this->checkErrors();
            }

            $button->click();

            $this->logger->debug("Waiting...");
            // skip reminder
            $timeout = 0;

            if ($continueBtn = $this->waitForElement(WebDriverBy::xpath("//a[input[contains(@value, 'Continue to my Personal Internet Banking')]]"), 5)) {
                $timeout = 5;
                $continueBtn->click();
            }
            // Digital Security Device Activation
            if ($this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Security Device Activation')]"), $timeout)) {
                $this->logger->notice("skip 'Security Device Activation'");

                if ($continueBtn = $this->waitForElement(WebDriverBy::xpath('//a[
                        contains(text(), "Don\'t have your mobile device with you?")
                        or contains(text(), "Don\'t have your Security Device?")
                    ]'), 0)
                ) {
                    $continueBtn->click();

                    if ($continueBtn = $this->waitForElement(WebDriverBy::xpath('
                            //a[contains(@class, \'secondaryBtn\') and @onclick= \'SubmitFormForLink1()\']
                            | //input[@value = "Log on without activating"]
                        '), 5)
                    ) {
                        $this->saveResponse();
                        $continueBtn->click();
                    }
                }
            }// if ($this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Digital Security Device Activation')]"), 5))
            $this->saveResponse();
            // The information you entered does not match our records. You have ... attempt(s) left, please try again.
            // Your Validation Code has expired. Please call Customer Care
            if ($error = $this->waitForElement(WebDriverBy::xpath("
                //div[@id = 'passwordError' and contains(text(), 'The information you entered does not match our records.')]
                | //div[@id = 'passwordError' and contains(text(), 'Your password must be between 8 and 30 characters long and can include letters')]
                | //p[contains(., 'Your Validation Code has expired. Please call Customer Care')]
                | //div[@class = 'errorText' and contains(., 'The password you entered does not match. Please try again.')]/text()[last()]
            "), 3)
            ) {
                throw new CheckException($error->getText(), ACCOUNT_INVALID_PASSWORD);
            }

            if ($question && isset($sq)) {
                // The information you entered does not match our records. Please enter your date of birth and password to continue.
                if ($error = $this->waitForElement(WebDriverBy::xpath("//p[@id = 'passwordDosiError' and contains(text(), 'The information you entered does not match our records. Please enter your date of birth and password to continue.')] | //div[contains(text(), 'Date of birth must be in the past')]"), 0)) {
                    $this->holdSession();
                    $this->AskQuestion($sq, $error->getText(), 'BirthDate');

                    return false;
                }
            }
            // Please choose how to receive your one-time code.
            if ($choose = $this->waitForElement(WebDriverBy::xpath('//legend[
                    contains(text(), "Please choose how to receive your one-time code.")
                    or contains(text(), "Please choose how to receive your activation code")
                ]/following-sibling::div[@id = "email_radio"]/input | //label[@for = "TB_EMAIL" or @id = "email_text"]'), 0)
            ) {
                $choose->click();
                sleep(4);
            }

            $continueBtn = $this->waitForElement(WebDriverBy::xpath('//input[@id = "sendSMSPIN"] | //button[@id = "trusted_continue_button"] | //input[@name = "Send verification code"]'), 0);
            $this->saveResponse();

            if ($continueBtn) {
                if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                    $this->Cancel();
                }

                try {
                    $continueBtn->click();
                } catch (UnknownServerException $e) {
                    $this->logger->error("UnknownServerException: " . $e->getMessage());
                }
                $sq = $this->waitForElement(WebDriverBy::xpath(self::XPATH_ONE_TIME_CODE), 10);
                $this->saveResponse();

                if (!$sq) {
                    if ($error = $this->waitForElement(WebDriverBy::xpath('//*[self::p or self::span][
                                contains(text(), "You have now exceeded the number of codes we can send you.")
                                or contains(text(), "Verification is currently unavailable due to too many activation code requests.")
                                or contains(text(), "Too many codes requested")
                            ]
                            | //div[contains(@class, "tb-error-text") and contains(., "verification is not available as you\'ve requested too many activation codes")]
                            | //h2[contains(text(), "You have requested too many codes")]
                        '), 0)
                    ) {
                        throw new CheckException($error->getText(), ACCOUNT_PROVIDER_ERROR);
                    }

                    return false;
                }
                $this->holdSession();

                $this->AskQuestion(str_replace('Keep this page open. ', '', $sq->getText()), null, 'OneTimeCode');

                return false;
            }

            // There have been too many unsuccessful attempts at providing your password. For your security, your password has been locked.
            // Account suspended
            if ($error = $this->waitForElement(WebDriverBy::xpath("
                    //p[contains(text(), 'There have been too many unsuccessful attempts at providing your password. For your security, your password has been locked.')]
                    | //h2[contains(text(), 'Account suspended') or contains(., 'Password locked')]
                "), 0)
            ) {
                throw new CheckException($error->getText(), ACCOUNT_LOCKOUT);
            }
            // There appears to be a problem with your account. Please call Customer Service on (local ref) for assistance.
            // There has been an error. Please contact us for assistance.
            // Your information cannot be retrieved. Please contact us using Live Chat or 1-800-975-4722 for further assistance.
            if ($error = $this->waitForElement(WebDriverBy::xpath("
                    //p[contains(., 'There appears to be a problem with your account. Please call Customer Service on (local ref) for assistance.')]
                    | //div[contains(text(), 'There has been an error. Please contact us for assistance.')]
                    | //span[contains(text(), 'Your information cannot be retrieved.')]
                    | //p[contains(text(), \"We're sorry, the system has encountered an error or is currently unavailable. Please try again or contact us if you continue to experience issues.\")]
                    | //p[contains(text(), 'The system is currently unavailable, please try again later.')]
                    | //p[contains(text(), 'We don’t currently have a mobile number or email address for you. Please update the information by logging on with your Security')]
                "), 0)
            ) {
                throw new CheckException($error->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            // I have read and agree to the Electronic Communications Disclosure Notice
            // Please review and accept the below Electronic Communications Disclosure.
            /**
             *  Digital Security Device activation incomplete.
             *
             * You have not completed activation of your Digital Security Device.
             * You will not be able to access full Online Banking until activation is complete.
             * To complete activation, use the HSBC Mobile Banking app.
             */
            if ($this->waitForElement(WebDriverBy::xpath("
                    //span[contains(text(), 'I have read and agree to the Electronic Communications Disclosure Notice')]
                    | //p[contains(text(), 'Please review and accept the below Electronic Communications Disclosure.')]
                    | //p[contains(text(), 'You have not completed activation of your Digital Security Device. You will not be able to access full Online Banking until activation is complete.')]
                "), 0)
            ) {
                $this->throwProfileUpdateMessageException();
            }

            // save page to logs
            $this->saveResponse();
        }

        return true;
    }

    public function parseLoginForm()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'gsa/?idv_cmd=idv.Authentication')]")) {
            return [];
        }
        $securityKey = $this->AccountFields['Pass'];
//        $securityKey = 'pass';//todo

        $this->http->Form['password'] = '';
        $digits = $this->http->FindNodes("//input[@class = 'smallestInput active']/@id");
        $this->logger->debug("digits -> " . implode(', ', $digits));

        foreach ($digits as $digit) {
            $offset = preg_replace("/[^\d]/", '', $digit);
            $this->logger->debug("$digit -> " . $digit);
            $this->logger->debug("offset -> " . $offset);

            if (!isset($securityKey[$offset - 1])) {
                $this->logger->notice("skip digit. digits -> " . var_export($digits, true));

                continue;
            }
//            $this->waitForElement(WebDriverBy::id("pass".$offset), 0)->sendKeys($securityKey[$offset-1]);
            $this->driver->findElement(WebDriverBy::id("pass" . $offset))->sendKeys($securityKey[$offset - 1]);
        }// foreach ($digits as $digit)

        return $this->http->Form;
    }

    public function memorableAnswer()
    {
        $this->logger->notice(__METHOD__);

        return $this->http->FindSingleNode("//label[contains(@for, 'memorableAnswer')]", null, true, '/(.+)\s*\:\s*$/ims');
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We're sorry that our website is currently unavailable, please try again later.
        // In order to perform a system upgrade, Online banking is not currently available. We look forward to serving you as soon as this upgrade is complete.
        if ($message = $this->http->FindSingleNode('
                //h4[contains(text(), "We\'re sorry that our website is currently unavailable, please try again later.")]
                | //p[contains(text(), "In order to perform a system upgrade, Online banking is not currently available.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function checkCredentials()
    {
        $this->logger->notice(__METHOD__);
        // Please enter a valid Username
        if ($message = $this->http->FindPreg("/(Please enter a valid Username\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The username you entered doesn't match our records. Please try again.
        // The information you entered does not match our records. Please try again.
        if ($message = $this->http->FindSingleNode('//p[
                contains(text(), "The username you entered doesn\'t match our records. Please try again.")
                or contains(text(), "The information you entered does not match our records. Please try again.")
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('
                //div[@id = "passwordError" and contains(text(), "The information you entered does not match our records.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your credentials are suspended
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Your credentials are suspended')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindSingleNode("//div[@aria-hidden = 'false']/div/h2[contains(text(), 'Account suspended')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if (strstr($this->http->currentUrl(), '.security.us.hsbc.com/gsa/IDV_USERID_SUSPENSION/')) {
            throw new CheckException('Your credentials are suspended', ACCOUNT_LOCKOUT);
        }
        // Internet Banking locked
        if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'For your security, you have been locked')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Stay informed of important account and service alerts by keeping your information up to date.
        if ($this->http->FindSingleNode('//p[contains(text(), "Stay informed of important account and service alerts by keeping your information up to date.")]')
            || $this->http->FindSingleNode("//h3[contains(text(), 'Activating the HSBC Digital Security Device')]")
            || $this->http->FindSingleNode("//h3[contains(text(), 'Digital Security Device Activation')]")
            // Electronic Communications Disclosure
            || $this->http->FindSingleNode("//span[contains(text(), 'I have read and agree to the Electronic Communications Disclosure Notice')]")) {
            $this->throwProfileUpdateMessageException();
        }
        // We're sorry, the system has encountered an error or is currently unavailable.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, the system has encountered an error or is currently unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // For your security you have been locked out of Personal Internet Banking due to too many failed log on attempts.
        // For your security you have been locked out of Online Banking and the HSBC Mobile Banking app due to too many failed log on attempts.
        if ($message = $this->http->FindSingleNode('//strong[
                contains(text(), "For your security you have been locked out of Personal Internet Banking due to too many failed log on attempts.")
                or contains(text(), "For your security you have been locked out of Online Banking and the HSBC Mobile Banking app due to too many failed log on attempts.")
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // The system is currently unavailable, please try again later.
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'showStep')]//p[contains(text(), 'The system is currently unavailable, please try again later.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }
        /*
         * Unable to retrieve your information.
         * Please contact a Customer Care Representative at 1-800-975-HSBC (1-800-975-4722).
         */
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Unable to retrieve your information.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");
        // provider error
        if ($this->http->FindPreg("/^https:\/\/www\.services\.online-banking\.us\.hsbc\.com\/gpib\/systemError\.html\?responseInfo=\{%22responseInfo%22:\{%22correlationId%22:%22([^%]+)%22\}\}$/", null, $currentUrl)) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        // parse login form
        $this->http->Form = $this->parseLoginForm();

        if (empty($this->http->Form)) {
            return false;
        }
        $question = $this->memorableAnswer();

        if (!isset($question)) {
            return false;
        }
        $this->holdSession();

        if (trim($question) == 'Memorable Answer') {
            $question = 'Enter your Memorable Answer';
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if (
            strstr($this->http->currentUrl(), 'data:text/plain;charset=utf-8;text,hsbc+%7C+')
            || $this->http->FindSingleNode("//h2[contains(text(), 'Your session has timed out')]")
        ) {
            if ($this->isNewSession()) {
                $this->logger->notice("new session");
            }

            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == 'BirthDate') {
            $this->saveResponse();

            return $this->questionAboutBirthDate();
        }

        if ($step == 'OneTimeCode') {
            $this->saveResponse();

            return $this->questionOneTimeCode();
        }

        $answer = $this->waitForElement(WebDriverBy::id("memorableAnswer"), 0);

        if (!$answer) {
            return false;
        }
        $answer->sendKeys($this->Answers[$this->Question]);
//        if ($btn = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'primaryBtn')]")))
        $btn = $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Continue']"));

        if (!$btn) {
            return false;
        }
        $btn->click();

        $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGOUT), 5);
        $this->saveResponse();

        // Invalid entry. Please enter valid Memorable Question answer and password.
        if ($this->http->FindPreg("/(Invalid entry\.\s*Please enter valid Memorable Question answer and password\.)/ims")) {
            $this->parseQuestion();

            return false;
        }
        // The information you entered does not match our records. If you enter an incorrect Digital Secure Key password you will generate an invalid security code. Please try again.
        if ($message = $this->http->FindSingleNode('//p[
                contains(text(), "The information you entered does not match our records. If you enter an incorrect Digital Secure Key password you will generate an invalid security code. Please try again.")
            ]')
        ) {
            $this->parseQuestion();

            return false;
        }
        $this->checkCredentials();

        return true;
    }

    public function questionOneTimeCode()
    {
        $this->logger->notice(__METHOD__);
        $sq = $this->waitForElement(WebDriverBy::xpath(self::XPATH_ONE_TIME_CODE), 7);
        $this->saveResponse();

        if (!$sq) {
            return false;
        }

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        if ($pin = $this->waitForElement(WebDriverBy::id('verifyWithSmsOtp'), 0)) {
            $pin->clear();
            $pin->sendKeys($answer);

            $continueBtn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "otp_submit_btn" and not(@disabled)]'), 5);
            $this->saveResponse();

            if (!$continueBtn) {
                $this->logger->error("[OneTimeCode]: btn not found");

                return false;
            }
            $continueBtn->click();

            $trustBtn = $this->waitForElement(WebDriverBy::xpath('//label[@for="TB_YES" or contains(@class, "trusted-radio-new-yes")]'), 10);
            $this->saveResponse();

            if ($trustBtn) {
                $trustBtn->click();
                $contBnt = $this->waitForElement(WebDriverBy::xpath('//button[@id = "trusted_logon_button" and not(@disabled)]'), 10);
                $this->saveResponse();

                if ($contBnt) {
                    $contBnt->click();
                }
            }

            $res = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "errorMsg") and contains(text(), "GSA_SYSTEM_ERROR")]'), 10); //todo: fake
            $this->saveResponse();

            if ($res && $res->getText() == 'GSA_SYSTEM_ERROR') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return true;
        }

        $pin = $this->waitForElement(WebDriverBy::id('pinverification'), 0);
        $pinPrefix = $this->waitForElement(WebDriverBy::id('cdePrefix'), 0);

        if (!$sq || !$pinPrefix) {
            $this->logger->error("[OneTimeCode]: input not found");

            return false;
        }

        if (strlen($answer) > 6) {
            $this->logger->notice("remove prefix from answer");
            $prefix = $this->http->FindPreg('/\((\d+)\)/', false, $pinPrefix->getText());
            $answer = $this->http->FindPreg("/^\(?{$prefix}\)?\s*(\d+)/", false, $answer);
        }
        $this->logger->debug("code: {$answer}");

        if (!$answer) {
            $this->holdSession();
            $this->AskQuestion($this->Question, "The information you entered does not match our records.", "OneTimeCode");

            return false;
        }

        $pin->clear();
        $pin->sendKeys($answer);

        $continueBtn = $this->waitForElement(WebDriverBy::xpath('//input[@id = "pinContinue"]'), 5);
        $this->saveResponse();

        if (!$continueBtn) {
            $this->logger->error("[OneTimeCode]: btn not found");

            return false;
        }
        $continueBtn->click();

        $this->waitForElement(WebDriverBy::xpath('
                //input[@id = "yes"]
                | //div[@id = "pinerror" and
                    (
                        contains(., "The information you entered does not match our records.")
                        or contains(., "The security code is 6 characters long, numbers only. Please try again.")
                        or contains(., "The activation code you entered doesn’t match. Please try again.")
                    )
                ]
        '), 10);
        $this->saveResponse();

        if ($error = $this->waitForElement(WebDriverBy::xpath('(//div[@id = "pinerror" and 
            (
                contains(., "The information you entered does not match our records.")
                or contains(., "The security code is 6 characters long, numbers only. Please try again.")
                or contains(., "The activation code you entered doesn’t match. Please try again.")
            )
            ])[1]'), 0)
        ) {
            $this->holdSession();
            $this->AskQuestion($this->Question, str_replace('Error The ', 'The ', $error->getText()), "OneTimeCode");

            return false;
        }
        $yes = $this->waitForElement(WebDriverBy::xpath('//input[@id = "yes"]'), 0);

        if (!$yes) {
            return false;
        }

        $yes->click();
        $loginBtn = $this->waitForElement(WebDriverBy::xpath('//input[@id = "tb_logon"]'), 0);
        $this->saveResponse();

        if (!$loginBtn) {
            return false;
        }

        $loginBtn->click();
        $res = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "errorMsg") and contains(text(), "GSA_SYSTEM_ERROR")]'), 10); //todo
        $this->saveResponse();

        if ($res && $res->getText() == 'GSA_SYSTEM_ERROR') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return true;
    }

    public function Parse()
    {
        try {
            $this->logger->notice("[Current URL]: " . $this->http->currentUrl());
        } catch (Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
            throw new CheckRetryNeededException(3, 0);
        }

        $simpleRegions = [
            'Canada',
            'UK', // refs #17790
        ];

        // TODO: refs #22666
        /*
        if ($this->AccountFields['Login2'] == "Singapore") {
//            $this->http->GetURL("https://www.hsbc.com.sg/online/ccredeemrewards/?giftDescription=SGD20%20Grab%20Food%20Voucher&giftCode=7007&point=7200");
            $this->http->GetURL("https://www.hsbc.com.sg/credit-cards/rewards/");

            $redeemBtn = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Redeem now")]'), 15);
            $this->saveResponse();

            $this->driver->executeScript("
                    try {
                        $([document.documentElement, document.body]).animate({
                          scrollTop: $('span:contains(\"Redeem now\")').offset().top - 200
                        }, 500);
                    } catch (e) {}
                ");

            $this->saveResponse();
            $link = $this->http->FindSingleNode('(//a[contains(., "Redeem now")]/@href)[1]');
            $this->logger->debug("[Link]: {$link}");
            $this->http->NormalizeURL($link);
            $this->logger->debug("[Link]: {$link}");
            $this->http->GetURL($link);
//            $redeemBtn = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Redeem now")]'), 0);
//            $redeemBtn->click();

            sleep(15);
            $this->saveResponse();
        }
        */

        if (in_array($this->AccountFields['Login2'], $simpleRegions)) {
            // UK - WORLD ELITE
            $cardWithRewards = $this->waitForElement(WebDriverBy::xpath("//*[self::a or self::span][
                contains(., 'HSBC Premier World Elite Mastercard')
                or contains(., 'HSBC World Elite Mastercard')
                or contains(., 'HSBC Premier World Mastercard')
                or contains(., 'HSBC Premier Mastercard')
                or contains(., 'WORLD ELITE')
                or contains(., 'HSBC PREMIER Mastercard')
                or contains(., 'HSBC ADVANCE Mastercard')
                or contains(., 'HSBC Plus Rewards Mastercard')
            ]"), 7);
            $this->saveResponse();
            $this->closePopup();

            $delay = 0;

            if (!$cardWithRewards) {
                // refs #22739
                $this->driver->executeScript("
                    try {
                        $([document.documentElement, document.body]).animate({
                          scrollTop: $('strong:contains(\"Mastercard\"), span:contains(\"Mastercard\")').offset().top - 200
                        }, 500);
                    } catch (e) {}
                ");

                // refs #22137
                $cardWithRewards = $this->waitForElement(WebDriverBy::xpath("//*[self::a or self::span][
                        contains(., 'HSBC Premier World Elite Mastercard')
                        or contains(., 'HSBC World Elite Mastercard')
                        or contains(., 'HSBC Premier World Mastercard')
                        or contains(., 'HSBC Premier Mastercard')
                        or contains(., 'WORLD ELITE')
                        or contains(., 'HSBC PREMIER Mastercard')
                        or contains(., 'HSBC ADVANCE Mastercard')
                        or contains(., 'HSBC Plus Rewards Mastercard')
                    ]"), 1);
                $this->saveResponse();
            }

            if ($cardWithRewards) {
                $cardWithRewards->click();
                $delay = 7;
            }

            $viewMore = $this->waitForElement(WebDriverBy::xpath("//*[self::a or self::span][contains(., 'View more details')]"), 5);
            $this->closePopup();

            // Name
            $name = $this->http->FindSingleNode('//h1[contains(text(), "Hello ")]', null, true, "/Hello ([^,!]+)/");

            if (!empty($name)) {
                $this->SetProperty('Name', beautifulName($name));
            }

            if ($viewMore) {
                $viewMore->click();
                $this->saveResponse();
            }

            // too many cards
            if (!$cardWithRewards && !$this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Rewards points')] | //p[not(contains(@class, \"noDisplay\"))]//span[contains(text(), 'Rewards Cash')]/following-sibling::span[@class = 'funds']"), 0)) {
                $cardWithRewards = $this->waitForElement(WebDriverBy::xpath("
                        //a[
                            contains(., 'HSBC Premier World Elite Mastercard')
                            or contains(., 'HSBC World Elite Mastercard')
                        ]//span[@id]
                        | //button[
                                contains(., 'HSBC PREMIER Mastercard')
                                or contains(., 'HSBC Premier World Elite Mastercard')
                            ]//span[@id]
                "), 0, false);

                if ($cardWithRewards) {
                    $id = $cardWithRewards->getAttribute('id');
                    $this->driver->executeScript("document.getElementById('{$id}').click();");
                    $delay = 7;
                }
            }// if (!$cardWithRewards && !$this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Rewards points')]"), 0))
            $rewardsPage = $this->waitForElement(WebDriverBy::xpath("
                //a[contains(text(), 'Rewards points')]
                | //a[contains(text(), 'Reward points')]
                | //a[contains(text(), 'Redeem your Points here')]
                | //p[not(contains(@class, \"noDisplay\"))]//span[contains(text(), 'Rewards Cash')]/following-sibling::span[@class = 'funds']
            "), $delay);
            $this->saveResponse();
            // Name
            $name = $this->http->FindSingleNode('//span[@data-dojo-attach-point = "_username"]', null, true, "/([^,]+)/");

            if (!empty($name)) {
                $this->SetProperty('Name', beautifulName($name));
            }

            if (!$rewardsPage) {
                if ($this->waitForElement(WebDriverBy::xpath("//div[not(contains(@class, 'dijitHidden'))]/p/a[contains(text(), 'Learn More about HSBC Programme')]"), 0)
                    // AccountID: 5424825, 5471587, 5464540
                    || $this->waitForElement(WebDriverBy::xpath("//h1[@id = 'account-summary-name' and contains(text(), 'HSBC World Elite Mastercard')]"), 0)
                    || count($this->http->FindNodes("//h1[@id = 'account-summary-name' and contains(text(), 'HSBC World Elite Mastercard')]")) == 2
                ) {
                    $this->SetBalanceNA();
                }

                // AccountID: 4614266
                $cards = $this->http->XPath->query("//div[@id = 'hdx_dijits_TitlePane_0_pane']/span/a//span[contains(@class, \"itemTitle\")]/text()[last()]");
                $this->logger->debug("Total {$cards->length} cards were found");
                $savingCard = 0;

                foreach ($cards as $card) {
                    $this->logger->debug("[Card]: {$card->nodeValue}");

                    if (strstr($card->nodeValue, 'HSBC Advance Chequing') || strstr($card->nodeValue, 'High-Rate Savings')) {
                        $savingCard++;
                    }
                }// foreach ($cards as $card)
                $this->logger->debug("Saving cards: {$savingCard}");

                if ($cards->length > 0 && $cards->length == $savingCard) {
                    $this->SetBalanceNA();
                }

                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    if ($this->http->FindSingleNode('//h2[contains(text(), "Log on - Accept Terms and Conditions")]')) {
                        $this->throwAcceptTermsMessageException();
                    }

                    if ($message = $this->http->FindSingleNode('//span[contains(text(), "Sorry, this function is currently unavailable. Please try again later.")]')) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }
                }

                return;
            }
            // Balance - TOTAL POINTS
            if (
                !$this->SetBalance($this->http->FindSingleNode('
                    //span[@data-dojo-attach-point = "_formattedRewardsBlance"]
                    | //p[@id = "cc-account-bonusPoints-value"]
                '))
            ) {
                if ($this->SetBalance($this->http->FindSingleNode('//span[contains(text(), "Rewards Cash")]/following-sibling::span[@class = "funds"]'))) {
                    $this->SetProperty('Currency', $this->http->FindSingleNode('//p[@id = "currencyField"]/span[@class = "currencyType"]'));
                }
            }
            // AccountID: 5209347
            if (
                $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && $this->http->FindPreg("/<span[^>]+data-dojo-attach-point=\"_formattedRewardsBlance\"><\/span>/")
            ) {
                $this->driver->executeScript('var windowOpen = window.open; window.open = function(url){windowOpen(url, \'_self\');}');
//            $this->driver->executeScript('document.querySelector(\'a[data-dojo-attach-point = "_rewardsPointLinkRedirect"]\').click()');
                $rewardsPage->click();
                sleep(1);
                $this->saveResponse();
                $this->http->GetURL("https://rewards.hsbc.ca/en-CA");

                $balance = $this->waitForElement(WebDriverBy::xpath("//span[@id = 'homePoints']"), 10);
                $this->saveResponse();

                if (!$balance) {
                    return;
                }
                // Balance - TOTAL POINTS
                $this->SetBalance($this->http->FindSingleNode("//span[@id = 'homePoints']"));
                // Name
                $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//h2[contains(text(), "Welcome back, ")]', null, true, "/Welcome back,\s*([^<]+)/")));
                // Travel Enhancement Credit
                $this->SetProperty('TravelCredit', $this->http->FindSingleNode("//strong[@id = 'homeTravelCredit']"));
            }

            return;
        }// if (in_array($this->AccountFields['Login2'], $simpleRegions) )

        // Electronic Communications Disclosure
        if ($this->http->FindSingleNode("//h3[contains(text(), 'Electronic Communications Disclosure')]")
            && $this->http->FindSingleNode("//input[contains(@value, 'Remind me Later')]/@value")
            && $this->http->ParseForm(null, "//input[contains(@value, 'Remind me Later')]/ancestor::form[1]")) {
            $this->logger->notice("skip Electronic Communications Disclosure");
            $this->http->GetURL("https://www.us.hsbc.com/1/2/3/personal/online-services/personal-internet-banking/view-accounts/account-summary?isUrlRedirect=yes&urlRedirectCommand=cmd_AccountSummary");
            $this->logger->notice("[Current URL]: " . $this->http->currentUrl());
        }

        $myRewards =
            $this->http->FindSingleNode("//a[contains(@title, 'My Rewards page')]/@href", null, true, '/\'([^\']+)/ims')
            ?? $this->http->FindSingleNode("//a[contains(text(), 'My rewards') and @data-uid = 'myRewards']/@data-url", null, false)
            // new design, March 2021
            ?? $this->http->FindSingleNode("//a[span[contains(text(), 'My rewards')] and contains(@href, 'LaunchOver')]/@href", null, false)
        ;
        // new design fix
        if (!$myRewards
            && (
                $this->http->FindSingleNode("//span[@data-dojo-attach-point = '_username']", null, true, "/[^\,]+/")
                || $this->http->FindSingleNode("//span[contains(text(), 'An error has occurred while processing your request.')]")
            )
        ) {
            $myRewards = "https://www.lgsso.online-banking.us.hsbc.com/lgapp-rwdmaz/services/LaunchOver";
        }

        $dashboardUnavailable = $this->http->FindSingleNode("//span[contains(text(), 'Sorry, the account dashboard is currently unavailable; please try again later.') or contains(text(), 'Your information cannot be retrieved.')]");

        if ($myRewards) {
            $this->logger->notice("Go to 'My Rewards' -> {$myRewards}");

            if ($error = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 're sorry, there has been an error. Please contact us quoting code PC8.')]"), 0)) {
                throw new CheckException($error->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            $this->http->NormalizeURL($myRewards);

            try {
                $this->http->GetURL($myRewards);
            } catch (ScriptTimeoutException $e) {
                $this->logger->debug("ScriptTimeoutException: " . $e->getMessage());
            }
//            finally {
//                sleep(5);
//                $this->http->GetURL($myRewards);
//            }
            // prevent broken parse when there is error on rewards page
            if ($this->waitForElement(WebDriverBy::xpath("//form[@name = 'PIB2MaritzLaunchingForm']"), 7)) {
                $this->driver->executeScript("document.PIB2MaritzLaunchingForm.submit();");
                sleep(2);
            }
            $this->waitForElement(WebDriverBy::xpath("
                //p[contains(text(), 'Points Balance:') or contains(text(), 'Cash Rewards Balance:')]
                | //li[@id = 'totalPointsLabel']
                | //div[contains(@class, 'pointText')]
                | //div[@id = 'header-middle-layer']/div[@class = 'balance']
            "), 7);

            try {
                $this->saveResponse();
            } catch (ScriptTimeoutException | TimeOutException $e) {
                $this->logger->debug("TimeoutException: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
            }

            $cards = $this->http->FindNodes("//select[contains(@id, '_ddlAccountGroup')]/option");

            if (count($cards) == 0) {
                $properties = $this->getAdditionalProperties();

                foreach ($properties as $property => $value) {
                    if ($property == 'Balance') {
                        $this->SetBalance($value);

                        continue;
                    }
                    $this->SetProperty($property, $value);
                }
            }// if (count($cards) == 0)
            else {
                foreach ($cards as $displayName) {
                    $this->logger->debug("{$displayName}");
                    $code = $this->http->FindPreg("/\-\s*([\d]+)/", false, $displayName);

                    $this->driver->executeScript("
                        var nextCard = $('select[id *= \"_ddlAccountGroup\"] option:contains(\"{$displayName}\")');
                        if (nextCard.length > 0) {
                            nextCard.attr('selected', 'selected');
                            $('input[id *= \"_btnGo\"]').click();
                        }
                    ");

                    $this->sendNotification("check balance - refs #21681 // RR");

                    $properties = $this->getAdditionalProperties();

                    if ($properties['Balance']) {
                        $this->AddSubAccount([
                            "Code"        => 'hsbc' . $this->AccountFields['Login2'] . $code,
                            'DisplayName' => $displayName,
                            // Balance -  Available
                            'Balance'     => $properties['Balance'],
                            // Name
                            'Name'        => $properties['Name'],
                            // Earned
                            'Earned'      => $properties['Earned'],
                            // Redeemed
                            'Redeemed'    => $properties['Redeemed'],
                            // Expired
                            'Expired'     => $properties['Expired'],
                        ], true);
                        $this->SetBalanceNA();
                    }// if ($balance)
                }// foreach ($cards as $card)
            }
        }// if ($myRewards)
        else {
            // Name
            $this->SetProperty('Name', beautifulName(
                $this->http->FindSingleNode("//*[self::em or self::h1][contains(text(), 'Welcome')]", null, true, '/Welcome\s*([^<!]+)/ims')
                ?? $this->http->FindSingleNode('//div[@id = "my-account-drop-down"]//div[@class = "name"]')
            ));

            if (!empty($this->Properties['Name'])) {
                $this->SetBalanceNA();
            }

            // refs #19888
            if ($balanceCard = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'HSBC Premier World Mastercard') or contains(text(), 'HSBC Elite Mastercard')]"), 0)) {
                $this->saveResponse();
                $this->driver->executeScript("var popup = document.querySelector('[id *= \"cpi-modal-dialog-\"]'); if (popup) popup.style = \"display: none;\";");
                $this->saveResponse();
                $balanceCard->click();

                $shoDetails = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Show details')]"), 10);
                $this->saveResponse();

                if ($shoDetails) {
                    $shoDetails->click();

                    $this->waitForElement(WebDriverBy::xpath("//div[p[contains(text(), 'Rewards balance')]]/following-sibling::div/p | //p[@id = 'cc-account-bonusPoints-value']"), 10);
                    $this->saveResponse();
                    // Rewards balance
                    $this->SetBalance($this->http->FindSingleNode('//div[p[contains(text(), "Rewards balance")]]/following-sibling::div/p | //p[@id = "cc-account-bonusPoints-value"]'));
                }

                /*
                $this->http->GetURL("https://www.lgsso.online-banking.us.hsbc.com/lgapp-rwdeng/services/LaunchOver");
                $this->sendNotification("refs #19888 See logs // RR");
                sleep(10);
                $this->saveResponse();
                */
            }
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Maintenance
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are performing scheduled maintenance')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            //We’re sorry, but we are unable to log you in at this time.
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We’re sorry, but we are unable to log you in at this time.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // A technical error has occurred
            if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'A technical error has occurred')]")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindSingleNode("//p[
                    contains(text(), 'You must accept the Terms and Conditions of the program before finalizing your account activation')
                    or contains(text(), 'Please review and accept the below Electronic Communications Disclosure.')
                    or contains(normalize-space(text()), 'You must accept the Program Rules of the applicable Card(s) on your account before finalizing your activation')
                ]")
                || $this->http->FindPreg("/<p>You must accept the Program Rules of the applicable Card\(s\) on your account before finalizing your activation/")
            ) {
                $this->throwAcceptTermsMessageException();
            }
            // Your account is currently Inactive. Please contact our Contact Center.
            // Your account is currently Cancelled. Please contact our Contact Center.
            // You are not authorised to enter into the application.
            /*
             * You are not enrolled for "My Rewards".
             * Please contact us via Live Chat or call us at 1-800-975-4722 to know more.[Ref# RWD001-1355-20170814075246]
             */
            if ($message = $this->waitForElement(WebDriverBy::xpath("
                    //p[contains(text(), 'Your account is currently Inactive. Please contact our Contact Center.')]
                    | //p[contains(text(), 'Your account is currently Cancelled. Please contact our Contact Center.')]
                    | //div[contains(text(), 'You are not authorised to enter into the application.')]
                    | //span[contains(text(), 'You are not enrolled for \"My Rewards\". Please contact us via Live Chat or call us at 1-800-975-4722 to know more')]
                "), 0)
            ) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            // provider error
            if ($message = $this->http->FindSingleNode('//h1[contains(text(), "We\'re sorry, an error has occured")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            /*
             * Service Unavailable
             *
             * We're sorry. Personal Internet Banking service is temporarily unavailable. Please try again shortly.
             * We apologize for the inconvenience.
             */
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 're sorry. Personal Internet Banking service is temporarily unavailable. Please try again shortly.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // This site can’t be reached
            if ($this->http->FindSingleNode("//*[self::h1 or self::span][contains(text(), 'This site can’t be reached') or contains(text(), 'This page isn’t working')]")
                && $this->http->FindSingleNode("//p[contains(text(), 'The connection was reset.')]")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // An error has occurred while accessing this page. Please try again later. If you continue to experience this issue, please contact us via Live Chat or call us at 1-800-975-4722 for further assistance.
            if ($this->http->FindSingleNode("//span[contains(text(), 'An error has occurred while accessing this page.')]") && isset($dashboardUnavailable)) {
                throw new CheckException($dashboardUnavailable, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function injectJQ()
    {
        $this->logger->notice(__METHOD__);
        $this->driver->executeScript("
            var jq = document.createElement('script');
            jq.src = \"https://code.jquery.com/jquery-1.12.4.min.js\";
            document.getElementsByTagName('head')[0].appendChild(jq);
        ");
    }

    protected function closePopup()
    {
        $this->logger->notice(__METHOD__);

        $this->driver->executeScript("var popup = document.getElementById('dijit_DialogUnderlay_0'); if (popup) popup.style = \"display: none;\";");
        $this->driver->executeScript("var popup = document.getElementById('dijit_Dialog_0'); if (popup) popup.style = \"display: none;\";");
        $this->driver->executeScript("var popup = document.getElementById('cpi-modal-dialog-0'); if (popup) popup.style = \"display: none;\";");
        $this->saveResponse();

        if ($closeBtn = $this->waitForElement(WebDriverBy::xpath("
                    //a[contains(@class, 'trigger-vam-popup-close') and contains(text(), 'Close')]
                    | //button[@data-pid-action = 'close']
                "), 0)
        ) {
            try {
                $closeBtn->click();
            } catch (Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
                $this->logger->error("[ElementClickInterceptedException]: {$e->getMessage()}");

                $this->driver->executeScript("try { document.querySelector('.vam-modal_closeicon').click() } catch (e) {}");
                sleep(1);
            }

            $this->saveResponse();
        }
    }

    protected function parseReCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey");
        }
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

    // refs #7603
    protected function magicDelay($delay = 2)
    {
        $this->logger->notice(__METHOD__ . " -> {$delay}");
        sleep($delay);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->saveResponse();
        $this->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));

        if ($this->http->FindSingleNode('//input[@id="username"]')) {
            return false;
        }

        // Access is allowed
        if ($this->http->FindSingleNode(self::XPATH_LOGOUT)) {
            return true;
        }

        return false;
    }

    private function getAdditionalProperties()
    {
        $mainBalance = $this->http->FindSingleNode("//p[contains(text(), 'Points Balance:') or contains(text(), 'Cash Rewards Balance:')]", null, true, "/\:\s*([^<]+)/ims")
            ?? $this->http->FindSingleNode("//li[@id = 'totalPointsLabel'] | //div[contains(@class, 'pointText')]", null, true, "/\:\s*([^<]+)/ims")
            ?? $this->http->FindSingleNode("//div[@id = 'header-middle-layer']/div[@class = 'balance']");

        if (isset($mainBalance) && $this->AccountFields['Login2'] != 'Canada') {
            $this->http->GetURL("https://rewards.us.hsbc.com/page.aspx?id=accountsummary");
        }

        $this->waitForElement(WebDriverBy::xpath("//span[contains(@id, 'PointsAvailableLabel')]"), 7);
        $this->saveResponse();

        return [
            // Name
            'Name' => beautifulName($this->http->FindSingleNode('//span[@id="hellptxt"] | //div[@class = "paxGreeting"]/span', null, true, "/([^!]+)/")),
            // Balance -  Available
            'Balance' => $this->http->FindSingleNode("//span[contains(@id, 'PointsAvailableLabel')] | //span[@class = 'pointValue']") ?? $this->http->FindSingleNode("//li[@id = 'totalPointsLabel'] | //div[contains(@class, 'pointText')]", null, true, "/\:\s*([^<]+)/ims") ?? $mainBalance,
            // Earned
            'Earned' => $this->http->FindSingleNode("//span[contains(@id, 'PointsEarnedLabel')]"),
            // Redeemed
            'Redeemed' => $this->http->FindSingleNode("//span[contains(@id, 'PointsRedeemedLabel')]"),
            // Expired
            'Expired' => $this->http->FindSingleNode("//span[contains(@id, 'PointsExpiredLabel')]"),
        ];
    }
}
