<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;
use AwardWallet\ItineraryArrays\AirTrip;
use AwardWallet\ItineraryArrays\AirTripSegment;
use AwardWallet\MainBundle\Service\Itinerary\CarRental;
use AwardWallet\MainBundle\Service\Itinerary\HotelReservation;

class TAccountCheckerPriceline extends TAccountCheckerExtended
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const CONFIGS = [
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
        'firefox-84' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => SeleniumFinderRequest::FIREFOX_84,
        ],
        'chrome-94-mac' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_94,
        ],
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
    ];

    private $authtoken = null;
    private $seleniumAuthAfterAttempt = -1;

    private $arFields;

    private $config;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Reward']) && !isset($properties['CurrentPointTotal'])) {
            return $properties['Reward'];
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], $fields['BalanceFormat']);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;

        if ($this->attempt > 1) {
            $this->setProxyBrightData();
        } elseif ($this->attempt == 1) {
            $this->http->SetProxy($this->proxyReCaptcha());
        } else {
            /*
            $this->setProxyMount();
            $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
            */
        }

        $userAgentKey = "User-Agent";

        if (!isset($this->State[$userAgentKey]) || $this->attempt > 1) {
            $this->http->setRandomUserAgent(7);
            $agent = $this->http->getDefaultHeader("User-Agent");

            if (!empty($agent)) {
                $this->State[$userAgentKey] = $agent;
            }
        } else {
            $this->http->setUserAgent($this->State[$userAgentKey]);
        }

        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->selenium();

        if ($this->attempt > $this->seleniumAuthAfterAttempt) {
            return true;
        }

        /*
        $this->http->GetURL('https://www.priceline.com/profile/');
        if ($this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException(3);
        }
        */

        if (!$this->http->ParseForm('global-modal-sign-in-form') && !$this->http->FindPreg("/SIGN_IN_STREAMLINE_FORMS/")) {
            return false;
        }
        $data = [
            "authProvider" => [
                "username" => $this->AccountFields['Login'],
                "password" => $this->AccountFields['Pass'],
                "type"     => "pcln",
                "appCode"  => "DESKTOP",
            ],
            "rememberMe"   => "true",
            "httpReferer"  => "https://www.priceline.com/profile/#/login",
        ];
        $headers = [
            "Accept"       => "application/json",
            "Content-Type" => "application/json",
            "Origin"       => "https://www.priceline.com",
            "Referer"      => "https://www.priceline.com/profile/",
        ];
        $this->http->PostURL("https://www.priceline.com/svcs/eng/gblsvcs/v1/customer/authorize?fields=all", json_encode($data), $headers);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        $this->markConfigAsBadOrSuccess(false);

        if ($this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]")) {
            throw new CheckRetryNeededException(2, 0);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (!isset($response)) {
            $this->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));
        }

        // Access is allowed
        if (isset($response->customer->authtoken)) {
            $this->authtoken = $response->customer->authtoken;
            // Name
            $this->SetProperty("Name", beautifulName($response->customer->firstName . " " . $response->customer->middleName . " " . $response->customer->lastName));

            return true;
        }// if (isset($response->customer->authtoken))
        elseif (
            ($name = $this->http->FindSingleNode('//div[contains(@class, "ProfileOverview__MinWidthFlex")]/div[1]')
                    ?? $this->http->FindSingleNode('//div[@id = "sign-out-menu-greeting"]/div[@class = "user-first-name"]')
                    ?? $this->http->FindSingleNode('//div[@id = "vip-badge"]//span[@class = "user-first-name"]')
                )
            && !empty($this->authtoken)
        ) {
            // Name
            $this->SetProperty("Name", beautifulName($name));

            return true;
        }
        // Time to update your password!
        if ($this->http->FindPreg('/"exception":"PASSWORD EXPIRED",/')) {
            $this->throwProfileUpdateMessageException();
        }
        // Invalid Username/Password
        if ($this->http->FindPreg("/\"exception\":\"Please check logs.\"/ims")
            || $this->http->FindPreg("/\"exception\":\"USERNAME PASSWORD MISMATCH\"/ims")) {
            throw new CheckException("Invalid Username/Password", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Invalid email address") or contains(text(), "Email and password do not match.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg('/Something went wrong. Please refresh and try again./')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Invalid Username/Password
        if ($this->http->FindPreg("/\"exception\":\"Internal Server Error\"/ims") && in_array($this->AccountFields['Login'], ['bg809@nyu.edu', 'stayreal.jam@gmail.com', 'juliewaterston@hotmail.com'])) {
            throw new CheckException("Invalid Username/Password", ACCOUNT_INVALID_PASSWORD);
        }
        // Your Account is Locked
        if ($this->http->FindPreg("/\"exception\":\"EXCEEDED ALLOWED LOGIN ATTEMPTS\"/ims") || $this->http->FindSingleNode('//h2[contains(text(), "Unlock Account")]')) {
            throw new CheckException("We've temporarily locked your account after too many failed attempts to sign in. Please contact us to reset your password.", ACCOUNT_LOCKOUT);
        }
        // Hard Code
        if ($this->http->FindPreg('/"exception":"Internal Server Error"/') && in_array($this->AccountFields['Login'], ['perry-sperling@att.ner', 'ophe13@yahoo.fr', 'JLO250'])) {
            throw new CheckException("Invalid Username/Password", ACCOUNT_INVALID_PASSWORD);
        }

        if (
            (isset($this->http->Response['code']) && $this->http->Response['code'] == 403)
            || $this->http->FindSingleNode('//p[contains(text(), "Please confirm that you are a real Priceline user.")]')
            || $this->http->FindSingleNode('//button[contains(text(), "Signing In...")]')
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "infobox-error")]//p')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'There is no account with the Username')
                || strstr($message, 'Email and password do not match. Please try again or reset your password.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//div[contains(text(), "Your account is locked. Please enter your email to unlock it.")]')) {
            throw new CheckException("Your account is locked.", ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        /*
        $this->markConfigAsBadOrSuccess(true);
        $this->sendNotification('refs #24888 priceline - need to check selenium config // IZ');
        */

        if (!empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }

    public function seleniumItinerary()
    {
        $this->logger->notice(__METHOD__);

        $allCookies = array_merge($this->http->GetCookies(".priceline.com"), $this->http->GetCookies(".priceline.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies("www.priceline.com"), $this->http->GetCookies("www.priceline.com", "/", true));

        $selenium = clone $this;
        $responseData = null;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $r = rand(0, 0);
            //$r = 0;

            if ($r == 0) {
                $this->DebugInfo = 'FIREFOX_100';
                $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_100);
                $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->http->setUserAgent(HttpBrowser::FIREFOX_USER_AGENT);
                $selenium->setKeepProfile(true);
            } elseif ($r == 1) {
                $this->DebugInfo = 'CHROME_84';
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
            } elseif ($r == 2) {
                $this->DebugInfo = 'CHROMIUM_80';
                $selenium->useChromium(SeleniumFinderRequest::CHROMIUM_80);
            }

            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL('https://www.priceline.com/gfhjghj');

            foreach ($allCookies as $key => $value) {
                try {
                    $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".priceline.com"]);
                } catch (InvalidCookieDomainException $e) {
                    $this->logger->error("InvalidCookieDomainException: " . $e->getMessage());
                    $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
                }
            }
            $selenium->http->GetURL("https://www.priceline.com/next-profile/trips?tab=past");
            $upcomingTrips = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(),'Upcoming Trips')]"), 5);

            if ($upcomingTrips) {
                $selenium->driver->executeScript('
                const constantMock = window.fetch;
                window.fetch = function() {
                    console.log(arguments);
                    return new Promise((resolve, reject) => {
                        constantMock.apply(this, arguments)
                            .then((response) => {
                                if (response.url.indexOf("/pcln-graph/") > -1) {
                                    response
                                    .clone()
                                    .json()
                                    .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
                                }
                                resolve(response);
                            })
                            .catch((error) => {
                                reject(response);
                            })
                    });
                }
                ');
                $upcomingTrips->click();
            }

            $this->savePageToLogs($selenium);
            $selenium->waitForElement(WebDriverBy::id("UPCOMING"), 5);
//            $this->captchaPressHold($selenium);
            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);
            $this->http->SetBody($responseData);

