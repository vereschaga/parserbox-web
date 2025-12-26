<?php

// case #10639

namespace AwardWallet\Engine\jetblue\Transfer;

class Register extends \TAccountCheckerJetblue
{
    public static $fieldMap1 = [
        'Email' => [
            'email',
            'confirmEmail',
        ],
        'Password' => [
            'password',
            'confirmPassword',
        ],
    ];

    public static $fieldMap3 = [
        'AddressLine1'    => 'addressLine1',
        'AddressLine2'    => 'addressLine2',
        'Country'         => 'countryCode',
        'PostalCode'      => 'zipCode',
        'City'            => 'city',
        'StateOrProvince' => 'stateCode',
        'Phone'           => 'primaryPhone',
        'PhoneType'       => 'phoneType',
    ];

    public static $fieldMap2 = [
        'Title'         => 'title',
        'FirstName'     => 'fName',
        'MiddleInitial' => 'mName',
        'LastName'      => 'lName',
        'Suffix'        => 'suffix',
        'Gender'        => 'gender',

        'BirthMonth'           => 'dobMonth',
        'BirthDay'             => 'dobDay',
        'BirthYear'            => 'dobYear',
        'HomeAirport'          => 'homeAir',
        'FavoriteDestination1' => 'favDest',
        'KnownTravelerNumber'  => 'ktn',
        'EnrollmentCode'       => 'enrollCode',
    ];

    public static $fieldMap4 = [
        'SecurityQuestionType1'   => 'accountData.securityAnswers[0].questionId',
        'SecurityQuestionAnswer1' => 'accountData.securityAnswers[0].plainAnswer',
        'SecurityQuestionType2'   => 'accountData.securityAnswers[1].questionId',
        'SecurityQuestionAnswer2' => 'accountData.securityAnswers[1].plainAnswer',
        'SecurityQuestionType3'   => 'accountData.securityAnswers[2].questionId',
        'SecurityQuestionAnswer3' => 'accountData.securityAnswers[2].plainAnswer',
        'SecurityQuestionType4'   => 'accountData.securityAnswers[3].questionId',
        'SecurityQuestionAnswer4' => 'accountData.securityAnswers[3].plainAnswer',
    ];

    public static $titles = [
        'DR'   => 'Dr',
        'JT'   => 'Jetter',
        'MISS' => 'Miss',
        'MR'   => 'Mr',
        'MRS'  => 'Mrs',
        'MS'   => 'Ms',
    ];

    public static $suffices = [
        'II'  => 'II',
        'III' => 'III',
        'JR'  => 'Jr.',
        'SR'  => 'Sr.',
    ];

