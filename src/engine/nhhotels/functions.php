<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerNhhotels extends TAccountChecker
{
    use PriceTools;
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.nh-hotels.com/discovery/my-profile/';

    private const LOGOUT_XPATH = '//a[@title="My Points"] | //a[contains(text(), "Log out")]'; // | //a[@data-state="logged"]
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $BETA_LOGIN = false;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->UseSelenium();

        $this->setProxyNetNut();

        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if ($fingerprint !== null) {
            $this->logger->info("selected fingerprint {$fingerprint->getId()}, {{$fingerprint->getBrowserFamily()}}:{{$fingerprint->getBrowserVersion()}}, {{$fingerprint->getPlatform()}}, {$fingerprint->getUseragent()}");
            $this->State['Fingerprint'] = $fingerprint->getFingerprint();
            $this->State['UserAgent'] = $fingerprint->getUseragent();
            $this->State['Resolution'] = [$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()];
        }

        if (isset($this->State['Fingerprint'])) {
            $this->logger->debug("set fingerprint");
            $this->seleniumOptions->fingerprint = $this->State['Fingerprint'];
        }

        if (isset($this->State['UserAgent'])) {
            $this->logger->debug("set userAgent");
            $this->http->setUserAgent($this->State['UserAgent']);
        }

        if (isset($this->State['Resolution'])) {
            $this->logger->debug("set resolution");
            $this->seleniumOptions->setResolution($this->State['Resolution']);
        }

        $this->usePacFile(false);
        $this->http->saveScreenshots = true;

        return;
        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();

        $this->http->GetURL('https://www.nh-hotels.com/en/nhdiscovery/login');

        $btnToForm = $this->waitForElement(WebDriverBy::xpath('//span[@data-target="#m-modal-header-login"] | //a[@data-nav="user"] | //button[contains(@class, "tag-button-login")] | //button[contains(@class, "btn-login")]'), 1);

        if (!$btnToForm) {
            $this->saveResponse();

            if (
                $this->waitForElement(WebDriverBy::xpath('//a[@title="My Points"]'), 0, false)
                || $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_XPATH), 0)
            ) {
                return true; // already logged in
            }

//            return $this->checkErrors();
        }

        if ($acceptCookies = $this->waitForElement(WebDriverBy::xpath('//button[@id = "consent-prompt-submit"]'), 10)) {
            $acceptCookies->click();
            $this->saveResponse();
        }

//        $btnToForm->click();
        $this->driver->executeScript("
            try {
                document.querySelector('a[title=\"Log in\"], button.tag-button-login, button.btn-login').click()
            } catch (e) {}
        ");
//        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "email" or @id = "login-email"]'), 3);
        $login = $this->waitForElement(WebDriverBy::xpath('//div[@id = "modalLogin"]//label[@for="email"]/following-sibling::div/input'), 3);

        if (empty($login)) {
            $this->driver->executeScript("let login = document.querySelector('input[name = \"email\"]'); if (login) login.style.zIndex = '100003';");
            $this->driver->executeScript("let pass = document.querySelector('input[name = \"password\"]'); if (pass) pass.style.zIndex = '100003';");
            $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "email"]'), 3);
        }

//        $pwd = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"] | //input[@id = "login-password"]'), 0);
        $pwd = $this->waitForElement(WebDriverBy::xpath('//div[@id = "modalLogin"]//label[@for="password"]/following-sibling::div/input'), 0);
//        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@data-testid="button-login"] | //button[text() = "Log in"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//div[@id = "modalLogin"]//button[contains(., "Log in")]'), 0);

        if (!isset($login, $pwd, $btn)) {
            $this->saveResponse();

            if (
                $this->waitForElement(WebDriverBy::xpath('//a[@title="My Points"]'), 0, false)
                || $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_XPATH), 0)
            ) {
                return true; // already logged in
            }

            try {
                $btnToForm->click();
            } catch (
                StaleElementReferenceException
                | \Facebook\WebDriver\Exception\StaleElementReferenceException
                $e
            ) {
                return false;
            }

            $isAuth = $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_XPATH), 3);

            if ($isAuth) {
                return true; // already logged in
            }

            return false;
        }
        $this->logger->debug('inserting credentials');
        $this->driver->executeScript('let remMe = document.querySelector(\'#remember, #rememberme\'); if (remMe) remMe.checked = true;');

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->steps = rand(10, 30);

        $mover->moveToElement($login);
        $mover->click();
        $mover->sendKeys($login, $this->AccountFields['Login'], 5);
        $mover->moveToElement($pwd);
        $mover->click();
        $mover->sendKeys($pwd, $this->AccountFields['Pass'], 5);

