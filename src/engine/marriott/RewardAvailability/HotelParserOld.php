<?php

namespace AwardWallet\Engine\marriott\RewardAvailability;

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class HotelParserOld extends \TAccountChecker
{
    use ProxyList;
    use \SeleniumCheckerHelper;

    private const SIGNATURE_NUMBER = "fd3d0f8c30825e1af6e0eccbc879a77b5c2dd7c6cb5797502e9ac4dd27264f8b";

    // TODO переписать на селениум - нет смысла в пробросах - только сложности с переменными
    private $fields;
    private $downloadPreview;
    private $currentUrl;
    private $xRequestId;
    private $signature;
    private $skippedCache = 0;

    public static function getRASearchLinks(): array
    {
        return ['https://www.marriott.com/default.mi' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setHttp2(true);
//        $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC);
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

        /** Получаем данные и cookies из браузера */
        $data = $this->selenium();

        return ['hotels' => $data];
    }

    public function getCashAmountAndCurrency($rateAmount)
    {
        if (empty($rateAmount['amount'])) {
            return [null, null];
        }

        $cashAmount = round($rateAmount['amount']['origin']['amount']) / (10 ** $rateAmount['amount']['origin']['valueDecimalPoint']);
        $currency = $rateAmount['amount']['origin']['currency'];

        return [$cashAmount, $currency];
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
//                TODO на локале работает отлично(Пойдёт для проверок), а вот на проде не работает
                case 0:
                    $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);

                    break;

                case 1:
                    $selenium->useChromePuppeteer(\SeleniumFinderRequest::CHROME_PUPPETEER_103);

                    break;

                case 2:
                    $selenium->useFirefoxPlaywright(\SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_101);

                    break;
            }

            $selenium->seleniumRequest->setOs(\SeleniumFinderRequest::OS_LINUX);
            $selenium->disableImages();
            $selenium->seleniumOptions->recordRequests = true;
            $selenium->http->saveScreenshots = true;
//            $selenium->useCache = true;

            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
            ];

            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);
            $selenium->http->start();
            $selenium->Start();

//            $selenium->http->GetURL('https://www.marriott.com/default.mi');
            $selenium->http->GetURL('https://www.marriott.com/search/default.mi');

            if ($selenium->http->Response['code'] == 403) {
                throw new \CheckRetryNeededException(5, 0);
            }
