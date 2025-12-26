<?php

namespace AwardWallet\Engine\aviancataca\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class ParserSMR extends \TAccountChecker
{
    use ProxyList;
    use \SeleniumCheckerHelper;

    private $inCabin;
    private $warning;
    private $bearer = null;
    private $ip;
    private $config;
    private $isHot;
    private $mover;
    private $isLoggedIn = false;

    public static function getRASearchLinks(): array
    {
        return ['https://www.lifemiles.com/fly/find' => 'search page'];
    }

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();
        $this->debugMode = $this->AccountFields['DebugState'] ?? false;

        $this->useSelenium();
        $this->KeepState = true;

        $this->http->setHttp2(true);
        $array = ['fr', 'de', 'us', 'au', 'pt', 'ca'];
        $targeting = $array[array_rand($array)];

//        if ($targeting === 'us' && $this->AccountFields['ParseMode'] === 'awardwallet') {
        $this->setProxyGoProxies(null, $targeting);
//        } else {
//            $this->setProxyNetNut(null, $targeting);
//        }

        $this->http->saveScreenshots = true;

        if (rand(0, 1)) {
            $this->useChromePuppeteer();
            $request = FingerprintRequest::chrome();
        } else {
            $this->useFirefoxPlaywright();
            $request = FingerprintRequest::firefox();
        }
//        if ($this->attempt >= 1) {
//            $this->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
//        } else {
//            $this->seleniumRequest->setOs(\SeleniumFinderRequest::OS_WINDOWS);
//        }

        $this->seleniumOptions->addHideSeleniumExtension = false;

