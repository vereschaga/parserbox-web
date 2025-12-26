<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerMacy extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $responseDataRewards = null;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'macyStarMoneyRewards')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

//    public static function GetAccountChecker($accountInfo) {
//        require_once __DIR__."/TAccountCheckerMacySelenium.php";
//        return new TAccountCheckerMacySelenium();
//    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');

//        if ($this->attempt > 0) {
//        $this->http->SetProxy($this->proxyReCaptchaIt7());
//        }

        // crocked server workaround
        $this->http->SetProxy($this->proxyReCaptchaIt7());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"       => "application/json, text/javascript, */*; q=0.01",
            "Content-Type" => "application/json",
        ];
        $data = [
            "SignIn" => ["email" => $this->AccountFields['Login']],
        ];
        $this->http->PostURL("https://www.macys.com/account-xapi/api/account/signin?&_deviceType=PC", json_encode($data), $headers);

        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 0);

        if (isset($response->user->firstName, $response->user->lastName, $response->user->tokenCredentials->userGUID)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter your email address in this format: jane@company.com
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter your email address in this format: jane@company.com", ACCOUNT_INVALID_PASSWORD);
        }

//        $this->http->removeCookies();
//        $this->http->GetURL("https://www.macys.com");
        $this->http->GetURL("https://www.macys.com/account/signin?");

        return $this->selenium();

        if ($sensorDataPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu', '(/.+?)'\]\);#")) {
            $this->http->NormalizeURL($sensorDataPostUrl);
        }

        $this->http->GetURL("https://www.macys.com/account-xapi/api/account/signin?", [
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
        ]);
        $response = $this->http->JsonLog();

        if (!isset($response->user->hosts->authwebKey)) {
            return $this->checkErrors();
        }

        foreach ($response->user->cookies as $cookie) {
            $this->http->setCookie($cookie->name, $cookie->value ?? null, $cookie->domain, $cookie->path);
        }
        $authWebKey = $response->user->hosts->authwebKey;

        /*
        $this->http->RetryCount = 0;
        if ($this->attempt > 2) {
            $sensorData = $this->getSensorData();
        }
        else {
            $sensorData = null;
            $this->sendSensorData($sensorData, $sensorDataPostUrl);
            $sensorData = null;
            $this->sendSensorData($sensorData, $sensorDataPostUrl);
        }
//        else {
//            $sensorData = $this->getSensorDataFromSelenium();
//        }
//        if (!empty($sensorData)) {
//            $this->sendSensorData($sensorData, $sensorDataPostUrl);
//        }
        */

        $this->http->FormURL = "https://auth.macys.com/v3/oauth2/token";
        $this->http->Form = [];
        $this->http->SetInputValue("grant_type", "password");
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("registrySignIn", "false");
        $this->http->SetInputValue("request_url", "https://www.macys.com/account/signin");
        $this->http->SetInputValue("deviceFingerPrint", "");
        $this->http->SetInputValue("authWebKey", $authWebKey);

        $headers = [
            "Accept-Encoding" => "gzip, deflate, br",
            "Accept"          => "application/json",
            "Authorization"   => "Basic " . urldecode($authWebKey),
            "Content-Type"    => "application/x-www-form-urlencoded",
            "Origin"          => "https://www.macys.com",
        ];
        $formUrl = $this->http->FormURL;
        $form = $this->http->Form;

        $this->http->RetryCount = 0;

        if (!$this->http->PostForm($headers) && !in_array($this->http->Response['code'], [0, 401, 403, 500, 302])) {
            return $this->checkErrors();
        }

        // debug
        if (in_array($this->http->Response['code'], [403])) {
            sleep(5);
            $this->sendNotification("second attempt // RR");

            if ($this->attempt > 2) {
                $sensorData = $this->getSensorData();
            } else {
                $sensorData = $this->getSensorDataFromSelenium();
            }

            if (!empty($sensorData)) {
                $this->sendSensorData($sensorData, $sensorDataPostUrl);
            }

            $this->http->FormURL = $formUrl;
            $this->http->Form = $form;
            $this->http->RetryCount = 0;

            if (!$this->http->PostForm($headers) && !in_array($this->http->Response['code'], [0, 401, 403, 500, 302])) {
                return $this->checkErrors();
            }
        }

        if (in_array($this->http->Response['code'], [403, 302])) {
            throw new CheckRetryNeededException(2, 1);
        }

        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[URL]: " . $this->http->currentUrl());
        $this->logger->debug("[CODE]: " . $this->http->Response['code']);

        if ($this->http->FindPreg('/closed for scheduled site improvements/ims')) {
            throw new CheckException('Sorry, shoppers! macys.com is temporarily closed for scheduled site improvements as we work to bring you a better shopping experience.', ACCOUNT_PROVIDER_ERROR);
        }
        //# Temporary shopping jam!
        if ($message = $this->http->FindPreg("/(We're currently experiencing heavier traffic than normal\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
//        if ($message =  $this->http->FindPreg("/(<H1>Access Denied<\/H1>)/ims"))
//            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // We value your security and privacy. To ensure your profile is secure, it's time to reset your password.
        if ($this->http->Response['body'] == '{"error":"access_denied","error_description":"user_tagged"}') {
            throw new CheckException("We value your security and privacy. To ensure your profile is secure, it's time to reset your password.", ACCOUNT_INVALID_PASSWORD);
        }
        // User is Not Found
        if ($this->http->Response['body'] == '{"error":"access_denied","error_description":"user_not_found"}') {
            throw new CheckException("User is Not Found", ACCOUNT_INVALID_PASSWORD);
        }
        /*
         * Please try again We weren't able to find the email address and password combination you entered.
         * Remember: Your password is case sensitive; please make sure CAPS lock is turned off.
         * Forgot your password? Reset It Now.
         */
        if ($this->http->Response['body'] == '{"error":"access_denied","error_description":"invalid_credentials"}'
            || $this->http->Response['body'] == '{"error":"access_denied","error_description":"too_many_failed_attempts"}') {
            throw new CheckException("Please try again We weren't able to find the email address and password combination you entered. Remember: Your password is case sensitive; please make sure CAPS lock is turned off.", ACCOUNT_INVALID_PASSWORD);
        }
        /*
         * In order to protect the privacy of our registered shoppers, Sign In is made unavailable after several failed attempts.
         * Please contact Customer Service at 1-800-BUY-MACY (1-800-289-6229).
         */
        if ($this->http->Response['body'] == '{"error":"access_denied","error_description":"user_locked"}') {
            throw new CheckException("In order to protect the privacy of our registered shoppers, Sign In is made unavailable after several failed attempts. Please contact Customer Service at 1-800-BUY-MACY (1-800-289-6229).", ACCOUNT_LOCKOUT);
        }
        // A technical error has occurred. For assistance, please call 1-800-289-6229.
        if ($this->http->Response['body'] == '{"error":"server_error","error_description":"Internal Server Error"}') {
            throw new CheckException("A technical error has occurred. For assistance, please call 1-800-289-6229.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['body'] == '{"error":"hard_lock_user","error_description":"User account is hard locked"}') {
            throw new CheckException("Sorry, it looks like there's a problem on our end. For assistance, please call 1-866-282-8977.", ACCOUNT_LOCKOUT);
        }

        if (isset($response->user->tokenCredentials->userGUID)) {
            return true;
        }

        if (!isset($response->access_token)) {
            return $this->checkErrors();
        }
        $this->http->setCookie("access_token", $response->access_token, ".macys.com");
        $headers = [
            "Accept"       => "application/json, text/javascript, */*; q=0.01",
            "Content-Type" => "application/json",
        ];
        $data = [
            "SignIn" => ["email" => $this->AccountFields['Login']],
        ];
        sleep(rand(1, 3));
        $this->http->PostURL("https://www.macys.com/account-xapi/api/account/signin?cm_sp=navigation-_-top_nav-_-account", json_encode($data), $headers);

        if (in_array($this->http->Response['code'], [403])) {
            $this->DebugInfo = '403 after final post';
        }

        return true;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog();

        if (!isset($response->user->tokenCredentials->userGUID)) {
            $this->logger->error("userGUID not found");

            return;
        }

        foreach ($response->user->cookies as $cookie) {
            $this->http->setCookie($cookie->name, $cookie->value, $cookie->domain, $cookie->path, null, $cookie->secure);
        }

        // Name
        $this->SetProperty("Name", beautifulName(($response->user->firstName ?? null) . " " . ($response->user->lastName ?? null)));

        $this->logger->info("Star Rewards", ['Header' => 3]);
        $headers = [
            'Accept'            => '*/*',
            'x-macys-signedin'  => 1,
            'x-macys-uid'       => $response->user->tokenCredentials->userID,
            'x-macys-userguid'  => $response->user->tokenCredentials->userGUID,
            'x-requested-with'  => 'XMLHttpRequest',
        ];
        $this->http->setCookie('secure_user_token', urlencode($this->http->getCookieByName('secure_user_token', '.macys.com')), '.macys.com');

        $params = http_build_query(['_' => date("UB")]);
        /*
        $this->http->GetURL('https://www.macys.com/xapi/loyalty/v1/starrewardssummary?' . $params, $headers);
        */
//        $this->http->GetURL('https://www.macys.com/xapi/loyalty/v1/starrewardssummary?_origin=HnF', $headers);

        if (in_array($this->http->Response['code'], [302])) {
            throw new CheckRetryNeededException(2, 1);
        }

        $response = $this->http->JsonLog($this->responseDataRewards);

        if (isset($response->rewardsInfo, $response->rewardsInfo->currentPoints, $response->tierInfo->tierName)) {
            // 0 current points
            $this->SetBalance($response->rewardsInfo->currentPoints);
            // Status
            $this->SetProperty('Status', $response->tierInfo->tierName);
            /*
            // 0 pending points
            $this->SetProperty('PendingPoints', intval($response->rewardsInfo->pendingPoints));
            // 1000 points until your next reward!
            $this->SetProperty('PointsUntilNextReward', intval($response->rewardsInfo->pointsToNextAward));
            */
            // YOU'VE SPENT:
            $this->SetProperty('MoneySpent', '$' . $response->tierInfo->yearToDateSpend);

            if (isset($response->tierInfo->spendToKeepCurrent) && strtolower($response->tierInfo->tierName) == 'gold') {
                // Money Retain Status
                $this->SetProperty('MoneyRetainStatus', '$' . $response->tierInfo->spendToKeepCurrent);
            }

            if (isset($response->tierInfo->spendToNextUpgrade) && strtolower($response->tierInfo->tierName) == 'silver') {
                // Spend to the next tier
                $this->SetProperty('SpendToTheNextTier', '$' . $response->tierInfo->spendToNextUpgrade);
            }

            if (isset($response->tierInfo->tierExpiryDate)) {
                // Status expiration date
                $this->SetProperty('StatusExpiration', strtotime($response->tierInfo->tierExpiryDate));
            }

            /*
            // Status exp Date - Enjoy Platinum benefits through 12/31/2018!    // refs #16188
            if (isset($response->tierInfo->tierTrackerInfo->currentTierDescription)) {
                $currentTierDescription = $response->tierInfo->tierTrackerInfo->currentTierDescription;

                if ($exp = $this->http->FindPreg('#through (\d+/\d+/\d{4})[!.]#i', false, $currentTierDescription)) {
                    $this->logger->debug("Status exp: {$exp}");
                    // https://redmine.awardwallet.com/issues/16188#note-43
                    if (in_array($response->tierInfo->tierName, ['GOLD', 'PLATINUM']) && $exp == '12/31/2020') {
                        $this->logger->notice("extend status exp date for Platinum and Gold Status in response to COVID-19");
                        $exp = "12/31/2021";
                    }
                    $this->SetProperty('StatusExpiration', $exp);
                }
            }

            // MoneyRetainStatus - To maintain your Platinum benefits through 12/31/2019, spend an additional $1066.35 at Macy\'s on your Macy\'s Credit Card by 12/31/2018.
            if (isset($response->tierInfo->tierTrackerInfo->maintainCurrentTierDescription)) {
                $maintainCurrentTierDescription
                    = $response->tierInfo->tierTrackerInfo->maintainCurrentTierDescription;

                if ($value
                    = $this->http->FindPreg('# spend an additional (\$[\d.,]+) at #', false,
                    $maintainCurrentTierDescription)
                ) {
                    $this->SetProperty('MoneyRetainStatus', $value);
                }
            }
            */

            $this->logger->info('Star Money Rewards', ['Header' => 3]);
//            $this->http->GetURL("https://www.macys.com/loyalty/starrewards?cm_sp=macys_account-_-starrewards-_-star_rewards&lid=star_rewards-star_rewards");
            $this->http->GetURL("https://www.macys.com/account/wallet?ocwallet=true");
            $ocwPageJson = $this->http->JsonLog($this->http->FindPreg('/ocwPageJson\',(.+\]\})\s*\);/'));
            $starRewardCardsList = $ocwPageJson->starRewardsInfo->starRewardCardsList ?? [];
            $starMoneyRewards = $ocwPageJson->starRewardsInfo->totalStarRewardCardsValue ?? null;

            if (isset($starMoneyRewards)) {
                $this->SetProperty("CombineSubAccounts", false);
                $this->logger->debug("Star Money Rewards Available: {$starMoneyRewards}");

                foreach ($starRewardCardsList as $starRewardCard) {
                    $expirationDate = $starRewardCard->expirationDate;

                    if (!isset($exp) || $exp > strtotime($expirationDate)) {
                        $exp = strtotime($expirationDate);
                        $expBalance = $starRewardCard->formattedCurrentValue;
                        $this->logger->debug("Set exp date: {$expirationDate} / {$expBalance}");
                    }
                }// foreach ($starRewardCardsList as $starRewardCard)

                if (isset($exp, $expBalance)) {
                    $this->AddSubAccount([
                        'Code'            => 'macyStarMoneyRewards',
                        'DisplayName'     => 'Star Money Rewards',
                        'Balance'         => $starMoneyRewards,
                        'ExpirationDate'  => $exp,
                        'ExpiringBalance' => $expBalance,
                    ], true);
                }// if (isset($exp, $expBalance))
            }// if (isset($balance))
        } elseif ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (isset($response->cardDefaultToWallet) && !isset($response->maskedCardNumber) && $response->cardDefaultToWallet === false) {
                $this->SetBalanceNA();
            } elseif (
                // AccountID: 2590821, 5191120
                isset($response->ccpaNoticeAndConsentEnabled, $response->emailLookupMembershipExists, $response->walletCardsIndicator)
                && $response->ccpaNoticeAndConsentEnabled == true
                && $response->emailLookupMembershipExists == false
                && in_array($response->walletCardsIndicator, [
                    "MultipleCards",
                    "OneCard", // AccountID: 4957711
                ])
                && !isset($response->cardDefaultToWallet)
                && !isset($response->maskedCardNumber)
            ) {
                $this->SetBalanceNA();
            } elseif (
                // AccountID: 5265619
                (
                    isset($response->walletCardsIndicator)
                    && !isset($response->maskedCardNumber)
                    && !isset($response->errors->error[0]->message)
                    && in_array($response->walletCardsIndicator, [
                        "NoCards",
                        "MultipleCards", // AccountID: 2590821
                        "OneCard", // AccountID: 3431391
                    ])
                )
                // AccountID: 2590821
                || (
                    isset($response->errors->error[0]->message)
                    && strstr($response->errors->error[0]->message, "We're experiencing a technical glitch. Please try again later.")
                )
            ) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
            // We're sorry! It looks like there's an issue with Star Rewards. Please try again later.
            elseif (isset($response->errors->error[0]->message) && strstr($response->errors->error[0]->message, 'We\'re sorry! It looks like there\'s an issue with Star Rewards. Please try again later.')) {
                $this->SetWarning($response->errors->error[0]->message);
            }
            // AccountID: 4634571
            elseif (isset($response->errors->error[0]->message) && strstr($response->errors->error[0]->message, "We're sorry! We can’t find a Star Rewards membership associated with your default Macy’s Credit Card. Please go to My Wallet and make your current Macy’s Credit Card your default card or call Macy's Credit Customer Service at 1-888-860-7111.")) {
                $this->SetWarning($response->errors->error[0]->message);
            } elseif (isset($response->errors->error[0]->message) && strstr($response->errors->error[0]->message, "We're sorry, your session has timed out. Please sign in to your account again")) {
                $this->sendNotification('session has timed out // MI');
            }
        }
    }

    /*function ParseFiles($filesStartDate){
        $this->http->TimeLimit = 500;
        $this->http->GetURL("https://www.macys.com/credit/accountsummary/account?cm_sp=macys_account-_-my_account-_-credit_account_summary");
        $cards = [];
        $cardStatements = $this->http->XPath->query("//div[@class = 'cardContent']");
        $this->http->Log("Total {$cardStatements->length} cards were found");
        foreach ($cardStatements as $cardStatement) {
            $statements = $this->http->XPath->query(".//button[contains(@class, 'mViewStatement')]/@id", $cardStatement);
            $this->http->Log("Total {$statements->length} statements were found");
            $cardNumber = null;
            for ($i = $statements->length - 1; $i >=0; $i--) {
                $card = explode(':', CleanXMLValue($statements->item($i)->nodeValue));
                /** @var DOMNode $node * /
                if (isset($card[1], $card[2])) {
                    $number = str_replace('-', '*', $card[2]);

                    if ($card[1] != 'Store' || $statements->length == 1)
                        $cardNumber = $number;
                    $this->http->Log("CardNumber $cardNumber");

                    $cards[$card[2]] = [
                        "Code" => $card[1],
                        "Number" => $number,
                        "CardNumber" => $cardNumber,
                    ];
                }// if (isset($card[1], $card[2]))
                else
                    $this->http->Log("Bad node <pre>".var_export(CleanXMLValue($node->nodeValue, true))."</pre>");
            }// foreach ($statements as $node)
        }// foreach ($cardStatements as $cardStatement)
        $result = [];
        do{
            if (count($cards) > 0) {
                $card = array_pop($cards);
                $this->http->Log("loading card ".var_export($card, true));
                $this->http->GetURL("https://www.macys.com/credit/accountsummary/onlinestatements?SelectedAccountMasterTypeCode={$card["Code"]}OnlineStatement&MasterAccountNumber=".$card["CardNumber"]);
                // Error in request
                if ($message = $this->http->FindPreg("/You do not have any statements to view at this time\./"))
                    $this->http->Log(">>> ".$message);
                // Skip accepting term and conditions
                if ($this->http->FindPreg("/\"type\"\s*:\s*\"success\",\s*\"value\":\s*\"goGreen\"/ims")) {
                    $this->http->Log("Not agree term and conditions now");
                    $this->http->GetURL("https://www.macys.com/credit/accountsummary/onlinestatements?SelectedAccountMasterTypeCode={$card["Code"]}OnlineStatement&MasterAccountNumber=".$card["CardNumber"]."&updateGoGreen=false");
                }
            }
            else
                $card = null;
            $options = $this->http->XPath->query("//select/option");
            $files = [];
            foreach ($options as $option) {
                /** @var DOMNode $option * /
                $file = [
                    'title' => CleanXMLValue($option->nodeValue),
                    'id' => $option->attributes->getNamedItem('id')->nodeValue
                ];
                $this->http->Log("node: {$file['title']}, {$file['id']}");
                $files[] = $file;
            }
            foreach ($files as $file) {
                $this->http->Log("downloading {$file['title']}, {$file['id']}");
                $date = strtotime($file['id']);
                $code = null;
                if (!empty($card['Number']) && preg_match('#\d\d\d\d$#ims', $card['Number'], $matches))
                    $code = $matches[0];
                if (intval($date) >= $filesStartDate) {
                    $fileName = $this->http->DownloadFile("https://www.macys.com/credit/accountsummary/onlinestatementdisplay?SelectedAccountMasterTypeCode={$card["Code"]}OnlineStatement&MasterAccountNumber={$card["CardNumber"]}&StatementMonth={$file['id']}&DisplayPDF=DisplayPDF");

                    if (strpos($this->http->Response['body'], '%PDF') === 0) {
                        $result[] = [
                            "FileDate" => $date,
                            "Name" => $file["title"],
                            "Extension" => "pdf",
                            "AccountNumber" => $code,
                            "AccountName" => !empty($file['title']) ? $file['title'] : '',
                            "AccountType" => ($card["Code"] == 'Store')  ? "Purchases made at Macy's" : "Purchases made outside Macy's",
                            "Contents" => $fileName,
                        ];
                    }// if (strpos($this->http->Response['body'], '%PDF') === 0)
                    else
                        $this->http->Log("not a PDF");
                }// if (intval($date) >= $filesStartDate)
                else
                    $this->http->Log("skip by date");
            }// foreach ($files as $file)
        } while(count($cards) > 0);
        return $result;
    }*/

    private function getSensorData()
    {
        $sensor = [
            // chrome
            "7a74G7m23Vrp0o5c9067691.4-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.86 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,382258,4455154,1440,829,1440,900,1440,428,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8928,0.669005142334,776797227576.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.macys.com/account/signin?-1,2,-94,-115,1,0,0,0,0,0,0,2,0,1553594455153,-999999,16619,0,0,2769,0,0,4,0,0,0873AC2ED7D6E59A5259E759324CDA1C~-1~YAAQLTxDF3ZcoH9pAQAA2hJyuQEC3eDYSuBdlW9QRIFFCUbdG1+noaM+hRmihxH6njsg7P2oou0X8/44cOAfjpyaBTwV+2+mbkLcEAJH1d02H/j08QeqwhziaoYslGvX5WEfYphDZMU5cpNPHVE9PsYekBNhZH0Uvk0AsTvvkJ9plzbudRlwICO0bpeyU4y6k/gQsUTZ+O+xx2mbeSWDjB4BHSxU7b+tCbAbFFd5CrMRMTvlkk0CxMJNGkWvyZDk/qllkE8uZ+9A1uV2CDlGE0plmN5G799uhExK~-1~-1~-1,27638,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,22275790-1,2,-94,-118,71695-1,2,-94,-121,;3;-1;0",
            "7a74G7m23Vrp0o5c9067691.4-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.86 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,382258,4480829,1440,829,1440,900,1440,428,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8928,0.415264718207,776797240414.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.macys.com/account/signin?-1,2,-94,-115,1,0,0,0,0,0,0,2,0,1553594480829,-999999,16619,0,0,2769,0,0,5,0,0,1FC0CC88B0FC826AD9445E70EC69FAB2~-1~YAAQLTxDFx5foH9pAQAAlHZyuQGH/PpGrha4S2+hu9de6gOQJ2wvcrEuDvGptTrLDcHJAI8otMUnmLSRY3juGybib+0guRrX4X3b9trJRob/ni5YM/EzkQiUZxTQERdm53DhxESsbcOhgGuH2uJnqcL0xjKLZ+qLmM0dPlILSD/xM57ohkzRyGKmKF0Nnmi8uM2//RKbD9EcgmkfaHfuXUj6kzEUpcSEpfG1PJvHE2hDJ3FhbKBv5tPZp+OWn7sR1lmwNH1Ev26ZxldDJwMrc+ssDjLV8/uQvnYn~-1~-1~-1,28136,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,362947716-1,2,-94,-118,72193-1,2,-94,-121,;4;-1;0",
            "7a74G7m23Vrp0o5c9067691.4-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.86 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,382258,4500400,1440,829,1440,900,1440,428,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8928,0.467417778233,776797250199.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.macys.com/account/signin?-1,2,-94,-115,1,0,0,0,0,0,0,3,0,1553594500399,-999999,16619,0,0,2769,0,0,6,0,0,4062C7DC35FEBACACBCDB454039FBCE7~-1~YAAQLTxDF9NjoH9pAQAA0cByuQHjDJwbxHhCqVLTVczqss4RANbfxG+zvEbBi8d+yZ32WChIsqcoq+nm1Sbzpg4AJqW6zpBXZjgmJDu486REDsqvSFCFIvRao4m44HrO6bvfmviD3lluUjvERoLZE7GlFK9yjE/HCcQ817cvAtvj2H3zmDt5Dh1Is/UClHI2lQJigsq0dvQL4kKZ3ClaT/kd5Fra0he73Z34p5Vcvis+w48d0/BzG5PqAZIAniZaoNmJ9VB1Z7WzucLLllctTgUkH5tpfksvd1Xz~-1~-1~-1,28070,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,22502000-1,2,-94,-118,72122-1,2,-94,-121,;4;-1;0",
            "7a74G7m23Vrp0o5c9067691.4-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.86 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,382258,4514468,1440,829,1440,900,1440,428,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8928,0.244146485122,776797257233,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.macys.com/account/signin?-1,2,-94,-115,1,0,0,0,0,0,0,3,0,1553594514466,-999999,16619,0,0,2769,0,0,6,0,0,09ACAB88A9ECBA3DE3E251F6593D4B78~-1~YAAQLTxDF39moH9pAQAAnvhyuQH8CnutvdZb5E+yevpgposwpVp5DHd2RvJKIFv32v80CsrZYoV4vIREj7Pe9Gm3ByNtLxmTbkQEkT4O6a8bnzrJi7ZV/oebkMA3HYSLLEct833d5Geft+VF64u6uxMVn4OGQ7jhi4+4Qz+fw1IJlZSb5qd2vGj/U1Ut6k+BX5ZGRjjyKQDVzaMCuGVwFjohyPnq7aMeHRbk51f9ajxy0HDI+Adrr7vbctWYcYAi/DdZsg/hGrUaw8VATb+fVm1WOAlma5WXkrJA~-1~-1~-1,27951,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,1015755127-1,2,-94,-118,71910-1,2,-94,-121,;4;-1;0",
            // firefox
            "7a74G7m23Vrp0o5c9067601.4-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0,uaend,11059,20100101,en-US,Gecko,1,0,0,0,382258,8125911,1440,829,1440,900,1440,453,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6007,0.362451461181,776799062955,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.macys.com/account/signin?-1,2,-94,-115,1,0,0,0,0,0,0,3,0,1553598125910,-999999,16619,0,0,2769,0,0,6,0,0,C5953DECACC520BFEF918FC86B64763C~-1~YAAQN8ZMaNrwTrZpAQAA2w6quQEx1sNp3fEQmB/bWGSxB6EOeXfdP6E9A+/AjfjI+LbLW5AWg89Xwb3FWTIq3fDbBZ8BfF1uASYQ7yF/hsQSVxgVutQNTm5p5IoKQkLdGgCbA7N54PWWZpvVeGsyI39E2oOnMHf5POWtbV9u3/opnBa1VwL2oDiWzdWyKVht0wYGtCtV/7bS7lQdRjKCnqEr3JD37mygvEABuhJIsqYj+0BRUrjItn+3XiD3JQU4rEKK2VBYwyGaFIIRUyqGTiXHnVTP1wNimFN1~-1~-1~-1,27463,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,8125928-1,2,-94,-118,68694-1,2,-94,-121,;5;-1;0",
            "7a74G7m23Vrp0o5c9067601.4-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0,uaend,11059,20100101,en-US,Gecko,1,0,0,0,382259,8485973,1440,829,1440,900,1440,453,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6007,0.474157396237,776799242986,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.macys.com/account/signin?-1,2,-94,-115,1,0,0,0,0,0,0,2,0,1553598485972,-999999,16619,0,0,2769,0,0,4,0,0,851CD8921E560DE98DC43525BFC7C7AE~-1~YAAQ5eEyF/SO3YJpAQAA+Y6vuQGa79/uBBdXbGA0Yfnf3874C0+sKCOKKnUScizLiLcHdTif5D/nhRh6RJrmCzq2J9UIO+6gpMrcyJoha4u3kKkDrwwgrRfEt5nQgAMv310eHU5+6kownckJ7NCi/+Ek4lq592lPUI/+3hbqCmnfpynRD5UR/NDAyLZgvvAv/O89jAEvJZcbohCMajqnhYdrzWsfoMq9ZfANKd2U+Akg68nS656sRpsdIrId4BIg21cuKAsRHg/KNjKhXBo5bfSS5GgI3+rsyTG4~-1~-1~-1,27345,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,76373793-1,2,-94,-118,68627-1,2,-94,-121,;3;-1;0",
            "7a74G7m23Vrp0o5c9067601.4-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0,uaend,11059,20100101,en-US,Gecko,1,0,0,0,382259,8509739,1440,829,1440,900,1440,453,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6007,0.739822012369,776799254869,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.macys.com/account/signin?-1,2,-94,-115,1,0,0,0,0,0,0,2,0,1553598509738,-999999,16619,0,0,2769,0,0,3,0,0,1C733E69B019D088E37EE5A81B946371~-1~YAAQ5eEyF3uP3YJpAQAALO6vuQGyjrfS0MMlH5z7ZjUgL7qYhM0y1G/Fj8+3gDugXKIRBxfl4GdBDMkwCbh2zsyr3dOLohY3Pt2yz5uI1DuzVcU+BJyeweq++OC/Sfv8IkfyqLfGQk4A+H9o/0cAKOMmHRnP4BBaUGEiZQ9/+QDRHmDJUEDFKSVGXw84dymq04rI+TR4WRv59IQ1c6RrgvUgcl1B9yDWLwQBgfvTk2cpIDeEA4TkLNUXopJWOOuYHO6BHANP4GTyWIGUUDgO6z3ZJIbl6VtWxGSO~-1~-1~-1,27063,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,8509747-1,2,-94,-118,68332-1,2,-94,-121,;3;-1;0",
            "7a74G7m23Vrp0o5c9067601.4-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0,uaend,11059,20100101,en-US,Gecko,1,0,0,0,382259,8527039,1440,829,1440,900,1440,453,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6007,0.389923922194,776799263519.5,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.macys.com/account/signin?-1,2,-94,-115,1,0,0,0,0,0,0,2,0,1553598527039,-999999,16619,0,0,2769,0,0,2,0,0,0FCE8CB7F4A730EC5BCF973EF5074094~-1~YAAQ5eEyF4iP3YJpAQAADTOwuQHEYSEY9Cs7RYppdzdHsY1F6Bu4T7HHf5LhME0EN3C6KsPcJhKNYLSBffs4u9RQnZkNqbZcwfzpKH1WNyANYTqz/9JazT8iSY+h4WKI/UYtmn0JAK7e1x/PCh3g3/RfVqLlYztvT6q4p6Wn9Hy+GbYoxdyitdfmRmMWTuLmB1B759BwxFfE4Hbj+ZwBftaUsNxI84xc3aExNouEwfKzM/f7FiQU8z7V2CFwkCillD08RR829fuP4flUidHVsQAv0WPs/3aQRg8T~-1~-1~-1,27533,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,8527034-1,2,-94,-118,68890-1,2,-94,-121,;2;-1;0",
            // safari
            "7a74G7m23Vrp0o5c9067601.4-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0.3 Safari/605.1.15,uaend,2819,20030107,en-US,Gecko,1,0,0,0,382259,9464488,1440,829,1440,900,1223,309,1223,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:0,bat:0,x11:0,x12:1,8916,0.18662647293,776799732244,loc:-1,2,-94,-101,do_dis,dm_dis,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.macys.com/account/signin?-1,2,-94,-115,1,0,0,0,0,0,0,1,0,1553599464488,-999999,16619,0,0,2769,0,0,1,0,0,97FC7CE32E331E9A6CE17C11DA4EEDDA~-1~YAAQ7NH1V0bI065pAQAAeoC+uQEZS0EGzXBupMcJJyMjIawNrzVL3pi6ZJ65lGknMrgWGWbNJT+xYVvjOeNRh8mNQI90uUbF/Oqw4AUgK2SyThlNICUmzGl2JIcfhJrJkubQ8/Xj1LKhkMNsyL+mu4tHrzr5TrnRdAJCLKUnKb/8QZbbg9hYA+sJWLbfDDDBcoLfMHpP/erh6SX/+QtYEOR1m2JaJ9kXNhjW0GpudL9tRY+5n95MXzPFCKDSKrvPm4H8EQZQIJFGQgEsOh7R+8Bptrr8bO+Ts0gp~-1~-1~-1,27406,-1,-1,25952624-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,19165588193-1,2,-94,-118,71609-1,2,-94,-121,;1;-1;0",
            "7a74G7m23Vrp0o5c9067601.4-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0.3 Safari/605.1.15,uaend,2819,20030107,en-US,Gecko,1,0,0,0,382259,9573779,1440,829,1440,900,1223,309,1223,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:0,bat:0,x11:0,x12:1,8916,0.381242201190,776799786889.5,loc:-1,2,-94,-101,do_dis,dm_dis,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.macys.com/account/signin?-1,2,-94,-115,1,0,0,0,0,0,0,0,0,1553599573779,-999999,16619,0,0,2769,0,0,1,0,0,7027F7FE0090418CECC24E931C6DC3EC~-1~YAAQ7NH1V63M065pAQAAqSfAuQHwKLCfExvcWkGcxwwmAymqKsMEHjaryjhlPLbMtXw02G+Mw9/4j5ymJJWjopFIUeYUtYV7Vzej/KrIUVVJBo9AWlI3dPOSqC3M74W2wx9AgoPfA2oGS/F+Roo3ltZlvWbTY/NnQrxH6aVR8lqO9N2drhKW/QUtP1MmWQbRL+wIRLdTa681/yhDgixXSpWv1TfWowEhInZkHyC9CnLDIkxoFj9IGkLh1okqj1nk8R9u/VmA9zpuCFt253WQvHp+kRO9/bmxhH9o~-1~-1~-1,27839,-1,-1,25952624-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,9573761-1,2,-94,-118,72209-1,2,-94,-121,;1;-1;0",
            "7a74G7m23Vrp0o5c9067601.4-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0.3 Safari/605.1.15,uaend,2819,20030107,en-US,Gecko,1,0,0,0,382259,9611656,1440,829,1440,900,1223,309,1223,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:0,bat:0,x11:0,x12:1,8916,0.534083143267,776799805828,loc:-1,2,-94,-101,do_dis,dm_dis,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.macys.com/account/signin?-1,2,-94,-115,1,0,0,0,0,0,0,1,0,1553599611656,-999999,16619,0,0,2769,0,0,1,0,0,42FB8CA89AE060E8A0ED8E15CB6E67B1~-1~YAAQ7NH1VyrO065pAQAAFb3AuQFevg6FEWPPsJNl59J0GZPfEkGB57a/AaFc/E4av8T6ypIvbf8/z0S2QTDIbNQFNkEt+aPRIFC1zvceiaeW4XcZEzfvMKCy+B3uDrjOoHcC7BnkrfuAaOLkSiyVl7hvuHzHW+fbf5EjTCgo0Lrdro70AlrrZEjP6HGEKwmXNMn7zhkykW74y2WcyNE8ygnzB7m01Z4b+aqS7wL6UoosaUF3aNYui+9KvETs1WHR/683VCzn732FXGqVh+Dnyur8ucJjL5vFsYg4~-1~-1~-1,27457,-1,-1,25952624-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,9611652-1,2,-94,-118,71697-1,2,-94,-121,;0;-1;0",
            "7a74G7m23Vrp0o5c9067601.4-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0.3 Safari/605.1.15,uaend,2819,20030107,en-US,Gecko,1,0,0,0,382259,9638159,1440,829,1440,900,1223,309,1223,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:0,bat:0,x11:0,x12:1,8916,0.06100885430,776799819079.5,loc:-1,2,-94,-101,do_dis,dm_dis,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.macys.com/account/signin?-1,2,-94,-115,1,0,0,0,0,0,0,0,0,1553599638159,-999999,16619,0,0,2769,0,0,1,0,0,3CC8716904A551B9B91BE6230760B839~-1~YAAQ7NH1VyDP065pAQAA6CbBuQHia9Lwzf34qY7Lso2XZ5QQDHCC/mbzAOzANvVbwaKG2cA4lpOTrwP2P3uqthFs+wi+h+xUG4klbae7x6qmlRzFKuANXPntqByu2BTImligzizMCj5Fr/Q1wvzFSz899WiT8Po3yR2A3AhzgotIjmdyzzXCuwcvXATC1VEfhoHB26tsan2SWgAZ+6MvFhNgxF6PO4enCKRtArmUOo32XlBUpDNmxzeOAmO6kqQIG8pA5euYuFy1CFNG2p5ySmVujA3R5iUAb6k1~-1~-1~-1,27883,-1,-1,25952624-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,86743589-1,2,-94,-118,72182-1,2,-94,-121,;1;-1;0",
        ];
        $sensor_data = $sensor[array_rand($sensor)];

        return $sensor_data;
    }

    private function sendSensorData($sensor_data, $sensorDataPostUrl = 'https://www.macys.com/public/6e2617b321619d6e5773fb99db069')
    {
        $this->http->RetryCount = 0;
        $referer = $this->http->currentUrl();

        $data = [
            "sensor_data" => $sensor_data,
        ];
        $headers = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
            "Origin"       => "https://www.macys.com",
            "Referer"      => "https://www.macys.com/account/signin",
        ];
        sleep(1);
        $this->http->PostURL($sensorDataPostUrl, json_encode($data), $headers);
        $this->http->JsonLog();

        $this->http->RetryCount = 2;
//        $this->http->setDefaultHeader("Referer", $referer);
        $this->http->setDefaultHeader("Referer", "https://www.macys.com/account/signin");
        sleep(1);
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useFirefox();
            $request = FingerprintRequest::firefox();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if (!empty($fingerprint)) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

//            $selenium->http->removeCookies();
//            $selenium->disableImages();
//            $selenium->http->setUserAgent($this->http->userAgent);
            $selenium->http->saveScreenshots = true;
            $selenium->seleniumOptions->recordRequests = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();
            $selenium->http->GetURL("https://www.macys.com/account/signin?cm_sp=navigation-_-top_nav-_-signin&lid=glbtopnav_sign_in-us");

            $loginInput = $selenium->waitForElement(WebDriverBy::id('email'), 7);
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('pw-input'), 0);

            if (empty($passwordInput)) {
                $selenium->driver->executeScript("let login = document.querySelector('input[id = \"email\"]'); if (login) login.style.zIndex = '100003';");
                $selenium->driver->executeScript("let pass = document.querySelector('input[id = \"pw-input\"]'); if (pass) pass.style.zIndex = '100003';");
                $selenium->driver->executeScript("let loginBtn = document.querySelector('button[id = \"sign-in\"]'); if (loginBtn) loginBtn.style.zIndex = '100003';");
                $loginInput = $selenium->waitForElement(WebDriverBy::id('email'), 0);
                $passwordInput = $selenium->waitForElement(WebDriverBy::id('pw-input'), 0);
            }

            $button = $selenium->waitForElement(WebDriverBy::id('sign-in'), 0);
            $this->saveToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                $this->DebugInfo = "login fields not found";

                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $selenium->driver->executeScript("document.getElementById('stay-signedin').checked = true;");
            $this->saveToLogs($selenium);

            $selenium->driver->executeScript('
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/"access_token":|"error_description":|"tokenCredentials":/g.exec( this.responseText )) {
                            localStorage.setItem("responseData", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
            ');
            $button->click();
            sleep(4);

            if ($selenium->waitForElement(WebDriverBy::id("sec-cpt-if"), 0)) {
                $selenium->waitFor(function () use ($selenium) {
                    return !$selenium->waitForElement(WebDriverBy::id("sec-cpt-if"), 0);
                });
            } else {
                $this->logger->debug("delay 15 sec");
                sleep(15);
            }

            if ($button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "overlay-close-btn")]'), 0)) {
                $this->saveToLogs($selenium);
                $button->click();

                $this->logger->debug("delay 15 sec");
                sleep(15);
            }

            $this->saveToLogs($selenium);

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);

            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
            $responseData = null;

            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");

                if ($this->http->FindPreg('#"access_token":|"error_description":|"tokenCredentials":#', false, json_encode($xhr->response->getBody()))) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $responseData = json_encode($xhr->response->getBody());
                    $this->logger->info("[Form responseData]: " . $responseData);

