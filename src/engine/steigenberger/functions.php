<?php

class TAccountCheckerSteigenberger extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://hrewards.com/en/account/login');

        if (!$this->http->Response['code'] == 200) {
            return $this->checkErrors();
        }

        $data = [
            'email'     => $this->AccountFields['Login'],
            'password'  => $this->AccountFields['Pass'],
        ];
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://hrewards.com/bff/auth/login', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, 0);

        if (isset($response->access_token)) {
            $this->State['Authorization'] = "Bearer {$response->access_token}";

            return $this->loginSuccessful();
        }

        if (isset($response->message) && $response->message == 'Unauthorized') {
            $this->DebugInfo = $response->message;

            throw new CheckException('E-mail or password are invalid, please try again', ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);

        // Balance - Reward Points
        $points = $response->rewards_points;
        $this->SetBalance($points ?? null);

        // Name
        if (isset($response->first_name, $response->last_name)) {
            $fullName = $response->first_name . " " . $response->last_name;
            $this->SetProperty('Name', beautifulName($fullName));
        }

        // Membership Number
        if (isset($response->loyaltyNumber)) {
            $this->SetProperty('MembershipNumber', $response->loyaltyNumber);
        }

        // Reward level
        if (isset($response->reward_level)) {
            $this->SetProperty('EliteLevel', $response->reward_level);
        }

        // Status Expiration
        if (isset($response->membership_level_valid_to_date)) {
            $this->SetProperty("StatusExpiration", strtotime($response->membership_level_valid_to_date));
        }

        $this->http->GetURL('https://hrewards.com/bff/members/points-status');
        $pointsStatusData = $this->http->JsonLog();

        // Status points to next status
        if (isset($pointsStatusData->dailypointStatus->missing_points_for_upgrade)) {
            $this->SetProperty('PointsToNextLevel', $pointsStatusData->dailypointStatus->missing_points_for_upgrade);
        }

        // Status nights to next status
        if (isset($pointsStatusData->dailypointStatus->missing_nights_for_upgrade)) {
            $this->SetProperty('NightsToNextLevel', $pointsStatusData->dailypointStatus->missing_nights_for_upgrade);
        }

        // Status points
        if (isset($pointsStatusData->dailypointStatus->missing_points_for_upgrade)) {
            $this->SetProperty('StatusPoints', $pointsStatusData->dailypointStatus->status_points);
        }

        // Status nights
        if (isset($pointsStatusData->dailypointStatus->missing_nights_for_upgrade)) {
            $this->SetProperty('StatusNights', $pointsStatusData->dailypointStatus->status_nights);
        }

        // Vouchers
        $this->logger->info('Vouchers', ['Header' => 3]);
        $this->http->GetURL('https://hrewards.com/bff/members/vouchers');
        $vouchersData = $this->http->JsonLog();

        if (is_iterable($vouchersData) && count($vouchersData) > 0 && isset($points)) {
            uasort($vouchersData, function ($a, $b) {
                if ($a->attributes->points == $b->attributes->points) {
                    return 0;
                }

                return ($a->attributes->points < $b->attributes->points) ? -1 : 1;
            });

            foreach ($vouchersData as $voucher) {
                if ($voucher->attributes->active !== true) {
                    $this->logger->debug('SKIP INCTIVE VOUCHER WITH ID ' . $voucher->id);

                    continue;
                }

                if ($points <= $voucher->attributes->points) {
                    $this->logger->debug('NEAREST REWARD ID: ' . $voucher->id);

                    // Points needed to next reward
                    $pointsToNextReward = $voucher->attributes->points - $points;
                    $this->SetProperty('PointsToNextReward', $pointsToNextReward);

                    break;
                }
            }
        }

        $this->logger->info('Exp date', ['Header' => 3]);

        // exp date parsing
        $this->http->GetURL('https://hrewards.com/bff/members/points-history-v2');

        $pointsHistoryData = $this->http->JsonLog();
        $pointsHistory = $pointsHistoryData->data;
        $pointsHistoryPrepared = [];

        if (isset($pointsHistory) && is_iterable($pointsHistory)) {
            foreach ($pointsHistory as $byYear) {
                $months = $byYear->months;

                if (!isset($months)) {
                    continue;
                }

                foreach ($months as $month) {
                    $history = $month->history;

                    if (!isset($history)) {
                        continue;
                    }

                    foreach ($history as $item) {
                        if ($item->rewards_points > 0) {
                            array_push($pointsHistoryPrepared, $item);
                        }
                    }
                }
            }

            uasort($pointsHistoryPrepared, function ($a, $b) {
                if (strtotime($a->from) == strtotime($b->from)) {
                    return 0;
                }

                return (strtotime($a->from) > strtotime($b->from)) ? -1 : 1;
            });

            $this->logger->debug('ALL PREPARED HISTORY:');
            $this->logger->debug(print_r($pointsHistoryPrepared, true));

            foreach ($pointsHistoryPrepared as $item) {
                $points = $points - $item->rewards_points;

                if ($points <= 0) {
                    // Earning Date
                    $this->SetProperty("EarningDate", strtotime($item->date));
                    // Expiration Date
                    $expDate = strtotime($item->expirationDate);
                    $this->logger->debug('EXP DATE: ' . $item->expirationDate . ' UNIXTIME: ' . $expDate);
                    $this->SetExpirationDate($expDate);
                    // Expiring Balance
                    $pointsEarned = $this->getPointsEarnedOnDate($pointsHistoryPrepared, $item->date);
                    $this->SetProperty("ExpiringBalance", $points + $pointsEarned);

                    break;
                }
            }
        }
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://hrewards.com/bff/members/bookings");

        $itineraries = $this->http->JsonLog();

        $sortedItineraries = $this->sortItineraries($itineraries);

        $upcomingItineraries = $sortedItineraries['upcoming'];
        $pastItineraries = $sortedItineraries['past'];
        $canceledItineraries = $sortedItineraries['canceled'];

        $upcomingItinerariesIsPresent = count($upcomingItineraries) != 0;
        $pastItinerariesIsPresent = count($pastItineraries) != 0;
        $canceledItinerariesIsPresent = count($canceledItineraries) != 0;

        $this->logger->debug('Upcoming itineraries is present: ' . (int) $upcomingItinerariesIsPresent);
        $this->logger->debug('Previous itineraries is present: ' . (int) $pastItinerariesIsPresent);
        $this->logger->debug('canceled itineraries is present: ' . (int) $canceledItinerariesIsPresent);

        // check for the no its
        $seemsNoIts = !$upcomingItinerariesIsPresent && !$pastItinerariesIsPresent;
        $this->logger->info('Seems no itineraries: ' . (int) $seemsNoIts);
        $this->logger->info('ParsePastIts: ' . (int) $this->ParsePastIts);

        if ($upcomingItinerariesIsPresent) {
            foreach ($upcomingItineraries as $node) {
                $this->parseItinerary($node);
            }
        }

        if ($pastItinerariesIsPresent && $this->ParsePastIts) {
            foreach ($pastItineraries as $node) {
                $this->parseItinerary($node);
            }
        }

        if ($seemsNoIts) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        if (!$this->itinerariesMaster->getNoItineraries() && $canceledItinerariesIsPresent) {
            foreach ($canceledItineraries as $node) {
                $this->parseItinerary($node);
            }
        }

        return [];
    }

    private function getPointsEarnedOnDate($pointsHistoryPrepared, $date)
    {
        $points = 0;

        foreach ($pointsHistoryPrepared as $item) {
            if ($item->date === $date) {
                $points += $item->rewards_points;
            }
        }

        return $points;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->State['Authorization'])) {
            return false;
        }
        $headers = ['Authorization' => $this->State['Authorization']];
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://hrewards.com/bff/members/profile', $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        $email = $response->email->email_address ?? null;
        $username = $response->username ?? null;
        $this->logger->debug("[Email]: {$email}");
        $this->logger->debug("[Username]: {$username}");

        if (
            strtolower($email) == strtolower($this->AccountFields['Login'])
            || strtolower($username) == strtolower($this->AccountFields['Login'])
            || strtolower($username) == 'jens.neuhof@t-online.de'
        ) {
            $this->http->setDefaultHeader("Authorization", $this->State['Authorization']);

            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function sortItineraries($itineraries)
    {
        $upcoming = [];
        $past = [];
        $canceled = [];

        foreach ($itineraries as $itinerary) {
            switch ($itinerary->category) {
                case 'upcoming':
                    array_push($upcoming, $itinerary);

                    break;

                case 'past':
                    array_push($past, $itinerary);

                    break;

                case 'canceled':
                    array_push($canceled, $itinerary);

                    break;
            }
        }

        return [
            'upcoming'  => $upcoming,
            'past'      => $past,
            'canceled'  => $canceled,
        ];
    }

    private function parseItinerary($itinerary)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info(join($itinerary->reservation_numbers, ";"), ['Header' => 3]);

        $h = $this->itinerariesMaster->createHotel();

        foreach ($itinerary->reservation_numbers as $number) {
            $h->general()->confirmation($number, "reservation number");
        }

        $this->http->GetURL('https://hrewards.com/bff/ecommerce/order/' . $itinerary->order_id);
        $itineraryDataFull = $this->http->JsonLog();

        $this->http->GetURL('https://hrewards.com/bff/content/hotel?locale=en&hotelId=' . $itinerary->hotel_id);
        $hotelDataFull = $this->http->JsonLog();

        $h->general()->status($itineraryDataFull->payment_status);

        $h->hotel()->name($hotelDataFull->hotelName);
        $h->hotel()->chain($hotelDataFull->managementCompanyName);
        $h->hotel()->address($hotelDataFull->hotelAddress);

        $h->booked()->checkIn2($itinerary->check_in_date);
        $h->booked()->checkOut2($itinerary->check_out_date);

        $h->price()->total($itineraryDataFull->price_total);
        $h->price()->currency($itineraryDataFull->currency);

        if ($itinerary->category == 'canceled') {
            $h->general()->cancelled();
        }

        $firstName = $itineraryDataFull->customer->first_name ?? null;
        $lastName = $itineraryDataFull->customer->last_name ?? null;

        if ($firstName && $lastName) {
            $h->general()->traveller(beautifulName("{$firstName} {$lastName}"), true);
        }

        $personCount = 0;
        $adultsCount = 0;

        $cancellationDataAll = [];

        if (isset($itineraryDataFull->stays) && count($itineraryDataFull->stays) > 1) {
            $this->sendNotification("refs #19607 - stays count > 1 // IZ");
        }

        foreach ($itineraryDataFull->stays as $stay) {
            foreach ($stay->room_stays as $roomStay) {
                $r = $h->addRoom();
                $r->setType($roomStay->room_type_code);

                if (isset($roomStay->price_per_night)) {
                    foreach ($roomStay->price_per_night as $price) {
                        $r->addRate($price->amnt_with_tax . '$/night');
                    }
                }

                $r->setRateType($roomStay->rate_code);
                $r->setConfirmation($roomStay->reservation_number);
                $r->setConfirmationDescription('reservation number');

                $personCount += $roomStay->person_count;
                $adultsCount += $roomStay->adults_count;

                // $travellerFirstName = $roomStay->traveller->first_name;
                // $travellerLastName = $roomStay->traveller->last_name;

                $cancellationData = [
                    'cancellable'                     => $roomStay->cancellable ?? null,
                    'cancellation_fee'                => $roomStay->cancellation_fee ?? null,
                    'cancellation_policy_description' => $roomStay->reservation_info->cancellation_policy_description ?? null,
                ];

                array_push($cancellationDataAll, $cancellationData);

                if (isset($roomStay->cancellation_fee) && $roomStay->cancellation_fee > 0) {
                    $this->sendNotification("refs #19607 - cancellation_fee detected // IZ");
                }

                // $h->general()->traveller(beautifulName("{$travellerFirstName} {$travellerLastName}"), true);
            }
        }

        $nonRefundable = false;

        foreach ($cancellationDataAll as $cancellationData) {
            if ($cancellationData['cancellable'] !== true || $cancellationData['cancellation_fee'] !== 0) {
                $nonRefundable = true;
            } else {
                $this->logger->debug('CANCELLATION: ' . $cancellationData['cancellation_policy_description']); // TODO
            }
        }

        if ($nonRefundable == true) {
            $h->setNonRefundable(true);
        } else {
            // $this->sendNotification("refs #19607 - found refundable itinerary // IZ");
            $this->parseCancellationPolicy($cancellationDataAll, $h);
        }

        $h->booked()->guests($personCount);
        $h->booked()->kids($personCount - $adultsCount);
    }

    private function parseCancellationPolicy($cancellationDataAll, $h)
    {
        if (!isset($cancellationDataAll) || count($cancellationDataAll) == 0) {
            return;
        }

        $cancellationPolicy = $cancellationDataAll[0]['cancellation_policy_description'];
        $equals = true;

        foreach ($cancellationDataAll as $cancellationData) {
            if ($cancellationPolicy !== $cancellationData['cancellation_policy_description']) {
                $equals = false;
            }
        }

        if ($equals === true) {
            if (
                strstr($cancellationData['cancellation_policy_description'], 'Free cancellation until 6:00pm local time on day of arrival. Late cancellation and non-arrival will be charged with cost of 1 night')
                || strstr($cancellationData['cancellation_policy_description'], 'Kostenfrei stornierbar bis 18:00 Uhr lokale Zeit am Tag der Anreise. Spätere Stornierung und Nicht-Anreise wird mit den Kosten einer Nacht in Rechnung gestellt')
            ) {
                $h->parseDeadlineRelative("0 days", "18:00");

                return;
            }

            if (
                strstr($cancellationData['cancellation_policy_description'], 'Kostenfrei stornierbar bis 24 Stunden vor dem Anreisetag. Spätere Stornierung und Nicht-Anreise wird mit 90% des Gesamtaufenthaltes in Rechnung gestellt')
                || strstr($cancellationData['cancellation_policy_description'], 'Free cancellation until 24 hours prior to arrival. Late cancellation and non-arrival will be charged 90% of total stay')
            ) {
                $h->parseDeadlineRelative("1 days", "00:00");

                return;
            }

            $this->sendNotification("refs #19607 - need to check deadline // IZ");
        }
    }
}
