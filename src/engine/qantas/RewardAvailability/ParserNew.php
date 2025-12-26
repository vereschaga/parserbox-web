<?php

namespace AwardWallet\Engine\qantas\RewardAvailability;

class ParserNew extends \TAccountCheckerQantas
{
    use \SeleniumCheckerHelper;

    private $debugMode = false;

    // NB: пока не все маршруты без авторизации. (надо проверять)
    private $seleniumAuth = true;

    private $routeNeedAuth = true;
    private $checkNewFormat;
    /** @var \HttpBrowser */
    private $curl;
    private $noFligths = false;
    private $checkedBefore = false;

    private $memberLogin;
    private $depDetail;
    private $arrDetail;
    // for statistic
    private $memStatBrowsers;
    private $keyBrowser;

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();
        parent::InitBrowser();
        $this->KeepState = false;

        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");

        if ($this->AccountFields['ParseMode'] === 'awardwallet') {
            $this->setProxyGoProxies(null, 'au');
        } else {
            $this->setProxyNetNut(null, 'au');
        }
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function LoadLoginForm()
    {
        $this->checkBeforeStart($this->AccountFields['RaRequestFields']);

        if ($this->noFligths || !$this->routeNeedAuth) {
            return true;
        }

        // for juicymilse it's ok(no auth)
        if (!$this->debugMode) {
            throw new \CheckException('We are unable to process this request currently. Please try again later', ACCOUNT_PROVIDER_ERROR);
        }

        // TODO maybe from loyalty ??
        return false;
    }

    public function Login()
    {
        if ($this->noFligths || !$this->routeNeedAuth) {
            return true;
        }

        // for juicymilse it's ok(no auth)
        if (!$this->debugMode) {
            throw new \CheckException('We are unable to process this request currently. Please try again later', ACCOUNT_PROVIDER_ERROR);
        }

        // TODO maybe from loyalty ??
        return false;
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => ['USD'],
            'supportedDateFlexibility' => 0, // 1
            'defaultCurrency'          => 'USD',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        if (!$this->checkedBefore) {
            $this->checkBeforeStart($this->AccountFields['RaRequestFields']);
        }

        if ($this->noFligths) {
            $this->logger->debug('no route');

            return ['routes' => []];
        }

        if (!$this->routeNeedAuth) {
            $this->logger->notice('new design');

            return $this->ParseReward($fields, $this->memberLogin, $this->depDetail, $this->arrDetail);
        }

        $this->logger->notice('!!!need auth!!!');
        // TODO if login is ok
        throw new \CheckException('Something went wrong', ACCOUNT_ENGINE_ERROR);
    }

