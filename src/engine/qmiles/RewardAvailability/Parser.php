<?php


namespace AwardWallet\Engine\qmiles\RewardAvailability;

use AwardWallet\Engine\qmiles\RewardAvailability\Helpers\SensorData;

class Parser extends \TAccountCheckerQmiles
{
    const RA_TIME_GO_OUT = 110; // for RewardAvailability parsers

    private array $fields;
    private array $routesTemplate;

    public static function getRASearchLinks(): array
    {
        return ['https://www.qatarairways.com/en/homepage.html' => 'search page'];
    }

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->setProxyBrightData(true);
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function getRewardAvailabilitySettings(): array
    {
        return [
            'supportedCurrencies' => ['USD', 'SGD', 'QAR', 'HKD', 'EUR', 'CAD', 'GBP', 'AUD'],
            'supportedDateFlexiblility' => 0,
            'defaultCurrency' => 'USD'
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $this->fields = $fields;

        $bookingUrl = $this->creatBookingUrl();

        if($this->getBookingPage($bookingUrl)) {

            $this->parseRouteTemplate();

            $url = $this->createUrlForPaymentInfo();

            $paymentInfo = $this->getAndParsePaymentInfo($url);

            $this->logOut();
            return [
                'routes' => $this->makeRoutersList($paymentInfo),
            ];
        } else {

            $this->logOut();
            return [
                'routes' => []
            ];
        }
    }

    private function creatBookingUrl()
    {
        $url = 'https://booking.qatarairways.com/nsp/views/showBooking.action?';

        $fields = [
            'widget' => 'QR',
            'searchType' => 'F',
            'addTaxToFare' => 'Y',
            'minPurTime' => '0',
            'upsellCallId' => '100',
            'tripType' => 'O',
            'bookingClass' => $this->getCabinFields($this->fields['Cabin']),
            'allowRedemption' => 'Y',
            'flexibleDate' => 'Off',
            'selLang' => 'EN',
            'fromStation' => $this->fields['DepCode'],
            'toStation' => $this->fields['ArrCode'],
            'departingHidden' => $this->getDepartingHiddenDate($this->fields['DepDate']),
            'departing' => $this->getDepartingDate($this->fields['DepDate']),
            'adults' => $this->fields['Adults'],
            'children' => '0',
            'infants' => '0',
            'teenager' => '0',
            'ofw' => '0',
            'promoCode' => '',
            'qmilesFlow' => 'true',
            'paymentMode' => 'qmiles',
        ];

        return $url . http_build_query($fields);
    }

    private function getCabinFields($cabin)
    {
        $cabins = [
            'economy' => 'E',
            'premiumEconomy' => 'E',
            'firstClass' => 'B',
            'business' => 'B',
        ];

        return $cabins[$cabin];
    }

    private function getDepartingHiddenDate($unix)
    {
        return date('d-M-Y', $unix);
    }

    private function getDepartingDate($unix)
    {
        return date('Y-m-d', $unix);
    }


    private function getBookingPage($url)
    {
        $this->logger->notice(__METHOD__);

        $this->http->GetURL($url, [], 10);

        if($this->http->Response['code'] == 200) {
            $holdTime = $this->http->FindSingleNode("//iframe[@id='sec-cpt-if']/@data-duration");
            if ($holdTime) {
                $this->logger->error("Challenge Validation: timeout {$holdTime}");
                throw new \CheckRetryNeededException(5, 0);
            }
        }

        if($this->http->Response['code'] == 403) {
            throw new \CheckRetryNeededException(5, 0);
        }

        $viewState = $this->http->FindPreg('/faces.ViewState:[\d+]" value="([^\"]+)"/i');

        $postData = [
            'searchToken' => 'NltoGpd0wkJ0bKU6xDrhMsqNsKGEm0FpBH4NF42T' . time(),
            'hidden_SUBMIT' => '1',
            'javax.faces.ViewState' => $viewState,
            'javax.faces.behavior.event' => 'click',
            'javax.faces.partial.event' => 'load',
            'javax.faces.source' => 'hidden:deeplinkRdmNonLoginLink',
            'javax.faces.partial.ajax' => 'true',
            'javax.faces.partial.execute' => 'hidden:deeplinkRdmNonLoginLink',
            'hidden' => 'hidden'
        ];

        $this->http->PostURL('https://booking.qatarairways.com/nsp/views/searchLoading.xhtml', $postData, []);

        if(strstr($this->http->currentUrl(), '404')) {
            sleep(5);
            $this->http->GetURL('https://booking.qatarairways.com/nsp/views/qmilesIndex.xhtml', [], 10);
            if(strstr($this->http->currentUrl(), '404')) {
                throw new \CheckRetryNeededException(5, 0);
            }
        }

        $this->http->GetURL('https://booking.qatarairways.com/nsp/views/qmilesIndex.xhtml', [], 10);

        $noFlight = $this->http->FindSingleNode("//li[contains(text(),'There are no flights available on the date(s) you have selected')]");
        if ($noFlight) {
            $this->SetWarning('There are no flights available on the date(s) you have selected. Below are the flight options for the next available date(s).');
            return false;
        }

        $flightList = $this->http->FindSingleNode("//h1//span[contains(text(), 'Outbound flight')]");
        if ($flightList) {
            return true;
        }

        throw new \CheckRetryNeededException(5, 0);

    }

    private function parseRouteTemplate()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://booking.qatarairways.com/nsp/flightServlet', [], ['Content-Type' => 'application/json']);
        $this->http->RetryCount = 2;

        $json = $this->http->JsonLog(null, 1, true);

        if (!isset($json['upsellResponse']['outBoundflights']) || in_array($this->http->Response['code'], [502, 503, 504])) {
            sleep(rand(1,2));
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://booking.qatarairways.com/nsp/flightServlet', [], ['Content-Type' => 'application/json']);
            $this->http->RetryCount = 2;

            $json = $this->http->JsonLog(null, 1, true);
        }

        if(!isset($json['upsellResponse']['outBoundflights']) || $this->http->Response['code'] == 403) {
            throw new \CheckRetryNeededException(5, 0);
        }

        $jsonBookingData = $json['upsellResponse']['outBoundflights'];

        $this->http->GetURL('https://booking.qatarairways.com/nsp/views/qmilesIndex.xhtml', [], 10);

        foreach ($jsonBookingData as $key => $flight) {
            $indexNum = $key + 1;
            $connections = $flight['flightItinerary']['flightVOs'];

            $this->routesTemplate[$key] = [
                'distance'       => null,
                'num_stops'      => (count($connections) > 1) ? count($connections) - 1 : null,
                'tickets'        => null,
                'award_type'     => null,
                'classOfService' => null,
                'times'          => [],
            ];

            $rootDates = $this->http->XPath->query("(//div[contains(@class,'fly-results')]//div[contains(@class,'qmiles-search')])[{$indexNum}]");

            if($rootDates) {
                $departureDates = $this->http->FindNodes(".//div[contains(@class,'qmiles-time-dep')]//div[contains(@class,'f-date')]", $rootDates->item(0));
                $departureTimes = $this->http->FindNodes(".//div[contains(@class,'qmiles-time-dep')]//div[contains(@class,'f-t-place')]/span[1]", $rootDates->item(0));

                $arrivalDates = $this->http->FindNodes(".//div[contains(@class,'qmiles-time-arr')]//div[contains(@class,'f-date')]", $rootDates->item(0));
                $arrivalTimes = $this->http->FindNodes(".//div[contains(@class,'qmiles-time-arr')]//div[contains(@class,'f-t-place')]/span[1]", $rootDates->item(0));
            }

            foreach ($connections as $key_con => $connection) {
                $depDate = explode(', ', $departureDates[$key_con])[1] . ' ' . $departureTimes[$key_con];
                $arrDate = explode(', ', $arrivalDates[$key_con])[1] . '' . $arrivalTimes[$key_con];

                if (isset($connections['techStopoverDisc']) && $connections['techStopoverDisc'] !== false) {
                    $this->sendNotification('check techStopoverDisc //DN');
                }

                $this->routesTemplate[$key]['connections'][] =
                    [
                        'departure' =>
                            [
                                'date'      => \DateTime::createFromFormat('d M Y H:i', $depDate)->format('Y-m-d H:i') ?? null,
                                'airport'   => $connection['depStation'] ?? null,
                                'terminal'  => null,
                            ],
                        'arrival'   =>
                            [
                                'date'      => \DateTime::createFromFormat('d M Y H:i', $arrDate)->format('Y-m-d H:i')  ?? null,
                                'airport'   => $connection['arrStation'] ?? null,
                                'terminal'  => null,
                            ],
                        'cabin'      => null,
                        'fare_class' => null,
                        'flight'     => [$connection['flightNumber'] ?? null],
                        'airline'    => $connection['carrier'] ?? null,
                        'aircraft'   => $connection['aircraft']['equipmentCode'] ?? null,
                        'tickets'    => null,
                        'meal'       => null,
                        'times'      => [],
                    ];
            }
        }
    }

