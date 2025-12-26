<?php

class TAccountCheckerMonarch extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->FilterHTML = false;
        $this->http->removeCookies();
//        $this->http->GetURL("https://bookflights.monarch.co.uk/MemberBookingManager.aspx");
        $this->http->GetURL("https://bookflights.monarch.co.uk/MyAccount.aspx");

        if (!$this->http->ParseForm("SkySales")) {
            return $this->checkErrors();
        }
//        $this->http->SetInputValue("MemberLoginMyBookingView\$TextBoxUserID", $this->AccountFields['Login']);
//        $this->http->SetInputValue("MemberLoginMyBookingView\$PasswordFieldPassword", $this->AccountFields['Pass']);
//        $this->http->SetInputValue("__EVENTTARGET", 'MemberLoginMyBookingView$LinkButtonLogIn');
//        $this->http->Form['__EVENTTARGET'] = 'MemberLoginMyBookingView$LinkButtonLogIn';

        $this->http->SetInputValue('MemberLoginMyAccountView$TextBoxUserID', $this->AccountFields['Login']);
        $this->http->SetInputValue('MemberLoginMyAccountView$PasswordFieldPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue("__EVENTTARGET", 'MemberLoginMyAccountView$LinkButtonLogIn');

        $this->http->SetInputValue("__VIEWSTATE", $this->http->Form['viewState']);
        $this->http->RetryCount = 0;
        $this->http->setCookie('culture', 'en-GB');

        return true;
    }

    public function checkErrors()
    {
        // provider error
        if ($message = $this->http->FindPreg("/An error has occurred.  Please try again./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'LogOut')]/@href")) {
            return true;
        }

        // Invalid login or password
        if ($this->http->FindSingleNode("//h1[contains(text(),'sorry there has been a problem...')]")) {
            // login
            if ($message = $this->http->FindSingleNode("//strong[contains(text(),'[1006:AgentNotFound]')]/..", null, true, "#^\[[^\]]+\](.*?)$#ms")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // password
            if ($message = $this->http->FindSingleNode("//strong[contains(text(),'[1004:AgentAuthentication]')]/..", null, true, "#^\[[^\]]+\](.*?)$#ms")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // 1. Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//p[@class='welcome-message']", null, true, "#^Welcome\s+(.*?)$#")));

        $this->http->GetURL("https://bookflights.monarch.co.uk/VCLookup.aspx");

        // Total flying points available (Balance *)
        $balance = $this->http->FindSingleNode("//th[contains(text(), 'Total flying points available')]/following-sibling::td");

        if ($balance === '') {
            $this->SetBalanceNA();
        } else {
            $this->SetBalance($balance);
        }

        // Membership expiry date (Expiration Date *)
        $date = $this->http->FindSingleNode("//th[contains(text(), 'Membership expiry date')]/following-sibling::td");

        if ($date) {
            $this->SetExpirationDate(strtotime(implode('/', array_reverse(explode('/', $date)))));
        }
        // 1. Flying Points awarded to date (renamed to "Lifetime flying points earned")
        $this->SetProperty('PointsAwardedToDate', $this->http->FindSingleNode("//th[contains(text(), 'Flying Points awarded to date')]/following-sibling::td"));
        // 2. Member since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode("//th[contains(text(), 'Member since')]/following-sibling::td"));
        // 3. Total flying points redeemed to date
        $this->SetProperty('TotalFlyingPointsRedeemed', $this->http->FindSingleNode("//th[contains(text(), 'Total flying points redeemed to date')]/following-sibling::td"));
        // 4. Membership year start date
        $this->SetProperty('MembershipYearStartDate', $this->http->FindSingleNode("//th[contains(text(), 'Membership year start date')]/following-sibling::td"));
        // 5. Total flying points expired to date
        $expired = trim($this->http->FindSingleNode("//th[contains(text(), 'Total flying points expired to date')]/following-sibling::td"));

        if ($expired !== '') {
            $this->SetProperty('TotalFlyingPointsExpired', $expired);
        }
        // 6. Membership level (Status *)
        $this->SetProperty('MembershipLevel', $this->http->FindSingleNode("//th[contains(text(), 'Membership level')]/following-sibling::td"));
        // 7. Membership points awarded year to date (renamed to "YTD Membership points")
        $this->SetProperty('MembershipPoints', $this->http->FindSingleNode("//th[contains(text(), 'Membership points awarded year to date')]/following-sibling::td"));
        // 8. Additional membership points required by to Maintain level
        $this->SetProperty('PointsToMaintainLevel', $this->http->FindSingleNode("//th[contains(text(), 'to Maintain')]/following-sibling::td"));
        // 9. Additional membership points required by to Reach level
        $this->SetProperty('PointsToNextLevel', $this->http->FindSingleNode("//th[contains(text(), 'to Reach')]/following-sibling::td"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // An error has occurred. Please try again
            if ($this->http->FindSingleNode("//h1[contains(text(),'sorry there has been a problem...')]")) {
                if ($message = $this->http->FindSingleNode("//strong[contains(text(),'[5022:SqlException]')]/..", null, true, "#^\[[^\]]+\](.*?)$#ms")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
            }// if ($this->http->FindSingleNode("//h1[contains(text(),'sorry there has been a problem...')]"))
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function ParseSingleItinerary()
    {
        $it = ["Kind" => "T"];

        $it["RecordLocator"] = $this->http->FindSingleNode("//p[contains(text(), 'Your confirmation number is:')]", null, true, "#\s+([^\s]+)$#");
        $it["Status"] = CleanXMLValue($this->http->FindSingleNode("//th[.='Status']/following-sibling::td[1]"));

        // reservation canceled
        if (preg_match("#^cancel+ed#i", $it["Status"])) {
            $it["Cancelled"] = true;
            //return $it;
        }
        $it["ReservationDate"] = strtotime($this->http->FindSingleNode("//th[.='Booking Date']/following-sibling::td[1]"));

        /* Charge calculate */

        // TotalCharge
        $it["TotalCharge"] = $this->http->FindSingleNode("//th[contains(text(), '(including service charges)')]/following-sibling::td[1]", null, true, '/[\d\.\,]+/ims');
        // Currency
        $it["Currency"] = $this->http->FindSingleNode("//th[contains(text(), '(including service charges)')]/following-sibling::td[1]", null, true, '/([A-Z]{3})/ims');
        // Charge Details
        $nodes = $this->http->XPath->query("//h2[.='Price Breakdown']/following-sibling::div[@class = 'popup-price-breakdown']/table");

        $fares = 0;
        $taxes = 0;

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $fare = $this->http->FindSingleNode("tr/td[.='Fare']/following-sibling::td[1]", $node, true, "#[\-\d\.\,]+#ims");
            $tax = $this->http->FindSingleNode("tr/td[.='Taxes & Charges']/following-sibling::td[1]", $node, true, "#[\-\d\.\,]+#ims");

            if ($fare) {
                $this->http->Log('Fare += ' . $fare);
            }

            if ($tax) {
                $this->http->Log('Taxes += ' . $tax);
            }

            if ($fare) {
                $fares += $fare;
            }

            if ($tax) {
                $taxes += $tax;
            }
            //$fees += $this->http->FindSingleNode("//td[.='Card Fees']/following-sibling::td[1]", $node, true, "#[\-\d\.\,]+#ims");
        }// for ($i = 0; $i < $nodes->length; $i++)

        // BaseFare
        $it["BaseFare"] = $fares;
        // Tax
        $it["Tax"] = $taxes;
        // Flight info
        $nodes = $this->http->XPath->query("//h2[.='Flight Details']/following-sibling::div/table[@class = 'confirmation-table']");
        $this->http->Log('Found ' . $nodes->length . ' segments');
        // Passengers
        $passengersInfo = $this->http->XPath->query("//h2[.='Passenger Details']/following-sibling::div/table[@class = 'confirmation-table']");
        $this->http->Log('Found ' . $nodes->length . ' passenger records');
        $segments = [];

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $segment = [];
            // FlightNumber
            $segment["FlightNumber"] = $this->http->FindSingleNode(".//td[4]", $node, true, "/^Flight\s+(.+)$/");
            // DepName
            $segment["DepName"] = $this->http->FindSingleNode("preceding-sibling::h3[@class = 'itinerary-h3']/text()[last()]", $node, true, "/(.+)\s+to\s+/u", $i);
            // DepCode
            $segment["DepCode"] = TRIP_CODE_UNKNOWN;

            if ($depCode = $this->http->FindPreg("#var FlightsStationsWrapper.*?\"code\":\s*\"([\w\d]{3})\",\s*\"name\":\s*\"" . addcslashes($segment["DepName"], "()/") . "\"#ims")) {
                $segment["DepCode"] = $depCode;
            }
            // ArrName
            $segment["ArrName"] = $this->http->FindSingleNode("preceding-sibling::h3[@class = 'itinerary-h3']/text()[last()]", $node, true, "/\s+to\s+(.+)/u", $i);
            // ArrCode
            $segment["ArrCode"] = TRIP_CODE_UNKNOWN;

            if ($arrCode = $this->http->FindPreg("#var FlightsStationsWrapper.*?\"code\":\s*\"([\w\d]{3})\",\s*\"name\":\s*\"{$segment["ArrName"]}\"#ims")) {
                $segment["ArrCode"] = $arrCode;
            }
            // DepDate
            $segment['DepDate'] = strtotime(
                $this->http->FindSingleNode(".//td[1]", $node) . ', ' .
                $this->http->FindSingleNode(".//td[2]", $node, true, "/^Departing\s+(.+)$/")
            );
            // ArrDate
            $segment['ArrDate'] = strtotime(
                $this->http->FindSingleNode(".//td[1]", $node) . ', ' .
                $this->http->FindSingleNode(".//td[3]", $node, true, "/^Arriving\s+(.+)$/")
            );
            // Passengers
            $segment['Passengers'] = array_map(function ($item) {
                return beautifulName($item);
            }, $this->http->FindNodes(".//th[1]", $passengersInfo->item($i)));
            // Seats
            $seats = $this->http->FindNodes('.//td[contains(., "Seat")]', $passengersInfo->item($i), '#Seat\s+(\d+\w)#');
            $segment['Seats'] = implode(', ', $seats);

            $segments[] = $segment;
        }// for ($i = 0; $i < $nodes->length; $i++)

        $it['TripSegments'] = $segments;

        return $it;
    }

    public function ParseItineraries()
    {
        // check if new reservations appeared
        $this->http->GetURL("https://bookflights.monarch.co.uk/BookingList.aspx");

        if (!$this->http->FindSingleNode("//p[contains(text(), 'No bookings found for future travel')]")) {
            //$this->sendNotification('Seems that new reservations appeared at https://bookflights.monarch.co.uk/BookingList.aspx');
            $this->http->Log("Bookings found!");
        } else {
            $this->http->Log("No bookings found for future travel");

            return $this->noItinerariesArr();
        }
        $result = [];
        $this->http->GetURL('https://bookflights.monarch.co.uk/BookingList.aspx');

        $buttons = $this->http->FindNodes('//div[@id="review-flights"]//a/span[contains(text(),"View Itinerary")]/../@href');

        foreach ($buttons as $js) {
            if (preg_match("#__doPostBack\('([^']+)','([^']+)'\)#", $js, $m)) {
                if (!$this->http->ParseForm("SkySales")) {
                    $this->http->Log("Required form SkySales wasn't found");

                    continue;
                }// if (!$this->http->ParseForm("SkySales"))
                $this->http->Form['__EVENTTARGET'] = 'BookingListBookingListView';
                $this->http->Form['__EVENTARGUMENT'] = $m[2];
                $this->http->Form['__VIEWSTATE'] = $this->http->Form['viewState'];
                $this->http->PostForm();

                $result[] = $this->ParseSingleItinerary();
            } else {
                $this->http->Log("Invalid content or reservation doesn't exists");
            }
        }// foreach ($buttons as $js)

        return $result;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://bookflights.monarch.co.uk/ChangeViewItinerary.aspx";

        return $arg;
    }

    public function GetConfirmationFields()
    {
        return [
            'Email' => [
                "Caption"  => "E-mail",
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('Email'),
                "Required" => true,
            ],
            'ConfNo' => [
                "Caption"  => "Reference number",
                "Type"     => "string",
                "Size"     => 10,
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://bookflights.monarch.co.uk/MemberBookingManager.aspx";
    }

    public function notifications($arFields)
    {
        $this->logger->notice(__METHOD__);
        $this->sendNotification("monarch - failed to retrieve itinerary by conf #", 'all', true,
            "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Email: {$arFields['Email']}");

        return null;
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->FilterHTML = false;
        $this->http->RetryCount = 0;
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm("SkySales")) {
            return $this->notifications($arFields);
        }
        $this->http->Form['__EVENTTARGET'] = 'ControlGroupBookingRetrieveMyBookingView$BookingRetrieveViewMyBookingView$ImageButtonSubmit';
        $this->http->Form['__VIEWSTATE'] = $this->http->Form['viewState'];
        $this->http->Form['ControlGroupBookingRetrieveMyBookingView$BookingRetrieveViewMyBookingView$TextEmailAddress'] = $arFields['Email'];
        $this->http->Form['ControlGroupBookingRetrieveMyBookingView$BookingRetrieveViewMyBookingView$TextConfirmationNumber'] = $arFields['ConfNo'];

        $this->http->PostForm();

        if (!$this->http->FindPreg("/Your confirmation number is:/ims")) {
            return $this->notifications($arFields);
        }

        $it = $this->ParseSingleItinerary();

        return null;
    }
}
