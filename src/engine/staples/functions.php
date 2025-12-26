<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerStaples extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.staples.com/grs/rewards/sr/loyaltycenter';

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;

        $this->http->setHttp2(true);
        $this->setProxyGoProxies();
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $loginURL = 'https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051';
        $this->http->GetURL($loginURL);

        if ($this->http->Response['code'] == 403) {
            $this->DebugInfo = '403';

            return false;
        }

        $this->getCookiesFromSelenium();

        return true;

        // Avoiding slow site error
        $slowSiteErrorRegexp = '#We’re sorry, some customers are experiencing slower than expected site speed#i';
        $i = 0;

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
            $this->logger->error($message);

            return false;
        }

        while ($i < 3 and $this->http->FindPreg($slowSiteErrorRegexp)) {
            sleep(15);
            $this->http->GetURL($loginURL);
            $i++;
        }// End of avoiding slow site error

        // retries
        if (empty($this->http->Response['body'])) {
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
        }

        if (!$this->http->FindSingleNode('//div[@class="LoginCom__forgotUsernameContainer"]') && !$this->http->FindPreg('/button id="loginSubmit"/')) {
            return $this->checkErrors();
        }

        $referer = $this->http->currentUrl();
        $jsessionId = $this->http->getCookieByName("JSESSIONID");
        $data = [
            "username"       => $this->AccountFields['Login'],
            "password"       => $this->AccountFields['Pass'],
            "rememberMe"     => true,
            "placement"      => "Login",
            "jsessionId"     => $jsessionId,
            "captchaAnswer"  => "",
            "flow"           => "login",
            "reloadFlag"     => false,
            "slideIn"        => false,
            "iFrame"         => false,
            "userAgent"      => $this->http->getDefaultHeader("User-Agent"),
            "stplSessionId"  => $jsessionId,
            "requestUrl"     => $referer,
            "nuCaptchaToken" => "",
            "page"           => 1,
            "ndsModeValue"   => "",
        ];

        if ($sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")) {
            $this->http->NormalizeURL($sensorPostUrl);
            $this->logger->notice('sensorPostUrl -> ' . $sensorPostUrl);

            /*
            if ($this->attempt == 1) {
            */
            $this->sendStaticSensorData($sensorPostUrl);
        /*
        } else {
            $this->sendSensorData($sensorPostUrl, null);
            $this->sendSensorData($sensorPostUrl, null);
        }
        */
        } else {
            $this->logger->error("sensorDataUrl not found");
        }

        $this->http->setDefaultHeader('Content-Type', 'application/json');
