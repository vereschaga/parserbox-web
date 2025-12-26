<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerTopcashback extends TAccountChecker
{
    use ProxyList;

    public $ContinueToStep;

    public $regionOptions = [
        ""        => "Select a Region", /*checked*/
        "Germany" => "Germany",
        "USA"     => "USA",
        "UK"      => "United Kingdom",
    ];

    private $domain;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $captcha = false;

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields);
        $fields["Login2"]["Options"] = $this->regionOptions;
    }

    public static function FormatBalance($fields, $properties)
    {
        switch ($fields['Login2']) {
            case 'USA':
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");

                break;

            case 'Germany':
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");

                break;

            case 'UK':
            default:
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");

                break;
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setRightDomain();

        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');

        if ($this->domain == 'co.uk') {
//            $this->http->SetProxy($this->proxyUK());
            $this->setProxyBrightData(null, 'static', 'gb');
        }

        if ($this->domain == 'de') {
            $this->http->setHttp2(true);
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;

        if ($this->domain == 'de') {
            $this->http->GetURL("https://www.topcashback.de/konto/uebersicht/", [], 20);
        } else {
            $this->http->GetURL("https://www.topcashback." . $this->domain . "/account/overview/", [], 20);
        }
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function setRightDomain()
    {
        $this->logger->notice(__METHOD__);

        switch ($this->AccountFields['Login2']) {
            case 'Germany':
                $this->domain = 'de';

                break;
            // Region "USA"
            case "USA":
                $this->domain = 'com';

                break;
            // Region "UK"
            default:
                $this->domain = 'co.uk';
        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        $this->http->setCookie("CookiesEnabled", "true", ".topcashback." . $this->domain);

        if ($this->domain == 'de') {
            $this->http->GetURL("https://www.topcashback." . $this->domain . "/keine-anmeldung?PageRequested=https%3a%2f%2fwww.topcashback." . $this->domain . "/konto/auszahlungen/");
        } else {
            $this->http->GetURL("https://www.topcashback." . $this->domain . "/logon?PageRequested=https://www.topcashback." . $this->domain . "/account/payments");
        }

        if (!$this->http->ParseForm("aspnetForm")) {
            return $this->checkErrors();
        }

        if ($this->http->InputExists('ctl00$GeckoOneColPrimary$Login$txtEmail')) {
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$Login$txtEmail', $this->AccountFields['Login']);
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$Login$loginPasswordInput', $this->AccountFields['Pass']);
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$Login$Loginbtn', 'Login');
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$Login$CaptchaSubmit', 'Login');
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$Login$chkRemeberMe', 'on');
        } elseif ($this->http->InputExists('ctl00$GeckoOneColPrimary$LoginRefactor1$txtEmail')) {
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$LoginRefactor1$txtEmail', $this->AccountFields['Login']);
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$LoginRefactor1$txtPassword', $this->AccountFields['Pass']);
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$LoginRefactor1$Loginbtn', 'Login');
        } elseif ($this->http->InputExists('ctl00$GeckoOneColPrimary$LoginV2$txtEmail')) {
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$LoginV2$txtEmail', $this->AccountFields['Login']);
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$LoginV2$txtPassword', $this->AccountFields['Pass']);
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$LoginV2$CaptchaSubmit', 'Login');
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$Login$chkRemeberMe', 'on');
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$signUpV2$cbOptIn', 'on');

            $form = [
                "__LASTFOCUS"                                                                       => "",
                "__EVENTTARGET"                                                                     => "",
                "__EVENTARGUMENT"                                                                   => "",
                "__VIEWSTATE"                                                                       => $this->http->Form["__VIEWSTATE"],
                "__VIEWSTATEGENERATOR"                                                              => $this->http->Form["__VIEWSTATEGENERATOR"],
                "__EVENTVALIDATION"                                                                 => $this->http->Form["__EVENTVALIDATION"],
                'ctl00$hidAwinTracking'                                                             => $this->http->Form['ctl00$hidAwinTracking'],
                'fakeemailforchromeautocompletebug'                                                 => "",
                'ctl00$GeckoOneColPrimary$JoinForm$BrowserTimeOffset$txtTimeOffId'                  => $this->http->Form['ctl00$GeckoOneColPrimary$JoinForm$BrowserTimeOffset$txtTimeOffId'],
                'ctl00$GeckoOneColPrimary$JoinForm$BrowserTimeOffset$txtTimeId'                     => $this->http->Form['ctl00$GeckoOneColPrimary$JoinForm$BrowserTimeOffset$txtTimeId'],
                'ctl00$GeckoOneColPrimary$JoinForm$DeviceFingerprint$deviceFingerprintField'        => $this->http->Form['ctl00$GeckoOneColPrimary$JoinForm$DeviceFingerprint$deviceFingerprintField'],
                'ctl00$GeckoOneColPrimary$JoinForm$emailInput'                                      => "",
                'ctl00$GeckoOneColPrimary$JoinForm$passwordInput'                                   => "",
                'ctl00$GeckoOneColPrimary$JoinForm$Token'                                           => $this->http->Form['ctl00$GeckoOneColPrimary$JoinForm$Token'],
                'ctl00$GeckoOneColPrimary$JoinForm$CaptchaHandler$FailedCaptchaResponseField'       => $this->http->Form['ctl00$GeckoOneColPrimary$JoinForm$CaptchaHandler$FailedCaptchaResponseField'],
                'ctl00$GeckoOneColPrimary$JoinForm$CaptchaHandler$CPRField'                         => $this->http->Form['ctl00$GeckoOneColPrimary$JoinForm$CaptchaHandler$CPRField'],
                //                '__RequestVerificationToken'                                                        => $this->http->Form['__RequestVerificationToken'],
                'ctl00$GeckoOneColPrimary$signUpV2$cbOptIn'                                         => "on",
                'ctl00$GeckoOneColPrimary$signUpV2$drpHeardFrom'                                    => "(Optional) Wo hast Du uns entdeckt?",
                'ctl00$GeckoOneColPrimary$LoginV2$btoControl$txtTimeOffId'                          => $this->http->Form['ctl00$GeckoOneColPrimary$LoginV2$btoControl$txtTimeOffId'],
                'ctl00$GeckoOneColPrimary$LoginV2$btoControl$txtTimeId'                             => $this->http->Form['ctl00$GeckoOneColPrimary$LoginV2$btoControl$txtTimeId'],
                'ctl00$GeckoOneColPrimary$LoginV2$deviceFingerprintControl$deviceFingerprintField'  => $this->http->Form['ctl00$GeckoOneColPrimary$LoginV2$deviceFingerprintControl$deviceFingerprintField'],
                //                'fakepasswordforchromeautocompeletbug'                                              => $this->http->Form['fakepasswordforchromeautocompeletbug'],
                'ctl00$GeckoOneColPrimary$LoginV2$txtEmail'                                         => $this->AccountFields['Login'],
                'ctl00$GeckoOneColPrimary$LoginV2$loginPasswordInput'                               => $this->AccountFields['Pass'],
                'ctl00$GeckoOneColPrimary$LoginV2$CaptchaHandler$FailedCaptchaResponseField'        => '',
                'ctl00$GeckoOneColPrimary$LoginV2$CaptchaHandler$CPRField'                          => '',
                'ctl00$GeckoOneColPrimary$LoginV2$token'                                            => $this->http->Form['ctl00$GeckoOneColPrimary$LoginV2$token'],
                'ctl00$GeckoOneColPrimary$LoginV2$CaptchaSubmit'                                    => 'Login',
                'ctl00$GeckoOneColPrimary$LoginV2$chkRememberMe'                                    => 'on',
            ];

            unset($this->http->Form);
            $this->http->Form = $form;
        } else {
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$LoginRefactor$txtEmail', $this->AccountFields['Login']);
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$LoginRefactor$txtPassword', $this->AccountFields['Pass']);
            $this->http->SetInputValue('ctl00$GeckoOneColPrimary$LoginRefactor$Loginbtn', 'Login');
        }

        $key = 'ctl00$GeckoOneColPrimary$Login$CaptchaControl1';

        if (!isset($this->http->Form['ctl00$GeckoOneColPrimary$Login$CaptchaControl1'])) {
            $key = 'ctl00$GeckoOneColPrimary$Login$CaptchaHandler$CaptchaControl1';
        }

        if (!isset($this->http->Form['ctl00$GeckoOneColPrimary$Login$CaptchaHandler$CaptchaControl1'])) {
            $key = 'ctl00$GeckoOneColPrimary$Login$CaptchaHandler$CaptchaCodeTextBox';
        }

        if (isset($this->http->Form[$key])) {
            $this->captcha = true;
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->http->SetInputValue($key, $captcha);
        }// if (isset($this->http->Form[$key]))

        elseif (
            $this->http->FindSingleNode("//div[@id='ctl00_GeckoOneColPrimary_Login_pnlLoginArea']//div[@class = 'g-recaptcha']/@data-sitekey")
            || $this->http->FindSingleNode("//script[contains(@src,'/recaptcha/api/challenge?k=')]/@src")
            || $this->http->FindSingleNode('//div[@id = "ctl00_GeckoOneColPrimary_Login_pnlLoginArea"]//script[contains(text(), "\'size\': \'invisible\'") or contains(text(), "sitekey")]')
            || $this->http->FindSingleNode('//div[@id = "ctl00_GeckoOneColPrimary_LoginV2_pnlLoginArea"]//script[contains(text(), "\'size\': \'invisible\'") or contains(text(), "sitekey")]')
        ) {
            $this->captcha = true;
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Sorry, we're currently undergoing maintenance.
        if ($message = $this->http->FindPreg('/(Sorry, we\'re currently undergoing maintenance\.)/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, An error has occurred
        if ($message = $this->http->FindPreg('/(Sorry, An error has occurred\.)/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Site Under Construction
        if ($message = $this->http->FindSingleNode('
                //title[contains(text(), "Site Under Construction")]
                | //p[contains(text(), "We\'re currently under maintenance.")]
                | //h1[contains(text(), "Sorry, our services are currently unavailable")]
                | //p[contains(text(), "Our website and app will be unavailable for a short while, all being well, about half an hour.")]
        ')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            // Server Error in '/' Application
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            // The service is unavailable
            || $this->http->FindPreg("/(The service is unavailable\.)/ims")
            // The requested resource is not found
            || $this->http->FindSingleNode("//p[contains(text(), 'The requested resource is not found')]")
            // Something seems to have gone wrong...
            || $this->http->FindSingleNode("//span[contains(text(), 'Something seems to have gone wrong...')]")
            // GATEWAY_TIMEOUT
            || $this->http->Response['code'] == 504) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->domain == 'co.uk') {
            $this->http->GetURL('https://www.topcashback.co.uk/');
            // We're just undergoing essential maintenance.
            if ($message = $this->http->FindSingleNode("(//font[contains(text(),'re just undergoing essential maintenance.')])[1]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->domain == 'co.uk')
        /*
        // retries
        $errorMessage = null;
        if ($this->domain == 'co.uk')
            $errorMessage = self::CAPTCHA_ERROR_MSG;
        if (strstr($this->http->currentUrl(), "https://www.topcashback.".$this->domain."/logon?PageRequested=http")
            || strstr($this->http->currentUrl(), "://www.topcashback.".$this->domain."/NoLogin?PageRequested=http"))
            throw new CheckRetryNeededException(3, 7, $errorMessage);
        */

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        switch ($this->AccountFields['Login2']) {
            // Region "Germany"
            case "Germany":
                $this->domain = 'de';
                $arg["SuccessURL"] = "https://www.topcashback.de/konto/auszahlungen/";

                return $arg;

                break;
            // Region "USA"
            case "USA":
                $this->domain = 'com';

                break;
            // Region "UK"
            default:
                $this->domain = 'co.uk';
        }
        $arg["SuccessURL"] = "https://www.topcashback." . $this->domain . "/account/payments";

        return $arg;
    }

    public function Login()
    {
        $this->http->setMaxRedirects(7);

        if (!$this->http->PostForm([], 100)) {
            return $this->checkErrors();
        }

        // Oops... login has failed
        if ($message = $this->http->FindSingleNode("//div[
                @id = 'ctl00_GeckoOneColPrimary_LoginRefactor1_pnlOopsLoginFailed'
                or @id = 'ctl00_GeckoOneColPrimary_Login_pnlOopsLoginFailed'
                or @id = 'ctl00_GeckoOneColPrimary_LoginV2_pnlOopsLoginFailedMemberNotEnabled'
            ]")
        ) {
            if (
                strstr($message, 'The code you typed does not match the code in the image.')
                || strstr($message, 'The code you typed has expired')
            ) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 7);
            } else {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }// if ($message = $this->http->FindSingleNode("//div[@id = 'ctl00_GeckoOneColPrimary_LoginRefactor1_pnlOopsLoginFailed']"))
        // Captcha input was incorrect.
        if ($message = $this->http->FindSingleNode("//div[
                    @id = 'ctl00_GeckoOneColPrimary_Login_pnlCaptchaFailed'
                    or @id = 'ctl00_GeckoOneColPrimary_LoginV2_pnlCaptchaFailed'
                    or @id = 'ctl00_GeckoOneColPrimary_Login_pnlCaptchaFailed'
            ]", null, true, "/(?:Captcha input was incorrect|Captcha Eingabe war falsch)/")
        ) {
            $this->logger->error($message);

            if ($this->captcha === false) {
                $this->DebugInfo = 'captcha key not found';

                return false;
            }
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
        }
        // Oops, login failed.
        if ($message = $this->http->FindSingleNode("//span[
                @id = 'ctl00_GeckoOneColPrimary_LoginRefactor1_lblLoginFailedMemberNotEnabled'
                or @id = 'ctl00_GeckoOneColPrimary_Login_lblLoginFailedMemberNotEnabled'
                or @id = 'ctl00_GeckoOneColPrimary_LoginV2_lblLoginFailed'
            ]", null, true, "/(?:Oops, login failed\.|Ups, die Anmeldung ist fehlgeschlagen\.)/ims")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // provider error
        if ($this->http->FindSingleNode("//span[contains(text(), 'Something seems to have gone wrong...')]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->domain == 'de') {
            $this->http->GetURL("https://www.topcashback.de/konto/uebersicht/");
        } else {
            $this->http->GetURL("https://www.topcashback." . $this->domain . "/account/overview/");
        }
        // Your account needs to be authenticated
        if ($message = $this->http->FindPreg("/(Your account needs to be authenticated\.)/ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Your account needs to be confirmed by following a link that you should have received from TopCashBack.", ACCOUNT_PROVIDER_ERROR);
        }
        // Choose between the TopCashBack Classic and TopCashBack Plus
        if ((strstr($this->http->currentUrl(), 'https://www.topcashback.co.uk/change-membership-level')
                && $this->http->FindSingleNode("//span[contains(text(), 'Exciting new changes at TopCashback...')]"))
            // Please select a secure question below which we may use to verify your identity in the future
            || $this->http->FindSingleNode("//span[contains(text(), 'Please select a secure question below which we may use to verify your identity in the future')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'Please select a security question from the list below. We may use this in the future if we need to verify who you are.')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'Please select a security question from the list below. Please remember the answer you set as we will ask you this in the future')]")
            || $this->http->FindSingleNode("//span[contains(normalize-space(text()), 'Please select a security question from the list below. You will be asked this question if we ever need to verify who you are in the future')]")
            || $this->http->FindSingleNode("//span[contains(text(), '请选择一个安全问题，以便用于您以后的身份验证。')]")
            || $this->http->FindSingleNode("//span[contains(text(), '请您选择以下安全问题，如果将来我们需要验证您的身份的时候，也许会需要您回答这些问题。')]")) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // do not switch to chinese region
        if ($this->http->currentUrl() == "http://www.topcashback." . $this->domain . "/switch-region" && $this->http->FindPreg("/No thanks, I want to stay on TopCashback.com/")
            && $this->http->ParseForm("aspnetForm")) {
            $this->logger->notice("Stay on TopCashback.com");
            $this->http->SetInputValue("__EVENTTARGET", 'ctl00$GeckoOneColPrimary$btnDoNotMoveAccount');
            $this->http->PostForm();
        }

        // If there is a record 'Member********'
        $accountNumber = $this->http->FindSingleNode("//span[@class = 'helv30bold']/text()", null, true, "/Member([^<]+)/ims");
        $this->SetProperty("AccountNumber", $accountNumber);
        // Balance - Outstanding    // refs #14471
        $this->SetBalance($this->http->FindSingleNode("//span[@id = 'ctl00_GeckoTwoColPrimary_AmountsBox_lblOutstanding']"));
        // Paid
        $this->SetProperty("Paid", $this->http->FindSingleNode("//span[@id = 'ctl00_GeckoTwoColPrimary_AmountsBox_lblPaid']"));
        // Total
        $this->SetProperty("TotalEarnings", $this->http->FindSingleNode("//span[@id = 'ctl00_GeckoTwoColPrimary_AmountsBox_lblTotal']"));

        // Cashback By Merchant
        if ($this->domain == 'de') {
            $this->http->GetURL("https://www.topcashback.de/konto/dashboard/");
        } else {
            $this->http->GetURL("https://www.topcashback." . $this->domain . "/account/dashboard/");
        }
        // Pending
        $pending = $this->http->FindSingleNode("
                //span[contains(@id,'MyDashboardControl_ctl00_lblpending') and contains(text(),'Pending')]/ancestor::div[1]
                | //div[contains(@class, 'gecko-dashboard-main')]//div[@id = 'gecko-pending-text']
            ", null, true, self::BALANCE_REGEXP_EXTENDED);
        $this->AddSubAccount([
            "Code"              => "topcashbackPending",
            "DisplayName"       => "Pending",
            "Balance"           => $pending,
            "BalanceInTotalSum" => true,
        ]);
        // Confirmed
        $confirmed = $this->http->FindSingleNode("
            //span[contains(@id,'MyDashboardControl_ctl00_lblconfirm') and contains(text(),'Confirmed')]/ancestor::div[1]
            | //div[contains(@class, 'gecko-dashboard-main')]//div[@id = 'gecko-confirmed-text']
        ", null, true, self::BALANCE_REGEXP_EXTENDED);
        $this->AddSubAccount([
            "Code"              => "topcashbackConfirmed",
            "DisplayName"       => "Confirmed",
            "Balance"           => $confirmed,
            "BalanceInTotalSum" => true,
        ]);
        // Payable
        $payable = $this->http->FindSingleNode("
                //span[contains(@id,'MyDashboardControl_ctl00_lblpayable') and contains(text(),'Payable')]/ancestor::div[1]
                | //div[contains(@class, 'gecko-dashboard-main')]//div[@id = 'gecko-payable-text']
        ", null, true, self::BALANCE_REGEXP_EXTENDED);
        $this->AddSubAccount([
            "Code"              => "topcashbackPayable",
            "DisplayName"       => "Payable",
            "Balance"           => $payable,
            "BalanceInTotalSum" => true,
        ]);
//        if ($pending && $confirmed && $payable) {
//            $balance = $pending + $confirmed + $payable;
//            $this->SetBalance($balance);
//        }

        if (!isset($accountNumber)) {
            if ($this->domain == 'de') {
                $this->http->GetURL("https://www.topcashback.de/konto/einstellungen/");
            } else {
                $this->http->GetURL("https://www.topcashback." . $this->domain . "/account/details");
            }

            if ($this->http->ParseForm("aspnetForm") && $this->http->InputExists('psw')) {
                $this->http->SetInputValue('ctl00$GeckoOneColPrimary$ctl00$passwordInput', $this->AccountFields['Pass']);
//                $this->http->SetInputValue('psw', $this->AccountFields['Pass']);
                $this->http->SetInputValue('__EVENTTARGET', 'ctl00$GeckoOneColPrimary$ctl00$btnContinue');
                $this->http->PostForm();
            }

            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[contains(text(), "Dein Name") or contains(text(), "Your Name")]/following-sibling::div[1]//span[@id]')));
        }
        // To continue to your requested page, you must first either confirm or revert your recent change to your payout details.
        if ($message = $this->http->FindPreg('/To continue to your requested page, you must first either confirm or revert your recent change to your payout details\./')) {
            $this->logger->info('Failed to parse name, could not open details page');
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Undergoing maintenance
            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Undergoing maintenance....') or contains(text(), '网站正在维护中....')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $captcha = false;

        if ($img = $this->http->FindSingleNode("//img[contains(@src, 'CaptchaImage.aspx?') or contains(@src, 'BotDetectCaptcha.ashx?get=image')]/@src")) {
            $this->logger->notice("Just captcha...");
            $this->http->NormalizeURL($img);
            $http2 = clone $this->http;
            $file = $http2->DownloadFile($img, "jpeg");
            $recognizer = $this->getCaptchaRecognizer();
            $recognizer->RecognizeTimeout = 120;
            $captcha = $this->recognizeCaptcha($recognizer, $file);
            unlink($file);
        }// if ($img = $this->http->FindSingleNode("//img[contains(@src, 'CaptchaImage.aspx?') or contains(@src, 'BotDetectCaptcha.ashx?get=image')]))
        elseif (
            ($key = $this->http->FindSingleNode("//div[@id='ctl00_GeckoOneColPrimary_Login_pnlLoginArea']//div[@class = 'g-recaptcha']/@data-sitekey"))
            || ($key = $this->http->FindSingleNode("//script[contains(@src,'/recaptcha/api/challenge?k=')]/@src", null, false, '/k=(.+?)&lang=/'))
            || ($key = $this->http->FindSingleNode('//div[@id = "ctl00_GeckoOneColPrimary_Login_pnlLoginArea"]//script[contains(text(), "\'size\': \'invisible\'") or contains(text(), "sitekey")]', null, false, '/sitekey\':\s*"([^\"]+)"/'))
            || ($key = $this->http->FindSingleNode('//div[@id = "ctl00_GeckoOneColPrimary_LoginV2_pnlLoginArea"]//script[contains(text(), "\'size\': \'invisible\'") or contains(text(), "sitekey")]', null, false, '/sitekey\':\s*"([^\"]+)"/'))
        ) {
            $this->logger->notice("ReCaptcha...");
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
        }// elseif ($captchaURL = $this->http->FindPreg('#https://www.google.com/recaptcha/api/challenge\?k=[^"]+#i'))

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//a[contains(@href, 'logout') or contains(@href, 'abmelden')]/@href")
            && !strstr($this->http->currentUrl(), 'maintenance')
            && !stristr($this->http->currentUrl(), 'SetSecurityQuestion?request=%2faccount%2foverview%2f')
        ) {
            return true;
        }

        return false;
    }
}
