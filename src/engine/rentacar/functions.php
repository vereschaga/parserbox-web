<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerRentacar extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    protected $endHistory = false;

    private $logonId = null;
    private $historyToken = null;
    private $tokenExp = null;
    private $headers = [
        "Accept"                      => "*/*",
        "Origin"                      => "https://www.enterprise.com",
        "locale"                      => "en_US",
        "domain_country_of_residence" => "US",
        "BRAND"                       => "ENTERPRISE",
        "CHANNEL"                     => "WEB",
    ];

    private $message = null;

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->http->setHttp2(true);

        // incapsula workaround
        if ($this->attempt == 2) {
            /*
            $this->http->SetProxy($this->proxyReCaptcha());// may be there should be luminati?
            */
            $this->setProxyGoProxies();
        } else {
//            $this->http->SetProxy($this->proxyDOP());
            $this->http->SetProxy($this->proxyReCaptcha());
        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.enterprise.com/en/home.html');
        /*

        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass']
        ];
        $headers = [
            "Content-Type" => "application/json;charset=utf-8",
            "Accept" => "application/json, text/plain, * /*",
        ];
        $this->http->PostURL("https://prd.webapi.enterprise.com/enterprise-ewt/ecom-service/login/EP?locale=en_US", json_encode($data), $headers);*/

        //		// refs #11588
        //		$charset = "ISO-8859-1";
        //		if(preg_match("#charset\s*=\s*(\S+)#", $this->http->Response['headers']["content-type"], $m))
        //			$charset = $m[1];
        //		$this->AccountFields['Pass'] = iconv("UTF-8", "{$charset}//IGNORE", $this->AccountFields['Pass']);
//
//        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        //		$this->http->Form['loginWidgetButton'] = 'Login';
        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Apache Tomcat/7.0.33 - Error report
        if ($this->http->FindSingleNode("//title[contains(text(), 'Apache Tomcat/7.0.33 - Error report')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // GATEWAY_TIMEOUT
        if ($this->http->FindPreg("/GATEWAY_TIMEOUT/i")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
//        $response = $this->http->JsonLog();
        // Access is allowed
//        if ($this->http->FindSingleNode('//span[contains(text(), "Member #")]'))
//            return true;
        if ($this->selenium()) {
            return true;
        }

        $this->logger->error('Message: ' . $this->message);
        // We're sorry, but there's something wrong with your email, member number or password
        if (stristr($this->message, "We're sorry, but there's something wrong with your email, member number or password")
            // We're sorry, but your password doesn't meet our Password Policy rules.
            || stristr($this->message, "We're sorry, but your password doesn't meet our Password Policy rules.")
            // We're sorry, but there is something wrong with your loyalty membership. Please call us for assistance.
            || stristr($this->message, "We're sorry, but there is something wrong with your loyalty membership")
            // We're sorry, but there's a problem with your password. Please reset your password to sign in to your account.
            || stristr($this->message, "We're sorry, but there's a problem with your password.")
            // The password reset link has expired. Please reset again.
            || stristr($this->message, "The password reset link has expired. Please reset again.")
            // We're sorry, but it looks like there is something wrong with the character format entered. Please clear the field and re-enter your text
            || stristr($this->message, "We're sorry, but it looks like there is something wrong with the character format entered")
            // Sign in credentials are required. Please provide your password.
            || stristr($this->message, "Sign in credentials are required. Please provide your password.")
            || stristr($this->message, "We're sorry, your password has expired. Please reset your password and try again.")
        ) {
            throw new CheckException($this->message, ACCOUNT_INVALID_PASSWORD);
        }
        // Enterprise Plus Terms and Conditions
        if (strstr($this->message, 'Latest version of Terms and conditions not accepted.')) {
            $this->throwAcceptTermsMessageException();
        }
        // We are sorry. Something went wrong. Please try again or call us for assistance.
        if (strstr($this->message, 'We are sorry. Something went wrong. Please try again or call us for assistance.')
            || strstr($this->message, 'We\'re sorry. Something went wrong. Please try again or call us for assistance.')
            // An unexpected system error has occurred
            || strstr($this->message, 'An unexpected system error has occurred')
            || strstr($this->message, 'An Error Occurred')
            // ECROS TOOK TOO LONG TO RESPOND
            || strstr($this->message, 'ECROS TOOK TOO LONG TO RESPOND')) {
            throw new CheckException($this->message, ACCOUNT_PROVIDER_ERROR);
        }
        // For your security, please update your password to meet our new secure login requirements.
        if (strstr($this->message, 'For your security, please update your password to meet our new secure login requirements.')) {
            $this->throwProfileUpdateMessageException();
        }
        // ERROR: exception
        if ($this->message == 'exception') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if (!$this->message && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            throw new CheckRetryNeededException(3, 10);
        }

        /*if (isset($response->auth_token))
            return true;
        // Please sign in to your account to continue
        if ($message = $this->http->FindPreg('/"message":"(Please sign in to your account to continue\.)"/'))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        if ($message = $this->http->FindPreg('/"message":"(We\'re sorry, but there\'s something wrong with your email, member number or password. Please provide a valid email or member number and password to sign in to your account\.)"/'))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // We're sorry, but there's a problem with your password. Please reset your password to sign in to your account.
        if ($message = $this->http->FindPreg('/"message":"(We\'re sorry, but there\'s a problem with your password\. Please reset your password to sign in to your account\.)"/'))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // We're sorry, but your password doesn't meet our Password Policy rules.
        if ($message = $this->http->FindPreg('/"message":"(We\'re sorry, but your password doesn\'t meet our Password Policy rules\.)"/'))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // We're sorry, but there is something wrong with your loyalty membership. Please call us for assistance.
        if ($message = $this->http->FindPreg('/"message":"(We\'re sorry, but there is something wrong with your loyalty membership\. Please call us for assistance\.)"/'))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        // We're sorry, but our services are temporarily offline. Please try again or call us for assistance.
        if ($message = $this->http->FindPreg('/"message":"(We\'re sorry, but our services are temporarily offline. Please try again or call us for assistance\.)"/'))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        // We are sorry. Something went wrong. Please try again or call us for assistance.
        if ($message = $this->http->FindPreg('/"message":"(We(?:\'| a)re sorry\. Something went wrong\. Please try again or call us for assistance\.)"/'))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        // Enterprise Plus Terms and Conditions
        if ($this->http->FindPreg('/"message":"Latest version of Terms and conditions not\s*accepted."/'))
            $this->throwAcceptTermsMessageException();
        // We're sorry, but your password doesn't meet our security standards. Please update your password.
        if ($this->http->FindPreg('/"message":"We\'re sorry, but your password doesn\'t meet our security standards\. Please update your password\."/'))
            throw new CheckException("Enterprise Rent-A-Car website is asking you to update your password, until you do so we would not be able to retrieve your account information", ACCOUNT_PROVIDER_ERROR); /*checked* /

        // Password does not meet security standards. Please change password
        if ($message = $this->http->FindPreg('/\"message\":\"((?:Password does not meet security standards. Please change password|We\'re sorry, but your password doesn\'t meet our security standards\. Please update your password\.))\"/'))
            throw new CheckRetryNeededException(2, 10, $message, ACCOUNT_PROVIDER_ERROR);*/

        return $this->checkErrors();
    }

    public function Parse()
    {
        $sessionResponse = $this->getUrlSession();
//        $sessionId = ArrayVal($sessionResponse, 'sessionId');
        $sessionTimeout = ArrayVal($sessionResponse, 'sessionTimeout');

//        if (!$sessionId) {
        if (!$sessionTimeout) {
            return;
        }
        $data = [
            "username"             => $this->AccountFields['Login'],
            "password"             => $this->AccountFields['Pass'],
            "remember_credentials" => true,
            "ulp_post_cutover"     => false,
        ];
        $headers = $this->headers + [
            "Content-Type"                => "application/json",
            //            "redis"                       => $sessionId,
        ];
        $headers["Accept"] = "application/json, text/plain, */*";
        $this->http->PostURL("https://prd-west.webapi.enterprise.com/enterprise-ewt/enterprise/profile/login/EP", json_encode($data), $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->profile)) {
            if (
                $this->http->FindSingleNode("//iframe[contains(@src, '_Incapsula_Resource')]")
                || empty($this->http->Response['body'])
            ) {
                $this->markProxyAsInvalid();

                if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                    $this->logger->notice("_Incapsula_Resource");

                    return;
                }

                throw new CheckRetryNeededException(3);
            }

            return;
        }

        $sessionResponse = $this->getUrlSession();
        $renter = ArrayVal(ArrayVal($sessionResponse, 'analytics'), 'renter');

        if ($renter) {
            // Member #
            $this->SetProperty("MemberNumber", ArrayVal($renter, 'loyaltyId'));
            // Tier Level
            $this->SetProperty("TierLevel", ArrayVal($renter, 'loyaltyTier'));
        }// if ($renter)

        $profileResponse = ArrayVal(ArrayVal($sessionResponse, 'reservationSession'), 'profileResponse');
        $profile = ArrayVal($profileResponse, 'profile');
        $basic_profile = ArrayVal($profile, 'basic_profile');

        if ($basic_profile) {
            // Name
            $this->SetProperty("Name", beautifulName(ArrayVal($basic_profile, 'first_name') . " " . ArrayVal($basic_profile, 'last_name')));
            // Rentals
            $this->SetProperty('RentalsCount', ArrayVal($basic_profile['loyalty_data'], 'rentals_to_date', null));
            // Rentals Days
            $this->SetProperty('RentalDays', ArrayVal($basic_profile['loyalty_data'], 'rental_days_to_date', null));
            // Balance - points to date
            $this->SetBalance(ArrayVal($basic_profile['loyalty_data'], 'points_to_date'));

            // Expiration Date  // refs #5959   https://redmine.awardwallet.com/issues/5959#note-21
            $exp = ArrayVal($basic_profile['loyalty_data'], 'points_expiration_date', null);

            if (strtotime($exp)) {
                $this->SetExpirationDate(strtotime($exp));
            }
            /*
             * Enterprise Plus is currently under maintenance.
             * If you need to make an update, you can call us at 866-507-6222 or make changes on our mobile app.
             *
             * We apologize for the inconvenience. Good news, you are logged in and can still make your reservation.
             */
            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                if ($this->http->FindPreg("/We are sorry. Something went wrong. Please try again or call us for assistance./")) {
                    if ($this->http->FindPreg("/\"points_to_date\":null,/")) {
                        $this->SetWarning("Loyalty information is temporarily unavailable");
                    }/*review*/
                    elseif (!empty($this->Properties['MemberNumber']) && !empty($this->Properties['Name']) && isset($this->Properties['RentalsCount'], $this->Properties['RentalDays'])) {
                        $this->SetBalanceNA();
                    }
                }
            }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

            $this->logonId = ArrayVal($basic_profile['loyalty_data'], 'loyalty_number', null);

            if (isset($sessionResponse['reservationSession']['comarchInfo'])) {
                $this->historyToken = ArrayVal($sessionResponse['reservationSession']['comarchInfo'], 'token', null);
                $this->tokenExp = ArrayVal($sessionResponse['reservationSession']['comarchInfo'], 'expirationTime', null);
            }

            // TODO: Collection occurs from the old page, the new one did not find
            /*
            if (isset($this->logonId, $this->historyToken, $this->tokenExp)) {
                // Expiration Date  // refs #5959
                $this->http->PostURL('https://enterpriseplus.enterprise.com/group/ehi/account-activity', array(
                    "domain" => "legacy.enterprise.com",
                    "logonId" => $this->logonId,
                    "tokenExp" => $this->tokenExp,
                    "tokenId" => $this->historyToken
                ));
                $authorization = $this->http->FindPreg("/access_token\":\"([^\"]+)/");
                if (
                    $this->http->FindPreg('/window\.location\.href=\'http:\/\/enterpriseplus\.enterprise\.com\/group\/ehi\/account-activity\'.replace\(\'http:\',\'https:\'\);/ims')
                    && $authorization
                ) {
//                    $this->http->GetURL("https://enterpriseplus.enterprise.com/group/ehi/account-activity");
                    $headers = [
                        "Accept"        => "application/json, text/plain, *
            /*",
                        "Authorization" => "Bearer {$authorization}",
                        "Content-type"  => "application/json",
                        "Referer"       => "https://enterpriseplus.enterprise.com/group/ehi/account-activity",
                    ];
                    $data = [
                        "startDate" => $dateFrom = date('m/d/Y', strtotime('-9 year')),
                        "endDate"   => $dateTo = date('m/d/Y'),
                    ];
                    $this->http->PostURL("https://enterpriseplus.enterprise.com/ehi-clm/me/activities", json_encode($data), $headers);
                }
                $exp = $this->http->FindPreg('/"expiryDate":"([^"]+)"/ims');
                if (strtotime($exp))
                    $this->SetExpirationDate(strtotime($exp));
            }// if (isset($this->logonId, $this->historyToken, $this->tokenExp))
            */
        }// if ($basic_profile)
        elseif ($this->http->Response['code'] != 503 && $this->ErrorCode == ACCOUNT_CHECKED) {
            $this->sendNotification("Exp date not found");
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->GetURL('https://prd-west.webapi.enterprise.com/enterprise-ewt/enterprise/mytrips/upcoming?&now=' . date('UB') . '&startRecord=0&ecordCount=5&locale=en_US', $this->headers);
        $response = $this->http->JsonLog(null, 0, true);
        $rentals = ArrayVal($response, 'trip_summaries', []);
        $this->logger->debug("Total " . count($rentals) . " rentals were found");
        // no itineraries
        if ($this->http->FindPreg("/\"trip_summaries\":\[\]/")) {
            if ($this->ParsePastIts) {
                $pastItineraries = $this->parsePastItineraries();

                if (!empty($pastItineraries)) {
                    return $pastItineraries;
                }
            }// if ($this->ParsePastIts)

            return $this->noItinerariesArr();
        }

        if ($msg = $this->http->FindPreg("/\{\"messages\":\[\{\"code\":\"CROS_SYSTEM_ERROR\",\"message\":\"([^\"]+)\"/")) {
            $this->logger->debug("DEBUG PART");
            $rentalsTest = [];
            $this->http->GetURL("https://prd-east.webapi.enterprise.com/enterprise-ewt/enterprise/mytrips/current");
            $respTest = $this->http->JsonLog(null, 1, true);
            $rentalsTest = array_merge(ArrayVal($respTest, 'trip_summaries', []), $rentalsTest);
            $this->http->GetURL("https://prd-east.webapi.enterprise.com/enterprise-ewt/enterprise/mytrips/upcoming?recordCount=30");
            $respTest = $this->http->JsonLog(null, 1, true);
            $rentalsTest = array_merge(ArrayVal($respTest, 'trip_summaries', []), $rentalsTest);

            if (!empty($rentalsTest)) {
                $this->sendNotification("check reservations// MI");
            }

            if ($this->ParsePastIts) {
                $this->http->GetURL("https://prd-east.webapi.enterprise.com/enterprise-ewt/enterprise/mytrips/past");
                $respTest = $this->http->JsonLog(null, 1, true);
                $rentalsTest = ArrayVal($respTest, 'trip_summaries', []);

                foreach ($rentalsTest as $tr) {
                    $invoice_number = $tr['invoice_number'] ?? null;
                    $customer_last_name = $tr['customer_last_name'] ?? null;

                    if (!$invoice_number && $customer_last_name) {
                        $this->http->GetURL("https://prd-east.webapi.enterprise.com/enterprise-ewt/enterprise/mytrips/invoiceUnauth?invoiceNumber={$invoice_number}&lastName={$customer_last_name}");
                    }
                }
            }
            $this->logger->error($msg);
        }

        foreach ($rentals as $rental) {
            $result[] = $this->ParseItinerary($rental);
        }// foreach ($rentals as $rental)

        if ($this->ParsePastIts) {
            $result = array_merge($result, $this->parsePastItineraries());
        }

        return $result;
    }

    public function ParseItinerary($rental, $past = false)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Parse itinerary #' . ArrayVal($rental, 'confirmation_number'), ['Header' => 3]);
        $r = $this->itinerariesMaster->createRental();
        // Number
        if ($past) {
            $r->general()->confirmation(ArrayVal($rental, 'invoice_number'), "Confirmation Number");
        } else {
            $r->general()->confirmation(ArrayVal($rental, 'confirmation_number'), "Confirmation Number");
        }
        // RenterName
        $r->general()->traveller(beautifulName(ArrayVal($rental, 'customer_first_name') . " " . ArrayVal($rental, 'customer_last_name')));
        // PickupDatetime
        if ($date = str_replace("T", ' ', ArrayVal($rental, 'pickup_time'))) {
            $r->pickup()->date2($date);
        }
        // DropoffDatetime
        if ($date = str_replace("T", ' ', ArrayVal($rental, 'return_time'))) {
            $r->dropoff()->date2($date);
        } elseif (empty(ArrayVal($rental, 'return_time')) && !empty(ArrayVal($rental, 'pickup_time'))) {
            $r->dropoff()->noDate();
        }
        // PickupLocation
        $street = ArrayVal($rental['pickup_location']['address'], 'street_addresses', []);
        $street = array_filter($street, function ($v, $k) {
            return $v != 'No Arpt/ship Pickup Service';
        }, ARRAY_FILTER_USE_BOTH);
        $pickupAddress =
            implode(', ', str_replace('>', '', $street)) . ', ' .
            ArrayVal($rental['pickup_location']['address'], 'city') . ', ' .
            ArrayVal($rental['pickup_location']['address'], 'country_subdivision_code') . ', ' .
            ArrayVal($rental['pickup_location']['address'], 'postal') . ', ' .
            ArrayVal($rental['pickup_location']['address'], 'country_code');
        $pickupAddress = trim($pickupAddress, ', ');

        if (!empty($pickupAddress)) {
            $r->pickup()->location($pickupAddress);
        }

        // DropoffLocation
        $street = ArrayVal($rental['return_location']['address'], 'street_addresses', []);
        $street = array_filter($street, function ($v, $k) {
            return $v != 'No Arpt/ship Pickup Service';
        }, ARRAY_FILTER_USE_BOTH);
        $dropoffAddress =
            implode(', ', str_replace('>', '', $street)) . ', ' .
            ArrayVal($rental['return_location']['address'], 'city') . ', ' .
            ArrayVal($rental['return_location']['address'], 'country_subdivision_code') . ', ' .
            ArrayVal($rental['return_location']['address'], 'postal') . ', ' .
            ArrayVal($rental['return_location']['address'], 'country_code');
        $dropoffAddress = trim($dropoffAddress, ', ');

        if (!empty($dropoffAddress)) {
            $r->dropoff()->location($dropoffAddress);
        }
        // Phones
        $phones = ArrayVal($rental['return_location']['address'], 'phones', []);

        foreach ($phones as $phone) {
            // PickupFax
            if ($phones['phone_type'] == 'FAX') {
                $r->pickup()->fax($phones['phone_number']);
            }
            // PickupPhone
            if ($phones['phone_type'] == 'OFFICE') {
                $r->pickup()->phone($phones['phone_number']);
            }
        }// foreach ($phones as $phone)
        // Car Model
        if ($past) {
            $r->car()->model(ArrayVal($rental['vehicle_details'], 'make') . " " . ArrayVal($rental['vehicle_details'], 'model'));
        }
        // CarType
        $r->car()->type(ArrayVal($rental['vehicle_details'], 'name'), true, false);

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        return [];
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"        => "PostingDate",
            "Description" => "Description",
            "Points"      => "Miles",
            "Rentals"     => "Info",
            "Rental days" => "Info",
            //            "Actions"     => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->http->FilterHTML = false;
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        if (isset($this->logonId, $this->historyToken, $this->tokenExp)) {
            $this->http->PostURL('https://enterpriseplus.enterprise.com/group/ehi/account-activity', [
                "domain"   => "legacy.enterprise.com",
                "logonId"  => $this->logonId,
                "tokenExp" => $this->tokenExp,
                "tokenId"  => $this->historyToken,
            ]);
        }// if (isset($this->logonId, $this->historyToken, $this->tokenExp))

        $authorization = $this->http->FindPreg("/access_token\":\"([^\"]+)/");

        if (
            $this->http->FindPreg('/window\.location\.href=\'http:\/\/enterpriseplus\.enterprise\.com\/group\/ehi\/account-activity\'.replace\(\'http:\',\'https:\'\);/ims')
            && $authorization
        ) {
//            $this->http->GetURL("https://enterpriseplus.enterprise.com/group/ehi/account-activity");
            $headers = [
                "Accept"        => "application/json, text/plain, */*",
                "Authorization" => "Bearer {$authorization}",
                "Content-type"  => "application/json",
                "Referer"       => "https://enterpriseplus.enterprise.com/group/ehi/account-activity",
            ];
            $data = [
                "startDate" => $dateFrom = date('m/d/Y', strtotime('-9 year')),
                "endDate"   => $dateTo = date('m/d/Y'),
            ];
            $this->http->PostURL("https://enterpriseplus.enterprise.com/ehi-clm/me/activities", json_encode($data), $headers);
            $startIndex = sizeof($result);
            $result = $this->ParseJsonHistory($startIndex, $startDate);
        }

        /*
        $date = time();
        $page = 0;
        $endHistory = 0;
        if ($this->http->ParseForm('accountActivityForm')) {
            do {
                $this->logger->debug("Page #{$page}");
                $this->http->SetInputValue('formPageNum', 1);
                $this->http->SetInputValue('filter', 1);
                // set dates
                $this->http->SetInputValue('dateTo', date('m/d/Y', $date));
                $date = strtotime('-1 year', $date);
                $this->http->SetInputValue('dateFrom', date('m/d/Y', $date));
                $date = strtotime('-1 day', $date);

                $this->http->PostForm();
                $page++;
                $startIndex = sizeof($result);
                $history = $this->ParsePageHistory($startIndex, $startDate);
                if (!empty($history))
                    $result = array_merge($result, $history);
                else {
                    $this->logger->notice(">>> Stop: end of history was found");
                    $endHistory++;
                    if ($endHistory > 3) {// refs #5011
                        $this->endHistory = true;
                    }
                }
                // page limit reached
                if ($page >= 30) {
                    $this->logger->notice(">>> Stop: page limit reached");
                    $this->endHistory = true;
                }// if ($page >= 30)
            }// do {
            while (
                date('Y', $date) > 1990
                && !$this->endHistory
                && $this->http->ParseForm('accountActivityForm')
            );
        }// if ($this->http->ParseForm('accountActivityForm'))
        */

        $this->getTime($startTimer);

        return $result;
    }

    public function ParseJsonHistory($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->JsonLog();
        $this->logger->debug("Total " . ((is_array($nodes) || ($nodes instanceof Countable)) ? count($nodes) : 0) . " history rows were found");

        if ($nodes) {
            foreach ($nodes as $node) {
                $dateStr = $node->transactionDate;
                $postDate = strtotime($dateStr);

                if (empty($postDate)) {
                    $this->logger->notice("empty date, try next node");

                    continue;
                }

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");
                    $this->endHistory = true;

                    break;
                }
                $result[$startIndex]['Date'] = $postDate;
                $description = $node->pickupLocationValue;

                if (!empty($description) && !empty($node->pickupLocationCity)) {
                    $description .= ", " . $node->pickupLocationCity;
                }

                if (
                empty($node->pickupLocationValue)
                && empty($node->pickupLocationCity)
                && $node->typeName == 'Generic accrual'
            ) {
                    $description = "Bonus points";
                }
                $result[$startIndex]['Description'] = $description;
                $result[$startIndex]['Points'] = $node->points;
                $result[$startIndex]['Rentals'] = $node->eqr;
                $result[$startIndex]['Rental days'] = $node->eqrd;
//            $result[$startIndex]['Actions'] = $node->availRedemptionAction;
                $startIndex++;
            }
        }// for ($i = 0; $i < $nodes->length; $i++)

        return $result;
    }

    protected function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox();
            $selenium->setKeepProfile(true);
