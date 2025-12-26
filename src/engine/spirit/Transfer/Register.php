<?php

namespace AwardWallet\Engine\spirit\Transfer;

class Register extends \TAccountChecker
{
    public static $countries = [
        'US' => 'United States of America',
        'AF' => 'Afghanistan',
        'AX' => 'Aland Islands',
        'AL' => 'Albania, Republic of',
        'DZ' => 'Algeria, People\'s Democratic Republic of',
        'AS' => 'American Samoa',
        'AD' => 'Andorra, Principality of',
        'AO' => 'Angola, Republic of',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica (the territory South of 60 deg S)',
        'AG' => 'Antigua and Barbuda',
        'AR' => 'Argentina, Argentine Republic',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia, Commonwealth of',
        'AT' => 'Austria, Republic of',
        'AZ' => 'Azerbaijan, Republic of',
        'BS' => 'Bahamas, Commonwealth of the',
        'BH' => 'Bahrain, Kingdom of',
        'BD' => 'Bangladesh, People\'s Republic of',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium, Kingdom of',
        'BZ' => 'Belize',
        'BJ' => 'Benin, Republic of',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan, Kingdom of',
        'BO' => 'Bolivia, Plurinational State of',
        'BA' => 'Bosnia and Herzegovina',
        'BW' => 'Botswana, Republic of',
        'BV' => 'Bouvet Island (Bouvetoya)',
        'BR' => 'Brazil, Federative Republic of',
        'IO' => 'British Indian Ocean Territory (Chagos Archipelago)',
        'VG' => 'British Virgin Islands',
        'BN' => 'Brunei Darussalam',
        'BG' => 'Bulgaria, Republic of',
        'BF' => 'Burkina Faso (was Upper Volta)',
        'BI' => 'Burundi, Republic of',
        'KH' => 'Cambodia, Kingdom of',
        'CM' => 'Cameroon, United Republic of',
        'CA' => 'Canada',
        'CV' => 'Cape Verde, Republic of',
        'KY' => 'Cayman Islands',
        'CF' => 'Central African Republic',
        'TD' => 'Chad, Republic of',
        'CL' => 'Chile, Republic of',
        'CN' => 'China, People\'s Republic of',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (Keeling) Islands',
        'CO' => 'Colombia, Republic of',
        'KM' => 'Comoros, Union of the',
        'CD' => 'Congo, Democratic Republic of (was Zaire)',
        'CG' => 'Congo, People\'s Republic of',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica, Republic of',
        'CI' => 'Cote D\'Ivoire, Ivory Coast, Republic of the',
        'HR' => 'Croatia, Republic of',
        'CU' => 'Cuba, Republic of',
        'CY' => 'Cyprus, Republic of',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark, Kingdom of',
        'DJ' => 'Djibouti, Republic of',
        'DM' => 'Dominica, Commonwealth of',
        'DO' => 'Dominican Republic',
        'EC' => 'Ecuador, Republic of',
        'EG' => 'Egypt, Arab Republic of',
        'SV' => 'El Salvador, Republic of',
        'GQ' => 'Equatorial Guinea, Republic of',
        'ER' => 'Eritrea, State of',
        'EE' => 'Estonia, Republic of',
        'ET' => 'Ethiopia, Federal Democratic Republic of',
        'FO' => 'Faeroe Islands',
        'FK' => 'Falkland Islands (Malvinas)',
        'FJ' => 'Fiji, Republic of the Fiji Islands',
        'FI' => 'Finland, Republic of',
        'FR' => 'France, French Republic',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
        'TF' => 'French Southern Territories',
        'GA' => 'Gabon, Gabonese Republic',
        'GM' => 'Gambia, Republic of the',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana, Republic of',
        'GI' => 'Gibraltar',
        'GR' => 'Greece, Hellenic Republic',
        'GL' => 'Greenland',
        'GD' => 'Grenada',
        'GP' => 'Guadeloupe',
        'GU' => 'Guam',
        'GT' => 'Guatemala, Republic of',
        'GN' => 'Guinea, Republic of',
        'GW' => 'Guinea-Bissau, Republic of',
        'GY' => 'Guyana, Co-operative Republic of',
        'HT' => 'Haiti, Republic of',
        'HM' => 'Heard and McDonald Islands',
        'HN' => 'Honduras, Republic of',
        'HK' => 'Hong Kong, Special Administrative Region of China',
        'HU' => 'Hungary, Republic of',
        'IS' => 'Iceland',
        'IN' => 'India, Republic of',
        'ID' => 'Indonesia, Republic of',
        'IR' => 'Iran, Islamic Republic of',
        'IQ' => 'Iraq, Republic of',
        'IE' => 'Ireland',
        'IL' => 'Israel, State of',
        'IT' => 'Italy, Italian Republic',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JO' => 'Jordan, Hashemite Kingdom of',
        'KZ' => 'Kazakhstan, Republic of',
        'KE' => 'Kenya, Republic of',
        'KI' => 'Kiribati, Republic of',
        'KP' => 'Korea, Democratic People\'s Republic of',
        'KR' => 'Korea, Republic of',
        'KW' => 'Kuwait, State of',
        'KG' => 'Kyrgyz',
        'LA' => 'Laos, People\'s Democratic Republic of',
        'LV' => 'Latvia, Republic of',
        'LB' => 'Lebanon, Republic of',
        'LS' => 'Lesotho, Kingdom of',
        'LR' => 'Liberia, Republic of',
        'LY' => 'Libyan Arab Jamahiriya, Great Socialist People\'s',
        'LI' => 'Liechtenstein, Principality of',
        'LT' => 'Lithuania, Republic of',
        'LU' => 'Luxembourg, Grand Duchy of',
        'MO' => 'Macao, Special Administrative Region of China',
        'MK' => 'Macedonia, Republic of',
        'MG' => 'Madagascar, Republic of',
        'MW' => 'Malawi, Republic of',
        'MY' => 'Malaysia',
        'MV' => 'Maldives, Republic of',
        'ML' => 'Mali, Republic of',
        'MT' => 'Malta, Republic of',
        'MH' => 'Marshall Islands, Republic of the',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania, Islamic Republic of',
        'MU' => 'Mauritius, Republic of',
        'YT' => 'Mayotte, Departmental Collectivity of',
        'MX' => 'Mexico',
        'FM' => 'Micronesia, Federated States of',
        'MD' => 'Moldova, Republic of',
        'MC' => 'Monaco, Principality of',
        'MN' => 'Mongolia',
        'ME' => 'Montenegro',
        'MS' => 'Montserrat',
        'MA' => 'Morocco, Kingdom of',
        'MZ' => 'Mozambique, Republic of',
        'MM' => 'Myanmar, Union of',
        'NA' => 'Namibia, Republic of',
        'NR' => 'Nauru, Republic of',
        'NP' => 'Nepal, Federal Democratic Republic of',
        'AN' => 'Netherlands Antilles',
        'NL' => 'Netherlands, Kingdom of the',
        'NC' => 'New Caledonia',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua, Republic of',
        'NE' => 'Niger, Republic of',
        'NG' => 'Nigeria, Federal Republic of',
        'NU' => 'Niue, Republic of',
        'NF' => 'Norfolk Island',
        'MP' => 'Northern Mariana Islands, Commonwealth of the',
        'NO' => 'Norway, Kingdom of',
        'OM' => 'Oman, Sultanate of',
        'PK' => 'Pakistan, Islamic Republic of',
        'PW' => 'Palau, Republic of',
        'PS' => 'Palestine, State of',
        'PA' => 'Panama, Republic of',
        'PG' => 'Papua New Guinea, Independent State of',
        'PY' => 'Paraguay, Republic of',
        'PE' => 'Peru, Republic of',
        'PH' => 'Philippines, Republic of the',
        'PN' => 'Pitcairn Island',
        'PL' => 'Poland, Republic of',
        'PT' => 'Portugal, Portuguese Republic',
        'PR' => 'Puerto Rico, Commonwealth of',
        'QA' => 'Qatar, State of',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda, Rwandese Republic',
        'BL' => 'Saint Barthelemy',
        'MF' => 'Saint Martin',
        'WS' => 'Samoa, Independent State of',
        'SM' => 'San Marino, Republic of',
        'ST' => 'Sao Tome and Principe, Democratic Republic of',
        'SA' => 'Saudi Arabia, Kingdom of',
        'SN' => 'Senegal, Republic of',
        'RS' => 'Serbia, Republic of',
        'CS' => 'Serbia and Montenegro',
        'SC' => 'Seychelles, Republic of',
        'SL' => 'Sierra Leone, Republic of',
        'SG' => 'Singapore, Republic of',
        'SK' => 'Slovakia (Slovak Republic)',
        'SI' => 'Slovenia, Republic of',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia, Somali Republic',
        'ZA' => 'South Africa, Republic of',
        'GS' => 'South Georgia and the South Sandwich Islands',
        'ES' => 'Spain, Kingdom of',
        'LK' => 'Sri Lanka, Democratic Socialist Republic of',
        'SH' => 'St. Helena',
        'KN' => 'St. Kitts and Nevis',
        'LC' => 'St. Lucia',
        'PM' => 'St. Pierre and Miquelon, Territorial Collectivity of',
        'VC' => 'St. Vincent and the Grenadines',
        'SD' => 'Sudan, Republic of the',
        'SR' => 'Suriname, Republic of',
        'SJ' => 'Svalbard &amp; Jan Mayen Islands',
        'SZ' => 'Swaziland, Kingdom of',
        'SE' => 'Sweden, Kingdom of',
        'CH' => 'Switzerland, Swiss Confederation',
        'SY' => 'Syrian Arab Republic',
        'TW' => 'Taiwan (Republic of China)',
        'TJ' => 'Tajikistan, Republic of',
        'TZ' => 'Tanzania, United Republic of',
        'TH' => 'Thailand, Kingdom of',
        'TL' => 'Timor-Leste, Democratic Republic of',
        'TG' => 'Togo, Togolese Republic',
        'TK' => 'Tokelau (Tokelau Islands)',
        'TO' => 'Tonga, Kingdom of',
        'TT' => 'Trinidad and Tobago, Republic of',
        'TN' => 'Tunisia (Tunisian Republic)',
        'TR' => 'Turkey, Republic of',
        'TM' => 'Turkmenistan, Republic of',
        'TC' => 'Turks and Caicos Islands',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda, Republic of',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates (was Trucial States)',
        'GB' => 'United Kingdom of Great Britain &amp; N. Ireland',
        'UM' => 'United States Minor Outlying Islands',
        'UY' => 'Uruguay, Oriental Republic of',
        'VI' => 'US Virgin Islands',
        'UZ' => 'Uzbekistan, Republic of',
        'VU' => 'Vanuatu, Republic of',
        'VA' => 'Vatican City State (Holy See)',
        'VE' => 'Venezuela, Bolivarian Republic of',
        'VN' => 'Vietnam, Socialist Republic of',
        'WF' => 'Wallis and Futuna Islands, Territory of the',
        'EH' => 'Western Sahara (was Spanish Sahara)',
        'YE' => 'Yemen, Republic of',
        'ZM' => 'Zambia, Republic of',
        'ZW' => 'Zimbabwe, Republic of',
        'BQ' => 'Bonaire, Saint Eustatius and Saba',
        'CW' => 'Curacao',
        'GG' => 'Guernsey',
        'IM' => 'Isle Of Man',
        'JE' => 'Jersey',
        'KV' => 'Kosovo, Republic of',
        'RK' => 'Kosovo, Republic of',
        'SS' => 'South Sudan',
        'SX' => 'Sint Maarten',
    ];
    public static $states = [
        'AK' => 'Alaska',
        'AL' => 'Alabama',
        'AR' => 'Arkansas',
        'AZ' => 'Arizona',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DC' => 'District of Columbia',
        'DE' => 'Delaware',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'IA' => 'Iowa',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'MA' => 'Massachusetts',
        'MD' => 'Maryland',
        'ME' => 'Maine',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MO' => 'Missouri',
        'MS' => 'Mississippi',
        'MT' => 'Montana',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'NE' => 'Nebraska',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NV' => 'Nevada',
        'NY' => 'New York',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'PR' => 'Puerto Rico',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VA' => 'Virginia',
        'VT' => 'Vermont',
        'WA' => 'Washington',
        'WI' => 'Wisconsin',
        'WV' => 'West Virginia',
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
    public static $airports = [
        'BQN' => 'Aguadilla, Puerto Rico',
        'AXM' => 'Armenia, Colombia',
        'AUA' => 'Aruba, Aruba',
        'ATL' => 'Atlanta, GA',
        'ACY' => 'Atlantic City, NJ ',
        'BWI' => 'Baltimore, MD / Washington, DC AREA',
        'BOG' => 'Bogota, Colombia',
        'BOS' => 'Boston, MA',
        'CUN' => 'Cancun, Mexico',
        'CTG' => 'Cartagena, Colombia',
        'CRW' => 'Charleston, WV',
        'ORD' => 'Chicago, IL - O\'Hare ',
        'CLE' => 'Cleveland, OH',
        'DFW' => 'Dallas/Ft. Worth, TX ',
        'DEN' => 'Denver, CO',
        'DTW' => 'Detroit, MI',
        'FLL' => 'Fort Lauderdale, FL / Miami, FL AREA ',
        'RSW' => 'Fort Myers, FL',
        'GUA' => 'Guatemala City, Guatemala',
        'IAH' => 'Houston, TX - Intercontinental',
        'MCI' => 'Kansas City, MO',
        'KIN' => 'Kingston, Jamaica',
        'LAS' => 'Las Vegas, NV',
        'LBE' => 'Latrobe, PA / Pittsburgh, PA AREA ',
        'LIM' => 'Lima, Peru',
        'LAX' => 'Los Angeles, CA',
        'SJD' => 'Los Cabos, Mexico',
        'MGA' => 'Managua, Nicaragua',
        'MDE' => 'Medellin, Colombia',
        'MSP' => 'Minneapolis/St. Paul, MN',
        'MBJ' => 'Montego Bay, Jamaica',
        'MYR' => 'Myrtle Beach, SC',
        'MSY' => 'New Orleans, LA',
        'LGA' => 'New York, NY - LaGuardia ',
        'IAG' => 'Niagara Falls, NY / Toronto, Canada AREA',
        'OAK' => 'Oakland, CA / San Francisco, CA AREA',
        'MCO' => 'Orlando, FL',
        'PTY' => 'Panama City, Panama',
        'PHL' => 'Philadelphia, PA',
        'PHX' => 'Phoenix/Sky Harbor, AZ',
        'PBG' => 'Plattsburgh, NY / Montreal, Canada AREA ',
        'PAP' => 'Port-au-Prince, Haiti',
        'PDX' => 'Portland, OR',
        'PUJ' => 'Punta Cana, Dominican Republic',
        'SAN' => 'San Diego, CA',
        'SJO' => 'San Jose, Costa Rica',
        'SJU' => 'San Juan, Puerto Rico',
        'SAP' => 'San Pedro Sula, Honduras',
        'SAL' => 'San Salvador, El Salvador',
        'STI' => 'Santiago, Dominican Republic',
        'SDQ' => 'Santo Domingo, Dominican Republic',
        'SEA' => 'Seattle-Tacoma, WA',
        'SXM' => 'St. Maarten, St. Maarten',
        'STT' => 'St. Thomas, U.S. Virgin Islands',
        'TPA' => 'Tampa, FL',
        'TLC' => 'Toluca, Mexico / Mexico City, Mexico AREA ',
        'PBI' => 'West Palm Beach, FL',
    ];
    public static $inputFieldsMap = [
        'Title'           => 'DDLTitle',
        'FirstName'       => 'TextBoxFirstName',
        'LastName'        => 'TextBoxLastName',
        'BirthDay'        => 'DropDownListBirthDateDay',
        'BirthMonth'      => 'DropDownListBirthDateMonth',
        'BirthYear'       => 'DropDownListBirthDateYear',
        'Email'           => ['TextBoxEmailAddress', 'TextBoxConfirmEmail'],
        'Password'        => ['TextBoxPassword', 'TextBoxConfirmPassword'],
        'Country'         => 'DDLCountry',
        'AddressLine1'    => 'TextBoxAddress1',
        'City'            => 'TextBoxCity',
        'StateOrProvince' => 'DDLState',
        'PostalCode'      => 'TextBoxZipcode',
        //		'PhoneCountryCodeNumeric' => '',
        //		'PhoneAreaCode' => '',
        'PhoneLocalNumber' => 'TextBoxHomePhone',
        'HomeAirport'      => 'ddHomeAirport',
    ];

    public function InitBrowser()
    {
        $this->UseCurlBrowser();
    }

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;

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

        if (!preg_match('/^[a-zA-Z0-9]{6,12}$/i', $fields['Password'])) {
            throw new \UserInputError('Password must be 6-12 characters long and contain no special characters');
        }

        $this->http->GetURL('https://www.spirit.com/FreeSpiritEnrollment.aspx');

        $fields['PhoneLocalNumber'] = $fields['PhoneCountryCodeNumeric'] . $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];

        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($fields[$awKey]) or $provKeys === false) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $this->http->SetInputValue('FreeSpiritEnrollmentGroupControl$FreeSpiritEnrollmentInputControl$' . $provKey, $fields[$awKey]);
            }
        }

        $viewState = $this->http->FindPreg('/id\s*=\s*"__VIEWSTATE"\s+value\s*=\s*"([^"]+)"/ims');
        $addPrefix = 'FreeSpiritEnrollmentGroupControl$FreeSpiritEnrollmentEMailNotifyControl$';
        $additional = [
            '__EVENTTARGET'                   => 'FreeSpiritEnrollmentGroupControl$LinkButtonSubmit',
            '__EVENTARGUMENT'                 => '',
            '__VIEWSTATE'                     => $viewState,
            $addPrefix . 'txtFirstName'       => $fields['FirstName'],
            $addPrefix . 'txtLastName'        => $fields['LastName'],
            $addPrefix . 'txtEmail'           => $fields['Email'],
            $addPrefix . 'txtConfirmEmail'    => $fields['Email'],
            $addPrefix . 'ddHomeAirport'      => $fields['HomeAirport'],
            $addPrefix . 'ddSecondaryAirport' => $fields['HomeAirport'],
            $addPrefix . 'chkSpecialOffer'    => 'on',
        ];

        foreach ($additional as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }

        if (!$this->http->PostForm()) {
            throw new \EngineError('Can not post register form');
        }

        if ($successMessage = $this->http->FindPreg("/You\s+have\s+successfully\s+registered/ims")) {
            $this->ErrorMessage = 'You have successfully registered and can now start earning miles for future award travel';
            $this->http->Log($this->ErrorMessage);

            return true;
        }

        if ($errorMessage = $this->http->FindPreg('/AccountInfoValidator.displayBubble\([^\,]+\,"([^"]+)"\)/ims')) {
            throw new \UserInputError($errorMessage);
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Title' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Title',
                'Required' => true,
                'Options'  =>
                [
                    'MR'  => 'Mr.',
                    'MRS' => 'Mrs.',
                    'MS'  => 'Ms.',
                ],
            ],
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
            'Email' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Password' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Password (6-12 characters)',
                'Required' => true,
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
                'Caption'  => 'City',
                'Required' => true,
            ],
            'StateOrProvince' =>
            [
                'Type'     => 'string',
                'Caption'  => 'State/Province (required for US and Canada)',
                'Required' => false,
                'Options'  => self::$states,
            ],
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Zip/Posta Code (required for US and Canada)',
                'Required' => false,
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
            'HomeAirport' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Home Airport',
                'Required' => true,
                'Options'  => self::$airports,
            ],
        ];
    }
}