    public function isBadProxy($browser = null): bool
    {
        if (!isset($browser)) {
            $browser = $this->http;
        }

        return
            strpos($browser->Error, 'Network error 28 - Connection timed out after') !== false
            || strpos($browser->Error, 'Network error 56 - Proxy CONNECT aborted') !== false
            || strpos($browser->Error, 'Network error 56 - Unexpected EOF') !== false
            || strpos($browser->Error, 'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
            || strpos($browser->Error, 'Network error 56 - Received HTTP code 403 from proxy after CONNECT') !== false
            || strpos($browser->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            || strpos($browser->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT') !== false
            || strpos($browser->Error, 'Network error 0 -') !== false
            || $browser->Response['code'] == 403;
    }

    private function ParseReward($fields, $memberLogin, $depDetail, $arrDetail, ?bool $isRetry = false)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("ParseReward [" . date("Y-m-d",
                $fields['DepDate']) . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);
        $travelDates = date("Ymd0000", $fields['DepDate']);

        $this->logger->debug(var_export($depDetail, true));
        $this->logger->debug(var_export($arrDetail, true));

        if ($this->routeNeedAuth) {
            throw new \CheckException('need auth', ACCOUNT_ENGINE_ERROR);
        }

        if (isset($depDetail, $arrDetail) && $depDetail->isClassicOnly && $arrDetail->isClassicOnly) {
            $this->sendNotification("check format // ZM");
        }
        $this->http->GetURL("https://www.qantas.com/au/en.html");

        $this->selenium();

        return $this->newFormatWithoutAuth($fields, $depDetail, $arrDetail, $travelDates);
    }

    private function checkBeforeStart($fields)
    {
        $this->logger->notice(__METHOD__);
        $this->checkedBefore = true;

        if ($fields['DepDate'] > strtotime('+354 day')) {
            $this->SetWarning('too late');

            $this->noFligths = true;
        }

        if ($fields['Currencies'][0] !== 'USD') {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }
        $depDetail = $arrDetail = null;

        $dataOrigin = \Cache::getInstance()->get('ra_qantas_origin_' . $fields['DepCode']);
        $headers = [
            'Accept'  => '*/*',
            'Origin'  => 'https://www.qantas.com',
            'Referer' => 'https://www.qantas.com/',
        ];

        $this->initCurl();

        if (!$dataOrigin || !isset($dataOrigin->airports)) {
            $this->logger->notice('Check qantas origin');
            $this->curl->RetryCount = 0;
            $this->curl->removeCookies();
            $this->curl->GetURL("https://api.qantas.com/flight/routesearch/v1/airports?queryFrom={$fields['DepCode']}",
                $headers, 20);

            if ($this->isBadProxy($this->curl)) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $data = $this->curl->JsonLog(null, 0);

            if (isset($data->airports)) {
                \Cache::getInstance()->set('ra_qantas_origin_' . $fields['DepCode'], $data, 60 * 60 * 24);
            }
        } else {
            $data = $dataOrigin;
        }

        if (isset($data->airports) && is_array($data->airports)) {
            if (count($data->airports) === 0) {
                $this->SetWarning("This origin is not available.");

                $this->noFligths = true;
            }

            foreach ($data->airports as $airport) {
                if ($airport->code === $fields['DepCode']) {
                    $depDetail = $airport;

                    break;
                }
            }

            if (!isset($depDetail)) {
                $this->SetWarning("This origin is not available.");

                $this->noFligths = true;
            }
        }

        $dataRoute = \Cache::getInstance()->get('ra_qantas_route_' . $fields['DepCode'] . '_' . $fields['ArrCode']);

        if (!$dataRoute || !isset($dataRoute->airports)) {
            $this->logger->notice('Check qantas route');
            $this->curl->RetryCount = 0;
            $this->curl->removeCookies();
            $this->curl->GetURL("https://api.qantas.com/flight/routesearch/v1/airports/{$fields['DepCode']}?queryTo={$fields['ArrCode']}",
                $headers, 20);
            $data = $this->curl->JsonLog(null, 0);

            if (isset($data->airports)) {
                \Cache::getInstance()->set('ra_qantas_route_' . $fields['DepCode'] . '_' . $fields['ArrCode'], $data,
                    60 * 60 * 24);
            }
        } else {
            $data = $dataRoute;
        }

        if (isset($data->airports) && is_array($data->airports)) {
            if (count($data->airports) === 0) {
                $this->SetWarning("This destination is not available from the selected origin.");

                $this->noFligths = true;
            }

            foreach ($data->airports as $airport) {
                if ($airport->code === $fields['ArrCode']) {
                    $arrDetail = $airport;

                    break;
                }
            }

            if (!isset($arrDetail)) {
                $this->SetWarning("This destination is not available from the selected origin.");

                $this->noFligths = true;
            }
        }
        $this->depDetail = $depDetail;
        $this->arrDetail = $arrDetail;

        $this->logger->debug(var_export($depDetail, true));

        if (isset($depDetail, $arrDetail) && $this->routeWithNewDesign($depDetail, $arrDetail)) {
            $this->seleniumAuth = false;
            $this->routeNeedAuth = false;
        }
    }

    private function selenium($isCookies = false): void
    {
        $this->logger->notice(__METHOD__);

        if ($isCookies) {
            $allCookies = array_merge($this->http->GetCookies(".qantas.com"),
                $this->http->GetCookies(".qantas.com", "/", true));
        }

        /** @var Parser $selenium */
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            switch (random_int(0, 4)) {
                case 0:
                    $selenium->useFirefoxPlaywright();

                    break;

                case 1:
                    $selenium->useFirefox();

                    break;

                case 2:
                    $selenium->useChromePuppeteer();

                    break;

                case 3:
                    $selenium->useGoogleChrome();

                    break;

                default:
                    $selenium->useChromium();

                    break;
            }
            $selenium->disableImages();
            $selenium->useCache();

            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($chosenResolution);
            $selenium->disableImages();

            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            if ($isCookies) {
                $randomStr = str_split("qwrawassfdss");
                shuffle($randomStr);
                $selenium->http->GetURL("https://www.qantas.com/" . implode('', $randomStr));
                $this->savePageToLogs($selenium);

                if ($this->http->FindPreg('/(?:page isn’t working|There is no Internet connection|This site can’t be reached|Access denied)/ims')) {
                    throw new \CheckRetryNeededException(5, 0);
                }

                foreach ($allCookies as $key => $value) {
                    $selenium->driver->manage()->addCookie([
                        'name'   => $key,
                        'value'  => $value,
                        'domain' => ".qantas.com",
                    ]);
                }
            }

            $selenium->http->GetURL("https://www.qantas.com/au/en.html");
            // save page to logs
            $selenium->http->SaveResponse();

            $selenium->http->GetURL("https://www.qantas.com/au/en/book-a-trip/flights.html");
            sleep(13);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }

            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } catch (\ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        }// catch (ScriptTimeoutException $e)
        catch (\UnknownServerException | \SessionNotCreatedException | \WebDriverCurlException
        | \WebDriverException | \Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // statistic
            $memStatBrowsers = \Cache::getInstance()->get('ra_qantas_stBrowsers');

            if (!is_array($memStatBrowsers)) {
                $memStatBrowsers = [];
            }
            $browserInfo = $selenium->http->driver->getBrowserInfo();

            if ($browserInfo) {
                $key = $this->getKeyConfig($browserInfo);

                if (!isset($memStatBrowsers[$key])) {
                    $memStatBrowsers[$key] = ['success' => 0, 'failed' => 0];
                }
                $this->memStatBrowsers = $memStatBrowsers;
                $this->keyBrowser = $key;
            } else {
                $retry = true;
            }
            // close Selenium browser
            $this->logger->debug("close Selenium browser");
            $selenium->http->cleanup();
            // retries
            if (isset($retry) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new \CheckRetryNeededException(3, 0);
            }
        }
    }

