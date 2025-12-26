<?php

namespace AwardWallet\Engine\rapidrewards\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use Facebook\WebDriver\Exception\WebDriverException;

class Parser extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    private const CONFIGS = [
        'chromium-80' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROMIUM,
            'browser-version' => \SeleniumFinderRequest::CHROMIUM_80,
        ],
        'chrome-84' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_84,
        ],
        'chrome-95' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_95,
        ],
        'chrome-99' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_99,
        ],
        'chrome-100' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_100,
        ],
        //        'firefox-59' => [
        //            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
        //            'browser-version' => \SeleniumFinderRequest::FIREFOX_59,
        //        ],
        'firefox-84' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_84,
        ],
        'firefox-playwright-101' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_101,
        ],
        'chrome-94-mac' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_94,
        ],
        'firefox-100' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_100,
        ],
    ];
    public $isRewardAvailability = true;

    private $config;
    private $newSession;

    public static function getRASearchLinks(): array
    {
        return ['https://www.southwest.com/air/booking/' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = false;

//        $array = ['ca', 'in', 'us', 'uk', 'fr', 'es']; // , 'be', 'au'
        $array = ['fr', 'uk']; // , 'be', 'au'
        $targeting = $array[array_rand($array)];

        if ($this->AccountFields['ParseMode'] === 'awardwallet') {
            if ($targeting === 'us') {
                $this->setProxyMount();
            } else {
                $this->setProxyGoProxies(null, $targeting);
            }
        } else {
            if (in_array($this->attempt, [0, 2])) {
                $this->setProxyDOP();
            } else {
                $this->setProxyGoProxies(null, $targeting);
            }
        }

        $this->setConfig();

        $this->http->setRandomUserAgent(10);
    }

    public function IsLoggedIn()
    {
        return true;
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function getRewardAvailabilitySettings()
    {
        $arrCurrencies = ['USD'];

        return [
            'supportedCurrencies'      => $arrCurrencies,
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'USD',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        if ($fields['Currencies'][0] !== 'USD') {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if ($fields['Adults'] > 8) {
            $this->SetWarning('The total number of passengers cannot exceed 8.');

            return ['routes' => []];
        }
        $lateDate = \Cache::getInstance()->get('ra_rapidrewards_late_date');

        if (empty($lateDate)) {
            $lateDate = strtotime("+ 235 days");
        } else {
            $this->logger->warning('late date: ' . date('Y-m-d', $lateDate));
        }

        if ($fields['DepDate'] > $lateDate) {
            $this->SetWarning("to late flight");

            return ['routes' => []];
        }
        $checkDate = time() - $fields['DepDate'];

        if ($checkDate > 0 && $checkDate < 60 * 60 * 2) {
            $this->SetWarning("Date must be in the future");

            return ['routes' => []];
        }

        $airports = $this->getAirports();

        if (!empty($airports) && !in_array($fields['DepCode'], $airports)) {
            $this->SetWarning('Unfortunately, no airports or origins match what you\'ve entered. - ' . $fields['DepCode']);

            return ['routes' => []];
        }

        if (!empty($airports) && !in_array($fields['ArrCode'], $airports)) {
            $this->SetWarning('Unfortunately, no airports or destinations match what you\'ve entered. - ' . $fields['ArrCode']);

            return ['routes' => []];
        }

        try {
            $data = $this->selenium($fields);
        } catch (\WebDriverCurlException | \WebDriverException | WebDriverException $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());
            $this->markConfigAsBad(); // иначе зацикливаться может, если браузер глюканул

            if (time() - $this->requestDateTime < 60) {
                throw new \CheckRetryNeededException(5, 0);
            }

            throw new \CheckException('WebDriverCurlException', ACCOUNT_ENGINE_ERROR);
        }

        if (is_array($data)) {
            return [];
        }

        if (!$data
            || strpos($data, 'An error occurred while processing your request') !== false
            || strpos($data, 'The server is temporarily unable to service your request') !== false
        ) {
            $msg = $this->http->FindSingleNode("//div[starts-with(normalize-space(),'Flights are not currently available for the date/time requested. Some flights may only operate on specific days of the week.')]");

            if ($msg) {
                $this->SetWarning($msg);

                return ['routes' => []];
            }
            $errors = $this->http->FindNodes("//li[contains(@class,'page-error--message') or contains(@class,'error-message--item')]");

            foreach ($errors as $error) {
                $this->logger->error($error);

                if (strpos($error, 'This is either because service is paused for that destination') !== false
                    || strpos($error, 'We are unable to process your request. Please try again.') !== false
                ) {
                    $warning = $error;
                }
            }

            if (count($errors) === 1
                && strpos($warning, 'We are unable to process your request. Please try again.') !== false
            ) {
                // it's block
                $this->markConfigAsBad();

                throw new \CheckRetryNeededException(5, 0);
            }

            if (isset($warning)) {
                $this->SetWarning($warning);

                return ['routes' => []];
            }

            throw new \CheckException("can\'t get data", ACCOUNT_ENGINE_ERROR);
        }

        if (strpos($data, '<head><title>502 Bad Gateway</title></head>') !== false) {
            throw new \CheckRetryNeededException(3, 0);
        }
        $data = $this->http->JsonLog($data, 1, true);

        if (!$data
            && strpos($this->http->Response['body'], 'We are unable to process your request. Please try again') !== false
        ) {
            $this->logger->error('We are unable to process your request. Please try again');

            throw new \CheckRetryNeededException(3, 0);
        }

        if (isset($data['notifications']['formErrors'][0]['code'])
            && $data['notifications']['formErrors'][0]['code'] === 'ERROR__NO_FLIGHTS_AVAILABLE'
        ) {
            $this->SetWarning('Wanna look for another day? We don’t have any flights from '
                . $fields['DepCode'] . ' to '
                . $fields['ArrCode'] . ' on '
                . date('m/d/Y', $fields['DepDate']));

            return [];
        }

        if (isset($data['notifications']['formErrors'][0]['code'])
            && $data['notifications']['formErrors'][0]['code'] === 'ERROR__NO_FARE_FOUND'
        ) {
            $this->SetWarning('Flights are not currently available for the date/time requested. Some flights may only operate on specific days of the week. Visit our Low Fare Calendar for our current schedule and lowest fares.');

            return [];
        }

        if (isset($data['notifications']['formErrors'][0]['code'])
            && $data['notifications']['formErrors'][0]['code'] === 'ERROR__NO_FLIGHT_SEARCH_RESULTS'
        ) {
            $this->SetWarning("We're sorry, no flights are available that match your search criteria. Please alter your search or choose a different origin/destination.");

            return [];
        }

        if (isset($data['notifications']['formErrors'][0]['code'])
            && $data['notifications']['formErrors'][0]['code'] === 'UNKNOWN_ERROR'
        ) {
            throw new \CheckRetryNeededException(3, 0);
        }

        if (isset($data['httpStatusCode'])
            && ($data['httpStatusCode'] === 'BAD_GATEWAY'
                || $data['httpStatusCode'] === 'BAD_GATEWAY')
        ) {
            $this->sendNotification("check restart BAD_GATEWAY/INTERNAL_SERVER_ERROR // ZM");

            throw new \CheckRetryNeededException(3, 0);
        }

        if (isset($data['notifications']['formErrors'][0]['code'])
            && $data['notifications']['formErrors'][0]['code'] === 'ERROR__UNKNOWN_ERROR__INVALID'
        ) {
            $this->sendNotification("check restart ERROR__UNKNOWN_ERROR__INVALID // ZM");

            throw new \CheckRetryNeededException(3, 0);
        }

        if (!isset($data['data']['searchResults']['airProducts']) && isset($data['message'])
            && $data['message'] === 'The requested service is temporarily unavailable'
        ) {
            throw new \CheckException($data['message'], ACCOUNT_PROVIDER_ERROR);
        }

        if (!isset($data['data']['searchResults']['airProducts'])) {
            $this->sendNotification("check data // ZM");

            throw new \CheckException("can\'t get data", ACCOUNT_ENGINE_ERROR);
        }
        $data = $data['data']['searchResults']['airProducts'];

        if (count($data) !== 1) {
            $this->logger->error('provider error. answer with wrong route');

            throw new \CheckRetryNeededException(5, 0);
        }
        $data = array_shift($data);

        return ['routes' => $this->parseRewardFlights($fields, $data)];
    }

    private function getAwardName(string $str): ?string
    {
        if (strlen($str) === 3) {
            $this->logger->error('get data with dollars, no miles');

            throw new \CheckRetryNeededException(5, 0);
        }
        $awards = [
            'PLURED' => 'Wanna Get Away Plus',
            'WGARED' => 'Wanna Get Away',
            'ANYRED' => 'Anytime',
            'BUSRED' => 'Business Select',
        ];

        if (!isset($awards[$str])) {
            $this->sendNotification("new award type: {$str} // ZM");

            return null;
        }

        return $awards[$str];
    }

    private function parseRewardFlights($fields, $data): array
    {
        $this->logger->notice(__METHOD__);
        $dateStr = date("Ymd", $fields['DepDate']);
        $this->logger->info("ParseReward [" . $dateStr . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);
        $routes = [];
        $this->logger->debug("Found " . count($data['details']) . " routes");

        foreach ($data['details'] as $numRoute => $detail) {
            $this->logger->debug("numRoute $numRoute");
            $segments = [];
            $stops = -1;

            foreach ($detail['segments'] as $numSegment => $segment) {
                $this->logger->debug("numSegment $numSegment");

                foreach ($segment['stopsDetails'] as $numSegDetail => $stopDetail) {
                    $this->logger->debug("numSegDetail $numSegDetail");
                    $seg = [
                        'num_stops' => 0,
                        'times'     => [
                            'flight'  => null,
                            'layover' => null,
                        ],
                        'departure' => [
                            'date'     => substr($stopDetail['departureDateTime'], 0, 19),
                            'dateTime' => strtotime(substr($stopDetail['departureDateTime'], 0, 19)),
                            'airport'  => $stopDetail['originationAirportCode'],
                        ],
                        'arrival' => [
                            'date'     => substr($stopDetail['arrivalDateTime'], 0, 19),
                            'dateTime' => strtotime(substr($stopDetail['arrivalDateTime'], 0, 19)),
                            'airport'  => $stopDetail['destinationAirportCode'],
                        ],
                        'flight'   => [$segment['marketingCarrierCode'] . $stopDetail['flightNumber']],
                        'airline'  => $segment['marketingCarrierCode'],
                        'operator' => $segment['operatingCarrierCode'],
                        'aircraft' => $segment['aircraftEquipmentType'],
                    ];
                    $segments[] = $seg;
                }
            }

            if (!isset($detail['fareProducts'])) {
                // при неверной дате (поздно вечером)
                $this->SetWarning('Wanna look for another day? We don’t have any flights from '
                    . $fields['DepCode'] . ' to '
                    . $fields['ArrCode'] . ' on '
                    . date('m/d/Y', $fields['DepDate']));

                return [];
            }

//            $totalTime = $this->separateTime($detail['totalDuration']);

            foreach ($detail['fareProducts']['ADULT'] as $key => $value) {
                if ($value['availabilityStatus'] !== 'AVAILABLE') {
                    if ($value['availabilityStatus'] !== 'SOLD_OUT') {
                        $soldOut = true;
                    } else {
                        $unavailable = true;
                    }

                    continue;
                }
                $this->logger->debug($key);
                $segments_ = $segments;

                foreach ($segments as $num => $segment) {
                    $segments_[$num]['cabin'] = 'economy';
//                    $segments_[$num]['cabin'] = ($key === 'BUSRED') ? 'business' : 'economy'; // ref 20270#note-37
                }

                $result = [
                    'award_type'     => $this->getAwardName($key),
                    'classOfService' => $this->getAwardName($key),
                    'num_stops'      => count($segments_) - 1,
                    'times'          => [
                        'flight' => null,
                        //                        'flight' => sprintf('%02d:%02d', $totalTime[0], $totalTime[1]),
                        'layover' => null,
                    ],
                    'redemptions' => [
                        'miles'   => $value['fare']['baseFare']['value'],
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $value['fare']['totalTaxesAndFees']['currencyCode'],
                        'taxes'    => $value['fare']['totalTaxesAndFees']['value'],
                        'fees'     => null,
                    ],
                    'tickets'     => $value['fare']['seatsLeft'] ?? null,
                    'connections' => $segments_,
                ];
                $this->logger->debug(var_export($result, true), ['pre' => true]);
                $routes[] = $result;
            }
        }

        if (empty($routes)) {
            if (isset($soldOut)) {
                $this->SetWarning("Unavailable flights (all sold out)");
            } else {
                if (isset($unavailable)) {
                    $this->SetWarning("Unavailable flights");
                }
            }
        }

        return $routes;
    }

    private function selenium($fields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;

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
            $this->logger->info('chosenResolution:');
            $this->logger->info(var_export($chosenResolution, true));

            $selenium->disableImages();
            $selenium->useCache();

            if (self::CONFIGS[$this->config]['browser-family'] === \SeleniumFinderRequest::BROWSER_FIREFOX
            || self::CONFIGS[$this->config]['browser-family'] === \SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT) {
                $request = FingerprintRequest::firefox();
            } else {
                $request = FingerprintRequest::chrome();
            }
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);
            $this->usePacFile(false);

            if (isset($fingerprint)
                && self::CONFIGS[$this->config]['browser-version'] !== \SeleniumFinderRequest::CHROME_94
                && self::CONFIGS[$this->config]['browser-version'] !== \SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_101
            ) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
                $selenium->seleniumOptions->userAgent = $fingerprint->getUseragent();
            } else {
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->seleniumOptions->userAgent = null;
            }

            $selenium->seleniumRequest->request(
                self::CONFIGS[$this->config]['browser-family'],
                self::CONFIGS[$this->config]['browser-version']
            );
//            $selenium->seleniumRequest->setHotPool(self::class);
            $selenium->KeepState = false;
            $selenium->keepCookies(false); // не нужны в принципе, плюс трейсы проскакивают на сохранении, потому принудительно выключаю
            $selenium->http->saveScreenshots = true;

            try {
                $selenium->http->start();
                $selenium->Start();
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'all selenium servers are busy') !== false) {
                    $this->logger->error($e->getMessage());

                    throw new \CheckRetryNeededException(5, 0);
                }

                try {
                    $selenium->http->start();
                    $selenium->Start();
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());

                    throw new \CheckRetryNeededException(5, 0);
                }
            }

            /** @var \SeleniumDriver $seleniumDriver */
            $seleniumDriver = $selenium->http->driver;
            $this->newSession = $seleniumDriver->isNewSession();
            $selenium->driver->executeScript('return localStorage.removeItem("responseData");');

            $data = $this->getData($selenium, $fields);

            $timeout = $data['timeout'];
            $responseData = $data['responseData'];
            // if strpos($responseData, '"severity":"ERROR","code":"UNKNOWN_ERROR"') !== false  -- ретраить нет смысла, только рестарт

            if (empty($responseData)) {
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();

                $array = ['us', 'uk', 'fr', 'es']; // , 'be', 'au'
                $targeting = $array[array_rand($array)];
                $selenium->setProxyBrightData(null, 'static', $targeting);

                // retry. it's work
                $data = $this->getData($selenium, $fields, true);
                $timeout = $data['timeout'];
                $responseData = $data['responseData'];

                if (empty($responseData)) {
                    $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                    $this->http->SaveResponse();

                    $this->markConfigAsBad();

                    $selenium->keepSession(false);

                    throw new \CheckRetryNeededException(5, 0);
                }
            }

            if ($this->newSession) {
                $this->logger->info("marking config {$this->config} as successful");
                \Cache::getInstance()->set('rapidrewards_config_' . $this->config, 1, 60 * 60);
            }

            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            $selenium->keepSession(true);
        } catch (\InvalidArgumentException | \WebDriverException $e) {
            $this->logger->error('Exception: ' . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if (isset($retry) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new \CheckRetryNeededException(5, 0);
            }
        }

        if (isset($timeout) && empty($responseData ?? null)) {
            throw new \CheckRetryNeededException(5, 0);
        }

        return $responseData ?? null;
    }

    private function getData($selenium, $fields, $isRetry = false): array
    {
        $this->logger->notice(__METHOD__);

        if (random_int(0, 1)) {
            try {
                $selenium->http->GetURL("https://www.southwest.com/air/booking/");
            } catch (\TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            } catch (\WebDriverException | \Facebook\WebDriver\Exception\WebDriverException $e) {
                $selenium->keepSession(false);
                $this->markConfigAsBad();

                throw new \CheckRetryNeededException(5, 0);
            }

            $selenium->driver->executeScript('window.stop();');

            if ($selenium->waitForElement(\WebDriverBy::xpath("
                            //span[contains(text(), 'This site can’t be reached') or contains(text(), 'This page isn’t working') or contains(text(), 'This site can’t provide a secure connection')]
                            | //h1[normalize-space()='Access Denied']
                            | //h1[normalize-space()='No internet']
                        "), 0)) {
                $selenium->keepSession(false);

                throw new \CheckRetryNeededException(5, 0);
            }

            if (random_int(0, 1)) {
                $this->simulateActivityOnSite($selenium, $fields);
            }
        }
        $this->checkBlock($selenium);

        try {
            $refresh = \Cache::getInstance()->get('ra_rapidrewards_late_date_refresh');

            if (!$refresh) {
                $this->refreshLateDate($selenium, $fields);
            }

            // get Data
            $dateStr = date("Y-m-d", $fields['DepDate']);
            $url = "https://www.southwest.com/air/booking/index.html?adultPassengersCount={$fields['Adults']}&adultsCount={$fields['Adults']}&departureDate={$dateStr}&departureTimeOfDay=ALL_DAY&destinationAirportCode={$fields['ArrCode']}&fareType=POINTS&originationAirportCode={$fields['DepCode']}&passengerType=ADULT&returnDate=&returnTimeOfDay=ALL_DAY&tripType=oneway&validate=true";

            try {
                $selenium->http->GetURL($url);
            } catch (\TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            } catch (\WebDriverException | \Facebook\WebDriver\Exception\WebDriverException $e) {
                $selenium->keepSession(false);
                $this->markConfigAsBad();

                throw new \CheckRetryNeededException(5, 0);
            }

            if ($selenium->waitForElement(\WebDriverBy::xpath("(//div[normalize-space()='Enter departure airport.'])[1]"), 5)) {
                $this->SetWarning('Invalid route with departure airport');

                return ['timeout' => null, 'responseData' => ['routes' => []]];
            }

            if ($selenium->waitForElement(\WebDriverBy::xpath("(//div[normalize-space()='Enter arrival airport.'])[1]"), 0)) {
                $this->SetWarning('Invalid route with arrival airport');

                return ['timeout' => null, 'responseData' => ['routes' => []]];
            }

            if ($departure = $selenium->waitForElement(\WebDriverBy::xpath("//span[normalize-space()='Depart Date']/following-sibling::div[last()]"),
                0)) {
                if ($dateStr !== date("Y-m-d", strtotime($departure->getText()))) {
                    $this->logger->notice('wrong date checked');
                    // helped
                    if (!$isRetry) {
                        return $this->getData($selenium, $fields, true);
                    }

                    throw new \CheckRetryNeededException(5, 0);
                }
            }

            $this->checkBlock($selenium);

            $btn = $selenium->waitForElement(\WebDriverBy::xpath('//button[.//span[normalize-space()="Search"]]'), 2);

            if (!$btn) {
                $selenium->keepSession(false);

                throw new \CheckRetryNeededException(5, 0);
            }
            $this->logger->info('[run fetch]');
            $selenium->driver->executeScript(/** @lang JavaScript */
                '
                const constantMock = window.fetch;
                window.fetch = function() {
                    console.log(arguments);
                    return new Promise((resolve, reject) => {
                        constantMock.apply(this, arguments)
                            .then((response) => {
                                if(response.url.indexOf("/booking/shopping") > -1) {
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

            try {
                $btn->click();
            } catch (\UnrecognizedExceptionException | \Facebook\WebDriver\Exception\WebDriverException $e) {
                $this->logger->error('Exception: ' . $e->getMessage());

                $selenium->keepSession(false);

                throw new \CheckRetryNeededException(5, 0);
            }
            $elem = $selenium->waitForElement(\WebDriverBy::xpath('
                //h3[contains(.,"Departing flights")] 
                | //li[contains(.,"We are unable to process your request. Please try again")]
            '), 15);

            if ($elem
                && strpos($elem->getText(), 'We are unable to process your request. Please try again') !== false
            ) {
                $this->markConfigAsBad();

                throw new \CheckRetryNeededException(5, 0);
            }
            /*            if ($elem && !$isRetry
                            && strpos($elem->getText(), 'We are unable to process your request. Please try again') !== false
                        ) {
                            return $this->getData($selenium, $fields, true);
                        }*/

            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            sleep(2);
            $responseData = $selenium->driver->executeScript('return localStorage.getItem("responseData");');

            if (isset($responseData) && strlen($responseData) < 40
                && $selenium->http->FindPregAll('/^\s*{"code":\d+}\s*$/', $responseData, PREG_PATTERN_ORDER, false, false)) {
                $responseData = null; // block
            }
        } catch (\ScriptTimeoutException | \TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
            $timeout = true;
        } catch (\Facebook\WebDriver\Exception\UnknownErrorException | \Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error($e->getMessage());
            $selenium->keepSession(false);

            throw new \CheckRetryNeededException(5, 0);
        }

        return ['timeout' => $timeout ?? null, 'responseData' => $responseData ?? null];
    }

    private function refreshLateDate($selenium, $fields)
    {
        // check late Date
        $dateStr = date("Y-m-d", strtotime("+8 month"));
        $url = "https://www.southwest.com/air/booking/index.html?adultPassengersCount={$fields['Adults']}&departureDate={$dateStr}&departureTimeOfDay=ALL_DAY&destinationAirportCode={$fields['ArrCode']}&fareType=POINTS&originationAirportCode={$fields['DepCode']}&passengerType=ADULT&returnDate=&returnTimeOfDay=ALL_DAY&tripType=oneway&validate=true&int=HOMEQBOMAIR&reset=true";
        $selenium->http->GetURL($url);
        sleep(2);
        $dep = $selenium->waitForElement(\WebDriverBy::id('departureDate'), 0);

        if ($dep) {
            $dep->click();
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            $date = $this->http->FindSingleNode("//strong[contains(.,'We are currently accepting air reservations through')]", null, false, "/air reservations through\s*(\w+\s+\d{1,2}, \d{4})\./");

            if ($date) {
                $this->logger->warning($date);
                $nd = strtotime($date, false);

                if ($nd) {
                    \Cache::getInstance()->set('ra_rapidrewards_late_date_refresh', 1, 60 * 60 * 10);
                    \Cache::getInstance()->set('ra_rapidrewards_late_date', $nd, 60 * 60 * 14);
                }
            }
        }
    }

    private function simulateActivityOnSite($selenium, $fields)
    {
        try {
            if ($recent = $selenium->waitForElement(\WebDriverBy::xpath("//input[@value='Recent searches']"), 0)) {
                $recent->click();
                usleep(rand(100000, 500000));
                $recent->click();
            }

            if ($oneway = $selenium->waitForElement(\WebDriverBy::xpath("//input[@value='oneway']"), 0)) {
                $oneway->click();
            }
        } catch (\StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage());
            $selenium->driver->executeScript('window.stop();');

            if ($oneway = $selenium->waitForElement(\WebDriverBy::xpath("//input[@value='oneway']"), 0)) {
                $oneway->click();
            }
        }

        try {
            if ($depField = $selenium->waitForElement(\WebDriverBy::id('originationAirportCode'), 0)) {
                $depField->sendKeys($fields['DepCode']);
            }
        } catch (\StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage());
            $selenium->driver->executeScript('window.stop();');

            try {
                if ($depField = $selenium->waitForElement(\WebDriverBy::id('originationAirportCode'), 0)) {
                    $depField->sendKeys($fields['DepCode']);
                }
            } catch (\Facebook\WebDriver\Exception\WebDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }
        } catch (\Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        try {
            if ($arrField = $selenium->waitForElement(\WebDriverBy::id('destinationAirportCode'), 0)) {
                $arrField->sendKeys($fields['ArrCode']);
            }
        } catch (\StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage());
            $selenium->driver->executeScript('window.stop();');

            if ($arrField = $selenium->waitForElement(\WebDriverBy::id('destinationAirportCode'), 0)) {
                $arrField->sendKeys($fields['ArrCode']);
            }
        }
    }

    private function checkBlock($selenium)
    {
        if ($selenium->waitForElement(\WebDriverBy::xpath("
                    //span[contains(text(), 'This site can’t be reached') or contains(text(), 'This page isn’t working') or contains(text(), 'This site can’t provide a secure connection')]
                    | //h1[normalize-space()='Access Denied']
                    | //h1[normalize-space()='No internet']
                "), 0)) {
            $selenium->keepSession(false);

            throw new \CheckRetryNeededException(5, 0);
        }
    }

    private function getAirports(): array
    {
        $this->logger->notice(__METHOD__);
        $airportCodes = \Cache::getInstance()->get('ra_rapid_airports');

        if (!is_array($airportCodes)) {
            $this->http->GetURL("https://www.southwest.com/swa-ui/bootstrap/air-booking/1/data.js");

            if ($this->isBadProxy()) {
                throw new \CheckRetryNeededException(5, 0);
            }

            $textWithJson = strstr($this->http->Response['body'], 'swa-bootstrap-air-booking/analytics-codes-data',
                true);
            $textWithJson = strstr($textWithJson, 'swa-bootstrap-air-booking/air/stations-data');
            $jsonData = $this->http->FindPreg("/swa-bootstrap-air-booking\/air\/stations-data\":\[function\(require,module,exports\)\{
module.exports = (\[\s*\{.+\}\s*\]);\s*\},\{\}\],\"/s", false, $textWithJson);
            $airports = $this->http->JsonLog($jsonData, 0, true);
            $airportCodes = [];

            if (is_array($airports)) {
                foreach ($airports as $airport) {
                    if (!empty($airport['altSearchNames'])) {
                        $airportCodes[] = $airport['id'];
                    }
                }
            } else {
                $this->sendNotification("check airports // ZM");
            }

            if (!empty($airportCodes)) {
                \Cache::getInstance()->set('ra_rapid_airports', $airportCodes, 60 * 60 * 24);
            }
        }
        $this->logger->debug(var_export($airportCodes, true));

        return $airportCodes;
    }

    private function isBadProxy(): bool
    {
        return
            $this->http->Response['code'] == 403
            || strpos($this->http->Error, 'Network error 28 - Connection timed out after') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 403 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            ;
    }

    private function setConfig()
    {
        $configs = self::CONFIGS;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            unset($configs['chrome-94-mac']);
        }

        $successfulConfigs = array_filter(array_keys($configs), function (string $key) {
            return \Cache::getInstance()->get('rapidrewards_config_' . $key) === 1;
        });

        $neutralConfigs = array_filter(array_keys($configs), function (string $key) {
            return \Cache::getInstance()->get('rapidrewards_config_' . $key) !== 0;
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

        $this->logger->info("selected config $this->config");
    }

    private function markConfigAsBad(): void
    {
        if ($this->newSession) {
            $this->logger->info("marking config {$this->config} as bad");
            \Cache::getInstance()->set('rapidrewards_config_' . $this->config, 0, 60 * 60);
        }
    }
}