    private function createUrlForPaymentInfo()
    {
        $this->logger->notice(__METHOD__);

        $urls = [];
        $urlsPath = [];

        $nodes = $this->http->XPath->query("//div[@class='fly-results']//div[contains(@class,'cabin-class')]");

        foreach($nodes as $node) {
            $radioBtn1 = array_diff($this->http->FindNodes(".//span[contains(@class,'p-data')][1]//span[@class='radiobtn']/@id",$node,"/outbound_(\d+_\d_\w)_\d+_\w/i"), ['', null]);
            $radioBtn2 = array_diff($this->http->FindNodes(".//span[contains(@class,'p-data')][2]//span[@class='radiobtn']/@id",$node,"/outbound_(\d+_\d_\w)_\d+_\w/i"), ['', null]);

            if ($radioBtn2) {
                foreach ($radioBtn1 as $radio1) {
                    $radioValues1 = explode('_', $radio1);

                    foreach ($radioBtn2 as $radio2) {
                        $radioValues2 = explode('_', $radio2);
                        if($radioValues1[0] == $radioValues2[0]) {
                            $urlsPath[$radioValues1[0]][] = "{$radioValues1[0]}_{$radioValues1[1]}-{$radioValues1[2]}_{$radioValues2[1]}-{$radioValues2[2]}";
                        }
                    }
                }
            } else {
                foreach ($radioBtn1 as $radio1) {
                    $radioValues1 = explode('_', $radio1);

                    $urlsPath[$radioValues1[0]][] = "{$radioValues1[0]}_{$radioValues1[1]}-{$radioValues1[2]}";
                }
            }
        }

        foreach ($urlsPath as $group) {
            $tmp = [];
            foreach ($group as $urlPath) {
                $tmp[] = "https://booking.qatarairways.com/nsp/rest/getTax/{$urlPath}/outbound/flase";
            }
            $urls[] = $tmp;
        }

        return $urls;
    }