//                    break;
                }

                if (strstr($xhr->request->getUri(), 'starrewardssummary')) {
//                if ($this->http->FindPreg('#walletCardsIndicator#', false, json_encode($xhr->response->getBody()))) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $this->responseDataRewards = json_encode($xhr->response->getBody());
                    $this->logger->info("[Form responseDataRewards]: " . $this->responseDataRewards);
                }

                if ($responseData && !empty($this->responseDataRewards)) {
                    break;
                }
            }

            $this->saveToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            if (!empty($responseData)) {
                $this->http->SetBody($responseData);

                return true;
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        if ($message = $this->http->FindSingleNode('//small[contains(text(), "Your password must be between 7-16 characters,")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "Complete your profile") or contains(text(), "Reset your password")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "notification-error")]//p[contains(@class, "notification-body")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Sorry, it looks like there\'s a problem on our end. For assistance, please call')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'Your security and privacy is important to us. To keep your account secure, reset your password.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Your email address or password is incorrect')) {
                throw new CheckException("Your email address or password is incorrect.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;
        }

        return false;
    }

    private function saveToLogs($selenium)
    {
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }

    private function getSensorDataFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $cache = Cache::getInstance();
        $browser = clone $this->http;
        $browser->getDefaultHeader("User-Agent");
        $cacheKey = "sensor_data_virgin" . sha1($browser->userAgent);
        $data = $cache->get($cacheKey);

        if (!empty($data) && $this->attempt == 0 && $this->attempt != 2) {
            return $data;
        }

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
//            if ($this->http->FindPreg('#Chrome|Safari|WebKit#ims', false, $selenium->http->getDefaultHeader("User-Agent"))) {
//                $selenium->useGoogleChrome();
//            }
            if (empty($data)) {
                $selenium->useFirefox();
            } else {
                $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_53);
            }

            $selenium->http->removeCookies();
