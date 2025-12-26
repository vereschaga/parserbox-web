<?php

namespace AwardWallet\Engine\testprovider\Itineraries;

use AwardWallet\Engine\testprovider\Success;

class InvalidAirportCode extends Success
{
    public function ParseItineraries()
    {
        return [
            [
                'Kind'          => 'T',
                'RecordLocator' => 'INVAIR',
                'TripSegments'  => [
                    [
                        'DepCode'      => 'HDQ',
                        'ArrCode'      => 'TYS',
                        'DepDate'      => strtotime("tomorrow"),
                        'ArrDate'      => strtotime("tomorrow") + 3600 * 12,
                        'FlightNumber' => '5221',
                        'AirlineName'  => 'AA',
                    ],
                ],
            ],
        ];
    }
}
