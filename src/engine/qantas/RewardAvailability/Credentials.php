<?php

namespace AwardWallet\Engine\qantas\RewardAvailability;

class Credentials
{
    public static function getCredentials()
    {
        return [
            'Login'   => '1959732452',
            'Login2'  => 'Milford',
            'Pass'    => '1212',
            'Answers' => [
                'Mother\'s maiden name'      => 'Millford',
                'Date of birth (yyyy-mm-dd)' => '1980-06-04',
                'Postcode'                   => '07071',
            ],
        ];
    }
}
