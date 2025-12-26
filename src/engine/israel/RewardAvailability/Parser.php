<?php

namespace AwardWallet\Engine\israel\RewardAvailability;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;

class Parser extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;
    use \PriceTools;

    public $isRewardAvailability = true;
    public static $useNew = true;
    private $token;
    private $auth_token;
    private $reloadStart;
    private $routeValid;
    private $routeChecked;
    private $DepData;
    private $ArrData;

    public static function GetAccountChecker($accountInfo)
    {
        if (!self::$useNew) {
            return new static();
        }

        require_once __DIR__ . "/ParserNew.php";

        return new ParserNew();
    }

    public static function getRASearchLinks(): array
    {
        return ['https://www.elal.com/en/OtherCountries/Pages/default.aspx' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->seleniumOptions->recordRequests = true;
        $this->useChromePuppeteer();
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->usePacFile(false);
        $this->disableImages();
        $this->http->setHttp2(true);
        $this->http->saveScreenshots = true; // сильно увеличивает время парсинга, включать лучше точечно для дебага
        $this->keepCookies(false);

        $this->setProxyGoProxies(null, 'ca');

//        $this->http->setRandomUserAgent(null, false, true, false, false, false);
        $this->seleniumRequest->setHotSessionPool(self::class, $this->AccountFields['ProviderCode']);
    }

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
//        $this->http->removeCookies();
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);

        if ($fields['Currencies'][0] !== 'USD') {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if ($fields['Adults'] > 9) {
            $this->SetWarning("You can check max 9 travellers");

            return ['routes' => []];
        }

        if ($fields['DepDate'] > strtotime("+361 days")) {
            $this->SetWarning('The requested departure date is too late.');

            return ['routes' => []];
        }

        try {
            $routes = $this->ParseReward($fields);
        } catch (\NoSuchDriverException $e) {
            $this->logger->error('NoSuchDriverException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\UnknownServerException $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\WebDriverCurlException | \WebDriverException $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());

            if (time() - $this->requestDateTime < 75) {
                throw new \CheckRetryNeededException(5, 0);
            }

            throw new \CheckException('WebDriverCurlException', ACCOUNT_ENGINE_ERROR);
        }

        if (!isset($routes)) {
            $this->http->saveScreenshots = true;
            $this->saveResponse();
            $this->http->saveScreenshots = false;

            throw new \CheckException('can\'t get routes', ACCOUNT_ENGINE_ERROR);
        }

        $this->logger->debug('save Session');
        $this->keepSession(true);

        return $routes;
    }

    private function ParseReward(array $fields, ?bool $isRetry = false)
    {
        $isHot = false;

        $this->logger->warning('[currentUrl]: ' . $this->http->currentUrl());

        if (strpos($this->http->currentUrl(), 'https://booking.elal.com/booking/flights') !== false
            || $this->http->currentUrl() === 'https://www.elal.com/en/OtherCountries/Pages/default.aspx'
        ) {
            $isHot = true;
        }

        if (!$isHot) {
            $this->loadStartPage();
        }

        $token = trim($this->driver->executeScript("return sessionStorage.getItem('sessionId');"), "\"");

        if (!empty($token) && !$this->validRoute($token, $fields)) {
            return [];
        }

        if (!isset($this->DepData, $this->ArrData)) {
            $this->logger->error("no airport Details");

            throw new \CheckException('something went wrong', ACCOUNT_ENGINE_ERROR);
        }
        $this->driver->executeScript("localStorage.removeItem('response');");

        if (!$isHot) {
            $this->loadFormRequest($fields);
        } else {
            $this->loadSessionRequest($fields);
        }
        $this->waitForElement(\WebDriverBy::xpath(" //span[normalize-space()='D e p a r t u r e']"), 45);

        $this->saveResponse();

        return $this->checkResult($fields);
    }

    private function loadFormRequest(array $fields)
    {
        $this->logger->notice(__METHOD__);

        $dateTrip = date('Y-m-d', $fields['DepDate']) . "T00:00:00.000Z";
        $dateSearch = str_replace(' ', 'T',
                date('Y-m-d H:i:s', strtotime("-5 minutes"))) . '.' . date('B') . 'Z'; //"2024-02-02T08:31:55.617Z"

        $recentSearches = [
            [
                "bookingClasses" => [
                    "economy",
                    "premium",
                    "business",
                ],
                "trip" => [
                    [
                        "name"        => "outbound",
                        "origin"      => $this->DepData,
                        "destination" => $this->ArrData,
                        "date"        => $dateTrip,
                    ],
                ],
                "promoCode"  => null,
                "passengers" => [
                    "SRC" => 0,
                    "ADT" => $fields['Adults'],
                    "INF" => 0,
                    "CHD" => 0,
                    "YTH" => 0,
                ],
                "travelType"       => "one_way",
                "isFlexibleSearch" => false,
                "name"             => $this->getUuid(),
                "priceDisplayAs"   => "cash",
                "searchDate"       => $dateSearch,
            ],
        ];

        $recentSearches = addslashes(json_encode($recentSearches, JSON_UNESCAPED_UNICODE));
        $this->driver->executeScript($scr =
            "
                localStorage.removeItem('lastExternalReferrerTime');
                localStorage.removeItem('tgt:tlm:0');
                localStorage.removeItem('lastExternalReferrer');
                localStorage.removeItem('__rtbh.lid');
               
                
                localStorage.setItem('taboola global:last-external-referrer', 'other');
                localStorage.setItem('tgt:tlm:upper', 47);
                localStorage.setItem('tgt:tlm:lower', 47);

                localStorage.setItem(\"recentSearches\", \"" . $recentSearches . "\");
            "
        );

        $this->logger->debug($scr, ['pre' => true]);
        sleep(2);
        $this->http->GetURL("https://www.elal.com/en/OtherCountries/Pages/default.aspx");
        $btn = $this->waitForElement(\WebDriverBy::xpath("//button[@type='submit' and (contains(text(),'Find me a flight') or contains(@aria-label,'search'))]"),
            10);
        $this->saveResponse();

        if (!$btn) {
            throw new \CheckException('block', ACCOUNT_ENGINE_ERROR);
        }

        $btn->click();
    }

    private function loadSessionRequest(array $fields)
    {
        $dateTrip = date('Y-m-d', $fields['DepDate']) . "T00:00:00.000Z";
        $dateSearch = str_replace(' ', 'T',
                date('Y-m-d H:i:s', strtotime("-5 minutes"))) . '.' . date('B') . 'Z'; //"2024-02-02T08:31:55.617Z"
        $dateExpiration = str_replace(' ', 'T',
                date('Y-m-d H:i:s', strtotime("+45 minutes"))) . '.' . date('B') . 'Z'; //"2024-02-02T09:13:35.475Z"

        $currentFlight = [
            "bookingClasses" => [
                "economy",
                "premium",
                "business",
            ],
            "trip" => [
                [
                    "name"        => "outbound",
                    "origin"      => $this->DepData,
                    "destination" => $this->ArrData,
                    "date"        => $dateTrip,
                ],
            ],
            "promoCode"  => null,
            "passengers" => [
                "SRC" => 0,
                "ADT" => $fields['Adults'],
                "INF" => 0,
                "CHD" => 0,
                "YTH" => 0,
            ],
            "travelType"       => "one_way",
            "isFlexibleSearch" => false,
            "name"             => $this->getUuid(),
            "priceDisplayAs"   => "cash",
            "searchDate"       => $dateSearch,
        ];

        $currentFlightStr = addslashes(json_encode($currentFlight, JSON_UNESCAPED_UNICODE));

        $this->driver->executeScript($scr =
            "
        sessionStorage.setItem(\"currentFlight\", \"" . $currentFlightStr . "\");
        sessionStorage.setItem(\"expirationDate\",\"" . $dateExpiration . "\" );
            "
        );
        $this->logger->debug($scr, ['pre' => true]);
        sleep(2);
        $this->http->GetURL("https://booking.elal.com/booking/flights?market=US&lang=en");
    }

    private function ParseRewardOld($fields, ?bool $isRetry = false)
    {
        try {
            $this->loadStartPage();
            $btn = $this->waitForElement(\WebDriverBy::xpath("//button[@type='submit' and (contains(text(),'Find me a flight') or contains(@aria-label,'search'))]"),
                10);
        } catch (\StaleElementReferenceException $e) {
            $this->logger->error('StaleElementReferenceException: ' . $e->getMessage());

            $this->http->GetURL("https://www.elal.com/en/OtherCountries/Pages/default.aspx");

            $this->loadStartPage();

            if (!$isRetry) {
                return $this->ParseReward($fields, true);
            }
            $btn = $this->waitForElement(\WebDriverBy::xpath("//button[@type='submit' and (contains(text(),'Find me a flight') or contains(@aria-label,'search'))]"),
                10);
        }
        $this->saveResponse();

        if (!$btn) {
            throw new \CheckRetryNeededException(5, 0);
        }

        /** @var \SeleniumDriver $seleniumDriver */
        $seleniumDriver = $this->http->driver;
        // clear data
//        $seleniumDriver->browserCommunicator->getRecordedRequests();

        try {
            // select one way
//            $oneway = $this->waitForElement(\WebDriverBy::id('application.travelTypes.oneWay'), 15);
            $oneway = $this->waitForElement(\WebDriverBy::xpath('//a[normalize-space()="One way"]'), 15);
        } catch (\StaleElementReferenceException $e) {
            $this->logger->error('StaleElementReferenceException: ' . $e->getMessage());

            if (!$isRetry) {
                return $this->ParseReward($fields, true);
            }
        }

        if (!$oneway) {
            try {
                $this->driver->executeScript('window.stop();');
                $this->sendNotification('check reload //ZM');
                $this->http->GetURL("https://www.elal.com/en/OtherCountries/Pages/default.aspx");
            } catch (\UnexpectedJavascriptException $e) {
                $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage());
            }
//            $oneway = $this->waitForElement(\WebDriverBy::id('application.travelTypes.oneWay'), 15);
            $oneway = $this->waitForElement(\WebDriverBy::xpath('//a[normalize-space()="One way"]'), 15);

            if (!$oneway) {
                // debug
                $this->http->saveScreenshots = true;
                $this->saveResponse();

                throw new \CheckException('page not load', ACCOUNT_ENGINE_ERROR);
            }
        }

//        if ($continueBrowsing = $this->waitForElement(\WebDriverBy::xpath("//a[@id='skip'][contains(.,'continue browsing')]"), 0)) {
//            $this->http->saveScreenshots = true;
//            $this->saveResponse();
//            $this->http->saveScreenshots = false;
//            $continueBrowsing->click();
//            $oneway = $this->waitForElement(\WebDriverBy::id('application.travelTypes.oneWay'), 3);
//        }

        if (!$this->http->FindSingleNode("//a[@id='application.travelTypes.oneWay'][contains(@class,'ui-input-toggle-group__item--active')]")) {
            $oneway->click();
        }
        $this->saveResponse();

        $token = trim($this->driver->executeScript("return sessionStorage.getItem('sessionId');"), "\"");
        $this->logger->emergency(var_export($token, true));

        $date = $this->waitForElement(\WebDriverBy::xpath("//input[@id='outbound-departure-calendar-input']"), 10);
        // select date
        $dateInput = date('M d', $fields['DepDate']);
        /*
                if ($date) {
                    $this->logger->debug('enter date:' . $dateInput);
        //            $date->sendKeys($dateInput);
                    $this->driver->executeScript("
                        var input = document.querySelector('#outbound-departure-calendar-input');
                        input.value = '{$dateInput}';");
                    $date->sendKeys(\WebDriverKeys::ENTER);
                    $this->saveResponse();

                    $oneway = $this->waitForElement(\WebDriverBy::xpath('//a[normalize-space()="One way"]'), 15);

                    if ($oneway) {
                        $oneway->click();
                    }
                    $this->saveResponse();
                }

                $this->checkIfCalendarOpened();

                $input = $this->driver->executeScript("return $('#outbound-departure-calendar-input').val();");
                $this->http->saveScreenshots = true;
                $this->saveResponse();

                if (empty($input)) {
                    $this->logger->error('not entered. try again enter date');
                    // debug
                    $this->http->saveScreenshots = true;
                    $this->saveResponse();
                    //            $this->http->saveScreenshots = false;

                    if (!$this->waitForElement(\WebDriverBy::xpath("//button[@type='submit' and (contains(text(),'Find me a flight') or contains(@aria-label,'search'))]"), 0)) {
                        if (!$isRetry) {
                            return $this->ParseReward($fields, true);
                        }

                        throw new \CheckRetryNeededException(5, 0);
                    }

                    if ($date) {
                        $this->driver->executeScript("
                                var input = document.querySelector('#outbound-departure-calendar-input');
                                input.value = '{$dateInput}';");
                        $date->sendKeys(\WebDriverKeys::ENTER);
                    }

                    $done = $this->waitForElement(\WebDriverBy::xpath("//button[@aria-label='Done'or @aria-label='search.calendar.submit']"), 3);

                    if ($done) {
                        $this->logger->debug('done->click');
                        $done->click();
                        //                sleep(1);
                    }

                    $input = $this->driver->executeScript("return $('#outbound-departure-calendar-input').val();");

                    if (empty($input)) {
                        $this->logger->error('empty input data');

                        if ($this->waitForElement(\WebDriverBy::xpath("//h1[contains(text(),'Covid Info Center')]"), 0)) {
                            throw new \CheckRetryNeededException(5, 0);
                        }

                        $this->http->saveScreenshots = true;
                        $this->saveResponse();
                        //                $this->http->saveScreenshots = false;

                        throw new \CheckRetryNeededException(5, 0);
                    }
                }
        */
        $token = trim($this->driver->executeScript("return sessionStorage.getItem('sessionId');"), "\"");

        if (!empty($token) && !$this->validRoute($token, $fields)) {
            return [];
        }

        // select departure
        $resAirport = $this->checkingAirport($fields['DepCode'], 'outbound-origin-location-input', 'from');

        if (is_array($resAirport)) {
            return $resAirport;
        }

        sleep(2);

        $arr = $this->waitForElement(\WebDriverBy::xpath('//input[@id="outbound-destination-location-input"]'));

        if (!$arr) {
            // debug
            $this->http->saveScreenshots = true;
            $this->saveResponse();

            throw new \CheckException('page not load', ACCOUNT_ENGINE_ERROR);
        }

        // select arrival
        $resAirport = $this->checkingAirport($fields['ArrCode'], 'outbound-destination-location-input', 'to');

        if (is_array($resAirport)) {
            return $resAirport;
        }

        $this->saveResponse();
//        $this->http->saveScreenshots = false;

        // if opened calendar - close it
        $this->checkIfCalendarOpened();

//        if (!$pax = $this->waitForElement(\WebDriverBy::id('passenger-counters-input'), 0)) {
        if (!$pax = $this->waitForElement(\WebDriverBy::xpath('//input[@id="passenger-counters-input"]'), 0)) {
            throw new \CheckRetryNeededException(5, 0);
        }

//        if (!$this->http->FindSingleNode("//search-input[@aria-label='Traveler selector, currently 1 traveler, {$fields['Adults']} Adult']")) {
//        $this->logger->debug('[pax count]: ' . $pax->getText());

//        if ($pax->getText() != $fields['Adults']) {
        try {
            $pax->click();
        } catch (\UnknownServerException $e) {
            $this->logger->error($e->getMessage());
            $this->driver->executeScript('
                $("#passenger-counters-input").click();
                '
            );
        }
        $this->driver->executeScript('
            $(\'button[aria-label^="Decrease"]\').each(function(){stop = 9; while(stop>0){$(this).click();stop--;}});
            '
        );
        $btnADTPlus = $this->waitForElement(\WebDriverBy::xpath("//button[@aria-label='Increase Adult']"), 0);
        $checked = 1;

        if ($btnADTPlus) {
            $this->driver->executeScript('
            $(\'button[aria-label="Increase Adult"]\').each(function(){stop = ' . $fields['Adults'] . '-1; while(stop>0){$(this).click();stop--;}});
            '
            );

            if ($this->driver->executeScript("return $('#passenger-counters-input').get(0).value;") != $fields['Adults']) {
                $this->logger->debug('check Adults again');
                $this->driver->executeScript('
            $(\'button[aria-label^="Decrease"]\').each(function(){stop = 9; while(stop>0){$(this).click();stop--;}});
            '
                );

                while ($checked != $fields['Adults']) {
                    $btnADTPlus->click();
                    sleep(1);
                    $checked++;
                }
            }
        }
//        }

        // check date AGAIN
        $this->saveResponse();
        $this->checkIfCalendarOpened();

        $input = $this->driver->executeScript("return $('#outbound-departure-calendar-input').val();");

        if (empty($input)) {
            $this->logger->error('empty input data');

            if ($date) {
                $this->driver->executeScript("
                var input = document.querySelector('#outbound-departure-calendar-input');
                input.value = '{$dateInput}';");
                $date->sendKeys(\WebDriverKeys::ENTER);
            }
            $done = $this->waitForElement(\WebDriverBy::xpath("//button[@aria-label='Done'or @aria-label='search.calendar.submit']"),
                3);

            if ($done) {
                $this->logger->debug('done->click');
                $done->click();
//                sleep(1);
            }
            $input = $this->driver->executeScript("return $('#outbound-departure-calendar-input').val();");

            if (empty($input)) {
                throw new \CheckException('empty date', ACCOUNT_ENGINE_ERROR);
            }
        }

        if ($oneway = $this->waitForElement(\WebDriverBy::xpath("//a[@id='application.travelTypes.oneWay'][not(contains(@class,'ui-input-toggle-group__item--active'))]"),
            0)) {
            $oneway->click();
        }

        $this->clickSearchAndWaitLoad();

        $this->http->saveScreenshots = true;
        $this->saveResponse();
//        $this->http->saveScreenshots = false;

        if (
            $this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//h4[contains(text(), 'Current session has been terminated')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'This page isn’t working')]")
            || $this->http->FindSingleNode("//p[contains(text(), 'There is something wrong with the proxy server, or the address is incorrect.')]")
        ) {
            $this->DebugInfo = "bad proxy";
            $this->markProxyAsInvalid();

            throw new \CheckRetryNeededException(5, 0);
        }

        $xpathCheckPoints = "//div[contains(@class,'ui-bound__price') and not(contains(@class,'ui-bound__prices'))][count(./div)=3]/div[3]";

        if ($this->http->XPath->query($xpathCheckPoints)->length > 0
            && $this->http->XPath->query($xpathCheckPoints . "/text()[normalize-space()][1][contains(.,'points')]/ancestor::div[1]")->length === 0
        ) {
            throw new \CheckRetryNeededException(5, 0);
            // иногда на горячих теряются флаги - был дебаг, данная схема не сработала пока рестарт
            $script = "
                localStorage.setItem('isCashFastDone', 'false');
                localStorage.setItem('isPoints', 'true');
                localStorage.setItem('isPointsFastDone', 'true');
                sessionStorage.setItem('isCashFastDone', 'false');
                sessionStorage.setItem('isPoints', 'true');
                sessionStorage.setItem('isPointsFastDone', 'true');
                ";
            $this->logger->debug("[run script]");
            $this->logger->debug($script, ['pre' => true]);
            $this->driver->executeScript($script);

            $div = $this->waitForElement(\WebDriverBy::xpath("//div[normalize-space()='Search']"), 0);

            if ($div) {
                $div->click();
            }

            $this->clickSearchAndWaitLoad();

            $this->http->saveScreenshots = true;
            $this->saveResponse();

            throw new \CheckRetryNeededException(5, 0);
        }

        if (($msg = $this->http->FindSingleNode("//p[contains(.,'We could not find any flights for your chosen dates but if you click on Continue we will take you to the')]"))
            || ($msg = $this->http->FindSingleNode("//p[contains(.,\"Unfortunately, we didn't find any flights for your chosen date\")]"))
            || ($msg = $this->http->FindSingleNode("//p[contains(.,\"There are no flights on your chosen date\")]"))
            || ($msg = $this->http->FindSingleNode("//p[contains(.,\"It seems there are no available seats on the flights for your selected date\")]"))
            || ($msg = $this->http->FindSingleNode("//p[contains(.,\"It seems there are no available seats on the selected date\")]"))
            || ($msg = $this->http->FindSingleNode("//p[contains(normalize-space(),'All tickets have already been sold or are not available for the route and date you have selected.')]"))
        ) {
            $this->SetWarning($msg);

            return ["routes" => []];
        }

        $responseData = $dictionaryData = $paxData = null;

        /*        $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

                foreach ($requests as $n => $xhr) {
                    if (strpos($xhr->request->getUri(), 'manageMyBooking/translations/en') !== false) {
                        $dictionaryData = json_encode($xhr->response->getBody());
                    }

                    if (strpos($xhr->request->getUri(), '/service/extly/search/pax') !== false) {
                        $paxData = json_encode($xhr->response->getBody());
                    }

                    if (strpos($xhr->request->getUri(), '/extly/booking/search/points/outbound') !== false) {
                        $responseData = json_encode($xhr->response->getBody());
                    }
                }*/

        $dictionary = $this->http->JsonLog($dictionaryData, 1, true);

        if (!$dictionary) {
            $this->logger->info("[Form response]: " . $dictionaryData);
        }
        $data = $this->http->JsonLog($responseData, 1, true);

        if (!$data) {
            $this->logger->info("[Form response]: " . $responseData);
            $pax = $this->http->JsonLog($paxData, 1, true);

            if (isset($pax['data']) && $pax['data'] === []) {
                $this->SetWarning("Unfortunately, we didn't find any flights for your chosen date. Either the tickets have been sold out or they are no longer available on the same date and route. Would you like to try a different date?");

                return [];
            }
        }

        if (!$data || !isset($data['data'])) {
            // old version - sometimes helps
            $id = preg_replace(['/^"/', '/"$/'], "",
                stripcslashes($this->driver->executeScript("return localStorage.getItem('sessionId');")));

            if (empty($id)) {
                $id = preg_replace(['/^"/', '/"$/'], "",
                    stripcslashes($this->driver->executeScript("return sessionStorage.getItem('sessionId');")));
            }
            $this->logger->debug('sessionId: ' . $id);

            if (empty($id)) {
                return $this->parseRewardFlightsHtml();
            }

            $this->driver->executeScript('
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    localStorage.setItem("response", this.responseText);
                }
            };
            xhttp.open("GET", "https://booking.elal.com/ibe/rest/scopes/elal/modules/application,common,header,user,cart,formValidators,bookingPNR,errors,atc,bookingAncillaries,bookingFlights,bookingPassengers,search,manageMyBooking/translations/en", true);
            xhttp.setRequestHeader("Authorization", "Bearer ' . $id . '");
            xhttp.setRequestHeader("Content-Type", "application/json; charset=UTF-8");
            xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
            xhttp.send();
            ');

            //        xhttp.open("GET", "https://booking.elal.com/ibe/rest/scopes/elal/modules/bookingFlights,bookingAncillaries/translations/en", true);
            sleep(2);
            $response = $this->driver->executeScript("return localStorage.getItem('response');");
            $dictionary = $this->http->JsonLog($response, 1, true);

            if (!$dictionary) {
                $this->logger->info("[Form response]: " . $response);
            }

            $this->driver->executeScript('
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    localStorage.setItem("response", this.responseText);
                }
            };
            xhttp.open("GET", "https://booking.elal.com/bfm/service/extly/booking/search/points/outbound", true);
            xhttp.setRequestHeader("Authorization", "Bearer ' . $id . '");
            xhttp.setRequestHeader("Content-Type", "application/json; charset=UTF-8");
            xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
            xhttp.send();
            ');

            sleep(2);
            $response = $this->driver->executeScript("return localStorage.getItem('response');");
            $this->logger->info("[Form response]: " . $response);
            $data = $this->http->JsonLog($response, 1, true);

            if (!$data) {
                $this->logger->info("[Form response]: " . $response);
            }
        }

        if (!$data || !isset($data['data'])) {
            throw new \CheckRetryNeededException(5, 0);
            /*$this->sendNotification('parse html');

            return $this->parseRewardFlightsHtml();*/
        }
        $mealCodes = [];

        if (isset($dictionary[0]['application']['bound']['meals']['codes']) && is_array($dictionary[0]['application']['bound']['meals']['codes'])) {
            foreach ($dictionary[0]['application']['bound']['meals']['codes'] as $key => $benefit) {
                $mealCodes[$key] = str_replace($benefit['label'], ' (' . $key . ')', '');
            }
        }

        if (!isset($data['data']['trip']['outbound'])) {
            if (isset($data['errors']['0']['type']) && $data['errors']['0']['type'] === 'WARNING') {
                throw new \CheckRetryNeededException(5, 0);
            }
            $this->sendNotification('check format json // ZM');

            return $this->parseRewardFlightsHtml();
        }

        return ["routes" => $this->parseRewardFlightsJson($fields, $mealCodes, $data)];
    }

    private function checkResult(array $fields, ?bool $isRetry = false)
    {
        if (
            $this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//h4[contains(text(), 'Current session has been terminated')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'This page isn’t working')]")
            || $this->http->FindSingleNode("//p[contains(text(), 'There is something wrong with the proxy server, or the address is incorrect.')]")
        ) {
            $this->DebugInfo = "bad proxy";
            $this->markProxyAsInvalid();

            throw new \CheckRetryNeededException(5, 0);
        }

        $xpathCheckPoints = "//div[contains(@class,'ui-bound__price') and not(contains(@class,'ui-bound__prices'))][count(./div)=3]/div[3]";

        if ($this->http->XPath->query($xpathCheckPoints)->length > 0
            && $this->http->XPath->query($xpathCheckPoints . "/text()[normalize-space()][1][contains(.,'points')]/ancestor::div[1]")->length === 0
        ) {
            // иногда на горячих теряются флаги - был дебаг c повтором прописывания - данная схема не сработала пока рестарт
            throw new \CheckRetryNeededException(5, 0);
        }

        if (($msg = $this->http->FindSingleNode("//p[contains(.,'We could not find any flights for your chosen dates but if you click on Continue we will take you to the')]"))
            || ($msg = $this->http->FindSingleNode("//p[contains(.,\"Unfortunately, we didn't find any flights for your chosen date\")]"))
            || ($msg = $this->http->FindSingleNode("//p[contains(.,\"There are no flights on your chosen date\")]"))
            || ($msg = $this->http->FindSingleNode("//p[contains(.,\"It seems there are no available seats on the flights for your selected date\")]"))
            || ($msg = $this->http->FindSingleNode("//p[contains(.,\"It seems there are no available seats on the selected date\")]"))
            || ($msg = $this->http->FindSingleNode("//p[contains(normalize-space(),'All tickets have already been sold or are not available for the route and date you have selected.')]"))
        ) {
            $this->SetWarning($msg);

            return ["routes" => []];
        }

        $responseData = $dictionaryData = $paxData = null;
        /** @var \SeleniumDriver $seleniumDriver */
        /*        $seleniumDriver = $this->http->driver;

                $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

                foreach ($requests as $n => $xhr) {
                    if (strpos($xhr->request->getUri(), 'manageMyBooking/translations/en') !== false) {
                        $dictionaryData = json_encode($xhr->response->getBody());
                    }

                    if (strpos($xhr->request->getUri(), '/service/extly/search/pax') !== false) {
                        $paxData = json_encode($xhr->response->getBody());
                    }

                    if (strpos($xhr->request->getUri(), '/extly/booking/search/points/outbound') !== false) {
                        $responseData = json_encode($xhr->response->getBody());
                    }
                }

                $dictionary = $this->http->JsonLog($dictionaryData, 1, true);

                if (!$dictionary) {
                    $this->logger->info("[Form response]: " . $dictionaryData);
                }
                $data = $this->http->JsonLog($responseData, 1, true);

                if (!$data) {
                    $this->logger->info("[Form response]: " . $responseData);
                    $pax = $this->http->JsonLog($paxData, 1, true);

                    if (isset($pax['data']) && $pax['data'] === []) {
                        $this->SetWarning("Unfortunately, we didn't find any flights for your chosen date. Either the tickets have been sold out or they are no longer available on the same date and route. Would you like to try a different date?");

                        return [];
                    }
                }*/
        $data = null;

        if (!$data || !isset($data['data'])) {
            // old version - sometimes helps
            $id = preg_replace(['/^"/', '/"$/'], "",
                stripcslashes($this->driver->executeScript("return localStorage.getItem('sessionId');")));

            if (empty($id)) {
                $id = preg_replace(['/^"/', '/"$/'], "",
                    stripcslashes($this->driver->executeScript("return sessionStorage.getItem('sessionId');")));
            }
            $this->logger->debug('sessionId: ' . $id);

            if (empty($id)) {
                return $this->parseRewardFlightsHtml();
            }

            $this->driver->executeScript('
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    localStorage.setItem("response", this.responseText);
                }
            };
            xhttp.open("GET", "https://booking.elal.com/ibe/rest/scopes/elal/modules/application,common,header,user,cart,formValidators,bookingPNR,errors,atc,bookingAncillaries,bookingFlights,bookingPassengers,search,manageMyBooking/translations/en", true);
            xhttp.setRequestHeader("Authorization", "Bearer ' . $id . '");
            xhttp.setRequestHeader("Content-Type", "application/json; charset=UTF-8");
            xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
            xhttp.send();
            ');

            //        xhttp.open("GET", "https://booking.elal.com/ibe/rest/scopes/elal/modules/bookingFlights,bookingAncillaries/translations/en", true);
            sleep(3);
            $response = $this->driver->executeScript("return localStorage.getItem('response');");
            $dictionary = $this->http->JsonLog($response, 1, true);

            if (!$dictionary) {
                $this->logger->info("[Form response]: " . $response);
            }

            $this->driver->executeScript('
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    localStorage.setItem("response", this.responseText);
                }
            };
            xhttp.open("GET", "https://booking.elal.com/bfm/service/extly/booking/search/points/outbound", true);
            xhttp.setRequestHeader("Authorization", "Bearer ' . $id . '");
            xhttp.setRequestHeader("Content-Type", "application/json; charset=UTF-8");
            xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
            xhttp.send();
            ');

            sleep(3);
            $response = $this->driver->executeScript("return localStorage.getItem('response');");

            $data = $this->http->JsonLog($response, 1, true);

            if (!$data) {
                $this->logger->info("[Form response]: " . $response);
            }
            // check result
            $dateStrCheck = date('Y-m-d', $fields['DepDate']);

            if (
                !empty($response)
                && strpos($response, 'departureDate":"') !== false
                && strpos($response, 'departureDate":"' . $dateStrCheck) === false
                && isset($data['data']['trip']['outbound'])
            ) {
                // из-за горячих иногда бывает. ретрай не помогает - потому рестарт

                throw new \CheckRetryNeededException(5, 0);
            }
        }

        if (!$data || !isset($data['data'])) {
            throw new \CheckRetryNeededException(5, 0);
            /*$this->sendNotification('parse html');

            return $this->parseRewardFlightsHtml();*/
        }
        $mealCodes = [];

        if (isset($dictionary[0]['application']['bound']['meals']['codes']) && is_array($dictionary[0]['application']['bound']['meals']['codes'])) {
            foreach ($dictionary[0]['application']['bound']['meals']['codes'] as $key => $benefit) {
                $mealCodes[$key] = str_replace($benefit['label'], ' (' . $key . ')', '');
            }
        }

        if (!isset($data['data']['trip']['outbound'])) {
            if (isset($data['errors']['0']['type']) && $data['errors']['0']['type'] === 'WARNING') {
                if ($data['errors']['0']['desc'] === 'Not availability') {
//                    if ($btn = $this->waitForElement(\WebDriverBy::xpath("//button[normalize-space()='New search']"),
                    if ($btn = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'popin-')]//button"),
                        0)) {
                        $btn->click();
                    }
                    $this->saveResponse();
                    $this->SetWarning("It seems there are no available seats on the selected date");

                    return [];
                }

                throw new \CheckRetryNeededException(5, 0);
            }
            $this->sendNotification('check format json // ZM');

            return $this->parseRewardFlightsHtml();
        }

        return ["routes" => $this->parseRewardFlightsJson($fields, $mealCodes, $data)];
    }

    private function checkIfCalendarOpened()
    {
        $done = $this->waitForElement(\WebDriverBy::xpath("//button[@aria-label='Done'or @aria-label='search.calendar.submit']"),
            3);

        if ($done) {
            $this->logger->debug('done->click');

            try {
                $done->click();
            } catch (\UnknownServerException | \TimeOutException $e) {
                $this->logger->error("exception: " . $e->getMessage());
                $done = $this->waitForElement(\WebDriverBy::xpath("//button[@aria-label='Done' or @aria-label='search.calendar.submit']"),
                    0);

                if ($done) {
                    $done->click();
                }
            }
        }
    }

    private function checkingAirport($code, $id, $type)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice($type);
        $elem = $this->waitForElement(\WebDriverBy::xpath("//input[@id='{$id}']"), 0);

        if ($elem) {
            $elem->clear();
        }
        $elem->sendKeys($code);
        $elem->sendKeys(\WebDriverKeys::DOWN);
        $elem->sendKeys(\WebDriverKeys::ENTER);

        // if opened calendar - close it
        $this->checkIfCalendarOpened();

        // check airport
        $location = $this->waitForElement(\WebDriverBy::xpath("//label[@id='{$id}-describedby']"), 3);

        if (!$location) {
            $this->logger->error('something with check location (' . $type . ')');
            $this->http->saveScreenshots = true;
            $this->saveResponse();

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($location->getText() === "Where {$type}?") {
            $this->SetWarning('no flights ' . $type . ' ' . $code);

            return ['routes' => []];
        }
    }

    private function checkingAirportOld($code, $id, $type)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice($type);

        $script = "
            function triggerInputAirport(selector, enteredValue) {
                const input = document.querySelector(selector);
                var createEvent = function(name) {
                    var event = document.createEvent('Event');
                    event.initEvent(name, true, true);
                    return event;
                }
                input.value = enteredValue;
                input.dispatchEvent(createEvent('change'));
                input.dispatchEvent(createEvent('input'));
            }
            triggerInputAirport('#%s', '%s');
            ";
        $scriptCheck = "return $(document.querySelector('#%s')).length;";

        $this->logger->debug('run script: triggerInputAirport ' . $type);

        try {
            $value = $this->driver->executeScript(sprintf($scriptCheck, $id));
            $this->logger->debug(var_export($value, true));
            $has = ($value == 1);

            if ($has) {
                $this->driver->executeScript(sprintf($script, $id, $code));
            } else {
                $elem = $this->waitForElement(\WebDriverBy::xpath("//input[@id='{$id}']"), 0);

                if ($elem) {
                    $elem->clear();
                }
                $this->driver->executeScript(sprintf($script, $id, $code));
            }
        } catch (\UnknownServerException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\StaleElementReferenceException $e) {
            $this->sendNotification('check parse // ZM');
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        }

        try {
            $airportCheck = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'search-input-location__item')]/div[contains(@class,'search-input-location__code')][normalize-space()='{$code}']"),
                10);
        } catch (\StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            // TODO for debug
            $this->http->saveScreenshots = true;
            $this->saveResponse();
            $reAirportCheck = true;

            $airportCheck = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'search-input-location__item')]/div[contains(@class,'search-input-location__code')][normalize-space()='$code']"),
                0);
        }

        if ($airportCheck) {
            $this->logger->debug('check code');
            $this->driver->executeScript(
                "
            $('div.search-input-location__item').find('div:contains(\"{$code}\")').click();
            "
            );
        } else {
            try {
                $this->saveResponse();
            } catch (\UnexpectedJavascriptException $e) {
                $this->logger->error("UnexpectedJavascriptException exception on saveResponse: " . $e->getMessage());
                $this->saveResponse();
            }

//            if (isset($reAirportCheck) || isset($this->reloadStart)) { // на рестарте новой странице может врать.. пока дебаг
            if ($this->routeValid) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $this->SetWarning('no flights ' . $type . ' ' . $code);

            return ['routes' => []];
        }
        // if opened calendar - close it
        $this->checkIfCalendarOpened();

        // check airport
        $location = $this->waitForElement(\WebDriverBy::xpath("//label[@id='{$id}-describedby']"), 3);

        if (!$location) {
            $this->logger->error('something with check location (' . $type . ')');
            $this->http->saveScreenshots = true;
            $this->saveResponse();

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($location->getText() === 'Where from?') {
            $this->SetWarning('no flights ' . $type . ' ' . $code);

            return ['routes' => []];
        }
    }

    private function loadStartPage()
    {
        $this->logger->notice(__METHOD__);

        $isHot = false;

        try {
            $startLoad = time();
            $this->http->removeCookies();
            sleep(1);
            $this->http->GetURL("https://www.elal.com/en/OtherCountries/Pages/default.aspx");

            if (time() - $startLoad > 60) {
                $this->logger->debug('site takes a long time to load');
                $this->driver->executeScript("window.stop();");
//                throw new \CheckException('site takes a long time to load', ACCOUNT_ENGINE_ERROR);
            }

            sleep(1);

            if ($this->http->FindPreg('/(?:page isn’t working|There is no Internet connection|This site can’t be reached|Check your proxy settings or contact your network administrator to|This site can’t provide a secure connection)/ims')) {
                throw new \CheckRetryNeededException(5, 0);
            }

            if ($msg = $this->http->FindSingleNode('//p[contains(normalize-space(),"Due to maintenance, This EL AL\'s page is not available at the moment.")]')) {
                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }

            $startTime = time();
            $time = time() - $startTime;
            $sleep = 20;

            while ($time < $sleep) {
                sleep(1);
                $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");
                $elem = $this->http->FindSingleNode("//div[contains(@class,'search-form')]//form/div[@id='searchFormTabset']");

                if ($elem) {
                    break;
                }
                $this->logger->notice("check errors");
                $this->saveResponse();
                $time = time() - $startTime;
            }

            if (isset($elem) && !$isHot) {
                sleep(1);
                $script = "
                localStorage.removeItem('lastExternalReferrerTime');
                localStorage.removeItem('tgt:tlm:0');
                localStorage.removeItem('lastExternalReferrer');
                localStorage.removeItem('__rtbh.lid');
                localStorage.setItem('taboola global:last-external-referrer', 'other');

                localStorage.setItem('isCashFastDone', 'false');
                localStorage.setItem('isPoints', 'true');
                localStorage.setItem('isPointsFastDone', 'true');
                sessionStorage.setItem('hasArena', 'false');
                sessionStorage.setItem('isCashFastDone', 'true');
                sessionStorage.setItem('isCheckboxChecked', 'false');
                sessionStorage.setItem('isLyUser', 'false');
                sessionStorage.setItem('isPoints', 'true');
                sessionStorage.setItem('isPointsFastDone', 'true');
                ";
                $this->logger->debug("[run script]");
                $this->logger->debug($script, ['pre' => true]);
                $this->driver->executeScript($script);
//                $this->http->GetURL("https://www.elal.com/en/OtherCountries/Pages/default.aspx");
            }
        } catch (\TimeOutException | \WebDriverCurlException $e) {
            $this->logger->error($e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        $btn = $this->waitForElement(\WebDriverBy::xpath("//button[@type='submit' and (contains(text(),'Find me a flight') or contains(@aria-label,'search'))]"),
            10);

        if (!$btn) {
            $this->saveResponse();

            throw new \CheckRetryNeededException(5, 0);
        }
    }

    private function loadStartPageOld()
    {
        $this->logger->notice(__METHOD__);
        $isHot = false;

        try {
            $startLoad = time();

            $this->logger->warning('[currentUrl]: ' . $this->http->currentUrl());

            if (strpos($this->http->currentUrl(), 'https://booking.elal.com/booking/flights') !== false
                || $this->http->currentUrl() === 'https://www.elal.com/en/OtherCountries/Pages/default.aspx'
            ) {
                $this->saveResponse();

                if ($btnFromLastSearchNotif = $this->waitForElement(\WebDriverBy::xpath("//popin-container//button"),
                    0)) {
                    $btnFromLastSearchNotif->click();
                    $this->saveResponse();
                }

                try {
                    $this->logger->debug('scroll Top throw script');
                    $this->driver->executeScript("window.scrollTo(0, 0);");
                } catch (\UnexpectedJavascriptException $e) {
                    $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage());
                }
                $this->saveResponse();
                $isHot = true;

                $div = $this->waitForElement(\WebDriverBy::xpath("//div[normalize-space()='Search']"), 0);

                if ($div) {
                    $div->click();
                }
            }

            if ($this->http->currentUrl() !== "https://www.elal.com/en/OtherCountries/Pages/default.aspx") {
                $this->http->GetURL("https://www.elal.com/en/OtherCountries/Pages/default.aspx");
            }

            if ($isHot) {
                try {
                    $this->logger->debug('scroll Top throw script');
                    $this->driver->executeScript("window.scrollTo(0, 0);");
                } catch (\UnexpectedJavascriptException $e) {
                    $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage());
                }
                $this->saveResponse();

                return;
            }

            if (time() - $startLoad > 60) {
                $this->reloadStart = true;
                $startLoad = time();
                $this->http->removeCookies();
                sleep(1);
                $this->http->GetURL("https://www.elal.com/en/OtherCountries/Pages/default.aspx");

                if (time() - $startLoad > 60) {
                    throw new \CheckException('site takes a long time to load', ACCOUNT_ENGINE_ERROR);
                }
            }
            sleep(1);

            if ($this->http->FindPreg('/(?:page isn’t working|There is no Internet connection|This site can’t be reached|Check your proxy settings or contact your network administrator to|This site can’t provide a secure connection)/ims')) {
                $parseTime = time() - $this->requestDateTime;

                if ($parseTime < 60) {
                    $attCnt = 5;
                } else {
                    $attCnt = 3;
                }

                throw new \CheckRetryNeededException($attCnt, 0);
            }

            if ($msg = $this->http->FindSingleNode('//p[contains(normalize-space(),"Due to maintenance, This EL AL\'s page is not available at the moment.")]')) {
                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }

            $startTime = time();
            $time = time() - $startTime;
            $sleep = 20;

            while ($time < $sleep) {
                sleep(1);
                $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");
                $elem = $this->http->FindSingleNode("//div[contains(@class,'search-form')]//form/div[@id='searchFormTabset']");

                if ($elem) {
                    break;
                }
                $this->logger->notice("check errors");
                $this->saveResponse();
                $time = time() - $startTime;
            }

            if (isset($elem) && !$isHot) {
                sleep(1);
                $script = "
                localStorage.setItem('isCashFastDone', 'false');
                localStorage.setItem('isPoints', 'true');
                localStorage.setItem('isPointsFastDone', 'true');
                sessionStorage.setItem('isCashFastDone', 'false');
                sessionStorage.setItem('isPoints', 'true');
                sessionStorage.setItem('isPointsFastDone', 'true');
                ";
                $this->logger->debug("[run script]");
                $this->logger->debug($script, ['pre' => true]);
                $this->driver->executeScript($script);
                $this->http->GetURL("https://www.elal.com/en/OtherCountries/Pages/default.aspx");
            }
        } catch (\TimeOutException | \WebDriverCurlException $e) {
            $this->logger->error($e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }
    }

    private function parseRewardFlightsHtml(): array
    {
        $this->logger->notice(__METHOD__);
        $routes = [];
        $this->http->FilterHTML = false;
        $xpath = "//div[contains(@class,'ui-panel__wrapper') and not(contains(@class,'ui-panel__wrapper__hide'))]/div[contains(@class,'ui-panel__content')]/div";
        $roots = $this->http->XPath->query($xpath);
        $this->logger->debug("found " . $roots->length . " routes");
//        $this->http->saveScreenshots = true; // сильно увеличивает время парсинга, включать лучше точечно для дебага

        if ($roots->length > 0) {
            foreach (range(1, $roots->length) as $num) {
                $this->logger->debug('parse route#' . $num);

//                $offers = $this->http->XPath->query($xpathOffers = "({$xpath})[{$num}]//div[@role='document']");
//                $offers = $this->http->XPath->query($xpathOffers = "({$xpath})[{$num}]//div[@role='radio']");
                $offers = $this->http->XPath->query($xpathOffers = "({$xpath})[{$num}]//div[contains(@class,'bound__prices')]/div");
                $this->logger->debug('xpathOffers: ' . $xpathOffers);
                $offersData = [];

                foreach ($offers as $offer) {
                    $offersData[] = [
                        'name'     => $this->http->FindSingleNode("./div[1]", $offer),
                        'payments' => $this->http->FindSingleNode("./div[3]", $offer),
                        'seats'    => $this->http->FindSingleNode("./div[4]", $offer),
                    ];
                }
                $this->logger->debug(var_export($offersData, true), ['pre' => true]);

                try {
                    $popin = $this->waitForElement(\WebDriverBy::xpath("(//div[contains(@class,'popin-bound-detail__flight')][starts-with(normalize-space(),'Time:') or contains(normalize-space(),'local time')])[1]"),
                        0);

                    if ($popin) {
                        $this->logger->debug('execute script: hide popup');
                        $this->driver->executeScript("document.querySelectorAll('popin-container>div')[0].setAttribute('style','display:none')");
                    }
                } catch (\StaleElementReferenceException $e) {
                    $this->logger->error($e->getMessage());
                }

                try {
                    $this->logger->debug('scroll throw script');
                    $this->driver->executeScript("
                        document.evaluate(\"(//a[contains(.,'Flight details')])[{$num}]\", document, null, XPathResult.ANY_TYPE, null ).iterateNext().scrollIntoView();
                        window.scrollTo(0, window.pageYOffset-100);
                        ");
                } catch (\UnexpectedJavascriptException $e) {
                    $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage());
                }

                $details = $this->waitForElement(\WebDriverBy::xpath("({$xpath})[{$num}]//a[contains(.,'Flight details')]"),
                    10);

                if (!$details) {
                    $this->logger->debug('try throw script');

                    try {
                        $this->driver->executeScript("document.evaluate(\"(//a[contains(.,'Flight details')])[{$num}]\", document, null, XPathResult.ANY_TYPE, null ).iterateNext().click()");
                    } catch (\UnexpectedJavascriptException $e) {
                        $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage());
                    }
                    $popin = $this->waitForElement(\WebDriverBy::xpath("(//div[contains(@class,'popin-bound-detail__flight')][starts-with(normalize-space(),'Time:') or contains(normalize-space(),'local time')])[1]"),
                        3);

                    if (!$popin) {
                        $this->http->saveScreenshots = true;
                        $this->saveResponse();
//                        $this->http->saveScreenshots = false;

                        throw new \CheckException('Flight details not found', ACCOUNT_ENGINE_ERROR);
                    }
                } else {
                    try {
                        $details->click();
                    } catch (\ElementNotVisibleException $e) {
                        $this->logger->error('ElementNotVisibleException: ' . $e->getMessage());

                        $this->http->saveScreenshots = true;
                        $this->saveResponse();
//                        $this->http->saveScreenshots = false;

                        throw new \CheckException('Flight details not visible', ACCOUNT_ENGINE_ERROR);
                    }
                }
//                $linkNum = $num - 1;
//                $this->driver->executeScript("$('a:contains(\"Flight details\")').eq({$linkNum}).click();");
//                $this->driver->executeScript("[...document.querySelectorAll('a')].filter(a => a.textContent.includes(\"Flight details\"))[{$linkNum}].click");
                $popin = $this->waitForElement(\WebDriverBy::xpath("(//div[contains(@class,'popin-bound-detail__flight')][starts-with(normalize-space(),'Time:') or starts-with(normalize-space(),'Departure time') or contains(normalize-space(),'local time')])[1]"),
                    3);

                try {
                    if (!$popin) {
//                        $this->sendNotification('check hide/show popin // ZM');
                        $this->logger->debug('execute script: show popup');
                        $this->driver->executeScript("document.querySelectorAll('popin-container>div')[0].setAttribute('style','display:block')");
                    }
                } catch (\StaleElementReferenceException $e) {
                    $this->logger->error($e->getMessage());
                }

                $this->saveResponse();
                $segments = $this->http->XPath->query("//popin-container/div[1]//div[contains(@class,'popin-bound-detail__flight-segment')]");

                if ($segments->length !== 1) {
                    $this->sendNotification('1+ segments // ZM');
                }
                $segs = [];

                foreach ($segments as $numRootSeg => $rootSegments) {
                    $numSeg = $numRootSeg + 1;
                    $depRoot = $this->http->XPath->query("./div[2]/div[1][contains(.,'Aircraft')]", $rootSegments);

                    if ($depRoot->length === 1) {
                        $depRoot = $depRoot->item(0);
                    } else {
                        $this->sendNotification('dep Data in segment // ZM');

                        throw new \CheckException('new format', ACCOUNT_ENGINE_ERROR);
                    }
                    $arrRoot = $this->http->XPath->query("./div[2]/div[1][contains(.,'Aircraft')]/following-sibling::div[1]",
                        $rootSegments);

                    if ($arrRoot->length === 1) {
                        $arrRoot = $arrRoot->item(0);
                    } else {
                        $this->sendNotification('arr Data in segment // ZM');

                        throw new \CheckException('new format', ACCOUNT_ENGINE_ERROR);
                    }
                    $dateDep = strtotime($this->http->FindSingleNode("./div[1]", $depRoot));
                    $dateArr = strtotime($this->http->FindSingleNode("./div[1]", $arrRoot));
                    $segs[] = [
                        'departure' => [
                            'date' => date('Y-m-d H:i',
                                strtotime(
                                    $this->http->FindSingleNode(
                                        "./div[4]/div[1]/p[1][contains(.,'Time') or contains(.,'Departure time')]/descendant::text()[normalize-space()!=''][2]",
                                        $depRoot
                                    )
                                    ?? $this->http->FindSingleNode(
                                        "./div[4]/div[1]/p[1][contains(.,'local time')]/descendant::text()[normalize-space()!=''][1]",
                                        $depRoot
                                    ),
//                                    ??$this->http->FindSingleNode(
//                                        ".//div[starts-with(normalize-space(text()),'Departure')]/following-sibling::div[1]",
//                                        $depRoot
//                                    ),
                                    $dateDep
                                )
                            ),
                            'airport'  => $this->http->FindSingleNode("./div[3]", $depRoot, false, "/\-\s+([A-Z]{3})$/"),
                            'terminal' => $this->http->FindSingleNode(
                                "./div[4]/div[1]/p[2][contains(.,'Terminal')]/descendant::text()[normalize-space()!=''][2]",
                                $depRoot
                            ),
                        ],
                        'arrival' => [
                            'date' => date('Y-m-d H:i',
                                strtotime(
                                    $this->http->FindSingleNode(
                                        "./div[4]/div[1]/p[1][contains(.,'Time') or contains(.,'Arrival time')]/descendant::text()[normalize-space()!=''][2]",
                                        $arrRoot
                                    ) ?? $this->http->FindSingleNode(
                                        "./div[4]/div[1]/p[1][contains(.,'local time')]/descendant::text()[normalize-space()!=''][1]",
                                        $arrRoot
                                    ),
                                    $dateArr
                                )
                            ),
                            'airport'  => $this->http->FindSingleNode("./div[3]", $arrRoot, false, "/\-\s+([A-Z]{3})$/"),
                            'terminal' => $this->http->FindSingleNode(
                                "./div[4]/div[1]/p[2][contains(.,'Terminal')]/descendant::text()[normalize-space()!=''][2]",
                                $arrRoot
                            ),
                        ],
                        'flight' => [
                            $this->http->FindSingleNode("(//div[contains(@class,'popin-bound-detail__duration__flight-number')])[{$numSeg}]",
                                null, false, "/^(\w{2}\d+)\b/"),
                        ],
                        'airline' => $this->http->FindSingleNode("(//div[contains(@class,'popin-bound-detail__duration__flight-number')])[{$numSeg}]",
                            null, false, "/^(\w{2})\d+\b/"),
                        'meal' => $this->http->FindSingleNode(
                            "./div[4]/div[2]/p[1][contains(.,'Meals')]/descendant::text()[normalize-space()!=''][2]",
                            $depRoot
                        ),
                        'aircraft' => $this->http->FindSingleNode(
                            "./div[4]/div[2]/p[2][contains(.,'Aircraft')]/descendant::text()[normalize-space()!=''][2]",
                            $depRoot
                        ),
                    ];
                }

                foreach ($offersData as $numOffer => $offer) {
                    $this->logger->debug("offer #" . $numOffer);

                    if ($offer['payments'] === '-') {
                        $this->logger->debug('no payments for ' . $offer['name']);

                        continue;
                    }

                    if (($res = $this->http->FindPregAll("/([\d.,]+)\s*points\s*\+\s*(\D*)([\d.,]+)(\D*)$/",
                            $offer['payments'], PREG_SET_ORDER, false,
                            false)) && isset($res[0])) {
                        $cabinText = strtolower($offer['name']);
                        $points = $res[0][1];
                        $tax = $res[0][3];
                        $currency = trim(!empty($res[0][2]) ? $res[0][2] : $res[0][4]);

                        if ($currency === '$') {
                            $currency = "USD";
                        } elseif ($currency === '฿') {
                            $currency = "THB";
                        } else {
                            $this->currency($currency);
                        }

                        foreach ($segs as &$seg) {
                            $seg['cabin'] = $this->getCabin($cabinText);
                        }
                        $res = [
                            'num_stops'   => 0,
                            'redemptions' => [
                                'miles'   => (int) PriceHelper::cost($points),
                                'program' => $this->AccountFields['ProviderCode'],
                            ],
                            'payments' => [
                                'currency' => $currency,
                                'taxes'    => PriceHelper::cost($tax),
                            ],
                            'classOfService' => $offer['name'],
                            'connections'    => $segs,
                        ];

                        if (!empty($offer['seats'])) {
                            $res['tickets'] = $this->http->FindPreg("/(\d+)\s*left at this price/",
                                false, $offer['seats']);

                            if (empty($res['tickets'])) {
                                $this->sendNotification('check tickets // ZM');
                            }
                        }
                        $this->logger->debug(var_export($res, true), ['pre' => true]);
                        $routes[] = $res;
                    } else {
                        if (($res = $this->http->FindPregAll("/^(\D*)([\d.,]+)(\D*)$/", $offer['payments'],
                                PREG_SET_ORDER, false, false))
                            && isset($res[0]) && $this->attempt < 2) {
                            $this->sendNotification("something wrong with payments. check restart // ZM");

                            throw new \CheckRetryNeededException(5, 0, 'wrong page. without points', ACCOUNT_ENGINE_ERROR);
                        }
                        $this->sendNotification('something wrong with payments data // ZM');

                        throw new \CheckException('wrong page. without points', ACCOUNT_ENGINE_ERROR);
                    }
                }

                try {
                    $this->logger->debug('execute script: close popup');
                    $this->driver->executeScript("document.querySelector('div.popin-root__close').click();");
                } catch (\StaleElementReferenceException $e) {
                    $this->logger->error($e->getMessage());
                }
                /*
                                try {
                                    $close = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'popin-root__close')]"), 3);
                                } catch (\StaleElementReferenceException $e) {
                                    $this->logger->error($e->getMessage());
                                    $this->saveResponse();
                                }

                                if (isset($close)) {
                                    try {
                                        $close->click();
                                        sleep(2);
                                    } catch (\StaleElementReferenceException $e) {
                                        $this->logger->error($e->getMessage());

                                        if ($close = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'popin-root__close')]"),
                                            0)) {
                                            $this->sendNotification('can\'t close popin-root // ZM');

                                            break;
                                        }
                                    }
                                } else {
                                    break;
                                }
                                */
            }
        }

        try {
            $this->saveResponse();
        } catch (\StaleElementReferenceException $e) {
            $this->logger->error($e->getMessage());
        }

        if (empty($routes)) {
            throw new \CheckException('something went wrong', ACCOUNT_ENGINE_ERROR);
        }

        return ["routes" => $routes];
    }

    private function parseRewardFlightsJson(array $fields, ?array $mealCodes, $data): array
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("ParseReward [" . date('y-m-d',
                $fields['DepDate']) . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);

        $indirectBounds = [];

        if (!empty($data['data']['trip']['outbound']['indirectBounds']['bounds'])) {
            foreach ($data['data']['trip']['outbound']['indirectBounds']['bounds'] as $ib) {
                if (!empty($ib['fares'])) {
                    $indirectBounds[] = $ib;
                }
            }

            if (!empty($indirectBounds)) {
                $this->sendNotification('check indirectBounds // ZM');
            }
        }
        $bounds = array_merge($indirectBounds,
            $data['data']['trip']['outbound']['directBounds']['bounds']);
        $this->logger->debug('Fount ' . count($bounds) . ' routes');
        $routes = [];

        foreach ($bounds as $numRoute => $bound) {
            $this->logger->debug('numRoute #' . $numRoute);
            $segments = [];
            $stops = -1;

            foreach ($bound['segments'] as $numSeg => $boundSeg) {
                $this->logger->debug('numSeg #' . $numSeg);
                $stops++;
                $segStops = 0;

                if (count($boundSeg['stops']) > 0) {
                    $segStops = count($boundSeg['stops']) - 1;
                    $stops += $segStops;
                }
                $meals = [];

                foreach ($boundSeg['mealTypes'] as $type) {
                    $meals[] = $mealCodes[$type] ?? null;
                }
                $meals = array_filter($meals);

                if (!empty($meals)) {
                    $meal = implode(', ', $meals);
                }

                if ($this->http->FindPreg("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])$/", false, $boundSeg['airline']['name'])) {
                    $airline = $boundSeg['airline']['name'];
                } else {
                    $airline = $boundSeg['carrier'];
                }
                $seg = [
                    'num_stops' => $segStops,
                    'departure' => [
                        'airport'  => $boundSeg['departureAirport']['code'],
                        'date'     => date('Y-m-d H:i', strtotime(substr($boundSeg['departureDate'], 0, 16))),
                        'terminal' => $boundSeg['departureTerminal']['name'] ?? null,
                    ],
                    'arrival' => [
                        'airport'  => $boundSeg['arrivalAirport']['code'],
                        'date'     => date('Y-m-d H:i', strtotime(substr($boundSeg['arrivalDate'], 0, 16))),
                        'terminal' => $boundSeg['arrivalTerminal']['name'] ?? null,
                    ],
                    'meal'     => $meal ?? null,
                    'flight'   => [$boundSeg['carrier'] . $boundSeg['flightNumber']],
                    'airline'  => $airline,
                    'aircraft' => $boundSeg['aircraftType'],
                    'times'    => ['flight' => null, 'layover' => null],
                ];
                $segments[] = $seg;
            }

            foreach ($bound['fares'] as $fare) {
                if (!empty($fare['mixedCabins'])) {
                    $this->sendNotification('mixedCabins // ZM');
                }
                $cabin = !empty($fare['bookingClassName']) ? $this->getCabin($fare['bookingClassName']) : null;
                $segments_ = $segments;

                foreach ($segments as $k => $seg) {
                    $segments_[$k]['cabin'] = $cabin;
                }
                $res = [
                    'distance'  => null,
                    'num_stops' => $stops,
                    'times'     => [
                        'flight'  => null,
                        'layover' => null,
                    ],
                    'redemptions' => [
                        'miles'   => $fare['netPrice']['points']['amount'],
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $fare['netPrice']['points']['taxes']['currencyCode'],
                        'taxes'    => null,
                        'fees'     => $fare['netPrice']['points']['taxes']['amount'],
                    ],
                    'tickets'        => $fare['nbSeatLeft'],
                    'classOfService' => strtoupper($fare['bookingClassName']),
                    'connections'    => $segments_,
                ];
                $this->logger->debug(var_export($res, true), ['pre' => true]);
                $routes[] = $res;
            }
        }

        return $routes;
    }

    private function getCabin(string $in)
    {
        switch ($in) {
            case 'economy':
                return 'economy';

            case 'premium':
                return 'premiumEconomy';

            case 'business':
                return 'business';

            default:
                $this->sendNotification('unknown cabin ' . $in . ' /ZM');

                return null;
        }
    }

    private function validRoute($token, $fields): bool
    {
        $this->logger->notice(__METHOD__);
        // если не 100% невалидный, то идем работать на странице "ручками"
//        $this->routeChecked = true;

        $origin = \Cache::getInstance()->get('ra_israel_origins_new');
        $origin = false;

        if (!is_array($origin)) {
            $tt =
                '
            var xhttp = new XMLHttpRequest();
            xhttp.withCredentials = true;
            xhttp.open("GET", "https://booking.elal.com/bfm/service/extly/search/locations/origin?market=US_LY&tripType=O&language=en", false);
            xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
            xhttp.setRequestHeader("Authorization", "Bearer ' . $token . '");
            xhttp.setRequestHeader("Content-Type", "application/json; charset=UTF-8");
            xhttp.setRequestHeader("Accept-Encoding", "gzip, deflate, br");
            xhttp.setRequestHeader("Origin", "https://www.elal.com");
            xhttp.setRequestHeader("Referer", "https://www.elal.com/");
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {

                    localStorage.setItem("retData",this.responseText);

                }
            };
            xhttp.send();
            v = localStorage.getItem("retData");
            return v;
';
            $this->logger->debug($tt);

            try {
                $returnData = $this->driver->executeScript($tt);
                $origin = $this->http->JsonLog($returnData, 1, true);
            } catch (\WebDriverException $e) {
                $this->logger->error($e->getMessage());
                $origin = null;
            }

            if (isset($origin['data']['nearby'], $origin['data']['origins'])) {
                \Cache::getInstance()->set('ra_israel_origins_new', $origin, 60 * 60 * 24);
            }
        }

        if (empty($origin) || !isset($origin['data']['nearby']) || !isset($origin['data']['origins'])) {
            $this->routeChecked = false;

            return true;
        }
        $codesOrigin = [];

        foreach ($origin['data']['nearby'] as $nearby) {
            $codesOrigin[] = $nearby['code'];

            if ($nearby['code'] === $fields['DepCode']) {
                $this->DepData = $nearby;
            }
        }

        foreach ($origin['data']['origins'] as $origin) {
            $codesOrigin[] = $origin['code'];

            if ($origin['code'] === $fields['DepCode']) {
                $this->DepData = $origin;
            }
        }
        $codesOrigin = array_unique($codesOrigin);
        sort($codesOrigin);