//            $this->captchaPressHold($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
        }// catch (ScriptTimeoutException $e)
        catch (NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $retry = true;
        } catch (UnknownServerException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->markProxyAsInvalid();
            $retry = true;
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return $responseData;
    }

    public function ParseItineraries()
    {
        $this->logger->info(__METHOD__);
        // https://www.priceline.com/dashboard/#/mytrips
        //$this->seleniumItinerary();
        $headers = [
            "Accept"                       => "application/json",
            "Origin"                       => "https://www.priceline.com",
            "Content-Type"                 => "application/json",
            "Referer"                      => "https://www.priceline.com/next-profile/trips",
            "Apollographql-Client-Name"    => "next-profile",
            "Apollographql-Client-Version" => "development",
        ];
        $query = '{"query":"\nquery Bookings(\n  $offset: Int!, \n  $emailAddress: String!, \n  $authtoken: String!, \n  $limit: Int,\n  $plfCode: String,\n  $pclnToken: String,\n  $pclnTokenType: PclnTokenType\n) {\n  bookings(\n    offset: $offset, \n    emailAddress: $emailAddress, \n    authtoken: $authtoken, \n    limit: $limit,\n    plfCode: $plfCode,\n    pclnToken: $pclnToken,\n    pclnTokenType: $pclnTokenType\n  ) {\n    hasMoreBookings\n    bookings {\n      ... on PConnectedBookingsSummary {\n        type\n        components {\n          ... on PFlightSummary {\n            type\n            offerNumber\n            checkStatusUrl\n            isCanceled\n            header\n            dateHeader\n            isExpressDeal\n            cityName\n            scheduleInfo {\n              duration\n              numberOfStops\n              flightNumber\n              arrivalAirportCode\n              departureDate\n              departureTime\n              departureAirportCode\n              companyName\n              companyLogoUrl\n              layovers {\n                location\n                duration\n              }\n              isOvernightFlight\n              arrivalTime\n            }\n            isOpenJaw\n            crossSellType\n            crossSellUrl\n            offerId\n            travelStartDate\n            creditAmount\n            creditExpiry\n            isRebookDisabled\n          }\n          ... on PHotelSummary {\n            type\n            offerNumber\n            checkStatusUrl\n            isCanceled\n            isExpressDeal\n            dateHeader\n            title\n            imageUrl\n            subTitle\n            rating\n            cityName\n            originalUrl\n            crossSellType\n            crossSellUrl\n            offerId\n            travelStartDate\n          }\n          ... on PPackageSummary {\n            type\n            checkStatusUrl\n            isCanceled\n            isExpressDeal\n            travelStartDate\n            offerId\n            bundleComponents {\n              ... on HotelSummary {\n                type\n                isCanceled\n                isExpressDeal\n                imageUrl\n                originalUrl\n                title\n                subTitle\n                rating\n                dateHeader\n                cityName\n                stateName\n                countryName\n              }\n              ... on FlightSummary {\n                type\n                isCanceled\n                isExpressDeal\n                header\n                dateHeader\n                cityName\n                isOpenJaw\n                scheduleInfo {\n                  duration\n                  numberOfStops\n                  flightNumber\n                  arrivalAirportCode\n                  departureAirportCode\n                  departureDate\n                  departureTime\n                  arrivalTime\n                  companyName\n                  layovers {\n                    location\n                    duration\n                  }\n                  companyLogoUrl\n                  isOvernightFlight\n                }\n              }\n              ... on RentalCarSummary {\n                isCanceled\n                isExpressDeal\n                type\n                isAirConditioned\n                isAutomatic\n                imageUrl\n                brandImageUrl\n                title\n                dateHeader\n              }\n            }\n          }\n          ... on PRentalCarSummary {\n            type\n            offerNumber\n            checkStatusUrl\n            isCanceled\n            isExpressDeal\n            dateHeader\n            title\n            imageUrl\n            brandImageUrl\n            isAirConditioned\n            isAutomatic\n            crossSellType\n            crossSellUrl\n            offerId\n            travelStartDate\n          }\n        }\n        headline\n        subHeadLine\n        location\n        request_id\n        tripHeadline\n        travelDates\n      }\n      ... on PFlightSummary {\n        type\n        offerNumber\n        checkStatusUrl\n        isCanceled\n        header\n        dateHeader\n        isExpressDeal\n        scheduleInfo {\n          duration\n          numberOfStops\n          flightNumber\n          arrivalAirportCode\n          departureDate\n          departureTime\n          departureAirportCode\n          companyName\n          companyLogoUrl\n          layovers {\n            location\n            duration\n          }\n          isOvernightFlight\n          arrivalTime\n        }\n        isOpenJaw\n        crossSellType\n        crossSellUrl\n        offerId\n        travelStartDate\n        creditAmount\n        creditExpiry\n        isRebookDisabled\n      }\n      ... on PHotelSummary {\n        type\n        checkStatusUrl\n        isCanceled\n        isExpressDeal\n        dateHeader\n        title\n        imageUrl\n        subTitle\n        rating\n        cityName\n        originalUrl\n        crossSellType\n        crossSellUrl\n        offerId\n        travelStartDate\n      }\n      ... on PRentalCarSummary {\n        type\n        checkStatusUrl\n        isCanceled\n        isExpressDeal\n        dateHeader\n        title\n        imageUrl\n        brandImageUrl\n        isAirConditioned\n        isAutomatic\n        crossSellType\n        crossSellUrl\n        offerId\n        travelStartDate\n      }\n      ... on PPackageSummary {\n        type\n        checkStatusUrl\n        isCanceled\n        isExpressDeal\n        travelStartDate\n        offerId\n        bundleComponents {\n          ... on HotelSummary {\n            type\n            isCanceled\n            isExpressDeal\n            imageUrl\n            originalUrl\n            title\n            subTitle\n            rating\n            dateHeader\n            cityName\n            stateName\n            countryName\n          }\n          ... on FlightSummary {\n            type\n            isCanceled\n            isExpressDeal\n            header\n            dateHeader\n            cityName\n            isOpenJaw\n            scheduleInfo {\n              duration\n              numberOfStops\n              flightNumber\n              arrivalAirportCode\n              departureAirportCode\n              departureDate\n              departureTime\n              arrivalTime\n              companyName\n              layovers {\n                location\n                duration\n              }\n              companyLogoUrl\n              isOvernightFlight\n            }\n          }\n          ... on RentalCarSummary {\n            isCanceled\n            isExpressDeal\n            type\n            isAirConditioned\n            isAutomatic\n            imageUrl\n            brandImageUrl\n            title\n            dateHeader\n          }\n        }\n      }\n    }\n  }\n}\n","variables":{"offset":0,"limit":10,"offerStatus":"accepted","offerTime":"future","plfCode":"PCLN","authtoken":"' . $this->authtoken . '","emailAddress":"' . $this->AccountFields['Login'] . '"}}';
        // future
        /*$this->http->PostURL("https://www.priceline.com/pws/v0/pcln-graph/", $query, $headers);
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg('#"blockScript":.+?/captcha/captcha.js\?#')) {*/
        //$response = $this->seleniumItinerary();
        $response = $this->http->JsonLog();

        if (empty($response)) {
            // future
            $this->http->PostURL("https://www.priceline.com/pws/v0/pcln-graph/", $query, $headers);
            $response = $this->http->JsonLog();
        }
//        }

        $headers['apollographql-client-name'] = 'travel-itinerary';
        $headers['x-newrelic-id'] = 'VgIHV1VWDRACUFJbBwMGX1Y=';

        if (!isset($response->data)) {
            return [];
        }

        if ($this->http->FindPreg('/"data":\{"bookings":.+?"bookings":\[\]}/')) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        foreach ($response->data->bookings->bookings as $item) {
            if ($item->type == 'CONNECTED_TRIP') {
                foreach ($item->components as $component) {
                    $this->getItinerary($component, $headers);
                }
            } else {
                $this->getItinerary($item, $headers);
            }
        }

        return [];
    }

    public function parseItineraryFlight($data)
    {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->createFlight();
        $detail = $data->data->itineraryDetails;
        $components = $data->data->itineraryDetails->components;
        $travellers = $tickets = [];

        foreach ($components as $component) {
            foreach ($component->tabs as $tab) {
                if (isset($tab->content->bookings)) {
                    foreach ($tab->content->bookings as $booking) {
                        $f->general()->confirmation($booking->confirmationNumber, 'Confirmation #');
                    }
                } elseif (isset($tab->content->reservationHolders)) {
                    foreach ($tab->content->reservationHolders as $holder) {
                        $travellers[] = $holder->firstName . ' ' . $holder->lastName;

                        foreach ($holder->flights as $flight) {
                            $tickets[] = $flight->ticketNumber;
                        }
                    }
                }
            }
        }
        $f->general()->travellers(array_unique($travellers));
        $f->issued()->tickets(array_unique($tickets), true);

        $f->ota()->confirmation($detail->purchase->confirmationNumber);
        $f->general()->date2($detail->purchase->purchaseDate);
        $f->price()->total(PriceHelper::cost(preg_replace('/[^\d.,\s]/', '', $data->data->itineraryDetails->cost->totalCostPrice)));

        foreach ($components as $component) {
            foreach ($component->summary->flightItineraryDetails->slice as $slice) {
                foreach ($slice->segment as $segment) {
                    $s = $f->addSegment();
                    $s->airline()->number($segment->flightNumber);
                    $s->airline()->name($segment->operatingAirline ?? $segment->marketingAirline);
                    $s->departure()->code($segment->origAirport);
                    $s->arrival()->code($segment->destAirport);
                    $s->departure()->date2($segment->departDateTime);
                    $s->arrival()->date2($segment->arrivalDateTime);
                    $s->extra()->aircraft($segment->equipmentCode);
                    $s->extra()->cabin($segment->cabinName);
                    $s->extra()->bookingCode($segment->bkgClass);
                    $s->extra()->duration(sprintf('%02dh %02dm', $segment->duration / 60, $segment->duration % 60));
                }
            }
        }
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    public function parseItineraryRental($data)
    {
        $this->logger->notice(__METHOD__);
        $detail = $data->data->itineraryDetails;
        $this->logger->info("Parse Rental #{$detail->purchase->confirmationNumber}", ['Header' => 3]);
        $r = $this->itinerariesMaster->createRental();
        $r->general()->noConfirmation();
        $r->ota()->confirmation($detail->purchase->confirmationNumber);
        $r->general()->date2($detail->purchase->purchaseDate);
        $r->general()->traveller("{$detail->tealiumOfferTracking->firstName} {$detail->tealiumOfferTracking->lastName}");
        $r->price()->total($detail->rcUpsellRequestParams->originalTotalPrice ?? null, false, true);

        foreach ($detail->components as $component) {
            if ($component->type != 'DRIVE') {
                continue;
            }
            $r->car()->image($component->summary->imageUrl);
            $r->car()->type($component->summary->title);
            $r->car()->model($component->summary->subTitle);

            $r->extra()->company($component->details->pickUpLocation->supplier);

            $r->pickup()->date2($component->details->pickUpText);
            $r->dropoff()->date2($component->details->dropOffText);
            $r->pickup()->location($component->details->pickUpLocation->address);
            $r->dropoff()->noLocation();

            break;
        }
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    public function parseItineraryStay($data)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->createHotel();
        $detail = $data->data->itineraryDetails;
        $travellers = $tickets = [];

        // data.itineraryDetails.components[0].tabs[0].content.bookings
        foreach ($data->data->itineraryDetails->components as $component) {
            foreach ($component->tabs as $tab) {
                if (isset($tab->content->bookings)) {
                    foreach ($tab->content->bookings as $booking) {
                        if ($booking->confirmationNumber) {
                            $r->general()->confirmation($booking->confirmationNumber, 'Confirmation number');
                            $this->logger->info("Parse Hotel #{$booking->confirmationNumber}", ['Header' => 3]);

                            break 2;
                        }
                    }
                } elseif (isset($tab->content->reservationHolders)) {
                    foreach ($tab->content->reservationHolders as $holder) {
                        $travellers[] = $holder->firstName . ' ' . $holder->lastName;

                        foreach ($holder->flights as $flight) {
                            $tickets[] = $flight->ticketNumber;
                        }
                    }
                }
            }
        }

        $r->ota()->confirmation($detail->purchase->confirmationNumber);
        $r->general()->date2($detail->purchase->purchaseDate);
        $r->general()->traveller("{$detail->tealiumOfferTracking->firstName} {$detail->tealiumOfferTracking->lastName}");
        $r->price()->total($detail->tealiumOfferTracking->rsvTotal ?? null, false, true);
        $r->price()->currency($detail->tealiumOfferTracking->currencyCode ?? null, false, true);

        foreach ($detail->components as $component) {
            $r->hotel()->name($component->summary->title);
            $r->hotel()->address($component->details->location->address);

            $dateIn = $data->data->itineraryDetails->tealiumOfferTracking->travelStartDate;
            $timeIn = $this->http->FindPreg('/after (\d+:\d+)/', false, $component->details->checkInText);
            $r->booked()->checkIn2("$dateIn $timeIn");
            $dateOut = $data->data->itineraryDetails->tealiumOfferTracking->travelEndDate;
            $timeOut = $this->http->FindPreg('/by (\d+:\d+)/', false, $component->details->checkOutText);
            $r->booked()->checkOut2("$dateOut $timeOut");
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    public function parseItineraryLinks()
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $links = $this->http->FindPregAll("/\"checkStatusUrl\":\"([^\"]+)/ims");
        $this->logger->debug(var_export($links, true), ['pre' => true]);

        $response = $this->http->JsonLog(null, 3, false);

        if ($this->http->FindPreg("/\{\"responseCode\":200,\"duration\":\d+,\"version\":\".+?\",\"src\":\".+?\",\"statusCode\":200,\"exceptionCode\":0,\"elapsedTime\":\"\d+ms\",\"requestId\":\".+?\",\"offerListJson\":\[\](?:,\"totalNumberOfOffers\":\d+)?\}/")
            || $this->http->FindPreg("/\{\"responseCode\":200,\"duration\":\d+,\"version\":\".+?\",\"rguid\":\".+?\",\"src\":\"[04]0\d+\"(?:,\"totalNumberOfOffers\":\d+)?\}/")
        ) {
            if (!$this->ParsePastIts) {
                return $this->noItinerariesArr();
            }

            return [];
        }
        $offers = $response->offerListJson ?? [];

        $this->http->GetURL('https://www.priceline.com/profile/#/mytrips');

        foreach ($links as $link) {
            // fix for retrieve itineraries by conf #
            $link = stripslashes($link);
            $tmp = parse_url($link);

            if (isset($tmp['query'])) {
                parse_str($tmp['query'], $tmp);
            }

            if (isset($tmp['zz'])) {
                $this->sendNotification("priceline - 8 dec 17 decided to check if actual this branch tmp[zz] and get above");
                // AirTrip
                if (strstr($link, 'airlines')) {
                    $this->http->GetURL("https://www.priceline.com/pws/v0/air/checkstatus/" . $tmp['zz']);

                    if ($trip = $this->ParseAirTripJson()) {
                        $result[] = $trip;
                    }
                }// if (strstr($link, 'airlines'))
                // Rentals
                if (strstr($link, 'rentalcars')) {
                    $this->http->GetURL('https://www.priceline.com/receipt/?offer-token=' . $tmp['zz'] . '/#/accept/');

                    if ($rentals = $this->ParseRentalsJson()) {
                        $result[] = $rentals;
                    }
                }// if (strstr($link, 'rentalcars'))
                // Hotels
                if (strstr($link, 'hotel')) {
                    $this->http->GetURL('https://www.priceline.com/receipt/?offer-token=' . $tmp['zz'] . '/#/accept/');

                    if ($hotels = $this->ParseHotelJson()) {
                        $result[] = $hotels;
                    } elseif ($hotels = $this->ParseHotelJsonFromList($tmp['zz'], $offers)) {  // refs 9143#note-69
                        $result[] = $hotels;
                    }
                }// if (strstr($link, 'hotel'))
            }// if (isset($tmp['zz']))
            elseif (isset($tmp['offer-token']) && !empty($offers)) {
                $this->logger->notice('[Step offer-token and $offers]');

                if ($rentals = $this->ParseRentalsJsonFromList($tmp['offer-token'], $offers)) {
                    $result[] = $rentals;
                }

                if ($hotels = $this->ParseHotelJsonFromList($tmp['offer-token'], $offers)) {
                    $result[] = $hotels;
                }

                if ($airs = $this->ParseAirTripJsonFromList($tmp['offer-token'], $offers)) {
                    $result[] = $airs;
                }
            }// elseif (isset($tmp['offer-token']))
            elseif (isset($tmp['offer-token'])) {
                $this->logger->notice('[Step offer-token]');
                $this->http->GetURL($link);

                if ($rentals = $this->ParseRentalsJson()) {
                    $result[] = $rentals;
                } elseif ($trip = $this->ParseAirTripJson()) {
                    $this->sendNotification("check airs // ZM");
                    $result[] = $trip;
                } elseif ($hotels = $this->ParseHotelJson()) {
                    $result[] = $hotels;
                }
            } elseif (isset($tmp['offertoken'])) {
                $this->logger->notice('[Step offertoken]');

                foreach ($response->offers as $offer) {
                    if (!isset($offer->offerDetails->checkStatusUrl)) {
                        return $result;
                    }
                    // Example: 2021042
                    if ($offer->offerDetails->checkStatusUrl == $link) {
                        if ($rentals = $this->ParseRentalsJson($offer)) {
                            $result[] = $rentals;
                        }
                        // 5373096
                        if ($trip = $this->ParseAirTripJson($offer)) {
                            $result[] = $trip;
                        }
                        // 5291332
                        if ($hotels = $this->ParseHotelJson($offer)) {
                            $result[] = $hotels;
                        }

                        if (empty($result)) {
                            $this->sendNotification("check others // MI");
                        }

                        continue;
                    }
                }
            }
        }// foreach ($links as $link)

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.priceline.com/profile/#/checkstatus";
    }

    public function confNoNotification($arFields)
    {
        $this->logger->notice(__METHOD__);
        $this->sendNotification("priceline - failed to retrieve itinerary by conf #", 'all', true,
            "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Email: {$arFields['Email']}");

        return null;
    }

    public function ParseConfirmationJson($obj)
    {
        $its = [];

        if (isset($obj->offerListJson) && is_array($obj->offerListJson)) {
            foreach ($obj->offerListJson as $offer) {
                if (isset($offer->trip->primaryOffer->pkgOfferProducts->pkgProducts)) {
                    $products = $offer->trip->primaryOffer->pkgOfferProducts->pkgProducts;
                } elseif (isset($offer->trip->primaryOffer)) {
                    $products = [$offer->trip->primaryOffer];
                } else {
                    if ($rentals = $this->ParseRentalsJsonFromList('', $offer, true)) {
                        $its[] = $rentals;
                    }

                    if ($hotels = $this->ParseHotelJsonFromList('', $offer, true)) {
                        $its[] = $hotels;
                    }

                    if ($airs = $this->ParseAirTripJsonFromList('', $offer, true)) {
                        $its[] = $airs;
                    }

                    return $its;
                }

                foreach ($products as $product) {
                    $new = null;

                    if (isset($product->hotel)) {
                        $its[] = $this->ParseConfirmationJsonHotel($product);
                    } elseif (isset($product->itinerary)) {
                        $its[] = $this->ParseConfirmationJsonFlight($product);
                    } elseif (isset($product->rentalLocation)) {
                        $its[] = $this->ParseConfirmationJsonRental($product);
                    }
                }
            }
        }

        return $its;
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        /*
        Please select a method for receiving the authentication link.
        Note:If your purchase was made through priceline cruises, priceline-europe, or a pricebreaker provider other than priceline.com, please contact us.
        */
        return null;
//        $this->http->SetProxy($this->proxyUK());
        // not works with below
//        $this->http->SetProxy($this->proxyDOP());
//        $this->http->SetProxy($this->proxyReCaptcha());
//        $this->setProxyBrightData();
        $this->http->removeCookies();
        $this->selenium();

        $this->arFields = $arFields;
        $this->http->GetURL('https://www.priceline.com/');

        if ($url = $this->http->FindSingleNode('(//a[contains(@href, "checkstatus") and contains(text(), "Find My Trip")]/@href)[1]')) {
            $this->http->NormalizeURL($url);

            $this->http->GetURL($url);
        }
//        if (!$this->http->ParseForm('checkRequestForm'))
//            return $this->confNoNotification($arFields);
        $data = [
            "emailAddress" => $arFields['Email'],
            "offerNumber"  => str_replace('-', '', $arFields['ConfNo']),
        ];
        $headers = [
            "Content-Type" => "application/json",
            "Accept"       => "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        ];
        $this->http->PostURL('https://www.priceline.com/pws/v0/customer/offer/' . $data['offerNumber'] . '?limit=1', json_encode($data), $headers);
        $obj = $this->http->JsonLog(null, 0);

        if (!empty($obj)) {
            if (isset($obj->offerListJson) && is_array($obj->offerListJson) && empty($obj->offerListJson) && isset($obj->responseCode) && $obj->responseCode == 200) {
                return "Reservation not found";
            }
            $it = $this->ParseConfirmationJson($obj);
        }

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Trip #",
                "Type"     => "string",
                "Size"     => 30,
                "Required" => true,
            ],
            "Email" => [
                "Caption"  => 'Email',
                "Type"     => "string",
                "Size"     => 60,
                "Required" => true,
            ],
        ];
    }

    public function ParseRentalCarsFromEmail($token)
    {
        $this->http = new HttpBrowser("none", new CurlDriver());
        $this->http->GetURL('https://www.priceline.com/receipt/?offer-token=' . $token . '/#/accept/');
        $rentals = $this->ParseRentalsJson();

        return $rentals;
    }

    public function ParseAirFromEmail($token)
    {
        $this->http = new HttpBrowser("none", new CurlDriver());
        $this->http->GetURL("https://www.priceline.com/pws/v0/air/checkstatus/" . $token);
        $airs = $this->ParseAirTripJson();

        return $airs;
    }

    public function ParseHotelFromEmail($token)
    {
        $this->http = new HttpBrowser("none", new CurlDriver());
        $this->http->GetURL('https://www.priceline.com/receipt/?offer-token=' . $token . '/#/accept/');
        $hotels = $this->ParseHotelJson();

        return $hotels;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $xKeys = [];
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            /*
            $selenium->useFirefoxPlaywright();
            $selenium->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            */

            /*
            $this->setConfig();

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

            $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->seleniumRequest->request(
                self::CONFIGS[$this->config]['browser-family'],
                self::CONFIGS[$this->config]['browser-version']
            );

            $selenium->usePacFile(false);
            $selenium->http->saveScreenshots = true;
            */

            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->http->setUserAgent(HttpBrowser::FIREFOX_USER_AGENT);
            $selenium->setKeepProfile(true);

            /*
            $r = 0;

            if ($r === 0) {
                $this->DebugInfo = 'FIREFOX_84';
                $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
                $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->http->setUserAgent(HttpBrowser::FIREFOX_USER_AGENT);
                $selenium->setKeepProfile(true);
            } elseif ($r === 1) {
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->seleniumOptions->userAgent = null;
            }
            */

            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL("https://www.priceline.com/profile/#/login");

            if ($captchaFrame = $selenium->waitForElement(WebDriverBy::xpath('//iframe[@id="px-captcha-modal" or @title="Human verification challenge" and not(@style="display: none;")]'), 10, false)) {
                $this->clickPressAndHoldByMouse($selenium);

                /*
                $selenium->driver->switchTo()->frame($captchaFrame);
                $this->savePageToLogs($selenium);

                if (!$pressAndHold = $selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Press")]'), 0, false)) {
                    return false;
                }
                $mover = new MouseMover($selenium->driver);
//                $mover->logger = $this->logger;
                $mover->enableCursor();

                // throws exception on chrome_94, seems that mouse cannot be moved on element with visibility == false (even if element is actually visible)
//                $mover->moveToElement($pressAndHold);
                $mouse = $selenium->driver->getMouse()->mouseDown($pressAndHold->getCoordinates());

                $this->savePageToLogs($selenium);
                $success = $this->waitFor(function () use ($selenium) {
                    return is_null($selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Press")]'), 0, false));
                }, 20);
                $mouse->mouseUp();
                $selenium->driver->switchTo()->defaultContent();
                $this->savePageToLogs($selenium);

                if (!$success) {
                    return false;
                }
                */
                $this->savePageToLogs($selenium);
            } else {
                // wait for loading
                $selenium->waitFor(function () use ($selenium) {
                    return is_null($selenium->waitForElement(WebDriverBy::xpath('//div[@data-testid="spinner"]'), 0));
                }, 60);
            }

            $this->savePageToLogs($selenium);

            if ($acceptCookiesBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "onetrust-accept-btn-handler"]'), 0, false)) {
                $acceptCookiesBtn->click();
                sleep(1);
            }

            $this->savePageToLogs($selenium);

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "signin-first-name" or @data-testid = "signin-first-name" or @id = "username"] | //iframe[@id="px-captcha-modal" and not(@style="display: none;")]'), 30);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password" or @data-testid = "password"]'), 0);
            $button =
                $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "button-sign-in"] | //input[@data-testid="button-sign-in"]'), 0, false)
                ?? $selenium->waitForElement(WebDriverBy::xpath('//button[@data-testid="submit-form" or @type="submit"]'), 0)
            ;
            // save page to logs
            $this->savePageToLogs($selenium);

            if ($captchaFrame = $selenium->waitForElement(WebDriverBy::xpath('//iframe[@id="px-captcha-modal" or @title="Human verification challenge" and not(@style="display: none;")]'), 0, false)) {
                $this->clickPressAndHoldByMouse($selenium);
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "signin-first-name" or @data-testid = "signin-first-name" or @id = "username"] | //iframe[@id="px-captcha-modal" and not(@style="display: none;")]'), 30);
                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password" or @data-testid = "password"]'), 0);
                $button =
                    $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "button-sign-in"] | //input[@data-testid="button-sign-in"]'), 0, false)
                    ?? $selenium->waitForElement(WebDriverBy::xpath('//button[@data-testid="submit-form" or @type="submit"]'), 0)
                ;
                // save page to logs
                $this->savePageToLogs($selenium);
            }

            if (!$loginInput || !$button) {
                if ($this->http->FindSingleNode('//div[@id = "pcln-okta-widget-target" and contains(., "re sorry, there was a problem loading our sign-in form.")] | //h1[contains(text(), "Looks like this browser is outdated.")] | //div[@data-testid="spinner"]')) {
                    $retry = true;
                }

                return $this->checkErrors();
            }

            if ($this->attempt > $this->seleniumAuthAfterAttempt) {
                $this->logger->debug("executeScript");
                $selenium->driver->executeScript('
                    let oldXHROpen = window.XMLHttpRequest.prototype.open;
                    window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                        this.addEventListener(\'load\', function() {
                            if (/(?:customer|exception)/g.exec( this.responseText )) {
                                localStorage.setItem(\'responseData\', this.responseText);
                            }
                        });

                        return oldXHROpen.apply(this, arguments);
                    };
                ');

                $this->logger->debug("set login");
                $loginInput->sendKeys($this->AccountFields['Login']);

                if ($captchaFrame = $selenium->waitForElement(WebDriverBy::xpath('//iframe[@id="px-captcha-modal" or @title="Human verification challenge" and not(@style="display: none;")]'), 10, false)) {
                    $this->clickPressAndHoldByMouse($selenium);
                    /*
                    $selenium->driver->switchTo()->frame($captchaFrame);
                    $this->savePageToLogs($selenium);

                    if (!$pressAndHold = $selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Press")]'), 0, false)) {
                        return false;
                    }
                    $mover = new MouseMover($selenium->driver);
                    $mover->logger = $this->logger;
                    $mover->enableCursor();

                    // throws exception on chrome_94, seems that mouse cannot be moved on element with visibility == false (even if element is actually visible)
                    $mover->moveToElement($pressAndHold);
                    $mouse = $selenium->driver->getMouse()->mouseDown($pressAndHold->getCoordinates());

                    $this->savePageToLogs($selenium);
                    $success = $this->waitFor(function () use ($selenium) {
                        return is_null($selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Press")]'), 0, false));
                    }, 20);
                    $mouse->mouseUp();
                    $selenium->driver->switchTo()->defaultContent();
                    $this->savePageToLogs($selenium);

                    if (!$success) {
                        return false;
                    }
                    */
                    $this->savePageToLogs($selenium);
                }

                if (!$passwordInput) {
                    $selenium->driver->executeScript('try { document.querySelector(\'button#button-sign-in, input[data-testid = "button-sign-in"], button[data-testid="submit-form"], button[type="submit"]\').click(); } catch (e) {}');
                    sleep(2);
                    $this->savePageToLogs($selenium);
                    $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password" or @data-testid = "password"]'), 5);

                    if (
                        !$passwordInput
                        && ($authWithPassword = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(., "Sign In with Password")]'), 0))
                    ) {
                        $this->savePageToLogs($selenium);
                        $authWithPassword->click();
                        $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password" or @data-testid = "password"]'), 5);
                    }

                    $button =
                        $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "button-sign-in"] | //input[@data-testid="button-sign-in"]'), 0, false)
                        ?? $selenium->waitForElement(WebDriverBy::xpath('//button[@data-testid="submit-form" or @type="submit"]'), 0)
                    ;

                    if (!$passwordInput || !$button) {
                        $this->savePageToLogs($selenium);

                        return false;
                    }
                }

                $this->logger->debug("set pass");
                $passwordInput->sendKeys($this->AccountFields['Pass']);

