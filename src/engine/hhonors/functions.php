<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Schema\Parser\Common\Hotel;

class TAccountCheckerHhonors extends TAccountChecker
{
    use PriceTools;
    use ProxyList;
    use SeleniumCheckerHelper;

    public const REWARDS_PROFILE_PAGE = 'https://www.hilton.com/en/hilton-honors/guest/my-account/';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    /*
    private $bugInAccount = false;
    */
    private $currentItin = 0;
    private $cntSkippedPast = 0;

    private $guestActivitiesSummary = null;

    public static function GetAccountChecker($accountInfo)
    {
//        if ($accountInfo["Login"] != 'norris05') {
//        return new static();
//        }

        require_once __DIR__ . "/TAccountCheckerHhonorsSelenium.php";

        return new TAccountCheckerHhonorsSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->FilterHTML = false;
        $this->http->setHttp2(true);
        // refs #14820
        /*
        $this->setProxyBrightData();
        */

        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip');
        $this->http->setRandomUserAgent(5);
//        $this->http->setUserAgent("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.3 Safari/605.1.15");
    }

    public function IsLoggedIn()
    {
        return false;
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PROFILE_PAGE, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }
        $this->delay();

        return false;
    }

    public function LoadLoginForm()
    {
        // refs #21922
        if ($this->attempt == 2) {
            $this->setProxyMount();
        } elseif ($this->attempt > 0) {
            $this->setProxyGoProxies();
        } else {
            $this->setProxyBrightData(null, "static", "gb");
        }
        $this->http->setRandomUserAgent(5);

        $this->http->removeCookies();
//        $this->selenium();
//
//        return true;

        $this->http->GetURL("https://www.hilton.com/en/hilton-honors/login/?forwardUrl=https%3A%2F%2Fwww.hilton.com%2Fen%2Fhilton-honors%2Fguest%2Fmy-account%2F");
        // retries
        if ($this->http->Response['code'] == 0 || $this->http->Response['code'] == 403) {
            $this->DebugInfo = 'Blocked';
            $this->ErrorReason = self::ERROR_REASON_BLOCK;

            throw new CheckRetryNeededException(4, 7);
        } else {
            $this->DebugInfo = null;
        }

        // refs #16199
        $this->AccountFields['Pass'] = substr($this->AccountFields['Pass'], 0, 32);

        $clientId = $this->http->FindPreg("/\"REACT_APP_WSO2_CLIENT_TOKEN_ID\":\"([^\"]+)/");
        $clientSecret = $this->http->FindPreg("/\"REACT_APP_WSO2_CLIENT_TOKEN_SECRET\":\"([^\"]+)/");

        if (!$clientId || !$clientSecret) {
            return $this->checkErrors();
        }

//        $this->selenium();
//
//        return true;

        /*
        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        */

        $data = [
            "username"     => $this->AccountFields['Login'],
            "password"     => $this->AccountFields['Pass'],
            "remember"     => true,
            //            "recaptcha"    => $captcha,
        ];
        $headers = [
            "Accept"              => "application/json; charset=utf-8",
            "Accept-Language"     => "en-US,en;q=0.5",
            //            "Accept-Encoding"     => "gzip, deflate, br",
            "Content-Type"        => "application/json; charset=utf-8",
            "x-dtreferer"         => "https://www.hilton.com/en/hilton-honors/login/?forwardUrl=https://www.hilton.com/en/hilton-honors/guest/my-account/",
            "Referer"             => "https://www.hilton.com/en/auth2/guest/login/",
            //            "X-Sec-Clge-Req-Type" => "ajax",
            "Forter-Token-Cookie" => $this->http->getCookieByName("forterToken", ".hilton.com"),
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.hilton.com/en/auth2/api/guest/login/OHW/", json_encode($data), $headers);

        /*
        if ($this->http->Response['code'] === 403 && empty($this->http->Response['body'])) {
            $this->logger->notice("sensor_data wokraround");
            $this->selenium();
            $this->http->PostURL("https://www.hilton.com/en/auth2/api/guest/login/OHW/", json_encode($data), $headers);
        }
        */

        $response = $this->http->JsonLog();

        if (isset($response->branding_url_content, $response->provider_secret_public)) {
            $this->http->GetURL(urldecode($response->branding_url_content));
            $captcha = $this->parseCaptcha($response->provider_secret_public);

            if ($captcha === false) {
                return false;
            }

            $this->http->GetURL("https://www.hilton.com/_sec/cp_challenge/verify?cpt-token={$captcha}");
            $response = $this->http->JsonLog();

            if (isset($response->success) && $response->success == false) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }

            if (
                strstr($this->http->Error, 'Network error 56 - OpenSSL SSL_read: error')
            ) {
                $this->captchaReporting($this->recognizer);
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            $this->http->PostURL("https://www.hilton.com/en/auth2/api/guest/login/OHW/", json_encode($data), $headers);
        }

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if ($wso2AuthToken = $this->http->getCookieByName("wso2AuthToken", ".hilton.com", "/", true)) {
            $response = $this->http->JsonLog($wso2AuthToken);
        }

        $access_token =
            $response->data->tokenInfo->access_token
            ?? $response->accessToken
            ?? null
        ;
        $token_type =
            $response->data->tokenInfo->token_type
            ?? $response->tokenType
            ?? null
        ;

        if (!$access_token || !$token_type) {
            $handler = $response->handler ?? null;
            $status = $response->error->status ?? null;

            if ($status) {
                $this->logger->error($status);

                if ($status == 'unexpected_error') {
                    throw new CheckRetryNeededException(3, 1);
                }

                if ($status == 'invalid_recaptcha') {
                    $this->captchaReporting($this->recognizer, false);

                    throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
                }
                $this->captchaReporting($this->recognizer);

                if ($status == 'invalid_grant' && $handler == 'onLogin') {
                    $this->markProxySuccessful();

                    throw new CheckException("Your login didn’t match our records. Please try again. Be careful: too many attempts will lock your account.", ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    ($status == 'An unknown server error has occurred.' && $handler == 'unknown_error')//todo
                    || ($status == 'unexpected_error.' && $handler == 'onLogin')
                ) {
                    throw new CheckException("Something went wrong, and your request wasn't submitted. Please try again later.", ACCOUNT_PROVIDER_ERROR);
                }
            }// if ($status)

            if (
                $this->http->Error === 'Network error 56 - Unexpected EOF'
                || $this->http->Error === 'Network error 28 - Unexpected EOF'
                || strstr($this->http->Error, 'Network error 28 - Operation timed out after')
                || strstr($this->http->Error, 'Network error 52 - Empty reply from server')
            ) {
                $this->captchaReporting($this->recognizer);
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            // may be wrong captcha answer?
            if (
                isset($this->http->Response['code'])
                && $this->http->Response['code'] === 403
                && empty($this->http->Response['body'])
            ) {
//                $this->captchaReporting($this->recognizer);

                if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                    return false;
                }

                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(2, 3, "Something went wrong, and your request wasn't submitted. Please try again later.");
            }

            if (
                $this->http->FindPreg("/No server is available to handle this request\./")
                || $this->http->FindSingleNode('//h1[contains(text(), "Service Unavailable - Zero size object<")]')
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }// if (!$access_token || !$token_type)

        $guestId =
            $response->data->userInfo->guestId
            ?? $response->guestId
        ;
        $wso2AuthToken = [
            "accessToken" => $access_token,
            "expiresIn"   => $response->data->tokenInfo->expires_in ?? $response->expiresIn,
            "tokenType"   => $token_type,
            "username"    => null,
            "timestamp"   => date("UB"),
            "guestId"     => $guestId,
        ];
        $this->http->setCookie("wso2AuthToken", json_encode($wso2AuthToken), ".hilton.com");
        $headers = [
            "Accept"          => "application/json; charset=utf-8",
            "Accept-Language" => "en-US,en;q=0.5",
            "Accept-Encoding" => "gzip, deflate, br",
            "Content-Type"    => "application/json; charset=utf-8",
            "Authorization"   => "{$token_type} {$access_token}",
            "Referer"         => "https://www.hilton.com/en/hilton-honors/login/?forwardUrl=https://www.hilton.com/en/hilton-honors/guest/my-account/",
        ];
        $data = [
            "query"         => "query guest {guest(guestId: {$guestId}, language: \"en\") { guestId hhonors { hhonorsNumber summary { totalPointsFmt tierName } } personalinfo { name { firstName } } }}",
            "operationName" => "guest",
        ];
        $this->http->PostURL("https://www.hilton.com/graphql/customer?operationName=guest", json_encode($data), $headers);
        $response = $this->http->JsonLog();

        if (
            isset($this->http->Response['code'])
            && $this->http->Response['code'] === 503
            || strstr($this->http->Error, 'Network error 52 - Empty reply from server')
            || (isset($response->data) && $response->data->guest === null)
        ) {
            sleep(5);
            $this->http->PostURL("https://www.hilton.com/graphql/customer?operationName=guest", json_encode($data), $headers);
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "503 Service Temporarily Unavailable")]')
            || $this->http->FindPreg('/\{"errors":\[\{"message":"Service Unavailable",/')
            || (
                $this->http->FindPreg('/"path":\["guest"\],"extensions":\{"code":"503","exception":\{"originalError":\{"message":"\[Breaker:/')
                && $this->http->FindPreg('/\],"data":\{"guest":null\},"extensions":\{"logSearch":"mdc.client_message_id/')
            )
        ) {
            throw new CheckRetryNeededException(3, 5);
        }

        $response = $this->http->JsonLog();
        $this->http->RetryCount = 2;

        if (isset($response->data->guest->hhonors->hhonorsNumber)) {
            $this->http->GetURL(self::REWARDS_PROFILE_PAGE);
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            $this->markProxySuccessful();
            $this->captchaReporting($this->recognizer);

            return true;
        }

        // no errors, no auth (AccountID: 3462308)
        if (strstr($this->http->Response['body'], '{"errors":[{"message":"Not Found","locations":[],"path":["guest"],"extensions":{"code":"404","exception":{}},"context":"dx-guests-gql","code":404}],"data":{"guest":null},"extensions":{"logSearch":"')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // provider bug fix, it helps
        if (
            strstr($this->http->Response['body'], '{"errors":[{"message":"Gateway Timeout","locations":[],"path":["guest"],"extensions":{"code":"504","exception":')
            || $this->http->FindSingleNode('//h1[contains(text(), "503 Service Temporarily Unavailable")]')
            || $this->http->FindPreg('/\{"errors":\[\{"message":"Service Unavailable",/')
            || (
                $this->http->FindPreg('/"path":\["guest"\],"extensions":\{"code":"503","exception":\{"originalError":\{"message":"\[Breaker:/')
                && $this->http->FindPreg('/\],"data":\{"guest":null\},"extensions":\{"logSearch":"mdc.client_message_id/')
            )
        ) {
            $this->markProxySuccessful();
            $this->captchaReporting($this->recognizer);

            throw new CheckRetryNeededException(3, 5, self::PROVIDER_ERROR_MSG);
        }

        if (strstr($this->http->Error, 'Network error 52 - Empty reply from server')) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 5);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $data = $this->http->JsonLog(null, 0);
        $guestInfo = $data->data->guest->personalinfo ?? null;
        // Name
        $firstName =
            $guestInfo->name->firstName
            ?? $this->http->getCookieByName("firstName")
            ?? ''
        ;
        $lastName = $guestInfo->name->lastName ?? '';
        $name = trim(beautifulName("{$firstName} {$lastName}"));
        $this->SetProperty("Name", $name);
        // Member Number
        $this->SetProperty("Number", $data->data->guest->hhonors->hhonorsNumber ?? null);
        // Status
        $this->SetProperty("Status", $data->data->guest->hhonors->summary->tierName ?? null);

        $brandGuest = $this->getBrandGuest();

        if (
            ($this->http->Response['code'] === 503 || strstr($this->http->Error, 'Network error 52 - Empty reply from server'))
            && empty($brandGuest)
        ) {
            $brandGuest = $data;
        }

        // Qualification Period
        $this->SetProperty("YearBegins", strtotime("1 JAN"));
        // Stays
        $this->SetProperty("Stays", $this->http->FindPreg("/\"qualifiedStays\":\s*([^,]+)/"));
        // Nights
        $this->SetProperty("Nights", $brandGuest->data->guest->hhonors->summary->qualifiedNights ?? null);
        // Base Points
        $this->SetProperty("BasePoints", $brandGuest->data->guest->hhonors->summary->qualifiedPointsFmt ?? null);
        // To Maintain Current Level
        $this->SetProperty("ToMaintainCurrentLevel", $brandGuest->data->guest->hhonors->summary->qualifiedNightsMaint ?? null);
        // To Reach Next Level
        $this->SetProperty("ToReachNextLevel", $brandGuest->data->guest->hhonors->summary->qualifiedNightsNext ?? null);
        // Points To Next Level
        $this->SetProperty("PointsToNextLevel", $brandGuest->data->guest->hhonors->summary->qualifiedPointsNextFmt ?? null);
        // Balance - Total Points
        $balance = $brandGuest->data->guest->hhonors->summary->totalPointsFmt ?? $data->props->pageProps->userSession->totalPoints ?? null;

        // refs #20867
        $this->logger->info('Free Night Rewards', ['Header' => 3]);
        $wso2AuthToken = $this->http->JsonLog($this->http->getCookieByName("wso2AuthToken"));

        if (!isset($wso2AuthToken->accessToken) || !isset($wso2AuthToken->guestId)) {
            $this->logger->error("get history failed: token or guest id not found");

            return;
        }

        $headers = [
            "Authorization" => "{$wso2AuthToken->tokenType} {$wso2AuthToken->accessToken}",
            "Origin"        => "https://www.hilton.com",
            "Accept"        => "*/*",
            "Content-Type"  => "application/json",
        ];
        $data = '{"operationName":"guest_hotel_MyAccount","variables":{"guestId":' . $wso2AuthToken->guestId . ',"language":"en"},"query":"query guest_hotel_MyAccount($guestId: BigInt!, $language: String!) {\n  guest(guestId: $guestId, language: $language) {\n    id: guestId\n    guestId\n    personalinfo {\n      name {\n        firstName @toTitleCase\n        __typename\n      }\n      emails {\n        validated\n        __typename\n      }\n      phones {\n        validated\n        __typename\n      }\n      hasUSAddress: hasAddressWithCountry(countryCodes: [\"US\"])\n      __typename\n    }\n    hhonors {\n      hhonorsNumber\n      isTeamMember\n      isLifetimeDiamond\n      isOwner\n      isOwnerHGV\n      isAmexCardHolder\n      summary {\n        tier\n        tierName\n        nextTier\n        requalTier\n        pointsExpiration\n        tierExpiration\n        nextTierName\n        totalPointsFmt\n        qualifiedNights\n        qualifiedNightsNext\n        qualifiedPoints\n        qualifiedPointsNext\n        qualifiedPointsFmt\n        qualifiedPointsNextFmt\n        qualifiedNightsMaint\n        rolledOverNights\n        showRequalMaintainMessage\n        showRequalDowngradeMessage\n        milestones {\n          applicableNights\n          bonusPoints\n          bonusPointsFmt\n          bonusPointsNext\n          bonusPointsNextFmt\n          maxBonusPoints\n          maxBonusPointsFmt\n          maxNights\n          nightsNext\n          showMilestoneBonusMessage\n          __typename\n        }\n        __typename\n      }\n      amexCoupons {\n        _available {\n          totalSize\n          __typename\n        }\n        _held {\n          totalSize\n          __typename\n        }\n        _used {\n          totalSize\n          __typename\n        }\n        available(sort: {by: startDate, order: asc}) {\n          ...GuestHHonorsAmexCoupon\n          __typename\n        }\n        held {\n          ...GuestHHonorsAmexCoupon\n          __typename\n        }\n        used {\n          ...GuestHHonorsAmexCoupon\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment GuestHHonorsAmexCoupon on GuestHHonorsDetailCoupon {\n  checkInDate\n  checkOutDate\n  code\n  codeMasked\n  confirmationNumber\n  checkOutDateFmt(language: $language)\n  endDate\n  endDateFmt(language: $language)\n  location\n  numberOfNights\n  offerCode\n  offerName\n  points\n  rewardType\n  startDate\n  status\n  hotel {\n    name\n    images {\n      master(imageVariant: honorsPropertyImageThumbnail) {\n        url\n        altText\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n"}';
        $this->http->PostURL("https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest_hotel_MyAccount", $data, $headers);

        if (
            $this->http->Response['code'] === 503
            || strstr($this->http->Error, 'Network error 52 - Empty reply from server')
        ) {
            sleep(5);
            $this->http->PostURL("https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest_hotel_MyAccount", $data, $headers);
        }

        $freeNightResponse = $this->http->JsonLog();

        $this->logger->info('Free Night Rewards: Ready to use', ['Header' => 4]);
        $availableCoupons = $freeNightResponse->data->guest->hhonors->amexCoupons->available ?? [];
        $this->parseFreeNightRewards($availableCoupons);

        $this->logger->info('Free Night Rewards: Reserved for upcoming stay', ['Header' => 4]);
        $reservedCoupons = $freeNightResponse->data->guest->hhonors->amexCoupons->held ?? [];
        $this->parseFreeNightRewards($reservedCoupons, true);

        // Expiration Date  // refs #4761
        $this->delay();
        $this->http->GetURL("https://www.hilton.com/en/hilton-honors/guest/activity/");

        // Retry login
        if (strstr($this->http->currentUrl(), 'https://secure3.hilton.com/en/hh/customer/login/index.htm?forwardPage')) {
            throw new CheckRetryNeededException(3, 1);
        }

        $this->SetBalance($balance);

        // refs #19889, AccountID: 4960107, 5353853
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $balance === "") {
            $this->logger->notice("provider bug fix balance === '{$balance}'");
            $this->SetBalance(0);
        }

        $this->logger->info('Expiration Date', ['Header' => 3]);
        $this->getHistory();
        $hasCanceled = false;

        $dec31 = 1672444800; // December 31, 2022

        foreach ($this->guestActivitiesSummary as $transaction) {
            if (isset($transaction->departureDate) && in_array(strtolower($transaction->guestActivityType),
                    ['past', 'other']) &&
                // https://redmine.awardwallet.com/issues/21728#note-8
                $transaction->totalPoints != 0
            ) {
                $departureDate = $transaction->departureDate;
                $d = strtotime("+24 months", strtotime($departureDate));

                if ($d !== false) {
                    $this->SetProperty("LastActivity", $departureDate);
                    $this->SetExpirationDate($d);

                    // https://redmine.awardwallet.com/issues/18690#note-23
                    if (isset($this->Properties["AccountExpirationDate"]) && $this->Properties["AccountExpirationDate"] < $dec31) {
                        $this->logger->notice("extending exp date by provider rules");
                        $this->SetExpirationDate($dec31);
                    }

                    break;
                }// if ($d !== false)
            }// if (isset($guestActivitiesSummary[0]->arrivalDate))
            elseif (isset($transaction->departureDate) && in_array($transaction->guestActivityType, ['cancelled'])) {
                $hasCanceled = true;
            }
        }// foreach ($this->guestActivitiesSummary as $transaction)

        /*
        if (
            ($this->guestActivitiesSummary === [] || $hasCanceled)
            && time() < $dec31// https://redmine.awardwallet.com/issues/18690#note-23
        ) {
            $this->logger->notice("no history, extending exp date by provider rules");
            $this->SetExpirationDate($dec31);
        }
        */

        // refs #14648
        $this->logger->info('Zip Code', ['Header' => 3]);
        $this->logger->debug('ZipCodeParseDate: ' . ($this->State['ZipCodeParseDate'] ?? null));
        $this->logger->debug('Time: ' . strtotime("-1 month"));

        if (
            !isset($this->State['ZipCodeParseDate'])
            || $this->State['ZipCodeParseDate'] < strtotime("-1 month")
        ) {
//            $this->http->GetURL("https://www.hilton.com/en/hilton-honors/guest/profile/personal-information/");
            $wso2AuthToken = $this->http->JsonLog($this->http->getCookieByName("wso2AuthToken"));

            if (!isset($wso2AuthToken->accessToken) || !isset($wso2AuthToken->guestId)) {
                $this->logger->error("get Profile Info failed: token or guest id not found");

                return;
            }

            $headers = [
                "Authorization" => "{$wso2AuthToken->tokenType} {$wso2AuthToken->accessToken}",
                "Origin"        => "https://www.hilton.com",
                "Accept"        => "*/*",
                "Content-Type"  => "application/json",
            ];
            $data = '{"operationName":"guest_languages","variables":{"guestId":' . $wso2AuthToken->guestId . ',"language":"en"},"query":"query guest_languages($language: String!, $guestId: BigInt!) {\n  guest(guestId: $guestId, language: $language) {\n    ...GuestPersonalInfo\n    ...HonorsInfo\n    ...Preferences\n    ...GuestTravelAccounts\n    __typename\n  }\n  languages(language: $language, sort: [{by: languageName}]) {\n    __typename\n    languageCode\n    languageName\n  }\n}\n\nfragment GuestPersonalInfo on Guest {\n  personalinfo {\n    __typename\n    name {\n      __typename\n      nameFmt @toTitleCase\n    }\n    paymentMethods(sort: [{by: preferred}]) {\n      ...PaymentMethods\n      __typename\n    }\n    phones(sort: [{by: preferred}]) {\n      ...PhoneNumbers\n      __typename\n    }\n    addresses {\n      ...Address\n      __typename\n    }\n    emails(sort: [{by: preferred}]) {\n      ...Email\n      __typename\n    }\n  }\n  __typename\n}\n\nfragment Address on GuestAddress {\n  __typename\n  addressId\n  addressLine1 @toTitleCase\n  addressLine2 @toTitleCase\n  addressLine3 @toTitleCase\n  addressType @toTitleCase\n  city @toTitleCase\n  state @toSentenceCase\n  postalCode\n  country\n  countryName\n  preferred\n  company @toTitleCase\n}\n\nfragment Email on GuestEmail {\n  __typename\n  emailId\n  emailAddressMasked\n  preferred\n  validated\n}\n\nfragment PhoneNumbers on GuestPhone {\n  __typename\n  phoneId\n  phoneType\n  phoneExtension\n  phoneNumberMasked(format: masked)\n  preferred\n  validated\n  phoneNumber2FAStatus\n  phoneCountry\n}\n\nfragment PaymentMethods on GuestPaymentMethod {\n  __typename\n  paymentId\n  cardCode\n  cardName\n  cardExpireDate\n  lastFour: cardNumberMasked(format: lastFour)\n  cardNumberMasked: cardNumberMasked(format: masked)\n  cardExpireDateMed: cardExpireDateFmt(format: \"medium\")\n  cardExpireDateLong: cardExpireDateFmt(format: \"long\")\n  expired\n  preferred\n}\n\nfragment HonorsInfo on Guest {\n  __typename\n  hhonors {\n    __typename\n    hhonorsNumber\n    summary {\n      tierName\n      __typename\n    }\n  }\n}\n\nfragment Preferences on Guest {\n  __typename\n  preferences {\n    __typename\n    personalizations {\n      __typename\n      preferredLanguage\n    }\n  }\n}\n\nfragment GuestTravelAccounts on Guest {\n  __typename\n  travelAccounts {\n    ...TravelAccounts\n    __typename\n  }\n}\n\nfragment TravelAccounts on GuestTravelAccounts {\n  __typename\n  corporateAccount\n  travelAgentNumber\n  unlimitedBudgetNumber\n  aarpNumber\n  aaaNumber\n  aaaInternationalNumber\n  travelAgentNumber\n  governmentMilitary\n}\n"}';
            $this->http->PostURL("https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest_languages", $data, $headers);

            if (
                $this->http->Response['code'] === 503
                || strstr($this->http->Error, 'Network error 52 - Empty reply from server')
            ) {
                sleep(5);
                $this->http->PostURL("https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest_languages", $data, $headers);
            }

            $profile = $this->http->JsonLog();
            $addresses = $profile->data->guest->personalinfo->addresses ?? [];

            foreach ($addresses as $address) {
                if ($address->addressType !== 'home' || $address->preferred != true) {
                    continue;
                }
                $this->SetProperty("ZipCode", $address->postalCode);
                $this->State['ZipCodeParseDate'] = time();

                $this->SetProperty("ParsedAddress", preg_replace(
                    '/(, ){2,}/',
                    ', ',
                    $address->addressLine1
                    . ', ' . $address->addressLine2
                    . ', ' . $address->addressLine3
                    . ', ' . $address->city
                    . ', ' . $address->state
                    . ', ' . $address->postalCode
                    . ', ' . $address->countryName
                ));
            }
            // Name
            $name = $this->http->FindPreg('/GuestName","nameFmt":"(.+?)"/ims');

            if ($name) {
                $this->SetProperty("Name", beautifulName($name));
            }
        }// if (!isset($this->State['ZipCodeParseDate']) || $this->State['ZipCodeParseDate'] > strtotime("-1 month"))
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
            "ArrivalDate" => [
                "Caption"  => "Arrival date",
                "Type"     => "date",
                "Value"    => date("Y-m-d"),
                "Required" => true,
            ],
        ];
    }

    public function ParseItineraries()
    {
        $guestActivitiesSummary = $this->getHistory();
        $activities = json_decode(json_encode($guestActivitiesSummary), true);

        $upcoming = [];
        $cancelled = [];
        $past = [];
        $this->cntSkippedPast = 0;
        $checkNewType = false;

        foreach ($activities as $activity) {
            $type = ArrayVal($activity, 'guestActivityType');

            if ($type === 'upcoming') {
                $upcoming[] = $activity;
            } elseif ($type === 'cancelled') {
                $cancelled[] = $activity;
            } elseif ($type === 'past') {
                $past[] = $activity;
            } elseif ($type === 'other') {
                $this->logger->notice('Skipping type other');
            } else {
                $this->logger->notice("New type: {$type}");
                $checkNewType = true;
            }
        }

        if ($checkNewType) {
            $this->sendNotification('Check new itin type // MI');
        }
        $cntUpcoming = count($upcoming);
        $cntCancelled = count($cancelled);
        $cntPast = count($past);
        $this->logger->info(sprintf('Found %s upcoming itineraries', $cntUpcoming));
        $this->logger->info(sprintf('Found %s cancelled itineraries', $cntCancelled));
        $this->logger->info(sprintf('Found %s past itineraries', $cntPast));

        $this->logger->info("Parse main info for itineraries (total: {$cntUpcoming})", ['Header' => 3]);

        foreach ($upcoming as $i => $activity) {
            if ($i >= 50) {
                $this->logger->debug("Save {$i} reservations");

                break;
            }

            $reservationData = $this->getReservationData($activity);

            // sometimes it helps
            if ($this->http->FindPreg("/\"data\":\{\"reservation\":null\},\"extensions\":/")) {
                sleep(2);
                $reservationData = $this->getReservationData($activity);
            }

            if ($reservationData) {
                $this->parseItinerary($reservationData);
            } else {
                $this->parseMinimalItinerary($activity);
            }

            if ($i % 5 === 0) {
                $this->logger->notice('Increase Time Limit: 100 sec');
                $this->increaseTimeLimit(50);
            }
        }
        $this->logger->info("Parse info for cancelled itineraries (total: {$cntCancelled})", ['Header' => 3]);

        foreach ($cancelled as $activity) {
            $this->parseMinimalItinerary($activity, $cntCancelled <= 20);
        }

        if ($this->ParsePastIts) {
            $this->logger->info("Parse info for past itineraries (total: {$cntPast})", ['Header' => 3]);

            foreach ($past as $activity) {
                $this->parseMinimalItinerary($activity, false);
            }
        } else {
            // cause not interest
            $cntPast = 0;
        }
        $this->logger->debug("cntSkippedPast " . $this->cntSkippedPast);
        $this->logger->debug("cntUpcoming " . $cntUpcoming);
        $this->logger->debug("cntCancelled " . $cntCancelled);
        $this->logger->debug("cntPast " . $cntPast);
        // NoItineraries
        if (!empty($activities) && count($this->itinerariesMaster->getItineraries()) === 0
            && $cntUpcoming + $cntCancelled + $cntPast === $this->cntSkippedPast
        ) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        if (empty($activities) && $this->http->FindPreg('/\{"data":\{"guest":\{"activitySummaryOptions":\{"guestActivitiesSummary":\[\],"__typename":"GuestActivitySummaryOptions"/')
        ) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        return [];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.hilton.com/en/book/reservation/find/";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->RetryCount = 0;
        //$this->http->setDefaultHeader("User-Agent", "Mozilla/5.0 (iPad; CPU OS 11_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.0 Mobile/15E148 Safari/604.1");
        //		$this->http->GetURL($this->ConfirmationNumberURL($arFields), [], 20);

        //$this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $result = $this->seleniumRetrieve($this->ConfirmationNumberURL($arFields), $arFields);

        return $result;

        $appId = $this->http->FindPreg('/"DX_AUTH_APP_CUSTOMER_ID":"(.+?)"/');

        if (!$appId) {
            return null;
        }
        $headers = [
            'Accept'       => '*/*',
            'Content-Type' => 'application/json',
            'Origin'       => 'https://www.hilton.com',
            'Referer'      => 'https://www.hilton.com/en/book/reservation/find/',
            'x-dtpc'       => '1$37565486_815h25vKCSHUWAPOCUHHKRRMKGGKNNMASVIHMSK-0e0',
        ];

        $data = [
            'app_id'      => $this->http->FindPreg('/"DX_AUTH_APP_CUSTOMER_ID":"(.+?)"/'),
            'confNumber'  => $arFields['ConfNo'],
            'arrivalDate' => date('Y-m-d', strtotime($arFields['ArrivalDate'])),
            'lastName'    => $arFields['LastName'],
        ];
        $this->http->PostURL('https://www.hilton.com/dx-customer/auth/reservations/reservationLogin', json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->error_description) && $response->error_description == 'Provided Authorization Grant is invalid') {
            return "Hmm, we can't find that reservation. Please check your details and try again.";
        }

