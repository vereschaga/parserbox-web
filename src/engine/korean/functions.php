<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\ItineraryArrays\AirTrip;

class TAccountCheckerKorean extends TAccountChecker
{
    use PriceTools;
    use SeleniumCheckerHelper;
    use ProxyList;

    protected $ksessionId;
    protected $loginInfo;
    private $currentItin = 0;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["NoCookieURL"] = true;

        return $arg;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""    => "Select your login type",
            "sky" => "SKYPASS No.",
            "uid" => "User ID",
        ];
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'], $properties['Currency']) && (strstr($properties['SubAccountCode'], "Coupons"))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], $properties['Currency'] . " %0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");


        if ($this->attempt == 1) {
            $this->setProxyBrightData(null, 'static', 'kr');
        } else {
            //$this->setProxyNetNut();
            $this->setProxyGoProxies(null, 'kr');
        }

        $userAgentKey = "UserAgent";

        if (empty($this->State[$userAgentKey]) || $this->attempt > 1) {
            $this->http->setRandomUserAgent(7);
            $agent = $this->http->getDefaultHeader("User-Agent");
            $this->State[$userAgentKey] = $agent;
        }
        $this->http->setUserAgent($this->State['UserAgent']);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.koreanair.com/api/li/auth/isUserLoggedIn");
        $this->http->RetryCount = 2;

        if ($this->isBadProxy()) {
            $this->setProxyBrightData(true);

            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.koreanair.com/api/li/auth/isUserLoggedIn");
            $this->http->RetryCount = 2;
        }
        $response = $this->http->JsonLog();

        if ($this->loginSuccessful($response)) {
            $this->loginInfo = $response;

            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);
        $this->http->removeCookies();

        $seleniumKey = $this->getCookiesFromSelenium();

        return true;

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.koreanair.com/login");

        if ($this->isBadProxy()) {
            $this->markProxyAsInvalid();

            $this->setProxyBrightData(true);
            $this->http->GetURL("https://www.koreanair.com/login");
        }
        $this->http->RetryCount = 2;

        if ($this->isBadProxy()) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 1);
        }

        if (!strstr($this->http->currentUrl(), '/login')) {
            return $this->checkErrors();
        }

        if ($this->AccountFields['Login2'] == 'sky'
            || (empty($this->AccountFields['Login2'])
                && $this->http->FindPreg("/^([A-Z]{2}\d{5,})$/ims", false, $this->AccountFields['Login']))) {
            $this->AccountFields['Login'] = str_replace(' ', '', $this->AccountFields['Login']);
            $this->logger->debug("SKYPASS No. Login >>> {$this->AccountFields['Login']}");
            $data = [
                "password"      => $this->AccountFields['Pass'],
                "skypassNumber" => $this->AccountFields['Login'],
            ];
        } else {
            $this->logger->debug("ID Login >>> {$this->AccountFields['Login']}");
            $data = [
                "password" => $this->AccountFields['Pass'],
                "userId"   => $this->AccountFields['Login'],
            ];
        }

        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><#");

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data URL not found");

            return false;
        }
        $retry = false;
        $this->http->NormalizeURL($sensorDataUrl);
        $key = $this->sendSensorData($sensorDataUrl);

        if (
            strpos($this->http->Error, 'Network error 56 - Unexpected EOF') !== false
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 1);
        }

        $headers = [
            "Accept"       => "*/*",
            "Referer"      => "https://www.koreanair.com/login",
            "Content-Type" => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.koreanair.com/api/li/auth/signIn", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        if (property_exists($this, 'isRewardAvailability') && $this->isRewardAvailability
            && ($this->isBadProxy() || $this->http->Response['code'] == 428 || $this->http->Response['code'] == 502)
        ) {
            // 502 retry not helped -> timeout
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(5, 1);
        }

        /*
        if ($this->http->Response['code'] == 403 || $this->http->Response['code'] == 428) {
            $this->sendStatistic(false, $retry, $key);
            $this->DebugInfo = "need to upd sensor_data [key: {$key}";

            $this->http->removeCookies();
            $seleniumKey = $this->getCookiesFromSelenium();

            return true;

            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.koreanair.com/api/li/auth/signIn", json_encode($data), $headers);
            $this->http->RetryCount = 2;

            $key = 100;

            if ($this->http->Response['code'] == 403) {
                $this->DebugInfo .= " / seleniumKey: {$seleniumKey}]";
                $this->sendStatistic(false, $retry, $key);

                throw new CheckRetryNeededException();
            } else {
                $this->DebugInfo .= "]";
                $this->sendStatistic(true, $retry, $key);
            }
        } else {
            $this->sendStatistic(true, $retry, $key);
        }
        */

        if (isset($this->http->Response['headers']['set-cookie'])) {
            $this->ksessionId = $this->http->FindPreg('/ksessionId=(.+?);/', false,
                join("\n", $this->http->Response['headers']['set-cookie']));

            if (empty($this->ksessionId)) {
                $this->logger->error('ksessionId is empty');

                if (strstr($this->http->Response['body'], '{"sec-cp-challenge": "true","provider":"crypto","branding_url_content":"/_sec/cp_challenge/crypto_message-3-6.htm"')) {
                    $this->markProxyAsInvalid();

                    throw new CheckRetryNeededException(3, 1);
                }

                return false;
            }
            $this->logger->notice("ksessionId: {$this->ksessionId}");
        }

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // Access is allowed
        if ($this->loginSuccessful($response)) {
            $this->loginInfo = $response;
            $this->http->setDefaultHeader('ksessionId', $this->ksessionId);

            return true;
        }

        if (isset($response->code, $response->message)) {
            $message = $response->message;

            if (
                $response->code == "IMM-LI.LOGIN.0006"
                && $message == "No matching member information. Please check ID or password."
//                || $this->http->Response['code'] == 401
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $response->code == "IMM-LI.MEMBER.2002"
                && $message == "There are 2 member IDs linked to the personal information entered. Please contact the Service Center."
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            /*
             * Reactivate online account
             *
             * Homepage dormant account. You can log in after verifying your identity.
            */
            if (
                $response->code == "IMM-LI.LOGIN.0007"
                && $message == "홈페이지 휴면 계정입니다. 본인확인 후 로그인 할 수 있습니다."
            ) {
                $this->throwProfileUpdateMessageException();
            }

            return false;
        }// if (isset($response->code, $response->message))

        if ($message = $this->http->FindSingleNode('//em[contains(@class, "-negative")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'No matching member information.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'This account is currently unavailable for use. Please contact the Service Center.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'Your request has not been processed successfully')) {
                throw new CheckRetryNeededException(3, 3, $message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == "홈페이지 휴면 계정입니다. 본인확인 후 로그인 할 수 있습니다.") {
                $this->throwProfileUpdateMessageException();
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('
                //p[contains(text(), "Your SKYPASS Number is connected with more than 1 User ID. Please select the User ID to maintain on our website.")]
                | //h1[contains(text(), "Reactivate online account")]
                | //h1[contains(text(), "Reset Password")]
            ')
        ) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $responseForBrokenAccount = $this->http->JsonLog();

        $this->http->RetryCount = 0;

        $this->http->PostURL("https://www.koreanair.com/api/ss/skypass/mileage/memberMileageSummary", []);

        if (strpos($this->http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)') !== false) {
            $this->http->SetProxy($this->proxyReCaptcha());
            $this->http->PostURL("https://www.koreanair.com/api/ss/skypass/mileage/memberMileageSummary", []);
        }

        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        // Accrued Miles (Accrual records of Korean Air and partner companies)
        $this->SetProperty('TotalMilesAccrued', $response->totalAccrued ?? null);
        // Redeemed Miles
        $this->SetProperty('TotalMilesRedeemed', $response->totalRedeemed ?? null);
        // Expired Miles
        $this->SetProperty('TotalMilesExpired', $response->expiredMileage ?? null);

        // Balance - Remaining / Redeemable Miles
        if (isset($response->remainMileage)) {
            // zero balance fix
            if ($response->remainMileage === '') {
                $this->SetBalance(0);
            }
            $this->SetBalance($response->remainMileage);
        } else {
            $this->logger->notice("Balance are not found");

            if ($this->attempt == 0 && isset($response->message) && $response->message == 'Please proceed after log-in.') {
                throw new CheckRetryNeededException(2, 1);
            }
        }

        $this->http->GetURL("https://www.koreanair.com/api/ss/skypass/member/searchMemberLevel");
        $response = $this->http->JsonLog();
        // Flights
        $this->SetProperty('QualifyingFlights', $response->keBoardingCount ?? null);
        // Accrual records of Korean Air flight
        $this->SetProperty('AccrualMiles', $response->keBoardingMileage ?? null);

        // Family Plan
        $this->logger->info("Family Plan", ['Header' => 3]);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.koreanair.com/api/ss/skypass/member/searchFamilyInfoList");
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 2);

        if (!empty($response->familyInfoList)) {
            $familyMileage = 0;

            foreach ($response->familyInfoList as $familyInfo) {
                if ($familyInfo->relationship === 'Member Him/Herself') {
                    continue;
                }// if ($familyInfo->relationship === 'Member Him/Herself')

                $familyMileage += $familyInfo->currentMileage;
            }// foreach ($response->familyInfoList as $familyInfo)

            $this->SetProperty('FamilyPlanMiles', $familyMileage);
        }// if (!empty($response->familyInfoList))

        if ($this->Balance > 0) {
            $this->logger->info("Expiration date", ['Header' => 3]);
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.koreanair.com/api/ss/skypass/mileage/expiredMileage", []);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
            $response = $response->expiredMileageList ?? [];
            // Setup Expiration Date and Expiring Miles
            if (!empty($response)) {
                foreach ($response as $row) {
                    if (isset($row->expiration, $row->remainMileage)
                    && $row->remainMileage > 0 && $row->expiration != 'Permanent') {
                        // Expiration Date
                        $this->logger->debug("Expiration Year -> " . $row->expiration . " / Accrued Mileage -> $row->accrualMileage");
                        // Expiring Miles
                        $this->SetProperty('ExpiringMiles', $row->remainMileage);

                        // Expiration date  // https://redmine.awardwallet.com/issues/12488#note-12
                        $exp = strtotime("31 Dec " . preg_replace("/.12$/", "", $row->expiration));

                        if ($exp) {
                            $this->SetExpirationDate($exp);
                        }

                        break;

                        // Expiration date  // refs #12488, #8967
                    /*
                    $toDate = date("Y-12-31", strtotime("-10 year", strtotime("01/01/$row->expiration")));
                    $fromDate = date("Y-01-01", strtotime("-10 year", strtotime("01/01/$row->expiration")));
                    $miles = 0;
                    $this->http->GetURL("https://www.koreanair.com/api/skypass/mileageHistory?fromDate={$fromDate}&toDate={$toDate}&_=".time().date("B"));
                    $response = $this->http->JsonLog(null, false);
                    if (!empty($response))
                    foreach ($response as $node) {

                        if (!isset($node->debitCredit) || !isset($node->mileage))
                            return;

                        $sign = '+';
                        if ($node->debitCredit == 'debit' && $node->mileage > 0)
                            $sign = '-';

                        // refs #12488
                        if (strtotime($node->entryDate) < strtotime("June 30, 2008")) {
                            $this->logger->notice("Skip old Date -> {$node->entryDate} / Miles -> ".$miles." / Sign $sign");
                            continue;
                        }

                        if ($sign == '-')
                            $miles -= $node->mileage;
                        else
                            $miles += $node->mileage;
                        $this->logger->debug("Date -> {$node->entryDate} / Miles -> ".$miles." / Sign $sign");
                    }// foreach ($response as $node)

                    if ($miles == $row->accrualMileage) {
                        unset($node);
                        $this->logger->notice("Success -> {$miles} == {$row->accrualMileage}");
                        $response = array_reverse($response);
                        $miles = $row->accrualMileage;
                        foreach ($response as $node) {
                            $sign = '+';
                            if ($node->debitCredit == 'debit' && $node->mileage > 0)
                                $sign = '-';
                            if ($sign == '+')
                                $miles -= $node->mileage;
                            $this->logger->debug(">>> Date -> {$node->entryDate} / Miles -> ".$miles." / Sign $sign");
                            if ($miles <= 0) {
                                $exp = strtotime("+10 year", strtotime($node->entryDate));
                                if (date("Y", $exp) == $row->expiration)
                                    $this->SetExpirationDate($exp);
                                // Expiring Miles
                                $this->SetProperty('ExpiringMiles', $miles + $node->mileage);
                                // Earning Date     // refs #4936
                                $this->SetProperty("EarningDate", $node->entryDate);
                                break;
                            }// if ($miles <= 0)
                        }// foreach ($response as $node)
                    }// if ($miles == $row->accrualMileage)
                    break;
                    */
//            $this->http->SetProxy($this->proxyDOP());
                    }// if (isset($row->expiration, $row->remainMileage) && $row->remainMileage > 0)
                }// foreach ($response as $row)
            }// if (!empty($response))
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // AccountID: 3700588
            if (
                isset($response->code, $response->status, $response->message)
                && $this->brokenAccount()
                && $response->message == 'Your request has not been processed successfully. Please contact the Service Center if the problem continues.'
                && $response->status == 'INTERNAL_SERVER_ERROR'
                && $response->code == 'COMM.9999'
            ) {
                $this->SetProperty('TotalMilesAccrued', $responseForBrokenAccount->userInfo->login->member_total_accrued_mile ?? null);
                $this->SetBalance($responseForBrokenAccount->userInfo->login->member_info_remaining_miles ?? null);
            }

            return;
        }

        $this->logger->info("Flight Coupons", ['Header' => 3]);
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"       => "application/json",
            "channel"      => "pc",
            "Content-Type" => "application/json",
            "Referer"      => "https://www.koreanair.com/my-wallet/coupon",
        ];
        $this->http->PostURL("https://www.koreanair.com/api/et/coupon/allPromotionCoupon", '{"caller":"MYPAGE"}', $headers);
        $couponList = $this->http->JsonLog();

        if (!empty($couponList->promotionDiscountCouponList)) {
            foreach ($couponList->promotionDiscountCouponList as $promotionDiscountCoupon) {
                $this->AddSubAccount([
                    "Code"           => "koreanFlightCoupons" . $promotionDiscountCoupon->couponCode,
                    "DisplayName"    => $promotionDiscountCoupon->couponDescription,
                    "Balance"        => $promotionDiscountCoupon->couponDetailCondition->discountAmount,
                    "ExpirationDate" => strtotime(preg_replace('/(\d{4})(\d{2})(\d{2})/', '$1-$2-$3', $promotionDiscountCoupon->couponDetailCondition->applyToDateTime)),
                    // Coupon number
                    "CouponNumber"   => $promotionDiscountCoupon->couponCode,
                    "Currency"       => $promotionDiscountCoupon->couponDetailCondition->discountCurrency,
                ]);
            }
        }

        $this->logger->info("Partner Coupons", ['Header' => 3]);
        $this->http->PostURL("https://www.koreanair.com/api/hmp/partnerscoupon/getCouponList", '{"langCode":"EN","couponType":""}', $headers);
        $partnersCoupons = $this->http->JsonLog();

        if (!empty($partnersCoupons)) {
            foreach ($partnersCoupons as $partnersCoupon) {
                $this->AddSubAccount([
                    "Code"           => "koreanPartnerCoupons" . $partnersCoupon->couponCode,
                    "DisplayName"    => "{$partnersCoupon->benefit} (#{$partnersCoupon->couponCode})",
                    "Balance"        => null,
                    "ExpirationDate" => strtotime(preg_replace('/(\d{4})(\d{2})(\d{2})/', '$1-$2-$3', $partnersCoupon->validTo)),
                    // Coupon number
                    "CouponNumber"   => $partnersCoupon->couponCode,
                ]);
            }// foreach ($partnersCoupons as $partnersCoupon)
        }// if (!empty($partnersCoupons))

        $this->logger->info("Complimentary e-Coupon", ['Header' => 3]);
        $this->http->GetURL("https://www.koreanair.com/api/et/coupon/electronCouponByMember");
        $this->http->RetryCount = 2;
        $electronCouponList = $this->http->JsonLog();

        if (!empty($electronCouponList->electronCouponList)) {
            foreach ($electronCouponList->electronCouponList as $electronCoupon) {
                $this->AddSubAccount([
                    "Code"           => "koreanComplimentaryECoupons" . $electronCoupon->ecpnAuthNumber,
                    "DisplayName"    => "Complimentary e-Coupon #{$electronCoupon->ecpnAuthNumber}",
                    "Balance"        => $electronCoupon->accualDiscountAmount,
                    "ExpirationDate" => strtotime(preg_replace('/(\d{4})(\d{2})(\d{2})/', '$1-$2-$3', $electronCoupon->ecpnExpireDate)),
                    // Coupon number
                    "CouponNumber"   => $electronCoupon->ecpnAuthNumber,
                    "Currency"       => $electronCoupon->currency,
                ]);
            }
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->RetryCount = 0;
        $this->http->setHttp2(false);
        $this->http->disableOriginHeader();
        $this->notClosedCleanly("https://www.koreanair.com/api/vw/reservationSearch/reservationList");
        $response = $this->http->JsonLog();

        // no Itineraries
        if ($this->http->Response['code'] == 200 && $this->http->FindPreg('/^\{"reservationList":\[\]\}/')) {
            return $this->noItinerariesArr();
        }
        $reservationList = $response->reservationList ?? [];

        foreach ($reservationList as $itinerary) {
            if (!isset($itinerary->reservationRecLoc, $itinerary->reservationNumber, $itinerary->enKey)) {
                $this->logger->debug(var_export($reservationList, true), ['pre' => true]);

                continue;
            }// if (!isset($itinerary->reservationRecLoc, $itinerary->reservationNumber))
            // Skip old itinerary
            if (isset($itinerary->arrivalDate)) {
                $arrivalTime = preg_replace("/(\d{2})(\d{2})/", "\${1}:\${2}", $itinerary->arrivalTime);
                $arrivalDate = preg_replace("/(\d{4})(\d{2})(\d{2})/", "\${1}/\${2}/\${3}", $itinerary->arrivalDate);
                $this->logger->debug("lastSegmentDate {$itinerary->arrivalDate} {$itinerary->arrivalTime} / " . strtotime($arrivalDate . " " . $arrivalTime));

                if (strtotime($arrivalDate) < strtotime("-1 month")) {
                    $this->logger->debug(var_export($itinerary, true), ['pre' => true]);
                    $this->logger->notice("Skip old itinerary: {$itinerary->reservationNumber} -> {$arrivalDate}");

                    continue;
                }
            }

            $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$itinerary->reservationRecLoc} ({$itinerary->reservationNumber})", ['Header' => 3]);
            $this->currentItin++;
            $enKey = $itinerary->enKey ?? null;
            $data = [
                "enKey" => $enKey,
            ];
            $headers = [
                "Accept"       => "*/*",
                "Referer"      => "https://www.koreanair.com/reservation/list",
                "Content-Type" => "application/json",
            ];
            $this->http->PostURL("https://www.koreanair.com/api/vw/reservationSearch/reservationDetail", json_encode($data), $headers);
            $response = $this->http->JsonLog();

            if ($this->http->Response['code'] == 403
                || (isset($response->message) && stripos($response->message, 'Communication was not successful. Please try again in a few minutes.') !== false)) {
                sleep(7);
                $this->http->PostURL("https://www.koreanair.com/api/vw/reservationSearch/reservationDetail",
                    json_encode($data), $headers);
                $this->http->JsonLog();
            }

            if ($this->http->Response['code'] == 200
                && isset($response->status) && $response->status === 'OK' && isset($response->message)
                && stripos($response->message,
                    'Advanced search is not available for this booking due to itinerary expiry or cancellation.') !== false
            ) {
                $this->logger->error('Skipping itinerary: ' . $response->message);

                continue;
            }

            /*
                            $itinUrl = "https://www.koreanair.com/api/reservation/{$itinerary->reservationNumber}?reservationCode={$itinerary->reservationNumber}&recordLocator={$itinerary->recLocNumber}&_=".time().date("B");
                            $this->http->GetURL($itinUrl, $headers, 30);
                            if ($this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)) {
                                sleep(7);
                                $this->http->GetURL($itinUrl, $headers, 30);
                            }
            */
            // This reservation is restricted to display at online. Please contact our Service Center for more information.
            $error = $this->http->FindPreg('/"(Unable to view reservation|This booking reference cannot be searched on the Korean Air website\. Please contact the original place of booking\.)/');

            if ($error) {
                if (isset($itinerary->paidStatus) && strcasecmp($itinerary->pnrStatus, 'PAID') == 0) {
                    $this->logger->error('Parse itinerary from preview/ List itineraries');
                    $result[] = $this->ParseItineraryPreview($itinerary);
                } else {
                    $this->logger->error('Skipping itinerary: ' . $error);
                }
                $flagCheck = true;

                continue;
            }

            $result[] = $this->ParseItinerary();
        }// foreach ($response as $itinerary)

        return $result;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"       => "PostingDate",
            "Title"      => "Description",
            "Flight No." => "Info",
            "Miles"      => "Miles",
            "Class"      => "Info",
            "Route"      => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $toDate = date("Ymd");
        $fromDate = date("Ymd", strtotime("-100 year", strtotime($toDate)));

        $this->http->GetURL("https://www.koreanair.com/api/ss/skypass/mileage/memberMileageHistory?fromDate={$fromDate}&historyType=All&searchTerm=PERIOD&toDate={$toDate}");

        $response = $this->http->JsonLog(null, 0);
        // it works
        if (!isset($response->meberMileageHistoryList)
            && (
                (isset($response->status) && $response->status == 'INTERNAL_SERVER_ERROR')
                || isset($response->message) && $response->message == 'Endpoint request timed out'
            )
        ) {
            if ($this->brokenAccount()) {
                return [];
            }

            sleep(5);
            $this->http->GetURL("https://www.koreanair.com/api/ss/skypass/mileage/memberMileageHistory?fromDate={$fromDate}&historyType=All&searchTerm=PERIOD&toDate={$toDate}");
        }

        $startIndex = sizeof($result);
        $result = $this->ParsePageHistory($startIndex, $startDate);

        $this->getTime($startTimer);

        return $result;
    }

    protected function isBadProxy(): bool
    {
        return $this->http->Response['code'] == 403
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 28 - Operation timed out after') !== false
            || strpos($this->http->Error, 'Network error 7 - Failed to connect to') !== false
            || strpos($this->http->Error, 'Network error 56 - Recv failure: Connection reset by peer') !== false
            || strpos($this->http->Error, 'Network error 35 - Unexpected EOF') !== false
            || strpos($this->http->Error, 'Network error 28 - Unexpected EOF') !== false
            || strpos($this->http->Error, 'Network error 0 -') !== false
            || strpos($this->http->Error, 'Network error 28 - Connection timed out after ') !== false
            || strpos($this->http->Error, 'Network error 35 - OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection') !== false
        ;
    }

    protected function loginSuccessful($response)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($response->userInfo->skypassNumber) || $response->userInfo->skypassNumber === '000000000000') {
            return false;
        }
        // SKYPASS #
        $this->SetProperty("AccountNumber", $response->userInfo->skypassNumber);
        // Name
        if (isset($response->userInfo->englishFirstName, $response->userInfo->englishLastName)) {
            $this->SetProperty("Name", beautifulName($response->userInfo->englishFirstName . " " . $response->userInfo->englishLastName));
        } else {
            $this->logger->notice("Name is not found");
        }
        // Status
        if (isset($response->userInfo->memberLevel)) {
            switch ($response->userInfo->memberLevel) {
                case 'TB':
                    $this->SetProperty("Status", 'SKYPASS');

                    break;

                case 'MC':
                    $this->SetProperty("Status", 'Morning Calm');

                    break;

                case 'MM':
                    $this->SetProperty("Status", 'Million Miler');

                    break;

                case 'MP':
                    $this->SetProperty("Status", 'Morning Calm Premium');

                    break;

                default:
                    $this->sendNotification("korean. Unknown Status: {$response->userInfo->skypassTier}");

                    break;
            }// switch ($response->skypassTier)
        }// if (isset($response->skypassTier))
        else {
            $this->logger->notice("Status is not found");
        }

        return true;
    }

    private function notClosedCleanly($url)
    {
        $cntTry = 0;

        do {
            $stop = true;
            $this->logger->debug('try #' . $cntTry);

            if ($cntTry > 1) {
                sleep(1);
            }

            switch ($cntTry) {
                case 1:
                    $this->http->setRandomUserAgent();

                    break;

                case 2:
                    $this->setProxyDOP();
                    $this->http->setRandomUserAgent();

                    break;

                case 3:
                    $this->http->SetProxy($this->proxyReCaptcha());
                    $this->http->setRandomUserAgent();

                    break;

                case 4:
                    $this->setProxyMount();
                    $this->http->setRandomUserAgent();

                    break;
            }
            $this->http->RetryCount = 0;
            $this->http->GetURL($url, ['Upgrade' => substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, 4)]);
            $this->http->RetryCount = 2;

            if (strstr($this->http->Error, 'Network error 92')) {
                $stop = false;
            }
            $cntTry++;
        } while (!$stop && $cntTry <= 5);
    }

    private function brokenAccount()
    {
        $this->logger->notice(__METHOD__);

        return $this->AccountFields['Login'] == 'ymoon95';
    }

    private function sendStatistic($success, $retry, $key)
    {
        $this->logger->notice(__METHOD__);
        StatLogger::getInstance()->info("korean sensor_data attempt", [
            "success"         => $success,
            "userAgentStr"    => $this->http->userAgent,
            "retry"           => $retry,
            "attempt"         => $this->attempt,
            "sensor_data_key" => $key,
            "isWindows"       => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // maintenance
        if ($message = $this->http->FindSingleNode('
                //p[@class = "info" and contains(text(), "Kindly be informed that we are performing system improvement on")]
                | //p[contains(text(), "Kindly be informed that we are undergoing scheduled system improvement")]
                | //p[contains(text(), "Website connection is delayed due to system check.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[@class = "info" and contains(text(), "Korean air website is temporarily unavailable due to a server error.")]')) {
            throw new CheckException("Korean air website is temporarily unavailable due to a server error.", ACCOUNT_PROVIDER_ERROR);
        }
        // Korean Air site is currently experiencing heavy traffic
        if ($message = $this->http->FindPreg("/Korean Air site is currently experiencing heavy traffic. Please try again later./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // High network traffic volume
        if ($message = $this->http->FindPreg("/Website connection is delayed due to high network traffic volume caused by ongoing online reservations for the flights during New Year holidays\. Please try again later\. We apologize for any inconvenience this may cause\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Website connection is delayed due to high network traffic volume.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            // An error occurred while processing your request.
            || ($this->http->FindPreg("/An error occurred while processing your request\./") && $this->http->Response['code'] == 504)) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function ParseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        /** @var AirTrip $result */
        $response = $this->http->JsonLog(null, 0);

        // Confirmation number
        $result['Kind'] = 'T';
        $result['RecordLocator'] = $response->reservationRecLoc ?? null;
        $result['TripNumber'] = $response->reservationNumber ?? null;

        // Passengers
        $ticketUrls = [];

        if (isset($response->travellerList)) {
            foreach ($response->travellerList as $traveller) {
                $result['Passengers'][] = beautifulName($traveller->engFirstName . ' ' . $traveller->engLastName);
                // AccountNumbers
                if (!empty($traveller->skypassNumber)) {
                    $accountNumbers[] = $traveller->skypassNumber;
                }

                if (!empty($traveller->ticketList)) {
                    foreach ($traveller->ticketList as $ticketDetails) {
                        if (!empty($ticketDetails->ticketNumber)) {
                            $ticketNumbers[] = $ticketDetails->ticketNumber;
                        }

                        if (!empty($ticketDetails->receiptUrl)) {
                            $ticketUrls[] = $ticketDetails->receiptUrl;
                        }
                    }
                }
            }// foreach ($response->travellers as $traveller)
        }// if (isset($response->travellers))
        // AccountNumbers
        if (!empty($accountNumbers)) {
            $result['AccountNumbers'] = array_values(array_unique($accountNumbers));
        }
        // TicketNumbers
        if (!empty($ticketNumbers)) {
            $result['TicketNumbers'] = array_values(array_unique($ticketNumbers));
        }
        $seatPreferences = $response->seatPreferences ?? [];
        // Receipt data
        $ticketUrls = array_values(array_unique($ticketUrls));
        $this->logger->info(sprintf('Found %s different ticket urls', count($ticketUrls)));
        $result = array_merge($result, $this->parseTickets($ticketUrls));

        // Air trip segments
        if (isset($response->itineraryList)) {
            foreach ($response->itineraryList as $flight) {
                $segment = [];
                // $this->logger->debug(var_export($flight, true), ['pre' => true]);

                if (isset($flight->isItinerary) && $flight->isItinerary == false) {
                    $this->logger->notice("skip non itinerary segment");
                    $this->logger->debug(var_export($flight, true), ['pre' => true]);

                    continue;
                }

                // FlightNumber
                $segment['FlightNumber'] = $flight->carrierNumber ?? $flight->flightNumber ?? null;
                // AirlineName
                $segment['AirlineName'] = $flight->carrierCode ?? null;
                // Cabin
                if (isset($flight->cabinClass)) {
                    $segment['Cabin'] = $flight->cabinClass;
                }
                // BookingClass
                $segment['BookingClass'] = $flight->bookingClass ?? null;
                // Stops
                if (isset($flight->numberOfStop)) {
                    $segment['Stops'] = $flight->numberOfStop;
                }
                // Aircraft
                $segment['Aircraft'] = trim($flight->aircraft ?? null);
                // Operator
                $segment['Operator'] = $flight->operatedBy ?? null;
                // DepartureTerminal
                $segment['DepartureTerminal'] = $flight->departureTerminal ?? null;
                // DepCode
                $segment['DepCode'] = $segment['DepName'] = $flight->departureAirport;
                // ArrivalTerminal
                $segment['ArrivalTerminal'] = $flight->arrivalTerminal ?? null;
                // ArrCode
                $segment['ArrCode'] = $segment['ArrName'] = $flight->arrivalAirport;
                // DepDate
                $departureDate = $flight->departureDate ?? null;
                $departureTime = $flight->departureTime ?? null;
                $depDate = $this->getDateTime($departureDate, $departureTime);

                if (isset($depDate)) {
                    $segment['DepDate'] = $depDate;
                }
                // ArrDate
                $arrivalDate = $flight->arrivalDate ?? null;
                $arrivalTime = $flight->arrivalTime ?? null;
                $arrDate = $this->getDateTime($arrivalDate, $arrivalTime);

                if (isset($arrDate)) {
                    $segment['ArrDate'] = $arrDate;
                }

                if (!isset($arrDate) && !isset($flight->arrivalDate, $flight->arrivalTime)) {
                    $segment['ArrDate'] = MISSING_DATE;
                }

                $segmentId = $flight->segmentId ?? null;

                foreach ($seatPreferences as $seatPreference) {
                    if ($segmentId == $seatPreference->segmentId) {
                        $segment['Seats'][] = $seatPreference->seatName;
                    }
                }

                $result['TripSegments'][] = $segment;
            }//   foreach ($response->itineraryList as $flight) {
        }// if (isset($response->itineraryList)) {

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function ParseItineraryPreview($response)
    {
        $this->logger->notice(__METHOD__);
        /** @var AirTrip $result */
        $result = [];

        // Confirmation number
        $result['Kind'] = 'T';
        $result['RecordLocator'] = $response->recLocNumber ?? null;
        $result['TripNumber'] = $response->reservationNumber ?? null;

        // Air trip segments
        if (isset($response->reservationInfoList)) {
            foreach ($response->reservationInfoList as $flight) {
                $segment = [];
                // $this->logger->debug(var_export($flight, true), ['pre' => true]);
                // FlightNumber
                $segment['FlightNumber'] = $flight->flightNumber;
                // Cabin
                if (isset($flight->cabinClass)) {
                    $segment['Cabin'] = $flight->cabinClass;
                }
                // BookingClass
                $segment['BookingClass'] = $flight->bookingClass;
                // AirlineName
                $segment['AirlineName'] = $flight->airlineCode ?? null;
                // DepCode
                $segment['DepCode'] = $segment['DepName'] = $flight->departureAirportCode;
                // ArrCode
                $segment['ArrCode'] = $segment['ArrName'] = $flight->destinationAirportCode;
                // DepDate
                $depDate = explode("T", $flight->departure);
//                    $this->http->Log("DepDate <pre>".var_export($depDate, true)."</pre>", false);
                if (isset($depDate[1])) {
                    $segment['DepDate'] = $depDate[0] . ' ' . preg_replace("/\..+$/", '', $depDate[1]);
                    $this->logger->debug("DepDate {$segment['DepDate']} / " . strtotime($segment['DepDate']));
                    $segment['DepDate'] = strtotime($segment['DepDate']);
                }
                // ArrDate
                $arrDate = explode("T", $flight->arrival);
//                    $this->http->Log("ArrDate <pre>".var_export($arrDate, true)."</pre>", false);
                if (isset($arrDate[1])) {
                    $segment['ArrDate'] = $arrDate[0] . ' ' . preg_replace("/\..+$/", '', $arrDate[1]);
                    $this->logger->debug("ArrDate {$segment['ArrDate']} / " . strtotime($segment['ArrDate']));
                    $segment['ArrDate'] = strtotime($segment['ArrDate']);
                }

                $result['TripSegments'][] = $segment;
            }// foreach ($response->reservationInfoList as $flight)
        }// if (isset($response->reservationInfoList))

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function parseTickets($urls)
    {
        $this->logger->notice(__METHOD__);
        $res = [
            'SpentAwards' => null,
            'TotalCharge' => null,
            'Currency'    => null,
            'Tax'         => null,
            'BaseFare'    => null,
            'Fees'        => [],
        ];

        /** @var HttpBrowser $http2 */
        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);
        $feeDict = [];

        foreach ($urls as $url) {
            $http2->GetURL($url);
            // TotalCharge
            $totalInfo = $http2->FindSingleNode('//li[contains(text(), "Total Amount")]/span[1]');
            $total = $http2->FindPreg('/([\d.,]+)/', false, $totalInfo);

            if (!is_null($total)) {
                $total = PriceHelper::cost($total);
                $res['TotalCharge'] += $total;
            }
            // Currency
            if (!$res['Currency']) {
                $currency = $this->currency($totalInfo);
                $res['Currency'] = $currency;
            }
            // Tax
            $taxInfo = $http2->FindSingleNode('//li[contains(text(), "Taxes")]/span[1]');
            $tax = $this->http->FindPreg('/([\d.,]+)/', false, $taxInfo);

            if (!is_null($tax)) {
                $tax = PriceHelper::cost($tax);
                $res['Tax'] += $tax;
            }
            // BaseFare
            $costInfo = $http2->FindSingleNode('//li[normalize-space(text()) = "Fare Amount"]/span[1]/descendant::text()[normalize-space()][last()]');
            $cost = $this->http->FindPreg('/([\d.,]+)/', false, $costInfo);

            if (!is_null($cost)) {
                $cost = PriceHelper::cost($cost);
                $res['BaseFare'] += $cost;
            }
            // Fees
            $feeKeys = [
                'Fuel Surcharge',
                'Service Fees',
                'Carrier Imposed Fees',
            ];

            foreach ($feeKeys as $key) {
                $value = $http2->FindSingleNode("//li[contains(text(), '$key')]/span[1]");
                $value = $http2->FindPreg('/([\d.,]+)/', false, $value);

                if (!is_null($value)) {
                    $value = PriceHelper::cost($value);
                    $feeDict[$key] = isset($feeDict[$key]) ? ($feeDict[$key] + $value) : $value;
                }
            }
            // SpentAwards
            $awardInfo = $http2->FindSingleNode('//li[normalize-space(text()) = "Form of Payment"]/span[1]');
            $awards = $this->http->FindPreg('/\-M(\d+)/', false, $awardInfo);

            if (!is_null($awards)) {
                $res['SpentAwards'] += $awards;
            }
        }

        foreach ($feeDict as $key => $value) {
            $res['Fees'][] = [$key => $value];
        }

        return $res;
    }

    private function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog(null, 0);

        if (!empty($response->meberMileageHistoryList)) {
            foreach ($response->meberMileageHistoryList as $row) {
                if (!isset($row->entryDate)) {
                    $this->logger->notice("skip");
                    $this->logger->debug(var_export($row, true), ['pre' => true]);

                    continue;
                }
                $dateStr = $row->entryDate;
                $postDate = $this->getDateTime($dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    continue;
                }
                $result[$startIndex]['Date'] = $postDate;
                $result[$startIndex]['Title'] = $row->mileageTypeName ?? null;
                // credit card transactions
                if (!isset($result[$startIndex]['Title'])) {
                    $result[$startIndex]['Title'] = $row->creditCardCompany ?? null;
                }
                // hotel transactions
                if (!isset($result[$startIndex]['Title'])) {
                    $result[$startIndex]['Title'] = $row->companyName ?? null;
                }
                // correcting Title
                if (!isset($result[$startIndex]['Title']) && isset($row->accrualMileTypeName)) {
                    switch ($row->accrualMileTypeName) {
                        case 'EB':
                            $result[$startIndex]['Title'] = 'Excess Baggage';

                            break;

                        case 'EY':
                            $result[$startIndex]['Title'] = 'Partner Airlines Bonus';

                            break;

                        case 'EXST':
                            $result[$startIndex]['Title'] = 'Additional seat';

                            break;

                        case 'CNXL PE':
                            /*
                            $result[$startIndex]['Title'] = 'Bonus Cancel Penalty';
                            */
                            $result[$startIndex]['Title'] = 'Award refund fees'; // AccountID: 4825295

                            break;

                        case 'Hotel':
                            $result[$startIndex]['Title'] = 'Miles-to Hotels';

                            break;

                        case 'Lounge':
                            $result[$startIndex]['Title'] = 'KAL Lounge Service';

                            break;

                        case 'KE DOM':
                            $result[$startIndex]['Title'] = 'Korean Air Domestic';

                            break;

                        case 'KE MIX DOM':
                            $result[$startIndex]['Title'] = 'Cash and Miles (Korea Domestic)';

                            break;

                        case 'KE MIX INT':
                            $result[$startIndex]['Title'] = 'Cash and Miles (International)';

                            break;

                        case 'KE UPGD':
                            $result[$startIndex]['Title'] = 'Korean Air Upgrade';

                            break;

                        case 'KE INT':
                            $result[$startIndex]['Title'] = 'Korean Air International';

                            break;

                        case 'KE ETXN':
                            $result[$startIndex]['Title'] = 'Bonus Extension Penalty';

                            break;

                        case 'Limousine':
                            $result[$startIndex]['Title'] = 'KAL Limousine';

                            break;

                        case 'Logo':
                            $result[$startIndex]['Title'] = 'Logo Products';

                            break;

                        case 'NS PE':
                            $result[$startIndex]['Title'] = 'No-Show Penalty';

                            break;

                        case 'RTW':
                            $result[$startIndex]['Title'] = 'Round-the-World Bonus';

                            break;

                        case 'STA':
                            $result[$startIndex]['Title'] = 'SkyTeam Bonus';

                            break;

                        case 'EK':
                        case 'BO':
                        case 'AS':
                        case 'HA':
                            $result[$startIndex]['Title'] = 'Partner Airlines Bonus';

                            break;

                        case 'Rentcar':
                            $result[$startIndex]['Title'] = 'Miles-to-Rent a car';

                            break;

                        case 'STA UPGD':
                            $result[$startIndex]['Title'] = 'SkyTeam Upgrade';

                            break;

                        case 'Tour':
                            $result[$startIndex]['Title'] = 'Mileage Tour';

                            break;

                        case '':
                        case 'KT':
                        case 'AT':
                        case 'Jeju FV':
                            $result[$startIndex]['Title'] = '-';

                            break;

                        default:
                            $this->logger->notice("[Unknown Title]: {$row->accrualMileTypeName}");

                            break;
                    }// switch ($row->accrualMileTypeName)
                }// if (!isset($result[$startIndex]['Title']) && isset($row->accrualMileTypeName))

                if (!isset($result[$startIndex])) {
                    continue;
                }
                // correcting Title
                if (in_array($result[$startIndex]['Title'], ['KE Domestic', 'KE International'])) {
                    $result[$startIndex]['Title'] = str_replace('KE', 'Korean Air', $result[$startIndex]['Title']);
                }

                if (in_array($result[$startIndex]['Title'], ['KORAM BANK'])) {
                    $result[$startIndex]['Title'] = 'Citi Bank';
                }

                if (in_array($result[$startIndex]['Title'], ['US BANK'])) {
                    $result[$startIndex]['Title'] = 'SKYPASS Visa Card';
                }

                if (in_array($result[$startIndex]['Title'], ['CHASE'])) {
                    $result[$startIndex]['Title'] = 'Chase Ultimate Rewards';
                }
                // Event
                if (!isset($result[$startIndex]['Title']) && isset($row->entryType) && $row->entryType == 'Event') {
                    $result[$startIndex]['Title'] = 'Event';
                }
                // Adjustment
                if (!isset($result[$startIndex]['Title']) && isset($row->entryType) && $row->entryType == 'Adjust') {
                    $result[$startIndex]['Title'] = 'Adjustment';
                }

                if (!isset($result[$startIndex]['Title'])) {
                    $this->logger->debug(var_export($row, true), ["pre" => true]);
                    $this->sendNotification("korean - refs #11916. History - empty Title", 'awardwallet');
                }
                $result[$startIndex]['Flight No.'] = $row->flightNumber ?? '';
                $result[$startIndex]['Class'] = $row->bookingClassName ?? '';

                if (isset($row->departureAirport, $row->arrivalAirport) && $row->departureAirport != 'Adjust') {
                    if ($row->departureAirport == $row->arrivalAirport) {
                        $route = $row->departureAirport;

                        if ($this->http->FindPreg("/^[A-Z]{6}$/", false, $route)) {
                            $route = preg_replace('/^([A-Z]{3})([A-Z]{3})$/', '$1-$2', $route, 3);
                        }

                        if ($this->http->FindPreg("/^[A-Z]{6}\s[A-Z]{6}$/", false, $route)) {
                            $route = preg_replace('/^([A-Z]{3})([A-Z]{3})\s([A-Z]{3})([A-Z]{3})$/', '$1-$2 / $3-$4', $route, 3);
                        }

                        $result[$startIndex]['Route'] = $route;
                    } else {
                        $result[$startIndex]['Route'] = $row->departureAirport . "-" . $row->arrivalAirport;
                    }
                } else {
                    $result[$startIndex]['Route'] = '-';
                }
                $sign = '';

                if ($row->debitCredit == 'debit' && $row->mileage > 0) {
                    $sign = '-';
                }

                if (
                    isset($row->accrualMileTypeName)
                    && in_array($row->accrualMileTypeName, [
                        'KT',
                        'EXST',
                    ])
                ) {
                    $result[$startIndex]['Miles'] = $sign . $row->selfDeductedMileage;
                } else {
                    $result[$startIndex]['Miles'] = $sign . $row->mileage;
                }

                $startIndex++;
            }
        }// foreach($response as $row)

        return $result;
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);
        $key = rand(0, 3);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            if (!isset($this->State["Resolution"]) || $this->attempt == 2) {
                $resolutions = [
                    [1152, 864],
                    [1280, 720],
                    [1280, 768],
                    [1280, 800],
                    [1360, 768],
                    [1366, 768],
                    [1920, 1080],
                ];
                $this->State["Resolution"] = $resolutions[array_rand($resolutions)];
            }

            $selenium->setScreenResolution($this->State["Resolution"]);

            if ($this->attempt == 0) {
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
            } else {
                /*
                $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
                $selenium->http->SetProxy($this->proxyDOP(), false);
                //            $selenium->setProxyBrightData();
                $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->setKeepProfile(true);
                $selenium->disableImages();
                */

                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
                $request = FingerprintRequest::chrome();
                $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                if ($fingerprint !== null) {
                    $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                    $selenium->http->setUserAgent($fingerprint->getUseragent());
                }
            }

            $selenium->http->removeCookies();
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->saveScreenshots = true;

            try {
                $selenium->http->GetURL("https://www.koreanair.com/login");
            } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("TimeoutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage());
                sleep(2);
                $this->savePageToLogs($selenium);
            }

            if ($this->AccountFields['Login2'] == 'sky'
                || (empty($this->AccountFields['Login2'])
                    && $this->http->FindPreg("/^([A-Z]{2}\d{5,})$/ims", false, $this->AccountFields['Login']))) {
                $this->AccountFields['Login'] = str_replace(' ', '', $this->AccountFields['Login']);
                $this->logger->debug("SKYPASS No. Login >>> {$this->AccountFields['Login']}");
                $this->acceptCookies($selenium);
                $label = $selenium->waitForElement(WebDriverBy::xpath('//ul[@aria-label="Login Type"]//button[contains(normalize-space(),"SKYPASS Number")]'), 15);

//                if ($label) {
                    $selenium->driver->executeScript("
                        var link = document.querySelectorAll('.login__btn');
                        link = Array.from( link ).filter( e => (/SKYPASS Number/i).test( e.textContent ) );
                        link[0].click();
                    ");
//                    $label->click();
//                }
            } else {
                $this->logger->debug("ID Login >>> {$this->AccountFields['Login']}");
                $this->acceptCookies($selenium);
                $label = $selenium->waitForElement(WebDriverBy::xpath('//ul[@aria-label="Login Type"]//button[contains(normalize-space(),"User ID")]'), 15);

                if ($label) {
                    $selenium->driver->executeScript("
                        var link = document.querySelectorAll('.login__btn');
                        link = Array.from( link ).filter( e => (/User ID/i).test( e.textContent ) );
                        link[0].click();
                    ");
//                    $label->click();
                }
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//label[contains(text(), "User ID") or contains(text(), "SKYPASS Number")]/following-sibling::div/input'), 5);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//label[contains(text(), "Password")]/following-sibling::div/input'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-click-name="Log-in"]'), 0);
            $this->acceptCookies($selenium);

            if (!$loginInput || !$passwordInput || !$btn) {
                $this->logger->error("something went wrong");

                if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")] | //span[contains(text(), "This site can’t be reached")]')) {
                    $selenium->markProxyAsInvalid();
                    $retry = true;
                }

                if ($this->http->FindPreg('/<app-root><\/app-root>/')) {
                    $retry = true;
                }

                return false;
            }

            $selenium->driver->executeScript("document.querySelector('input[name=\"idSaveChk\"]').checked = true;");

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $btn->click();

            /*
            $selenium->driver->executeScript('
                const constantMock = window.fetch;
                window.fetch = function() {
                    console.log(arguments);
                    return new Promise((resolve, reject) => {
                        constantMock.apply(this, arguments)
                        .then((response) => {
                            if (response.url.indexOf("auth/signIn") > -1) {
                                response
                                .clone()
                                .json()
                                .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
                        }
                            resolve(response);
                        })
                    .catch((error) => {
                            reject(response);
                        })
                    });
                }
            ');
            */

            $res = $selenium->waitForElement(WebDriverBy::xpath($xpath = '
                //button[@data-ga4-click-name="Log out" or normalize-space() = "Update Later"]
                | //em[contains(@class, "-negative")]
                | //p[contains(text(), "Your SKYPASS Number is connected with more than 1 User ID. Please select the User ID to maintain on our website.")]
                | //h1[contains(text(), "Reactivate online account") or contains(text(), "Reset Password")]
                | //button[contains(text(),"Remind me again in 90 days")]
            '), 10);

            try {
                $this->savePageToLogs($selenium);
            } catch (ErrorException $e) {
                $this->logger->error("ErrorException: " . $e->getMessage());
            }
            $remindMe = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(),"Remind me again in 90 days")]'), 0);
            if ($remindMe) {
                $remindMe->click();
                $res = $selenium->waitForElement(WebDriverBy::xpath($xpath), 10);
                $this->savePageToLogs($selenium);
            }
            // provider error workaround
            if ($res && strstr($res->getText(), 'Your request has not been processed successfully.')
                || $selenium->waitForElement(WebDriverBy::xpath('//div[@id="sec-if-container" or @id="sec-overlay" or @id="sec-text-if" or @id="sec-cpt-if"]'), 5)) {
                /*
                if ($selenium->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0)) {
                    $selenium->waitFor(function () use ($selenium) {
                        return !$selenium->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0);
                    }, 120);
                }
                */
                $this->overlayWorkaround($selenium);


                $remindMe = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(),"Remind me again in 90 days")]'), 2);
                if ($remindMe) {
                    $remindMe->click();
                    $res = $selenium->waitForElement(WebDriverBy::xpath($xpath), 10);
                    $this->savePageToLogs($selenium);
                }
                $this->savePageToLogs($selenium);
                try {
                    $this->logger->error("click Log-in one more time");
                    if ($btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-click-name="Log-in"]'),
                        0)) {
                        $btn->click();
                    }
                } catch (UnrecognizedExceptionException $e) {
                    $this->logger->error("UnrecognizedException: " . $e->getMessage());
                }

                $this->logger->error("wait 30 sec");
                sleep(20);
                $this->savePageToLogs($selenium);
                $remindMe = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(),"Remind me again in 90 days")]'), 2);
                if ($remindMe) {
                    $remindMe->click();
                    $res = $selenium->waitForElement(WebDriverBy::xpath($xpath), 10);
                    $this->savePageToLogs($selenium);
                }
                if ($selenium->waitForElement(WebDriverBy::xpath('//div[@id="sec-if-container" or @id="sec-overlay" or @id="sec-text-if" or @id="sec-cpt-if"]'), 5)) {
                    /*
                    $selenium->waitFor(function () use ($selenium) {
                        return !$selenium->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0);
                    }, 120);
                    */
                    $this->overlayWorkaround($selenium);
                    try {
                        $this->logger->error("click Log-in one more time");
                        if ($btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-click-name="Log-in"]'),
                            0)) {
                            $btn->click();
                        }
                    } catch (UnrecognizedExceptionException $e) {
                        $this->logger->error("UnrecognizedException: " . $e->getMessage());
                    }
                    $this->logger->error("wait 10 sec");
                    sleep(10);
                    $this->savePageToLogs($selenium);
                }
            }// if ($res && strstr($res->getText(), 'Your request has not been processed successfully.'))

            if ($updateLaterBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[normalize-space() = "Update Later"]'), 0)) {
                $updateLaterBtn->click();
                sleep(3);
                $this->savePageToLogs($selenium);
            }

            $ksessionId = $selenium->driver->executeScript("return sessionStorage.getItem('ksessionId');");
            $this->logger->info("[Form ksessionId]: " . $ksessionId);
            $loggedInUserInfo = $selenium->driver->executeScript("return sessionStorage.getItem('loggedInUserInfo');");
            $this->logger->info("[Form loggedInUserInfo]: " . $loggedInUserInfo);

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);

            if (!empty($ksessionId)) {
                $this->ksessionId = $ksessionId;
                $this->http->setDefaultHeader("ksessionId", $ksessionId);
            }

            //$selenium->http->GetURL("https://www.koreanair.com/api/vw/reservationSearch/reservationList");

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            if (!empty($responseData)) {
                $this->http->SetBody($responseData);
            } elseif (!empty($loggedInUserInfo) && $loggedInUserInfo != '{}') {
                $this->http->SetBody($loggedInUserInfo);
            }
        } catch (
            UnknownServerException
            | SessionNotCreatedException
            | WebDriverCurlException
            | TimeOutException
            | NoSuchWindowException
            | NoSuchDriverException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | Facebook\WebDriver\Exception\WebDriverException
            $e
        ) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(4, 3);
            }
        }

        return $key;
    }

    private function overlayWorkaround($selenium)
    {
        $this->logger->notice(__METHOD__);

        if ($selenium->waitForElement(WebDriverBy::xpath('//div[@id="sec-if-container" or @id="sec-overlay" or @id="sec-text-if" or @id="sec-cpt-if"]'), 7)) {
            $this->savePageToLogs($selenium);
            // "I'm not a robot"
            if ($iframe = $selenium->waitForElement(WebDriverBy::xpath("//iframe[@id = 'sec-cpt-if']"), 0)) {
                $selenium->driver->switchTo()->frame($iframe);
                $robotCheckbox = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'robot-checkbox']"), 5);
                if ($robotCheckbox) {
                    $this->logger->debug("click by checkbox");
//                $selenium->driver->executeScript('document.querySelector(\'#sec-cpt-if\').contentWindow.document.querySelector(\'#robot-checkbox\').click()');
                    $selenium->driver->executeScript('document.querySelector(\'#robot-checkbox\').click()');
                    sleep(3);
                    $this->savePageToLogs($selenium);
                    $this->logger->debug("click by 'Proceed' btn");
                    $btn = $selenium->waitForElement(WebDriverBy::xpath("//*[@id='progress-button']"), 2);
                    if ($btn) {
                        $btn->click();
                    }
//                $selenium->driver->executeScript('document.querySelector(\'#sec-cpt-if\').contentWindow.document.querySelector(\'#proceed-button\').click()');
                    sleep(7);
                    $selenium->driver->switchTo()->defaultContent();
                } else {
                    $selenium->driver->switchTo()->defaultContent();
                    $selenium->waitFor(function () use ($selenium) {
                        return !$selenium->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0);
                    }, 120);
                }
            } else {
                $selenium->waitFor(function () use ($selenium) {
                    return !$selenium->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0);
                }, 120);
            }

            $this->savePageToLogs($selenium);
        }
    }

    private function acceptCookies($selenium)
    {
        $this->logger->notice(__METHOD__);
        // Accept All
        $selenium->driver->executeScript("try { document.querySelector('kc-global-cookie-banner').shadowRoot.querySelector('.-confirm').click() } catch (e) {}");
        sleep(2);
        $this->savePageToLogs($selenium);
    }

    private function sendSensorData($sensorDataUrl)
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data url not found");

            return false;
        }
