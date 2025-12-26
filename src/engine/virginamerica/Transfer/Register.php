<?php

namespace AwardWallet\Engine\virginamerica\Transfer;

class Register extends \TAccountChecker
{
    public static $countries = [
        'AC' => 'ASCENSION ISLAND',
        'AD' => 'ANDORRA',
        'AE' => 'UNITED ARAB EMIRATES',
        'AF' => 'AFGHANISTAN',
        'AG' => 'ANTIGUA AND BARBUDA',
        'AI' => 'ANGUILLA',
        'AL' => 'ALBANIA',
        'AM' => 'ARMENIA',
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
        'BL' => 'SAINT BARTHELEMY',
        'BM' => 'BERMUDA',
        'BN' => 'BRUNEI DARUSSALAM',
        'BO' => 'PLURINATIONAL STATE OF BOLIVIA',
        'BQ' => 'BONAIRE, SAINT EUSTATIUS AND SABA',
        'BR' => 'BRAZIL',
        'BS' => 'BAHAMAS',
        'BT' => 'BHUTAN',
        'BV' => 'BOUVET ISLAND',
        'BW' => 'BOTSWANA',
        'BY' => 'BELARUS',
        'BZ' => 'BELIZE',
        'CA' => 'CANADA',
        'CC' => 'COCOS (KEELING) ISLANDS',
        'CD' => 'DEMOCRATIC REPUBLIC OF CONGO',
        'CF' => 'CENTRAL AFRICAN REPUBLIC',
        'CG' => 'REPUBLIC OF CONGO',
        'CH' => 'SWITZERLAND',
        'CI' => 'COTE D\'IVOIRE',
        'CK' => 'COOK ISLANDS',
        'CL' => 'CHILE',
        'CM' => 'CAMEROON',
        'CN' => 'CHINA',
        'CO' => 'COLOMBIA',
        'CP' => 'CLIPPERTON ISLAND',
        'CR' => 'COSTA RICA',
        'CU' => 'CUBA',
        'CV' => 'CAPE VERDE',
        'CW' => 'CURACAO',
        'CX' => 'CHRISTMAS ISLAND',
        'CY' => 'CYPRUS',
        'CZ' => 'CZECH REPUBLIC',
        'DE' => 'GERMANY',
        'DG' => 'DIEGO GARCIA',
        'DJ' => 'DJIBOUTI',
        'DK' => 'DENMARK',
        'DM' => 'DOMINICA',
        'DO' => 'DOMINICAN REPUBLIC',
        'DZ' => 'ALGERIA',
        'EA' => 'CEUTA, MULILLA',
        'EC' => 'ECUADOR',
        'EE' => 'ESTONIA',
        'EG' => 'EGYPT',
        'EH' => 'WESTERN SAHARA',
        'ER' => 'ERITREA',
        'ES' => 'SPAIN',
        'ET' => 'ETHIOPIA',
        'EU' => 'EUROPEAN UNION',
        'FI' => 'FINLAND',
        'FJ' => 'FIJI',
        'FK' => 'FALKLAND ISLANDS',
        'FM' => 'FEDERATED STATES OF MICRONESIA',
        'FO' => 'FAROE ISLANDS',
        'FR' => 'FRANCE',
        'FX' => 'FRANCE, METROPOLITAN',
        'GA' => 'GABON',
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
        'GS' => 'SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS',
        'GT' => 'GUATEMALA',
        'GU' => 'GUAM',
        'GW' => 'GUINEA-BISSAU',
        'GY' => 'GUYANA',
        'HK' => 'HONG KONG',
        'HM' => 'HEARD ISLAND AND MCDONALD ISLANDS',
        'HN' => 'HONDURAS',
        'HR' => 'CROATIA',
        'HT' => 'HAITI',
        'HU' => 'HUNGARY',
        'IC' => 'CANARY ISLANDS',
        'ID' => 'INDONESIA',
        'IE' => 'IRELAND',
        'IL' => 'ISRAEL',
        'IM' => 'ISLE OF MAN',
        'IN' => 'INDIA',
        'IO' => 'BRITISH INDIAN OCEAN TERRITORY',
        'IQ' => 'IRAQ',
        'IR' => 'ISLAMIC REPUBLIC OF IRAN',
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
        'KN' => 'SAINT KITTS AND NEVIS',
        'KP' => 'DEMOCRATIC PEOPLE\'S REPUBLIC OF KOREA',
        'KR' => 'KOREA, REPUBLIC OF',
        'KW' => 'KUWAIT',
        'KY' => 'CAYMAN ISLANDS',
        'KZ' => 'KAZAKHSTAN',
        'LA' => 'LAO PEOPLE\'S DEMOCRATIC REPUBLIC',
        'LB' => 'LEBANON',
        'LC' => 'SAINT LUCIA',
        'LI' => 'LIECHTENSTEIN',
        'LK' => 'SRI LANKA',
        'LR' => 'LIBERIA',
        'LS' => 'LESOTHO',
        'LT' => 'LITHUANIA',
        'LU' => 'LUXEMBOURG',
        'LV' => 'LATVIA',
        'LY' => 'LIBYA',
        'MA' => 'MOROCCO',
        'MC' => 'MONACO',
        'MD' => 'MOLDOVA',
        'ME' => 'MONTENEGRO',
        'MF' => 'SAINT MARTIN',
        'MG' => 'MADAGASCAR',
        'MH' => 'MARSHALL ISLANDS',
        'MK' => 'THE FORMER YUGOSLAV REPUBLIC OF MACEDONIA',
        'ML' => 'MALI',
        'MM' => 'MYANMAR',
        'MN' => 'MONGOLIA',
        'MO' => 'MACAO',
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
        'NE' => 'NIGER',
        'NF' => 'NORFOLK ISLAND',
        'NG' => 'NIGERIA',
        'NI' => 'NICARAGUA',
        'NL' => 'NETHERLANDS',
        'NO' => 'NORWAY',
        'NP' => 'NEPAL',
        'NR' => 'NAURU',
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
        'PM' => 'SAINT PIERRE AND MIQUELON',
        'PN' => 'PITCAIRN',
        'PR' => 'PUERTO RICO',
        'PS' => 'PALESTINIAN TERRITORY, OCCUPIED',
        'PT' => 'PORTUGAL',
        'PW' => 'PALAU',
        'PY' => 'PARAGUAY',
        'QA' => 'QATAR',
        'RE' => 'REUNION',
        'RO' => 'ROMANIA',
        'RS' => 'SERBIA',
        'RU' => 'RUSSIAN FEDERATION',
        'RW' => 'RWANDA',
        'SA' => 'SAUDI ARABIA',
        'SB' => 'SOLOMON ISLANDS',
        'SC' => 'SEYCHELLES',
        'SD' => 'SUDAN',
        'SE' => 'SWEDEN',
        'SG' => 'SINGAPORE',
        'SH' => 'SAINT HELENA, ASCENSION AND TRISTAN DA CUNHA',
        'SI' => 'SLOVENIA',
        'SJ' => 'SVALBARD AND JAN MAYEN',
        'SK' => 'SLOVAKIA',
        'SL' => 'SIERRA LEONE',
        'SM' => 'SAN MARINO',
        'SN' => 'SENEGAL',
        'SO' => 'SOMALIA',
        'SR' => 'SURINAME',
        'ST' => 'SAO TOME AND PRINCIPE',
        'SU' => 'USSR',
        'SV' => 'EL SALVADOR',
        'SX' => 'SINT MAARTEN',
        'SY' => 'SYRIAN ARAB REPUBLIC',
        'SZ' => 'SWAZILAND',
        'TA' => 'TRISTAN DE CUNHA',
        'TC' => 'TURKS AND CAICOS ISLANDS',
        'TD' => 'CHAD',
        'TF' => 'FRENCH SOUTHERN TERRITORIES',
        'TG' => 'TOGO',
        'TH' => 'THAILAND',
        'TJ' => 'TAJIKISTAN',
        'TK' => 'TOKELAU',
        'TL' => 'EAST TIMOR',
        'TM' => 'TURKMENISTAN',
        'TN' => 'TUNISIA',
        'TO' => 'TONGA',
        'TR' => 'TURKEY',
        'TT' => 'TRINIDAD AND TOBAGO',
        'TV' => 'TUVALU',
        'TW' => 'TAIWAN',
        'TZ' => 'UNITED REPUBLIC OF TANZANIA',
        'UA' => 'UKRAINE',
        'UG' => 'UGANDA',
        'GB' => 'UNITED KINGDOM',
        'UM' => 'UNITED STATES MINOR OUTLYING ISLANDS',
        'US' => 'UNITED STATES',
        'UY' => 'URUGUAY',
        'UZ' => 'UZBEKISTAN',
        'VA' => 'VATICAN CITY STATE',
        'VC' => 'SAINT VINCENT AND THE GRENADINES',
        'VE' => 'BOLIVARIAN REPUBLIC OF VENEZUELA',
        'VG' => 'VIRGIN ISLANDS (BRITISH)',
        'VI' => 'VIRGIN ISLANDS (US)',
        'VN' => 'VIETNAM',
        'VU' => 'VANUATU',
        'WF' => 'WALLIS AND FUTUNA',
        'WS' => 'SAMOA',
        'YE' => 'YEMEN',
        'YT' => 'MAYOTTE',
        'ZA' => 'SOUTH AFRICA',
        'ZM' => 'ZAMBIA',
        'ZW' => 'ZIMBABWE',
    ];

