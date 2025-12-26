<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerIchotelsgroup extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public const CONFIG_API_KEY = 'se9ym5iAzaW8pxfBjkmgbuGjJcr3Pj6Y';

    private const LOGIN_URL = 'https://www.ihg.com/rewardsclub/us/en/sign-in/?fwdest=/rewardsclub/us/en/account-mgmt/home';

    private $logout_xpath = "//div[@class = 'logIn']//div[not(contains(@style, 'display: none;'))]/a[contains(text(), 'Sign Out')]";
    private $number_xpath = '//span[@data-slnm-ihg="memberNumberSID"]';

    private $curl = false;
    private $seleniumUrl = null;

    /* https://redmine.awardwallet.com/issues/17359#note-10
    private $apikey = '3_XupfLzsZ9JEyW3sT_JgJfVPxxmRpNgVup9sFJncoXoFC7VmX7wGjlgXPSckRrpud';
    */
    private $apikey = '4_jpzahMO4CBnl9Elopzfr0A';
    private $sdk = "js_latest";
    private $context = 'R2649647561';

    private $headers = [];

    private $currentItin = 0;
    private $rulesToken = null;
    private $lastName;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setKeepUserAgent(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.ihg.com/rewardsclub/us/en/account-mgmt/home", [], 20);
        $this->http->RetryCount = 2;

        if (!$this->http->getCookieByName("X-IHG-SSO-TOKEN", ".ihg.com")) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $this->delay();

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if ($this->attempt > 0 && rand(0, 1)) {
            $this->context = 'R3648154460';
        }

        if ($this->flexibleProxy() || $this->attempt == 2) {
            $this->setProxyGoProxies(null, "us", null, null, self::LOGIN_URL);
        } else {
            $this->logger->notice("no Proxy");
        }

        if (strstr($this->AccountFields['Login'], 'onerror=alert(1)')) {
            throw new CheckException("Your PIN must be 4-digits in length.", ACCOUNT_INVALID_PASSWORD);
        }

        if ((filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false)) {
            $this->logger->debug("non email");
        }

        if (
            $this->attempt == 2
        ) {
            $this->curl = true;
        }

        // retries
        if (
            isset($this->http->Response['code'])
            && $this->http->Response['code'] == 403
            && $this->http->currentUrl() == 'https://www.ihg.com/rewardsclub/us/en/sign-in/?fwdest=https://www.ihg.com/rewardsclub/us/en/account/home'
            && !$this->http->ParseForm("gigya-login-form")) {
            throw new CheckRetryNeededException(2, 10);
        }

        $this->http->RetryCount = 1;
        $this->http->GetURL('https://www.ihg.com/rewardsclub/us/en/sign-in/?fwdest=/rewardsclub/us/en/account-mgmt/home');
        $this->http->RetryCount = 2;
        $this->http->setCookie("gig_bootstrap_{$this->apikey}", 'identity-us_ver3', '.ihg.com');

        if ($this->http->Response['code'] !== 200) {
            if (
                $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
                && $this->http->Response['code'] == 403
            ) {
                $this->flexibleProxy(true);
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(4, 5);
            }

            return $this->checkErrors();
        }

        $this->delay();

        $this->http->GetURL("https://identity-us.ihg.com/accounts.getScreenSets?screenSetIDs=IHG-Login&include=html%2Ccss%2Cjavascript%2Ctranslations%2C&lang=en&APIKey={$this->apikey}&source=showScreenSet&sdk={$this->sdk}&pageURL=https%3A%2F%2Fwww.ihg.com%2Frewardsclub%2Fus%2Fen%2Fsign-in%3Ffwdest%3D%252Frewardsclub%252Fus%252Fen%252Faccount-mgmt%252Fhome&format=jsonp&callback=gigya.callback&context={$this->context}");

        if (!$this->http->FindPreg("/form class=.\"gigya-login-form/")) {
            $response = $this->http->JsonLog($this->http->FindPreg("/gigya\.callback\(([\w\W]+)\);\s*$/ims"), 0);
            $message = $response->errorDetails ?? null;
            $this->logger->error($message);
            $this->retriesApiIssue($message);

            if (
                $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
                && $this->http->Response['code'] == 403
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(4, 5);
            }

            if (trim($this->http->Error) == 'Network error 0 -') {
                throw new CheckRetryNeededException(4, 5);
            }

            return $this->checkErrors();
        }
        // for cookies
        $this->http->GetURL("https://identity-us.ihg.com/accounts.webSdkBootstrap?apiKey={$this->apikey}&pageURL=https%3A%2F%2Fwww.ihg.com%2Frewardsclub%2Fus%2Fen%2Fsign-in%3Ffwdest%3D%252Frewardsclub%252Fus%252Fen%252Faccount-mgmt%252Fhome&format=jsonp&callback=gigya.callback&context={$this->context}");
        $this->http->JsonLog($this->http->FindPreg("/gigya\.callback\(([\w\W]+)\);\s*$/ims"), 0);

        $this->delay();

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://identity-us.ihg.com/accounts.login?context={$this->context}&&saveResponseID={$this->context}", [
            'loginID'           => $this->AccountFields['Login'],
            'password'          => $this->AccountFields['Pass'],
            'sessionExpiration' => '0',
            'targetEnv'         => 'jssdk',
            'include'           => 'profile,data,emails,subscriptions,preferences,',
            'includeUserInfo'   => 'true',
            'loginMode'         => 'standard',
            'lang'              => 'en',
            'APIKey'            => $this->apikey,
            'source'            => 'showScreenSet',
            'sdk'               => $this->sdk,
            'authMode'          => 'cookie',
            'pageURL'           => 'https://www.ihg.com/rewardsclub/us/en/sign-in?fwdest=%2Frewardsclub%2Fus%2Fen%2Faccount-mgmt%2Fhome',
            'format'            => 'jsonp',
            'callback'          => 'gigya.callback',
            'context'           => $this->context,
            'utf8'              => '✓', //&#x2713;
        ]);
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] == 403) {
//            $this->selenium();

            $this->http->RetryCount = 0;
            $this->http->PostURL("https://identity-us.ihg.com/accounts.login?context={$this->context}&&saveResponseID={$this->context}", [
                'loginID'           => $this->AccountFields['Login'],
                'password'          => $this->AccountFields['Pass'],
                'sessionExpiration' => '0',
                'targetEnv'         => 'jssdk',
                'include'           => 'profile,data,emails,subscriptions,preferences,',
                'includeUserInfo'   => 'true',
                'loginMode'         => 'standard',
                'lang'              => 'en',
                'APIKey'            => $this->apikey,
                'source'            => 'showScreenSet',
                'sdk'               => $this->sdk,
                'authMode'          => 'cookie',
                'pageURL'           => 'https://www.ihg.com/rewardsclub/us/en/sign-in?fwdest=%2Frewardsclub%2Fus%2Fen%2Faccount-mgmt%2Fhome',
                'format'            => 'jsonp',
                'callback'          => 'gigya.callback',
                'context'           => $this->context,
                'utf8'              => '✓', //&#x2713;
            ]);
            $this->http->RetryCount = 2;

            $key = 100;

            if ($this->http->Response['code'] == 403) {
                $this->sendStatistic(false, false, $key);
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 5);
            } else {
                $this->sendStatistic(true, false, $key);
            }
        }

        return true;
    }

    public function Login()
    {
        // AccountID: 756284
        $response = $this->http->JsonLog($this->http->FindPreg("/gigya\.callback\(([\w\W]+)\);\s*$/ims"), 0);

        if (isset($response->errorMessage) && $response->errorMessage == 'Operation not allowed') {
            throw new CheckException($response->errorMessage, ACCOUNT_PROVIDER_ERROR);
        }

        $data = http_build_query([
            'APIKey'         => $this->apikey,
            'callback'       => "gigya.callback",
            'context'        => $this->context,
            'format'         => 'jsonp',
            'noAuth'         => 'true',
            'saveResponseID' => $this->context,
            'sdk'            => $this->sdk,
        ]);
        $this->http->GetURL("https://identity-us.ihg.com/socialize.getSavedResponse?{$data}");
        $response = $this->http->JsonLog($this->http->FindPreg("/gigya\.callback\(([\w\W]+)\);\s*$/ims"), 0);

        if (isset($response->sessionInfo->login_token)) {
            $login_token = $response->sessionInfo->login_token;
            $this->http->RetryCount = 0;
            $this->http->setCookie("glt_{$this->apikey}", $response->sessionInfo->login_token, '.ihg.com');

            $this->delay();

            $this->logger->notice("get first token");
            $headers = [
                "Accept"          => "*",
                "Accept-Encoding" => "gzip, deflate, br",
            ];
            $query = http_build_query([
                'fields'      => "firstName,data.rcMembershipNumber,data.memberKey",
                'expiration'  => 900,
                'APIKey'      => $this->apikey,
                'sdk'         => $this->sdk,
                'login_token' => $login_token,
                'authMode'    => 'cookie',
                'pageURL'     => 'https://www.ihg.com/rewardsclub/us/en/sign-in?fwdest=%2Frewardsclub%2Fus%2Fen%2Faccount-mgmt%2Fhome',
                'format'      => 'jsonp',
                'callback'    => "gigya.callback",
                'context'     => $this->context,
            ]);
            $this->http->GetURL("https://identity-us.ihg.com/accounts.getJWT?" . $query, $headers);
            $response = $this->http->JsonLog($this->http->FindPreg("/gigya\.callback\(([\w\W]+)\);\s*$/ims"));

            if (!isset($response->id_token)) {
                $this->logger->notice('id_token not found');

                $message = $response->errorDetails ?? null;
                $this->logger->error($message);

                if ($message == 'Unauthorized user') {
                    throw new CheckRetryNeededException(3);
                }

                $this->retriesApiIssue($message);

                return false;
            }
            $this->http->RetryCount = 2;
            $this->logger->notice("create cookie");
            $this->http->setCookie("X-IHG-SSO-TOKEN", $response->id_token, ".ihg.com");

            $result = $this->loginSuccessful();

            if (!$result) {
                $response = $this->http->JsonLog(null, 0);
                $message = $response->message ?? null;

                if (in_array($message, [
                    "Member is closed or removed",
                    "Guest Service failed while processing request",
                    "Validation failed while processing request",
                ])
                    || ($this->http->Response['code'] == 503 && $this->http->FindSingleNode('//title[contains(text(), "503 Service Unavailabl")]'))
                ) {
                    throw new CheckException("Sorry, please try again. If the problem persists, please contact Customer Care for assistance.", ACCOUNT_PROVIDER_ERROR);
                }

                if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry, the page you are looking for is currently unavailable")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                // provider problems after msintenance
                if ($this->http->Response['code'] == 502 && $this->http->FindSingleNode('//title[contains(text(), "502 Proxy Error") or contains(text(), "502 Bad Gateway")]')) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;
            }// if (!$result)

            return $result;
        } elseif (isset($response->errorDetails)) {
            $message = $response->errorDetails ?? null;
            $this->logger->error("[errorDetails]: {$message}");

            // The login credentials you entered are invalid. Please reset your password or contact Customer Care.
            if (
                $message == 'invalid loginID or password'
                || $message == 'Login has been denied'
            ) {
                throw new CheckException("The login credentials you entered are invalid. Please reset your password or contact Customer Care.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Old Password Used') {
                throw new CheckException("The login credentials you entered do not match any in our system. Please check your email or member number and PIN/Password and try again. If you're still having trouble you can reset your password or contact Customer Care for assistance.", ACCOUNT_INVALID_PASSWORD);
            }

            // Please accept Terms and Conditions
            if ($message == 'Registration was not finalized') {
                $this->throwAcceptTermsMessageException();
            }

            if ($message == 'Pending Password Change') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Network partitioning error') {
                throw new CheckException("Forwarding error", ACCOUNT_PROVIDER_ERROR);
            }

            // Your account is temporarily locked. Please wait 30 minutes before trying again or contact Customer Care.
            if ($message == 'Account temporarily locked out') {
                throw new CheckException("Your account is temporarily locked. Please wait 30 minutes before trying again or contact Customer Care.", ACCOUNT_LOCKOUT);
            }
            // We're sorry, your account is disabled. Please contact Customer Care for assistance.
            if ($message == 'Account Disabled') {
                throw new CheckException("We're sorry, your account is disabled. Please contact Customer Care for assistance.", ACCOUNT_PROVIDER_ERROR);
            }

            // Please accept Terms and Conditions
            if (
                $message == 'Missing required fields for registration: preferences.terms.digitalTS.isConsentGranted, preferences.privacy.digitalPP.isConsentGranted'
                || $message == 'Missing required fields for registration: country'
                || $message == 'Missing required fields for registration: preferences.terms.digitalTS.isConsentGranted'
            ) {
                $this->throwAcceptTermsMessageException();
            }

            if ($message == 'Invalid argument: saveResponseID') {
                throw new CheckException("There are errors in your form, please try again", ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            $this->retriesApiIssue($message);
        }

        if (isset($response->errorMessage) && $response->errorMessage == 'General Server Error') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->FindPreg('/Yes! Please activate the Hotel Bills feature for my accoun/ims')) {
            return;
        }

        $headers = [
            "X-IHG-API-KEY"   => self::CONFIG_API_KEY,
            "X-IHG-SSO-TOKEN" => $this->http->getCookieByName("X-IHG-SSO-TOKEN", ".ihg.com"),
            "Accept"          => "application/json, text/javascript, */*; q=0.01",
        ];
        $response = $this->http->JsonLog(null, 0);

        if (isset($response->message)) {
            // isLoggedIn workaround
            if ($this->attempt == 0 && $response->message == 'Not authorized') {
                throw new CheckRetryNeededException(2, 0);
            }
            // AccountID: 4877260
            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $response->message == 'Member is closed or removed') {
                throw new CheckException('Sorry, please try again. If the problem persists, please contact Customer Care for assistance.', ACCOUNT_PROVIDER_ERROR);
            }
        }// if (isset($response->message))

        if (isset($response->programs)) {
            foreach ($response->programs as $program) {
                if (isset($program->currentPointsBalance, $program->yearToDateSummary->earnedPoints, $program->membershipKey,
                            $program->yearToDateSummary->eliteQualifyingEarnedPoints)
                    && $program->programCode == 'PC'
                ) {
                    // Name
                    $walletProfile = $response->name ?? null;
                    $this->SetProperty("Name", beautifulName(($walletProfile->firstName ?? null) . " " . ($walletProfile->lastName ?? null)));
                    // Current Points Balance
                    $this->SetBalance($program->currentPointsBalance ?? null);
                    // Member #
                    $this->SetProperty("Number", $memberNumber = $response->rewardsClubMemberNumber ?? null);
                    // Membership Level
                    $this->SetProperty("Level", $program->levelDescription ?? null);
                    // Status Expiration Date
                    $this->SetProperty("LevelExpiry", $program->levelExpirationDate ?? null);

                    // Expiration Date  // refs #10587
                    $exp = $program->currentPointsBalanceExpirationDate ?? null;

                    if (!empty($exp) && strtotime($exp)) {
                        $this->SetExpirationDate(strtotime($exp));
                    }
                    // refs #12688, 14782, 19918
                    elseif (isset($this->Properties['Level']) && strlen($this->Properties['Level']) > 3 && !stristr($this->Properties['Level'], 'Club')) {
                        $this->SetExpirationDateNever();
                        $this->ClearExpirationDate();
                        $this->SetProperty("AccountExpirationWarning", "do not expire with elite status");
                    }// elseif (isset($this->Properties['Level']) && strlen($this->Properties['Level']) > 4 && !stristr($this->Properties['Level'], 'Club'))

                    // Total Points Earned
                    $this->SetProperty("TotalPointsEarned", $program->yearToDateSummary->earnedPoints);
                    // Qualifying Points
                    $this->SetProperty("PointsEarned", $program->yearToDateSummary->eliteQualifyingEarnedPoints ?? null);
                    // Qualifying Nights
                    $this->SetProperty("QualifyingNights", $program->yearToDateSummary->qualifyingNights ?? null);
                    // Reward Nights Redeemed
                    $this->SetProperty("RewardNights", $program->yearToDateSummary->rewardNights ?? null);
                    // ... Elite Rollover Nights (Last Year Elite Rollover Nights)
                    $this->SetProperty("LastYearEliteRolloverNights", $program->yearToDateSummary->rolloverNights ?? null);
                    // Earnings Preference
                    $this->SetProperty("EarningsPreference", beautifulName($program->earningType ?? null));

                    // Member since
                    $this->SetProperty("MemberSince", $program->enrollmentDate ?? null);
                }

                if (isset(
                        $program->programCode,
                        $program->levelCode,
                        $program->statusCode,
                        $program->levelDescription,
                        $program->membershipKey
                    )
                    && $program->programCode == 'AMB'
                    && $program->levelCode == 'AMB'
                    && $program->statusCode == 'O'
                    && $program->levelDescription == 'AMBASSADOR'
                    && (!property_exists($program, 'statusReason') || $program->statusReason != 'R')
                ) {
                    $ambassadorMembershipKey = $program->membershipKey;
                }
            }// foreach ($response->programs as $program)
        }// if (isset($response->programs))
        // Name
        if (isset($response->nameOnCard)) {
            $this->SetProperty("Name", beautifulName($response->nameOnCard));
        }

        // refs #19944
        $this->logger->info('Zip Code', ['Header' => 3]);
        $this->logger->debug('ZipCodeParseDate: ' . ($this->State['ZipCodeParseDate'] ?? null));
        $this->logger->debug('Time: ' . strtotime("-1 month"));

        if (
            !isset($this->State['ZipCodeParseDate'])
            || $this->State['ZipCodeParseDate'] < strtotime("-1 month")
        ) {
            $zip = $response->preferredAddress->postalCode ?? null;
            $country = $response->preferredAddress->countryCode ?? null;

            if ($country == 'US' && strlen($zip) == 9) {
                $zipCode = substr($zip, 0, 5) . " " . substr($zip, 5);
            } else {
                $zipCode = $zip;
            }
            $this->SetProperty("ZipCode", $zipCode);
            $street = $response->preferredAddress->line1 ?? null;

            if (isset($response->preferredAddress->line2)) {
                $street .= ", " . $response->preferredAddress->line2 ?? null;
            }

            if (isset($response->preferredAddress->line3)) {
                $street .= ", " . $response->preferredAddress->line3 ?? null;
            }
            $region = '';

            if (isset($response->preferredAddress->locality1)) {
                $region = ", " . $response->preferredAddress->locality1;
            }

            if (isset($response->preferredAddress->region1)) {
                $region .= ", " . $response->preferredAddress->region1;
            }

            if ($zipCode && $street) {
                $this->SetProperty("ParsedAddress",
                    $street
                    . $region
                    . ", " . $zipCode
                    . ", " . $country
                );
            }// if ($zipCode)
            $this->State['ZipCodeParseDate'] = time();
        }// if (!isset($this->State['ZipCodeParseDate']) || $this->State['ZipCodeParseDate'] > strtotime("-1 month"))

        // SubAccount - Free nights   // refs #6321

        $this->logger->info("Free nights", ['Header' => 3]);
        $time = date("UB");
        $this->http->GetURL("https://apis.ihg.com/members/v2/profiles/me/registeredOffers?cacheBuster={$time}&offerType=freeNightsOnly", $headers);
        $response = $this->http->JsonLog(null, 3, true, 'freeNights');
        $this->SetProperty("CombineSubAccounts", false);

        if (is_array($response)) {
            $offers = ArrayVal($response, 'offers', []);

            foreach ($offers as $offer) {
                $freeNights = ArrayVal($offer, 'freeNights', []);
                $vouchers = ArrayVal($freeNights, 'freeNightVoucherSummaries', []);
                $summary = ArrayVal($offer, 'summary', []);

                foreach ($vouchers as $voucher) {
                    $status = ArrayVal($voucher, 'status');
                    $bookingEndDate = ArrayVal($voucher, 'bookingEndDate');
                    $bookingStartDate = ArrayVal($voucher, 'bookingStartDate');
                    $unredeemedQuantity = ArrayVal($voucher, 'unredeemedQuantity');

                    if ($status == 'AVAILABLE' && $bookingEndDate && $unredeemedQuantity > 0) {
                        $exp = strtotime($bookingEndDate);

                        if ($exp != false && $exp > time()) {
                            $name = ArrayVal($summary, 'offerDescription') ?: "Chase Anniversary Free Night";
                            $this->AddSubAccount([
                                'Code'           => 'ichotelsgroup' . "FreeNights" . $bookingStartDate . md5($name) . $exp,
                                'DisplayName'    => $name,
                                'Balance'        => $unredeemedQuantity,
                                'ExpirationDate' => $exp,
                            ], true);
                        }// if ($exp != false && $exp > time())
                    }// if ($status == 'AVAILABLE' && $bookingEndDate && $unredeemedQuantity > 0)
                }// foreach ($vouchers as $voucher)
            }// foreach ($offers as $offer)
        }// if (is_array($response))

        if (isset($ambassadorMembershipKey, $memberNumber)) {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://apis.ihg.com/members/v1/memberships/{$ambassadorMembershipKey}/membershipBenefits?cacheBuster={$time}", $headers, 30);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            foreach ($response->membershipBenefits ?? [] as $benefit) {
                $code = $benefit->benefitType->code ?? null;
                $description = $benefit->benefitType->description ?? null;
                $status = $benefit->status ?? null;
                $exp = strtotime($benefit->expirationDate ?? '');

                if ($code == 'FREE WEEKEND NIGHT'
                    && $description == 'Ambassador Weekend Night Certificate'
                    && $status == 'AVAILABLE'
                    && $exp > time()
                ) {
                    $this->AddSubAccount([
                        'Code'           => 'AmbassadorComplimentaryWeekendNight' . $memberNumber,
                        'DisplayName'    => 'Ambassador Complimentary Weekend Night',
                        'Balance'        => null,
                        'ExpirationDate' => $exp,
                    ]);
                }
            }
        }

        // refs #22132
        $this->logger->info("Rewards to enhance your stay", ['Header' => 3]);
        $time = date("UB");
        $headers['X-CDC-API-KEY'] = '4_jpzahMO4CBnl9Elopzfr0A';
        $this->http->GetURL("https://apis.ihg.com/members/benefits/v2/vouchers?voucherStatus=Issued&cacheBuster={$time}", $headers);
        $response = $this->http->JsonLog(null, 3, true, 'name');

        if (is_array($response)) {
            $vouchers = ArrayVal($response, 'vouchers', []);

            foreach ($vouchers as $voucher) {
                $status = ArrayVal(ArrayVal($voucher, 'status'), 'status');

                if ($status == 'Issued') {
                    $id = ArrayVal($voucher, 'id');
                    $expiryDate = ArrayVal(ArrayVal($voucher, 'usage'), 'expiryDate');
                    $name = ArrayVal($voucher, 'name');
                    $exp = strtotime($expiryDate);

                    if ($exp === false || $exp < time()) {
                        continue;
                    }

                    $this->AddSubAccount([
                        'Code'           => 'ichotelsgroupRewards' . $id . $exp,
                        'DisplayName'    => $name,
                        'Balance'        => null,
                        'ExpirationDate' => $exp,
                    ], true);
                }// if ($status == 'Issued')
            }// foreach ($vouchers as $voucher)
        }// if (is_array($response))
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);

        if (empty($this->lastName) || empty($this->Properties['Number'])) {
            $this->logger->error('LastName or Number is empty');

            return [];
        }
        $headers = [
            'Accept'          => 'application/json, text/plain, */*',
            'X-IHG-API-KEY'   => self::CONFIG_API_KEY,
            'X-IHG-SSO-TOKEN' => $this->http->getCookieByName("X-IHG-SSO-TOKEN", ".ihg.com"),
        ];
        $cacheBuster = time() . date("B");
        $time = date("UB");
        $params = http_build_query([
            'cacheBuster'  => $cacheBuster,
            'lastName'     => $this->lastName,
            'limit'        => '100',
            'loyaltyId'    => $this->Properties['Number'],
            'offset'       => '1',
            'retrieveMode' => 'DISPLAY_LIST',
        ]);
        $this->http->GetURL("https://apis.ihg.com/reservations/v2/hotels?{$params}", $headers);
        //$this->http->GetURL("https://apis.ihg.com/reservations/v2/hotels?_cbVar={$cacheBuster}&_={$time}", $headers);
        $res = $this->http->JsonLog();

        if (isset($res->foundReservation)) {
            return $this->ParseItinerariesNew($res);
        }

        return [];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.ihg.com/hotels/us/en/stay-mgmt/ManageYourStay";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $arFields['ConfNo'] = substr($arFields['ConfNo'], 0, 8);
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if ($this->http->Response['code'] != 200) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        if (!$this->getHeaders()) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'X-IHG-API-KEY' => self::CONFIG_API_KEY,
            'ihg-language'  => 'en-US',
            'Content-Type'  => 'application/json; charset=UTF-8',
            'Referer'       => $this->ConfirmationNumberURL($arFields),
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://apis.ihg.com/reservations/v3/hotels/{$arFields['ConfNo']}?lastName={$arFields['LastName']}",
            $headers);
        $this->http->RetryCount = 2;
        $reservation = $this->http->JsonLog();

        if ($this->http->Response['code'] == 404
            && !isset($reservation->hotelReservation)
            && isset($reservation->message)
            && ($reservation->message == 'No reservation available for the input confirmation number.'
                || $reservation->message == 'No reservation available for the input cancellation number.')
        ) {
            return 'We are sorry, our system is temporarily unavailable. Please try again or contact your nearest reservation office for assistance.';
        }

        if ($this->http->Response['code'] == 400
            && !isset($reservation->hotelReservation)
            && isset($reservation->message)
            && $reservation->message === 'The reservation is not accessible via Web or Mobile phone channel.'
        ) {
            return 'Please note that most reservations booked directly with IHG, such as via IHG.com, may be retrieved here. For reservations made through a third party, contact the provider directly for assistance. For more information, you may also contact Customer Care.';
        }

        if (($this->http->Response['code'] == 400 || $this->http->Response['code'] == 403)
            && !isset($reservation->hotelReservation)
            && isset($reservation->message)
            && (
                $reservation->message === 'Last name of reservation is not equals to provided name.'
                || (
                    isset($reservation->errors) && is_array($reservation->errors)
                    && isset($reservation->errors[0]->message)
                    && $reservation->errors[0]->message === 'The number is not Numeric')
            )
        ) {
            return 'We couldn\'t process your request at this time. Please try again later, or contact Customer Care for assistance.';
        }

        if (isset($reservation->hotelReservation, $reservation->hotelReservation->reservationStatus)) {
            if ($reservation->hotelReservation->reservationStatus === 'CANCELLED'
            ) {
                return 'Your reservation has been canceled. Please contact your nearest IHG® Rewards Club Customer Care for assistance.';
            }
            $hotelMnemonic = $reservation->hotelReservation->hotels[0]->hotelMnemonic;
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://apis.ihg.com/hotels/v1/profiles/{$hotelMnemonic}/details?fieldset=brandInfo,profile,address,contact,policies,location,facilities,parking,tax,services,marketing",
                $headers);
            $hotel = $this->http->JsonLog(null, 0);
            $this->http->RetryCount = 2;
            $this->parseReservationJson($reservation, $hotel);

            if (!empty($this->itinerariesMaster->getItineraries())) {
                return null;
            }
        }
        $this->sendNotification('check old retrieve // MI');
        /*// old version...
        $message = $this->ParseItineraryJson($arFields['ConfNo'], $arFields['LastName']);

        if (!empty($message)) {
            return $message;
        }*/

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function GetHistoryColumns()
    {
        return [
            "Activity Type"           => "Info",
            "Date Posted"             => "PostingDate",
            "Description"             => "Description",
            "Total Earned"            => "Miles",
            "Bonus"                   => "Bonus",
            "Elite Qualifying Points" => "Info",
        ];
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->FilterHTML = false;
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $this->http->GetURL('https://www.ihg.com/rewardsclub/us/en/account/home');

        $page = 1;
        //		do {
        $this->logger->debug("Page #{$page}");
        $params = [
            'activityType' => 'all',
            'duration'     => '365',
            'cacheBuster'  => date("UB"),
            'limit'        => '1000',
            'offset'       => $page,
        ];
        $headers = [
            "X-IHG-API-KEY"   => self::CONFIG_API_KEY,
            "X-IHG-SSO-TOKEN" => $this->http->getCookieByName("X-IHG-SSO-TOKEN", ".ihg.com"),
            "Accept"          => "application/json, text/javascript, */*; q=0.01",
        ];
        $this->http->GetURL('https://apis.ihg.com/members/v1/profiles/me/activities?' . http_build_query($params), $headers);
        $response = $this->http->JsonLog(null, 3);
        $page++;
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate, $response));
        //		}
