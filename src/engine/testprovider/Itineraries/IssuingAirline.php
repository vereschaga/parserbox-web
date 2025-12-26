<?php

namespace AwardWallet\Engine\testprovider\Itineraries;

use AwardWallet\Engine\testprovider\Success;

class IssuingAirline extends Success
{
    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $it = [
            [
                'Kind'          => 'T',
                'RecordLocator' => 'LY4OWD',
                'Passengers'    => 'Mr Alexi Vereschaga',
                'TripSegments'  => [
                    [
                        'AirlineName'  => 'KL',
                        'Operator'     => 'KLM',
                        'Duration'     => '3:55',
                        'DepDate'      => $this->clipSecondsFromTimeStamp(strtotime('2030-01-01 10:00')),
                        'DepCode'      => 'JFK',
                        'DepName'      => 'JF Kennedy Airport',
                        'ArrDate'      => $this->clipSecondsFromTimeStamp(strtotime('2030-01-01 13:55')),
                        'ArrCode'      => 'AMS',
                        'ArrName'      => 'Amsterdam - Schiphol, Netherlands',
                        'FlightNumber' => '642',
                        'Seats'        => '23',
                        'Stops'        => 0,
                    ],
                ],
            ],
        ];
    }
}