//        $this->sendNotification('check retrieve // MI');
//        $this->parseItinerary($data);
//        $it = [];

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"           => "PostingDate",
            "Check-out Date" => "Info.Date",
            "Type"           => "Info",
            "Description"    => "Description",
            "Points Earned"  => "Miles",
            "Points"         => "Info",
            "Bonus"          => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');

        if (isset($startDate)) {
            $startDate = strtotime('-4 day', $startDate);
            $this->logger->debug('>> [set historyStartDate date -4 days]: ' . $startDate);
        }
        $startTimer = $this->getTime();

        $this->getHistory();
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($this->guestActivitiesSummary, $startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function parseCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);
        $key = $key ?? $this->http->FindPreg("/,\"RECAPTCHA_PUBLIC_KEY\":\"([^\"]+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        if ($key == 'close') {
            $this->DebugInfo = "captcha token not found";

            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(), //"https://www.hilton.com/en/auth/login/", //
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Something went wrong
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Something went wrong')]")) {
            throw new CheckException("Something went wrong. Maybe it’s us, maybe it’s you. (It’s probably us).", ACCOUNT_PROVIDER_ERROR);
        }
        //# Error processing request
        if ($message = $this->http->FindPreg("/difficulties and were unable to complete your request/ims")) {
            throw new CheckException("Hilton website had a hiccup, please try to check your balance at a later time.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Error processing request
        if ($message = $this->http->FindPreg("/difficulties and were unable to complete your request/ims")) {
            throw new CheckException("Unfortunately, we are experiencing technical difficulties and were unable to complete your request.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Due to technical difficulties
        if ($message = $this->http->FindPreg("/(Due to technical difficulties, we cannot access customer profiles at this tme)/ims")) {
            throw new CheckException("Due to technical difficulties, we cannot access customer profiles at this time", ACCOUNT_PROVIDER_ERROR);
        }
        //# Due to technical issues we cannot access your customer profile right now
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Due to technical issues we cannot access your customer profile right now')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Unfortunately, we are having technical difficulties and are unable to complete your request.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Unfortunately, we are having technical difficulties and are unable to complete your request.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our Apologies! We're currently working out our issues so we can be better for you.
        if ($message = $this->http->FindPreg("/Our Apologies! We\&\#39;re currently working out our issues so we can be better for you\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our Apologies! We're currently working out our issues so we can be better for you.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 're currently working out our issues so we can be better for you.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Errors
        if ($this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status')]")
            || $this->http->FindSingleNode("//h2[contains(text(), 'Error 404--Not Found')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            || $this->http->FindSingleNode("//p[contains(text(), 'The application is currently not serving requests at this endpoint. It may not have been started or is still starting.')]")
            || $this->http->FindPreg("/(Error 404--Not Found)/ims")
            || $this->http->FindPreg("/(Internal Server Error)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: " . $currentUrl);

        if (
            $currentUrl == 'https://secure3.hilton.com/en/hh/customer/login/index.htm'
            && $this->http->FindPreg('/(?:page isn’t working|There is no Internet connection|<head><\/head><body><pre style="word-wrap: break-word; white-space: pre-wrap;"><\/pre><\/body>)/ims')
        ) {
            throw new CheckRetryNeededException(3, 10);
        }

        return false;
    }

    public function detectDeadLine(Hotel $h)
    {
        if (empty($h->getCancellation())) {
            return;
        }

        // Free cancellation before 12 noon local hotel time on 27 Dec 2021
        // Free cancellation before 11:59 PM local hotel time on 24 May 2023.
        if (preg_match('/Free cancellation before (\d+(?::\d+\s*[AP]M|\s*noon)) local hotel time on (\d+ \w+ \d{4})/', $h->getCancellation(), $m)) {
            $m[1] = str_replace('12 noon', '12:00 AM', $m[1]);
            $h->booked()->deadlineRelative($m[2], $m[1]);
        } elseif ($this->http->FindPreg('/Free cancellation/', false, $h->getCancellation())) {
            $this->sendNotification('check deadline // MI');
        }
    }

    public function addHotelData(Hotel $hotel, $hotelData, $arrivalDate, $departureDate)
    {
        $this->logger->notice(__METHOD__);
        $hotelName = $this->arrayVal($hotelData, ['data', 'hotel', 'name']);

        if (!empty($hotelName)) {
            $hotelName = preg_replace("/\s+/", ' ', $hotelName);
        }
        // hotel name
        $hotel->setHotelName($hotelName);
        // address
        $hotel->setAddress($this->arrayVal($hotelData, ['data', 'hotel', 'address', 'addressFmt'], null)
            ?? $this->arrayVal($hotelData, ['data', 'hotel', 'address', 'addressStacked']));
        // check in time
        $checkinTimeFmt = $this->arrayVal($hotelData, ['data', 'hotel', 'checkin', 'checkinTimeFmt']);

        if ($checkinTimeFmt) {
            $hotel->setCheckInDate(strtotime($checkinTimeFmt, $hotel->getCheckInDate()));
        }
        // check out time
        $checkoutTimeFmt = $this->arrayVal($hotelData, ['data', 'hotel', 'checkin', 'checkoutTimeFmt']);

        if ($checkoutTimeFmt) {
            $hotel->setCheckOutDate(strtotime($checkoutTimeFmt, $hotel->getCheckOutDate()));
        }

        if ($arrivalDate == $departureDate && $hotel->getCheckOutDate() < $hotel->getCheckInDate()) {
            return true;
        }

        return false;
    }

    public function arrayVal($ar, $indices, $default = null)
    {
        $res = $ar;

        if (is_string($indices)) {
            $indices = [$indices];
        }

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return $default;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    public function ParsePageHistory($guestActivitiesSummary, $startIndex, $startDate)
    {
        $result = [];
        $this->logger->debug("Total " . ((is_array($guestActivitiesSummary) || ($guestActivitiesSummary instanceof Countable)) ? count($guestActivitiesSummary) : 0) . " history transactions were found");

        foreach ($guestActivitiesSummary as $transaction) {
            $dateStr = $transaction->arrivalDate;
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Check-out Date'] = strtotime($transaction->departureDate);
            $result[$startIndex]['Description'] = $transaction->descFmt;

            $parseDetails = true;
            $skipTransaction = false;

            switch ($transaction->guestActivityType) {
                case 'past':
                    $result[$startIndex]['Type'] = 'Points activity';

                    break;

                case 'cancelled':
                    $parseDetails = false;
                    $result[$startIndex]['Type'] = 'Cancellation ' . $transaction->cxlNumber;
                    $now = strtotime('-1 day', time());

                    if ($result[$startIndex]['Check-out Date'] > $now) {
                        $this->logger->info('skipping cancelled in the future');
                        $skipTransaction = true;
                    }

                    break;

                case 'other':
                    $parseDetails = false;
                    $result[$startIndex]['Date'] = $result[$startIndex]['Check-out Date'];
                    unset($result[$startIndex]['Check-out Date']);
                    $result[$startIndex]['Type'] = 'Points earned';

                    if ($transaction->totalPoints < 0) {
                        $result[$startIndex]['Type'] = 'Points used';
                    }

                    break;

                case 'upcoming':
                    $this->logger->notice("skip upcoming reservation: {$result[$startIndex]['Date']} / {$result[$startIndex]['Description']}");
                    unset($result[$startIndex]);
                    $skipTransaction = true;

                    break;

                default:
                    $this->sendNotification("new history type was found: {$transaction->guestActivityType}");

                    break;
            }

            if ($skipTransaction === true) {
                continue;
            }

            $result[$startIndex]['Points Earned'] = $transaction->totalPointsFmt;
//                $result[$startIndex]['Miles Earned'] = $this->http->FindSingleNode("td[6]", $nodes->item($i));
            $startIndex++;

            if ($parseDetails) {
                $transactionDetails = $transaction->transactions ?? [];

                foreach ($transactionDetails as $transactionDetail) {
                    $result[$startIndex]['Date'] = $postDate;
                    $result[$startIndex]['Type'] = 'Details';
                    $result[$startIndex]['Description'] = $transactionDetail->descriptionFmt;

                    if ($transactionDetail->guestActivityPointsType === "pointsUsed") {
                        $result[$startIndex]['Points'] = $transactionDetail->usedPointsFmt;
                    } else {
                        $result[$startIndex]['Points'] = $transactionDetail->basePointsFmt;
                    }
                    $result[$startIndex]['Bonus'] = $transactionDetail->bonusPointsFmt;
                    //                $result[$startIndex]['Miles'] = $this->http->FindSingleNode("td[5]", $nodes->item($i));
                    $startIndex++;
                }

                $roomDetails = $transaction->roomDetails ?? [];

                foreach ($roomDetails as $i => $room) {
                    $roomIndex = $i + 1;
                    $transactionDetails = $room->transactions ?? [];

                    foreach ($transactionDetails as $transactionDetail) {
                        $result[$startIndex]['Date'] = $postDate;
                        $result[$startIndex]['Type'] = 'Details';
                        $result[$startIndex]['Description'] = "Room {$roomIndex}: {$transactionDetail->descriptionFmt}";

                        if ($transactionDetail->guestActivityPointsType === "pointsUsed") {
                            $result[$startIndex]['Points'] = $transactionDetail->usedPointsFmt;
                        } else {
                            $result[$startIndex]['Points'] = $transactionDetail->basePointsFmt;
                        }
                        $result[$startIndex]['Bonus'] = $transactionDetail->bonusPointsFmt;
                        //                $result[$startIndex]['Miles'] = $this->http->FindSingleNode("td[5]", $nodes->item($i));
                        $startIndex++;
                    }
                }
            }
        }

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $wso2AuthToken = $this->http->JsonLog($this->http->getCookieByName("wso2AuthToken"));

        if (!isset($wso2AuthToken->accessToken) || !isset($wso2AuthToken->guestId)) {
            $this->logger->error("get brand guest failed: token or guest id not found");

            return false;
        }

        $headers = [
            "Authorization" => "{$wso2AuthToken->tokenType} {$wso2AuthToken->accessToken}",
            "Origin"        => "https://www.hilton.com",
            "Accept"        => "*/*",
            "Content-Type"  => "application/json",
        ];
        $data = '{"operationName":"guest","variables":{"guestId":' . $wso2AuthToken->guestId . ',"language":"en"},"query":"query guest($guestId: BigInt!, $language: String!) {\n  guest(guestId: $guestId, language: $language) {\n    id: guestId\n    guestId\n    personalinfo {\n      name {\n        firstName @toTitleCase\n        __typename\n      }\n      emails {\n        validated\n        __typename\n      }\n      phones {\n        validated\n        __typename\n      }\n      hasUSAddress: hasAddressWithCountry(countryCodes: [\"US\"])\n      __typename\n    }\n    hhonors {\n      hhonorsNumber\n      isTeamMember\n      isLifetimeDiamond\n      isOwner\n      isOwnerHGV\n      isAmexCardHolder\n      summary {\n        tier\n        tierName\n        nextTier\n        requalTier\n        pointsExpiration\n        tierExpiration\n        nextTierName\n        totalPointsFmt\n        qualifiedNights\n        qualifiedNightsNext\n        qualifiedPoints\n        qualifiedPointsNext\n        qualifiedPointsFmt\n        qualifiedPointsNextFmt\n        qualifiedNightsMaint\n        rolledOverNights\n        showRequalMaintainMessage\n        showRequalDowngradeMessage\n        milestones {\n          applicableNights\n          bonusPoints\n          bonusPointsFmt\n          bonusPointsNext\n          bonusPointsNextFmt\n          maxBonusPoints\n          maxBonusPointsFmt\n          maxNights\n          nightsNext\n          showMilestoneBonusMessage\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->PostURL("https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest", $data, $headers);

        if (
            $this->http->Response['code'] === 503
            || strstr($this->http->Error, 'Network error 52 - Empty reply from server')
        ) {
            sleep(5);
            /*
            $data = '{"operationName":"guest_hotel_MyAccount","variables":{"guestId":' . $wso2AuthToken->guestId . ',"language":"en"},"query":"query guest_hotel_MyAccount($guestId: BigInt!, $language: String!) {\n  guest(guestId: $guestId, language: $language) {\n    id: guestId\n    guestId\n    personalinfo {\n      name {\n        firstName @toTitleCase\n        __typename\n      }\n      emails {\n        validated\n        __typename\n      }\n      phones {\n        validated\n        __typename\n      }\n      hasUSAddress: hasAddressWithCountry(countryCodes: [\"US\"])\n      __typename\n    }\n    hhonors {\n      hhonorsNumber\n      isTeamMember\n      isLifetimeDiamond\n      isOwner\n      isOwnerHGV\n      isAmexCardHolder\n      summary {\n        tier\n        tierName\n        nextTier\n        requalTier\n        pointsExpiration\n        tierExpiration\n        nextTierName\n        totalPointsFmt\n        qualifiedNights\n        qualifiedNightsNext\n        qualifiedPoints\n        qualifiedPointsNext\n        qualifiedPointsFmt\n        qualifiedPointsNextFmt\n        qualifiedNightsMaint\n        rolledOverNights\n        showRequalMaintainMessage\n        showRequalDowngradeMessage\n        milestones {\n          applicableNights\n          bonusPoints\n          bonusPointsFmt\n          bonusPointsNext\n          bonusPointsNextFmt\n          maxBonusPoints\n          maxBonusPointsFmt\n          maxNights\n          nightsNext\n          showMilestoneBonusMessage\n          __typename\n        }\n        __typename\n      }\n      amexCoupons {\n        _available {\n          totalSize\n          __typename\n        }\n        _held {\n          totalSize\n          __typename\n        }\n        _used {\n          totalSize\n          __typename\n        }\n        available(sort: {by: startDate, order: asc}) {\n          ...GuestHHonorsAmexCoupon\n          __typename\n        }\n        held {\n          ...GuestHHonorsAmexCoupon\n          __typename\n        }\n        used {\n          ...GuestHHonorsAmexCoupon\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment GuestHHonorsAmexCoupon on GuestHHonorsDetailCoupon {\n  checkInDate\n  checkOutDate\n  codeMasked\n  checkOutDateFmt(language: $language)\n  endDate\n  endDateFmt(language: $language)\n  location\n  numberOfNights\n  offerName\n  points\n  rewardType\n  startDate\n  status\n  hotel {\n    name\n    images {\n      master(imageVariant: honorsPropertyImageThumbnail) {\n        url\n        altText\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n"}';
            $this->http->PostURL("https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest_hotel_MyAccount", $data, $headers);
            */
            $this->http->PostURL("https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest", $data, $headers);
        }

        $data = $this->http->JsonLog();

        if ($data->data->guest->hhonors->hhonorsNumber ?? null) {
            return true;
        }

        return false;
    }

    private function delay()
    {
        $delay = rand(1, 10);
        $this->logger->debug("Delay -> {$delay}");
        sleep($delay);
    }

    private function getBrandGuest()
    {
        $this->logger->notice(__METHOD__);
        $wso2AuthToken = $this->http->JsonLog($this->http->getCookieByName("wso2AuthToken"));

        if (!isset($wso2AuthToken->accessToken) || !isset($wso2AuthToken->guestId)) {
            $this->logger->error("get brand guest failed: token or guest id not found");

            return [];
        }
        $headers = [
            "Authorization" => "{$wso2AuthToken->tokenType} {$wso2AuthToken->accessToken}",
            "Origin"        => "https://www.hilton.com",
            "Accept"        => "*/*",
            "Content-Type"  => "application/json",
        ];
        $payload = '{"operationName":"guest_hotel_MyAccount","variables":{"guestId":' . $wso2AuthToken->guestId . ',"language":"en"},"query":"query guest_hotel_MyAccount($guestId: BigInt!, $language: String!) {\n  guest(guestId: $guestId, language: $language) {\n    id: guestId\n    guestId\n    personalinfo {\n      name {\n        firstName @toTitleCase\n        __typename\n      }\n      emails {\n        validated\n        __typename\n      }\n      phones {\n        validated\n        __typename\n      }\n      hasUSAddress: hasAddressWithCountry(countryCodes: [\"US\"])\n      __typename\n    }\n    hhonors {\n      hhonorsNumber\n      isTeamMember\n      isLifetimeDiamond\n      isOwner\n      isOwnerHGV\n      isAmexCardHolder\n      summary {\n        tier\n        tierName\n        nextTier\n        requalTier\n        pointsExpiration\n        tierExpiration\n        nextTierName\n        totalPointsFmt\n        qualifiedNights\n        qualifiedNightsNext\n        qualifiedPoints\n        qualifiedPointsNext\n        qualifiedPointsFmt\n        qualifiedPointsNextFmt\n        qualifiedNightsMaint\n        rolledOverNights\n        showRequalMaintainMessage\n        showRequalDowngradeMessage\n        milestones {\n          applicableNights\n          bonusPoints\n          bonusPointsFmt\n          bonusPointsNext\n          bonusPointsNextFmt\n          maxBonusPoints\n          maxBonusPointsFmt\n          maxNights\n          nightsNext\n          showMilestoneBonusMessage\n          __typename\n        }\n        __typename\n      }\n      amexCoupons {\n        _available {\n          totalSize\n          __typename\n        }\n        _held {\n          totalSize\n          __typename\n        }\n        _used {\n          totalSize\n          __typename\n        }\n        available(sort: {by: startDate, order: asc}) {\n          ...GuestHHonorsAmexCoupon\n          __typename\n        }\n        held {\n          ...GuestHHonorsAmexCoupon\n          __typename\n        }\n        used {\n          ...GuestHHonorsAmexCoupon\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment GuestHHonorsAmexCoupon on GuestHHonorsDetailCoupon {\n  checkInDate\n  checkOutDate\n  codeMasked\n  checkOutDateFmt(language: $language)\n  endDate\n  endDateFmt(language: $language)\n  location\n  numberOfNights\n  offerName\n  points\n  rewardType\n  startDate\n  status\n  hotel {\n    name\n    images {\n      master(imageVariant: honorsPropertyImageThumbnail) {\n        url\n        altText\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n"}';
        $this->http->PostURL('https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest_hotel_MyAccount', $payload, $headers);

        if (
            $this->http->Response['code'] === 503
            || strstr($this->http->Error, 'Network error 52 - Empty reply from server')
        ) {
            sleep(5);
            $this->http->PostURL('https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest_hotel_MyAccount', $payload, $headers);
        }

        return $this->http->JsonLog();
    }

    private function getHistory()
    {
        $this->logger->notice(__METHOD__);

        if (!empty($this->guestActivitiesSummary)) {
            return $this->guestActivitiesSummary;
        }

        $wso2AuthToken = $this->http->JsonLog($this->http->getCookieByName("wso2AuthToken"));

        if (!isset($wso2AuthToken->accessToken) || !isset($wso2AuthToken->guestId)) {
            $this->logger->error("get history failed: token or guest id not found");

            return [];
        }

        $headers = [
            "Authorization" => "{$wso2AuthToken->tokenType} {$wso2AuthToken->accessToken}",
            "Origin"        => "https://www.hilton.com",
            "Accept"        => "*/*",
            "Content-Type"  => "application/json",
        ];
        $startDate = date("Y-m-d", strtotime("-1 year"));
        $endDate = date("Y-m-d", strtotime("+1 year"));
        $data = '{"query":"query guest_guestActivitySummaryOptions_hotel($guestId: BigInt!, $language: String!, $startDate: String!, $endDate: String!, $guestActivityTypes: [GuestActivityType], $sort: [StayHHonorsActivitySummarySortInput!], $first: Int, $after: String, $guestActivityDisplayType: GuestActivityDisplayType) {\n  guest(guestId: $guestId, language: $language) {\n    id: guestId\n    guestId\n    hhonors {\n      summary {\n        tierName\n        totalPoints\n      }\n    }\n    activitySummaryOptions(\n      input: {groupMultiRoomStays: true, startDate: $startDate, endDate: $endDate, guestActivityTypes: $guestActivityTypes, guestActivityDisplayType: $guestActivityDisplayType}\n    ) {\n      _guestActivitiesSummary {\n        totalSize\n        size\n        start\n        end\n        nextCursor\n        prevCursor\n      }\n      guestActivitiesSummary(sort: $sort, first: $first, after: $after) {\n        ...StayActivitySummary\n      }\n    }\n  }\n}\n\n      \n    fragment StayActivitySummary on StayHHonorsActivitySummary {\n  numRooms\n  _id\n  stayId\n  arrivalDate\n  departureDate\n  hotelName\n  desc\n  descFmt: desc @toTitleCase\n  guestActivityType\n  ctyhocn\n  brandCode\n  roomDetails(sort: {by: roomSeries, order: asc}) {\n    ...StayRoomDetails\n    transactions {\n      ...StayTransaction\n    }\n  }\n  transactions {\n    ...StayTransaction\n  }\n  bookAgainUrl\n  checkinUrl\n  confNumber\n  cxlNumber\n  timeframe\n  lengthOfStay\n  viewFolioUrl\n  viewOrEditReservationUrl\n  earnedPoints\n  earnedPointsFmt\n  totalPoints\n  totalPointsFmt\n  usedPoints\n  usedPointsFmt\n}\n    \n    fragment StayRoomDetails on StayHHonorsActivityRoomDetail {\n  _id\n  bonusPoints\n  bonusPointsFmt\n  cxlNumber\n  guestActivityType\n  roomSeries\n  roomTypeName\n  roomTypeNameFmt: roomTypeName @truncate(byWords: true, length: 3)\n  bookAgainUrl\n  usedPointsFmt(language: $language)\n  transactions {\n    transactionId\n    transactionType\n    partnerName\n    baseEarningOption\n    guestActivityPointsType\n    description\n    descriptionFmt: description @toTitleCase\n    basePoints\n    basePointsFmt\n    bonusPoints\n    bonusPointsFmt\n    status\n    usedPointsFmt(language: $language)\n  }\n  totalPoints\n  totalPointsFmt(language: $language)\n  viewFolioUrl(type: link)\n}\n    \n\n    fragment StayTransaction on StayHHonorsTransaction {\n  transactionId\n  transactionType\n  partnerName\n  baseEarningOption\n  guestActivityPointsType\n  description\n  descriptionFmt: description @toTitleCase\n  basePoints\n  basePointsFmt\n  bonusPoints\n  bonusPointsFmt\n  earnedPoints\n  earnedPointsFmt\n  status\n  usedPoints\n  usedPointsFmt(language: $language)\n  hiltonForBusiness {\n    _id\n    h4bFlag\n    h4bName\n  }\n}\n    ","operationName":"guest_guestActivitySummaryOptions_hotel","variables":{"guestId":'.$wso2AuthToken->guestId.',"language":"en","startDate":"'.$startDate.'","endDate":"'.$endDate.'","after":"","first":100,"guestActivityDisplayType":"bankStatement","guestActivityTypes":["cancelled","past","upcoming","other"]}}';
        $this->increaseTimeLimit();
        $this->http->PostURL("https://www.hilton.com/graphql/customer?appName=dx_guests_app&operationName=guest_guestActivitySummaryOptions_hotel&originalOpName=guest_guestActivitySummaryOptions_hotel&bl=en", $data, $headers);
        $this->http->RetryCount = 0;

        if (
            $this->http->Response['code'] === 503
            || strstr($this->http->Error, 'Network error 52 - Empty reply from server')
        ) {
            sleep(5);
            $this->http->PostURL("https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest_guestActivitySummaryOptions", $data, $headers);
        }

        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, 'guestActivitiesSummary');
        $this->guestActivitiesSummary = $response->data->guest->activitySummaryOptions->guestActivitiesSummary ?? [];

        return $this->guestActivitiesSummary;
    }

    private function parseItinerary($data): void
    {
        $this->logger->notice(__METHOD__);
        $reservation = $this->arrayVal($data, ['data', 'reservation']);

        if (!$reservation) {
            $this->sendNotification('check parse itinerary');

            return;
        }
        $departureDate = $reservation['departureDate'] ?? '';
        $isPast = strtotime($departureDate) < strtotime('-1 day', time());

        if ($isPast && !$this->ParsePastIts) {
            $this->logger->info('Skipping hotel: in the past');
            $this->cntSkippedPast++;

            return;
        }
        $arrivalDate = $reservation['arrivalDate'] ?? '';
        /*
        if ($arrivalDate && $arrivalDate === $departureDate) {
            $this->logger->error('Skipping hotel: the same arrival / departure dates');
            $this->cntSkippedPast++;
            return;
        }
        */

        $hotel = $this->itinerariesMaster->createHotel();
        // confirmation number
        $conf = $reservation['confNumber'] ?? null;
        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$conf}", ['Header' => 3]);
        $this->currentItin++;
        $hotel->addConfirmationNumber($conf, 'Confirmation number', true);
        // check in date
        $hotel->setCheckInDate(strtotime($arrivalDate));
        // check out date
        $hotel->setCheckOutDate(strtotime($departureDate));
        // cancellation policy
        $hotel->setCancellation($this->arrayVal($reservation, ['disclaimer', 'hhonorsCancellationCharges']), false, true);

        if ($this->arrayVal($reservation, ['cost', 'totalTaxes']) && strpos($this->arrayVal($reservation, ['cost', 'totalTaxes']), '-') === false) {
            // total
            $hotel->obtainPrice()->setTotal($this->arrayVal($reservation, ['cost', 'totalAmountBeforeTax']));
            // tax
            $hotel->obtainPrice()->setTax($this->arrayVal($reservation, ['cost', 'totalTaxes']));
            // currency
            $hotel->obtainPrice()->setCurrencyCode($this->arrayVal($reservation, ['cost', 'currency', 'currencyCode']));
            // spent awards
            $hotel->obtainPrice()->setSpentAwards($this->arrayVal($reservation, ['certificates', 'totalPointsFmt']), false, true);
        }
        // rooms
        foreach (ArrayVal($reservation, 'rooms', []) as $key => $roomData) {
            $room = $hotel->addRoom();
            $rateDetails = $this->arrayVal($roomData, ['cost', 'rateDetails']);

            foreach ($rateDetails as $rateDetail) {
                // TODO: Different number of rates for each room, because of this an error occurs
                if ($key > 0 && count($hotel->getRooms()[0]->getRates()) != count($rateDetails)) {
                    continue;
                }
                $room->addRate($this->arrayVal($rateDetail, ['rateAmountFmt']));
            }

            // type
            $room->setType($this->arrayVal($roomData, ['roomType', 'roomTypeName']));
            // description
            $desc = $this->arrayVal($roomData, ['roomType', 'roomTypeDesc']);

            if ($desc) {
                $desc = preg_replace('/\s+/', ' ', strip_tags($desc));
                $room->setDescription($desc ? trim($desc) : null, false, true);
            }
            // cancellation policy
            $cancelation = $this->arrayVal($roomData, ['guarantee', 'cxlPolicyDesc']);

            if (empty($hotel->getCancellation()) && !empty($cancelation)) {
                $hotel->setCancellation($cancelation);
            }
        }

        $hotel->parseNonRefundable('/If you cancel for any reason, attempt to modify this reservation, or do not arrive on your specified check-in date, your payment is non-refundable/');
        // Deadline
        $this->detectDeadLine($hotel);
        // guest count
        $hotel->setGuestCount($reservation['totalNumAdults'] ?? null);
        // kids count
        $hotel->setKidsCount($reservation['totalNumChildren'] ?? null, false, true);
        // hotel name
        $propCode = $reservation['propCode'] ?? null;
        $hotelData = $this->getHotelData($propCode);

        if ($hotelData) {
            $skip = $this->addHotelData($hotel, $hotelData, $arrivalDate, $departureDate);

            if ($skip) {
                $this->logger->error('Skipping hotel: the same arrival / departure dates');
                $this->itinerariesMaster->removeItinerary($hotel);
                $this->cntSkippedPast++;

                return;
            }
        }

        $this->logger->info('Parsed Hotel:');
        $this->logger->info(var_export($hotel->toArray(), true), ['pre' => true]);
    }

    private function parseMinimalItinerary($activity, ?bool $withDetails = true)
    {
        $this->logger->notice(__METHOD__);

        if (!$activity) {
            $this->sendNotification('check parse minimal itinerary');

            return;
        }
        $departureDate = ArrayVal($activity, 'departureDate');
        $isPast = strtotime($departureDate) < strtotime('-1 day');

        if ($isPast && !$this->ParsePastIts) {
            $this->logger->info('Skipping hotel: in the past');
            $this->cntSkippedPast++;

            return;
        }
        $cancelled = null;
        // cancelled
        if (ArrayVal($activity, 'guestActivityType') === 'cancelled') {
            $cancelled = true;
        }
        $arrivalDate = ArrayVal($activity, 'arrivalDate');

        if ($arrivalDate && $arrivalDate === $departureDate) {
            $this->logger->error('Skipping hotel: the same arrival / departure dates');

            if ($cancelled) {
                $this->cntSkippedPast++;
            }

            return;
        }
        $propCode = $this->http->FindPreg('/ctyhocn=(\w+)/', false, ArrayVal($activity, 'bookAgainUrl'));
        $rooms = ArrayVal($activity, 'roomDetails', []);

        if (!$propCode) {
            $propCodes = [];

            foreach ($rooms as $room) {
                $propCodes[] = $this->http->FindPreg('/ctyhocn=(\w+)/', false, ArrayVal($room, 'bookAgainUrl'));
            }
            $propCodes = array_unique($propCodes);

            if (count($propCodes) === 1) {
                $propCode = array_shift($propCodes);
            }
        }

        if (!$propCode && !$cancelled) {
            $this->logger->error('Skipping hotel: property code is missing');

            return;
        }
        $hotel = $this->itinerariesMaster->createHotel();

        if (isset($cancelled)) {
            $hotel->setCancelled(true);
        }
        // check in date
        $hotel->setCheckInDate(strtotime($arrivalDate));
        // check out date
        $hotel->setCheckOutDate(strtotime($departureDate));

        if ($propCode && $withDetails) {
            // hotel name, address, check in / check out times
            $hotelData = $this->getHotelData($propCode);

            if ($hotelData) {
                $skip = $this->addHotelData($hotel, $hotelData, $arrivalDate, $departureDate);

                if ($skip) {
                    $this->logger->error('Skipping hotel: the same arrival / departure dates');
                    $this->cntSkippedPast++;

                    return;
                }
            }
        } else {
            $hotel->hotel()
                ->name(ArrayVal($activity, 'hotelName'))
                ->noAddress();
            $cxlNumber = [];

            foreach ($rooms as $room) {
                $r = $hotel->addRoom();
                $r->setType(ArrayVal($room, 'roomTypeName'));
                $cxlNumber[] = ArrayVal($room, 'cxlNumber');
            }
            $cxlNumber = array_values(array_unique($cxlNumber));

            if (!empty($cxlNumber) && !empty($cxlNumber[0])) {
                $hotel->general()->cancellationNumber($cxlNumber[0]);
            }
        }

        // confirmation number
        $conf = ArrayVal($activity, 'confNumber');
        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$conf}", ['Header' => 3]);
        $this->currentItin++;
        $hotel->addConfirmationNumber($conf, 'Confirmation number', true);
        // spent awards
        $usedPoints = (int) (ArrayVal($activity, 'usedPoints', 0));

        if ($usedPoints) {
            $hotel->obtainPrice()->setSpentAwards(ArrayVal($activity, 'usedPointsFmt'));
        }

        $this->logger->info('Parsed Hotel:');
        $this->logger->info(var_export($hotel->toArray(), true), ['pre' => true]);
    }

    private function getReservationData($activity)
    {
        $this->logger->notice(__METHOD__);
        $confNumber = ArrayVal($activity, 'confNumber');
        $lastName = $this->http->FindPreg('/lastName=(.+)/', false, ArrayVal($activity, 'viewOrEditReservationUrl'));

        if (!$lastName) {
            $this->logger->error('lastName is missing');

            return null;
        }
        $arrivalDate = ArrayVal($activity, 'arrivalDate');
        $wso2AuthToken = $this->http->JsonLog($this->http->getCookieByName("wso2AuthToken"), 3, true) ?: [];
        $guestId = ArrayVal($wso2AuthToken, 'guestId');

        if (!$guestId) {
            $this->logger->error('guestId missing');

            return null;
        }
        $token = ArrayVal($wso2AuthToken, 'accessToken');

        if (!$token) {
            $this->logger->error('auth token missing');

            return null;
        }
        $auth = "Bearer {$token}";
        $this->sendReservationRequest($auth, $confNumber, $lastName, $arrivalDate, $guestId);

        $result = $this->http->JsonLog(null, 3, true);

        $message = $result->errors[0]->message ?? null;

        if ($message && in_array($message, ['Gateway Timeout', 'Service Unavailable'])) {
            sleep(5);
            $this->logger->error("[Retrying]: {$message}");
            $this->sendNotification("Gateway Timeout / Service Unavailable // RR");
            $this->sendReservationRequest($auth, $confNumber, $lastName, $arrivalDate, $guestId);
            $result = $this->http->JsonLog(null, 3, true);
        }

        return $result;
    }

    private function getReservationDataRetrieve($confNumber, $lastName, $arrivalDate)
    {
        $this->logger->notice(__METHOD__);
        //$arrivalDate = $this->http->FindPreg('/arrivaldate=([\d-]+)/', false, $this->http->currentUrl());

        if (!$arrivalDate) {
            $this->logger->error('arrival date is missing');

            return null;
        }

        if ($appId = $this->http->FindPreg("/\"DX_AUTH_APP_CUSTOMER_ID\"\s*:\s*\"([\w\-]+)\"/")) {
            $headers = [
                "Origin"       => "https://www.hilton.com",
                "Accept"       => "*/*",
                "Content-Type" => "application/json",
            ];
            $payload = [
                'app_id'      => $appId,
                'arrivalDate' => $arrivalDate,
                'confNumber'  => $confNumber,
                'lastName'    => $lastName,
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.hilton.com/dx-customer/auth/reservations/reservationLogin',
                json_encode($payload),
                $headers);
            $this->http->RetryCount = 2;

            if ($this->http->FindPreg('/"Provided Authorization Grant is invalid"/')) {
                return "Hmm, we can't find that reservation. Please check your details and try again.";
            }
            $data = $this->http->JsonLog(null, 3, true);
            $auth = $data['token_type'] . ' ' . $data['access_token'];
        }

        if (empty($auth)) {
            $this->logger->error('Empty authorization header');

            return null;
            //$auth = "Basic c1Y3QTdZY0NQVHFLblc2c2s5WVp4RXJwa2JnYTprQnhEclJuUGE4eTZfVGZQTHc4QzB5Qk4waHNh";
        }
        $this->http->RetryCount = 0;
        $this->sendReservationRequest($auth, $confNumber, $lastName, $arrivalDate, 'null');
        $this->http->RetryCount = 2;

        return $this->http->JsonLog(null, 3, true, 'message');
    }

    private function sendReservationRequest($auth, $confNumber, $lastName, $arrivalDate, $guestId)
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Authorization" => $auth,
            "Origin"        => "https://www.hilton.com",
            "Accept"        => "*/*",
            "Content-Type"  => "application/json",
        ];

        if (isset($this->http->Response['headers']['hltclientmessageid'])) {
            $headers['hltclientmessageid'] = $this->http->Response['headers']['hltclientmessageid'];
        }

        $payload = '{"operationName":"reservation","variables":{"confNumber":"' . $confNumber . '","language":"en","guestId":' . $guestId . ',"lastName":"' . $lastName . '","arrivalDate":"' . $arrivalDate . '"},"query":"query reservation($confNumber: String!, $language: String!, $guestId: BigInt, $lastName: String!, $arrivalDate: String!) {\n  reservation(\n    confNumber: $confNumber\n    language: $language\n    authInput: {guestId: $guestId, lastName: $lastName, arrivalDate: $arrivalDate}\n  ) {\n    ...RESERVATION_FRAGMENT\n    __typename\n  }\n}\n\nfragment RESERVATION_FRAGMENT on Reservation {\n  addOnsResModifyEligible\n  confNumber\n  arrivalDate\n  departureDate\n  cancelEligible\n  modifyEligible\n  cxlNumber\n  restricted\n  adjoiningRoomStay\n  adjoiningRoomsFailure\n  scaRequired\n  autoUpgradedStay\n  showAutoUpgradeIndicator\n  specialRateOptions {\n    corporateId\n    groupCode\n    hhonors\n    pnd\n    promoCode\n    travelAgent\n    familyAndFriends\n    teamMember\n    owner\n    ownerHGV\n    __typename\n  }\n  clientAccounts {\n    clientId\n    clientType\n    clientName\n    __typename\n  }\n  comments {\n    generalInfo\n    __typename\n  }\n  disclaimer {\n    diamond48\n    fullPrePayNonRefundable\n    hgfConfirmation\n    hgvMaxTermsAndConditions\n    hhonorsCancellationCharges\n    hhonorsPointsDeduction\n    hhonorsPrintedConfirmation\n    lengthOfStay\n    rightToCancel\n    totalRate\n    teamMemberEligibility\n    vatCharge\n    __typename\n  }\n  certificates {\n    totalPoints\n    totalPointsFmt\n    __typename\n  }\n  cost {\n    currency {\n      currencyCode\n      currencySymbol\n      description\n      __typename\n    }\n    roomRevUSD: totalAmountBeforeTax(currencyCode: \"USD\")\n    totalAddOnsAmount\n    totalAddOnsAmountFmt\n    totalAmountBeforeTax\n    totalAmountAfterTaxFmt: guestTotalCostAfterTaxFmt\n    totalAmountAfterTax: guestTotalCostAfterTax\n    totalAmountBeforeTaxFmt\n    totalServiceCharges\n    totalServiceChargesFmt\n    totalTaxes\n    totalTaxesFmt\n    __typename\n  }\n  foodAndBeverageCreditBenefit {\n    description\n    heading\n    linkLabel\n    linkUrl\n    __typename\n  }\n  guarantee {\n    cxlPolicyCode\n    cxlPolicyDesc\n    guarPolicyCode\n    guarPolicyDesc\n    guarMethodCode\n    taxDisclaimers {\n      text\n      title\n      __typename\n    }\n    disclaimer {\n      legal\n      __typename\n    }\n    paymentCard {\n      cardCode\n      cardName\n      cardNumber\n      cardExpireDate\n      expireDate: cardExpireDateFmt(format: \"MMM yyyy\")\n      expireDateFull: cardExpireDateFmt(format: \"MMMM yyyy\")\n      expired\n      policy {\n        bankValidationMsg\n        __typename\n      }\n      __typename\n    }\n    deposit {\n      amount\n      __typename\n    }\n    taxDisclaimers {\n      text\n      title\n      __typename\n    }\n    __typename\n  }\n  guest {\n    guestId\n    tier\n    name {\n      firstName\n      lastName\n      nameFmt\n      __typename\n    }\n    emails {\n      emailAddress\n      emailType\n      __typename\n    }\n    addresses {\n      addressLine1\n      addressLine2\n      city\n      country\n      state\n      postalCode\n      addressFmt\n      addressType\n      __typename\n    }\n    hhonorsNumber\n    phones {\n      phoneNumber\n      phoneType\n      __typename\n    }\n    __typename\n  }\n  propCode\n  nor1Upgrade(provider: \"DOHWR\") {\n    content {\n      button\n      description\n      firstName\n      title\n      __typename\n    }\n    offerLink\n    requested\n    success\n    __typename\n  }\n  notifications {\n    subType\n    text\n    type\n    __typename\n  }\n  requests {\n    specialRequests {\n      pets\n      servicePets\n      __typename\n    }\n    __typename\n  }\n  rooms {\n    gnrNumber\n    resCreateDateFmt(format: \"yyyy-MM-dd\")\n    addOns {\n      addOnCost {\n        amountAfterTax\n        amountAfterTaxFmt\n        __typename\n      }\n      addOnDetails {\n        addOnAvailType\n        addOnDescription\n        addOnCode\n        addOnName\n        amountAfterTax\n        amountAfterTaxFmt\n        averageDailyRate\n        averageDailyRateFmt\n        categoryCode\n        counts {\n          numAddOns\n          fulfillmentDate\n          rate\n          rateFmt\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    additionalNames {\n      firstName\n      lastName\n      __typename\n    }\n    certificates {\n      certNumber\n      totalPoints\n      totalPointsFmt\n      __typename\n    }\n    numAdults\n    numChildren\n    childAges\n    autoUpgradedStay\n    isStayUpsell\n    isStayUpsellOverAutoUpgrade\n    priorRoomType {\n      roomTypeName\n      __typename\n    }\n    cost {\n      currency {\n        currencyCode\n        currencySymbol\n        description\n        __typename\n      }\n      amountAfterTax: guestTotalCostAfterTax\n      amountAfterTaxFmt: guestTotalCostAfterTaxFmt\n      amountBeforeTax\n      amountBeforeTaxFmt\n      amountBeforeTaxFmtTrunc: amountAfterTaxFmt(decimal: 0, strategy: trunc)\n      serviceChargeFeeType\n      serviceChargePeriods {\n        serviceCharges {\n          amount\n          amountFmt\n          description\n          __typename\n        }\n        __typename\n      }\n      totalServiceCharges\n      totalServiceChargesFmt\n      totalTaxes\n      totalTaxesFmt\n      rateDetails(perNight: true) {\n        effectiveDateFmt(format: \"medium\")\n        effectiveDateFmtAda: effectiveDateFmt(format: \"long\")\n        rateAmount\n        rateAmountFmt\n        rateAmountFmtTrunc: rateAmountFmt(decimal: 0, strategy: trunc)\n        __typename\n      }\n      upgradedAmount\n      upgradedAmountFmt\n      __typename\n    }\n    guarantee {\n      cxlPolicyCode\n      cxlPolicyDesc\n      guarPolicyCode\n      guarPolicyDesc\n      __typename\n    }\n    numAdults\n    numChildren\n    ratePlan {\n      confidentialRates\n      hhonorsMembershipRequired\n      advancePurchase\n      promoCode\n      disclaimer {\n        diamond48\n        fullPrePayNonRefundable\n        hhonorsCancellationCharges\n        hhonorsPointsDeduction\n        hhonorsPrintedConfirmation\n        lengthOfStay\n        rightToCancel\n        totalRate\n        __typename\n      }\n      ratePlanCode\n      ratePlanName\n      ratePlanDesc\n      specialRateType\n      serviceChargesAndTaxesIncluded\n      __typename\n    }\n    roomType {\n      adaAccessibleRoom\n      roomTypeCode\n      roomTypeName\n      roomTypeDesc\n      roomOccupancy\n      __typename\n    }\n    __typename\n  }\n  taxPeriods {\n    taxes {\n      description\n      __typename\n    }\n    __typename\n  }\n  paymentOptions {\n    cardOptions {\n      policy {\n        bankValidationMsg\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  totalNumAdults\n  totalNumChildren\n  totalNumRooms\n  unlimitedRewardsNumber\n  __typename\n}\n"}';
        $itinUrl = 'https://www.hilton.com/graphql/customer?appName=dx-reservations-ui&language=en&operationName=reservation';
        $this->http->PostURL($itinUrl, $payload, $headers);
    }

    private function getHotelData($propCode): ?array
    {
        $this->logger->notice(__METHOD__);

        if (!$propCode) {
            $this->logger->error('hotel property code is missing');

            return null;
        }

        $wso2AuthToken = $this->http->JsonLog($this->http->getCookieByName("wso2AuthToken"), 3, true) ?: [];
        $token = ArrayVal($wso2AuthToken, 'accessToken');
        $tokenType = ArrayVal($wso2AuthToken, 'tokenType');

        if (!$token || !$tokenType) {
            $this->logger->error('auth token missing');

            return null;
        }
        $headers = [
            "Authorization" => "{$tokenType} {$token}",
            "Origin"        => "https://www.hilton.com",
            "Accept"        => "*/*",
            "Content-Type"  => "application/json",
        ];
        $payload = '{"operationName":"brand_hotel_shopAvailOptions","variables":{"language":"en","ctyhocn":"' . $propCode . '"},"query":"query brand_hotel_shopAvailOptions($language: String!, $ctyhocn: String!) {\n  hotel(ctyhocn: $ctyhocn, language: $language) {\n    ctyhocn\n    brandCode\n    contactInfo {\n      phoneNumber\n      __typename\n    }\n    display {\n      preOpenMsg\n      open\n      resEnabled\n      __typename\n    }\n    creditCardTypes {\n      guaranteeType\n      code\n      name\n      __typename\n    }\n    address {\n      addressFmt(format: \"stacked\")\n      countryName\n      country\n      state\n      mapCity\n      __typename\n    }\n    brand {\n      formalName\n      name\n      phone {\n        supportNumber\n        supportIntlNumber\n        __typename\n      }\n      url\n      searchOptions {\n        url\n        __typename\n      }\n      __typename\n    }\n    localization {\n      currency {\n        currencyCode\n        currencySymbol\n        description\n        __typename\n      }\n      __typename\n    }\n    overview {\n      resortFeeDisclosureDesc\n      __typename\n    }\n    name\n    propCode\n    shopAvailOptions {\n      maxArrivalDate\n      maxDepartureDate\n      minArrivalDate\n      minDepartureDate\n      maxNumOccupants\n      maxNumChildren\n      ageBasedPricing\n      adultAge\n      adjoiningRooms\n      __typename\n    }\n    hotelAmenities: amenities(filter: {groups_includes: [hotel]}) {\n      id\n      name\n      __typename\n    }\n    stayIncludesAmenities: amenities(\n      filter: {groups_includes: [stay]}\n      useBrandNames: true\n    ) {\n      id\n      name\n      __typename\n    }\n    images {\n      master(imageVariant: bookPropertyImageThumbnail) {\n        _id\n        altText\n        variants {\n          size\n          url\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    familyPolicy\n    registration {\n      checkinTimeFmt(language: $language)\n      checkoutTimeFmt(language: $language)\n      earlyCheckinText\n      __typename\n    }\n    pets {\n      description\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $hotelUrl = 'https://www.hilton.com/graphql/customer?appName=dx-reservations-ui&ctyhocn=TTNHL&language=en&operationName=brand_hotel_shopAvailOptions';
        $this->increaseTimeLimit();
        $this->http->PostURL($hotelUrl, $payload, $headers);

        if ($error = $this->http->FindPreg("/The server didn't respond in time./")) {
            $this->setProxyGoProxies();
            $this->logger->error("Retrying: {$error}");
            sleep(2);
            $this->increaseTimeLimit();
            $this->http->PostURL($hotelUrl, $payload, $headers);
            $this->sendNotification('check proxy 503 // MI');
        }

        return $this->http->JsonLog(null, 3, true);
    }

    private function parseFreeNightRewards($coupons, $reserved = false)
    {
        $displayNameDescription = '';

        if ($reserved === true) {
            $displayNameDescription = 'Reserved ';
        }

        foreach ($coupons as $coupon) {
            $code = str_replace('••••• ', '', $coupon->codeMasked);
            $exp = $coupon->endDate;
            $displayName = $displayNameDescription . $coupon->offerName . ' Certificate # ' . $coupon->codeMasked;
            $this->AddSubAccount([
                'Code'           => "amexFreeNightRewards" . str_replace(' - ', '', $displayNameDescription) . $code . strtotime($exp),
                'DisplayName'    => $displayName,
                'Balance'        => $coupon->points,
                'Number'         => $coupon->codeMasked,
                'ExpirationDate' => strtotime($exp),
            ]);
        }// foreach ($amexCoupons as $amexCoupon)
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        /*
        $key = 'hhonors_abck';
        $result = Cache::getInstance()->get($key);

        if (!empty($result) && $this->attempt == 0) {
            $this->logger->debug("set _abck from cache: {$result}");
            $this->http->setCookie("_abck", $result, ".hilton.com");

            return null;
        }
        */

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);

//            $selenium->seleniumOptions->addAntiCaptchaExtension = true;
//            $selenium->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www.hilton.com/en/hilton-honors/login/?forwardUrl=https%3A%2F%2Fwww.hilton.com%2Fen%2Fhilton-honors%2Fguest%2Fmy-account%2F");
            $iframe = $selenium->waitForElement(WebDriverBy::xpath('//iframe[@data-e2e="loginIframe"]'), 7);
            $this->savePageToLogs($selenium);

            if (!$iframe) {
                return false;
            }

            $selenium->driver->switchTo()->frame($iframe);

            $login = $selenium->waitForElement(WebDriverBy::id('username'), 15);
            $pass = $selenium->waitForElement(WebDriverBy::id('password'), 5);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-e2e = "signInButton"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$pass || !$btn) {
                return $this->checkErrors();
            }

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->sendKeys($login, $this->AccountFields['Login'], 10);
            $mover->sendKeys($pass, $this->AccountFields['Pass'], 10);
            $this->savePageToLogs($selenium);

            /*
            $btn->click();

            $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign Out")] | //span[@data-e2e = "errorText"] | //span[contains(text(), "Hilton Honors number")]'), 40);
            $this->savePageToLogs($selenium);
            */

            // 6Lelq5cUAAAAADLNVu2TXItDtSodLVgJfWAtCTrH
            /*
            $captcha = $this->parseCaptcha($this->http->FindPreg("/data-key=\"([^\"]+)\"/"));

            if ($captcha !== false) {
                $selenium->http->GetURL("https://www.hilton.com/_sec/cp_challenge/verify?cpt-token={$captcha}");
                $response = $selenium->http->JsonLog();
//                $selenium->http->GetURL("https://www.hilton.com/en/hilton-honors/login/?forwardUrl=https%3A%2F%2Fwww.hilton.com%2Fen%2Fhilton-honors%2Fguest%2Fmy-account%2F");
            }
            */

            $selenium->driver->switchTo()->defaultContent();

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                /*
                if (!in_array($cookie['name'], [
                    '_abck',
                ])) {
                    continue;
                }

                $result = $cookie['value'];
                $this->logger->debug("set new _abck: {$result}");
                Cache::getInstance()->set($key, $cookie['value'], 60 * 60 * 20);

                $this->http->setCookie("_abck", $result, ".hilton.com");
                */
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            if (!empty($responseData)) {
                $this->http->SetBody($responseData);
            }
        } catch (NoSuchDriverException | WebDriverException $e) {
            $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }

    private function seleniumRetrieve($url, $arFields)
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);

