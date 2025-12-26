<?php

class TAccountCheckerPegasus extends TAccountChecker
{
    private $headers = [
        'Referer'                     => 'https://web.flypgs.com/login',
        'Content-Type'                => 'application/json',
        'Accept'                      => 'application/json, text/plain, */*',
        'Access-Control-Allow-Origin' => '*',
        'X-PLATFORM'                  => 'web',
        'X-VERSION'                   => '1.0.1',
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);

        $phoneCodes = $this->getPhoneCodes();

        $arFields["Login2"]["Options"] = [
            "" => "Select a region",
        ];

        foreach ($phoneCodes as $phoneCode) {
            $arFields["Login2"]["Options"][$phoneCode->phoneCode] = "{$phoneCode->name}";
        }
    }

    public function getPhoneCodes()
    {
        $this->logger->notice(__METHOD__);

        $phoneCodes = Cache::getInstance()->get('pegasus_phone_codes');

        if (!$phoneCodes) {
            $this->http->GetURL('https://www.flypgs.com/assets/data/phone_codes_es_en.json');

            $phoneCodes = $this->http->JsonLog()->countryList ?? [];

            Cache::getInstance()->set('pegasus_phone_codes', $phoneCodes, 3600 * 24);
        }

        return $phoneCodes;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip');
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://web.flypgs.com/pegasus/profile/overall', $this->headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, true);
        $memberProfileResponse = ArrayVal($response, 'memberProfileResponse');

        if ($memberProfileResponse) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (
            filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false
            || $this->http->FindPreg("/^[a-z]/ims", false, $this->AccountFields['Login'])
        ) {
            throw new CheckException('Your member details are incorrect. Please check and try again.', ACCOUNT_INVALID_PASSWORD);
        }

        if (in_array($this->AccountFields['Login'], [
            '34626931218', // 0034626931218
            '994504500486',
            '7525492215', // 07525492215
        ])
        ) {
            throw new CheckException("Your member details are incorrect. Please check and try again.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->prepareCredentials();

        // https://web.flypgs.com/login
        $this->http->GetURL('https://www.flypgs.com/en');

        if (!$this->http->ParseForm('bolbol-login-form')) {
            return $this->checkErrors();
        }

        // $this->AccountFields['Pass'] = '';// todo
        $passLength = strlen($this->AccountFields['Pass']);
        $this->logger->debug("pass length: " . $passLength);
        /**
         * This value length is invalid. It should be between 6 characters long.
         * You can add 00 before your Pegasus Plus password to log in to Pegasus BolBol.
         */
        $this->AccountFields['Pass'] = $passLength == 4 ? '00' . $this->AccountFields['Pass'] : $this->AccountFields['Pass'];

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        // captcha
        $captcha = $this->parseReCaptcha();

        if (!$captcha) {
            $this->logger->debug('failed to parse captcha');

            return $this->checkErrors();
        }

        $data = [
            'PhoneNumber'               => $this->AccountFields['Login'],
            'Password'                  => $this->AccountFields['Pass'],
            'PhoneAreaCode'             => $this->AccountFields['Login2'],
            'GoogleRecaptchaResponse'   => $captcha,
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'Alt-Used'     => 'www.flypgs.com',
        ];

        $this->http->RetryCount = 0;

        if (!$this->http->PostURL('https://www.flypgs.com/apint/Loyalty/SignIn', json_encode($data), $headers)) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        $encryptFFToken = $response->response->encryptFFToken ?? null;

        if (
            isset($response->isSuccess, $encryptFFToken)
            && $response->isSuccess == true
        ) {
            $this->http->setCookie('X-FF-TOKEN', $encryptFFToken, '.flypgs.com', '/', null, false);

            return true;
        }

        if (isset($response->message)) {
            $error = $response->message;
            $this->logger->error("[Error]: {$error}");

            if (strstr($error, "Kullanıcı bilgileriniz hatalı, lütfen kontrol edip tekrar deneyiniz")) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, "Your member details are incorrect. Please check and try again.")) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            if ($error == "Login Service is disabled") {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $error;
        }

        // A problem has occurred. Please try again later.
        if ($this->http->Response['code'] == 500 && $this->http->FindPreg("/<b> Description: <\/b>An unhandled exception occurred during the execution of the current web request. Please review the stack trace for more information about the error and where it originated in the code\./")) {
            throw new CheckException("A problem has occurred. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != 'https://web.flypgs.com/pegasus/profile/overall') {
            $this->http->GetURL('https://web.flypgs.com/pegasus/profile/overall', $this->headers);
        }
        $response = $this->http->JsonLog();

        $member = $response->memberProfileResponse->member ?? null;

        if (isset($member)) {
            if (isset($member->name, $member->surname)) {
                // Name
                $this->SetProperty('Name', beautifulName("{$member->name} {$member->surname}"));
            }

            if (isset($member->startDate)) {
                // Pegasus BolBol member since
                $this->SetProperty('MembershipStartDate', date("d F Y", strtotime($member->startDate)));
            }
        }

        $memberCardDetailResponse = $response->memberCardDetailResponse ?? null;

        if (isset($memberCardDetailResponse)) {
            if (isset($memberCardDetailResponse->totalPoints)) {
                // My total points - Balance
                $this->SetBalance($memberCardDetailResponse->totalPoints);
            }

            if (isset($memberCardDetailResponse->totalPointsSinceEnrolment)) {
                // Lifetime points
                $this->SetProperty('LifetimePoints', $memberCardDetailResponse->totalPointsSinceEnrolment);
            }

            if (isset($memberCardDetailResponse->redeemedMiles)) {
                // Redeemed points
                $this->SetProperty('RedeemedPoints', str_replace('-', '', $memberCardDetailResponse->redeemedMiles));
            }

            // Expiration date and points
            $pointsToExpire = $memberCardDetailResponse->pointsToExpire1 ?? null;
            $pointsToExpireDate = $memberCardDetailResponse->pointsToExpireDate1 ?? null;

            if (isset($pointsToExpire,$pointsToExpireDate)) {
                // Expiring balance
                $this->SetProperty('ExpiringBalance', $pointsToExpire);
                // ExpirationDate
                if ($pointsToExpire > 0 && ($exp = strtotime($pointsToExpireDate, false))) {
                    $this->SetExpirationDate($exp);
                }
            }
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->GetURL("https://web.flypgs.com/pegasus/member/reservations?startDate=" . date("Y-m-d") . "&endDate=", $this->headers);
        $response = $this->http->JsonLog(null, 3, true);
        // no Itineraries
        if ($this->http->FindPreg("/^\{\"memberPnrList\":\[\]\}$/")) {
//            if ($this->ParsePastIts) {
//                $pastItineraries = $this->parsePastItineraries();
//                if (!empty($pastItineraries))
//                    return $pastItineraries;
//            }// if ($this->ParsePastIts)
            return $this->noItinerariesArr();
        }
        $memberPnrList = ArrayVal($response, 'memberPnrList', []);
        $this->logger->debug("Total " . (is_array($memberPnrList) ? count($memberPnrList) : "not array") . " itineraries were found");

        foreach ($memberPnrList as $memberPnr) {
            $pnrInfo = ArrayVal($memberPnr, 'pnrInfo');
            $pnrNo = ArrayVal($pnrInfo, 'pnrNo');
            $this->logger->info('Parse Itinerary #' . $pnrNo, ['Header' => 3]);
            $data = [
                'pnrNo'             => $pnrNo,
                'surname'           => ArrayVal($pnrInfo, 'surname'),
                'deleteOptionalSsr' => true,
                'filter'            => 'MMB',
            ];
            // $this->http->PostURL("https://web.flypgs.com/pegasus/reservation/details/pnr", json_encode($data),$this->headers);
            if (!strstr($pnrNo, '?')) {
                $this->http->PostURL("https://web.flypgs.com/pegasus/pnr-search/filtered", json_encode($data), $this->headers);
                $this->parseItinerary();
            }
        }// foreach ($itineraryForms as $itineraryForm)

//        if ($this->ParsePastIts)
//            $result = array_merge($result, $this->parsePastItineraries());

        return $result;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key =
            $this->http->FindPreg('/ \'sitekey\': "(.+?)"/')
            ?? $this->http->FindSingleNode('//div[@id = "jsRecaptcha_LoginForm"]/@data-recaptcha-sitekey')
            ?? $this->http->FindSingleNode('//form[@id = "pgsLoginForm" and @data-recaptcha-show="show"]/@data-recaptcha-sitekey')
            ?? $this->http->FindSingleNode('//form[@id = "header-bolbol-login-form" and @data-recaptcha-show="show"]/@data-recaptcha-sitekey')
            ?? $this->http->FindSingleNode('//div[@data-recaptcha-sitekey]/@data-recaptcha-sitekey')
        ;
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function prepareCredentials()
    {
        $this->logger->notice(__METHOD__);

        // prepare credentials data
        $this->AccountFields['Login'] = str_replace(['(', ')', '+', '-'], '', $this->AccountFields['Login']);
        // CA+1-xxxxxxxx
        $this->AccountFields['Login'] = preg_replace("/^[A-Z]+/", '', $this->AccountFields['Login']);

        $this->logger->debug("cleaned login: {$this->AccountFields['Login']}");

        if (isset($this->AccountFields['Login2'])) {
            return;
        }

        foreach ($this->getPhoneCodes() as $codeObject) {
            $code = $codeObject->phoneCode ?? null;

            if (isset($code) && strlen($code) > 0 && strpos($this->AccountFields['Login'], $code) === 0) {
                $this->logger->debug("Code object found:");
                $this->logger->debug(print_r($codeObject, true));

                $this->AccountFields['Login2'] = $code;

                break;
            }
        }

        $this->AccountFields['Login'] = mb_substr($this->AccountFields['Login'], strlen($this->AccountFields['Login2']));

        $this->logger->debug("prepared login: {$this->AccountFields['Login']}");
        $this->logger->debug("prepared login2: {$this->AccountFields['Login2']}");
    }

    private function parseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 3, true);

        if (isset($response['title']['key']) && $response['title']['key'] == 'error.title.mw00002') {
            $this->logger->error("Invalid request (mw00002), skip this itinerary");

            return;
        }

        $f = $this->itinerariesMaster->add()->flight();
        $pnrInfo = ArrayVal($response, 'pnrInfo');
        $confNo = str_replace('&', '', ArrayVal($pnrInfo, 'pnrNo'));

        $createdDateTime = ArrayVal($pnrInfo, 'createdDateTime', null);
        $f->general()
            ->confirmation($confNo, 'Reservation (PNR) No', null, '/^[%@[Å$+=\-A-Z\d]{4,10}/i')
            ->date(strtotime(preg_replace('/:\d\d$/', ':00', $createdDateTime)));

        if (ArrayVal($pnrInfo, 'status', null) == 'CX') {
            $f->general()->cancelled()->status('Cancelled');
        }

        // Passengers
        $passengerList = ArrayVal($response, 'passengerList', []);

        foreach ($passengerList as $passenger) {
            $f->general()->traveller(beautifulName(ArrayVal($passenger, 'name') . ' ' . ArrayVal($passenger, 'surname')), true);
        }
        // TicketNumbers
        $ticketNumberList = ArrayVal($response, 'ticketNumberList', []);

        foreach ($ticketNumberList as $ticketNumber) {
            $f->issued()->ticket($ticketNumber, false);
        }

        $segments = [];

        if ($departureFlight = ArrayVal($response, 'departureFlight', [])) {
            $segments[] = $departureFlight;
        }

        if ($returnFlight = ArrayVal($response, 'returnFlight', [])) {
            $segments[] = $returnFlight;
        }

        if (!$segments) {
            $segments = ArrayVal($response, 'flightList', []);
        }

        $this->logger->debug("Total " . (is_array($segments) ? count($segments) : "not array") . " segments were found");

        $badItinerary = false;

        foreach ($segments as $seg) {
            $s = $f->addSegment();

            $s->airline()
                ->name(ArrayVal($seg, 'airline'))
                ->number(ArrayVal($seg, 'flightNo'));

            $departureLocation = ArrayVal($seg, 'departureLocation');
            $s->departure()
                ->code(ArrayVal($departureLocation, 'portCode'))
                ->name(ArrayVal($departureLocation, 'portName'))
                ->date(strtotime(ArrayVal($seg, 'departureDateTime')));

            $arrivalLocation = ArrayVal($seg, 'arrivalLocation');
            $s->arrival()
                ->code(ArrayVal($arrivalLocation, 'portCode'))
                ->name(ArrayVal($arrivalLocation, 'portName'))
                ->date(strtotime(ArrayVal($seg, 'arrivalDateTime')));

            if (
                $this->http->FindPreg('/([@\[Å$+=\-]+)/', false, $f->getIssuingConfirmation())
                && ArrayVal($seg, 'departureDateTime') == ArrayVal($seg, 'arrivalDateTime')
                && ArrayVal($seg, 'arrivalDateTime') == '2222-01-01T00:00:00'
            ) {
                $this->logger->notice("skip bad segment");
                $badItinerary = true;
                $this->sendNotification("skip wrong segment // RR");

                continue;
            }

            // Seats
            $ssrInfo = ArrayVal($seg, 'ssrInfo');
            $ssrGroupList = ArrayVal($ssrInfo, 'ssrGroupList', []);

            foreach ($ssrGroupList as $ssrGroup) {
                if (ArrayVal($ssrGroup, 'ssrType') != 'SEAT') {
                    continue;
                }
                $ssrList = ArrayVal($ssrGroup, 'ssrList', []);

                foreach ($ssrList as $ssr) {
                    $s->extra()->seat(ArrayVal($ssr, 'selectedSsrCode'));
                }
            }// foreach ($ssrGroupList as $ssrGroup)
        }// foreach ($segments as $seg)

        $pnrId = ArrayVal($pnrInfo, 'pnrId');

        if ($pnrId) {
            $this->http->GetURL("https://web.flypgs.com/pegasus/reservation/history/{$pnrId}", $this->headers);
            $response = $this->http->JsonLog(null, 3, true);
            $accountingHistoryList = ArrayVal($response, 'accountingHistoryList', []);

            foreach ($accountingHistoryList as $accountingHistory) {
                $fare = ArrayVal($accountingHistory, 'fare', null);

                if (!$fare) {
                    continue;
                }
                $goodFare = $fare;
                $amount = ArrayVal($goodFare, 'amount', null);

                if (isset($totalCharge)) {
                    $totalCharge += floatval($amount);
                } else {
                    $totalCharge = floatval($amount);
                }
            }// foreach ($accountingHistoryList as $accountingHistory)

            if (isset($totalCharge)) {
                $f->price()->total($totalCharge);
                $currency = ArrayVal($goodFare, 'currency');

                switch ($currency) {
                    case 'TL':
                        $currency = 'TRY';

                        break;
                }
                $f->price()->currency($currency);
            }
        }// if ($pnrId)

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        if (
            $badItinerary === true
            && count($f->getSegments()) === 0
        ) {
            $this->logger->notice("remove crooked itinerary");
            $this->sendNotification("remove crooked itinerary // RR");
            $this->itinerariesMaster->removeItinerary($f);
        }
    }
}