    private function getAndParsePaymentInfo($urls)
    {
        $this->logger->notice(__METHOD__);

        $tmp = [];

        foreach($urls as $keyGroup => $urlGroup) {
            foreach($urlGroup as $url) {
                if((time() - $this->requestDateTime) > self::RA_TIME_GO_OUT) {
                    break;
                }

                $this->http->RetryCount = 0;
                $this->http->PostURL($url, [],  ['Content-Type' => 'application/json']);
                $this->http->RetryCount = 2;

                $json = $this->http->JsonLog(null, 1, true);

                if (!isset($json['flights'][0]) || in_array($this->http->Response['code'], [502, 503, 504])) {
                    sleep(rand(1,2));
                    $this->http->RetryCount = 0;
                    $this->http->PostURL($url, [],  ['Content-Type' => 'application/json']);
                    $this->http->RetryCount = 2;

                    $json = $this->http->JsonLog(null, 1, true);
                }

                if(!isset($json['flights'][0]) || $this->http->Response['code'] == 403) {
                    $this->SetWarning('Not all flights collected taxes');
                    break;
                }

                $jsonResp = $json['flights'][0];

                $tmp[] =  [
                    'payments' =>
                        [
                            'currency' => $jsonResp['fareSummary']['currency'],
                            'tax'      => $jsonResp['fareSummary']['tax'],
                            'fee'      => $jsonResp['fareSummary']['fee'],
                        ],
                    'redemptions' =>
                        [
                            'miles'    => $jsonResp['fareSummary']['miles'][0]['amount'],
                            'program'  => $jsonResp['fareSummary']['miles'][0]['milesCurrency'],
                        ],
                    'cabin' => array_column($jsonResp['flightVOs'],'rbd'),
                    'routesTemplateId' => $keyGroup
                ];
            }

            if((time() - $this->requestDateTime) > self::RA_TIME_GO_OUT) {
                $this->SetWarning('Not all flights collected taxes');
                break;
            }
        }

        return $tmp;
    }

