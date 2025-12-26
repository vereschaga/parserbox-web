<?php

// case #10446

namespace AwardWallet\Engine\voila\Transfer;

class Register extends \TAccountCheckerVoila
{
    public static $fieldMap1 = [
        'Email'    => 'ctl00$MainContent$txtEmail',
        'Password' => [
            'ctl00$MainContent$txtPassword',
            'ctl00$MainContent$txtConfirmPassword',
        ],
    ];

    public static $fieldMap2 = [
        'FirstName'                   => 'ctl00$MainContent$txtFirstName',
        'MiddleInitial'               => 'ctl00$MainContent$txtMiddleName',
        'LastName'                    => 'ctl00$MainContent$txtLastName',
        'Gender'                      => 'ctl00$MainContent$ddlGender',
        'BirthMonth'                  => 'ctl00$MainContent$txtBirthMonth',
        'BirthDay'                    => 'ctl00$MainContent$txtBirthDays',
        'BirthYear'                   => 'ctl00$MainContent$txtBirthYear',
        'AnniversaryMonth'            => 'ctl00$MainContent$txtAnnMonth',
        'AnniversaryDay'              => 'ctl00$MainContent$txtAnnDays',
        'AnniversaryYear'             => 'ctl00$MainContent$txtAnnYear',
        'PreferredLanguage'           => 'ctl00$MainContent$ddlLangauge',
        'HomePhoneCountryCode'        => 'ctl00$MainContent$ddlTelephone',
        'HomePhoneCountryNumber'      => 'ctl00$MainContent$txtTelephone2',
        'MobilePhoneCountryCode'      => 'ctl00$MainContent$ddlMobilePhone',
        'MobilePhoneCountryNumber'    => 'ctl00$MainContent$txtMobileNO',
        'AlternatePhoneCountryCode'   => 'ctl00$MainContent$ddlAltTelephone',
        'AlternatePhoneCountryNumber' => 'ctl00$MainContent$txtAltrPhone2',
    ];

    public static $fieldMap3 = [
        'Company'          => 'ctl00$MainContent$txtCompany',
        'AddressLine1'     => 'ctl00$MainContent$txtAddressLine1',
        'AddressLine2'     => 'ctl00$MainContent$txtAddressLine2',
        'City'             => 'ctl00$MainContent$txtCity',
        'StateOrProvince'  => 'ctl00$MainContent$txtState',
        'PostalCode'       => 'ctl00$MainContent$txtPostalCode',
        'Country'          => 'ctl00$MainContent$ddlCountry',
        'TravelPreference' => 'ctl00$MainContent$ddlPrimaryTravel',
        'RegistrationCode' => 'ctl00$MainContent$txtRegisterationCode',
        'PreferredBrand'   => 'ctl00$MainContent$ddlBrandsList',
    ];

    public static $travelPreferences = [
        '1' => 'Business',
        '2' => 'Pleasure',
    ];

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $preferredLanguages = [
        'EN'     => 'English',
        'ES'     => 'Español (España)',
        'FR'     => 'French',
        'ID'     => 'Indonesia',
        'ES-419' => 'Español (América Latina)',
        'MS'     => 'Malay',
        'PT'     => 'Português (Brasil)',
        'TH'     => 'ภาษาไทย',
        'ZH'     => '中文（简体）',
    ];

    public static $preferredBrands = [
        1       => 'VOILÀ',
        12546   => 'Othon',
        12549   => 'Hotéis Deville',
        1000010 => 'Lexington Hotels',
        100011  => 'Sutera Harbour Resort',
        100012  => 'Ritz Lagoa Da Anta',
        100015  => 'HB Hotels',
        100016  => 'Riande Rewards',
        100018  => 'Copley Square',
        1000008 => 'The Media Hotel And Towers',
        12692   => 'St Giles Hotel New York',
        12511   => 'St Regis',
        881     => 'Kayumanis',
        100013  => 'Impiana Hotels',
        100014  => 'Time Hotels',
        100019  => 'Keylime Cove',
        975     => 'HUSA Hoteles',
        100024  => 'Matsubara Hoteis',
        100020  => 'L.E. Hotels Rewards',
    ];

