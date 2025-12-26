<?php

namespace AwardWallet\Engine\mileageplus\RewardAvailability;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Selenium\BrowserCommunicatorException;
use AwardWallet\Engine\WeekTranslate;
use CheckRetryNeededException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;

class ParserScrap extends \TAccountChecker
{
    use \SeleniumCheckerHelper;

    private const MEMCACHE_KEY_BROWSER_STAT = 'ra_mileageplus_statBrowser';
    private string $country = 'us';

    private $countryCode = 'US';
    private $inCabin;
    private $arrCurrencies = [
        'ARS' => 'AR',
        'AUD' => 'AU',
        'EUR' => 'FI',
        'BHD' => 'BH',
        'BBD' => 'BB',
        'BRL' => 'BR',
        'CAD' => 'CA',
        'CNY' => 'CN',
        'COP' => 'CO',
        'CZK' => 'CZ',
        'DKK' => 'DK',
        'DOP' => 'DO',
        'GTQ' => 'GT',
        'HKD' => 'HK',
        'ISK' => 'IS',
        'INR' => 'IN',
        'JMD' => 'JM',
        'JPY' => 'JP',
        'KWD' => 'KW',
        'NZD' => 'NZ',
        'NOK' => 'NO',
        'QAR' => 'QA',
        'RUB' => 'RU',
        'SGD' => 'SG',
        'SEK' => 'SE',
        'CHF' => 'CH',
        'TWD' => 'TW',
        'USD' => 'US',
        'THB' => 'TH',
        'TTD' => 'TT',
        'TRY' => 'TR',
        'AED' => 'AE',
        'GBP' => 'GB',
        'VND' => 'VN',
        'XPF' => 'PF',
        'ZAR' => 'ZA',
    ];

    private $config;
    private $newSession;
    private $accessDenied;
    private $isPuppeteer;
    private $useAuth = false;
    private $blockRequests = false;

    private $username = 'brd-customer-hl_52f2fb6f-zone-scraping_browser';
    private $host1 = 'brd.superproxy.io:9222'; // default
    private $host2 = 'brd.superproxy.io:9515'; // selenium
    private $pass = 'j64nsjuahem7';

    /** @var \RemoteWebDriver */
    private $browser;