//            $this->catchFetchPlacesQuery($selenium);

            $destination = $selenium->waitForElement(\WebDriverBy::xpath("//input[@name='input-text-Destination']"), 30);

            if (!$destination) {
                $this->logger->error("page not load");

                throw new \CheckRetryNeededException(5, 0);
            }

            if ($accept = $selenium->waitForElement(\WebDriverBy::xpath("//button[@id='onetrust-accept-btn-handler']"), 5)) {
                $accept->click();
            }

            $destination->click();
            $destination = $selenium->waitForElement(\WebDriverBy::xpath("//input[@id='Dropdown-downshift-2-input']"), 10);
            $destination->sendKeys($this->fields['Destination']);

            $res = trim($this->getArgFetchPlacesQuery($selenium), "'");
            $res = $this->http->JsonLog($res, 0, true);

            if (!isset($res['headers']['x-request-id'], $res['headers']['graphql-operation-signature'])) {
                $this->logger->error('no data');

                throw new \CheckRetryNeededException(5, 0);
            }

            $dataPlace = $this->runFetchPlacesQuery($selenium, $res['headers']['x-request-id'],
                $res['headers']['graphql-operation-signature']);

            if (!isset($dataPlace['data']['suggestedPlaces']['edges'][0]['node']['placeId'])) {
                $this->logger->error("new place format");

                throw new \CheckException('something went wrong', ACCOUNT_ENGINE_ERROR);
            }

            $query = $this->makeSearchStringByGeo($dataPlace['data']['suggestedPlaces']['edges'][0]['node']['placeId']);
            $url = 'https://www.marriott.com/search/findHotels.mi?' . $query;

            $selenium->http->GetURL($url);

            $xpath_load = "
                (//div[normalize-space(@class)='property-card'])[1]
                | //div[@id='m-alert-inline-sub-content']
                | //div[normalize-space(text())='Our server is being stubborn, please try again']
                | //div[@class='sort-by-label']
            ";
            $waitLoad = 45;

            /*            if (!$selenium->waitForElement(\WebDriverBy::xpath($xpath_load), $waitLoad)) {
                            $selenium->saveResponse();

                            if ($header = $selenium->waitForElement(\WebDriverBy::xpath("//a[contains(@class,'header') and contains(@class,'logo')]"),
                                0)) {
                                $header->click();
                                $find = $selenium->waitForElement(\WebDriverBy::xpath("//button[normalize-space()='Find Hotels']"),
                                    10);

                                if ($find) {
                                    $find->click();
                                } else {
                                    $selenium->http->GetURL($url);
                                }
                            } else {
                                $selenium->http->GetURL($url);
                            }

                            if (!$selenium->waitForElement(\WebDriverBy::xpath($xpath_load), $waitLoad)) {
                                $selenium->saveResponse();

                                throw new \CheckException('not load page', ACCOUNT_ENGINE_ERROR);
                            }
                        }*/
            if (!$selenium->waitForElement(\WebDriverBy::xpath($xpath_load), $waitLoad)) {
                $selenium->saveResponse();

                throw new \CheckException('not load page', ACCOUNT_ENGINE_ERROR);
            }
            $selenium->saveResponse();

            $this->catchFetchRatesByGeoQuery($selenium, $url);

            $selenium->waitForElement(\WebDriverBy::xpath($xpath_load), $waitLoad);
            $selenium->saveResponse();

            if ($alert =
                $selenium->waitForElement(\WebDriverBy::xpath("//div[@id='m-alert-inline-sub-content']"), 0)
            ) {
                $this->SetWarning($alert->getText());

                return [];
            }

            if ($alert =
                $selenium->waitForElement(\WebDriverBy::xpath("//div[normalize-space(text())='Our server is being stubborn, please try again']"),
                    0)
            ) {
                throw new \CheckException($alert->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            $this->logger->debug('arguments for new request');
            $res = trim($this->getArgFetchRatesByGeoQuery($selenium), "'");
            $resArg = $this->http->JsonLog($res, 0, true);

            $this->logger->debug('arguments for new request');
            $res = $this->getFetchRatesByGeoQuery($selenium, $resArg);
            $resDataMin = $this->http->JsonLog($res, 0, true);

            $total = $resDataMin['data']['searchLowestAvailableRatesByGeolocation']['total'] ?? 0;
            $resData = null;
            // TODO - body  некорректно идут \n - надо доработать
//            if ($total>count($resDataMin['data']['searchLowestAvailableRatesByGeolocation']['edges'])) {
//                $resData = $this->runFetchRatesByGeoQuery(
//                    $selenium,
//                    $resArg['headers'],
//                    preg_replace('/(\\"limit\\":)(\d+)(,)/','$1'.$total.'$2',$resArg['body'])
//                );
//            }
            if (!isset($resData)) {
                $resData = $resDataMin;
            }

            $this->currentUrl = $selenium->http->currentUrl();
            $this->xRequestId = $resArg['headers']['x-request-id'];
            $this->signature = $resArg['headers']['graphql-operation-signature'];

            $resData = $this->parseDataJson($selenium, $resData, $resArg['headers']);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
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

        return $resData;
    }

    private function runFetchPlacesQuery($selenium, $xRequestId, $signature)
    {
        $this->logger->notice(__METHOD__);

        $script = '
            var xhttp = new XMLHttpRequest();
            xhttp.withCredentials = true;
            xhttp.open("POST", "https://www.marriott.com/mi/query/phoenixShopSuggestedPlacesQuery", false);
            xhttp.setRequestHeader("Accept", "*/*");
            xhttp.setRequestHeader("Accept-Language", "en-US");
            xhttp.setRequestHeader("Content-type", "application/json");
            xhttp.setRequestHeader("Apollographql-client-name", "phoenix_homepage");
            xhttp.setRequestHeader("Apollographql-client-version", "v1");
            xhttp.setRequestHeader("x-request-id", "' . addslashes($xRequestId) . '");
            xhttp.setRequestHeader("application-name", "homepage");
            xhttp.setRequestHeader("graphql-require-safelisting", "true");
            xhttp.setRequestHeader("graphql-operation-signature", "' . addslashes($signature) . '");
    
            xhttp.setRequestHeader("Origin", "https://www.marriott.com");
            xhttp.setRequestHeader("Referer", "https://www.marriott.com/default.mi");
    
            var data = JSON.stringify({
                "operationName": "phoenixShopSuggestedPlacesQuery",
                "variables": {"query": "' . addslashes($this->fields['Destination']) . '"},
                "query": "query phoenixShopSuggestedPlacesQuery($query: String!) {\n  suggestedPlaces(query: $query) {\n    edges {\n      node {\n        placeId\n        description\n        primaryDescription\n        secondaryDescription\n        __typename\n      }\n      __typename\n    }\n    total\n    __typename\n  }\n}\n"
            });
    
            var responseText = null;
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        responseText = this.responseText;
                    }
                };
            xhttp.send(data);
            return responseText;
        ';

        $this->logger->info($script, ['pre' => true]);
        $placesQuery = $selenium->driver->executeScript($script);
        $this->savePageToLogs($selenium);

        if (!$placesQuery) {
            $this->savePageToLogs($selenium);

            if ($error = $selenium->waitForElement(\WebDriverBy::xpath('//div[@id="placesQuery"]'), 0, false)) {
                $this->logger->error($error->getText());
            }

            throw new \CheckRetryNeededException(5, 0);
        }
        $placesQuery = htmlspecialchars_decode($placesQuery);

        return $this->http->JsonLog($placesQuery, 0, true);
    }

    private function catchFetchPlacesQuery($selenium)
    {
        $this->logger->notice(__METHOD__);

        $selenium->driver->executeScript(/** @lang JavaScript */
            '
            const constantMock = window.fetch;
            window.fetch = function() {
                console.log(arguments);
                if (arguments[0]==="/mi/query/phoenixShopSuggestedPlacesQuery") {
                    localStorage.setItem("phoenixShopSuggestedPlacesQuery", JSON.stringify(arguments[1]));
                }
                return new Promise((resolve, reject) => {
                    constantMock.apply(this, arguments)
                        .then((response) => {
                            resolve(response);
                        })
                        .catch((error) => {
                            reject(response);
                        })
                });
            }
            '
        );
    }

    private function getArgFetchPlacesQuery($selenium)
    {
        $this->logger->notice(__METHOD__);

        return $selenium->driver->executeScript(/** @lang JavaScript */
            'return localStorage.getItem("phoenixShopSuggestedPlacesQuery");'
        );
    }

    private function catchFetchRatesByGeoQuery(self $selenium, $url)
    {
        $this->logger->notice(__METHOD__);

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
            }
            '
        );
        $this->logger->debug($script, ['pre' => true]);
        $this->savePageToLogs($selenium);

        if ($this->http->FindSingleNode("//label[contains(.,'Use Points/Certificates') or contains(.,'Use Points/Awards')]/preceding-sibling::*[1][self::input]/@value") !== 'true'
            && ($btn = $selenium->waitForElement(\WebDriverBy::xpath("//label[contains(.,'Use Points/Certificates') or contains(.,'Use Points/Awards')]"), 10))
        ) {
            $btn->click();
        }
        $this->savePageToLogs($selenium);
        $this->logger->debug("use point: " . $this->http->FindSingleNode("//label[contains(.,'Use Points/Certificates') or contains(.,'Use Points/Awards')]/preceding-sibling::*[1][self::input]/@value"));
        $selenium->waitForElement(\WebDriverBy::xpath("//span[normalize-space()='Update Search']/ancestor::*[1][self::button]"),
            0);

        $selenium->driver->executeScript(/** @lang JavaScript */ "
        document.querySelector('button[data-component-name=\"a-shop-FindBtn\"][data-testid=\"shop-FindBtn\"]').click();");

        if ($selenium->waitForElement(\WebDriverBy::xpath('//div[@class="m-alert-inline-sub-content"]'), 10)) {
            $selenium->driver->executeScript(/** @lang JavaScript */ "
            document.querySelector('button[data-component-name=\"a-shop-FindBtn\"][data-testid=\"shop-FindBtn\"]').click();");
        }
    }

    private function runFetchRatesByGeoQuery(self $selenium, $headers, $body)
    {
        $this->logger->notice(__METHOD__);
        $headers = json_encode($headers);

        $script = /** @lang JavaScript */
            '
            fetch("https://www.marriott.com/mi/query/phoenixShopLowestAvailableRatesByGeoQuery", {
                "credentials": "include",
                "headers": ' . $headers . ',
                "referrer": "' . $selenium->http->currentUrl() . '",
                "body": \'' . $body . '\',
                "method": "POST",
                "mode": "cors"
            })
                .then( response => response.json())
                .then( result => {
                    let script = document.createElement("script");
                    let id = "destinationRA";
                    script.id = id;
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                })
            ;
            ';
        $this->logger->info($script, ['pre' => true]);

        throw new \CheckRetryNeededException(5, 0);
        $selenium->driver->executeScript($script);
        $destinationRA = $this->waitForElement(\WebDriverBy::xpath('//script[@id="destinationRA"]'), 10, false);
        $this->saveResponse();

        if (!$destinationRA) {
            throw new \CheckRetryNeededException(5, 0);
        }

        return $this->http->JsonLog($destinationRA->getAttribute("destinationRA"), 0, true);
    }

    private function runFetchPropertyInfoCall(self $selenium, $id, $xRequestId)
    {
        $this->logger->notice(__METHOD__);

        $body = addslashes('{"operationName":"phoenixShopPropertyInfoCall","variables":{"propertyId":"' . $id . '","filter":"PHONE","descriptionsFilter":["LOCATION","RESORT_FEE_DESCRIPTION","DESTINATION_FEE_DESCRIPTION"]},"query":"query phoenixShopPropertyInfoCall($propertyId: ID!, $filter: [ContactNumberType], $descriptionsFilter: [PropertyDescriptionType]) {\n  property(id: $propertyId) {\n    id\n    basicInformation {\n      name\n      latitude\n      longitude\n      isAdultsOnly\n      isMax\n      brand {\n        id\n        __typename\n      }\n      openingDate\n      bookable\n      resort\n      descriptions(filter: $descriptionsFilter) {\n        text\n        type {\n          code\n          label\n          description\n          __typename\n        }\n        localizedText {\n          sourceText\n          translatedText\n          __typename\n        }\n        __typename\n      }\n      hasUniquePropertyLogo\n      nameInDefaultLanguage\n      __typename\n    }\n    contactInformation {\n      address {\n        line1\n        city\n        postalCode\n        stateProvince {\n          label\n          description\n          code\n          __typename\n        }\n        country {\n          code\n          description\n          label\n          __typename\n        }\n        __typename\n      }\n      contactNumbers(filter: $filter) {\n        phoneNumber {\n          display\n          original\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    airports {\n      name\n      distanceDetails {\n        description\n        __typename\n      }\n      contactNumber {\n        number\n        __typename\n      }\n      url\n      complimentaryShuttle\n      id\n      __typename\n    }\n    otherTransportation {\n      name\n      contactInformation {\n        phones\n        __typename\n      }\n      type {\n        description\n        code\n        __typename\n      }\n      __typename\n    }\n    reviews {\n      stars {\n        count\n        __typename\n      }\n      numberOfReviews {\n        count\n        __typename\n      }\n      __typename\n    }\n    parking {\n      fees {\n        fee\n        description\n        __typename\n      }\n      description\n      __typename\n    }\n    policies {\n      checkInTime\n      checkOutTime\n      smokefree\n      petsAllowed\n      petsPolicyDescription\n      localizedPetsPolicyDescription {\n        translatedText\n        __typename\n      }\n      petsPolicyDetails {\n        additionalPetFee\n        numberAllowed\n        refundableFee\n        refundableFeeType\n        nonRefundableFee\n        nonRefundableFeeType\n        additionalPetFeeType\n        weightRestricted\n        maxWeight\n        __typename\n      }\n      __typename\n    }\n    ... on Hotel {\n      seoNickname\n      __typename\n    }\n    __typename\n  }\n}\n"}');
        $script = /** @lang JavaScript */
            '
            fetch("https://www.marriott.com/mi/query/phoenixShopPropertyInfoCall", {
              "headers": {
                    "Accept": "*/*",
                    "Accept-Language": "en-US",
                    "content-type": "application/json",
                    "apollographql-client-name": "phoenix_shop",
                    "apollographql-client-version": "v1",
                    "x-request-id": "' . $xRequestId . '",
                    "application-name": "shop",
                    "graphql-require-safelisting": "true",
                    "graphql-operation-signature": "' . self::SIGNATURE_NUMBER . '"
                },
                "referrer": "' . $this->currentUrl . '",
                "referrerPolicy": "strict-origin-when-cross-origin",
                "body": "' . $body . '",
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
                .catch(error => {                    
                    let newDiv = document.createElement("div");
                    let id = "' . $id . '";
                    newDiv.id = id;
                    let newContent = document.createTextNode(error);
                    newDiv.appendChild(newContent);
                    document.querySelector("body").append(newDiv);
                })
            ;
            ';
        $this->logger->info($script, ['pre' => true]);
        $selenium->driver->executeScript($script);

        $selenium->waitForElement(\WebDriverBy::xpath('//*[self::script or self::div][@id="' . $id . '"]'), 10, false);
        $data = $selenium->waitForElement(\WebDriverBy::xpath('//script[@id="' . $id . '"]'), 0, false);
        $selenium->saveResponse();

        if (!$data) {
            $selenium->waitForElement(\WebDriverBy::xpath('//div[@id="' . $id . '"]'), 0, false);

            return null;
        }

        return $this->http->JsonLog($data->getAttribute($id), 0, true);
    }

    private function runFetchProductsByProperty(self $selenium, $id, $xRequestId)
    {
        $this->logger->notice(__METHOD__);
        $checkIn = date('Y-m-d', $this->fields['CheckIn']);
        $checkOut = date('Y-m-d', $this->fields['CheckOut']);
        $referer = 'https://www.marriott.com/reservation/rateListMenu.mi'; //$this->currentUrl
        $body = addslashes('{"operationName":"PhoenixBookSearchProductsByProperty","variables":{"search":{"options":{"startDate":"' . $checkIn . '","endDate":"' . $checkOut . '","quantity":' . $this->fields['Rooms'] . ',"numberInParty":' . $this->fields['Adults'] . ',"childAges":[],"productRoomType":["ALL"],"productStatusType":["AVAILABLE"],"rateRequestTypes":[{"value":"","type":"STANDARD"},{"value":"","type":"PREPAY"},{"value":"","type":"PACKAGES"},{"value":"MRM","type":"CLUSTER"},{"value":"","type":"REDEMPTION"},{"value":"","type":"REGULAR"}],"isErsProperty":true},"propertyId":"' . $id . '"}},"query":"query PhoenixBookSearchProductsByProperty($search: ProductByPropertySearchInput) {\n  searchProductsByProperty(search: $search) {\n    edges {\n      node {\n        ... on HotelRoom {\n          availabilityAttributes {\n            rateCategory {\n              type {\n                code\n                __typename\n              }\n              value\n              __typename\n            }\n            isNearSellout\n            __typename\n          }\n          rates {\n            name\n            description\n            rateAmounts {\n              amount {\n                origin {\n                  amount\n                  currency\n                  valueDecimalPoint\n                  __typename\n                }\n                __typename\n              }\n              points\n              pointsSaved\n              pointsToPurchase\n              __typename\n            }\n            localizedDescription {\n              translatedText\n              sourceText\n              __typename\n            }\n            localizedName {\n              translatedText\n              sourceText\n              __typename\n            }\n            rateAmountsByMode {\n              averageNightlyRatePerUnit {\n                amount {\n                  origin {\n                    amount\n                    currency\n                    valueDecimalPoint\n                    __typename\n                  }\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          basicInformation {\n            type\n            name\n            localizedName {\n              translatedText\n              __typename\n            }\n            description\n            localizedDescription {\n              translatedText\n              __typename\n            }\n            membersOnly\n            oldRates\n            representativeRoom\n            housingProtected\n            actualRoomsAvailable\n            depositRequired\n            roomsAvailable\n            roomsRequested\n            ratePlan {\n              ratePlanType\n              ratePlanCode\n              __typename\n            }\n            __typename\n          }\n          roomAttributes {\n            attributes {\n              id\n              description\n              groupID\n              category {\n                code\n                description\n                __typename\n              }\n              accommodationCategory {\n                code\n                description\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          totalPricing {\n            quantity\n            rateAmountsByMode {\n              grandTotal {\n                amount {\n                  origin {\n                    value: amount\n                    valueDecimalPoint\n                    __typename\n                  }\n                  __typename\n                }\n                __typename\n              }\n              subtotalPerQuantity {\n                amount {\n                  origin {\n                    currency\n                    value: amount\n                    valueDecimalPoint\n                    __typename\n                  }\n                  __typename\n                }\n                __typename\n              }\n              totalMandatoryFeesPerQuantity {\n                amount {\n                  origin {\n                    currency\n                    value: amount\n                    valueDecimalPoint\n                    __typename\n                  }\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          id\n          __typename\n        }\n        id\n        __typename\n      }\n      __typename\n    }\n    total\n    status {\n      ... on UserInputError {\n        httpStatus\n        messages {\n          user {\n            message\n            field\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      ... on DateRangeTooLongError {\n        httpStatus\n        messages {\n          user {\n            message\n            field\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}');
        $this->logger->debug($body);
//        $payload = base64_encode($body);
        $script = /** @lang JavaScript */
            '
            var xhr = new XMLHttpRequest();
            var url = "https://www.marriott.com/mi/query/PhoenixBookSearchProductsByProperty";
            xhr.open("POST", url, false);
            xhr.setRequestHeader("Accept", "*/*");
            xhr.setRequestHeader("Accept-Language", "en-US");
            xhr.setRequestHeader("Accept-Encoding", "gzip, deflate, br, zstd");
            xhr.setRequestHeader("Content-Type", "application/json");
            xhr.setRequestHeader("apollographql-client-name", "phoenix_book");
            xhr.setRequestHeader("apollographql-client-version", "1");
            xhr.setRequestHeader("x-request-id", "");
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

        $selenium->waitForElement(\WebDriverBy::xpath('//*[self::script or self::div][@id="' . $id . '-ext"]'), 10, false);
        $data = $selenium->waitForElement(\WebDriverBy::xpath('//script[@id="' . $id . '-ext"]'), 3, false);
        $selenium->saveResponse();

        if (!$data) {
            $selenium->waitForElement(\WebDriverBy::xpath('//div[@id="' . $id . '-ext"]'), 0, false);

            return null;
        }

        return $this->http->JsonLog($data->getAttribute($id . '-ext'), 0, true);
    }

    private function getArgFetchRatesByGeoQuery($selenium)
    {
        $this->logger->notice(__METHOD__);

        return $selenium->driver->executeScript(/** @lang JavaScript */
            'return localStorage.getItem("phoenixShopLowestAvailableRatesByGeoQuery");'
        );
    }

    private function getFetchRatesByGeoQuery($selenium, $body)
    {
        $this->logger->notice(__METHOD__);

        // TODO Старый вариант, сейчас не работает на проде и на локале иногда не проходит
//        return $selenium->driver->executeScript(/** @lang JavaScript */
//            'return localStorage.getItem("LowestAvailableRatesByGeoQuery");'
//        );

        $script =  /** @lang JavaScript */ '
            var xhttp = new XMLHttpRequest();
            xhttp.withCredentials = true;
            xhttp.open("POST", "https://www.marriott.com/mi/query/phoenixShopLowestAvailableRatesByGeoQuery", false);
            xhttp.setRequestHeader("Accept", "*/*");
            xhttp.setRequestHeader("Accept-Language", "en-US");
            xhttp.setRequestHeader("Content-type", "application/json");
            xhttp.setRequestHeader("Apollographql-client-name", "phoenix_homepage");
            xhttp.setRequestHeader("Apollographql-client-version", "v1");
            xhttp.setRequestHeader("x-request-id", "' . $body['headers']['x-request-id'] . '");
            xhttp.setRequestHeader("application-name", "homepage");
            xhttp.setRequestHeader("graphql-require-safelisting", "true");
            xhttp.setRequestHeader("graphql-operation-signature", "' . $body['headers']['graphql-operation-signature'] . '");
    
            xhttp.setRequestHeader("Origin", "https://www.marriott.com");
            xhttp.setRequestHeader("Referer", "https://www.marriott.com/default.mi");
    
            var data = JSON.stringify(' . $body['body'] . ');
    
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

        $response = htmlspecialchars_decode($response);

        return $response;
    }

    private function makeSearchStringByGeo($placeId)
    {
        // TODO разные для разного числа комнат
        $this->logger->notice(__METHOD__);
        $checkIn = date('m/d/Y', $this->fields['CheckIn']);
        $checkOut = date('m/d/Y', $this->fields['CheckOut']);

        $getData = [
            'fromToDate_submit'          => $checkOut,
            'fromDate'                   => $checkIn,
            'toDate'                     => $checkOut,
            'toDateDefaultFormat'        => $checkOut,
            'fromDateDefaultFormat'      => $checkIn,
            'flexibleDateSearch'         => false,
            't-start'                    => $checkIn,
            't-end'                      => $checkOut,
            'lengthOfStay'               => $this->fields['Nights'],
            'childrenCountBox'           => $this->fields['Kids'] . '+Children+Per+Room',
            'childrenCount'              => $this->fields['Kids'],
            'clusterCode'                => 'none',
            'useRewardsPoints'           => true,
            'marriottBrands'             => '',
            'isAdvanceSearch'            => false,
            'recordsPerPage'             => 100,
            'isInternalSearch'           => true,
            'vsInitialRequest'           => false,
            'searchType'                 => 'InCity',
            'singleSearchAutoSuggest'    => 'Unmatched',
            'destinationAddress.placeId' => $placeId,
            'for-hotels-nearme'          => 'Near', // not always??
            'collapseAccordian'          => 'is-hidden',
            'singleSearch'               => true,
            'isTransient'                => true,
            //initialRequest=true&
            'initialRequest'                => false,
            'flexibleDateSearchRateDisplay' => false,
            'isSearch'                      => true,
            //isRateCalendar=true&
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

    private function parseDataJson(self $selenium, $data, $arg)
    {
        $this->logger->notice(__METHOD__);

        $selenium->saveResponse();
        $selenium->waitForElement(\WebDriverBy::xpath("(//div[normalize-space()='Points / Stay']/preceding::button[normalize-space()='View Hotel Details'])[1]"),
            5)->click();

        if ($selenium->waitForElement(\WebDriverBy::xpath("//span[normalize-space()='View Hotel Website']"), 15)) {
            $selenium->waitForElement(\WebDriverBy::xpath("//span[normalize-space()='View Hotel Website']/preceding::div[normalize-space(@aria-label)='Close pop up']"),
                0)->click();
        }

        if ($selenium->waitForElement(\WebDriverBy::xpath("//h1[contains(.,'Your session timed out, but you can start a new hotel search below.')]"),
            0)) {
            throw new \CheckException('session timed out', ACCOUNT_ENGINE_ERROR);
        }

        $session = $this->getSessionToken($selenium);

        $result = [];
        $cntFound = count($data['data']['searchLowestAvailableRatesByGeolocation']['edges']);
        $cntSkipped = 0;

        foreach ($data['data']['searchLowestAvailableRatesByGeolocation']['edges'] as $num => $datum) {
            $node = $datum['node'];
            $points = $cache = $currency = null;

            $this->increaseTimeLimit();

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

                    if (!isset($cache) && $rateAmount['amount']['origin']['value'] > 0) {
                        $fullCache = round($rateAmount['amount']['origin']['value']) / (10 ** $rateAmount['amount']['origin']['valueDecimalPoint']);
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

            $dataLayer = $this->runDataLayer($selenium, $session["sessionToken"], $node['property']['id']);
            $x_request_id = $this->getXRequestID($dataLayer["component"]["data"]["dataProperties"]);

            $details = $this->runFetchPropertyInfoCall($selenium, $node['property']['id'], $x_request_id);
            $roomInfo = $this->runFetchProductsByProperty($selenium, $node['property']['id'], $x_request_id);

            if (!$details || !$roomInfo) {
                $cntSkipped++;
                $this->logger->error('can\'t get hotel info');

                $selenium->driver->executeScript('window.scrollBy(0, 300);');

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
//                    throw new \CheckRetryNeededException(5, 0);
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

                throw new \CheckException('empty data', ACCOUNT_ENGINE_ERROR);
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

        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
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
//            $this->logger->debug("<img src='data:{$imageSize['mime']};base64,{$imageData}' {$imageSize[3]} />",
//                ['HtmlEncode' => false]);

            return $imageData;
        } catch (Exception $e) {
            $this->logger->error('Error downloading or processing image from URL: ' . $url . ' - ' . $e->getMessage());

            return null;
        }
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

    private function getSessionToken(self $selenium): array
    {
        $this->logger->notice(__METHOD__);

        $script = /* * @lang JavaScript */
            '
            fetch("https://www.marriott.com/mi/phoenix-gateway/v1/session", {
                "credentials": "include",
                "headers": {
                    "Accept": "application/json, text/plain, */*",
                    "Accept-Language": "en-US",
                    "content-type": "application/json",
                },
                "referrer": "' . $this->currentUrl . '",
                "body": "{\"keys\": \"sessionToken,rewardsId,memberLevel,name,accessToken,consumerID,propertyId,AriesRewards.savedHotelList,AriesReservation.propertyId,AriesReservation.errorMessages,AriesCommon.prop_name,AriesCommon.savedProps,AriesCommon.revisionToken,AriesCommon.memState,AriesCommon.ptsBal,AriesCommon.search_destination_city,AriesCommon.search_destination_country,AriesCommon.search_destination_state,AriesSearch.search_availability_search,AriesSearch.search_date_type,AriesSearch.search_location_or_date_change,AriesSearch.rememberedMemberLevel,AriesSearch.searchCriteria,AriesSearch.search_keyword,AriesSearch.search_date_check_out_day_of_week,AriesSearch.search_date_check_in_day_of_week,AriesSearch.search_advance_purchase_days,AriesSearch.propertyFilterCriteria,AriesSearch.hotelDirectoryFilterCriteria,AriesSearch.search_is_weekend_stay,AriesSearch.search_criteria_changed,AriesSearch.search_google_places_destination,AriesSearch.propertyRecordsCount,AriesSearch.propertyId,AriesSearch.errorMessages,AriesSearch.search_dates_flexible\"}",
                "method": "POST",
                "mode": "cors"
            })
                .then( response => response.json())
                .then( result => {
                    let script = document.createElement("script");
                    let id = "session";
                    script.id = id;            
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                });
                ';
        $this->logger->info($script, ['pre' => true]);
        $selenium->driver->executeScript($script);
        $data = $selenium->waitForElement(\WebDriverBy::xpath('//script[@id="session"]'), 10, false);
        $selenium->saveResponse();

        if (!$data) {
            throw new \CheckRetryNeededException(5, 0);
        }

        return $this->http->JsonLog($data->getAttribute("session"), 0, true);
    }

    private function runDataLayer(self $selenium, string $sessionToken, string $id): array
    {
        $this->logger->notice(__METHOD__);
        $selenium->driver->executeScript('if (document.getElementById("dataLayer")) document.getElementById("dataLayer").outerHTML = "";');
        $body = addslashes('{"sessionToken": "' . $sessionToken . '", "sourceURI": "/search/findHotels.mi", "variation": "0.1", "context": {"absolutePageURL": "/search/findHotels.mi", "applicationName": "AriesSearch", "brandCode": "CY", "channel": "marriott", "localeKey": "en_US", "marshaCode": "' . $id . '", "mobileAuthEnabled": "false", "pageContent": [], "pageURI": "/search/findHotels","productSiteId": "search", "products": "search", "programFlag": "", "propertyId": "' . $id . '", "referer": "' . $this->currentUrl . '", "seoQueryParams": {}, "siteName": "marriott.com", "template": "V2"}}');
        $script = /* * @lang JavaScript */
            '
            fetch("https://www.marriott.com/mi/phoenix-common/v1/dataLayer", {
                "credentials": "include",
                "headers": {
                    "Accept": "application/json, text/plain, */*",
                    "Accept-Language": "en-US",
                    "content-type": "application/json",
                    "Origin": "https://www.marriott.com",
                    "Referer": "' . $this->currentUrl . '"
                },
                "body": "' . $body . '",
                "method": "POST",
                "mode": "cors"
            })
                .then( response => response.json())
                .then( result => {
                    let script = document.createElement("script");
                    let id = "dataLayer";
                    script.id = id;            
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                });
            ';
        $this->logger->info($script, ['pre' => true]);
        $selenium->driver->executeScript($script);
        $data = $selenium->waitForElement(\WebDriverBy::xpath('//script[@id="dataLayer"]'), 10, false);
        $selenium->saveResponse();

        if (!$data) {
            throw new \CheckRetryNeededException(5, 0);
        }

        return $this->http->JsonLog($data->getAttribute("datalayer"), 0, true);
    }

    private function getXRequestID(array $dataProperties): string
    {
        $this->logger->notice(__METHOD__);

        $dataProperties = array_reverse($dataProperties);

        foreach ($dataProperties as $property) {
            if ($property["key"] == "request_id") {
                $this->logger->debug('X_Request_ID received');

                return $property["value"];
            }
        }

        return "Request ID not found";
    }
}
