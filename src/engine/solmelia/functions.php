<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSolmelia extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    private const REWARDS_PAGE_URL = "https://www.melia.com/en/meliarewards/dashboard";
    private const CONFIGS = [
        /*
        'firefox-100' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => SeleniumFinderRequest::FIREFOX_100,
        ],
        'chrome-100' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_100,
        ],
        'chromium-80' => [
            'agent'           => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36",
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROMIUM,
            'browser-version' => SeleniumFinderRequest::CHROMIUM_80,
        ],
        'chrome-84' => [
            'agent'           => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36",
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_84,
        ],
        'chrome-95' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_95,
        ],
        'puppeteer-103' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME_PUPPETEER,
            'browser-version' => SeleniumFinderRequest::CHROME_PUPPETEER_103,
        ],
        /*
        'firefox-84' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => SeleniumFinderRequest::FIREFOX_84,
        ],
        */
        'chrome-94-mac' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_94,
        ],
        /*
        'firefox-playwright-100' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
            'browser-version' => SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_100,
        ],
        'firefox-playwright-102' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
            'browser-version' => SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_102,
        ],
        'firefox-playwright-101' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
            'browser-version' => SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_101,
        ],
        */
    ];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $currentItin = 0;
    private $config;
    private $authRequest;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        // refs #13486
        /*
        $this->http->SetProxy($this->proxyDOP());
        */
        $this->http->setHttp2(true);
        /*
        $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_EU));
        */
        $this->setProxyGoProxies(null, 'es');

        /*
        $this->http->setDefaultHeader("User-Agent", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/604.4.7 (KHTML, like Gecko) Version/11.0.2 Safari/604.4.7");
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        */
    }

    public function IsLoggedIn()
    {
        unset($this->State['baseURL']);

        if (!isset($this->State['authentication'])) {
            return false;
        }

//        $this->http->RetryCount = 0;
//        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
//        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        return $this->selenium();
    }

    public function LoadLoginFormOld()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.melia.com/en/login");

        if ($this->http->Response['code'] != 200) {
            if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            return $this->checkErrors();
        }

        $this->AccountFields['Pass'] = substr($this->AccountFields['Pass'], 0, 25); // AccountID: 6182590

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->selenium();

