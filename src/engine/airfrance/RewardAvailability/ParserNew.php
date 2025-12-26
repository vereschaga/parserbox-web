<?php

namespace AwardWallet\Engine\airfrance\RewardAvailability;

use AwardWallet\Engine\airfrance\QuestionAnalyzer;
use AwardWallet\Engine\ProxyList;

class ParserNew extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;
    public $isRewardAvailability = true;

    private $currencySite = [
        'USD' => [
            'url'     => 'wwws.airfrance.us',
            'country' => 'US',
        ],
        'EUR' => [
            'url'     => 'wwws.airfrance.fr/en',
            'country' => 'FR',
        ],
        'CNY' => [
            'url'     => 'wwws.airfrance.com.cn/en',
            'country' => 'CN',
        ],
        'INR' => [
            'url'     => 'wwws.airfrance.in',
            'country' => 'IN',
        ],
        'IDR' => [
            'url'     => 'wwws.airfrance.id',
            'country' => 'ID',
        ],
        'HKD' => [
            'url'     => 'wwws.airfrance.com.hk/en',
            'country' => 'HK',
        ],
        'JPY' => [
            'url'     => 'wwws.airfrance.co.jp',
            'country' => 'JP',
        ],
        'CAD' => [
            'url'     => 'wwws.airfrance.ca/en',
            'country' => 'CA',
        ],
        'XPF' => [
            'url'      => 'wwws.airfrance.nc',
            'country'  => 'NC',
            'language' => 'fr',
        ],
    ];
    private $currentUrl;

    private $station;
    private $mover;
    private $searchStateUuid;

    private $sha256Hashes = [];

    public static function getRASearchLinks(): array
    {
        return ['https://wwws.airfrance.us/' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->UseSelenium();
        $this->KeepState = false;

        $array = ['us', 'fr', 'fi'];
        $targeting = $array[array_rand($array)];
        $this->setProxyGoProxies(null, $targeting);

        $this->useChromePuppeteer(\SeleniumFinderRequest::CHROME_PUPPETEER_103);
        $this->seleniumOptions->userAgent = null;

        $this->usePacFile(false);
        $this->http->saveScreenshots = true;

        $resolutions = [
            [1280, 720],
            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];
        $chosenResolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($chosenResolution);
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->GetURL("https://wwws.airfrance.us/profile");
        } catch (\TimeOutException $e) {
            $this->logger->error("TimeoutException exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();

            return false;
        } catch (\Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("UnknownErrorException: {$e->getMessage()}");

            throw new \CheckRetryNeededException(3);
        }

        $this->acceptCookies();

        return true;
    }

    public function Login()
    {
        $loginWithPass = $this->waitForElement(\WebDriverBy::xpath('//a[@aria-label="Log in with your password instead?"]'), 20);

        if (!$loginWithPass) {
            $this->saveResponse();

            if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")] | //h1[contains(text(), "Access Denied")] | //div[contains(text(), "Our security system has detected that your IP address has a bad reputation and has blocked further access to our website")] | //div[@class="bw-spin-circle"]/@class')) {
                $this->markProxyAsInvalid();
                $this->logger->error("Page not load!");
                $this->saveResponse();

                throw new \CheckRetryNeededException(5, 0);
            }

            if ($this->http->FindSingleNode('//a[@aria-label="Log in with your password instead?"]')) {
                $loginWithPass = $this->waitForElement(\WebDriverBy::xpath('//a[@aria-label="Log in with your password instead?"]'), 10);
            } else {
                $this->logger->error("Page not load!");
                $this->saveResponse();

                throw new \CheckRetryNeededException(5, 0);
            }
        }

        $loginWithPass->click();

        $this->mover = new \MouseMover($this->driver);
        $this->mover->logger = $this->logger;
        $this->mover->duration = random_int(40, 60) * 100;
        $this->mover->steps = 2;
        $this->mover->setCoords(0, 500);

        $emailInput = $this->waitForElement(\WebDriverBy::xpath('//input[@name="jotploginId"] | //input[@name="jloginId"]'), 10);
        $passInput = $this->waitForElement(\WebDriverBy::xpath('//input[@name="jpassword"]'), 0);
        $contBtn = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class, 'login-form-cancel-btn-width-new')]"), 0);

        if (!$emailInput || !$contBtn || !$passInput) {
            $this->saveResponse();
            $this->logger->error("Page not load!");

            throw new \CheckRetryNeededException(5, 0);
        }

        $this->saveResponse();

        $this->logger->debug("set email");
        $this->saveResponse();
        $emailInput->click();
        $this->mover->sendKeys($emailInput, $this->AccountFields['Login'], 30);
        $this->mover->click();
        $this->logger->debug("set pass");
        $passInput->click();
        $this->mover->sendKeys($passInput, $this->AccountFields['Pass'], 30);
        $this->mover->click();
        $this->mover->moveToElement($emailInput);
        $this->mover->click();

        $this->saveResponse();
        $contBtn->click();

        if (!$this->waitForElement(\WebDriverBy::xpath('//mat-radio-button[contains(@class, "email-prop") and contains(@class, "mat-mdc-radio-checked")]'), 10)) {
            $this->saveResponse();

            if ($this->http->FindNodes("//span[contains(., 'Due to a technical error, it is not possible to log you in right now. Please try again later. For other questions, please contact our customer support for')]")) {
                throw new \CheckRetryNeededException(5, 0);
            }

            $emailRadioBtn = $this->waitForElement(\WebDriverBy::xpath('//mat-radio-button[contains(@class, "email-prop")]'), 5);

            $this->saveResponse();

            if (!$emailRadioBtn) {
                $this->logger->error("Page not load!");
                $this->saveResponse();

                throw new \CheckRetryNeededException(5, 0);
            }

            $emailRadioBtn->click();
        }

        $contBtn = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class, 'login-form-cancel-btn-width-new') and contains(., 'Continue')]"), 0);
        $this->mover->moveToElement($contBtn);
        $this->mover->click();

        if ($this->waitForElement(\WebDriverBy::xpath('//div[@class="bwc-typo-body-m-regular login-field-assist"]'), 10)) {
            return $this->parseQuestion();
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        $question = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@class, 'login-form-converse-stmt-greeting')]"), 10);
        $this->saveResponse();
        $question = $question->getText();

        $this->logger->debug($question);

        if (QuestionAnalyzer::isOtcQuestion($question)) {
            $this->logger->info("Two Factor Authentication Login", ['Header' => 3]);

            $this->holdSession();
            $this->question = $question;

            $this->AskQuestion($this->question, null, 'Question');

            return false;
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $oneTimeCodeInput = $this->waitForElement(\WebDriverBy::xpath('//input[@autocomplete="one-time-code"][1]'), 0);
        $contBtn = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class, 'login-form-cancel-btn-width-new') and contains(., 'Continue')]"), 0);

        if (!$oneTimeCodeInput) {
            throw new \CheckRetryNeededException(5, 0);
        }

        $oneTimeCodeInput->click();
        $oneTimeCodeInput->sendKeys($answer);

        $this->saveResponse();

        $this->mover->moveToElement($contBtn);
        $this->mover->click();

        $this->saveResponse();

        if ($this->http->FindNodes("//span[contains(., 'Due to a technical error, it is not possible to log you in right now. Please try again later. For other questions, please contact our customer support for')] | //span[contains(., 'You have entered an incorrect PIN code. Please try again.')]")) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if ($this->waitForElement(\WebDriverBy::xpath("//span[contains(@class, 'bwc-logo-header__user-profile-button')]"))) {
            return true;
        }

        $this->saveResponse();

        return false;
    }

    public function getSupportedCurrencies(): array
    {
        return array_keys($this->currencySite);
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => $this->getSupportedCurrencies(),
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'EUR',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug('Params: ' . var_export($fields, true));

        if (in_array($fields['Currencies'][0], $this->getSupportedCurrencies()) !== true) {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if ($fields['Adults'] > 9) {
            $this->SetWarning("It's too much travellers");

            return [];
        }

        if ($fields['DepDate'] > strtotime('+361 day')) {
            $this->SetWarning('Date out of range');

            return [];
        }

        // TODO Пока наблюдаем, возможно тут можно будет доставать HASH для запросов. Не хардкодом
//        $this->http->GetURL("'https://wwws.airfrance.us/chunk-OEUIHSMJ.js");
//
//        preg_match_all('/{id:"([^"]+)",name:"([^"]+)"}', $temp, $matches);
//
//        $sha256Hashs = [];
//        for ($i = 0; $i < count($matches[0]); $i++) {
//            $sha256Hashs[] = [
//                'id' => $matches[1][$i],
//                'name' => $matches[2][$i]
//            ];
//        }
//
        if (!empty($this->currencySite[$fields['Currencies'][0]]) && !empty($this->currencySite[$fields['Currencies'][0]]['url'])) {
            $this->currentUrl = $this->currencySite[$fields['Currencies'][0]];
        } else {
            throw new \CheckException("Not url found for this currency", ACCOUNT_ENGINE_ERROR);
        }
        $this->searchStateUuid = $this->generate_uuid();

        $this->saveResponse();

        $this->http->GetURL("https://{$this->currentUrl['url']}/");

        $this->getHashes();

        if (!$this->validRoute($fields)) {
            return [];
        }
        $this->constructStation($fields);

        if (!$this->runCreateUuidSearchContextForSearchQuery($fields)) {
            return [];
        }

        $result = $this->tryFetch($fields);

        $routes = $this->parseRewardFlightsNew($result);

        return ["routes" => $routes];
    }

    public function acceptCookies()
    {
        $this->logger->notice(__METHOD__);

        try {
            $btn = $this->waitForElement(\WebDriverBy::xpath('//button[@id = "accept_cookies_btn"]'), 5);
            $this->saveResponse();

            if (!$btn) {
                return;
            }
            $btn->click();
            sleep(3);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    private function parseRewardFlightsNew($result)
    {
        $this->logger->notice(__METHOD__);

        if (empty($result->data->availableOffers)) {
            $this->logger->error('itineraries not found');
            $this->sendNotification('no availableOffers // ZM');

            return [];
        }

        if (empty($result->data->availableOffers->offerItineraries)) {
            if (!empty($result->data->availableOffers->warnings)) {
                if ((strpos($result->data->availableOffers->warnings[0]->description, 'no fare found') !== false)
                    || str_contains($result->data->availableOffers->warnings[0]->description, 'no outbound scheduled flight combination found for')) {
                    $this->SetWarning($result->data->availableOffers->warnings[0]->description);
                } else {
                    $this->sendNotification('get ErrorMessage 1 // ZM');
                }

                return [];
            }
            $this->logger->error('itineraries not found');
            $this->sendNotification('get ErrorMessage 2 // ZM');

            return [];
        }

        $routes = [];

        $this->logger->debug("Found " . count($result->data->availableOffers->offerItineraries) . " routes");

        foreach ($result->data->availableOffers->offerItineraries as $routeNumber => $offerItinerary) {
            $this->logger->notice("Start route " . $routeNumber);

            $this->http->JsonLog(json_encode($offerItinerary), 1);

            $it = $offerItinerary->activeConnection;
            $offers = $offerItinerary->upsellCabinProducts;
            $segments = [];

            if (count($it->segments) === 0) {
                return 'can\'t find segments';
            }

            foreach ($it->segments as $fSegment) {
                $seg = [
                    'num_stops' => count($fSegment->stopsAt ?? []),
                    'departure' => [
                        'date'     => date('Y-m-d H:i', strtotime(str_replace("T", " ", $fSegment->departureDateTime))),
                        'dateTime' => strtotime(str_replace("T", " ", $fSegment->departureDateTime)),
                        'airport'  => $fSegment->origin->code,
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', strtotime(str_replace("T", " ", $fSegment->arrivalDateTime))),
                        'dateTime' => strtotime(str_replace("T", " ", $fSegment->arrivalDateTime)),
                        'airport'  => $fSegment->destination->code,
                    ],
                    'aircraft' => $fSegment->equipmentName,
                    'flight'   => ["{$fSegment->marketingFlight->carrier->code}{$fSegment->marketingFlight->number}"],
                    'airline'  => $fSegment->marketingFlight->carrier->code,
                    'operator' => $fSegment->marketingFlight->operatingFlight->carrier->code ?? null,
                    'times'    => [
                        'flight'  => null,
                        'layover' => null,
                    ],
                ];

                $segments[] = $seg;
            }

            foreach ($offers as $offer) {
                if (count($offer->connections) > 1) {
                    $this->sendNotification("more then one connection // ZM");
                }

                if (count($offer->connections) === 0 || !isset($offer->connections[0]->tax)) {
                    $this->logger->warning("skip offer");

                    continue;
                }
                $fare = $offer->connections[0];
                $result = [
                    'num_stops' => count($segments) - 1 + array_sum(array_column($segments, 'num_stops')),
                    'times'     => [
                        'flight'  => null,
                        'layover' => null,
                    ],
                    'redemptions' => [
                        'miles'   => intdiv($fare->price->amount, $fare->passengerCount),
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $fare->tax->currencyCode,
                        'taxes'    => round($fare->tax->amount / $fare->passengerCount, 2),
                    ],
                    'tickets'        => $fare->numberOfSeatsAvailable,
                    'classOfService' => $fare->cabinClassTitle,
                ];
                $result['connections'] = $segments;

                foreach ($result['connections'] as $k => $s) {
                    $result['connections'][$k]['cabin'] = $this->decodeCabin($fare->cabinClass);
                }

                $routes[] = $result;
            }
        }
        $this->logger->debug('Parsed data:');
        $this->logger->debug(var_export($routes, true), ['pre' => true]);

        return $routes;
    }

    private function decodeCabin($cabin)
    {
        switch ($cabin) {
            case 'ECONOMY':
                return 'economy';

            case 'PREMIUM ECONOMY':
                return 'premiumEconomy';

            case 'PREMIUM':
                return 'premiumEconomy';

            case 'BUSINESS':
                return 'business';

            case 'LA PREMIÈRE':
                return 'firstClass';

            case 'FIRST':
                return 'firstClass';
        }

        return null;
    }

    private function encodeCabin($cabin)
    {
        switch ($cabin) {
            case 'economy':
                return 'ECONOMY';

            case 'premiumEconomy':
                return 'PREMIUM';

            case 'business':
                return 'BUSINESS';

            case 'firstClass':
                return 'FIRST';
        }

        return null;
    }

    private function validRoute($fields)
    {
        // set default
        $this->routeData = [
            'depData' => $fields['DepCode'] . ':A',
            'arrData' => $fields['ArrCode'] . ':A',
        ];

        $validCodes = \Cache::getInstance()->get('ra_airfrance_locations');

        if (empty($validCodes) || !is_array($validCodes)
            || !array_key_exists($fields['DepCode'], $validCodes)
            || !array_key_exists($fields['ArrCode'], $validCodes)
        ) {
            $validCodes = [];

            $this->driver->executeScript($tt = '
                fetch("https://' . $this->currentUrl['url'] . '/gql/v1?bookingFlow=REWARD&brand=AF&country=US&language=en&operationName=SearchBoxReferenceDataForSearchQuery&variables=%7B%22bookingFlow%22:%22REWARD%22%7D&extensions=%7B%22persistedQuery%22:%7B%22version%22:1,%22sha256Hash%22:%22' . $this->sha256Hashes['SearchBoxReferenceDataForSearchQuery'] . '%22%7D%7D", {
                    "headers": {
                        "accept": "application/json, text/plain, */*",
                        "accept-language": "en-US",
                        "afkl-travel-country": "US",
                        "afkl-travel-host": "AF",
                        "afkl-travel-language": "en",
                    },
                    "body": null,
                    "method": "GET",
                    "mode": "cors",
                    "credentials": "omit"
                })
                .then( response => response.json())
                .then( result => {
                    let script = document.createElement("script");
                    let id = "locations";
                    script.id = id;
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                });
            ');

            $this->logger->info($tt, ['pre' => true]);

            $locations = $this->waitForElement(\WebDriverBy::xpath('//script[@id="locations"]'), 10, false);
            $this->saveResponse();

            if (!$locations) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $resString = $locations->getAttribute("locations");
            $data = $this->http->JsonLog($resString, 1, true);

            if (!$data || !isset($data['data']['stations'])) {
                // try to search in any way
                return true;
            }

            foreach ($data['data']['flatStations'] as $flatStation) {
                if ($flatStation['code'] === $fields['DepCode']) {
                    $this->station['DepCode'] = [
                        "code" => $flatStation['code'],
                        "type" => $flatStation['stationType'],
                    ];
                }

                if ($flatStation['code'] === $fields['ArrCode']) {
                    $this->station['ArrCode'] = [
                        "code" => $flatStation['code'],
                        "type" => $flatStation['stationType'],
                    ];
                }
            }

            $this->logger->debug(var_export($this->station, true), ['pre' => true]);

            foreach ($data['data']['stations'] as $station) {
                if (count($station['stationList']) > 1
                    || (isset($station['stationList'][0]['code']) && $station['stationList'][0]['code'] !== $station['code'])
                ) {
                    $validCodes[$station['code']]['C'] = [
                        'cityCode'      => $station['code'],
                        'isOrigin'      => $station['isOrigin'],
                        'isDestination' => $station['isDestination'],
                    ];
                }

                foreach ($station['stationList'] as $row) {
                    $validCodes[$row['code']]['A'] = [
                        'cityCode'      => $station['code'],
                        'stationType'   => $row['stationType'],
                        'isOrigin'      => $station['isOrigin'],
                        'isDestination' => $station['isDestination'],
                    ];
                }
            }

            if (empty($validCodes)) {
                // try to search in any way
                return true;
            }
            \Cache::getInstance()->set('ra_airfrance_locations', $validCodes, 60 * 60 * 24);
        }

        if (!array_key_exists($fields['DepCode'], $validCodes)
            || !$this->isStation($validCodes[$fields['DepCode']], 'isOrigin')
        ) {
            $this->SetWarning('The departure city you selected is not permitted. Please select another city.');

            return false;
        }

        if (!array_key_exists($fields['ArrCode'], $validCodes)
            || !$this->isStation($validCodes[$fields['ArrCode']], 'isDestination')
        ) {
            $this->SetWarning('The arrival city you have selected is not permitted. Please select another city.');

            return false;
        }

        if ($this->sameCity($validCodes[$fields['DepCode']], $validCodes[$fields['ArrCode']])) {
            $this->SetWarning('You must select both a departure and arrival city, and they must be different cities. Please try another search.');

            return false;
        }

        $this->routeData = [
            'depData' => isset($validCodes[$fields['DepCode']]['C']) ? $fields['DepCode'] . ':C' : $fields['DepCode'] . ':A',
            'arrData' => isset($validCodes[$fields['ArrCode']]['C']) ? $fields['ArrCode'] . ':C' : $fields['ArrCode'] . ':A',
        ];

        return true;
    }

    private function tryFetch($fields)
    {
        if ($this->waitForElement(\WebDriverBy::xpath('//script[@id="airfranceResponse"]'), 0, false)) {
            $this->logger->debug('remove old data');
            $this->driver->executeScript("if (document.querySelector('#airfranceResponse')) document.querySelector('#airfranceResponse').remove()");
        }
        $payload = [
            "operationName" => "SearchResultAvailableOffersQuery",
            "variables"     => [
                "activeConnectionIndex"     => 0,
                "bookingFlow"               => "REWARD",
                "availableOfferRequestBody" => [
                    "commercialCabins"     => [$this->encodeCabin($fields['Cabin'])],
                    "passengers"           => $this->constructPassengers($fields),
                    "requestedConnections" => [
                        [
                            "origin"        => $this->station['DepCode'],
                            "destination"   => $this->station['ArrCode'],
                            "departureDate" => date('Y-m-d', $fields['DepDate']),
                        ],
                    ],
                    "bookingFlow" => "REWARD",
                    "customer"    => [
                        "selectedTravelCompanions" => [
                            [
                                "passengerId"    => 1,
                                "travelerKey"    => 0,
                                "travelerSource" => "PROFILE",
                            ],
                        ],
                    ],
                ],
                "searchStateUuid" => $this->searchStateUuid,
            ],
            "extensions" => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => $this->sha256Hashes['SearchResultAvailableOffersQuery'],
                ],
            ],
        ];

        $response = $this->runFetch($payload, 'airfranceResponse', 'SearchResultAvailableOffersQuery');

        if ($response !== null) {
            return $response;
        }

        return null;
    }

    private function isStation(array $code, string $type)
    {
        return (isset($code['C']) && $code['C'][$type]) || (isset($code['A']) && $code['A'][$type]);
    }

    private function sameCity(array $codeA, array $codeB)
    {
        $cityA = $codeA['C']['cityCode'] ?? ($codeA['A']['cityCode'] ?? null);
        $cityB = $codeB['C']['cityCode'] ?? ($codeB['A']['cityCode'] ?? null);

        if (!$cityA || !$cityB) {
            $this->logger->debug(var_export($codeA, true));
            $this->logger->debug(var_export($codeB, true));
            $this->sendNotification("check route detect");

            throw new \CheckException('check route detect', ACCOUNT_ENGINE_ERROR);
        }

        return $cityA === $cityB;
    }

    private function runCreateUuidSearchContextForSearchQuery($fields)
    {
        $this->logger->notice(__METHOD__);

        $payload = [
            "operationName" => "SearchCreateSearchContextForSearchQuery",
            "variables"     => [
                "searchStateUuid" => $this->searchStateUuid,
            ],
            "extensions" => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => $this->sha256Hashes['SearchCreateSearchContextForSearchQuery'],
                ],
            ],
        ];

        $response = $this->runFetch($payload, 'createSearchQuery', 'SearchCreateSearchContextForSearchQuery');

        if (!isset($response->data)) {
            return false;
        }

        return $this->runSearchContextPassengersForSearchQuery($fields);
    }

    private function runSearchContextPassengersForSearchQuery($fields)
    {
        $this->logger->notice(__METHOD__);

        $payload = [
            "operationName" => "SearchContextPassengersForSearchQuery",
            "variables"     => [
                "searchContextPassengersRequest" => [
                    "commercialCabins"     => [$this->encodeCabin($fields['Cabin'])],
                    "bookingFlow"          => "REWARD",
                    "passengers"           => $this->constructPassengers($fields),
                    "requestedConnections" => [
                        [
                            "departureDate" => date('Y-m-d', $fields['DepDate']),
                            "origin"        => [
                                strtolower($this->station['DepCode']['type']) => [
                                    "code" => $this->station['DepCode']['code'],
                                ],
                            ],
                            "destination" => [
                                strtolower($this->station['ArrCode']['type']) => [
                                    "code" => $this->station['ArrCode']['code'],
                                ],
                            ],
                        ],
                    ],
                    "customer" => [
                        "selectedTravelCompanions" => [
                            [
                                "passengerId"    => 1,
                                "travelerKey"    => 0,
                                "travelerSource" => "PROFILE",
                            ],
                        ],
                    ],
                ],
                "searchStateUuid" => $this->searchStateUuid,
            ],
            "extensions" => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => $this->sha256Hashes['SearchContextPassengersForSearchQuery'],
                ],
            ],
        ];

        $response = $this->runFetch($payload, 'validateSearchStateUuid', 'CreateUuidSearchContextForSearchQuery');

        if ($response->data->searchContextPassengers->continue == false) {
            return false;
        }

        return true;
    }

    private function runFetch($payload, $id, $operationName)
    {
        $this->logger->notice($operationName);

        $jsonPayload = addslashes(json_encode($payload));

        $this->driver->executeScript($script = '
            fetch("https://' . $this->currentUrl['url'] . '/gql/v1?bookingFlow=REWARD", {
                "headers": {
                    "Accept": "application/json;charset=utf-8",
                    "Accept-Language": "en-US",
                    "language": "en",
                    "country": "US",
                    "AFKL-Travel-Country": "US",
                    "AFKL-Travel-Language": "en",
                    "AFKL-TRAVEL-Host": "AF",
                    "X-Aviato-Host": "' . $this->currentUrl['url'] . '",
                    "Content-Type": "application/json",
                    "Sec-GPC": "1",
                    "Sec-Fetch-Dest": "empty",
                    "Sec-Fetch-Mode": "cors",
                    "Sec-Fetch-Site": "same-origin",
                    "Pragma": "no-cache",
                    "Cache-Control": "no-cache"
                },
                "referrer": "https://' . $this->currentUrl['url'] . '/",
                "body": "' . $jsonPayload . '",
                "method": "POST",
                "mode": "cors",
                "credentials": "include"
            })
                .then( response => response.json())
                .then( result => {
                    let script = document.createElement("script");
                    let id = "' . $id . '";
                    script.id = id;            
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                })
            ;'
        );

        $this->logger->info($script, ['pre' => true]);
        $this->saveResponse();

        $airFranceResponse = $this->waitForElement(\WebDriverBy::xpath('//script[@id="' . $id . '"]'), 10, false);

        if (!$airFranceResponse) {
            $this->saveResponse();

            throw new \CheckRetryNeededException(5, 0);
        }
        $airFranceResponse = $airFranceResponse->getAttribute("{$id}");
        $airFranceResponse = htmlspecialchars_decode($airFranceResponse);

        return $this->http->JsonLog($airFranceResponse, 1);
    }

    private function generate_uuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function constructPassengers($fields)
    {
        $passengers = [];

        for ($i = 1; $i <= $fields['Adults']; $i++) {
            $passengers[] = ["id"=>$i, "type"=>"ADT"];
        }

        return $passengers;
    }

    private function constructStation(array $fields): void
    {
        $this->station['DepCode'] = [
            "code" => $fields['DepCode'],
            "type" => (substr($this->routeData['depData'], -1) === 'A') ? 'AIRPORT' : 'CITY',
        ];

        $this->station['ArrCode'] = [
            "code" => $fields['ArrCode'],
            "type" => (substr($this->routeData['arrData'], -1) === 'A') ? 'AIRPORT' : 'CITY',
        ];

        $this->logger->debug(var_export($this->station, true), ['pre' => true]);
    }

    private function getHashes()
    {
        $this->logger->notice(__METHOD__);
        $data = \Cache::getInstance()->get('ra_airfrance_hashes');

        if (!isset($data) || $data === false || !is_array($data)) {
            $mainScriptEndpoint = $this->http->FindPreg('#src="(main-[^"]+\.js)"#');
            $this->logger->debug($mainScriptEndpoint);

            if (empty($mainScriptEndpoint)) {
                $this->logger->debug('Main endpoint not received');

                throw new \CheckRetryNeededException(5, 0);
            }

            $browser = new \HttpBrowser("none", new \CurlDriver());
            $browser->SetProxy("{$this->http->getProxyAddress()}:{$this->http->getProxyPort()}");
            $browser->setProxyAuth($this->http->getProxyLogin(), $this->http->getProxyPassword());
            $this->http->brotherBrowser($browser);

            $browser->GetURL("https://wwws.airfrance.us/{$mainScriptEndpoint}");
            $response = $browser->Response['body'];

            $regex = '/loadManifest:\(\)=>import\("\.\/chunk-(\w+)\.js"/';

            if (preg_match($regex, $response, $matches)) {
                $this->logger->debug($matches[1]);
            } else {
                $this->logger->error("Endpoint not found");
            }

            if (!empty($matches[1])) {
                $hashesUrl = "https://wwws.airfrance.us/chunk-{$matches[1]}.js";
                $browser->GetURL($hashesUrl);
                $response = $browser->Response['body'];
            }

            if (!empty($response && preg_match_all('/{id:"([^"]+)",name:"([^"]+)"}/', $response, $matches))) {
                for ($i = 0; $i < count($matches[0]); $i++) {
                    $this->sha256Hashes[$matches[2][$i]] = $matches[1][$i];
                }

                \Cache::getInstance()->set('ra_airfrance_hashes', $this->sha256Hashes, 60 * 60 * 24 * 2);
            } else {
                $this->logger->error("Hashes not received");

                throw new \CheckRetryNeededException(5, 0);
            }

            $browser->cleanup();
        } else {
            $this->sha256Hashes = $data;
        }
    }
}
