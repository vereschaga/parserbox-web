<?php

class TAccountCheckerOtelcom extends TAccountChecker
{
    use PriceTools;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.otel.com/accounts/login/?next=/accounts/profile/edit/");
        $csrfToken = $this->http->FindPreg("/csrfToken:\s*\"([^\"]+)/");

        if (!$csrfToken) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://www.otel.com/accounts/login/';
        $this->http->SetInputValue("csrfmiddlewaretoken", $csrfToken);
        $this->http->SetInputValue("login", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://www.otel.com/bookings/upcoming/';

        return $arg;
    }

    public function checkErrors()
    {
        //# Scheduled maintenance
        if ($message = $this->http->FindSingleNode("//body[contains(text(), 'performing scheduled maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are updating our database. Our service will be back shortly.
        if ($message = $this->http->FindPreg("/(We are updating our database\.\s*Our service will be back shortly\.)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm(["X-Requested-With" => "XMLHttpRequest"])) {
            return $this->checkErrors();
        }
        $response = $this->http->JsonLog(null, true, true);
        $location = ArrayVal($response, 'location', null);

        if ($location) {
            $this->http->NormalizeURL($location);
            $this->http->GetURL($location);
        }// if ($location)
        // Success
        if ($this->http->FindPreg("/auth: true/")) {
            return true;
        }
        $form_errors = ArrayVal($response, 'form_errors', null);
        $errors = ArrayVal($form_errors, '__all__', null);
        // The e-mail address and/or password you specified are not correct.
        if (isset($errors[0]) && strstr($errors[0], 'The e-mail address and/or password you specified are not correct')) {
            throw new CheckException($errors[0], ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/fullName:\s*\"([^\"]+)/")));

        if (!empty($this->Properties['Name']) || $this->http->FindPreg("/username:\s*\"([^\"]+)/")) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $results = [];
        $this->http->GetURL("https://www.otel.com/bookings/upcoming/");
        $nodes = $this->http->XPath->query("//div[@class='trip-list__item' and contains(.,'Check-in') and contains(.,'Check-Out')]");
        $this->logger->debug("Found {$nodes->length} Reservations");

        if (!$this->http->FindPreg("#upcomingBookings:\s+\"\"#") || !$this->http->FindPreg("#var\s*upcomingBookingProducts\s*=\s*\[\]#")) {
            $this->sendNotification("otelcom - need check ParseItineraries");
        }

        if ($nodes->length == 0) {
            if ($this->http->FindNodes("//text()[contains(., 'You have 0 Upcoming Trips')]")
                    || $this->http->FindNodes("//p[contains(text(), 'You have no trip')]")) {
                return $this->noItinerariesArr();
            }
            // TODO скрипт отрисовывет сообщение, видимо с upcomingBookingProducts - но не удалось найти доказательств...
//            $this->http->FindPreg("/<script>\s*var pastBookingProducts = \[\];\s*var upcomingBookingProducts = \[\];\s+</script>/");
        }

        // Parse Hotel
        foreach ($nodes as $node) {
            $results[] = $this->parseHotel($node);
        }

        // Parse Voucher
        foreach ($results as &$value) {
            if (isset($value['Voucher'])) {
                $this->http->GetURL("https://www.otel.com" . $value['Voucher']);
                unset($value['Voucher']);
                $value += $this->parseVoucher();
            }
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($results, true), ['pre' => true]);

        $this->checkTours();

        return $results;
    }

    public function parseHotel($node)
    {
        $result = ['Kind' => 'R'];
        $result['ConfirmationNumber'] = $this->http->FindSingleNode(".//div[@class='trip-image__book-number']", $node, false, '/^[\w\-]+$/');
        $this->logger->info('Parse itinerary #' . $result['ConfirmationNumber'], ['Header' => 3]);
        $result['HotelName'] = $this->http->FindSingleNode(".//div[@class='trip-content__name']", $node);
        $result['Address'] = $this->http->FindSingleNode(".//div[@class='trip-content__desc']", $node);

        $result['CheckInDate'] = strtotime($this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), 'Check-in ')]", $node, false, '/Check-in\s+(.+)/'), false);
        $this->logger->debug("Check In Date " . $result['CheckInDate']);
        $result['CheckOutDate'] = strtotime($this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), 'Check-Out ')]", $node, false, '/Check-Out\s+(.+)/'), false);
        $this->logger->debug("Check Out Date " . $result['CheckOutDate']);

        $text = $this->http->FindSingleNode(".//div[@class='trip-content__list'][count(.//li)=3]", $node);
        $result['Guests'] = $this->http->FindPreg('/\b(\d+) Adults/', false, $text);
        $result['Kids'] = $this->http->FindPreg('/\b(\d+) Child/', false, $text);
        $result['Rooms'] = $this->http->FindPreg('/\b(\d+) Room/', false, $text);

        $total = $this->http->FindSingleNode(".//div[@class='trip-content__price-area']", $node);
        $result['Total'] = $this->cost($total);
        $result['Currency'] = $this->currency($total);

        if ($voucher = $this->http->FindSingleNode(".//text()[normalize-space(.)='Print Voucher']/ancestor::a[1]/@href", $node)) {
            $result['Voucher'] = $voucher;
        }

        return $result;
    }

    public function parseVoucher()
    {
        $result = [];
        $result['TripNumber'] = $this->http->FindSingleNode("//strong[text()='Booking reference(s)']/following-sibling::span[1]", null, false, '/^[\w\-]+$/');
        $result['GuestNames'] = preg_split('/,\s*/', $this->http->FindSingleNode("//strong[text()='Names:']/following-sibling::span[1]"));
        $result['RoomType'] = $this->http->FindSingleNode("//strong[text()='Room Type:']/following-sibling::span[1]");
        $result['ReservationDate'] = strtotime($this->http->FindSingleNode("//strong[text()='Reservation:']/following-sibling::span[1]"), false);

        $result['Taxes'] = $this->cost($this->http->FindSingleNode("//strong[text()='VAT:']/following-sibling::span[1]"));
        $result['Phone'] = $this->http->FindSingleNode("//text()[contains(., 'reservation, please call')]");
        $result['Phone'] = $this->http->FindPreg('#\s+([+\d\s()\-]+)\s*(?:\(\d+/\d+\))?$#', false, $result['Phone']);

        $result['CancellationPolicy'] = join("\n", $this->http->FindNodes("//strong[text()='Cancellation and Amendment Policy']/following-sibling::p[position() < 5]"));

        if (empty($result['ReservationDate']) || empty($result['Phone'])) {
            $this->logger->error("It is necessary to check \"Print Voucher\"");
        }

        return $result;
    }

    private function checkTours()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.otel.com/accounts/profile/tours/");

        if (!$this->http->FindPreg("#\{\s*tours:\s*\[\]\s*\}#")) {
            $this->sendNotification("otelcom - need check ParseItineraries, tours // ZM");
        }
    }
}
