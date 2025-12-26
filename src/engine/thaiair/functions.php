<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;
use Facebook\WebDriver\Exception\UnknownErrorException;

class TAccountCheckerThaiair extends TAccountChecker
{
    use OtcHelper;
    use SeleniumCheckerHelper;
    use ProxyList;

    protected $collectedHistory = true;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $token = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptchaIt7());
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['token'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.thaiairways.com/app/rop/web/check_token/", [
            "member_id" => strtoupper($this->AccountFields['Login']),
            "token"     => $this->State['token'],
        ], [], 30);

        if ($this->http->FindPreg('/"success":true,"data":"Valid token\."/')) {
            $this->token = $this->State['token'];
            $this->http->RetryCount = 1;
            $this->http->PostURL("https://www.thaiairways.com/app/rop/web/get_member_profile", [
                "member_id" => strtoupper($this->AccountFields['Login']),
                "token"     => $this->token,
            ], [], 30);
        }// if ($this->http->FindPreg('/"success":true,"data":"Valid token\."/'))
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data');

        if (isset($data['MemberProfileRS'])) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->FilterHTML = false;

        if (strstr($this->AccountFields['Pass'], '@') || strstr($this->AccountFields['Pass'], '-') || strstr($this->AccountFields['Pass'], '!')) {
            throw new CheckException("Please check your PIN. Only alphabets and numbers are accepted.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->getCookiesFromSelenium();

        if (!$this->http->FindSingleNode("//input[@id = 'member_id']/@id")) {
            return $this->checkErrors();
        }

        $this->http->FormURL = 'https://www.thaiairways.com/app/rop/web/checkPinSendOtp';
        $this->http->SetInputValue("member_id", strtoupper($this->AccountFields['Login']));
        $this->http->SetInputValue("lang", 'en');
        $this->http->unsetInputValue('member_pin');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Back-end server is at capacity
        $header503 = $this->http->Response['headers']['http/1.1 503 service unavailable'] ?? '';

        if ($this->http->FindPreg('/Back-end server is at capacity/', false, $header503)) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Due to a temporary error the request could not be serviced
        if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'Due to a temporary error the request could not be serviced')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // System is unavailable please try again
        if ($message = $this->http->FindPreg("/(System is unavilable please try again)/ims")) {
            throw new CheckException("System is unavailable please try again.", ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, online service is unavailable due to system upgrade
        if ($message = $this->http->FindSingleNode("//center[contains(text(), 'Sorry, online service is unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // HTTP Status 404 / HTTP Status 503
        if ($this->http->FindPreg("/(>HTTP Status (?:404|503) -)/ims")
            // Proxy Error
            || $this->http->FindPreg("/(<h1>Proxy Error)<\/h1>/ims")
            || $this->http->FindPreg("/(<h1>Service Temporarily Unavailable)<\/h1>/ims")
            || $this->http->FindSingleNode("//h2[contains(text(), 'The request could not be satisfied.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data');
        $this->token = ArrayVal($response, 'token');

        if ($this->http->FindPreg('/\{\"success\":true/') && $this->token) {
            $this->State['token'] = $this->token;

            return true;
        }
        // Please check your Member ID and Pin.
        if ($this->http->FindPreg('/^\{"status":false\}$/')) {
            throw new CheckException('Please check your Member ID and Pin.', ACCOUNT_INVALID_PASSWORD);
        }
        $otpRef = ArrayVal($data, 'otp_ref_key');

        if ($otpRef) {
            $this->parseQuestion($otpRef);
        }

        if ($message = ArrayVal($data, 'mwerror:ErrDesc')) {
            $this->checkCredentials($message);
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $data = [
            'member_id'    => strtoupper($this->AccountFields['Login']),
            'member_pin'   => base64_encode($this->AccountFields['Pass']),
            'otpRef'       => $this->State['otpRef'],
            'otpKey'       => $this->Answers[$this->Question],
        ];
        unset($this->Answers[$this->Question]);
        $headers = [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'x-requested-with' => 'XMLHttpRequest',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.thaiairways.com/app/rop/web/check_pin', $data, $headers);
        $this->http->RetryCount = 2;

        $incapsula = $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src");
        if ($incapsula) {
            $this->incapsula();
            $this->http->PostURL('https://www.thaiairways.com/app/rop/web/check_pin', $data, $headers);
        }

        if ($this->http->FindPreg('/{"success":false,"otp":false}/')) {
            $this->AskQuestion($this->Question, "This OTP Code is not valid with the defined ref key. Please submit the request again and wait for the new OTP Code.", 'Question');

            return false;
        }

        $response = $this->http->JsonLog(null, 3, true);
        $this->token = ArrayVal($response, 'token');

        if ($this->http->FindPreg('/\{\"success\":true/') && $this->token) {
            $this->State['token'] = $this->token;

            return true;
        }

        if ($error = ArrayVal(ArrayVal($response, 'data'), 'ns0:Message')) {
            $this->checkCredentials($error);
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != "https://www.thaiairways.com/app/rop/web/get_member_profile") {
            $this->http->PostURL("https://www.thaiairways.com/app/rop/web/get_member_profile", [
                "member_id" => strtoupper($this->AccountFields['Login']),
                "token"     => $this->token,
            ]);
        }
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data');

        if (!isset($data['MemberProfileRS'])) {
            if (
                $this->http->FindPreg("/(<h1>Service Temporarily Unavailable)<\/h1>/ims")
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }
        // Balance - Current Mileage
        $this->SetBalance(ArrayVal($data['MemberProfileRS'], 'RemainingMiles'));
        // Name
        $name = Html::cleanXMLValue(
            ArrayVal($data['MemberProfileRS'], 'Salutation')
            . " " . ArrayVal($data['MemberProfileRS'], 'FirstName')
            . " " . ArrayVal($data['MemberProfileRS'], 'MiddleName')
            . " " . ArrayVal($data['MemberProfileRS'], 'LastName'));
        $this->SetProperty("Name", beautifulName($name));
        // Status
        $this->SetProperty("Status", ArrayVal($data['MemberProfileRS'], 'PrivilegeCard'));
        // Member ID
        $this->SetProperty("AccountNumber", ArrayVal($data['MemberProfileRS'], 'MemberID'));

        $this->http->PostURL("https://www.thaiairways.com/app/rop/web/get_mileage_info", [
            "member_id"  => strtoupper($this->AccountFields['Login']),
            "start_date" => "",
            "token"      => $this->token,
        ]);
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data');

        if (!$data) {
            return;
        }
        // Expiration Date  // refs #6469
        $nodes = ArrayVal($data['MileageInformationRS'], 'MilesExpiry', []);
        $countNodes = count($nodes);
        $this->logger->debug("Total nodes found: {$countNodes}");
        $quarter = 0;

        foreach ($nodes as $node) {
            $date = $node['MilesExpireyDate'];
            $expMiles = $node['Amount'];
            $this->logger->debug("Date: {$date} / {$expMiles}");

            if ($expMiles == 0) {
                $quarter++;
            }

            if ($expMiles > 0 && (!isset($exp) || $exp < strtotime($date))) {
                $exp = strtotime($date);
                // Miles To Expire
                $this->SetProperty("MilesToExpire", $expMiles);
                $this->SetExpirationDate($exp);
            }// if ($expMiles > 0 && strtotime($exp))
        }// foreach ($nodes as $node)

        if (
            (!isset($exp) && $quarter == $countNodes && $countNodes > 0)
            || (isset($exp) && $exp < strtotime("31 Dec 2022"))
        ) {
            $this->ClearExpirationDate();

            // refs #21189
            if (time() < strtotime("31 Dec 2022")) {
                $this->logger->notice("extend exp date by provider rules");
                $this->SetExpirationDate(strtotime("31 Dec 2023"));
            }
        }
    }

    public function GetHistoryColumns()
    {
        return [
            'Date'                    => 'PostingDate',
            'Code'                    => 'Info',
            'Flight Number'           => 'Info',
            'Service Class'           => 'Info',
            'Transaction Description' => 'Description',
            'Earned Miles'            => 'Miles',
            'Qualifying Miles'        => 'Info',
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->FilterHTML = false;
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        if (!$this->collectedHistory) {
            return $result;
        }

//        $this->http->GetURL("http://www.thaiairways.com/app/rop/web/get_mileage_info/?member_id=".strtoupper($this->AccountFields['Login'])."&start_date=12");
        // get more than 1 year transactions
        $this->http->PostURL("https://www.thaiairways.com/app/rop/web/get_mileage_info", [
            "member_id"  => strtoupper($this->AccountFields['Login']),
            "start_date" => "60",
            "token"      => $this->token,
        ]);
        $startIndex = sizeof($result);
        $result = $this->ParseHistoryPage($startIndex, $startDate);

        $this->getTime($startTimer);

        return $result;
    }

    public function ParseHistoryPage($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data');

        if (!$data) {
            return $result;
        }
        $nodes = ArrayVal($data['MileageInformationRS'], 'ActivityDetails', []);
        $this->logger->debug("Found " . count($nodes) . " items");
        // if history has only one row
        if (isset($nodes['ActivityDate'], $nodes['DescriptionDetails'])) {
            $nodes = [$nodes];
        }

        foreach ($nodes as $node) {
//            $this->http->Log("<pre>".var_export($node, true)."</pre>", false);
            $dateStr = ArrayVal($node, 'ActivityDate');
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->debug("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Code'] = ArrayVal($node, 'PartnerCode');
            $result[$startIndex]['Flight Number'] = ArrayVal($node, 'FlightNumber');
            $result[$startIndex]['Service Class'] = ArrayVal($node, 'FlightClass');
            $descriptionDetails = ArrayVal($node, 'DescriptionDetails');

            if (ArrayVal($descriptionDetails, 'Description')) {
                $result[$startIndex]['Transaction Description'] = ArrayVal($descriptionDetails, 'Description');
            } else {
                $description = [];

                if (is_array($descriptionDetails)) {
                    foreach ($descriptionDetails as $descriptionDetail) {
                        $description[] = ArrayVal($descriptionDetail, 'Description');
                    }
                }
                $result[$startIndex]['Transaction Description'] = implode('; ', $description);
            }
            $result[$startIndex]['Earned Miles'] = ArrayVal($node, 'TotalMiles');

            if (empty($result[$startIndex]['Earned Miles']) && !empty(ArrayVal($descriptionDetails, 'EarnedMiles'))) {
                $result[$startIndex]['Earned Miles'] = ArrayVal($descriptionDetails, 'EarnedMiles');
            }
            $result[$startIndex]['Qualifying Miles'] = ArrayVal($node, 'QualifyingMiles');
            $startIndex++;
        }

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://www.thaiairways.com/en/Manage_My_Booking/My_Booking.page';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        // Reservation retrieve form screenshot 1447243200
        // Reservation details screenshot 1447243229
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->LogHeaders = true;

        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $parseStatus = $this->http->ParseForm("viewbookingform");

        if (!$parseStatus) {
            return $this->notifications($arFields);
        }

        // $this->http->FormURL = 'http://booking.thaiairways.com/retrievePnrEnc/PnrController';
        $this->http->FormURL = 'https://www.thaiairways.com/retrievePnrEnc/PnrController';

        $inputs = [
            'pnrCode'                  => $arFields['ConfNo'],
            'pnr_Code'                 => 'Enter Reservation Code',
            'lastName'                 => $arFields['LastName'],
            'last_Name'                => 'Enter Passenger Last Name',
            'frdPath'                  => '/Manage_My_Booking/view_booking',
            'iwPreActions'             => 'redirectViewBooking',
            'LANGUAGE'                 => 'GB',
            'REC_LOC'                  => $arFields['ConfNo'],
            'DIRECT_RETRIEVE_LASTNAME' => $arFields['LastName'],
        ];
        // unset($this->http->Form['pnr_Code']);
        // unset($this->http->Form['last_Name']);
        // unset($this->http->Form['frdPath']);
        // unset($this->http->Form['iwPreActions']);

        foreach ($inputs as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }

        $postStatus = $this->http->PostForm();

        if (!$postStatus) {
            return $this->notifications($arFields);
        }

        $this->http->FilterHTML = false;
        $parseStatus = $this->http->ParseForm('form1');
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

//        if (isset($responseStr->challenge, $responseStr->gt)) {
//            $captcha = $this->parseGeetTestCaptcha($responseStr->gt, $responseStr->challenge);

        if (!$parseStatus) {
            return $this->notifications($arFields);
        }

        $this->http->RetryCount = 0;
        $postStatus = $this->http->PostForm();

        if (!$postStatus) {
            return $this->notifications($arFields);
        }

        $this->http->RetryCount = 2;

        if ($message = $this->http->FindPreg('/We are unable to find this confirmation number. Please validate your entry and try again/i')) {
            return $message;
        }

        $it['Kind'] = "T";
        $it['RecordLocator'] = $this->http->FindPreg('/locator":"([^\"]+)"/');
        // ReservationDate
        $reservationDate = $this->http->FindPreg('/creationDate":"([^\"]+)"/');

        if ($reservationDate = strtotime($reservationDate)) {
            $it['ReservationDate'] = $reservationDate;
        }
        // Passengers
        $json = $this->http->FindPreg('/"TravellerList"\s*:\s*(\{.+\})\s*,\s*"UpgradeServiceBreakdown"/');
//        $this->http->Log("<pre>".var_export($json, true)."</pre>", false);
        $travellerList = $this->http->JsonLog($json, 3, true);
        $travellers = ArrayVal($travellerList, 'Travellers', []);
        $numbers = [];

        foreach ($travellers as $traveller) {
            $it['Passengers'][] = beautifulName(
                ArrayVal($traveller['IdentityInformation'], 'IDEN_TitleName') . " " .
                ArrayVal($traveller['IdentityInformation'], 'IDEN_FirstName') . " " .
                ArrayVal($traveller['IdentityInformation'], 'IDEN_LastName')
            );
            // AccountNumbers
            if (isset($traveller['FrequentFlyer'][0])) {
                $number = ArrayVal($traveller['FrequentFlyer'][0], 'FREQ_Airline') . " " . ArrayVal($traveller['FrequentFlyer'][0], 'FREQ_Number');
            }

            if (!empty($number)) {
                $numbers[] = $number;
            }
        }// foreach ($travellers as $traveller)
        // AccountNumbers
        if (!empty($numbers)) {
            $it['AccountNumbers'] = $numbers;
        }
        // may be wrong values
//        // TotalCharge
//        $it['TotalCharge'] = $this->http->FindPreg('/"totalAmount":\s*([\d\.]+),\s*"tax"/');
//        // Tax
//        $it['Tax'] = $this->http->FindPreg('/"tax":\s*([\d\.]+),/');
//        // BaseFare
//        $it['BaseFare'] = $this->http->FindPreg('/"amountWithoutTaxAndFee":\s*([\d\.]+),/');
//        // Currency
//        $it['Currency'] = $this->http->FindPreg('/"currency":\s*\{"name":\s*"[^\"]+"\s*,\s*"code":\s*"([A-Z]{3})"\s*,/');

        // Segments

        $json = $this->http->FindPreg('/"ListItineraryView"\s*:\s*(\{.+)\}\s*,\s*"forms"/ims');
//        $this->http->Log("<pre>".var_export($json, true)."</pre>", false);
        $listItineraryView = $this->http->JsonLog($json, 3, true);
        $listItineraryElem = ArrayVal($listItineraryView, 'listItineraryElem', []);
        $this->logger->debug("Total " . count($listItineraryElem) . " legs were found");

        foreach ($listItineraryElem as $elem) {
            $segments = ArrayVal($elem, 'listSegment', []);
            $this->logger->debug("Total " . count($segments) . " segments were found");

            foreach ($segments as $segment) {
                $seg = [];
//                $this->http->Log("segment: <pre>".var_export($segment, true)."</pre>", false);
                // FlightNumber
                $seg['FlightNumber'] = ArrayVal($segment, 'flightNumber');
                // DepName
                $seg['DepName'] = ArrayVal($segment['beginLocation'], 'locationName') . ", " . ArrayVal($segment['beginLocation'], 'cityName') . ", " . ArrayVal($segment['beginLocation'], 'countryName');
                // DepCode
                $seg['DepCode'] = ArrayVal($segment['beginLocation'], 'locationCode');
                // DepDate
                $seg['DepDate'] = strtotime(ArrayVal($segment, 'beginDate'));
                // DepartureTerminal
                $seg['DepartureTerminal'] = ArrayVal($segment, 'beginTerminal');
                // ArrName
                $seg['ArrName'] = ArrayVal($segment['endLocation'], 'locationName') . ", " . ArrayVal($segment['endLocation'], 'cityName') . ", " . ArrayVal($segment['endLocation'], 'countryName');
                // ArrivalTerminal
                $seg['ArrivalTerminal'] = ArrayVal($segment, 'endTerminal');
                // ArrCode
                $seg['ArrCode'] = ArrayVal($segment['endLocation'], 'locationCode');
                // ArrDate
                $seg['ArrDate'] = strtotime(ArrayVal($segment, 'endDate'));
                // AirlineName
                $seg['AirlineName'] = ArrayVal($segment['airline'], 'name');
                // Aircraft
                $seg['Aircraft'] = ArrayVal($segment['equipment'], 'name');
                // Cabin
                $seg['Cabin'] = ArrayVal($segment['listCabin'][0], 'name');

                $it['TripSegments'][] = $seg;
            }// foreach ($segments as $segment)
        }// foreach ($listItineraryElem as $elem)

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
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Size"     => 40,
                "Required" => true,
            ],
        ];
    }

    protected function incapsula()
    {
        $this->logger->notice(__METHOD__);
        $referer = $this->http->currentUrl();
        $incapsula = $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src");

        if (isset($incapsula)) {
            sleep(2);
            $this->http->NormalizeURL($incapsula);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($incapsula);
            $this->http->RetryCount = 2;
            $this->logger->debug("parse captcha form");
            $action = $this->http->FindPreg("/xhr.open\(\"POST\", \"([^\"]+)/");

            if (!$action) {
                return false;
            }
            $captcha = $this->parseHCaptcha($referer);

            if ($captcha === false) {
                return false;
            }
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.thaiairways.com' . $action, ['g-recaptcha-response' => $captcha], ["Referer" => $referer, "Content-Type" => "application/x-www-form-urlencoded"]);
            $this->http->RetryCount = 2;
            $this->http->FilterHTML = true;
            sleep(2);
            //$this->http->GetURL($referer);
        }// if (isset($distil))

        return true;
    }

    protected function parseHCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class='h-captcha']/@data-sitekey");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"    => "hcaptcha",
            "pageurl"   => $currentUrl ?? $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function parseReCaptcha($referer)
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("(//div[@class = 'g-recaptcha']/@data-sitekey)[1]");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $referer ? $referer : $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function parseGeetTestCaptcha($gt, $challenge)
    {
        $postData = [
            "type"       => "GeeTestTaskProxyless",
            "websiteURL" => $this->http->currentUrl(),
            "gt"         => $gt,
            "challenge"  => $challenge,
        ];
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($recognizer, $postData);
    }

    private function checkCredentials($message)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->error("[Error]: '{$message}'");
        // Member does not exist
        if (
            strstr($message, 'Member does not exist')
            || strstr($message, 'Incorrect Pin Number')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // XML Parsing Error
        if (strstr($message, 'XML Parsing Error')) {
            throw new CheckException("Invalid credentials", ACCOUNT_INVALID_PASSWORD);
        }
        // Member ID or PIN validation failed
        if ($this->http->FindPreg('/Member ID or PIN validation failed/i')) {
            throw new CheckException("Invalid credentials", ACCOUNT_INVALID_PASSWORD);
        }
        // Your account is locked
        if ($message == "Account is locked" || $this->http->FindPreg("/Account is locked/", false, $message)) {
            throw new CheckException("Your account is locked.", ACCOUNT_LOCKOUT);
        }
        // Member must be 'active' or 'dormant' to perform this funtion
        if ($message = $this->http->FindPreg('/Member must be \'active\' or \'dormant\' to perform this funtion/i')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        $this->DebugInfo = $message;
    }

    private function parseQuestion($otpRef)
    {
        $this->logger->notice(__METHOD__);

        if (!empty($otpRef)) {
            $question = 'The OTP Code is a 4 digit number sent to your registered e-mail address. Please keep your OTP code secured and submit this code within 5 minutes';
        }

        if (!$question) {
            return false;
        }
        $this->State['otpRef'] = $otpRef;
//        $this->Question = $question;
//        $this->ErrorCode = ACCOUNT_QUESTION;
//        $this->Step = "Question";
        $this->AskQuestion($question, null, 'Question');

        return true;
    }

    private function notifications($arFields)
    {
        $this->sendNotification("failed to retrieve itinerary by conf #", 'all', true,
            "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}");

        return null;
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $this->http->userAgent = $selenium->seleniumOptions->userAgent;
            $selenium->driver->manage()->window()->maximize();
            $selenium->http->GetURL('https://www.thaiairways.com/en_TH/rop/index.page');
            $selenium->waitForElement(WebDriverBy::id('member_id'), 10);
            $this->savePageToLogs($selenium);

            foreach ($selenium->driver->manage()->getCookies() as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (UnknownErrorException $e) {
            $this->logger->error('Exception: ' . $e->getMessage(), ['pre' => true]);

            if (stripos($e->getMessage(), 'page crash') !== false) {
                $retry = true;
            }
        } finally {
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 5);
            }
        }
    }
}
