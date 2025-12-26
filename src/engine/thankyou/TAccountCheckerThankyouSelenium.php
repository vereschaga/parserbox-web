<?php

use AwardWallet\Common\Parsing\Html;

require_once __DIR__ . '/../citybank/TAccountCheckerCityBankSelenium.php';

class TAccountCheckerThankyouSelenium extends TAccountCheckerCityBankSelenium
{
    use SeleniumCheckerHelper;

    public const IDENTIFICATION_CODE_MSG = 'Please enter Identification Code which was sent to your phone. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.';

    public $regionOptions = [
        ""         => "Select type of your credentials",
        "Citibank" => "Citibank® Online username and password",
        //        "ThankYou" => "ThankYou.com username and password",
        "Sears"    => "Sears username and password",
    ];

    protected $citiBank = false;

    public function InitBrowser()
    {
        TAccountChecker::InitBrowser();
        $this->UseSelenium();
        $this->useFirefoxPlaywright();
//        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
//        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
//        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->http->saveScreenshots = true;
//        $this->disableImages();
    }

    public function LoadLoginForm()
    {
        $this->DebugInfo = null;
        $this->http->removeCookies();
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        // todo: need test
        if (!in_array($this->AccountFields['Login2'], ['Citibank', 'Sears'])) {
            return false;
        }

        try {
            $this->http->GetURL("https://online.citibank.com/US/JPS/portal/Index.do?userType=tyLogin");
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            sleep(3);
            $this->saveResponse();
        } catch (WebDriverCurlException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 5);
        }

        $formXpath = "//form[@name = 'partnerLoginForm']";

        try {
            $loginField = $this->waitForElement(WebDriverBy::xpath("{$formXpath}//input[@id = 'username']"), 10);
        } catch (StaleElementReferenceException | NoSuchElementException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            sleep(10);
            $loginField = $this->waitForElement(WebDriverBy::xpath("{$formXpath}//input[@id = 'username']"), 5);
        }
        $this->saveResponse();

        $loading = null;

