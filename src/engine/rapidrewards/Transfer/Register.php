<?php

namespace AwardWallet\Engine\rapidrewards\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use ProxyList;

    public static $suffices = [
        "CEO" => "CEO",
        "CLU" => "CLU",
        "CPA" => "CPA",
        "DC"  => "DC",
        "DDS" => "DDS",
        "DO"  => "DO",
        "DPM" => "DPM",
        "DVM" => "DVM",
        "I"   => "I",
        "II"  => "II",
        "III" => "III",
        "IV"  => "IV",
        "JR"  => "JR",
        "MD"  => "MD",
        "OD"  => "OD",
        "PHD" => "PHD",
        "RN"  => "RN",
        "SR"  => "SR",
        "V"   => "V",
        "VI"  => "VI",
    ];

    public static $genders = [
        "M" => "Male",
        "F" => "Female",
    ];

    public static $addressTypes = [
        "H" => "HOME",
        "B" => "BUSINESS",
        "O" => "OTHER",
    ];

    public static $phoneTypes = [
        "H" => "Home",
        "B" => "Business",
        "M" => "Mobile",
        "O" => "Other",
    ];

    public static $securityQuestions = [
        "What is the name of the city in which you were born?" => "What is the name of the city in which you were born?",
        "What is the name of your first pet?"                  => "What is the name of your first pet?",
        "What is your favorite ice cream flavor?"              => "What is your favorite ice cream flavor?",
        "What was the color of your first car?"                => "What was the color of your first car?",
        "What is the middle name of your youngest child?"      => "What is the middle name of your youngest child?",
        "What is the name of your favorite sports team?"       => "What is the name of your favorite sports team?",
        "What was your high school mascot?"                    => "What was your high school mascot?",
        "What is your mother's maiden name?"                   => "What is your mother's maiden name?",
        "What is the name of your favorite singer/band?"       => "What is the name of your favorite singer/band?",
        "What is the name of your favorite movie?"             => "What is the name of your favorite movie?",
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
        'BQ' => 'BONAIRE, SINT EUSTATIUS AND SABA',
        'BA' => 'BOSNIA AND HERZEGOVINA',
        'BW' => 'BOTSWANA',
        'BR' => 'BRAZIL',
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
        'CX' => 'CHRISTMAS ISLAND',
        'CC' => 'COCOS (KEELING) ISLANDS',
        'CO' => 'COLOMBIA',
        'KM' => 'COMOROS',
        'CD' => 'THE DEMOCRATIC REPUBLIC OF THE CONGO',
        'CG' => 'CONGO',
        'CK' => 'COOK ISLANDS',
        'CR' => 'COSTA RICA',
        'CI' => 'COTE D IVOIRE (Ivory Coast)',
        'HR' => 'CROATIA (LOCAL NAME - HRVATSKA)',
        'CU' => 'CUBA',
        'CW' => 'CURACAO',
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
        'FK' => 'FALKLAND ISLANDS (MALVINAS)',
        'FO' => 'FAROE ISLANDS',
        'FJ' => 'FIJI',
        'FI' => 'FINLAND',
        'FR' => 'FRANCE',
        'GF' => 'FRENCH GUIANA',
        'PF' => 'FRENCH POLYNESIA',
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
        'GN' => 'GUINEA',
        'GW' => 'GUINEA - BISSAU',
        'GY' => 'GUYANA',
        'HT' => 'HAITI',
        'HN' => 'HONDURAS',
        'HK' => 'HONG KONG',
        'HU' => 'HUNGARY',
        'IS' => 'ICELAND',
        'IN' => 'INDIA',
        'ID' => 'INDONESIA',
        'IR' => 'IRAN (ISLAMIC REPUBLIC OF)',
        'IQ' => 'IRAQ',
        'IE' => 'IRELAND',
        'IL' => 'ISRAEL',
        'IT' => 'ITALY',
        'JM' => 'JAMAICA',
        'JP' => 'JAPAN',
        'JO' => 'JORDAN',
        'KZ' => 'KAZAKHSTAN',
        'KE' => 'KENYA',
        'KI' => 'KIRIBATI',
        'KP' => 'DEMOCRATIC PEOPLE\'S REPUBLIC OF KOREA',
        'KR' => 'REPUBLIC OF KOREA',
        'KW' => 'KUWAIT',
        'KG' => 'KYRGYZSTAN',
        'LA' => 'LAO PEOPLES DEMOCRATIC REPUBLIC',
        'LV' => 'LATVIA',
        'LB' => 'LEBANON',
        'LS' => 'LESOTHO',
        'LR' => 'LIBERIA',
        'LY' => 'LIBYA',
        'LI' => 'LIECHTENSTEIN',
        'LT' => 'LITHUANIA',
        'LU' => 'LUXEMBOURG',
        'MO' => 'MACAO',
        'MK' => 'MACEDONIA - THE FORMER YUGOSLAV REPUBLIC OF',
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
        'FM' => 'MICRONESIA - FEDERATED STATES OF',
        'MD' => 'REPUBLIC OF MOLDOVA',
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
        'NZ' => 'NEW ZEALAND',
        'NI' => 'NICARAGUA',
        'NE' => 'NIGER',
        'NG' => 'NIGERIA',
        'NF' => 'NORFOLK ISLAND',
        'MP' => 'NORTHERN MARIANA ISLANDS',
        'NO' => 'NORWAY',
        'OM' => 'OMAN',
        'PK' => 'PAKISTAN',
        'PW' => 'PALAU',
        'PS' => 'PALESTINE, STATE OF',
        'PA' => 'PANAMA',
        'PG' => 'PAPUA NEW GUINEA',
        'PY' => 'PARAGUAY',
        'PE' => 'PERU',
        'PH' => 'PHILIPPINES',
        'PL' => 'POLAND',
        'PT' => 'PORTUGAL',
        'PR' => 'PUERTO RICO',
        'QA' => 'QATAR',
        'RE' => 'REUNION',
        'RO' => 'ROMANIA',
        'RU' => 'RUSSIAN FEDERATION',
        'RW' => 'RWANDA',
        'SH' => 'SAINT HELENA',
        'KN' => 'SAINT KITTS AND NEVIS',
        'LC' => 'SAINT LUCIA',
        'PM' => 'SAINT PIERRE AND MIQUELON',
        'VC' => 'SAINT VINCENT AND THE GRENADINES',
        'WS' => 'SAMOA',
        'SM' => 'SAN MARINO',
        'ST' => 'SAO TOME AND PRINCIPE',
        'SA' => 'SAUDI ARABIA',
        'SN' => 'SENEGAL',
        'RS' => 'SERBIA',
        'SC' => 'SEYCHELLES',
        'SL' => 'SIERRA LEONE',
        'SG' => 'SINGAPORE',
        'SX' => 'SINT MAARTEN (DUTCH PART)',
        'SK' => 'SLOVAKIA (SLOVAK REPUBLIC)',
        'SI' => 'SLOVENIA',
        'SB' => 'SOLOMON ISLANDS',
        'SO' => 'SOMALIA',
        'ZA' => 'SOUTH AFRICA',
        'SS' => 'SOUTH SUDAN',
        'ES' => 'SPAIN',
        'LK' => 'SRI LANKA',
        'SD' => 'SUDAN',
        'SR' => 'SURINAME',
        'SZ' => 'SWAZILAND',
        'SE' => 'SWEDEN',
        'CH' => 'SWITZERLAND',
        'SY' => 'SYRIAN ARAB REPUBLIC',
        'TW' => 'CHINESE TAIPEI',
        'TJ' => 'TAJIKISTAN',
        'TZ' => 'UNITED REPUBLIC OF TANZANIA',
        'TH' => 'THAILAND',
        'TL' => 'TIMOR-LESTE',
        'TG' => 'TOGO',
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
        'US' => 'UNITED STATES OF AMERICA',
        'UY' => 'URUGUAY',
        'UZ' => 'UZBEKISTAN',
        'VU' => 'VANUATU',
        'VE' => 'VENEZUELA, BOLIVARIAN REPUBLIC OF',
        'VN' => 'VIET NAM',
        'VG' => 'BRITISH VIRGIN ISLANDS',
        'VI' => 'U.S. VIRGIN ISLANDS',
        'WF' => 'WALLIS AND FUTUNA ISLANDS',
        'EH' => 'WESTERN SAHARA',
        'YE' => 'REPUBLIC OF YEMEN',
        'ZM' => 'ZAMBIA',
        'ZW' => 'ZIMBABWE',
    ];

    public function InitBrowser()
    {
        $this->UseCurlBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            //			$this->http->setExternalProxy();
            $this->http->SetProxy($this->proxyReCaptcha());
        } else {
            $this->http->SetProxy('localhost:8000');
        }
    }

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->GetURL('https://www.southwest.com/account/enroll/enroll-member');

        if (!$this->http->ParseForm('customer')) {
            return false;
        }
        $this->http->FormURL = 'https://www.southwest.com/flight/account/enroll/enroll-member';
        unset($this->http->Form['']);
        unset($this->http->Form['acceptRulesAndRegulations']);
        $fields['Gender'] = self::$genders[$fields['Gender']];
        $fields['AddressType'] = self::$addressTypes[$fields['AddressType']];
        $fields['PhoneType'] = self::$phoneTypes[$fields['PhoneType']];

        if ($fields['AddressType'] === 'BUSINESS' && trim($fields['Company']) === '') {
            throw new \UserInputError('Company name is required for business address.');
        }

        if ($fields['SecurityQuestionType1'] === $fields['SecurityQuestionType2']) {
            throw new \UserInputError('Security questions should be different.');
        }
        $json = $this->http->FindSingleNode('//script[contains(.,"var countriesJSON =")]', null, true, "/var countriesJSON =\s*'(\{[^}]+\})'/");

        if (!isset($json) || !($phoneCountryCodes = json_decode($json, true))) {
            $this->http->Log('Failed to load phone country codes.');

            return false;
        }

        if (!isset(self::$countries[$fields['Country']])) {
            throw new \UserInputError('Invalid country code.');
        }

        if (!isset($phoneCountryCodes[$fields['PhoneCountryCodeAlphabetic']])) {
            throw new \UserInputError('Invalid phone country code.');
        }
        $fields['PhoneCode'] = $phoneCountryCodes[$fields['PhoneCountryCodeAlphabetic']];
        $fields['PhoneNumber'] = $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];

        foreach ([
            'customer.suffix' => 'Suffix',
            'customer.birthMonth' => 'BirthMonth',
            'customer.birthDay' => 'BirthDay',
            'customer.birthYear' => 'BirthYear',
            'customer.gender' => 'Gender',
            'customer.firstName' => 'FirstName',
            'customer.middleName' => 'MiddleName',
            'customer.lastName' => 'LastName',
            'customer.lastFourOfSsn' => 'SS',
            'customer.familiarName' => 'PreferredFirstName',
            'contactInfo.addresses[0].addressType' => 'AddressType',
            'contactInfo.addresses[0].country' => 'Country',
            'contactInfo.addresses[0].companyName' => 'Company',
            'contactInfo.addresses[0].line1' => 'AddressLine1',
            'contactInfo.addresses[0].line2' => 'AddressLine2',
            'contactInfo.addresses[0].city' => 'City',
            'contactInfo.addresses[0].zipOrPostalCode' => 'PostalCode',
            'contactInfo.phones[0].phoneNumber.phoneType' => 'PhoneType',
            'contactInfo.phones[0].phoneNumber.country' => 'PhoneCountryCodeAlphabetic',
            'contactInfo.phones[0].phoneNumber.countryCode' => 'PhoneCode',
            'contactInfo.phones[0].phoneNumber.rawPhoneNumber' => 'PhoneNumber',
            'contactInfo.emails[0].address' => 'Email',
            'contactInfo.emails[0].confirmationAddress' => 'Email',
            'account.username' => 'Username',
            'account.password' => 'Password',
            'account.passwordConfirmation' => 'Password',
            'account.securityQuestion' => 'SecurityQuestionType1',
            'account.securityQuestion2' => 'SecurityQuestionType2',
            'account.securityAnswer' => 'SecurityQuestionAnswer1',
            'account.securityAnswer2' => 'SecurityQuestionAnswer2',
        ] as $n => $f) {
            if (isset($fields[$f])) {
                $this->http->Form[$n] = $fields[$f];
            }
        }

        if ($fields['Country'] === 'US') {
            $states = $this->http->FindNodes('//select[@id="js-contact-info-state"]/option/@value');

            if (!in_array($fields['StateOrProvince'], $states)) {
                throw new \UserInputError('Invalid US state.');
            }
            $this->http->Form['contactInfo.addresses[0].state'] = $fields['StateOrProvince'];
        } else {
            $this->http->Form['contactInfo.addresses[0].province'] = $fields['StateOrProvince'];
        }
        $this->http->Form['customer.accountType'] = 'MEMBER';

        if (!empty($fields['PromoCode'])) {
            $this->http->Form['promoCode'] = $fields['PromoCode'];
        }
        $this->http->Form['acceptRulesAndRegulations'] = 'true';

        foreach ([
            "customer.receiveRapidRewardsReport" => "EmailStatements",
            "customer.receiveRapidRewardsUpdate" => "EmailUpdates",
            "customer.receiveClickAndSaveEmail" => "EmailDeals",
            "customer.receiveInaNutshellEmail" => "EmailNutshell",
        ] as $n => $f) {
            if (!isset($fields[$f]) || !$fields[$f]) {
                unset($this->http->Form[$n]);
            }
        }

        if (!$this->http->PostForm()) {
            return false;
        }
        $errors = $this->http->FindNodes('//ul[@id="errors"]/li');

        if (count($errors) > 0) {
            $text = implode(', ', $errors);
            $this->http->Log('Errors found.', LOG_LEVEL_ERROR);

            throw new \UserInputError($text); // Is it always user input error?
        }

        if ($this->checkSuccess()) {
            return true;
        }

        if (!$this->http->FindSingleNode('//div[contains(text(),"Please review this information")]') || !$this->http->ParseForm('customer')) {
            return false;
        }
        $this->http->Form['Create'] = 'Create Account';

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($error = $this->http->FindSingleNode('//ul[@id="errors"]/li[1]')) {
            throw new \UserInputError($error);
        } // Is it always user input error?

        return $this->checkSuccess();
    }

    public function getRegisterFields()
    {
        return [
            "FirstName" => [
                "Type"     => "string",
                "Caption"  => "First Name",
                "Required" => true,
            ],
            "MiddleName" => [
                "Type"     => "string",
                "Caption"  => "Middle Name",
                "Required" => false,
            ],
            "LastName" => [
                "Type"     => "string",
                "Caption"  => "Last Name",
                "Required" => true,
            ],
            "Suffix" => [
                "Type"     => "string",
                "Caption"  => "Suffix",
                "Required" => false,
                "Options"  => self::$suffices,
            ],
            "BirthDay" => [
                "Type"     => "integer",
                "Caption"  => "Day of Birth Date",
                "Required" => true,
            ],
            "BirthMonth" => [
                "Type"     => "integer",
                "Caption"  => "Month of Birth Date",
                "Required" => true,
            ],
            "BirthYear" => [
                "Type"     => "integer",
                "Caption"  => "Year of Birth Date",
                "Required" => true,
            ],
            "Gender" => [
                "Type"     => "string",
                "Caption"  => "Gender",
                "Required" => true,
                "Options"  => [
                    'M' => 'Male',
                    'F' => 'Female',
                ],
            ],
            "SS" => [
                "Type"     => "string",
                "Caption"  => "SS# last 4 digits",
                "Required" => false,
            ],
            "PreferredFirstName" => [
                "Type"     => "string",
                "Caption"  => "Preferred First Name",
                "Required" => false,
            ],
            "AddressType" => [
                "Type"     => "string",
                "Caption"  => "Address Type",
                "Required" => true,
                "Options"  => self::$addressTypes,
            ],
            "Country" => [
                "Type"     => "string",
                "Caption"  => "2 Letter Country Code",
                "Required" => true,
                'Options'  => self::$countries,
            ],
            "Company" => [
                "Type"     => "string",
                "Caption"  => "Company Name, required for business address",
                "Required" => false,
            ],
            "AddressLine1" => [
                "Type"     => "string",
                "Caption"  => "Address Line 1",
                "Required" => true,
            ],
            "AddressLine2" => [
                "Type"     => "string",
                "Caption"  => "Address Line 2",
                "Required" => false,
            ],
            "City" => [
                "Type"     => "string",
                "Caption"  => "City",
                "Required" => true,
            ],
            "StateOrProvince" => [
                "Type"     => "string",
                "Caption"  => "State Code for US or Province/Region Name for other countries",
                "Required" => true,
            ],
            "PostalCode" => [
                "Type"     => "string",
                "Caption"  => "Postal Code",
                "Required" => true,
            ],
            "PhoneType" => [
                "Type"     => "string",
                "Caption"  => "PhoneType",
                "Required" => true,
                "Options"  => self::$phoneTypes,
            ],
            "PhoneCountryCodeAlphabetic" => [
                "Type"     => "string",
                "Caption"  => "2 Letter Phone Country Code",
                "Required" => true,
                "Options"  => self::$countries,
            ],
            "PhoneAreaCode" => [
                "Type"     => "string",
                "Caption"  => "Area code",
                "Required" => true,
            ],
            "PhoneLocalNumber" => [
                "Type"     => "string",
                "Caption"  => "Phone number",
                "Required" => true,
            ],
            "Email" => [
                "Type"     => "string",
                "Caption"  => "Email Address",
                "Required" => true,
            ],
            "Username" => [
                "Type"     => "string",
                "Caption"  => "Username",
                "Required" => true,
            ],
            "Password" => [
                "Type"     => "string",
                "Caption"  => "Password (must contain at least one number, uppercase letter or one of the following special characters: ! @ # $ % ^ * ( ) , . ; : / \\ )",
                "Required" => true,
            ],
            "SecurityQuestionType1" => [
                "Type"     => "string",
                "Caption"  => "First Security Question",
                "Required" => true,
                "Options"  => self::$securityQuestions,
            ],
            "SecurityQuestionAnswer1" => [
                "Type"     => "string",
                "Caption"  => "Answer to the First Security Question",
                "Required" => true,
            ],
            "SecurityQuestionType2" => [
                "Type"     => "string",
                "Caption"  => "Second Security Question",
                "Required" => true,
                "Options"  => self::$securityQuestions,
            ],
            "SecurityQuestionAnswer2" => [
                "Type"     => "string",
                "Caption"  => "Answer to the Second Security Question",
                "Required" => true,
            ],
            "PromoCode" => [
                "Type"     => "string",
                "Caption"  => "Promotion Code for Enrollment in Rapid Rewards",
                "Required" => false,
            ],
            "EmailStatements" => [
                "Type"     => "boolean",
                "Caption"  => "Send Monthly Account Statements",
                "Required" => false,
            ],
            "EmailUpdates" => [
                "Type"     => "boolean",
                "Caption"  => "Send emails highlighting the latest ways to maximize your Membership",
                "Required" => false,
            ],
            "EmailDeals" => [
                "Type"     => "boolean",
                "Caption"  => "Send emails showcasing best deals on flights, hotels, rental cars, packages & more",
                "Required" => false,
            ],
            "EmailNutshell" => [
                "Type"     => "boolean",
                "Caption"  => "Send emails informing you of latest contests, new routes, special product discounts, and more",
                "Required" => false,
            ],
        ];
    }

    public static function countries($checker)
    {
        $checker->http->GetURL('https://www.southwest.com/account/enroll/enroll-member');
        $json = $checker->http->FindSingleNode('//script[contains(.,"var countriesJSON =")]', null, true, "/var countriesJSON =\s*'(\{[^}]+\})'/");

        if (!isset($json) || !($countries = json_decode($json, true))) {
            $checker->logger->error('Failed to load phone country codes.');

            return false;
        }

        return $countries;
    }

    protected function checkSuccess()
    {
        if ($h = $this->http->FindSingleNode('//h1[@id="accountCreatedHeader" and contains(.,"Your Account Has Been Created")]')) {
            $number = $this->http->FindSingleNode('//div[@id="accountNumber"]', null, true, '/^\d+/');
            $this->ErrorMessage = $h . ' Account Number: ' . $number;

            return true;
        }

        return false;
    }
}
