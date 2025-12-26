<?php

namespace AwardWallet\Engine\iberia\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use CheckException;
use CheckRetryNeededException;
use Facebook\WebDriver\Exception\UnknownErrorException;
use HttpBrowser;
use MouseMover;
use SeleniumFinderRequest;
use UnexpectedJavascriptException;
use WebDriverBy;

class ParserNew extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;
    private const CONFIGS = [
        //        'chrome-95' => [
        //            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
        //            'browser-version' => \SeleniumFinderRequest::CHROME_95,
        //        ],
        //        'chrome-99' => [
        //            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
        //            'browser-version' => \SeleniumFinderRequest::CHROME_99,
        //        ],
        //        'chrome-100' => [
        //            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
        //            'browser-version' => \SeleniumFinderRequest::CHROME_100,
        //        ],
        //        'chrome-94' => [
        //            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
        //            'browser-version' => \SeleniumFinderRequest::CHROME_94,
        //        ],
        //        'chrome-pup-100' => [
        //            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME_PUPPETEER,
        //            'browser-version' => \SeleniumFinderRequest::CHROME_PUPPETEER_100,
        //        ],
        //        'chrome-pup-103' => [
        //            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME_PUPPETEER,
        //            'browser-version' => \SeleniumFinderRequest::CHROME_PUPPETEER_103,
        //        ],
        //        'chrome-pup-104' => [
        //            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME_PUPPETEER,
        //            'browser-version' => \SeleniumFinderRequest::CHROME_PUPPETEER_104,
        //        ],
        //        'firefox-100' => [
        //            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
        //            'browser-version' => \SeleniumFinderRequest::FIREFOX_100,
        //        ],
        'firefox-playwright-100' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_100,
        ],
        'firefox-playwright-101' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_101,
        ],
        'firefox-playwright-102' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_102,
        ],
        //        'firefox-playwright-103' => [
        //            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
        //            'browser-version' => \SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_103,
        //        ],
        //        'firefox-84' => [
        //            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
        //            'browser-version' => \SeleniumFinderRequest::FIREFOX_84,
        //        ],
    ];
    private const ATTEMPT_REQUEST_LIMIT = 94;
    private const PARSED_ROUTES_LIMIT = 40;
    private const REQUEST_TIMEOUT = 10;
    private const MAX_LOGIN_FAILS = 45;
    private const XPATH_ERRORS = '//p[
            contains(normalize-space(),"Sorry, an error has occurred. Please try again later.")
            or contains(normalize-space(),"Lo sentimos, se ha producido un error. ")
            or contains(normalize-space(),"Lamentamos, occorreu um erro. ")
            or contains(normalize-space(),"An error has occurred in the Login.")
        ]
        | //div[@id = "Error_Subtitulo" and contains(text(), "The connection was interrupted due to an error,")]
    ';

    public $isRewardAvailability = true;

    private $config;
    private $systemErrorFare = false;

    private $mover;

    // TODO
    private $isLoggedIn = false;

    public static function getRASearchLinks(): array
    {
        return ['https://www.iberia.com/us/search-engine-flights-with-avios/' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->debugMode = $this->AccountFields['DebugState'] ?? false;

        $this->UseSelenium();
        $this->usePacFile(false);
        $this->setConfig();

//        $array  = [ 'us', 'ca', 'fi', 'au', 'fr'];
//        $targeting = $array[array_rand($array)];
//
        if ($this->AccountFields['ParseMode'] === 'awardwallet') {
            $this->setProxyGoProxies();
        } else {
            $array = ['pt', 'es'];
            $targeting = $array[array_rand($array)];

            switch (rand(0, 1)) {
                case 0:
                    $this->setProxyNetNut(null, $targeting);

                    break;

                case 1:
                    $this->setProxyGoProxies(null, $targeting);

                    break;

//                case 2:
//                    $this->setProxyBrightData(null, "static", $targeting);
//
//                    break;
            }
        }

//        if (self::CONFIGS[$this->config]['browser-family'] === SeleniumFinderRequest::BROWSER_FIREFOX
//            || self::CONFIGS[$this->config]['browser-family'] === SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT) {
//            $request = FingerprintRequest::firefox();
//        } else {
//            $request = FingerprintRequest::chrome();
//        }

//        $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
//        $request->platform = array_rand([
//            'MacIntel',
//            'Win64',
//            'Win32',
//        ]);  //(random_int(0, 1)) ? 'MacIntel' : 'Win64';
//        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);
//
//        if (isset($fingerprint)
//                && self::CONFIGS[$this->config]['browser-version'] !== SeleniumFinderRequest::CHROME_94
//                && self::CONFIGS[$this->config]['browser-version'] !== SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_101
//                && self::CONFIGS[$this->config]['browser-version'] !== SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_102
//            ) {
//            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
//            $this->http->setUserAgent($fingerprint->getUseragent());
//            $this->seleniumOptions->userAgent = $fingerprint->getUseragent();
//        } else {
//            $os = [
//                SeleniumFinderRequest::OS_WINDOWS,
//                SeleniumFinderRequest::OS_MAC,
//                SeleniumFinderRequest::OS_MAC_M1,
//            ];
//            $this->seleniumRequest->setOs($os[array_rand($os)]);
//            $this->seleniumOptions->addHideSeleniumExtension = false;
//            $this->seleniumOptions->userAgent = null;
//        }
//
//        $this->seleniumRequest->request(
//            self::CONFIGS[$this->config]['browser-family'],
//            self::CONFIGS[$this->config]['browser-version']
//        );

        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->addPuppeteerStealthExtension = false;

        $this->http->setRandomUserAgent(null, false, false, true, false, false, true, true);

        $this->seleniumRequest->setHotSessionPool(self::class, $this->AccountFields['ProviderCode']);

//        if (self::CONFIGS[$this->config]['browser-version'] !== SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_101) {
        $this->http->saveScreenshots = true;
//        }
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL('https://www.iberia.com/us/search-engine-flights-with-avios/');

        if ($this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Fly with Avios")] | //span[@id="loggedUserName"]'), 10)) {
            $this->isLoggedIn = true;

            return true;
        }

        $this->saveResponse();

        return false;
    }

    public function LoadLoginForm()
    {
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        $this->driver->executeScript("Object.defineProperty(navigator, 'webdriver', {get: () => undefined})");

        if ($this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Fly with Avios")] | //span[@id="loggedUserName"]'), 5)) {
            $this->isLoggedIn = true;

            return true;
        }

        try {
            if (!stristr($this->http->currentUrl(), '/IDY_LoginPage')) {
                /*if ($this->IsLoggedIn()) {
                    return true;
                }*/
                $this->http->GetURL("https://www.iberia.com/?language=en");
                sleep(random_int(3, 5));
                $this->http->GetURL("https://www.iberia.com/integration/ibplus/login/");
            }
        } catch (UnknownErrorException $e) {
            $this->logger->error("Facebook\WebDriver\Exception\UnknownErrorException: " . $e->getMessage(),
                ['pre' => true]);

            throw new CheckRetryNeededException(3, 0);
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException exception on saveResponse: " . $e->getMessage(),
                ['pre' => true]);
            sleep(5);
        }

        $this->waitForElement(WebDriverBy::xpath('//input[@id = "loginPage:theForm:loginEmailInput"] | //input[@id = "iberia-plus"] | //h1[contains(text(), "Access Denied")] | //div[contains(text(), "The connection was interrupted due to an error,")] | //span[contains(text(), "This site can") or contains(text(), "t be reached")] | //button[@id = "onetrust-accept-btn-handler"]'),
            10);

        if ($accept = $this->waitForElement(WebDriverBy::xpath('//button[@id = "onetrust-accept-btn-handler"] | //button[@name = "acceptCookie"]'),
            5)) {
            $this->saveResponse();

            $accept->click();
            $this->waitForElement(WebDriverBy::xpath('//input[@id = "loginPage:theForm:loginEmailInput"] | //input[@id = "iberia-plus"] | //h1[contains(text(), "Access Denied")] | //div[contains(text(), "The connection was interrupted due to an error,")] | //span[contains(text(), "This site can") or contains(text(), "t be reached")]'),
                10);
        }

        if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "The connection was interrupted due to an error,")] | //h1[contains(text(), "Access Denied")] | //span[contains(text(), "This site can") or contains(text(), "t be reached")]'),
            0)) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
            /*$this->saveResponse();

            $this->http->GetURL("https://www.iberia.com/integration/ibplus/login/");
            $this->waitForElement(WebDriverBy::xpath('//input[@id = "loginPage:theForm:loginEmailInput"] | //input[@id = "iberia-plus"] | //h1[contains(text(), "Access Denied")] | //div[contains(text(), "The connection was interrupted due to an error,")] | //span[contains(text(), "This site can") or contains(text(), "t be reached")]'), 10);*/
        }
        $this->saveResponse();

        if ($this->waitForElement(WebDriverBy::xpath('//input[@id = "loginPage:theForm:loginEmailInput"]'), 0)) {
            return true;
        }

        return false;
    }

    public function Login()
    {
        if ($this->isLoggedIn) {
            return true;
        }

        if (!isset($this->AccountFields['Login'])) {
            throw new CheckException('no account for login', ACCOUNT_ENGINE_ERROR);
        }
        $this->AccountFields['Login'] = trim(preg_replace("/(?:^IB\s*|[^[:print:]\r\n])/ims", "",
            $this->AccountFields['Login']));
        $this->AccountFields['Pass'] = preg_replace("/^[^[:print:]]*/ims", "",
            $this->AccountFields['Pass']);

        $form = '//form[@id = "loginPage:theForm"]';
        $loginInput = $this->waitForElement(WebDriverBy::xpath($form . '//input[@id = "loginPage:theForm:loginEmailInput"]'),
            0);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath($form . '//input[@id = "loginPage:theForm:loginPasswordInput"]'),
            0);
        $button = $this->waitForElement(WebDriverBy::xpath($form . '//input[@id = "loginPage:theForm:loginSubmit"]'),
            0);

        if (!$loginInput || !$passwordInput || !$button) {
            $this->logger->error("something went wrong");
            $this->saveResponse();
            $this->checkPage();

            return $this->checkErrors();
        }

        if (self::CONFIGS[$this->config]['browser-version'] == SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_101
            || self::CONFIGS[$this->config]['browser-version'] == SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_102) {
            $mover = new MouseMover($this->driver);
            $mover->logger = $this->logger;
            $mover->duration = rand(300, 1000);
            $mover->steps = rand(10, 20);

            //$mover->moveToElement($loginInput);
            $mover->click();
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 15);

            //$mover->moveToElement($passwordInput);
            $mover->click();
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 25);

            sleep(random_int(1, 3));

            $this->saveResponse();
            $mover->moveToElement($button);
            $mover->click();
        } else {
            $this->saveResponse();
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            sleep(random_int(1, 3));
            $this->driver->executeScript("
                if (document.getElementById('loginFormLayout')) {
                    $('#loginFormLayout button.inte-submitIBP:contains(\"Go\")').click();
                }
                else
                    document.querySelector('input[id = \"loginPage:theForm:loginSubmit\"]').click();
            ");
        }

        $this->logger->debug('need delay');
        $block = $this->waitForElement(WebDriverBy::xpath('//div[@id="userErrorController"]/label'), 7);

        $this->saveResponse();

        if ($block) {
            $this->saveResponse();

            if (stripos($block->getText(), 'email might not be registered') !== false) {
                throw new \CheckException($block->getText(), ACCOUNT_INVALID_PASSWORD);
            }

            if (stripos($block->getText(), 'Plus number does not correspond') !== false) {
                $this->markConfigSuccess();

                throw new \CheckException($block->getText(), ACCOUNT_LOCKOUT);
            }

            $this->sendNotification($block->getText() . ' //DM');

            throw new \CheckException($block->getText(), ACCOUNT_PREVENT_LOCKOUT);
        }

        $this->checkPage();

        $result = $this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Fly with Avios")] | //span[@id="loggedUserName"]'),
            35);

        $this->saveResponse();

        if ($result) {
            $this->http->GetURL('https://www.iberia.com/us/search-engine-flights-with-avios/');
            sleep(3);
            $this->markConfigSuccess();
            $this->saveResponse();

            return true;
        } else {
            $this->markConfigAsBad();

            return false;
        }
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
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        if ($fields['Currencies'][0] !== 'USD') {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if ($fields['Adults'] > 9) {
            $this->SetWarning("It's too much travellers");

            return ["routes" => []];
        }

        $day = date('d', $fields['DepDate']);
        $month = date('Ym', $fields['DepDate']);
        $year = date('Y', $fields['DepDate']);

//        if (!stristr($this->http->currentUrl(), '/IDY_LoginPage')) {
//            $this->http->GetURL("https://www.iberia.com/us/");
//            $this->waitForElement(WebDriverBy::xpath('//span[@id="loggedUserName"]'), 15);
//            $this->saveResponse();
//        }

        if (strpos($this->http->currentUrl(), '/search-engine-flights-with-avios/') === false) {
            try {
                $this->http->GetURL('https://www.iberia.com/us/search-engine-flights-with-avios/');
//            $this->http->GetURL("https://www.iberia.com/flights/?market=US&language=en&appliesOMB=false&splitEndCity=false&initializedOMB=true&flexible=true&TRIP_TYPE=1&BEGIN_CITY_01={$fields['DepCode']}&END_CITY_01={$fields['ArrCode']}&BEGIN_DAY_01={$day}&BEGIN_MONTH_01={$month}&BEGIN_YEAR_01={$year}&FARE_TYPE=R&quadrigam=IBHMPA&ADT={$fields['Adults']}&CHD=0&INF=0&boton=Search&bookingMarket=US&pagoAvios=true#!/availability");
            } catch (\WebDriverException $e) {
                $this->logger->error("WebDriverException: " . $e->getMessage());
            }

            $this->waitForElement(\WebDriverBy::xpath('//button[@id="bbki-segment-info-segment-cabin-E-btn"]'), 10,
                false);
            $this->saveResponse();
        }
        $reLogin = false;

        if (stristr($this->http->currentUrl(), '/IDY_LoginPage')) {
            $this->isLoggedIn = false;

            if ($this->LoadLoginForm()) {
                $this->Login();
                $reLogin = true;
            } else {
                return [];
            }
        }

        if ($reLogin || $this->waitForElement(\WebDriverBy::xpath('//span[@class="ib-error-amadeus__title"]'), 0)) {
            try {
//                $day++;
                $this->http->GetURL("https://www.iberia.com/flights/?pagoAvios=true&market=US&language=en&appliesOMB=false&splitEndCity=false&initializedOMB=true&flexible=true&TRIP_TYPE=1&BEGIN_CITY_01={$fields['DepCode']}&END_CITY_01={$fields['ArrCode']}&BEGIN_DAY_01={$day}&BEGIN_MONTH_01={$month}&BEGIN_YEAR_01={$year}&FARE_TYPE=R&quadrigam=IBHMPA&ADT={$fields['Adults']}&CHD=0&INF=0&boton=Search&bookingMarket=US");
            } catch (\WebDriverException $e) {
                $this->logger->error("WebDriverException: " . $e->getMessage());
            }

            $this->waitForElement(\WebDriverBy::xpath('//button[@id="bbki-segment-info-segment-cabin-E-btn"]'), 10, false);
            $this->saveResponse();

            if ($this->waitForElement(\WebDriverBy::xpath('//span[@class="ib-error-amadeus__title"]'), 7)) {
                $this->SetWarning("It was not possible to collect all the information on the flights.");

                return [];
            }
        }

        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            if ($cookie['name'] === 'IBERIACOM_SSO_ACCESS') {
                $token = $cookie['value'];

                break;
            }
        }

        if (isset($token)) {
            $this->logger->debug('Has an authorization token');

            try {
                if (is_array($checkRoute = $this->checkValidRouteAjax($fields, $this, $token))) {
                    return $checkRoute;
                }
            } catch (\WebDriverException | \WebDriverCurlException $e) {
                // рестартить не надо. отрабатывает далее без проблем. даже на горячем при сохранении сессии дальше ок
                $this->logger->error("WebDriverException: " . $e->getMessage());
            }
            $this->logger->notice('Logged in, saving session');

            $headers = [
                'Authorization' => "Bearer {$token}",
                'Accept'        => 'application/json, text/plain, */*',
                'Content-Type'  => 'application/json;charset=UTF-8',
                'Origin'        => 'https://www.iberia.com',
                'Referer'       => 'https://www.iberia.com/',
                'Connection'    => 'keep-alive',
            ];

            $dateDep = date('Y-m-d', $fields['DepDate']);
            $data = '{"slices":[{"origin":"' . $fields['DepCode'] . '","destination":"' . $fields['ArrCode'] . '","date":"' . $dateDep . '"}],"passengers":[{"passengerType":"ADULT","count":' . $fields['Adults'] . '}],"marketCode":"US","preferredCabin":""}';

            $browser = new \HttpBrowser("none", new \CurlDriver());

            $browser->SetProxy("{$this->http->getProxyAddress()}:{$this->http->getProxyPort()}");
            $browser->setProxyAuth($this->http->getProxyLogin(), $this->http->getProxyPassword());
            $browser->setUserAgent($this->http->getDefaultHeader("User-Agent"));

            $this->http->brotherBrowser($browser);
            $cookies = $this->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }

            try {
                sleep(3);
                $this->saveResponse();

                // Пробовать можно но часто беды с этим и 403-е блоки
//                if ($this->debugMode) {
//                $browser->PostURL('https://ibisservices.iberia.com/api/sse-rpa/rs/v1/availability', $data, $headers);
//
//                if ($browser->Response['сode'] == 500) {
//                    $resData = 204;
//                }
//                $responseData = $browser->JsonLog(null, 3, false, 'slices');
//            }
                $resData = $this->getXHR($this, 'POST',
                    'https://ibisservices.iberia.com/api/sse-rpa/rs/v1/availability', $headers, $data, false);
                $responseData = $this->http->JsonLog($resData, 3, false, 'slices');
                $this->saveResponse();
            } catch (\ScriptTimeoutException $e) {
                $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
                $this->DebugInfo = "ScriptTimeoutException";
            } catch (\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverException
            | \UnexpectedJavascriptException $e) {
                $this->logger->error($e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'New session attempts retry count exceeded') === false) {
                    throw $e;
                }
                $this->logger->error("exception: " . $e->getMessage());
                $this->DebugInfo = "New session attempts retry count exceeded";

                throw new \CheckRetryNeededException(5, 0);
            }

            if ($resData == 204 || $resData == 204 || !isset($resData) || empty($responseData->originDestinations[0]->slices)) {
                $this->SetWarning("We can't find any exclusive Iberia Plus seats for the selected date and destination.");
                // $this->sendNotification('check warning //DM');
                $result = [];
            } else {
                $result = $this->parseRewardFlights($responseData, $token, $fields, $browser);
            }