//        $this->logger->debug("[origins]:");
//        $this->logger->debug(var_export($codesOrigin, true));
        $this->logger->debug("[DepData]:");
        $this->logger->debug(var_export($this->DepData, true), ['pre' => true]);

        if (!empty($codesOrigin) && !in_array($fields['DepCode'], $codesOrigin)) {
            $this->SetWarning('no flights from ' . $fields['DepCode']);

            return false;
        }

        $destination = \Cache::getInstance()->get('ra_israel_from_new1' . $fields['DepCode']);
        $destination = false;

        if (!is_array($destination)) {
            $tt =
                '
            var xhttp = new XMLHttpRequest();
            xhttp.withCredentials = true;
            xhttp.open("GET", "https://booking.elal.com/bfm/service/extly/search/locations/destination?market=US_LY&tripType=O&origin=' . $fields['DepCode'] . '&language=en", false);
            xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
            xhttp.setRequestHeader("Authorization", "Bearer ' . $token . '");
            xhttp.setRequestHeader("Content-Type", "application/json; charset=UTF-8");
            xhttp.setRequestHeader("Accept-Encoding", "gzip, deflate, br");
            xhttp.setRequestHeader("Origin", "https://www.elal.com");
            xhttp.setRequestHeader("Referer", "https://www.elal.com/");
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {

                    localStorage.setItem("retData",this.responseText);

                }
            };
            xhttp.send();
            v = localStorage.getItem("retData");
            return v;