    private function getKeyConfig(array $info)
    {
        return $info[\SeleniumStarter::CONTEXT_BROWSER_FAMILY] . '-' . $info[\SeleniumStarter::CONTEXT_BROWSER_VERSION];
    }

    private function getCabinFields($onlyKeys = true): array
    {
        $cabins = [
            'economy'        => 'Economy',
            'premiumEconomy' => 'Premium economy',
            'firstClass'     => 'First',
            'business'       => 'Business',
        ];

        if ($onlyKeys) {
            return array_keys($cabins);
        }

        return $cabins;
    }

    private function parseRewardFlights($fields, $isNew = false, $data = null): array
    {
        $routes = [];
        $cabinArray = $this->getCabinFields(false);

        if ($isNew && $data) {
            $jsonData = $this->http->JsonLog($data, 0, true);
        } else {
            $TAB_ID = $this->http->FindPreg("/TAB_ID=(.+)/", false, $this->http->currentUrl());
            $json = $this->http->FindSingleNode("//script[contains(.,'var theRFCOForm')]", null, false,
                "/theRFCOForm = new RFCOForm\((\{.+\})\);\s*(?:$|\/\/)/iu");
            $jsonData = $this->http->JsonLog($json, 0, true);
            $requestID = (int) ($this->http->FindPreg("/this.requestId = (\d+);/") ?? 100);
            $TAB_ID = $TAB_ID ?? $jsonData['tabSessionId'];
        }

        if ($isNew) {
            if (isset($jsonData['modelInput']['availability']['bounds'][0])) {
                $availability = $jsonData['modelInput']['availability'];
            }
        } else {
            if (isset($jsonData['availability']['bounds'][0])) {
                $availability = $jsonData['availability'];
            }
        }

        if (!isset($availability)) {
            $this->logger->error('other format json');

            throw new \CheckException('other format json', ACCOUNT_ENGINE_ERROR);
        }

        $bounds = $availability['bounds'][0];
        $fareFamilies = $availability['listFareFamily']['fareFamilies'];

        if (empty($bounds['listItineraries'])) {
            $dateStr = date("d M", $fields['DepDate']);
            $this->SetWarning("Trip from {$fields['DepCode']} to {$fields['ArrCode']} is not available on the {$dateStr}.");

            return [];
        }

        $currency = $availability['currency']['code'];

        if ($isNew) {
            $itineraries = $bounds['listItineraries']['itineraries'];
        } else {
            $itineraries = $bounds['listItineraries']['itinerariesAsMap'];
        }

        if ($isNew) {
            $sortedFlights = $this->sortFlights($bounds['flights']);
        }

        foreach ($itineraries as $id => $itinerary) {
            if ($isNew) {
                if (!isset($sortedFlights[$itinerary['itemId']]) || isset($itinerary['fakeItinerary'])) {
                    $this->sendNotification("check itineraries vs flights or fakeIt //ZM");
                    $this->logger->notice("skip itinerary");

                    continue;
                }
                $flights = $sortedFlights[$itinerary['itemId']];
            } else {
                if (!isset($bounds['flights'][$id]) || isset($itinerary['fakeItinerary'])) {
                    $this->sendNotification("check itinerariesAsMap vs flights or fakeIt //ZM");
                    $this->logger->notice("skip itinerary");

                    continue;
                }
                $flights = $bounds['flights'][$id];
            }
            $this->logger->debug('itinerary #' . $id . ($isNew ? "[" . $itinerary['itemId'] . "]" : ''));
            $segments = [];
            $layover = null;
            $totalFlight = null;
            // заглушка:
            $urlReferer = 'https://book.qantas.com/';

            if (!$isNew) {
                $urlReferer = $this->http->currentUrl();
            }

            foreach ($itinerary['segments'] as $s) {
                // first value - classic search, second - new format
                $flightNum = $s['flightFullName'] ?? ($s['airline']['code'] . $s['flightNumber']);

                if ($flightNum === 'RJ344') {
                    $this->logger->error('skip this route: it has flight RJ344 (go back in time)');

                    continue 2;
                }
                $airline = $s['cachedAirlineCode'] ?? $s['airline']['code'];

                if (($pos = strpos($airline, '_')) !== false) {
                    $airline = substr($airline, 0, $pos);
                }
                $seg = [
                    'id'        => $s['id'],
                    'departure' => [
                        'date'     => date('Y-m-d H:i', (int) ($s['beginDate'] / 1000)),
                        'dateTime' => (int) ($s['beginDate'] / 1000),
                        'airport'  => $this->http->FindPreg('/^([A-Z]{3})/', false, $s['beginLocationCode']),
                        'terminal' => $this->http->FindPreg('/^(?:[A-Z]{3})(.*)/', false, $s['beginLocationCode']),
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', (int) ($s['endDate'] / 1000)),
                        'dateTime' => (int) ($s['endDate'] / 1000),
                        'airport'  => $this->http->FindPreg('/^([A-Z]{3})/', false, $s['endLocationCode']),
                        'terminal' => $this->http->FindPreg('/^(?:[A-Z]{3})(.*)/', false, $s['endLocationCode']),
                    ],
                    'num_stops' => $s['nbrOfStops'],
                    'cabin'     => null,
                    'flight'    => [$flightNum],
                    'airline'   => $airline,
                    'distance'  => null,
                    'times'     => ['flight' => null, 'layover' => null],
                ];

                if ($isNew) {
                    if (isset($bounds['listItineraries']['airlines'][$s['cachedOperatingCarrierCode']])) {
                        $seg['operator'] = $bounds['listItineraries']['airlines'][$s['cachedOperatingCarrierCode']]['capitalizedName'];
                    }
                }
                $segments[] = $seg;
            }
            $headers = [
                'Accept'           => 'application/json, text/javascript, */*; q=0.01',
                'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer'          => $urlReferer,
            ];

            foreach ($flights['listRecommendation'] as $cabinCode => $item) {
                $this->logger->emergency($cabinCode);
                $this->logger->emergency('tax' . $item['taxForOne']);
                $headData = [
                    'distance'  => null,
                    'num_stops' => $itinerary['nbrOfStops'],
                    'times'     => [
                        'flight'  => $totalFlight,
                        'layover' => $itinerary['nbrOfStops'] == 0 ? null : $layover,
                    ],
                    'redemptions' => [
                        'miles'   => null,
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $currency,
                        'taxes'    => $isNew ? null : $item['taxForOne'], // 'taxForAll'
                        'fees'     => null,
                    ],
                ];

                $routeCabins = [];

                foreach ($item['mixedCabins'] as $mixedCabin) {
                    $routeCabins[$mixedCabin['segmentId']] = $mixedCabin['realCabinName'];
                }

                if ($isNew) {
                    if (isset($this->useMarginalPointsPrice)) {
                        $showTax = $this->useMarginalPointsPrice;
                    } else {
                        $showTax = $item['priceForOne']['surcharges'] !== 0;
                    }

                    if (!$showTax) {
                        $headData['redemptions']['miles'] = $item['priceForOne']['convertedCashMiles'];
                    } else {
                        $headData['redemptions']['miles'] = $item['priceForOne']['convertedBaseFare'];
                        $headData['payments']['taxes'] = $item['priceForOne']['tax'];
                    }
                } else {
                    $requestID++;
                    $AIRLINE_CODES = urlencode(implode(',', array_map(function ($s) {
                        return substr($s['cachedAirlineCode'], 0, 2);
                    }, $itinerary['segments'])));
                    $OPERATING_CARRIER_CODES = urlencode(implode(',', array_map(function ($s) {
                        return stripos($s['cachedOperatingCarrierCode'],
                            'null') !== false ? substr($s['cachedAirlineCode'],
                            0, 2) : substr($s['cachedOperatingCarrierCode'], 0, 2);
                    }, $itinerary['segments'])));

                    $CLASSES = urlencode(implode(',', $item['rbds']));

                    $DATES = urlencode(implode(',', array_map(function ($s) {
                        return date("dmYHi", $s['departure']['dateTime']) . '/' . date("dmYHi",
                                $s['arrival']['dateTime']);
                    }, $segments)));

                    $FLIGHT_NUMBERS = urlencode(implode(',', array_map(function ($s) {
                        return $s['flightNumber'];
                    }, $itinerary['segments'])));

                    $SECTORS = urlencode(implode(',', array_map(function ($s) {
                        return $s['departure']['airport'] . '/' . $s['arrival']['airport'];
                    }, $segments)));
                    $SEGMENT_ID = urlencode(implode(',', array_keys($segments)));
                    $NB_DAYS_BETWEEN_DEP_ARR = urlencode(implode(',', array_map(function ($s) {
                        return $s['nbDaysBetweenItiDepAndSegArr'];
                    }, $itinerary['segments'])));
                    $postData = "PAGE_CODE=RFCO&LANGUAGE=GB&SITE=QFQFQFFA&TAB_ID={$TAB_ID}&REQUEST_ID={$requestID}&AIRLINE_CODES={$AIRLINE_CODES}&OPERATING_CARRIER_CODES={$OPERATING_CARRIER_CODES}&CLASSES={$CLASSES}&DATES={$DATES}&FLIGHT_NUMBERS={$FLIGHT_NUMBERS}&SECTORS={$SECTORS}&SEGMENT_ID={$SEGMENT_ID}&NB_DAYS_BETWEEN_DEP_ARR={$NB_DAYS_BETWEEN_DEP_ARR}&CONTAINS_CLASSIC_REWARDS=true&NB_BOUNDS_IN_REQUEST=1";
                    $memMaxRedirects = $this->curl->getMaxRedirects();
                    $this->curl->RetryCount = 0;
                    $this->curl->setMaxRedirects(0);
                    $this->setCookieFromSelenium($this->curl);
                    $this->curl->PostURL("https://book.qantas.com/pl/QFAward/wds/RetrieveLoyaltyPointsInfoServlet",
                        $postData, $headers);

                    if ($this->isBadProxy($this->curl)) {
                        $this->sendNotification('proxy changed // ZM');

                        if (!isset($proxyChanged)) {
                            $this->setProxyGoProxies(null, 'gb');
                            sleep(1);
                            $this->curl->PostURL("https://book.qantas.com/pl/QFAward/wds/RetrieveLoyaltyPointsInfoServlet",
                                $postData, $headers);
                            $proxyChanged = true;
                        } else {
                            break 2; // вернем, что собрали
                        }
                    }

                    if ($this->curl->Response['code'] == 500) {
                        sleep(2);
                        $this->curl->PostURL("https://book.qantas.com/pl/QFAward/wds/RetrieveLoyaltyPointsInfoServlet",
                            $postData, $headers);

                        if ($this->curl->Response['code'] != 200) {
                            $skip500 = true;

                            continue;
                        }
                        $skip500Helped = true;
                    }

                    if ($this->curl->Response['code'] != 200
                        || $this->curl->FindPreg('/^\s*{"model":{"pageCode":"RPTS","requestId":"\d+","localizedMessages":{}}}\s*$/')
                    ) { // retry helped
                        sleep(2);
                        $this->curl->setMaxRedirects($memMaxRedirects);
                        $this->curl->PostURL("https://book.qantas.com/pl/QFAward/wds/RetrieveLoyaltyPointsInfoServlet",
                            $postData, $headers);
                    }
                    $this->curl->RetryCount = 2;

                    if ($this->curl->FindPreg('/^\s*{"model":{"pageCode":"RPTS","requestId":"\d+","localizedMessages":{}}}\s*$/')) {
                        $this->logger->warning('can\'t get miles. skip ' . $cabinCode);

                        continue;
                    }

                    if ($this->curl->Response['code'] == 403) {
                        continue;
                    }

                    $paymentData = $this->curl->JsonLog(null, 0, true);

                    if (isset($skip500)) {
                        $hasPaymentsAfter500 = true;
                    }

                    if (isset($paymentData['model']['pageCode'])
                        && $paymentData['model']['pageCode'] === 'RPTS'
                        && $this->curl->FindPreg("/THE ITINERARY CONTAINS MORE THAN ONE DEPARTURE FROM THE FIRST DEPARTURE CITY\/COUNTRY/")
                    ) {
                        $this->logger->error('skip MORE THAN ONE DEPARTURE: ' . $cabinCode);
                        $this->logger->error('THE ITINERARY CONTAINS MORE THAN ONE DEPARTURE FROM THE FIRST DEPARTURE CITY/COUNTRY');
                        $skipMoreThanOne = true;

                        continue;
                    }

                    if (isset($paymentData['model']['pageCode'])
                        && $paymentData['model']['pageCode'] === 'GERR'
                        && $this->curl->FindPreg("/We are having trouble processing your booking. Please try again or contact us if the problem persists/")
                    ) {
                        $this->logger->error('skip Recommendation: ' . $cabinCode);
                        $this->logger->error('We are having trouble processing your booking. Please try again or contact us if the problem persists');
                        $skipRecommendation = true;

                        continue;
                    }

                    if (isset($paymentData['model']['bound']['trip'][0])) {
                        $headData['redemptions']['miles'] = $paymentData['model']['quote']['costWithoutDiscount'];
                    } else {
                        if ($this->requestDateTime < 70) {
                            throw new \CheckRetryNeededException(5, 0);
                        }

                        if (isset($skipNoMiles)) {
                            if (count($routes) > 0) {
                                return $routes;
                            }

                            if (!isset($this->checkNewFormat)) {
                                $this->checkNewFormat = true;

                                return [];
                            }
                            $this->sendNotification("can't get miles // ZM");

                            throw new \CheckException("can't get miles", ACCOUNT_ENGINE_ERROR);
                        }
                        // after skip can parse other - ok
                        $skipNoMiles = true;
                        $this->logger->warning('skip route (can\'t get miles)');

                        continue;
                    }
                }
                $updSegments = $segments;

                foreach ($segments as $i => $segment) {
                    if (array_key_exists($segment['id'], $routeCabins)) {
                        foreach ($cabinArray as $k => $v) {
                            if (strcasecmp($routeCabins[$segment['id']], $v) == 0) {
                                $updSegments[$i]['cabin'] = $k;

                                break;
                            }
                        }
                    } else {
                        if ($isNew && isset($availability['fareFamiliesMinirules']['minirules'][$cabinCode]['fullTextRules'])) {
                            $updSegments[$i]['cabin'] = $this->getCabinFromData($availability['fareFamiliesMinirules']['minirules'][$cabinCode],
                                '');
                        }

                        if (!$updSegments[$i]['cabin'] && $isNew && isset($availability['listFareFamily']['fareFamilies'][$cabinCode])) {
                            $updSegments[$i]['cabin'] = $this->getCabinFromData([],
                                $availability['listFareFamily']['fareFamilies'][$cabinCode]['name']);
                        }
                        // MB: $availability['ffCodeToAssociatedCabinForCabinCrossSell'][$cabinCode] - checked - не подходит для детекта кэбина

                        if (!$updSegments[$i]['cabin']) {
                            foreach ($cabinArray as $k => $v) {
//                            if (isset($fareFamilies[$item['ffCode']]) && strcasecmp($fareFamilies[$item['ffCode']]['name'],
                                if (isset($fareFamilies[$cabinCode]) && strcasecmp($fareFamilies[$cabinCode]['name'],
                                        $v) == 0
                                ) {
                                    $updSegments[$i]['cabin'] = $k;

                                    break;
                                }
                            }
                        }
                    }

                    if (!$updSegments[$i]['cabin']) {
                        $this->sendNotification("check cabin // ZM");
                    }
                    unset($updSegments[$i]['id']);
                }
                $result = ['connections' => $updSegments];
                $res = array_merge($headData, $result);

                if ($isNew) {
                    if ($item['showLSA']) { // флаг LastSeatMessage - показать ли сообщение 'Hurry! There are 5 or fewer seats available at this price. Book now to secure your seat.'
                        $res['tickets'] = 5;
                    }

                    if (isset($availability['listFareFamily']['fareFamilies'][$cabinCode]['name'])) {
                        $res['award_type'] = $availability['listFareFamily']['fareFamilies'][$cabinCode]['name'];

                        if (preg_match("/^(Business|Premium Economy|First|Economy) (Saver|Flex|Sale|Classic Reward|Deal)$/i",
                            $res['award_type'], $m)) {
                            $res['award_type'] = $m[2];
                            $res['classOfService'] = $m[1];
                        } else {
                            $res['classOfService'] = $availability['listFareFamily']['fareFamilies'][$cabinCode]['name'];

                            if ($res['classOfService'] === 'Saver' || $res['classOfService'] === 'Flex' || $res['classOfService'] === 'Sale') { // seems Economy
                                $res['award_type'] = $res['classOfService'];
                                $res['classOfService'] = 'Economy';
                            }
                        }
                    }
                }
                $this->logger->debug("Parsed data:");
                $this->logger->debug(var_export($res, true), ['pre' => true]);
                $routes[] = $res;
            }
        }

        if (isset($skipMoreThanOne) && empty($routes)) {
            $this->SetWarning('ERROR : please check those fields; THE ITINERARY CONTAINS MORE THAN ONE DEPARTURE FROM THE FIRST DEPARTURE CITY/COUNTRY');

            return $routes;
        }

        if (isset($skipRecommendation) && empty($routes)) {
            throw new \CheckException('We are having trouble processing your booking. Please try again or contact us if the problem persists', ACCOUNT_PROVIDER_ERROR);
        }

        if (isset($hasPaymentsAfter500)) {
            $this->sendNotification('payments  after 500 // ZM');
        }

        if (isset($skip500Helped)) {
            $this->sendNotification('retry 500 helped // ZM');
        }

        if (isset($skip500) && empty($routes)) {
            $this->sendNotification('no payments (500) // ZM');

            throw new \CheckException('provider has never given payment (500)', ACCOUNT_ENGINE_ERROR);
        }

        return $routes;
    }

