<?php

namespace AwardWallet\Engine\harrah\Transfer;

class Register extends \TAccountCheckerHarrah
{
    public static $fieldMap = [
        'FirstName'       => 'firstName',
        'LastName'        => 'lastName',
        'BirthMonth'      => 'monthOfBirth',
        'BirthDay'        => 'dayOfBirth',
        'BirthYear'       => 'dob',
        'Gender'          => 'gender',
        'Country'         => 'country',
        'AddressLine1'    => 'address1',
        'City'            => 'city',
        'StateOrProvince' => 'state',
        'PostalCode'      => 'zipcode',
        // 'PhoneType' => '',
        // 'PhoneCountryCodeNumeric' => '',
        // 'PhoneAreaCode' => '',
        // 'PhoneLocalNumber' => '',
        'Email'    => 'email',
        'Username' => 'username',
        'Password' => [
            'password1',
            'password2',
        ],
        'SecurityQuestionType1'   => 'question1',
        'SecurityQuestionAnswer1' => 'answer1',
    ];

    public static $genderMap = [
        'M' => 'male',
        'F' => 'female',
    ];

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $countries = [
        'US' => 'UNITED STATES',
        'CA' => 'CANADA',
        'AF' => 'AFGHANISTAN',
        'AX' => 'ALAND ISLANDS',
        'AL' => 'ALBANIA',
        'DZ' => 'ALGERIA',
        'AD' => 'ANDORRA',
        'AO' => 'ANGOLA',
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
        'BA' => 'BOSNIA HERZEGOVINA',
        'BW' => 'BOTSWANA',
        'BV' => 'BOUVET ISLAND',
        'BR' => 'BRAZIL',
        'IO' => 'BRITISH INDIAN OCEAN TERRITORY',
        'VG' => 'BRITISH VIRGIN ISLANDS',
        'BN' => 'BRUNEI',
        'BG' => 'BULGARIA',
        'BF' => 'BURKINA FASO',
        'BI' => 'BURUNDI',
        'KH' => 'CAMBODIA',
        'CM' => 'CAMEROON',
        'CV' => 'CAPE VERDE',
        'KY' => 'CAYMAN ISLANDS',
        'CF' => 'CENTRAL AFRICAN REPUBLIC',
        'TD' => 'CHAD',
        'CL' => 'CHILE',
        'CN' => 'CHINA',
        'CX' => 'CHRISTMAS ISLAND',
        'CC' => 'COCOS ISLANDS',
        'CO' => 'COLOMBIA',
        'CG' => 'CONGO',
        'CD' => 'CONGO, DEMOCRATIC REPUBLIC OF',
        'CK' => 'COOK ISLANDS',
        'CR' => 'COSTA RICA',
        'HR' => 'CROATIA',
        'CU' => 'CUBA',
        'CY' => 'CYPRUS',
        'CZ' => 'CZECH REPUBLIC',
        'DK' => 'DENMARK',
        'DJ' => 'DJIBOUTI',
        'DM' => 'DOMINICA',
        'DO' => 'DOMINICAN REPUBLIC',
        'EC' => 'ECUADOR',
        'EG' => 'EGYPT',
        'SV' => 'EL SALVADOR',
        'GQ' => 'EQUATORIAL GUINEA',
        'ER' => 'ERITREA',
        'EE' => 'ESTONIA',
        'ET' => 'ETHIOPIA',
        'FO' => 'FAROE ISLANDS',
        'FJ' => 'FIJI ISLANDS',
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
        'GT' => 'GUATEMALA',
        'GG' => 'GUERNSEY',
        'GN' => 'GUINEA',
        'GW' => 'GUINEA-BISSAU',
        'GY' => 'GUYANA',
        'HT' => 'HAITI',
        'VA' => 'VATICAN CITY STATE',
        'HN' => 'HONDURAS',
        'HK' => 'HONG KONG',
        'HU' => 'HUNGARY',
        'IS' => 'ICELAND',
        'IN' => 'INDIA',
        'ID' => 'INDONESIA',
        'IE' => 'IRELAND',
        'IQ' => 'IRAQ',
        'IM' => 'ISLE OF MAN',
        'IL' => 'ISRAEL',
        'IT' => 'ITALY',
        'JM' => 'JAMAICA',
        'JP' => 'JAPAN',
        'JE' => 'JERSEY',
        'JO' => 'JORDAN',
        'KZ' => 'KAZAKHSTAN',
        'KE' => 'KENYA',
        'KI' => 'KIRIBATI',
        'KW' => 'KUWAIT',
        'KG' => 'KYRGYZSTAN',
        'LA' => 'LAO PEOPLE\'S DEMOCRATIC REPUBLIC',
        'LV' => 'LATVIA',
        'LB' => 'LEBANON',
        'LR' => 'LIBERIA',
        'LY' => 'LIBYAN ARAB JAMAHIRIYA',
        'LI' => 'LIECHTENSTEIN',
        'LT' => 'LITHUANIA',
        'LU' => 'LUXEMBOURG',
        'MO' => 'MACAU',
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
        'MD' => 'MOLDOVA',
        'MC' => 'MONACO',
        'MN' => 'MONGOLIA',
        'ME' => 'MONTENEGRO',
        'MS' => 'MONTSERRAT',
        'MA' => 'MOROCCO',
        'MZ' => 'MOZAMBIQUE',
        'MM' => 'MYANMAR',
        'NA' => 'NAMIBIA',
        'NR' => 'NAURU',
        'NP' => 'NEPAL',
        'NL' => 'NETHERLANDS',
        'NC' => 'NEW CALEDONIA',
        'PG' => 'PAPUA NEW GUINEA',
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
        'PS' => 'PALESTINIAN TERRITORY, OCCUPIED',
        'PA' => 'PANAMA',
        'PY' => 'PARAGUAY',
        'PE' => 'PERU',
        'PN' => 'PITCAIRN',
        'PL' => 'POLAND',
        'PT' => 'PORTUGAL',
        'QA' => 'QATAR',
        'RE' => 'REUNION ISLAND',
        'RO' => 'ROMANIA',
        'RU' => 'RUSSIA',
        'RW' => 'RWANDA',
        'BL' => 'SAINT BARTHELEMY',
        'SH' => 'SAINT HELENA',
        'KN' => 'SAINT KITTS AND NEVIS',
        'LC' => 'SAINT LUCIA',
        'MF' => 'SAINT MARTIN',
        'PM' => 'SAINT PIERRE AND MIQUELON',
        'VC' => 'SAINT VINCENT AND THE GRENADINES',
        'WS' => 'SAMOA',
        'SM' => 'SAN MARINO',
        'ST' => 'SAO TOME AND PRINCIPE',
        'SA' => 'SAUDI ARABIA',
        'SN' => 'SENEGAL',
        'RS' => 'SERBIA',
        'SL' => 'SIERRA LEONE',
        'SG' => 'SINGAPORE',
        'SK' => 'SLOVAKIA',
        'SI' => 'SLOVENIA',
        'SB' => 'SOLOMON ISLAND',
        'SO' => 'SOMALIA',
        'ZA' => 'SOUTH AFRICA',
        'ES' => 'SPAIN',
        'LK' => 'SRI LANKA',
        'SD' => 'SUDAN',
        'SR' => 'SURINAME',
        'SJ' => 'SVALBARD AND JAN MAYEN',
        'SZ' => 'SWAZILAND',
        'SE' => 'SWEDEN',
        'CH' => 'SWITZERLAND',
        'SY' => 'SYRIA',
        'TW' => 'TAIWAN',
        'TJ' => 'TAJIKISTAN',
        'TZ' => 'TANZANIA',
        'TH' => 'THAILAND',
        'TL' => 'TIMOR-LESTE',
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
        'UY' => 'URUGUAY',
        'UZ' => 'UZBEKISTAN',
        'VU' => 'VANUATU',
        'VE' => 'VENEZUELA',
        'VN' => 'VIETNAM',
        'WF' => 'WALLIS AND FUTUNA ISLANDS',
        'EH' => 'WESTERN SAHARA',
        'YE' => 'YEMEN',
        'ZM' => 'ZAMBIA',
        'ZW' => 'ZIMBABWE',
        'KM' => 'COMOROS ISL',
        'CI' => 'COTE D\'IVOIRE (IVORY COAST)',
        'FK' => 'FALKLAND ISLANDS/MALVINAS',
        'HM' => 'HEARD ISLAND AND MCDONALD ISL',
        'KP' => 'KOREA, DEM PPLE\'S REP (NORTH)',
        'KR' => 'KOREA, REP OF (SOUTH)',
        'LS' => 'LESOTO',
        'MK' => 'MACEDONIA, YUGOSLAV REPUBLIC',
        'FM' => 'MICRONESIA, FEDERATED STATES',
        'PH' => 'PHILLIPPINES',
        'SC' => 'SEYCHELLES ISLAND',
        'GS' => 'SOUTH GEORGIA/SANDWICH ISLANDS',
        'UM' => 'UNITED STATES MINOR ISLANDS',
    ];

