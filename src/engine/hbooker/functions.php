<?php

// refs #8744

use AwardWallet\Common\Parsing\Html;
use AwardWallet\ItineraryArrays\Hotel;

class TAccountCheckerHbooker extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
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
        $this->selenium();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        // Invalid credentials
        if ($message = $this->http->FindSingleNode('//form[descendant::input[@id="input_email"]]/descendant::p[contains(@class,"Text__error")]')) {
            $this->logger->error($message);

            if (
                // The security of your data is our top priority. Therefore we have adapted the My HOTEL DE Login to the latest security standards. Please check your e-mail inbox and set a new password for your My HOTEL DE account.
            strstr($message, 'Please check your e-mail inbox and set a new password for your My HOTEL DE account.')
            || strstr($message, 'Please enter a valid e-mail address, e.g.john@example.com')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $name = $this->http->FindSingleNode('//div[contains(@class, "HeaderUserProfile__userInfo")]//span');
        $this->SetProperty("Name", beautifulName($name));

        if (!empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.hotel.info/user/reservations?language=en", [], 20);
        $this->http->RetryCount = 2;

        if (!$this->http->FindSingleNode('//span[normalize-space() = "No bookings have been found"]')) {
            $this->sendNotification("refs #20003: Reservations is not empty");
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.hotel.info/user/profile?language=en", [], 20);
        $this->http->RetryCount = 2;

        if (
            $this->http->FindSingleNode('//div[contains(@class,"PersonalDataOverview__data") and contains(normalize-space(),"' . $this->AccountFields['Login'] . '")]')
        ) {
            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL("https://www.hotel.info/user/profile?language=en");

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@id="input_email"]'), 10);
            $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@id="input_password"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('(//button[./span[text() = "Login now" or text() = "Log in"]])[1]'), 0);
            $this->saveToLogs($selenium);

            if (!$login || !$pass || !$button) {
                return $this->checkErrors();
            }

            $acceptAllCookies = $selenium->waitForElement(WebDriverBy::id('onetrust-accept-btn-handler'), 0);

            if ($acceptAllCookies) {
                $acceptAllCookies->click();
            }

            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('
            //div[contains(@class,"PersonalDataOverview__data") and contains(normalize-space(),"' . $this->AccountFields['Login'] . '")]
            | //form[descendant::input[@id="input_email"]]/descendant::p[contains(@class,"Text__error")]
            '), 10);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->saveToLogs($selenium);
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 1);
            }
        }

        return true;
    }

    /*  function ParseItineraries()
      {
          $result = [];
          $urls = [];
          if ($this->graphql == true) {
              $this->http->GetURL("https://www.hotel.info/user/reservations?language=en");
              $data = [
                  "operationName" => "ReservationsQuery",
                  "variables"     => [],
                  "query"         => "query ReservationsQuery {\n  bookingsByUserId {\n    bookingData {\n      ...bookingDataFragment\n      __typename\n    }\n    staticHotelData {\n      ...staticHotelDataFragment\n      __typename\n    }\n    offerData {\n      offers {\n        ...offerDataFragment\n        __typename\n      }\n      totalPriceCustomer {\n        amount\n        currencyCode\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment bookingDataFragment on BookingConfirmation {\n  bookingId\n  reservationDate\n  processKey\n  bookingStatus\n  __typename\n}\n\nfragment staticHotelDataFragment on Hotel {\n  hotelId\n  hotelName\n  hotelStars\n  address {\n    postalCode\n    street\n    city\n    country\n    __typename\n  }\n  pictures(limit: 1) {\n    width\n    height\n    url\n    __typename\n  }\n  __typename\n}\n\nfragment offerDataFragment on ReservableOffer {\n  offerKey\n  numberOfNights\n  numberOfPersons\n  numberOfRooms\n  dateOfArrival\n  dateOfDeparture\n  __typename\n}\n",
              ];
              $headers = [
                  "Accept"          => "* /*",
                  "Accept-Encoding" => "gzip, deflate, br",
                  "content-type"    => "application/json",
                  "X-Csrf-Token"    => $this->http->getCookieByName("nikita-X-Csrf-Token"),
              ];
              $this->http->PostURL("https://www.hotel.de/graphql", json_encode($data), $headers);
              $this->http->JsonLog();

              if (Html::cleanXMLValue($this->http->Response['body']) == '{"data":{"bookingsByUserId":[]}}') {
                  return $this->noItinerariesArr();
              }

              $this->sendNotification("itineraries were found");

              return [];
          }
          $this->http->GetURL("https://www.hotel.info/Customer/ReservationOverview.aspx?tabcewp=1&lng=EN");
          $data = [
              "ReservationNr"                  => "",
              "SelectedReservationState"       => "All",// OpenReservation
              "SelectedReservationPeriod"      => "All",
              "SoonestArrival"                 => date('n/j/Y', strtotime("-1 week")),
              "LatestArrival"                  => date('n/j/Y', strtotime("+1 week")),
              "SelectedCustomerBookingAgentNr" => -1,
              "Page"                           => "1",
              "SortCriteria"                   => "0",
              "IsDescending"                   => "true",
          ];
          $headers = [
              "X-CSRF-Token"     => $this->http->FindSingleNode("//div[@id = 'FilteroptionDiv']//input[@name = '__RequestVerificationToken']/@value"),
              "X-Requested-With" => "XMLHttpRequest",
              "Content-Type"     => "application/json; charset=utf-8",
          ];
          $this->http->PostURL("https://www.hotel.info/Customer/Profile/GetReservations?lng=EN", json_encode($data), $headers);
          $response = $this->http->JsonLog(null, 3, true);

          if ($this->http->FindPreg('/No bookings have been found/i')) {
              return $this->noItinerariesArr();
          }
          $this->sendNotification("hbooker - check reservation");

          $itineraries = ArrayVal($response['Result'], 'ModelList', []);
          $this->logger->debug("Total ".count($itineraries)." itineraries were found");
          foreach ($itineraries as $itinerary) {
              if ($itinerary['Status'] == 'Booked') {
                  $this->sendNotification("hbooker. New reservation was found: {$itinerary['Status']}");
              }
              $confNo = $itinerary['ReservationNr'];
              // todo: need to realize parsing past and future bookings
              if (strtolower($itinerary['Status']) == 'departed') {
                  $this->logger->notice("skip old itinerary #{$confNo}");
                  continue;
              }
              $this->logger->info(sprintf('Parse Itinerary #%s', $confNo), ['Header' => 3]);
              if (strtolower($itinerary['Status']) == 'cancelled') {
                  $result[] = [
                      'Kind' => 'R',
                      'ConfirmationNumber' => $confNo,
                      'Cancelled' => true,
                  ];
              }
          }// foreach ($itineraries as $itinerary)

          $nodes = $this->http->XPath->query("//div[@id = 'ReservationResult']//tr[contains(@class, 'navigation')]/following-sibling::tr[not(@class = 'normal')]");
//        $this->logger->debug("Total {$nodes->length} future itineraries were found");
          for ($i = 0; $i < $nodes->length; $i++) {
              $confNumber = $this->http->FindSingleNode("td[1]/a", $nodes->item($i));
              $link = $this->http->FindSingleNode("td[1]/a/@href", $nodes->item($i));
              $status = $this->http->FindSingleNode("td[8]", $nodes->item($i));
              // Parse Cancelled
              if (strtolower($status) == 'cancelled') {
                  $result[] = ['ConfirmationNumber' => $confNumber, 'Cancelled' => true, 'Kind' => 'R'];
              } else {
                  $urls[] = $link;
              }
          }// for ($i = 0; $i < $nodes->length; $i++)
          foreach ($urls as $url) {
              $this->http->GetURL($url);
              $result[] = $this->parseReservation();
          }// foreach ($urls as $url)

          return $result;
      }
      */

    /*
    function parseReservation()
    {
        $this->logger->notice(__METHOD__);
        /** @var Hotel $result * /
        $result = [];
        // Kind
        $result['Kind'] = "R";
        // ConfirmationNumber
        $result['ConfirmationNumber'] = $this->http->FindSingleNode("//span[@id = 'ReservationHeaderReservationNumber']");
        // Status
        $result['Status'] = $this->http->FindSingleNode("//font[b[contains(text(), 'Status')]]", null, true, "/Status\s*:\s*([^<]+)/ims");
        // ReservationDate
        $result['ReservationDate'] = $this->http->FindSingleNode("//font[b[contains(text(), 'Reservation Date')]]", null, true, "/Date\s*:\s*([^<]+)/ims");
        $this->http->Log("ReservationDate: {$result['ReservationDate']} / ".strtotime($result['ReservationDate']));
        $result['ReservationDate'] = strtotime($result['ReservationDate']);
        // HotelName
        $result['HotelName'] = $this->http->FindSingleNode("//td[b[contains(text(), 'Hotel name:')]]/following-sibling::td[1]");
        // DetailedAddress
        $street = $this->http->FindSingleNode("//td[contains(text(), 'Street:')]/following-sibling::td[1]");
        $cityName = $this->http->FindSingleNode("//td[contains(text(), 'Post Code/City:')]/following-sibling::td[1]", null, true, "/^\s*\d+\s*([^<\(]+)/ims");
        $country = $this->http->FindSingleNode("//td[contains(text(), 'Post Code/City:')]/following-sibling::td[1]", null, true,
            "/^\s*\d+\s*[^<\(]+\(([^\)]+)/ims");
        $postalCode = $this->http->FindSingleNode("//td[contains(text(), 'Post Code/City:')]/following-sibling::td[1]", null, true, "/^\s*(\d+)/ims");
        $result["DetailedAddress"] = [
            [
                "AddressLine" => $street,
                "CityName"    => $cityName,
                "PostalCode"  => $postalCode,
                "StateProv"   => '',
                "Country"     => $country,
            ],
        ];
        // Address
        $result['Address'] = $street.', '.$this->http->FindSingleNode("//td[contains(text(), 'Post Code/City:')]/following-sibling::td[1]");
        // Phone
        $result['Phone'] = $this->http->FindSingleNode("//td[contains(text(), 'Phone:')]/following-sibling::td[1]");
        // Fax
        $result['Fax'] = $this->http->FindSingleNode("//td[contains(text(), 'Fax:')]/following-sibling::td[1]");
        // CheckInDate
        $checkIn = $this->http->FindSingleNode("//td[b[contains(text(), 'Arrival:')]]/following-sibling::td[1]");
        $this->http->Log("CheckInDate: {$checkIn} / ".strtotime($checkIn));
        $result['CheckInDate'] = strtotime($checkIn);
        // CheckOutDate
        $checkOut = $this->http->FindSingleNode("//td[b[contains(text(), 'Departure:')]]/following-sibling::td[1]");
        $this->http->Log("CheckOutDate: {$checkOut} / ".strtotime($checkOut));
        $result['CheckOutDate'] = strtotime($checkOut);
        // Guests
        $result['Guests'] = $this->http->FindSingleNode("//td[b[contains(text(), 'Total no. of Persons:')]]/following-sibling::td[1]");
        // Rooms
        $result['Rooms'] = $this->http->FindSingleNode("//tr[td[b[contains(text(), 'No. of Rooms:')]]]/following-sibling::tr[1]/td[2]");
        // Total
        $result['Total'] = $this->http->FindSingleNode('//b[contains(text(), "Total price")]', null, true, "/:\s*([\d\.\,\s]+)/ims");
        if (!isset($result['Total'])) {
            $result['Total'] = $this->http->FindSingleNode("//tr[td[b[contains(text(), 'Total:')]]]/following-sibling::tr[1]/following-sibling::tr[last()]/td[last()]",
                null, true, "/([\d\.\,\s]+)/ims");
        }
        //$result['Total'] = $this->http->FindSingleNode("//tr[td[b[contains(text(), 'Total:')]]]/following-sibling::tr[1]/td[4]", null, true, "/([\d\.\,\s]+)/ims");

        // Currency
        $result['Currency'] = $this->http->FindSingleNode('//b[contains(text(), "Total price")]', null, true, "/[A-Z]{3}/ims");
        if (!isset($result['Currency'])) {
            $result['Currency'] = $this->http->FindSingleNode("//tr[td[b[contains(text(), 'Total:')]]]/following-sibling::tr[1]/following-sibling::tr[last()]/td[last()]",
                null, true, "/[A-Z]{3}/ims");
        }
        //$result['Currency'] = $this->http->FindSingleNode("//tr[td[b[contains(text(), 'Total:')]]]/following-sibling::tr[1]/td[4]", null, true, "/[A-Z]{3}/ims");

        // RateType
        $rateTypeNodes = $this->http->XPath->query("//tr[td[contains(text(), 'Rate Description:')]]/following-sibling::tr");
        for ($i = 0; $i < $rateTypeNodes->length; $i++) {
            $rateType = $this->http->FindSingleNode("td[2]", $rateTypeNodes->item($i));
            if (!$rateType) {
                break;
            }
            $result['RateType'] = isset($result['RateType']) ? $result['RateType'].', '.$rateType : $rateType;
        }
        // RoomTypeDescription
        $roomTypeDescriptionNodes = $this->http->XPath->query("//tr[td[contains(text(), 'Room Description:')]]/following-sibling::tr");
        for ($i = 0; $i < $roomTypeDescriptionNodes->length; $i++) {
            $roomTypeDescription = $this->http->FindSingleNode("td[2]", $roomTypeDescriptionNodes->item($i));
            if (!$roomTypeDescription) {
                break;
            }
            $result['RoomTypeDescription'] = isset($result['RoomTypeDescription']) ? $result['RoomTypeDescription'].', '.$roomTypeDescription : $roomTypeDescription;
        }
        // GuestNames
        $guestNamesNodes = $this->http->XPath->query("//tr[td[contains(text(), 'Details on the guest:')]]/following-sibling::tr[1]//tr");
        for ($i = 0; $i < $guestNamesNodes->length; $i++) {
            $result['GuestNames'][] =
                $this->http->FindSingleNode("td[2]", $guestNamesNodes->item($i), true, "/\:\s*([^<]+)/ims")
                .' '.$this->http->FindSingleNode("td[1]", $guestNamesNodes->item($i), true, "/\:\s*([^<]+)/ims");
        }

        return $result;
    }
    */

    private function saveToLogs($selenium)
    {
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
}