//        while (
//            !empty($response)
//            && !empty($response->totalRecords)
//            && $response->totalRecords != 0
//            && $page < 30
//        );

        $this->getTime($startTimer);

        return $result;
    }

    private function delay()
    {
        $delay = rand(1, 7);
        $this->logger->debug("Delay -> {$delay}");
        sleep($delay);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $ssoToken = $this->http->getCookieByName("X-IHG-SSO-TOKEN", ".ihg.com");
        $this->logger->debug("[X-IHG-SSO-TOKEN]: {$ssoToken}");

        if (!$ssoToken) {
            return false;
        }
        $headers = [
            "X-IHG-API-KEY"   => self::CONFIG_API_KEY,
            "X-IHG-SSO-TOKEN" => $ssoToken,
            "Accept"          => "application/json, text/javascript, */*; q=0.01",
        ];
        $time = date("UB");
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://apis.ihg.com/members/v2/profiles/me?cacheBuster={$time}&fieldset=", $headers, 30);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 4);

        if (
            isset($response->preferredEmail->address, $response->rewardsClubMemberNumber)
            && strtolower($this->AccountFields['Login']) != strtolower($response->preferredEmail->address)
            && $this->AccountFields['Login'] != $response->rewardsClubMemberNumber
            && strtolower($this->AccountFields['Login2']) != strtolower($response->name->lastName)
            && !(strtolower($response->preferredEmail->address) == 'craigchu.charleymills@gmail.com' && strtolower($this->AccountFields['Login']) == 'craigchu22@hotmail.com')// AccountID: 2684073, user with eternal problems
            && !(strtolower($response->preferredEmail->address) == 'maria@heimbecher.com' && strtolower($this->AccountFields['Login']) == 'reed@heimbecher.com')// AccountID: 3108673
        ) {
            $this->logger->error("something went wrong, email addresses mismatch");
            $this->Balance = null;
            $this->ErrorCode = ACCOUNT_ENGINE_ERROR;

            return false;
        }

        if (isset($response->rewardsClubMemberNumber)) {
            $this->lastName = $response->name->lastName ?? null;

            return true;
        }

        return false;
    }

    private function retriesApiIssue($message)
    {
        $this->logger->notice(__METHOD__);

        if ($message == 'Api Rate limit exceeded') {
            $this->flexibleProxy(true);

            throw new CheckRetryNeededException(3, 10);
        }
    }

    private function flexibleProxy($proxy = false)
    {
        $this->logger->notice(__METHOD__);
        $proxyEnable = 'false';

        $cache = Cache::getInstance();
        $cacheKey = "proxy_ichotelsgroup";
        $data = $cache->get($cacheKey);

        if (!empty($data) && $data === 'true') {
            return true;
        }

        if ($this->attempt == 3 && $proxy === true) {
            $proxyEnable = 'true';
        }

        $cache->set($cacheKey, $proxyEnable, 300);

        return $proxyEnable === 'true';
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        /** @var TAccountCheckerIchotelsgroup $selenium */
        $selenium = clone $this;
        $retryProviderError = false;
        $retry = false;
        $firefox = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();

            if (!isset($this->State['Resolution']) || $this->attempt > 1) {
                // british
                $resolutions = [
                    [1152, 864],
                    //                    [1280, 720],
                    //                    [1280, 768],
                    [1280, 800],
                    [1360, 768],
                    [1366, 768],
                    [1920, 1080],
                ];
                $resolution = $resolutions[array_rand($resolutions)];
                $this->logger->notice("set new resolution");
                $resolution = $resolutions[array_rand($resolutions)];
                $this->State['Resolution'] = $resolution;
            } else {
                $this->logger->notice("get resolution from State");
                $resolution = $this->State['Resolution'];
                $this->logger->notice("restored resolution: " . join('x', $resolution));
            }

            $selenium->setScreenResolution($resolution);

            // retries
            if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
                $this->DebugInfo = 'Access Denied: ' . implode("x", $resolution);
                $this->logger->error(">>> Access Denied");
                $retryProviderError = true;

                $this->flexibleProxy(true);
            }// if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]"))
            elseif ($this->http->FindPreg("/An error occurred while processing your request\./")) {
                $this->DebugInfo = 'An error occurred: ' . implode("x", $resolution);
                $this->logger->error(">>> An error occurred");
                $retryProviderError = true;
            }// if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]"))
            elseif (
                $this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]")
                || $this->http->FindPreg('/page isn’t working/ims')
            ) {
                $retryProviderError = true;
            } else {
                $this->DebugInfo = null;
            }

            if (!$retryProviderError) {
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            }
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
            $this->seleniumUrl = $selenium->http->currentUrl();
        } catch (StaleElementReferenceException | UnexpectedJavascriptException | UnknownServerException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->DebugInfo = "Exception";
            // retries
            $retry = true;

            if (
                (
                    strstr($e->getMessage(), 'Reached error page')
                )
            ) {
                $selenium->markProxyAsInvalid();
            }
        } catch (SessionNotCreatedException $e) {
            $this->logger->error("SessionNotCreatedException exception: " . $e->getMessage());
            $this->DebugInfo = "SessionNotCreatedException";
            // retries
            $retry = true;
        } finally {
            // close Selenium browser

            StatLogger::getInstance()->info("ichotelsgroup login attempt", [
                "success"      => !$retryProviderError,
                "proxy"        => $proxy ?? null,
                "browser"      => $selenium->seleniumRequest->getBrowser() . ":" . $selenium->seleniumRequest->getVersion(),
                "userAgentStr" => $selenium->http->userAgent,
                "resolution"   => $selenium->seleniumOptions->resolution[0] . "x" . $selenium->seleniumOptions->resolution[1],
                "attempt"      => $this->attempt,
                "isWindows"    => stripos($this->http->userAgent, 'windows') !== false,
            ]);

            if ($retryProviderError) {
                $selenium->markProxyAsInvalid();
            }

            try {
                $this->savePageToLogs($selenium);
                $selenium->http->SaveResponse();
            } catch (Throwable $e) {
                $this->logger->warning("failed to save logs: " . $e->getMessage());
            }
            $selenium->http->cleanup(); //todo:

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(4, 7);
            }

            if ($retryProviderError && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(4);
            }
        }

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We are sorry, our system is temporarily unavailable.
        if (empty($this->http->Response['body']) && $this->http->Response['code'] == 200
            && $this->http->currentUrl() == 'https://www.ihg.com/rewardsclub/us/en/sign-in/?fwdest=https://www.ihg.com/rewardsclub/us/en/account/home') {
            throw new CheckException("We are sorry, our system is temporarily unavailable. Please try again later or contact the IHG® Rewards Club Customer Care Center for assistance.", ACCOUNT_PROVIDER_ERROR);
        }

        // We are temporarily unavailable due to system maintenance
        if ($message = $this->http->FindSingleNode("
                //h2[contains(text(), 'We are temporarily unavailable due to system maintenance.')]
                | //h1[contains(text(), 'Our websites are undergoing scheduled maintenance.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Rewards Club system is currently unavailable
        $message = $this->http->FindSingleNode('//div[contains(@class, "errorTopMsgContainer") and contains(@style, "display: block;")]');

        if ($message && $this->http->FindPreg('/Rewards Club system is currently unavailable./', false, $message)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable - Zero size object
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - Zero size object')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function ParseItinerariesNew(object $res)
    {
        $this->logger->notice(__METHOD__);

        if ($res->foundReservation === false) {
            if ($this->http->FindPreg("/^\{\"warnings\":\[\{\"message\":\"No reservations found for search criteria\",\"code\":\"CRS_284\"\}\],\"foundReservation\":false(?:,\"reservationCount\":0,\"totalReservationCount\":0)?\}\s*$/")) {
                $this->itinerariesMaster->setNoItineraries(true);
            }

            return [];
        }

        if (isset($res->hotelReservations) && is_array($res->hotelReservations)) {
            foreach ($res->hotelReservations as $hotelReservation) {
                $confNo = $hotelReservation->reservationIds->confirmationNumber;
                $lastName = $hotelReservation->userProfiles[0]->personName->surname;

                if (count($hotelReservation->hotels) !== 1) {
                    $this->sendNotification("check hotel reservation // ZM");

                    continue;
                }
                $hotelMnemonic = $hotelReservation->hotels[0]->hotelMnemonic;
                $this->logger->info("[{$this->currentItin}] Parse Itinerary #$confNo", ['Header' => 3]);
                $this->currentItin++;
                $headers = [
                    'Accept'        => 'application/json, text/plain, */*',
                    'X-IHG-API-KEY' => self::CONFIG_API_KEY,
                    'Referer'       => "https://www.ihg.com/hotels/us/en/pay/reservation-view?confirmationNumber={$confNo}&lastName={$lastName}",
                ];
                $this->http->RetryCount = 0;
                $this->http->GetURL("https://apis.ihg.com/hotels/v1/profiles/{$hotelMnemonic}/details?fieldset=brandInfo,profile,address,contact,policies,location,facilities,parking,tax,services,marketing", $headers);

                if (
                    in_array($this->http->Response['code'], [500, 504])
                    || $this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)
                ) {
                    sleep(3);
                    $this->http->GetURL("https://apis.ihg.com/hotels/v1/profiles/{$hotelMnemonic}/details?fieldset=brandInfo,profile,address,contact,policies,location,facilities,parking,tax,services,marketing", $headers);

                    if (
                        in_array($this->http->Response['code'], [500, 504])
                        || $this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)
                    ) {
                        sleep(7);
                        $this->http->GetURL("https://apis.ihg.com/hotels/v1/profiles/{$hotelMnemonic}/details?fieldset=brandInfo,profile,address,contact,policies,location,facilities,parking,tax,services,marketing", $headers);
                    }
                } elseif (in_array($this->http->Response['code'], [404])) {
                    // TODO: Such is the presentation on the site
                    $this->http->GetURL("https://apis.ihg.com/hotels/v1/profiles/SYDAP/details?fieldset=brandInfo,profile,address,contact,policies,location,facilities,parking,tax,services,marketing", $headers);
                }
                $hotel = $this->http->JsonLog(null, 2);
                $this->http->GetURL("https://apis.ihg.com/reservations/v2/hotels/{$confNo}?lastName={$lastName}", $headers);
                $reservation = $this->http->JsonLog();

                if ($this->http->Response['code'] == 400
                    && !isset($reservation->hotelReservation)
                    && isset($reservation->message)
                    && $reservation->message === 'The reservation is not accessible via Web or Mobile phone channel.'
                ) {
                    $this->logger->error($reservation->message);
                    $this->parseReservationJson_preview($hotelReservation, $hotel);

                    continue;
                }
                $this->http->RetryCount = 2;

                if (isset($reservation->hotelReservation) && isset($hotel)) {
                    $this->parseReservationJson($reservation, $hotel);
                }
            }
        }

        return [];
    }

    private function parseReservationJson_preview(object $res, object $hotel)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->hotel();

        if (count($res->segments) > 1) {
            $this->sendNotification("check booked segments (preview) // ZM");
        }
        $r->general()
            ->confirmation($res->reservationIds->confirmationNumber)
            ->status($res->segments[0]->status);

        if (strcasecmp($res->segments[0]->status, 'CANCELLED') === 0) {
            $r->general()->cancelled();

            if (isset($res->reservationIds->cancellationNumber)) {
                $r->general()->cancellationNumber($res->reservationIds->cancellationNumber);
            }
        }

        foreach ($res->userProfiles as $guest) {
            $r->general()->traveller(beautifulName($guest->personName->given . ' ' . $guest->personName->surname),
                true);

            if (isset($guest->loyaltyProgram, $guest->loyaltyProgram->loyaltyId)) {
                $r->program()->account($guest->loyaltyProgram->loyaltyId, false);
            }
        }

        if (empty($res->segments)) {
            return;
        }

        $booked = $res->segments[0];

        foreach ($booked->offer->productUses as $productUses) {
            if (in_array($productUses->productTypeCode, ['BRK', 'DIN'])) {
                // BRK - breakfast
                // DIN - dinner
                continue;
            }

            if (!in_array($productUses->productTypeCode, ['SR'])) {
                $this->sendNotification("check type {$productUses->productTypeCode} // ZM");
            }
            $room = $r->addRoom();
            $room->setType($productUses->productName);
        }

        if (count($r->getRooms()) > 1) {
            $this->sendNotification("check rooms // ZM"); // see also guests
        }
        $polices = $hotel->hotelInfo->policies;

        if (isset($polices->checkinTime)) {
            $r->booked()->checkIn2($booked->checkInDate . 'T' . $polices->checkinTime);
        } else {
            $r->booked()->checkIn2($booked->checkInDate);
        }

        if (isset($polices->checkoutTime)) {
            $r->booked()->checkOut2($booked->checkOutDate . 'T' . $polices->checkoutTime);
        } else {
            $r->booked()->checkOut2($booked->checkOutDate);
        }

        foreach ($booked->offer->productUses as $productUse) {
            if (isset($productUse->dailyRates, $productUse->adults) && $productUse->productTypeCode === 'SR') {
                $r->booked()->guests($productUse->adults);

                break;
            }
        }
        $this->fillHotelInfo($r, $hotel);
    }

    private function parseReservationJson(object $res, object $hotel)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->hotel();

        $r->general()
            ->confirmation($res->hotelReservation->reservationIds->confirmationNumbers[0]->ihgConfirmationNumber)
            ->status($res->hotelReservation->reservationStatus)
            ->date(strtotime($res->hotelReservation->createDateTime));

        if (strcasecmp($res->hotelReservation->reservationStatus, 'CANCELLED') === 0) {
            $r->general()->cancelled();

            if (isset($res->hotelReservation->reservationIds->cancellationNumber)) {
                $r->general()->cancellationNumber($res->hotelReservation->reservationIds->cancellationNumber);
            }
        }
        $travellers = $accounts = [];

        foreach ($res->hotelReservation->userProfiles as $guest) {
            if (!empty($guest->personName->given)) {
                $travellers[] = beautifulName($guest->personName->given . ' ' . $guest->personName->surname);
            }

            if (isset($guest->loyaltyProgram, $guest->loyaltyProgram->loyaltyId)) {
                $accounts[] = $guest->loyaltyProgram->loyaltyId;
            }
        }
        $travellers = array_unique($travellers);
        $accounts = array_unique($accounts);

        if (!empty($travellers)) {
            $r->general()->travellers($travellers);
        }

        if (!empty($accounts)) {
            $r->program()->accounts($accounts, false);
        }

        if (empty($res->hotelReservation->segments)) {
            return;
        }

        /*if (count($res->hotelReservation->segments) > 1) {
            $this->sendNotification("check booked segments // MI");
        }*/
        $segment = $res->hotelReservation->segments[0];
        $polices = $segment->offer->policies ?? null;

        if (isset($polices->checkinTime)) {
            $r->booked()->checkIn2($segment->checkInDate . 'T' . $polices->checkinTime);
        } else {
            $r->booked()->checkIn2($segment->checkInDate);
        }

        if (isset($polices->checkoutTime)) {
            $r->booked()->checkOut2($segment->checkOutDate . 'T' . $polices->checkoutTime);
        } else {
            $r->booked()->checkOut2($segment->checkOutDate);
        }

        if ($r->getCancelled()) {
            if (isset($polices->cancellationNoShow->formattedDescription) && property_exists($polices->cancellationNoShow, 'formattedDescription')) {
                $r->general()->cancellation($polices->cancellationNoShow->formattedDescription);
            }
        } elseif ($polices) {
            if (property_exists($polices, 'cancellationNoShow') && isset($polices->cancellationNoShow->formattedDescription)) {
                $r->general()->cancellation($polices->cancellationNoShow->formattedDescription);
            } elseif (property_exists($polices, 'cancellation') && isset($polices->cancellation->formattedDescription)) {
                $r->general()->cancellation($polices->cancellation->formattedDescription);
            } elseif (isset($polices->formattedDescription)) {
                $r->general()->cancellation($polices->formattedDescription);
            }
        }

        if (isset($polices->isRefundable) && $polices->isRefundable === true
            && isset($polices->cancellationNoShow, $polices->cancellationNoShow->deadline->dateTime)
        ) {
            $r->booked()->deadline2($polices->cancellationNoShow->deadline->dateTime);
        } elseif (isset($polices->isRefundable) && $polices->isRefundable === false) {
            $r->booked()->nonRefundable();
        } elseif (!empty($cancelledText = $r->getCancellation())) {
            // parse cancellations
            if ($d = $this->http->FindPreg("/(?:Reward|Free) Night bookings are non-refundable if cancelled within (\d+) days? of arrival./i",
                false, $cancelledText)
            ) {
                $r->booked()->deadlineRelative($d . " days");
            }
        }

        foreach ($segment->offer->productUses as $productUse) {
            if (isset($productUse->dailyRates, $productUse->adults)) {
                $r->booked()->guests($productUse->adults);

                if (isset($productUse->children)) {
                    $r->booked()->kids($productUse->children);
                }

                break;
            }
        }

        $freeNights = 0;

        if ((isset($res->hotelReservation->ratePlanDefinitions[0]->isFreeNight) && $res->hotelReservation->ratePlanDefinitions[0]->isFreeNight === false)
            || isset($res->hotelReservation->ratePlanDefinitions[0]->isFreeNight) === false) {
        }

        // Points
        if (isset($segment->payment->paymentByPoints[0])) {
            //$this->sendNotification('by points // MI');

            if (count($segment->payment->paymentByPoints) > 1) {
                $this->sendNotification('paymentByPoints > 1 // MI');
            }

            foreach ($segment->payment->paymentByPoints as $paymentByPoints) {
                if (!isset($paymentByPoints->totalPoints) && isset($paymentByPoints->voucherSummary)) {
                    if (isset($res->hotelReservation->ratePlanDefinitions) && count($res->hotelReservation->ratePlanDefinitions) > 0) {
                        foreach ($res->hotelReservation->ratePlanDefinitions as $ratePlanDefinitions) {
                            if (!empty($ratePlanDefinitions->isFreeNight) && $ratePlanDefinitions->isFreeNight === true) {
                                $freeNights++;
                            }
                        }
                    }
                } else {
                    if (isset($paymentByPoints->totalPoints)) {
                        $r->price()->spentAwards($paymentByPoints->totalPoints);
                    }

                    foreach ($paymentByPoints->dynamicDailyPoints ?? [] as $dailyPoints) {
                        if ($dailyPoints->dailyPointsCost < 1 && $this->http->FindPreg('/\d+-\d+-\d+/', false,
                                $dailyPoints->start)) {
                            $freeNights++;
                        }
                    }
                }
            }
        }
        // Currency
        else {
            if (isset($res->hotelReservation->totalReservationAmount[0]->amountAfterTax)) {
                $r->price()->total(sprintf("%.3f", $res->hotelReservation->totalReservationAmount[0]->amountAfterTax));
            }

            if (isset($res->hotelReservation->totalReservationAmount[0]->baseAmount)) {
                $r->price()->cost(sprintf("%.3f", $res->hotelReservation->totalReservationAmount[0]->baseAmount));
            }

            if (isset($res->hotelReservation->totalReservationAmount[0]->totalTaxes)) {
                $r->price()->tax(sprintf("%.3f", $res->hotelReservation->totalReservationAmount[0]->totalTaxes));
            }
            $r->price()
                    ->currency($res->hotelReservation->totalReservationAmount[0]->currencyCode);

            // SATURDAY AUG 14 2021-SUNDAY AUG 15 2021 0.01 USD
            if (isset($segment->offer->rates->dailyRates)) {
                foreach ($segment->offer->rates->dailyRates as $dailyRates) {
                    if (isset($dailyRates->dailyTotalRate->amountBeforeTax) && $dailyRates->dailyTotalRate->amountBeforeTax < 1) {
                        $freeNights++;
                    }
                }
            }
        }

        if ($freeNights > 0) {
            $r->booked()->freeNights($freeNights);
        }

        foreach ($res->hotelReservation->productDefinitions as $productDefinition) {
            if (!isset($productDefinition->productName, $productDefinition->description, $productDefinition->productTypeCode) || in_array($productDefinition->productTypeCode, ['BRK'])) {
                // BRK - breakfast
                continue;
            }
            $room = $r->addRoom();
            $room
                ->setType($productDefinition->productName)
                ->setDescription($productDefinition->description);

            // Points
            /*if (isset($segment->payment->paymentByPoints[0])) {
                foreach ($segment->payment->paymentByPoints as $paymentByPoints) {
                    foreach ($paymentByPoints->dynamicDailyPoints ?? [] as $dailyPoints) {
                        $room->setRate($dailyPoints->dailyPointsCost . ' Points');
                    }
                }
            }
            // Currency
            else {
                foreach ($segment->offer->rates->dailyRates as $d) {
                    $room->addRate(($d->dailyTotalRate->baseAmount ?? $d->dailyTotalRate->amountBeforeTax)
                        . ' ' . $res->hotelReservation->totalReservationAmount[0]->currencyCode);
                }
            }*/

            if (isset($res->hotelReservation->rateCategory)) {
                $room->setRateType($res->hotelReservation->rateCategory->longName);
            }
        }

        $this->fillHotelInfo($r, $hotel);
    }

    private function fillHotelInfo(AwardWallet\Schema\Parser\Common\Hotel $r, object $hotel)
    {
        $this->logger->notice(__METHOD__);

        if (empty($hotel->hotelInfo)) {
            return;
        }
        $r->hotel()
            ->name($hotel->hotelInfo->brandInfo->brandName ?? $hotel->hotelInfo->profile->name)
            ->address(implode(', ', array_filter([
                $hotel->hotelInfo->address->street1 ?? null,
                $hotel->hotelInfo->address->street4 ?? null,
                $hotel->hotelInfo->address->city ?? null,
                $hotel->hotelInfo->address->state->code ?? null,
                $hotel->hotelInfo->address->zip ?? null,
                $hotel->hotelInfo->address->country->name ?? null,
            ])))
            ->phone($hotel->hotelInfo->contact[0]->frontDeskNumber ?? null, false, true)
            ->fax($hotel->hotelInfo->contact[0]->faxNumber ?? null, false, true);
    }

    private function ArrayVal($ar, $indices)
    {
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return null;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    private function getHeaders()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.ihg.com/static/uhf/brands/us/en/uhf.min.js');
        $apiKey = $this->http->FindPreg('/APIGEE_APIKEY_VALUE="(\w+)";/');

        if (!$apiKey) {
            $this->logger->error('ihg key or token not found');

            return null;
        }
        $this->http->RetryCount = 2;

        return $this->headers = [
            "x-ihg-api-key"       => $apiKey,
            "x-ihg-sso-token"     => $this->http->getCookieByName('X-IHG-SSO-TOKEN', '.ihg.com'),
            "accept"              => "application/json, text/plain, */*",
        ];
    }

    private function getRulesToken()
    {
        $this->logger->notice(__METHOD__);

        if ($this->rulesToken) {
            return $this->rulesToken;
        }
        /** @var HttpBrowser $http2 */
        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);
        $http2->GetURL('https://www.ihg.com/guestapi/v1/crowneplaza/us/en/web/resolveCountryLanguage?brandCode=6c&targetLanguage=en&_cbVar=&_=');
        $this->rulesToken = ArrayVal($http2->Response['headers'], 'x-ihg-mws-api-token');

        return $this->rulesToken;
    }

    private function ParseItineraryJson($conf, $lastName): ?string
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("[{$this->currentItin}] Parse Itinerary #$conf", ['Header' => 3]);
        $this->currentItin++;

        $reservationLink = "https://apis.ihg.com/reservations/v1/hotelReservations/{$conf}?lastName={$lastName}";
        $itinHeaders = [
            'Accept'          => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'gzip, deflate, br',
            'X-IHG-API-KEY'   => self::CONFIG_API_KEY,
            'X-IHG-SSO-TOKEN' => $this->http->getCookieByName("X-IHG-SSO-TOKEN", ".ihg.com"),
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL($reservationLink, $itinHeaders);

        if ($this->http->FindPreg('/"faultstring":"Gateway Timeout"/')) {
            sleep(3);
            $this->http->GetURL($reservationLink, $itinHeaders);
        }
        $this->http->RetryCount = 2;

        if (
            ($msg = $this->http->FindSingleNode('//title[contains(text(), "Error 503 Service Unavailable")]'))
            || $this->http->FindPreg("/\"message\"\s*:\s*\"Undetermined error\s*\-\s*please report\"/")
            || $this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)
            || (
                ($msg = $this->http->FindSingleNode('//title[contains(text(), "502 Bad Gateway")]'))
                && $this->http->FindPreg("/a padding to disable MSIE and Chrome friendly error page/")
            )
            || (
                ($msg = $this->http->FindSingleNode('//title[contains(text(), "502 Proxy Error")]'))
                && $this->http->FindPreg("/The proxy server received an invalid/")
            )
        ) {
            $this->logger->notice('try to load itinerary one more time');
            sleep(5);
            $this->http->RetryCount = 0;
            $this->http->GetURL($reservationLink, $itinHeaders);
            $this->http->RetryCount = 2;
        }

        $reservationData = $this->http->JsonLog(null, 3, true);

        if (!$reservationData) {
            $this->sendNotification('check itinerary json // MI');
        }

        if ($this->http->FindPreg('/javax\.xml\.ws\.WebServiceException: Could not send Message\./')) {
            $msg = ArrayVal($reservationData, 'message');
            $this->logger->error("Skipping itinerary: {$msg}");

            return $msg;
        }

        if ($message = ArrayVal($reservationData, 'message', null)) {
            $this->logger->error("Error: {$message}");

            if (
                strstr($message, 'Error: The system took too long to process your request.. Please try again later or call the hotel directly.')
                || strstr($message, 'System temporarily unavailable, please try later')
            ) {
                $this->logger->notice('try to load itinerary one more time');
                $this->sendNotification('check itinerary retry // MI');
                sleep(10);
                $this->http->RetryCount = 0;
                $this->http->GetURL($reservationLink, $itinHeaders);
                $this->http->RetryCount = 2;
                $reservationData = $this->http->JsonLog(null, 3, true);
            }

            if (
                strstr($message, 'The reservation cannot be viewed via the Internet')
            ) {
                $this->logger->notice('Skip this reservation');

                return $message;
            }

            if ($error = $this->http->FindPreg('/Last name provided does not match last name on reservation/', false, $message)) {
                $this->logger->notice('Skip this reservation');

                return $error;
            }

            if ($error = $this->http->FindPreg('/Invalid confirmation or cancellation number. Provide a valid confirmation or cancellation number/', false, $message)) {
                $this->logger->notice('Skip this reservation');

                return $error;
            }

            if ($error = $this->http->FindPreg('/The reservation was not found for Synxis confirmation number/', false, $message)) {
                $this->logger->notice('Skip this reservation');

                return $error;
            }

            if ($message === 'Resource Not Found') {
                $error = $reservationData['errors'][0]['message'];

                if ($error === 'Route not recognized by proxy') {
                    $this->logger->notice('Skip this reservation');

                    return 'This confirmation number was not found. For reservations made through a third party, contact the provider directly for assistance.';
                }
            }
        }// if ($message = ArrayVal($reservationData, 'message', null))

        $itineraryNumber = $this->ArrayVal($reservationData, ['confirmationNumbers', 0, 'ihgConfirmationNumber']);
        $h = $this->itinerariesMaster->add()->hotel();

        $h->general()
            ->confirmation($itineraryNumber, "Confirmation #")
            ->date2(ArrayVal($reservationData, 'originalSellDate'));

        $guestFirstName = $this->ArrayVal($reservationData, ['guest', 'firstName']);
        $guestLastName = $this->ArrayVal($reservationData, ['guest', 'lastName']);
        $this->logger->debug("guest: '{$guestFirstName} {$guestLastName}'");

        if (!empty($guestFirstName) && !empty($guestLastName)) {
            $h->general()->traveller(beautifulName("{$guestFirstName} {$guestLastName}"), true);
        }

        $additionalGuests = ArrayVal(ArrayVal($reservationData, 'guest'), 'additionalGuests', []);
        $this->logger->debug("count of additionalGuests: " . count($additionalGuests));

        foreach ($additionalGuests as $additionalGuest) {
            $h->general()
                ->traveller(beautifulName("{$additionalGuest['firstName']} {$additionalGuest['lastName']}"), true);
        }

        $number = $this->ArrayVal($reservationData, ['guest', 'rewardsClubNumber']);

        if (!empty($number)) {
            $h->program()->account($number, false);
        }

        $checkInDate = $this->ArrayVal($reservationData, ['stay', 'checkInDate']);
        $checkOutDate = $this->ArrayVal($reservationData, ['stay', 'checkOutDate']);

        $h->booked()
            ->checkIn2($checkInDate)
            ->checkOut2($checkOutDate)
            ->guests($this->ArrayVal($reservationData, ['stay', 'adults']))
            ->kids($this->ArrayVal($reservationData, ['stay', 'children']))
            ->rooms($this->ArrayVal($reservationData, ['stay', 'numberOfRooms']));

        $h->price()
            ->spentAwards($this->ArrayVal($reservationData, ['pointsAndCashOptions', 'rewardNightRate', 'totalPoints']), false, true);

        if ($this->ArrayVal($reservationData, ['specialNightRate', 'typeIndicator']) !== 'FREE_NIGHT') {
            // Total
            $total = $this->ArrayVal($reservationData, ['stay', 'totalPrice']);
            $tax = $this->ArrayVal($reservationData, ['stay', 'totalTax']);
        }

        $hotelMnemonic = $this->ArrayVal($reservationData, ['stay', 'hotelMnemonic']);
        $rateCode = $this->ArrayVal($reservationData, ['stay', 'rateCode']);
        $totalPrice = $this->ArrayVal($reservationData, ['stay', 'totalPrice']);

        $time = date("UB");
        $hotelLink = "https://apis.ihg.com/hotels/v1/profiles/{$hotelMnemonic}/details?_cbVar={$time}&_={$time}";
        $this->http->GetURL($hotelLink, $itinHeaders);

        if (in_array($this->http->Response['code'], [206, 500, 502, 503, 504])) {
            sleep(5);
            $time = date("UB");
            $hotelLink = "https://apis.ihg.com/hotels/v1/profiles/{$hotelMnemonic}/details?_cbVar={$time}&_={$time}";
            $this->http->GetURL($hotelLink, $itinHeaders);
        }

        $hotelData = $this->http->JsonLog(null, 0, true);
        $address = trim(sprintf(
            '%s, %s',
            $this->ArrayVal($hotelData, ['hotelInfo', 'address', 'street1']),
            $this->ArrayVal($hotelData, ['hotelInfo', 'address', 'city'])
        ));

        $h->hotel()
            ->name($this->ArrayVal($hotelData, ['hotelInfo', 'brandInfo', 'brandName']))
            ->address($address)
            ->phone($this->ArrayVal($hotelData, ['hotelInfo', 'contact', 0, 'frontDeskNumber']), false, true);

        // CheckInDate again
        $time1 = $this->ArrayVal($hotelData, ['hotelInfo', 'policies', 'checkinTime']);

        if ($time1) {
            $h->booked()->checkIn(strtotime($time1, $h->getCheckInDate()));
        }
        // CheckOutDate again
        $time2 = $this->ArrayVal($hotelData, ['hotelInfo', 'policies', 'checkoutTime']);

        if ($time2) {
            $h->booked()->checkOut(strtotime($time2, $h->getCheckOutDate()));
        }
        // Currency
        $currencies = $this->ArrayVal($hotelData, ['hotelInfo', 'policies', 'acceptedCurrencies']);

        if (is_array($currencies) && count($currencies) === 1) {
            $h->price()->currency($this->ArrayVal($currencies, [0, 'code']));
        }

        $this->http->RetryCount = 0;
        $time = time() . date("B");
        $earningsEstimateUrl = sprintf('https://apis.ihg.com/reservations/v1/pointEstimate?cacheBuster=%s&checkInDate=%sZ&checkOutDate=%sZ&currencyCode=%s&hotelCode=%s&iataNumber=0&programCode=PC&rateCode=%s&totalRoomRevenue=%s', $time, $checkInDate, $checkOutDate, $h->getPrice()->getCurrencyCode(), $hotelMnemonic, $rateCode, $totalPrice);
        $this->http->GetURL($earningsEstimateUrl, $itinHeaders);
        $response = $this->http->JsonLog();
        $unitInfos = $response->estimations[0]->unitInfos ?? [];

        foreach ($unitInfos as $unitInfo) {
            $unitName = $unitInfo->units[0]->unitName ?? null;
            $amount = $unitInfo->units[0]->amount ?? null;
            $this->logger->debug("'{$unitName}': {$amount}");

            if ($unitInfo->name == 'Total Earnings' && $unitName == 'IHG REWARDS CLUB POINTS' && $amount > 0) {
                $h->program()->earnedAwards("{$amount} points");

                break;
            } elseif ($unitInfo->name == 'Total Earnings' && strstr($unitName, 'MILES') && !empty($amount)) {
                $h->program()->earnedAwards("{$amount} {$unitName}");

                break;
            } elseif ($unitInfo->name == 'Total Earnings' && !empty($unitName) && !empty($amount)) {
                $this->sendNotification("need to check earnedAwards // RR");
            }
        }// foreach ($unitInfos as $unitInfo)

        $this->http->RetryCount = 1;
        $headers = array_merge($itinHeaders, ['X-IHG-MWS-API-Token' => $this->getRulesToken()]);
        $rulesUrl = "https://apis.ihg.com/guest-api/v1/ihg/us/en/reservationRateRules?confNumber={$conf}&hotelCode={$hotelMnemonic}&lastName={$lastName}";
        $this->http->GetURL($rulesUrl, $headers);

        if (in_array($this->http->Response['code'], [500])) {
            sleep(5);
            $this->http->GetURL($rulesUrl, $headers);
        }
        $this->http->RetryCount = 2;

        $rulesData = $this->http->JsonLog(null, 0, true);

        if (!$rulesData) {
            $this->sendNotification('check parse itinerary json // MI');
        }

        if (!in_array($this->http->Response['body'], [
            '[{"sl_translate":"message,localizedMessage,title","code":"700"}]',
            '[{"sl_translate":"message,localizedMessage,title","message":"This reservation cannot be viewed on the phone.  Please call the hotel directly or your nearest reservation office.","localizedMessage":"This reservation cannot be viewed on the phone.  Please call the hotel directly or your nearest reservation office.","code":"707"}]',
            '[{"message":"This reservation cannot be viewed on the phone.  Please call the hotel directly or your nearest reservation office.","localizedMessage":"This reservation cannot be viewed on the phone.  Please call the hotel directly or your nearest reservation office.","code":"707"}]',
            '[{"sl_translate":"message,localizedMessage,title","message":"An unknown error while retrieving reservation.","localizedMessage":"An unknown error while retrieving reservation.","code":"700"}]',
        ])
        ) {
            // CancellationPolicy
            $h->general()
                ->cancellation($this->ArrayVal($rulesData, ['rate', 'cancellationPolicy']), false, true);

            $deadline = (
                $this->http->FindPreg("/^Canceling your reservation before ([^\.]+) will result in no charge\./", false, $h->getCancellation())
                ?? $this->http->FindPreg("/^Canceling your reservation after ([^\.]+), or failing to show, will result in a charge/", false, $h->getCancellation())
            );

            if ($deadline) {
                $this->logger->debug($deadline);
                $deadline = preg_replace('/\s*\(local hotel time\)\s*on\s*\w+\s*\,/', '', $deadline);
                $deadline = str_replace(' (local hotel time) on ', ' ', $deadline);
                $deadline = str_replace(',', '', $deadline);
                $this->logger->debug($deadline);
                $h->booked()->deadline2($deadline);
            } elseif (
                $this->http->FindPreg('/^(?:Canceling your reservation or failing to arrive will result in forfeiture of your deposit.|Canceling your reservation or failing to show will result in a charge for the first night per room to your credit card.|Canceling your reservation or failing to show will result in a charge for 1 night per room to your credit card.|Canceling your reservation or failing to show will result in a charge for the entire stay per room to your credit card\.|Canceling your reservation before [^\.]+ will result in a charge for the entire stay per room to your credit card\.|Canceling your reservation before [^\.]+ will result in a charge for the first night per room to your credit card\.|Canceling your reservation before ([^\.]+) will result in a charge for the 1 night per room to your credit card\.|Canceling your reservation before [^\,\.]+ on or before [^\.]+ will result in a charge for 1 night per room to your credit card.|^Canceling your reservation or failing to show will result in a charge for the first and last nights per room to your credit card|Canceling your reservation after [^\.]+ or failing to show, will result in a charge equal|Cancelling your reservation or failing to arrive results in forfeiting your prepayment less a deduction for the saved expenses to the hotel|^Canceling your reservation before [^\.]+ will result in a charge for the first night per room to your credit card or other guaranteed payment method\.)/', false, $h->getCancellation())
                || $this->http->FindPreg('/(?:Cancelling your reservation or failing to arrive results in forfeiting your prepayment less a deduction for the saved expenses to the hotel \(generally 10\% of booking price\), which will be repaid to your credit card\.|Cancellation policy for hotels in Germany: There will be a deduction from this charge for expenses saved by the hotel|Canceling your reservation or failing to show will result in a charge for the entire stay per room to your credit card or other guaranteed payment method.|Cancelling your reservation or failing to arrive results in forfeiting your prepayment less a deduction for the saved expenses to the hotel \(generally 10\% of booking price\))/', false, $h->getCancellation())
            ) {
                $h->booked()->nonRefundable();
            } elseif ($h->getCancellation()) {
                $this->sendNotification("Non-refundable stay?");
            }

            $room = $h->addRoom()
                ->setType($rulesData['room']['description'] ?? null)
                ->setDescription($rulesData['room']['longDescription'] ?? null, false, true)
                ->setRateType($rulesData['rate']['longDescription'] ?? null, true, true);

            if ($room->getRateType() !== 'Refunded in Points Only' && isset($total)) {
                $this->logger->info('points-only reservation');
                $h->price()
                    ->tax($tax)
                    ->total($total);

                $avgNightlyRate = $rulesData['room']['charges']['avgNightlyRate'] ?? null;

                if ($avgNightlyRate) {
                    $room->setRate("{$avgNightlyRate} {$h->getPrice()->getCurrencyCode()} per room, per night");
                }
            }

            $h->price()->cost($rulesData['room']['charges']['price'] ?? null);
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);

        return null;
    }

    private function ParsePageHistory($startIndex, $startDate, $data)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $activities = $data->activities ?? [];

        foreach ($activities as $activity) {
            $node = $activity->activitySummary;
            $dateStr = $node->datePosted;
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                continue;
            }
            $result[$startIndex]['Date Posted'] = $postDate;
            $result[$startIndex]['Activity Type'] = $node->activityType ?? null;
            $result[$startIndex]['Description'] = $node->description ?? null;

            if ($this->http->FindPreg("/Bonus/ims", false, $result[$startIndex]['Description'])) {
                $result[$startIndex]['Bonus'] = $node->totalEarnedValue ?? null;
            } else {
                $result[$startIndex]['Total Earned'] = $node->totalEarnedValue ?? null;
            }
            $result[$startIndex]['Elite Qualifying Points'] = $node->eliteQualifyingPointValue ?? null;
            $startIndex++;
        }

        return $result;
    }

    private function sendStatistic($success, $retry, $key)
    {
        $this->logger->notice(__METHOD__);
        StatLogger::getInstance()->info("ichotelsgroup sensor_data attempt", [
            "success"         => $success,
            "userAgentStr"    => $this->http->userAgent,
            "retry"           => $retry,
            "attempt"         => $this->attempt,
            "sensor_data_key" => $key,
            "isWindows"       => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }
}