//        $postURL = "https://www.staples.com/idm/api/identityProxy/logincommon?TYPE=100663296&REALMOID=06-000a9084-7aa0-1daa-b12a-11140a0cf0c1&SMAUTHREASON=0&METHOD=GET&SMAGENTNAME=-SM-staples%2ecom--nginx&TARGET=-SM-https%3a%2f%2fwww%2estaples%2ecom%2fidm%2fapi%2fidentityProxy%2fdotcompostlogin%3flangId%3d--1%26userRedirect%3dtrue%26storeId%3d10001%26catalogId%3d10051";
        $postURL = "https://www.staples.com/idm/api/identityProxy/logincommon?langId=-1&userRedirect=true&storeId=10001&catalogId=10051";
        $this->http->PostURL($postURL, json_encode($data), ["Referer" => $referer]);
        $captchaSrc = $this->http->FindPreg('/class=\\\\"nucaptcha-media\\\\" src=\\\\"(.+?)\\\\"/');

        if ($captchaSrc) {
            $token = $this->http->FindPreg('/name=\\\\"nucaptcha-token\\\\" type=\\\\"hidden\\\\" value=\\\\"(.+?)\\\\"/');
            $token = "$token,|0|VIDEO|8||0|0";
            $data['nuCaptchaToken'] = $token;
            $captcha = $this->solveCaptcha($captchaSrc);
            $data['captchaAnswer'] = $captcha;
            $this->http->PostURL($postURL, json_encode($data), ["Referer" => $referer]);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We're sorry the site is currently unavailable.
        if ($message = $this->http->FindSingleNode('
                //h1[contains(text(), "We&rsquo;re sorry the site is currently unavailable.")]
                | //h2[contains(text(), "We’re updating our site, but we’ll be back soon.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, this site is temporarily unavailable.
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Sorry, this site is temporarily unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We’re sorry the site is currently unavailable.
        if ($message = $this->http->FindPreg('/We&rsquo;re sorry the site is currently unavailable\./')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Due to technical difficulties the current operation could not be completed
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Due to technical difficulties the current operation could not be completed')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(Due to technical difficulties the current operation could not be completed[^<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            // Internal Server Error
            || $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")
            // HTTP Status 404 -
            || $this->http->FindPreg("/<body><h1>HTTP Status 404 - <\/h1>/")
            // HTTP Status 500 -
            || $this->http->FindPreg("/<body><h1>HTTP Status 500 - <\/h1>/")
            // We’re sorry, some customers are experiencing slower than expected site speed.
            || $this->http->FindPreg("/(We\&rsquo;re sorry, some customers are experiencing slower than expected site spee)/ims")
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/ims")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Sorry, but an error has been made.
        if ($message = $this->http->FindSingleNode('//h3[contains(text(), "Sorry, but an error has been made.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Big things are in the works. Please check back soon!
        if ($this->http->FindSingleNode('//h1[contains(text(), "Big things are in the works.")]')) {
            throw new CheckException("Our website is currently undergoing scheduled maintenance. Please check back soon!", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re busy updating our site for you.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->http->Response['code'] == 403) {
            $this->DebugInfo = '403';

//            throw new CheckRetryNeededException(2, 1);

            return $this->checkErrors();
        }
        $response = $this->http->JsonLog();
        $location = $response->Location ?? null;

        if ($location) {
            $this->http->NormalizeURL($location);
            $this->http->GetURL($location);
        } else {
            $message = $response->msg ?? null;

            if (!$message) {
                /**
                 * As part of ongoing security system maintenance and to further secure your account, we are requiring that you reset your password.
                 * Please take a moment to help protect your information. Thank you for helping us be proactive with your account security!
                 */
                if (
                    $this->http->Response['body'] == '{"authReason":20,"msg":"","status":500}'
                    || $this->http->Response['body'] == '{"authReason":20,"compromised":false,"msg":"","status":500}'
                ) {
                    $this->throwProfileUpdateMessageException();
                }

                // AccountID: 20420
                if ($this->http->Response['body'] == '{"authReason":20,"compromised":true,"msg":"","status":500}') {
                    throw new CheckException("Dear Customer, Staples takes the security of our user information very seriously. As part of ongoing security efforts, we are requiring you to reset your password to help us better protect your account information. Thank you!", ACCOUNT_PROVIDER_ERROR);
                }

                $hasCaptchaResponseFl = $response->body->captchaResponse->hasCaptchaResponseFl ?? null;

                if ($hasCaptchaResponseFl === true) {
                    throw new CheckRetryNeededException(2, 1);
                }

                if ($message = $this->http->FindSingleNode('//div[contains(text(), "An unexpected error occured.")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                // broken account, no errors, no auth (AccountID: 6323291)
                if ($this->AccountFields['Login'] == 'EPPKLEE4') {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }
            $this->logger->error("Error -> {$message}");
            // We're sorry, but this username and password combination does not match our records. If you do not have a Staples.com account, you will need to create one.
            if (
                strstr($message, "We're sorry, but this username and password combination does not match our records.")
                || strstr($message, "As part of ongoing security efforts, we are requiring you to reset your password to help us better protect your account information")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, "For security purposes, this account has been locked. To unlock your account and reset your password please")
            ) {
                throw new CheckException("For security purposes, this account has been locked.", ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $staplesRewardsInfo = urldecode($this->http->getCookieByName("StaplesRewardsInfo"));
        $this->logger->debug("[StaplesRewardsInfo]: " . $staplesRewardsInfo);
        // Access is allowed
        if (
            $this->http->FindPreg("/\{isVerified:\"Y\",rewardsNumber:\"\d+\"\}/", false, $staplesRewardsInfo)
            || $this->http->getCookieByName("DirectCustomerNumber")
        ) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            if ($this->http->currentUrl() == 'https://www.staples.com/lp/easyrewardsoverview') {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $this->http->Response['code'] == 502
                && $this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->loginSuccessful();
        }

        // not a member
        if ($this->http->currentUrl() == 'https://www.staples.com/personalinfo/rewards?catalogId=10051&langId=-1&storeId=10001'
            && $this->http->FindSingleNode("//div[contains(@class,'stp--container-sm stp-tracking-maincontainer')]/@class")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // We're sorry, your rewards membership is not active.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, your rewards membership is not active.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Good news! We now have Staples Rewards® right here on Staples.com®!
         * To get started, create a new Staples.com account below
         * or login with your existing Staples.com account.
         */
        if (strstr($this->http->currentUrl(), '26storeId%3D10001%26origin%3Drl%26srwoption%3D1')) {
            throw new CheckException("Staples Rewards website is asking you to create a NEW Staples.com® Account, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        // not a member
        if (strstr($this->http->currentUrl(), 'https://www.staples.com/personalinfo/rewards?catalogId=10051&langId=-1&storeId=10001')
                    || strstr($this->http->currentUrl(), 'https://www.staples.com/sbd/cre/programs/rewards/learn-more/index.html')
                    || $staplesRewardsInfo == '{isVerified:"",rewardsNumber:""}'
                ) {
            $this->http->GetURL("https://www.staples.com/personalinfo/api/user");
            $this->http->JsonLog();

            if (
                $this->http->FindPreg("/^\{\"id\":\d+,/")
                && $this->http->FindPreg("/,\"legacyId\":\"\d+\",\"username\":\"[^\"]+\",/")
                && $this->http->FindPreg("/\"ignorePrdAlt\":false/")
                && !$this->http->FindPreg("/customerNumber/")
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg('/,"contactInfo":\{"firstName":"([^\"]+)\","lastName":"/') . " " . $this->http->FindPreg('/,"contactInfo":\{"firstName":"[^\"]+\","lastName":"([^\"]+)/')));
        // Rewards Number
        $this->SetProperty("Number", $this->http->FindPreg('/rewardsNumber":(\d+)/'));
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindPreg("/\"memberSince\":\"([^\"]+)/"));
        // Year-to-date summary: Rewards
        $this->SetProperty("YearToDateRewards", $this->http->FindPreg('/lifetime":(\d+)/'));
        // Year-to-date summary: Spend
        $this->SetProperty("YearToDateSpend", $this->http->FindPreg('/lifetimeSpend":(\d+)/'));
        // Membership Type - status
        $this->SetProperty('MembershipType', $this->http->FindPreg("/rewardsTierDescription\":\"([^\"]+)/"));

        // Balance - Points balance
        if (!$this->SetBalance($this->http->FindPreg("/\"balances\":\{\"current\":(\d+)/"))) {
            // AccountID: 2899363
            $this->SetWarning($this->http->FindSingleNode('//span[contains(text(), "Any Staples Rewards earned on this account will be issued to the main cardholder.")]'));

            return;
        }

        $readyToRedeem = $this->http->FindSingleNode('//div[contains(@aria-label, " Ready to redeem")]');
        // Ready to redeem
        if ($readyToRedeem > 0) {
            $this->AddSubAccount([
                "Code"        => "staplesReadyToRedeem",
                "DisplayName" => "Ready to redeem",
                "Balance"     => $readyToRedeem,
                "Currency"    => "$",
            ], true);
        }

        $rewardsNumberEncrypted = $this->http->FindPreg("/rewardsNumberEncrypted\":\"([^\"]+)/");

        $this->logger->info('Offers', ['Header' => 3]);
        $this->http->GetURL("https://www.staples.com/rewards/session/api/slpOffersProxy/offers?offerType=TARGETED&featured=false&offsetLimit=0");
        $offers = $this->http->JsonLog()->results ?? [];
        $this->logger->debug("Total " . count($offers) . " offers were found");

        foreach ($offers as $offer) {
            if ($offer->activated == false) {
                continue;
            }

            $this->AddSubAccount([
                "Code"           => "staplesOffer" . ($offer->activationId ?? $offer->id),
                "DisplayName"    => $offer->details->digitalDescription,
                "Balance"        => null,
                "ExpirationDate" => strtotime($offer->offerExpirationDate ?? $offer->details->endDate),
            ], true);
        }

        $this->logger->info('Easy Rewards offers', ['Header' => 3]);
        $this->http->GetURL("https://www.staples.com/rewards/session/api/slpOffersProxy/offers?offerType=MASS%2FTARGETED%2FRECYCLING&featured=true&offsetLimit=0");
        $easyRewardsOffers = $this->http->JsonLog()->results ?? [];
        $this->logger->debug("Total " . count($easyRewardsOffers) . " Easy Rewards offers were found");

        foreach ($easyRewardsOffers as $easyRewardsOffer) {
            if ($easyRewardsOffer->activated == false) {
                continue;
            }

            $this->AddSubAccount([
                "Code"           => "staplesEasyRewardsOffer" . $easyRewardsOffer->id,
                "DisplayName"    => $easyRewardsOffer->details->digitalDescription,
                "Balance"        => null,
                "ExpirationDate" => strtotime($easyRewardsOffer->details->endDate),
            ], true);
        }

        // refs #23582
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $this->http->PostURL("https://www.staples.com/rewards/session/api/pointHistory/getPointHistory", '{"limit":9,"offset":0}');
        $pointHistory = $this->http->JsonLog()->pointhistory ?? [];

        foreach ($pointHistory as $item) {
            $this->logger->debug("[Date]: {$item->transactionDate} / {$item->pointsArray[0]}");

            if ($item->pointsArray[0] != 0) {
                $this->SetProperty('LastActivity', $item->transactionDate);
                $this->SetExpirationDate(strtotime("+18 month", strtotime($item->transactionDate)));

                break;
            }
        }

        if (!$rewardsNumberEncrypted) {
            $this->sendNotification("rewardsNumberEncrypted not found");

            return;
        }

        // if was found "REDEEMABLE REWARDS AND COUPONS"
        $this->logger->info('Redeemable rewards and Coupons', ['Header' => 3]);
        $this->logger->notice(">>>>>>> Find Balance...  <<<<<<<");
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://www.staples.com/rewards/api/client/grs/rewards/sr/rewardscenter");
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        // Expiration Date  // refs #4280
        $coupons = $response->PROFILE_INFO_STATE->couponsRewards->couponDataList ?? [];
        $this->logger->debug("Total coupons were found: " . count($coupons));
        $exps = [];
        $today = time();

        foreach ($coupons as $coupon) {
            $value = $coupon->couponInfo->couponValue;
            $exparr = date_parse($coupon->couponInfo->expirationDate);
            $exp = mktime(0, 0, 0, $exparr['month'], $exparr['day'], $exparr['year']);

            if ($coupon->rewardsCoupon === false) {
                $this->AddSubAccount([
                    "Code"           => "staplesRewards" . $coupon->couponNumber,
                    "DisplayName"    => $coupon->couponInfo->descriptionText1,
                    "Balance"        => $coupon->couponInfo->couponValue,
                    "Number"         => $coupon->couponNumber,
                    "ExpirationDate" => $exp,
                ], true);

                continue;
            }

            // refs #23582
            /*
            if ($exp > $today && (!isset($expDate) || $exp <= $expDate)) {
                if (isset($couponValue, $expDate) && $exp == $expDate) {
                    $couponValue += $value;
                } else {
                    $couponValue = $value;
                }

                $expDate = $exp;

                $this->logger->debug("Expiration Date $expDate - " . var_export(date('d/m/Y', ($expDate)), true));
                $this->SetExpirationDate($expDate);
                // Expiring balance - Rewards Amount
                $this->SetProperty("ExpiringBalance", "$" . $couponValue);
            }// if ($exp > $today && (!isset($expDate) || $exp < $expDate))
            */
        }// foreach ($coupons as $coupon)

        // Balance - Your current Rewards earnings are: (Rewards earned)
        $data = [
            "startDate"       => null,
            "endDate"         => null,
            "type"            => "Monthly",
            "rewardsNumber"   => $rewardsNumberEncrypted,
            "monthyyyy"       => date('mY'),
        ];
        $headers = [
            "Accept"          => "application/json, text/plain, */*",
            "Accept-Encoding" => "gzip, deflate, br",
            "Accept-Language" => "en-US,en;q=0.5",
            "Content-Type"    => "application/json;charset=utf-8",
            "Referer"         => "https://www.staples.com/grs/rewards/sr/rewardscenter",
            "x-http-encode"   => "true",
        ];
        $this->http->PostURL("https://www.staples.com/rewards/api/rewardsCenter/rewardsSummaryInfo", json_encode($data), $headers);
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg("/\"success\":false,\"code\":400,\"message\":\"400 - /")) {
            sleep(5);
            $this->http->PostURL("https://www.staples.com/rewards/api/rewardsCenter/rewardsSummaryInfo", json_encode($data), $headers);
            $response = $this->http->JsonLog();
        }

        // nor rewards for some broken accounts: 2675615, 577365, 2899363, 501255б 5215714
        $balance1 = $response->rewardsSummary->rewardsEarnedIncudingRollover ?? null;

        // Balance - Ink & Toner Summary (Current ink or toner spend in the last 180 days)
        $data = '{"startDate":null,"endDate":null,"type":"Monthly","rewardsNumber":"' . $rewardsNumberEncrypted . '","monthYear":"' . date('mY') . '"}';
        $data = [
            "startDate"     => null,
            "endDate"       => null,
            "type"          => "Monthly",
            "rewardsNumber" => $rewardsNumberEncrypted,
            "monthYear"     => date('mY'),
            "grindType"     => "SPEND",
        ];
        $this->http->PostURL("https://www.staples.com/rewards/api/rewardsCenter/inkRecycleSummary", json_encode($data), $headers);
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg('/\{"success":false,"code":400,"message":"400 -/')) {
            sleep(5);
            $this->http->PostURL("https://www.staples.com/rewards/api/rewardsCenter/inkRecycleSummary", json_encode($data), $headers);
            $response = $this->http->JsonLog();
        }

        if (!isset($response->inkTonerSummary)) {
            return;
        }

        $balance2 = $response->inkTonerSummary->rewardsEarned;
        // ... ink cartridges recycled
        $this->SetProperty("InkCartridgesRecycled", $response->inkTonerSummary->catridgesRecycled);

        $subAccounts = [
            [
                "Code"        => "staplesInk",
                "DisplayName" => "Ink Recycling Rewards",
                "Balance"     => preg_replace('/[^\d\.]/ims', '', $balance2),
            ],
            [
                "Code"        => "staplesPending",
                "DisplayName" => "Pending Rewards",
                "Balance"     => preg_replace('/[^\d\.]/ims', '', $balance1),
            ],
        ];

        foreach ($subAccounts as $subAccount) {
            $this->AddSubAccount($subAccount);
        }

        $this->http->PostURL("https://www.staples.com/rplus/api/sbaBenefits/getMembershipContent", '{"fromDate":"","toDate":""}', $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->savings)) {
            return;
        }

        // YTD Saved
        if (isset($response->savings[1]->amount)) {// AccountID: 3669019, 102832
            $this->SetProperty("YearToDateSavings", '$' . $response->savings[1]->amount);
        }
        // Lifetime savings
        $this->SetProperty("LifetimeSavings", '$' . number_format(round($response->savings[0]->amount)));
    }

    public function parseCaptcha($selenium)
    {
        $this->logger->debug("parseCaptcha");
//        $url = $selenium->waitForElement(WebDriverBy::xpath("//img[@id = 'nucaptcha-media']"), 0);
        $url = $selenium->waitForElement(WebDriverBy::xpath("//div[@id = 'media-container']"), 0);

        if (!$url) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        /*
        $this->logger->debug("Captcha URL -> " . $url->getAttribute('src'));

        return $this->recognizeCaptchaByURL($this->recognizer, $url->getAttribute('src'), "gif");
        */
        $pathToScreenshot = $selenium->takeScreenshotOfElement($url);
        $captcha = $this->recognizeCaptcha($this->recognizer, $pathToScreenshot);
        unlink($pathToScreenshot);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg('/rewardsNumber":(\d+)/ims')) {
            return true;
        }

        return false;
    }

    private function sendSensorData($sensorPostUrl, $sensor_data)
    {
        $this->http->RetryCount = 0;
//        $referer = $this->http->currentUrl();
        $data = [
            "sensor_data" => $sensor_data,
        ];
        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "text/plain;charset=UTF-8",
        ];
        sleep(1);
        $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
//        $this->http->setDefaultHeader("Referer", $referer);
        sleep(1);
    }

    private function solveCaptcha($src)
    {
        $this->logger->notice(__METHOD__);
        $file = $this->http->DownloadFile($src, "gif");
        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($recognizer, $file);
        unlink($file);

        return $captcha;
    }

    private function sendStaticSensorData($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);
        $sensorData = [
            // 0
            "7a74G7m23Vrp0o5c9251521.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,399436,2402677,1382,784,1382,864,1382,301,1396,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:124.19999694824219,vib:1,bat:0,x11:0,x12:1,6018,0.16846014984,811706201338.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1623412402677,-999999,17366,0,0,2894,0,0,4,0,0,0A65012AFB477D4ED097D79548B0C956~-1~YAAQhicwF76WKOZ5AQAAbmnr+gaGr3CmstqhkrHNaAYCIongw6xWk2T/kWtUQjJZzoWXPYTRg5ndOE7koUOOV+MfxwawKuZ1M3RTor1gtvhzHBqYd07LR4g4BnCNlunimH78oyiDXzCy1nZkDiywRw3ynuaIXfOObh0ZyESW3eoVd4nOAT4D/zwKGKMHL5meLz5rdCFB/lty6OY1K7h0N+74RvoYYsbdAx9rL601O5AS5EUKX3mcHBLqHlU44YhCWEICjKSeXS4ybLJqwsU/TgmjdDtaz049/lm5byYS2NM+Z+kyUlFTmuHe457MDmxnVNKV+CGhxyw35KcZIMObrIMrDobq0SMt6TDxdlhz+R/UCJBfCbVjXmlAeDWGnV7u74NZHD0/n7q5dozF~-1~-1~1623415903,37505,-1,-1,26067385,PiZtE,59306,95-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,7207953-1,2,-94,-118,91523-1,2,-94,-129,-1,2,-94,-121,;6;-1;0",
            // 1
            "7a74G7m23Vrp0o5c9251521.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36,uaend,11635,20030107,en-US,Gecko,3,0,0,0,399436,2769286,1536,871,1536,960,1536,486,0,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.686172118343,811706384643,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,1,864,864,1;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,1;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1623412769286,-999999,17366,0,0,2894,0,0,7,0,0,F8D4A175F5CE5520F42E01F19599F07D~-1~YAAQqp0ZuAMKCqh5AQAA6SGu+gZPtKGtJqREsCRu/7eQB9Eb4BUzx9VgDDIpmFa/3PMb968hxI7vm3KxOvgFVBj6WAgYZVzpScJyHlPcW/ULpNuZtOMpPDUlQ52Yluv04cl9vekANZ16sLqvX/u5cBnBDYbVESfNcHP3PI/HUuiznB/0nt6IGuwWaRJEYsL2Gw/O0vmDw8OoNcq8QdTpLAQiOCJf8Shihulo9+vNSTxQsvi502tzQoIJq3KT5cgXmwsHSW6ca+eWRR0lAh04SkpUeGqQSV2bcrCs8LGr+ZAGM2VZrbc51PszfHcF/sH7OBXBjdyi3N3zSLl8DjWpRY31Is6+dabq9oVF6pQ3LJBMssINa/wzGa0vDeLDCXWBRJlzSNgly93CHo8oGpl9O9gEt8b5MQGX1Q==~0~-1~1623408939,38676,-1,-1,30261693,PiZtE,58141,62-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,74770533-1,2,-94,-118,94438-1,2,-94,-129,-1,2,-94,-121,;15;-1;0",
            // 2
            "7a74G7m23Vrp0o5c9251501.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399434,5390371,1536,871,1536,960,1536,486,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.07080919135,811702695185,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,3,0,1623405390370,-999999,17366,0,0,2894,0,0,4,0,0,F8D4A175F5CE5520F42E01F19599F07D~-1~YAAQnicwFwMclvZ5AQAAZmmA+gZ+OfByvcmBHvdcZ5bXkVqoXNQi4rv5iAwuJvSSPd75vlYk/ocHqnF5MgiaA4w4NtL/Ao+8VbEM23eVM0ubloSqC47gr9fPl/QPPHvxeDKnyfiPAnVFlY1/WxpxhAB0f62TRpgvsgYubsPA99Cea0eBHXlFf+yJGe1WcaPCDa23tULvYNeTIQP+hd2d5sPu/K9XMhdSos4dibygMheM7kI0yFdIEI6hupw+/9zIvq2rpp3ghbjLZOKGqVu2mCtpm1Q7vaW+41WRLZYHD6U4lILmNLo56xe/luhQK1GheOZJgNevMIj3vwH8L2JIgxOmb8uxPUpGWy6kgUcIBRxGO1OTzD6rU7Fmk4AswPRhJALX+9L0C08mT+M=~-1~-1~1623408930,37462,-1,-1,30261693,PiZtE,82296,96-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,16171113-1,2,-94,-118,93321-1,2,-94,-129,-1,2,-94,-121,;7;-1;0",
            // 3
            "7a74G7m23Vrp0o5c9251591.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399433,9782634,1536,871,1536,960,1536,486,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.510007092255,811699891316,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,4,0,1623399782632,-999999,17366,0,0,2894,0,0,12,0,0,41BB1776C06A082DB3179BDD4FBC8793~-1~YAAQqp0ZuClbB6h5AQAAINgq+gZN/zd6Av8vZ/2+8+KtzQY67xT/X+YsnSLI+mjDeTQ6oTlgXdgrQ5ucow92YsOP3iOe5JNUCYk5vdZXSLqrkJZvz7+izadGYWSYVOkP2sBIE5cnMgOYYO2UKcIoK54Bwfs6RJPhwk31tYYntcOUnDYRWEi8Hczyto9yjWGAV+4xnphpbrnPHrxDGNect9P8rIixco9S9xLCYAbxfULUn7JcqT0iRgKC/3ARaGBMC0gGmeiAklEWlMWvmNk2MHg5fm76GcpBtJ/4BDjq25kR7lE+49SmpPCdPJirQKJyT1xYv/KEmvSsBRe2jOifJB7ekW0f3pnF4CrtUAjTxuIWstGf9WTXb/YhrZjjQZ+fpDeiA0XXaSEs70M=~-1~-1~1623403277,37469,-1,-1,30261693,PiZtE,25130,50-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,1320655212-1,2,-94,-118,93467-1,2,-94,-129,-1,2,-94,-121,;22;-1;0",
            // 4
            "7a74G7m23Vrp0o5c9251571.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,399431,1663833,1536,871,1536,960,1536,462,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6018,0.512231659256,811695831916,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1623391663832,-999999,17366,0,0,2894,0,0,2,0,0,FC44A185AF06DC1B5223431D90EECB29~-1~YAAQCBEcuBvZtel5AQAA2uKu+QbtfK+3oXzH5rOe1maAeHHObrbAM8sZf+HAYWC/y1+wnaAB2FesREYykq1c2/va3v6xsgJKhJC5btlgbiuL6GvZykyog6Kp805b1yNxJ8ajeE5t9yIMZ8F83kiw9Zvm7RpRxRvuliWFEVZiLCm9YPXYTi70mQfsxRVEbrlnYbSC69KEr65VngyvDfBXWos5FAvp/128iVego2JL+D0NsSPj/5F65IYJ7AxV76pU12BU2+r4HJZoUC2ACmS5rlBWEqRPhdA/iOLScn5OiVVzkClIDngPIWl+Y1VIHtkuA4xH2ulk86TJezKJOgJ9hXXixqExmZU47R+CDQ53TmhQE14Pljerc1u2klbAzQ==~-1~-1~1623395150,35592,-1,-1,26067385,PiZtE,83279,16-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,44923242-1,2,-94,-118,88817-1,2,-94,-129,-1,2,-94,-121,;12;-1;0",
            // 5
            "7a74G7m23Vrp0o5c9150281.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,390460,223687,1536,880,1536,960,1536,392,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8975,0.279587891139,793465111843,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1369,864,0;1,2,0,0,1388,883,0;-1,2,-94,-102,0,-1,0,0,1369,864,0;1,2,0,0,1388,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?TYPE=100663296&REALMOID=06-000a9084-7aa0-1daa-b12a-11140a0cf0c1&SMAUTHREASON=0&METHOD=GET&SMAGENTNAME=-SM-staples%2ecom--nginx&TARGET=-SM-https%3a%2f%2fwww%2estaples%2ecom%2fidm%2fapi%2fidentityProxy%2fdotcompostlogin%3flangId%3d--1%26userRedirect%3dtrue%26storeId%3d10001%26catalogId%3d10051-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1586930223686,-999999,16976,0,0,2829,0,0,4,0,0,F1C53C9B665593F042583496F8CD72BB~-1~YAAQrOtwaChngmdxAQAAyX9pfAMGQdW4BagnyLDffJh5oOSx1YHFUCWJWolyH19GPx6iK5lk/ms8aXhmGbajs6ExXTdaI+EVGmZEiCV4r0u56pWJlqAnS5DweRMq1zJ9B/DydG/c2ze9F4pKH8faRpmCUhH6d5+6mtoRnwFj6Pt84owtEJcbWTYtHzbh01J/sQ+TP1NqgKXpBJARsiZvTWOzGZYnzjgAfSoL9T8nFJeKBnES81RSkj0zbtaY5iIVh9xuKUx9Osp0eXRCtGt4IXao9oGdYUQ7Hk5oyIVrWSvL2MhjnCiM2dHQxg==~-1~-1~-1,30096,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,2013097-1,2,-94,-118,103209-1,2,-94,-121,;5;-1;0",
            // 6
            "7a74G7m23Vrp0o5c9163751.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,390766,6998242,1536,880,1536,960,1536,449,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8975,0.946907313473,794088499120.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1369,864,0;1,2,0,0,1388,883,0;-1,2,-94,-102,0,-1,0,0,1369,864,0;1,2,0,0,1388,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?TYPE=100663296&REALMOID=06-000a9084-7aa0-1daa-b12a-11140a0cf0c1&SMAUTHREASON=0&METHOD=GET&SMAGENTNAME=-SM-staples%2ecom--nginx&TARGET=-SM-https%3a%2f%2fwww%2estaples%2ecom%2fidm%2fapi%2fidentityProxy%2fdotcompostlogin%3flangId%3d--1%26userRedirect%3dtrue%26storeId%3d10001%26catalogId%3d10051-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1588176998241,-999999,16989,0,0,2831,0,0,3,0,0,7E948E122F3A5313B9F6048D6A7D3D12~-1~YAAQ0y3+pWq5TZJxAQAA5MS5xgO76DyUJSyzh0XjWQIpOtC07ywAuEjdpdeSgk+hlWgC78+2tPzbMt9kMXnrMUy1SNhRUdS1gKI42xf9OpwsviNsrEMlRXszoCLWpW1bCWJ5MZYsbFcaAt+PPRTJ5ykCi+fcGmqOh4JCHvlQVcGOPLg8S5bWkQEQgxtEnQdymYJowZUa4fcpq3KNId9pEqK3TyVh4zf1F5yo8BDdrO57xLSB7lpSItn/V6/269wBAVFHd7KLYLxQoyJ24li1z48a7P6ezhlD+iyAa0tC9JdGDALVa0PqL7Khpg==~-1~-1~-1,29699,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,314920738-1,2,-94,-118,103034-1,2,-94,-121,;5;-1;0",
            // 7
            "7a74G7m23Vrp0o5c9251581.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,399432,7474202,1382,784,1382,864,1382,301,1396,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:124.19999694824219,vib:1,bat:0,x11:0,x12:1,6018,0.728404203364,811698737101,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,0,0,1623397474202,-999999,17366,0,0,2894,0,0,2,0,0,D1BF2BBE3DE74AF6D202D3B536A3AFC4~-1~YAAQnicwF+AGk/Z5AQAAXJ8H+gZG3muD8h6nnQ7FdLJmzhirK/vznHdnHOrMmhYbJJ4CERDYf/ldjstl9ADPiYRvnzWxjCZAgXwUjyRGeFMkn4LYJ5GU9LZK3XVGYwPj7WJyabINNpzwXYjU/rcyjjaEie+RuYOaTf6V4sDWfvt5CqbPyZELS2FSGEMf81dE8xcMtPYPoTo7ELeaQ849V3iTLGu0qlNGIEfYB5whl9yUbM4qkYvSjK/pauLP/cQr+/AtaGuBs7CIRt6emAcHEenOLIWh9RO+3mfXsjtUgMyYRdr9TYkNxeG9aYkoAoigPQxKFntRy9M2xWomSIDaJUqBqCO7LmZW96BWEH5SeCtKU7u4QEnP6Cn84xWMkEOp64m47jDYHl003PoF~-1~-1~1623401026,37513,-1,-1,26067385,PiZtE,55494,107-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,112112916-1,2,-94,-118,91524-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
            // 8
            "7a74G7m23Vrp0o5c9251581.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,399432,4979582,1382,784,1382,864,1382,301,1396,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:124.19999694824219,vib:1,bat:0,x11:0,x12:1,6018,0.04384401421,811697489791,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1623394979582,-999999,17366,0,0,2894,0,0,3,0,0,C4F15C3ECC196ED935A96DFDFD9A8D5C~-1~YAAQd6w4F507I+Z5AQAA3o3h+QZ4kMTgVVgcXanPM740fUPEPAAevauobN9JCSBtnwV1ttsy7UWr1ZRqQSxn4mbwSMkclWzyFgw2tH9E5REZv0rlLVTpe9ZkfZdYOHm0UfJzvPfKN5GHExinCPL3kx19/Jqjx/ZeZBmLiok7Rg4c+J/nozSXQmFl0qgcmc2ZEWESLy5peNHfmvb6+fQacHXyeKV99oPZp/WtW/oLru8JxRWG67RrbEdnTACHZKhIcWI/LTsWsH/MiexXiCNCydv5nu89L2m2KyKS6SmVlOOjHxqoTt73j7DrJzW2l/wrA50TPLlAoN/m58CYclns0efwXUU8fQu2PLnZ5FWeNiry1+PaWf/QuI+KxHXD8jk/sBR3Z3IBakzzu1zw~-1~-1~1623398516,37614,-1,-1,25543097,PiZtE,98148,56-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,134448657-1,2,-94,-118,91595-1,2,-94,-129,-1,2,-94,-121,;15;-1;0",
            // 9
            "7a74G7m23Vrp0o5c9160361.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,391352,5978680,1536,880,1536,960,1536,447,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8975,0.204465421102,795277989339.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1369,864,0;1,2,0,0,1388,883,0;-1,2,-94,-102,0,-1,0,0,1369,864,0;1,2,0,0,1388,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?TYPE=100663296&REALMOID=06-000a9084-7aa0-1daa-b12a-11140a0cf0c1&SMAUTHREASON=0&METHOD=GET&SMAGENTNAME=-SM-staples%2ecom--nginx&TARGET=-SM-https%3a%2f%2fwww%2estaples%2ecom%2fidm%2fapi%2fidentityProxy%2fdotcompostlogin%3flangId%3d--1%26userRedirect%3dtrue%26storeId%3d10001%26catalogId%3d10051-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1590555978679,-999999,17015,0,0,2835,0,0,4,0,0,9285E42F36E9346F0428434EDD3E9101~-1~YAAQxQcPFzQa11JyAQAAMBuGVAMJCp5TubjVP67d2fm4NTC4H3NnDLdSs9El3UN0T5LB4s0sK14M4sauJWtRZkK2nvqhNrtSTt/np55LeLnDk/5N1Tq3UhcdTKKpCTQ1khCWJCK9LDnv9zwXQ9P0eDGBZGymwICE2wz60yMSb+DdDZ8fVX9fxyR+dxtq+T0wQZ6KzSGDckUHP4Dex7IKkQpYpkwZDmgDVq4MS7rKJjd7ucBbm/9UhVtzY3MaE2I6nQJNIB401FprXP4PBpjcPOe1GLpt1uuMZ9ZyTW0n2De9he17opIJjU4ECA==~-1~-1~-1,29022,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,484273009-1,2,-94,-118,102283-1,2,-94,-121,;5;-1;0",
            // 10
            "7a74G7m23Vrp0o5c9251581.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399432,7180812,1536,871,1536,960,1536,486,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.06873495234,811698590406,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1623397180812,-999999,17366,0,0,2894,0,0,3,0,0,B42BFF586960500976507E3EDF337DB8~-1~YAAQqp0ZuMbPBqh5AQAAoyQD+gbARUqS07QGqahZBUskpjoG4w4FRN9vw06N33lObZeZWA1gHdor868xX5SH9CaVU6SDFrPjD2YrL0RMt/sZbLyWzchvddB6MbTLncjybTitt8tFoY+e7IhMCGQPLBRXfwVaDa3YHtQPheHJ5VwEMZ1aDCPvm4+2tjnZOXI+AGdykfV3K4HjUcmZ98rHR5/4XW9G8uqQ5at9X1tpB/hloa9zcOogFeX6Gvzs/spNW/lsZcZCVmm2X+oNeEVpMWfT23MtBr4HDnYpYivc8ezOhuAXNNmW9EU6Wtid/ombVjB2y6P3bGd5xA+TzbBbMZM4w2Ydn6fC2YApoU6PSt0p4JIIp7BiEOJJK635PO7xZXI8mm5V2+nJ/Uc=~-1~-1~1623400672,36907,-1,-1,30261693,PiZtE,40536,35-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,1744937073-1,2,-94,-118,92799-1,2,-94,-129,-1,2,-94,-121,;9;-1;0",
        ];

        $secondSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9251521.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,399436,2402677,1382,784,1382,864,1382,301,1396,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:124.19999694824219,vib:1,bat:0,x11:0,x12:1,6018,0.395306564197,811706201338.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,548,0,1623412402677,5,17366,0,0,2894,0,0,548,0,0,0A65012AFB477D4ED097D79548B0C956~0~YAAQhicwF+SWKOZ5AQAAEmvr+gaajEaUko2XLTDDu/obgFY3g0NcgRSdID45zCNT0yzP73YLvqy9mhnHR47v0YEcyuo4QzOCVv3At10UJM5Foa9VhBtKF4+2j/lijOf+wH0snoSTtYjhNH3x+sq+MNLMNdHpvOlF1i7LSIRsfw9ca7sKzX+aa7n/ptHSNjnHTMC9D0FZyQUlAn98dyT33TlAzUxUUqKW3ICrvLIz5cQ8dGBtn0gAAvJztym9qdxW/vlZijaveKD8JcYeJ6RsaqlYKoA97del2x59SRz+jdcypb8OAEOn91yy4LjavXXc04whBB9dLyj/T9DV9PVNkXk2GZ/5g1TaUgPIKSAKt0q9GvE3u/AO4foC+q20PU65y7CNqX96oPJ6HS73mFXaDjIq2uGtkGA7MtI=~-1~||1-SiRIyKyIMj-1-10-1000-2||~1623415947,40492,496,-845217378,26067385,PiZtE,36226,68-1,2,-94,-106,8,1-1,2,-94,-119,0,0,0,200,0,0,0,200,0,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.4653571ee08538,0.3a731aecbdac8,0.3083a01fa17d9,0.625c4e332af58,0.5a7217aa6a4238,0.cab31d671f3e58,0.0133bc27192a18,0.5b27c1cdcf5788,0.424db1bc827f9,0.ca6c632bae8e88;0,0,1,0,2,2,1,0,1,0;0,0,5,3,26,13,5,2,5,6;0A65012AFB477D4ED097D79548B0C956,1623412402677,SiRIyKyIMj,0A65012AFB477D4ED097D79548B0C9561623412402677SiRIyKyIMj,1,1,0.4653571ee08538,0A65012AFB477D4ED097D79548B0C9561623412402677SiRIyKyIMj10.4653571ee08538,25,138,198,192,161,189,182,144,13,136,209,65,182,118,228,219,197,82,2,183,213,223,214,250,209,81,142,247,53,181,125,216,432,0,1623412403225;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,-1498601298;1464348676;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5397-1,2,-94,-116,7207953-1,2,-94,-118,129809-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2.2222222222222223,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;3;9;0",
            // 1
            "7a74G7m23Vrp0o5c9251521.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36,uaend,11635,20030107,en-US,Gecko,3,0,0,0,399436,2769286,1536,871,1536,960,1536,486,0,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.271408024135,811706384651.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,1,864,864,1;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,1;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,9895,0,1623412769303,27,17366,0,0,2894,0,0,9897,0,0,F8D4A175F5CE5520F42E01F19599F07D~0~YAAQnicwF2ocmfZ5AQAAygPx+gZt7UYo0o6Hu2wy715P/AzUnhGHy/KHw6KL2lSe8mg5lssU4mT1yveRwjJFJ1a21m6NsSyospFLaDeeQnHO7tPABjMXyq/PXxKI4U/kkp9qmoQInFQQNm8tt0Hck/UBE9RkG+tixw0qJx2pETcQPl4Ov3lgYfDyPM+qf0+UrFg5V4kfyMFF4JpyzUqYWDc6usDG48NHYfyi0mCodfL54HIvxI+mUeAhcLYxd8I7SFSzaL4+1lit8+f8dgR5BxlM5H8hjWWaXjqMnblumRVr60dEcw3Jm+uqNVvHU1Djm6TLbcMVwRv6txey8/0qJ/+fXn6XSZ7LyxdCJVQQNWXPkU5jre8Ecf9zhi1CiIUvw6P5dwo0gNAV6w+c8dGMd1pdCMMfzBfs4w==~-1~||1-xurQgoPUUA-1-10-1000-2||~1623416281,41268,132,807932707,30261693,PiZtE,72190,77-1,2,-94,-106,8,1-1,2,-94,-119,20,40,200,40,20,20,20,0,20,0,0,0,20,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.a5ec792fb9f8d,0.70ab57ad17293,0.9c277f375c6f2,0.38ac17ad14f8c,0.54503697f646c,0.f2adc391dd23f,0.947882886eb5f,0.d94822025df23,0.33cbf31cc7781,0.8ffc90df513a9;2,0,0,1,1,3,6,1,3,2;0,0,2,2,5,4,17,2,10,5;F8D4A175F5CE5520F42E01F19599F07D,1623412769303,xurQgoPUUA,F8D4A175F5CE5520F42E01F19599F07D1623412769303xurQgoPUUA,1,1,0.a5ec792fb9f8d,F8D4A175F5CE5520F42E01F19599F07D1623412769303xurQgoPUUA10.a5ec792fb9f8d,80,202,13,255,92,21,182,192,80,4,67,32,211,21,122,96,223,226,95,47,226,141,120,161,38,176,77,61,106,183,109,70,798,0,1623412779198;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,74770533-1,2,-94,-118,132606-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0f7edf978cda71a82a5af4b78a8df770924ac922ce0e334cc52acd51016343e3,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;6;18;0",
            // 2
            "7a74G7m23Vrp0o5c9251501.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399434,5390371,1536,871,1536,960,1536,486,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.03148544215,811702695185,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,653,0,1623405390370,16,17366,0,0,2894,0,0,653,0,0,F8D4A175F5CE5520F42E01F19599F07D~-1~YAAQnicwFwUclvZ5AQAAcWuA+gbdsq3eS05UmGsChHLCRiCOEFmvLZ0AqvLfbr4LCpB7G2GS7I5KTX49dVP+3FsIO+9oK18Kv1Ah96Isg1rkoohGY+jYFkBiFqISLWrf6/IdkrlixPGi+x8t+ZocKZ1z6cWayTUQzUAHH3uT5cuZPwedUyl2Yqa1Zu+w1OL3CJJmuvGF+WbDM/MREnbdp+bbcUYluHFjbBXpCIHLzfZpuuiO3OIzssKzp9o4dlWizc+Fzd4mP7KtIEnkAJxjJgbWg1Is3sQsgejlDLVU/mxYPgvI2mrNNeliYIwNHHs03ZdRY1qFGPcy397vpfUyxQQ+p+fAwrOPFVrnvaFoODFtNHu/+J6geY8lpsfyGtKajZIDLshwhl39TSk=~-1~||1-LMWuivyCaL-1-10-1000-2||~1623408964,39922,910,1268849552,30261693,PiZtE,74736,70-1,2,-94,-106,9,1-1,2,-94,-119,40,40,40,40,60,60,40,0,0,0,0,0,20,180,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,16171113-1,2,-94,-118,98855-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;12;13;0",
            // 3
            "7a74G7m23Vrp0o5c9251591.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399433,9782634,1536,871,1536,960,1536,486,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.545316620272,811699891316,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,681,0,1623399782632,33,17366,0,0,2894,0,0,681,0,0,41BB1776C06A082DB3179BDD4FBC8793~0~YAAQqp0ZuDVbB6h5AQAA+tkq+gb/RdZ/nVaHOBtSDxGl0rNP3opInDlwmLO+3XTomFZuk8dfjcG99gIYUsoE0ysnTL1esT/Q2TRSMKm0a1n9b+ZUdaOUSu0W8FbduenITY0RSwge6NwM+861hyJJ8PsXLIdZMGcrF1xwlG3rMzmyGcU9+3QCZ3hIkpSreEV+Q1I3xrnd2eiKM00MqQnT3bVKIEtpn46rsgwmXLneI0C4qGv8rNGzoAXzZzgOlsM6V0HTrx1x8LBuwMIqCtvD09+XaWziV8Twq1du0FrIAZ6KyWHvIVgYXal83HrdGIjVH0AfbeLV+hOXaiuteh1lOfF8iw1eqj/SzCf1RE2re/nPBcrf4jFgAilkv/80e0jAj4obHimVLPWzDZbZfNGxwKRZoqio6vpnDQ==~-1~||1-EsrCxICNaL-1-10-1000-2||~1623403360,41243,864,-1630605517,30261693,PiZtE,107960,38-1,2,-94,-106,8,1-1,2,-94,-119,40,40,40,40,60,60,40,20,20,0,0,0,20,180,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.174345cf4aac8,0.8bf7ac638b76a,0.b502ca536cdb,0.9ae329f523ec3,0.4ca757ccae7e7,0.9efc730ee6f55,0.c437342cd8f3c,0.dd39112b0b3a1,0.fe0502846679f,0.a5561d8739cfc;1,0,1,1,1,0,1,4,1,0;0,0,0,0,3,0,0,15,5,3;41BB1776C06A082DB3179BDD4FBC8793,1623399782632,EsrCxICNaL,41BB1776C06A082DB3179BDD4FBC87931623399782632EsrCxICNaL,1,1,0.174345cf4aac8,41BB1776C06A082DB3179BDD4FBC87931623399782632EsrCxICNaL10.174345cf4aac8,211,147,157,37,17,22,252,41,34,219,85,180,51,222,13,201,225,0,46,4,63,145,214,215,18,87,13,224,209,150,18,185,448,0,1623399783312;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,1320655212-1,2,-94,-118,132313-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0f7edf978cda71a82a5af4b78a8df770924ac922ce0e334cc52acd51016343e3,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;3;29;0",
            // 4
            "7a74G7m23Vrp0o5c9251571.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,399431,1663833,1536,871,1536,960,1536,462,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6018,0.235836589117,811695831916,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,680,0,1623391663832,4,17366,0,0,2894,0,0,680,0,0,FC44A185AF06DC1B5223431D90EECB29~0~YAAQCBEcuCHZtel5AQAArPiu+QbL58q76j5sKTov/QLId5EoApxt4r9rnby+QRnI2bvkOm6Hc5EPbnezw156/8vKViqZ7raglp24INlbNP3RztqdSHNYgXFuJ4R3hjF1oTTZUWmJa2QKWYrFyLxDY19+LF+ACVaZxXo4swJ4jpCASULL5XoOB8Lf0Ejy3v0T2MfxnHkGctMN8ZsshyG0BeFS5k004h0GDYsSCVrHctE9gsv882T707o/EgEsyIMMdAK6p9JcwbegDfQM3MriC1WXVEmZRBNCpjtE481Oo1fiX9/+gYAde398b/C0dURA8QZDXtFMPf5hd+OmQTWm91fbrBe6PnoIT75ME4wBRId6jWrOCW4+t7SdYiDKkzSdxg3zFcbD0W2htZyNJNKyNt8B3bZqyn/Qbv4=~-1~||1-dZmexCjTRc-1-10-1000-2||~1623395158,40254,613,1189538132,26067385,PiZtE,57743,100-1,2,-94,-106,8,1-1,2,-94,-119,0,0,0,0,200,0,0,0,0,200,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.5d121f9870577,0.51a35001beecd8,0.86bc034893bb68,0.30530876f20808,0.a2755f00712228,0.51d3ab05fd1b98,0.431015f0e21f3,0.0f54789c50754,0.2cf4863f98c958,0.5f678cb75d0168;0,0,0,0,0,0,0,0,1,2;0,1,0,0,5,9,4,3,6,24;FC44A185AF06DC1B5223431D90EECB29,1623391663832,dZmexCjTRc,FC44A185AF06DC1B5223431D90EECB291623391663832dZmexCjTRc,1,1,0.5d121f9870577,FC44A185AF06DC1B5223431D90EECB291623391663832dZmexCjTRc10.5d121f9870577,182,63,146,29,53,243,9,28,87,205,68,249,128,96,199,109,136,121,191,116,124,105,65,31,46,171,245,21,149,209,181,31,497,0,1623391664512;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,44923242-1,2,-94,-118,127894-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;3;15;0",
            // 5
            "7a74G7m23Vrp0o5c9150281.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,390460,223687,1536,880,1536,960,1536,392,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8975,0.805047437402,793465111843,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1369,864,0;1,2,0,0,1388,883,0;-1,2,-94,-102,0,-1,0,0,1369,864,0;1,2,0,0,1388,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?TYPE=100663296&REALMOID=06-000a9084-7aa0-1daa-b12a-11140a0cf0c1&SMAUTHREASON=0&METHOD=GET&SMAGENTNAME=-SM-staples%2ecom--nginx&TARGET=-SM-https%3a%2f%2fwww%2estaples%2ecom%2fidm%2fapi%2fidentityProxy%2fdotcompostlogin%3flangId%3d--1%26userRedirect%3dtrue%26storeId%3d10001%26catalogId%3d10051-1,2,-94,-115,1,32,32,0,0,0,0,550,0,1586930223686,40,16976,0,0,2829,0,0,551,0,0,F1C53C9B665593F042583496F8CD72BB~-1~YAAQrOtwaFhngmdxAQAACotpfAM1JvyXfK8NluJAnJDpg41IrOFXJW/64s3gciXw2qg/a7h82EH/t+3Fa3ucbs58xOircoR2ig5neaBuDFrAcq2Hcsyva6ekwoAqNIDbQjeXs0XIlsRoeZInOJYH5rXAttgArfMHfl1GJGe9HiKJduzC+GecEMI6dhrwvJGMwcnl5XXt8IyM+DOF6hE1LSZHawRibfT3+DL9Fz2pUkzx1hRFSH1d4IXxX5FuvzudjnQXXaMKXr4uoeMycvdGLDEnJAWyAslV2kajhAXgb/g2FJmTbE8EIWzeQS2Ma6ig3GoNqzOgw6Sg~-1~-1~-1,31738,800,-480591687,30261693-1,2,-94,-106,9,1-1,2,-94,-119,52,55,46,43,67,70,45,10,11,8,8,8,13,407,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,5584-1,2,-94,-116,2013097-1,2,-94,-118,108145-1,2,-94,-121,;4;9;0",
            // 6
            "7a74G7m23Vrp0o5c9163751.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,390766,6998242,1536,880,1536,960,1536,449,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8975,0.660988609330,794088499120.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1369,864,0;1,2,0,0,1388,883,0;-1,2,-94,-102,0,-1,0,0,1369,864,0;1,2,0,0,1388,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?TYPE=100663296&REALMOID=06-000a9084-7aa0-1daa-b12a-11140a0cf0c1&SMAUTHREASON=0&METHOD=GET&SMAGENTNAME=-SM-staples%2ecom--nginx&TARGET=-SM-https%3a%2f%2fwww%2estaples%2ecom%2fidm%2fapi%2fidentityProxy%2fdotcompostlogin%3flangId%3d--1%26userRedirect%3dtrue%26storeId%3d10001%26catalogId%3d10051-1,2,-94,-115,1,32,32,0,0,0,0,519,0,1588176998241,10,16989,0,0,2831,0,0,520,0,0,7E948E122F3A5313B9F6048D6A7D3D12~-1~YAAQ0y3+pYa5TZJxAQAA/M+5xgMBW37CAXEAucVbyyidexLAJvQPqA6owChlkNnYgdmBmoYn/hrPZwi+wS4wFhHbopC0hQ2hwAJsm09Gs8TDjTlFRLEOZ9/PfjtUWf/cUbNbLT8wREvYdH2fUOPPaybI8Dj4VdM9sj/M86WhLUWPwT8lw78RisdSQHsuYJqzXniy9JQQqtL3nSSIwSroa/ClMn6HJb8P9VGaNt5SyzccdUjLSDog8JafpOqU1MWx3wNgiu9yVylaQXkP/B7pe2lTtYBdI9AWysofdZzKBDLpseMk7EabV99Da2HUn9sYeBbK2oq9fKYD~-1~-1~-1,31582,217,1005878714,30261693-1,2,-94,-106,9,1-1,2,-94,-119,40,42,42,42,67,61,37,9,8,6,7,7,11,346,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,5584-1,2,-94,-116,314920738-1,2,-94,-118,108086-1,2,-94,-121,;3;8;0",
            // 7
            "7a74G7m23Vrp0o5c9251581.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,399432,7474202,1382,784,1382,864,1382,301,1396,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:124.19999694824219,vib:1,bat:0,x11:0,x12:1,6018,0.453049826226,811698737101,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,551,0,1623397474202,9,17366,0,0,2894,0,0,551,0,0,D1BF2BBE3DE74AF6D202D3B536A3AFC4~0~YAAQnicwF+cGk/Z5AQAAjaAH+gavsCov0IzaYgTupEH5CvE4bAVf+wqbM97vSOgzRlthZSswXGOVvdx1wdmxwi33fSAgdxjBF8bLod1y8rzgrL24C1npZf8xp0QHOCbnUGLDB9Usdee7vtTB/4sWPFv4wTUPu4sHeJfrzuONgqXQH2pqk9FuLuslujqlOkj0TT8iBC6Fy6SDodMMTLCr69QPP6NwkwoE9PRetWVfWlDPiaBaHEhU9obDIrWIwq/YlQ7Z0z+BPQf9LVgaf5e+H9BzBon/IeA44GTNymI0V+D0lEYa3GlcyS4nu+msdRch8GL0R50+6dp1ZmwBj4tIxuKlX5PbgO2pzzrHI9ymHR9U4/4NgVIS2lskAw18gGwPRyoynSHdOxO0xorOYSipXgRr2Lp9xJkrAMc=~-1~||1-zNuJPmvgRo-1-10-1000-2||~1623400997,41709,702,-920879085,26067385,PiZtE,51219,70-1,2,-94,-106,8,1-1,2,-94,-119,0,0,0,0,0,0,0,0,0,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.05e7a668734268,0.08873ba5bae02,0.13884691378c38,0.7fc97d26f6a6,0.95da9bfb36373,0.6078d3079fbf58,0.982afbecfec2e,0.2e3db28ebabea8,0.fd7a71185c1cc,0.3d9eb9ac12ec7;1,1,0,0,0,1,0,1,0,1;0,3,0,1,0,8,0,9,12,3;D1BF2BBE3DE74AF6D202D3B536A3AFC4,1623397474202,zNuJPmvgRo,D1BF2BBE3DE74AF6D202D3B536A3AFC41623397474202zNuJPmvgRo,1,1,0.05e7a668734268,D1BF2BBE3DE74AF6D202D3B536A3AFC41623397474202zNuJPmvgRo10.05e7a668734268,223,22,99,60,14,173,22,127,92,191,2,30,77,181,211,143,108,218,57,233,244,33,49,136,10,164,125,2,181,60,197,123,475,0,1623397474753;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,-1498601298;1464348676;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5397-1,2,-94,-116,112112916-1,2,-94,-118,130785-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2.2222222222222223,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;3;6;0",
            // 8
            "7a74G7m23Vrp0o5c9251581.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,399432,4979582,1382,784,1382,864,1382,301,1396,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:124.19999694824219,vib:1,bat:0,x11:0,x12:1,6018,0.208375666104,811697489791,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,747,0,1623394979582,5,17366,0,0,2894,0,0,747,0,0,C4F15C3ECC196ED935A96DFDFD9A8D5C~0~YAAQd6w4F6I7I+Z5AQAA5Y/h+QZiHBvjIgqdqhKX4rZ6mxW2OmxkZWsOujc/Rlmx/RBkh0yin1QZ+D3gaH6I4F3JbjOPv7IdZ2KAPK7p/JJiN4zkdluN13nzdSY+Cly6IZTWy+mKXcT5JDpHdn7GHguokdm6w90hUYfd8CKCvze91zQsBJyFJ100ZA8NGtrB+hsY+tZhGdYBnuhD4I6cVkWa5IkB9PLDcaCRxK4ow2cUtsaWrTQPfY3qsR4hfSvqGY1TXoJ2hLwQDIkS4DZgZQIql2+YhpWbUKril0W/NJ0ZeTFxEhF61XF79h5hlZDzO5qPck6WdWsx6y/3YNUuBcm1faSOndLcqtx/OAsy1moJMrxuB8X3zF7YKEj6dFLrWI5kFRoDw+8+zB2vSvaKBOpl+z2TLDRUcyk=~-1~||1-bggqOjiBsl-1-10-1000-2||~1623398510,40897,573,-1415202050,25543097,PiZtE,31675,30-1,2,-94,-106,8,1-1,2,-94,-119,0,0,0,0,0,0,0,0,0,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.a73e1ad6afd42,0.cc8dcca1e6f4b,0.cbfff5c7839f9,0.69da8108c8c6,0.3d0b555e43dd98,0.c629f754d400a,0.37e5e9d9b1d79,0.ea06b0eec00e28,0.d89120c1a1367,0.d1cc0d244178a;1,0,0,0,0,0,1,0,0,1;0,0,1,2,3,1,14,1,0,6;C4F15C3ECC196ED935A96DFDFD9A8D5C,1623394979582,bggqOjiBsl,C4F15C3ECC196ED935A96DFDFD9A8D5C1623394979582bggqOjiBsl,1,1,0.a73e1ad6afd42,C4F15C3ECC196ED935A96DFDFD9A8D5C1623394979582bggqOjiBsl10.a73e1ad6afd42,214,84,169,97,18,48,86,203,121,174,104,200,179,136,16,232,69,191,234,68,72,4,46,222,154,98,155,140,228,65,67,209,503,0,1623394980329;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,-1498601298;1464348676;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5397-1,2,-94,-116,134448657-1,2,-94,-118,130738-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2.2222222222222223,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;2;19;0",
            // 9
            "7a74G7m23Vrp0o5c9160361.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,391352,5978680,1536,880,1536,960,1536,447,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8975,0.993324137496,795277989339.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1369,864,0;1,2,0,0,1388,883,0;-1,2,-94,-102,0,-1,0,0,1369,864,0;1,2,0,0,1388,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?TYPE=100663296&REALMOID=06-000a9084-7aa0-1daa-b12a-11140a0cf0c1&SMAUTHREASON=0&METHOD=GET&SMAGENTNAME=-SM-staples%2ecom--nginx&TARGET=-SM-https%3a%2f%2fwww%2estaples%2ecom%2fidm%2fapi%2fidentityProxy%2fdotcompostlogin%3flangId%3d--1%26userRedirect%3dtrue%26storeId%3d10001%26catalogId%3d10051-1,2,-94,-115,1,32,32,0,0,0,0,538,0,1590555978679,28,17015,0,0,2835,0,0,539,0,0,9285E42F36E9346F0428434EDD3E9101~-1~YAAQxQcPF0ca11JyAQAAbiyGVAOMvCYVuUZPlI2CNt/NmssgQZoQH7wHnGUNEiJH5QHFtoR5eh/0S+/q0THctMBtz2J1xFBH4cUPbVXu1nHzTqXlUSi3oAzwbH7/PjYdzEXvhYAeLsLqDCOFRX3mwnL7zOWhbJ0B5U4dG9+6i+e8cjjlUfEyX58VTnfIV5iEaIVoUYX153t2j37V5pqoeWr+fowFtBJfMF0eIa3gppW/W8At43QclE1BXOI4AxlRYOsNqG3XcZj7SN/ZCNT09A308Bs/OPP+3ImEIheC2Dfm/ZSkTsXJmt5iT4bBFH8RYlresZ0KQVk7~-1~-1~-1,30320,445,-1440495522,30261693-1,2,-94,-106,9,1-1,2,-94,-119,41,42,44,42,264,57,37,8,7,6,6,6,11,375,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,5584-1,2,-94,-116,484273009-1,2,-94,-118,106933-1,2,-94,-121,;3;9;0",
            // 10
            "7a74G7m23Vrp0o5c9251581.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399432,7180812,1536,871,1536,960,1536,486,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8930,0.514609887257,811698590406,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-102,0,-1,0,1,864,864,0;1,-1,0,1,883,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051-1,2,-94,-115,1,32,32,0,0,0,0,505,0,1623397180812,15,17366,0,0,2894,0,0,521,0,0,B42BFF586960500976507E3EDF337DB8~0~YAAQqp0ZuMzPBqh5AQAAbyYD+gZUgJgyJ5Vg2Gt6Z+00HOHrMerBeBNgOpTeuFQKARXqzKUhkLL2RQDoEnwzFS3z754B7EHD/+XQV5Qfjr15j/Njnz631I3PjTeHKZ9Gt3evUan0qccBLgNJlBffM3tBiOS6HHWVgRJEWXI3f/Wp03xtUHhIySbTJJfaV1LkuLt8aCcLg6F6sa8xeoxErIe+TRukSCeiuaUY9faucJVZGQFC0k0WsF1i8ADJP1MNLa/UEErx28syv0GO/s4IfxRNiN8uUxVNpJXDnT6lXiM8pq/FFF8VSKkYeekVE9CPqZFJ3EAndVVLAbOGdq2+m0mcHnlAcCP1zy1HOkgZg7B4XyaaaRveRiIWQK0bpEM3N7HEKbAvjdZgfhmbvDCqSQ/VcsdqiCKRDg==~-1~||1-bsxQRemiRk-1-10-1000-2||~1623400739,40597,644,1278615509,30261693,PiZtE,46401,48-1,2,-94,-106,8,1-1,2,-94,-119,40,40,40,40,60,60,40,20,40,0,0,0,20,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.77117e13d1bb7,0.5923b9e5ba6f2,0.e9fd37810ee18,0.658f3ed86750e,0.d261937f523a3,0.e94ee2a5032d5,0.1b825de9e9bbf,0.dc77ef460d7b1,0.d2eb5baefadf9,0.60423bfc67aeb;1,0,1,3,1,0,0,8,3,6;0,1,2,6,6,1,1,35,8,32;B42BFF586960500976507E3EDF337DB8,1623397180812,bsxQRemiRk,B42BFF586960500976507E3EDF337DB81623397180812bsxQRemiRk,1,1,0.77117e13d1bb7,B42BFF586960500976507E3EDF337DB81623397180812bsxQRemiRk10.77117e13d1bb7,103,151,122,179,32,27,49,142,125,201,222,199,203,115,176,144,75,224,5,203,23,36,126,43,205,83,137,206,208,147,235,215,430,0,1623397181317;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,1744937073-1,2,-94,-118,132368-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;29;12;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $this->sendSensorData($sensorPostUrl, $sensorData[$key]);

        $this->sendSensorData($sensorPostUrl, $secondSensorData[$key]);
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            /*
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);
            */

            $selenium->setProxyGoProxies();

            $selenium->useFirefoxPlaywright();
//            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->http->removeCookies();
            $selenium->keepCookies(false);
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www.staples.com/idm/com/login?langId=-1&userRedirect=true&storeId=10001&catalogId=10051");

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "username" or @id = "loginUsername"]'), 5);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password" or @id = "loginPassword"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//*[@id = "loginBtn"]'), 0);

            if (empty($loginInput) || empty($button)) {
                $this->savePageToLogs($selenium);
                $selenium->driver->executeScript("let login = document.querySelector('input[id = \"username\"], input[id = \"loginUsername\"]'); if (login) login.style.zIndex = '100003';");
                $selenium->driver->executeScript("let pass = document.querySelector('input[id = \"password\"], input[id = \"loginPassword\"]'); if (pass) pass.style.zIndex = '100003';");
                $selenium->driver->executeScript("let loginBtn = document.querySelector('#loginBtn'); if (loginBtn) loginBtn.style.zIndex = '100003';");
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "username" or @id = "loginUsername"]'), 10);
                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password" or @id = "loginPassword"]'), 0);
                $button = $selenium->waitForElement(WebDriverBy::xpath('//*[@id = "loginBtn"]'), 0);
            }

            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return $this->checkErrors();
            }

            $this->closeModal($selenium);

//            $loginInput->sendKeys($this->AccountFields['Login']);
            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 5);
//            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 5);

            $selenium->driver->executeScript('
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/logincommon/g.exec(url)) {
                            localStorage.setItem("responseData", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
            ');
            $button->click();

            sleep(3);

            $res = $selenium->waitForElement(WebDriverBy::xpath('
                //div[contains(@class, "rewards-menu-dropdown__rewardsMenuContainer")]
                | //img[@id = "nucaptcha-media"]
                | //div[@id = "media-container"]/canvas
                | //button[@id = "closeIconContainer"]
            '), 10);
            $this->savePageToLogs($selenium);

            $this->closeModal($selenium);

            $captcha = null;

            if ($res) {
                sleep(3);
                $captcha = $selenium->waitForElement(WebDriverBy::xpath('//img[@id = "nucaptcha-media"] | //div[@id = "media-container"]/canvas'), 3);
                $this->savePageToLogs($selenium);
            }

            if ($captcha) {
                $captcha = $this->parseCaptcha($selenium);

                if ($captcha === false) {
                    return $this->checkErrors();
                }

                $this->logger->debug("Input captcha");
                $captchaInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'nucaptcha-answer']"), 10);

                $this->closeModal($selenium);

                if ($captchaInput) {
                    $captchaInput->clear();
                    $captchaInput->sendKeys($captcha);
                } else {
                    $this->logger->error("captcha input not found");
                }

                $this->savePageToLogs($selenium);
                $this->logger->debug("Posting form");
                $this->closeModal($selenium);
//                $button->click();
                $selenium->driver->executeScript('
                    document.getElementById(\'loginBtn\').click();
                ');

                $selenium->waitForElement(WebDriverBy::xpath('
                    //div[contains(@class, "rewards-menu-dropdown__rewardsMenuContainer")]
                '), 10);
                $this->savePageToLogs($selenium);
                $this->closeModal($selenium);
            }

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);

            // AccountID: 4880366, 5349902, 4363354
            if (empty($responseData)) {
                $selenium->http->GetURL("https://www.staples.com");

                $menu = $selenium->waitForElement(WebDriverBy::xpath('//div[@aria-label="Account Menu"]'), 5);
                $this->savePageToLogs($selenium);

                if ($menu) {
                    $menu->click();
                }

                $selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Sign In") and contains(text(), "Sign in")]'), 5);
                $this->savePageToLogs($selenium);

                $selenium->driver->executeScript('document.querySelector(\'#top_right_menu_item_6 span.headlinerText\').click()');
                $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Sign in"]'), 5);
                $this->savePageToLogs($selenium);
                $selenium->driver->executeScript('try { document.querySelector(\'button.noBlueOutlineOnFocus, button[aria-label="Sign in"]\').click() } catch(e) {}');

                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "username" or @id = "loginUsername"]'), 10);
                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password" or @id = "loginPassword"]'), 0);
                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "loginBtn"]'), 5);

                if (empty($loginInput) || empty($button)) {
                    $this->savePageToLogs($selenium);
                    $selenium->driver->executeScript("let login = document.querySelector('input[id = \"username\"], input[id = \"loginUsername\"]'); if (login) login.style.zIndex = '100003';");
                    $selenium->driver->executeScript("let pass = document.querySelector('input[id = \"password\"], input[id = \"loginPassword\"]'); if (pass) pass.style.zIndex = '100003';");
                    $selenium->driver->executeScript("let loginBtn = document.querySelector('#loginBtn'); if (loginBtn) loginBtn.style.zIndex = '100003';");
                    $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "username" or @id = "loginUsername"]'), 10);
                    $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password" or @id = "loginPassword"]'), 0);
                    $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "loginBtn"]'), 0);
                }

                // save page to logs
                $this->savePageToLogs($selenium);

                if (!$loginInput || !$passwordInput || !$button) {
                    return $this->checkErrors();
                }

                $this->logger->info("set credentials");
                $loginInput->sendKeys($this->AccountFields['Login']);
                $passwordInput->sendKeys($this->AccountFields['Pass']);
                $this->logger->info("js injecion");
                $selenium->driver->executeScript('
                    let oldXHROpen = window.XMLHttpRequest.prototype.open;
                    window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                        this.addEventListener("load", function() {
                            if (/logincommon/g.exec(url)) {
                                localStorage.setItem("responseData", this.responseText);
                            }
                        });
                        return oldXHROpen.apply(this, arguments);
                    };
                    
                    document.getElementById(\'loginBtn\').click();
                ');

                sleep(7);
                $this->savePageToLogs($selenium);

                if ($selenium->waitForElement(WebDriverBy::xpath('//img[@id = "nucaptcha-media"] | //div[@id = "media-container"]/canvas'), 0)) {
                    $captcha = $this->parseCaptcha($selenium);

                    if ($captcha === false) {
                        return $this->checkErrors();
                    }

                    $this->logger->debug("Input captcha");
                    $captchaInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'nucaptcha-answer']"), 10);

                    $this->closeModal($selenium);

                    if ($captchaInput) {
                        $captchaInput->clear();
                        $captchaInput->sendKeys($captcha);
                    } else {
                        $this->logger->error("captcha input not found");
                    }

                    $this->savePageToLogs($selenium);
                    $this->logger->debug("Posting form");
                    $this->closeModal($selenium);
    //                $button->click();
                    $selenium->driver->executeScript('
                        document.getElementById(\'loginBtn\').click();
                    ');

                    sleep(15);
                    $this->savePageToLogs($selenium);
                }

                $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
                $this->logger->info("[Form responseData]: " . $responseData);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            if (!empty($responseData) && !strstr($responseData, 'Access Denied')) {
                $this->http->SetBody($responseData);

                return true;
            }
        } catch (UnknownServerException | NoSuchDriverException | SessionNotCreatedException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    private function closeModal($selenium)
    {
        $this->logger->notice(__METHOD__);
        $selenium->driver->executeScript("try { var overlay = document.querySelector(\"#dialogContainer\"); if (overlay) overlay.style.display = \"none\"; } catch (e) {}");
        $selenium->driver->executeScript("try { var overlay2 = document.querySelector(\"#attentive_overlay\"); if (overlay2) overlay2.style.display = \"none\"; } catch (e) {}");
        $this->savePageToLogs($selenium);
    }
}
