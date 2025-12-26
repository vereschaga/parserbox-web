<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Schema\Parser\Common;

class TAccountCheckerUral extends TAccountChecker
{
    // TODO: Yandex SmartCaptcha https://captchaforum.com/threads/yandex-captcha-bypass.4353/

    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $selenium;

    private $headers = [
        "Accept"           => "application/json, text/plain, */*",
        "Origin"           => "https://www.uralairlines.ru",
        "Content-Type"     => "multipart/form-data; boundary=---------------------------14739151813917741611952659626",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        // $this->http->SetProxy($this->proxyReCaptcha());
//        $this->setProxyBrightData(null, "dc_ips_ru", "ru");
//        $this->setProxyGoProxies(null, 'ru');
        $this->http->FilterHTML = false;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.uralairlines.ru/en/cabinet/", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->logger->notice(__METHOD__);
            $this->selenium = clone $this;
            $this->http->brotherBrowser($this->selenium->http);

            $this->logger->notice("Running Selenium...");
            $this->selenium->UseSelenium();

//            $this->selenium->usePacFile(false);

            $this->selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $this->selenium->setKeepProfile(true);
            $this->selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
            $this->selenium->seleniumOptions->addHideSeleniumExtension = false;
            $this->selenium->seleniumOptions->userAgent = null;

            $this->selenium->http->saveScreenshots = true;

            $this->selenium->http->start();
            $this->selenium->Start();

//            $this->selenium->http->removeCookies();
            $this->selenium->http->GetURL("https://www.uralairlines.ru/en/cabinet/auth/?redirect=cabinet");

            $this->selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"] | //div[contains(@class, "promo-code-popup")]//button[contains(@class, "modal-close")] | //iframe[@data-testid="checkbox-iframe"]'), 15);
            $this->selenium->saveResponse();

            /*
            $this->selenium->driver->executeScript("document.querySelector('#captcha-modal').remove()");
            $this->selenium->saveResponse();
            */

            $this->yandexCaptchaRecognizing();

            $closePopupButton = $this->selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "promo-code-popup")]//button[contains(@class, "modal-close")]'), 0);

            if ($closePopupButton) {
                $this->selenium->saveResponse();
                $closePopupButton->click();
            }

            $login = $this->selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 2);
            $password = $this->selenium->waitForElement(WebDriverBy::xpath('//input[@name="password"]'), 0);
            $this->selenium->saveResponse();

            $login->sendKeys($this->AccountFields['Login']);
            $password->sendKeys($this->AccountFields['Pass']);
            $this->selenium->saveResponse();

            $submit = $this->selenium->waitForElement(WebDriverBy::xpath('//input[@id="auth_submit_cabinet"]'), 0);
            $submit->click();

            $this->yandexCaptchaRecognizing();

            /*
            if (!$this->processCaptcha()) {
                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }
            */

            $this->selenium->waitForElement(webdriverby::xpath("//div[@class = 'myinfo__card']"), 5);

            try {
                $this->saveToLogs();
            } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
                $this->selenium->driver->switchTo()->defaultContent();
                $this->saveToLogs();
            }

            return true;
        } finally {
            $this->selenium->http->cleanup();
        }
    }

    /*
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.uralairlines.ru/en/cabinet/auth/?redirect=cabinet");
        /*
        $sessid = $this->http->FindPreg("/'bitrix_sessid':'([^\']+)/");
        $sitekey = $this->http->FindPreg("/var recaptcha_sitekey = '([^\']+)';\s*var recaptcha_in_auth = '1';/");
        if (!$sessid)
            return $this->checkErrors();
        $data = [
            "Login"    => "",
            "Password" => "",
            "data"     => "auth",
            "lang"     => "ru",
            "sessid"   => $sessid,
            "template" => "wings",
        ];
        $headers = [
            "X-Requested-With" => "XMLHttpRequest",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "Accept"           => "text/html, *
        /*; q=0.01",
        ];
        $this->http->PostURL("https://u6ibe.book.uralairlines.ru/api/v2.2/session", $data, $this->headers);
        */

