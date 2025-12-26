<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerEuropcar extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $currentItin = 0;
    private $skipItin = 0;

    private $headers = [
        "Accept"  => "application/json, text/plain, */*",
        "Referer" => "https://www.europcar.com/",
        "Origin"  => "https://www.europcar.com",
    ];
    private $customerId = null;

    public static function FormatBalance($fields, $properties)
    {
        $format = $fields['Balance'] == 1 ? "%d day" : "%d days";

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], $format);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->setHttp2(false);
        $this->setProxyMount();
    }

    public function IsLoggedIn()
    {
//        if ($this->loginSuccessful()) {
//            return true;
//        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.europcar.com/en-us");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $telemetry = $this->http->FindPreg("/telemetry=([^&=]+)/", false, $this->getCookiesFromSelenium());

        $data = [
            "client_id"    => "onesite-client",
            "grant_type"   => "password",
            "username"     => $this->AccountFields['Login'],
            "password"     => $this->AccountFields['Pass'],
            //"bm-telemetry" => $telemetry ? urldecode($telemetry): $this->getTelemetry(),
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://secure.europcar.com/auth/realms/ecom/protocol/openid-connect/token", $data, $this->headers);
        $this->http->RetryCount = 2;

        if (strpos($this->http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)') !== false) {
            $this->DebugInfo = "Need to update sensor_data {$this->DebugInfo}";

            return false;
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->access_token)) {
            $customerDataStr = base64_decode(explode('.', $response->access_token)[1] ?? null);
            $this->logger->debug($customerDataStr);
            $customerData = $this->http->JsonLog($customerDataStr);
            $customerId = $customerData->customerId ?? null;

            if (!isset($customerId)) {
                return false;
            }

            $this->customerId = $customerId;
            $this->headers += [
                "Authorization" => "Bearer {$response->access_token}",
            ];

            return true;
        }

        $message = $response->error_description ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Invalid user credentials') {
                throw new CheckException("Email and/or password is/are invalid. Please double check or try again using your Driver ID.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://api.aws.emobg.io/customerapi/v1/customers/{$this->customerId}?view=full&getAccount=true", $this->headers);
        $response = $this->http->JsonLog();
        // Name
        $this->SetProperty("Name", beautifulName(($response->customerDetails->firstName ?? '') . " " . ($response->customerDetails->lastName ?? '')));
        // Europcar Driver Id
        $this->SetProperty("AccountNumber", $response->privilegeProgram->membershipId ?? null);
        // Status
        $this->SetProperty("Status", $this->getStatusByCode($response->privilegeProgram->code ?? null));
        // Qualifying rentals
        $this->SetProperty("QualifyingRentals", $response->privilegeProgram->rentalNbr ?? null);
        // Qualifying days
        $this->SetProperty("QualifyingDays", $response->privilegeProgram->rentalDayNbr ?? null);

        // Balance  // refs #5743
        if (isset($this->Properties['QualifyingDays'])) {
            $this->SetBalance($this->Properties['QualifyingDays']);
        } elseif (
            (
                (
                    !empty($this->Properties['Status'])
                    && isset($this->Properties['AccountNumber'])
                )
                || $response->privilegeProgram === null
            )
            && !empty($this->Properties['Name'])
        ) {
            $this->SetBalanceNA();
        }
    }

    private function getStatusByCode($code)
    {
        $this->logger->notice(__METHOD__);

        switch ($code) {
            case 'PC': $status = 'Privilege Club'; break;
            case 'PX': $status = 'Privilege Executive'; break;
            case 'PE': $status = 'Privilege Elite'; break;
            case 'PV': $status = 'Privilege Elite VIP'; break;
            default: $status = null;
        }

        return $status;
    }

    public function ParseItineraries()
    {
//        $this->http->GetURL("https://www.europcar.com/en-us/account/customer?section=bookings");
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://api-gf.aws.emobg.io/emobgapi/v1/bookings?limit=30", $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (
            $this->http->FindPreg("/^\[\]$/")
            || $this->http->Response['code'] == 400 && $this->http->FindPreg("/\"message\":\"Cars information of carCategory '\[[^\]]+\]' and for countryCode '/")
        ) {
            return $this->itinerariesMaster->setNoItineraries(true);
        }

        if ($this->http->Response['code'] != 200) {
            return [];
        }

        $rentalCount = ((is_array($response) || ($response instanceof Countable)) ? count($response) : 0);
        $this->logger->debug("Total {$rentalCount} itineraries were found");

        foreach ($response as $rental) {
            $this->parseJsonRental($rental);
        }

        if (count($this->itinerariesMaster->getItineraries()) === 0
            && !$this->ParsePastIts
            && $rentalCount === $this->skipItin
        ) {
            $this->logger->debug("all skipped(past)-> noItineraries");

            return $this->noItinerariesArr();
        }

        return [];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.europcar.com/en-us/reservation/searchbooking";
    }

    public function notifications($arFields)
    {
        $this->logger->notice("notifications");
        $this->sendNotification("europcar - failed to retrieve itinerary by conf #", 'all', true,
            "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}");

        return null;
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if ($this->http->Response['code'] != 200) {
            return $this->notifications($arFields);
        }

        $this->headers = [
            "x-caller"      => "onesite",
            "X-reservation" => $arFields["ConfNo"],
        ];

        if (filter_var($arFields["LastName"], FILTER_VALIDATE_EMAIL) === false) {
            $this->http->GetURL("https://api-gf.aws.emobg.io/emobgapi/v1/bookings/{$arFields["ConfNo"]}?apikey=mqK5fTg12djSMga6sl1NbgeuOwbMhAxR&lastName={$arFields["LastName"]}", $this->headers);
        } else {
            $this->http->GetURL("https://api-gf.aws.emobg.io/emobgapi/v1/bookings/{$arFields["ConfNo"]}?apikey=mqK5fTg12djSMga6sl1NbgeuOwbMhAxR&email={$arFields["LastName"]}", $this->headers);
        }

        $response = $this->http->JsonLog();
        $message = $response->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == "Booking with id: {$arFields["ConfNo"]} not found") {
                return "Sorry, we couldn’t find your booking";
            }

            return $this->notifications($arFields);
        }

        $this->parseJsonRental($response);

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Reservation number",
                "Type"     => "string",
                "Size"     => 33,
                "Required" => true,
            ],
            "LastName"      => [
                "Caption"  => "Last Name or Email",
                "Type"     => "string",
                "Size"     => 50,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    private function parseJsonRental($rentalData)
    {
        $this->logger->notice(__METHOD__);
        // confirmation number
        $conf = $rentalData->id;
        $this->logger->info("[{$this->currentItin}] Parse Rental #{$conf}", ['Header' => 3]);
        $this->currentItin++;

        // pickup and dropoff datetime
        $pickupDate = $rentalData->pickup->dateTime;
        $dropoffDate = $rentalData->dropOff->dateTime;
        $pastItin = $dropoffDate && strtotime($dropoffDate) < strtotime('-1 day', strtotime('now'));

        if ($pastItin && !$this->ParsePastIts) {
            $this->logger->info('Skipping itinerary in the past');
            $this->skipItin++;

            return;
        }

        $rental = $this->itinerariesMaster->createRental();
        $rental->addConfirmationNumber($conf, 'Reservation number', true);

        $rental->general()
            ->date2($rentalData->creationDate)
            ->traveller($rentalData->driver->personalInformation->firstName . " " . $rentalData->driver->personalInformation->lastName, true)
        ;

        $rental->setPickUpDateTime(strtotime($pickupDate));

        if ($rental->getPickUpDateTime() == strtotime($dropoffDate)) {
            $rental->setDropOffDateTime(strtotime('+1 minute', strtotime($dropoffDate)));
        } else {
            $rental->setDropOffDateTime(strtotime($dropoffDate));
        }
        // pickup location
        $rental->setPickUpLocation(
            $rentalData->pickup->stationName
            . ", " . $rentalData->pickup->city
            . ", " . $rentalData->pickup->countryCode
        );
        // dropoff location
        $rental->setDropOffLocation(
            $rentalData->dropOff->stationName
            . ", " . $rentalData->dropOff->city
            . ", " . $rentalData->dropOff->countryCode
        );

        // total
        $rental->price()
            ->total(PriceHelper::cost($rentalData->priceInCustomerCurrency->amount))
            ->cost(PriceHelper::cost($rentalData->basePriceInCustomerCurrency->amount))
            ->tax(PriceHelper::cost($rentalData->rate->taxes->VAT))
        ;
        // currency
        $rental->price()->currency($rentalData->priceInCustomerCurrency->currency);

        // car image url
        $imgUrl = $rentalData->car->photos->left->large ?? null;

        if (isset($imgUrl)) {
            $this->http->NormalizeURL($imgUrl);
            $rental->setCarImageUrl($imgUrl);
        }

        // car type
        $rental->setCarType($rentalData->car->category ?? null, false, true);
        $rental->setCarModel($rentalData->car->carModel ?? null, false, true);

        $this->logger->info('Parsed Rental:');
        $this->logger->info(var_export($rental->toArray(), true), ['pre' => true]);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        // for English version
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.europcar.com/en-us/account/customer?section=profile");
        $this->http->RetryCount = 2;
        // Access is allowed
        if ($this->http->FindSingleNode("(//*[contains(text(), 'Log out')])")) {
            return true;
        }

        return false;
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);
        $responseData = null;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;
            $selenium->usePacFile(false);
            $selenium->keepCookies(false);

            $selenium->seleniumOptions->recordRequests = true;

            $selenium->useFirefoxPlaywright();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.europcar.com/en-us");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('window.stop();');
            }

            sleep(3);
            $agree = $selenium->waitForElement(WebDriverBy::xpath('//button[@id="didomi-notice-agree-button"]'), 2);
            if ($agree) {
                $agree->click();
            }

            $this->savePageToLogs($selenium);
            $selenium->driver->executeScript('document.querySelector(\'[data-test-id="login-nav-button"]\').click()');

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@id="email"]'), 7);
            $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@id="password"]'), 0);
