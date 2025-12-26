<?php

namespace AwardWallet\Engine\testprovider\Itineraries;

use AwardWallet\Engine\testprovider\Success;
use AwardWallet\MainBundle\Entity\Restaurant;

class Meeting extends Success
{
    public function ParseItineraries()
    {
        return [
            [
                'Kind'        => 'E',
                'ConfNo'      => '123456789',
                'Name'        => 'Landing on Mars with Musk',
                'StartDate'   => strtotime('12 may 2030, 12:00'),
                'EndDate'     => strtotime('12 may 2030, 14:00'),
                'Address'     => 'Mars',
                'Phone'       => '122-236-785',
                'DinerName'   => 'Elon Musk',
                'Guests'      => 2,
                'TotalCharge' => 8000,
                'Tax'         => 1096.00,
                'Currency'    => 'USD',
                'EventType'   => Restaurant::EVENT_MEETING,
            ],
        ];
    }
}
