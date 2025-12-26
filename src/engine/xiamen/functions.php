<?php

// refs #10807
class TAccountCheckerXiamen extends TAccountChecker
{
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $publicKey;
    private $smDeviceID = 'WHJMrwNw1k/FJMl2h2goBtIeg7ITSNXAVgH62/zCAfC+d6G2G32fYSdbtz8VvrT2Ry8LZCl+zOjNZoSStavSjLRduT+NPlSSzdCW1tldyDzmQI99+chXEipAG9y3lfkO74v8RPoE/I3ycqUbzY0YY/CHkvLSSe4GZkIpAQOFE1ku/uihhhvBr4GxhX5VEVM7bqIRI5aemugYiWRbjOLMhMxFOQB3sf95ahaL0E2i2kanzNPs7StqFDVBRruqkqpEZ1487582755342';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0');
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.xiamenair.com/en-US/');
        $this->http->GetURL('https://ffp.xiamenair.com/en-US/Login/Login');

        if (!$this->http->FindSingleNode("//input[@placeholder='FFP card No./Username/ID card No./Phone No.']/@placeholder")) {
            return $this->checkErrors();
        }

        $uiaType = $this->http->FindPreg("/uia_type=([^&]+)/", false, $this->http->currentUrl());
        $clientID = $this->http->FindPreg("/client_id=([^&]+)/", false, $this->http->currentUrl());
        $pageToken = $this->http->FindSingleNode("//input[@name='pageToken']/@value");