    private function getCabinFromData($data, $extValue): ?string
    {
        $this->logger->notice(__METHOD__);

        if (!isset($data['fullTextRules'])) {
            $data['fullTextRules'] = [];
        }

        foreach ($data['fullTextRules'] as $v) {
            if (isset($v['ruleId']) && $v['ruleId'] === 'travelClass' && isset($v['fullTextValue']) && !empty($v['fullTextValue'])) {
                $data['fareFamilyName'] = $v['fullTextValue'];

                break;
            }
        }

        if (!isset($data['fareFamilyName'])) {
            $data['fareFamilyName'] = $extValue;
        }

        if (stripos($data['fareFamilyName'], 'Premium Economy') !== false) {
            return 'premiumEconomy';
        }

        if (stripos($data['fareFamilyName'], 'Economy') !== false
            || $data['fareFamilyName'] === 'Saver'
            || $data['fareFamilyName'] === 'Flex'
        ) {
            return 'economy';
        }

        if (stripos($data['fareFamilyName'], 'Business') !== false) {
            return 'business';
        }

        if (stripos($data['fareFamilyName'], 'First') !== false) {
            return 'firstClass';
        }

        return null;
    }

    private function checkBackupToSearch($dataPrice): ?array
    {
        $this->logger->notice(__METHOD__);

        if (isset($dataPrice->modelInput) && isset($dataPrice->modelInput->pageCode)
            && $dataPrice->modelInput->pageCode === 'BackupToSearch'
            && isset($dataPrice->modelInput->form)
            && strpos($dataPrice->modelInput->form->action,
                'www.qantas.com/tripflowapp/bookingError.tripflow') !== false
        ) {
            $headers = [
                'Origin'       => 'https://book.qantas.com',
                'Content-type' => 'application/x-www-form-urlencoded',
                'Referer'      => 'https://book.qantas.com/',
            ];
            $payload = 'WDS_SESSION_LESS=TRUE';
            $checkCnt = 1;

            foreach ($dataPrice->modelInput->form->parameters as $key => $value) {
                if (is_array($value) && count($value) === 1 && $key !== 'WDS_SESSION_LESS') {
                    $payload .= "&" . $key . '=' . $value[0];
                    $checkCnt++;
                }
            }

            if ($checkCnt === 8) {
                $url = preg_replace("/.*\/\//", "https://", trim($dataPrice->modelInput->form->action));
                $memRedirects = $this->http->getMaxRedirects();
                $this->http->setMaxRedirects(0);
                $this->http->PostURL($url, $payload, $headers);
                $this->http->setMaxRedirects($memRedirects);

                if (isset($this->http->Response['headers']['location'])
                    && ($code = $this->http->FindPreg("#book-a-trip/flights.html\?errorCodes=(\d+)$#", false,
                        $this->http->Response['headers']['location']))) {
                    $this->http->GetURL($this->http->Response['headers']['location']);

                    switch ($code) {
                        case '7190':
                            $this->SetWarning("We're having trouble finding flight options that match your search. Try another date or refer to our flight network page for a list of current destinations");

                            break;

                        case '9200':
                            $this->SetWarning('We can’t find any flights for your departing journey. Try another date or refer to our flight network page for a list of current destinations.');

                            break;

                        case '66002':
                            $this->SetWarning('We couldn\'t find any flights for the dates you entered. Try changing the dates and search again.');

                            break;

                        case '7130':
                            $this->SetWarning('We couldn\'t find any flights for your request. Please search again later.');

                            break;

                        default:
                            $this->SetWarning('We couldn\'t find any flights for the dates you entered. Try changing the dates and search again.');
                            $this->sendNotification("check code // ZM");
                    }

                    return [];
                }
            }
        }

        return null;
    }