';
            $this->logger->debug($tt);

            try {
                $returnData = $this->driver->executeScript($tt);
                $destination = $this->http->JsonLog($returnData, 1, true);
            } catch (\WebDriverException $e) {
                $this->logger->error($e->getMessage());
                $destination = null;
            }

            if (isset($destination['data']['destinations'])) {
                \Cache::getInstance()->set('ra_israel_from_new1' . $fields['DepCode'], $destination, 60 * 60 * 24);
            }
        }

        if (empty($destination) || !isset($destination['data']['destinations'])) {
            $this->routeChecked = false;

            return true;
        }
        $codesDestination = [];

        foreach ($destination['data']['destinations'] as $destination) {
            $codesDestination[] = $destination['code'];

            if ($destination['code'] === $fields['ArrCode']) {
                $this->ArrData = $destination;
            }
        }
        $codesDestination = array_unique($codesDestination);
        sort($codesDestination);
//        $this->logger->debug("[destinations]:");
//        $this->logger->debug(var_export($codesDestination, true));
        $this->logger->debug("[ArrData]:");
        $this->logger->debug(var_export($this->ArrData, true), ['pre' => true]);

        if (!empty($codesDestination) && !in_array($fields['ArrCode'], $codesDestination)) {
            $this->SetWarning('no flights from ' . $fields['DepCode'] . ' to ' . $fields['ArrCode']);

            return false;
        }

        if (!empty($codesDestination) && in_array($fields['ArrCode'], $codesDestination)
            && !empty($codesOrigin) && in_array($fields['DepCode'], $codesOrigin)
        ) {
            $this->routeValid = true;
        }

        $this->routeChecked = true;

        return true;
    }

    private function getUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function clickSearchAndWaitLoad()
    {
        $btn = $this->waitForElement(\WebDriverBy::xpath("//button[@type='submit' and (contains(text(),'Find me a flight') or contains(@aria-label,'search'))]"),
            10);

        if (!$btn) {
            throw new \CheckRetryNeededException(5, 0);
        }
        $btn->click();

        $this->saveResponse();

        sleep(2);

        $xpathLoading = "//div[normalize-space()='Loading flights...' or starts-with(normalize-space(),'Searching for flights')]";

        try {
            $loading = $this->waitForElement(\WebDriverBy::xpath($xpathLoading), 5);
        } catch (\StaleElementReferenceException $e) {
            $this->logger->error($e->getMessage());
            sleep(2);
            $loading = $this->waitForElement(\WebDriverBy::xpath($xpathLoading), 5);
        }
        // wait load
        if ($loading) {
            try {
                $this->waitFor(
                    function () use ($xpathLoading) {
                        return !$this->waitForElement(\WebDriverBy::xpath($xpathLoading),
                            0);
                    },
                    20
                );
            } catch (\StaleElementReferenceException $e) {
                $this->logger->error($e->getMessage());
            }

            if ($this->waitForElement(\WebDriverBy::xpath($xpathLoading), 0)) {
                $this->http->saveScreenshots = true;
                $this->saveResponse();

                throw new \CheckRetryNeededException(5, 0);
            }
        }
        $this->logger->debug('Loaded flights');
        $this->waitFor(
            function () {
                return $this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'panel__content')]"), 0)
                    || $this->waitForElement(\WebDriverBy::id("outbound-destination-location-input-message"), 0)
                    || $this->driver->findElement(\WebDriverBy::xpath("//p[contains(.,'There are no flights on your chosen date')]"))
                    || $this->driver->findElement(\WebDriverBy::xpath("//p[contains(.,'It seems there are no available seats')]"))
                    || $this->driver->findElement(\WebDriverBy::xpath("//h4[contains(.,'Current session has been terminated')]"))
                    || $this->driver->findElement(\WebDriverBy::xpath("//p[contains(.,'We could not find any flights for your chosen dates but if you click on Continue we will take you to the')]"))
                    || $this->driver->findElement(\WebDriverBy::xpath("//p[contains(.,\"Unfortunately, we didn't find any flights for your chosen date\")]"))
                    || $this->driver->findElement(\WebDriverBy::xpath("//p[contains(normalize-space(),'All tickets have already been sold or are not available for the route and date you have selected.')]"));
            },
            20
        );
    }
}