    public static $countryPhoneCodes = [
        1   => '1',
        7   => '7',
        20  => '20',
        27  => '27',
        30  => '30',
        31  => '31',
        32  => '32',
        33  => '33',
        34  => '34',
        36  => '36',
        39  => '39',
        40  => '40',
        41  => '41',
        43  => '43',
        44  => '44',
        45  => '45',
        46  => '46',
        47  => '47',
        48  => '48',
        49  => '49',
        51  => '51',
        52  => '52',
        53  => '53',
        54  => '54',
        55  => '55',
        56  => '56',
        57  => '57',
        58  => '58',
        60  => '60',
        61  => '61',
        62  => '62',
        63  => '63',
        64  => '64',
        65  => '65',
        66  => '66',
        81  => '81',
        82  => '82',
        84  => '84',
        86  => '86',
        90  => '90',
        91  => '91',
        92  => '92',
        93  => '93',
        94  => '94',
        98  => '98',
        212 => '212',
        213 => '213',
        216 => '216',
        218 => '218',
        220 => '220',
        221 => '221',
        222 => '222',
        223 => '223',
        224 => '224',
        225 => '225',
        226 => '226',
        227 => '227',
        228 => '228',
        229 => '229',
        230 => '230',
        231 => '231',
        232 => '232',
        233 => '233',
        234 => '234',
        235 => '235',
        236 => '236',
        237 => '237',
        238 => '238',
        239 => '239',
        240 => '240',
        241 => '241',
        242 => '242',
        244 => '244',
        245 => '245',
        246 => '246',
        248 => '248',
        249 => '249',
        250 => '250',
        251 => '251',
        252 => '252',
        254 => '254',
        255 => '255',
        256 => '256',
        257 => '257',
        258 => '258',
        260 => '260',
        261 => '261',
        262 => '262',
        263 => '263',
        264 => '264',
        265 => '265',
        266 => '266',
        267 => '267',
        268 => '268',
        269 => '269',
        284 => '284',
        290 => '290',
        291 => '291',
        297 => '297',
        298 => '298',
        299 => '299',
        340 => '340',
        345 => '345',
        350 => '350',
        351 => '351',
        352 => '352',
        353 => '353',
        354 => '354',
        355 => '355',
        356 => '356',
        357 => '357',
        358 => '358',
        359 => '359',
        370 => '370',
        371 => '371',
        372 => '372',
        373 => '373',
        374 => '374',
        375 => '375',
        376 => '376',
        377 => '377',
        378 => '378',
        380 => '380',
        381 => '381',
        385 => '385',
        386 => '386',
        387 => '387',
        389 => '389',
        420 => '420',
        421 => '421',
        423 => '423',
        441 => '441',
        473 => '473',
        500 => '500',
        501 => '501',
        502 => '502',
        503 => '503',
        504 => '504',
        505 => '505',
        506 => '506',
        507 => '507',
        508 => '508',
        509 => '509',
        590 => '590',
        591 => '591',
        592 => '592',
        593 => '593',
        594 => '594',
        595 => '595',
        596 => '596',
        597 => '597',
        598 => '598',
        599 => '599',
        649 => '649',
        664 => '664',
        670 => '670',
        671 => '671',
        672 => '672',
        673 => '673',
        674 => '674',
        675 => '675',
        676 => '676',
        677 => '677',
        678 => '678',
        679 => '679',
        680 => '680',
        681 => '681',
        682 => '682',
        683 => '683',
        684 => '684',
        685 => '685',
        686 => '686',
        687 => '687',
        688 => '688',
        689 => '689',
        690 => '690',
        691 => '691',
        692 => '692',
        758 => '758',
        767 => '767',
        784 => '784',
        787 => '787',
        809 => '809',
        850 => '850',
        852 => '852',
        853 => '853',
        855 => '855',
        856 => '856',
        868 => '868',
        869 => '869',
        876 => '876',
        880 => '880',
        886 => '886',
        960 => '960',
        961 => '961',
        962 => '962',
        963 => '963',
        964 => '964',
        965 => '965',
        966 => '966',
        967 => '967',
        968 => '968',
        971 => '971',
        972 => '972',
        973 => '973',
        974 => '974',
        975 => '975',
        976 => '976',
        977 => '977',
        992 => '992',
        993 => '993',
        994 => '994',
        995 => '995',
        996 => '996',
        998 => '998',
        95  => '95',
        111 => '111',
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
        'BR' => 'Brasil',
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
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'CI' => 'Cote D\'Ivoire (Ivory Coast)',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'CD' => 'Democratic Republic of the Congo',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'TL' => 'Timor-Leste',
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
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard Island and McDonald Islands',
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
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
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
        'MH' => 'Marshall Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'MD' => 'Moldova',
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
        'KP' => 'North Korea',
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PS' => 'Palestinian Territory',
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
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'SH' => 'Saint Helena',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'PM' => 'Saint Pierre and Miquelon',
        'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia and Montenegro',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'KR' => 'South Korea',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TG' => 'Togo',
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
        'VA' => 'Vatican City State (Holy See)',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'VG' => 'Virgin Islands (British)',
        'VI' => 'Virgin Islands (U.S.)',
        'WF' => 'Wallis and Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'FM' => 'Federated States of Micronesia',
        'GS' => 'S. Georgia and S. Sandwich Islands',
    ];