//        $login->click();
//        $login->sendKeys($this->AccountFields['Login']);
//        $pwd->click();
//        $pwd->sendKeys($this->AccountFields['Pass']);

        $this->saveResponse();
        $this->captchaAuth();

        try {
            $btn->click();
            $btn->click();
        } catch (
            StaleElementReferenceException
            | \Facebook\WebDriver\Exception\StaleElementReferenceException
            $e
        ) {
            $btn = $this->waitForElement(WebDriverBy::xpath('//button[text() = "Log in"]'), 0);

            if (!$btn) {
                return false;
            }
            $btn->click();
        }

        return true;

        if (!$this->http->ParseForm("loginFormV2")) {
            return $this->checkErrors();
        }

        $captcha = $this->parseCaptcha();

        if (!$captcha) {
            return false;
        }

        $data = [
            "service"      => "http://www.nh-hotels.com",
            "username"     => $this->AccountFields['Login'],
            "password"     => $this->AccountFields['Pass'],
            "token"        => $captcha,
            "bm-telemetry" => $telemetry,
            "rememberMe"   => true,
            "tokenV2"      => "",
        ];

        $this->http->setCookie("_abck", "51F2D69FE7FED3A1817DE22F30ED1497~-1~YAAQD+/dF4pETwCIAQAAkPd5CQk9In6eC2IRWZFVLc+DAOPDqIyTH9i1YmFIjJWNkuEwkcA9CWc7LWS1e4r14KXZO3G9aPLJmdnWOVh2KAwDJTMNakTXRR1vxgQ/UKowE58waMLz6y1GnL7tgxTYnmJ98670o38ua76gwOpHkPTjFnWdN3Tee2RgjOpSXP1yegFRHS+10XPxDFizTy5f0vXbND6RdhS1H+KWr9/OBXPosIOk44VW29YE2xan9JJ66WV+A5QlbG0G7zb5O3uVB0bdbR6K8c/sP9Cx/RS3Vf1vjf3iq5L1y81UEOGxsmpFROL+m+/U1DP+hzLSSGg3ZEHQoLQaRXiFos7vTnU5fpLShnHpL/39bzUO9JZHs/tWLGgXFR0OpMOoKiEJ9PJVxDZCKi3VxYPYGkCtvVnNJhWdgh17yVVskw==~-1~-1~-1", ".nh-hotels.com"); // todo: sensor_data workaround

        $headers = [
            "Accept"        => "application/json, text/javascript, */*; q=0.01",
            "Content-type"  => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://secure-web.nh-hotels.com/sso-api/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function captchaAuth()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindPreg('/Complete the Captcha to continue/')
        ) {
            $this->logger->debug('waiting for recaptcha');
            $captcha = $this->parseCaptchaNewForm();

            if (!$captcha) {
                return false;
            }

            $this->driver->executeScript('document.getElementsByName("g-recaptcha-response")[0].value = "' . $captcha . '";');

            return true;
        }

        return false;
    }

    /*
    public function captchaAuth()
    {
        $this->logger->notice(__METHOD__);

//        $this->waitFor(function () {
//            return is_null($this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0));
//        }, 120);

        if (
            $this->http->FindSingleNode('//div[@data-widget-id="g-recaptcha-0"]/@data-widget-id')
            || $this->http->FindPreg('/Complete the Captcha to continue/')
        ) {
            $this->logger->debug('waiting for recaptcha');
            $captcha = $this->parseCaptcha();

            if (!$captcha) {
                return false;
            }

            $this->driver->executeScript("
                let e = {
                    user: '{$this->AccountFields['Login']}',
                    password: '{$this->AccountFields['Pass']}',
                    token: '{$captcha}'
                };
                login.loginSSO(e);
            ");

            return true;
        }

        return false;
    }
    */

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_XPATH . ' | //a[@data-state="logged"] | //form[@id="loginFormV2"]//div[@class="js-error-login text-color-red"] | //form[@id="loginFormV2"]//div[@class="help-block with-errors side"]/ul/li | //section[@class="m-GDPR optional"] | //p[contains(@class, "error")] | //div[@class="error"]'), 25);
        $this->saveResponse();

        if ($this->captchaAuth()) {
            sleep(15);
            $this->saveResponse();
            /*
            $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_XPATH . ' | //a[@data-state="logged"] | //form[@id="loginFormV2"]//div[@class="js-error-login text-color-red"] | //form[@id="loginFormV2"]//div[@class="help-block with-errors side"]/ul/li | //section[@class="m-GDPR optional"] | //p[contains(@class, "error")] | //div[@class="error"]'), 10);
            */
            $this->waitForElement(WebDriverBy::xpath('//button[@type="submit" and span[contains(text(), "Log in")]]'), 0)->click();

            for ($i = 0; $i < 5; $i++) {
                $this->saveResponse();

                if ($btn = $this->waitForElement(WebDriverBy::xpath('//button[@type="submit" and span[contains(text(), "Log in")]]'), 5)) {
                    $this->logger->debug('retry click');
                    $btn->click();
                } else {
                    break;
                }
            }
        }

        /*
        if ($this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 3)) {
            $this->saveResponse();
            $this->logger->debug('waiting for recaptcha');
            $captchaSuccess = $this->waitFor(function () {
                return is_null($this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0));
            }, 120);
            $btn = $this->waitForElement(WebDriverBy::xpath('//button[text() = "Log in"]'), 0);
            $this->saveResponse();

            if (!$captchaSuccess || !$btn) {
                return false;
            }
            $btn->click();
            $this->waitForElement(WebDriverBy::xpath('//a[@title="My Points"] | //a[@data-state="logged"] | //form[@id="loginFormV2"]//div[@class="js-error-login text-color-red"] | //form[@id="loginFormV2"]//div[@class="help-block with-errors side"]/ul/li | //section[@class="m-GDPR optional"]'), 25);
        }
        */

        if ($this->waitForElement(WebDriverBy::xpath(self::LOGOUT_XPATH), 15)) {
            return true;
        }

        $this->saveResponse();

        $message = $this->http->FindSingleNode('//form[@id="loginFormV2"]//div[@class="js-error-login text-color-red"]')
            ?? $this->http->FindSingleNode('//form[@id="loginFormV2"]//div[@class="help-block with-errors side"]/ul/li')
            ?? $this->http->FindSingleNode('//p[contains(@class, "error")] | //div[@class="error"]')
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (stripos($message, 'The e-mail or password entered is incorrect') !== false
                || stripos($message, 'Please review the field') !== false
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Login Error') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'Complete the Captcha to continue')) {
                /*
                throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                */
                throw new CheckRetryNeededException(2, 0, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->waitForElement(WebDriverBy::xpath('//section[@class="m-GDPR optional"]'), 0)) {
            $this->http->GetURL('https://www.nh-hotels.com/');
            $el = $this->waitForElement(WebDriverBy::xpath('//span[@class="points"]'), 15);
            $this->saveResponse();

            if ($el || $this->http->FindSingleNode('//span[@class="points"]')) {
                return true;
            }

            $this->throwAcceptTermsMessageException();
        }

        return $this->checkErrors();

        $response = $this->http->JsonLog();

        if (isset($response->token)) {
            $this->captchaReporting($this->recognizer);
            $this->BETA_LOGIN = true;

            return true;
        }

        if (isset($response->type)) {
            // The e-mail or password entered is incorrect
            if ($response->type == 'problem:login-error') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("The e-mail or password entered is incorrect", ACCOUNT_INVALID_PASSWORD);
            }

            if ($response->type == 'problem:captcha-error') {
                if (
                    $response->code == '002'
                    && $response->detail == "The e-mail or password entered is incorrect"
                ) {
                    $this->captchaReporting($this->recognizer, false);

                    throw new CheckRetryNeededException(2, 0, "The e-mail or password entered is incorrect", ACCOUNT_INVALID_PASSWORD);
                }

                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 0);
            }

            $this->DebugInfo = $response->type;

            return false;
        }

        // Access is allowed
        if ($this->loggedIn()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->saveResponse();

        if ($this->http->currentUrl() !== self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        $el = $this->waitForElement(WebDriverBy::xpath('//h1[text() = "Los servicios no responden" or text() = "Request aborted"] | //span[@class="points"]'), 10);
        $this->saveResponse();

        if ($el) {
            $text = $el->getText();

            if (stripos($text, 'Request aborted') !== false) {
                $this->http->GetURL(self::REWARDS_PAGE_URL);
                $this->waitForElement(WebDriverBy::xpath('//h1[text() = "Los servicios no responden" or text() = "Request aborted"] | //span[@class="points"]'), 10);
                $this->saveResponse();
            }

            // refs #22896
            if (stripos($text, 'Los servicios no responden') !== false) {
//                throw new CheckException($text, ACCOUNT_PROVIDER_ERROR);
                $this->http->GetURL("https://www.nh-hotels.com/servicing");
                $this->waitForElement(WebDriverBy::xpath('//span[@class="username"]'), 10);
                $this->saveResponse();
                // Name
                $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//span[@class="username"]', null, true, "/Hello\s*(.+)/")));
            }
        }
        // Status
        $this->SetProperty('Status', $this->http->FindSingleNode('//span[@class="category"]'));
        // Balance - D$
        $this->SetBalance($this->http->FindSingleNode('//span[@class="points"] | //li[@class="user-points"]/span[not(@class)]', null, true, '/([\d.,]+)/'));

        if ($linkToProfile = $this->waitForElement(WebDriverBy::linkText('PROFILE'), 0)) {
            $linkToProfile->click();
        }

        $this->waitForElement(WebDriverBy::xpath('//div[@class="info-user"]'), 2);
        $this->saveResponse();

        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[@class="info-user"]/p[1]')));
        // Card Number
        $this->SetProperty('Number', $this->http->FindSingleNode('//div[@class="info-user"]/p[2]/b'));
        // Member since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//div[@class="info-user"]/p[3]', null, true, '#(\d{2}/\d{2}/\d{4})#'));
        // Your category expires
        $this->SetProperty('StatusExpiration', $this->http->FindSingleNode('//div[@class="info-user"]/p[4]', null, true, '#Your category expires (\d{1,2} \w+ \d{4})#'));

        $this->http->GetURL('https://www.nh-hotels.com/en/discovery/my-points/');
        $this->waitForElement(WebDriverBy::xpath('//p[@id="bonusAmount"]'), 10);
        $this->saveResponse();

        // Expiring balance
        $this->SetProperty('PointsToExpire', $this->http->FindSingleNode('//div[@class="expiration"]/p[contains(text(), "expire")]', null, true, '/(.*) expire/ims'));
        // Expiration date
        $exp = $this->http->FindSingleNode('//div[@class="expiration"]/p[contains(text(), "expire")]', null, true, '/expire on (.*)/ims');
        if ($exp) {
            $this->SetExpirationDate(strtotime($this->ModifyDateFormat($exp)));
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->http->GetURL("https://www.nh-hotels.com/en");
            $menu = $this->waitForElement(WebDriverBy::xpath('//a[@data-testid="button-login"] | //a[@title="Log in"]'), 5);
            $this->saveResponse();

            if ($menu) {
                $menu->click();
                $this->waitForElement(WebDriverBy::xpath('//span[@class="points"] | //li[@class="user-points"]/span[not(@class)]'), 10);
                $this->saveResponse();
                // Balance - D$
                $this->SetBalance($this->http->FindSingleNode('//span[@class="points"] | //li[@class="user-points"]/span[not(@class)]', null, true, '/([\d.,]+)/'));
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.nh-hotels.com/servicing";
    }

    // default
    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Reservation number",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Size"     => 50,
                "Required" => false,
                "Value"    => $this->GetUserField('LastName'),
            ],
        ];
    }

    // default
    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if ($this->http->XPath->query("//div[contains(@class, 'info_timbre_securityerror')]")->length > 0) {
            return "Itinerary not found";
        }
        $it = [$this->ParseItineraryCompany()];

        return null;
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->BETA_LOGIN) {
            return [];
        }
        $this->http->setCookie('TOKENSSO', $this->http->getCookieByName('TOKENSSO'), 'www.nh-hotels.com');
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => '',
        ];
        $this->http->GetURL('https://www.nh-hotels.com/loyalty-api/getMyBookings/41?offSet=1&rowCount=3', $headers);
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg('/"bookings":\s*\[\]/')) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        foreach ($response->bookings as $item) {
            $this->http->GetURL("https://www.nh-hotels.com/booking-management/41/reservation?signature={$item->signature}", $headers);
            $response = $this->http->JsonLog();
            $this->parseItinerary($response);
        }

        return [];
    }

    protected function parseCaptchaV2()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/recatpchaV2Public:\s*\"([^\"]+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, false);
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);

        $key = $this->http->FindPreg("/recatpchaV3Public:\s*\"([^\"]+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $postData = [
            "type"       => "RecaptchaV3TaskProxyless",
            "websiteURL" => $this->http->currentUrl(),
            "websiteKey" => $key,
            "minScore"   => 0.3,
            "pageAction" => "login",
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
            "version"   => "v3",
            "action"    => "login", // https://assets.nh-hotels.net/system/nhGroup/v3/html/js/async.js?v=10.75
            "min_score" => 0.9,
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    protected function parseCaptchaNewForm()
    {
        $this->logger->notice(__METHOD__);

        $key = $this->http->FindSingleNode('//script[contains(@src, "render")]/@src', null, true, '/render=(.*)/');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        /*
        $postData = [
            "type"       => "RecaptchaV3TaskProxyless",
            "websiteURL" => $this->http->currentUrl(),
            "websiteKey" => $key,
            "minScore"   => 0.3,
            "pageAction" => "login",
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        */

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            "action"    => "login", // https://assets.nh-hotels.net/system/nhGroup/v3/html/js/async.js?v=10.75
            "min_score" => 0.9,
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function parseItinerary($data)
    {
        $this->logger->notice(__METHOD__);

        $h = $this->itinerariesMaster->createHotel();
        $h->general()->confirmation($data->idReservation);

        foreach ($data->roomList as $room) {
            $r = $h->addRoom();
            $r->setType($room->roomName, true, true);
            $r->setDescription($room->descriptionRoom, true, true);
            $r->setRate($room->roomPrice->amountBeforeTax);

            if ($room->numpax3 > 0) {
                $h->booked()->kids($room->numpax3);
            }
            $h->booked()->guests($room->numpax1);

            foreach ($room->guestList as $guest) {
                if (isset($guest->name)) {
                    $guest->name = trim($guest->name);
                    $guest->surname1 = trim($guest->surname1);

                    if (!empty($guest->name) || !empty($guest->surname1)) {
                        $h->general()->traveller("$guest->name $guest->surname1");
                    }
                }
            }
        }

        $h->price()->total($data->totalPrice->amountAfterTax);
        $h->price()->tax($data->totalPrice->tax);

        $h->price()->currency($data->totalPrice->currencyCode);
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => '',
        ];
        $this->http->GetURL("https://www.nh-hotels.com/content-api/41/hotels/ESGC.IMPER", $headers);
        $hotel = $this->http->JsonLog(null, 2);

        $h->hotel()->name($hotel->nameHotel);
        $h->hotel()->address("{$hotel->address->address} {$hotel->address->city} {$hotel->address->country} {$hotel->address->cp}");
        $data->startDate = $this->ModifyDateFormat($data->startDate);
        $data->endDate = $this->ModifyDateFormat($data->endDate);

        $h->booked()->checkIn2("$data->startDate, {$hotel->checkIn->hour}:{$hotel->checkIn->minutes}");
        $h->booked()->checkOut2("$data->endDate, {$hotel->checkOut->hour}:{$hotel->checkOut->minutes}");

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry, our site is currently undergoing scheduled maintenance")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function loggedIn()
    {
        return count($this->http->FindNodes("//*[contains(text(), 'Log out')] | //a[contains(@href, '/logout.')]")) > 0;
    }

    private function parseProfilePage()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.nh-hotels.com/rewards/profile");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//input[@id = "nombre"]/@value') . " " . $this->http->FindSingleNode('//input[@id = "apellidos"]/@value')));
        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//p[contains(text(), 'Member since:')]/span"));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode('//span[contains(@class, "card-custom")]'));
        // NH World member No.
        $this->SetProperty("Number", $this->http->FindSingleNode("//p[contains(text(), 'Card Number')]", null, true, "/Number\s*\:?\s*([^<]+)/ims"));
    }

    private function parseMainPage()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("http://www.nh-hotels.com/nh/?language=en&privateMode=true");
        // Balance - points
        $this->SetBalance($this->http->FindSingleNode("//span[@id = 'nhUser-points']"));

        $this->parseProfilePage();
    }

    // default
    private function ParseItineraryCompany()
    {
        $this->logger->notice(__METHOD__);
        $result = ["Kind" => "R"];
        $result["ConfirmationNumber"] = $this->http->FindSingleNode("//strong[contains(text(), 'Booking number:')]/following-sibling::span[1]");
        $result["HotelName"] = $this->http->FindSingleNode("//h2[contains(@class, 'titular02')]/a");
        $result["Address"] = $this->http->FindSingleNode("//span[@itemprop='streetAddress']");
        $result["Phone"] = $this->http->FindSingleNode("//span[@itemprop='telephone']");
        $result["Fax"] = $this->http->FindSingleNode("//span[@itemprop='faxNumber']");
        $dates = $this->http->FindSingleNode("//div[@class='confirmacion']/p[1]/strong");

        if ($dates && preg_match("/From: ([\d\/]+) to: ([\d\/]+)/", $dates, $m)) {
            $result["CheckInDate"] = strtotime($this->ModifyDateFormat($m[1]));
            $result["CheckOutDate"] = strtotime($this->ModifyDateFormat($m[2]));
        }
        $rooms = $this->http->XPath->query("//div[@class='confirmacion']//p[strong[contains(text(), 'Room')]]");
        $roomCount = 0;

        foreach ($rooms as $node) {
            if ($this->http->FindSingleNode("strong", $node, true, "/Room \d+/ims")) {
                $roomCount++;
                $result["RoomType"] = $this->http->FindSingleNode("text()[last()]", $node);
            }

            if (!isset($result["Guests"]) && ($adult = $this->http->FindSingleNode("strong", $node, true, "/\((\d+) adult/ims"))) {
                $result["Guests"] = $adult;
            }
        }

        if ($roomCount > 0) {
            $result["Rooms"] = $roomCount;
        }
        $result["GuestNames"] = $this->http->FindSingleNode("//div[h3[contains(text(), 'Reservation holder')]]/p[1]/strong[1]");
        $result["CancellationPolicy"] = $this->http->FindSingleNode("//p[strong[contains(text(), 'Reservation Guarantee Policy')]]/following-sibling::p[1]");
        $result["RateType"] = $this->http->FindSingleNode("//p[strong[text()='Type']]/text()[last()]");
        $result["Cost"] = $this->http->FindSingleNode("//p[contains(text(), 'Rate')]/following-sibling::p[1]", null, true, "/^([\d\.]+)/");
        $result["Taxes"] = $this->http->FindSingleNode("//p[contains(text(), 'Rate')]/following-sibling::p[3]", null, true, "/^([\d\.]+)/");
        $result["Total"] = $this->http->FindSingleNode("//strong[@class='price02']", null, true, "/^([\d\.]+)/");
        $result["Currency"] = $this->http->FindSingleNode("//strong[@class='price02']", null, true, "/[A-Z]+$/");

        return $result;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = null;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useFirefoxPlaywright();
            $selenium->setProxyBrightData();
            /*
            $selenium->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
            */
//            $selenium->http->SetProxy($this->proxyDOP(), false);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            /*
            $selenium->useGoogleChrome();

            $selenium->setProxyBrightData();

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
                $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
            }
            */

            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL('https://www.nh-hotels.com/');
            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'identifier']"), 5);

            // save page to logs
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            $get_telemetry = $selenium->driver->executeScript("return bmak ? bmak.get_telemetry() : \"\";");
            $this->logger->info("[Form get_telemetry]: '" . $get_telemetry . "'");

            foreach ($cookies as $cookie) {
//                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } catch (Facebook\WebDriver\Exception\UnknownErrorException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $get_telemetry;
    }
}
