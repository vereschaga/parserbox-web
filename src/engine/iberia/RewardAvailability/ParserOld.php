<?php

namespace AwardWallet\Engine\iberia\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use CheckException;
use CheckRetryNeededException;
use UnexpectedJavascriptException;
use WebDriverBy;

class ParserOld extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;

    private const XPATH_LOGOUT = '//span[@class = "nav-account-number"]';
    private const XPATH_ERRORS = '//p[
            contains(normalize-space(),"Sorry, an error has occurred. Please try again later.")
            or contains(normalize-space(),"Lo sentimos, se ha producido un error. ")
            or contains(normalize-space(),"Lamentamos, occorreu um erro. ")
            or contains(normalize-space(),"An error has occurred in the Login.")
        ]
        | //div[@id = "Error_Subtitulo" and contains(text(), "The connection was interrupted due to an error,")]
    ';
    private const ATTEMPT_REQUEST_LIMIT = 94;
    private const PARSED_ROUTES_LIMIT = 40;
    private const REQUEST_TIMEOUT = 10;
    private const MAX_LOGIN_FAILS = 45;

    private const CONFIGS = [
        'chrome-94' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_94,
        ],
        'chrome-pup-103' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME_PUPPETEER,
            'browser-version' => \SeleniumFinderRequest::CHROME_PUPPETEER_103,
        ],
        //        'firefox-100' => [
        //            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
        //            'browser-version' => \SeleniumFinderRequest::FIREFOX_100,
        //        ],
        //        'firefox-playwright-101' => [
        //            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
        //            'browser-version' => \SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_101,
        //        ],
        'firefox-84' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_84,
        ],
    ];
    public $isRewardAvailability = true;

    private $headers = [];
    private $systemErrorFare;
    private $config;
    private $newSession;
    private $fingerprint;
    private $token;

    public static function getRASearchLinks(): array
    {
        return ['https://www.iberia.com/us/search-engine-flights-with-avios/' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->debugMode = $this->AccountFields['DebugState'] ?? false;

//        $this->config = (random_int(0, 1)) ? 'chrome-95' : 'chrome-pup-103';
        $this->config = array_rand(self::CONFIGS);
        $this->logger->info("selected config $this->config");

        $this->http->setHttp2(true);
        $this->KeepState = false;

        if ($this->AccountFields['ParseMode'] === 'awardwallet') {
            $this->setProxyGoProxies(null, 'es');
        } else {
            $this->setProxyNetNut(null, 'es');
        }
        /*        switch (random_int(0, 2)) {
                    case 0:
                        $this->setProxyBrightData(null, 'static', 'fi');

                        break;

                    case 1:

                        break;

                    case 2:
                        $this->setProxyGoProxies(null, "ca");

                        break;
                }*/

        if (strpos(self::CONFIGS[$this->config]['browser-family'], 'firefox') === false) {
            $request = FingerprintRequest::chrome();
        } else {
            $request = FingerprintRequest::firefox();
        }
        $request->browserVersionMin = 100;
        $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
        $this->fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if (isset($this->fingerprint)) {
            $this->http->setUserAgent($this->fingerprint->getUseragent());
        } else {
            if (strpos(self::CONFIGS[$this->config]['browser-family'], 'firefox') === false) {
                $this->http->setRandomUserAgent(null, false, true, false, true, false);
            } else {
                $this->http->setRandomUserAgent(null, true, false, false, true, false);
            }
        }
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

        if ($fields['Adults'] > 9) {
            $this->SetWarning("It's too much travellers");

            return ["routes" => []];
        }

        if (!isset($this->AccountFields['Login'])) {
            throw new CheckException('no account for login', ACCOUNT_ENGINE_ERROR);
        }
        $this->AccountFields['Login'] = trim(preg_replace("/(?:^IB\s*|[^[:print:]\r\n])/ims", "",
            $this->AccountFields['Login']));
        $this->AccountFields['Pass'] = preg_replace("/^[^[:print:]]*/ims", "",
            $this->AccountFields['Pass']); // AccountID: 4185733

        try {
            $data = $this->selenium($fields);
        } catch (\WebDriverException $e) {
            $this->logger->error('WebDriverException: ' . $e->getMessage());

            throw new CheckRetryNeededException(5, 0);
        } catch (\ErrorException $e) {
            if (strpos($e->getMessage(), 'Array to string conversion') !== false
                || strpos($e->getMessage(), 'strlen() expects parameter 1 to be string, array given') !== false
            ) {
                // TODO бага селениума
                throw new \CheckRetryNeededException(5, 0);
            }

            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Exception: ' . $e->getMessage());

            if (strpos($e->getMessage(), 'session not created') !== false) {
                throw new CheckRetryNeededException(5, 0);
            }

            throw $e;
        }

        if (!$data && $this->ErrorCode == ACCOUNT_WARNING) {
            return [];
        }

        if (is_array($data) && isset($data['routes']) && empty($data['routes'])) {
            return $data;
        }

        if (empty($data) || (isset($data->error) && $data->error == 'unauthorized')) {
            throw new CheckRetryNeededException(5, 0);
        }

        if (isset($data->originDestinations, $data->originDestinations->slices)
            && is_array($data->originDestinations->slices)
            && empty($data->originDestinations->slices)
        ) {
            $this->sendNotification('check warning(error) //ZM');

            throw new \CheckException('check warning(error)', ACCOUNT_ENGINE_ERROR);
//            return ["routes" => []];
        }

        if (isset($data->originDestinations) && is_array($data->originDestinations)
            && isset($data->originDestinations[0]) && isset($data->originDestinations[0]->slices)
            && is_array($data->originDestinations[0]->slices)
            && empty($data->originDestinations[0]->slices)
        ) {
            $this->SetWarning("Sorry, we can't show you the flights. For reasons beyond the control of Iberia, we can't show you the flights available at this time");

            return ["routes" => []];
        }

        if (!isset($data->originDestinations)) {
            if (isset($data->errors[0]) && isset($data->errors[0]->reason)
                && strpos($data->errors[0]->reason,
                    'No availability has been found for the selected search.') !== false) {
                $this->SetWarning($data->errors[0]->reason);

                return ["routes" => []];
            }

            throw new \CheckException('response error', ACCOUNT_ENGINE_ERROR);
        }

        $this->logger->notice("IBERIACOM_SSO_ACCESS: {$this->http->getCookieByName('IBERIACOM_SSO_ACCESS', 'www.iberia.com')}");
        $token = $this->http->getCookieByName('IBERIACOM_SSO_ACCESS', 'www.iberia.com');

        return ["routes" => $this->parseRewardFlights($data, $token, $fields)];
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
            || strpos($browser->Error, 'Network error 56 - Received HTTP code 407 from proxy after CONNECT') !== false
            || strpos($browser->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            || strpos($browser->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT') !== false
            || strpos($browser->Error, 'Network error 56 - Recv failure') !== false
            || strpos($browser->Error, 'Network error 0 -') !== false
            || $browser->Response['code'] == 403;
    }

    private function getCabinForSegment(string $cabin)
    {
        $cabins = [
            'TOURIST'        => 'economy', // Economy Class
            'ECONOMY'        => 'economy', // Blue Class
            'PREMIUMTOURIST' => 'premiumEconomy', // Premium Economy
            'BUSINESS'       => 'business', // Business
            'FIRST'          => 'firstClass', // First Class
        ];

        if (isset($cabins[$cabin])) {
            return $cabins[$cabin];
        }
        $this->sendNotification("RA check cabin for segment {$cabin} // MI");

        throw new \CheckException("check cabin for segment {$cabin}", ACCOUNT_ENGINE_ERROR);
    }

    private function getAwardTypeForSegment(string $cabin)
    {
        $cabins = [
            'TOURIST'        => 'Economy Class',
            'ECONOMY'        => 'Blue Class',
            'PREMIUMTOURIST' => 'Premium Economy',
            'BUSINESS'       => 'Business',
            'FIRST'          => 'First Class',
        ];

        if (isset($cabins[$cabin])) {
            return $cabins[$cabin];
        }
        $this->sendNotification("RA check cabin for segment {$cabin} // MI");

        throw new \CheckException("check cabin for segment {$cabin}", ACCOUNT_ENGINE_ERROR);
    }

    private function parseRewardFlights($data, $token, $fields = []): array
    {
        $routes = [];
        $attemptRequest = 0;
        $searchParams = [];

        foreach ($data->originDestinations as $key => $originDestination) {
            foreach ($originDestination->slices as $slice) {
                $this->logger->notice("-- {$slice->sliceId} --");

                foreach ($slice->segments as $i => $segment) {
                    foreach ($segment->offers as $offer) {
                        if ($attemptRequest > self::ATTEMPT_REQUEST_LIMIT && !empty($routes)) {
                            $this->logger->error('Exceeding the limit, stopping');

                            break 3;
                        }

                        if ((time() - $this->requestDateTime) > 105 && count($routes) > 0) {
                            $this->logger->error('Exceeding the limit parsing time, stopping');

                            break 3;
                        }

                        if (count($slice->segments) == 1) {
                            $attemptRequest++;
                            $this->logger->notice("{$offer->bookingClass}");
                            $tmpOffers = [$offer];
                            $selectedOffers = [
                                [
                                    'segmentId' => preg_replace('/[A-Z]$/', '', $offer->offerId),
                                    'offerId'   => $offer->offerId,
                                ],
                            ];
                            $this->logger->notice(var_export($selectedOffers, true));

                            // TODO хотят скипать не кэбины, а партнеров...
                            if ($this->getCabinForSegment($offer->bookingClass) !== $fields['Cabin']) {
                                $this->logger->notice('skip other cabin');
                            }

                            $searchParams[] = [
                                'slice'          => $slice,
                                'selectedOffers' => $selectedOffers,
                                'tmpOffers'      => $tmpOffers,
                            ];
                        } else {
                            for ($j = $i + 1; $j <= count($slice->segments); $j++) {
                                if (isset($slice->segments[$j]->offers)) {
                                    foreach ($slice->segments[$j]->offers as $offer2) {
                                        if (count($slice->segments) == 3) {
                                            for ($k = $j + 1; $k <= count($slice->segments); $k++) {
                                                if (isset($slice->segments[$k]->offers)) {
                                                    foreach ($slice->segments[$k]->offers as $offer3) {
                                                        $attemptRequest++;
                                                        $this->logger->notice("{$offer->bookingClass} -> {$offer2->bookingClass} -> {$offer3->bookingClass}");
                                                        $tmpOffers = [$offer, $offer2, $offer3];
                                                        $selectedOffers = [
                                                            [
                                                                'segmentId' => preg_replace('/[A-Z]$/', '',
                                                                    $offer->offerId),
                                                                'offerId' => $offer->offerId,
                                                            ],
                                                            [
                                                                'segmentId' => preg_replace('/[A-Z]$/', '',
                                                                    $offer2->offerId),
                                                                'offerId' => $offer2->offerId,
                                                            ],
                                                            [
                                                                'segmentId' => preg_replace('/[A-Z]$/', '',
                                                                    $offer3->offerId),
                                                                'offerId' => $offer3->offerId,
                                                            ],
                                                        ];
                                                        $this->logger->notice(var_export($selectedOffers, true));

                                                        if (!in_array($fields['Cabin'],
                                                            [
                                                                $this->getCabinForSegment($offer->bookingClass),
                                                                $this->getCabinForSegment($offer2->bookingClass),
                                                                $this->getCabinForSegment($offer3->bookingClass),
                                                            ])) {
                                                            $this->logger->notice('skip other cabins');
                                                        }

                                                        $searchParams[] = [
                                                            'slice'          => $slice,
                                                            'selectedOffers' => $selectedOffers,
                                                            'tmpOffers'      => $tmpOffers,
                                                        ];
                                                    }
                                                }
                                            }
                                        } else {
                                            $attemptRequest++;
                                            $this->logger->notice("{$offer->bookingClass} -> {$offer2->bookingClass}");
                                            $tmpOffers = [$offer, $offer2];
                                            $selectedOffers = [
                                                [
                                                    'segmentId' => preg_replace('/[A-Z]$/', '', $offer->offerId),
                                                    'offerId'   => $offer->offerId,
                                                ],
                                                [
                                                    'segmentId' => preg_replace('/[A-Z]$/', '', $offer2->offerId),
                                                    'offerId'   => $offer2->offerId,
                                                ],
                                            ];
                                            $this->logger->notice(var_export($selectedOffers, true));

                                            if (!in_array($fields['Cabin'],
                                                [
                                                    $this->getCabinForSegment($offer->bookingClass),
                                                    $this->getCabinForSegment($offer2->bookingClass),
                                                ])) {
                                                $this->logger->notice('skip other cabins');
                                            }

                                            $searchParams[] = [
                                                'slice'          => $slice,
                                                'selectedOffers' => $selectedOffers,
                                                'tmpOffers'      => $tmpOffers,
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $this->logger->notice("");
            }
        }

        $routes = $this->parseRewardFlight($fields, $token, $data, $searchParams);

        if ($this->systemErrorFare && empty($routes)) {
            throw new \CheckException('There has been a system error. Please try again and, if the issue persists, please contact us.', ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->newSession) {
            $this->logger->info("marking config {$this->config} as successful");
            \Cache::getInstance()->set('iberia_config_' . $this->config, 1, 900);
        }

        return $routes;
    }

    private function parseRewardFlight($fields, $token, $data, $searchParams)
    {
        $this->logger->notice(__METHOD__);

        $remainingSeats = [];
        $options = [];
        $routes = [];

        $headers = [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'  => 'application/json; charset=UTF-8',
            'Origin'        => 'https://www.iberia.com',
            'Referer'       => 'https://www.iberia.com/',
        ];

        foreach ($searchParams as $param) {
            foreach ($param['tmpOffers'] as $tmpOffer) {
                $remainingSeats[$tmpOffer->offerId] = $tmpOffer->remainingSeats;
            }
            $postData = [
                'responseId'         => $data->responseId,
                'originDestinations' => [
                    [
                        'originDestinationId' => 'OD0',
                        'selectedSliceId'     => $param['slice']->sliceId,
                        'selectedOffers'      => $param['selectedOffers'],
                    ],
                ],
                'specialNeedQualifierCods' => ["DISA"],
            ];

            $options[] = [
                'method'   => 'POST',
                'sURL'     => 'https://ibisservices.iberia.com/api/sse-rpa/rs/v1/fare',
                'postData' => json_encode($postData),
                'headers'  => $headers,
                'timeout'  => 10,
            ];
        }

        $this->http->sendAsyncRequests($options);

        $allSkipped = true;

        foreach ($this->http->asyncResponses as $response) {
            $this->http->Response = $response;
            $fare = $this->http->JsonLog(null, 1, false);

            if (is_null($fare)) {
                continue;
            }
            $allSkipped = false;

            if ($this->http->Response['code'] != 200) {
                if (isset($fare->errors)
                    && isset($fare->errors[0]) && isset($fare->errors[0]->reason)
                ) {
                    $this->logger->error($fare->errors[0]->reason);

                    if (strpos($fare->errors[0]->reason,
                            'There has been a system error. Please try again and, if the issue persists, please contact us.') !== false
                        || strpos($fare->errors[0]->reason,
                            'There has been a communication error with the scoring system. Please try again and, if the problem persists, please contact us') !== false
                        || strpos($fare->errors[0]->reason,
                            'We are having problems with your request. Please try again.') !== false) {
                        $this->systemErrorFare = true;
                    }
                }

                continue;
            }
            $offer = $fare->offers[0];

            // 1 or 2 flights
            if (isset($offer->redemptionOptions)) {
                usort($offer->redemptionOptions, function ($item1, $item2) {
                    return $item1->optionId <=> $item2->optionId;
                });
                $option = $offer->redemptionOptions[0];
                $price = $offer->price;
            } // 3 flights
            elseif (isset($offer->promotion->discountedRedemptionOptions[0])) {
                usort($offer->promotion->discountedRedemptionOptions, function ($item1, $item2) {
                    return $item1->optionId <=> $item2->optionId;
                });
                $option = $offer->promotion->discountedRedemptionOptions[0]->discountedPrice;
                $price = $offer->price;
            } else {
                $this->logger->error('Empty offers->redemptionOptions');

                return false;
            }

            $route = [
                'times'       => ['flight' => null, 'layover' => null],
                'redemptions' => [
                    'miles'   => round($option->totalPoints->fare / $fields['Adults']),
                    'program' => $this->AccountFields['ProviderCode'],
                ],
                'payments' => [
                    'currency' => $option->totalCash->currency,
                    'taxes'    => round(($option->totalCash->fare + $price->total) / $fields['Adults'], 2),
                    'fees'     => null,
                ],
            ];
            $classOfServiceArray = [];
            $slice = $offer->slices[0];

            foreach ($slice->segments as $segment) {
                $seatsKey = $segment->id . $segment->cabin->rbd;
                $classOfService = $this->getAwardTypeForSegment($segment->cabin->bookingClass);

                if (preg_match('/^(.+\w+) class$/i', $classOfService, $m)) {
                    $classOfService = $m[1];
                }
                $classOfServiceArray[] = $classOfService;
                $flNum = $segment->flight->marketingFlightNumber ?? $segment->flight->operationalFlightNumber;
                $route['connections'][] = [
                    'departure' => [
                        'date'     => date('Y-m-d H:i', strtotime($segment->departureDateTime)),
                        'dateTime' => strtotime($segment->departureDateTime),
                        'airport'  => $segment->departure->airport->code,
                        'terminal' => $segment->departure->terminal ?? null,
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', strtotime($segment->arrivalDateTime)),
                        'dateTime' => strtotime($segment->arrivalDateTime),
                        'airport'  => $segment->arrival->airport->code,
                        'terminal' => $segment->arrival->terminal ?? null,
                    ],
                    'meal'           => null,
                    'tickets'        => $remainingSeats[$seatsKey],
                    'cabin'          => $this->getCabinForSegment($segment->cabin->bookingClass),
                    'classOfService' => $classOfService,
                    'fare_class'     => $segment->cabin->bookingCode,
                    'award_type'     => $this->getAwardTypeForSegment($segment->cabin->bookingClass),
                    'flight'         => ["{$segment->flight->marketingCarrier->code}{$flNum}"],
                    'airline'        => $segment->flight->marketingCarrier->code,
                    'operator'       => $segment->flight->operationalCarrier->code,
                    'distance'       => null,
                    'aircraft'       => $segment->flight->aircraft->description,
                    'times'          => [
                        'flight'  => null,
                        'layover' => null,
                    ],
                ];
            }
            $classOfServiceArray = array_values(array_unique($classOfServiceArray));

            if (count($classOfServiceArray) === 1) {
                $route['classOfService'] = $classOfServiceArray[0];
            }
            $route['num_stops'] = count($route['connections']) - 1;

            $routes[] = $route;
        }

        if ($allSkipped) {
            $this->logger->error('sendAsyncRequests failed');

            throw new \CheckRetryNeededException(5, 0);
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

            $selenium->seleniumRequest->request(self::CONFIGS[$this->config]['browser-family'],
                self::CONFIGS[$this->config]['browser-version']);

            if ($this->fingerprint) {
                $selenium->seleniumOptions->userAgent = $this->fingerprint->getUseragent();
                $selenium->http->setUserAgent($this->fingerprint->getUseragent());
            }

            $selenium->usePacFile(false);
            $selenium->keepCookies(false);

            $selenium->seleniumRequest->setHotSessionPool(self::class, $this->AccountFields['ProviderCode']);
            $selenium->http->saveScreenshots = true;
            $selenium->disableImages();

            if ($this->config !== 'firefox-84') {
                $selenium->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
            }

            $selenium->http->start();
            $selenium->Start();
            /** @var \SeleniumDriver $seleniumDriver */
            $seleniumDriver = $selenium->http->driver;
            $this->newSession = $seleniumDriver->isNewSession();
            $oldToken = null;
            $selenium->driver->manage()->window()->maximize();

            $currentUrl = $this->http->currentUrl();
            $this->logger->error('Current url - ' . $currentUrl);

            if (strpos($currentUrl, '/search-engine-flights-with-avios/') === false) {
                try {
                    $selenium->http->GetURL('https://www.iberia.com/us/');
                } catch (UnexpectedJavascriptException $e) {
                    $this->logger->error("UnexpectedJavascriptException exception on saveResponse: " . $e->getMessage());
                } catch (\ScriptTimeoutException | \TimeOutException $e) {
                    $this->logger->error("TimeOutException exception: " . $e->getMessage());
                }

                if ($cookie = $selenium->waitForElement(WebDriverBy::xpath('//button[@id=\'onetrust-accept-btn-handler\']'),
                    5)) {
                    sleep(1);
                    $cookie->click();
                }

                $this->clickSearchForm($selenium);
            }

            try {
                $selenium->http->GetURL('https://www.iberia.com/us/search-engine-flights-with-avios/');
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("UnexpectedJavascriptException exception on saveResponse: " . $e->getMessage());
            } catch (\ScriptTimeoutException | \TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
            }

            $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

            if (!$sensorPostUrl) {
                $this->logger->error("sensor_data URL not found");
            }
            $this->http->NormalizeURL($sensorPostUrl);
            $this->logger->debug($sensorPostUrl);

            $this->checkPage($selenium);

            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] === 'IBERIACOM_SSO_ACCESS') {
                    $oldToken = $cookie['value'];
                    $this->logger->debug(var_export($cookie, true), ['pre' => true]);
                }
            }

            $currentUrl = $this->http->currentUrl();
            $this->logger->error('Current url - ' . $currentUrl);

            if (!isset($oldToken) || strpos($currentUrl, 'login.iberia.com') !== false) {
                $selenium->driver->manage()->deleteAllCookies();

                if (!$this->interceptSubmitCredentials($selenium)) {
                    $currentUrl = $this->http->currentUrl();
                    $this->logger->error('Current url - ' . $currentUrl);
                    $this->interceptSubmitCredentials($selenium);
                }
            }

            if ($elem = $selenium->waitForElement(\WebDriverBy::xpath('//h2[contains(normalize-space(),"Access to the Iberia Plus personal area is currently blocked for this user.")]'),
                0)) {
                $this->logger->error($elem->getText());
                $this->sendNotification('ACCOUNT_LOCKOUT // MI');

                throw new \CheckException($elem->getText(), ACCOUNT_LOCKOUT);
            }

            if ($elem = $selenium->waitForElement(\WebDriverBy::xpath('//p[contains(normalize-space(),"Contraseña, debe tener una longitud de 6 caracteres")]'),
                0)) {
                $this->logger->error($elem->getText());

                throw new \CheckException($elem->getText(), ACCOUNT_INVALID_PASSWORD);
            }

            if ($elem = $selenium->waitForElement(\WebDriverBy::xpath(self::XPATH_ERRORS), 0)) {
                $this->logger->error($elem->getText());

                if (strpos($elem->getText(), 'The connection was interrupted due to an error') !== false) {
                    $this->markProxyAsInvalid();
                    $selenium->keepSession(false);
                    $this->markConfigAsBad();

                    throw new \CheckRetryNeededException(5, 0);
                }

                if ($this->isBlockedMessage($elem->getText())) {
                    $this->markConfigAsBad();
                }

                throw new \CheckRetryNeededException(5, 0);
            }

            if ($cookie = $selenium->waitForElement(WebDriverBy::xpath('//button[@id=\'onetrust-accept-btn-handler\']'),
                5)) {
                $cookie->click();
                sleep(1);
            }
            $this->savePageToLogs($selenium);

            $selenium->waitForElement(\WebDriverBy::xpath("//h1[contains(text(),'Fly with Avios')]"), 20);

            $this->savePageToLogs($selenium);

            //-->  check valid route
            $this->logger->debug('brotherBrowser for check valid route');
            $browser = clone $this;
            $browser->http->setHttp2(true);
            $this->http->brotherBrowser($browser->http);
//            $browser->http->setUserAgent("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/604.5.6 (KHTML, like Gecko) Version/11.0.3 Safari/604.5.6");

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] === 'IBERIACOM_SSO_ACCESS') {
                    $token = $cookie['value'];
                }
                $browser->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }

            if (isset($token)) {
                $this->logger->debug('Has an authorization token');

                try {
                    if (is_array($checkRoute = $this->checkValidRouteAjax($fields, $selenium, $token))) {
                        return $checkRoute;
                    }
                } catch (\WebDriverException | \WebDriverCurlException $e) {
                    // рестартить не надо. отрабатывает далее без проблем. даже на горячем при сохранении сессии дальше ок
                    $this->logger->error("WebDriverException: " . $e->getMessage());
                }
                $this->logger->notice('Logged in, saving session');
                $selenium->keepSession(true);

                $headers = [
                    'Authorization' => "Bearer {$token}",
                    'Accept'        => 'application/json, text/plain, */*',
                    'Content-Type'  => 'application/json;charset=UTF-8',
                    'Origin'        => 'https://www.iberia.com',
                    'Referer'       => 'https://www.iberia.com/',
                    'Connection'    => 'keep-alive',
                ];

                $dateDep = date('Y-m-d', $fields['DepDate']);
                $data = '{"slices":[{"origin":"' . $fields['DepCode'] . '","destination":"' . $fields['ArrCode'] . '","date":"' . $dateDep . '"}],"passengers":[{"passengerType":"ADULT","count":' . $fields['Adults'] . '}],"marketCode":"US","preferredCabin":""}';
                /*                $browser->http->RetryCount = 0;
                                $browser->http->PostURL('https://ibisservices.iberia.com/api/sse-rpa/rs/v1/availability', $data, $headers, 10);

                                if ($this->isBadProxy($browser->http)
                                    || strpos($browser->http->Error, 'Network error 28 - Operation timed out after') !== false
                                ) {
                                    $browser->http->PostURL('https://ibisservices.iberia.com/api/sse-rpa/rs/v1/availability', $data,
                                        $headers, 10);
                                }
                                $browser->http->RetryCount = 2;

                                if ($this->isBadProxy($browser->http)
                                    || strpos($browser->http->Error, 'Network error 28 - Operation timed out after') !== false
                                    || $browser->http->Response['code'] == 500
                                ) {
                                    //  TODO: пока без ресета
                                    $this->markProxyAsInvalid();

                                    throw new CheckRetryNeededException(5, 0);
                                }
                                $responseData = $browser->http->JsonLog(null, 3, false, 'slices');*/
//                if (!$responseData){
                $resData = $this->getXHR($selenium, 'POST',
                    'https://ibisservices.iberia.com/api/sse-rpa/rs/v1/availability', $headers, $data);
                $responseData = $browser->http->JsonLog($resData, 3, false, 'slices');

                $day = date('d', $fields['DepDate']);
                $month = date('Ym', $fields['DepDate']);
                $year = date('Y', $fields['DepDate']);
                $selenium->http->GetURL("https://www.iberia.com/flights/?market=US&language=en&appliesOMB=false&splitEndCity=false&initializedOMB=true&flexible=true&TRIP_TYPE=1&BEGIN_CITY_01={$fields['DepCode']}&END_CITY_01={$fields['ArrCode']}&BEGIN_DAY_01={$day}&BEGIN_MONTH_01={$month}&BEGIN_YEAR_01={$year}&FARE_TYPE=R&quadrigam=IBHMPA&ADT={$fields['Adults']}&CHD=0&INF=0&boton=Search&bookingMarket=US&pagoAvios=true#!/availability");

                sleep(5);
                $this->savePageToLogs($selenium);

//                }
            } else {
                $this->logger->debug('No authorization token');

                if ($message = $selenium->waitForElement(WebDriverBy::xpath('//p[contains(@class, "paragraph__regular--modal-claim")]'),
                    3)) {
                    $this->savePageToLogs($selenium);
                    $this->logger->error($message->getText());

                    if ($this->isBlockedMessage($message->getText())) {
                        $this->markConfigAsBad();

                        throw new CheckRetryNeededException(2, 0, $message->getText(), ACCOUNT_PROVIDER_ERROR);
                    }
                }
                $this->savePageToLogs($selenium);

                if ($this->http->FindSingleNode("//div[contains(@class,'overlay')][contains(.,'Loading...')]")) {
                    $this->logger->notice('still Loading...');

                    throw new CheckRetryNeededException(5, 0);
                }

                return false;
            }

//            if (is_null($responseData) && $browser->http->Response['code'] == '204') {
            if ($resData == 204 || $resData == 204) {
                $responseData = null;
                $this->SetWarning("We can't find any exclusive Iberia Plus seats for the selected date and destination.");
            }
        } catch (\ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        } catch (\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error($e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'New session attempts retry count exceeded') === false) {
                throw $e;
            }
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "New session attempts retry count exceeded";

            throw new \CheckRetryNeededException(5, 0);
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return $responseData;
    }

    // TODO remove it
    private function savePageToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }

    private function getXHR($selenium, $method, $url, array $headers, $payload)
    {
        $this->logger->notice(__METHOD__);
        $headersString = "";

        foreach ($headers as $key => $value) {
            $headersString .= 'xhttp.setRequestHeader("' . $key . '", "' . $value . '");
        ';
        }

        if (is_array($payload)) {
            $payload = json_encode($payload);
        }
        $script = '
                var xhttp = new XMLHttpRequest();
                xhttp.withCredentials = true;
                xhttp.open("' . $method . '", "' . $url . '", false);
                ' . $headersString . '
                var data = JSON.stringify(' . $payload . ');
                var responseText = null;
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && (this.status == 200 || this.status == 202 || this.status == 204 || this.status == 500)) {
                        if (this.status == 204 || this.status == 500)
                            responseText = 204;
                        else
                            responseText = this.responseText;
                    }
                };
                xhttp.send(data);
                return responseText;
            ';
        $this->logger->debug("[run script]:");
        $this->logger->debug($script, ['pre' => true]);

        return $selenium->driver->executeScript($script);
    }

    private function interceptSubmitCredentials($selenium)
    {
        $this->logger->notice(__METHOD__);

        /*        if (!strpos($selenium->http->currentUrl(), 'login.iberia.com/IDY_LoginPage')) {
                    try {
                        $selenium->http->GetURL('https://www.iberia.com/us/search-engine-flights-with-avios/');
                    } catch (UnexpectedJavascriptException $e) {
                        $this->logger->error("UnexpectedJavascriptException exception on saveResponse: " . $e->getMessage());
                        $this->savePageToLogs($selenium);
                    } catch (\ScriptTimeoutException | \TimeOutException $e) {
                        $this->logger->error("TimeOutException exception: " . $e->getMessage());

                        try {
                            $selenium->http->GetURL('https://www.iberia.com/us/search-engine-flights-with-avios/');
                        } catch (UnexpectedJavascriptException $e) {
                            $this->logger->error("UnexpectedJavascriptException exception on saveResponse: " . $e->getMessage());
                            $this->savePageToLogs($selenium);
                        } catch (\TimeOutException $e) {
                            $this->logger->error("TimeOutException exception: " . $e->getMessage());
                            $selenium->http->GetURL('https://www.iberia.com/us/search-engine-flights-with-avios/');
                        }
                    } catch (\Facebook\WebDriver\Exception\UnknownErrorException | \Facebook\WebDriver\Exception\WebDriverException $e) {
                        $this->logger->error("UnknownErrorException exception: " . $e->getMessage());

                        throw new \CheckRetryNeededException(5, 0);
                    }
                }*/

        $this->checkPage($selenium);

        $this->waitFor(function () use ($selenium) {
            return $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "iberia-plus" or @id = "loginPage:theForm:loginEmailInput"]'),
                0);
        }, 10);

        if (strpos($selenium->http->currentUrl(), 'login.iberia.com/IDY_LoginPage') !== false) {
            return $this->interceptSubmitCredentials2($selenium);
        }

        throw new CheckException('Change login page, check it!', ACCOUNT_ENGINE_ERROR);
    }

    private function checkPage($selenium)
    {
        if ($selenium->waitForElement(\WebDriverBy::xpath("
                //span[contains(text(), 'This site can’t be reached')]
                | //h1[normalize-space()='Access Denied']
                | //div[@id = 'Error_Subtitulo' and contains(text(), 'The connection was interrupted due to an error,')]
            "), 0)) {
            $this->markProxyAsInvalid();

            throw new \CheckRetryNeededException(5, 0);
        }
    }

    private function interceptSubmitCredentials2($selenium): bool
    {
        $this->logger->notice(__METHOD__);

        if ($cookie = $selenium->waitForElement(WebDriverBy::xpath('//button[@id=\'onetrust-accept-btn-handler\']'),
            5)) {
            sleep(1);
            $cookie->click();
        }

        $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id=\'loginPage:theForm:loginEmailInput\']'),
            0);
        $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id=\'loginPage:theForm:loginPasswordInput\']'),
            0);
        $button = $selenium->waitForElement(WebDriverBy::xpath('//input[@id=\'loginPage:theForm:loginSubmit\']'), 0);
        $this->savePageToLogs($selenium);

        if (!$loginInput || !$passwordInput || !$button) {
            $this->logger->error('something went wrong');
            $this->savePageToLogs($selenium);

            throw new CheckRetryNeededException(5, 0);
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $this->someSleep();
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->someSleep();
        $this->logger->debug('submit login');
        $button->click();

        $this->logger->debug('need delay');
        $block = $selenium->waitForElement(WebDriverBy::xpath('//div[@id=\'userErrorController\']/label'), 5);

        $this->savePageToLogs($selenium);

        if ($block) {
            $this->savePageToLogs($selenium);

            if (strpos($block->getText(), 'email might not be registered') !== false) {
                throw new \CheckException($block->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            if (strpos($block->getText(), 'Plus number does not correspond') !== false) {
                \Cache::getInstance()->set('iberia_login_' . $this->AccountFields['Login'], null, 1);

                throw new \CheckException($block->getText(), ACCOUNT_LOCKOUT);
            }

            $this->sendNotification($block->getText() . ' //ND');

            throw new \CheckException($block->getText(), ACCOUNT_PREVENT_LOCKOUT);
        }

        $this->checkPage($selenium);

        $result = $selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Fly with Avios")] | ' . self::XPATH_ERRORS),
            35);

        if (!$result) {
            $selenium->http->GetURL('https://www.iberia.com/us/search-engine-flights-with-avios/');
        }

        $this->savePageToLogs($selenium);

        return !($selenium->http->currentUrl() !== 'https://www.iberia.com/us/search-engine-flights-with-avios/');
    }

    private function hasCode($data, $code)
    {
        foreach ($data as $row) {
            foreach ($row->cities as $city) {
                if ($city->code === $code) {
                    return true;
                }
            }
        }

        return false;
    }

    private function checkValidRouteAjax($fields, $selenium, $token): ?array
    {
        $this->logger->notice(__METHOD__);

        try {
            $script = '
                var xhttp = new XMLHttpRequest();
                xhttp.open("GET", "https://ibisservices.iberia.com/api/rdu-loc/rs/loc/v1/location/areas/origin/", false);
                xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
                xhttp.setRequestHeader("Origin", "https://www.iberia.com");
                xhttp.setRequestHeader("Referer", "https://www.iberia.com/");
                xhttp.setRequestHeader("Connection", "keep-alive");
                xhttp.setRequestHeader("Authorization", "Bearer ' . $token . '");
    
                var responseText = null;
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        responseText = this.responseText;
                    }
                };
                xhttp.send();
                return responseText;
            ';

            $returnData = $selenium->driver->executeScript($tt = $script);

            $this->logger->debug($tt, ['pre' => true]);

            $origin = $selenium->http->JsonLog($returnData, 1);

            if (is_array($origin) && !$this->hasCode($origin, $fields['DepCode'])) {
                $this->SetWarning('no flights from ' . $fields['DepCode']);

                return ['routes' => []];
            }

            $returnData = $selenium->driver->executeScript($tt =
                '
                        var xhttp = new XMLHttpRequest();
                        xhttp.open("GET", "https://ibisservices.iberia.com/api/rdu-loc/rs/loc/v1/location/areas/destination/", false);
                        xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
                        xhttp.setRequestHeader("Origin", "https://www.iberia.com");
                        xhttp.setRequestHeader("Referer", "https://www.iberia.com/");
                        xhttp.setRequestHeader("Connection", "keep-alive");
                        xhttp.setRequestHeader("Authorization", "Bearer ' . $token . '");
            
                        var responseText = null;
                        xhttp.onreadystatechange = function() {
                            if (this.readyState == 4 && this.status == 200) {
                                responseText = this.responseText;
                            }
                        };
                        xhttp.send();
                        return responseText;
            '
            );

            $this->logger->debug($tt, ['pre' => true]);

            $destination = $selenium->http->JsonLog($returnData, 1);

            if (is_array($destination) && !$this->hasCode($destination, $fields['ArrCode'])) {
                $this->SetWarning('no flights to ' . $fields['ArrCode']);

                return ['routes' => []];
            }

            return null;
        } catch (\Facebook\WebDriver\Exception\InvalidSelectorException | \Facebook\WebDriver\Exception\ScriptTimeoutException | \Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            throw new \CheckRetryNeededException(5, 0);
        }
    }

    private function markConfigAsBad(): void
    {
        $this->logger->info("marking config {$this->config} as bad");
        \Cache::getInstance()->set('iberia_config_' . $this->config, 0, 900);
    }

    private function isBlockedMessage(string $message): bool
    {
        return strpos($message, 'Sorry, an error has occurred. Please try again later.') !== false
            || strpos($message, 'Lo sentimos, se ha producido un error. ') !== false
            || strpos($message, 'Lamentamos, occorreu um erro. ') !== false;
    }

    private function someSleep()
    {
        usleep(random_int(70, 250) * 10000);
    }

    private function clickSearchForm(self $selenium)
    {
        $this->logger->notice(__METHOD__);

        $miles = $selenium->waitForElement(\WebDriverBy::xpath("//label[@for=\"paywithAvios\"]"), 5);

        if (!$miles) {
            $selenium->saveResponse();

            throw new \CheckRetryNeededException(5, 0);
        }

        $inputs = [
            'from'       => $selenium->waitForElement(\WebDriverBy::xpath("//input[@id=\"flight_origin1\"]/.."), 5),
            'to'         => $selenium->waitForElement(\WebDriverBy::xpath("//input[@id=\"flight_destiny1\"]/.."), 0),
            'date1'      => $selenium->waitForElement(\WebDriverBy::xpath("//label[@for=\"flight_round_date1\"]"), 0),
            'date2'      => $selenium->waitForElement(\WebDriverBy::xpath("//label[@for=\"flight_return_date1\"]"), 0),
            'miles'      => $miles,
            'passengers' => $selenium->waitForElement(\WebDriverBy::xpath("//button[@id=\"flight_passengers1\"]"), 0),
        ];

        $cntClicks = random_int(3, count($inputs));
        $this->logger->info('Random cnt clicks: ' . $cntClicks);

        while ($cntClicks) {
            $key = array_keys($inputs)[random_int(0, count($inputs) - 1)];
            $this->logger->info('Try to click on: ' . $key);
            $this->clickTextField($inputs[$key]);
            unset($inputs[$key]);
            $cntClicks--;
        }
    }

    private function clickTextField($input)
    {
        if ($input) {
            $input->click();
            $this->someSleep();
            $input->click();
            $this->someSleep();
        }
    }

    private function sendSensorData($sensorDataUrl)
    {
        $this->logger->notice(__METHOD__);

        $sensorData = [
            '3;0;1;0;3228228;3TRNW/vVCSdAS79nkW3sLI7FDzSUv094z9V6VKzIYQk=;14,29,0,0,4,504;\"g[P_}\"1;c\"<A \"q\"^o_;Ui<YS\"R0`\"c[~\"$\"@|WSB>7\"q\"+o|\"e\"0WsJ80ZAyF6XmG4[\"+\"2+D\"fBB nxG0\"2M\"-;:\"_=)\"F`VMt(}E\"70C\"lkG5U5+j7M\"UL3\"1}XvKce(\"r`MVSaiwp(_`2VXV{G2^eKQ-2oV+?PGzJD)LA_]!x,sj>prtDqaBK36#3s_\"]\"B)Y\"mz-GHpe#b\"1Y\"F{E5pyk\"dVB\">g~(`#<$\"@ek\"pyx]5{91h2>}-K68?N?lb-q?*38Bb&p^|\"b\"/^)\"I\":HHYN\",,$\"4YpIV\"l4Az2Mx)\"!ja#\"J}xVi=\"^wovaMX!w_ln6O/T Ln}\"o\"T6r\"6/7CY\"|g{\"!\"ePMZoHV8^<b,;Y\"3gs\"VQ\"JTi.9\"6:+\"?/(-5sY\"k\"U=?\"HNs\"i\"km2O~9Pf#j`3g^gvAp@-eApS^$2-U/+y@]\"2\"h-V\",}U+3wNh\"`E-~\"U\"o~wZU\"Y\"wSf\"_3A\"$~a~1)-:m^C\"e(x\"C\"\"s]`\"x.s\"5R**]N3\"},g93(4.jgB++/8R0EO%_@~If2y+df{`BYrwEsqS6g|h<=(=$x.Ym1it.Xuf<EeJ\"@-9|\"X\"?I#\"E#1\"wauxa2yZ@eUt\"]i_Pr5y\" \"oekP\"&\"m2>\"p\"\"*\"#-a\"T(IT^:\"k`KC\"ubh]Z\"~A_\"*:F5^\",xpZ\"H]~X^Q\"%<+G}{5S;9m-?NHx?{\"Q\"pgj\"Yj6w:w\"=}3&MOcgw\"C$K\"UvB\";;%-hiK\"X{4\"7d\"=`V\"o4=\"i\"\"_\"_#N\"edK_J\"zl\"FJeCsdko\"@Fo\"Z\"M{a#)h-1|,v4$;&,\"5\"7%X\"$\"Nw7Sk587lPq$W?=(BIaVC:_H\".\"gRT\"P\"\"J\"r7\"n\"@co476A{UhJb/XD.rd$gI*Ob=5DV#3?.ucOI3f5Q+R.ymLkx~li+jHHjtmH1^q[!\"Y3o\"PW*\"#16JW\"*Kw\"1\"\"(\"o?%\"N%]\"=c=8\"lk#LtnKI\"G+\"cPTq+U2f\"l1q\"(\"\"7ve\"imP\"cw/LY\"5kM\"|5L!K,= T/UwmvF\"m[1\"D\"8T)G/:EJw!99cKi;N7tSMSVk9J9?pzkcKu=,NSd.|0rLkLX%XjF,%N`\"N\"w(N\")A4N1:rs\"e4[c\"= Z7~66%`1E]A9>Kz?%Y4oI\"!*U\"S]eTr\">F]\"W\"\"[\"lON\"IA \"2$Z\"Bb\"&d`\"h\"#siq`fVR?uA^P\"W\"6nT\"W\">+rsB6xqC(fYI 0HC<)l@Y,jI5!J>5[THDPscDYCd$3E`MJUrK2qC_k^u^P>I/.drI#TD=Hu@qULBmc>X5wqE=5XU|pPEXfUT9+O%#5U]bUgy/^2-A/2k\"8Rr\" `r\"7&}uU\"N^tG\"L\"\"X\"_1/\"}\"O\"5Ks\"9!$\"eWfCC$Dij\"t}|1l\"V\",I\"5*e\"~V-6\"f\"rr{rnL9L\":!)\"HI{\"A\"mtN:vfa; zzWj)|{m|1.o=!A|^TJFC/+}^6[plPx%\"}QC\"b-x\"C\"Gl\"5^_\"Eg8y\"0>HE/\"u^d\"x\"\"j\"+~A\"9%u\"Po0\"F$@2A\",7.\"/K,#.\"RY\"o\"F\"3mi\"u<\"#\"kZ$t<~3Z\"W\"C0~\"~\"\"+\"e^ \"CmTR4iz\"#cO\"/\"\"J\"3oG\"m{?f$PJS\"Zbg\"KRL|B3bX1uZ4\"u )^\"v\"E*h(i+wbH@n=%b.e)onx5%VwMNWlS&R)u!4P6-.Hi~I\"`$4\"7pZ\"k\";(a:FkI3Id+J&]M|V%a`qgMk&[2(trahRCyVtF{i{7j?#XC&tBo#F0)@X<*r[adiUI(p9UHAVtSyk=@$m<}6VF5e)oXVJLQYH[)&Ln]W}7&IF. mg<u;aX_~BE\"&\"en-\"I*y,txv\"UD%xdhDM$k9ba`*bKCDMiY}y4xo6povKs^6E*8${_]yENU2daWYq$1GZt_R+oewN{bTPMXGMD3Q/<M1]edhq7Jms.-3bBXu?qmU_UjkmaT*dx*uQcSsyR_16[b]=$4O(]dNcb m*(}S7PoY.>=mnEa7LqxvhSb(eQW>SX|v;%-pU(8)p).RxBf>Lq}3!p2T={2-KLq%4;6}#\"T\"),@\"]/Jo.K.\",\"?fL\"6Bd\"g\"\"y;|\"vN6\"&5`}_|n,\"_,ii\"4\" el(=f5$U_(r?7QQ_,#[{;/%gLodb1~MYjGf!tY01Ka\"4th\")Gq\"[<(R([{w(`,*$Nd!Hz\"jg!\"S\"`29Y*Dd@]gKgGUe<}H)inu?{LKc}d};g6].6-bq0!~t+\"|\"rc@\"v:]\"L~e\"{\"H\"&w \"e2t\"%\"8)~I d\"=%P P\"wZ?\"5h3\"~`Gfi\"xrT[\"qC;a3}\"v\"e0N\"O$#\"q/hy:qdu\"_NM,\"W\"\",Gb\"1qh\"l\"\"H4v\"+[O\"C\"(Tt`\"1\"gd$\";\"_%IK\"RsC\"]Rj\"',
            '3;0;1;2048;3228228;3TRNW/vVCSdAS79nkW3sLI7FDzSUv094z9V6VKzIYQk=;24,0,0,1,3,0;#\"mRf\"(A/qg\"Ax#E\"d\"Y\"Di)\"%G6\"|\"$+\"}\"^xe\"Z\"6-\"em=\"+83|\"!pa\"yYG\"y\"e0{]\"7+Q\"-KR\"JE q,TJ~RT\"aO2\"#h?R=iR9p%\"8.:\"i\"\"c\"n,r\"&D`.qW\"7`V=t@q\"(T^Y2\"!4K\"u%RTt\">4Z%\"l[OR56w^o?=\"P$D\"MEY9T)\"tk\"_rf\"U=w1\"D\"IN3bB\"WFb\"C[a\"Oyu(dnA\"upV\"?\"\"m|h\">Gg\"&&h8037\"ekB\"&\"s\"#7C\":Cx\"Wb_eRWv_i\"I|\"Z\"3\"_\"~==\"V)<,)OWw\"s5(=,7oWp4tUgg5Fu/i|77M1$9O<$ #29YTjfxW@Q}jZ_k0N1S{?fw-tc~[9v)+CGhv67B(v9fWGTgA^6U0b$8NK?l0{rzzDV$.KVcP>l1/(LO6X=n<gJR|eeJ\"Z\">Mu\"*\"W0E/-1S*y@x!h7m%PzekMJ`DCicw`6s/BwN(EktFStWa\"j\"xJ2\"F>2v9\"Bc2\"$O}QZrtk\"$8/\"D\"\"Q\"g(o\"C\"\" \"HL%\":\"\"A*<\"_l<\"a\"8[N/ j]9~Z1/H,t} UwMrY3p+_bs[DZiKpk;K=4o`6d#0}o(+j,^C,`EX7M`?+QYInlTYTR6&lQ^%i`&0[.pN:qY$b 4+lCZ8wqYyekLY&v.ZQDo&S*uTD1sJ0\"a\"5N}\"m\"\"g\"Z8Q\"D[&n~;a:lL2v4|C:J\"eS\"w\"\"I\";7Q\"&^W#P;=a\"Q04\"F.ya=\"~q\"wi&\"dh&\"lk_^A\"=>\"A]?-}\"jOU<\"yGK@`m~;\"FTn1\"v\"]\"R=x\"Zu\"n]gaY-5Y\"#8%\"~tq-G\"me1\"W\"`\"u1~>&0y!$,\"h,q\"_\"\"y\"m0R\"b\"\",\"R;T\"e?=Xq(cZd=Cp(2IqR^6\"9w%^C\"bbIIeta96<QVo\"y(r\"?C\"*\"v}K\"4\"x%a\"F*t1RK}/|\"3Nh\"CD*t6\"8*EX\"pAe+7]IXM(S~ MIV(+J/y0IJ,J3=cp;c:c3tny8\"h\"n,B\"lk%4hl\"B>M4\"cWVs+n?xT9^6XyviCh\"YVo\"+\"7~=GD=kenp5B20+fY3r3UW(JSmo%<,p^-w[|P+1|y]S%5;)Ydo_~D<Z6Zu!o c%e3lpDkqP8A*YbP+$wE8*U3Weft%&Xg{A.N,Jt(7~N9W<dc:bRR3![4\"K6|\"wP \"cEz_~K[wZN[uZ(od^\"A!1\"jn(ck#I\"\"z\"$WY\"~-.+i\"1+k\"sxbYRD\"OvJ/\"f}C-a\"4gg(\"D\"YGrA*ghX\"LoU\"$e.\"+\"0P<o`?>}tN1Kq\"d\"F3q\";\"oP>imY};$5b*lF\"(\"<J#\"g\"\"qSo\"<MS\"8MEbD%3@\"zRw\"{nIa@\"}q$b\"?d5\"dk}\"i\"b~uoJCIV0[N- {x\"j_Ltj\"8j\"2-A\"gj;\")si#bOSuXA\"1$A\"lA0|B\"b<}\"D\"=4(EbKx _OYqS2#,_)rtwpuGo@BEc6taHE)2teHn8>\"uk<\"-97\"(\"47\"^\"kg>\"-]lMm5W.0+}c\"2,mh\"`V}^+w^\"Y,<0%^Gs2i@5ETr9\"=\"dLA\"x\"\"j\"/}0\"9Ww~e\"}W$a\"@\"\"B\"68i\"F\"$.iIx\"~BC\"qRw\"=Wg@[\"lRu\"A\"\"W\"<2\"k\"e<Y47\":@&\")4)E0\"D*UPp\"d4{\"9\"\"_\"qKX\"([S x\"J(\"V\"\".\"%k]\"',
            '3;0;1;0;3228228;3TRNW/vVCSdAS79nkW3sLI7FDzSUv094z9V6VKzIYQk=;13,5,0,0,5,935;#\"mRf\"(A/qg\"Ax#E\"d\"Y\"Di)\"%G6\"|\"},;&\")\"S35\"C\"7v\"?-4\"?o2X\"}% \"I}T\"g\"~d!G\"SU@\",HA\"*o-QC:_VAn\"-!]\"Mw8:SSt(}E\"</=\"l\"\"+\"kWc\"j?vm=Mr\"B|;lWY:\"hDBHN\"L>k\"c)AT<\"%rHD\"S-zjB\"w%I\"XB$yxh\"|}\"gf0\"VPyZ\"P\"NoZmX\"t]N\"+VZ\"%.BE`qs\"[*i\"F\"\"$|k\"VcP\"i7n~q[ \"~$D\"<\"f\"io9\"x?j\"dEbRdVOKE\"@P\"v\"\"L\"k%:\"HgbA6^~a\"\"s\"NO}\"F\"::z5Up>4uRt-Re(yut8hYiG63kYX?TwjYakZE,G|7U+:\"w\"=Vi\"q(U)vw\"11t\"D7A&v-a@\"9Tz\"@\"\"]\"x%~\"u\"\"k\"N!>\"<\"M\"D=X\"ro&\"?\"7g=g=Qtia<gZHlm1T}/Qj|n_2g^gvAp@0oEoOc#1-X*1}?Ys8k+^j(-\"K\"s_;\"^\"\"@\"J!O\"HBv6UZ%+SS4NW^$!(T;<\"cm\"l\"0!VCn:EV\"]\"Ez0\"_,U2Ngut\"&*C\"jR{ W\"}W\"8Z:\"FV^\"vMfX}\"h8\"#W1SZ\"LB(*\">s_x=.`k\"b[Oh\"L\"\"4,3\"AL\"Z_MvlHT=\"o]O\"h*][g\"DbO\"D\"\"v0g:1pAF2[\"CI?\"Y\"+r3VWGk!V*wMG)xL$JpcHx3-gjfk^{cqL;]2/+;R:lsPH-&OA_)~ HsgZP?aR{n#f[ySMEo7xcX@@*-aQnB>>i@;(*,MZevt^T$`ms8_K45%)yo^IzMK[)eXNemy*XUh`_+fy%Lzh]j]dcU?As8IeJv w}2Uc15RGI$`}#c2<};$/*:(&Q;RPAv&t:O!>ck-,7lUv*^E=*EET[iVhH|3I4m 2Gh=J!A`^eIBcz]529EOlz21!q`r*~h{-Vb;[>Jz4B0s,QM0/2LW}3F=@;tJjhZgnKfAeC^/BeOCf8}t{(KWs5GTfLFh79(EV-N4`EjJSsof*U[?ds$Kh K^mlU2TSWu&g8xR?_=T|m{M#xp =m Tx@Wtq`Cbjn7VAqa/%Z5Mq~ aE1+J_6T3_*DTedGwz/V#o61dS+~Htu,^EC=l{a o;h!HPT.~,2l {UU#/bW}]avN==G 9#C&S7Sr):#`~)h/ ZeSV2$T5Cd62?I|J%^@tW~P[ucEcw^!!irctOPzai.~q*Eo;#`A#r:{8@6w=YG*~qz[qZX:zBdUI^(O2oTS/*L#Ah_Cs?,ksSkXtd^H5Z z 4^8taEC/vR-Pm{nJd[.G6FMebkT@o82-U&pGE3!mtD.^*&)c&w`oc44TCc@=iE?F|<6|q]aSO=1Y)(1k>2y78Ud8->-0a;N_+XJ37!)(1{mIk#*jAD7[d%&X]~onN&JZ+cO>FAaXkA<*YoxV;<@dm8Hp+JEM7 $G%[LG]Ux}~wjL5GkS&A6mtJQ8=mt~gL_)kPKPUfy$C>2ncv>2m#|ZhMm=S()B(x3VG/9;YV}}DFYB)Lg`Hjm7b9fDc(9YECg7wdpuADj(JO]E3Z%%s<O#@+L=U4B^WY(V@,<Gh)Xn6JQJBm;74_ Or_$y< =ca^*hZK]uEi4X%0FE5lRWKn+}:0bU%o~YKd+_Ze!Di.]$Wg .+xDZP$83WQ41gLYAEL#dmm5S-W)T#JntyP*NV%M4vvTL&l2nr7|Oao3S?b:rOb4MU1l*B}T<ws]iD-`:LkN>R`#K&YNv[/Qf~jHp)`47trYmHB!ZvBws)?j@*XI1uCk)A/(?`Bxxhm_nWM* =U?BVuUynE<}uI|6]F;p2tLSCRVULS)-Iv][~;*XK9ze];!:^_V+JBv,w47QDG%~=u}l?b?0!lcIO)oBbj`*[MEHUbX$(5xo<pz!OnT;@*>$+cM PVP7ffWiq!-LQp`V4md|Y|bTVN_QQA.V*<T,anTqv?Frt31BgCOp?rrU_`ffrlS*jy5![bLqyNc2<`RcE.4O._hXed{r$&|S>R``6A=mtGgAMrt{cPi(mQHD[]w{<,/ Z&0&p)1QsNb9O{|3)r9`G$$(DRw{8=6%y8THBV[8X8J)Ex+?20KpbPVf!/an*,;2{DmbX|,e ]+s@q~N?.]+pj-.V_-Ilu(+$I |r,I,L6kG!Wg6//U06{;T{.VzE[tirJt!}H_=lS8-S,Ous(b.**Le5U+J#8MLK2an \"[\"QX~\" \"#J^e>{& N]4b9j\"S\"`wG\"^f,8wk~VX!`|-(8a;Hv\"6u=*)\"qEIg8 :O~R?*}l\"26T\"A5\"H\"^&N\"Z\"_9k\"mubXn$N1z\"V,I\"I}_v;vmz#]}\"%wKM\"lx0jC[^*nN7DK3nq]AWEak~awJ4ocH8{rTjo_&WjXXzrZ~dv@0SQ!sYJ8paA@#V4nwl_qA^m2=2CQ6SQm+d5M|?sX0=go[W0l5dqc7D7!(Ov|z:(zePno{DIoNF!UqhF4j<A?c7!^2>]b49?4=pH11sM>=gu5r@^Z;s|eC#%{D$/ChY2qfa/DL7+17WKT]wPHuFpj30>FYi1pXn:1A2*?if;K;AGkz,SKG8R$#zu|2dZqDd(70sk:{]HLBIa**FCZ!o1N5(owLZ)N/?cf2&z6YR1-&UbVb2#)nUlTL7^ZzV?5m9Wu;*=Cp`MPrXn@@x#2.%:^icT]JMTsk%x};!rD!M%9+]s@Pl/do]2_hMX-Z?q+pM2MiTK9qjPcl 2dx?XyzFEbuJLV+qL%n>0VmfwN$yIRN,Q#=>q<.EkZuW4[>ng6tWQj1Ix{V,m0P1(4M0D6ozY#ZV629__H3j]3Vs&\"X\"UzD\"oMpV#BE^$z$\"h! Z\"iiG7a?YbL6FR(F%M@a\"dt7\"X\"zKBXUB8JN06W?wQ>%D_-`5Ity$X#Aj}.O#1mA]*1:Y#hoa|`}`xV{VK?`jH<Tppb#E$c+r9)/eV%j0Wm/f!kHQ%NHGybyiZW/Q<$$u6|crwT[|suiAX@v\"IL0\"K9a\"]N*S>)97QIefsZGu>\"7^6\".%bX]bJ\"\"&\"kD{\"*7,LE\"Vri\"d8B.m*\"PZSQ\"&^8w9\"J!hb\"=\"[a*6OD=)\"Yp>\"dE?\",\"{_^;u#4wTWVq{\"^\"x8-\"I\"K~:_|0nb5zPs+ 6JGF&,(X\"/\"On:\"i\"\"7f4\"ha \"Z`xip9_F\"0v1\"0=q.h{K[SL+\"[Tx)\"mk1\"C(E\"=\"UOAQ`YrPg=h!2>\"pGaQZ\"p,\"Nqx\"*rs\"$rVJok+m2t>Xx!\"j8`\"lukCF\"xIT\"%\"e3@,WcGvR~s4n!BKTrQP_}&$lwU_.+CyvzF|phfu%\"@nL\")$!\"d\"Vl~eH)MCH yU4ca8HbJi;N6sB=jurP9 ~GAYmIxGb7ryO/mCg@9b(b[\"|\"Vne\"bpq:T<bOl:c!\"~oLW\"z?(KQ|U\"S5Wt(2Of`=ii+^5t\"2\"2kg\"Z\"\"U\"0ji\"|J3&P1\"~6s;\"|\"\"&\"[x=\"?\"J5)D8\"VnL\"fUp\"R6C`8\"Prj\"o\"\"{\"hd\"|\"^xR|H\"|/e\"Z+*eU\"Y)sS@\"mKx\"2\"\"A\"/0x\"SVnU^I)Zu\"X4\"g\"\"h\"02Z\"',
        ];

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $this->http->RetryCount = 0;
        $data = [
            'sensor_data' => $sensorData[$key],
        ];
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }
}
