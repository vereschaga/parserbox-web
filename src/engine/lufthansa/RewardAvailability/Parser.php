<?php

namespace AwardWallet\Engine\lufthansa\RewardAvailability;

use AwardWallet\Engine\ProxyList;

class Parser extends \TAccountCheckerLufthansa
{
    use \SeleniumCheckerHelper;
    use ProxyList;
    public $isRewardAvailability = true;
    private $browser;

    public static function getRASearchLinks(): array
    {
        return ['https://www.miles-and-more.com/row/en/spend/flights/flight-award.html' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        /*
        $this->setProxyBrightData();
        */
        //$this->http->setRandomUserAgent(20);
        $this->http->setUserAgent("Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36");
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function LoadLoginForm()
    {
        //$this->http->removeCookies();
        parent::loginMAM();

        return true;
    }

    public function Login()
    {
        return true;
    }

    public function getRewardAvailabilitySettings()
    {
        $arrCurrencies = ['USD'];

        return [
            'supportedCurrencies'      => $arrCurrencies,
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'USD',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));
//        if (!$this->validRoute($fields)) {
//            return ['routes' => []];
//        }
        $supportedCurrencies = $this->getRewardAvailabilitySettings()['supportedCurrencies'];

        if (!in_array($fields['Currencies'][0], $supportedCurrencies)) {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        $this->http->GetURL('https://www.miles-and-more.com/row/en/spend/flights/flight-award.html');
        //$this->http->GetURL('https://api.miles-and-more.com/v1/user/386813570/sso/ams');
        $data = http_build_query([
            'COUNTRY_SITE'                 => 'DE',
            'FORCE_OVERRIDE'               => 'TRUE',
            'LANGUAGE'                     => 'GB',
            'PORTAL'                       => 'MAM',
            'PORTAL_SESSION'               => 'MTM4MjQwODA3NzMxMTQ0MjAwMTM1OTE1ODE4MTExNDIyNTYwNDAzNzk0NTUxMTMxODU',
            'POS'                          => 'DE',
            'SITE'                         => '5AHC5AHC',
            'SO_SITE_COUNTRY_OF_RESIDENCE' => 'DE',
            'SO_SITE_LH_FRONTEND_URL'      => 'www.miles-and-more.com',
            'WDS_IBM_LOGOUT_URL'           => 'https://www.miles-and-more.com',
            'ENC'                          => 'ANu5Xe6WtD2sf_agWgX5V3bZKJW6FtIyKjX0nsI3ykrYNVCGYt7TWOvYWxARuIyc0oYNlxV5sIup0JINDOYh2ye6i9PTd2SoG7DiBRSu2Gz5c3l_EyK0_IDuvjmCIKpdo4fJ2IgAJt20PpoDga_u_3bahSV8l0LUQb47qQKOmOASl0Ze9wkTaDJRTzJi5iplu9RprefyTSmxolqfG_n1wHPRCNbVMJMFX9mVdn5RJ5OCMtzBX4fwwd0YL52xIuplUjMP_U4zds487zKrZSmmC7PYhG1rnVDbgQ8QPoCxtb_bx18pkotQQcEky5nasKFzYYwO1c0z3R3WFBrcOB6INpeCj8l_8zJdEb5w5VVhDiIqbivYROg1UoQLgVXabm5mVfO6EKxnDqX9zioJgCHhLJ23e2XG8D6ibyjg_ejihKcW1LUr2a69RZP9K-_S9SZdc3gBJX6wxtCRZQk1wJQh7cwNgKUSWqKf3f3o6Bn2SiUdQEG9V3OnL5nAfakRn0Ing8tW7wHbgc51Z-pH8oPje4ONp7u226eSm7tMLgLkkN6OUQsCMh6KZz6azsV15utrqyJQReHBYFC1vZvCb2uEy8OKyou4BxZGiFi3i_-eiompwbNYlAdwR7mJs5ubDxXiJch8LnH4P0RmffL88D7VSVwJeTaw9pRBh3WM5ckuOl6amdsEu8Vg46MYxDTOwtaxPQjCwSi-Y-qKnGNK431DsQqbJjTzdzFFX7s9jr40-uMAI67TNtw4p_qZCAPpjNTbxEWgs43x5UiFecxNwTslNNlz6J5RM1nJCSWKDDRBKJEoLFZjN-vATD70qiuxnjIb-9iC6ZleMIpKpOIwiE2IlH1_gNoxibxNVw==',
            'ENCT'                         => '2',
            'SERVICE_ID'                   => '6',
            'B_DATE_1'                     => '202105210000',
            'B_LOCATION_1'                 => 'OSL',
            'CABIN'                        => 'E',
            'E_LOCATION_1'                 => 'LAX',
            'RELATIONSHIP_1'               => 'MEMBER',
            'TRAVELLER_TYPE_1'             => 'ADT',
            'TRIP_TYPE'                    => 'O',
            'ALLOW_PROMO'                  => 'N',
            'SO_SITE_ALLOW_CITP_COMMANDS'  => 'true',
            'SO_SITE_CURRENCY_REBOOK_FEE'  => 'EUR',
            'SO_SITE_ETIX_MIN_DATE'        => '20210521',
            'SO_SITE_EXP_TBM_FEE_AMOUNT'   => '40.00',
            'SO_SITE_EXP_TBM_FEE_CURR'     => 'EUR',
            'SO_SITE_EXP_TBM_MIN_DATE'     => '20210524',
            'SO_SITE_FP_WITHHOLD_TAXES'    => 'TRUE',
            'SO_SITE_OFFICE_ID'            => 'FRALH08MM',
            'SO_SITE_POINT_OF_SALE'        => 'FRA',
            'SO_SITE_POINT_OF_TICKETING'   => 'FRA',
            'SO_SITE_TBM_MIN_DATE'         => '20210524',
            'SO_SITE_ADDR_DELIVERY_FMT'    => 'ADDR:%M,%Y,%D,%A,%B%P,%Z %C',
            'SO_SITE_ALLOW_TAX_IN_MILES'   => 'FALSE',
        ]);
        $this->http->GetURL("https://book.miles-and-more.com/mam/dyn/air/booking/AwardEntry?{$data}");

        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];
        $rawModel = $this->http->FindPreg('/var rawModel = (\{.+?\}); sessionStorage/');
        $rawModel = $this->http->JsonLog($rawModel, 2);

        $searchData = $rawModel->modelObject->mamEntryPage->searchData;
        unset(
            $searchData->{'@c'},
            $searchData->commercialFareFamilies[0]->boundId,
            $searchData->commercialFareFamilies[0]->creationDate
        );

        $searchData->searchDestinations[0]->originLocation->{'@c'} = 'Location';
        $searchData->searchDestinations[0]->destinationLocation->{'@c'} = 'Location';
        $searchData->searchDestinations[0]->originLocation_ = 'A_OSL';
        $searchData->searchDestinations[0]->destinationLocation_ = 'A_LAX';

        $data = [
            '@c' => 'avail.flexpricer.searchdata.MAMFlexPricerSearchData',
        ];
        $data = array_merge((array) $searchData, $data);
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://book.miles-and-more.com/mam/dyn/air/booking/AwardAvailability;jsessionid={$rawModel->modelObject->request->sessionID}?OUTPUT_FORMAT=json&LANGUAGE=GB&COUNTRY_SITE=DE&SITE=5AHC5AHC",
            json_encode(array_filter($data))
        );
        $this->http->RetryCount = 2;

        //return ['routes' => $routes];
    }

