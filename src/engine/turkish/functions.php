<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerTurkish extends TAccountChecker
{
    use ProxyList;

    public $regionOptions = [
        ""  => "Select your login type",
        "1" => "Membership number",
        "2" => "Email address",
        //        "3" => "Mobile number (Appropriate format: Area Code - Number)",
        "4" => "Turkish ID number",
    ];
    protected $airCodes;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/json; charset=utf-8",
            "X-Requested-With" => "XMLHttpRequest",
            "country"          => "us",
            "page"             => "https://www.turkishairlines.com/en-us/",
        ];
//        $this->http->GetURL("https://www.turkishairlines.com/en-us/index.html");
//        $pageRequestId = $this->http->FindSingleNode('//meta[@name = "PageRequestID"]/@content');
//        if (isset($pageRequestId)) {
//            $headers["pageRequestId"] = $pageRequestId;
//        }
        $this->http->GetURL("https://www.turkishairlines.com/com.thy.web.online.miles/ms/miles/memberprofile?_=" . date("UB"), $headers, 20);

        if (strpos($this->http->Error, 'Network error 28 - Connection timed out after') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Unexpected EOF') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 403 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            || $this->http->Response['code'] == 403
        ) {
            throw new CheckRetryNeededException(3, 0);
        }
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data');

        if (ArrayVal($data, 'milesProgramInfo', null)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // AccountID: 2915447
        $this->AccountFields["Login2"] = empty($this->AccountFields["Login2"]) ? "1" : $this->AccountFields["Login2"];

        // AccountID: 4484540
        // Please enter a valid email address.
        if ($this->AccountFields["Login2"] == '2' && filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        $this->http->LogHeaders = true;
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.turkishairlines.com/en-us/index.html", [], 45);

        if ($this->isBadConnect() || empty($this->http->Response['body'])) {
            $this->setProxyBrightData();
            $this->http->removeCookies();
            $this->http->GetURL("https://www.turkishairlines.com/en-us/index.html", [], 45);

            if ($this->isBadConnect()) {
                throw new CheckRetryNeededException(5, 0);
            }
        }
        $this->http->RetryCount = 2;

        $pageRequestId = $this->http->FindSingleNode('//meta[@name = "PageRequestID"]/@content');
        $jsessionid = $this->http->FindSingleNode('//meta[@name = "jsessionid"]/@content');

        if ($this->http->Response["code"] != 200) {
            return $this->checkErrors();
        }

        $this->AccountFields["Login"] = preg_replace("/\s/ims", '', $this->AccountFields["Login"]);
        $this->AccountFields["Login"] = preg_replace("/^TK/ims", "", $this->AccountFields["Login"]);

        if ($this->AccountFields["Login2"] == "3") {
            $this->AccountFields["Login"] = str_replace("-", "", $this->AccountFields["Login"]);
        }
//        $this->http->SetInputValue('j_username', $this->AccountFields["Login"]);
//        $this->http->SetInputValue('j_password', $this->AccountFields['Pass']);

        // AccountID: 2915447
        if ($this->AccountFields["Login2"] == '1') {
            $this->AccountFields["Login"] = substr($this->AccountFields["Login"], 0, 9);
        }

        $data = [
            "username"      => $this->AccountFields["Login"],
            "loginType"     => $this->AccountFields["Login2"],
            "password"      => $this->AccountFields['Pass'],
            "recaptchaInfo" => new stdClass(), // "recaptchaInfo":{} or "recaptchaInfo":{"response":""}
        ];
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/json; charset=utf-8",
            "X-Requested-With" => "XMLHttpRequest",
            //            "comarch"          => "",
            "country"          => "us",
            "page"             => "https://www.turkishairlines.com/en-us/index.html",
            "pageRequestId"    => $pageRequestId,
            //'requestid'        => '5b36a177-c0ef-4964-b7d8-decdc84f6be0',
            "Referer"          => "https://www.turkishairlines.com/en-us/index.html",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.turkishairlines.com/com.thy.web.online.miles/ms/login/signin{$jsessionid}", json_encode($data), $headers);

        if ($this->isBadConnect()) {
            throw new CheckRetryNeededException(5, 0);
        }

        // fixed some account (AccountID: 2564011, 3686882)
        if (strstr($this->http->Response['body'], '{"type":"WARNING","error":{"validationMessages":[{"field":"password","msg":"Error-MS-MS002012101","id":"SER-TMS-MS002012101","code":"MS002012101","global":false}]') && $data['loginType'] == '1') {
            $this->logger->notice("truncate login to 9 symbols");
            $data['username'] = substr($data['username'], 0, 9);
            $this->http->PostURL("https://www.turkishairlines.com/com.thy.web.online.miles/ms/login/signin{$jsessionid}", json_encode($data), $headers);
        }

        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - Zero size object')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
            || ($this->http->Response['code'] == 500 && $this->http->FindPreg("/Error 500\: javax.servlet.ServletException: Filter \[MDCInsertingServletFilter\]/"))
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($msg = $this->http->FindSingleNode('//h2[contains(text(), "Our website is undergoing some scheduled maintenance.")]')) {
            throw new CheckException($msg, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        // Access is allowed
        if (isset($response->type) && $response->type == 'SUCCESS') {
            return true;
        }
        // Invalid credentials
        if (isset($response->error->validationMessages[0]->code)) {
            $message = $response->error->validationMessages[0]->code;
            $this->logger->error("[ErrorCode]: {$message}");
            // The password you have entered is incorrect.
            if (
                $message == 'MS00201201'
                || $message == 'MS002012VAL_ERROR'
                || $message == 'MS00201210' // AccountID: 4460185
                || $message == 'MS00201211'
                || $message == 'MS00201212'
                || $message == 'MS00201225' // sq or just account lockout?
            ) {
                throw new CheckException("The password you have entered is incorrect.", ACCOUNT_INVALID_PASSWORD);
            }
            // Please enter a valid Miles&Smiles membership number or password and try again.
            if ($message == 'MS002012112') {
                throw new CheckException("Please enter a valid Miles&Smiles membership number or password and try again.", ACCOUNT_INVALID_PASSWORD);
            }
            // Your membership has been canceled.
            if ($message == 'MS00201203') {
                throw new CheckException("Your membership has been canceled.", ACCOUNT_INVALID_PASSWORD);
            }
            // Your membership has been suspended because you have entered an incorrect password 3 times.
            if ($message == 'MS00201205') {
                throw new CheckException("Your membership has been suspended because you have entered an incorrect password 3 times.", ACCOUNT_LOCKOUT);
            }
            // You have entered an incorrect Miles&Smiles membership number. Please check and try again.
            if ($message == 'MS002012101') {
                throw new CheckException("You have entered an incorrect Miles&Smiles membership number. Please check and try again.", ACCOUNT_INVALID_PASSWORD);
            }
            // Turkish ID number has been entered incorrectly.
            if ($message == 'MS002012104' || $message == 'MS002012109') {
                throw new CheckException("Turkish ID number has been entered incorrectly.", ACCOUNT_INVALID_PASSWORD);
            }
            // The email address you have entered is associated with another membership. Please change the email address.
            if ($message == 'MS002012105') {
                throw new CheckException("The email address you have entered is associated with another membership. Please change the email address.", ACCOUNT_INVALID_PASSWORD);
            }
            // To continue, you need to change your password on the website.
            if ($message == 'MS00201206') {
                throw new CheckException("To continue, you need to change your password on the website.", ACCOUNT_INVALID_PASSWORD);
            }
            // Please enter a valid email address.
            if ($message == 'MS002012108') {
                throw new CheckException("Please enter a valid email address. Please tray again or sign in using your Miles&Smiles membership number.", ACCOUNT_INVALID_PASSWORD);
            }
            // Your sign in preference is invalid. Please set your account sign in preferences.
            if ($message == 'MS002012111') {
                throw new CheckException("Your sign in preference is invalid. Please set your account sign in preferences.", ACCOUNT_INVALID_PASSWORD);
            }
            // This membership number has been combined with another membership under your name.
            if ($message == 'MS00201204') {
                throw new CheckException("This membership number has been combined with another membership under your name.", ACCOUNT_INVALID_PASSWORD);
            }
            // Your membership status has been changed to pending because you have entered an incorrect password 3 times.
            if ($message == 'MS00201221') {
                throw new CheckException("Your membership status has been changed to pending because you have entered an incorrect password 3 times.", ACCOUNT_LOCKOUT);
            }
            // AccountID: 4338862, 2907794
            if ($message == 'MS00201208') {
                throw new CheckException("Invalid credentials.", ACCOUNT_INVALID_PASSWORD);
            }

            // maintenance
            if ($message == 'MS_CUTOVER') {
                throw new CheckException('Miles&Smiles services are out of service due to planned infrastructure works.', ACCOUNT_PROVIDER_ERROR);
            }

            // for debug
            $this->DebugInfo = $message;
        }// if (isset($response->error->validationMessages[0]->code))
        elseif (isset($response->error->validationMessages[0]->msg)) {
            $msg = $response->error->validationMessages[0]->msg ?? null;
            $this->logger->error("msg: {$msg}");
            // for debug
            $this->DebugInfo = $msg;
            // AccountID: 4286251
            if ($msg == 'Error-MS-MS00200803') {
                throw new CheckException("The password you have entered is incorrect.", ACCOUNT_INVALID_PASSWORD);
            }
            // AccountID: 4249237
            if ($msg == 'Error-MS-01') {
                throw new CheckException("Your sign in preference is invalid. Please set your account sign in preferences.", ACCOUNT_INVALID_PASSWORD);
            }
            // AccountID: 2669425
            if ($msg == 'Error-MS-null') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // AccountID: 4857657
            if ($msg == 'Error-MS-MS03108003') {
                throw new CheckException("You have entered an invalid information. Please check and try again.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($msg == 'Error-CAMS-07') {
                throw new CheckRetryNeededException(2, 10, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// elseif (isset($response->error->validationMessages[0]->msg))
        elseif (isset($response->error->messages[0]->code)) {
            $code = $response->error->messages[0]->code;
            $this->logger->error("code: {$code}");
            // AccountID: 4720220
            if ($code == 'ERR-GENEL-08' && $response->error->messages[0]->msg == null) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $responseAuth = $this->http->JsonLog();

        if (!stristr($this->http->currentUrl(), 'https://www.turkishairlines.com/com.thy.web.online.miles/ms/miles/memberprofile?_=')) {
            $this->http->GetURL("https://www.turkishairlines.com/com.thy.web.online.miles/ms/miles/memberprofile?_=" . date("UB"));
        }

        $response = $this->http->JsonLog(null, 3, true);

        // refs #18129
        if (!$this->http->FindPreg("/" . str_replace('+', '\+', $this->AccountFields['Login']) . "/ims")) {
            if (in_array($this->AccountFields['Login'], ['436208578'])) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (isset($responseAuth->data->forceChangePinNumber) && $responseAuth->data->forceChangePinNumber) {
                $this->throwProfileUpdateMessageException();
            }

            throw new CheckRetryNeededException();
        }

        $data = ArrayVal($response, 'data');
        // Name
        $personalInfo = ArrayVal($data, 'personalInfo');
        $this->SetProperty('Name', beautifulName(ArrayVal($personalInfo, 'firstName') . " " . ArrayVal($personalInfo, 'surname')));
        // Member ID
        $milesProgramInfo = ArrayVal($data, 'milesProgramInfo');
        $this->SetProperty('AccountNumber', ArrayVal($milesProgramInfo, 'ffId'));
        // Balance - Total miles
        $this->SetBalance(ArrayVal($milesProgramInfo, 'totalMiles'));
        //  Total Miles since joining
        $this->setPropertyFromJson($milesProgramInfo, "TotalMilesSince", "milesSinceEnrolment");
        // Status miles
        $this->setPropertyFromJson($milesProgramInfo, "StatusMiles", "statusMiles");
        // Status Miles (Last 12 months)
        $this->setPropertyFromJson($milesProgramInfo, "StatusMilesYTD", "statusMilesLastYear");
        // Status
        $status = ArrayVal($milesProgramInfo, 'cardType');
        $this->logger->debug("Status >> " . $status);

        switch ($status) {
            case 'CC':
                $status = "Classic";

                break;

            case 'CP':
                $status = "Classic Plus";

                break;

            case 'EC':
                $status = "Elite";

                break;

            case 'EP':
                $status = "Elite Plus";

                break;
        }// switch ($status)
        $this->SetProperty('Status', $status);
        // Expiry date (Status Expiration)
        $expireDate = ArrayVal($milesProgramInfo, 'expireDate');

        if ($expireDate) {
            $expireDate = preg_replace('/000$/', '', $expireDate);
            $this->SetProperty("StatusExpiration", date("F d, Y", $expireDate));
        }// if ($expireDate)

        if ($this->Balance <= 0) {
            return;
        }
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $this->http->GetURL("https://www.turkishairlines.com/com.thy.web.online.miles/ms/miles/getExpiredMilesInfo?_=" . date("UB"));
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data', []);
        $exp = null;

        foreach ($data as $expRow) {
            $expDate = $this->ModifyDateFormat(ArrayVal($expRow, 'year'), '.');

            if (!isset($exp) || $exp > strtotime($expDate)) {
                $exp = strtotime($expDate);
                // Expiration Date
                $this->SetExpirationDate($exp);
                // Miles to expire
                $this->SetProperty("MilesToExpire", number_format(ArrayVal($expRow, 'mile')));
            }// if (!isset($exp) || $exp > strtotime($expDate))
        }// foreach ($data as $expRow)
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->GetURL("https://www.turkishairlines.com/com.thy.web.online.ibs/ibs/booking/activepnrs?showall=true&_=" . date("UB"));
        $response = $this->http->JsonLog(null, 3, true);

        if ($this->http->Response['body'] == '{"type":"SUCCESS","newWindow":false,"preventRedirect":false,"infoMessages":[null]}'
            || $this->http->Response['body'] == '{"type":"SUCCESS","newWindow":false,"preventRedirect":false,"data":[],"infoMessages":[]}') {
            return $this->noItinerariesArr();
        }

        $data = ArrayVal($response, 'data', []);
        $this->logger->notice("Total " . count($data) . " reservations were found");

        foreach ($data as $itinerary) {
            $this->logger->info('Parse itinerary #' . ArrayVal($itinerary, 'pnr'), ['Header' => 3]);
            $res = $this->parseJsonItinerary($itinerary);

            if (!empty($res)) {
                $its = $this->checkResItinerary($res);

                foreach ($its as $it) {
                    $result[] = $it;
                }
            }
        }// foreach ($data as $itinerary)

        return $result;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"        => "PostingDate",
            "Type"        => "Info",
            "Description" => "Description",
            "Miles"       => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $page = 0;
        $this->http->GetURL("https://www.turkishairlines.com/com.thy.web.online.miles/ms/miles/memberawards?_=" . date("UB"));

        do {
            $this->logger->debug("[Page: {$page}]");

            if ($page > 0) {
                $this->http->GetURL("https://www.turkishairlines.com/com.thy.web.online.miles/ms/miles/memberaallactivities?pageNumber={$page}&_=" . date("UB"));
            }
            $page++;
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
        } while (
            !$this->http->FindPreg("/\"data\":\[\]/")
            && $page < 10
            && $this->http->Response['code'] == 200
        );

        usort($result, function ($a, $b) { return $b['Date'] - $a['Date']; });

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog(null, 0, true);
        $nodes = ArrayVal($response, 'data', []);
        $this->logger->debug("Found " . count($nodes) . " items were found");

        foreach ($nodes as $node) {
            $activityDatas = ArrayVal($node, 'activityDatas', null);
            $awardDatas = false;

            if (!isset($activityDatas)) {
                $awardDatas = true;
                $activityDatas = ArrayVal($node, 'awardDatas', null);
            }
            $this->logger->debug("Found " . count($activityDatas) . " activityDatas items were found");

            foreach ($activityDatas as $activityData) {
                $dateStr = ArrayVal($activityData, 'activityDate', null) ?? ArrayVal($activityData, 'issueDate', null);
                $postDate = preg_replace('/\d{3}$/', '', $dateStr);
                $dateStr = date("m/d/Y", $postDate);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    continue;
                }// if (isset($startDate) && $postDate < $startDate)
                $result[$startIndex]['Date'] = $postDate;
                $result[$startIndex]['Description'] = ArrayVal($activityData, 'description', null) ?? ArrayVal($activityData, 'definition');

                // json bug fix
                $exclude = [
                    'Card Renewal',
                    'Card criteria has not been fullfilled in the first year.',
                ];

                if (in_array($result[$startIndex]['Description'], $exclude)) {
                    $this->logger->notice("Skip {$result[$startIndex]['Description']} transaction");

                    continue;
                }

                if (
                    ArrayVal($activityData, 'flightNumber', null)
                    || $awardDatas == true
                ) {
                    $result[$startIndex]['Type'] = 'Flight';
                    $result[$startIndex]['Miles'] = ArrayVal($activityData, 'miles');
                } else {
                    $result[$startIndex]['Type'] = 'Other';
                    $result[$startIndex]['Miles'] = ArrayVal($activityData, 'points');
                }
                $startIndex++;
            }// foreach ($activityDatas as $activityData)
        }

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.turkishairlines.com/en-us/index.html";
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Ticket # or reservation code",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName"      => [
                "Caption"  => "Passenger Surname",
                "Type"     => "string",
                "Size"     => 20,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        //$pageRequestId = $this->http->FindSingleNode('//meta[@name = "PageRequestID"]/@content');
        //$jsessionid = $this->http->FindSingleNode('//meta[@name = "jsessionid"]/@content');

        if ($this->http->Response["code"] != 200) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }
        $data = [
            "pnr"              => strtoupper($arFields['ConfNo']),
            "surname"          => strtoupper($arFields['LastName']),
            "passengers"       => [],
            "tccManageBooking" => false,
            "retrieveBaggage"  => true,
        ];
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/json; charset=utf-8",
            "X-Requested-With" => "XMLHttpRequest",
            "comarch"          => "",
            "country"          => "us",
            "page"             => "https://www.turkishairlines.com/en-us/",
            "Requestid"        => $this->getUuid(),
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.turkishairlines.com/com.thy.web.online.ibs/ibs/booking/searchreservation", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 1, true);

        if ($itinerary = ArrayVal($response, 'data', [])) {
            $it = $this->parseJsonItinerary($itinerary);
        } elseif ($this->http->FindPreg("/\"msg\":\"TICKET_NUMBER_INVALID\"/")) {
            return 'Your eTicket number is incomplete or incorrect. Please enter the 13-digit numerical code which appears in the “Ticket number” box on your ticket. If you do not know your eTicket number, you can leave this field blank and enter your reservation code (PNR).';
        } elseif ($this->http->FindPreg("/\"msg\":\"The following field contains unsuitable characters/")) {
            return 'The field contains unsuitable characters';
        } elseif (
            $this->http->FindPreg("/\"(?:msg|reason)\":\"SURNAME_NOT_VERIFIED_FOR_PNR\"/")
        ) {
            return 'The surname you entered cannot be found. Please check the passenger\'s surname and try again.';
        } elseif ($this->http->FindPreg("/\"reason\":\"PNR_NUMBER_NOT_VALID\"/")) {
            return 'You entered an incorrect or incomplete reservation code (PNR). Please check your PNR and try again.';
        } elseif ($this->http->FindPreg("/\"code\":\"ERR-GENEL-08\"/")) {
            return 'Please check your PNR and try again.';
        } elseif ($this->http->FindPreg('/"reason":"UNABLE_TO_RETRIEVE_PNR_FROM_TROYA_AND_TROPHY"/')) {
            return 'Please check your PNR and try again.';
        } elseif ($this->http->FindPreg('/"code":"Error-BOS-55000","msg":"TICKETS_COULD_NOT_BE_DISPLAYED"/')) {
            return 'Please check your PNR and try again.';
        } elseif ($this->http->FindPreg('/"reason":"TICKETS_COULD_NOT_BE_DISPLAYED"/')) {
            return 'Please check your PNR and try again.';
        } elseif ($this->http->FindPreg('/"reason":"NOT_ALLOWED_OTHER_AIRLINE_TICKET"/')) {
            return 'This reservation cannot be viewed due to the sales channel in which it was formed.';
        } elseif ($this->http->FindPreg('/"code":"surnameViolation","global":true}\],"messages":\[\],"type":"FAILURE"/')) {
            return 'surname Violation';
        } elseif ($this->http->FindPreg('/"code":"Error-BOS-42016","global":true\}\],"messages":\[\],"type":"FAILURE",/')) {
            return 'We are unable to process your transaction due to missing information in your reservation. Please contact our call center.';
        } else {
            $this->sendNotification("failed to retrieve itinerary by conf #");
        }

        return null;
    }

    public function getUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $url =
            $this->http->FindSingleNode('//img[@src = "Captcha.jpg"]/@src') ?:
                $this->http->FindSingleNode('//img[@src = "jcaptcha.jpg"]/@src');

        if (!$url) {
            return false;
        }
        $this->http->NormalizeURL($url);

        $file = $this->http->DownloadFile($url, "jpg");
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $captcha = $this->recognizeCaptcha($recognizer, $file);
        unlink($file);

        return $captcha;
    }

    protected function isBadConnect(): bool
    {
        return strpos($this->http->Error, 'Network error 28 - Connection timed out after') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 403 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 407 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 28 - Unexpected EOF') !== false
            || strpos($this->http->Error, 'Network error 56 - Unexpected EOF') !== false
            || strpos($this->http->Error, 'Network error 35 - Unexpected EOF') !== false
            || strpos($this->http->Error, 'Network error 16 -') !== false
            || strpos($this->http->Error, 'Network error 35 - OpenSSL SSL_connect') !== false
            || strpos($this->http->Error, 'Network error 7 - Unexpected EOF') !== false
            || strpos($this->http->Error, 'Network error 7 - Failed to connect to') !== false
            || strpos($this->http->Error, 'Network error 56 - Proxy CONNECT aborted') !== false
            || strpos($this->http->Error, 'Network error 56 - Recv failure') !== false
            || strpos($this->http->Error, 'Network error 52 - Empty reply from server') !== false
            || strpos($this->http->Error, 'Network error 0 -') !== false
            || strpos($this->http->Error, 'Operation timed out after') !== false
            || $this->http->Response['code'] == 403;
    }

    private function setPropertyFromJson($milesProgramInfo, $propertyName, $jsonPropertyName)
    {
        $this->logger->notice(__METHOD__);
        $property = ArrayVal($milesProgramInfo, $jsonPropertyName);

        if ($property !== '') {
            $this->SetProperty($propertyName, number_format($property));
        } else {
            $this->logger->notice("{$propertyName} not found");
        }
    }

    private function checkResItinerary(array $it)
    {
        // hard code for TK392: separate flight and bus IST->XHQ
        if (!isset($it['TripSegments'])) {
            return [$it];
        }
        $it2 = $it;

        if (isset($it2['TotalCharge'])) {
            unset($it2['TotalCharge']);
        }

        if (isset($it2['Currency'])) {
            unset($it2['Currency']);
        }

        if (isset($it2['SpentAwards'])) {
            unset($it2['SpentAwards']);
        }
        $it2['TripCategory'] = TRIP_CATEGORY_BUS;
        $it2['TripSegments'] = [];

        foreach ($it['TripSegments'] as &$seg) {
            if ($seg['AirlineName'] === 'TK' && (int) $seg['FlightNumber'] === 392 && $seg['DepCode'] === 'IST' && $seg['ArrCode'] === 'XHQ') {
                $seg2 = $seg;
                $seg['ArrCode'] = 'BUS';
                $seg['ArrName'] = 'BUS';
                $seg['ArrDate'] = MISSING_DATE;
                $seg2['DepCode'] = 'BUS';
                $seg2['DepName'] = 'BUS';
                $seg2['DepDate'] = MISSING_DATE;

                if (isset($seg2['Seats'])) {
                    unset($seg2['Seats']);
                }

                if (isset($seg2['Duration'])) {
                    unset($seg2['Duration']);
                }

                if (isset($seg2['Aircraft'])) {
                    unset($seg2['Aircraft']);
                }
                $it2['TripSegments'][] = $seg2;
                $flag = true;

                break;
            }
        }

        if (isset($flag)) {
            $res = [$it, $it2];
            $this->logger->debug('Parsed itinerary (exclude bus):');
            $this->logger->debug(var_export($res, true), ['pre' => true]);

            return $res;
        }

        return [$it];
    }

    private function parseJsonItinerary($itinerary)
    {
        $this->logger->notice(__METHOD__);
        $result = ["Kind" => "T"];
        $canceled = ArrayVal($itinerary, 'canceled');

        if ($canceled === true) {
            $result['Cancelled'] = true;
        }
        // ConfirmationNumber
        $result['RecordLocator'] = ArrayVal($itinerary, 'pnr') ?? ArrayVal($itinerary, 'recordKey');
        // TotalCharge
        $totalCharge = ArrayVal(ArrayVal($itinerary, 'totalCharge'), 'amount');

        $totalMealPrice = ArrayVal($itinerary, 'totalMealPrice');

        // TODO: Due to the cost of food in miles, the total total was not displayed correctly
        if (!empty($totalCharge) && ArrayVal($totalMealPrice, 'currency', 0) != 'PTS') {
            $result['TotalCharge'] = $totalCharge;
            $result['Currency'] = ArrayVal(ArrayVal($itinerary, 'totalCharge'), 'currency');
        }

        // BaseFare
        // EarnedAwards
        $milesToBeEarned = ArrayVal($itinerary, 'milesToBeEarned');

        if (!empty($milesToBeEarned)) {
            $result['EarnedAwards'] = $milesToBeEarned . ' miles';
        }
        // SpentAwards
        $awardTicketMileAmount = ArrayVal(ArrayVal($itinerary, 'awardTicketMileAmount'), 'amount');

        if (!empty($awardTicketMileAmount)) {
            $result['SpentAwards'] = $awardTicketMileAmount . ' miles';
        }
        $createDate = ArrayVal($itinerary, 'createDate');

        if ($createDate) {
            $createDate = preg_replace('/000$/', '', $createDate);
            $result['ReservationDate'] = (int) $createDate;
        }
        // Passengers
        $passengerInfo = ArrayVal($itinerary, 'passengers', []);
        $seats = [];
        $result['AccountNumbers'] = [];

        foreach ($passengerInfo as $passenger) {
            $result['Passengers'][] = beautifulName(ArrayVal($passenger, 'fullName'));
            // TicketNumbers
            $ticketNumber = ArrayVal($passenger, 'ticketNumber');

            if (!empty($ticketNumber)) {
                $result['TicketNumbers'][] = $ticketNumber;
            }
            // AccountNumbers
            $accountNumber = ArrayVal(ArrayVal($passenger, 'fqtvInfo'), 'cardNumber');

            if (!empty($accountNumber)) {
                $result['AccountNumbers'][] = $accountNumber;
            }

            $flightStateInfos = ArrayVal($passenger, 'flightStateInfos', []);

            foreach ($flightStateInfos as $flightStateInfo) {
                $seats[] = [
                    "flightNumber" => ArrayVal(ArrayVal($flightStateInfo, 'segment'), 'flightNumber'),
                    "seatNumber"   => ArrayVal($flightStateInfo, 'seatNumber'),
                ];
            }// foreach ($flightStateInfos as $flightStateInfo)
        }// for ($i = 0; $i < $passengerInfo->length; $i++)
        $result['AccountNumbers'] = array_values(array_unique($result['AccountNumbers']));

        $trips = ArrayVal($itinerary, 'allFlights', []);

        if (isset($itinerary['allFlights']) && empty($trips)) {
            if ($canceled === true) {
                $this->logger->debug('Parsed cancelled itinerary:');
                $this->logger->debug(var_export($result, true), ['pre' => true]);

                return $result;
            }
            $this->logger->error('Skip: no segments');

            return [];
        }
        $this->logger->debug("Total " . count($trips) . " trips found");
        $disrupted = false;

        foreach ($trips as $trip) {
            $segments = ArrayVal($trip, 'segments', []);
            $this->logger->debug("Total " . count($segments) . " segments found");

            foreach ($segments as $segment) {
                $wkSegment = ArrayVal($segment, 'wkSegment');

                if ($wkSegment === true) {
                    $this->logger->error('Skipping disrupted segment');
                    $disrupted = true;

                    continue;
                }
                $seg = [];
                // FlightNumber
                $flightNumber = ArrayVal($segment, 'flightNumber');
                $seg['FlightNumber'] = $this->http->FindPreg('/(\d+)$/ims', false, $flightNumber);

                if ($seg['FlightNumber'] === '0') {
                    $this->logger->error('skip reservation. flightNumber = 0');

                    return [];
                }
                // AirlineName
                $seg['AirlineName'] = $this->http->FindPreg('/([^\d]+)/', false, $flightNumber);
                // Seats
                $seg['Seats'] = [];

                foreach ($seats as $seat) {
                    if ($seat['flightNumber'] == $flightNumber) {
                        $seg['Seats'][] = $seat['seatNumber'];
                    }
                }// foreach ($seats as $seat)
                $seg['Seats'] = array_values(array_unique(array_filter($seg['Seats'])));
                $seg['DepCode'] = $seg['DepName'] = ArrayVal(ArrayVal($segment, 'originAirport'), 'code');
                // DepDate
                $departureDateTimeISO = ArrayVal($segment, 'departureDateTimeISO');
                $depDate = ArrayVal($departureDateTimeISO, 'dateLocal');
                $depTime = ArrayVal($departureDateTimeISO, 'hourMinuteLocal');
                $this->logger->debug("DepDate: {$depDate} {$depTime}");

                if ($depDate && $depTime) {
                    $seg['DepDate'] = strtotime($depDate . " " . $depTime);
                }

                $seg['ArrCode'] = $seg['ArrName'] = ArrayVal(ArrayVal($segment, 'destinationAirport'), 'code');
                // ArrDate
                $arrivalDateTimeISO = ArrayVal($segment, 'arrivalDateTimeISO');
                $arrDate = ArrayVal($arrivalDateTimeISO, 'dateLocal');
                $arrTime = ArrayVal($arrivalDateTimeISO, 'hourMinuteLocal');
                $this->logger->debug("ArrDate: {$arrDate} {$arrTime}");

                if ($arrDate && $arrTime) {
                    $seg['ArrDate'] = strtotime($arrDate . " " . $arrTime);
                }
                // Cabin
                $seg['Cabin'] = ArrayVal(ArrayVal(ArrayVal($segment, 'bookingClass'), 'cabinType'), 'name');
                // Booking class
                $seg['BookingClass'] = ArrayVal($segment, 'fareBasisCode');
                // Duration
                $flightDuration = ArrayVal($segment, 'flightDuration');

                if ($flightDuration) {
                    $flightDuration = preg_replace('/000$/', '', $flightDuration);
                    $time = new DateTime('@' . $flightDuration);
                    $seg['Duration'] = sprintf('%02dh %02dm', $time->format('H'), $time->format('i'));
                }// if ($flightDuration)
                // Aircraft
                $seg['Aircraft'] = ArrayVal($segment, 'aircraftType');

                $result['TripSegments'][] = $seg;
            }// foreach ($segments as $segment)
        }

        if ($disrupted && count(ArrayVal($result, 'TripSegments', [])) == 0) {
            $this->logger->error('Skipping itinerary with only disrupted segments');

            return [];
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);
        // debug
        $resultStr = serialize($result);

        if (utf8_encode($resultStr) !== $resultStr) {
            $this->sendNotification('check result (utf8) // ZM');
            StatLogger::getInstance()->info("turkish utf8", ['original' => $resultStr, 'encoded' => utf8_encode($resultStr)]);
        }

        if (
            isset($this->AccountFields["Login"]) && (
                strstr($this->AccountFields["Login"], '410624537')
                || strstr($this->AccountFields["Login"], '410 624 537')
            )
        ) {
            $this->sendNotification('check parse bad acc');

            return [];
        }

        return $result;
    }
}
