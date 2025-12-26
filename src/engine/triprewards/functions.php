<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerTriprewards extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const XPATH_PROFILE = '//div[contains(@class, "account-tab-text signed-in")]//span[contains(@class, "user-firstname")]';
    private const XPATH_ERRORS = '//div[contains(@class, "background-color-container")]//div[contains(@class, "form-error help-block") and @style = "display: block;"] | //small[@data-fv-result="INVALID"] | //h1[contains(text(), "Internal Server Error")]';

    private $restart = true;
    private $key = null;
    private $currentItin = 0;
    /**
     * @var false|float|int|mixed|Services_JSON_Error|string
     */
    private $reservations;

//    public static function GetAccountChecker($accountInfo) {
//        if (!empty($accountInfo['ConfNo']) && !empty($accountInfo['Code'])) {
//            return new static();
//        }
//        else {
//            require_once __DIR__.'/TAccountCheckerTriprewardsSelenium.php';
//            return new TAccountCheckerTriprewardsSelenium();
//        }
//    }

    public static function GetAccountChecker($accountInfo)
    {
        if (!empty($accountInfo['ConfNo']) && !empty($accountInfo['Code'])) {
            return new static();
        }

//        if (in_array($accountInfo['Login'], [
//            'kaatie.mckenziee@gmail.com',
//        ])) {
        require_once __DIR__ . "/TAccountCheckerTriprewardsSelenium.php";

        return new TAccountCheckerTriprewardsSelenium();
//        }

//        return new static();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->LogHeaders = true;
        $this->http->setHttp2(true);

        $this->http->SetProxy($this->proxyReCaptchaVultr());

        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function IsLoggedIn()
    {
        return $this->loginSuccessful();
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.wyndhamhotels.com/WHGServices/loyalty/V4/member/profile', [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->accountNumber)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.wyndhamhotels.com/", [], 20);

        if (!$this->http->ParseForm("wr-signin")) {
            if ($this->http->Response['code'] == 0) {
                throw new CheckRetryNeededException(2, 3);
            }

            return $this->checkErrors();
        }

        if ($sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")) {
//            if ($this->attempt < 2) {
            return $this->getCookiesFromSelenium();
//            } else {
//                sleep(1);
//                $this->http->RetryCount = 0;
//                $sensorDataHeaders = [
//                    "Accept"        => "*/*",
//                    "Content-type"  => "application/json",
//                ];
//                $sensorData = [
//                    'sensor_data' => $this->getSensorData(),
//                ];
//                $this->http->NormalizeURL($sensorPostUrl);
//                $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
//                sleep(1);
//                $sensorData = [
//                    'sensor_data' => $this->getSensorData(true),
//                ];
//                $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
//                $this->http->JsonLog();
//                $this->http->RetryCount = 2;
//                sleep(1);
//            }
        } else {
            $this->logger->error("sensor_data URL not found");
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Referer'      => 'https://www.wyndhamhotels.com/wyndham-rewards',
        ];
        $data = [
            'Password'   => $this->AccountFields['Pass'],
            'Username'   => $this->AccountFields['Login'],
            'rememberMe' => true,
        ];
        $this->http->PostURL('https://www.wyndhamhotels.com/WHGServices/loyalty/member/authenticate', json_encode($data), $headers);

        if ($this->http->Response == 500) {
            sleep(7);
            $this->http->PostURL('https://www.wyndhamhotels.com/WHGServices/loyalty/member/authenticate', json_encode($data), $headers);
        }
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Internal Server Error - Read<
        if (
            $this->http->FindSingleNode("//h1[
                contains(text(), 'Internal Server Error - Read')
                or contains(text(), 'Error: Server Error')
            ]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Oops - something went wrong. Please try again later.
        if ($this->http->FindPreg('/"Status":"Error","ErrorCode":"0010","ErrorMessage":"Connection Error"/')) {
            throw new CheckException('Oops - something went wrong. Please try again later.', ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // security questions
        if (isset($response->TwoFormActionCode) && $response->TwoFormActionCode == 'verify' && $this->parseQuestion()) {
            return false;
        }
        // Success
        if (isset($response->trustedDeviceToken) || $this->http->FindSingleNode(self::XPATH_PROFILE)) {
            return $this->loginSuccessful();
        }
        // Set up your security questions
        if (isset($response->TwoFormActionCode) && $response->TwoFormActionCode == 'setup') {
            $this->throwProfileUpdateMessageException();
        }
        // Invalid credentials
        $this->checkProviderErrors($response);

        // [20 Jan 2019]: strange error on all accounts
        if ($this->http->Response['body'] == '{"Status":"Error","ErrorCode":"0006","ErrorMessage":"Please complete all required fields."}') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // sensor_data workaround
        if (
            $this->http->Response['code'] == 403
        ) {
            $this->DebugInfo = "need to update sensor_data";

            throw new CheckRetryNeededException(3, 0);
        }

        if (
            $this->http->Response['code'] == 502
            && $this->http->FindPreg("/<title>502<\/title>502 Bad Gateway/")
        ) {
            throw new CheckException("Oops - something went wrong. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www.wyndhamhotels.com/WHGServices/loyalty/member/twoFormVerificationRetrieve');
        $response = $this->http->JsonLog();

        if (empty($response->data->question) || empty($response->data->id)) {
            return false;
        }
        $this->State['id'] = $response->data->id;
        $question = $response->data->question;
        $this->logger->debug("question found: " . $question);
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->State['id'])) {
            $this->logger->debug("id not found");

            return false;
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Referer'      => 'https://www.wyndhamhotels.com/wyndham-rewards',
        ];
        $data = [
            'id'     => $this->State['id'],
            'answer' => Html::cleanXMLValue($this->Answers[$this->Question]), // '\b'
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.wyndhamhotels.com/WHGServices/loyalty/member/twoFormVerificationSubmit', json_encode($data), $headers, 40);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        // check errors
        if (isset($response->Status) && $response->Status == 'Error' && isset($response->ErrorMessage)) {
            $this->checkProviderErrors($response);
            // Security Question Answer Incorrect
            if (in_array($response->ErrorMessage, ['Security Question Answer Incorrect', 'Security question answer does not match'])) {
                $this->AskQuestion($this->Question, $response->ErrorMessage, 'Question');
            }

            return false;
        }
        // hard code
        if ($this->http->Response['code'] == 0) {
            return false;
        }

        if ($this->http->Response['code'] == 403 && $this->restart) {
            $this->restart = false;

            return $this->LoadLoginForm() && $this->Login();
        }

        return $this->loginSuccessful();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);

        if (
            (
                !isset($response->firstName)
                && isset($response->ErrorMessage)
                && $response->ErrorMessage = "We're sorry, an error occurred. Please try signing in again."
            )
            || strstr($this->http->Error, 'Network error 28 - Connection timed out after')
            || strstr($this->http->Error, 'Network error 52 - Empty reply from server')
            || $this->http->Response['code'] == 403
        ) {
            throw new CheckRetryNeededException(2, 0);
        }

        // Balance - Current Points
//        $this->SetBalance($response->AccountInfo->PointBalance);// TODO
        // Name
        $firstName = $response->firstName;
        $lastName = $response->lastName;
        $this->SetProperty("Name", beautifulName($firstName . ' ' . $lastName));

        // Status
        $this->SetProperty('Status', $response->currentTier->description ?? null);
        // MemberSince December 29, 2006
        $this->SetProperty('MemberSince', date('F j, Y', strtotime($response->enrollmentDateTime, false)));
        // Member #
        $this->SetProperty("AccountNumber", $response->accountNumber ?? null);
        // Nights to Next Level
        if (isset($response->earningTier->accruedAmount, $response->earningTier->requiredAmount)) {
            $this->SetProperty('NextLevel', $response->earningTier->requiredAmount - $response->earningTier->accruedAmount);
        }
        // Nights
        if ($response->earningTier->accruedAmount ?? null) {
            $this->SetProperty('QualifyingNights', $response->earningTier->accruedAmount);
        }
        // Expiration Date
        if (isset($response->AccountInfo->PointExpirationInfo->PointExpirationBuckets)) { // TODO
            $expirationList = $response->AccountInfo->PointExpirationInfo->PointExpirationBuckets;

            if (!empty($expirationList)) {
                $this->logger->notice("Set Exp date");

                foreach ($expirationList as $expiration) {
                    if (isset($expiration->Points) && $expiration->Points > 0 && (!isset($exp) || $exp > strtotime($expiration->ExpirationDate))) {
                        $this->SetProperty('ExpiringBalance', $expiration->Points);
                        $exp = strtotime($expiration->ExpirationDate);
                        $this->SetExpirationDate($exp);
                    }// if (isset($expiration->Points) && $expiration->Points > 0)
                }// foreach ($expirationList as $expiration)
            }// if (!empty($expirationList))
            /**
             * https://redmine.awardwallet.com/issues/14300#note-11.
             *
             * In addition, after 18 consecutive months without any account activity,
             * all of your points will be forfeited.
             *
             * Be sure to stay or redeem with us by ... .
             */
            if (!empty($response->CustLoyalty)) {
                foreach ($response->CustLoyalty as $loyalty) {
                    if (in_array($loyalty->ProgramID, ['WVO', 'CET'])) {
                        continue;
                    }

                    if ($loyalty->ProgramID != 'WR') {
                        $this->sendNotification('triprewards - refs #14300: Need to check Expiration Date');
                    }

                    if (isset($exp, $loyalty->ExpireDate) && $exp > strtotime($loyalty->ExpireDate)) {
                        $this->logger->notice("Correcting Exp date");
                        unset($this->Properties['ExpiringBalance']);
                        $this->SetExpirationDate(strtotime($loyalty->ExpireDate));
                    }// if (isset($exp) && $exp > strtotime($loyalty->ExpireDate))
                }// foreach ($response->CustLoyalty as $loyalty)
            }// if (!empty($response->CustLoyalty))
        }// if (isset($response->AccountInfo->PointExpirationInfo->PointExpirationBuckets))
    }

    public function ParseItineraries()
    {
        $result = [];
        /*$dateFrom = date('Y-m-d', strtotime('-2 year'));
        $dateTo = date('Y-m-d', strtotime('+1 year'));
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.wyndhamhotels.com/WHGServices/loyalty/member/reservations?dateFrom={$dateFrom}&dateTo={$dateTo}");

        if ($this->http->FindPreg('/"ErrorCode":"2015"/')) {
            $this->logger->info('Retrying with a different dateFrom');
            $dateFrom = date('Y-m-d', strtotime('-1 year'));
            $this->http->GetURL("https://www.wyndhamhotels.com/WHGServices/loyalty/member/reservations?dateFrom={$dateFrom}&dateTo={$dateTo}");
        }
        $this->http->RetryCount = 2;

        if ($this->http->FindPreg('/"ErrorCode":"(?:0003|1000)"/')) {
            // retry not works
            // Something's not right - please try again later!
            $this->logger->error($this->http->FindPreg('/"ErrorMessage":"[^"]+"/'));

            return [];
        }
        $response = $this->http->JsonLog(null, 3);

        // provider bug fix
        if ($this->http->FindPreg('/\{"Status":"Error","ErrorCode":"500","ErrorMessage":""\}/')) {
            sleep(5);
            $this->sendNotification("retry, 500 // RR");
            $this->http->GetURL("https://www.wyndhamhotels.com/WHGServices/loyalty/member/reservations?dateFrom={$dateFrom}&dateTo={$dateTo}");
            $response = $this->http->JsonLog(null, 3);
        }

        $noItineraries = false;

        // provider bug fix
        if ($this->http->FindPreg('/\{"success":"true","data":\[\]\}/')) {
            $dateFrom = date('Y-m-d', strtotime('-1 year'));
            $dateTo = date('Y-m-d', strtotime('+1 year'));
            $this->http->GetURL("https://www.wyndhamhotels.com/WHGServices/loyalty/member/reservations?dateFrom={$dateFrom}&dateTo={$dateTo}");
            $response = $this->http->JsonLog(null, 3);
        }*/
        $icid = "IN:WR:20190403:MAQLM:MALEFTNAV:RESERVATIONS";
        $this->selenium('https://www.wyndhamhotels.com/wyndham-rewards/my-account/reservations?ICID=' . urldecode($icid));
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg('/\{"success":"true","data":\[\]\}/')) {
            return $this->noItinerariesArr();
        }
        $noItineraries = false;

        if ($this->http->FindPreg('/"success":"true"/') && $this->http->FindPreg('/"data":\[\]/')) {
            $noItineraries = true;
        }

        if (!isset($response->reservations->current) && !isset($response->reservations->future)) {
            if (isset($response->ErrorCode) && $response->ErrorCode != 504) {
                $this->sendNotification('Check the reservation for something wrong // MI');

                return $result;
            }
        }

        if (!$noItineraries) {
            $reservations = [];

            // When the reservation is one, it is stored as an object, example: "past": {...}
            if (isset($response->reservations->current)) {
                if (is_object($response->reservations->current)) {
                    $reservations[] = $response->reservations->current;
                } elseif (is_array($response->reservations->current)) {
                    $reservations = array_merge($reservations, $response->reservations->current);
                }
            }

            if (isset($response->reservations->future)) {
                if (is_object($response->reservations->future)) {
                    $reservations[] = $response->reservations->future;
                } elseif (is_array($response->reservations->future)) {
                    $reservations = array_merge($reservations, $response->reservations->future);
                }
            }

            $this->logger->debug("Total: " . count($reservations) . " itineraries were found");

            foreach ($reservations as $item) {
                $this->ParseItinerary($item, $response->property);
            }
            unset($reservations);
        }// if (!$noItineraries)
        // Past reservations
        if ($this->ParsePastIts && isset($response->reservations->past) && $response->reservations->past != "") {
            if (is_object($response->reservations->past)) {
                $response->reservations->past = [$response->reservations->past];
            }
            $this->logger->debug("Total " . count($response->reservations->past) . " past reservations found");

            foreach ($response->reservations->past as $item) {
                $this->ParseItinerary($item, $response->property);
            }
        }// if ($this->ParsePastIts && isset($response->reservations->past) && $response->reservations->past != "")
        elseif ($noItineraries) {
            return $this->noItinerariesArr();
        }
        $this->logger->debug("collected " . count($this->itinerariesMaster->getItineraries()) . " itineraries");

        if (isset($response->reservations->cancelled) && $response->reservations->cancelled != "") {
            foreach ($response->reservations->cancelled as $item) {
                $this->ParseItinerary($item, $response->property, true);
            }
        }

        if (count($this->itinerariesMaster->getItineraries()) === 0
            && !$this->ParsePastIts && isset($response->reservations->past)
            && $response->reservations->past != "") {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        return $result;
    }

    public function ParseItinerary($item, $property, $cancelled = false)
    {
        $this->logger->notice(__METHOD__);

        if (empty($item->rooms)) {
            return;
        }
        // Rooms
        $rooms = $item->rooms;

        if (!isset($rooms->brandId, $property->{$rooms->brandId . $rooms->propertyCode})) {
            $this->logger->error('Property Not Found');

            return;
        }
        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$item->confirmationNumber}", ['Header' => 3]);
        $this->currentItin++;
        // Hotels
        $hotel = $property->{$rooms->brandId . $rooms->propertyCode};

        $checkIn = $rooms->checkInDate . ' ' . ($hotel->checkInTime == 'NA' ? '' : $hotel->checkInTime);
        $checkOut = $rooms->checkOutDate . ' ' . ($hotel->checkOutTime == 'NA' ? '' : $hotel->checkOutTime);

        if ($checkIn == $checkOut) {
            $this->logger->error('Skip: invalid dates');

            return;
        }
        $h = $this->itinerariesMaster->add()->hotel();

        if (!$cancelled) {
            $tax = round($rooms->totalTaxAmount, 2);

            if ($tax > 0) {
                $h->price()
                    ->tax($tax)
                    ->cost(round($rooms->roomRevenue, 2))
                    ->total(round($tax + $rooms->roomRevenue, 2))
                    ->currency($hotel->currency->code ?? null);
            }

            if (isset($rooms->pointsUsed) && $rooms->pointsUsed > 0) {
                $h->price()->spentAwards(number_format($rooms->pointsUsed) . ' PTS');
            }
        }

        $h->general()
            ->confirmation($item->confirmationNumber, "Confirmation Number", true)
            ->date2($item->bookingDate)
            ->status($item->status)
            ->traveller(beautifulName($rooms->firstName . " " . $rooms->lastName), true);
//            ->cancellation($this->http->FindSingleNode('(//div[normalize-space(text()) = "Cancellation policy"]/following-sibling::div[1])[1]'));

        if ($cancelled) {
            if ($item->status !== 'Cancelled') {
                $this->sendNotification('check parse cancelled. wrong status? // MI');
            } else {
                $h->general()
                    ->cancellationNumber($item->cancellationNumber)
                    ->cancelled();
            }
        }

        $address = join(', ', array_filter([
            $hotel->propertyAddress,
            $hotel->propertyCity,
            $hotel->propertyPostalCode,
            $hotel->propertyCountryCode,
        ]));
        $h->hotel()
            ->name($hotel->propertyName)
            ->phone($hotel->phone)
            ->address($address);

        if ($cancelled) {
            $h->booked()
                ->checkIn2($rooms->checkInDate)
                ->checkOut2($rooms->checkOutDate);
        } else {
            $h->booked()
                ->checkIn2($checkIn)
                ->checkOut2($checkOut)
                ->guests($rooms->noOfAdults)
                ->kids($rooms->noOfChildren)
                ->rooms(intval($rooms->noOfRooms));

            if ($h->getCheckOutDate() < $h->getCheckInDate()) {
                $this->itinerariesMaster->removeItinerary($h);

                return;
            }

            if ($rooms->noOfRooms > 1) {
                $this->sendNotification("triprewards. Multiple room were found");
            }

//        $deadline = $this->http->FindSingleNode('(//div[normalize-space(text()) = "Cancellation policy"]/following-sibling::div[1])[1]/div[contains(text(), "Free cancellation until")]', null, true, "/until([^\(]+)/");
//        if ($deadline) {
//            $this->logger->debug($deadline);
//            $deadline = str_replace(',', '', $deadline);
//            $this->logger->debug($deadline);
//            $h->booked()->deadline2($deadline);
//        }
//        if ($this->http->FindSingleNode('(//div[normalize-space(text()) = "Cancellation policy"]/following-sibling::div[1])[1]/div[contains(text(), "Non-refundable reservation")]', null, false)) {
//            $h->booked()->nonRefundable();
//        }
            $r = $h->addRoom();
//        $r->setType();
            $r->setRate($rooms->roomRate, true);

            if (!strstr($rooms->rateDesc,
                'Prices quoted are only valid and available for Guests staying exclusively for leisure purposes, and shall not apply to those Guests staying for group, business, incentive, or meeting reasons.')
            ) {
                $r->setRateType($rooms->rateDesc, true);
            }
            $r->setDescription($rooms->roomDesc, true);
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation number",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "FirstName" => [
                "Caption"  => "First Name",
                "Type"     => "string",
                "Size"     => 64,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => true,
            ],
            "LastName" => [
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Size"     => 64,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.wyndhamhotels.com/find-reservation";
    }

    public function notify($arFields)
    {
        $this->logger->notice(__METHOD__);
        parent::sendNotification("triprewards - failed to retrieve itinerary by conf #", 'all', true,
            "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>
            FirstName: {$arFields['FirstName']}<br/>
            LastName: {$arFields['LastName']}
        ");
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->http->LogHeaders = true;
        $this->http->RetryCount = 0;
        $this->http->GetURL($this->ConfirmationNumberURL($arFields)); // causes timeout for some weird reason

        $postData = [
            'firstName'    => $arFields['FirstName'],
            'lastName'     => $arFields['LastName'],
            'confirmation' => $arFields['ConfNo'],
            'hotelBrand'   => 'ALL',
            'language'     => 'en-us',
        ];
        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $this->http->PostURL('https://www.wyndhamhotels.com/BWSServices/services/booking/retrieveReservation', $postData, $headers);

        $data = $this->http->JsonLog(null, 3, true);
        $status = ArrayVal($data, 'status');

        if ($status === 'Error') {
            // errmsg is misleading
            $errorMessage = ArrayVal($data, 'errorMessage');

            if (in_array($errorMessage, ['UNSUPPORTED_BOOKING_CHANNEL_ERROR_CODE', '111'])) {
                return 'Reservation Not Found';
            }
        }

        if ($status !== 'OK') {
            $this->notify($arFields);

            return null;
        }
        $it = [$this->ParseConfirmationItinerary($data)];

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"          => "PostingDate",
            "Description"   => "Description",
            "Activity Type" => "Info",
            "Nights"        => "Info",
            "Points"        => "Miles",
            "Miles"         => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];

        if (!isset($this->Properties['AccountNumber'])) {
            $this->logger->error("AccountNumber not found");

            return [];
        }

        $startTimer = $this->getTime();
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $this->http->GetURL('https://www.wyndhamhotels.com/WHGServices/loyalty/V4/member/activity?memberIdentifier=' . $this->Properties['AccountNumber']);
        $response = $this->http->JsonLog(null, 3);
        $result = array_merge($result, $this->parsePageHistory($response, $startDate));
        $this->getTime($startTimer);

        return $result;
    }

    public function parsePageHistory($response, $startDate)
    {
        $result = [];

        if (empty($response->data)) {
            return $result;
        }
        $this->logger->debug("Total " . count($response->data) . " activity rows were found");

        foreach ($response->data as $activity) {
            $dateStr = $activity->transactionGroupDateTime;
            $date = strtotime($dateStr, false);

            if (isset($startDate) && $date < $startDate) {
                $this->logger->notice("break at date {$dateStr} ({$date})");

                break;
            }

            $points = 0;
            $miles = 0;

            foreach ($activity->transactionGroupEarn as $transactionGroupEarn) {
                if (!isset($transactionGroupEarn->currencyCategoryCode)) {
                    continue;
                }

                switch ($transactionGroupEarn->currencyCategoryCode) {
                    case 'Points':
                        $points = $transactionGroupEarn->amount;

                        break;

                    default:
                        $this->logger->debug("[currencyCategoryCode]: {$transactionGroupEarn->currencyCategoryCode}");

                        if ($transactionGroupEarn->currencyTypeCode == 'MILES') {
                            $miles = $transactionGroupEarn->amount;
                        } else {
                            $activity->date; // TODO: gap
                        }
                }
            }

            $result[] = [
                'Date'          => $date,
                'Description'   => $activity->stay[0]->ace03Description ?? $activity->transactionGroupDescription,
                'Activity Type' => $activity->translatedType,
                'Nights'        =>
                    // Stay
                    $activity->stay[0]->eligibleNights
                    // Redemption
                    ?? $activity->transactions[0]->spend->quantity
                    // default
                    ?? 0,
                'Points'        => $points,
                'Miles'         => $activity->miles ?? $miles,
            ];
        }

        return $result;
    }

    protected function ParseConfirmationItinerary($data)
    {
        $this->logger->notice(__METHOD__);
        $res = ['Kind' => 'R'];
        // ConfirmationNumber
        $res['ConfirmationNumber'] = $this->ArrayVal($data, ['retrieve', 'confirmation']);
        // HotelName
        $res['HotelName'] = $this->ArrayVal($data, ['retrieve', 'property', 'hotelName']);
        // Address
        $res['Address'] = $this->ArrayVal($data, ['retrieve', 'property', 'hotelAddressLine']);
        // CheckInDate
        $date1 = $this->ArrayVal($data, ['retrieve', 'rooms', 0, 'CheckInDate']);
        $res['CheckInDate'] = strtotime($date1);
        // CheckOutDate
        $date1 = $this->ArrayVal($data, ['retrieve', 'rooms', 0, 'CheckOutDate']);
        $res['CheckOutDate'] = strtotime($date1);
        // Total
        $res['Total'] = PriceHelper::cost($this->ArrayVal($data, ['retrieve', 'rooms', 0, 'TotalAfterTax']));
        // SpentAwards
        $res['SpentAwards'] = $this->ArrayVal($data, ['retrieve', 'rooms', 0, 'points']);
        $res['SpentAwards'] = empty($res['SpentAwards']) ? null : $res['SpentAwards'] . ' PTS';
        // Currency
        $res['Currency'] = $this->ArrayVal($data, ['retrieve', 'rooms', 0, 'CurrencyCode']);
        // Cost
        $res['Cost'] = PriceHelper::cost($this->ArrayVal($data, ['retrieve', 'rooms', 0, 'TotalBeforeTax']));
        // Guests
        $res['Guests'] = $this->ArrayVal($data, ['retrieve', 'rooms', 0, 'nAdults']);
        // Kids
        $res['Kids'] = $this->ArrayVal($data, ['retrieve', 'rooms', 0, 'nChildren']);
        // RoomType
        $res['RoomType'] = $this->ArrayVal($data, ['retrieve', 'rooms', 0, 'Description']);
        // RoomTypeDescription
        $res['RoomTypeDescription'] = $this->ArrayVal($data, ['retrieve', 'rooms', 0, 'roomTypeDescription']);
        // Rooms
        $res['Rooms'] = $this->ArrayVal($data, ['retrieve', 'rooms', 0, 'Units']);
        // CancellationPolicy
        $res['CancellationPolicy'] = $this->ArrayVal($data, ['retrieve', 'rooms', 0, 'cancelPolicyDesc']);

        return $res;
    }

    protected function ArrayVal($array, $indices, $default = null)
    {
        $res = $array;

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

    private function checkProviderErrors($response)
    {
        $this->logger->notice(__METHOD__);

        $message =
            $response->ErrorMessage
            ?? $this->http->FindSingleNode(self::XPATH_ERRORS)
            ?? null
        ;
        $internalMessage = $response->InternalErrorMessage ?? null;

        if ($message) {
            $this->logger->error($internalMessage);
            $this->logger->error($message);
            // We were unable to verify the information you provided. Please try again or visit Forgot Password to access your account.
            if (strstr($message, 'We were unable to verify the information you provided.')) {
                $this->logger->notice("false positive error");

                $this->markProxyAsInvalid();

                if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                    return;
                }

                if (strlen($this->AccountFields['Login']) > 16) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                throw new CheckRetryNeededException(3, 10, $message, ACCOUNT_INVALID_PASSWORD);
            }

            // Invalid Credentials
            if (strstr($message, 'Invalid Credentials')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'Username is required.'
                || $message == 'Password is required.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // Your account has been suspended/closed. Please call Wyndham Rewards Member Services at 1.844.405.4141
            if (strstr($message, 'Your account has been suspended or closed.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
            // Account Locked
            if (
                strstr($message, 'Account Locked')
                || strstr($message, 'Your account has been locked.')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
            /*
             * For your security, this account has been locked.
             * Visit the Forgot Password page to send an email to the address stored on your account
             * with instructions to reset your password or contact Member Services at 1-844-405-4141.
             */
            if (strstr($message, 'For your security, this account has been locked.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
            // Oops - something went wrong. Please try again later.
            if (strstr($message, 'Unexpected Error')) {
                throw new CheckException('Oops - something went wrong. Please try again later.', ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'System error')) {
                throw new CheckException('Oops - something went wrong. Please try again later.', ACCOUNT_PROVIDER_ERROR);
            }

            // Something's not right - please try again later!
            if ($message == "Something's not right - please try again later.") {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == "Internal Server Error") {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // no errors, no auth, temporarily provider bug
            if (strstr($message, 'Invalid Token')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if (isset($response->ErrorMessage))
        // AccountID: 6803080
        elseif (count($this->http->FindNodes('//small[@data-fv-result="INVALID"]')) == 2) {
            throw new CheckException("Password is required.", ACCOUNT_INVALID_PASSWORD);
        }
    }

    private function getSensorData($secondSensor = false)
    {
        $sensorData = [
            // 0
            '2;3421746;4407861;11,0,0,1,2,0;J~S+KhCza*$V*UyU&O231OP*B,Bu]FL>WWk^-9qz]?%`Ft8AR(6op&I!/,@I@l_hC+:!~9[aa,2[L!U_kM0EBz+h(pA 2on~ey,n@c^>:jS0-RYq(oUsjrcGm+8~3xu.ne}fl75xxZjOJm[i0(j|2n5_]4x[~Ss*uY[<#P7zPBEpesGe)}K^H%HFxRN42*.]vgyB/ivTEEs`[u )Rxd#E4g`@7vff!BT=1 J2B|}$z?!9}j3mkPz%_lWGC-,cVj9=ASB!OO!255&3J>do}oy6MkhDY:8tF;m_nGzipk]>6A3{Y`v>uzBGjWMMP,*-!MZ-My6&MVXpb,B]fmM<<>(SK-:6ID%8#dDOi5!%#7Ip-N^$v{IJVhP*qGQ#6}j-,hEc#VD?/> Qf?IU n2NnqbF-BLHlr+`$X>Y}gPX`)B!5n.U=H<Vi~BQXWwz76`S`F>dv7`}}skNZ$@/=OA9b,hair~6YGn)]IpljdIg*O[U~h+[p)v$<.8.IO.Fz0Q^r?`y,&Kg~Y%f=&?z4bPV*SNIcp3O&6_s60-[#%!|jSwhG4P0U&x.Re49Aa9Qz>},V@MvZjf#&8sW6(fBI:8<]/`_ =|Bm>vvZI_2s26U$tOH~{PHmFKd<&3-]jOx^#i5LNtHhDNVTR}vfdP/d9GjM4NMtB(4EaBkKU]Q5V[(LnIQH<j!G):]O=IU-[B(dB-[90M=n#iP+Qj~^idZ4p5]?:Scnwh10_&%fzJ+kZ]P,/$<w,Z]oOqPhP*q>}k6X+uS7Mmnp(^Jj>(e2T>W&YPnnd<_;qsZUB-f`w;=Fn>*r}&F4[OcCFg;>Gq>fj`4(>Bwr0MxiG}pZQD39K+J12i[pjI|YDE4D5;&W  $0LwE/INp!JZf5*&%+-B=9q.+9{&PoP 2L(^cf{.S`:2Re[8{,D,O;&`V^@Jni>&3N9`1~<R53d)LFH-s1[5Ywf#)G4YN1>i5H2}&A0}M|E4{%d`U%2>S1$2Dfmi{KL!q[tXrj{<|69P(U>@5+B o$yEHy)GGc9x^~#e-7:6s6Yxt)7,(]~~mkbxRz7fevjl&XT~i/Z}qJzF/2b_M#NTNij6R%Fsr$|tO.-+Kk@vf=hV/Krt!MO+&4NS:z8g!%>4yD3_!G(bFzuI1Yz+3hFZIVfj}W*cr.=>v==vHd6i3kX=%1-|<u>n_{*6^8g}1056[7u)^e~gK;>wB<o}: thSMpNVeFu8d*0XrqxHO;qvi^SD`df$r`()D8gD3qSjQj$a+u1gKA~5-t{iO2/rU5?*] ]1Lzc|W4M9IVwWF>|Q1x38u?YHF8W}Z!wZT<2V>frNqJT]8s2C}9xS6!Q{!q6YFvoaY9=m[{|MBr6~uwz68ESaN0f-4Mm!#f_KK+fy`*[aG<c[Sia{~BOT!So%Xg0A%;/&*y$d:2af~[|(|-@Qc,4L;Fs]e~=ssSnXjb>YQ#_x~V5<q&h%1EO2(6tHbrW.)y$*47I<2nYgv]BVLg!Kv8>sgXd@[yO,;#I>fWDhoJ8YpP9(5IeI&Q~4:X1%NmDvj2FNs&9-A&w1Gv9EM$Gw,s+AE*gV)Hw40&L[(-jikGRv94)D=zK!B~T|*O<0c:*s$3Qq&QLXpY%6km)Le/AaC[qZqyfr8gz_?1,VY19&5NQX;J:Y{t&i(/dX4<:<)Ey_?`Lj.F6|F8B(etWt[djWFrj8O-+3QD4M/>#]`C$%]`j1T/r_vRL~PJ;1?Y*L1UdWkMESsT8f/22ZMY:mpWLUte]<MVog5js/K7wzmz[vC^t=CwikcMXR>kRDWUnwT>SurKb#VO$HB L?=lY`t4`r{AKG7gH]Cf5T=>acyD6SYX#b|}kGZ+Zve0lW=!ijwrA2>o{B2<i8 53xHp}NBrIiP^?)A6[Y@0Jv{#{e4F1NNEI67$XL9LD=`uX#fl-$H${!+IGH}OUSa%Gv#W))EH|2O<gn(U5_ft x52;z3o}#@k:~Io#E>bIQy( ZrSP>&W7_0lhfCu4kIQfaE1@u<CCfaEv%{>s_f)Q7fsDMeRhSJ1ozi-khe3Oo1TM8(7y^v1lTVGaYb$&(.iJg-q9ldv@q@mvZR@O8[oDRB@QU_^E@CgLDVL=I5]Eu7v7Cz#~cn^7X:4Fd/5IM4,nb#{l.w=.r4 =m 9.`!#mXnu@?x.[VcbQuEBDb|Hw]6d0m}B?7k~p2=Gy7`vFGC2c$X/d&3MQ9cM bvHD@|EKHky%CEV|K]2f2fiZD]Bhd69h&m79J}[eh(4h8o&Y$>;K&hQ}Foa64`Q?(+&~6jSpe!J{OAT-J*((M1owNxfBF#+WQ)Z_ fGge8V<lP|_uVSo[IJDzpbBK7DuR{&;O?(>R%D(dZ{iGgu9 NbM,`RDMDD..YCT#P3+3o<)fW3q-^:ZFG`.r@>9JD9/3oVzr?9OeM%oTE[yX(wwo_YIIv(w.(!li#j*ONKu1*[O4/zpJ%Pquy/{[GQUk^O!/df=[cm5$G*u4kXJc~Gjz!R}u[E$A=UcogVL>TMzI16N3<#/,:l:Oa5o]+}L@Maj@_hFF!--7aU^Lg]pB}`Q]gU+){1dDY!@syyN5@T@vYpFCNM[TtO)DI=o%z)&*<cRx{(eu(6dOBTISaar_vF.P]Q9fHw%~5x--hc|>^m4{[KX<%1;DC0Zt_C8EU*3|?U@QIYrax%q! #BW$',
            // 1
            '2;3491385;3224114;10,0,0,0,2,0;<=hM@RU)|T{rgx0I{-~nc^ThCruaL&]AtpkX}k6VZyq$&.,ZuXaABO]O[fgVQ@i$/qunZ&f]nCPnt$W;g|_-* 82IQ__.:j2H)52|Oc.D5S^_3jHi iOIPYl.6MFR1!*J@6DHt3{g3X0Qe`Jy.vyye;6Yh}&dy[..J-Ni3H8R>W#?UG9^6$e}{6S!5QVjXNqG=3Wj:U7$a|Xd)y7`uwg!)Gl:Nkih=~Iz&$Kc<FP$hK~4//B8aVZn/[z<e3DaPZ*9E::P>]G*qE$G8*yUFP3[nGh@]=H}j$*,07%>oXK,vZAk,>R5pyq<I.N2SFM!{/v[YMGgb<1g|U/k0K[9/?=i%h?zQ~G{&9up%+a7}uA`W40XBNYlJQKEb0yg:o;pu.%a2G$WU6:$?Mp@%;l/OVwP#^8%ZS S^{wu+D0@eWcPTY +l<SY:M(i#~UV!zH6wn`^XO)d(syt,||byt97`cu[4/ZgKJrXsKP;{;%`1t~$2,A&{Col#GfJ?9duZJCzJiovLvh[jk|2;yUJBh@OHv>DS{epnm?6h-1?0VD;Mh{/<QiUG,_#+R*$bfQ]ZlECR{!lAr#T83al/`eM[5lADtNx3B:XPP3U}Kax_@BQo~@U*;WJTD{=esQK5F;)1?-!dr<QM,><kuy yQ|x##8o%7<Ki]*XUqt{l3>fyaq};Z+CF~GZm n3R>*63UO6c#x/|nQweaouFpGs[ BYsbTr`Mx;4do9?h@$jgh]L>kyOiR4p*gR:56a,#v(/bk?Uz+8;/`d;*RuTnR)s8#;bZW)g<duxXhz?aHM ,2]Z]4G0ZF${u2Cx}!T,.$Uo8];T07_ak>}!(b(rH+lj9-Ddcr$VVb_Jj>~N-<lm!F3<N)L?|mKR9s=!7Nucl{wpJwX0X3(edO2McFfh</(,ve.b%:$s}#a*dD*drt0R]BHdcS)$Uj[[<Dtco/oZ:?iF*fK*t:v#B-0MiC+x3(fttjD6O{B`{(%I4Z~P/iCa%%y8L284p._6)(%fyK!K`+e@<xoOH/m.IUBm51t&q(6v<:#9Ovnx@mC-%e*cQA&ShNOtNKMbs7(~f*{xgBHLm=1ow[j(a.*K5,5o?B*K$qj7HWH9iPVk9D?z}vGL{9%^2=R&FM5|&BP9/A<E}XY6] -t.r.{3 ,M#[[3o%{A?x9Qc| 8wMpVn?J/O:MSa`7D|@R<z/-Xe5S*?dh{YMbxeEHEgc+y/FF|R5U$o.2fxh{Ml2QcG4NkgIFJds>GfgiNE{8%$&6Jx`s(-c$~:AagB^J0,G^[8(?WNP^R>FG deN~4K_yQY.4F[*)A6O)h6R%_wt@}zI9*|c%%SBKD4[rqlHZK, iY+85O`x,QZH7 VAtg.[x*PbvWVEyl#z)UV1Y C1=U>6$VE!p@{`6*MMb[R,^YT64Xz^)gn@h8|fL<6[B}2jWv99M+rh2LuO{A?6fHc)&r!?^iM!t^FbTaaiGdO&ebdX:w^:,uH@cam=x]Gc:?yX[+si`k%kCz)iB=ttuk$|ph}p&g]}* >3 4Pn(dmQNgUCwtr)PF3OIL>Nn>WnQmy;Fepfg5$8qm~(m1?.Kx.a9qBAA}_F5Gl-WKBTnVBk^d!H2x5z8,x+nj7gwm7RamQtX&ZbY<g6woxo&Kc1&&d8nRbL*#OK 5y0P(mZl{xq;3]OVhGhMkw J8@+IZc)TXuiwFjv%7YXNLGeSXWN}ix eDDxBtlq|<-NZqQEB nAUek}zh$%m{arQQVPeD^sPX!v,rj(6&g_]z}:;TOO5vPeD8vRp4h4QtDGnME`!iE][{wGPtgiZJz+4B89i1k}a<bd xc1V#3<Kl~GEos$pv0`ROssPx[~h7]l78&Vyrqz|Mn7N#!9T&Q#toMb-fxqX)Y+{B@lBC:O]7K*2V{752c-;rduk2,WIw_#DDA&RW]s0:#Q,Ph}*&p=MB|5|XI9%_?_=FN6mgj&F&D3Dd,<H{T/T.)J6.&LC-~Iv+A&mfuya3iOvRzvV^Q/GK,]E}C&tURrXh|jRcvznHytGpDty0;!!<$OR77%/r`:t?,I1AmLXPYtG<]@AgSBe$dbP0=,1u7e@OB*c.}$EF$ L9}P_$8~9_I*/%-**!!ak4F@h_yb/&uoN:xZt2IAVbf2Idov/&#tf.iZT~M1l^IQ:*hW}AISWR<7@;FL-xrt65Xqn2=@!yh:z{=-d<89m84y>:^nz1HW-oApkQ!j[_Fv+%xHc0HR0S!hx1HUv#L?:rJ_{Q#r{#d!z{oe;y}xUC9_wJhq;GwmjW7RkE zk/%D)NABpXE6oEyqzA:&Ke',
            // 2
            '2;4473142;3163462;12,0,0,1,2,0;1RK7e?IFw1 L50c&p<7c_=uqr,o&Co=j.M7q0SZp?FH#jNHmfXb``KY96LxHmSYRj:9F{gsY%1sF?V,VIvOWfL9<:s/l=$+;Ts 7{gt-^:!hdBiB{U+K]@!DK5qd8/tb#ec}hOr)b50q#hUZV{wTE8|9~/R*u9@-M#7^<A,{c18-n#$hr(AOojhjAxJ5vnO$jLm(%m;OD`0! MK}#;:$C6AQ,8;4a/Z;xd$B?Ard(hjzR%0$5}p=oeh;P:}nYaIHye)W/f1UQ6QHkk*0A1tF{(*S!tU6FD7=vs7`rIU%wr+mPOoq]{~A/5?sJ:w}u,orc_+im?b/+!EIr,3Xldk?&3~)ZD>HDSJ3FFm`<#c/H;Yg6Ex$z$ST2!E+]^Ne`s%/$vbAFl.8L<d#Ql<V|w)qUq.$6c]bw)A!LO-7d}&:=m|}]~(sG>YF-]kk!`Uc{>R<jmFH]+)V*,SIj}_yXe|gc2}H.8B[h:Nts Di1TNGOwOko1`y^D:,&SM~gr(QXWQlp/^zFyqE@P$O4cy-^iG06h=A*FX-1u-Wji1|N6,3Y<k/lPAqBf7#M!FU.{$R|T#LPD2|S3rRl1uSQ&oL3V,4@rjCm+9|KcH(5a[u`/AKXR9fbqN}i&ERlMX^jTc;T?jFIzw-w}@{&[=8UztDGGVdp_EBgD[AIa7t4pw5o5/4mLx/,{zod7l,X&5BS4/EvfJaW/Tb&D`CBx:g5Y6v$RBd[y#lZsetO?{RtjtYCJ:2!d8{)$[/(cJx::&#}P Q>:$<kQzh<3N#D_bYpf/#}+f+K[$~>zj;LO$.p>uH9m+@<,g>KNrYvm>+`dgpkF0-}yopO@n>? /VUAWO}[EG_USij`cCl`MQKMGPy7b37Fqj^Qp%pWvp750fJZqDq$8olkr rk/n)CxR``u*_r4Ga`g!:N]=v]o{x4>C3>u_jyo+[br8=$Ke/`>&h%H2Nrkmy?>W9?c+?UlH<Cj&m P5MAW@;[rcG>XujACa]l1Jf{y^z,>(`!V|JNzbY#?i/,-[k=;b_nV[&6M9N2l?GeBmH[#ouqt]Lec8Ps(I_vdXLdVKy.h9/(Ca4[vwpg4vn$@auTFFCZeYzir]<T.i[ qf1Dk;(Sz/w>]1N#>xtRPB,bXDwkCtxNm._~L1uM (0TlA.Qd@(?.+u+qk5fWy0s^>! @5IP!}#Mjw*c}j)c=u]3M_>f`>@xw[Wyaj#wOm}UpJ|m5x:{X<%m2NtZQ/6 0il<{7;J3I`khEmlQ+W:0jHA`}L;8x,x,rz4?Wuhf_6v8%kfA(}GUsoi(E:uyiuawohR8`$a|Ed7nnKAf`&JZploP_NK%<PDd]iS<g.0+T(OB:|Byj=; #}tJT|dek_,@8U34R4:;b6~ZgLL{F+r<jQ#Y|yvX2NAvL1#sl//5o:r|tbqkO`IrK;zLy^_lIek1BZZC1A!![;.vc-2|-U&J!D+@sru/<ihIYpB0^9^pP.xo3+80E9=K`j&jJ.v|=n(a>/39&g/L#kr_y}>9VV9?&4c~;C:rdg{Oi<HRK/Fub-UJai!>(W`<6%(iXnTUpC}!n5Di!Lh)Bw qG0y*K/eBHZ).X=H{;2S1Z%B<]VqoS36Ok+Ud)0Q6?[.1+Ud7n$3h3u3RQJ~S8L_f4,*=br0,^e09:SwX)~0JlLh1@vwPAMp Kfyr?Jnm=Q#*3o&b:6Ul7zm x!SB>:pV]#X{XqDDJR~t}XthyG|iUBj@EP{KZpD}lXv3mOej1tI^{YxsIV&5eY6]z@$_y+Ws_0oJ~.z&#T}>[[NaWv3A!oPiK[_<UhZ%3ghp~Gb@Qam1ojmljR4~P*EM_HrGfC-2Pg%mY+K_zkm;ENj)~m.Da3uBd2uz6zaplHJ!jj4L>s_&j,LZII@y1mI;{-KEmczk{5PzM&vB*X?&}KO]x)%?Z<fbGy:$gl{DSZP>MzO3LwiYao.~]@m=nhk9wSI17W5]P<iWb-eh23dHJ6XN N{NVetw{GHpJ3#ex?x;qQYtoe{:t9;l/j3p1J $zG){hewW,gUq^L`_,&*3t-YdWrZUjAj?F|H&E04zG5jK#X[c0,TDdgg_8Sx)yq:dV`~3jT&Ha9{g!wfM#tyjWyCUrLPB4;/|oPrJE8=4p+tZd@n>Ra#-=%4fFZ_Bq+L5mb(X[CYa-DiAq#:OEQ-]{gdLS9tp#fm=X**|)qaF$dBav9PGL}NMZ!;tV8lYop)Lrn4[eih5j V#5n/wzb3>CgU4Q=#$C#x- 2gDMUuot2lY{*IKXryz/`*XRAz#-Dj:Kh (8:~/Xyzjx7c^sYNWj8z4C7 #iJqWqaxTZurqQr}Fm0hro ay3HL>|~HrOJzds:ygGCp9-VI,]7;{;&I#5zSjO`>D19aLVrmOy;nM!kec![Ao(.RI`G/1r-yW80h&Xpp2Mb_b}aK2WkJa3]#BlT?->QMj8rL6`3l1Yo)C8n<s[/R&@usHnPISLig${*wyI$lez$V@>28 yc*zKG]WYQ@|iyxBIWBAwUZZ:?[A>3;,^)m:x}3^>:?{Uv#FmIT9zUb6rG8}3@glsli1wxd0,TbmvT6x*c29MdDfE!si&gmoM,2rCfvQSy~O]Jq<Rrq!aU^C>+{z3Lt!44$jJp.u^nXNZ&%heBJ!w0(>&fos82VhyuHsOt.P**-`0H/^|U5yfZwu@$/*}h&4~^eO3f1/x;G}N~U2y4CQKOs=9-G>`HWU{4y4-U#*hCV)V:Q`!Mn3/[o1%x>Ky(?]rUoic{vomTW.[DszIy5eeeD!xt2B|-x2W}4-/@JME&|.TD)LR:R!39',
            // 3
            '2;4337985;4339265;10,0,0,0,2,0; Y=*P_x{NerbD0yupuOS ]r=/q<<yGt_Z?sQu:DH7ToX/)x*!{io05SkoO{2kA+im?8g9a}sa:gC #|.!0uzj[{7)1S#c/-sHF7pV7E95k0}hjt*p4~|S?s8Uyc,mNgGhv)VGbQOR4SI:NA]^HVhMXz})~VzkWF2B,9w]:ga{I|yRp;u%@Mxtn=%@Gm88#ih%tsfM|`ixN,8.@I##OHbbJ6+=9$ML*IALKRN@rYUn_5E/bn/Ut.EJBdu59 VaHaMFTWT#M2Un[,<%IQQg,Zo|!tX|2p=C{lo-/K=Gzx/ODz9GluY~:c<L9=6N[XHRRko64fanR?T&yl )2e8d8~JVdPH!1(cdz/Oj!UFaQZZ..>[N2@]r[|]x2!}Gqsdgt+6YKdo*}n9q*q4?%O7lYwWm/2v[>8|J[-Iq%W|&rxPk(.[fV+sL-nVr34=mJCHhzStMn<exp!K6GGo:tRE|{N0cy0>$OP]BE|; W)5aarT91G~+4o^C:y|;vs%gMUfKZ;]CjClBNM2u>CpK=t&biD7m>stV=wB`FQe^,a+f}2{^y1uRJ+3o{bW:q*H;4<W*U?gYCCa39`O7Xy^D:}(%-ktW8UsoK!0%I2obE:mBVyyl5RB(yr~v,mx[S`.96Dm[&!`_;/kEs44!N|pGjYcm|#ydR/GJL,=)[eC@1%hJnD;8e=8#-2MFXZtIRP(Bp7VE#@$#_oSKb:GI(W>4_Vu 9FbM;!big;icTQCMCH(!f;CB|fkI`xHKrl751nAab<ptfc[sm{^a:n(3wT[V!~Nf.OpXnB+G=5B>qtp+~33$lMn&[GGR#*V{Ha04>|`{4+eJSmm;F`GJ3$>MC$LKo-W$]r5AW<OWzZLFMor=;d]tI:W&ufy-_uU~FsG[qiY!A5%46]l$7ybuVTy>5.Gc>U1>)C}i}kfEdTDXc7CQV~7jeY8^RK2:i$}I3X<W#ttBq,r03^mGj:UcPY4fgX,{f]JzhE~8kqs4z&tAS4F#:s/V=> e`a:f>upL=sV0SXaC{/&BiXgI`A_m*4lHTcBfT6j`jA0V,2Kzlu!WvNkW&o-H)J p*Z/l9-5usOO&bcwK)pn<sJ}a5vNv!,zp ;%RT*.v6me0|5820ES*OFua<uK+4gM<[vX7@t&5(rjH>QyWM{-hD~_Y;)L;]{kM0L2qo`+pzkvM?Yzyw:]RtbIP0-JJP){z;[O:=EYMh_iV1o<48Av]+],3-D %48Y%[P.B$wB)PadqQQDoN)byp:qutz*+R&&B 4JyCc]*FWHPKJ2Yp.X3/lt:(>JORF8Gz.bPD?yGvvw](2BQd/G[HI<(m^)-}%%W^2q2[}]?)i8!:uQxeDcL5, $[AfjG=D~.a# i%0$2582x(q_9TMx,;OK#e%hnJR$ow=.bI5byfu(psNt9CNbqHC-a}IjDx@9pX}7_7vi,@4Z= !qpw~9Am8}9o+3Wq$ZB#Al,XCoU1HX6{):yQ+hT;LcI]ScXc<ot[[{u|-W3OVVONjF:Mnk@qan<a6lk@if5I$gH$t,uhcI>RV[dHo}GnUf }Nvg%D,WFH..1KzRD:v+(8YxC*G@OQ$lZUGr.`M.5hC0~<Ctld;7+}{zc0e1p;yY![,@}Dj2LR gBPh<Uk6&;ZrNm1gr19lm}~l>`7DsnCM}e0u2$esnRth(1AzC,qK-!:{1904PXG-0n{beUXxIt8-8]zb|mHZ-o;~@YM8[,iHV:SCLFWrs4feUwAQWA@+>Q*=o1N/qt2x~l~.p2PU53}=;bzC{|h!dhj+W-TSm:;+30Dgn`lWP)YQc0TCv sxeY@4d04=jC8|<>ga&|E3,ZBPQ)EA &#4 ,gU!ju~Z+0!=Nk$BAfP`9p.?pF1*<(<%>+*X)AI/m|z-Cl%N_rnxT4Xl3&*t%-,;B74tTc+gM^[$>TtI`3*5%k-:|ceUgp24G1D k!B6k:8ayEe6W|+Fto2]<1,&Q@k#T:Exv9YW7L`(H)U|@r1**|:qqQTOt(SYw.=~z1eROm|MkV#Y1Um2&}gvsNk~.M~6Swwj_+`DPs4o7p--dDQhn{Hzmiw+^sI[}4OGN}_LX~AxH4kU a%TJZ*ihXu3Owc&Jfy%!YC=A{=qI6viA,G4%2jKTN}%k/l$c|BGQ1nw StrBI-xP@mIGW;~-6Y|P(`2%3_]o[%CeKl^3<}^UY!Xq}dicg:b9oX.p-tj&uNi%l$6|/lnFF8`v-~l~?`==VAl]1e-;&o-U2^]MTEKCEwbx!$|4Y8hI!%lQvQq(4!=WN%,r<,7})f>g07#Jt5sql[5w[>]+vuBofBK:ILgbf:?W#,+Yg_3%fvSS+5Ik_n:je<p)L:dTV}mBqfgaC0m{mhbS[5.#*{[?ci-F3F&gA;@CF^03U9>~nze]E5YO`{g==&FO[qfd&yz6p+#eDD%&&:$9.~O,l,]C]k0=l$CQoaoWJNi=zrl|n7$;5 jGJm`OTSntT_?Uru{^HevbFRDIYeoF0/fFg{zYrq6_3?*8Ojvi^t:(hzf{EUSqt}kKl6(s9=d#Uy[#?_|^Rw@0y>1GSx.BQYXE_/$-?N}I}I.L)8XMBkSA),>[{=X!n=5$V(ol/J5Z>jH Fa2+/j9*o17r+Tg;_gT^6jlX$L*[c%e9q@lY^/k~X%An2g&Xt4)%]7HMy9.HC+zF*K%w1if[Pt)g*f7_)@AW3pn!Wv`E9}gGJg5B*wuMGLr[&_(cAUWXM9/G}WH !bo<3tAWdO8IX S#T_vRtNhzY8@D)${VcUU9~iAP}WEz Ii_6qZ?a=BkWSPzn[J/+$- oI6Xra',
            // 4
            '2;3622466;3618872;10,0,0,0,2,0;}U@O^4J{XCkIg<y38!7T)YH$- yTAy]h!v-@vHGb:UE7`*G.y4>N~MxOVBQ9#xO`nO6,3b6_,P%p;!2B7*EsEP3vYw#rkM&Xmvlx5sI=pfWFvGk+O|B7I-7,K;9=yMm1a_OhCqQK!6sb9q9djS*WFQ=3|<[TbH5jli7Y.vCA&9uva(c:8<e3+r+&>MhnzXc7gltEe`>?-7}0`O)M~oIFeWvt3!JN/zp;3,16K@Zzr}|-am5hk#Aq=8Jg$+Z_yzw`5HZ<i[]cN9i 7X1{#>Alm?|,pm9_ug}^GclO/0{%2G@UXM!45Qdlt~Gc-LxA^[nb;VyU>@BXbyz:2^$lS8*p14b RwLj^E]~+P{kPMBp 8BdmxQJo,6r0m|=E6m)2w-Vu2jT;V]{2UV).E*!Toef-Em0!#aR|7^MSyF=AE_x.U&BEl+.yGP+A-`9rD2iHW}KMI&v 4Cm+ZrRZI}w u+!VcH/cE+eWd.*B|DQEsxX$O|PW|y _<3#X#XcT*U54o>DU?n&#B`<e>f76cUluMa=Ro0/0xq|j^?DUQ!37o.h.~?-QizeFcqr^*|ea@S/&J0IhF/mQ)x&]Fli ]h#n`t3hg=&[mFncnn9&v0ETb-Gyo{^Z9PfZIk&dtV8q5sm[</d33r8N-dU-#Q_}Z~>F5SPBrX<LGTAR6(@aiY B&TYD9tvH.Aklc4a p68O#0ZZJ<oB<2d5yAQhe}vPCd/++n>7%!(o|cWq`N%V)e>:H~2#{_D]EtC$%.`WJfg*U]ZJW]:qo}^(#eiOo_gSF~ZQiC-1x4AhseNl:R;},>oFAA>5qSUr;jPozX=G9Xk<Nz2>JP(r#s(j8LV_~(1uyWCKsTNKEm1PqBW&{1%n*Lyfe^p}:K6(T7%FdWAQZ-9AAuYLXw_NDsKpm609-U! &pf)U;!?R.G{j0FKyF+(WucJql,@lt7`S>hRz!r-7v;!R=Z)J[aT!@E8NP{6e}@;oRN{R^Jb._0`z].#Eyyz<v%i!>g!MiFEmn[7rFM>^@m]8%c7Nt$/^6K5]lCEv27FfhdG(.<[$m88sdD(] {`tZ+Q&qC@|<$;Z`>#46m-MlgoMD)wAky?|Mdp>i9c@jZuF~`H`7C8UJ8&zqSN!am~aV`;Ws+w9D* x_H5L0v.`9DDK5/uZ8R%YHb{GwrA7bQ(Qn3vi%H4gYOJIS0n#kC!zixp=+&_[Q!G.<UbyGEc[+FXc_)Z!>5X/wkDGISl?%1[7eY1x/Hz>Dkvh){=|R_c9hZ;:#:kY!Nkh>pK>avNrI.uxsd5m!4ocao^tI4{E|&k+pLZHG=q<#*.QJ{hJH}4 $I>KtV9,cdEKkTXnL|sX(Cp{8Acc[I *#Z}B:T(;Vb&#fzsmD;3x._MYM1,06_?,7l@?DAvTv3r$Cr:=xhF)=uV?%*Q)6M`)uwRa``M@l-m4fvmLhm{MIPB$>t|85~{6~~U0/Zc+F8gUSA91e2pZJ.J7prQ_$Lnh $k<fmR8r`Sl[#:XYgbE4gAc4,[u%oOU3|?hj$B7Iyx%KDxE>Z.6l5-{Rga 26kQ%1&&1!JG`U <!-W*3.2]VxJ~|8XrjoI ]wW~x>$Zp=gUt<,l73/cHrfP7yX%)7yJ|!/XjsGXqAmjg8qal%Js_E>psVBa1Ef;y1<]zc-lDSjne%kFjpKSsXN13ywEkw>!n|3j)m]5]rdZQe}-LfDooW[KQ)%bF5K`ImyV|uF)dP-aj+ye9I4A1HLKRG>)5T=43G4Dx<:5e^1d5D??Q|cUO=NG`OC-Vnkb]}H(RS+!fc6r9a^U C;x) 45G<D72P|~U}^M6Y;lUK0sC>Di7t9 QRnKd15-/T &Qd,OPa{!6/tl5cx Cm#fs>tyKX sq@$0Ci/-t!grdmTX&SetM&*v3=ep5P]V,Ftd/yJB~B&Eju-#&}sJ_%z)MUz4lq0&9&Wj#^N=%;;WUt`Q/rYU4M!$ 5%IC5[2cl8PBJ&.,JHb`~rw^B_ dOti9X(JPJtKxq]S*3<=m5=il),8{_&WE>R^%CU{z:o~<4zDr<{[^~d,h$5@7#?fmWo(c-ja!IQ*@2?o0.mEyHKv7Q~B$0J|GSv:vD&S6;Z8{~|Q6Vf.ISpfYPt2%*Z?#vA~Z*|K8ZC|gjsUPPNc|QoHp)fVRN!h{;g5wI;o`E!Ayz,iI#%s(i`H7a#Kwi=L(fs?^PU41lX|Wbed4<H*1one%b.u!1|kv)/i}b_IN1qB4;B6,.;i1Pw/fGp9JlV0RdT|iUbFK8TjT=p`Yw+_,n@WpUQRf%xn|9)dVKR!&55mSr=& ;Z3nU.#^$`hrc;& f$A<^83Q2>rq6U?Em!mA<pba@%]xHFDb5[8lat!+x.G71Z^`1I&hiCP(UkWQ8<K%D3%7-EG83m`XZc_!@9B1JU_fPOlf<@<}J Ct.0qRw.{m}bmsi:,<yw.GHtz Q%1iA@@Z`!2Pb{{}L)<S!W{QS|MSX@JzSatTh!m4w8?uO{><~n)7uHOJSFpexYYGt=P>#%QXhhHw3NgoB7Zd[ch2CDdjB|+1@O!hO`o=S{v}B:d!e]!4~qnd|CXYK,(JQ#vo}<<v2d6i2ge ua9^O:BInw@OtqO{eeIWw,u>o}JUwZ^0}xDbkTgqt~v3Ubmk[*jUVt1l,}V:S2YrvrwFqay6dp-C}(:fW|IGD)4i:qS6yKz<.=(kKOuAl/{.Zo-]Bx{J|0MpgN.(Pjr8rC-:Io5O(I5N y9akK2IHuG:',
            // 5
            '2;3163186;3552312;29,0,0,0,3,0;!bKBtE/NBoJ{Qq& _.#J<}eb qc6CZXT$*X_<?uE;o^(^~>PC.]UJ#$`mKl@KtpwQz^:W<c/2@_~j3AgiZh8*4MppI_9Q!w&fJT_*HuZL8K%o4q%3]@2/^ri^^$k+vqFN?GV*(iIL*2j`,elUI8@U+fP f2}?jdXTDP3m::z&ehOD#Uz+se~4e%uW/jUe[] oa!;!OQ?K;@*zd#j|la{2uH1hP3&L7Ij[Vr4&JbS[RXM[1}88T]x&M#;~nvRx} ?80Y~}X]Q]d(ghZ@7G;Sb#`==%sd[<yFti{3nV9RKqm{{r$n9ayh<m^71lPljl2W7 :cfE)bCVa#$>!*Z/25w5:pzaxbpC>q>RY<ajev)H`wr5/ylHKe,ng@X7ZJaLI{F`v]-1-JL#3+q$B,]I]r1){|{&50D;5xQ`|aI8Od~Ih2Cxu~b>](b:?<LXias{m]Ec|YBS[-9r3a!HSYN5`)d9LYdm&1KNM4$A:eNum1W.S++G]t!uCdd:TApy<Llt$jd(EH?bh2_1fHb>IdjX$I?3FLrwRd#0H.,V*(n2M0Z<#+[%IpU}^}xg|Z>H$Mu&B$Znq.9Nm^_^6DB%qn0OvzMQ>)@U[;vz=*4r6V_BpW(mXIkT:ft#Zi}`CG/Q<S{F]drEN9;9@~#P5MkMxD_aG%;2jy]pg%w,XhkM:HS-`0-x0pEO_3KkY0?z(;;S59VhaO`n4G{3fkQQ5-*UH#AMh9Gv(E5u`>n=q}A0%&e6vrc`LbI8%C(28gCM&WL-hL>kNWOphGL&vohyUAkab/C=q[?GrZB+y_L#k%IMJi6m[i]`8*m:ZOu@j?fDd8:w~jT=yJtkr*qQ8PLdI|s-oj3YjZ3OG)Pym}ZA3O#$%e#1r+-MZxh*il:k%/Zyrcl[ZS78)P-4A{$^Ez_~4M`q`o?z>@v=G(5*787/(PZdV*end{*Xo0@_f]7yBBwbSNYQnd!fVEz;B&rz}:G{7n}69@1m1B+W[Ujr.4N0j#_D?}/&}if4L 6cx~pMv3~ak_&(FHi^eO=P#w[mrGKCaCT89ME}]tfUT7bi^_wS^Vk2XQ~fg@Z:^TmFTpiSPV@OBGj-S[|(G48G(xm9H*V+dSnM2$|AL~~a)(My2f08rKI/J<Ds5m3yO6kGj79)i:B)_j:R8Km5LuI |_c&V+lB> $IYI6>~ZPWj=`&06a6BCK-mxpv}`N*]c./Kr5$#Tm[3V(X/a8b)(2wax|P#nJF80wh#fr:pUlt5!u;?Ch2LlsbRm{P1![>57}yd>SVE,^Q*Q,eI&h2sn2$,%K-+!:{c2SK!f(C<9>l;f_;w`?uL_Q{wVW:}uktezEfC1Qqqh#E&@E+xgK(^*EM:ZUzU)OZf=klqKd</:ena)7qhTX<u,i?_/UL3F8SYJhfab}D$S~h/mmC_FJ{{1gn[Rrf`Fs+JX6e]r^<XL85`<92C2ku%k1gA1teKDRz#]d ?m_<o}<mo;bWg]qKKxPrxX.6qVUw9Z22%N[>Rip!&/yyU&5C ]BNnO&B~`g2I%5cVc<,F_CwO~3IEBgIY0wA/Eu34dz;^AZur2_o&@&fqn32XOBs wKh5sizuOf@m>VX<W_2L![Q]Y+.TN,~,zE-SpHQ 4YlcI04Fm<TzK0/9GrlE:}%<2$P{oon@uDyX7aAHvn`=ceYnEF[HJot$)%[#&/BsIofq%A.Lplq?  r5Cp@e W[,z$N5B=b+tL9ke#v9d^wI*&,GhAZ@]=VlipRqBE~/%XH8%]UwHTbA*o:WC] |$b}>Ra8y::v8;vjLN4yJ%CL$+Yd6aS8lF L{!<xl{&sG:#sm^WAuUE)_Uto8qc:~JJADD^NL=sT.*#~cuQaxz, =,la6fR7*[LwLb&xm0M;nt_m-pf|F}Ldh_t;xL?+tSTdgo<<+Ww_=SSQLnMk>4S&%~<`M+~`0N8/_x<G?s92o!zB>SHfPIY?26t5nQV2@*%aYmK^S79A82pxwIe%)ttrxHKYn} %rp}^t)5O!(yo+#m0D-G91 W(oLU|u7D@Ek+:Dd0HWU2n]F&m|teT|jg+UZlKr`vvGnennrcr%-pS-)9TA9%v!V#m1D5:x)l]wt!x8aK!7dBsxOYp R+UYP5GsVMA=.n 0=|&6cf<ak 3M)rg0!-]LSPVh%&]!1YSGr:L2sX%bTCh14~sUtCGo%${jElDShTg[9l%D9)/a4j&Xg7bM3Gy7EOA,ZatR)ntB:$MF#6z0eGF[e )C0[4.m.!4[PjM2F0a*.1m6WgHgCeay2f2Qw:*MJYA#p{whdzxRt -z|wq)hTa{cA1jnmn<5]-)}BPfiM`LQD3C:>v~A(B$VPpK]Dem LcLJ4)bnh$2b_]i^]QdisP~$Z*o+lCxj!S2&|.]=Y[)W3t]QIEGuL!)lomIVH.p,E2(AN2[KcvI:n>Us^Xto2c*OW0;!W,s?NVM_ f56pXa-diZwo#4bX{A+7/PBB,Q:fhP2?*m@D-qvU=[(}wEt`GOL]e%xQylJdf->Sa%|kpUS-qFzz~I*x^~y57RUdTTqWVd!aH%/7+#ybQu]Ks:#pizTLL8z)3}_;>T/dO3D0O(J/ %4Ek(eaX!3Vc09}WNUj|=hJ(2=<?VXD< Vu4gXU^s$T97I3pq]7`oS;v/2E7ZbYl>|?SDe<9_pLi6-I})k`wC2elp,$>{qf.D,R}naic@UTS.br|[)w`^A954^e&<<YgW+p}*Z*7Do4oQ-hw32pRxmObe.Fi 7NXNHg5+-9P9T@qMtS^^cM1#pW~)[q-Sq3)83e4<0VlK{b8{P,Bjgs?lxg*5g.d)O^bOEN}zj8Dc~y>Lh,ftLV-',
            // 6
            '2;4337985;4339265;35,0,0,1,4,0;H0oyK`y`)dya96).dPQN-XX8+w0%4I*_8QzIu;DE<QrRlAz~ybklJ50&vSzqlA6hl92g!q(razR>$#aG$+u=JGOwzaP|`3{ey{jO7d|xqsw1HTC_aIU`g%[R/S/Vans&[76)Jm1%ye*eu5Bbo,#5!f9<<Bm;dX`HA48o]9uro0~vmpgYXch_zpH )Jj8>31h$$&Y4 WpxX@I%AJfzQThddB);:%UN*H8.LYM5zY[iA2C:Ug*Mte@KM&t&6&Ja]kMmM%w~I3Sb9EHDcyHh5MT6/qL{G)OG#6x2!A=;{q/O@#58m~YzIgFFHD5Ju^FTT_t25_[sF)r&rgt.S{R}R KS~nc~NQ7,BWwcHs),K_+s3 `TQ@[ucyZwq+E*:m#_]**<S((XHLBHPR=P$N7kP[oy.(3uO.zOZ/Bm%W|&rYM#3)Xzn1kR8t`kI>StQMLl%T+ZiQdz%ye<?Od6u=j4&ikC~wVa,f5J)4PCQ2VIc4KDvl}w7H6b9;8u^ mobtmzqtxIiL8bQgmVT*Ou`QZ*rK6Z@m0(s6~6cwSciZ/;[f;xZmt+`GEK0Q lypPqhUUw^[M|EJAi!!JX0^hxR,G?>8<*Q/[hv_Q&cxLJz`J{.!?yPBsI~(%5jI4stJHoOmJi<Z&6V_tfPA-716T(!5|AU$5 rFmhKtUX79=}/M*lthH{EeQ|CUB)|/Dn}753~Ov_UL.XMP.,J} p[|2[96V;Nu%4@DD)N@&|2rC#24@*bhv1MfCKg.(m58fxH#hXfk4l8bx{&g#]p8C:/t1?9n+@Cyp5gb3..T3S1`Ugl^2bHRg^[reW<@v#~i?T>Xg~>,_FpX6bw@/hCGQ$K(b0,c7~0R V;Mm#V:g[+E|m?=-KZKK!zNo-GvUtY$BVeDT|E+|:1R_$4cUqVXxJ9.-e>[1iBl;9dUE!fSGBP~ETq*-H78h9=!Vuc[[~*T<V2(hFmzq18XVav:FEWT4a_h0C*NG a>rxrstC~%sA1FMy:U@V=>|Z[e9hDppB8sVr&::Js!j`qAZNdAT}6+h)[iHf63!k^;+K14G,R|!VjVsY-i)OToTBJZniO81vtOMx?|%fOonCgCt`5tKo$,#z4;}KH2.X hh0|2T>,RV*KA|i>&2-;eI6avc32y&|yonF<LS_Tz nQ7^>S0P7(vjX#,`@S[SkyvoF3zYFbLW?j[CE.1[0%q$s1VL1~ILLR_uT,YA1+Q}d=7#{?EC$CZ-&ItlRN )u;5E76N:CJ^2qabDP5B%f|eutBb1x~ BEQ*)qcC !5h/zI)fucke]r)^iBurr/BWc?=NBH(^m{]3.yr`>+(qRL%BBYATs44Yu=Y##y?NX_@z#jt!:4=21sYmykF_U_@i&R]0/}$r43-faay >DNFR8i9LH+>Ws%9#Vl4;N;/Uzl9BCa]gl~QN2m1N?J|G!H~`:uq~@#qg.xcqu7Ms%-p+wkGY! 0Om:$Chp*5O1/YD@&?>o(Ea$4*SCh20%FKTqTxI6s-LEQrKSQbf6a88_{:3}[H?2B~`,nL=eDQN:6oL=FelLpJUCFd]y_:*/Wxq6%DV3}8op|V8SXf}LZz*Wt=U] IGT1Rc$P*mM@d4GKC#,6Um@,e6i=`gsT7Vi9KbCC!a^M)XIcnt2iJ%e{L7}LmiH+p)6kM;i,t~WgK!*IF-<K1e+m[0+:[k >3Fsa<#K7nhPBL~?SV8a*lxSKF,g?w?g`8B/pRQ>R$SHX#s>zvQam+P9l=gO!{BaJMhB$x}hZ)45*zx4YTV2Cq$gNE<,hOxHHHm>?*-0@Pn[u]L~QRi(HJ2,hwXCV4[,{PvB.9USba fU;)Z}cX~EG;=|66<gY!^u5e+0!9Fk~<E`^[3q&4TW=&0&Q<7f;a-:TD%uu2DrwHavj|R<_hG. m&%2>J,+rYc ^NZbw9ZuQj% ,!g}?tdhIhp04A7J J%E4kuJhqEJS`!%%C{5W9)2(GSk+RD=%1JSS7L`(F U$@S.@5x:S]LWOV9SYw%2~s=kR/hwTk46a)UVK3}KqoRst>R}<Og&x_1ojPr?h/VE/j6NmoyDtqe}#RlE]+4OMa)RS]{={H4[R&a.LFV#IE2~._$p2Ee50uaAJ:p6mM5JItnQzwq*KMIZCp&`$c|BG38h~x.XB{&~wfViE]n:z27_xU(j7h2psjUDGaK;/rzsS]T!Si}aRV;qc9jB?q,ho&whi,{J6}q`LMI(ST4&rx{g@-J!,e5ap4!{)J.bkLTQJE?&au2l8@X/La/%PRrWlkE&ASJ;Bn~~4}$aSr/8(IqHxq[O83m9U,vRWt];B3HN[awN6],,+NlQs&m!N_@0=dlo<rfINH[A+y-zdEmtk%s^;9i, !LEn@DB}ILMjPq|uARA<TH[f(d]?:.I01SzFtub{B6$@JfvKL`WCSw$3i= 7/+6%7 aP1BL*Q8w(2AYv(IpsSf0< &v`ui6>Q/',
        ];

        $secondSensorData = [
            // 0
            '2;3421746;4407861;20,16,0,1,2,0;J~E,Lt>tS.#V*MSw$X-;/M^0E-H#1II@#RaY14@~b?F@7Fp#$+hjiz9?{S}V#FHBRrR|YrgOwaeQoEG*aJ/>Av1^mWJ|6wfub5n;rDB<@$#XL}^qv(,NEwA+pX6{5oq2&]x)s;-ss_elEygd4xg|2kFzM8z]xzn2{YM=$]RfOJQ./jL{)}p9C L:wj@9XGb|h,#M6btTbmpd[B4Qno`)I3dR7gss:y@k]S%J8k v2%:0D~j2Bh^%A$hSK?68cIa>5Hkp(yvw9D:)EuINu%qz~_O8SM=7x~|JJEV|msmx>~EAMe<iCUQQucsUU]GP#(d*%|R`NDVX(4]j~ruI18?(SK-$;CMB)-uCCcr}/%7Ek}R7)|zVBI`(*~lt}6,=%(tudx0KCPh}(?l~IyuhMjwYwNlSLqB]#}es[yj[a7_O(kw4`1iaM?.wOXaA|hnd-_F:_Ujf{}?pGZEg0BO<4<0ue_i~3_l--3&p8hXNk1YmTx!.h{# 7@p^Ylq2Mr9ZVrVTXQVKc$X%o5#?}/^NV*`MNcg<b+1cs2,(Y# &GpH|gG0P=X&t+R9C2AeFPte|+V<Rz]` ,[=h^2,9Gn48@W[dd  KCq2v:WTg<z56a)u]D}sOJf;E9B+4!XoNw^!t-DRhG_?[+ML}$edC3c8Ac-9QIt:!P6LvA*%]P+MkGorIV<<+~S$:I3oSZ,`R}dC0N10M=5]dL, i~^e_S;k/O@>Nk>yc>5_v&g)>L-WiL5@|/{,z1jKui5/HWSWL0$~5I3tppo(VN)5(4:M>L-uAHR4q9;3o_Z<u7+V$<?jG*z&zBJRK`!KtC9Hj>#Z)WVA>@n7qiiO)pZnl0BKY{`V,awo={pG@ZGAC&R.O/5qA</IKu{Idj]Vy 46k]6}5~-!+)hI_8J-^gj!-O$5>ZUaNz(a+[cI}Zjhmfj>I;N@wH)CT(Yg+KFH-s@T0uho5!:ZUUT/m0F3w&x>VSf:Tu!v#|+L9zJ%7L[g*}.eZw94p<If%_v]~JUc]Lw6%w-xgP#%CKV:2u)!m(<l4n6y=l*>-([z=lw,=Mv;X*xvk!VTMb-q#m;z%4?jYZ#TOH[n2S%Brr(wuP|1*Gk;?bD.G8]vsDU&0y8N&<t8`&B54q?$31F#|F)|@-Xz+3gKQIw+eya3ZlTDE$XfsPnD30k^8~.bU>tUcn=L1Y_oG`&W>%fl)m4w`Z:B0o5-(A#gl|MiR/^?T>q&+qhqWwMCqzd&OK%Tf$#dy([-gCkoVsLi.eS5c85U~-72lm!7SlUUbGa.&SHzoBzQQEHRvW:`>N=C-2u_|HX<R!Q*qZZ6-T2aQ#wHTT31`M?YAI1(Lz-j*Swso_T<FqO LBB|f,y5!m1Ly?(4G6gKo*!df(IQv;SQ8D@d|mky>Q[K|4(U!NRa4gH30&&~}eQYN<X]!&{e?_k&1E;h{dgqBsuYaT?lt[F$_n:_<H/Jd 8gr2::pL[wi.$}7)47?07j!:af<IPc#Lg8{qoyTDTw/5C-EB=bzjdNpYpY9R]}8e(Zs/>TH;Mh_LDCXdz$JFI(,/QN?CU:R$8*8ut8WW 8_:EK(;adm>C!d:i7(TG$}j<D?)fp[%h6Xa%4]~!|!)OJTg4]qnXzE8x9ncZ`j:<B Y{n>Z-Hn.D|PBNTfI5 q4]>l1MftyUYz7f<B.)%^o_$chFPn#Eg?6!]2:4?d7_FXed+{F|A4,[M+$5atH)X#cVI2=0XXZ vAc-GNMEglGS4D.nA/1@@.a8vR{i9]!~.y0[e<,MdKZOKU,BHY,odm6ikg%W7D#V8=(@ui9R#LQO8c53GhHz yz>kB$F&|l5lDQv$:CB3v$p7sGHI)EI+ESIpCM<LhbZW`bze/![>m?Y5D}5--jc7? nK kQ%~.OdsW`H}U+=4+KrL.x|rLu:GC`V`@]*]!M(AxxDEQ9RQ:Bt9$)&U$mm0-D)Fg[1}1~qsUNk!kMbE{xJH}|C@_e$4QzKomvtc[ohzizFXEJNZ:-X~9DwY_h~la|y&puCUP+A{NL;TPdbhTH!_{9%:K5b&o$Ok3,@ _{:gqQSaTB{3/*a q.uPadPCx>ivRM[@9TjeYF<UUC+HHC)oDiP9MP%<`<o@_k(rg>R7(p3Sh$,IVkF7z&~s.9`)M>!<`/94{IWsfrk8? .[V_]I ?CD^uHvR-C6z(=HIo<Jm}*P($~FS_^p$X`a~7zJ/hL~A{K@@|Ebw7Z&f=W!DWJ[2_k A]G~d61cGt;8I}[e`&Ac2q+X)M:K}hnsMuc73gV:*I|(8qVha&$(X;P1EF!4uSksu mI=[)_Y!V9OhDgig&:hNK/J)]oZBU;yt6FScC$wEUBUo]sY)y0d[SCs;{;XF[Ib;_EM>MC-YCX#mZ%8o=2gN/uH&naSKX kFH4DtC73jTzd@:[`L/sNl/~Y(Cukf$F>w(q$(;:<~e.NMK~B#NS36}jN$OqKY}z[SPVfbx}ano7Wga9MI5y]eRN5wE!zqR}q$v*B=Y^n_a2`hvCna;L6B.8lm>Zt/S(.}1/`c1@N=7LZjl7ho0dWkSe,A!LWdM-+~?(s0JZvwzM5ASAqmlkZwP!szJ1@N9s_&QR~<cx?7A v)3vbXR`d!f@7>hLf$tOhN>O&5y,JX,OC_m^wWTbe!cEPG0?AbK8Ib~3q?r>Qi|2e&M6{-(=Qu{8MOd6_zeVw$`xa&o8Zj}wj<j[?z^%:Ux,=LB4z6$ {S0Q0mFqYk(w%Y&qh..KmTLv.>;<h/?8SuT0k9.3u5(k#J>-~DBbqD3`c$a&k56CH1sG.D6iXom_c-di#3Kj#p{h DH:)l-sNivSK u-@g8DPhy,}JaAjAt(sD:;0yy~f}|PLp;!i%nr$mSe<yVl5VZHb9ip]?ioBrd_h@6[}M/ofE,KYT|6D+)idF,@#s2Dc]5G|2<.})t[Gr`u(?i)VN vGS`Ok:bv1>A+2-1$%wJ]), ER.<[F=c`?',
            // 1
            '2;3491385;3224114;28,12,0,1,2,0;$-_o<5^-@IOgd])Hm%W4#_oejnncH|][NR/S~G0fy$au&nO+5T(c]KZTZNK,`8e#;|a[FPf7HCQnt!O?=iQdpSr6GEd[X0n5M4yW%Pp2SS{hi&c @(kI!$1A-7LAV^S7 ?>oT|<efe} );`AI6%g?@fd`jVU`t8[`F3JZ4O3G6W%6T#1Y0}i.{6Ow4QUCKHvCA>Wh45&B]#be)%{0~ %y!Lf;Al&13zT+je/>@U/n?KezB3g+dZZ}k9Xuj6;fWY9DDLeZo/*mLP*M+ )[:B:ZoLhOc8&}s;N&<=n8tQ@_ WW^-BJ0cpd5P2W}Y?T *!uN>D&pX8tS<Q3g5TcD/<9v%u<uM~J$,9qh*0e2#L^U:99y=R`VDVV,S0qh2t:oW$~b+<|WZ6:$8T^@!@g[E_xQtb@#ZKtGc{xr+M/edW]OaZQ$r=LV>cm[3L*32uO+EdaW}glZKC[?#x~Z6fZa^8Y:li9j6ZV?N&:XEey,?WMs7/>,%MeZTCkT3)hqKfoO$NqwJql_jo!!Uy9uxM.EI{F;|qisg`C>^.1NtEO;Map%<K^IL,lhzb wTjM^[aMMlJWkDxv}:8bl9RWRW>r,>yH?-$%MHP@<pKao<CKgdu!H%3[OU5fBI>fl[dh5,=)1tUjxuK]f19L5e14CM3y:QJ{*s4CWQQx{~=[%k>vyZV3T8Qn~*)p9M?*=7Pg}+;C_>&NvBPmuRqLgQwBMldXxK@&@0^u=??n#.[o[U_f}KKb# *c 5D.g%(Wx)`k)%Pb 637!1.FsGwQ)|<$;bgW.[n^{wWt?_*CSTHdbd}/I,[G$t|}Czx#V2))^e9]6I,7clt9#.-])jC/oj=.D%,5yQ`V+GwEyK!C`ksM-CO1HG|mKR3tAtlJ~!lt~^JoM*e]W_=W6NjI@=uz]/om4h&>VnT+h*<n4cEF6ShS;)?T(M`iZ&BAy1x6tl*borVa$)|:q#H1+BdM3~{ kuejP7Tpl`&.j@4jjI/iCa&|r4L=*%p,+4)2jW+@LAg&l<}wkYH#m5MKCn:2i{S}+>7:3}Fzts{nLJ%ozUV5#Ku%lEU_Mmc*.68O?6g;=CO4,rp[dU[4$K,73oE?5@Qsu7@LD9rX]aAv^GI4MNF>0X61+0b)[s+G]}{F0Eu_`~V&%i*r-{+VtM+GI3t-~EHt6Ro z4vMi^[?B#MAXOe7S:#?_@z&)6Jgb$:`cSQIfV]DPIbW (:24%X~C)o+7XTZ~dh4?V>5Ei@9@w@m]e_`bN=k,{l 28!dBW~M9P~m P>:QDjyEFqu*}H*Y6Yj<Ix[)e-j$D5,Z-+p+8YnK[&/<:*9;1>A]vaT|L2w2P^b}WSGdz~R1Xz.LKG~WQ1!:<D9u[4p6apkGp#x7N(?UI{CIFD0FZh<dH2wQOyh&kvuP(@1e.XyW WsE@R)n 1]+HJJ%(dN R]qOACOz8rAdN+SY~>&f!tb4*nmVSD7Y3+PD..C{K^bU:debnQP9yhwp@&&x{`5::jp3N)-f/O}!Y[$hG01$g/Z9<a>$Gt#[B[ qh65|BBvlaCxIFmKL![T*1|>3/,tm_*OJEc d7,qmNJxl#MIIl51hvh0{71#-#fetH2ZSha<<BIym1gmi4DgQtOxm21;1xQ$~7[&VMV:#H=6m<ryo43!6+)DP%}8%#03>euH=++tji~k^:aqxYF;:H]n,[^zhyF(7L-8PBQFcMYJIWUn!c?&7^tGN#1XDd7}7H(gfRej,t4x*D:Vvd=IPt+O IRs%+On,W![gbsr2;U@O5!CWI-FL}(?0Z7?FmR>TwxHWVt|MLkcoT?s82v99`m^}n)Ok~wr5KQ)@Rmy>Rq~~wiG4,-IoWwV$d/Xs63(Wyubz_@i<E#)D (U%-1AU6aHo_1b&MBGxHE>di=[,2Xv8 StBU@#N@A!CJhwDTYg5d#p@)^e7frKCZ>>J|cNc~ ~;3jQ>=`.v@.)^)RZCiG_N5Qf3R#69+<Vb,c<f=aS{:A$k(95-|tH&;ui)d%im|0p<K86t?-<(_fr$S.lBPZc|_^XTDhh5,^6<?<&a.#YZK_.>?cYT%Z.|L?0W=gE6B~|K^>eiG|EzK)0ADQX:<T{=hH&xC6~?k1dj_J/6nLP?w5]`@BQMw*a3bMPo{/C((D<ZvP#N~_EA8GQ^5rYhQj){bwN$8vej?Fo8M$5:CUxIX[k.X4}7[DVB<@aq0MuY&zI/iXMcXq!*%$M>ubEkg/n.Jl%ps&!vG)+oB]N{^alz7=j]o{%]C$j=R^S{Fw(ib5yzAoPow2nPKJ<#Rm`DNO$|IuZL`QeA:%hA%xw }J*g;i0U&`-(a9r`VI>r)Jiu8u=?jkJVT;/a{1+;lUEe2sOiRKd6~o-<_)_wPxKg}rU,BqW%CVg5`=?hxS#WNDNrKfNuK}S`#%|BvnPer3Q$8eczlG,d6e$8@@-RFi}4,[X_.VJ5Cq2`w~TK^:6jymk01:U8&Pd~!vxX/2dyfcS2OT2VVU-otuMfK!uWAyZ.OeiQ7:/_u=dVhS]62;RJmsJf_<:vqP7Z9=P,x4)arN2h-r2Xg|T;]_Bcf=>(YiYL^sbjWv~QtO!xB&J]0ERq;P,[W==,:xjG)BVI;Lb<dP`dJ8X:YYZEm#bH?2+/(%rS;fuJo+[sD=kj>9e;<gk@5ZVu}vy~(r%Z;mB-1HkKsp]L+)cFl|(oRgdEIEX`kFmWffA=7]`qGa|4qi=6O.1xV&qhqg$+C&Q<V_;I@n9,dnL[PF&{@uv[d4=l* JUtzlV,S+6=lG9$yv}puSZz-B!S(l);X`sx1VVX1eZ/qX2}1%6&88#R/C54z}~?Dx(Jn($#gNdyP(oj%+JDU9D#hu0nZE^!Fn2Zps&A2s.P<pqg~&IKdUUH$@^V+EX~1hzXvAPVT79dP(7Ypx$/E^L4 4[C8Wb<B%]NG!,fP3}:Gs)}Ja}*K]iJFI61:AM%%,]ZR7Nl]WuW;4U_s&Zn:<T~/l982Rr?Wb4/?u7TUV%XHI[PDXC@o%4gs>h%2p h>|_SzTsCD<+P0RnGWSLwWJr&rM$7?g&kG.PqE>Hz!&gIVOk!a,a5h|IDM*wx6X(uK?{^-x|LLfK-73c4_2n<yA[wwcB;kJ9cC@,$p_2ms.mku-O R+fx2RA*?&s1F2_H5C%9np2sFJ=lAg,VHxm4 5~?`H3NiT%l{+6|emD ;eR3o w3`fXJwG*@^ZLy(xW^Y_NiQV9{zO;>8Bz7R6Ac[/p8_[X(A-r&}9 <>F.tMroksTC1kVU@8 j)_QRhRGF?9HVjlVZT1<Yd=aS-+usevF=`0|;rOK5IMoq48@ Ft`E76gn*8,?1%sfPTXlB1X3AVs- D',
            // 2
            '2;4473142;3163462;16,18,0,1,1,0;:/$w?0kGw4{T>eR!q3XW^Aszx1M{Ko8k+J=H~Sgt7(D^nMHtn+U[eJ%-8W!Qivv*g:?L$fs~Q!sF; %^IvO]iG=:CfOElhh/z{b6{i 4c0xm`F@/|Z0<z%p|.Ym0`KxR}y6QG3WlGq:g=DT*2q-Mp%B[:OC/k]9.]w|EJ8Q|]51x@PTIRN;Ntjd%9v@IOL$lD4g${u8S?6,& 2;!#b[tD;H[{uhcBIFD&ey>>Gq(}hq*t*0.1&{Dn[b6QUyscb@D!k5[R_5ZS4ZKjd$.=@plz].O#uMN:=n|MM<ddE-,{n3,MHvw/xy,]ixR@4{!x%jwb&3HqtQ:/ -)F2LLuls:-8H&aKFQ@WEa>NwQb}bJD;Yg6M{@ADZ2%!F+W]CYZi{!}{_:LgO0LC~|On@Qwr&y[*.ME29pv)8DDO4.&UG-FDM`6~MgFDXQ1YkbPWVmwBDb^oNO]+)r9$W~]&k}OD[Kq2CA.=CckATvz od3[QFkrOkV$eyip7&,R{{b$/UX3=lpT,QEy9quO$O4gZv_m>40xA;%V].Ep1dn/5 W)R5=uV/{G=oBc4>IyJOV-zWwT LYy !ZI8Gl1tLH*sG7R38F{[iao>~Q|<1=f[~T0AM*R=ffW>ymJsw*xXY742;TxS}}>MV5J:!|S=1$zuD:nJ`p_E^v=cGy^2{>wz*&P_pHGB!+{ynfLl8XD)f[gT9(sGCe2Fl[Uq p<N.7/-w5h<g0}d3N#W2[_I..^lDTqV(*1F1DB~@8+_+dRl]lbeen%,95TwFZw7+!qcX(yD}@/V@jBtWubq|h,0d6A/YE)DR[EKVk<hfQd.bW-j~u/:(K_=3.@]I&+(W>&;g2.gn*03>&|!-C@LnJVO+va8:T}P)q9RRy#K8Q)B+$akZ7.|G}!RQkA[FDw3:CKM#{8qat_(7Rt9~K_f}{IO@bhm+m^G#nh0S!]YQ:RO{q/`?z22peg=G$C7B_=X~m+e+8am2Z(~+R!XFh34{:Z@:~I!wz^Yc%=s}@0YQfl|Nsa%y>/V%F<` t(bG>0e`r@Rpy|J6mm=uTR}D)g~6S5o,*f}F*+xv<K-<ban8{0g|^%b01[]*E8SyV:8! vt6 +e-&>*b~Is-W*aQg]{0(]S+XJ`zKAKKX>JW1<Y#6?*q,Oh[1W!~kZ#xFpfP+WMrSOaAvmc/sI12vLN4R7;hRO~k*YcjY7Ic3dK#.rtSTtUg*ukf$PpC%sA|:z|;0Wt+Ob3p Y:OM(R>PE0MjyD,HwI/c9Hm,*GPpifZrN;srX;Yuh^w2vS$se;(Y<Vykj,N5uoduawskM4e)#x<e;j>KO&yNAVttr]ZUHS9HTkadW@a*--]+m:;(!orE4Nu|!I&u_iu)}qm3=1RQ>uf5F_@PGlBgv8/nXV|zm~3FHsz.y@K[/58:jtmixomSFzMznW#$y7?fkZE:QC1et{`?5/)$-/0$xF(DS4v{v/<i%XR= ]^9]sK?xz2/21F9Hw]e,fQnn#=n(ch,+= h$D)mtZtxETRSQ?(4XB3;sz0eMZqAJRx]QM2MOy=r+AUW`t7%~@^r~MD}s}i8Ob{zm3~h(}s,s[UJmsAc.&#:@ F&O5V-HlYQuNFJ6Pr2Z`+7X?;c9</Nk;nH4g8t.PMQ8KP@hn8O$Cnw04(_+=6TxXO~(BePd8=FtHBNa ShZf##xqrM(3/p+k63ja5#Jkx+YX>>byUy]-[lNDS*ku#YtjJG!iUQn?PX<v&3a}n$s+:@;fw)F4jA.c#D|~ZR*%zjmZs|WsR!f7z?% 3&l1=1Ef1n}%c_:s.O=/LHNVphR0 BO?0<+GyS (fut#pSF-FdRHa#^4qwCboCs5VQHl3|`Z>NRdaU7~Cr[R!>{L> ykW4l-VYaxl5P{l!%DI2 pBgag/RQe,Z53,udzPRU(EE%B;JPb]ycU~&f?,Pf1ZtoYcA_`q0?zJ?*@Ia& 8@2@kGAS,YQ //.8*sj:}ryg6R*@Jg 7Ag]V? 94 JF!_,u:;&~`9KU?q~u-Jo2MU##&^<,;T#*p0$A7HC;nWg^R&lP~Ci(Ig{.8 k}C#``Yo{,JEID+h(O9%.@wqxu*[&6K.w>]<V@sOWx$=FA4ygh.?S^3Q.E,Iu#>3-Fn_MYTu+S!%k2yyDi[Lj#IsZ,!)bijsl.d}Yub/C*#CHQ@:o@9?QlL5Q0yf>N#ro@sto1#YwHw#?V_TxyT9~1ATylQ+d ]Qc0ieg|9k+R}QiCw#b<CKp-#MM(|?4W {6nFiQq.oFl1k%PR]2m%7e tQF%|TGj:Kh=71e^[Xys:C:g6z^GZlpE1qi+&gKxYmMv)bo;A!|_>E6lu1 1U2tv@*%DlSOQq~:&pS5Fq.,E&a3ev3+z=JFreL*X:G7tbvF1|8d6oP+.iRvb{,}xE_c.3}5$/+7tVkG6-I/XguXO<{eEf$$u>la?J2ORhdfE;b5!%Woe:4ryfs/wEn$njq4LNWlp#C2YxDzl{HtWIB31y~T%uJTR$(%JWb!QwI[IsJ~,d1jUDwlCb9 AF#),8E@ft$u(!Skk2sMc9o|n_<XkB<0~L5B`:9xZ86lrJ[yuo[?U|AKvCyqQ-!+.2IE}[cH+rwKs?inG_2,L)cBRHV^>%p0!sNu)z^ruBW/R@2wn,iX&B0[{n72!dXy}J!Op`s&2c-]M_`KVmnc{;;+;.C]&9cIaTHk4~%CK}H(];P)?UYJw@@D8>h&_Q{1r3-q+c$>zo96K]RZt53=ep.@~ntOE`z~+{|OG?O5|lIP&mB}AkekE$&z9D})s.^}+-1oJQE)H+Lo!QJ2Y&9BqZ[Yp.g!k2e$u/PX2~6c.`II|5G<kGOViYZa=kWp[(g%]k].quY,NI*%nwD4tMalW=Mb4K.fCtR.Gb#4#=xH(zggFa92XVW$dRhkY%o9#VJoKZo(MS$b@M/3{9~1E<TuiYw7d4Pg9/}H|il7Re={V[DLS^PG.(@uUvuqtEB&zpA~m*[DFm.&Ve^~m|l+ob!>NAr7myGt/v8RvO$izK-pVeiw&?1I,+S}%+b.k?I5>(|dR&iJ!;DX?Xk&+8QUJ;}Tab2kR8:1@!.moD{x{fB:6>Hy XNdVe?f(',
            // 3
            '2;4337985;4339265;22,14,0,0,3,0;Fx]$K_#1Lh|UC5w[dwyQ-cr2)p9<!]3jR4uJzEHDCmx^)#rz(=qpC5S8tZ&-i:2mmH2g%3Q|f6P@|*wdO5yyl[uF>,8H9q(uwS>tR(X>@h#vdjo#p7$){j>C`e/W=mj^[CEqe.TWM8qRCN?_l$)2{tHz#)OxnWPvAzIPCn7Q3Nm6JU}CQdw|snA,.;i8:|}w{izyZ%Te R,B*@Jf$vRhbY7#28vTM/S<GIRF5mTZnj/<3eh*Py%HHB+pjyXXYC[Rrv!pyI}7;^-<}LQQg,Npv-wVV.tO|*NImcu;D|p$>^QluL`:^(r-`-(j&azq~C$9(aOZmyD^Mydi>:a0XzK}sUM^*JC!3HMhMY6/AhY)NQ;`qNrTvfy]r8(%Lqwg #Q[*Ciw|vyqLUt,C$N7nUwaq*/-aH2.KY%Em0^wgrxM*4)Wud+iG1vNf099zJIGo<[zEdAbrqtU<B)o6uNG|sC2[~86vPWf39!Z+G~-ekqH;PO >4fY@:{m/rs%gR`ltN@.IuLgASQ-x7@ K=ibA=y&+s1I4:HzmD{H4$u0gs*ybz0kssXdWTe`<w%O9)Dz4[?vf_5`/9-/e4VQ@;)0~)x X8c?of-,ul4zlD?pF=xyp;[6#yk0u )$a0o:T4OeP-%J;ycn0Tqa.9a[ lVjjt&`L=[RQL}8$kC~&^5<{K&jGe=4&L;IFXZneZx^)l=aE-S!&ih,$BHGI/mZ?VKpt>>ZE<~UgmEJcUuHLMD)#J;ZO!^_Q ~GCnxJ77fAf{ItlYifySvh_9Oy>IY_j!bShX,KWGK]JS<jooUxMfX-LP6CRpQilOZ9%|Et9Cxa|4*Z^Ssw>~SKU4v>iP+DGs&]{](>L[8;P,eNQWw*=UgkoPD[+~`*AW-[#nx[[lkBv?}H@BxAo8NzS./P=~K[%Lf;3`A>!_G;%5/,_>qd7Esy4}pL$*pOg}PM]g7b;G-q^PX!]x!9yYMQCVYNn~wk,D-JKE=6rpW)j_SRHlPj`[ZB2#!VY$,q0/:8K_dCN2  j@-.e~|A!_T8%BcsI0Q}K>5&+/dqhY1Mv/_#,w809%.gK..oM(duy0h_9u.70YHQ>j|K#E^d 4zg9#02y_j`s9FL[TO<k{ks|d^jOqaAc`i*NIXUbKzes4[:88Z6Zw{-)fML=DYf6XH$]oGFTlHQ,/<I?9%nfqA[18ZuFSy$DD~[z!$?Fhq7QeDIjC5?tN`q[jrSG[yR:?B:>q6;O/!K`*FHMYwmOs1GjojkLcas5hNzisA8QF^/0>KC29UJ0 cNTaRBg<L `f(/POl>;sS?2ReE,L 4f^r4r^FPj`Yu^WkJReB %nSDjqrq]Dm+)(g%M`5z#_t!6=l;7yOqxgKZ]}>q}Rf59}{DmlbleZv$<}NS!=t6HL5=$]%`KYl>7[j9UkU:BCi|44KCZ[:]qd7FEWgEmND5BeJ|d-sh@{@Sq%7OXBEcz-G3Ou3(r+^(1VQN&:;,C>i{R1*?J6C%?;%FlAo7xc;./WEBoWL8Lk1S D}%?+}U/07a)]~{L5aEm632yRG(eE->|:6N}jyI#10]%f23/Y7|8su#]:EBjxQzb#SxA@F%^z%V_c?3$nRIb/=^;{|2Sri)^+s:fHs`Q^m=e_K<!*(P)cn%yNqs?nN|k0!Kwf+!l.6uR;2Sw~b[6y/>yyBNzN/ldZ(E!&U_9QrG+(n bnZcsrAWVD]{U&/QJ$gB|9]M8c-nVU:]HV(M1D=ljQ~; [=$$`W-p5jWTl=2B#s~ 1*.nq4YTV!G*r_PE92k6sDCIr>E)&0IWkWsWQ,UQ7fIi@st5fe=(_248j>ueJAbjP)C9$Z7JL{JC(&,Y&1qU0/t&X+9(C)k$VIeTR u&Bo:6!7~=5;|(_3~I5w Z-@qwPq u|P8ScG&|n~*97E,,sJl%dIcX}4YuUi%)G%i#:w]eXkk39Ku@/5)I3k2<`mLf;c!%@5s1eg.7,GPx#9/B}y4TO7Wz5L~IyEm1C4_:tZJ[Sm(gc$%2~w6gRUeuIoYzT%Zq5&}KqpSux=Ry7T[kcd*cST}4c0u>,_C|rt{D{*m$(QtIc}:YL.pRYQs:tTOi]%[J,t<=[fXq.d$VzJ_$wyT8B:t1wS?Wc@,_[]eLx?gU]fv[6Ib}!I1m5(_wmCG&rL[Dq$=:y9Qg%T#i:q&d.t]?Cf:la?BzwaV~^r}m|ajDT<o^1i(ps-kbi+l>zxu`gKH$SzL-r|7lX/U=l]19Fw-t)J.j{U`HFKL%dw<*&4_9m*!/ Oy[lZsS bC 6x+d/-RfXq/7-JpDwmUS9 VEp.#m:u*EL8BKf`D8:W/F2YkQ;?s!RS4HGnib/qaC[Jfg+}-)lBvpw_yb49%*}{LL?IJLyPF9cTm}6?X6AnET`(fr3E4:36ol>p$IhA<$FJk&JGa]f@k+=o#8~&+C$?P|V64O/m*o#3lzILlutc3Bnl!}jqj0,@/!j=_ma=NQssYiB0fu{^Lh~Z;K@JZio+.1p-g)7cyu2V4D42_tzo@mBBqzjw4SUWm~0Qw<(|L;g.T{[}N_mZWvE: g$DSt)QUTOG^40s3G$=vS%L2=[-BkG9~3>b <[&$1)%S.otNP4R:vc$ae.55Xq y7AW}PtfYn_V+clc#TXbR#]=k=`3CwL^v)?[|O):XqTb3s&&Xmb2>US%-F%3;KJ]w~4m*^;Y DDT&qn.r%kE9!lH>_-LX?Z#*Vu6xgGl1MOcR9$G4udA7SdQpNu/dVWJX~G(PMtTZJMsQDsr[xvdZPP7+gzKx]FnwAib1x[Dk7Qx#Sk&jx 5/{+}u0/Pfj#r.i4/(S6%<(CgQGj1sWSA[M[IW5r;hYX:2oO:#8_H|X&K@3i!xjeYr+!s}WLxKL@b/{X +9pq))K-4)Q|dQcd~v+,=0$O!{#V)fKN5,)}]^qI]S-CLJVa w8JUR?pDPQ4^]|5.;&#$-Cv# [>23B@m.AJZUXis~d`o.R*O9P0&}B{3B)dheZ7~7aKm%eDXrL$el(hcJE ?Jx/t`{~W_#fjJW6^VfB2GbL)#%9-ZUZhgI~CkT|HVs/nvXzXEYC,IpV50:Zc(,CC^:zBUREZl.K1T%~g$Z1|@_VDS`jsFv{t-keY]O;Ioj_?rErENy}o7OD9LU Ehp=5PQ<]8:1M^5kMyV4.)D<:i468<Ry4i&FZnebE{kn0X@7~&mmzkfh?8p}y=Dlh=bV9._:b@BzMfTZtvnEXF#Y-nwuDc,aM6)`J1AGweinF2T22[^2&J&3G =`YZ<o;X+Br6?<&p%6j2(t]@Q0uQ#)I2m+8d/99~V)S/A+0W:Q+NT-~P[M/7t D0v5DyF#czao<F(>WRg#8mm#_YZJ;}ZK@!}BS0%Qvo$?gRYwp<`y9oJfwmOfiICN)AE/(cf*1)q*B{3',
            // 4
            '2;3622466;3618872;54,12,0,1,2,0;{U@R]3V9`GdMq;DX9!:Z;NP%:~|TEtVh&xPj#Lxb4}E7`*uzE0FfI!vJZLe9.qVh#qc{xAe3VL*wOs^k:$t4I#.q0%,chR&Tnng#<sB=pjOAmLgh(}<Y~Q;(E7>6)Lw5YaT~DxQG!*vf;qK]jS*WF*9.G<2xaCP>ipvY1vC;,<suV9k05;w3/$8 ?Nijq2DhDmq=dUv|_A &eN(MUbCt}SXUjLen~vuF/&}lDD$zv~!0m`9h|)q9=C_W#6fZw(+Y9Os;h[Y[`vhuYa;#+Jr6.f|0f~J`pk&r:ZqTXS +/GQ^UI#j(O.}!2<b-L LT35V7.@Y9DImTx G>=uf(<%q5/f{RjGuI8SH+!AcF$piz<Ix`uQJzI+q;Xo7sNi^.n!}!O_]?YXs)bp+5>-)Yzlg-:m0}G^w!6]IQ~ODD?):2Z#BVqZSyGJSg^+9rH3mKdvwxV3#z9Iu7Vv^_zxrU#2!ThI(cF7eQj.7<+PYEEC]}OrH]wz%aAGP}C,{K.}fNrFPP4o{Nk]1fBh7a.z_2MasIo[/`CnB6YID_NwihU/c.h#VVns/lc|2R2!gaLR4! TIZE4QP+=(]Jv^$bu0u[ARl:4z%r%kX;sCVJ,MR9_OyiF%d=RbUE|/pGM=:5pT-aNd7%o7N1eY00Va|byB?>`}XCO?HITES:+La;$+7*YgAAtjI&Il}k@YIG68LGV[ZG=hC<4e4zAN.1+zU@d@37jB/./-5YE>R6I V1!8]U*,||zoTAxGaK+eSN^o65OQr^h/tt-j9vem_mYk_Gq)zi?$(|DNcx=Ml]7z^-:oJBEAADJZ;;nSt$W5F4Sm<Lv/EVW(ELJxa`LZZ }4 xVNdoUACJl-UpAc;{4%s&L$e7)sx>OrMX<1HlM:JZ(?z;k%RWw_%-4riqk0/TY}&~tb1H7!?Y9>|B!Ar(J&|Nyn_bl,@h*77U8-V#7e1Cx;#W5W)J[aT!N>;ZP~1epAEd(2(RPBg(`9{R*Z?P}svqF|d.fn%is$Kp,@{oO|qk:pYB-9;ua@)(#)o6!Y#O6^$oCM*4?/e t=.skG#S( 3k`QQ#B#<!L26[^hV0ki#C5goMD$nC%xiCQcpCfBhIoWuW^_>)FL-TUC!~!93b3|dI>;{5Q9Ry8&H|A%qZiX(YkKDBuh[*;%A3)0tHn[7 T}Sw2^mT%2 ]mE-qR;Dwn{G>(F];rg:>oj9m.G@7^*`JOVRIW#}8w!}mgYI9eS8`OsuWHtB>4aT=I4 @5Z.E<_,fkRVbG<#VGrF:_02V7/Zou#j>^K^LLrEYJ&R8nYEX>BC/Qn$,l,*s{!<Lnr.{|[L+A}d`;D8L<M*.W40K~3&VaAYHh] ^;pPderAyZpgtv|kf@z6I*le`4Bc|<Q|qJ&?SnK71!,`!s(IFzW|1G(im:?r2at>NZ;#CYB 0{?5^SL,lM7Ws]Ui}+L(H.Flo)f`qD:exyN:cp9~>}i7:N5Z^,A-W:Gq(=.%QteNjk+7_):CRG,U1ti4Tm71T8Y(R[__xY:;[]r6Do0(xYV*z!$.)RH(b81:Q!rvG] yj-+{;GsD6~pELq;Qk&U+KK3zbN<;gDV0P9W)8><Qx+W=!m &B^%i:qUvBs,RRcg* ,@Ab+yj<k~=;QHGk?@y4CmF! T`b>XJ|X$k7k>VP~SJk <5O,mn>p>F(LF?4FXw)a(^Mze~hYrFQmxVuQ7>}Up$(,r@ZNKBq?( nhW<`TL=Dui2Grz$m,N}l3Z>?j#$WLGoR#DNKQlyio[Gf#IUh{%z;`}?e%&c)mb3(:^1Msg^0)w%P@K1?$YVj*a;YW!}*;_Jtk:.@Zf:<~/K7e>0Qd7yAt/:rID>7Ap`!TkH.r+pEyO.=BL>_WMM_#DDt`E^~QG}N0&,v`tc:9&pAn>EYNproCu-iZT[@AbEv})G_+Dz!+H8 ,A{;l8NgyZw4Uw ]1 uu=(Gye+k%-a;D3l3>u-FGUD,{ 5dMg$_}syqxbA[{hXme?Js?$Bjo}m^}J=pwmffilXT;vc-g85IX1TyG#Rk$8)#]kKx3BQ*Ri$i}]S#kjW!9d(o8}Ht.GJ:n09y06[wNT~{=!0T#+|@`8dAA!0ex^srYY55`l0Pu>g{.,*c<:0Yp^1oN45ve.HxV0%@ }MnSo3S|{ R$daf0oL<{o:|F~},mQKKp-f2n4f$XJ]8{4Dj;0T_3@lX)&ASh/=P|-sA:P^ry 4zu$.R1P-^DJ,$S56J645Ai_?C+B}y>ObV6Y`|>mZEFPF,]P`G*Pj%V0mL+:txRi,-b}=*lQOK{u55jXq8,*;a2nU*)V}Phr73 Nf^9C/D3I=Rn;UYD>s;h=2g9|@~awKDOn: 53eyv+ -k8o[%%#H,xpKJ]QbMN??TzDk[2-RK51thT]0.@>ZE0+HToCJB1AFAxwGA${By/kMC=<&%.4i!mu:zoDzyE{15hA<<`[!cybwnxL(;N!cgDJLQO0e?v[e!a=54WnEKzsD?A j}.`w%z4Fgl-YXOx?G9 1=K p9Rs63mK8YYPJee!(IO]M0I^9`>2Q/e#qm:.,Y&dY|=,WRP2=Wg~!xcmUsv_?An3h6j)cZ8TIl60$AIozBFly( g6IT=UqBhM}^wZ^&!vD>+SgnE_r7`umkgG_X[!=t,Pp:S8Sq}kAw(a!.q|ZX9ATW]~mfc&J+Y9}dIyCQS`Cqrk9Cp4w52v1gArFQVZznC+6*^Cl4;y09vs:(ZF6ZTEghvVb}M }@{f!B{hfm})oR+YUx)&-aDe35;#c1)f?6agYQ8-Q8>IqF3D',
            // 5
            '2;3163186;3552312;11,36,0,0,1,0;L.zg3J(*xIXyHZ(]%O!~v =_&Rd<O]_Q/fN(ccz32X_c][#$Q*S](vXC4P`;LtmqEJ{XUi^/TE}%_(XbmS~2*Ke=bMdPL#{ `PX^*NC||Skn:2]v.bVYO$DxF0K9I!{]_ ghN)dHD);l[/a 3-[rrdcP}s0m@Gx)y0e[e+4 *fjHG!U|+seJq,+fHLO;Z4?Umju;oKkoyrui`Bvor#:^kP5E5*#Js(vXWM=9$%bLQd?41fFq1o._*|G8|uQ>mw9hTJTv].71]0e.mK@gB88]QNb3ts^f;~Fpvt@c?v}`kir{fx]3m|i<kX40l&khL-a1P03%d&1>Vc}04(%ywZ9g1&6[<X}/9BsBJT<jnfvvJ0mB#L&UDyHZhW@DX.J]M?{Ksr>kh6OKVt/B`z+]FbgGs)S{&:7N4!v$`xf>REOT}LfCcvNbBe!T^.^H@_bwvdg{]xYCSd-QrCBN%9UR=Zyc>aJ0[K6?BR99Aj`G}VYO|M+Mr$7!D-)[/IKe~<Jwx{nZ$EG8^aJSeImg/Ih]K*&;*M*rF</$$G/+U6YH;U_^skQBLn;+)b$NJZD@s|v5L`!#Q.T*z:lu/j+,bWx@NzN} t$zfRY6Aj[N|SXO5$,SL7<kpV21Cslw{AI,J3K 1-0Jzfl7zB,TM8(jM=9|b<$6/cyU8]+u*QcpN9AQ2[L)~gmC0`3;m*%6VX4tS5iQa?J0.REJ.6RqI}-URwfKQ9]/y(:.<A]s.q!<1}Ag6&.qvfP?M_@^k-}?S-U2.lLFtA(m1ftG{zCZfaMa^K.CulmwpaxMru/:GlnIt0&1bWfbmgMT=ZDq6PyE_:.Zwo&= tNp%gw?[<FU^xrw%6KQ_YZJ5)dbJ[u:x.<p0j@j_k+-N]yh|u:no!ea)F870cMTe1[39E*W/KXe-6Z/zdtr|9HvAIVaT@7c($VX5N*qomO^VBeB2X-<~mEQ^6O.^Aj~h_@{6E0r#K=|Nem!kAEcH1G+c4]qF_,zjp~2+s*5QMK<9&&Cc{qoR#/~amZ2{JS^YnSPP|v[pxIk9jCh,:$E.m{IW[}ekS?!T6sd1cQzd0##5(0I .+zu?*njF!Msah|(G8:Mq J8:0)+cLjWl|u}Gu~D{#VsPZ9<HAGgNAUj)h3vT1tG~+5)[Er/xEi9BGr1PUCx[Yg5V+f]@*S|<Z?BzLULy7t&e0^uAHH/`wu`s^02iW=)`r~FTTkg@R)Y/`I[$55wab .|oKF:,$^xfU/pUbzp[<jX*l&YmPZVdxP^j&e?L>6bCPmW>ip@m_-xC!Wf5S;~WCIUo4vPjWP:a*BT.}E82z.{dIjNf&xt>[F~#s$l*db^IO}pj)R}]F/}vQ;R#A5^p<81MOK-I`q(jSX?Yo%cE34kc6S9D~&AT^gG-Hyh=IL~[(z[:e1j0C^m_elRNTz|SaSZ,tBrnw-7bCk,Kvr{8h4ZTbv.BWP5um@MpFng%FaU}J7u$fX|=[5k*0_0w{FY{lD3h7Ci%v,lh6^&4{>@nZ)r)vB:6Whhf_V=^TY6BO9KPBN_u`9U:tRaG$(VWzZ3r=e0(TS%x/1<e>??V.K2VK[.pP5$3,!3:sdeX2i] x-k wK1d&W8$bMM/hR/H_-oDD.>Jp~x}!zQE 9bn~t=g2E!{y]]eg.~+$^r.oIs8{W(V($Q)@F){YS%[&-uP?:kOgmA~{^D]f(dK[^CX+poBuNO9r,jO4tFL=&)*uZr8k(T1:Xr1mn%.v>wnyjNx@Lu(pwBE1v<^(VWG|ubg@za`1=cU;^eNA>DP?)aJ;L=O4n6m;Cm)GftT FU.|Gs(5 f{+p?3jfVeQ=yJa!k]otRqc, J?X?JbNF`iX&.xzcuQY@u,~ MKP6iM2$[Qz`^r~D(F:lrX=J1dKA#Pn]dyGx}w,nSMz-^`8xQGIfT9MPcJku4W!9x@X;)Q`dD2lY#=P;y3,p #5:KJY;g*o85Yqp)L1G~+eNnANM6DI8UBhb(4=#zZc+>t6ukG^f`p73gJ:D+LDK4dXv}dlb0!H)&l.=Z~~/!,imGx(?yRQIhqGKn&IFO-h})f5D27*h/v:-gLi}kF|0,%B]7;SsKR?NxM$,%34XIdVPBZ5OIb3dOBO_;Yx,2LRRA%gyh%>H]}gsYAZzqay+IhC_3a[,L51Sn+5{UgzEK(qDX{&:46Fm*L0Q5Z51BTt]fgV_l]^0~NW ]Oe10wN]5h8p]AIq8L>{@P,PS;3dzSOneYD`oe7BM4bn@}CuJP?oENA-^ef(Qv=3PQ#l@dAPMc_-oal]$.jtIREuf]zXjV=%16wI[aAbZxxFnq.6PipkyCq^Tk*v@r=a^qX?JU$TN.uXc?7Z5>M=!D0d)oB&P.UX/A}p/>oF8z$pgG@qWiwrSr Y!iB~O?j2Pl!}<%G2(szkeLQ, |<7)JM6aXy<8[m3TrmXtE(]gJ(9}N>(|9mK@j]_-.fO=^]PY|kv0kR<6T%SF3B%o=r]`,S*k[R-gt%0O}pxEr`ASHSkaxP^0*SfR?*; #dhMN:q6|Jt!iK; v-1SReOTwZ[`-UH5/(0z,`M~VziCy&dz&GEu#--2Z7BM+V<>{0rzh0tT.>N(}a]!;YcY(wR<0[*thyu6A1s:}I0z[y/gMD`P}KshBtp,QHdU<yh4z!9,q@E%WJT@ 6H>_-@EJt~,oj]}q@tCSZ((v`k?1UymS]hBb%J,Ax|.+t:bFM53+FYj7OpPEo}L`H<_.gAu-X|i&tROcS_}-Bj =GSPBg>//9Q7K<lfsRm+uW3Lz[wM=22Dq-?$>=*4$Nb@vc:rD(Go^o>rzm(6 &_ysL@ub@&$1`r](r^MuW~F>R1]6XT?m;@y:!:;U)jt/CoQ&/e^',
            // 6
            '2;4337985;4339265;17,62,0,0,2,0;Atd1l$taG] Y?+1ZTI-*V[{3(b,^KtcC9wW#&,b) )MHFXGp?EY45:J<oXO8b0@NNyg5r,;MK9T>$%~4l!780x37?8Y ^4(nWN8yX)B19s(qhjn|p4-5qdCa`:aa2iW:*HEqh8RJM9UJ87|1v% 2(t=!}|K}pYR2NpMjb:hho)aHpt}=Q_h} uI );iG7wym{un_Y&]e~&_70zQsN~}9`_=U:@#VR6HqTI^NBt(cmacD6^g.RNYnMH[v.;YR[GaSv@VxO~;+bT1AFc Ic8WxvZP Kpp]F^^o-(LDGzK4P@zmyvyTyIi>MQ08Qr`IG3EH@8YcT`?bd*gw8WzX5o6G]%lh*N?&1MXq=uJT0hfmbJFq2lgRn[yRt60*Gzz^}zNX!Ilo+xr@svm7D$N7k`vQm/()g=./OX*=z*Zm.*6jz5rku[.qS-lVr;4>vKIGpfhoFpHemx`e28FoB[b<#sO3Vz.2%7jX2N7_{@z.f`rH31O`S4fY@:$LHns/RbUg0U9]@oBrReq-u2<uLBs/Mu@6m>gmNARpzu8jX-G6g/1Zd V^2Bb8H$i^9v/.7O|r)x{_;vAkLV0!rbM6%G1#y5w R=bzqZ(5mC*sbQ~ <b0721noSUK~u }}VNt65]yK_&t`W46gEw=HBN%(g!Uhjz~uha&CNH{z:[^DG1%k[,a1?h<Ax.3COXYjdh;tWmGwi}2b5_iLFe:M_CqM8RLuxA>9+r*URx JE#<`F9inbJ4?N}YlIeq=Dtw+5A$eYgQ,.Ydh{yvk@Hi~?~ZVOz&Ta/Hj_bF%BXArecu|3-22$mYn(VH3hr]DU(o-(7&l%43FsSm|9:XOV8v6XC+Uh,!egjf>-q85Pr`SRXjwDGiPsZZz zfz4Rz]!bsFQrIguE@;N-`J>3^Zyc`tC=:R^<P2jHn;he&_>W[Yu)3-@2IRo`X;WRR,N&$#Lo=(&(YP.H}Pn|4|Dq:Jn;6r<j`0R,RUeI$MCGCK-U5pFG5H#Gt!K>>)k|&9fJ|xB>yVrK`e<t//NeAZyEo0a*smGcf=qeL?`d:/Q02E7ly+KkVoR-M:HYzmZEh.iH2:zuOI}U`|gXbw(!?yhA{Bw!2vngT|GM%2r%v}Sw83K,JV/T< e<&O%8YV~uq^?=!!|~vt<4QmWQo%c>%dal[VDvx<X0v3lzfyqyEHy>/~N&;cBF3u}.PL=JFz|kdLkn2DPY+5I3/890P|`64y*ob@6d7m%Z7.Rib~q<9@42N3*5c-n[XIW=B{qM~4iF*ulz&JQtj yoKp!5l6~isYrh`OFvB;Gy%vm.7Xd6CNIH.in{];.}rw%|/)/i|;B/ -+/5^pJ:#kR<j1iDy}ow$6=)J,|;)xs2tU}En(R`+4=(t)@iwa9Vcv!]IT~VwJ0xuv;Z6X5Kmw8=f0DX9J/v]2>hgNA9^ydJ&R$KV%:f7=W@EOBsZ|]NI~kPH_RV$z#v5Ot8xP$W{6[XUu7@&G$|{Pu@W@C!}24 Gg7iP(NHl&VMMzUTBTl8O(8c}4,,];)<a&X,%iQaO}QQ2xLAJeoGv/o6Cca{E$/+_yy<-iZ9*=(z5^8DGo#R-v!i ?TK;hw)Ayh0a3~szJ4-}#E4er]w:%@~M}z:VG6xc%C`YP*E/R];bNwRTIM?ZJYX09&C xkzv7?2ax6w~YLLjG/HZ-hEkFPg}?>^}y3,koiR9)- sv/Yk7qu&Tg3c]>oQXiyu=bM@qgh9yvh>k+yig*>AI1?g5b7[@J!3RIuMBZ1]Agxq*NO_XzLrIku$OXlF1_I*[F~kreMy#i$:o#Cwg*g6fg[n+qrZ/|1r>MSvWAJR|,{Ays6t^{V6^@N>o^SN1Y]JCm5Z~Fm,Y]tp-4@u(TGyjnk`noPK>pgo9T,/`{Y;_~[C(GW]8i,=6LMWYA,7htd/f>V8>^k]X:*qC/`II*(xOU8q?iy}WBo4tw1iW!VIH;3E^rLs~E>ie-S?)Y[m_3~cWrX*xl&bkV^3QOWeG7<i+fP ,.!Yj%Jq@iQJHb%W#-2<)T]nLo`AXy>|Qdrv3R<_|TUo5g0uB,c7SZ[fEthd)(QrZ|C0TKLvRSUx?yR=cQ/G?LPgD ]]p-_x[y~>zC?T psS1mTJ4);1O$%*oKUUq+d#e~d!AQL<v6>SwmOF(lMEi>Ce%5-2Z}P)l:t&[iv^5Pe5`c|PsrbY#SriyMWd>Z5nX%e5tp xI$~o}Oxv`q[c@Sr<#g(;g9/6RldF#5/*&Ij.kGbTIKFE|bv:+!9[5iI+)UKtQp#-xJ?X 0}$}4,0hMl;>0EjO+0tO2%cEP {n>hY@G6BLqv#8:W0++Nl^;,fvSS4{Ccdf9nXANPGt#^VUHxmqp?qc<E$*#zR?{+XB~UM@S[$?T:VK]4AYg4eC6:.:,>Uv:p$dw6;$CEq-gk[X_=p~7e>=|)&A:RErO;8H/t&w#3tzCPeu{`;BlR98`~TD$A/%e=Zrc:HPnxUd@Tfr!XSq9~:S,cUik+/<)jg(-v4q7V<K$9t-6e_rB-dwq *NW}yqeSl74{*1a0a!V%T{3YW$G<u(1K`s/grxODX4-{?F~GzI,T5@ULBrMUD,?h#CQ|):1&Q#z}Og)S?vMuYaz01]2 |vPq~UmDOnjwM^i^#R?xkw^>qE`U^4ptm2Hz1opfi:2F 2HBs4&TH+nF9Jy*6iNOMx5k*Ol9[y0g`EOeOlw} _J|2`2JP9v( PawO7]GcLX`S1EQ|^^AA^iA,l5.iSrE(##0)OBU~VTu^<[nU Pb.$*9.4>QJ3FdwLBj8L3D4m)r-U;&Aus/d!Y}xP;L#B8Ec:*?;!-o88$F.{b)!tg]n=QX6rOnb_Wu4dPO7-TAr}`k&L',
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        if (!isset($this->key)) {
            $this->key = array_rand($sensorData);
            $this->logger->notice("set key: {$this->key}");
        }
        $this->logger->notice("key: {$this->key}");

        $sensor_data = $secondSensor === false ? $sensorData[$this->key] : $secondSensorData[$this->key];

        return $sensor_data;
    }

    private function selenium($url)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $allCookies = array_merge($this->http->GetCookies(".wyndhamhotels.com"), $this->http->GetCookies(".wyndhamhotels.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies("www.wyndhamhotels.com"), $this->http->GetCookies("www.wyndhamhotels.com", "/", true));

        try {
            $selenium->UseSelenium();
            $selenium->useFirefox();
            $selenium->http->setUserAgent($this->http->userAgent);
            $selenium->disableImages();
            $selenium->useCache();
            $this->seleniumOptions->recordRequests = true;

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;
            $selenium->http->GetURL("https://www.wyndhamhotels.com/d-fd");

            foreach ($allCookies as $key => $value) {
                $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".wyndhamhotels.com"]);
            }

            $this->seleniumRequest($selenium, $url);

            if ($this->http->FindPreg('/"Something\'s not right - please try again later."/')) {
                $this->seleniumRequest($selenium, $url);

                if (!$this->http->FindPreg('/"Something\'s not right - please try again later."/')) {
                    $this->sendNotification('success retry // MI');
                }
            }
            /*$cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }*/
        } catch (Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup();
        }

        return true;
    }

    private function seleniumRequest($selenium, $url)
    {
        $selenium->http->GetURL($url);
        $selenium->waitForElement(WebDriverBy::xpath("(//div[@id='upcoming-res-listings']//div[@class='listing-row row'])[1]"), 20);
        $seleniumDriver = $selenium->http->driver;
        $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

        foreach ($requests as $n => $xhr) {
            $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
            if (stripos($xhr->request->getUri(), '/loyalty/member/reservations') !== false) {
                $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                $this->reservations = json_encode($xhr->response->getBody());

                break;
            }
        }

        if (!empty($this->reservations)) {
            $this->http->SetBody($this->reservations);
        } else {
            //$this->http->SetBody('{}');
        }
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);

//        $cacheKey = 'triprewards_abck';
//        $result = Cache::getInstance()->get($cacheKey);
//
//        if (
//            !empty($result)
//            && $this->attempt < 1
//            && ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION
//        ) {
//            $this->logger->debug("set _abck from cache: {$result}");
//            $this->http->setCookie("_abck", $result);
//
//            return null;
//        }

        $selenium = clone $this;
        $retry = false;
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
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);

            $selenium->http->saveScreenshots = true;

            $configs = [0, 1, 2, 3];

            if ($this->attempt == 1) {
                $configs = [7];
            } elseif ($this->attempt == 2) {
                $configs = [5];
            }

            $config = $configs[array_rand($configs)];

            switch ($config) {
                case 0:
                    $selenium->useGoogleChrome();
                    $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::chrome()]);

                    break;

                case 1:
                    $selenium->useChromium();
                    $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::chrome()]);

                    break;

                case 2:
                    $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
                    $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::chrome()]);

                    break;

                case 3:
                    $selenium->useFirefox();

                    $request = FingerprintRequest::firefox();
                    $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                    $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                    break;

                case 4:
                    $selenium->useChromePuppeteer();
                    $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
                    $selenium->seleniumOptions->addHideSeleniumExtension = false;
                    $selenium->seleniumOptions->userAgent = null;

                    break;

                case 5:
                    $selenium->useChromePuppeteer(SeleniumFinderRequest::CHROME_PUPPETEER_100);

                    break;

                case 7:
                    $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
                    $selenium->seleniumOptions->addHideSeleniumExtension = false;
                    $selenium->seleniumOptions->userAgent = null;

                    break;

                default:
//                    $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
//                    $selenium->seleniumOptions->addHideSeleniumExtension = false;
//                    $selenium->seleniumOptions->userAgent = null;

                    $selenium->useFirefoxPlaywright();
                    $selenium->seleniumOptions->addHideSeleniumExtension = false;
                    $selenium->seleniumOptions->userAgent = null;

                    break;
            }

            if (!empty($fingerprint)) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

//            $selenium->disableImages();

//            $selenium->keepCookies(false);
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://www.wyndhamhotels.com/wyndham-rewards/login");

            $xpathForm = '//div[contains(@class, "background-color-container")]//form[contains(@class, "sign-in-form")] | //main[contains(@class, "login")]//form';

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath($xpathForm . '//input[@name = "login-username" or @name = "username"]'), 7);
            $this->closePopup($selenium);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath($xpathForm . '//input[@name = "login-password" or @name = "password"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput) {
                return false;
            }

            $loginInput->clear();
            $loginInput->sendKeys($this->AccountFields['Login']);
            $this->savePageToLogs($selenium);
            $passwordInput->clear();
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $button = $selenium->waitForElement(WebDriverBy::xpath($xpathForm . '//button[contains(text(), "SIGN IN") or contains(text(), "Continue")]'), 3);
            $this->savePageToLogs($selenium);

            if (!$button) {
                return false;
            }

            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath(
                self::XPATH_PROFILE
                . ' | ' . self::XPATH_ERRORS
                . ' | //div[contains(@class, "background-color-container")]//input[@name = "answer1"]
            '), 20);
            $this->closePopup($selenium);

            $this->savePageToLogs($selenium);
            $this->logger->debug("find sq question");

            $question = $this->http->FindSingleNode('//p[@class = "question"]');

            if ($question) {
                if (!isset($this->Answers[$question])) {
                    $cookies = $selenium->driver->manage()->getCookies();
                    $this->http->removeCookies();

                    foreach ($cookies as $cookie) {
                        $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                    }

                    $this->AskQuestion($question, null, 'Question');

                    return false;
                }

                $answerInput = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "background-color-container")]//input[@name = "answer1"]'), 0);
                $answerInput->sendKeys($this->Answers[$question]);

                $btn = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "background-color-container")]//button[contains(text(), "Submit")]'), 0);
                $this->savePageToLogs($selenium);

                if (!$btn) {
                    return false;
                }

                $btn->click();

                $selenium->waitForElement(WebDriverBy::xpath(
                    self::XPATH_PROFILE
                    . ' | ' . self::XPATH_ERRORS
                ), 10);

                $this->closePopup($selenium);

                $this->savePageToLogs($selenium);
                $this->logger->debug("check results");

                if ($message = $this->http->FindSingleNode(self::XPATH_ERRORS)) {
                    $this->logger->error("[Error]: {$message}");

                    if (strstr($message, 'Security Question Answer Incorrect')) {
                        unset($this->Answers[$question]);
                        $this->AskQuestion($question, $message, 'Question');

                        return false;
                    }

                    if (!strstr($message, 'Something\'s not right - please try again later.')) {
                        return false;
                    }
                }// if ($message = $this->http->FindSingleNode(self::XPATH_ERRORS))

                $this->logger->notice("sleep 5 sec");
                sleep(5);
                $this->savePageToLogs($selenium);
            }// if ($question)

            $cookies = $selenium->driver->manage()->getCookies();
            $this->http->removeCookies();

            foreach ($cookies as $cookie) {
//                if ($cookie['name'] == '_abck') {
//                    $result = $cookie['value'];
//                    $this->logger->debug("set new _abck: {$result}");
//                    Cache::getInstance()->set($cacheKey, $result, 60 * 60 * 20);
//
//                    $this->http->setCookie("_abck", $result);
//                }

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (UnknownServerException | SessionNotCreatedException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(2, 0);
            }
        }

        return true;
    }

    private function closePopup($selenium)
    {
        $this->logger->debug("close popup");
        $selenium->driver->executeScript("var popup = document.querySelector('#attentive_overlay'); if (popup) popup.style = \"display: none;\";");
    }
}