    public static $countries = [
        'AF' => 'Afghanistan',
        'AX' => 'Aland Islands',
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
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (Keeling) Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CD' => 'Congo, the Democratic Republic of the',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'CI' => 'Cote D\'Ivoire',
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
        'FK' => 'Falkland Islands (Malvinas)',
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
        'GP' => 'Guadeloupe',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GG' => 'Guernsey',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard Island and McDonald Islands',
        'VA' => 'Holy See (Vatican City State)',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran, Islamic Republic of',
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
        'KP' => 'Korea, Democratic People\'s Republic of',
        'KR' => 'Korea, Republic of',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao People\'s Democratic Republic',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libyan Arab Jamahiriya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macao',
        'MK' => 'Macedonia, the Former Yugoslav Republic of',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia, Federated States of',
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
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PS' => 'Palestinian Territory, Occupied',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
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
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'BL' => 'Saint Barthélemy',
        'SH' => 'Saint Helena',
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
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'GS' => 'South Georgia and the South Sandwich Islands',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
        'TW' => 'Taiwan, Province of China',
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
        'TC' => 'Turks and Caicos Islands',
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
        'VE' => 'Venezuela',
        'VN' => 'Viet Nam',
        'VG' => 'Virgin Islands, British',
        'VI' => 'Virgin Islands, U.s.',
        'WF' => 'Wallis and Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    // TODO: Seems that it is standard ISO alpha-3 country code, recheck it and if so - make generic mapping method for all such providers
    public static $countriesMap = [
        'AF' => 'AFG',
        'AX' => 'ALA',
        'AL' => 'ALB',
        'DZ' => 'DZA',
        'AS' => 'ASM',
        'AD' => 'AND',
        'AO' => 'AGO',
        'AI' => 'AIA',
        'AQ' => 'ATA',
        'AG' => 'ATG',
        'AR' => 'ARG',
        'AM' => 'ARM',
        'AW' => 'ABW',
        'AU' => 'AUS',
        'AT' => 'AUT',
        'AZ' => 'AZE',
        'BS' => 'BHS',
        'BH' => 'BHR',
        'BD' => 'BGD',
        'BB' => 'BRB',
        'BY' => 'BLR',
        'BE' => 'BEL',
        'BZ' => 'BLZ',
        'BJ' => 'BEN',
        'BM' => 'BMU',
        'BT' => 'BTN',
        'BO' => 'BOL',
        'BA' => 'BIH',
        'BW' => 'BWA',
        'BV' => 'BVT',
        'BR' => 'BRA',
        'IO' => 'IOT',
        'BN' => 'BRN',
        'BG' => 'BGR',
        'BF' => 'BFA',
        'BI' => 'BDI',
        'KH' => 'KHM',
        'CM' => 'CMR',
        'CA' => 'CAN',
        'CV' => 'CPV',
        'KY' => 'CYM',
        'CF' => 'CAF',
        'TD' => 'TCD',
        'CL' => 'CHL',
        'CN' => 'CHN',
        'CX' => 'CXR',
        'CC' => 'CCK',
        'CO' => 'COL',
        'KM' => 'COM',
        'CG' => 'COG',
        'CD' => 'COD',
        'CK' => 'COK',
        'CR' => 'CRI',
        'CI' => 'CIV',
        'HR' => 'HRV',
        'CU' => 'CUB',
        'CW' => 'CUW',
        'CY' => 'CYP',
        'CZ' => 'CZE',
        'DK' => 'DNK',
        'DJ' => 'DJI',
        'DM' => 'DMA',
        'DO' => 'DOM',
        'EC' => 'ECU',
        'EG' => 'EGY',
        'SV' => 'SLV',
        'GQ' => 'GNQ',
        'ER' => 'ERI',
        'EE' => 'EST',
        'ET' => 'ETH',
        'FK' => 'FLK',
        'FO' => 'FRO',
        'FJ' => 'FJI',
        'FI' => 'FIN',
        'FR' => 'FRA',
        'GF' => 'GUF',
        'PF' => 'PYF',
        'TF' => 'ATF',
        'GA' => 'GAB',
        'GM' => 'GMB',
        'GE' => 'GEO',
        'DE' => 'DEU',
        'GH' => 'GHA',
        'GI' => 'GIB',
        'GR' => 'GRC',
        'GL' => 'GRL',
        'GD' => 'GRD',
        'GP' => 'GLP',
        'GU' => 'GUM',
        'GT' => 'GTM',
        'GG' => 'GGY',
        'GN' => 'GIN',
        'GW' => 'GNB',
        'GY' => 'GUY',
        'HT' => 'HTI',
        'HM' => 'HMD',
        'VA' => 'VAT',
        'HN' => 'HND',
        'HK' => 'HKG',
        'HU' => 'HUN',
        'IS' => 'ISL',
        'IN' => 'IND',
        'ID' => 'IDN',
        'IR' => 'IRN',
        'IQ' => 'IRQ',
        'IE' => 'IRL',
        'IM' => 'IMN',
        'IL' => 'ISR',
        'IT' => 'ITA',
        'JM' => 'JAM',
        'JP' => 'JPN',
        'JE' => 'JEY',
        'JO' => 'JOR',
        'KZ' => 'KAZ',
        'KE' => 'KEN',
        'KI' => 'KIR',
        'KP' => 'PRK',
        'KR' => 'KOR',
        'KW' => 'KWT',
        'KG' => 'KGZ',
        'LA' => 'LAO',
        'LV' => 'LVA',
        'LB' => 'LBN',
        'LS' => 'LSO',
        'LR' => 'LBR',
        'LY' => 'LBY',
        'LI' => 'LIE',
        'LT' => 'LTU',
        'LU' => 'LUX',
        'MO' => 'MAC',
        'MK' => 'MKD',
        'MG' => 'MDG',
        'MW' => 'MWI',
        'MY' => 'MYS',
        'MV' => 'MDV',
        'ML' => 'MLI',
        'MT' => 'MLT',
        'MH' => 'MHL',
        'MQ' => 'MTQ',
        'MR' => 'MRT',
        'MU' => 'MUS',
        'YT' => 'MYT',
        'MX' => 'MEX',
        'FM' => 'FSM',
        'MD' => 'MDA',
        'MC' => 'MCO',
        'MN' => 'MNG',
        'ME' => 'MNE',
        'MS' => 'MSR',
        'MA' => 'MAR',
        'MZ' => 'MOZ',
        'MM' => 'MMR',
        'NA' => 'NAM',
        'NR' => 'NRU',
        'NP' => 'NPL',
        'NL' => 'NLD',
        'NC' => 'NCL',
        'NZ' => 'NZL',
        'NI' => 'NIC',
        'NE' => 'NER',
        'NG' => 'NGA',
        'NU' => 'NIU',
        'NF' => 'NFK',
        'MP' => 'MNP',
        'NO' => 'NOR',
        'OM' => 'OMN',
        'PK' => 'PAK',
        'PW' => 'PLW',
        'PS' => 'PSE',
        'PA' => 'PAN',
        'PG' => 'PNG',
        'PY' => 'PRY',
        'PE' => 'PER',
        'PH' => 'PHL',
        'PN' => 'PCN',
        'PL' => 'POL',
        'PT' => 'PRT',
        'PR' => 'PRI',
        'QA' => 'QAT',
        'RE' => 'REU',
        'RO' => 'ROU',
        'RU' => 'RUS',
        'RW' => 'RWA',
        'BL' => 'BLM',
        'SH' => 'SHN',
        'KN' => 'KNA',
        'LC' => 'LCA',
        'MF' => 'MAF',
        'PM' => 'SPM',
        'VC' => 'VCT',
        'WS' => 'WSM',
        'SM' => 'SMR',
        'ST' => 'STP',
        'SA' => 'SAU',
        'SN' => 'SEN',
        'RS' => 'SRB',
        'SC' => 'SYC',
        'SL' => 'SLE',
        'SG' => 'SGP',
        'SK' => 'SVK',
        'SI' => 'SVN',
        'SB' => 'SLB',
        'SO' => 'SOM',
        'ZA' => 'ZAF',
        'GS' => 'SGS',
        'ES' => 'ESP',
        'LK' => 'LKA',
        'SD' => 'SDN',
        'SR' => 'SUR',
        'SJ' => 'SJM',
        'SZ' => 'SWZ',
        'SE' => 'SWE',
        'CH' => 'CHE',
        'SY' => 'SYR',
        'TW' => 'TWN',
        'TJ' => 'TJK',
        'TZ' => 'TZA',
        'TH' => 'THA',
        'TL' => 'TLS',
        'TG' => 'TGO',
        'TK' => 'TKL',
        'TO' => 'TON',
        'TT' => 'TTO',
        'TN' => 'TUN',
        'TR' => 'TUR',
        'TM' => 'TKM',
        'TC' => 'TCA',
        'TV' => 'TUV',
        'UG' => 'UGA',
        'UA' => 'UKR',
        'AE' => 'ARE',
        'GB' => 'GBR',
        'US' => 'USA',
        'UM' => 'UMI',
        'UY' => 'URY',
        'UZ' => 'UZB',
        'VU' => 'VUT',
        'VE' => 'VEN',
        'VN' => 'VNM',
        'VG' => 'VGB',
        'VI' => 'VIR',
        'WF' => 'WLF',
        'EH' => 'ESH',
        'YE' => 'YEM',
        'ZM' => 'ZMB',
        'ZW' => 'ZWE',
    ];

    public static $states = [
        // TODO: Fill for other countries
        'US' => [
            'AL' => 'Alabama',
            'AK' => 'Alaska',
            'AS' => 'American Samoa (see also separate entry under AS)',
            'AZ' => 'Arizona',
            'AR' => 'Arkansas',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'DC' => 'District of Columbia',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'GU' => 'Guam (see also separate entry under GU)',
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
            'MP' => 'Northern Mariana Islands (see also separate entry under MP)',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PA' => 'Pennsylvania',
            'PR' => 'Puerto Rico (see also separate entry under PR)',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UM' => 'United States Minor Outlying Islands (see also separate entry under UM)',
            'UT' => 'Utah',
            'VT' => 'Vermont',
            'VI' => 'Virgin Islands, U.S. (see also separate entry under VI)',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming',
        ],
    ];

    public static $phoneTypes = [
        'H' => 'Home',
        'M' => 'Mobile',
        'B' => 'Work',
    ];

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $securityQuestionTypes = [
        19 => 'What is the name of your favorite pet?',
        18 => 'What is your preferred musical genre?',
        // 17 => 'What is the street number of the house you grew up in?',
        16 => 'What time of the day were you born?',
        15 => 'What is the name of your favorite childhood friend?',
        14 => 'What is your favorite sport to watch?',
        13 => 'What year did you graduate from college?',
        12 => 'What is the name of the company of your first job?',
        11 => 'What year did you graduate from High School?',
        10 => 'What is the middle name of your oldest sibling?',
        9  => 'What is the middle name of your oldest child?',
        8  => 'What was the last name of your third grade teacher?',
        7  => 'What was your childhood nickname?',
        6  => 'What is your spouse’s mother’s maiden name?',
        5  => 'What is your mother’s maiden name?',
        4  => 'What was your high school mascot?',
    ];

    public static $airports = [
        'BQN' => 'Aguadilla',
        'ABQ' => 'Albuquerque',
        'ANC' => 'Anchorage',
        'AUA' => 'Aruba',
        'AUS' => 'Austin',
        'BWI' => 'Baltimore',
        'BGI' => 'Barbados',
        'BDA' => 'Bermuda (Hamilton)',
        'BOG' => 'Bogota',
        'BOS' => 'Boston',
        'BUF' => 'Buffalo',
        'BUR' => 'Burbank',
        'BTV' => 'Burlington',
        'CUN' => 'Cancun',
        'CTG' => 'Cartagena',
        'CHS' => 'Charleston',
        'CLT' => 'Charlotte',
        'ORD' => 'Chicago',
        'CLE' => 'Cleveland',
        'CUR' => 'Curacao',
        'DFW' => 'Dallas/Fort Worth',
        'DEN' => 'Denver',
        'DTW' => 'Detroit',
        'FLL' => 'Ft Lauderdale',
        'RSW' => 'Ft Myers',
        'GCM' => 'Grand Cayman',
        'GND' => 'Grenada',
        'BDL' => 'Hartford',
        'HOU' => 'Houston',
        'HYA' => 'Hyannis',
        'JAX' => 'Jacksonville',
        'KIN' => 'Kingston',
        'LRM' => 'La Romana',
        'LAS' => 'Las Vegas',
        'LIR' => 'Liberia',
        'LIM' => 'Lima',
        'LGB' => 'Long Beach',
        'LAX' => 'Los Angeles',
        'MVY' => 'Marthas Vineyard',
        'MDE' => 'Medellin',
        'MBJ' => 'Montego Bay',
        'ACK' => 'Nantucket',
        'NAS' => 'Nassau',
        'MSY' => 'New Orleans',
        'CRC' => 'New York',
        'HDQ' => 'New York',
        'JFK' => 'New York',
        'LGA' => 'New York',
        'EWR' => 'Newark',
        'SWF' => 'Newburgh',
        'OAK' => 'Oakland',
        'ONT' => 'Ontario',
        'MCO' => 'Orlando',
        'PHL' => 'Philadelphia',
        'PHX' => 'Phoenix',
        'PIT' => 'Pittsburgh',
        'PSE' => 'Ponce',
        'PAP' => 'Port Au Prince',
        'POS' => 'Port Of Spain',
        'PDX' => 'Portland',
        'PWM' => 'Portland',
        'PVD' => 'Providence',
        'PLS' => 'Providenciales',
        'PVC' => 'Provincetown',
        'POP' => 'Puerto Plata',
        'PUJ' => 'Punta Cana',
        'RDU' => 'Raleigh-Durham',
        'RNO' => 'Reno',
        'RIC' => 'Richmond',
        'ROC' => 'Rochester',
        'RUT' => 'Rutland',
        'SMF' => 'Sacramento',
        'UVF' => 'Saint Lucia',
        'SLC' => 'Salt Lake City',
        'AZS' => 'Samana',
        'SAN' => 'San Diego',
        'SFO' => 'San Francisco',
        'SJC' => 'San Jose',
        'SJO' => 'San Jose',
        'SJU' => 'San Juan',
        'STI' => 'Santiago',
        'SDQ' => 'Santo Domingo',
        'SRQ' => 'Sarasota Bradenton',
        'SAV' => 'Savannah',
        'SEA' => 'Seattle',
        'STT' => 'St Thomas',
        'STX' => 'St. Croix',
        'SXM' => 'St. Maarten',
        'SYR' => 'Syracuse',
        'TPA' => 'Tampa',
        'TUS' => 'Tucson',
        'DCA' => 'Washington',
        'IAD' => 'Washington',
        'PBI' => 'West Palm Beach',
        'HPN' => 'White Plains',
        'ORH' => 'Worcester',
    ];

    public function registerAccount(array $fields)
    {
        $this->http->Log('>>> ' . __METHOD__);

        $this->http->log('[INFO] initial fields:');
        $this->http->log(json_encode($fields, JSON_PRETTY_PRINT));

        $fields['Phone'] = $this->getPhone($fields);
        $this->checkData($fields);

        $this->http->getUrl('https://trueblue.jetblue.com/web/trueblue/register/?intcmp=hd_join');

        if (!$this->http->ParseForm('enrollment-form')) {
            $this->http->log('>>> failed to parse reg form');

            return false;
        }
        $jsessionid = $this->http->findPreg('/jsessionid=(\w+)/i');
        $p_auth = $this->http->findPreg('/p_auth=(\w+)/i');

        $origCountryCode = $fields['Country'];
        $fields['Country'] = self::$countriesMap[$fields['Country']];
        $this->logger->debug('Mapped standard country code "' . $origCountryCode . '" to provider code "' . $fields['Country'] . '"');

        $this->step1($fields);
        $this->step2($fields);
        $this->step3($fields, $jsessionid);
        $this->step4($fields);
        $this->step5($fields, $jsessionid, $p_auth);

        return true;
    }

    public function getRegisterFields()
    {
        return [
            'Title' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Title',
                'Required' => true,
                'Options'  => self::$titles,
            ],
            'FirstName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'First name',
                'Required' => true,
            ],
            'MiddleInitial' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Middle name',
                'Required' => false,
            ],
            'LastName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Last name',
                'Required' => true,
            ],
            'Suffix' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Suffix',
                'Required' => false,
                'Options'  => self::$suffices,
            ],
            'AddressLine1' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Street address 1',
                'Required' => true,
            ],
            'AddressLine2' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Street address ',
                'Required' => false,
            ],
            'Country' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Zip/Postal code',
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
                'Caption'  => 'State/Province (required for most countries)',
                'Required' => false,
            ],
            'PhoneType' => [
                'Type'     => 'string',
                'Caption'  => 'Phone type',
                'Required' => true,
                'Options'  => self::$phoneTypes,
            ],
            'PhoneCountryCodeNumeric' => [
                'Type'     => 'string',
                'Caption'  => '1-3-number Country Code',
                'Required' => true,
            ],
            'PhoneAreaCode' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Area Code',
                'Required' => true,
            ],
            'PhoneLocalNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Local Number',
                'Required' => true,
            ],
            'BirthMonth' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Birth Month',
                'Required' => true,
            ],
            'BirthDay' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Birth Day',
                'Required' => true,
            ],
            'BirthYear' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Birth Year',
                'Required' => true,
            ],
            'Gender' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => self::$genders,
            ],
            'Email' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Email address',
                'Required' => true,
            ],
            'Password' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Your password must be 8 – 20 characters long. Only alphanumerical characters, spaces, dashes and underscores are allowed.',
                'Required' => true,
            ],
            'SecurityQuestionType1' =>
          [
              'Type'     => 'integer',
              'Caption'  => 'Security question 1',
              'Required' => true,
              'Options'  => self::$securityQuestionTypes,
          ],
            'SecurityQuestionAnswer1' =>
          [
              'Type'     => 'string',
              'Caption'  => 'Answer 1, answer must be between 3 and 100 characters long, and contain only alphanumeric characters and spaces.',
              'Required' => true,
          ],
            'SecurityQuestionType2' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Security question 2',
                'Required' => true,
                'Options'  => self::$securityQuestionTypes,
            ],
            'SecurityQuestionAnswer2' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Answer 2',
                'Required' => true,
            ],
            'SecurityQuestionType3' =>
            [
                'Type'     => 'int ',
                'Caption'  => 'Security question 3',
                'Required' => true,
                'Options'  => self::$securityQuestionTypes,
            ],
            'SecurityQuestionAnswer3' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Answer 3',
                'Required' => true,
            ],
            'SecurityQuestionType4' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Security question 4',
                'Required' => true,
                'Options'  => self::$securityQuestionTypes,
            ],
            'SecurityQuestionAnswer4' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Answer 4',
                'Required' => true,
            ],
            'KnownTravelerNumber' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Known Traveler Number',
                'Required' => false,
            ],
            'EnrollmentCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Enrollment code',
                'Required' => false,
            ],
            'HomeAirport' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Home airport',
                'Required' => true,
                'Options'  => self::$airports,
            ],
            'FavoriteDestination1' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Favorite destination 1',
                'Required' => true,
                'Options'  => self::$airports,
            ],
            'FavoriteDestination2' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Favorite destination 2',
                'Required' => false,
                'Options'  => self::$airports,
            ],
        ];
    }

    private function checkData($fields)
    {
        $answers = [
            'SecurityQuestionAnswer1',
            'SecurityQuestionAnswer2',
            'SecurityQuestionAnswer3',
            'SecurityQuestionAnswer4',
        ];

        foreach ($answers as $_ => $name) {
            $ans = $fields[$name];

            if (!preg_match('/[\w\s]{3,100}/i', $ans)) {
                throw new \UserInputError("$name must be between 3 and 100 characters long, and contain only alphanumeric characters and spaces.");
            }
        }
    }

    private function getPhone(array $fields)
    {
        return sprintf('%s%s',
            $fields['PhoneAreaCode'],
            $fields['PhoneLocalNumber']
        );
    }

    private function step1($fields)
    {
        $this->populateForm($fields, self::$fieldMap1);
        $this->http->FormURL = 'https://trueblue.jetblue.com/web/trueblue/register?p_p_id=jbregistrationportlet_WAR_jbregistrationportlet&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=validateStepOne&p_p_cacheability=cacheLevelPage&p_p_col_id=column-3&p_p_col_count=1';
        $this->http->postForm();

        if ($this->http->findPreg('/"success"\s*:\s*true/i')) {
            return;
        }
        $error = $this->http->findPreg('/Your email: .+? is already in use[.]/');

        if ($error) {
            throw new \ProviderError($error);
        }

        if ($this->http->findPreg('/my-eap-info-password/i')) {
            throw new \UserInputError('Invalid password');
        }

        if ($this->http->findPreg('/my-eap-info-email-address/i')) {
            throw new \UserInputError('Invalid email');
        }

        throw new \EngineError('Step 1 validation error');
    }

    private function step2($fields)
    {
        $this->populateForm($fields, self::$fieldMap2);
        $this->http->FormURL = 'https://trueblue.jetblue.com/web/trueblue/register?p_p_id=jbregistrationportlet_WAR_jbregistrationportlet&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=validateStepTwo&p_p_cacheability=cacheLevelPage&p_p_col_id=column-3&p_p_col_count=1&_jbregistrationportlet_WAR_jbregistrationportlet_implicitModel=true';
        $this->http->postForm();

        if ($this->http->findPreg('/"success"\s*:\s*true/i')) {
            return;
        }

        throw new \EngineError('Step 2 validation error');
    }

    private function changeCountry($fields)
    {
        $this->populateForm([
            'Country' => $fields['Country'],
        ], self::$fieldMap3);
        $this->http->FormURL = 'https://trueblue.jetblue.com/web/trueblue/register?p_p_id=jbregistrationportlet_WAR_jbregistrationportlet&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=changeCountry&p_p_cacheability=cacheLevelPage&p_p_col_id=column-3&p_p_col_count=1';
        $this->http->postForm();
    }

    private function step3($fields, $jsessionid)
    {
        $this->changeCountry($fields);

        $this->populateForm($fields, self::$fieldMap3);
        $this->http->FormURL = sprintf('https://trueblue.jetblue.com/web/trueblue/register;jsessionid=%s.jbportal2?p_p_id=jbregistrationportlet_WAR_jbregistrationportlet&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=validateStepThree&p_p_cacheability=cacheLevelPage&p_p_col_id=column-3&p_p_col_count=1',
            $jsessionid
        );
        $this->http->postForm();

        if ($this->http->findPreg('/"success"\s*:\s*true/i')) {
            return;
        }

        if ($this->http->findPreg('/my-info-country/i')) {
            throw new \UserInputError(sprintf('Invalid country %s', $fields['Country']));
        }

        if ($this->http->findPreg('/my-info-state/i')) {
            throw new \UserInputError(sprintf('Invalid state %s for country %s', $fields['StateOrProvince'], $fields['Country']));
        }

        throw new \EngineError('Step 3 validation error');
    }

    private function step4($fields)
    {
        $this->populateForm($fields, self::$fieldMap4);
        $this->http->FormURL = 'https://trueblue.jetblue.com/web/trueblue/register?p_p_id=jbregistrationportlet_WAR_jbregistrationportlet&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=validateStepFour&p_p_cacheability=cacheLevelPage&p_p_col_id=column-3&p_p_col_count=1&_jbregistrationportlet_WAR_jbregistrationportlet_implicitModel=true';
        $this->http->postForm();

        if ($this->http->findPreg('/"success"\s*:\s*true/i')) {
            return;
        }

        if ($this->http->findPreg('/my-eap-info-secret-answer/i')) {
            throw new \UserInputError('Invalid security answer');
        }

        throw new \EngineError('Step 4 validation error');
    }

    private function step5($fields, $jsessionid, $p_auth)
    {
        $url = sprintf('https://trueblue.jetblue.com/web/trueblue/register;jsessionid=%s.jbportal2?p_auth=%s&p_p_id=jbregistrationportlet_WAR_jbregistrationportlet&p_p_lifecycle=1&p_p_state=normal&p_p_mode=view&p_p_col_id=column-3&p_p_col_count=1&_jbregistrationportlet_WAR_jbregistrationportlet_javax.portlet.action=enroll',
            $jsessionid,
            $p_auth
        );
        $this->http->getUrl($url);

        $success = $this->http->FindPreg("/Congratulations!/i");

        if ($success) {
            $message = 'Successfull registration';
            $this->http->Log($message);
            $this->ErrorMessage = $message;

            return;
        }

        throw new \EngineError('Unexpected response on account registration submit');
    }

    private function populateForm($fields, $fieldMap)
    {
        $this->http->Form = [];

        foreach ($fieldMap as $awkey => $keys) {
            if (!isset($fields[$awkey])) {
                $fields[$awkey] = '';
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