    public static $countriesMap = [
        'US' => 'USA',
        'CA' => 'CAN',
        'AF' => 'AF',
        'AX' => 'AX',
        'AL' => 'A2',
        'DZ' => 'AG',
        'AD' => 'AO',
        'AO' => 'AV',
        'AQ' => 'AQ',
        'AG' => 'A3',
        'AR' => 'A1',
        'AM' => 'AI',
        'AW' => 'AW',
        'AU' => 'AU',
        'AT' => 'AT',
        'AZ' => 'AJ',
        'BS' => 'BA',
        'BH' => 'B6',
        'BD' => 'BD',
        'BB' => 'BR',
        'BY' => 'BY',
        'BE' => 'BL',
        'BZ' => 'BE',
        'BJ' => 'DA',
        'BM' => 'BM',
        'BT' => 'BH',
        'BO' => 'BO',
        'BA' => 'BK',
        'BW' => 'BT',
        'BV' => 'BI',
        'BR' => 'BZ',
        'IO' => 'IO',
        'VG' => 'VG',
        'BN' => 'BX',
        'BG' => 'BG',
        'BF' => 'UP',
        'BI' => 'BN',
        'KH' => 'CM',
        'CM' => 'CE',
        'CV' => 'CP',
        'KY' => 'KB',
        'CF' => 'CL',
        'TD' => 'CH',
        'CL' => 'CI',
        'CN' => 'CD',
        'CX' => 'KT',
        'CC' => 'C1',
        'CO' => 'CU',
        'CG' => 'CG',
        'CD' => 'C2',
        'CK' => 'CK',
        'CR' => 'CS',
        'HR' => 'HR',
        'CU' => 'CB',
        'CY' => 'DW',
        'CZ' => 'CZ',
        'DK' => 'DM',
        'DJ' => 'DJ',
        'DM' => 'DI',
        'DO' => 'DO',
        'EC' => 'EC',
        'EG' => 'EG',
        'SV' => 'EL',
        'GQ' => 'EQ',
        'ER' => 'ER',
        'EE' => 'EO',
        'ET' => 'EH',
        'FO' => 'F1',
        'FJ' => 'FJ',
        'FI' => 'FN',
        'FR' => 'FR',
        'GF' => 'FC',
        'PF' => 'PF',
        'TF' => 'TF',
        'GA' => 'GB',
        'GM' => 'GM',
        'GE' => 'GO',
        'DE' => 'GE',
        'GH' => 'GH',
        'GI' => 'GR',
        'GR' => 'GC',
        'GL' => 'GN',
        'GD' => 'GQ',
        'GP' => 'GP',
        'GT' => 'GF',
        'GG' => 'GK',
        'GN' => 'GG',
        'GW' => 'GW',
        'GY' => 'GY',
        'HT' => 'HA',
        'VA' => 'VC',
        'HN' => 'HO',
        'HK' => 'HN',
        'HU' => 'HY',
        'IS' => 'IC',
        'IN' => 'II',
        'ID' => 'IE',
        'IE' => 'IR',
        'IQ' => 'IQ',
        'IM' => 'IM',
        'IL' => 'IG',
        'IT' => 'IT',
        'JM' => 'JA',
        'JP' => 'JP',
        'JE' => 'JE',
        'JO' => 'JO',
        'KZ' => 'KA',
        'KE' => 'KE',
        'KI' => 'KR',
        'KW' => 'KU',
        'KG' => 'KG',
        'LA' => 'LO',
        'LV' => 'LV',
        'LB' => 'LE',
        'LR' => 'LI',
        'LY' => 'LY',
        'LI' => 'LC',
        'LT' => 'LH',
        'LU' => 'LU',
        'MO' => 'M3',
        'MG' => 'MG',
        'MW' => 'ML',
        'MY' => 'MC',
        'MV' => 'MV',
        'ML' => 'MF',
        'MT' => 'MH',
        'MH' => 'M9',
        'MQ' => 'MQ',
        'MR' => 'MU',
        'MU' => 'M6',
        'YT' => 'YA',
        'MX' => 'MEX',
        'MD' => 'M7',
        'MC' => 'M5',
        'MN' => 'M4',
        'ME' => 'M8',
        'MS' => 'M2',
        'MA' => 'MZ',
        'MZ' => 'OZ',
        'MM' => 'MM',
        'NA' => 'N3',
        'NR' => 'NA',
        'NP' => 'NP',
        'NL' => 'NR',
        'NC' => 'N5',
        'PG' => 'PB',
        'NZ' => 'NZ',
        'NI' => 'NX',
        'NE' => 'N1',
        'NG' => 'N2',
        'NU' => 'N6',
        'NF' => 'NF',
        'MP' => 'M1',
        'NO' => 'NO',
        'OM' => 'OM',
        'PK' => 'PK',
        'PW' => 'PW',
        'PS' => 'PS',
        'PA' => 'PN',
        'PY' => 'PG',
        'PE' => 'PU',
        'PN' => 'P1',
        'PL' => 'PO',
        'PT' => 'PT',
        'QA' => 'QA',
        'RE' => 'RE',
        'RO' => 'RM',
        'RU' => 'RU',
        'RW' => 'RW',
        'BL' => 'S9',
        'SH' => 'S6',
        'KN' => 'KN',
        'LC' => 'SL',
        'MF' => 'SN',
        'PM' => 'PM',
        'VC' => 'SV',
        'WS' => 'EU',
        'SM' => 'SM',
        'ST' => 'SA',
        'SA' => 'SU',
        'SN' => 'SE',
        'RS' => 'RS',
        'SL' => 'SB',
        'SG' => 'SG',
        'SK' => 'S8',
        'SI' => 'SI',
        'SB' => 'BP',
        'SO' => 'SH',
        'ZA' => 'ST',
        'ES' => 'SX',
        'LK' => 'CY',
        'SD' => 'S7',
        'SR' => 'S1',
        'SJ' => 'SJ',
        'SZ' => 'S2',
        'SE' => 'S3',
        'CH' => 'S4',
        'SY' => 'S5',
        'TW' => 'TI',
        'TJ' => 'TA',
        'TZ' => 'TZ',
        'TH' => 'TL',
        'TL' => 'T1',
        'TG' => 'TB',
        'TK' => 'T2',
        'TO' => 'TC',
        'TT' => 'TR',
        'TN' => 'TU',
        'TR' => 'TK',
        'TM' => 'TM',
        'TC' => 'TS',
        'TV' => 'TV',
        'UG' => 'UG',
        'UA' => 'UK',
        'AE' => 'UA',
        'GB' => 'UI',
        'UY' => 'UR',
        'UZ' => 'UZ',
        'VU' => 'VU',
        'VE' => 'VE',
        'VN' => 'VN',
        'WF' => 'WF',
        'EH' => 'WS',
        'YE' => 'YE',
        'ZM' => 'ZA',
        'ZW' => 'ZI',
        'KM' => 'KM',
        'CI' => 'IV',
        'FK' => 'FK',
        'HM' => 'HM',
        'KP' => 'N4',
        'KR' => 'SQ',
        'LS' => 'LS',
        'MK' => 'MK',
        'FM' => 'FM',
        'PH' => 'PH',
        'SC' => 'SY',
        'GS' => 'GS',
        'UM' => 'UM',
    ];