//        $this->http->setCookie("_abck", "833F86DF21D8B274E92DDF1CCACDEE5D~-1~YAAQkJs+Fwv4nLmFAQAALzXeyAnGSdNW3tdwpZeXNiLQB6ljSmcJuKoXbc28z7IHSRqmiuuLpRxnX4a8DnXfWygjuEGfh1kK3I2oReJu8bgko/t0uuxV3d9UriyYNNTCZlj1p/3banq6Mf8milfLRVHkxCNt0okhQ/7TB9Rl+53RfTT6wLZBTyBaXzsrFauHoa7SclaKTDziZvMb0TPNnO+2FbZmhKFCQRYIzwP8AtFLoDRf3VmZujfIha805Pdb2edIiJCSCAg3mapBoUkU0HoA9lWdZXVWwOPgmrzqOaTnAg3Bkn8pzavmO4/e+bx4CQ3g3oXDk+35Bxd0GFjiSn4EJPIRug6f+jn493lGy7bn41ZfhYWKIIjTTaCsEGI7PbIslPXp/uglEm1Q/Q==~-1~-1~-1", ".koreanair.com");

        $sensorData = [
            '2;3488056;3224119;13,0,0,0,1,0;;wg8luz!WW!O ;_U,-n8?TC*<W/^G1-xwaP|Yjs,]eOO$K/s@$Qy&hAx9NDpeA-~CQS`:[r`Ts*a{qECjwO.z)aRmU73:;lQ!v25s3sWI_AZ+utU2ROFDxdf|p0&sGKJbLoYGk88)OmR$za]F0rao3/7] {T*$Qy~ .k!>Y3ZCHwn.NZOAve7`^W-~`9hk>v*8<6$o?_8X)~Z[[pvG f_vy(`,,s->VUpG5&eWK>2k&S00)8$7y{<nD9hQULgk/KmfZPfj@KUUxjjwZvo/0@6s3B,_bh78`~Ak7>y]FYtU!7$339`r~L{q(Jygxv@P)Hk$CS^Q-ZuU-/El#lm=FUeTm<%`p{LkBf;SW+Y%I5uIz[7Ge|$J>(?EEO_tlUM >jOqBMqdK&I6w1kTy,r`!sj_+JG@T>A|?*~N_bZ}<OHs!k$J^0Aou-#zV&y^TI*^ApM^_1@9XnF{ :@q]}6Sd%,n=!noOQ;v+.0[gg.Q%odqh&6r:f,c|>>2vO`]7JE cB/?:Az7Sn}A$UiL]W2t[*(9wYdz@J^Z.Dmf=]UDT@ 6.l&k>r?t}<+/o_m$R}koXBt**E&CP0:]ul4jj>`Q$L`p6BunZ.3i0(>KC+bs2}B0Tp1A`JVPzU0D0ZM%NX#)vr&ef;.OhZK 2m6(iWB*[>[usf.G<|,c2:XvunOq86DoGN+[XpB&.Sem1M`}vXrSlv#{*ri0P{_%v.#fNQ;7mWDOF#,jA5;;qyR!*LpUfSwdtj9B/B!<.1/W[Z-5p^+1n=Zq#tj9:F]f4!c}m e){ yLjmU!;GrFb0Xz^S^8${(4ND164LJZ.1nNXe>F=+U_{Tezm4Z!xHgpFZH]I(+Pqo=M?^C)=Id|?_,9_4#X@./lhlv_f (H4@ txC2~=4TvP}Rd8.K?zxkq8LBgk;=ZVTK4]tbrt$G8NyWyHTbh`)?5 ,4StOo[gndL?IFDe`9cGwLvLO9#xBm4Y~p8L}>ejwGjQiun-@nO,_<23Ycdd/(3y:FvZ@X/=gSX~(wB2K{RUe^<,(R%b_GCEd Ya%LFImsrKx*I6WM*;~oD+Fz)3hb,=KoU%z=z5N[i<:2>J:<^_ABqb5ew!iN[j3O#Q;$d;.0fNE37sI9zveWI:^;ShJ=NU-H9gb{J,`{%cZy,e7?%(o~^V~i$I0rkKBL>oZ0#wrn3U2THQP }}t$mwD9Frxc#An@*dsJ8WRZ1mb XG ,l$$6]uW:;?6H {6z-;iNJC)r9I0`752o1&D9$=/)/a)M76*avuzZ4;2oLYr YydsRWZ!/R@SQ/6zi3pAlwU_.jQ}xDU$HgzDe1toQB&byy*<#F[sx?}Z#N6[abFt}x]>u-X8.N>e%!OBw@CC*{H4ExkY<PzF4u32^h(stv;K{M_uuH(*6ON?tBK@sa9*Z~O&:q%ZW=bqD$^4s:rRdly^J$%cN6kM]06E]l5=L2D}!:6R[<zg{we>Gy_Wu><@<!>}~r(t0}0gz >Q/EnW-|9Ki*Im![[ikKw_b0!K@$KT?DZtq+A<,~IA|RDy}Y:*^=YEi[(02G7EM2TH0>8O;LF>qr:TV]KrSABIf/_=zPuY|J%f7.hk8-9>HldyDDYxr q}w$x}@(mMUwx2![E;rt<})|bRh,(U9d:(hZacjP1.i_V?tcXw+kCzcoEOaU0FK%NI).vk%H3Q_szq6k8oaH]1p.jx`vyw@qN4wIstg]9 &L7#1W*S8ne3(G+9Q5MmEY/6y+s4.>=|!mpI*C79z4TN}H!FdkIvF5a5[(`{d`B/Z }cw)oE@:2,uA5Kt`51oBe7D<EST`:5_!.&e9,7+y!]M=(FmniHp[nUMwr-:<3vB:+0QHnRbML226*Y_Yi@xta~$DLln{t_;l|~V%5#=ZmliLGn6rc3zvu%!p!Q*J5_vXhwa8!rn{%&},0w}3ZE>ka(=Yq9B%leILMV=z~mox73@-)pKP6gBd_y*^zGa}$;wN44y_bZqq.BWGB|e1M:tK`1tNoTi0+j<xc$*:9K~1($C.O>7BiW`bhB`<d0Q@9FE=l$sVlr7Qm{OY(>2$z8b)0rd G8IZ3Rs.W6j,mBf{Gd}nB`^z5@_0hJhW]D`&UAU  HE4?q,CxA_g},$X+] dcQ*oI*bEZ-*7t@9IPAGq/IQCY_g=usCyHc.!_O.n9at!R)=;X!Nm-]4n3(:=_lOL!_D<ljIn^G8<Rwx|jnE{g,j,WJ>(<QM9a>G9l [NwU_ cFSrHZ?gR1w6i)`Q9W.tR<%~v|XL.$Kp.pos >&tx^l6w(H0%BTHvs^pf`PTcrCOZY*oA;Ho%%c8x4NEl[@eLVB>2hBI]XfkPVx2sU:%iWL#`$SSJi)v|hdJN[_hja9^PF>D%b[FB7(]X|@D>(Zhs!}K78W&NU%RRW)MGr.,7YUB1Xz}gjV*Q<UMf+}9+XZN1e>w={&x(%|p_,gfvO-1wm ev3A_XN<y!$g?6+yO(>3}|W$CR,nqa.Acs8msf3rfT^Z8Ojzw5(A(4wK`{]E_^x$PFIgoz#jyn%yaPJcA)7=2E!fx?.l})Ki&Ci^| 9&o4CPE^!CXtn**vyo4O~7dV-F$E#Inu5B<l^QEHC+zCI5!.OnI]Fdblb/|wP*Zu*/=mk',
        ];

        $secondSensorData = [
            '2;3488056;3224119;26,16,0,0,1,0;=}m)q$/kRaq4S69z90M8lSD{jR+QP5.vw>vQ4S{u}mzK#&bT $XP:PW%vTB4A(U K_9g=]s_5pN2H axWeA]3odDR @/Uxxcd+?cN}S[T3,Y+j4)@}K5jw`Y(E6rsb#{(rxcQo474OhW-&`QJUmXj7<8g~ `7g}t~!3zag^^VTLDjsW4PE$pd_b/>g4qVz>{.9>`*-%6k4,|Y8v MJ%JCK#}Y0`~]jUaPae/4`7HK^)}20t8*<tojz*4sjPLbt%M)n*y k@Ki[{o]u{ytplhFYju`O%f*7czA|skthgXtO,5A829DQLN{q,H%f| AZ#Km*GT[L5RqZ112F(@{%~+(]r/F_p{Mc5Fz-.+=ct?]I$GWOe|^4u[@2Judev(Ig>wKAEMkeVGJ.w_7U]5GfnsaQ1kOATd>eErXw_i(y)]xydt)K[0Aou-#zV&yo@n4X8os.`rMAgmK?8TY.N%@p!85T`?5?p#[94~T|d&OxT1dnn%<h>&,cs>>YwSVY+N8v`D8k_wHXW<~0/Lkfe@jlZ/#gnQ_EDuYG;tsIFp`IX7z*[j,^:zD~CnB2tQi-W*sSSMb.pE#9P5{@J3>vJ(eJ%LZqaCdn ),iAdkFq Yn8Ep18yF,eJ)wK{ *g1MgW,Itze$(i@~Kp`Uv2l`1hSc.W5U~tb%K<{/dx#ZP_pBq<;.IxV5.T]MXvRec16`(y[sS8k)z.x(:][**C*5jRL71qf$NFTMUF46<YW&{3}8CjyCKy`BMWG&}g^1^W+9xkgysB8_d}z1C ~0l^)gto;moUJyRmdR0),<Jo{=D)WZ2#+:|Ir*54GAYS9nI]-N,86wg+5e&=<>,gL;oFU?QNigxuzo8>^FI@A_ eg-9kr(fRv*uVpJ^f#DD97Fl}>1#Z>Wq>X|m8.MDFJkT8D>_w.^YVTF+[<rWoRS{I$WvMOcd](?}Qal.W3Faqjoz(IF@ee>cCwQ{Lc?&}>vg`lpbP#9k, rf>vrk(irK![G:Wm%0.W0O ;PJ=n P^#soM>,*MjOzjC)n[j00@1JDCj>Rp=0<IihuL#47h+vDC@sD&K$/8rPW~z9pB?@{)M]oJx8dx;}gcB@qf8dy{njYc,x!M;j>g)7O)q.;lLD;ubW@-d[[iJ=N|1L4eT&gPeq 3^y(eF:}}pxeY(o&K0y:N-S8i]2#E0:TG7bxW4O}$p.r;H(Aj1Zq(o_.`^;FY=R (` >;#<l~mBGE=_k5+)DeL&}UX,/hKb#(xNt]GLE_fl1ueMweNIj6(#_87NsaBf.3>`?U|.wCK_[f^[~VSV;]Ky gudqM5Y67@8SM0?Spo@`ZF7vV]h7mZ:BEc7E#%Q &G| TE[Flt>v+%1igO[8idV`^xXbY~/ju3nS^t{~I^//YZ*H%9v5gaSst)(4!$W=^Q*lJTv-XpvRL,8 6z6t0]TkniCvtSa~8|;T6:;%/N(j8J_8gOu]jNO2bZA]$,x@.{l{r)NwLuZtDM1^Nq;&D+.>`,<M_iSDuD-WF_!U8-Hh7Fp/c*SUKzi!?n*NNogz SNLm#g~s>)X(Poun>AAL 3Tte^72cQN2Q_T(?~OL#e:t:adlD}tkZGw=zA4):5h{VW-9Q:uuH:_)QQP]9p#&>3|0K[=Z! !hMPg6[U(P@Z[>F9l.TJSLJo8kDu Q{*E3f+$5CbkuLMAfK+P9(A-[=V=ua@atR)-viy;e8OZaOY`_0x+G3y6D;&~<4qoJ&4Q75Gv`X7t|WZJt=|Qv^SkKc9za]?-S!Ff+Sq=2nebii{ae=8]B{g/_]n[:6#xA,Hhfc3hG5J4Q^XV[C=l5/AP.a<0-/Yg;$T(^eFe4dfs;&+f(SuR_T[t>1hja#T;g*d|g>CXMFGFse}#M#0!-fE,B<(Rx8NE/,=,{{q{TNG-/c9,%@$y|@`D1 YGCSh?Ngw[od<.Sm?MjPwl*V/-Y6o~POj^!Iug,=xv EAg7tP2d]Jr2~#8ocrx5LuCKw?iMWJp0~K:n~(Jv,3d7 TsBQ?6!D8oq%l~wiN>I9An9~shB<4`oA3(xu,0}M[|,]&uO.`kpW[;i:4gx0yE^Mi#nP~hB>?K@ =^XvnJY]!*-g9& 8BRSTdduS xP.z@~7My&CM)qOd=ioZA3,Mz!_bc7owUTWs+S.;{bwIBkY+l;u{i9gqS8>E5w72OJ!ew[/$}OV,HSfC4)K;UYtlnV:w;]Qc#%m(]L`p#dHdJvLQj8D{_VxX;^{kpIu05Egvt*,bAcvHM^GUHW_d8D7{e</kx;.e)AqC4b](pA>-H,%h[Hox%kc`U`Tx+Ol8VjL<qE;nc;s9VO>W#oGZB>;MBR}x<gUM|=2R9~p`N#[RJKEm4JggdyvR0*ja=[ZN(JOkZF}slC5}>B,V!4OfbulB+G7JO31Zi.>MSd`YZB#Xz#8s:3&BCMb)D>/~`?;Rvo<!!y,}vc !_f~UP5{5>b:;=ULY>VdiBEueeP3jydR`%=I+x[HrtgzD]uj%7v;9MLNGdE7(<126P_{)IVP|+zvIJp#(re1.qSTP/q)xRq!^;xC2q#)HnEj2X%KB%k6N^*,{LV3s)*B -:SF3PbMGzEtAnw5*tA~YFHOizLVy{2u:1bNraqEhSvP~Wm/ty<j[Jiv9Jz P]kbg UOzn1_Xp?#J=olj]gX?@##Mh1[yYOCCuKRGn2u90WX)_ SgU$W)Xq$d~|oEUysAT(9?fVsI1q_>~Fvs+UDROw ZDd _!D]R~]t~UweC.!q',
        ];

        if (count($sensorData) !== count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $this->http->RetryCount = 0;
        $data = [
            'sensor_data' => $sensorData[$key],
        ];
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);
        $data = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }

    private function getDateTime($date, $time = '')
    {
        $prDate = null;
        $result = null;

        if (!empty($date)) {
            $prDate = preg_replace("/(\d{4})(\d{2})(\d{2})/", "\${1}/\${2}/\${3}", $date);
        }

        if (!empty($time)) {
            $time = preg_replace("/(\d{2})(\d{2})/", "\${1}:\${2}", $time);
        }

        if ($prDate) {
            $result = strtotime($prDate . " " . $time);
        }

        return $result;
    }
}
