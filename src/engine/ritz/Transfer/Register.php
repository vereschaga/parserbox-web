<?php

namespace AwardWallet\Engine\ritz\Transfer;

class Register extends \TAccountChecker
{
    public static $fieldMap = [
        'Title'           => 'title',
        'FirstName'       => 'firstName',
        'LastName'        => 'lastName',
        'Country'         => 'country',
        'AddressType'     => 'addressType',
        'AddressLine1'    => 'street1',
        'City'            => 'city',
        'StateOrProvince' => 'stateProvince',
        'PostalCode'      => 'postalCode',
        'Email'           => 'emailAddress',
        'Password'        => [
            'password',
            'passwordConfirmation',
        ],
    ];

    public static $addressTypes = [
        'H' => 'Home',
        'B' => 'Business',
    ];

    public static $titles = [
        'MR'   => 'Mr.',
        'MRS'  => 'Mrs.',
        'DR'   => 'Dr.',
        'MS'   => 'Ms.',
        'MISS' => 'Miss',
    ];

    public static $countries = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua',
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
        'BA' => 'Bosnia and Herzegovina',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'BR Indian Ocean Territories',
        'VG' => 'British Virgin Islands',
        'BN' => 'Brunei Darussalam',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CV' => 'Cape Verde',
        'KY' => 'Cayman Islands',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoro Islands',
        'CG' => 'Congo',
        'CD' => 'Congo, Dem. Republic',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CW' => 'Curacao',
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
        'FO' => 'Faeroe Islands',
        'FK' => 'Falkland Islands',
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
        'GP' => 'Guadeloupe',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard & McDonald Islands',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'CI' => 'Ivory Coast',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KP' => 'Korea, Dem. Peoples',
        'KR' => 'Korea, Republic Of',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Laos',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macau',
        'MK' => 'Macedonia',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'MD' => 'Moldova, Republic of',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'ME' => 'Montenegro',
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
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn Island',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'LC' => 'Saint Lucia',
        'WS' => 'Samoa, Ind. State of',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome-Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'GS' => 'S Georgia-S Sandwich',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SH' => 'St Helena',
        'KN' => 'St Kitts-Nevis',
        'PM' => 'St Pierre & Miquelon',
        'VC' => 'St Vincent & The Grenadines',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard & Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikstan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TL' => 'Timor-Leste',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad & Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks & Caicos',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'UY' => 'Uruguay',
        'US' => 'USA',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'VI' => 'Virgin Islands (United States)',
        'WF' => 'Wallis & Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'SX' => 'St Maarten',
    ];

    public static $states = [
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AS' => 'American Samoa',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'DC' => 'District of Columbia',
        'FM' => 'Federated States of Micronesia',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'GU' => 'Guam',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MH' => 'Marshall Islands',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'MP' => 'Northern Mariana Islands',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PW' => 'Palau',
        'PA' => 'Pennsylvania',
        'PR' => 'Puerto Rico',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VI' => 'Virgin Islands',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',

        'AB' => 'Alberta',
        'BC' => 'British Columbia',
        'MB' => 'Manitoba',
        'NB' => 'New Brunswick',
        'NL' => 'Newfoundland and Labrador',
        'NS' => 'Nova Scotia',
        'NT' => 'Northwest Territories',
        'NU' => 'Nunavut',
        'ON' => 'Ontario',
        'PE' => 'Prince Edward Island',
        'QC' => 'Quebec',
        'SK' => 'Saskatchewan',
        'YT' => 'Yukon',
    ];

    protected $fields;
    protected $registerUrl = 'https://rewards.ritzcarlton.com/ritz/rewards/createAccount/createAccountPage1.mi?segmentId=elite.nonrewards';

    public function registerAccount(array $fields)
    {
        $this->http->Log('>>> ' . __METHOD__);

        $this->fields = $fields;

        $this->checkFields();
        $this->registerInternal();
        $this->checkStatus();

        return true;
    }

    public function getRegisterFields()
    {
        return [
            'Title' => [
                'Type'     => 'string',
                'Caption'  => 'Salutation',
                'Required' => false,
                'Options'  => self::$titles,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name',
                'Required' => true,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'AddressType' => [
                'Type'     => 'string',
                'Caption'  => 'Address Type',
                'Required' => true,
                'Options'  => self::$addressTypes,
            ],
            'AddressLine1' => [
                'Type'     => 'string',
                'Caption'  => 'Address',
                'Required' => true,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'City',
                'Required' => true,
            ],
            'StateOrProvince' => [
                'Type'     => 'string',
                'Caption'  => 'State Or Province (required for US and Canada)',
                'Required' => false,
                'Options'  => self::$states,
            ],
            'PostalCode' => [
                'Type'     => 'string',
                'Caption'  => 'Zip Code (required for US and Canada)',
                'Required' => false,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password (required 8-20 characters, lower case letter, upper case letter, number or special character)',
                'Required' => true,
            ],
        ];
    }

    private function registerInternal()
    {
        $this->http->Log('>>> ' . __METHOD__);

        $this->http->getUrl($this->registerUrl);

        $status = $this->http->parseForm('createAccountActionForm');
        $this->http->log('[INFO] reg form:');
        $this->http->log(print_r($this->http->Form, true));

        if (!$status) {
            $this->http->Log('[INFO] failed to parse register form');

            return false;
        }

        $this->populateForm($this->fields, self::$fieldMap);
        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('[INFO] failed to post register form');

            return false;
        }
    }

    private function checkStatus()
    {
        $this->http->Log('>>> ' . __METHOD__);

        $success = $this->http->findPreg('/Congratulations on your new membership/i');

        if ($success) {
            $acc = $this->http->findSingleNode('//*[contains(@class, "t-account-number")]');
            $msg = sprintf('Successfull registration, your account number is %s', $acc);
            $this->http->log(sprintf('[INFO] %s', $msg));
            $this->ErrorMessage = $msg;

            return true;
        }

        $error = $this->http->findSingleNode('//*[contains(@class, "l-message-box")]/ul');

        if ($error) {
            $this->http->log(sprintf('[ERROR] %s', $error));

            throw new \UserInputError($error); // Is it always user input error?
        }

        throw new \EngineError('Unexpected response on account registration submit');
    }

    private function checkFields()
    {
        $this->http->Log('>>> ' . __METHOD__);

        if (!in_array($this->fields['Country'], array_keys(self::$countries))) {
            throw new \UserInputError('Invalid country code');
        }

        $state = arrayVal($this->fields, 'StateOrProvince');

        if ($state && !in_array($state, array_keys(self::$states))) {
            throw new \UserInputError('Invalid state code');
        }
    }

    private function populateForm($fields, $fieldMap)
    {
        foreach ($fieldMap as $awkey => $keys) {
            if (!isset($fields[$awkey])) {
                continue;
            }

            if (!is_array($keys)) {
                $keys = [$keys];
            }

            foreach ($keys as $k) {
                $this->http->setInputValue($k, $fields[$awkey]);
            }
        }
    }
}
