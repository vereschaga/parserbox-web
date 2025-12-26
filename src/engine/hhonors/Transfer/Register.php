<?php

namespace AwardWallet\Engine\hhonors\Transfer;

class Register extends \TAccountCheckerHhonors
{
    public static $inputFieldsMap = [
        'FirstName'    => 'firstName',
        'LastName'     => 'lastName',
        'Country'      => 'countryCode',
        'AddressLine1' => 'street1',
        //		'AddressLine2' => 'street2',
        'City'            => 'city',
        'StateOrProvince' => 'state',
        'PostalCode'      => 'postalCode',
        'Email'           => 'email',
        'Password'        => [
            'password',
            'confirmPassword',
        ],
        'PhoneCountryCodeNumeric' => false,
        'PhoneAreaCode'           => false,
        'PhoneLocalNumber'        => false,
        'PreferredLanguage'       => 'preferredLanguage',
        'ReceiveThirdPartyEmails' => 'thirdParty',
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
        'CG' => 'Congo',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'HR' => 'Croatia',
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
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'LC' => 'Saint Lucia',
        'SM' => 'San Marino',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
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
        'PM' => 'St Pierre & Miquelon',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
        'TW' => 'Taiwan',
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
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'IO' => 'BR Indian Ocean Territories',
        'KM' => 'Comoro Islands',
        'CD' => 'Congo, Dem. Republic',
        'HM' => 'Heard & McDonald Islands',
        'KP' => 'Korea, Dem. Peoples',
        'PN' => 'Pitcairn Island',
        'WS' => 'Samoa, Ind. State of',
        'ST' => 'Sao Tome-Principe',
        'GS' => 'S Georgia-S Sandwich',
        'SH' => 'St Helena',
        'KN' => 'St Kitts-Nevis',
        'VC' => 'St Vincent & The Grenadines',
        'SJ' => 'Svalbard & Jan Mayen',
        'TJ' => 'Tajikstan',
        'TC' => 'Turks & Caicos',
        'VI' => 'Virgin Islands (United States)',
        'WF' => 'Wallis & Futuna',
    ];

    public static $states = [
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'DC' => 'District of Columbia',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
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
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
        'AA' => 'AA(Armed Forces)',
        'AE' => 'AE (Area Europe)',
        'AP' => 'AP (Area Pacific)',
        'AS' => 'American Samoa',
        'GU' => 'Guam',
        'MH' => 'Marshall Islands',
        'MP' => 'Northern Mariana Islands',
        'PW' => 'Palau',
        'PR' => 'Puerto Rico',
        'VI' => 'Virgin Islands',
    ];

    public static $languages = [
        'en'      => 'English',
        'ar'      => 'Arabic',
        'zh-Hans' => 'Chinese (Simplified)',
        'fr'      => 'French',
        'de'      => 'German',
        'it'      => 'Italian',
        'ja'      => 'Japanese',
        'ko'      => 'Korean',
        'pt'      => 'Portuguese',
        'ru'      => 'Russian',
        'es'      => 'Spanish',
        'tr'      => 'Turkish',
    ];

    protected $languageMap = [
        'en'      => 'en_US',
        'ar'      => 'ar_AE',
        'zh-Hans' => 'zh_CN',
        'fr'      => 'fr_FR',
        'de'      => 'de_DE',
        'it'      => 'it_IT',
        'ja'      => 'ja_JP',
        'ko'      => 'ko_KR',
        'pt'      => 'pt_BR',
        'ru'      => 'ru_RU',
        'es'      => 'es_XM',
        'tr'      => 'tr_TR',
    ];

    public function registerAccount(array $fields)
    {
        $this->http->removeCookies();
        $this->logger->notice('Load for create sessoin...');
        $this->http->GetURL('https://secure3.hilton.com/en_US/hh/error/sessionexpired.htm');
        $this->delay();

        $this->http->setDefaultHeader('Accept-Encoding', 'gzip');
        $fields['PreferredLanguage'] = $this->languageMap[$fields['PreferredLanguage']];
        $this->logger->notice('Load registration page...');
        $this->http->GetURL('https://secure3.hilton.com/en/hh/customer/join/joinHHonors.htm');
        $this->delay();

        $status = $this->http->ParseForm('enrollForm');

        if (!$status) {
            $this->http->Log('Failed to parse create account form');

            return false;
        }

        $this->http->Log('Registration form:');
        $this->http->Log(print_r($fields, true));

        $fields['userAgreement'] = 'true';

        foreach (['ReceiveThirdPartyEmails'] as $key) {
            if (isset($fields[$key])) {
                if ($fields[$key]) {
                    $fields[$key] = 'true';
                } else {
                    unset($fields[$key]);
                }
            }
        }

        foreach (self::$inputFieldsMap as $awKey => $hiltonKeys) {
            if (isset($fields[$awKey]) and $hiltonKeys !== false) {
                if (!is_array($hiltonKeys)) {
                    $hiltonKeys = [$hiltonKeys];
                }

                foreach ($hiltonKeys as $hiltonKey) {
                    $this->http->SetInputValue($hiltonKey, $fields[$awKey]);
                }
            }
        }
        $phone = '+' . $fields['PhoneCountryCodeNumeric'] . $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];
        $this->http->SetInputValue('phone', $phone);

        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post create account form');

            return false;
        }
        $body = $this->http->Response['body'];
        $this->http->SetBody($body);
        $err = $this->http->FindSingleNode('//div[contains(@class, "errorListing")]');

        if ($err) {
            $this->http->Log($err);

            throw new \UserInputError($err); // Is it always user input error?
        }

        if ($this->http->FindSingleNode('//h1[contains(., "You\'re in") and (contains(., "Great to have you") or contains(.,"start enjoying instant benefits"))]')) {
            $number = $this->http->FindPreg('/(?:HHonors|Hilton\s+Honors)\s+Member\s+Number:.*?(\d+)/isu');
            $msg = "registration successful, hhonors number: $number";
            $this->http->Log($msg);
            $this->ErrorMessage = $msg;

            return true;
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password',
                'Required' => true,
            ],
            'ReceiveThirdPartyEmails' => [
                'Type'     => 'boolean',
                'Caption'  => 'I do not wish to receive information from select third parties about complementary products and services.',
                'Required' => false,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Required' => true,
                'Caption'  => 'First Name',
            ],
            'LastName' => [
                'Type'     => 'string',
                'Required' => true,
                'Caption'  => 'Last Name',
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country code',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'AddressLine1' => [
                'Type'     => 'string',
                'Caption'  => 'Address Line 1',
                'Required' => true,
            ],
            //			'AddressLine2' => [
            //				'Type' => 'string',
            //				'Caption' => 'Address Line 2',
            //				'Required' => false,
            //			],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'City',
                'Required' => true,
            ],
            'StateOrProvince' => [
                'Type'     => 'string',
                'Caption'  => 'State/Province (required for USA)',
                'Required' => false,
            ],
            'PostalCode' => [
                'Type'    => 'string',
                'Caption' => 'Zip code/Postal code',
            ],
            'PhoneCountryCodeNumeric' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Country Code (numeric)',
                'Required' => false,
            ],
            'PhoneAreaCode' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Area Code',
                'Required' => false,
            ],
            'PhoneLocalNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Local Number',
                'Required' => false,
            ],
            'PreferredLanguage' => [
                'Type'     => 'string',
                'Caption'  => 'Preferred Language',
                'Required' => true,
                'Options'  => self::$languages,
            ],
        ];
    }

    // fake creds:
    // John Johnson
    // tufe@cheaphub.net
    // j8jasd8JSJAD
    // 590833946
}
