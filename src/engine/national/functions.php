<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerNational extends TAccountChecker
{
    use ProxyList;
//    use SeleniumCheckerHelper;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && stristr($properties['SubAccountCode'], 'RentalCreditsEarned')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
        }

        if ($fields['Balance'] == 1) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%d day");
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%d days");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");

        $this->setProxyGoProxies();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->selenium();

        $this->http->GetURL("https://www.nationalcar.com/en/home.html");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $headers = $this->getHeaders("_2");
        $data = [
            "username"             => $this->AccountFields['Login'],
            "password"             => $this->AccountFields['Pass'],
            "remember_credentials" => true,
        ];
        $this->http->GetURL("https://prd-west.webapi.nationalcar.com/gma-national/session/current?clean=false", $headers);

        if ($this->http->Response['code'] == 403 && $this->http->FindPreg("/_Incapsula_Resource/")) {
            $this->DebugInfo = 'Incapsula';
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 5);

            return false;
        }

        $this->http->JsonLog();
        $this->http->GetURL("https://prd-west.webapi.nationalcar.com/gma-national/globalgateway/headers", $headers);
        $this->http->JsonLog();
        sleep(2);
        $this->http->PostURL("https://prd-west.webapi.nationalcar.com/gma-national/profile/login", json_encode($data), $headers);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# nationalcar.com reservation system is currently undergoing system maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'system is currently undergoing system maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# We are currently performing scheduled maintenance on our reservation system.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently performing scheduled maintenance on our reservation system.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(normalize-space(text()), 'We are currently performing scheduled maintenance on our reservation system')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        //# nationalcar.com reservation system is currently experiencing high volume
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'system is currently experiencing high volume')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Internal Server Error
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# The system just experienced an unexpected error
        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'The system just experienced an unexpected error')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->logger->debug("[Code]: " . $this->http->Response['code']);
        // retries
        if (in_array($this->http->Response['code'], [0, 503, 500])
            || $this->http->FindPreg("/<h1>We're sorry. The page you're <br>looking for does not exist\.<\/h1>/")) {
            throw new CheckRetryNeededException(3);
        }

        return false;
    }

    public function Login()
    {
        //		if (!$this->http->PostForm())
        //			return $this->checkErrors();
        $response = $this->http->JsonLog();

        if (isset($response->analytics)) {
            return true;
        }

        if (isset($response->messages[0]->message)) {
            $message = $response->messages[0]->message;
            $this->logger->error("[Error]: {$message}");

            if (
                $message == "We're sorry, but there's something wrong with your username/member number or password. Please provide valid login information to access your account."
                || $message == "We're sorry, but there's something wrong with your email, member number or password. Please provide a valid email or member number and password to sign in to your account."
                || $message == 'The password reset link has expired. Please reset again.'
                || $message == 'For your security, please update your password to meet our new secure login requirements.'
                || strstr($message, "We're sorry, but it looks like there is something wrong with the character format entered. Please clear the field and re-enter your text.")
                || $message == "We're sorry, your password has expired. Please reset your password and try again."
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'We are sorry. Something went wrong. Please try again or call us for assistance.'
                || $message == "We're sorry. Something went wrong. Please try again or call us for assistance."
                || $message == 'An Error Occurred'
                || $message == 'We\'re sorry, but there is something wrong with your loyalty membership. Please call us for assistance.'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }// if (isset($response->messages[0]->message))

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);

        if (!isset($response->analytics->gbo->profile->profile->basic_profile)) {
            return;
        }
        $profile = $response->gbo->profile->profile->basic_profile ?? $response->analytics->gbo->profile->profile->basic_profile;
        // Reward: Rental Credits
        $this->SetProperty("Reward", beautifulName(
            $response->analytics->gbo->profile->preference->reward_preferences->reward_type
            ?? $response->gbo->profile->preference->reward_preferences->reward_type
            ?? ''
        ));
        // Name
        if (isset($profile->first_name, $profile->last_name)) {
            $this->SetProperty("Name", beautifulName($profile->first_name . " " . $profile->last_name));
        }

        if (!isset($profile->loyalty_data)) {
            return;
        }
        $loyalty = $profile->loyalty_data;
        // Balance - Free Days Available  // refs #4123
        if (isset($loyalty->free_days)) {
            $this->SetBalance($loyalty->free_days);
        } else {
            $this->logger->notice("Balance not found");
            // We have updated our Terms and Conditions since you last logged in. Please review & accept to proceed.
            $message = $response->gbo->profile->messages[0]->message ?? null;

            if ($message == 'Please accept the Terms and Conditions to continue.') {
                $this->throwAcceptTermsMessageException();
            }
//            if ($this->http->FindPreg('/"couponsCount":null,"creditsNeeded"/'))
//                $this->SetBalanceNA();
        }
        // Expiration Date
        if (isset($loyalty->redemption_coupon_data->available_coupons_lists)) {
            foreach ($loyalty->redemption_coupon_data->available_coupons_lists as $coupon) {
                if (!isset($exp) || strtotime($coupon->expiration_date) < $exp) {
                    $exp = strtotime($coupon->expiration_date);
                    $this->SetExpirationDate($exp);
                }
            }
        }
        // Tier
        $this->SetProperty("Tier", $loyalty->loyalty_tier ?? '');
        // Status Expires
        $this->SetProperty("StatusExpiration", isset($loyalty->membership_end_date) ? date("M d, Y", strtotime($loyalty->membership_end_date)) : '');
        // Rentals remaining to Next Status
        $this->SetProperty("RentalsToNextStatus", $loyalty->activity_to_next_tier->remaining_rental_count ?? '');
        // Rental Days remaining to Next Status
        $this->SetProperty("RentalDaysToNextStatus", $loyalty->activity_to_next_tier->remaining_rental_days ?? '');

        // Emerald Club Number
        $this->SetProperty("Number", $loyalty->loyalty_number ?? '');

        // Sub Accounts - Rental Credits Earned // refs #4123, 4467
        if (isset($loyalty->credits_received)) {
            // Rental Credits Earned
            $this->SetProperty("RentalCreditsEarned", $loyalty->credits_received);
            $this->SetProperty("CombineSubAccounts", false);
            $this->AddSubAccount([
                'Code'                => "RentalCreditsEarned",
                'DisplayName'         => "Rental Credits Earned",
                'Balance'             => $loyalty->credits_received,
                // Needed to next free day
                'NeededToNextFreeDay' => $loyalty->credits_needed_for_free_day ?? '',
            ], true);
        }// if (isset($loyalty->credits_received))
    }

    public function ParseItineraries()
    {
        $result = [];
        // prd-east
        $this->http->GetURL("https://prd-west.webapi.nationalcar.com/gma-national/trips/upcoming?startRecordNumber=1&recordCount=98&tripsLimit=100", $this->getHeaders());
        $response = $this->http->JsonLog(null, 2);

        if (isset($response->messages[0]->priority) && ($response->messages[0]->priority === 'ERROR')) {
            sleep(5);
            $this->http->GetURL("https://prd-west.webapi.nationalcar.com/gma-national/trips/upcoming?startRecordNumber=1&recordCount=98&tripsLimit=100", $this->getHeaders());
            $response = $this->http->JsonLog(null, 2);
        }

        if ($this->http->FindPreg("/^\{\"more_records_available\":false\}$/ims")
            || $this->http->FindPreg("/\"message\":\"No record found for renter.\"/ims")) {
            if ($this->ParsePastIts) {
                $pastItineraries = $this->parsePastItineraries();

                if (!empty($pastItineraries)) {
                    return $pastItineraries;
                }
            }// if ($this->ParsePastIts)

            return $this->noItinerariesArr();
        }

        // first reservation
        $reservations = [];
        // other reservations
        if (isset($response->upcoming_reservations)) {
            foreach ($response->upcoming_reservations as $res) {
                $reservations[] = [
                    "confirmationNumber" => $res->confirmation_number,
                    "firstName"          => $res->customer_first_name,
                    "lastName"           => $res->customer_last_name,
                ];
            }
        }

        if (isset($response->pre_write_tickets)) {// current
            foreach ($response->pre_write_tickets as $res) {
                $reservations[] = [
                    "confirmationNumber" => $res->confirmation_number,
                    "firstName"          => $res->customer_first_name,
                    "lastName"           => $res->customer_last_name,
                ];
            }
        }
        $this->logger->debug("Total " . count($reservations) . " itineraries were found");

        if (count($reservations) > 10) {
            $this->increaseTimeLimit(300);
        }

        foreach ($reservations as $i => $reservation) {
            unset($res);
//            $res = $this->ParseItinerary_new_debug($reservation);
            $res = $this->ParseItinerary($reservation);

            if (is_string($res)) {
                $this->logger->error("Error -> {$res}");
            }

            if (is_array($res) && !empty($res)) {
                $result[] = $res;
            } elseif (isset($response->upcoming_reservations[$i]) && $response->upcoming_reservations[$i]->confirmation_number === $reservation['confirmationNumber']) {
                $res = $this->parseFromHeaderJson($response->upcoming_reservations[$i]);
                $result[] = $res;
            }
        }

        if ($this->ParsePastIts) {
            $result = array_merge($result, $this->parsePastItineraries());
        }

        return $result;
    }

    public function ParseItinerary($reservation)
    {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => 'L'];
        $startTimer = $this->getTime();

        $this->logger->info('Parse Itinerary #' . $reservation["confirmationNumber"], ['Header' => 3]);

        $headers = $this->getHeaders();
        $this->http->RetryCount = 0;
        $this->http->GetURL(sprintf("https://prd-west.webapi.nationalcar.com/gma-national/reservations/retrieve?confirmationNumber={$reservation["confirmationNumber"]}&firstName=%s&lastName=%s", urlencode($reservation["firstName"]), urlencode($reservation["lastName"])), $headers);
        $response = $this->http->JsonLog(null, 0);

        if (!$response) {
            $this->logger->notice('Retrying: invalid json');
            sleep(5);
            $this->http->GetURL(sprintf("https://prd-west.webapi.nationalcar.com/gma-national/reservations/retrieve?confirmationNumber={$reservation["confirmationNumber"]}&firstName=%s&lastName=%s", urlencode($reservation["firstName"]), urlencode($reservation["lastName"])), $headers);
            $response = $this->http->JsonLog(null, 0);
        }
        $this->http->RetryCount = 2;

        if ($this->http->FindPreg('/"tech_message":"Could not write JSON:/')) {
            $this->logger->info('The site does not return valid json itinerary');

            return [];
        }

        if ($this->http->FindPreg('/"code":"EHI_SVC_TECHNICAL_EXCEPTION/')) {
            $this->logger->info('Technical error');

            return [];
        }

        if (isset($response->messages[0]->message)) {
            return $response->messages[0]->message;
        }
        // ConfirmationNumber
        if (!isset($response->session->analytics->gbo->reservation)) {
            return [];
        }
        $reservation = $response->session->gbo->reservation ?? $response->session->analytics->gbo->reservation;

        $result['Number'] = $reservation->confirmation_number ?? null;
        // PickupLocation
        $result["PickupLocation"] = preg_replace('/>/u', ' ', $reservation->pickup_location->name);

        if (isset($reservation->pickup_location->address->street_addresses, $reservation->pickup_location->address->city)) {
            $result["PickupLocation"] .= '; ' . preg_replace('/>/u', ' ', implode(", ",
                    $reservation->pickup_location->address->street_addresses) . ', ' . $reservation->pickup_location->address->city);
        }
        // PickupDatetime
        $result["PickupDatetime"] = strtotime($reservation->pickup_time);
        // PickupPhone
        if (isset($reservation->pickup_location->phones)) {
            foreach ($reservation->pickup_location->phones as $phone) {
                if ($phone->default_indicator == true && isset($phone->formatted_phone_number)) {
                    $result['PickupPhone'] = $phone->formatted_phone_number;
                }
            }
        }
        unset($phone);
        // DropoffLocation
        $result["DropoffLocation"] = preg_replace('/>/u', ' ', $reservation->return_location->name);

        if (isset($reservation->return_location->address->street_addresses, $reservation->return_location->address->city)) {
            $result["DropoffLocation"] .= '; ' . preg_replace('/>/u', ' ', implode(", ",
                    $reservation->return_location->address->street_addresses) . ', ' . $reservation->return_location->address->city);
        }
        // DropoffDatetime
        $result["DropoffDatetime"] = strtotime($reservation->return_time);
        // DropoffPhone
        if (isset($reservation->return_location->phones)) {
            foreach ($reservation->return_location->phones as $phone) {
                if ($phone->default_indicator == true && isset($phone->formatted_phone_number)) {
                    $result['DropoffPhone'] = $phone->formatted_phone_number;
                }
            }
        }

        // Currency
        $result['Currency'] = $reservation->car_class_details->vehicle_rates[0]->price_summary->estimated_total_view->code ?? null;
        // TotalCharge
        $result['TotalCharge'] = $reservation->car_class_details->vehicle_rates[0]->price_summary->estimated_total_view->amount ?? null;
        // Tax and Fees
        $fees = [];
        $taxes = [];

        if (isset($reservation->car_class_details->vehicle_rates[0]->price_summary->payment_line_items->FEE)) {
            foreach ($reservation->car_class_details->vehicle_rates[0]->price_summary->payment_line_items->FEE as $fee) {
                $name = $fee->description;

                if ($this->http->FindPreg('/Subtotal/', false, $name)) {
                    continue;
                } elseif ($this->http->FindPreg('/Tax/', false, $name)) {
                    $taxes[] = $fee->total_amount_view->amount;
                } else {
                    $charge = $fee->total_amount_view->amount;

                    if (isset($charge)) {
                        $fees[] = ['Name' => $name, 'Charge' => $charge];
                    }
                }
            }

            if (isset($fees[0])) {
                $result['Fees'] = $fees;
            }

            if (isset($taxes[0])) {
                $result['TotalTaxAmount'] = array_sum($taxes);
            }
        }
        // Discount
