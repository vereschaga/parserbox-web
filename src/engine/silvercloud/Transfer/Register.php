<?php

namespace AwardWallet\Engine\silvercloud\Transfer;

class Register extends \TAccountCheckerSilvercloud
{
    public static $titles = [
        'Mr.'  => 'Mr.',
        'Dr.'  => 'Dr.',
        'Miss' => 'Miss',
        'Mrs.' => 'Mrs.',
        'Ms.'  => 'Ms.',
        'Rev.' => 'Rev.',
    ];

    public static $countries = [
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BZ' => 'Belize',
        'BJ' => 'Benin',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Territory',
        'BN' => 'Brunei Darussalam',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CV' => 'Cape Verde',
        'KY' => 'Cayman Islands',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (Keeling) Islands',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'HR' => 'Croatia',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
        'TF' => 'French Southern Territories',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GR' => 'Greece',
        'GL' => 'Greenland',
        'GD' => 'Grenada',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JP' => 'Japan',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macau',
        'MK' => 'Macedonia',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'MX' => 'Mexico',
        'MD' => 'Moldova, Republic of',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'MS' => 'Montserrat',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar',
        'NA' => 'Namibia',
        'NR' => 'Nauru',
        'NP' => 'Nepal',
        'NL' => 'Netherlands',
        'NC' => 'New Caledonia',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'NU' => 'Niue',
        'NF' => 'Norfolk Island',
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PA' => 'Panama',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'WS' => 'Samoa (Independent)',
        'SM' => 'San Marino',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SR' => 'Suriname',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UM' => 'United States Minor Outlying Islands',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City State (Holy See)',
        'VE' => 'Venezuela',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'CN' => 'China',
        'KP' => 'Korea, North',
        'KR' => 'Korea, South',
    ];

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        //$this->http->LogHeaders = true;
        $this->http->GetURL('https://www.silverrewards.com/silverrewardssignup/default.cfm');
        $this->http->FormURL = 'https://www.silverrewards.com/silverrewardssignup/default.cfm';
        $this->http->Form = ['terms' => '1'];

        if (!$this->http->PostForm()) {
            return false;
        }

        if (!$this->http->ParseForm('CFForm_1')) {
            return false;
        }

        switch ($fields['Country']) {
            case 'US':
                $this->http->Form['country'] = 1;

                break;

            case 'CA':
                $this->http->Form['country'] = 2;

                break;

            default:
                $this->http->Form['country'] = 3;

                break;
        }

        if (!$this->http->PostForm()) {
            return false;
        }

        if (!$this->http->FindSingleNode('//text()[contains(., "fill out the enrollment form")]') || !$this->http->ParseForm(null, 1, true, '//form[input[@name="LocationId"]]')) {
            return false;
        }
        $us = $fields['Country'] === 'US';
        $fields['Country'] = self::$countries[$fields['Country']];
        $fullPhone = $fields['PhoneCountryCodeNumeric'] . $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];

        if (strlen($fullPhone) < 10) {
            throw new \UserInputError('Invalid phone number');
        }
        $fields['FullPhone'] = substr($fullPhone, -10, 3) . '-' . substr($fullPhone, -7, 3) . '-' . substr($fullPhone, -4, 4);

        if ($us) {
            $states = $this->http->FindNodes('//select[@name="State"]/option/@value');

            if (!in_array($fields['StateOrProvince'], $states)) {
                throw new \UserInputError('Invalid US state');
            }
        }

        foreach ([
            'salutation' => 'Title',
            'FName' => 'FirstName',
            'MiddleName' => 'MiddleInitial',
            'LName' => 'LastName',
            'Email' => 'Email',
            'Address1' => 'AddressLine1',
            'Address2' => 'AddressLine2',
            'City' => 'City',
            'State' => 'StateOrProvince',
            'Zip' => 'PostalCode',
            'country' => 'Country',
            'Company' => 'Company',
            'Phone' => 'FullPhone',
            'Month' => 'BirthMonth',
            'Day' => 'BirthDay',
            'Year' => 'BirthYear',
        ] as $n => $f) {
            $this->http->Form[$n] = $fields[$f];
        }

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->XPath->query('//img[contains(@alt, "There was a problem creating your new account")]')->length > 0) {
            $error = $this->http->FindSingleNode('//div[contains(@style, "color:red")]');

            if (empty($error)) {
                return false;
            } else {
                throw new \UserInputError($error);
            } // Is it always user input error?
        }

        if ($success = $this->http->FindSingleNode('//div[contains(text(), "Thank you for signing up for Silver Cloud Inns and Hotels Silver Rewards Program")]')) {
            $this->ErrorMessage = $success;
            $this->http->Log($success);

            return true;
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Title' => [
                'Type'     => 'string',
                'Caption'  => 'Title',
                'Required' => true,
                'Options'  => self::$titles,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name',
                'Required' => true,
            ],
            'MiddleInitial' => [
                'Type'     => 'string',
                'Caption'  => 'Middle Initial',
                'Required' => false,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name',
                'Required' => true,
            ],
            'BirthDay' => [
                'Type'     => 'integer',
                'Caption'  => 'Day of Birth Date',
                'Required' => true,
            ],
            'BirthMonth' => [
                'Type'     => 'integer',
                'Caption'  => 'Month of Birth Date',
                'Required' => true,
            ],
            'BirthYear' => [
                'Type'     => 'integer',
                'Caption'  => 'Year of Birth Date',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => '2 letter country code',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'StateOrProvince' => [
                'Type'     => 'string',
                'Caption'  => 'State/Province (if your country is not US or Canada, please type a full name)',
                'Required' => true,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'City',
                'Required' => true,
            ],
            'AddressLine1' => [
                'Type'     => 'string',
                'Caption'  => 'Address Line 1',
                'Required' => true,
            ],
            'AddressLine2' => [
                'Type'     => 'string',
                'Caption'  => 'Address Line 2',
                'Required' => false,
            ],
            'PostalCode' => [
                'Type'     => 'string',
                'Caption'  => 'Postal Code',
                'Required' => true,
            ],
            'Company' => [
                'Type'     => 'string',
                'Caption'  => 'Company name',
                'Required' => false,
            ],
            'PhoneCountryCodeNumeric' => [
                'Type'     => 'string',
                'Caption'  => 'Phone country code',
                'Required' => true,
            ],
            'PhoneAreaCode' => [
                'Type'     => 'string',
                'Caption'  => 'Phone area code',
                'Required' => true,
            ],
            'PhoneLocalNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone number',
                'Required' => true,
            ],
        ];
    }
}