    public function parseRewardFlights($flightSearch, array $fields)
    {
        $this->logger->notice(__METHOD__);

        return;
    }

    private function getCabinFields($flip = true): array
    {
        $cabins = [
            'Y' => 'economy',
            'S' => 'premiumEconomy',
            'J' => 'business',
            'F' => 'firstClass',
        ];

        if ($flip) {
            return array_flip($cabins);
        }

        return $cabins;
    }

    private function inSellingClass($code, $leg)
    {
        foreach ($leg->cabinClassAvailability as $legSellingClass) {
            if ($code == $legSellingClass->sellingClass) {
                return $legSellingClass->numberOfSeats ?? true;
            }
        }

        return false;
    }

    private function formatTimes($milliseconds)
    {
        $seconds = floor($milliseconds / 1000);
        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;

        return ($hours + $minutes > 0) ? sprintf('%02d:%02d', $hours, $minutes) : null;
    }

    private function sumTimes($connections)
    {
        $minutesFlight = $minutesLayover = 0;

        foreach ($connections as $value) {
            if (isset($value['times']['flight'])) {
                [$hour, $minute] = explode(':', $value['times']['flight']);
                $minutesFlight += $hour * 60;
                $minutesFlight += $minute;
                /*
                if (isset($value['times']['layover'])) {
                    list($hour, $minute) = explode(':', $value['times']['layover']);
                    $minutesFlight += $hour * 60;
                    $minutesFlight += $minute;
                }
                */
            }

            if (isset($value['times']['layover'])) {
                [$hour, $minute] = explode(':', $value['times']['layover']);
                $minutesLayover += $hour * 60;
                $minutesLayover += $minute;
            }
        }
        $hoursFlight = floor($minutesFlight / 60);
        $minutesFlight -= floor($minutesFlight / 60) * 60;
        $hoursLayover = floor($minutesLayover / 60);
        $minutesLayover -= floor($minutesLayover / 60) * 60;

        return [
            'flight'  => sprintf('%02d:%02d', $hoursFlight, $minutesFlight),
            'layover' => ($hoursLayover + $minutesLayover > 0) ?
                sprintf('%02d:%02d', $hoursLayover, $minutesLayover) : null,
        ];
    }
}
