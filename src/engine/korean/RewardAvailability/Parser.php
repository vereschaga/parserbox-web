<?php

namespace AwardWallet\Engine\korean\RewardAvailability;

use AwardWallet\Common\Selenium\BrowserCommunicatorException;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;
use WebDriverBy;

class Parser extends \TAccountCheckerKorean
{
    use \SeleniumCheckerHelper;
    use ProxyList;
    private const xpathAuth = '
            //button[normalize-space()="Log out"]
            | //button[@id="my-panel-btn"]
        ';

    public $isRewardAvailability = true;
    private $data;
    private $calendarData;

    private $deepAirportCode = null;
    private $arrAirportCode = null;

    public static function getRASearchLinks(): array
    {
        return ['https://www.koreanair.com/' => 'search page'];
    }

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();
//        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->debugMode = isset($this->AccountFields['DebugState']) && $this->AccountFields['DebugState'];

//        if ($this->attempt === 0) {
//            switch (random_int(0, 3)) {
//                case 0:
//                    $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC);
//
//                    break;
//
//                case 1:
        if ($this->AccountFields['ParseMode'] === 'awardwallet') {
            $this->setProxyGoProxies();
        } else {
            $this->setProxyGoProxies(null, 'kr');
        }

//                    break;
//
//                case 2:
        ////                    $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, 'fi');
//                    $this->setProxyGoProxies();
//
//                    break;
//
//                case 3:
        ////                    $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, 'kr');
//                    $this->setProxyGoProxies(null, 'it');
//
//                    break;
//            }
//        } elseif ($this->attempt == 1) {
//            $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, 'fr');
//       }
        /* else {
             $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, 'us', true);
         }
         if ($this->attempt == 0) {
             $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, 'de', true);
         }*/
        $userAgentKey = "UserAgent";

