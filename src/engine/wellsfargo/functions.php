<?php

class TAccountCheckerWellsfargo extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->LogHeaders = true;
        // on the prod -> Network error 35 - Unknown SSL protocol error in connection to online.wellsfargo.com:443
        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
        }
    }

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerWellsfargoSelenium.php";

        return new TAccountCheckerWellsfargoSelenium();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // set cookie - JS emulation
        $this->http->setCookie("BROWSER_SUPPORT_LEVEL", "SUPPORTED", ".wellsfargo.com");
        $this->http->setCookie("CookiesAreEnabled", "yes", ".wellsfargo.com");

        //		$this->http->GetURL("https://online.wellsfargo.com/das/web/rewards");
        $this->http->GetURL("https://connect.secure.wellsfargo.com/auth/login/rewards");

        // retries
        if (empty($this->http->Response['body']) && $this->http->Response['code'] == 0) {
            throw new CheckRetryNeededException(3, 7);
        }

        //		$this->http->GetURL("https://connect.secure.wellsfargo.com/auth/login/rewards");
        if (!$this->http->ParseForm("Signon")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("j_username", $this->AccountFields['Login']);
        $this->http->SetInputValue("j_password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("jsenabled", "true");
        $this->http->SetInputValue("userPrefs", "TF1;015;;;;;;;;;;;;;;;;;;;;;;Mozilla;Netscape;5.0%20%28Macintosh%3B%20Intel%20Mac%20OS%20X%2010_10_5%29%20AppleWebKit/537.36%20%28KHTML%2C%20like%20Gecko%29%20Chrome/45.0.2454.93%20Safari/537.36;20030107;undefined;true;;true;MacIntel;undefined;Mozilla/5.0%20%28Macintosh%3B%20Intel%20Mac%20OS%20X%2010_10_5%29%20AppleWebKit/537.36%20%28KHTML%2C%20like%20Gecko%29%20Chrome/45.0.2454.93%20Safari/537.36;ru;windows-1251;connect.secure.wellsfargo.com;undefined;undefined;undefined;undefined;true;false;1442561357323;5;07.06.2005%2C%2021%3A33%3A44;1440;900;;18.0;;;;;21;-300;-360;18.09.2015%2C%2012%3A29%3A17;24;1440;831;0;23;;;;;;Shockwave%20Flash%7CShockwave%20Flash%2018.0%20r0;;;;;;;;;;;;;14;");

        return true;
    }

    public function checkErrors()
    {
        //# Maintenance
        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "We\'re sorry, the page you are looking for is temporarily unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Online Banking is temporarily unavailable
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Online Banking is temporarily unavailable")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're sorry, our system is temporarily unavailable
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "We\'re sorry, our system is temporarily unavailable")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // website is currently unavailable due to routine scheduled maintenance
        if ($this->http->FindPreg('/(website is currently unavailable due to (?:a temporary outage|routine scheduled maintenance|scheduled maintenance))/ims')) {
            throw new CheckException("The Wells Fargo Rewards® website is currently unavailable due to routine scheduled maintenance. We are sorry for any inconvenience this may have caused. The website will be back up shortly. Please check back soon.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function SAMLResponse()
    {
        $this->http->Log(__METHOD__);
        // SAMLResponse
        if ($this->http->ParseForm(null, 1, true, "//form[contains(@action, 'https://www.mywellsfargorewards.com/sso/post')]")) {
            $this->http->Log("Posting SAMLResponse");
            $this->http->PostForm();
        }
    }

    public function checkProviderError()
    {
        $this->http->Log(__METHOD__);
        // Need to update profile
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Action Required: Your Password Is No Longer Valid')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Action Required: Username and Password Update')]")) {
            throw new CheckException("Wells Fargo Rewards website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }
    }

    public function Login()
    {
        sleep(1);

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($pd = $this->http->FindPreg("/window\[\"bobcmn\"\].+(\#sCmnToken\#.+#eCmnToken#)/")) {
            $this->http->Log("posting _pd value");
            $this->http->PostURL('https://connect.secure.wellsfargo.com/auth/login/do', ['_pd' => $pd], ['content-type' => 'multipart/form-data']);
        }

        $this->SAMLResponse();

        if ($this->http->FindPreg("/Skip\s*to\s*Main\s*Content/")) {
            return true;
        } else {
            $this->logger->notice("Wrong credentials?");
        }
        // security questions
        if ($this->parseQuestion()) {
            return false;
        }
        // We do not recognize your username and/or password.
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'We do not recognize your username and/or password.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Online access is currently unavailable
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Online access is currently unavailable')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We have temporarily prevented online access to your account
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'We have temporarily prevented online access to your account')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->checkProviderError();

        // Old code

        /*// set cookie
        if ($myCookie = $this->http->FindPreg("/document.cookie = \"ISD_WOF_COOKIE=([^\;]+)/ims")) {
            $this->http->setCookie("ISD_WOF_COOKIE", $myCookie, "online.wellsfargo.com");
        }// if ($myCookie = $this->http->FindPreg("/document.cookie = \"ISD_WOF_COOKIE=([^\;]+)/ims"))

        if ($refresh = $this->http->FindPreg("/window\.location\.replace\(\'([^\']+)/ims")) {
            $this->http->Log("Refresh page");
            sleep(1);
            $this->http->GetURL($refresh);
            // We do not recognize your username and/or password.
            if ($this->http->FindPreg("/We do not recognize your username and\/or password\./ims"))
                throw new CheckException("We do not recognize your username and/or password. Please try again.", ACCOUNT_INVALID_PASSWORD);
            // We cannot verify your entry.  For help signing on, please go to Password Help or call us at 1-800-956-4442.
            if ($message = $this->http->FindPreg("/We cannot verify your entry/ims"))
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            // Your online access has been temporarily disabled.
            if ($message = $this->http->FindPreg("/Your online access has been temporarily disabled\./ims"))
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            /*
             * The password entered contains invalid characters.
             * You may enter numbers 0 - 9 and/or letters A - Z.
             * At least one letter and one number must be entered.
             * You may also use special characters (such as @, %, &, #).
             * Please re-enter your password. Your password should be different than your username.
             * /
            if ($message = $this->http->FindPreg("/The password entered contains invalid characters\./ims"))
                throw new CheckException("The password entered contains invalid characters. You may enter numbers 0 - 9 and/or letters A - Z. At least one letter and one number must be entered. You may also use special characters (such as @, %, &, #). Please re-enter your password. Your password should be different than your username.", ACCOUNT_INVALID_PASSWORD);
            ## You do not have any accounts available for online access.  Please call 1-800-956-4442.  Online Brokerage customers please call 1-877-879-2495.
            if ($message = $this->http->FindPreg("/You do not have any accounts available for online access\./ims"))
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            // Please try again later. Wells Fargo Online® is temporarily unavailable.
            if ($message = $this->http->FindPreg("/strong>Please try again later\.\&\#160;\&\#160;Wells Fargo Online<sup>\&\#174;<\/sup> is temporarily unavailable\./ims"))
                throw new CheckException("Please try again later. Wells Fargo Online® is temporarily unavailable.", ACCOUNT_PROVIDER_ERROR);

            // skip profile update
            if ($this->http->FindPreg("/Please update your email address in order to continue receiving statements online\./ims") && $this->http->ParseForm("emailVerificationSplashCommand")) {
                $this->http->Log("skip profile update");
                $this->http->SetInputValue("emailRemindMeLater", "Remind Me Later");
                $this->http->PostForm();
            }
            // skip choice Online delivery
            if ($this->http->FindPreg("/Please set the delivery preferences for your account\(s\)/ims") && $this->http->ParseForm("deliveryPreference")) {
                $this->http->Log("skip choice Online delivery");
                $this->http->SetInputValue("olsRemindMeLater", "Remind Me Later");
                $this->http->PostForm();
            }

            // form auto-submit
            if ($this->http->ParseForm(null, 1, true, '//form[contains(@action, "com/sso/post")]'))
                if (!$this->http->PostForm()) {
                    // maintenance
                    $this->http->GetURL("https://www.mywellsfargorewards.com/");
                }
                $this->checkErrors();
        }
        // You have entered an invalid username
        if ($this->http->FindPreg("/You have entered an invalid username\./ims"))
            throw new CheckException("You have entered an invalid username. Please re-enter your username.", ACCOUNT_INVALID_PASSWORD);
        // Your password must be at least 5 characters in length.
        if ($this->http->FindPreg("/Your password must be at least 5 characters in length\./ims"))
            throw new CheckException("Your password must be at least 5 characters in length. Please re-enter your password. ", ACCOUNT_INVALID_PASSWORD);
        // Please try again later.  Wells Fargo Online® is temporarily unavailable.
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Please try again later')]"))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        ## We're sorry, the service you are looking for is temporarily unavailable. Please try again later.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'the service you are looking for is temporarily unavailable')]"))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);*/

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->http->Log("parseQuestion");
        $question = $this->http->FindSingleNode("//div[contains(text(), 'Security Question')]/following-sibling::div[1]");

        if (!isset($question)) {
            $this->http->Log("New question type");
            $question = $this->http->FindSingleNode("//p[contains(text(), 'For your security, please verify your identity by answering the following questions')]/following-sibling::span/strong");

            if (isset($question) && !$this->http->ParseForm("identity_form")) {
                return false;
            }

            unset($this->State["answers"]);
            $answers = $this->http->XPath->query("//input[@name = 'selectedAnswer']");
            $this->http->Log("Total {$answers->length} answers were found");

            foreach ($answers as $answer) {
                $this->State["answers"][] = $this->http->FindSingleNode('@value', $answer);
            }
        }

        if (!isset($question) || (!$this->http->ParseForm("command") && !$this->http->ParseForm("identity_form"))
            && $this->http->ParseForm("tfaForm")) {
            if ($this->http->FindSingleNode("//h1[contains(text(), 'Please confirm your identity')]")) {
                $this->http->Log("Advanced Access Code");
                $question = $this->http->FindPreg("/phoneList: \{CELL_\d+=Mobile (XXX-XXX-\d+)/");
                $telephone = $this->http->FindPreg("/phoneList: \{(CELL_\d+)=Mobile XXX-XXX-\d+/");

                if (!isset($telephone) && !isset($question)) {
                    $question = $this->http->FindPreg("/phoneList: \{HOME_\d+=Home (XXX-XXX-\d+)/");
                    $telephone = $this->http->FindPreg("/phoneList: \{(HOME_\d+)=Home XXX-XXX-\d+/");
                }

                if (!isset($telephone) && !isset($question)) {
                    $question = $this->http->FindPreg("/phoneList: \{BUS_\d+=Work (XXX-XXX-\d+)/");
                    $telephone = $this->http->FindPreg("/phoneList: \{(BUS_\d+)=Work XXX-XXX-\d+/");
                }

                if (!isset($telephone) || !isset($question) || !$this->http->ParseForm("tfaForm")) {
                    return false;
                }

                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }

                $question = "Please enter Advanced Access Code which was sent to the following phone number: {$question}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";

                /*
                cancelRequested:
                deliveryMode:	sms
                indict_phoneNumber-type:
                permission: sendotp
                rsacode
                rsadevicecode
                sendcode: request code
                submitDeviceCode
                telephone: CELL_1
                */
                $this->http->SetInputValue("telephone", $telephone);
                $this->http->SetInputValue("deliveryMode", 'sms');
//                $this->http->SetInputValue("sendcode", 'request code');
                if (!$this->http->PostForm()) {
                    return false;
                }
                // Your Advanced Access code could not be delivered
                if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Your Advanced Access code could not be delivered')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if (!$this->http->ParseForm("tfaForm")) {
                    return false;
                }
            }// if ($this->http->FindSingleNode("//h1[contains(text(), 'Please confirm your identity')]"))
            else {
                return false;
            }
        }// if (!isset($question) || !$this->http->ParseForm("command"))
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->Log("ProcessStep");

        if (strstr($this->Question, 'Please enter Advanced Access Code which was sent to the following phone number')) {
            $this->http->Log("Advanced Access Code");
//            $this->sendNotification("wellsfargo - Advanced Access Code was entered");
            /*
             *
             * Response Headers
             *
             * Cache-Control	no-cache, no-store
             * Content-Language	english-US
             * Content-Type	text/html;charset=ISO-8859-1
             * Date	Fri, 06 Nov 2015 07:09:45 GMT
             * Expires	Thu, 01 Jan 1970 00:00:00 GMT
             * Pragma	no-cache
             * Server	KONICHIWA/1.1
             * Strict-Transport-Security	max-age=31536000 ; includeSubDomains
             * Transfer-Encoding	chunked
             * X-Frame-Options	SAMEORIGIN
             * X-XSS-Protection	1; mode=block
             * x-content-type-options	nosniff
             *
             * Requested Headers
             *
             * Accept	text/html,application/xhtml+xml,application/xml;q=0.9,* /*;q=0.8
             * Accept-Encoding gzip, deflate
             * Accept-Language en-US,en;q=0.5
             * Connection keep-alive
             * Cookie tabSelection=0; wfacookie=4520151027041301626629692; SIMSCookie=7081575_0b84b70be8704b26777a63c934b67166; ISD_TF_COOKIE=vWAk3bVTtOB98CdjJH9V5O5YglT+rh4jFvQ3lgom9t1LPrhbEiudj0UWlFOO9qsu31br3SXIQeozjwAAAAE=; CookiesAreEnabled=yes; BROWSER_SUPPORT_LEVEL=SUPPORTED; KCOOKIE=0b9d3ea3-7cb4-41a8-8b40-f887fc7e4ad9; OAM_APP_COOKIE=3ED4C169B135AE518D1CCF6B37110136; OAM_APP_INIT=loginapp
             * DNT 1
             * Host oam.wellsfargo.com
             * Referer https://oam.wellsfargo.com/oam/access/crosspTwoFAIndictSendCode?OAM_TKN=dbf2ba6887c72a11305f1e9418499df9620fb5e2fc6a9300fabfbe3d9f626e87
             * User-Agent Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:42.0) Gecko/20100101 Firefox/42.0
             * Content-Length 32
             * Content-Type application/x-www-form-urlencoded
             *
             * POST -> https://oam.wellsfargo.com/oam/access/crosspTwoFAIndictSubmitCode?OAM_TKN=62240b98a6a37e65aac37ccb87434728586f3268923001399980de1f1788a7d6
             *
             * cancelRequested
             * passcode:	234243
             *
             * cancelRequested=&passcode=234243
             */
            $this->http->SetInputValue("passcode", $this->Answers[$this->Question]);
            // do not keep Advanced Access Code
            unset($this->Answers[$this->Question]);
        } elseif (!empty($this->State["answers"])) {
            $this->http->Log("New question type");
            $selectedAnswer = '';

            foreach ($this->State["answers"] as $answer) {
                if (stristr($answer, $this->Answers[$this->Question])) {
                    $this->http->Log("Answer: {$answer}");
                    $selectedAnswer = $answer;

                    break;
                }
            }
            /*
             * cancel
             * indict_phoneNumber-type
             * permission:	sendotp
             * selectedAnswer: Washtenaw, Michigan
             */
            $this->http->SetInputValue("selectedAnswer", $selectedAnswer);
        } else {
            $this->http->Log("Just question");
            $this->http->SetInputValue("answer", $this->Answers[$this->Question]);
            $this->http->SetInputValue("submit", 'Submit');
        }

        if (!$this->http->PostForm()) {
            return false;
        }
        $this->SAMLResponse();
        // If error ask again
        if ($error = $this->http->FindSingleNode("//strong[contains(text(), 'You have submitted an invalid Advanced Access code.')]")) {
            $this->AskQuestion($this->Question, $error);

            return false;
        }
        $this->checkProviderError();

        return true;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['CurrencyType']) && strstr($properties['CurrencyType'], 'CASH')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        } else {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
        }
    }

    public function Parse()
    {
        $this->http->PostURL("https://www.mywellsfargorewards.com/Home/GetRans?n=" . $this->random(), []);
        $response = $this->http->JsonLog();

//        // Invalid credentials
//        if ($this->http->FindPreg("/\"Success\":false/ims")
//            && $this->http->FindPreg("/Requested information cannot be found/ims"))
//            throw new CheckException("We do not recognize your username and/or password.", ACCOUNT_INVALID_PASSWORD);
        // We’re sorry. Our system is temporarily unavailable
        if ($this->http->FindPreg("/\"Success\":false/ims")
            && $this->http->FindPreg("/Exception when calling a Web API: System.AggregateException: One or more errors occurred\./ims")) {
            throw new CheckException("Our system is currently unavailable, and we apologize for any inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }
        // retries
        if ($this->http->FindPreg("/\"Success\":false/ims")
            && ($this->http->FindPreg("/User is not logged in, please log-in first\./ims")
                || $this->http->FindPreg("/Requested information cannot be found\. Related Rewards accounts cannot be found for the given CustomerKey/ims"))
            && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            throw new CheckRetryNeededException(3, 10);
        }

        if (isset($response->Rans[0]->AccountNumber)) {
            foreach ($response->Rans as $ran) {
                // Rewards ID
                if (isset($ran->AccountNumber)) {
                    $account["RewardsID"] = $ran->AccountNumber;
                } else {
                    $this->http->Log(">>> RewardsID");
                }
                // CurrencyType
                if (isset($ran->CurrencyType)) {
                    $account["CurrencyType"] = $ran->CurrencyType;
                } else {
                    $this->http->Log(">>> CurrencyType");
                }
                // Current Balance
                if (isset($ran->CurrentBalance)) {
                    $account["Balance"] = $ran->CurrentBalance;

                    if (isset($account["CurrencyType"]) && strtolower($account["CurrencyType"]) == 'cash') {
                        $account["Balance"] = $account["Balance"] / 100;
                    }
                } else {
                    $this->http->Log(">>> CurrentBalance");
                }
                // DisplayName, Code
                if (isset($ran->EarningMechanisms[0]->AccountNumber, $ran->EarningMechanisms[0]->Description)) {
                    $account["DisplayName"] = $ran->EarningMechanisms[0]->Description
                        . " " . $ran->EarningMechanisms[0]->AccountNumber;

                    if (preg_match("/(\d{4})/ims", $account["DisplayName"], $matches)) {
                        $account["Code"] = "wellsfargo" . $matches[1];
                    }
                    // Account Number
                    $account["AccountNumber"] = $ran->EarningMechanisms[0]->AccountNumber;
                } // AccountID: 1594469
                elseif (count($response->Rans) == 1 && isset($ran->AccountNumber)) {
                    $account["DisplayName"] = "Wells Fargo Rewards";
                    $account["Code"] = "wellsfargoMain" . $ran->AccountNumber;
                }
                // Base Rewards
                if (isset($ran->BasePoints)) {
                    $account["BaseRewards"] = $ran->BasePoints;
                } else {
                    $this->http->Log(">>> BasePoints");
                }
                // Bonus Rewards
                if (isset($ran->BonusPoints)) {
                    $account["BonusRewards"] = $ran->BonusPoints;
                } else {
                    $this->http->Log(">>> BonusPoints");
                }
                // Earn More Mall Rewards
                if (isset($ran->EmmPoints)) {
                    $account["EarnMoreMallRewards"] = $ran->EmmPoints;
                } else {
                    $this->http->Log(">>> EmmPoints");
                }
                // Rewards adjusted to date
                if (isset($ran->AdjustmentsToDate)) {
                    $account["RewardsAdjustedToDate"] = $ran->AdjustmentsToDate;
                } else {
                    $this->http->Log(">>> AdjustmentsToDate");
                }
                // Rewards Pending
                if (isset($ran->PendingPoints)) {
                    $account["RewardsPending"] = $ran->PendingPoints;
                } else {
                    $this->http->Log(">>> PendingPoints");
                }

                if (isset($account["DisplayName"], $account["Code"], $account["Balance"])) {
                    $subAccounts[] = $account;
                }
            }// foreach ($response->Rans as $ran)

            // Set SubAccounts
            if (isset($subAccounts) && count($subAccounts) > 0) {
                $this->SetProperty("SubAccounts", $subAccounts);
                $this->SetBalanceNA();
            }// if (isset($subAccounts) && count($subAccounts) > 0)

            // Get full json
            $this->http->PostURL("https://www.mywellsfargorewards.com/Home/SetRan?n=" . $this->random(), ["accountNumber" => $response->Rans[0]->AccountNumber]);
            $this->http->JsonLog();
            $this->http->PostURL("https://www.mywellsfargorewards.com/Home/GetProfile?n=" . $this->random(), []);
            $response = $this->http->JsonLog(null, false);

            if (isset($response->Profile)) {
                $this->http->Log("<pre>" . var_export($response->Profile, true) . "</pre>", false);
            }
            // Name
            if (isset($response->Profile->FirstName, $response->Profile->LastName)) {
                $this->SetProperty("Name", beautifulName($response->Profile->FirstName . " " . $response->Profile->LastName));
            } else {
                $this->http->Log(">>> Name");
            }
        } // if (isset($response->Rans[0]->AccountNumber))
        elseif ($this->http->FindPreg("/\"Success\":true/ims") && empty($response->Rans)
                && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            throw new CheckException("Our system is currently unavailable, and we apologize for any inconvenience.", ACCOUNT_PROVIDER_ERROR);
        } else { // Maintenance
            $this->checkErrors();
        }
    }
}