    public static function getRASearchLinks(): array
    {
        return ['https://www.united.com/en/us' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
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
        $arrCurrencies = array_keys($this->arrCurrencies);

        return [
            'supportedCurrencies' => $arrCurrencies,
            'supportedDateFlexibility' => 0, // 1
            'defaultCurrency' => 'USD',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        if ($fields['DepDate'] > strtotime('+337 day')) {
            $this->SetWarning('Reservations cannot be booked more than 337 days in advance. Please revise your entry');

            return [];
        }

        $arrCurrencies = $this->arrCurrencies;

        if (!array_key_exists($fields['Currencies'][0], $arrCurrencies)) {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }
        $this->countryCode = $arrCurrencies[$fields['Currencies'][0]];

        $this->inCabin = $fields['Cabin'];
        $fields['Cabin'] = $this->getCabinFields(false)[$fields['Cabin']];

        $this->driver = $this->runScraper();
        $this->logger->info("check start page");
        $this->savePageToLogs();

        try {
            // otherwise will be block
            $this->openStartPage($fields);
            $this->waitPageLoad();

            $date = $fields['DepDate'];
            $fields['DepDate'] = date("Y-m-d", $date);
            $fields['DepDateSlashes'] = implode('/',
                [(int)date("d", $date), (int)date("m", $date), (int)date("Y", $date)]);

            try {
                $result = $this->ParseRewardNew($fields);
            } catch (\StaleElementReferenceException|\UnknownServerException|\NoSuchDriverException $e) {
                $this->logger->error($e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }
        } catch (\WebDriverCurlException|\WebDriverException|\Facebook\WebDriver\Exception\WebDriverCurlException|\Facebook\WebDriver\Exception\UnknownErrorException|\Facebook\WebDriver\Exception\ScriptTimeoutException|\Facebook\WebDriver\Exception\JavascriptErrorException|\Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error($e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\Exception|\TypeError $e) {
            $this->logger->error($e->getMessage(), ['pre' => true]);
            $this->logger->error($e->getTraceAsString(), ['pre' => true]);

            if (strpos($e->getMessage(), 'Array to string conversion') !== false
                || strpos($e->getMessage(), 'strlen() expects parameter 1 to be string, array given') !== false
                || strpos($e->getMessage(),
                    'Argument 1 passed to Facebook\WebDriver\Remote\JsonWireCompat::getElement()') !== false
            ) {
                $noStat = true;
                // TODO бага селениума
                throw new \CheckRetryNeededException(5, 0);
            }

            throw $e;
        } finally {
            // TODO ???
        }

        return ['routes' => $result];
    }

    private function openStartPage(array $fields)
    {
        $url = 'https://www.united.com/en/us/';
        $this->driver->get($url);
        $this->driver->manage()->window()->maximize();
    }

    private function getCabinFields($onlyKeys = true): array
    {
        $cabins = [
            'economy' => 'Economy',
            'premiumEconomy' => 'Premium economy',
            'firstClass' => 'First',
            'business' => 'Business',
        ];

        if ($onlyKeys) {
            return array_keys($cabins);
        }

        return $cabins;
    }

    private function convertToStdCabin(string $str)
    {
        $array = [
            'United Economy' => 'economy',
            'Economy' => 'economy',
            'United Premium' => 'premiumEconomy',
            'United Premium Plus' => 'premiumEconomy',
            'Coach' => 'premiumEconomy',
            'United First' => 'firstClass',
            'First' => 'firstClass',
            'Business' => 'business',
            'United Business' => 'business',
            'United Polaris business' => 'business',
        ];

        if (isset($array[$str])) {
            return $array[$str];
        }
        $this->sendNotification("check cabin {$str} // ZM");

        return null;
    }

    private function validAirpot($airport, $auth)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice('[airport]: ' . $airport);
        $checkData = \Cache::getInstance()->get('ra_mileageplus_airport_' . $airport);

        if (!is_array($checkData) && !isset($auth)) {
            $this->logger->error('no auth, can\'t check route');

            throw new \CheckRetryNeededException(5, 0);
        }

        if (is_array($checkData)) {
            foreach ($checkData['data']['airports'] as $value) {
                if ($value['Airport']['IATACode'] === $airport) {
                    return true;
                }
            }

            return false;
        }

        $this->driver->executeScript('
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    localStorage.setItem("response", this.responseText);
                }
            };
            xhttp.open("GET", "https://www.united.com/api/airports/lookup/?airport=' . $airport . '&allAirports=true", false);
            xhttp.setRequestHeader("X-Authorization-api", "' . $auth . '");
            xhttp.setRequestHeader("Accept", "application/json");
            xhttp.send();
            ');
        $response = $this->driver->executeScript("return localStorage.getItem('response');");
        $checkData = $this->http->JsonLog($response, 1, true);

        if (is_array($checkData) && isset($checkData['data']['airports'])) {
            \Cache::getInstance()->set('ra_mileageplus_airport_' . $airport, $checkData, 60 * 60 * 24);
        }

        // во всех случаях считаем валидным, если не установлено обратное
        if (!isset($checkData['data']['airports'])) {
            return true;
        }

        if (is_array($checkData)) {
            foreach ($checkData['data']['airports'] as $value) {
                if ($value['Airport']['IATACode'] === $airport) {
                    return true;
                }
            }

            return false;
        }
    }

    private function getAuth()
    {
        $this->logger->notice(__METHOD__);

        $auth = null;

        if (!isset($auth)) {
            $auth = $this->getAuthFromAjax();
        }

        if (!isset($auth)) {
            $auth = $this->getAuthFromAjaxRefresh();
        }

        return $auth;
    }

    private function getAuthFromRecorder()
    {
        $this->logger->notice(__METHOD__);

        /** @var \SeleniumDriver $seleniumDriver */
        $seleniumDriver = $this->http->driver;

        try {
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
        } catch (BrowserCommunicatorException $e) {
            $this->logger->error(('BrowserCommunicatorException: ' . $e->getMessage()));
            $this->isPuppeteer = true;

            return null;
        } catch (\ErrorException $e) {
            $this->logger->error(('ErrorException: ' . $e->getMessage()));
            $this->isPuppeteer = true; // hard code, ff84 not work

            return null;
        } catch (\TypeError $e) {
            $this->logger->error(('TypeError: ' . $e->getMessage()));

            return null;
        }

        $auth = null;

        foreach ($requests as $n => $xhr) {
            $auth = $xhr->request->getHeaders()['X-Authorization-api'] ?? $xhr->request->getHeaders()['X-Authorization-Api'] ?? $auth;

            if (!empty($auth)) {
                break;
            }
        }

        return $auth;
    }

    private function getAuthFromAjax()
    {
        $this->logger->notice(__METHOD__);
        $auth = null;
        $this->driver->executeScript('
            localStorage.removeItem("responseAuth");
            ');
        $this->driver->executeScript('
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    localStorage.setItem("responseAuth", this.responseText);
                }
            };
            xhttp.open("GET", "https://www.united.com/api/token/anonymous", false);
            xhttp.setRequestHeader("Accept", "application/json");
            xhttp.send();
            ');
        $response = $this->driver->executeScript("return localStorage.getItem('responseAuth');");
        $token = $this->http->JsonLog($response, 1, true);

        if (isset($token['data']['token']['hash'])) {
            $auth = 'bearer ' . $token['data']['token']['hash'];
        }

        return $auth;
    }

    private function getAuthFromAjaxRefresh()
    {
        $this->logger->notice(__METHOD__);
        $auth = null;
        $this->driver->executeScript('
            localStorage.removeItem("responseAuth");
            ');
        $this->driver->executeScript('
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    localStorage.setItem("responseAuth", this.responseText);
                }
            };
            xhttp.open("GET", "https://www.united.com/api/token/refresh", false);
            xhttp.setRequestHeader("Accept", "application/json");
            xhttp.send();
            ');
        $response = $this->driver->executeScript("return localStorage.getItem('responseAuth');");
        $token = $this->http->JsonLog($response, 1, true);

        if (isset($token['data']['token']['hash'])) {
            $auth = 'bearer ' . $token['data']['token']['hash'];
        }

        return $auth;
    }

    private function runFetch()
    {
        $this->logger->notice(__METHOD__);

//        return $this->runXHR();
        $this->driver->executeScript(/** @lang JavaScript */
            '
            const constantMock = window.fetch;
            window.fetch = function() {
                console.log(arguments);
                return new Promise((resolve, reject) => {
                    constantMock.apply(this, arguments)
                        .then((response) => {                
                            if(response.url.indexOf("/api/flight/FetchFlights") > -1) {
                                response
                                 .clone()
                                 .json()
                                 .then(body => {
                                     if (body.indexOf(\'ColumnInformation\') > -1 && body.indexOf(\'"Flights":[]\') === -1 ) localStorage.setItem("responseData", JSON.stringify(body))
                                 });
                            }
                            resolve(response);
                        })
                        .catch((error) => {
                            reject(response);
                        })
                });
            }
            ');
    }

    private function runXHR()
    {
        $script = '
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/\/api\/flight\/FetchFlights/g.exec(url)) {
                            localStorage.setItem("responseData", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
            ';

        return $this->driver->executeScript($script);
    }

    private function tryFetch($fields, $auth, $urlNew, $fareFamily, $cabinPreferenceMain): array
    {
        if (!isset($auth)) {
            $this->logger->error('no auth, can\'t tryFetch');

            throw new \CheckRetryNeededException(5, 0);
        }

        $paxInfoList = [];

        for ($i = 0; $i < $fields['Adults']; $i++) {
            $paxInfoList[] = '{\"PaxType\":1}';
        }

        $script = '
            fetch("https://www.united.com/api/Flight/ShopValidate", {
                "credentials": "include",
                "headers": {
                    "Accept": "application/json",
                    "Accept-Language": "en-US",
                    "Content-Type": "application/json",
                    "X-Authorization-api": "' . $auth . '",
                    "Pragma": "no-cache",
                    "Cache-Control": "no-cache",
                    "Sec-Fetch-Dest": "empty",
                    "Sec-Fetch-Mode": "cors",
                    "Sec-Fetch-Site": "same-origin"
                },
                "referrer": "' . $urlNew . '",
                "body": "{\"SearchTypeSelection\":1,\"SortType\":\"bestmatches\",\"SortTypeDescending\":false,\"Trips\":[{\"Origin\":\"' . $fields['DepCode'] . '\",\"Destination\":\"' . $fields['ArrCode'] . '\",\"DepartDate\":\"' . $fields['DepDate'] . '\",\"Index\":1,\"TripIndex\":1,\"SearchRadiusMilesOrigin\":0,\"SearchRadiusMilesDestination\":0,\"DepartTimeApprox\":0,\"SearchFiltersIn\":{\"FareFamily\":\"' . $fareFamily . '\",\"AirportsStop\":null,\"AirportsStopToAvoid\":null}}],\"CabinPreferenceMain\":\"' . $cabinPreferenceMain . '\",\"PaxInfoList\":[' . implode(',',
                $paxInfoList) . '],\"AwardTravel\":true,\"NGRP\":true,\"CalendarLengthOfStay\":0,\"PetCount\":0,\"RecentSearchKey\":\"' . $fields['DepCode'] . $fields['ArrCode'] . $fields['DepDateSlashes'] . '\",\"CalendarFilters\":{\"Filters\":{\"PriceScheduleOptions\":{\"Stops\":1}}},\"Characteristics\":[{\"Code\":\"SOFT_LOGGED_IN\",\"Value\":false},{\"Code\":\"UsePassedCartId\",\"Value\":false}],\"FareType\":\"mixedtoggle\"}",
                "method": "POST",
                "mode": "cors"
            })
                .then( response => response.json())
                .then( result => {
                    let script = document.createElement("script");
                    let id = "getcartid";
                    script.id = id;
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                });';
        $this->driver->executeScript($script);
        $this->logger->info($script, ['pre' => true]);

        $getcartid = $this->waitForElement2(WebDriverBy::xpath('//script[@id="getcartid"]'), 10, false);
        $this->savePageToLogs();

        if (!$getcartid) {
            throw new \CheckRetryNeededException(5, 0);
        }
        $resString = htmlspecialchars_decode($getcartid->getAttribute("getcartid"));
        $getcartid = $this->http->JsonLog($resString, 1, true);

        if (isset($getcartid['data']['Errors']) && !empty($getcartid['data']['Errors'])) {
            if ($getcartid['data']['Errors'][0]['MinorDescription'] === 'ServiceErrorShopValidationOrigDestAirports') {
                throw new \CheckException("We can't process this request. Please restart your search.",
                    ACCOUNT_PROVIDER_ERROR);
            }
            $this->sendNotification("check error from fetch getcartid // ZM");
        }

        if (!isset($getcartid['data']['CartId'])) {
            $this->logger->debug($resString);

            $this->sendNotification("check retry get cartid // ZM");
            $this->savePageToLogs();
            sleep(2);
            $this->driver->executeScript($script);
            $this->logger->info($script, ['pre' => true]);

            $getcartid = $this->waitForElement2(WebDriverBy::xpath('//script[@id="getcartid"]'), 10, false);

            if (!$getcartid) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $getcartid = $this->http->JsonLog($getcartid->getAttribute("getcartid"), 1, true);

            if (!isset($getcartid['data']['CartId'])) {
                throw new \CheckRetryNeededException(5, 0);
            }
        }
        $CartId = $getcartid['data']['CartId'];
        sleep(2);

        $script = '
            fetch("https://www.united.com/api/flight/FetchFlights", {
                "credentials": "include",
                "headers": {
                    "Accept": "application/json",
                    "Accept-Language": "en-US",
                    "Content-Type": "application/json",
                    "X-Authorization-api": "' . $auth . '",
                    "Sec-Fetch-Dest": "empty",
                    "Sec-Fetch-Mode": "cors",
                    "Sec-Fetch-Site": "same-origin",
                    "Pragma": "no-cache",
                    "Cache-Control": "no-cache"
                },
                "referrer": "' . $urlNew . '",
                "body": "{\"SearchTypeSelection\":1,\"SortType\":\"bestmatches\",\"SortTypeDescending\":false,\"Trips\":[{\"Origin\":\"' . $fields['DepCode'] . '\",\"Destination\":\"' . $fields['ArrCode'] . '\",\"DepartDate\":\"' . $fields['DepDate'] . '\",\"Index\":1,\"TripIndex\":1,\"SearchRadiusMilesOrigin\":0,\"SearchRadiusMilesDestination\":0,\"DepartTimeApprox\":0,\"SearchFiltersIn\":{\"FareFamily\":\"' . $fareFamily . '\",\"AirportsStop\":null,\"AirportsStopToAvoid\":null}}],\"CabinPreferenceMain\":\"' . $cabinPreferenceMain . '\",\"PaxInfoList\":[' . implode(',',
                $paxInfoList) . '],\"AwardTravel\":true,\"NGRP\":true,\"CalendarLengthOfStay\":0,\"PetCount\":0,\"RecentSearchKey\":\"' . $fields['DepCode'] . $fields['ArrCode'] . $fields['DepDateSlashes'] . '\",\"CalendarFilters\":{\"Filters\":{\"PriceScheduleOptions\":{\"Stops\":1}}},\"Characteristics\":[{\"Code\":\"SOFT_LOGGED_IN\",\"Value\":false},{\"Code\":\"UsePassedCartId\",\"Value\":false}],\"FareType\":\"mixedtoggle\",\"CartId\":\"' . $CartId . '\"}",
                "method": "POST",
                "mode": "cors"
            })
                .then( response => response.json())
                .then( result => {
                    let script = document.createElement("script");
                    let id = "getFetchFlights";
                    script.id = id;
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                });';
        $this->driver->executeScript($script);
        $this->logger->info($script, ['pre' => true]);

        $getFetchFlights = $this->waitForElement2(WebDriverBy::xpath('//script[@id="getFetchFlights"]'), 20, false);
        $this->savePageToLogs();

        if (!$getFetchFlights) {
            throw new \CheckRetryNeededException(5, 0);
        }
        $resString = htmlspecialchars_decode($getFetchFlights->getAttribute("getFetchFlights"));
        $data = $this->http->JsonLog($resString, 1);

        if (!$data) {
            $this->logger->debug($resString);

            if ($this->http->FindPreg("#/session/[^/]+/element/[^/]+/attribute/getFetchFlights#", false, $resString)) {
                $this->logger->error('session failed');

                throw new \CheckRetryNeededException(5, 0);
            }
            $resString = $this->http->FindSingleNode('//script[@id="getFetchFlights"]/@getFetchFlights');
            $resString = htmlspecialchars_decode($resString);
            $data = $this->http->JsonLog($resString, 1);
        }

        if (!isset($data->data->Trips[0])) {
            $this->savePageToLogs();
            $this->sendNotification('check fetch (wrong data) // ZM');

            if (count($this->http->FindNodes('//script[@id="getFetchFlights"]')) >= 1) {
                $data = $this->http->JsonLog(
                    $this->http->FindSingleNode('(//script[@id="getFetchFlights"]/@getFetchFlights)[last()]'),
                    1
                );
            }

            if (!isset($data->data->Trips[0])) {
                if ($this->attempt === 0) {
                    throw new \CheckRetryNeededException(5, 0);
                }

                throw new \CheckException('bad data', ACCOUNT_ENGINE_ERROR);
            }
        }

        return $this->parseRewardFlightsJson($data, true);
    }

    private function ParseRewardNew($fields = [], $isRetry = false)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("ParseReward [" . $fields['DepDate'] . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);

        switch ($fields['Cabin']) {
            case 'Primary economy':
                $cabin = '&act=1';
                $fareFamily = 'ECO-PREMIUM';
                $cabinPreferenceMain = 'economy';

                break;

            case 'Business':
                $cabin = '&act=2';
                $fareFamily = 'BUSINESS';
                $cabinPreferenceMain = 'premium';

                break;

            case 'First':
                $cabin = '&act=3';
                $fareFamily = 'FIRST';
                $cabinPreferenceMain = 'premium';

                break;

            default:
                $cabin = '';
                $fareFamily = 'ECONOMY';
                $cabinPreferenceMain = 'economy';

                break;
        }

        $urlNew = "https://www.united.com/en/us/fsr/choose-flights?f={$fields['DepCode']}&t={$fields['ArrCode']}&d={$fields['DepDate']}&tt=1&at=1&sc=7{$cabin}&px={$fields['Adults']}%2C0%2C0%2C0%2C0%2C0%2C0%2C0&taxng=1&newHP=True&clm=7&st=bestmatches&tqp=A";

        $auth = $this->getAuth(); // necessary for validRoute
        $this->logger->debug("xhr auth: $auth");

        if (!$this->validAirpot($fields['DepCode'], $auth)) {
            $this->SetWarning("Can’t choose {$fields['DepCode']}. Choose another airport code.");

            return [];
        }

        if (!$this->validAirpot($fields['ArrCode'], $auth)) {
            $this->SetWarning("Can’t choose {$fields['ArrCode']}. Choose another airport code.");

            return [];
        }
        $this->driver->executeScript("localStorage.removeItem('responseData');");
        $this->accessDenied = true; // чтобы исключить ложное mark success на горячем
        $this->driver->get($urlNew);
        $this->runFetch();

        if ($this->http->FindPreg('/(?:You don\'t have permission to access "http:\/\/www.united.com|This site can’t be reached|This site can’t provide a secure connection|This page isn’t working|No internet)/')
            || $this->http->FindSingleNode("//h1[normalize-space()='Access Denied']")
        ) {
            $this->driver->get("https://www.united.com/en/us/book-flight/united-reservations");
            sleep(3);
            $this->savePageToLogs();
            $this->driver->get($urlNew);

//            if ($this->isPuppeteer) {
            $this->runFetch();
//            }

            if ($this->http->FindPreg('/(?:You don\'t have permission to access "http:\/\/www.united.com|This site can’t be reached|This site can’t provide a secure connection|This page isn’t working|No internet)/')
                || $this->http->FindSingleNode("//h1[normalize-space()='Access Denied']")) {
                $this->markProxyAsInvalid();

                $this->logger->error('Access Denied');
                $this->accessDenied = true;

                throw new \CheckRetryNeededException(5, 0);
            }
        }
        if ($this->waitForElement2(WebDriverBy::xpath("//button[contains(@class,'atm-c-drawer__controls__close')]"),
            4)) {
            if ($this->waitForElement2(WebDriverBy::xpath("//*[normalize-space(text())=\"Session expired\"]"), 0)) {
                $this->savePageToLogs();
                $this->accessDenied = true;

                throw new \CheckRetryNeededException(5, 0);
            }

            try {
                $this->driver->executeScript("document.querySelector('div>div[data-focus-guard=\"true\"][tabIndex=\"0\"]').parentElement.remove()");
            } catch (\UnexpectedJavascriptException $e) {
                $this->logger->error($e->getMessage());
            }// click вызывает зависание
            $this->savePageToLogs();
        }

        // TODO remove it
        if ($this->waitForElement2(WebDriverBy::id('closeBtn'), 0)) {
            $this->savePageToLogs();


            if ($this->waitForElement2(WebDriverBy::xpath("//*[normalize-space(text())=\"Session expired\"]"), 0)) {
//                if ($isRetry) {
                $this->accessDenied = true;

                throw new \CheckRetryNeededException(5, 0);
            }

            try {
                $this->driver->executeScript("document.querySelector('div[class*=\"app-components-LoginButton-loginButton__overlayClass\"]').remove()");
            } catch (\UnexpectedJavascriptException $e) {
                $this->logger->error($e->getMessage());
            }// click вызывает зависание
        }

        $warningXPATH = "//div[@role='alert']//div[normalize-space()!=''][not(starts-with(normalize-space(),'Book now with flexibility'))][not(starts-with(normalize-space(),'Sign in'))][not(contains(.,'will be at different airport'))][not(contains(.,'re showing nearby airports in'))][not(contains(.,'ve updated your trip to today'))][not(contains(.,'re showing results for '))][not(contains(.,'FSR3.awardChangeFeeWaiver'))]";
        $this->checkLoadData($warningXPATH);
        $warning = $this->waitForElement2(WebDriverBy::xpath($warningXPATH), 0);

        if ($warning) {
            $msg = $warning->getText();
            $this->logger->error("Message: $msg");

            if (strpos($msg, "We can't process this request. Please restart your search") !== false) {
                // bad route usually (it checked on validAirport), sometimes PE
                if (time() - $this->requestDateTime < 60) {
                    return $this->ParseRewardNew($fields);
                }

                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }
            if (strpos($msg,
                    "You may not be able to book trips or change reservations made with award miles or PlusPoints due to maintenance work between") !== false) {
                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }

            if (strpos($msg,
                    "We're sorry, but united.com was unable to complete your request. Please try later or") !== false
                || strpos($msg,
                    "Sorry, no results have been found. Please enter a different origin location or expand your search area") !== false
            ) {
                $this->accessDenied = true;

                if (time() - $this->requestDateTime < $this->AccountFields['Timeout'] - 20) {
                    // по новой ParseRewardNew - не помогает
                    throw new \CheckRetryNeededException(5, 0);
                }

                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }

            if (stripos($msg, "No flights match the filters you've selected") !== false) {
                $this->SetWarning($msg);

                return [];
            }

            $this->sendNotification('check warning // ZM');
        }

        if ($msg = $this->http->FindSingleNode("//h2[normalize-space()=\"We couldn't find a flight but we can still help\"]")) {
            $this->SetWarning($msg);

            return [];
        }

        if ($msg = $this->http->FindSingleNode("//h2[normalize-space()=\"We couldn't find an exact match\"]")) {
            $msg1 = $this->http->FindSingleNode("//h2[normalize-space()=\"We couldn't find an exact match\"]/following::text()[normalize-space()!=''][1][contains(.,'No flight is available')]",
                null, false, "/^(.+?\.)/");

            if ($msg1) {
                $msg = $msg1;
            }
            $this->SetWarning($msg);

            return [];
        }

        if ($this->http->FindSingleNode("//h1[normalize-space()='Access Denied']")) {
            $this->accessDenied = true;

            throw new \CheckRetryNeededException(5, 0);
        }

        $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");

        if (empty($responseData)) {
                $this->logger->debug("localStorage - responseData");
                sleep(3);
                $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
        }

        $data = $this->http->JsonLog($responseData, 1);

        if (isset($data->data->Trips[0])) {
            return $this->parseRewardFlightsJson($data);
        }

        if (empty($data)) {
            return $this->tryFetch($fields, $auth, $urlNew, $fareFamily, $cabinPreferenceMain);
        }

        if ($this->driver->executeScript("return document.querySelector('input[name=milesOrMoney][value=miles]').checked==false;")
            && !$this->http->FindSingleNode("(//div[contains(@class,'milesContainer')])[1]")
        ) {
            return $this->ParseReward($fields);
        }

        return $this->parseRewardFlightsJson($data);
    }

    private function ParseRewardNew2($fields = [], $isRetry = false)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("ParseReward [" . $fields['DepDate'] . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);

        switch ($fields['Cabin']) {
            case 'Primary economy':
                $cabin = '&act=1';
                $fareFamily = 'ECO-PREMIUM';
                $cabinPreferenceMain = 'economy';

                break;

            case 'Business':
                $cabin = '&act=2';
                $fareFamily = 'BUSINESS';
                $cabinPreferenceMain = 'premium';

                break;

            case 'First':
                $cabin = '&act=3';
                $fareFamily = 'FIRST';
                $cabinPreferenceMain = 'premium';

                break;

            default:
                $cabin = '';
                $fareFamily = 'ECONOMY';
                $cabinPreferenceMain = 'economy';

                break;
        }

        $urlNew = "https://www.united.com/en/us/fsr/choose-flights?f={$fields['DepCode']}&t={$fields['ArrCode']}&d={$fields['DepDate']}&tt=1&at=1&sc=7{$cabin}&px={$fields['Adults']}%2C0%2C0%2C0%2C0%2C0%2C0%2C0&taxng=1&newHP=True&clm=7&st=bestmatches&tqp=A";

        $auth = $this->getAuth(); // necessary for validRoute
        $this->logger->debug("xhr auth: $auth");

        if (!$this->validAirpot($fields['DepCode'], $auth)) {
            $this->SetWarning("Can’t choose {$fields['DepCode']}. Choose another airport code.");

            return [];
        }

        if (!$this->validAirpot($fields['ArrCode'], $auth)) {
            $this->SetWarning("Can’t choose {$fields['ArrCode']}. Choose another airport code.");

            return [];
        }
        $this->driver->executeScript("localStorage.removeItem('responseData');");
        $this->accessDenied = true; // чтобы исключить ложное mark success на горячем
        $this->driver->get($urlNew);
        $this->savePageToLogs();

        try {
            return $this->tryFetch($fields, $auth, $urlNew, $fareFamily, $cabinPreferenceMain);
        } catch (\CheckRetryNeededException $e) {
            $this->driver->get("https://www.united.com/en/us/book-flight/united-reservations");
            $this->waitForElement2(WebDriverBy::xpath("//label[normalize-space()='From']"), 15);
            $this->savePageToLogs();
            return $this->tryFetch($fields, $auth, $urlNew, $fareFamily, $cabinPreferenceMain);
        }
    }

    private function checkLoadData($warningXPATH)
    {
        // TODO optimize waiting
        $this->waitFor(function () use ($warningXPATH) {
            return $this->waitForElement2(WebDriverBy::xpath("(
                    (//button[normalize-space()='Details'])[1]
                    | //h2[normalize-space()=\"We couldn't find a flight but we can still help\"]
                    | //h2[normalize-space()=\"We couldn't find an exact match\"]
                    | //*[normalize-space(text())=\"Session expired\"]
                    | " . $warningXPATH . ")[1]"), 0)
                || $this->waitForElement2(WebDriverBy::id("flightInfoHeader"), 0);
        }, 30);
        $this->savePageToLogs();

        if ($this->waitForElement2(WebDriverBy::xpath("//*[normalize-space(text())=\"Session expired\"]"), 0)) {
            $this->accessDenied = true;

            throw new \CheckRetryNeededException(5, 0);
        }
        $this->waitForElement2(WebDriverBy::xpath("(//b[contains(text(),'Depart on')])[1]"), 5, false);
    }

    private function parseRewardFlightsJson($data, $isFetch = false): array
    {
        $result = [];

        $skippedFlights = 0;

        if (!isset($data->data->Trips[0])) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if (!isset($data->data->Trips[0]->ColumnInformation)) {
            if ($isFetch) {
                $this->SetWarning("No flight is available on the date you've selected.");

                return [];
            }

            if ($this->http->FindSingleNode("(//div[contains(@class,'app-components-Shopping-NoFlights')])[1]")) {
                $this->SetWarning("No flight is available on the date you've selected.");

                return [];
            }

            if ($this->http->FindSingleNode("//span[contains(.,\"We can't process this request. Please restart your search.\")]")) {
                throw new \CheckException("We can't process this request. Please restart your search.",
                    ACCOUNT_PROVIDER_ERROR);
            }

            $this->sendNotification('check restart empty Flights // ZM');

            throw new \CheckRetryNeededException(5, 0);
        }

        $labels = [];
        $fareFamilies = [];

        foreach ($data->data->Trips[0]->ColumnInformation->Columns as $col) {
            if (!isset($col->FareFamilies)) {
                throw new \CheckRetryNeededException(5, 0);
            }

            foreach ($col->FareFamilies as $fareFamily) {
                $labels[$col->DataSourceLabel][$col->Description][] = $fareFamily;
                $fareFamilies[$fareFamily][] = $col->DataSourceLabel;
            }
        }
        // when $fareFamilies is empty  - hidden flights (by call)

        $this->logger->debug('[labels]');
        $this->logger->debug(var_export($labels, true), ['pre' => true]);
        $this->logger->debug('[fareFamilies]');
        $this->logger->debug(var_export($fareFamilies, true), ['pre' => true]);

        foreach ($fareFamilies as $d => $values) {
            if (count($values) > 1) {
                $this->sendNotification('check fareFamilies ' . $d . ' // ZM');
            }
        }

        foreach ($data->data->Trips[0]->Flights as $numFlight => $flight) {
            $this->logger->debug('parse flight #' . $numFlight);
            $segments = [];
            $segments[] = $this->getSegmentInfo($flight);

            foreach ($flight->Connections as $conFlight) {
                $segments[] = $this->getSegmentInfo($conFlight);
            }

            $skippedProd = 0;

            foreach ($flight->Products as $numProd => $product) {
                if (empty($product->Prices)) {
                    $skippedProd++;

                    continue;
                }
                $this->logger->debug('parse product #' . $numProd);
                $offer = $this->convertPrices($product->Prices);

                if (!isset($offer['miles'])) {
                    $this->sendNotification('check awards // ZM');

                    throw new \CheckException('check awards', ACCOUNT_ENGINE_ERROR);
                }

                $fixedSeg = $segments;

                foreach ($segments as $numSegm => $segment) {
                    $segProduct = $segment['products'][$numProd] ?? [];

                    if (empty($segProduct)) {
                        continue 2;
                    }
                    $fixedSeg[$numSegm]['cabin'] = $this->convertToStdCabin($segProduct->Description);
                    $fixedSeg[$numSegm]['meal'] = $segProduct->MealDescription ?? null;
                    $fixedSeg[$numSegm]['fare_class'] = $segProduct->BookingCode;
                    $fixedSeg[$numSegm]['classOfService'] = $segProduct->Description;
                    // full data - больше полная запись не нужна

                    // for debug
                    $fixedSeg[$numSegm]['award_type'] = ($product->AwardType ?? '') . ($segProduct->CabinTypeText ?? '');
                    unset($fixedSeg[$numSegm]['products']);
                }
                $classOfService = null;

                if (!isset($fareFamilies[$product->ProductType])) {
                    if ($product->ProductType === 'MIN-FIRST-SURP-OR-DISP') {
//                        $classOfService = 'First (lowest)'; // no need full name
                        $classOfService = 'First';
                    } elseif ($product->ProductType === 'MIN-BUSINESS-SURP-OR-DISP') {
//                        $classOfService = 'Business (lowest)';// no need full name
                        $classOfService = 'Business';
                    } elseif ($product->ProductType === 'MIN-BUSINESS-SURP-OR-DISP-NOT-MIXED') {
                        $classOfService = 'Business';
                    } else {
                        $this->sendNotification('no fareFamily: ' . $product->ProductType);
                    }
                } else {
                    if (count($fareFamilies[$product->ProductType]) > 1) {
                        $this->sendNotification('> 1 fareFamily: ' . $product->ProductType);
                    }
                    $classOfService = trim($fareFamilies[$product->ProductType][0]);
                }
                $res = [
                    'distance' => null,
                    'num_stops' => count($segments) - 1 + array_sum(array_column($segments, 'num_stops')),
                    'times' => [],
                    'redemptions' => [
                        'miles' => $offer['miles'],
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $offer['currency'],
                        'taxes' => $offer['taxes'],
                        'fees' => $offer['fee'],
                    ],
                    'award_type' => $product->AwardType ?? null,
                    'connections' => $fixedSeg,
                    //                    'classOfService' => $this->convertToClassOfService($product->ProductType ?? null, $product->CabinType ?? null),
                    'classOfService' => $classOfService,
                ];
                $this->logger->debug(var_export($res, true), ['pre' => true]);
                $result[] = $res;
            }

            if ($skippedProd === count($flight->Products)) {
                $skippedFlights++;
            }
        }

        if ($skippedFlights === count($data->data->Trips[0]->Flights)) {
            $this->SetWarning('Not available');
        }
        $this->logger->emergency(count($result));
        $result = array_map('unserialize', array_unique(array_map('serialize', $result)));
        $this->logger->emergency(count($result));

        return $result;
    }

    private function convertToClassOfService(?string $row, ?string $cabin): ?string
    {
        if (!$row) {
            return null;
        }

        if (preg_match("/^(?:MIN|ECO)\-(\w+)\-/", $row, $m)) {
            switch ($m[1]) {
                case "ECONOMY":
                    return "Economy";

                case "PREMIUM":
                    return "Premium Economy";

                case "BUSINESS":
                    return "Business";

                case "FIRST":
                    return "First";
            }
        }
        $this->logger->warning($row);
        $this->sendNotification('new class service');

        return null;
    }

    private function getSegmentInfo($flight): array
    {
        return [
            'num_stops' => count($flight->StopInfos ?? []),
            'departure' => [
                'date' => $flight->DepartDateTime,
                'dateTime' => strtotime($flight->DepartDateTime),
                'airport' => $flight->Origin,
            ],
            'arrival' => [
                'date' => $flight->DestinationDateTime,
                'dateTime' => strtotime($flight->DestinationDateTime),
                'airport' => $flight->Destination,
            ],
            'cabin' => null,
            'meal' => null,
            'fare_class' => null,
            'flight' => [
                //                $flight->OperatingCarrier . $flight->FlightNumber,
                $flight->MarketingCarrier . $flight->FlightNumber, // OriginalFlightNumber
            ],
            'airline' => $flight->MarketingCarrier,
            'operator' => $flight->OperatingCarrier,
            'aircraft' => $flight->EquipmentDisclosures->EquipmentType,
            'times' => [],
            'products' => $flight->Products,
        ];
    }

    private function convertPrices($data)
    {
        $offer = [
            'miles' => null,
            'taxes' => null,
            'currency' => null,
            'fee' => null,
        ];

        foreach ($data as $value) {
            switch ($value->PricingType) {
                case 'Award':
                    $offer['miles'] = $value->Amount;

                    break;

                case 'Tax':
                case 'Taxes':
                    if (isset($offer['taxes'])) {
                        $this->sendNotification('check taxes // ZM');

                        throw new \CheckException('wrong taxes', ACCOUNT_ENGINE_ERROR);
                    }
                    $offer['taxes'] = $value->Amount;
                    $offer['currency'] = $value->Currency;

                    break;

                case 'CloseInFee':
                    if ($value->Amount > 0) {
                        $offer['fee'] = $value->Amount;
                    }

                    break;
            }
        }

        return $offer;
    }

    private function ParseReward($fields = [], ?bool $isRetry = false)
    {
        $this->logger->notice(__METHOD__);
        $this->sendNotification('check old // ZM');
        $this->logger->info("ParseReward [" . $fields['DepDate'] . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);

        switch ($fields['Cabin']) {
            case 'Primary economy':
                $cabin = '&act=1';

                break;

            case 'Business':
                $cabin = '&act=2';

                break;

            case 'First':
                $cabin = '&act=3';

                break;

            default:
                $cabin = '';

                break;
        }

        if (($pos = strpos($fields['Cabin'], ' ')) !== false) {
            $cabinKeyword = ucfirst(strtolower(substr($fields['Cabin'], 0, $pos)));
        } else {
            $cabinKeyword = ucfirst(strtolower($fields['Cabin']));
        }

        $url = "https://www.united.com/ual/en/{$this->countryCode}/flight-search/book-a-flight/results/awd?f={$fields['DepCode']}&t={$fields['ArrCode']}&d={$fields['DepDate']}&sc=7&st=bestmatches&cbm=-1&cbm2=-1&ft=0&cp=0&tt=1&at=1&rm=1{$cabin}&px={$fields['Adults']}&taxng=1&clm=7&idx=1";

        $this->driver->get($url);
        $this->savePageToLogs();

        if ($this->http->FindPreg('/(?:You don\'t have permission to access "http:\/\/www.united.com|This site can’t be reached|This site can’t provide a secure connection|This page isn’t working|No internet)/')
            || $this->http->FindSingleNode("//h1[normalize-space()='Access Denied']")
        ) {
            $this->driver->get("https://www.united.com");
            sleep(3);
            $this->savePageToLogs();
            $this->driver->get($url);
        }

        $this->savePageToLogs();

        if ($this->waitForElement2(WebDriverBy::id('frm-login'), 35)) {
            if ($btn = $this->waitForElement2(WebDriverBy::xpath("//button[contains(@class,'simplemodal') and normalize-space()='Close']"),
                5)
            ) {
                $this->logger->debug("close login modal (by executeScript) ");
                $this->driver->executeScript("document.querySelector('button.simplemodal-close').click();");
            }

            if (!$this->waitForElement2(WebDriverBy::xpath("//button[contains(@class,'simplemodal') and normalize-space()='Close']"),
                    0)
                || $this->waitForElement2(WebDriverBy::id("implemodal-container"), 0)
            ) {
                $this->logger->debug("hide login modal (by executeScript) ");
                $this->driver->executeScript("
                $('#simplemodal-container').toggle();
                $('#simplemodal-overlay').toggle();
                ");
            }
        }

        $this->savePageToLogs();

        if ($this->waitForElement2(WebDriverBy::xpath("//h2[
                contains(.,'We are unable to process your request. Please see the message below for details')
                or contains(.,'We could not process your request. Please see the message below and make revisions')
            ]"), 0)
        ) {
            if ($msg = $this->http->FindSingleNode("//li[contains(normalize-space(),'Either the information you entered is not valid or the airport is not served by United or our partners. Please revise your entry')]")) {
                $this->SetWarning($msg); // wrong airport

                return [];
            }
            $this->logger->error($msg = 'We are unable to process your request');

            throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
        }

        if ($msg = $this->http->FindSingleNode("//text()[contains(.,'Sorry, there seems to be a technical problem. Please try again in a few minutes')]/ancestor::*[1]")) {
            $this->logger->error($msg);

            throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
        }

        $this->waitFor(
            function () {
                return !$this->waitForElement2(WebDriverBy::xpath('//h2[contains(.,"Thank you for choosing United and letting us connect you to the world") or contains(.,"We are unable to process your request. Please see the message below for details")]'),
                    0);
            },
            30
        );
        $this->savePageToLogs();

        if ($msg = $this->waitForElement2(WebDriverBy::xpath('//h2[contains(.,"There are no award flights on the date you selected.") or contains(.,"We are unable to process your request. Please see the message below for details")]'),
            0)) {
            $this->SetWarning($msg->getText());

            return [];
        }

        if ($this->http->FindSingleNode("//button[normalize-space()='Sign in']/ancestor::div[1][contains(.,'better search result')]")) {
            if (!$isRetry) {
                return $this->ParseReward($fields, true);
            }
            $this->logger->error('staying on search form');

            throw new \CheckRetryNeededException(5, 0);
        }
        $depRes = trim($this->http->FindSingleNode("//input[@id='Origin']/@value"));
        $arrRes = trim($this->http->FindSingleNode("//input[@id='Destination']/@value"));
        $this->logger->debug($depRes);
        $this->logger->debug($arrRes);

        if (!empty($depRes) && !empty($arrRes)
            && ($depRes !== $fields['DepCode'] || $arrRes !== $fields['ArrCode'])
        ) {
            if (!$isRetry) {
                return $this->ParseReward($fields, true);
            }
            $this->sendNotification('check wrong route // ZM');

            throw new \CheckRetryNeededException(5, 0);
        }

        // TODO: check it;
        $showCnt = $this->http->FindSingleNode("//span[@id='resultnotext']");
        $allCnt = $this->http->FindSingleNode("//span[@id='resultnotextTlt']");
        $this->logger->debug("Displaying " . $showCnt . " of " . $allCnt);

        if ((int)$showCnt !== (int)$allCnt) {
            if ($a = $this->waitForElement2(WebDriverBy::xpath("//a[@id='a-results-show-all'][contains(.,'Show all flights')]"),
                0)
            ) {
                $a->click();
            }
            $this->waitFor(function () {
                return $this->waitForElement2(WebDriverBy::id("showlessreslttext"), 0);
            }, 10);
            $this->savePageToLogs();
        }

        if (empty($this->http->FindNodes("//a//text()[normalize-space()='Details']"))) {
            throw new \CheckException('something went wrong', ACCOUNT_ENGINE_ERROR);
        }

        if (!empty($allCnt)) {
            $divider = 20;

            if ($allCnt % $divider > 0) {
                $steps = intdiv($allCnt, $divider) + 1;
            } else {
                $steps = intdiv($allCnt, $divider);
            }
            $this->logger->warning('steps:' . $steps);

            $this->logger->debug("open details (by executeScript)");

            for ($cnt = 0; $cnt < $steps; $cnt++) {
                $start = $cnt * $divider;
                $end = min(($cnt + 1) * $divider, $allCnt);
                $this->logger->warning('open:' . $start . '-' . $end);
                $scriptDetail = "
        var elements = document.querySelectorAll('a');
        var links = Array.prototype.filter.call(elements, function(element){
            return RegExp('Details').test(element.textContent);
        });
        for (var i = {$start}; i < {$end}; i++) {
            links[i].click();
        };";

                try {
                    $this->driver->executeScript($scriptDetail);
                } catch (\WebDriverCurlException $e) {
                    $this->logger->error($e->getMessage());

                    try {
                        sleep(1);
                        $this->driver->executeScript("window.stop();");
                        $this->logger->debug('one more time.');
                        $this->driver->executeScript($scriptDetail);
                    } catch (\WebDriverCurlException $e) {
                        $this->logger->error($e->getMessage());

                        throw new \CheckRetryNeededException(5, 0);
                    }
                } catch (\NoSuchDriverException $e) {
                    $this->logger->error("NoSuchDriverException: {$e->getMessage()}");

                    throw new \CheckRetryNeededException(5, 0);
                }
            }
        }

        $this->savePageToLogs();

        return $this->parseRewardFlights($cabinKeyword, $fields['Adults']);
    }

    private function parseRewardFlights(string $cabinKeyword, ?int $adults = 1): array
    {
        $this->logger->notice(__METHOD__);
        $cabins = $this->getCabinFields(false);
        $dateRelative = 0;
        // Apr 15, 2022
        if ($date = $this->http->FindSingleNode("//input[@id='DepartDate']/@value", null, false,
            '/^\w{3} \d{2}, \d{4}$/')) {
            $this->logger->debug("DepartDate: {$date}");
            $dateRelative = strtotime($date);
        }
        // all cabins search
        $conditions = [];
        $this->http->FilterHTML = false;

        foreach ($cabins as $cabin) {
            if (($pos = strpos($cabin, ' ')) !== false) {
                $cabinKeyword = ucfirst(strtolower(substr($cabin, 0, $pos)));
            } else {
                $cabinKeyword = ucfirst(strtolower($cabin));
            }
            $conditions[] = "starts-with(normalize-space(),'Fare for {$cabinKeyword}') or starts-with(normalize-space(),'fare for {$cabinKeyword}')";
        }
        $condition = implode('or ', $conditions);
        $xpath = "//span[$condition]";

        $routes = [];
        $Roots = $this->http->XPath->query($rootPath = "//ul[@id='flight-result-list-revised']/li[.{$xpath}/ancestor::div[2][not(contains(.,'Not available'))]]");

        $this->logger->debug("Found {$Roots->length} routes");
        $this->logger->debug("path: " . $rootPath);
        $formToken = null;
        $segmentsAll = $this->http->XPath->query($rootPath . "/descendant::div[starts-with(@id,'flight-details-')][1]//div[@class='segment']");

        if ($segmentsAll->length === 0) {
            $this->logger->error('details not opened');

            throw new \CheckRetryNeededException(5, 0);
        }

        foreach ($Roots as $numRoot => $root) {
            $this->logger->debug("num route: " . $numRoot);

            $stop = $this->http->FindSingleNode("./descendant::div[2]//span[contains(.,'stop')]/following::text()[normalize-space()!=''][1]",
                $root);

            if ($stop === 'Nonstop') {
                $stop = 0;
            } else {
                $stop = (int)$this->http->FindPreg("/(\d+) stop/", false, $stop);
            }
            $this->logger->debug("root stops: " . $stop);

            $depData = $this->http->FindNodes("./div[contains(@class,'summary')]//div[contains(@class,'depart')]//text()[normalize-space()!=''][not(contains(.,'Departing'))]",
                $root);

            if (count($depData) !== 3) {
                $depData = $this->http->FindNodes("./div[contains(@class,'summary')]//div[contains(@class,'depart')]//text()[normalize-space()!=''][not(contains(.,'Departing'))][./ancestor::*[1][not(self::span)]]",
                    $root);
            }

            if (count($depData) !== 3) {
                $this->logger->error('other format departure');

                throw new \CheckException('other format departure', ACCOUNT_ENGINE_ERROR);
            }
            $this->logger->debug('depData[]: ' . var_export($depData, true));
            $depTime = $depData[1];
            $date = $depData[0];
            $date = $this->normalizeDate($date);
            $this->logger->debug("date: " . $date);

            if (is_string($date)) {
                $depDate = EmailDateHelper::parseDateRelative($date, $dateRelative);
            } else {
                $depDate = $date;
            }
            $this->logger->debug("depTime: " . $depTime);
            $this->logger->debug("depDate: " . $depDate);

            $arrData = $this->http->FindNodes("./div[contains(@class,'summary')]//div[contains(@class,'arrive')]//text()[normalize-space()!=''][not(contains(.,'Arriving'))]",
                $root);

            if (count($arrData) !== 3) {
                $arrData = $this->http->FindNodes("./div[contains(@class,'summary')]//div[contains(@class,'arrive')]//text()[normalize-space()!=''][not(contains(.,'Arriving'))][./ancestor::*[1][not(self::span)]]",
                    $root);
            }

            if (count($arrData) !== 3) {
                $this->logger->error('other format arrive');

                throw new \CheckException('other format arrive', ACCOUNT_ENGINE_ERROR);
            }
            $this->logger->debug('arrData[]: ' . var_export($arrData, true));

            $arrTime = $arrData[1];
            $date = $arrData[0];
            $date = $this->normalizeDate($date);
            $this->logger->debug("date: " . $date);

            if (is_string($date)) {
                $arrDate = EmailDateHelper::parseDateRelative($date, $dateRelative);
            } else {
                $arrDate = $date;
            }
            $this->logger->debug("arrTime: " . $arrTime);
            $this->logger->debug("arrDate: " . $arrDate);

            $routeInfo = [
                'departure' => [
                    'date' => date('Y-m-d H:i', strtotime($depTime, $depDate)),
                    'dateTime' => strtotime($depTime, $depDate),
                    'airport' => $depData[2],
                ],
                'arrival' => [
                    'date' => date('Y-m-d H:i', strtotime($arrTime, $arrDate)),
                    'dateTime' => strtotime($arrTime, $arrDate),
                    'airport' => $arrData[2],
                ],
            ];
            $this->logger->debug(var_export($routeInfo, true), ['pre' => true]);

            $result = ['connections' => []];
            $depFlight = $routeInfo['departure']['dateTime'];
            $memDepFlight = $memArrFlight = [];
            $totalFlight = null;
            $segments = $this->http->XPath->query("./descendant::div[starts-with(@id,'flight-details-')][1]//div[@class='segment']",
                $root);

            $sumStops = 0;

            $prevStopCode = null;

            foreach ($segments as $segNum => $r) {
                $this->logger->debug('segNum: ' . $segNum);
                $segStops = null;
                $destination = $this->http->FindSingleNode(".//div[@class='segment-orig-dest']", $r);
                $depCode = $this->http->FindPreg("/.+\(([A-Z]{3})(?:\s*\-\s*.\w+ Station)?\) to .+/", false,
                    $destination);

                if (!$depCode) {
                    $depCode = $this->http->FindPreg("/.+\(([A-Z]{3})(?:\s*\-\s*.[\w\s]+)?\) to .+/", false,
                        $destination);
                }
                $arrCode = $this->http->FindPreg("/.+\([A-Z]{3}(?:\s*\-\s*.\w+ Station)?\) to .+\(([A-Z]{3})(?:\s*\-\s*.\w+ Station)?\)/",
                    false, $destination);

                if (!$arrCode) {
                    $arrCode = $this->http->FindPreg("/.+\([A-Z]{3}(?:\s*\-\s*.[\w\s]+)?\) to .+\(([A-Z]{3})(?:\s*\-\s*.[\w\s]+)?\)/",
                        false, $destination);
                }
                $stopCode = $this->http->FindSingleNode(".//descendant::text()[contains(normalize-space(),'Stop in')]",
                    $r, false, '/(?:\(([A-Z]{3})\)|\s+([A-Z]{3}))$/');
                $stopCodeOne = $this->http->FindSingleNode("(.//descendant::text()[contains(normalize-space(),'Stop in')])[1]",
                    $r, false, '/(?:\(([A-Z]{3})\)|\s+([A-Z]{3}))$/');

                if (!$stopCodeOne) {
                    $texts = $this->http->FindSingleNode(".", $r);
                    // sometimes xpath not work
                    if ($this->http->FindPreg('/Stop in/', false, $texts)) {
                        $items = $this->http->FindPregAll('/Stop in ([A-Z]{3})\b/', $texts);

                        if (count($items) === 0) {
                            $items = $this->http->FindPregAll('/Stop in [\w\s]+?,[\w\s]*?\(([A-Z]{3})\b\s+-\s+/',
                                $texts);
                        }

                        if (count($items) === 1) {
                            $stopCode = $stopCodeOne = $items[0];
                        } elseif (count($items) > 1) {
                            $stopCode = null;
                            $stopCodeOne = $items[0];
                        } else {
                            $this->sendNotification("check Stop in // ZM");
                        }
                    }
                }

                $this->logger->notice('prevStopCode' . $prevStopCode);
                $this->logger->notice($depCode . '->' . $arrCode);

                if (isset($prevStopCode) && $prevStopCode === $depCode) {
                    $this->logger->notice($result['connections'][$segNum - 1]['num_stops'] ?? '');
                    $this->logger->notice($sumStops);
                }

                if (isset($prevStopCode) && $prevStopCode === $depCode && isset($result['connections'][$segNum - 1]['num_stops'])) {
                    $sumStops -= $result['connections'][$segNum - 1]['num_stops'];
                    unset($result['connections'][$segNum - 1]['num_stops']);
                }

                $prevStopCode = null;

                if (!empty($stopCodeOne) && $stopCodeOne !== $arrCode) {
                    $segStops = $this->http->FindSingleNode(".//table[@class='stops']/preceding-sibling::text()", $r,
                        false, "/(\d+)\s+stop/i");
                    $this->logger->debug("segment stops: " . $segStops);

                    if (!empty($stopCode)) {
                        $prevStopCode = $stopCode;
                    }
                }
                //  12:30 am - 5:05 pm (9h 35m)
                $times = $this->http->FindSingleNode(".//div[@class='segment-times']", $r);

                if (empty($times) && $stop > 0 && $segments->length !== 1) {
                    $this->sendNotification("check parse segment " . $segNum . " // ZM");

                    throw new \CheckException('something new in segments', ACCOUNT_ENGINE_ERROR);
                }

                if (empty($times)) {
                    $depTime = date("H:i", $routeInfo['departure']['dateTime']);
                    $arrTime = date("H:i", $routeInfo['arrival']['dateTime']);
                } else {
                    $depTime = $this->http->FindPreg("/^(\d+:\d+[^\-]*?)\s*\-/", false, $times);
                    $arrTime = $this->http->FindPreg("/^\d+:\d+[^\-]*?\s*\-\s*(\d+:\d+[^\(]*?)\s*\(/", false,
                        $times);
                }
                $this->logger->debug('depTime: ' . $depTime);
                $this->logger->debug('arrTime: ' . $arrTime);

                $departText = $this->http->FindSingleNode("(.//ul[@class='advisories-messages']/li[starts-with(normalize-space(),'Depart')])[1]",
                    $r, false,
                    "/(Depart.*[:;]\s+.+)/"); // [not(./preceding-sibling::li[starts-with(normalize-space(),'Arrive')])]

                if ($departText) {
                    $memPrevDepFlight = $depFlight;
                    $segDepCode = $this->http->FindPreg("/Depart ([A-Z]{3})[:;]\s+.+/", false, $departText);
                    $depart = $this->http->FindPreg("/Depart.*[:;]\s+(.+)/", false, $departText);
                    $depart = $this->normalizeDate($depart);

                    if (is_string($depart)) {
                        $depFlight = EmailDateHelper::parseDateRelative($depart, $depFlight);
                    } else {
                        $depFlight = $depart;
                    }

                    if ($segDepCode !== $depCode) {
                        $memDepFlight[$segDepCode] = $depFlight;
                        $this->logger->debug('memDepFlight: ' . $segDepCode . ' date: ' . $depFlight);
                        $depFlight = $memPrevDepFlight;
                    }
                } else {
                    $checkDepDate = strtotime($depTime, $depFlight);

                    if ($checkDepDate < $depFlight) {
                        $depFlight = strtotime('00:00', strtotime("+1 day", $depFlight));
                    }
                }

                if (isset($memDepFlight[$depCode])) {
                    $this->logger->debug('depFlight from memDepFlight');
                    $depFlight = $memDepFlight[$depCode];
                }

                $arriveText = $this->http->FindSingleNode("(.//ul[@class='advisories-messages']//text()[starts-with(normalize-space(),'Arrive')])[1]",
                    $r, false, "/(Arrive.*[:;]\s+.+)/");

                if ($arriveText) {
                    // далее проверка для какого сегмента дата arrive:
                    // если день прилета указан не для текущего сегмента, а всего перелета (а сегмент - остановка)
                    // см. https://redmine.awardwallet.com/issues/20671#note-20

                    $segArrCode = $this->http->FindPreg("/Arrive ([A-Z]{3})[:;]\s+.+/", false, $arriveText);
                    $arrive = $this->http->FindPreg("/Arrive.*[:;]\s+(.+)/", false, $arriveText);
                    $arrive = $this->normalizeDate($arrive);

                    if (is_string($arrive)) {
                        $arrFlight = EmailDateHelper::parseDateRelative($arrive, $depFlight);
                    } else {
                        $arrFlight = $arrive;
                    }

                    if ($segArrCode !== $arrCode) {
                        $memArrFlight[$segArrCode] = $arrFlight;
                        $this->logger->debug('memArrFlight: ' . $segArrCode . ' date: ' . $arrFlight);
                        $arrFlight = $depFlight;
                    }
                } else {
                    $arrFlight = $depFlight;
                }

                if (isset($memArrFlight[$arrCode])) {
                    $this->logger->debug('arrFlight from memArrFlight');
                    $arrFlight = $memArrFlight[$arrCode];
                }
                $this->logger->debug('depFlight: ' . $depFlight);
                $this->logger->debug('arrFlight: ' . $arrFlight);

                $seg = [
                    'num_stops' => ($segments->length === 1 && $stop > 0 ? $stop : $segStops),
                    'segNum' => $segNum,
                    'departure' => [
                        'date' => date('Y-m-d H:i', strtotime($depTime, $depFlight)),
                        'dateTime' => strtotime($depTime, $depFlight),
                        'airport' => $depCode,
                    ],
                    'arrival' => [
                        'date' => date('Y-m-d H:i', strtotime($arrTime, $arrFlight)),
                        'dateTime' => strtotime($arrTime, $arrFlight),
                        'airport' => $arrCode,
                    ],
                    'cabin' => null,
                    'meal' => null,
                    'fare_class' => null,
                    'flight' => [
                        str_replace(' ', '',
                            $this->http->FindSingleNode(".//div[@class='segment-flight-equipment']/descendant::text()[normalize-space()!=''][1]",
                                $r)),
                    ],
                    'airline' => $this->http->FindSingleNode(".//div[@class='segment-flight-equipment']/descendant::text()[normalize-space()!=''][1]",
                        $r, false,
                        '/^([A-Z\d]{2})\s*\d+$/'),
                    'operator' => $this->http->FindSingleNode(".//div[@class='segment-operator']", $r, false,
                        '/Operated By\s+(.+)/'),
                    'distance' => null,
                    'times' => [
                        'layover' => null,
                    ],
                ];

                if ($overnight = $this->http->FindSingleNode(".//li[contains(.,'Overnight connection')]//text()[starts-with(normalize-space(),'Depart')]",
                    $r, false, "/Depart.*;\s+(.+)/")
                ) {
                    $overnight = $this->normalizeDate($overnight);

                    if (is_string($overnight)) {
                        $depFlight = EmailDateHelper::parseDateRelative($overnight, $depFlight);
                    } else {
                        $depFlight = $overnight;
                    }
                } else {
                    $depFlight = $seg['arrival']['dateTime'];
                }
                $this->logger->debug("for next, depFlight: " . $depFlight);
                $seg['aircraft'] = $this->http->FindSingleNode(".//div[@class='segment-flight-equipment']//text()[starts-with(normalize-space(),'|')]",
                    $r, false, "/\|\s(.+)/");
                $result['connections'][] = $seg;
                $sumStops += $seg['num_stops'] ?? 0;
            }

            if (isset($seg['arrival']['dateTime'])
                && date('Y-m-d', $seg['arrival']['dateTime']) !== date('Y-m-d', $routeInfo['arrival']['dateTime'])
            ) {
                $this->logger->error('wrong arrival - num route:' . $numRoot);

                continue;
            }

            if ($stop !== ($sumStops + $segments->length - 1)) {
                $this->sendNotification('check stops // ZM');

                if ($stop < ($sumStops + $segments->length - 1)) {
                    $stop = ($sumStops + $segments->length - 1);
                }
            }

            $prices = $this->http->XPath->query($pricesPath = ".{$xpath}/ancestor::div[2][not(contains(.,'Not available'))][.//div[contains(@class,'base-price') or contains(@class,'additional-fare')]]",
                $root);
            $this->logger->debug("Found {$prices->length} type of price");
            $this->logger->debug("prices path: " . $pricesPath);

            foreach ($prices as $numPrice => $prRoot) {
                $this->logger->debug("num price: " . $numPrice);
                $colNum = $this->http->XPath->query("./ancestor::div[starts-with(@id,'product')][1]/preceding-sibling::div",
                        $prRoot)->length + 1;
                $award = $this->http->FindSingleNode("./ancestor::div[starts-with(@id,'product')][1]{$xpath}", $prRoot,
                    false, "/(?:Economy|Business|First)\s+(.+)/");

                $tickets = $this->http->FindSingleNode("./ancestor::div[starts-with(@id,'product')][1]//div[contains(@class,'pp-remaining-seats')]",
                    $prRoot, false, "/(\d+)\s+tickets? left/");

                if (isset($tickets) && $tickets < $adults) {
                    $this->logger->debug('skip rout ' . $numRoot . ' with price №' . $numPrice . ' (tickets > ' . $adults . ')');

                    continue;
                }
                $miles = $this->http->FindSingleNode("./ancestor::div[starts-with(@id,'product')][1]//div[contains(@class,'pp-base-price')]",
                    $prRoot, false, "/(.+)\s+miles/");

                if ($numMiles = $this->http->FindPreg("/^(\d[\d.]*)k$/", false, $miles)) {
                    $miles = (int)($numMiles * 1000);
                }
                $fare = $this->http->FindSingleNode("./ancestor::div[starts-with(@id,'product')][1]//div[contains(@class,'pp-additional-fare')]",
                    $prRoot, false, "/\+(.+)/");
                $isFirstPos = true;
                $currency = str_replace(" ", '', $this->http->FindPreg("/^(\D+)/u", false, $fare));

                if (empty($currency)) {
                    $isFirstPos = false;
                    $currency = str_replace(" ", '', $this->http->FindPreg("/(\D+)$/u", false, $fare));
                }
                $fees = $this->http->FindPreg("/(\d[.,\d]+)/u", false, $fare);

                $sum = \AwardWallet\Common\Parser\Util\PriceHelper::cost($fees);
                $headData = [
                    'distance' => null,
                    'num_stops' => $stop,
                    'times' => [],
                    'redemptions' => [
                        'miles' => $miles,
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $this->currencyLoc($currency, $isFirstPos),
                        'taxes' => $sum,
                        'fees' => null,
                    ],
                ];

                $outResult = $result;

                foreach ($result['connections'] as $i => $seg) {
                    $cabinStr = $this->http->FindSingleNode(".//table/descendant::td[{$colNum}]//div[@class='fare-class']/text()[1]",
                        $segments->item($seg['segNum']));

                    if (null === $cabinStr) {
                        $this->logger->warning('skip price. something wrong with cabins');
                        $skippedPrice = true;

                        continue 2;
                    }
                    $outResult['connections'][$i]['cabin'] = $this->convertToStdCabin($cabinStr) ?? $this->inCabin;
                    $outResult['connections'][$i]['meal'] = $this->http->FindSingleNode(".//table/descendant::td[{$colNum}]//div[@class='meal']",
                        $segments->item($seg['segNum']));
                    $outResult['connections'][$i]['fare_class'] = $this->http->FindSingleNode(".//table/descendant::td[{$colNum}]//div[@class='fare-class']/div[1]/text()[1]",
                        $segments->item($seg['segNum']), false, "/\(([A-Z]{1,2})\s*(?:\)|$)/");
                    unset($outResult['connections'][$i]['segNum']);
                }
                $res = array_merge($headData, $outResult);
                $res['tickets'] = $tickets;

                if ($award !== '(mixed cabin)') {
                    $res['award_type'] = $award;
                }

                $this->logger->notice("main numRoute #" . count($routes));
                $this->logger->debug(var_export($res, true), ['pre' => true]);
                $routes[] = $res;
            }
        }

        if (empty($routes) && isset($skippedPrice)) {
            $this->sendNotification('0 routes. problems with cabins // ZM');

            throw new \CheckException('', ACCOUNT_ENGINE_ERROR);
        }

        return $routes;
    }

    private function currencyLoc($s, $isFirstPos)
    {
        if (preg_match("/^[A-Z]{3}$/", $s)) {
            return $s;
        }

        if ($isFirstPos) {
            $sym = [
                '€' => 'EUR',
                'US$' => 'USD',
                'CA$' => 'CAD',
                'AR$' => 'ARS',
                'CO$' => 'COP',
                'NZ$' => 'NZD',
                'TW$' => 'TWD',
                'TT$' => 'TTD',
                'S$' => 'SGD',
                'KD' => 'KWD',
                'RM' => 'VND',
                'TL' => 'TRY',
                'Q' => 'GTQ',
                'QR' => 'QAR',
                '£' => 'GBP',
                '₹' => 'INR',
                'Rs' => 'INR',
                'J$' => 'JMD',
                'BD' => 'BHD',
                'BB$' => 'BBD',
                '¥' => 'CNY',
                '฿' => 'THB',
            ];
        } else {
            $sym = [
                '¥' => "JPY",
            ];
        }

        if (isset($sym[$s])) {
            return $sym[$s];
        }

        return $this->currency($s);
    }

    private function normalizeDate(string $strDate)
    {
        if (preg_match('/^(?<week>\w{3}), (?<date>\w{3} \d+)$/', $strDate, $m)) {
            $dayOfWeekInt = WeekTranslate::number1($m['week'], 'en');
            $strDate = $m['date'];

            return EmailDateHelper::parseDateUsingWeekDay($strDate, $dayOfWeekInt);
        }

        return $strDate;
    }

    private function waitPageLoad(): bool
    {
        $this->logger->notice(__METHOD__);
        $result = $this->waitForElement2(WebDriverBy::xpath(
                "//button[normalize-space()=\"Return to home page\"]" // search results with Session Expired error
                . " | //b[normalize-space()=\"Depart on:\"]" // search results
                . " | //h2[normalize-space()=\"Explore destinations\"]" // main page
                . " | //h2[normalize-space()=\"We couldn't find an exact match\"]" // main page
                . " | //h1[normalize-space()=\"Book a flight\"]" // main page
                . " | //h2/span[normalize-space()=\"Book\"]" // main page
                . " | //span[contains(normalize-space(), \"We're sorry, but united.com was unable to complete your request.\")]" // main page
            ), 15) !== null;

        $this->savePageToLogs($this);

        return $result;
    }

    /**
     * @param int $timeout
     * @param bool $visible
     *
     * @return RemoteWebElement|null
     */
    protected function waitForElement2(WebDriverBy $by, $timeout = 60, $visible = true)
    {
        /** @var RemoteWebElement $element */
        $element = null;
        $start = time();
        $this->waitFor(
            function () use ($by, &$element, $visible) {
                try {
                    $elements = $this->driver->findElements($by);
                } catch (Facebook\WebDriver\Exception\StaleElementReferenceException | StaleElementReferenceException $e) {
                    $this->logger->error("[waitForElement exception on findElements]: " . $e->getMessage(), ['HtmlEncode' => true]);
                    sleep(1);
                    $elements = $this->driver->findElements($by);
                }

                foreach ($elements as $element) {
                    try {
                        if ($visible && !$element->isDisplayed()) {
                            $element = null;
                        }
                    } catch (Facebook\WebDriver\Exception\StaleElementReferenceException | StaleElementReferenceException $e) {
                        $this->logger->error("[waitForElement StaleElementReferenceException on isDisplayed]: " . $e->getMessage(), ['HtmlEncode' => true]);
                        // isDisplayed throws this if element already disappeared from page
                        $element = null;
                    }

                    return !empty($element);
                }

                return false;
            },
            $timeout
        );
        $timeSpent = time() - $start;

        if (!empty($element)) {
            try {
                $this->http->Log("found element {$by->getValue()}, displayed: {$element->isDisplayed()}, text: '" . trim($element->getText()) . "', spent time: $timeSpent", LOG_LEVEL_NOTICE);
            } catch (\Facebook\WebDriver\Exception\StaleElementReferenceException | StaleElementReferenceException $e) {
                // final fallback for element disappearance, getText throws this too
                $this->http->Log("element {$by->getValue()} found and disappeared, spent time: $timeSpent");
                $element = null;
                $timeLeft = $timeout - $timeSpent;

                if ($timeLeft > 0) {
                    $this->http->Log("restarting search, time left: $timeLeft");

                    return $this->waitForElement2($by, $timeLeft, $visible);
                }
            }
        } else {
            $this->http->Log("element {$by->getValue()} not found, spent time: $timeSpent");
        }

        return $element;
    }

    private function runScraper()
    {
        $AUTH = $this->username . ':' . $this->pass;

        $SBR_WEBDRIVER = 'https://' . $this->username . '-' . $AUTH . '@' . $this->host2;

        return RemoteWebDriver::create($SBR_WEBDRIVER);
    }

    private function savePageToLogs($selenium = null)
    {
        $this->logger->notice(__METHOD__);
        if (!isset($selenium)) {
            $selenium = $this;
        }
        $result = $selenium->driver->executeScript('return document.documentElement.innerHTML');

        if (!is_array($result)) {
            if ($selenium->http->saveScreenshots && $selenium->driver instanceof \SeleniumDriver && $selenium->driver->webDriver !== null) {
                try {
                    $selenium->driver->webDriver->takeScreenshot($this->http->LogDir . '/' . sprintf("step%02d-screenshot.png",
                            $this->http->ResponseNumber));
                } catch (
                \Facebook\WebDriver\Exception\UnknownErrorException
                | \Facebook\WebDriver\Exception\WebDriverException
                | \Facebook\WebDriver\Exception\UnknownServerException
                | \WebDriverException
                | \UnknownServerException
                | \ErrorException
                $e
                ) {
                    $this->http->Log("[Failed to save screenshot]: exception - " . (strlen($e->getMessage()) > 300 ? substr($e->getMessage(), 0,
                                297) . '...' : $e->getMessage()), LOG_LEVEL_ERROR);
                }
            }
            // save page to logs
            $this->http->SetBody($result);
            $this->http->SaveResponse();
        } else {
            $this->http->SetBody('');
            $this->logger->error(var_export($result, true), ['pre' => true]);
        }
    }

}