        if (empty($this->State[$userAgentKey]) || $this->attempt > 1) {
            $this->http->setRandomUserAgent(10);
            $agent = $this->http->getDefaultHeader("User-Agent");
            $this->State[$userAgentKey] = $agent;
        }
        $this->http->setUserAgent($this->State['UserAgent']);
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => ['EUR', 'KRW', 'USD'],
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'USD',
        ];
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

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        if ($fields['DepDate'] > strtotime('+363 day')) {
            $this->ErrorCode = ACCOUNT_WARNING;
            $this->ErrorMessage = "The requested departure date is too late.";
            $this->logger->error($this->ErrorMessage);

            return ['routes' => []];
        }

        $settings = $this->getRewardAvailabilitySettings();

        if (!in_array($fields['Currencies'][0], $settings['supportedCurrencies'])) {
            $fields['Currencies'][0] = $settings['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        $this->http->RetryCount = 0;

        if (!$this->validRoute($fields)) {
            $this->ErrorCode = ACCOUNT_WARNING;
            $this->ErrorMessage = "This route is not in the list of award flights.";
            $this->logger->error($this->ErrorMessage);

            return ['routes' => []];
        }

        $this->data = null;

        try {
            $this->selenium($fields);
        } catch (\StaleElementReferenceException $e) {
            $this->logger->error('StaleElementReferenceException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\ScriptTimeoutException $e) {
            $this->logger->error('ScriptTimeoutException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\Exception | \TypeError $e) {
            $this->logger->error($e->getMessage(), ['pre' => true]);
            $this->logger->error($e->getTraceAsString(), ['pre' => true]);

            if (strpos($e->getMessage(), 'Array to string conversion') !== false
                || strpos($e->getMessage(), 'strlen() expects parameter 1 to be string, array given') !== false
                || strpos($e->getMessage(), 'must be of the type string or null, array given') !== false
                || strpos($e->getMessage(),
                    'Argument 1 passed to Facebook\WebDriver\Remote\JsonWireCompat::getElement()') !== false
            ) {
                // TODO бага селениума
                throw new \CheckRetryNeededException(5, 0);
            }

            throw $e;
        }

        if (!empty($this->data)) {
            $data = $this->data;
        } else {
            throw new \CheckRetryNeededException(5, 0);
            $data = $this->getDataCurl($fields);
        }

        /*if (!$data && (in_array($this->http->Response['code'], [403.502])
                || $this->http->Error === 'Network error 56 - Unexpected EOF')
            || $this->http->FindPreg('#"provider":"crypto","branding_url_content":"/_sec/cp_challenge/crypto_message#')
            || $this->isBadProxy()
        ) {
            // 502 retry not helped, only restart
            $this->markProxyAsInvalid();

            throw new \CheckRetryNeededException(5, 0);
        }*/

        if (!isset($data->upsellBoundAvailList)) {
            if (is_array($data) && $data == ["routes" => []]) {
                return $data;
            }

            if (isset($data->status) && $data->status === 'OK'
                && isset($data->message) && $data->message === 'Communication was not successful. Please try again in a few minutes.'
                && isset($data->subMessages)
                && strpos($data->subMessages[0], 'We are unable to find recommendations for your search') !== false
            ) {
                $this->logger->error($msg = 'Flights cannot be searched with the itinerary you have entered. Please search again.');
                $this->SetWarning($msg);

                return ["routes" => []];
            }

            if (isset($data->status) && $data->status === 'OK'
                && isset($data->message) && $data->message === 'Communication was not successful. Please try again in a few minutes.'
                && isset($data->subMessages)
                && strpos($data->subMessages[0], 'The requested departure date is too late.') !== false
            ) {
                $this->logger->error($data->subMessages[0]);
                $this->ErrorCode = ACCOUNT_WARNING;
                $this->ErrorMessage = $data->subMessages[0];

                return ["routes" => []];
            }

            if (isset($data->status) && $data->status === 'OK'
                && isset($data->message)
                && isset($data->subMessages)
                && strpos($data->subMessages[0],
                    'We are unable to find departing flights for the requested outbound') !== false
            ) {
                if ($this->calendarData && $this->notFlightDay(date('Ymd', $fields['DepDate']))) {
                    $this->logger->notice('no flights on selected day');
                    $this->SetWarning('There are no remaining seats in the outbound flights on the date selected');

                    return [
                        "routes" => [],
                    ];
                }

                if (strpos($data->message,
                        'There are no remaining seats in the outbound flights on the date selected') !== false) {
                    $this->SetWarning('There are no remaining seats in the outbound flights on the date selected');

                    return [
                        "routes" => [],
                    ];
                }

                throw new \CheckRetryNeededException(5, 0);
            }

            if (isset($data->status) && $data->status === 'OK'
                && isset($data->message) && $data->message === 'Please proceed after log-in.'
            ) {
                $this->logger->error('Please proceed after log-in.');

                throw new \CheckRetryNeededException(5, 0);
            }

            throw new \CheckException('Something went wrong', ACCOUNT_ENGINE_ERROR);
        }

        return [
            "routes" => $this->parseRewardFlights($data, $fields),
        ];
    }

    private function notFlightDay($dateString)
    {
        if (!isset($this->calendarData->boundFareCalendarList)
            || !isset($this->calendarData->boundFareCalendarList[0])
            || !isset($this->calendarData->boundFareCalendarList[0]->fareCalendarList)
            || !is_array($this->calendarData->boundFareCalendarList[0]->fareCalendarList)
        ) {
            return false;
        }
        $noFlights = $hasDay = false;

        foreach ($this->calendarData->boundFareCalendarList[0]->fareCalendarList as $bound) {
            if ($bound->departureDate !== $dateString) {
                continue;
            }

            if (isset($bound->travellerTypeFareInfoList)
                && is_array($bound->travellerTypeFareInfoList)
                && empty($bound->travellerTypeFareInfoList)
                && empty($bound->currency)
            ) {
                $noFlights = true;
            }
            $hasDay = true;

            break;
        }

        if ($hasDay) {
            return $noFlights;
        }

        return false;
    }

    private function getDataCurl($fields)
    {
        $this->logger->notice(__METHOD__);
        $headers = ['Accept' => '*/*', 'channel' => 'pc', 'Referer' => 'https://www.koreanair.com/booking/search'];
        $this->http->GetURL("https://www.koreanair.com/api/et/uiCommon/c/e/alertMessageInfo?arrivalAirport={$fields['ArrCode']}&departureAirport={$fields['DepCode']}&endDate=" . date('Y-m-d',
                $fields['DepDate']) . " 00:00&flowType=NR&langCode=en&messageLocation=BG&startDate=" . date('Y-m-d',
                $fields['DepDate']) . " 00:00&tripType=OW", $headers);

        $headers = [
            'Accept'  => '*/*',
            'channel' => 'pc',
            'Referer' => 'https://www.koreanair.com/booking/select-award-flight',
        ];
        $this->http->GetURL("https://www.koreanair.com/api/li/auth/loginUserInfo", $headers);

        $this->http->RetryCount = 2;
        $this->http->setDefaultHeader('Accept', '*/*');
        $this->http->setDefaultHeader('Content-Type', 'application/json');
        $this->http->setDefaultHeader('channel', 'pc');

        if ($this->ksessionId) {
            $this->http->setDefaultHeader('ksessionId', $this->ksessionId);
        }
        $this->http->setDefaultHeader('X-Sec-Clge-Req-Type', 'ajax');

        $payload = $this->getPayloadMain($fields);
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.koreanair.com/api/ap/booking/avail/awardAvailability', json_encode($payload),
            $headers, 45);
        $data = $this->http->JsonLog();
        $this->http->RetryCount = 2;

        if (($this->http->FindPreg('#"provider":"crypto","branding_url_content":"/_sec/cp_challenge/crypto_message#')
                || $this->http->Response['code'] == 403)
            && !$this->ksessionId
        ) {
            // sometimes help
            $this->selenium([], false);

            if ($this->ksessionId) {
                $this->http->setDefaultHeader('ksessionId', $this->ksessionId);
            }
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.koreanair.com/api/ap/booking/avail/awardAvailability',
                json_encode($payload), $headers, 45);
            $data = $this->http->JsonLog();
            $this->http->RetryCount = 2;
        }

        return $data;
    }

    private function selenium($fields, $auth = true)
    {
        $this->logger->notice(__METHOD__);

        if (!$auth) {
            $allCookies = array_merge($this->http->GetCookies(".koreanair.com"),
                $this->http->GetCookies(".koreanair.com", "/", true));
            $allCookies = array_merge($allCookies, $this->http->GetCookies("www.koreanair.com"),
                $this->http->GetCookies("www.koreanair.com", "/", true));
        }
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;
//            $selenium->usePacFile(false);
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_100);

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;

            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }
            $selenium->http->saveScreenshots = true;
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($chosenResolution);
            $selenium->seleniumRequest->setHotSessionPool(self::class . '100', $this->AccountFields['ProviderCode']);

            try {
                $selenium->http->start();
                $selenium->Start();
            } catch (\UnknownServerException | \SessionNotCreatedException $e) {
                $this->markProxyAsInvalid();
                $this->logger->error("exception: " . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }

            if ($auth) {
                if (!$this->auth($selenium)) {
                    return false;
                }

                if ($this->http->FindSingleNode("//em[contains(.,'Your request has not been processed successfully')]")) {
                    $selenium->waitFor(function () use ($selenium) {
                        return !$selenium->waitForElement(WebDriverBy::id('sec-text-if'), 0);
                    }, 100);

                    try {
                        $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@type='submit' and contains(text(),'Log in')]"),
                            0);

                        if ($btn) {
                            $btn->click();
                        }
                    } catch (\UnrecognizedExceptionException $e) {
                        $this->logger->error($e);

                        throw new \CheckRetryNeededException(5, 0);
                    }

                    sleep(3);
                    $this->savePageToLogs($selenium);

                    if ($selenium->waitForElement(\WebDriverBy::xpath("
                    //span[contains(text(), 'This site can’t be reached') or contains(text(), 'This page isn’t working')]
                    | //h1[normalize-space()='Access Denied']
                    | //h1[normalize-space()='No internet']
                "), 0)) {
                        throw new \CheckRetryNeededException(5, 0);
                    }
                    $selenium->waitFor(function () use ($selenium) {
                        $loggedInUserInfo = $selenium->driver->executeScript("return sessionStorage.getItem('loggedInUserInfo')");
                        $dLogin = $this->http->JsonLog($loggedInUserInfo, 1);

                        return isset($dLogin->signinStatus) || $selenium->waitForElement(\WebDriverBy::xpath(self::xpathAuth), 0);
                    }, 20);
                }

                $loggedInUserInfo = $selenium->driver->executeScript("return sessionStorage.getItem('loggedInUserInfo')");
                $dLogin = $this->http->JsonLog($loggedInUserInfo, 1);

                if (!(isset($dLogin->signinStatus) || $selenium->waitForElement(\WebDriverBy::xpath(self::xpathAuth), 0))
                    && ($btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@type='submit' and contains(text(),'Log in')]"),
                        0))) {
                    try {
                        if (!$this->auth($selenium)) {
                            return false;
                        }
                    } catch (\UnrecognizedExceptionException $e) {
                        $this->logger->error('UnrecognizedExceptionException: ' . $e->getMessage());

                        throw new \CheckRetryNeededException(5, 0);
                    }
                }

                $this->savePageToLogs($selenium);
                $selenium->waitFor(function () use ($selenium) {
                    $loggedInUserInfo = $selenium->driver->executeScript("return sessionStorage.getItem('loggedInUserInfo')");
                    $dLogin = $this->http->JsonLog($loggedInUserInfo, 1);

                    return isset($dLogin->signinStatus) || $selenium->waitForElement(\WebDriverBy::xpath(self::xpathAuth), 0);
                }, 20);
                $this->savePageToLogs($selenium);

                $loggedInUserInfo = $selenium->driver->executeScript("return sessionStorage.getItem('loggedInUserInfo')");
                $dLogin = $this->http->JsonLog($loggedInUserInfo, 1);

                if (!(isset($dLogin->signinStatus) || $selenium->waitForElement(\WebDriverBy::xpath(self::xpathAuth), 0))
                    && ($btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@type='submit' and contains(text(),'Log in')]"),
                        0))) {
                    if ($msg = $this->http->FindSingleNode("//em[contains(.,'No matching member information')]")) {
                        $this->logger->error($msg);

                        throw new \CheckException('something wrong', ACCOUNT_LOCKOUT);
                    }

                    if ($msg = $this->http->FindSingleNode("//em[contains(.,'Your request has not been processed successfully')]")) {
                        $this->logger->error($msg);

                        throw new \CheckException('something wrong', ACCOUNT_PREVENT_LOCKOUT);
                    }

                    throw new \CheckRetryNeededException(5, 0);
                }
                $this->getKsessionId($selenium);

                try {
                    $this->data = $this->tryAjax($selenium, $fields);
                } catch (\InvalidSelectorException | \Facebook\WebDriver\Exception\InvalidSelectorException $e) {
                    // sometimes help
                    $this->logger->error('InvalidSelectorException: ' . $e->getMessage());
                    sleep(2);

                    try {
                        $this->data = $this->tryAjax($selenium, $fields);
                    } catch (\InvalidSelectorException | \Facebook\WebDriver\Exception\InvalidSelectorException $e) {
                        $this->logger->error('InvalidSelectorException: ' . $e->getMessage());

                        throw new \CheckRetryNeededException(5, 0);
                    }
                }

                if ($this->loginInfo && isset($this->loginInfo->signinStatus) && !$this->loginInfo->signinStatus) {
                    throw new \CheckRetryNeededException(5, 0);
                }

                if ($this->data) {
                    $this->logger->notice('Data ok, saving session');
                    $selenium->keepSession(true);
                }
                $this->savePageToLogs($selenium);
            } else {
                $selenium->http->GetURL('https://www.koreanair.com/de/en3');

                foreach ($allCookies as $key => $value) {
                    $selenium->driver->manage()->addCookie([
                        'name'   => $key,
                        'value'  => $value,
                        'domain' => ".koreanair.com",
                    ]);
                }
                $selenium->http->GetURL('https://www.koreanair.com');

                sleep(5);
                $this->getKsessionId($selenium);
                $this->savePageToLogs($selenium);
            }
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
        } catch (\UnknownServerException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
            $retry = true;
        } catch (\ErrorException | \TypeError $e) {
            $this->logger->error($e->getMessage(), ['pre' => true]);
            $this->logger->error($e->getTraceAsString(), ['pre' => true]);

            if (strpos($e->getMessage(), 'Array to string conversion') !== false
                || strpos($e->getMessage(), 'strlen() expects parameter 1 to be string, array given') !== false
                || strpos($e->getMessage(),
                    'Argument 1 passed to Facebook\WebDriver\Remote\JsonWireCompat::getElement()') !== false
            ) {
                // TODO бага селениума
                $retry = true;
            } else {
                throw $e;
            }
        } catch (\WebDriverCurlException
        | \Facebook\WebDriver\Exception\WebDriverException
        | \Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException exception: " . $e->getMessage());
            $this->DebugInfo = "WebDriverCurlException";
            // retries
            if (strpos($e->getMessage(), '/session') !== false) {
                $retry = true;
            }
        }// catch (WebDriverCurlException $e)
        catch (\NoSuchDriverException
        | \NoSuchWindowException
        | \TimeOutException
        | \Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            // retries
            $retry = true;
        } catch (\WebDriverException
        | \Facebook\WebDriver\Exception\UnknownErrorException
        | \Facebook\WebDriver\Exception\UnrecognizedExceptionException
        $e) {
            $this->logger->error("WebDriverException: " . $e->getMessage());
            $this->DebugInfo = "exception";
            // retries
            $retry = true;
        }// catch (WebDriverException $e)
        catch (\UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException exception: " . $e->getMessage());
            $this->DebugInfo = "UnexpectedJavascriptException";
            // retries
            if (
                strpos($e->getMessage(), 'TypeError: document.documentElement is null') !== false
                || strpos($e->getMessage(), 'ReferenceError: $ is not defined') !== false
            ) {
                $retry = true;
            }
            $result = false;
        }// catch (UnexpectedJavascriptException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new \CheckRetryNeededException(5, 10);
            }
        }

        return $result;
    }

    private function auth($selenium, $isRetry = false)
    {
        $mover = new \MouseMover($selenium->driver);
        $mover->logger = $this->logger;
        $mover->enableCursor();
        $mover->duration = random_int(40, 60) * 100;
        $mover->steps = 1;
        $mover->setCoords(0, 500);

        $this->logger->notice(__METHOD__);
        $loggedInUserInfo = $selenium->driver->executeScript("return sessionStorage.getItem('loggedInUserInfo')");
        $dLogin = $this->http->JsonLog($loggedInUserInfo, 1);

        if (strpos($selenium->http->currentUrl(), 'https://www.koreanair.com/') !== false
            && (isset($dLogin->signinStatus) || $selenium->waitForElement(\WebDriverBy::xpath(self::xpathAuth), 0))
        ) {
            try {
                $selenium->http->GetURL('https://www.koreanair.com');

                if ($selenium->waitForElement(\WebDriverBy::xpath("
                    //span[contains(text(), 'This site can’t be reached') or contains(text(), 'This page isn’t working')]
                    | //h1[normalize-space()='Access Denied']
                    | //h1[normalize-space()='No internet']
                "), 0)) {
                    throw new \CheckRetryNeededException(5, 0);
                }
            } catch (\UnknownServerException $e) {
                $this->logger->error("exception: " . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }

            return true;
        }

        /*        if ($cont = $selenium->waitForElement(\WebDriverBy::id('dialog-ip-btn-continue'), 2)) {
                    $cont->click();
                }*/

//        $loginXpath = "//label[contains(text(),'User ID or SKYPASS Number (12 digits)')]/following-sibling::div/input";
        if (!isset($this->AccountFields['Login2'])) {
            // TODO tmp: for ra-awardwallet
            throw new \CheckException('no auth data', ACCOUNT_ENGINE_ERROR);
        }

        if ($this->AccountFields['Login2'] === 'sky') {
            $loginXpath = "//label[contains(text(),'SKYPASS Number') and contains(.,'Required')]/following-sibling::div/input";
            $radioXPath = "//button[normalize-space(text())='SKYPASS Number']";
        } else {
            $loginXpath = "//label[contains(text(),'User ID') and contains(.,'Required')]/following-sibling::div/input";
            $radioXPath = "//button[normalize-space(text())='User ID']";
        }

        try {
            $selenium->http->GetURL('https://www.koreanair.com');
            sleep(2);

            if ($selenium->waitForElement(\WebDriverBy::xpath("
                    //span[contains(text(), 'This site can’t be reached') or contains(text(), 'This page isn’t working')]
                    | //h1[normalize-space()='Access Denied']
                    | //h1[normalize-space()='No internet']
                "), 0)) {
                throw new \CheckRetryNeededException(5, 0);
            }

            $selenium->driver->executeScript("try { document.querySelector('kc-global-cookie-banner').shadowRoot.querySelector('.-confirm').click() } catch (e) {}");
            $award = $selenium->waitForElement(\WebDriverBy::xpath('//button[@id="tabBonusTrip"]'), 15);

            $this->savePageToLogs($selenium);

            $loggedInUserInfo = $selenium->driver->executeScript("return sessionStorage.getItem('loggedInUserInfo')");
            $dLogin = $this->http->JsonLog($loggedInUserInfo, 1);

            if (isset($dLogin->signinStatus)
                || $selenium->waitForElement(\WebDriverBy::xpath(self::xpathAuth), 0)
            ) {
                return true;
            }

            if ($award) {
//                $award->click();
                $selenium->driver->executeScript("document.querySelector('button#tabBonusTrip').click()");
            } else {
                $selenium->http->GetURL('https://www.koreanair.com/login');
                $loginPage = true;
            }

            if (!$selenium->waitForElement(WebDriverBy::xpath($loginXpath), 5)) {
                $selenium->driver->executeScript('window.stop();');
                $selenium->http->GetURL('https://www.koreanair.com/login');
            }
        } catch (\UnknownServerException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\UnrecognizedExceptionException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }

        $radio = $selenium->waitForElement(\WebDriverBy::xpath($radioXPath), 15);

        if ($radio) {
            $radio->click();
        }

        $login = $selenium->waitForElement(WebDriverBy::xpath($loginXpath), 15);
        $this->savePageToLogs($selenium);

        if (!$login && !isset($loginPage)) {
            $loggedInUserInfo = $selenium->driver->executeScript("return sessionStorage.getItem('loggedInUserInfo')");
            $dLogin = $this->http->JsonLog($loggedInUserInfo, 1);

            if (isset($dLogin->signinStatus) || $selenium->waitForElement(\WebDriverBy::xpath(self::xpathAuth), 0)
            ) {
                return true;
            }

            throw new \CheckRetryNeededException(5, 0);
        }

        $pass = $selenium->waitForElement(WebDriverBy::xpath("//label[contains(text(),' Password ')]/following-sibling::div/input"),
            0);

        $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@type='submit' and contains(text(),'Log in')]"),
            0);

        if (!$login || !$pass || !$btn) {
            if ($login && $pass && !$isRetry) {
                // helped
                return $this->auth($selenium, true);
            }
            $this->savePageToLogs($selenium);

            return false;
        }
        $mover->moveToElement($login);
        $mover->click();
        $mover->sendKeys($login, $this->AccountFields['Login']);

        $mover->moveToElement($pass);
        $mover->click();
        $mover->sendKeys($pass, $this->AccountFields['Pass']);

        sleep(1);

        $btn->click();
//        $login->sendKeys($this->AccountFields['Login']);
//        $pass->sendKeys($this->AccountFields['Pass']);
//        sleep(1);
//        $this->savePageToLogs($selenium);
//        $selenium->driver->executeScript("try { document.querySelector('kc-global-cookie-banner').shadowRoot.querySelector('.-confirm').click() } catch (e) {}");
//        $btn->click();

        if ($selenium->waitForElement(\WebDriverBy::xpath("
                    //span[contains(text(), 'This site can’t be reached') or contains(text(), 'This page isn’t working')]
                    | //h1[normalize-space()='Access Denied']
                    | //h1[normalize-space()='No internet']
                "), 5)) {
            throw new \CheckRetryNeededException(5, 0);
        }
//        sleep(5);
        $selenium->waitFor(function () use ($selenium) {
            $loggedInUserInfo = $selenium->driver->executeScript("return sessionStorage.getItem('loggedInUserInfo')");
            $dLogin = $this->http->JsonLog($loggedInUserInfo, 1);

            return !$selenium->waitForElement(WebDriverBy::xpath("//button[@type='submit' and (contains(text(),'Log in') or contains(text(),'Login'))]"),
                    0) && (isset($dLogin->signinStatus) || $selenium->waitForElement(\WebDriverBy::xpath(self::xpathAuth), 0)
                );
        }, 5);
        $this->savePageToLogs($selenium);

        if (!$this->ksessionId) {
            $this->getKsessionId($selenium);
        }

        return true;
    }

    private function getKsessionId($selenium)
    {
        $this->logger->notice(__METHOD__);
        $auth = null;
        /** @var \SeleniumDriver $seleniumDriver */
        $seleniumDriver = $selenium->http->driver;
        $this->logger->notice(__METHOD__ . '-1');

        try {
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
        } catch (BrowserCommunicatorException $e) {
            $this->logger->error('BrowserCommunicatorException: ' . $e->getMessage());
            $this->loginInfo = null;

            return;
            // if curl will be using then use restart
//            throw new \CheckRetryNeededException(5, 0);
        } catch (\Facebook\WebDriver\Exception\ScriptTimeoutException $e) {// worked
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
        }
        $this->logger->notice(__METHOD__ . '-2');

        $responseData = null;

        foreach ($requests as $n => $xhr) {
//            $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//            $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
            $auth = $xhr->request->getHeaders()['ksessionId'] ?? $auth;

            if (strpos($xhr->request->getUri(), '/api/li/auth/isUserLoggedIn') !== false
                && $xhr->response->getStatus() == 200) {
                $responseData = json_encode($xhr->response->getBody());
            }

            if ($auth && $responseData) {
                $response = $this->http->JsonLog($responseData);

                if (!$response || !isset($response->userInfo->skypassNumber) || $response->userInfo->skypassNumber === '000000000000') {
                    $this->loginInfo = null;
                } else {
                    $this->loginInfo = $response;
                }

                break;
            }
        }

        if ($auth) {
            $this->ksessionId = $auth;
        }
        $this->logger->debug("xhr ksessionId: $this->ksessionId");
    }

    private function tryAjax($selenium, $fields)
    {
        $this->logger->notice(__METHOD__);

        if (!$this->loginInfo) {
            $returnData = $selenium->driver->executeScript($tt = /** @lang JavaScript */
                '
                    var xhttp = new XMLHttpRequest();
                    xhttp.open("GET", "https://www.koreanair.com/api/li/auth/isUserLoggedIn", false);

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

            if (!$returnData) {
                return null;
            }
            $response = $this->http->JsonLog($returnData);

            if (!$response || !isset($response->userInfo->skypassNumber) || $response->userInfo->skypassNumber === '000000000000') {
                return null;
            }
            $this->loginInfo = $response;
        }

        $data = $this->getPayloadCalendar($fields);

        try {
            $calendarData = $selenium->driver->executeScript($tt = /** @lang JavaScript */
                '
                    var xhttp = new XMLHttpRequest();
                    xhttp.withCredentials = true;
                    xhttp.open("POST", "https://www.koreanair.com/api/ap/booking/avail/calendarFareMatrix", false);
                    xhttp.setRequestHeader("Content-type", "application/json");
                    xhttp.setRequestHeader("Accept", "application/json");
                    xhttp.setRequestHeader("ksessionid", "' . $this->ksessionId . '");
                    xhttp.setRequestHeader("channel", "pc");
                    xhttp.setRequestHeader("Connection", "keep-alive");
                    xhttp.setRequestHeader("Accept-Encoding", "gzip, deflate, br");
                    xhttp.setRequestHeader("x-sec-clge-req-type", "ajax");
                    xhttp.setRequestHeader("Referer", "https://www.koreanair.com/booking/calendar-fare-bonus");

        
                    var data = JSON.stringify(' . json_encode($data) . ');
                    var responseText = null;
                    xhttp.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                            responseText = this.responseText;
                        }
                    };
                    xhttp.send(data);
                    return responseText;
        '
            );
        } catch (\InvalidSelectorException | \Facebook\WebDriver\Exception\ScriptTimeoutException $e) {
            $this->logger->error('Exception: ' . $e->getMessage());
            $this->logger->debug($tt, ['pre' => true]);
            sleep(2);

            try {
                $this->logger->debug("retry script calendarData");
                $calendarData = $selenium->driver->executeScript($tt);
            } catch (\InvalidSelectorException | \Facebook\WebDriver\Exception\ScriptTimeoutException $e) {
                $this->logger->error('Exception: ' . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }
        }
        $this->logger->debug($tt, ['pre' => true]);
        $this->calendarData = $this->http->JsonLog($calendarData);

        if (isset($this->calendarData->message)
            && $this->calendarData->message === 'Communication was not successful. Please try again in a few minutes.'
        ) {
            $this->logger->notice('no flights on selected day');
            $this->SetWarning('No seats are available not only on the boarding date you have entered, but also nearby dates.');
            $data = ["routes" => []];

            return $data;
            /*            if ($this->attempt === 0) {
                            throw new \CheckRetryNeededException(2, 0);
                        }

                        throw new \CheckException($this->calendarData->message, ACCOUNT_PROVIDER_ERROR);*/
        }

        if ($this->calendarData && $this->notFlightDay(date('Ymd', $fields['DepDate']))) {
            $this->logger->notice('no flights on selected day');
            $this->SetWarning('There are no remaining seats in the outbound flights on the date selected');
            $data = ["routes" => []];

            return $data;
        }

        $data = $this->getPayloadMain($fields);

        try {
            $data = str_replace('"', '\"', json_encode($data));

            $selenium->driver->executeScript($tt = /** @lang JavaScript */ '
                fetch("https://www.koreanair.com/api/ap/booking/avail/awardAvailability", {
                    "headers": {
                        "accept": "application/json",
                        "channel": "pc",
                        "content-type": "application/json",
                        "ksessionid": "' . ($this->ksessionId) . '",
                        "x-sec-clge-req-type": "ajax"
                    },
                    "origin": "https://www.koreanair.com",
                    "referrer": "https://www.koreanair.com/booking/select-award-flight/departure",
                    "body": "' . $data . '",
                    "method": "POST",
                    "mode": "cors",
                }).then( response => response.json())
                    .then( result => {
                        let script = document.createElement("script");
                        let id = "dataPrice";
                        script.id = id;
                        script.setAttribute(id, JSON.stringify(result));
                        document.querySelector("body").append(script);
                    }).catch(error => {                    
                        let newDiv = document.createElement("div");
                        let id = "error";
                        newDiv.id = id;
                        let newContent = document.createTextNode(error);
                        newDiv.appendChild(newContent);
                        document.querySelector("body").append(newDiv);
                    });
               ');

            //Нужно ожидание пока Запрос отработает
            sleep(10);
            $this->savePageToLogs($selenium);

            $fetchData = $this->http->FindSingleNode('//script[@id="dataPrice"]/@dataprice');

            if (!isset($fetchData)) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $returnData = $this->http->JsonLog($fetchData);

            if (isset($returnData->verify_url)) {
                $this->logger->error('Verify this request');

                $flame = $selenium->waitForElement(\WebDriverBy::xpath('//iframe[@id="sec-cpt-if"]'), 0);

                if (!$flame) {
                    throw new \CheckRetryNeededException(5, 0);
                }

                $selenium->driver->switchTo()->frame($flame);

                do {
                    try {
                        $tic = $selenium->waitForElement(\WebDriverBy::xpath('//div[@id="sec-ch-ctdn-timer"]'), 1, false)->getText();
                        sleep(2);
                        $nextTic = $selenium->waitForElement(\WebDriverBy::xpath('//div[@id="sec-ch-ctdn-timer"]'), 1, false)->getText();
                    } catch (\Error $e) {
                        $this->logger->error('Don\'t verified...');

                        throw new \CheckRetryNeededException(5, 0);
                    }

                    $this->logger->debug('Verify...');
                } while ($tic !== $nextTic);
                $this->logger->error('Verified!');
                $selenium->driver->switchTo()->defaultContent();

                $this->savePageToLogs($selenium);
                $selenium->driver->executeScript($tt);

                sleep(10);
                $this->savePageToLogs($selenium);

                $fetchData = $this->http->FindSingleNode('//script[@id="dataPrice"][2]/@dataprice');

                if (!isset($fetchData)) {
                    throw new \CheckRetryNeededException(5, 0);
                }
                $returnData = $this->http->JsonLog($fetchData);
            }
        } catch (\InvalidSelectorException | \Facebook\WebDriver\Exception\ScriptTimeoutException $e) {
            // retry help
            $this->logger->error('InvalidSelectorException: ' . $e->getMessage());
            $this->logger->debug($tt, ['pre' => true]);

            try {
                $returnData = $selenium->driver->executeScript($tt);
            } catch (\InvalidSelectorException | \Facebook\WebDriver\Exception\ScriptTimeoutException $e) {
                $this->logger->error('InvalidSelectorException: ' . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }
        }

        $this->logger->debug($tt, ['pre' => true]);

        return $returnData;
    }

    private function getPayloadMain($fields)
    {
        return [
            'commercialFareFamilies' => ['KEBONUSALL'],
            'currency'               => $fields['Currencies'][0],
            'sta'                    => false,
            'segmentList'            => [
                [
                    'departureDate'    => date('Ymd', $fields['DepDate']),
                    'departureAirport' => $this->deepAirportCode,
                    'arrivalAirport'   => $this->arrAirportCode,
                ],
            ],
            'travelers' => [
                [
                    'travellerType' => 'ADT',
                    'fqtvNumber'    => $this->loginInfo->userInfo->skypassNumber ?? null,
                    'lastName'      => $this->loginInfo->userInfo->englishLastName ?? null,
                    'firstName'     => $this->loginInfo->userInfo->englishFirstName ?? null,
                ],
            ],
            'corporateCode' => 'string',
        ];
    }

    private function getPayloadCalendar($fields)
    {
        return [
            'commercialFareFamilies' => ['KEBONUSALL'],
            'sta'                    => false,
            'adult'                  => $fields['Adults'],
            'child'                  => 0,
            'infant'                 => 0,
            'segmentList'            => [
                [
                    'departureDate'    => date('Ymd', $fields['DepDate']),
                    'departureAirport' => $this->deepAirportCode,
                    'arrivalAirport'   => $this->arrAirportCode,
                ],
            ],
            'travelers' => [
                [
                    'travellerType' => 'ADT',
                    'fqtvNumber'    => $this->loginInfo->userInfo->skypassNumber ?? null,
                    'lastName'      => $this->loginInfo->userInfo->englishLastName ?? null,
                    'firstName'     => $this->loginInfo->userInfo->englishFirstName ?? null,
                    'discountCode'  => '',
                ],
            ],
            'corporateCode' => '',
            'type'          => '[KeCalendarFareBonusMatrixRequest] Set KeCalendarFareBonusMatrixRequest',
        ];
    }

    private function getCabin(string $cabin, bool $isFlip = true)
    {
        $cabins = [
            'economy'        => 'X', // Economy Class
            'premiumEconomy' => 'O', // Prestige Class
            //            'business' => ' ',
            'firstClass' => 'A', // First Class
        ];

        if ($isFlip) {
            $cabins = array_flip($cabins);
        }

        if (isset($cabins[$cabin])) {
            return $cabins[$cabin];
        }
        $this->sendNotification("RA check cabin {$cabin} (" . var_export($isFlip, true) . ") // MI");

        throw new \CheckException("check cabin {$cabin} (" . var_export($isFlip, true) . ")", ACCOUNT_ENGINE_ERROR);
    }

    private function getAwardType(string $cabin)
    {
        $cabins = [
            'X' => 'Economy Class', // Economy Class
            'O' => 'Prestige Class', // Prestige Class
            //            'business' => ' ',
            'A' => 'First Class', // First Class
        ];

        if (isset($cabins[$cabin])) {
            return $cabins[$cabin];
        }
        $this->sendNotification("RA check cabin {$cabin} // MI");

        throw new \CheckException("check cabin {$cabin}", ACCOUNT_ENGINE_ERROR);
    }

    private function parseRewardFlights($data, $fields = []): array
    {
        $this->logger->notice(__METHOD__);
        $routes = [];

        if (count($data->upsellBoundAvailList) > 1) {
            $this->sendNotification('RA chech upsellBoundAvailList // MI');

            throw new \CheckException('upsellBoundAvailList > 1', ACCOUNT_ENGINE_ERROR);
        }

        foreach ($data->upsellBoundAvailList[0]->availFlightList as $availFlightList) {
            foreach ($availFlightList->commercialFareFamilyList as $fare) {
                if ($fare->soldout === true) {
                    $this->logger->notice('Skip soldOut');
                    $skipped = true;

                    continue;
                }
                $route = [
                    'distance'  => null,
                    'num_stops' => $availFlightList->numberOfStops,
                    'times'     => [
                        'flight'  => $availFlightList->totalFlyingTime,
                        'layover' => null,
                    ],
                    'redemptions' => [
                        'miles'   => $fare->travellerTypeFareList[0]->mileage,
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $fare->travellerTypeFareList[0]->currency,
                        'taxes'    => $fare->travellerTypeFareList[0]->totalAmount,
                        'fees'     => null,
                    ],
                    'connections'    => [],
                    'tickets'        => $fare->seatCount,
                    'award_type'     => $this->getAwardType($fare->bookingClass),
                    'classOfService' => $this->clearCOS($this->getAwardType($fare->bookingClass)),
                ];

                foreach ($availFlightList->flightInfoList as $flight) {
                    $route['connections'][] = [
                        'departure' => [
                            'date'     => date('Y-m-d H:i', strtotime($flight->departureDateTime)),
                            'dateTime' => strtotime($flight->departureDateTime),
                            'airport'  => $flight->departureAirport,
                            'terminal' => $flight->departureTerminal ?? null,
                        ],
                        'arrival' => [
                            'date'     => date('Y-m-d H:i', strtotime($flight->arrivalDateTime)),
                            'dateTime' => strtotime($flight->arrivalDateTime),
                            'airport'  => $flight->arrivalAirport,
                            'terminal' => $flight->arrivalTerminal ?? null,
                        ],
                        'meal'       => null,
                        'cabin'      => $this->getCabin($fare->bookingClass),
                        'fare_class' => $fare->bookingClass,
                        'flight'     => ["{$flight->carrierCode}{$flight->flightNumber}"],
                        'airline'    => $flight->carrierCode,
                        'operator'   => $flight->operationCarrierCode,
                        'distance'   => null,
                        'aircraft'   => $flight->aircraftTypeDesc,
                        'times'      => [
                            'flight'  => $flight->flyingTime,
                            'layover' => null,
                        ],
                    ];
                }
                $this->logger->debug('Parsed data:');
                $this->logger->debug(var_export($route, true), ['pre' => true]);
                $routes[] = $route;
            }
        }

        if (empty($routes) && isset($skipped)) {
            $this->SetWarning('All tickets are sold out');
        }

        if (empty($routes) && empty($data->upsellBoundAvailList[0]->availFlightList)) {
            $this->SetWarning('No flights found');
        }

        return $routes;
    }

    private function validRoute($fields): bool
    {
        $this->logger->notice(__METHOD__);

        $airports = \Cache::getInstance()->get('ra_korean_airports2');

        if (!$airports || !is_array($airports)) {
            $airports = [];
            $this->http->GetURL("https://www.koreanair.com/api/et/route/c/a/getReservationAirport?airportCode=&directionType=D&flowType=NR&langCode=en&nationCode=us&tripType=RO",
                [], 20);

            if ($this->isBadProxy()
                || strpos($this->http->Error, 'Network error 28 - Connection timed out after') !== false
                || strpos($this->http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly') !== false
            ) {
                $this->setProxyGoProxies(null, 'kr');

                $this->http->GetURL("https://www.koreanair.com/api/et/route/c/a/getReservationAirport?airportCode=&directionType=D&flowType=NR&langCode=en&nationCode=us&tripType=RO");

                if ($this->isBadProxy()
                    || strpos($this->http->Error, 'Network error 28 - Connection timed out after') !== false
                    || strpos($this->http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly') !== false) {
                    throw new \CheckRetryNeededException(5, 0);
                }
            }
            $data = $this->http->JsonLog(null, 1, true);

            foreach ($data['locationInfoList'] as $locationInfoList) {
                $airports[] = $locationInfoList['airportCode'];
            }

            if (!empty($airports)) {
                \Cache::getInstance()->set('ra_korean_airports2', $airports, 60 * 60 * 24);
            }
        }

        foreach ($airports as $airportCode) {
            if (strcasecmp($airportCode, $fields['DepCode']) === 0) {
                $this->deepAirportCode = $airportCode;
            }

            if (strcasecmp($airportCode, $fields['ArrCode']) === 0) {
                $this->arrAirportCode = $airportCode;
            }

            if ($this->deepAirportCode && $this->arrAirportCode) {
                break;
            }
        }

        if ($this->deepAirportCode && $this->arrAirportCode) {
            $route = "{$this->deepAirportCode}-{$this->arrAirportCode}";
        } else {
            return false;
        }

        $data = \Cache::getInstance()->get('ra_korean_award_route2' . $this->deepAirportCode . '-' . $this->arrAirportCode);

        if (!$data) {
            $this->http->GetURL("https://www.koreanair.com/api/et/route/c/a/isAwardRoute?routeList={$route}",
                [], 20);

            if ($this->isBadProxy() || strpos($this->http->Error,
                    'Network error 28 - Connection timed out after') !== false) {
                if ($this->AccountFields['ParseMode'] === 'awardwallet') {
                    $this->setProxyMount();
                } else {
                    $this->setProxyGoProxies();
                }

                $this->http->GetURL("https://www.koreanair.com/api/et/route/c/a/isAwardRoute?routeList={$route}",
                    [], 20);
            }
            $data = $this->http->JsonLog();

            if ($this->isBadProxy()) {
                throw new \CheckRetryNeededException(5, 1);
            }

            if (!empty($data)) {
                \Cache::getInstance()->set('ra_korean_award_route2' . $this->deepAirportCode . '-' . $this->arrAirportCode,
                    $data, 60 * 60 * 24 * 2);
            }
        }

        return !isset($data->award) || $data->award === true;
    }

    private function clearCOS(string $cos): string
    {
        if (preg_match("/^(.+\w+) (?:cabin|class|standard|reward)$/i", $cos, $m)) {
            $cos = $m[1];
        }

        return $cos;
    }
}
