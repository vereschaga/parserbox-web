<?php

// test commit

namespace AwardWallet\Engine\hertz\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;
    public $timeout = 20;

    public static $titles = [
        'DR'  => 'Dr.',
        'MR'  => 'Mr.',
        'MRS' => 'Mrs',
        'MS'  => 'Ms',
    ];

    public static $driversLicenseIssuingCountries = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
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
        'BQ' => 'Bonaire, St Eustatius and Saba',
        'BA' => 'Bosnia-Herzegovina',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Terr',
        'VG' => 'British Virgin Islands',
        'BN' => 'Brunei',
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
        'KM' => 'Comoros',
        'CD' => 'Congo, Dem Rep of the',
        'CG' => 'Congo, Republic of the',
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
        'TL' => 'East Timor',
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
        'TF' => 'French Southern Territory',
        'WF' => 'Futuna Islands',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia (Europe)',
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
        'GW' => 'Guinea-Blasau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard &amp; Mcdonald Island',
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
        'XK' => 'Kosovo',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao People\'s Democratic R',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libyan Arab Jamahiriya',
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
        'MH' => 'Marshall Island',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'MD' => 'Moldova',
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
        'MP' => 'Northern Mariana Islands (Saipan)',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
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
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'KN' => 'Saint Kitts &amp; Nevis',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome',
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
        'GS' => 'South Georgia',
        'KR' => 'South Korea',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'BL' => 'St. Barthelemy',
        'SH' => 'St. Helena, Ascension, Tristan',
        'LC' => 'St. Lucia',
        'SX' => 'St. Maarten (Dutch)',
        'MF' => 'St. Martin (French)',
        'PM' => 'St. Pierre',
        'VC' => 'St. Vincent &amp; Grenadines',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad &amp; Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks and Caicos Islands',
        'TV' => 'Tuvalu',
        'UM' => 'US Minor Islands',
        'VI' => 'US Virgin Islands',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City State',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZR' => 'Zaire',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    public static $driversLicenseIssuingStateOrProvince = [
        'AFBD' => 'Badakhshan',
        'AFBG' => 'Badghis',
        'AFBL' => 'Baghlan',
        'AFBK' => 'Balkh',
        'AFBM' => 'Bamyan',
        'AFDK' => 'Daykundi',
        'AFFH' => 'Farah',
        'AFFB' => 'Faryab',
        'AFGZ' => 'Ghazni',
        'AFGR' => 'Ghor',
        'AFHM' => 'Helmand',
        'AFHR' => 'Herat',
        'AFJW' => 'Jowzjan',
        'AFKB' => 'Kabul',
        'AFKD' => 'Kandahar',
        'AFKP' => 'Kapisa',
        'AFKT' => 'Khost',
        'AFKR' => 'Kunar',
        'AFKZ' => 'Kunduz',
        'AFLA' => 'Laghman',
        'AFLW' => 'Logar',
        'AFNG' => 'Nangarhar',
        'AFNM' => 'Nimruz',
        'AFNR' => 'Nuristan',
        'AFOZ' => 'Oru-zga-n',
        'AFPT' => 'Paktia',
        'AFPK' => 'Paktika',
        'AFPJ' => 'Panjshir',
        'AFPV' => 'Parwan',
        'AFSM' => 'Samangan',
        'AFSP' => 'Sar-e Pol',
        'AFTK' => 'Takhar',
        'AFVR' => 'Wardak',
        'AFZB' => 'Zabul',
        'AUAC' => 'AU Capital Territory',
        'AUNS' => 'New South Wales',
        'AUNT' => 'Northern Territory',
        'AUQL' => 'Queensland',
        'AUSA' => 'South Australia',
        'AUTS' => 'Tasmania',
        'AUVI' => 'Victoria',
        'AUWA' => 'Western Australia',
        'CAAL' => 'Alberta',
        'CABC' => 'British Columbia',
        'CAMN' => 'Manitoba',
        'CANB' => 'New Brunswick',
        'CANF' => 'Newfoundland',
        'CANT' => 'Northwest Territories',
        'CANS' => 'Nova Scotia',
        'CANU' => 'Nunavut',
        'CAOT' => 'Ontario',
        'CAPE' => 'Prince Edward Island',
        'CAQU' => 'Quebec',
        'CASA' => 'Saskatchewan',
        'CAYT' => 'Yukon',
        'MNAR' => 'Arhangay',
        'MNBO' => 'Bayan-Olgiy',
        'MNBH' => 'Bayanhongor',
        'MNBU' => 'Bulgan',
        'MNDA' => 'Darhan-Uul',
        'MNDD' => 'Dornod',
        'MNDG' => 'Dornogovi',
        'MNDU' => 'Dundgovi',
        'MNDZ' => 'Dzavhan',
        'MNGA' => 'Govi-Altay',
        'MNGS' => 'Govisumber',
        'MNHN' => 'Hentiy',
        'MNHD' => 'Hovd',
        'MNHG' => 'Hovsgol',
        'MNOG' => 'Omnogovi',
        'MNER' => 'Orhon',
        'MNSL' => 'Selenge',
        'MNSB' => 'Suhbaatar',
        'MNTO' => 'Tov',
        'MNUB' => 'Ulaanbaatar',
        'MNUV' => 'Uvs',
        'MNOH' => 'Ööngay',
        'USAL' => 'Alabama',
        'USAK' => 'Alaska',
        'USAZ' => 'Arizona',
        'USAR' => 'Arkansas',
        'USAA' => 'Armed Forces (AA)',
        'USAE' => 'Armed Forces (AE)',
        'USAP' => 'Armed Forces (AP)',
        'USCA' => 'California',
        'USCO' => 'Colorado',
        'USCT' => 'Connecticut',
        'USDE' => 'Delaware',
        'USDC' => 'District Of Columbia',
        'USFL' => 'Florida',
        'USGA' => 'Georgia',
        'USHI' => 'Hawaii',
        'USID' => 'Idaho',
        'USIL' => 'Illinois',
        'USIN' => 'Indiana',
        'USIA' => 'Iowa',
        'USKS' => 'Kansas',
        'USKY' => 'Kentucky',
        'USLA' => 'Louisiana',
        'USME' => 'Maine',
        'USMD' => 'Maryland',
        'USMA' => 'Massachusetts',
        'USMI' => 'Michigan',
        'USMN' => 'Minnesota',
        'USMS' => 'Mississippi',
        'USMO' => 'Missouri',
        'USMT' => 'Montana',
        'USNE' => 'Nebraska',
        'USNV' => 'Nevada',
        'USNH' => 'New Hampshire',
        'USNJ' => 'New Jersey',
        'USNM' => 'New Mexico',
        'USNY' => 'New York',
        'USNC' => 'North Carolina',
        'USND' => 'North Dakota',
        'USOH' => 'Ohio',
        'USOK' => 'Oklahoma',
        'USOR' => 'Oregon',
        'USPA' => 'Pennsylvania',
        'USRI' => 'Rhode Island',
        'USSC' => 'South Carolina',
        'USSD' => 'South Dakota',
        'USTN' => 'Tennessee',
        'USTX' => 'Texas',
        'USUT' => 'Utah',
        'USVT' => 'Vermont',
        'USVA' => 'Virginia',
        'USWA' => 'Washington',
        'USWV' => 'West Virginia',
        'USWI' => 'Wisconsin',
        'USWY' => 'Wyoming',
    ];

    public static $phoneTypes = [
        'H' => 'Home',
        'B' => 'Business',
        'M' => 'Mobile',
    ];

    public static $addressTypes = [
        'H' => 'Home',
        'B' => 'Business',
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
        'BQ' => 'Bonaire, St Eustatius and Saba',
        'BA' => 'Bosnia-Herzegovina',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Terr',
        'VG' => 'British Virgin Islands',
        'BN' => 'Brunei',
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
        'KM' => 'Comoros',
        'CD' => 'Zaire',
        'CG' => 'Congo, Republic of the',
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
        'TL' => 'East Timor',
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
        'TF' => 'French Southern Territory',
        'WF' => 'Futuna Islands',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia (Europe)',
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
        'GW' => 'Guinea-Blasau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard & Mcdonald Island',
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
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao People\'s Democratic R',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libyan Arab Jamahiriya',
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
        'MH' => 'Marshall Island',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'MD' => 'Moldova',
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
        'MP' => 'Northern Mariana Islands (Saipan)',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
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
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'KN' => 'Saint Kitts & Nevis',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome',
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
        'GS' => 'South Georgia',
        'KR' => 'South Korea',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'BL' => 'St. Barthelemy',
        'SH' => 'St. Helena, Ascension, Tristan',
        'LC' => 'St. Lucia',
        'SX' => 'St. Maarten (Dutch)',
        'MF' => 'St. Martin (French)',
        'PM' => 'St. Pierre',
        'VC' => 'St. Vincent & Grenadines',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad & Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks and Caicos Islands',
        'TV' => 'Tuvalu',
        'UM' => 'US Minor Islands',
        'VI' => 'US Virgin Islands',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City State',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    public static $states = [
        'AFBD' => 'Badakhshan',
        'AFBG' => 'Badghis',
        'AFBL' => 'Baghlan',
        'AFBK' => 'Balkh',
        'AFBM' => 'Bamyan',
        'AFDK' => 'Daykundi',
        'AFFH' => 'Farah',
        'AFFB' => 'Faryab',
        'AFGZ' => 'Ghazni',
        'AFGR' => 'Ghor',
        'AFHM' => 'Helmand',
        'AFHR' => 'Herat',
        'AFJW' => 'Jowzjan',
        'AFKB' => 'Kabul',
        'AFKD' => 'Kandahar',
        'AFKP' => 'Kapisa',
        'AFKT' => 'Khost',
        'AFKR' => 'Kunar',
        'AFKZ' => 'Kunduz',
        'AFLA' => 'Laghman',
        'AFLW' => 'Logar',
        'AFNG' => 'Nangarhar',
        'AFNM' => 'Nimruz',
        'AFNR' => 'Nuristan',
        'AFOZ' => 'Oru-zga-n',
        'AFPT' => 'Paktia',
        'AFPK' => 'Paktika',
        'AFPJ' => 'Panjshir',
        'AFPV' => 'Parwan',
        'AFSM' => 'Samangan',
        'AFSP' => 'Sar-e Pol',
        'AFTK' => 'Takhar',
        'AFVR' => 'Wardak',
        'AFZB' => 'Zabul',
        'AUAC' => 'AU Capital Territory',
        'AUNS' => 'New South Wales',
        'AUNT' => 'Northern Territory',
        'AUQL' => 'Queensland',
        'AUSA' => 'South Australia',
        'AUTS' => 'Tasmania',
        'AUVI' => 'Victoria',
        'AUWA' => 'Western Australia',
        'CAAL' => 'Alberta',
        'CABC' => 'British Columbia',
        'CAMN' => 'Manitoba',
        'CANB' => 'New Brunswick',
        'CANF' => 'Newfoundland',
        'CANT' => 'Northwest Territories',
        'CANS' => 'Nova Scotia',
        'CANU' => 'Nunavut',
        'CAOT' => 'Ontario',
        'CAPE' => 'Prince Edward Island',
        'CAQU' => 'Quebec',
        'CASA' => 'Saskatchewan',
        'CAYT' => 'Yukon',
        'MNAR' => 'Arhangay',
        'MNBO' => 'Bayan-Olgiy',
        'MNBH' => 'Bayanhongor',
        'MNBU' => 'Bulgan',
        'MNDA' => 'Darhan-Uul',
        'MNDD' => 'Dornod',
        'MNDG' => 'Dornogovi',
        'MNDU' => 'Dundgovi',
        'MNDZ' => 'Dzavhan',
        'MNGA' => 'Govi-Altay',
        'MNGS' => 'Govisumber',
        'MNHN' => 'Hentiy',
        'MNHD' => 'Hovd',
        'MNHG' => 'Hovsgol',
        'MNOG' => 'Omnogovi',
        'MNER' => 'Orhon',
        'MNSL' => 'Selenge',
        'MNSB' => 'Suhbaatar',
        'MNTO' => 'Tov',
        'MNUB' => 'Ulaanbaatar',
        'MNUV' => 'Uvs',
        'MNOH' => 'Ööngay',
        'USAL' => 'Alabama',
        'USAK' => 'Alaska',
        'USAZ' => 'Arizona',
        'USAR' => 'Arkansas',
        'USAA' => 'Armed Forces (AA)',
        'USAE' => 'Armed Forces (AE)',
        'USAP' => 'Armed Forces (AP)',
        'USCA' => 'California',
        'USCO' => 'Colorado',
        'USCT' => 'Connecticut',
        'USDE' => 'Delaware',
        'USDC' => 'District Of Columbia',
        'USFL' => 'Florida',
        'USGA' => 'Georgia',
        'USHI' => 'Hawaii',
        'USID' => 'Idaho',
        'USIL' => 'Illinois',
        'USIN' => 'Indiana',
        'USIA' => 'Iowa',
        'USKS' => 'Kansas',
        'USKY' => 'Kentucky',
        'USLA' => 'Louisiana',
        'USME' => 'Maine',
        'USMD' => 'Maryland',
        'USMA' => 'Massachusetts',
        'USMI' => 'Michigan',
        'USMN' => 'Minnesota',
        'USMS' => 'Mississippi',
        'USMO' => 'Missouri',
        'USMT' => 'Montana',
        'USNE' => 'Nebraska',
        'USNV' => 'Nevada',
        'USNH' => 'New Hampshire',
        'USNJ' => 'New Jersey',
        'USNM' => 'New Mexico',
        'USNY' => 'New York',
        'USNC' => 'North Carolina',
        'USND' => 'North Dakota',
        'USOH' => 'Ohio',
        'USOK' => 'Oklahoma',
        'USOR' => 'Oregon',
        'USPA' => 'Pennsylvania',
        'USRI' => 'Rhode Island',
        'USSC' => 'South Carolina',
        'USSD' => 'South Dakota',
        'USTN' => 'Tennessee',
        'USTX' => 'Texas',
        'USUT' => 'Utah',
        'USVT' => 'Vermont',
        'USVA' => 'Virginia',
        'USWA' => 'Washington',
        'USWV' => 'West Virginia',
        'USWI' => 'Wisconsin',
        'USWY' => 'Wyoming',
    ];

    public static $creditCardTypes = [
        'AIR' => 'AirPlus',
        'AXP' => 'American Exp Purchasing Card',
        'AMX' => 'American Express',
        'DCL' => 'Diners Club',
        'DIS' => 'Discover Network',
        'JCB' => 'JCB',
        'MC'  => 'Mastercard',
        'VSA' => 'Visa',
    ];

    public static $vehiclePreferencesForUsOrCanada = [
        'CCAR' => 'Compact, 4-Door',
        'ICAR' => 'Mid-Size',
        'SCAR' => 'Standard',
        'FCAR' => 'Full-Size, 4-Door',
        'PCAR' => 'Premium',
        'LCAR' => 'Luxury',
        'SFAR' => 'Sport Utility Vehicle, 4-wheel drive',
    ];

    public static $vehiclePreferenceForEuropeMiddleEastOrAfrica = [
        //		'ECAN' => 'Economy - Automatic',
        //		'ECMN' => 'Economy - Manual',
        'CCAN' => 'Compact - Automatic',
        'CCMN' => 'Compact - Manual',
        'IDAR' => 'Intermediate - Automatic',
        'IDMR' => 'Intermediate - Manual',
        'SDAR' => 'Standard - Automatic',
        'SDMR' => 'Standard - Manual',
        'FCAN' => 'Full-Size - Automatic',
        'FVMR' => 'Full-Size - Manual',
        'PFAR' => 'Premium - Automatic',
        'PDMR' => 'Premium - Manual',
        'MBMN' => 'Mini Class',
    ];

    public static $vehiclePreferenceForAustralia = [
        'CCMR' => 'Compact - Manual',
        'ICAR' => 'Intermediate - Automatic',
        'FCAR' => 'Full Size - Automatic',
    ];

    public static $vehiclePreferenceForNewZealand = [
        'CDAR' => 'Compact - Automatic',
        'CDMR' => 'Compact - Manual',
        'IDAR' => 'Intermediate - Automatic',
        'FDAR' => 'Full-Size - Automatic',
    ];

    public static $inputFieldsMap = [];
    protected $fields;
    protected $stepNum = 1;
    protected $startTime;

    /** @var TAccountChecker */
