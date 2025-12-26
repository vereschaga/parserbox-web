<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSpirit extends TAccountChecker
{
    use PriceTools;
    use ProxyList;
    use SeleniumCheckerHelper;

    public $airCodes = [];

    private $firstName;
    private $lastName;
    private $providerBug = false;

    private $newApiURL = false;

    private $headers = [
        'Accept'                    => 'application/json, text/plain, */*',
        'Content-Type'              => 'application/json',
        'Origin'                    => 'https://www.spirit.com',
        'Ocp-Apim-Subscription-Key' => '3b6a6994753b4efc86376552e52b8432',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setDefaultHeader("Origin", "https://www.spirit.com");
        //$this->http->setRandomUserAgent();
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['headers'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $result = $this->loginSuccessful();
        $this->http->RetryCount = 2;

        return $result;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        // AccountID: 5809291
        if (strstr($this->AccountFields['Login'], '@@')) {
            throw new CheckException("Email address is invalid", ACCOUNT_INVALID_PASSWORD);
        }
        // AccountID: 6359179
        if (
            !is_numeric($this->AccountFields['Login'])
            && filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new CheckException("Email address is invalid", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.spirit.com/account-login");

        // request has been blocked
        if ($this->http->Response['code'] == 403 || empty($this->http->Response['body'])) {
            return $this->selenium();
            $proxy = $this->http->getLiveProxy("https://www.spirit.com/account-login");

            if (!$proxy) {
                $proxy = $this->proxyReCaptcha();
            }
            $this->http->SetProxy($proxy, false);
            $this->http->GetURL("https://www.spirit.com/account-login");

            if ($this->http->Response['code'] == 403) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException();
            }
        }// if ($this->http->Response['code'] == 403)
        $this->http->RetryCount = 2;

        if (
            $this->http->Response['code'] != 200
            || $this->http->FindSingleNode('//strong[
                contains(text(), "We\'re currently performing a system upgrade")
                or contains(text(), "We are currently performing a maintenance upgrade to improve your experience.")
            ]')
        ) {
//        if (!$this->http->ParseForm("SkySales"))
            return $this->checkErrors();
        }

        $this->sendSensorData();

//        $this->selenium();

        if (!$this->getToken()) {
            if ($this->http->Response['code'] == 500 && $this->http->FindPreg("/\"type\":\"Error\",\"rawMessage\":\"An unknown session failure has occured\.\",/")) {
                throw new CheckException("An error has occurred. Please try again. If the error persists, please call our reservations department at 801-401-2222. Feel free to leave any comments regarding your website experience using the feedback button on your right hand side.", ACCOUNT_PROVIDER_ERROR);
            }

            // retry on provider error
            if ($this->http->Response['code'] == 500 && $this->http->FindPreg("/\{ \"statusCode\": 500, \"message\": \"Internal server error\",/")) {
                throw new CheckRetryNeededException();
            }

            if ($this->http->Response['code'] == 403) {
                $this->DebugInfo = 'getting token: 403';

                $this->http->removeCookies();
                $this->selenium();

                return true;

                throw new CheckRetryNeededException(2, 0);
            }

            return $this->checkErrors();
        }

        $data = [
            "credentials" =>
                [
                    "username"        => "",
                    "password"        => substr($this->AccountFields['Pass'], 0, 16), // AccountID: 3455224
                    "domain"          => "WWW",
                    "applicationName" => "dotRezWeb",
                ],
        ];

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            $data["credentials"]["alternateIdentifier"] = $this->AccountFields['Login'];
        } else {
            $data["credentials"]["username"] = $this->AccountFields['Login'];
        }

        $this->http->RetryCount = 0;
        $postURL = "https://www.spirit.com/api/nk-token/api/nk/token";
        $this->http->PutURL($postURL, json_encode($data), $this->headers);

        if ($this->http->Response['code'] == 403) {
            return $this->selenium();
            /*
            sleep(3);
            $this->http->PutURL("https://api.spirit.com/nk-token/api/nk/token", json_encode($data), $this->headers);
            if (!in_array($this->http->Response['code'], [403, 204])) {
                $this->sendNotification("403, one more attempt // RR");
            }
            else {
                $this->sendNotification("403, retry // RR");
            $proxy = $this->proxyReCaptcha();
            $this->http->SetProxy($proxy, false);
            */
            $this->selenium();
            $this->http->PutURL($postURL, json_encode($data), $this->headers);
            /*
            }
            */
        }

        $this->http->RetryCount = 2;

        return true;
    }

    public function getToken()
    {
        $this->logger->notice(__METHOD__);
        /*
        $tokenURL = 'https://www.spirit.com/api/nk-token/api/v1/token';
        */
        $tokenURL = 'https://www.spirit.com/api/prod-token/api/v1/token';
        $data = '{"applicationName":"dotRezWeb"}';
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
            'Referer'      => 'https://www.spirit.com/account-login',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($tokenURL, $data, $headers + $this->headers);
        $this->http->RetryCount = 2;

        // 403 workaround
        /*
        $retry = 0;

        while ($this->http->Response['code'] == 403 && $retry < 1) {
            $this->logger->debug("[Retry]: {$retry}");
            $retry++;
            sleep(2 * $retry);

            if ($retry > 1) {
                $proxy = $this->proxyReCaptcha();
                $this->http->SetProxy($proxy, false);
            }// if ($this->http->Response['code'] == 403)

            $this->http->PostURL("https://api.spirit.com/dotrez2/api/nsk/v1/token", $data, $headers + $this->headers);

            if ($this->http->Response['code'] == 403) {
                $this->logger->notice("Try other api url");
                $this->http->PostURL($tokenURL, $data, $headers);

                if ($this->http->Response['code'] == 201) {
                    $this->newApiURL = true;
                }
            }
        }
        */

        $response = $this->http->JsonLog();
        $token = $response->data->token ?? null;

        if (!isset($token)) {
            return false;
        }
        $this->headers["Authorization"] = "Bearer {$token}";

        $response->data->lastUsedTimeInMilliseconds = intval(date("UB"));
        $this->http->setCookie("token", $token);
        $this->http->setCookie("tokenData", json_encode($response->data));
        /*
        $this->http->GetURL("https://www.spirit.com/api/nk-token/api/v1/token", $this->$headers + $this->headers);
        $this->http->JsonLog();
        sleep(2);
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[RETRY -> Current URL]: " . $this->http->currentUrl());
        // provider error
        if (($this->http->Response['code'] == 500 && $this->http->currentUrl() == 'https://www.spirit.com/ErrorMessage.aspx')
            || ($this->http->Response['code'] == 500 && $this->http->currentUrl() == 'https://www.spirit.com/FreeSpiritProfile.aspx')
            || $this->http->FindPreg("/An error occurred while processing your request\./")
            /*|| $this->http->FindPreg("/Server Error in '\/' Application\./")*/
            || $this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]")
            || ($this->http->Response['code'] == 503 && $this->http->FindPreg("/The service is unavailable\./"))) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//strong[contains(text(), "We’ll be done with our scheduled maintenance")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL("https://www.spirit.com/");
        //# Site down for maintenance
        if ($this->http->FindSingleNode("//img[contains(@src, 'maintenance/maintenance')]/@src")
            || $this->http->FindSingleNode("//img[contains(@src, 'images/spiritway/still-up-air.png')]/@src")
            || $this->http->FindSingleNode("//p[contains(text(), 'We are currently upgrading our systems')]")) {
            throw new CheckException("The website is temporarily down for maintenance. Please try to access it later.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        // Sorry, we are currently performing a system upgrade
        if ($message = $this->http->FindSingleNode('//strong[contains(text(), "we are currently performing a system upgrade")]')) {
            throw new CheckException("Sorry, " . $message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//strong[
                contains(text(), "We are currently performing a maintenance upgrade to improve your experience.")
                or contains(text(), "We’ll be done with our scheduled maintenance")
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // The service is unavailable. Server is too busy
        if ($message = $this->http->FindPreg('/(The service is unavailable\.)<html><body><h1>Server is too busy<\/h1><\/body><\/html>/')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (
            $this->http->Response['code'] == 204
            || $this->http->FindNodes('
                //p[contains(text(), "My Points")]
                | //div[@class = "sub-header-user-points" and normalize-space(text()) = "Points"]')
        ) {
            $this->State['headers'] = $this->headers;

            if ($this->loginSuccessful()) {
                return true;
            }

            if ($this->http->FindPreg("/blockScript/")) {
                throw new CheckRetryNeededException(3, 0);
            }

            return false;
        }

        $message =
            $response->errors[0]->rawMessage
            ?? $this->http->FindSingleNode('//div[contains(@class, "alert-danger")]')
            ?? $this->http->FindSingleNode('//div[contains(@class, "s-error-text")]')
            ?? $this->http->FindSingleNode('//div[@role = "alertdialog" and @style = ""]')
            ?? $this->http->FindSingleNode('//div[@role = "alert" and @style = ""]')
            ?? null
        ;

        if ($message) {
            $this->logger->error("[Error]: " . $message);
            $message = preg_replace("/\s*Feedback ×$/", "", $message);
            // Invalid email address or incorrect password. Please correct and re-try or select Sign Up.
            if (
                stristr($message, "No agent found for requested agent name WWW/{$this->AccountFields['Login']}")
                || (stristr($message, "The agent (WWW/") && stristr($message, ") was not authenticated."))
                || (stristr($message, "No user with the alternate identifier '{$this->AccountFields['Login']}' was found.  Login cannot be completed."))
                || (stristr($message, "The agent (WWW/") && stristr($message, ") is locked."))
                || (stristr($message, "Invalid email address or incorrect password."))
                || (stristr($message, "Only numbers are allowed."))
//                || (stristr($message, "The HTTP status code of the response was not expected (400).") && $this->http->FindPreg("/^([\d\-]+)$/", false, $this->AccountFields['Login']))
                || stristr($message, "The HTTP status code of the response was not expected (400).")
                || stristr($message, "Member account is inactive")
            ) {
                throw new CheckException("Invalid email address or incorrect password. Please correct and re-try or select Sign Up.", ACCOUNT_INVALID_PASSWORD);
            }
            // Please re-type in your temporary password.
            if (
                stristr($message, "The agent (WWW/") && stristr($message, ") must reset their password.")
            ) {
                $this->throwProfileUpdateMessageException();
            }

            if (
                stristr($message, "Multiple services match the specified type. More specific retrieval criteria is required.")
                || $message == 'errorMessages.defaultErrorMessage'
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException();
            }

            if (
                strstr($message, 'An error has occurred. Please try again. If the error persists, please call our reservations department at 855-728-3555. Feel free to leave any comments regarding your website experience using the feedback button on your right hand side.')
                || strstr($message, 'An error has occurred. Please try your request again. If the error persists, chat with us here for additional assistance.')
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(2, 10, $message);
            }

            if (
                // The HTTP status code of the response was not expected (500). Status: 500 Response: {"ClassName":"System.AggregateException","Message":"One or more errors occurred.","Data":null,"InnerException":{"ClassName":"System.Net.Http.HttpRequestException","Message":"An error occurred while sending the request.","Data":null,"InnerException":{"ClassName":"System.Net.WebException","Message":"The underlying connection was closed: An unexpected error occurred on a receive.","Data":null,"InnerException":{"ClassName":"System.IO.IOException","Message":"Unable to read data from the transport connection: An
                stristr($message, "The HTTP status code of the response was not expected (500).")
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->Response['code'] == 500 && empty($this->http->Response['body'])) {
            throw new CheckException("Invalid email address or incorrect password. Please correct and re-try or select Sign Up.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $name = $response->data->person->name;
        $name = beautifulName(Html::cleanXMLValue($name->first . " " . $name->last));
        $this->SetProperty("Name", beautifulName($name));

        $mainProgram = $response->data->person->programs[0] ?? null;

        if (count($response->data->person->programs) > 1) {
            $this->logger->error("multiple programs were found");
            $this->logger->debug(var_export($response->data->person->programs, true), ["pre" => true]);

            $countOfPrograms = 0;

            foreach ($response->data->person->programs as $program) {
                if ($program->programCode == 'FS') {
                    $this->logger->notice("skip program code 'FS'");

                    continue;
                }

                if ($program->programCode == 'NK') {
                    $mainProgram = $program;
                }
                $countOfPrograms++;
            }

            if ($countOfPrograms != 1) {
                return;
            }
        }
        $number = $mainProgram->programNumber ?? null;

        if (!$number) {
            $this->logger->error("programNumber not found");

            return;
        }
        // Balance - Your Current Miles
        $this->SetBalance($mainProgram->pointBalance ?? null);
        // Free Spirit Account Number
        $this->SetProperty("Number", $number);

        /*
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.spirit.com/nk-account/api/Account/accountdetail", $this->headers);
        $this->http->RetryCount = 1;

        // it helps
        if (
            $this->http->Response['code'] == 500
            || $this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)
        ) {
            $this->http->JsonLog();
            sleep(5);
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.spirit.com/nk-account/api/Account/accountdetail", $this->headers);
            $this->http->RetryCount = 1;

            // it helps also
            if (
                $this->http->Response['code'] == 500
                || $this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)
            ) {
                throw new CheckRetryNeededException();
            }
        }

        $response = $this->http->JsonLog();
        */
        // Mileage Earning Tier
        $this->SetProperty("Status", $response->data->tierStatus ?? null);
        // Status Expiration - Free Spirit Silver  Valid through
        $tierEndDate = strtotime($response->data->tierEndDate);

        if ($tierEndDate >= strtotime('now')) {
            $this->SetProperty("StatusExpiration", date("F d, Y", $tierEndDate));
        }

        // Spirit $9 Fare Club    // refs #5997
        $this->logger->info('Savers$ Club Membership', ['Header' => 3]);
        // Date Joined
        $joined = $response->data->clubMembership->subscriptionStartDate ?? null;
        // Renewal Date
        $exp = $response->data->clubMembership->subscriptionEndDate ?? null;
        // Days left in membership
        $day = $response->data->clubMembership->daysLeftInMembership ?? null;

        if (strtotime($exp) && isset($day) && $day > 0) {// bug fix (AccountID: 4143643)
            $this->SetProperty("CombineSubAccounts", false);
            $this->AddSubAccount([
                'Code'                 => 'spiritSaversClubMembership',
                'DisplayName'          => 'Savers$ Club Membership',
                'Balance'              => null,
                'DateJoined'           => date("F d, Y", strtotime($joined)),
                // Days left in membership
                // refs#20162 'DaysLeftInMembership' => $day,
                'ExpirationDate'       => strtotime($exp),
            ], true);
        }

        // Expiration Date   // refs #5780
        if ($this->Balance > 0) {
            $this->getHistory(null, true);
        }// if ($this->Balance > 0)

        $this->SetProperty("StatusQualifyingPoints", $response->data->clubMembership->lifetimeAccumulatedQualifyingPoints ?? 0);
        /*$data = '{"operationName":null,"variables":{},"query":"{\n  memberTQPInfo(freeSpiritNumber: \"' . $number . '\") {\n    creditCardTQP\n    extrasTQP\n    fareTQP\n    totalTQP\n    spiritTQPYTDBalance\n    spiritTQPMonthBalance\n    overrideTQP\n    __typename\n  }\n}\n"}';
        $this->http->PostURL("https://api.spirit.com/prod-account/graphql", $data, $this->headers);
        $response = $this->http->JsonLog();

        if (isset($response->data->memberTQPInfo->totalTQP)) {
            $this->SetProperty("StatusQualifyingPoints", $response->data->memberTQPInfo->totalTQP);
        }*/
    }

    public function getHistory($startDate, $expDate = false)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        if ($expDate == true) {
            $this->logger->info('Expiration date', ['Header' => 3]);
        }
        $number = $this->Properties['Number'];
        $transactionPeriodStartDate = date("Y-n-j", strtotime("-5 years"));
        $transactionPeriodEndDate = date("Y-n-j");
        $data = '{"operationName":null,"variables":{},"query":"{\n  mileageStatementInfo(\n    statementRequest: {accountNumber: \"' . $number . '\", transactionPeriodStartDate: \"' . $transactionPeriodStartDate . '\", transactionPeriodEndDate: \"' . $transactionPeriodEndDate . '\", transactionType: \"ALL\", lastID: 0, pageSize: 1000}\n  ) {\n    customerPointsBreakdown {\n      balance\n      category\n      credit\n      dateEarned\n      debit\n      description\n      ccQualifyingPoints\n      nkCcQualifyingPoints\n      nkQualifyingPoints\n      referenceNumber\n      __typename\n    }\n    startDate\n    startingBalance\n    startingBalanceSpecified\n    __typename\n  }\n}\n"}';
        $this->http->PostURL("https://api.spirit.com/prod-account/graphql", $data, $this->headers);
        $response = $this->http->JsonLog(null, 0);
        $transactions = $response->data->mileageStatementInfo->customerPointsBreakdown ?? [];
        $this->logger->debug("Total " . count($transactions) . " transactions were found");
        $stop = false;

        $startIndex = sizeof($result);
        $result = $this->ParsePageHistory($transactions, $startIndex, $startDate, $stop, $expDate);

        return $result;
    }

    public function ParseItineraries()
    {
        $this->http->RetryCount = 0;
        $data = '{"operationName":null,"variables":{},"query":"{\n  findUserBookings(\n    searchRequest: {includeDistance: true, returnCount: 100, includeAccrualEstimate: true, searchByCustomerNumber: true}\n  ) {\n    currentBookings {\n      allowedToModifyGdsBooking\n      bookingKey\n      bookingStatus\n      channelType\n      destination\n      distance\n      editable\n      expiredDate\n      flightDate\n      flightNumber\n      name {\n        first\n        last\n        __typename\n      }\n      origin\n      passengerId\n      recordLocator\n      sourceAgentCode\n      sourceDomainCode\n      sourceOrganizationCode\n      systemCode\n      qualifyingPoints\n      redeemablePoints\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->PostURL('https://api.spirit.com/prod-user/graphql', $data, $this->headers);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();

        $completedBookings = $response->data->completedBookings ?? []; // TODO
        $currentBookings = $response->data->findUserBookings->currentBookings ?? [];

        if ($this->ParsePastIts) {
            $this->parsePastItineraries($completedBookings);
        }

        // no itineraries
        if (empty($currentBookings) && $this->http->FindPreg("/\"currentBookings\":\[\],/")) {
            if ($this->ParsePastIts && !empty($completedBookings)) {
                return [];
            }

            return $this->noItinerariesArr();
        }

        $this->logger->debug("Total " . count($currentBookings) . " future itineraries were found");

        if (count($currentBookings) > 0) {
            $this->sendNotification('check it // MI');
        }

        foreach ($currentBookings as $currentBooking) {
            $confNo = $currentBooking->recordLocator;
            $lastName = $currentBooking->name->last;
            $this->ParseItinerary($confNo, $lastName);
        }

        return [];
    }

    public function ParseItinerary($confNo, $lastName)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Parse Itinerary #' . $confNo, ['Header' => 3]);
        $this->http->RetryCount = 0;
        $data = json_encode([
            'lastName'      => $lastName,
            'recordLocator' => $confNo,
        ]);
        $this->http->PostURL("https://www.spirit.com/api/prod-booking/api/booking/retrieve", $data, $this->headers);

        /* if ($this->http->Response['code'] == 500
             && !$this->http->FindPreg("/Last name validation failed/")
             && !$this->http->FindPreg("/The identifier 'RecordLocator' with value/")) {
             $sleep = rand(2, 4);
             sleep($sleep);
             $this->logger->debug("retry");
             $this->http->GetURL("https://www.spirit.com/api/nk-booking/api/booking/retrieve?recordLocator={$confNo}&lastName={$lastName}", $this->headers);

             if ($this->http->Response['code'] != 500) {
                 $this->sendNotification("retry - ok // ZM");
             } else {
                 $this->sendNotification("retry - fail // ZM");
             }
         }*/
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3);

        $message = $response->errors[0]->rawMessage ?? null;

        if ($message) {
            $this->logger->error($message);

            if (
                strstr($message, "Last name validation failed.")
                || strstr($message, "The identifier 'RecordLocator' with value '{$confNo}' is invalid.")
            ) {
                return "We are unable to locate the itinerary. Please verify the information is correct and try again. The combination of the customer last name and the Confirmation Code is invalid. Please try again.";
            }

            return null;
        }

        $data = $response->data ?? null;

        $f = $this->itinerariesMaster->add()->flight();
        // RecordLocator
        $f->general()
            ->confirmation($confNo, 'Confirmation Code', true);

        if (!$data) {
            $this->logger->error("wrong response");

            return null;
        }
        $f->general()
            ->date2($data->info->bookedDate);

        $isCancelled = $data->isCancelled ?? null;

        if ($isCancelled == true) {
            $f->general()->cancelled();
        }

        switch ($data->info->status) {
            case '2':
                $status = 'Confirmed';
                $f->general()->status($status);

                break;

            case '3':
            case '4':
                $status = 'Cancelled';
                $f->general()->status($status);

                break;

            default:
                $this->sendNotification("Unknown status {$data->info->status} // RR");
        }

        $f->price()->currency($data->currencyCode);
        $f->price()->total($data->breakdown->totalAmount);

        $breakdown = $data->priceDisplay->flightPrice->breakdown ?? [];

        foreach ($breakdown as $price) {
            if ($price->display == 'Flight Price' && $price->price > 0) {
                //$f->price()->cost($price->price);

                continue;
            }
            $f->price()->fee($price->display, round($price->price, 2));
        }

        if ($data->priceDisplay->bags->total > 0) {
            $f->price()->fee("Bags", $data->priceDisplay->bags->total);
        }

        if ($data->priceDisplay->seats->total > 0) {
            $f->price()->fee("Seats", $data->priceDisplay->seats->total);
        }

        $passengers = $data->passengers ?? [];

        foreach ($passengers as $key => $passenger) {
            $frequentFlyer = $passenger->accountNumber ?? null;

            if ($frequentFlyer) {
                $f->program()->account($frequentFlyer, false);
            }
            $f->general()->traveller(beautifulName(Html::cleanXMLValue($passenger->name->first . " " . ($passenger->name->middle ?? '') . " " . $passenger->name->last)), true);
        }

        // Air Trip Segments

        $journeys = $data->journeys ?? [];
        $this->logger->debug("Total " . count($journeys) . " journeys were found");

        foreach ($journeys as $journey) {
            $segments = $journey->segments ?? [];
            $this->logger->debug("Total " . count($segments) . " segments were found");

            foreach ($segments as $segment) {
                $legs = $segment->legs ?? [];
                $this->logger->debug("Total " . count($legs) . " legs were found");

                foreach ($legs as $leg) {
                    $s = $f->addSegment();

                    $s->airline()
                        ->name($segment->identifier->carrierCode)
                        ->number($segment->identifier->identifier);

                    $s->extra()
                        ->cabin($segment->cabinOfService)
                        ->aircraft($leg->legInfo->equipmentType, true, true)
                        ->duration(preg_replace("/:\d+$/", "", $leg->travelTime))
                        ->stops($journey->stops);

                    if (!empty($leg->distanceInMiles)) {
                        $s->extra()
                            ->miles($leg->distanceInMiles);
                    }

                    $s->departure()
                        ->code($leg->designator->origin)
                        ->terminal($leg->legInfo->departureTerminal ?? null, true, true)
                        ->date2($leg->designator->departure);

                    $s->arrival()
                        ->code($leg->designator->destination)
                        ->terminal($leg->legInfo->arrivalTerminal ?? null, true, true)
                        ->date2($leg->designator->arrival);
                }// foreach ($legs as $leg)
            }// foreach ($journey->segments as $segment)
        }// foreach ($journeys as $journey)

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        return null;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://www.spirit.com/home-check-in';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        //$this->sendSensorData();
        //$this->sendSensorDataRetrieve();
        // request has been blocked
        if ($this->http->Response['code'] == 403) {
            $proxy = $this->http->getLiveProxy($this->ConfirmationNumberURL($arFields));

            if (!$proxy) {
                $proxy = $this->proxyReCaptcha();
            }
            $this->http->SetProxy($proxy);
            $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        } // if ($this->http->Response['code'] == 403)

        $this->seleniumRetrieve($arFields);

        /*
        if (/*$this->http->Response['code'] > 201 || *
        / !$this->getToken()) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }
        */

        $error = $this->ParseItinerary($arFields['ConfNo'], $arFields['LastName']);

        if (is_string($error)) {
            return $error;
        }

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
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
            "Date"        => "PostingDate",
            "Transaction" => "Description",
            "Points"      => "Miles",
            "Balance"     => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $result = $this->getHistory($startDate);

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($transactions, $startIndex, $startDate, &$stop, $expDate)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        foreach ($transactions as $transaction) {
            $dateStr = $transaction->dateEarned;
            $credit = $transaction->credit;
            $postDate = strtotime($dateStr);

            if ($expDate === true) {
                if ((!empty($credit) || !empty($transaction->debit)) && $postDate) {
                    $stop = true;
                    // Last Activity
                    $this->SetProperty("LastActivity", date("m/d/Y", $postDate));
                    $this->SetExpirationDate(strtotime("+12 month", $postDate));

                    return [];
                }// if (!empty($credit) && strtotime($date))

                continue;
            }// if ($expDate === true)

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $stop = true;

                continue;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Transaction'] = $transaction->description;
            $debit = intval(preg_replace('/,/', '', $transaction->debit));

            if ($debit < 0) {
                $debit *= -1;
            }
            $credit = intval(preg_replace('/,/', '', $transaction->credit));
            $result[$startIndex]['Points'] = $credit - $debit;
            $result[$startIndex]['Balance'] = $transaction->balance;
            $startIndex++;
        }// foreach ($transactions as $transaction)

        return $result;
    }

    public function seleniumRetrieve($arFields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

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
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($chosenResolution);

            /*
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumRequest->setOs("mac");
            $selenium->http->setUserAgent(null);
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
            */

            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->seleniumOptions->addAntiCaptchaExtension = true;
            $selenium->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();

            /*
            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
                $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
            }

            */
            $selenium->setProxyGoProxies();

            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();
            $this->http->removeCookies();

            try {
                $selenium->http->GetURL('https://www.spirit.com/');
                sleep(5);
                $selenium->http->GetURL('https://www.spirit.com/home-check-in');
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            }

            $handler = $selenium->waitForElement(WebDriverBy::id("onetrust-accept-btn-handler"), 7);

            if ($handler) {
                $this->savePageToLogs($selenium);
                $handler->click();
                sleep(5);
            }

            $login = $selenium->waitForElement(WebDriverBy::id("home_checkin-lastName"), 0);
            $recordLocator = $selenium->waitForElement(WebDriverBy::id("home_checkin-recordLocator"), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-qa="home-page.manage-travel-home-form-find-trip-continue-link"]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$recordLocator || !$login || !$btn) {
                $this->sendNotification("failed to retrieve itinerary by conf #");

                return null;
            }

            $this->logger->debug("scroll to login");
            $selenium->driver->executeScript("document.querySelector('.trips-container').scrollIntoView()");
            $this->savePageToLogs($selenium);

            try {
                $this->logger->debug("set ConfNo");
                $recordLocator->sendKeys($arFields['ConfNo']);
                $this->logger->debug("set LastName");
                $login->sendKeys($arFields['LastName']);
                $this->logger->debug("click Check-In");
//                $btn->click();
                $selenium->driver->executeScript("document.querySelector('button[data-qa=\"home-page.manage-travel-home-form-find-trip-continue-link\"]').click();");
            } catch (
                WebDriverException
                | Facebook\WebDriver\Exception\UnknownErrorException
                $e
            ) {
                $this->logger->error("WebDriverException / UnknownErrorException: " . $e->getMessage());
            } catch (Exception $e) {
                $this->logger->error("Exception: {$e->getMessage()}");
            }

            $selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Your Trip Summary")]'), 10);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] == 'token') {
                    $selenium->markProxySuccessful();
                    $this->headers['Authorization'] = "Bearer {$cookie['value']}";
                }
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return $currentUrl;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->State['headers']['Ocp-Apim-Subscription-Key'] = '3b6a6994753b4efc86376552e52b8432';
        $this->http->GetURL("https://api.spirit.com/prod-account/api/Account/accountdetail", $this->State['headers']);
        $response = $this->http->JsonLog();

        if (isset($response->data->person->name)) {
            $this->headers = $this->State['headers'];

            return true;
        }

        return false;
    }

    private function parsePastItineraries($pastIts)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Past Itineraries", ['Header' => 2]);
        $startTimer = $this->getTime();

        $this->logger->debug("Total " . count($pastIts) . " past reservations found");

        if (empty($pastIts) && $this->http->FindPreg("/\{\"completedBookings\":\[\]/")) {
            $this->logger->notice("No past reservations");
        }

        foreach ($pastIts as $pastIt) {
            $confNo = $pastIt->recordLocator ?? null;
            $this->logger->info('Parse Itinerary #' . $confNo, ['Header' => 3]);
            $date = $pastIt->flightDate ?? null;
            $origin = $pastIt->originCode ?? null;
            $destination = $pastIt->destinationCode ?? null;
            $traveledMiles = $pastIt->distance ?? null;

            $this->logger->debug("[Origin]: '{$origin}'");
            $this->logger->debug("[Destination]: '{$destination}'");
            // only cancelled trips (may be)
            if ($origin === 'Origin:' && $destination === 'Destination:' && !$traveledMiles) {
                $this->logger->debug("Skip past itinerary with empty checkpoints");

                continue;
            }

            $date = $this->http->FindPreg("/(.+)T/", false, $date);

            $f = $this->itinerariesMaster->add()->flight();

            if ($confNo) {
                $f->general()->confirmation($confNo, 'Confirmation Code', true);
            } else {
                $f->general()->noConfirmation();
            }

            $f->general()->traveller(beautifulName(Html::cleanXMLValue(
                $pastIt->name->first . " " . ($pastIt->name->middle ?? "") . " " . $pastIt->name->last)), true);

            $s = $f->addSegment();
            $s->airline()->noName();

            $flightNumber = $pastIt->flightNumber ?? null;

            if ($flightNumber) {
                $s->airline()->number($flightNumber);
            } else {
                $s->airline()->noName();
            }

            $s->departure()
                ->code($origin)
                ->noDate()
                ->day2($date);

            $s->arrival()
                ->code($destination)
                ->noDate()
                ->day2($date);

            $s->extra()->miles($traveledMiles, false, true);

            $this->logger->debug('Parsed Itinerary:');
            $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
        }// for ($i = 0; $i < $pastIts->length; $i++)

        $this->getTime($startTimer);
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data URL not found");

            return $this->checkErrors();
        }

        $this->http->NormalizeURL($sensorDataUrl);

        $abck = [
            "2AE28F2766B908BE9888D56921F76037~0~YAAQXNcwFysBI0KAAQAA+vRVRgeoyKuOcrNs2v6I+FyOC/LRx2nxdR8x3BUwL3OLEUaolDn8h9GZA9csJy6HMD5BUMmQKkMDLjkABE5tRnQh8ekEVhO7u6A+OCGRaoYYFYWCJFHXSxZFLTPtAucsue3SugJ7j5stZ1H3AEkST4jV5GGuTN7EAGedVcmove/MYOzy4Bsip1poDEndNN7o0Zt18g/Pu+fJS/8IEaCTJvveVHvHwMwLe9fig71vqs9YjuVKCsANP3nUlvBJwTi2PKthMhM+vrgDAFjx8nLOinD0Of55fvfl0O0+M7rqOcGUNbjXTZtF0ko551JPis7rqq3UQpqHvy8RoVJqrWuOLh7UL924wjzo0csCBD1xge9eggawn0fp2WsjV7YEt8NT/SP6fE0+qyGfYg==",
            "B6AF61BE9D885EE67E0C3C76FB195027~0~YAAQXNcwF13CI0KAAQAAq/9tRgeGTS84gME9rVJPy8DYdbRMwXkNGgiw/VyqcJFoz6Uo+QmOmbEI3X7RdrnzwncGe7HUHw0a261xJM/iUUqgLaZMZeYmVvnXnJdmRMw65vLU9WKDquEOyFcE9khETbzsWM6XHqeXapH6kzdWzFvQyXvJe0WJ7j9kJ1aN4/yNYlwaKKRxkC2jdCGqYOj7FhRgrCcNbymhJKvbVunmR44HSQZKmBHc22VVoi2KmQ0UgAXJGn7mZq+kT2+7TgmFDL/QKqp4fCX/YjCtbYNJnCBBbSzVM6/DfnydzflP0lhiRDdAIAEq3HycvqIJ0ExFLKR8r2A/jAh6qiv1ED7nkhXWNY2dNOfWlT750t0F+N5dBu2ZaHNgYVPx3bd28TYIIrfz9ttPlwP0Yg==",
            "B6E6FAA01E05D17371FD7ACDCF96BE26~0~YAAQRkA2FzmjtUSAAQAAXSNzRgcGD5HWQaFxMwYWRmf/Wk5TBhliDpnHRURfxbpdL36RHb4XpkXwTUOJUaXPUd4E+6vMCxqJpcEBikPYKqJGluPda2G0p7PXd3IuiQgHKnQEbXMH/aXdXP27ndsPAgRX3l4Gc6N3pZDV5D9URc9QKu1nrQntrkSoDrV+t61cwQOlP5Y9sEv5RJpLphKJUyXpWRt9mX4/eHPQmL6ubs+rTn2dw/PUaDidSCy8KEkTjkaFRfuE+vWUzL2qkVAgmrzTqXOfdbm3zCShYN9iZJe9qEXiqE1rDIdu1T/GW61Ad2GK225uRpF//p0f3Lg+cYcmVbTgIQA785Gn2NINmH6es9JLYGuJBS1voVdJPR8ah7XMPF28cL0fOp+aSeeBCp6UcsjJXcjo",
            "69793D4B4FA0E8E167B6C301D0167262~0~YAAQXNcwF0RtJUKAAQAAKi+YRgeIsCvizXvYKt0/W+YwL9zu3xf4SqwNTi8Gfsz3zwXZ0FUQeIlN7SOLiaJqufTS6M//pq3ZbWKevMoWK8b9YIU+b7V1OKX9jgqexInaiOpwJwg+DlPWMeHYkHUfrlc0DExvqAoKdxilqLZyjHhAROjsxt08NSWPxc7KX+mGkmfU6IoSdrQw+j3UkMy+AlXYXRozxFn3GHcAuHg0eWLcwJyUyPmXykKWiMIjE421Ygrq3Pp0aAdHU+nBwq0QmUf4psHdD3Avyhx3fCjS9szJv4j0kOm66X44zcjL0Tm4UjEGqs9i3UQWFI7Dc833brET4KAE13k/fVxqsRx3RcVh6Gf+CEPoLKy/s1tWHe6fFxozvXVuiI9+1zBJoC7fT3Yg0jnmksQcAA==",
        ];
        $this->http->setCookie("_abck", $abck[array_rand($abck)], '.spirit.com');

        $sensorData = [
            // 0
            "7a74G7m23Vrp0o5c9236631.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:99.0) Gecko/20100101 Firefox/99.0,uaend,11059,20100101,en-US,Gecko,5,0,0,0,406089,1816170,1536,871,1536,960,1536,399,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6020,0.06155961430,825225908085,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.spirit.com/account-login-1,2,-94,-115,1,32,32,0,0,0,0,0,0,1650451816170,-999999,17656,0,0,2942,0,0,1,0,0,69793D4B4FA0E8E167B6C301D0167262~-1~YAAQXNcwF4BrJUKAAQAAQQGYRgd0AEqLLvPLJ5veWzqJ4wpeCxvYgloaY6p2KUlr8jx+3w6ag0Csjrf/ZXkDfZuPlXK/bsHWuIraymFIxryLNITKlFTdQ6x0gCYJ+oz19xtxd3LB+7dH36DGl8/DgCzW4k+RV2vQKb7YiPL5by1pWWOvIK9/ThA7FKvYbBRRjD1D+6P5E9R3IEsAwXTvvqdnsiob7ol30x5IgPs/9BcemWz4vDDkndqvY2deo7RGIqeyoLpIbVXdoSbQnSwZ+cquD33YmZU/plUMz8b9HIUowu2jOROMGWK615mC+sie/tqe0ixAm+Q1ea9sKHupkdPKpGr32xjUylzrM5iRtz5rIxdXkJa/Sru1CzQ+VgOqdHPlcY7hnYX8C84=~-1~-1~1650455403,37619,-1,-1,26067385,PiZtE,60752,52,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,5448477-1,2,-94,-118,82913-1,2,-94,-129,-1,2,-94,-121,;7;-1;0",
            // 1
            "7a74G7m23Vrp0o5c9236631.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:99.0) Gecko/20100101 Firefox/99.0,uaend,11059,20100101,en-US,Gecko,5,0,0,0,406088,9051929,1536,871,1536,960,1536,399,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6020,0.502275567251,825224525964.5,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.spirit.com/account-login-1,2,-94,-115,1,32,32,0,0,0,0,0,0,1650449051929,-999999,17656,0,0,2942,0,0,1,0,0,B6AF61BE9D885EE67E0C3C76FB195027~-1~YAAQXNcwF6XBI0KAAQAAiuZtRgfEF/dYHONOtbqsqZv8wPFSFPuXl3NQwyQFwB/PwccS8oWHDRM4CPMgKPUP3cluchauooGOi1ODiOUsSOkfyP1JdR/4OO8oigyv6dL6+4ZzRyex2wzFeTm613sexi04pUhnHS32m5TTxRsDGl5Mqmy/0blNlwVZpGogReafgfDhSNpguXFf1i4MBcu5i+As4N1K0MBYmaA9/PEx0KwOETmgFIUVDMb7caqTdE5p8vEiRl4fjpLn8s8+wwb+rDqTxuclJfIiudej18lut8jUWP7N5sYSc+BYkBmrjXhdvDpRnLWgCoCa7p7pcbljjVzZ3i8k/XvQfhckEJshETTJtqusUQufQ8pHmUDh~-1~-1~1650452551,36535,-1,-1,26067385,PiZtE,96176,83,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,27155862-1,2,-94,-118,82028-1,2,-94,-129,-1,2,-94,-121,;2;-1;0",
            // 2
            "7a74G7m23Vrp0o5c9236631.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,406088,9391471,1536,871,1536,960,1536,433,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,9023,0.534783547267,825224695735,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.spirit.com/account-login-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1650449391470,-999999,17656,0,0,2942,0,0,3,0,0,B6E6FAA01E05D17371FD7ACDCF96BE26~-1~YAAQRkA2FwqjtUSAAQAAOhxzRgfIuG9YI4Y7x+EROgIRbwTGj6cEe+ZwKTToO5XMI5iA84X8VkJZPk9V3p5bOGoPRHB/+mx75e8MaFjpUCEN9q08JRpyX4RVezQ/tt+Xri+/KhbIDJOPqyR+AC8oGcotpXqd1TSDeW006BcH6Y7Y5ZeVxvVrzDtnEJhbrH3eIAjn1O36Q0AdirLwgq11BPttIq/WYgYpN+ytP7QT7P0N67uGx9hVPlBIMqdKckH+j067IjLXdBS8XE5OwzqeBPzdknbgIlnlTpNnLxoxuOHaYlXSb13EPnXVNW2PLy8uPoZ7+eUXszozIzeOTxE4+7V/5ID1YbX1lZTyD+4qGacYIWWvRuRpA6kajiqp9g+G0qHf+7Pxf/ukGQ==~-1~-1~1650452928,37015,-1,-1,30261693,PiZtE,46724,85,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,140872011-1,2,-94,-118,85198-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 3
            "7a74G7m23Vrp0o5c9236621.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,406088,8470502,1536,871,1536,960,1536,433,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,9023,0.586318420293,825224235250.5,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.spirit.com/account-login-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1650448470501,-999999,17656,0,0,2942,0,0,2,0,0,AF75288C4DF8619C0DA9C70B2ED88BF0~-1~YAAQRkA2F4httUSAAQAAmutkRgcfWsF/lKAGd6PV7y9txDo5qpJhcXOgZ7vSGT36mm12MKa4DWM/Ag1nOgLO2PPUgBmvk1IUAsbYbKADUNHtTufVQhdwQGOg69OiGqnM8HQ6zOYcx+9wfMavmu2Y3+Abqa/3YGO3hUv86dctCt+k7tzQSW0G4CU52bUSBQJ8+6Q7WmzMxTTsnzCmu/zD0A1YPVmWVZZfUhXOrJgfedZ7xeSt236Fb5dyeEugvpGHZHmco6RSg5Wgc7kDtw2V9DYi1mlbij3pL9SOuKEO15TkbF65qcoIuXz1iz+2RyKlGfqsTnGyOVe+2L5ck5Z8MGccEfymzHrXGjZy3eIQTduNlDVicbWoW5hrpnVRm76UuzHSuSPJ71NcnA==~-1~-1~1650451979,37518,-1,-1,30261693,PiZtE,77885,72,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,25411491-1,2,-94,-118,85771-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
        ];

        $secondSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9236631.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:99.0) Gecko/20100101 Firefox/99.0,uaend,11059,20100101,en-US,Gecko,5,0,0,0,406089,1816170,1536,871,1536,960,1536,399,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6020,0.320594959160,825225908085,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.spirit.com/account-login-1,2,-94,-115,1,32,32,0,0,0,0,519,0,1650451816170,4,17656,0,0,2942,0,0,520,0,0,69793D4B4FA0E8E167B6C301D0167262~-1~YAAQXNcwF4BrJUKAAQAAQQGYRgd0AEqLLvPLJ5veWzqJ4wpeCxvYgloaY6p2KUlr8jx+3w6ag0Csjrf/ZXkDfZuPlXK/bsHWuIraymFIxryLNITKlFTdQ6x0gCYJ+oz19xtxd3LB+7dH36DGl8/DgCzW4k+RV2vQKb7YiPL5by1pWWOvIK9/ThA7FKvYbBRRjD1D+6P5E9R3IEsAwXTvvqdnsiob7ol30x5IgPs/9BcemWz4vDDkndqvY2deo7RGIqeyoLpIbVXdoSbQnSwZ+cquD33YmZU/plUMz8b9HIUowu2jOROMGWK615mC+sie/tqe0ixAm+Q1ea9sKHupkdPKpGr32xjUylzrM5iRtz5rIxdXkJa/Sru1CzQ+VgOqdHPlcY7hnYX8C84=~-1~-1~1650455403,37619,739,-870722622,26067385,PiZtE,50661,49,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,25610054;2077731666;dis;,7;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5326-1,2,-94,-116,5448477-1,2,-94,-118,84342-1,2,-94,-129,,,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,,,,0-1,2,-94,-121,;3;7;0",
            // 1
            "7a74G7m23Vrp0o5c9236631.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:99.0) Gecko/20100101 Firefox/99.0,uaend,11059,20100101,en-US,Gecko,5,0,0,0,406088,9051929,1536,871,1536,960,1536,399,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6020,0.14594233572,825224525964.5,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.spirit.com/account-login-1,2,-94,-115,1,32,32,0,0,0,0,519,0,1650449051929,5,17656,0,0,2942,0,0,520,0,0,B6AF61BE9D885EE67E0C3C76FB195027~-1~YAAQXNcwF6XBI0KAAQAAiuZtRgfEF/dYHONOtbqsqZv8wPFSFPuXl3NQwyQFwB/PwccS8oWHDRM4CPMgKPUP3cluchauooGOi1ODiOUsSOkfyP1JdR/4OO8oigyv6dL6+4ZzRyex2wzFeTm613sexi04pUhnHS32m5TTxRsDGl5Mqmy/0blNlwVZpGogReafgfDhSNpguXFf1i4MBcu5i+As4N1K0MBYmaA9/PEx0KwOETmgFIUVDMb7caqTdE5p8vEiRl4fjpLn8s8+wwb+rDqTxuclJfIiudej18lut8jUWP7N5sYSc+BYkBmrjXhdvDpRnLWgCoCa7p7pcbljjVzZ3i8k/XvQfhckEJshETTJtqusUQufQ8pHmUDh~-1~-1~1650452551,36535,840,-1960084075,26067385,PiZtE,63685,28,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,25610054;2077731666;dis;,7;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5326-1,2,-94,-116,27155862-1,2,-94,-118,83386-1,2,-94,-129,,,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,,,,0-1,2,-94,-121,;3;2;0",
            // 2
            "7a74G7m23Vrp0o5c9236631.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,406088,9391471,1536,871,1536,960,1536,433,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,9023,0.989373808494,825224695735,0,loc:-1,2,-94,-131,Mozilla/5.0 (macOS;12.3.1;x86;64;) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.spirit.com/account-login-1,2,-94,-115,1,32,32,0,0,0,0,462,0,1650449391470,11,17656,0,0,2942,0,0,474,0,0,B6E6FAA01E05D17371FD7ACDCF96BE26~-1~YAAQRkA2FyqjtUSAAQAAECFzRgdYbGpdTdxWe6lnGybIqEzjVL19G1d2p++WqQ+PR5wLJbOxTPihwy3hK8AlyT7YLBYN9yusORQh+l9alHJzxzjjTQAkaIvCYEdSUF6qzZtg6V4hEIZ5Bn9kATptiCVGV48elPCULa4PfgUxeyr6SqCKKGGvmb+Ky1EJfdkLawrcbtV50SG7TDlWLN/I4cosso8TdovNKUJTT3KRQk4tafQWE2LpfY0mW4iCUrdIaxdWtkwPCd1JWDNlCk8HICIG4ZmUxCYOIgta2uHTE/IdZdKTUVNZaYSp3ZoSeW4ERNds3CEQWMQ3+gva7DRhM9I+OK8SYjx46EnD7il+c16+X+coo/f8JvQYxRxV/Pq8LZR98ZJyGI6cLg==~-1~||1-tkfXDPBFvl-1-10-1000-2||~1650452884,39182,977,-163410228,30261693,PiZtE,35426,69,0,-1-1,2,-94,-106,8,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.34687a0fdc09e,0.20815047b2a48,0.a0bec694e0647,0.c81927531e9bb,0.48cca85111214,0.383cdc2eab2df,0.ae98db2fc3883,0.1978cd0bfb585,0.10b81c8312d42,0.7110a54179d0e;1,0,0,2,0,1,1,1,1,1;0,0,1,8,2,2,17,9,2,19;B6E6FAA01E05D17371FD7ACDCF96BE26,1650449391470,tkfXDPBFvl,B6E6FAA01E05D17371FD7ACDCF96BE261650449391470tkfXDPBFvl,1,1,0.34687a0fdc09e,B6E6FAA01E05D17371FD7ACDCF96BE261650449391470tkfXDPBFvl10.34687a0fdc09e,128,254,104,157,42,2,19,95,71,206,186,236,132,74,28,106,177,246,224,204,43,119,68,244,7,138,89,204,208,53,147,124,408,0,1650449391932;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5475-1,2,-94,-116,140872011-1,2,-94,-118,128487-1,2,-94,-129,,,0,,,,0-1,2,-94,-121,;14;7;0",
            // 3
            "7a74G7m23Vrp0o5c9236621.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,406088,8470502,1536,871,1536,960,1536,433,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,9023,0.767659093383,825224235250.5,0,loc:-1,2,-94,-131,Mozilla/5.0 (macOS;12.3.1;x86;64;) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.spirit.com/account-login-1,2,-94,-115,1,32,32,0,0,0,0,463,0,1650448470501,10,17656,0,0,2942,0,0,474,0,0,AF75288C4DF8619C0DA9C70B2ED88BF0~-1~YAAQRkA2FzNutUSAAQAAExRlRge9emHMvsUI2bP3YbBSBh+Ia6v4b5FfVpc4h/Lij89KNPnm6jO3IaKzeTnRiqABHer7jt+A5zkPfS6fjMsY1VEiiCF4Sl9+0wocAj2OkZXQpiV93u7k9GudDml7V6V/sD4+7iXIJOShWfyvW72+KJQIdKoTUaa0Xj7gxoK0BphO27Wws8rWEIQuVjz2VZkjWw4mCzM8LEZT1axQnWTunHMesOdZRM07G4nt2aJcY0+eUI/bg3gQhY9MWAjPQpmOZnLfbAUd2eUb6PoZpGpEoGTDVC/QI5AJOTy2TIT56NtsnMAmUPsvuardG7DvR1TEOUvReulYUoNJGA0GyYYJAraDbP+T4okU07de1UoaXn8gcGEa0SB6sg==~-1~||1-bHmuXEsTCy-1-10-1000-2||~1650452017,38988,473,-1458215340,30261693,PiZtE,22001,15,0,-1-1,2,-94,-106,8,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.8914ee2fe6c13,0.732ec1016d8d4,0.a569adbe77b1,0.003bd959cefc6,0.70b9daea9f075,0.780dc8e699d43,0.5202f8628c48d,0.7d9a1e96ce3e8,0.da6bf0535042f,0.bb4b5f4653585;1,0,2,1,0,1,1,1,1,3;0,0,9,5,0,3,1,5,4,24;AF75288C4DF8619C0DA9C70B2ED88BF0,1650448470501,bHmuXEsTCy,AF75288C4DF8619C0DA9C70B2ED88BF01650448470501bHmuXEsTCy,1,1,0.8914ee2fe6c13,AF75288C4DF8619C0DA9C70B2ED88BF01650448470501bHmuXEsTCy10.8914ee2fe6c13,195,9,121,157,77,172,75,20,161,18,250,38,10,222,251,159,72,247,61,172,148,174,205,46,107,96,171,92,248,53,207,231,408,0,1650448470964;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5475-1,2,-94,-116,25411491-1,2,-94,-118,128598-1,2,-94,-129,,,0,,,,0-1,2,-94,-121,;13;7;0",
        ];

        if (count($sensorData) !== count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $this->http->RetryCount = 0;
        $referer = $this->http->currentUrl();

        $data = [
            "sensor_data" => $sensorData[$key],
        ];
        $headers = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        $data = [
            "sensor_data" => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        $this->http->RetryCount = 2;
        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);
        sleep(1);

        return true;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $retry = false;

        try {
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
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($chosenResolution);

            if ($this->attempt < 50) {
                $selenium->useFirefoxPlaywright();
                $selenium->setProxyGoProxies();
//                $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->seleniumOptions->userAgent = null;
            } else {
                $this->useChromePuppeteer();
                $request = FingerprintRequest::chrome();
                $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                $selenium->setProxyGoProxies();

                if ($fingerprint !== null) {
                    $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                    $this->seleniumOptions->setResolution([
                        $fingerprint->getScreenWidth(),
                        $fingerprint->getScreenHeight()
                    ]);
                    $this->http->setUserAgent($fingerprint->getUseragent());
                }
            }

//            $selenium->disableImages();
//            $selenium->useCache();
//            $selenium->keepCookies(false);
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

//            $selenium->http->GetURL("https://www.spirit.com");
            $selenium->http->GetURL("https://www.spirit.com/account-login");

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Log In")]'), 5);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
                    $retry = true;
                }

                return false;
            }

            $this->logger->debug("set login");
            $loginInput->sendKeys($this->AccountFields['Login']);

            if ($selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "overlay")]'), 0)) {
                sleep(5);
                $this->savePageToLogs($selenium);
            }

            if ($accept = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "onetrust-accept-btn-handler"]'), 0)) {
                $accept->click();
                $this->savePageToLogs($selenium);
            }

            $this->logger->debug("set pass");
            $passwordInput->click();
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->logger->debug("click by btn");

            try {
                $button->click();
//                $button->sendKeys(\WebDriverKeys::ENTER);
            } catch (UnknownServerException $e) {
                $this->logger->error("UnknownServerException: " . $e->getMessage());
                $this->sendNotification("UnknownServerException // RR");
                sleep(5);
                $this->savePageToLogs($selenium);
                $loginInput->sendKeys($this->AccountFields['Login']);
            }

            $result = $selenium->waitForElement(WebDriverBy::xpath('
                //p[contains(text(), "My Points")]
                | //div[@class = "sub-header-user-points" and normalize-space(text()) = "Points"]
                | //div[contains(@class, "alert-danger")]
                | //div[contains(@class, "s-error-text")]
                | //div[@role = "alertdialog" and @style = ""]
                | //div[@role = "alert" and @style = ""]
            '), 10);
            $this->savePageToLogs($selenium);

            $attemptTwo = false;

            if ($result && strstr($result->getText(), 'An error has occurred. Please try again. If the error persists, please call our reservations department')) {
                $attemptTwo = true;
                sleep(5);
                $button->click();
                sleep(5);
                $this->savePageToLogs($selenium);

                $result = $selenium->waitForElement(WebDriverBy::xpath('
                    //p[contains(text(), "My Points")]
                    | //div[@class = "sub-header-user-points" and normalize-space(text()) = "Points"]
                    | //div[contains(@class, "alert-danger")]
                    | //div[contains(@class, "s-error-text")]
                    | //div[@role = "alertdialog" and @style = ""]
                    | //div[@role = "alert" and @style = ""]
                '), 5);
                $this->savePageToLogs($selenium);
            }

            sleep(3);
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

//            $auth_token = $selenium->driver->executeScript("return localStorage.getItem('token');");
//            $this->logger->info("got auth token: " . $auth_token);

            foreach ($cookies as $cookie) {
                if ($cookie['name'] == 'token') {
                    $selenium->markProxySuccessful();
                    $this->headers['Authorization'] = "Bearer {$cookie['value']}";
                }
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (UnknownServerException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
//            $retry = true;
        } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if (!isset($result) && isset($attemptTwo) && $attemptTwo === true && empty($this->headers['Authorization'])) {
                $retry = true;

                if ($this->attempt == 1) {
                    throw new CheckException("An error has occurred. Please try again. If the error persists, please call our reservations department at 801-401-2222. Feel free to leave any comments regarding your website experience using the feedback button on your right hand side.", ACCOUNT_PROVIDER_ERROR);
                }
            }

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(2);
            }
        }

        return true;
    }
}
