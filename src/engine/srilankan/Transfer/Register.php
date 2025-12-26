<?php

namespace AwardWallet\Engine\srilankan\Transfer;

class Register extends \TAccountCheckerSrilankan
{
    public static $titles = [
        'REV'  => 'Rev',
        'PROF' => 'PROF',
        'DR'   => 'Dr',
        'HON'  => 'Hon',
        'MR'   => 'Mr',
        'MRS'  => 'Mrs',
        'MS'   => 'Ms',
        'EXC'  => 'Exc',
        'VEN'  => 'Ven',
        'MISS' => 'Miss',
        'MAST' => 'Master',
        'ADMI' => 'Admiral',
        'MAJO' => 'Major', // Provider site does not allow to register with this title, though it is shown in select; update: refs 10682#note-17
        'CAPT' => 'Capt',
    ];

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $nationalities = [
        'AD' => 'ANDORRA',
        'AE' => 'UNITED ARAB EMIRATES',
        'AF' => 'AFGHANISTAN',
        'AG' => 'ANTIGUA AND BARBUDA',
        'AI' => 'ANGUILLA',
        'AL' => 'ALBANIA',
        'AM' => 'ARMENIA',
        'AN' => 'NETHERLANDS ANTILLES',
        'AO' => 'ANGOLA',
        'AQ' => 'ANTARCTICA',
        'AR' => 'ARGENTINA',
        'AS' => 'AMERICAN SAMOA',
        'AT' => 'AUSTRIA',
        'AU' => 'AUSTRALIA',
        'AW' => 'ARUBA',
        'AZ' => 'AZERBAIJAN',
        'BA' => 'BOSNIA AND HERZEGOVINA',
        'BB' => 'BARBADOS',
        'BD' => 'BANGLADESH',
        'BE' => 'BELGIUM',
        'BF' => 'BURKINA FASO',
        'BG' => 'BULGARIA',
        'BH' => 'BAHRAIN',
        'BI' => 'BURUNDI',
        'BJ' => 'BENIN',
        'BM' => 'BERMUDA',
        'BN' => 'BRUNEI DARUSSALAM',
        'BO' => 'BOLIVIA',
        'BR' => 'BRAZIL',
        'BS' => 'BAHAMAS',
        'BT' => 'BHUTAN',
        'BV' => 'BOUVET ISLAND',
        'BW' => 'BOTSWANA',
        'BY' => 'BELARUS',
        'BZ' => 'BELIZE',
        'CA' => 'CANADA',
        'CC' => 'COCOS / KEELING ISLANDS',
        'CD' => 'DEMOCRATIC REPUBLIC OF CONGO',
        'CE' => 'CANARY ISLANDS',
        'CF' => 'CENTRAL AFRICAN REPUBLIC',
        'CG' => 'CONGO',
        'CH' => 'SWITZERLAND',
        'CI' => 'COTE D IVOIRE',
        'CK' => 'COOK ISLANDS',
        'CL' => 'CHILE',
        'CM' => 'CAMEROON',
        'CN' => 'CHINA',
        'CO' => 'COLOMBIA',
        'CR' => 'COSTA RICA',
        'CS' => 'SERBIA AND MONTENEGRO',
        'CU' => 'CUBA',
        'CV' => 'CAPE VERDE',
        'CX' => 'CHRISTMAS ISLANDS',
        'CY' => 'CYPRUS',
        'CZ' => 'CZECH REPUBLIC',
        'DE' => 'GERMANY',
        'DJ' => 'DJIBOUTI',
        'DK' => 'DENMARK',
        'DM' => 'DOMINICA',
        'DO' => 'DOMINICAN REPUBLIC',
        'DZ' => 'ALGERIA',
        'EC' => 'ECUADOR',
        'EE' => 'ESTONIA',
        'EG' => 'EGYPT',
        'EH' => 'WESTERN SAHARA',
        'ER' => 'ERITREA',
        'ES' => 'SPAIN',
        'ET' => 'ETHIOPIA',
        'FI' => 'FINLAND',
        'FJ' => 'FIJI',
        'FK' => 'FALKLAND ISLANDS',
        'FM' => 'MICRONESIA',
        'FO' => 'FAROE ISLANDS',
        'FR' => 'FRANCE',
        'FX' => 'FX3',
        'GA' => 'GABON',
        'GB' => 'UNITED KINGDOM',
        'GD' => 'GRENADA',
        'GE' => 'GEORGIA',
        'GF' => 'FRENCH GUIANA',
        'GG' => 'GUERNSEY',
        'GH' => 'GHANA',
        'GI' => 'GIBRALTAR',
        'GL' => 'GREENLAND',
        'GM' => 'GAMBIA',
        'GN' => 'GUINEA',
        'GP' => 'GUADELOUPE',
        'GQ' => 'EQUATORIAL GUINEA',
        'GR' => 'GREECE',
        'GS' => 'SOUTH GEORGIA',
        'GT' => 'GUATEMALA',
        'GU' => 'GUAM',
        'GW' => 'GUINEA BISSAU',
        'GY' => 'GUYANA',
        'HK' => 'HONG KONG',
        'HM' => 'HEARD AND MCDONALD ISLANDS',
        'HN' => 'HONDURAS',
        'HR' => 'CROATIA',
        'HT' => 'HAITI',
        'HU' => 'HUNGARY',
        'ID' => 'INDONESIA',
        'IE' => 'IRELAND',
        'IL' => 'ISRAEL',
        'IM' => 'ISLE OF MAN',
        'IN' => 'INDIA',
        'IO' => 'BRITISH INDIAN OCEAN TERRITORY',
        'IQ' => 'IRAQ',
        'IR' => 'IRAN',
        'IS' => 'ICELAND',
        'IT' => 'ITALY',
        'JE' => 'JERSEY',
        'JM' => 'JAMAICA',
        'JO' => 'JORDAN',
        'JP' => 'JAPAN',
        'KE' => 'KENYA',
        'KG' => 'KYRGYZSTAN',
        'KH' => 'CAMBODIA',
        'KI' => 'KIRIBATI',
        'KM' => 'COMOROS',
        'KN' => 'ST KITTS AND NEVIS',
        'KP' => 'DEMOCRATIC PEOPLES REPUBLIC OF KOREA',
        'KR' => 'REPUBLIC OF KOREA (SOUTH KOREA)',
        'KW' => 'KUWAIT',
        'KY' => 'CAYMAN ISLANDS',
        'KZ' => 'KAZAKSTAN',
        'LA' => 'LAO PEOPLES DEMOCRATIC REPUBLIC',
        'LB' => 'LEBANON',
        'LC' => 'SAINT LUCIA',
        'LI' => 'LIECHTENSTEIN',
        'LK' => 'SRI LANKA',
        'LR' => 'LIBERIA',
        'LS' => 'LESOTHO',
        'LT' => 'LITHUANIA',
        'LU' => 'LUXEMBOURG',
        'LV' => 'LATVIA',
        'LY' => 'LIBYAN ARAB JAMAHIRIYA',
        'MA' => 'MOROCCO',
        'MC' => 'MONACO',
        'MD' => 'MOLDOVA REP OF',
        'MG' => 'MADAGASCAR',
        'MH' => 'MARSHALL ISLANDS',
        'MK' => 'MACEDONIA FORMER YUGOSLAV REP',
        'ML' => 'MALI',
        'MM' => 'MYANMAR',
        'MN' => 'MONGOLIA',
        'MO' => 'MACAU',
        'MP' => 'NORTHERN MARIANA ISLANDS',
        'MQ' => 'MARTINIQUE',
        'MR' => 'MAURITANIA',
        'MS' => 'MONTSERRAT',
        'MT' => 'MALTA',
        'MU' => 'MAURITIUS',
        'MV' => 'MALDIVES',
        'MW' => 'MALAWI',
        'MX' => 'MEXICO',
        'MY' => 'MALAYSIA',
        'MZ' => 'MOZAMBIQUE',
        'NA' => 'NAMIBIA',
        'NC' => 'NEW CALEDONIA',
        'ND' => 'ND',
        'NE' => 'NIGER',
        'NF' => 'NORFOLK ISLAND',
        'NG' => 'NIGERIA',
        'NI' => 'NICARAGUA',
        'NL' => 'NETHERLANDS',
        'NO' => 'NORWAY',
        'NP' => 'NEPAL',
        'NR' => 'NAURU',
        'NT' => 'AUSTRALIA GOLD COAST',
        'NU' => 'NIUE',
        'NZ' => 'NEW ZEALAND',
        'OM' => 'OMAN',
        'PA' => 'PANAMA',
        'PE' => 'PERU',
        'PF' => 'FRENCH POLYNESIA',
        'PG' => 'PAPUA NEW GUINEA',
        'PH' => 'PHILIPPINES',
        'PK' => 'PAKISTAN',
        'PL' => 'POLAND',
        'PM' => 'ST PIERRE AND MIQUELON',
        'PN' => 'PITCAIRN ISLAND',
        'PR' => 'PUERTO RICO',
        'PS' => 'PALESTINIAN TERRITORIES',
        'PT' => 'PORTUGAL',
        'PW' => 'PALAU',
        'PY' => 'PARAGUAY',
        'QA' => 'QATAR',
        'RE' => 'REUNION',
        'RK' => 'KOSOVO',
        'RO' => 'ROMANIA',
        'RS' => 'REPUBLIC OF SERBIA',
        'RU' => 'RUSSIAN FEDERATION',
        'RW' => 'RWANDA',
        'SA' => 'SAUDI ARABIA',
        'SB' => 'SOLOMON ISLANDS',
        'SC' => 'SEYCHELLES',
        'SD' => 'SUDAN',
        'SE' => 'SWEDEN',
        'SG' => 'SINGAPORE',
        'SH' => 'ST HELENA',
        'SI' => 'SLOVENIA',
        'SJ' => 'SVALBARD AND JAN MEYEN ISLANDS',
        'SK' => 'SLOVAKIA',
        'SL' => 'SIERRA LEONE',
        'SM' => 'SAN MARINO',
        'SN' => 'SENEGAL',
        'SO' => 'SOMALIA',
        'SR' => 'SURINAM',
        'ST' => 'SAO TOME AND PRINCIPE',
        'SV' => 'EL SALVADOR',
        'SY' => 'SYRIAN ARAB REPUBLIC',
        'SZ' => 'SWAZILAND',
        'TC' => 'TURKS AND CAICOS ISLANDS',
        'TD' => 'CHAD',
        'TF' => 'FRENCH SOUTHERN TERRITORIES',
        'TG' => 'TOGO',
        'TH' => 'THAILAND',
        'TI' => 'TI7',
        'TJ' => 'TAJIKISTAN',
        'TK' => 'TOKELAU',
        'TM' => 'TURKMENISTAN',
        'TN' => 'TUNISIA',
        'TO' => 'TONGA',
        'TP' => 'EAST TIMOR',
        'TR' => 'TURKEY',
        'TT' => 'TRINIDAD AND TOBAGO',
        'TV' => 'TUVALU',
        'TW' => 'TAIWAN',
        'TZ' => 'TANZANIA',
        'UA' => 'UKRAINE',
        'UG' => 'UGANDA',
        'UM' => 'UNITED STATES MINOR OUTLYING ISLANDS',
        'US' => 'UNITED STATES OF AMERICA',
        'UY' => 'URUGUAY',
        'UZ' => 'UZBEKISTAN',
        'VA' => 'VATICAN CITY STATE',
        'VC' => 'ST VINCENT AND THE GRENADINES',
        'VE' => 'VENEZUELA',
        'VG' => 'VIRGIN ISLANDS',
        'VI' => 'VIRGIN ISLANDS ... U.S.',
        'VN' => 'VIETNAM',
        'VU' => 'VANUATU',
        'WF' => 'WALLIS AND FUTUNA ISLANDS',
        'WS' => 'SAMOA',
        'YE' => 'REPUBLIC OF YEMEN',
        'YT' => 'MAYOTTE',
        'YU' => 'YUGOSLAVIA',
        'ZA' => 'SOUTH AFRICA',
        'ZM' => 'ZAMBIA',
        'ZR' => 'ZAIRE',
        'ZW' => 'ZIMBABWE',
    ];

