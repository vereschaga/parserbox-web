<?php

namespace AwardWallet\Engine\virgin\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use CheckRetryNeededException;
use Facebook\WebDriver\Exception\InvalidSelectorException;
use Facebook\WebDriver\Exception\InvalidSessionIdException;
use Facebook\WebDriver\Exception\SessionNotCreatedException;
use Facebook\WebDriver\Exception\WebDriverException;
use Symfony\Component\HttpClient\Exception\TransportException;

class Parser extends \TAccountChecker
{
    // parser is almost the same as delta
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;

    private const ATTEMPTS_CNT = 4;
    private const PROXY_KEY = 'ra_virgin_denied_proxy';
    private const BROWSER_STATISTIC_KEY = 'ra_virgin_statistBr';
    public static bool $useScrapingBrowser = false;

    private const CONFIGS = [
        'chrome-84' => [
            'agent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family' => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_84,
        ],
        'chrome-95' => [
            'agent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family' => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_95,
        ],
        'chrome-99' => [
            'agent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family' => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_99,
        ],
        'chrome-extension' => [
            'agent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family' => \SeleniumFinderRequest::BROWSER_CHROME_EXTENSION,
            'browser-version' => \SeleniumFinderRequest::CHROME_EXTENSION_103,
        ],
        'chrome-100' => [
            'agent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family' => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_100,
        ],
        'firefox-playwright-101' => [
            'agent' => null,
            'browser-family' => \SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_101,
        ],
        'firefox-84' => [
            'agent' => null,
            'browser-family' => \SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_84,
        ],
        'chrome-puppeteer-103' => [
            'agent' => null,
            'browser-family' => \SeleniumFinderRequest::BROWSER_CHROME_PUPPETEER,
            'browser-version' => \SeleniumFinderRequest::CHROME_PUPPETEER_103,
        ],
        // for prod
        'chrome-94-mac' => [
            'browser-family' => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_94,
        ],
    ];
    public $isRewardAvailability = true;
    private $airportDetails = [];
    private $cacheKey;
    private $supportedCurrencies = ['USD'];
    private $sensorPostUrl;
    private $refererSensor;
    private $config;
    private $newSession;

    public static function GetAccountChecker($accountInfo)
    {
        if (self::$useScrapingBrowser) {
            require_once __DIR__ . "/ParserScrap.php";

            return new ParserScrap();
        }
        return new static();
    }

    public static function getRASearchLinks(): array
    {
        return ['https://www.virginatlantic.com/us/en' => 'search page'];
    }

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Upgrade-Insecure-Requests", "1");
        $this->http->setDefaultHeader("Connection", "keep-alive");
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');

        switch (random_int(0, 1)) {
            case 0:
                $this->config = 'chrome-puppeteer-103';

                break;
            /*
                        case 1:
                            $this->config = 'chrome-99';

                            break;

                        case 2:
                            $this->config = 'chrome-84';

                            break;

                        case 3:
                            $this->config = 'chrome-95';

                            break;
                        case 4:
                            $this->config = 'chrome-extension';

                            break;
                        case 5:
                            $this->config = 'firefox-84';

                            break;
                            */
            default:
                $this->config = 'chrome-94-mac';

                break;
        }

        $array     = ['de', 'us', 'ca', 'fi', 'au', 'fr'];
        $targeting = $array[array_rand($array)];

        if ($this->AccountFields['ParseMode'] === 'awardwallet') {
            $this->setProxyGoProxies(null, $targeting);
        } else {
            $this->setProxyGoProxies(null, $targeting);
        }

