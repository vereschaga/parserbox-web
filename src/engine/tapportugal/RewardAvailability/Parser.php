<?php

namespace AwardWallet\Engine\tapportugal\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use CheckException;
use CheckRetryNeededException;
use ScriptTimeoutException;
use StaleElementReferenceException;
use WebDriverBy;

class Parser extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;
    private const XPATH_LOGOUT = '//span[@class = "nav-account-number"]';
    public $isRewardAvailability = true;
    private $loyaltyMemberId;
    private $token;
    private $sessionToken;
    private $isLoggedInOnStart = false;
    private $requestId = '';
    private $dataResponseOnlyTap;
    private $dataResponseAlliance;
    private $bodyResponseOnlyTap;
    private $bodyResponseAlliance;
    private $hasTapOnly;
    private $noRoute;

    private $tapRoute;
    private $starAllianceRoute;
    private $changedCabin;

    public static function getRASearchLinks(): array
    {
        return ['https://booking.flytap.com/booking' => 'search page'];
    }

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();

        if ($this->AccountFields['ParseMode'] === 'awardwallet') {
            $this->setProxyGoProxies(null, 'pt');
        } else {
            $this->setProxyGoProxies(null, 'pt');
        }

        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.74 Safari/537.36');
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        return true;
    }

    public function Login()
    {
        return true;
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => ['EUR'],
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'EUR',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        if ($fields['DepDate'] > strtotime('+360 day')) {
            $this->SetWarning('You checked too late date');

            return ['routes' => []];
        }
        $warningMsg = null;

        $supportedCurrencies = $this->getRewardAvailabilitySettings()['supportedCurrencies'];

        if (!in_array($fields['Currencies'][0], $supportedCurrencies)) {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        $origins = \Cache::getInstance()->get('ra_tapportugal_origins');

        if (is_array($origins) && !in_array($fields['DepCode'], $origins)) {
            $this->SetWarning('No flights from ' . $fields['DepCode']);

            return ['routes' => []];
        }

//        if (!$this->validRoute($fields)) {
//            return ['routes' => []];
//        }
        if ($fields['Adults'] > 9) {
            $this->SetWarning("It's too much travellers");

            return ['routes' => []];
        }
        $counter = \Cache::getInstance()->get('ra_tapportugal_failed_auth');

        if ($counter && $counter > 100) {
            $this->logger->error('10 min downtime is on');

            throw new \CheckException('Login temporariamente indisponível.', ACCOUNT_PROVIDER_ERROR);
        }
//        $this->logger->error('temporary off parsing');
//
//        throw new \CheckException("Something went wrong", ACCOUNT_ENGINE_ERROR);
        try {
            if (!$this->isLoggedInOnStart && !$this->selenium($fields)) {
                throw new \CheckException("Something went wrong", ACCOUNT_ENGINE_ERROR);
            }
        } catch (\NoSuchDriverException $e) {
            $this->logger->error('NoSuchDriverException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error('Facebook\WebDriver\Exception\WebDriverCurlException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error('\Facebook\WebDriver\Exception\TimeoutException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($this->noRoute) {
            return ['routes' => []];
        }

        // New search
        $this->logger->info('TAP + ALLIANCE');

        $data = null;

        if (!empty($this->dataResponseAlliance)) {
            $data = $this->dataResponseAlliance;
            $this->http->SetBody($this->bodyResponseAlliance);
        } elseif (!isset($this->tapRoute, $this->starAllianceRoute) || $this->starAllianceRoute) {
            $data = $this->otherTypeSearch($fields, true);
        }
        $routes = [];

        if (isset($data)) {
            if (empty($data->data)) {
                if (isset($data->errors[0])) {
                    $warningMsg = $this->http->FindPreg('/"desc":"(NO ITINERARY FOUND FOR REQUESTED SEGMENT.+?)"/') ??
                        $this->http->FindPreg('/"desc":"(No available flight found for the requested segment.+?)"/') ??
                        $this->http->FindPreg('#"desc":"(Unknown City/Airport)"#') ??
                        $this->http->FindPreg('#"desc":"(Bad value \(coded\) - timeDetails)"#') ??
                        $this->http->FindPreg('/"desc":"(NO\s+FARE\s+FOUND\s+FOR\s+REQUESTED\s+ITINERARY)"/m');

                    if (!$warningMsg) {
                        if (strpos($this->http->Response['body'],
                            '"desc":"Transaction unable to process') !== false
                            || strpos($this->http->Response['body'],
                                '"code":"404","type":"ERROR"') !== false
                            || strpos($this->http->Response['body'],
                                '"code":"Read timed out","type":"ERROR","desc":"404"') !== false
                        ) {
                            throw new CheckRetryNeededException(5, 0);
                        }
                        $unknownErrorFromTapAlliance = $data->errors[0];
                        $this->logger->error('mem error on tap+alliance');
                    }
                } else {
                    if ($this->http->Response['code'] == 403) {
                        throw new CheckRetryNeededException(5, 0);
                    }

                    throw new \CheckException("Something went wrong", ACCOUNT_ENGINE_ERROR);
                }
            } elseif (empty($data->data->offers)) {
                $warningMsg = 'Select another date with available flights';
            } else {
                $routes = $this->parseRewardFlights($data, $fields, true);
            }
        }

        $this->logger->info('TAP ONLY');
        $data = null;

        if (!empty($this->dataResponseOnlyTap)) {
            $data = $this->dataResponseOnlyTap;
            $this->http->SetBody($this->bodyResponseOnlyTap);
        } elseif ($this->hasTapOnly || !isset($this->tapRoute, $this->starAllianceRoute)) {
            $data = $this->otherTypeSearch($fields, false);
        }

        if (isset($data)) {
            if (empty($data->data)) {
                if (isset($data->errors[0])) {
                    $warningMsg1 = $this->http->FindPreg('/"desc":"(NO ITINERARY FOUND FOR REQUESTED SEGMENT.+?)"/') ??
                        $this->http->FindPreg('/"desc":"(No available flight found for the requested segment.+?)"/') ??
                        $this->http->FindPreg('#"desc":"(Unknown City/Airport)"#') ??
                        $this->http->FindPreg('#"desc":"(Bad value \(coded\) - timeDetails)"#') ??
                        $this->http->FindPreg('/"desc":"(Read timed out)/') ??
                        $this->http->FindPreg('/"desc":"(NO\s+FARE\s+FOUND\s+FOR\s+REQUESTED\s+ITINERARY)"/m');

                    if (!$warningMsg1 && (empty($routes) || empty($warningMsg) || isset($unknownErrorFromTapAlliance))) {
                        if (empty($routes)) {
                            if (strpos($this->http->Response['body'], '"desc":"Transaction unable to process') !== false) {
                                throw new CheckRetryNeededException(5, 0);
                            }
                            $this->sendNotification('check error (2) // ZM');

                            throw new \CheckException("Something went wrong", ACCOUNT_ENGINE_ERROR);
                        }
                        $this->sendNotification('check error (3) // ZM');
                    } elseif ($warningMsg1 && empty($warningMsg)) {
                        $warningMsg = $warningMsg1;
                    }
                } elseif (empty($routes)) {
                    if ($this->http->Response['code'] == 403) {
                        throw new CheckRetryNeededException(5, 0);
                    }

                    throw new \CheckException("Something went wrong", ACCOUNT_ENGINE_ERROR);
                }
            } elseif (empty($data->data->offers)) {
                $warningMsg = 'Select another date with available flights';
            } else {
                $routesOnlyTap = $this->parseRewardFlights($data, $fields, false);

                if (!empty($routesOnlyTap) && $routesOnlyTap != $routes) {
                    $allRoutes = array_merge($routesOnlyTap, $routes);
                    $routes = array_map('unserialize', array_unique(array_map('serialize', $allRoutes)));
                }
            }
        }

        if (isset($this->changedCabin) && count($this->changedCabin) === 2 && isset($this->tapRoute, $this->starAllianceRoute) && $this->tapRoute && $this->starAllianceRoute && empty($warningMsg)) {
            $this->logger->notice('possible duplicates with different cabins'); // так-то руками не находились, но на всякий влог отметка
        }

        if (empty($routes) && !empty($warningMsg)) {
            if ($warningMsg === "Read timed out") {
                throw new \CheckException($warningMsg, ACCOUNT_PROVIDER_ERROR);
            }

            if ($warningMsg === "Bad value (coded) - timeDetails") {
                $warningMsg = 'Select another date with available flights';
            }
            $this->SetWarning($warningMsg);
        }

        return ['routes' => $routes];
    }

    private function getCabin(string $cabin, bool $isFlip = true)
    {
        $cabins = [
            'economy' => 'Economy', // basic
            //'premiumEconomy' => '', //
            'business'   => 'Business', //  executive
            //'firstClass' => 'First',
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

    private function getCabinNew(string $cabin): string
    {
        $cabins = [
            'economy'   => 'economy',
            'executive' => 'business',
        ];

        if (isset($cabins[$cabin])) {
            return $cabins[$cabin];
        }
        $this->sendNotification("RA check cabin {$cabin} // MI");

        throw new \CheckException("check cabin {$cabin}", ACCOUNT_ENGINE_ERROR);
    }

    private function getCabinAlliance(string $cabin): string
    {
        $cabins = [
            'economy'   => 'X',
            'executive' => 'I',
        ];

        if (isset($cabins[$cabin])) {
            return $cabins[$cabin];
        }
        $this->sendNotification("RA check cabin alliance {$cabin} // MI");

        throw new \CheckException("check cabin alliance {$cabin}", ACCOUNT_ENGINE_ERROR);
    }

    /*private function getCabinRbd(string $cabin): string
    {
        $cabins = [
            'X' => 'economy',
            'I' => 'business',
        ];

        if (isset($cabins[$cabin])) {
            return $cabins[$cabin];
        }
        $this->sendNotification("RA check cabin rbd {$cabin} // MI");

        throw new \CheckException("check cabin rbd {$cabin}", ACCOUNT_ENGINE_ERROR);
    }*/

    private function queryRewardFlights($fields, $starAwards = 'True')
    {
        $this->logger->notice(__METHOD__);
        $depDateString = date('d.m.Y', $fields['DepDate']);
        $depDate = date('Y-m-d\TH:i:s', $fields['DepDate']);
        $query = [
            'pageTrace'           => '21',
            'market'              => '',
            '_l'                  => 'en',
            'requestID'           => '5464608726960792937',
            'flightType'          => 'Single',
            'origin'              => $fields['DepCode'],
            'destination'         => $fields['ArrCode'],
            'negotiatedFaresOnly' => 'False',
            'milesAndCash'        => '0',
            'maxConn'             => '-1',
            'depDate'             => $depDateString,
            'adt'                 => $fields['Adults'],
            'resident'            => '',
            'giftCode'            => '&',
            'starAwards'          => $starAwards,
        ];

        $this->http->setOriginHeader = false;
        $this->http->GetURL('https://book.flytap.com/air/TAPMilesAndGo/SelectRedemption.aspx?' . http_build_query($query));

        parse_str(html_entity_decode($this->http->FindSingleNode("//form[@id='aspnetForm']/@action")), $output);
        $this->logger->debug(var_export($output, true));

        if (empty($output) || !isset($output['depDate'])) {
            if ($msg = $this->http->FindPreg("/(The selected route is not available. Please select a valid route using fields)/")) {
                $this->SetWarning($msg);

                return;
            }

            if ($this->http->FindPreg("/(We are experiencing technical problems at the moment and are unable to proceed with your request. Please try again later)/")) {
                throw new \CheckException('technical problems at the moment', ACCOUNT_PROVIDER_ERROR);
            }

            if (isset($output['_/Search_aspx?errorCode']) && $output['_/Search_aspx?errorCode'] === '3'
                && $this->http->FindSingleNode("//p[normalize-space()='Attention Please']/following-sibling::p[normalize-space()='URL malformed']")
            ) {
                if (strtotime("-1 day", $fields['DepDate']) < time()) {
                    $this->SetWarning('It is not possible to book online within less than 24 hours before flight departure. Please review your selection.');
                } else {
                    $this->SetWarning('The selected route is not available. Please review your selection.');
                    $this->sendNotification('check msg 2 // ZM');
                }

                return;
            }

            throw new \CheckException('something went wrong', ACCOUNT_ENGINE_ERROR);
        }
        $headers = [
            'X-Requested-With' => "XMLHttpRequest",
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'     => 'application/json; charset=UTF-8',
        ];
        $this->http->RetryCount = 0;
        $data = '{
          "availabilityRequest": {
            "segments": [
              {
                "Origin": "' . $fields['DepCode'] . '",
                "Destination": "' . $fields['ArrCode'] . '",
                "DepTime": null,
                "DepDate": "' . $depDate . '",
                "DepDateString": "' . $depDateString . '"
              }
            ],
            "loyaltyProgramMemberId": "' . $this->loyaltyMemberId . '",
            "tierLevel": "Miles",
            "starAwards": ' . strtolower($starAwards) . ',
            "_a": "",
            "origin": "' . $fields['DepCode'] . '",
            "destination": "' . $fields['ArrCode'] . '",
            "depDate": "' . $output['depDate'] . '",
            "retDate": "",
            "depTime": "",
            "retTime": "",
            "flightType": "Single",
            "adt": "' . $output['adt'] . '",
            "chd": "",
            "inf": "",
            "src": "",
            "stu": "",
            "yth": "",
            "yad": "0",
            "cabinClass": "",
            "_l": "en",
            "promoCode": "",
            "congressCode": "",
            "agentCode": "",
            "resident": "",
            "market": "",
            "contract": "TAPVictoria",
            "requestID": "' . $output['requestID'] . '",
            "sessionId": "",
            "pageTicket": "",
            "selectedBP": "",
            "_debug": false,
            "isB2B": false,
            "maxConn": "",
            "isPreviuosFpp": false,
            "negotiatedFaresOnly": false,
            "stopover": "",
            "nights": "0",
            "stayMulti": 0,
            "milesAndCash": 0,
            "uh": "' . $output['uh'] . '"
          }
        }';
        $this->http->PostURL('https://book.flytap.com/air/WebServices/Availability/TAPAvailability.asmx/GetMulticityFlights?_l=en',
            json_decode(json_encode($data)),
            $headers
        );
        $this->http->RetryCount = 2;
        $this->requestId = $output['requestID'];
    }

    private function otherTypeSearch($fields, $starAlliance = false)
    {
        $this->logger->notice(__METHOD__);
        // 401
        //$this->http->GetURL("https://booking.flytap.com/bfm/rest/search/pax/types?market=US&journeyList={$fields['DepCode']},{$fields['ArrCode']}&tripType=O");

        $dateStr = date('dmY', $fields['DepDate']);
        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Authorization' => 'Bearer ' . $this->sessionToken,
            'Content-Type'  => 'application/json',
            'Origin'        => 'https://booking.flytap.com',
            'Referer'       => 'https://booking.flytap.com/booking/flights',
        ];
        $payload = '{"adt":' .
            $fields['Adults'] . ',"airlineId":"TP","c14":0,"cabinClass":"E","chd":0,"departureDate":["' . $dateStr . '"],"destination":["' . $fields['ArrCode'] . '"],"inf":0,"language":"en-us","market":"US","origin":["' . $fields['DepCode'] . '"],"passengers":{"ADT":' . $fields['Adults'] . ',"YTH":0,"CHD":0,"INF":0},"returnDate":"' . $dateStr . '","tripType":"O","validTripType":true,"payWithMiles":true,"starAlliance":' . var_export($starAlliance, true) . ',"yth":0}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://booking.flytap.com/bfm/rest/booking/availability/search?payWithMiles=true&starAlliance=" . var_export($starAlliance, true), $payload, $headers, 30);
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] == 503
            || $this->http->FindPreg('/"status":"(500)"/')
            || strpos($this->http->Error, 'Network error 28 - Operation timed out after ') !== false
            || strpos($this->http->Error, 'Network error 28 - Connection timed out after ') !== false
        ) {
            sleep(5);
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://booking.flytap.com/bfm/rest/booking/availability/search?payWithMiles=true&starAlliance=" . var_export($starAlliance, true), $payload, $headers, 30);
            $this->http->RetryCount = 2;
        }

        if (strpos($this->http->Error, 'Network error 56 - Received HTTP code 407 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 28 - Operation timed out after ') !== false
            || strpos($this->http->Error, 'Network error 28 - Connection timed out after ') !== false
            || $this->http->Response['code'] == 403
            || strpos($this->http->Response['body'], '"desc":"Server is busy, please try again in a few minutes') !== false
            || strpos($this->http->Response['body'], '"desc":"Read timed out') !== false
        ) {
            throw new CheckRetryNeededException(5, 0);
        }

        if ($this->http->Response['code'] != 200) {
            throw new \CheckRetryNeededException(5, 0);
        }

        return $this->http->JsonLog();
    }

    private function parseRewardFlights($data, $fields = [], $starAlliance = true): array
    {
        $this->logger->notice(__METHOD__);
        $routes = [];

        foreach ($data->data->listOutbound as $outbound) {
            foreach ($outbound->relateOffer as $keyRoute => $rateOffer) {
                $offer = null;

                foreach ($data->data->offers->listOffers as $itemOffer) {
                    if ($itemOffer->idOffer == $rateOffer) {
                        $offer = $itemOffer;

                        break;
                    }
                }

                if (empty($offer)) {
                    $this->logger->info('skip offer ' . $rateOffer . ' no data');

                    continue;
                }
                $fareFamily = $offer->outFareFamily;
                //$cabin = $this->getCabinNew(strtolower($offer->outbound->cabin[0]));
                $route = [
                    'distance'  => null,
                    'num_stops' => $outbound->numberOfStops,
                    'times'     => [
                        'flight'  => $this->convertMinDuration($outbound->duration),
                        'layover' => null,
                    ],
                    'redemptions' => [
                        'miles'   => round($offer->outbound->totalPoints->price / $fields['Adults']),
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $data->data->offers->currency,
                        'taxes'    => round(($offer->outbound->totalPrice->tax + $offer->outbound->totalPrice->obFee) / $fields['Adults'], 2),
                        'fees'     => null,
                    ],
                    'connections'     => [],
                    'tickets'         => null,
                    'award_type'      => null,
                    'classOfService'  => $this->convertClassOfService($offer->outFareFamily),
                ];
                // Connections
                foreach ($outbound->listSegment as $keyConn => $segment) {
                    if (!empty($offer->outbound->cabin)) {
                        $cabin = $this->getCabinNew(strtolower($offer->outbound->cabin[$keyConn]));
                    } else {
                        $this->logger->info('skip offer ' . $rateOffer . ' no cabins, sold out');

                        break 2;
                    }
                    //$bounds = [$offer->outbound, $offer->inbound];
                    $rbd = $offer->outbound->rbd[$keyConn];

                    if (!$starAlliance && in_array($fareFamily, ['AWEXECU', 'AWEXENEW']) && !in_array($rbd, ['I', 'Z'])) {
                    } elseif ($outbound->numberOfStops > 0 && /*$starAlliance &&*/ in_array($fareFamily, ['AWEXECU', 'AWEXENEW', 'AWFIRST']) && $rbd != 'I') {
                        $this->logger->notice("Change $cabin for economy");

                        if ($cabin !== 'economy') {
                            if (isset($this->changedCabin)) {
                                $this->changedCabin[$starAlliance] = true;
                            } else {
                                $this->changedCabin = [$starAlliance => true];
                            }
                        }
                        $cabin = 'economy';
                    } elseif (in_array($fareFamily, ['AWEXECU', 'AWEXENEW']) && !in_array($rbd, ['C', 'Z', 'I', 'J'])) {
                    }
                    //
                    // Sd = ["C", "Z", "I", "J"], Md = ["I", "Z"], kd = ["I"],
                    /*e.d(t, "wl", (function() {
                        return Sd
                    }
                    )),
                    e.d(t, "vl", (function() {
                        return Md
                    }
                    )),
                    e.d(t, "nl", (function() {
                        return kd
                    }
                    )),*/

                    $route['connections'][] = [
                        'num_stops' => count($segment->technicalStops ?? []),
                        'departure' => [
                            'date'     => date('Y-m-d H:i', strtotime($segment->departureDate)),
                            'dateTime' => strtotime($segment->departureDate),
                            'airport'  => $segment->departureAirport,
                            'terminal' => $segment->departureTerminal,
                        ],
                        'arrival' => [
                            'date'     => date('Y-m-d H:i', strtotime($segment->arrivalDate)),
                            'dateTime' => strtotime($segment->arrivalDate),
                            'airport'  => $segment->arrivalAirport,
                            'terminal' => $segment->arrivalTerminal,
                        ],
                        'meal'       => null,
                        'cabin'      => $cabin,
                        //'fare_class' => $starAlliance ? $this->getCabinAlliance($cabin) : null,
                        'flight'     => ["{$segment->carrier}{$segment->flightNumber}"],
                        'airline'    => $segment->carrier,
                        'operator'   => $segment->operationCarrier,
                        'distance'   => null,
                        'aircraft'   => $segment->equipment,
                        'times'      => [
                            'flight'  => $this->convertMinDuration($segment->duration),
                            'layover' => $this->convertMinDuration($segment->stopTime),
                        ],
                    ];
                }
                $route['num_stops'] = count($route['connections']) - 1 + array_sum(array_column($route['connections'], 'num_stops'));
                $this->logger->debug(var_export($route, true), ['pre' => true]);
                $routes[] = $route;
            }
        }

        return $routes;
    }

    private function convertClassOfService(string $str): ?string
    {
        switch ($str) {
            case "AWBASIC":
            case "AWBASINT":
            case "AWCLANEW":
                return 'Economy';

            case "AWEXECU":
            case "AWEXEINT":
            case "AWEXENEW":
                return 'Business';
        }
        $this->sendNotification('check outFareFamily: ' . $str);

        return null;
    }

    private function parseRewardFlightsOld($data, $fields = []): array
    {
        $routes = [];

        foreach ($data->PriceOptionCollection as $price) {
            $route = [
                'distance'  => null,
                'num_stops' => null,
                'times'     => [
                    'flight'  => null,
                    'layover' => null,
                ],
                'redemptions' => [
                    'miles'   => $price->DisplayPriceWithDiscount / $fields['Adults'],
                    'program' => $this->AccountFields['ProviderCode'],
                ],
                'payments' => [
                    'currency' => $price->DisplayCashCurrency,
                    'taxes'    => $price->DisplayCashPrice / $fields['Adults'],
                    'fees'     => null,
                ],
                'connections' => [],
                'tickets'     => null,
                'award_type'  => null,
            ];

            if (count($price->LegCollection) > 1) {
                $this->sendNotification('RA check LegCollection // MI');

                throw new \CheckException('RA check LegCollection', ACCOUNT_ENGINE_ERROR);
            }

            foreach ($price->LegCollection as $leg) {
                foreach ($leg->Segments as $segment) {
                    $route['connections'][] = [
                        'departure' => [
                            'date'     => date('Y-m-d H:i', strtotime($segment->DepDate)),
                            'dateTime' => strtotime($segment->DepDate),
                            'airport'  => $segment->OriginTLC,
                            'terminal' => $segment->OriginTerminal,
                        ],
                        'arrival' => [
                            'date'     => date('Y-m-d H:i', strtotime($segment->ArrDate)),
                            'dateTime' => strtotime($segment->ArrDate),
                            'airport'  => $segment->DestinationTLC,
                            'terminal' => $segment->DestinationTerminal,
                        ],
                        'meal'       => null,
                        'cabin'      => $this->getCabin($segment->CabinClass),
                        'fare_class' => $segment->BookingClassCode,
                        'flight'     => ["{$segment->FlightString}"],
                        'airline'    => $segment->CarrierCode,
                        'operator'   => $segment->CarrierCode,
                        'distance'   => null,
                        'aircraft'   => $segment->Aircraft,
                        'times'      => [
                            'flight'  => $this->convertDuration($segment->Duration),
                            'layover' => $this->convertDuration($segment->CalculatedLayover),
                        ],
                    ];
                }
                $route['num_stops'] = $leg->LayoverCount;
                $route['award_type'] = $leg->BrandedProduct;
                $route['times'] = [
                    'flight' => $this->sumLayovers($route['connections'], 'flight'),
                    //                    'flight' => $this->convertDuration($leg->LegDuration),
                    'layover' => $this->sumLayovers($route['connections']),
                ];
            }

            $this->logger->debug('Parsed data:');
            $this->logger->debug(var_export($route, true), ['pre' => true]);
            $routes[] = $route;
        }

        return $routes;
    }

    private function convertMinDuration($minutes)
    {
        $format = gmdate('H:i', $minutes * 60);

        if ($format == '00:00') {
            return null;
        }

        return $format;
    }

    private function convertDuration($duration)
    {
        if (preg_match("/^(\d+)\s*[hrs]+\s*(\d+)\s*[min]+$/", $duration, $m)) {
            return sprintf('%02d:%02d', $m[1], $m[2]);
        } elseif (preg_match("/^(\d+)\s*[min]+$/", $duration, $m)) {
            return sprintf('%02d:%02d', 0, $m[1]);
        }

        return null;
    }

    private function sumLayovers($connections, $fieldName = 'layover')
    {
        $minutesLayover = 0;

        foreach ($connections as $value) {
            if (isset($value['times'][$fieldName])) {
                [$hour, $minute] = explode(':', $value['times'][$fieldName]);
                $minutesLayover += $hour * 60;
                $minutesLayover += $minute;
            }
        }
        $hoursLayover = floor($minutesLayover / 60);
        $minutesLayover -= floor($minutesLayover / 60) * 60;

        return ($hoursLayover + $minutesLayover > 0) ?
            sprintf('%02d:%02d', $hoursLayover, $minutesLayover) : null;
    }

    private function selenium($fields): bool
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();
        $login = false;
        $retry = false;
        $selenium = clone $this;
        $this->selenium = true;
        $this->http->brotherBrowser($selenium->http);
        $error = null;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            if (!isset($this->State["Resolution"])) {
                $resolutions = [
                    //                    [1152, 864],
                    //                    [1280, 720],
                    [1280, 768],
                    [1280, 800],
                    [1360, 768],
                    //[1920, 1080],
                ];
                $this->State["Resolution"] = $resolutions[array_rand($resolutions)];
            }

            $selenium->setScreenResolution($this->State["Resolution"]);
//            $selenium->useChromium();
//            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_100);

            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $this->seleniumRequest->setOs(\SeleniumFinderRequest::OS_LINUX);
            $request = FingerprintRequest::firefox();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
                $selenium->seleniumOptions->setResolution([
                    $fingerprint->getScreenWidth(),
                    $fingerprint->getScreenHeight(),
                ]);
            }

            $selenium->disableImages();
            $selenium->useCache();
            $selenium->usePacFile(false);

            $selenium->http->saveScreenshots = true;
            $selenium->seleniumRequest->setHotSessionPool(self::class, $this->AccountFields['ProviderCode']);

            try {
                $selenium->http->start();
                $selenium->Start();
            } catch (\UnknownServerException | \TimeOutException | \ErrorException $e) {
                $this->markProxyAsInvalid();
                $this->logger->error("exception: " . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            } catch (\UnknownErrorException
                | \Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->markProxyAsInvalid();
                $this->logger->error("exception: " . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }

            try {
                $selenium->http->GetURL("https://booking.flytap.com/booking", [], 15);
            } catch (\TimeOutException | \UnknownServerException | \NoSuchDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage());

                throw new CheckRetryNeededException(5, 0);
            } catch (\UnexpectedAlertOpenException | \Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("Exception: " . $e->getMessage());

                try {
                    $selenium->http->GetURL("https://booking.flytap.com/booking");
                } catch (\UnexpectedAlertOpenException | \Facebook\WebDriver\Exception\UnknownErrorException | \WebDriverException  $e) {
                    throw new CheckRetryNeededException(5, 0);
                }
            } catch (\Facebook\WebDriver\Exception\WebDriverException | \WebDriverException  $e) {
                throw new CheckRetryNeededException(5, 0);
            }

            try {
                $this->savePageToLogs($selenium);
            } catch (\UnexpectedJavascriptException | \WebDriverException | \Facebook\WebDriver\Exception\WebDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage());

                throw new CheckRetryNeededException(5, 0);
            }

            if ($this->http->FindSingleNode('//span[contains(., "Bad gateway")]')) {
                throw new \CheckException('HOST ERROR', ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->isBadProxy()) {
                $this->DebugInfo = "bad proxy";
                $this->markProxyAsInvalid();

                throw new \CheckRetryNeededException(5, 0);
            }

            $this->acceptCookie($selenium);

            $logo = $selenium->waitForElement(WebDriverBy::xpath("//a//*[@alt='TAP Air Portugal logo']|//*[@class='flight-actions__item flight-search']"), 30);

            if ($selenium->waitForElement(\WebDriverBy::xpath("//p[contains(normalize-space(), 'Estamos fazendo melhorias em nosso mecanismo de reservas. Pedimos desculpas pela inconveniência.')]"), 0)) {
                throw new \CheckException('Estamos fazendo melhorias em nosso mecanismo de reservas. Pedimos desculpas pela inconveniência.', ACCOUNT_PROVIDER_ERROR);
            }

            if ($selenium->waitForElement(\WebDriverBy::xpath("//h1[contains(text(),'Voltaremos em breve')]"), 0)) {
                throw new \CheckException('Technical works.', ACCOUNT_PROVIDER_ERROR);
            }

//            try {
//                $this->savePageToLogs($selenium);
//            } catch (\NoSuchDriverException | \UnknownCommandException $e) {
//                $this->logger->error("exception: " . $e->getMessage());
//            }

            if (!$logo) {
                throw new \CheckRetryNeededException(5, 0);
            }

            try {
                $script = "return sessionStorage.getItem('userData');";
                $this->savePageToLogs($selenium);
                $this->logger->debug("[run script]");
                $this->logger->debug($script, ['pre' => true]);
                $userData = $selenium->driver->executeScript($script);
            } catch (\UnknownServerException $e) {
                $this->logger->error('UnknownServerException: ' . $e->getMessage());

                throw new CheckRetryNeededException(5, 0);
            } catch (\WebDriverException | \Facebook\WebDriver\Exception\WebDriverException $e) {
                $this->logger->error("WebDriverException: " . $e->getMessage());

                throw new CheckRetryNeededException(5, 0);
            }

//            if ($this->http->FindSingleNode('(//div[contains(@class,"header-fallback__user")][normalize-space()!="Login"])[1]')) {
            if (!empty($userData)) {
                $this->logger->debug("logged in");
                $login = true;
            } else {
                $this->savePageToLogs($selenium);

                try {
                    $login = $this->auth($selenium);
                } catch (\NoSuchDriverException $e) {
                    $this->logger->error('NoSuchDriverException: ' . $e->getMessage());

                    throw new CheckRetryNeededException(5, 0);
                }

                if (!isset($login)) {
                    return false;
                }
            }

            if ($login) {
                $script = "return sessionStorage.getItem('userData');";
                $this->logger->debug("[run script]");
                $this->logger->debug($script, ['pre' => true]);
                $userData = $selenium->driver->executeScript($script);

                if (!empty($userData)) {
                    $data = $this->http->JsonLog($userData, 1);

                    if ($data) {
                        if (isset($data->ffCarrier, $data->ffNumber)) {
                            $this->loyaltyMemberId = $data->ffCarrier . $data->ffNumber;
                        }

                        if (isset($data->flyTapLogin)) {
                            $this->token = $data->flyTapLogin;
                        }
                    }
                }
                $script = "return sessionStorage.getItem('token');";
                $this->logger->debug("[run script]");
                $this->logger->debug($script, ['pre' => true]);
                $this->sessionToken = $selenium->driver->executeScript($script);
                $this->sessionToken = trim($this->sessionToken, '"');
                $this->logger->debug('token ' . $this->sessionToken);

                if (strpos($this->sessionToken, '/session/') !== false) {
                    $this->logger->warning('selenium failed');

                    throw new \CheckRetryNeededException(5, 0);
//                    if (!$this->refreshToken($selenium)) {
//                        throw new \CheckRetryNeededException(5, 0);
//                    }
                }

                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                        $cookie['expiry'] ?? null);
                }

                try {
                    $this->savePageToLogs($selenium);

                    if ($message = $selenium->waitForElement(\WebDriverBy::xpath("//h1[contains(.,'Upgrade to a Miles')]"), 3)) {
                        throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
                    }

                    $this->hasTapOnly = $this->checkRouteData($selenium, $fields);

                    if ($this->noRoute) {
                        $this->logger->notice('Data ok, saving session');
                        $selenium->keepSession(true);

                        return true;
                    }

                    if (isset($this->tapRoute, $this->starAllianceRoute)) {
                        if ($this->tapRoute) {
                            $this->dataResponseOnlyTap = $this->tryAjax($selenium, $fields, false);
                        }

                        if ($this->starAllianceRoute) {
                            $this->dataResponseAlliance = $this->tryAjax($selenium, $fields);
                        }
                    } else {
                        $this->dataResponseAlliance = $this->tryAjax($selenium, $fields);

                        if ($this->hasTapOnly) {
                            $this->dataResponseOnlyTap = $this->tryAjax($selenium, $fields, false);
                        }
                    }
                } catch (\UnexpectedJavascriptException | \Facebook\WebDriver\Exception\InvalidSelectorException
                | \WebDriverException | \Facebook\WebDriver\Exception\WebDriverException $e) {
                    $this->logger->error($e->getMessage());

                    if (!isset($this->bodyResponseOnlyTap)) {
                        $this->dataResponseOnlyTap = null;
                        $this->bodyResponseOnlyTap = null;
                    }

                    if (!isset($this->bodyResponseAlliance)) {
                        $this->dataResponseAlliance = null;
                        $this->bodyResponseAlliance = null;
                    }
                } catch (\NoSuchWindowException | \UnknownServerException $e) {
                    $this->logger->error($e->getMessage());

                    throw new \CheckRetryNeededException(5, 0);
                }

                if (isset($this->dataResponseAlliance) && (!$this->hasTapOnly || isset($this->dataResponseOnlyTap))) {
                    $this->logger->notice('Data ok, saving session');
                    $selenium->keepSession(true);
                    $dataOk = true;
                }
            }

