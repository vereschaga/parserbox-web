<?php

namespace AwardWallet\Engine\jetblue\RewardAvailability;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class Parser extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;
    use \PriceTools;
    public $isRewardAvailability = true;
    private $browser;
    private $responseData;

    public static function getRASearchLinks(): array
    {
        return ['https://www.jetblue.com/' => 'search page'];
    }

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();
        // no accounts, independent search
        $this->KeepState = false;
        $this->keepCookies(false);

        $this->http->setKeepUserAgent(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");

        $this->http->setRandomUserAgent();

        $array = ['uk', 'es', 'us', 'pt'];

        if ($this->attempt < 2) {
            $targeting = $array[array_rand($array)];

            if ($targeting === 'us' && $this->AccountFields['ParseMode'] === 'awardwallet') {
                $this->setProxyMount();
            } else {
                $this->setProxyGoProxies(null, $targeting, null, null, 'https://www.jetblue.com/');
            }
        } else {
            $targeting = $array[array_rand($array)];
            $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, $targeting);
        }
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
        $arrCurrencies = ['USD'];

        return [
            'supportedCurrencies'      => $arrCurrencies,
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'USD',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->http->FilterHTML = false;
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));
        $routes = [];

        if ($fields['DepDate'] > strtotime('+332 day')) {
            $this->SetWarning('The requested departure date is too late.');

            return [];
        }

        $this->http->RetryCount = 0;

        if (!$this->validRoute($fields)) {
            return ['routes' => []];
        }
        $this->http->RetryCount = 2;

        $supportedCurrencies = $this->getRewardAvailabilitySettings()['supportedCurrencies'];

        if (!in_array($fields['Currencies'][0], $supportedCurrencies)) {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        try {
            $url = $this->seleniumSearchFlights($fields);
        } catch (\ErrorException $e) {
            $this->logger->error('ErrorException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        if (null !== $this->responseData) {
            if (is_array($this->responseData) && empty($this->responseData)) {
                return [];
            }
            $msg = $this->http->FindSingleNode("//jb-dialog//jb-dialog-content/p");

            if (!empty($msg)) {
                if (strpos($msg, "Unfortunately we can't proceed with this selection. Please reselect") !== false) {
                    if ($this->attempt !== 4) {
                        throw new \CheckRetryNeededException(5, 0);
                    }

                    throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
                }

                if (strpos($msg, "The dates for your search cannot be in the past. Please start a new search.") !== false) {
                    $this->SetWarning($msg);

                    return [];
                }
                $this->logger->error("DEBUG MSG: " . $msg);
            }

            return ['routes' => $this->parseJsonDataStorage($this->responseData)];
        }

        if (isset($url) && strpos($url, '/booking/flights?from=') !== false) {
            if ($msd = $this->http->FindSingleNode('//h2[normalize-space()="No flights have been found for your search criteria"]')) {
                $this->SetWarning($msd);

                return [];
            }

            throw new \CheckException('new format', ACCOUNT_ENGINE_ERROR);
        }

        if (isset($url) && strpos($url, '/best-fare-finder?nff=true') !== false
            && ($msg = $this->http->FindPreg("/(There are no flights available on the date\(s\) you selected, but you can check more options on our Best Fare Finder.)/"))) {
            $this->SetWarning($msg);

            return [];
        }

        if (!isset($url)) {
            throw new \CheckException('form not submitted', ACCOUNT_ENGINE_ERROR);
        }

        if (stripos($url, 'AirSearchErrorForward') !== false) {
            $errorCode = $this->http->FindPreg("/errorCode=(.+)/", false, $url);
            $this->logger->error($errorCode);

            $msg = $this->http->FindSingleNode("//td[@id='errorText']/p[contains(.,'No flights have been')][1]");
            $this->logger->error($msg);

            if (in_array($errorCode, ['NO_FLIGHTS_FOUND', 'EXTERNAL_ERROR']) || !empty($msg)) {
                $this->SetWarning($msg);

                return ['routes' => []];
            }
            $this->sendNotification('check errorCode // ZM');

            throw new \CheckException('unknown errorCode', ACCOUNT_ENGINE_ERROR);
        } elseif (stripos($url, 'AirFareFamiliesFlexibleForward') !== false) {
            $awards = [];
            $awardsList = $this->http->XPath->query("//table//tr/th[contains(@class, 'colCost')]");
            $this->logger->debug("Total Award Type: {$awardsList->length}");

            foreach ($awardsList as $root) {
                $name = $this->http->FindSingleNode(".//span[@class='ffTitle']", $root);
                $class = implode(' ',
                    array_filter(array_map("trim", explode(' ', trim($root->getAttribute('class'))))));

                if (!empty($name) && !empty($class)) {
                    $awards[$class] = $name;
                }
            }

            if (empty($awards)) {
                if ($msg = $this->http->FindSingleNode("//p[contains(.,'These deals seem to be on a brief vacay, but should be back any minute. Please try again soon')]")) {
                    if ($this->attempt < 2) {
                        $this->logger->error($msg);

                        throw new \CheckRetryNeededException(5, 0);
                    }
                    $this->SetWarning($msg);

                    return [];
                }

                throw new \CheckException('Total Award Type empty', ACCOUNT_ENGINE_ERROR);
            }
            $this->logger->debug(var_export($awards, true));

            $data = $this->http->FindPreg('/tdGroupData\[0\]\s*=\s*(\{.+?\});/s');

            if (empty($data)) {
                throw new \CheckException('tdGroupData empty', ACCOUNT_ENGINE_ERROR);
            }
            $data = str_replace("\'", '\"', $data);
            $data = join("\n", array_values((array) $this->http->JsonLog($data, 0)));
            $this->http->SetBody($data);
            //print_r($data);

            $nodes = $this->http->XPath->query("//tbody[@id]");
            $this->logger->debug("Total routes: " . $nodes->length);

            if ($nodes->length == 0) {
                throw new \CheckException('Total routes empty', ACCOUNT_ENGINE_ERROR);
            }

            foreach ($nodes as $numRote => $item) {
                if ((time() - $this->requestDateTime) > 110 && !empty($routes)) {
                    $skippedCnt = $nodes->length - $numRote;
                    $this->logger->notice('skip last ' . $skippedCnt . ' routes');

                    break;
                }
                // Connections
                $tr = $this->http->XPath->query("tr", $item);
                $this->logger->debug("numRote " . $numRote);
                $this->logger->debug("Total connections: " . $tr->length);
                $connections = [];
                $stop = 0;

                foreach ($tr as $root) {
                    $layover = $this->http->FindSingleNode("td[@class='colDuration']//span[@class='landTime']", $root,
                        false, '/Layover:\s*(.+)/');

                    if ($layover) {
                        $stop++;
                    }

                    $aircraft = $this->http->FindSingleNode("td[@class='colDepart']//span[@class='equipType']//span[contains(text(),'Aircraft')]/following-sibling::text()",
                        $root);
                    $depCode = $this->http->FindSingleNode("td[@class='colDepart']//div[@class='location']", $root,
                        false, "/\(([A-Z]{3})\)/");

                    if (empty($depCode)) {
                        $depCode = $this->http->FindSingleNode("td[@class='colDepart']//div[@class='location']/descendant::text()[normalize-space()!=''][1]", $root,
                            false, "/^([A-Z]{3})$/");
                    }
                    $arrCode = $this->http->FindSingleNode("td[@class='colArrive']//div[@class='location']", $root,
                        false, "/\(([A-Z]{3})\)/");

                    if (empty($arrCode)) {
                        $arrCode = $this->http->FindSingleNode("td[@class='colArrive']//div[@class='location']/descendant::text()[normalize-space()!=''][1]", $root,
                            false, "/^([A-Z]{3})$/");
                    }

                    $detailsUrl = $this->http->FindSingleNode(".//a[starts-with(@onclick,'showFlightDetailsPopUp')]/@onclick",
                        $root, false, "/showFlightDetailsPopUp\('(.+?)'\)/");

                    $checkDate = $depTime = $arrTime = $airline = $aircraft = $flightNumber = null;

                    $depTimeString = $this->http->FindSingleNode("td[@class='colDepart']//div[@class='time']/descendant::text()[normalize-space()!=''][1]", $root);
                    $depDateString = $this->http->FindSingleNode("td[@class='colDepart']//div[@class='time']/span[1]", $root, false, "/^([+-]\s*\d+)/");

                    if ($depDateString) {
                        $this->logger->debug($depDateString);
                        $depDate = strtotime($depDateString . ' days', $fields['DepDate']);
                    } else {
                        $depDate = $fields['DepDate'];
                    }
                    $airline = $this->http->FindPreg("/airlineCode=(\w+)/", false, $detailsUrl);
                    $flightNumber = $this->http->FindPreg("/flightNumber=(\d+)/", false, $detailsUrl);
                    $flight = $airline . $flightNumber;
                    $day = $this->http->FindPreg("/departureDay=(\d+)/", false, $detailsUrl);
                    $month = $this->http->FindPreg("/departureMonth=(\d+)/", false, $detailsUrl);
                    $year = $this->http->FindPreg("/departureYear=(\d+)/", false, $detailsUrl);

                    if ($day && $month && $year) {
                        $month++;
                        $checkDate = strtotime(sprintf("%04d-%02d-%02d", $year, $month, $day));

                        if ($checkDate !== $depDate) {
                            $this->logger->debug($checkDate);
                            $this->sendNotification('check depDate // ZM');
                        }
                    }
                    $depTime = strtotime($depTimeString, $depDate);

                    $arrTimeString = $this->http->FindSingleNode("td[@class='colArrive']//div[@class='time']/descendant::text()[normalize-space()!=''][1]", $root);
                    $arrDateString = $this->http->FindSingleNode("td[@class='colArrive']//div[@class='time']/span", $root, false, "/^([+-]\s*\d+)/");

                    if ($arrDateString) {
                        $this->logger->debug($arrDateString);
                        $arrDate = strtotime($arrDateString . ' days', $fields['DepDate']);
                    } else {
                        $arrDate = $fields['DepDate'];
                    }
                    $arrTime = strtotime($arrTimeString, $arrDate);
                    $aircraft = $this->http->FindSingleNode("./td[@class='colDepart']/descendant::span[@class='equipType'][1]", $root);

                    $operator = $this->http->FindPreg('/&operatingAirlineCode=(\w{2})&/', false, $detailsUrl);

                    if (!$depTime || !$arrTime || !$airline || !$aircraft || !$flightNumber) {
                        $this->browser->RetryCount = 0;

                        $vsid = $this->http->FindPreg("/vsid=([\-\w]+)$/", false, $detailsUrl);
                        //                    $this->browser->PostURL("https://book.jetblue.com/B6/{$detailsUrl}", ['popupAction' => true]);
                        $headers = [
                            'Accept'       => '* /*', // TODO!!!
                            'ADRUM'        => 'isAjax:true',
                            'Content-Type' => 'application/x-www-form-urlencoded',
                            'Referer'      => 'https://book.jetblue.com/B6/AirFareFamiliesFlexibleForward.do?vsid=' . $vsid,
                        ];
                        $this->browser->PostURL("https://book.jetblue.com/B6/{$detailsUrl}", 'popupAction=true',
                            $headers, 10);

                        if ($this->browser->FindPreg("/Oops! Your session has timed out. Please start your search again/")) {
                            if (intdiv($nodes->length, 2) < $numRote
                                || (time() - $this->requestDateTime) > 90
                            ) {
                                $skippedCnt = $nodes->length - $numRote;
                                $this->logger->error('Your session has timed out');
                                $this->logger->notice('skip last ' . $skippedCnt . ' routes');

                                break 2;
                            }

                            throw new \CheckRetryNeededException(5, 0);
                            // TODO может есть возможность продлить сессию? ((
                        }

                        if ($this->browser->FindPreg("/External error occurred. Please try again later/")) {
                            // debug
                            $this->browser->PostURL("https://book.jetblue.com/B6/{$detailsUrl}", 'popupAction=true',
                                $headers, 10);
                            $data = $this->browser->FindPreg("/\{\s*contentData:\s*\{(UpdateCont:'.+?)'(?:,webstatisticsCall:|\}\s*\})/s",
                                false,
                                $this->browser->Response['body']);
                            $data = '{' . str_replace("UpdateCont:'", '"UpdateCont":"',
                                    str_replace("\'", '\"', $data)) . '"}';
                            $data = $this->http->JsonLog($data, 0);

                            if ($this->browser->FindPreg("/External error occurred. Please try again later./")) {
                                if (isset($onceContinue1)) {
                                    if (intdiv($nodes->length, 2) < $numRote
                                        || (time() - $this->requestDateTime) > 90
                                    ) {
                                        $skippedCnt = $nodes->length - $numRote;
                                        $this->logger->error('no data');
                                        $this->logger->notice('skip last ' . $skippedCnt . ' routes');

                                        break 2;
                                    }

                                    throw new \CheckRetryNeededException(5, 0);
                                }
                                $this->sendNotification('check retry 1 //ZM');
                                $onceContinue1 = true;
                                $this->logger->error('try to skip, and check');

                                continue;
                            }
                            // +1 helped
                            $this->sendNotification('check retry 1, helped //ZM');
                        }

                        if ($this->browser->FindPreg("/We were unable to process your request, external error occurred, please try again/")) {
                            $this->browser->PostURL("https://book.jetblue.com/B6/{$detailsUrl}", 'popupAction=true',
                                $headers, 10);
                            $this->sendNotification('check retry2 //ZM');

                            if ($this->browser->FindPreg("/We were unable to process your request, external error occurred, please try again/")) {
                                if (intdiv($nodes->length, 2) < $numRote
                                    || (time() - $this->requestDateTime) > 90
                                ) {
                                    $skippedCnt = $nodes->length - $numRote;
                                    $this->logger->error('no data');
                                    $this->logger->notice('skip last ' . $skippedCnt . ' routes');

                                    break 2;
                                }

                                throw new \CheckRetryNeededException(5, 0);
                            }
                        }

                        if (strpos($this->browser->currentUrl(),
                                'https://www.jetblue.com/mx/book-error?') !== false) {
                            $this->browser->PostURL("https://book.jetblue.com/B6/{$detailsUrl}", 'popupAction=true',
                                $headers, 10);
                            $this->sendNotification('check retry3 //ZM');
                            // NB: if (book-error?) retry help
                            // un_jtt_application_platform=iphone - not work
                            if (strpos($this->browser->currentUrl(),
                                    'https://www.jetblue.com/mx/book-error?') !== false) {
                                if (intdiv($nodes->length, 2) < $numRote
                                    || (time() - $this->requestDateTime) > 90
                                ) {
                                    $skippedCnt = $nodes->length - $numRote;
                                    $this->logger->error('no data');
                                    $this->logger->notice('skip last ' . $skippedCnt . ' routes');

                                    break 2;
                                }

                                throw new \CheckRetryNeededException(5, 0);
                            }
                        }
                        $data = $this->browser->FindPreg("/\{\s*contentData:\s*\{(UpdateCont:'.+?)'(?:,webstatisticsCall:|\}\s*\})/s",
                            false,
                            $this->browser->Response['body']);
                        $data = '{' . str_replace("UpdateCont:'", '"UpdateCont":"',
                                str_replace("\'", '\"', $data)) . '"}';
                        $data = $this->http->JsonLog($data, 0);

                        if (empty($data->UpdateCont)) {
                            $this->sendNotification('check connections // MI');

                            continue;
                        }
                        $this->browser->SetBody($data->UpdateCont);
                        $flight = $this->browser->FindSingleNode("//td[@class='colFlight']");
                        $depDate = $this->browser->FindSingleNode("//td[@class='colDepart']//span[@class='date']");
                        $depTime = $this->browser->FindSingleNode("//td[@class='colDepart']//span[@class='time']");
                        $arrDate = $this->browser->FindSingleNode("//td[@class='colArrive']//span[@class='date']");
                        $arrTime = $this->browser->FindSingleNode("//td[@class='colArrive']//span[@class='time']");
                        $duration = $this->browser->FindSingleNode("//td[@class='colTimeTotal']");
                        $distance = $this->browser->FindSingleNode("//td[@class='colMiles']/div[normalize-space()!='']");
                        $depTime = strtotime($depTime, strtotime($depDate));
                        $arrTime = strtotime($arrTime, strtotime($arrDate));
                    }

                    $connections[] = [
                        'departure' => [
                            'date'     => date('Y-m-d H:i', $depTime),
                            'dateTime' => $depTime,
                            'airport'  => $depCode,
                        ],
                        'arrival' => [
                            'date'     => date('Y-m-d H:i', $arrTime),
                            'dateTime' => $arrTime,
                            'airport'  => $arrCode,
                        ],
                        'meal'       => null,
                        'cabin'      => null,
                        'fare_class' => null,
                        'flight'     => [str_replace(' ', '', $flight)],
                        'airline'    => $this->http->FindPreg('/^([A-Z\d]{2})\s*\d{1,4}/', false, $flight),
                        'operator'   => $operator,
                        'distance'   => $distance ?? null,
                        'aircraft'   => $aircraft,
                        'times'      => [
                            'flight'  => null,
                            'layover' => null,
                        ],
                    ];
                    sleep(rand(0, 1));
                }

                // Prices
                //table/tbody[@id]/tr/td[contains(@class, 'colCost')]
                $prices = $this->http->XPath->query("tr/td[contains(@class, 'colCost')]", $item);
                $this->logger->debug("Total prices: " . $prices->length);

                $timesData = [
                    'num_stops' => $stop,
                    'times'     => null,
                ];

                foreach ($prices as $root) {
                    //print_r($root);
                    $ptsValue = $this->http->FindSingleNode(".//span[@class='ptsValue']",
                        $root);
                    $taxesValue = $this->http->FindSingleNode(".//span[@class='taxesValue']",
                        $root);
                    $seats = $this->http->FindSingleNode(".//text()[contains(.,'left at this price')]/preceding-sibling::b",
                        $root, false, '/^(\d+)\s*seat/');

                    if (!isset($ptsValue, $taxesValue)) {
                        $this->logger->notice('Skip: not price');

                        continue;
                    }

                    $class = implode(' ',
                        array_filter(array_map("trim", explode(' ', trim($root->getAttribute('class'))))));
                    $headData = $timesData + [
                        'distance'    => null,
                        'redemptions' => [
                            'miles'   => preg_replace('/[^\d]+/', '', $ptsValue),
                            'program' => $this->AccountFields['ProviderCode'],
                        ],
                        'payments' => [
                            'currency' => $this->currency($taxesValue),
                            'taxes'    => PriceHelper::cost(trim(preg_replace('/[^\d.,\s]/', '', $taxesValue))),
                            'fees'     => null,
                        ],
                        'tickets'    => $seats,
                        'award_type' => $this->getAwardTypeForClass($awards, $class),
                    ];

                    // Cabins
                    $cabins = $this->getCabinFields(false);

                    foreach ($connections as $key => $value) {
                        if (isset($cabins[$headData['award_type']])) {
                            $connections[$key]['cabin'] = $cabins[$headData['award_type']];
                        } else {
                            $this->sendNotification('AR check cabins // MI');
                        }
                    }
                    $headData['connections'] = $connections;

                    $routes[] = $headData;
                    $this->logger->debug('Parsed data:');
                    $this->logger->debug(var_export($headData, true), ['pre' => true]);
                }
            }
        } else {
            throw new \CheckException('something went wrong', ACCOUNT_ENGINE_ERROR);
        }

        return ['routes' => $routes];
    }

    private function getCabinFields($onlyKeys = true): array
    {
        $cabins = [
            'Blue Basic' => 'economy',
            'Blue'       => 'economy',
            'Blue Plus'  => 'economy',
            'Blue Extra' => 'economy',
            'Mint'       => 'business',
        ];

        if ($onlyKeys) {
            return array_keys($cabins);
        }

        return $cabins;
    }

    private function getFareTypeByFareCode(string $code): ?string
    {
//        https://www.jetblue.com/resp-magnoliapublic/.rest/jetblue/v4/dcInterlineMigration/customDelivery/dcInterlineMigration
        $list = [
            'DN' => 'Blue Basic',
            'AN' => 'Blue',
            'A1' => 'Economy',
            'A2' => 'Economy',
            'AR' => 'Blue',
            'CN' => 'Blue Plus',
            'CR' => 'Blue Plus',
            'GN' => 'Blue Extra',
            'GR' => 'Blue Extra',
            'J1' => 'Business',
            'MN' => 'Mint',
            'M1' => 'Business Lie',
            'MR' => 'Mint',
        ];

        if (isset($list[$code])) {
            return str_replace(' ', '_', strtoupper($list[$code]));
        }

        if ($code === 'J2') {
            return $code;
        }
        $this->sendnotification('check code ' . $code . ' //ZM');

        return null;
    }

    private function getCabinClassFields($code)
    {
        $cabins = [
            'BLUE_BASIC'          => 'economy',
            'BLUE'                => 'economy',
            'BLUE_PLUS'           => 'economy',
            'ECONOMY_REDEMPTION'  => 'economy', // operated by Qatar
            'BUSINESS_REDEMPTION' => 'business', // operated by Qatar
            'BLUE_EXTRA'          => 'economy',
            'MINT'                => 'business',
        ];

        return $cabins[$code];
    }

    private function getAwardTypeForClass($wards, $class)
    {
        $class = trim($class);

        if (isset($wards[$class])) {
            return $wards[$class];
        }
        $this->sendNotification('check Award Type // ZM');

        return null;
    }

    private function seleniumSearchFlights($fields)
    {
        $url = null;
        $this->responseData = null;
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->keepCookies(false);
            $evaluateMouse = false;

            switch (rand(0, 2)) {
                case 0:
                    $selenium->useChromePuppeteer();

                    break;

                case 1:
                    $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);

                    break;

                case 2:
                    $selenium->useFirefoxPlaywright();

                    break;

                case 3:
                    $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
//                    $this->setKeepProfile(true);

                    break;

                case 4:
                    $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
//                    $this->setKeepProfile(true);

                    break;
            }

//            $selenium->http->saveScreenshots = true;
            $resolutions = [
                //                [1152, 864],
                //                [1280, 720],
                [1280, 768],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $this->logger->debug("set screen resolution: " . implode('x', $chosenResolution));
            $this->setScreenResolution($chosenResolution);
//            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->usePacFile(false);
//            $selenium->seleniumRequest->setHotPool(self::class); // old
            $selenium->seleniumRequest->setHotSessionPool(self::class, $this->AccountFields['ProviderCode']);

            try {
                $selenium->http->start();
                $selenium->Start();
            } catch (\TimeOutException $e) {
                $this->markProxyAsInvalid();
                $this->logger->error("exception: " . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            } catch (\WebDriverException | \WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverCurlException $e) {
                // Reached error page: about:neterror?e=dnsNotFound&u=https%3A//s3.amazonaws.com/awardwallet-public/healthcheck.html
                $this->logger->error("exception: " . $e->getMessage());
                $this->logger->error($e->getTraceAsString(), ['HtmlEncode' => true]);

                throw new \CheckRetryNeededException(5, 0);
            }
            $current = $selenium->http->currentUrl();

            if (isset($current)) {
                $this->logger->warning($current);
                $isHot = $this->isHot = strpos($current, 'jetblue.com') !== false;
            }

            try {
                if (!$isHot) {
                    $selenium->http->GetURL("https://www.jetblue.com/");
                    $cookieFrame = $selenium->waitForElement(\WebDriverBy::xpath("//iframe[contains(@src,'trustarc')and starts-with(@id,'pop-frame')]"),
                        4);

                    if ($cookieFrame) {
                        $this->logger->info('close popUp');
                        $selenium->driver->executeScript("document.querySelectorAll('div[id^=\"pop-\"]').forEach(function(e){ e.setAttribute('style','display:none') })");
                        $this->savePageToLogs($selenium);
                    }
                } else {
                    $selenium->driver->executeScript("sessionStorage.removeItem('fares');");
                }
            } catch (\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverCurlException $e) {
                $this->logger->error('WebDriverCurlException: ' . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }

            if ($selenium->waitForElement(\WebDriverBy::xpath("
                //div[contains(normalize-space(text()),'Our website is currently undergoing maintenance')]
                | // h2[contains(normalize-space(),'It’s so fly to see you.')]
              "), 5)) {
                $this->savePageToLogs($selenium);
                $msg = $this->http->FindSingleNode("//div[contains(normalize-space(text()),'Our website is currently undergoing maintenance')]");

                if ($msg) {
                    throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
                }
            }

            //new search TODO DEBUG
            $dateStr = date('Y-m-d', $fields['DepDate']);
//            $url = "https://www.jetblue.com/booking/flights?from={$fields['DepCode']}&to={$fields['ArrCode']}&depart={$dateStr}&isMultiCity=false&noOfRoute=1&lang=en&adults={$fields['Adults']}&children=0&infants=0&sharedMarket=false&roundTripFaresFlag=false&usePoints=true";
            $url = "https://www.jetblue.com/booking/flights?adults={$fields['Adults']}&children=0&infants=0&redemPoint=true&roundTripFaresFlag=false&from={$fields['DepCode']}&to={$fields['ArrCode']}&depart={$dateStr}";
            $responseData = $this->interceptResponseDataStorage($url, $selenium, 5);

            try {
                $this->savePageToLogs($selenium);
            } catch (\WebDriverException | \Facebook\WebDriver\Exception\WebDriverException
            | \WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverCurlException
            | \InvalidSessionIdException | \Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                // otherwise killed watchdog
                throw new \CheckRetryNeededException(5, 0);
            }

            if (($msg = $this->http->FindSingleNode("//h2[contains(.,'No flights have been')]"))
                || ($msg = $this->http->FindSingleNode("//div[count(.//div)=0][contains(.,'There are no flights available on the date(s) you selected')]"))
                || ($msg = $this->http->FindSingleNode("//jb-dialog//jb-dialog-content/p[contains(.,'There are no flights available on the date(s) you selected')]"))
                || ($msg = $this->http->FindSingleNode("//jb-dialog//jb-dialog-content[contains(.,'There are no flights available on the date(s) you selected')]"))
            ) {
                $this->SetWarning($msg);
                $this->responseData = [];

                return;
            }

            if (!empty($responseData)) {
                $data = $this->http->JsonLog($responseData, 1);

                if (isset($data->httpStatus)) {
                    if ($data->httpStatus == 500) {
                        throw new \CheckException($data->message, ACCOUNT_PROVIDER_ERROR);
                    }
                }

                if (isset($data->departingFlights->dategroup)) {
                    $this->responseData = $data;
                }

                return;
            }

            $this->logger->notice('can\'t get responseData. try old version of parse');
            $selenium->driver->manage()->deleteAllCookies();
            // old version
            $selenium->http->GetURL('https://www.jetblue.com/');

            try {
                $frame = $selenium->waitForElement(\WebDriverBy::xpath("//iframe[starts-with(@id,'pop-frame')]"), 3);
            } catch (\StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException: " . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }

            if ($frame) {
                $this->logger->info('close popUp');
                $selenium->driver->executeScript("document.querySelectorAll('div[id^=\"pop-\"]').forEach(function(e){ e.setAttribute('style','display:none') })");
            }
            $this->savePageToLogs($selenium);

            $button = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(.,'Search flights')]"), 20);

            if (!$button) {
                $this->sendNotification('check no btn // ZM');
                $this->savePageToLogs($selenium);
                $frame = $selenium->waitForElement(\WebDriverBy::xpath("//iframe[starts-with(@id,'pop-frame')]"), 0);

                if ($frame) {
                    throw new \CheckRetryNeededException(5, 0);
                    $cookie = $selenium->waitForElement(\WebDriverBy::xpath("//a[@class='call']"), 0);

                    if ($cookie) {
                        $cookie->click();
                    }
                    $selenium->driver->switchTo()->defaultContent();
                    $button = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(.,'Search flights')]"),
                        20);

                    if (!$button) {
                        $this->savePageToLogs($selenium);
                        $frame = $selenium->waitForElement(\WebDriverBy::xpath("//iframe[starts-with(@id,'pop-frame')]"),
                            0);

                        if ($frame) {
                            $selenium->driver->executeScript("document.querySelectorAll('div[id^=\"pop-\"]').forEach(function(e){ e.setAttribute('style','display:none') })");
                            sleep(2);
                        }
                    }
                }
                $button = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(.,'Search flights')]"), 20);
            }

            if (!$button) {
                $this->savePageToLogs($selenium);

                return null;
            }
            $script = "
                localStorage.setItem('truste.eu.cookie.notice_preferences', '{\"name\":\"truste.eu.cookie.notice_preferences\",\"value\":\"2:\",\"path\":\"/\",\"expires\":1664876779109}');
                localStorage.setItem('truste.eu.cookie.notice_gdpr_prefs', '{\"name\":\"truste.eu.cookie.notice_gdpr_prefs\",\"value\":\"0,1,2:\",\"path\":\"/\",\"expires\":1664876779113}');
                localStorage.setItem('truste.eu.cookie.cmapi_cookie_privacy', '{\"name\":\"truste.eu.cookie.cmapi_cookie_privacy\",\"value\":\"permit 1,2,3\",\"path\":\"/\",\"expires\":1664876779121}');
                localStorage.setItem('truste.eu.cookie.cmapi_gtm_bl', '{\"name\":\"truste.eu.cookie.cmapi_gtm_bl\",\"value\":\"\",\"path\":\"/\",\"expires\":1664876779120}');
                localStorage.setItem('booker', '{\"recentAirSearches\":[{\"originCode\":[\"{$fields['DepCode']}\"],\"destinationCode\":[\"{$fields['ArrCode']}\"],\"departDate\":[\"" . date('Y-m-d', $fields['DepDate']) . "\"],\"returnDate\":[],\"intineraryType\":\"OW\",\"ADT\":1,\"CHD\":0,\"INF\":0,\"fare\":\"tb\"}],\"recentVacationSearches\":[],\"recentLocations\":{\"origins\":[[\"{$fields['DepCode']}\"],[],[],[]],\"destinations\":[[\"{$fields['ArrCode']}\"],[],[],[]]}}');
            ";
            $this->logger->debug("[run script]");
            $this->logger->debug($script, ['pre' => true]);
            $selenium->driver->executeScript($script);

            $url = $this->runSearch($selenium, $fields, $evaluateMouse);

            if (!$url) {
                $url = $this->runSearch($selenium, $fields, $evaluateMouse);

                if (!$url) {
                    $url = $this->runSearch($selenium, $fields, $evaluateMouse);

                    if (!$url) {
                        return null;
                    }
                }
            }

            if ($this->http->FindSingleNode("//button[contains(.,'Search flights')]")) {
                throw new \CheckException('not submitted, same page', ACCOUNT_ENGINE_ERROR);
            }

            if ($this->http->FindSingleNode("//p[contains(.,'We are sorry, the session you are using has expired.')]")) {
                $here = $selenium->waitForElement(\WebDriverBy::xpath("//p[starts-with(normalize-space(),'You can begin your search again by')]/a[contains(.,'clicking here')]"), 0);
                $this->sendNotification('check session expired on start // ZM');

                if ($here) {
                    $here->click();
                    $url = $this->runSearch($selenium, $fields, $evaluateMouse);

                    if (!$url) {
                        return null;
                    }

                    if ($this->http->FindSingleNode("//p[contains(.,'We are sorry, the session you are using has expired.')]")) {
                        throw new \CheckRetryNeededException(5, 0);
                    }
                } else {
                    throw new \CheckRetryNeededException(5, 0);
                }
            }

            $this->browser = new \HttpBrowser("none", new \CurlDriver());
            $this->http->brotherBrowser($this->browser);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
                $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
            $this->savePageToLogs($selenium);
            $this->logger->debug('[url]: ' . $selenium->http->currentUrl());
            $url = $selenium->http->currentUrl();
        } catch (\ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        }// catch (ScriptTimeoutException $e)
        catch (\UnknownServerException | \SessionNotCreatedException | \TimeOutException | \Facebook\WebDriver\Exception\UnknownErrorException | \TypeError $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } catch (\NoSuchWindowException $e) {
            $this->logger->error("NoSuchWindowException exception: " . $e->getMessage());
            $retry = true;
        } catch (\WebDriverCurlException | \WebDriverException $e) {
            $this->logger->error("WebDriverCurlException exception: " . $e->getMessage());
            $retry = true;
        } catch (\Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
            $this->logger->error('InvalidSessionIdException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } finally {
            if (isset($this->responseData)) {
                $this->logger->notice('Data ok, saving session');
                $selenium->keepSession(true);
            }
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if (isset($retry) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new \CheckRetryNeededException(5, 0);
            }
        }

        return $url;
    }

    private function runSearch($selenium, $fields, $evaluateMouse)
    {
        try {
            $script = "
localStorage.setItem('booker', '{\"recentAirSearches\":[{\"originCode\":[\"{$fields['DepCode']}\"],\"destinationCode\":[\"{$fields['ArrCode']}\"],\"departDate\":[\"" . date('Y-m-d', $fields['DepDate']) . "\"],\"returnDate\":[],\"intineraryType\":\"OW\",\"ADT\":1,\"CHD\":0,\"INF\":0,\"fare\":\"tb\"}],\"recentVacationSearches\":[],\"recentLocations\":{\"origins\":[[\"{$fields['DepCode']}\"],[],[],[]],\"destinations\":[[\"{$fields['ArrCode']}\"],[],[],[]]}}');
";
            $this->logger->debug("[run script]");
            $this->logger->debug($script, ['pre' => true]);
            $selenium->driver->executeScript($script);
            $selenium->http->GetURL('https://www.jetblue.com/');
        } catch (\TimeOutException $e) {
            $this->logger->error($e->getMessage());
            $selenium->driver->executeScript('window.stop();');
        }
        $button = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(.,'Search flights')]"), 20);

        if (!$button) {
            $this->savePageToLogs($selenium);

            return null;
        }
        /*
        $cookieFrame = $selenium->waitForElement(\WebDriverBy::xpath("//iframe[contains(@src,'trustarc')and starts-with(@id,'pop-frame')]"), 2);

        if ($cookieFrame) {
            $selenium->driver->switchTo()->frame($cookieFrame);
            $accept = $selenium->waitForElement(\WebDriverBy::xpath("//a[normalize-space()='Accept All Cookies']"), 3);

            if ($accept) {
                $accept->click();
                sleep(1);
            }
            $selenium->driver->switchTo()->defaultContent();
            $this->savePageToLogs($selenium);
        }
        */
        //$button = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(.,'Search flights')]"), 10);
        /*
        $dateDep = date('Y-m-d', $fields['DepDate']);
        $script = "
                var form = document.querySelector('form[action=\"https://book.jetblue.com/B6/webqtrip.html\"]');
                if (form) {
                    var elem = form.querySelector('input[name=\"pos\"]');
                    if(elem)
                        elem.value='JETBLUEREDEMPTION_US';
                    elem = form.querySelector('input[name=\"journeySpan\"]');
                    if(elem)
                        elem.value='OW';
                    elem = form.querySelector('input[name=\"origin\"]');
                    if(elem)
                        elem.value='{$fields['DepCode']}';
                    elem = form.querySelector('input[name=\"destination\"]');
                    if(elem)
                        elem.value='{$fields['ArrCode']}';
                    elem = form.querySelector('input[name=\"numAdults\"]');
                    if(elem)
                        elem.value='{$fields['Adults']}';
                    elem = form.querySelector('input[name=\"jbBookerCurrency-getaway\"]');
                    if(elem)
                        elem.value='tb';
                    elem = form.querySelector('input[name=\"jbBookerCurrency-flights\"]');
                    if(elem)
                        elem.value='tb';
                    elem = form.querySelector('input[name=\"fareFamily\"]');
                    if(elem)
                        elem.value='TRUEBLUE';
                    elem = form.querySelector('input[name=\"fareDisplay\"]');
                    if(elem)
                        elem.value='points';
                    elem = form.querySelector('input[name=\"departureDate\"]');
                    if(elem)
                        elem.value='{$dateDep}';
                }
            ";
        */
        /*
        $this->logger->debug("[run script]");
        $script = "
function contains(selector, text) {
    var elements = document.querySelectorAll(selector);
    return [].filter.call(elements, function (element) {
        return RegExp(text).test(element.textContent);
    });
}

form = document.querySelector('#first-tab form');
form.querySelector('jb-select[formcontrolname=\"tripType\"]').querySelector('button').click();
elem = contains('jb-select-option span', 'One-way')
if (elem.length === 1)
    elem[0].click();
elem = contains('jb-checkbox', 'Use TrueBlue points')
if (elem.length === 2)
    elem[0].click();
";
        $this->logger->debug($script, ['pre' => true]);
        $selenium->driver->executeScript($script);
        $elem = $selenium->waitForElement(\WebDriverBy::xpath("//dot-city-selector-v2[@data-qaid='fromAirport']//input"), 0);
        $elem->clear();
        $elem->sendKeys($fields['DepCode']);
        $elem->sendKeys(\WebDriverKeys::ENTER);
        sleep(1);
        $elem = $selenium->waitForElement(\WebDriverBy::xpath("//dot-city-selector-v2[@data-qaid='toAirport']//input"), 0);
        $elem->clear();
        $elem->sendKeys($fields['ArrCode']);
        $elem->sendKeys(\WebDriverKeys::ENTER);
        sleep(1);
        $elem = $selenium->waitForElement(\WebDriverBy::xpath("//input[starts-with(@id,'jb-date-picker-input-id')]"), 0);
        $elem->clear();
        $elem->sendKeys(date('D M d', $fields['DepDate']));
        $elem->sendKeys(\WebDriverKeys::ENTER);
        */

        // TODO Adults

        sleep(rand(1, 3));
        $this->savePageToLogs($selenium);

        if ($button) {
            $this->logger->debug('click Search');
            $button->click();
        }

//        if ($elem = $selenium->waitForElement(\WebDriverBy::xpath("//button[starts-with(normalize-space(),'Continue to flight results')]"),
//            2)) {
//            $elem->click();
//        } else {
        $this->logger->debug("[run script]");
        $script = "
        function contains(selector, text) {
            var elements = document.querySelectorAll(selector);
            return [].filter.call(elements, function (element) {
                return RegExp(text).test(element.textContent);
            });
        }
        elem = contains('button','Continue')
        if (elem.length === 1)
            elem[0].click();
        ";
        $this->logger->debug($script, ['pre' => true]);
        $selenium->driver->executeScript($script);
//        }

//        $this->logger->debug("[run script]");
//        $script = "
        //function contains(selector, text) {
//    var elements = document.querySelectorAll(selector);
//    return [].filter.call(elements, function (element) {
//        return RegExp(text).test(element.textContent);
//    });
        //}
        //elem = contains('button','Search flights')
        //if (elem.length === 1)
//    elem[0].click();
        //";
//        $this->logger->debug($script, ['pre' => true]);
//        $selenium->driver->executeScript($script);

        /*
        if (!$evaluateMouse) {
            $script = "var form = document.querySelector('form[action=\"https://book.jetblue.com/B6/webqtrip.html\"]');
                if (!form)
                    form = document.querySelector('#first-tab form');
                if (form) {
                    form.submit();
                }
                ";
            $this->logger->debug("[run script]");
            $this->logger->debug($script, ['pre' => true]);
            $selenium->driver->executeScript($script);
        } else {
            $mover = new \MouseMover($this->driver);
            $mover->logger = $this->logger;
            $mover->duration = 100000;
            $mover->steps = 50;
            $mover->moveToElement($button);
            $mover->click();

            $this->logger->debug("[run script one more time]");
            $selenium->driver->executeScript($script);
            $script = "var form = document.querySelector('form[action=\"https://book.jetblue.com/B6/webqtrip.html\"]');
                if (!form)
                    form = document.querySelector('#first-tab form');
                if (form) {
                    form.submit();
                }
                ";
            $this->savePageToLogs($selenium);
            $this->logger->debug("[run script]");
            $this->logger->debug($script, ['pre' => true]);
            $selenium->driver->executeScript($script);
        }
        */

//        $this->logger->debug($script, ['pre' => true]);
//        $selenium->driver->executeScript($script);

        $button = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(.,'Search flights')]"), 3);

        if ($button) {
            $this->savePageToLogs($selenium);

            return null;
        }
        $selenium->waitForElement(\WebDriverBy::xpath("//h3[contains(text(),'Departing flights')]"), 25);
        $this->savePageToLogs($selenium);

        $this->logger->debug('[url]: ' . $selenium->http->currentUrl());

        return $selenium->http->currentUrl();
    }

    private function validRoute($fields): bool
    {
        $this->logger->notice(__METHOD__);
        $dataFrom = \Cache::getInstance()->get('ra_jb_origins');

        if (!$dataFrom || !is_array($dataFrom)) {
            $this->http->GetURL("https://jbrest.jetblue.com/od/od-service/origins");

            if ($this->http->Response['code'] == 502) {
                $this->http->GetURL("https://jbrest.jetblue.com/od/od-service/origins");
            }
            $dataFrom = $this->http->JsonLog(null, 0, true);

            if (!empty($dataFrom)) {
                \Cache::getInstance()->set('ra_jb_origins', $dataFrom, 60 * 60 * 24);
            }
        }

        if (isset($dataFrom['data']['origins']) && is_array($dataFrom['data']['origins'])) {
            $inOrigins = false;

            foreach ($dataFrom['data']['origins'] as $origin) {
                if ($origin['code'] === $fields['DepCode']) {
                    $inOrigins = true;

                    break;
                }
            }

            if (!$inOrigins) {
                $this->SetWarning($fields['DepCode'] . " is not in list of origins");

                return false;
            }
            $dataTo = \Cache::getInstance()->get('ra_jb_destinations_' . $fields['DepCode']);

            if (!$dataTo || !is_array($dataTo)) {
                $this->http->GetURL("https://jbrest.jetblue.com/od/od-service/routes/destinations/origin/" . $fields['DepCode']);

                if ($this->http->Response['code'] == 502) {
                    $this->http->GetURL("https://jbrest.jetblue.com/od/od-service/routes/destinations/origin/" . $fields['DepCode']);
                }
                $dataTo = $this->http->JsonLog(null, 0, true);

                if (!empty($dataTo)) {
                    \Cache::getInstance()->set('ra_jb_destinations_' . $fields['DepCode'], $dataTo, 60 * 60 * 24);
                }
            }

            if (isset($dataTo['data']['destinations']) && is_array($dataTo['data']['destinations'])) {
                $inDestinations = false;

                foreach ($dataTo['data']['destinations'] as $destinations) {
                    if ($destinations['code'] === $fields['ArrCode']) {
                        $inDestinations = true;

                        break;
                    }
                }

                if (!$inDestinations) {
                    $this->SetWarning($fields['ArrCode'] . " is not in list of destinations");

                    return false;
                }
            }
        }

        return true;
    }

    private function interceptResponseDataStorage($url, $selenium, $limit = 10)
    {
        $this->logger->notice(__METHOD__);

        try {
            try {
                $selenium->http->GetURL($url);

                if ($this->http->FindSingleNode("//h1[contains(., 'Be right back')]")
                    && ($msg = $this->http->FindSingleNode("//h1[contains(., 'Be right back')]/following-sibling::p[1]"))
                ) {
                    $link = $this->http->FindSingleNode("//b[contains(.,'Find your way')]/following-sibling::ul//a[contains(.,'Book flight')]/@href");
                    $selenium->http->GetURL($link); // debug

                    if ($this->attempt === 0) {
                        $this->sendNotification('check PROVIDER_ERROR // ZM');

                        throw new \CheckRetryNeededException(5, 0);
                    }

                    throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
                }
            } catch (\UnexpectedJavascriptException $e) {
                $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage());
                $selenium->http->GetURL($url);
            } catch (\WebDriverException $e) {
                $this->logger->error('WebDriverException: ' . $e->getMessage());
                $selenium->http->GetURL($url);
            } catch (\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverCurlException $e) {
                $this->logger->error('WebDriverCurlException: ' . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            } catch (\Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
                $this->logger->error('InvalidSessionIdException: ' . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            } catch (\UnrecognizedExceptionException $e) {
                $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }
        } catch (\TimeOutException $e) {
            $this->logger->error('TimeOutException: ' . $e->getMessage());
            $selenium->driver->executeScript('window.stop();');

            try {
                $this->savePageToLogs($selenium);
            } catch (\TimeOutException $e) {
                $this->logger->error('TimeOutException: ' . $e->getMessage());
            }

            throw new \CheckRetryNeededException(5, 0);
        } catch (\UnexpectedAlertOpenException | \WebDriverException | \WebDriverCurlException $e) {
            throw new \CheckRetryNeededException(5, 0);
        }

        try {
            $frame = $selenium->waitForElement(\WebDriverBy::xpath("//iframe[starts-with(@id,'pop-frame')]"), 3);
        } catch (\StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\UnrecognizedExceptionException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
            $this->logger->error("InvalidSessionIdException: " . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($frame) {
            $this->logger->info('close popUp');
            $selenium->driver->executeScript("document.querySelectorAll('div[id^=\"pop-\"]').forEach(function(e){ e.setAttribute('style','display:none') })");
        }

        try {
            $this->savePageToLogs($selenium);
        } catch (\WebDriverException | \Facebook\WebDriver\Exception\WebDriverException
            | \WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("web driver exception: " . $e->getMessage());
            // otherwise killed watchdog
            throw new \CheckRetryNeededException(5, 0);
        }

        if (
            $this->http->FindSingleNode("//h1[contains(., 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//h1[normalize-space()='Access Denied']")
            || $this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'This page isn’t working')]")
            || $this->http->FindSingleNode("//p[contains(text(), 'There is something wrong with the proxy server, or the address is incorrect.')]")
        ) {
            $this->DebugInfo = "bad proxy";

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($this->http->FindSingleNode("//jb-dialog//jb-dialog-content/p[contains(.,\"Unfortunately we can't proceed with this selection. Please reselect\")]")) {
            $this->checkPopUp($selenium);

            $startSession = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(.,'Start new search')]"),
                0);

            if ($startSession) {
                throw new \CheckRetryNeededException(5, 0);

                try {
                    $startSession->click();
                } catch (\UnrecognizedExceptionException $e) {
                    $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage());
                    $this->sendNotification('check UnrecognizedExceptionException // ZM');
                    sleep(1);
                    $this->checkPopUp($selenium);
                    $startSession = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(.,'Start new search')]"),
                        0);

                    if ($startSession) {
                        $startSession->click();
                    }
                }
            }
            sleep(2);

            try {
                $selenium->http->GetURL($url);
            } catch (\UnexpectedJavascriptException $e) {
                $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage());
                sleep(1);
                $selenium->http->GetURL($url);
            } catch (\WebDriverException $e) {
                $this->logger->error("WebDriverException: " . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }

            $this->savePageToLogs($selenium);

            if ($this->http->FindSingleNode("//jb-dialog//jb-dialog-content/p[contains(.,\"Unfortunately we can't proceed with this selection. Please reselect\")]")) {
                throw new \CheckRetryNeededException(5, 0);
            }
        }

        if ($this->http->FindSingleNode("//h1[contains(., 'Be right back')]")
            && ($msg = $this->http->FindSingleNode("//h1[contains(., 'Be right back')]/following-sibling::p[1]"))
        ) {
            $link = $this->http->FindSingleNode("//b[contains(.,'Find your way')]/following-sibling::ul//a[contains(.,'Book flight')]/@href");
            $selenium->http->GetURL($link); // debug

            throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
        }

        $waitCounter = 0;
        $this->logger->debug('Loading...');

        try {
            $this->waitFor(function () use ($selenium, &$waitCounter) {
                $waitCounter++;
                $this->logger->debug($waitCounter);

                return
                    $selenium->driver->findElement(\WebDriverBy::id('flight-details'))
                    || $selenium->driver->findElement(\WebDriverBy::xpath('//jb-dialog//jb-dialog-content'))
                    || $selenium->driver->findElement(\WebDriverBy::xpath('//h2[contains(normalize-space(),"No flights have been found for your search criteria")]'));
            }, 45);
        } catch (\Exception | \TypeError $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        try {
            $this->savePageToLogs($selenium);
        } catch (\Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
            $this->logger->error('InvalidSessionIdException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error('WebDriverCurlException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        if (!$selenium->waitForElement(\WebDriverBy::id('flight-details'), 0)
            && !$selenium->waitForElement(\WebDriverBy::xpath('//jb-dialog//jb-dialog-content'), 0)
            && !$selenium->waitForElement(\WebDriverBy::xpath('//h2[contains(normalize-space(),"No flights have been found for your search criteria")]'),
                0)) {
            throw new \CheckRetryNeededException(5, 0);
        }
        $responseData = $selenium->driver->executeScript("return sessionStorage.getItem('fares');");

        return $responseData;
    }

    private function checkPopUp($selenium)
    {
        try {
            $frame = $selenium->waitForElement(\WebDriverBy::xpath("//iframe[starts-with(@id,'pop-frame')]"), 3);
        } catch (\StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($frame) {
            $this->logger->info('close popUp');
            $selenium->driver->executeScript("document.querySelectorAll('div[id^=\"pop-\"]').forEach(function(e){ e.setAttribute('style','display:none') })");
        }
        $this->savePageToLogs($selenium);
    }

    private function interceptResponseData($url, $selenium, $limit = 10)
    {
        $this->logger->notice(__METHOD__);

        $selenium->http->GetURL($url);
        usleep(100);

        try {
            $this->logger->debug('run script');
            $selenium->driver->executeScript('
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener("load", function() {
                    if (/lfs-rwb\/outboundLFS/g.exec( url ) && /itinerary/g.exec( this.responseText )) {
                        localStorage.setItem("responseData", this.responseText);
                    }
                });
                return oldXHROpen.apply(this, arguments);
            };
            ');
        } catch (\UnexpectedJavascriptException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            sleep(2);
            $this->sendNotification("UnexpectedJavascriptException // ZM");
        }

        $selenium->waitForElement(\WebDriverBy::id('flight-details'), 20);
        //            $selenium->savePageToLogs($selenium);

        try {
            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");

            if (empty($responseData)) {
                $this->logger->debug("+{$limit} sec wait");
                sleep($limit);
                $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");

                if (empty($responseData)) {
                    $this->logger->debug("+{$limit} sec wait");
                    sleep($limit);
                    $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
                }
            }
        } catch (\UnknownServerException $e) {
            $responseData = null;
        }

        return $responseData;
    }

    private function parseJsonDataStorageNew($data)
    {
        $this->logger->notice(__METHOD__);
        $routes = [];

        foreach ($data->departingFlights->fareDetail as $fare) {
            $stops = count($fare->connections ?? []);

            $connections = [];

            foreach ($fare->segments as $segment) {
                $stops += $segment->stops;

                $depTime = strtotime(substr($segment->depart, 0, 16));
                $arrTime = strtotime(substr($segment->arrive, 0, 16));
                $connections[] = [
                    'num_stops' => $segment->stops,
                    'departure' => [
                        'date'     => date('Y-m-d H:i', $depTime),
                        'dateTime' => $depTime,
                        'airport'  => $segment->from,
                        'terminal' => $segment->throughFlightLegs[0]->departureTerminal ?? null,
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', $arrTime),
                        'dateTime' => $arrTime,
                        'airport'  => $segment->to,
                        'terminal' => $segment->throughFlightLegs[0]->arrivalTerminal ?? null,
                    ],
                    'cabin'      => $segment->cabinclass,
                    'fare_class' => $segment->bookingclass,
                    'meal'       => null,
                    'flight'     => [
                        isset($segment->filingAirline) && $segment->filingAirline == 'JetBlue'
                            ? $segment->marketingAirlineCode . ' ' . $segment->flightno
                            : $segment->flightno,  //Partner flights duplicate iata code
                    ],
                    'airline'    => $segment->marketingAirlineCode,
                    'operator'   => $segment->operatingAirlineCode,
                    'aircraft'   => $segment->aircraftCode,
                    'times'      => [
                        'flight'  => null,
                        'layover' => null,
                    ],
                ];
            }

            foreach ($fare->bundles as $bundle) {
                if (in_array($bundle->status, ['SOLD_OUT', 'NOT_OFFERED'])) {
                    continue;
                }

                if (!isset($bundle->points)) {
                    $noPoints = true;

                    continue;
                }

                if (!isset($bundle->code)) {
                    $fareType = $this->getFareTypeByFareCode($bundle->fareCode);

                    if (null === $fareType) {
                        $noCode = true;
                        $this->logger->warning('skip fare-bundle. no Code');
                    }

                    if ($fareType === $bundle->fareCode) {
                        $this->logger->warning('stop parse. other data is invisible == incorrect');

                        break 2;
                    }

                    continue;
                }

                $fareType = $bundle->code;
                $cabinClass = $this->getCabinClassFields($fareType);
                $bookingClass = $bundle->bookingclass;
                $headData = [
                    'num_stops'   => $stops,
                    'distance'    => null,
                    'redemptions' => [
                        'miles'   => preg_replace('/[^\d]+/', '', $bundle->points),
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $this->currency($data->departingFlights->currency),
                        'taxes'    => PriceHelper::cost(trim(preg_replace('/[^\d.,\s]/', '', $bundle->fareTax))),
                        'fees'     => null,
                    ],
                    'tickets'        => $bundle->inventoryQuantity ?? null,
                    'award_type'     => ucwords(strtolower(str_replace('_', ' ', $fareType))),
                    'classOfService' => ucwords(strtolower(str_replace('_', ' ', $fareType))),
                ];
                $segments = $connections;

                foreach ($connections as $key => $connection) {
                    $segments[$key]['cabin'] = $cabinClass;
                    $segments[$key]['fare_class'] = $bookingClass;
                    // не прокатывает. много расхождения. схема от juicymiles остается в зависимости от оплаты кэюина
                    /*if ($connection['cabin'] == $bundle->cabinclass) {
                        $segments[$key]['cabin'] = $this->getCabinClassFields($bundle->code);
                    }*/
                }

                $headData['connections'] = $segments;
                $routes[] = $headData;
                $this->logger->debug('Parsed data:');
                $this->logger->debug(var_export($headData, true), ['pre' => true]);
            }
        }

        if (isset($noCode)) {
            if (empty($routes)) {
                $this->sendNotification('no code/no routes. check restart // ZM');

                throw new \CheckRetryNeededException(5, 0);
            }
            $this->logger->debug(var_export($routes, true), ['pre' => true]);
            // если нет кода, то даже что собрал, там неверные поинты походу...
            throw new \CheckRetryNeededException(5, 0);
        }

        if (empty($routes) && isset($noPoints)) {
            $this->SetWarning("No flights have been found for your search criteria.");
        }

        return $routes;
    }

    private function parseJsonDataStorage($data)
    {
        $this->logger->notice(__METHOD__);
        $routes = [];

        foreach ($data->departingFlights->fareDetail as $fareType => $fareDetail) {
            foreach ($fareDetail as $cabinType => $fare) {
                if (!isset($fare->cabinclass)) {
                    return $this->parseJsonDataStorageNew($data);
                }

                if ($fare->cabinclass == 'N/A') {
                    continue;
                }

                if (!isset($fare->points)) {
                    $noPoints = true;

                    continue;
                }
                $cabinClass = $this->getCabinClassFields($fare->code);
                $connections = [];
                $stops = -1;
                $headData = [
                    'num_stops'   => null,
                    'distance'    => null,
                    'redemptions' => [
                        'miles'   => preg_replace('/[^\d]+/', '', $fare->points),
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $this->currency($data->departingFlights->currency),
                        'taxes'    => PriceHelper::cost(trim(preg_replace('/[^\d.,\s]/', '', $fare->fareTax))),
                        'fees'     => null,
                    ],
                    'tickets'        => $fare->inventoryQuantity ?? null,
                    'award_type'     => ucwords(strtolower(str_replace('_', ' ', $fareType))),
                    'classOfService' => ucwords(strtolower(str_replace('_', ' ', $fareType))),
                ];

                // Cabins
                $cabins = $this->getCabinFields(false);

                foreach ($connections as $key => $value) {
                    if (isset($cabins[$headData['award_type']])) {
                        $connections[$key]['cabin'] = $cabins[$headData['award_type']];
                    } else {
                        $this->sendNotification('AR check cabins // ZM');
                    }
                }

                foreach ($fare->segments as $segment) {
                    $stops += (1 + $segment->stops);

                    $depTime = strtotime(substr($segment->depart, 0, 16));
                    $arrTime = strtotime(substr($segment->arrive, 0, 16));
                    $connections[] = [
                        'num_stops' => $segment->stops,
                        'departure' => [
                            'date'     => date('Y-m-d H:i', $depTime),
                            'dateTime' => $depTime,
                            'airport'  => $segment->from,
                            'terminal' => $segment->throughFlightLegs[0]->departureTerminal ?? null,
                        ],
                        'arrival' => [
                            'date'     => date('Y-m-d H:i', $arrTime),
                            'dateTime' => $arrTime,
                            'airport'  => $segment->to,
                            'terminal' => $segment->throughFlightLegs[0]->arrivalTerminal ?? null,
                        ],
                        'cabin'      => $cabinClass,
                        'fare_class' => $segment->bookingclass,
                        'meal'       => null,
                        'flight'     => [
                            isset($segment->filingAirline) || $segment->filingAirline == 'JetBlue'
                                ? $segment->marketingAirlineCode . ' ' . $segment->flightno
                                : $segment->flightno,  //Partner flights duplicate iata code
                        ],
                        'airline'    => $segment->marketingAirlineCode,
                        'operator'   => $segment->operatingAirlineCode,
                        'aircraft'   => $segment->aircraftCode,
                        'times'      => [
                            'flight'  => null,
                            'layover' => null,
                        ],
                    ];
                }
                $headData['num_stops'] = $stops;
                $headData['connections'] = $connections;
                $routes[] = $headData;
                $this->logger->debug('Parsed data:');
                $this->logger->debug(var_export($headData, true), ['pre' => true]);
            }
        }

        if (empty($routes) && isset($noPoints)) {
            $this->SetWarning("No flights have been found for your search criteria.");
        }
        $this->sendNotification('steel old version // ZM');

        return $routes;
    }

    private function parseJsonData($data)
    {
        $this->logger->notice(__METHOD__);

        $routes = [];
        $awards = $this->getCabinFields();

        $offers = [];

        foreach ($data['fareGroup'] as $fareGroup) {
            $fare = ucwords(strtolower(str_replace('_', ' ', $fareGroup['fareCode'])));

            if (!in_array($fare, $awards)) {
                $this->sendNotification('check unknown award_type // ZM');
                $this->logger->warning('skip ' . $fare);

                continue;
            }

            foreach ($fareGroup['bundleList'] as $bundleList) {
                if ($bundleList['status'] === 'NOT_OFFERED') {
                    continue;
                }

                if ($bundleList['status'] !== 'AVAILABLE' && $bundleList['points'] !== 'N/A') {
                    $checkStatus = true;

                    continue;
                }
                $offers[$bundleList['itineraryID']][$fare] = $bundleList;
            }
        }

        if (isset($checkStatus)) {
            $this->sendNotification('check status at bundleList // ZM');
        }

        foreach ($data['itinerary'] as $itinerary) {
            $connections = [];
            $stops = -1;
            $id = $itinerary['id'];

            foreach ($itinerary['segments'] as $segment) {
                $stops += (1 + $segment['stops']);

                if ($segment['stops'] > 0) {
                    $this->sendNotification('check stops // ZM');
                }
                $depTime = strtotime(substr($segment['depart'], 0, 16));
                $arrTime = strtotime(substr($segment['arrive'], 0, 16));
                $connections[] = [
                    'num_stops' => $segment['stops'],
                    'departure' => [
                        'date'     => date('Y-m-d H:i', $depTime),
                        'dateTime' => $depTime,
                        'airport'  => $segment['from'],
                        'terminal' => $segment['throughFlightLegs'][0]['departureTerminal'] ?? null,
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', $arrTime),
                        'dateTime' => $arrTime,
                        'airport'  => $segment['to'],
                        'terminal' => $segment['throughFlightLegs'][0]['arrivalTerminal'] ?? null,
                    ],
                    'cabin'      => $segment['cabinclass'],
                    'fare_class' => $segment['bookingclass'],
                    'meal'       => null,
                    'flight'     => [
                        $segment['filingAirline'] == 'JetBlue' ? $segment['marketingAirlineCode'] . ' ' . $segment['flightno']
                            : $segment['flightno'],  //Partner flights duplicate iata code
                    ],
                    'airline'    => $segment['marketingAirlineCode'],
                    'operator'   => $segment['operatingAirlineCode'],
                    'aircraft'   => $segment['aircraftCode'],
                    'times'      => [
                        'flight'  => null,
                        'layover' => null,
                    ],
                ];
            }

            if (isset($offers[$id])) {
                foreach ($offers[$id] as $fare => $bundleList) {
                    $headData = [
                        'num_stops'   => $stops,
                        'distance'    => null,
                        'redemptions' => [
                            'miles'   => preg_replace('/[^\d]+/', '', $bundleList['points']),
                            'program' => $this->AccountFields['ProviderCode'],
                        ],
                        'payments' => [
                            'currency' => $this->currency($data['currency']),
                            'taxes'    => PriceHelper::cost(trim(preg_replace('/[^\d.,\s]/', '', $bundleList['fareTax']))),
                            'fees'     => null,
                        ],
                        'tickets'    => $bundleList['inventoryQuantity'] ?? null,
                        'award_type' => $fare,
                    ];

                    // Cabins
                    $cabins = $this->getCabinFields(false);

                    foreach ($connections as $key => $value) {
                        if (isset($cabins[$headData['award_type']])) {
                            $connections[$key]['cabin'] = $cabins[$headData['award_type']];
                        } else {
                            $this->sendNotification('AR check cabins // ZM');
                        }
                    }
                    $headData['connections'] = $connections;

                    $routes[] = $headData;
                    $this->logger->debug('Parsed data:');
                    $this->logger->debug(var_export($headData, true), ['pre' => true]);
                }
            }
        }

        if (empty($routes)) {
            $this->sendNotification('check parse json // ZM');

            throw new \CheckException('something wrong with json', ACCOUNT_ENGINE_ERROR);
        }

        return $routes;
    }
}