    private function sortFlights(array $data): array
    {
        $res = [];

        foreach ($data as $item) {
            $res[$item['flightId']] = $item;
        }

        if (empty($res)) {
            return $data;
        }

        return $res;
    }

    private function routeWithNewDesign($dep, $arr)
    {
        return
            (($dep->country->code === 'AU' || $arr->country->code !== 'AU')
                && ($arr->isCommercialOnly === false)
                && in_array($dep->country->code, $this->singleEngineCountries()))
            || (($arr->country->code === 'AU' || $dep->country->code !== 'AU')
                && ($dep->isCommercialOnly === false)
                && in_array($arr->country->code, $this->singleEngineCountries()));
    }

    private function singleEngineCountries(): array
    {
        return [
            "AU",
            "NZ",
            "FJ",
            "PF",
            "NC",
            "VU",
            "CA",
            "MX",
            "KR",
            "HK",
            "SG",
            "TH",
            "CN",
            "JP",
            "GB",
            "FR",
            "DE",
            "NL",
            "IE",
            "ZA",
            "IN",
            "ID",
            "MY",
            "PG",
            "TW",
            "VN",
            "AT",
            "BE",
            "DK",
            "AE",
            "FI",
            "IL",
            "IT",
            "NO",
            "ES",
            "CH",
            "SE",
            "PH",
            "US",
            "TR",
            "CL",
        ];
    }