//                $button->click();
                $selenium->driver->executeScript('try { document.querySelector(\'button#button-sign-in, input[data-testid = "button-sign-in"], button[data-testid="submit-form"], button[type="submit"]\').click(); } catch (e) {}');

                $selenium->waitForElement(WebDriverBy::xpath('
                    //*[@id = "sign-in-module__user-name-signedIn"]
                    | //div[contains(text(), "Invalid email address")]
                    | //div[contains(text(), "Email and password do not match.")]
                    | //h2[contains(text(), "Unlock Account")]
                    | //div[contains(@class, "infobox-error")]//p
                    | //p[contains(text(), "Please confirm that you are a real Priceline user.")]
                '), 15);
            }

            $this->savePageToLogs($selenium);
            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);

            if (
                !empty($responseData)
                && !$this->http->FindSingleNode('//div[contains(@class, "infobox-error")]//p')
                && !$this->http->FindSingleNode('//div[contains(text(), "Invalid email address") or contains(text(), "Email and password do not match.")]')
            ) {
                $this->http->SetBody($responseData);
            } elseif ($selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Signing In...")]'), 0)) {
                $retry = true;
            } else {
                $this->savePageToLogs($selenium);
            }

            $this->authtoken =
                $this->http->getCookieByName("dmc", ".priceline.com", "/")
                ?? $selenium->driver->executeScript("return sessionStorage.getItem('authToken');")
                ?? $this->http->JsonLog($selenium->driver->executeScript("return localStorage.getItem('okta-token-storage');"))->accessToken->claims->{'com.priceline.token.dmc.value'}
                ?? null
            ;
            $this->logger->info("[authtoken]: " . $this->authtoken);

            if (!empty($this->authtoken)) {
                $this->markProxySuccessful();

                $selenium->http->GetURL("https://www.priceline.com/next-profile/trips?tab=past");
                $upcomingTrips = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(),'Upcoming Trips')]"), 5);

                if ($upcomingTrips) {
                    $upcomingTrips->click();
                }

                $selenium->waitForElement(WebDriverBy::xpath("//input[@id='UPCOMING'] | //div[contains(text(), 'Upcoming Trips')]"), 10);
                $this->savePageToLogs($selenium);