//            $selenium->seleniumOptions->addAntiCaptchaExtension = true;
//            $selenium->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;
            $selenium->http->GetURL($url);

            $loginInput = $selenium->waitForElement(WebDriverBy::id('confNumber'), 7);
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('lastName'), 0);
            $arrivalInput = $selenium->waitForElement(WebDriverBy::id('arrival'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(text(),'Find It')]"), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return $this->checkErrors();
            }

            $loginInput->sendKeys($arFields['ConfNo']);
            $passwordInput->sendKeys($arFields['LastName']);

            $arrivalY = date('Y', strtotime($arFields['ArrivalDate']));
            $arrivalM = date('m', strtotime($arFields['ArrivalDate']));
            $arrivalD = date('d', strtotime($arFields['ArrivalDate']));

            $arrivalInput->sendKeys($arrivalM);
            $arrivalInput->sendKeys($arrivalD);
            $arrivalInput->sendKeys($arrivalY);
            $arrivalInput->sendKeys(\WebDriverKeys::TAB);
            $this->savePageToLogs($selenium);

            $button->click();

            // Hmm, we can't find that reservation. Please check your details and try again.
            $error = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(),'t find that reservation. Please check your details and try again.')]"), 7);

            if ($error) {
                return $error->getText();
            }
            $error = $selenium->waitForElement(WebDriverBy::xpath("//h2[contains(text(),'Sign in to view or modify this reservation.')]"), 0);

            if ($error) {
                return $error->getText();
            }
            $this->sendNotification('not auth it // MI');

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
        } catch (NoSuchDriverException | WebDriverException $e) {
            $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }
}