        $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if (isset($fingerprint)) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->http->setUserAgent($fingerprint->getUseragent());
        }

        $this->seleniumRequest->setHotSessionPool(
            self::class,
            $this->AccountFields['ProviderCode'],
            $this->AccountFields['AccountKey']
        );
    }

    public function IsLoggedIn()
    {
        if (strpos($this->http->currentUrl(), 'lifemiles.com') !== false) {
            $this->isHot = true;
            $this->http->GetURL("https://www.lifemiles.com/fly/find");

            $this->waitForElement(\WebDriverBy::xpath("//input[@id='ar_bkr_fromairport']"), 15);
            $token = $this->driver->manage()->getCookieNamed("rfhtkn");
            $token = $token["value"];

            $token = $this->runRefreshToken($token);

            if (isset($token["TokenGrantResponse"]["access_token"])) {
                $this->bearer = $token["TokenGrantResponse"]["access_token"];
            }

            if (!isset($this->bearer)) {
                return false;
            }

            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if ($this->AccountFields['ParseMode'] === 'awardwallet') {
            $this->logger->error('parser off');

            throw new \CheckException('Account blocked', ACCOUNT_ENGINE_ERROR);
        }

        $this->mover = new \MouseMover($this->driver);
        $this->mover->logger = $this->logger;
        $this->mover->enableCursor();
        $this->mover->duration = random_int(40, 60) * 100;
        $this->mover->steps = 3;

        $this->http->GetURL('https://www.lifemiles.com');

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Currently this service is not available due to maintenance work.')] | //p/node()[contains(., 'En este momento nuestros sistemas no están disponibles debido a que estamos realizando un mantenimiento programado.')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(., 'Actualmente, nuestro sitio web y app están temporalmente fuera de servicio debido a un mantenimiento programado')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (!$this->waitForElement(\WebDriverBy::xpath("//div[@class='homepage-ui-Homepage_landingJoin']//button[1][@class='homepage-ui-Homepage_landingLoginButton']/span[contains(., 'Log in')] | //span[@class='account-ui-AccountActivitySubHeader_titleAmount']"),
                40)) {
            $this->logger->error("Page not loading");
            $this->saveResponse();

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($this->waitForElement(\WebDriverBy::xpath('//span[@class="account-ui-AccountActivitySubHeader_titleAmount"]'), 5)) {
            $this->isLoggedIn = true;

            return true;
        }

        if ($cookieBtn = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class, 'CookiesBrowserAlert_acceptButtonNO')]"),
                5)) {
            sleep(2);
            $this->mover->moveToElement($cookieBtn);
            $this->mover->click();

            if ($this->waitForElement(\WebDriverBy::xpath("//button[contains(@class, 'CookiesBrowserAlert_acceptButtonNO')]"),
                    2)) {
                $this->driver->executeScript("document.querySelector('.CookiesBrowserAlert_acceptButtonNO.CookiesBrowserAlert_acceptButton').click();");
            }
        }

        $logIn = $this->waitForElement(\WebDriverBy::xpath("//div[@class='homepage-ui-Homepage_landingJoin']//button[1][@class='homepage-ui-Homepage_landingLoginButton']/span[contains(., 'Log in')]"),
                0);

        if (!$logIn) {
            $this->logger->error("Page not loading");
            $this->saveResponse();

            throw new \CheckRetryNeededException(5, 0);
        }
        $this->saveResponse();

        sleep(2);
        $this->mover->moveToElement($logIn);
        $this->mover->click();

        $resXpath = "
            //input[@id = 'username']
            | //a[@id = 'social-Lifemiles']
            | //p[contains(text(), 'activity and behavior on this site made us think that you are a bot')]
            | //p[contains(text(), 'activity and behavior on our site made us think you could be a robot')]
            | //*[self::h1 or self::span][contains(text(), 'This site can’t be reached')]
            | //h1[contains(text(), 'Access Denied')]
            | //h1[contains(text(), 'Vive tus millas,')]
            | //div[contains(@class, 'AccountActivityCard_userId')]
            | //p/node()[contains(., 'En este momento nuestros sistemas no están disponibles debido a que estamos realizando un mantenimiento programado.')]
            ";
        $this->saveResponse();

        if ($this->waitForElement(\WebDriverBy::xpath($resXpath), 20)) {
            return true;
        }

        return false;
    }

    public function Login()
    {
        if ($this->isLoggedIn) {
            return true;
        }

        $resXpath = "
            //input[@id = 'username']
            | //a[@id = 'social-Lifemiles']
            | //p[contains(text(), 'activity and behavior on this site made us think that you are a bot')]
            | //p[contains(text(), 'activity and behavior on our site made us think you could be a robot')]
            | //*[self::h1 or self::span][contains(text(), 'This site can’t be reached')]
            | //h1[contains(text(), 'Access Denied')]
            | //h1[contains(text(), 'Vive tus millas,')]
            | //div[contains(@class, 'AccountActivityCard_userId')]
            | //p/node()[contains(., 'En este momento nuestros sistemas no están disponibles debido a que estamos realizando un mantenimiento programado.')]
            | //div[@id = 'sec-cpt-if']
        ";
        $this->waitForElement(\WebDriverBy::xpath($resXpath), 0);
        $this->saveResponse();

        if ($this->waitForElement(\WebDriverBy::xpath("//h1[contains(text(), 'Vive tus millas,')]"), 0, false)) {
            $this->logger->notice("try to load login form one more time");
            $this->http->GetURL("https://www.lifemiles.com/integrator/v1/authentication/oauth/authorize?client_id=lm_website&redirect_uri=https%3A%2F%2Fwww.lifemiles.com%2Foauth-signin&response_type=token&state=%7B%27Access-Level%27%3A%20%270%27%2C%20%27Redirect-Uri%27%3A%20%27%27%7D&scope=read");
            $this->waitForElement(\WebDriverBy::xpath($resXpath), 10);
            $this->saveResponse();
        }

        if ($loginWithUsername = $this->waitForElement(\WebDriverBy::xpath("//a[@id = 'social-Lifemiles']"), 0)) {
            try {
                $this->mover->moveToElement($loginWithUsername);
                $this->mover->click();
            } catch (\TimeOutException | \Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error('caught ' . get_class($e) . ' on line ' . $e->getLine());
                $this->saveResponse();

                if ($loginWithUsername = $this->waitForElement(\WebDriverBy::xpath("//a[@id = 'social-Lifemiles']"),
                    0)) {
                    $this->mover->moveToElement($loginWithUsername);
                    $this->mover->click();
                }
            }
            $this->waitForElement(\WebDriverBy::xpath($resXpath), 10);

            $this->saveResponse();
        }

        $this->driver->executeScript('let btn = document.querySelector("button[class*=CookiesBrowserAlert_acceptButton]"); if (btn) btn.click();');

        // waiting for full form loading
        $this->waitForElement(\WebDriverBy::xpath('//button[contains(@class, "Button_button__") and not(@disabled) and span[contains(text(), "Log in") or contains(text(), "Ingresar")]] | //button[@id = "Login-confirm"]'),
            20);

        $login = $this->waitForElement(\WebDriverBy::xpath("//input[@id = 'username']"), 0);

        // may be too long loading
        if (!$login && $this->waitForElement(\WebDriverBy::xpath('//img[@alt="loading..."]'), 0)) {
            $login = $this->waitForElement(\WebDriverBy::xpath("//input[@id = 'username']"), 20);
            $this->saveResponse();
        }

        $pass = $this->waitForElement(\WebDriverBy::xpath("//input[@id = 'password']"), 0);

        $this->saveResponse();

        if (!$login || !$pass) {
            $this->logger->error("something went wrong");
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

            // sessuin is active
            if ($this->http->FindSingleNode("//div[contains(@class, 'AccountActivityCard_userId')]")) {
                return true;
            }

            $this->saveResponse();
            // Currently this service is not available due to maintenance work.
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Currently this service is not available due to maintenance work.')] | //p/node()[contains(., 'En este momento nuestros sistemas no están disponibles debido a que estamos realizando un mantenimiento programado.')]")) {
                throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode("//p[contains(., 'Actualmente, nuestro sitio web y app están temporalmente fuera de servicio debido a un mantenimiento programado')]")) {
                throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $this->http->FindSingleNode('//div[@id = "homepage-ui-app"]//img[@alt="loading..."]/@alt')
                || $this->http->FindSingleNode('//div[@id = "root"]//img[@alt="loading..."]/@alt')
                || $this->http->FindSingleNode('//p[contains(text(), "Lo sentimos, no pudimos realizar tu solicitud, por favor intenta nuevamente. Si el problema persiste contacta a nuestro Call Center")]')
                || $this->http->FindSingleNode('//p[contains(text(), "Sorry, we could not process your request, please try again.")]')
                || $this->http->FindSingleNode("//*[self::h1 or self::span][contains(text(), 'This site can’t be reached')]")
                || $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")
                || $this->http->currentUrl() == 'https://www.lifemiles.com/integrator/v1/authentication/oauth/authorize?client_id=lm_website&redirect_uri=https%3A%2F%2Fwww.lifemiles.com%2Foauth-signin&response_type=token&state=%7B%27Access-Level%27%3A%20%270%27%2C%20%27Redirect-Uri%27%3A%20%27%27%7D&scope=read'
                || $this->http->currentUrl() == 'https://www.lifemiles.com/sign-in'
            ) {
                $this->callRetries();

                if ($this->http->FindSingleNode('//a[contains(text(), "Iniciar sesión")]')) {
                    throw new \CheckRetryNeededException(3);
                }
            }

            return false;
        }

        try {
            $this->logger->debug("click by login");
            $login->click();
            // selenium trace workaround
        } catch (\Facebook\WebDriver\Exception\StaleElementReferenceException | \StaleElementReferenceException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->saveResponse();

            sleep(5);
            $login = $this->waitForElement(\WebDriverBy::xpath("//input[@id = 'username']"), 0);
            $pass = $this->waitForElement(\WebDriverBy::xpath("//input[@id = 'password']"), 0);
        }

        $this->logger->debug("clear login");
        $this->mover->moveToElement($login);
        $this->mover->click();
        $login->clear();
        $this->logger->debug("set login");
        $this->mover->sendKeys($login, $this->AccountFields['Login'], 20);

        $this->logger->debug("click by pass");
        $this->mover->moveToElement($pass);
        $this->mover->click();
        $pass->clear();
        $this->logger->debug("set pass");
        $this->mover->sendKeys($pass, $this->AccountFields['Pass'], 20);

        $this->mover->moveToElement($login);
        $this->mover->click();

        $btn = $this->waitForElement(\WebDriverBy::xpath('//button[contains(@class, "Button_button__") and not(@disabled) and span[contains(text(), "Log in") or contains(text(), "Ingresar")]] | //button[not(@disabled) and @id = "Login-confirm"]'),
            5);
        $this->saveResponse();

        if (!$btn) {
            $this->logger->error("something went wrong");

            if ($this->http->FindSingleNode("//input[@id = 'username']/following-sibling::p[contains(@class, 'authentication-ui-Input_imageInvalid')]/@class")) {
                throw new \CheckException("Your User ID or Password is incorrect. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }
        $this->mover->moveToElement($btn);
        $this->mover->click();

        if ($this->waitForElement(\WebDriverBy::xpath("//div[@id = 'sec-container']"), 5)) {
            $this->saveResponse();
            $this->overlayWorkaround();
        }

        $loginSuccessXpath = "
            //div[contains(@class, 'AccountActivityCard_userId')]
            | //p[contains(text(), 'our activity and behavior on this site made us think that you are a bot')]
            | //p[contains(text(), 'activity and behavior on our site made us think you could be a robot')]
            | //p[contains(@class, 'GeneralErrorModal_description')]
            | //button[contains(@class, 'authentication-ui-InitialPage_buttonDontShow')]
            | //h1[contains(text(), 'Confirma tu identidad') or contains(text(), 'Confirm your identity')]
            | //h1[contains(text(), 'Sorry, the page you tried cannot be found!')]
        ";
        $loginSuccess = $this->waitForElement(\WebDriverBy::xpath($loginSuccessXpath), 15);

        if (!$loginSuccess) {
            if ($this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")) {
                throw new \CheckRetryNeededException(5, 0);
            }
        }

        if ($loginSuccess && stripos($loginSuccess->getText(), "10990") !== false) {
            throw new \CheckException("Account Lockout", ACCOUNT_PREVENT_LOCKOUT);
        }

        if (!$loginSuccess) {
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'En este momento no hemos podido encontrar lo que buscas y la operación no pudo ser completada.')] | //p[contains(text(), ' complete the operation at this time. For assistance, please visit our')]")) {
                throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        if ($this->cancel2faSetup($loginSuccess)) {
            $loginSuccess = $this->waitForElement(\WebDriverBy::xpath($loginSuccessXpath), 15);
        }

        try {
            $conditions = !$loginSuccess && $this->waitForElement(\WebDriverBy::xpath('//img[@alt="loading..."]'), 10);
        } catch (
        \Facebook\WebDriver\Exception\StaleElementReferenceException
        | \StaleElementReferenceException
        $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->saveResponse();
            $conditions = $this->waitForElement(\WebDriverBy::xpath('//img[@alt="loading..."]'), 10);
        }

        // may be too long loading
        if ($conditions) {
            $this->waitForElement(\WebDriverBy::xpath($loginSuccessXpath));
        }
        $this->saveResponse();

        if ($this->http->FindSingleNode("
                //p[contains(text(), 'our activity and behavior on this site made us think that you are a bot')]
                | //p[contains(text(), 'activity and behavior on our site made us think you could be a robot')]
                | //*[self::h1 or self::span][contains(text(), 'This site can’t be reached')]
                | //h1[contains(text(), 'Sorry, the page you tried cannot be found!')]
            ")
        ) {
            $this->callRetries();
        }

        if (!$this->waitForElement(\WebDriverBy::xpath('//span[@class="account-ui-AccountActivitySubHeader_titleAmount"]'), 5)) {
            throw new \CheckRetryNeededException(5, 0);
        }

        return true;
    }

    public function getRewardAvailabilitySettings()
    {
        $arrCurrencies = ['USD'];

        return [
            'supportedCurrencies'      => $arrCurrencies,
            'supportedDateFlexibility' => 0, // 3
            'defaultCurrency'          => 'USD',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->http->FilterHTML = false;
        $this->http->LogHeaders = true;

        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        if (empty($this->bearer)) {
            $this->waitForElement(\WebDriverBy::xpath('//span[@class="account-ui-AccountActivitySubHeader_titleAmount"]'), 10);
            $this->saveResponse();

            $cookies = $this->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] == 'dra3j') {
                    $this->bearer = $cookie['value'];
                }
            }
        }

        $this->logger->debug("Access_token: " . $this->bearer);

        if (empty($this->bearer)) {
            throw new \CheckRetryNeededException(5, 0);
        }

        try {
            $cabinData = $this->getCabinFields(false);
            $this->inCabin = $fields['Cabin'];
            $fields['cabinName'] = $cabinData[$this->inCabin]['cabinName'];
            $fields['Cabin'] = $cabinData[$this->inCabin]['cabin'];

            if ($fields['Currencies'][0] !== 'USD') {
                $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
                $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
            }

            if ($fields['Adults'] > 8) {
                $this->SetWarning("you can check max 8 travellers");

                return [];
            }

            [$airportsOrigin, $airportsDestination] = $this->getAirports();

            if (!array_key_exists($fields['DepCode'], $airportsOrigin)) {
                $this->SetWarning('no flights from ' . $fields['DepCode']);

                return [];
            }

            if (!array_key_exists($fields['ArrCode'], $airportsDestination)) {
                $this->SetWarning('no flights to ' . $fields['ArrCode']);

                return [];
            }

            $fields['DepCity'] = $airportsOrigin[$fields['DepCode']];
            $fields['ArrCity'] = $airportsDestination[$fields['ArrCode']];
            $payload = "{\"cabin\":\"{$fields['Cabin']}\",\"ftNum\":\"\",\"internationalization\":{\"language\":\"es\",\"country\":\"us\",\"currency\":\"usd\"},\"itineraryName\":\"One-Way\",\"itineraryType\":\"OW\",\"numOd\":1,\"ods\":[{\"id\":1,\"origin\":{\"cityName\":\"{$fields['DepCity']}\",\"cityCode\":\"{$fields['DepCode']}\"},\"destination\":{\"cityName\":\"{$fields['ArrCity']}\",\"cityCode\":\"{$fields['ArrCode']}\"}}],\"paxNum\":{$fields['Adults']},\"selectedSearchType\":\"SMR\"}";

            $headers = [
                'Accept'         => 'application/json',
                'Authorization'  => 'Bearer ' . $this->bearer,
                'Content-Type'   => 'application/json',
                'realm'          => 'lifemiles',
                'Origin'         => 'https://www.lifemiles.com',
                'Referer'        => 'https://www.lifemiles.com/',
            ];
            $this->http->RetryCount = 0;

            $res = $this->getXHRPost("https://api.lifemiles.com/svc/air-redemption-par-header-private", $headers,
                $payload);

            $data = $this->http->JsonLog($res, 1, false, 'schHcfltrc');

            if (!isset($data->idCotizacion) || !isset($data->sch, $data->sch->schHcfltrc)) {
                if (isset($data->description)
                    && ($data->description === 'The origin/destination entered is not available for the selected airline. Please try with another search'
                    | $data->description === 'El origen/destino ingresado no está disponible para la aerolínea seleccionada. Por favor intenta otra búsqueda')) {
                    $this->SetWarning('The origin/destination entered is not available for the selected airline. Please try with another search');

                    return ['routes' => []];
                }

                if ($this->http->Response['code'] == 403) {
                    throw new \CheckRetryNeededException(5, 0);
                }

                throw new \CheckException('no data with idCotizacion & schHcfltrc', ACCOUNT_ENGINE_ERROR);
            }
            $idCoti = $data->idCotizacion;
            $schHcfltrc = $data->sch->schHcfltrc;
            $searchTypePrioritize = $data->searchTypePrioritize;
//        // no range
            $fields['DepDate'] = date("Y-m-d", $fields['DepDate']);

            if (!isset($this->State["proxy-ip"])) {
                $http = clone $this->http;
                $http->GetURL('https://ipinfo.io/ip');
                $ip = $http->Response['body'];
                $ip = $http->FindPreg("/^(\d+\.\d+\.\d+\.\d+)$/", false, $ip);

                if (isset($ip)) {
                    $this->ip = $ip;
                }
                unset($http);
            } else {
                $this->ip = $this->State["proxy-ip"];
            }

            $routes = $this->ParseRewardAirlines($fields, $idCoti, $schHcfltrc, $searchTypePrioritize);
        } finally {
            if ($this->ErrorCode !== 9 && empty($routes)) {
                $this->keepSession(false);

                throw new \CheckRetryNeededException(5, 5);
            }
        }
        $this->keepSession(true);

        return ['routes' => $routes];
    }

    protected function parseReCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode('//iframe[@id="sec-cpt-if"]/@data-key');
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $recognizer->RecognizeTimeout = 120;

        $postData = [
            "type"       => "RecaptchaV2TaskProxyless",
            "websiteURL" => $this->http->currentUrl(),
            "websiteKey" => $key,
        ];

        return $this->recognizeAntiCaptcha($recognizer, $postData);
    }

    private function getAirports(?string $airline = 'SMR')
    {
        $this->logger->notice(__METHOD__);
        $extKey = '';

        if (!empty($airline)) {
            $extKey = '_' . $airline;
            $airline = '/' . $airline;
        }

        $airportsOrigin = \Cache::getInstance()->get('aviancataca_ra_depcodes' . $extKey);
        $airportsDestination = \Cache::getInstance()->get('aviancataca_ra_arrcodes' . $extKey);

        if ($airportsOrigin === false || $airportsDestination === false) {
            $headers = [
                'Accept'        => 'application/json',
                'Referer'       => 'https://www.lifemiles.com/',
                'Origin'        => 'https://www.lifemiles.com/',
                'Authorization' => 'Bearer ' . $this->bearer,
                'realm'         => 'lifemiles',
            ];
            $url = "https://api.lifemiles.com/svc/air-redemption-par-booker/en/us/usd" . $airline;
            $response = $this->getXHRGet($url, $headers);
            $data = $this->http->JsonLog($response, 0);

            if (isset($data->find, $data->find->booker, $data->find->booker->airports, $data->find->booker->airports->destination, $data->find->booker->airports->origin)) {
                $airportsOrigin = $airportsDestination = [];

                foreach ($data->find->booker->airports->origin as $item) {
                    $airportsOrigin[$item->code] = $item->cityName;
                }

                foreach ($data->find->booker->airports->destination as $item) {
                    $airportsDestination[$item->code] = $item->cityName;
                }

                if (!empty($airportsOrigin) && !empty($airportsDestination)) {
                    \Cache::getInstance()->set('aviancataca_ra_depcodes' . $extKey, $airportsOrigin, 60 * 60 * 24);
                    \Cache::getInstance()->set('aviancataca_ra_arrcodes' . $extKey, $airportsDestination, 60 * 60 * 24);
                } else {
                    $this->logger->error('other format json');

                    throw new \CheckException('no list airports', ACCOUNT_ENGINE_ERROR);
                }
            } else {
                throw new \CheckException('no list airports', ACCOUNT_ENGINE_ERROR);
            }
        }

        return [$airportsOrigin, $airportsDestination];
    }

    private function getXHRGet($url, array $headers)
    {
        $this->logger->notice(__METHOD__);
        $headersString = "";

        foreach ($headers as $key => $value) {
            $headersString .= 'xhttp.setRequestHeader("' . $key . '", "' . $value . '");
        ';
        }

        $script = '
                var xhttp = new XMLHttpRequest();
                xhttp.withCredentials = true;
                xhttp.open("GET", "' . $url . '", false);
                ' . $headersString . '
                var responseText = null;
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && (this.status == 200 || this.status == 202)) {
                        responseText = this.responseText;
                    } else {
                        responseText = this.status;
                    }
                };
                xhttp.send();
                return responseText;
            ';
        $this->logger->debug("[run script]:");
        $this->logger->debug($script, ['pre' => true]);

        return $this->driver->executeScript($script);
    }

    private function getXHRPost($url, array $headers, $payload)
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

        $payload = base64_encode($payload);
        $script = '
                var xhttp = new XMLHttpRequest();
                xhttp.withCredentials = true;
                var data = atob("' . $payload . '");
                xhttp.open("POST", "' . $url . '", false);
                ' . $headersString . '
                var responseText = null;
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && (this.status == 200 || this.status == 202)) {
                        responseText = this.responseText;
                    } else {
                        responseText = this.status;
                    }
                };
                xhttp.send(data);
                return responseText;
            ';

        $this->logger->debug("[run script]:");
        $this->logger->debug($script, ['pre' => true]);

        sleep(5);

        return $this->driver->executeScript($script);
    }

    private function getCabinFields($onlyKeys = true): array
    {
        // show - means, show in answers
        $cabins = [
            'economy'        => ['Economy', 'cabin' => 1, 'cabinName' => ['Economy on sale', 'Economy'], 'show' => true],
            'premiumEconomy' => [
                'Economy',
                'cabin'     => 1,
                'cabinName' => ['Economy on sale', 'Economy'],
                'show'      => false,
            ],
            'firstClass' => ['Business or First', 'cabin' => 2, 'cabinName' => ['First class'], 'show' => true],
            'business'   => [
                'Business or First',
                'cabin'     => 2,
                'cabinName' => ['Business on sale', 'Business'],
                'show'      => true,
            ],
        ];

        if ($onlyKeys) {
            return array_keys($cabins);
        }

        return $cabins;
    }

    private function parseRewardFlights($data, ?bool $isRetry = false): array
    {
        $this->logger->notice(__METHOD__);
        $routes = [];

        $cabinArr = array_filter($this->getCabinFields(false), function ($v) {
            return $v['show'];
        });
        $cabinNames = [];

        foreach ($cabinArr as $k => $v) {
            foreach ($v['cabinName'] as $name) {
                $cabinNames[$name] = $k;
            }
        }

        $this->warning = null;

        if (isset($data->tripsList) && is_array($data->tripsList) && !empty($data->tripsList)) {
            $this->logger->debug("Found " . count($data->tripsList) . " routes");

            foreach ($data->tripsList as $numRoot => $trip) {
                $result = ['connections' => []];
                $this->logger->notice("route " . $numRoot);

                $itOffers = null;

                foreach ($trip->products as $list) {
                    // no filter
//                    if (in_array($list->cabinName, $cabin) && $list->soldOut !== true) { //cabin = $fields['cabinName']
//                        $itOffers[] = $list;
//                    }
                    if (!isset($list->soldOut)) {
                        continue;
                    }

                    if ($list->soldOut !== true) {
                        $itOffers[] = ['cabin' => $list->cabinName, 'cabinCode' => $list->cabinCode, 'offer' => $list];
                    }
                }

                if (!isset($itOffers)) {
                    $this->logger->debug('skip rout ' . $numRoot . ' (soldOut for cabin...)');
                    $warningSoldOut = true;

                    continue;
                }
                $tickets = null;

                // for debug
//                $this->http->JsonLog(json_encode($trip), 1);

                $segments = [];

                foreach ($trip->flightsDetail as $flightsDetail) {
                    $seg = [
                        'departure' => [
                            'date'     => $flightsDetail->departingDate . ' ' . $flightsDetail->departingTime,
                            'dateTime' => strtotime($flightsDetail->departingDate . ' ' . $flightsDetail->departingTime),
                            'airport'  => $flightsDetail->departingCityCode,
                        ],
                        'arrival' => [
                            'date'     => $flightsDetail->arrivalDate . ' ' . $flightsDetail->arrivalTime,
                            'dateTime' => strtotime($flightsDetail->arrivalDate . ' ' . $flightsDetail->arrivalTime),
                            'airport'  => $flightsDetail->arrivalCityCode,
                        ],
                        'num_stops' => $flightsDetail->numberOfStops,
                        'flight'    => [$flightsDetail->marketingCompany . $flightsDetail->flightNumber],
                        'airline'   => $flightsDetail->marketingCompany,
                        'operator'  => $flightsDetail->operatedCompany,
                        'times'     => [
                            'flight'  => null,
                            'layover' => null,
                        ],
                    ];
                    $segments[] = ['id' => $flightsDetail->id, 'seg' => $seg];
                }

                $isOld = false;

                try {
//                    $conn = $this->getConnections($segments, $itOffers);
                    $conn = $this->getConnectionsNew($segments, $itOffers, $isOld);
                } catch (\ErrorException $e) {
                    $this->logger->error($e->getMessage());
                    $this->logger->error('something went wrong with getConnections');
                    $this->logger->warning(var_export($segments, true), ['pre' => true]);
                    $this->logger->error(var_export($itOffers, true), ['pre' => true]);

                    if (!$isRetry) {
                        return $this->parseRewardFlights($data, true);
                    }

                    throw new \CheckRetryNeededException(5, 0);
                }

                $isOkParseConnections = true;

                foreach ($conn as $item) {
                    if (count($item) !== count($segments)) {
                        $isOkParseConnections = false;

                        break;
                    }
                }

                if (!$isOkParseConnections) {
                    $isOld = true;
                    $conn = $this->getConnections($segments, $itOffers);
                }

                if (empty($conn)) {
                    $this->logger->debug('skip rout ' . $numRoot . ' (soldOut for cabin...)');
                    $warningSoldOut = true;

                    continue;
                }

                foreach ($conn as $numConn => $con) {
                    $connections = [];
                    $tickets = null;
                    $totalMiles = 0;
                    $award_types = [];
                    $award_type = null;
                    $classOfService = [];

                    foreach ($con as $seg) {
                        if (!isset($seg['seg'])) {
                            $this->logger->error('something went wrong');
                            $this->logger->error(var_export($conn, true), ['pre' => true]);

                            if (!$isRetry) {
                                return $this->parseRewardFlights($data, true);
                            }

                            throw new \CheckRetryNeededException(5, 0);
                        }
                        $segData = $seg['seg'];
                        $segData['aircraft'] = $seg['aircraft'];
                        $segData['fare_class'] = $seg['fare_class'];

                        if (!$isOld) {
                            $award_type = $this->getAwardType($seg['awardTitle']) ?? $award_type;
                        }

                        switch ($seg['cabinCode']) {
                            case 1:// Economy
                                $segData['cabin'] = 'economy';
                                $segData['classOfService'] = 'Economy';

                                break;

                            case 2:
                                $segData['cabin'] = 'business';
                                $segData['classOfService'] = 'Business';

                                break;

                            case 3:
                                $segData['cabin'] = 'firstClass';
                                $segData['classOfService'] = 'First';

                                break;

                            default:
                                if ($isOld && isset($cabinNames[$seg['award_type']])) {
                                    $segData['cabin'] = $cabinNames[$seg['award_type']];
                                } else {
                                    $this->sendNotification('check cabin // ZM');
                                    $segData['cabin'] = $this->inCabin;
                                }
                        }

                        if (isset($segData['classOfService'])) {
                            $classOfService[] = $segData['classOfService'];
                        }

                        if (!isset($tickets)) {
                            $tickets = $seg['tickets'];
                        } else {
                            $tickets = min($seg['tickets'], $tickets);
                        }
                        $totalMiles += $seg['total_miles'];
                        $award_types[] = $seg['award_type'];

                        if (!isset($segData['classOfService'])) {
                            $this->logger->warning('no classOfService');
                            $checkFormat = true;
                        }
                        $connections[] = $segData;
                    }
                    $award_types = array_values(array_unique($award_types));

                    if ($isOld && !isset($award_type) && count($award_types) === 1) {
                        $award_type = $award_types[0];
                    }
                    $result = [
                        'num_stops'  => count($connections) - 1 + array_sum(array_column($connections, 'num_stops')),
                        'award_type' => $award_type ?? null,
                        'times'      => [
                            'flight'  => null,
                            'layover' => null,
                        ],
                        'redemptions' => [
                            'miles'   => $totalMiles,
                            'program' => $this->AccountFields['ProviderCode'],
                        ],
                        'payments' => [
                            'currency' => 'USD',
                            'taxes'    => $trip->usdTaxValue,
                            'fees'     => null,
                        ],
                        'tickets'     => $tickets,
                        'connections' => $connections,
                    ];
                    $classOfService = array_values(array_unique($classOfService));

                    if (count($classOfService) === 1) {
                        $result['classOfService'] = $classOfService[0];
                    }
                    $routes[] = $result;
                    $this->logger->emergency('result #' . $numConn . ':');
                    $this->logger->debug(var_export($result, true), ['pre' => true]);
                }
            }

            if (isset($checkFormat)) {
                $this->sendNotification('check Format (no ClassOfService) // ZM');
            }

            if (empty($routes) && isset($warningSoldOut)) {
                $this->SetWarning('All tickets are sold out');
            }
        } else {
            $this->logger->debug('no flights. tripsList is empty');

            if ((isset($data->status) && $data->status === 'success')
                || ($data->page == false && $data->buttonCancelClose == false && $data->applyOta == false)) {
                $this->SetWarning('Not available');
            } // else because of timeouts
        }

        return $routes;
    }

    private function getAwardType($awardTitle)
    {
        $award_type = null;

        switch ($awardTitle) {
            case 'XS':
                $award_type = 'Lowest Price';

                break;

            case 'S':
                $award_type = 'Basic travel';

                break;

            case 'M':
                $award_type = 'More comfort';

                break;

            case 'L':
                $award_type = 'More flexibility';

                break;

            case 'XL':
                $award_type = 'Premium travel';

                break;

            case 'XXL':
                $award_type = 'Total comfort';

                break;

            default:
                $this->sendNotification('check award_type ' . $awardTitle . ' // ZM');
        }

        return $award_type;
    }

    private function getConnections($segments, $products): array
    {
        $matrix = [];

        foreach ($segments as $s) {
            $flight = [];

            foreach ($products as $p) {
                foreach ($p['offer']->flights as $fl) {
                    if (isset($p['offer']->totalMiles) && empty($p['offer']->totalMiles)) {
                        continue;
                    }

                    if (isset($p['offer']->regularMiles) && !empty((int) $p['offer']->regularMiles)) {
                        // club lifemiles discount
                        continue;
                    }

                    if ($s['id'] == $fl->id) {
                        if (!property_exists($fl, 'soldOut')) {
                            continue;
                        }

                        if (!$fl->soldOut) {
                            $resSegment = $s;
                            $resSegment['aircraft'] = $fl->eqp;
                            $resSegment['fare_class'] = $fl->class;
                            $resSegment['tickets'] = $fl->remainingSeats;
                            $resSegment['total_miles'] = $fl->miles;
                            $resSegment['award_type'] = $p['offer']->cabinName;
                            $resSegment['cabinCode'] = $p['offer']->cabinCode;
                            $resSegment['awardTitle'] = $p['offer']->cabinCode;
                            $flight[] = $resSegment;
                        } else {
                            $flight[] = [];
                        }
                    }
                }
            }
            $matrix[] = $flight;
        }

        return $this->getRoutes($matrix);
    }

    private function getConnectionsNew($segments, $products, &$isOld): array
    {
        $result = [];

        foreach ($products as $p) {
            if (!isset($p['offer']->title)) {
                $isOld = true;

                return $this->getConnections($segments, $products);
            }

            if (!isset($p['offer']->showBundle) || !$p['offer']->showBundle) {
                continue;
            }
            $flight = [];

            foreach ($p['offer']->flights as $fl) {
                if (isset($p['offer']->totalMiles) && empty($p['offer']->totalMiles)) {
                    continue 2;
                }

                if (isset($p['offer']->regularMiles) && !empty((int) $p['offer']->regularMiles)) {
                    // club lifemiles discount
                    continue 2;
                }

                foreach ($segments as $s) {
                    if ($s['id'] == $fl->id) {
                        if (!property_exists($fl, 'soldOut')) {
                            continue 2;
                        }

                        if (!$fl->soldOut) {
                            $resSegment = $s;
                            //$resSegment['distance'] = $fl->miles;
                            $resSegment['aircraft'] = $fl->eqp;
                            $resSegment['fare_class'] = $fl->class;
                            $resSegment['tickets'] = $fl->remainingSeats;
                            $resSegment['total_miles'] = $fl->miles;
                            $resSegment['award_type'] = $p['offer']->cabinName;
                            $resSegment['cabinCode'] = $p['offer']->cabinCode;
                            $resSegment['awardTitle'] = $p['offer']->title;
                            $flight[] = $resSegment;
                        } else {
                            break 2;
                        }
                    }
                }
            }
            $result[] = $flight;
        }

        return $result;
    }

    private function getRoutes($a, $start_route = 0): array
    {
        if (is_array($a) && $start_route != count($a)) {
            $result = [];
            $products_quantity = count($a[0]);
            $routes_quantity = count($a);

            for ($i = 0; $i < $products_quantity; $i++) {
                if ($a[$start_route][$i]) {
                    if ($start_route == $routes_quantity - 1) {
                        $result[] = [0 => $a[$start_route][$i]];
                    } else {
                        $result_r = $this->getRoutes($a, $start_route + 1);

                        if (is_array($result_r) && $result_r) {
                            foreach ($result_r as $route_part) {
                                $result_route = [$a[$start_route][$i]];
                                $result_route = array_merge($result_route, $route_part);
                                $result[] = $result_route;
                            }
                        }
                    }
                }
            }

            return $result;
        }

        return [];
    }

    private function ParseRewardAirlines($fields, $idCoti, $schHcfltrc, $searchTypePrioritize)
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $dataAirlines = $this->getPartnerAirlines($fields);
        $fields['DepCity'] = $dataAirlines['dep'];
        $fields['ArrCity'] = $dataAirlines['arr'];
        $validAirlines = $dataAirlines['airlines'];
        $this->logger->debug(var_export($validAirlines, true));

        $headers = [
            'Accept'         => 'application/json',
            'Content-Type'   => 'application/json',
            'Authorization'  => 'Bearer ' . $this->bearer,
            'realm'          => 'lifemiles',
        ];

        if (!isset($idCoti) || !isset($schHcfltrc)) {
            $this->sendNotification('check getting idCoti // ZM');

            throw new \CheckRetryNeededException(5, 0);
        }

        $routes = [];
        $warnings = [];

        $this->http->RetryCount = 0;

        $this->logger->critical('searchTypePrioritize: ' . $searchTypePrioritize);
        $res = $this->findFlight($fields, $searchTypePrioritize, $headers, $idCoti, $schHcfltrc);

        if (isset($res->page) && $res->page == false) {
            if (strpos($res->description,
                    'Sorry, we could not process your request, please try again. If the problem persists contact our') !== false) {
                $this->SetWarning('No flights. Try to choose more fare options');
            } else {
                $this->SetWarning($res->description);
            }
        }

        if (empty($res)) {
            $this->logger->critical('something went wrong. skip searchTypePrioritize');
        } elseif (empty($warnings)) {
            $routes_ = $routes;
            $routes = array_merge($this->parseRewardFlights($res), $routes_);
        }

        if (($key = array_search($searchTypePrioritize, $validAirlines)) !== false) {
            unset($validAirlines[$key]);
        }

        $warnings = array_values(array_unique(array_filter($warnings)));
        $this->logger->warning('warnings met:');
        $this->logger->warning(var_export($warnings, true));

        $allRoutes = $routes;
        $routes = array_map('unserialize', array_unique(array_map('serialize', $allRoutes)));

        return $routes;
    }

    private function findFlight($fields, $typeSearch, $headers, $idCoti, $schHcfltrc)
    {
        $this->logger->notice(__METHOD__);

        $payload = '{"internationalization":{"language":"en","country":"us","currency":"usd"},"currencies":[{"currency":"USD","decimal":2,"rateUsd":1}],"passengers":' . $fields['Adults'] . ',"od":{"orig":"' . $fields['DepCode'] . '","dest":"' . $fields['ArrCode'] . '","departingCity":"' . $fields['DepCity'] . '","arrivalCity":"' . $fields['ArrCity'] . '","depDate":"' . $fields['DepDate'] . '","depTime":""},"filter":false,"codPromo":null,"idCoti":"' . $idCoti . '","officeId":"","ftNum":"","discounts":[],"promotionCodes":["DBEP21"],"context":"D","ipAddress":"' . $this->ip . '","channel":"COM","cabin":"' . $fields['Cabin'] . '","itinerary":"OW","odNum":1,"usdTaxValue":"0","getQuickSummary":false,"ods":"","searchType":"SMR","searchTypePrioritized":"' . $typeSearch . '","sch":{"schHcfltrc":"' . $schHcfltrc . '"},"posCountry":"US","odAp":[{"org":"' . $fields['DepCode'] . '","dest":"' . $fields['ArrCode'] . '","cabin":' . $fields['Cabin'] . '}],"suscriptionPaymentStatus":""}';

        $this->logger->notice('parse flights: ' . $payload);

        if (empty($this->ip)) {
            $payload = str_replace('/"ipAddress":"\d+\.\d+\.\d+\.\d+",/', '', $payload);
        }
        $this->http->RetryCount = 0;

        $res = $this->getXHRPost('https://api.lifemiles.com/svc/air-redemption-find-flight-private', $headers,
            $payload);
        $this->http->SetBody($res);

        return $this->http->JsonLog(null, 1, false, 'tripsList');
    }

    private function getPartnerAirlines($fields)
    {
        $this->logger->notice(__METHOD__);
        $partners = \Cache::getInstance()->get('aviancataca_ra_partners');

        if (!$partners || !is_array($partners)) {
            $partners = [];
            $headers = [
                'Accept'        => 'application/json',
                'Origin'        => 'https://www.lifemiles.com/',
                'Referer'       => 'https://www.lifemiles.com/',
                'Authorization' => 'Bearer ' . $this->bearer,
                'realm'         => 'lifemiles',
            ];

            $result = $this->getXHRGet("https://api.lifemiles.com/svc/air-redemption-par-booker/en/us/usd", $headers);
            $data = $this->http->JsonLog($result, 0, true);

            foreach ($data['find']['booker']['airlines'] as $main) {
                if (strlen($main['code']) === 2) {
                    $partners[] = $main['code'];
                }
            }

            if (!empty($partners)) {
                \Cache::getInstance()->set('aviancataca_ra_partners', $partners, 60 * 60 * 24);
            } else {
                throw new \CheckException('no partners', ACCOUNT_ENGINE_ERROR);
            }
        }

        $validAirlines = [];
        $depCity = $arrCity = null;

        [$airportsOrigin, $airportsDestination] = $this->getAirports();

        if (!array_key_exists($fields['DepCode'], $airportsOrigin)
                || !array_key_exists($fields['ArrCode'], $airportsDestination)) {
            $this->logger->debug('no flights ' . $fields['DepCode'] . '->' . $fields['ArrCode']);
        }
        $validAirlines[] = "SMR";

        if (!isset($depCity, $arrCity)) {
            $depCity = $airportsOrigin[$fields['DepCode']];
            $arrCity = $airportsDestination[$fields['ArrCode']];
        }

        return ['airlines' => $validAirlines, 'dep' => $depCity, 'arr' => $arrCity];
    }

    private function runRefreshToken(string $oldToken): ?array
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "accept"          => "application/json",
            "accept-Encoding" => "gzip, deflate, br, zstd",
            "realm"           => "lifemiles",
            "Origin"          => "https://www.lifemiles.com",
        ];

        $referrer = "https://www.lifemiles.com/";
        $body = addslashes('{"authorizationCode":"' . $oldToken . '","applicationID":"lm"}');
        $result = $this->getFetch("refreshtoken", "POST", "https://oauth.lifemiles.com/authentication/token/refresh",
            $headers, $body, $referrer, false);

        return $this->http->JsonLog($result, 1, true);
    }

    private function getFetch($id, $method, $url, array $headers, $payload, $referer, $addAuth = true)
    {
        if ($addAuth) {
            $headers['Authorization'] = 'Bearer ' . $this->bearer;
        }

        if (is_array($headers)) {
            $headers = json_encode($headers);
        }

        $script = '
                fetch("' . $url . '", {
                  "headers": ' . $headers . ',
                  "referrer": "' . $referer . '",
                  "body": \'' . $payload . '\',
                  "method": "' . $method . '",
                  "credentials": "omit",
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
                });;
            ';

        $this->logger->debug("[run script]:");
        $this->logger->debug($script, ['pre' => true]);
        $this->driver->executeScript($script);
        $this->driver->executeScript($script);

        $ext = $this->waitForElement(\WebDriverBy::xpath('//script[@id="' . $id . '"]'), 10, false);
        $this->saveResponse();

        if (!$ext) {
            if ($error = $this->waitForElement(\WebDriverBy::xpath('//div[@id="' . $id . '"]'), 0, false)) {
                $this->logger->error($error->getText());
            }

            return null;
        }

        return htmlspecialchars_decode($ext->getAttribute($id));
    }

    private function callRetries()
    {
        $this->logger->notice(__METHOD__);

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->logger->debug("[attempt]: {$this->attempt}");

            if (
                $this->attempt == 2
                && ($message = $this->http->FindSingleNode('//p[contains(text(), "Lo sentimos, no pudimos realizar tu solicitud, por favor intenta nuevamente. Si el problema persiste contacta a nuestro Call Center")] | //p[contains(text(), "Sorry, we could not process your request, please try again.")]'))
            ) {
                throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
            } elseif (
                $this->attempt == 2
                && ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Sorry, the page you tried cannot be found!')]"))
            ) {
                throw new \CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            throw new \CheckRetryNeededException(3, 0);
        }
    }

    private function cancel2faSetup($button)
    {
        $this->logger->notice(__METHOD__);

        if (!$button || !str_contains($button->getAttribute('class'), 'authentication-ui-InitialPage_buttonDontShow')) {
            return false;
        }

        $button->click();
        $label = $this->waitForElement(\WebDriverBy::xpath('//label[contains(@class, "authentication-ui-MfaTerms_labelCheckbox")]'),
            3);
        $this->saveResponse();

        if (!$label) {
            $this->logger->error('label for 2fa terms not found');

            return false;
        }

        $label->click();
        $button = $this->waitForElement(\WebDriverBy::xpath('//button[contains(., "Continue")]'), 0);

        if (!$button) {
            $this->logger->error('button for 2fa cancel not found');

            return false;
        }

        $button->click();
        $button = $this->waitForElement(\WebDriverBy::xpath('//button[@class="authentication-ui-Button_button authentication-ui-Button_buttonBlue authentication-ui-VerificationMfaModal_buttonModal"]'),
            5);
        $this->saveResponse();

        if (!$button) {
            $this->logger->error('button in modal for 2fa cancel not found');

            return false;
        }

        $button->click();

        return true;
    }

    private function overlayWorkaround()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//iframe[@id="sec-cpt-if"]/@data-key');

        if ($this->waitForElement(\WebDriverBy::xpath("//div[@id = 'sec-container']"), 7)) {
            $this->saveResponse();
            $iframe = $this->waitForElement(\WebDriverBy::xpath("//iframe[@id = 'sec-cpt-if']"), 0);
            $this->driver->switchTo()->frame($iframe);
            $this->saveResponse();

            $captcha = $this->parseReCaptcha($key);

            if (!$captcha) {
                return;
            }

            $this->logger->debug("script");
            $this->driver->executeScript(/** @lang JavaScript */ "verifyAkReCaptcha('$captcha');");
            $this->driver->switchTo()->defaultContent();
            $this->saveResponse();
        }
    }
}