//            $button = $selenium->waitForElement(WebDriverBy::xpath('//input[@data-test-id="submit-button"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$pass) {
                return false;
            }

            $login->sendKeys($this->getRandomString() . '@yahoo.com');
            $pass->sendKeys($this->getRandomString());

            $this->logger->debug("click 'Sign in'");
            $this->savePageToLogs($selenium);
            $selenium->driver->executeScript('document.querySelector(\'[data-test-id="submit-button"]\').click()');

            $selenium->waitForElement(WebDriverBy::xpath('//p[@data-test-id="error-message-label"]'), 10);
            $this->savePageToLogs($selenium);

            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
//                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
                //$this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
                if (stristr($xhr->request->getUri(), '/ecom/protocol/openid-connect/token')) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->request->getBody()));
                    $responseData = json_encode($xhr->request->getBody());

                    break;
                }
            }

            //$this->logger->info("[Form responseData]: " . $responseData);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (
            NoSuchDriverException
            | Facebook\WebDriver\Exception\InvalidSessionIdException
            | Facebook\WebDriver\Exception\UnrecognizedExceptionException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $responseData;
    }

    private function getRandomString()
    {
        $symbols = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $result = [];
        for ($i = 0; $i <= 10; $i++) {
            $result[] = $symbols[rand(0, strlen($symbols) - 1)];
        }

        return join($result);
    }

    private function getTelemetry()
    {
        $this->logger->notice(__METHOD__);

        return "a=&&&e=Q0MyMTRBODYzOTdCNEYyRjEwNEMyODE1NjI5NkMyQkF+WUFBUW13TkpGMDdzSWc2U0FRQUFRckRjTFJueDFrak9FWklibGF6bk55RW1MV0w3V2RmWlZ0eWtoMzlxSG9WZE9Ob3J3WGNVQlhER056aWUzYy8vVDJQd010ZHZRK1B6TjNBZUYvbTJ0b3JFRS8rR1A1eWNGMkFUVWFpZlVTYVJWbE51SDlBU1EyVnRTZU5aSkNoVjBqQnFrUlF1enU4aUVQcHRWdmdSTmREVVJmMTZBMldtLy92VXJHL0pYY2Y3dDhJdlZPZHdkV3czNUgxVCttcmtUUWJVVE8zYy81ciszY2RzZVQ2N29ENll3Q2RudHNLWVZHNHFveXpyU2hhRjYrZyt2KzhodkIrVFBNMm1rbXE2QWpCZlJIRC9YcUNDT29kMDh0QlNhalJhbWRZMGtyYUE5WVlBdm4xVjFwM0ljVGJPOXpVaXVBOS9raitneEVNWTJjemlWb3ZFc1Y5YUVKQkkzUXZSb0R5TXhxMDY0emFsblJhcGQ4TkwxUHBQTkVYTllSYTN0RkFhdm9mRDVtdFBnTnI5TGFqRDlBPT1+MzQyMTc2NX4zMjI1OTIx&&&sensor_data=MjszNDIxNzY1OzMyMjU5MjE7MTcsMTgsMCwxLDcsMjQ7UjMlYmgkSXllL05KTyRMSCwtIz1HV1pqUkl9P0JKeyQ4Zm5OU0RKKWo7W2lwP3tjW2huLixST2BWVSlja2heTkV7d3NnKz59ciRpMVc0aHVudys8fSlZMTIpYlkrVDMhOzZjPHJzd2FXOXVwdk9aTUJqbTJKYzxuZyNiV25+ZDo1KEE2NlgrI3dSPF4oaUtZWnMvWXxNYCR1Jm5MZyBiU09PWVFqaHkpKlZIaHpdSllqLz19M3IgUVJhVDNQc2lKU0VkWiEpTT5MNyVEY1k+S1kzLyVIITdwdDgvc3s1ID9BQlpJNDlkWnNbSmo7M3w/TmN5NEkmTH1pOHJBbDUkeS4mVWUsRml9L3diMWJoVHwyaTd4RjJYN1NvYmA7aGZRZXo1UyhHZXV2Z1BlICwrUmNIaF5DLUImPW1me0wkLlYjMUo9WG1ASUNubUN3SEt6LXxXIVFKdC5BdSgkVTohJENKUkB7Sys5NCgsZkdYZSZVRW5jSTchajdxdiFfOUlGbTd6OyVQejZxSiVRPlBkMGtVNjV9blFGcE5UbGFBTVopOyRjQW1PezpeZ1FARGIwbEBQPzcsdHdFJThNPzlQUypML2pkMnxBcCo3M3czVDBdZ1hdR0k/L3RecDx1azJELFQvcF9ALV4wVHRYVnc2d1NbTk9QXkhRK3MuWk1IcyRlNipNaD00a0pvLCl1T3NdRU0+SkFLTFQ0K0sheHU7az0jflFfSDdsVnk7PUN5ODd9fnwzQkBJVTw6aUAwOWAmc1hJRkgsJmlbflVCTixgSjxTVHR9Oio/MyZkPElQdD86e31vfWlxTElzR1BoQ29jWn11QkstT2ZdZ1EzRVcwZFo2STdPPFI6NVk4UVsyeHh5MUlmdkZTcHBmRCo8VzJwVFNgZmxwe3RsPGx8KShVU2ByMVJ3a197bUIrZCRHMmZdWGFsNSA6OTp4VXotfWhpdDZgK0d7OWpfe11JW3ttWVlTe3gpPExTTzV7UmheSlVkM1AvSjY+cXs4fnJAZEc1PzpjbzE7O2dxVE91Sjp8OkghTCpAIUQkNEE/Omw0dXAkd0VlLTZaaiRrbiNWW0tpIFolXzp6Qis3VEtVIE9QOk5TKjpoLExgb2FuSG5xdihVJF59fFg1biRUWVMmZ1JPczJNc01kQ0trS3A+fXgqQW5KMnlCP3pXeUtRUTFsdXIqQXw+bjl7NFNbXjtvJCNfemhCPnRuRnJbIzQ9NWh0fEVWKmBCY0ViMy49Jmd9IGIwclhPOi5pWCxfayZqUWxqR2g7cVcqaiBGP05sTFZxUipuTl5WQy8jTC9NZlVAUF87Y0ppZkJ7UG8wQTN7P1U0dHRUV05eOTxLXXE1eHcuQypOP3RlQilYPkRsUlRuMnJQSklZTFVGUHt1KU9OQmspViR8YVE4fmc5Wnx2bzZjTiozfj0+TEQ/JnE9Y3JkI1MwZFtFNix6YDdXJCguYHhwWmRkcm8zMj8tbkFJfG1IY1MvIyh4ZFY+LzM1LTxmZiskUTNKITt1TUFtO297UktNcl0sdlBBSmFIdiYgKTg4ZTA7QnFKP28uVEpROEB3WiEgZ20ySTp0WEgrZ2pvWTU2NEtYdTRrcC4uI2s9XnBMK2NvfFkvKTUpfV40THNNIS4uUFotSG8sUkNNQVg+Tzp2emR4djQ7UTtLdGp9Ny13fHtEXy5BeTlma29ZSGgra0xZVGpxMDpRXVpBJE1rUU1ZVy5DJVN5QV56MXl4Mk1ILUI/TmIjPFRVYFZHXyZ1bn5AZyxpOn00cnw4fiVIdGRPYGM7OmlmVUlfVE9wPzcqT2tFcTx5VCxaeD04OV83KHU4Llh8TmUkPVVARW9ORU5RJl0ue0BCSlFXMCYrWzQ2LlFeMUAgdHRvMjBEdkBCVCM+clF9IGhCKkx3aCRUMjYuVWg7akRyOVNrbypSNkJNJj8vYFo0KmlIfTtVYzsoKyZmbU18ViVUe05KZzt7IzJ5QjJkbUlDLXc5eiotfl94JENsVyNjN3dAcG0jaUxmfFc3IG9oSEcvKVo+RmtFOFpnQFJDIGo5JjohL00oezZTRmhpTldWWzQtd1FzMXdmMDF2OyheUy14TTI6ZVZJZHttSUcyRDZMMTFtWV9BMzAsbEZ+akxPLXE8eDxEaEkmQjR1c3Ygdn54KmtbdUtRazZQa3s4fXhpWTMhZDtqNjZeUi05aShRbWtrXUlxRHNHc0BXai5OJW87dGNTTCZkLV9dSF5SRiAyPk41NWIuRXt7NTUhemV2YltVPW85S0wvW1FWY2osM09hdmRuQCUsOWtDOXohdTt7M3RsQG8gLXVMXUtiZDM6bnMzOzchYmQlVTYley4oMitCIy9mRV9lVCQyLFllP1UpKlNJUEZ+RkosciVqISY1Nkk6NHxkeUAobW95TVJ+RngzT2JvWzpZdGJMVkpkczMsQj5TOHYsTEs6RFV+PXgxaDNQZClpVG89LXwhIT1LbHNDTEosJVFpW1B2IVNvTnVWfUFea3hpN1FKL0VNIyVSaHwtPSQkUCVoazBEdkAhTzJhOUVpXWgqXWNMZl07Sno1WGV1cG5Mb3JqL082Zkt9aTBgaip8W35kYVRpJU5qS2UyPWZVWD9tamw6XT5zXjNlXTxUMjUyd1FTTnF9WihnJUpaMjUpcE9YWyNXMWxsVD1tYzBna3lZIzVEcSBYI3EtdyFDUWE/djZNaUEvbXBGSSR8TH07SSpiYn1HIE16XzF3O2BwIGVOYiZTK3tkUEdJLiNRNyZnTDJMUDFWMmdiK3lEbyA7eHY2Pi0pX0BdO3ctKnV6NW9DUEIkWDJ0UUEzd0htST9vYE9NOyk8N0J7Mn41K18wKHhdLjkkXU01dFpPeGFxPzk5YiRwR0Y2Tk1aWmZDLU8gfW8uX0lzdU5TJS9mS2o1LyRhKCBUZV5yay4oL318OnZhcEBYQ3kqZyBbVTR9UHR7eFd6dl5sfjA4UVhrVVN4UlBhMGJSNzkhLHl2ZFt8YFlpPW9jXXx3OUBkcS8hd18lPj9+TT4gMy46JiV0fkckLTUgT2dSZXVJV2wjNDo2eGBnIVUxJXB+fTY1Qjh7Wj1UcU0uNDNaYTNHdC5QSVgwfT5BKnBnaG5rMSZBLDRyUW4lc1FvdjtDdDhuI0ZJZD8vWHZRKz84U1JmcTAsNWddeEo2JiA5ZHZQd0tnQklYSTNVKGxIW2QpJFlQJSB8ZGgxR0g0Q14rVCFMM2B6M0s+L24tfl5+JT9MfTJZT15eTndDOjJWbEhtSW5pdlh1Mix8LmhERmsmXXI5TiE3RUQzYzFoak09bmxdMHFKJUx5aW5Ja3VsIUdtK1F0QlB4Zm1Gb316UHZLJnY7PnVdazU+RCtaXl0gOWsxZ3pWcjsyQXVQaW5AXzdzaVE9eGc3WmlnUTM/SGUtWyhjK2szVFNZRXo2O3E2OXZvVEd9bjdnKDFzYHhyQldBb2M0aClWYmZhSUtqQXxrTlM5QiNvOHg0TDQjM0Z6PCRWOW9hdVBkb3BQITVoOEEhMnByZ20+eDdZSUVJXTZzOWpYJiokTVZ4cGZ6SyA7OiVfR1ZYTC5lKk5KP1d2RyogU0Ipd0s2TnhqWypCQ2t6ayV6ICQlclh7SjYhK0xAeSZ2bFZWIG59TlNbfkdAeCV3Oz5JTUk9KlgxezJdIHFMQHkkZ28pR10gKzlkOzN0IC5QRmRjeGdXOGBbazdyWj9GLThqLV1YMFNnbD9yd2kkMThQcnk5e3lfNztFIFM7WG80LSw1cmpPdCg/el1GWGVqO05vdDs9NWxVZnRJLjFyKHkqejJjRSF2fFtFXjB3S1EhLVhyPzc9JWghKHNWWkpMUnhxIXh6YjlUZVlCSFVuKFsgTF4lKUMxbSxBM2x6fik0SEtoYHBJPTQlYFFea0BWNEotP3puLX5vMWJDMDEsS1d2MUVKTzYxR05qaHN2WX5eeWV7TFZ1ZWcuUzw3SD1ocDxLdyg6JWU9aWdUZytmKWI+fEBtNU1FSXBDNS9AUnkmXWs6S15OVm5TUztzL1Uva0c0ZEBaJXQoTCg1dDhObSlhKFZbenV+WXwpfVRoTyZxTEB9VnMzSUl9V1peIDpnKWMrYHAzPENzUmReMV1FaXJSR3diLFZZa0kuMCtldUZ3WSNVTCEtNnp5IzFEfGZWMXVwVTpdLE99Py00V2ErKEMobE1wP0ZgRSsyUC1SUy4ycmxlRHhRcCt2Ym58SCFnQHhZOlgqRGVNOVNoQnRqUmBGTzgtcUZtKHtncz5uQixhWCVkQnsuSkcxVm1OIyp0KyUjcG9NM1FxcGshPiFINm9zTj53U3IqJlgrPTI3NzA1P1I1QyplLmZlVHU5K1dJLipwZzksRG5YaS5fUCREOklJYVdsUEFyeT4vaiR4UUMwMyF3YEVsODxzeDs1ITg3QlBgcSB2Yy5oang9Z1VGQC04fCpkTyRVWmZGfmlvIHg/SGVnMCZxSCUsRW9JNn4pdjN7IUptLWx0LWJGQT5SZ2c4WWh3IHxWRERgOHJsWGxSb295Z1g3eC5LfltnYn0wXXE/T3R0I21KZGhXNSt+djhCUllSTjR4LE4seCQlTVU5VCk5ZltqSkNjeWRCQD9iX3h2ejs3IF96PTp8Oi9jc1lPXWI7RVJKNFhyZ1BWWHV+PzB1ZHVnVmcxMyM7QG8rcTZ6MXBuPC8lRnFXRVteIS1WaTs3UzcrSHl8eSk9cEQsTjRQK0FUVk1+SkQ7U017MmJ9S09lYjEpR1lGdCljcCVQP29OZzAkd18vJW44Pn02cixQXzJSWytQMXYwSlB3a0FMTzNCcy1dfFBdL3smV1ZYblNbLigwQ0lDW2dMLWZXQDNVUnY7WFQ4ayAqR2BEaTx0P2Z8LzJ0U2lmTmVlRDcqblA2cTtNY08lLzdyNHE5elI1TH0xN3BkbSZhQip1aU9TLC1VLkxkPTIuVXxNL2lPJGoobHd5bmF9OGxBIDIkcnd0YjN3Kk9DKEhfKGVBLHdHOlpCU3BWUG4xdVNFNz88PCMyYTxRfXlkdzZmPihuI2dLfGB5S0IxWSVdRlM/SEdQRlcvJDg+UTFhSSVhU0I5IHtRJihSRTpyYk04QzBSS2ZjZ0tPb0dCSWkxICggeTx1c2ZAZkMrQks/LlJTcUdIWmV8ZmElX1llMWdPMjQhI318Ykl4RFFbNmxXSmVtcUZLZH5XJHRBakFVOH14dlhtc2VTTi1UVW9VNXl+PkJwczVObGVfN30vNntNQjBIRkRCWkFCI01jdl1FPTxac0RMIyhKSUw6eTlCI2laT0RsengwfXhkRVl+aUJGTF4xVHFOZTAuPSNeK0QxaXFffCE6SF1ZWEk3R2BPPisoLkZ2OnEvXVdzVll6OSZmcmMvM1RiISAqdmYpTUkzTlN6SyxHLlB6LEQ0LE55b1JqZDE4am84N0c1KWB2IHQ0RG5OME4wWWZSamcoLl5HPlIoWkA9VERKPDZTJVZWVWtgYWJ+IzZgNFlKTn4ofntzKnRHL1d7PlN1NzpBRyF1SzZGfV8/JFQ3VXsuO147ODtnfkNqQlt0U2ZvfE0hLz1jO3hMOXlgQCxVMSU/d1djVX44cUUrRDFDWmtqUjZJVTNINHVfWEl6dXt0IUEjaXFvQD0+YldhZXRKTWBCK05eQFlMMy9zclZZIFhqfXdZdSBScFV8b0MuVCM9SDsgPFgubF48STxBe3RSOEZeW1hdbkN2bzUtZllyT3AjdWQgOiZiaVpVUUpJPyZxeFE5ME5pS2ddN0FwZzZwJk09R0goZE5bUlZGWFRgPyxOe2tYMkI3ZmE4O3N4Qy1TZm9nM1VbP0g+S1JdWl9HL2w5Ly1iL2s9OSUkbHlAQFohKjFYJXJlflE1N01VQFI/dVZMUChOMyNYbG5YZj02Wi8rPntKRDY/TW95OURHSERuXltsNGVmRk9CRkNKKSBXN3dUellqW3dyNkl7fTwubHBLZlZSentxdW8geS54YkJ6OkgvaHBRMjJbe0hSfXh2WkZaZ1UxLHcrNEM9Uj9NJV97NXxqaXI3THgydCo6TFlEK0ZhPyMuMDZAWXVTS0okSklbREloZT1YKUt2N2R6NWpjIzo0cXh6N0tmcSoqOCFFcjJ7Q3s1MCpqJWd2XmBKamkucGlndCQsQGZwQUNZQTBbLiAkOEV9TjhCRG4qRnBlWCpVMi06cS01dU1+WXlaKnElWG9jNmc/eUspXzlzKi1VKkhJPzFjJjlXaEUkZj0qMHFBWWwsUmpeQz1YQT0hMShpVi1PXlM/ailxcE47bC9IfH13Xyp5ViM0dHV3bT0/fkldZUdtSSYoLH5POTh4WHs5dzxvTncxRWRBIXJhJmhpa1dwTyNdQ3g8WldvUD5AKz5rbTk9aTJ8fjhMaiNmZFJscEs8SzAjbix4WTE6WnFTMTEoMkdqMUx3TnJUICpqW3FzakNjYTBsIDFgUDRvLH5MXSVQKDpPKStuKGtFJUFoe0koMXErQSxqemVQczwvM0Z3S2cgYkwleFlRKW5fVVorWmN8YFN6VnAmMFtJVSo9ODZsLW0zYlZ6MSsxXzppZ2FFXzdxSCwwUEBZWzZlT3cuMCFKcDw2Q0tTfE5WZHM6JVQ4Q3FyNiBfSTogNHtyKFtrVlR9Xj9oKjN3dDwhdCpXcEdieFUsM002UyNrWSpUTDVXR29dajUsYWY0fDl+X18paGM9Y3M2K30vV2E9QU1tfn5DUk4pPVAjSlJ7UUlzMUh6W2VOWWA2U09ZMSMhflN1UkhKeW9qYTQ3PTBCSnldMXc1QWpCY3tBcC1aPy8mUDVzdUtzdWxgVVl3S25jeD8yM1MvbjpGWVp8fjxlWXhPSSlUOi9EKEcsTG8lODkkTGdfLWVZLTtRZi0qPCshSHBzZ3MzZFR6a3hCci9VRDdvLSV3KjhnTHBWTS1wZFhfMC5LeDZnZn1UayxyJUt4UHtCdW4way1Re286IGNWVmI9M281Si4ufU9MZl95REdAcFRgTnJ0VXNEbDtUd31hVyRGPWY0a0tHdnBHL1F1JDBuTlQ=";
    }
}
