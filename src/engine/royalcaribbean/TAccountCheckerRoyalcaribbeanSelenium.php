<?php

class TAccountCheckerRoyalcaribbeanSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    /**
     * @var HttpBrowser
     */
    public $browser;

    private $converter;
    private $appKey = "hyNNqIPHHzaLzVpcICPdAdbFV8yvTsAm";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useChromium();
        $this->useCache();
        $this->disableImages();
        $this->keepCookies(false);
    }

//    function IsLoggedIn() {
//        $this->http->GetURL("https://www.royalcaribbean.com/account/");
//        $logout = $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "loyalty-program__number")]'), 0);
//        $this->saveResponse();
//        if ($logout)
//            return true;
//
//        return false;
//    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://www.royalcaribbean.com/account/");

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "email"]'), 10);
        $pass = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);

        if (!$login || !$pass) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return $this->checkErrors();
        }
        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $stay = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "mdc-checkbox--upgraded")]'), 0);

        if ($stay) {
            $stay->click();
        }

        $sbm = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "btn-signin")]'), 0);

        if (!$sbm) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return false;
        }
        $sbm->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'The Royal Caribbean website and Reservation system are')]"), 0)) {
            throw new CheckException(ucfirst(strtolower($message->getText())), ACCOUNT_PROVIDER_ERROR);
        }
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        $sleep = 20;
        $startTime = time();

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
            $logout = $this->waitForElement(WebDriverBy::xpath('//a[@interaction="loyalty number"] | //input[@value="Add"]/preceding-sibling::app-control//span[contains(text(), "Crown & Anchor® Society")]'), 0);
            $this->saveResponse();

            if ($logout) {
                return true;
            }
            // Invalid email and password combination.
            if ($message = $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "input-error") and contains(., "Invalid email and password combination")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            // Please try again. Make sure you enter the email and password associated with your account.
            if ($message = $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "input-error") and contains(., "Please try again. Make sure you enter the email and password associated with your account.")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            // We're unable to complete your request, so please try again later.
            if ($message = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "We\'re unable to complete your request, so please try again later.")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            // Something's not right, so give it another try.
            if ($message = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Something\'s not right, so give it another try.")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            /*
             * Pardon the interruption (Let's get this right)
             * Our enhanced security requires a one-time account validation.
             */
            if ($this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Our enhanced security requires a one-time account validation.")]'), 0)
                || $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Please complete the fields below for this one-time account security validation.")]'), 0)) {
                $this->throwProfileUpdateMessageException();
            }

            $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "banner__text")]'), 0);

            sleep(1);
            $this->saveResponse();
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h2[contains(@class, "hero-header__user-name")]')));
        // Crown & Anchor® Society
        $this->SetProperty("Number", $this->http->FindSingleNode('//a[@interaction="loyalty number"]'));
        // Level
        $this->SetProperty("Level", $this->http->FindSingleNode('//a[@interaction="loyalty tier"]'));
        // Balance - Pts
        $balance = $this->http->FindSingleNode('//a[@interaction="loyalty points"]');
        $this->SetBalance($balance);
        // for Elite levels
        $this->SetProperty("CruisePoints", $balance);

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (($this->waitForElement(WebDriverBy::xpath('//input[@value="Add"]/preceding-sibling::app-control//span[contains(text(), "Crown & Anchor® Society")]'), 0) || $this->http->FindSingleNode("//span[contains(text(), 'We were unable to locate this member number, so please try again.')]"))
                && !empty($this->Properties['Name'])) {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // Full Name
//        $this->http->GetURL("https://www.royalcaribbean.com/account/settings/personal");
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            if ($cookie['name'] == 'loyaltyData') {
                $this->logger->debug("loyaltyData -> {$cookie['value']}");
                $name = CleanXMLValue($this->http->FindPreg("/firstName=([^\|]+)/", false, $cookie['value'])
                    . ' ' . $this->http->FindPreg("/lastName=([^\|]+)/", false, $cookie['value']));

                if (strlen($name) > 2) {
                    $this->SetProperty("Name", beautifulName($name));
                }
            }
        }
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->curl = true;

        $this->browser->LogHeaders = true;
        $this->browser->setProxyParams($this->http->getProxyParams());
        $this->browser->GetURL($this->http->currentUrl());
    }

    public function ParseItineraries()
    {
        $this->converter = new CruiseSegmentsConverter();
        $result = [];
        $bookings = $this->http->FindNodes("//p[contains(@class, 'reservation-card__content-number')]");
        $this->logger->debug("Total " . count($bookings) . " cruises were found");

        foreach ($bookings as $booking) {
            $this->logger->info('Parse itinerary #' . $booking, ['Header' => 3]);
            $planner = $this->waitForElement(WebDriverBy::xpath('//div[p[contains(@class, "reservation-card__content-number") and contains(text(), "' . $booking . '")]]/following-sibling::div[1]//a[contains(., "Plan your cruise")]'), 0);

            if (!$planner) {
                $this->logger->error("something went wrong");

                continue;
            }// if (!$planner)
            $passengers = $this->http->FindSingleNode('//p[contains(@class, "reservation-card__content-number") and contains(text(), "' . $booking . '")]/following-sibling::p[contains(@class, "reservation-card-guests")]');
            // multiple cruises workaround
//            $planner->click();
            $this->driver->executeScript("$('p:contains(\"{$booking}\")').parents('article.reservation-card').find('a:contains(\"Plan your cruise\")').get(0).click();");
            $this->saveResponse();
            $shipName = $this->waitForElement(WebDriverBy::xpath('//li[contains(text(), "Sailing On")]/strong'), 10);
            $this->saveResponse();

            if ($shipName) {
                $result[] = $this->ParseItinerary($booking, $passengers);
            }

            $this->http->GetURL("https://secure.royalcaribbean.com/cruiseplanner/logout");
            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");

            if ($currentUrl == 'https://secure.royalcaribbean.com/asr/login.do') {
                $this->logger->notice("Force redirect to 'My Account'");
                $this->http->GetURL("https://www.royalcaribbean.com/account/");
            }// if ($currentUrl == 'https://secure.royalcaribbean.com/asr/login.do')
            $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "reservation-card__content-number")]'), 10);
            $this->saveResponse();
        }// foreach ($bookings as $booking)

        return $result;
    }

    public function ParseItinerary($booking, $passengers)
    {
        $result = [];
//        $this->http->GetURL("https://secure.royalcaribbean.com/cruiseplanner/login?cruiseBookingId={$booking->bookingId}&lastname={$booking->lastName}&shipCode={$booking->shipCode}&sailDate=".preg_replace("/(\d{4})(\d{2})(\d{2})/", "$1-$2-$3", $booking->sailDate)."&brand=R");

        $result['Kind'] = 'T';
        $result['TripCategory'] = TRIP_CATEGORY_CRUISE;

        $result['RecordLocator'] = $booking;
        $result['ShipName'] = $this->http->FindSingleNode('//li[contains(text(), "Sailing On")]/strong');
        $result['CruiseName'] = $this->http->FindSingleNode('//li[contains(text(), "Your")]/strong');

        $result['Passengers'] = array_map(function ($item) {
            return beautifulName(trim($item));
        }, array_unique(explode(',', $passengers)));
//        foreach ($booking->passengers as $passenger)
//            $result['Passengers'][] = beautifulName($passenger->firstName." ".$passenger->lastName);
//
//        $result['Deck'] = $this->http->FindSingleNode('//td[strong[contains(text(), "Stateroom:")]]/a[1]', null, true, '/(.+),/ims');
//        $result['RoomClass'] = $booking->stateroomType;
//        $result['RoomNumber'] = $booking->stateroomNumber;

        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/json; charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->parseWithCurl();
        $this->browser->PostURL("https://secure.royalcaribbean.com/cruiseplanner/api/mySchedule", "{}", $headers);
        $response = $this->browser->JsonLog(null, false);

        if (!isset($response->tours[0]->days)) {
            $this->logger->error("something went wrong");

            return [];
        }
        $days = $response->tours[0]->days;
        $this->logger->debug("Total " . count($days) . " days were found");
        $cruise = [];
        $passengers = [];

        foreach ($days as $day) {
            $date = $day->dateText;

            if (!empty($day->events)) {
                foreach ($day->events as $event) {
                    $segment = [];

                    if ($event->eventType != 'cruiseType') {
                        $this->logger->debug("skip not cruiseType");

                        if (!empty($event->guests)) {
                            foreach ($event->guests as $guest) {
                                $passengers[] = $guest->name;
                            }
                        }

                        continue;
                    }
                    $segment['Port'] = $event->location;

                    if ($event->eventTitle == 'Departure') {
                        if ($time = $event->startTimeText) {
                            $segment['DepDate'] = strtotime("$time $date");
                        }
                    } elseif ($event->eventTitle == 'Arrival') {
                        if ($time = $event->startTimeText) {
                            $segment['ArrDate'] = strtotime("$time $date");
                        }
                    } else {
                        $this->logger->error("Wrong eventTitle: {$event->eventTitle}");
                    }
                    $cruise[] = $segment;
                }// foreach ($day->events as $event)
            }// if (!empty($day->events))
        }// foreach ($days as $day)
//        $this->logger->debug(var_export($cruise, true), ['pre' => true]);
        $result['TripSegments'] = $this->converter->Convert($cruise);

        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Access-Token" => $this->http->getCookieByName("accessToken"),
            "AppKey"       => $this->appKey,
        ];
        $this->browser->GetURL("https://aws-prd.api.rccl.com/v1/guestAccounts/edocs/campaignMetadata?bookingIds={$booking}", $headers);
        $response = $this->browser->JsonLog(null, false);

        if (isset($response->getCampaignMetadataResponse->getCampaignMetadataResult->campaignTransactions->campaignTransaction[0]->reservationReference)) {
            $reservationReference = $response->getCampaignMetadataResponse->getCampaignMetadataResult->campaignTransactions->campaignTransaction[0]->reservationReference;

            $result['Deck'] = $reservationReference->stateroomDeck;
//            $result['RoomClass'] = $booking->stateroomType;
            $result['RoomNumber'] = $reservationReference->stateroomNumber;

//            $this->browser->GetURL("https://secure.royalcaribbean.com/cruiseplanner/login?cruiseBookingId={$booking}&lastname=".$this->http->FindPreg("/([^\,]+)/", $reservationReference->passengerLastName)."&shipCode={$reservationReference->shipCode}&sailDate={$reservationReference->sailDateDisplay}&brand={$reservationReference->brandCode}");
//            $response = $this->browser->JsonLog();
        }

        if (!empty($passengers)) {
            $result['Passengers'] = array_map(function ($item) {
                return beautifulName($item);
            }, array_unique($passengers));
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }
}