//            $selenium->http->setRandomUserAgent();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->disableImages();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www.enterprise.com/en/account.html#ep");

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "username"]'), 20);

            if (!$loginInput) {
                // save page to logs
                $this->saveToLogs($selenium);
                $selenium->http->GetURL("https://www.enterprise.com/");
                $selenium->http->GetURL("https://www.enterprise.com/en/account.html#ep");

                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "username"]'), 20);
            }

            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//div[@id ="account"]//div[button[contains(text(), "Sign In") and @aria-label="Sign In"]]'), 0);

            if ($widget = $selenium->waitForElement(WebDriverBy::xpath('//div[@class = "login-widget active"]//button[@aria-expanded="true"]'), 0)) {
                $this->saveToLogs($selenium);
                $widget->click();
            }

            // save page to logs
            $this->saveToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                $this->logger->error("something went wrong");

                return $this->checkErrors();
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In")]'), 0);
            // save page to logs
            $this->saveToLogs($selenium);

            if (!$button) {
                $this->logger->error("something went wrong");

                return $this->checkErrors();
            }

            //$("#adroll_consent_banner").hide()
            $selenium->driver->executeScript('
                let cookies = $(\'#consent_blackbar, .ReactModalPortal , #adroll_consent_banner, #onetrust-consent-sdk\');
                if (cookies.length > 0) {
                    cookies.remove();
                }
                let modal = $(".modal-container.active");
                if (modal.length > 0) {
                    modal.find(".modal-header .close-modal").click();
                }
            ');
            sleep(1);
            $this->saveToLogs($selenium);
            $button->click();

            if (
                $selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Member #")]'), 15)
                || $this->http->FindSingleNode('//span[contains(text(), "Member #")]')
            ) {
                $result = true;
            }
            // save page to logs
            $selenium->driver->executeScript('window.stop();');
            $this->saveToLogs($selenium);
            // error
            $errorXpath = '
                //li[@class = "global-error"]/span[2]
                | //span[contains(@id, "-error")]
                | //button[contains(text(), "ACCEPT TERMS & CONDITIONS")]
            ';
            $message = $selenium->waitForElement(WebDriverBy::xpath($errorXpath), 0);

            if (
                !$result
                && !$message && ($button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In")]'), 0))
            ) {
                $button->click();

                if (
                    $selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Member #")]'), 15)
                    || $this->http->FindSingleNode('//span[contains(text(), "Member #")]')
                ) {
                    $result = true;
                }
                // save page to logs
                $selenium->driver->executeScript('window.stop();');
                $this->saveToLogs($selenium);
                $message = $selenium->waitForElement(WebDriverBy::xpath($errorXpath), 0);
            }

            if ($message) {
                $this->message = $message->getText();
            } else {
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            }
        }// try
        catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());

            if (strstr($e->getMessage(), 'Command timed out in client when executing')) {
                $retry = true;
            }
        }// catch (TimeOutException $e)
        catch (WebDriverCurlException | NoSuchDriverException | NoSuchWindowException | UnrecognizedExceptionException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        }// catch (TimeOutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->logger->debug("[attempt]: {$this->attempt}");

            throw new CheckRetryNeededException(3, 0);
        }

        return $result;
    }

    protected function getUrlSession()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://prd-west.webapi.enterprise.com/enterprise-ewt/current-session?&now=" . date('UB') . "&locale=en_US", $this->headers);
        $response = $this->http->JsonLog(null, 3, true);

        if (isset($response['messages'][0]['code']) && $response['messages'][0]['code'] === '500') {
            $this->logger->debug('current-session retry');
            sleep(2);
            $this->http->GetURL("https://prd-west.webapi.enterprise.com/enterprise-ewt/current-session?&now=" . date('UB') . "&locale=en_US", $this->headers);
            $response = $this->http->JsonLog(null, 3, true);
        }// if (isset($response['messages'][0]['code']) && $response['messages'][0]['code'] === '500')

        return $response;
    }

    private function saveToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }

    private function parsePastItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Past Itineraries", ['Header' => 2]);
        $startTimer = $this->getTime();
        $result = [];
        $this->http->GetURL('https://prd-west.webapi.enterprise.com/enterprise-ewt/enterprise/mytrips/past?&now=' . date('UB') . '4&startRecord=0&ecordCount=5&locale=en_US', $this->headers);
        $response = $this->http->JsonLog(null, 0, true);
        $pastIts = ArrayVal($response, 'trip_summaries', []);
        $this->logger->debug("Total " . count($pastIts) . " past reservations found");

        if ($this->http->FindPreg("/\"trip_summaries\":\[\]/")) {
            $this->logger->notice(">>> We don't have any past rentals on file for you.");

            return [];
        }

        foreach ($pastIts as $pastIt) {
            $result[] = $this->ParseItinerary($pastIt, true);
        }// foreach ($rentals as $rental)
        $this->getTime($startTimer);

        return $result;
    }

    /*
    function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query('//div[contains(@class, "content-section")]/div/div[@class="title-row"]');
        $this->logger->debug("Found {$nodes->length} items");
        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $dateStr = $this->http->FindSingleNode('div[1]', $node);
            $postDate = strtotime($dateStr);
            if (empty($postDate)) {
                $this->logger->notice("empty date, try next node");
                continue;
            }
            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;
                break;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Description'] = $this->http->FindSingleNode('div[2]', $node);
            $result[$startIndex]['Points'] = $this->http->FindSingleNode('div[3]', $node);
            $result[$startIndex]['Rentals'] = $this->http->FindSingleNode('div[4]', $node);
            $result[$startIndex]['Rental days'] = $this->http->FindSingleNode('div[5]', $node);
            $result[$startIndex]['Actions'] = $this->http->FindSingleNode('div[6]', $node);
            $startIndex++;
        }// for ($i = 0; $i < $nodes->length; $i++)

        return $result;
    }
    */
}
