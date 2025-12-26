<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerKyte extends TAccountChecker
{
    use ProxyList;

    private $recognizer;
    private $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->LogHeaders = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip');
    }

    /*public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://api.kyte.com/api/v2/users/me/trips', [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindPreg('/\{"data":\{"active":/')) {
            return true;
        }

        return false;
    }*/

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://api.kyte.com/api/v1/users/status?email=" . $this->AccountFields['Login']);
        $response = $this->http->JsonLog();

        if ($response->data->registered === false) {
            throw new CheckException('Invalid username or password', ACCOUNT_INVALID_PASSWORD);
        }

        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
            'Origin'       => 'https://kyte.com',
            'X-Anon-Id'    => '0909b6d1-558c-47fe-887c-adae8f3284ac',
            'X-Client-Id'  => 'consumer-web',
        ];
        $data = [
            'email'    => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ];
        $this->http->PostURL("https://api.kyte.com/api/v1/users/sign_in", json_encode($data), $headers);

        return true;
    }

    public function checkErrors()
    {
        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if ($response->status == 'success') {
            return true;
        }

        if ($response->status == 'Invalid username or password') {
            throw new CheckException($response->status, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $this->SetProperty('Name', beautifulName($response->data->name));

        // refs#22466
        if (!empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $this->http->GetURL('https://api.kyte.com/api/v2/users/me/trips');

        if (!$this->ParsePastIts && $this->http->FindPreg('/"upcoming":\[\]/')) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        } elseif ($this->ParsePastIts && $this->http->FindPreg('/\{"data":\{"active":\[\],"past":\[\],"upcoming":\[\]\}\}/')) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        if (!$this->http->FindPreg('/"upcoming":\[\]/')) {
            $this->sendNotification('upcoming // MI');
        }

        $response = $this->http->JsonLog();

        if ($this->ParsePastIts) {
            foreach ($response->data->past as $data) {
                $this->parseItinerary($data);
            }
        }

        return [];
    }

    private function parseItinerary($data)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->createRental();
        $this->logger->info(sprintf('[%s] Parse Car #%s', $this->currentItin++, ""), ['Header' => 3]);
        $r->general()->noConfirmation();

        $dt = new DateTime('now', new DateTimeZone($data->delivery_leg->service_area->timezone));
        $dt->setTimestamp($data->delivery_leg->date / 1000);
        $r->pickup()->date2($dt->format("Y-m-d H:i:s"));
        $r->pickup()->location($data->delivery_leg->address);

        $dt = new DateTime('now', new DateTimeZone($data->return_leg->service_area->timezone));
        $dt->setTimestamp($data->return_leg->date / 1000);
        $r->dropoff()->date2($dt->format("Y-m-d H:i:s"));
        $r->dropoff()->location($data->return_leg->address);

        $r->car()->type($data->quoted_vehicle->vehicle_class);
        $r->car()->model($data->quoted_vehicle->make . ' ' . $data->quoted_vehicle->model . ' or similar');
        $r->car()->image($data->quoted_vehicle->img_src);

        $this->http->GetURL("https://api.kyte.com/api/v2/users/me/trips/{$data->uuid}");
        $response = $this->http->JsonLog();
        $r->price()->currency($response->data->tips->currency);
        $r->price()->total($response->data->tips->trip_price);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }
}