    public static $states = [
        'AL' => 'ALABAMA',
        'AK' => 'ALASKA',
        'AZ' => 'ARIZONA',
        'AR' => 'ARKANSAS',
        'CA' => 'CALIFORNIA',
        'CO' => 'COLORADO',
        'CT' => 'CONNECTICUT',
        'DE' => 'DELAWARE',
        'DC' => 'DISTRICT OF COLUMBIA',
        'FL' => 'FLORIDA',
        'GA' => 'GEORGIA',
        'HI' => 'HAWAII',
        'ID' => 'IDAHO',
        'IL' => 'ILLINOIS',
        'IN' => 'INDIANA',
        'IA' => 'IOWA',
        'KS' => 'KANSAS',
        'KY' => 'KENTUCKY',
        'LA' => 'LOUISIANA',
        'ME' => 'MAINE',
        'MD' => 'MARYLAND',
        'MA' => 'MASSACHUSETTS',
        'MI' => 'MICHIGAN',
        'MN' => 'MINNESOTA',
        'MS' => 'MISSISSIPPI',
        'MO' => 'MISSOURI',
        'MT' => 'MONTANA',
        'NE' => 'NEBRASKA',
        'NV' => 'NEVADA',
        'NH' => 'NEW HAMPSHIRE',
        'NJ' => 'NEW JERSEY',
        'NM' => 'NEW MEXICO',
        'NY' => 'NEW YORK',
        'NC' => 'NORTH CAROLINA',
        'ND' => 'NORTH DAKOTA',
        'OH' => 'OHIO',
        'OK' => 'OKLAHOMA',
        'OR' => 'OREGON',
        'PA' => 'PENNSYLVANIA',
        'RI' => 'RHODE ISLAND',
        'SC' => 'SOUTH CAROLINA',
        'SD' => 'SOUTH DAKOTA',
        'TN' => 'TENNESSEE',
        'TX' => 'TEXAS',
        'AS' => 'US - AMERICAN SOMOA',
        'AE' => 'US - ARMED FORCES EUROPE',
        'AA' => 'US - ARMED FORCES OF AMERICAS',
        'AP' => 'US - ARMED FORCES PACIFIC',
        'GU' => 'US - GUAM',
        'MP' => 'US - NORTHERN MARIANA ISLANDS',
        'PR' => 'US - PUERTO RICO',
        'VI' => 'US - VIRGIN ISLANDS',
        'UT' => 'UTAH',
        'VT' => 'VERMONT',
        'VA' => 'VIRGINIA',
        'WA' => 'WASHINGTON',
        'WV' => 'WEST VIRGINIA',
        'WI' => 'WISCONSIN',
        'WY' => 'WYOMING',
        'AB' => 'ALBERTA',
        'BC' => 'BRITISH COLUMBIA',
        'MB' => 'MANITOBA',
        'NU' => 'NANAVUT',
        'NB' => 'NEW BRUNSWICK',
        'NL' => 'NEWFOUNDLAND AND LABRADOR',
        'NT' => 'NORTHWEST TERRITORY',
        'NS' => 'NOVA SCOTIA',
        'ON' => 'ONTARIO',
        'PE' => 'PRINCE EDWARD ISLAND',
        'QC' => 'QUEBEC',
        'SK' => 'SASKATCHEWAN',
        'YT' => 'YUKON TERRITORY',
    ];

