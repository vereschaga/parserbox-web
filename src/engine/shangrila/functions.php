<?php

include_once __DIR__ . '/../asiana/Crypt/RSA.php';

include_once __DIR__ . '/../asiana/Crypt/BigInteger.php';
use AwardWallet\Engine\ProxyList;

class TAccountCheckerShangrila extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerShangrilaSelenium.php";

        return new TAccountCheckerShangrilaSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $this->http->setDefaultHeader('Accept-Encoding', 'gzip');
    }

    public function IsLoggedIn()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.shangri-la.com/v1/website?__c=' . (time() * 1000), '{"service":"gcService.fetchUserInfo(query)","query":{"unMask":false},"context":{"lang":"English"},"mfa":{"accessTicket":""}}', [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json;charset=UTF-8',
            'X-Sl-Service' => 'gcService.fetchUserInfo(query)',
        ], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, 'gcInfo');

        if ($this->loginSuccessful($response)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid Email Address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/');

        if (
            !$this->http->FindSingleNode("//label[contains(text(),'Membership Number/ Verified Email')]")
            && !$this->http->FindPreg("/memberIdOrEmail\":\"Membership Number\/ Verified Email\",/")
            && !$this->http->FindPreg("/Why have I been blocked\?<\/h2>/")
        ) {
            return $this->checkErrors();
        }

        if ($sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#") ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")
            || $this->http->FindPreg("/Why have I been blocked\?<\/h2>/")
            ) {
//            $this->http->NormalizeURL($sensorPostUrl);
//            $this->logger->debug('sensorPostUrl -> ' . $sensorPostUrl);
//            $this->sendStaticSensorData($sensorPostUrl);
            $this->selenium();
        }

        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json;charset=UTF-8',
            'X-Sl-Service' => 'loginService.login(loginQuery)',
            'Origin'       => 'https://www.shangri-la.com',
            'Priority'     => 'u=1, i',
        ];
        $dvid = $this->http->getCookieByName('_dvid_');
        $uuid = $this->http->getCookieByName('uuid');
        $data = '{"query":{"skipCount":false,"password":"' . $this->encryptPass($this->AccountFields['Pass']) . '","gcMemberId":"' . $this->AccountFields["Login"] . '"},"service":"loginService.login(loginQuery)","context":{"appVersion":"99.9.9","os":"","packageType":"production","vendor":"","net":"","brand":"","sh":375,"sw":667,"mac":"","imei":"","carrier":"","ip":"","downloadChannel":"","timeZone":"+3:00","dvid":"' . $dvid . '","uuid":"' . $uuid . '","platformName":"BROWSER","lang":"English","source":"WEBSITE"},"mfa":{"accessTicket":""},"user":{}}';
        //$data = '{"query":{"skipCount":false,"password":"' . $this->encryptPass($this->AccountFields['Pass']) . '","gcMemberId":"' . $this->AccountFields["Login"] . '"},"service":"loginService.login(loginQuery)","context":{"source":"WEBSITE","lang":"English","dvid":"'.$dvid.'","timeZone":3},"mfa":{"accessTicket":""}}';
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.shangri-la.com/v1/website?__c=' . date('UB'), $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        // sensor_data issue
        if (
            $this->http->Response['code'] == 403
            && empty($this->http->Response['body'])
        ) {
            $this->DebugInfo = 'need to upd sensor_data: ' . $this->DebugInfo;

            $this->getCookiesFromSelenium();
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.shangri-la.com/v1/website?__c=' . date('UB'), $data, $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
        }

        if (!isset($response->errorInfo->captcha->geetest->imgStr)) {
            // sensor_data issue
            if (
                $this->http->Response['code'] == 403
                && empty($this->http->Response['body'])
            ) {
                $this->DebugInfo = 'need to upd sensor_data: ' . $this->DebugInfo;

                throw new CheckRetryNeededException(2, 1);
            }
            // 方法执行系统性错误 (Method execution system error)
            $message = $response->message ?? null;

            if ($message === '方法执行系统性错误') {
                throw new CheckRetryNeededException();
            }

            if (
                isset($response->data->gcInfoResponse->gcInfo)
                || isset($response->data->loginError->msg)
            ) {
                $this->logger->notice("captcha not found");

                return true;
            }

            return false;
        }
        $responseStr = $this->http->JsonLog($response->errorInfo->captcha->geetest->imgStr);

        if (isset($responseStr->challenge, $responseStr->gt)) {
//            $captcha = $this->parseGeettestRuCaptcha($responseStr->gt, $responseStr->challenge);
            $captcha = $this->parseGeettestAntiCaptcha($responseStr->gt, $responseStr->challenge);
            /*
            {"query":{"gcMemberId":"","password":"LczTv/s9idjBQCOH1l4xVOafMlCTWJ3b3BkDTXe0FVyrczq6QsSVD0kswyQ4GaRVkK5z5kp1GaYZ9pvwDJzfX6Sl9QztSs+rXuj/9KN2+vs7M4tEwTlQbB+qp3ltEeQqq8GIc8tVjvVPJ39h58J3c6bOvvLIcKEqTdlOQMKYLLE=",
           "skipCount":false,"rememberPassword":false},"service":"loginService.login(loginQuery)",
           "context":{"lang":"English","dvid":"dw-48ec8f87-d06c-46b2-ac14-73ba6ece452e","timeZone":3},
           "mfa":{"accessTicket":""},"captcha":{"type":"geetest","geetest":{"challenge":"8bd6adbff889e72d290c6ddea5c5a324",
           "validate":"ee319c7f73066cec8a40e0a4b35fb76a","seccode":"ee319c7f73066cec8a40e0a4b35fb76a|jordan","token":"8bd6adbff889e72d290c6ddea5c5a324"}}}
            */
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.shangri-la.com/v1/website?__c=' . date('UB'), json_encode([
                'query' => [
                    'gcMemberId'       => $this->AccountFields["Login"],
                    'password'         => $this->encryptPass($this->AccountFields['Pass']),
                    'skipCount'        => false,
                    'rememberPassword' => false,
                ],
                'service' => 'loginService.login(loginQuery)',
                'context' => ['lang' => 'English'],
                'mfa'     => ['accessTicket' => ''],
                'captcha' => ['type' => 'geetest', 'geetest' => [
                    'challenge' => $captcha->geetest_challenge ?? $captcha->challenge,
                    'validate'  => $captcha->geetest_validate ?? $captcha->validate,
                    'seccode'   => $captcha->geetest_seccode ?? $captcha->seccode,
                    'token'     => $response->errorInfo->captcha->geetest->token,
                ]],
            ]), [
                'Accept'       => 'application/json, text/plain, */*',
                'Content-Type' => 'application/json;charset=UTF-8',
                'X-Sl-Service' => 'loginService.login(loginQuery)',
            ]);
            $this->http->RetryCount = 2;
        }// if (isset($responseStr->challenge, $responseStr->gt))

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[
                contains(text(), "Error 502: Bad Gateway :-(")
                or contains(text(), "Internal Server Error - Read")
            ]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        //if (!$this->http->PostForm())
        //    return $this->checkErrors();
        // Access is allowed
        if ($this->loginSuccessful($response)) {
            return true;
        }
        // Errors credentials
        $message = $response->data->loginError->msg ?? null;

        if ($message) {
            $this->logger->error("[message]: {$message}");

            if (
                stripos($message, 'Membership Number / Password is not valid, or email is not verified. Please try again.') !== false
                || stripos($message, 'Your account has been temporarily locked. Please try again after') !== false
                || stripos($message, 'Membership Number / Password is not valid. Please try again.') !== false
                || stripos($message, 'Membership Number/Password is not valid. Please try again.') !== false
                || stripos($message, 'Email/Password is not valid or email is not verified. Please try again.') !== false
                || (stripos($message, 'Incorrect password') !== false && stripos($message, 'attempts left') !== false)
                || stripos($message, 'No password is set for this account, please sign in via our WeChat Mini Program (WeChat ID: shangri-lahotels)') !== false
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                stripos($message, 'It looks like you already have a Shangri-La Circle account. Please activate your account before signing in.') !== false
                || stripos($message, 'Sorry, we are experiencing some technical difficulty. Please try again later.') !== false
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }
        // Network error, please try again later.
        if (isset($response->status, $response->message)) {
            if ($response->status == 201 && $response->message == "方法执行系统性错误") {
                throw new CheckException('Network error, please try again later.', ACCOUNT_PROVIDER_ERROR);
            }

            if ($response->status == 201 && $response->message == "Sorry, we are experiencing some technical difficulty. Please try again later.	") {
                throw new CheckException($response->message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($response->status == 301 && strstr($response->message, "Unfortunately, we are experiencing some technical difficulty. Please try again later.")) {
                throw new CheckException('Unfortunately, we are experiencing some technical difficulty. Please try again later.', ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $response->message;

            return false;
        }

        // provider bug fix
        if (
            strstr($this->http->Error, 'Network error 28 - Operation timed out after')
        ) {
            throw new CheckRetryNeededException(3, 1);
        }

        return $this->checkErrors();
    }

    public function Parse($responseData = [])
    {
        //$response = $this->http->JsonLog(null, 0);
        if (empty($responseData['fetchUserInfo'])) {
            return;
        }
        $response = $this->http->JsonLog($responseData['fetchUserInfo'], 0);

        //$this->http->GetURL('https://www.shangri-la.com/en/corporate/golden-circle/online-services/account-summary/');
        if ($shipExp = $this->http->FindSingleNode("(//text()[contains(.,' status is valid until ')])[1]", null, true, '/until (\d+ \w+ \d{4})/')) {
            // Membership Expiration - Your Jade status is valid until 31 Dec 2020
            $this->SetProperty("MembershipExpiration", $shipExp);
        }

        $gcInfo = $response->data->gcInfoResponse->gcInfo ?? $response->data->gcInfo;
        //# Name
        $this->SetProperty("Name", beautifulName($gcInfo->firstName . ' ' . $gcInfo->lastName));
        //# Membership Number
        $this->SetProperty("Number", $gcInfo->gcMemberId);
        //# Member Since
        $this->SetProperty("MemberSince", date('m/y', strtotime($gcInfo->enrollTime ?? $gcInfo->registryDate, false)));
        // Tier Points.
        $this->SetProperty("TierPoints", $gcInfo->tierPoints ?? null);
        //# Current Tier
        $this->SetProperty("CurrentTier", beautifulName($gcInfo->tierLevel));
        //# Qualifying Nights Completed
        $this->SetProperty("QualifiedRoomNights", $gcInfo->qualifiedRoomNights);
        //# Balance - GC Award Points Balance
        $this->SetBalance($gcInfo->points);

        // Expiration Date
        $text = $response->data->gcInfoResponse->points->text ?? $response->data->gcInfo->pointsExpireText ?? null;
        $this->logger->debug($text);

        if ($text) {
            // GC Points expiring on 31 Dec 2020: 140
            $expDate = strtotime($this->http->FindPreg('/Points? expiring on (\d+ \w+ \d{4})\s*:\s*\d+/', false, $text), false);

            if ($expDate) {
                $this->SetExpirationDate($expDate);
                // Points to Expire
                $this->SetProperty("PointsToExpire", $this->http->FindPreg('/Points? expiring on \d+ \w+ \d{4}\s*:\s*([\d.,\s]+)/', false, $text));
            }
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        // Upcoming reservations
        $page = 1;
        $noUpcoming = false;

        do {
            $this->http->GetURL("https://www.shangri-la.com/en/corporate/shangrilacircle/online-services/reservations-list/?orderType=UPCOMING&page=1&orderConsumeType=HOTEL&page={$page}");
            $stop = $this->http->FindPreg('/"hotelOrderList":\[\]/');
            $this->logger->debug('Stop: ' . $stop . ', Page: ' . $page);

            if (!$this->http->FindPreg('/var __pageData\s*=\s*.+?"hotelOrderList":\[\],"totalCount":0,/s')) {
                //$this->sendNotification('check reservation');
                $items = $this->http->JsonLog($this->http->FindPreg('/var __pageData\s*=\s*(\{.+?\});/s'));

                if (isset($items->orderDatas->hotelOrderList)) {
                    foreach ($items->orderDatas->hotelOrderList as $item) {
                        $url = $item->detailUrl;
                        $this->http->NormalizeURL($url);
                        $this->http->GetURL($url);

                        if ($res = $this->ParseItinerary()) {
                            $result[] = $res;
                        }
                    }
                }
            } elseif ($page === 1) {
                $noUpcoming = true;
            }
            $page++;
        } while ($page < 5 && !$stop);

        // Cancelled reservations
        if (!$noUpcoming) {
            $this->http->GetURL('https://www.shangri-la.com/en/corporate/golden-circle/online-services/reservations-list/?orderType=CANCELLED&orderConsumeType=HOTEL');

            if (!$this->http->FindPreg('/var __pageData\s*=\s*.+?"hotelOrderList":\[\],"totalCount":0,/s')) {
                $items = $this->http->JsonLog($this->http->FindPreg('/var __pageData\s*=\s*(\{.+?\});/s'));

                if (isset($items->orderDatas->hotelOrderList)) {
                    foreach ($items->orderDatas->hotelOrderList as $item) {
                        $result[] = [
                            'Kind'               => 'R',
                            'ConfirmationNumber' => $item->orderStatus,
                            'Cancelled'          => true,
                        ];
                    }
                }
            }
        }

        // Past reservations
        if ($this->ParsePastIts) {
            $this->http->GetURL('https://www.shangri-la.com/en/corporate/golden-circle/online-services/reservations-list/?orderType=PAST&orderConsumeType=HOTEL');
            $noPast = false;

            if (!$this->http->FindPreg('/var __pageData\s*=\s*.+?"hotelOrderList":\[\],"totalCount":0,/s')) {
                $items = $this->http->JsonLog($this->http->FindPreg('/var __pageData\s*=\s*(\{.+?\});/s'));

                if (isset($items->orderDatas->hotelOrderList)) {
                    foreach ($items->orderDatas->hotelOrderList as $item) {
                        $url = $item->detailUrl;
                        $this->http->NormalizeURL($url);
                        $this->http->GetURL($url);

                        if ($res = $this->ParseItinerary()) {
                            $result[] = $res;
                        }
                    }
                }
            } else {
                $noPast = true;
            }

            if ($noPast && $noUpcoming) {
                return $this->noItinerariesArr();
            }
        } elseif ($noUpcoming) {
            return $this->noItinerariesArr();
        }

        return $result;
    }

    public function ParseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $it = $this->http->JsonLog($this->http->FindPreg('/var __pageData\s*=\s*(\{.+?\});/s'));

        if (!isset($it->orderInfo, $it->orderInfo->base->confirmationNo, $it->orderInfo->reservationInfo)) {
            return [];
        }
        $order = $it->orderInfo;
        $result = ['Kind' => 'R'];
        // ConfirmationNumber
        $result['ConfirmationNumber'] = $order->base->confirmationNo;
        $this->logger->info('Parse itinerary #' . $result['ConfirmationNumber'], ['Header' => 3]);
        $result['Status'] = beautifulName($order->base->orderStatus);

        $result['HotelName'] = $order->reservationInfo->hotelName;
        $result['Phone'] = $order->reservationInfo->hotelPhone;
        $result['Address'] = str_replace('<br />', ', ', $order->reservationInfo->hotelAddress);

        $result['CheckInDate'] = strtotime($order->reservationInfo->checkInDate, false);
        $result['CheckOutDate'] = strtotime($order->reservationInfo->checkOutDate, false);

        $result['Rooms'] = $order->reservationInfo->roomNum;
        $result['Guests'] = $order->reservationInfo->adultNum;
        $result['Kids'] = $order->reservationInfo->childrenNum;
        $result['RoomType'] = "{$order->reservationInfo->room->roomName} ({$order->reservationInfo->room->bedName})";

        if (isset($order->costDetail->chargeDetail->roomCost->amount)) {
            $result['Cost'] = $order->costDetail->chargeDetail->roomCost->amount;
        }

        if (isset($order->costDetail->chargeDetail->serviceChargeAndTax->amount)) {
            $result['Taxes'] = $order->costDetail->chargeDetail->serviceChargeAndTax->amount;
        }

        if (isset($order->costDetail->chargeDetail->totalCost->amount)) {
            $result['Total'] = $order->costDetail->chargeDetail->totalCost->amount;
        }

        $result['Currency'] =
            $order->costDetail->chargeDetail->totalCost->currency
            ?? $order->costDetail->chargeDetail->serviceChargeAndTax->currency
            ?? null
        ;

        if (is_object($order->personalInfo)) {
            $firstName = $order->personalInfo->firstName ?? null;
            $result['GuestNames'][] = beautifulName("{$firstName} {$order->personalInfo->lastName}");
        } else {
            $this->sendNotification('shangrila - Check GuestNames > 1');
        }

        $result['CancellationPolicy'] = $order->reservationInfo->cancelPolicy;

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    public function loginSuccessful($response)
    {
        $this->logger->notice(__METHOD__);

        if (isset($response->data->gcInfoResponse->gcInfo->gcMemberId) || isset($response->data->gcInfo->gcMemberId)) {
            return true;
        }

        return false;
    }

    protected function parseGeettestAntiCaptcha($gt, $challenge)
    {
        $this->logger->notice(__METHOD__);
        $postData = [
            "type"       => "GeeTestTaskProxyless",
            "websiteURL" => 'https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/',
            "gt"         => $gt,
            "challenge"  => $challenge,
        ];
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($recognizer, $postData);
    }

    protected function parseGeettestRuCaptcha($gt, $challenge)
    {
        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => 'https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/', //$this->http->currentUrl(),
            "proxy"      => $this->http->GetProxy(),
            'api_server' => 'api.geetest.com',
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
        $request = $this->http->JsonLog($captcha);

        if (empty($request)) {
            $this->logger->info('Retrying parsing geetest captcha');
            $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
            $request = $this->http->JsonLog($captcha);
        }

        if (empty($request)) {
            $this->geetestFailed = true;
            $this->logger->error("geetestFailed = true");

            return false;
        }

        return $request;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL('https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/');
            $acceptCookies = $selenium->waitForElement(WebDriverBy::xpath("//*[@id = 'js-cookie-manage-accept-all']"), 5);

            if ($acceptCookies) {
                $acceptCookies->click();
                sleep(3);
            }
            $changePassword = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(),'Change to use password')]"), 1);

            if ($changePassword) {
                $changePassword->click();
            }
            $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@name,'email-password')]"), 1);

            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo
        }
    }

    private function encryptPass($password)
    {
        $rsa = new Crypt_RSA();
        $rsa->loadKey('-----BEGIN RSA PUBLIC KEY----- MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC4cv/N+lHtp9yr3K3MxlCpgX8hwuLuPjxqmbKBbOFYaJEVIZOojsPrLyUZmSp4znByrzB+3kELmTRoTcZFKlIpzdk5oWMeSP88abPoXr00A0YXaGNzViJWVn04DqKKGlhl4R4TJ7EqlvLT7Lc7QCDZFIuBzwQnvhcv5qIu567kMwIDAQAB -----END RSA PUBLIC KEY-----');
        $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
        $rsa->setPublicKey();

        return base64_encode($rsa->encrypt($password));
    }

    private function sendStaticSensorData($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);
        $sensorData = [
            // 0
            '2;3225670;4272945;18,0,0,1,1,0;dsyQ8itHtg]zcoYtioDF<<LbztaF!X^s>?P([-AEaXSr_5*B&4qdm4||gnHS)G}6~~8&m!E&{!8:5RW{mxaNm*|b /_u)?,i/>fRA{s}fh! /Z])zt|vATIKfn7^JzN1?^w{h)OD,<Ny#Pbw&F3)D{&/4B}I/_En<[ mzGqjco)IT8X{&282c68(1OM!p:FU5D~}4Xz-+UMz9m6eq5* c=;Io5}L/tw2@1elzP381RiIhYi4QuqxW6LVxK,Xb>1l*VZ^^A&ns8+[xL_t)PZnm4=K3NZvvb[=$Ob#y?y$:v*z/}I/W$M5*x*;uE={!sps=z^h|o]~)b aH5WMq6WksJ[9b?6$|,)]bC4EfT2SuGyjH&}f!^{9x/J>6vh-$}6*B@``vuG4}:iOC.q+T!?vXrFJ7wV}6$q^+[@&E~i)qk];_s}<%8<{Npt?efHQuq2Gp^Ea%RcE9p~j-l#k`i6m4ZO`WuqiRMV5{||2M[m:OBOOgJkwaA3[pe/GHlD&F!,iVQLR>H+30FGE`P1w&%gh@n_9+4>wg]@SJ1khB?L5LuvJVkc_3(9Drh@x(4,(&1a[Y!^o~<Gk9N.VnRL]x0nEq4+*oM$b.|23a4b@-2b_aVN_9j`{x*mIR,2Li<p0m!M_~NR}q6< `*0:c.R_wXaq[l*^2urAG|,wJf4Q$0nEqjw9.UQG1aP/[#8]Q6C<^I=c-`PP,$kG$4Bl!9-;e%E4,R?0]{P._4zOkS(jUWY2,$`Jvx$Ek6pcc%x9`2gn(UW#]zhA(UX u#R2>v/Dq7*;xJaj8bU&t8/`#AdqyeQOS+InWrG/E/st`CQd:aZ9LqfG:_<FiH%<Mm7t:*d:z52R93>6xLC~,z2X?%g:7jhz*g&dD;UakIafREZ%eN-kHDC5g&o.Xc9E^BoL,&_<rS] %eADp=E3WV?3!B{Ap`Ql-s46qm:@hG^ {r8gR4:+BDnOB9p=qq_8a*|ZCD!}IA[*!2UtuVWY$p@|<vu9koiBfG40+pP9bX,Om._F}c&%QIV[vx_%RcnRjs.;5<+/g6VdfeM(C}$7WU=y:)4_7UcNdQDDKMZP+/b2DQ7vvn/3Xj<=l_SVu7>#YS0NVUvXPT*ey%r]fP)1yq5Rxl%_=l!|=+-BG_coUeO(EXA(26a~UaGcx{8| .?G@JPf+JUpdjG<juyalzQ~CU:^D85#}CnRBN;p~WFVkpyRf9<yRCs7o8u5b#66-h*gh{qSA}!@QeINtB=6IiGeM|U$[1<y2^ 5h3=V,9a!0;Zt1~Q)[sLz]8(O3OxvnN ym8NoIg#TkH(q[4I;R~c5*[R|i(J][)qwo2K|G,:}7QLa1m}(a{YG?n$<$L!038Ldj5C~S4N4KgkaF|/RW;&vnhcXuN~,<J27?i0hA S%x[kwtR+B#K<!.dnUF!Lg$n`f$Vjd4(R7Z0OiG:ZsBw#kmT`OGoa{3-w4M{B8wg85`.EfPPn/pGM>O=N=X}Ys72*IwRwrRF&qN*<bZY(A(uukwu-9*XYy]L5[xhA$sxV?#l+_RM|A: 2&07URZ=+h4.4]1oQI3>xBTDn7?;{FA,7;Ytwo0&q`n9j=]N,.IS69tqgrv*KJc_y0WS:5sIAl<sN}P8r&us)=EBoOO[Hnt1Lc!57)4-i@t;I~yEBTEXSaVK,Tz3vWaK#8xt8JfmxLGa-S%/!5)UU]L_:>7A5%-=V`La6Ppf sn~ tXadpw8DmRL2(@iW6^gCMqC|Sac)qgvPIm=%)(<*:5JD}glcih@LfJ)K2HFsdj*L@.TCeyPJ#&$zq=]lcu&k1w5 R9AV[L}GG.FZ}5Y|GPckp xWXmE&?MNI*J+&17l68.JP*L~3q~@;0[AQ^s0 EPmY3piJ1BFwu,LN,O{E%6fZdAq!/V.uAWilL&fz2:~XYl-:(X.p Gss)aH`]9`Dbi?7~,iG?~6~:Cb!i8,R/f{5Jz%t/j[>kt_]9:rBseZN l2jLzX7}H}>BHn;RIjRSX8G7%b@Q!jNp#f1oOH|a9q+C+*@hM$|p{s+rd%5`sj`-AvN7{tqNfQu,2!Rj`2@AN=TE<+ CidP2Z,}SA@~,B6S~jqGm^HIJVZ#i/][(KeEp;0^XXQ71m_vSF_8]k3VLiu1)72)P(/>i9+]YMVDQ9xZ3|Ha:@(BAnw02WMDnU^iIxgISQUDIF7h;I`E||l,)Ym?HWyM3v1/}Q=8@7[UR]5{^,}VDXB.vE_G9PTZ;.KuJrPKNXy5VH;w_n5*]et9Z!Cs%ES.N8gu20DV.4iT8Y<yVsA5idDN$M;gA[ON!B::@&I%(PNTifp_T}:Rv>Jq>z;{Hh<0:6Q9XXmk,<ZvHATDA]&(+_S#A)]Ieeafxx5*QUjt=Pp1WlwvfHsot8K+O.razl~6@To_BKgdNT,Gm)C02tARL|<9P/rnQw1aeeK~%xGd5I$P*9WeV6!5-tmlM,$UC)Iy%Bme',
            // 1
            '2;3289654;4342580;21,0,0,1,2,0;oI4dmWw7WNM5EA<0,kQa3sn3%vQ@yp{$xRTZTu7HG&wwss&yrTQ;?+@U:C&7.&2b]jg$ n?iTwR+^H*x9kISu5m!]:7]<Sqj4,_xiyVwo_`W.()AG*:c;>*j2mMMFB78qMyQI4Rt&sHq]JX<LyFQ${5c.3u?/)c+^1lV<y:7UgJ;Rc-^BmPI~Ja-q$NoR^xRZ[Dt#!B|nQ]Ii-Z@ibp@.=wC--BB~t`)(%+E;X~<c=n[`[;m_U;a<eraIJ:P+E@W{uhY8tyY;|Iud77D3tx@{,3A@ i4SFjGG3rdn%+~mB:Y$;(@F}ia2L8kn+f*Y:r.YBQ{}1?j>b7S[#U[1o1$gXziJ_x]+dsIYZ4>3h.KjizG2OpD #@URmh!ielK>OgmP9z/J06x+>e*=h@#ngwZ+W|9SZ(TihV2ccjoBE5-a<]^}.WI=4lnady!RI$iYvkHk(q<<xlsu[[<hXu(#8<LhsdWQS]b hdYnsi`SFy.y|{Hvpv`LQdv]lzu`#G%if!dHwR,dh^Ay3q,0[h]/k*i#$u]i(ZGpkD~R9BaT6awd;qy-$}d`F]IlUPoY`1!XN#zH,CTIFb&pVSHU@[0L8n;eV_vg`RH#teQ.jE&:yMkP_kawhxE%nf~t7:Z)xN]Ck,|T0szvu;STE1Sw1ML^?>k%?^/WK#Y|<tz.t1uzWLpTDU]6Z=D^LY7,Sd>mO]``_NU?4]7>sfC:>OyBOrz65-Rs-8pnXU`LcUh]*|iD#d;?E[g*US(A3Ftax)s9P?IHKU8:_0n D(iip8Mt2]zD@S8y;)6twmY~A>`K_=wF-@21~M/WpEj>2O8)}_L&6@Q_z*@EP yDXPBSf;H(G+Pt @1-PpPMa[t&K_Vr3wm7O]Nis@S,Na#Hh,~w:jZGgk38s1OZb]X(HBG?|R f9yTvAgKO)TVB^tqX-:DZB0*f=08T|Q lJ5d!Y(2n`=>BZmk<AO}AY2V3jpvAfG~wOF~h RI^usyy8f*J1S4QqGgd7A^2N?r^CAx`2ej%U?L[%FpufT:F)`4rOz&T2iQZn3v],kzD{dz%YhY.H=U+PXs$i2DvS9.1ru^@5l-8+NzBz<%[)wN4j!3%vpU6adQ@NW:B#dr=;sF`7aB6YSEmWf*!srDJ-|I`&1tqSDr$ )~X;mbK:!zJ8CIGp-NQ*))9-KBA+R1svm9];slOV@>se+FQL|Ey{5 |0hanLJ|O*]/J.zYX^WP/ LI{%S$*X$#M+izUnu<#[-d&OCrUa-Bqqh2:_ebgTwvBIys*Rd^9Hf7]dIZ*UTUA;rKWK<:>-}7)8ZLk.8o^f}]#feq-G<RD%teL/lGT`}k<$G]u*4g[ mvNTy?e&r2.*lXn#)en|Af>J(1_sd`XS5|_oi$.EWlh{=Jh)}fv<:tPkXJBR[YNQ2&bwv>Jq$;E`D/Tsyi#9[3q0$.w,G=/L+|u$zPa!@_q,cum`_J{1j3_@/br?njb9`mfn KeA2w*L?5v4BBcwT wX?nPe39mcv}>YhC]<V|7bOQ-krv@k=ppBlE`uGL03wos9K~I+Z~@[BMR)4fIcfqXebMT{GZsH*8Cr2]/{Bz}ko{`9Q3~j[8f[wSCxP^ze?LadZ4`K*>z5AN_?UP2.y^ZV8,4WER{gIjjGoY<tN8G=Pb^yk#a?)!4R!cgH7CiWECbU&eI< o:o)Rw=aug<Bp;3856{b^@?5_pN~%Wf78XJ`!&RLsYn%<L;aRTN0Q1,{IiV5&s_LL}e%WWRzF>,>9MDgCx]M)TIW&T4ws``^F9$Lym}RRf@Td>JrMypj6t{(URT[aHSHV/%U+w@ybV?Uh1p]V rzD}7ERRG0=bDC?5$6*GWrf#}/;dwEOofP}cV[+QO/ml~}2^uO5,RE,h10K8K{+S3KQ.AgTz&ra{VXf*CG,4ZU7GFizYvP=,4OtsGc ~?6HPlJv6=q>qPB+;Cr5v=h09u+d8rYB 97$G4>K>L*Hc5*!0GtAaF(L)9CKHm5||$3Y^DOv#!RQCka>a@o8|W:k./Wnj0wGauvUc6Tn)E;5k/Eoc W%x-.Ize${&hy,<#c@G=}La3;XaW`Wo(iBRggeHd.9ys^U:mCq0i6l0KU_-m%/]tjs6!eN?=*C#+kwwP`9^S Xt7=Zkr4=wJhd<5Q*oI*/M(t07V/s;-G5Ri*H1W09~^H8,tVJ`2a%?gl^!x= `{yZ~9V,}d(J!gH>Gz:3][B_|pAzAHF,WT: S-<l2Lt5PJb{NJOEF/m&WYKow:w8maJllKi9Z$TmUHNDGB9DfI/Y,+rMpO4z^e|= d$S]7}I9+=4@Vn[wQQtLKPu>5lo^ZA9{o<rjxIoraliktZSd7;6Zbl8b^BI+7-8zbDsY&7 72DwP~;8a+U&[p*:$UV(mpr)nZ0cw)iHQDkt6Ly I4G(cp-pHw!EvLGW}y&gwc_]f]-7a%@]+ !mXl)l?QLR_nRp`Z@IgvJ?Odj!PyG`!KC~7.Q@;x*^<ez4&9cdqZ7v%Bg.^|RzPad^F0-!p}}DKm<Z?]hXr;:~=W%J+I&ae8WGFw19o*,Pf+ROt7qr5jkW#/b3wE,M1ypba7e^RgvK:#Eyr;~*U(0~UaNSe4~R*FrVnT~ x/P*;~&=P4.3aGOLk9WLImdAi>uy-Y;@qdxOT6iA]0&(nU2S:Z=)j$2#q3fr*g4PUv>i=}E[)jmr6&Vp8lTA2z YJyJhC`>NW%Q3',
            //            // 2
            //            "7a74G7m23Vrp0o5c9135871.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.182 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397155,1664336,1536,871,1536,960,1536,448,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8920,0.03086956615,807070832168,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1614141664336,-999999,17267,0,0,2877,0,0,2,0,0,72C964343FCC43051F1F2AD862A7363D~-1~YAAQv7QtFybRObJ3AQAAUyxX0gUq0jGYzaJsxYhnBtpLNlYl/oOcO9tz4JzX/krQIR4HZq4N2WcSQmbR/mnJ+J074UxQ6V+f6vGOa0z9rC+QGKAZgeNlSlsTXDHFUob1+TrFS/I+TFhYAA4T5R481KconU7u1jZZnwHmqk7jLQk9wNuTwXG94iTr5b3xsxl6P+LOEPJWoJauF57/3tmkXvB8p36XS+QElt3NUQjlzSjYPCC3mClFOGayMJtj6bLhp6xTcEDJ/HMzY/mOBtzrimEritBnKL1RcLPKp62j/9Io0O6gTimNKkTlb1wbEx4bruKeaERV+sbHwRYI9LScBArqp6/IVfrGY1uiYq0N4o4C5AlcCBID9hslhzPk2srXY9csE3K+8m8doLoOCss=~-1~-1~-1,37001,-1,-1,30261693,PiZtE,32303,39-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,24964794-1,2,-94,-118,95174-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
            //            // 3
            //            "7a74G7m23Vrp0o5c9139621.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397496,7213457,1536,871,1536,960,1536,487,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8884,0.12326539261,807763606728.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1615527213457,-999999,17282,0,0,2880,0,0,2,0,0,C3C8FAFA5EF02D9AF663DE17B2216A1B~-1~YAAQbpt7XBNU2xt4AQAA6PbsJAXOMZbFDqwMs1OvQMkrhZPvVR2OYlw1WrLh6nrfH06vvPXaMrU/MNxHJfR0ua61xFlVgpXlatp6zkcIYiMrBxOUX9gIyikwKRGF4c12EQyx+J3r+U/h3pzrVypanB8ckxVbV9lqojP4fEKeNxlvoW2pS12wBUkJ1E4b11fbRXfAD7Q6Sy9+xVppEuCPPpktRyIGfwRwvQ2G2Mb84sB2plnP4KPq13f/R2ISBK5dkptrwqYvrr1oorTecjeaUIcn2almbthPDn0Spr+1FJCvgIzKgvyKB01rnsx+rS0OOB6jniW9wQof7X8PyyoxHsrQgrJB6CZ3R0sc4U3GveooYpUWXENgk+WgxLj8yw6D/RJLHms1JN/JWwH08b8=~-1~-1~-1,37982,-1,-1,30261693,PiZtE,71366,69-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,108201819-1,2,-94,-118,96314-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
            //            // 4
            //            "7a74G7m23Vrp0o5c9143071.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.90 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397881,1410825,1536,871,1536,960,1536,403,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8884,0.227721875113,808545705412.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1617091410825,-999999,17299,0,0,2883,0,0,8,0,0,C0CD7DA4A6C73AF8DB38235421ECAC52~-1~YAAQnwrGF6UNSHZ4AQAA4rEoggUiWx7I4qlspj1Le2WVyH2fEvau/01bFykRbEFbekf6h/pD4JnQiQSylgdhyLTB3lv33rbKxO5/zejmddDu4/CucojpqnNEekBklW19/dTgxjodSZGARmumrdXnBh4ZfopR5ExWsei7Y0w8i8JGISUhm48xCQcL4DWwRK0oH+xnvN3VKQotRWpYLC7zCxQgEsNvp7H9wtaw3YiAn07q6pTtEEb2IvTX89zNs93tRBCZ0F0cbpSNPrEzEHVK4qkpkd1pw41t0ama4Sw4hUhY7LJW5JgZkQL0Dg/XJCxyX0NeGObQJx0rm9MuwM4q4m9M0fcwEefI7LSfqG2J5CZlCx47kBxY1I35ubylxPvFMoPDvh8DyAKYr8G4IFc=~-1~-1~-1,37570,-1,-1,30261693,PiZtE,41395,88-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,38092362-1,2,-94,-118,95929-1,2,-94,-129,-1,2,-94,-121,;16;-1;0",
            //            // 5
            //            "7a74G7m23Vrp0o5c9135871.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.182 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397155,1550297,1536,871,1536,960,1536,448,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8920,0.644139442322,807070775148.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1614141550297,-999999,17267,0,0,2877,0,0,3,0,0,0DC6A0F878DBE523FE5BA8003C647315~-1~YAAQRWAZuIgv1Ml3AQAAi2VV0gUfEZCH0DmIjfTvEJ300pdVjX860FzkJ5kZgE3Hx5OZA7Z9DU9bzepIRfKwVp33gAXU85tCb3KvO4H6aVM4RtwICVp0DXmj/wACqdKKD6blLalp3N2WDa/DSWNbMPHMvfKlAW+ANIl727Rqf9PG2SvM116QrOA3dK9fr5/x33z2c53/G/vt7McoR3EMarwE7PXtp5ZaPyIO6tuU5PejJAE3JzDyNJUe3JdvHfF+vhIYAAdZt5mUlKfIeoBl1KW3diABRtgoJagaKdzbTIkyTlqmshKNDPqDhLhGXE31YCznDW7HYlQyFjpwbwiyC2bPRPBGjaLDYXa8boqEDMFAhWnXy6AyljWD0zAzgnOe232BQFVmCptdncka6oU=~-1~-1~-1,36683,-1,-1,30261693,PiZtE,76310,57-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,4650906-1,2,-94,-118,95056-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            //            // 6
            //            "7a74G7m23Vrp0o5c9141291.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:88.0) Gecko/20100101 Firefox/88.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,398520,809699,1536,871,1536,960,1536,462,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6016,0.02820305414,809845404849.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,0,0,1619690809699,-999999,17326,0,0,2887,0,0,3,0,0,EFBA05E25B23B82B45709D04001830AB~-1~YAAQvGAZuOI8nBt5AQAA80oYHQXuhFsrm3Wjjapx6dUExiNpPs6Us9zZej5MBCnPevwijREV5lYlkGylHrZ3vybmEniJgVJ15IPQusXfbT1Ig7TTTVehuDfhpu/Qc7Kc+ONlUnLGkMK01aGh3Hv2bweFl+8hLZuMezMDuCNk05dd311VySoqgk8KZ7TOGldADY7ysgj8P4aCWY0nldc9FHnQ+QqAjpy4M/BG8hdBzXtXxoZA1488bPwEVTIBcewJVI3NWDmDm6J/GRvdGc4OqksuvcWmfMwRIT6+FniNdS/EOVhoFbYarAhuSJjqvDMoH/qLA/KvqF/hfV5WMt3Ups4MbXdWhEzUpqfYxvViu28yi3LmwdIq4dAxqOGYZ50bFgQVJadSNhfCk4RMdjlz~-1~-1~-1,37915,-1,-1,26067385,PiZtE,58099,84-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,2429166-1,2,-94,-118,93513-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
            //            // 7
            //            "7a74G7m23Vrp0o5c9115631.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395371,1175826,1536,872,1536,960,1536,461,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.928329266464,803445587912.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1606891175825,-999999,17190,0,0,2865,0,0,3,0,0,E04B53ECD9B680B233BCDC6A81333251~-1~YAAQv7QtF0S/ARF2AQAAtn0tIgTQZH/N54xbabZxvHKNjbJRwlOrJshMoy+WHX2RRYg1uB8Qj3xO2dKdzsiuN+Hh0rw+2F72u9V7nGlMm0n7Dfx29Ur8e4DHXodSUONq/0ecMi7+oS/j+tS/KAEfonQv14UcdzCWCe4NRtvc3XMhRFL/WuRw0AI1qZ+qXAs0C20B0ylIDHHFuXUMY/zVH2dLLFkE46+S4bmoG1waEkEghg1uLmZ5MFOZsBXKi8ieWU/dgNgKul5wuBVKE/hzE4UD3N6OBzZFoMIoWY+t/Q7ViriO+/Ih/0+5blx08g==~-1~-1~-1,29084,-1,-1,30261693,PiZtE,16087,85-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,31747026-1,2,-94,-118,87468-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
            //            // 8
            //            "7a74G7m23Vrp0o5c9254871.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:88.0) Gecko/20100101 Firefox/88.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,398838,2310434,1536,871,1536,960,1536,462,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6016,0.214909593107,810491155217,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1620982310434,-999999,17340,0,0,2890,0,0,3,0,0,FF5ED289861F95F716D99BDA14D5EA75~-1~YAAQyQcPF0lboUB5AQAARCUTagUcnCHtrxzGHiz59Ah6Ftx8kU9mVT3HLTvotNBd/RC0xpIGZWwwV9BvXN1HMsYHOM1LCPbeSWOKkqLNJ2n6RPWi36x8thS2WV0u5sepEHiYJ4YBGMhr5uSwSMyTB0gO/S2pgIj9y31plUZ0w4/SxAQtFTFNxZVkUkU1nvfioaXs4sgvG83QgdUNKO8L4pzo0d0UbHrO6rQLuSAvk63gyGkucsineKrVzOaUMorM0yeDUndiRw68/rKWGmfjq9Tt6kg2eo2WpxVWKnIA/MRLC3/SMwGIqbF+sv0D8o97sVsu1RYTYZqCW2OmVA/HUoMp9RleXRvVbqEgdPv/LPCx+gSfzrKb3x3YYhXIuDG2d4hT36PhwdVraOVTu8dI~-1~-1~-1,37596,-1,-1,26067385,PiZtE,45189,80,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,173282496-1,2,-94,-118,93330-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            //            // 9
            //            "7a74G7m23Vrp0o5c9141291.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:88.0) Gecko/20100101 Firefox/88.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,398520,1108581,1536,871,1536,960,1536,462,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6016,0.635366826317,809845554290,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1619691108580,-999999,17326,0,0,2887,0,0,3,0,0,FDEC66BC64DEBA333BAB240D28EE4BF7~-1~YAAQv7QtF/3yaAx5AQAAfPgcHQWDHKwl1DM5ocY4stF1l2AhSaXx/QMUGEDwPK4HOUoX+yed04JBWt6jKYw+4f5ErbfltgkcSv8da0hSr66+1e9fCzgQtL5g2S7zLif2GSpq2bHqTPhCDrRuGyLbWwpdUvoUqe1erPGCC1qOhmZoVVH3c1pX07TZLWQZgZZIbedMMjIh8GAUftQG/XPxSzq8c+ELI7MD833nw4fvfz2laoLnhsQz6wVs09YgZH0iaZAo6zUCx8I+5XPvIfY+C6PntoeXmnAM97HLZBisi56RSik/o+WRBWz2gN70x5jZ8HfsR6+7Am565jhS2HtQfyW8tE+PziNlFHmWVtIiIV0g8ApTel7YURZ8NGS/7EjukXCj6FgVEHNldRJRSDlQ~-1~-1~-1,36911,-1,-1,26067385,PiZtE,46325,100-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,269385831-1,2,-94,-118,92532-1,2,-94,-129,-1,2,-94,-121,;6;-1;0",
        ];

        $sensorData2 = [
            // 0
            '2;3225670;4272945;20,24,0,0,2,0;,7<oN[vK3yG&&@$33)=QerJ/77jvBU4;`XCK2Of=|2|40WN`Sg? 6UO&07l Rm@U*,3yf(A+pMV`n2W$t{dJl2|fv|[-PmA`.J]=>dwpTd7mg:k)z,%{7OVMbp7XBzJyJYtV[._D)ABu|LX|+H5$Ix n/G&C(`ClAUxhtJ|bUn|va:Zs4=3/^-4,OdXzc6(Ut+L~qKR.)>@*@t4Wm0$._8@Rb4s1u@p3{,avr):68Ni!@wj9Lho!c:@M L&XS8>l2X%h^,,1>Wz/&n5f)P]iP&JI$T8zjU[9)O)FM5u$5m,e:xD&W~%:6mWGwG5|xcpPA}^d|fb+ c hF(WIm>W^R=4,`@)sy&yYb8|0)K8W[Fy]5yqW|^f/ws0OAwM5unymAD^Kpr=%FO%BS#iU~2&Fi73TwQ/ 6cF18LB>eQXHB)_&i3Ej!](PLOF7%|9zB<VNP>?V[sgHFqNUQ7mX6/q{Q`3X)KPSci1>952],7k A:hocoY[KBu::5[<B&_@7Ajc{zsL}eBG}x.+N;ad~s&$]?X)YUuK!WP`DQR07,4lJ>38@~RYPOz4nOiNT&EYRmqFuqz0nUcVF8g~j^QoWkE0E-R6@Bv)QpCK)A:iO%Q@~+l59SatxzZAj8xa;W+M$kIg7<Skp&ZmJNXq ^Nq~.ya wH<NP?_3b-U+RSZTp<!p38Vk#@zj)rk=cOzQf<?02%E-7LQNVOGIS[49s}_b$dvGshd7C.SDlS(q*QFP{bp],36]]>n{4Nyq8O^`=)O4rv$z2by$R34>lJ<kOV8p-:OscklBXN|uWH_ Hqmta[GT!8t5r}0Cuf#_=VY8nU74dsI6a<>c@/6Dh1wE.X(Z=2H1r96+NG.+u(XF&p/? qr.v!pO=]W&H^v`At5L+$`?s}v}[rZfKc*ww*`A@gLg$8:K)aGoq$mD0KS8jA{i?=#?/AlN}(o n$KY/g|{EO8zAa-*2J[|+L1Tcb~b{7kGA`D=0v3Hu2=6Wi`6*wb8m|>z$DnT?@Rx-!}K-rJa1(`u*u-hjgTQPMie5vS3BKLL6H>G!%X#}^vF]Ec$!lu[MF_/q*^fHqKSh6:-]5TUQ=?1KQd>U]k2Jk{0lVzI3{HP5_pXOH;t~Rku<`fNfdqy9_$epUxFJFO:ab0>u,,6}?Go%u26@5LbM>DR2:l4Tow~@p8sJu2XR|;a~JxDuQEyld~ae$U{7d_8y8hecFPTjL^:wL7;GYt[j}A$%]^zp;B6{anCuqE:+fKm:Y0QUk=inkS)g+Q*s;-0BeVE_s>8Lr1rikgYmx|8e@6%XgQO/5ZR$}fCw*/9CAlo.$VBaFWS.iko,F!<$(f!RHU-m{&_{Y>6ud<VX~(/wL`l3GuI1N0Iku%t=Wl@`ZB2umwEqHH[LB2Hr3p=~T5[&5B;~/CzD:&+mbW@zHb)lV`!Wuc*~G>a3L[q>]xBw#lgT9&vJA{21{,HyBexjE)V)?_TNi-vPODMBM05uYu;2/IoL{e~G,tF+G]WTSD3yjd|o-r{^Ur8CAfwb81nmOD!b*lRM|A: ,&3>[RZ=7l4(2c-czU16t&XAm4IB$A:%7+QejG/zz=r#2P}DUbSJ8D$uCF}Q261%4yk<c1wwsI~bBCth VxZT+ODlHW-G( u;q;|CB.0fC{f@!`~s}E`M3%H}fz4pP-W#8SkDPfa#WC^fJ%~zB)QJ-X_:w`M?.)6VZEi1Srm~fUx.rOebt&3=SEM7,Dc*B^g|Eq3tDSg}>k#P4mx%6Dxy:?H: Ipcap%Un=lL$HWrkg*L5 5=lmDJ|%$ufBhjbm+b,|BxEm9lOC&HB.JZ|?X&NRm^_*a[TqDz?+N@rNq-(,hB:&BK%Vviq|H1(a6?>{0xD7dY.|=aUb8nz5?Q-L{I.7]Z0@w%%d,gG5ddL! ev~[YWeo(fEq,V+KTab|*Yf<(-_N_soDv~Dkdf@G_@zY%h%R_r_:V]`rhTK05vo`>Te(Mfl2eM}]6sM()t2y=E)i^KK7I9*U3Y#hXpx_5zJ=a@j{&?,+O_O(%sqh%toJ0UoeX5<gJLptWHpPq---I`]6M5EDU@<T~IlZQ*E,g_8+RsM:H~vq:u_FSF/YwrjaZ{KBRz=3WfWK<&qj|XF*/dU3J?hw3-2}(b(Zrr3 abXY?BpW/VnA<htc?6!Nou2{?sR]eCodKN<uy%vr:6LgDq~l!*Zm38YbM*C5:~D<:B;NvZEBpKj(VmOIvv5V8,QXUVf#.}Ml(^V9+FXxW1P B;@T/vX*QS~> |g*N4j!g5C|JqkhZOoJ6q+,`qLuIp=<Y49Pf]]JbrzGCtpVG3CEx_{v{|Y&IBe|<108]D(ol*BauL#=/mhaQ^# 3B*]9]USapx1r]S]sBGk6lgmq{Azzo5F.Q5pSyn!:3:pQBK]g6Tx?}|p<<|=FQ)/ N6qa1v<`aeK({x>]9T(K(,R_h6w,4UmlC/jU;!F~v:tIbK0Dz@~P}8gZ09!)-j-FzxTH+CO$E4M?bq;eB&mgt6VY5?.=OsNEu-#:$|F|1Q,qLU8a<TCj_ Reqkjc^iF>  uu^R@t,R?$F_4WIwvJihEZg_U 4jUU:M>zF9 [tEE*@XEze:7yf8q.]R=g~rMiWhf^3pU=U.+{>]y$v`N1.^<c+1@M~qIXMsl)~#i_*QZm~N9wuK0QA+Y.,a]Qc3D[qKT/kAg+Z+*8l$`pLc^lic?cqe6M]rh<fv@=BEIBlVKc9jeDEAK:H),a4JK3hiix.ET4Y*+V~^i a0!q!;10G(*Z;OnM%74Jg(jo%D.M,f+3QTZOOTlk{nrdGPlH8PJ{.Wmqe&01xi <Rjppyt<VZdaC3=5VBbS0G}7o5ch~ZX}G,srDa,kh&(;vCBa]4Xeld:h0H/FW;Aq%#k~*E%):UL8!Ux6=.Qf;OE#MKOf-nFL~z]^OTV Dnu0s|<sts;M _NeA(j-BF?(XDGXk;Z 9R&O;4_^%1K,#mG[mjW`5O$;_1$+kfyVuKU86O(ebmdzf}.wJ n9dip]FBIn,a',
            // 1
            '2;3289654;4342580;97,30,0,1,6,0;vQb}jOx0 KD5E=A(5`Oh3sp1JvVH tJ:wR_$si0MA#r41zu{n`Nn8,$qrHusED.%[<Y)%t?gU4DGSBchaE/_54%_|e<7{?{d,,F{l}Dsgls[iu$QZ1uV$.$XyjPF1vilEN6(.E![TfKVhq.RneY0khqOzcz/y!%%=2Z<?S,[VVss6Nnw8,%-NML7kfCZXeVoWWuas%[K}Bf.:.{,p%2(J0l!NR@UL[=,LrGGO1Y7(tW>T,I~ul6JB@_C,(z;CeYzlxLB[|i?SmV}`Cg1[c]bJl|rTb`nt>GbhnPEZ+kr! X;iuCz@P$cPqF6n9+Gg4-aJggVx=11JP~E)U3a0$OLA<RCG^paXg]mpW%Ur>L9VtXG3L~d-)Sb34Jt)c5J_ AAvl@M1{,qK)B*g4F=:S<3;ZslQ^Xi&j 4PqoeNnB;u0FkE[>#u>3rd3a<VIz..K!qVvJG]X^iEnM,O-0cz039qpli?KfX26/X;GK&^0/YLU/0;NjtVk=SY2.@! gMrJ/e_a<X$`D6=p<$QR$Z--,{yMS<bQtW[Dm2Ys$=j|9T*Vpx2/6)-V|M?7R/@xj[B!;;Y(DngC6lXmdhU*:m4Eu_gmk|pMt_^Kyyi-.%O?Z4Cs0#4R92V vQnE-1im|4P CQHW!Y8li.Q$=X5q^h T@}x*d:}?/!Jmq@@|`HO{d&q.8b{tg,5R8dCU!cD`_?y&$+y9J#e&=T3|26e57F|wB@d;u(T&eD3 &Hlc &+^8LL3Uh|Jyr98B o^jYrT{-(&eF8>a}{E+ixVw2KA_$H:V;du5gzS8]`qM]IWv|HQ806Jh,XyId$6O<*KVC.:BJ@aUBDDkOx7!BOg/2n~8Pp @wc zUHTZp&Pa~r3wm7Ox^o_Inl3^uQd, |:mU#BD=8o-SNfXX)MB<exR #H @ 4iWW V`O^1O[yChzB4*e=7,{6UpqJj3L^!,.j==D]q9]?N#AO70=giyInB!uXAzc!Le-oLUy@`*JSh8EzkEs:9[%pN}QE:#`/cg&U;Dw;IhD{I7F(+6mXoP&nwVN;7y]6p#;*bs+QhC)iK G)(;)r&B}S9PEvigQ;o)84r9>{<$QN%l/oz8*s3M2flT[umA6%`tA9sF[<XK*~K=rYg!%{AYI-)q~x4|zRIf/)+<`?oZnU!fN,Csjq)NJ{1(C,mVG! Fgyu?Y=osSX9E{d!JEs5F/&1(NK61vRQ){Q-,P2J!`8+Sa-MrwSa+Uy[UON@GNpGsM$+6^J1n&9/aAeccCY-^b]JqFKzk.(}Q8Pd+X]IufXC_=>wSWy^:61Cs8;Ry!!5oVY%X.(+/#MIU@A=tx|lGXe+<W{Fcw/4lfs5rNX?_e+r56/e^q0(]szFc7P(1^iH{Wl6sizp$4H[dlo&^j~(av7cuGxWu:Td_Hv2*hxq(Kp)9<W>Qg~mkz@d2i4v2p,L=$P|yi|(JV)?_f&kr0B+%[1f. D*~KJblZ?lnYs W`o1Lc%3:v@>Eh T!|S9ik!9&4cFA>QrD]7N|=gTM-px}8:~~pB5gQ:cKH4nvw;D IFj&,efjN*4Hr={~t:$lF}OcnoE>/q)^2w;x5ooyWXmb.gSB8}kOC<lekg;PT2Z5dO}a$_W>lFM}G,}jUW[6,aASuZNjv<<BiXW2cDTkQ=h~l;*!.x!fbN6OdSEn R|.aGsq3p)MB>W|iE6:;382;{byGC7WvZ,~xuB#bCa+#sOrd8D0H;]LOF0J*# @G6qfPmKHdNqU:*M+ukt60y]=U7PZ3L-c6t]P+E-IuT|QJb2ZWDXhu(ClVELlM`a:yjvtSGJO0+^.5G|^P?Ud,.gb)moD!=9sN>VL04C?:&^*GM1k{~*lMEGNcmlI-S_5hpUcg(|.%zT7TRG*%2`e,N%1W`aI%BaH &~&4OUqM V/,XI2?Ue{_vLX77O6|BcI-45KNpw.2A}b0LC+R)SiQr^A>F#YWG2$Zu-lCaxA%L<!<jvDe~Myb)g!o+Zy~Rbeu{1bbAL}u#{gAomb{9n>vP=g4Zrih6qp`q|PV2Mr/D=7i/:=_v]~yP8dLV+*.^;,;Le;P Ex TCU#vrc|[0bb1|`Hm)0<xaU_*;p0n)j+HLZ-o|1_x^]I$]R5d2AH,b${S]>^O{]h>EW.q3FrK.ndSE,{Q!(P0|!>_.k?~nKS|3C1 <9<mN#5t(h^.j~@-v(Al?.`>zV&bl$|k$C{bPB=CB1#]9_$ng~>iI+bW>{uHBYVL8FwB_>ON]DH0p&znOc!^64na?4hOi8%&OvTNQEKK]dfM/Y.,zMdT4)YfD8re+URS$P;#:?DKm_uUUjLHTt@/ghfQAE$e=pioJpzYin1P.zNtPK8RGxC0AL-:{-BgDxkG:ytv:,r|fB]HC3BQ`1PTD;DZq+nb2i|w_hmh**6Hu%=Z^%Tu-lC;-9CPJWNJ!g!)~]j]*3dwK^$r$qXl%mCQ@XZxx1u _Eh|]Ubb.L%;swDOaN9ap]<?Lcl >YMP9#B[V:Ni4O$E{Dvx6{Dq+.7DBkt5=dc|h]rZQ%+[yB{S3a(VjR3t,.t0.Lh3Ptu.qy=phY}3b9|J(T+<rem3hcZgrFZ):qm9$*N 1&^aL!i++Z6A10yH!x]xEDQ}|jf/.<`LURk4!M@wkC&Iy|*_;59`{OvVu=KLgaMgnI7PBwjoc!a<bv+m8NIqX&GpN Efnr!WU`AhN?.{#QExRfha5ur,C5Q@rSvBZgC:O[@rZfcA#%gL!>TL.JS0P>^R0s8t/?/sce<XiL`OR2Y)[Y.ur0ApOB c;1Z4XpeK7Q-IeARUTLCqS|mVCl$n63`9!mB.`:4(JlWCwJy{hOU;^PWJwt0{VGX.G9o2&zDKDA3|~,aC^HSCi96;?4c~T]iL,,D%QOVTa0S@YSr|66[SRyV6B@()Mfi8JD9>=1;KoJa)SV|4TRj]qSM$0x08AAa*%~/D-QB}sYKU(`ZcV]c%N jv([GNP_Tff1y)2@YJ/h!pjRJk8sCmHTF&H[e2]H@4%Dorm5G6%_|MBa*L|7XdNcTk4o|W}un3v1(XEaFkfE7;.[2N5D',
            //            // 2
            //            "7a74G7m23Vrp0o5c9135871.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.182 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397155,1664336,1536,871,1536,960,1536,448,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8920,0.514075474257,807070832168,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,1,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,0,1,7,525,396;1,1,11,522,396;2,1,19,519,396;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,2829,32,0,0,0,2797,547,0,1614141664336,22,17267,0,3,2877,0,0,548,37,0,72C964343FCC43051F1F2AD862A7363D~-1~YAAQv7QtF0jRObJ3AQAAqC5X0gUguWaepAyz0GBoo2aMd0oZEw19YRg63I+KzjCJ0AKY3MLw+TB8vvx60fnSIkDc08ugJwRMpUORDVFgKbXlC3j46fRm0JREhJZW5RuDa/ox+WNsroPa73lS/U4jNT/+Mxq6OtVk2aSOrz5u2DtiOdS8jOMNVqibrYf9RESMBjNIFjvQ0puHExtjxNGYeicfYg8D8+4wUquKvwsveC/dgjU8fv9gXSejFNQbFztHKCqqNXZQ30KLLFusgGSgoBXKOEPh31s3GJ555CsewwT9T//eFyP12W1rskXineO6H5wuF7v47s98yrgF/El4KlL3YuAjhbkm5TyKpMZl5V8upu2PkN6EAE4Y1W2uSfSjatqHoGeeexJ38Hy0yUk=~-1~||1-cRCpOmPFVf-1-10-1000-2||~-1,38988,90,1280943102,30261693,PiZtE,70350,106-1,2,-94,-106,9,1-1,2,-94,-119,36,33,37,37,61,59,38,31,35,6,6,6,11,367,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,24964794-1,2,-94,-118,103044-1,2,-94,-129,c73d9197239d1276bf2dd0921e5e4d8ab70cdc09ecc3a52921b4602b562571ba,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;26;6;0",
            //            // 3
            //            "7a74G7m23Vrp0o5c9139621.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397496,7213457,1536,871,1536,960,1536,487,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8884,0.400876193200,807763606728.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,822,0,1615527213457,27,17282,0,0,2880,0,0,823,0,0,C3C8FAFA5EF02D9AF663DE17B2216A1B~-1~YAAQbpt7XBRU2xt4AQAAjvvsJAWNDf2WIsOvnsvJ0AVP1D9WE/sYvuHPjmR/xycHNxLm/GaDM/Gv9lIKXHEP1efid8wrBpc3rROD313QV016d2cnfHVrqdoH+K1ZrsgJNHMr6Gz1iXiCsgfTre7kSGim7P7Jqan+6qcLZg33fRMyucWnYWMCDWg1OsyzXXxMxLEp5mv9riNQxNH7apfRn3VandRMUEh9Bsf31q2X+j1SFwgyq1f9yQdIanjVJAJ08fuAzPtdf1Su3wlAJXn+9+li+H6Fw4E/im2/lr1+JusJBBqo2X969ojsUJKIUT8R3QtkPAQwBk0BwvJklMAMYgqMbbHzW9OumAnAL1Qnsw2aoMRsO3CEbH4OxfVqg0aCRcdbjInI5FAr9GtFmyk=~-1~||1-GOZDAyoSlL-1-10-1000-2||~-1,39342,23,-1296817655,30261693,PiZtE,53857,98-1,2,-94,-106,9,1-1,2,-94,-119,28,34,32,33,50,52,10,8,8,5,6,6,11,364,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,108201819-1,2,-94,-118,100863-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;28;6;0",
            //            // 4
            //            "7a74G7m23Vrp0o5c9143071.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.90 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397881,1410825,1536,871,1536,960,1536,403,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8884,0.312258098156,808545705412.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,1,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,1480,0,1617091410825,100,17299,0,0,2883,0,0,1483,0,0,C0CD7DA4A6C73AF8DB38235421ECAC52~-1~YAAQnwrGF6UNSHZ4AQAA4rEoggUiWx7I4qlspj1Le2WVyH2fEvau/01bFykRbEFbekf6h/pD4JnQiQSylgdhyLTB3lv33rbKxO5/zejmddDu4/CucojpqnNEekBklW19/dTgxjodSZGARmumrdXnBh4ZfopR5ExWsei7Y0w8i8JGISUhm48xCQcL4DWwRK0oH+xnvN3VKQotRWpYLC7zCxQgEsNvp7H9wtaw3YiAn07q6pTtEEb2IvTX89zNs93tRBCZ0F0cbpSNPrEzEHVK4qkpkd1pw41t0ama4Sw4hUhY7LJW5JgZkQL0Dg/XJCxyX0NeGObQJx0rm9MuwM4q4m9M0fcwEefI7LSfqG2J5CZlCx47kBxY1I35ubylxPvFMoPDvh8DyAKYr8G4IFc=~-1~-1~-1,37570,41,-81810899,30261693,PiZtE,38264,49-1,2,-94,-106,9,1-1,2,-94,-119,40,44,43,49,65,67,41,38,12,9,9,9,14,541,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,38092362-1,2,-94,-118,99230-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;65;19;0",
            //            // 5
            //            "7a74G7m23Vrp0o5c9135871.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.182 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397155,1550297,1536,871,1536,960,1536,448,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8920,0.889957758444,807070775148.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,1110,0,1614141550297,123,17267,0,0,2877,0,0,1111,0,0,0DC6A0F878DBE523FE5BA8003C647315~-1~YAAQRWAZuIgv1Ml3AQAAi2VV0gUfEZCH0DmIjfTvEJ300pdVjX860FzkJ5kZgE3Hx5OZA7Z9DU9bzepIRfKwVp33gAXU85tCb3KvO4H6aVM4RtwICVp0DXmj/wACqdKKD6blLalp3N2WDa/DSWNbMPHMvfKlAW+ANIl727Rqf9PG2SvM116QrOA3dK9fr5/x33z2c53/G/vt7McoR3EMarwE7PXtp5ZaPyIO6tuU5PejJAE3JzDyNJUe3JdvHfF+vhIYAAdZt5mUlKfIeoBl1KW3diABRtgoJagaKdzbTIkyTlqmshKNDPqDhLhGXE31YCznDW7HYlQyFjpwbwiyC2bPRPBGjaLDYXa8boqEDMFAhWnXy6AyljWD0zAzgnOe232BQFVmCptdncka6oU=~-1~-1~-1,36683,73,1124406630,30261693,PiZtE,63867,31-1,2,-94,-106,9,1-1,2,-94,-119,33,41,47,58,102,74,31,26,12,6,6,7,10,511,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,4650906-1,2,-94,-118,98458-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;123;8;0",
            //            // 6
            //            "7a74G7m23Vrp0o5c9141291.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:88.0) Gecko/20100101 Firefox/88.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,398520,809699,1536,871,1536,960,1536,462,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6016,0.577628325288,809845404849.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,1,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,511,0,1619690809699,6,17326,0,0,2887,0,0,512,0,0,EFBA05E25B23B82B45709D04001830AB~-1~YAAQvGAZuOI8nBt5AQAA80oYHQXuhFsrm3Wjjapx6dUExiNpPs6Us9zZej5MBCnPevwijREV5lYlkGylHrZ3vybmEniJgVJ15IPQusXfbT1Ig7TTTVehuDfhpu/Qc7Kc+ONlUnLGkMK01aGh3Hv2bweFl+8hLZuMezMDuCNk05dd311VySoqgk8KZ7TOGldADY7ysgj8P4aCWY0nldc9FHnQ+QqAjpy4M/BG8hdBzXtXxoZA1488bPwEVTIBcewJVI3NWDmDm6J/GRvdGc4OqksuvcWmfMwRIT6+FniNdS/EOVhoFbYarAhuSJjqvDMoH/qLA/KvqF/hfV5WMt3Ups4MbXdWhEzUpqfYxvViu28yi3LmwdIq4dAxqOGYZ50bFgQVJadSNhfCk4RMdjlz~-1~-1~-1,37915,648,1089133076,26067385,PiZtE,13010,57-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,200,0,0,0,0,0,0,200,0,0,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,2429166-1,2,-94,-118,96305-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,cd15889e4b58585ec9f3c796725c752f6dd7926965daec54124da062a5aaf8e1,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;14;5;0",
            //            // 7
            //            "7a74G7m23Vrp0o5c9115631.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395371,1175826,1536,872,1536,960,1536,461,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.869892027434,803445587912.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,1,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,537,0,1606891175825,15,17190,0,0,2865,0,0,538,0,0,E04B53ECD9B680B233BCDC6A81333251~-1~YAAQv7QtF6W/ARF2AQAAp4AtIgTaGZ+cyXwZjv4QlWXufYpXOQ0AR4RDotYCOgK15S3sPrU3HAEQgnz9obKyRj5miC6VNqUKLgqQIclyk7N1TuSKbqat6Ne4oeO86bvJyaBEnV5d7Hl1LB6VCGT3zZLC5rwr43REVxiyPTAT1wFt1Q4OnOPpnyim8D4uBJkD6NW8s1FzRBmD2lFs2NyDT7Q/2rFtRyLPjb+uFBUUkxQn8apOZAutblv+xdc9e2Kp1lqK7y2WebKLm30ZG4zZNzvyAxJowMN0rslLxmoTW4+frcWqSxO9HFsqLufcvP5SPQnZ/vjboh89lY+u~-1~||1-HRhUwrylFI-1-10-1000-2||~-1,33692,71,-1741552716,30261693,PiZtE,72352,47-1,2,-94,-106,9,1-1,2,-94,-119,29,31,30,31,49,52,35,33,50,35,6,6,10,355,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,31747026-1,2,-94,-118,95391-1,2,-94,-129,c73d9197239d1276bf2dd0921e5e4d8ab70cdc09ecc3a52921b4602b562571ba,2,0,,,,0-1,2,-94,-121,;5;6;0",
            //            // 8
            //            "7a74G7m23Vrp0o5c9254871.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:88.0) Gecko/20100101 Firefox/88.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,398838,2310434,1536,871,1536,960,1536,462,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6016,0.383485181191,810491155217,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,1,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,0,228;-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,1279,0,1620982310434,6,17340,0,0,2890,0,0,1279,0,0,FF5ED289861F95F716D99BDA14D5EA75~-1~YAAQyQcPF0lboUB5AQAARCUTagUcnCHtrxzGHiz59Ah6Ftx8kU9mVT3HLTvotNBd/RC0xpIGZWwwV9BvXN1HMsYHOM1LCPbeSWOKkqLNJ2n6RPWi36x8thS2WV0u5sepEHiYJ4YBGMhr5uSwSMyTB0gO/S2pgIj9y31plUZ0w4/SxAQtFTFNxZVkUkU1nvfioaXs4sgvG83QgdUNKO8L4pzo0d0UbHrO6rQLuSAvk63gyGkucsineKrVzOaUMorM0yeDUndiRw68/rKWGmfjq9Tt6kg2eo2WpxVWKnIA/MRLC3/SMwGIqbF+sv0D8o97sVsu1RYTYZqCW2OmVA/HUoMp9RleXRvVbqEgdPv/LPCx+gSfzrKb3x3YYhXIuDG2d4hT36PhwdVraOVTu8dI~-1~-1~-1,37596,329,-559103099,26067385,PiZtE,80408,48,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,200,0,0,0,0,200,0,0,0,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,173282496-1,2,-94,-118,96552-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,0,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;19;7;0",
            //            // 9
            //            "7a74G7m23Vrp0o5c9141291.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:88.0) Gecko/20100101 Firefox/88.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,398520,1108581,1536,871,1536,960,1536,462,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6016,0.10923126154,809845554290,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,1,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,0,371;-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,1442,0,1619691108580,4,17326,0,0,2887,0,0,1443,0,0,FDEC66BC64DEBA333BAB240D28EE4BF7~-1~YAAQv7QtFwTzaAx5AQAANfscHQVgk6j8Ql5DnNPBT2FF/r2Cb6658T9pDNivFyuClrnrIFwLfBw9raVj/afNR3A216aHwudnqgOZ/cAKczbmOCmMZnfgegnmfcpVgpIK2EfhurtXbI3XQL7yttosS/mWygajrEpDOFPFIex2phus4+k4zyVRN70QiizmSwd0htXBLeYVC6Gs9hU5JW36dvK8OZAWYDcZR8NGy1HSa+1o7x/fOhVdKFrb/XF7+RiLN8GvMzaBoegPdKCee1nyUTtD+eyDcA5Za/sFpOrKNfEOJKgZ/OAZbLbVOIApkAnVIRdaT9wtsdmkg9YWnP0lP2p9Aon4ihOUFbdPZ91fV7LcUwZBWf8L4q7gWcBR6DGLhCxb4v3xT6RqgCsGf7rL~-1~||1-aAdtALAiei-1-10-1000-2||~-1,39621,884,-1951845078,26067385,PiZtE,19040,88-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,0,0,0,0,200,0,200,200,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,269385831-1,2,-94,-118,98514-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,cd15889e4b58585ec9f3c796725c752f6dd7926965daec54124da062a5aaf8e1,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;12;8;0",
        ];

        $sensorData3 = [
            // 0
            '2;3225670;4272945;6,24,0,1,2,0;sqhySZuE-yp}ywNtewB}4J3+=3T I.&y-4Ow&OpKwSjifZN`S]9 >_K(9mdN!;zQ|%5#e#F+zsy:=ZWl} 2Jn+|l#*V~Pt.c7>XEJa!pXqf/.Lq0R(`[3UMFbj<bHeNl@WpcZ1]L5<Jy&SXx+A3}My|h3B,**g;fGSr_&II^Yl*JX8X!/:7,j0/0MjW~b6xj<zsuzQ~ |6F*5v3]l+u6js;tHiWQ3gc3!5[m_X;++ZUFh5`4Mmp W-TTKA#^X<2qO 9bk=,,m=Se|c0s-^Om5/J>#J/-u-V>![0:F1v*-i3`<xQ/J.+>*r GXBB{jrw)=rWe,ke!*ctbA(SHm>LgS=c%T7)o+zQUV9|0UL3N[JzY>yeQy`f  `8V5usfvmnM<vjEsrhx?xOtVP3%S2NE?g3Sk@lS6XsehS<~gSb%i]p!mZ=:|`!TUOx7c]VUw1PIPC%_D6Ag2q`=DD$*ZQ{EN^+XYHJFgpa`>h3]1A>|Ag@nktR`G>w>GI(fbU_G0Dac {pU}5>wGJr-&545~hS(,6(_Y_pzQXv-?KL1J(4eD75?BSHRJH|]r#cJEbtZM`NJEdmkA.hZ:=*?>UMfkqu#KaLciJo)T@J)*2CZo-}k VwA<S4#{GYx?5|8VT[GvCJ`jo+j4$N:RW1 QQUvu&z2 8P<wP)h70-d/BSD^th!u,3Wk*@et$md8cPkvcW0YD+CwNW%N*LA<WV/?{}U[RlvqF7pdD0Sq=c(e+:NX{QSFq$@f?c/GQf^5]tq=W4{fo!cJ^%?LxZVZz3e)[2ZH2,N~.`C!C1f$UKta+fVW3+$<Z5Ljt~gykAXVm2BuF8ap?i(k.~xF}fewGsDzc[h/oo$nKs9oM().mqQLxf`2w[u2e+dCpYKkAceH<ge`7}pofuXf!^(QL1EcAkDA=mW#@X2RKW&{.P*<L&35zTqZ!-<^OwmKB*-txBfVV8]b;d+;7@VMcp8fma1&*3aA? )$v[,!/QtkYDTzl!#:qr>gjj@bN/)jkWR3%Gl#.* qh~{-@XWB[X.[o<sl^cd(Ck.tY~ZbrM$|Y[8FQn?}]psW@^,aN;H@@>P3;]ds<9nqi%7O_<HeI[;#55(WW=GG]SNf!l2f>2oSn`l0F1mh]8n3|[/tgjm#OcmYN>BQp7=Z;XzYnFep&3sZ)L4rqx,0JbugbO=e}oXysDZ;Y6P)4:!y?fE,UF=q*Mb8cTFk,,$6Gr6o),<:}C6*C%p`fy{I~s?V`I[tI@7QwGgO,o/d 4$>M*+ 1AlrCm/-TP<jAC/`rT$i7%OwxQK_!NKRq>o)@ZO:]%wY00:^wSI,)J(f}E_?1rjn(F.;(>x.RHY-m!#Xw`:2whI~T!!<sThg:C~Q1N{WrM~s*[j<aR;1kqARsGUkOG?BsB R5e5Y@6NF-3R6KD1;i{i/wMRJvh(C&m9D*~sojfwPWg!APW-?zCF-xu<x[_.7MfrpF#GpJH>9`P_5umg([rFb({|z8?0QMJ/_ZlmOZDyu9:%&qs+4tM6W!)]_+!>v~AMCxgE@D@DC%lE!)_/k<qwKe7JxsH4%yYx)taKS}MSJABh|Z`v2kMO(|H,r!Q6ik*tkM$jV7))9!32NVgMPp8t$-P{(J(`MiS$Ys%Q!Z1}U6fh&]jOG>t%k$2$7d_<:D%d|,2$!*HFzwY:9S88V/!OfKWwYv?~IH}h0yIm:rUc3KN-Hl hV.7~SINGt<=2pHv,!c:#qpyuEg|>+p`cpo1hx,W4*^x:BBI&{,|m]Z|H}k{ZR=d4wK((9%<J(JPK#yr)Si5Z<fteU9Fmu.3ET^QZOp#iTT.^T[Z ]xCfb3.AF{ifb9t0{AAs-2i_zAngf+Q%B{7fv?5+]<7,Z1y88Lb7p:j^k8j,+uI!K-Dkn1i]4pv/]$`I ql7&C{3AwK]o,GxC~b-|nxniIRa8[D++jdI4i2De;y9*f0i9#=/C|-;|a!neD1olV`=-cKPm]Ih_9a{Kg:xT]1K%r0M2e`GS@E;%LGWNa)SM6koL=ye@q 6?1v_91*AqtlpqF9ZtjX*Bl<K{?LJoUp|<. _`?HBA@WDI|iRj3P-R0hS=4 gNwP%jmGpa?@Zb%lilaYzMz~I<1`1WO2+dcuWOV!]H<O?jo1)94<Y40;lI/dbdoTbE}V7}2yH@9[Ks(@7h]<z:ap?rkTPPUHSH|`?NgBowc(/]m>}[dM6W1<qLE6D7R@Z&=}PjzbBICm%G_+1]cU@0>QB#vtf(D5N;-#aF,sipH9Y|>`1P~VG0SwV7=1IVio4!5rW|=0s+,jCRwens;V_{7o=P#bnwAGs`Qw7~<Y6ng)3`HW`LV/[?+(jLep->arAFbO<b/z%GN1A1IFe@]opc9rYV]u@Tt19LKzfEmf|6|~Rxnauhz6*SvrBG+lc1,Eq!r9fFC$&MgLGgqg8E<7hlu*$|BccR1R*716]ByVb]ufp4f_sUv~$4Dz^K/9w6$Rv1bhpS6IN,/zwR,^5}S+BI-V43$om(oAt6tq&e^t,]+r_g>C4/F6CR@-D>)Y~_Jua&Rr%r]l0CesXlCK83zo{]BZB`1[I{~P7[BZohI!zrUJ*WIz::z[ E9*6hEI^;6ua6t4OLEa~z:dYacdZhJ8c)-t8]Ocs`N5)cAh0a3$OGOZ$l9Yy/D```1/)Q9MJO2 ;)`..bb=bVDZL=x33C;RX[18h]V/bP1IS4w Lvui5:/=;Y<c7 uBkUTa:nZLL;@B504U6/I9^cfs|P<-/JGK+Yq/h8!{{,1,E%}_7HgO%0.K]0Ak(7$T,7~?~HWOWXg[-yFWEUqM8EU|+VqHf(+: [&E ^fhyz<GeV^I1J]S@YS1O{>h(jhOhSrA]CyDeN4oa^B}k?_T4]r6`<c=M+6WL)d3ti!$ED1:~LeRLt?E.MfDY@uVxCgjv{@|vbtmZ^W@hq9sxAt{s7S+YBnW!l,ES:zSIIfg$2ZxNu]pkJ G=.rQ,kAR-r+TW^Bkv`]&YyXvo(UKG(klK(rI*x[L2nAcsuX6HIg#W,c*`1L-T5u2nB,xXSD1sC9qe2rSWNFP[RE`;#KpO3&=N2VK5(!`[To[w/m`enKjKs CZQ)1M0+>V<_UFJ=>0-`BVok^r L{hG3le!h}6rn;L+msYbYeXM1x0WSN[sM3YaU.#X&@kKY(8#`pammyvsY0m5+-8tSy egC6iTo332E)w]g^rw&%,rg4jnb(QF@N<a@D!].adm1UcL}Eqv,*C ,Qfgx43W37|Q&x_AJbr|R6cSi^^d|&:&8>y y4.rgwe: 3ts$~6,lOR?jG2G]rs,&[@WY7lb=?>wpp``#*5s!asX2udx=Nhw}L6MvH.*{7&IDLa|HR<6}sow3Y~<b+4J^E(Yo),(*6Hhn~~bdQa-y_otM^:^2Lz#9/|>TTlwi*/j:|yG-$gQA!BiiI{F=+x,O$+iecTeq6CgqKM$46bv,)Hh%hPsZvQj&)yTj!=dT#8]:ng/hokxzu|mc9(Z?rk&o;En8To.8cnMe(Ix)8c=3AixSO9(h;W{MF[$hr]1.q0l}3Md=4qHWZ5B=kg53rP#=9l9M',
            // 1
            '2;3289654;4342580;11,30,0,1,0,134;4$:_8*w/|JI^::;03``34kil2}K69K+obc{>`)q3uZn4pe<-dp/;x_b#@3*qI6*>jSO9Fp8^;$A;HGmZ`N<T-(+azZRc+n`Y9!Jthxij|Pc^qp)/DN}3s,|j&kVF?:025X^=[dBijYss,6X[pi`.p?y&Ry^aC%F{o/-iw&Y`*Xp~gTCt8Y3o7F_dtoJVW23r[TN5v$cxT=gKg z1Lv%*e+9wLw<VAaWrb`F{u9g+-(KY(Xu-SM>*8@pN60zOIe]r9ywm,u`sT6VE#nm]X24_FgRCX7/m{DK3k{RB[-qnV(a}Xu!vuRna$Ge.jV*HY|`$BdQEk#.}4Gz?!M4U`t*,Zp=2 =7CoSV&1v<8]-/Yx)kl{FX!l~@<8i6@KJoYG^|Yy+xnY!(.MmQ$eE-VI[%20jE=0Je>U5C{7WUN-_dq>XI&0+K8w-_eKCp`k;V&-~-hLrhcB;FAg%oNX]xVBohjBI536f>)I7a?Y.2Ax0K^8yUgaMK+uRtk1:nqii,fR&^EX/AVCI<VAkV?H7>BsN,AcKGS^3ZG{wG0_:K:Th`{9g:T5LP;kr(G<.XA!;`LZN[zy*u[#FiaxZ$KWzSYQi{G<[[~5{OuZpcU9s<INB#8#ujf0.!/)mq@jqa&P2dWDD- _:_;8?`,}qt?//MQZD=k:JIpD5lgEV@<*p5rHzXR}<O.LEmE+gbN}u XoeaZpfW_=4Z3$$?kcYtz>qg0:6:g#BZ8+0WKGlan`E!bk!cDEIap/My$=9Bze|#%DP@D=PYiOR,n!D!eN]8Fu<d(?9U2 <V5w)a vRsi<W:|?R8l Kc8Z{R_32I@z%GJ&3>JuQ,,@E&xw6{{3b3H,LRK-RM$%MlTLWZ;&JXvwPpj2SQWorMnJ$X#Hl${urp[Gaseo@YV)9Xg,OO;EeP p8 UvN6XC c^Mkh9^*>ht6,._6S,j%U(h0~a>o.39`E?F[x6^=G$DZM]/gixIoB6D]9s[|LUV8pt(;Y;U8Y-hHxtWr2Y%M7v_e:YP5^g%Y<mP~CuD{N2J|)6sY|!W1@,Y96m^0fx; ^9~h?bydA{bR-)$%[EiG|x1k{^U4k#]+y6<|A+^Qwa4dv2*o[M1ecL&NV6?(dmD7 FS6XF/EKVDaV!!u=3F1#x !5My(Lf)z!<]7jZN@uS63Cq_j-WN!- B6KAB+.Qr{m:U8spPx9? Y9vRDwUl+) FE;BGZJ|DOT*J- u>MYKqk@VRRb$}}+!C[ty%f#_3?>P!3=P)CIx|h>#b8qB70e<uvLOmX_/~>rd<B$7Jh!c54!Rc|Qq&4CPz5hp#&0pW^ P>X/&|(-T:V dz&dLXc}F]#L]u!9casEyPb?o0,j.2%fVu.%cpzLg?Tz6iC_I[tyxd|h$1DVi3oxwl~|ez4`7G(#x2I`dHmLWmgq>F %?ISF0Q}!jz9`,jSv3t(@tsS|-t%%FZ{Meq-cm0=FQwF*D=Y}SM#T>=+%H5MisE!6Pc7[iCp!~>X2omelM/@t//eC?;-tnXCNV86HhbsdqR7JhlHCfQV8GP/rstZD~M#SD4`jgKI4Ijwl_X02hQvMcxL2;7l-Z/9;*brcp[AUVP5a0,ukm)d0<U5>UV+H&/K$ipv7Y<*KcS,Fh^IIAl>#.>6z}I&f^7 K5m3H#ne&;a?)#4n(lmO0HkUKs~R)eI@ m[k>QEBWue@:8[*<1PMeq@<3_sN}$U^<TXLr+}yZwbMC7_TY]WFJ_;9)U X-)rkSP}U*H~a.l_^:mhL H,Dq=doi5ii9W1!6$[74GL|A_0.hP#rBPZ+jxsWnu.EV0mih)Ki}7_?9]hb.h0nelU76sZRW<s#4^(]obz6_6[=8VZ,x<JUX$hi}Xq~AO0L`FSaQQ(/gJ3,_ZB5?~J`7h^%/K2%cl:(vUmnimQB#Ln;aJFt(cszEW/X~Y+Wxe6sTcMIw]kWL3H5%plB)7PPHC;JAh*D1`9#&7C?uJLrV?/PZSj_5YD}Bqd OJgn4d4ZUuI|c,[o1AcQezPoJs=0q7S?HK<v</Bo7:[_hQD~tJHXZ99 sH7e-&+}gEM?!7oyfD`)0bip&&!V.Fak/=pi8`inK6DK]x{/{hJq3}.VlxVVCCqM onT&h$_hnloe!5S8^fir:Q}^3N/Te_V3.>.3~/I94bI.F;.ZD=J531_s*^4YFhRH~|g a{nvJ`;#>)$6&TCuR%ap#vjm1p[M9A:s])-?7$Bj~BoJ![c9{d8BoM2`2lo5m1ZOYL3t&aZDowb30j,>I;Oa-3(VzTHIPRMjY^AD`34r];W}zbqK8)k0[R8nP5$;5DKgVE7 E%@`{B<fd[WD9zi:m+o/[rY%Ntg1&Yx5aTWdUECw%~YljWWgNam4p~R}tDVvx:)=qsFb@^biyz{y/gZ4fwLc^[Jsg<Fx!G<O!hr-vG5(9SyKOCC$gw-xTc( @g$MV8I,eTi iDKJMH2OoLjob#iz6L=c@B4`6[@|2j;J<o);ej%*IagNoS!S7Nb,J*I{Hx~-!p4UN.HJft0@aixdWw:<x7V}CFI+T)5N]Fv9-s-+Q(+Jtp3<nxVkP#3f4;>B$24h>Q:^[[muApXGemD,1[~ooXa(ed+6Y5NhssP{|x4n{K}2nf.*7gFNJv-%HMwg;.Dqs+UA:>[z_V3TveqiqDZ8<x*G{oTK&SyMF2i<TOyAS$d~1x5G[5KKm8uXD.~tQGv!eh4l(d*Xf,HgOwB.oFB(a>C7F}nd!CJ}iZK3H$1XBgT,tky.:Y{G%jk:&e|Ml?v-i]zs_@z|mKij)R9UjXPn6eBcAl([7C4xC_VPk~ePdmV=>g_k;5y5=3|@tw,jIL;%-qzqGYE|REZ=Hob)A!1s4=%(/+>TERa2wa<1Z.s:>hN+!rQ~JNM)ZM}SYlxy;L?<XA@J!^s {s/;vLHtPVh8&vi(}K&zxB-ed=t4tX5J$LU@M=7cweOGu<3hcxl)QV7N*$ig`ojFXUvqojr<+1DL?,h5Wy5vDJ0B9C/5 y1aeiA-JD:~fv>x]qy?`,Bz1Wjt`P`-dLRcAw$?XPXA/FhXBke.X,K.co 07IERO=LQecx5ibJ?FhD<-?E!=h5)K1gN.2![1ey?V_30`k$ha1xNLq,T~k=umqrJNGa9D=.9bJq$Zho}j:rplhx[VOJd;kZ&U/1;h^E,goXc@Pl@lC#0ROEU*zKFF[GzJ 5=A{4v#xvKz1OR9}zu-_$ma0-EX/R[zS~[%L@I6zwj=Y97@%qr|W*qUg?9}a8w&*zQbvD_</W d.d`vYP[CSw%scBIF2XCGkeMVm=`Z){S.T,AA=(OhGwsbgYV:DpUy/(|Q*7n[|nUzs8Qa5Y{/~X!s-|bzszu$UL>]?S)wA@{qKFb{f]|],edjp~ 8*ZsyH=ZX$34=}~gZ%}3/gc*XM?8<AGdjoVI9ILVz?5hrttkhQ*>s44[=(481WMFV(ml3m} X!jEq,#|V?iIu,%[490Kp`X$INQO=`x|O9RjY_WPpPbVidspjpp-P#7bhO%gRL]qD{#oso++:z>.IHWJ0+5[b:M{|JC9kVw*1gjcxxr%U>Jyn*4 h^pGZGP%=XmodOg1=NCDxi42TLNB*=kfGTwl1b-T/%Ybsg&k!vgpK(lo5N*/ ?W]dQi@oUzT}Hj1DY-b;<ar7Fo&Dl0[;hC6;`Y F{*R8c$A-iN2yv<Cd5%r)1JPN}KFHi#]5uPEeFf*.(f@-0NN#L!6.w16>l@NGR(yFFxt=~rE~|V<nn1B@xyp&Gsn{`^7H#<&&KLesBzOPe@F&E2vAjlSneGCw NfT)6/kg@=3 A~z9#_r@GsO(m0O{lgA8X^sa$&^jN*8+a<+gPjX2e`Rpc|lIz@K;)Brdt]J]=T0=`Mf!3l0Bv<)gc){]5@YEEn(mLA2N:D15uW@<zwIgW&t=M/#D9zhJM5P@zsMm{~%.n]<=L:l(.AO-#m]4}Ba!c5ZN-E8J%;dnUNPMwQ{=_ig&x@c}j&X.m47]y*bzPefJl`;<H#Srf{E5ObltB<>@I.[[+YLx8O]pgg?+74?Bht-!T{far4p25uPLOb|*Cv#>57vQu%[Nn-JCG@Rz=Y}HjP8;',
            //            // 2
            //            "7a74G7m23Vrp0o5c9135871.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.182 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397155,1664336,1536,871,1536,960,1536,448,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8920,0.289916636144,807070832168,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,1,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,0,1,7,525,396;1,1,11,522,396;2,1,19,519,396;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,2829,32,0,0,0,2797,660,0,1614141664336,22,17267,0,3,2877,0,0,660,37,0,72C964343FCC43051F1F2AD862A7363D~-1~YAAQv7QtF0jRObJ3AQAAqC5X0gUguWaepAyz0GBoo2aMd0oZEw19YRg63I+KzjCJ0AKY3MLw+TB8vvx60fnSIkDc08ugJwRMpUORDVFgKbXlC3j46fRm0JREhJZW5RuDa/ox+WNsroPa73lS/U4jNT/+Mxq6OtVk2aSOrz5u2DtiOdS8jOMNVqibrYf9RESMBjNIFjvQ0puHExtjxNGYeicfYg8D8+4wUquKvwsveC/dgjU8fv9gXSejFNQbFztHKCqqNXZQ30KLLFusgGSgoBXKOEPh31s3GJ555CsewwT9T//eFyP12W1rskXineO6H5wuF7v47s98yrgF/El4KlL3YuAjhbkm5TyKpMZl5V8upu2PkN6EAE4Y1W2uSfSjatqHoGeeexJ38Hy0yUk=~-1~||1-cRCpOmPFVf-1-10-1000-2||~-1,38988,90,1280943102,30261693,PiZtE,12751,41-1,2,-94,-106,8,2-1,2,-94,-119,36,33,37,37,61,59,38,31,35,6,6,6,11,367,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.658e84c05c292,0.a436c9350a7d9,0.6ce27a789c074,0.37112a6692f51,0.1d4498994fb18,0.dc1ce1a54da6e,0.210537db335e7,0.0794f267f5d3,0.7abd8d7724cbf,0.58678dce8d243;1,1,2,1,0,4,0,1,1,4;0,0,1,4,0,11,1,2,1,9;72C964343FCC43051F1F2AD862A7363D,1614141664336,cRCpOmPFVf,72C964343FCC43051F1F2AD862A7363D1614141664336cRCpOmPFVf,1,1,0.658e84c05c292,72C964343FCC43051F1F2AD862A7363D1614141664336cRCpOmPFVf10.658e84c05c292,12,50,109,19,70,179,159,127,135,3,58,32,207,21,170,118,10,191,176,66,88,242,186,87,171,66,60,148,131,179,224,119,448,0,1614141664996;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,24964794-1,2,-94,-118,134239-1,2,-94,-129,c73d9197239d1276bf2dd0921e5e4d8ab70cdc09ecc3a52921b4602b562571ba,2,0d65734a64c01ce05475c3345af06aef5246ddb8f0161ec4776e6f9c160dfd9b,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;3;6;0",
            //            // 3
            //            "7a74G7m23Vrp0o5c9139621.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397496,7213457,1536,871,1536,960,1536,487,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8884,0.215033965107,807763606728.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,1,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,1082,0,1615527213457,27,17282,0,0,2880,0,0,1082,0,0,C3C8FAFA5EF02D9AF663DE17B2216A1B~-1~YAAQbpt7XBRU2xt4AQAAjvvsJAWNDf2WIsOvnsvJ0AVP1D9WE/sYvuHPjmR/xycHNxLm/GaDM/Gv9lIKXHEP1efid8wrBpc3rROD313QV016d2cnfHVrqdoH+K1ZrsgJNHMr6Gz1iXiCsgfTre7kSGim7P7Jqan+6qcLZg33fRMyucWnYWMCDWg1OsyzXXxMxLEp5mv9riNQxNH7apfRn3VandRMUEh9Bsf31q2X+j1SFwgyq1f9yQdIanjVJAJ08fuAzPtdf1Su3wlAJXn+9+li+H6Fw4E/im2/lr1+JusJBBqo2X969ojsUJKIUT8R3QtkPAQwBk0BwvJklMAMYgqMbbHzW9OumAnAL1Qnsw2aoMRsO3CEbH4OxfVqg0aCRcdbjInI5FAr9GtFmyk=~-1~||1-GOZDAyoSlL-1-10-1000-2||~-1,39342,23,-1296817655,30261693,PiZtE,45045,33-1,2,-94,-106,8,2-1,2,-94,-119,28,34,32,33,50,52,10,8,8,5,6,6,11,364,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.82c3e32834ac,0.6e99fc825d6f5,0.a330b697de3e8,0.9921c821cc106,0.49d2b464b874f,0.47fb55280c9a7,0.0aad26ed0e69e,0.e6b3e954c4962,0.a6be5ee53bb09,0.1d1f7f103533e;1,2,0,0,3,1,0,2,2,0;0,1,0,2,5,2,0,7,3,2;C3C8FAFA5EF02D9AF663DE17B2216A1B,1615527213457,GOZDAyoSlL,C3C8FAFA5EF02D9AF663DE17B2216A1B1615527213457GOZDAyoSlL,1,1,0.82c3e32834ac,C3C8FAFA5EF02D9AF663DE17B2216A1B1615527213457GOZDAyoSlL10.82c3e32834ac,131,95,7,54,251,173,30,131,151,170,71,45,6,2,181,159,187,119,206,254,53,77,90,193,130,11,103,218,252,105,247,241,795,0,1615527214538;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,108201819-1,2,-94,-118,132499-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0d65734a64c01ce05475c3345af06aef5246ddb8f0161ec4776e6f9c160dfd9b,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;3;6;0",
            //            // 4
            //            "7a74G7m23Vrp0o5c9143071.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.90 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397881,1410825,1536,871,1536,960,1536,403,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8884,0.299345878149,808545705412.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,1,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,1,9963;-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,9996,0,1617091410825,100,17299,0,0,2883,0,0,9997,0,0,C0CD7DA4A6C73AF8DB38235421ECAC52~0~YAAQnwrGF7ENSHZ4AQAAHsMoggU5m22U6EOg7d/+YPHWxW1MOotOlG3l7RHnDw51qFhDD1rI42YlzPp63vw7zfPhBrq5eEVmvnKywNUWpbOWVO4ySrIhnQgHy19CeKQIUY55z0oD3GlABot0GAXTE3f3jfu31Fpbhit1HfRQ3u4TUCvc4JR9KoJJSxRHe/wnj2krxujYVLPt0b8ENZLBB3z+xTba4+AlXwl1RNWyXeQXTIIrp1AAn329QCaldPvuaB7jO1OlvDTwdvkcmrpEc+EBm+Nlu1Px3cbFTvXsVtLAb/AGGQ9V8Vp4TBmzmwW6jQigV9f11MMdVxMxtQpF2pqHU8IFRlimqbCkWI5SPWvfSypAr8HfINvLNRDClhtcRe9Mh3ZkHw+87aswNSTwk0wOIxawShY6WJkKjw==~-1~||1-NYKLjYIUQe-1-10-1000-2||~-1,40962,41,-81810899,30261693,PiZtE,96597,18-1,2,-94,-106,8,2-1,2,-94,-119,45,41,40,41,64,66,41,38,9,6,7,7,13,451,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.22a1c1f4b8d68,0.ab40d91313fc9,0.bbfe69af4ee02,0.2288808fad9e5,0.47ea8a225f914,0.caefde9ddc125,0.c6d8fe21c658c,0.2ee9a15da10d4,0.1b8ba74074e91,0.78caa41a6fe49;0,3,1,1,0,6,0,2,5,3;0,0,3,1,0,16,0,3,19,7;C0CD7DA4A6C73AF8DB38235421ECAC52,1617091410825,NYKLjYIUQe,C0CD7DA4A6C73AF8DB38235421ECAC521617091410825NYKLjYIUQe,1,1,0.22a1c1f4b8d68,C0CD7DA4A6C73AF8DB38235421ECAC521617091410825NYKLjYIUQe10.22a1c1f4b8d68,38,81,216,147,63,219,42,191,207,88,250,23,99,87,231,173,254,184,84,78,48,162,220,109,146,240,10,125,140,194,14,140,2358,0,1617091420821;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,38092362-1,2,-94,-118,135400-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0d65734a64c01ce05475c3345af06aef5246ddb8f0161ec4776e6f9c160dfd9b,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;5;19;0",
            //            // 5
            //            "7a74G7m23Vrp0o5c9135871.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.182 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,397155,1550297,1536,871,1536,960,1536,448,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8920,0.694736432347,807070775148.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,1,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,10963,0,1614141550297,123,17267,0,0,2877,0,0,10965,0,0,0DC6A0F878DBE523FE5BA8003C647315~0~YAAQRWAZuCUw1Ml3AQAA9XVV0gVH3A28dMBFDTGnvwhy4GJkxTRLwYJT8WPORb8UmFfWrhSGMKeIBb1xL4RZ4IwPzXoJmD2gjJNtCr3wV9bbCTAjmPTECiG9fbQqrGZQ0Z0qLEIDkVLiiAXEOtQhrm+s8wqJZmZatr08uHsJ6MjoafgZmLnXpUJos8vKHS2i+0UxOOpzdhDO9CN7XrAJK4t8MUlp4r5N44Bd9Xd4jO/pCRYrsssfwUJJJj4k3XxIeGKDVEEPWW7egCn6OJaCWrqpl/c5Y/Hc9r3FilXOcz0adLWJL6T4uB8jHaKsVekEQ17jMFwOQSe9RgYalcEMOjQxlqmtMn8qRPhhKQbKaG2EJJ7OdXChZ0S1RCvZkvWV3tKZA2Eb+BQy4BEQ6y1/SZ0GvkoWiBFLwM1/+w==~-1~||1-KqhYvWzfZw-1-10-1000-2||~-1,40581,73,1124406630,30261693,PiZtE,12218,90-1,2,-94,-106,8,2-1,2,-94,-119,43,41,44,230,80,67,44,38,14,7,7,7,14,737,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.5dd2e06fc1d05,0.886048b8a9745,0.4acee4e0af0c5,0.edf8e37cb878b,0.2d0216d0ff46f,0.727090910b66b,0.5112063d032f8,0.ae1cd5455859,0.195b0cf946004,0.20c1ea7e1355c;0,2,1,1,4,2,4,0,9,0;0,0,2,5,10,4,6,0,14,1;0DC6A0F878DBE523FE5BA8003C647315,1614141550297,KqhYvWzfZw,0DC6A0F878DBE523FE5BA8003C6473151614141550297KqhYvWzfZw,1,1,0.5dd2e06fc1d05,0DC6A0F878DBE523FE5BA8003C6473151614141550297KqhYvWzfZw10.5dd2e06fc1d05,120,114,125,164,116,185,248,19,5,73,68,77,73,98,192,181,76,50,168,97,207,236,56,140,55,205,109,28,214,216,167,119,1961,0,1614141561260;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,4650906-1,2,-94,-118,134650-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0d65734a64c01ce05475c3345af06aef5246ddb8f0161ec4776e6f9c160dfd9b,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;5;8;0",
            //            // 6
            //            "7a74G7m23Vrp0o5c9141291.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:88.0) Gecko/20100101 Firefox/88.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,398520,809699,1536,871,1536,960,1536,462,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6016,0.702943660351,809845404849.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,1,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,1335,0,1619690809699,6,17326,0,0,2887,0,0,1335,0,0,EFBA05E25B23B82B45709D04001830AB~0~YAAQvGAZuLg9nBt5AQAA520YHQXYVHuAz/b0FX0pzI1tQUOxBrgutlhmoasvTEDr33IOIyyw8FCt8VwSHRbIGN9vaTcDx5MlxeuvRwMGNa8r1hy17356t0IqaQHyCdMSvIZQWhhmkjwyUB5gmBnXJPmBrhh8y6UmjTa6mL6QL5tlo4W64udw5qGPGyatLQL9V4FX9BBCuyRUOOMty4bwy9uvbPUTondgTLmAa+S4FmlgfnNTDD7+XawZ3lTl/5x49TB7MM019nspc0+u+FKpbi+ybNqnN91PoRS48O08yAJI6aTvh1IkKMYTtL/cf+mNDnI5n/ICGpOoGJqjJJVe9rhU64tCtapaoJ3dy2sl1RUCHbyLtQnTOG95+Uw6pXqBHSGcPREMzRsYPMgqrDGJ7/tzAO8wOgns5ixknh8=~-1~||1-JrCZIdMHsh-1-10-1000-2||~-1,40954,648,1089133076,26067385,PiZtE,49423,79-1,2,-94,-106,8,2-1,2,-94,-119,0,0,0,0,200,0,0,0,0,0,0,200,0,0,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.df9b2e6972d8e,0.ad9a12fa2b40c8,0.edbfe1f6d3ea48,0.1705f5e8dd7768,0.c699843fec53a8,0.e90acdf1e4128,0.ecfbe2f51c2ad8,0.971ab5bbd63458,0.cbcb601b6888d,0.80fbc49d421268;0,0,0,0,0,3,0,0,0,0;0,0,7,0,3,14,0,1,6,1;EFBA05E25B23B82B45709D04001830AB,1619690809699,JrCZIdMHsh,EFBA05E25B23B82B45709D04001830AB1619690809699JrCZIdMHsh,1,1,0.df9b2e6972d8e,EFBA05E25B23B82B45709D04001830AB1619690809699JrCZIdMHsh10.df9b2e6972d8e,19,48,187,137,244,166,145,116,32,40,149,0,237,128,212,133,211,109,121,198,32,129,124,63,35,97,92,127,15,43,124,5,1276,0,1619690811034;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,2429166-1,2,-94,-118,132362-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,cd15889e4b58585ec9f3c796725c752f6dd7926965daec54124da062a5aaf8e1,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;0;5;0",
            //            // 7
            //            "7a74G7m23Vrp0o5c9115631.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395371,1175826,1536,872,1536,960,1536,461,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.13489643167,803445587912.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,1,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,613,0,1606891175825,15,17190,0,0,2865,0,0,614,0,0,E04B53ECD9B680B233BCDC6A81333251~-1~YAAQv7QtF6W/ARF2AQAAp4AtIgTaGZ+cyXwZjv4QlWXufYpXOQ0AR4RDotYCOgK15S3sPrU3HAEQgnz9obKyRj5miC6VNqUKLgqQIclyk7N1TuSKbqat6Ne4oeO86bvJyaBEnV5d7Hl1LB6VCGT3zZLC5rwr43REVxiyPTAT1wFt1Q4OnOPpnyim8D4uBJkD6NW8s1FzRBmD2lFs2NyDT7Q/2rFtRyLPjb+uFBUUkxQn8apOZAutblv+xdc9e2Kp1lqK7y2WebKLm30ZG4zZNzvyAxJowMN0rslLxmoTW4+frcWqSxO9HFsqLufcvP5SPQnZ/vjboh89lY+u~-1~||1-HRhUwrylFI-1-10-1000-2||~-1,33692,71,-1741552716,30261693,PiZtE,93010,77-1,2,-94,-106,8,2-1,2,-94,-119,29,31,30,31,49,52,35,33,50,35,6,6,10,355,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.21beea87b6951,0.0903cd279ac5b,0.b773a19f65e68,0.33865140b7811,0.6425c99328749,0.3a5662c653499,0.480296a6678b7,0.2dfed6f8a8071,0.44b08efa27633,0.2f2cc70c06165;0,0,1,3,0,0,2,2,2,0;0,0,3,7,2,0,3,7,14,0;E04B53ECD9B680B233BCDC6A81333251,1606891175825,HRhUwrylFI,E04B53ECD9B680B233BCDC6A813332511606891175825HRhUwrylFI,1,1,0.21beea87b6951,E04B53ECD9B680B233BCDC6A813332511606891175825HRhUwrylFI10.21beea87b6951,127,126,255,94,206,187,17,61,70,124,13,183,52,188,148,111,221,183,145,137,153,245,85,113,218,123,148,222,211,60,251,47,377,0,1606891176438;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,31747026-1,2,-94,-118,126914-1,2,-94,-129,c73d9197239d1276bf2dd0921e5e4d8ab70cdc09ecc3a52921b4602b562571ba,2,0d65734a64c01ce05475c3345af06aef5246ddb8f0161ec4776e6f9c160dfd9b,,,,0-1,2,-94,-121,;3;6;0",
            //            // 8
            //            "7a74G7m23Vrp0o5c9254871.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:88.0) Gecko/20100101 Firefox/88.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,398838,2310434,1536,871,1536,960,1536,462,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6016,0.16270685281,810491155217,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,1,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,0,2,2415,9,0,4,-1;1,2,2430,17,0,0,-1;-1,2,-94,-110,0,1,2447,521,273;1,1,2470,520,307;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,0,228;1,2303;3,2338;-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,4879,6573,32,0,0,0,11419,2471,0,1620982310434,6,17340,2,2,2890,0,0,2472,9762,0,FF5ED289861F95F716D99BDA14D5EA75~0~YAAQyQcPF1NboUB5AQAAkCwTagU1bad4Y46Wb2T4uFnsPwXaNWQtNZ85KWPlO2II9F8znksuCWYbCc+yuVBlPGcg5pw8s5r77tnBa6Ldm7L/bx5yGSIHeL9EY6X9dXV/1YDortg7m0mKOlX3DKIqvxcou12VU4oCkTgBzRFFIdfjquU5Ayfj4YTAuknF+kjBm45xE2X9Ai2B34eaKJS8Juhlimfnq6Ju7BFEPzmAPunhLJVEqKtT6YRYWGmBpoV0WNDnaeZEIVyoFqFelr1ELIFB7m9PtHfOaxfc3Z1q7+lhIviqqaWBaN6dDWU5JrEE50oxom6kJ8DlxjBo/kJqOrNQ5YCWdrXvoOIduUIfrI36tcl91L9tBgPksM4KKIWifXIt9MzzhPkX+lNNY7ZRukOdl6nQDkIR3PK10eA=~-1~||1-tBiFMrQmUR-1-10-1000-2||~-1,40937,329,-559103099,26067385,PiZtE,109207,53,0,-1-1,2,-94,-106,8,2-1,2,-94,-119,200,0,0,0,0,200,0,0,0,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.008c3675941898,0.0e1a461f9e02b8,0.4c127bbc5b236,0.0396a8bc4333f,0.e062b752a4332,0.fa52745a961158,0.2b84e18182df38,0.d9c35029df1858,0.8be9ab03139898,0.b4004b0da942d;0,0,1,0,0,0,0,1,0,0;0,1,2,0,0,12,1,15,2,6;FF5ED289861F95F716D99BDA14D5EA75,1620982310434,tBiFMrQmUR,FF5ED289861F95F716D99BDA14D5EA751620982310434tBiFMrQmUR,1,1,0.008c3675941898,FF5ED289861F95F716D99BDA14D5EA751620982310434tBiFMrQmUR10.008c3675941898,219,153,69,50,28,115,56,236,191,229,7,103,65,247,245,21,111,107,172,142,159,71,26,122,233,215,218,6,153,105,106,209,2304,0,1620982312905;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,173282496-1,2,-94,-118,136537-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,cd15889e4b58585ec9f3c796725c752f6dd7926965daec54124da062a5aaf8e1,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;3;7;0",
            //            // 9
            //            "7a74G7m23Vrp0o5c9141291.68-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:88.0) Gecko/20100101 Firefox/88.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,398520,1108581,1536,871,1536,960,1536,462,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6016,0.511661105255,809845554290,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-102,0,-1,1,0,-1,975,0;1,2,0,0,-1,883,0;0,0,0,0,-1,1155,0;-1,-1,0,0,-1,632,0;1,2,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,0,371;1,2737;3,2761;-1,2,-94,-112,https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/-1,2,-94,-115,1,32,32,0,0,0,0,2774,0,1619691108580,4,17326,0,0,2887,0,0,2774,0,0,FDEC66BC64DEBA333BAB240D28EE4BF7~0~YAAQv7QtFx7zaAx5AQAA+v8cHQWmVgbyeYwu8PMy0X1pxPCKkSHyIRa7FiQwlJowHjjpwa7IKbkS65RWk5x/pTJmpjGPJYfd1PfGjEF6vqNwJdThIKewIHrA8dy/e8LwgSyinmSKxtMhDj/WkFB16r3Qg2tFDRgyRoh7GNE2F4SBEGFIyvA/qaOywp0Gm1LmTKmy9133+onNzsSAYPwb/NFm84j1/JYP7sfGUVnfKFLRWIqS3iTSCDEZfumzFhaPGrSc9J4k0Al7zz+j8LLkL2G2gsw3LXSEjUVd3Zi27ssiJVpT3GmF+jLkklSTDht7yp7fP+1y4BHXCFqLNUlErHz70txzFuzDNg/Zm9H+WQf7cyfG564Lh+Fe3NpKm7p81Y6qEmhSbXBT7C65kZqH/EmjFLx/m9O90nk4Rd0=~-1~||1-aAdtALAiei-1-10-1000-2||~-1,40687,884,-1951845078,26067385,PiZtE,20210,46-1,2,-94,-106,8,2-1,2,-94,-119,0,0,0,0,0,0,0,0,200,0,200,200,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.0f1c9eaea029f,0.7cd7277dcff4b,0.036c48660c7258,0.0421f2af01d3a8,0.0c7ffedf5c29d,0.b0f12c00d4d44,0.cde6ab8a0d7c,0.903561454bff1,0.479def5bbf643,0.3c257c5c3437c;0,0,0,1,0,0,0,0,1,1;0,0,1,1,0,0,3,1,0,3;FDEC66BC64DEBA333BAB240D28EE4BF7,1619691108580,aAdtALAiei,FDEC66BC64DEBA333BAB240D28EE4BF71619691108580aAdtALAiei,1,1,0.0f1c9eaea029f,FDEC66BC64DEBA333BAB240D28EE4BF71619691108580aAdtALAiei10.0f1c9eaea029f,87,109,29,35,248,53,192,37,52,172,122,70,112,214,235,22,220,187,145,82,172,121,134,200,87,220,241,241,89,2,27,202,1438,0,1619691111354;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,269385831-1,2,-94,-118,132997-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,cd15889e4b58585ec9f3c796725c752f6dd7926965daec54124da062a5aaf8e1,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;3;8;0",
        ];

        if (count($sensorData) != count($sensorData2)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }
        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = empty($this->DebugInfo) ? $key : $this->DebugInfo . " | " . $key;

        /*
        if ($this->attempt == 1) {
            $sensorData[$key] = null;
            $sensorData2[$key] = null;
            $sensorData3[$key] = null;
            $this->DebugInfo = $this->DebugInfo . " / 1100";
        }
        */

        $sensorDataHeaders = [
            "Accept"          => "*/*",
            "Content-type"    => "text/plain;charset=UTF-8",
            "Accept-Encoding" => "gzip, deflate, br",
            "Origin"          => "https://www.shangri-la.com",
            "Referer"         => "https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData[$key]]), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData2[$key]]), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData3[$key]]), $sensorDataHeaders);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
        sleep(1);

        return $key;
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);
        $key = rand(0, 1);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
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

            switch ($key) {
                case 0:
                    $selenium->useChromium();

                    break;

                case 1:
                    $selenium->useGoogleChrome();

                    break;
            }
            $selenium->disableImages();
            $selenium->http->removeCookies();
            $selenium->keepCookies(false);
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL('https://www.shangri-la.com/en/corporate/golden-circle/gcsignin/');
            } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "gcMemberId"]'), 5);
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] != 'bm_sz') {
                    continue;
                }
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (UnknownServerException | SessionNotCreatedException | Facebook\WebDriver\Exception\WebDriverCurlException $e) {
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

        return $key;
    }
}
