<?php

namespace AwardWallet\Engine\aa\RewardAvailability;

use AwardWallet\Engine\ProxyList;
use CheckException;

class Parser extends \TAccountChecker
{
    use ProxyList;

    public $isRewardAvailability = true;

    public static function getRASearchLinks(): array
    {
        return ['https://www.aa.com/homePage.do?locale=en_US' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->http->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36');
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br, zstd");

        $this->http->setHttp2(true);
        $this->setProxyGoProxies(null, 'uk');
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function ParseRewardAvailability(array $fields): array
    {
        if (!$this->isFieldsValid($fields)) {
            return ['routes' => []];
        }

        $jsonResponse = $this->GetJsonWithRoutes($fields);

        if (!$this->isResponseValid($jsonResponse)) {
            return ['routes' => []];
        }

        $flightsData = $jsonResponse;
        $routes = [];

        foreach ($flightsData['slices'] as $flightInfo) {
            $availableRoutesMask = $this->getAvailableRoutesMask($flightInfo);
            $this->deleteRoutesByMask($flightInfo, $availableRoutesMask);

            for ($routeNumber = 0; $routeNumber < array_sum($availableRoutesMask); $routeNumber++) {
                $route = [
                    'distance'       => $this->getDistance($flightInfo),
                    'num_stops'      => $flightInfo['stops'],
                    'tickets'        => $this->getTicketsCount($flightInfo, $routeNumber),
                    'award_type'     => null,
                    'classOfService' => $this->getClassOfService($flightInfo, $routeNumber),
                    'payments'       => $this->getPayments($flightInfo, $routeNumber),
                    'redemptions'    => $this->getRedemptions($flightInfo, $routeNumber, $flightsData['utag']['adult_passengers']),
                    'connections'    => $this->getConnections($flightInfo, $routeNumber),
                ];

                $routes[] = $route;
            }
        }

        return ['routes' => $routes];
    }

    public function isFieldsValid($fields): bool
    {
        if ($fields['Adults'] > 9) {
            $this->SetWarning("You can book up to 9 passengers in one reservation.");

            return false;
        }

        if ($fields['DepDate'] > strtotime('+331 day')) {
            $this->SetWarning('You can book a flight up to 331 days before departure.');

            return false;
        }

        $settings = $this->getRewardAvailabilitySettings();

        if (!in_array($fields['Currencies'][0], $settings['supportedCurrencies'])) {
            $fields['Currencies'][0] = $settings['defaultCurrency'];
            $this->logger->notice("Parse with defaultCurrency: " . $settings['defaultCurrency']);
        }

        return true;
    }

    public function isResponseValid($jsonResponse): bool
    {
        if (empty($jsonResponse)) {
            $this->sendNotification('Error getting routes // SK', 'aa', true, 'Response is empty');

            throw new CheckException('Empty response', ACCOUNT_PROVIDER_ERROR);
        }

        if (isset($jsonResponse['errorNumber'])) {
            $notificationBody = implode(' ', array_map(function ($key, $value) { return "$key = $value;\n"; }, $jsonResponse));

            $this->sendNotification('Error of getting routes // SK', 'aa', true, $notificationBody);

            throw new CheckException("Response with error:\n{$notificationBody}", ACCOUNT_PROVIDER_ERROR);
        }

        if (isset($jsonResponse['slices']) && empty($jsonResponse['slices'])) {
            $this->SetWarning('No flights found for your search');

            return false;
        }

        return true;
    }

    public function GetJsonWithRoutes(array $fields)
    {
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
            'Origin'       => 'https://www.aa.com',
            'Referer'      => 'https://www.aa.com/booking/choose-flights/1',
        ];

        $postData = [
            "metadata" => [
                "selectedProducts" => [],
                "tripType"         => "OneWay",
                "udo"              => (object) [],
            ],
            "passengers" => [
                [
                    "type"  => "adult",
                    "count" => $fields['Adults'],
                ],
            ],
            "requestHeader" => [
                "clientId" => "AAcom",
            ],
            "slices" => [
                (object) [
                    "allCarriers"               => true,
                    "cabin"                     => "",
                    "departureDate"             => date("Y-m-d", $fields['DepDate']),
                    "destination"               => $fields['ArrCode'],
                    "destinationNearbyAirports" => false,
                    "maxStops"                  => null,
                    "origin"                    => $fields['DepCode'],
                    "originNearbyAirports"      => false,
                ],
            ],
            "tripOptions" => (object) [
                "corporateBooking" => false,
                "fareType"         => "Lowest",
                "locale"           => $this->getLanguageByCurrency($fields['Currencies'][0]),
                "pointOfSale"      => null,
                "searchType"       => "Award",
            ],
            "loyaltyInfo" => null,
            "version"     => "",
            "queryParams" => (object) [
                "sliceIndex"  => 0,
                "sessionId"   => "",
                "solutionSet" => "",
                "solutionId"  => "",
            ],
        ];

        $this->http->PostURL('https://www.aa.com/booking/api/search/itinerary', json_encode($postData), $headers);

        return $this->http->JsonLog($this->http->Response['body'], 1, true);
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => ['USD', 'MXN', 'CAD', 'BRL', 'COP', 'CLP', 'GBP'],
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'USD',
        ];
    }

    public function getLanguageByCurrency($currency): string
    {
        $map = [
            'USD' => 'en-US',
            'MXN' => 'es-MX',
            'CAD' => 'en_CA',
            'BRL' => 'pt_BR',
            'COP' => 'es_CO',
            'CLP' => 'es_CL',
            'GBP' => 'en_GB',
        ];

        return $map[$currency] ?? 'en-US';
    }

