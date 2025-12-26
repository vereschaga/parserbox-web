<?php

// refs #3013

class TAccountCheckerSlh extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://slh.com/invited/my-profile';

    private $transId = null;
    private $policy = null;
    private $tenant = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.slh.com/login-redirect-page?returnUrl=/invited/my-invited');

        if ($action = $this->http->FindPreg("/form name=\"form-redirection\" action=\"([^\"]+)/")) {
            $this->http->NormalizeURL($action);
            $this->http->PostURL($action, []);
        }
        $this->transId = $this->http->FindPreg("/\"transId\":\"([^\"]+)/");
        $this->tenant = $this->http->FindPreg("/\"tenant\":\"([^\"]+)/");
        $this->policy = $this->http->FindPreg("/\"policy\":\"([^\"]+)/");

        if (!$this->transId || !$this->tenant || !$this->policy) {
            return $this->checkErrors();
        }
        $this->http->FormURL = "https://slhb2c.b2clogin.com{$this->tenant}/SelfAsserted?tx={$this->transId}&p={$this->policy}";
        $this->http->SetInputValue('signInName', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('request_type', 'RESPONSE');

        return true;
    }

    public function Login()
    {
        $csrf = $this->http->FindPreg("/csrf\":\"([^\"]+)/");

        if (!$csrf) {
            return $this->checkErrors();
        }
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-CSRF-TOKEN"     => $csrf,
            "X-Requested-With" => "XMLHttpRequest",
            "Origin"           => "https://slhb2c.b2clogin.com",
        ];
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status == 200) {
            $param = [
                'rememberMe' => 'true',
                'csrf_token' => $csrf,
                'tx'         => $this->transId,
                'p'          => $this->policy,
                'diags'      => '{"pageViewId":"f284bc18-12ae-4688-88e0-4351d4f11e09","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1586974399,"acD":1},{"ac":"T021 - URL:https://slh-prodsuk-b2c.azurewebsites.net/custompages/invited-signin.html?ui_locales=en","acST":1586974399,"acD":10},{"ac":"T029","acST":1586974399,"acD":10},{"ac":"T019","acST":1586974399,"acD":14},{"ac":"T004","acST":1586974399,"acD":3},{"ac":"T003","acST":1586974399,"acD":5},{"ac":"T035","acST":1586974399,"acD":0},{"ac":"T030Online","acST":1586974399,"acD":0},{"ac":"T002","acST":1586974412,"acD":0},{"ac":"T018T010","acST":1586974411,"acD":897}]}',
            ];
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://slhb2c.b2clogin.com{$this->tenant}/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));
            $this->http->RetryCount = 2;

            if ($this->http->ParseForm("auto")) {
                $this->http->PostForm();
            }

            if ($action = $this->http->FindPreg("/form action=\"(\/identity\/externallogin\?authenticationType[^\"]+)/")) {
                $this->http->NormalizeURL($action);
                $this->http->PostURL($action, []);

                if ($this->http->ParseForm("auto")) {
                    $this->http->PostForm();
                }

                // no auth  // AccountID: 5020206, 5188513, 5195299, 3770565
                if (
                    $this->http->currentUrl() == 'https://slhb2c.b2clogin.com/slhb2c.onmicrosoft.com/oauth2/v2.0/logout?p=B2C_1_slh.signup-and-signin_V1&redirect_uri=https://slh.com/error-pages/login-error'
                    // AccountID: 3929687
                    || $this->http->currentUrl() == 'https://slhb2c.b2clogin.com/slhb2c.onmicrosoft.com/oauth2/v2.0/logout?p=B2C_1A_signup_signin_Invited&redirect_uri=https://slh.com/error-pages/login-error'
                ) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }

            if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Scheduled maintenance')]")) {
                throw new CheckRetryNeededException(2, 10, $message);
            }

            return $this->loginSuccessful();
        }

        $message = $response->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Your password is incorrect.'
                || $message == 'The email address or password provided is incorrect.'
                || $message == 'We can\'t seem to find your account.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//input[@data-sc-field-name = "FirstName"]/@value') . " " . $this->http->FindSingleNode('//input[@data-sc-field-name = "LastName"]/@value')));

        $this->http->GetURL("https://slh.com/invited/my-invited");
        // Membership number
        $this->SetProperty("MembershipNumber", $this->http->FindSingleNode('//p[contains(text(), "Membership number:")]', null, false, '/:\s*([0-9]+)$/'));
        // Status
        if ($status = $this->http->FindSingleNode('//p[contains(text(), "Current tier level")]/following-sibling::img/@src')) {
            $status = basename($status);
            $status = $this->http->FindPreg("/(.+)-logo-no-padding/", false, $status);
            $this->logger->debug("Status: '{$status}'");

            switch ($status) {
                case 'inspired':
                    $this->SetProperty("Status", "Inspired");

                    break;

                case 'indulged':
                    $this->SetProperty("Status", "Indulged");

                    break;

                case 'intrigued':
                    $this->SetProperty("Status", "Intrigued");

                    break;

                default:
                    $this->sendNotification("Unknown status: {$status}");
            } // switch ($status)
        }
        // Tier Expiry Date
        $this->SetProperty("TierExpiryDate", $this->http->FindSingleNode('//p[contains(text(), "Tier Expiry:")]/strong'));
        // Balance - Nights stayed
        $this->SetBalance($this->http->FindSingleNode('//p[contains(text(), "Nights stayed")]/preceding-sibling::div[1]'));
        // Nights to next tier
        $this->SetProperty("NightsToNextTier", $this->http->FindSingleNode('//p[contains(text(), "Nights to next tier")]/preceding-sibling::div[1]'));
        // Rewards
        $this->SetProperty("Rewards", $this->http->FindSingleNode('//p[contains(text(), "Reward(s) available")]/preceding-sibling::div[1]'));

        if (!isset($this->Properties['Rewards']) || $this->Properties['Rewards'] == 0) {
            return;
        }
        $this->logger->info('My Rewards', ['Header' => 3]);
        // refs #16822
        $this->http->GetURL("https://slh.com/invited/my-rewards");
        // Reward Night Vouchers
        $vouchers = $this->http->XPath->query('//div[contains(@class, "sc-invited-rewards__voucher-item")]');
        $this->logger->debug("Total {$vouchers->length} vouchers were found");

        foreach ($vouchers as $voucher) {
            // Voucher Code
            $displayName = $this->http->FindSingleNode(".//span[contains(text(), 'Voucher Code:')]/following-sibling::span/b", $voucher);
            unset($balance);

            if ($balanceImg = $this->http->FindSingleNode(".//img[contains(@class, 'sc-invited-rewards__system-icon')]/@src", $voucher)) {
                $balanceImg = basename($balanceImg);
                $balance = $this->http->FindPreg("/(.+)\?rev=/", false, $balanceImg);
                $this->logger->debug("balance: '{$balance}'");

                switch ($balance) {
                    case 'invited-voucher-reward.ashx':
                        $balance = '$300';

                        break;

                    default:
                        $this->sendNotification("Unknown balance: {$balanceImg}");
                } // switch ($status)
            }// if ($balanceImg = $this->http->FindSingleNode(".//img[contains(@class, 'sc-invited-rewards__system-icon')]/@src", $voucher))
            $exp = strtotime($this->http->FindSingleNode(".//span[contains(text(), 'Expiry:')]/following-sibling::span/b", $voucher));

            if (isset($balance, $displayName) && $exp) {
                $this->AddSubAccount([
                    "Code"           => 'slhVoucher' . $exp . str_replace('$', 'Bucks', $balance),
                    "DisplayName"    => "Voucher Code: {$displayName}",
                    "Balance"        => $balance,
                    "ExpirationDate" => $exp,
                ]);
            }
        }// foreach ($vouchers as $voucher)
    }

    public function ParseItineraries(): array
    {
        $this->http->RetryCount = 0;
        $itinListUrl = 'https://slh.com/api/sf_getloggedinreservations?pageIndex=0&resultsPerPage=100';
        $this->http->GetURL($itinListUrl);

        if ($this->http->Response['code'] === 500 && $this->http->FindPreg('/^\{"Message":"An error has occurred."\}$/')) {
            $this->http->JsonLog($this->http->FindPreg("/(\{\"totalResults.+)$/"));
            $this->logger->error('Retrying to get hotels: internal error');
            sleep(5);
            // strange provider behavior
            $this->http->GetURL($itinListUrl);
            $this->http->JsonLog($this->http->FindPreg("/(\{\"totalResults.+)$/"));

            if ($this->http->Response['code'] === 500 && $this->http->FindPreg('/^\{"Message":"An error has occurred."\}$/')) {
                if ($this->Balance === '0') {
                    return $this->noItinerariesArr();
                }

                return [];
            }
        }
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog($this->http->FindPreg("/(\{\"totalResults.+)$/"));
        $items = $response->items ?? [];
        $totalResults = $response->totalResults ?? null;
        $futureResults = 0;

        foreach ($items as $item) {
            if ($this->ParsePastIts == false && $item->active == false) {
                $this->logger->debug("Skip old itinerary #{$item->reservationCode}");

                continue;
            }

            if ($item->active == true) {
                $futureResults++;
                $arFields = [
                    'ConfNo'       => $item->reservationCode,
                    'EmailAddress' => $this->AccountFields['Login'],
                ];
                $it = [];
                $this->CheckConfirmationNumberInternal($arFields, $it);
            } else {
                $this->parseItineraryNew($item);
            }
        }// foreach ($items as $item)

        if (
            ($totalResults > 0 && $futureResults == 0 && $this->ParsePastIts == false)
            || $totalResults === 0
        ) {
            return $this->noItinerariesArr();
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"       => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "EmailAddress" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('Email'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://be.synxis.com/signIn?chain=22402&locale=en-GB&filter=CHAIN&guestemail=CHAIN&level=chain&src=CHAIN&themecode=slhtheme';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->GetURL($this->ConfirmationNumberURL($arFields))) {
            if (
                $this->http->FindPreg('#<head>\s*<META NAME="robots" CONTENT="noindex,nofollow">\s*<script src="/_Incapsula_Resource\?SWJIYLWA=[^\"]+">\s*</script>\s*<body>#')
                || empty($this->http->Response['body'])
            ) {
                $this->selenium($this->ConfirmationNumberURL($arFields));
            }

            if ($this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")) {
                $this->incapsula();
                $this->incapsula();
            }
        }
        $cid = $this->http->FindPreg("/m\['cid'\]=(\d+);/");
        $sid = $this->http->FindPreg("/m\['sid'\]='([\w\-]+)';/");

        if (!isset($cid, $sid)) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }
        $this->parseItinerary($arFields['ConfNo'], $sid, $cid, $arFields['EmailAddress']);

        if ($this->http->FindPreg('/,"ItineraryList":\[\],/')) {
            return 'We apologise. We cannot locate your reservation. Please check your information and try again.';
        }

        return null;
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

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->Response['code'] == 200 && $this->http->FindNodes("//a[contains(@href, 'Logout')]")) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->Response['code'] == 404 && $this->http->FindPreg("/The resource you are looking for has been removed, had its name changed, or is temporarily unavailable./")) {
            $this->http->GetURL("https://www.slh.com/");
        }

        // provider error
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Hang in there!')]") && strstr($this->http->currentUrl(), 'error500.html?aspxerrorpath=')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // We are currently down for maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(text(),'We are currently down for maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h1[normalize-space(text()) = 'Scheduled maintenance']")) {
            throw new CheckException($message . ". Please visit us again soon.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(),'We are in the process of making some changes to the website and access to INVITED will be down during this time')]")) {
            throw new CheckException("We are in the process of making some changes to the website and access to INVITED will be down during this time.", ACCOUNT_PROVIDER_ERROR);
        }
        /**
         * Sorry, something unexpected happened.
         * We will try to resolve this issue as soon as possible.
         */
        if ($message = $this->http->FindSingleNode('//div[h1[contains(text(), "Sorry, something unexpected happened.")]]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function incapsula($isRedirect = true)
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
        }// if (isset($distil))
        $this->logger->debug("parse captcha form");
        $formURL = $this->http->FindPreg("/\"POST\", \"(\/_Incapsula_Resource\?[^\"]+)\"\s*,/");

        if (!$formURL) {
            return false;
        }
        $captcha = $this->parseHCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->NormalizeURL($formURL);
        $this->http->FormURL = $formURL;
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->http->SetInputValue('h-captcha-response', $captcha);
        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        if ($isRedirect) {
            $this->http->GetURL($referer);

            if ($this->http->Response['code'] == 503) {
                $this->http->GetURL($this->http->getCurrentScheme() . "://" . $this->http->getCurrentHost());
                sleep(1);
                $this->http->GetURL($referer);
            }
        }

        return true;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class = 'form_container']/div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->increaseTimeLimit(300);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, true, 3, 1);

        $this->increaseTimeLimit($recognizer->RecognizeTimeout);

        return $captcha;
    }

    private function selenium($url)
    {
        $this->logger->notice(__METHOD__);
        /** @var TAccountCheckerSlh */
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL($url);
            //sleep(1);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            // save page to logs
            $selenium->http->SaveResponse();
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }
    }

    private function parseItineraryNew($item)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($item->detailUrl)) {
            $this->logger->error('Skip: empty url');

            return;
        }
        $number = $item->reservationCode ?? null;
        $this->logger->info("Parse Itinerary #{$number}", ['Header' => 3]);

        $hotel = $this->itinerariesMaster->createHotel();
        $hotel->general()->confirmation($number);
        $hotel->hotel()->name($item->title);

        $detailUrl = 'https://slh.com' . $item->detailUrl;
        //$this->http->NormalizeURL($detailUrl);
        $this->http->GetURL($detailUrl);
        // Address
        $hotel->hotel()->address(join(', ', $this->http->FindNodes('//div[contains(@class, "sc-hotel-location__hotel-headline")]/following-sibling::p[position() > 1]')));
        // check in date
        $checkInTime = $this->http->FindSingleNode('//p[contains(text(), "Check in:")]/following-sibling::h5');
        $this->logger->debug("[check in time]: {$checkInTime}");

        if (strlen($checkInTime) == 4) {
            $checkInTime = preg_replace("#^(\d{2})(\d{2})$#", '$1:$2', $checkInTime);
            $this->logger->debug("[check in time]: {$checkInTime}");
        }

        if ($checkInTime) {
            $hotel->booked()->checkIn2($item->checkIn . ', ' . $checkInTime);
        }
        // check out date
        $checkOutTime = $this->http->FindSingleNode('//p[contains(text(), "Check out:")]/following-sibling::h5');
        $this->logger->debug("[check out time]: {$checkOutTime}");

        if (strlen($checkOutTime) == 4) {
            $checkOutTime = preg_replace("#^(\d{2})(\d{2})$#", '$1:$2', $checkOutTime);
            $this->logger->debug("[check out time]: {$checkOutTime}");
        }

        if ($checkOutTime) {
            $hotel->booked()->checkOut2($item->checkOut . ', ' . $checkOutTime);
        }

        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($hotel->toArray(), true), ['pre' => true]);
    }

    private function parseItinerary($number, $sid, $cid, $email)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Parse Itinerary #{$number}", ['Header' => 3]);
        $data = json_encode([
            'version'     => '0.0',
            'Hotel'       => (object) [],
            'Chain'       => ['id' => $cid],
            'ChannelList' => ['PrimaryChannel' => ['Code' => 'WEB'], 'SecondaryChannel' => ['Code' => 'GC']],
            'Query'       => [
                'Itinerary'   => (object) [],
                'Reservation' => [
                    'BookingAgent'           => ['BookerProfile' => ['id' => '']],
                    'GuestList'              => ['Guest' => ['EmailAddress' => $email]],
                    'CRS_confirmationNumber' => $number,
                ],
                'IgnorePendingChanges' => true,
            ],
            'UserDetails' => ['Preferences' => ['Language' => ['code' => 'en-GB']]],
        ]);
        $headers = [
            'Accept'          => 'application/json,application/x-javascript',
            'Content-Type'    => 'application/json; charset=utf-8',
            'Accept-Language' => 'undefined',
            'activityid'      => $sid,
            'context'         => 'BE',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://be.synxis.com/gw/itinerary/v1/queryReservation', $data, $headers);
        $response = $this->http->JsonLog();
        $this->http->RetryCount = 2;

        if (!isset($response->ReservationList[0])) {
            return;
        }

        if (count($response->ReservationList) > 1) {
            $this->sendNotification("itineraries were found // MI");
        }
        $rl = $response->ReservationList[0];
        $hotel = $this->itinerariesMaster->createHotel();
        $hotel->general()->confirmation($rl->CRS_confirmationNumber);
        $hotel->general()->status($rl->Status);

        if ($rl->Status == 'Cancelled') {
            $hotel->general()->cancelled();
        }

        foreach ($rl->GuestList as $elem) {
            $hotel->general()->traveller(join(' ', (array) $elem->PersonName));
        }

        $hotel->hotel()->name($rl->Hotel->Name);
        $hotel->hotel()->address(join(', ', array_filter($rl->Hotel->BasicPropertyInfo->Address->AddressLine))
            . ', ' . $rl->Hotel->BasicPropertyInfo->Address->City . ', ' . $rl->Hotel->BasicPropertyInfo->Address->PostalCode
        );

        foreach ($rl->RoomStay->GuestCount as $elem) {
            if ($elem->AgeQualifyingCode == 'Child') {
                $hotel->booked()->kids($elem->NumGuests);
            } elseif ($elem->AgeQualifyingCode == 'Adult') {
                $hotel->booked()->guests($elem->NumGuests);
            }
        }
        $hotel->setCancellation($rl->CancelPolicy->Description);

        // Reservations must be cancelled by 6PM (local time) 1 day prior to arrival to avoid 1 night penalty fee.
        // Cancel by 3PM - local time - 1 day prior to arrival to avoid 1 night penalty fee.
        // Cancel up to 14:00 on arrival day (local time) to avoid 1 night penalty fee.
        $cancellation = $hotel->getCancellation();

        if (isset($cancellation, $rl->CancelPolicy->CancelPenaltyDate)) {
            if ($this->http->FindPreg('/(?:prior to arrival to avoid \d+(?: night|(?:pct|%) of total stay) penalty fee\.|prior to arrival to avoid 100% of total stay penalty fee\.|^Cancellations fees: no penalty up to \d+.M \(local\) - \d+ days prior to arrival|Cancel up to \d+:\d+ on arrival day \(local time\) to avoid \d+ nights? penalty fee|^Reservations must be cancelled up to \d+.M on arrival day, local time, to avoid \d+ nights? penalty fee\.|Cancel up to \d+.m on arrival day - local time - to avoid \d+ nights? penalty fee\.|Cancel by \d+\wM - local time - \d+ days? prior to arrival to avoid a penalty charge.)/i', false, $cancellation)) {
                $hotel->booked()->deadline2($rl->CancelPolicy->CancelPenaltyDate);
            } else {
                $this->sendNotification('Need to check deadline');
            }
        } elseif (isset($cancellation) && $this->http->FindPreg('/100% of the total stay will be charged as penalty fee in case of cancellation\./i', false, $cancellation)) {
            $hotel->booked()->nonRefundable();
        }

        if (isset($rl->RoomPriceList->TotalPrice->Price->CurrencyCode)) {
            $hotel->price()->currency($rl->RoomPriceList->TotalPrice->Price->CurrencyCode);
            $hotel->price()->total($rl->RoomPriceList->TotalPrice->Price->TotalAmount);
        }

        $data = json_encode([
            'version'        => 1,
            'Hotel'          => ['id' => $rl->Hotel->Id],
            'PrimaryChannel' => ['code' => 'WEB'],
            'UserDetails'    => ['Preferences' => ['Language' => ['code' => 'en-GB']]],
            'Chain'          => ['id' => $cid],
        ]);
        $this->http->PostURL('https://be.synxis.com/gw/partner/v1/GetHotelDetails', $data, $headers);
        $hotelDetails = $this->http->JsonLog(null, 0);

        // check in date
        $checkInTime = null;
        $checkInCheckOut = $hotelDetails->Hotel->BasicPropertyInfo->PropertyInfo->CheckInCheckOut ?? null;
        $this->logger->debug("[CheckInCheckOut]: {$checkInCheckOut}");

        if ($checkInCheckOut) {
            $checkInTime = $this->http->FindPreg('/In Time:? (?:\d+:\d+ |)\(?(\d+:\d+ [AP]M)\)?/ims', false, $checkInCheckOut);
        }

        if (!$checkInTime) {
            $checkInTime = $hotelDetails->Hotel->PolicyList->LocalPolicyList->CheckIn;

            if (!strtotime($checkInTime)) {
                $checkInTime = $this->http->FindPreg('/^(.+?)\s+/', false, $checkInTime);
            }
        }
        $this->logger->debug("[check in time]: {$checkInTime}");

        if ($checkInTime) {
            $hotel->booked()->checkIn2($this->http->FindPreg('/^(.+?)T/', false, $rl->RoomStay->StartDate) . ', ' . $checkInTime);
        }
        // check out date
        $checkOutTime = null;

        if ($checkInCheckOut) {
            $checkOutTime = $this->http->FindPreg('/Out Time:? (?:\d+:\d+ |)\(?(\d+:\d+ [AP]M)\)?/ims', false, $checkInCheckOut);
        }

        if (!$checkOutTime) {
            $checkOutTime = $hotelDetails->Hotel->PolicyList->LocalPolicyList->CheckOut;

            if (!strtotime($checkOutTime)) {
                $checkOutTime = $this->http->FindPreg('/^(.+?)\s+/', false, $checkOutTime);
            }
        }
        $this->logger->debug("[check out time]: {$checkOutTime}");

        if ($checkOutTime) {
            $hotel->booked()->checkOut2($this->http->FindPreg('/^(.+?)T/', false, $rl->RoomStay->EndDate) . ', ' . $checkOutTime);
        }

        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($hotel->toArray(), true), ['pre' => true]);
    }
}
