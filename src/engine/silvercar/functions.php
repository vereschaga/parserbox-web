<?php

class TAccountCheckerSilvercar extends TAccountChecker
{
    private $headers = [
        'Accept'          => 'application/json',
        'Accept-Encoding' => 'gzip, deflate, br',
        'Api-Version'     => '2',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['id_token'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $success = $this->loginSuccessful($this->State['id_token']);
        $this->http->RetryCount = 2;

        if ($success) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://app.audiondemand.com/login');
        $hs = $this->http->FindPreg("/<script src=\"\/static\/js\/main\.(.+)\.chunk\.js\"><\/script>/");

        if (empty($hs)) {
            return false;
        }

        $data = [
            "email"    => $this->AccountFields["Login"],
            "password" => $this->AccountFields["Pass"],
        ];
        $headers = [
            'Accept'           => 'application/json',
            'Accept-Encoding'  => 'gzip, deflate, br',
            'Content-Type'     => 'application/json',
            "x-sc-app-name"    => "Driver-ACR",
            "x-sc-app-version" => "4.0.27-29525",
            "x-sc-app-source"  => "Official",
            "x-sc-platform"    => "Web Desktop",
            "Api-Version"      => "2",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.silvercar.com/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->authentication_token) && $this->loginSuccessful($response->authentication_token)) {
            return true;
        }

        $message = $response->message ?? null;

        if ($message) {
            $this->logger->debug("[Error]: {$message}");

            if ($message == "Invalid email or password") {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->Error == 'Network error 6 - Could not resolve host: api.silvercar.com') {
            throw new CheckException("Sorry. Something is amiss on our side.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $user = $this->http->JsonLog(null, 0);
        $firstName = $user->first_name ?? '';
        $lastName = $user->last_name ?? '';
        // Name
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));

        $this->http->GetURL('https://api.silvercar.com/user/points', $this->headers);
        $point = $this->http->JsonLog();
        // Points Balance
        $this->SetBalance($point->total ?? null);
    }

    public function ParseItineraries()
    {
        $this->http->GetURL('https://api.silvercar.com/rentals', $this->headers);
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg("/^\[\]$/")) {
            return $this->noItinerariesArr();
        }
        $oldItineraries = 0;

        foreach ($response as $it) {
            if (!$this->ParsePastIts && strtotime($it->dropoff_at) < strtotime('now')) {
                $oldItineraries++;
                $this->logger->debug("skip old Itinerary #" . $it->confirmation_token);

                continue;
            }
            $this->http->GetURL('https://api.silvercar.com/rentals/' . $it->id . '/receipt', $this->headers);
            $item = $this->http->JsonLog();

            $fleet = null;
            $location = null;
            $traveller = null;

            foreach ($it->links as $link) {
                if ($link->rel == "fleet") {
                    $this->http->GetURL($link->href, $this->headers);
                    $fleet = $this->http->JsonLog();
                }

                if ($link->rel == "location") {
                    $this->http->GetURL($link->href, $this->headers);
                    $location = $this->http->JsonLog();
                }

                if ($link->rel == "rental_agreement") {
                    $this->http->GetURL($link->href, $this->headers);
                    $traveller = $this->http->FindSingleNode('//text()[starts-with(normalize-space(),"Renter\'s Name:")]', null, true, "/Renter's Name:(.+)/");
                }
            }
            $this->parseRental($item, $fleet, $location, $traveller, $it->state);
        }

        if ($oldItineraries !== 0 && $oldItineraries === count($response)) {
            return $this->noItinerariesArr();
        }

        return [];
    }

    private function loginSuccessful($id_token)
    {
        $this->logger->notice(__METHOD__);

        $this->headers['Authorization'] = $id_token;
        $this->http->GetURL('https://api.silvercar.com/user', $this->headers, 20);
        $response = $this->http->JsonLog();

        if (isset($response->email)) {
            if (strtolower($response->email) === strtolower($this->AccountFields['Login'])) {
                return true;
            }
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function parseRental($rd, $fleet, $location, $traveller, $state)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Parse itinerary #' . $rd->trip_details->confirmation_number . " - status:" . $state, ['Header' => 3]);
        $r = $this->itinerariesMaster->add()->rental();

        $r->price()
            ->cost($rd->pricing->rental->total)
            ->total($rd->total)
            ->tax($rd->pricing->taxes->total)
            ->currency($rd->currency_code);

        if (isset($rd->pricing->fees_and_concessions->label, $rd->pricing->fees_and_concessions->total)) {
            $r->price()->fee($rd->pricing->fees_and_concessions->label, $rd->pricing->fees_and_concessions->total);
        }

        if (isset($rd->pricing->discount->total)) {
            $r->price()->discount(str_replace("-", "", $rd->pricing->discount->total));
        }
        $r->general()
            ->confirmation($rd->trip_details->confirmation_number, 'Confirmation number', true)
            ->status($state)
            ->traveller(beautifulName($traveller), true);
        $r->pickup()
            ->date2($rd->trip_details->pickup_at);
        $r->pickup()->detailed()
            ->address($location->address->line1)
            ->city($location->address->city)
            ->country($location->address->country)
            ->state($location->address->state)
            ->zip($location->address->zip);
        $pickupLocation = implode(', ', [$location->address->line1, $location->address->city, $location->address->country, $location->address->state, $location->address->zip]);
        $r->pickup()
            ->location($pickupLocation)
            ->phone($location->phone_number)
            ->openingHours($location->hours);
        $r->dropoff()
            ->same()
            ->date2($rd->trip_details->dropoff_at)
            ->phone($location->phone_number)
            ->openingHours($location->hours);

        if (isset($fleet->vehicle)) {
            $r->car()
                ->model($fleet->vehicle->make . ' ' . $fleet->vehicle->model);
        }

        $this->logger->info('Parsed rental:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);
    }
}
