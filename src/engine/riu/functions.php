<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerRiu extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://www.riu.com/riuclass-utils!puntosUsuario.action?v=web&contexto=RC&formato=json&idioma=en&request.aspect=desk";

    private $curlDrive;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
        $this->usePacFile(false);
//        if ($this->attempt == 0) {
//            $this->useGoogleChrome();
//        } else {
//            $this->useFirefox();
//            $this->setKeepProfile(true);
//        }
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
        $this->http->GetURL("https://www.riu.com/en/riu-class/my-riuclass/mis-puntos/index.jsp");
        sleep(13); // wait while the provider scripts load

        $this->acceptCookies();

        $rcLink = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class,"riu-ui-main-header__login-button")] 
        | //a[contains(text(), "Log in")] 
        | //button[@name="buttonName" and contains(., "Log in")]'), 10);

        if ($rcLink) {
            try {
                $this->acceptCookies();
                $rcLink->click();
            } catch (Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
                $this->logger->error("ElementClickInterceptedException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $this->saveResponse();
            }
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "login_input_input"]'), 10);

        if (!$loginInput) {
            $rcLink = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Log in")] | //button[@name="buttonName" and contains(., "Log in")]'), 0);
            /*
            $this->saveResponse();
            */

            if ($rcLink) {
                throw new CheckRetryNeededException(2, 1);
            }
        }

        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password_input_input"]'), 0);
        $btnLogIn = $this->waitForElement(WebDriverBy::xpath('//div[@id="dialog"]//button[contains(., "Log in to")]'), 0);

        if (!$loginInput || !$passwordInput || !$btnLogIn) {
            $this->logger->error('something went wrong');
            $this->saveResponse();

            if ($this->waitForElement(WebDriverBy::xpath('//h3[contains(text(), "Download our new app!")]'), 0)) {
                throw new CheckRetryNeededException(2, 1);
            }

            return $this->checkErrors();
        }// if (!$loginInput || !$passwordInput || !$btnLogIn)

        $this->acceptCookies();

        $this->logger->debug("set credentials");
        $loginInput->clear();
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->clear();
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->logger->debug("submit form");
        $this->acceptCookies();
        $this->saveResponse();
        $btnLogIn->click();

        /*
        $this->http->GetURL('https://www.riu.com/en/riu-class/index.jsp');
        if (!$this->http->FindSingleNode('//a[normalize-space(text())="Create account"]')) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://www.riu.com/login.jsp';
        $this->http->Form = [];
        $this->http->SetInputValue("idioma", "en");
        $this->http->SetInputValue("j_username", $this->AccountFields['Login']);
        $this->http->SetInputValue("j_password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("metodo", "json");
        $this->http->SetInputValue("v", "web");
        */

        return true;
    }

    public function acceptCookies()
    {
        $this->logger->notice(__METHOD__);

        if ($accept = $this->waitForElement(WebDriverBy::xpath('//button[@id = "onetrust-accept-btn-handler"]'), 0)) {
            $this->logger->debug("accept cookies");
            $accept->click();

            sleep(1);
        }
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // HTTP Status 500
        if (
            $this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 500 -')]")
            || ($this->http->Response['code'] == 504 && $this->http->FindPreg("/An error occurred while processing your request\.<p>/"))
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        try {
            $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Riu Class - ")] | //a[contains(@href, "riu-class/personal-area/my-details")] | //strong[contains(text(), "Welcome back,")] | //div[contains(@class, "riu-class-login-form__msg--error")] | //p[contains(@class, "u-color-danger")] | //h3[contains(text(), "Hello again,")]'), 15);
//            $this->saveResponse();
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 5);
        }

//        if ($this->http->FindNodes('//p[contains(text(), "Riu Class - ")] | //a[contains(@href, "riu-class/personal-area/my-details")]/@href')) {
        if ($this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Riu Class - ")] | //a[contains(@href, "riu-class/personal-area/my-details")] | //strong[contains(text(), "Welcome back,")] | //h3[contains(text(), "Hello again,")]'), 10)) {
            if ($this->loginSuccessful()) {
                $this->State['jsessionid'] = $this->driver->executeScript('return localStorage.getItem("riu-jsessionId")');

                return true;
            }
        }

        $this->saveResponse();
        // Incorrect password or username
        $message = $this->http->FindSingleNode('//div[contains(@class, "riu-class-login-form__msg--error")] | //p[contains(@class, "u-color-danger")]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (in_array($message, [
                'Incorrect password or username',
                'Inconsistent user accesses',
                'Incorrect username or password.',
            ])
            ) {
                throw new CheckException(Html::cleanXMLValue($message), ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'), 0, true);
        // Name
        $this->SetProperty("Name", beautifulName(ArrayVal($response, 'nombre')));
        // Riu Class Nº
        $this->SetProperty("Number", ArrayVal($response, 'codCuenta'));
        // Status
        $this->SetProperty("Status", ArrayVal($response, 'descTarjeta'));
        // Balance - points balance
        $this->SetBalance(ArrayVal($response, 'ptosDisponible'));

        $this->openCurlDrive();
        $this->copySeleniumCookies($this, $this->curlDrive);

        $this->curlDrive->GetURL("https://www.riu.com/api/riuclass/v1/customer/riuclass/account?user-account=" . ArrayVal($response, 'codCuenta'), [
            'jsessionid'      => $this->State['jsessionid'] ?? '',
            'accept-version'  => 'vnd.web-backend-api.v0',
            'Accept-Language' => 'en',
        ]);
        $response = $this->curlDrive->JsonLog();

        if (isset($response->account->blocked_points)) {
            // Blocked
            $this->SetProperty('Blocked', $response->account->blocked_points);
        }

        if (isset($response->account->stays_last_year)) {
            // Nights
            $this->SetProperty('Nights', $response->account->stays_last_year);
        }

        if (isset($response->account->stays_status_description)) {
            // Nights until the next level
            $this->SetProperty('NightsUntilTheNextLevel', $this->http->FindPreg('/\d+/', false, $response->account->stays_status_description));
        }

        if (isset($response->account->stays_status_description)) {
            // Next elite level
            $this->SetProperty('NextEliteLevel', $this->http->FindPreg('/class (.*) status/ims', false, $response->account->stays_status_description));
        }

        $this->http->GetURL("https://www.riu.com/riuclass-utils!tarjetaUsuario.action?formato=json&v=web");
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'), 3, true);
        // Points accumulated
        $this->SetProperty("PointsAccumulated", ArrayVal($response, 'ptosAcumulados'));
        // Points frozen
        $this->SetProperty("PointsFrozen", ArrayVal($response, 'ptosBloqueados'));
        // Points available
        $this->SetProperty("PointsAvailable", ArrayVal($response, 'ptosDisponibles'));
        // Provisional points
        $this->SetProperty("ProvisionalPoints", ArrayVal($response, 'ptosProvisionales'));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && ArrayVal($response, 'errorID') == 'Cancelled account' && ArrayVal($response, 'errorMensaje')) {
            // [Cancelled account] Error retrieving the card status. Cancelled account
            throw new CheckException("[" . ArrayVal($response, 'errorID') . "] " . ArrayVal($response, 'errorMensaje'), ACCOUNT_PROVIDER_ERROR);
        }

        try {
            $this->http->GetURL("https://www.riu.com/riuclass-utils!frasesEstadoPuntos.action?formato=json&v=web");
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
        }
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'), 3, true);
        // Points to Next Level
        $this->SetProperty('PointsToNextLevel', $this->http->FindPreg('/You need\s+(.+)\s+pts. per person to get/ims'));

        $this->http->GetURL("https://www.riu.com/riuclass-utils!datosCuentaUsuario.action?formato=json&v=web&datosBasicos=false&idioma=en&request.aspect=desk");
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'), 0);
        // Registration date
        if (isset($response->titularReserva->fechaAltaAsString)) {
            $this->SetProperty("RegistrationDate", $response->titularReserva->fechaAltaAsString);
        } else {
            $this->logger->debug("Registration date not found");
        }

        // Expiration date  // refs #10197
        try {
            $this->http->GetURL("https://www.riu.com/riuclass-utils!ultimasReservasUsuario.action?formato=json&v=web&idioma=en&request.aspect=desk");
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
        }
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'), 3, true);
        $reservations = ArrayVal($response, 'listaReservas', []);
        $this->logger->debug("Total " . count($reservations) . " reservations were found");

        foreach ($reservations as $reservation) {
            $status = strtolower(ArrayVal($reservation, 'estado'));
            $date = ArrayVal($reservation, 'fechaSalida');

            if ($status == 'check-out' && (!isset($exp) || strtotime($date) > $exp)) {
                // Last Activity
                $this->SetProperty("LastActivity", strtotime($date));

                if ($exp = strtotime($date)) {
                    $this->SetExpirationDate(strtotime("+4 year", $exp));
                }
            }// if ($status == 'check-out' && (!isset($exp) || strtotime($date) > $exp))
        }// foreach ($reservations as $reservation)
    }

    public function ParseItineraries()
    {
        $headers = [
            'Accept' => 'application/json, text/plain, */*',
        ];

        try {
            $this->http->GetURL('https://www.riu.com/riuclass-utils!ultimasReservasUsuario.action?formato=json&v=web&idioma=en&request.aspect=desk', $headers);
        } catch (NoSuchWindowException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }
        $data = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'), 0, true);

        foreach (ArrayVal($data, 'listaReservas', []) as $reserva) {
            $checkOutDate = strtotime(ArrayVal($reserva, 'fechaSalida'));
            $pastItin = $checkOutDate && $checkOutDate < strtotime('now');

            if (!$this->ParsePastIts && $pastItin) {
                $this->logger->info('Skipping itinerary in the past');

                continue;
            }

            if ($pastItin) {
                $this->parseItineraryV2($reserva);
            } else {
                $conf = ArrayVal($reserva, 'codigo');

                try {
                    $this->http->GetURL("https://www.riu.com/riuclass-utils!detalleReservaConLocalizador.action?formato=json&v=web&idioma=en&request.aspect=desk&localizador=$conf&numSeq=1", $headers);
                } catch (NoSuchWindowException | NoSuchDriverException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                }

                if ($this->http->FindPreg('/"classNameExceptionJson":"java.lang.NullPointerException"/')) {
                    $this->parseItineraryV2($reserva);

                    continue;
                }
                $data = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'), 3, true);
                $this->parseItineraryV1($data);
            }
        }

        return [];
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'), 3, true);

        if (ArrayVal($response, 'codCuenta', null)) {
            return true;
        }

        return false;
    }

    private function parseItineraryV2($data)
    {
        $this->logger->notice(__METHOD__);
        $hotel = $this->itinerariesMaster->createHotel();
        // ConfirmationNumber
        $conf = ArrayVal($data, 'codigo');
        $hotel->addConfirmationNumber($conf, null, true);
        $this->logger->info("Parse Itinerary #$conf", ['Header' => 3]);
        // HotelName
        $hotel->setHotelName(ArrayVal($data, 'hotel'));
        // Address
        if (!empty($destino = ArrayVal($data, 'destino', ''))) {
            $pais = ArrayVal($data, 'pais', '');
            $address = $destino;

            if ($pais) {
                $address = "$address ($pais)";
            }
        }

        if (isset($address)) {
            $hotel->setAddress($address);
        } else {
            $hotel->setNoAddress(true);
        }
        // CheckInDate
        $date1 = strtotime(ArrayVal($data, 'fechaLlegada'));
        $hotel->setCheckInDate($date1);
        // CheckOutDate
        $date2 = strtotime(ArrayVal($data, 'fechaSalida'));
        $hotel->setCheckOutDate($date2);
        // Status
        $hotel->setStatus(ArrayVal($data, 'estado'));
        // Travellers
        $firstName = ArrayVal($data, 'nombre');
        $lastName = ArrayVal($data, 'apellidos');
        $guest = trim(beautifulName("$firstName $lastName"));

        if ($guest) {
            $hotel->addTraveller($guest);
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($hotel->toArray(), true), ['pre' => true]);
    }

    private function parseItineraryV1($data)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($data['detalleReservaList'][0])) {
            $this->sendNotification('check parse itinerary // MI');

            return;
        }
        $details = $data['detalleReservaList'][0];

        $hotel = $this->itinerariesMaster->createHotel();
        // ConfirmationNumber
        $conf = ArrayVal($details, 'localizadorReserva');
        $hotel->addConfirmationNumber($conf, null, true);
        $this->logger->info("Parse Itinerary #$conf", ['Header' => 3]);
        // HotelName
        $hotel->setHotelName(ArrayVal($details, 'nombreHotel'));
        // Address
        $hotel->setAddress(ArrayVal($details, 'lugar'));
        // CheckInDate
        $date1 = strtotime(ArrayVal($details, 'desde'));
        $hotel->setCheckInDate($date1);
        // CheckOutDate
        $date2 = strtotime(ArrayVal($details, 'hasta'));
        $hotel->setCheckOutDate($date2);
        // Adults
        $hotel->setGuestCount(ArrayVal($details, 'adultos'));
        // Kids
        $hotel->setKidsCount(ArrayVal($details, 'bebes'));
        // Status
        $hotel->setStatus(ArrayVal($details, 'estadoPeticion'));

        if ($hotel->getStatus() === "cancelada") {
            $hotel->setCancelled(true);
        }
        // Total
        if (is_array(ArrayVal($details, 'precioTotal'))) {
            $total = ArrayVal($details, 'precioTotal');
            $total = PriceHelper::cost(ArrayVal($total, 'importe'));
        } else {
            $total = PriceHelper::cost(ArrayVal($details, 'precioTotal'));
        }
        $hotel->price()->total($total);
        // Total price (pts)
        $points = PriceHelper::cost(ArrayVal($details, 'totalPuntosRC'));

        if (ArrayVal($details, 'reservaPrecioYpuntos') == true) {
            $hotel->price()->spentAwards($points . " pts");
        }
        // Currency
        $hotel->price()->currency(ArrayVal($details, 'moneda'));
        // Type
        $room = $hotel->addRoom();
        $roomType = strip_tags(ArrayVal($details, 'tipo'));
        // 1 <b>Jr. Suite Standard</b>
        //  + <b>Suite</b> with front sea view
        $roomType = $this->http->FindPreg('/^\s*[\d+]+\s+(.+)/', false, $roomType);
        $room->setType($roomType);
        // Description
        $room->setDescription(ArrayVal($details, 'regimen'));
        // Rate
        $room->setRate(ArrayVal($details, 'precioNocheFormat'));
        // Travellers
        if (isset($data['detalleHuespedes'][0]['huesped'])) {
            $guests = $data['detalleHuespedes'][0]['huesped'];
            $hotel->setTravellers(array_unique(explode(', ', $guests)));
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($hotel->toArray(), true), ['pre' => true]);
    }

    private function copySeleniumCookies($selenium, $curl)
    {
        $this->logger->notice(__METHOD__);

        $cookies = $selenium->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $curl->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
    }

    private function openCurlDrive()
    {
        $this->logger->notice(__METHOD__);
        $this->curlDrive = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->curlDrive);
    }
}
