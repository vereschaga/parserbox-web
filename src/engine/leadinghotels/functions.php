<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerLeadinghotels extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.lhw.com/account/dashboard';

    private $itin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = true;

        $this->UseSelenium();
//        $this->disableImages();
        /*
        $this->useGoogleChrome();
        */
        $this->useFirefoxPlaywright();
        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        /*
        $this->http->SetProxy($this->proxyReCaptchaIt7());
        */
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
        // Please enter a valid email address.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        try {
            $this->http->GetURL("https://www.lhw.com/login");
        } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 1);
        }
        /*
        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('EmailInput', $this->AccountFields['Login']);
        $this->http->SetInputValue('PasswordInput', $this->AccountFields['Pass']);
        */

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "EmailInput"]'), 7);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "PasswordInput"]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput) {
            return $this->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $button = $this->waitForElement(WebDriverBy::xpath("//form[@id = 'loginForm']//button[contains(@class, 'btn-submit') and not(@disabled)]"), 0);

        if (!$button) {
            return false;
        }

        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(),"Our site is temporarily unavailable due to planned system upgrades currently in progress.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# LHW.com is currently unavailable.
        if ($message = $this->http->FindSingleNode('//strong[contains(text(),"LHW.com is currently unavailable")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are in the process of making LHW.com even better for you, so the site is temporarily unavailable.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are in the process of making LHW.com even better for you')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Provider Error
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "There is a problem with the page you are looking for and it can\'t be displayed.")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Our site is temporarily unavailable due to system upgrades currently in progress.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Our site is temporarily unavailable due to system upgrades currently in progress.")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //h1[contains(@class, "user-name")]
            | //span[contains(@class, "is-invalid") and normalize-space(text()) != ""] 
        '), 10);
        $this->saveResponse();
