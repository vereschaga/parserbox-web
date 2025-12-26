<?php

namespace AwardWallet\Engine\aeroplan\RewardAvailability;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use Facebook\WebDriver\Exception\WebDriverException;

class Parser extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;

    private const MEMCACHE_KEY_BROWSER_STAT = 'ra_aeroplan_stateBrow';

    private const CABINS = [
        'eco'        => 'economy',
        'ecoPremium' => 'premiumEconomy',
        'business'   => 'business',
        'first'      => 'firstClass',
    ];
    private const AWARD_TYPES = [
        'FIRSTSTD'   => 'Lowest Reward',
        'PYLOW'      => 'Lowest Reward',
        'OWPYLOW'    => 'Lowest Reward',
        'FIRSTLOW'   => 'Lowest Reward',
        'RTPYLOW'    => 'Lowest Reward',
        'DBFPYLOW'   => 'Lowest Reward',
        'BUSSTD'     => 'Lowest Reward',
        'EXECLOW'    => 'Lowest Reward',
        'PRESTD'     => 'Lowest Reward',
        'DBFEXL'     => 'Lowest Reward',
        'RTNSTEL'    => 'Lowest Reward',
        'OWNSTEL'    => 'Lowest Reward',
        'OWNSTNBMTG' => 'Standard Reward',
        'DBFTANGO'   => 'Standard Reward',
        'STANDARD'   => 'Standard Reward',
        'RTNSTNBMTG' => 'Standard Reward',
        'ECOSTD'     => 'Standard Reward',
        'DBFLAT'     => 'Latitude Reward',
        'RTSTARLT'   => 'Latitude Reward',
        'LATITUDE'   => 'Latitude Reward',
        'ECOCONF'    => 'Latitude Reward',
        'OWSTARLT'   => 'Latitude Reward',
        'RTSTARTP'   => 'Flex Reward',
        'ECOFLEX'    => 'Flex Reward',
        'DBFFLEX'    => 'Flex Reward',
        'FLEX'       => 'Flex Reward',
        'EXECFLEX'   => 'Flexible Reward',
        'RTSTAREF'   => 'Flexible Reward',
        'FIRSTFLEX'  => 'Flexible Reward',
        'BUSFLEX'    => 'Flexible Reward',
        'OWPYFLEX'   => 'Flexible Reward',
        'OWSTAREF'   => 'Flexible Reward',
        'PREFLEX'    => 'Flexible Reward',
        'DBFPYFLEX'  => 'Flexible Reward',
        'OWNBMTP'    => 'Flexible Reward',
        'PYFLEX'     => 'Flexible Reward',
        'DBFEXF'     => 'Flexible Reward',
        'RTPYFLEX'   => 'Flexible Reward',
        'DCOMFORT'   => 'Comfort Reward',
    ];

    public $isRewardAvailability = true;
    private $config;

    private $seleniumDriver;

    public static function getRASearchLinks(): array
    {
        return ['https://www.aircanada.com/us/en/aco/home.html'=>'search page'];
    }

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();
        $this->http->setHttp2(true);
        $this->UseSelenium();
        $this->keepCookies(false); // исключить ненужные сохранения => исключить трейсы/задержки
        $this->debugMode = isset($this->AccountFields['DebugState']) && $this->AccountFields['DebugState'];

        if (!$this->debugMode) {
            $randomBrowserInt = random_int(0, 2);
        } else {
            $randomBrowserInt = 3;
        }

        switch ($randomBrowserInt) {
            case 0:
                $this->useFirefoxPlaywright(\SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_100);
                $this->config = 'ff_pw_101';

                break;

            case 1:
                $this->useFirefoxPlaywright();
                $this->config = 'ff_pw_101';

                break;

            case 2:
                $this->useFirefoxPlaywright(\SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_102);
                $this->config = 'ff_pw_101';

                break;

            case 3:
                $this->useFirefoxPlaywright(\SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_100);
                $this->config = 'test';

                break;
        }

        $this->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);

        $resolutions = [
            [2560, 1600],
        ];
        $chosenResolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($chosenResolution);

        $this->seleniumOptions->addHideSeleniumExtension = false;
//        $this->seleniumOptions->addPuppeteerStealthExtension = false;

        $this->usePacFile(false);
//        $this->useCache();

        $userAgents = [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_6) AppleWebKit/623.23 (KHTML, like Gecko) Version/13.3.27 Safari/623.23',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_0) AppleWebKit/618.34.2 (KHTML, like Gecko) Version/16.1.64 Safari/618.34.2',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 12.3) AppleWebKit/618.12 (KHTML, like Gecko) Version/17.3.91 Safari/618.12',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 13) AppleWebKit/619.26.5 (KHTML, like Gecko) Version/16.5.10 Safari/619.26.5',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 13.2) AppleWebKit/619.31 (KHTML, like Gecko) Version/16.2 Safari/619.31',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11) AppleWebKit/537.36 (KHTML, like Gecko) Version/14.1.56 Safari/622.2',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/537.36 (KHTML, like Gecko) Version/13.4 Safari/622.4',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.2) AppleWebKit/620.4.8 (KHTML, like Gecko) Version/17.4 Safari/620.4.8',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 11) AppleWebKit/620.24.15 (KHTML, like Gecko) Version/17.0.46 Safari/620.24.15',
            'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_4_11; fi-fi) AppleWebKit/525.13 (KHTML, like Gecko) Version/3.1 Safari/525.13',
            'Mozilla/5.0 (Macintosh; PPC Mac OS X 10_10_8 rv:2.0; fur-IT) AppleWebKit/532.27.1 (KHTML, like Gecko) Version/5.0.4 Safari/532.27.1',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/569.36 (KHTML, like Gecko) Safari/12.0 Safari/605.1.15',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 12.1) AppleWebKit/620.17.1 (KHTML, like Gecko) Version/16.3 Safari/620.17.1',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14.1) AppleWebKit/618.19 (KHTML, like Gecko) Version/17.0.73 Safari/618.19',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_5) AppleWebKit/616.35 (KHTML, like Gecko) Version/12.6 Safari/616.35',
        ];

        $this->http->setUserAgent($userAgents[array_rand($userAgents)]);

//        switch (rand(0, 2)) {
//            case 0:
//                $countries = ['us', 'ca'];
//                $proxyRegion = $countries[array_rand($countries)];
//
//                $this->setProxyNetNut(null, $proxyRegion);
//
//                break;

//            case 1:
//                //us, gb, fr, de, au, il, kr, fi, es
//            $countries = ['us', 'gb'];
//            $proxyRegion = $countries[array_rand($countries)];
//
//            $this->setProxyBrightData(null, 'static', $proxyRegion);
//                $this->setProxyDOP(['nyc1', 'nyc2', 'nyc3', 'lon1', 'fra1']);
//
//                break;
//            $this->setProxyDOP();

        $countries = ['us', 'ca', 'de', 'uk', 'es', 'mx'];
        $proxyRegion = $countries[array_rand($countries)];
        $this->setProxyGoProxies(null, $proxyRegion);
        //  }

//        $this->setProxyDOP(['nyc1', 'nyc2', 'nyc3', 'lon1', 'fra1']);