//        $this->http->setCookie("_abck", "9F8D4DDAF284B11CC1BB8A789B15D8DE~0~YAAQFOHdF1yiU/eJAQAALeNtJgr0JDVATtdnNEjlFFxcjrxkZCuyMhjr2S2bEb9Gi6zDUinMwGI24mN00RMVH5FyRdfds0JlMk6n4cHURX+cgOHCw0eYgxbb0u/Owqw7JANU9mwYW1oB4WcNTv/r4DGxqu6boXbjnPnkj5HelP5BkhOqusikp2F8vid3ztUkIxPI0x633AUoIP8LtC19eKlu5s3miOMg0N/3lSBgJ9GnOUUoOHNEaf6AeKc+c4V7z6ACIHOyarisvx5ssi/+xwWf4EDFVtZNr0+fn4tSV1F7vFyNIbMTmudDGz0d4f8VqblCZ9lIhl1cLmfetNS3WWtamWxufkBnPkgHYQiDr0g9h1kK2/MghxjU4j1+zjZhupu4UrIPqFoF7q8puJAzCSmP3ixU0411~-1~||-1||~-1", ".melia.com");

        $data = '{"operationName":"getToken","variables":{"user":"' . $this->AccountFields['Login'] . '","password":"' . str_replace('"', '\"', $this->AccountFields['Pass']) . '","lang":"en","countryNavigation":"US","rememberMe":true},"query":"query getToken($user: String!, $password: String!, $lang: String!, $countryNavigation: String!, $rememberMe: Boolean) {\n  getUserAccessToken(\n    origin: MELIA_COM\n    userType: CLIENT\n    lang: $lang\n    countryNavigation: $countryNavigation\n    auth: {username: $user, password: $password, rememberMe: $rememberMe}\n  ) {\n    accessToken\n    refreshToken\n    secondsToExpireAccessToken\n    secondsToExpireRefreshToken\n  }\n}\n"}';
        $headers = [
            "Accept"         => "*/*",
            "content-type"   => "application/json",
            "authentication" => "Basic bWVsaWEtY29tOnYzckFTPHkyYz92fXArTFs",
            "Origin"         => "https://www.melia.com",
            "Countrycaptcha" => "US",
            "Tokencaptcha"   => $captcha,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://gateway.melia.com/loyalty-customer/graphql", $data, $headers);
        $this->http->RetryCount = 2;

        if (
            $this->http->Response['code'] == 403
            && $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
        ) {
            throw new CheckRetryNeededException(2);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Lo sentimos, estamos trabajando en tareas de mantenimiento, esperamos estar de vuelta muy pronto.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog($this->authRequest);

        if (isset($response->data->getUserAccessToken->accessToken)) {
            $this->captchaReporting($this->recognizer);
            $this->State['authentication'] = "Bearer {$response->data->getUserAccessToken->accessToken}";

            return $this->loginSuccessful();
        }

        $message =
            $response->errors[0]->message
            ?? $response->message
            ?? null
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");
            $this->captchaReporting($this->recognizer);

            if (
                $message == 'Invalid credentials'
                || $message == 'ServiceException: Invalid credentials'// AccountID: 6338713
                || $message == 'Origin is mandatory'// AccountID: 4852919
            ) {
                throw new CheckException("The data doesn’t match or is incorrect. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'For security, we have sent you an email with a link to update your password'
                || $message == 'ServiceException: For security, we have sent you an email with a link to update your password'
            ) {
                $this->throwProfileUpdateMessageException();
            }

            if (
                $message == 'An error has occurred processing your request'
                || $message == 'ServiceException: An error has occurred processing your request'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $message == 'Internal Server Error'
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $code = $response->errors[0]->code ?? null;

        if ($code) {
            $this->logger->error("[Error code]: {$code}");

            if ($code == 'GENERIC_ERROR') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->captchaReporting($this->recognizer);

            if (in_array($code, [
                'CUSTOMER_ACTIVE_SIRIUS_PENDING_COGNITO',
                'INVALID_CREDENTIALS',
            ])) {
                throw new CheckException("The data doesn’t match or is incorrect. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $code;

            return false;
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $customerById = $response->data->customerById;
        // Name
        $this->SetProperty("Name", beautifulName($customerById->firstName . " " . $customerById->lastName1));
        // Balance - MeliáRewards points
        $loyaltyCard = $customerById->loyaltyCard;
        $this->SetBalance($loyaltyCard->points);
        // Card number
        $this->SetProperty("Number", $loyaltyCard->cardNumber);
        // Status
        $cardType = $loyaltyCard->cardType;

        switch ($cardType) {
            case 'M':
                $status = 'White';

                break;

            case 'S':
                $status = 'Silver';

                break;

            case 'O':
                $status = 'Gold';

                break;

            case 'P':
                $status = 'Platinum';

                break;

            default:
                $this->sendNotification("Unknown status: {$cardType}");
                $status = '';
        }
        $this->SetProperty("Status", $status);

        /*
        // Expiration Date
        $exp = $this->http->FindSingleNode('//small[contains(text(), "Expire the") or contains(text(), "They expire on")]/strong', null, true, "#\d+/\d+/\d{4}#");

        if (!$exp) {
            $exp = $this->http->FindSingleNode('//small[contains(text(), "They expire")]/text()[last()]');
        }
        $exp = $this->ModifyDateFormat($exp);

        if ($exp = strtotime($exp)) {
            $this->SetExpirationDate($exp);
        }
        */
    }

    public function ParseItineraries()
    {
        $minDate = date("Y-m-d");
        $data = '{"operationName":"getReservationList","variables":{"status":"A","minDate":"' . $minDate . '"},"query":"query getReservationList($status: String, $minDate: String) {\n  reservationList(status: $status, arrivalDate: $minDate) {\n    locator\n    checkIn\n    checkOut\n    nights\n    hotel {\n      code\n      title\n      categoryDescription\n      address {\n        location\n      }\n      galleryImages {\n        gallery\n      }\n    }\n    origin\n    amount\n    currencyCode\n    totalPoints\n    status\n    pointsAmount\n    pointsRedeemed\n    externalLocator\n    externalLocatorURL\n    salesOfficeCode\n  }\n}\n"}';
        $this->http->PostURL("https://gateway.melia.com/loyalty-booking/graphql", $data);
        $response = $this->http->JsonLog();
        $reservationList = $response->data->reservationList ?? [];

        if ($this->ParsePastIts) {
            $data = '{"operationName":"getReservationList","variables":{},"query":"query getReservationList($status: String, $minDate: String) {\n  reservationList(status: $status, arrivalDate: $minDate) {\n    locator\n    checkIn\n    checkOut\n    nights\n    hotel {\n      code\n      title\n      categoryDescription\n      address {\n        location\n      }\n      galleryImages {\n        gallery\n      }\n    }\n    origin\n    amount\n    currencyCode\n    totalPoints\n    status\n    pointsAmount\n    pointsRedeemed\n    externalLocator\n    externalLocatorURL\n    salesOfficeCode\n  }\n}\n"}';
            $this->http->PostURL("https://gateway.melia.com/loyalty-booking/graphql", $data);
            $response = $this->http->JsonLog();
            $reservationList = array_merge($reservationList, $response->data->reservationList ?? []);
        }

        if ($this->http->Response['body'] == '{"data":{"reservationList":[]}}') {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        foreach ($reservationList as $item) {
            $this->ParseItinerary($item);
            $this->currentItin++;
        }

        return [];
    }

    public function ParseItinerary($reservation)
    {
        $this->logger->notice(__METHOD__);
        $conf = $reservation->locator;
        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$conf}", ['Header' => 3]);

        // fix for past itineraries
        if (($reservation->hotel === null || $reservation->hotel->title === null) && strtotime($reservation->checkOut) < time()) {
            $this->logger->info("[{$this->currentItin}] skip old itinerary #{$conf} without hotel info", ['Header' => 4]);
            $this->logger->notice("skip old itinerary without hotel info");

            return;
        }

        $h = $this->itinerariesMaster->createHotel();
        $h->general()->confirmation($conf, "Booking reference number");

        $h->booked()
            ->checkIn2($reservation->checkIn)
            ->checkOut2($reservation->checkOut)
        ;

        $h->price()
            ->total($reservation->amount, false, true)
            ->currency($reservation->currencyCode)
            ->spentAwards($reservation->pointsRedeemed)
        ;

        if (!empty($reservation->pointsAmount)) {
            $h->program()->earnedAwards($reservation->pointsAmount);
        }

        if ($reservation->status == 'CANCELLED' && $reservation->status == 'CANCELED') {
            $h->general()->status(beautifulName($reservation->status));
            $h->general()->cancelled();
            $this->logger->debug('Parsed Itinerary:');
            $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
        }

        $h->hotel()
            ->name($reservation->hotel->title);

        if (!empty($reservation->hotel->address->location)) {
            $h->hotel()->address($reservation->hotel->address->location);
        }

        $data = '{"operationName":"getReservationDetailInfo","variables":{"localizer":"' . $conf . '","hotel":"' . $reservation->hotel->code . '","arrivalDate":"' . $reservation->checkIn . '","departureDate":"' . $reservation->checkOut . '","language":"en","partnerID":"DEFAULT","device":"desktop"},"query":"query getReservationDetailInfo($localizer: String!, $hotel: String!, $arrivalDate: Date!, $departureDate: Date!, $language: String!, $partnerID: String, $device: String) {\n  getReservationDetailInfo(\n    localizer: $localizer\n    hotel: $hotel\n    arrivalDate: $arrivalDate\n    departureDate: $departureDate\n    language: $language\n    partnerID: $partnerID\n    device: $device\n  ) {\n    hotel {\n      info {\n        uuid\n        code\n        descriptionClaim\n        title\n        hotelCategory {\n          displayName\n          image {\n            url\n            alt\n          }\n        }\n        latitude\n        longitude\n        emailContact\n        url\n        zipCode\n        primaryLocation\n        address\n        location\n        images {\n          url\n          alt\n        }\n        services {\n          name\n          displayName\n          image {\n            url\n            alt\n          }\n        }\n        amenities {\n          kind\n          title\n          distance\n        }\n      }\n    }\n    freeDynamicServices {\n      code\n      title\n      dynamicServices {\n        code\n        title\n        totalQty\n      }\n    }\n    roomStay {\n      rateQuote {\n        totalCountryTaxes {\n          value\n          currencyCode\n        }\n        totalAmountWithTax {\n          value\n          currencyCode\n        }\n        totalAmountWithoutTax {\n          value\n          currencyCode\n        }\n        totalAllotServAmount {\n          value\n          currencyCode\n        }\n      }\n      fidelityPoints {\n        stayPoints\n        pointsLoyaltyBeforeOffer\n      }\n      inventoryCode\n      roomCode {\n        baseRoomCode\n        mealPlan\n      }\n      roomInformation {\n        code\n        title\n      }\n    }\n    bookingRules {\n      cancelPenalties {\n        description\n      }\n    }\n    rateInfo {\n      conditions {\n        type\n        description\n        title\n      }\n    }\n    taxes {\n      code\n      percent\n      currency\n      amount\n    }\n    requestDetail {\n      requestedCurrencyCode\n      stayDateRange {\n        checkIn\n        checkOut\n        durationDays\n      }\n      occupancy {\n        rooms {\n          adults\n          childrenAges\n        }\n      }\n    }\n    extendedDetails {\n      clientName\n      isModifiable\n      isCancelable\n      webCheckin\n      reservationStatus\n      clientTelephone\n      clientEmail\n    }\n  }\n}\n"}';
        $this->http->PostURL("https://gateway.melia.com/booking/graphql", $data);
        $response = $this->http->JsonLog();
        $error = $this->http->FindPreg('/(?:"message":"Service exception:.+?RESERVATIONID DOES NOT MATCH"|"message":"Service exception: \w+ - INVALID AIRLINE CODE")/');
        $reservationDetailInfo = $response->data->getReservationDetailInfo ?? null;

        if (isset($reservationDetailInfo->hotel->info->address)) {
            $address =
                $reservationDetailInfo->hotel->info->address
                . " " . ($reservationDetailInfo->hotel->info->zipCode ?? null)
                . " " . ($reservationDetailInfo->hotel->info->primaryLocation ?? null)
            ;
        }

        $h->hotel()
            ->name($reservationDetailInfo->hotel->info->title ?? $reservation->hotel->title);

        if (!empty($address)) {
            $h->hotel()->address($address);
        }

        $status = $reservationDetailInfo->extendedDetails->reservationStatus ?? $reservation->status;

        switch ($status) {
            case 'A':
            case 'ACTIVE':
            case 'CONFIRMED':
                $h->general()->status("Confirmed");

                break;

            case 'E':
            case 'ENJOYED':
                $h->general()->status("Enjoyed");

                break;

            case 'C':
            case 'CANCELED':
            case 'CANCELLED':
                $h->general()
                    ->cancelled()
                    ->status("Cancelled");

                break;

            default:
                $this->sendNotification("Unknown status {$status} // MI");

                break;
        }

        if (!isset($reservationDetailInfo->requestDetail) && $error) {
            $this->logger->debug('Parsed Itinerary:');
            $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);

            return;
        }

        $h->booked()->rooms(count($reservationDetailInfo->requestDetail->occupancy->rooms));
        $guests = 0;

        foreach ($reservationDetailInfo->requestDetail->occupancy->rooms as $room) {
            $guests += $room->adults;
        }

        $h->booked()->guests($guests);

        $taxes = 0;

        foreach ($reservationDetailInfo->taxes ?? [] as $tax) {
            $taxes += $tax->amount;
        }

        $h->price()->tax($taxes);

        $cancelPenalties = $reservationDetailInfo->bookingRules->cancelPenalties->description ?? null;
        $h->general()->cancellation($cancelPenalties, false, true);

        if (strstr($cancelPenalties, 'will be charged as cancellation penalty')) {
            $h->booked()->nonRefundable();
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = '6Lf4fvciAAAAAK-gukUvV3cdhY8HM-ECrDRXUzCr';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $postData = [
            "type"        => "RecaptchaV2TaskProxyless",
            "websiteURL"  => 'https://www.melia.com/en/login',
            "websiteKey"  => $key,
            "isInvisible" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        /*
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
        */
    }

    private function markConfigAsBadOrSuccess($success = true): void
    {
        if ($success) {
            $this->logger->info("marking config {$this->config} as successful");
            Cache::getInstance()->set('solmelia_config_' . $this->config, 1, 60 * 60);
        } else {
            $this->logger->info("marking config {$this->config} as bad");
            Cache::getInstance()->set('solmelia_config_' . $this->config, 0, 60 * 60);
        }
    }

    private function setConfig()
    {
        $configs = self::CONFIGS;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            unset($configs['chrome-94-mac']);
        }

        /*
        $successfulConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('solmelia_1_config_' . $key) === 1;
        });

        $neutralConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('solmelia_1_config_' . $key) !== 0;
        });

        if (count($neutralConfigs) > 0) {
            $this->config = $neutralConfigs[array_rand($neutralConfigs)];
            $this->logger->info("found " . count($neutralConfigs) . " successful configs");
        }
        */

        /*
        if (count($successfulConfigs) > 0) {
            $this->config = $successfulConfigs[array_rand($successfulConfigs)];
            $this->logger->info("found " . count($successfulConfigs) . " successful configs");
        } elseif (count($neutralConfigs) > 0) {
            $this->config = $neutralConfigs[array_rand($neutralConfigs)];
            $this->logger->info("found " . count($neutralConfigs) . " neutral configs");
        } else {
            $this->config = array_rand($configs);
        }
        */

        $this->config = array_rand($configs);

        $this->logger->info("selected config $this->config");
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $headers = [
            "Accept"         => "*/*",
            "content-type"   => "application/json",
            "authentication" => $this->State['authentication'],
            "tokentype"      => "userToken",
            "Origin"         => "https://www.melia.com",
        ];
        $data = '{"operationName":"getNavInfo","variables":{},"query":"query getNavInfo {\n  customerById {\n    idSirius: id\n    firstName\n    lastName1\n    lastName2\n    email\n    nationality\n    loyaltyCard {\n      cardType\n      cardGroup\n      cardNumber\n      points\n      stakeholder {\n        code\n      }\n      valuePoint\n      houseAccount\n    }\n    contactInformation {\n      telephonePrefix\n      telephoneNumber\n      provinceCode\n      postalCode\n    }\n    dataAnalytics {\n      emailMD5\n      emailSHA256\n    }\n  }\n}\n"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://gateway.melia.com/loyalty-customer/graphql", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, 'cardNumber');

        $email = $response->data->customerById->email ?? null;
        $this->logger->debug("[Email]: {$email}");
        $cardNumber = $response->data->customerById->loyaltyCard->cardNumber ?? null;
        $this->logger->debug("[cardNumber]: {$cardNumber}");

        if (
            ($email && strtolower($email) == strtolower($this->AccountFields['Login']))
            || ($cardNumber && strstr(strtolower($this->AccountFields['Login']), strtolower($cardNumber)))
        ) {
            foreach ($headers as $header => $value) {
                $this->http->setDefaultHeader($header, $value);
            }

            return true;
        }

        return false;
    }

//    function GetConfirmationFields() {
//        return array(
//            "ConfNo" => array(
//                "Caption" => "Confirmation number",
//                "Type" => "string",
//                "Size" => 20,
//                "Required" => true,
//            ),
//            "HotelName" => array(
//                "Caption" => "First Name",
//                "Type" => "string",
//                "Size" => 64,
//                "Value" => $this->GetUserField('FirstName'),
//                "Required" => true,
//            ),
//            "CheckInDate" => array(
//                "Caption" => "Last Name",
//                "Type" => "string",
//                "Size" => 64,
//                "Value" => $this->GetUserField('CheckInDate'),
//                "Required" => true,
//            ),
//            "CheckOutDate" => array(
//                "Caption" => "Last Name",
//                "Type" => "string",
//                "Size" => 64,
//                "Value" => $this->GetUserField('CheckInDate'),
//                "Required" => true,
//            ),
//        );
//    }
//
//    public function ConfirmationNumberURL($arFields) {
//        return "https://www.melia.com/en//manage-reservation";
//    }
//
//    private function notifications($arFields) {
//        $this->logger->notice(__METHOD__);
//        $this->sendNotification("carlson - failed to retrieve itinerary by conf #", 'all', true,
//            "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a>");
//    }
//
//    function CheckConfirmationNumberInternal($arFields, &$it) {
//        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
//        if (!$this->http->ParseForm("cancelForm")) {
//            $this->notifications($arFields);
//            return null;
//        }
//        $this->http->SetInputValue('localizerCancel', $arFields['ConfNo']);
//        $this->http->SetInputValue('hotelCancel', $arFields['HotelName']);
//        $this->http->SetInputValue('checkinCancel', $arFields['CheckInDate']);
//        $this->http->SetInputValue('checkoutCancel', $arFields['CheckOutDate']);
//        $this->http->PostForm();
//
//        if ($error = $this->http->FindSingleNode("//div[contains(text(), 'The reservation information you provided was not recognized. Did you enter it correctly?')]"))
//            return $error;
//
//        if ($res = $this->ParseItinerary($itinerary['checkIn'], $itinerary['checkOut'])) {
//            $it = $res;
//        }
//
//        return null;
//    }

    /*
    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $key = 'solmelia_abck';
        $result = Cache::getInstance()->get($key);

        if (!empty($result) && $this->attempt == 0) {
            $this->logger->debug("set _abck from cache: {$result}");

            $this->http->setCookie("_abck", $result, ".melia.com");

            return null;
        }

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useGoogleChrome();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www.melia.com/en/login");
//            $loginPopup = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "btnLogin"]'), 7);

            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if (!in_array($cookie['name'], [
                    //                    'bm_sz',
                    '_abck',
                ])) {
                    //continue;
                }

                $result = $cookie['value'];

                if (in_array($cookie['name'], [
                    //                    'bm_sz',
                    '_abck',
                ])) {
                    $this->logger->debug("set new _abck: {$result}");
                    Cache::getInstance()->set($key, $cookie['value'], 60 * 60 * 20);
                }

                $this->http->setCookie("_abck", $result, ".melia.com");
//                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (NoSuchDriverException | WebDriverException $e) {
            $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }
    */

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $WAIT_TIMEOUT = 10;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            $selenium->seleniumOptions->recordRequests = true;

            $this->setConfig();

            $resolutions = [
                [3840, 2160],
            ];

            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);

            $selenium->seleniumRequest->request(
                self::CONFIGS[$this->config]['browser-family'],
                self::CONFIGS[$this->config]['browser-version']
            );

            $selenium->usePacFile(false);
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->removeCookies();
            $selenium->http->GetURL('https://www.melia.com/en/login');

            if ($acceptCookiesButton = $selenium->waitForElement(WebDriverBy::xpath('//button[@id="didomi-notice-agree-button"]'), $WAIT_TIMEOUT)) {
                $acceptCookiesButton->click();
            }

            $loginButton = $selenium->waitForElement(WebDriverBy::xpath('//button[@id="access"]'), $WAIT_TIMEOUT);
            $this->savePageToLogs($selenium);

            if ($loginButton) {
                $loginButton->click();
            }
            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@id="user"]'), $WAIT_TIMEOUT);
            $password = $selenium->waitForElement(WebDriverBy::xpath('//input[@id="password"]'), 0);
            $this->savePageToLogs($selenium);

            if (!isset($login, $password)) {
                $this->markConfigAsBadOrSuccess(false);

                return $this->checkErrors();
            }
            $login->sendKeys($this->AccountFields['Login']);
            $password->sendKeys($this->AccountFields['Pass']);
            $login->click(); // prevent ElementClickInterceptedException
            $captcha = $this->parseReCaptcha();
            $selenium->driver->executeScript('document.getElementsByName("g-recaptcha-response")[0].value = "' . $captcha . '";');
            $submit = $selenium->waitForElement(WebDriverBy::xpath('//button[@id="submitBtn"]'), $WAIT_TIMEOUT);
            $this->savePageToLogs($selenium);

            if (!isset($submit)) {
                $this->markConfigAsBadOrSuccess(false);

                return $this->checkErrors();
            }
            $submit->click();
            $selenium->waitForElement(WebDriverBy::xpath('//dt[contains(text(), "Card number")]/following-sibling::dd | //p[contains(@class, "error")]'), $WAIT_TIMEOUT);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->getRecordedRequests($selenium);

            return true;
        } finally {
            $selenium->http->cleanup();
        }
    }

    private function getRecordedRequests($selenium)
    {
        $this->logger->notice(__METHOD__);

        try {
            $requests = $selenium->http->driver->browserCommunicator->getRecordedRequests();
        } catch (AwardWallet\Common\Selenium\BrowserCommunicatorException $e) {
            $this->logger->error("BrowserCommunicatorException: " . $e->getMessage(), ['HtmlEncode' => true]);

            $requests = [];
        }

        foreach ($requests as $xhr) {
            /*
            $this->logger->debug('[CATHED REQUEST URL]: ' . $xhr->request->getUri());
            $this->logger->debug('[CATHED REQUEST BODY]' . json_encode($xhr->request->getUri()));
            $this->logger->debug('[CATHED REQUEST BODY CONTAINS TOKEN]' . (int) (strstr(json_encode($xhr->response->getBody()), "getUserAccessToken")));
            */

            if (
                strstr($xhr->request->getUri(), 'loyalty-customer/graphql')
                && strstr(json_encode($xhr->response->getBody()), "getUserAccessToken")
                && !isset($this->authRequest)
            ) {
                $this->authRequest = json_encode($xhr->response->getBody());
                $this->logger->debug('Catched auth request');
            }
        }
    }
}
