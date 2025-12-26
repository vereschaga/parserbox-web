<?php

class TAccountCheckerKimpton extends TAccountChecker
{
    protected $collectedHistory = false;
    protected $endHistory = false;
    private $noItineraries = false;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Visits']) && isset($properties['Nights']) && !isset($properties['SubAccountCode'])) {
            return "{$properties['Visits']} Stays<br>{$properties['Nights']} Nights";
        } else {
            return parent::FormatBalance($fields, $properties);
        }
    }

    public function LoadLoginForm()
    {
        // Kimpton Karma has Joined IHG® Rewards Club
        throw new CheckException("Kimpton Karma Rewards has rolled into IHG® Rewards Club and has become one program with a single reward points system.", ACCOUNT_PROVIDER_ERROR);
        $this->http->removeCookies();
        $this->http->GetURL("http://www.kimptonhotels.com/intouch/KITLogin.aspx");

        if (!$this->http->ParseForm(null, 1, true, "//form[contains(@action, 'sign-in')]")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("signin[email]", $this->AccountFields['Login']);
        $this->http->SetInputValue("signin[password]", $this->AccountFields['Pass']);

        foreach ($this->http->Form as $k => $v) {
            if (strstr($k, 'member[')) {
                unset($this->http->Form[$k]);
            }
        }

        return true;
    }

    public function checkErrors()
    {
        // KimptonHotels.com is temporarily unavailable
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "KimptonHotels.com is temporarily unavailable")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Karma is offline, but you can still make reservations.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Karma is offline, but you can still make reservations.")]')) {
            throw new CheckException("Karma is offline. We'll be back on our feet shortly.", ACCOUNT_PROVIDER_ERROR);
        }
        // Karma Rewards is offline for our annual tier reset
        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "Karma Rewards is offline for our annual tier reset")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Happy New Year!
         *
         * We’re updating your Karma Rewards member profile with your 2017 tier status.
         * Everything will be updated by January 10th so check back then.
         * You can still make a reservation as a Karma Rewards member online. To get there, click here.
         * Need help? No problem, just call us at (888) 695-4678. We'd be happy to take care of you over the phone.
         */
        if ($this->http->FindPreg('/We’re updating your Karma Rewards member profile with your 2017 tier status./')
            && $this->http->FindPreg('/Everything will be updated by January 10th so check back then\./')) {
            throw new CheckException("Happy New Year! We’re updating your Karma Rewards member profile with your 2017 tier status. Everything will be updated by January 10th so check back then.", ACCOUNT_PROVIDER_ERROR);
        }
