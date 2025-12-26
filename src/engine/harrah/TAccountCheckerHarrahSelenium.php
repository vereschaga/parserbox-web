<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerHarrahSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private $selenium = false;

    private $token = null;
    private $lastName = null;

    /** @var HttpBrowser */
    private $curl = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $this->setProxyMount();
        $this->useChromePuppeteer();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL('https://www.caesars.com/myrewards/profile/#myrewards', [], 20);
        $this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "MY REWARDS")] | //input[@name="userID"]'), 10);
        $this->saveResponse();

        if ($this->http->FindSingleNode('//div[contains(text(), "SIGN OUT")]')) {
            $this->http->GetURL("https://www.caesars.com/a/security/keepalive.aspx?fullrefresh=true&format=json");

            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        try {
            $this->http->GetURL('https://www.caesars.com/mytotalrewards/#sign-in');
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 3);
        }

        $this->waitForElement(WebDriverBy::xpath('//input[@name = "userID"] | //iframe[contains(@src, "_Incapsula_Resource") and @id = "main-iframe"]/@src'), 15);
        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "userID"]'), 0);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "userPassword"]'), 0);
        $loginButton = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "SIGN IN")]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$loginButton) {
            $this->logger->error('Something went wrong');

            if ($this->http->FindPreg('/<main>\s*<div id="main"><\/div>\s*<\/main>/')) {
                throw new CheckRetryNeededException(2, 3);
            }

            if ($this->http->FindSingleNode('//iframe[contains(@src, "_Incapsula_Resource") and @id = "main-iframe"]/@src | //p[contains(., "is not available to customers or patients who are located outside of the United States or U.S. territories.")]')) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            if ($message = $this->http->FindSingleNode('//div[@id="system-error-modal" and contains(normalize-space(),"We are unable to continue with your request. Please refresh or try again later.")]')) {
                throw new CheckRetryNeededException(2, 1, $message, ACCOUNT_PROVIDER_ERROR);
            }
            // This site can’t be reached
            if ($this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")) {
                $this->DebugInfo = "This site can’t be reached";

                throw new CheckRetryNeededException(3, 10);
            }// if ($this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]"))

            return false;
        }
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        try {
            $loginButton->click();
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        return true;
    }

    public function Login()
    {
        try {
            $this->waitForElement(WebDriverBy::xpath('//div[contains(text(),"reward credits")] 
            | //a[contains(., "Sign Out")] 
            | //div[@id = "errorMsg"]/div[contains(@class, "index-module_notification-content")] 
            | //div[contains(@class, "index-module_form-error")] 
            | //h3[contains(text(), "MY CURRENT TIER")] 
            | //h4[contains(text(), "Activate Account")] 
            | //div[contains(text(), "We have updated the Rules and Regulations for our Caesars Rewards program.")] | //div[@data-testid="toast-message"]//span
            | //div[contains(text(),"Invalid username or password. Please try again.")]'), 25);
            $this->saveResponse();
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        }

        if ($this->http->FindSingleNode('//div[contains(text(),"reward credits")] 
        | //h3[contains(text(), "MY CURRENT TIER")] 
        | //div[contains(text(), "We have updated the Rules and Regulations for our Caesars Rewards program.")]')) {
            // refs #23845
            if ($btn = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Accept")]'), 0)) {
                $btn->click();

                $this->waitForElement(WebDriverBy::xpath('//a[contains(., "Sign Out")] | //h3[contains(text(), "MY CURRENT TIER")]'), 15);
                $this->saveResponse();
            }

            $this->http->GetURL("https://www.caesars.com/a/security/keepalive.aspx?fullrefresh=true&format=json");

            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[@id = "errorMsg"]/div[contains(@class, "index-module_notification-content")] 
        | //div[contains(@class, "index-module_form-error")] 
        | (//div[@data-testid="toast-message"]//span)[1]
        | //div[contains(text(),"Invalid username or password. Please try again.")]
        ')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Invalid username or password. Please try again.')
                || strstr($message, 'The password is invalid. Please try again.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'The account you are trying to access is currently locked.')
                || strstr($message, 'We\'re sorry, but your account is currently inactive')
                || strstr($message, 'It appears this account has been deactivated.')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (
                strstr($message, 'We apologize for the inconvenience, but our Caesars Rewards system is temporarily unavailable')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//h4[contains(text(), "Activate Account")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindPreg("/_Incapsula_Resource/")) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(2);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->curl = new HttpBrowser('none', new CurlDriver());
        $this->http->brotherBrowser($this->curl);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->curl->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $logininfo = $this->http->JsonLog($this->http->FindSingleNode("//pre[not(@id)]"));
        // Balance - Rewards Credit Balance
        $this->SetBalance($logininfo->rewardCreditBalance);
        // Name
        $this->SetProperty("Name", beautifulName($logininfo->firstName . " " . $logininfo->lastName));
        // for itineraries
        $this->lastName = $logininfo->lastName;
        $this->logger->debug("LastName: {$this->lastName}");

        // // Account Status as of
        // $this->SetProperty("AccountStatusAsOf", $this->http->FindPreg("/Account Status as of:\&nbsp;([^<]+)/ims"));
        // Tier Score
        $this->SetProperty("TierScore", $logininfo->tierscore ?? $logininfo->tierScore);
        // Current Tier
        if (isset($logininfo->tier)) {
            switch ($logininfo->tier) {
                case 'GLD':
                case 'GOLD':
                case 'TOTAL GOLD':
                    $this->SetProperty("CurrentTier", 'Gold');

                    break;

                case 'PLT':
                case 'PLATINUM':
                    $this->SetProperty("CurrentTier", 'Platinum');

                    break;

                case 'DIA':
                case 'DIAMOND':
                case 'DIAMOND PLUS':
                case 'DIAMOND ELITE':
                    $plus = '';

                    $tierCode =
                        $logininfo->extendedtier
                        ?? $logininfo->tierCode
                    ;

                    if ($tierCode == 'DIAP') {
                        $plus = ' Plus';
                    } elseif ($tierCode == 'DIAE') {
                        $plus = ' Elite';
                    }

                    $this->SetProperty("CurrentTier", 'Diamond' . $plus);

                    break;

                case 'SEV':
                case 'SEVEN STARS':
                    $this->SetProperty("CurrentTier", 'Seven Stars');

                    break;

                default:
                    $this->sendNotification("Unknown status: {$logininfo->tier}");
            }
        }

        // hard code (AccountID: 2794826)
        if (
            isset($response->logininfo->accountbalance)
            && isset($response->logininfo->tierscore)
            && $response->logininfo->accountbalance == $response->logininfo->tierscore
            && $response->logininfo->tierscore == -1
        ) {
            $this->SetProperty("TierScore", 0);
            $this->SetBalance(0);
        }

        // Account Number
        $this->SetProperty("AccountNumber", $logininfo->accountId);

        $this->curl->GetURL("https://www.caesars.com/a/security/keepalive.aspx?fullrefresh=false");
        $this->token = $this->curl->getCookieByName("sectoken") ?? null;
        $this->logger->debug("Token: {$this->token}");

        // Rewards Credit Exp. Date
        if ($this->Balance > 0 && !empty($this->token)) {
            $this->curl->GetURL("https://www.caesars.com/asp_net/proxy.aspx?url=lb%3A//prodmercury/mercury/GetGuestProfile%3Fresponseformat%3Djson%26primaryaccttoken%3D{$this->token}&t=" . time() . date("B"));
            $response = $this->curl->JsonLog();

            if (isset($response->logininfo->tier->expirationdate)) {
                // Status expiration date
                $this->SetProperty('StatusExpiration', strtotime($response->logininfo->tier->expirationdate));
            }

            if (
                isset($response->logininfo->rewardcredits->expirationdate)
                && strtotime($response->logininfo->rewardcredits->expirationdate) > time()
            ) {
                $this->SetExpirationDate(strtotime($response->logininfo->rewardcredits->expirationdate));
            }
        }// if ($this->Balance > 0 && isset($response->logininfo->token))

        $this->curl->GetURL('https://www.caesars.com/api/v1/trtiers');
        $tiers = $this->curl->JsonLog();

        foreach ($tiers as $tier) {
            if (strtolower($tier->name) == strtolower($this->Properties['CurrentTier'])) {
                // Credits to maintain current tier
                $this->SetProperty('CreditsToMaintainCurrentTier', $tier->mincredits - $this->Properties['TierScore']);
            }
        }
    }

    public function ParseItineraries(): array
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        // WARNING! This working on prod only
        if (!isset($this->token)) {
            $this->logger->error("token not found");

            return [];
        }

        $this->curl->GetURL("https://www.caesars.com/asp_net/proxy.aspx?url=lb%3A//prodmercury/mercury/GetTRReservations%3Fprimaryaccttoken%3D{$this->token}%26responseformat%3Djson");
        $response = $this->curl->JsonLog();

        if (isset($response->lastname)) {
            $this->lastName = $response->lastname;
        }

        if ($this->curl->FindPreg('/"reservations":\[\]/ims')) {
            $this->logger->debug('no it');
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        if (
            !isset($response->reservations)
            && strstr($this->http->Response['body'], '[{"@messageType":"FAILURE","@message":"Error returned from IMS service","#text":"IMS00012 -- RES Communication Error."}]')
        ) {
            return [];
        }

        $totalReservationsCount = count($response->reservations);
        $pastReservationsCount = 0;
        $this->logger->debug("Total {$totalReservationsCount} reservation were found");
        $parsePast = false;

        if ($this->ParsePastIts) {
            $parsePast = true;
        }

        foreach ($response->reservations as $reservation) {
            if ($reservation->state != 'FUTURE' && $parsePast === false) {
                $this->logger->error("Skipping itinerary #{$reservation->confirmationCode} with state {$reservation->state}");

                if ($reservation->state == 'PAST') {
                    $pastReservationsCount++;
                }

                continue;
            }

            $this->logger->info("Parse Itinerary #{$reservation->confirmationCode}", ['Header' => 3]);
            $res = [
                "Kind"               => "R",
                "ConfirmationNumber" => $reservation->confirmationCode,
                "CheckInDate"        => strtotime(str_replace('-', '/', $reservation->checkInDate)),
                "CheckOutDate"       => strtotime(str_replace('-', '/', $reservation->checkOutDate)),
                "RoomType"           => $reservation->roomTypeTitle ?? null,
                "HotelName"          => $reservation->propertyName ?? null,
                "Guests"             => $reservation->adults,
                "Kids"               => $reservation->children,
            ];
            // CANCELLED RESERVATION
            if ($reservation->status == 'Cancelled') {
                $this->logger->notice("skip cancelled itinerary #{$reservation->confirmationCode} with state {$reservation->state}");
                $res['Cancelled'] = true;
                $result[] = $res;

                continue;
            }

            $propCode = $reservation->propertyCode;
            $this->setAddressJson($this->curl, $reservation, $propCode, $res);
            $this->setRoomJson($this->curl, $reservation, $propCode, $res);

            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($res, true), ['pre' => true]);

            $result[] = $res;
        }// foreach ($response->reservations as $reservation)

        if ($pastReservationsCount == $totalReservationsCount) {
            $this->logger->notice('All user itineraries are in past, assuming noItineraries case');

            return $this->noItinerariesArr();
        }// if (!$this->ParsePastIts && $pastReservationsCount == $totalReservationsCount)

        $this->checkItineraries($result, true);

        return $result;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // page not found
        if ($error = $this->http->FindPreg("/The web page you are looking for could not be found/ims")) {
            throw new CheckException("Site is temporarily down. Please try to access it later.", ACCOUNT_PROVIDER_ERROR);
        }
        // 500 - Internal server error.
        if ($this->http->FindSingleNode("//h2[contains(text(), '500 - Internal server error.')]")
            || $this->http->FindSingleNode("//p[contains(text(), 'HTTP Error 503. The service is unavailable.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg('/"@message":"(We have encountered some technical issues, please try again later.)"/')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function setAddressJson($browser, $reservation, $propCode, &$res): void
    {
        $this->logger->notice(__METHOD__);
        $arrivalDate = urlencode(str_replace('-', '/', $reservation->checkInDate));
        $browser->GetURL("https://www.caesars.com/book/?view=findreservation&confCode={$res['ConfirmationNumber']}&lastName={$this->lastName}&arrivalDate={$arrivalDate}&propcode={$propCode}", [], 120);

        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $browser->GetURL("https://www.caesars.com/api/v1/properties/{$propCode}", $headers);

        $res["Phone"] = $browser->FindPreg('/"phone":"([^\"]+)/ims');

        $address = $browser->FindPreg('/"street":"([^\"]+)/ims');
        $city = $browser->FindPreg('/"city":"([^"]+)/ims');
        $state = $browser->FindPreg('/"state":"([^\"]+)/ims');
        $country = $browser->FindPreg('/country":"([^\"]+)/ims');
        $zip = $browser->FindPreg('/"zip":"([^\"]+)/ims');
        $res["Address"] = implode(', ', array_filter([$address, $city, $state, $zip, $country])) ?: null;
        $res['DetailedAddress'] = [
            [
                "AddressLine" => $address,
                "CityName"    => $city,
                "PostalCode"  => $zip,
                "StateProv"   => $state,
                "Country"     => $country,
            ],
        ];

        if (empty($reservation->propertyName)) {
            $res['HotelName'] = $browser->FindPreg('/,"propertyName":"(.+?)",/');
        }
    }

    private function setRoomJson($browser, $reservation, $propCode, &$res): void
    {
        $this->logger->notice(__METHOD__);
        $arrivalDate = urlencode(str_replace('-', '/', $reservation->checkInDate));
        $browser->GetURL("https://www.caesars.com/book/?view=findreservation&confCode={$res['ConfirmationNumber']}&lastName={$this->lastName}&arrivalDate={$arrivalDate}&propcode={$propCode}", [], 120);

        $headers = [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Content-type'     => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        // 02-18-2022 t o 2022-02-18
        $d = explode('-', $reservation->checkInDate);

        if (count($d) == 3) {
            $reservation->checkInDate = "$d[2]-$d[0]-$d[1]";
        }
        $payload = [
            'request' => [
                '__type'        => 'HotelReservationRequest:TotalRewards', // strangely has to be the first
                'ArrivalDate'   => $reservation->checkInDate,
                'ConfCode'      => $reservation->confirmationCode,
                'LastName'      => $this->lastName,
                'PropCode'      => $propCode,
            ],
        ];
        $browser->PostURL('https://www.caesars.com/asp_net/bookingproxy.aspx?url=lb%3A%2F%2Fprodgalaxy%2FGalaxy.Services.Ordering.WCFApp%2FOrderingService.svc%2Frest%2Fv2%2FFindReservation', json_encode($payload), $headers);

        if ($browser->FindPreg('/No response from response data queue./')) {
            sleep(1);
            $browser->PostURL('https://www.caesars.com/asp_net/bookingproxy.aspx?url=lb%3A%2F%2Fprodgalaxy%2FGalaxy.Services.Ordering.WCFApp%2FOrderingService.svc%2Frest%2Fv2%2FFindReservation', json_encode($payload), $headers);
        }

        if ($msg = $browser->FindPreg('/"ErrorMessage":"(A reservation for this guest could not be found\.)"/')) {
            $this->logger->error("No room info: {$msg}");

            return;
        }

        $firstName = $browser->FindPreg('/"FirstName":"(.+?)"/u');
        $lastName = $browser->FindPreg('/"LastName":"(.+?)"/u');
        $guestName = trim(beautifulName("{$firstName} {$lastName}"));

        if ($guestName) {
            $res['GuestNames'] = [$guestName];
        }
        $rateSetCode = $browser->FindPreg('/"RateSetCode":"(\w*)"/');

        if ($rateSetCode === "") {
            $this->logger->error('RateSetCode is present but empty');

            return;
        }

        if (!$rateSetCode) {
            if (!$browser->FindPreg('/\{"ErrorMessage":"(?:No Reservation found for given input|LMS ErrorMessage-> No response from response data queue. EngineName - CRS-A-CRS-A-7")/')) {
                //$this->sendNotification('check rate // MI');
            }

            return;
        }

        $this->logger->debug("RateSetCode: $rateSetCode");
        $browser->GetURL("https://www.caesars.com/api/v1/properties/{$propCode}/hotel/rooms", $headers);
        $roomsData = $browser->JsonLog(null, 0, true);

        if (!$roomsData) {
            $this->sendNotification('check rooms // MI');

            return;
        }

        foreach ($roomsData as $room) {
            $rateSet = $room['rateSet'] ?? null;

            if ($rateSet == $rateSetCode) {
                if (empty($res['HotelName'])) {
                    $this->logger->debug('New HotelName: ' . ($room['propertyName'] ?? null));
                    $res['HotelName'] = $room['propertyName'] ?? null;
                }
                $name = $room['name'] ?? null;

                if (!$name) {
                    $this->sendNotification('check rooms // MI');

                    continue;
                }
                $desc = preg_split('/\s*\|\s*/', $name);
                $res['RoomType'] = array_shift($desc);

                if (count($desc)) {
                    $res['RoomTypeDescription'] = implode(', ', $desc);
                }
            }
        }
    }
}
