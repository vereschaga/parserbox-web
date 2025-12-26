<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerSixt extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public $regionOptions = [
        ""        => "Select your region",
        "Germany" => "Germany",
        "USA"     => "United States",
    ];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $domain = 'com';

    private $profileData = null;
    private $bookingData = null;
    private $pastBookingData = null;

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields);
        $fields['Login2']['Options'] = $this->regionOptions;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));

        $this->http->setDefaultHeader('Accept', 'application/json, text/plain, */*');
        $this->http->setDefaultHeader('Content-Type', 'application/json');
        $this->http->setDefaultHeader('sx-browser-id', '137996daf7858512ddf95be68dfbc23d7e780e7d98fa29b335915063818a8453');
        $this->http->setDefaultHeader('sx-platform', 'web-next');
        $this->http->setDefaultHeader('x-client-id', 'web-browser-2501186645373610004896605373651080192024');
        $this->http->setDefaultHeader('x-client-type', 'web');
        $this->http->setDefaultHeader('x-correlation-id', 'faaaf3e5-7ec3-48ff-a1e6-b33b4307e68c');
        $this->http->setDefaultHeader('x-sx-o-client-id', '1918e66b-1c26-491e-a497-e4a0ab7a032a:oeu1651662338734r0.6552278096943411');

        if (!isset($this->AccountFields['Login2'])) {
            return;
        }

        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->debug('Region => ' . $this->AccountFields['Login2']);

        if ($this->AccountFields['Login2'] == 'Germany') {
            $this->domain = 'de';
        }
    }

    public function IsLoggedIn()
    {
        if (isset($this->State['accessToken'])) {
            $this->http->setDefaultHeader('authorization', $this->State['accessToken']);
            $this->http->RetryCount = 0;
            $this->http->GetURL('https://web-api.orange.sixt.com/v2/users');
            $this->http->RetryCount = 2;
            $data = $this->http->JsonLog();

            if (isset($data->firstName)) {
                return true;
            }
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            // Bitte geben Sie eine gültige E-Mail-Adresse ein
            throw new CheckException('Please enter a valid email address', ACCOUNT_INVALID_PASSWORD);
        }
        $this->http->removeCookies();
        $this->http->GetURL("https://www.sixt.{$this->domain}/");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $this->getCookiesFromSelenium();

        return true;

        // {"username":"hutandion@hotmail.com","password":"@Dion2031"}
        $data = json_encode([
            'username'      => $this->AccountFields['Login'],
            'password'      => $this->AccountFields['Pass'],
        ]);

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://web-api.orange.sixt.com/v1/auth/login', $data);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->Response['code'] == 503 && $this->http->FindPreg('#<div id="app"></div>#')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // The website is currently not available due to maintenance downtime
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'is currently not available')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Due to maintenance, the page is currently not available.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Due to maintenance, the page is currently not available.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Proxy Error
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Proxy Error')]")
            // 504 Gateway Time-out
            || $this->http->FindSingleNode("//h1[contains(text(), '504 Gateway Time-out')]")
            // 502 Bad Gateway
            || $this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# An error occurred while processing your request
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * The server is temporarily unable to service your request due to maintenance downtime or capacity problems.
         * Please try again later
         */
        if ($message = $this->http->FindPreg("/(The server is temporarily unable to service your\s*request due to maintenance downtime or capacity\s*problems\. Please try again later)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Website is currently being updated
        if ($message = $this->http->FindSingleNode('//*[contains(text(), "website is currently being updated")]', null, false)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Leider ist ein temporäres Problem aufgetreten.
        if ($this->http->FindPreg("/(Leider ist ein temporäres Problem aufgetreten\.)/ims")) {
            throw new CheckException("We are experiencing a temporary problem. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/

        // authorization not working on some accounts
        if ($this->http->FindPreg("/^https:\/\/www\.sixt\.{$this->domain}\/php\/reservation\/login\.login\?_=\d+$/", false, $this->http->currentUrl())
            && $this->http->Response['code'] == 500) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $auth = $this->http->JsonLog();
        $auth = $auth->auth ?? $auth;

        if (isset($auth->accessToken)) {
            $this->State['accessToken'] = 'Bearer ' . $auth->accessToken;
            $this->http->setDefaultHeader('authorization', 'Bearer ' . $auth->accessToken);

            return true;
        }

        $message =
            $auth->message
            ?? $this->http->FindSingleNode('//div[contains(text(), "Incorrect password. Please try again.")] | //div[contains(text(), "fen Sie Ihr Passwort und versuchen es erneut")]')
            ?? null
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");
            // Your password may have expired or been entered incorrectly.. Please use the link 'Forgotten password'.
            if (
                $message == 'Please check your email address or try another password. If you don\'t remember it, you can request the reset of your password.'
                || $message == 'Bitte überprüfen Sie die Emailadresse oder versuchen Sie ein anderes Passwort. Falls Sie das Passwort vergessen haben, können Sie es zurücksetzen.'
                || $message == 'Incorrect password. Please try again.'
                || $message == 'Überprüfen Sie Ihr Passwort und versuchen es erneut'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode("//p[
                contains(text(), '{$this->AccountFields['Login']} ist noch nicht bei SIXT registriert.')
                or contains(text(), '{$this->AccountFields['Login']} is not yet registered with SIXT.')
                or contains(text(), '{$this->AccountFields['Login']} looks new to us.')
            ]
            | //*[self::span or self::div][
                    (contains(text(), 'Nice to meet you! Let') and contains(text(), 's get to know each other better.'))
                    or contains(text(), 'Willkommen an Bord')
                ]
        ")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindSingleNode('//div[contains(text(), "Login with Siemens Smartcard (PKI)")]')
            || $this->http->FindPreg("/>Azure AD Multi-Factor Authentication Wiki \(Siemens internal\)\.<\.?\/a>/")
        ) {
            throw new CheckException("You are being redirected to the Siemens website that does not contain your Sixt account information.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->AccountFields['Login'] == 'falk.ebert@siemens.com') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $data = $this->http->JsonLog(null, 0);

        if (!isset($data->firstName, $data->personLoyaltyStatus)) {
            $this->http->RetryCount = 0;
            /*
            $this->http->GetURL('https://web-api.orange.sixt.com/v2/users');

            // it helps
            if ($this->http->Response['code'] == 502) {
                sleep(5);
                $this->http->GetURL('https://web-api.orange.sixt.com/v2/users');
            }
            */

            $data = $this->http->JsonLog($this->profileData);
            $this->http->RetryCount = 2;
        }

        $this->SetProperty("Name", beautifulName($data->firstName . ' ' . $data->lastName));

        foreach ($data->profiles as $profile) {
            if (isset($profile->isPreselected) && $profile->isPreselected === true) {
                $this->SetProperty("CardNumber", $profile->id);
                $this->SetProperty('Level', beautifulName($profile->loyaltyStatus));

                break;
            }
        }

        if ((isset($this->Properties['Level']) && !empty($this->Properties['Name'])
            && isset($this->Properties['CardNumber']))
        ) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $this->http->RetryCount = 0;
        /*
        $this->http->GetURL('https://web-api.orange.sixt.com/v1/customer-support/bookings/rent?bookingState=upcoming');

        if ($this->http->Response['code'] == 502) {
            sleep(5);
            $this->sendNotification("retry, 502 issue // RR");
            $this->http->GetURL('https://web-api.orange.sixt.com/v1/customer-support/bookings/rent?bookingState=upcoming');
        }
        */
        $this->http->SetBody($this->bookingData);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg('/"list":\[\]/')) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        $list = $response->list ?? [];

        foreach ($list as $item) {
            $this->parseItinerary($item);
        }

        if ($this->ParsePastIts) {
            $this->http->RetryCount = 0;
            /*
            $this->http->GetURL('https://web-api.orange.sixt.com/v1/customer-support/bookings/rent?bookingState=history&duration=one_year');
            */
            $this->http->SetBody($this->pastBookingData);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if ($this->http->FindPreg('/"list":\[\]/')) {
                return [];
            }

            $list = $response->list ?? [];

            foreach ($list as $item) {
                $this->parseItinerary($item);
            }
        }

        return [];
    }

    public function parseItineraryRetrieve()
    {
        $this->logger->notice(__METHOD__);
        $car = $this->itinerariesMaster->createRental();
        // Number
        $car->general()->confirmation($this->http->FindSingleNode("//h2[contains(text(), 'Reservierung') or contains(text(), 'Reservation')]", null, true, '/:\s*([\d]+)/ims'));
        // Data from left part
        $pickup = $this->http->FindNodes('//div[@class="sx-res-info" and div[contains(text(), "Pickup Location") or contains(text(), "Abholung")]]/div[@class="sx-res-info-text"]/p/text()[normalize-space(.) != ""]');
        $this->logger->debug(var_export($pickup, true));

        if (count($pickup) === 2 && preg_match('#^\d{1,2}[/.]\d{1,2}[/.]\d{4}#', $pickup[1])) {
            $car->pickup()->location($pickup[0]);
            $car->pickup()->date(strtotime(preg_replace('#[^/.\d:\samp]+#', '', $pickup[1])));
        }
        $dropoff = $this->http->FindNodes('//div[@class="sx-res-info" and div[contains(text(), "Return Location") or contains(text(), "ckgabe")]]/div[@class="sx-res-info-text"]/p/text()[normalize-space(.) != ""]');
        $this->logger->debug(var_export($dropoff, true));

        if (count($pickup) === 2 && preg_match('#^\d{1,2}[/.]\d{1,2}[/.]\d{4}#', $dropoff[1])) {
            $car->dropoff()->location($dropoff[0]);
            $car->dropoff()->date(strtotime(preg_replace('#[^/.\d:\samp]+#', '', $dropoff[1])));
        }

        if (empty($car->getPickUpLocation())) {
            // PickupLocation
            $loc = $this->http->FindNodes("//div[h4[contains(text(), 'Pickup Location') or contains(text(), 'Abholung')]]/p[1]/text()");
            $this->logger->debug("Location length: " . count($loc));
            $location = '';

            for ($i = 0; $i < 3; $i++) {
                $location .= isset($loc[$i]) ? ' ' . $loc[$i] : '';
            }
            $car->pickup()->location(Html::cleanXMLValue($location));
            // PickupDatetime
            $date = implode(' ', $this->http->FindNodes("//div[h4[contains(text(), 'Pickup Location') or contains(text(), 'Abholung')]]/p[2]/text()"));
            $date = Html::cleanXMLValue(preg_replace("/[^\/.\d:\s]+/", '', $date));
            $this->logger->debug("PickupDatetime -> {$date}");
            $car->pickup()->date(strtotime($date));
        }

        if (empty($car->getDropOffLocation())) {
            // DropoffLocation
            $loc = $this->http->FindNodes("//div[h4[contains(text(), 'Return Location') or (contains(text(), 'R') and contains(text(), 'ckgabe'))]]/p[1]/text()");

            if (empty($loc)) {
                //div[contains(text(), 'Return Location') or (contains(text(), 'R') and contains(text(), 'ckgabe'))]/following-sibling::div/p[1]/text()
                $this->logger->debug("Location length: " . count($loc));
            }
            $location = '';

            for ($i = 0; $i < 3; $i++) {
                $location .= isset($loc[$i]) ? ' ' . $loc[$i] : '';
            }
            $car->dropoff()->location(Html::cleanXMLValue($location));
            // DropoffDatetime
            $date = implode(' ', $this->http->FindNodes("//div[h4[contains(text(), 'Return Location') or contains(text(), 'R') and contains(text(), 'ckgabe')]]/p[2]/text()"));
            $date = Html::cleanXMLValue(preg_replace("/[^\/.\d:]+\s/", '', $date));
            $this->logger->debug("DropoffDatetime -> {$date}");
            $car->dropoff()->date(strtotime($date));
        }
        $car->car()->model($this->http->FindSingleNode('//div[@class="sx-res-offerexample-header"]/h2'));

        if ($url = $this->http->FindSingleNode('//div[@class="sx-res-offerexample-img"]/img/@src')) {
            $this->http->NormalizeURL($url);
            $car->car()->image($url);
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($car->toArray(), true), ['pre' => true]);
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"         => "Reservation number",
                "Type"            => "string",
                "Size"            => 20,
                "InputAttributes" => "style=\"width: 300px;\"",
                "Required"        => true,
            ],
            "SecurityCode"      => [
                "Caption"         => "Security code",
                "Type"            => "string",
                "Size"            => 20,
                "InputAttributes" => "style=\"width: 300px;\"",
                "Required"        => true,
            ],
            "Region"        => [
                "Options"         => $this->regionOptions,
                "Type"            => "string",
                "Size"            => 10,
                "InputAttributes" => "style=\"width: 300px;\"",
                "Required"        => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        switch ($arFields['Region'] ?? '') {
            case "Germany":
                return 'https://www.sixt.de/php/resexpress/form';

            default:
                return "https://www.sixt.com/php/resexpress/?language=en_US";
        }
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->removeCookies();
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm("sx-oci-form")) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        if ($arFields["Region"] == 'Germany') {
            $this->domain = 'de';
        }

        $credentials = [
            "ReservationNumber"     => $arFields["ConfNo"],
            "SecurityCode"          => $arFields["SecurityCode"],
            "Domain"                => $this->domain,
        ];

        $this->http->Form = [];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->FormURL = "https://www.sixt.{$credentials["Domain"]}/php/resexpress/checklogin?_=" . date('UB');
        $this->http->SetInputValue('num', $credentials["ReservationNumber"]);
        $this->http->SetInputValue('qual', $credentials["SecurityCode"]);

        if (!$this->http->PostForm($headers)) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        $this->http->JsonLog();

        if ($err = $this->http->FindPreg('/\{"err":\[\{"txt":"(There is no booking with the number.+?|Zur eingebenen Nummer konnte keine Reservierung gefunden werden.+?|Your booking has already been processed; no further amendments are possible.+?|The security code or last name entered does not match the reservation data. Please check your entry.|Ihre Reservierung wurde bereits verarbeitet; es sind keine.+?|Please enter your reservation number)"\}\],/u')) {
            $err = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
                return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
            }, $err);

            return $err;
        }

        $this->http->GetURL("https://www.sixt.{$credentials["Domain"]}/php/resexpress/form");
        $this->parseItineraryRetrieve();

        return null;
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions)) || empty($region)) {
            $region = 'USA';
        }

        return $region;
    }

    private function parseItinerary($data)
    {
        $this->logger->info("Parse itinerary #{$data->reservationNumber}", ['Header' => 3]);
        $r = $this->itinerariesMaster->add()->rental();
        $r->general()->confirmation($data->reservationNumber);
        $r->price()->total($data->price->value);
        $r->price()->currency($data->price->currency);

        // 2022-07-09T12:30:00.000+02:00
        $r->pickup()->date2($this->http->FindPreg('/(\d+-\d+-\d+T\d+:\d+)/', false, $data->trip->pickupDate->date));
        $r->dropoff()->date2($this->http->FindPreg('/(\d+-\d+-\d+T\d+:\d+)/', false, $data->trip->returnDate->date));

        $r->pickup()->location($data->trip->pickupLocation->name);
        $d = $r->pickup()->detailed();
        $d->address($data->trip->pickupLocation->address->street)
            ->city($data->trip->pickupLocation->address->city)
            ->country($data->trip->pickupLocation->address->countryName);

        if (!empty($data->trip->pickupLocation->address->postCode)) {
            $d->zip($data->trip->pickupLocation->address->postCode);
        }

        $r->dropoff()->location($data->trip->returnLocation->name);
        $d = $r->dropoff()->detailed();
        $d->address($data->trip->returnLocation->address->street)
            ->city($data->trip->returnLocation->address->city)
            ->country($data->trip->returnLocation->address->countryName);

        if (!empty($data->trip->returnLocation->address->postCode)) {
            $d->zip($data->trip->returnLocation->address->postCode);
        }

        $r->car()->type($data->subtitle, true, false);
        $r->car()->model($data->title, true, false);
        $r->car()->image($data->vehicle->image->thumbnail);

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);
        $key = rand(0, 3);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox();
            $selenium->disableImages();
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();

            try {
                $selenium->http->GetURL("https://www.sixt.{$this->domain}/account/#/bookings");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeoutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage());
                sleep(2);
                $this->savePageToLogs($selenium);
            }

            $contEmail = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "zenauth_root" or @id = "login" or @id = "customersettings_root"]//span[contains(text(), " | ") and not(contains(@class, "text"))] | //div[contains(text(), "Log in") or contains(text(), "Anmelden")] | //button[@data-testid="uc-ccpa-button"]'), 10);
            $this->savePageToLogs($selenium);

            try {
                if ($contEmail && $contEmail->getText() == 'OK') {
                    $contEmail->click();

                    $contEmail = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "zenauth_root" or @id = "login" or @id = "customersettings_root"]//span[contains(text(), " | ") and not(contains(@class, "text"))] | //div[contains(text(), "Log in") or contains(text(), "Anmelden")]'), 10);
                    $this->savePageToLogs($selenium);
                }
            } catch (Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $contEmail = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "zenauth_root" or @id = "login" or @id = "customersettings_root"]//span[contains(text(), " | ") and not(contains(@class, "text"))] | //div[contains(text(), "Log in") or contains(text(), "Anmelden")] | //button[@data-testid="uc-ccpa-button"]'), 0);
            }

            if ($contEmail) {
                $selenium->driver->executeScript('
                    let loginBtn = document.querySelector("span.LoginButton__label");
    
                    if (!loginBtn)
                        loginBtn = document.querySelectorAll("#zenauth_root span")[1];
    
                    if (!loginBtn)
                        loginBtn = document.evaluate(\'//div[contains(text(), "Log in") or contains(text(), "Anmelden")] | //span[contains(text(), "Log in") or contains(text(), "Anmelden")]\', document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
    
                    loginBtn.click();
                ');
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "username" or @id = "email"]'), 15);
            $this->savePageToLogs($selenium);

            if (!$loginInput) {
                if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Something went wrong.')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }

            try {
                $loginInput->sendKeys($this->AccountFields['Login']);
            } catch (Facebook\WebDriver\Exception\ElementNotInteractableException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

                $this->savePageToLogs($selenium);
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "username" or @id = "email"]'), 0);
                $loginInput->sendKeys($this->AccountFields['Login']);
            }

            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[(normalize-space() = "Continue" or normalize-space() = "Weiter") or contains(., "Next") and not(@disabled)]'), 3);
            $this->savePageToLogs($selenium);

            if (!$btn) {
                return false;
            }

            $this->acceptCookies($selenium);

            $btn->click();

            $contWithPass = $selenium->waitForElement(WebDriverBy::xpath('//*[self::p or self::div][contains(text(), "with password") or contains(text(), "Mit Passwort")] | //span[contains(text(), "Passwort verwenden") or contains(text(), "with password")]'), 10);
            $this->savePageToLogs($selenium);

            $this->acceptCookies($selenium);

            if ($contWithPass) {
                $contWithPass->click();
            }

            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 5);
            $this->savePageToLogs($selenium);

            if (!$passwordInput) {
                return false;
            }

            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[normalize-space() = "Log in" or normalize-space() = "Sign in" or normalize-space(.) = "Einloggen" and not(@disabled)]'), 3);

            if (!$btn) {
                $this->savePageToLogs($selenium);

                return false;
            }

//            $selenium->driver->executeScript('
//                let oldXHROpen = window.XMLHttpRequest.prototype.open;
//                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
//                    this.addEventListener("load", function() {
//                        if (/auth\/login/g.exec(url)) {
//                            localStorage.setItem("responseData", this.responseText);
//                        }
//                    });
//                    return oldXHROpen.apply(this, arguments);
//                };
//            ');
            $selenium->driver->executeScript('
                const constantMock = window.fetch;
                window.fetch = function() {
                    console.log(arguments);
                    return new Promise((resolve, reject) => {
                        constantMock.apply(this, arguments)
                        .then((response) => {
                            if (response.url.indexOf("auth/login") > -1) {
                                response
                                .clone()
                                .json()
                                .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
                        }
                            resolve(response);
                        })
                    .catch((error) => {
                            reject(response);
                        })
                    });
                }
            ');

            $btn->click();

            $res = $selenium->waitForElement(WebDriverBy::xpath('
                //span[@class = "ProfileButton__profileName"]
                | //div[contains(text(), "Incorrect password. Please try again.")]
                | //div[contains(text(), "fen Sie Ihr Passwort und versuchen es erneut")]
            '), 10);
            $this->savePageToLogs($selenium);

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);

            if (empty($responseData)) {
                $responseData = $selenium->driver->executeScript("return localStorage.getItem('user');");
                $this->logger->info("[Form responseData]: " . $responseData);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            if (!empty($responseData) && (!$res || !stristr($res->getText(), 'Incorrect password. Please try again'))) {
                $this->http->SetBody($responseData);

                $selenium->http->GetURL("https://web-api.orange.sixt.com/v2/users");
                $auth = $this->http->JsonLog();
                $auth = $auth->auth ?? $auth;

                if (isset($auth->accessToken)) {
                    $this->getProfileData($selenium, $auth->accessToken);
                    $this->logger->debug("get profile data");
                    $this->profileData = $selenium->driver->executeScript("return localStorage.getItem('profileData');");
                    $this->logger->info("[Form profileData]: " . $this->profileData);

                    $this->getBookingData($selenium, $auth->accessToken);
                    $this->logger->debug("get booking data");
                    $this->bookingData = $selenium->driver->executeScript("return localStorage.getItem('bookingData');");
                    $this->logger->info("[Form booking]: " . $this->bookingData);

                    $this->getPastBookingData($selenium, $auth->accessToken);
                    $this->logger->debug("get past booking data");
                    $this->pastBookingData = $selenium->driver->executeScript("return localStorage.getItem('pastBookingData');");
                    $this->logger->info("[Form past booking data]: " . $this->pastBookingData);
                }
            }
        } catch (
            UnknownServerException
            | SessionNotCreatedException
            | WebDriverCurlException
            | TimeOutException
            | NoSuchWindowException
            | Facebook\WebDriver\Exception\UnrecognizedExceptionException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | Facebook\WebDriver\Exception\InvalidSessionIdException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3);
            }
        }

        return $key;
    }

    private function getProfileData($selenium, $token)
    {
        $this->logger->notice(__METHOD__);
        $selenium->driver->executeScript('
            fetch("https://web-api.orange.sixt.com/v2/users", {
                "credentials": "include",
                "headers": {
                    "Accept": "*/*",
                    "authorization": "Bearer ' . $token . '",
                },
                "method": "GET",
                "mode": "cors"
            }).then((response) => {
                    response
                    .clone()
                    .json()
                    .then(body => localStorage.setItem("profileData", JSON.stringify(body)));
            })
        ');
        $this->logger->debug("request sent");
        sleep(2);
    }

    private function getBookingData($selenium, $token)
    {
        $this->logger->notice(__METHOD__);
        $selenium->driver->executeScript('
            fetch("https://web-api.orange.sixt.com/v1/customer-support/bookings/v2/rent?bookingState=upcoming", {
                "credentials": "include",
                "headers": {
                    "Accept": "*/*",
                    "authorization": "Bearer ' . $token . '",
                },
                "method": "GET",
                "mode": "cors"
            }).then((response) => {
                    response
                    .clone()
                    .json()
                    .then(body => localStorage.setItem("bookingData", JSON.stringify(body)));
            })
        ');
        $this->logger->debug("request sent");
        sleep(2);
    }

    private function getPastBookingData($selenium, $token)
    {
        $this->logger->notice(__METHOD__);
        $selenium->driver->executeScript('
            fetch("https://web-api.orange.sixt.com/v1/customer-support/bookings/v2/rent?bookingState=upcoming", {
                "credentials": "include",
                "headers": {
                    "Accept": "*/*",
                    "authorization": "Bearer ' . $token . '",
                },
                "method": "GET",
                "mode": "cors"
            }).then((response) => {
                    response
                    .clone()
                    .json()
                    .then(body => localStorage.setItem("pastBookingData", JSON.stringify(body)));
            })
        ');
        $this->logger->debug("request sent");
        sleep(2);
    }

    private function acceptCookies($selenium)
    {
        $this->logger->notice(__METHOD__);
        $selenium->driver->executeScript("var popup = document.querySelector('#usercentrics-root').style = 'display: none;'; if (popup) popup.style = \"display: none;\";");
    }
}
