<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerThankyou extends TAccountChecker
{
    use ProxyList;

    public const IDENTIFICATION_CODE_MSG = 'Please enter Identification Code which was sent to your phone. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.';

    public $regionOptions = [
        ""         => "Select type of your credentials",
        "Citibank" => "Citibank® Online username and password",
        //        "ThankYou" => "ThankYou.com username and password",
        "Sears"    => "Sears username and password",
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], $properties['Currency'] . "%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerThankyouSelenium.php";

        return new TAccountCheckerThankyouSelenium();

        if (in_array($accountInfo['Login2'], ['Citibank', 'Sears'])) {
            require_once __DIR__ . "/TAccountCheckerThankyouSelenium.php";

            return new TAccountCheckerThankyouSelenium();
        }

        return new static();
    }

    public function LoadLoginForm()
    {
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->debug('Region => ' . $this->AccountFields['Login2']);

        $this->http->removeCookies();
        $this->http->setMaxRedirects(10);
        $this->http->GetURL('https://www.citi.com/citi-partner/thankyou/login?userType=tyLogin&locale=en_US&TYNewUser=false&TYForgotUUID=false&TYMigration=&SAMLPostURL=https:%2F%2Fwww.thankyou.com%2F%2Fgateway2.htm&ErrorCode=&TYPostURL=https:%2F%2Fwww.thankyou.com%2F%2FtyLoginGateway.htm&cmp=null');
//        if (!$this->http->ParseForm("partnerLoginForm")) {
        if (!$this->http->FindSingleNode('//div[@id = "maincontent"]/@id')) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://www.thankyou.com//tyLoginGateway.htm';
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('accountType', "thankYou");
//        $this->http->SetInputValue('userType', "tyLogin");
//        $this->http->SetInputValue('selectionMode', "I");
//        $this->http->SetInputValue('counterty', "0");
        $this->http->SetInputValue('remember', "Y");
        $this->http->SetInputValue('locale', "en_US");
        $this->http->SetInputValue('cbolLang', "ENG");
        $this->http->SetInputValue('tyLocale', "en_US");
//        $this->http->SetInputValue('tmxSessionId', $this->http->FindPreg("/session_id=([^\"]+)/"));

        return true;
    }

    public function LoadLoginFormCitibank()
    {
        $this->http->FilterHTML = false;
        $this->http->setCookie("s_cc", "true", ".citibank.com");
        $this->http->GetURL('https://online.citibank.com/US/JPS/portal/Index.do?userType=tyLogin&TYNewUser=false&TYForgotUUID=false&TYMigration=&SAMLPostURL=https%3A%2F%2Fwww.thankyou.com%2Fgateway2.jspx&ErrorCode=&TYPostURL=https%3A%2F%2Fwww.thankyou.com%2FtyLoginGateway.jspx&cmp=null');

        if (!$this->http->ParseForm("SignonForm")) {
            return $this->checkErrors();
        }
//        $this->http->FormURL = 'https://online.citibank.com/US/JSO/signon/CallIFXValidationServiceForGPS.do?LoginMode=tyLogin';
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
//        $this->http->SetInputValue('usernameMasked', "SC****EE");
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('XYZ_Extra', $this->http->FindPreg("/XYZ_Extra value=([^>]+)/ims"));
        // secret keys
        $XXX_ExtraName = $this->http->FindPreg("/XXX_Extra\s*value=\'\s*\+\s*([^\+\s]+)/");
        $key = $this->http->FindPreg("/{$XXX_ExtraName}\s*=\s*([\d\w]+)\.substring\([\d\w]+\.length\s*-\s*[\d\w]+\);/ims");
        $lengthName = $this->http->FindPreg("/{$XXX_ExtraName}\s*=\s*[\d\w]+\.substring\([\d\w]+\.length\s*-\s*([\d\w]+)\);/ims");
        $length = $this->http->FindPreg("/{$lengthName}\s*=\s*\'([^\']+)/ims");
        $this->logger->debug("Key: $key | length: $length");
        $XXX_Extra = substr($key, -$length);
        $this->http->SetInputValue('XXX_Extra', $XXX_Extra);
//        $this->http->SetInputValue('devicePrint', "version%3D2%26pm%5Ffpua%3Dmozilla%2F5%2E0%20%28macintosh%3B%20intel%20mac%20os%20x%2010%2E10%3B%20rv%3A35%2E0%29%20gecko%2F20100101%20firefox%2F35%2E0%7C5%2E0%20%28Macintosh%29%7CMacIntel%26pm%5Ffpsc%3D24%7C1280%7C800%7C731%26pm%5Ffpsw%3D%26pm%5Ffptz%3D5%26pm%5Ffpln%3Dlang%3Den%2DUS%7Csyslang%3D%7Cuserlang%3D%26pm%5Ffpjv%3D1%26pm%5Ffpco%3D1%26pm%5Ffpasw%3Ddefault%20browser%7Cgoogletalkbrowserplugin%7Co1dbrowserplugin%7Cjavaappletplugin%7Cquicktime%20plugin%7Cflash%20player%7Cskype%5Fc2c%5Fsafari%26pm%5Ffpan%3DNetscape%26pm%5Ffpacn%3DMozilla%26pm%5Ffpol%3Dtrue%26pm%5Ffposp%3D%26pm%5Ffpup%3D%26pm%5Ffpsaw%3D1280%26pm%5Ffpspd%3D24%26pm%5Ffpsbd%3D%26pm%5Ffpsdx%3D%26pm%5Ffpsdy%3D%26pm%5Ffpslx%3D%26pm%5Ffpsly%3D%26pm%5Ffpsfse%3D%26pm%5Ffpsui%3D");
//        $this->http->SetInputValue('ioBlackBox', "	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");

//        if ($this->http->ParseForm("SAMLResponse"))
//            $this->http->PostForm();
//        if ($this->http->ParseForm("GPSSSO"))
//            $this->http->PostForm();
//        if ($this->http->ParseForm("GPSSSO"))
//            $this->http->PostForm();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindPreg("/(Network\s*website is temporarily\s*unavailable while we perform maintenance\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're sorry. Citi.com is temporarily unavailable
        if ($message = $this->http->FindPreg("/(We\'re sorry\. Citi\.com is temporarily unavailable\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Provider error
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Server Error')]")
            || $this->http->FindSingleNode("//h2[contains(text(), 'Error 404')]")
            || $this->http->FindPreg("/Error 503--Service Unavailable/")
            || $this->http->FindPreg("/(Error 404--Not Found)/ims")
            || $this->http->FindPreg("/(The page cannot be displayed because an internal server error has occurred)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Maintenance
        $this->http->GetURL("https://www.thankyou.com/");

        if ($this->http->ParseForm("cmsHomeForm")) {
            $this->http->PostForm();
        }

        if ($message = $this->http->FindSingleNode('//b[contains(text(), "the ThankYou Rewards website is temporarily unavailable while we perform maintenance")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function providerErrors()
    {
        $this->logger->notice(__METHOD__);
        // Important: Our sign-on process is changing
        if ($this->http->currentUrl() == 'https://www.thankyou.com/routing.jspx'
            && $this->http->FindPreg("/<h3>Important: Our sign-on process is changing<\/h3>/ims")
            && ($skip = $this->http->FindPreg("/<a href=\"([^\"]+)\"[^>]+>Continue to ThankYou.com/ims"))) {
            $this->logger->notice("Skip update profile");
            $this->http->NormalizeURL($skip);
            $this->http->GetURL($skip);
        }
        // Important: You'll only be able to access your account with Citi credentials
        if ((($this->http->currentUrl() == 'https://www.thankyou.com/routing.jspx' || $this->http->currentUrl() == 'https://www.thankyou.com/preLogin.jspx')
                && $this->http->FindPreg("/You'll only be able to access your account with Citi credentials/ims"))
            // The email address you provided has been flagged as undeliverable
            || ($this->http->FindSingleNode("//p[contains(text(), 'The email address you provided has been flagged as undeliverable.')]"))) {
            $this->throwProfileUpdateMessageException();
        }

        // Important: Our sign-on process is changing
        if ($this->http->FindPreg("/Continue to ThankYou.com/") && $this->http->FindPreg("/Important: Our sign-on process is changing/")) {
            $this->http->GetURL("https://www.thankyou.com/updateMigrationBypassCount.jspx");

            if ($this->http->ParseForm("cmsHomeForm")) {
                $this->http->PostForm();
            }
        }
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        // ENTER YOUR IDENTIFICATION CODE
        if ($this->http->ParseForm("mfaForm")) {
            $phoneId = $this->http->FindPreg("/\"phoneDetails\":\[\{\"lastFourDigits\":\"\d+\",\"phoneId\":\"([^\"]+)/");

            if (!$phoneId) {
                $this->logger->error("phone id not found");

                return false;
            }

            $headers = [
                "Accept"       => "application/json, text/plain, */*",
                "Content-Type" => "application/json",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.thankyou.com/deliverOTPController.htm?message=0&sort={$phoneId}", "{}", $headers);
            $this->http->RetryCount = 2;
            $divId = $this->http->JsonLog()->divId ?? null;

            if (!in_array($divId, ["modal-enter-code", "modal-mfa-customer-service"])) {
                $this->logger->error("something went wrong");

                return false;
            }

            $this->Question = self::IDENTIFICATION_CODE_MSG;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "2fa";

            return false;
        }// if ($this->http->ParseForm("mfaForm"))

        if ($this->http->ParseForm("tygrWebLoginForm")) {
            $this->http->PostForm();
        }
        /*
         * The information you submitted does not match our records.
         * If you have a Citi® Credit, Consumer Banking or Sears Mastercard® account,
         * please select the appropriate account from the dropdown below.
         */
        if (
            $this->http->FindSingleNode("//span[@id = 'loginError' and contains(text(), 'The information you submitted does not match our records.')]")
            || strstr($this->http->currentUrl(), 'ErrorCode=E104&TYPostURL=https://www.thankyou.com//tyLoginGateway.htm&cmp=null')
            || strstr($this->http->currentUrl(), 'userType=tyLogin&locale=en_US&TYNewUser=false&TYForgotUUID=false&TYMigration=&ErrorCode=E104&cmp=null')
        ) {
            throw new CheckException("The information you submitted does not match our records.", ACCOUNT_INVALID_PASSWORD);
        }
        // from js
        if ($this->http->ParseForm("analyzeForm")) {
            $ip = preg_replace('#:\d+$#ims', '', $this->getDoProxies()[0]);
            $this->logger->notice("IP: $ip");
            $this->http->SetInputValue("devicePrint", "version=2&pm_fpua=mozilla/5.0 (macintosh; intel mac os x 10.9; rv:30.0) gecko/20100101 firefox/30.0|5.0 (Macintosh)|MacIntel&pm_fpsc=24|1280|800|726&pm_fpsw=&pm_fptz=6&pm_fpln=lang=en-US|syslang=|userlang=&pm_fpjv=1&pm_fpco=1&pm_fpasw=flash player|default browser|googletalkbrowserplugin|o1dbrowserplugin|quicktime plugin|javaappletplugin|sharepointbrowserplugin|skype_c2c_safari|flip4mac wmv plugin&pm_fpan=Netscape&pm_fpacn=Mozilla&pm_fpol=true&pm_fposp=&pm_fpup=&pm_fpsaw=1280&pm_fpspd=24&pm_fpsbd=&pm_fpsdx=&pm_fpsdy=&pm_fpslx=&pm_fpsly=&pm_fpsfse=&pm_fpsui=");
            $this->http->SetInputValue("ipAddress", $ip);

            $this->http->FilterHTML = false;
            $this->http->PostForm();

            if ($this->parseQuestion()) {
                return false;
            }

            $this->dummyform();
            $this->http->FilterHTML = true;
        }

        if ($this->http->ParseForm("samlform")) {
            sleep(1);
            $this->http->PostForm();
            sleep(1);

            if ($action = $this->http->FindSingleNode("//form[@name = 'relaystateform']/@action")) {
                $this->http->NormalizeURL($action);
                $this->http->GetURL($action);
            }

            if ($action = $this->http->FindSingleNode("//form[@name = 'relaystateform']/@action")) {
                sleep(1);
                $this->http->NormalizeURL($action);
                $this->http->GetURL($action);
            }
        }// if ($this->http->ParseForm("samlform"))

        if ($this->http->ParseForm("cmsHomeForm")) {
            $this->http->PostForm();
        }

        if ($this->http->ParseForm("cmsHomeForm")) {
            $this->http->PostForm();
        }

        $this->providerErrors();

        // Check that user logined
        if ($this->http->FindPreg("/memberId='([^\']+)/") || $this->http->FindNodes('//a[contains(@href, "logout")]/@href')) {
            return true;
        }

        // AccountID: 61650
        if ($this->AccountFields["Login"] == 'dloren11') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function dummyform()
    {
        $this->logger->notice("Posting dummyform");
        // dummyform
        $this->http->PostURL("https://www.thankyou.com/preLogin.jspx", []);
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

        $this->http->Form = [];
        // https://www.thankyou.com/hrtPhoneSubmit.jspx
        $this->http->SetInputValue("medium", "0");
        $this->http->SetInputValue("phonenumber", $phone);
        $this->http->PostForm();
//        $this->sendNotification("thankyou. Code was sent");
        if (!$this->http->ParseForm("formOTP2")) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->debug(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($step == '2fa') {
            $otpValue = $this->Answers[$this->Question];
            unset($this->Answers[$this->Question]);

            $this->sendNotification("2fa // RR");

            $headers = [
                "Accept" => "application/json, text/plain, */*",
            ];
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.thankyou.com/validateOTPController.htm?otpValue={$otpValue}", $headers);
            $this->http->RetryCount = 2;
            $divId = $this->http->JsonLog()->divId ?? null;

            // The Identification Code you entered is incorrect. Please enter your Identification Code again exactly as you received it.
            if ($divId == 'ReEnterCode') {
                $this->AskQuestion($this->Question, "The Identification Code you entered is incorrect. Please enter your Identification Code again exactly as you received it.", "2fa");

                return false;
            }

            $this->http->GetURL("https://www.thankyou.com/hrtStart.jspx");

            return true;
        }

        // https://www.thankyou.com/hrtValidateOTP.jspx
        $this->http->SetInputValue("otpValue", $this->Answers[$this->Question]);
        // TODO: Notifications
//        $this->ArchiveLogs = true;
//        $this->sendNotification("thankyou. Code was entered");
        if (!$this->http->PostForm()) {
            return false;
        }
        $this->logger->debug("the code was entered");
        // remove Identification Code
        unset($this->Answers[$this->Question]);
        // Invalid Code. Please re-enter.
        if ($error = $this->http->FindSingleNode("//p[contains(text(), 'The Identification Code you entered is incorrect.')]")) {
            $this->logger->error(">>> Invalid Code. Please re-enter.");
            $this->AskQuestion($this->Question, $error, "Question");

            return false;
        }
        // Your Identification Code Has Expired
        if ($error = $this->http->FindSingleNode("//h2[contains(text(), 'Your Identification Code Has Expired')]")) {
            $this->logger->error(">>> Your Identification Code Has Expired");
            $this->AskQuestion($this->Question, $error, "Question");
//            $this->http->GetURL("https://www.thankyou.com/hrtStart.jspx");
//            if ($this->parseQuestion())
            return false;
        }
        // click "Continue"
        if ($link = $this->http->FindSingleNode("//a[contains(text(), 'CONTINUE')]/@href")) {
            $this->logger->debug("click \"Continue\"");
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);

            $this->dummyform();
        }
        // For your protection, your account has been locked
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'For your protection, your account has been locked')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        $this->providerErrors();

        return true;
    }

    public function Parse()
    {
        // Balance - Points
        if ($memberId = $this->http->FindPreg("/memberId='([^\']+)/")) {
//            $this->http->GetURL("https://hub.thankyou.com/tygr-web/tyMemberInfo.htm?memberid={$memberId}&callback=&callback=memberInfo");
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.thankyou.com/tyMemberInfo.htm?memberid={$memberId}&callback=&callback=memberInfo&_=" . date("UB"));
            $this->http->RetryCount = 2;
            $this->SetBalance($this->http->FindPreg("/\"Points\"\s*:\s*\"([^\"]+)/"));
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindPreg("/\"DisplayName\"\s*:\s*\"([^\"]+)/")));
            // ThankYou Account
            $this->SetProperty("AccountNumber", $this->http->FindPreg("/\"MemberId\"\s*:\s*\"([^\"]+)/"));
//            $this->http->GetURL('https://hub.thankyou.com/tygr-web/pointsSummary.htm?cmp=nav&lid=header|my-account|points-summary');

            // AccountID: 1070829, 361003, 37445
            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                $this->http->GetURL('https://www.thankyou.com/pointsSummary.htm?src=TYUSENG');
                $this->SetBalance($this->http->FindSingleNode('//div[contains(@class, "points-header-desktop-info")]//h2[span[contains(text(), "Points Available")]]', null, true, "/(.+)\s+Point/") ?? $this->http->FindPreg("/\"userPoints\"\s*:\s*\"([^\"]+)/"));
                // ThankYou Account
                $this->SetProperty("AccountNumber", $this->http->FindPreg("/\"MemberId\"\s*:\s*\"([^\"]+)/ims"));
                $expPoints = $this->http->FindPreg("/expiringPointsTotal\":(\d+)/ims");
                $this->SetProperty('NumberOfExpirePoints', $expPoints);
                $exp = $this->http->FindPreg("/formatedPointsExpirationDate\":\"(^\"+)/ims");

                if ($expPoints > 0 && $exp) {
                    $this->sendNotification("exp balance {$exp} // RR");
                    $this->SetExpirationDate(strtotime($exp));
                }
            }
        } else {
            $this->SetBalance($this->http->FindSingleNode("//div[@id = 'account-menu-logged-in']//span[@class='points']"));
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id = 'account-menu-logged-in']//span[contains(text(), 'Hi')]", null, true, "/Hi\s*([^<]+)/")));
            // ThankYou Account
            $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//p[@id = 'thankyou-account-num']"));
        }

        // Get the nearest expiration date and number of points that will dissapear
        if ($this->Balance <= 0) {
            return;
        }
        $this->logger->info("Expiration date", ['Header' => 3]);
//        $this->http->GetURL('https://hub.thankyou.com/tygr-web/pointsSummary.htm?src=TYUSENG');
        $this->http->GetURL('https://www.thankyou.com/pointsSummary.htm?src=TYUSENG');
        /*
        if ($this->http->ParseForm('samlform')) {
            if ($this->http->PostForm()) {
                $this->http->GetURL('https://hub.thankyou.com/tygr-web/selectLogin.htm');
                if ($this->http->ParseForm('relaystateform'))
                    $this->http->PostForm();
        */
        // no points to expire
        $message = $this->http->FindSingleNode('(//p[contains(text(), "You do not have points expiring in the next 60 days or less.")])[1]');
        $this->logger->notice($message);
//                $this->http->GetURL('https://hub.thankyou.com/tygr-web/expiringPoints.htm');
        $this->http->GetURL('https://www.thankyou.com/expiringPoints.htm?src=TYUSENG');
        $this->http->GetURL('https://www.thankyou.com/pointsExpiringPaginationAjax.htm?fromRow=0&count=20&sortby=&direction=&accountKey=all&pageNum=1');
        // Find expiration dates values
        $response = $this->http->JsonLog();
        $expiringPointsDetailList = $response->expiringPointsDetailList ?? [];
        $this->logger->debug("Total {$response->totalRecords} nodes were found");
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
        /*
            }// if ($this->http->PostForm())
        }// if ($this->http->ParseForm('accountForm'))
        */
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.thankyou.com/';
        $arg['PreloadAsImages'] = true;

        return $arg;
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions))) {
            $region = 'ThankYou';
        }

        return $region;
    }
}
