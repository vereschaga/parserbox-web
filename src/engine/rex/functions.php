<?php

class TAccountCheckerRex extends TAccountChecker
{
    // use AwardWallet\Engine\ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.rex.com.au/';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->RetryCount = 0;
        // $this->http->SetProxy($this->proxyPurchase());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if (
             !$this->http->FindSingleNode('//title[contains(text(), "Rex Airlines")]')
             || $this->http->Response['code'] != 200
         ) {
            return $this->checkErrors();
        }

        return true;
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->Response['headers']['in-auth-response'] ?? null) {
            $this->logger->error("[Error]: {$message}");

            // There is different text on the website and in the headlines
            if (strstr($message, 'Incorrect response') || strstr($message, 'Username ' . $this->AccountFields['Login'] . ' not found')) {
                throw new CheckException("Invalid Username or Password", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function Parse()
    {
        /*
            Points info:
                id 6352 - main balance(rex flyer points)
                id 6353 - status points(need to up tier)
                id 6354 - status flights(need to up tier)
            With this data it will be easier for you to understand the API outputs
        */

        $this->GetSecureUrl('https://rexflyer.com.au/inspirenetz-api/api/0.9/json/customer/customerprofile/12');
        $log = $this->http->JsonLog(null, 3, true);

        // Name
        $firstName = $log['data']['cusFName'] ?? null;
        $lastName = $log['data']['cusLName'] ?? null;
        $this->SetProperty('Name', beautifulName("{$firstName} {$lastName}"));

        // Elite status
        $this->SetProperty('Tier', $log['data']['tieName'] ?? null);

        // Member since
        $this->SetProperty('MemberSince', strtotime($log['data']['cusRegisterTimestamp'] ?? null) ?? null);

        // Number
        $this->SetProperty('Number', $log['data']['cusLoyaltyId'] ?? null);

        $this->GetSecureUrl('https://rexflyer.com.au/inspirenetz-api/api/0.9/json/portal/rewardbalance/12/6352');
        $log = $this->http->JsonLog(null, 3, true);

        if (isset($log['data']) && count($log['data']) == 0) {
            // Balance - Rex Flyer Points
            // AccountID 7567383. https://redmine.awardwallet.com/issues/23163#note-56
            $this->SetBalance(0);
        // $this->sendNotification("refs #23163 need to check balance // IZ");
        } else {
            // Balance - Rex Flyer Points
            $this->SetBalance($log['data'][0]['crbRewardBalance'] ?? null);
        }

        $this->GetSecureUrl('https://rexflyer.com.au/inspirenetz-api/api/0.9/json/customer/tier/status/info');
        $log = $this->http->JsonLog(null, 3, true);
        $data = $log['data']['upgrade'] ?? null;

        foreach ($data['requirements'] ?? null as $requirementsItem) {
            if ($requirementsItem['unit'] == "6353") {
                // Status points
                $this->SetProperty('StatusPoints', $requirementsItem['currentValue'] ?? null);

                // Status points till next elite level
                $this->SetProperty('StatusPointsTillNextLevel', $requirementsItem['requiredValue'] ?? null);
            }

            if ($requirementsItem['unit'] == "6354") {
                // Status flights
                $this->SetProperty('StatusFlights', $requirementsItem['currentValue'] ?? null);

                // Status points till next elite level
                $this->SetProperty('StatusFlightsTillNextLevel', $requirementsItem['requiredValue'] ?? null);
            }
        }

        if (
            $this->GetSecureUrl('https://rexflyer.com.au/inspirenetz-api/api/0.9/json/customer/transactions/12/1980-01-01/9999-12-31')
        ) {
            $log = $this->http->JsonLog(null, 3, true);
            $data = $log['data'] ?? null;

            if (
                is_numeric($log['totalpages'] ?? null)
                && (int) $log['totalpages'] > 1
            ) {
                $this->sendNotification("refs #23163 The maximum number of elements on the page was found. Pagination needs to be redone // IZ");
            }
        }

        if (
            $this->GetSecureUrl('https://rexflyer.com.au/inspirenetz-api/api/0.9/json/user/point/expiry/list?merchantNo=12&months=6')
        ) {
            $log = $this->http->JsonLog(null, 3, true);
            $data = $log['data'] ?? null;

            if ($data) {
                foreach ($data as $key => $value) {
                    // Expiring Balance
                    $this->SetProperty("ExpiringBalance", $value);
                    // Expiration date
                    $this->SetExpirationDate(strtotime($key));
                }
            }
        }

        // itineraries
        if (
            $this->PostSecureUrl('https://rexflyer.com.au/inspirenetz-api/api/0.9/json/pnr/search/ffn/segments', "{}")
        ) {
            $log = $this->http->JsonLog(null, 3, true);
            $data = $log['data'] ?? null;

            if (!is_iterable($data) || count($data) == 0) {
                return;
            }

            foreach ($data as $itinerary) {
                $date = $itinerary['CreatedDate'] ?? null;

                if ($date != null && strtotime($date) > time()) {
                    $this->sendNotification("refs #23163 Found upcoming flights with correct date // IZ");

                    break;
                }

                $segments = $itinerary['PaxSegments'] ?? [];

                foreach ($segments as $segment) {
                    $date = $segment['ServiceStartDate'] ?? null;

                    if ($date != null && strtotime($date) > time()) {
                        $this->sendNotification("refs #23163 Found upcoming flights with correct date // IZ");

                        break;
                    }
                }
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->GetSecureUrl('https://rexflyer.com.au/inspirenetz-api/api/0.9/json/user/authenticate')) {
            return $this->checkErrors();
        }

        $log = $this->http->JsonLog(null, 3, true);

        if ($log['status'] == 'success') {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function GetSecureUrl($url)
    {
        $this->http->GetURL($url);
        $data = $this->http->FindPregAll('/\"([^\"]+)\"/', $this->http->Response['headers']['www-authenticate']);
        $hash = $this->getHash($this->AccountFields['Login'], $data[0], $this->AccountFields['Pass'], 'GET', $url, $data[2]);

        return $this->http->GetURL($url, [
            "Authorization" => 'Digest realm="' . $data[0] . '", username="' . $this->AccountFields['Login'] . '", uri="' . $url . '", nonce="' . $data[2] . '", response="' . $hash . '"',
            "Content-Type"  => "application/json",
        ]);
    }

    private function PostSecureUrl($url, $params)
    {
        $this->http->PostUrl($url, $params);
        $data = $this->http->FindPregAll('/\"([^\"]+)\"/', $this->http->Response['headers']['www-authenticate']);
        $hash = $this->getHash($this->AccountFields['Login'], $data[0], $this->AccountFields['Pass'], 'POST', $url, $data[2]);

        return $this->http->PostURL($url, $params, [
            "Authorization" => 'Digest realm="' . $data[0] . '", username="' . $this->AccountFields['Login'] . '", uri="' . $url . '", nonce="' . $data[2] . '", response="' . $hash . '"',
            "Content-Type"  => "application/json",
        ]);
    }

    private function getHash($login, $realm, $password, $method, $url, $nonce)
    {
        $a1 = $login . ':' . $realm . ':' . $password;
        $a2 = $method . ':' . $url;

        return md5(md5($a1) . ':' . $nonce . ':' . md5($a2));
    }

    /*
    public function ParseItineraries()
    {
        // this provider only has future reservations

        if (
            $this->PostSecureUrl('https://rexflyer.com.au/inspirenetz-api/api/0.9/json/pnr/search/ffn/segments', "{}")
        ) {
            $log = $this->http->JsonLog(null, 3, true);
            $data = $log['data'] ?? null;

            if (!is_iterable($data) || count($data) == 0) {
                return;
            }

            $this->sendNotification("refs #23163 Found upcoming flights // IZ");

            foreach($data as $dataItem) {
                $this->parseItinerary($dataItem);
            }
        }

        return [];
    }

    private function parseItinerary($node) // There may be errors here. The code is left until notification is received
    {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->createFlight();

        $confNo = $node['PNR'];

        $this->logger->info("Parse Itinerary #{$confNo}", ['Header' => 3]);

        $f->general()->confirmation($confNo, 'Confirmation #');
        $f->general()->date2($node['CreatedDate']);
        $f->price()->total($node['TotalFare']);
        $f->price()->currency($node['Currency']);
        $f->issued()->name($node['AirlineIdentifier']);

        $segments = $node['PaxSegments'] ?? [];

        foreach($segments as $segment) {
            $s = $f->addSegment();

            $this->logger->info("Parse segment #" . $segment['Etkt'], ['Header' => 2]);

            $s->airline()->confirmation($segment['Etkt']);


            $f->general()->traveller($segment['FirstName'], false);

            $s->airline()->carrierNumber($segment['MarketingFlightNbr']);
            $s->airline()->carrierName($segment['MarketingAirlineCode']);
            $s->departure()->code($segment['ServiceStartCity']);
            $s->departure()->date2($segment['ServiceStartDate'] . ' ' . $segment['ServiceStartTime']);
            $s->arrival()->code($segment['ServiceEndCity']);
            $s->arrival()->noDate();
            $s->extra()->bookingCode($segment['ClassOfService']);
            $s->extra()->status($segment['CouponStatus']);
        }
    }
    */
}
