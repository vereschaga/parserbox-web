<?php

namespace AwardWallet\Engine\turkish\RewardAvailability;

use AwardWallet\Common\Selenium\BrowserCommunicatorException;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use CheckRetryNeededException;
use SeleniumCheckerHelper;
use WebDriverBy;

class Parser extends \TAccountCheckerTurkish
{
    use \PriceTools;
    use ProxyList;
    use SeleniumCheckerHelper;

    private const XPATH_SUCCESSFUL = '//button[contains(@class,"signoutBTN")] | //button[./span[normalize-space()="SIGNOUT"]] | //div[@data-bind="text: ffpNumber()"]';
    private const XPATH_SIGN_IN = '//div[contains(@class, "signin-dropdown") or contains(@class, "signinDropdown")]';
    public $isRewardAvailability = true;

    private $warning;
    private $skipped = false;
    private $noFlights = false;
    private $ports = [];
    private $isHot = false;

    public static function getRASearchLinks(): array
    {
        return [
            'https://www.turkishairlines.com/en-int/miles-and-smiles/book-award-tickets/'              => 'search page',
            'https://www.turkishairlines.com/en-int/miles-and-smiles/account/book-star-award-tickets/' => 'alliance page',
        ];
    }

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->setDefaultHeader('Accept-Language', 'en');
        $this->UseSelenium();
        $this->seleniumOptions->recordRequests = true;
        $this->http->saveScreenshots = true;
        /*
                switch (random_int(0, 2)) {
                    case 0:
                        $this->useGoogleChrome();

                        break;

                    case 1:
                        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);

                        break;

                    default:
                        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);

                        break;
                }
                */
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
//        $this->seleniumOptions->addHideSeleniumExtension = false;
//        $this->seleniumOptions->userAgent = null;

        $request = FingerprintRequest::firefox();
        $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
        $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        $array = ['us', 'cl', 'dk', 'fr', 'cn', 'id'];
        $targeting = $array[array_rand($array)];

        if ($this->AccountFields['ParseMode'] === 'awardwallet') {
            $this->setProxyGoProxies(null, $targeting);
        } else {
            $this->setProxyGoProxies(null, $targeting);
        }

        $resolutions = [
            [1024, 768],
            [1152, 864],
            [1280, 800],
        ];

        $resolution = $resolutions[array_rand($resolutions)];
        $this->State['chosenResolution'] = $resolution;
        $this->setScreenResolution($this->State['chosenResolution']);