//        if (!$this->http->PostForm()) {
//            return $this->checkErrors();
//        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindPreg('/data-valmsg-replace="true">(The email address and\/or password couldn\&\#39;t be found\. Please enter a valid email address and password combination\.)</')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('(//span[contains(text(), "We don\'t recognize this email address")])[1]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Please enter a valid email address.
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Please enter a valid email address.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We were unable to process your request. Please try again later.
        if ($message = $this->http->FindSingleNode('//form[@id = "loginForm"]//span[contains(text(), "We were unable to process your request. Please try again later.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Please reset your password
        if ($message = $this->http->FindSingleNode('//h6[contains(text(), "Please reset your password")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($message = $this->http->FindSingleNode('//span[contains(@class, "is-invalid") and normalize-space(text()) != ""]')) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'The email address and/or password couldn\'t be found. Please enter a valid email address and password combination.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//a[@id='dropdownMyAccount']", null, false, '/^([^(]+)/')));
        // Leaders Club Member
        $this->SetProperty("Status", $this->http->FindSingleNode("//div[contains(@class,'dropdown-account')]//p[contains(text(),' Club ') and contains(.,' ID ')]/text()[1]"));
        // Member ID
        $this->SetProperty("MemberNumber", $this->http->FindSingleNode("//div[contains(@class,'dropdown-account')]//p[contains(.,'Member ID')]", null, false, '/ID\s+(\d+)/'));
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//div[contains(@class,'dropdown-account')]//p[contains(.,'Member Since')]", null, false, '/Since\s+([\w\s]+)/'));
        // Balance - Points
        $balance = $this->http->FindSingleNode('//h1[@id = "point-counter"]/@data-points-balance');

        if (is_numeric($balance)) {
            $this->SetBalance(round($balance));
        }

        // Membership Expiration - Expires
        $this->SetProperty("MembershipExpiration", $this->http->FindSingleNode("//div[contains(@class,'dropdown-account')]//p[contains(.,'Expires')]/following-sibling::p[1]"));

        if ($exp = $this->http->FindSingleNode("//div[contains(@class,'dropdown-account')]//p[contains(text(), 'Available until')]", null, true, "/until\s*([^<]+)/")) {
            $this->SetExpirationDate(strtotime($exp));
        }

        // Dollars Spent - SPENT
        //$this->SetProperty("DollarsSpent", $this->http->FindSingleNode("//h7[contains(text(),'SPENT')]/preceding-sibling::h4"));
        $this->SetProperty("DollarsSpent", $this->http->FindSingleNode("//div[@id='donut-chart']//h4"));

        // Value (370.08 USD Value)
        $this->SetProperty("Value", $this->http->FindSingleNode("//h1[@id='point-counter']/following-sibling::p[contains(text(),'Value')]", null, false, '/^\((.+?)\s*Value/'));

        if ($this->http->FindSingleNode("//p[contains(text(),'Pre-Arrival Upgrades')]")) {
            if ($balance = $this->http->FindPreg('#h7 class="h7-bold">([\d.,]+) AVAILABLE</h7#')) {
                $this->SetProperty("CombineSubAccounts", false);
                $this->AddSubAccount([
                    'Code'        => 'leadinghotelsPreArrivalUpgrades',
                    'DisplayName' => 'Pre-Arrival Upgrades',
                    'Balance'     => $balance,
                ], true);
            }
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Not a member
            if (
                $this->http->FindSingleNode("(//a[contains(text(), 'Join Leaders Club')])[1]")
                || $this->http->FindPreg("/<h8>Become a Member<\/h8>/")
            ) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
            // Your Leaders Club Membership expired on ... .
            if (
                $message = $this->http->FindSingleNode('//div[@class = "message" and contains(., "Your Leaders Club Membership expired on")]')
                    ?? $this->http->FindSingleNode('//div[@id = "userAccountNavMobile"]//p[contains(., "Leaders Club Membership Expired")]')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            /*
             AccountID: 3000404
            */
            if (
                !empty($this->Properties['Name'])
                && !empty($this->Properties['MemberNumber'])
                && !empty($this->Properties['MemberSince'])
                && !empty($this->Properties['Status'])
                && !empty($this->Properties['MembershipExpiration'])
                && $this->http->FindPreg('/<h7 class="points h7-bold">N\/A Points<\/h7>/')
            ) {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
        elseif (empty($this->Properties['Status'])) {
            $this->sendNotification('refs #16999, Check Status');
        }
    }

    // refs #16999#note-12
    public function ParseItineraries()
    {
        $result = [];
        $this->http->setDefaultHeader('X-Requested-With', 'XMLHttpRequest');
        $this->http->setDefaultHeader('Accept', '*/*');
        $userId = $this->http->FindPreg("/var uuid = '(.+?)';/");
        // Upcoming reservations
        $noUpcoming = false;
        $this->http->GetURL("https://www.lhw.com/api/profile/reservations/getreservations/{$userId}/booked");
        $response = $this->http->JsonLog($this->http->FindSingleNode("//pre[not(@id)]"));
        $data = $response->data ?? $response->Data ?? [];

        if (!empty($data)) {
            $this->logger->debug("Total " . count($data) . " reservations found");

            foreach ($data as $item) {
                if ($res = $this->ParseItinerary($item)) {
                    $result[] = $res;
                }
            }
        } elseif ($this->http->FindPreg('/"data":\[\],"errors":null/ims')) {
            $noUpcoming = true;
        }

        // Cancelled reservations
        if (!$noUpcoming) {
            $this->logger->info('Parse Cancelled itineraries', ['Header' => 2]);

            try {
                $this->http->GetURL("https://www.lhw.com/api/profile/reservations/getreservations/{$userId}/cancelled");
            } catch (SessionNotCreatedException $e) {
                $this->logger->error("SessionNotCreatedException: " . $e->getMessage());
            }
            $response = $this->http->JsonLog($this->http->FindSingleNode("//pre[not(@id)]"));
            $data = $response->data ?? $response->Data ?? [];

            if (!empty($data)) {
                foreach ($data as $item) {
                    $result[] = [
                        'Kind'               => 'R',
                        'ConfirmationNumber' => $item->confirmationnumber ?? $item->Confirmationnumber,
                        'Cancelled'          => true,
                    ];
                }
            }
        }

        // Past reservations
        if ($this->ParsePastIts) {
            $this->logger->info('Parse past itineraries', ['Header' => 2]);

            try {
                $this->http->GetURL("https://www.lhw.com/api/profile/reservations/getreservations/{$userId}/consumed");
            } catch (SessionNotCreatedException $e) {
                $this->logger->error("SessionNotCreatedException: " . $e->getMessage());
            }
            $noPast = false;
            $response = $this->http->JsonLog($this->http->FindSingleNode("//pre[not(@id)]"));
            $data = $response->data ?? $response->Data ?? [];

            if (!empty($data)) {
                $data = array_slice($data, 0, 100);

                foreach ($data as $item) {
                    $this->increaseTimeLimit();

                    if ($res = $this->ParseItinerary($item)) {
                        $result[] = $res;
                    }
                }
            } elseif ($this->http->FindPreg('/"data":\[\],"errors":null/ims')) {
                $noPast = true;
            }

            if ($noPast && $noUpcoming) {
                return $this->noItinerariesArr();
            }
        } elseif ($noUpcoming) {
            return $this->noItinerariesArr();
        }

        return $result;
    }

    public function ParseItinerary($item)
    {
        $this->logger->notice(__METHOD__);

        if (
            !isset($item->confirmationnumber, $item->property, $item->address1)
            && !isset($item->Confirmationnumber, $item->Property, $item->Address1)
        ) {
            return null;
        }
        $this->itin++;
        $result = ['Kind' => 'R'];
        $result['ConfirmationNumber'] = $item->confirmationnumber ?? $item->Confirmationnumber;
        $this->logger->info("Parse Itinerary[{$this->itin}] #" . $result['ConfirmationNumber'], ['Header' => 3]);
        $result['HotelName'] = $item->property ?? $item->Property;
        $result['Address'] = Html::cleanXMLValue(str_replace(["N<sup>ro</sup>", " — ", "<br>"], ["No.", ", ", ", "], $item->address1 ?? $item->Address1));
        $phone = str_replace('–', '-', $item->phonenumber ?? $item->Phonenumber);
        $phone = preg_split('/\s*;\s*/', $phone);
        $result['Phone'] = join(',', $phone);
        $fax = str_replace('–', '-', $item->faxnumber ?? $item->Faxnumber);
        $result['Fax'] = $fax;
        $name = $item->guestfirstname ?? $item->Guestfirstname;
        $lastName = $item->guestlastname ?? $item->Guestlastname;

        if (!empty($name)) {
            $result['GuestNames'][] = beautifulName("{$name} {$lastName}");
        }
        $displayArrivalDate = $item->displayArrivalDate ?? $item->DisplayArrivalDate;
        $checkinafter = $item->checkinafter ?? $item->Checkinafter;
        $result['CheckInDate'] = strtotime("{$displayArrivalDate} {$checkinafter}");
        $displayDepartureDate = $item->displayDepartureDate ?? $item->DisplayDepartureDate;
        $checkoutbefore = $item->checkoutbefore ?? $item->Checkoutbefore;
        $result['CheckOutDate'] = strtotime("{$displayDepartureDate} {$checkoutbefore}");

        $result['Guests'] = $item->numadults ?? $item->Numadults;
        $result['Kids'] = $item->numchildren ?? $item->Numchildren;
        $result['Rooms'] = $item->numrooms ?? $item->Numrooms;
        $result['RoomType'] = strip_tags($item->roomdescription ?? $item->Roomdescription);
        $result['RateType'] = strip_tags($item->ratedescription ?? $item->Ratedescription);

        $result['Total'] = $item->displayTotalAmount ?? $item->DisplayTotalAmount;
        $result['Currency'] = $item->totalamountcurrency ?? $item->Totalamountcurrency;

        try {
            $this->http->GetURL("https://www.lhw.com/account/reservations/reservation-details?cn={$result['ConfirmationNumber']}");
        } catch (Exception $e) {
            $this->logger->error("[Exception]: {$e->getMessage()}");
        }

        $cancellationPolicy = (
            $this->http->FindPreg('/"cancellationPolicy":"(.+?)"/') ?:
            $this->http->FindSingleNode('(//p/strong[text()="Cancellation Policy:"]/following-sibling::text())[1]')
        );

        if ($cancellationPolicy) {
            $result['CancellationPolicy'] = $cancellationPolicy;
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(text(),'Logout')]") && !strstr($this->http->currentUrl(), 'https://www.lhw.com/login')) {
            return true;
        }

        return false;
    }
}
