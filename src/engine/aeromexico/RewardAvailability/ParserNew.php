<?php

namespace AwardWallet\Engine\aeromexico\RewardAvailability;

use AwardWallet\Engine\ProxyList;
use CheckRetryNeededException;

class ParserNew extends \TAccountChecker
{
    use \PriceTools;
    use ProxyList;
    use \SeleniumCheckerHelper;

    public $isRewardAvailability = true;

    private $headers = [
        'Accept'          => '*/*',
        'Authorization'   => 'Basic N21rbmdqdm83YTlwOHRwNTRxZjBlczdmYWg6dGQwdjc4YnR2OWplbXRkdGdhbzVoa2JnMzBvYWQ4aWw3bmg4Zjg4YWdsbzZkdm84cXJw',
        'Content-Type'    => 'application/x-www-form-urlencoded',
    ];

    public static function getRASearchLinks(): array
    {
        return ['https://aeromexico.com/es-mx' => 'search page'];
    }

    public static function GetAccountChecker($accountInfo)
    {
        return new static();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->setProxyGoProxies(null, 'mx');
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

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => ['MXN', 'USD'],
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'MXN',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));
        $this->http->RetryCount = 0;

        if (!in_array($fields['Currencies'][0], ['MXN', 'USD'])) {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if ($fields['Adults'] > 7) {
            $this->SetWarning("over max adults");

            return ['routes' => []];
        }

        if ($fields['DepDate'] > strtotime("+330 days")) {
            $this->SetWarning("too late flight");

            return ['routes' => []];
        }

        $accessToken = $this->getAccessToken();
        $grantToken = $this->getGrantAccessToken();

        if (!$this->checkRout($fields, $accessToken)) {
            return ['routes' => []];
        }

        $result = $this->getResul($fields, $grantToken);

        return ['routes' => $this->parseRewardFlights($result)];
    }

    private function getAccessToken(): string
    {
        $this->logger->notice(__METHOD__);

        $this->http->PostURL('https://www.aeromexico.com/api/oauth2/token?grant_type=client_credentials', null, $this->headers);
        $response = $this->http->JsonLog(null, 0);

        if (empty($response)) {
            throw new CheckRetryNeededException(5, 0);
        }

        return $response->access_token;
    }

    private function getGrantAccessToken(): string
    {
        $this->logger->notice(__METHOD__);

        $headers = $this->headers;
        $headers['Authorization'] = 'Basic Nmc2OVJKaXpPR1F2ckdaOXVBYURORFNiN3lKSGtSa0U6NzFwZlZWQ3ZQZ3ZqNWVoNA==';
        $headers['Access_type'] = 'client_credentials';

        $this->http->GetURL('https://www.aeromexico.com/api/v1/am-grant/grantAccess', $headers);
        $response = $this->http->JsonLog(null, 0);

        if (empty($response)) {
            throw new CheckRetryNeededException(5, 0);
        }

        return $response->grantAccess;
    }

    private function getResul(array $fields, string $grantToken): array
    {
        $dateStr = date("Y-m-d", $fields['DepDate']);

        $body = http_build_query([
            'city-pair-dates'  => "{$fields['DepCode']}_{$fields['ArrCode']}_{$dateStr}",
            'travelers'        => "A{$fields['Adults']}_C0_I0_PH0_PC0",
            'store'            => 'mx',
            'coupon'           => '',
            'discount'         => '',
            'cartId'           => '',
            'pos'              => 'WEB',
            'isSDC'            => 'false',
            'leg'              => 1,
            'client'           => 'c0a15a816728db125230db85c8c76af45873d7dfcd4f7d88381d7af68ef3dc24b7a1ab7e57955da5ad9ad0da6a740dc2Abef49HoARlSWnwMp4RsBw==',
            'redirectionRoute' => '',
            'isPremierPoints'  => 'true',
        ]);

        $headers = $this->headers;
        $headers['Authorization'] = 'Bearer ' . $grantToken;
        $headers['Access_type'] = 'client_credentials';

        $this->http->GetURL("https://www.aeromexico.com/api/v2/fares/detailed/ow?{$body}", $headers);
        $response = $this->http->JsonLog(null, 1);

        if ($this->http->Response['code'] == 400) {
            return [];
        }

        if (empty($response->_collection[0]->_collection)) {
            return [];
        }

        return $response->_collection[0]->_collection;
    }

    private function checkRout($fields, $token)
    {
        $headers = $this->headers;
        $headers['Authorization'] = "Bearer {$token}";
        $headers['Accept-Encoding'] = "gzip, deflate, br, zstd";
//        $headers['Accept'] = "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7";
        $headers['Content-Type'] = "application/json";
        $headers['Referer'] = "https://www.aeromexico.com/es-mx";
        $headers['Pos'] = "WEB";
        $headers['Client'] = "ecommerce";
        $headers['Flow'] = "booking";

        $this->http->GetURL("https://www.aeromexico.com/api/v5/airports/arrival/{$fields['DepCode']}", $headers);

        //        HTTP Status 406 – Not Acceptable
        if (strpos($this->http->Response['body'], "HTTP Status 406 – Not Acceptable") !== false) {
            $this->SetWarning('There are no flights from this airport');

            return false;
        }

        $response = $this->http->JsonLog(null, 0);

        if (($this->http->Response['code'] == 401)
        || ($this->http->Response['code'] == 500)
        || ($this->http->Response['code'] == 404)) {
            $this->SetWarning('There are no flights from this airport');

            return false;
        }

        if ($this->isBadProxy()
        || $response->messageType == "ERROR_MESSAGE") {
            throw new \CheckRetryNeededException(3, 0);
        }

        foreach ($response->data->fliesToAirportCode as $airport) {
            if ($airport === $fields['ArrCode']) {
                return true;
            }
        }

        $this->SetWarning('There are no flights to this route');

        return false;
    }

    private function parseRewardFlights($isOffer): array
    {
        $this->logger->notice(__METHOD__);

        if (empty($isOffer)) {
            $this->SetWarning("There are no flights to this route");

            return [];
        }

        $results = [];

        foreach ($isOffer as $element) {
            if ($element->typeFare !== 'FFY') {
                continue;
            }

            foreach ($element->fares->_collection as $fare) {
                $result = [
                    'distance'       => $element->segments->totalFlightMilesAM,
                    'num_stops'      => count($element->segments->_collection) - 1,
                    'tickets'        => $fare->seatsRemaining,
                    'award_type'     => $fare->fareType ?? null,
                    'classOfService' => $this->getClassOfService($fare->fareType),
                    'payments'       => [
                        'currency' => $fare->estimate->currency->currencyCode,
                        'taxes'    => $fare->estimate->currency->total - $fare->estimate->currency->base,
                        'fees'     => $fare->estimate->currency->bookingFee,
                    ],
                    'redemptions' => [
                        'miles'   => $fare->estimate->currency->totalCPPoints,
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'times' => [
                        'flight'  => null,
                        'layover' => null,
                    ],
                ];

                foreach ($element->segments->_collection as $segment) {
                    $seg = [
                        'num_stops' => 0,
                        'departure' => [
                            'date'     => date('Y-m-d H:i', strtotime($segment->departureDateTime)),
                            'dateTime' => strtotime($segment->departureDateTime),
                            'airport'  => $segment->departureAirport,
                        ],
                        'arrival' => [
                            'date'     => date('Y-m-d H:i', strtotime($segment->arrivalDateTime)),
                            'dateTime' => strtotime($segment->arrivalDateTime),
                            'airport'  => $segment->arrivalAirport,
                        ],
                        'cabin'      => $this->getNameCabin($fare->fareType) ?? null,
                        'fare_class' => $segment->bookingClass,
                        'aircraft'   => $sigment->aircraftType ?? null,
                        'flight'     => [$segment->operatingCarrier . $segment->operatingFlightCode],
                        'airline'    => $segment->operatingCarrier,
                        'times'      => [
                            'flight'  => null,
                            'layover' => null,
                        ],
                        'tickets' => null,
                    ];
                    $result['connections'][] = $seg;
                }
                $results[] = $result;
            }
        }

        if (empty($results)) {
            $this->SetWarning('No flights for points on this date');
        }

        $this->logger->debug(var_export($results, true), ['pre' => true]);

        return $results;
    }

    private function getNameCabin(string $fareType)
    {
        $array = [
            'RS'       => 'economy',
            'COACH_EC' => 'economy',
            'COACH_EF' => 'economy',
            'COACH_CL' => 'economy',
            'COACH_CF' => 'economy',
            'COACH_AM' => 'business',
            'COACH_AF' => 'business',
            'FIRST_FF' => 'firstClass',
            'FIRST_FL' => 'firstClass',
            'FIRST_PO' => 'firstClass',
            'FIRST_PF' => 'firstClass',
            'RZ'       => 'firstClass',
        ];

        if (empty($array[$fareType])) {
            return null;
        } else {
            return $array[$fareType];
        }
    }

    private function getClassOfService($fareType)
    {
        $array = [
            'RS'       => 'Boleto Clásico',
            'COACH_EC' => 'Boleto Clásico',
            'COACH_EF' => 'Boleto Clásico',
            'COACH_CL' => 'Clásico',
            'COACH_CF' => 'Clásica',
            'COACH_AM' => 'AM Plus',
            'COACH_AF' => 'AM Plus',
            'FIRST_FF' => 'Premier',
            'FIRST_FL' => 'Premier',
            'FIRST_PO' => 'Premier One',
            'FIRST_PF' => 'Premier One',
            'RZ'       => 'Boleto Premier',
        ];

        if (empty($array[$fareType])) {
            $this->sendNotification('Check ClassOfService //DM');

            return null;
        } else {
            return $array[$fareType];
        }
    }

    private function isBadProxy(): bool
    {
        return
            $this->http->Response['code'] == 403
            || $this->http->Response['code'] == 502
            || $this->http->Response['code'] == 504
            || strpos($this->http->Error, 'Network error 0 -') !== false
            || strpos($this->http->Error, 'Network error 52 - Empty reply from server') !== false
            || strpos($this->http->Error, 'Network error 56 - OpenSSL SSL_read: error') !== false
            || strpos($this->http->Error, 'Network error 28 - Connection timed out after') !== false
            || strpos($this->http->Error, 'Network error 28 - Operation timed out after') !== false
            || strpos($this->http->Error, 'Network error 56 - Proxy CONNECT aborted') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 403 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 490 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 7 - Failed to connect to') !== false
            || strpos($this->http->Error, 'Network error 56 - Recv failure: Connection reset by peer') !== false
            ;
    }

    //AM Plus
//AM Plus Flexible
//Clásica
//Clásica Flexible
//Premier
//Premier Flexible
//Premier One
//Premier One Flexible
//Boleto Premier
//Boleto Premier Flexible
}
