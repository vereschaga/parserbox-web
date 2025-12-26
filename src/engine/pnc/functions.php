<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerPnc extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

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
        $this->http->setHttp2(true);

        $this->UseSelenium();

        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        /*
        $this->useChromePuppeteer();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN - 3;
        $request->platform = 'Win32';
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if (isset($fingerprint)) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->http->setUserAgent($fingerprint->getUseragent());
        }
        */

        $this->http->saveScreenshots = true;

        $this->usePacFile(false);
        $this->keepCookies(false); // TODO: Process killed by watchdog workaround
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->removeCookies();
//        $this->http->GetURL('https://www.pnc.com/en/personal-banking.html');
//        $this->http->GetURL('https://www.onlinebanking.pnc.com/alservlet/PNCOnlineBankingServletLogin');
            $this->http->GetURL('https://www.onlinebanking.pnc.com/alservlet/LogoutServlet?albb=true');
        } catch (UnexpectedAlertOpenException | UnexpectedJavascriptException | Facebook\WebDriver\Exception\UnexpectedAlertOpenException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException | Facebook\WebDriver\Exception\NoSuchAlertException | UnexpectedAlertOpenException | Facebook\WebDriver\Exception\UnexpectedAlertOpenException $e) {
                $this->logger->error("LoadLoginForm -> exception: " . $e->getMessage());
            } finally {
                $this->logger->debug("LoadLoginForm -> finally");
            }
        } catch (NoAlertOpenException | Facebook\WebDriver\Exception\NoSuchAlertException $e) {
            $this->logger->debug("no alert, skip");
        }

        $signon = $this->waitForElement(WebDriverBy::xpath('//input[@id = "Signon"]'), 5);

        if ($signon) {
            $signon->click();
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[contains(@id, "signonUserId"]'), 5);

        if (!$loginInput) {
            $this->driver->executeScript("try { document.querySelector('#signonUserId').style.zIndex = '100003'; } catch (e) {}");
            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "signonUserId"]'), 5);
        }

        $this->driver->executeScript("try { document.querySelector('[id *= \"wbb-password-input-\"]').style.zIndex = '100003'; } catch (e) {}");
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[contains(@id, "wbb-password-input-")]'), 5);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput) {
            return $this->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(@id, "wbb-button-")]'), 3);

        if (!$button) {
            $this->saveResponse();

            return $this->checkErrors();
        }

        $button->click();

        /*
        $this->sendSensorData();

        $this->http->GetURL('https://www.onlinebanking.pnc.com/alservlet/SignonInitServlet?devicePrint=version%3D1%26pm_fpua%3Dmozilla/5.0%20%28macintosh%3B%20intel%20mac%20os%20x%2010.15%3B%20rv%3A98.0%29%20gecko/20100101%20firefox/98.0%7C5.0%20%28Macintosh%29%7CMacIntel%26pm_fpsc%3D24%7C1536%7C960%7C871%26pm_fpsw%3D%26pm_fptz%3D5%26pm_fpln%3Dlang%3Den-US%7Csyslang%3D%7Cuserlang%3D%26pm_fpjv%3D0%26pm_fpco%3D1');

        if (!$this->http->ParseForm("alternateSignon")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("userId", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("save_user_id", "true");
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//b[contains(text(), "Scheduled Maintenance for ")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        /*
        $headers = [
            "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*
        /*;q=0.8",
            "Origin" => "https://www.pnc.com",
        ];

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }
        */

        sleep(5);
        $this->waitForElement(WebDriverBy::xpath('
            //frame[@name = "center"]
            | //div[contains(@class, "wbb-notification-header")]/h3
            | //b[contains(text(), "It looks like you\'re experiencing problems logging in.")]
            | //div[@aria-label = "Step 1: Select a Phone Number:"]//input[@id = "1"]
        '), 25);
        $iframe = $this->waitForElement(WebDriverBy::xpath('//frame[@name = "center"]'), 0);
        $this->saveResponse();

        if ($iframe) {
            $this->logger->debug("switch to iframe");
            $this->driver->switchTo()->frame($iframe);

            $this->waitForElement(WebDriverBy::xpath('
                //div[contains(@class, "wbb-notification-header")]/h3
                | //td[contains(., "Question:")]/following-sibling::td[1]
                | //div[@aria-label = "Step 1: Select a Phone Number:"]//input[@id = "1"]
                | //a[contains(text(), "Sign Off")]
                | //b[contains(text(), "It looks like you\'re experiencing problems logging in.")]
            '), 10);
            $this->saveResponse();
        }

        if ($question = $this->http->FindSingleNode('//td[contains(., "Question:")]/following-sibling::td[1]')) {
            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";
            $this->holdSession();

            return false;
        }
        // We've sent you a text message with a one-time passcode to ... . The one-time passcode you received is valid for the next 10 minutes.
//        elseif ($this->http->FindSingleNode('//div[contains(text(), "Verifying Your Identity...")]')) {
        elseif ($this->http->FindSingleNode('//div[@aria-label = "Step 1: Select a Phone Number:"]//input[@id = "1"]/@id')) {
            /*
            $this->http->GetURL("https://secure-api.pnc.com/rtl/signin/authentication-status");
            $response = $this->http->JsonLog();

            if (!isset($response->data->nextStep) || $response->data->nextStep != 'OTP_GENERATE') {
                return false;
            }

            $response->data->context->smsOptions

            $data = [
                "id"     => "1",
                "method" => "SMS",
            ];
            $headers = [
                'Accept' => 'application/json, text/plain, *
            /*',
                'apiKey' => 'mvA3n3WuR8AgddlDBN4hMxZHlmC1mcuKe0nME6Bj41RGPDQpVs1Gjt7BPmAlPDcz',
            ];
            $this->http->PostURL("https://secure-api.pnc.com/rtl/signin/generate-otp", json_encode($data), $headers);
            $response = $this->http->JsonLog();

            if (!isset($response->data->nextStep) || $response->data->nextStep != 'OTP_VERIFY') {
                return false;
            }

            $this->AskQuestion($this->Question, null, "2fa");
            */

            return $this->parseQuestion();
        }

        /*
        {"otpValue":"123456","rememberMyDevice":true}
https://secure-api.pnc.com/rtl/signin/validate-otp
        $response->errors[0]->code
        INVALID_OTP
                We did not recognize the one-time passcode you entered. Please check and try again.
        */

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "wbb-notification-header")]/h3')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'We did not recognize the information you entered. Please check the information and try again.')
                || strstr($message, 'There are no eligible accounts associated with the User ID you entered. If you would like to open an account, please visit pnc.com.')
                || $message == 'Incorrect user ID or password'
                || $message == 'You currently have no eligible accounts for Online Banking.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Please try again later. We apologize for any inconvenience.') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'Online Banking is currently revoked for this User ID.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//b[contains(text(), "It looks like you\'re experiencing problems logging in.")]')) {
            $this->DebugInfo = 'access blocked';
            $this->ErrorReason = self::ERROR_REASON_BLOCK;

            return false;
        }

        if ($this->AccountFields['Login'] == 'towfica') {
            throw new CheckException("We did not recognize the information you entered. Please check the information and try again.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //frame[@name = "center"]
            | //a[contains(text(), "PNC Rewards Center") and contains(@href, "RewardsInquiryServlet")]
        '), 10);
        $this->saveResponse();

        if (!$rewardsCenter = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "PNC Rewards Center") and contains(@href, "RewardsInquiryServlet")]'), 0)) {
            $iframe = $this->waitForElement(WebDriverBy::xpath('//frame[@name = "center"]'), 0);

            if ($iframe) {
                $this->logger->debug("switch to iframe");
                $this->driver->switchTo()->frame($iframe);
            }

            $rewardsCenter = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "PNC Rewards Center") and contains(@href, "RewardsInquiryServlet")]'), 0);
        }

        $this->saveResponse();

        if (!$rewardsCenter) {
            return;
        }

        $rewardsCenter->click();

        $this->waitForElement(WebDriverBy::xpath('
            //div[contains(@class, "rewardsSummary")]/table//tr[contains(@class, "vwAccountNoBorder")]/td[2]
            | //span[contains(text(), "Start Earning Rewards Now")]
        '), 10);
        $this->saveResponse();

        // PNC points Summary -> Total Points
        $pointsSummary = $this->http->XPath->query('//div[contains(@class, "rewardsSummary")]/table//tr[contains(@class, "vwAccountNoBorder") and td[3]]');
        $this->logger->debug("Total {$pointsSummary->length} PNC points Summary rows were found");

        foreach ($pointsSummary as $points) {
            $displayName = $this->http->FindSingleNode('td[1]', $points);
            $this->logger->debug("card -> {$displayName}");
            $balance = $this->http->FindSingleNode('td[2]', $points);
            $expiringBalance = $this->http->FindSingleNode('td[3]', $points);

            $subAcc = [
                "Code"            => "PointsSummary" . md5($displayName),
                "DisplayName"     => $displayName,
                "Balance"         => $balance,
                "ExpiringBalance" => $expiringBalance,
            ];

            $expNodes = $this->http->XPath->query('following-sibling::tr[1]//td[contains(., "Exp")]', $points);
            $this->logger->debug("Total {$expNodes->length} exp dates were found");

            foreach ($expNodes as $expNode) {
                $expBalance = $this->http->FindSingleNode('text()[1]', $expNode, true, "/(.+)\s+pts/");
                $expDate = $this->http->FindSingleNode('span', $expNode, true, "/Exp.\s*(.+)/");
                $this->logger->debug("[$expDate] -> {$expBalance}");

                if (
                    $expBalance > 0
                    && (!isset($exp) || $exp > $expDate)
                ) {
                    $exp = strtotime($expDate);
                    $subAcc['ExpiringBalance'] = $expDate;
                    $subAcc['ExpirationDate'] = $exp;
                }
            }

            $this->AddSubAccount($subAcc);
        }// foreach ($pointsSummary as $points)

        // PNC Purchase Payback Cash Summary
        $paybackCash = $this->http->XPath->query('//div[div[h1[contains(text(), "PNC Purchase Payback Cash Summary")]]]/following-sibling::div//table//tr[contains(@class, "vwAccount")]');
        $this->logger->debug("Total {$paybackCash->length} Payback Cash rows were found");

        foreach ($paybackCash as $cash) {
            $displayName = $this->http->FindSingleNode('td[1]', $cash);
            $this->logger->debug("card -> {$displayName}");
            $balance = $this->http->FindSingleNode('td[2]', $cash);

            $this->AddSubAccount([
                "Code"           => "PurchasePaybackCashSummary" . md5($displayName),
                "DisplayName"    => $displayName . " (Cash Summary)",
                "Balance"        => $balance,
                'Currency'       => "$",
            ]);
        }

        // PNC Credit Cards Rewards Summary
        $rewards = $this->http->XPath->query('//div[div[h1[contains(text(), "PNC Credit Cards Rewards Summary")]]]/following-sibling::div//table[1]//tr[not(contains(@class, "Header"))]');
        $this->logger->debug("Total {$rewards->length} Credit Cards Rewards Summary");

        foreach ($rewards as $reward) {
            $displayName = Html::cleanXMLValue(implode('', $this->http->FindNodes('td[1]/text()', $reward)));
            $this->logger->debug("card -> {$displayName}");
            $balance = $this->http->FindSingleNode('td[3]', $reward);

            $this->AddSubAccount([
                "Code"           => "CreditCardsRewardsSummary" . md5($displayName),
                "DisplayName"    => $displayName . " (Rewards Summary)",
                "Balance"        => $balance,
                'Currency'       => "$",
            ]);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (
                $this->http->FindSingleNode('//span[contains(text(), "Start Earning Rewards Now")]')
                // AccountID: 6132461
                || $this->http->FindSingleNode('//td[contains(text(), "This card cannot be enrolled. Please call")]')
                // AccountIDL: 6131910
//                || $this->http->FindSingleNode('//img[contains(@title, "We are in the process of updating your account.  Please check back later.")]')
                || (
                    // AccountID: 5873046
                    !empty($this->Properties['SubAccounts'])
                    && ($paybackCash->length || $pointsSummary->length || $paybackCash->length)
                )
                // AccountID: 3990535
                || (
                    count($this->http->FindNodes('//div[contains(@class, "hiddenOverflow")]/table//tr[@id = "cc_detail_row"]/td[2]')) > 1
                    && ($paybackCash->length === 0 && $pointsSummary->length === 0 && $paybackCash->length === 0)
                )
            ) {
                $this->SetBalanceNA();
            }
        }

        $this->http->GetURL("https://www.onlinebanking.pnc.com/alservlet/PersonalInformationServlet");
        $this->waitForElement(WebDriverBy::xpath('//tr[th[contains(., "Customer Address")]]/following-sibling::tr/td[1]'), 5);
        $this->saveResponse();
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('(//tr[th[contains(., "Customer Address")]]/following-sibling::tr/td[1]/text()[1])[1]')));
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        $sendPhoneBtn = $this->waitForElement(WebDriverBy::xpath('//div[@aria-label = "Step 1: Select a Phone Number:"]//input[@id = "1"]'), 0);
        $btnGenerateOtp = $this->waitForElement(WebDriverBy::id('btnGenerateOtp'), 0);
        $this->saveResponse();

        if (!$sendPhoneBtn || !$btnGenerateOtp) {
            return false;
        }

        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $sendPhoneBtn->click();
        $btnGenerateOtp->click();

        $question = $this->waitForElement(WebDriverBy::xpath('//p[
            contains(., "We\'ve sent you a text message with a one-time passcode to")
            or contains(., "We\'ve called you at")
        ]'), 5);
        $this->saveResponse();

        if (!$question) {
            return false;
        }

        $this->AskQuestion($question->getText(), null, "2fa");
        /*
        if (
            !$this->waitForElement(WebDriverBy::xpath("
                //strong[contains(text(), 'How do you want to receive your unique ID code?')]
            "), 0)
            && !$this->waitForElement(WebDriverBy::xpath("
                //a[@id = 'sendEmail']
            "), 0, false)
        ) {
            return false;
        }

        $sendEmailBtn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'sendEmailBtn']"), 0)
            ?? $this->waitForElement(WebDriverBy::xpath("//a[@id = 'sendEmail']"), 0, false);
        $this->saveResponse();

        if (!$sendEmailBtn && $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'No email address found')]"), 0)) {
            $sendPhoneBtn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'sendTextBtn']"), 0);

            if (!$sendPhoneBtn && $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'No mobile number found')]"), 0)) {
                $this->throwProfileUpdateMessageException();
            }
        }

        if (!$sendEmailBtn && !$sendPhoneBtn) {
            return false;
        }

        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        if ($sendEmailBtn) {
            $sendEmailBtn->click();
        } else {
            $sendPhoneBtn->click();
        }

        $receivedMyCodeYesClickable = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'receivedMyCodeYesClickable'] | input[@id = 'didYouReceiveYourCode_input_0']"), 7);
        $this->saveResponse();

        if (!$receivedMyCodeYesClickable) {
            return false;
        }
        $receivedMyCodeYesClickable->click();
        */

        return false;
    }

    public function ProcessStep($step)
    {
        if ($step == '2fa') {
            return $this->processCode();
        }

        $iframe = $this->waitForElement(WebDriverBy::xpath('//frame[@name = "center"]'), 5);
        $this->saveResponse();

        if ($iframe) {
            $this->logger->debug("switch to iframe");
            $this->driver->switchTo()->frame($iframe);
        }

        $answerInput = $this->waitForElement(WebDriverBy::xpath('//input[@name="answer"]'), 5);
        $this->saveResponse();

        if (!$answerInput) {
            $this->logger->error("something went wrong");

            return false;
        }

        $this->saveResponse();
        $answerInput->clear();
        $answerInput->sendKeys($this->Answers[$this->Question]);

        $contBtn = $this->waitForElement(WebDriverBy::xpath('//input[@value = "Continue" and not(@disabled)]'), 3);
        $this->saveResponse();

        if (!$contBtn) {
            $this->logger->error("something went wrong");

            return false;
        }

        $contBtn->click();
        sleep(5);
        $this->saveResponse();

        return true;
    }

    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            "7a74G7m23Vrp0o5c9232891.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:98.0) Gecko/20100101 Firefox/98.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,405748,6533666,1536,871,1536,960,1537,450,1537,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6018,0.819426922409,824533266833,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,2297,620,0;1,0,0,0,2560,883,0;-1,2,-94,-102,0,0,0,0,2297,620,0;1,0,0,0,2560,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.onlinebanking.pnc.com/alservlet/SignonInitServlet?devicePrint=version%3D1%26pm_fpua%3Dmozilla/5.0%20%28macintosh%3B%20intel%20mac%20os%20x%2010.15%3B%20rv%3A98.0%29%20gecko/20100101%20firefox/98.0%7C5.0%20%28Macintosh%29%7CMacIntel%26pm_fpsc%3D24%7C1536%7C960%7C871%26pm_fpsw%3D%26pm_fptz%3D5%26pm_fpln%3Dlang%3Den-US%7Csyslang%3D%7Cuserlang%3D%26pm_fpjv%3D0%26pm_fpco%3D1-1,2,-94,-115,1,32,32,0,0,0,0,0,0,1649066533666,-999999,17641,0,0,2940,0,0,1,0,0,B860205D469CD773D82D35D45B98F198~-1~YAAQD54qFwbs+tV/AQAAoWEG9Ae6fBDK/aj27n12CavpjL/Ujc1mEqgGp1HO072UvW+UJxVW2ggydj+OHKHePBuHIyoC85E15RvIe8evJxd/25m4j33qdeHnIkD0C10nwZ7Mjwb3coq+r3Kg2xqPIjT9VGfsKtzFxk+3JucHLqla2N0NfKeX8MsS0uHZlnzfNUUMnJYONHvYF1A3uPMRD6eCf8YwjbjfjQK+uYzD90d+E8CuSjnFpMRXe5cH4BwTSBr97Ca3syAuQVvf00grYmKwakR1UYKywTYQPEKQCyeyevLSm1TUxsvDZvq6ehrdo5kB0G1OgAxKiUhSd19YY2RmPt7DyzjPM+TI8HMrpjfU1tVsHDr1SJmEj6GhB44eoX4MaF8niIE=~-1~-1~-1,36177,-1,-1,26067385,PiZtE,36952,74,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,19600911-1,2,-94,-118,112273-1,2,-94,-129,-1,2,-94,-121,;1;-1;0",
        ];

        $secondSensorData = [
            "7a74G7m23Vrp0o5c9232891.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:98.0) Gecko/20100101 Firefox/98.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,405748,6533666,1536,871,1536,960,1537,450,1537,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6018,0.294622031147,824533266833,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,2297,620,0;1,0,0,0,2560,883,0;-1,2,-94,-102,0,0,0,0,2297,620,0;1,0,0,0,2560,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.onlinebanking.pnc.com/alservlet/SignonInitServlet?devicePrint=version%3D1%26pm_fpua%3Dmozilla/5.0%20%28macintosh%3B%20intel%20mac%20os%20x%2010.15%3B%20rv%3A98.0%29%20gecko/20100101%20firefox/98.0%7C5.0%20%28Macintosh%29%7CMacIntel%26pm_fpsc%3D24%7C1536%7C960%7C871%26pm_fpsw%3D%26pm_fptz%3D5%26pm_fpln%3Dlang%3Den-US%7Csyslang%3D%7Cuserlang%3D%26pm_fpjv%3D0%26pm_fpco%3D1-1,2,-94,-115,1,32,32,0,0,0,0,508,0,1649066533666,4,17641,0,0,2940,0,0,508,0,0,B860205D469CD773D82D35D45B98F198~-1~YAAQD54qFwbs+tV/AQAAoWEG9Ae6fBDK/aj27n12CavpjL/Ujc1mEqgGp1HO072UvW+UJxVW2ggydj+OHKHePBuHIyoC85E15RvIe8evJxd/25m4j33qdeHnIkD0C10nwZ7Mjwb3coq+r3Kg2xqPIjT9VGfsKtzFxk+3JucHLqla2N0NfKeX8MsS0uHZlnzfNUUMnJYONHvYF1A3uPMRD6eCf8YwjbjfjQK+uYzD90d+E8CuSjnFpMRXe5cH4BwTSBr97Ca3syAuQVvf00grYmKwakR1UYKywTYQPEKQCyeyevLSm1TUxsvDZvq6ehrdo5kB0G1OgAxKiUhSd19YY2RmPt7DyzjPM+TI8HMrpjfU1tVsHDr1SJmEj6GhB44eoX4MaF8niIE=~-1~-1~-1,36177,609,1751425909,26067385,PiZtE,100797,57,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,25610054;2077731666;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5227-1,2,-94,-116,19600911-1,2,-94,-118,113648-1,2,-94,-129,,,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,,,,0-1,2,-94,-121,;2;3;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        sleep(1);
        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;

        return true;
    }

    protected function processCode()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Entering code', ['Header' => 3]);

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $idCodeInput = $this->waitForElement(WebDriverBy::id('otp'), 5);
        $this->saveResponse();

        if (!$idCodeInput) {
            $this->logger->error("something went wrong");

            return false;
        }

        $this->saveResponse();
        $idCodeInput->clear();
        $idCodeInput->sendKeys($answer);

        $contBtn = $this->waitForElement(WebDriverBy::xpath('//input[@value = "Continue" and not(@disabled)]'), 3);
        $this->saveResponse();

        if (!$idCodeInput || !$contBtn) {
            $this->logger->error("something went wrong");

            return false;
        }

        $contBtn->click();
        sleep(5);
        $error = $this->waitForElement(WebDriverBy::xpath("//p[@class = 'main-error']"), 0);
        $this->saveResponse();

        if ($error) {
            $message = $error->getText();
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'We are experiencing technical difficulties with Online Banking. We should be back up shortly.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // We did not recognize the one-time passcode you entered. Please check and try again.
        $error = $this->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'We did not recognize the one-time passcode you entered. Please check and try again.')]"), 0);

        if (!empty($error)) {
            $error = $error->getText();
            $this->logger->notice("error: " . $error);
            $this->holdSession();
            $this->AskQuestion($this->Question, $error, "2fa");

            return false;
        }// if (!empty($error))

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(text(), "Sign Off")]')) {
            return true;
        }

        return false;
    }
}
