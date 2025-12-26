<?php

class TAccountCheckerLoews extends TAccountChecker
{
    use PriceTools;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.loewshotels.com/account/dashboard", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.loewshotels.com/account");

        if (!$this->http->ParseForm(null, '//form[@data-form-primary="true"]')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("action", "default");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "System maintenance underway")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->ParseForm("hiddenform")) {
            $this->http->PostForm([], 80);
        }

        $response = $this->http->JsonLog();
        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        $code = $response->code ?? null;
        $description = $response->description ?? null;

        if ($code && $description) {
            // We've recently made improvements to the Loews Account area of our website. If this is your first time logging in since the upgrade please reset your password via the &quot;Forgot Password&quot; link.
            if ($code == "invalid_user_password" && $description == "Wrong email or password.") {
                throw new CheckException("We've recently made improvements to the Loews Account area of our website. If this is your first time logging in since the upgrade please reset your password via the &quot;Forgot Password&quot; link.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($code == "too_many_attempts" && $description == "Your account has been blocked after multiple consecutive login attempts. We've sent you an email with instructions on how to unblock it.") {
                throw new CheckException($description, ACCOUNT_LOCKOUT);
            }
        }
        // Please accept our new terms & conditions, then reset your password to verify your account.
        if ($this->http->FindSingleNode('//label[contains(text(), "Please accept our new terms & conditions, then reset your password to verify your account.")]')) {
            $this->throwAcceptTermsMessageException();
        }

        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Something went wrong please try again.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->AccountFields['Login'] == 'marnierrobinson@gmail.com') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//div[contains(@class, 'profile') and contains(@class, 'tablet-hide')]/p | //div[@id = 'loyalty-photos']//p[@class = 'profile-name']"));
        // Account ID
        $this->SetProperty("Number", $this->http->FindSingleNode('//p[contains(text(), "Account ID:")]', null, true, "/Account ID: \#(\d+)/"));

        if (empty($this->Properties['Name'])) {
            $this->http->GetURL("https://www.loewshotels.com/account/profile");
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[@name = 'firstname']/@value") . " " . $this->http->FindSingleNode("//input[@name = 'lastname']/@value")));
        }

        if (!empty($this->Properties['Name']) || !empty($this->Properties['Number'])) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $this->http->GetURL('https://www.loewshotels.com/account/stays');
        $hotelNodes = $this->xpathQuery('//div[contains(@class, "upcoming-stays-wrapper")]//div[contains(@class, "stays-wrapper") and not(//p[contains(normalize-space(), "You do not currently have any registered stays")])]/article');

        $hotelNodesPast = $this->xpathQuery('//div[contains(@class, "stay-history-wrapper")]//div[contains(@class, "entries-wrapper")]//article');

        if (
            $hotelNodes->length == 0
            && $this->http->FindSingleNode("//div[contains(@class,'upcoming-stays-wrapper stays-big-wrapper')]//p[contains(normalize-space(),'You do not currently have any registered stays')]")
            && (!$this->ParsePastIts || $hotelNodes->length == 0)
        ) {
            return $this->noItinerariesArr();
        }

        foreach ($hotelNodes as $node) {
            $this->parseHotelUpcoming($node);
        }

        if ($this->ParsePastIts) {
            //$this->http->GetURL('https://www.loewshotels.com/account/stay-history');
            foreach ($hotelNodesPast as $node) {
                $this->parseHotelPast($node);
            }
        }

        return [];
    }

    public function ConfirmationNumberURL($arFields)
    {
        $arrive = date('Y-m-d');
        $depart = date('Y-m-d', strtotime('+1 day'));

        return "https://be.synxis.com/signin?adult=1&arrive=$arrive&chain=19776&child=0&depart=$depart&level=chain&locale=en-US&rooms=1&shell=CBE&start=searchres&template=CBE";
    }

    public function GetConfirmationFields()
    {
        return [
            'ConfNo' => [
                'Caption'  => 'Reservation or Itinerary Confirmation',
                'Type'     => 'string',
                'Size'     => 40,
                'Required' => true,
            ],
            'Email'  => [
                'Caption'  => 'E-mail',
                'Type'     => 'string',
                'Size'     => 40,
                'Value'    => $this->GetUserField('Email'),
                'Required' => true,
            ],
        ];
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        if ($this->http->GetURL($this->ConfirmationNumberURL($arFields))) {
            if (
                $this->http->FindPreg('#<head>\s*<META NAME="robots" CONTENT="noindex,nofollow">\s*<script src="/_Incapsula_Resource\?SWJIYLWA=[^\"]+">\s*</script>\s*<body>#')
                || empty($this->http->Response['body'])
            ) {
                $this->selenium($this->ConfirmationNumberURL($arFields));
            }

            if ($this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")) {
                $this->incapsula();
            }
        }

        if (!$this->http->FindPreg('/"With Confirmation #/')) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }
        $headers = [
            'Accept'       => 'application/json,application/x-javascript',
            'Content-Type' => 'application/json; charset=utf-8',
            'Origin'       => 'https://be.synxis.com',
        ];
        $data = '{"version":"0.0","Hotel":{},"Chain":{"id":"19776"},"ChannelList":{"PrimaryChannel":{"Code":"WEB"},"SecondaryChannel":{"Code":"GC"}},"Query":{"Itinerary":{},"Reservation":{"BookingAgent":{"BookerProfile":{"id":""}},"GuestList":{"Guest":{"EmailAddress":"' . $arFields['Email'] . '"}},"CRS_confirmationNumber":"' . $arFields['ConfNo'] . '"},"IgnorePendingChanges":true},"UserDetails":{"Preferences":{"Language":{"code":"en-US"}}}}';

        $this->http->PostURL('https://be.synxis.com/gw/itinerary/v1/queryReservation', $data, $headers);

        if ($this->http->FindPreg('/"Paging":{"Size":0,"Start":0,"Total":0},"ReservationList":\[\]/')) {
            return 'We apologize. We cannot locate your reservation. Please check your information and try again.';
        }

        $this->parseHotelConfirmation();

        return null;
    }

    protected function incapsula($isRedirect = true)
    {
        $this->logger->notice(__METHOD__);
        $referer = $this->http->currentUrl();
        $incapsula = $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src");

        if (isset($incapsula)) {
            sleep(2);
            $this->http->NormalizeURL($incapsula);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($incapsula);
            $this->http->RetryCount = 2;
        }// if (isset($distil))
        $this->logger->debug("parse captcha form");
        $formURL = $this->http->FindPreg("/\"POST\", \"(\/_Incapsula_Resource\?[^\"]+)\"\s*,/");

        if (!$formURL) {
            return false;
        }
        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->NormalizeURL($formURL);
        $this->http->FormURL = $formURL;
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        if ($isRedirect) {
            $this->http->GetURL($referer);

            if ($this->http->Response['code'] == 503) {
                $this->http->GetURL($this->http->getCurrentScheme() . "://" . $this->http->getCurrentHost());
                sleep(1);
                $this->http->GetURL($referer);
            }
        }

        return true;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class = 'form_container']/div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->increaseTimeLimit(300);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, true, 3, 1);

        $this->increaseTimeLimit($recognizer->RecognizeTimeout);

        return $captcha;
    }

    private function parseHotelConfirmation()
    {
        $this->logger->notice(__METHOD__);
        $data = $this->http->JsonLog();
        $hotel = $this->itinerariesMaster->createHotel();
        // confirmation number
        $conf = $data->ItineraryList[0]->Reservation[0]->CRS_confirmationNumber;
        $hotel->addConfirmationNumber($conf, 'Confirmation', true);
        $this->logger->info("Parse Hotel #{$conf}", ['Header' => 3]);
        // hotel name
        $reservation = $data->ReservationList[0];
        $hotelName = $reservation->Hotel->Name;
        $hotel->setHotelName($hotelName);
        $address = join(', ', array_filter($reservation->Hotel->BasicPropertyInfo->Address->AddressLine));

        if (isset($reservation->Hotel->BasicPropertyInfo->Address->City)) {
            $address .= ', ' . $reservation->Hotel->BasicPropertyInfo->Address->City;
        }

        if (isset($reservation->Hotel->BasicPropertyInfo->Address->CountryName->Code)) {
            $address .= ', ' . $reservation->Hotel->BasicPropertyInfo->Address->CountryName->Code;
        }

        if (isset($reservation->Hotel->BasicPropertyInfo->Address->PostalCode)) {
            $address .= ', ' . $reservation->Hotel->BasicPropertyInfo->Address->PostalCode;
        }
        $hotel->setAddress($address);
        $phones = $reservation->Hotel->BasicPropertyInfo->ContactNumberList;

        foreach ($phones as $phone) {
            $hotel->setPhone($phone->Number);
        }

        $hotel->booked()->checkIn2($reservation->RoomStay->StartDate);
        // check out date
        $hotel->booked()->checkOut2($reservation->RoomStay->EndDate);

        foreach ($reservation->GuestList as $person) {
            $hotel->general()->traveller($person->PersonName->GivenName);
        }

        // guest count
        foreach ($reservation->RoomStay->GuestCount as $guest) {
            if ($guest->NumGuests > 0) {
                if ($guest->AgeQualifyingCode == 'Child') {
                    $hotel->setKidsCount($guest->NumGuests);
                } elseif ($guest->AgeQualifyingCode == 'Adult') {
                    $hotel->setGuestCount($guest->NumGuests);
                }
            }
        }

        $r = $hotel->addRoom();

        foreach ($reservation->RoomPriceList->PriceBreakdownList as $priceBreakdownList) {
            foreach ($priceBreakdownList->ProductPriceList as $product) {
                $r->addRate($product->Price->TotalAmount);
            }
        }
        $hotel->price()->total($reservation->RoomPriceList->TotalPrice->Price->TotalAmountIncludingTaxesFees);
        $hotel->price()->tax($reservation->RoomPriceList->TotalPrice->Price->Tax->Amount);
        $hotel->price()->currency($reservation->RoomPriceList->TotalPrice->Price->CurrencyCode);

        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($hotel->toArray(), true), ['pre' => true]);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(@href, "logout")]')) {
            return true;
        }

        return false;
    }

    private function xpathQuery($query, $parent = null): DomNodeList
    {
        $this->logger->notice(__METHOD__);
        $res = $this->http->XPath->query($query, $parent);
        $this->logger->info(sprintf('found %s nodes: %s', $res->length, $query));

        return $res;
    }

    private function parseHotelUpcoming(DOMNode $node)
    {
        $this->logger->notice(__METHOD__);
        $hotel = $this->itinerariesMaster->createHotel();
        // confirmation number
        $conf = $this->http->FindSingleNode(".//p[contains(@class, 'stays-dashboard__stay-code')]", $node, false, '/#\s*(.+)/');
        $hotel->addConfirmationNumber($conf, 'Confirmation', true);
        $this->logger->info("Parse Hotel #{$conf}", ['Header' => 3]);
        // hotel name
        $hotelName = $this->http->FindSingleNode("(.//h3[contains(@class, 'stays-dashboard__stay-title')])[1]", $node);
        $hotel->setHotelName($hotelName);
        // address
        $hotel->setNoAddress(true);

        // check in date
        $hotel->booked()->checkIn2($this->http->FindSingleNode(".//div[contains(@class,'check-in-info')]/span[contains(@class,'check-in-date value')]", $node));
        // check out date
        $hotel->booked()->checkOut2($this->http->FindSingleNode(".//div[contains(@class,'check-out-info')]/span[contains(@class,'check-in-date value')]", $node));
        // guest count
        $guests = $this->http->FindSingleNode(".//span[contains(@class,'guests value')]", $node, false, '/(\d+) Adults?/');
        $hotel->setGuestCount($guests);

        $guests = $this->http->FindSingleNode(".//span[contains(@class,'guests value')]", $node, false, '/(\d+) Children/');
        $hotel->setKidsCount($guests, false, true);

        // total
        $r = $hotel->addRoom();
        $r->setRate($this->http->FindSingleNode(".//div[contains(@class,'room-details')]//div[contains(@class,'room-price-info')]/span[contains(@class,'price value')]", $node));

        $hotel->price()->total($this->amount($this->http->FindSingleNode(".//span[contains(text(),'Due on Arrival')]/following-sibling::span[contains(@class,'price value')]", $node)));
        //$hotel->price()->currency($currency);
        $tax = $this->amount($this->http->FindSingleNode(".//span[contains(text(),'Taxes & Service Fees')]/../../following-sibling::div//span[contains(@class,'price value')]",
            $node));

        if ($tax > 0) {
            $hotel->price()->tax($tax);
        }

        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($hotel->toArray(), true), ['pre' => true]);
    }

    private function amount($str)
    {
        // 1,220.00
        return preg_replace('/(\d+),(\d+\.\d+)/', '$1$2', $str);
    }

    private function parseHotelPast(DOMNode $node)
    {
        $this->logger->notice(__METHOD__);
        $hotel = $this->itinerariesMaster->createHotel();
        // confirmation number
        $conf = $this->http->FindSingleNode(".//p[contains(@class, 'stays-dashboard__stay-code')]", $node, false, '/#\s*(.+)/');
        $hotel->addConfirmationNumber($conf, 'Confirmation', true);
        $this->logger->info("Past Parse Hotel #{$conf}", ['Header' => 3]);
        // hotel name
        $hotelName = $this->http->FindSingleNode("(.//h3[contains(@class, 'stays-dashboard__stay-title')])[1]", $node);
        $hotel->setHotelName($hotelName);
        // address
        $address = $this->http->FindNodes(".//div[contains(@class, 'stays-dashboard__stay-details-data')]/p/b//text()[position()>1]", $node);
        $hotel->setAddress(join(', ', $address));

        $traveller = $this->http->FindSingleNode("(.//div[contains(@class, 'stays-dashboard__stay-details-data')]/p/b/text())[1]", $node);
        $hotel->addTraveller(beautifulName($traveller));

        $stayId = $this->http->FindSingleNode(".//div[contains(@class, 'stays-dashboard__stay-folio')]/@data-folio-id", $node);

        if (!$stayId) {
            $this->logger->error('Empty stayId');

            return;
        }
        $browser = clone $this->http;
        $this->http->brotherBrowser($browser);
        //$browser = new HttpBrowser("none", new CurlDriver());
        $browser->PostURL("https://www.loewshotels.com/account/folio?crs_stay_id={$stayId}", [], [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'x-requested-with' => 'XMLHttpRequest',
        ]);
        $data = $browser->JsonLog();

        // check in date
        $hotel->booked()->checkIn2($data->data->stay->arrival);
        // check out date
        $hotel->booked()->checkOut2($data->data->stay->arrival);
        // guest count
        if (!empty($data->data->guests->adults)) {
            $hotel->setGuestCount($data->data->guests->adults);
        }

        if (!empty($data->data->guests->children)) {
            $hotel->setKidsCount($data->data->guests->children);
        }

        // total
        $total = 0;
        $currency = null;

        foreach ($data->data->invoices[0]->lines as $line) {
            if ($line->total > 0) {
                $total += $line->total;
                $currency = $line->currency;
            }
        }
        $hotel->price()->total($total);
        $hotel->price()->currency($currency);

        // cancelled
        if ($this->http->FindSingleNode('.//h2[contains(text(), "Cancelled")]', $node)) {
            $hotel->setCancelled(true);
        }

        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($hotel->toArray(), true), ['pre' => true]);
    }

    private function selenium($url)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL($url);
            //sleep(1);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            // save page to logs
            $selenium->http->SaveResponse();
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }
    }
}