        try {
            if ($this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'jamp')]"), 0)) {
                sleep(7);
                $loginField = $this->waitForElement(WebDriverBy::xpath("{$formXpath}//input[@id = 'username']"), 0);
                $this->saveResponse();
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->saveResponse();
            sleep(10);
            $loginField = $this->waitForElement(WebDriverBy::xpath("{$formXpath}//input[@id = 'username']"), 5);
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 3);
        }

        // password
        $passField = $this->waitForElement(WebDriverBy::xpath("{$formXpath}//input[@id = 'password']"), 0);
        $loginButton = $this->waitForElement(WebDriverBy::xpath("{$formXpath}//button[@id = 'signInBtn']"), 0);

        try {
            $this->saveResponse();
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            sleep(3);
            $this->saveResponse();
        }

        try {
            $loading = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'jamp')]"), 0);
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            sleep(3);
            $loading = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'jamp')]"), 0);
        }

        // sometimes it helps
        if ($loading) {
            sleep(7);
            $loginField = $this->waitForElement(WebDriverBy::xpath("{$formXpath}//input[@id = 'username']"), 10);
            $passField = $this->waitForElement(WebDriverBy::xpath("{$formXpath}//input[@id = 'password']"), 0);
            $loginButton = $this->waitForElement(WebDriverBy::xpath("{$formXpath}//button[@id = 'signInBtn']"), 0);
        }

        if (empty($loginField) || empty($passField) || !$loginButton) {
            if ($logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')] | //div[@id = 'header-signoff'] | //a[@id = 'signOffmainAnchor']"), 0)) {
                return true;
            }

            $this->DebugInfo = 'Failed to find inputs';

            try {
                $this->http->GetURL("https://online.citibank.com/US/JPS/portal/Index.do?userType=tyLogin");
            } catch (NoSuchDriverException $e) {
                $this->logger->error("NoSuchDriverException: " . $e->getMessage());

                throw new CheckRetryNeededException(2, 3);
            }
            sleep(7);
            $loginField = $this->waitForElement(WebDriverBy::xpath("{$formXpath}//input[@id = 'username']"), 10);
            $passField = $this->waitForElement(WebDriverBy::xpath("{$formXpath}//input[@id = 'password']"), 0);
            $loginButton = $this->waitForElement(WebDriverBy::xpath("{$formXpath}//button[@id = 'signInBtn']"), 0);
            $this->saveResponse();
        }

        if (empty($loginField) || empty($passField) || !$loginButton) {
            $this->logger->error('Failed to find inputs');
//            throw new CheckRetryNeededException();

            return $this->checkErrors();
        }

        try {
            $loginField->sendKeys($this->AccountFields['Login']);
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 3);
        }
        $passField->sendKeys($this->AccountFields['Pass']);

        if ($this->AccountFields['Login2'] === 'Sears') {
            $this->driver->executeScript("
                function triggerInput(selector, enteredValue) {
                    const input = document.querySelector(selector);
                    var createEvent = function(name) {
                        var event = document.createEvent('Event');
                        event.initEvent(name, true, true);
                        return event;
                    };
                    input.dispatchEvent(createEvent('focus'));
                    input.value = enteredValue;
                    input.dispatchEvent(createEvent('change'));
                    input.dispatchEvent(createEvent('input'));
                    input.dispatchEvent(createEvent('blur'));
                }
                triggerInput('input[name = \"IdStrHiddenInput\"]', 'sears');
            ");
        }// if ($this->AccountFields['Login2'] === 'Sears')

        $this->saveResponse();

        $loginButton->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//p[
                contains(text(), "We are currently making updates to Citi.com.")
                or contains(text(), "Our online and mobile banking sites will be undergoing routine maintenance")
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Maintenance
        if ($message = $this->http->FindPreg("/(Network\s*website is temporarily\s*unavailable while we perform maintenance\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# We're sorry. Citi.com is temporarily unavailable
        if ($message = $this->http->FindPreg("/(We\'re sorry\. Citi\.com is temporarily unavailable\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are sorry. we had an issue processing your request.
//        if ($message = $this->http->FindPreg("/(We are sorry\.\s*we had an issue processing your request\.)/ims"))
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        //# Provider error
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Server Error')]")
            || $this->http->FindSingleNode("//h2[contains(text(), 'Error 404')]")
            || $this->http->FindPreg("/Error 503--Service Unavailable/")
            || $this->http->FindPreg("/(Error 404--Not Found)/ims")
            || $this->http->FindPreg("/(The page cannot be displayed because an internal server error has occurred)/ims")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // The server is temporarily unable to service your request. Please try again later.
        if ($message = $this->http->FindPreg("/h1>\s*(The server is temporarily unable to service your request\.\s*Please try again\s*later\.)<p>/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // ThankYou.com is currently unavailable for this User ID.
        if ($message = $this->http->FindSingleNode("//h4[@id = 'apperror_ty']", null, true, "/ThankYou\.com is currently unavailable for this User ID\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We've had a problem processing your request.
        if ($message = $this->http->FindSingleNode("//font[@class = 'errortext']", null, true, "/We've had a problem processing your request\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're sorry. Citi.com is temporarily unavailable.
        if ($message = $this->http->FindPreg("/(We\'re sorry\. Citi\.com is temporarily unavailable\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            // We're very sorry, but that content you are looking for can't be found.
            // We're sorry. This page is unavailable.
            // We are unable to process your request at this time.
        $message = $this->waitForElement(WebDriverBy::xpath('
                //p[contains(text(), "We\'re very sorry, but that content you are looking for can\'t be found.")]
                | //td[@class = "jrsintrotext"]/li[@class = "jrserrortext" and contains(text(), "We\'re sorry. This page is unavailable.")]
                | //div[contains(text(), "We are unable to process your request at this time.")]
                | //td[contains(text(), "We\'re unable to process your request.")]
                | //h1[contains(text(), "AccountOnline Temporarily Unavailable")]
                | //h1[contains(text(), "Oops! Page Not Found")]
                | //p[contains(text(), "Citi Online Access® is currently down for scheduled maintenance.")]
            '), 0)
        ) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }
        // Our system is experiencing temporary delays.
        if ($message = $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Our system is experiencing temporary delays.")]'), 0)) {
            $error = $message->getText();
            $this->http->GetURL("https://online.citi.com/US/CBOL/ain/cardasboa/flow.action");
//            if ($this->successLogin())
            if ($this->loginSuccessful()) {
                return true;
            }

            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }

        try {
            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 5);
        }

        if (strstr($currentUrl, 'CallIFXValidationServiceForGPS')) {
            $this->DebugInfo = 'CallIFXValidationServiceForGPS';

            throw new CheckRetryNeededException(3, 7, self::PROVIDER_ERROR_MSG);
        }

        if (strstr($currentUrl, 'https://www.citi.com/credit-cards/citi.action')) {
            throw new CheckRetryNeededException(2, 5);
        }

        if ($message = $this->waitForElement(WebDriverBy::xpath('//td[@class = "MTxtBold" and contains(text(), "I am sorry ...the page you requested cannot be found on this server.")]'), 0)) {
            throw new CheckRetryNeededException(2, 5, $message->getText());
        }

        if (
            $currentUrl == 'https://www.citi.com/'
            || $currentUrl == 'https://www.citi.com/?loginScreenId=inactivityHomePage'
        ) {
            throw new CheckRetryNeededException(3, 0);
        }

        // Maintenance
        $this->http->GetURL("https://www.thankyou.com/");

        if ($message = $this->http->FindSingleNode('//b[contains(text(), "the ThankYou Rewards website is temporarily unavailable while we perform maintenance")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function successLogin()
    {
        $this->logger->notice(__METHOD__);
        $signOffBtn = $this->waitForElement(WebDriverBy::xpath("//a[@class = 'signOffBtn']"), 10);
        $this->saveResponse();

        if ($signOffBtn || $this->http->FindNodes("//a[@class = 'signOffBtn']")
            || $this->waitForElement(WebDriverBy::xpath("//div[@id = 'otpPasswordSecurityHeader']"), 0)) {
            $this->citiBank = true;

            return true;
        }

        return false;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Wait link 1");

        try {
            sleep(3);
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            $delay = 15; // AccountID: 2693937
//        if (strstr($this->http->currentUrl(), 'siteId=SEARS'))
//            $delay = 15;
            $this->logger->debug("[Delay]: {$delay}");

            $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')] | //div[@id = 'header-signoff']"), $delay);
            $this->saveResponse();

            if (!$logout && $this->waitForElement(WebDriverBy::xpath("//form[@name = 'cmsHomeForm']"), 0, false)) {
                $this->driver->executeScript("$('form[name = \"cmsHomeForm\"]').submit();");
                $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')] | //div[@id = 'header-signoff']"), $delay);
                $this->saveResponse();
            }

            if ($this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Identity Checkpoint')]"), 0)) {
                return $this->processVerificationCode();
            }

            if (
                $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Security Questions Help Us Verify Your Identity")]'), 0)
            ) {
                $this->throwAcceptTermsMessageException();
            }

            $this->skipOffer();

            // AccountID: 38261, 327705, 4403627
//        if (in_array($this->AccountFields["Login"], ['melnick333', 'edelquinn5', 'leohartono']))
            if (
            $this->waitForElement(WebDriverBy::xpath('//h2[
                contains(text(), "We Can’t Find This Page (404)")
                or contains(text(), "We\'ve encountered an issue and are having a problem processing your request.")
            ]
                | //p[contains(text(), "You must be the ThankYou® Account member — generally the Citi® primary cardmember or primary signer on the enrolled Citibank® checking account")]
                | //a[contains(text(), "Return to Dashboard")]
            '), 0)
//                | //h2[contains(text(), "Help Us Verify Your Identity")]
            ) {
                $this->http->GetURL("https://online.citi.com/US/CBOL/ain/cardasboa/flow.action");
            }

            // Help Us Verify Your Identity     // refs #19047
            if ($this->waitForElement(WebDriverBy::xpath("//select[@id = 'otpDropdown'] | //div[@id = 'otpDropdown1']"), 0)) {
                return $this->processIdentificationCodeThankyou();
            }
            // two question (ATM typically)
            if ($this->waitForElement(WebDriverBy::id('challengeQuesId0'), 0)) {
                return $this->processATM();
            }
            // Security checkpoint, two question (Mother/city typically)
            if ($this->waitForElement(WebDriverBy::xpath("
                    //label[@for = 'challengeAnswers0' or contains(@for, 'sec_question_0')]
                    | //h2[normalize-space(text()) = 'Security Questions']
                    | //h2[normalize-space(text()) = 'Challenge Questions']
                    | //p[normalize-space(text()) = 'Please answer the question below.']
                "), 0)
            ) {
                return $this->processSecurityCheckpoint();
            }
            // Security checkpoint, two question (Last 3 digits on Signature Pane/Security Word typically)
            if ($this->waitForElement(WebDriverBy::xpath("//label[@for = 'CVV2']"), 0)) {
                return $this->processSecurityCheckpointV2();
            }

            if ($logout
                || $this->waitForElement(WebDriverBy::xpath("//span[@id = 'points-default-2'] | //span[contains(@id, 'mobile-points-default-info')]"), 0)
                || $this->http->FindSingleNode("//div[@id = 'account-menu-logged-in' or @id = 'mobile-header-account-info']//span[@class='points']", null, false) !== null) {
                $this->saveResponse();
                // We weren't able to find any accounts that matched your profile information.
                if ($message = $this->http->FindSingleNode('//p[contains(text(), "We weren\'t able to find any accounts that matched your profile information.")]')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($this->parseQuestion()) {
                    return false;
                }
                // The email address you provided has been flagged as undeliverable.
                if ($this->http->FindSingleNode("
                        //p[contains(text(), 'The email address you provided has been flagged as undeliverable.')]
                        | //input[@id = 'CyotaEnrollment' and @value = 'Create Security Questions']/@value
                    ")
                ) {
                    $this->throwProfileUpdateMessageException();
                }
                // Important: You must deactivate your ThankYou.com username and password
                if ($message = $this->http->FindSingleNode("
                    //h3[contains(text(), 'Important: You must deactivate your ThankYou.com username and password')]
                    | //p[contains(text(), 'You must be the ThankYou® Account member — generally the Citi® primary cardmember or primary signer on the enrolled Citibank® checking account — to access the associated ThankYou Member Account from this website.')]
                ")
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // In addition, you may be asked to update your user ID and/or password.
                if ($this->waitForElement(WebDriverBy::id('updateUserButton'), 0) && $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "In addition, you may be asked to update your user ID and/or password.")]'), 0)) {
                    $this->throwProfileUpdateMessageException();
                }

                // delay
                $this->waitForElement(WebDriverBy::xpath("//div[@id = 'account-menu-logged-in' or @id = 'mobile-header-account-info']//span[contains(text(), 'Hi')] | //div[@id = 'form_account_select-2']"), 7);
                $this->saveResponse();

                return true;
            }

            if ($link = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'header-signon']"), 0)) {
//        if ($link = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Check your point balance and redeem for rewards')]"), 0))
                $link->click();
                sleep(3);
                $this->saveResponse();
            }

            $this->logger->debug("Wait link 2");

            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
            // AccountID: 635518, 4622043, 244861, 1216530
            if (
                $currentUrl == 'https://online.citi.com/null'
                || $currentUrl == 'https://www.thankyou.com/cms/thankyou/'
                // AccountID: 5000396
                || $currentUrl == 'https://www.thankyou.com/tyAccountLocked.htm'
            ) {
                $this->http->GetURL("https://online.citi.com/US/ag/mrc/dashboard");
                /*
                $this->http->GetURL("https://online.citi.com/US/ag/parent-interstitial?userType=tyLogin&nextRoute=https://online.citi.com/US/ag/citi-partner-sso/thankyou");
                */
            }

            // provider notice
            // Safeguarding Your Account
            if (
                $this->waitForElement(WebDriverBy::xpath('
                    //div[contains(text(), "We apologize for any inconvenience, but to protect your account, further charges may be limited until you have contacted our Customer Service Department")]
                    | //p[contains(text(), "We need you to contact us to make sure information on your account profile is up to date.")]
                    | //h1[contains(text(), "Safeguarding Your Account") or contains(text(), "Immediate Attention Required")]
                    | //li[contains(text(), "Limited–Time Balance Transfer Offer")]
                    | //h2[contains(text(), "Your credit limit has increased!")]
                '), 5)
                && ($contBtn = $this->waitForElement(WebDriverBy::xpath('
                        //button[@id = "cbol_cam_okEot"]
                        | //a[contains(text(), "Continue to Account")]
                        | //a[@id = "cancelLink" and text() = "Continue to Account"]
                        | //a[@id = "tertiaryCTA" and text() = "Not Now. Please remind me later."]
                        | //a[@id = "secondaryCTA" and text() = "Skip"]
                    '), 0))
            ) {
                $this->logger->notice("skip provider notice");
                $contBtn->click();
                sleep(3);
            }
            // We've Noticed Some Unusual Activity.
            $notNow = $this->waitForElement(WebDriverBy::id('cbol_cam_notnow'), 0);

            if ($notNow && $this->waitForElement(WebDriverBy::xpath('//h3[contains(text(), "We\'ve Noticed Some Unusual Activity.")]'), 0)) {
                $notNow->click();

                if ($linkContinue = $this->waitForElement(WebDriverBy::id('cbol_cam_cancelOverlayNo'), 5)) {
                    $linkContinue->click();
                }
                sleep(3);
            }// if ($notNow && $this->waitForElement(WebDriverBy::xpath('//h3[contains(text(), "We\'ve Noticed Some Unusual Activity.")]'), 0))

            $this->skipOffer();

            // if was redirect to CitiBank website
            if (
                $this->loginSuccessful()
                || $this->waitForElement(WebDriverBy::xpath("
                    //div[contains(@class, 'cA-mrc-rewardsContainer')] 
                    | //div[contains(text(), 'Link Your Other Accounts')]
                    "), 0)
                || $this->waitForElement(WebDriverBy::xpath("
                    //div[contains(@class, 'cA-ada-welcomeBarTitleWrapper')]
                    "), 0, false)
            ) {
                $this->logger->notice("parseCitiBank");

                // We Can’t Find This Page (404)
                if (($message = $this->waitForElement(WebDriverBy::xpath('//h2[
                        contains(text(), "We Can’t Find This Page (404)")
                        or contains(text(), "Is your Contact Info up-to-date?")
                        or contains(text(), "Please Confirm Your Info")
                        or contains(text(), "Mortgage Discounts For Room To Grow")
                        or contains(text(), "Mortgage Deals For New Hiding Spots")
                        or contains(., "Mortgage Deals For Room To Grow")
                    ]
                    | //span[contains(text(), "Trouble signing on? Select ")]
                '), 0))
                    // The page isn’t redirecting properly
                    || $this->http->FindSingleNode('//h1[contains(text(), "The page isn’t redirecting properly")]')
                ) {
                    if ($message) {
                        $error = $message->getText();
                        $this->logger->error($error);
                    } else {
                        $this->logger->error("The page isn’t redirecting properly");
                    }
                    $error = $message->getText();
                    $this->logger->error($error);
                    $this->http->GetURL("https://online.citi.com/US/CBOL/ain/cardasboa/flow.action");
                }

                $accountsButton = $this->waitForElement(WebDriverBy::xpath("//ul[@id = 'nav_secured']//a[contains(text(), 'Accounts')]"), 10);
                // Identification Code Delivery Options
                if (!$accountsButton && $this->waitForElement(WebDriverBy::xpath("//div[@id = 'otpPasswordSecurityHeader']"), 0)) {
                    throw new CheckException("We do not support accounts with Identification Code yet", ACCOUNT_PROVIDER_ERROR); /*review*/
                    $this->logger->info('Identification Code Delivery Options', ['Header' => 3]);
                    $this->logger->notice('Verification: Identification Code Delivery Options');
                    $this->DebugInfo = 'Identification Code';
                    $this->saveResponse();

                    $select = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Make Your Selection')]"), 5);
                    $this->saveResponse();

                    if ($select) {
                        $this->logger->debug("Open selector");
                        $select->click();
                    }// if ($select)

                    // Select option "Send a Text me at ***-..."
                    $option = $this->waitForElement(WebDriverBy::xpath('//li[span[contains(text(), "Text me")]]'), 5);
                    $this->saveResponse();

                    if ($option) {
                        $this->logger->debug('Select option "Text me at ***-..."');
                        $option->click();
                    }// if ($option)
                    // Click "Next" button
                    if ($nextButton = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'cmlink_NextDisabled_Link']"), 0)) {
                        $this->logger->debug('Click "Next" button');
                        $nextButton->click();
                        /*
                         * I agree to receive a call or message, such as a prerecorded message, a call or message from an automated dialing system,
                         * or a text message at the number selected for the purpose of receiving my Identification Code for this transaction.
                         * Reminder: Normal cell phone charges may apply for delivery of this Identification Code.
                         */
                        if ($smsCheck = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'smsCheck']"), 3)) {
                            $this->logger->debug('Click "I agree to receive a call or message" checkbox');
//                        $smsCheck->click();
                            $this->driver->executeScript("document.getElementById('smsCheck').click();");
                            sleep(1);
                            // Click "Next" button
                            if ($nextButton = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'cmlink_NextDisabled_Link']"), 0)) {
                                $this->logger->debug('Click "Next" button');
                                $this->saveResponse();
                                $nextButton->click();
                            }// if ($nextButton = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'cmlink_NextDisabled_Link']"), 0))
                        }// if ($smsCheck = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'smsCheck']"), 3))

//                    $this->sendNotification("thankyou. Identification Code Delivery Options");

                        // todo: remove it after tests
                        // wait loading rewards
                        $this->waitForElement(WebDriverBy::xpath("//span[@id = 'ThankYou']"), 20);
                        $this->saveResponse();
                    }// if ($nextButton = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'cmlink_NextDisabled_Link']"), 5))

                    return false;
                }// if (!$accountsButton && $this->waitForElement(WebDriverBy::xpath("//div[@id = 'otpPasswordSecurityHeader']"), 0))
                // Help Us Verify Your Identity
                if ($this->waitForElement(WebDriverBy::xpath("//select[@id = 'otpDeliveryOptions']"), 0)) {
                    return $this->processIdentificationCode();
                }

                if ($accountsButton) {
                    $accountsButton->click();
                }
                // wait loading rewards
                $this->waitForElement(WebDriverBy::xpath("//span[@id = 'ThankYou']"), 20);
                $this->saveResponse();

                /*
                 * We're very sorry we're having technical issues. Please try again.
                 * We apologize for any inconvenience and appreciate your patience. [Citi002]
                 */
                if ($message = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We\'re very sorry we\'re having technical issues.")]'), 0)) {
                    throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
                }
                // Your Account is Blocked
                if ($message = $this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Your Account is Blocked")]'), 0)) {
                    throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
                }

                $this->citiBank = true;

                return true;
            }
            $this->logger->debug("Check Errors");
            $this->saveResponse();

            if ($this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')] | //div[@id = 'header-signoff']"), 0, false)) {
                return true;
            }

            // Enter Your Identification Code
            if ($q = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Enter Your Identification Code')]"), 0)) {
                $question = "Enter Your Identification Code";

                if (!isset($this->Answers[$question])) {
                    $this->holdSession();
                    $this->AskQuestion($question);

                    return false;
                }// if (!isset($this->Answers[$question]))

                return false;
            }// if ($q = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Enter Your Identification Code')]"), 0))
            // Terms & Conditions
            if (
                $this->waitForElement(WebDriverBy::xpath('
                    //span[@class = "jrsintroText" and contains(text(), "Please read the Citi Online Banking User Agreement carefully")]
                    | //div[contains(text(), "Your password doesn’t meet the latest requirements. Take a moment to update it now.")]
                    | //div[contains(text(), "Your User ID doesn’t meet the latest requirements. Take a moment to update it now.")]
                '), 0)
            ) {
                $this->throwAcceptTermsMessageException();
            }
            // two question (ATM typically)
            if ($this->waitForElement(WebDriverBy::id('challengeQuesId0'), 0)) {
                return $this->processATM();
            }
            // Help Us Verify Your Identity
            if ($this->waitForElement(WebDriverBy::xpath("//select[@id = 'otpDeliveryOptions']"), 0)) {
                return $this->processIdentificationCode();
            }
            // Security checkpoint, two question (Mother/city typically)
            if ($this->waitForElement(WebDriverBy::xpath("//label[@for = 'challengeAnswers0']"), 0)) {
                return $this->processSecurityCheckpoint();
            }
            // Security checkpoint, two question (Last 3 digits on Signature Pane/Security Word typically)
            if ($this->waitForElement(WebDriverBy::xpath("//label[@for = 'CVV2']"), 0)) {
                return $this->processSecurityCheckpointV2();
            }

            $error = $this->waitForElement(WebDriverBy::xpath('//span[@id = "loginError"]'), 0);

            if ($error) {
                $message = $error->getText();

                if (strstr($message, 'The information you submitted does not match our records.')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
            }// if ($error)
            /**
             * Having trouble signing on? Please try again or click here to be reminded of your user ID or reset your password.
             * -------------------------------------------------------------------------------------------------------------------
             * We've encountered an issue and are working to fix the issue
             * -------------------------------------------------------------------------------------------------------------------
             * I am sorry ...the page you requested cannot be found on this server.
             * -------------------------------------------------------------------------------------------------------------------
             * Important: You must deactivate your ThankYou.com username and password
             * -------------------------------------------------------------------------------------------------------------------
             * Your Citi username and password will not be affected.
             * -------------------------------------------------------------------------------------------------------------------
             * We’re sorry. We can’t display detailed account information for closed accounts.
             */
            /*
             * We're sorry, only primary account holders can access ThankYou® Rewards.
             * As an Authorized User, you continue earning points every time you use your Sears MasterCard.
             * The primary account holder can view their ThankYou® Rewards balance any time and redeem them for amazing rewards and experiences.
             */
            /*
             * ThankYou.com is currently unavailable for this User ID. Please visit Citibank® Online or Citicards.com
             * or SearsCard.com to learn more about your account status.
             */
            /*
             * We are sorry. we had an issue processing your request.
             * To access ThankYou, please go directly to thankyou.com
             */
            if ($message = $this->waitForElement(WebDriverBy::xpath("
                //h4[contains(text(), 'Having trouble signing on? Please try again or')] | //span[@id = 'cbolui-iconDomID-Red Error-iconText' and contains(text(), 'Having trouble signing on? Please try again or')]
                | //span[@class = \"dashboardFailSpanMsg\" and contains(text(), \"We've encountered an issue and are working to fix the issue\")]
                | //td[@class = \"MTxtBold\" and contains(text(), \"I am sorry ...the page you requested cannot be found on this server.\")]
                | //h3[contains(text(), 'Important: You must deactivate your ThankYou.com username and password')]
                | //h3[contains(text(), 'Your Citi username and password will not be affected.')]
                | //p[contains(text(), 'We are sorry. we had an issue processing your request.')]
                | //span[contains(text(), \"We’re sorry. We can’t display detailed account information for closed accounts.\")]
                | //div[contains(text(), \"We're sorry, only primary account holders can access ThankYou\")]
                | //h4[contains(., 'is currently unavailable for this User ID. Please visit')]
                | //span[@class = \"strong\" and contains(text(), \"We've encountered an issue\")]
        "), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            // Need to create security questions
            if ($this->waitForElement(WebDriverBy::id('cmlink_CyotaCreateQuestionsIntr'), 0) && $this->waitForElement(WebDriverBy::xpath('//span[contains(., "please create your Security Questions")]'), 0)) {
                $this->throwProfileUpdateMessageException();
            }
            // The email address you provided has been flagged as undeliverable.
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The email address you provided has been flagged as undeliverable.')]")) {
                throw new CheckException("Thank You Network website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }
            // We weren't able to find any accounts that matched your profile information.
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "We weren\'t able to find any accounts that matched your profile information.")]')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->FindSingleNode('//p[contains(text(), "For your security, online access has been blocked.")]')) {
                throw new CheckException("For your security, online access has been blocked.", ACCOUNT_LOCKOUT);
            }

            if ($message = $this->http->FindSingleNode('//p[contains(text(), "For security reasons, we cannot allow you to proceed. If you require assistance, ")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR/*ACCOUNT_LOCKOUT*/);
            }

            if ($message = $this->http->FindSingleNode("
                    //div[contains(@class, 'critical')]//span[@class = 'strong']
                    | //div[contains(@class, 'input-error')]/span[@class = 'validation-message-danger']
                ")
            ) {
                $this->logger->error("[Error]: {$message}");

                if (
                    strstr($message, 'The information you submitted does not match our records.')
                    || strstr($message, 'Your password must be at least 6 characters long')
                    || strstr($message, 'Your information doesn\'t match our records.')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (strstr($message, 'Trouble signing on?')) {
                    throw new CheckRetryNeededException(2, 0);
                }

                $this->DebugInfo = $message;

                return false;
            }

            // Looks like you are having trouble signing on. Please try again.
            if ($message = $this->http->FindSingleNode('//span[@class = "cbolui-icon_text" and contains(text(), "Looks like you are having trouble signing on. Please try again.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode('//h2[@class = "modal-header-title" and contains(text(), "Sorry, Your Account is Locked")]')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            /*
             * You should have received your new Citibank(r) ATM/Debit Card by now. Once you have entered the required information and
             * activated your new ATM/Debit Card, you will be able to access the full functionality of Citibank Online.
             */
            if ($message = $this->http->FindPreg("/<p>(You should have received your new Citibank\(r\) ATM\/Debit Card by now\.\s*Once you have entered the required information and activated your new ATM\/Debit Card, you will be able to access the full functionality of Citibank Online\.)\s*<\/p>/")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Update your Citibank online account with your new ATM/Debit Card or Credit Card
            if ($this->http->FindSingleNode("
                //strong[contains(text(), 'Update your Citibank online account with your new ATM/Debit Card or Credit Card')]
                | //span[@class = \"jrsintroText\" and contains(text(), \"Please read the Citi Online Banking User Agreement carefully\")]
                | //*[self::div or self::h2][contains(@class, \"head-padding\") and contains(text(), \"Activate Your Card\")]
                | //h2[contains(text(), 's Time for a New Password.')]
            ")
                || strstr($this->http->currentUrl(), 'profile-update/change-password')
            ) {
                $this->throwProfileUpdateMessageException();
            }
            // Continue to Limited Site
            if ($this->waitForElement(WebDriverBy::xpath('//span[@id = "userLogin"]//p[contains(text(), "If you haven\'t received your new  Citibank(r) ATM/Debit card yet, you can access a limited version of Citibank Online.")]'), 0)) {
                $this->throwProfileUpdateMessageException();
            }
            /**
             * General Error.
             *
             * We've had a problem processing your request.
             * Please call the number on the back of your card for assistance.
             */
            if ($this->waitForElement(WebDriverBy::xpath("//font[@color = 'red' and contains(., 'General Error')]"), 0)
                && ($message = $this->http->FindPreg("/We've had a problem processing your request\./"))) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            // We're very sorry. AccountOnline is temporarily unavailable. Please stop by later.
            if ($message = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We\'re very sorry. AccountOnline is temporarily unavailable. Please stop by later.")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");

            // retries
            if ($currentUrl == 'https://online.citibank.com/US/JPS/portal/Index.do?userType=tyLogin'
                && $this->waitForElement(WebDriverBy::xpath('//img[@class = "errorIconPlacement"]'))) {
                throw new CheckRetryNeededException(3, 7);
            }

            if (($topNavAccounts = $this->waitForElement(WebDriverBy::xpath("//li[@id = 'topNavAccounts']/a | //button[contains(text(), 'View Accounts')]"), 0))
                && (stristr($currentUrl, 'goallpaperless/flow.action')
                    // todo: debug
                    || stristr($currentUrl, 'portal/Unauthorized.do')
                    || stristr($currentUrl, 'fraud-interstitial/high-fraud-alert')
                )
            ) {
                $topNavAccounts->click();
                $signOff = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Welcome,')]"), 7);
                $this->saveResponse();

                if (parent::getName()) {
                    $this->citiBank = true;

                    return true;
                }
            }

            // hard code: user not member of thankyou
            if (
                (
                    (
                        $currentUrl == 'https://online.citibank.com/US/JPS/portal/Index.do?userType=tyLogin'
                        || $currentUrl == 'https://online.citi.com/US/JPS/portal/Index.do?userType=tyLogin'
                        || $currentUrl == 'https://www.citi.com/credit-cards/citi.action'
                        || $currentUrl == 'https://online.citi.com/US/ag/error'// AccountID: 2589887
                        || strstr($currentUrl, 'https://online.citi.com/US/login.do?JFP_TOKEN=')
                        || $currentUrl == 'https://online.citi.com/homepage'
                        || strstr($currentUrl, 'https://www.citi.com/?JFP_TOKEN=')
                    )
                    && in_array($this->AccountFields["Login"], [
                        'dbcrblack1991',
                        'robynd610',
                        'benedix88',
                        'shaumik79', // AccountID: 2589887
                    ])
                )
                || $currentUrl == 'https://online.citi.com/US/JPS/portal/Index.do?userType=tyLogin&errorMode=dashboard&errorCode=access_denied_sears_ty'
                || in_array($this->AccountFields["Login"], [
                    'clapclap1per',
                    'benedix88', // AccountID: 3146223, https://online.citi.com/US/ag/sign-off?sessionTimeout=true
                ])
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 1);
        }

        if ($currentUrl == 'https://www.citi.com/citi-partner/thankyou/login?userType=tyLogin&locale=en_US&TYNewUser=false&TYForgotUUID=false&TYMigration=&ErrorCode=&cmp=null') {
            throw new CheckRetryNeededException(3, 3);
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        // Identification Code
        if (!$this->http->FindSingleNode("//h2[contains(text(), 'Please Confirm Your Identity')]")
            || !$this->http->ParseForm("formPhoneNumbers")) {
            return false;
        }
        // Phone
        $phone = $this->http->FindSingleNode("(//select[@id = 'phonenumber']/option[@value != '' and @value != 'nophone']/@value)[1]");
        $question = self::IDENTIFICATION_CODE_MSG;
        $this->logger->debug("phone: " . $phone);
        $this->logger->debug("question: " . $question);

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question);

            return false;
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($step == "SecurityCheckpoint") {
            return $this->processSecurityCheckpoint();
        }

        if ($step == "SecurityCheckpointV2") {
            return $this->processSecurityCheckpointV2();
        }

        if ($step == "IdentificationCode") {
            return $this->processIdentificationCodeEntering();
        }

        if ($step == "IdentificationCodeThankyou") {
            return $this->processIdentificationCodeThankyouEntering();
        }

        if ($step == "VerificationCode") {
            return $this->processVerificationCodeThankyouEntering();
        }

        if ($this->Question == "Enter Your Identification Code") {
            $input = $this->waitForElement(WebDriverBy::xpath('//input[@name = "otpMasked"]'));
            $verify = $this->waitForElement(WebDriverBy::xpath('//input[@id = "cmlink_VerifyDisabled_Link"]'));

            if ($input && $verify) {
                $input->sendKeys($this->Answers["Enter Your Identification Code"]);
            } else {
                return false;
            }
            $this->logger->debug('click "Verify"');
            $verify->click();

            return true;
        }

        $input = $this->waitForElement(WebDriverBy::xpath('//input[@name = "otpValue"]'));
        $input->sendKeys($this->Answers[self::IDENTIFICATION_CODE_MSG]);

        $this->http->SetInputValue("otpValue", $this->Answers[$this->Question]);
        // TODO: Notifications
        $this->sendNotification("thankyou. Code was entered");
//        if (!$this->http->PostForm())
//            return false;
//        $this->http->Log("the code was entered");
//        // remove Identification Code
//        unset($this->Answers[$this->Question]);
        // Invalid Code. Please re-enter.
        if ($error = $this->http->FindSingleNode("//p[contains(text(), 'The Identification Code you entered is incorrect.')]")) {
            $this->logger->error(">>> Invalid Code. Please re-enter.");
            $this->AskQuestion($this->Question, $error);

            return false;
        }
        // Your Identification Code Has Expired
        if ($error = $this->http->FindSingleNode("//h2[contains(text(), 'Your Identification Code Has Expired')]")) {
            $this->logger->error(">>> Your Identification Code Has Expired");
            $this->AskQuestion($this->Question, $error);

            return false;
        }
        // click "Continue"
        if ($link = $this->http->FindSingleNode("//a[contains(text(), 'CONTINUE')]/@href")) {
            $this->logger->debug("click \"Continue\"");
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
            $this->saveResponse();
        }
        // For your protection, your account has been locked
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'For your protection, your account has been locked')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return true;
    }

    public function parseCitiBank()
    {
        $this->logger->notice(__METHOD__);

        $this->AccountFields['Login2'] = 'USA';
        parent::Parse();

        return;

        $hasAccountAA = false;

        if (
            // Total Available Citi ThankYou®  Points: Not Available
        $this->waitForElement(WebDriverBy::xpath("
                //tr[contains(., 'ThankYou® Rewards')]/following-sibling::tr[1]//div[contains(text(), 'This information is temporarily unavailable.')]
                | //div[span[contains(., 'Total Available Citi ThankYou®  Points:')]]/following-sibling::div[1]//span[contains(text(), 'Not Available')]
            "), 10)) {
            $this->SetBalanceNA();
        }
        $this->saveResponse();
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//strong[@id = 'user_name'] | //div[@id = 'welcomeBarHeadline'] | //div[contains(@class, 'cA-ada-welcomeBarTitleWrapper')] | //div[contains(@class, 'bgwelcome')]", null, true, "/Welcome(?: back|)\,?\s*([^<]+)/ims")));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // multiple cards
            $this->logger->notice("multiple cards");
            $multipleAccounts = $this->http->XPath->query("
                    //div[@id = 'accountsPanelInnerContainer']//a[@id = 'cmlink_AccountNameLink']
                    | //div[@id = 'cardsAccountPanel']//a[contains(@id, 'cA-spf-accountNameLink-')]
                    | //div[contains(@class, 'cA-mrc-accountName')]//a[
                            contains(@class, 'ng-star-inserted')
                            and contains(@class, 'chevron-link')
                            and not(
                                contains(@href, 'linkaccounts')
                                or contains(@href, 'offersforyou')
                                or contains(@href, 'entryPoint')
                                or contains(@href, 'docmanagement/ecom')
                                or contains(@href, 'payments/crecarpay')
                            )
                    ]
                    | //div[contains(@class, 'cA-mrc-accountWrapper')]//a[
                            contains(@class, 'chevron-link')
                            and not(
                                contains(@href, 'linkaccounts')
                                or contains(@href, 'offersforyou')
                                or contains(@href, 'entryPoint')
                                or contains(@href, 'docmanagement/ecom')
                                or contains(@href, 'payments/crecarpay')
                            )
                    ]
            ");
            $this->logger->debug("Total {$multipleAccounts->length} cards were found");
            $cardsInfo = [];

            for ($i = 0; $i < $multipleAccounts->length; $i++) {
                $link = $this->http->FindSingleNode("@href", $multipleAccounts->item($i), true, "/javascript:link\(\'([^\']+)/");

                if (!$link) {
                    $link = $this->http->FindSingleNode("@href", $multipleAccounts->item($i));
                }
                $this->logger->debug("Link -> " . $this->http->FindSingleNode("@href", $multipleAccounts->item($i)));
                $this->logger->debug("Link -> {$link}");
                $displayName = Html::cleanXMLValue($multipleAccounts->item($i)->nodeValue);
                $this->logger->debug("displayName -> {$displayName}");

                if (isset($link, $displayName)) {
                    $cardsInfo[] = [
                        "link"        => $link,
                        "displayName" => $displayName,
                    ];
                }
            }// for ($i = 0; $i < $multipleAccounts->length; $i++)
            unset($displayName);

            foreach ($cardsInfo as $card) {
                $this->logger->debug(var_export($card, true), ['pre' => true]);
                $this->logger->debug("Go to -> {$card['link']}");
                $this->increaseTimeLimit();

                try {
                    $this->http->GetURL("https://online.citibank.com" . $card['link']);
                } catch (WebDriverException $e) {
                    $this->logger->error("exception: " . $e->getMessage());

                    throw new CheckRetryNeededException(3, 0);
                }
                // Total Available Citi ThankYou® Points
                $balance = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'cT-rewardsValue'] | //span[@id = 'availableRewardBalance']"), 7);
                $totalCitiThankYouPoints = $this->waitForElement(WebDriverBy::xpath("//div[//img[@title = 'ThankYou']]/following-sibling::div[@class = 'cA-spf-rewardsWrapper']//div[@class = 'cA-spf-rewardsValue']"), 0);
                $this->saveResponse();
                $displayName = str_replace('Â®', '®', $card['displayName']);
                $code = 'thankyou' . str_replace([' ', '/', '+', "'", "%"], ['', '', 'Plus', '', 'Percent'], $displayName);

                if (isset($balance, $displayName)
                    && !(stristr($displayName, 'Advantage') || stristr($displayName, 'AA card') || stristr($displayName, 'citi aa'))
                ) {
                    $this->AddSubAccount([
                        'Code'        => $code,
                        'DisplayName' => $displayName,
                        'Balance'     => $balance->getText(),
                    ]);
                    // detected cards
                    $cards[] = [
                        'Code'            => $code,
                        'DisplayName'     => $displayName,
                        "CardDescription" => C_CARD_DESC_ACTIVE,
                    ];
                }// if (isset($balance, $displayName))
                elseif (isset($displayName)) {
                    $this->logger->notice(">>> Skip card without balance");
                    $cardDescription = C_CARD_DESC_DO_NOT_EARN;

                    if (stristr($displayName, 'Advantage') || stristr($displayName, 'AA card')) {
                        $cardDescription = C_CARD_DESC_AA;
                    }

                    if (
                        stristr($displayName, 'Advantage')
                        || stristr($displayName, 'AA card')
                        || stristr($displayName, 'citi aa')
                    ) {
                        $hasAccountAA = true;
                        $cardDescription = C_CARD_DESC_AA;
                    }

                    if (stristr($displayName, 'Hilton')) {
                        $cardDescription = C_CARD_DESC_HHONORS;
                    }
                    // detected cards
                    $cards[] = [
                        'Code'            => $code,
                        'DisplayName'     => $displayName,
                        "CardDescription" => $cardDescription,
                    ];
                } else {
                    $this->logger->error(">>> Wrong cards");
                }
            }// foreach ($cards as $card)

            if (!empty($cards)) {
                $this->SetBalanceNA();
                $this->SetProperty("DetectedCards", $cards);
            }// if (!empty($cards))
            // Total Available Citi ThankYou®  Points
            if (!isset($this->Properties['SubAccounts']) && !empty($totalCitiThankYouPoints)) {
                unset($this->Properties['DetectedCards']);
                $this->SetBalance($totalCitiThankYouPoints->getText());
            }// if (!isset($this->Properties['SubAccounts']) && !empty($totalCitiThankYouPoints))
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($totalCitiThankYouPoints = $this->waitForElement(WebDriverBy::xpath("//div[//img[@title = 'ThankYou']]/following-sibling::div[@class = 'cA-spf-rewardsWrapper']//div[@class = 'cA-spf-rewardsValue'] | //div[@class = 'cA-mrc-rewardsContainer' and //img[@src = '/JRS/images/logo_TY_144.png']]//div[contains(@class, 'cA-mrc-rewardsvalue')]"), 0)) {
                $this->SetBalance($totalCitiThankYouPoints->getText());
            } else {
                if (!$this->SetBalance($this->http->FindSingleNode("//div[contains(@class, \"cA-mrc-rewardsContainer\") and contains(., \"Total Available ThankYou® Points\")]//div[contains(@class, 'cA-mrc-rewardsvalue')]"))) {
                    $this->SetBalance(
                        $this->http->FindSingleNode("
                            //div[contains(@class, \"cA-mrc-rewardsContainer\") and contains(., \"Cash Rewards Balance\") or contains(., \"Your Dividend Dollars:\")]//div[contains(@class, 'cA-mrc-rewardsvalue')]
                            | //div[contains(@class, 'reward-points-earned') and contains(., 'Cash Rewards Balance') or contains(., 'Your Dividend Dollars:')]/following-sibling::div[contains(@class, 'rewards-value')]
                        ")
                    );
                    $this->SetProperty("Currency", "$");
                }
                // AccountID: 3653923
                // AccountID: 1023371
                // AccountID: 444600
                // AccountID: 105032
                // AccountID: 2413769
                $detectedCardsOnly = $this->http->XPath->query("//div[@id = 'cardsAccountPanel']//div[contains(@id, 'creditCardAccountPanel')]");
                $this->logger->debug("[v.1]: Total {$detectedCardsOnly->length} cards were found");

                if ($detectedCardsOnly->length == 0) {
                    $detectedCardsOnly = $this->http->XPath->query("//div[contains(@id, 'accountInfoPanel')] | //div[contains(@id, 'creditCardAccountPanel-')] | //div[contains(@class, 'cA-ada-accountDetailsPanel')] | //div[@class = 'cA-mrc-accountName']");
                    $this->logger->debug("Total {$detectedCardsOnly->length} cards were found");
                }

                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    if ($detectedCardsOnly->length == 1 || $detectedCardsOnly->length == 2 || $detectedCardsOnly->length == 3 || $detectedCardsOnly->length == 5) {
                        $this->SetBalanceNA();
                    } // Sorry, we can't currently load your rewards information. Please try again later.
                    elseif ($message = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Sorry, we can\'t currently load your rewards information. Please try again later.")]'), 0)) {
                        $this->SetWarning($message->getText());
                    } elseif (
                        // AccountID: 3049572, no any accounts in profile
                        $this->AccountFields['Login'] == 'clapclap1per'
                        && !empty($this->Properties['Name'])
                    ) {
                        $this->SetBalanceNA();
                    }
                }
            }
        }
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);

        if ($this->citiBank || $this->loginSuccessful()) {
            try {
                $this->parseCitiBank();
            } catch (NoSuchDriverException | UnknownServerException $e) {
                $this->logger->error("exception: " . $e->getMessage());

                throw new CheckRetryNeededException(3, 1);
            }

            return;
        }

        // Name
        $this->SetProperty("Name", beautifulName(
            $this->http->FindSingleNode("//div[@id = 'account-menu-logged-in']//span[contains(text(), 'Hi')]", null, true, "/Hi\s*([^<]+)/")
            ?? $this->http->FindSingleNode("//li[@id = 'accountDropdown']//span[@class = 'expanded']")
            ?? $this->http->FindSingleNode("//span[@class = 'user-initials']")
        ));

        $multipleAccounts = $this->http->XPath->query('//tr[contains(@class, "select-card-element")]');
        $this->logger->debug("Total {$multipleAccounts->length} cards were found");

        if ($multipleAccounts->length > 0) {
            $cards = [];
            $mainBalance = null;

            for ($i = 0; $i < $multipleAccounts->length; $i++) {
                $balance = $this->http->FindSingleNode('.//div[contains(@class, "card-element-total-available-points")]/span', $multipleAccounts->item($i));
                $displayName = $this->http->FindSingleNode("(.//div[contains(@class, 'card-element-info-label')])[1]", $multipleAccounts->item($i));
                $accountNumber = $this->http->FindSingleNode(".//div[contains(@class, 'card-element-account-number')]", $multipleAccounts->item($i));

                if (isset($balance, $displayName, $accountNumber)) {
                    if (is_null($mainBalance)) {
                        $mainBalance = str_replace(',', '', $balance);
                    } else {
                        $mainBalance += str_replace(',', '', $balance);
                    }
                    $this->logger->debug("[Balance]: {$mainBalance}");

                    $this->AddSubAccount([
                        'Code'              => 'thankyou' . $accountNumber,
                        'DisplayName'       => $displayName,
                        'Balance'           => $balance,
                        'AccountNumber'     => $accountNumber,
                        'BalanceInTotalSum' => true,
                    ]);
                    // detected cards
                    $cards[] = [
                        'Code'            => 'thankyou' . $accountNumber,
                        'DisplayName'     => $displayName,
                        "CardDescription" => C_CARD_DESC_ACTIVE,
                    ];
                }// if (isset($balance, $displayName))
                elseif (isset($displayName, $accountNumber)) {
                    $this->logger->notice(">>> Skip card without balance");
                    // detected cards
                    $cards[] = [
                        'Code'            => 'thankyou' . $accountNumber,
                        'DisplayName'     => $displayName,
                        "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                    ];
                } else {
                    $this->logger->error(">>> Wrong cards");
                }
            }// for ($i = 0; $i < $nodes->length; $i++)

            if (!empty($cards)) {
                $this->SetBalance($mainBalance);
                $this->SetProperty("DetectedCards", $cards);
            }
        } else {
            // Balance - Points
            $this->SetBalance(
                $this->http->FindSingleNode("//div[@id = 'account-menu-logged-in']//span[@class='points']")
                ?? $this->http->FindSingleNode("//div[@id = 'mobile-header-account-info']//span[@class='points']")
                ?? $this->http->FindSingleNode('//p[contains(@class, "points-available")]')
            );
            // ThankYou Account
            $this->SetProperty("AccountNumber",
                $this->http->FindSingleNode("//p[@id = 'thankyou-account-num']")
                ?? $this->http->FindPreg('/"userMembershipID":"([^"]+)/')
            );
        }

        // multiple cards
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            unset($this->Properties['Name']);
            $multipleAccounts = $this->http->XPath->query("//div[@id = 'form_account_select-2']");
            $this->logger->debug("Total {$multipleAccounts->length} cards were found");
            $cards = [];
            $mainBalance = null;

            for ($i = 0; $i < $multipleAccounts->length; $i++) {
                $balance = $this->http->FindSingleNode("b", $multipleAccounts->item($i), true, '/[\d\.\,]+/ims');
                $displayName = str_replace('Â®', '®', $this->http->FindSingleNode("text()[2]", $multipleAccounts->item($i)));
                $accountNumber = $this->http->FindSingleNode("text()[3]", $multipleAccounts->item($i), true, "/#\s*([^<]+)/");

                if (isset($balance, $displayName, $accountNumber)) {
                    if (is_null($mainBalance)) {
                        $mainBalance = str_replace(',', '', $balance);
                    } else {
                        $mainBalance += str_replace(',', '', $balance);
                    }
                    $this->logger->debug("[Balance]: {$mainBalance}");

                    $this->AddSubAccount([
                        'Code'              => 'thankyou' . $accountNumber,
                        'DisplayName'       => $displayName,
                        'Balance'           => $balance,
                        'AccountNumber'     => $accountNumber,
                        'BalanceInTotalSum' => true,
                    ]);
                    // detected cards
                    $cards[] = [
                        'Code'            => 'thankyou' . $accountNumber,
                        'DisplayName'     => $displayName,
                        "CardDescription" => C_CARD_DESC_ACTIVE,
                    ];
                }// if (isset($balance, $displayName))
                elseif (isset($displayName, $accountNumber)) {
                    $this->logger->notice(">>> Skip card without balance");
                    // detected cards
                    $cards[] = [
                        'Code'            => 'thankyou' . $accountNumber,
                        'DisplayName'     => $displayName,
                        "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                    ];
                } else {
                    $this->logger->error(">>> Wrong cards");
                }
            }// for ($i = 0; $i < $nodes->length; $i++)

            if (!empty($cards)) {
                $this->SetBalance($mainBalance);
                $this->SetProperty("DetectedCards", $cards);
            }

            // AccountID: 3049572, Your request is being processed.
            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && $this->AccountFields['Login'] == 'RENEPLATA'
                && $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Your request is being processed.")]'), 0)
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        //Get the nearest expiration date and number of points that will dissapear
        try {
            $this->http->GetURL('https://www.thankyou.com/pointsSummary.htm?src=TYUSENG');
//            // no points to expire
//            $message = $this->http->FindSingleNode('(//p[contains(text(), "You do not have points expiring in the next 60 days or less.")])[1]');
//            $this->logger->notice($message);
            $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Expiration Date')]"), 15);
            $this->saveResponse();
        } catch (UnknownServerException | WebDriverCurlException | WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->SetBalance($this->http->FindSingleNode('//li[@id = "accountDropdown"]//span[@class="points"] | //p[contains(@class, "points-available")]'));
        }

        // no points to expire
        $message = $this->http->FindSingleNode('(//p[contains(text(), "You do not have points expiring in the next 60 days or less.")])[1]');
        $this->logger->notice($message);

        try {
            $this->http->GetURL('https://www.thankyou.com/expiringPoints.htm?src=TYUSENG');
            $this->http->GetURL('https://www.thankyou.com/pointsExpiringPaginationAjax.htm?fromRow=0&count=20&sortby=&direction=&accountKey=all&pageNum=1');
            $this->saveResponse();
        } catch (NoSuchDriverException | UnknownServerException $e) {
            $this->logger->error("[Exception]: {$e->getMessage()}");
        }
        // Find expiration dates values
        $response = $this->http->FindSingleNode("//body/text()[1]");
        $this->logger->debug(">>{$response}<<");
        $response = $this->http->JsonLog($response);
        $expiringPointsDetailList = $response->expiringPointsDetailList ?? [];
        $totalRecords = $response->totalRecords ?? null;
        $this->logger->debug("Total {totalRecords} nodes were found");
        $nearestExpirationDate = false;
        $nearestExpirationPoints = false;

        foreach ($expiringPointsDetailList as $expiringPointsDetails) {
            $currentAssocDate = strtotime($expiringPointsDetails->pointsExpirationDate);
            $this->logger->debug("Date: {$currentAssocDate}");

            if ($currentAssocDate) {
                if (!$nearestExpirationDate) {
                    $nearestExpirationDate = $currentAssocDate;
                    $nearestExpirationPoints = $expiringPointsDetails->points;
                } elseif ($currentAssocDate < $nearestExpirationDate) {
                    $nearestExpirationDate = $currentAssocDate;
                    $nearestExpirationPoints = $expiringPointsDetails->points;
                }
            }// if ($currentAssocDate)
        }// for ($i = 0; $i < $nodes->length; $i++)
        //Set the date and points
        if ($nearestExpirationDate && $nearestExpirationPoints) {
            $this->SetExpirationDate($nearestExpirationDate);
            $this->SetProperty('NumberOfExpirePoints', $nearestExpirationPoints);
        }// if ($nearestExpirationDate && $nearestExpirationPoints)
        // look for 'At this time, you have no points expiring within 90 days.'
        elseif ($message) {
            $this->ClearExpirationDate();
        }
    }

    protected function processVerificationCode()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question (Verification Code)', ['Header' => 3]);

        if (
            !($phone = $this->waitForElement(WebDriverBy::xpath("//label[@for = 'phoneNumbers_0']"), 0))
            || !($phoneText = $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Text']/preceding-sibling::label"), 0))
            || !($agreement = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'otpAgreement']/following-sibling::label"), 0))
        ) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return false;
        }

        $phone->click();
        $phoneText->click();
        $this->saveResponse();
        $agreement->click();

        $contBtn = $this->waitForElement(WebDriverBy::xpath("//button[@aria-label = 'Continue to the next step.']"), 0);
        $this->saveResponse();

        if (!$contBtn) {
            $this->logger->error("something went wrong");

            return false;
        }

        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $contBtn->click();
        // Enter Your Identification Code
        $q = $this->waitForElement(WebDriverBy::xpath('//h3[contains(text(), "Enter your verification code below to proceed.")]'), 10);
        $this->saveResponse();

        if (!$q) {
            $this->logger->error("question not found");

            return false;
        }

        $question = $q->getText();
        $this->holdSession();
        $this->AskQuestion($question, null, "VerificationCode");

        return false;
    }

    protected function processVerificationCodeThankyouEntering()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[Question]: {$this->Question}");
        $verificationCode = $this->waitForElement(WebDriverBy::xpath('//input[@name = "verificationCode"]'), 0);
        $submitBtn = $contBtn = $this->waitForElement(WebDriverBy::xpath("//button[@aria-label = 'Continue to the next step.']"), 0);
        $this->saveResponse();

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        if (!$verificationCode || !$submitBtn) {
            $this->logger->error("something went wrong");

            return false;
        }

        $verificationCode->sendKeys($answer);
        $submitBtn->click();

        sleep(3);
        // The code you entered does not match our records or has expired. For your protection, multiple unsuccessful attempts will result in a temporary account lockout.
        $error = $this->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'The code you entered does not match our records or has expired.')]"), 0);
        $this->saveResponse();

        if (!empty($error)) {
            $error = $error->getText();
            $this->logger->notice("error: " . $error);
            $this->holdSession();
            unset($this->Answers[$this->Question]);
            $this->AskQuestion($this->Question, $error, "VerificationCode");

            return false;
        }// if (!empty($error))

        $this->logger->debug("[Current URL]: " . $this->http->currentUrl());
        sleep(10); //todo
        $this->saveResponse();

        return true;
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions)) || empty($region)) {
            $region = 'Citibank';
        }

        return $region;
    }
}
