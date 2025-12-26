<?php

class TAccountCheckerOrbitzbus extends TAccountCheckerExtended
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.orbitzforbusiness.net/Secure/OFBSignIn');

        if (!$this->http->ParseForm('main')) {
            return false;
        }
        $this->http->SetInputValue('memberEmail', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindPreg("/body onload=\"document\.forms\[0\].submit\(\);/")) {
            $this->http->PostForm();
        }

        if ($message = $this->http->FindSingleNode("//p[@class='error']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Orbitz for Business is now a part of Egencia
        if ($message = $this->http->FindSingleNode("//div[@class = 'ofbMessage' and contains(text(), 'Orbitz for Business is now a part of Egencia')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//a[contains(@href, 'SignOut')]/@href")) {
            return true;
        }
        // We experienced an error and were unable to complete your request.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We experienced an error and were unable to complete your request.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Your access has expired.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Your access has expired.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // AccountID: 2752393
        if ($this->http->currentUrl() == 'https://w3-03.sso.ibm.com/FIM/sps/IBM_W3_SAML20_EXTERNAL/saml20/logininitial?PartnerId=orbitzforbusiness&Target=https://www.orbitzforbusiness.net/Secure/ProcessSSORequest' && $this->http->Response['code'] == 0) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.orbitzforbusiness.net/Secure/ViewMyAccount?z=4f80&r=8&shadowing=false");
        // set Name property
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//*[@id='myAccount']/div/h3")));

        // Email and Name
        if ($this->http->FindSingleNode("//*[@id='myAccount']/div/p[1]") && !empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->FilterHTML = false;
        $this->http->GetURL("https://www.orbitzforbusiness.net/Secure/PerformDisplayMyTrips?z=6242&r=5d");

        if ($this->http->FindPreg("/(You have no trips booked\.)/ims")) {
            return $this->noItinerariesArr();
        }

        $links = $this->http->FindNodes("//div[@id = 'nav']//a[contains(@href, 'selectedTravelPlanLocatorCode')]/@href");
        $this->http->Log('Found ' . count($links) . ' reservations');

        foreach ($links as $link) {
            $this->http->FilterHTML = false;
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
            // find types of trip
            $airReservationHeaders = $this->http->XPath->query("//h2[contains(text(), 'Flight reservation')]");
            $hotelReservationHeaders = $this->http->XPath->query("//h2[contains(text(), 'Hotel reservation')]");
            $carReservationHeaders = $this->http->XPath->query("//h2[contains(text(), 'Car rental reservation')]");
            $trainReservationHeaders = $this->http->XPath->query("//h2[contains(text(), 'Train ') or contains(text(), 'Rail ')]");
            // View full trip details
            if ($openDetails = $this->http->FindSingleNode("//button[contains(text(), 'View full trip details')]/@onclick", null, true, "/self\.location=\'([^\']+)/ims")) {
                $this->http->Log("Open full trip details");
                $this->http->NormalizeURL($openDetails);
                $this->http->GetURL($openDetails);
                $this->http->FilterHTML = true;
            }

            // parse itineraries
            if ($airReservationHeaders->length > 0) {
                $airReservations = $this->http->XPath->query('//div[@id = "airDetails"]');

                foreach ($airReservations as $ar) {
                    $result[] = $this->ParseItinerary($ar);
                }
            }

            if ($hotelReservationHeaders->length > 0) {
                $hotelReservations = $this->http->XPath->query('//div[@id = "hotelDetails"]');

                foreach ($hotelReservations as $hr) {
                    $result[] = $this->ParseReservations($hr);
                }
            }

            if ($carReservationHeaders->length > 0) {
                $carReservations = $this->http->XPath->query('//div[@id = "carDetails" and contains(., "Pick-up")]');

                foreach ($carReservations as $cr) {
                    $result[] = $this->ParseRentals($cr);
                }
            }

            if ($trainReservationHeaders->length > 0) {
                $this->sendNotification("orbitzbus. Train reservation was found!");
            }
        }

        return $result;
    }

    public function ParseItinerary($airReservationNode)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $result = [];
        $it = ["Kind" => "T"];
        $it["RecordLocator"] = $this->http->FindSingleNode(".//td[contains(text(), 'Airline record locator:')]/following-sibling::td[1]", $airReservationNode, true, '/\-\s*([^\s<]+)/');
        // TripNumber
        $it['TripNumber'] = $this->http->FindSingleNode(".//td[contains(., 'Orbitz record locator:')]/following-sibling::td[1]", $airReservationNode);
        // ReservationDate
        $it["ReservationDate"] = strtotime($this->http->FindSingleNode(".//td[contains(text(), 'Reservation date:')]/following-sibling::td[1]", $airReservationNode));

        // Passengers
        $it["Passengers"] = array_map('beautifulName', $this->http->FindNodes(".//table[@class = 'travelerInformation']/tr/td[1]", $airReservationNode));
        // AccountNumbers
        $it["AccountNumbers"] = implode(', ', $this->http->FindNodes(".//table[@class = 'travelerInformation']/tr/td[4]/dl/dd", $airReservationNode));
        // TotalCharge
        $it["TotalCharge"] = $this->http->FindSingleNode(".//div[@id = 'airCostSummary']//tr[@class = 'total']/td[@class = 'value']", null, true, '/[\d\.\,]+/ims');
        // Currency
        $it["Currency"] = $this->http->FindSingleNode(".//div[@id = 'airCostSummary']//tr[@class = 'total']/td[@class = 'value']", null, true, '/([A-Z]{3})/ims');
        // BaseFare
        $it["BaseFare"] = $this->http->FindSingleNode(".//th[contains(text(), 'Airfare')]/following-sibling::td[@class = 'value']", null, true, '/[\d\.\,]+/ims');
        // Tax
        $it["Tax"] = $this->http->FindSingleNode(".//td[span[contains(text(), 'Online transaction fee')]]/following-sibling::td[@class = 'value']", null, true, '/[\d\.\,]+/ims');

        // Segments
        $slices = $this->http->XPath->query(".//div[@class = 'slice']", $airReservationNode);
        $this->http->Log("Total slices found: " . $slices->length);

        foreach ($slices as $slice) {
            $text = text($slice->nodeValue);
            $date = re('#\w+\s+\d+,\s+\d+#i', $text);
            $this->http->Log("date: " . $date);

            if (!$date) {
                continue;
            }
            $legs = $this->http->XPath->query("div[contains(@class, 'sliceContent')]/div[contains(@class, 'segmentSection') and span]", $slice);
            $timeInfo = $this->http->XPath->query("div[contains(@class, 'sliceContent')]/div[contains(@class, 'segmentSection') and span]/following-sibling::table", $slice);
            $seatInfo = $this->http->XPath->query("div[contains(@class, 'sliceContent')]/div[contains(@class, 'segmentSection') and span]/following-sibling::div[@class = 'legSegInfo']", $slice);
            $this->http->Log("Segments found: " . $legs->length);

            for ($i = 0; $i < $legs->length; $i++) {
                // DepCode
                $depCode = $this->http->FindSingleNode(".//td[contains(text(), 'Depart:')]/following-sibling::td[2]/a", $timeInfo->item($i), true, "/\((\w{3})\)/");

                if (empty($depCode)) {
                    $depCode = $this->http->FindSingleNode(".//td[contains(text(), 'Depart:')]/following-sibling::td[2]/text()[last()]", $timeInfo->item($i), true, "/\((\w{3})\)/");
                }

                if (empty($depCode)) {
                    $depCode = $this->http->FindSingleNode(".//td[contains(text(), 'Depart:')]/following-sibling::td[2]/span[@class = 'openJawCode']", $timeInfo->item($i), true, "/\((\w{3})\)/");
                }
                // DepName
                $depName = $this->http->FindSingleNode(".//td[contains(text(), 'Depart:')]/following-sibling::td[2]/a", $timeInfo->item($i), true, "/[^\(]+/");

                if (empty($depName)) {
                    $depName = $this->http->FindSingleNode(".//td[contains(text(), 'Depart:')]/following-sibling::td[2]/text()[last()]", $timeInfo->item($i), true, "/[^\(]+/");
                }
                // ArrCode
                $arrCode = $this->http->FindSingleNode(".//td[contains(text(), 'Arrive:')]/following-sibling::td[2]/a", $timeInfo->item($i), true, "/\((\w{3})\)/");

                if (empty($arrCode)) {
                    $arrCode = $this->http->FindSingleNode(".//td[contains(text(), 'Arrive:')]/following-sibling::td[2]/text()[last()]", $timeInfo->item($i), true, "/\((\w{3})\)/");
                }

                if (empty($arrCode)) {
                    $arrCode = $this->http->FindSingleNode(".//td[contains(text(), 'Arrive:')]/following-sibling::td[2]/span[@class = 'openJawCode']", $timeInfo->item($i), true, "/\((\w{3})\)/");
                }
                // ArrName
                $arrName = $this->http->FindSingleNode(".//td[contains(text(), 'Arrive:')]/following-sibling::td[2]/a", $timeInfo->item($i), true, "/[^\(]+/");

                if (empty($arrName)) {
                    $arrName = $this->http->FindSingleNode(".//td[contains(text(), 'Arrive:')]/following-sibling::td[2]/text()[last()]", $timeInfo->item($i), true, "/[^\(]+/");
                }

                $result[] = [
                    // AirlineName
                    'AirlineName'  => $this->http->FindSingleNode("span[@class = 'carrier']", $legs->item($i), true, "/([\w\s]+)\s\d+/"),
                    // FlightNumber
                    'FlightNumber' => $this->http->FindSingleNode("span[@class = 'carrier']", $legs->item($i), true, "/\d+/"),
                    // Cabin
                    'Cabin'        => $this->http->FindSingleNode("span[2]", $legs->item($i), true, '/^\s*([^\|]+)/ims'),
                    // BookingClass
                    'BookingClass'        => $this->http->FindSingleNode("span[2]", $legs->item($i), true, '/^\s*[^\|]+\|\s*([A-Z|]{1})\s*\|/ims'),
                    // TraveledMiles
                    'TraveledMiles'=> $this->http->FindSingleNode("span[2]", $legs->item($i), true, '/\|\s*([\d\.\,]+)\s*Miles/ims'),
                    // Aircraft
                    //                    'Aircraft'     => $this->http->FindSingleNode("span[2]", $legs->item($i), true, '/\s*([^\|]+\))\s*\|\s*[^\|]+(?:hr|min)/ims'),
                    'Aircraft'     => $this->http->FindSingleNode("span[2]", $legs->item($i), true, '/\s*([^\|]+\))\s*\|(?:\s*[^\|]+\||\s*)[^\|]+(?:hr|min)/ims'),
                    // Duration
                    'Duration'     => $this->http->FindSingleNode("span[2]", $legs->item($i), true, '/\|\s*([^\|]+(?:hr|min))/ims'),
                    // Meal
                    'Meal'         => $this->http->FindSingleNode("span[2]", $legs->item($i), true, '/\|\s*([a-z\s]+)\s*\|\s*[^\|]+(?:hr|min)/ims'),
                    // DepDate
                    'DepDate'      => strtotime($date . ' ' . $this->http->FindSingleNode(".//td[contains(text(), 'Depart:')]/following-sibling::td[1]", $timeInfo->item($i))),
                    // DepCode
                    'DepCode'      => $depCode,
                    // DepName
                    'DepName'      => $depName,
                    // ArrDate
                    'ArrDate'      => strtotime($date . ' ' . $this->http->FindSingleNode(".//td[contains(text(), 'Arrive:')]/following-sibling::td[1]", $timeInfo->item($i))),
                    // ArrCode
                    'ArrCode'      => $arrCode,
                    // ArrName
                    'ArrName'      => $arrName,
                    // Seats
                    'Seats'        => $this->http->FindSingleNode("p[contains(text(), 'Seat')]", $seatInfo->item($i), true, "/:\s*([^\|<]+)/"),
                ];
            }// foreach ($legs as $context)
        }// foreach ($slices as $slice)
        $it["TripSegments"] = $result;

        return $it;
    }

    public function ParseReservations($hotelReservationNode)
    {
        /** @var \AwardWallet\ItineraryArrays\Hotel $result */
        $result = [];

        $result['Kind'] = 'R';
        // ConfirmationNumber
        $result['ConfirmationNumber'] = $this->http->FindSingleNode(".//dt[contains(text(), 'confirmation number:')]/following-sibling::dd[1]", $hotelReservationNode);
        // TripNumber
        $result['TripNumber'] = $this->http->FindSingleNode(".//dt[contains(text(), 'Orbitz record locator:')]/following-sibling::dd[1]", $hotelReservationNode);

        if (!isset($result['ConfirmationNumber'])) {
            $result['ConfirmationNumber'] = $result['TripNumber'];
        }
        // ReservationDate
        $result['ReservationDate'] = strtotime($this->http->FindSingleNode(".//dt[contains(text(), 'Reservation date')]/following-sibling::dd[1]", $hotelReservationNode));
        // HotelName
        $result['HotelName'] = $this->http->FindSingleNode(".//div[@class = 'vendorName']", $hotelReservationNode);
        // CheckInDate
        $checkInDate = $this->http->FindSingleNode(".//th[contains(text(), 'Check-in:')]/following-sibling::th", $hotelReservationNode);
        $timeInDate = $this->http->FindSingleNode(".//th[contains(text(), 'Check-in:')]/following-sibling::td", $hotelReservationNode);

        if ($checkInDate and $timeInDate) {
            $result['CheckInDate'] = strtotime($checkInDate . ' ' . $timeInDate);
        }
        // CheckOutDate
        $checkOutDate = $this->http->FindSingleNode(".//th[contains(text(), 'Check-out:')]/following-sibling::th", $hotelReservationNode);
        $timeOutDate = $this->http->FindSingleNode(".//th[contains(text(), 'Check-out:')]/following-sibling::td", $hotelReservationNode);

        if ($checkOutDate and $timeOutDate) {
            $result['CheckOutDate'] = strtotime($checkOutDate . ' ' . $timeOutDate);
        }
        // Address
        $result['Address'] = implode(', ', $this->http->FindNodes(".//div[@class = 'vendorAddress']/text()", $hotelReservationNode));
        // Phone
        $result['Phone'] = CleanXMLValue($this->http->FindHTMLByXpath(".//div[@class = 'vendorPhoneNumbers']", '/Phone:\s*<\/strong>\s*([^<]+)/ims', $hotelReservationNode));
        // Fax
        $result['Fax'] = CleanXMLValue($this->http->FindHTMLByXpath(".//div[@class = 'vendorPhoneNumbers']", '/Fax:\s*<\/strong>\s*([^<]+)/ims', $hotelReservationNode));
        // Guests
        $result['Guests'] = $this->http->FindSingleNode(".//dt[contains(text(), 'Total guests:')]/following-sibling::dd[1]", $hotelReservationNode);
//        $result['Kids'] = 0;
        // Rooms
        $result['Rooms'] = $this->http->FindSingleNode(".//dt[contains(text(), 'Total rooms:')]/following-sibling::dd[1]", $hotelReservationNode);
        // RoomTypeDescription
        $roomDesc = $this->http->FindNodes(".//strong[contains(text(), 'Room description:')]/parent::p/text()", $hotelReservationNode);

        if (isset($roomDesc[0])) {
            $result['RoomTypeDescription'] = preg_replace("/^\s\|\s*/", '', implode(' | ', $roomDesc));
        }
        // CancellationPolicy
        $result['CancellationPolicy'] = $this->http->FindSingleNode(".//dt[contains(text(), 'Cancellation:')]/following-sibling::dd[1]", $hotelReservationNode);

//        $result['Taxes'] = $this->http->FindSingleNode('//tr[contains(@class, "hotelTaxesFeesLink")]/td[last()]', null, true, '/\d+[\.\,]?\d*[\.\,]?\d*/');
        // Total
        $result["Total"] = $this->http->FindSingleNode(".//dt[contains(text(), 'Total charges')]/following-sibling::dd[1]", $hotelReservationNode, true, '/[\d\.\,]+/ims');
        // Currency
        $result["Currency"] = $this->http->FindSingleNode(".//dt[contains(text(), 'Total charges')]/following-sibling::dd[1]", $hotelReservationNode, true, '/([A-Z]{3})/ims');
        // GuestNames
        $result['GuestNames'] = beautifulName($this->http->FindSingleNode(".//dt[contains(text(), 'Reservation made for')]/following-sibling::dd[1]", $hotelReservationNode));

        return $result;
    }

    public function ParseRentals($carReservationNode)
    {
        /** @var \AwardWallet\ItineraryArrays\CarRental $result */
        $result = [];

        $result['Kind'] = 'L';
        // Number
        $result['Number'] = $this->http->FindSingleNode(".//dt[contains(text(), 'Confirmation number:')]/following-sibling::dd[1]", $carReservationNode);
        // ReservationDate
        $result['ReservationDate'] = strtotime($this->http->FindSingleNode(".//dt[contains(text(), 'Reservation date')]/following-sibling::dd[1]", $carReservationNode));
        // RentalCompany
        $result['RentalCompany'] = CleanXMLValue($this->http->FindHTMLByXpath(".//div[@class = 'vendorName']", '/>\s*([^<]+)<span/ims', $carReservationNode));
        // PickupDatetime
        $result['PickupDatetime'] = strtotime($this->http->FindSingleNode(".//th[contains(text(), 'Pick-up')]/following-sibling::th", $carReservationNode));
        // DropoffDatetime
        $result['DropoffDatetime'] = strtotime($this->http->FindSingleNode(".//th[contains(text(), 'Drop-off')]/following-sibling::th", $carReservationNode));
        // PickupLocation
        $result['PickupLocation'] = $this->http->FindSingleNode(".//th[contains(text(), 'Pick-up')]/following-sibling::td/text()[1]", $carReservationNode);
        // DropoffLocation
        $result['DropoffLocation'] = $this->http->FindSingleNode(".//th[contains(text(), 'Drop-off')]/following-sibling::td/text()[1]", $carReservationNode);
        // PickupPhone
        $result['PickupPhone'] = CleanXMLValue($this->http->FindSingleNode(".//th[contains(text(), 'Pick-up')]/following-sibling::td/p/strong[contains(text(), 'Phone:')]/following-sibling::span", $carReservationNode));
        // PickupHours
        $result['PickupHours'] = CleanXMLValue($this->http->FindSingleNode(".//th[contains(text(), 'Pick-up')]/following-sibling::td/p[strong[contains(text(), 'Hours:')]]", $carReservationNode, true, '/:\s*([^<]+)/ims'));
        // TotalCharge
        $result["TotalCharge"] = $this->http->FindSingleNode(".//div[@class = 'totalPrice']", null, true, '/[\d\.\,]+/ims');
        // TotalTaxAmount
        $result["TotalTaxAmount"] = $this->http->FindSingleNode(".//td[contains(text(), 'Taxes and fees')]/following-sibling::td[1]", null, true, '/[\d\.\,]+/ims');
        // Currency
        $result["Currency"] = $this->http->FindSingleNode(".//div[@class = 'totalPrice']", null, true, '/([A-Z]{3})/ims');
        // RenterName
        $result['RenterName'] = beautifulName($this->http->FindSingleNode(".//dt[contains(text(), 'Primary driver')]/following-sibling::dd[1]", $carReservationNode));

        return $result;
    }
}