//            $selenium->disableImages();
            $selenium->http->setUserAgent($browser->userAgent);
//            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->start();
            $selenium->Start();
            $selenium->keepCookies(false);
            $selenium->http->GetURL("https://www.macys.com/account/signin?");

            //            $pass = $selenium->waitForElement(WebDriverBy::id('password'), 0);
            sleep(5);
            $login = $selenium->waitForElement(WebDriverBy::id('email'), 7);
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if ($login/* && $pass*/) {
                $this->logger->info("login form loaded");
                $selenium->driver->executeScript("(function(send) {
                    XMLHttpRequest.prototype.send = function(data) {
                      console.log('ajax');
                      console.log(data);
                      localStorage.setItem('sensor_data', data);
                    };
                })(XMLHttpRequest.prototype.send);");
                $login->click();
                sleep(1);
                $sensor_data = $selenium->driver->executeScript("return localStorage.getItem('sensor_data');");
                $this->logger->info("got sensor data: " . $sensor_data);

                if (!empty($sensor_data)) {
                    $data = @json_decode($sensor_data, true);

                    if (is_array($data) && isset($data["sensor_data"])) {
                        $cache->set($cacheKey, $data["sensor_data"], 500);

                        return $data["sensor_data"];
                    }
                }
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return null;
    }
}
