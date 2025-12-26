<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerShoppersdrugmart extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL("https://www1.shoppersdrugmart.ca/en/optimum/userheaderinfo");

        if ($this->http->FindPreg("/\"TotalPoints\":\"([^\"]+)/")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www1.shoppersdrugmart.ca/en/optimum-new/my-optimum");
        $xpid = $this->http->FindPreg('/xpid:"([^\"]+)/');
        $token = $this->http->FindSingleNode("//div[@id = 'OptHdrForm']/input[@name = '__RequestVerificationToken']/@value");

        if (!$this->http->FindNodes("//input[@name = 'cn']/@name") || !isset($xpid, $token)) {
            return $this->checkErrors();
        }
        $this->http->Form = [];
        $this->http->FormURL = 'https://www1.shoppersdrugmart.ca/en/Optimum/Login';
        $this->http->setDefaultHeader("__RequestVerificationToken", $token);
        $this->http->setDefaultHeader("X-NewRelic-ID", $xpid);
        $this->http->setDefaultHeader("Accept", "application/json, text/javascript, */*; q=0.01");
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");

        $sLogin = $this->AccountFields['Login'];

        if ($match = $this->http->FindPreg("/^603207(\d{9})$/ims", false, $sLogin)) {
            $sLogin = $match;
        }
        $this->http->SetInputValue("cn", $sLogin);
        $this->http->SetInputValue("pwd", $this->AccountFields['Pass']);
        $this->http->SetInputValue("remember", "true");
//        $password = $this->stringToHex($this->AccountFields['Pass']);
//        $this->http->Log($password);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function stringToHex($s)
    {
        $this->http->Log("Encrypting password");
        $r = "";
        $hexes = ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9", "a", "b", "c", "d", "e", "f"];

        for ($i = 0; $i < strlen($s); $i++) {
            $r .= $hexes[ord($s[$i]) >> 4] . $hexes[ord($s[$i]) & 0xf];
        }

        return $r;
    }

    public function checkErrors()
    {
//        ## Failure of server APACHE bridge
//        if ($this->http->FindSingleNode("//h2[contains(text(), 'Failure of server APACHE bridge')]", null, true, "/([^:]+)/ims")
//            ## Error 500--Internal Server Error
//            || $this->http->FindPreg("/Error 500--Internal Server Error/ims")
//            ## Error 404--Not Found
//            || $this->http->FindPreg("/Error 404--Not Found/ims"))
//            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
//        ## Server encountered technical problems
//        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'Server encountered technical problems')]"))
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
//
//        if (($message = $this->http->FindSingleNode("//p[@align='center']/b/text()")) == 'ERROR')
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

        /*
         * Please note - at the moment you will not be able to log in to your PC Optimum account from the Shoppers Drug Mart website.
         * This is temporary and we are working to resolve this issue.
         *
         * In the meantime, please visit pcoptimum.ca to log in to your account.
         *
         * We appreciate your patience.
         */
        if ($message = $this->http->FindSingleNode("//div[@class = 'md-bll__maintenance']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * SHOPPERS OPTIMUM WEBSITE AND APPLICATION UNAVAILABLE.
         * The Shoppers Optimum program will be unavailable starting February 1st at 12:01AM EST, as we transition our
         * stores to the PC Optimum program, which will go live at 5AM EST February 1st.
         * During this time, you will not be able to earn or spend points, or access your account.
         * We appreciate your patience.
         */
        if ($this->http->FindNodes("//text()[contains(., 'program will be unavailable starting February 1')]")
        && $this->http->FindNodes("//text()[contains(., 'at 12:01AM EST, as we transition our stores to the')]")) {
            throw new CheckException('The Shoppers Optimum program will be unavailable starting February 1st at 12:01AM EST, as we transition our stores to the PC Optimum program, which will go live at 5AM EST February 1st', ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $response = $this->http->JsonLog();

        if (isset($response->Code)) {
            switch ($response->Code) {
                case '901':
                    return true;

                    break;

                case '2':
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);

                    break;

                case '902':
                    throw new CheckException("The Optimum Card number or password you entered is incorrect. Please try again.", ACCOUNT_INVALID_PASSWORD);

                    break;

                case '906':
                    $this->recognizer->reportIncorrectlySolvedCAPTCHA();

                    throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);

                    break;

                case '907':
                    throw new CheckException("Account not Active", ACCOUNT_INVALID_PASSWORD);

                    break;

                case '909':
                    throw new CheckException("Your card number or password is incorrect. You have {$response->Msg} attempt(s) remaining.", ACCOUNT_INVALID_PASSWORD);

                    break;

                default:
                    $this->logger->error("Unknown Code: {$response->Code}");

                    break;
            }// switch ($response->Code)
        }// if (isset($response->Code))

//        if ($message = $this->http->FindNodes("//p[@class = 'errorheader']/following-sibling::ul[1]//li"))
//            throw new CheckException(implode(" ", $message), ACCOUNT_INVALID_PASSWORD);
//
//        if ($message = $this->http->FindPreg("/(Please create your new secure password for accessing Optimum content online\.)/ims"))
//            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
//        ## Sorry,your account has been locked. Please try after 20 minutes
//        if ($message = $this->http->FindPreg("/(Sorry,your account has been locked\.\s*Please try after 20 minutes\.)/ims"))
//            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
//        ## Sorry, system has encountered technical problems, please try after some time.
//        if ($message = $this->http->FindPreg("/(Sorry\s*\,\s*system has encountered technical problems\,\s*please try after some time\.)/ims"))
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www1.shoppersdrugmart.ca/en/optimum/userheaderinfo");

        // provider bug fix
        if ($this->http->FindPreg("/\"TotalPoints\":null,/")) {
            sleep(7);
            $this->logger->notice("Provider bug. try to load balance one more time");
            $this->http->GetURL("https://www1.shoppersdrugmart.ca/en/optimum/userheaderinfo");
        }// if ($this->http->FindPreg("/\"TotalPoints\":null,/"))

        // Balance - Points Balance
        $this->SetBalance($this->http->FindPreg("/\"TotalPoints\":\"([^\"]+)/"));

        $this->http->setDefaultHeader("Accept", "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8");
        $this->http->GetURL("https://www1.shoppersdrugmart.ca/en/optimum-new/my-optimum");
        // Card Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//h3[contains(text(), 'Optimum Card Number')]/following-sibling::p[1]"));
        // Points Value
        $this->SetProperty("PointsValue", $this->http->FindSingleNode("//h3[contains(text(), 'Points Value:')]/following-sibling::p[1]"));
        // Coupons currently loaded on card
        $this->SetProperty("Coupons", $this->http->FindSingleNode("//h3[contains(text(), 'Coupons currently loaded on card')]/following-sibling::p[1]"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            /*
             * SHOPPERS OPTIMUM WEBSITE AND APPLICATION UNAVAILABLE
             *
             * The Shoppers Optimum program will be unavailable starting February 1st at 12:01AM EST
             * as we transition our stores to the PC Optimum program, which will go live at 5AM EST February 1st.
             * During this time you will not be able to earn or spend points, or access your account.
             * We appreciate your patience.
             *
             * Notice: Your Shoppers Optimum coupons are only valid until Wednesday, Jan 31, 2018.
             */
            if ($message = $this->http->FindSingleNode("//a[@class = 'md-otn-link' and contains(text(), 'The Shoppers Optimum program will be unavailable starting February 1')]")) {
                throw new CheckException(preg_replace("/\s*Learn\s*more\s*$/", "", $message), ACCOUNT_PROVIDER_ERROR);
            }
        }

        if (isset($this->Properties["Coupons"]) && $this->Properties["Coupons"] > 0) {
            $this->sendNotification("shoppersdrugmart. Coupons > 0");
        }

        $this->http->GetURL("https://www1.shoppersdrugmart.ca/en/optimum-new/update-profile");
        // Name
        $name = CleanXMLValue($this->http->FindSingleNode("//input[@name = 'FirstName']/@value") . ' ' . $this->http->FindSingleNode("//input[@name = 'LastName']/@value"));
        $this->SetProperty("Name", beautifulName($name));

        // Expiration Date  // refs #3912
        $this->http->GetURL("https://www1.shoppersdrugmart.ca/en/optimum-new/transaction-history");
        $nodes = $this->http->XPath->query("//th[contains(text(), 'Transaction Date')]/ancestor::table[1]//tr[td]");
        $this->logger->debug("Total {$nodes->length} nodes were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $date = $this->http->FindSingleNode("td[1]", $node);
            $totalPointsAwarded = $this->http->FindSingleNode("td[4]", $node);

            if ($totalPointsAwarded > 0) {
                // Last Activity
                $this->SetProperty("LastActivity", $date);
                $this->SetExpirationDate(strtotime("+12 month", strtotime($date)));

                break;
            }// if ($totalPointsAwarded > 0)
        }// for ($i = 0; $i < $nodes->length; $i++)
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        // https://www1.shoppersdrugmart.ca/static/core/dist/v-636433569121366089/core.min.js
        $key = '6Lcq9SMUAAAAAHkzfuKtutMTFrg1C79_Tm09YfZi';
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
}