//            $this->captchaPressHold($selenium);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        catch (NoSuchDriverException | \Facebook\WebDriver\Exception\NoSuchDriverException
            | WebDriverCurlException | \Facebook\WebDriver\Exception\Internal\WebDriverCurlException $e
        ) {
            $this->logger->error("exception: " . $e->getMessage());
            $retry = true;
        } catch (UnknownServerException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->markProxyAsInvalid();
            $retry = true;
        } finally {
            if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                // close Selenium browser
                $selenium->http->cleanup();
            }

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return $xKeys;
    }

    protected function getScriptJson()
    {
        $this->logger->notice(__METHOD__);
        $script = $this->http->FindSingleNode('//script[contains(., "var requestHandlerData = ")]', null, true, '/var requestHandlerData = (\{.+\}\});/');

        if (!$script) {
            $script = $this->http->FindSingleNode('//script[contains(., "PCLN_BOOTSTRAP_DATA.offerDetails = ")]', null, true, '/offerDetails = (\{.+?\});\s*window./');
        }

        if (!$script) {
            $this->logger->error('script json not found');

            return null;
        }// if (!$script)

        $script = preg_replace('/&quot;/', '\\"', $script);
        $script = Html::cleanXMLValue($script);
        $script = preg_replace('/\(\d+:\d+\s+\d+\s+\w+\s+\d{4}\)/', '', $script);

        return $script;
    }

    protected function ParseHotelJson($data = null)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($data->offerDetails->primaryOffer->hotel->hotelName)) {
            if (isset($data->offerDetails->primaryOffer->bundleComponents)) {
                foreach ($data->offerDetails->primaryOffer->bundleComponents as $bundleComponents) {
                    if ($bundleComponents->componentType == 'STAY') {
                        $offer = (object) ['primaryOffer' => null];
                        $offer->primaryOffer = $bundleComponents->item;
                    }
                }
            } else {
                $script = $this->getScriptJson();
                $this->logger->info(var_export($script, true), ['pre' => true]);
                $data = $this->http->JsonLog($this->jsonFixError($script), 2);

                if (isset($data->offerDetails)) {
                    $offer = $data->offerDetails;
                }
            }
        } elseif (isset($data->offerDetails)) {
            $offer = $data->offerDetails;
        }

        if (empty($offer->primaryOffer)) {
            return null;
        }
        $this->logger->info('Hotel Parse Itinerary #' . $data->offerDetails->offerNum, ['Header' => 3]);

        $hotel = new HotelReservation($this->logger);

        // TripNumber
        $hotel->setTripNumber($data->offerDetails->offerNum);
        // ConfirmationNumber
        $hotel->setConfirmationNumber($data->offerDetails->offerNum);
        // Rooms
        $hotel->setRoomsCount($offer->primaryOffer->numResRooms);
        // CheckInDate
        if (isset($offer->primaryOffer->hotel->checkInTime)) {
            // 14:00 - 00:00
            $checkInTime = $this->http->FindPreg('/^(\d+:\d+)/', false, $offer->primaryOffer->hotel->checkInTime);
            $hotel->setCheckInDate(strtotime($checkInTime, strtotime($offer->primaryOffer->travelStartDateTime)));
        } else {
            $hotel->setCheckInDate(strtotime($offer->primaryOffer->travelStartDateTime));
        }
        // CheckOutDate
        if (isset($offer->primaryOffer->hotel->checkOutTime)) {
            // 1:00 - 12:00
            $checkOutTime = $this->http->FindPreg('/^(\d+:\d+)/', false, $offer->primaryOffer->hotel->checkOutTime);
            $hotel->setCheckOutDate(strtotime($checkOutTime, strtotime($offer->primaryOffer->travelEndDateTime)));
        } else {
            $hotel->setCheckOutDate(strtotime($offer->primaryOffer->travelEndDateTime));
        }
        // Total
        if (isset($offer->primaryOffer->hotelSummaryOfCharges->requested->totalAmountChargedByPriceline)) {
            $hotel->setTotal($offer->primaryOffer->hotelSummaryOfCharges->requested->totalAmountChargedByPriceline);
        }
        // Currency
        if (isset($offer->primaryOffer->hotelSummaryOfCharges->requested->currencyCode)) {
            $hotel->setCurrencyCode($offer->primaryOffer->hotelSummaryOfCharges->requested->currencyCode);
        }
        // Taxes
        if (isset($offer->primaryOffer->hotelSummaryOfCharges->requested->totalTaxesAndFees)) {
            $hotel->setTax($offer->primaryOffer->hotelSummaryOfCharges->requested->totalTaxesAndFees);
        }
        // Cost
        if (isset($offer->primaryOffer->hotelSummaryOfCharges->requested->subTotal)) {
            $hotel->setCost($offer->primaryOffer->hotelSummaryOfCharges->requested->subTotal);
        } elseif (isset($offer->primaryOffer->summaryOfCharges->subTotal->amount)) {
            $hotel->setCost($offer->primaryOffer->summaryOfCharges->subTotal->amount);
        }

        // ReservationDate
        if (isset($offer->customer->creationDateTime)) {
            $hotel->setReservationDate(strtotime(date("Y-m-d H:i", substr($offer->customer->creationDateTime, 0, 10))));
        }

        $confNo = [];
        $guests = [];
        $guestsCnt = [];
        $kidsCnt = [];

        if (isset($offer->primaryOffer->rooms)) {
            foreach ($offer->primaryOffer->rooms as $room) {
                // ConfirmationNumber
                $confNo[] = $room->confirmationNum;
                $guests[] = beautifulName(trim($room->firstName . ' ' . $room->lastName));
                // Guests
                $guestsCnt[] = $room->numOfAdults;
                $kidsCnt[] = $room->numOfChildren;
                // RoomTypeDescription
                if (isset($room->roomTypeDesc)) {
                    $hotel->setRoomTypeDescription($room->roomTypeDesc);
                }

                if (isset($room->cancelPolicyText)) {
                    $hotel->setCancellationPolicy($room->cancelPolicyText);
                }
            }
        }
        $confNo = array_unique(array_map('trim', $confNo));
        $hotel->setConfirmationNumbers($confNo);
        1 >= count($guestsCnt) ? $hotel->setGuestCount(implode('',
            $guestsCnt)) : $hotel->setGuestCount(array_sum($guestsCnt));
        1 >= count($kidsCnt) ? $hotel->setKidsCount(implode('', $kidsCnt)) : $hotel->setKidsCount(array_sum($kidsCnt));

        // GuestNames
        $hotel->setGuests($guests);
        // HotelName
        $hotel->setHotelName($offer->primaryOffer->hotel->hotelName);
        // Phone
        if (isset($offer->primaryOffer->hotel->phone)) {
            is_array($offer->primaryOffer->hotel->phone) ? $hotel->setPhone($offer->primaryOffer->hotel->phone[0]) : $hotel->setPhone($offer->primaryOffer->hotel->phone);
        }
        // Fax
        if (isset($offer->primaryOffer->hotel->fax)) {
            $hotel->setFax($offer->primaryOffer->hotel->fax);
        }
        // Address
        $addr = [];
        !isset($offer->primaryOffer->hotel->address->addressLine1) ?: $addr[] = $offer->primaryOffer->hotel->address->addressLine1;
        !isset($offer->primaryOffer->hotel->address->stateName) ?: $addr[] = $offer->primaryOffer->hotel->address->stateName;
        !isset($offer->primaryOffer->hotel->address->stateCode) ?: $addr[] = $offer->primaryOffer->hotel->address->stateCode;
        !isset($offer->primaryOffer->hotel->address->postalCode) ?: $addr[] = $offer->primaryOffer->hotel->address->postalCode;
        !isset($offer->primaryOffer->hotel->address->cityName) ?: $addr[] = $offer->primaryOffer->hotel->address->cityName;
        !isset($offer->primaryOffer->hotel->address->countryName) ?: $addr[] = $offer->primaryOffer->hotel->address->countryName;
        !isset($offer->primaryOffer->hotel->address->zip) ?: $addr[] = 'ZIP: ' . $offer->primaryOffer->hotel->address->zip;
        $hotel->setAddressText(implode(', ', $addr));

        if (isset($offer->primaryOffer->importantInformation->cancelPolicy->{'Cancellation policy'})) {
            $hotel->setCancellationPolicy($offer->primaryOffer->importantInformation->cancelPolicy->{'Cancellation policy'});
        }

        return $hotel->convertToOldArrayFormat();
    }

    // if syntax error in full json
    protected function ParseHotelJsonFromList($key, $offers, $retrieve = false)
    {
        $this->logger->notice(__METHOD__);

        if ($retrieve) {
            if (isset($offers[0]) && isset($offers[0]->offerToken)) {
                $key = $offers[0]->offerToken;
            } else {
                if (!empty($this->arFields)) {
                    $this->confNoNotification($this->arFields);
                }

                return [];
            }
        }

        for ($i = -1, $iCount = count($offers); ++$i < $iCount;) {
            if ($key == $offers[$i]->offerToken && isset($offers[$i]->trip) && isset($offers[$i]->trip->primaryOffer)
                && isset($offers[$i]->trip->primaryOffer->hotelId) && $offers[$i]->trip->primaryOffer->hotelId > 0
            ) {
                $hotel = new HotelReservation($this->logger);
                $trip = $offers[$i]->trip;

                // TripNumber
                $hotel->setTripNumber($trip->primaryOfferNum);
                // ConfirmationNumber
                if (isset($offers[$i]->hotelConfNumber) && !empty($offers[$i]->hotelConfNumber)) {
                    $hotel->setConfirmationNumber($offers[$i]->hotelConfNumber);
                } else {
                    $hotel->setConfirmationNumber($trip->primaryOfferNum);
                }
                $this->logger->info('Hotel Parse Itinerary #' . $hotel->getConfirmationNumber(), ['Header' => 3]);
                // Rooms
                $hotel->setRoomsCount($trip->primaryOffer->numResRooms);
                // CheckInDate
                $hotel->setCheckInDate(strtotime($trip->primaryOffer->utcTravelStartDate->srcDateTime));
                // CheckOutDate
                $hotel->setCheckOutDate(strtotime($trip->primaryOffer->utcTravelEndDate->srcDateTime));
                // Total
                if (isset($trip->primaryOffer->summaryOfCharges->totalChargedByPriceline->amount)) {
                    $hotel->setTotal($trip->primaryOffer->summaryOfCharges->totalChargedByPriceline->amount);
                } elseif (isset($trip->primaryOffer->hotelSummaryOfCharges->totalAmountChargedByPriceline->amount)) {
                    $hotel->setTotal($trip->primaryOffer->hotelSummaryOfCharges->totalAmountChargedByPriceline->amount);
                }

                // Currency
                if (isset($trip->primaryOffer->summaryOfCharges->totalChargedByPriceline->currency)) {
                    $hotel->setCurrencyCode($trip->primaryOffer->summaryOfCharges->totalChargedByPriceline->currency);
                } elseif (isset($trip->primaryOffer->hotelSummaryOfCharges->totalAmountChargedByPriceline->currency)) {
                    $hotel->setCurrencyCode($trip->primaryOffer->hotelSummaryOfCharges->totalAmountChargedByPriceline->currency);
                }
                // Taxes
                if (isset($trip->primaryOffer->summaryOfCharges->totalTaxesAndFees)) {
                    $hotel->setTax($trip->primaryOffer->summaryOfCharges->totalTaxesAndFees->amount);
                }
                // Cost
                $hotel->setCost($trip->primaryOffer->summaryOfCharges->subTotal->amount);
                // ReservationDate
                if (isset($trip->primaryOffer->createdDate)) {
                    $created = strtotime($trip->primaryOffer->createdDate);
                    $hotel->setReservationDate($created - $created % 60);
                }
                $confNo = [];
                $guests = [];

                foreach ($trip->primaryOffer->rooms as $room) {
                    // ConfirmationNumber
                    if (isset($room->confirmationNum)) {
                        $confNo[] = $room->confirmationNum;
                    } elseif (isset($room->reservationId)) {
                        $confNo[] = $room->reservationId;
                    }
                    $guests[] = beautifulName($room->resFirstName . " " . $room->resLastName);
                    // RoomTypeDescription
                    $hotel->setRoomTypeDescription($room->roomTypeDesc);
                }
                $hotel->setConfirmationNumbers(implode(', ', $confNo));
                // GuestNames
                $hotel->setGuests($guests);
                // HotelName
                $hotel->setHotelName($trip->primaryOffer->hotel->hotelName);
                // Phone
                if (isset($trip->primaryOffer->hotel->hotelPhone[0])) {
                    $hotel->setPhone($trip->primaryOffer->hotel->hotelPhone[0]);
                }
                // Fax
                if (isset($trip->primaryOffer->hotel->hotelFax[0])) {
                    $hotel->setFax($trip->primaryOffer->hotel->hotelFax[0]);
                }
                // Address
                $hotel->setAddressText(''
                    . (isset($trip->primaryOffer->hotel->hotelAddr->line1) ? $trip->primaryOffer->hotel->hotelAddr->line1 . ", " : '')
                    . (isset($trip->primaryOffer->hotel->hotelAddr->stateName) ? $trip->primaryOffer->hotel->hotelAddr->stateName . ", " : '')
                    . (isset($trip->primaryOffer->hotel->hotelAddr->stateCode) ? $trip->primaryOffer->hotel->hotelAddr->stateCode . " " : '')
                    . (isset($trip->primaryOffer->hotel->hotelAddr->postalCode) ? $trip->primaryOffer->hotel->hotelAddr->postalCode . ", " : '')
                    . (isset($trip->primaryOffer->hotel->hotelAddr->city) ? $trip->primaryOffer->hotel->hotelAddr->city . ", " : '')
                    . ($trip->primaryOffer->hotel->hotelAddr->countryName ?? "")
                );

                return $hotel->convertToOldArrayFormat();
            }// if ($key == $offers[$i]->offerToken && $offers[$i]->trip->primaryOffer->hotelId > 0)
        }// for ($i = -1, $iCount = count($offers); ++$i < $iCount;)

        return [];
    }

    protected function jsonFixError($json)
    {
        $json = str_replace(["\r", "\n", '\"'], '', $json);
        $json = trim($json);

        for ($i = -1; ++$i <= 31; ++$i) {
            $json = str_replace(chr($i), '', $json);
        }
        $json = str_replace(chr(127), '', $json);

        if (0 === strpos(bin2hex($json), 'efbbbf')) {
            $json = substr($json, 3);
        }

        return $json;
    }

    protected function ParseRentalsJson($data = null)
    {
        $this->logger->notice(__METHOD__);
        $rental = null;
        $ports = [];

        if (empty($data)) {
            $script = $this->getScriptJson();
            $data = $this->http->JsonLog($this->jsonFixError($script), 2);
        }

        if (!empty($data) && isset($data->offerDetails) && isset($data->offerDetails->primaryOffer->rentalData)
            && 'O_REJECTED' !== $data->offerDetails->primaryOffer->statusCode) {
            $offer = $data->offerDetails->primaryOffer->rentalData;

            // Locations
            if (isset($offer->airports)) {
                foreach ($offer->airports as $key => $port) {
                    if (isset($port->fullDisplayName)) {
                        $ports[$key] = $port->fullDisplayName;
                    }
                }
            }
            $this->logger->debug("Ports: <pre>" . var_export($ports, true) . "</pre>");

            $this->logger->info('Rental Parse Itinerary #' . $offer->confirmationId, ['Header' => 3]);

            $rental = new AwardWallet\MainBundle\Service\Itinerary\CarRental($this->logger);
            // Number
            $rental->setConfirmationNumber($offer->confirmationId);
            // TripNumber
            $rental->setTripNumber($data->offerDetails->primaryOffer->offerNumber);
            // ReservationDate
            if (isset($data->offerDetails->offerDateTimeUTC)) {
                $rental->setReservationDate(strtotime(preg_replace("#(\d{2}:\d{2}):\d{2}#", "$1:00", $data->offerDetails->offerDateTimeUTC)));
            }
            // RenterName
            if (isset($offer->driver->firstName, $offer->driver->lastName)) {
                $rental->setDriverName(beautifulName($offer->driver->firstName . ' ' . $offer->driver->lastName));
            }

            if (isset($offer->vehicleRate)) {
                $vehicleRate = $offer->vehicleRate;
                // Pickup
                if (isset($vehicleRate->pickupDateTime, $vehicleRate->partnerInfo->returnLocationId)) {
                    $rental->getPickup()
                        // PickupDatetime
                        ->setLocalDateTime(strtotime($vehicleRate->pickupDateTime))
                        // PickupPhone
                        ->setPhone($offer->partner->phoneNumber);
                    // PickupLocation
                    if (isset($offer->partnerLocations)) {
                        foreach ($offer->partnerLocations as $k => $v) {
                            if ($v->id == $vehicleRate->partnerInfo->pickupLocationId) {
                                $provinceCode = isset($v->address->provinceCode) ? " " . $v->address->provinceCode : '';
                                $postalCode = isset($v->address->postalCode) ? " " . $v->address->postalCode : '';
                                $address = $v->address->addressLine1 . ", " . $v->address->cityName . ", " . $provinceCode . $postalCode;
                                !isset($v->address->countryName) ?: $address .= ', ' . $v->address->countryName;

                                if (isset($v->airportCode, $ports[$v->airportCode])) {
                                    $address = $ports[$v->airportCode] . " - " . $address;
                                }
                                $rental->getPickup()->setAddress($address);
                            }
                        }
                    }
                }
                // Dropoff
                if (isset($vehicleRate->returnDateTime, $vehicleRate->partnerInfo->returnLocationId)) {
                    $rental->getDropoff()
                        // DropoffDatetime
                        ->setLocalDateTime(strtotime($vehicleRate->returnDateTime))
                        // DropoffPhone
                        ->setPhone($offer->partner->phoneNumber);
                    // DropoffLocation
                    if (isset($offer->partnerLocations)) {
                        foreach ($offer->partnerLocations as $k => $v) {
                            if ($v->id == $vehicleRate->partnerInfo->returnLocationId) {
                                $provinceCode = isset($v->address->provinceCode) ? " " . $v->address->provinceCode : '';
                                $postalCode = isset($v->address->postalCode) ? " " . $v->address->postalCode : '';
                                $address = $v->address->addressLine1 . ", " . $v->address->cityName . ", " . $provinceCode . $postalCode;
                                !isset($v->address->countryName) ?: $address .= ', ' . $v->address->countryName;

                                if (isset($v->airportCode, $ports[$v->airportCode])) {
                                    $address = $ports[$v->airportCode] . " - " . $address;
                                }
                                $rental->getDropoff()->setAddress($address);
                            }
                        }
                    }
                }
                // CarModel
                if (isset($vehicleRate->partnerInfo->vehicleExample)) {
                    $rental->setCarModel($vehicleRate->partnerInfo->vehicleExample);
                }
                // CarImageUrl
                if (isset($vehicleRate->partnerInfo->images->SIZE134X72)) {
                    $img = $vehicleRate->partnerInfo->images->SIZE134X72;

                    if (!strstr($img, "https:")) {
                        $img = "https:" . $img;
                    }
                    $rental->setCarImageUrl($img);
                }

                if (isset($vehicleRate->rates)) {
                    foreach ($vehicleRate->rates as $key => $summary) {
                        $rental->getTotalPrice()
                            // Currency
                            ->setCurrencyCode($key)
                            // TotalCharge
                            ->setTotal($summary->summary->totalCharges ?? null)
                            // TotalTaxAmount
                            ->setTax($summary->summary->totalTaxesAndFees ?? null);
                    }
                }
                $rental->setProviderName($offer->partner->partnerName);
            }

            return $rental->convertToOldArrayFormat();
        }

        return [];
    }

    protected function ParseRentalsJsonFromList($key, $offers, $retrieve = false)
    {
        $this->logger->notice(__METHOD__);

        if ($retrieve) {
            if (isset($offers[0]) && isset($offers[0]->offerToken)) {
                $key = $offers[0]->offerToken;
            } else {
                if (!empty($this->arFields)) {
                    $this->confNoNotification($this->arFields);
                }

                return [];
            }
        }

        for ($i = -1, $iCount = count($offers); ++$i < $iCount;) {
            if ($key == $offers[$i]->offerToken && isset($offers[$i]->trip) && isset($offers[$i]->trip->primaryOffer)
                && isset($offers[$i]->trip->primaryOffer->offerType) && $offers[$i]->trip->primaryOffer->offerType == 'RENTAL_CAR'
            ) {
                $rental = new CarRental($this->logger);
                $trip = $offers[$i]->trip;

                if (isset($offers[$i]->accepted)) {
                    if ($offers[$i]->accepted == false) {
                        return [];
                    }
                } else {
                    $this->sendNotification("priceline - structure of json is changed");

                    return [];
                }

                // RenterName
                if (isset($trip->customer) && isset($trip->customer->firstName, $trip->customer->lastName)) {
                    if (isset($trip->customer->middleName)) {
                        $rental->setDriverName(beautifulName($trip->customer->firstName . ' ' . $trip->customer->middleName . ' ' . $trip->customer->lastName));
                    } else {
                        $rental->setDriverName(beautifulName($trip->customer->firstName . ' ' . $trip->customer->lastName));
                    }
                }

                $offer = $trip->primaryOffer;

                // Number
                if (isset($offer->confirmationNumber)) {
                    $rental->setConfirmationNumber($offer->confirmationNumber);
                }
                $this->logger->info('Rental Parse Itinerary #' . $rental->getConfirmationNumber(), ['Header' => 3]);
                // TripNumber
                $rental->setTripNumber($offer->offerNum);
                // ReservationDate
                if (isset($offer->offerDateTime)) {
                    $rental->setReservationDate(strtotime($offer->offerDateTime));
                }
                // Pickup
                if (isset($offer->rentalLocation)) {
                    // PickupDateTime
                    if (isset($offer->travelStartDate)) {
                        $rental->getPickup()->setLocalDateTime(strtotime($offer->travelStartDate));
                    }
                    // PickupPhone
                    if (isset($offer->rentalLocation->rentalPartner->partnerPhone)) {
                        $rental->getPickup()->setPhone($offer->rentalLocation->rentalPartner->partnerPhone);
                    }
                    // PickupLocation
                    if (isset($offer->rentalLocation->addressLine1, $offer->rentalLocation->city, $offer->rentalLocation->countryName)) {
                        $address = implode(", ", [$offer->rentalLocation->addressLine1, $offer->rentalLocation->city, $offer->rentalLocation->countryName]);

                        if (isset($offer->rentalLocation->airportCode, $offer->rentalLocation->airportName)) {
                            $address = '(' . $offer->rentalLocation->airportCode . ") " . $offer->rentalLocation->airportName . " - " . $address;
                        }
                        $rental->getPickup()->setAddress($address);
                    }

                    if (!$rental->getPickup()->getAddress()) {
                        if (isset($offer->rentalLocation->name)) {
                            $rental->getPickup()->setAddress($offer->rentalLocation->name);
                        }
                    }
                    // RentalCompany
                    if (isset($offer->rentalLocation->rentalPartner->partnerName)) {
                        $rental->setProviderName($offer->rentalLocation->rentalPartner->partnerName);
                    }
                }
                // Dropoff
                if (isset($offer->dropoffRentalLocation)) {
                    // DropoffDatetime
                    if (isset($offer->travelEndDate)) {
                        $rental->getDropoff()->setLocalDateTime(strtotime($offer->travelEndDate));
                    }
                    // DropoffPhone
                    if (isset($offer->dropoffRentalLocation->rentalPartner->partnerPhone)) {
                        $rental->getDropoff()->setPhone($offer->dropoffRentalLocation->rentalPartner->partnerPhone);
                    }
                    // DropoffLocation
                    if (isset($offer->dropoffRentalLocation->addressLine1, $offer->dropoffRentalLocation->city, $offer->dropoffRentalLocation->countryName)) {
                        $address = implode(", ", [$offer->dropoffRentalLocation->addressLine1, $offer->dropoffRentalLocation->city, $offer->dropoffRentalLocation->countryName]);

                        if (isset($offer->dropoffRentalLocation->airportCode, $offer->dropoffRentalLocation->airportName)) {
                            $address = '(' . $offer->dropoffRentalLocation->airportCode . ") " . $offer->dropoffRentalLocation->airportName . " - " . $address;
                        }
                        $rental->getDropoff()->setAddress($address);
                    }

                    if (!$rental->getDropoff()->getAddress()) {
                        if (isset($offer->dropoffRentalLocation->name)) {
                            $rental->getDropoff()->setAddress($offer->dropoffRentalLocation->name);
                        }
                    }
                }

                // CarType
                if (isset($offer->vehicle)) {
                    $carType = [];

                    if (isset($offer->vehicle->desc)) {
                        $carType[] = $offer->vehicle->desc;
                    }

                    if (isset($offer->vehicle->driveDesc)) {
                        $carType[] = $offer->vehicle->driveDesc;
                    }

                    if (isset($offer->vehicle->typeDesc)) {
                        $carType[] = $offer->vehicle->typeDesc;
                    }
                    $rental->setCarType(implode(", ", $carType));
                }
                // CarModel
                if (isset($offer->vehicleExample) && isset($offer->vehicleExample->makeAndModel)) {
                    $rental->setCarModel($offer->vehicleExample->makeAndModel);
                }
                // TotalCharge
                if (isset($offer->summaryOfChares->grandTotal->amount)) {
                    $rental->getTotalPrice()->setTotal($offer->summaryOfChares->grandTotal->amount);
                }

                if (!$rental->getTotalPrice()->getTotal()) {
                    if (isset($offer->offerPrice->amount)) {
                        $rental->getTotalPrice()->setTotal($offer->offerPrice->amount);
                    }
                }
                // Currency
                if (isset($offer->summaryOfChares->grandTotal->currency)) {
                    $rental->getTotalPrice()->setCurrencyCode($offer->summaryOfChares->grandTotal->currency);
                }

                if (!$rental->getTotalPrice()->getCurrencyCode()) {
                    if (isset($offer->offerPrice->currency)) {
                        $rental->getTotalPrice()->setCurrencyCode($offer->offerPrice->currency);
                    }
                }

                $res = $rental->convertToOldArrayFormat();
                $this->logger->debug('Parsed Car:');
                $this->logger->debug(var_export($res, true), ['pre' => true]);

                return $res;
            }// if ($key == $offers[$i]->offerToken && $offers[$i]->trip->primaryOffer->offerType == 'RENTAL_CAR')
        }// for ($i = -1, $iCount = count($offers); ++$i < $iCount;)

        return [];
    }

    protected function ParseAirTripJson($data = null)
    {
        $this->logger->notice(__METHOD__);
        $trip = null;

        if (isset($data->offerDetails, $data->offerDetails->primaryOffer)) {
            if (!isset($data->offerDetails->primaryOffer->airport) && isset($data->offerDetails->primaryOffer->bundleComponents)) {
                foreach ($data->offerDetails->primaryOffer->bundleComponents as $bundleComponents) {
                    if ($bundleComponents->componentType == 'FLY') {
                        $data->airCheckStatusRsp = $bundleComponents->item;
                    }
                }
            } else {
                $data->airCheckStatusRsp = $data->offerDetails->primaryOffer;
            }
        } else {
            $data = $this->http->JsonLog(null, 2);
        }

        if (isset($data->airCheckStatusRsp->airport)) {
            $trip = ['Kind' => 'T', 'Passengers' => [], 'TripSegments' => []];
            $ports = [];
            // Airport codes
            if (isset($data->airCheckStatusRsp->airport) && is_array($data->airCheckStatusRsp->airport)) {
                foreach ($data->airCheckStatusRsp->airport as $port) {
                    if (isset($port->city) && isset($port->code) && isset($port->name)) {
                        $ports[$port->code] = $port->name . ', ' . $port->city;
                    }
                }
            }
            // RecordLocator, TripNumber
            if (isset($data->airCheckStatusRsp->bookingReferenceId)) {
                $trip['RecordLocator'] = $data->airCheckStatusRsp->bookingReferenceId;
                $trip['TripNumber'] = $data->airCheckStatusRsp->bookingReferenceId;
            }
            // RecordLocator
            if (isset($data->airCheckStatusRsp->pnrLocator)) {
                $trip['RecordLocator'] = $data->airCheckStatusRsp->pnrLocator;
            }
            $this->logger->info('Air Parse Itinerary #' . $trip['RecordLocator'], ['Header' => 3]);

            // ReservationDate
            if (isset($data->airCheckStatusRsp->lastChangeDateTime)) {
                $trip['ReservationDate'] = strtotime(substr($data->airCheckStatusRsp->lastChangeDateTime, 0, -3));
            }
            // Passengers, TicketNumbers, AccountNumbers
            $trip['TicketNumbers'] = [];
            $seatsByOrigAirport = [];

            if (isset($data->airCheckStatusRsp->passenger) && is_array($data->airCheckStatusRsp->passenger)) {
                foreach ($data->airCheckStatusRsp->passenger as $passenger) {
                    if (isset($passenger->personName) && isset($passenger->personName->givenName) && isset($passenger->personName->surname)) {
                        if (isset($passenger->personName->middleName)) {
                            $trip['Passengers'][] = beautifulName(sprintf('%s %s %s', $passenger->personName->givenName, $passenger->personName->middleName, $passenger->personName->surname));
                        } else {
                            $trip['Passengers'][] = beautifulName(sprintf('%s %s', $passenger->personName->givenName, $passenger->personName->surname));
                        }
                    }

                    if (isset($passenger->ticketNumber) && is_array($passenger->ticketNumber)) {
                        $trip['TicketNumbers'] = array_merge($trip['TicketNumbers'], $passenger->ticketNumber);
                    }

                    if (isset($passenger->custLoyalty) && is_array($passenger->custLoyalty)) {
                        foreach ($passenger->custLoyalty as $custLoyalty) {
                            if (isset($custLoyalty->vendorCode, $custLoyalty->membershipId)) {
                                $trip['AccountNumbers'][] = $custLoyalty->vendorCode . '-' . $custLoyalty->membershipId;
                            }
                        }
                    }

                    if (isset($passenger->seat) && is_array($passenger->seat)) {
                        foreach ($passenger->seat as $seat) {
                            if (isset($seat->row, $seat->column)) {
                                if (isset($seat->segmentOrigAirport)) {
                                    $seatsByOrigAirport[$seat->segmentOrigAirport][] = $seat->row . $seat->column;
                                }
                            }
                        }
                    }
                }
            }

            // Segments
            if (isset($data->airCheckStatusRsp->slice) && is_array($data->airCheckStatusRsp->slice)) {
                foreach ($data->airCheckStatusRsp->slice as $slice) {
                    if (isset($slice->segment) && is_array($slice->segment)) {
                        foreach ($slice->segment as $segment) {
                            $new = [];
                            // Cabin
                            if (isset($segment->cabinName)) {
                                $new['Cabin'] = $segment->cabinName;
                            }
                            // FlightNumber
                            if (isset($segment->flightNumber)) {
                                $new['FlightNumber'] = $segment->flightNumber;
                            }

                            // Aircraft
                            if (isset($segment->equipmentCode, $data->airCheckStatusRsp->equipment)) {
                                foreach ($data->airCheckStatusRsp->equipment as $aircraft) {
                                    if (isset($aircraft->code) && $aircraft->code == $segment->equipmentCode) {
                                        $new['Aircraft'] = $aircraft->name;

                                        break;
                                    }
                                }
                            }
                            // DepCode
                            if (isset($segment->origAirport)) {
                                $new['DepCode'] = $segment->origAirport;

                                if (isset($ports[$segment->origAirport])) {
                                    $new['DepName'] = $ports[$segment->origAirport];
                                }
                                // Seats
                                if (count($seatsByOrigAirport) > 0 && isset($seatsByOrigAirport[$segment->origAirport])) {
                                    $new['Seats'] = implode(", ", array_unique($seatsByOrigAirport[$segment->origAirport]));
                                }
                            }// if (isset($segment->origAirport))
                            // ArrCode
                            if (isset($segment->destAirport)) {
                                $new['ArrCode'] = $segment->destAirport;

                                if (isset($ports[$segment->destAirport])) {
                                    $new['ArrName'] = $ports[$segment->destAirport];
                                }
                            }// if (isset($segment->destAirport))
                            // AirlineName
                            if (isset($segment->marketingAirline)) {
                                $new['AirlineName'] = $segment->marketingAirline;
                            }
                            // DepDate
                            if (isset($segment->departDateTime)) {
                                $new['DepDate'] = strtotime($segment->departDateTime);
                            }
                            // ArrDate
                            if (isset($segment->arrivalDateTime)) {
                                $new['ArrDate'] = strtotime($segment->arrivalDateTime);
                            }
                            // BookingClass
                            if (isset($segment->bkgClass)) {
                                $new['BookingClass'] = $segment->bkgClass;
                            }
                            // TraveledMiles
                            if (isset($segment->distance)) {
                                $new['TraveledMiles'] = $segment->distance;
                            }
                            $trip['TripSegments'][] = $new;
                        }
                    }
                }
            }// foreach($slice->segment as $segment)

            if (isset($data->airCheckStatusRsp->pricingInfo)) {
                // BaseFare
                if (isset($data->airCheckStatusRsp->pricingInfo->baseFare)) {
                    $trip['BaseFare'] = number_format($data->airCheckStatusRsp->pricingInfo->baseFare, 2);
                }
                // Currency
                if (isset($data->airCheckStatusRsp->pricingInfo->currencyCode)) {
                    $trip['Currency'] = $data->airCheckStatusRsp->pricingInfo->currencyCode;
                }
                // TotalCharge
                if (isset($data->airCheckStatusRsp->pricingInfo->totalTripCost)) {
                    $trip['TotalCharge'] = number_format($data->airCheckStatusRsp->pricingInfo->totalTripCost, 2);
                }
                // Tax
                if (isset($data->airCheckStatusRsp->pricingInfo->totalTaxes)) {
                    $trip['Tax'] = number_format($data->airCheckStatusRsp->pricingInfo->totalTaxes, 2);
                }
            }// if (isset($data->airCheckStatusRsp->pricingInfo))
        }// if (isset($data->airCheckStatusRsp))

        return $trip;
    }

    protected function ParseAirTripJsonFromList($key, $offers, $retrieve = false)
    {
        $this->logger->notice(__METHOD__);

        if ($retrieve) {
            if (isset($offers[0]) && isset($offers[0]->offerToken)) {
                $key = $offers[0]->offerToken;
            } else {
                if (!empty($this->arFields)) {
                    $this->confNoNotification($this->arFields);
                }

                return [];
            }
        }

        for ($i = -1, $iCount = count($offers); ++$i < $iCount;) {
            if ($key == $offers[$i]->offerToken && isset($offers[$i]->trip) && isset($offers[$i]->trip->primaryOffer)
                && isset($offers[$i]->trip->primaryOffer->itinerary)
            ) {
                $trip = $offers[$i]->trip;

                if (isset($offers[$i]->accepted)) {
                    if ($offers[$i]->accepted == false) {
                        return [];
                    }
                } else {
                    $this->sendNotification("priceline - structure of json is changed");

                    return [];
                }
                //there are details
                //				$this->http->GetURL('https://www.priceline.com/receipt/?offer-token=' . $key . '/#/accept/');
                //				$data = $this->http->FindPreg("/BOOTSTRAP_DATA.offerDetails = (\{.+\});/");

                /** @var AirTrip $airtrip */
                $airtrip = ['Kind' => 'T', 'Passengers' => [], 'TripSegments' => []];
                // RecordLocator, TripNumber
                if (isset($trip->primaryOffer->offerNum)) {
                    $airtrip['RecordLocator'] = $trip->primaryOffer->offerNum;
                    $airtrip['TripNumber'] = $trip->primaryOffer->offerNum;
                }
                // RecordLocator
                if (isset($trip->primaryOffer->pnrCode)) {
                    $airtrip['RecordLocator'] = $trip->primaryOffer->pnrCode;
                }
                $this->logger->info('Air Parse Itinerary #' . ArrayVal($airtrip, 'RecordLocator'), ['Header' => 3]);

                // ReservationDate
                if (isset($trip->primaryOffer->offerDateTime)) {
                    $unixDate = strtotime($trip->primaryOffer->offerDateTime);
                    $unixDate = $unixDate - $unixDate % 60;
                    $airtrip['ReservationDate'] = $unixDate;
                }

                // Status
                if (isset($trip->statusReason->statusCode)) {
                    $airtrip['Status'] = $trip->statusReason->statusCode;
                }

                if (isset($trip->primaryOffer->summaryOfCharges) && isset($trip->primaryOffer->summaryOfCharges->requestedCharges)) {
                    $requestedCharges = $trip->primaryOffer->summaryOfCharges->requestedCharges;
                    // BaseFare
                    if (isset($requestedCharges->subTotal) && isset($requestedCharges->subTotal->amount)) {
                        $airtrip['BaseFare'] = $requestedCharges->subTotal->amount;
                    } elseif (isset($requestedCharges->unitCost) && isset($requestedCharges->unitCost->amount)) {
                        $airtrip['BaseFare'] = $requestedCharges->unitCost->amount;
                    }
                    // Tax
                    if (isset($requestedCharges->totalTaxesAndFees) && isset($requestedCharges->totalTaxesAndFees->amount)) {
                        $airtrip['Tax'] = $requestedCharges->totalTaxesAndFees->amount;
                    }
                    // TotalCharge
                    if (isset($requestedCharges->grandTotal) && isset($requestedCharges->grandTotal->amount)) {
                        $airtrip['TotalCharge'] = $requestedCharges->grandTotal->amount;
                    }
                    // Currency
                    if (isset($requestedCharges->grandTotal) && isset($requestedCharges->grandTotal->currency)) {
                        $airtrip['Currency'] = $requestedCharges->grandTotal->currency;
                    }
                }
                $seatsByFN = [];
                $airtrip['TicketNumbers'] = [];
                // Passengers, AccountNumbers, TicketNumbers
                if (isset($trip->primaryOffer->passengerBeans) && is_array($trip->primaryOffer->passengerBeans)) {
                    foreach ($trip->primaryOffer->passengerBeans as $passenger) {
                        if (isset($passenger->lastName) && isset($passenger->firstName)) {
                            if (isset($passenger->middleName)) {
                                $airtrip['Passengers'][] = beautifulName(sprintf('%s %s %s', $passenger->firstName, $passenger->middleName, $passenger->lastName));
                            } else {
                                $airtrip['Passengers'][] = beautifulName(sprintf('%s %s', $passenger->firstName, $passenger->lastName));
                            }
                        }

                        if (isset($passenger->mileageAccountNumbers)) {
                            if (isset($airtrip['AccountNumbers'])) {
                                $airtrip['AccountNumbers'] = array_values(array_unique(array_merge($airtrip['AccountNumbers'], array_values((array) $passenger->mileageAccountNumbers))));
                            } else {
                                $airtrip['AccountNumbers'] = array_values((array) $passenger->mileageAccountNumbers);
                            }
                        }

                        if (isset($passenger->ticketNumbers) && is_array($passenger->ticketNumbers)) {
                            $numbers = array_map('trim', $passenger->ticketNumbers);
                            $airtrip['TicketNumbers'] = array_merge($airtrip['TicketNumbers'], $numbers);
                        }

                        if (isset($passenger->seats) && is_array($passenger->seats)) {
                            foreach ($passenger->seats as $seat) {
                                if (isset($seat->seatRow, $seat->seatCol)) {
                                    if (isset($seat->flightNumber)) {
                                        $seatsByFN[$seat->flightNumber][] = $seat->seatRow . $seat->seatCol;
                                    }
                                }
                            }
                        }
                    }
                }

                $itinerary = $trip->primaryOffer->itinerary;
                $confirmNums = [];

                foreach ($itinerary->slices as $slice) {
                    if (isset($slice->segmentBeans) && is_array($slice->segmentBeans)) {
                        foreach ($slice->segmentBeans as $segment) {
                            /** @var AirTripSegment $seg */
                            $seg = [];
                            // AirlineName
                            if (isset($segment->carrierCode)) {
                                $seg['AirlineName'] = $segment->carrierCode;
                            }
                            // FlightNumber
                            if (isset($segment->flightNum)) {
                                $seg['FlightNumber'] = $segment->flightNum;
                                // Seats
                                if (count($seatsByFN) > 0 && isset($seatsByFN[$segment->flightNum])) {
                                    $seg['Seats'] = implode(", ", $seatsByFN[$segment->flightNum]);
                                }
                            }
                            // DepDate
                            if (isset($segment->departDateTime)) {
                                $seg['DepDate'] = strtotime($segment->departDateTime);
                            }
                            // ArrDate
                            if (isset($segment->arriveDateTime)) {
                                $seg['ArrDate'] = strtotime($segment->arriveDateTime);
                            }
                            // DepCode
                            if (isset($segment->origAirport)) {
                                $seg['DepCode'] = $segment->origAirport;
                            }
                            // ArrCode
                            if (isset($segment->destAirport)) {
                                $seg['ArrCode'] = $segment->destAirport;
                            }
                            // Aircraft
                            if (isset($segment->equipmentName)) {
                                $seg['Aircraft'] = $segment->equipmentName;
                            }
                            // Stops
                            if (isset($segment->numStops)) {
                                $seg['Stops'] = $segment->numStops;
                            }
                            // Cabin
                            if (isset($segment->cabinClassDesc)) {
                                $seg['Cabin'] = $segment->cabinClassDesc;
                            }
                            // BookingClass
                            if (isset($segment->bookingClass)) {
                                $seg['BookingClass'] = $segment->bookingClass;
                            }
                            // TraveledMiles
                            if (isset($segment->flownMileage)) {
                                $seg['TraveledMiles'] = $segment->flownMileage;
                            }
                            // Duration
                            if (isset($segment->totalElapsedTime)) {
                                $seg['Duration'] = sprintf('%02d h %02d min', $segment->totalElapsedTime / 60, $segment->totalElapsedTime % 60);
                            }

                            if (isset($segment->carrierLocator)) {
                                $confirmNums[] = $segment->carrierLocator;
                            }
                            $airtrip['TripSegments'][] = $seg;
                        }
                    }
                }

                if (count($confirmNums) > 0) {
                    $airtrip['ConfirmationNumbers'] = implode(", ", array_unique($confirmNums));
                }

                $this->logger->debug('Parsed Air:');
                $this->logger->debug(var_export($airtrip, true), ['pre' => true]);

                return $airtrip;
            }
        }

        return [];
    }

    protected function ParseConfirmationJsonHotel($product)
    {
        $it = ['Kind' => 'R'];

        if (isset($product->hotel->hotelName)) {
            $it['HotelName'] = $product->hotel->hotelName;
        }

        if (isset($product->hotel->hotelAddr->city)
            && isset($product->hotel->hotelAddr->line1)
            && isset($product->hotel->hotelAddr->countryName)) {
            $it['Address'] = sprintf('%s, %s, %s', $product->hotel->hotelAddr->line1, $product->hotel->hotelAddr->city, $product->hotel->hotelAddr->countryName);
        }

        if (isset($product->travelStartDate) && isset($product->hotel->checkInTime)) {
            $it['CheckInDate'] = strtotime(sprintf('%s %s', preg_replace('/T[\d:]+$/', '', $product->travelStartDate), $product->hotel->checkInTime));
        }

        if (isset($product->travelEndDate) && isset($product->hotel->checkOutTime)) {
            $it['CheckOutDate'] = strtotime(sprintf('%s %s', preg_replace('/T[\d:]+$/', '', $product->travelEndDate), $product->hotel->checkOutTime));
        }

        if (isset($product->rooms) && is_array($product->rooms)) {
            $it['Rooms'] = count($product->rooms);

            if (isset($product->rooms[0]->confirmationNum)) {
                $it['ConfirmationNumber'] = $product->rooms[0]->confirmationNum;
            }
        }

        if (isset($product->summaryOfCharges->grandTotal->amount)) {
            $it['Total'] = $product->summaryOfCharges->grandTotal->amount;
        }

        if (isset($product->summaryOfCharges->grandTotal->currency)) {
            $it['Currency'] = $product->summaryOfCharges->grandTotal->currency;
        }

        return $it;
    }

    protected function ParseConfirmationJsonFlight($product)
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        if (isset($product->passengerBeans) && is_array($product->passengerBeans)) {
            $it['Passengers'] = $it['TicketNumbers'] = [];

            foreach ($product->passengerBeans as $bean) {
                if (isset($bean->firstName) && isset($bean->lastName)) {
                    $it['Passengers'][] = sprintf('%s %s', $bean->firstName, $bean->lastName);
                }

                if (isset($bean->ticketNumbers) && is_array($bean->ticketNumbers)) {
                    $it['TicketNumbers'] = array_merge($it['TicketNumbers'], $bean->ticketNumbers);
                }
            }

            if (count($it['TicketNumbers']) > 0) {
                $tickets = [];

                foreach ($it['TicketNumbers'] as $ticket) {
                    if (is_string($ticket)) {
                        $tickets[] = trim($ticket);
                    }
                }
                $it['TicketNumbers'] = implode(',', $tickets);
            }
        }

        if (isset($product->pnrCode)) {
            $it['RecordLocator'] = $product->pnrCode;
        }

        if (isset($product->itinerary->slices) && is_array($product->itinerary->slices)) {
            foreach ($product->itinerary->slices as $slice) {
                if (isset($slice->segmentBeans) && is_array($slice->segmentBeans)) {
                    foreach ($slice->segmentBeans as $bean) {
                        $segment = [];

                        if (isset($bean->carrierName)) {
                            $segment['AirlineName'] = $bean->carrierName;
                        }

                        if (isset($bean->flightNum)) {
                            $segment['FlightNumber'] = $bean->flightNum;
                        }

                        if (isset($bean->origAirport)) {
                            $segment['DepCode'] = $bean->origAirport;
                        }

                        if (isset($bean->origAirportName)) {
                            $segment['DepName'] = $bean->origAirportName;
                        }

                        if (isset($bean->destAirport)) {
                            $segment['ArrCode'] = $bean->destAirport;
                        }

                        if (isset($bean->destAirportName)) {
                            $segment['ArrName'] = $bean->destAirportName;
                        }

                        if (isset($bean->equipmentName)) {
                            $segment['Aircraft'] = $bean->equipmentName;
                        }

                        if (isset($bean->cabinClassDesc)) {
                            $segment['Cabin'] = $bean->cabinClassDesc;
                        }

                        if (isset($bean->bookingClass)) {
                            $segment['BookingClass'] = $bean->bookingClass;
                        }

                        if (isset($bean->departDateTime)) {
                            $segment['DepDate'] = strtotime($bean->departDateTime);
                        }

                        if (isset($bean->arriveDateTime)) {
                            $segment['ArrDate'] = strtotime($bean->arriveDateTime);
                        }
                        $it['TripSegments'][] = $segment;
                    }
                }
            }
        }

        if (isset($product->summaryOfCharges->grandTotal->amount)) {
            $it['TotalCharge'] = $product->summaryOfCharges->grandTotal->amount;
        }

        if (isset($product->summaryOfCharges->grandTotal->currency)) {
            $it['Currency'] = $product->summaryOfCharges->grandTotal->currency;
        }

        return $it;
    }

    protected function ParseConfirmationJsonRental($product)
    {
        $it = ['Kind' => 'L'];

        if (isset($product->confirmationNumber)) {
            $it['Number'] = $product->confirmationNumber;
        }

        if (isset($product->vehicle->className)) {
            $it['CarType'] = $product->vehicle->className;
        }

        if (isset($product->vehicleExample->makeAndModel)) {
            $it['CarModel'] = $product->vehicleExample->makeAndModel;
        }

        if (isset($product->rentalLocation)) {
            $loc = $product->rentalLocation;

            if (isset($loc->rentalPartner->partnerName)) {
                $it['RentalCompany'] = $loc->rentalPartner->partnerName;
            }

            if (isset($loc->rentalPartner->partnerPhone)) {
                $it['PickupPhone'] = $loc->rentalPartner->partnerPhone;
            }
            $addr = [];

            if (isset($loc->name)) {
                $addr[] = $loc->name;
            }

            if (isset($loc->addressLine1)) {
                $addr[] = $loc->addressLine1;
            }

            if (isset($loc->city)) {
                $addr[] = $loc->city;
            }

            if (isset($loc->countryCode)) {
                $addr[] = $loc->countryCode;
            }

            if (isset($loc->zipCode)) {
                $addr[] = $loc->zipCode;
            }
            $it['PickupLocation'] = implode(', ', $addr);
        }

        if (isset($product->dropoffRentalLocation)) {
            $loc = $product->dropoffRentalLocation;

            if (isset($loc->rentalPartner->partnerPhone)) {
                $it['DropoffPhone'] = $loc->rentalPartner->partnerPhone;
            }
            $addr = [];

            if (isset($loc->name)) {
                $addr[] = $loc->name;
            }

            if (isset($loc->addressLine1)) {
                $addr[] = $loc->addressLine1;
            }

            if (isset($loc->city)) {
                $addr[] = $loc->city;
            }

            if (isset($loc->countryCode)) {
                $addr[] = $loc->countryCode;
            }

            if (isset($loc->zipCode)) {
                $addr[] = $loc->zipCode;
            }
            $it['DropoffLocation'] = implode(', ', $addr);
        }

        if (isset($product->pickupTimeStr)) {
            $it['PickupDatetime'] = strtotime($product->pickupTimeStr);
        }

        if (isset($product->dropoffDateTime)) {
            $it['DropoffDatetime'] = strtotime($product->dropoffDateTime);
        }

        if (isset($product->summaryOfCharges->grandTotal->amount)) {
            $it['TotalCharge'] = $product->summaryOfCharges->grandTotal->amount;
        }

        if (isset($product->summaryOfCharges->grandTotal->currency)) {
            $it['Currency'] = $product->summaryOfCharges->grandTotal->currency;
        }

        return $it;
    }

    protected function clickPressAndHoldByMouse(
        $selenium,
        $captchaFrameXpath = '//iframe[@id="px-captcha-modal" or @title="Human verification challenge" and not(@style="display: none;")]',
        $captchaElemXpath = '//div[@id = "px-captcha"] | //p[contains(text(), "Press")]',
        $xOffset = 300,
        $yOffset = 40
    ) {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("xOffset: {$xOffset} / yOffset: {$yOffset}");

        $captchaFrame = $selenium->waitForElement(WebDriverBy::xpath($captchaFrameXpath), 5);
        $selenium->driver->switchTo()->frame($captchaFrame);
        $this->savePageToLogs($selenium);

        $captchaElem = $selenium->waitForElement(WebDriverBy::xpath($captchaElemXpath), 0);
        $this->savePageToLogs($selenium);

        if (!$captchaElem) {
            return false;
        }

        $mover = new MouseMover($selenium->driver);
        $mover->logger = $selenium->logger;
        $mouse = $selenium->driver->getMouse();
        $mover->enableCursor();

        $mouse->mouseMove($captchaElem->getCoordinates());
        $this->savePagetoLogs($selenium);

        if ($selenium->driver instanceof RemoteWebDriver) {
            // unsupported in new versions of webdriver
            $captchaCoords = $captchaElem->getCoordinates()->inViewPort();
        } else {
            $captchaCoords = $captchaElem->getLocation();
        }

        $this->logger->info(var_export([
            'x' => $captchaCoords->getX(),
            'y' => $captchaCoords->getY(),
        ], true), ['pre' => true]);

        $x = intval($captchaCoords->getX() + $xOffset);
        $y = intval($captchaCoords->getY() + $yOffset);
        $mover->moveToCoordinates(['x' => $x, 'y' => $y], ['x' => 0, 'y' => 0]);
        $this->savePagetoLogs($selenium);
        $this->logger->debug("clicking Verify you are human button");
        $mouse->mouseDown();

        sleep(3);
        $this->savePagetoLogs($selenium);
        $mouse->mouseDown();
        $this->savePagetoLogs($selenium);

        // need to mouseup and move to close captcha window
        sleep(10);
        $this->logger->debug("releasing mouse");
        $mouse->mouseUp();
        sleep(1);
        $this->savePagetoLogs($selenium);
        // $mover->moveToCoordinates(['x' => 50, 'y' => 50], ['x' => 0, 'y' => 0]);
        $x = intval($captchaCoords->getX() + 10);
        $y = intval($captchaCoords->getY() + 10);
        $mover->moveToCoordinates(['x' => $x, 'y' => $y], ['x' => 0, 'y' => 0]);
        $this->savePagetoLogs($selenium);

        return true;
    }

    private function markConfigAsBadOrSuccess($success = true): void
    {
        return;

        if ($success) {
            $this->logger->info("marking config {$this->config} as successful");
            Cache::getInstance()->set('priceline_config_' . $this->config, 1, 60 * 60);
        } else {
            $this->logger->info("marking config {$this->config} as bad");
            Cache::getInstance()->set('priceline_config_' . $this->config, 0, 60 * 60);
        }
    }

    private function setConfig()
    {
        $configs = self::CONFIGS;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            unset($configs['chrome-94-mac']);
        }

        $successfulConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('priceline_config_' . $key) === 1;
        });

        $neutralConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('priceline_config_' . $key) !== 0;
        });

        if (count($successfulConfigs) > 0) {
            $this->config = $successfulConfigs[array_rand($successfulConfigs)];
            $this->logger->info("found " . count($successfulConfigs) . " successful configs");
        } elseif (count($neutralConfigs) > 0) {
            $this->config = $neutralConfigs[array_rand($neutralConfigs)];
            $this->logger->info("found " . count($neutralConfigs) . " neutral configs");
        } else {
            $this->config = array_rand($configs);
        }

        /*
        $this->config = array_rand($configs);
        */

        $this->logger->info("selected config $this->config");
    }

    private function getItinerary($item, $headers)
    {
        $this->logger->debug("checkStatusUrl: " . $item->checkStatusUrl);
        $offerToken = $this->http->FindPreg('/offertoken=(.+)/', false, $item->checkStatusUrl);
        $headers['Referer'] = $item->checkStatusUrl;
        $query = http_build_query([
            'operationName' => 'getItineraryDetails',
            'variables'     => json_encode([
                'offerToken'        => $offerToken,
                'summaryDateFormat' => 'MM/dd/yyyy',
                'dateHeaderFormat'  => 'MMM d',
                'plfCode'           => 'PCLN',
            ]),
            'extensions' => json_encode([
                'persistedQuery' => [
                    'version'    => 1,
                    'sha256Hash' => '62087fca68a6e9ea5cc29825fea676d9b4582477deb388ed2ed52f0201949488',
                ],
            ]),
        ]);
        $this->http->GetURL("https://www.priceline.com/pws/v0/pcln-graph/?" . $query, $headers);
        $response = $this->http->JsonLog();

        // prevent traces
        if (!isset($response->data)) {
            $this->sendNotification('upd sha256Hash // MI');
            $this->logger->error("something went wrong, may be sha256Hash issue");

            return;
        }

        switch ($item->type) {
            case 'FLY':
                $this->parseItineraryFlight($response);

                break;

            case 'DRIVE':
                $this->parseItineraryRental($response);

                break;

            case 'STAY':
                $this->sendNotification('Check stay // MI');
                $this->parseItineraryStay($response);

                break;

            default:
                $this->sendNotification('new type // MI');

                break;
        }
    }

    private function captchaPressHold($selenium): bool
    {
        if ($captchaFrame = $selenium->waitForElement(WebDriverBy::xpath('//iframe[@id="px-captcha-modal" or @title="Human verification challenge" and not(@style="display: none;")]'), 7, false)) {
            $selenium->driver->switchTo()->frame($captchaFrame);
            $this->savePageToLogs($selenium);

            if (!$pressAndHold = $selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Press")]'), 0, false)) {
                return false;
            }
            $mover = new MouseMover($selenium->driver);
//                $mover->logger = $this->logger;
            $mover->enableCursor();

            // throws exception on chrome_94, seems that mouse cannot be moved on element with visibility == false (even if element is actually visible)
//                $mover->moveToElement($pressAndHold);
            try {
                $mouse = $selenium->driver->getMouse()->mouseDown($pressAndHold->getCoordinates());
            } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("UnknownErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);
            }

            $this->savePageToLogs($selenium);
            $success = $this->waitFor(function () use ($selenium) {
                return is_null($selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Press")]'), 0, false));
            }, 20);

            try {
                $mouse->mouseUp();
            } catch (ErrorException $e) {
                $this->logger->error("ErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);
            }

            $selenium->driver->switchTo()->defaultContent();
            $this->savePageToLogs($selenium);

            if (!$success) {
                return false;
            }
        }

        return true;
    }
}