//        if (!$this->http->ParseForm("login_cabinet_from_id")) {
    /*
        if ($this->http->FindPreg('/cabinet-disable="1"></')) {
            throw new CheckException("Planned work is underway to update the Wings program. All online services of the site are not available, including access to your personal account and the ability to issue a premium ticket. The program is updated and will be better for you. Thanks!", ACCOUNT_PROVIDER_ERROR);
        }

        if (!$this->http->FindPreg('/<form class="uk-form-default">/')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields["Login"]);
        $this->http->SetInputValue('password', $this->AccountFields["Pass"]);
        $this->http->SetInputValue('my_computer', "1");

        $this->http->Form = [];

        $captcha = $this->parseReCaptcha();
        $captchaData = '';

        if ($captcha !== false) {
            $captchaData = '
-----------------------------14739151813917741611952659626
Content-Disposition: form-data; name="g-recaptcha-response"

' . $captcha;
        }

        $this->http->RetryCount = 0;
        $data = '-----------------------------14739151813917741611952659626
Content-Disposition: form-data; name="username"

' . $this->AccountFields["Login"] . '
-----------------------------14739151813917741611952659626
Content-Disposition: form-data; name="password"

' . $this->AccountFields["Pass"] . '
-----------------------------14739151813917741611952659626
Content-Disposition: form-data; name="my_computer"

0' . $captchaData . '
-----------------------------14739151813917741611952659626--
';
        $this->http->PostURL("https://www.uralairlines.ru/en/cabinet/auth/?ajax=auth&action=auth", $data, $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }
    */
    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "404 Not Found")]')) {
            $this->http->GetURL("https://www.uralairlines.ru/");
        }

        // Проводятся плановые работы по обновлению программы «Крылья».
        if ($message = $this->http->FindSingleNode("
                //h6[contains(text(), 'Проводятся плановые работы по')]
                | //div[contains(text(), 'На сайте проводятся технические работы.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("
                //h1[contains(text(), '502 Bad Gateway')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // login successful
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        // Неверный логин или пароль.
        if ($message = $this->http->FindSingleNode('//*[contains(@class, "uk-notification-message-danger")]')) {
            $this->logger->error("[Error]: {$message}");

            $this->captchaReporting($this->recognizer);

            if (
                strstr($message, 'Authorization error, check the entered data')
                || $message == 'Пользователь не найден, просим вас зарегистрироваться.'
                || $message == 'User not found, please register.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    /*
    public function Login()
    {
        $response = $this->http->JsonLog();

        $status = $response->status ?? null;

        if ($status == 'success') {
        $this->http->GetURL('https://www.uralairlines.ru/en/cabinet/');
        }

        // login successful
        if ($this->loginSuccessful()) {
            // $this->captchaReporting($this->recognizer);

            return true;
        }
        // Неверный логин или пароль.
        if ($message = $response->global_form->text ?? null) {
            $this->logger->error($message);

            // $this->captchaReporting($this->recognizer);

            if (
                strstr($message, 'Authorization error, check the entered data')
                || $message == 'Пользователь не найден, просим вас зарегистрироваться.'
                || $message == 'User not found, please register.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }
    */

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h2[@class = 'myinfo__person-name']")));
        // Status
        $this->SetProperty("Level", $this->http->FindSingleNode("//a[contains(@class, 'myinfo__bonus-level')]"));
        // Полёты
        $this->SetProperty("FlightsYTD", $this->http->FindSingleNode("//div[contains(text(), 'Number of flights')]/following-sibling::span[contains(@class, 'bonus-earned__val')]"));
        // Номер карты
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//div[@class = 'myinfo__card']"));
        // Balance - Bonuses
        $this->SetBalance(
            $this->http->FindSingleNode("//div[contains(@class, 'myinfo__bonus-points') and contains(normalize-space(),'Bonuses:')]/strong")
            ?? $this->http->FindSingleNode("//div[contains(@class, 'myinfo__bonus-points')]/strong")
        );
        // Expiring balance
        $this->SetProperty("ExpiringBalance",
            $this->http->FindSingleNode("//div[contains(text(), 'Burns')]/following-sibling::div/text()[1]")
            ?? $this->http->FindSingleNode("//div[@class = 'user-stats__col' and position() = 2]/following-sibling::div/text()[1]")
        );
        // Burns ...
        $exp =
            $this->http->FindSingleNode("//div[contains(text(), 'Burns')]", null, true, "/Burns\s*(.+)/")
            ?? $this->http->FindSingleNode("//div[@class = 'user-stats__col' and position() = 2]", null, true, "/Burns\s*(.+)/")
        ;

        if ($exp && ($exp = strtotime($exp))) {
            $this->SetExpirationDate($exp);
        }
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.uralairlines.ru/en/";
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Order number",
                "Type"     => "string",
                "Size"     => 30,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Caption"  => "Surname",
                "Size"     => 30,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->SetProxy(null);
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $this->sendNotification('need to check retrieve');

        $headers = [
            'Accept' => 'application/json, text/plain, */*',
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://service.uralairlines.ru/3328/env/env.json', $headers);
        $this->http->RetryCount = 2;
        $data = $this->http->JsonLog(null, 2, true);
        $apiKey = ArrayVal($data, 'API_KEY');

        if (!$apiKey) {
            $this->sendNotification('check retrieve api key // MI');

            return null;
        }

        $headers = [
            'Accept'          => 'application/json, text/plain, */*',
            'Accept-Language' => '',
            'X-Api-Key'       => $apiKey,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://u6ibe.book.uralairlines.ru/api/v2.3/Session', [], $headers);
        $this->http->RetryCount = 2;
        $data = $this->http->JsonLog(null, 2, true);
        $sessionKey = ArrayVal($data, 'sessionKey');

        if (!$sessionKey) {
            $this->sendNotification('check retrieve session key // MI');

            return null;
        }

        $headers = [
            'Accept'          => 'application/json, text/plain, */*',
            'Accept-Language' => '',
            'X-Api-Key'       => $apiKey,
            'X-Session'       => $sessionKey,
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://u6ibe.book.uralairlines.ru/api/v2.3/Reservation?pnrNumber={$arFields['ConfNo']}&lastName={$arFields['LastName']}&ticketNumber=", $headers);
        $this->http->RetryCount = 2;
        $data = $this->http->JsonLog(null, 2, true);

        if (!$data) {
            $this->sendNotification('check retrieve get // MI');

            return null;
        }

        if ($this->http->FindPreg('/PNR_Retrieve function returned error/')) {
            return 'We are unable to find this confirmation number. Please validate your entry and try again or contact us for further information. (8104)';
        }

        $this->parseItinerary($data);

        return null;
    }

    protected function yandexCaptchaRecognizing()
    {
        $this->logger->notice(__METHOD__);
        $delay = 5;
        $captchaIframe = $this->selenium->waitForElement(WebDriverBy::xpath('//iframe[@data-testid="checkbox-iframe"]'), 0);

        if ($captchaIframe) {
            $this->selenium->driver->switchTo()->frame($captchaIframe);
            $this->selenium->saveResponse();

            $captchaLabel = $this->selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'js-button']"), 5);
            $this->selenium->saveResponse();

            if (!$captchaLabel) {
                return;
            }

            $captchaLabel->click();

            $this->selenium->driver->switchTo()->defaultContent();
            $delay = 20;
        }

        $captchaAdvancedIframe = $this->selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "_visible")]//iframe[@data-testid="advanced-iframe"]'), $delay);
        $this->selenium->saveResponse();

        if (!$captchaAdvancedIframe) {
            return;
        }

        $this->selenium->driver->switchTo()->frame($captchaAdvancedIframe);
        $this->selenium->saveResponse();

        $captchaInput = $this->selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'rep']"), 5);
        $this->selenium->saveResponse();

        if (!$captchaInput) {
            return;
        }

        $submitBtn = $this->selenium->waitForElement(WebDriverBy::xpath("//button[@aria-describedby=\"submit-description\"]"), 0);
        $img = $this->selenium->waitForElement(WebDriverBy::xpath("//img[@alt=\"Image challenge\"]"), 5);
        $this->selenium->saveResponse();

        if (!$img || !$submitBtn) {
            return;
        }

        $captcha = $this->parseCaptcha($img);

        if ($captcha == false) {
            return;
        }

        $captchaInput->sendKeys($captcha);
        $submitBtn->click();

        $this->logger->debug("delay -> 10 sec");
        $this->selenium->saveResponse();
        sleep(10);

        $this->selenium->driver->switchTo()->defaultContent();
        $this->selenium->saveResponse();
    }

    protected function parseCaptcha($img)
    {
        $this->logger->notice(__METHOD__);

        if (!$img) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $pathToScreenshot = $this->selenium->takeScreenshotOfElement($img);
        $captcha = $this->recognizeCaptcha($this->recognizer, $pathToScreenshot);
        unlink($pathToScreenshot);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//div[@class = 'myinfo__card']")) {
            return true;
        }

        return false;
    }

    private function parseItinerary(array $data)
    {
        $this->logger->notice(__METHOD__);
        $flight = $this->itinerariesMaster->createFlight();
        // ConfirmationNumber
        $conf = $this->arrayVal($data, ['data', 'number']);
        $flight->addConfirmationNumber($conf, 'Order number', true);
        // ReservationDate
        $reservationDate = strtotime($this->arrayVal($data, ['data', 'reservationDate']));
        $flight->setReservationDate($reservationDate);
        // Total
        // $total = $this->arrayVal($data, ['data', 'baseFarePrices', 0, 'totalPrice']);
        // $flight->price()->total($total);
        // Currency
        // $currency = $this->arrayVal($data, ['data', 'baseFarePrices', 0, 'currency']);
        // $flight->price()->currency($currency);
        // Travellers
        $passengers = $this->arrayVal($data, ['data', 'passengers'], []);

        foreach ($passengers as $passenger) {
            $firstName = ArrayVal($passenger, 'firstName', '');
            $lastName = ArrayVal($passenger, 'surname', '');
            $traveller = trim(beautifulName(sprintf('%s %s', $firstName, $lastName)));

            if ($traveller) {
                $flight->addTraveller($traveller);
            }
        }
        // TicketNumbers
        $tickets = $this->arrayVal($data, ['data', 'tickets'], []);

        foreach ($tickets as $ticket) {
            $ticket = ArrayVal($ticket, 'number');

            if ($ticket) {
                $flight->addTicketNumber($ticket, false);
            }
        }
        // Segments
        $outboundFlights = $this->arrayVal($data, ['data', 'journey', 'outboundFlights'], []);
        $returnFlights = $this->arrayVal($data, ['data', 'journey', 'returnFlights'], []);
        $allFlights = array_merge($outboundFlights, $returnFlights);

        foreach ($allFlights as $segmentItem) {
            $segment = $this->parseSegment($flight, $segmentItem);
            // Seats
            $seats = $this->findSeats($data, $segmentItem);
            $segment->setSeats($seats);
            // Meal
            // $meal = $this->findMeal($data, $segment);
            // $segment->addMeal($meal, false, true);
        }

        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($flight->toArray(), true), ['pre' => true]);
    }

    private function findMeal(array $data, Common\FlightSegment $segment): ?string
    {
        $this->logger->notice(__METHOD__);
        $foodOnBoards = $this->arrayVal($data, ['data', 'foodOnBoards'], []);

        foreach ($foodOnBoards as $food) {
            $correctFlight = (
                ArrayVal($food, 'flightNumber') === $segment->getFlightNumber()
                && ArrayVal($food, 'departureAirport') === $segment->getDepCode()
                && ArrayVal($food, 'arrivalAirport') === $segment->getArrCode()
            );

            if ($correctFlight) {
                return ArrayVal($food, 'en');
            }
        }

        return null;
    }

    private function parseSegment(Common\Flight $flight, array $segmentItem): Common\FlightSegment
    {
        $this->logger->notice(__METHOD__);
        $segment = $flight->addSegment();
        // FlightNumber
        $segment->setFlightNumber(ArrayVal($segmentItem, 'flightNumber'));
        // DepCode
        $segment->setDepCode(ArrayVal($segmentItem, 'origin'));
        // ArrCode
        $segment->setArrCode(ArrayVal($segmentItem, 'destination'));
        // DepDate
        $depDate = strtotime(ArrayVal($segmentItem, 'departureDate'));
        $segment->setDepDate($depDate);
        // ArrDate
        $arrDate = strtotime(ArrayVal($segmentItem, 'arrivalDate'));
        $segment->setArrDate($arrDate);
        // Aircraft
        $segment->setAircraft(ArrayVal($segmentItem, 'aircraft'));
        // Duration
        $segment->setDuration(ArrayVal($segmentItem, 'flightDuration'));
        // Cabin
        $classOfService = ArrayVal($segmentItem, 'classOfService');

        if ($classOfService === 'X' || $classOfService === 'O') {
            $segment->setCabin('Economy');
        } else {
            $this->sendNotification("check new $classOfService cabin // MI");
        }

        return $segment;
    }

    private function findSeats(array $data, array $segmentItem): array
    {
        $this->logger->notice(__METHOD__);
        $res = [];
        $seatItems = $this->arrayVal($data, ['data', 'seats'], []);

        foreach ($seatItems as $seatItem) {
            if (ArrayVal($seatItem, 'flightReference') === ArrayVal($segmentItem, 'referenceNumber')) {
                $seat = trim(sprintf('%s%s', ArrayVal($seatItem, 'number', ''), ArrayVal($seatItem, 'title', '')));

                if ($seat) {
                    $res[] = $seat;
                }
            }
        }

        return $res;
    }

    private function arrayVal($ar, $indices, $default = null)
    {
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return $default;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    private function saveToLogs()
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        $this->selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($this->selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
}
