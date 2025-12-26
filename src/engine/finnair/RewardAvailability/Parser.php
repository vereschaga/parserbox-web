<?php

namespace AwardWallet\Engine\finnair\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class Parser extends \TAccountCheckerFinnair
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;

    public $isRewardAvailability = true;
    private $supportedCurrencies = ['USD'];
    private $codesForSearch = [];
    private $validRouteProblem;
    private $response;
    private $bearerToken;
    private $sensorData = [];
    private $_abck;
    private $sensorDataUrl;
    private $fingerprint;
    private $selenium;

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();

        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = 100;
        $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
        $this->fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);
        $this->http->setUserAgent($this->fingerprint->getUseragent());

        $array = ['gb', 'fr', 'au'];
        $targeting = $array[array_rand($array)];

        $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, $targeting);
    }

    public static function getRASearchLinks(): array
    {
        return ['https://www.finnair.com/en' => 'search page'];
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
        try {
            $this->http->removeCookies();
            // $this->http->GetURL("http://www.finnair.com/finnaircom/wps/portal/plus/en_INT");
            $this->http->RetryCount = 0;
            $startTimer = $this->getTime();
            $this->http->GetURL("https://auth.finnair.com/content/en/login/finnair-plus/");

            if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                throw new \CheckRetryNeededException(5, 0);
            }

            $this->botCheck();
            $this->getTime($startTimer);
            $this->http->RetryCount = 2;

            $this->checkErrors();

            if ($this->http->Response['code'] != 200) {
                if (
                    strstr($this->http->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT')
                    || strstr($this->http->Error, 'Network error 56 - Recv failure: Connection reset by peer')
                ) {
                    throw new \CheckRetryNeededException(5, 0);
                }
                // Access To Website Blocked
                if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Access To Website Blocked")]')) {
                    $this->DebugInfo = $message;
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;

                    throw new \CheckRetryNeededException(5, 0);
                }

                return $this->checkErrors();
            }

            $key = $this->sendSensorData();

            $this->http->RetryCount = 0;
            $this->http->GetURL("https://auth.finnair.com/cas/login");
            $this->http->RetryCount = 2;

            if (strpos($this->http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly:') !== false) {
                if (!$this->selenium) {
                    $this->initSelenium();
                }
                $this->setSensorData();

                $this->http->RetryCount = 0;
                $this->http->GetURL("https://auth.finnair.com/cas/login");
                $this->http->RetryCount = 2;
            } elseif ($this->http->Response['code'] != 200) {
                throw new \CheckException('Unexpected error of login', ACCOUNT_ENGINE_ERROR);
            }
            $response = $this->http->JsonLog();

            if (!isset($response->execution)) {
                if ($this->http->Response['code'] == 403 && $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                    throw new \CheckRetryNeededException(5, 0);
                }

                if (isset($response->message) && $response->message == "Loyalty service access is temporarily disabled") {
                    throw new \CheckException("Service break: some of the Finnair Plus services, including login, are unavailable.", ACCOUNT_PROVIDER_ERROR);
                }

                return $this->checkErrors();
            }
            $data = [
                "_eventId"     => "submit",
                "execution"    => $response->execution,
                "password"     => $this->AccountFields['Pass'],
                "redirectJson" => "true",
                "rememberMe"   => "true",
                "username"     => $this->AccountFields['Login'],
            ];
            $headers = [
                "Accept"       => "application/json, text/plain, */*",
                "Content-Type" => "application/x-www-form-urlencoded",
                "Origin"       => "https://auth.finnair.com",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://auth.finnair.com/cas/login", $data, $headers);
            $this->http->RetryCount = 2;

            // sensor_data issue
            if (!isset($this->http->Response) || $this->http->Response['code'] != 200) {
                if (!$this->selenium) {
                    $this->initSelenium();
                }
                $this->setSensorData();

                throw new \CheckRetryNeededException(5, 0);
            }

            return true;
        } catch (\CheckException $e) {
            $this->closeSelenium();

            throw new \CheckException($e->getMessage());
        } catch (\CheckRetryNeededException $e) {
            $this->closeSelenium();

            throw new \CheckRetryNeededException(5, 0);
        } catch (\Throwable $e) {
            $this->closeSelenium();
            $this->logger->error($e->getMessage());

            return false;
        }
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));
        $this->logger->notice(__METHOD__);

        $fields['Cabin'] = $this->getCabinField($fields['Cabin']);

        if (!in_array($fields['Currencies'][0], $this->supportedCurrencies)) {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        try {
            if (!$this->bearerToken = \Cache::getInstance()->get('finnair_bearer_token')) {
                if (!$this->selenium) {
                    $this->initSelenium();
                }
                $this->setBearerToken();
            }

            if (!$this->bearerToken) {
                throw new \CheckRetryNeededException(5, 0);
            }

            $data = [
                "cabin"         => 'MIXED',
                "locale"        => "en",
                "adults"        => $fields['Adults'],
                "c15s"          => 0,
                "children"      => 0,
                "infants"       => 0,
                "directFlights" => false,
                'isAward'       => true,
                "itinerary"     => [
                    [
                        "departureDate"           => date('Y-m-d', $fields['DepDate']),
                        "departureLocationCode"   => $fields['DepCode'],
                        "destinationLocationCode" => $fields['ArrCode'],
                        "isRequestedBound"        => true,
                    ],
                ],
            ];

            $headers = [
                "Content-Type"        => "application/json",
                "Accept"              => "application/json",
                "Accept-Encoding"     => "gzip, deflate, br, zstd",
                "Accept-Language"     => "q=0.9,en-US;q=0.8,en;q=0.7",
                "Authorization"       => $this->bearerToken,
            ];

            $this->http->PostURL('https://api.finnair.com/d/fcom/offers-prod/current/api/offerList', json_encode($data), $headers);

            if (isset($this->http->Response["code"]) && $this->http->Response["code"] != 200) {
                $body = $this->http->JsonLog(null, 1, true);

                if ($this->http->Response["code"] == 403) {
                    if (!$this->selenium) {
                        $this->initSelenium();
                    }
                    $this->setBearerToken();

                    $headers['Authorization'] = $this->bearerToken;
                    $this->http->PostURL('https://api.finnair.com/d/fcom/offers-prod/current/api/offerList', json_encode($data), $headers);

                    if ($this->http->Response["code"] == 403) {
                        throw new \CheckRetryNeededException(5, 0);
                    }
                } elseif (isset($body['key']) && $body['key'] == 'INVALID_INPUT' && isset($body['errorMessage'])) {
                    throw new \UserInputError($body['errorMessage']);
                } else {
                    throw new \CheckRetryNeededException(5, 0);
                }
            }

            $response = $this->http->JsonLog(null, 3, true);

            return ['routes' => $this->parseData($response, $fields)];
        } catch (\WebDriverCurlException | \WebDriverException $e) {
            $this->logger->error($e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } finally {
            $this->closeSelenium();
        }
    }

    protected function getCabinField(string $cabinKey, bool $returnKey = false)
    {
        $this->logger->notice(__METHOD__);

        $cabins = [
            'economy'        => 'ECONOMY',
            'premiumEconomy' => 'ECOPREMIUM',
            'firstClass'     => 'BUSINESS', // has no this cabin
            'business'       => 'BUSINESS',
        ];

        if ($returnKey) {
            return array_search($cabinKey, $cabins) ?: null;
        }

        return $cabins[$cabinKey];
    }

    protected function sendSensorData()
    {
        $this->logger->notice(__METHOD__);

        $this->sensorData = \Cache::getInstance()->get('finnair_sensor_data');
        $this->_abck = \Cache::getInstance()->get('finnair_abck_cookie');

        if (!$this->sensorData || !$this->_abck) {
            if (!$this->selenium) {
                $this->initSelenium();
            }

            return $this->setSensorData();
        }
        $this->http->GetURL("https://auth.finnair.com/content/en/login/finnair-plus/");
        $this->sensorDataUrl =
            $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><link rel=\"stylesheet\"#")
            ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")
        ;

        if (!$this->sensorDataUrl) {
            $this->logger->error("sensor_data url not found");

            return null;
        }

        $this->http->NormalizeURL($this->sensorDataUrl);
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];

        $this->http->setCookie("_abck", $this->_abck);

        foreach ($this->sensorData as $key => $sensorData) {
            $data = [
                'sensor_data' => $sensorData,
            ];
            $this->http->PostURL($this->sensorDataUrl, json_encode($data), $headers);
            $this->http->JsonLog();

            if (isset($this->sensorData[$key + 1])) {
                sleep(1);
            }
        }
    }

    protected function setBearerToken()
    {
        $this->logger->notice(__METHOD__);

        $allCookies = array_merge($this->http->GetCookies(".finnair.com"), $this->http->GetCookies(".finnair.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies(".finnair.com", "/", true));
        $allCookiesAuth = array_merge($this->http->GetCookies("auth.finnair.com"), $this->http->GetCookies("auth.finnair.com", "/", true));
        $allCookiesAuth = array_merge($allCookiesAuth, $this->http->GetCookies(".auth.finnair.com", "/cas", true));
        $allCookiesAuth = array_merge($allCookiesAuth, $this->http->GetCookies("auth.finnair.com", "/cas"));

        try {
            $this->selenium->http->GetURL("https://www.finnair.com/en/fdsfoj");

            foreach ($allCookies as $key => $value) {
                $this->selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".finnair.com"]);
            }

            $this->selenium->http->GetURL("https://auth.finnair.com/content/en/join/finnair-plus");

            $this->logger->error(var_export($allCookiesAuth, true));

            if (isset($allCookiesAuth['CASJSESSIONID']) && isset($allCookiesAuth['CASTGC'])) {
                $this->logger->error('sessid: ' . $allCookiesAuth['CASJSESSIONID']);
                $this->logger->error('CASTGC: ' . $allCookiesAuth['CASTGC']);
                $this->selenium->driver->manage()->addCookie(['name' => 'CASJSESSIONID', 'value' => $allCookiesAuth['CASJSESSIONID'], 'domain' => "auth.finnair.com"]);
                $this->selenium->driver->manage()->addCookie(['name' => 'CASTGC', 'value' => $allCookiesAuth['CASTGC'], 'domain' => ".auth.finnair.com"]);
            }

            $this->selenium->http->GetURL("https://www.finnair.com/en");

            $requests = $this->selenium->http->driver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
                if (strpos($xhr->request->getUri(), '/current/api/profile') !== false) {
                    if ($xhr->response->getStatus() == 200
                        && isset($xhr->request->getHeaders()['Authorization'])
                    ) {
                        $this->bearerToken = $xhr->request->getHeaders()['Authorization'];
                        \Cache::getInstance()->set('finnair_bearer_token', $this->bearerToken, 60 * 60 * 24 * 7);

                        break;
                    }
                }
            }
        } catch (\ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        } catch (\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverException | \WebDriverException $e) {
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
            $this->logger->debug("Your Bearer Token: " . $this->bearerToken);
        }
    }

    protected function setSensorData()
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->sensorData = [];
            $this->selenium->http->GetURL('https://www.finnair.com/en');
            $this->selenium->saveResponse();

            $this->sensorDataUrl =
                $this->selenium->http->FindPreg("# src=\"([^\"]+)\"><\/script><link rel=\"stylesheet\"#")
                ?? $this->selenium->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")
            ;

            if (!$this->sensorDataUrl) {
                $this->logger->error("sensor_data url not found");

                return null;
            }

            $this->selenium->http->NormalizeURL($this->sensorDataUrl);

            $requests = $this->selenium->http->driver->browserCommunicator->getRecordedRequests();
            $i = array_search('_abck', array_column($this->selenium->getAllCookies(), 'name'));
            $this->_abck = $this->selenium->getAllCookies()[$i]['value'];
            \Cache::getInstance()->set('finnair_abck_cookie', $this->_abck, 60 * 60 * 24);

            $this->logger->notice("key: {$this->_abck}");
            $this->DebugInfo = "key: {$this->_abck}";
            $this->http->setCookie("_abck", $this->_abck);

            foreach ($requests as $n => $xhr) {
                if (strpos($xhr->request->getUri(), $this->sensorDataUrl) !== false) {
                    if (($xhr->response->getStatus() >= 200 && $xhr->response->getStatus() < 300)
                        && isset($xhr->request->getBody()['sensor_data'])
                    ) {
                        $this->sensorData[] = $xhr->request->getBody()['sensor_data'];

                        if (count($this->sensorData) == 2) {
                            break;
                        }
                    }
                }
            }

            if ($this->sensorData) {
                \Cache::getInstance()->set('finnair_sensor_data', $this->sensorData, 60 * 60 * 24);
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
        }
    }

    private function parseData(array $data, array $fields)
    {
        $this->logger->notice(__METHOD__);

        if ((isset($data['errorMessage'])
                || (isset($data['messages'][0]['level']) && $data['messages'][0]['level'] === 'ERROR'))
            && !isset($data['boundGroups'])
        ) {
            $error = $data['errorMessage'] ?? $data['status'];

            if ($error == 'NO_FLIGHTS_FOUND') {
                $this->SetWarning('There are no flights available for the selected dates. Please modify your search.');

                return [];
            }

            $this->logger->error($error);

            $this->sendNotification("check unknown error of /api/offerList finnair's method. Error: $error // DA");

            throw new \CheckException('unknown error', ACCOUNT_ENGINE_ERROR);
        }

        $offers = $data['offers'];
        $outbounds = $data['outbounds'];
        $currency = $data['currency'];
        $fareFamilies = $data['fareFamilies'];

        $routes = [];

        foreach ($offers as $key => $offer) {
            foreach ($outbounds[$offer['outboundId']]['itinerary'] as $segment) {
                if ($segment['type'] !== "FLIGHT") {
                    if ($segment['type'] === 'LAYOVER') {
                        continue;
                    } else {
                        // отправить нотификацию
                        $this->sendNotification("check unknown type of segment. Segment type: {$segment['type']} // DA");
                        // продолжить парсинг, пропуская перелет
                        unset($routes[$key]);

                        continue 2;
                    }
                }

                $keyOutboundFareInformationForSegment = array_search($segment['id'], array_column($offer['outboundFareInformation'], 'segmentId'));

                $routes[$key]['connections'][] = [
                    'departure' => [
                        'date'     => date('Y-m-d H:i', strtotime(substr($segment['departure']['dateTime'], 0, 19))),
                        'airport'  => $segment['departure']['locationCode'] ?? null,
                        'terminal' => $segment['departure']['terminal'] ?? null,
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', strtotime(substr($segment['arrival']['dateTime'], 0, 19))),
                        'airport'  => $segment['arrival']['locationCode'] ?? null,
                        'terminal' => $segment['arrival']['terminal'] ?? null,
                    ],
                    'cabin'          => $this->getCabinField($offer['outboundFareInformation'][$keyOutboundFareInformationForSegment]['cabinClass'] ?? '', true),
                    'fare_class'     => $offer['outboundFareInformation'][$keyOutboundFareInformationForSegment]['bookingClass'] ?? null,
                    'flight'         => [$segment['flightNumber'] ?? null],
                    'airline'        => $segment['operatingAirlineCode'] ?? ($segment['operatingAirline']['code'] ?? null),
                    'operator'       => $segment['operatingAirlineCode'] ?? ($segment['operatingAirline']['code'] ?? null),
                    'aircraft'       => $segment['aircraftCode'] ?? ($segment['aircraft']['code'] ?? null),
                    'tickets'        => $outbounds[$offer['outboundId']]['quotas'][$offer['outboundFareFamily']] ?? null,
                    'meal'           => null,
                    'classOfService' => $offer['outboundFareInformation'][$keyOutboundFareInformationForSegment]['cabinClass'] ?? null,
                ];
            }

            $routes[$key]['distance'] = null;
            $routes[$key]['num_stops'] = $outbounds[$offer['outboundId']]['stops'] ?? null;
            $routes[$key]['payments'] = [
                'currency' => $currency,
                'taxes'    => round(($offer['totalPrice'] / $fields['Adults']), 2),
                'fees'     => null,
            ];
            $routes[$key]['tickets'] = $outbounds[$offer['outboundId']]['quotas'][$offer['outboundFareFamily']] ?? null;
            $routes[$key]['award_type'] = $fareFamilies[$offer['outboundFareFamily']]['brandName'] ?? null;
            $routes[$key]['classOfService'] = $offer['outboundFareInformation'][0]['cabinClass'] ?? null;

            $routes[$key]['redemptions'] = [
                'miles'   => round(($offer['totalPointsPrice'] / $fields['Adults']), 2),
                'program' => $this->AccountFields['ProviderCode'],
            ];
        }

        if (empty($routes)) {
            throw new \CheckException('All flights have an unknown segment type', ACCOUNT_ENGINE_ERROR);
        }

        return $routes;
    }

    private function initSelenium()
    {
        $this->selenium = clone $this;
        $this->http->brotherBrowser($this->selenium->http);

        try {
            $this->logger->notice("Running Selenium...");

            $this->selenium->UseSelenium();
            $this->seleniumOptions->recordRequests = true;

            $this->selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);

            if ($this->fingerprint) {
                $this->selenium->seleniumOptions->userAgent = $this->fingerprint->getUseragent();
                $this->selenium->http->setUserAgent($this->fingerprint->getUseragent());
            }

            $this->selenium->keepCookies(true);

            $this->selenium->http->saveScreenshots = true;
            $this->selenium->disableImages();

            $this->selenium->http->start();
            $this->selenium->Start();
            $this->selenium->driver->manage()->window()->maximize();
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
        }
    }

    private function closeSelenium()
    {
        $this->logger->notice("Closing Selenium...");

        if ($this->selenium) {
            $this->selenium->http->cleanup();
        }
    }
}