    private function makeRoutersList($paymentInfo)
    {
        $this->logger->notice(__METHOD__);

        $rotes = [];

        foreach ($paymentInfo as $data) {
            $routeTemplate = $this->routesTemplate[$data['routesTemplateId']];

            $routeTemplate['payments'] = $data['payments'];
            $routeTemplate['redemptions'] = $data['redemptions'];

            foreach($data['cabin'] as $index => $cabin) {
                $routeTemplate['connections'][$index]['cabin'] = ($cabin == 'A') ? 'firstClass' : ( ($cabin == 'I') ? 'business' : 'economy' );
            }

            $rotes[] = $routeTemplate;
        }

        return $rotes;
    }

    private function logOut()
    {
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.qatarairways.com/qr/Logout', [
            'resource' => '/en/homepage.html',
            'logOut' => 'logOut',
        ],  ['Content-Type' => 'application/x-www-form-urlencoded']);
        $this->http->RetryCount = 2;
    }

    protected function sendSensorData()
    {
        $this->logger->notice(__METHOD__);

        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data URL not found");

            return $this->checkErrors();
        }

        $this->http->NormalizeURL($sensorDataUrl);

        $sensData = \Cache::getInstance()->get('ra_qmiles_sensData');

        if(!$sensData || empty($sensData['abck']) || empty($sensData['sensData'])) {
            $sensData = SensorData::$sensData;

            $item = array_shift($sensData['abck']);
            array_push($sensData['abck'], $item);
            \Cache::getInstance()->set('ra_qmiles_sensData', $sensData, 60 * 30);
        }

        $key = array_key_first($sensData['abck']);
        $this->logger->notice("key: {$key}");
        $this->http->setCookie("_abck", $sensData['abck'][$key]); // todo: sensor_data workaround

        unset($sensData['abck'][$key]);

        $formUrl = $this->http->FormURL;
        $form = $this->http->Form;

        $key = array_key_first($sensData['sensData']);
        $this->logger->notice("key: {$key}");

        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorDataUrl, json_encode(['sensor_data' => $sensData['sensData'][$key][0]]), $sensorDataHeaders);
        $this->http->JsonLog();

        if (!empty($secondSensorData[$key])) {
            sleep(1);
            $this->http->PostURL($sensorDataUrl, json_encode(['sensor_data' => $sensData['sensData'][$key][1]]), $sensorDataHeaders);
            $this->http->RetryCount = 2;
            $this->http->JsonLog();
        }

        unset($sensData['sensData'][$key]);

        \Cache::getInstance()->set('ra_qmiles_sensData', $sensData, 60 * 30);

        $this->http->FormURL = $formUrl;
        $this->http->Form = $form;

        return $key;
    }
}