//    public $primaryChecker;
    private $registrUrl = 'https://www.hertz.com/rentacar/member/enrollment/gold/step';

    public function initBrowser()
    {
//        $this->AccountFields['BrowserState'] = null;
        $this->useSelenium();

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP());
        } else {
            $this->http->SetProxy('localhost:8000');
        }
//
//        $this->InitSeleniumBrowser($this->http->GetProxy());
    }

    public function getRegisterFields()
    {
        return [
            'Title' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Title ',
                'Required' => true,
                'Options'  => self::$titles,
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
            'BirthYear' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Year of Birth (you must be 21 years or older, some exceptions apply)',
                'Required' => true,
            ],
            'BirthMonth' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Month of Birth',
                'Required' => true,
            ],
            'BirthDay' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Day of Birth',
                'Required' => true,
            ],
            'DriversLicenseNumber' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Drivers License Number',
                'Required' => true,
            ],
            'DriversLicenseIssuingCountry' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Drivers License Issuing Country and State',
                'Required' => true,
                'Options'  => self::$driversLicenseIssuingCountries,
            ],
            'DriversLicenseIssuingStateOrProvince' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Drivers License Issuing State Or Province (required for Afghanistan, Australia, Canada, Mongolia and United States)',
                'Required' => false,
                'Options'  => self::$driversLicenseIssuingStateOrProvince,
            ],
            'DriverLicenseExpirationYear' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Driver License Expiration Year',
                'Required' => false,
            ],
            'DriverLicenseExpirationMonth' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Driver License Expiration Month',
                'Required' => false,
            ],
            'DriverLicenseExpirationDay' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Driver License Expiration Day',
                'Required' => false,
            ],
            'DriverLicenseIssuedYear' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Driver License Issued Year',
                'Required' => false,
            ],
            'DriverLicenseIssuedMonth' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Driver License Issued Month',
                'Required' => false,
            ],
            'DriverLicenseIssuedDay' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Driver License Issued Day',
                'Required' => false,
            ],
            'Email' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Email (will be your User ID)																					',
                'Required' => true,
            ],
            'Password' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Password (must be from 4 to 16 characters in length and asterisks are not accepted)',
                'Required' => true,
            ],
            'PhoneType' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Phone Type',
                'Required' => true,
                'Options'  => self::$phoneTypes,
            ],
            'PhoneCountryCodeAlphabetic' =>
            [
                'Type'     => 'string',
                'Caption'  => '2-letter Phone Country Code',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            //			'PhoneCountryCodeNumeric' =>
            //			array (
            //				'Type' => 'string',
            //				'Caption' => '1-3-number Phone Country Code',
            //				'Required' => true,
            //			),
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
            'AddressType' => [
                'Type'     => 'string',
                'Caption'  => 'Address type',
                'Required' => true,
                'Options'  => self::$addressTypes,
            ],
            'Company' => [
                'Type'     => 'string',
                'Caption'  => 'Company Name (required if AddressType is Business)',
                'Required' => false,
            ],
            'JobTitle' => [
                'Type'     => 'string',
                'Caption'  => 'Business Role (required if AddressType is Business)',
                'Required' => false,
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
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Postal Code',
                'Required' => true,
            ],
            'Country' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'StateOrProvince' =>
            [
                'Type'     => 'string',
                'Caption'  => 'State or Province (required for Afghanistan, Australia, Canada, Mongolia and United States)',
                'Required' => false,
                'Options'  => self::$states,
            ],
            'CreditCardType' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Credit Card Type',
                'Required' => true,
                'Options'  => self::$creditCardTypes,
            ],
            'CreditCardNumber' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Credit Card Number',
                'Required' => true,
            ],
            'CreditCardExpirationMonth' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Credit Card Expiration Month',
                'Required' => true,
            ],
            'CreditCardExpirationYear' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Credit Card Expiration Year',
                'Required' => true,
            ],
            'VehiclePreferenceForUsOrCanada' =>
            [
                'Type'     => 'string',
                'Caption'  => 'U.S./Canada Vehicle Preference',
                'Required' => true,
                'Options'  => self::$vehiclePreferencesForUsOrCanada,
            ],
            'VehiclePreferenceForEuropeMiddleEastOrAfrica' =>
            [
                'Type'     => 'string',
                'Caption'  => 'European / Middle Eastern / African (EMEA) Vehicle Preferences',
                'Required' => true,
                'Options'  => self::$vehiclePreferenceForEuropeMiddleEastOrAfrica,
            ],
            'VehiclePreferenceForAustralia' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Australian Vehicle Class Preference',
                'Required' => true,
                'Options'  => self::$vehiclePreferenceForAustralia,
            ],
            'VehiclePreferenceForNewZealand' =>
            [
                'Type'     => 'string',
                'Caption'  => 'NewZealand Vehicle Class Preference',
                'Required' => true,
                'Options'  => self::$vehiclePreferenceForNewZealand,
            ],
        ];
    }

    public function registerAccount(array $fields)
    {
//        $this->http->GetURL('http://whatismyipaddress.com/');
//        sleep(10);
//        return false;

        $this->fields = $fields;
        $this->startTime = time();

        return $this->registerInternal();
    }

    protected function logTime()
    {
        if (isset($this->startTime)) {
            $this->http->Log((time() - $this->startTime) . ' seconds since start');
        } else {
            $this->http->Log('start timer not set', LOG_LEVEL_ERROR);
        }
    }

    protected function waitForElement(\WebDriverBy $by, $timeout = null, $visible = true)
    {
        if (!isset($timeout)) {
            $timeout = $this->timeout;
        }
        $time = time();

        do {
            $elements = $this->driver->findElements($by);

            if (count($elements) > 0) {
                $this->http->Log('found element ' . $by->getValue());

                return array_shift($elements);
            }
            sleep(1);
        } while ((time() - $time) < $timeout);
        $this->http->Log('element ' . $by->getValue() . ' not found');

        return null;
    }

    protected function registerInternal()
    {
        $this->http->GetURL($this->registrUrl);

        if (!($elem = $this->waitForElement(\WebDriverBy::id('visitorJoinButton'), $this->timeout, false))) {
            throw new \EngineError('Could not find registration form fields');
        }
        $elem->click();

        if (!($elem = $this->waitForElement(\WebDriverBy::id('emember-step1-page-container'), $this->timeout, false))) {
            throw new \EngineError('Could not find registration form fields');
        }

        $this->checkErrors();

        $steps = ['stepDriverInfo', 'stepPersonalInfo', 'stepPaymentsInfo', 'sendValues', 'stepCarPreferences', 'submit'];
        $result = true;

        foreach ($steps as $method) {
            $result = $this->$method();
            $this->http->Log("$method complete");
            $this->logTime();
            ++$this->stepNum;
            $this->saveResponse();
        }

        return $result;
    }

    protected function checkErrors()
    {
        $countriesList = ['AF', 'AU', 'CA', 'MN', 'US'];
        $errorFields = [];

        if (in_array($this->fields['DriversLicenseIssuingCountry'], $countriesList)) {
            if (!isset($this->fields['DriversLicenseIssuingStateOrProvince']) || trim($this->fields['DriversLicenseIssuingStateOrProvince']) === '') {
                $errorFields[] = '\'DriversLicenseIssuingStateOrProvince\'';
            }
        }

        if (in_array($this->fields['Country'], $countriesList)) {
            if (!isset($this->fields['StateOrProvince']) || trim($this->fields['StateOrProvince']) === '') {
                $errorFields[] = '\'StateOrProvince\'';
            }
        }

        if ($this->fields['AddressType'] === 'B') {
            if (!isset($this->fields['Company']) || trim($this->fields['Company']) === '') {
                $errorFields[] = '\'Company\'';
            }

            if (!isset($this->fields['JobTitle']) || trim($this->fields['JobTitle']) === '') {
                $errorFields[] = '\'JobTitle\'';
            }
        }

        if (!empty($errorFields)) {
            throw new \UserInputError('Fields ' . implode($errorFields, ', ') . ' required to fill.');
        }
    }

    protected function setInputValues($data)
    {
        foreach ($data as $key => $value) {
            if (!($elem = $this->waitForElement(\WebDriverBy::id($key), $this->timeout, false))) {
                throw new \EngineError('Could not find registration form fields ' . $key);
            }

            $this->driver->executeScript("$('#{$key}').val('{$value}'); $('#{$key}').change(); $('#{$key}').keypress(); $('#{$key}').keyup();");
        }
    }

    protected function sendValues()
    {
        if (!($elem = $this->waitForElement(\WebDriverBy::xpath("(//div[contains(@class, 'button-section')]/button[contains(@class, 'primary')])[{$this->stepNum}]"), $this->timeout, false))) {
            throw new \EngineError("Can't find 'Next' button (Step #{$this->stepNum})");
        }

        if (!$elem->isDisplayed()) {
            throw new \EngineError("Unable to click 'Next' button (Step #{$this->stepNum})");
        }

        $elem->click();
    }

    protected function collectErrors()
    {
        $this->http->Log('collecting errors');
        $this->logTime();

        if ($elem = $this->waitForElement(\WebDriverBy::xpath("(//label[contains(@class, 'error')])[1]"), $this->timeout, false)) {
            $errors = $this->driver->findElements(\WebDriverBy::xpath("//label[contains(@class, 'error')]"));
            $msg = "Invalid values in fields: ";

            foreach ($errors as $error) {
                $msg .= "'" . str_replace('*', '', str_replace(':', '', $error->getText())) . "', ";
            }

            $this->logTime();
            $this->saveResponse();

            throw new \UserInputError($msg);
        }
    }

    protected function stepDriverInfo()
    {
        $country = $this->fields['DriversLicenseIssuingCountry'];

        if (isset($this->fields['DriversLicenseIssuingStateOrProvince']) and trim($this->fields['DriversLicenseIssuingStateOrProvince']) !== '') {
            $country = $this->fields['DriversLicenseIssuingStateOrProvince'];
        }

        $this->http->Log('driver info: setting input values');
        $this->logTime();
        $this->setInputValues([
            'birthYear'                     => $this->fields['BirthYear'],
            'birthMonth'                    => $this->fields['BirthMonth'],
            'birthDay'                      => $this->fields['BirthDay'],
            'driversLicense'                => $this->fields['DriversLicenseNumber'],
            'driversLicenseCountryState'    => $country,
            'driversLicenseExpirationYear'  => $this->fields['DriverLicenseExpirationYear'],
            'driversLicenseExpirationMonth' => $this->fields['DriverLicenseExpirationMonth'],
            'driversLicenseExpirationDay'   => $this->fields['DriverLicenseExpirationDay'],
            'driversLicenseIssueYear'       => $this->fields['DriverLicenseIssuedYear'],
            'driversLicenseIssueMonth'      => $this->fields['DriverLicenseIssuedMonth'],
            'driversLicenseIssueDay'        => $this->fields['DriverLicenseIssuedDay'],
            'firstName'                     => $this->fields['FirstName'],
            'lastName'                      => $this->fields['LastName'],
            'namePrefix'                    => $this->fields['Title'],
        ]);

        $this->http->Log('driver info: sending values');
        $this->logTime();
        $this->sendValues();

        if ($elem = $this->waitForElement(\WebDriverBy::id("email"), $this->timeout, false)) {
            $this->http->Log('found email input, next step is up');
            $this->logTime();

            return;
        }

        $this->collectErrors();

        throw new \EngineError('Undefined stepDriverInfo error');
    }

    protected function stepPersonalInfo()
    {
        $data = [
            'email'                  => $this->fields['Email'],
            'emailVerify'            => $this->fields['Email'],
            'username'               => $this->fields['Email'],
            'mobilePhoneCountryCode' => $this->fields['PhoneCountryCodeAlphabetic'],
            'password'               => $this->fields['Password'],
            'passwordVerify'         => $this->fields['Password'],
        ];
        $phones = ['H' => 'personalPhone', 'M' => 'mobilePhoneNumber', 'B' => 'businessPhone'];
        $data[$phones[$this->fields['PhoneType']]] = $this->fields['PhoneAreaCode'] . $this->fields['PhoneLocalNumber'];

        $this->setInputValues($data);
        $this->sendValues();

        if ($elem = $this->waitForElement(\WebDriverBy::id("cardMaskedNum"), $this->timeout, false)) {
            return;
        }

        $this->collectErrors();

        throw new \EngineError('Undefined stepPersonalInfo error');
    }

    protected function stepPaymentsInfo()
    {
        $addrType = ['B'=>'business', 'H'=>'personal'];

        // Provider uses wrong country codes for Zaire (ZR instead of standard CD)
        // Map from our standard ISO code to wrong code used by provider
        if ($this->fields['Country'] == 'CD') {
            $this->fields['Country'] = 'ZR';
            $this->logger->debug('Mapped standard country code "CD" to provider code "ZR"');
        }

        $country = $this->fields['Country'];

        if (isset($this->fields['StateOrProvince']) and trim($this->fields['StateOrProvince']) !== '') {
            $country = $this->fields['StateOrProvince'];
        }

        $addrData = [
            'Address1'     => $this->fields['AddressLine1'],
            'City'         => $this->fields['City'],
            'CountryState' => $country,
            'ZipCode'      => $this->fields['PostalCode'],
        ];
        $data = [
            'creditcard-type'  => $this->fields['CreditCardType'],
            'cardExpYear'      => $this->fields['CreditCardExpirationYear'],
            'creditcard-month' => $this->fields['CreditCardExpirationMonth'],
            'cardMaskedNum'    => $this->fields['CreditCardNumber'],
        ];

        foreach ($addrData as $item => $value) {
            $data[$addrType[$this->fields['AddressType']] . $item] = $value;
        }

        if ($this->fields['AddressType'] === 'H') {
            /**
                //div[contains(@class,'home-add-section')]/descendant::button[contains(@class,'expansionButton')]
                //div[contains(@class,'busn-add-section')]/descendant::button[contains(@class,'expansionButton')]
             */
            $expandButtons = $this->driver->findElements(\WebDriverBy::xpath("//div[contains(@class,'add-section')]/descendant::button[contains(@class,'expansionButton')]"));

            foreach ($expandButtons as $button) {
                $button->click();
            }
        } else {
            $data['businessName'] = $this->fields['Company'] ?? '';
            $data['businessRole'] = $this->fields['JobTitle'] ?? '';
        }

        $this->setInputValues($data);
        $this->sendValues();

        if ($elem = $this->waitForElement(\WebDriverBy::xpath("//select[@name='issuingCompany']"), $this->timeout, false)) {
            return;
        }

        $this->collectErrors();

        throw new \EngineError('Undefined stepPaymentsInfo error');
    }

    protected function stepCarPreferences()
    {
        if (!$elem = $this->waitForElement(\WebDriverBy::xpath("(//span[contains(@class,'enrollment-blue-plus-box')])[1]"), $this->timeout, false)) {
            throw new \EngineError('Undefined stepCarPreferences error');
        }

        $expandButtons = $this->driver->findElements(\WebDriverBy::xpath("//span[contains(@class,'enrollment-blue-plus-box')]"));

        foreach ($expandButtons as $button) {
            $button->click();
        }

        $prefValues = [
            $this->fields['VehiclePreferenceForUsOrCanada'],
            $this->fields['VehiclePreferenceForEuropeMiddleEastOrAfrica'],
            $this->fields['VehiclePreferenceForAustralia'],
            $this->fields['VehiclePreferenceForNewZealand'],
        ];

        foreach ($prefValues as $value) {
            if (!$elem = $this->waitForElement(\WebDriverBy::xpath("//input[@type='radio' and @value='{$value}']"), $this->timeout, false)) {
                continue;
            }

            $elem->click();
        }

        $inputsForClick = [
            'personalAccidentInsuranceEMEA',
            'lossDamageWaiverUS', 'lossDamageWaiverUS', 'liabilityInsuranceSupplementAllStatesUS', 'personalAccidentInsuranceUS',
            'lossDamageWaiverCA', 'personalAccidentInsuranceCA',
            'accidentExcessReductionAU',
            'personalAccidentInsuranceNZ', 'personalEffectsCoverageNZ', 'accidentExcessReductionNZ',
            'collisionDamageWaiverEMEA', 'theftProtectionEMEA',
        ];

        foreach ($inputsForClick as $name) {
            $elem = $this->waitForElement(\WebDriverBy::xpath("//input[@name='{$name}']"), $this->timeout, false);
            $elem->click();
        }

        $this->sendValues();

        if ($elem = $this->waitForElement(\WebDriverBy::xpath("//button[@id='summary-submit']"), $this->timeout, false)) {
            return;
        }

//        $this->collectErrors();
        throw new \EngineError('Undefined stepCarPreferences error');
    }

    protected function submit()
    {
        if ($elem = $this->waitForElement(\WebDriverBy::xpath("//input[@id='termsAndConditionsCheckBox']"), $this->timeout, false)) {
            $elem->click();
        }

        if ($elem = $this->waitForElement(\WebDriverBy::xpath("//input[@id='eConsentCheckBox']"), $this->timeout, false)) {
            $elem->click();
        }

        if ($elem = $this->waitForElement(\WebDriverBy::xpath("(//button[@id='summary-submit'])[2]"), $this->timeout, false)) {
            $elem->click();
        }

        if ($elem = $this->waitForElement(\WebDriverBy::xpath("//div[@id='step7-container']/descendant::h2[@class='gold_plus_title']"), $this->timeout, false)) {
            $this->ErrorMessage = $elem->getText();
            $this->http->Log($this->ErrorMessage);

            return true;
        }

        throw new \EngineError('Unknown submit error');
    }

    protected function logPageSource($logLevel = null)
    {
        $this->log($this->driver->executeScript('return document.documentElement.innerHTML'), $logLevel);
    }

    private function log($msg, $loglevel = null)
    {
        $this->http->Log($msg, $loglevel);
    }
}
