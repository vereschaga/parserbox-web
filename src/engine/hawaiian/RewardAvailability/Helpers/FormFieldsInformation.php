<?php


namespace AwardWallet\Engine\hawaiian\RewardAvailability\Helpers;


class FormFieldsInformation
{

    private static $genders = [
        'male' => 'Male',
        'female' => 'Female',
    ];

    private static $zipCodes = [
        '35005' => 'AL',
        '99501' => 'AK',
        '85002' => 'AZ',
        '71603' => 'AR',
        '96106' => 'CA',
        '80001' => 'CO',
        '06001' => 'CT',
        '20011' => 'DC',
        '19701' => 'DE',
        '32007' => 'FL',
        '30002' => 'GA',
        '96701' => 'HI',
        '83204' => 'ID',
        '46001' => 'IN',
        '50003' => 'IA',
        '60042' => 'IL',
        '66025' => 'KS',
        '40022' => 'KY',
        '70001' => 'LA',
        '03901' => 'ME',
        '21930' => 'MD',
        '01001' => 'MA',
        '48003' => 'MI',
        '55001' => 'MN',
        '38601' => 'MS',
        '65899' => 'MO',
        '59001' => 'MT',
        '68001' => 'NE',
        '89830' => 'NV',
        '03031' => 'NH',
        '07001' => 'NJ',
        '87005' => 'NM',
        '14925' => 'NY',
        '27009' => 'NC',
        '58001' => 'ND',
        '43001' => 'OH',
        '73001' => 'OK',
        '97004' => 'OR',
        '15004' => 'PA',
        '02801' => 'RI',
        '29001' => 'SC',
        '57002' => 'SD',
        '37010' => 'TN',
        '73301' => 'TX',
        '84002' => 'UT',
        '05907' => 'VT',
        '20101' => 'VA',
        '98002' => 'WA',
        '26886' => 'WV',
        '53001' => 'WI',
        '82001' => 'WY',
    ];

    public static function getRegisterFields()
    {
        return [
            'Username' => [
                'Type'     => 'string',
                'Caption'  => 'Username (Must be at least six characters long, including at least one letter and no special characters or spaces) unique',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email (max length 50) unique',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password (Must be 10-16 characters and include at least 1 lowercase letter, 1 uppercase letter and 1 number)',
                'Required' => true,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name (Must 0-30 characters long and no special characters or spaces or number)',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name (Must 0-30 characters long and no special characters or spaces or number)',
                'Required' => true,
            ],
            'Gender' => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => static::$genders,
            ],
            'BirthdayDate' => [
                'Type'     => 'date',
                'Caption'  => 'Your date of birth, older than 18 (MM/DD/YYYY)',
                'Required' => true,
            ],
            'ZipCode' => [
                'Type'     => 'string',
                'Caption'  => 'Zip Code',
                'Required' => true,
                'Options'  => static::$zipCodes,
            ],
            'Address' => [
                'Type'     => 'string',
                'Caption'  => 'Address (Must 0-29 characters or numbers long (include . , / \ and space) )',
                'Required' => true,
            ],
            'PhoneNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Number (10 numbers length)',
                'Required' => true,
            ],
            'Answer' => [
                'Type'     => 'string',
                'Caption'  => 'Answer (max length 25)',
                'Required' => true,
            ],
        ];
    }
}