    private function initCurl()
    {
        $this->logger->notice(__METHOD__);

        $this->curl = new \HttpBrowser("none", new \CurlDriver());
        $this->http->brotherBrowser($this->curl);

        $this->curl->LogHeaders = true;
        $this->curl->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->curl->setHttp2(true);

        $this->curl->SetProxy("{$this->http->getProxyAddress()}:{$this->http->getProxyPort()}");
        $this->curl->setProxyAuth($this->http->getProxyLogin(), $this->http->getProxyPassword());
        $this->curl->setUserAgent($this->http->getDefaultHeader("User-Agent"));
    }

    private function setCookieFromSelenium($http)
    {
        $this->logger->notice(__METHOD__);

        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                $cookie['expiry'] ?? null);
        }
    }

    private function newFormatWithoutAuth($fields, $depDetail, $arrDetail, $travelDates)
    {
        $this->logger->notice(__METHOD__);

        $headers = [
            'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Content-Type'              => 'application/x-www-form-urlencoded',
            'Origin'                    => 'https://www.qantas.com',
            'Referer'                   => 'https://www.qantas.com/',
            'Upgrade-Insecure-Requests' => 1,
        ];
        $payload = [
            "depAirports"         => $fields['DepCode'],
            "destAirports"        => $fields['ArrCode'],
            "travelDates"         => $travelDates,
            "numberOfAdults"      => $fields['Adults'],
            "numberOfYoungAdults" => 0,
            "numberOfChildren"    => 0,
            "numberOfInfants"     => 0,
            "travelClass"         => "ECO",
            "searchOption"        => "M",
            //            "FF_MEMBER_ID"        => $this->AccountFields['Login'],
            //            "FF_TOKEN"            => $this->State["token"],
            "QFdeviceType"   => "desktop",
            "PAGE_FROM"      => "/bookingError/v1/redirect/us/en/book-a-trip/flights.html",
            "USER_LANG"      => "EN",
            "USER_LOCALE"    => "EN_US",
            "int_cam"        => "au:bookflight:top:newfsw:en:flights",
            "isClassicOnly"  => "TRUE",
            "isViewInPoints" => "TRUE",
        ];

        $this->http->PostURL("https://book.qantas.com/qf-booking/dyn/air/tripflow.redirect", $payload, $headers);

        if ($this->http->Response['code'] == 403 || empty($this->http->Response['body'])) {
            $this->memStatBrowsers[$this->keyBrowser]['failed']++;
            \Cache::getInstance()->set('ra_qantas_stBrowsers', $this->memStatBrowsers, 60 * 60 * 24);
            $this->logger->warning(var_export($this->memStatBrowsers, true), ['pre' => true]);

            $this->selenium(true);

            $this->http->PostURL("https://book.qantas.com/qf-booking/dyn/air/tripflow.redirect", $payload, $headers);
        }

        if ($this->isBadProxy()) {
            $this->memStatBrowsers[$this->keyBrowser]['failed']++;
            \Cache::getInstance()->set('ra_qantas_stBrowsers', $this->memStatBrowsers, 60 * 60 * 24);
            $this->logger->warning(var_export($this->memStatBrowsers, true), ['pre' => true]);

            $this->logger->error("request has been broken");

            throw new \CheckRetryNeededException(5, 0);
        }
        $this->memStatBrowsers[$this->keyBrowser]['success']++;
        \Cache::getInstance()->set('ra_qantas_stBrowsers', $this->memStatBrowsers, 60 * 60 * 24);
        $this->logger->warning(var_export($this->memStatBrowsers, true), ['pre' => true]);

        $TAB_ID = $this->http->FindPreg("/action=\"https:\/\/book\.qantas\.com\/qf-booking\/dyn\/air\/booking\/preAvailabilityActionFromLoad\?TAB_ID=([^\"]+)\"/");

        $headers = [
            'Accept'           => 'application/json',
            'Origin'           => 'https://book.qantas.com',
            'Content-type'     => 'application/x-www-form-urlencoded',
            'Referer'          => 'https://book.qantas.com/qf-booking/dyn/air/booking/FFCO?SITE=QFQFQFBW&LANGUAGE=GB&TAB_ID=' . $TAB_ID,
            'sec-ch-ua-mobile' => '?0',
            'sec-fetch-dest'   => 'empty',
            'sec-fetch-mode'   => 'cors',
            'sec-fetch-site'   => 'same-origin',
        ];

        $payload = [
            "WDS_DMIN_FILTER"             => "UPSELL_INT",
            "WDS_BUILD_DMIN_FROM_SESSION" => "TRUE",
            "WDS_OLD_TAB_ID"              => "",
            "SITE"                        => "QFQFQFBW",
            "LANGUAGE"                    => "GB",
            "SKIN"                        => "P",
            "TAB_ID"                      => $TAB_ID,
        ];

        $this->http->PostURL("https://book.qantas.com/qf-booking/dyn/air/booking/flexPricerAvailabilityActionFromLoad",
            $payload, $headers);

        $dataPrice = $this->http->JsonLog();

        if (is_array($this->checkBackupToSearch($dataPrice))) {
            return [];
        }

        return $this->parseRewardFlights($fields, true, $this->http->Response['body']);
    }
}
