<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerIryo extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const WAIT_TIMEOUT = 10;

    private $upcomingResponseData;
    private $previousResponseData;
    private $cancelledResponseData;
    private $stationsResponseData;
    private $productsResponseData;
    private $specialResponseData;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->setProxyGoProxies();

        $this->useChromePuppeteer();
//        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;

        /*
        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->setKeepProfile(true);
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        */

        /*
        $this->seleniumOptions->recordRequests = true;
        */
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL('https://iryo.eu/es/yo', [], 20);

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://auth.iryo.eu/auth/realms/ilsa/protocol/openid-connect/auth?client_id=b2c&redirect_uri=https%3A%2F%2Firyo.eu&state=aec2540d-c410-45f1-8387-3b026ea23bdb&response_mode=query&response_type=code&scope=openid&nonce=a1ad5f07-821e-448a-954c-b51f19d51d91&ui_locales=en-GB&code_challenge=tEX6qY2KEdmtD7vvKSEfk1dvWYYiLtW6k0tl_3f2bDI&code_challenge_method=S256');
//        $this->http->GetURL('https://iryo.eu/es/home');

        $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'desktop-header__user')] | //input[@id = 'username'] | //input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe"), self::WAIT_TIMEOUT);

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $this->saveResponse();
        }

//        if ($loginFormBtn = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'desktop-header__user')]"), self::WAIT_TIMEOUT)) {
//            $loginFormBtn->click();
//        }

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="username"]'), self::WAIT_TIMEOUT);
        $pass = $this->waitForElement(WebDriverBy::xpath('//input[@id="password"]'), 0);
        $rememberMe = $this->waitForElement(WebDriverBy::xpath('//label[@for="rememberMe"]'), 0);
        $loginButton = $this->waitForElement(WebDriverBy::xpath('//input[@id="kc-login"]'), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$rememberMe || !$loginButton) {
            $this->logger->error("Failed to find form fields");

            if ($this->loginSuccessful()) {
                return true;
            }

            return $this->checkErrors();
        }
        $login->clear();
        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $rememberMeChecked = $this->driver->executeScript("document.getElementById('rememberMe').checked");

        if (!$rememberMeChecked) {
            $rememberMe->click();
        }
        $loginButton->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "user-avatar__initials")] | //div[@id="input-error"] | //input[@value = \'Verify you are human\'] | //div[@id = \'turnstile-wrapper\']//iframe'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $this->saveResponse();
        }

        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[@id="input-error"]')) {
            $this->logger->error($message);

            if (strstr($message, 'Invalid username or password')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return $this->checkErrors();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://iryo.eu/es/account/user');
        $this->waitForElement(WebDriverBy::xpath('//div[@class="ilsa-account-user__name-summary"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if (!$this->http->FindSingleNode('//div[@class="ilsa-account-user__name-summary"]', null, true)) {
            $this->logger->error("Wrong page loaded. Retry needed");

            throw new CheckRetryNeededException(3, 3);
        }

        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[@class="ilsa-account-user__name-summary"]')));

        $this->http->GetURL('https://iryo-clubyo.loyaltysp.es/home/myIryos');

        $this->waitForElement(WebDriverBy::xpath('//img[contains(@class, "img-target")]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        // Balance - iryos
        $iryos = $this->http->FindSingleNode('//div[contains(@class, "data-img")]/div[3]', null, true, "/(.*)\siryos/");
        $this->SetBalance(PriceHelper::parse($iryos));

        // Number
        $this->SetProperty('Number', $this->http->FindSingleNode('//div[contains(@class, "data-img")]/div[2]'));
        // Elite level
        $this->SetProperty('Level', $this->http->FindSingleNode('//img[contains(@class, "img-target")]/@alt'));

        $this->SetProperty('SpendToNextLevel', $this->http->FindSingleNode('//div[contains(@class, "nextlevel")]/small/text()', null, true, '/(?:You need|Te faltan) ([^A-z\s]+)/'));
        $this->SetProperty('TripsToNextLevel', $this->http->FindSingleNode('//div[contains(@class, "nextlevel")]/small/text()', null, true, '/([0-9]+) (?:trips|viajes)/'));
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);

        $this->http->GetURL('https://iryo.eu/es/my-bookings');
        $this->waitForElement(WebDriverBy::xpath('//div[@class="ilsa-my-bookings"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $this->saveResponse();
        }

        $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "in progress") or contains(text(), "en curso")]/../..'), 5)->click();
        $noUpcomingItineraries = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "ilsa-tabs-tab") and not(@hidden)]//*[contains(text(), "NO RESERVATIONS") or contains(text(), "NO HAY RESERVAS")]'), 5);
        $this->saveResponse();
        $this->logger->info('no upcoming itineraries: ' . (int) isset($noUpcomingItineraries));

        $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "last") or contains(text(), "pasadas")]/../..'), 5)->click();
        $noPreviousItineraries = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "ilsa-tabs-tab") and not(@hidden)]//*[contains(text(), "NO RESERVATIONS") or contains(text(), "NO HAY RESERVAS")]'), 5);
        $this->saveResponse();
        $this->logger->info('no previous itineraries: ' . (int) isset($noPreviousItineraries));

        $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "cancel") or contains(text(), "canceladas")]/../..'), 5)->click();
        $noCancelledItineraries = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "ilsa-tabs-tab") and not(@hidden)]//*[contains(text(), "NO RESERVATIONS") or contains(text(), "NO HAY RESERVAS")]'), 5);
        $this->saveResponse();
        $this->logger->info('no cancelled itineraries: ' . (int) isset($noCancelledItineraries));
        $this->logger->info('ParsePastIts:' . (int) $this->ParsePastIts);

        // check for the no its
        $seemsNoIts = $noUpcomingItineraries && $noPreviousItineraries;
        $this->logger->info('Seems no itineraries: ' . (int) $seemsNoIts);

        if ($seemsNoIts) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        return [];// TODO: refs #24772

        $this->getRecordedRequests();

        if (isset($this->upcomingResponseData) && !isset($noUpcomingItineraries)) {
            $upcomingItinerariesResponse = $this->http->JsonLog($this->upcomingResponseData);

            if (
                isset($upcomingItinerariesResponse->pageSize, $upcomingItinerariesResponse->total)
                && $upcomingItinerariesResponse->pageSize < $upcomingItinerariesResponse->total
            ) {
                $this->sendNotification('refs #22415 need to add a pagination mechanism // IZ');
            }

            $upcomingItinerariesData = $upcomingItinerariesResponse->data ?? null;

            if (isset($upcomingItinerariesData) && count($upcomingItinerariesData) > 0) {
                $this->logger->info("upcoming itineraries", ['Header' => 3]);

                foreach ($upcomingItinerariesData as $itinerary) {
                    $this->parseItinerary($itinerary);
                }
            }
        }

        if (isset($this->previousResponseData) && !isset($noPreviousItineraries) && $this->ParsePastIts) {
            $previousItinerariesResponse = $this->http->JsonLog($this->previousResponseData);

            if (
                isset($previousItinerariesResponse->pageSize, $previousItinerariesResponse->total)
                && $previousItinerariesResponse->pageSize < $previousItinerariesResponse->total
            ) {
                $this->sendNotification('refs #22415 need to add a pagination mechanism // IZ');
            }

            $previousItinerariesData = $previousItinerariesResponse->data ?? null;

            if (isset($previousItinerariesData) && count($previousItinerariesData) > 0) {
                $this->logger->info("previous itineraries", ['Header' => 3]);

                foreach ($previousItinerariesData as $itinerary) {
                    $this->parseItinerary($itinerary);
                }
            }
        }

        if (isset($this->cancelledResponseData) && !$this->itinerariesMaster->getNoItineraries() && !isset($noCancelledItineraries)) {
            $cancelledItinerariesResponse = $this->http->JsonLog($this->cancelledResponseData);

            if (
                isset($cancelledItinerariesResponse->pageSize, $cancelledItinerariesResponse->total)
                && $cancelledItinerariesResponse->pageSize < $cancelledItinerariesResponse->total
            ) {
                $this->sendNotification('refs #22415 need to add a pagination mechanism // IZ');
            }

            $cancelledItinerariesData = $cancelledItinerariesResponse->data ?? null;

            if (isset($cancelledItinerariesData) && count($cancelledItinerariesData) > 0) {
                $this->logger->info("cancelled itineraries", ['Header' => 3]);

                foreach ($cancelledItinerariesData as $itinerary) {
                    $this->parseItinerary($itinerary);
                }
            }
        }

        return [];
    }

    private function parseItinerary($itinerary)
    {
        $confNo = $itinerary->bookingNumber;
        $this->logger->info("Parse Itinerary #{$confNo}", ['Header' => 3]);

        $t = $this->itinerariesMaster->createTrain();
        $t->addConfirmationNumber($confNo, 'Booking');
        $t->general()->status($itinerary->status);

        if ($itinerary->status == 'CANCELLED') {
            $t->general()->cancelled();
        }

        $t->obtainPrice()->setCurrencyCode($itinerary->currency);
        $t->obtainPrice()->setTotal($itinerary->totalPrice);

        foreach ($itinerary->passengers as $passenger) {
            $name = $passenger->name ?? '';
            $firstSurname = $passenger->firstSurname ?? '';
            $secSurname = $passenger->secSurname ?? '';

            $t->general()->traveller(beautifulName("{$name} {$firstSurname} {$secSurname}"), true);
        }

        foreach ($itinerary->bookingTariffSegments as $segment) {
            $s = $t->addSegment();

            $s->extra()->noNumber();

            $uicDepartureStation = $this->findStationData($segment->uicDepartureStation);
            $uicArrivalStation = $this->findStationData($segment->uicArrivalStation);

            if (isset($uicDepartureStation, $uicArrivalStation)) {
                $s->departure()->code($uicDepartureStation->shortCode);
                $s->departure()->name($uicDepartureStation->name);

                $s->arrival()->code($uicArrivalStation->shortCode);
                $s->arrival()->name($uicArrivalStation->name);
            } else {
                $this->sendNotification('refs #22415 need to check findStationData // IZ');
            }

            if (isset($segment->bookingJourneySegments) && count($segment->bookingJourneySegments) > 1) {
                $this->sendNotification('refs #22415 need to check bookingJourneySegments // IZ');
            }

            $s->departure()->date2($segment->bookingJourneySegments[0]->departureDateTime);
            $s->arrival()->date2($segment->bookingJourneySegments[0]->arrivalDateTime);

            foreach ($itinerary->passengers as $passenger) {
                foreach ($segment->products as $product) {
                    if ($passenger->id == $product->passengerId) {
                        $productData = $this->findProductData($product->code);

                        if (isset($product->type) && $product->type == 'ST') {
                            $s->extra()->car($product->seat->carriage);
                            $s->extra()->seat($product->seat->number, false, false);
                            $s->extra()->cabin($productData->name ?? null);
                        }

                        if (isset($product->type) && $product->type == 'ME') {
                            $s->extra()->meal($productData->name ?? null);
                        }

                        $t->addTicketNumber($product->ticketNumber, false);

                        if (isset($product->specialRequests) && count($product->specialRequests) > 0) {
                            foreach ($product->specialRequests as $specialRequest) {
                                $specialData = $this->findSpecialData($specialRequest->code);

                                if ($specialRequest->type == 'ME') {
                                    $s->extra()->meal($specialData->name ?? null);
                                } else {
                                    $this->sendNotification('refs #22415 need to check specialRequests // IZ');
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $logoutItemXpath = '//div[contains(@data-qa, "user-menu-logout")] | //div[contains(@class, "b2c-header__user-container-auth--name")]';
        $this->waitForElement(WebDriverBy::xpath($logoutItemXpath), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $this->waitForElement(WebDriverBy::xpath($logoutItemXpath), self::WAIT_TIMEOUT);
            $this->saveResponse();
        }

        if ($this->http->FindSingleNode($logoutItemXpath)) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function getRecordedRequests()
    {
        $this->logger->notice(__METHOD__);

        try {
            $requests = $this->http->driver->browserCommunicator->getRecordedRequests();
        } catch (AwardWallet\Common\Selenium\BrowserCommunicatorException $e) {
            $this->logger->error("BrowserCommunicatorException: " . $e->getMessage(), ['HtmlEncode' => true]);

            $requests = [];
        }

        foreach ($requests as $xhr) {
            if (strstr($xhr->request->getUri(), 'https://api.iryo.eu/b2c/manage/bookings?target=NEXT&status=CONFIRMED') && !isset($this->upcomingResponseData)) {
                $this->upcomingResponseData = json_encode($xhr->response->getBody());
                $this->logger->debug('Catched upcoming itineraries request');
            }

            if (strstr($xhr->request->getUri(), 'https://api.iryo.eu/b2c/manage/bookings?target=HISTORICAL&status=CONFIRMED') && !isset($this->previousResponseData)) {
                $this->previousResponseData = json_encode($xhr->response->getBody());
                $this->logger->debug('Catched previous itineraries request');
            }

            if (strstr($xhr->request->getUri(), 'https://api.iryo.eu/b2c/manage/bookings?target=ALL&status=CANCELLED') && !isset($this->cancelledResponseData)) {
                $this->cancelledResponseData = json_encode($xhr->response->getBody());
                $this->logger->debug('Catched cancelled itineraries request');
            }

            if (strstr($xhr->request->getUri(), 'https://api.iryo.eu/b2c/support/stations') && !isset($this->stationsResponseData)) {
                $stationsResponseData = $this->http->JsonLog(json_encode($xhr->response->getBody()));
                $this->stationsResponseData = $stationsResponseData->data;
                $this->logger->debug('Catched stations data request');
            }

            if (strstr($xhr->request->getUri(), 'https://api.iryo.eu/b2c/support/products') && !isset($this->productsResponseData)) {
                $productsResponseData = $this->http->JsonLog(json_encode($xhr->response->getBody()));
                $this->productsResponseData = $productsResponseData->data;
                $this->logger->debug('Catched products data request');
            }

            if (strstr($xhr->request->getUri(), 'https://api.iryo.eu/b2c/support/special-requests') && !isset($this->specialResponseData)) {
                $specialResponseData = $this->http->JsonLog(json_encode($xhr->response->getBody()));
                $this->specialResponseData = $specialResponseData->data;
                $this->logger->debug('Catched special data request');
            }
        }// foreach ($requests as $xhr)
    }

    private function findStationData($stationCode)
    {
        foreach ($this->stationsResponseData as $station) {
            if ($station->uicStationCode == $stationCode) {
                return $station;
            }
        }
    }

    private function findProductData($productCode)
    {
        foreach ($this->productsResponseData as $product) {
            if ($product->code == $productCode) {
                return $product;
            }
        }
    }

    private function findSpecialData($specialCode)
    {
        foreach ($this->specialResponseData as $special) {
            if ($special->code == $specialCode) {
                return $special;
            }
        }
    }
}
