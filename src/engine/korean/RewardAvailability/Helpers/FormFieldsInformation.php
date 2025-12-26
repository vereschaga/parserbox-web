<?php


namespace AwardWallet\Engine\korean\RewardAvailability\Helpers;


class FormFieldsInformation
{
    private static $gender = [
        "male" => "Male", "female" => "Female",
    ];

    public static function getRegisterFields()
    {
        return [
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name (2-25 length)',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name (2-26 length)',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password (8 to 20 alphanumeric and special characters(@~!#$%^&*()\-=+,.?) )',
                'Required' => true,
            ],
            'BirthdayDate' => [
                'Type'     => 'date',
                'Caption'  => 'Date of Birth Date, older than 18 (MM/DD/YYYY)',
                'Required' => true,
            ],
            'Gender' => [
                'Type'    => 'string',
                'Caption' => 'Gender',
                'Required' => true,
                'Options'  => static::$gender,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'PhoneNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Number (10 max-length, US only)',
                'Required' => true,
            ],
        ];
    }
}