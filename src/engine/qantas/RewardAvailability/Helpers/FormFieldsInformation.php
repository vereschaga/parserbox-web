<?php

namespace AwardWallet\Engine\qantas\RewardAvailability\Helpers;

class FormFieldsInformation
{
    private static $title = [
        "Mr" => "Mr", "Mrs" => "Mrs",
    ];

    public static $states = [
        'AL' => '0',
        'AK' => '1',
        'AZ' => '2',
        'AR' => '3',
        'CA' => '4',
        'CO' => '5',
        'CT' => '6',
        'DE' => '7',
        'DC' => '8',
        'FL' => '9',
        'GA' => '10',
        'HI' => '11',
        'ID' => '12',
        'IL' => '13',
        'IN' => '14',
        'IA' => '15',
        'KS' => '16',
        'KY' => '17',
        'LA' => '18',
        'ME' => '19',
        'MD' => '20',
        'MA' => '21',
        'MI' => '22',
        'MN' => '23',
        'MS' => '24',
        'MO' => '25',
        'MT' => '26',
        'NE' => '27',
        'NV' => '28',
        'NH' => '29',
        'NJ' => '30',
        'NM' => '31',
        'NY' => '32',
        'NC' => '33',
        'ND' => '34',
        'OH' => '35',
        'OK' => '36',
        'OR' => '37',
        'PA' => '38',
        'RI' => '39',
        'SC' => '40',
        'SD' => '41',
        'TN' => '42',
        'TX' => '43',
        'UT' => '44',
        'VT' => '45',
        'VA' => '46',
        'WA' => '47',
        'WV' => '48',
        'WI' => '49',
        'WY' => '50',
    ];

    public static function getRegisterFields()
    {
        return [
            'Title' => [
                'Type'     => 'string',
                'Caption'  => 'Title',
                'Required' => true,
                'Options'  => static::$title,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name (2-25 length, characters only)',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name (2-26 length, characters only)',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'PhoneNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Number (numbers, 10 max-length)',
                'Required' => true,
            ],
            'Address' => [
                'Type'     => 'string',
                'Caption'  => 'Address (Must 0-29 characters or numbers long (include . , / \ and space) )',
                'Required' => true,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'City (0-29 characters)',
                'Required' => true,
            ],
            'ZipCode' => [
                'Type'     => 'string',
                'Caption'  => 'Post Code (numbers, 5 max-length, US only)',
                'Required' => true,
            ],
            'State' => [
                'Type'     => 'string',
                'Caption'  => 'State (US only)',
                'Required' => true,
                'Options'  => static::$states,
            ],
            'BirthdayDate' => [
                'Type'     => 'date',
                'Caption'  => 'Your date of birth, older than 18 (MM/DD/YYYY)',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'PIN code for authorization (4 number only, must not consist of 4 consecutive or same numbers)',
                'Required' => true,
            ],
        ];
    }
}
