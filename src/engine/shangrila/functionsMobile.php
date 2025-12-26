<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerShangrilaMobile extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->LogHeaders = true;

        if ($this->account == 2) {
            $this->sendNotification("attempt via proxy // RR");
            $this->http->SetProxy($this->proxyReCaptcha());
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://goldencircle-m.shangri-la.com/accountSummary.aspx', [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://goldencircle-m.shangri-la.com/accountSummary.aspx");

        $this->logger->debug("Mobile version");

        if (!$this->http->ParseForm("ctl00")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('ctl00$MainContent$loginNumber', $this->AccountFields["Login"]);
        $this->http->SetInputValue('ctl00$MainContent$password', $this->AccountFields["Pass"]);
        $this->http->SetInputValue('ctl00$MainContent$rememberLogin1', 'on');
        $this->http->SetInputValue('ctl00$MainContent$signin', 'Sign In');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->Response['code'] == 302) {
            throw new CheckRetryNeededException(3, 15);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }
        // Password is a required field
        if ($message = $this->http->FindPreg("/showAlert\('(Password is a required field.)'\);/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Please enter a valid Golden Circle Membership Number.
        if ($message = $this->http->FindPreg("/\('(Please\+enter\+a\+valid\+Golden\+Circle\+Membership\+Number.)'\)/")) {
            throw new CheckException(urldecode($message), ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/showAlert\('(Your sign-in attempt was not successful. Please try again.)'\);/")) {
            throw new CheckRetryNeededException(4, 10, $message);
        }

        // retry, (Unknown Error)
        if (
            $this->http->FindSingleNode("//div[contains(text(), 'Unknown Error.')]")
            && $this->http->currentUrl() == 'https://goldencircle-m.shangri-la.com/Error.aspx?aspxerrorpath=/myAccount.aspx'
        ) {
            // AccountID: 2409267, 4513847
            throw new CheckRetryNeededException(3, 7, "Membership Number / Password is not valid. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->currentUrl() == 'https://goldencircle-m.shangri-la.com/myAccount.aspx'
            && $this->http->ParseForm("ctl00")) {
            throw new CheckRetryNeededException(3, 5);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@id='MainContent_welcomeLb']", null, false, '/Welcome \w{2,3}\s+(.+)/')));
        //# Membership Number
        $this->SetProperty("Number", str_replace(' ', '', $this->http->FindSingleNode("//span[@id='MainContent_gcMemberIdLb']")));
        //# Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//span[@id='MainContent_memberDate']"));
        //# Current Tier
        $this->SetProperty("CurrentTier", beautifulName($this->http->FindSingleNode("//span[@id='MainContent_tier']")));

        //# Qualifying Nights Completed
        $this->SetProperty("QualifiedRoomNights", $this->http->FindSingleNode("//span[@id='MainContent_qNightsLb']"));
        //# Qualifying Stays Completed
        $this->SetProperty("QualifyingStays", $this->http->FindSingleNode("//span[@id='MainContent_qStaysLb']"));
        //# Balance - GC Award Points Balance
        $this->SetBalance($this->http->FindSingleNode("//span[@id='MainContent_gcPointsAvailableLb']"));

        // Expire date
        $nodes = $this->http->XPath->query("//tr[td[contains(text(),'Points will expire on')]]");
        $minDate = strtotime('01/01/3018');

        foreach ($nodes as $node) {
            $expDate = $this->http->FindSingleNode("td[1]", $node, false, '/\d+ \w+ \d{4}/');
            $this->logger->debug("Expiration Date: {$expDate}");
            $expDate = strtotime($expDate, false);

            if ($expDate && $expDate < $minDate) {
                $minDate = $expDate;
                $this->SetExpirationDate($minDate);
                $this->SetProperty('PointsToExpire', $this->http->FindSingleNode("td[2]/span", $node, false, '/^([\d.,]+)$/'));
            }
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->GetURL('https://www.shangri-la.com/mobile/reservations/anondetails/');

        if (!$this->http->ParseForm('aspnetForm')) {
            return $result;
        }
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$ctrlAccountSignIn$UserName', $this->AccountFields["Login"]);
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$ctrlAccountSignIn$Password', $this->AccountFields["Pass"]);
        $this->http->SetInputValue('__EVENTTARGET', 'ctl00$ContentPlaceHolder1$ctrlAccountSignIn$btnSignIn');

        if (!$this->http->PostForm()) {
            return $result;
        }

        // You have no upcoming reservations at this time.
        if ($this->http->FindSingleNode("//div[@id='ctl00_ContentPlaceHolder1_pnlNoReservations']//div[contains(text(),'You have no upcoming reservations at this time.')]")) {
            return $this->noItinerariesArr();
        }

        $arrayLinks = [];
        $links = $this->http->FindNodes("//span[contains(text(), 'Confirmation Number:')]/following-sibling::a/@href");

        foreach ($links as $link) {
            $this->http->NormalizeURL($link);
            $arrayLinks[] = $link;
        }

        foreach ($arrayLinks as $link) {
            $this->http->GetURL($link);

            if ($url = $this->http->FindSingleNode("//a[@id='ctl00_ContentPlaceHolder1_hypModifyBooking']/@href")) {
                $this->http->NormalizeURL($url);
                $this->http->GetURL($url);

                if ($res = $this->ParseItinerary()) {
                    $result[] = $res;
                }
            }
        }

        return $result;
    }

    public function ParseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => 'R'];
        // ConfirmationNumber
        $result['ConfirmationNumber'] = $this->http->FindSingleNode("//h2[contains(text(),'Confirmation Number:')]", null, false, '/:\s*(\d+)/');
        $this->logger->info('Parse Itinerary #' . $result['ConfirmationNumber'], ['Header' => 3]);

        $result['CheckInDate'] = strtotime($this->http->FindSingleNode("//span[contains(text(),'Check-in Date:')]/following-sibling::span"), false);
        $result['CheckOutDate'] = strtotime($this->http->FindSingleNode("//span[contains(text(),'Check-out Date:')]/following-sibling::span"), false);

        $result['Rooms'] = $this->http->FindSingleNode("//span[contains(text(),'Rooms:')]/following-sibling::span");
        $result['Guests'] = $this->http->FindSingleNode("//span[contains(text(),'Adults (per room):')]/following-sibling::span");
        $result['Kids'] = $this->http->FindSingleNode("//span[contains(text(),'Adults (per room):')]/following-sibling::span");
        $result['RoomType'] = $this->http->FindSingleNode("//span[contains(text(),'Room Type:')]/following-sibling::span");
        $result['RateType'] = $this->http->FindSingleNode("//span[contains(text(),'Rate Selected:')]/following-sibling::span");
        $result['RoomTypeDescription'] = $this->http->FindSingleNode("//span[contains(text(),'Rate Selected:')]/ancestor::div[1]/following-sibling::div");

        $result['GuestNames'][] = $this->http->FindSingleNode("//span[text()='Name:']/following-sibling::span");

        $result['Taxes'] = $this->http->FindSingleNode("//span[contains(text(),'Service Charge and Tax:')]/following-sibling::span", null, false, '/[A-Z]{3}\s*([\d.,]+)/');

        if ($sum = $this->http->FindSingleNode("//span[contains(text(),'Total Room Charges (incl. taxes):')]/following-sibling::div/span")) {
            $result['Total'] = $this->http->FindPreg('/[A-Z]{3}\s*([\d.,]+)/', false, $sum);
            $result['Currency'] = $this->http->FindPreg('/([A-Z]{3})\s*[\d.,]+/', false, $sum);
        }

        if ($url = $this->http->FindSingleNode("//a[@id='ctl00_ContentPlaceHolder1_hypHotelName']/@href")) {
            $result['HotelName'] = $this->http->FindSingleNode("//a[@id='ctl00_ContentPlaceHolder1_hypHotelName']");
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
            $address = $this->http->FindSingleNode("//div[@class='hotel-page-content']/h1/text()[1]");
            $result['Address'] = $this->http->FindPreg('/^(.+?)\s+T:/', false, $address) ?: $address;

            if (!$result['Address']) {
                $result['Address'] = $this->http->FindSingleNode("//dt[normalize-space(text())='Address']/following-sibling::dd[1]");
            }

            if (!$result['Address']) {
                $this->sendNotification('shangrila - new address');
            }
            $result['Phone'] = $this->http->FindSingleNode("//div[@class='hotel-page-content']/h1/a[@class='telephone']");

            if (!$result['Phone']) {
                $result['Phone'] = $this->http->FindSingleNode("//dt[normalize-space(text())='Phone']/following-sibling::dd[1]");
            }
        }
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a/span[contains(text(), 'Sign Out')]")) {
            return true;
        }

        return false;
    }
}