//            if (!$login && $selenium->waitForElement(\WebDriverBy::xpath("//h2[@id='modal__title'][normalize-space()='Login']"), 30)) {
//                throw new CheckException('No login form', ACCOUNT_PROVIDER_ERROR);
//            }

            try {
                $this->logger->debug("[Current Selenium URL]: {$selenium->http->currentUrl()}");
            } catch (\NoSuchDriverException $e) {
                $this->logger->error($e->getMessage());

                if (!isset($dataOk)) {
                    throw new CheckRetryNeededException(5, 0);
                }
            }
        } catch (ScriptTimeoutException | \Facebook\WebDriver\Exception\ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $retry = true;
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            // retries
            if (stripos($e->getMessage(), 'Element not found in the cache') !== false) {
                $retry = true;
            }
        } catch (\UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage());
            $this->logger->debug("Need to change ff version");
            $retry = true;
        } catch (\WebDriverCurlException | \WebDriverException
        | \Facebook\WebDriver\Exception\InvalidSessionIdException
        | \Facebook\WebDriver\Exception\UnrecognizedExceptionException
        | \Facebook\WebDriver\Exception\NoSuchWindowException
        | \Facebook\WebDriver\Exception\WebDriverException
        $e) {
            $this->logger->error($e->getMessage());
            $retry = true;
        } catch (\ErrorException | \TypeError $e) {
            $this->logger->error($e->getMessage(), ['pre' => true]);
            $this->logger->error($e->getTraceAsString(), ['pre' => true]);

            if (strpos($e->getMessage(), 'Array to string conversion') !== false
                || strpos($e->getMessage(), 'strlen() expects parameter 1 to be string, array given') !== false
                || strpos($e->getMessage(), 'expects parameter 2 to be string, array given') !== false
                || strpos($e->getMessage(), 'expects parameter 1 to be string, array given') !== false
                || strpos($e->getMessage(), 'strpos(): needle is not a string or an integer') !== false
                || strpos($e->getMessage(), 'must be of the type string or null, array given') !== false
                || strpos($e->getMessage(),
                    'Argument 1 passed to Facebook\WebDriver\Remote\JsonWireCompat::getElement()') !== false
            ) {
                $retry = true;
            } else {
                throw $e;
            }
        } catch (\UnknownCommandException
        | \Facebook\WebDriver\Exception\UnknownCommandException $e) {
            $this->logger->error('UnknownCommandException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(5, 0);
            }
        }
        $this->getTime($startTimer);

        if (!is_null($error)) {
            $this->logger->error($error);

            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }

        return $login;
        // if old version with  queryRewardFlights
