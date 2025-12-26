<?php

namespace AwardWallet\Engine\korean\Transfer;

class Register extends \TAccountChecker
{
    public static $inputFieldsMap = [
        'FirstName'               => 'firstName',
        'LastName'                => 'lastName',
        'BirthDay'                => 'dateOfBirthDay',
        'BirthMonth'              => 'dateOfBirthMonth',
        'BirthYear'               => 'dateOfBirthYear',
        'Gender'                  => 'gender',
        'Email'                   => 'email',
        'AddressType'             => 'addressType',
        'Country'                 => 'residenceCountry',
        'AddressLine1'            => 'addressLine1',
        'City'                    => 'city',
        'StateOrProvince'         => '',
        'PostalCode'              => 'postalCode',
        'PhoneCountryCodeNumeric' => 'representCountryNumberCode',
        'PhoneLocalNumber'        => 'representPhoneNumber',
        'Username'                => 'userId',
        'Password'                => 'password',
    ];
    public static $countries = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua and Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas (the)',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BZ' => 'Belize',
        'BJ' => 'Benin',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia, Plurinational State of',
        'BQ' => 'Bonaire, Sint Eustatius and Saba',
        'BA' => 'Bosnia and Herzegovina',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Territory (the)',
        'BN' => 'Brunei Darussalam',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'CV' => 'Cabo Verde',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'KY' => 'Cayman Islands (the)',
        'CF' => 'Central African Republic (the)',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (Keeling) Islands (the)',
        'CO' => 'Columbia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CS' => 'Congo (the Democratic Republic of the)',
        'CK' => 'Cook Islands (the)',
        'CR' => 'Costa Rica',
        'CI' => 'Cote d\'lvoire',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CW' => 'Curacao',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic (the)',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic (the)',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands (the) [Malvinas]',
        'FO' => 'Faroe Islands (the)',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
        'TF' => 'French Southern Territories (the)',
        'GA' => 'Gabon',
        'GM' => 'Gambia (The)',
        'GP' => 'Gaudeloupe',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GR' => 'Greece',
        'GL' => 'Greenland',
        'GD' => 'Grenada',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GG' => 'Guernsey',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard Island and McDonald Islands',
        'VA' => 'Holy See (the) [Vatican City State]',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran (the Islamic Republic of)',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IM' => 'Isle of Man',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JE' => 'Jersey',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KR' => 'Korea (the Republic of)',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao People\'s Democratic Republic (the)',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macao',
        'MK' => 'Macedonia (the former Yugoslav Republic of)',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands (the)',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia (the Federated States of)',
        'MD' => 'Moldova (the Republic of)',
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
        'NL' => 'Netherlands (the)',
        'NC' => 'New Caledonia',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger (the)',
        'NG' => 'Nigeria',
        'NU' => 'Niue',
        'NF' => 'Norfolk Island',
        'MP' => 'Northern Mariana Islands (the)',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PS' => 'Palestine, State of',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines (the)',
        'PN' => 'Pitcairn',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russian Federation (the)',
        'RW' => 'Rwanda',
        'BL' => 'Saint Barthelemy',
        'SH' => 'Saint Helena, Ascension and Tristan da Cunha',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'MF' => 'Saint Martin (French part)',
        'PM' => 'Saint Pierre and Miquelon',
        'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SX' => 'Sint Maarten (Dutch part)',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands (the)',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'GS' => 'South Georgia and the South Sandwich Islands',
        'SS' => 'South Sudan',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan (the)',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic (the)',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania, United Republic of',
        'TH' => 'Thailand',
        'TL' => 'Timor-Leste',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks and Caicos Islands (the)',
        'TV' => 'Tuvalu',
        'US' => 'USA, United States of America',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates (the)',
        'GB' => 'United Kingdom (the)',
        'UM' => 'United States Minor Outlying Islands (the)',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VE' => 'Venezuela, Bolivarian Republic of',
        'VN' => 'Viet Nam',
        'VG' => 'Virgin Islands (British)',
        'VI' => 'Virgin Islands (U.S.)',
        'WF' => 'Wallis and Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
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
        'FM' => 'Federated State of Micronesia',
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
        'LA' => 'Louisianna',
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
        'AA' => 'U.S. Armed Forces - Americas',
        'AE' => 'U.S. Armed Forces - Europe',
        'AP' => 'U.S. Armed Forces - Pacific',
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
        'NT' => 'Northwest Territories',
        'NS' => 'Nova Scotia',
        'NU' => 'Nunavut',
        'ON' => 'Ontario',
        'PE' => 'Prince Edward Island',
        'QC' => 'Quebec',
        'SK' => 'Saskatchewan',
        'YT' => 'Yukon',
    ];

    public function InitBrowser()
    {
        $this->UseCurlBrowser();
    }

    public function registerAccount(array $fields)
    {
        if (!preg_match('/^[a-zA-Z0-9]{6,12}$/i', $fields['Username'])) {
            throw new \UserInputError('Username must be a combination of 6~12 English letters and numerals with no spaces and special symbols');
        }

        if (mb_strlen($fields['Password']) < 8 || mb_strlen($fields['Password']) > 20 || $fields['Password'] === $fields['Username']) {
            throw new \UserInputError('Password (must be eight to twenty characters long, may not be the same as the Username)');
        }

        if (strpos($fields['Email'], '+') !== false) {
            throw new \UserInputError('The e-mail format entered is invalid');
        }

        if (in_array($fields['Country'], ['US', 'CA'])) {
            if (!isset($fields['StateOrProvince'])) {
                throw new \UserInputError('State is required for US and CA');
            }

            if (!array_key_exists($fields['StateOrProvince'], self::$states)) {
                throw new \UserInputError('Unavailable State for US and CA');
            }

            if (!isset($fields['PostalCode'])) {
                throw new \UserInputError('PostalCode is required for US and CA');
            }
        }

        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;
        $this->http->removeCookies();
        $this->http->GetURL("https://www.koreanair.com/global/en.html");

        //------ check values
        $this->http->setDefaultHeader('Content-Type', 'application/json; charset=utf-8');

        $this->http->PostURL( //username
            'https://www.koreanair.com/api/skypass/checkduplicateid',
            json_encode(['userId'=>strtoupper($fields['Username'])])
        );
        $response = $this->http->JsonLog();

        if (isset($response->userId)) {
            throw new \UserInputError('The ID already exists. Please enter another Username.');
        }

        if (in_array($fields['Country'], ['US', 'CA'])) {
            $this->http->PostURL( //postalcode
                'https://www.koreanair.com/api/skypass/validatePostalCode',
                json_encode([
                    'country'    => $fields['Country'],
                    'province'   => $fields['StateOrProvince'],
                    'postalCode' => $fields['PostalCode'],
                ])
            );
            $response = $this->http->JsonLog();

            if (!isset($response->postalCode)) {
                throw new \UserInputError('Invalid PostalCode');
            }
        }

        //------ end check values

        $birthDate = $fields['BirthYear'] . '-' .
            \DateTime::createFromFormat('!m', intval($fields['BirthMonth']))->format('m') . '-' .
            \DateTime::createFromFormat('!m', intval($fields['BirthDay']))->format('d');

        $fields['PhoneLocalNumber'] = $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];
        $fields['Gender'] = $fields['Gender'] === 'M' ? 'MALE' : 'FEMALE';

        $regData = [
            "skypassNumber"          => "",
            "phoneNoCountryCode"     => "",
            "phoneNo"                => "",
            "addressType"            => "H",
            "addressLine2"           => " ",
            "addrLang"               => "EN",
            "termsOfUse"             => "Y",
            "privacyPolicy"          => "Y",
            "memberTermsAgreeYn"     => "Y",
            "cyberSkyShopEnrollYn"   => "N",
            "emailIsoL3LanguageCode" => "EN",
            "sendMailTo"             => "H",
            "address"                => [[
                "addressLine1"    => $fields['AddressLine1'],
                "addressLine2"    => "",
                "addressLanguage" => "EN",
                "addressType"     => "H",
                "city"            => $fields['City'],
                "province"        => $fields['StateOrProvince'] ?? '',
                "postalCode"      => $fields['PostalCode'],
                "countryCode"     => $fields['Country'],
            ]],
            "newsEmailDmYn"            => "N",
            "promoEmailDmYn"           => "N",
            "smsYn"                    => "N",
            "dateOfBirth"              => $birthDate,
            "securityQuestion"         => "A",
            "securityResponse"         => "undefined",
            "thirdPartyInfoOfferAgrYn" => "N",
            "currentSite"              => "https://www.koreanair.com/global/en",
            "homeNo"                   => "",
            "homeNoCountryCode"        => "",
            "enrollLang"               => "en",
            "enrollCountry"            => "us",
            "enrollDate"               => date('Ymd'),
        ];

        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($fields[$awKey]) or $provKeys === false) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $regData[$provKey] = $fields[$awKey] . '';
            }
        }

        $this->http->PostURL('https://www.koreanair.com/api/skypass/createMember', json_encode($regData));

        $response = $this->http->JsonLog();

        if (isset($response->skypassNumber)) {
            $this->ErrorMessage = "SKYPASS No.: {$response->skypassNumber}; Thank you for joining SKYPASS.";
            $this->http->Log($this->ErrorMessage);

            return true;
        }

        if (isset($response->message)) {
            throw new \UserInputError($response->message);
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'FirstName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'First Name',
                'Required' => true,
            ],
            'LastName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Last Name',
                'Required' => true,
            ],
            'BirthDay' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Day of Birth Date',
                'Required' => true,
            ],
            'BirthMonth' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Month of Birth Date',
                'Required' => true,
            ],
            'BirthYear' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Year of Birth Date ',
                'Required' => true,
            ],
            'Gender' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  =>
                [
                    'M' => 'Male',
                    'F' => 'Female',
                ],
            ],
            'Email' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'AddressType' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Address Type',
                'Required' => true,
                'Options'  =>
                [
                    'H' => 'Home',
                    'B' => 'Business',
                ],
            ],
            'Country' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'AddressLine1' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Address Line',
                'Required' => true,
            ],
            'City' =>
            [
                'Type'     => 'string',
                'Caption'  => 'City (required for US and Canada)',
                'Required' => false,
            ],
            'StateOrProvince' =>
            [
                'Type'     => 'string',
                'Caption'  => 'State (required for US and Canada)',
                'Required' => false,
                'Options'  => self::$states,
            ],
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Postal Code',
                'Required' => true,
            ],
            'PhoneCountryCodeNumeric' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Phone Country Code',
                'Required' => true,
            ],
            'PhoneAreaCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Phone Area Code',
                'Required' => true,
            ],
            'PhoneLocalNumber' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Phone Number',
                'Required' => true,
            ],
            'Username' =>
            [
                'Type'     => 'string',
                'Caption'  => 'User ID (please enter a combination of 6~12 English letters and numerals)',
                'Required' => true,
            ],
            'Password' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Password (must be eight to twenty characters long, may not be the same as the user\'s ID)',
                'Required' => true,
            ],
        ];
    }
}
