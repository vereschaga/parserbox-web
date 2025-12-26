<?php

namespace AwardWallet\Engine\hawaiian\RewardAvailability;

use AwardWallet\Common\Selenium\BrowserCommunicatorException;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;
use CheckRetryNeededException;
use Facebook\WebDriver\Exception\UnrecognizedExceptionException;
use TimeOutException;
use WebDriverBy;

//    CREDENTIALS FOR TEST
//    [
//        'Login' => 'iormark',
//        'Pass' => 'Banana1234',
//    }
class Parser extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;

    public const STATISTIC_KEY_BROWSERS = 'ra_hawaiian_stBr';
    private const XPATH_LOGOUT = '//span[@class = "nav-account-number"] | //h4[contains(.,"Your Member Benefits")]';
    private const XPATH_BAD_PROXY = "//h1[contains(text(), 'This site can’t be reached')]
                    | //span[contains(text(), 'This site can’t be reached')]
                    | //h1[normalize-space()='Access Denied']
                    | //h1[normalize-space()='Unable to connect']
                    | //h1[normalize-space()='Connection Error.']
                    | //span[contains(text(), 'This page isn’t working')]
                    | //div[contains(text(), 'Access to this website has been temporarily blocked')]
                    | //p[contains(text(), 'There is something wrong with the proxy server, or the address is incorrect.')]
                    | //h2[contains(.,'403 - Forbidden')]
                    | //h3[contains(text(), 'You do not have permission to view this directory or page using the credentials that you supplied')]
                    ";
    public $isRewardAvailability = true;
    private $headers = [];
    private $responseData;

    public static function getRASearchLinks(): array
    {
        return ['https://www.hawaiianairlines.com/book/flights' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->keepCookies(false);
//        $this->KeepState = true;
        $this->debugMode = $this->AccountFields['DebugState'] ?? false;

        $array = ['us', 'uk', 'ca'];
        $targeting = $array[array_rand($array)];

        if ($targeting === 'us' && $this->AccountFields['ParseMode'] === 'awardwallet') {
            $this->setProxyMount();
        } else {
            switch ($this->attempt) {
                case 1:
                    $this->setProxyDOP(Settings::DATACENTERS_NORTH_AMERICA);

                    break;

                default:
                    $this->setProxyGoProxies(null, $targeting);

                    break;
            }
        }

        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        return false;
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
        return [
            'supportedCurrencies'      => ['USD'],
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

        if ($fields['Adults'] > 7) {
            $this->SetWarning("It's too much travellers");

            return [];
        }

        if ($fields['DepDate'] > strtotime('+330 day')) {
            $this->SetWarning('The requested departure date is too late.');

            return [];
        }

        if (!$this->validRoute($fields)) {
            return ['routes' => []];
        }

        $this->headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json;charset=UTF-8',
        ];
        $this->responseData = null;

        try {
            $this->selenium($fields);
        } catch (\UnexpectedAlertOpenException $e) {
            $this->logger->error('UnexpectedAlertOpenException: ' . $e->getMessage());

            throw new CheckRetryNeededException(5, 3);
        }

        if (is_array($this->responseData)) {
            return ['routes' => []];
        }

        if (empty($this->responseData)) {
            $this->logger->notice("empty response fare search");

            throw new CheckRetryNeededException(5, 3);
        }

        $data = $this->http->JsonLog($this->responseData, 1, false);

        if (isset($data->message) && strpos($data->message, 'Exception') !== false) {
            throw new CheckRetryNeededException(5, 0);
        }

        return ["routes" => $this->parseRewardFlights($data, $fields)];
    }

    private function getCabin(string $cabin, bool $isFlip = true)
    {
        $cabins = [
            'economy'    => 'M',
            'business'   => 'C',
            'firstClass' => 'F',
        ];

        if ($isFlip) {
            $cabins = array_flip($cabins);
        }

        if (isset($cabins[$cabin])) {
            return $cabins[$cabin];
        }

        $this->sendNotification("RA check cabin {$cabin} (" . var_export($isFlip, true) . ") // MI");

        throw new \CheckException("check cabin {$cabin} (" . var_export($isFlip, true) . ")", ACCOUNT_ENGINE_ERROR);
    }

    private function getAwardType(string $brandId, bool $isFlip = false, ?bool $isFull = true)
    {
        $brands = [
            'MB' => 'Main Cabin Basic',
            'MN' => 'Main Cabin',
            'MR' => 'Refundable Main Cabin',
            'CP' => 'Corporate Special',
            'CS' => 'Corporate Standard',
            'CX' => 'Corporate Flexible',
            'CB' => 'Corporate Business Class',
            'CF' => 'Corporate First Class',
            'PF' => 'Extra Comfort',
            'FC' => 'First Class',
            'BC' => 'Business Class',
            'AM' => 'Main Cabin',
            'AF' => 'First Class',
            'AB' => 'Business Class',
        ];

        if ($isFlip) {
            $brands = array_flip($brands);
        }

        if (isset($brands[$brandId])) {
            if (!$isFull) {
                $brands[$brandId] = str_replace([' Cabin', ' Class'], '', $brands[$brandId]);
            }

            return $brands[$brandId];
        }
        $this->sendNotification("RA check award type {$brandId} (" . var_export($isFlip, true) . ") // MI");

        throw new \CheckException("check award type {$brandId} (" . var_export($isFlip, true) . ")", ACCOUNT_ENGINE_ERROR);
    }

    private function parseRewardFlights($data, array $fields, ?bool $isRetry = false): array
    {
        $routes = [];

        foreach ($data as $trip) {
            $segment = $trip->segments[0];

            if (substr($segment->departure->airportDateTimeString, 0, 10) !== date('Y-m-d', $fields['DepDate'])) {
                continue;
            }

            foreach ($trip->fareList as $fareDetail) {
                if (!isset($fareDetail->farePrice->totalMiles->amount)) {
                    $this->logger->error('no miles data');

                    throw new CheckRetryNeededException(5, 0);
                }
                $numStops = count($fareDetail->flightDetails) - 1;

                $route = [
                    'distance'    => null,
                    'num_stops'   => ($numStops > 0) ? $numStops : null,
                    'times'       => ['flight' => null, 'layover' => null],
                    'redemptions' => [
                        'miles'   => $fareDetail->farePrice->totalMiles->amount,
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $fareDetail->farePrice->taxesTotal->currency,
                        'taxes'    => round($fareDetail->farePrice->taxesTotal->amount),
                        'fees'     => null,
                    ],
                    'connections'    => [],
                    'tickets'        => null,
                    'award_type'     => $this->getAwardType($fareDetail->brandId),
                    'classOfService' => $this->getAwardType($fareDetail->brandId, false, false),
                ];

                $flightDetails = [];

                foreach ($fareDetail->flightDetails as $flightDetail) {
                    $flightDetails[$flightDetail->flightId] = [
                        'cabin'         => $flightDetail->cabin,
                        'brandId'       => $flightDetail->brandId,
                        'fareBasisCode' => $flightDetail->fareBasisCode,
                    ];
                }

                foreach ($trip->segments[0]->flights as $segNum => $flight) {
                    if ($segNum === 0 && $trip->segments[0]->origin !== $fields['DepCode']) {
                        throw new CheckRetryNeededException(5, 0);
                    }

                    $cabin = $this->getCabin(
                        $flightDetails[$flight->flightId]['cabin']
                    );
                    $segClassOfService = $this->getAwardType(
                        $flightDetails[$flight->flightId]['brandId'], false, false
                    );
                    $segBookingClass = $flightDetails[$flight->flightId]['fareBasisCode'];

                    $route['connections'][] = [
                        'departure' => [
                            'date'     => date('Y-m-d H:i', strtotime($flight->departureDateTime->airportDateTimeString)),
                            'dateTime' => strtotime($flight->departureDateTime->airportDateTimeString),
                            'airport'  => $flight->departureLocation,
                            'terminal' => null,
                        ],
                        'arrival' => [
                            'date'     => date('Y-m-d H:i', strtotime($flight->arrivalDateTime->airportDateTimeString)),
                            'dateTime' => strtotime($flight->arrivalDateTime->airportDateTimeString),
                            'airport'  => $flight->arrivalLocation,
                            'terminal' => null,
                        ],
                        'meal'           => null,
                        'cabin'          => $cabin ?? null,
                        'fare_class'     => $segBookingClass ?? null,
                        'classOfService' => $segClassOfService ?? null,
                        'flight'         => ["{$flight->operatingProvider}{$flight->flightNumber}"],
                        'airline'        => $flight->operatingProvider,
                        'operator'       => $flight->operatingProvider,
                        'distance'       => null,
                        'aircraft'       => null,
                        'times'          => [],
                    ];
                }

                $route['times'] = [];

                $this->logger->debug('Parsed data:');
                $this->logger->debug(var_export($route, true), ['pre' => true]);
                $routes[] = $route;
            }
        }

        if (empty($routes)) {
            $this->SetWarning('No Flights');
        }

        return $routes;
    }

    private function selenium(array $fields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $responseData = null;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;
//            $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);

            switch (1) {
                case 0:
                    $this->useFirefox(\SeleniumFinderRequest::FIREFOX_100);
                    $request = FingerprintRequest::firefox();

                    $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
                    $platforms = ['Linux x86_64', 'MacIntel', 'Win32', 'Windows'];
                    $request->platform = $platforms[array_rand($platforms)];
                    $this->logger->debug("search FP: " . $request->platform);
                    $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                    if ($fingerprint) {
                        $selenium->http->setUserAgent($fingerprint->getUseragent());
                    }

                    $selenium->http->saveScreenshots = true;

                    break;

                case 1:
                    $this->useFirefoxPlaywright();
                    $selenium->http->setRandomUserAgent(null, true, false, false);

                    $selenium->http->saveScreenshots = false;

                    break;
            }

            $selenium->disableImages();
            $selenium->useCache();

            $selenium->seleniumRequest->setHotSessionPool(self::class, $this->AccountFields['ProviderCode']);

            try {
                $selenium->http->start();
                $selenium->Start();
            } catch (\ErrorException $e) {
                $this->logger->error('ErrorException: ' . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            } catch (\NoAlertOpenException $e) {
                $this->logger->error('NoAlertOpenException: ' . $e->getMessage());
                $this->sendNotification('NoAlertOpenException on start selenium // ZM');
            } catch (\Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error('TimeoutException: ' . $e->getMessage());
                $this->sendNotification('TimeoutException on start selenium // ZM');

                throw new \CheckRetryNeededException(5, 0);
            }
            /** @var \SeleniumDriver $seleniumDriver */
            $seleniumDriver = $selenium->http->driver;
            $browserInfo = $seleniumDriver->getBrowserInfo();
            $key = $this->getKeyConfig($browserInfo);
            $this->logger->debug('selenium browser: ' . $key);

            try {
//                    $selenium->http->GetURL('https://www.hawaiianairlines.com/');
                $selenium->http->GetURL('https://www.hawaiianairlines.com/book/flights', [], 15);
//                sleep(300);
            } catch (\UnexpectedAlertOpenException $e) {
                $this->logger->error("UnexpectedAlertOpenException exception: " . $e->getMessage());

                try {
                    $error = $selenium->driver->switchTo()->alert()->getText();
                    $this->logger->debug("alert -> {$error}");
                    $selenium->driver->switchTo()->alert()->accept();
                    $this->logger->debug("alert, accept");
                } catch (\NoAlertOpenException $e) {
                    $this->logger->error("UnexpectedAlertOpenException -> NoAlertOpenException exception: " . $e->getMessage());

                    throw new CheckRetryNeededException(5, 0);
                }
                $this->savePageToLogs($selenium);

                if ($this->http->FindSingleNode("//p[contains(.,'Firefox is configured to use a proxy server that is refusing connections')]")) {
                    throw new CheckRetryNeededException(5, 0);
                }
            }

            try {
            } catch (\ErrorException $e) {
                throw new \CheckRetryNeededException(5, 0);
            }

            $selenium->waitForElement(\WebDriverBy::xpath("
            //div[@class='travel-alert-description']
            | //div[contains(text(),'systems are temporarily unavailable')]
            | //a[@id='sign_in_link']
            | //a[@id='my_account_user_dropdown']
            "), 15);

            $description = $selenium->waitForElement(WebDriverBy::xpath("//div[@class='travel-alert-description']"), 0);

            if ($description && strpos($description->getText(),
                    'Booking new tickets on HawaiianAirlines.com or on our mobile app remains unavailable') !== false) {
                throw new \CheckException('Booking new tickets on HawaiianAirlines.com or on our mobile app remains unavailable. You may book new tickets with your preferred online travel agency.', ACCOUNT_PROVIDER_ERROR);
            }

            if ($selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(),'systems are temporarily unavailable')]"),
                0)) {
                throw new \CheckException('Systems are temporarily unavailable as we perform scheduled maintenance and upgrades.', ACCOUNT_PROVIDER_ERROR);
            }

            if (!$selenium->waitForElement(WebDriverBy::xpath('//a[@id="my_account_user_dropdown"]'), 0)) {
                if ($selenium->waitForElement(WebDriverBy::xpath('//a[@id="sign_in_link"]'), 0)) {
                    $selenium->http->GetURL("https://www.hawaiianairlines.com/my-account/login/?ReturnUrl=%2fbook%2fflights");

                    if ($selenium->waitForElement(WebDriverBy::xpath(
                        self::XPATH_BAD_PROXY . " | //input[@name = 'UserName'] | //h4[contains(.,'Your Member Benefits')]"),
                        15)
                    ) {
                        if ($selenium->waitForElement(WebDriverBy::xpath(self::XPATH_BAD_PROXY), 0)
                        ) {
                            $this->savePageToLogs($selenium);

                            $this->DebugInfo = "bad proxy";
                            $this->markProxyAsInvalid();

                            throw new \CheckRetryNeededException(5, 0);
                        }
                    }
                } else {
                    throw new CheckRetryNeededException(5, 0);
                }
            }
            $this->savePageToLogs($selenium);

            // Sorry, an error occurred while processing your request.
            if ($selenium->waitForElement(WebDriverBy::xpath("
                //h2[contains(text(), 'Service Unavailable')]
                | //h2[contains(text(), 'Sorry, an error occurred while processing your request.')]
                | //h1[contains(text(), 'Internal Server Error - Read')]
            "), 0)
            ) {
//                | //strong[contains(text(), 'HawaiianMiles Maintenance')]/ancestor::div[1] -
//                We will be performing maintenance to our HawaiianMiles systems. During this period, award and miles redemption will be unavailable. Response times and website performance may be slower than usual. We apologize for any inconvenience this may cause. Thank you for your patience and understanding as we work to improve our systems to serve you better.
//      не тот вид тех работ - поиск возможен
                throw new \CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // set csrf
            $this->headers = [
                "csrf"             => $this->http->FindPreg("/var tokens = '([^\']+)/"),
                "X-Requested-With" => "XMLHttpRequest",
                "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            ];
            $this->logger->debug(var_export($this->headers, true), ['pre' => true]);

            $logout = $selenium->waitForElement(WebDriverBy::xpath(self::XPATH_LOGOUT), 0, true);

            if (!$logout) {
                if (!$this->tryLogIn($selenium)) {
                    if ($selenium->waitForElement(WebDriverBy::xpath("//em[contains(@for,'Password')]"), 0)) {
                        $selenium->http->GetURL('https://www.hawaiianairlines.com/my-account/login');

                        if (!$this->tryLogIn($selenium)) {
                            throw new CheckRetryNeededException(5, 3);
                        }
                    }
                }
            }
            // check ones again
            if (!$selenium->waitForElement(WebDriverBy::xpath('//a[@id="my_account_user_dropdown"]'), 10)) {
                $this->logger->error('not logged in');

                throw new \CheckRetryNeededException(5, 0);
            }

            $responseData = $this->fillFormSearchFlight($selenium, $fields);

            if (empty($responseData) && $this->ErrorCode !== ACCOUNT_WARNING) {
                $responseData = $this->runMainUrl($selenium, $fields);
            }
        } catch (\ScriptTimeoutException | \TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->logger->error('TimeOutException: ' . $e->getMessage());
            // checked, always fail on start
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(5, 0);
        } catch (\NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());

            throw new CheckRetryNeededException(5, 0);
        } catch (\WebDriverCurlException | \UnknownServerException | \SessionNotCreatedException
        | \UnexpectedJavascriptException | \Facebook\WebDriver\Exception\WebDriverCurlException
        | \WebDriverException | \Facebook\WebDriver\Exception\UnknownErrorException
        | \Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->markProxyAsInvalid();
            $noStat = true;

            throw new CheckRetryNeededException(5, 3);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'New session attempts retry count exceeded') !== false
                || strpos($e->getMessage(), 'session not created') !== false
            ) {
                // TODO не стартует селениум, надо выше чинить если валит
                $this->logger->error('Exception: ' . $e->getMessage());
                $this->sendNotification($e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }

            throw $e;
        } finally {
            $this->responseData = $responseData ?? null;

            // statistic
            if (isset($seleniumDriver)) {
                $memStatBrowsers = \Cache::getInstance()->get(self::STATISTIC_KEY_BROWSERS);

                if (!is_array($memStatBrowsers)) {
                    $memStatBrowsers = [];
                }
                $browserInfo = $seleniumDriver->getBrowserInfo();
                $key = $this->getKeyConfig($browserInfo);

                if (!isset($memStatBrowsers[$key])) {
                    $memStatBrowsers[$key] = ['success' => 0, 'failed' => 0];
                }

                if ($this->ErrorCode === ACCOUNT_WARNING
                    || (!empty($this->responseData)
                        && !(
                            property_exists($this->responseData, 'message')
                            && is_string($this->responseData->message)
                            && strpos($this->responseData->message, 'internal server error (500)') !== false
                        )
                    )
                ) {
                    $memStatBrowsers[$key]['success']++;
                } else {
                    $memStatBrowsers[$key]['failed']++;
                }

                if (!isset($noStat)) {
                    \Cache::getInstance()->set(self::STATISTIC_KEY_BROWSERS, $memStatBrowsers, 60 * 60 * 24);
                }
                $this->logger->warning(var_export($memStatBrowsers, true), ['pre' => true]);
            }

            if (!empty($this->responseData)) {
                if (property_exists($this->responseData, 'message')
                    && is_string($this->responseData->message)
                    && strpos($this->responseData->message, 'internal server error (500)') !== false
                ) {
                    throw new \CheckException('Systems are temporarily unavailable', ACCOUNT_PROVIDER_ERROR);
                }
                $this->logger->debug("Data ok. Save session");
                $selenium->keepSession(true);
            } elseif ($this->ErrorCode === ACCOUNT_WARNING) {
                $this->logger->debug("Data ok. Save session");
                $selenium->keepSession(true);
            }

            // close Selenium browser
            $selenium->http->cleanup();
        }
    }

    private function fillFormSearchFlight($selenium, $fields)
    {
        $this->logger->notice(__METHOD__);

        try {
            if (strpos($selenium->http->currentUrl(), '/www.hawaiianairlines.com/book/flights') === false) {
                $selenium->http->GetURL('https://www.hawaiianairlines.com/book/flights');
            }
        } catch (\ScriptTimeoutException | \TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $selenium->driver->executeScript('window.stop();');
            $this->savePageToLogs($selenium);

            if (strpos($selenium->http->currentUrl(), '/www.hawaiianairlines.com/book/flights') === false) {
                $selenium->http->GetURL('https://www.hawaiianairlines.com/book/flights');
            }
        }

        try {
            if (!$tripType = $selenium->waitForElement(WebDriverBy::xpath("//a[@id=\"triptype1\"]"), 0)) {
                throw new CheckRetryNeededException(5, 0);
            }

            $tripType->click();

            $dateDepEu = date('m/d/Y', $fields['DepDate']);

            if (!$departureDate = $selenium->waitForElement(WebDriverBy::xpath("//input[@id=\"DepartureDate\"]"), 5,
                false)) {
                throw new CheckRetryNeededException(5, 0);
            }

            $departureDate->click();
            $departureDate->clear();
            $departureDate->sendKeys($dateDepEu);

            $this->savePageToLogs($selenium);

            if (!$this->fillAirport($selenium, 'origin', $fields['DepCode'])) {
                $this->SetWarning('No flights from ' . $fields['DepCode']);

                return [];
            }

            if (!$this->fillAirport($selenium, 'destination', $fields['ArrCode'])) {
                $this->SetWarning('No flights from ' . $fields['DepCode'] . ' to ' . $fields['ArrCode']);

                return [];
            }

            $selenium->driver->executeScript("document.querySelector('select[id=\'adultCount\']').value={$fields['Adults']};");

            if ($selenium->driver->executeScript("return document.querySelector('#type1').disabled;") == true) {
                $this->sendNotification('check Miles // ZM');

                if ($this->attempt === 0) {
                    $this->savePageToLogs($selenium);

                    throw new CheckRetryNeededException(5, 0);
                }
                $this->SetWarning('No reward flights from ' . $fields['DepCode'] . ' to ' . $fields['ArrCode']);

                return [];
            }

            if (!$miles = $selenium->waitForElement(WebDriverBy::xpath("//label[@for=\"type1\"]"), 0, false)) {
                throw new CheckRetryNeededException(5, 0);
            }

            $miles->click();

            $this->savePageToLogs($selenium);

            // re-enter (скорее всего в этот день нет перелетов. но, для надежности перебьем
            if ($selenium->waitForElement(\WebDriverBy::xpath("//em[@for='_FlightSearchSegmentList[0].DepartureDate' and contains(.,'This field is required')]"),
                2)) {
                $this->SetWarning('No flights');

                return [];

                if (!$departureDate = $selenium->waitForElement(WebDriverBy::xpath("//input[@id=\"DepartureDate\"]"), 5,
                    false)) {
                    throw new CheckRetryNeededException(5, 0);
                }

                $departureDate->click();
                $departureDate->clear();
                $departureDate->sendKeys($dateDepEu);

                $this->savePageToLogs($selenium);
            }

            if (!$btnSearchFlights = $selenium->waitForElement(WebDriverBy::xpath("//button[@ng-if=\"btnSearchFlights\"]"),
                0, false)) {
                throw new CheckRetryNeededException(5, 0);
            }
            $btnSearchFlights->click();
        } catch (\UnrecognizedExceptionException | UnrecognizedExceptionException $e) {
            $this->logger->error($e->getMessage());

            throw new CheckRetryNeededException(5, 0);
        }

        $selenium->waitForElement(\WebDriverBy::xpath("
                //div[contains(text(), 'Access to this website has been temporarily blocked')]
                | //h1[normalize-space()='Depart']
                | //h1[normalize-space()='Sorry, something went wrong']
                | " . self::XPATH_BAD_PROXY
        ), 30);
//        $selenium->waitForElement(\WebDriverBy::xpath("
//                //span[contains(text(),'Showing Prices As:')]
//                | //input[name=\"FlightSearchSegmentList[0].DestinationCityCode\"]
//                | //div[contains(text(), 'Access to this website has been temporarily blocked')]
//                | //div[@class=\"flight-results\"]
//                | //h1[normalize-space()='Sorry, something went wrong']
//                | " . self::XPATH_BAD_PROXY
//                ), 30);

        $selenium->waitFor(function () use ($selenium) {
            return !$selenium->waitForElement(\WebDriverBy::xpath('//p[contains(.,"Book now, change if you need to without a fee.")]'),
                0);
        }, 30);
        // wait again
        $selenium->waitForElement(\WebDriverBy::xpath("
                //div[contains(text(), 'Access to this website has been temporarily blocked')]
                | //h1[normalize-space()='Depart']
                | //h1[normalize-space()='Sorry, something went wrong']
                | " . self::XPATH_BAD_PROXY
        ), 30);

        if ($alert =
            $selenium->waitForElement(
                \WebDriverBy::xpath("//div[contains(text(), 'Access to this website has been temporarily blocked')]"),
                0
            )
        ) {
            throw new \CheckException($alert->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        if ($selenium->waitForElement(WebDriverBy::xpath(self::XPATH_BAD_PROXY), 0)) {
            $this->DebugInfo = "bad proxy";
            $this->markProxyAsInvalid();
            $this->savePageToLogs($selenium);

            throw new \CheckRetryNeededException(5, 0);
        }

        if (!$selenium->waitForElement(\WebDriverBy::xpath("//h1[normalize-space()='Depart']"), 0)
            && $selenium->waitForElement(WebDriverBy::xpath("//h1[normalize-space()='Sorry, something went wrong']"), 0)
        ) {
            $this->savePageToLogs($selenium);
            $msg = $this->http->FindSingleNode("//h1[normalize-space()='Sorry, something went wrong']/following-sibling::p[1]");

            if (strpos($msg, 'Please try again') === false) {
                $msg = 'We have encountered an unknown error and are looking into it. Please try again';
            }

            if (time() - $this->requestDateTime > $this->AccountFields['Timeout'] - 20) {
                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }

            throw new \CheckRetryNeededException(2, 0, $msg, ACCOUNT_PROVIDER_ERROR);
        }

        try {
            $cookies = $selenium->driver->manage()->getCookies();
        } catch (\WebDriverException $e) {
            $this->logger->error($e->getMessage());

            throw new CheckRetryNeededException(5, 0);
        }

        foreach ($cookies as $cookie) {
            $allCook[$cookie['name']] = $cookie['value'];
            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                $cookie['expiry'] ?? null);
        }
        $responseData = null;

        $this->savePageToLogs($selenium);

        if ($msg = $selenium->http->FindSingleNode("//div[contains(.,'Sorry, there are no flights available for this date. Please try a different date and/or route.')]")) {
            $this->SetWarning($msg);

            return [];
        }
        $results = $this->http->FindNodes("//div[contains(@class,'flight-row')]");

        if (empty($results)) {
            $this->savePageToLogs($selenium);
            sleep(1);
            $results = $this->http->FindNodes("//div[contains(@class,'flight-row')]");
        } else {
            $this->logger->error('flight-row not found');
        }

        if (!empty($results)) {
            $fareSearch = $selenium->driver->executeScript("return window.sessionStorage.getItem('__fareSearch');");
            $data = $this->http->JsonLog(stripslashes($fareSearch), 1);

            if (isset($data->fareSearchResults) && count($data->fareSearchResults) === count($results)) {
                $responseData = json_encode($data->fareSearchResults);
            }
        }

        if (!isset($responseData)) {
            /** @var \SeleniumDriver $seleniumDriver */
            $seleniumDriver = $selenium->http->driver;

            try {
//                sleep(5);
                $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
            } catch (BrowserCommunicatorException | \NoSuchDriverException | \UnexpectedJavascriptException $e) {
                $this->logger->error('Exception: ' . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            } catch (\ErrorException $e) {
                $this->logger->error('getRecordedRequests - ErrorException: ' . $e->getMessage());

                if (strpos($e->getMessage(), 'Expected parameter 2 to be an array, string given') !== false
                    || strpos($e->getMessage(), 'Expected parameter 2 to be an array, null given') !== false) {
                    return null;
                }

                throw $e;
            }

            foreach ($requests as $n => $xhr) {
                if (strpos($xhr->request->getUri(), 'api/fareSearch') !== false) {
                    $responseData = json_encode($xhr->response->getBody());
                }
            }
        }

        return $responseData;
    }

    private function fillAirport($selenium, $type, $airCode, ?bool $isRetry = false): bool
    {
        // $type = ['origin', 'destination'];
        if (!$airport = $selenium->waitForElement(WebDriverBy::xpath("//input[@id=\"{$type}\"]"), 0, false)) {
            throw new CheckRetryNeededException(5, 0);
        }

        $airport->clear();
        $airport->sendKeys(' ' . $airCode);

        if (!$airportBtn = $selenium->waitForElement(WebDriverBy::xpath("//a/span/strong[contains(text(),'{$airCode}')]"),
            15, false)) {
            $this->savePageToLogs($selenium);
            $textAirport = $selenium->driver->executeScript("return $('input#{$type}').val()");

            if (!$this->http->FindPreg("/\({$airCode}\)/", false, $textAirport)) {
                if (!$isRetry) {
                    return $this->fillAirport($selenium, $type, $airCode, true);
                }

                if ($this->attempt === 0) {
                    throw new CheckRetryNeededException(5, 0);
                }

                return false;
            }
            $checked = true;
        }

        if (!isset($checked)) {
            $airportBtn->click();
        }

        return true;
    }

    private function runMainUrl($selenium, $fields, $isRetry = false)
    {
        $this->logger->notice(__METHOD__);

        $dateDepStd = date('Y-m-d', $fields['DepDate']);
        $selenium->http->GetURL("https://shopping.hawaiianairlines.com/?l={$fields['DepCode']}-{$fields['ArrCode']}+{$dateDepStd}&a={$fields['Adults']}&c=0&r=0&p=1&y=USD&pc=&affCode=&pss=1A");

        $selenium->waitForElement(\WebDriverBy::xpath("
                //div[contains(text(), 'Access to this website has been temporarily blocked')]
                | //h1[normalize-space()='Sorry, something went wrong']
                | //h1[normalize-space()='Depart']
                | " . self::XPATH_BAD_PROXY
        ), 30);

        $selenium->waitFor(function () use ($selenium) {
            return !$selenium->waitForElement(\WebDriverBy::xpath('
            //p[contains(.,"Book now, change if you need to without a fee.")]
            | //*[normalize-space()="No change fees"]'), 0);
        }, 50);

        if (!$selenium->waitForElement(\WebDriverBy::xpath("
                //div[contains(text(), 'Access to this website has been temporarily blocked')]
                | //h1[normalize-space()='Depart']
                | //h1[normalize-space()='Sorry, something went wrong']
                | " . self::XPATH_BAD_PROXY
        ), 0)) {
            // не догрузилось. проверка ожидания свыше показала, что лишь 1% дожидается, оптимальнее рестартануть
            $this->logger->error('something went wrong. page not load');
            $this->savePageToLogs($selenium);

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($selenium->waitForElement(WebDriverBy::xpath(self::XPATH_BAD_PROXY), 0)) {
            $this->DebugInfo = "bad proxy";
            $this->markProxyAsInvalid();

            throw new \CheckRetryNeededException(5, 0);
        }

        if (!$selenium->waitForElement(\WebDriverBy::xpath("//h1[normalize-space()='Depart']"), 0)
            && $selenium->waitForElement(WebDriverBy::xpath("//h1[normalize-space()='Sorry, something went wrong']"), 0)
        ) {
            $this->savePageToLogs($selenium);

            if (!$isRetry) {
                $selenium->http->GetURL('https://www.hawaiianairlines.com/book/flights');
                sleep(2);

                if ($miles = $selenium->waitForElement(\WebDriverBy::xpath("//span[normalize-space(text())='Miles']"),
                    0)) {
                    $miles->click();
                }

                return $this->runMainUrl($selenium, $fields, true);
            }
            $msg = $this->http->FindSingleNode("//h1[normalize-space()='Sorry, something went wrong']/following-sibling::p[1]");

            if (strpos($msg, 'Please try again') === false) {
                $msg = 'We have encountered an unknown error and are looking into it. Please try again';
            }

            throw new \CheckRetryNeededException(2, 0, $msg, ACCOUNT_PROVIDER_ERROR);
        }

        try {
            $cookies = $selenium->driver->manage()->getCookies();
        } catch (\WebDriverException $e) {
            $this->logger->error($e->getMessage());

            throw new CheckRetryNeededException(5, 0);
        }

        foreach ($cookies as $cookie) {
            $allCook[$cookie['name']] = $cookie['value'];
            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                $cookie['expiry'] ?? null);
        }
        $responseData = null;

        $this->savePageToLogs($selenium);

        $results = $this->http->FindNodes("//div[contains(@class,'flight-row')]");

        if (empty($results)) {
            $selenium->waitFor(function () use ($selenium) {
                return !$selenium->waitForElement(\WebDriverBy::xpath('//p[contains(.,"Book now, change if you need to without a fee.")]'),
                    0);
            }, 30);
            $this->savePageToLogs($selenium);

            if ($msg = $this->http->FindSingleNode("//div[contains(text(),'Sorry, there are no flights available for this date. Please try a different date and/or route.')]")) {
                $this->SetWarning($msg);

                return [];
            }

            if ($msg = $this->http->FindSingleNode("//div[contains(.,'re sorry but there are no flights available for this date. Please try a different date and/or route')]")) {
                $this->SetWarning($msg);

                return [];
            }
            $results = $this->http->FindNodes("//div[contains(@class,'flight-row')]");
        }

        if (!empty($results)) {
            $fareSearch = $selenium->driver->executeScript("return sessionStorage.getItem('__fareSearch');");
            $fareSearch = stripslashes($fareSearch);

            if (strpos($fareSearch, '"') === 0) {
                $this->logger->debug($fareSearch, ['pre' => true]);
                $fareSearch = substr($fareSearch, 1, -1);
            }
            $data = $this->http->JsonLog($fareSearch, 1);

            if (empty($data)) {
                $this->sendNotification('check fareSearch state // ZM');

                $fareSearch = $selenium->driver->executeScript("return sessionStorage.getItem('state');");
                $fareSearch = stripslashes($fareSearch);

                if (strpos($fareSearch, '"') === 0) {
                    $this->logger->debug($fareSearch, ['pre' => true]);
                    $fareSearch = substr($fareSearch, 1, -1);
                }
                $data = $this->http->JsonLog($fareSearch, 1);

                if (isset($data->fareSearch->fareSearchResults) && count($data->fareSearch->fareSearchResults) === count($results)) {
                    $responseData = json_encode($data->fareSearchResults);
                }
            }

            if (isset($data->fareSearchResults) && count($data->fareSearchResults) === count($results)) {
                $responseData = json_encode($data->fareSearchResults);
            }
        }

        if (!isset($responseData)) {
            /** @var \SeleniumDriver $seleniumDriver */
            $seleniumDriver = $selenium->http->driver;

            try {
//                sleep(5);
                $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
            } catch (BrowserCommunicatorException | \NoSuchDriverException | \UnexpectedJavascriptException | \ErrorException $e) {
                $this->logger->error('Exception: ' . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }

            foreach ($requests as $n => $xhr) {
                if (strpos($xhr->request->getUri(), 'api/fareSearch') !== false) {
                    $responseData = json_encode($xhr->response->getBody());
                }
            }
        }

        return $responseData;
    }

    private function getKeyConfig(array $info)
    {
        return $info[\SeleniumStarter::CONTEXT_BROWSER_FAMILY] . '-' . $info[\SeleniumStarter::CONTEXT_BROWSER_VERSION];
    }

    private function tryLogIn($selenium, $isRetry = false): bool
    {
        $input = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'UserName']"), 0);

        if ($input === null) {
            return false;
        }

        if (!isset($this->AccountFields['Login']) || !isset($this->AccountFields['Pass'])) {
            throw new CheckRetryNeededException(5, 0);
        }

        $login = preg_replace('/\s+/ims', '', $this->AccountFields['Login']);
        $input->clear();
        $input->sendKeys($login);

        if (!$this->enterPassword($selenium)) {
            throw new \CheckRetryNeededException(5, 5);
        }

        $sleep = 20; // если не авторизовался, то скорее всего уже нет
        $startTime = time();
        $loggedIn = false;
        $wasRetry = false;

        while ((time() - $startTime) < $sleep) {
            try {
                $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
                $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
                // look for logout link
                $logout = $selenium->waitForElement(WebDriverBy::xpath(self::XPATH_LOGOUT), 0, true);
                $this->savePageToLogs($selenium);

                if (!$logout && $selenium->http->currentUrl() === 'https://www.hawaiianairlines.com/book/flights?logon=true'
                    && $selenium->waitForElement(WebDriverBy::xpath("//script[contains(.,'var acctNo =') and contains(.,'var isLoggedIn = true;')]"),
                        0, false)) {
                    $logout = true;
                }

                $error = $selenium->waitForElement(WebDriverBy::xpath("
                      //div[contains(normalize-space(text()),'Sorry, your login attempt was unsuccessful. If you entered an email address shared by multiple accounts, please check your email for instructions')] 
                    | //div[contains(normalize-space(text()),'Email and password could not be found. Please try again')] 
                    | (//em[contains(.,'This field is required')])[1]
                "), 0);

                if ($error || $selenium->waitForElement(WebDriverBy::xpath("//em[contains(@for,'Password')]"), 0)) {
                    if (!$isRetry) {
                        $selenium->http->GetURL('https://www.hawaiianairlines.com/book/flights');

                        if (!$selenium->waitForElement(WebDriverBy::xpath('//a[@id="my_account_user_dropdown"]'), 2)) {
                            if ($link = $selenium->waitForElement(WebDriverBy::xpath('//a[@id="sign_in_link"]'), 2)) {
                                $link->click();
                            }
                        }
                        $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'UserName']"), 10);
                        $this->savePageToLogs($selenium);

                        return $this->tryLogIn($selenium, true);
                    }

                    if ($wasRetry) {
                        throw new \CheckRetryNeededException(5, 5);
                    }
                    $wasRetry = true;

                    if (!$this->enterPassword($selenium)) {
                        throw new \CheckRetryNeededException(5, 5);
                    }
                }
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $this->DebugInfo = "TimeOutException";

                throw new CheckRetryNeededException(5, 3);
            }

            if ($logout) {
                $loggedIn = true;

                break;
            }
        }

        if (!$loggedIn) {
            throw new CheckRetryNeededException(5, 0);
        }

        return $loggedIn;
    }

    private function enterPassword($selenium): bool
    {
//        $passInput = $selenium->driver->findElement(WebDriverBy::xpath("//input[@name = 'Password']"), 0);
//
//        $mover->moveToElement($passInput);
//        $mover->click();
//        $mover->sendKeys($passInput, $this->AccountFields['Pass'], 40);
//
//        $this->savePageToLogs($selenium);
//
//        $loginButton = $selenium->waitForElement(WebDriverBy::xpath("//form[@name = 'login']//button"));
//
//        if (!$loginButton) {
//            $this->logger->error('Failed to find login button');
//
//            return false;
//        }
//
//        $mover->moveToElement($loginButton);
//        $mover->click();

//         Старая схема без Мувера, рабочая
        $selenium->driver->findElement(WebDriverBy::xpath("//input[@name = 'Password']"))->sendKeys($this->AccountFields['Pass']);
        $this->savePageToLogs($selenium);

        $loginButton = $selenium->waitForElement(WebDriverBy::xpath("//form[@name = 'login']//button"));

        if (!$loginButton) {
            $this->logger->error('Failed to find login button');

            return false;
        }

        $selenium->driver->executeScript("document.querySelector('form[name = \"login\"] button').click();");

        return true;
    }

    private function isBadProxy(): bool
    {
        return strpos($this->http->Error,
                'Network error 56 - Received HTTP code 502 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 7 - Failed to connect to') !== false
            || strpos($this->http->Error, 'Network error 56 - Unexpected EOF') !== false
            || strpos($this->http->Error, 'Network error 35 - OpenSSL SSL_connect') !== false
            || strpos($this->http->Error, 'empty body') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 407 from proxy after') !== false;
    }

    private function validRoute($fields): bool
    {
        $this->logger->notice(__METHOD__);
        $dataFrom = \Cache::getInstance()->get('ra_hawaiian_origins');

        if (!$dataFrom) {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://js.s-hawaiianairlines.com/Book/City/6.0.8/GetValidCities?sc_lang=en", [], 10);
            $this->http->RetryCount = 2;

            if ($this->isBadProxy()) {
                throw new \CheckRetryNeededException(5, 0);
            }

            if (strpos($this->http->Error, 'Operation timed out after') !== false) {
                $this->http->RetryCount = 0;
                $this->http->GetURL("https://js.s-hawaiianairlines.com/Book/City/6.0.8/GetValidCities?sc_lang=en", [],
                    10);
                $this->http->RetryCount = 2;
            }

            $dataFrom = $this->http->JsonLog(null, 2);

            if (!empty($dataFrom)) {
                \Cache::getInstance()->set('ra_hawaiian_origins', $dataFrom, 60 * 60 * 24);
            } else {
                // try to search anywhere
                return $this->routeForPoints($fields);
            }
        }

        if (isset($dataFrom->CityList->Cities->{$fields['DepCode']})) {
            if (in_array($fields['ArrCode'], $dataFrom->CityList->Cities->{$fields['DepCode']})) {
                return $this->routeForPoints($fields);
            }
        }
        $this->SetWarning("{$fields['DepCode']} -> {$fields['ArrCode']} is not in list of destinations");

        return false;
    }

    private function routeForPoints($fields): bool
    {
        $this->logger->notice(__METHOD__);
        $dataFrom = \Cache::getInstance()->get('ra_hawaiian_point_origins');

        if (!$dataFrom) {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://js.s-hawaiianairlines.com/Book/City/6.0.8/GetCities?sc_lang=en", [], 10);
            $this->http->RetryCount = 2;

            if ($this->isBadProxy()) {
                throw new \CheckRetryNeededException(5, 0);
            }

            if (strpos($this->http->Error, 'Operation timed out after') !== false) {
                $this->http->RetryCount = 0;
                $this->http->GetURL("https://js.s-hawaiianairlines.com/Book/City/6.0.8/GetCities?sc_lang=en", [], 10);
                $this->http->RetryCount = 2;
            }

            $dataFrom = $this->http->JsonLog(null, 2);

            if (!empty($dataFrom)) {
                \Cache::getInstance()->set('ra_hawaiian_point_origins', $dataFrom, 60 * 60 * 24);
            } else {
                // try to search anywhere
                return true;
            }
        }

        $depData = $arrDate = null;

        foreach ($dataFrom->CityList->Cities as $city) {
            if (isset($depData, $arrDate)) {
                return true;
            }

            if ($city->Code === $fields['DepCode']) {
                if ($city->IsHACity === false) {
                    $this->SetWarning('no reward flights from ' . $fields['DepCode']);

                    return false;
                }
                $depData = $city;

                continue;
            }

            if ($city->Code === $fields['ArrCode']) {
                if ($city->IsHACity === false) {
                    $this->SetWarning('no reward flights to ' . $fields['ArrCode']);

                    return false;
                }
                $arrData = $city;
            }
        }

        if (in_array($fields['DepCode'], ['MKK', 'JHM', 'LNY'])
            || in_array($fields['ArrCode'], ['MKK', 'JHM', 'LNY'])
        ) {
            $this->SetWarning('no reward flights from ' . $fields['DepCode'] . ' to ' . $fields['ArrCode']);

            return false;
        }

        if (!isset($depData) || !isset($arrData)) {
            // try to search anywhere
            return true;
        }

        // origin cannot be code share, unless it's Ohana flights (MKK, JHM, or LNY)
        // destination cannot be code share, unless it's Ohana flights (MKK, JHM, or LNY)
        if ($depData->IsCodeShare) {
            $this->SetWarning('no reward flights from ' . $fields['DepCode']);
            $this->sendNotification('check route for points // ZM');

            return false;
        }

        if ($arrData->IsCodeShare) {
            $this->SetWarning('no reward flights to ' . $fields['ArrCode']);
            $this->sendNotification('check route for points // ZM');

            return false;
        }
        /*  see https://js.s-hawaiianairlines.com/bundles/24.3.1.270/app function isHARoute() {...} */

        // try to search anywhere
        return true;
    }
}