//        return $login && isset($this->loyaltyMemberId);
    }

    private function refreshToken($selenium)
    {
        $refreshTokenScript = '
                                if (document.querySelector("#tokenTab")) document.querySelector("#tokenTab").remove();

                                fetch("https://booking.flytap.com/bfm/rest/session/resetValues", {
                                    "credentials": "include",
                                    "headers": {
                                        "Accept": "application/json, text/plain, * /*",
                                        "Accept-Language": "en-US,en;q=0.5",
                                        "Authorization": "Bearer ' . $this->sessionToken . '",
                                        "Content-Type": "application/json",
                                        "Sec-Fetch-Dest": "empty",
                                        "Sec-Fetch-Mode": "cors",
                                        "Sec-Fetch-Site": "same-origin",
                                        "Pragma": "no-cache",
                                        "Cache-Control": "no-cache"
                                    },
                                    "referrer": "https://booking.flytap.com/booking",
                                    "body": "{\"clientId\":\"-bqBinBiHz4Yg+87BN+PU3TaXUWyRrn1T/iV/LjxgeSA=\",\"clientSecret\":\"DxKLkFeWzANc4JSIIarjoPSr6M+cXv1rcqWry2QV2Azr5EutGYR/oJ79IT3fMR+qM5H/RArvIPtyquvjHebM1Q==\",\"referralId\":\"h7g+cmbKWJ3XmZajrMhyUpp9.cms35\",\"market\":\"US\",\"language\":\"en-us\",\"userProfile\":null,\"appModule\":\"0\"}",
                                    "method": "POST",
                                    "mode": "cors"
                                })
                                .then( response => response.json())
                                .then( result => {
                                    let script = document.createElement("script");
                                    let id = "tokenTab";
                                    script.id = id;
                                    script.setAttribute(id, JSON.stringify(result));
                                    document.querySelector("body").append(script);
                                });';
        $this->logger->info($refreshTokenScript, ['pre' => true]);
        $selenium->driver->executeScript($refreshTokenScript);

        $tokenTab = $selenium->waitForElement(\WebDriverBy::xpath('//script[@id="tokenTab"]'), 10, false);
        $selenium->saveResponse();

        if (!$tokenTab) {
            $this->logger->error("can't refresh token");

            return false;
        }
        $resString = $tokenTab->getAttribute("tokenTab");
        $resString = htmlspecialchars_decode($resString);
        $data = $this->http->JsonLog($resString);

        if (isset($data->status) && $data->status == 200) {
//                $this->sessionToken = $data->userProfile->flyTapLogin;
            $this->sessionToken = $data->id;
            $this->logger->debug('reset token ' . $this->sessionToken);
        }

        return true;
    }

    private function acceptCookie($selenium)
    {
        $accept = $selenium->waitForElement(\WebDriverBy::xpath('//button[@id="onetrust-accept-btn-handler"]'), 0);

        if ($accept) {
            $this->logger->debug("click accept");
            $accept->click();
            $this->waitFor(function () use ($selenium) {
                return !$selenium->waitForElement(\WebDriverBy::xpath('//button[@id="onetrust-accept-btn-handler"]'), 0);
            }, 20);
        }
    }

    private function auth($selenium): ?bool
    {
        $this->logger->notice(__METHOD__);
        $login = false;

        if ($btn = $selenium->waitForElement(\WebDriverBy::xpath("//a[normalize-space()='Login' or normalize-space()='header.text.logIn']"),
            25)) {
            if ($selenium->waitForElement(\WebDriverBy::xpath("//a[normalize-space()='header.text.logIn']"), 0)) {
                try {
                    $selenium->http->GetURL("https://booking.flytap.com/booking");
                    $this->savePageToLogs($selenium);

                    // 502 Bad Gateway
                    if ($this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')) {
                        if ($this->attempt == 0) {
                            throw new CheckRetryNeededException(5, 0);
                        }

                        throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    if ($this->isBadProxy()) {
                        $this->markProxyAsInvalid();

                        throw new CheckRetryNeededException(5, 0);
                    }
                } catch (\TimeOutException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());

                    throw new CheckRetryNeededException(5, 0);
                } catch (\WebDriverException | \Facebook\WebDriver\Exception\WebDriverException $e) {
                    $this->logger->error("WebDriverException: " . $e->getMessage());

                    throw new CheckRetryNeededException(5, 0);
                }
            }

            if ($selenium->waitForElement(\WebDriverBy::xpath("//a[normalize-space()='header.text.logIn']"), 10)) {
                $this->logger->error('header.text.logIn');

                throw new CheckRetryNeededException(5, 0);
            }
        }

        $this->acceptCookie($selenium);

        if (!$btn = $selenium->waitForElement(\WebDriverBy::xpath("//a[normalize-space()='Login' or normalize-space()='header.text.logIn']"), 0)) {
            try {
                $this->logger->debug("[run js]: document.querySelector('#pay-miles').click();");
                $selenium->driver->executeScript("document.querySelector('#pay-miles').click();");
            } catch (\UnexpectedJavascriptException
            | \UnknownCommandException $e) {
                $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            } catch (\Facebook\WebDriver\Exception\JavascriptErrorException $e) {
                $this->logger->error('JavascriptErrorException: ' . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            } catch (\Facebook\WebDriver\Exception\WebDriverException $e) {
                $this->logger->error('WebDriverException: ' . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }
        }

        if ($btn = $selenium->waitForElement(\WebDriverBy::xpath("//a[normalize-space()='Login']"), 5)) {
            $this->logger->debug("login click");

            try {
                $btn->click();
            } catch (\UnrecognizedExceptionException $e) {
                $this->logger->error('UnrecognizedExceptionException: ' . $e->getMessage());
                $this->savePageToLogs($selenium);

                throw new \CheckRetryNeededException(5, 0);
            } catch (\Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
                $selenium->driver->executeScript("document.querySelector('.header-fallback__user-name').click();");
            } catch (\WebDriverException | \Facebook\WebDriver\Exception\WebDriverException $e) {
                $this->logger->error("WebDriverException: " . $e->getMessage());

                throw new CheckRetryNeededException(5, 0);
            }
        }

        try {
            $this->savePageToLogs($selenium);
        } catch (\UnknownCommandException $e) {
            $this->logger->error('UnknownCommandException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\WebDriverException | \Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("WebDriverException: " . $e->getMessage());

            throw new CheckRetryNeededException(5, 0);
        }

        $loginInput = $selenium->waitForElement(\WebDriverBy::xpath('//input[@id="login"]'), 10, false);
        $passwordInput = $selenium->waitForElement(\WebDriverBy::xpath('//input[@id="login-password"]'), 0, false);
        $button = $selenium->waitForElement(\WebDriverBy::xpath("//button[@type='submit'][normalize-space()='Login' or normalize-space()='Log in' or normalize-space()='header.text.logIn']"), 0);

        if (!$loginInput || !$passwordInput || !$button) {
            $this->savePageToLogs($selenium);
            $this->logger->error('login form not load');

            return null;
        }

        if (!isset($this->AccountFields['Login']) || !isset($this->AccountFields['Pass'])) {
            throw new CheckRetryNeededException(5, 0);
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        if (!$button) {
            $this->savePageToLogs($selenium);

            return null;
        }

        try {
            $button->click();
        } catch (\Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
            $selenium->driver->executeScript("document.querySelector('button[type=\"submit\"]').click();");
        } catch (\WebDriverException | \Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("WebDriverException: " . $e->getMessage());

            throw new CheckRetryNeededException(5, 0);
        }

        if ($selenium->waitForElement(\WebDriverBy::xpath("//span[contains(normalize-space(), 'Login temporariamente indisponível. Reservas online e check-in a funcionar corretamente.')]"), 10)) {
            $counter = \Cache::getInstance()->get('ra_tapportugal_failed_auth');

            if (!$counter) {
                $counter = 0;
            }
            $counter++;
            \Cache::getInstance()->set('ra_tapportugal_failed_auth', $counter, 10 * 60); // 10min

            throw new \CheckException('Login temporariamente indisponível.', ACCOUNT_PROVIDER_ERROR);
        }

        try {
            $this->savePageToLogs($selenium);
        } catch (\UnknownCommandException $e) {
            $this->logger->error('UnknownCommandException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\WebDriverException | \Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("WebDriverException: " . $e->getMessage());

            throw new CheckRetryNeededException(5, 0);
        }

        $sleep = 25;
        $startTime = time();

        $scriptUserData = "return sessionStorage.getItem('userData');";
        $this->logger->debug("[script scriptUserData]");
        $this->logger->debug($scriptUserData, ['pre' => true]);

        while (((time() - $startTime) < $sleep) && !$login) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");

            if ($selenium->waitForElement(WebDriverBy::xpath('(//div[contains(@class,"header-fallback__user")][normalize-space()!="Login"])[1]'), 0, false)) {
                $login = true;
                $this->savePageToLogs($selenium);

                break;
            }
            $this->logger->debug("[run script scriptUserData]");
            $userData = $selenium->driver->executeScript($scriptUserData);

            if (!empty($userData)) {
                $this->logger->debug("logged in");
                $login = true;
                $this->savePageToLogs($selenium);

                break;
            }

            if ($message = $selenium->waitForElement(WebDriverBy::xpath("//h5[contains(.,'Login Error')]/following-sibling::div[1][contains(.,'Algo correu mal. Tente mais tarde.')]"), 0)) {
                if ($this->attempt >= 3 || (time() - $this->requestDateTime) > 90) {
                    throw new \CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
                }

                throw new CheckRetryNeededException(5, 0);
            }

            if ($message = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class,'form-errors')]"), 0)
            ) {
                $error = $this->http->FindPreg("/^[\w.]*\:\:?\s*([^<]+)/ims", false, $message->getText());

                if (!$error) {
                    $error = $message->getText();
                }
                $this->logger->error($error);

                if (strpos($error,
                        "Sorry, it is currently not possible to validate the information provided. Please try again later.") !== false
                    || strpos($error,
                        "E-mail ou número de cliente (TP): Campo obrigatório") !== false
                    || strpos($error,
                        "Lamentamos, mas de momento não é possível validar as informações fornecidas. Por favor, tente novamente mais tarde.") !== false
                    || strpos($error, "header.login.errorLoginRequiredOrInvalid") !== false
                    || strpos($error, "header.login.error.userUnknown") !== false
                    || strpos($error, "O login do utilizador que inseriu não é válido") !== false
                    || strpos($error, "Palavra-passe: campo obrigatório.") !== false
                ) {
                    throw new CheckRetryNeededException(5, 0);
                }
                $this->sendNotification('check msg // ZM');

                break;
            }

            if ($message = $selenium->waitForElement(WebDriverBy::xpath("//span[contains(.,'O estado da sua conta não lhe permite aceder a este link. Por favor, contacte o serviço de apoio Miles & Go')]"), 0)
            ) {
                $msg = $message->getText();
                $this->logger->error($msg);

                if ($this->attempt >= 3 || (time() - $this->requestDateTime) > 90) {
                    throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
                }

                throw new CheckRetryNeededException(5, 0);
            }

            if ($message = $selenium->waitForElement(WebDriverBy::xpath("//*[contains(normalize-space(text()),'Login temporariamente indisponivel. Reservas online"), 0)) {
                $msg = $message->getText();
                $this->logger->error($msg);
                $this->sendNotification('check msg // ZM');

                if ($this->attempt >= 3 || (time() - $this->requestDateTime) > 90) {
                    throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
                }

                throw new CheckRetryNeededException(5, 0);
            }
            $this->savePageToLogs($selenium);
        }

        if (!$login) {
            $script = "return sessionStorage.getItem('userData');";
            $this->logger->debug("[run script]");
            $this->logger->debug($script, ['pre' => true]);
            $userData = $selenium->driver->executeScript($script);

            if (!empty($userData)) {
                $this->logger->debug("logged in");
                $login = true;
                $this->savePageToLogs($selenium);
            }
        }

        try {
            $selenium->driver->executeScript("
            if (document.querySelector('#pay-miles') && !document.querySelector('#pay-miles').checked)
                document.querySelector('#pay-miles').click();
        ");
        } catch (\UnexpectedJavascriptException $e) {
            $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }
        $this->savePageToLogs($selenium);

        return $login;
    }

    private function tryAjax($selenium, $fields, ?bool $alliance = true)
    {
        $this->logger->notice(__METHOD__);
        //sleep(5);
        $dateStr = date('dmY', $fields['DepDate']);
        $dateStrCheck = date('Y-m-d', $fields['DepDate']);

        if (empty($this->sessionToken)) {
            return null;
        }

        if ($alliance) {
            $this->logger->info('tap+alliance');
            sleep(2);
        } else {
            $this->logger->info('tap only');
        }
        $selenium->driver->executeScript('localStorage.removeItem("tapResponseAjax");');

        $tt = $this->getRequestScript($fields, $dateStr, $alliance);
        $this->logger->debug($tt, ['pre' => true]);

        try {
            $returnData = $selenium->driver->executeScript($tt);
        } catch (\Facebook\WebDriver\Exception\ScriptTimeoutException | \WebDriverException $e) {
            $this->logger->error('[ScriptTimeoutException]: ' . $e->getMessage());
            $selenium->driver->executeScript('window.stop();');
            sleep(2);
            $returnData = $selenium->driver->executeScript($tt);
        } catch (\Facebook\WebDriver\Exception\JavascriptErrorException  $e) {
            $this->logger->error('[JavascriptErrorException]: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        $res = $this->http->JsonLog($returnData);

        if (!$res) {
            $this->logger->debug($returnData, ['pre' => true]);

            if (strpos($returnData, '/session/') !== false) {
                $this->logger->warning('selenium failed');

                throw new \CheckRetryNeededException(5, 0);
            }

            if (empty($this->bodyResponseOnlyTap) && $alliance) {
                $this->logger->error("no data, restart");

                throw new \CheckRetryNeededException(5, 0);
            }
            $this->logger->error("no data");

            return null;
        }

        if (strpos($returnData, '"desc":"Invalid FlightSearch data') !== false) {
            // перелеты могут быть. ретрай не помогает вообще, только полный рестарт
            throw new CheckRetryNeededException(5, 10);
        }

        $memReturnData = null;

        if (empty($returnData)
            || (
                strpos($returnData, '"errors":[{"code":') === false
                && strpos($returnData, 'departureDate":"' . $dateStrCheck) === false
            )
            || strpos($returnData, '"desc":"Read timed out') !== false
            || strpos($returnData, '"desc":"Past date/time not allowed"') !== false
            || strpos($returnData, '"desc":"Bad value (coded) - timeDetails"') !== false
            || strpos($returnData, '"code":"500","type":"ERROR"') !== false
            || strpos($returnData, '"desc":"11|Session|"') !== false
            || strpos($returnData, '"errors":[{"code":"931","type":"ERROR"') !== false // крайне редко, но бывает ложной ошибкой. перелеты на самом деле есть, а отдает, что не нашел
            || strpos($returnData, '"desc":"Server is busy, please try again in a few minutes') !== false
            || $this->http->FindPregAll('/<body>Bad Request<\/body>/', $returnData, PREG_PATTERN_ORDER, false, false)
            || strpos($returnData, '"desc":"42|Application|Too many opened conversations. Please close them and try again') !== false
            // Transaction unable to process : TECH INIT   ||   Transaction unable to process : AVL
            || strpos($returnData, '"desc":"Transaction unable to process') !== false
        ) {
            // refresh token - ни разу не сработал
            /*if (!$this->refreshToken($selenium)) {
                throw new CheckRetryNeededException(5, 10);
            }
            sleep(2);*/

            $script = "return sessionStorage.getItem('token');";
            $this->logger->debug("[run script]");
            $this->logger->debug($script, ['pre' => true]);
            $this->sessionToken = $selenium->driver->executeScript($script);
            $this->sessionToken = trim($this->sessionToken, '"');
            $this->logger->debug('token ' . $this->sessionToken);

            if (strpos($this->sessionToken, '/session/') !== false) {
                $this->logger->warning('selenium failed');

                throw new \CheckRetryNeededException(5, 0);
            }

            $tt = $this->getRequestScript($fields, $dateStr, $alliance);

            if (strpos($returnData, '"errors":[{"code":"931","type":"ERROR"') !== false
                || strpos($returnData, '"desc":"Server is busy, please try again in a few minutes') !== false
            ) {
                $this->logger->debug("set mem returnData");
                $memReturnData = $returnData; // т.к не всегда ложный ответ
            }
            // helped
            $this->logger->debug($tt, ['pre' => true]);
            $returnData = $selenium->driver->executeScript($tt);
            $this->logger->debug("new returnData");
            $res = $this->http->JsonLog($returnData);
        }

        if (
            !empty($returnData)
            && strpos($returnData, '"errors":[{"code":') === false
            && strpos($returnData, 'departureDate":"' . $dateStrCheck) === false
        ) {
            $this->logger->error('wrong response/departureDate');

            throw new CheckRetryNeededException(5, 10);
        }

        if ((strpos($returnData, '"code":"500","type":"ERROR"') !== false
                || strpos($returnData, '"status":"400","errors"') !== false)
            && isset($memReturnData)
        ) {
            $this->logger->debug("get mem returnData");
            $returnData = $memReturnData;
        }

        if (
            (strpos($returnData, 'Bad Request') !== false
                && $this->http->FindPregAll('/<body>Bad Request<\/body>/', $returnData, PREG_PATTERN_ORDER, false, false))
            || (strpos($returnData, '11|Session|') !== false
                && $this->http->FindPreg('/"desc":"\s*11\|Session\|"/', false, $returnData))
            || strpos($returnData, '"desc":"Server is busy, please try again in a few minutes') !== false
            || strpos($returnData, '"desc":"Invalid FlightSearch data') !== false
        ) {
            throw new CheckRetryNeededException(5, 0);
        }

        if ($alliance) {
            $this->bodyResponseAlliance = $returnData;
        } else {
            $this->bodyResponseOnlyTap = $returnData;
        }

        return $res;
    }

    private function getRequestScript(array $fields, string $dateStr, bool $alliance)
    {
        return '
                    var xhttp = new XMLHttpRequest();
                    xhttp.withCredentials = true;
                    xhttp.open("POST", "https://booking.flytap.com/bfm/rest/booking/availability/search?payWithMiles=true&starAlliance=' . var_export($alliance, true) . '", false);
                    xhttp.setRequestHeader("Content-type", "application/json");
                    xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
                    xhttp.setRequestHeader("Authorization", "Bearer ' . $this->sessionToken . '");
                    xhttp.setRequestHeader("Connection", "keep-alive");
                    xhttp.setRequestHeader("Accept-Encoding", "gzip, deflate, br");
                    xhttp.setRequestHeader("Origin", "https://booking.flytap.com");
                    xhttp.setRequestHeader("Sec-Fetch-Dest", "empty");
                    xhttp.setRequestHeader("Sec-Fetch-Mode", "cors");
                    xhttp.setRequestHeader("Sec-Fetch-Site", "same-origin");
                    xhttp.setRequestHeader("Referer", "https://booking.flytap.com/booking/flights");

        
                    var data = JSON.stringify({"adt":' . $fields['Adults'] . ',"airlineId":"TP","c14":0,"cabinClass":"E","chd":0,"departureDate":["' . $dateStr . '"],"destination":["' . $fields['ArrCode'] . '"],"inf":0,"language":"en-us","market":"US","origin":["' . $fields['DepCode'] . '"],"passengers":{"ADT":' . $fields['Adults'] . ',"YTH":0,"CHD":0,"INF":0},"returnDate":"' . $dateStr . '","tripType":"O","validTripType":true,"payWithMiles":true,"starAlliance":' . var_export($alliance, true) . ',"yth":0});
                    var responseText = null;
                    xhttp.onreadystatechange = function() {
                        responseStatus = this.status;
                        if (this.readyState == 4 && this.status == 200) {
                            responseText = this.responseText;
                            localStorage.setItem("tapResponseAjax",this.responseText);
                        }
                    };
                    xhttp.send(data);
                    return responseText;
        ';
    }

    private function fillAirport($selenium, $id, $val)
    {
        $inp = $selenium->waitForElement(\WebDriverBy::id($id), 0);

        if ($inp) {
            $inp->click();
            $inp->clear();
            $inp->sendKeys($val);
            $inp->sendKeys(\WebDriverKeys::TAB);
            sleep(1);
            $text = $selenium->driver->executeScript("return document.querySelector('#flight-search-from').previousSibling.previousSibling.innerText");

            if (!preg_match("/^[A-Z]{3}$/", trim($text))) {
                $text = $selenium->driver->executeScript("return document.querySelector('#flight-search-from').previousSibling.innerText");
            }
            $this->logger->error($text);
        }
    }

    private function checkRouteData($selenium, $fields)
    {
        $this->logger->notice(__METHOD__);

        if (empty($this->sessionToken)) {
            return null;
        }

        $origins = \Cache::getInstance()->get('ra_tapportugal_origins');

        if (!is_array($origins)) {
            $tt =
                '
                    var xhttp = new XMLHttpRequest();
                    xhttp.open("POST", "https://booking.flytap.com/bfm/rest/journey/origin/search", false);
                    xhttp.setRequestHeader("Content-type", "application/json");
                    xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
                    xhttp.setRequestHeader("Authorization","Bearer ' . $this->sessionToken . '");
                    xhttp.setRequestHeader("Origin","https://booking.flytap.com");
                    xhttp.setRequestHeader("Referer","https://booking.flytap.com/booking/flights");
        
                    var data = JSON.stringify({"tripType":"O","market":"US","language":"en-us","airlineIds":["TP"],"payWithMiles":true});
                    xhttp.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                            localStorage.setItem("retData",this.responseText);
                        }
                    };
                    xhttp.send(data);
                    v = localStorage.getItem("retData");
                    return v;
        ';
            $this->logger->debug($tt);

            try {
                $originData = $selenium->driver->executeScript($tt);
            } catch (\Facebook\WebDriver\Exception\InvalidSelectorException $e) {
                $this->logger->error('[InvalidSelectorException]: ' . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
                sleep(2);
                $originData = $selenium->driver->executeScript($tt);
            } catch (\Facebook\WebDriver\Exception\ScriptTimeoutException $e) {
                $this->logger->error('[ScriptTimeoutException]: ' . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
                sleep(2);
                $originData = $selenium->driver->executeScript($tt);
            }
            $data = $this->http->JsonLog($originData, 1, true);

            $origins = [];

            if (isset($data['data']['origins'])) {
                $origins = array_map(function ($d) {
                    return $d['airport'];
                }, $data['data']['origins']);

                if (!empty($origins)) {
                    \Cache::getInstance()->set('ra_tapportugal_origins', $origins, 24 * 60 * 60);
                }
            }
        }

        if (is_array($origins) && !empty($origins) && !in_array($fields['DepCode'], $origins)) {
            $this->SetWarning('No flights from ' . $fields['DepCode']);
            $this->noRoute = true;

            return false;
        }

        $tt =
            '
                    var xhttp = new XMLHttpRequest();
                    xhttp.open("POST", "https://booking.flytap.com/bfm/rest/journey/destination/search", false);
                    xhttp.setRequestHeader("Content-type", "application/json");
                    xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
                    xhttp.setRequestHeader("Authorization","Bearer ' . $this->sessionToken . '");
                    xhttp.setRequestHeader("Origin","https://booking.flytap.com");
                    xhttp.setRequestHeader("Referer","https://booking.flytap.com/booking/flights");
        
                    var data = JSON.stringify({"tripType":"O","market":"US","language":"en-us","airlineIds":["TP"],"payWithMiles":true,"origin":"' . $fields['DepCode'] . '"});
                    xhttp.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                            localStorage.setItem("retData",this.responseText);
                        }
                    };
                    xhttp.send(data);
                    v = localStorage.getItem("retData");
                    return v;
        ';
        $this->logger->debug($tt);

        try {
            $returnData = $selenium->driver->executeScript($tt);
        } catch (\Facebook\WebDriver\Exception\InvalidSelectorException $e) {
            $this->logger->error('[InvalidSelectorException]: ' . $e->getMessage());
            $selenium->driver->executeScript('window.stop();');
            sleep(2);
            $returnData = $selenium->driver->executeScript($tt);
        } catch (\Facebook\WebDriver\Exception\ScriptTimeoutException $e) {
            $this->logger->error('[ScriptTimeoutException]: ' . $e->getMessage());
            $selenium->driver->executeScript('window.stop();');
            sleep(2);
            $returnData = $selenium->driver->executeScript($tt);
        } catch (\Facebook\WebDriver\Exception\JavascriptErrorException $e) {
            $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        if (empty($returnData) || strpos('"code":"500","type":"ERROR"', $returnData) !== false) {
            sleep(2);
            // helped
            $returnData = $selenium->driver->executeScript($tt);

            if (empty($returnData)) {
                throw new \CheckRetryNeededException(5, 0);
            }

            if (strpos('"code":"500","type":"ERROR"', $returnData) !== false) {
                $returnData = null;
            }
        }
        $data = $this->http->JsonLog($returnData, 1, true);
        $noFlight = true;
        $flight = null;

        if (isset($data['data']['destinations'])) {
            foreach ($data['data']['destinations'] as $destination) {
                if ($destination['airport'] === $fields['ArrCode']) {
                    $flight = $destination;
                    $noFlight = false;
                    $this->tapRoute = $destination['tapRoute'];
                    $this->starAllianceRoute = $destination['starAllianceRoute'];

                    break;
                }
            }

            if ($noFlight) {
                $this->SetWarning('No flights from ' . $fields['DepCode'] . ' to ' . $fields['ArrCode']);
                $this->noRoute = true;

                return false;
            }

            if ($flight && array_key_exists('tapRoute', $flight)) {
                return $flight['tapRoute'];
            }
        }

        return true;
    }

    private function isBadProxy()
    {
        return $this->http->FindSingleNode("//h1[contains(., 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//h1[normalize-space()='Access Denied']")
            || $this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'This page isn’t working')]")
            || $this->http->FindSingleNode("//p[contains(text(), 'There is something wrong with the proxy server, or the address is incorrect.')]");
    }

    /**
     * TODO: All bullshit, don't use.
     */
    /*private function validRoute($fields): bool
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://book.flytap.com/air/WebServices/Schedule/AirportCombination.asmx/CheckEligibleRoute", [
            'origin' => $fields['DepCode'],
            'destination' => $fields['ArrCode'],
        ]);
        $data = $this->http->JsonLog();
        return $data->domestic === true;
    }*/

    /*private function validRoute($fields): bool
    {
        $this->logger->notice(__METHOD__);
        $airports = \Cache::getInstance()->get('ra_tapportugal_airports');
        if (!$airports || !is_array($airports)) {
            $this->http->GetURL("https://www.flytap.com/api/general/masterdata?sc_mark=US&sc_lang=en-US", [
                'Connection' => 'keep-alive',
                'Cache-Control' => 'no-cache',
                'Accept' => '* / *',
            ], 10);
            $airports = $this->http->JsonLog(null, 0);
            if (!isset($airports->Airports)) {
                throw new \CheckException("Something went wrong", ACCOUNT_ENGINE_ERROR);
            }
            $airports = $airports->Airports;
            if (!empty($airports)) {
                \Cache::getInstance()->set('ra_tapportugal_airports', $airports, 60 * 60 * 24);
            } else {
                $this->sendNotification("RA check airports // MI");
            }
        }
        if ($airports && is_array($airports)) {
            foreach ($airports as $airport) {
                if ($airport->IATACode == $fields['DepCode']) {
                    return in_array($fields['ArrCode'], $airport->Connections);
                }
            }
        }
        return false;
    }*/
}