    public static $inputFieldsMap = [
        'Email'                   => '',
        'Password'                => '',
        'FirstName'               => '',
        'LastName'                => '',
        'Gender'                  => '',
        'BirthMonth'              => '',
        'BirthDay'                => '',
        'BirthYear'               => '',
        'AddressType'             => '',
        'AddressLine1'            => '',
        'Country'                 => '',
        'StateOrProvince'         => '',
        'PostalCode'              => '',
        'PhoneCountryCodeNumeric' => '',
        'PhoneAreaCode'           => '',
        'PhoneLocalNumber'        => '',
    ];

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;
        $this->http->removeCookies();
        $this->http->GetURL('https://www.virginamerica.com/elevate-frequent-flyer/sign-up');
        $this->http->GetURL("https://www.virginamerica.com/api/v0/cart/retrieve");
        $this->http->setDefaultHeader('Content-Type', 'application/json;charset=utf-8');
        //		$this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate');
        //		$this->http->setDefaultHeader('ADRUM', 'isAjax:true');
        //		$this->http->setDefaultHeader('Host', 'www.virginamerica.com');
        //		$this->http->setDefaultHeader('Origin', 'https://www.virginamerica.com');
        //		$this->http->setDefaultHeader('Referer', 'https://www.virginamerica.com/elevate-frequent-flyer/sign-up');
        //		$this->http->setDefaultHeader('UI-Version', 'junkyard-dog-build-807');

