<?php

class TAccountCheckerPia extends TAccountChecker
{
    public $code = "pia";

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://book-{$this->code}.crane.aero/ibe/loyalty/mycard?", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful() && !strstr($this->http->currentUrl(), '.crane.aero/ibe/search')) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://book-{$this->code}.crane.aero/ibe/loyalty");

        if (!$this->http->ParseForm(null, "//form[contains(@action, '/ibe/loyalty;')]")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('isRemember', 'on');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//span[@id = 'errorModalText']")) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "Please check your credentials and try again.")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Miles Balance
        $this->SetBalance($this->http->FindSingleNode('//div[contains(@class, "loyalty-cover-detailed") and not(contains(@class, "mobile"))]//h2[contains(@class, "loyalty-cover-miles")]'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[contains(@class, "loyalty-cover-detailed") and not(contains(@class, "mobile"))]//span[contains(@class, "loyalty-cover-name")]')));
        // Membership No
        $this->SetProperty("Number", beautifulName($this->http->FindSingleNode('//div[contains(@class, "loyalty-cover-detailed") and not(contains(@class, "mobile"))]//span[contains(text(), "Membership No")]/following-sibling::span')));
        // Status
        $this->SetProperty("Status", beautifulName($this->http->FindSingleNode('//div[contains(@class, "loyalty-cover-detailed") and not(contains(@class, "mobile"))]//span[contains(text(), "Current Tier")]/following-sibling::span')));
        // Membership Start Date
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//div[contains(text(), 'Member Since')]/following-sibling::div"));

        // Expiration Date
        $expDate = $this->http->FindSingleNode('//div[contains(@class, "loyalty-cover-detailed") and not(contains(@class, "mobile"))]//span[contains(text(), "Upcoming Miles Expire Day")]/following-sibling::span');

        if ($exp = strtotime($expDate)) {
            $this->SetExpirationDate($exp);
        }
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//a/span[contains(text(), 'Logout')]")
            || $this->http->FindNodes("//a/span[contains(text(), 'Log Out')]") // bruneiair
        ) {
            return true;
        }

        return false;
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->GetURL("https://book-{$this->code}.crane.aero/ibe/loyalty/myTickets/upcomingFlights");

        $itineraries = $this->http->XPath->query("//table[@class='upcoming-flight-table']//tr//form[@action='/ibe/reservation/search']");

        if ($itineraries->length == 0 && $this->http->FindSingleNode("//h3[contains(text(),'t see your upcoming flight?')]")) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }
        $this->logger->debug("Total {$itineraries->length} itineraries were found");

        $forms = [];

        foreach ($itineraries as $itinerary) {
            $forms[] = [
                '_sid'    => $this->http->FindSingleNode("./input[@name='_sid']/@value", $itinerary),
                '_cid'    => $this->http->FindSingleNode("./input[@name='_cid']/@value", $itinerary),
                'surname' => $this->http->FindSingleNode("./input[@name='surname']/@value", $itinerary),
                'PNRNo'   => $this->http->FindSingleNode("./input[@name='PNRNo']/@value", $itinerary),
            ];
        }

        foreach ($forms as $form) {
            $this->http->GetURL("https://book-royalbrunei.crane.aero/ibe/reservation/search?" . http_build_query($form));
            $this->parseItinerary();
        }

        return $result;
    }

    private function parseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->add()->flight();
        $f->general()->confirmation($this->http->FindSingleNode("//span[@class='pnr-text pnr-code']"), 'Booking Reference Number (PNR)');

        $travellers = $this->http->XPath->query("//div[contains(@class,'passenger-card')]/a");
        $this->logger->debug("Total {$travellers->length} travellers were found");

        foreach ($travellers as $traveller) {
            $f->general()->traveller($this->http->FindSingleNode(".//span[@class='passengers-text']", $traveller));
        }

        $segments = $this->http->XPath->query("//div[contains(@class,'flight-table')]//div[contains(@class,'table-item')]/div[contains(@class,'primary-row')]");
        $this->logger->debug("Total {$segments->length} segments were found");

        foreach ($segments as $segment) {
            $s = $f->addSegment();
            $airline = $this->http->FindSingleNode(".//div[@class='segment-flight-number']/span", $segment);
            $s->airline()->name($this->http->FindPreg('/([A-Z]{2})-\d+/', false, $airline));
            $s->airline()->number($this->http->FindPreg('/[A-Z]{2}-(\d+)/', false, $airline));

            $s->extra()->duration($this->http->FindSingleNode("(.//span[@class='flight-duration'])[1]", $segment));
            $s->extra()->stops($this->http->FindSingleNode("(.//span[@class='total-stop'])[1]", $segment, false, '/^\d+$/') ?? 0);

            $depName = $this->http->FindSingleNode(".//div[contains(@class,'left-block')]//span[@class='port']", $segment);
            $depTime = $this->http->FindSingleNode(".//div[contains(@class,'left-block')]//div[@class='date-time-block']", $segment);

            // Kota Kinabalu (BKI)
            $s->departure()->name($this->http->FindPreg('/^(.+?)\(/', false, $depName));
            $s->departure()->code($this->http->FindPreg('/\(([A-Z]{3})\)/', false, $depName));
            $s->departure()->date2(preg_replace('/\s+-\s+/', ', ', $depTime));

            $arrName = $this->http->FindSingleNode(".//div[contains(@class,'right-block')]//span[@class='port']", $segment);
            $arrTime = $this->http->FindSingleNode(".//div[contains(@class,'right-block')]//div[@class='date-time-block']", $segment);

            // Kota Kinabalu (BKI)
            $s->arrival()->name($this->http->FindPreg('/^(.+?)\(/', false, $arrName));
            $s->arrival()->code($this->http->FindPreg('/\(([A-Z]{3})\)/', false, $arrName));
            $s->arrival()->date2(preg_replace('/\s+-\s+/', ', ', $arrTime));

            $search = "$depName - $arrName";
            $seats = $this->http->XPath->query("//div[contains(@class,'passenger-collapse') and .//span[contains(@class,'direction-title') and contains(text(),'$search')]]");
            $this->logger->debug("Total {$seats->length} seats were found");

            foreach ($seats as $seat) {
                $seatName = $this->http->FindSingleNode(".//span[contains(@class,'package-ssr__SEAT')]", $seat);
                $seat = str_replace(' ', '', $this->http->FindPreg('/Seat (.+)/', false, $seatName));
                $s->extra()->seat($seat, true);
            }
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }
}
