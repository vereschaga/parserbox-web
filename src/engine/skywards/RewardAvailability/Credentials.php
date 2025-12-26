<?php

namespace AwardWallet\Engine\skywards\RewardAvailability;

class Credentials
{
    public static function getCredentials()
    {
        $variants = [
            [
                'Login' => 'EK662627571',
                //                'Pass'  => 'B@nana1234',
                'Pass'  => 'B@nana12_',
                //                //                'Email' => 'zm4tstaw@gmail.com',
            ],
            //            ['Login' => 'k.solovyeva@yahoo.co.uk', 'Pass' => 'Rhfcyjakjncrfz28!']
            /*
            [
                'Login' => 'EK667601395',
                'Pass'  => 'hQRUBR5Tff2sr7x_',
                //                'Email' => 'johanna.smith0680@gmail.com',
                //                'EmailPass'=>'tbd8rMAkZyzEVYF'
            ],
*/
            /*TODO: Your account has been proactively locked as a security precaution. Please reset your password using the forgot password link below to regain access to your account. To keep your account safe, we may request you update your password from time to time.
             [
                'Login' => 'EK667602714',
                'Pass'  => 'tb34rM!AkZ#zEVF',
                //                'Email' => 'johanna.smith.0680@gmail.com',
            ],
            [
                'Login' => 'EK667604722',
                'Pass'  => 'rl26rMDAkZszE_F',
                //                'Email' => 'johannasmith.0680@gmail.com',
            ],*/
            /*[
                'Login' => 'EK667787201',
                'Pass'  => '&cw>vn4N7{sQXt',
                //'Email' => 'gosling412@gmail.com',
            ],*/
        ];
        $key = array_rand($variants);

        return $variants[$key];
    }
}