    public static $securityQuestionTypes = [
        '1'  => 'What color was your first car?',
        '10' => 'What was the first name of your first roommate during college?',
        '11' => 'Who was your first employer?',
        '12' => 'What is the first name of your paternal grandmother (your father\'s mother)?',
        '2'  => 'What was the name of the street you grew up on?',
        '3'  => 'In what city was your mother born?',
        '4'  => 'What school did you attend for first grade?',
        '5'  => 'What is the first name of your maternal grandmother (your mother\'s mother)',
        '6'  => 'How old were you at your wedding?',
        '7'  => 'What was the model of your first car?',
        '8'  => 'In what city was your father born?',
        '9'  => 'What was your favorite childhood toy?',
    ];

    public function registerAccount(array $fields)
    {
        $this->http->log(sprintf('>>> %s', __METHOD__));
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;

        $fields['PhoneCountryCodeNumeric'] = '+' . $fields['PhoneCountryCodeNumeric'];

        $this->checkRegistrationData($fields);

        $this->http->getUrl('https://www.totalrewards.com/Program/');

        if (!$this->http->parseForm('createTRAccountMod_form')) {
            $this->http->log('failed to find sign up form');

            return false;
        }
        $this->http->log('>>> Register form:');
        $this->http->log(print_r($this->http->Form, true));

        $this->convertGender($fields);
        $this->http->log('>>> $fields:');
        $this->http->log(print_r($fields, true));

        $origCountryCode = $fields['Country'];
        $fields['Country'] = self::$countriesMap[$fields['Country']];
        $this->logger->debug('Mapped standard country code "' . $origCountryCode . '" to provider code "' . $fields['Country'] . '"');

        foreach (self::$fieldMap as $awkey => $keys) {
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
        $this->convertPhoneNumber($fields);

        if (!$this->setCaptcha()) {
            return false;
        }

        $this->http->postForm();

        $success = $this->http->findPreg('/Thank you for becoming the newest member of Total Rewards!/i');

        if ($success) {
            $message = 'Successfull registration.';
            $account = $this->http->findPreg('/Your Total Rewards number is.*?(\d+)/i');

            if ($account) {
                $message = "$message Your total rewards number is $account.";
            } else {
                $message = "$message Unfortunately we weren't able to retrieve account number.";
            }
            $message = "$message Please confirm your email address.";
            $this->http->log($message);
            $this->ErrorMessage = $message;

            return true;
        }

        $this->checkRegistrationErrors();

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name (your first name must exactly match what is listed on your ID)',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name (your last name must exactly match what is listed on your ID)',
                'Required' => true,
            ],
            'BirthMonth' => [
                'Type'     => 'integer',
                'Caption'  => 'Birth Month',
                'Required' => true,
            ],
            'BirthDay' => [
                'Type'     => 'integer',
                'Caption'  => '1 <= Birth Day <= 31',
                'Required' => true,
            ],
            'BirthYear' => [
                'Type'     => 'integer',
                'Caption'  => sprintf('1917 <= Birth Year <= %s', date('Y') - 19),
                'Required' => true,
            ],
            'Gender' => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => self::$genders,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'AddressLine1' => [
                'Type'     => 'string',
                'Caption'  => 'Address Line 1',
                'Required' => true,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'City',
                'Required' => true,
            ],
            'StateOrProvince' => [
                'Type'     => 'string',
                'Caption'  => 'State or Province (required for USA and Canada; unfortunately Missouri residents cannot join Total Rewards online)',
                'Required' => false,
                'Options'  => self::$states,
            ],
            'PostalCode' => [
                'Type'     => 'string',
                'Caption'  => 'Postal Code',
                'Required' => true,
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
                'Caption'  => 'Phone Number',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Username' => [
                'Type'     => 'string',
                'Caption'  => 'Username (must start with a letter and may include numbers; the length must be a minimum of 6 characters)',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => "Password (must be between 7 and 20 characters long and have at least three of the four following types of characters: an uppercase letter; a lowercase letter; a number; an allowed special character @'.#$%^*-+=!_)",
                'Required' => true,
            ],
            'SecurityQuestionType1' => [
                'Type'     => 'string',
                'Caption'  => 'Security Question',
                'Required' => true,
                'Options'  => self::$securityQuestionTypes,
            ],
            'SecurityQuestionAnswer1' => [
                'Type'     => 'string',
                'Caption'  => 'Security Answer',
                'Required' => true,
            ],
        ];
    }

    private function checkRegistrationData($fields)
    {
        if ($fields['BirthDay'] < 1 || $fields['BirthDay'] > 31) {
            throw new \UserInputError($this->getRegisterFields()['BirthDay']['Caption']);
        }

        if ($fields['BirthYear'] < 1917) {
            throw new \UserInputError('Birth year has to be >= 1917');
        }

        if ($fields['BirthYear'] >= date('Y') - 18) {
            throw new \UserInputError('You must be of legal age to gamble at the casino.');
        }

        if ($fields['StateOrProvince'] === 'MO') {
            throw new \UserInputError('Unfortunately Missouri residents cannot join Total Rewards online.');
        }

        if (($fields['Country'] === 'USA' || $fields['Country'] === 'CAN') && !arrayVal($fields, 'StateOrProvince')) {
            throw new \UserInputError('State is required for USA and Canada.');
        }
    }

    private function checkRegistrationErrors()
    {
        $errors = $this->http->findNodes('//*[contains(@class, "oneOffError") and contains(@style, "inline")]');

        if ($errors) {
            $msg = implode(' ', $errors);

            if (preg_match('/Code entered did not match/i', $msg)) {
                throw new \ProviderError('Please try again later');
            }

            $this->http->log($msg, 'LOG_LEVEL_ERROR');

            throw new \UserInputError($msg);
        }

        $globalError = $this->http->findSingleNode('//*[@id = "ctl00_global_errorMsgText"]');

        if ($globalError) {
            $msg = $globalError;
            $this->http->log($globalError, 'LOG_LEVEL_ERROR');

            throw new \UserInputError($msg);
        }

        $existingError = $this->http->findPreg('/The information you entered matches an existing Total Rewards account/i');

        if ($existingError) {
            $this->http->log($existingError, 'LOG_LEVEL_ERROR');

            throw new \UserInputError($existingError);
        }

        if ($this->http->findPreg('/An application error occurred on the server/i')) {
            throw new \ProviderError('Site error, please try again later');
        }
    }

    private function convertPhoneNumber(array $fields)
    {
        $phone = sprintf('%s%s',
            $fields['PhoneAreaCode'],
            $fields['PhoneLocalNumber']
        );
        $this->http->setInputValue('homephone', $phone);
    }

    private function convertGender(array &$fields)
    {
        $gender = self::$genderMap[$fields['Gender']];
        $fields['Gender'] = $gender;
    }

    private function parseCaptcha($guid)
    {
        $this->http->log(sprintf('>>> %s', __METHOD__));

        $url = sprintf('https://www.totalrewards.com/program/CaptchaImage.aspx?guid=%s', $guid);
        $this->http->log(">>> Captcha url: $url", LOG_LEVEL_ERROR);

        $http2 = clone $this->http;
        $file = $http2->downloadFile($url, 'jpeg');
        $http2->log(">>> Captcha: $file");

        $recognizer = $this->getCaptchaRecognizer();

        try {
            $captcha = str_replace(' ', '', $recognizer->recognizeFile($file));
        } catch (\CaptchaException $e) {
            $this->http->log(sprintf('>>> Captcha recognition exception: %s', $e->getMessage()), LOG_LEVEL_ERROR);

            return false;
        }

        unlink($file);

        return strtoupper($captcha);
    }

    private function setCaptcha()
    {
        $this->http->log(sprintf('>>> %s', __METHOD__));

        $guid = $this->http->findPreg("/var __gid='(.+?)';/i");

        if (!$guid) {
            $this->http->log('>>> Could not find captcha guid');

            return false;
        }

        $value = $this->parseCaptcha($guid);

        if (!$value) {
            return false;
        }

        // $script = $this->http->findSingleNode("//*[contains(@class, 'capthcha_field')]");
        // $this->http->log(">>> script: $script");
        $this->http->setInputValue('__cap_a1', $guid);

        $key = $this->http->findPreg("/input name=\\\'(\w+)\\\'/");
        $this->http->log(">>> Captcha input name: $key");
        $this->http->setInputValue($key, $value);

        return true;
    }
}
