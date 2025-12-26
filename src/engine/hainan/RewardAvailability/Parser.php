<?php

namespace AwardWallet\Engine\hainan\RewardAvailability;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class Parser extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;

    public $isRewardAvailability = true;
    private $memCookies;
    private $depName;
    private $arrName;
    private $noRoute;
    private $withAuth = true;

    public function IsLoggedIn()
    {
        return true;
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
//        $this->setProxyBrightData(null, Settings::RA_ZONE_RESIDENTIAL);
        $this->setProxyDOP();
        $this->http->setRandomUserAgent(20);
        $this->http->FilterHTML = false;
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => ['CNY'],
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'CNY',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        if ($fields['Currencies'][0] !== 'CNY') {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if ($fields['Adults'] > 9) {
            $this->logger->error("you can check max 9 travellers");

            return ['routes' => []];
        }

        if (!$this->validRoute($fields)) {
            return ['routes' => []];
        }

        return $this->tmpWarning();

        $this->noRoute = false;

        if (!$this->selenium($fields)) {
            if ($this->http->FindPreg('/src="\/_Incapsula_Resource\?/')) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $this->sendNotification('check fail // ZM');

            throw new \CheckException('something went wrong', ACCOUNT_ENGINE_ERROR);
        }

        /*
        if ($this->noRoute) {
            $this->logger->error('Unsupported Location');

            return [];
        }

        if (!$this->http->ParseForm(null, "//form[@data-form='AWARD']")) {
            throw new \CheckException('page not load', ACCOUNT_ENGINE_ERROR);
        }

        $memForm = $this->http->Form;
        $memFormURL = $this->http->FormURL;

        if (strpos($this->http->FormURL, '/HUPortal/dyn/portal/doEnc') === 0) {
            $this->http->FormURL = 'https://www.hainanairlines.com' . $this->http->FormURL;
        }
        $this->logger->debug(var_export($memForm, true));
        $this->logger->debug($memFormURL);
        */
        $this->http->FormURL = 'https://www.hainanairlines.com/HUPortal/dyn/portal/doEnc';

        // try get info for requests
        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);

        $http2->removeCookies();

        foreach ($this->memCookies as $cookie) {
            if (!in_array($cookie['name'], ['reese84', 'DWM_XSITECODE'])) {
                $http2->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
        }

        $headers = [
            'Accept'       => '*/*',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Referer'      => 'https://www.hainanairlines.com/US/US/Home',
        ];
        $postData = "COUNTRY_SITE=US&LANGUAGE=US&SITE=CBHZCBHZ&PAGE=FSIP&FSIP_HAS_CHD_INF=false&FSIP_SOURCE_COUNTRY_CODES=CN&FSIP_DESTINATION_COUNTRY_CODES=CN&FSIP_TRIP_TYPE=O";
        $http2->PostURL("https://www.hainanairlines.com/HUPortal/dyn/portal/flightSearchIntermediatePage",
            $postData, $headers);

        $data = $http2->JsonLog(null, 1);

        if (!isset($data->mapDataUI->CSRF_TOKEN)) {
            throw new \CheckException('no token', ACCOUNT_ENGINE_ERROR);
        }
        // once again
        $this->http->removeCookies();

        foreach ($this->memCookies as $cookie) {
            if (!in_array($cookie['name'], ['incap_sh_2250578'])) {
                $this->http->setCookie($cookie['name'], $cookie['value'],
                    $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
        }

        $token = [
            'key' => $data->mapDataUI->CSRF_TOKEN->KEY,
            'val' => urlencode($data->mapDataUI->CSRF_TOKEN->VALUE),
        ];
        $d1 = urlencode(date('m/d/Y', $fields['DepDate']));
        $d2 = date('Ymd0000', $fields['DepDate']);
        $cabin = in_array($fields['Cabin'], ['economy', 'premiumEconomy']) ? 'E' : 'B';
        $postData = 'BOOKING_FLOW=AWARD&CABIN_FILTERING=TRUE&PRICING_TYPE=O&DISPLAY_TYPE=1&CABIN_FILTERING=TRUE&IS_FLEXIBLE=TRUE&LANGUAGE=US&SITE=CBHZCBHZ&COUNTRY_SITE=US&AIR_PARAM_CABIN_E_CFF=TESTECOAWD&AIR_PARAM_CABIN_B_CFF=TESTBUSAWD&AIR_PARAM_CABIN_F_CFF=FIRST&TRIGGER_PAGE=SRCH&departure-location=&arrival-location=&departureLoc=' . $this->depName . '&B_LOCATION_1=' . $fields['DepCode'] . '&arrivalLoc=' . $this->arrName . '&E_LOCATION_1=' . $fields['ArrCode'] . '&TRIP_TYPE=O&departure-date=' . $d1 . '&B_DATE_1=' . $d2 . '&B_ANY_TIME_1=TRUE&B_DATE_2=&B_ANY_TIME_2=TRUE&CABIN=' . $cabin . '&NB_ADT=' . $fields['Adults'] . '&NB_CHD=0&NB_INF=0&' . $token['key'] . '=' . $token['val'];
        $headers = [
            'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Content-Type'              => 'application/x-www-form-urlencoded',
            'Origin'                    => 'https://www.hainanairlines.com',
            'Referer'                   => 'https://www.hainanairlines.com/US/US/Home',
            'Upgrade-Insecure-Requests' => '1',
        ];
        $this->http->PostURL($this->http->FormURL, $postData, $headers);

        if ($this->http->FindPreg("/\/_Incapsula_Resource?/")) {
            $this->logger->error('/_Incapsula_Resource');

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($this->http->ParseForm(null, '//form[contains(@action,"booking/availability")]')) {
            $this->http->PostForm();
        } else {
            throw new \CheckException('can\'t load the page availability', ACCOUNT_ENGINE_ERROR);
        }

        if (($script = $this->http->FindSingleNode("//script[contains(.,'clientSideData =')]"))
            && stripos($script, 'REDIRECT_AUTO_SUBMIT') !== false
            && $this->http->FindPreg('/clientSideData = \{"REDIRECT_AUTO_SUBMIT":true\};/', false, $script)
        ) {
            $this->logger->notice('We are unable to find departing flights matching the criteria you specified. Please modify your selection and try again.');
            $this->logger->notice('skip route data');

            return ["routes" => []];
        }

        return ["routes" => $this->parseRewardFlights($fields, $token)];
    }

    private function parseRewardFlights($fields, $token): array
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("ParseReward [" . $fields['DepDate'] . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);

        // for payment requests below (http2)
        $headers = [
            "Accept"                    => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
            "Content-Type"              => "application/x-www-form-urlencoded",
            "Origin"                    => "https://www.hainanairlines.com",
            "Referer"                   => "https://www.hainanairlines.com/HUOnline/dyn/air/booking/availability",
            "Upgrade-Insecure-Requests" => "1",
        ];
        $action = $this->http->FindSingleNode("//form[contains(@action,'fare')]/@action");

        if (strpos($action, '/HUOnline/dyn/air/booking/fare') === 0) {
            $action = 'https://www.hainanairlines.com' . $action;
        }
        $tab_id = $this->http->FindPreg("/booking\/fare\?TAB_ID=([^&=]+)$/", false, $action);
        $d1 = urlencode(date('m/d/Y', $fields['DepDate']));
        $d2 = date('Ymd0000', $fields['DepDate']);
        $cabin = in_array($fields['Cabin'], ['economy', 'premiumEconomy']) ? 'E' : 'B';

        $postData = 'LANGUAGE=US&SITE=CBHZCBHZ&COUNTRY_SITE=US&AIR_PARAM_SHOW_FARE_PAGE=TRUE&FROM_AVAI=true&RESTRICTION=true&arrivalLoc=' . $this->arrName . '&TRIGGER_PAGE=SRCH&B_DATE_1=' . $d2 . '&PRICING_TYPE=O&B_ANY_TIME_1=TRUE&NB_CHD=0&B_LOCATION_1=' . $fields['DepCode'] . '&AIR_PARAM_CABIN_B_CFF=TESTBUSAWD&AIR_PARAM_CABIN_F_CFF=FIRST&' . $token['key'] . '=' . $token['val'] . '&NB_ADT=' . $fields['Adults'] . '&IS_FLEXIBLE=TRUE&CABIN_FILTERING=TRUE&BOOKING_FLOW=AWARD&E_LOCATION_1=' . $fields['ArrCode'] . '&AIR_PARAM_CABIN_E_CFF=TESTECOAWD&TAB_ID=' . urlencode($tab_id) . '&departureLoc=' . $this->depName . '&NB_INF=0&CABIN=' . $cabin . '&DISPLAY_TYPE=1&departure-date=' . $d1 . '&B_ANY_TIME_2=TRUE&ROW_1=ROWAWAWAWROW&TRIP_TYPE=O&WDS_ITINERARY_DISPLAY_MODE=D&PAGE_TICKET=0&SchedDrivenAvailButton_1=ROWAWAWAWROW';

        if (!($clientSideData = $this->http->FindPreg("/var clientSideData = (\{.+?\})\;\s+clientSideData\[/"))) {
            $data = $this->http->JsonLog($clientSideData, 1, true);
//            return $this->parseRewardFlightsJson($dataJson, $fields);
        }

        // parsing
        $rootRoutes = $this->http->XPath->query("//*[@id='b0FlightsTBodyEl']/tr[./td[1][span[contains(@class,'custom-radio')]]]");
        $this->logger->debug("found " . $rootRoutes->length . " routes");
        $result = [];

        foreach ($rootRoutes as $numRoute => $root) {
            $this->logger->debug("route #$numRoute");
            $segments = [];
            $numSegments = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space()!=''][1]", $root,
                false, "/Itinerary has (\d+) segments,/");

            if (!$numSegments) {
                $this->sendNotification("check segments");

                throw new \CheckException("check segments", ACCOUNT_ENGINE_ERROR);
            }
            $this->logger->debug("found $numSegments segment(s)");
            $cntSegments = (int) $numSegments;
            $allStops = -1;

            foreach (range(1, $cntSegments) as $number) {
                $this->logger->debug("seg #$number");
                $allStops++;

                if ($number === 1) {
                    $rootSeg = $root;
                    $td1 = 2;
                } else {
                    $nextSeg = ($number - 1) * 2;
                    $roots = $this->http->XPath->query("./following-sibling::tr[{$nextSeg}]", $root);

                    if ($roots->length !== 1) {
                        $this->sendNotification("check segments (roots)");

                        throw new \CheckException("check segments (roots)", ACCOUNT_ENGINE_ERROR);
                    }
                    $rootSeg = $roots->item(0);
                    $td1 = 1;
                }
                $td2 = $td1 + 1;
                $td3 = $td1 + 2;
                $urlDetails = $this->http->FindSingleNode("./td[{$td3}]//a[contains(@href,'/air/huFlightInfo')]/@href",
                    $rootSeg);

                $depCode = $this->http->FindPreg("/&B_LOCATION=([A-Z]{3})&/", false, $urlDetails);
                $arrCode = $this->http->FindPreg("/&E_LOCATION=([A-Z]{3})&/", false, $urlDetails);
                $depDate = preg_replace("/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})$/", '$1-$2-$3 $4:$5',
                    $this->http->FindPreg("/&B_DATE=(\d{12})&/", false, $urlDetails));

                $airline = $this->http->FindPreg("/&AIRLINE_CODE=([A-Z\d][A-Z]|[A-Z][A-Z\d])&/", false, $urlDetails);
                $flightNumber = $this->http->FindPreg("/&FLIGHT_NUMBER=(\d+)&/", false, $urlDetails);
                $aircraft = $this->http->FindPreg("/&EQUIPMENT_CODE=([^&]+)&/", false, $urlDetails);
                $stops = $this->http->FindPreg("/&NUMBER_OF_STOPS=(\d+)/", false, $urlDetails);
                $allStops += $stops;

                if ($number !== $cntSegments) {
                    $layover = $this->http->FindSingleNode("./following-sibling::tr[1]/td[2]/span[1]", $rootSeg, false,
                        "/Layover: (.+)/");
                    $this->logger->debug("layover:" . $layover);
                }
                $arrTime = $this->http->FindSingleNode("./td[{$td2}]/descendant::time[1]", $rootSeg);
                $time = $this->http->FindPreg("/^(\d+:\d+)\b/", false, $arrTime);
                $arrDate = strtotime($time, strtotime($depDate));

                // correct $arrDate
                $depTime = $this->http->FindSingleNode("./td[{$td1}]/descendant::time[1]", $rootSeg);
                $daysDep = $this->http->FindPreg("/^\d+:\d+ ([+-]\d+)$/", false, $depTime);

                if (($days = $this->http->FindPreg("/^\d+:\d+ ([+-]\d+)$/", false, $arrTime))
                    && $daysDep !== $days) {
                    $days = $days - ($daysDep = $daysDep ?? 0);
                    $arrDate = strtotime($days . ' days', $arrDate);
                }
                $segments[] = [
                    'num_stops' => $stops,
                    'cabin'     => $fields['Cabin'],
                    'departure' => [
                        'date'     => $depDate,
                        'airport'  => $depCode,
                        'terminal' => $this->http->FindSingleNode("./td[{$td1}]/descendant::span[contains(@class,'terminal')]",
                            $rootSeg, false, "/Terminal (\w+)/"),
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', $arrDate),
                        'airport'  => $arrCode,
                        'terminal' => $this->http->FindSingleNode("./td[{$td2}]/descendant::span[contains(@class,'terminal')]",
                            $rootSeg, false, "/Terminal (\w+)/"),
                    ],
                    'flight'   => [$airline . $flightNumber],
                    'airline'  => $airline,
                    'aircraft' => $aircraft,
                ];
            }

            $this->logger->debug("#$numRoute-segments");
            $this->logger->debug(var_export($segments, true), ['pre' => true]);
            sleep(1);
            $http2 = clone $this->http;
            $this->http->brotherBrowser($http2);
            $http2->SetBody($this->http->Response['body']);

            $pd = str_replace('ROWAWAWAWROW', $numRoute, $postData);
            $http2->PostURL($action, $pd, $headers);

            if ($http2->FindSingleNode("//div[normalize-space()='Ticket Price']")) {
                $points = $http2->FindSingleNode("//div[normalize-space()='Ticket Price']/following-sibling::div[1]//span[@id='finalMilesTobePaid']");
                $taxes = $http2->FindSingleNode("//div[normalize-space()='Ticket Price']/following-sibling::div[2]//span[@id='finalPriceTobePaid']");
                $currency = $http2->FindSingleNode("//div[normalize-space()='Ticket Price']/following-sibling::div[2]//span[contains(@class,'currency-text')]");
                $res = [
                    'num_stops'   => $allStops,
                    'redemptions' => [
                        'miles'   => (int) (PriceHelper::cost($points) / $fields['Adults']),
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $currency,
                        'taxes'    => round(PriceHelper::cost($taxes) / $fields['Adults'], 2),
                    ],
                    //                    'tickets' => null,
                    'connections' => $segments,
                ];
                $this->logger->debug(var_export($res, true), ['pre' => true]);
                $result[] = $res;
            } else {
                if (($script = $http2->FindSingleNode("//script[contains(.,'clientSideData =')]"))
                    && stripos($script, 'REDIRECT_AUTO_SUBMIT') !== false
                    && $this->http->FindPreg('/clientSideData = \{"REDIRECT_AUTO_SUBMIT":true\};/', false, $script)
                ) {
                    $this->logger->notice('System error. Please contact us for more information');
                    $this->logger->notice('skip route data');

                    continue;
                }
                $this->sendNotification("check getting prices // ZM");
            }
        }

        return $result;
    }

    private function parseRewardFlightsJson($dataJson, $fields): array
    {
        // TODO maybe....
        $this->logger->notice(__METHOD__);

        return [];
    }

    private function selenium($fields): bool
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox();
//            $selenium->useChromium();
            $selenium->disableImages();
            $selenium->usePacFile(false);

            $resolutions = [
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($chosenResolution);

            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.hainanairlines.com/US/US/Home");
            } catch (\ScriptTimeoutException | \TimeOutException $e) {
                $this->logger->error("ScriptTimeoutException: " . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }

            if (!$this->selectRoute($selenium, $fields)) {
                $selected = $this->selectRoute($selenium, $fields);

                if (!$selected) {
                    throw new \CheckRetryNeededException(5, 0);
                }
            }

            if ($this->withAuth) {
                $login = $selenium->waitForElement(\WebDriverBy::xpath("//li//a[contains(.,'Login')]"),
                    3);

                if (!$login) {
                    throw new \CheckException('login bnt not found', ACCOUNT_ENGINE_ERROR);
                }
                $login->click();

                $selenium->waitForElement(\WebDriverBy::xpath('//h2[contains(.,"Login to Fortune Wings")]'),
                    2);

                $selenium->saveResponse();
                $card = $selenium->waitForElement(\WebDriverBy::xpath("//h2[contains(.,'Login to Fortune Wings')]/ancestor::div[2]//input[@id='loginfortuneMobile']"), 0);
                $pwd = $selenium->waitForElement(\WebDriverBy::xpath("//h2[contains(.,'Login to Fortune Wings')]/ancestor::div[2]//input[@id='loginpasswordMobile']"), 0);

                if (!$card || !$pwd) {
                    throw new \CheckException('login form not found', ACCOUNT_ENGINE_ERROR);
                }
                $card->sendKeys($this->AccountFields['Login']);
                $pwd->sendKeys($this->AccountFields['Pass']);
                $selenium->saveResponse();

                $btn = $selenium->waitForElement(\WebDriverBy::xpath("//h2[contains(.,'Login to Fortune Wings')]/ancestor::div[2]//button[normalize-space()='Login']"), 0);

                if (!$btn) {
                    throw new \CheckException('login btn not found', ACCOUNT_ENGINE_ERROR);
                }
                $btn->click();
                sleep(5);
//                try {
//                    $selenium->http->GetURL("https://www.hainanairlines.com/US/US/Home");
//                } catch (\ScriptTimeoutException | \TimeOutException $e) {
//                    $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
//
//                    throw new \CheckRetryNeededException(3, 0);
//                }
//
//                $btn = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(normalize-space(),'Search flights')]"));
//
//                if (!$btn) {
//                    return false;
//                }
            }

            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
//            reese84 есть в localStorage

            if ($this->http->FindPreg('/<iframe id="main-iframe" src="\/_Incapsula_Resource\?/')) {
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();

                throw new \CheckRetryNeededException(5, 0);
            }

            $cookies = $selenium->driver->manage()->getCookies();
            $this->memCookies = $cookies;

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
        } catch (\ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        }// catch (ScriptTimeoutException $e)
        catch (\UnknownServerException | \SessionNotCreatedException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if (isset($retry) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new \CheckRetryNeededException(5, 0);
            }
        }

        return true;
    }

    private function selectRoute($selenium, $fields)
    {
        $btn = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(normalize-space(),'Search flights')]"));

        if (!$btn) {
            return false;
        }
        $btn->click();

        $btn = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(normalize-space(),'Search flights')]"));

        if (!$btn) {
            return false;
        }
        $btn->click();

        $span = $selenium->waitForElement(\WebDriverBy::xpath("//span/label[contains(normalize-space(),'Redeem your award flight(s)')]"),
            0);

        if (!$span) {
            return false;
        }
        $span->click();
        $dep = $selenium->waitForElement(\WebDriverBy::id('filght-search-from-AWARD'), 0);
        $arr = $selenium->waitForElement(\WebDriverBy::id('filght-search-to-AWARD'), 0);

        if (!$dep || !$arr) {
            return false;
        }
        $dep->click();
        sleep(1);
        $span = $selenium->waitForElement(\WebDriverBy::xpath("//form[contains(@class,'flight-form-srch-AWARD')]//span/label[contains(normalize-space(),'One Way')]"),
            2);

        if (!$span) {
            return false;
        }
        $span->click();

        $dep->click();
        $dep->clear();
        $dep->sendKeys(substr($fields['DepCode'], 0, 1));
        sleep(1);
        $dep->sendKeys(substr($fields['DepCode'], 1, 1));
        sleep(1);
        $dep->sendKeys(substr($fields['DepCode'], 2, 1));
        sleep(1);
        $dep->sendKeys(\WebDriverKeys::ENTER);
        //            $selenium->driver->executeScript("$('#filght-search-from-AWARD').parent('div').parent('div').nextAll('ul').find('li:contains(\"({$fields['DepCode']})\")').click()");
        $this->depName = $selenium->driver->executeScript("return $('#filght-search-from-AWARD').val();");
        $this->logger->error($this->depName);
        $arr->click();
        $arr->clear();
        $arr->sendKeys(substr($fields['ArrCode'], 0, 1));
        sleep(1);
        $arr->sendKeys(substr($fields['ArrCode'], 1, 1));
        sleep(1);
        $arr->sendKeys(substr($fields['ArrCode'], 2, 1));
        sleep(1);
        $arr->sendKeys(\WebDriverKeys::ENTER);
        //            $selenium->driver->executeScript("$('#filght-search-to-AWARD').parent('div').parent('div').nextAll('ul').find('li:contains(\"({$fields['ArrCode']})\")').click()");
        $this->arrName = $selenium->driver->executeScript("return $('#filght-search-to-AWARD').val();");
        $this->logger->error($this->arrName);

        // save page to logs
        $selenium->http->SaveResponse();

        if (!$this->http->FindPreg("/\({$fields['DepCode']}\)/", false, $this->depName)
            || !$this->http->FindPreg("/\({$fields['ArrCode']}\)/", false, $this->arrName)
        ) {
            // airport not selected
            return false;
        }

        if ($selenium->waitForElement(\WebDriverBy::id('filght-search-from-AWARD-error'), 0)
            || $selenium->waitForElement(\WebDriverBy::id('filght-search-to-AWARD-error'), 0)
        ) {
            //$this->noRoute = true;// no need with validRoute
            throw new \CheckRetryNeededException(5, 0);
        }

        return true;
    }

    private function validRoute($fields): bool
    {
        $locations = \Cache::getInstance()->get('ra_hainan_locations');

        if (!$locations || !is_array($locations)) {
//            $http2 = clone $this->http;
            $http2 = new \HttpBrowser("none", new \CurlDriver());
            $this->http->brotherBrowser($http2);
            $http2->GetURL("https://www.hainanairlines.com/HUPortal/dyn/portal/locationPicker/customizedRegionCodeMap?SITE=CBHZCBHZ&LANGUAGE=US&PAGE=HOME&COUNTRY_SITE=US&CODE=AWARDREG");
            $data = $http2->JsonLog(null, 0, true);

            if (!isset($data['mapDataUI']['LOCATION_DATA']['cities'])) {
                $this->sendNotification("RA check locations format // ZM");

                return true;
            }

            $locations = array_unique($http2->FindPregAll('/"([A-Z]{3})"/'));

            if (!empty($locations)) {
                \Cache::getInstance()->set('ra_hainan_locations', $locations, 60 * 60 * 24);
            } else {
                $this->sendNotification("RA check locations // ZM");
                // steel try parse
                return true;
            }
        }

        if (!in_array($fields['DepCode'], $locations)) {
            $this->logger->error('not found DepCode');

            return false;
        }

        if (!in_array($fields['ArrCode'], $locations)) {
            $this->logger->error('not found ArrCode');

            return false;
        }

        return true;
    }

    private function tmpWarning()
    {
        $this->logger->notice(__METHOD__);
        $msg = 'Sorry! The flight you are searching is currently unavailable on our site. We are sorry for any inconvenience this may cause. If you need any other help, please call our website customer service at 86 898 95339. (UI_809)';

        $this->ErrorCode = ACCOUNT_WARNING;
        $this->ErrorMessage = $msg;

        return ['routes' => []];
    }
}