//        $this->setProxyDOP();
//        $this->setProxyDOP(['lon1', 'sfo1', 'sfo3', 'tor1', 'sfo3']);

        // горячие нельзя. по скорости в разы шустрее, но много вранья в sessionStorage, хоть он и чистится, откуда-то дотягивает
        // проще отключить. полминуты сбор - норм

        $this->http->saveScreenshots = true;
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
        $arrCurrencies = ['CAD'];

        return [
            'supportedCurrencies'      => $arrCurrencies,
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'CAD',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));
        $fields['Cabin'] = $this->getCabinFields(false)[$fields['Cabin']];
        $this->logger->info("Current UA: {$this->http->userAgent}. Header UA: {$this->http->getDefaultHeader('User-Agent')}");

        /** @var \SeleniumDriver seleniumDriver */
        $seleniumDriver = $this->http->driver;
        $this->seleniumDriver = $seleniumDriver;

        try {
            $isHot = (strpos($this->http->currentUrl(), '.aircanada.com') !== false);

            if (!$this->validRoute($fields)) {
                $this->ErrorCode = ACCOUNT_WARNING;

                if ($isHot) {
                    $this->logger->notice("Data ok. Save session");
                }

                return ['routes' => []];
            }

            if ($fields['Currencies'][0] !== 'CAD') {
                $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
                $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
            }

            $fields['DepDate'] = date("Y-m-d", $fields['DepDate']);

            $result = $this->ParseReward($fields);
        } catch (\WebDriverCurlException | \WebDriverException | \Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\ErrorException $e) {
            if (strpos($e->getMessage(), 'Array to string conversion') !== false) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());

            throw $e;
        } finally {
            if (!empty($result) || $this->ErrorCode === ACCOUNT_WARNING) {
                $this->logger->notice("Data ok. Save session");

                $this->loggerStateBrowser($result);
            }

            try {
                $this->http->cleanup();
            } catch (\ErrorException $e) {
                $this->logger->error($e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }
        }

        return ['routes' => $result];
    }

    private function getCabinFields($onlyKeys = true): array
    {
        $cabins = [
            'economy'        => 'Economy Class',
            'premiumEconomy' => 'Premium Economy',
            'firstClass'     => 'First Class',
            'business'       => 'Business Class',
        ];

        if ($onlyKeys) {
            return array_keys($cabins);
        }

        return $cabins;
    }

    private function ParseReward($fields = [], $isRetry = false)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("ParseReward [" . $fields['DepDate'] . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);

        $this->driver->executeScript("Object.defineProperty(navigator, 'webdriver', {get: () => undefined})");

        try {
            try {
                $this->http->GetURL('https://www.aircanada.com');
            } catch (\ErrorException $e) {
                $this->logger->error('ErrorException: ' . $e->getMessage());
                $this->logger->error($e->getTraceAsString(), ['HtmlEncode' => true]);

                $this->loggerStateBrowser();

                throw new \CheckRetryNeededException(5, 0);
            } catch (\Facebook\WebDriver\Exception\UnknownErrorException | \UnknownErrorException $e) {
                $this->logger->error('UnknownErrorException: ' . $e->getMessage());

                $this->loggerStateBrowser();

                throw new \CheckRetryNeededException(5, 0);
            } catch (\Facebook\WebDriver\Exception\WebDriverException | \WebDriverException $e) {
                $this->logger->error('WebDriverException: ' . $e->getMessage());

                $this->loggerStateBrowser();

                throw new \CheckRetryNeededException(5, 0);
            }

            if ($this->waitForElement(\WebDriverBy::xpath('//h1[contains(text(), "Access Denied")]'), 0)) {
//                $this->markProxyAsInvalid();
                $this->loggerStateBrowser();

                throw new \CheckRetryNeededException(5, 0);
            }

            if (!$this->waitForElement(\WebDriverBy::xpath('//div[@id="flightsOriginLocationbkmgLocationContainer"]'), 10)) {
//                $this->markProxyAsInvalid();

                $this->http->GetURL('https://www.aircanada.com');

                if (!$this->waitForElement(\WebDriverBy::xpath('//div[@id="flightsOriginLocationbkmgLocationContainer"]'), 10)) {
                    $this->loggerStateBrowser();

                    $this->saveResponse();

                    throw new \CheckRetryNeededException(4, 5);
                }
            }

//            $this->waitForElement(\WebDriverBy::xpath("
//                //button[@id='onetrust-accept-btn-handler']
//                | //p[contains(.,'Book with Aeroplan points')]"),
//                0);

            try {
                if ($cookies = $this->waitForElement(\WebDriverBy::xpath("//button[@id='onetrust-accept-btn-handler']"), 5)) {
                    $cookies->click();
                }

//                if ($point = $this->waitForElement(\WebDriverBy::xpath("//span[@id='bkmg-desktop_searchTypeToggle']"), 0)) {
//                    $point->click();
//                }

//                $this->clickSearchForm();
            } catch (WebDriverException $e) {
                $this->logger->error($e->getMessage());
                $this->http->SaveResponse();
            }
            $this->saveResponse();

            /*
                        if (!$isRetry) {
                            try {
                                $this->http->GetURL('https://www.aircanada.com/');
                            } catch (\ErrorException $e) {
                                $this->logger->error('ErrorException: ' . $e->getMessage());
                                $this->logger->error($e->getTraceAsString(), ['HtmlEncode' => true]);

                                throw new \CheckRetryNeededException(5, 0);
                            }

                            if ($way = $this->waitForElement(\WebDriverBy::xpath('//label[@for="bkmgFlights_tripTypeSelector_O"]'),
                                            10)) {
                                $way->click();
                                $this->someSleep();
                            }

                            if (!$way && is_null($this->http->FindSingleNode("(//img[@alt='Air Canada' or @class='ngx-ac-logo-image'])[1]"))) {
                                $this->saveResponse();

                                throw new \CheckRetryNeededException(5, 0);
                            }

                            $inputs = [
                                'points' => $this->waitForElement(\WebDriverBy::xpath('//label[@for="bkmgFlights_searchTypeToggle"]'),
                                    0),
                                'passengers' => $this->waitForElement(\WebDriverBy::xpath('//div[@id="bkmgFlights_selectTravelersMainContainer"]'),
                                    0),
                                'date' => $this->waitForElement(\WebDriverBy::xpath('//input[@id="bkmgFlights_travelDates_1"]'), 0),
                                'des'  => $this->waitForElement(\WebDriverBy::xpath('//input[@id="bkmgFlights_destination_trip_1"]'),
                                    0),
                                'orig' => $this->waitForElement(\WebDriverBy::xpath('//input[@id="bkmgFlights_origin_trip_1"]'), 0),
                            ];

                            $cnt = random_int(3, count($inputs));

                            $this->logger->info('Inputs count to click: ' . $cnt);

                            while ($cnt) {
                                shuffle($inputs);
                                $input = array_shift($inputs);

                                if ($input) {
                                    $input->click();
                                    $this->someSleep();
                                }
                                $cnt--;
                            }
                        }
                        $this->driver->executeScript("localStorage.removeItem('responseDataCal');");
                        $this->driver->executeScript("localStorage.removeItem('responseData');");
                        $this->driver->executeScript("sessionStorage.removeItem('redemption-searchParameters');");
                        $this->driver->executeScript("sessionStorage.removeItem('redemption-airBounds');");

                        sleep(2);
            */
            try {
                $this->loadPage($fields);
            } catch (\ErrorException $e) {
                $this->logger->error($e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }
            sleep(2);

            try {
                $responseDataCal = $this->driver->executeScript("return localStorage.getItem('responseDataCal');");
                $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
                $responseDataNew = $this->driver->executeScript("return sessionStorage.getItem('redemption-airBounds');");

//                if ($this->debugMode) {
//                    $this->driver->executeScript(
//                        'let script = document.createElement("script");
//                        let id = "airBounds";
//                        script.id = id;
//                        script.setAttribute(id, JSON.stringify(sessionStorage["redemption-airBounds"]));
//                        document.querySelector("body").append(script);');
//
//                    $this->saveResponse();
//
//                    $responseDataNewString = $this->http->FindSingleNode("//script[@id='airBounds']/@airbounds");
//                    $responseDataNew = $this->http->JsonLog($responseDataNewString, 1, true);
//                }

                if ($responseDataNew === 'null') {
                    $responseDataNew = null;
                }
            } catch (\Facebook\WebDriver\Exception\WebDriverCurlException | \WebDriverCurlException $e) {
                $responseData = $responseDataNew = $responseDataCal = null;
            }

            if (is_array($responseData)) {
                $responseData = json_encode($responseData);
            }

            if (is_array($responseDataCal)) {
                $responseDataCal = json_encode($responseDataCal);
            }

            if (!empty($responseDataCal)) {
                $this->logger->error('[from calendar]');
                $this->http->JsonLog($responseDataCal, 1, true);
            }

            if (is_array($responseDataNew)) {
                $responseDataNew = json_encode($responseDataNew);
            }

            if (!empty($responseDataNew)) {
                $this->logger->error('[from fetch]');
                $this->http->JsonLog($responseData, 1, true);

                $this->logger->error('[from sessionStorage]');

                if (strpos($responseData, 'session timed out or not found') !== false) {
                    $this->http->JsonLog($responseData, 1, true);

                    throw new \CheckRetryNeededException(5, 0);
                }
                $responseNew = $this->http->JsonLog($responseDataNew, 1, true);
            } else {
                $responseNew = null;
            }

            try {
                $requestDataNew = $this->driver->executeScript("return sessionStorage.getItem('redemption-searchParameters');");
            } catch (\Facebook\WebDriver\Exception\WebDriverCurlException | \WebDriverCurlException $e) {
                $this->logger->error($e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }

            if ($requestDataNew === 'null') {
                $requestDataNew = null;
            }

            if ($requestDataNew) {
                if (is_array($requestDataNew)) {
                    $requestDataNew = json_encode($requestDataNew);
                }
                $jsonData = $this->http->JsonLog($requestDataNew, 1, true);

                if (isset($jsonData['currentSearch']['bounds'][0])) {
                    $dateCheck = substr($jsonData['currentSearch']['bounds'][0]['departureDate'], 0, 10);
                    $depCheck = $jsonData['currentSearch']['bounds'][0]['from'];
                    $arrCheck = $jsonData['currentSearch']['bounds'][0]['to'];
                    $this->logger->error($dateCheck);
                    $this->logger->error($depCheck);
                    $this->logger->error($arrCheck);

                    if ($dateCheck !== $fields['DepDate'] || $depCheck !== $fields['DepCode'] || $arrCheck !== $fields['ArrCode']) {
                        $this->logger->error('wrong data. retry');

                        if (!$isRetry) {
                            return $this->ParseReward($fields, true);
                        }

                        throw new \CheckRetryNeededException(5, 0);
                    }
                }
            }

            if (isset($responseNew['entries'][0]['airBoundGroups'])) {
                $airBounds = $responseNew['entries'][0]['airBoundGroups'];

                if (is_array($airBounds) && empty($airBounds)) {
                    // '{"currentEntryIndex":0,"entries":{"0":{"airBoundGroups":[],"selection":null,"isPending":false,"isFailure":true}}}'
                    $this->SetWarning('There are no flights available on the date you selected');

                    return [];
                }
            } else {
                $airBounds = null;
            }

            $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");

            if (empty($responseData) && empty($airBounds)) {
                sleep(3);
                $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");

                if (empty($responseData)) {
                    sleep(2);
                    $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
                }
            }
            $found = (int) $this->http->FindSingleNode("//span[normalize-space()='Flight results:']/following-sibling::span[1]", null, false, "/(\d+)\s*flights found/");

            if (empty($responseData) && empty($airBounds)
                && ($this->http->FindSingleNode("//h1[starts-with(normalize-space(),'Searching for flights')]")
                    || !empty($found))
            ) {
                $this->http->SaveResponse();

                throw new \CheckRetryNeededException(5, 0);
            }

            if ($this->http->FindSingleNode("//div[normalize-space()='ERR_TUNNEL_CONNECTION_FAILED']")) {
                $this->sendNotification('can’t be reached // ZM');

                throw new \CheckRetryNeededException(5, 0);
            }

            if (empty($responseData) && empty($airBounds) && is_null($this->http->FindSingleNode("(//img[@alt='Air Canada' or @class='ngx-ac-logo-image'])[1]"))) {
                $this->saveResponse();

                throw new \CheckRetryNeededException(5, 0);
            }
        } catch (\ScriptTimeoutException | \TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (\NoSuchElementException | \UnknownServerException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        $this->saveResponse();

        if ($this->http->FindSingleNode('//p[contains(.,"We can\'t seem to find flights that match your search criteria. Here are a few options:")][./preceding::text()[normalize-space()!=""][1][normalize-space()="Change flight"]]')) {
            $this->SetWarning("We can't seem to find flights that match your search criteria.");

            return [];
        }

        if ($msg = $this->http->FindSingleNode('//h1[normalize-space()="No flights available"]/ancestor::div[1]/following-sibling::div[1]')) {
            $this->SetWarning($msg);

            return [];
        }

        if ($msg = $this->http->FindSingleNode('//strong[contains(.,"Air Canada\'s website is not available right now.")]/ancestor::div[1]')) {
            $this->logger->error($msg);

            if ($this->attempt === 0) {
                throw new \CheckRetryNeededException(5, 0);
            }

            throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
        }

        if (isset($responseData) || isset($airBounds)) {
            if (is_array($responseData)) {
                $responseData = json_encode($responseData);
            }

            return $this->parseRewardFlightsJson($fields, $responseData, $airBounds);
        }

        if ($this->attempt < 2) {
            throw new \CheckRetryNeededException(3, 0);
        }
        $this->logger->notice("go to parse from html");

        try {
            $result = $this->parseRewardFlights($fields['Cabin']);
        } catch (\WebDriverException | WebDriverException $e) {
            $this->logger->error($e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        return $result;
    }

    private function loadPage($fields)
    {
        $this->logger->notice(__METHOD__);
        $url = "https://www.aircanada.com/aeroplan/redeem/availability/outbound?lang=en-CA&tripType=O&ADT={$fields['Adults']}&YTH=0&CHD=0&INF=0&INS=0&org0={$fields['DepCode']}&dest0={$fields['ArrCode']}&departureDate0={$fields['DepDate']}&marketCode=INT";

        try {
            $this->http->GetURL($url);
        } catch (\Facebook\WebDriver\Exception\WebDriverCurlException | \WebDriverCurlException
        | \Facebook\WebDriver\Exception\InvalidSessionIdException | \Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error($e->getMessage());

            $this->loggerStateBrowser();

            throw new \CheckRetryNeededException(5, 0);
        }

        /*        $this->logger->info('[run fetch]');
                $this->driver->executeScript(/** @lang JavaScript * / //TODO
                    '
                    const constantMock = window.fetch;
                    window.fetch = function() {
                        console.log(arguments);
                        return new Promise((resolve, reject) => {
                            constantMock.apply(this, arguments)
                                .then((response) => {
                                    if(response.url.indexOf("/air-bounds") > -1) {
                                        response
                                         .clone()
                                         .json()
                                         .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
                                    }
                                    if(response.url.indexOf("/air-calendars") > -1) {
                                        response
                                         .clone()
                                         .json()
                                         .then(body => localStorage.setItem("responseDataCal", JSON.stringify(body)));
                                    }
                                    resolve(response);
                                })
                                .catch((error) => {
                                    reject(response);
                                })
                        });
                    }
                    ');*/
        // retries
        if ($this->http->FindPreg('/(?:page isn’t working|There is no Internet connection|This site can’t be reached)/ims')
            || $this->http->FindSingleNode("//h1[normalize-space()='Access Denied']")
        ) {
            $this->http->removeCookies();
            $this->http->GetURL($url);

            if ($this->http->FindPreg('/(?:page isn’t working|There is no Internet connection|This site can’t be reached)/ims')
                || $this->http->FindSingleNode("//h1[normalize-space()='Access Denied']")) {
                $this->markProxyAsInvalid();
                $this->loggerStateBrowser();

                throw new \CheckRetryNeededException(5, 0);
            }
        }

        if ($this->waitForElement(\WebDriverBy::xpath('//span[contains(., "An error occurred and we were not able to complete the process")]'), 10)) {
            $this->http->removeCookies();
            $this->http->GetURL($url);
        }

        $waitLoadXpath = "
                (//span[normalize-space() = 'Sort and filter'] 
                | //h1[normalize-space()='No flights available'])[1] 
                | (//h1[normalize-space()='Departing flight'])[1]
                | //h1[contains(.,'Are your travel dates flexible')]
                | //strong[contains(.,\"Air Canada's website is not available right now.\")]/ancestor::div[1]
                ";

        try {
            $res = $this->waitForElement(\WebDriverBy::xpath($waitLoadXpath), 30, false);

            if (!$res) {
                $this->saveResponse(); // lock

                $this->loggerStateBrowser();

                throw new \CheckRetryNeededException(5, 0);
                /*$this->waitFor(function () {
                    return !$this->waitForElement(\WebDriverBy::xpath('//h1[contains(text(),"Searching for flights from")]'),
                        0);
                }, 20);*/
            }
        } catch (\StaleElementReferenceException $e) {
            $this->sendNotification('check StaleElementReferenceException // ZM');
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage());
            $this->waitForElement(\WebDriverBy::xpath("(//span[normalize-space() = 'Sort and filter'] | //h1[normalize-space()='No flights available'])[1]"), 40, false);
        }
        $this->saveResponse();
    }

    private function parseRewardFlightsJson(array $fields, ?string $data, $airBounds = null): array
    {
        $cabin = $fields['Cabin'];
        $this->logger->notice(__METHOD__);
        $oldFormat = false;

        if (!isset($airBounds)) {
            $oldFormat = true;
            $jsonData = $this->http->JsonLog($data, 1, true);

            if (isset($jsonData['errors'])) {
                if ($jsonData['errors'][0]['title'] === 'NO FLIGHTS FOUND') {
                    $this->ErrorCode = ACCOUNT_WARNING;
                    $this->ErrorMessage = 'No flights found';

                    return [];
                }

                if ($jsonData['errors'][0]['title'] === 'OFFER CREATION FAILURE') {
                    $this->logger->error($this->ErrorMessage = 'No flights available');
                    $this->ErrorCode = ACCOUNT_WARNING;

                    return [];
                }
                $this->sendNotification("check response error // ZM");

                throw new \CheckException($jsonData['errors'][0]['title'], ACCOUNT_ENGINE_ERROR);
            }

            if (!isset($jsonData['data']['airBoundGroups'])) {
                if (isset($jsonData['message']) && $jsonData['message'] === 'Endpoint request timed out') {
                    $this->sendNotification("check retry // ZM");

                    throw new \CheckRetryNeededException(5, 0);
                }
                $this->sendNotification("check format json // ZM");

                throw new \CheckException('new format', ACCOUNT_ENGINE_ERROR);
            }
            $airBounds = $jsonData['data']['airBoundGroups'];
        }
        $this->logger->debug("Found " . count($airBounds) . " routes");

        $routes = [];
        $listClassOfService = [
            'business'   => 'Business',
            'ecoPremium' => 'Premium Economy',
            'eco'        => 'Economy',
            'first'      => 'First',
        ];

        foreach ($airBounds as $numRoot => $route) {
            $this->logger->debug("num route: " . $numRoot);

            if ($oldFormat) {
                $routeInfo = $this->parseDetailsJson($route['boundDetails'], $jsonData['dictionaries']);
            } else {
                $routeInfo = $this->parseDetailsJson2($route['boundDetails']);
            }

            if (empty($routeInfo)) {
                $this->logger->error("skip empty route");

                continue;
            }
            $stop = count($routeInfo['segments']) - 1;
            $this->logger->debug("cntAwards:" . count($route['airBounds']));

            foreach ($route['airBounds'] as $numAward => $airBound) {
                $this->logger->debug("numAward:" . $numAward);

                if ($oldFormat) {
                    $awardHead = $airBound['fareFamilyCode'];
                } else {
                    $awardHead = $airBound['fareFamily']['cabin'];
                }

                foreach ($airBound['prices']['unitPrices'] as $unit) {
                    foreach ($unit['travelerIds'] as $travelerId) {
                        if (strpos($travelerId, 'ADT') === 0) {
                            $sum = $unit['prices'][0]['total'] / 100;
                            $currency = $unit['prices'][0]['currencyCode'];
                            $miles = $unit['milesConversion']['convertedMiles']['base'];

                            break 2;
                        }
                    }
                }

                if (!isset($sum, $currency, $miles)) {
                    $this->sendNotification("can't find price for ADT // ZM");

                    continue;
                }
                $details = [];

                foreach ($airBound['availabilityDetails'] as $availabilityDetail) {
                    $details[$availabilityDetail['flightId']] = [
                        'cabin'        => $availabilityDetail['cabin'],
                        'bookingClass' => $availabilityDetail['bookingClass'],
                        'tickets'      => $availabilityDetail['quota'] ?? null,
                    ];
                }
                $classOfService = null;

                if ($oldFormat && isset($jsonData['dictionaries']['fareFamilyWithServices'][$awardHead])) {
                    if (array_key_exists($jsonData['dictionaries']['fareFamilyWithServices'][$awardHead]['cabin'],
                        $listClassOfService)) {
                        $classOfService = $listClassOfService[$jsonData['dictionaries']['fareFamilyWithServices'][$awardHead]['cabin']];
                    } else {
                        $this->sendNotification('check fareFamilyWithServices ' . $awardHead . ' // ZM');
                    }
                } else {
                    if (array_key_exists($awardHead, $listClassOfService)) {
                        $classOfService = $listClassOfService[$awardHead];
                    } else {
                        $this->sendNotification('check fareFamilyWithServices ' . $airBound['fareFamilyCode'] . '(' . $awardHead . ') // ZM');
                    }
                }

                $result = ['connections' => []];
                $headData = [
                    'distance'    => null,
                    //                    'num_stops'   => $stop,
                    'redemptions' => [
                        'miles'   => $miles,
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $this->currencyLoc($currency),
                        'taxes'    => $sum,
                        'fees'     => null,
                    ],
                    'classOfService' => $classOfService,
                ];

                $tickets = 100;

                $stop = -1;

                foreach ($routeInfo['segments'] as $segNum => $s) {
                    $stop++;
                    $detail = $details[$s['flightId']];

                    if ($oldFormat) {
                        $dictionary = $jsonData['dictionaries']['flight'][$s['flightId']] ?? null;
                    } else {
                        $dictionary['stops'] = $s['stops'];
                    }
                    $seg = [
                        'departure' => [
                            'date'     => date('Y-m-d H:i', $s['departure']['datetime']),
                            'dateTime' => $s['departure']['datetime'],
                            'airport'  => $s['departure']['code'],
                        ],
                        'arrival' => [
                            'date'     => date('Y-m-d H:i', $s['arrival']['datetime']),
                            'dateTime' => $s['arrival']['datetime'],
                            'airport'  => $s['arrival']['code'],
                        ],
                        'meal'           => null,
                        'cabin'          => self::CABINS[$detail['cabin']] ?? $this->tryGuessCabin($detail['cabin']),
                        'fare_class'     => $detail['bookingClass'],
                        'flight'         => [$s['flight']],
                        'airline'        => $s['airline'],
                        'operator'       => $s['operator'],
                        'distance'       => null,
                        'aircraft'       => $s['aircraft'],
                        'tickets'        => $detail['tickets'],
                        'num_stops'      => count($dictionary['stops'] ?? []),
                        'classOfService' => $listClassOfService[$detail['cabin']],
                    ];
                    $stop += $seg['num_stops'];

                    if (isset($detail['tickets'])) {
                        $tickets = min($detail['tickets'], $tickets);
                    }
                    $result['connections'][] = $seg;
                }
                $res = array_merge($headData, $result);
                $res['award_type'] = self::AWARD_TYPES[$awardHead] ?? $awardHead;
                $res['num_stops'] = $stop;

                if (isset($tickets) && $tickets !== 100) {
                    $res['tickets'] = $tickets;
                }

                $this->logger->debug(var_export($res, true), ['pre' => true]);
                $routes[] = $res;
            }
        }

        return $routes;
    }

    private function parseRewardFlights(string $cabin): array
    {
        $this->logger->notice(__METHOD__);

        if ($signInClose = $this->waitForElement(\WebDriverBy::xpath("//h2[contains(.,'Sign in to view the best fares!')]/ancestor::div[1]/following-sibling::span[1][@aria-label='Close']"), 0)) {
            $signInClose->click();
            $this->saveResponse();
        }
        $cabinKeywords = array_map("strtolower", array_values($this->getCabinFields(false)));
        $dataAnalyticsVal = implode(' or ', array_map(function ($s) {
            return "contains(@data-analytics-val,'{$s}')";
        }, $cabinKeywords));

//        $xpath = "//*[@analytictag='search results'][./following-sibling::div/*[contains(@data-analytics-val,'{$cabinKeyword}')][string-length(normalize-space())>1]]";
        $xpath = "//*[@analytictag='search results'][./following-sibling::div/*[{$dataAnalyticsVal}][string-length(normalize-space())>1]]";
        $routes = [];
        $Roots = $this->http->XPath->query($xpath);

        $this->logger->debug("Found {$Roots->length} routes");
        $this->logger->debug("path: " . $xpath);
        $formToken = null;
        $routesDetails = [];

        // at first details, then price (otherwise DOM-broke)
        $counter = 0;
        $limit = 0;

        if ($loginPopup = $this->waitForElement(\WebDriverBy::xpath("//span[contains(@class,'icon-close') and @aria-label='Close']"), 0)) {
            $loginPopup->click();
            $this->saveResponse();
        }

        foreach ($Roots as $numRoot => $root) {// $root - don't use, it's broke
            $this->logger->debug("num route: " . $numRoot);
            $position = $numRoot + 1;

            /* unnecessary. all routes must be collected
            $stop = $this->http->FindSingleNode("(" . $xpath . ")[{$position}]//kilo-flight-duration-pres", null, false,
                "/^(.+?)\s*\|/");
            if ($stop === 'Non-stop') {
                $stop = 0;
            } else {
                $stop = (int)$this->http->FindPreg("/(\d+) stops?/", false, $stop);
            }
            if ($stop > $maxStops) {
                $this->logger->debug('skip route ' . $numRoot . ' (stops > ' . $maxStops . ')');
                continue;
            }
            $depCode = $this->http->FindSingleNode("(" . $xpath . ")[{$position}]//span[contains(@class,'departure-code')]",
                null, false, "/^\(([A-Z]{3})\)$/");
            if (!empty($depCode) && $depCode !== $dep) {
                $this->logger->debug('skip route ' . $numRoot . ' (other airport departure ' . $depCode . ')');
                continue;
            }
            $arrCode = $this->http->FindSingleNode("(" . $xpath . ")[{$position}]//span[contains(@class,'arrival-code')]",
                null, false, "/^\(([A-Z]{3})\)$/");
            if (!empty($arrCode) && $arrCode !== $arr) {
                $this->logger->debug('skip route ' . $numRoot . ' (other airport arrival ' . $arrCode . ')');
                continue;
            }
            */
            // open details
            $details = $this->driver->findElement(\WebDriverBy::xpath("(" . $xpath . ")[{$position}]//a[contains(.,'Details')]"));
            $y = $details->getLocation()->getY() - 20;
            $this->driver->executeScript("window.scrollBy(0, $y)");
            $details = $this->driver->findElement(\WebDriverBy::xpath("(" . $xpath . ")[{$position}]//a[contains(.,'Details')]"));

            if (!$details) {
                $this->http->saveScreenshots = true;
                $this->saveResponse();

                throw new \CheckException("can't see details", ACCOUNT_ENGINE_ERROR);
            }
            $limit++;
            $counter++;

            if ($counter > 20) {
                if ($limit > 100 && $this->http->saveScreenshots) {
                    $this->logger->error('ENOUGH!!!');

                    break;
                }

                $counter = 0;
                // no need (requestDateTime used)
//                $this->increaseTimeLimit();
            }
            $details->click();

            if ($limit === 1) { // only first click longer
                $sleep = 2;
                $this->logger->error("sleep -> {$sleep} sec");
                sleep($sleep);
            }
            $this->saveResponse();
            $this->checkSession();

            $routeInfo = $this->parseDetails();
            $this->logger->debug("routeInfo: " . var_export($routeInfo, true), ['pre' => true]);
            $routesDetails[$numRoot] = $routeInfo;

            /*
            $close = $this->waitForElement(\WebDriverBy::xpath("//span[@aria-label='Close']"), 0);
            if ($close) {
            */
            $this->logger->debug("close details by executeScript");
            $this->driver->executeScript("var element = document.querySelector('[aria-label=\"Close\"]'); if (element) element.click();");
            /*
            $this->driver->executeScript("
                var elements = document.querySelectorAll('span[aria-label=\"Close\"]');
                for (var i = 0, len = elements.length; i < len; i++) {
                    elements[i].click();
                };
            ");
        }
            */
            $this->saveResponse();
            $this->checkSession();
        }
        /*
        $this->http->saveScreenshots = false;
        */
        $this->saveResponse();
        $cabinFields = array_flip($this->getCabinFields(false));

        foreach ($Roots as $numRoot => $root) {// $root - don't use, it's broke
            if (!isset($routesDetails[$numRoot])) {
                continue;
            }

            if ((time() - $this->requestDateTime) > 110) {
                $this->logger->error('Time limit');

                break;
            }
            $routeInfo = $routesDetails[$numRoot];
            $this->logger->debug("num route: " . $numRoot);
            // no need (requestDateTime used)
//            $this->increaseTimeLimit();
            $position = $numRoot + 1;

            $stop = $this->http->FindSingleNode("(" . $xpath . ")[{$position}]//kilo-flight-duration-pres", null, false,
                "/^(.+?)\s*\|/");

            if ($stop === 'Non-stop') {
                $stop = 0;
            } else {
                $stop = (int) $this->http->FindPreg("/(\d+) stops?/", false, $stop);
            }
            $cntAwards = count($this->http->FindNodes("(" . $xpath . ")[{$position}]/following-sibling::div[1]//div[@aria-label][.//span[contains(@class,'cabin-text') and contains(.,'Select seats from')]]"));
            $this->logger->debug("cntAwards:" . $cntAwards);

            for ($numAwards = 1; $numAwards <= $cntAwards; $numAwards++) {
                $this->logger->debug("numAwards:" . $numAwards);
                $cabinDefault = $this->http->FindSingleNode("((" . $xpath . ")[{$position}]/following-sibling::div[1]//div[@aria-label][.//span[contains(@class,'cabin-text') and contains(.,'Select seats from')]])[{$numAwards}]/@aria-label",
                    null, false, "/(.+?)\s*seats from/i");
                $this->logger->debug("cabinDefault:" . $cabinDefault);
                // open award-prices
                $awards = $this->driver->findElement(\WebDriverBy::xpath("((" . $xpath . ")[{$position}]/following-sibling::div[1]//div[@aria-label][.//span[contains(@class,'cabin-text') and contains(.,'Select seats from')]])[$numAwards]"));

                if (!$awards) {
                    throw new \CheckException('can\'t find awards', ACCOUNT_ENGINE_ERROR);
                }
                $awards->click();
                // sel prices
                $this->saveResponse();
                $prices = $this->http->XPath->query($pricesPath = "(" . $xpath . ")[{$position}]/ancestor::div[contains(@class,'upsell-row')][1]/following-sibling::div[1]//div[contains(@class,'fare-list-item')]");
                $this->logger->debug("Found {$prices->length} type of price");
                $this->logger->debug("prices path: " . $pricesPath);

//                if ($stop === 0) {
//                    $layover = null;
//                } else {
//                    $layover = $this->sumLayovers($routeInfo);
//                }
//                $this->logger->debug("layover Route: " . $layover);

                foreach ($prices as $numPrice => $prRoot) {
                    $this->logger->debug("num price: " . $numPrice);

                    if ($this->http->FindSingleNode(".//div[contains(@class,'fare-button')][contains(.,'Not available')]",
                        $prRoot)
                    ) {
                        $this->logger->debug("skip Not available award");

                        continue;
                    }
                    $pos = $numPrice + 1;
                    $awardHead = $this->http->FindSingleNode("./ancestor::div[contains(@class,'fare-list')]/preceding-sibling::div[1]/div[{$pos}]",
                        $prRoot);
                    $miles = $this->http->FindSingleNode(".//kilo-price-with-points//span[@class='points-total']",
                        $prRoot);
                    $awardSum = $this->http->FindSingleNode(".//kilo-price-with-points//kilo-price", $prRoot);

                    $routeCabins = [];
                    $cabins = $this->http->XPath->query(".//div[contains(@class,'mixed-cabin-title')]/following-sibling::div",
                        $prRoot);

                    foreach ($cabins as $item) {
                        $routeMix = str_replace([' ', ':'], '', $this->http->FindSingleNode("./span[1]", $item));

                        if (!empty($routeMix)) {
                            $routeCabins[$routeMix] = $this->http->FindSingleNode("./span[2]", $item);
                        }
                    }
                    $this->logger->debug("routeCabins: " . var_export($routeCabins, true));
                    $result = ['connections' => []];

                    if ($numMiles = $this->http->FindPreg("/^(\d[\d.]+)k$/i", false, $miles)) {
                        $miles = (int) ($numMiles * 1000);
                    }
                    $currency = str_replace(" ", '', $this->http->FindPreg("/^(\D+)/u", false, $awardSum));
                    $fees = $this->http->FindPreg("/(\d[.,\d]+)/u", false, $awardSum);

                    $sum = PriceHelper::cost($fees);
                    $headData = [
                        'distance'    => null,
                        'num_stops'   => $stop,
                        'times'       => ['flight' => null, 'layover' => null],
                        'redemptions' => [
                            'miles'   => $miles,
                            'program' => $this->AccountFields['ProviderCode'],
                        ],
                        'payments' => [
                            'currency' => $this->currencyLoc($currency),
                            'taxes'    => $sum,
                            'fees'     => null,
                        ],
                    ];

                    foreach ($routeInfo['segments'] as $segNum => $s) {
                        $routeMix = $s['departure']['code'] . '-' . $s['arrival']['code'];
                        $printCabin = (isset($routeCabins[$routeMix]) ? $routeCabins[$s['departure']['code'] . '-' . $s['arrival']['code']] : $cabinDefault);
                        $seg = [
                            'departure' => [
                                'date'     => date('Y-m-d H:i', $s['departure']['datetime']),
                                'dateTime' => $s['departure']['datetime'],
                                'airport'  => $s['departure']['code'],
                            ],
                            'arrival' => [
                                'date'     => date('Y-m-d H:i', $s['arrival']['datetime']),
                                'dateTime' => $s['arrival']['datetime'],
                                'airport'  => $s['arrival']['code'],
                            ],
                            'meal'       => null,
                            'cabin'      => $cabinFields[$printCabin] ?? ($cabinFields[$cabinDefault] ?? $this->tryGuessCabin($cabinDefault)),
                            'fare_class' => null,
                            'flight'     => [$s['flight']],
                            'airline'    => $s['airline'],
                            'operator'   => $s['operator'],
                            'distance'   => null,
                            'aircraft'   => $s['aircraft'],
                            'times'      => [
                                'flight'  => null, //$s['duration'],
                                'layover' => null, // $s['layover'],
                            ],
                            'classOfService' => $printCabin,
                        ];
                        $result['connections'][] = $seg;
                    }
                    $res = array_merge($headData, $result);

                    if (strpos($cabinDefault, ' left') !== false) {
                        $res['tickets'] = $this->http->FindPreg("/\d+ seat/", false, $cabinDefault);
                    }
                    $res['award_type'] = $awardHead;

                    $this->logger->debug(var_export($res, true), ['pre' => true]);
                    $routes[] = $res;
                }
            }
        }

        $this->logger->debug('Parsed data:');
        $this->logger->debug(var_export($routes, true), ['pre' => true]);

        return $routes;
    }

    private function parseDetails(): array
    {
        $depDate = $this->http->FindSingleNode("//mat-dialog-container//kilo-flight-details-pres/descendant::span[starts-with(normalize-space(),'Departing')][1]",
            null, false, '/Departing (.+)/');
        $totalTravel = $this->http->FindSingleNode("//mat-dialog-container//kilo-flight-details-pres/descendant::span[starts-with(normalize-space(),'Departing')][1]/following-sibling::span",
            null, false, "/Total travel time:\s*(.+)/");
        $this->logger->debug("duration Route: " . $totalTravel);

        $segments = $this->http->XPath->query("//mat-dialog-container//kilo-flight-details-pres//kilo-flight-segment-details-cont");
        $routeInfo = [
            'duration' => $totalTravel,
            'segments' => [],
        ];

        foreach ($segments as $segment) {
            $timeDep = $this->http->FindSingleNode("./descendant::div[1]/div[1]/span[1]", $segment);
//            $dayDep = $this->http->FindSingleNode("./descendant::div[1]/div[1]/span[2]", $segment);
            $timeArr = $this->http->FindSingleNode("./descendant::div[1]/following-sibling::div[not(contains(.,'Stop in'))][1]/div[1]/span[1]",
                $segment);
            $stops = count($this->http->FindNodes("./descendant::div[1]/following-sibling::div[position()<3][contains(.,'Stop in')]",
                $segment));

//            $dayArr = $this->http->FindSingleNode("./descendant::div[1]/following-sibling::div[1]/div[1]/span[2]", $segment);
            $departure = $this->http->FindSingleNode("./descendant::div[1]/div[contains(@class,'flight-details')]/div[1]//descendant::span[1]",
                $segment);
            $departureDate = $this->http->FindSingleNode("./descendant::div[1]/div[contains(@class,'flight-details')]/div[1]//descendant::span[2]",
                $segment, false, "/Departing (.+)/");

            if (empty($departureDate)) {
                $departureDate = $depDate;
            }
            $this->logger->debug('Departing: ' . $departureDate);
            $arrival = $this->http->FindSingleNode("./descendant::div[1]/following-sibling::div[not(contains(.,'Stop in'))][1]/div[contains(@class,'flight-details')]/span[1]",
                $segment);
            $arrivalDate = $this->http->FindSingleNode("./descendant::div[1]/following-sibling::div[not(contains(.,'Stop in'))][1]/div[contains(@class,'flight-details')]/span[2]",
                $segment, false, "/Arriving (.+)/");

            if (empty($arrivalDate)) {
                $arrivalDate = $departureDate;
            }
            $this->logger->debug('Arriving: ' . $arrivalDate);

            $weekday = $this->http->FindPreg('/(\w+),\s+.+$/i', false, $departureDate);
            $weekdayNumber = (int) date('N', strtotime($weekday));
            $parsedDepDate = EmailDateHelper::parseDateUsingWeekDay("$departureDate, " . date('Y'), $weekdayNumber);
            $depDateTime = strtotime($timeDep, $parsedDepDate);

            $weekday = $this->http->FindPreg('/(\w+),\s+.+$/i', false, $arrivalDate);
            $weekdayNumber = (int) date('N', strtotime($weekday));
            $parsedArrDate = EmailDateHelper::parseDateUsingWeekDay("$arrivalDate, " . date('Y'), $weekdayNumber);
            $arrDateTime = strtotime($timeArr, $parsedArrDate);
            $seg = [
                // не стоит выводить. см маршрут IAD-SEZ и там перелет IAD-EWR-ADD-SEZ пишет, что 2 остановки.а по факту в деталях еще указывает остановку м/д EWR и ADD в LFW (один рейс, может потому)
                //                'num_stops' => $stops,
                'departure' => [
                    'datetime' => $depDateTime,
                    'name'     => $this->http->FindPreg("/(.+) [A-Z]{3}$/", false, $departure),
                    'code'     => $this->http->FindPreg("/.+ ([A-Z]{3})$/", false, $departure),
                ],
                'arrival' => [
                    'datetime' => $arrDateTime,
                    'name'     => $this->http->FindPreg("/(.+) [A-Z]{3}$/", false, $arrival),
                    'code'     => $this->http->FindPreg("/.+ ([A-Z]{3})$/", false, $arrival),
                ],
                'flight' => str_replace(' ', '',
                    $this->http->FindSingleNode("./descendant::div[1]/div[contains(@class,'flight-details')]//span[@class='flight-number']",
                        $segment)),
                'airline' => $this->http->FindSingleNode("./descendant::div[1]/div[contains(@class,'flight-details')]//span[@class='flight-number']",
                    $segment, false, "/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$/"),
                'operator' => $this->http->FindSingleNode("./descendant::div[1]/div[contains(@class,'flight-details')]//span[@class='flight-number']/following-sibling::span[contains(.,'Operated by')]",
                    $segment, false, "/Operated by (.+)/"),
                'duration' => $this->http->FindSingleNode("./descendant::div[1]/div[contains(@class,'flight-details')]//span[starts-with(normalize-space(),'Duration')]",
                    $segment, false, "/Duration:\s*(.+)/"),
                'aircraft' => $this->http->FindSingleNode("./descendant::div[1]/div[@class='aircraft']", $segment) ?? $this->http->FindSingleNode("./descendant::div[1]//span[starts-with(@class,'aircraft-type ')]", $segment),
                'layover'  => $this->http->FindSingleNode("./descendant::div[1]/following-sibling::div[1]/div[contains(@class,'layover')]",
                    $segment, false, "/Layover:\s*(.+?)\s*(?:\||$)/"),
            ];
            $routeInfo['segments'][] = $seg;
        }

        return $routeInfo;
    }

    private function parseDetailsJson($data, $dictionaries): array
    {
        $this->sendNotification("old format // ZM"); // 24.01.2024
        $totalTravel = (int) ($data['duration'] / 60);
        $this->logger->debug("duration Route: " . $totalTravel);
        $routeInfo = [
            'duration' => $totalTravel,
            'segments' => [],
        ];

        foreach ($data['segments'] as $segment) {
            if (!isset($dictionaries['flight'][$segment['flightId']])) {
                $this->sendNotification("check flightId. not found // ZM");

                return [];
            }
            $segmentData = $dictionaries['flight'][$segment['flightId']];

            $seg = [
                'flightId'  => $segment['flightId'],
                'departure' => [
                    'datetime' => strtotime(substr($segmentData['departure']['dateTime'], 0, 16)),
                    'code'     => $segmentData['departure']['locationCode'],
                    'terminal' => $segmentData['departure']['terminal'] ?? null,
                ],
                'arrival' => [
                    'datetime' => strtotime(substr($segmentData['arrival']['dateTime'], 0, 16)),
                    'code'     => $segmentData['arrival']['locationCode'],
                    'terminal' => $segmentData['arrival']['terminal'] ?? null,
                ],
                'flight'   => $segmentData['marketingAirlineCode'] . $segmentData['marketingFlightNumber'],
                'airline'  => $segmentData['marketingAirlineCode'],
                'operator' => $segmentData['operatingAirlineCode'] ?? null,
                'aircraft' => $segmentData['aircraftCode'],
                'layover'  => isset($segment['connectionTime']) ? (int) ($segment['connectionTime'] / 60) : null,
                // не стоит выводить. см маршрут IAD-SEZ и там перелет IAD-EWR-ADD-SEZ пишет, что 2 остановки.а по факту в деталях еще указывает остановку м/д EWR и ADD в LFW (один рейс, может потому)
                //                'num_stops'=> isset($segment['stops']) ? count($segment['stops']) : null
            ];
            $routeInfo['segments'][] = $seg;
        }

        return $routeInfo;
    }

    private function parseDetailsJson2($data): array
    {
        $totalTravel = (int) ($data['duration'] / 60);
        $this->logger->debug("duration Route: " . $totalTravel);
        $routeInfo = [
            'duration' => $totalTravel,
            'segments' => [],
        ];

        foreach ($data['segments'] as $segment) {
            $segmentData = $segment['flight'];

            $seg = [
                'flightId'  => $segment['flightId'],
                'stops'     => $segment['stops'] ?? [],
                'departure' => [
                    'datetime' => strtotime(substr($segmentData['departure']['dateTime'], 0, 16)),
                    'code'     => $segmentData['departure']['locationCode'],
                    'terminal' => $segmentData['departure']['terminal'] ?? null,
                ],
                'arrival' => [
                    'datetime' => strtotime(substr($segmentData['arrival']['dateTime'], 0, 16)),
                    'code'     => $segmentData['arrival']['locationCode'],
                    'terminal' => $segmentData['arrival']['terminal'] ?? null,
                ],
                'flight'   => $segmentData['marketingAirlineCode'] . $segmentData['marketingFlightNumber'],
                'airline'  => $segmentData['marketingAirlineCode'],
                'operator' => $segmentData['operatingAirlineCode'] ?? null,
                'aircraft' => $segmentData['aircraftCode'],
                'layover'  => isset($segment['connectionTime']) ? (int) ($segment['connectionTime'] / 60) : null,
            ];
            $routeInfo['segments'][] = $seg;
        }

        return $routeInfo;
    }

    private function checkSession($noMatter = false)
    {
        if ($noMatter || $this->http->FindSingleNode("//button[@data-analytics-val='timeout>continue']")) {
            $this->logger->debug("Extend your session");
            $this->driver->executeScript("
                    var elements = document.querySelectorAll('button[data-analytics-val=\"timeout>continue\"]');
        
                    for (var i = 0, len = elements.length; i < len; i++) {
                        elements[i].click();
                    };                ");
        }
    }

    private function currencyLoc($s)
    {
        $sym = ['CA$' => 'CAD'];

        if (isset($sym[$s])) {
            return $sym[$s];
        }

        return $this->currency($s);
    }

    private function tryGuessCabin(string $str): string
    {
        $this->logger->notice(__METHOD__);

        if (stripos($str, 'Business') !== false) {
            return 'business';
        }

        if (stripos($str, 'First') !== false) {
            return 'firstClass';
        }

        if (stripos($str, 'Premium Economy') !== false) {
            return 'premiumEconomy';
        }

        if (stripos($str, 'Economy') !== false) {
            return 'economy';
        }

        $this->sendNotification('guessing cabin. check it');

        return $str;
    }

    private function validRoute(array $fields): bool
    {
        $this->logger->notice(__METHOD__);

        return true;
        $validCodes = \Cache::getInstance()->get('ra_aeroplan_locations');

        if (!empty($validCodes) && is_array($validCodes)
            && in_array($fields['DepCode'], $validCodes) && in_array($fields['ArrCode'], $validCodes)) {
            return true;
        }
        $validCodes = [];
        $http2 = new \HttpBrowser("none", new \CurlDriver());
        $this->http->brotherBrowser($http2);
        $http2->GetURL('https://www.aircanada.com/content/aircanada-config/ca/en/location.html');

        if ($http2->Response['code'] == 403) {
            throw new \CheckRetryNeededException(5, 0);
        }

        $data = $http2->JsonLog(null, 0);

        foreach ($data->countries as $country) {
            if (isset($country->da) && !in_array($country->da, $validCodes)) {
                $validCodes[] = $country->da;
            }

            if (isset($country->states) && isset($country->states->da)
                && !in_array($country->states->da, $validCodes)) {
                $validCodes[] = $country->states->da;
            }
            $cities = $this->getCities($country);

            foreach ($cities as $city) {
                if (!in_array($city->code, $validCodes)) {
                    $validCodes[] = $city->code;
                }

                foreach ($city->airports as $airport) {
                    if (!in_array($airport->code, $validCodes)) {
                        $validCodes[] = $airport->code;
                    }
                }
            }
        }

        if (empty($validCodes)) {
            $this->sendNotification('check locations // ZM');

            return false;
        }
        \Cache::getInstance()->set('ra_aeroplan_locations', $validCodes, 60 * 60 * 24);

        if (!in_array($fields['DepCode'], $validCodes)) {
            $this->SetWarning($this->ErrorMessage = 'no ' . $fields['DepCode'] . ' in locations');

            return false;
        }

        if (!in_array($fields['ArrCode'], $validCodes)) {
            $this->SetWarning($this->ErrorMessage = 'no ' . $fields['ArrCode'] . ' in locations');

            return false;
        }

        return true;
    }

    private function getCities($data)
    {
        if (isset($data->cities)) {
            return $data->cities;
        }
        $cities = [];

        if (isset($data->states)) {
            foreach ($data->states as $state) {
                $cities = array_merge($cities, $state->cities);
            }
        }

        return $cities;
    }

    private function someSleep()
    {
        usleep(random_int(7, 20) * 100000);
    }

    private function getFingerprint()
    {
        $fps = \Cache::getInstance()->get('aeroplan_fps');
        $fingerprintFactory = $this->services->get(FingerprintFactory::class);

        if (!$fps) {
            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = 100;
            $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
            $fpIds = $fingerprintFactory->getFingerprintSet([$request], 32000);
            $fps = array_map(function ($value) {
                return ['id' => $value, 'cnt' => 0];
            }, $fpIds);
            \Cache::getInstance()->set('aeroplan_fps', $fps, 300);
        }

        usort($fps, function ($a, $b) {
            if ($a['cnt'] == $b['cnt']) {
                return 0;
            }

            return ($a['cnt'] < $b['cnt']) ? -1 : 1;
        });

        ++$fps[0]['cnt'];

        \Cache::getInstance()->set('aeroplan_fps', $fps, 300);

        return $fingerprintFactory->getOneById($fps[0]['id']);
    }

    private function clickSearchForm()
    {
        $this->logger->notice(__METHOD__);

        $inputs = [
            'miles'        => $this->waitForElement(\WebDriverBy::xpath("//input[contains(@class, 'abc-form-element-checkbox')]"), 5),
            'from'         => $this->waitForElement(\WebDriverBy::xpath("//div[@id = 'flightsOriginLocationbkmgLocationContainer']"), 0),
            'to'           => $this->waitForElement(\WebDriverBy::xpath("//div[@id = 'flightsOriginDestinationbkmgLocationContainer']"), 0),
            'date'         => $this->waitForElement(\WebDriverBy::xpath("//input[@name = 'bkmg-desktop_travelDates']"), 0),
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

    private function clickTextField($element)
    {
        if (isset($element)) {
            $element->click();
            sleep(2);
            $element->click();
            sleep(2);
        } else {
            $this->logger->error('Failed!');
        }
    }

    private function getKeyConfig(array $info)
    {
        return $info[\SeleniumStarter::CONTEXT_BROWSER_FAMILY] . '-' . $info[\SeleniumStarter::CONTEXT_BROWSER_VERSION] . '-' . $this->seleniumRequest->getOs();
    }

    private function loggerStateBrowser($result = null)
    {
        return false;
        $memStatBrowsers = \Cache::getInstance()->get(self::MEMCACHE_KEY_BROWSER_STAT);

        if (!is_array($memStatBrowsers)) {
            $memStatBrowsers = [];
        }
        $browserInfo = $this->seleniumDriver->getBrowserInfo();
        $key = $this->getKeyConfig($browserInfo);

        if (!isset($memStatBrowsers[$key])) {
            $memStatBrowsers[$key] = ['success' => 0, 'failed' => 0];
        }

        if (empty($result) && $this->ErrorCode !== ACCOUNT_WARNING) {
            $this->logger->info("marking config {$this->config} as bad");
            \Cache::getInstance()->set('aeroplan_config_' . $this->config, 0);
        } else {
            $this->logger->info("marking config {$this->config} as successful");
            \Cache::getInstance()->set('aeroplan_config_' . $this->config, 1);
        }

        if (empty($result) && $this->ErrorCode !== ACCOUNT_WARNING) {
            $memStatBrowsers[$key]['failed']++;
        } else {
            $memStatBrowsers[$key]['success']++;
        }

        if (!isset($noStat)) {
            \Cache::getInstance()->set(self::MEMCACHE_KEY_BROWSER_STAT, $memStatBrowsers, 60 * 60 * 24);
        }
        $this->logger->warning(var_export($memStatBrowsers, true), ['pre' => true]);
    }
}
