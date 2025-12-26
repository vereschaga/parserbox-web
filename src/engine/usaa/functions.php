<?php

class TAccountCheckerUsaa extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public const TEXT_SECURITY_CODE = "Please enter the email address listed in your profile.";
    public const ASK_SECURITY_CODE = "Please enter your temporary passcode.";
    public const ASK_TEXT_SECURITY_CODE = "Please enter your Security Code.";

    private $key = null;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerUsaaSelenium.php";

        return new TAccountCheckerUsaaSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency']) && strtolower($properties['Currency']) == 'cash') {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL("https://www.usaa.com/inet/ent_home/CpHome?action=INIT");
        // Account Locked
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Account Locked')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($this->http->currentUrl() == 'https://www.usaa.com/inet/ent_auth_secques/changeforced?acf=1') {
            throw new CheckException("USAA (Rewards) website is asking you to update your account, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Temporary Passcode')]")) {
            $this->sendTemporaryPasscode();
        }

        if (!$this->parseQuestion() || $this->http->FindSingleNode("//div[contains(text(), 'GET SECURITY CODE')]")) {
            return false;
        }
        // Information about your income and accounts
        if ($this->http->FindPreg("/<h1 class=\"font-large-label\">Personal Information<\/h1>/")) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->http->FindSingleNode("(//a[contains(@href, 'logoff')]/@href)[1]") !== null;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields['Login2']['InputType'] = 'password';
        $arFields['Login2']['RegExp'] = '/^\d\d\d\d$/ims';
        $arFields['Login2']['RegExpErrorMessage'] = 'Please enter a valid four-digit numeric PIN';
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->ParseMetaRedirects = false;
        $this->http->GetURL("https://www.usaa.com/inet/ent_logon/Logon?redirectjsp=true");

        if ($this->attempt == 1) {
            $this->key = 1000;
            $this->selenium("https://www.usaa.com/inet/ent_logon/Logon?redirectjsp=true");
        } else {
            $this->key = $this->sendSensorData();
        }

        $this->http->GetURL("https://www.usaa.com/inet/ent_home/CpHome?action=INIT&jump=jp_default");

        if ($this->http->ParseForm("frmBrowserProfile")) {
            $this->http->PostForm();
        }

        if (!$this->http->ParseForm("Logon")) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://www.usaa.com/inet/ent_logon/j_security_check';
        $this->http->SetInputValue("j_username", $this->AccountFields['Login']);
        $this->http->SetInputValue("j_password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("CSRFToken", $this->http->FindPreg("/addCSRFToken\('([^\']+)/"));
        $this->http->SetInputValue("fp_userlang", "undefined");
        $this->http->SetInputValue("fp_display", "24|1440|900|832");
        $this->http->SetInputValue("fp_lang", "lang=en-US|syslang=|userlang=");
        $this->http->SetInputValue("fp_syslang", "undefined");
//        $this->http->SetInputValue("fp_timezone", "5");
        $this->http->SetInputValue("fp_browser", "mozilla/5.0 (macintosh; intel mac os x 10_15_4) applewebkit/537.36 (khtml, like gecko) chrome/80.0.3987.132 safari/537.36|5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36|MacIntel");
        $this->http->SetInputValue("risk_deviceprint", "version%3D3%2E5%2E1%5F4%26pm%5Ffpua%3Dmozilla%2F5%2E0%20%28macintosh%3B%20intel%20mac%20os%20x%2010%5F15%5F4%29%20applewebkit%2F537%2E36%20%28khtml%2C%20like%20gecko%29%20chrome%2F80%2E0%2E3987%2E132%20safari%2F537%2E36%7C5%2E0%20%28Macintosh%3B%20Intel%20Mac%20OS%20X%2010%5F15%5F4%29%20AppleWebKit%2F537%2E36%20%28KHTML%2C%20like%20Gecko%29%20Chrome%2F80%2E0%2E3987%2E132%20Safari%2F537%2E36%7CMacIntel%26pm%5Ffpsc%3D24%7C1536%7C960%7C880%26pm%5Ffpsw%3D%26pm%5Ffptz%3D5%26pm%5Ffpln%3Dlang%3Den%2DUS%7Csyslang%3D%7Cuserlang%3D%26pm%5Ffpjv%3D0%26pm%5Ffpco%3D1%26pm%5Ffpasw%3Dinternal%2Dpdf%2Dviewer%7Cmhjfbmdgcfjbbpaeojofohoefgiehjai%7Cinternal%2Dnacl%2Dplugin%26pm%5Ffpan%3DNetscape%26pm%5Ffpacn%3DMozilla%26pm%5Ffpol%3Dtrue%26pm%5Ffposp%3D%26pm%5Ffpup%3D%26pm%5Ffpsaw%3D1536%26pm%5Ffpspd%3D24%26pm%5Ffpsbd%3D%26pm%5Ffpsdx%3D%26pm%5Ffpsdy%3D%26pm%5Ffpslx%3D%26pm%5Ffpsly%3D%26pm%5Ffpsfse%3D%26pm%5Ffpsui%3D%26pm%5Fos%3DMac%26pm%5Fbrmjv%3D80%26pm%5Fbr%3DChrome%26pm%5Finpt%3D%26pm%5Fexpt%3D");
        $this->http->SetInputValue("authBarLogonUrl", "https://www.usaa.com/inet/ent_logon/Logon?redirectjsp=true&akredirect=true");

        return true;
    }

    public function checkProviderErrors()
    {
        $this->logger->notice(__METHOD__);
        // Our system is currently unavailable.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Our system is currently unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->FindPreg('/^\d\d\d\d$/ims', false, $this->AccountFields['Login2'])) {
            throw new CheckException("Please enter a valid four-digit numeric PIN", ACCOUNT_INVALID_PASSWORD);
        }
        sleep(3);

        if (!$this->http->PostForm(["Referer" => "https://www.usaa.com/inet/ent_logon/Logon?redirectjsp=true&akredirect=true"])) {
            return $this->checkErrors();
        }
        // Pardon Our Interruption
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Pardon Our Interruption')]")
            && $this->http->ParseForm(null, "//form[contains(@action, 'occupationDeepDiveForm')]")) {
            $this->http->SetInputValue("deepDiveInitialPanel:occupationLogonForm:nextButton", "x");
            $this->http->PostForm();
        }

        if (!$this->checkErrors()) {
            return false;
        }

        if (!$this->sendPin()) {
            // Text Security Code
            if ($this->http->FindSingleNode("//h1[contains(text(), 'Text Security Code')]")) {
                $this->sendTextSecurityCode();
            }
            // Temporary Passcode
            if ($this->http->FindSingleNode("//h1[contains(text(), 'Temporary Passcode')]")) {
                $this->sendTemporaryPasscode();
            }

            // For your protection, we cannot provide the email option because you have disabled this option in your security preferences.
            if (
                $this->http->FindSingleNode("//p[contains(text(), 'For your protection, we cannot provide the email option because you have disabled this option in your security preferences.')]")
            ) {
                $this->sendTemporaryPasscode(true);

                if ($this->http->FindSingleNode("//p[contains(text(), 'Please enter your phone number in your profile.')]")) {
                    throw new CheckException("We cannot send your temporary passcode to your e-mail addresses because you have disabled this option in your security preferences.", ACCOUNT_PROVIDER_ERROR);
                }
            }

            if ($this->http->FindSingleNode("//div[contains(text(), 'Where should we send the security code?')] | //div[contains(text(), 'GET SECURITY CODE')]")) {
                $this->parseQuestion();

                return false;
            }

            $this->logger->notice(">>> Provider error");
//            if ($this->http->FindPreg('/(meta http-equiv=\"refresh\" CONTENT=\"0;URL\=https&#58;\&#47;\&#47;www\.usaa\.com\&#47\;inet\&#47;ent_logon\&#47\;Logon\")/ims')) {
//                $this->http->GetURL("https://www.usaa.com/inet/ent_logon/Logon");
            if ($metaRedirect = $this->http->FindPreg('/meta http-equiv=\"refresh\" CONTENT=\"0;\s*URL\=([^\"]+)\"/ims')) {
                $this->logger->debug("meta redirect...");
                $this->http->GetURL(urldecode($metaRedirect));
                $originalUrl = $this->http->FindPreg("/var originalUrl = \"([^\"])/");
                $queryString = $this->http->FindPreg("/var queryString = \"([^\"])/");
                $this->logger->debug("Posting frmBrowserProfile form...");

                if ($this->http->ParseForm("frmBrowserProfile") && isset($originalUrl)) {
                    $this->http->FormURL = $originalUrl . $queryString;
                    $this->http->PostForm();
                }
            }// if ($metaRedirect = $this->http->FindPreg('/meta http-equiv=\"refresh\" CONTENT=\"0;\s*URL\=([^\"]+)\"/ims'))

            if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'According to our records, you have unresolved charges with us')])[1]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Pardon Our Interruption
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are experiencing technical difficulties')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->checkErrors();

            return false;
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Temporary Passcode')]")) {
            $this->sendTemporaryPasscode();
        }

        if (!$this->parseQuestion()) {
            return false;
        }

        $this->sendPin();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->CheckError($this->http->FindSingleNode("//strong[contains(text(), 'Select three security questions')]"), ACCOUNT_PROVIDER_ERROR);
        //# Password Recovery
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Password Recovery')]")
            // Reset Your PIN
            || $this->http->FindSingleNode("//h1[contains(text(), 'Reset Your PIN')]")
            /*
             * USAA is now required, by law, to obtain signed consent to continue communicating
             * with our members the way we have in the past.
             */
            || ($this->http->currentUrl() == 'https://www.usaa.com/inet/agreement_capture/consent/home'
                && $this->http->FindPreg("/USAA is now required, by law, to obtain signed\s*consent to continue communicating with our members the way we have in\s*the past/ims"))
            //# Need set up security questions
            || ($this->http->currentUrl() == 'https://www.usaa.com/inet/ent_auth_secques/changeforced')
            //# Documents to Sign Electronically
            || ($this->http->FindSingleNode("//iframe[@title = 'Esignature Documents' and @id = 'esignWicketIframe']/@src"))) {
            $this->throwProfileUpdateMessageException();
        }
        $error = $this->http->FindSingleNode("//label[@id = 'messageLoginErrorLabel']");

        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//div[@class = 'messageError relativeParent']");
        }

        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//p[@class = 'attention warning_logonError']", null, false);
        }

        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//p[@class = 'notice_logonError']", null, false);
        }

        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//span[@class = 'feedbackPanelERROR']", null, false);
        }

        if (isset($error)) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = $error;

            if ($this->http->FindPreg("/account has been locked/ims", false, $error)) {
                $this->ErrorCode = ACCOUNT_LOCKOUT;
            }

            return false;
        }

        // skip Account review
        if ($this->http->currentUrl() == 'https://www.usaa.com/my/information-review') {
            $this->logger->notice("skip Account review");
            $this->http->GetURL("https://www.usaa.com/inet/ent_aml_cdd_apps/CddApplication/?complete-talon-flow=true");

            if ($this->http->FindPreg("/<meta http-equiv=\"refresh\" content=\"0;URL=https:\/\/www\.usaa\.com\/my\/information-review\"\/>/")) {
                $this->throwProfileUpdateMessageException();
            }
        }

        // Logon Recovery
        if (($this->http->FindSingleNode("//h1[contains(text(), 'Logon Recovery')]")
            // Reset Your Password
            || $this->http->FindSingleNode("//h1[contains(text(), 'Reset Your Password')]"))
            && strstr($this->http->currentUrl(), 'event=forgotPassword')) {
            throw new CheckException("USAA (Rewards) website is asking you to recover your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        //# Please enter a valid four-digit numeric PIN.
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Enter Your PIN')]")
            && strstr($this->http->currentUrl(), 'https://www.usaa.com/inet/ent_auth_pin/?w')) {
            throw new CheckException("Please enter a valid four-digit numeric PIN.", ACCOUNT_INVALID_PASSWORD);
        }
        // Some of your identification information is incorrect. Your account has been locked.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Some of your identification information is incorrect. Your account has been locked.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        /*
         * According to our records, you have unresolved charges with us.
         * To obtain limited access to existing accounts you may log on by clicking proceed below.
         * In order to resolve your charges and gain access to usaa.com,
         * please call toll-free 1-800-331-2454. (2:5703)
         */
        $this->logger->debug("[CurrentURL] -> " . $this->http->currentUrl());

        if ($this->http->currentUrl() == 'https://www.usaa.com/inet/ent_logoff/https&#58') {
            throw new CheckException("According to our records, you have unresolved charges with us. To obtain limited access to existing accounts you may log on by clicking proceed below. In order to resolve your charges and gain access to usaa.com, please call toll-free 1-800-331-2454.", ACCOUNT_PROVIDER_ERROR);
        }

        // Skip update
        $form = $this->http->FindSingleNode("//form[contains(@action, './review?1-1.IFormSubmitListener-occupationLogonPanel-occupationForm&acf=2&flowExecutionKey=e1s1') and @method='post']/@id", null, true, "/^id[0-9a-z]+/");

        if ($this->http->FindPreg("/To comply with regulatory guidance and to aid in the prevention of money laundering, we are requesting your occupation information\./ims") && $this->http->ParseForm($form)) {
            $this->logger->notice("Skip update");
            $this->http->SetInputValue("navigationPanel:cancelDotComButton", "x");
            $this->http->PostForm();
        }
        // Information about your income and accounts
        if ($this->http->FindPreg("/Remind Me Later/") && $this->http->ParseForm("taskEntry")) {
            $this->logger->notice("Skip update");
            $this->http->FormURL = 'https://www.usaa.com/inet/ent_aml_cdd_apps/CddApplication/TaskEntry?0-1.IFormSubmitListener-taskentryForm&detour=start&detourId=IsAMLHighRiskMember&acf=2&flowExecutionKey=e1s1';
            $this->http->SetInputValue("taskEntryNext", "Next");
            $this->http->SetInputValue("taskEntry_hf_0", "");
            $this->http->PostForm();

            if ($this->http->FindPreg("/<h1 class=\"font-large-label\">Personal Information<\/h1>/")) {
                $this->throwProfileUpdateMessageException();
            }
        }
        // Occupation Information
        if (strstr($this->http->currentUrl(), 'https://www.usaa.com/inet/ent_memberprofile/OccupationInfoReview/review?1&detour=start&detourId=IsMemberOccupationInfoRequired&acf=2&flowExecutionKey=')) {
            $this->logger->notice("Skip profile update");
            $this->http->GetURL("https://www.usaa.com/inet/ent_home/CpHome?action=INIT&action=INIT&wa_ref=pri_auth_nav_home");
        }
        /*
         * We're sorry, but the usaa.com page or activity you requested is temporarily unavailable.
         * We apologize for any inconvenience. Please try again later.
         */
        if ($message = $this->http->FindSingleNode('//p[contains(., "page or activity you requested is temporarily unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         *  We're Unable to Complete Your Request
         *
         * According to our records you filed bankruptcy and we cannot collect certain debts.
         * To obtain access to your existing accounts you may log on by clicking proceed below.
         */
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "According to our records you filed bankruptcy and we cannot collect certain debts.")]')) {
            throw new CheckException("According to our records you filed bankruptcy and we cannot collect certain debts.", ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * We're Unable to Complete Your Request
         *
         * Due to past difficulties, we can no longer offer you access to usaa.com. If you feel that we have restricted your access in error, please call toll-free 1-800-922-9092.
         */
        if ($message = $this->http->FindSingleNode('//div[contains(@class, "msgError") and contains(., "Due to past difficulties, we can no longer offer you access to usaa.com.")]/text()[last()]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Information Review
         *
         * To better protect our membership, we need to collect pertinent information about the people we serve.
         * Please complete this questionnaire in order to proceed to your account.
         * If you've recently answered the questions, we need you to answer them again as we were unable to save your responses.
         */
        if ($this->http->FindSingleNode('//h2[contains(text(), "Information Review")]') && $this->http->ParseForm("taskEntry")) {
            $this->throwProfileUpdateMessageException();
        }

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "We are unable to send you a code due to a missing mobile phone number or email on file or you have disabled your ability to receive a code")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/var errorDescription = \"We’ve encountered a problem. We are looking into it./")) {
            $this->DebugInfo .= "sensor_data [key: {$this->key}] ";

            throw new CheckRetryNeededException(2, 7, "We’ve encountered a problem. We are looking into it. Please try again later.");
        }

        return true;
    }

    public function sendTemporaryPasscode($phone = false)
    {
        $this->logger->notice("sendTemporaryPasscode");
        // id1a_hf_0=
        if ($this->http->ParseForm(null, "//form[contains(@action, 'FormSubmitListener-deliveryForm')]")) {
            $this->http->SetInputValue(":submit", "Next");
            // email
            $this->http->SetInputValue("deliveryPanel:selfRecoveryTable:selfRecoveryTable_body:optionsRow:optionsRow_body:methodsGroup", "radio0");

            if ($phone === true) {
                $this->http->SetInputValue("deliveryPanel:selfRecoveryTable:selfRecoveryTable_body:optionsRow:optionsRow_body:methodsGroup", "radio1");
            }

            if ($this->http->PostForm()
                && $this->http->FindSingleNode("//div[contains(@id , 'id') and not(contains(@class, 'hidden')) and div[contains(@id , 'id')]//label[text() = 'Email Address']]")
                && $this->http->ParseForm(null, "//form[contains(@action, 'FormSubmitListener-deliveryForm')]")) {
                if (!isset($this->Answers[self::TEXT_SECURITY_CODE])) {
                    $this->AskQuestion(self::TEXT_SECURITY_CODE);

                    return false;
                }// if (!isset($this->Answers[self::TEXT_SECURITY_CODE]))
                else {
                    $this->http->SetInputValue("deliveryPanel:selfRecoveryTable:selfRecoveryTable_body:emailRow:border:border_body:input", $this->Answers[self::TEXT_SECURITY_CODE]);
                }
                $this->http->SetInputValue(":submit", "Next");
                $this->http->PostForm();

                if ($error = $this->http->FindSingleNode('//p[contains(text(), "We do not have the email address you entered on file. Please enter an email address from your profile.")]')) {
                    $this->AskQuestion(self::TEXT_SECURITY_CODE);

                    return false;
                }

                // parse Question
                if (!$this->parseQuestion()) {
                    return false;
                }
            }
        }
    }

    public function sendTextSecurityCode()
    {
        $this->logger->notice("sendTextSecurityCode");
        // link "Have a Code Sent by E-mail"
        if ($link = $this->http->FindSingleNode("//span[contains(text(), 'Have a Code Sent by E-mail')]/parent::a/@href")) {
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
            $this->parseQuestion();
        }// if ($link = $this->http->FindSingleNode("//span[contains(text(), 'Have a Code Sent by E-mail')]/parent::a/@href"))
        elseif (!$this->http->FindSingleNode("//h1[contains(text(), 'Temporary Passcode')]")) {
            $this->http->Log("sendTextSecurityCode to mobile");

            // send Text Security Code Again
            if ($this->http->FindSingleNode("//h1[contains(text(), 'Text Security Code')]")
                && $this->http->FindSingleNode("//span[contains(text(), 'Have It Sent Again')]")
                && !$this->http->ParseForm(null, "//form[contains(@action, 'FormSubmitListener-deliveryForm')]")) {
                $this->logger->notice("click 'Have It Sent Again'");
                $this->http->GetURL("https://www.usaa.com/inet/ent_auth_otc/otc/TextSecCodeSMSDeliveryPage");
            }

            if ($this->http->ParseForm(null, "//form[contains(@action, 'FormSubmitListener-deliveryForm')]")) {
                $this->http->SetInputValue(":submit", "Next");
                // Mobile Number
                $value = $this->http->FindSingleNode("//input[@name = 'deliveryPanel:selfRecoveryTable:selfRecoveryTable_body:selectPhone:selectPhone_body:mobileNumbersGroup' and following-sibling::label[contains(text(), 'Primary') and contains(text(), '***-***')]]/@value");

                if (!$value) {
                    $value = "radio0";
                }
                $this->http->SetInputValue("deliveryPanel:selfRecoveryTable:selfRecoveryTable_body:selectPhone:selectPhone_body:mobileNumbersGroup", $value);
                $this->http->FilterHTML = false;

                if ($this->http->PostForm()
                    && $this->http->FindSingleNode("//div[@class = 'label']//span[text() = 'Security Code']")
                    && $this->http->ParseForm(null, "//form[contains(@action, 'FormSubmitListener-tempCodeEntryForm')]")) {
                    // parse Question
                    if (!$this->parseQuestion()) {
                        return false;
                    }
                }
            }
        }// elseif (!$this->http->FindSingleNode("//h1[contains(text(), 'Temporary Passcode')]"))
    }

    public function sendPinV2()
    {
        $this->logger->notice("sending pin v2");
        // AccountID: 1536377, debug
        $this->http->setMaxRedirects(10);

        if ($this->http->FindSingleNode("//input[@name = 'table:row1:pin1']") === null) {
            return false;
        }
        $form = $this->http->FindSingleNode("//form[descendant::input[@name = 'table:row1:pin1']][@method='post']/@id", null, true, "/^id[0-9a-z]+/");

        if (!$this->http->ParseForm($form)) {
            return false;
        }
        $this->http->SetInputValue("table:row1:pin1", $this->AccountFields['Login2']);
        $this->http->Form["submitButton"] = "Next";
        $this->http->ParseMetaRedirects = true;

        if (!$this->http->PostForm()) {
            return false;
        }
        $this->http->ParseMetaRedirects = false;

        if (!$this->checkErrors()) {
            return false;
        }
        //# Please enter a valid four-digit numeric PIN
        if (($this->http->currentUrl() == 'https://www.usaa.com/inet/ent_auth_pin/?w:interface=:0:1:::'
                && $this->http->FindSingleNode("//h1[contains(text(), 'Enter Your PIN')]"))
            || ($this->http->currentUrl() == 'https://www.usaa.com/inet/ent_auth_pin/page/ForgotPinPage')) {
            throw new CheckException("Please enter a valid four-digit numeric PIN.", ACCOUNT_INVALID_PASSWORD);
        }

        return true;
    }

    public function sendPin()
    {
        $this->logger->notice("sending pin");

        if ($this->http->FindSingleNode("//input[@id = 'cppindatacontainer.verifypin']") === null) {
            return $this->sendPinV2();
        }

        if (!$this->http->ParseForm("CpEnterPinPage")) {
            return false;
        }
        $this->http->Form["cppindatacontainer.verifypin"] = $this->AccountFields['Login2'];
        $this->http->Form["PS_DYNAMIC_ACTION"] = "PsDynamicAction_[action]Update[/action][target][/target][context][/context]";

        if (!$this->http->PostForm()) {
            return false;
        }

        if (!$this->checkErrors()) {
            return false;
        }

        return true;
    }

    public function checkAnswers()
    {
        $this->logger->debug("state: " . var_export($this->State, true));

        if (isset($this->LastRequestTime)) {
            $timeFromLastRequest = time() - $this->LastRequestTime;
        } else {
            $timeFromLastRequest = SECONDS_PER_DAY * 30;
        }
        $this->logger->debug("time from last code request: " . $timeFromLastRequest);
//        if ($timeFromLastRequest > SECONDS_PER_DAY && count($this->Answers) > 0) {
//            $this->http->log("resetting answers, expired");
//            unset($this->Answers[$this->question]);
//        }
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        // Text Security Code
        if ($this->http->FindSingleNode("//div[contains(text(), 'We will only accept email addresses listed in your profile')]")) {
            $this->logger->debug("parseQuestion - Text Security Code");
            $question = self::TEXT_SECURITY_CODE;
            // getting id form
            $form = $this->http->FindSingleNode("//form[descendant::input[@name = 'deliveryPanel:selfRecoveryTable:emailInput:input' or @name = 'deliveryPanel:selfRecoveryTable:selfRecoveryTable_body:emailInput:emailInput_body:input']][@method='post']/@id", null, true, "/^id[0-9a-z]+/");
        }// if ($this->http->FindSingleNode("//div[contains(text(), 'We will only accept email addresses listed in your profile')]"))
        // ask Temporary Passcode
        elseif ($this->http->FindSingleNode("//p[contains(text(), 'Your temporary passcode has been sent')]")
                || $this->http->FindSingleNode("//h1[contains(text(), 'Enter Your Temporary Passcode')]")
                // Text Security Code to mobile
                || $this->http->FindSingleNode("//div[contains(text(), 'We have sent a security code to')]")
                || $this->http->FindSingleNode("//h1[contains(text(), 'Text Security Code')]")) {
            $this->logger->notice("ask Temporary Passcode");
            $question = self::ASK_SECURITY_CODE;
            // getting id form
            $form = $this->http->FindSingleNode("//form[descendant::input[@name = 'tempPasscodeTable:tempPasscodeTable_body:otcRow:otcRow_body:codeContainer:code']][@method='post']/@id", null, true, "/^id[0-9a-z]+/");
        }// if ($this->http->FindSingleNode("//p[contains(text(), 'Your temporary passcode has been sent.')]"))
        // Where should we send the security code?
        elseif ($this->http->FindSingleNode("//div[contains(text(), 'Where should we send the security code?')] | //div[contains(text(), 'GET SECURITY CODE')]")) {
            $this->logger->notice("ask Security code");

            $checker = clone $this;
            $this->http->brotherBrowser($checker->http);

            try {
                $this->logger->notice("Running Selenium...");
                $checker->UseSelenium();
                $checker->useChromium();
                $checker->http->saveScreenshots = true;
//                $checker->useSeleniumServer("selenium-dev.awardwallet.com");
//                $checker->http->SetProxy($this->http->GetProxy());
                $checker->http->GetURL('https://www.usaa.com/inet/ent_home/CpHome?action=INIT&jump=jp_default');
                $checker->Start();
                $link = $checker->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Log On")]'), 10);

                if ($link) {
                    $link->click();
                }

                $loginInput = $checker->waitForElement(WebDriverBy::id('j_usaaNum'), 10);
                $this->savePageToLogs($checker);

                // save page to logs
                $this->savePageToLogs($checker);
                // login
                if ($loginInput) {
                    $loginInput->sendKeys($this->AccountFields['Login']);
                    // password
                    $passwordInput = $checker->waitForElement(WebDriverBy::id('j_usaaPass'), 0);

                    if (!$passwordInput) {
                        return $this->checkErrors();
                    }
                    $passwordInput->sendKeys($this->AccountFields['Pass']);
                    // Sign In
                    $button = $checker->waitForElement(WebDriverBy::xpath('//button[contains(@class, "ent-logon-jump-button")]'), 0);

                    if (!$button) {
                        return $this->checkErrors();
                    }
                    //                $checker->driver->executeScript('setTimeout(function(){ delete document.$cdc_asdjflasutopfhvcZLmcfl_; document.getElementById("ecuserlogbutton").click(); }, 500)');
                    //                sleep(3);
                    $button->click();
                }

                $code = $checker->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Where should we send the security code?')] | //div[contains(text(), 'GET SECURITY CODE')]"), 10);
                // save page to logs
                $this->savePageToLogs($checker);

                if (!$code) {
                    return false;
                }

                // try find email
                if ($email = $checker->waitForElement(WebDriverBy::xpath('//label[contains(text(), "@")]'), 0)) {
                    $email->click();
                }

                $button = $checker->waitForElement(WebDriverBy::xpath('//button[@name = "Next"]'), 0);

                if (!$button) {
                    return $this->checkErrors();
                }
                $button->click();
                $code = $checker->waitForElement(WebDriverBy::xpath('//div[
                    contains(text(), "We\'ve sent your security code to")
                ]'), 15);
//                contains(text(), "We've sent your security code to")
//                or contains(text(), "We've sent your code to")
//                or contains(text(), "Enter the Security code.")
//                or

                // todo:
                if (!$code && ($button = $checker->waitForElement(WebDriverBy::xpath('//button[@name = "Next"]'), 0))) {
                    $button->click();
                    $code = $checker->waitForElement(WebDriverBy::xpath('//div[
                        contains(text(), "We\'ve sent your security code to")
                    ]'), 15);
                }

                if ($code) {
                    /*
                    $question = self::ASK_TEXT_SECURITY_CODE;
                    */
                    $question = $code->getText();
                }
                // save page to logs
                $this->savePageToLogs($checker);

                $cookies = $checker->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            } catch (ScriptTimeoutException $e) {
                $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
                // retries
                if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                    throw new CheckRetryNeededException(5);
                }
            } finally {
                // close Selenium browser

                if (!isset($question)) {
                    $checker->http->cleanup();
                } else {
                    $this->holdSession();
                }
            }

            // getting id form
            $form = $this->http->FindSingleNode("//form[descendant::input[@name = 'securityCodeentryTable:securityCodeentryTable_body:code']][@method='post']/@id", null, true, "/^id[0-9a-z]+/");
            $this->http->SetInputValue("submitButton", "Submit");
        }// if ($this->http->FindSingleNode("//p[contains(text(), 'Your temporary passcode has been sent.')]"))
        // security question
        else {
            $this->logger->debug("parseQuestion - just question");
            $question = $this->http->FindSingleNode("//label[contains(@for, 'securityQuestionTextField')]");
            // getting id form
            $form = $this->http->FindSingleNode("//form[descendant::input[@name = 'table:table_body:questionRow:questionRow_body:answer']][@method='post']/@id", null, true, "/^id[0-9a-z]+/");
            // https://www.usaa.com/inet/ent_auth_secques/answer?0-1.IFormSubmitListener-secquesform&acf=1
//            $formURL = $this->http->FindSingleNode("//form[descendant::input[@name = 'table:table_body:questionRow:questionRow_body:answer']][@method='post']/@action");
        }

        if (!isset($question)) {
            return true;
        }

        if (!$this->http->ParseForm($form)) {
            return false;
        }
//        if (!empty($formURL)) {
//            $this->http->NormalizeURL($formURL);
//            $formURL .= '&acf=1';
//            $this->http->Log("formURL: {$formURL}");
//            $this->http->FormURL = $formURL;
//        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice("ProcessStep. Question: {$this->Question}");
        // Text Security Code
        if (isset($this->http->Form["deliveryPanel:selfRecoveryTable:emailInput:input"])
            || $this->Question == self::TEXT_SECURITY_CODE) {
            $this->http->Log("ProcessStep - entering email address (Text Security Code)");
            // new?
            if ($this->http->FindSingleNode("//input[@name = 'deliveryPanel:selfRecoveryTable:selfRecoveryTable_body:emailInput:emailInput_body:input']/@name")) {
                $this->http->SetInputValue("deliveryPanel:selfRecoveryTable:selfRecoveryTable_body:emailInput:emailInput_body:input", $this->Answers[self::TEXT_SECURITY_CODE]);
                $this->http->SetInputValue("next", "Next");
                // may be bug
                if ($this->http->FormURL == 'https://www.usaa.com/inet/ent_auth_otc/otc/./textSecCodeDelivery') {
                    $this->http->FormURL = 'https://www.usaa.com/inet/ent_auth_otc/otc/textSecCodeDelivery?2-1.IFormSubmitListener-deliveryForm';
                }
            }// if (isset($this->http->Form["deliveryPanel:selfRecoveryTable:selfRecoveryTable_body:emailInput:emailInput_body:input"]))
            // old
            else {
                $this->http->SetInputValue("deliveryPanel:selfRecoveryTable:emailInput:input", $this->Answers[self::TEXT_SECURITY_CODE]);
                $this->http->SetInputValue("submitButton", "Next");
            }

            if (!$this->http->PostForm()) {
                return false;
            }
            $this->State["CodeSent"] = true;
            $this->State["CodeSentDate"] = time();
            $this->http->Log("ProcessStep - form submitting");
            // if email address was entered incorrect
            if ($errorMessage = $this->http->FindSingleNode("//p[contains(text(), 'We do not have the email address you entered on file.')]")) {
                $this->AskQuestion(self::TEXT_SECURITY_CODE, $errorMessage);
            }

            // ask Text Security Code
            $question = $this->http->FindSingleNode("//div[contains(text(), 'We have sent a security code to')]");
            $form = $this->http->FindSingleNode("//form[descendant::input[@name = 'tempPasscodeTable:otcRow:codeContainer:code']][@method='post']/@id", null, true, "/^id[0-9a-z]+/");

            if (!$this->http->ParseForm($form) || !isset($question)) {
                $this->logger->error("ProcessStep. ask Text Security Code failed.");

                return false;
            }
            // TODO: notifications
            $this->sendNotification("usaa - Text Security Code. Submit form with email address");
            $this->logger->debug("Question was asked: {$question}");
            $this->AskQuestion($question);

            return false;
        }
        // entering Temporary Passcode
        elseif ($this->Question == self::ASK_SECURITY_CODE) {
            $this->logger->debug("ProcessStep - entering Temporary Passcode");
            // check Answers
            $this->checkAnswers();

            $this->http->SetInputValue("tempPasscodeTable:tempPasscodeTable_body:otcRow:otcRow_body:codeContainer:code", $this->Answers[$this->Question]);
            $this->http->Form["submitButton"] = "Next";

            if (!$this->http->PostForm()) {
                return false;
            }
            // You have entered a temporary passcode that is invalid
            if ($errorMessage = $this->http->FindSingleNode("//p[contains(text(), 'You have entered a temporary passcode that is invalid.')]")) {
                $this->AskQuestion(self::ASK_SECURITY_CODE, $errorMessage);

                return false;
            }
        }// elseif ($this->Question == self::ASK_SECURITY_CODE)
        elseif ($this->Question == self::ASK_TEXT_SECURITY_CODE || strstr($this->Question, "We've sent your security code to")) {
            $this->logger->debug("ProcessStep - entering Security Code");
            $this->http->SetInputValue("securityCodeentryTable:securityCodeentryTable_body:code", $this->Answers[$this->Question]);
            $this->http->SetInputValue("submitButton", "Submit");

            if (!$this->http->PostForm()) {
                return false;
            }
            unset($this->Answers[$this->Question]);
            // Invalid code. Check the code and try again, or request another one.
            if ($error = $this->http->FindSingleNode('//p[contains(text(), "Invalid code. Check the code and try again, or request another one.")]')) {
                $this->AskQuestion($this->Question, $error, "Question");

                return false;
            }
        }// elseif ($this->Question == self::ASK_SECURITY_CODE)
        // entering Text Security Code
        elseif (strstr($this->Question, 'We have sent a security code to')) {
            $this->logger->debug("ProcessStep - entering Text Security Code");
            // check Answers
            $this->checkAnswers();

            $this->http->Form["tempPasscodeTable:otcRow:codeContainer:code"] = $this->Answers[$this->Question];
            $this->http->Form["submitButton"] = "Next";

            if (!$this->http->PostForm()) {
                return false;
            }
        }// end entering Text Security Code
        // security question
        else {
            $this->logger->debug("ProcessStep - just question");
            $this->http->ParseMetaRedirects = true;
            $this->http->SetInputValue("table:table_body:questionRow:questionRow_body:answer", $this->Answers[$this->Question]);
            $this->http->Form["submitbutton"] = "Submit";

            if (!$this->http->PostForm()) {
                return false;
            }
            $this->http->ParseMetaRedirects = false;

            if (!$this->checkErrors()
                || $this->http->FindPreg("/(In order for us to verify your identity, please answer the following security question)/ims")) {
                $this->parseQuestion();

                return false;
            }
        }

        return true;
    }

    public function parseSubQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode("//label[contains(@for, 'id')]");

        if (!isset($question)) {
            return false;
        }

        if (!$this->http->ParseForm("id1b")) {
            return false;
        }

        if (isset($this->Answers[$question])) {
            $this->http->Form["table:table_body:questionRow:questionRow_body:answer"] = $this->Answers[$question];
            $this->http->Form["submitbutton"] = "Submit";
            $this->http->PostForm();

            return true;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = false;

        return true;
    }

    public function Parse()
    {
        //# Enter Pin
        if (strstr($this->http->currentUrl(), 'inet/ent_auth_pin/page/PinEntryPage')
            || $this->http->FindSingleNode("//h1[contains(text(), 'Enter Your PIN')]")) {
            $this->logger->debug(">>> Enter Pin");
            $this->sendPin();
        }// if (strstr($this->http->currentUrl(), 'inet/ent_auth_pin/page/PinEntryPage'))
        //# Answer a question
        if (strstr($this->http->currentUrl(), 'inet/ent_auth_secques/answer')) {
            $this->logger->debug(">>> Answer a question");
            $this->parseQuestion();
        }// if (strstr($this->http->currentUrl(), 'inet/ent_auth_secques/answer'))
        // Pardon Our Interruption v.2
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Pardon Our Interruption')]")
            && $this->http->ParseForm(null, "//form[contains(@action, 'occupationForm')]")) {
            $this->logger->debug(">>> Skip update");
            $this->http->ParseMetaRedirects = true;
            $this->http->SetInputValue("navigationPanel:cancelDotComButton", "x");
            $this->http->PostForm();
            $this->http->ParseMetaRedirects = false;
        }
        // debug
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Pardon Our Interruption')]")
            && $this->http->ParseForm(null, "//form[contains(@action, 'ConfirmPhone')]")) {
            $this->logger->debug(">>> Skip update");
            $this->http->ParseMetaRedirects = true;
            $this->http->SetInputValue("navigationPanel:cancelDotComButton", "x");
            $this->http->PostForm();
            $this->http->ParseMetaRedirects = false;
        }
        /*
         * In order to better protect our membership, periodically we need to collect pertinent information about the people we serve.
         * Please take a few minutes to complete a short questionnaire.
         */
        $form = $this->http->FindSingleNode("//form[contains(@action, 'IFormSubmitListener-taskentryForm&detour=start&detourId=IsAMLHighRiskMember&flowExecutionKey=e1s1') and @method='post']/@id", null, true, "/^id[0-9a-z]+/");

        if ($this->http->FindPreg("/In order to better protect our membership, periodically we need to collect pertinent information about the people we serve\./ims") && $this->http->ParseForm($form)) {
            $this->throwProfileUpdateMessageException();
        }

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//strong[contains(text(), 'Welcome')]/parent::*", null, true, "/Welcome\s*\,?\s*([^<]+)/ims")));

        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'phoneVerifyContainer']/hr[@style = 'margin-bottom:20px;']/following-sibling::span[1]")));
        }
        // USAA Number
        $this->SetProperty("USAANumber", $this->http->FindSingleNode("//*[contains(text(), 'USAA Number')]", null, true, "/Number\s*\-?\:?\s*([^<]+)/ims"));

        if (empty($this->Properties['Name'])) {
            $headers = [
                'Accept'       => 'application/json, text/plain, */*',
                'Origin'       => "https://www.usaa.com",
                "x-csrf-token" => "tokenValue",
            ];
            $this->http->PostURL("https://api.usaa.com/auth/oauth/v5/web/token", "grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&scope=web", $headers);
            $response = $this->http->JsonLog();

            if (isset($response->access_token)) {
                $headers = [
                    'Accept'        => 'application/json, text/plain, */*',
                    'Origin'        => "https://www.usaa.com",
                    'Authorization' => "{$response->token_type} {$response->access_token}",
                ];
                $token = $this->http->getCookieByName("id_token_marker", ".usaa.com");

                if (isset($token)) {
                    $this->http->GetURL("https://api.usaa.com/enterprise/core-customer/v1/individuals/{$token}/name", $headers);
                    $response = $this->http->JsonLog();
                    $this->SetProperty("Name", beautifulName($response->formalName ?? null));
                }
            }
        }

