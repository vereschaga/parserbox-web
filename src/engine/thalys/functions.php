<?php

use AwardWallet\Schema\Parser\Common;

class TAccountCheckerThalys extends TAccountChecker
{
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $headers = [
        "Accept"           => "*/*",
        "Content-Type"     => "application/json",
        "X-Requested-With" => "XMLHttpRequest",
    ];

    private $currency = null;
    private $stations = [];
    private $currentItin = 0;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'thalysVoucher')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $result = $this->loginSuccessful();
        $this->http->RetryCount = 2;

        if ($result) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "The Thalys.com site is currently unavailable")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL("https://www.thalys.com/de/en/my-account");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
        $data = [
            "userId"     => $this->AccountFields['Login'],
            "rememberMe" => true,
            "password"   => $this->AccountFields['Pass'],
        ];

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $headers = [
            "Origin"              => "https://www.thalys.com",
            "x-recaptcha-token"   => $captcha,
            "x-recaptcha-version" => "2",
            //            "x-recaptcha-version" => "3",
            "x-recaptcha-context" => "desktop",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.thalys.com/api/authenticate", json_encode($data), $this->headers + $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Thalys TheCard website is temporarily unavailable. Please try to connect later.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Thalys TheCard website is temporarily unavailable. Please try to connect later.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our site is currently under maintenance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our site is currently under maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // A technical error occurred
        if ($message = $this->http->FindSingleNode("//div[@id = 'error']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Error 503 Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Error 503 Service Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->http->getCookieByName("thalys_token") && $this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        $response = $this->http->JsonLog();
        $message = $response->Reason ?? $response->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            // Invalid credentials
            if ($message == 'Invalid Credentials') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("It is not possible to authenticate you with this information.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Site unavailable') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == 'RECAPTCHA_ERROR') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 1);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", beautifulName($response->person->firstname . " " . $response->person->lastname));
        // Thalys No.
        $this->SetProperty("ThalysNo", $response->loyalty->cn ?? null);
        // Balance - You have ... Miles
        $this->SetBalance($response->loyalty->miles->points ?? null);
        // Status
        $this->SetProperty("Status", $response->loyalty->status->level ?? null);
        // [Status] until ...
        $this->SetProperty("StatusExpiration", date("m/d/Y", strtotime($response->loyalty->status->end_date)));
        // Member points
        $this->SetProperty("StatusMiles", $response->loyalty->status->points ?? null);

        if ($this->Balance > 0) {
            $this->logger->info('Expiration Date', ['Header' => 3]);
            // https://www.thalys.com/de/en/my-account/see-my-miles-earned-and-spent
            $this->http->GetURL("https://www.thalys.com/api/accounts/miles-expirations", $this->headers);
            $expInfo = $this->http->JsonLog(null, 0) ?: [];

            if ($this->http->FindPreg("/^\[\]$/")) {
                $this->ClearExpirationDate();
            }
            $expDate = null;

            foreach ($expInfo as $exp) {
                if ($exp->amount > 0 && (!isset($expDate) || $expDate > strtotime($exp->expiration_date))) {
                    $expDate = strtotime($exp->expiration_date);
                    // Expiring balance
                    $this->SetProperty("ExpiringBalance", $exp->amount);
                    $this->SetExpirationDate($expDate);
                }// if ($exp->amount > 0 && !isset($expDate) || $expDate < strtotime($exp->expiration_date))
            }// foreach ($expInfo as $exp)
        }// if ($this->Balance > 0)

        $this->logger->info('Vouchers', ['Header' => 3]);
        // https://www.thalys.com/de/en/my-account/my-e-vouchers
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.thalys.com/api/accounts/evouchers", $this->headers);
        $this->http->RetryCount = 2;

        // prevent traces, broken on the provider side
        if ($this->http->Response['code'] == 400) {
            $this->logger->error("getting vouchers failed");

            return;
        }

        $vouchersInfo = $this->http->JsonLog() ?: [];

        if (isset($vouchersInfo->message) && $vouchersInfo->message == 'Site unavailable') {
            return;
        }

        foreach ($vouchersInfo as $voucher) {
            $balance = $voucher->amount;
            $code = $voucher->code;
            $type = $voucher->type;

            if (!in_array($type, ['Compensation', 'Prepaid'])) {
                $this->sendNotification("Unknown voucher was found: {$type} // RR");
            }
            $this->AddSubAccount([
                "Code"           => 'thalysVoucher' . $code,
                "DisplayName"    => "Discount Code: {$code}",
                "Balance"        => $balance,
                "Voucher"        => $code,
                "ExpirationDate" => strtotime($voucher->validity_end_date, false),
            ]);
        }
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www.thalys.com/de/en/my-account/my-tickets');
        $this->currency = $this->http->FindPreg("/env_currency : '([A-Z]{3})'/");

        // future
        $this->logger->info("Parse Future", ['Header' => 3]);
        $this->http->GetURL("https://www.thalys.com/api/svoc_tickets/upcoming", $this->headers);

        if ($this->http->Response['code'] != 404) {
            $futureTickets = $this->http->JsonLog(null, 0, true);

            if (!empty($futureTickets)) {
                $this->parseItinerariesJson($futureTickets);
            }
        }
        // cancelled
        $this->logger->info("Parse Cancelled", ['Header' => 3]);
        $this->http->GetURL("https://www.thalys.com/api/svoc_tickets/canceled", $this->headers);

        if ($this->http->Response['code'] != 404) {
            $cancelledTickets = $this->http->JsonLog(null, 0, true);

            if (!empty($cancelledTickets)) {
                $this->parseItinerariesJson($cancelledTickets, true);
            }
        }
        // past
        if ($this->ParsePastIts) {
            $this->logger->info("Parse Past", ['Header' => 3]);
            $this->http->GetURL("https://www.thalys.com/api/svoc_tickets/passed", $this->headers);

            if ($this->http->Response['code'] != 404) {
                $pastTickets = $this->http->JsonLog(null, 0, true);

                if (!empty($pastTickets)) {
                    $this->parseItinerariesJson($pastTickets, false, true);
                }
            }
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "File Reference (PNR)",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "Email" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('Email'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.thalys.com/de/en";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $this->currency = $this->http->FindPreg("/env_currency : '([A-Z]{3})'/");

        $conf = $arFields['ConfNo'];
        $email = $arFields['Email'];
        $this->http->GetURL("https://www.thalys.com/api/svoc_tickets/search?PNR={$conf}&Email={$email}", $this->headers);

        if ($this->http->Response['code'] === 404) {
            return 'No trips match the information entered. Please check the information entered.';
        }
        $tickets = $this->http->JsonLog(null, 0, true);
        $isExpired = $tickets[0]['computedStatus']['isExpired'] ?? null;

        if ($isExpired === true) {
            return 'This trip has expired.';
        }
        $isInvalid = $tickets[0]['computedStatus']['isInvalid'] ?? null;

        if ($isInvalid === true) {
            return 'This trip was cancelled.';
        }

        if (!empty($tickets)) {
            $this->parseItinerariesJson($tickets);
        }

        return null;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg('/site_keys":\{"v2":"([^\"]+)/');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => "1",
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.thalys.com/api/accounts", $this->headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $user_id = $response->authentication->user_id ?? null;
        $cin = $response->loyalty->cin ?? null;
        $this->logger->debug("[user_id]: {$user_id}");
        $this->logger->debug("[cin]: {$cin}");

        if (
            isset($response->loyalty)
            && (
                ($user_id && strtolower($user_id) == strtolower($this->AccountFields['Login']))
                || ($cin && $cin == $this->AccountFields['Login'])
            )
        ) {
            return true;
        }

        return false;
    }

    private function parseItinerariesJson(array $tickets, $cancelled = false, $past = false)
    {
        $this->logger->notice(__METHOD__);
        $confSet = [];

        foreach ($tickets as $ticket) {
            $conf = ArrayVal($ticket, 'PNR');

            if (isset($confSet[$conf])) {
                continue;
            }
            $this->parseItineraryJson($conf, $tickets, $cancelled, $past);
            $confSet[$conf] = true;
        }
    }

    private function parseItineraryJson($conf, $tickets, $cancelled = false, $past = false)
    {
        $this->logger->notice(__METHOD__);
        $train = $this->itinerariesMaster->createTrain();

        if ($cancelled) {
            $train->setCancelled(true);
        }
        // confirmation number
        $train->addConfirmationNumber($conf, 'Booking', true);
        $this->logger->info("[{$this->currentItin}] Parse Train #{$conf}", ['Header' => 4]);
        $this->currentItin++;
        $total = null;
        $travellerSet = [];

        foreach ($tickets as $ticket) {
            if (ArrayVal($ticket, 'PNR') !== $conf) {
                continue;
            }
            // reservation date
            if (!$train->getReservationDate()) {
                $bookingDate = $this->getBookingDate($ticket);

                if ($bookingDate) {
                    $train->setReservationDate(strtotime($bookingDate));
                }
            }
            $seg = $this->findSegment($ticket, $train);

            if ($seg) {
                $this->logger->info('Skipping the same segment');

                if ($seg->getDepDate() > strtotime('now')) {
                    $this->sendNotification('check segment skip // MI');
                }
                $seat = $ticket['SeatNumber'] ?? null;

                if ($seat) {
                    $seg->addSeat($seat);
                }

                continue;
            }

            $seg = $train->addSegment();
            // number
            $seg->setNumber(ArrayVal($ticket, 'TrainNumber'));
            // car number
            $seg->setCarNumber($ticket['CarNumber'] ?? null, false, true);
            // dep code
            $seg->setDepCode($ticket['OriginStation'] ?? null);
            // arr code
            $seg->setArrCode($ticket['DestinationStation'] ?? null);
            // dep name
            $seg->setDepName($this->getStationName($seg->getDepCode()));
            // arr name
            $seg->setArrName($this->getStationName($seg->getArrCode()));
            // dep date
            $date = ArrayVal($ticket, 'TravelDate');

            if ($past && !isset($ticket['TravelTime']) && !isset($ticket['ArrivalTime'])) {
                $this->itinerariesMaster->removeItinerary($train);
                $this->logger->error("Removed past train #{$conf} with no departure / arrival times");
                $this->currentItin--;

                return;
            }

            if ($cancelled && !isset($ticket['TravelTime']) && !isset($ticket['ArrivalTime'])) {
                $this->logger->notice("canceled train #{$conf} with no departure / arrival times");
                $seg->setNoArrDate(true);
                $seg->setNoDepDate(true);
            } else {
                $time1 = $this->http->FindPreg('/^(\d+:\d+)/', false, ArrayVal($ticket, 'TravelTime'));
                $seg->setDepDate(strtotime($time1, strtotime($date)));
                // arr date
                $time2 = $this->http->FindPreg('/^(\d+:\d+)/', false, ArrayVal($ticket, 'ArrivalTime'));
                $dt2 = strtotime($time2, strtotime($date));

                if (!$train->getCancelled() || $dt2) {
                    $seg->setArrDate($dt2);

                    if ($seg->getArrDate() < $seg->getDepDate() && !$train->getCancelled()) {
                        $this->sendNotification('check date order // MI');
                    }
                }
            }
            // travellers
            foreach (ArrayVal($ticket, 'BookingInfos', []) as $info) {
                $name = trim(sprintf('%s %s', ArrayVal($info, 'FirstName'), ArrayVal($info, 'LastName')));

                if (isset($travellerSet[$name]) || !$name) {
                    continue;
                }
                $train->addTraveller(beautifulName($name));
                $travellerSet[$name] = true;
            }
            // total
            $amount = ArrayVal($ticket, 'TotalAmount');

            if (is_null($amount)) {
                $this->sendNotification('check total // MI');
            } else {
                $total += floatval($amount);
            }
            // seats
            $seat = $ticket['SeatNumber'] ?? null;

            if ($seat) {
                $seg->addSeat($seat);
            }
            // booking class
            $comfortClass = ArrayVal($ticket, 'ComfortClass');

            if ($comfortClass == 2) {
                $seg->setCabin('Standard');
            } elseif ($comfortClass == 1) {
                $seg->setCabin('Premium');
            } else {
                $this->sendNotification('check cabin // MI');
            }
        }

        if (!is_null($total)) {
            $train->price()->total($total, true);
        }
        $train->price()->currency($this->currency, false, $cancelled);

        $this->logger->debug('Parsed Train:');
        $this->logger->debug(var_export($train->toArray(), true), ['pre' => true]);
    }

    private function findSegment(array $ticket, Common\Train $train): ?Common\TrainSegment
    {
        $this->logger->notice(__METHOD__);

        foreach ($train->getSegments() as $seg) {
            if (
                $seg->getDepCode() === ($ticket['OriginStation'] ?? null)
                && $seg->getArrCode() === ($ticket['DestinationStation'] ?? null)
                && strstr($ticket['TravelDate'] ?? '', date('Y-m-d', $seg->getDepDate()))
                && strstr($ticket['TravelTime'] ?? '', date('H:i', $seg->getDepDate()))
            ) {
                return $seg;
            }
        }

        return null;
    }

    private function getBookingDate($ticket)
    {
        $this->logger->notice(__METHOD__);
        $transactions = $this->arrayVal($ticket, ['TicketInfos', 'Transactions'], []);

        foreach ($transactions as $trans) {
            if (ArrayVal($trans, 'TransactionType') === 'Sold') {
                $date = ArrayVal($trans, 'TransactionDate');

                return $date;
            }
        }

        return null;
    }

    private function getStationName($code)
    {
        $this->logger->notice(__METHOD__);

        if (!$this->stations) {
            $this->http->GetURL('https://www.thalys.com/json/stations/de/en', $this->headers);
            $this->stations = $this->http->JsonLog(null, 0, true);
        }

        $thalysland = $this->arrayVal($this->stations, ['gares', 'thalysland'], []);

        foreach ($thalysland as $item) {
            $k = ArrayVal($item, 'k');

            if ($k === $code) {
                return ArrayVal($item, 'v');
            }
        }

        return $code;
    }

    private function arrayVal($ar, $indices, $default = null)
    {
        if (!is_array($indices)) {
            $indices = [$indices];
        }
        $res = $ar;

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
}