    public static $countries = [
        'AF' => 'AFGHANISTAN',
        'AL' => 'ALBANIA',
        'DZ' => 'ALGERIA',
        'AS' => 'AMERICAN SAMOA',
        'AD' => 'ANDORRA',
        'AO' => 'ANGOLA',
        'AI' => 'ANGUILLA',
        'AQ' => 'ANTARCTICA',
        'AG' => 'ANTIGUA AND BARBUDA',
        'AR' => 'ARGENTINA',
        'AM' => 'ARMENIA',
        'AW' => 'ARUBA',
        'AU' => 'AUSTRALIA',
        'AT' => 'AUSTRIA',
        'AZ' => 'AZERBAIJAN',
        'BS' => 'BAHAMAS',
        'BH' => 'BAHRAIN',
        'BD' => 'BANGLADESH',
        'BB' => 'BARBADOS',
        'BY' => 'BELARUS',
        'BE' => 'BELGIUM',
        'BZ' => 'BELIZE',
        'BJ' => 'BENIN',
        'BM' => 'BERMUDA',
        'BT' => 'BHUTAN',
        'BO' => 'BOLIVIA',
        'BA' => 'BOSNIA AND HERZEGOVINA',
        'BW' => 'BOTSWANA',
        'BV' => 'BOUVET ISLAND',
        'BR' => 'BRAZIL',
        'IO' => 'BRITISH INDIAN OCEAN TERRITORY',
        'BN' => 'BRUNEI DARUSSALAM',
        'BG' => 'BULGARIA',
        'BF' => 'BURKINA FASO',
        'BI' => 'BURUNDI',
        'KH' => 'CAMBODIA',
        'CM' => 'CAMEROON',
        'CA' => 'CANADA',
        'CV' => 'CAPE VERDE',
        'KY' => 'CAYMAN ISLANDS',
        'CF' => 'CENTRAL AFRICAN REPUBLIC',
        'TD' => 'CHAD',
        'CL' => 'CHILE',
        'CN' => 'CHINA',
        'CX' => 'CHRISTMAS ISLANDS',
        'CC' => 'COCOS / KEELING ISLANDS',
        'CO' => 'COLOMBIA',
        'KM' => 'COMOROS',
        'CG' => 'CONGO',
        'CK' => 'COOK ISLANDS',
        'CR' => 'COSTA RICA',
        'CI' => 'COTE D IVOIRE',
        'HR' => 'CROATIA',
        'CU' => 'CUBA',
        'CY' => 'CYPRUS',
        'CZ' => 'CZECH REPUBLIC',
        'KP' => 'DEMOCRATIC PEOPLES REPUBLIC OF KOREA',
        'CD' => 'DEMOCRATIC REPUBLIC OF CONGO',
        'DK' => 'DENMARK',
        'DJ' => 'DJIBOUTI',
        'DM' => 'DOMINICA',
        'DO' => 'DOMINICAN REPUBLIC',
        'TL' => 'EAST TIMOR',
        'EC' => 'ECUADOR',
        'EG' => 'EGYPT',
        'SV' => 'EL SALVADOR',
        'GQ' => 'EQUATORIAL GUINEA',
        'ER' => 'ERITREA',
        'EE' => 'ESTONIA',
        'ET' => 'ETHIOPIA',
        'FK' => 'FALKLAND ISLANDS',
        'FO' => 'FAROE ISLANDS',
        'FJ' => 'FIJI',
        'FI' => 'FINLAND',
        'FR' => 'FRANCE',
        'GF' => 'FRENCH GUIANA',
        'PF' => 'FRENCH POLYNESIA',
        'TF' => 'FRENCH SOUTHERN TERRITORIES',
        'GA' => 'GABON',
        'GM' => 'GAMBIA',
        'GE' => 'GEORGIA',
        'DE' => 'GERMANY',
        'GH' => 'GHANA',
        'GI' => 'GIBRALTAR',
        'GR' => 'GREECE',
        'GL' => 'GREENLAND',
        'GD' => 'GRENADA',
        'GP' => 'GUADELOUPE',
        'GU' => 'GUAM',
        'GT' => 'GUATEMALA',
        'GG' => 'GUERNSEY',
        'GN' => 'GUINEA',
        'GW' => 'GUINEA BISSAU',
        'GY' => 'GUYANA',
        'HT' => 'HAITI',
        'HM' => 'HEARD AND MCDONALD ISLANDS',
        'HN' => 'HONDURAS',
        'HK' => 'HONG KONG',
        'HU' => 'HUNGARY',
        'IS' => 'ICELAND',
        'IN' => 'INDIA',
        'ID' => 'INDONESIA',
        'IR' => 'IRAN',
        'IQ' => 'IRAQ',
        'IE' => 'IRELAND',
        'IM' => 'ISLE OF MAN',
        'IL' => 'ISRAEL',
        'IT' => 'ITALY',
        'JM' => 'JAMAICA',
        'JP' => 'JAPAN',
        'JE' => 'JERSEY',
        'JO' => 'JORDAN',
        'KZ' => 'KAZAKSTAN',
        'KE' => 'KENYA',
        'KI' => 'KIRIBATI',
        'KW' => 'KUWAIT',
        'KG' => 'KYRGYZSTAN',
        'LA' => 'LAO PEOPLES DEMOCRATIC REPUBLIC',
        'LV' => 'LATVIA',
        'LB' => 'LEBANON',
        'LS' => 'LESOTHO',
        'LR' => 'LIBERIA',
        'LY' => 'LIBYAN ARAB JAMAHIRIYA',
        'LI' => 'LIECHTENSTEIN',
        'LT' => 'LITHUANIA',
        'LU' => 'LUXEMBOURG',
        'MO' => 'MACAU',
        'MK' => 'MACEDONIA FORMER YUGOSLAV REP',
        'MG' => 'MADAGASCAR',
        'MW' => 'MALAWI',
        'MY' => 'MALAYSIA',
        'MV' => 'MALDIVES',
        'ML' => 'MALI',
        'MT' => 'MALTA',
        'MH' => 'MARSHALL ISLANDS',
        'MQ' => 'MARTINIQUE',
        'MR' => 'MAURITANIA',
        'MU' => 'MAURITIUS',
        'YT' => 'MAYOTTE',
        'MX' => 'MEXICO',
        'FM' => 'MICRONESIA',
        'MD' => 'MOLDOVA REP OF',
        'MC' => 'MONACO',
        'MN' => 'MONGOLIA',
        'MS' => 'MONTSERRAT',
        'MA' => 'MOROCCO',
        'MZ' => 'MOZAMBIQUE',
        'MM' => 'MYANMAR',
        'NA' => 'NAMIBIA',
        'NR' => 'NAURU',
        'NP' => 'NEPAL',
        'NL' => 'NETHERLANDS',
        'NC' => 'NEW CALEDONIA',
        'NZ' => 'NEW ZEALAND',
        'NI' => 'NICARAGUA',
        'NE' => 'NIGER',
        'NG' => 'NIGERIA',
        'NU' => 'NIUE',
        'NF' => 'NORFOLK ISLAND',
        'MP' => 'NORTHERN MARIANA ISLANDS',
        'NO' => 'NORWAY',
        'OM' => 'OMAN',
        'PK' => 'PAKISTAN',
        'PW' => 'PALAU',
        'PS' => 'PALESTINIAN TERRITORIES',
        'PA' => 'PANAMA',
        'PG' => 'PAPUA NEW GUINEA',
        'PY' => 'PARAGUAY',
        'PE' => 'PERU',
        'PH' => 'PHILIPPINES',
        'PN' => 'PITCAIRN ISLAND',
        'PL' => 'POLAND',
        'PT' => 'PORTUGAL',
        'PR' => 'PUERTO RICO',
        'QA' => 'QATAR',
        'KR' => 'REPUBLIC OF KOREA (SOUTH KOREA)',
        'YE' => 'REPUBLIC OF YEMEN',
        'RE' => 'REUNION',
        'RO' => 'ROMANIA',
        'RU' => 'RUSSIAN FEDERATION',
        'RW' => 'RWANDA',
        'LC' => 'SAINT LUCIA',
        'WS' => 'SAMOA',
        'SM' => 'SAN MARINO',
        'ST' => 'SAO TOME AND PRINCIPE',
        'SA' => 'SAUDI ARABIA',
        'SN' => 'SENEGAL',
        'RS' => 'REPUBLIC OF SERBIA',
        'SC' => 'SEYCHELLES',
        'SL' => 'SIERRA LEONE',
        'SG' => 'SINGAPORE',
        'SK' => 'SLOVAKIA',
        'SI' => 'SLOVENIA',
        'SB' => 'SOLOMON ISLANDS',
        'SO' => 'SOMALIA',
        'ZA' => 'SOUTH AFRICA',
        'GS' => 'SOUTH GEORGIA',
        'ES' => 'SPAIN',
        'LK' => 'SRI LANKA',
        'SH' => 'ST HELENA',
        'KN' => 'ST KITTS AND NEVIS',
        'PM' => 'ST PIERRE AND MIQUELON',
        'VC' => 'ST VINCENT AND THE GRENADINES',
        'SD' => 'SUDAN',
        'SR' => 'SURINAM',
        'SJ' => 'SVALBARD AND JAN MEYEN ISLANDS',
        'SZ' => 'SWAZILAND',
        'SE' => 'SWEDEN',
        'CH' => 'SWITZERLAND',
        'SY' => 'SYRIAN ARAB REPUBLIC',
        'TW' => 'TAIWAN',
        'TJ' => 'TAJIKISTAN',
        'TZ' => 'TANZANIA',
        'TH' => 'THAILAND',
        'TG' => 'TOGO',
        'TK' => 'TOKELAU',
        'TO' => 'TONGA',
        'TT' => 'TRINIDAD AND TOBAGO',
        'TN' => 'TUNISIA',
        'TR' => 'TURKEY',
        'TM' => 'TURKMENISTAN',
        'TC' => 'TURKS AND CAICOS ISLANDS',
        'TV' => 'TUVALU',
        'UG' => 'UGANDA',
        'UA' => 'UKRAINE',
        'AE' => 'UNITED ARAB EMIRATES',
        'GB' => 'UNITED KINGDOM',
        'UM' => 'UNITED STATES MINOR OUTLYING ISLANDS',
        'US' => 'UNITED STATES OF AMERICA',
        'UY' => 'URUGUAY',
        'UZ' => 'UZBEKISTAN',
        'VU' => 'VANUATU',
        'VA' => 'VATICAN CITY STATE',
        'VE' => 'VENEZUELA',
        'VN' => 'VIETNAM',
        'VG' => 'VIRGIN ISLANDS',
        'VI' => 'VIRGIN ISLANDS ... U.S.',
        'WF' => 'WALLIS AND FUTUNA ISLANDS',
        'EH' => 'WESTERN SAHARA',
        'ZM' => 'ZAMBIA',
        'ZW' => 'ZIMBABWE',
    ];

