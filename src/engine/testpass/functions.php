<?php

class TAccountCheckerTestpass extends TAccountChecker
{
    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function Parse()
    {
        $this->SetBalance(100);
    }

    public function ParseItineraries()
    {
        return [
            [
                'Kind'            => 'T',
                'RecordLocator'   => 'TSTPSSCN',
                'Passengers'      => 'John Smith, Katy Smith',
                'TotalCharge'     => 100,
                'Tax'             => 7,
                'Currency'        => 'USD',
                'ReservationDate' => strtotime('2014-01-01 9:00'),
                'TripSegments'    => [
                    [
                        'AirlineName'  => 'DL',
                        'Duration'     => '3:55',
                        'DepDate'      => strtotime('2050-01-01 10:00'),
                        'DepCode'      => 'JFK',
                        'DepName'      => 'JF Kennedy Airport',
                        'ArrDate'      => strtotime('2050-01-01 13:55'),
                        'ArrCode'      => 'LAX',
                        'ArrName'      => 'Los Angeles International Airport',
                        'FlightNumber' => 'TE223',
                        'Seats'        => '23',
                    ],
                ],
                'ConfirmationNumbers' => '12345, 56789',
            ],
        ];
    }
}
