<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\MainBundle\Service\Itinerary\Event;

class TAccountCheckerPrincess extends TAccountChecker
{
    use PriceTools;

    /* @var CruiseSegmentsConverter */
    private $converter;
    private $currentItin = 0;
    private $flights = [];
    private $events = [];

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://book.princess.com/captaincircle/creditBank.page';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://book.princess.com/captaincircle/myPrincess.page");

        if (!$this->http->ParseForm("signin")) {
            return $this->checkErrors();
        }
        $this->http->setCookie("COOKIE_CHECK", "YES", ".princess.com", "/");
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('keep-signed-in', "Y");

        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br",
            "appid"           => '{"env":"prod","migration":"N","sessionId":"4268f173-ed2a-46c3-a435-b84a40bc1ad2"}',
            "BookingCompany"  => "PC",
            "Content-Type"    => "application/json",
            "authorization"   => "Basic " . base64_encode($this->AccountFields['Login'] . ":" . urlencode($this->http->Form["password"])),
            "pcl-client-id"   => "32e7224ac6cc41302f673c5f5d27b4ba", // pclClientId from https://www.princess.com/js/global/princess-libs.combined.js
            "ProductCompany"  => "PC",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://gw.api.princess.com/pcl-web/internal/guest/p1.0/login", null, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // we're having technical difficulties
        if ($message = $this->http->FindSingleNode("//h1/strong[contains(text(), 're having technical difficulties')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We’re Making Improvements
        if (strstr($this->http->currentUrl(), 'maintenance')
            && ($message = $this->http->FindPreg('/(We\&rsquo;re Making Improvements)/'))) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We’re Making Improvements
        if (strstr($this->http->currentUrl(), 'maintenance')
            && ($message = $this->http->FindSingleNode('//strong[contains(text(), "we\'re making improvements")]'))) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We’re Having Technical Difficulties
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "We’re Having Technical Difficulties")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# We are currently upgrading our systems
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently upgrading our systems')]/parent::td")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# The server is temporarily unable to service your request
        if ($message = $this->http->FindPreg("/(The server is temporarily unable to service your request\.\s*Please try again later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Due to unusually high volumes, we will not be able to process your request immediately.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Due to unusually high volumes, we will not be able to process your request immediately.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# An error occurred while processing your request
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->FindPreg("/Could not resolve host: book\.princess\.com/ims") && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            throw new CheckRetryNeededException(3, 7);
        }

        return false;
    }

    public function Login()
    {
        /*
        $headers = [
            "X-Requested-With" => "XMLHttpRequest",
            "Content-Type" => "application/x-www-form-urlencoded; charset=UTF-8",
            "ADRUM"	=> "isAjax:true",
            "Accept" => "application/json, text/javascript, *
        /*; q=0.01"
        ];
        if (!$this->http->PostForm($headers))
            return $this->checkErrors();
        */
        $response = $this->http->JsonLog();
        // Access is allowed
        if (!empty($response->token)) {
            $accountStatus = null;

            foreach (explode('.', $response->token) as $str) {
                $str = base64_decode($str);
                $this->logger->debug($str);

                if ($accountStatus = $this->http->FindPreg('/"accountStatus":"(.+?)"/', false, $str)) {
                    break;
                }
            }

            // Your Account is Missing Some Information
            if ($accountStatus == 'Ocean') {
                $this->throwProfileUpdateMessageException();
            }

            $this->http->setCookie("pcl-ccntkn", $response->token, ".princess.com");
            $this->http->GetURL('https://book.princess.com/captaincircle/quickLogin.do');

            return true;
        }

        $message =
            $response->message
            ?? $response->error
            ?? null
        ;

        if ($message === 'Unauthorized') {
            throw new CheckException("Your login attempt was unsuccessful. Please check that you are using the correct Login ID (or Email) and Password combination associated with your account.", ACCOUNT_INVALID_PASSWORD);
        }

        if (
            $this->http->FindPreg("/error\":\"Internal Server Error\",\"message\":\"Dangling meta character '\*' near index 0/")
            || $this->http->FindPreg("/status\":500,\"error\":\"Internal Server Error\",\"message\":\"Unmatched closing '\)'/")
            || $this->http->FindPreg("/status\":500,\"error\":\"Internal Server Error\",\"message\":\"Unclosed counted closure near index /")
            || $this->http->FindPreg("/status\":500,\"error\":\"Internal Server Error\",\"message\":\"I\/O error on GET request for/")
        ) {
            throw new CheckException("We are currently experiencing technical difficulties. Please try again.", ACCOUNT_PROVIDER_ERROR);
        }

        if (strstr($message, 'URLDecoder: Illegal hex characters in escape (%) pattern ')) {
            throw new CheckException("If your current password contains this invalid character ( % ) please reset your password using only supported characters.", ACCOUNT_INVALID_PASSWORD);
        }
        /*
        // Your email address or password cannot be found, please try again.
        if ($message = $this->http->FindPreg('/(Your email address or password cannot be found[^.]*)/ims'))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // We are currently experiencing technical difficulties
        if ($message = $this->http->FindPreg('/"(We are currently experiencing technical difficulties\..*?)"/ims'))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        */

        // hard code
        $browser = clone $this->http;
        $browser->GetURL('https://book.princess.com/captaincircle/creditBank.page');

        if ($browser->FindSingleNode("//span[contains(text(), 'Member #')]", null, true, "/\s*([^<]+)/")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://book.princess.com/captaincircle/creditBank.page');

        // Your Account is Missing Some Information
        $emptyResponse = $this->http->Response['body'] === '{"data":null,"message":null,"status":null}';

        // refs #13071
        $credits = ['Cruise', 'Onboard'];

        foreach ($credits as $credit) {
            unset($exp, $expAmount, $displayName);
            // DisplayName
            $displayName = "{$credit} Credits";
            // BALANCE TOTALS -> AMOUNT
            $creditBalance = $this->http->FindSingleNode("//div[h2[contains(text(), '{$credit} Credits')]]/following-sibling::div[1]/span", null, true, self::BALANCE_REGEXP);
            $this->logger->notice("[{$displayName}]: $creditBalance");
            // history
            $transactions = $this->http->XPath->query("//div[div[h2[contains(text(), '{$credit} Credits')]]/following-sibling::div[1]/span]/following-sibling::div[1]//table[//th[contains(text(), 'Expires')]]//tr[td]");
            $this->logger->debug("Total {$transactions->length} history nodes were found");

            foreach ($transactions as $transaction) {
                $amount = $this->http->FindSingleNode("td[6]", $transaction, true, self::BALANCE_REGEXP_EXTENDED);
                $date = $this->http->FindSingleNode("td[4]", $transaction, true, "/(?:Sail|Book)\s*By\s*([^<]+)/");
                $this->logger->debug("date: $date, amount: $amount");

                if ((!isset($exp) || strtotime($date) <= $exp) && $amount) {
                    $amount = floatval($amount);

                    if (isset($exp) && strtotime($date) == $exp) {
                        if (!isset($expAmount)) {
                            $expAmount = $amount;
                        } else {
                            $expAmount += $amount;
                        }
                    }// if (isset($exp) && strtotime($date) <= $exp)
                    else {// if (!isset($exp) || strtotime($date) <= $exp)
                        $exp = strtotime($date);
                        $expAmount = $amount;
                    }
                    $this->logger->debug("[{$displayName}]: Exp: $exp -> ExpAmount: $expAmount");
                }// if ((!isset($exp) || strtotime($date) <= $exp) && $amount)
            }// foreach ($transactions as $transaction)

            if (isset($exp, $expAmount)) {
                $this->SetProperty("CombineSubAccounts", false);
                $this->AddSubAccount([
                    'Code'            => 'princess' . str_replace(' ', '', $displayName),
                    'DisplayName'     => $displayName,
                    'Balance'         => $creditBalance,
                    'ExpirationDate'  => $exp,
                    'ExpiringBalance' => "$" . $expAmount,
                ], true);
            }// if (isset($exp, $expAmount))
            $this->logger->debug('-------------------------------------------------------');
        }// foreach ($credits as $credit)

        if (!$this->http->FindPreg('/You do not currently have/ims')) {
            // Name
            $this->SetProperty("Name", $this->http->FindSingleNode("//span[contains(@class, 'guest-name')]"));

            $noBalance = false;

            if (!$this->http->GetURL('https://book.princess.com/captaincircle/cruiseHistory.page')) {
                $noBalance = true;
            }
            // Balance - Total Princess Cruise Days
            $balance = $this->http->FindSingleNode("//span[contains(text(), 'Total Cruise Days')]", null, true, "/(\d+)\s*Total/ims");

            if (isset($balance)) {
                $this->SetBalance($balance);
            }
            // for Elite levels
            $this->SetProperty("CruiseDays", $balance);
            // Total Cruises Sailed
            $this->SetProperty("TotalPrincessCredits", $this->http->FindSingleNode("//span[contains(text(), 'Total Cruises Sailed')]", null, true, "/(\d+)\s*Total/ims"));

            $this->http->GetURL('https://book.princess.com/captaincircle/myPrincess.page');
            // Name
            $this->SetProperty("Name", $this->http->FindSingleNode("//span[contains(@class, 'guest-name')]"));
            // Level
            $this->SetProperty("Status", $this->http->FindSingleNode("//span[contains(@class, 'level')]/text()[last()]", null, true, "/is\s+(.+)/"));
            // Member #
            $this->SetProperty("Number", $this->http->FindSingleNode("//span[contains(text(), 'Member #')]", null, true, "/Member \#\s*([^<]+)/"));
            // Cruise Credits
            $this->SetProperty("CruiseCredits", $this->http->FindSingleNode('//a[contains(text(), "Cruise Credits")]/../strong'));
            // Onboard Credits
            $this->SetProperty("OnboardCredits", $this->http->FindSingleNode('//a[contains(text(), "Onboard Credits")]/../strong'));

            // fix for AccountID: 944777
            if (!isset($this->Properties['Status']) && !isset($this->Properties['Number'])
                && (!isset($this->Properties['CruiseCredits']) || $noBalance)
                /* AccountID: 944777 && !isset($this->Properties['OnboardCredits'])*/
                && isset($this->Properties['Name']) && !isset($balance)) {
                $this->SetBalanceNA();
            }
        }// if (!$this->http->FindPreg('/You do not currently have/ims'))
        else {
            $this->http->GetURL('https://book.princess.com/captaincircle/myPrincess.page');
            // Name
            $this->SetProperty("Name", $this->http->FindPreg("/name\s*:\s*<\/b>([^<]*)/ims"));
            $this->SetBalanceNA();
            $this->http->GetURL('https://book.princess.com/captaincircle/referralRewards.page');
            // Member #
            $this->SetProperty("Number", $this->http->FindSingleNode("//input[@name = 'referrerCCN']/@value"));
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // maintenance
            if ($this->http->FindSingleNode('//h1[contains(text(), "We’re Making Improvements")]')
                && ($message = $this->http->FindSingleNode('//p[contains(text(), "We apologize for any inconvenience and will be back ")]'))) {
                throw new CheckException("We’re Making Improvements. {$message}", ACCOUNT_PROVIDER_ERROR);
            }

            // AccountID: 2627070, 2100065, 4924592
            if ($emptyResponse) {
                throw new CheckException("Your registration is complete.", ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function ParseItineraries()
    {
        $result = [];
        $links = [];
        $this->converter = new CruiseSegmentsConverter();

        $this->http->GetURL('https://book.princess.com/captaincircle/myPrincess.page');
        $nodes = $this->http->XPath->query("//a[contains(text(), 'Manage This Booking')]");
        $this->logger->debug("Total {$nodes->length} were reservations");

        foreach ($nodes as $node) {
            $href = $this->http->FindSingleNode('@href', $node);
            $name = $this->http->FindSingleNode('parent::div/preceding-sibling::h3', $node);
            $links[] = ['name' => $name, 'href' => $href];
        }// foreach ($nodes as $node)

        foreach ($links as $segment) {
            sleep(rand(1, 3));
            $this->increaseTimeLimit();
            $name = $segment['name'];
            $link = $segment['href'];
            $this->logger->debug("[Link]:" . $link);
            $link = "https://book.princess.com{$link}";
            $this->http->GetURL($link, [], 20);

            if (strstr($this->http->Error, 'Network error 28 - Operation timed out after')
                || $this->http->Response['code'] == 403) {
                sleep(7);
                $this->http->GetURL($link, [], 20);
            }

            if (!isset($fistLink)) {
                $fistLink = $link;
            }

            if ($this->http->FindSingleNode('//p[contains(text(), "As your ship is scheduled to leave port within 1 day")]')) {
                $this->logger->notice("Cruise not available in html");

                continue;
            }

            if ($this->http->FindSingleNode('//span[
                    contains(text(), "5390: You do not have access to Booking ID") and contains(text(), "Please verify the passenger name and retry.")
                    or contains(text(), "The Date of Birth you entered does not match your information on file.")
                ]')
            ) {
                $this->logger->notice("Cruise not available in html");

                continue;
            }

            if ($this->http->FindSingleNode('//h1[contains(.,"Terms & Conditions")]')) {
                $this->logger->notice("Terms & Conditions: By accessing the information and materials on this web site, you agree as follows...");

                continue;
            }

            if ($this->http->FindSingleNode('//span[contains(text(), "This booking has been cancelled")]')) {
                $it = [
                    'Kind'          => 'T',
                    'TripCategory'  => TRIP_CATEGORY_CRUISE,
                    'RecordLocator' => $this->http->FindPreg('/bookingId=(\w+)/', false, $this->http->currentUrl()),
                    'Cancelled'     => true,
                ];
            } else {
                $it = $this->ParseItinerary($name);

                if ($it === null) {
                    continue;
                }
            }
            $this->logger->debug('Parsed Itinerary:');
            $this->logger->debug(var_export($it, true), ['pre' => true]);
            $result[] = $it;

            if (!empty($this->flights)) {
                $this->logger->info(sprintf('[%s] Parse Flight', $this->currentItin - 1),
                    ['Header' => 3]);
                $this->logger->debug('Parsed Itinerary Flights:');
                $this->logger->debug(var_export($this->flights, true), ['pre' => true]);

                foreach ($this->flights as $it) {
                    $result[] = $it;
                }
                // reset flights;
                $this->flights = [];
            }

            if (!empty($this->events)) {
                $this->logger->info(sprintf('[%s] Parse Event', $this->currentItin - 1),
                    ['Header' => 3]);
                $this->logger->debug('Parsed Itinerary Events:');
                $this->logger->debug(var_export($this->events, true), ['pre' => true]);

                foreach ($this->events as $it) {
                    $result[] = $it;
                }
                // reset events;
                $this->events = [];
            }
        }// foreach ($links as $name => $link)

        return $result;
        // Excursions
        $this->logger->info('Excursions', ['Header' => 3]);
        $this->http->GetURL('https://www.princess.com/cruise-excursions/');
        $nodes = $this->http->XPath->query("//div[@id = 'current-reservations']//div[contains(@class, 'tour-details-box')]");
        $this->logger->debug("Total {$nodes->length} excursions were found");
        // provider bug fix
        if ($nodes->length == 0 && isset($fistLink)) {
            $this->http->GetURL($fistLink);
            $this->http->GetURL('https://book.princess.com/cruisepersonalizer/excursions.page');
            $nodes = $this->http->XPath->query("//div[@id = 'current-reservations']//div[contains(@class, 'tour-details-box')]");
            $this->logger->debug("Total {$nodes->length} excursions were found");
        }// if ($nodes->length == 0)

        if ($nodes->length > 0) {
            $this->sendNotification('Excursions > 0 // MI');
        }

        foreach ($nodes as $node) {
            // 5390: You do not have access to Booking ID ... . Please verify the passenger name and retry.
            if ($this->http->FindSingleNode('//span[
                    contains(text(), "5390: You do not have access to Booking ID") and contains(text(), "Please verify the passenger name and retry.")
                    or contains(text(), "The Date of Birth you entered does not match your information on file.")
                ]')
            ) {
                $this->logger->notice("Cruise not available in html");

                continue;
            }

            $it = $this->parseEvent($node);
            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($it, true), ['pre' => true]);
            $result[] = $it;
        }// foreach ($nodes as $node)

        if (empty($result)) {
            $this->http->GetURL("https://book.princess.com/cruisepersonalizer/index.page");

            if ($msg = $this->http->FindSingleNode("//p[contains(.,'There are no cruises associated to your princess.com account.')]")) {
                $this->sendNotification("no it // MI");

                return $this->noItinerariesArr();
            }
            // debug retry
            if ($this->http->FindSingleNode("//h1[contains(.,'There was an error while processing your request.')]")) {
                sleep(5);
                $this->http->GetURL("https://book.princess.com/cruisepersonalizer/index.page");

                if ($this->http->FindSingleNode("//h1[contains(.,'There was an error while processing your request.')]")) {
                    $this->sendNotification("retry not work // MI");
                } else {
                    $this->sendNotification("retry helped // MI");

                    if ($msg = $this->http->FindSingleNode("//p[contains(., 'There are no cruises associated to your princess.com account.')]")) {
                        return $this->noItinerariesArr();
                    }
                    $this->sendNotification("check itineraries // MI");
                }
            } elseif (!$this->http->FindSingleNode('//h1[contains(.,"Terms & Conditions")]')) {
                // NB: not all links are to real bookings. but if they are no - it means no itineraries
                $this->sendNotification("check itineraries 2// MI");
            }
        }

        return $result;
    }

    public function ParseItinerary($name)
    {
        $result = [];

        $result['Kind'] = 'T';
        $result['TripCategory'] = TRIP_CATEGORY_CRUISE;

        $result["RecordLocator"] = (
            $this->http->FindSingleNode("//li[contains(text(), 'Booking Number')]/strong") ?:
            $this->http->FindSingleNode("//div[@id = 'container']/@data-dtm-booking-number")
        );
        $this->logger->info(sprintf('[%s] Parse Cruise #%s', $this->currentItin++, $result["RecordLocator"]), ['Header' => 3]);
        $result['CruiseName'] = $name;
        $result['VoyageNumber'] = $this->http->FindSingleNode("//div[@id = 'container']/@data-dtm-voyage-id");
        $result["Deck"] = $this->http->FindSingleNode("//a[contains(@href, 'deckPlans')]");
        $result["ShipName"] = $this->http->FindSingleNode('//a[contains(@href, "ships") and not(contains(text(), "Download the"))]');
        $result["ShipCode"] = $this->http->FindSingleNode("//a[contains(@href, 'deckPlans')]/@href", null, true, "/shipCode=([^<\=]+)/");
        $result["RoomClass"] = $this->http->FindSingleNode("//li[contains(text(), 'Category')]", null, true, "/Category\s*([^\,]+)/");
        $result["RoomNumber"] = $this->http->FindSingleNode("//li[contains(text(), 'Stateroom')]", null, true, "/Stateroom\s*([^\,]+)/");
        $result["Status"] = $this->http->FindSingleNode("//li[contains(text(), 'Booking Number')]", null, true, "/\-\s*(.+)$/");
        $points = $this->http->FindNodes("//h3[normalize-space()='Booking Details']/following-sibling::div[1]//li[count(./a)=2]/a");

        if ($result["RoomNumber"] == " ") {
            $this->logger->debug("room not found");
            unset($result["RoomNumber"]);
        }

        $this->http->GetURL("https://book.princess.com/cruisepersonalizer/paxCheckIn.page");
        $passengers = $this->http->FindNodes("//div[contains(@class, 'booking-pax-list')]/div/table/caption");

        if (count($passengers) > 0) {
            $result['Passengers'] = implode(', ', $passengers);
        }

        return $this->parseSegmentsJson($result, $points);
        // debug. if it will be problems - del row above

        $this->http->FilterHTML = false;
        $this->http->GetURL("https://book.princess.com/cruisepersonalizer/itinerary.page");
        $segments = [];
        $rows = $this->http->XPath->query("//tr[@class='shorex']");
        $this->logger->debug("Total " . $rows->length . " segments were found");

        if ($rows->length === 0 && $this->http->FindSingleNode('//ul[@class="errorMessage"][starts-with(normalize-space(),"There was an error")]')) {
            // new format seems
            return $this->parseSegmentsJson($result, $points);
        }

        $segment = [];

        foreach ($rows as $row) {
            $date = $this->http->FindSingleNode("td[1]", $row);
            $arrDate = $this->http->FindSingleNode("td[3]/span[1]", $row);
            $depDate = $this->http->FindSingleNode("td[3]/span[3]", $row);
            $segment["Port"] = $this->http->FindSingleNode("td[2]/a[not(contains(., 'port information'))]", $row);

            if (!$segment["Port"]) {
                $segment["Port"] = $this->http->FindSingleNode("td[2]/text()[1]", $row);
            }

            if (!empty($arrDate)) {
                $segment["ArrDate"] = strtotime($date . " " . $arrDate);
            }

            if (!empty($depDate)) {
                $segment["DepDate"] = strtotime($date . " " . $depDate);
                $segments[] = $segment;
                $segment = [];
            }// if (!empty($depDate))
        }// foreach ($rows as $row)
        // last segment, final arrival
        if (isset($segment["ArrDate"])) {
            $segments[] = $segment;
        }

        if (count($segments) > 0) {
            $result["TripSegments"] = $this->converter->Convert($segments);
        } else {
            $result["TripSegments"] = [];
        }

        return $result;
    }

    private function parseSegmentsJson(array $result, array $points)
    {
        $this->logger->notice(__METHOD__);
        $this->increaseTimeLimit();
        $this->http->GetURL("https://book.princess.com/cruisepersonalizer/json/keepAlive.page");
        $res = $this->http->JsonLog(null, 3, false, 'appToken');

        if (!isset($res->data, $res->data->tokenObj, $res->data->tokenObj->appToken)) {
            return $result;
        }
        $token = $res->data->tokenObj->appToken;

        $this->http->setDefaultHeader('pcl-client-id', '32e7224ac6cc41302f673c5f5d27b4ba');
        $headers = [
            'Accept'         => 'application/json, text/javascript, */*; q=0.01',
            'ProductCompany' => 'PC',
            'BookingCompany' => 'PC',
            'AppId'          => '{"agencyId":"DIRPB","cruiseLineCode":"PCL","sessionId":"Unique Token","systemId":"PB"}',
            'AuthToken'      => $token,
            'Referer'        => 'https://www.princess.com/cruise-excursions/',
        ];
        $this->http->GetURL("https://gw.api.princess.com/pcl-web/internal/cpmf/v1.0/booking/display/menu", $headers);
        $res = $this->http->JsonLog(null, 3, true, 'agencyId');

        if (!isset($res['nav-menu']) || !isset($res['nav-menu']['agencyId'])) {
            return $result;
        }
        $agencyId = $res['nav-menu']['agencyId'];
        $bookingCompany = $res['nav-menu']['bookingCompany'];
        $headers = [
            'Accept'         => 'application/json, text/javascript, */*; q=0.01',
            'BookingCompany' => $bookingCompany,
            'ProductCompany' => 'PC',
            'AppId'          => '{"agencyId":"' . $agencyId . '","cruiseLineCode":"PCL","sessionId":"Unique Token","systemId":"PB","bookNum":"' . $result["RecordLocator"] . '"}',
            'Referer'        => 'https://www.princess.com/cruise-excursions/',
            'AuthToken'      => $token,
            'reqsrc'         => 'W',
        ];
        $this->http->GetURL("https://gw.api.princess.com/pcl-web/internal/cpmf/v1.0/booking/summary", $headers);
        $res = $this->http->JsonLog(null, 3, false, 'events');

        if (!isset($res->itinerary) || !isset($res->itinerary->events) || !is_array($res->itinerary->events)) {
            // 1. There is a message on the website "You do not have access to Booking ID XPCN6W. Please verify the passenger name and retry." can't login
            // 2. You must accept the Terms & Conditions
            if ($err = $this->http->FindPreg('/"title":"(An unexpected response condition code has been received\.|Invalid Booking Number)",/')) {
                $this->logger->error($err);
            }

            return null;
        }

        if (!isset($result['Passengers']) && isset($res->booking, $res->booking->paxList, $res->booking->paxList->paxes) && is_array($res->booking->paxList->paxes)) {
            foreach ($res->booking->paxList->paxes as $pax) {
                $result['Passengers'][] = beautifulName($pax->firstName . ' ' . $pax->middleName . ' ' . $pax->lastName);
            }
            $result['Currency'] = $res->booking->currencyCode ?? null;
            $result['TotalCharge'] = $res->booking->pricing->totalFare ?? null;
        }
        $segments = [];
        $segment = [];
        $flights = [];
        $events = [];

        foreach ($res->itinerary->events as $row) {
            if (!isset($row->typeCode)) {
                $this->logger->error('something wrong with segment');
                $segments = [];

                break;
            }

            if (in_array($row->typeCode, ['PKG', 'SEA', 'SPA'])) {
                continue;
            }

            if ($row->typeCode === 'FLT') {
                $flights[] = $row;

                continue;
            }

            if (in_array($row->typeCode, ['DIN', 'TUR'])) {
                // DIN - dinner on board - no address - skip
                // TUR - tour (fe: airport to ship) - no locations|times - skip
                continue;
            }

            if ($row->typeCode === 'SHX') {
                $events[] = $row;

                continue;
            }

            if (!in_array($row->typeCode, ['EMB', 'PV', 'DEB'])) {
                $this->logger->error('new type segment ' . $row->typeCode . ' // ZM');
                $segments = [];

                break;
            }
            $date = strtotime(preg_replace("/^(\d{2})(\d{2})(\d{4})$/", "$1/$2/$3", $row->startDate));

            if (empty($segments)) {
                if (isset($row->startTime) && !empty($row->startTime)) {
                    $depDate = preg_replace("/^(\d{2})(\d{2})$/", "$1:$2", $row->startTime);
                } else {
                    $depDate = isset($row->endTime) ? preg_replace("/^(\d{2})(\d{2})$/", "$1:$2", $row->endTime) : null;
                }
            } else {
                $arrDate = isset($row->startTime) ? preg_replace("/^(\d{2})(\d{2})$/", "$1:$2", $row->startTime) : null;
                $depDate = isset($row->endTime) ? preg_replace("/^(\d{2})(\d{2})$/", "$1:$2", $row->endTime) : null;
            }
            $segment["Port"] = 'Port: ' . $row->startPortName;

            if (!empty($arrDate)) {
                $segment["ArrDate"] = strtotime($arrDate, $date);
            }

            if (!empty($depDate)) {
                $segment["DepDate"] = strtotime($depDate, $date);
                $segments[] = $segment;
                $segment = [];
            }// if (!empty($depDate))
        }// foreach ($res->itinerary->events as $row)
        // last segment, final arrival
        if (isset($segment["ArrDate"])) {
            $segments[] = $segment;
        }

        if (count($points) === 2 && !empty($segments)) {
            if ($segments[0]['Port'] !== 'Port: ' . $points[0] || end($segments)['Port'] !== 'Port: ' . $points[1]) {
                $this->logger->error("look's like not the same routes");
                $this->logger->debug(var_export($points, true));
                $this->logger->emergency(var_export($segments, true));

                return $result;
            }
        } else {
            $this->sendNotification("can't check first point // ZM");
            $this->logger->debug(var_export($points, true), ['pre' => true]);
        }

        if (count($segments) > 0) {
            $result["TripSegments"] = $this->converter->Convert($segments);
        } else {
            $result["TripSegments"] = [];
        }
        //## Events ###
        if (!empty($events)) {
            $rows = [];

            foreach ($events as $event) {
                $key = $event->startDate . $event->startTime . '_' . $event->endDate . $event->endTime;

                if (!isset($rows[$key])) {
                    $rows[$key] = [
                        'numPassengers' => [$event->paxIncluded],
                        'totalPrice'    => (float) $event->totalPrice,
                        'row'           => $event,
                    ];
                } else {
                    $rows[$key]['numPassengers'][] = (int) $event->paxIncluded;
                    $rows[$key]['totalPrice'] += (float) $event->totalPrice;
                }
            }

            foreach ($rows as $key => $event) {
                $it = [
                    "Kind"        => "E",
                    "TripNumber"  => $result['RecordLocator'],
                    "ConfNo"      => CONFNO_UNKNOWN,
                    "EventType"   => EVENT_EVENT,
                    "Guests"      => count($event['numPassengers']),
                    "Name"        => $event['row']->name,
                    "Address"     => 'Port: ' . $event['row']->startPortName,
                    "Status"      => $event['row']->statusDesc,
                    "TotalCharge" => $event['totalPrice'],
                    "Currency"    => $result['Currency'],
                ];
                // hardCode
                if ($this->http->FindPreg("/ Vincent & the\s*$/", false, $it['Address'])) {
                    $it['Address'] = "Port: St. Vincent, St. Vincent & the Grenadines";
                }

                if (isset($result['Passengers'][$event['numPassengers'][0]])) {
                    $it["DinerName"] = $result['Passengers'][$event['numPassengers'][0]];
                }
                $startDate = strtotime(preg_replace("/^(\d{2})(\d{2})(\d{4})$/", "$1/$2/$3", $event['row']->startDate));
                $startTime = preg_replace("/^(\d{2})(\d{2})$/", "$1:$2", $event['row']->startTime);

                if (isset($event['row']->endDate) && empty($event['row']->endDate)) {
                    $event['row']->endDate = $event['row']->startDate;
                }
                $endDate = strtotime(preg_replace("/^(\d{2})(\d{2})(\d{4})$/", "$1/$2/$3", $event['row']->endDate));
                $endTime = preg_replace("/^(\d{2})(\d{2})$/", "$1:$2", $event['row']->endTime);

                $it['StartDate'] = !empty($startTime) ? strtotime($startTime, $startDate) : $startDate;
                $it['EndDate'] = !empty($endTime) ? strtotime($endTime, $endDate) : $endDate;
                // object TODO
//                if (!empty($result['Passengers']) && count($result['Passengers'])===count($event['numPassengers'])){
//                    $it['GuestNames'] = $result['Passengers'];
//                }
                $this->events[] = $it;
            }
        }
        //## Flights ###
        if (!empty($flights)) {
            $it = [
                "Kind"          => "T",
                "RecordLocator" => $result['RecordLocator'],
                "TripSegments"  => [],
            ];

            if (isset($result['Passengers'])) {
                $it['Passengers'] = $result['Passengers'];
            }

            foreach ($flights as $row) {
                $seg = [];
                $seg['DepName'] = $row->startCityName;
                $seg['DepCode'] = $row->startCityCode;
                $date = strtotime(preg_replace("/^(\d{2})(\d{2})(\d{4})$/", "$1/$2/$3", $row->startDate));
                $depDate = isset($row->startTime) ? preg_replace("/^(\d{2})(\d{2})$/", "$1:$2", $row->startTime) : null;

                if ($depDate) {
                    $seg['DepDate'] = strtotime($depDate, $date);
                }
                $seg['ArrName'] = $row->endCityName;
                $seg['ArrCode'] = $row->endCityCode;
                $date = strtotime(preg_replace("/^(\d{2})(\d{2})(\d{4})$/", "$1/$2/$3", $row->endDate));
                $arrDate = isset($row->endTime) ? preg_replace("/^(\d{2})(\d{2})$/", "$1:$2", $row->endTime) : null;

                if ($arrDate) {
                    $seg['ArrDate'] = strtotime($arrDate, $date);
                }
                $seg['FlightNumber'] = $row->flightNum;
                $seg['AirlineName'] = $row->flightCarrierCode;
                $seg['Operator'] = $row->flightOperCarrierCode;
                $seg['Aircraft'] = preg_replace("/\s+/", ' ', $row->aircraftType);
                $seg['BookingClass'] = $row->flightClass;
                $seg['Cabin'] = $row->cabinClass;

                if ($it['RecordLocator'] !== $row->airPNR) {
                    $it['RecordLocator'] = $row->airPNR;
                }
                //$row->airlineLocatorCode, $row->airPNR;
                $it['TripSegments'][] = $seg;
            }
            $it['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $it['TripSegments'])));
            $this->flights[] = $it;
//            $this->logger->emergency("check it");// TODO:
//            $headers = [
//                'Accept' => 'text/html, */*; q=0.01',
//                'Referer' => 'https://book.princess.com/cruisepersonalizer/flightTransfer.page',
//                'X-Requested-With' => 'XMLHttpRequest'
//            ];
//            $this->http->PostURL("https://book.princess.com/cruisepersonalizer/eZAirSummary.page", null, $headers);
        }

        return $result;
    }

    private function parseEvent($node)
    {
        $this->logger->notice(__METHOD__);
        $event = new Event($this->logger);
        // ConfirmationNumber
        $confNo = Html::cleanXMLValue($this->http->FindSingleNode(".//div[contains(@class, 'tour-details')]/preceding-sibling::h6", $node, true, "/([^\|]+)/"));

        if (!$confNo) {
            $confNo = Html::cleanXMLValue($this->http->FindSingleNode(".//div[contains(@class, 'tour-details')]/preceding-sibling::p[1]", $node, true, "/([^\|]+)/"));
        }
        $this->logger->info(sprintf('[%s] Parse Event #%s', $this->currentItin++, $confNo), ['Header' => 3]);
        $event->getProviderDetails()->setConfirmationNumber($confNo);
        // Status
        $event->setStatus($this->http->FindHTMLByXpath(".//div[contains(@class, 'tour-details')]", "/Status:\s*<span[^\>]*>([^<]+)/ims", $node));
        // Name
        $name = $this->http->FindSingleNode(".//div[contains(@class, 'tour-details')]/preceding-sibling::h4", $node, true, "/([^\|]+)/");

        if (!$name) {
            $name = Html::cleanXMLValue($this->http->FindSingleNode(".//div[contains(@class, 'tour-details')]/preceding-sibling::p[1]", $node, true, "/\|\s+(.+)/"));
        }
        $event->setEventName($name);
        // StartDate
        $date = $this->http->FindHTMLByXpath(".//div[contains(@class, 'tour-details')]", "/Date:\s*<strong[^\>]*>([^<]+)/ims", $node);
        $startTime = $this->http->FindHTMLByXpath(".//div[contains(@class, 'tour-details')]", "/Depart:\s*<strong[^\>]*>([^<]+)/ims", $node);

        if ($date and $startTime) {
            $event->setStartDateTime(strtotime("$date, $startTime"));
        } elseif (
            $date
            && $this->http->FindHTMLByXpath(".//div[contains(@class, 'tour-details')]", "/Depart:\s*<strong><\/strong><br>\s*Return:\s*<strong><\/strong><br>/ims", $node)
            && strstr($name, 'Rental')
        ) {
            $this->logger->notice("set default time for excursion");
            $event->setStartDateTime(strtotime("$date, 8:00"));
        }
        $endTime = $this->http->FindHTMLByXpath(".//div[contains(@class, 'tour-details')]", "/Return:\s*<strong[^\>]*>([^<]+)/ims", $node);

        if ($date and $endTime) {
            $event->setEndDateTime(strtotime("$date, $endTime"));
        }
        // GuestNames
        $event->setGuests($this->http->FindNodes(".//div[contains(@class, 'tour-details')]//div[@class = 'passengers']/strong", $node));
        // Guests
        $event->setGuestCount($this->http->FindHTMLByXpath(".//div[contains(@class, 'tour-details')]", "/Adult:\s*<strong[^\>]*>(\d+)/ims", $node));
        // Address
        $event->setAddressText($this->http->FindSingleNode(".//div[contains(@class, 'tour-details')]/preceding-sibling::h6", $node, true, "/\|\s*([^<]+)/"));

        if (!$event->getAddressText()) {
            $event->setAddressText($event->getEventName());
        }
        // TotalCharge
        $totalStr = $this->http->FindSingleNode(".//div[contains(@class, 'tour-pricing')]//th[contains(text(), 'Total Price:')]/following-sibling::th[1]", $node);
        $event->setCurrencyCode($this->currency($totalStr));
        // Currency
        $event->setTotal($this->cost($totalStr));
        // EventType
        $event->setEventType(EVENT_EVENT);

        return $event->convertToOldArrayFormat();
    }
}