//        $result['Discount'] = $reservation->rateChargeTaxesFees->totalSavings;
        // AccountNumber
        $result['AccountNumbers'] = $reservation->profile_details->loyalty_data->loyalty_number ?? null;
        // car Type and Model
        $result['CarType'] = $reservation->car_class_details->name ?? null;
        $result['CarModel'] = $reservation->car_class_details->make_model_or_similar_text ?? null;
        // CarImageURL
        $result['CarImageUrl'] = isset($reservation->car_class_details->images->SideProfile->path) ? str_replace(['{width}', '{quality}'], ['240', 'high'], $reservation->car_class_details->images->SideProfile->path) : null;
        $result['RenterName'] = beautifulName($reservation->driver_info->first_name . " " . $reservation->driver_info->last_name);

        $this->getTime($startTimer);
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.nationalcar.com/en_US/car-rental/reservation/reservationSearch.html";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $reservation = [
            "confirmationNumber" => $arFields["ConfNo"],
            "firstName"          => $arFields["FirstName"],
            "lastName"           => $arFields["LastName"],
        ];
        $result = $this->ParseItinerary($reservation);
        // error
        if (!is_array($result)) {
            return $result;
        }

        $it = [$result];

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "FirstName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function GetHistoryColumns()
    {
        return [
            "Pickup Date"     => "PostingDate",
            "Pickup Location" => "Description",
            "Invoice #"       => "Info",
            "Vehicle"         => "Info",
            "Credits"         => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->FilterHTML = false;
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        if (!isset($this->Properties["Number"])) {
            $this->logger->error("membership_number not found");

            return [];
        }
        $this->http->PostURL("https://prd-west.webapi.nationalcar.com/gma-national/trips/past", json_encode(["membership_number" => $this->Properties["Number"]]), $this->getHeaders());
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog(null, 0);

        if (!isset($response->trip_summaries)) {
            return $result;
        }
        $pastIts = $response->trip_summaries;
        $this->logger->debug("Total " . count($pastIts) . " rentals were found");

        foreach ($pastIts as $res) {
            $dateStr = preg_replace("/^[a-z]{3}\,\s*/ims", '', $res->pickup_time);
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[$startIndex]['Pickup Date'] = $postDate;
            $result[$startIndex]['Pickup Location'] = $res->pickup_location->name;
            $result[$startIndex]['Invoice #'] = $res->invoice_number;
            $result[$startIndex]['Vehicle'] = isset($res->vehicle_details->make) ? $res->vehicle_details->make . " " . $res->vehicle_details->model : $res->vehicle_details->model;
            $result[$startIndex]['Credits'] = $res->credits_earned;
            $startIndex++;
        }

        return $result;
    }

    private function getHeaders($tabID = '_1')
    {
        return $headers = [
            "Accept"                      => "application/json, text/plain, */*",
            "BRAND"                       => "NATIONAL",
            "CHANNEL"                     => "WEB",
            "Content-Type"                => "application/json",
            "TAB_ID"                      => date("UB") . $tabID,
            "locale"                      => "en_US",
            "domain_country_of_residence" => "US",
            "Origin"                      => "https://www.nationalcar.com",
        ];
    }

    private function parsePastItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Past Itineraries", ['Header' => 2]);
        $startTimer = $this->getTime();
        $result = [];

        if (!isset($this->Properties["Number"])) {
            $this->logger->error("membership_number not found");

            return [];
        }
        $this->http->PostURL("https://prd-west.webapi.nationalcar.com/gma-national/trips/past", json_encode(["membership_number" => $this->Properties["Number"]]), $this->getHeaders());
        $response = $this->http->JsonLog(null, 0);

        if (!isset($response->trip_summaries)) {
            if ($this->http->FindPreg("/^\{\}$/ims")) {
                $this->logger->notice(">>> No trips to display");
            }

            return [];
        }// if (!isset($response->invoiceList))
        $pastIts = $response->trip_summaries;
        $this->logger->debug("Total " . count($pastIts) . " past reservations found");

        foreach ($pastIts as $pastIt) {
            $rental = ['Kind' => 'L'];

            if (is_null($pastIt->pickup_time) || is_null($pastIt->return_time)) {
                $this->logger->info('null pick up or drop off datetime, skipped');

                continue;
            }
            // ConfirmationNumber
            $rental['Number'] = $pastIt->rental_agreement_number;
            // PickupLocation
            $rental["PickupLocation"] = $pastIt->pickup_location->name;
            // PickupDatetime
            $rental["PickupDatetime"] = strtotime($pastIt->pickup_time);
            // DropoffLocation
            $rental["DropoffLocation"] = $pastIt->return_location->name;
            // DropoffDatetime
            $rental["DropoffDatetime"] = strtotime($pastIt->return_time);
            // EarnedAwards
            $rental["EarnedAwards"] = ($pastIt->credits_earned == 1) ? "{$pastIt->credits_earned} credit" : "{$pastIt->credits_earned} credits";
            // Currency
//            $rental['Currency'] = $pastIt->invoiceAmount;
            // TotalCharge
//            $rental['TotalCharge'] = $pastIt->invoiceAmount;
            // AccountNumber
            $rental['AccountNumbers'] = $pastIt->membership_number;
            // Car Model
            $rental['CarModel'] = Html::cleanXMLValue(($pastIt->vehicle_details->make ?? '') . " " . $pastIt->vehicle_details->model);
            $rental['RenterName'] = beautifulName($pastIt->customer_first_name . " " . $pastIt->customer_last_name);

            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($rental, true), ['pre' => true]);

            $result[] = $rental;
        }// for ($i = 0; $i < $pastIts->length; $i++)
        $this->getTime($startTimer);

        return $result;
    }

    private function parseFromHeaderJson($reservation)
    {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => 'L'];
        $startTimer = $this->getTime();

        $this->logger->info('Parse Itinerary #' . $reservation->confirmation_number . ' from headerJson', ['Header' => 3]);

        $headers = $this->getHeaders();
        $this->http->RetryCount = 0;

        $result['Number'] = $reservation->confirmation_number ?? null;
        // PickupLocation
        $result["PickupLocation"] = $reservation->pickup_location->name;

        if (isset($reservation->pickup_location->address->street_addresses, $reservation->pickup_location->address->city)) {
            $result["PickupLocation"] .= '; ' . implode(", ",
                    $reservation->pickup_location->address->street_addresses) . ', ' . $reservation->pickup_location->address->city;
        }
        // PickupDatetime
        $result["PickupDatetime"] = strtotime($reservation->pickup_time);
        // PickupPhone
        if (isset($reservation->pickup_location->phones)) {
            foreach ($reservation->pickup_location->phones as $phone) {
                if ($phone->default_indicator == true && isset($phone->formatted_phone_number)) {
                    $result['PickupPhone'] = $phone->formatted_phone_number;
                }
            }
        }
        unset($phone);
        // DropoffLocation
        $result["DropoffLocation"] = $reservation->return_location->name;

        if (isset($reservation->return_location->address->street_addresses, $reservation->return_location->address->city)) {
            $result["PickupLocation"] .= '; ' . implode(", ",
                    $reservation->return_location->address->street_addresses) . ', ' . $reservation->return_location->address->city;
        }
        // DropoffDatetime
        if (isset($reservation->return_time)) {
            $result["DropoffDatetime"] = strtotime($reservation->return_time);
        }

        if (isset($result["PickupDatetime"]) && !isset($result["DropoffDatetime"])) {
            $result["DropoffDatetime"] = MISSING_DATE;
        }
        // DropoffPhone
        if (isset($reservation->return_location->phones)) {
            foreach ($reservation->return_location->phones as $phone) {
                if ($phone->default_indicator == true && isset($phone->formatted_phone_number)) {
                    $result['DropoffPhone'] = $phone->formatted_phone_number;
                }
            }
        }

        // car Type and Model
        $result['CarType'] = $reservation->vehicle_details->name ?? null;
        $result['CarModel'] = $reservation->vehicle_details->make_model_or_similar_text ?? null;
        // CarImageURL
        $result['CarImageUrl'] = isset($reservation->vehicle_details->images[0]->path) ? str_replace(['{width}', '{quality}'], ['240', 'high'], $reservation->vehicle_details->images[0]->path) : null;
        $result['RenterName'] = beautifulName($reservation->customer_first_name . " " . $reservation->customer_last_name);

        $this->getTime($startTimer);
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    /*
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
//            $selenium->http->setRandomUserAgent();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->disableImages();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www.nationalcar.com/en/home.html");

            $loginInput = $selenium->waitForElement(WebDriverBy::id('username'), 10);
            $this->saveToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
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

    private function saveToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
    */
}
