<?php

namespace AwardWallet\Engine\marriott\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class HotelParser extends \TAccountChecker
{
    use ProxyList;
    use \SeleniumCheckerHelper;

    // TODO переписать на селениум - нет смысла в пробросах - только сложности с переменными
    private $fields;
    private $downloadPreview;
    private $skippedCache = 0;

    public static function getRASearchLinks(): array
    {
        return ['https://www.marriott.com/default.mi' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->logger->notice("Running Selenium...");
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br, zstd");

        $this->http->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:133.0) Gecko/20100101 Firefox/133.0');

        $this->setProxyGoProxies();
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

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));
        $this->logger->notice(__METHOD__);

        if ($fields['Rooms'] > 3) {
            $this->SetWarning('You can reserve a maximum of three rooms at a time.');

            return ['hotels' => []];
        }

        if (($fields['Adults'] + $fields['Kids']) > 8) {
            $this->SetWarning('Maximum: 8 total guests');

            return ['hotels' => []];
        }

        if ($fields['CheckOut'] == $fields['CheckIn']) {
            $this->SetWarning('You can’t book a day-use room.');

            return ['hotels' => []];
        }

        $this->fields = $fields;
        $this->downloadPreview = $fields['DownloadPreview'] ?? false;

        $this->fields['Nights'] = ($fields['CheckOut'] - $fields['CheckIn']) / 24 / 60 / 60;
        $this->logger->debug('Nights: ' . $this->fields['Nights']);

        if ($this->fields['Nights'] > 60) {
            $this->SetWarning('You cannot book a hotel for more than 60 night');

            return ['hotels' => []];
        }

        $this->http->GetURL("https://www.marriott.com/default.mi");

        $this->saveResponse();
        $data = $this->http->FindPreg('/{"props":{"pageProps":({?.*}),"__N_SSP"/');

        $data = $this->http->JsonLog($data, 1, true);
        $this->placeId = $this->getPlaceIdDestination($data['operationSignatures'], $data['requestId']);

        $data = $this->selenium();

        return ['hotels' => $data];
    }

    protected function runRecordScript($selenium): void
    {
        $selenium->driver->executeScript(
            $script = /** @lang JavaScript */ '
            const constantMock = window.fetch;
            window.fetch = function() {
                console.log(arguments);
                if (arguments[0]==="/mi/query/phoenixShopLowestAvailableRatesByGeoQuery") {
                    localStorage.setItem("phoenixShopLowestAvailableRatesByGeoQuery", JSON.stringify(arguments[1]));
                }
                return new Promise((resolve, reject) => {
                    constantMock.apply(this, arguments)
                        .then((response) => {
                            if(response.url.indexOf("mi/query/phoenixShopLowestAvailableRatesByGeoQuery") > -1) {
                                response
                                 .clone()
                                 .json()
                                 .then(body => {
                                     localStorage.setItem("LowestAvailableRatesByGeoQuery", JSON.stringify(body))
                                 });
                            }
                            
                            resolve(response);
                        })
                        .catch((error) => {
                            reject(response);
                        })
                });
            }'
        );
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            switch (0) {
                case 0:
                    $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_100);
                    $request = FingerprintRequest::firefox();

                    break;

//                case 2:
//                    $selenium->useChromePuppeteer(\SeleniumFinderRequest::CHROME_PUPPETEER_103);
//                    $request = FingerprintRequest::chrome();
//
//                    break;

                case 1:
                    $selenium->useFirefoxPlaywright(\SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_101);
                    $request = FingerprintRequest::firefox();

                    break;
            }

            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if (isset($fingerprint)) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

            $selenium->seleniumRequest->setOs(\SeleniumFinderRequest::OS_LINUX);
            $selenium->disableImages();
            $selenium->seleniumOptions->recordRequests = true;
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            $query = $this->makeSearchStringByGeo();

            $url = 'https://www.marriott.com/search/findHotels.mi?' . $query;

            $selenium->http->GetURL($url);
            $selenium->waitForElement(\WebDriverBy::xpath('//button[@custom_click_track_value="Phoenix Search Results| Hotel Details Page |internal"]'),
                30);

            if ($accept = $selenium->waitForElement(\WebDriverBy::xpath("//button[@id='onetrust-accept-btn-handler']"),
                5)) {
                $accept->click();
            }

            $this->savePageToLogs($selenium);

            if ($this->http->FindSingleNode("//label[contains(.,'Use Points/Certificates') or contains(.,'Use Points/Awards')]/preceding-sibling::*[1][self::input]/@value") !== 'true'
                && ($btn = $selenium->waitForElement(\WebDriverBy::xpath("//label[contains(.,'Use Points/Certificates') or contains(.,'Use Points/Awards')]"),
                    10))
            ) {
                $btn->click();
            }

            $this->runRecordScript($selenium);

            $this->savePageToLogs($selenium);

            $selenium->waitForElement(\WebDriverBy::xpath("//span[normalize-space()='Update Search']/ancestor::*[1][self::button]"),
                0);
            $selenium->driver->executeScript(/** @lang JavaScript */ "
                document.querySelector('button[data-component-name=\"a-shop-FindBtn\"][data-testid=\"shop-FindBtn\"]').click();");

            if ($selenium->waitForElement(\WebDriverBy::xpath('//div[@class="m-alert-inline-sub-content"]'), 10)) {
                $selenium->driver->executeScript(/** @lang JavaScript */ "
                    document.querySelector('button[data-component-name=\"a-shop-FindBtn\"][data-testid=\"shop-FindBtn\"]').click();");
            }

            $this->savePageToLogs($selenium);
            sleep(5);

            $res = $selenium->driver->executeScript(/** @lang JavaScript */
                'return localStorage.getItem("LowestAvailableRatesByGeoQuery");');
            $response = $this->http->JsonLog($res, 0, true);

            $res = $selenium->driver->executeScript(/** @lang JavaScript */
                'return localStorage.getItem("phoenixShopLowestAvailableRatesByGeoQuery");');
            $resDataMin = $this->http->JsonLog($res, 0, true);

            $data = $this->getAllHotelsBaseInfo($selenium,
                $response['data']['searchLowestAvailableRatesByGeolocation']['total'], $resDataMin);

            $cookies = $selenium->driver->manage()->getCookies();

//            $this->sensorSensorData();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }

            $result = [];
            $cntFound = count($data['data']['searchLowestAvailableRatesByGeolocation']['edges']);
            $cntSkipped = 0;

            foreach ($data['data']['searchLowestAvailableRatesByGeolocation']['edges'] as $num => $datum) {
                $node = $datum['node'];
                $points = $cache = $currency = null;

                $this->increaseTimeLimit(180);

                foreach ($node['rates'] as $rate) {
                    if ($rate['status']['code'] === 'unavailable') {
                        continue;
                    }

                    if ($rate['status']['code'] !== 'available') {
                        $this->sendNotification("check status code rate // ZM");
                    }

                    foreach ($rate['rateAmounts'] as $rateAmount) {
                        if (isset($rateAmount['points'])) {
                            $fullPoints = $rateAmount['points'];

                            continue;
                        }
                        $currency = $rateAmount['amount']['origin']['currency'];

                        if (!isset($cache) && $rateAmount['amount']['origin']['amount'] > 0) {
                            $fullCache = round($rateAmount['amount']['origin']['amount']) / (10 ** $rateAmount['amount']['origin']['valueDecimalPoint']);
                        }
                    }
                }

                if (!isset($fullPoints, $fullCache, $currency)) {
                    $this->sendNotification("check node {$num} // ZM");

                    continue;
                }

                if (!$node['property']['basicInformation']['bookable']) {
                    $this->logger->debug("skip not bookable");

                    continue;
                }

                $details = $this->getInfoCall($node['property']['id'], $url, $resDataMin['headers']['x-request-id']);
                $roomInfo = $this->runFetchProductsByProperty($selenium, $node['property']['id'],
                    $resDataMin['headers']['x-request-id'], $url);

                if (!$details || !$roomInfo) {
                    $cntSkipped++;
                    $this->logger->error('can\'t get hotel info');

                    $selenium->driver->executeScript('window.scrollBy(0, 500);');
                    sleep(2);

                    if ($cntSkipped > 3) {
                        if (($cntFound < 10 && count($result) > 0)
                            || (count($result) > $cntFound / 2)) {
                            return $result;
                        }

                        if (empty($result)) {
                            throw new \CheckRetryNeededException(5, 0);
                        }

                        $this->SetWarning('It was not possible to collect all the information on hotels.');

                        $this->logger->debug(var_export($result, true), ['pre' => true]);

                        return $result;
                    }

                    continue;
                }

                if (isset($details['errors']) || isset($roomInfo['errors'])) {
                    if (isset($details['errors'])) {
                        $data = json_encode($details);
                        $this->logger->debug("Details");
                        $this->http->JsonLog($data, 1);
                    } else {
                        $data = json_encode($roomInfo);
                        $this->logger->debug("RoomInfo");
                        $this->http->JsonLog($data, 1);
                    }

                    $this->sendNotification("Check Errors into response");

                    continue;
//                    throw new \CheckException('empty data', ACCOUNT_ENGINE_ERROR);
                }
                $cntSkipped = 0; // reset on success

                $address = implode(', ', array_filter([
                    $details['data']['property']['contactInformation']['address']['line1'] ?? null,
                    $details['data']['property']['contactInformation']['address']['city'] ?? null,
                    $details['data']['property']['contactInformation']['address']['stateProvince']['description'] ?? null,
                    $details['data']['property']['contactInformation']['address']['country']['description'] ?? null,
                ]));
                $url = 'https://www.marriott.com/hotels/travel/' . $details['data']['property']['seoNickname'];

                if ($this->downloadPreview && isset($node['property']['media']['primaryImage']['edges'][0]['node']['imageUrls']['wideHorizontal']) !== false) {
                    $preview = $this->getBase64FromImageUrl('https://cache.marriott.com' . $node['property']['media']['primaryImage']['edges'][0]['node']['imageUrls']['wideHorizontal']);
                } else {
                    $preview = null;
                }

                $rooms = [];
                $names = [];

                foreach ($roomInfo['data']['searchProductsByProperty']['edges'] as $info) {
                    if (($info['node']['availabilityAttributes']['rateCategory']['type']['code'] !== 'redemption')
                        || (in_array($info['node']['basicInformation']['description'], $names))) {
                        continue;
                    }

                    $rates = [];
                    $rateAmount = $info['node']['rates']['rateAmounts'][0];
                    $points = $rateAmount['points'];
                    $names[] = $info['node']['basicInformation']['description'];

                    [$cashAmount, $currency] = $this->getCashAmountAndCurrency($rateAmount);

                    $rates[] = [
                        'name'           => $info['node']['rates']['name'],
                        'description'    => $info['node']['rates']['description'],
                        'pointsPerNight' => $points / $this->fields['Nights'],
                        'cashPerNight'   => round($cashAmount),
                        'currency'       => $currency,
                    ];

                    foreach ($roomInfo['data']['searchProductsByProperty']['edges'] as $edge) {
                        if ($edge['node']['availabilityAttributes']['rateCategory']['type']['code'] !== 'redemption'
                            || $info['node']['rates']['name'] === $edge['node']['rates']['name']) {
                            continue;
                        }

                        $rateAmount = $edge['node']['rates']['rateAmounts'][0];
                        $points = $rateAmount['points'];

                        [$cashAmount, $currency] = $this->getCashAmountAndCurrency($rateAmount);

                        if ($info['node']['basicInformation']['description'] == $edge['node']['basicInformation']['description']) {
                            $rates[] = [
                                'name'           => $edge['node']['rates']['name'],
                                'description'    => $edge['node']['rates']['description'],
                                'pointsPerNight' => $points / $this->fields['Nights'],
                                'cashPerNight'   => round($cashAmount),
                                'currency'       => $currency,
                            ];
                        }
                    }

                    $rooms[] = [
                        'type'        => $info['node']['basicInformation']['type'],
                        'name'        => $info['node']['basicInformation']['name'],
                        'description' => $info['node']['basicInformation']['description'],
                        'rates'       => $rates,
                    ];
                }

                if (empty($rooms)) {
                    $cntSkipped++;
                    $this->logger->error('can\'t get rates info');

                    continue;
                }
                $result[] = [
                    'name'        => $node['property']['basicInformation']['nameInDefaultLanguage'],
                    'checkInDate' => date("Y-m-d H:i",
                        strtotime($details['data']['property']['policies']['checkInTime'], $this->fields['CheckIn'])),
                    'checkOutDate' => date("Y-m-d H:i",
                        strtotime($details['data']['property']['policies']['checkOutTime'], $this->fields['CheckOut'])),
                    'rooms'                 => $rooms,
                    'hotelDescription'      => $node['property']['basicInformation']['descriptions'][0]['text'] ?? null,
                    'numberOfNights'        => $this->fields['Nights'],
                    'pointsPerNight'        => $fullPoints,
                    'fullCashPricePerNight' => $fullCache,
                    'distance'              => $node['distance'],
                    'rating'                => $node['property']['reviews']['stars']['count'] ?? null,
                    'awardCategory'         => null,
                    'numberOfReviews'       => $node['property']['reviews']['numberOfReviews']['count'] ?? null,
                    'address'               => $address,
                    'detailedAddress'       => [
                        'addressLine' => $details['data']['property']['contactInformation']['address']['line1'] ?? null,
                        'city'        => $details['data']['property']['contactInformation']['address']['city'] ?? null,
                        'state'       => $details['data']['property']['contactInformation']['address']['stateProvince']['description'] ?? null,
                        'countryName' => $details['data']['property']['contactInformation']['address']['country']['description'] ?? null,
                        'postalCode'  => $details['data']['property']['contactInformation']['address']['postalCode'] ?? null,
                        'lat'         => $node['property']['basicInformation']['latitude'] ?? null,
                        'lng'         => $node['property']['basicInformation']['longitude'] ?? null,
                        'timezone'    => null,
                    ],
                    'phone'   => $details['data']['property']['contactInformation']['contactNumbers'][0]['phoneNumber']['original'] ?? null,
                    'url'     => $url,
                    'preview' => $preview,
                ];
            }
        } catch (
        \WebDriverCurlException
        | \WebDriverException
        | \Facebook\WebDriver\Exception\WebDriverCurlException
        | \Facebook\WebDriver\Exception\WebDriverException $e
        ) {
            $this->logger->error($e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return $result;
    }

    private function savePageToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        try {
            $selenium->http->SaveResponse();
        } catch (\ErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }

    private function getPlaceIdDestination($signatures, $requestId): string
    {
        $headers = [
            "Accept"                       => "*/*",
            "Accept-Language"              => "en-US",
            "Apollographql-Client-Name"    => "phoenix_homepage",
            "Apollographql-Client-Version" => "v1",
            "Application-Name"             => "homepage",
            "Content-Type"                 => "application/json",
            "Graphql-Operation-Signature"  => $signatures[5]['signature'],
            "Graphql-Require-Safelisting"  => "true",
            "X-Request-Id"                 => $requestId,
        ];

        $payload = '{"operationName":"phoenixShopSuggestedPlacesQuery","variables":{"query":"' . $this->fields['Destination'] . '"},"query":"query phoenixShopSuggestedPlacesQuery($query: String!) {\n  suggestedPlaces(query: $query) {\n    edges {\n      node {\n        placeId\n        description\n        primaryDescription\n        secondaryDescription\n        __typename\n      }\n      __typename\n    }\n    total\n    __typename\n  }\n}\n"}';

        $this->http->PostURL('https://www.marriott.com/mi/query/phoenixShopSuggestedPlacesQuery', $payload,
            $headers);
        $dataPlace = $this->http->JsonLog(null, 1, true);

        if (!isset($dataPlace['data']['suggestedPlaces']['edges'][0]['node']['placeId'])) {
            $this->logger->error("new place format");

            throw new \CheckException('something went wrong', ACCOUNT_ENGINE_ERROR);
        }

        return $dataPlace['data']['suggestedPlaces']['edges'][0]['node']['placeId'];
    }

    private function makeSearchStringByGeo()
    {
        // TODO разные для разного числа комнат
        $this->logger->notice(__METHOD__);
        $checkIn = date('m/d/Y', $this->fields['CheckIn']);
        $checkOut = date('m/d/Y', $this->fields['CheckOut']);

        $getData = [
            'fromDate'                       => $checkIn,
            'fromToDate_submit'              => $checkOut,
            'toDate'                         => $checkOut,
            'toDateDefaultFormat'            => $checkOut,
            'fromDateDefaultFormat'          => $checkIn,
            'flexibleDateSearch'             => false,
            't-start'                        => $checkIn,
            't-end'                          => $checkOut,
            'lengthOfStay'                   => $this->fields['Nights'],
            'childrenCountBox'               => $this->fields['Kids'] . '+Children+Per+Room',
            'childrenCount'                  => $this->fields['Kids'],
            'clusterCode'                    => 'none',
            'useRewardsPoints'               => false,
            'marriottBrands'                 => '',
            'isAdvanceSearch'                => false,
            'recordsPerPage'                 => 100,
            'isInternalSearch'               => true,
            'vsInitialRequest'               => false,
            'searchType'                     => 'InCity',
            'singleSearchAutoSuggest'        => 'Unmatched',
            'destinationAddress.placeId'     => $this->placeId,
            'for-hotels-nearme'              => 'Near', // not always??
            'collapseAccordian'              => 'is-hidden',
            'singleSearch'                   => true,
            'isTransient'                    => true,
            'initialRequest'                 => false,
            'flexibleDateSearchRateDisplay'  => false,
            'isSearch'                       => true,
            'isRateCalendar'                 => false,
            'destinationAddress.destination' => $this->fields['Destination'],
            'isHideFlexibleDateCalendar'     => false,
            'roomCountBox'                   => '1+Room',
            'roomCount'                      => $this->fields['Rooms'],
            'guestCountBox'                  => $this->fields['Adults'] . '+Adult+Per+Room',
            'numAdultsPerRoom'               => $this->fields['Adults'],
            // not always
            'destinationAddress.location'   => $this->fields['Destination'],
            'fromToDate'                    => date('m/d/Y'),
            'isFlexibleDatesOptionSelected' => false,
            'numberOfRooms'                 => $this->fields['Rooms'],
            'view'                          => 'list',
        ];

        if ($this->fields['Kids'] > 0) {
            $getData['childrenAges'] = 0;
        }

        return http_build_query($getData) . '#/0/';
    }

    private function runFetchProductsByProperty($selenium, $id, $xRexquestId, $url)
    {
        $this->logger->notice(__METHOD__);

        $checkIn = date('Y-m-d', $this->fields['CheckIn']);
        $checkOut = date('Y-m-d', $this->fields['CheckOut']);

        $body = addslashes('{"operationName":"PhoenixBookSearchProductsByProperty","variables":{"search":{"options":{"startDate":"' . $checkIn . '","endDate":"' . $checkOut . '","quantity":' . $this->fields['Rooms'] . ',"numberInParty":' . $this->fields['Adults'] . ',"childAges":[],"productRoomType":["ALL"],"productStatusType":["AVAILABLE"],"rateRequestTypes":[{"value":"","type":"STANDARD"},{"value":"","type":"PREPAY"},{"value":"","type":"PACKAGES"},{"value":"MRM","type":"CLUSTER"},{"value":"","type":"REDEMPTION"},{"value":"","type":"REGULAR"}],"isErsProperty":true},"propertyId":"' . $id . '"},"offset":0,"limit":150},"query":"query PhoenixBookSearchProductsByProperty($search: ProductByPropertySearchInput) {\n  searchProductsByProperty(search: $search) {\n    edges {\n      node {\n        ... on HotelRoom {\n          availabilityAttributes {\n            rateCategory {\n              type {\n                code\n                __typename\n              }\n              value\n              __typename\n            }\n            isNearSellout\n            __typename\n          }\n          rates {\n            name\n            description\n            rateAmounts {\n              amount {\n                origin {\n                  amount\n                  currency\n                  valueDecimalPoint\n                  __typename\n                }\n                __typename\n              }\n              points\n              pointsSaved\n              pointsToPurchase\n              __typename\n            }\n            localizedDescription {\n              translatedText\n              sourceText\n              __typename\n            }\n            localizedName {\n              translatedText\n              sourceText\n              __typename\n            }\n            rateAmountsByMode {\n              averageNightlyRatePerUnit {\n                amount {\n                  origin {\n                    amount\n                    currency\n                    valueDecimalPoint\n                    __typename\n                  }\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          basicInformation {\n            type\n            name\n            localizedName {\n              translatedText\n              __typename\n            }\n            description\n            localizedDescription {\n              translatedText\n              __typename\n            }\n            membersOnly\n            oldRates\n            representativeRoom\n            housingProtected\n            actualRoomsAvailable\n            depositRequired\n            roomsAvailable\n            roomsRequested\n            ratePlan {\n              ratePlanType\n              ratePlanCode\n              __typename\n            }\n            __typename\n          }\n          roomAttributes {\n            attributes {\n              id\n              description\n              groupID\n              category {\n                code\n                description\n                __typename\n              }\n              accommodationCategory {\n                code\n                description\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          totalPricing {\n            quantity\n            rateAmountsByMode {\n              grandTotal {\n                amount {\n                  origin {\n                    value: amount\n                    valueDecimalPoint\n                    __typename\n                  }\n                  __typename\n                }\n                __typename\n              }\n              subtotalPerQuantity {\n                amount {\n                  origin {\n                    currency\n                    value: amount\n                    valueDecimalPoint\n                    __typename\n                  }\n                  __typename\n                }\n                __typename\n              }\n              totalMandatoryFeesPerQuantity {\n                amount {\n                  origin {\n                    currency\n                    value: amount\n                    valueDecimalPoint\n                    __typename\n                  }\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          id\n          __typename\n        }\n        id\n        __typename\n      }\n      __typename\n    }\n    total\n    status {\n      ... on UserInputError {\n        httpStatus\n        messages {\n          user {\n            message\n            field\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      ... on DateRangeTooLongError {\n        httpStatus\n        messages {\n          user {\n            message\n            field\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}');
        ////        $this->logger->debug($body);
//        $body = '{"operationName":"PhoenixBookSearchProductsByProperty","variables":{"search":{"options":{"startDate":"' . $checkIn . '","endDate":"' . $checkOut . '","quantity":' . $this->fields['Rooms'] . ',"numberInParty":' . $this->fields['Adults'] . ',"childAges":[],"productRoomType":["ALL"],"productStatusType":["AVAILABLE"],"rateRequestTypes":[{"value":"","type":"STANDARD"},{"value":"","type":"PREPAY"},{"value":"","type":"PACKAGES"},{"value":"MRM","type":"CLUSTER"},{"value":"","type":"REDEMPTION"},{"value":"","type":"REGULAR"}],"isErsProperty":true},"propertyId":"' . $id . '"},"offset":0,"limit":150},"query":"query PhoenixBookSearchProductsByProperty($search: ProductByPropertySearchInput) {\n  searchProductsByProperty(search: $search) {\n    edges {\n      node {\n        ... on HotelRoom {\n          availabilityAttributes {\n            rateCategory {\n              type {\n                code\n                __typename\n              }\n              value\n              __typename\n            }\n            isNearSellout\n            __typename\n          }\n          rates {\n            name\n            description\n            rateAmounts {\n              amount {\n                origin {\n                  amount\n                  currency\n                  valueDecimalPoint\n                  __typename\n                }\n                __typename\n              }\n              points\n              pointsSaved\n              pointsToPurchase\n              __typename\n            }\n            localizedDescription {\n              translatedText\n              sourceText\n              __typename\n            }\n            localizedName {\n              translatedText\n              sourceText\n              __typename\n            }\n            rateAmountsByMode {\n              averageNightlyRatePerUnit {\n                amount {\n                  origin {\n                    amount\n                    currency\n                    valueDecimalPoint\n                    __typename\n                  }\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          basicInformation {\n            type\n            name\n            localizedName {\n              translatedText\n              __typename\n            }\n            description\n            localizedDescription {\n              translatedText\n              __typename\n            }\n            membersOnly\n            oldRates\n            representativeRoom\n            housingProtected\n            actualRoomsAvailable\n            depositRequired\n            roomsAvailable\n            roomsRequested\n            ratePlan {\n              ratePlanType\n              ratePlanCode\n              __typename\n            }\n            __typename\n          }\n          roomAttributes {\n            attributes {\n              id\n              description\n              groupID\n              category {\n                code\n                description\n                __typename\n              }\n              accommodationCategory {\n                code\n                description\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          totalPricing {\n            quantity\n            rateAmountsByMode {\n              grandTotal {\n                amount {\n                  origin {\n                    value: amount\n                    valueDecimalPoint\n                    __typename\n                  }\n                  __typename\n                }\n                __typename\n              }\n              subtotalPerQuantity {\n                amount {\n                  origin {\n                    currency\n                    value: amount\n                    valueDecimalPoint\n                    __typename\n                  }\n                  __typename\n                }\n                __typename\n              }\n              totalMandatoryFeesPerQuantity {\n                amount {\n                  origin {\n                    currency\n                    value: amount\n                    valueDecimalPoint\n                    __typename\n                  }\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          id\n          __typename\n        }\n        id\n        __typename\n      }\n      __typename\n    }\n    total\n    status {\n      ... on UserInputError {\n        httpStatus\n        messages {\n          user {\n            message\n            field\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      ... on DateRangeTooLongError {\n        httpStatus\n        messages {\n          user {\n            message\n            field\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}';
//
//        $headers = [
//            "Accept" => "*/*",
//            "Accept-Language" => "en-US",
//            "apollographql-client-name" => "phoenix_book",
//            "apollographql-client-version" => "1",
//            "application-name" => "book",
//            "Content-Type" => "application/json",
//            "graphql-force-safelisting" => "true",
//            "graphql-operation-name" => "PhoenixBookSearchProductsByProperty",
//            "graphql-operation-Signature" => '41074b9fea7d55005c16018f7a21065da94574116f9c6a5a63e5b0067adc0cbe',
//            "graphql-require-safelisting" => "true",
//            "Origin" => "https://www.marriott.com",
//            "Referer" => "https://www.marriott.com/reservation/rateListMenu.mi",
//            "Sec-fetch-dest" => "empty",
//            "Sec-fetch-mode" => "cors",
//            "Sec-fetch-site" => "same-origin",
//            "X-aZnN0eCb-z" => "q",

//            "X-aZnN0eCb-f" => "A7fBImqUAQAAhIXvSd-uw06Gv6lp67aFMdBMYB6on76duqxIVpgZK55zvqTNAVqXjoWcuJbCwH9eCOfvosJeCA==",
//            "X-aZnN0eCb-b" => "-rqazn7",
//            "X-aZnN0eCb-c" => "AICgDGqUAQAApVZWMcpG0tubJ9MuVCYA-2X8rUr_FAoRMIvsnlTCPuPO5_AU",
//            "X-aZnN0eCb-d" => "ADaAhIDBCKGBgQGAAYIQgISigaIAwBGAzPpCxg_33ocxnsD_CABUwj7jzufwFAAAAAAGDCa-AYygfjc16MJJhUbZFMHYXG8",
//            "X-aZnN0eCb-a" => "QklggaHysXgd6aSA=WVkMKNew2GweQuOfoI__IZC4oaj-lYp8Vg=_b3irpVDzG=T2g_2bwtVW2dlmYnViwHvvk0GOWmtQTd9l27Pbs3gtJhlapWe0YS1eZAK50l2g6MYTNsW3N3MU-tIYTVVDyHjAd5n5J81LxU7Mta95gEUBvQGYDefNZre09CE=6VLPOrxXiWNl12luVzxQpBDMiR-I2=GUowykEoPdNkij2rG6Ri1zlsu=I_0U3UMbu=IB11lt6KWdXO0d5VFI3bjBUL2BN538LCM=Yo_xOiOBCf_40_8Y5gk2fHsh7Laq715=YDdTdUS-uf3EDKTLOy19jFG6VFGTn2-HTb02sSRi_aogiNaAmJj75G9NFrtezLLDAdlIIuKSvD8gZ=VyyNy92NJp2EigazGD0pqMxS7PyNihE8VKJNEMlnmNNXigBu2SM316zb-jHhI91xK9wef4KIlmkaHtb4hWR66CfbuEVmeaTXFqPnHergUFRjqPxtxp5lm7a9wUYVA=eS6lDpVpXdbEP2IGNgCK4goIPl8XkoGDuuiq2M2-97_B=rYC84qKLMPS92QlzP5AqukwnnkB5aBVB3It6Oq2eVnHuQZSah3EYga_Ey0u5O_oCbVE_9dQWgJqo8L43tr9DL4r5ypee9-_bY9afUAqoIXHaAXw8m1MvNYeIX6XgdWbeeaHGvfbDpFOohh3yiPzWFhZ9E=L9PQtjMCgnps1vBykrJN7HwYepgKx4vMOlr5afXe1S5r9TGifAL-j1_nj6IkCPgTyk8IhYO4QlzKFi1LIoaYZEFZKlCoYH_KFZxRbWx-S7SAknXUFC1jeUDOqWmfpsRsuX1a6EVSnh9vhSLnkDlYHLsJKzlIbb4pHQB1_pol5kYGj8PLFJ8Hk6GJRq-U195hKuhUUkYy8V4Piyw7szCo=GTIiOTM0UVe4PK3MD_FjIi3hMnvARyxWyNRzVn-kHJDZji76uI15dl6yRumXBXXsM_sGAp9RkD2XfECslGa7uvvdeDYZipIl_g8I3PagoZsyHlZfP2OjJUpb1IfSFwIjBgtTlFgy5Lvk7VyOQhoVd1LalfH4HBB-LAz_NoxzmXPtjv7_Izpjs7JOCQvuSLpaMX7x4ht1lFkVNQlnyJOJb3GnDfs56NASo5PfRlC57AQFfNlohNGODwrCNh-sP7r0JQ=v9zgyRf42ZsO6I_wx1uf4VPkrBqkTMC1QD=3HYgFabZo1w8Q-ndRPE3EyDIqXb6AzAbgnT-oMqlk-ZDx44GdWVvFp7Z28qI9NOxvKm9PAlrPBs_N6C=hwNNYR6=O=urtoVIRrBpFaGsIvVESXFBkbu6X4eiRai0PBBKJXWY9049Mo0vjYpUn7T790QMqnmZMsM5UtmURX0j8lHolQpaBCvKmCLxdekEvEhHDY9ro0GxG_pHFCdRO2BKhQoove8QVlewy47mr1fqxh2arz=qWGroAf8DEgg6=Vmd0ZUpLqOufzs32J9KqMEddKGTb9CC-zBeVebLuU4C_Cxs4n8lSq8ms45Z=HXAsMYBHf2HvuD21H9u0iIjIyPKMRf=kg12JVZidSbYjeSGikmIGfi79_tGL15mEoLyVgtlQrKpu=AF2BP_KJHkTCeCbltvX9eJ7C1sHTUluYOaYqS2ZWaziexIde60ey7=Y=4U0-zw=7WD8XsZP9ig7HAiLqLxR666SMJA2oB1q8N5ipXQW1g_tmNQ0dvUf5A4bJFpaWHLhn7_hUm-5EpeqHzlBjWK4QYwhxyjPvLvse=6LAZmY4A6HgEGLwYXd3eNPQ7_06zU6rVqzAIzR2lvTPAVioxuhZqXp5Vgrp2nsm-riGjjBxf4FxWTyeebjEVe1a_B69oKn3N-Y=ZWRjZSbiqBMsXrn2unRwgqvej_oBR0H_GWG56QWHVyyCIWWklnmyJL-gLBRjTpCRtWN_iEeiAUlFgg_a3EMhedQ=w6niEnRVv0wOtI1i_44RZDaKhdjTvWI0gWAmXW4OMDUgyyudMNEe9ILAiLsOOvyxlqM1lCp0iHZurL",

//            "TE" => "trailers",
//
//        ];
//

//        $options[] = [
//            'method'   => 'POST',
//            'sURL'     => 'https://www.marriott.com/mi/query/PhoenixBookSearchProductsByProperty',
//            'postData' => $body,
//            'headers'  => $headers,
//            'timeout'  => 20,
//        ];
//
//        $this->http->sendAsyncRequests($options);
//
        ////        $this->http->PostURL('https://www.marriott.com/mi/query/PhoenixBookSearchProductsByProperty', $body, $headers);
//
//        return $this->http->JsonLog(null, 1, true);
        $script = /** @lang JavaScript */
            '
            var xhr = new XMLHttpRequest();
            xhr.withCredentials = true;
            var url = "https://www.marriott.com/mi/query/PhoenixBookSearchProductsByProperty";
            xhr.open("POST", url, false);
            xhr.setRequestHeader("Accept", "*/*");
            xhr.setRequestHeader("Accept-Language", "en-US");
            xhr.setRequestHeader("Accept-Encoding", "gzip, deflate, br, zstd");
            xhr.setRequestHeader("Content-Type", "application/json");
            xhr.setRequestHeader("apollographql-client-name", "phoenix_book");
            xhr.setRequestHeader("apollographql-client-version", "1");
            xhr.setRequestHeader("application-name", "book");
            xhr.setRequestHeader("graphql-require-safelisting", "true");
            xhr.setRequestHeader("graphql-operation-signature", "41074b9fea7d55005c16018f7a21065da94574116f9c6a5a63e5b0067adc0cbe");
            xhr.setRequestHeader("graphql-operation-name", "PhoenixBookSearchProductsByProperty");

            xhr.onreadystatechange = function () {
                if (xhr.readyState === XMLHttpRequest.DONE) {
                    if (xhr.status === 200) {
                    var result = JSON.parse(xhr.responseText);
                    let script = document.createElement("script");
                    let id = "' . $id . '-ext";
                    script.id = id;
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                } else {
                    let newDiv = document.createElement("div");
                    let id = "' . $id . '-ext";
                    newDiv.id = id;
                    let newContent = document.createTextNode(xhr.statusText);
                    newDiv.appendChild(newContent);
                    document.querySelector("body").append(newDiv);
                }
             }
        };

        xhr.send("' . $body . '");
        ';
        $this->logger->info($script, ['pre' => true]);
        $selenium->driver->executeScript($script);

        $selenium->waitForElement(\WebDriverBy::xpath('//*[self::script or self::div][@id="' . $id . '-ext"]'), 10,
            false);
        $data = $selenium->waitForElement(\WebDriverBy::xpath('//script[@id="' . $id . '-ext"]'), 3, false);
        $selenium->saveResponse();

        if (!$data) {
            $selenium->waitForElement(\WebDriverBy::xpath('//div[@id="' . $id . '-ext"]'), 0, false);

            return null;
        }

        return $this->http->JsonLog($data->getAttribute($id . '-ext'), 1, true);
    }

    private function getCashAmountAndCurrency($rateAmount)
    {
        if (empty($rateAmount['amount'])) {
            return [null, null];
        }

        $cashAmount = round($rateAmount['amount']['origin']['amount']) / (10 ** $rateAmount['amount']['origin']['valueDecimalPoint']);
        $currency = $rateAmount['amount']['origin']['currency'];

        return [$cashAmount, $currency];
    }

    private function getInfoCall($id, $url, $requestId): array
    {
        $body = '{"operationName":"phoenixShopPropertyInfoCall","variables":{"propertyId":"' . $id . '","filter":"PHONE","descriptionsFilter":["LOCATION","RESORT_FEE_DESCRIPTION","DESTINATION_FEE_DESCRIPTION"]},"query":"query phoenixShopPropertyInfoCall($propertyId: ID!, $filter: [ContactNumberType], $descriptionsFilter: [PropertyDescriptionType]) {\n  property(id: $propertyId) {\n    id\n    basicInformation {\n      name\n      latitude\n      longitude\n      isAdultsOnly\n      isMax\n      brand {\n        id\n        __typename\n      }\n      openingDate\n      bookable\n      resort\n      descriptions(filter: $descriptionsFilter) {\n        text\n        type {\n          code\n          label\n          description\n          __typename\n        }\n        localizedText {\n          sourceText\n          translatedText\n          __typename\n        }\n        __typename\n      }\n      hasUniquePropertyLogo\n      nameInDefaultLanguage\n      __typename\n    }\n    contactInformation {\n      address {\n        line1\n        city\n        postalCode\n        stateProvince {\n          label\n          description\n          code\n          __typename\n        }\n        country {\n          code\n          description\n          label\n          __typename\n        }\n        __typename\n      }\n      contactNumbers(filter: $filter) {\n        phoneNumber {\n          display\n          original\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    airports {\n      name\n      distanceDetails {\n        description\n        __typename\n      }\n      contactNumber {\n        number\n        __typename\n      }\n      url\n      complimentaryShuttle\n      id\n      __typename\n    }\n    otherTransportation {\n      name\n      contactInformation {\n        phones\n        __typename\n      }\n      type {\n        description\n        code\n        __typename\n      }\n      __typename\n    }\n    reviews {\n      stars {\n        count\n        __typename\n      }\n      numberOfReviews {\n        count\n        __typename\n      }\n      __typename\n    }\n    parking {\n      fees {\n        fee\n        description\n        __typename\n      }\n      description\n      __typename\n    }\n    policies {\n      checkInTime\n      checkOutTime\n      smokefree\n      petsAllowed\n      petsPolicyDescription\n      localizedPetsPolicyDescription {\n        translatedText\n        __typename\n      }\n      petsPolicyDetails {\n        additionalPetFee\n        numberAllowed\n        refundableFee\n        refundableFeeType\n        nonRefundableFee\n        nonRefundableFeeType\n        additionalPetFeeType\n        weightRestricted\n        maxWeight\n        __typename\n      }\n      __typename\n    }\n    ... on Hotel {\n      seoNickname\n      __typename\n    }\n    __typename\n  }\n}\n"}';

        $headers = [
            "Accept"                       => "*/*",
            "Accept-Language"              => "en-US",
            "Apollographql-Client-Name"    => "phoenix_homepage",
            "Apollographql-Client-Version" => "V1",
            "Application-Name"             => "homepage",
            "Content-Type"                 => "application/json",
            "Graphql-Operation-Signature"  => 'e5b4b394e3c4b2e70198cc6c7e9f592aab8d9024cce27469444ba0b0c156d555',
            "Graphql-Require-Safelisting"  => "true",
            "X-Dtreferer"                  => $url,
            "X-Request-Id"                 => $requestId,
        ];

        $this->http->PostURL('https://www.marriott.com/mi/query/phoenixShopPropertyInfoCall', $body, $headers);

        return $this->http->JsonLog(null, 0, true);
    }

    private function getAllHotelsBaseInfo($selenium, $total, $data): array
    {
        $payload = str_replace('40,', "{$total},",
            $data["body"]);

        $script = /** @lang JavaScript */
            '
            var xhttp = new XMLHttpRequest();
            xhttp.withCredentials = true;
            xhttp.open("POST", "https://www.marriott.com/mi/query/phoenixShopLowestAvailableRatesByGeoQuery", false);
            xhttp.setRequestHeader("Accept", "*/*");
            xhttp.setRequestHeader("Accept-Language", "en-US");
            xhttp.setRequestHeader("Content-type", "application/json");
            xhttp.setRequestHeader("Apollographql-client-name", "phoenix_homepage");
            xhttp.setRequestHeader("Apollographql-client-version", "v1");
            xhttp.setRequestHeader("x-request-id", "' . $data['headers']['x-request-id'] . '");
            xhttp.setRequestHeader("application-name", "homepage");
            xhttp.setRequestHeader("graphql-require-safelisting", "true");
            xhttp.setRequestHeader("graphql-operation-signature", "' . $data['headers']['graphql-operation-signature'] . '");
    
            xhttp.setRequestHeader("Origin", "https://www.marriott.com");
            xhttp.setRequestHeader("Referer", "https://www.marriott.com/default.mi");
    
            var data = JSON.stringify(' . $payload . ');
    
            var responseText = null;
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        responseText = this.responseText;
                    }
                };
            xhttp.send(data);
            return responseText;
        ';

        $response = $selenium->driver->executeScript($script);
        $this->logger->info($script, ['pre' => true]);
        $this->savePageToLogs($selenium);

        return $this->http->JsonLog($response, 1, true);
    }

    private function getBase64FromImageUrl(?string $url): ?string
    {
        if (null === $url) {
            $this->logger->warning('Provided URL is null.');

            return null;
        }

        $this->logger->info('Download image: ' . $url);
        $http2 = clone $this->http;

        try {
            $file = $http2->DownloadFile($url);

            if (!file_exists($file)) {
                $this->logger->error('Failed to download file from URL: ' . $url);

                return null;
            }

            $imageSize = getimagesize($file);

            if (!$imageSize) {
                return null;
            }

            if ($imageSize[0] > 400) {
                $image = new \Imagick($file);
                $image->scaleImage(400, 0);

                file_put_contents($file, $image);
                $imageSize = getimagesize($file);
            }

            $imageData = base64_encode(file_get_contents($file));
            unlink($file);

            return $imageData;
        } catch (Exception $e) {
            $this->logger->error('Error downloading or processing image from URL: ' . $url . ' - ' . $e->getMessage());

            return null;
        }
    }

    private function sensorSensorData()
    {
        $this->logger->notice(__METHOD__);

        $sensorPostUrl = $this->http->FindPreg("#<\/script><script[^>]+src=\"([^\"]+)\"[^>]*><\/script><\/span>#");
        $this->logger->debug($sensorPostUrl);

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");

            return null;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            '3;0;1;2048;4407609;/i5QpaoaRUPFFJWIySSYEXR/7fQ/GMojSTpXTuMeuzc=;54,4,0,2,6,745;=t]N\"PS6\":\"bYi@~3N2x\"D^W\"ji^\"d>FMI/K9_`Ae?Cwwt^Nas`1\"i*?H\"/ )mZp:sNs[\"5w(^\"r|.i].D4]yX\"o6Ga\"+\"\"y\"yNl\"{>f-S0F\"eE7\"cI~<)\"Gn\"ukca?\"xo#\"n6_?pRmF@sU\"h<\"DZd$9L{1\"2#$\"SD#U_\"fEk\"L!5W]L#H\"5<V\"].Kzi/y\"E}@\"h\"d.$SDcLh=;9xGTJkzJh;a;IseoG|*/VtX-zbO+v3cgtI+96PkChOfBc-/\"v\"9J$\"c>4j[E5R)=L#k^w+\".H=\"fq>L^-$z\"_{v\"-gH5&\")#C\"CK\"k<t\"#@>\"U\"r2FvT~gR~$thPH(XM<&|#Kv#jlNVd:%76/5=__o|#-kO\"&\"~Db\"n\"\"`J$\";ys\"O\"\"m\"]&`\" bY]f\"L$\"e\"\"]5C\"5Io\"!k>d~7l\"\"G\"TtU\"?G5}i,Lpln N3\"Z(=\"Aqb,LSV#JK3C:I>iS\"6$h\"^W;a=}}qf`F\"s1ig\"o=9nxfc`jw$\"`>.\"P\"\"3\"ZXb\"|\"P\"C]Y\"RPW\"C\",kf.a\"gR`\"5l^;u\"#\"CLRpK[mrab__{p-by1:<zRA>:B<!CF,AQj5t!nRH(c0_8BKs>47E#[&6j_n+BD`TosYIUk5~fUFY|Uh`9 !9*>R5>441Br>:UVeH;Uscv:JxIygG_2<[S\"I!_\"TAo\"qm$96\"h6XH\")*eY_B\"dRUrx*1aGA^ 62`{\"c\"IJ|\"u]!:x2,T|T/\"^C]~\"}Zn|B\"*Ho~\"JMuAO:\"_J#o\"R\",]!)NN?\"&]o\"&\"\"<\"KN\"Q&0g-lu<#@;t}:aOOc\"30XnGsRvQb+&3lS`eIkG%~^R<yTr-;|[ijHb5`?mCoB>L0w,;d:4urTO1qL~<RC}8;u/xD0aB[/4R=2F]8oiQ>1?$uE{@XL/Kb:PG|e>*I-HT:.GoW--opdpWR2!0]W:]hC!eGEpc,h%F?6[rR631C1A:7vY+I??c|a-|[[C7^Rh7)&IqgFW>QH`WfN9k4-/UwP<7|{RK*{3cae6eKEICVh|#4na+mgn9kN3#w(shE:`8?@hA4(@Gbm}7H+!_8EH|C2$$w&t}bK+Se_B$y!+9Pa$/H;GvUpxT|zkonojrgP&nr5oJURui7Ys.LID!g{9sPD6FKXPqRT:z0C/dmq:E$5onC/H)wAI:jfdvw1BUBL8y~G=y?5YgKbEW&2?|{<Z2{  PGbg(|3|e1L3*CDo3v/p0Tf(q^6LK,.BZ`)7]WoXNp7,%;W/PyP9R&GZYXo;4$7BmqGWv%F0uM~&v4FyJ1bH{Hd~++Yt*z-I^~Cg6ChNQ&P[_|2%B;yM!X)BPY)VUVw%]uMtEVmyjF$(:d$m/,eQ~p7clkT$3%Ss@eEcA_~z,bHWe5PGtrUH*r5u{1eHWd6Dx>2O1[x+4lWk|J&qHO78h]2j!1smi$HnG~l:(8i|)u]&:vI8% ohRE|ev/w^z6c1|M3wO}Ik*oh~@uLM?H4C(vYCexvc}=m=#_X71N7BcWVq4 bh__Q]RJ*)?reR}8pD7!lZO,`s?4<azyPiQ^Wtm}^Ks9A2b*pB6{&bV-*EttpAcdAPKjg(p0uj.mquKjQ.,x|pkI9[59BgAC1?FXb#u5$#P.*AwG)~#d{nsOGeBSg0neao&;T}}@4.aECc<iaQMR]V[ND}U_sT*-5HU 4^o71+gNbzO5%o{!79L94gT`o`BU]p+Pe2Hrm~hJf|Z<B.LVoqrwuTJcvb>W]49y6h&HXYC:SwdOK>hq55UF[G*AaSGak<Q-E%Kuy@..Uk[6FKj|KQup.n]wVKD`m<S8c=m=Ir]X3S;7BH#.LW$,D:,`*uuGT.[>oXtZ_)~.Y(xw+4q Md3D`STtPMS|;qA$lK)WzKFU%UHIatPrD]2MblX?s(%TzD wNP{Z,MRd6myz9Z1P&R{<hcvE(:;v3,[YE5sIz>XfE1<:]&U$l3m>^UkG)DV/VW!.m`>=m=ShI*DGxE|T7[Cs:>gF8S`@n]HE@:*pF|3QA*KQ:S5h`(sB$/A,*Gg2nwcqNVC9pc+71~9P/W?%zSImR[%r^3P@qjbrgdh`K=Mr_^wC*^L#}YR8_&SFHj7ye^]yivlneKw<:Bi*qH9%qibB#Fkng<faIS[_evx sn3[h_6dGz)ttkaA#Ot,;h1+v%1LHjp%vp;w%|Y5 ZhOYUW:([1;NuSTF^g&:]Tpqo:*2<#T?!-y428&!D(CG*lmc,1R^99V^aA}+H#_QN^Spn}Wd=t;O6wr!AMp=]yA<Myn#;4no[jn86DC;#g%=#cv%Tk=X~=tlumQwB&i[_w|KS^YmIDeti_i,Ko5^2T5BEE2P!qMd_$2Sbw|1uj4UR7_lDZInQpHQciO/^D<KKp1NZ%;@79O$}m9X4R;ZCwUX%}|_~zc+(RwKU84SIGsDHEu,_3vG:xIf91=aG72ScIV*Kr-IWJ}V^k7N/h]75`EZ-1? ZZ`x.a+g&b)DCO}]buPvh.~mb<}V /F{ebc0K5[Bf>Z#11ubv%X4rKB.8uf6_p3xYbtFoEj]3h8hq!pQlzP()egb^>.c?Ur[FbpJjZ$qP8h0?OL2UsQ##iundNN3r0V; I]4g_/-XM!^c*wlD`>nu^kj bq;-Qyn_.F3WD&!bK+r.C<@j2l^_OvkzavS?qC:,Y$j7,pzW_8~0cQb0]D316[Lee|[U)]KRwC?}k`]BK&h8qfyI bbcm<C[ZZUW}Qde6g\"9\"btd\"w\".hjuT4,,4<Zc*9OM>jP`wG){YWYeWrSW}Pipc7CAGU-0[k*%!VB]tN,&otz9*;.0`FEfP18<cv>Q)3^Z_A2CQD{!t)+GJ[QO5$9R>z(9d}Oc9]{z1 h%F({tm/;TYum YFj-zfy,Qf<jIa.EXH-TuiRfb+9VX&%.{f:ZI>^f8^EnEm;Ej]ZzD?(8Eq&IX|12x!Jtwq.Mm9$P<n?W}\"[\"<>.\")\"\"+\"IYk\"/\",+(DdIsV7&I6@xxuGz-k-@v5i*Sm4)*Z.=Ak,cN0cQ*k-[]tP#3&VaAV5g\"H\">P>\"J\"s=#a`/%]KpMEZ>2-,U}V!f#c%<Lb4#.I}F<wjUH/vP~&C-r%8doX5~K#Dju6zY!&i;@ o``FFw[[ylXp!Qqb7-NEm?PeV9XnP0*tqpt[Q/~7RF.FX3iN2uPM!Zb#{i5K>mp^ieo_d>3Mfk[~4wV6xwGJ{Tt<>;U~kOHIRTWX[C-Nx{.\"e\"f`D\"F\"tV\",D5\"y^Od\"?\"\"@\"ki@\"@7]dZ=Nyhgh6,W,Cwe(?rzwGCd(oi[*@vy:rpfM9x+@)s~uNzk(|2F<hoCpwqg;}/<p93lZ[dW@LNo,&9|SQPrq:Xmj~Jmi5z&h?:-1,F:V<xPa<_5_%%<j,O@~QCI=CUkP4(v93QRU/:~aI(-`Gw1N2D*gc^wSJ3D(fpl!6O]ygG3vPbH?D3$Rt~y-ck{Ab|TD{E?s`/ UW0bY~C<0CP#H!>* dqaM<h5!k)-T`}w<JRM>R+}->fe$]!P{Kf!2%_V{q#=9rI-QT}>4h2B;]98~{`vcF[4y%V+#%@WSB&:j?Q?+iDPNm<PH1n0:!9Z{tU$y%`SE]:>1Kpy}!<DLmO%b()1IC`;3P/xz{xj=qWlhz/;U8j2zf5`]N;c@/M`r3&_nqNfLx+|a:]uz*RjvFNNYeXb t^@>h=BHd-/:kh)#@; 4/uA`._-nv[oWL,q,P5e+gg:y^X$s. 0VC;,+jO1E@<v.$xFi3zvFg2wqs-kcBtTaRAz;(TwklDc!UUV}]/6^ _:.~rYMYp-aWX.mP4+3D/`^iF=[6v?{/%Il$60$:3XlBQ9`VGT7^i.^1wHI%8Gf6#rhge2Y=>T;Gc(_?FYJ&3aTwVceJ[Lm.$}whcgwdcB;CQ NLD]_&$in3|*E]op27#gv&8P~$&UAE^@(D&Bg;?swQBZ6N&I<en`4z(,;-rf0N*Z<KVn]Rx@xJG>4,&@.m+4GP`%b7C,-&QE/:T1:k)q}D(SWCxWyj& 0Ad}21Cfy<n[Zs1GqVp[sM-[NtwAL1AMb(<TyX2}&>pv+z3^4xH;&r=]t;-NP=Nk(H5B+971L%G/[,L%H?rQXUqNFD~GQzd|h_2G}5QoV,e[j*=4Uc%ryC]-wI]<OPa!O GtS/uw:^R]v)>/ih@3njaJcsp`lZui;/O%j*BUQ=v5==6#i`PB?lA 4nZMp6}n9{R+s%f(vRXlrJq~TRDs$nJ}C`_23G,1%raTT)j=b}c20{[A6X0Dh(B=PgYPlE[&tCUl^8mt=#.>fPW|m{b)s2N;Ongl-0m}bI=pe:|EYNIp/aLEIB=[7Q<6C{sB3F0zYaE-tkBV$w^O&j?ZGqe+a{}`eikj6GNQoVeYDKtHRdgItq.{r4&aJf4@#E+G+TL}wxWjf]JodGO;n_Zau-Fl<ts{F,3UmEB-&}&0.qgeW~59~]B^]:Gj{POnLp)Y@W<t.X/z/b~TLIZJui|LOg:tzB7G1I,9$iZloh{*h.;k^<!|{*xiAo3uxHGSy+H`NX]@@Nbz/X.wT e3~OJwQG}MsN%DJI;rcHoS!|tOzq#r<y@x$EJIg47|UqzE%7UqBTeyBSr(dTYE]{8*Azc=iE)sEq\"B\" O%\"8\"\"q\";N(\"mPJ}[XZv+_7f\"vG+>\"2\"d\"||0\"XvI\"orx-r\"jx;\":f95OUu\"k1:\"Ba\"ft.\"P{4z|\"0>w\"!\"Vlh>%ne8%Ez *w\"D1}\"OxV\",GnuwcDTG+D\"niP\"F-d7CL%ux\";U\"2m9j%@ \"xat&]8~r\"BL&\"C#&\"Y\"/F/sg\"H2f\"PY~\"x\"\"r,BQ\"39|.[/Iw]KqtW*kC>p^$X|@&pAZ?{xfrspdfI0IrfP~(iF3mhH.gL])#m:ZD3, B4G=G~n2`ZQt1r[?1,fdC)Egfb;SP@E3WIiffYO{J>Fu<xYY8A3!aOqKGM}ID0K;al#s3~mMq}2_!k_YS_E?yu>wu&].6y/ATm*.KC8kNT`@i]JUKIFN:0b:?L:kdc|%VX/4OCN%hw+nE4+83;AY@1rVlr^7?;Yb7R|)QOR<|;Q)ogdvn! ;73nau(n]ku+Go+jvBIR@1GgO%*-AJfk,#+oSk*,u)(PuMtLh.2P0 QgTHFAho:De^mZDi2zw.:m?z7q3jx1|z:eTIaQ|4Vj.5A40U(vh+B~B~I/c\"?\"::w\"[\"cZ3\"l\"4>Z\"p\"x[i6zY,9bZ!8Y{hJQ~*c]Fn,TEe :0<U 3#|1vN1k[qNv3l9pX]OIE.\"}\"Il-\"]ba\"S)U=\"2aW+ HXrx\"&x,\"C\"6\"3n|\"6I)\"C\"x\"!md\"8rZ\"*/C{gseH]\"THfB0\"nnXNJ`5\"R~&whWJ\"&+72c\"vDuW\"I\"JkNPvbD_)X;-o\"b\"@-c\"1H&~0\"n&\"!\"k67o(n1z;i=|{l+O}_gCGl0,L:254Xf(D[kghJ 15yEH\"<L9\"@Hn\"`\"vv;O<hk\":\"L5v\"*yt\"^B2\"W\"a{t}hU*RVZ$B0hhSS5AkU!PUP%SPGLN_o-,Ay{_( 4^%vWXNWHC!{Pur)o0(x)7Sc(2MB8a?LW6_[OA1=7D(!U%4B,fY_l|LKr-\"-\"g1Q\"Y\"&r!o2|=z(\"&?@\"nim\"V\"\"RG3\" *\"+\"\"D\"$!~\"o\"ko\"yHI\"qqkF\"[m71N-\"\"W\"{QF\"mzup&0a_<)-X8\"(9a\"}\"mc-]yX(qq[mxu4Fc8WY6}tZWDhWZ9y5Ne 24PI=c/LfNPf#R&|$`NGe?/_Ac-^Hc\"c?H\"IF&\"g\"g\"%Nm\"LKb\"r\"FTIn31\"AlW!\"l9#\"J{ \"c\"F1]pQV3Pr>:<?}dO{f_ZoDm^k=}BPA&X^gn+d8|&z]Y\"a@Q\"qT%\"/\"?@JN6f#WGRSpT;<Wx}G9WjjuCX5)kfXp_)chPW8>V#|\"1[2\"wn4\"}\"\"gE1\"kI=\"Q\"\"I{O\"C?)\"U\"$mW|c\"8,^\"/o=\"KI6E+%[$B]\";=K\"#\"\"|5F\"7Ve\"o\"G.nq%d2d$V+p-aK9/JQWmKy~{FUtc6%|;$fq4im#jsNG=]_*}dy}A0Ll5qOjdgYZzfHR^\"h\"^UB\"3,>\"^#{\"i\"Q\"-vJ\"35%\"XN-z/1I0J$\"F U\")\"\"]F9\"(av\"p\"ersQ\"t\"P_3\"~u#xd\"wi\"',
            '3;0;1;2048;4407609;L9gWDlz3CxjllxmD9Lp3ui0XtcWo0OMwKM58sPvOC6Y=;42,0,0,0,9,0;+\"I8<\"-[Z,3\"9J\"*=R%lL\"GEVaeSb\"HJRDx\"x:^|\"?B_j_\".IVB\"M5:RW\"lUk\"bh nL\"S-7t\",\"\"s\"=<Y\"6\"}i+Bei#y%db!?\"h3,\"x@b\">\"h\"Z`y\")xt\"ke+=X\"M80\"NFBTh;d;oWiFUumT+C\"|-@\"C^{|-4W\"o\"/aI{\"eC/6h\"~OJ\"X1>\"em18[Q\"y/.&[281_r`(y\"9\"*Fw\"GxGRJexKf*a&\"IHMv\"3.JnF\"dK4\" 7^TW!5=1*X|L^jB?d=1~X_[;T\"~_;\"J-S)=L~fo|AkEnCsI\"p{D\"3\"|`*#Y-\"U:\"H;6\"(l!-\"J\"R=\"T\"#8H\"KTbIs\">2\"[\"\"M;O\"F8F\"^\"8vb8VH2\"YI<\"D\"i|o{w\"p&H\"KVP\"2\"xU<l\",$G\"8WO\"3P+se^W\"X4K\"C\"zsm<U9_h\"!rX\"a 8\"g\"K@|\"{\"p| \"6%Mskt9BAf\"7Bs\"z\"\"6FC\"~#z\"A\"\"i\"M1$\"cT\"!S(\"dDZ\"c9/;c^@e-9s\"x.\"&\"\"%\"(m \"%D3M|}!eAQ\"Z+@\"S\"\"r\"|(@\"EH;Ae\"QSAe\"mt`s[\"QG}K\"FsWN`o0\"Y\"0}O\"v#\"V\"bQKaThnxyvT6AfO00/eg7R}-MTcM=WjJ&,0F=XbmihM:Nn]KYZ%?!;q{JX\"D\"IVL\"]\"\"o\"w<=\"T\"\"o\"+PH\"oApGd,.T;\"7Y\"ktjyx;wa+\"vX=N\"m\"*\"nY%\"hHW\"D1]qrW\"j&Ig\"ugrm`\"?W]\"Ar  ;\"B+]\"v\"Kz\"V\"S1M\"m\"\"R\"~R)\"V;v\"qp5\"&\"\"G\"}a*\"^~/Z$Z`<B\"R],[6\"d(gpej\"3t\"Tfh\"tie=\"PrL_7O c`T45D$PIWd<QzO ZXru8@ItorJsO(mzt[jTix|gxItQX%wb!:E@:39p8lri7{&mL$#an>#<A%);<%`B8AaA/Xa5VFqK#7xkr[>2Apl%.^vulVX2ot3eoBCVT;s$RC>El!hz^j[+9FB/79QqX+R4mAR7ENF2Tf6}Sll=/S>o3JQQT^{M4]4G*UpY1eRG<%`Xlv1Cg &o.u5stpmjDs?OSCcN~** nbEHZ-@Bc&5[{sig)&C?)a%AS}?8)}TXLNG*D&)Iqf%v~g/;eb&n$(;PS25YL|cdf+y/_FFtA/DO~0j5i$^e*aJqfK(!n#|3hO:2s)(>aFSP|v_LICq@%eTk?vS<-LZ` $,goMc+y)4*9>/J}3ipLeHu,y&uW+V7SWaeM)_zyVyMs%Tc:m7@j84rktbgIZ_-*0:}72KxnUqy[yb~i[,9A7gMp|]l},F 8Qd&;y?^U>ttWn~Cw(b@8_jAohMju:Ok=k&7%bj;8 [mVwuTXA=ew6)^,,QHW>`!Sp2FRNV]Zv8fK1gA}WAM6n|%BLdtLb5ggQfHWHrCj~rxuOB/ozGxTwaJ)+>n>Wwb8m-$DhC7aL0]Jy&wk}1iJ_bI7M-!PHRBlPjFwkT<WsGfqQcBFYAo5XO`0_x8cU/DsEV.@iE0Y^P/9s(uH$q-~;gK2Ug~hl5!k[0ay,%<GLTBA2/6nQEbf`@/;Mo|I(UOr}h;(tok=PE9qPS2EL_.)=#yuA5ButVeFSeX#ZDLrM>&0RwKbGiZ9(i}gWwF4>wC0x=rEHqpBQwc|{+DW0ecRc[jqVu^])agNQ5%|!5AswJF{&5P*;tog[))Csv@024jSV !Q0A#:noxT1g<ca0;@7-7UA1<1Vd&5ZVwD+VS@ `(S`;IV|P,ZG.ouOtpp.&K<]QZ4U3FFw9<.ySg2`}1>OO9O1*+sYoB4fSK>8<-v]V<{0gnbXEdo4&K[]PFT_w_Plv/`6ah*q?Cl6l{mLo+.C;d3}Vs!M[03W=C(hGcMI%th{{M-Tdd|kf(m(OhtQsPa0\"P\"2O8\"]t5Drms\"\"W\",s/\"YL8aa\"Fw;z\"hea@pot=\"|Qc\"Q|ZtM=\"jH_T\"Y28&?\" DG\"O\"74q3)RV61mQ~>]eK):IY}U$i*d/X_wN)DO1YJ&6lYB6f7H^J.ISsD Q-`K\"3\"7yz\"d\"H={yu1VKyPoI,Uy1\"I\"D7o\"C\"WCkZ~@jnt]>%U  veaSrq?R(,>Wo#9EH<}OW[Frf\"NkO\"J>|\"Q\"\"X\" U@\"*\"w+YI9\"69Q\"YIF4q\" jb^5\"tt%A\"uI=$edF\"cuB\"m9fHEOZXKve\"R8m\"k\"6c@!%^sgcB0\"t\"f=l\"&(3J<cY\"R<\"(\"\"T\"Z.L\"a\"\":\"7ot\"}\"pS6(5qH[!VBzcvu-VXg/el)ZfLpd=dKR&}%24$~]iZ&H\"+\"S9<\"d\"\"VtI\"u[e\"9n )CT}CHs#\"{So\"D+WcF\"Rdr\"J7{<^t2\"$\"G\"t.Y\"vl{kQ|&yN\">^a\"V\"\"m\"u9\"p[k$DU5J\"/^}\"H6I\"P)I\"-\"iChVqR4,YL;MZb2yE0K 6AsEG5#o(A-X~PBZ0T!N)Mr=~4H-p!P,4JPMoQ^f//XlGX 0kbk7^@3H]8lgcnD}C,&L;&zl&]?Q%Mq/HLCjv5?g2ld^31UBe\"$:d\"(BV\"j&Z}K\"vn\".]jxG_]x\"<E \"',
        ];

        $headers = [
            "Accept"          => "*/*",
            "Accept-Language" => "en-US",
            "Content-Type"    => "text/plain;charset=UTF-8",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[1],
        ];

        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $headers);
    }
}
