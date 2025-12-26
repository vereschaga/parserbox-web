<?php

use AwardWallet\Common\Parser\Util\PriceHelper;

class TAccountCheckerMarriottvacationclub extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public const WAIT_TIMEOUT = 7;

    private const REWARDS_PAGE_URL = 'https://owners.marriottvacationclub.com/timeshare/mvco/getProductSummary';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();
        $this->http->GetURL('https://login.marriottvacationclub.com/login');

        if ($this->http->Response['code'] !== 200) {
            return false;
        }

        $this->selenium();

        return true;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
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

            $selenium->http->GetURL('https://login.marriottvacationclub.com/login');

            $login = $selenium->waitForElement(WebDriverBy::id('username'), self::WAIT_TIMEOUT);
            $pass = $selenium->waitForElement(WebDriverBy::id('password'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(),"Log In")]'), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$pass || !$button) {
                $this->logger->error("Something wrong with login, pass or button");

                return false;
            }

            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);
            $button->click();
            $this->savePageToLogs($selenium);

            $selenium->waitForElement(WebDriverBy::xpath('
                //a[contains(@href, "logout")]
                | //*[@id="password-error"]/p
                | //span[contains(text(), "This page isn’t working")]
            '), self::WAIT_TIMEOUT);
            // save page to logs
            $this->savePageToLogs($selenium);

            if ($this->http->FindSingleNode('(//a[contains(@href, "logout")])[1]')) {
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            }
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->DebugInfo = "TimeOutException";

            throw new CheckRetryNeededException(3, 0);
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        if ($this->http->FindSingleNode('//span[contains(text(), "This page isn’t working")]')) {
            $this->DebugInfo = "bad proxy";

            throw new \CheckRetryNeededException(2);
        }

        return true;
    }

    public function Login()
    {
        if ($message = $this->http->FindSingleNode('
                //p[
                    contains(text(), "Due to system maintenance, this area of the web site is unavailable.")
                    or contains(text(), "To use the website, you must have at least one active ownership associated to your user account.")
                    or contains(text(), "BE BACK SOON")
                    or contains(text(), "THANK YOU FOR YOUR PATIENCE")
                ]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        //invalid username or password
        $message = $this->http->FindSingleNode("//*[@id='password-error']/p");

        if ($message) {
            $this->logger->error("Message: {$message}");

            if (strstr($message, "You've entered an invalid username or password.")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "Please provide the information below to connect your ownership to this account.")]')) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() !== self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        //SubAccount
        $subAccounts = $this->http->XPath->query('//*[@id="accordion"]/div[descendant::div[@id="omr_ContractDetails"] and descendant::div[starts-with(@id,"avcpsCollapseTwo")] ]');
        $this->logger->debug("Total {$subAccounts->length} Available Vacation Club Points were found");

        $omr_ContractDetails = "div[@id='omr_ContractDetails']/descendant::h4";
        $avcpsCollapseTwo = "descendant::div[starts-with(@id,'avcpsCollapseTwo')]";
        $totalBalance = null;

        foreach ($subAccounts as $key => $account) {
            $expirationDate = $this->http->FindSingleNode($omr_ContractDetails, $account, true, "/\d{1,2} [A-z]+ \d{2,4}\s*-\s*(\d{1,2} [A-z]+ \d{2,4})/s");
            $displayName = $this->http->FindSingleNode($omr_ContractDetails, $account, true, "/(Use Year:\s*\d{1,2} [A-z]+ \d{2,4}\s*-\s*\d{1,2} [A-z]+ \d{2,4})/s");
            $contractDate = $this->http->FindSingleNode($avcpsCollapseTwo, $account, true, "/Contract Date:\s*(\d{1,2}-[A-z]+-\d{2,4})/s");
            $balance = $this->http->FindSingleNode($omr_ContractDetails, $account, true, "/Points Available:\s*(\d+[\d,]+)/s");
            $pointType = $this->http->FindSingleNode($avcpsCollapseTwo, $account, true, "/Point Type\s*([A-z\s]+)\s*\|/s");

            if (empty($expirationDate)) {
                $this->logger->info("Expiration Date is empty!", ['Header' => 3]);
                $this->sendNotification("refs #14371: Expiration Date is empty!");
                $this->logger->debug(var_export([$displayName, $expirationDate, $contractDate, $balance, $pointType], true), ['pre' => true]);
            }
            $balance = PriceHelper::cost($balance);
            $totalBalance += $balance;
            $this->logger->debug(var_export("Main balance:{$totalBalance}", true), ['pre' => true]);

            $this->AddSubAccount([
                'Code'           => 'marriottvacationclub' . md5($displayName),
                "DisplayName"    => $displayName,
                "Balance"        => $balance,
                "ExpirationDate" => strtotime($expirationDate),
                "ContractDate"   => $contractDate,
                "PointType"      => $pointType,
            ]);
        }

        // Balance -
        $this->SetBalance($totalBalance);

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // AccountID: 5024040
            if (
                $this->http->FindPreg('#club-weeks">(?:My |)Marriott Vacation Club Weeks</h4>#')
                && !$this->http->FindPreg('#club-weeks">(?:My |)Vacation Club Points</h4>#')
                && $subAccounts->length == 0
            ) {
                $this->SetBalanceNA();
            } elseif ($message = $this->http->FindSingleNode('//p[contains(text(), "At this time, we are experiencing an interruption in service. Please accept our apologies and re-visit the site at a later time.") or contains(text(), "At this time, you don\'t currently have any Points available to reserve")]')) {
                $this->SetWarning($message);
            } elseif ($this->http->FindSingleNode('//p[@style and contains(text(), "To redeem your enrolled Marriott Vacation Club")]')) {
                $this->SetWarning("To redeem your enrolled Marriott Vacation Club® Week for access to one of these options, you must first elect your Week for Club Points.");
            }
        }

        $this->http->GetURL("https://owners.marriottvacationclub.com/timeshare/mvco/account/myprofile");
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//*[@id="ownerName"]/@value')));
        // Benefit Level
        $this->SetProperty('Status', $this->http->FindSingleNode('(//*[contains(normalize-space(),"Benefit Level:") and @class = "tabTitleValue"]/following-sibling::*[@class = "profileNonChangeValue"][1])[1]'));
        // Customer ID
        $this->SetProperty('Number', $this->http->FindSingleNode('(//*[contains(normalize-space(),"Customer ID:") and @class = "tabTitleLabel"]/following-sibling::*[@class = "tabTitleValue" and contains(.,"Primary")][1])[1]', null, true, "/^(\d+)\s.+$/"));
    }

    public function ParseItineraries()
    {
        if (!isset($this->Properties['Number'])) {
            $this->logger->error("Customer ID not found");

            return [];
        }

        $this->http->GetURL("https://owners.marriottvacationclub.com/timeshare/mvco/getUpcomingReservation?intMode=AC&ownerId={$this->Properties['Number']}&screenType=RESERVATIONS#");

        $headers = [
            'Authorization' => "Bearer {$this->http->FindSingleNode("//input[@id='ssoTokenForReact']/@value")}",
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://gateway.mvwc.com/proxy/owner-tsw-reservation/v2/owner/reservation?reservationType=UPCOMING", $headers);
        $this->http->RetryCount = 2;
        $nodes = $this->http->JsonLog();
        $notUpcoming = $this->http->FindPreg('/"reservations":\s*\[\s*\]/');

        if ($notUpcoming && !$this->ParsePastIts) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        if (!isset($nodes->reservations)) {
            if (isset($nodes->message) && $nodes->message == "Unable to get reservation history") {
                $this->logger->error("[Error]: {$nodes->message}");

                return [];
            }

            $this->sendNotification("reservations broken");

            return [];
        }

        foreach ($nodes->reservations as $node) {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://gateway.mvwc.com/proxy/owner-tsw-reservation/v2/owner/reservation/{$node->reservationDetails->reservationID}?resType=UPCOMING", $headers);
            $this->http->RetryCount = 0;
            $this->http->RetryCount = 2;
            $data = $this->http->JsonLog();

            if (isset($data->errorCode) && $data->errorCode = 'EXPRESSION') {
                $this->logger->error('Same error on the site');
            }
            $this->parseHotel($data);
        }

        if ($this->ParsePastIts) {
            $this->http->GetURL("https://gateway.mvwc.com/proxy/owner-tsw-reservation/v2/owner/reservation?reservationType=PAST", $headers);
            $nodes = $this->http->JsonLog();

            if ($notUpcoming && $this->http->FindPreg('/"reservations":\s*\[\s*\]/')) {
                $this->itinerariesMaster->setNoItineraries(true);

                return [];
            }

            foreach ($nodes->reservations as $node) {
                $this->http->GetURL("https://gateway.mvwc.com/proxy/owner-tsw-reservation/v2/owner/reservation/{$node->reservationDetails->reservationID}?resType=PAST", $headers);
                $data = $this->http->JsonLog();
                $this->parseHotel($data, 'Past');
            }
        }

        return [];
    }

    public function ParseItinerariesOld()
    {
        $this->http->GetURL("https://owners.marriottvacationclub.com/timeshare/mvco/getUpcomingReservation");
        $upcomingPoints = $this->http->FindSingleNode("//*[@id='upcomingReservation']/descendant::h3[starts-with(normalize-space(),'You currently do not have any upcoming reservations')]");
        $upcomingWeeks = $this->http->FindSingleNode("//*[@id='upComingReservationTableDiv']/descendant::h3[starts-with(normalize-space(),'You currently do not have any upcoming reservations')]");
        // My Upcoming Points Reservations
        $reservationsPoints = $this->http->XPath->query('//div[@id="upcomingReservation"]/div[@class = "transaction-mobile-section"]');
        $this->logger->debug("Total {$reservationsPoints->length} My Upcoming Points Reservations");
        // My Upcoming Weeks Reservations
        $reservationsWeeks = $this->http->XPath->query('//*[@id="upComingReservationTableDiv"]/tr');
        $this->logger->debug("Total {$reservationsWeeks->length} My Upcoming Weeks Reservations");

        if ($reservationsPoints->length === 1
            && $reservationsWeeks->length === 1
            && !empty($upcomingPoints)
            && !empty($upcomingWeeks)
        ) {
            return $this->noItinerariesArr();
        }

        $titlePoints = $this->http->FindNodes('//div[@id="upcomingReservation"]/preceding::div[@class="transaction-mobile-group"]/descendant::th');

        if ($reservationsPoints->length > 0 && isset($titlePoints) && empty($upcomingPoints)) {
            $this->logger->info('Parsed HotelTypePoints count:' . $reservationsPoints->length, ['Header' => 3]);
            $titlePoints = array_flip($titlePoints);
            $this->logger->debug(var_export($titlePoints, true), ['pre' => true]);

            foreach ($reservationsPoints as $it) {
                $this->parseHotelTypePoints($it, $titlePoints);
            }
        }

        $titleWeeks = $this->http->FindNodes('//*[@id="upComingReservationTableDiv"]/preceding::tr[1]/th');

        if ($reservationsWeeks->length > 0 && isset($titleWeeks) && empty($upcomingWeeks)) {
            $this->logger->info('Parsed HotelTypeWeeks count:' . $reservationsWeeks->length, ['Header' => 3]);
            $titleWeeks = array_flip($titleWeeks);
            $this->logger->debug(var_export($titleWeeks, true), ['pre' => true]);

            foreach ($reservationsWeeks as $it) {
                $this->parseHotelTypeWeeks($it, $titleWeeks);
            }
        }

        return [];
    }

    private function parseHotel($data, $type = ''): void
    {
        $this->logger->notice(__METHOD__);

        if (!isset($data->reservationNumber)) {
            $this->logger->error('Skip: something went wrong');

            return;
        }

        if ($data->reservationDetails->resortName == 'EC Cruise') {
            $this->logger->error('Skip: cruise little information');

            return;
        }
        $h = $this->itinerariesMaster->add()->hotel();
        // Transactions Details

        // Confirmation Number: 12345678
        $confNo = $data->reservationNumber;
        $this->logger->info("Parse$type Itinerary #{$confNo}", ['Header' => 3]);
        $h->general()
            ->confirmation($confNo, 'Confirmation number', true)
            ->date2($data->transactionDate);

        $h->general()->traveller(beautifulName($data->primaryGuest->firstName . " " . $data->primaryGuest->lastName));

        $h->hotel()
            ->name($data->reservationDetails->resortName);

        if (!empty($data->reservationDetails->resortLocation)) {
            $h->hotel()
                ->address($data->reservationDetails->resortLocation);
        } elseif (isset($data->reservationDetails->resortLocation) && empty($data->reservationDetails->resortLocation)) {
            $h->hotel()->noAddress();
        }

        $h->addRoom()
            ->setType($data->reservationDetails->roomType->name, true, true)
            ->setDescription($data->reservationDetails->roomType->description);

        $h->booked()->checkIn2($data->reservationDetails->checkInDate);
        $h->booked()->checkOut2($data->reservationDetails->checkInDate);

        $h->price()
            ->spentAwards($data->usageDetails->appliedPoints);

        $h->booked()
            ->guests($data->reservationDetails->numOfGuests);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('(//a[contains(@href, "logout")])[1]')
            && !strstr($this->http->currentUrl(), "mvco/owner/login")
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // maintenance
        $this->CheckError($this->http->FindSingleNode('//h1[normalize-space() = "We Apologize for the Inconvenience"]/ancestor::div[@class = "login-box"]'), ACCOUNT_PROVIDER_ERROR);

        return false;
    }

    // UpcomingPointsReservations
    private function parseHotelTypePoints($it, $title): void
    {
        $this->logger->notice(__METHOD__);
        $h = $this->itinerariesMaster->add()->hotel();
        // Transactions Details
        $xPathTransactionsDetails = 'descendant::p[normalize-space() = "Transactions Details"]/ancestor::div[1]';

        // Confirmation Number: 12345678
        $confNo = $this->http->FindSingleNode($xPathTransactionsDetails, $it, true, "/Confirmation Number:\s(\d+)\s?/s");
        $this->logger->info("Parse Itinerary #{$confNo}", ['Header' => 3]);
        $h->general()
            // Confirmation Number: 12345678
            ->confirmation($confNo, 'Confirmation number', true)
            // Status: ...
            ->status($this->http->FindSingleNode('descendant::td[' . ($title['Check-In Date'] + 1) . ']', $it, true, "/Status: (.+?)$/s"))
            // Transaction Date : 01 MAY 2020
            ->date2($this->http->FindSingleNode('descendant::td[' . ($title['Transaction Date'] + 1) . ']', $it, true, "/(\d+\s[A-z]{3}\s\d{4})/s"));

        if ($traveller = $this->http->FindSingleNode($xPathTransactionsDetails, $it, true,
            "/Guest Name:\s([\w\s\.]+)\s?No\./s")) {
            $h->general()->traveller(beautifulName($traveller));
        }

        $h->addRoom()
            // Room/Type
            ->setDescription($this->http->FindSingleNode('descendant::td[' . ($title['Room/Type'] + 1) . ']', $it));

        $h->price()
            // 	Points Usage : -1000
            ->spentAwards($this->http->FindSingleNode('descendant::td[' . ($title['Points Usage'] + 1) . ']', $it));

        $h->hotel()
            ->name($this->http->FindSingleNode('descendant::td[' . ($title['Destination'] + 1) . ']/a', $it));
        $h->booked()
            ->guests($this->http->FindSingleNode($xPathTransactionsDetails, $it, true, "/Guests:\s(\d+)/s"));

        $nights = $this->http->FindSingleNode('descendant::td[' . ($title['Nights'] + 1) . ']', $it, true, '(\d+)');
        $checkInDate = $this->http->FindSingleNode('descendant::td[' . ($title['Check-In Date'] + 1) . ']', $it, true, "/(\d+\s[A-z]{3}\s\d{4})/s");
        $destinationUrl = $this->http->FindSingleNode('descendant::td[' . ($title['Destination'] + 1) . ']/a/@href', $it);

        $this->parseHotelInfo($destinationUrl, $h, $checkInDate, $nights);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    // UpcomingWeeksReservation
    private function parseHotelTypeWeeks($it, $title): void
    {
        $this->logger->notice(__METHOD__);
        $h = $this->itinerariesMaster->add()->hotel();
        // Confirmation No.: 12345678
        $confNo = $this->http->FindSingleNode('descendant::td[' . ($title['Check-In Date OR Check-In Date Deposited'] + 1) . ']', $it, true, "/Confirmation No\.:\s(\d+)\s?/s");

        if (empty($confNo) && $status = $this->http->FindSingleNode('descendant::td[' . ($title['Check-In Date OR Check-In Date Deposited'] + 1) . ']', $it, true, "/Status: (\w+\s*\w*)$/s")) {
            $confNo = CONFNO_UNKNOWN;
            $h->general()
                // Confirmation No.: 12345678
                ->noConfirmation()
                // Status: ...
                ->status($status)
                // Transaction Date : 01 MAY 2020
                ->date2($this->http->FindSingleNode('descendant::td[' . ($title['Transaction Date'] + 1) . ']', $it));
        } else {
            $h->general()
                // Confirmation No.: 12345678
                ->confirmation($confNo, 'Confirmation number', true)
                // Status: ...
                ->status($this->http->FindSingleNode('descendant::td[' . ($title['Check-In Date OR Check-In Date Deposited'] + 1) . ']', $it, true, "/Status: (.+?)Confirmation/s"))
                // Transaction Date : 01 MAY 2020
                ->date2($this->http->FindSingleNode('descendant::td[' . ($title['Transaction Date'] + 1) . ']', $it));
        }
        $this->logger->info("Parse Itinerary #{$confNo}", ['Header' => 3]);

        $h->addRoom()
            ->setDescription($this->http->FindSingleNode('descendant::td[' . ($title['Villa Portion'] + 1) . ']', $it));

        $nights = $this->http->FindSingleNode('descendant::td[' . ($title['Nights'] + 1) . ']', $it, true, "/(\d+)/");
        $checkInDate = $this->http->FindSingleNode('descendant::td[' . ($title['Check-In Date OR Check-In Date Deposited'] + 1) . ']', $it, true, "/(\d+\s[A-z]{3}\s\d{4})/s");
        $destinationUrl = $this->http->FindSingleNode('descendant::td[' . ($title['Ownership'] + 1) . ']/a/@href', $it);

        $this->parseHotelInfo($destinationUrl, $h, $checkInDate, $nights);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    /** @param AwardWallet\Schema\Parser\Common\Hotel $h */
    private function parseHotelInfo($destinationUrl, $h, $checkInDate, $nights)
    {
        $this->logger->notice(__METHOD__);

        if (!$destinationUrl) {
            return false;
        }
        $browser = clone $this->http;
        $this->http->NormalizeURL($destinationUrl);
        $browser->GetURL($destinationUrl);
        $checkInTime = $browser->FindSingleNode('(//p[@class="resortInfo"])[1]/text()[contains(normalize-space(),"Check-in time:")]', null, true, "/Check-in time:\s?(\d+:\d+\s?[APM]+)/");
        $checkOutTime = $browser->FindSingleNode('(//p[@class="resortInfo"])[1]/text()[contains(normalize-space(),"Check-out time:")]', null, true, "/Check-out time:\s?(\d+:\d+\s?[APM]+)/");

        $this->logger->debug($checkInTime);
        $this->logger->debug($checkOutTime);

        if (!isset($checkInTime, $checkOutTime) && $msg = $browser->FindSingleNode('//p[contains(text(),"re sorry, there was a problem with the server loading this page. We apologize for the inconvenience.")]')) {
            $this->logger->error($msg);
            $h->hotel()->noAddress();

            return false;
        }

        $h->booked()
            //	Check-In Date : 01 MAY 2020
            ->checkIn(strtotime($checkInDate . ", " . $checkInTime));

        if ($nights > 0) {
            $h->booked()->checkOut(strtotime("+" . $nights . " days", strtotime($checkInDate . ", " . $checkOutTime)));
        } elseif (!$nights && !$checkOutTime) {
            $h->booked()->noCheckOut();
        }

        $h->hotel()
            ->name($browser->FindSingleNode('(//p[@class="resortInfo"]/preceding-sibling::h1/text())[1]'))
            ->address(implode(', ', $browser->FindNodes('(//p[@class="resortInfo"])[1]/text()[position() <= 2][not(contains(normalize-space(),"Phone:"))][not(contains(normalize-space(),"Check-in"))][not(contains(normalize-space(),"Check-out"))][not(contains(normalize-space(),"Fax:"))][normalize-space()!=""]')))
            ->phone($browser->FindSingleNode('(//p[@class="resortInfo"])[1]/text()[contains(normalize-space(),"Phone:")]', null, true, "/Phone:(.+)/"));

        $fax = trim($browser->FindSingleNode('(//p[@class="resortInfo"])[1]/text()[contains(normalize-space(),"Fax:")]',
            null, true, "/Fax:(.+)/"));

        if ($fax != 'N/A' && !empty($fax)) {
            $h->hotel()->fax($fax);
        }

        return true;
    }
}