//        // Service Temporarily Unavailable
//        if ($message = $this->http->FindSingleNode('//strong[contains(text(), "We\'re down, but you don\'t have to be")]'))
//            throw new CheckException("Service Temporarily Unavailable", ACCOUNT_PROVIDER_ERROR);
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            // Server Error in '/' Application
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // provider error
        if ($this->http->currentUrl() == 'https://www.kimptonhotels.com/my-karma/dashboard'
            && $this->http->Response['code'] == 500) {
            throw new CheckException("KimptonHotels.com is temporarily unavailable. Our apologies for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }
        // maintenance
        if ($this->http->FindPreg("/my-karma\/reset-404/", false, $this->http->currentUrl())
            && $this->http->Response['code'] == 404
            && ($message = $this->http->FindSingleNode('//p[contains(text(), "We’re updating your Karma Rewards member profile with your ' . date("Y") . ' tier status")] | (//h2[contains(text(), "Karma Rewards is offline")])[1]'))) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Wrong Username or Password
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Wrong Username or Password')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Access is allowed
        if ($this->http->FindSingleNode("(//a[contains(@href, 'sign-out')])[1]")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() == 'https://www.kimptonhotels.com/my-karma/complete-profile-landing') {
            $this->logger->notice("Skip profile update");
            $this->http->GetURL("https://www.kimptonhotels.com/my-karma/dashboard");
        }// if ($this->http->currentUrl() == 'https://www.kimptonhotels.com/my-karma/complete-profile-landing')

        // Stays
        $this->SetProperty("Visits", $this->http->FindSingleNode("//div[@class = 'night_progress-info']/h4[contains(text(), 'stay')]", null, true, '/(\d+)/ims'));
        // Nights
        $nights = $this->http->FindSingleNode("//div[@class = 'night_progress-info']/h4[contains(text(), 'nights')]", null, true, '/(\d+)/ims');
        $this->SetProperty("Nights", $nights);
        // Balance - Nights
        $this->SetBalance($nights);
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//h2[@class = 'karma-member-name']"));
        // Karma ID
        $this->SetProperty("Number", $this->http->FindSingleNode("//h3[contains(text(), 'Karma ID:')]/span"));
        // Status
        $this->SetProperty('Status', $this->http->FindSingleNode("//h3[contains(text(), 'Karma Tier')]/span"));

        // for itineraries
        if ($this->http->FindSingleNode('//p[contains(text(), "You have no reservations.")]')) {
            $this->http->Log("No itineraries were found");
            $this->noItineraries = true;
        }

        // Rewards   // refs #5634
        $rewards = $this->http->FindSingleNode("//span[contains(text(), 'See My Rewards')]/following-sibling::span[1]");

        if ($rewards) {
            $this->http->GetURL("https://www.kimptonhotels.com/my-karma/rewards-benefits");
            $nodes = $this->http->XPath->query("//div[@class = 'karma-rewards-touts']/div");
            $this->http->Log("Total {$nodes->length} rewards were found");

            for ($i = 0; $i < $nodes->length; $i++) {
                // Certificate No
                $sertificateNumber = $this->http->FindSingleNode(".//div[@class = 'tout-code']", $nodes->item($i));
                $expiration = $this->http->FindSingleNode(".//div[@class = 'tout-expiry']", $nodes->item($i), true, "/Expires\s*:\s*([^<]+)/ims");
                $expiration = strtotime($expiration);

                if (!empty($sertificateNumber) && $expiration != false) {
                    $subAccounts[] = [
                        'Code'           => "KimptonRewards" . str_replace(' ', '', $sertificateNumber),
                        'DisplayName'    => CleanXMLValue($sertificateNumber),
                        'Balance'        => null,
                        "ExpirationDate" => $expiration,
                    ];
                }// if (isset($balance))
            }// for ($i = 0; $i < $nodes->length; $i++)

            if (isset($subAccounts)) {
                $this->SetProperty("CombineSubAccounts", false);
                $this->http->Log("Total subAccounts: " . count($subAccounts));
                //# Set SubAccounts Properties
                $this->SetProperty("SubAccounts", $subAccounts);
            }// if(isset($subAccounts))
        }
        // Expiration Date   // refs #5634
        $this->http->GetURL("https://www.kimptonhotels.com/my-karma/stays");
        // Table "Where You've Been"
        $lastActivities = $this->http->FindNodes("//div[@id = 'checked_out_list']/table[@class = 'table']//tr[td]/td[1]", null, "/[^\,]+/ims");
        $lastActivities = array_reverse($lastActivities);

        foreach ($lastActivities as $lastActivity) {
            if (!isset($this->Properties['LastActivity']) || strtotime($this->Properties['LastActivity']) < strtotime($lastActivity)) {
                // Last Activity
                $this->SetProperty("LastActivity", $lastActivity);

                $lastActivity = strtotime($lastActivity);

                if ($lastActivity != false && $lastActivity > (strtotime("-2 year"))) {
                    $this->http->Log("Expiration Date = Last activity + 24 months");
                    $exp = strtotime("+ 24 month", $lastActivity);
                    $this->SetExpirationDate($exp);
                }// if ($lastActivity != false && $lastActivity > (strtotime("-2 year")))
            }// if (!isset($this->Properties['LastActivity']) || strtotime($this->Properties['LastActivity']) < strtotime($lastActivity))
        }// foreach ($lastActivities as $lastActivity)
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'http://www.kimptonhotels.com/intouch/KITLogin.aspx';

        return $arg;
    }

    public function ParseItineraries()
    {
        $result = [];
        $links = [];
        // no Itineraries
        if ($this->noItineraries) {
            return $this->noItinerariesArr();
        }
        // View all reservations
        if ($this->http->currentUrl() != 'https://www.kimptonhotels.com/my-karma/stays') {
            $this->http->GetURL("https://www.kimptonhotels.com/my-karma/stays");
        }
        // it is necessary that a Xpath worked
        $page = 1;
        $link = null;

        do {
            if (isset($link)) {
                $this->http->GetURL($link);
            }
            $this->http->Log('parsing page ' . $page);
            $this->http->Response['body'] = str_replace(['article', 'section'], 'div', $this->http->Response['body']);
            $this->http->SetBody($this->http->Response['body'], true);
            $confs = $this->http->FindNodes("//strong[contains(text(), 'Confirmation:')]/parent::div", null, "/:\s*([^<]+)/ims");
            $this->http->Log("Total " . count($confs) . " reservations were found");

            foreach ($confs as $conf) {
                $result[] = $this->ParseItinerary($conf);
            }
            $page++;

            if ($link = $this->http->FindSingleNode(sprintf('//a[@href="/my-karma/stays?page=%d"]/@href', $page), null, false, null, 1)) {
                $this->http->NormalizeURL($link);
            }
        } while (isset($link) && $page < 6);

        return $result;
    }

    public function ParseItinerary($conf)
    {
        $result = [];

        $result['Kind'] = 'R';
        // ConfirmationNumber
        $result['ConfirmationNumber'] = $conf;
        // HotelName
        $result['HotelName'] = $this->http->FindSingleNode("//div[contains(., '{$result['ConfirmationNumber']}')]/ancestor::*[contains(@class, 'hotel_tile-content')][1]/preceding::div[1]/h2");
        // Status
        $result['Status'] = $this->http->FindSingleNode("//div[contains(., '{$result['ConfirmationNumber']}')]/following-sibling::div[strong[contains(text(), 'Status:')]]", null, true, "/:\s*([^<\,]+)/ims");
        // RateType
        $result['RateType'] = $this->http->FindSingleNode("//div[contains(., '{$result['ConfirmationNumber']}')]/following-sibling::div[strong[contains(text(), 'Rate Eligibility:')]]", null, true, "/:\s*([^<\,]+)/ims");
        // CheckInDate
        if ($date = $this->http->FindSingleNode("//div[contains(., '{$result['ConfirmationNumber']}')]/preceding-sibling::div[strong[contains(text(), 'Arrival Date:')]]", null, true, "/:\s*([^<\,]+)/ims")) {
            $result['CheckInDate'] = strtotime($date);
            // CheckOutDate
            if ($nights = $this->http->FindSingleNode("//div[contains(., '{$result['ConfirmationNumber']}')]/preceding-sibling::div[strong[contains(text(), 'Arrival Date:')]]", null, true, "/:\s*[^<\,]+\s*\,\s*(\d+)/ims")) {
                $this->http->Log("nights: {$nights}");
                $result['CheckOutDate'] = strtotime("+{$nights} day", $result['CheckInDate']);
            }
        }
        // Address
        $address = $this->http->FindNodes("//div[contains(., '{$result['ConfirmationNumber']}')]/ancestor::div[@class = 'hotel_tile-stay-info-area']/preceding-sibling::div/div[contains(@class, 'hotel_tile-stay-contact')]/text()");
        $result['Address'] = implode(' ', array_filter($address));
        // Phone
        $result['Phone'] = $this->http->FindSingleNode("//div[contains(., '{$result['ConfirmationNumber']}')]/ancestor::div[@class = 'hotel_tile-stay-info-area']/preceding-sibling::div/div[contains(@class, 'hotel_tile-stay-contact')]/a[contains(@class, 'hotel_tile-stay-contact-phone')]");
        /*// Fax
        $result['Fax'] = $this->http->FindSingleNode('//td[contains(text(), "Fax")]/following-sibling::td[1]');

        $result['GuestNames'] = $this->http->FindSingleNode('//table[@class="DtlGuestInfoTbl"]//td[contains(text(), "Name") and not(contains(text(), "Hotel"))]/following-sibling::td[1]');

        $result['Guests'] = $this->http->FindSingleNode('//td[contains(text(), "Number of Guests")]/following-sibling::td[1]/span');

        $result['Rooms'] = $this->http->FindSingleNode('//td[contains(text(), "Number of Rooms")]/following-sibling::td[1]');

        $result['Rate'] = $this->http->FindSingleNode('//td[contains(text(), "Rate")]/following-sibling::td[1]');

        $result['RoomTypeDescription'] = implode(', ', $this->http->FindNodes('//div[@class="RoomRequestsItem"]'));

        $result['Cost'] = $this->http->FindSingleNode('//td[contains(text(), "Room Total")]/following-sibling::td[1]', null, true, '/([\d,\.]+)/');
        $result['Taxes'] = $this->http->FindSingleNode('//td[contains(text(), "Tax")]/following-sibling::td[1]', null, true, '/([\d,\.]+)/');
        $result['Total'] = $this->http->FindSingleNode('//td[contains(text(), "Total") and not(contains(text(), "Room"))]/following-sibling::td[1]', null, true, '/([\d,\.]+)/');
        $result['Currency'] = $this->http->FindSingleNode('(//td[contains(text(), "Total")]/following-sibling::td[1])[1]', null, true, '/([A-Z]{3})/');*/

        return $result;
    }

    public function GetHistoryColumns()
    {
        return [
            "Arrival"        => "PostingDate",
            "Hotels"         => "Description",
            "Nights"         => "Miles",
            "City"           => "Info",
            "Confirmation #" => "Info",
            "Rate Type"      => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->FilterHTML = false;
        $this->http->Log("[History start date: " . intval($startDate) . " ]");
        $result = [];
        $startTimer = microtime(true);

        if ($this->http->currentUrl() != 'https://www.kimptonhotels.com/my-karma/stays') {
            $this->http->GetURL('https://www.kimptonhotels.com/my-karma/stays');
        }
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParseHistoryPage($startIndex, $startDate));

        $years = $this->http->FindNodes("//select[@id = 'checked_out_year_year']/option/@value");

        for ($i = 1; $i < count($years) && !$this->endHistory && !$this->collectedHistory; $i++) {
            $this->http->GetURL("https://www.kimptonhotels.com/my-karma/stays/?checked_out_year={$years[$i]}&_=" . time() . date("B"));
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParseHistoryPage($startIndex, $startDate));
        }

        usort($result, function ($a, $b) { return $b['Arrival'] - $a['Arrival']; });

        $this->http->Log("[Time parsing: " . (microtime(true) - $startTimer) . "]");

        return $result;
    }

    public function ParseHistoryPage($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query("//table[@class = 'table']//tr");
        $this->http->Log("Found {$nodes->length} items");

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);

            $dateStr = $this->http->FindSingleNode("td[1]", $nodes->item($i), true, "/[^\,]+/ims");
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->http->Log("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                continue;
            }

            if (empty($postDate)) {
                continue;
            }
            $result[$startIndex]['Arrival'] = $postDate;
            $result[$startIndex]['Nights'] = $this->http->FindSingleNode("td[1]", $nodes->item($i), true, "/\,\s*([^<]+)/ims");
            $result[$startIndex]['Hotels'] = $this->http->FindSingleNode('td[2]', $node);
            $result[$startIndex]['City'] = $this->http->FindSingleNode('td[3]', $node);
            $result[$startIndex]['Confirmation #'] = $this->http->FindSingleNode('td[4]', $node, true, '/Confirmation\s*:\s*([^<]+)/ims');
            $result[$startIndex]['Rate Type'] = $this->http->FindSingleNode('td[5]', $node);
            $startIndex++;
        }

        return $result;
    }
}