        if (!isset($uiaType, $clientID, $pageToken)) {
            $this->logger->debug('params not found');

            return $this->checkErrors();
        }

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }

        /*
         function encrypt(word) {
            var key = CryptoJS.enc.Utf8.parse("xiamenair1234567");
            var srcs = CryptoJS.enc.Utf8.parse(word);
            var encrypted = CryptoJS.AES.encrypt(srcs, key, {mode: CryptoJS.mode.ECB, padding: CryptoJS.pad.Pkcs7});
            return encrypted.toString();
         }
         */

        $headers = [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'     => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'Origin'           => 'https://ecipuia.xiamenair.com',
        ];

        $this->http->GetURL('https://ecipuia.xiamenair.com/api/v1/oauth2/getPublicKey', $headers);
        $publicKeyData = $this->http->JsonLog();

        $publicKey = $publicKeyData->result->publicKey;
        $keyPairID = $publicKeyData->result->keyPairId;

        if (!isset($publicKey, $keyPairID)) {
            $this->logger->debug('error on get public key');

            return $this->checkErrors();
        }

        $this->publicKey = $this->preparePublicKey($publicKey);
        $this->http->setCookie('shumeiBlockBox', $this->smDeviceID, 'ecipuia.xiamenair.com', '/api/v1/oauth2');

        $data = [
            'pageToken' => $pageToken,
            'account'   => base64_encode($this->opensslEncrypt($this->AccountFields['Login'])),
            'password'  => base64_encode($this->opensslEncrypt($this->AccountFields['Pass'])),
            'clientId'  => $clientID,
            'deviceId'  => md5($this->AccountFields['Login']),
            'code'      => $captcha,
            'type'      => '1',
            'uiaType'   => $uiaType,
            'smDeviceId'=> $this->smDeviceID,
            'state'     => '',
            'keyPairId' => $keyPairID,
        ];

        // $this->http->PostURL('https://ecipuia.xiamenair.com/api/v1/oauth2/verify2', json_encode($data), $headers);

        $this->State['data'] = $data;
        $this->auth();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // 502 Bad Gateway
        if ($this->http->FindSingleNode("//title[contains(text(), '502 Bad Gateway')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->State['data']['msgOrEmailCode'] = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $this->auth();

        if ($error = $this->http->FindPreg("/\"msg\":\"(Please input correct SMS or email verification code)\"/")) {
            $this->AskQuestion($this->Question, $error, "Question");

            return false;
        }

        if ($error = $this->http->FindPreg("/\"msg\":\"(Incorrect SMS \/ Email verification code.)\"/")) {
            $this->AskQuestion($this->Question, $error, "Question");

            return false;
        }

        $response = $this->http->JsonLog();

        if (isset($response->result->redirectUrl)) {
            $this->http->GetURL($response->result->redirectUrl);
        }

        $this->loginSuccessful();

        return true;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();

        if (isset($response->result->redirectUrl)) {
            $this->http->NormalizeURL($response->result->redirectUrl);
            $this->http->RetryCount = 0;
            $this->http->GetURL($response->result->redirectUrl);
            $this->http->RetryCount = 2;
        }
        // The user account doesn't exist.
        if (isset($response->msg)) {
            $message = $response->msg;
            $this->logger->error("[Error]: " . $message);

            // Incorrect image captcha
            if (
                strstr($message, 'Incorrect image captcha')
                || strstr($message, '图片验证码输入错误')
            ) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3);
            }

            $this->captchaReporting($this->recognizer);

            if (
                // You've entered wrong password.
                strstr($message, "You've entered wrong password.")
                || strstr($message, "The user account doesn't exist.")
                // Wrong password. 4 attempt(s) left.
                || strstr($message, "Wrong password. ")
                || strstr($message, "您的密码过于简单，请点击“忘记密码”重置后再登录")
                || strstr($message, "Sorry, the username you've entered does not exist. Please check and re-enter.")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "You have entered the wrong passwords for too many times")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, "System error.")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            // Your account was just signed in to from a new device.
            if (
                strstr($message, 'Your account was just signed in to from a new device. To keep it secure, please enter the verification code we sent to the mobile number')
                || strstr($message, 'You are logging onto a new device. For the safety of your account, a verification code has been sent t')
                || strstr($message, '您当前正使用新设备登录，为保证您的账户安全，已发送验证码到')
            ) {
                $this->AskQuestion($message, null, "Question");

                return false;
            }

            if (strstr($message, 'Service Busy')) {
                $this->AskQuestion('Please enter verification code', null, "Question");

                return false;
            }

            if (strstr($message, "Your password is weak. To ensure the security of your account, please modify it as required before logging in.")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;
        }// if (isset($response->msg))

        // Refresh? You have 2 chances left to enter a correct password or your account will be disabled.
        if ($message = $this->http->FindSingleNode("(//div[@id='msg' and contains(text(), 'chances left to enter a correct password or your account will be disabled')])[1]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//div[@id='msg' and contains(text(), 'The credentials you provided cannot be determined to be authentic')])[1]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The user account doesn
        if ($message = $this->http->FindSingleNode("(//div[@id='msg' and contains(text(), 'The user account doesn')])[1]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Wrong password
        if ($message = $this->http->FindSingleNode("(//div[@id='msg' and contains(text(), 'Wrong password.')])[1]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Verification code incorrect
        if ($message = $this->http->FindSingleNode("(//div[@id='msg' and contains(text(), 'Verification code incorrect')])[1]")) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(2, 3, self::CAPTCHA_ERROR_MSG);
        }
        // entered wrong password too many times
        if ($message = $this->http->FindSingleNode("(//div[@id = 'msg' and contains(text(), 'entered wrong password too many times')])[1]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // AccountID: 4682177
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "System error, please try again ...")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Mileage balance
        $this->SetBalance($this->http->FindSingleNode("//div[contains(., 'Mileage Balance') or contains(., 'Mileage balance')]/following-sibling::p/span"));
        // Good Afternoon
        $this->SetProperty('Name', beautifulName(str_replace('/', ' ', $this->http->FindSingleNode("//meta[@name='WT.loginUserName']/@content"))));
        // Card No
        $this->SetProperty('CardNo', $this->http->FindSingleNode("//p[contains(., 'Card No')]/span"));
        // Membership
        $this->SetProperty('Membership', $this->http->FindSingleNode("//span[normalize-space(text())='Membership']/following-sibling::em"));
        // Miles earned this year
        $this->SetProperty('MilesEarned', $this->http->FindSingleNode("//span[normalize-space(text())='Miles earned this year']/following-sibling::em"));
        // Start date of the membership qualification
        $this->SetProperty('MemberSince', $this->http->FindSingleNode("//span[normalize-space(text())='Start date of the membership qualification']/following-sibling::em"));
        // Qualification segments this year
        $this->SetProperty('YTDSegments', $this->http->FindSingleNode("//span[normalize-space(text())='Qualification segments this year']/following-sibling::em"));

        $this->logger->info('Expiration date', ['Header' => 3]);
        $expNodes = $this->http->XPath->query("//em[contains(., 'Mileage balance')]/following-sibling::div/p/label[contains(text(), 'valid until')]");
        $this->logger->debug("Total {$expNodes->length} exp nodes were found");

        foreach ($expNodes as $expNode) {
            $expDate = $this->http->FindSingleNode('following-sibling::strong[1]', $expNode);
            $expBalance = $this->http->FindSingleNode('preceding-sibling::strong[1]', $expNode);
            $this->logger->debug("Exp date: {$expDate} / $expBalance");

            if ((!isset($exp) || $exp > strtotime($expDate)) && strtotime($expDate)) {
                $exp = strtotime($expDate);
                $this->SetExpirationDate($exp);
                // Expiring balance
                $this->SetProperty('ExpiringBalance', $expBalance);
            }// if ((!isset($exp) || $exp > strtotime($expDate)) && strtotime($expDate))
        }// foreach ($expNodes as $expNode)

        // AccountID 4402418
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->http->PostURL('https://ffp.xiamenair.com/en-US/Home/GetStatusParam', []);
            $response = $this->http->JsonLog();

            if (isset($response->point) && !empty($this->Properties['Name'])) {
                $this->SetBalance($response->point);
            }
        }
        //$this->sendNotification('nticket // MI', 'awardwallet');

        $this->http->GetURL('https://www.xiamenair.cn/en-cn/nolist.html');
        $this->http->setCookie('BIGipServersvrpool_ffp_8001', $this->http->getCookieByName('BIGipServersvrpool_ffp_8001', 'ffp.xiamenair.com'), 'ecipuia.xiamenair.com');
        $this->http->setCookie('ASP.NET_SessionId', $this->http->getCookieByName('ASP.NET_SessionId', 'ffp.xiamenair.com'), 'ecipuia.xiamenair.com');
        $this->http->setCookie('BIGipServersvrpool_ffp_8001', $this->http->getCookieByName('BIGipServersvrpool_ffp_8001', 'ffp.xiamenair.com'), 'csapi.xiamenair.com');
        $this->http->setCookie('ASP.NET_SessionId', $this->http->getCookieByName('ASP.NET_SessionId', 'ffp.xiamenair.com'), 'csapi.xiamenair.com');

//        $this->http->disableOriginHeader();
//        $data = [
//            'Accept'  => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
//            'Referer' => 'https://www.xiamenair.cn/',
//        ];
//        $this->http->GetURL('https://ecipuia.xiamenair.com/api/v1/oauth2/authorize?uia_type=st&lang=en-cn&client_id=uiaweb_client&redirect_uri=https%3A%2F%2Fwww.xiamenair.cn%2Fen-cn%2Flogin-success.html', $data);
//        $this->http->JsonLog();
//
//        $headers = [
//            'Accept'           => 'application/json, text/plain, */*',
//            'Content-Type'     => 'application/json;charset=UTF-8',
//            'X-Requested-With' => 'XMLHttpRequest',
//            'accessToken'      => '',
//            'device'           => '59846437df7d4e82a2d73716bbb448d1',
//            'Origin'           => 'https://www.xiamenair.cn',
//            'channel'          => 'PCWEB',
//        ];
//        $this->http->PostURL('https://csapi.xiamenair.com/user/channelLogin/loginByST', json_encode(['st' => '9919CD8267534FFD93278CFC06940943']), $headers);
//
        $headers = [
            'Accept'           => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
        ];
        $this->http->GetURL('https://ecipuia.xiamenair.com/api/v1/oauth2/authorize?uia_type=st&lang=en-cn&client_id=uiaweb_client&redirect_uri=https%3A%2F%2Fwww.xiamenair.cn%2Fen-cn%2Flogin-success.html', $headers);
        $this->http->JsonLog();

        $headers = [
            'Accept'           => 'application/json, text/plain, */*',
            'Content-Type'     => 'application/json;charset=UTF-8',
            'X-Requested-With' => 'XMLHttpRequest',
            'accessToken'      => 'T20hIarcn7CwqueS89GwoQ6Q1Eo',
            'channel'          => 'PCWEB',
            'Origin'           => 'https://www.xiamenair.cn',
        ];
        $this->http->PostURL('https://csapi.xiamenair.com/order/api/v1/order/list', json_encode(['pageSize' => 10, 'startPageNum' => 1]), $headers);
    }

    public function ParseItineraries()
    {
        return [];
        $result = [];
        $this->http->RetryCount = 1;

        if (!$this->http->GetURL('https://et.xiamenair.com/xiamenair/myorder/MyOrderList.action?p_a_l=1&lang=en', [], 30)) {
            return $result;
        }

        // try to fix provider bug
        if ($this->http->currentUrl() == 'https://ffp.xiamenair.com/en-US/bindaccount/bindtoegret') {
            if (!$this->http->GetURL('https://et.xiamenair.com/xiamenair/myorder/MyOrderList.action?p_a_l=1&lang=en', [], 30)) {
                return $result;
            }
        }

        $orderIds = $this->http->FindNodes("//a[starts-with(@onclick, 'viewOrderDetail') and normalize-space(@class)='orderId']");

        if (count($orderIds) == 0 && $this->http->FindSingleNode("//span[contains(text(),'No unused flight tickets')]")) {
            return $this->noItinerariesArr();
        }

        foreach ($orderIds as $orderId) {
            $this->logger->info('Parse itinerary #' . $orderId, ['Header' => 3]);
            $this->http->PostURL('https://et.xiamenair.com/xiamenair/order/orderDetail.action', ['orderId' => $orderId]);

            if ($it = $this->ParseItinerary()) {
                $result[] = $it;
            }
        }

        return $result;
    }

    public function ParseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => 'T'];
        $parent = "(//div[contains(@class,'tab-mess mb10')][1])";
        $result['RecordLocator'] = $this->http->FindSingleNode("{$parent}//table[.//td[contains(text(),'Order number')]]/tbody//td[2]");
        $result['TripNumber'] = $this->http->FindSingleNode("{$parent}//table[.//td[contains(text(),'Order number')]]/tbody//td[1]");
        $result['ReservationDate'] = strtotime($this->http->FindSingleNode("{$parent}//table[.//td[contains(text(),'Order number')]]/tbody//td[4]"), false);
        $result['Currency'] = 'JPY';
        $result['TotalCharge'] = $this->http->FindSingleNode("(//div[@id = 'payInfos']//td[contains(text(), 'Booking with cash')]/em)[1]", null, true, "/([\d,\.\\s]+)/");
        $passengers = $this->http->XPath->query("{$parent}//table[.//td[normalize-space(text())='PassengerType']]/tbody/tr");

        foreach ($passengers as $passenger) {
            $result['Passengers'][] = $this->http->FindSingleNode("./td[1]", $passenger);

            if ($accountNumbers = $this->http->FindSingleNode("./td[5]", $passenger/*, false, '/^\s*([A-Z]{2}\s*\d{8,})/'*/)) {
                $result['AccountNumbers'][] = $accountNumbers;
            }
        }
        $segments = $this->http->XPath->query("{$parent}//table[.//td[normalize-space(text())='Fare Class' and position() = 6]]");
        $this->logger->debug("Total {$segments->length} segments v.1 were found");
        $version = 1;

        if ($segments->length == 0) {
            $version = 2;
            $segments = $this->http->XPath->query("{$parent}//table[.//td[normalize-space(text())='Departure']]");
            $this->logger->debug("Total {$segments->length} segments v.2 were found");
        }

        foreach ($segments as $segment) {
            $it = [];
            $it['FlightNumber'] = $this->http->FindSingleNode(".//tr[1]//td[1]/p[2]", $segment, false, '/Flight [A-Z\d]{2}(\d{3,4})/');
            $it['AirlineName'] = $this->http->FindSingleNode(".//tr[1]//td[1]/p[2]", $segment, false, '/Flight ([A-Z\d]{2})\d{3,4}/');

            if ($version == 1) {
                $result['TicketNumbers'][] = $this->http->FindSingleNode(".//tr[2]//td[4]/p[1]", $segment);

                $it['Aircraft'] = $this->http->FindSingleNode(".//tr[1]//td[1]/p[3]", $segment);

                $it['DepDate'] = strtotime($this->http->FindSingleNode(".//tr[2]//td[1]/p[1]", $segment), false);
                $it['ArrDate'] = strtotime($this->http->FindSingleNode(".//tr[2]//td[3]/p[1]", $segment), false);

                $it['DepName'] = $this->http->FindSingleNode(".//tr[2]//td[1]/p[2]", $segment);
                $it['ArrName'] = $this->http->FindSingleNode(".//tr[2]//td[3]/p[2]", $segment);

                $it['Cabin'] = $this->http->FindSingleNode(".//tr[2]//td[5]", $segment, false, '/^(.+?)\s+Class/');
                $it['BookingClass'] = $this->http->FindSingleNode(".//tr[2]//td[5]", $segment, false, '/Class\s*\(([A-Z])\)/');
                $it['Meal'] = $this->http->FindSingleNode(".//tr[2]//td[8]", $segment);
                $it['Seats'] = array_filter(array_map(function ($item) {
                    if (trim($item) != 'not selected') {
                        return $item;
                    } else {
                        return null;
                    }
                }, $this->http->FindNodes(".//tr[2]//td[6]", $segment)));

                $stops = $this->http->FindSingleNode(".//tr[2]//td[2]/span", $segment);

                if (is_numeric($stops) || $stops != '[ ? ]') {
                    $this->sendNotification('refs #10807, xiamen - Check Seats and Codes "' . $stops . '"');
                    $it['Stops'] = $stops;
                }

                // SHA~FOC~MF8574~2018-07-26
                $codes = $this->http->FindSingleNode(".//tr[2]//td[2]//div/@dsgparams", $segment);
            } else {
                $it['Aircraft'] = $this->http->FindSingleNode(".//tr[2]//td[5]", $segment);

                $it['DepDate'] = strtotime($this->http->FindSingleNode(".//tr[2]//td[2]/p[1]", $segment), false);
                $it['ArrDate'] = strtotime($this->http->FindSingleNode(".//tr[2]//td[4]/p[1]", $segment), false);

                $it['DepName'] = $this->http->FindSingleNode(".//tr[2]//td[2]/p[2]", $segment);

                if ($terminal = $this->http->FindPreg("/.+T(\d+)$/", false, $it['DepName'])) {
                    $it['DepName'] = $this->http->FindPreg("/(.+)T\d+$/", false, $it['DepName']);
                    $it['DepartureTerminal'] = $terminal;
                }
                $it['ArrName'] = $this->http->FindSingleNode(".//tr[2]//td[4]/p[2]", $segment);

                if ($terminal = $this->http->FindPreg("/.+T(\d+)$/", false, $it['ArrName'])) {
                    $it['ArrName'] = $this->http->FindPreg("/(.+)T\d+$/", false, $it['ArrName']);
                    $it['ArrivalTerminal'] = $terminal;
                }

                $cabin = $this->http->FindSingleNode(".//tr[2]//td[1]", $segment);

                if ($text = $this->http->FindPreg('/^(.+?)\s+Class/', false, $cabin)) {
                    $it['Cabin'] = $text;
                } else {
                    $it['Cabin'] = $cabin;
                }

                $it['BookingClass'] = $this->http->FindSingleNode(".//tr[2]//td[1]", $segment, false, '/Class\s*\(([A-Z])\)/');

                $it['Meal'] = implode(', ', $this->http->FindNodes("following-sibling::table//tr/td[6]", $segment));
                $it['Seats'] = array_filter(array_map(function ($item) {
                    if (trim($item) != 'not selected') {
                        return $item;
                    } else {
                        return null;
                    }
                }, $this->http->FindNodes("following-sibling::table//tr/td[5]", $segment)));

                $stops = $this->http->FindSingleNode(".//tr[2]//td[3]/span", $segment);

                if (is_numeric($stops) || $stops != '[ ? ]') {
                    $this->sendNotification('refs #10807, xiamen - Check Seats and Codes "' . $stops . '"');
                    $it['Stops'] = $stops;
                }

                // SHA~FOC~MF8574~2018-07-26
                $codes = $this->http->FindSingleNode(".//tr[2]//td[3]//div/@dsgparams", $segment);
            }
            $this->logger->debug('dsgParams: ' . $codes);
            $it['DepCode'] = $this->http->FindPreg('/^([A-Z]{3})~/', false, $codes);
            $it['ArrCode'] = $this->http->FindPreg('/^[A-Z]{3}~([A-Z]{3})~/', false, $codes);

            $result['TripSegments'][] = $it;
        }
        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $captchaUrl = $this->http->FindSingleNode("(//div[@class='login-panel']//img[@src='captcha']/@src)[1]");

        if (!$captchaUrl) {
            return false;
        }
        /*
        $file = $this->http->DownloadFile('https://uia.xiamenair.com/external/api/v1/oauth2/' . $captchaUrl, "jpg");
        */
        $file = $this->http->DownloadFile('https://ecipuia.xiamenair.com/api/v1/oauth2/' . $captchaUrl, "jpg");
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file, ["regsense" => 1]);
        unlink($file);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->setMaxRedirects(7);
        $this->http->GetURL('https://ffp.xiamenair.com/en-US/MyAccount/Index');
        $this->http->setMaxRedirects(5);

        if ($this->http->FindSingleNode("//meta[@name='WT.loginUserName']/@content")) {
            return true;
        }

        return false;
    }

    private function auth()
    {
        $headers = [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'     => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'Origin'           => 'https://ecipuia.xiamenair.com',
        ];
        /*
        $this->http->PostURL('https://uia.xiamenair.com/external/api/v1/oauth2/verify', json_encode($this->State['data']), $headers);
        */
        $this->http->PostURL('https://ecipuia.xiamenair.com/api/v1/oauth2/verify2', json_encode($this->State['data']), $headers);
    }

    private function preparePublicKey($key)
    {
        $this->logger->notice(__METHOD__);
        $preparedKey = "-----BEGIN PUBLIC KEY-----\n" . str_replace(["\n", "\r"], '', $key) . "\n-----END PUBLIC KEY-----";
        $this->logger->debug('[PREPARED KEY]: ' . $preparedKey);

        return openssl_get_publickey($preparedKey);
    }

    private function opensslEncrypt($text)
    {
        openssl_public_encrypt($text, $result, $this->publicKey);

        return $result;
    }
}