    protected $languageMap = [
        'EN'     => 'EN',
        'ES'     => 'ES',
        'FR'     => 'FR',
        'ID'     => 'ID',
        'ES-419' => 'LA',
        'MS'     => 'MS',
        'PT'     => 'PT-BR',
        'TH'     => 'TH',
        'ZH'     => 'ZH',
    ];

    public function registerAccount(array $fields)
    {
        $this->http->log('[INFO] ' . __METHOD__);
        $this->ArchiveLogs = true;

        $this->http->log('[INFO] raw fields:');
        $this->http->log(json_encode($fields, JSON_PRETTY_PRINT));

        $fields['PreferredLanguage'] = $this->languageMap[$fields['PreferredLanguage']];
        $fields['TravelPreference'] = $fields['TravelPreference'] - 1;
        $fields['Gender'] = self::$genders[$fields['Gender']];

        // Provider uses wrong country codes for:
        // - Serbia and Montenegro (CS instead of standard RS)
        // - United Kingdom (UK instead of standard GB)
        // Map from our standard ISO code to wrong code used by provider
        $wrongCountryCodesFixingMap = [
            'RS' => 'CS',
            'GB' => 'UK',
        ];

        if (isset($wrongCountryCodesFixingMap[$fields['Country']])) {
            $origCountryCode = $fields['Country'];
            $fields['Country'] = $wrongCountryCodesFixingMap[$fields['Country']];
            $this->logger->debug('Mapped standard country code "' . $origCountryCode . '" to provider code "' . $fields['Country'] . '"');
        }

        $this->http->log('[INFO] fixed fields:');
        $this->http->log(json_encode($fields, JSON_PRETTY_PRINT));

        $this->http->getURL('http://www.vhr.com/joinstep1.aspx');
        $this->http->setCookie('language', 'en');
        $this->http->getURL('http://www.vhr.com/joinstep1.aspx');
        $this->http->log('[INFO] cookies:');
        $this->http->log(print_r($this->http->getCookies('www.vhr.com'), true));

        if (!$this->registerAccountStep($fields, 1, self::$fieldMap1)) {
            return false;
        }

        if (!$this->registerAccountStep($fields, 2, self::$fieldMap2)) {
            return false;
        }

        if (!$this->registerAccountStep($fields, 3, self::$fieldMap3)) {
            return false;
        }

        if (!$this->registerAccountStep($fields, 4, [])) {
            return false;
        }

        $acc = $this->http->findSingleNode('//*[@id = "ctl00_MainContent_CardNumber"]');

        if ($acc) {
            $msg = "Successfull registration. Your confirmation number is $acc.";
            $this->http->log($msg);
            $this->ErrorMessage = $msg;

            return true;
        }

        $this->http->log('unknown error');

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Email' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Email',
                    'Required' => true,
                ],
            'Password' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Password',
                    'Required' => true,
                ],
            'FirstName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'First Name',
                    'Required' => true,
                ],
            'MiddleInitial' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Middle Initial',
                    'Required' => false,
                ],
            'LastName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Last Name',
                    'Required' => true,
                ],
            'Gender' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Gender',
                    'Required' => true,
                    'Options'  => self::$genders,
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
            'AnniversaryMonth' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Anniversary Month',
                    'Required' => false,
                ],
            'AnniversaryDay' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Anniversary Day',
                    'Required' => false,
                ],
            'AnniversaryYear' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Anniversary Year',
                    'Required' => false,
                ],
            'PreferredLanguage' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Preferred Language',
                    'Required' => true,
                    'Options'  => self::$preferredLanguages,
                ],
            'HomePhoneCountryCode' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Home Phone Country Code',
                    'Required' => true,
                    'Options'  => self::$countryPhoneCodes,
                ],
            'HomePhoneCountryNumber' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Home Phone Country Number',
                    'Required' => true,
                ],
            'MobilePhoneCountryCode' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Mobile Phone Country Code',
                    'Required' => false,
                    'Options'  => self::$countryPhoneCodes,
                ],
            'MobilePhoneCountryNumber' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Mobile Phone Country Number',
                    'Required' => false,
                ],
            'AlternatePhoneCountryCode' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Alternate Phone Country Code',
                    'Required' => false,
                    'Options'  => self::$countryPhoneCodes,
                ],
            'AlternatePhoneCountryNumber' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Alternate Phone Country Number',
                    'Required' => false,
                ],
            'Company' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Company',
                    'Required' => false,
                ],
            'AddressLine1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Line 1',
                    'Required' => true,
                ],
            'AddressLine2' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Line 2',
                    'Required' => false,
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
                    'Caption'  => 'State or Province',
                    'Required' => false,
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
            'TravelPreference' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'I primarily travel for',
                    'Required' => true,
                    'Options'  => self::$travelPreferences,
                ],
            'RegistrationCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Registration Code',
                    'Required' => false,
                ],
            'PreferredBrand' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Preferred Brand',
                    'Required' => false,
                    'Options'  => self::$preferredBrands,
                ],
        ];
    }

    private function registerAccountStep($fields, $step, $fieldMap)
    {
        $this->http->log(sprintf('[INFO] %s # %s', __METHOD__, $step));

        if (!$this->http->parseForm('aspnetForm')) {
            $this->http->log("failed to parse step $step form");

            return false;
        }

        $this->http->log("=form $step:");
        $this->http->log(print_r($this->http->Form, true));

        // input errors, not exhaustive
        if ($step === 1) {
            $passLen = strlen(arrayVal($fields, 'Password'));

            if ($passLen < 4 || $passLen > 10) {
                throw new \UserInputError('Password has to be between 4 and 10 characters.');
            }
        } elseif ($step === 2) {
            if (strlen(arrayVal($fields, 'BirthYear')) !== 4) {
                throw new \UserInputError('Birth year has to be 4 characters long.');
            }
            $fields['BirthYear'] = substr($fields['BirthYear'], 2, 2);
            $anniversaryYear = arrayVal($fields, 'AnniversaryYear');

            if ($anniversaryYear && strlen($anniversaryYear) !== 4) {
                throw new \UserInputError('Anniversary year has to be 4 characters long.');
            }
        }

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

        // add stuff to post
        if ($step === 1) {
            $this->http->setInputValue('ctl00$MainContent$btnJoinGHR', 'Join VOILÀ');
        } elseif ($step === 2) {
            $this->http->setInputValue('ctl00$MainContent$btnNext', 'Next');

            if (!arrayVal($this->http->Form, 'ctl00$MainContent$ddlMobilePhone')) {
                $this->http->setInputValue('ctl00$MainContent$ddlMobilePhone', '--');
            }

            if (!arrayVal($this->http->Form, 'ctl00$MainContent$ddlAltTelephone')) {
                $this->http->setInputValue('ctl00$MainContent$ddlAltTelephone', '--');
            }
        } elseif ($step === 3) {
            $this->http->setInputValue('ctl00$MainContent$btnNext', 'Next');
        } elseif ($step === 4) {
            $this->http->setInputValue('ctl00$MainContent$btnNext', 'Approve');
        }

        if (!$this->http->postForm()) {
            $this->http->log("failed to post step $step form");

            return false;
        }
        $errors = $this->http->findNodes('//div[@class = "error_wrap"]');

        if ($errors) {
            $msg = implode('. ', $errors);
            $this->http->log($msg);

            throw new \UserInputError($msg); // Is it always user input error?
        }

        $siteError = $this->http->FindSingleNode('//*[
			(@id = "ctl00_MainContent_lblErrorFound" and @class = "error_msg") or
			@id = "ctl00_MainContent_lblErrorMsg1"
		]');

        if ($siteError) {
            $msg = 'An error occured, please try again later.';
            $this->http->log($msg);

            throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
        }

        return true;
    }
}
