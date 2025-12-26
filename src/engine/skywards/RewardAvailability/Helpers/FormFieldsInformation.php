<?php


namespace AwardWallet\Engine\skywards\RewardAvailability\Helpers;


class FormFieldsInformation
{
    private static $title = [
        "Mr" => "Mr", "Mrs" => "Mrs"
    ];

    private static $dobMonth = [
        "Jan" => "Jan", "Feb" => "Feb", "Mar" => "Mar", "Apr" => "Apr", "May" => "May", "Jun" => "Jun", "Jul" => "Jul", "Aug" => "Aug", "Sep" => "Sep", "Oct" => "Oct", "Nov" => "Nov", "Dec" => "Dec",
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
                'Caption'  => 'First Name (2-25 length)',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name (2-26 length)',
                'Required' => true,
            ],
            'BirthdayDate' => [
                'Type'     => 'date',
                'Caption'  => 'Your date of birth, older than 18 (MM/DD/YYYY)',
                'Required' => true,
            ],
            'PhoneNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Number (10 max-length, US only)',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password (Must be 8-16 characters and include at least 1 lowercase letter, 1 uppercase letter, 1 number and 1 symbol [!@#$%^&*()])',
                'Required' => true,
            ],
        ];
    }
}