    public static $addressTypes = [
        'HOME' => 'Home',
        'WORK' => 'Business',
    ];

    public static $contactMethods = [
        'POST'   => 'Post',
        'E-MAIL' => 'E-Mail',
    ];

    public static $phoneTypes = [
        'MOBIL' => 'Mobile',
        'HOME'  => 'Home',
        'WORK'  => 'Work',
    ];

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;

        $this->http->GetURL('https://www.flysmiles.com/home/register');

        $url = 'https://www.flysmiles.com/home/RegisterUser';

        $queryParams = []; // TODO: Form query parameters in more clever way

        // Provider uses wrong country codes EAST TIMOR (TP instead of standard TL)
        // Map from our standard ISO code to wrong code used by provider
        if ($fields['Country'] == 'TL') {
            $fields['Country'] = 'TP';
            $this->logger->debug('Mapped standard country code "TL" to provider code "TP"');
        }

        foreach ($this->inputFieldsMap() as $awKey => $provKeys) {
            if (!isset($fields[$awKey]) or $provKeys === false) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $queryParams[] = $provKey . '=' . $fields[$awKey];
            }
        }

        $addFields = [
            'mn'         => '',
            'com'        => '',
            'dep'        => '',
            'bc'         => '',
            'jt'         => '',
            'add2'       => '',
            'add3'       => '',
            'sta'        => '',
            'city'       => '',
            'tac'        => 'undefined',
            'crdno'      => '',
            'pintrs'     => '',
            'pseat'      => '',
            'pmeal'      => '',
            'pdrink'     => '',
            'psport'     => '',
            'pmovie'     => '',
            'ffpmail'    => 'Y',
            'nonffpmail' => 'Y',
            'recmem'     => '',
        ];

        foreach ($addFields as $key => $value) {
            $queryParams[] = $key . '=' . $value;
        }

        $d = sprintf('%02d', $fields['BirthDay']);
        $m = sprintf('%02d', $fields['BirthMonth']);
        $y = sprintf('%02d', $fields['BirthYear']);
        $queryParams[] = "dob=$m/$d/$y";

        $status = $this->http->PostURL($url, implode("&", $queryParams));

        if (!$status) {
            $this->http->Log('Failed to post account registration form', LOG_LEVEL_ERROR);

            return false;
        }

        $response = json_decode($this->http->Response['body'], true);

        if ($response === null) {
            $this->http->Log('Failed to JSON decode response');

            return false;
        }

        if ($number = $response['FFCardNumber']) {
            $successMessage = "Your FlySmiLes Membership Number is: $number";
            $this->ErrorMessage = $successMessage;
            $this->http->Log($successMessage);

            return true;
        } elseif ($error = trim($response['ErrorMsg'])) {
            $this->http->Log($error, LOG_LEVEL_ERROR);

            if ($error == 'Duplicate record found') {
                throw new \ProviderError($error);
            } else {
                throw new \UserInputError($error);
            } // Is it always user input error?
        } else {
            $this->http->Log('Unknown account registration error');

            return false;
        }
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
                'Caption'  => 'First Name (name as on your passport)',
                'Required' => true,
            ],
            'LastName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Family Name (name as on your passport)',
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
                'Caption'  => 'Month of Birth Date',
                'Required' => true,
            ],
            'BirthDay' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Day of Birth Date',
                'Required' => true,
            ],
            'BirthYear' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Year of Birth Date',
                'Required' => true,
            ],
            'Nationality' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Nationality',
                'Required' => true,
                'Options'  => self::$nationalities,
            ],
            'Country' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Country of Residence',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'Password' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Password (should consist of 8 characters and including one numeric, one character in lower case and should not contain any special characters)',
                'Required' => true,
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
                'Options'  => self::$addressTypes,
            ],
            'ContactMethod' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Your Preferred Contact Method',
                'Required' => true,
                'Options'  => self::$contactMethods,
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
                'Required' => false,
            ],
            'StateOrProvince' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Region (required for Australia, Canada, US)',
                'Required' => false,
            ],
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'P.O. Box No. ',
                'Required' => true,
            ],
            'PhoneType' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Phone Type ',
                'Required' => false,
                'Options'  => self::$phoneTypes,
            ],
            'PhoneCountryCodeNumeric' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Phone Country Code Numeric',
                'Required' => false,
            ],
            'PhoneAreaCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Phone Area Code',
                'Required' => false,
            ],
            'PhoneLocalNumber' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Phone Number',
                'Required' => false,
            ],
        ];
    }

    public static function inputFieldsMap()
    {
        return [
            'Title'       => 'ti',
            'FirstName'   => 'fn',
            'LastName'    => 'ln',
            'Gender'      => 'gen',
            'BirthMonth'  => false,
            'BirthDay'    => false,
            'BirthYear'   => false,
            'Nationality' => 'nat',
            'Country'     => [
                'cor',
                'acor',
            ],
            'Password'                => 'pwd',
            'Email'                   => 'em',
            'AddressType'             => 'pma',
            'ContactMethod'           => 'pcm',
            'AddressLine1'            => 'add1',
            'City'                    => 'tow',
            'StateOrProvince'         => 'reg',
            'PostalCode'              => 'pob',
            'PhoneType'               => 'tpt',
            'PhoneCountryCodeNumeric' => 'tcc',
            'PhoneAreaCode'           => 'tac',
            'PhoneLocalNumber'        => 'tn',
        ];
    }
}