    public function getAvailableRoutesMask($flightInfo): array
    {
        $mask = [];

        foreach ($flightInfo['pricingDetail'] as $pricingDetail) {
            $mask[] = $pricingDetail['productAvailable'];
        }

        return $mask;
    }

    public function deleteRoutesByMask(&$flightInfo, $mask)
    {
        for ($i = 0; $i < count($mask); $i++) {
            if ($mask[$i] === false) {
                unset($flightInfo['pricingDetail'][$i]);
            }

            // array reindexing
            $flightInfo['pricingDetail'] = array_values($flightInfo['pricingDetail']);
        }
    }

    public function getDistance(array $flightInfo): string
    {
        $distance = null;

        foreach ($flightInfo['segments'] as $segment) {
            $distance += $segment['legs'][0]['distanceInMiles'];
        }

        return $distance . ' miles';
    }

    public function getMeals(array $meals): string
    {
        $map = [
            'B'  => 'Breakfast',
            'BS' => 'Beverage service',
            'C'  => 'Complimentary liquor',
            'D'  => 'Dinner',
            'F'  => 'Snacks (for a fee)',
            'G'  => 'Snacks (for a fee)',
            'H'  => 'Hot meal',
            'K'  => 'Continental breakfast',
            'L'  => 'Lunch',
            'M'  => 'Meal',
            'NS' => 'Not served',
            'O'  => 'Cold meal',
            'P'  => 'Alcohol for Purchase',
            'R'  => 'Refreshments',
            'S'  => 'Snack or brunch',
            'V'  => 'Refreshments for purchase',
        ];

        foreach ($meals as &$meal) {
            if (isset($map[$meal])) {
                $meal = $map[$meal];
            } else {
                $this->sendNotification('Meal not found // SK', 'aa', true, 'Meal "' . $meal . '" not found');
                unset($meal);
            }
        }

        return implode(", ", $meals);
    }

    public function getTicketsCount(array $flightInfo, int $cabinNumber)
    {
        $ticketsCount = $flightInfo['pricingDetail'][$cabinNumber]['seatsRemaining'];

        if ($ticketsCount === 0) {
            $ticketsCount = null;
        }

        return $ticketsCount;
    }

    public function getClassOfService($flightInfo, int $cabinNumber)
    {
        $productType = $flightInfo['productDetails'][$cabinNumber]['productType'];

        $map = [
            'COACH'           => 'Main',
            'PREMIUM_ECONOMY' => 'Premium Economy',
            'BUSINESS'        => 'Business',
            'FIRST'           => 'First',
        ];

        if (!isset($map[$productType])) {
            $this->sendNotification('Class of service not found // SK', 'aa', true, 'productType "' . $productType . '" not found');
        }

        return $map[$productType] ?? null;
    }

    public function getCabin($cabinType)
    {
        $map = [
            'COACH'           => 'economy',
            'PREMIUM_ECONOMY' => 'premiumEconomy',
            'BUSINESS'        => 'business',
            'FIRST'           => 'firstClass',
        ];

        if (!isset($map[$cabinType])) {
            $this->sendNotification('Cabin not found // SK', 'aa', true, 'cabinType "' . $cabinType . '" not found');
        }

        return $map[$cabinType] ?? null;
    }

    public function getPayments(array $flightInfo, int $cabinNumber): array
    {
        return [
            'currency' => $flightInfo['pricingDetail'][$cabinNumber]['allPassengerTaxesAndFees']['currency'],
            'taxes'    => $flightInfo['pricingDetail'][$cabinNumber]['allPassengerTaxesAndFees']['amount'],
        ];
    }

    public function getRedemptions(array $flightInfo, int $cabinNumber, int $passengersCount): array
    {
        return [
            'miles'   => $flightInfo['pricingDetail'][$cabinNumber]['perPassengerAwardPoints'] * $passengersCount,
            'program' => $this->AccountFields['ProviderCode'],
        ];
    }

    public function getConnections(array $flightInfo, int $cabinNumber): array
    {
        $connections = [];

        foreach ($flightInfo['segments'] as $segment) {
            $productDetails = $segment['legs'][0]['productDetails'][$cabinNumber];

            $connection = [
                'num_stops' => 0,
                'departure' => [
                    'date'    => str_replace('T', ' ', $this->re("/^(\d{4}\-\d+\-\d+T\d+\:\d+)\:.*$/", $segment['departureDateTime'])),
                    'airport' => $segment['origin']['code'],
                ],

                'arrival' => [
                    'date'    => str_replace('T', ' ', $this->re("/^(\d{4}\-\d+\-\d+T\d+\:\d+)\:.*$/", $segment['arrivalDateTime'])),
                    'airport' => $segment['destination']['code'],
                ],

                'cabin'      => $this->getCabin($productDetails['cabinType']),
                'fare_class' => $productDetails['bookingCode'],
                'flight'     => $segment['flight']['carrierCode'] . $segment['flight']['flightNumber'],
                'airline'    => $segment['flight']['carrierCode'],
                'operator'   => $segment['flight']['carrierCode'],
                'aircraft'   => $segment['legs'][0]['aircraft']['code'],
                'tickets'    => null,
                'meal'       => $this->getMeals($productDetails['meals']),
            ];

            $connections[] = $connection;
        }

        return $connections;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