        $phone = $fields['PhoneCountryCodeNumeric'] . $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];
        $regData = [
            "elevateAccountInfo"  => [
                "userID"         => $fields['Email'],
                "password"       => $fields['Password'],
                "foundingMember" => false,
            ],
            "personDetail" => [
                "firstName"   => $fields['FirstName'],
                "lastName"    => $fields['LastName'],
                "middleName"  => '',
                "gender"      => $fields['Gender'] === 'M' ? 'MALE' : 'FEMALE',
                "dateOfBirth" => $fields['BirthYear'] . '-' .
                    \DateTime::createFromFormat('!m', intval($fields['BirthMonth']))->format('m') . '-' .
                    \DateTime::createFromFormat('!m', intval($fields['BirthDay']))->format('d'),
            ],
            "phoneInfo" => [
                "areaCode"    => substr($phone, 0, 3),
                "number"      => substr($phone, 3),
                "contactType" => "HOME",
                "sequenceNum" => 0,
                "primary"     => true,
            ],
            "emailInfo" => [
                "address"     => $fields['Email'],
                "emailType"   => "HOME",
                "sequenceNum" => 0,
                "primary"     => true,
                "emailName"   => "ElevateEmail",
            ],
            "addressInfo" => [
                "addressOne"  => $fields['AddressLine1'],
                "city"        => $fields['City'],
                "state"       => $fields['StateOrProvince'] ?? '',
                "country"     => $fields['Country'],
                "zipCode"     => $fields['PostalCode'],
                "addressType" => "HOME",
                "sequenceNum" => 0,
                "primary"     => true,
                "addressName" => "Primary Address",
            ],
            "flightAlertOptions" => [
                "emailAddress"              => $fields['Email'],
                "smsNumber"                 => $phone,
                "notifyMinutesPriorToEvent" => 60,
            ],
            "avatarCode" => ["avatarCode" => "A1"],
            "signMeUp"   => true,
        ];

        $this->http->PostURL('https://www.virginamerica.com/api/v0/elevate/join-elevate', json_encode($regData));

        $response = $this->http->JsonLog();

        if (isset($response->status) and strtoupper($response->status->status) === 'ERROR') {
            if (!isset($response->status->error->code)) {
                throw new \UserInputError($response->status->error->status);
            }

            $erCode = $response->status->error->code;
            $this->http->GetURL("https://www.virginamerica.com/data/error-mapping/api-error-codes.json");
            $errors = $this->http->JsonLog($this->http->Response['body'], false, true);

            throw new \UserInputError($errors[$erCode]['internalMsg']);
        }

        if (isset($response->response)) {
            $this->ErrorMessage = 'ELEVATE # ' . $response->response->elevateId;
            $this->http->Log($this->ErrorMessage);

            return true;
        }

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
                'Caption'  => 'Password (8 to 16 characters in length)',
                'Required' => true,
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
            //			'AddressType' =>
            //			array (
            //				'Type' => 'string',
            //				'Caption' => 'Address Type',
            //				'Required' => true,
            //				'Options' =>
            //				array (
            //					'H' => 'Home',
            //					'B' => 'Business',
            //				),
            //			),
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
                'Caption'  => 'State or Province',
                'Required' => false,
            ],
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Postal/ZIP Code',
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
        ];
    }
}