//                }
        } else {
            $this->logger->debug('No authorization token');

            if ($message = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "paragraph__regular--modal-claim")]'),
                3)) {
                $this->saveResponse();
                $this->logger->error($message->getText());

                if ($this->isBlockedMessage($message->getText())) {
                    //$this->markConfigAsBad();

                    throw new CheckRetryNeededException(2, 0, $message->getText(), ACCOUNT_PROVIDER_ERROR);
                }
            }
            $this->saveResponse();

            if ($this->http->FindSingleNode("//div[contains(@class,'overlay')][contains(.,'Loading...')]")) {
                $this->logger->notice('still Loading...');

                throw new CheckRetryNeededException(5, 0);
            }

            throw new CheckRetryNeededException(5, 0, "Authorization token not received");
        }

        if (!$result && $this->ErrorCode == ACCOUNT_WARNING) {
            return [];
        }

        if (is_array($result) && isset($result['routes']) && empty($result['routes'])) {
            return $data;
        }

        return ["routes" => $result];
    }

    private function markConfigAsBad(): void
    {
        $this->logger->info("marking config {$this->config} as bad");
        \Cache::getInstance()->set('iberia_ra_config_' . $this->config, 0, 1000);
    }

    private function markConfigSuccess(): void
    {
        $this->logger->info("marking config {$this->config} as successful");
        \Cache::getInstance()->set('iberia_ra_config_' . $this->config, 1, 1000);
    }

    private function setConfig()
    {
        $configs = self::CONFIGS;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            unset($configs['chrome-94-mac']);
        }

        $successfulConfigs = array_filter(array_keys($configs), function (string $key) {
            return \Cache::getInstance()->get('triprewards_config_' . $key) === 1;
        });

        $neutralConfigs = array_filter(array_keys($configs), function (string $key) {
            return \Cache::getInstance()->get('triprewards_config_' . $key) !== 0;
        });

        if (count($successfulConfigs) > 0) {
            $this->config = $successfulConfigs[array_rand($successfulConfigs)];
            $this->logger->info("found " . count($successfulConfigs) . " successful configs");
        } elseif (count($neutralConfigs) > 0) {
            $this->config = $neutralConfigs[array_rand($neutralConfigs)];
            $this->logger->info("found " . count($neutralConfigs) . " neutral configs");
        } else {
            $this->config = array_rand($configs);
        }

        $this->logger->info("selected config $this->config");
    }

    private function isBlockedMessage(string $message): bool
    {
        return strpos($message, 'Sorry, an error has occurred. Please try again later.') !== false
            || strpos($message, 'Lo sentimos, se ha producido un error. ') !== false
            || strpos($message, 'Lamentamos, occorreu um erro. ') !== false;
    }

    private function someSleep()
    {
        usleep(random_int(70, 250) * 10000);
    }

    private function parseRewardFlights($data, $token, $fields = [], $browser): array
    {
        $routes = [];
        $attemptRequest = 0;
        $searchParams = [];

        foreach ($data->originDestinations as $key => $originDestination) {
            foreach ($originDestination->slices as $slice) {
                $this->logger->notice("-- {$slice->sliceId} --");

                foreach ($slice->segments as $i => $segment) {
                    foreach ($segment->offers as $offer) {
                        if ($attemptRequest > self::ATTEMPT_REQUEST_LIMIT && !empty($routes)) {
                            $this->logger->error('Exceeding the limit, stopping');

                            break 3;
                        }

                        if ((time() - $this->requestDateTime) > 105 && count($routes) > 0) {
                            $this->logger->error('Exceeding the limit parsing time, stopping');

                            break 3;
                        }

                        if (count($slice->segments) == 1) {
                            $attemptRequest++;
                            $this->logger->notice("{$offer->bookingClass}");
                            $tmpOffers = [$offer];
                            $selectedOffers = [
                                [
                                    'segmentId' => preg_replace('/[A-Z]$/', '', $offer->offerId),
                                    'offerId'   => $offer->offerId,
                                ],
                            ];
                            $this->logger->notice(var_export($selectedOffers, true));

                            // TODO хотят скипать не кэбины, а партнеров...
                            if ($this->getCabinForSegment($offer->bookingClass) !== $fields['Cabin']) {
                                $this->logger->notice('skip other cabin');
                            }

                            $searchParams[] = [
                                'slice'          => $slice,
                                'selectedOffers' => $selectedOffers,
                                'tmpOffers'      => $tmpOffers,
                            ];
                        } else {
                            for ($j = $i + 1; $j <= count($slice->segments); $j++) {
                                if (isset($slice->segments[$j]->offers)) {
                                    foreach ($slice->segments[$j]->offers as $offer2) {
                                        if (count($slice->segments) == 3) {
                                            for ($k = $j + 1; $k <= count($slice->segments); $k++) {
                                                if (isset($slice->segments[$k]->offers)) {
                                                    foreach ($slice->segments[$k]->offers as $offer3) {
                                                        $attemptRequest++;
                                                        $this->logger->notice("{$offer->bookingClass} -> {$offer2->bookingClass} -> {$offer3->bookingClass}");
                                                        $tmpOffers = [$offer, $offer2, $offer3];
                                                        $selectedOffers = [
                                                            [
                                                                'segmentId' => preg_replace('/[A-Z]$/', '',
                                                                    $offer->offerId),
                                                                'offerId' => $offer->offerId,
                                                            ],
                                                            [
                                                                'segmentId' => preg_replace('/[A-Z]$/', '',
                                                                    $offer2->offerId),
                                                                'offerId' => $offer2->offerId,
                                                            ],
                                                            [
                                                                'segmentId' => preg_replace('/[A-Z]$/', '',
                                                                    $offer3->offerId),
                                                                'offerId' => $offer3->offerId,
                                                            ],
                                                        ];
                                                        $this->logger->notice(var_export($selectedOffers, true));

                                                        if (!in_array($fields['Cabin'],
                                                            [
                                                                $this->getCabinForSegment($offer->bookingClass),
                                                                $this->getCabinForSegment($offer2->bookingClass),
                                                                $this->getCabinForSegment($offer3->bookingClass),
                                                            ])) {
                                                            $this->logger->notice('skip other cabins');
                                                        }

                                                        $searchParams[] = [
                                                            'slice'          => $slice,
                                                            'selectedOffers' => $selectedOffers,
                                                            'tmpOffers'      => $tmpOffers,
                                                        ];
                                                    }
                                                }
                                            }
                                        } else {
                                            $attemptRequest++;
                                            $this->logger->notice("{$offer->bookingClass} -> {$offer2->bookingClass}");
                                            $tmpOffers = [$offer, $offer2];
                                            $selectedOffers = [
                                                [
                                                    'segmentId' => preg_replace('/[A-Z]$/', '', $offer->offerId),
                                                    'offerId'   => $offer->offerId,
                                                ],
                                                [
                                                    'segmentId' => preg_replace('/[A-Z]$/', '', $offer2->offerId),
                                                    'offerId'   => $offer2->offerId,
                                                ],
                                            ];
                                            $this->logger->notice(var_export($selectedOffers, true));

                                            if (!in_array($fields['Cabin'],
                                                [
                                                    $this->getCabinForSegment($offer->bookingClass),
                                                    $this->getCabinForSegment($offer2->bookingClass),
                                                ])) {
                                                $this->logger->notice('skip other cabins');
                                            }

                                            $searchParams[] = [
                                                'slice'          => $slice,
                                                'selectedOffers' => $selectedOffers,
                                                'tmpOffers'      => $tmpOffers,
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $this->logger->notice("");
            }
        }

//        if (!$this->debugMode) {
//            $routes = $this->parseRewardFlight($fields, $token, $data, $searchParams, $selenium);
//        } else {
        $routes = $this->parseRewardFlightOld($fields, $token, $data, $searchParams, $browser);
//        }

        if ($this->systemErrorFare && empty($routes)) {
            throw new \CheckException('There has been a system error. Please try again and, if the issue persists, please contact us.', ACCOUNT_PROVIDER_ERROR);
        }

        //$this->markConfigSuccess();

        $this->keepSession(true);

        return $routes;
    }

    private function parseRewardFlight($fields, $token, $data, $searchParams, $selenium = null)
    {
        $this->logger->notice(__METHOD__);

        $remainingSeats = [];
        $routes = [];
        $responses = [];

        $headers = [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'  => 'application/json; charset=UTF-8',
            'Origin'        => 'https://www.iberia.com',
            'Referer'       => 'https://www.iberia.com/',
        ];

        foreach ($searchParams as $param) {
            foreach ($param['tmpOffers'] as $tmpOffer) {
                $remainingSeats[$tmpOffer->offerId] = $tmpOffer->remainingSeats;
            }
            $postData = [
                'responseId'         => $data->responseId,
                'originDestinations' => [
                    [
                        'originDestinationId' => 'OD0',
                        'selectedSliceId'     => $param['slice']->sliceId,
                        'selectedOffers'      => $param['selectedOffers'],
                    ],
                ],
                'specialNeedQualifierCods' => ["DISA"],
            ];

            try {
                $response = $this->getXHR($selenium, "POST", "https://ibisservices.iberia.com/api/sse-rpa/rs/v1/fare",
                    $headers, $postData);

                $responses[] = $this->http->JsonLog($response, 1, true);
            } catch (\WebDriverException $e) {
                $this->logger->error($e->getMessage());

                $url = $selenium->http->currentUrl();
                $selenium->http->GetURL($url);

                $selenium->waitForElement(\WebDriverBy::xpath('//button[@id="bbki-segment-info-segment-cabin-E-btn"]'),
                    10, false);

                $this->savePageToLogs($selenium);

                if ($selenium->waitForElement(\WebDriverBy::xpath('//span[@class="ib-error-amadeus__title"]'), 0)) {
                    $this->SetWarning("It was not possible to collect all the information on the flights.");

                    break;
                }

                try {
                    $response = $this->getXHR($selenium, "POST",
                        "https://ibisservices.iberia.com/api/sse-rpa/rs/v1/fare", $headers, $postData);

                    $responses[] = $this->http->JsonLog($response, 1, true);
                } catch (\WebDriverException $e) {
                    $this->logger->error($e->getMessage());

                    $this->SetWarning("It was not possible to collect all the information on the flights.");

                    break;
                }
            }
        }

        $allSkipped = true;

        foreach ($responses as $response) {
            $fare = $response;

            if (is_null($fare)) {
                continue;
            }
            $allSkipped = false;

            if ($response == 204) {
                if (isset($fare->errors)
                    && isset($fare->errors[0]) && isset($fare->errors[0]->reason)
                ) {
                    $this->logger->error($fare->errors[0]->reason);

                    if (strpos($fare->errors[0]->reason,
                            'There has been a system error. Please try again and, if the issue persists, please contact us.') !== false
                        || strpos($fare->errors[0]->reason,
                            'There has been a communication error with the scoring system. Please try again and, if the problem persists, please contact us') !== false
                        || strpos($fare->errors[0]->reason,
                            'We are having problems with your request. Please try again.') !== false) {
                        $this->systemErrorFare = true;
                    }

                    continue;
                }

                continue;
            }
            $offer = $fare['offers'][0];

            // 1 or 2 flights
            if (isset($offer['redemptionOptions'])) {
                usort($offer['redemptionOptions'], function ($item1, $item2) {
                    return $item1['optionId'] <=> $item2['optionId'];
                });
                $option = $offer['redemptionOptions'][0];
                $price = $offer['price'];
            } // 3 flights
            elseif (isset($offer['promotion']['discountedRedemptionOptions'][0])) {
                usort($offer['promotion']['discountedRedemptionOptions'], function ($item1, $item2) {
                    return $item1['optionId'] <=> $item2['optionId'];
                });
                $option = $offer['promotion']['discountedRedemptionOptions'][0]['discountedPrice'];
                $price = $offer['price'];
            } else {
                $this->logger->error('Empty offers->redemptionOptions');

                return false;
            }

            $route = [
                'times'       => ['flight' => null, 'layover' => null],
                'redemptions' => [
                    'miles'   => round($option['totalPoints']['fare'] / $fields['Adults']),
                    'program' => $this->AccountFields['ProviderCode'],
                ],
                'payments' => [
                    'currency' => $option['totalCash']['currency'],
                    'taxes'    => round(($option['totalCash']['fare'] + $price['total']) / $fields['Adults'], 2),
                    'fees'     => null,
                ],
            ];
            $classOfServiceArray = [];
            $slice = $offer['slices'][0];

            foreach ($slice['segments'] as $segment) {
                $seatsKey = $segment['id'] . $segment['cabin']['rbd'];
                $classOfService = $this->getAwardTypeForSegment($segment['cabin']['bookingClass']);

                if (preg_match('/^(.+\w+) class$/i', $classOfService, $m)) {
                    $classOfService = $m[1];
                }
                $classOfServiceArray[] = $classOfService;
                $flNum = $segment['flight']['marketingFlightNumber'] ?? $segment['flight']['operationalFlightNumber'];
                $route['connections'][] = [
                    'departure' => [
                        'date'     => date('Y-m-d H:i', strtotime($segment['departureDateTime'])),
                        'dateTime' => strtotime($segment['departureDateTime']),
                        'airport'  => $segment['departure']['airport']['code'],
                        'terminal' => $segment['departure']['terminal'] ?? null,
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', strtotime($segment['arrivalDateTime'])),
                        'dateTime' => strtotime($segment['arrivalDateTime']),
                        'airport'  => $segment['arrival']['airport']['code'],
                        'terminal' => $segment['arrival']['terminal'] ?? null,
                    ],
                    'meal'           => null,
                    'tickets'        => $remainingSeats[$seatsKey],
                    'cabin'          => $this->getCabinForSegment($segment['cabin']['bookingClass']),
                    'classOfService' => $classOfService,
                    'fare_class'     => $segment['cabin']['bookingCode'],
                    'award_type'     => $this->getAwardTypeForSegment($segment['cabin']['bookingClass']),
                    'flight'         => ["{$segment['flight']['marketingCarrier']['code']}{$flNum}"],
                    'airline'        => $segment['flight']['marketingCarrier']['code'],
                    'operator'       => $segment['flight']['operationalCarrier']['code'],
                    'distance'       => null,
                    'aircraft'       => $segment['flight']['aircraft']['description'],
                    'times'          => [
                        'flight'  => null,
                        'layover' => null,
                    ],
                ];
            }
            $classOfServiceArray = array_values(array_unique($classOfServiceArray));

            if (count($classOfServiceArray) === 1) {
                $route['classOfService'] = $classOfServiceArray[0];
            }
            $route['num_stops'] = count($route['connections']) - 1;

            $this->logger->debug(var_export($route, true), ['pre' => true]);
            $routes[] = $route;
        }

        if ($allSkipped) {
            $this->logger->error('sendAsyncRequests failed');

            throw new CheckRetryNeededException(5, 0);
        }

        if (empty($routes)) {
            throw new CheckRetryNeededException(5, 0);
        }

        return $routes;
    }

    private function parseRewardFlightOld($fields, $token, $data, $searchParams, $browser)
    {
        $this->logger->notice(__METHOD__);

        $remainingSeats = [];
        $options = [];
        $routes = [];

        $headers = [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'  => 'application/json; charset=UTF-8',
            'Origin'        => 'https://www.iberia.com',
            'Referer'       => 'https://www.iberia.com/',
        ];

        foreach ($searchParams as $param) {
            foreach ($param['tmpOffers'] as $tmpOffer) {
                $remainingSeats[$tmpOffer->offerId] = $tmpOffer->remainingSeats;
            }
            $postData = [
                'responseId'         => $data->responseId,
                'originDestinations' => [
                    [
                        'originDestinationId' => 'OD0',
                        'selectedSliceId'     => $param['slice']->sliceId,
                        'selectedOffers'      => $param['selectedOffers'],
                    ],
                ],
                'specialNeedQualifierCods' => ["DISA"],
            ];

            $options[] = [
                'method'   => 'POST',
                'sURL'     => 'https://ibisservices.iberia.com/api/sse-rpa/rs/v1/fare',
                'postData' => json_encode($postData),
                'headers'  => $headers,
                'timeout'  => 10,
            ];
        }

        $browser->sendAsyncRequests($options);
//        $this->http->sendAsyncRequests($options);

        $allSkipped = true;

        foreach ($browser->asyncResponses as $response) {
            $this->http->Response = $response;
            $fare = $this->http->JsonLog(null, 1, false);

            if (is_null($fare)) {
                continue;
            }
            $allSkipped = false;

            if ($this->http->Response['code'] != 200) {
                if (isset($fare->errors)
                    && isset($fare->errors[0]) && isset($fare->errors[0]->reason)
                ) {
                    $this->logger->error($fare->errors[0]->reason);

                    if (strpos($fare->errors[0]->reason,
                            'There has been a system error. Please try again and, if the issue persists, please contact us.') !== false
                        || strpos($fare->errors[0]->reason,
                            'There has been a communication error with the scoring system. Please try again and, if the problem persists, please contact us') !== false
                        || strpos($fare->errors[0]->reason,
                            'We are having problems with your request. Please try again.') !== false) {
                        $this->systemErrorFare = true;
                    }
                }

                continue;
            }
            $offer = $fare->offers[0];

            // 1 or 2 flights
            if (isset($offer->redemptionOptions)) {
                usort($offer->redemptionOptions, function ($item1, $item2) {
                    return $item1->optionId <=> $item2->optionId;
                });
                $option = $offer->redemptionOptions[0];
                $price = $offer->price;
            } // 3 flights
            elseif (isset($offer->promotion->discountedRedemptionOptions[0])) {
                usort($offer->promotion->discountedRedemptionOptions, function ($item1, $item2) {
                    return $item1->optionId <=> $item2->optionId;
                });
                $option = $offer->promotion->discountedRedemptionOptions[0]->discountedPrice;
                $price = $offer->price;
            } else {
                $this->logger->error('Empty offers->redemptionOptions');

                return false;
            }

            $route = [
                'times'       => ['flight' => null, 'layover' => null],
                'redemptions' => [
                    'miles'   => round($option->totalPoints->fare / $fields['Adults']),
                    'program' => $this->AccountFields['ProviderCode'],
                ],
                'payments' => [
                    'currency' => $option->totalCash->currency,
                    'taxes'    => round(($option->totalCash->fare + $price->total) / $fields['Adults'], 2),
                    'fees'     => null,
                ],
            ];
            $classOfServiceArray = [];
            $slice = $offer->slices[0];

            foreach ($slice->segments as $segment) {
                $seatsKey = $segment->id . $segment->cabin->rbd;
                $classOfService = $this->getAwardTypeForSegment($segment->cabin->bookingClass);

                if (preg_match('/^(.+\w+) class$/i', $classOfService, $m)) {
                    $classOfService = $m[1];
                }
                $classOfServiceArray[] = $classOfService;
                $flNum = $segment->flight->marketingFlightNumber ?? $segment->flight->operationalFlightNumber;
                $route['connections'][] = [
                    'departure' => [
                        'date'     => date('Y-m-d H:i', strtotime($segment->departureDateTime)),
                        'dateTime' => strtotime($segment->departureDateTime),
                        'airport'  => $segment->departure->airport->code,
                        'terminal' => $segment->departure->terminal ?? null,
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', strtotime($segment->arrivalDateTime)),
                        'dateTime' => strtotime($segment->arrivalDateTime),
                        'airport'  => $segment->arrival->airport->code,
                        'terminal' => $segment->arrival->terminal ?? null,
                    ],
                    'meal'           => null,
                    'tickets'        => $remainingSeats[$seatsKey],
                    'cabin'          => $this->getCabinForSegment($segment->cabin->bookingClass),
                    'classOfService' => $classOfService,
                    'fare_class'     => $segment->cabin->bookingCode,
                    'award_type'     => $this->getAwardTypeForSegment($segment->cabin->bookingClass),
                    'flight'         => ["{$segment->flight->marketingCarrier->code}{$flNum}"],
                    'airline'        => $segment->flight->marketingCarrier->code,
                    'operator'       => $segment->flight->operationalCarrier->code,
                    'distance'       => null,
                    'aircraft'       => $segment->flight->aircraft->description,
                    'times'          => [
                        'flight'  => null,
                        'layover' => null,
                    ],
                ];
            }
            $classOfServiceArray = array_values(array_unique($classOfServiceArray));

            if (count($classOfServiceArray) === 1) {
                $route['classOfService'] = $classOfServiceArray[0];
            }
            $route['num_stops'] = count($route['connections']) - 1;

            $this->logger->debug(var_export($route, true), ['pre' => true]);

            $routes[] = $route;
        }

        if ($allSkipped) {
            $this->logger->error('sendAsyncRequests failed');

            throw new \CheckRetryNeededException(5, 0);
        }

        return $routes;
    }

    private function checkValidRouteAjax($fields, $selenium, $token): ?array
    {
        $this->logger->notice(__METHOD__);

        try {
            $script = '
                var xhttp = new XMLHttpRequest();
                xhttp.open("GET", "https://ibisservices.iberia.com/api/rdu-loc/rs/loc/v1/location/areas/origin/", false);
                xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
                xhttp.setRequestHeader("Origin", "https://www.iberia.com");
                xhttp.setRequestHeader("Referer", "https://www.iberia.com/");
                xhttp.setRequestHeader("Connection", "keep-alive");
                xhttp.setRequestHeader("Authorization", "Bearer ' . $token . '");
    
                var responseText = null;
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        responseText = this.responseText;
                    }
                };
                xhttp.send();
                return responseText;
            ';

            $returnData = $selenium->driver->executeScript($tt = $script);

            $this->logger->debug($tt, ['pre' => true]);

            $origin = $selenium->http->JsonLog($returnData, 1);

            if (is_array($origin) && !$this->hasCode($origin, $fields['DepCode'])) {
                $this->SetWarning('no flights from ' . $fields['DepCode']);

                return ['routes' => []];
            }

            $returnData = $selenium->driver->executeScript($tt =
                '
                        var xhttp = new XMLHttpRequest();
                        xhttp.open("GET", "https://ibisservices.iberia.com/api/rdu-loc/rs/loc/v1/location/areas/destination/", false);
                        xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
                        xhttp.setRequestHeader("Origin", "https://www.iberia.com");
                        xhttp.setRequestHeader("Referer", "https://www.iberia.com/");
                        xhttp.setRequestHeader("Connection", "keep-alive");
                        xhttp.setRequestHeader("Authorization", "Bearer ' . $token . '");
            
                        var responseText = null;
                        xhttp.onreadystatechange = function() {
                            if (this.readyState == 4 && this.status == 200) {
                                responseText = this.responseText;
                            }
                        };
                        xhttp.send();
                        return responseText;
            '
            );

            $this->logger->debug($tt, ['pre' => true]);

            $destination = $selenium->http->JsonLog($returnData, 1);

            if (is_array($destination) && !$this->hasCode($destination, $fields['ArrCode'])) {
                $this->SetWarning('no flights to ' . $fields['ArrCode']);

                return ['routes' => []];
            }

            return null;
        } catch (\Facebook\WebDriver\Exception\InvalidSelectorException | \Facebook\WebDriver\Exception\ScriptTimeoutException | \Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            throw new CheckRetryNeededException(5, 0);
        }
    }

    private function hasCode($data, $code)
    {
        foreach ($data as $row) {
            foreach ($row->cities as $city) {
                if ($city->code === $code) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getXHR($selenium, $method, $url, array $headers, $payload, $async = false)
    {
        $this->logger->notice(__METHOD__);
        $headersString = "";

        foreach ($headers as $key => $value) {
            $headersString .= 'xhttp.setRequestHeader("' . $key . '", "' . $value . '");
        ';
        }

        if (is_array($payload)) {
            $payload = json_encode($payload);
        }
        $script = '
                var xhttp = new XMLHttpRequest();
                xhttp.withCredentials = true;
                xhttp.open("' . $method . '", "' . $url . '", ' . ($async ? 'true' : 'false') . ');
                ' . $headersString . '
                var data = JSON.stringify(' . $payload . ');
                var responseText = null;
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && (this.status == 200 || this.status == 202 || this.status == 204 || this.status == 500)) {
                        if (this.status == 204 || this.status == 500)
                            responseText = 204;
                        else
                            responseText = this.responseText;
                    }
                };
                xhttp.send(data);
                return responseText;
            ';
        $this->logger->debug("[run script]:");
        $this->logger->debug($script, ['pre' => true]);

        return $selenium->driver->executeScript($script);
    }

    private function getCabinForSegment(string $cabin)
    {
        $cabins = [
            'TOURIST'        => 'economy', // Economy Class
            'ECONOMY'        => 'economy', // Blue Class
            'PREMIUMTOURIST' => 'premiumEconomy', // Premium Economy
            'BUSINESS'       => 'business', // Business
            'FIRST'          => 'firstClass', // First Class
        ];

        if (isset($cabins[$cabin])) {
            return $cabins[$cabin];
        }
        $this->sendNotification("RA check cabin for segment {$cabin} // MI");

        throw new \CheckException("check cabin for segment {$cabin}", ACCOUNT_ENGINE_ERROR);
    }

    private function getAwardTypeForSegment(string $cabin)
    {
        $cabins = [
            'TOURIST'        => 'Economy Class',
            'ECONOMY'        => 'Blue Class',
            'PREMIUMTOURIST' => 'Premium Economy',
            'BUSINESS'       => 'Business',
            'FIRST'          => 'First Class',
        ];

        if (isset($cabins[$cabin])) {
            return $cabins[$cabin];
        }
        $this->sendNotification("RA check cabin for segment {$cabin} // MI");

        throw new \CheckException("check cabin for segment {$cabin}", ACCOUNT_ENGINE_ERROR);
    }

    private function checkPage()
    {
        $this->logger->notice(__METHOD__);

        if ($this->waitForElement(\WebDriverBy::xpath("
                //span[contains(text(), 'This site can’t be reached')]
                | //h1[normalize-space()='Access Denied']
                | //div[@id = 'Error_Subtitulo' and contains(text(), 'The connection was interrupted due to an error,')]
            "), 0)) {
            $this->markProxyAsInvalid();
            $this->markConfigAsBad();

            throw new CheckRetryNeededException(3, 0);
        }
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg("/In order to continue to improve our product we are currently making some modifications in iberia\.com/ims")) {
            throw new CheckException('In order to continue to improve our product we are currently making some modifications in iberia.com. We will be available in a few hours. We apologise for the inconvenience caused.', ACCOUNT_PROVIDER_ERROR);
        }
        // Online services are not available
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'At this moment, our online services are not available')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Online services are not available
        if ($message = $this->http->FindPreg("/At this moment, our online services are not available as result of the high number of accesses. Please try again in a few minutes/ims")) {
            throw new CheckException('Iberia Plus website had a hiccup, please try to check your balance at a later time.', ACCOUNT_PROVIDER_ERROR);
        }
        //# Online services are not available
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'our online services are not available')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Due to a problem with our systems we can not offer online services temporarily
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Due to a problem with our systems we can not offer online services temporarily')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The page you have requested is not available
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The page you have requested is not available')]")) {
            throw new CheckRetryNeededException(3, 3, $message);
        }

        if (
            // An error occurred while processing your request.
            ($this->http->FindPreg("/An error occurred while processing your request./") && $this->http->Response['code'] == 504)
            || $this->http->FindSingleNode("//h1[contains(text(), 'Gateway Timeout')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