        $this->logger->info("selected config $this->config");
//        $this->setConfig();

//        $this->http->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.6.1 Safari/605.1.15');
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies' => $this->supportedCurrencies,
            'supportedDateFlexibility' => 0,
            'defaultCurrency' => 'USD',
        ];
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
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

        try {
            $routes = $this->ParseReward($fields);
        } catch (\WebDriverException|WebDriverException $e) {
            $this->logger->error($e->getMessage());

            throw new CheckRetryNeededException(5, 0);
        }

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

    private function setConfig()
    {
        $configs = self::CONFIGS;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            unset($configs['chrome-94-mac']);
        }

        $successfulConfigs = array_filter(array_keys($configs), function (string $key) {
            return \Cache::getInstance()->get('virgin_config_' . $key) === 1;
        });

        $neutralConfigs = array_filter(array_keys($configs), function (string $key) {
            return \Cache::getInstance()->get('virgin_config_' . $key) !== 0;
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
            case 'economy':
                return 'VSLT';

            case 'premiumEconomy':
                return 'VSPE';

            case 'business':
                return 'VSUP';

            case 'firstClass':
                return 'VSUP';
        }
        $this->sendNotification('new cabin ' . $cabin . ' // ZM');

        return null;
    }

    private function ParseReward($fields = []): array
    {
        // Get cacheKey
        // Load data
        $fields['DepDate'] = date("Y-m-d", $fields['DepDate']);
        $brandID           = $this->encodeCabin($fields['Cabin']);

        try {
            $data = $this->selenium($fields, $brandID);
        } catch (\WebDriverException|\WebDriverCurlException $e) {
            $this->logger->error('WebDriverException: ' . $e->getMessage());

            throw new CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
        } catch (\ErrorException|InvalidSessionIdException
        |\UnexpectedResponseException|SessionNotCreatedException $e) {
            $this->logger->error('ErrorException: ' . $e->getMessage());

            if (strpos($e->getMessage(), 'Undefined ') === 0) {
                $this->logger->error('ErrorException: ' . $e->getTraceAsString());
                $this->sendNotification("check error with index // ZM");
            }

            throw new CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
        }

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
                $result                    = [
                    'distance' => null,
                    'num_stops' => $trip->stopCount,
                    'times' => [
                        'flight' => null,
                        'layover' => null,
                    ],
                    'redemptions' => [
                        // totalPriceByPTC - price for all, totalPrice - for ane
                        'miles' => $itOffer->totalPrice->miles->miles,
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $itOffer->totalPrice->currency->code,
                        'taxes' => $itOffer->totalPrice->currency->amount,
                        'fees' => null,
                    ],
                    'tickets' => $itOffer->seatsAvailableCount ?? null,
                    'classOfService' => $this->clearCOS($this->getBrandName($itOffer->dominantSegmentBrandId,
                        $itOffer->brandByFlightLeg)),
                ];

                $result['connections'] = [];

                foreach ($trip->flightSegment as $flightSegment) {
                    $flightSegmentId = $flightSegment->id;

                    foreach ($flightSegment->flightLeg as $numLeg => $flightLeg) {
                        $flightLegId = $flightLeg->id;
                        $seg         = [
                            'departure' => [
                                'date' => date('Y-m-d H:i', strtotime($flightLeg->schedDepartLocalTs)),
                                'dateTime' => strtotime($flightLeg->schedDepartLocalTs),
                                'airport' => $flightLeg->originAirportCode,
                            ],
                            'arrival' => [
                                'date' => date('Y-m-d H:i', strtotime($flightLeg->schedArrivalLocalTs)),
                                'dateTime' => strtotime($flightLeg->schedArrivalLocalTs),
                                'airport' => $flightLeg->destAirportCode,
                            ],
                            'meal' => null,
                            'cabin' => null,
                            'fare_class' => null,
                            'distance' => $flightLeg->distance->measure . ' ' . $flightLeg->distance->unit,
                            'aircraft' => $flightLeg->aircraft->fleetTypeCode,
                            'flight' => [$flightLeg->viewSeatUrl->fltNumber],
                            'airline' => $flightLeg->marketingCarrier->code,
                            'operator' => $flightLeg->operatingCarrier->code,
                            'times' => [
                                'flight' => null,
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

                                        if (empty($itOffer->brandByFlightLeg[$numLeg]->brandName)){
                                            $brandName = $this->getBrandName($itOffer->dominantSegmentBrandId,
                                                $itOffer->brandByFlightLeg);
                                        } else {
                                            $brandName = $itOffer->brandByFlightLeg[$numLeg]->brandName;
                                        }

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
            'MAIN' => 'Economy Classic',
            'E'    => 'Economy Classic',
            'AFST' => 'Economy Classic',
            'VSCL' => 'Economy Classic',
            'VSPE' => 'Premium',
            'VSUP' => 'Upper Class',
            'BU'    => 'Upper Class',
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

        $airports           = \Cache::getInstance()->get('ra_virgin_airports');
        $airportDesc        = \Cache::getInstance()->get('ra_virgin_airportDesc');
        $airportCountryCode = \Cache::getInstance()->get('ra_virgin_airportCountry');

        if (!$airports || !is_array($airports) || !$airportDesc || !is_array($airportDesc) || !$airportCountryCode || !is_array($airportCountryCode)) {
            $airports           = [];
            $airportDesc        = [];
            $airportCountryCode = [];

            $this->http->GetURL("https://www.virginatlantic.com/util/airports/ALL/asc", [], 20);

            if ($this->http->currentUrl() === 'https://www.virginatlantic.com/gb/en/error/system-unavailable1.html') {
                // it's work
                $this->http->GetURL("https://www.virginatlantic.com/util/airports/ALL/asc", [], 20);
            }
            $data = $this->http->JsonLog(null, 1);

            if ($this->http->currentUrl() === 'https://www.virginatlantic.com/gb/en/error/system-unavailable1.html') {
                $this->markProxyAsInvalid();

                throw new \CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
            }

            if (strpos($this->http->Error,
                    'Network error 56 - Received HTTP code 407 from proxy after CONNECT') !== false
                || strpos($this->http->Error,
                    'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
                || strpos($this->http->Error, 'Network error 28 - Operation timed out after ') !== false
                || $this->http->Response['code'] == 403
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
            }

            if (!isset($data->listOfCities) || !is_array($data->listOfCities)) {
                return true;
            }

            if (empty($data->listOfCities)) {
                return true;
            }

            foreach ($data->listOfCities as $city) {
                $airports[]                             = $city->airportCode;
                $airportDesc[$city->airportCode]        = $city->cityName . ', ' . $city->region;
                $airportCountryCode[$city->airportCode] = $city->countryCode;
            }

            if (!empty($airports)) {
                \Cache::getInstance()->set('ra_virgin_airports', $airports, 60 * 60 * 24);
                \Cache::getInstance()->set('ra_virgin_airportDesc', $airportDesc, 60 * 60 * 24);
                \Cache::getInstance()->set('ra_virgin_airportCountry', $airportCountryCode, 60 * 60 * 24);
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

        $this->airportDetails = [
            $fields['DepCode'] => [
                'desc' => $airportDesc[$fields['DepCode']],
                'country' => $airportCountryCode[$fields['DepCode']]
            ],
            $fields['ArrCode'] => [
                'desc' => $airportDesc[$fields['ArrCode']],
                'country' => $airportCountryCode[$fields['ArrCode']]
            ],
        ];

        return true;
    }

    private function selenium($fields, $brandID)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $response = null;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->seleniumRequest->request(self::CONFIGS[$this->config]['browser-family'],
                self::CONFIGS[$this->config]['browser-version']);

            $useMac = 0;

            if (in_array($this->config, ['chrome-94-mac', 'firefox-playwright-101', 'chrome-puppeteer-103'])) {
                if ($this->config === 'chrome-94-mac') {
                    $useMac = 1;
                } else {
                    $useMac = random_int(0, 1);
                }
            }

            if ($useMac) {
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->http->setUserAgent(null);
                $selenium->usePacFile(false);
                $selenium->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
            } else {
                if (strpos($this->config, 'firefox') !== false) {
                    $request = FingerprintRequest::firefox();
                } else {
                    $request = FingerprintRequest::chrome();
                }
                if (strpos($this->config, 'extension') !== false) {
                    $selenium->seleniumOptions->addPuppeteerStealthExtension = false;
                    $selenium->seleniumOptions->addHideSeleniumExtension     = false;
                    $selenium->seleniumOptions->userAgent                    = null;
                } else {
                    $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
                    $request->platform          = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
                    $fingerprint                = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                    if (isset($fingerprint)) {
                        $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                        $selenium->http->setUserAgent($fingerprint->getUseragent());
                        $selenium->seleniumOptions->userAgent = $fingerprint->getUseragent();
                    } else {
                        if (strpos($this->config, 'firefox') !== false) {
                            $selenium->http->setRandomUserAgent(null, true, false, false, true, false);
                        } else {
                            $selenium->http->setRandomUserAgent(null, false, true, false, true, false);
                        }
                    }
                }
            }

            $selenium->disableImages();

            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $selenium->setScreenResolution($resolutions[array_rand($resolutions)]);

//            $selenium->useCache();
//            for debug
            $selenium->http->saveScreenshots = true;
            $selenium->seleniumRequest->setHotSessionPool(self::class, $this->AccountFields['ProviderCode']);

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
                    throw new \CheckRetryNeededException(5, 0);
                }
            }

            /** @var \SeleniumDriver $seleniumDriver */
            $seleniumDriver   = $selenium->http->driver;
            $this->newSession = $seleniumDriver->isNewSession();

            try {
                // do not request page on hot session
                if (strpos($selenium->http->currentUrl(),
                        "https://www.virginatlantic.com/flight-search/book-a-flight") === false) {
                    $selenium->http->GetURL("https://www.virginatlantic.com/en-US");

                    if ($btnCookies = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(text(),'Yes, I Agree')]"),
                        5, true)) {
                        $btnCookies->click();
                    }

                    if ($reward = $selenium->waitForElement(\WebDriverBy::xpath("//span[normalize-space()='Reward flights']"), 15)) {
                        $reward->click();
                    } elseif ($selenium->waitForElement(
                        \WebDriverBy::xpath("
                            //div[contains(@class,'homepageError')]//h1[contains(text(),'Site unavailable')]
                            | //h1[contains(.,'This site can’t be reached')]
                            | //h1[contains(.,'This page isn’t working')]
                            | //h1[contains(.,'Access Denied')]"
                        ), 0)
                    ) {
                        $retry        = true;
                        $accessDenied = true;

                        return [];
                    }

                    if ($roundTrip = $selenium->waitForElement(\WebDriverBy::xpath("//button[./span[normalize-space()='Round trip']]"),
                        5)) {
                        $roundTrip->click();
                        $oneway = $selenium->waitForElement(\WebDriverBy::xpath("//li[./button[./span[normalize-space()='One way']]]"),
                            3);

                        if (!$oneway) {
                            $this->logger->error('bad load');
                            // сайт тупит. лучше сразу на рестрат иначе watchdog прибьет
                            throw new \CheckRetryNeededException(5, 0);
                        }
                        $oneway->click();

                        if ($from = $selenium->waitForElement(\WebDriverBy::xpath("//input[@id='flights_from']"), 0)) {
                            $from->sendKeys($fields['DepCode']);

                            if ($selenium->waitForElement(\WebDriverBy::xpath("//section[@id='popover-flying-from']"),
                                10)) {
                                $from->sendKeys(\WebDriverKeys::ARROW_DOWN);
                                $from->sendKeys(\WebDriverKeys::ENTER);
                            }
                        }

                        if ($to = $selenium->waitForElement(\WebDriverBy::xpath("//input[@id='flights_to']"), 0)) {
                            $to->sendKeys($fields['ArrCode']);

                            if ($selenium->waitForElement(\WebDriverBy::xpath("//section[@id='popover-flights-to']"),
                                10)) {
                                $to->sendKeys(\WebDriverKeys::ARROW_DOWN);
                                $to->sendKeys(\WebDriverKeys::ENTER);
                            }
                        }

                        if ($search = $selenium->waitForElement(\WebDriverBy::xpath("//button[.//span[normalize-space()='Search']]"))) {
                            $search->click();
                        }
                    }

                    $this->savePageToLogs($selenium);

                    if (strpos($selenium->http->currentUrl(),
                            "https://www.virginatlantic.com/flight-search/book-a-flight?tripType=") === false) {
                        $selenium->http->GetURL("https://www.virginatlantic.com/flight-search/book-a-flight?tripType");
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Exception: ' . $e->getMessage());
            }

            if (!($loadPage = $selenium->waitForElement(\WebDriverBy::xpath("//h1[normalize-space()='Book a flight'] | //a[@id='fromAirportName']"),
                15, false))) {
                $selenium->http->GetURL("https://www.virginatlantic.com/flight-search/book-a-flight");
                $loadPage = $selenium->waitForElement(\WebDriverBy::xpath("//h1[normalize-space()='Book a flight']  | //a[@id='fromAirportName']"),
                    45,
                    false);
            }

            if (!$loadPage) {
                // TODO: debug
                // сильно похоже, что сразу рестартить
                if ($this->http->FindSingleNode("//div[contains(@class,'homepageError')]//h1[contains(text(),'Site unavailable')]")
                    || $this->http->FindSingleNode("//h1[contains(.,'This site can’t be reached')]")
                    || $this->http->FindSingleNode("//h1[contains(.,'This page isn’t working')]")
                    || $this->http->FindSingleNode("//h1[contains(.,'Access Denied')]")
                ) {
                    $retry        = true;
                    $accessDenied = true;

                    return [];
                }

                if ($message = $selenium->http->FindPreg("/An error occurred while processing your request\.<p>/")) {
                    $this->logger->error($message);

                    throw new \CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
                $this->savePageToLogs($selenium);

                throw new CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
            }

            if ($btnCookies = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(text(),'Yes, I Agree')]"),
                0, true)) {
                $btnCookies->click();
            }

            try {
                $selenium->driver->executeScript("
                try {document.querySelector('#survey-wrapper').querySelector('button[data-aut=\"button-x-close\"]').click()} catch(e){console.log(e)}
                ");
                $selenium->driver->executeScript("$('button:contains(\"Close\")').click();");
            } catch (\WebDriverException|\UnexpectedResponseException
            |\WebDriverCurlException|\Facebook\WebDriver\Exception\WebDriverException $e) {
                $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage());
            }

            // save page to logs
            $this->savePageToLogs($selenium);

            // retries
            if ($this->http->FindPreg('/(?:page isn’t working|There is no Internet connection|This site can’t be reached)/ims')) {
                $retry = true;

                return [];
            }

            $payload = $this->getPayload($fields, $brandID);

            try {
                if ($btnCookies = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(text(),'Yes, I Agree')]"),
                    0, true)) {
                    $btnCookies->click();
                }

                if ($points = $selenium->waitForElement(\WebDriverBy::xpath("//label[./span[normalize-space()='Points']]"),
                    0, true)) {
                    $points->click();
                }

                $btnFrom = $selenium->waitForElement(\WebDriverBy::xpath("//a[@id='fromAirportName']"), 0, true);

                if ($btnFrom) {
                    $btnFrom->click();
                }

                if ($inputFrom = $selenium->waitForElement(\WebDriverBy::xpath("//input[@id='search_input']"), 1,
                    true)) {
                    $inputFrom->sendKeys('ZZZ');
                    $inputFrom->sendKeys(\WebDriverKeys::ENTER);
                    $inputFrom->sendKeys(\WebDriverKeys::ESCAPE);
                }

                $btnSubmit = $selenium->waitForElement(\WebDriverBy::xpath('//button[@id="btnSubmit"]/..'), 0, true);

                if ($btnSubmit) {
                    $btnSubmit->click();
                }
            } catch (\UnrecognizedExceptionException $e) {
                $this->logger->error('UnrecognizedExceptionException: ' . $e->getMessage());
            } catch (\WebDriverException|WebDriverException $e) {
                $this->logger->error('WebDriverException: ' . $e->getMessage());
                $this->checkWebDriverException($e->getMessage());

                $this->savePageToLogs($selenium);
            }

            try {
                $btnClose = $selenium->waitForElement(\WebDriverBy::xpath("//modal-container"),
                    0, true);

                if ($btnClose) {
                    $btnClose->click();
                }
            } catch (\UnrecognizedExceptionException $e) {
                $this->logger->error('UnrecognizedExceptionException: ' . $e->getMessage());
            } catch (\WebDriverException $e) {
                $this->logger->error('WebDriverException: ' . $e->getMessage());
                $this->checkWebDriverException($e->getMessage());
                $this->savePageToLogs($selenium);
            }

            $this->savePageToLogs($selenium);

            $response = null;

            try {
                $response = $this->search($selenium, $payload);

                if (strpos($response, 'Access Denied') !== false) {
                    $selenium->setProxyGoProxies();
                    $selenium->http->removeCookies();
                    $selenium->http->GetURL("https://www.virginatlantic.com/en-US");

                    if ($btnCookies = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(text(),'Yes, I Agree')]"),
                        5, true)) {
                        $btnCookies->click();
                    }

                    $selenium->http->GetURL("https://www.virginatlantic.com/flight-search/flexible-dates?cacheKeySuffix=" . $this->cacheKey);
                    $selenium->waitForElement(\WebDriverBy::xpath("//img[@class='siteLogo']"), 10);
                    if (($pl = $this->getPayloadFlexDate($fields, $brandID))) {
                        $this->loadPayload($selenium, $pl);
                        $response = $this->searchXMLHttpFlexDate($selenium, $pl);
                    }

                    if (strpos($response, 'Access Denied') !== false) {
                        $retry        = true;
                        $accessDenied = true;

                        return [];
                    }
                    $response = $this->http->JsonLog($response);

                    if (!isset($data->shoppingError, $data->shoppingError->error, $data->shoppingError->error->message, $data->shoppingError->error->message->message)) {
                        $response = $this->search($selenium, $payload);
                        if (strpos($response, 'Access Denied') !== false) {
                            $retry        = true;
                            $accessDenied = true;

                            return [];
                        }
                    }
                }
                if (is_string($response)) {
                    $response = $this->http->JsonLog($response);
                }
            } catch (InvalidSelectorException|\InvalidSelectorException
            |\UnexpectedResponseException|\WebDriverCurlException $e) {
                $this->logger->error($e->getMessage());

                throw new CheckRetryNeededException(5, 0);
            }

            if (!empty($response)) {
                $this->logger->notice('Data ok, saving session');
                $selenium->keepSession(true);
            }

            $this->savePageToLogs($selenium);
        } catch (TransportException $e) {
            $this->logger->error("TransportException: " . $e->getMessage());
            $retry = true;
        } catch (\UnknownServerException|\SessionNotCreatedException|\WebDriverCurlException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry           = true;
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Call to a member function click() on null')) {
                $this->savePageToLogs($selenium);
            }
            $this->logger->error("exception: " . $e->getMessage());

            throw $e;
        } finally {
            if (isset($seleniumDriver)) {
                // statistic
                $memStatBrowsers = \Cache::getInstance()->get(self::BROWSER_STATISTIC_KEY);

                if (!is_array($memStatBrowsers)) {
                    $memStatBrowsers = [];
                }
                $browserInfo = $seleniumDriver->getBrowserInfo();
                $key         = $this->getKeyConfig($browserInfo);

                if (!isset($memStatBrowsers[$key])) {
                    $memStatBrowsers[$key] = ['success' => 0, 'failed' => 0];
                }
            }
            // close Selenium browser
            $selenium->http->cleanup();
            if ((empty($response) || isset($accessDenied)) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->markProxyAsInvalid();

                if (isset($memStatBrowsers)) {
                    $memStatBrowsers[$key]['failed']++;
                }
            } else {
                if (isset($memStatBrowsers)) {
                    $memStatBrowsers[$key]['success']++;
                }
            }

            if (isset($memStatBrowsers)) {
                \Cache::getInstance()->set(self::BROWSER_STATISTIC_KEY, $memStatBrowsers, 60 * 60 * 24);
                $this->logger->warning(var_export($memStatBrowsers, true), ['pre' => true]);
            }

            if ($this->newSession) {
                if (isset($accessDenied)) {
                    $this->logger->info("marking config {$this->config} as bad");
                    \Cache::getInstance()->set('virgin_config_' . $this->config, 0);
                } else {
                    $this->logger->info("marking config {$this->config} as successful");
                    \Cache::getInstance()->set('virgin_config_' . $this->config, 1);
                }
            }
            // retries
            if (isset($retry) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
            }
        }

        return $response;
    }

    private function getKeyConfig(array $info)
    {
        foreach (self::CONFIGS as $key => $config) {
            if (
                $info[\SeleniumStarter::CONTEXT_BROWSER_FAMILY] === $config[\SeleniumStarter::CONTEXT_BROWSER_FAMILY]
                && $info[\SeleniumStarter::CONTEXT_BROWSER_VERSION] === $config[\SeleniumStarter::CONTEXT_BROWSER_VERSION]
            ) {
                return $key;
            }
        }

        return $info[\SeleniumStarter::CONTEXT_BROWSER_FAMILY] . '-' . $info[\SeleniumStarter::CONTEXT_BROWSER_VERSION];
    }

    private function checkWebDriverException($message)
    {
        if (strpos($message, 'JSON decoding of remote response failed') !== false
            && $this->http->FindPreg("/\bError code: 4\b/", false, $message)
        ) {
            throw new CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
        }
    }

    private function savePageToLogs($selenium)
    {
        try {
            $this->logger->notice(__METHOD__);
            // save page to logs
            $selenium->http->SaveResponse();
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } catch (\WebDriverException|\UnexpectedResponseException
        |\WebDriverCurlException|\Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error('WebDriverException: ' . $e->getMessage());
            $this->checkWebDriverException($e->getMessage());
        } catch (\ErrorException $e) {
            $this->logger->error("ErrorException exception: " . $e->getMessage());
            $this->logger->error($e->getTraceAsString(), ['pre' => true]);
        }
    }

    private function owSearch($cacheKey, $payload)
    {
        $headers = [
            'Accept' => 'application/json',
            'cachekey' => $cacheKey,
            'Content-Type' => 'application/json; charset=UTF-8',
            'Origin' => 'https://www.virginatlantic.com',
            'Referer' => 'https://www.virginatlantic.com/flight-search/search-results?cacheKeySuffix=' . $cacheKey,
            'x-app-channel' => 'sl-sho',
            'x-app-refresh' => '',
            'x-app-route' => 'SL-RSB',
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.virginatlantic.com/shop/ow/search",
            $payload, $headers);

        if (strlen($this->http->Response['body']) == 0) {
            $this->sendNotification('check retry // ZM');
            $this->http->PostURL("https://www.virginatlantic.com/shop/ow/search",
                $payload, $headers);
        }

        $this->http->RetryCount = 2;

        if ($this->http->FindPreg("/We're sorry, there was a problem processing your request. Please go back and try the entry again/")) {
            return null;
        }

        if ($this->http->Response['code'] == 403) {
            return null;
        }

        return $this->http->JsonLog(null, 0);
    }

    private function getPayload($fields, $brandID)
    {
        $payload = '{"action":"findFlights","destinationAirportRadius":{"unit":"MI","measure":100},"deltaOnlySearch":false,"originAirportRadius":{"unit":"MI","measure":100},"passengers":[{"type":"ADT","count":' . $fields['Adults'] . '},{"type":"GBE","count":0},{"type":"CNN","count":0},{"type":"INF","count":0}],"searchType":"search","segments":[{"origin":"' . $fields['DepCode'] . '","destination":"' . $fields['ArrCode'] . '","departureDate":"' . $fields['DepDate'] . '","returnDate":null}],"shopType":"MILES","tripType":"ONE_WAY","priceType":"Award","priceSchedule":"price","awardTravel":true,"refundableFlightsOnly":false,"nonstopFlightsOnly":false,"datesFlexible":false,"flexCalendar":false,"flexAirport":false,"upgradeRequest":false,"pageName":"FLIGHT_SEARCH","cacheKey":"' . $this->cacheKey . '","actionType":"flexDateSearch","initialSearchBy":{"meetingEventCode":"","refundable":false,"flexAirport":false,"flexDate":false,"flexDaysWeeks":"FLEX_DAYS"},"vendorDetails":{"vendorReferrerUrl":"https://www.virginatlantic.com/en-US"},"sortableOptionId":"priceAward","requestPageNum":"1","filter":null}';
//        $payload = '{"bestFare":"' . $brandID . '","action":"findFlights","destinationAirportRadius":{"unit":"MI","measure":100},"deltaOnlySearch":false,"meetingEventCode":"","originAirportRadius":{"unit":"MI","measure":100},"passengers":[{"type":"ADT","count":' . $fields['Adults'] . '}],"searchType":"search","segments":[{"origin":"' . $fields['DepCode'] . '","destination":"' . $fields['ArrCode'] . '","departureDate":"' . $fields['DepDate'] . '","returnDate":null}],"shopType":"MILES","tripType":"ONE_WAY","priceType":"Award","priceSchedule":"AWARD","awardTravel":true,"refundableFlightsOnly":false,"nonstopFlightsOnly":false,"datesFlexible":true,"flexCalendar":false,"flexAirport":false,"upgradeRequest":false,"pageName":"FLIGHT_SEARCH","cacheKey":"' . $this->cacheKey . '","requestPageNum":"1","actionType":"","initialSearchBy":{"fareFamily":"' . $brandID . '","meetingEventCode":"","refundable":false,"flexAirport":false,"flexDate":true,"flexDaysWeeks":"FLEX_DAYS"},"sortableOptionId":"priceAward","filter":null}';

        return $payload;
    }

    private function getPayloadFlexDate($fields, $brandID): ?string
    {
        if (!isset($this->airportDetails[$fields['DepCode']]) || !isset($this->airportDetails[$fields['ArrCode']])) {
            return null;
        }
        $payload = "{\"isEdocApplied\":false,\"tripType\":\"ONE_WAY\",\"shopType\":\"MILES\",\"priceType\":\"Award\",\"nonstopFlightsOnly\":\"false\",\"bookingPostVerify\":\"RTR_YES\",\"bundled\":\"off\",\"segments\":[{\"origin\":\"" . $fields['DepCode'] . "\",\"destination\":\"" . $fields['ArrCode'] . "\",\"originCountryCode\":\"" . $this->airportDetails[$fields['DepCode']]["country"] . "\",\"destinationCountryCode\":\"" . $this->airportDetails[$fields['ArrCode']]["country"] . "\",\"departureDate\":\"" . $fields['DepDate'] . "\",\"connectionAirportCode\":null}],\"destinationAirportRadius\":{\"measure\":100,\"unit\":\"MI\"},\"originAirportRadius\":{\"measure\":100,\"unit\":\"MI\"},\"flexAirport\":false,\"flexDate\":true,\"flexDaysWeeks\":\"FLEX_DAYS\",\"passengers\":[{\"count\":" . $fields['Adults'] . ",\"type\":\"ADT\"}],\"meetingEventCode\":\"\",\"bestFare\":\"VSLT\",\"searchByCabin\":true,\"cabinFareClass\":null,\"refundableFlightsOnly\":false,\"deltaOnlySearch\":\"false\",\"initialSearchBy\":{\"fareFamily\":\"VSLT\",\"cabinFareClass\":null,\"meetingEventCode\":\"\",\"refundable\":false,\"flexAirport\":false,\"flexDate\":true,\"flexDaysWeeks\":\"FLEX_DAYS\",\"deepLinkVendorId\":null},\"searchType\":\"flexDateSearch\",\"searchByFareClass\":null,\"pageName\":\"FLEX_DATE\",\"requestPageNum\":\"\",\"action\":\"findFlights\",\"actionType\":\"\",\"priceSchedule\":\"AWARD\",\"schedulePrice\":\"miles\",\"shopWithMiles\":\"on\",\"awardTravel\":\"true\",\"datesFlexible\":true,\"flexCalendar\":false,\"upgradeRequest\":false,\"is_Flex_Search\":true}";

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

        try {
            $selenium->driver->executeScript($script);
        } catch (\WebDriverException|\UnexpectedResponseException
        |\WebDriverCurlException|\Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(self::ATTEMPTS_CNT, 0);
        }
    }

    private function search($selenium, $payload)
    {
        $this->cacheKey = $this->getUuid();
        $payload        = preg_replace('/cacheKey":"([^"]+)"/', 'cacheKey":"' . $this->cacheKey . '"', $payload);
        $result         = $this->searchXMLHttp($selenium, $payload);
//        $selenium->http->GetURL("https://www.virginatlantic.com/flight-search/book-a-flight");

        return $result;
        $data = $this->searchFetch($selenium, $payload);

        if (!$data) {
            $selenium->http->GetURL("https://www.virginatlantic.com/flight-search/search-results?cacheKeySuffix=" . $this->cacheKey);
            sleep(2);
            $result = $this->searchXMLHttp($selenium, $payload);
            $selenium->http->GetURL("https://www.virginatlantic.com/flight-search/book-a-flight");

            return $result;
        }

        return $data;
    }

    private function searchFetch($selenium, $payload)
    {
        $this->logger->notice(__METHOD__);

        $selenium->driver->executeScript("$('#searchResult').remove()");

        $script = '
            searchResult = null;
            fetch("https://www.virginatlantic.com/shop/ow/search", {
                "credentials": "include",
                "headers": {
                    "Accept": "application/json",
                    "Accept-Language": "en-US,en;q=0.5",
                    "Content-Type": "application/json; charset=utf-8",
                    "X-APP-CHANNEL": "sl-sho",
                    "X-APP-ROUTE": "SL-RSB",
                    "X-APP-REFRESH": "",
                    "CacheKey": "' . $this->cacheKey . '",
                    "Sec-GPC": "1",
                    "Sec-Fetch-Dest": "empty",
                    "Sec-Fetch-Mode": "cors",
                    "Sec-Fetch-Site": "same-origin",
                    "Pragma": "no-cache",
                    "Cache-Control": "no-cache"                },
                "referrer": "https://www.virginatlantic.com/flight-search/search-results?cacheKeySuffix=' . $this->cacheKey . '",
                "body": \'' . $payload . '\',
                "method": "POST",
                "mode": "cors"
            })
                .then( result => {
                    let script = document.createElement("script");
                    let id = "searchResult";
                    script.id = id;
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                    
                });';
        $this->logger->info($script, ['pre' => true]);

        try {
            $selenium->driver->executeScript($script);
            $searchResult = $selenium->waitForElement(\WebDriverBy::xpath('//script[@id="searchResult"]'), 20, false);
            $selenium->saveResponse();

            if (!$searchResult) {
                return null;
            }
            $searchResult = htmlspecialchars_decode($searchResult->getAttribute("searchResult"));
        } catch (\WebDriverException|WebDriverException $e) {
            $this->logger->error($e->getMessage());

            return null;
        }

        $data = $this->http->JsonLog($searchResult, 1);

        if (!$data && trim($searchResult) !== '{}') {
            return $searchResult;
        }

        return null;
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

        try {
            $response = $selenium->driver->executeScript($script);
        } catch (\WebDriverException|\UnexpectedResponseException
        |\WebDriverCurlException|\Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error('WebDriverException: ' . $e->getMessage());
            $this->checkWebDriverException($e->getMessage());
            sleep(2);

            try {
                $response = $selenium->driver->executeScript($script);
            } catch (\WebDriverException|\UnexpectedResponseException
            |\WebDriverCurlException|\Facebook\WebDriver\Exception\WebDriverException $e) {
                throw new CheckRetryNeededException(5, 0);
            }
        }

        if (strpos($response, "{") !== 0) {
            $this->logger->debug($response);
        }

        return $response;
    }

    private function searchXMLHttpFlexDate($selenium, $payload)
    {
        $this->logger->notice(__METHOD__);

//    "body": "{\"isEdocApplied\":false,\"tripType\":\"ONE_WAY\",\"shopType\":\"MILES\",\"priceType\":\"Award\",\"nonstopFlightsOnly\":\"false\",\"bookingPostVerify\":\"RTR_YES\",\"bundled\":\"off\",\"segments\":[{\"origin\":\"TLV\",\"destination\":\"JFK\",\"originCountryCode\":\"IL\",\"destinationCountryCode\":\"US\",\"departureDate\":\"2024-06-19\",\"connectionAirportCode\":null}],\"destinationAirportRadius\":{\"measure\":100,\"unit\":\"MI\"},\"originAirportRadius\":{\"measure\":100,\"unit\":\"MI\"},\"flexAirport\":false,\"flexDate\":true,\"flexDaysWeeks\":\"FLEX_DAYS\",\"passengers\":[{\"count\":1,\"type\":\"ADT\"}],\"meetingEventCode\":\"\",\"bestFare\":\"VSLT\",\"searchByCabin\":true,\"cabinFareClass\":null,\"refundableFlightsOnly\":false,\"deltaOnlySearch\":\"false\",\"initialSearchBy\":{\"fareFamily\":\"VSLT\",\"cabinFareClass\":null,\"meetingEventCode\":\"\",\"refundable\":false,\"flexAirport\":false,\"flexDate\":true,\"flexDaysWeeks\":\"FLEX_DAYS\",\"deepLinkVendorId\":null},\"searchType\":\"flexDateSearch\",\"searchByFareClass\":null,\"pageName\":\"FLEX_DATE\",\"requestPageNum\":\"\",\"action\":\"findFlights\",\"actionType\":\"\",\"priceSchedule\":\"AWARD\",\"schedulePrice\":\"miles\",\"shopWithMiles\":\"on\",\"awardTravel\":\"true\",\"datesFlexible\":true,\"flexCalendar\":false,\"upgradeRequest\":false,\"is_Flex_Search\":true}",

        $script = '
                    var xhttp = new XMLHttpRequest();
                    xhttp.withCredentials = true;
                    xhttp.open("POST", "https://www.virginatlantic.com/shop/ow/flexdatesearch", false);
                    xhttp.setRequestHeader("Accept", "application/json");
                    xhttp.setRequestHeader("Content-type", "application/json; charset=utf-8");
                    xhttp.setRequestHeader("X-APP-CHANNEL", "sl-sho");
                    xhttp.setRequestHeader("X-APP-ROUTE", "SL-RSB");
                    xhttp.setRequestHeader("X-APP-REFRESH", "");
                    xhttp.setRequestHeader("Sec-Fetch-Dest", "empty");
                    xhttp.setRequestHeader("Sec-Fetch-Mode", "cors");
                    xhttp.setRequestHeader("Sec-Fetch-Site", "same-origin");
                    xhttp.setRequestHeader("CacheKey", "' . $this->cacheKey . '");
                    xhttp.setRequestHeader("Referer", "https://www.virginatlantic.com/flight-search/flexible-dates?cacheKeySuffix=' . $this->cacheKey . '");
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

        try {
            $response = $selenium->driver->executeScript($script);
        } catch (\WebDriverException|\UnexpectedResponseException
        |\WebDriverCurlException|\Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error('WebDriverException: ' . $e->getMessage());
            $this->checkWebDriverException($e->getMessage());
            sleep(2);

            try {
                $response = $selenium->driver->executeScript($script);
            } catch (\WebDriverException|\UnexpectedResponseException
            |\WebDriverCurlException|\Facebook\WebDriver\Exception\WebDriverException $e) {
                throw new CheckRetryNeededException(5, 0);
            }
        }

        if (strpos($response, "{") !== 0) {
            $this->logger->debug($response);
        }

        return $response;
    }
}