//        // Go to the new My Accounts page
//        if ($this->http->FindPreg("/Go to the new My Accounts page/ims") && $this->http->ParseForm("RolloutWelcomePage")) {
//            $this->http->Log("Go to the new My Accounts page");
//            $this->http->Form['PS_SCROLL_POSITION'] = '0:0:RolloutWelcomePage';
//            $this->http->Form['PS_DYNAMIC_ACTION'] = 'PsDynamicAction_[action]CONTINUETODASHBOARDVIEW[/action][target][/target][context][/context]';
//            $this->http->PostForm();
//        }https://www.usaa.com/inet/ent_accounts/EntManageAccounts?action=init&mhdpilot=true&gadgetId=57DEFXYCO54WURMR7O3&lookAndFeel=iframe_gadget&jslibs=pubsub.js,refresh.js,gadget-events.js,rpc.js,dynamic-height.js,gadget-autoheight.js&title=My%20Accounts%20Summary&multipleViewStates=open&parent=http%3A%2F%2Fwww.usaa.com&st=john.doe:john.doe:appid:cont:url:0:default&parentRelayURL=https%3A%2F%2Fwww.usaa.com%2Fjavascript%2Fent%2Fportal%2Ffeatures%2Frpc_relay.html&rpctoken=1777940968
//
        $this->logger->notice("Loading frame with cards");

        if (!$this->http->GetURL("https://www.usaa.com/inet/ent_accounts/EntManageAccounts?action=INIT")) {
            if (!empty($this->Properties['Name']) && $this->http->Response['code'] == 404) {
                $this->SetBalanceNA();
            }

            return;
        }

        if ($this->http->FindPreg("/You currently have no accounts with USAA/ims") !== null) {
            $this->SetBalanceNA();
        }
        // Detected cards
        $allDetectedCards = $this->http->XPath->query("//div[@class = 'floatLeft acctName' and a[contains(@href, 'https://www.usaa.com/inet/gas_bank/BkAccounts?target=AccountSummary')]]");
        $this->http->Log("Total {$allDetectedCards->length} cards were found");

        for ($i = 0; $i < $allDetectedCards->length; $i++) {
            $displayName = $this->http->FindSingleNode("a", $allDetectedCards->item($i));

            if (!empty($displayName)) {
                $displayName = preg_replace("/\s*3 Account Name$\s*/", "", $displayName);
            }

            if (!empty($displayName)) {
                $code = str_replace([' ', '\'', '/', '?', "'", '"', ':', '(', ')', ','], '', $displayName);
                $code = str_replace(['+'], ['Plus'], $code);
                $this->AddDetectedCard([
                    "Code"            => 'usaa' . $code,
                    "DisplayName"     => $displayName,
                    "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                ], true);
            }
        }// end Detected cards
        $cards = $this->http->FindNodes("//div[@class = 'floatLeft acctName']/a[contains(@href, 'https://www.usaa.com/inet/gas_bank/BkAccounts?target=AccountSummary')]/@href");
        $subAccounts = [];

        foreach ($cards as $url) {
            $this->logger->notice("loading card...");

            if ($this->http->GetURL($url)) {
                if ($this->parseSubQuestion()) {
                    return;
                }
                // for Detected cards
                $displayName = $this->http->FindSingleNode("//span[@class = 'allcaps']");
                // loading rewards
                if ($rewardsUrl = $this->http->FindPreg('/"([^"]*\/inet\/gas_bank\/BkAccountRewardPointBalance\?[^"]*)"/ims')) {
                    $this->logger->notice("loading rewards...");
                    $this->http->setDefaultHeader('X-Requested-With', 'XMLHttpRequest');
                    $this->http->PostURL($rewardsUrl, []);
                    $this->SetBalance($this->http->FindSingleNode("//span[@class = 'rewardsModule_amount']"));
                    // Detected cards
                    $code = str_replace([' ', '\'', '/', '?', "'", '"', ':', '(', ')', ','], '', $displayName);
                    $code = str_replace(['+'], ['Plus'], $code);
                    $this->AddDetectedCard([
                        "Code"            => 'usaa' . $code,
                        "DisplayName"     => $displayName,
                        "CardDescription" => C_CARD_DESC_ACTIVE,
                    ], true);
                }

                if ($this->http->ParseForm("CreditSummaryPage")) {
                    $name = $this->http->FindSingleNode("//h1/span[@class = 'allcaps']");

                    if (isset($name)) {
                        $name = ucfirst(strtolower($name));
                    }
                    $number = $this->http->FindSingleNode("//th[contains(text(), 'Account Number')]/following::td[1]", null, false, null, 0);
                    $dynamicAction = $this->http->FindPreg("/return dynamicAction\('([^']*EaglePoint[^']*)'/ims");

                    if (isset($dynamicAction)) {
                        $this->logger->debug("clicking rewards link, action: " . $dynamicAction);
                        $this->http->Form['PS_DYNAMIC_ACTION'] = $dynamicAction;

                        if ($this->http->PostForm()) {
                            $url = $this->http->FindPreg("/function\s+wait\(\)\{\s*document\.location\s*=\s*'([^']+)'/ims");
                            $this->logger->notice("loading rewards...");

                            if ($this->http->GetURL($url)) {
                                $this->CheckError($this->http->FindSingleNode("//b[contains(text(), 'USAA online Rewards Program site is not currently available due to maintenance')]"), ACCOUNT_PROVIDER_ERROR);
                                $subAccount = $this->addSubAccounUSAAt($name, $number, $displayName);

                                if (!empty($subAccount)) {
                                    $subAccounts[] = $subAccount;
                                }
                            }// if ($this->http->GetURL($url))
                        }// if ($this->http->PostForm())
                    }// if (isset($dynamicAction))
                    else {
                        $subAccount = $this->addSubAccounUSAAt($name, $number, $displayName);

                        if (!empty($subAccount)) {
                            $subAccounts[] = $subAccount;
                        }
                    }
                }// if ($this->http->ParseForm("CreditSummaryPage"))
            }// if ($this->http->GetURL($url))
        }// foreach ($cards as $url)

        if (count($subAccounts) > 0) {
            $this->SetProperty("SubAccounts", $subAccounts);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && !empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }

    public function addSubAccounUSAAt($name, $number, $displayName)
    {
        $this->logger->notice(__METHOD__);
        $subAccount = [];
        $balance = $this->http->FindSingleNode("//td[h4[b[contains(text(), 'Available Points:')]]]/following::td[1]");
        // Rewards Points Balance
        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//div[@id = 'CCRewardsProgram']//span[@class = 'cc-infoModule-value']");
        }

        if (isset($name) && isset($number) && isset($balance)) {
            $subAccount = [
                "Code"        => 'usaa' . $number,
                "DisplayName" => "$name $number",
                "Balance"     => $balance,
                "Currency"    => strstr($balance, "$") ? "cash" : null,
                "LastUpdated" => $this->http->FindSingleNode("//td[b[contains(text(), 'Last Updated:')]]/following::td[1]"),
                "Earned"      => $this->http->FindSingleNode("//td[b[contains(text(), 'Earned:')]]/following::td[1]"),
                "Redeemed"    => $this->http->FindSingleNode("//td[b[contains(text(), 'Redeemed:')]]/following::td[1]"),
                "Pending"     => $this->http->FindSingleNode("//td[b[contains(text(), 'Pending Airline Redemptions:')]]/following::td[1]"),
            ];
            // Detected cards
            $this->AddDetectedCard([
                "Code"            => 'usaa' . str_replace([' ', '\'', '/', '?', "'", '"', ':', '(', ')', ','], '', $displayName),
                "DisplayName"     => "$name $number",
                "CardDescription" => C_CARD_DESC_ACTIVE,
            ], true);
        }// if (isset($name) && isset($number) && isset($balance))

        return $subAccount;
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl = $this->http->FindPreg('/src="(\/resources\/\w+)"/');

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");
            $sensorPostUrl = 'https://www.usaa.com/resources/54d4861499160138bf440e7dd79e22';
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensor = [
            // 0
            "7a74G7m23Vrp0o5c9157311.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:74.0) Gecko/20100101 Firefox/74.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,390205,4081906,1536,880,1536,960,1536,455,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6006,0.671445871335,792947040952.5,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-102,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,3,2514;1,2515;-1,2,-94,-112,https://www.usaa.com/inet/ent_logon/Logon-1,2,-94,-115,1,32,32,0,0,0,0,2992,0,1585894081905,5,16965,0,0,2827,0,0,2993,0,0,B9F657AAC629269ECA6F3DCD831E39C2~-1~YAAQMWUzuHBT6xlxAQAAh0anPgPJtaUq6NRxHyihVr/qlHOldL79I6/d5/RfNmBindtG3Qkiz4L+vEsgY97fLpJtewtkmv71iwpZqgIplyUr8eQ4IsHdsvOUJ1vvonb4aY6oo53HB+L2APVygmUfJuLPzAT8xBkiBqk9IF6Esx6J/r6ICyqJrTb0L4vW2x4uLNURdfPzlaONeYmK+LyNv2WI07gE5TWnan+4O4G6On2GPY4uGG+3OqztG5/3Mbk2SrVgF49Je0eKVGk56VKae7psyj7JnW5kz3uuVF5AKFOv4dbUedUWKah2scmorE19apYuWvRn~-1~-1~-1,31067,488,670230388,26067385-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,0,200,0,0,0,200,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,36737199-1,2,-94,-118,88144-1,2,-94,-121,;1;5;0",
            // 1
            "7a74G7m23Vrp0o5c9103751.65-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.75 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,394310,8640353,1536,880,1536,960,1536,345,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8925,0.17213411386,801289320176.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-102,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usaa.com/inet/ent_logon/Logon-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1602578640353,-999999,17143,0,0,2857,0,0,2,0,0,C53B380EAAF9CEB23EF3EB0355B6150F~-1~YAAQlwDARYfAWvF0AQAAqzYhIQR5iVtaxPAb4i/hdjjnRJoxTmnR+AQqvvt4a7XJdhr6pIinpLjUh5IbLRrPoHL2bGv8mj1fN8wt3j/IfJY4KruFfjhImjbHZ3na1EOas9L1h59p9/Q3yxMKLnsx8mUrSPa4aDRmD5E289dxUeTevY7QaLpNy5lGDXNa1c/z+X8rVKHIwXUdIEifzwNOoSODf99IBUndOt0F+AQkw3V0CCqZ+DJcH1rMcGUb1ehD/Sj/p6DdilTSTXyjVO3f/5FV6oTzAcDHI2BJSvv+6IQZEijihteCIw==~-1~-1~-1,29369,-1,-1,30261693,PiZtE,24889,54-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,129605265-1,2,-94,-118,86499-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
            // 2
            "7a74G7m23Vrp0o5c9103791.65-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.75 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,394313,2158514,1536,880,1536,960,1536,448,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8925,0.463052945231,801296079256.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-102,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usaa.com/inet/ent_logon/Logon-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1602592158513,-999999,17144,0,0,2857,0,0,3,0,0,FC60DCB448B88461970AEFA130014300~-1~YAAQBPfSF79C5BN1AQAA4avvIQSOH/8jUnYY0cRNbScqI5RBGI7jncwM5hQOSGMsrICwi+1wgyftza2/ASIT+/VoPQeQbE37QmhXKq6j77FGzfRsDtTrP4ml9f1r/zNK1MqsqWmb5UrcHBxzPlo0XPaQXwrk6KUBYdfz6LzmEOrQkR83Tl7RPi+FNREsmS0vWbQSXpxsFvjlIGFxDce3lw9UEXZZO6iEIP5DVB454PRdJILol/I0hsJcvDNDoxXlBIdK87uG2sAz6emxthLSXZiSb04xJtehW0leafB5DNOqUm93H/6Jww==~-1~-1~-1,29157,-1,-1,30261693,PiZtE,44360,100-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,291399624-1,2,-94,-118,86424-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 3
            "7a74G7m23Vrp0o5c9102781.64-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.121 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,394224,7673271,1536,880,1536,960,1536,462,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.961869715480,801113836635.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-102,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usaa.com/inet/ent_logon/Logon-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1602227673271,-999999,17140,0,0,2856,0,0,3,0,0,FB8EA2BFFB9209D668A59CA67F3C39B2~-1~YAAQTZTcFwxMMZ90AQAAUuY1DAQ4V9AneS/eOk/rQtxrUJvJuQBk/5bmbNahsYGi446XHDkd8kUZ8ySAeoIqF5j497EHkbv6gKNdiFaDK5r3fEZ+AQmbeWjFPZNX0oeUcSPb4SpLpyP5G6yM6hIxAD152tXOhmmikhWwCV5UzCwPbnD3TZiNUbDSVobqVlZCGEgobhDF4tLI1pO2dP6WFxN5Vg50+BL9znSPCr7p8bs+R+4z0lnxGkqomZzGjCqpPkqUtZnKQlx8WH2v4zVTgeFYr4FK61S6Dz+ir/CwXmTeKYGOC7mVHQ==~-1~-1~-1,29290,-1,-1,30261693,PiZtE,108873,61-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,23019750-1,2,-94,-118,86621-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 4
            "7a74G7m23Vrp0o5c9103951.65-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.75 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,394327,9493763,1536,880,1536,960,1536,448,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8925,0.888520051444,801324746881,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-102,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usaa.com/inet/ent_logon/Logon-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1602649493762,-999999,17144,0,0,2857,0,0,3,0,0,9B392C96B71749516C8949B2B439D258~-1~YAAQb6w4F7uKjPl0AQAAq4ZaJQRdoGRvXgGjspsHPRkRDs6oUkVFe5BNakkU2OzaJqv93t3SiwHcxC8MUyK+dWQ4Ri9a5FnXvySQkTxDoUs5tvBe/IEtG70/gZScLNvq6fzkQC5jU3Lwl1a9PZMNckfqm4fUZHu/kS8gqv/1XZCeOkyq6/P5PItuYJIHSbwtVuFDLlamkrSAZ/Om+XV7EwFy0pGKHHetbEjidoHK7BsT4CGbgY7TUKLFnN+6smbmqtA+UknglXMsYplHh/Zil48No/GqIwpC2O6OUBeVtWHkF8KvCiY6mw==~-1~-1~-1,29640,-1,-1,30261693,PiZtE,106102,64-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,28481250-1,2,-94,-118,86791-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 5
            "7a74G7m23Vrp0o5c9156321.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,390117,9238187,1536,880,1536,960,1536,453,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8975,0.441423038220,792769619093.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-102,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usaa.com/inet/ent_logon/Logon-1,2,-94,-115,1,32,32,0,0,0,0,519,0,1585539238187,11,16961,0,0,2826,0,0,520,0,0,070D0912C896139B0513901DD04B0873~-1~YAAQlwDARb2XXRxxAQAAEsqAKQMyZSCgwOE/ee/dHVChXkHBFDEmduN+LFOXNjHhXFsBi/JbcR+XX8DDE/GfybMDrGx/Dg6oOSIe5ZnCj6cTiFotmqfYFjkUdQZdkR5QkXBanz7KvhNI4r3U6pBNVFnRjnIFD83wu3iJGGTDnD0rbJpDnekKhAsiiX61blpktGC58srF5xdT7OxnxrWwLizENtNYSTZBAl4gp89MjNwSLLUjR0H4PfFfjHNyb0UfD5K4UXEsywAK8mCwtiuPGrJDqPmLb1rPs0owd/CaB9BKpdh3SqkL82OcMxTdShzj8JS8JS2V~-1~-1~-1,30854,41,-672395682,30261693-1,2,-94,-106,9,1-1,2,-94,-119,83,56,54,54,69,72,46,41,43,7,7,8,13,375,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,5584-1,2,-94,-116,748292513-1,2,-94,-118,90277-1,2,-94,-121,;3;8;0",
            // 6
            "7a74G7m23Vrp0o5c9198691.64-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.102 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,393861,5148932,1536,880,1536,960,1536,478,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8968,0.427350973213,800377574466,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-102,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usaa.com/inet/ent_logon/Logon-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1600755148932,-999999,17124,0,0,2854,0,0,3,0,0,05E133EC550D82FBEA17C21C96A32283~-1~YAAQYZTcFwC3mbF0AQAAAqVwtARm0m3YbF+TNL7ttaidVJ4WKo89cPnDEn5p6LkHCilkym45s/NYmdBitm2cLz2dpwjL/WYoUbgCeUclaV7Q4pJrzyRELPliUD2aq5jVnr3WaqLLBhmhsz+Y0/SZFAPpz3p0n3aiHuvF5GaJoCfBBNVrEWbxopgEr7XPqRCWIHTuMxIsuDmKw/WhqKQrHT8W0Q2jXtbLu3VU3Zqs+CSjqJBFilF94I35xBS9+aiNHrE73tggCmK3iRAGbZOTh2b1S0HXijERxoPOnFIuAWtwK8VCwWfJlg==~-1~-1~-1,29531,-1,-1,30261693,PiZtE,22416,68-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,695106225-1,2,-94,-118,86708-1,2,-94,-129,-1,2,-94,-121,;6;-1;0",
            // 7
            "7a74G7m23Vrp0o5c9102781.65-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.121 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,394224,7626092,1536,880,1536,960,1536,462,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.303583309151,801113813045.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-102,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usaa.com/inet/ent_logon/Logon-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1602227626091,-999999,17140,0,0,2856,0,0,4,0,0,F9E4F4A0D820922B775AE69D4DD84864~-1~YAAQJPfSF9Lk5P50AQAAeSw1DATX7LeyD3zui0/7kkofiD41B9X55vRlT/2//BHi7Ku4xs0WpAdN8zxaQJYaqPDkTAon0iXBzucAbhZAQoCabPXdispqt3fp2bmTHjfr3MtfCG2aGlQIF/LdhT0R30HVG3+/ygoH1HMUVVQP+lRIlEIANoVa4/hiwi5YxgSHx4dnqCVNpm67ZSXn6MeSchUQz/szoBKLug+nZ4+Ay13s8OJgPQA3YATMk1QcV+lwyY3m823rqRpolOrNutFd3inALPAljEBenCVYcLDDc7p0UvOUAnMxnA==~-1~-1~-1,29010,-1,-1,30261693,PiZtE,91061,86-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,22878267-1,2,-94,-118,86231-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 8
            "7a74G7m23Vrp0o5c9157301.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,390205,3397055,1536,880,1536,960,1536,487,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8975,0.274713978137,792946698527.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-102,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-108,-1,2,-94,-110,0,1,12,1061,278;1,1,16,1061,264;2,1,29,1073,237;3,1,66,1110,203;4,1,68,1114,201;5,1,80,1121,197;6,1,85,1124,196;7,1,93,1130,193;8,1,104,1133,192;9,1,128,1140,190;10,1,149,1141,192;11,1,436,1141,192;12,1,444,1141,195;13,1,452,1141,198;14,1,460,1139,203;15,1,468,1136,207;16,1,476,1131,211;17,1,485,1126,216;18,1,492,1119,221;19,1,501,1105,232;20,1,508,1088,243;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usaa.com/inet/ent_logon/Logon-1,2,-94,-115,1,33751,32,0,0,0,33719,518,0,1585893397055,9,16965,0,21,2827,0,0,520,5552,0,F52942D988EBCEB7883693EDB6DC04C3~-1~YAAQMWUzuKhN6xlxAQAA4NKcPgMi50T9jU4qLN3vz7ACHN7LtiYAt3Eu4JzWeu3Sim0Bodm8V7svhQ/GPeroBI0ZB9Evrrqt0w+MffKCcF7oVXweLkrhI+gx1M11FML9v8DpXx90WY7gMoNCC3Yhmhva0OiFwMKuT16D2MTGY7V7xeTZfFflGYD2vc7pdouiBz5f4S+qL3DXB2EckFbcKkiXeDAJnkCSL8RHgfllrdWm/4yrNB80yliN2hTLQj3ADtZrMlXR4HmwPiJCMaVDGi61g1GZf00OmsCL4Pdm7nGZFvc/249yDDuWYrXxX969ZpLFhXB5~-1~-1~-1,30399,230,-107771871,30261693-1,2,-94,-106,9,1-1,2,-94,-119,29,30,31,30,62,52,12,8,8,6,6,6,10,358,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,5584-1,2,-94,-116,3397069-1,2,-94,-118,108266-1,2,-94,-121,;3;8;0",
            // 9
            "7a74G7m23Vrp0o5c9198691.64-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.102 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,393861,5197530,1536,880,1536,960,1536,478,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8968,0.584801757292,800377598765,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-102,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usaa.com/inet/ent_logon/Logon-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1600755197530,-999999,17124,0,0,2854,0,0,3,0,0,706DD334FE65F983264E8F3E87B44234~-1~YAAQtx/JFydBVnJ0AQAA9sFxtAR+QcNhB/JiiknBkFMzVlKnfdHT2oqEeMUIjzpBjh6itu66sYIwLG7HdP//x+d8Ml/U1u9coCRiWDbxbMBYHaPN6JM4EnPFBb3KTsant85Jsu5tLVCfRehUxamW36pZzeFx7rw8jWtba7ISf+dJ+qbFZbn+UItPIbVBdiNJS5NGuWB8uDSluSN1Ofbz+4Zx5hJyOAac4UsWsJzYQKw3nATfqurU+cvEWBWR1DI94fCvVYmCaQRXeuzM2Txc28fzsgIYkffvxehQYTD+oKdT5JyOIvsr0w==~-1~-1~-1,29764,-1,-1,30261693,PiZtE,51799,61-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,15592602-1,2,-94,-118,86958-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 10
            "7a74G7m23Vrp0o5c9158801.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:74.0) Gecko/20100101 Firefox/74.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,390337,356615,1536,880,1536,960,1536,495,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6006,0.13440042567,793215178307.5,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-102,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usaa.com/inet/ent_logon/Logon-1,2,-94,-115,1,32,32,0,0,0,0,548,0,1586430356615,6,16971,0,0,2828,0,0,549,0,0,5B309A58D07A290FABB9C2D5CB74E3B8~-1~YAAQmQrGFypGBj1xAQAAMS2eXgM53Fxq1G0Bmc/YatkdgsNO36wJTjP/I4a6rWch7813Tb743k2afsHd1QTIcPzedy2w4IlnjXuVwHSt27y8JHfBWqiW5CszHuFUPChoA0yzDkMUIy548NEKiSoH2J/JWtAyVAFI/NuvFjdDJEfler4Y5V57jyijZBlq0pwS0z3BFx3NMJEsEhcEY0AjRSjeXHsoLLTkvoeU9HVHdF8wUb5e1Iq3cXOfulYBzfpuq+VEeYXtg4+IXTo5GkGIWcfHsqRMIKIA8owUc5FSwgSIrraoUoozzbFLWBQTu17vE9ygIjSG~-1~-1~-1,30924,75,1987130958,26067385-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,0,200,0,0,0,200,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,356615-1,2,-94,-118,87049-1,2,-94,-121,;2;4;0",
            // 11
            "7a74G7m23Vrp0o5c9158801.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:74.0) Gecko/20100101 Firefox/74.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,390337,389730,1536,880,1536,960,1536,495,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6006,0.504538660252,793215194864.5,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-102,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usaa.com/inet/ent_logon/Logon-1,2,-94,-115,1,32,32,0,0,0,0,512,0,1586430389729,6,16971,0,0,2828,0,0,513,0,0,F28B6BD9245CFCBC0D2AF7B116E1E153~-1~YAAQmQrGF81HBj1xAQAAQa+eXgNjQLSgnng5hdfsjTDpClTYM8dn4TMcP1Y4aevQGOMNT8hmbAGz5TBV8FmPyinNnvtI2Qve71n3jbVUeqTp8A0P0qXFzaR0ZlmHnE8BiCKDjeSNONe1eOS0HzEVC0/VVHksjYenxKI60gLVKsb+Aa5zl5FlMqD0FtyFpE8SpXgNOmtqaScy1j+qfszLIJMRYvKrLn0S2z5IOzY/y6XhTR/uDdi3OX1trzffhtcpyxVSuaOZK/h6eHBKgBAsuiQID5mT0VWEb4s+sdHtPg6VFmuKbnikKmA1yGWLtlNqEoxlSNjm~-1~-1~-1,31285,791,587402532,26067385-1,2,-94,-106,9,1-1,2,-94,-119,0,0,200,0,200,0,0,0,0,0,0,0,200,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,3507552-1,2,-94,-118,87561-1,2,-94,-121,;1;5;0",
            // 12
            "7a74G7m23Vrp0o5c9103791.65-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:81.0) Gecko/20100101 Firefox/81.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,394313,1721244,1536,880,1536,960,1536,432,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:125,vib:1,bat:0,x11:0,x12:1,6002,0.0036590761,801295860621.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-102,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usaa.com/inet/ent_logon/Logon-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1602591721243,-999999,17144,0,0,2857,0,0,3,0,0,DDE2030CF93E6EC0025115E4BF57AB18~-1~YAAQLffSFzBTth51AQAAMPPoIQS/uvkoQKj9l1OXQ0cI7KppAWdqoZtpnx9FNj1heiunVSAxpR6fwEouXNXZjR63uKGY/adOx77aKxcTfcauz5HP2bUGa9Oe99amjHM3ImbDbwmurO2oKxStm8EdDPIAnbITppwuDA5RZoO4LvofMJ/ckEszq1sIapq7uYb/FnRHnEzLY3boOzRTBcEg9NKJD7+6wZu5IHEloxJpaVWvkUPu554EqaUkqq/79BAMf7Uka11uq+EIFaqsBpHgszFGYBgZD2ZXVX8PDzqYga95sJUSHsTvse5jcXp94b/akNqtcG4=~0~-1~-1,31240,-1,-1,26067385,PiZtE,43706,80-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,129093288-1,2,-94,-118,85598-1,2,-94,-129,-1,2,-94,-121,;3;-1;0",
            // 13
            "7a74G7m23Vrp0o5c9196751.64-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.102 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,393690,7577932,1536,880,1536,960,1536,501,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8968,0.435273297217,800028788966,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-102,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usaa.com/inet/ent_logon/Logon-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1600057577932,-999999,17116,0,0,2852,0,0,3,0,0,99A87C07DFBB6F9ADA94890313F0A4CF~-1~YAAQjw1lX9VxnXl0AQAAaufcigQKH1pywHtVvKRVYYUlfyj9bOR6N6RQ0bMNqw7dzX/IB20UtvdvSwotHHyx2Y7kdQwxLA9g9fK7xLbKTmbdLOAYqiIZz+urJbL/yBN+q4R68zRzkcTjRG5rCUuAyTD6SPJMhoRMg6Yk29BCufglNntj3uKhF1XfIgQv7RHZrsPOxxbhAIiaW/BkAX65J1qgfGxx8A+A8RvySRMAnvXfTIyn5+HW3vgmbJNG6vCruMIt1Rz1+IdX/wjxAwXDhzE/u1AtYjVDkYjO1YmQFEo5kjSTWr8akA==~-1~-1~-1,29944,-1,-1,30261693,PiZtE,76701,90-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,22733757-1,2,-94,-118,87104-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 14
            "7a74G7m23Vrp0o5c9103791.65-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:81.0) Gecko/20100101 Firefox/81.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,394313,1749202,1536,880,1536,960,1536,432,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:125,vib:1,bat:0,x11:0,x12:1,6002,0.10137412450,801295874601,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-102,0,0,0,0,630,1209,0;0,-1,0,0,730,1065,0;1,0,0,0,833,1084,0;0,-1,0,0,931,1065,0;1,0,0,0,1034,1084,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usaa.com/inet/ent_logon/Logon-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1602591749202,-999999,17144,0,0,2857,0,0,2,0,0,153F50C87A0F4B868C2EDB212B640306~-1~YAAQTZTcF6CnTRV1AQAAbVrpIQTMB2Thi4P/NcNuWvbr5d+Q4PKh6PasPafinqvX3kXBodDi8GxTyJPU7YGZaYs7Guv6k/BywHesc+Vcwuzq4e1yZXd8556C2ccv9yY8DiIXRop+LZUxZcNgriWs3xHWrb+SWDP3NxU7h5DMjiF12mB8f8DYjZ2nLWObl9Q3Fj+xKYvs7gHqxNHKgWvomr9Bu16HD1Q/maD/hOXK7Rwroc3QgajQRS+a0WOpxKhGmUXEj4cFXdD9gRM8Sixt8738VuvXyiWP5Olr5KMthzjz+DVC7yX+DQ==~-1~-1~-1,29302,-1,-1,26067385,PiZtE,54803,58-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,26238090-1,2,-94,-118,83606-1,2,-94,-129,-1,2,-94,-121,;2;-1;0",
        ];

        $key = array_rand($sensor);
        $this->logger->notice("[key]: {$key}");
        $sensor_data = $sensor[$key];

        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        sleep(1);
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensor_data]), $headers);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }

    private function selenium($currentUrl)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $selenium->useFirefox();
            $selenium->disableImages();
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL($currentUrl);
            $logiOn = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@class, "profileWidget-button--logon")]'), 5);

            if ($logiOn) {
                $logiOn->click();
            }
            // save page to logs
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] != 'bm_sz') {
                    continue;
                }
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            $result = true;
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup();
        }

        return $result;
    }
}
