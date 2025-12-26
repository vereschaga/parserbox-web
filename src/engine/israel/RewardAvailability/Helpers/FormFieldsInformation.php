<?php

namespace AwardWallet\Engine\israel\RewardAvailability\Helpers;

class FormFieldsInformation
{
    public static $genders = [
        "male"   => "Male",
        "female" => "Female",
    ];
    private static $title = [
        "Mr" => "Mr", "Mrs" => "Mrs", "Miss" => "Miss", "Mdm" => "Mdm", "Ms" => "Ms", "Mstr" => "Mstr", "Dr" => "Dr", "Prof" => "Prof", "Others" => "Others",
    ];

    private static $dobMonth = [
        '1' => '01', '2' => '02', '3' => '03', '4' => '04', '5' => '05', '6' => '06', '7' => '07', '8' => '08', '9' => '09', '10' => '10', '11' => '11', '12' => '12',
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
                'Caption'  => 'First Name (2-25 length, can use characters and symbols /\'@()”-,)',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name (2-26 length, can use characters and symbols /\'@()”-,)',
                'Required' => true,
            ],
            'BirthdayDate' => [
                'Type'     => 'date',
                'Caption'  => 'Your date of birth, older than 18 (MM/DD/YYYY)',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'The password should include 6 characters, At least one English letter A-Z, At least one number, At least one character of the type: [+ | & ! * . - % _ ? : =] .',
                'Required' => true,
            ],
            'MobileAreaCode' => [
                'Type'     => 'string',
                'Caption'  => 'Mobile Area Code for US (4 max-length)',
                'Required' => true,
            ],
            'PhoneNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Number (10 digits with area code)',
                'Required' => true,
            ],
            'Gender' => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => static::$genders,
            ],
        ];
    }
}