        if (isset($fingerprint)) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->http->setUserAgent($fingerprint->getUseragent());
            $this->seleniumOptions->userAgent = $fingerprint->getUseragent();
        }

        $this->KeepState = false;
        $this->seleniumRequest->setHotSessionPool(
            self::class,
            $this->AccountFields['ProviderCode'],
            $this->AccountFields['AccountKey']
        );
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
        $this->logger->debug('CurrentUrl: ' . $this->http->currentUrl());

        try {
            $this->driver->manage()->window()->maximize();
        } catch (\Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($this->isNewSession()) {
            /** @var \SeleniumDriver $seleniumDriver */
            $seleniumDriver = $this->http->driver;

            if (method_exists($seleniumDriver->browserCommunicator, 'blockRequests')) {
                $seleniumDriver->browserCommunicator->blockRequests([
                    '*://*.doubleclick.net/*',
                    '*://*.google-analytics.com/*',
                    '*://*.googlesyndication.com/*',
                    '*://fonts.gstatic.com/*',
                    '*://*.quantummetric.com/*',
                    '*://*.facebook.com/*',
                    '*://*.tiktok.com/*',
                    //'*://*.akstat.io/*', // triggering bot protection
                    '*://*.optimizely.com/*',
                    '*://*.liveperson.*/*',
                    //'*://*.go-mpulse.net/*', // triggering bot protection
                    '*://*.tiqcdn.com/*',
                    '*://*.googletagmanager.com/*',
                    '*://webmatomo.turkishairlines.com//*',
                    '*.woff',
                ]);
            } else {
                $this->sendNotification("check blockRequests not exists // ZM");
            }
        }

        $this->ports = [];

        if (!$this->validRoute($this->AccountFields['RaRequestFields'])) {
            $this->noFlights = true;

//            if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_SUCCESSFUL), 0)) {
            $this->keepSession(true);
//            }

            return true;
        }

        try {
            $this->http->GetURL('https://www.turkishairlines.com/en-int/');
        } catch (\Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error($e->getMessage());
            // TODO tmp
            throw new \CheckRetryNeededException(5, 0);
        }

        if (!$this->waitForElement(WebDriverBy::xpath('//*[self::a or self::span][@id="one-way"]'), 20)) {
            $this->waitForElement(WebDriverBy::xpath('
            //h1[contains(text(), "Access Denied")]
            | //h1[contains(text(), "Secure Connection Failed")]
            | ' . self::XPATH_SIGN_IN . '
            | //span[contains(text(), "This site can’t be reached")]
            | '// . self::XPATH_SUCCESSFUL
            ), 0);
        }
        $this->savePageToLogs($this);

        if ($this->waitForElement(WebDriverBy::xpath('//body[not(normalize-space())]'), 0)) {
            $this->sendNotification('Checking a blank page //DM');

            throw new \CheckRetryNeededException(5, 0);
        }

        $this->driver->executeScript("
            var cookieWarningAcceptId = document.querySelector('#allowCookiesButton');
 
            if (cookieWarningAcceptId) {
                cookieWarningAcceptId.click();
            }
        ");

//        if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_SIGN_IN), 0)) {
//            $this->signIn();
//        }

//        $res = !$this->waitForElement(WebDriverBy::xpath(self::XPATH_SIGN_IN), 0);
        $this->saveResponse();
//        $this->badProxyDetection($res);
//
//        if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_SUCCESSFUL), 0)) {
        $this->saveResponse();

        if ($modalButton = $this->waitForElement(\WebDriverBy::xpath('//button[text()="Close"]'), 0)) {
            $modalButton->click();
        }

        $this->markProxySuccessful();

        $this->randomClickSearchForm();

        return true;
//        }
        $this->driver->executeScript('window.location.reload()');

        if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_SUCCESSFUL), 15)) {
            $this->saveResponse();
            $this->markProxySuccessful();

            $this->randomClickSearchForm();

            return true;
        }
        $this->saveResponse();
        $this->markProxyAsInvalid();

        throw new \CheckRetryNeededException(5, 0);
    }

    public function getRewardAvailabilitySettings()
    {
        $arrCurrencies = $this->getListCurrencies(false);

        return [
            'supportedCurrencies'      => $arrCurrencies,
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'TRY',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->http->FilterHTML = false;

        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        if ($this->noFlights) {
            return [];
        }

        $listCurrencies = $this->getListCurrencies();

        if (!in_array($fields['Currencies'][0], $listCurrencies)) {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        // TODO???
        if (in_array($fields['Cabin'], ['firstClass', 'business']) && $fields['Adults'] > 4) {
            $this->logger->error($msg = "Business passengers (non-infant) can be booked on international flights at maximum 4 people at one time.");
            $this->SetWarning($msg);

            $this->keepSession(true);

            return ['routes' => []];
        }

        if ($fields['DepDate'] > strtotime('+355 day')) {
            $this->SetWarning('Please specify valid date. (DATE OUT OF OVER LIMIT)');

            $this->keepSession(true);

            return ['routes' => []];
        }

        // TODO???
        if ($fields['Adults'] > 1) {
            $this->logger->error("You can issue the first ticket only to your name in order to verify your membership. Subsequently, you can issue award tickets for other persons as well.");
            $this->SetWarning("You can issue the ticket only for 1 passenger");

            $this->keepSession(true);

            return ['routes' => []];
        }

        $PageRequestID = $this->waitForElement(WebDriverBy::xpath('//meta[@name="PageRequestID"]'), 5, false);

        if ($PageRequestID) {
            $PageRequestID = $PageRequestID->getAttribute('content');
            $this->logger->info('$PageRequestID: ' . $PageRequestID);
        } else {
            $PageRequestID = $this->generateRequestId();
        }
//
//        $locations = \Cache::getInstance()->get('ra_tk_locations');
//
//        if ($locations === false || !is_array($locations)) {
//            $this->driver->executeScript('
//                fetch("https://www.turkishairlines.com/com.thy.web.online.ibs/ibs/booking/locations/TK/en?_=3", {
//                  "headers": {
//                    "accept": "application/json, text/javascript, */*; q=0.01",
//                    "accept-language": "en",
//                  },
//                  "referrer": "https://www.turkishairlines.com/en-int/index.html",
//                  "referrerPolicy": "no-referrer-when-downgrade",
//                  "body": null,
//                  "method": "GET",
//                  "mode": "cors",
//                  "credentials": "omit"
//                }).then( response => response.json())
//                  .then( result => {
//                    let script = document.createElement("script");
//                    let id = "locations";
//                    script.id = id;
//                    script.setAttribute(id, JSON.stringify(result));
//                    document.querySelector("body").append(script);
//                });
//            ');
//
//            $locationsJson = $this->waitForElement(\WebDriverBy::xpath('//script[@id="locations"]'), 10, false);
//            $this->saveResponse();
//
//            if (!$locationsJson) {
//                throw new \CheckRetryNeededException(5, 0);
//            }
//            $data = $this->http->JsonLog($locationsJson->getAttribute("locations"), 1, true);
//
//            if (isset($data['data']['ports'])) {
//                foreach ($data['data']['ports'] as $num => $ports) {
//                    if ($ports['hideInBooker']) {
//                        continue;
//                    }
//
//                    if ($ports['multi']) {
//                        foreach ($ports['ports'] as $port) {
//                            $locations[$port] = ['code' => $port, 'multi' => false, 'domestic' => $ports['domestic']];
//                        }
//
//                        if (!isset($locations[$ports['city']['code']])) {
//                            $locations[$ports['city']['code']] = [
//                                'code'     => $ports['port']['code'],
//                                'multi'    => true,
//                                'domestic' => $ports['domestic'],
//                            ];
//                        }
//                    } else {
//                        if (!isset($locations[$ports['city']['code']])) {
//                            $locations[$ports['city']['code']] = [
//                                'code'     => $ports['code'],
//                                'multi'    => false,
//                                'domestic' => $ports['domestic'],
//                            ];
//                        }
//
//                        if ($ports['code'] !== $ports['city']['code'] && !isset($locations[$ports['code']])) {
//                            $locations[$ports['code']] = [
//                                'code'     => $ports['code'],
//                                'multi'    => false,
//                                'domestic' => $ports['domestic'],
//                            ];
//                        }
//                    }
//                }
//
//                if (!empty($locations)) {
//                    \Cache::getInstance()->set('ra_tk_locations', $locations, 60 * 60 * 24);
//                } else {
//                    $this->logger->error('other format json');
//
//                    throw new \CheckException('no list locations', ACCOUNT_ENGINE_ERROR);
//                }
//            } else {
//                throw new \CheckException('no list locations', ACCOUNT_ENGINE_ERROR);
//            }
//        }
//
//        if (!array_key_exists($fields['DepCode'], $locations)) {
//            $this->logger->error($msg = 'no flights from ' . $fields['DepCode']);
//            $this->SetWarning($msg);
//
//            $this->keepSession(true);
//
//            return ['routes' => []];
//        }
//
//        if (!array_key_exists($fields['ArrCode'], $locations)) {
//            $this->logger->error($msg = 'no flights to ' . $fields['ArrCode']);
//            $this->SetWarning($msg);
//
//            $this->keepSession(true);
//
//            return ['routes' => []];
//        }
//
//        $fields['origin'] = $locations[$fields['DepCode']];
//        $fields['destination'] = $locations[$fields['ArrCode']];

        if (!$PageRequestID) {
            throw new \CheckException("can't load page and get PageRequestID", ACCOUNT_ENGINE_ERROR);
        }

        try {
            $routes = $this->ParseReward($fields, $PageRequestID);
        } catch (\WebDriverException | \WebDriverCurlException $e) {
            $this->logger->error('WebDriverException: ' . $e->getMessage());

            throw new CheckRetryNeededException(5, 0);
        } catch (\CheckException $e) {
            $this->logger->error('try with alliance');

            throw new CheckRetryNeededException(5, 0);
        } catch (\ErrorException $e) {
            if (strpos($e->getMessage(), 'Array to string conversion') !== false
                || strpos($e->getMessage(), 'strlen() expects parameter 1 to be string, array given') !== false
            ) {
                // TODO бага селениума
                throw new \CheckRetryNeededException(5, 0);
            }

            throw $e;
        }

//        try {
//            $routesAlliance = $this->ParseReward($fields, $PageRequestID, true);
//        } catch (\CheckException $e) {
//            if (empty($routes) && empty($this->warning)) {
//                throw $e;
//            }
//            $routesAlliance = [];
//        }

//        if ($routesAlliance != $routes) {
//            $allRoutes = array_merge($routes, $routesAlliance);
//            $routes = array_map('unserialize', array_unique(array_map('serialize', $allRoutes)));
//        }

        if (empty($routes)) {
            if ($this->DebugInfo == "sensor_data issue") {
                throw new \CheckRetryNeededException(5, 0);
            }

            if ($this->skipped) {
                $this->SetWarning('No seats available');
            }

            if (!empty($this->warning)) {
                $this->SetWarning($this->warning);
            }
        }

        if (
            in_array($this->http->Response['code'], [502, 503, 504, 403])
            && $this->DebugInfo !== "sensor_data issue"
        ) {
            throw new \CheckRetryNeededException(5, 0);
        }

        $this->keepSession(true);

        return ['routes' => $routes];
    }

    protected function isBadConnect($http = null): bool
    {
        $this->logger->notice(__METHOD__);

        if (null === $http) {
            $http = $this->http;
        }

        return strpos($http->Error, 'Network error 28 - Connection timed out after') !== false
            || strpos($http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly') !== false
            || strpos($http->Error, 'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
            || strpos($http->Error, 'Network error 56 - Received HTTP code 403 from proxy after CONNECT') !== false
            || strpos($http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            || strpos($http->Error, 'Network error 56 - Received HTTP code 525 from proxy after CONNECT') !== false
            || strpos($http->Error, 'Network error 56 - Received HTTP code 407 from proxy after CONNECT') !== false
            || strpos($http->Error, 'Network error 28 - Unexpected EOF') !== false
            || strpos($http->Error, 'Network error 56 - Unexpected EOF') !== false
            || strpos($http->Error, 'Network error 35 - Unexpected EOF') !== false
            || strpos($http->Error, 'Network error 16 -') !== false
            || strpos($http->Error, 'Network error 35 - OpenSSL SSL_connect') !== false
            || strpos($http->Error, 'Network error 7 - Unexpected EOF') !== false
            || strpos($http->Error, 'Network error 7 - Failed to connect to') !== false
            || strpos($http->Error, 'Network error 56 - Proxy CONNECT aborted') !== false
            || strpos($http->Error, 'Network error 56 - Recv failure') !== false
            || strpos($http->Error, 'Network error 52 - Empty reply from server') !== false
            || strpos($http->Error, 'Network error 0 -') !== false
            || strpos($http->Error, 'Operation timed out after') !== false
            || $http->Response['code'] == 403;
    }

    private function randomClickSearchForm()
    {
        $this->driver->executeScript("
            if (document.querySelector('div[data-testid=\"undefined-close-button\"]')) {
                document.querySelector('div[data-testid=\"undefined-close-button\"]').click();
            }
        ");

        if (!$this->waitForElement(WebDriverBy::xpath('//*[self::a or self::span][@id="one-way"]'), 10)) {
            $this->saveResponse();

            throw new CheckRetryNeededException(5, 0);
        }

        $inputs = [
            [
                'name'    => 'oneWay',
                'element' => $this->waitForElement(WebDriverBy::xpath('//*[self::a or self::span][@id="one-way"]'), 5),
            ],
            [
                'name'    => 'roundTrip',
                'element' => $this->waitForElement(WebDriverBy::xpath('//a[@id="round-trip"]'), 0),
            ],
            [
                'name'    => 'from',
                'element' => $this->waitForElement(WebDriverBy::xpath('//input[@id="general-booker-from"]/../..'), 0),
            ],
            [
                'name'    => 'to',
                'element' => $this->waitForElement(WebDriverBy::xpath('//input[@id="general-booker-to"]/../..'), 0),
            ],
            [
                'name'    => 'date',
                'element' => $this->waitForElement(WebDriverBy::xpath('//div[@id="general-booker-datapicker"]'), 0),
            ],
            [
                'name'    => 'cabinPassenger',
                'element' => $this->waitForElement(WebDriverBy::xpath('//div[@id="general-booker-paxpicker"]/..'), 0),
            ],
        ];

        $cnt = random_int(1, 3);

        while ($cnt) {
            $index = random_int(0, count($inputs) - 1);
            $this->logger->info("Try to click - {$inputs[$index]['name']}");

            if ($inputs[$index]['element']) {
                $inputs[$index]['element']->click();
                $this->someSleep();
            }
            unset($inputs[$index]);
            sort($inputs);
            $cnt--;
        }
    }

    private function validRoute(array $fields, bool $isCheck = false): bool
    {
        $http2 = new \HttpBrowser("none", new \CurlDriver());
        $this->http->brotherBrowser($http2);
        $http2->LogHeaders = true;
        $http2->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $http2->setHttp2(true);
        $http2->SetProxy("{$this->http->getProxyAddress()}:{$this->http->getProxyPort()}");
        $http2->setProxyAuth($this->http->getProxyLogin(), $this->http->getProxyPassword());
        $http2->setUserAgent($this->http->getDefaultHeader("User-Agent"));

        $x_bfps = [
            '78b80e30e5f6fca420d806d6a878bc7d',
            '1cd0911ecac319994580fdbb84ba3347',
            'ac3376126bf4389d2720953268f5aeec',
            '98003ce15ce8629fe7e9d67781875eea',
        ];
        $x_bfp = $x_bfps[array_rand($x_bfps)];

        if (!$this->hasAirport($http2, $fields['DepCode'], $x_bfp, $isCheck)) {
            $this->SetWarning('no flights from ' . $fields['DepCode']);

            return false;
        }

        if (!$this->hasAirport($http2, $fields['ArrCode'], $x_bfp, $isCheck)) {
            $this->SetWarning('no flights to ' . $fields['ArrCode']);

            return false;
        }

        if (count($this->ports) !== 2) {
            $this->logger->error("has no airport details");
            $this->logger->notice(var_export($this->ports, true), ['pre' => true]);

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($this->ports[$fields['DepCode']] === $this->ports[$fields['ArrCode']]
            || $this->ports[$fields['DepCode']]['city']['code'] === $this->ports[$fields['ArrCode']]['city']['code']) {
            $this->SetWarning('Departure and arrival points cannot be the same. Please change one.');

            return false;
        }
        $this->logger->notice(var_export($this->ports, true), ['pre' => true]);

        return true;
    }

    private function hasAirport($http2, $airport, $x_bfp, $isCheck = false): bool
    {
        if (!$isCheck) {
            $data = \Cache::getInstance()->get('ra_tk_locations2' . $airport);
        }

        if (!isset($data) || $data === false || !is_array($data)) {
            $headers = [
                'Accept'     => 'application/json, text/plain, */*',
                'Referer'    => 'https://www.turkishairlines.com/',
                'X-bfp'      => $x_bfp,
                'X-clientId' => $this->getUuid(), //'e8a5beb4-1dc1-47d2-b56c-f4c47c0d50ab',
                'X-country'  => 'int',
                'X-requestId'=> $this->generateRequestId(), // '34e719b0-9a07-4721-85c0-da28df201b4a'
            ];
            $http2->RetryCount = 0;
            $http2->GetURL("https://www.turkishairlines.com/api/v1/booking/locations/TK/en?searchText=" . $airport, $headers);
            $http2->RetryCount = 2;

            if ($this->isBadConnect($http2)) {
                throw new CheckRetryNeededException(5, 0);
            }
            $data = $http2->JsonLog(null, 1, true);

            if (!isset($data['data']['locations']['ports'])) {
                if (isset($data['data'], $data['success'])
                    && array_key_exists('locations', $data['data'])
                    && array_key_exists('statusDetailList', $data)
                    && $data['success'] && null === $data['statusDetailList']
                ) {
                    // Не могу понять, зачем записываем в кеш, если ничего не нашли
                    //\Cache::getInstance()->set('ra_tk_locations' . $airport, $data, 60 * 60 * 24 * 7);

                    return false;
                }
                $this->sendNotification("check airport " . $airport . " // ZM");

                return true;
            }  // сменил название кеша, чтобы мусор убрать
            \Cache::getInstance()->set('ra_tk_locations2' . $airport, $data, 60 * 60 * 24 * 7);
        }

        $has = false;

        // tmp 2 formats
        if ((isset($data['data']['ports']) && is_array($data['data']['ports']))
            || (isset($data['data']['locations']['ports']) && is_array($data['data']['locations']['ports']))) {
            $ports = $data['data']['locations']['ports'] ?? $data['data']['ports'];

            foreach ($ports as $port) {
                if ($port['code'] === $airport) {
                    $this->ports[$airport] = $port;
                    $has = true;

                    break;
                }
            }

            if (!$has) {
                foreach ($ports as $port) {
                    if ($port['city']['code'] === $airport) {
                        $this->ports[$airport] = $port;
                        $has = true;

                        break;
                    }
                }
            }
        } else {
            $has = true;
        }

        return $has;
    }

    private function signIn()
    {
        $this->logger->notice(__METHOD__);
        $res = $this->waitForElement(WebDriverBy::xpath('
            //h1[contains(text(), "Access Denied")]
            | //h1[contains(text(), "Secure Connection Failed")]
            | ' . self::XPATH_SIGN_IN . '
            | //span[contains(text(), "This site can’t be reached")]
            | ' . self::XPATH_SUCCESSFUL
        ), 0);
        /*
                if ($res && strpos($res->getText(), 'Header.SignIn') !== false) {
                    // TODO BLOCK?!!!
                    $this->logger->error("bad load page");

                    throw new \CheckRetryNeededException(5, 0);
                }
        */
        $signin = $this->waitForElement(WebDriverBy::xpath(self::XPATH_SIGN_IN), 0);

        if (!$signin) {
            if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_SUCCESSFUL), 0)) {
                $this->logger->notice("session is active, let's parse");

                return;
            }

            $this->logger->error('something went wrong');
            $this->saveResponse();
            $this->badProxyDetection($res);

            $this->logger->notice("[Current URL]: {$this->http->currentUrl()}");

            throw new CheckRetryNeededException(5, 0);
        }

        $this->driver->executeScript("
            var cookieWarningAcceptId = document.querySelector('#allowCookiesButton');
 
            if (cookieWarningAcceptId) {
                cookieWarningAcceptId.click();
            }
        ");

        $this->waitFor(function () {
            return !$this->waitForElement(\WebDriverBy::xpath("//img[@alt='Loading Overlay']"), 0);
        }, 30);
        $but = $this->waitForElement(\WebDriverBy::xpath("//button[normalize-space()='Award ticket - Buy a ticket with Miles' or contains(@class,'AwardTicketButton_awardTicketButton')]"),
            0);
        $this->saveResponse();

        if ($but) {
            $but->click();

            if (!$this->waitForElement(WebDriverBy::xpath('//input[@id="tkNumber"]'), 5)) {
                // it's better restart, 99% block
                $this->driver->executeScript("document.getElementById('signinbtn').click();");

                if (!$this->waitForElement(WebDriverBy::xpath('//input[@id="tkNumber"]'), 5)) {
                    throw new \CheckRetryNeededException(5, 0);
                }
            }
        } else {
            $this->logger->debug('signin click');
            $signin->click();
        }
        $this->someSleep();
        $this->saveResponse();

        $usernameInput = $this->waitForElement(WebDriverBy::xpath('//input[@id="tkNumber"]'), 0);

        // it helps, sometimes click by 'sign in' not wroking
        if (!$usernameInput && $this->waitForElement(WebDriverBy::xpath(self::XPATH_SIGN_IN), 0)) {
            $this->saveResponse();
            unset($usernameInput);
            $this->driver->executeScript("if (document.querySelector('#signin')) document.querySelector('#signin').click(); else document.querySelector('#signinbtn').click();");
            $usernameInput = $this->waitForElement(WebDriverBy::id('tk-number'), 5);

            if (!$usernameInput) {
                $usernameInput = $this->waitForElement(WebDriverBy::id('tkNumber'), 0);
            }
        }

        if (!($passwordInput = $this->waitForElement(WebDriverBy::id('password'), 5))) {
            $passwordInput = $this->waitForElement(WebDriverBy::id('msPassword'), 0);
        }
        $signinBtn = $this->waitForElement(WebDriverBy::xpath('//a[@class="signinBTN"] | // button[@id="msLoginButton"]'),
            0);

        if (!$usernameInput || !$passwordInput || !$signinBtn) {
            $this->logger->error('something went wrong');

            if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_SUCCESSFUL), 0)) {
                $this->logger->notice("session is active, let's parse");

                return;
            }

            $this->saveResponse();

            throw new \CheckRetryNeededException(5, 0);
        }

        try {
            $usernameInput->sendKeys($this->AccountFields['Login']);
        } catch (\Facebook\WebDriver\Exception\ElementNotInteractableException $e) {
            $this->logger->error("[Exception]: {$e->getMessage()}");
            $this->saveResponse();

            if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_SUCCESSFUL), 0)) {
                $this->logger->notice("session is active, let's parse");

                return;
            }
        }

        try {
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->someSleep();
        } catch (\UnrecognizedExceptionException $e) {
            $this->logger->error("[Exception]: {$e->getMessage()}");
            $this->saveResponse();
        }
        $this->saveResponse();

        if ($signinBtn = $this->waitForElement(WebDriverBy::xpath('//a[@class="signinBTN"] | // button[@id="msLoginButton"]'),
            0)) {
            $signinBtn->click();
        }
        sleep(2);

        $this->logger->error('Mouse');
        $mover = new \MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = random_int(100000, 500000);
        $mover->steps = random_int(5, 10);

        $signinBtn = $this->waitForElement(WebDriverBy::xpath('//a[@class="signinBTN"] | // button[@id="msLoginButton"]'), 0);
        $this->saveResponse();

        if ($signinBtn) {
            try {
                $mover->moveToElement($signinBtn);
                $mover->click();
            } catch (\StaleElementReferenceException $e) {
                $this->logger->debug("click sign in Btn by mouseMover was prevented by StaleElementException");
            }
            sleep(1);
        }

        if ($signinBtn = $this->waitForElement(WebDriverBy::xpath('//a[@class="signinBTN"] | // button[@id="msLoginButton"]'), 0)) {
            $this->logger->debug("click sign in Btn");
            $this->driver->executeScript("
                if (document.querySelector(\"button[id='msLoginButton']\")) {
                    document.querySelector(\"button[id='msLoginButton']\").click();
                }
            ");
        }
    }

    private function badProxyDetection($res = null): void
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")] | //h1[contains(text(), "Secure Connection Failed")] | //span[contains(text(), "This site can’t be reached")]')
            || !$res
        ) {
//            $this->markProxyAsInvalid();
            throw new \CheckRetryNeededException(5, 0);
        }
    }

    private function getCabinFields($onlyKeys = true): array
    {
        $cabins = [
            'economy'        => 'ECONOMY',
            'premiumEconomy' => 'ECONOMY',
            'firstClass'     => 'BUSINESS',
            'business'       => 'BUSINESS',
        ];

        if ($onlyKeys) {
            return array_keys($cabins);
        }

        return $cabins;
    }

    private function ParseReward($fields, $PageRequestID)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("ParseReward [" . date("Y-m-d",
                $fields['DepDate']) . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);

        $cabin = $this->getCabinFields(false)[$fields['Cabin']];

        $this->http->GetURL("https://www.turkishairlines.com/en-int/miles-and-smiles/book-award-tickets");

        $this->saveResponse();

        if ($this->http->FindSingleNode('//span[contains(., "Take a short break from your passion for travel!")]')) {
            throw new \CheckRetryNeededException(5, 0);
        }

        $this->waitForElement(\WebDriverBy::xpath("//label[normalize-space()='From']"), 15);

        /** @var \SeleniumDriver $seleniumDriver */
        $seleniumDriver = $this->http->driver;

        try {
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
        } catch (BrowserCommunicatorException $e) {
            $this->logger->error('BrowserCommunicatorException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }
        $headers = [];

        foreach ($requests as $n => $xhr) {
            if (stripos($xhr->request->getUri(), 'information/messages/en/int') !== false) {
                $headers = $xhr->request->getHeaders();

                break;
            }
        }
        $this->logger->info(var_export($headers, true), ['pre' => true]);

        if (empty($headers)) {
            $this->logger->error("seems block(not load page etc), so restart");
            $this->saveResponse();

            throw new \CheckRetryNeededException(5, 0);
        }

        $dateStr = date("d-m-Y", $fields['DepDate']);
        $x_token = trim($this->driver->executeScript("return sessionStorage.getItem('X-Token');"), '"');
        $payload = '{"selectedBookerSearch":"O","selectedCabinClass":"' . $cabin . '","moduleType":"AWARD","passengerTypeList":[{"quantity":' . $fields['Adults'] . ',"code":"ADULT"}],"originDestinationInformationList":[{"originAirportCode":"' . $this->ports[$fields['DepCode']]['code'] . '","originMultiPort":' . json_encode($this->ports[$fields['DepCode']]['multi']) . ',"destinationAirportCode":"' . $this->ports[$fields['ArrCode']]['code'] . '","destinationMultiPort":' . json_encode($this->ports[$fields['ArrCode']]['multi']) . ',"departureDate":"' . $dateStr . '"}]}';
        $conversationId = $PageRequestID;
        $referer = 'https://www.turkishairlines.com/en-int/miles-and-smiles/book-award-tickets/availability';

        try {
//            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            $script = "
            var xhr = new XMLHttpRequest();
            xhr.withCredentials = true;
            xhr.open('POST', 'https://www.turkishairlines.com/api/v1/availability', false);
            xhr.setRequestHeader('Accept', 'application/json, text/plain, */*');
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.setRequestHeader('Origin', 'https://www.turkishairlines.com');
            xhr.setRequestHeader('X-Token', '{$x_token}');
            xhr.setRequestHeader('Referer', '{$referer}');
            xhr.setRequestHeader('X-country', 'int');
            xhr.setRequestHeader('X-conversationId', '{$conversationId}');
            xhr.setRequestHeader('X-clientId', '{$headers['X-clientId']}');
            xhr.setRequestHeader('X-requestId', '{$headers['X-requestId']}');
            xhr.setRequestHeader('X-bfp', '{$headers['X-bfp']}');
            xhr.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    resData = this.responseText;
                }
            };
            xhr.send('{$payload}');
            return resData;";
            $this->logger->debug('Run script: ' . PHP_EOL . $script, ['pre' => true]);

            $data = null;
            $resData = $this->driver->executeScript($script);

            if (is_string($resData) && strpos($resData, 'Error-DefaultErrorMessage') !== false) {
                $this->logger->debug('retry script above. Error-DefaultErrorMessage');
                sleep(random_int(1, 2));
                // helped
                $resData = $this->driver->executeScript($script);
            }

            if (is_string($resData)) {
                $data = $this->http->JsonLog($resData, 1, true);
            }
        } catch (\WebDriverException | \Facebook\WebDriver\Exception\WebDriverCurlException | \WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(5, 0);
        }

        if (empty($data)) {
            throw new CheckRetryNeededException(5, 0);
        }

        if (!isset($data['data']['originDestinationInformationList'])) {
            if ($data['statusDetailList'][0]['code'] === 'Error-BOS-10008') {
                $this->SetWarning('We are sorry to inform you that we are unable to process your transaction at the moment. You may try again in a short while, and if you encounter a similar situation, you may contact our call center.');

                return [];
            }

            if ($data['statusDetailList'][0]['code'] === 'ibs.booking.availability.label.error.noflights'
                || $data['statusDetailList'][0]['code'] === 'Error-BOS-25016') {
                $this->SetWarning('There are no flights for the day you look, you can search for the other days.');

                return [];
            }

            if ($data['statusDetailList'][0]['code'] === 'Error-DS-12019') {
                $this->SetWarning('There are no flights available for your selected date. Please try searching for flights on a different date.');

                return [];
            }

            if (!$this->validRoute($fields, true)) {
                return [];
            }

            if ($data['statusDetailList'][0]['code'] === 'Error-BOS-25097') {
                $this->sendNotification("check message 25097 // ZM");
            } else {
                if ($data['statusDetailList'][0]['code'] !== 'Error-DefaultErrorMessage') {
                    $this->sendNotification('hasNoData // ZM');
                }
            }

            throw new \CheckException('something wrong', ACCOUNT_ENGINE_ERROR);
        }

        if (isset($data['data']['originDestinationInformationList'][0]['soldOutAllFlights'])
            && $data['data']['originDestinationInformationList'][0]['soldOutAllFlights']) {
            $this->SetWarning('No flights');

            return [];
        }
        // headers for getPrice
        $headers = [
            "Accept"          => "application/json, text/plain, */*",
            "Accept-Language" => "en",
            "Content-Type"    => "application/json",
            "X-clientId"      => $headers['X-clientId'],
            "X-bfp"           => $headers['X-bfp'],
            "X-country"       => "int",
            "X-conversationId"=> $conversationId,
            "X-Token"         => $x_token,
        ];

        return $this->parseRewardFlightsJson($data, $fields, $headers);
    }

    private function getPriceDetails($fields, $headers, $segmentList, $bookingPriceInfoList, $id)
    {
        if (!$this->sendResetFlight($fields, $headers, $segmentList, $bookingPriceInfoList, $id)) {
            return null;
        }

        if (null === ($requestId = $this->generateRequestId())) {
            throw new \CheckException("failed generate requestId", ACCOUNT_ENGINE_ERROR);
        }
        $headers['X-requestId'] = $requestId;

        $list = $segmentList;

        foreach ($segmentList as $num=>$segment) {
            $list[$num]['cabinType'] = $bookingPriceInfoList['cabinType'];
            $list[$num]['fareBasisCode'] = $bookingPriceInfoList['fareBasisCodeList'][$num];
            $list[$num]['resBookDesigCode'] = $bookingPriceInfoList['resBookDesigCodeList'][$num];
        }
        $priceUrl = "https://www.turkishairlines.com/api/v1/fare/fare-quote-for-award-ticket";
        $payload = "{\"optionList\":[{\"segmentList\":" .
            json_encode($list) .
            "}],\"passengerList\":[{\"quantity\":{$fields['Adults']},\"code\":\"ADULT\"}],\"currency\":\"MILE\"}";
        $referrer = "https://www.turkishairlines.com/en-int/miles-and-smiles/book-award-tickets/availability";
//        $res = $this->getFetchDetails($id, $priceUrl, $headers, $payload, $referrer);
        usleep(random_int(7, 35) * 100000);

        try {
            $res = $this->getXHRDetails($id, $priceUrl, $headers, $payload, $referrer);
        } catch (\WebDriverException | \WebDriverCurlException $e) {
            $this->logger->error('WebDriverException: ' . $e->getMessage());

            return null;
        }
        $detales = null;
        // debug
        if (strpos($res, 'Error-BOS-') !== false
            && strpos($res, 'Error-BOS-16013') === false
            && strpos($res, 'Error-BOS-17030') === false
        ) {
            $detales = $this->http->JsonLog($res, 1, false);

            if (strpos($res, 'Error-BOS-17173') !== false) {
                $this->sendNotification($detales->statusDetailList[0]->code . " // ZM");
            }
            // 17173 - retry helped
            $detales = null;

            try {
                $res = $this->getXHRDetails($id, $priceUrl, $headers, $payload, $referrer);
            } catch (\WebDriverException | \WebDriverCurlException $e) {
                $this->logger->error('WebDriverException: ' . $e->getMessage());

                return null;
            }
        }

        if (!empty($res)) {
            $detales = $this->http->JsonLog($res, 1, false);
        }

        if (isset($detales->success) && $detales->success === false
            && isset($detales->statusDetailList[0]->code)
        ) {
            switch ($detales->statusDetailList[0]->code) {
                case 'Error-BOS-16013':
                    return "All our seats in the fare class you have selected are full";

                case 'Error-BOS-28002':// xz
                case 'Error-BOS-29008':// xz
                case 'Error-BOS-17173':// xz
                case 'Error-BOS-17030':
                case 'Error-DefaultErrorMessage': // sometimes retry above not work
                    return "Your transaction cannot be completed because there are no seats available on the flight you selected. Please check the flight's availability and try again.";

                default:
//                    $this->sendNotification("check error on getPriceDetails // ZM");

                    break;
            }
        }

        /** @var \SeleniumDriver $seleniumDriver */
        $seleniumDriver = $this->http->driver;
        // reset records every time (check it only if no tax) DEBUG
        $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

        if (!isset($detales->data, $detales->data->tax)) {
            usleep(random_int(7, 35) * 100000);

            $this->logger->error(var_export($res, true));

            $headers = [];

            foreach ($requests as $n => $xhr) {
                if (stripos($xhr->request->getUri(), 'api/v1/fare/fares-initiate-for-award-ticket') !== false) {
                    $this->logger->info(var_export($xhr, true), ['pre' => true]);
                }
            }

            return null;
            // TODO retry
//            $this->sendNotification("can't get tax payments // ZM");

//            throw new \CheckException("can't get tax payments", ACCOUNT_ENGINE_ERROR);
            throw new \CheckRetryNeededException(5, 0);
        }

        return $detales;
    }

    private function sendResetFlight($fields, $headers, $segmentList, $bookingPriceInfoList, $id)
    {
        if (null === ($requestId = $this->generateRequestId())) {
            throw new \CheckException("failed generate requestId", ACCOUNT_ENGINE_ERROR);
        }
        $headers['X-requestId'] = $requestId;

        $resetUrl = "https://www.turkishairlines.com/api/v1/availability/info-by-ond";
        $payload = '{"availabilityInfoByOndItemList":[{"departureAirportCode":"' . $this->ports[$fields['DepCode']]['code'] . '","departureCountryCode":"' . $this->ports[$fields['DepCode']]['country']['code'] . '","departureAirportDomestic":' . json_encode($this->ports[$fields['DepCode']]['domestic']) . ',"arrivalAirportCode":"' . $this->ports[$fields['ArrCode']]['code'] . '","arrivalCountryCode":"' . $this->ports[$fields['ArrCode']]['country']['code'] . '","arrivalAirportDomestic":' . json_encode($this->ports[$fields['ArrCode']]['domestic']) . '}]}';
        $referrer = "https://www.turkishairlines.com/en-int/miles-and-smiles/book-award-tickets/availability";
        usleep(random_int(7, 35) * 100000);
        $res = $this->getXHRDetails($id, $resetUrl, $headers, $payload, $referrer);
        $detales = null;

        if (!empty($res)) {
            $detales = $this->http->JsonLog($res, 1, false);
        }

        if (!isset($detales->success)) {
            usleep(random_int(7, 35) * 100000);

            $this->logger->error(var_export($res, true));

            return false;
        }

        return true;
    }

    private function parseRewardFlightsJson($data, $fields, $headers): array
    {
        $this->logger->notice(__METHOD__);
        $routes = [];
        $defaultCurrency = null;

        foreach ($data['data']['originDestinationInformationList'][0]['originDestinationOptionList'] as $numRoute=>$list) {
            $this->logger->debug('route #' . $numRoute);

            if ($list['soldOut']) {
                $this->logger->debug('soldOut');

                continue;
            }
//            $headers['X-conversationId'] = $this->generateRequestId();
            $headers['Origin'] = 'https://www.turkishairlines.com';
            $headers['Accept-Encoding'] = 'gzip, deflate, br, zstd';
            $headers['Sec-Fetch-Dest'] = 'empty';
            $headers['Sec-Fetch-Mode'] = 'cors';
            $headers['Sec-Fetch-Site'] = 'same-origin';
            $headers['Pragma'] = 'no-cache';
            $headers['Cache-Control'] = 'no-cache';

            foreach ($list['fareCategory'] as $fare => $fareDetails) {
                $this->logger->debug('fare: ' . $fare);

                if ($fareDetails['status'] !== 'AVAILABLE') {
                    $this->logger->debug('not AVAILABLE (' . $fareDetails['status'] . ')');

                    if ($fareDetails['status'] !== 'SOLDOUT_OR_NOCABIN' && $fareDetails['status'] !== 'SOLDOUT') {
                        $this->sendNotification($fareDetails['status']);
                    }

                    continue;
                }

                foreach ($fareDetails['bookingPriceInfoList'] as $bookingPriceInfoList) {
                    $details = $this->getPriceDetails($fields, $headers, $list['segmentList'], $bookingPriceInfoList,
                        $fare . $numRoute);

                    if (is_string($details)) {
                        $this->logger->error($details);

                        continue;
                    }

                    if (null === $details) {
                        $this->SetWarning('it is possible that flights are collected without taxes');
                        $numStops = count($list['segmentList']) ?? 0;
                        $taxes = null;
                        $miles = $bookingPriceInfoList['referencePassengerFare']['totalFare']['amount'];
                    } else {
                        $numStops = count($list['segmentList']) ?? 0;
                        $taxes = $details->data->tax;
                        $miles = $details->data->mile->amount;

                        if (!isset($defaultCurrency)) {
                            $defaultCurrency = $taxes->currencyCode;
                        }
                    }

                    $route = [
                        'distance'    => null,
                        'num_stops'   => $numStops,
                        'redemptions' => [
                            'miles'   => $miles,
                            'program' => $this->AccountFields['ProviderCode'],
                        ],
                        'payments' => [
                            'currency' => $taxes->currencyCode ?? $defaultCurrency,
                            'taxes'    => $taxes->amount ?? null,
                            'fees'     => null,
                        ],
                        'award_type'     => $fare,
                        'classOfService' => ucwords($this->convertFareBasisCodeToCabin($fare)),
                        'connections'    => [],
                    ];

                    if ($route['payments']['currency'] === null) {
                        $redefineCurrency = true;
                    }

                    switch ($fare) {
                        case 'ECONOMY':
                            if (isset($list['lastSeatCount']['eco'])) {
                                $route['tickets'] = $list['lastSeatCount']['eco'];
                            }

                            break;

                        case 'BUSINESS':
                            if (isset($list['lastSeatCount']['business'])) {
                                $route['tickets'] = $list['lastSeatCount']['business'];
                            }

                            break;
                    }

                    $numStops = -1;

                    foreach ($list['segmentList'] as $numSegment => $segment) {
                        $numStops++;
                        $route['connections'][] = [
                            'num_stops' => 0,
                            'departure' => [
                                'date'     => date('Y-m-d H:i', strtotime($segment['departureDateTime'])),
                                'dateTime' => strtotime($segment['departureDateTime']),
                                'airport'  => $segment['departureAirportCode'],
                            ],
                            'arrival' => [
                                'date'     => date('Y-m-d H:i', strtotime($segment['arrivalDateTime'])),
                                'dateTime' => strtotime($segment['arrivalDateTime']),
                                'airport'  => $segment['arrivalAirportCode'],
                            ],
                            'cabin'      => $this->convertFareBasisCodeToCabin($bookingPriceInfoList['cabinType']),
                            'fare_class' => $bookingPriceInfoList['fareBasisCodeList'][$numSegment],
                            // $segment->fareBasisCode,
                            'aircraft' => $segment['equipmentCode'] ?? null,
                            'flight'   => [$segment['flightCode']['airlineCode'] . $segment['flightCode']['flightNumber']],
                            'airline'  => $segment['flightCode']['airlineCode'],
                        ];
                    }
                    $route['num_stops'] = $numStops;

                    $routes[] = $route;
                }
            }
        }

        if (isset($redefineCurrency)) {
            $oldRoutes = $routes;
            $defaultCurrency = $defaultCurrency ?? 'USD';

            foreach ($oldRoutes as $n => $r) {
                if (empty(['payments']['currency'])) {
                    $routes[$n]['payments']['currency'] = $defaultCurrency;
                }
            }
        }

//        if (isset($skipFareNotTax)) {
//            $this->sendNotification("skipFareNotTax // ZM");
//        }

        $this->logger->debug(var_export($routes, true), ['pre' => true]);

        return $routes;
    }

    private function getFetchDetails($id, $url, array $headers, $payload, $referer)
    {
        $this->logger->notice(__METHOD__);

        if (is_array($headers)) {
            $headers = json_encode($headers);
        }
        $payload = base64_encode($payload);
        $script = '
                fetch("' . $url . '", {
                  "headers": ' . $headers . ',
                  "referrer": "' . $referer . '",
                  "body": atob("' . $payload . '"),
                  "method": "POST",
                  "mode": "cors",
                  "credentials": "include"
                }).then( response => response.json())
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
                });
            ';
        $this->logger->debug("[run script]:");
        $this->logger->debug($script, ['pre'=>true]);
        $this->driver->executeScript($script);

        $this->waitForElement(\WebDriverBy::xpath('//*[self::script or self::div][@id="' . $id . '"]'), 20, false);

        $ext = $this->waitForElement(\WebDriverBy::xpath('//script[@id="' . $id . '"]'), 0, false);
        $this->saveResponse();

        if (!$ext) {
            $this->waitForElement(\WebDriverBy::xpath('//div[@id="' . $id . '"]'), 0, false);

            return null;
        }

        return $ext->getAttribute($id);
    }

    private function getXHR($method, $url, array $headers, $payload)
    {
        $headersString = "";

        foreach ($headers as $key=>$value) {
            $headersString .= 'xhttp.setRequestHeader("' . $key . '", "' . $value . '");
        ';
        }

        if (is_array($payload)) {
            $payload = json_encode($payload);
        }
        $payload = base64_encode($payload);
//        var data = JSON.stringify(' . $payload . ');
        $script = '
                var xhttp = new XMLHttpRequest();
                xhttp.withCredentials = true;
                xhttp.open("' . $method . '", "' . $url . '", false);
                ' . $headersString . '
                var data = atob("' . $payload . '");
                var responseText = null;
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && (this.status == 200 || this.status == 202)) {
                        responseText = this.responseText;
                    }
                };
                xhttp.send(data);
                return responseText;
            ';
        $this->logger->debug("[run script]:");
        $this->logger->debug($script, ['pre'=>true]);

        $resData = $this->driver->executeScript($script);

        if (is_string($resData) && strpos($resData, 'Error-DefaultErrorMessage') !== false) {
            sleep(1);
            // helped
            $resData = $this->driver->executeScript($script);
        }

        return $resData;
    }

    private function getXHRDetails($id, $url, array $headers, $payload, $referer)
    {
        $this->logger->notice(__METHOD__);

        if (!is_array($headers)) {
            return null;
        }

        if (!isset($headers['Referer'])) {
            $headers['Referer'] = $referer;
        }

        return $this->getXHR("POST", $url, $headers, $payload);
    }

    private function parseRewardFlights($data, $PageRequestID, $cId, $fields, $isAlliance, $url): array
    {
        $this->logger->notice(__METHOD__);
        $routes = [];

        if (count($data->data->routes) > 1 || count($data->data->routes[0]->days) > 1) {
            $this->sendNotification("check routes // ZM");
        }
        $dataRoutes = $data->data->routes[0]->days[0];
        $this->logger->debug("Found " . count($dataRoutes->flights) . " routes");
        $currency = $fields['Currencies'][0];

        if ($currency !== $data->data->originalCurrency->code) {
            $headers = [
                'Accept'           => '*/*',
                'cId'              => $cId,
                'Content-Type'     => 'application/json; charset=utf-8',
                'country'          => 'us',
                'page'             => $url,
                'pageRequestId'    => $PageRequestID,
                'Referer'          => $url,
                'X-Requested-With' => 'XMLHttpRequest',
            ];
            $getPrice = true;
        }

        foreach ($dataRoutes->flights as $numRoute => $it) {
            $this->logger->info('route #' . $numRoute);
            $this->http->JsonLog(json_encode($it), 1);

            if (is_null($it->availableFlightFare)) {
                $this->skipped = true;

                continue;
            }
            $this->logger->debug("Found " . count($it->availableFlightFare->fareBreakdowns) . " offers");

            foreach ($it->availableFlightFare->fareBreakdowns as $numOffer => $itOffer) {
                $fareBasisCode = $itOffer->fareBasisCode;

                $this->logger->info('offer #' . $numOffer);
                $result = [
                    'distance'    => null,
                    'num_stops'   => $it->countOfAllStops,
                    'redemptions' => [
                        'miles'   => $itOffer->passengerFareBaseFareAmount,
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $itOffer->passengerFareTotalFareCurrencyCode,
                        'taxes'    => $itOffer->passengerFareTotalFareAmount,
                        'fees'     => null,
                    ],
                    'award_type'     => $this->convertFareBasisCodeToText($fareBasisCode),
                    'classOfService' => ucwords($this->convertFareBasisCodeToCabin($fareBasisCode)),
                    'connections'    => [],
                ];

                if (isset($getPrice)) {
                    if (null === ($requestId = $this->generateRequestId())) {
                        throw new \CheckException("failed generate requestId", ACCOUNT_ENGINE_ERROR);
                    }
                    $headers['requestId'] = $requestId;
                    $it->availableFlightFare->preferedFareBasisCode = $fareBasisCode;

                    $priceUrl = "https://www.turkishairlines.com/com.thy.web.online.ibs/ibs/awardticketbooking/bookingcalculatedprice";

                    if ($isAlliance) {
                        $priceUrl .= "star";
                        $payload = "{\"flights\":["
                            . substr(json_encode($it), 0, -1)
                            . "],\"passengers\":[{\"code\":\"ADULT\",\"quantity\":{$fields['Adults']}}]}";
                    } else {
                        $payload = "{\"flights\":["
                            . substr(json_encode($it), 0, -1)
                            . ",\"hasISODT\":true,\"routeIndex\":0,\"index\":{$it->id}}],\"passengers\":[{\"code\":\"ADULT\",\"quantity\":{$fields['Adults']}}]}";
                    }
                    $this->http->RetryCount = 0;
                    $this->http->PostURL($priceUrl, $payload, $headers);
                    $this->http->RetryCount = 2;
                    $priceData = $this->http->JsonLog(null, 1, false, $currency);

                    if (isset($priceData->type) && $priceData->type === 'ERROR') {
                        sleep(2);
                        $this->http->RetryCount = 0;
                        $this->http->PostURL("https://www.turkishairlines.com/com.thy.web.online.ibs/ibs/awardticketbooking/bookingcalculatedprice",
                            $payload, $headers);
                        $this->http->RetryCount = 2;
                        $priceData = $this->http->JsonLog(null, 1, false, $currency);
                        $this->sendNotification("check retry // ZM");
                    }

                    if (!isset($priceData->data, $priceData->data->convertedTotalTaxPrices->$currency)) {
//                        $this->sendNotification("can't get tax payments // ZM");

                        throw new \CheckException("can't get tax payments", ACCOUNT_ENGINE_ERROR);
                    }
                    $priceConverted = $priceData->data->convertedTotalTaxPrices->$currency;
                    $result['payments'] = [
                        'currency' => $priceConverted->currency, // $priceData->data->taxCurrency,
                        'taxes'    => $priceConverted->amount, // $priceData->data->totalTax,
                        'fees'     => null,
                    ];
                }

                $tickets = 100;

                foreach ($it->segments as $numSeg => $segment) {
                    $this->logger->info('segment #' . $numSeg);

                    $this->http->JsonLog(json_encode($segment));
                    $seg = [
                        'arrivalDateTimeDisplay' => strtotime($segment->arrivalDateTimeDisplay), // for calc layover
                        'num_stops'              => $segment->stopQuantity,
                        'departure'              => [
                            'date' => date('Y-m-d H:i', strtotime($segment->departureDateTimeISO->hourMinuteLocal,
                                strtotime($segment->departureDateTimeISO->dateLocal))),
                            'dateTime' => strtotime($segment->departureDateTimeISO->hourMinuteLocal,
                                strtotime($segment->departureDateTimeISO->dateLocal)),
                            'airport' => $segment->originAirport->code,
                        ],
                        'arrival' => [
                            'date' => date('Y-m-d H:i', strtotime($segment->arrivalDateTimeISO->hourMinuteLocal,
                                strtotime($segment->arrivalDateTimeISO->dateLocal))),
                            'dateTime' => strtotime($segment->arrivalDateTimeISO->hourMinuteLocal,
                                strtotime($segment->arrivalDateTimeISO->dateLocal)),
                            'airport' => $segment->destinationAirport->code,
                        ],
                        'meal'       => null,
                        'cabin'      => $this->convertFareBasisCodeToCabin($fareBasisCode),
                        'fare_class' => null,
                        'distance'   => null,
                        'aircraft'   => $segment->equipment->airEquipType,
                        'flight'     => [$segment->flightNumber],
                        'airline'    => $this->http->FindPreg("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])/", false, $segment->flightNumber),
                        'operator'   => $segment->airline->shortName,
                        'times'      => [
                            'layover' => null,
                        ],
                        'tickets' => $segment->remainingSeats->$fareBasisCode ?? null,
                    ];

                    if (isset($seg['tickets'])) {
                        $tickets = min($tickets, $seg['tickets']);
                    }
                    $result['connections'][] = $seg;
                }

                if ($tickets < 100) {
                    $result['tickets'] = $tickets;
                } else {
                    $result['tickets'] = null;
                }
                $this->logger->debug(var_export($result, true), ['pre' => true]);
                $routes[] = $result;
            }
        }

        return $routes;
    }

    private function getListCurrencies($goToSite = true): array
    {
        // они сами конвертят, отображение через раз. пока игра не стоит свеч
        return ['TRY'];
    }

    private function generateRequestId(): ?string
    {
        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
        $script = "
            G=function()
            {
              var e= (new Date).getTime()+Math.random()*Math.random(),
              t=\"xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx\".replace(/[xy]/g, function(t){
              var n=0|(e+16*Math.random())%16;return e=Math.floor(e/16),
              (\"x\"===t?n:8|3&n).toString(16)
            });return t}
            sendResponseToPhp(G());
        ";

        try {
            $requestId = $jsExecutor->executeString($script);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        if (!$requestId) {
            $this->logger->error('Failed to encrypt offerAuth');

            return null;
        }

        return $requestId;
    }

    private function convertFareBasisCodeToCabin(string $fareBasisCode): ?string
    {
        $arrayFareBasisCodes = [
            'FREEECONOMYG'   => 'economy',
            'FREEECONOMYNG'  => 'economy',
            'ECONOMY'        => 'economy',
            'BUSINESS'       => 'business',
            'FREEBUSINESSG'  => 'business',
            'FREEBUSINESSNG' => 'business',
        ];

        if (isset($arrayFareBasisCodes[$fareBasisCode])) {
            return $arrayFareBasisCodes[$fareBasisCode];
        }
        $this->sendNotification("check FareBasisCodeToCabin {$fareBasisCode} // ZM");

        return null;
    }

    private function convertFareBasisCodeToText(string $fareBasisCode): ?string
    {
        $arrayFareBasisCodes = [
            'FREEECONOMYG'   => 'Economy Award Ticket',
            'FREEECONOMYNG'  => 'Economy Award Ticket (Promotion)',
            'FREEBUSINESSG'  => 'Business Award Ticket',
            'FREEBUSINESSNG' => 'Business Award Ticket (Promotion)',
        ];

        if (isset($arrayFareBasisCodes[$fareBasisCode])) {
            return $arrayFareBasisCodes[$fareBasisCode];
        }
        $this->sendNotification("check FareBasisCodeToCabin {$fareBasisCode} // ZM");

        return null;
    }

    private function someSleep()
    {
        usleep(random_int(12, 25) * 100000);
    }
}
