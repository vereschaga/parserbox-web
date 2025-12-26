<?php

namespace AwardWallet\Engine\virgin\RewardAvailability;

use CheckRetryNeededException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;

class ParserScrap extends \TAccountChecker
{
    use \SeleniumCheckerHelper;

    private const ATTEMPTS_CNT = 4;

    private $airportDest = [];
    private $cacheKey;
    private $supportedCurrencies = ['USD'];

    private $username = 'brd-customer-hl_52f2fb6f-zone-scraping_browser';
    private $host1 = 'brd.superproxy.io:9222'; // default
    private $host2 = 'brd.superproxy.io:9515'; // selenium
    private $pass = 'j64nsjuahem7';

    /** @var \RemoteWebDriver */
    private $browser;

    public static function getRASearchLinks(): array
    {
        return ['https://www.virginatlantic.com/us/en' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => $this->supportedCurrencies,
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'USD',
        ];
    }

    public function LoadLoginForm()
    {
        $this->cacheKey = $this->getUuid();

        return true;
    }

    public function Login()
    {
        return true;
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug('Params: ' . var_export($fields, true));

        if ($fields['Adults'] > 9) {
            $this->logger->info('Error in params');
            $this->logger->error("It's too much travellers");

            return ['routes' => []];
        }

        if ($fields['DepDate'] > strtotime('+331 day')) {
            $this->SetWarning('Ah - something\'s not right here. We can only show you flights up to 331 days in advance - can you also check the return date is after the departure date');

            return [];
        }

        $this->http->RetryCount = 0;

        if (!$this->validRouteAll($fields)) {
            return ['routes' => []];
        }
        $this->http->RetryCount = 2;

        if (in_array($fields['Currencies'][0], $this->supportedCurrencies) !== true) {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        $routes = $this->ParseReward($fields);

        return ["routes" => $routes];
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

    /**
     * @param int $timeout
     * @param bool $visible
     *
     * @return RemoteWebElement|null
     */
    protected function waitForElement2(WebDriverBy $by, $timeout = 60, $visible = true)
    {
        /** @var RemoteWebElement $element */
        $element = null;
        $start = time();
        $this->waitFor(
            function () use ($by, &$element, $visible) {
                try {
                    $elements = $this->driver->findElements($by);
                } catch (Facebook\WebDriver\Exception\StaleElementReferenceException | StaleElementReferenceException $e) {
                    $this->logger->error("[waitForElement exception on findElements]: " . $e->getMessage(), ['HtmlEncode' => true]);
                    sleep(1);
                    $elements = $this->driver->findElements($by);
                }

                foreach ($elements as $element) {
                    try {
                        if ($visible && !$element->isDisplayed()) {
                            $element = null;
                        }
                    } catch (Facebook\WebDriver\Exception\StaleElementReferenceException | StaleElementReferenceException $e) {
                        $this->logger->error("[waitForElement StaleElementReferenceException on isDisplayed]: " . $e->getMessage(), ['HtmlEncode' => true]);
                        // isDisplayed throws this if element already disappeared from page
                        $element = null;
                    }

                    return !empty($element);
                }

                return false;
            },
            $timeout
        );
        $timeSpent = time() - $start;

        if (!empty($element)) {
            try {
                $this->http->Log("found element {$by->getValue()}, displayed: {$element->isDisplayed()}, text: '" . trim($element->getText()) . "', spent time: $timeSpent", LOG_LEVEL_NOTICE);
            } catch (\Facebook\WebDriver\Exception\StaleElementReferenceException | StaleElementReferenceException $e) {
                // final fallback for element disappearance, getText throws this too
                $this->http->Log("element {$by->getValue()} found and disappeared, spent time: $timeSpent");
                $element = null;
                $timeLeft = $timeout - $timeSpent;

                if ($timeLeft > 0) {
                    $this->http->Log("restarting search, time left: $timeLeft");

                    return $this->waitForElement2($by, $timeLeft, $visible);
                }
            }
        } else {
            $this->http->Log("element {$by->getValue()} not found, spent time: $timeSpent");
        }

        return $element;
    }

    private function runScraper()
    {
        $AUTH = $this->username . ':' . $this->pass;

        $SBR_WEBDRIVER = 'https://' . $this->username . '-' . $AUTH . '@' . $this->host2;

        return RemoteWebDriver::create($SBR_WEBDRIVER);
    }

    private function decodeCabin($cabin)
    {
        switch ($cabin) {
            case 'VSLT':
            case 'VSCL':
            case 'VSDT':
            case 'E':
            case 'AFST': // AirFrance
            case 'DCP': // Delta
            case 'DPPS': // Delta
            case 'KLEC': // KLM
            case 'MAIN':
                return 'economy';

            case 'VSPE':
            case 'PE':
            case 'AFPE':
            case 'KLPE':
                return 'premiumEconomy';

            case 'VSUP':
            case 'BU':
            case 'AFBU': // AirFrance
            case 'KLBU': // KLM
                return 'business';

            case 'FIRST':
            case 'D1':
            case 'D1S':
                return 'firstClass';
        }
        $this->sendNotification('new cabin ' . $cabin . ' // ZM');

        return null;
    }

    private function encodeCabin($cabin)
    {
        switch ($cabin) {
            case 'economy': return 'VSLT';

            case 'premiumEconomy': return 'VSPE';

            case 'business': return 'VSUP';

            case 'firstClass': return 'VSUP';
        }
        $this->sendNotification('new cabin ' . $cabin . ' // ZM');

        return null;
    }

    private function ParseReward($fields = []): array
    {
        // Get cacheKey
        // Load data
        $fields['DepDate'] = date("Y-m-d", $fields['DepDate']);
        $brandID = $this->encodeCabin($fields['Cabin']);

        $data = $this->getData($fields, $brandID);

        if (is_array($data) && empty($data)) {
            return [];
        }

        if (empty($data)) {
            throw new \CheckException('failed to get data', ACCOUNT_ENGINE_ERROR);
        }

        return $this->parseRewardFlights($data, $fields);
    }

    private function parseRewardFlights($data, array $fields): array
    {
        $this->logger->info("parseRewardFlights [" . $fields['DepDate']
            . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);
        $routes = [];

        if (isset($data->shoppingError, $data->shoppingError->error, $data->shoppingError->error->message, $data->shoppingError->error->message->message)) {
            $this->logger->error($data->shoppingError->error->message->message);

            if (strpos($data->shoppingError->error->message->message,
                    'No results were found for your search. You may have better results if you use flexible dates and airports or if you search for a One-Way') !== false) {
                $this->SetWarning("Sorry, no reward flights are available for your search, some flights don't operate every day. Try selecting flexible dates to see more availability.#101638_A");
            } elseif (strpos($data->shoppingError->error->message->message,
                    'No results were found for your search. Try changing your cities or dates. Some itineraries may not be offered') !== false
                || strpos($data->shoppingError->error->message->message,
                    'for the date selected is not available. The next available flight departs on') !== false) {
                $this->SetWarning("Sorry, no flights are available for that search. As some flights don't operate every day, try selecting flexible dates or nearby airports to see more availability.#101639A");
            } elseif (strpos($data->shoppingError->error->message->message,
                    "We're sorry, there was a problem processing your request. Please go back and try the entry again.") !== false) {
                // bad route
                $this->SetWarning("We're sorry, there was a problem processing your request. Please go back and try the entry again.");
            } elseif (strpos($data->shoppingError->error->message->message,
                    "We're sorry, but we are unable to find a flight with Delta or our partner airlines that currently services this route. Please try again") !== false) {
                $this->SetWarning("Sorry, no flights are available for that search. As some flights don't operate every day, try selecting flexible dates or nearby airports to see more availability.");
            } elseif (strpos($data->shoppingError->error->message->message,
                    "Please try searching for airports within a 100 mile radius of") !== false
                || strpos($data->shoppingError->error->message->message,
                    "We're sorry, but there are not enough seats available on this flight to complete your booking") !== false
                || strpos($data->shoppingError->error->message->message,
                    "Travel for the date you selected is not offered or sold out. ") !== false
            ) {
                $this->SetWarning("Sorry, there are no flights available for that search. Please try again");
            } elseif (strpos($data->shoppingError->error->message->message,
                    "There are no flights available for the date(s) requested. Please change your cities or dates") !== false
                || strpos($data->shoppingError->error->message->message,
                    "There are no flights available for the search criteria provided.") !== false
                || strpos($data->shoppingError->error->message->message,
                    "re sorry, but we do not fly this route on the selected day. Some of our flights operate seasonally") !== false
                || strpos($data->shoppingError->error->message->message,
                    "re sorry, but flights to this destination have either departed for the day or are departing too soon to be booked. Please try again by selecting a different date") !== false
                || strpos($data->shoppingError->error->message->message,
                    "re sorry, but we do not fly this route on the selected day. Some of our routes operate seasonally or on select days of the week") !== false
                || strpos($data->shoppingError->error->message->message,
                    "re sorry, but we are unable to find a flight that meets your current search criteria. Please try again by") !== false
                || strpos($data->shoppingError->error->message->message,
                    "re sorry, but we are unable to find a flight that meets your current search criteria. Please try again.") !== false
                || strpos($data->shoppingError->error->message->message,
                    "We are unable to find a flight on the selected date with enough available seats") !== false
                || strpos($data->shoppingError->error->message->message,
                    "We're sorry, but online bookings for this route require an advance purchase") !== false
            ) {
                $this->SetWarning($data->shoppingError->error->message->message);
            } else {
                $this->sendNotification('check error msg // ZM');
            }

            return [];
        }

        if (isset($data->shoppingError, $data->shoppingError->error, $data->shoppingError->error->message, $data->shoppingError->error->message->message)) {
            $this->logger->error($data->shoppingError->error->message->message);

            return [];
        }

        if (!isset($data->itinerary) || !is_array($data->itinerary)) {
            throw new \CheckException('itinerary not found. other format json', ACCOUNT_ENGINE_ERROR);
        }

        $this->logger->debug("Found " . count($data->itinerary) . " routes");

        foreach ($data->itinerary as $numRoot => $it) {
            if (count($it->trip) !== 1) {
                $this->logger->error("check itinerary $numRoot");
            }

            $trip = $it->trip[0];
            $this->logger->notice("Start route " . $numRoot);
            // for debug
            $this->http->JsonLog(json_encode($it), 1);

            $itOffers = [];

            foreach ($it->fare as $fare) {
                if ($fare->soldOut === false && $fare->offered === true) {
                    $itOffers[] = $fare;
                }
            }

            foreach ($itOffers as $itOffer) {
                $segDominantSegmentBrandId = $itOffer->dominantSegmentBrandId;
                $result = [
                    'distance'  => null,
                    'num_stops' => $trip->stopCount,
                    'times'     => [
                        'flight'  => null,
                        'layover' => null,
                    ],
                    'redemptions' => [
                        // totalPriceByPTC - price for all, totalPrice - for ane
                        'miles'   => $itOffer->totalPrice->miles->miles,
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $itOffer->totalPrice->currency->code,
                        'taxes'    => $itOffer->totalPrice->currency->amount,
                        'fees'     => null,
                    ],
                    'tickets'        => $itOffer->seatsAvailableCount ?? null,
                    'classOfService' => $this->clearCOS($this->getBrandName($itOffer->dominantSegmentBrandId, $itOffer->brandByFlightLeg)),
                ];

                $result['connections'] = [];

                foreach ($trip->flightSegment as $flightSegment) {
                    $flightSegmentId = $flightSegment->id;

                    foreach ($flightSegment->flightLeg as $numLeg=>$flightLeg) {
                        $flightLegId = $flightLeg->id;
                        $seg = [
                            'departure' => [
                                'date'     => date('Y-m-d H:i', strtotime($flightLeg->schedDepartLocalTs)),
                                'dateTime' => strtotime($flightLeg->schedDepartLocalTs),
                                'airport'  => $flightLeg->originAirportCode,
                            ],
                            'arrival' => [
                                'date'     => date('Y-m-d H:i', strtotime($flightLeg->schedArrivalLocalTs)),
                                'dateTime' => strtotime($flightLeg->schedArrivalLocalTs),
                                'airport'  => $flightLeg->destAirportCode,
                            ],
                            'meal'       => null,
                            'cabin'      => null,
                            'fare_class' => null,
                            'distance'   => $flightLeg->distance->measure . ' ' . $flightLeg->distance->unit,
                            'aircraft'   => $flightLeg->aircraft->fleetTypeCode,
                            'flight'     => [$flightLeg->viewSeatUrl->fltNumber],
                            'airline'    => $flightLeg->marketingCarrier->code,
                            'operator'   => $flightLeg->operatingCarrier->code,
                            'times'      => [
                                'flight'  => null,
                                'layover' => null,
                            ],
                        ];

                        foreach ($flightLeg->viewSeatUrl->fareOffer->itineraryOfferList as $list) {
                            if ($list->dominantSegmentBrandId === $segDominantSegmentBrandId) {
                                foreach ($list->brandInfoByFlightLegs as $brandByLeg) {
                                    if ($brandByLeg->flightSegmentId === $flightSegmentId && $brandByLeg->flightLegId === $flightLegId) {
                                        if ($brandByLeg->brandId === 'UNKNOWN') {
                                            $seg['cabin'] = $this->decodeCabin($list->dominantSegmentBrandId);
                                        } else {
                                            $seg['cabin'] = $this->decodeCabin($brandByLeg->brandId);
                                        }
                                        $seg['fare_class'] = $brandByLeg->cos;
                                        $brandName = $itOffer->brandByFlightLeg[$numLeg]->brandName;

                                        $seg['classOfService'] = $this->clearCOS($brandName);
//                                        $seg['classOfService'] = $brandName . ' (' . $brandByLeg->cos . ')'; // no full name with fare class
                                    }
                                }
                            }
                        }

                        $result['connections'][] = $seg;
                    }
                }

                $routes[] = $result;
            }
        }

        $this->logger->debug('Parsed data:');
        $this->logger->debug(var_export($routes, true), ['pre' => true]);

        return $routes;
    }

    private function clearCOS(string $cos): string
    {
        if (preg_match("/^(.+\w+) (?:cabin|class|standard|reward)$/i", $cos, $m)) {
            $cos = $m[1];
        }

        return $cos;
    }

    private function getBrandName($id, $list): ?string
    {
        foreach ($list as $item) {
            if ($item->brandId === $id && isset($item->brandName)) {
                return $item->brandName;
            }
        }

        return $this->brandID2Award($id);
    }

    private function brandID2Award(string $brandID): ?string
    {
        $array = [
            'MAIN'  => 'Economy Classic',
            'VSCL'  => 'Economy Classic',
            'VSPE'  => 'Premium',
            'VSUP'  => 'Upper Class',
            'FIRST' => 'Upper Class',
        ];

        if (!isset($array[$brandID])) {
            $this->sendNotification('check brandId: ' . $brandID);
        }

        return $array[$brandID] ?? null;
    }

    private function validRouteAll($fields)
    {
        $this->logger->notice(__METHOD__);

        $airports = \Cache::getInstance()->get('ra_virgin_airports');
        $airportDesc = \Cache::getInstance()->get('ra_virgin_airportDesc');

        if (!$airports || !is_array($airports) || !$airportDesc || !is_array($airportDesc)) {
            $airports = [];
            $airportDesc = [];

            $this->http->GetURL("https://www.virginatlantic.com/util/airports/ALL/asc", [], 20);

            if ($this->http->currentUrl() === 'https://www.virginatlantic.com/gb/en/error/system-unavailable1.html') {
                // it's work
                $this->http->GetURL("https://www.virginatlantic.com/util/airports/ALL/asc", [], 20);
            }
            $data = $this->http->JsonLog(null, 1);

            if ($this->http->currentUrl() === 'https://www.virginatlantic.com/gb/en/error/system-unavailable1.html') {
                throw new \CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
            }

            if (strpos($this->http->Error,
                    'Network error 56 - Received HTTP code 407 from proxy after CONNECT') !== false
                || strpos($this->http->Error,
                    'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
                || strpos($this->http->Error, 'Network error 28 - Operation timed out after ') !== false
                || $this->http->Response['code'] == 403
            ) {
                throw new CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
            }

            if (!isset($data->listOfCities) || !is_array($data->listOfCities)) {
                return true;
            }

            if (empty($data->listOfCities)) {
                return true;
            }

            foreach ($data->listOfCities as $city) {
                $airports[] = $city->airportCode;
                $airportDesc[$city->airportCode] = $city->cityName . ', ' . $city->region;
            }

            if (!empty($airports)) {
                \Cache::getInstance()->set('ra_virgin_airports', $airports, 60 * 60 * 24);
                \Cache::getInstance()->set('ra_virgin_airportDesc', $airportDesc, 60 * 60 * 24);
            }
        }

        if (!empty($airports) && !in_array($fields['DepCode'], $airports)) {
            $this->SetWarning('no flights from ' . $fields['DepCode']);

            return false;
        }

        if (!empty($airports) && !in_array($fields['ArrCode'], $airports)) {
            $this->SetWarning('no flights to ' . $fields['ArrCode']);

            return false;
        }

        $this->airportDest = [
            $fields['DepCode'] => $airportDesc[$fields['DepCode']],
            $fields['ArrCode'] => $airportDesc[$fields['ArrCode']],
        ];

        return true;
    }

    private function getData($fields, $brandID)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $selenium->http->saveScreenshots = true;
        $selenium->driver = $this->runScraper();
        $this->logger->info("check start page");
        $this->savePageToLogs($selenium);

//        $startUrl = 'https://www.virginatlantic.com/flight-search/book-a-flight';
        $startUrl = 'https://www.virginatlantic.com/en-US';
        $this->logger->debug('run start url: ' . $startUrl);

        $loadXPATH = "
            //h1[normalize-space()='Book a flight']
            | //h1[normalize-space()='So, where next?']
        ";

        try {
            $selenium->driver->get($startUrl);
        } catch (\Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error('WebDriverCurlException');

            throw new CheckRetryNeededException(5, 0);
        }

        $selenium->waitForElement2(WebDriverBy::xpath($loadXPATH),
            30, false);

        if ($btnCookies = $selenium->waitForElement2(WebDriverBy::xpath("//button[contains(text(),'Yes, I Agree')]"), 0)) {
            $btnCookies->click();
        }

        try {
            $SSOUser = $selenium->driver->manage()->getCookieNamed('SSOUser');
        } catch (\Facebook\WebDriver\Exception\NoSuchCookieException $e) {
            $SSOUser = null;
        }

        if (!empty($SSOUser)) {
            $this->logger->emergency('has cookie');
        }
        $selenium->driver->executeScript("document.cookie = 'SSOUser=JohnDoe; domain=.virginatlantic.com; path=/; secure';");

        $this->savePageToLogs($selenium);

        $loadPage = $selenium->waitForElement2(WebDriverBy::xpath($loadXPATH), 0, false);
        //sleep(200);
        if (!$loadPage) {
            return null;
        }

        $payload = $this->getPayload($fields, $brandID);

        try {
            $response = $this->search($selenium, $payload);
        } catch (\Facebook\WebDriver\Exception\JavascriptErrorException $e) {
            $this->logger->error('JavascriptErrorException: ' . $e->getMessage());
            sleep(1);
            $response = $this->search($selenium, $payload);
        }

        if (strpos($response, 'Access Denied') !== false) {
            $this->logger->error('Access Denied');

            return null;
        }
        $response = $this->http->JsonLog($response);

        return $response;
    }

    private function savePageToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        $result = $selenium->driver->executeScript('return document.documentElement.innerHTML');

        if (!is_array($result)) {
            if ($selenium->http->saveScreenshots && $selenium->driver instanceof \SeleniumDriver && $selenium->driver->webDriver !== null) {
                try {
                    $selenium->driver->webDriver->takeScreenshot($this->http->LogDir . '/' . sprintf("step%02d-screenshot.png",
                            $this->http->ResponseNumber));
                } catch (
                \Facebook\WebDriver\Exception\UnknownErrorException
                | \Facebook\WebDriver\Exception\WebDriverException
                | \Facebook\WebDriver\Exception\UnknownServerException
                | \WebDriverException
                | \UnknownServerException
                | \ErrorException
                $e
                ) {
                    $this->http->Log("[Failed to save screenshot]: exception - " . (strlen($e->getMessage()) > 300 ? substr($e->getMessage(), 0,
                                297) . '...' : $e->getMessage()), LOG_LEVEL_ERROR);
                }
            }
            // save page to logs
            $this->http->SetBody($result);
            $this->http->SaveResponse();
        } else {
            $this->http->SetBody('');
            $this->logger->error(var_export($result, true), ['pre' => true]);
        }
    }

    private function getPayload($fields, $brandID)
    {
        $payload = '{"action":"findFlights","destinationAirportRadius":{"unit":"MI","measure":100},"deltaOnlySearch":false,"originAirportRadius":{"unit":"MI","measure":100},"passengers":[{"type":"ADT","count":' . $fields['Adults'] . '},{"type":"GBE","count":0},{"type":"CNN","count":0},{"type":"INF","count":0}],"searchType":"search","segments":[{"origin":"' . $fields['DepCode'] . '","destination":"' . $fields['ArrCode'] . '","departureDate":"' . $fields['DepDate'] . '","returnDate":null}],"shopType":"MILES","tripType":"ONE_WAY","priceType":"Award","priceSchedule":"price","awardTravel":true,"refundableFlightsOnly":false,"nonstopFlightsOnly":false,"datesFlexible":false,"flexCalendar":false,"flexAirport":false,"upgradeRequest":false,"pageName":"FLIGHT_SEARCH","cacheKey":"' . $this->cacheKey . '","actionType":"flexDateSearch","initialSearchBy":{"meetingEventCode":"","refundable":false,"flexAirport":false,"flexDate":false,"flexDaysWeeks":"FLEX_DAYS"},"vendorDetails":{"vendorReferrerUrl":"https://www.virginatlantic.com/en-US"},"sortableOptionId":"priceAward","requestPageNum":"1","filter":null}';
//        $payload = '{"bestFare":"' . $brandID . '","action":"findFlights","destinationAirportRadius":{"unit":"MI","measure":100},"deltaOnlySearch":false,"meetingEventCode":"","originAirportRadius":{"unit":"MI","measure":100},"passengers":[{"type":"ADT","count":' . $fields['Adults'] . '}],"searchType":"search","segments":[{"origin":"' . $fields['DepCode'] . '","destination":"' . $fields['ArrCode'] . '","departureDate":"' . $fields['DepDate'] . '","returnDate":null}],"shopType":"MILES","tripType":"ONE_WAY","priceType":"Award","priceSchedule":"AWARD","awardTravel":true,"refundableFlightsOnly":false,"nonstopFlightsOnly":false,"datesFlexible":true,"flexCalendar":false,"flexAirport":false,"upgradeRequest":false,"pageName":"FLIGHT_SEARCH","cacheKey":"' . $this->cacheKey . '","requestPageNum":"1","actionType":"","initialSearchBy":{"fareFamily":"' . $brandID . '","meetingEventCode":"","refundable":false,"flexAirport":false,"flexDate":true,"flexDaysWeeks":"FLEX_DAYS"},"sortableOptionId":"priceAward","filter":null}';

        return $payload;
    }

    private function loadPayload($selenium, $payload)
    {
        $script = "
                var nn = 'postData'+localStorage.getItem('cacheKeySuffix');
                localStorage.removeItem(nn);
                localStorage.removeItem('cacheKeySuffix');
                localStorage.setItem('cacheKeySuffix', '{$this->cacheKey}');
                localStorage.setItem('postData{$this->cacheKey}', '{$payload}');
                localStorage.setItem('paymentType', 'miles');
                ";
        $this->logger->debug("[run script]");
        $this->logger->debug($script, ['pre' => true]);

        $selenium->driver->executeScript($script);
    }

    private function search($selenium, $payload)
    {
        $this->cacheKey = $this->getUuid();
        $payload = preg_replace('/cacheKey":"([^"]+)"/', 'cacheKey":"' . $this->cacheKey . '"', $payload);
        $result = $this->searchXMLHttp($selenium, $payload);

        return $result;
    }

    private function searchXMLHttp($selenium, $payload)
    {
        $this->logger->notice(__METHOD__);

        $script = '
                    var xhttp = new XMLHttpRequest();
                    xhttp.withCredentials = true;
                    xhttp.open("POST", "https://www.virginatlantic.com/shop/ow/search", false);
                    xhttp.setRequestHeader("Accept", "application/json");
                    xhttp.setRequestHeader("Content-type", "application/json");
                    xhttp.setRequestHeader("cachekey", "' . $this->cacheKey . '");
                    var resData = null; 
                    xhttp.onreadystatechange = function() {
                        resData = this.responseText;
                    };
                    xhttp.send(\'' . $payload . '\');
                    return resData;
                ';
        $this->logger->debug("[run script]");
        $this->logger->debug($script, ['pre' => true]);
        sleep(2);

        $response = $selenium->driver->executeScript($script);

        if (strpos($response, "{") !== 0) {
            $this->logger->debug($response);
        }

        return $response;
    }
}
