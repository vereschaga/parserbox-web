<?php

// case #10396

namespace AwardWallet\Engine\triprewards\Transfer;

class Register extends \TAccountCheckerTriprewards
{
    public static $fieldMap = [
        'FirstName' => 'memberBean.firstName',
        'LastName'  => 'memberBean.lastName',
        'Email'     => 'memberBean.emailAddress',

        'AddressType' => 'memberBean.addrInd',
        // 'Company' => 'tmpCompFld',
        'AddressLine1'    => 'memberBean.addrLine1',
        'AddressLine2'    => 'memberBean.addrLine2',
        'AddressLine3'    => 'memberBean.addrLine3',
        'Country'         => 'memberBean.country',
        'City'            => 'memberBean.city',
        'StateOrProvince' => 'memberBean.state',

        'PostalCode'  => 'memberBean.zipCode',
        'PhoneNumber' => 'memberBean.phoneNumber',
        'PhoneType'   => 'memberBean.phoneType',

        'Username' => 'memberBean.enrolluserName',
        'Password' => [
            'memberBean.enrollPassword',
            'memberBean.enrollPasswordConf',
        ],

        'EarningPreference' => 'memberBean.pointsType',
        // 'FrequentTravelerPartner' => 'memberBean.partnerID',
        // 'FrequentTravelerNumber' => 'memberBean.freqTravAccNum',
        // 'FrequentTravelerFirstName' => 'memberBean.firstNameOnFreqTravAcc',
        // 'FrequentTravelerLastName' => 'memberBean.lastNameOnFreqTravAcc',

        'ReceiveNewslettersAndPromotions' => 'emailOptOut',
    ];

    public static $securityFieldMap = [
        'SecurityQuestionType1'   => 'memberBean.securityQuestions[0].dataCode.id',
        'SecurityQuestionAnswer1' => 'memberBean.securityQuestions[0].answer',
        'SecurityQuestionType2'   => 'memberBean.securityQuestions[1].dataCode.id',
        'SecurityQuestionAnswer2' => 'memberBean.securityQuestions[1].answer',
        'SecurityQuestionType3'   => 'memberBean.securityQuestions[2].dataCode.id',
        'SecurityQuestionAnswer3' => 'memberBean.securityQuestions[2].answer',
    ];

    public static $phoneTypes = [
        'W' => 'Business',
        'C' => 'Mobile',
        'H' => 'Home',
    ];

    public static $countries = [
        "AD" => "ANDORRA",
        "AE" => "UNITED ARAB EMIRATES",
        "AF" => "AFGHANISTAN",
        "AG" => "ANTIGUA AND BARBUDA",
        "AI" => "ANGUILLA",
        "AL" => "ALBANIA",
        "AM" => "ARMENIA",
        "AN" => "NETHERLANDS ANTILLES",
        "AO" => "ANGOLA",
        "AQ" => "ANTARCTICA",
        "AR" => "ARGENTINA",
        "AS" => "AMERICAN SAMOA",
        "AT" => "AUSTRIA",
        "AU" => "AUSTRALIA",
        "AW" => "ARUBA",
        "AZ" => "AZERBAIJAN",
        "BA" => "BOSNIA AND HERZEGOVINA",
        "BB" => "BARBADOS",
        "BD" => "BANGLADESH",
        "BE" => "BELGIUM",
        "BF" => "BURKINA FASO",
        "BG" => "BULGARIA",
        "BH" => "BAHRAIN",
        "BI" => "BURUNDI",
        "BJ" => "BENIN",
        "BM" => "BERMUDA",
        "BN" => "BRUNEI DARUSSALAM",
        "BO" => "BOLIVIA",
        "BR" => "BRAZIL",
        "BS" => "BAHAMAS",
        "BT" => "BHUTAN",
        "BV" => "BOUVET ISLAND",
        "BW" => "BOTSWANA",
        "BY" => "BELARUS",
        "BZ" => "BELIZE",
        "CA" => "CANADA",
        "CC" => "COCOS (KEELING) ISLANDS",
        "CD" => "CONGO, THE DEMOCRATIC REPUBLIC OF THE",
        "CF" => "CENTRAL AFRICAN REPUBLIC",
        "CG" => "CONGO",
        "CH" => "SWITZERLAND",
        "CI" => "CÔTE D'IVOIRE",
        "CK" => "COOK ISLANDS",
        "CL" => "CHILE",
        "CM" => "CAMEROON",
        "CN" => "CHINA",
        "CO" => "COLOMBIA",
        "CR" => "COSTA RICA",
        "CS" => "SERBIA AND MONTENEGRO",
        "CU" => "CUBA",
        "CV" => "CAPE VERDE",
        "CX" => "CHRISTMAS ISLAND",
        "CY" => "CYPRUS",
        "CZ" => "CZECH REPUBLIC",
        "DE" => "GERMANY",
        "DJ" => "DJIBOUTI",
        "DK" => "DENMARK",
        "DM" => "DOMINICA",
        "DO" => "DOMINICAN REPUBLIC",
        "DZ" => "ALGERIA",
        "EC" => "ECUADOR",
        "EE" => "ESTONIA",
        "EG" => "EGYPT",
        "EH" => "WESTERN SAHARA",
        "ER" => "ERITREA",
        "ES" => "SPAIN",
        "ET" => "ETHIOPIA",
        "FI" => "FINLAND",
        "FJ" => "FIJI",
        "FK" => "FALKLAND ISLANDS (MALVINAS)",
        "FM" => "MICRONESIA, FEDERATED STATES OF",
        "FO" => "FAROE ISLANDS",
        "FR" => "FRANCE",
        "FX" => "FRANCE, METROPOLITAN",
        "GA" => "GABON",
        "GB" => "UNITED KINGDOM",
        "GD" => "GRENADA",
        "GE" => "GEORGIA",
        "GF" => "FRENCH GUIANA",
        "GH" => "GHANA",
        "GI" => "GIBRALTAR",
        "GL" => "GREENLAND",
        "GM" => "GAMBIA",
        "GN" => "GUINEA",
        "GP" => "GUADELOUPE",
        "GQ" => "EQUATORIAL GUINEA",
        "GR" => "GREECE",
        "GS" => "SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS",
        "GT" => "GUATEMALA",
        "GU" => "GUAM",
        "GW" => "GUINEA-BISSAU",
        "GY" => "GUYANA",
        "HK" => "HONG KONG",
        "HM" => "HEARD ISLAND AND MCDONALD ISLANDS",
        "HN" => "HONDURAS",
        "HR" => "CROATIA",
        "HT" => "HAITI",
        "HU" => "HUNGARY",
        "ID" => "INDONESIA",
        "IE" => "IRELAND",
        "IL" => "ISRAEL",
        "IN" => "INDIA",
        "IO" => "BRITISH INDIAN OCEAN TERRITORY",
        "IQ" => "IRAQ",
        "IR" => "IRAN, ISLAMIC REPUBLIC OF",
        "IS" => "ICELAND",
        "IT" => "ITALY",
        "JM" => "JAMAICA",
        "JO" => "JORDAN",
        "JP" => "JAPAN",
        "KE" => "KENYA",
        "KG" => "KYRGYZSTAN",
        "KH" => "CAMBODIA",
        "KI" => "KIRIBATI",
        "KM" => "COMOROS",
        "KN" => "SAINT KITTS AND NEVIS",
        "KP" => "KOREA, DEMOCRATIC PEOPLE'S REPUBLIC OF",
        "KR" => "KOREA, REPUBLIC OF",
        "KW" => "KUWAIT",
        "KY" => "CAYMAN ISLANDS",
        "KZ" => "KAZAKHSTAN",
        "LA" => "LAO PEOPLES DEMOCRATIC REPUBLIC",
        "LB" => "LEBANON",
        "LC" => "SAINT LUCIA",
        "LI" => "LIECHTENSTEIN",
        "LK" => "SRI LANKA",
        "LR" => "LIBERIA",
        "LS" => "LESOTHO",
        "LT" => "LITHUANIA",
        "LU" => "LUXEMBOURG",
        "LV" => "LATVIA",
        "LY" => "LIBYAN ARAB JAMAHIRIYA",
        "MA" => "MOROCCO",
        "MC" => "MONACO",
        "MD" => "MOLDOVA, REPUBLIC OF",
        "ME" => "MONTENEGRO",
        "MG" => "MADAGASCAR",
        "MH" => "MARSHALL ISLANDS",
        "MK" => "MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF",
        "ML" => "MALI",
        "MM" => "MYANMAR",
        "MN" => "MONGOLIA",
        "MO" => "MACAO",
        "MP" => "NORTHERN MARIANA ISLANDS",
        "MQ" => "MARTINIQUE",
        "MR" => "MAURITANIA",
        "MS" => "MONTSERRAT",
        "MT" => "MALTA",
        "MU" => "MAURITIUS",
        "MV" => "MALDIVES",
        "MW" => "MALAWI",
        "MX" => "MEXICO",
        "MY" => "MALAYSIA",
        "MZ" => "MOZAMBIQUE",
        "NA" => "NAMIBIA",
        "NC" => "NEW CALEDONIA",
        "NE" => "NIGER",
        "NF" => "NORFOLK ISLAND",
        "NG" => "NIGERIA",
        "NI" => "NICARAGUA",
        "NL" => "NETHERLANDS",
        "NO" => "NORWAY",
        "NP" => "NEPAL",
        "NR" => "NAURU",
        "NU" => "NIUE",
        "NZ" => "NEW ZEALAND",
        "OM" => "OMAN",
        "PA" => "PANAMA",
        "PE" => "PERU",
        "PF" => "FRENCH POLYNESIA",
        "PG" => "PAPUA NEW GUINEA",
        "PH" => "PHILIPPINES",
        "PK" => "PAKISTAN",
        "PL" => "POLAND",
        "PM" => "SAINT PIERRE AND MIQUELON",
        "PN" => "PITCAIRN",
        "PR" => "PUERTO RICO",
        "PS" => "PALESTINIAN TERRITORY, OCCUPIED",
        "PT" => "PORTUGAL",
        "PW" => "PALAU",
        "PY" => "PARAGUAY",
        "QA" => "QATAR",
        "RE" => "RÉUNION",
        "RO" => "ROMANIA",
        "RU" => "RUSSIAN FEDERATION",
        "RW" => "RWANDA",
        "SA" => "SAUDI ARABIA",
        "SB" => "SOLOMON ISLANDS",
        "SC" => "SEYCHELLES",
        "SD" => "SUDAN",
        "SE" => "SWEDEN",
        "SG" => "SINGAPORE",
        "SH" => "SAINT HELENA",
        "SI" => "SLOVENIA",
        "SJ" => "SVALBARD AND JAN MAYEN",
        "SK" => "SLOVAKIA",
        "SL" => "SIERRA LEONE",
        "SM" => "SAN MARINO",
        "SN" => "SENEGAL",
        "SO" => "SOMALIA",
        "SR" => "SURINAME",
        "ST" => "SAO TOME AND PRINCIPE",
        "SV" => "EL SALVADOR",
        "SY" => "SYRIAN ARAB REPUBLIC",
        "SZ" => "SWAZILAND",
        "TC" => "TURKS AND CAICOS ISLANDS",
        "TD" => "CHAD",
        "TF" => "FRENCH SOUTHERN TERRITORIES",
        "TG" => "TOGO",
        "TH" => "THAILAND",
        "TJ" => "TAJIKISTAN",
        "TK" => "TOKELAU",
        "TL" => "TIMOR-LESTE",
        "TM" => "TURKMENISTAN",
        "TN" => "TUNISIA",
        "TO" => "TONGA",
        "TP" => "EAST TIMOR",
        "TR" => "TURKEY",
        "TT" => "TRINIDAD AND TOBAGO",
        "TV" => "TUVALU",
        "TW" => "TAIWAN, REPUBLIC OF CHINA",
        "TZ" => "TANZANIA, UNITED REPUBLIC OF",
        "UA" => "UKRAINE",
        "UG" => "UGANDA",
        "UM" => "UNITED STATES MINOR OUTLYING ISLANDS",
        "US" => "UNITED STATES",
        "UY" => "URUGUAY",
        "UZ" => "UZBEKISTAN",
        "VA" => "HOLY SEE (VATICAN CITY STATE)",
        "VC" => "SAINT VINCENT AND THE GRENADINES",
        "VE" => "VENEZUELA",
        "VG" => "VIRGIN ISLANDS, BRITISH",
        "VI" => "VIRGIN ISLANDS, U.S.",
        "VN" => "VIET NAM",
        "VU" => "VANUATU",
        "WF" => "WALLIS AND FUTUNA",
        "WS" => "SAMOA",
        "XX" => "UNKNOWN",
        "YE" => "YEMEN",
        "YT" => "MAYOTTE",
        "YU" => "YUGOSLAVIA (SERBIA AND MONTENEGRO)",
        "ZA" => "SOUTH AFRICA",
        "ZM" => "ZAMBIA",
        "ZW" => "ZIMBABWE",
    ];

    public static $earnings = [
        'P' => 'Wyndham',
        'M' => 'Partners',
    ];

    public static $addressTypes = [
        'B' => 'Business',
        'H' => 'Home',
    ];

    public static $statesByCountry = [
        'US' => [
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
            'MP' => 'Northern Mariana Islands',
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
            'UM' => 'United States Minor Outlying Islands',
            'UT' => 'Utah',
            'VT' => 'Vermont',
            'VI' => 'Virgin Islands, U.S.',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming',
            'AA' => 'Armed Forces Americas',
            'AE' => 'Armed Forces Europe',
            'AP' => 'Armed Forces Pacific',
        ],
        'CA' => [
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
            'YT' => 'Yukon Territory',
        ],
    ];

    public static $partners = [
        '8340' => 'AEROMEXICO PREMIER MILES',
        '2280' => 'AEROPLAN (AIR CANADA)',
        '4520' => 'AIR CHINA PHOENIX MILES',
        '5704' => 'ALASKA AIRLINES MILEAGE PLAN',
        '1391' => 'AMERICAN AIRLINES AADVANTAGE',
        '1394' => 'AMTRAK GUEST REWARDS',
        '7520' => 'CHINA EASTERN AIRLINES EASTERN MILES',
        '7781' => 'CHINA SOUTHERN SKY PEARL CLUB',
        '7641' => 'CZECH AIRLINES OK PLUS',
        '4500' => 'FRONTIER AIRLINES EARLYRETURNS',
        '7400' => 'HAINAN AIRLINES FORTUNE WINGS CLUB',
        '5707' => 'HAWAIIAN AIRLINES HAWAIIANMILES',
        '5703' => 'JET AIRWAYS JPMILES',
        '5708' => 'MELIA REWARDS',
        '5701' => 'MILES AND MORE',
        '5705' => 'QATAR AIRWAYS PRIVILEGE CLUB QMILES',
        '5702' => 'SAUDI ARABIAN AIRLINES ALFURSAN MILES',
        '8200' => 'SOUTHWEST AIRLINES RAPID REWARDS',
        '4482' => 'TOPBONUS MILES',
        '2000' => 'TURKISH AIRLINES MILES & SMILES',
        '4320' => 'UNITED MILEAGE PLUS',
        '4280' => 'US AIRWAYS DIVIDEND MILES',
    ];

    public static $securityQuestions = [
        '52438' => 'In what city or town did you meet your spouse/partner?',
        '52439' => 'In what city or town does your nearest sibling live?',
        '52440' => 'In what city or town was your first job?',
        '1791'  => 'In what city were you born?',
        '52441' => 'To what city did you go on your honeymoon?',
        '1717'  => 'What elementary school did you attend?',
        '52442' => 'What is the name of your favorite childhood friend?',
        '52437' => 'What is your favorite pet\'s name?',
        '1790'  => 'What is your mother\'s maiden name?',
        '1793'  => 'What is your mother\'s middle name?',
        '52443' => 'What is your oldest cousin\'s first name?',
        '52444' => 'What is your oldest sibling\'s birthday month and year? (e.g., January 1900)',
        '1787'  => 'What is your paternal grandmother\'s maiden name?',
        '1792'  => 'What is your wedding anniversary (mmddyy)?',
        '1715'  => 'What street did you grow up on?',
        '1789'  => 'What was the name of your first boyfriend/girlfriend?',
        '52445' => 'What was your childhood nickname?',
        '1716'  => 'What was your first best friend\'s name?',
        '1788'  => 'What was your high school mascot?',
        '52446' => 'What was your maternal grandfather\'s first name?',
    ];

    public function InitBrowser()
    {
        $this->UseCurlBrowser();
        $this->AccountFields['BrowserState'] = null;
    }

    public function registerAccount(array $fields)
    {
        $this->http->Log('[INFO] ' . __METHOD__);
        $this->ArchiveLogs = true;

        $this->http->GetURL('https://www.wyndhamrewards.com/trec/consumer/consumerEnroll.action?variant=');

        if (!$this->http->ParseForm('enrollForm')) {
            $this->http->Log('[INFO] failed to parse registration form');

            return false;
        }
        $this->http->FormURL = "https://www.wyndhamrewards.com/trec/consumer/enrollSubmit.action";
        $this->http->Log('=cookies:');
        $this->http->Log(print_r($this->http->getCookies('www.wyndhamrewards.com', '/trec', 1), true));

        $fields['EarningPreference'] = 'P';
        $fields['AddressType'] = 'H';

        //todo: adjust this to boolean value in ReceiveNewslettersAndPromotions
        if (ArrayVal($fields, 'ReceiveNewslettersAndPromotions')) {
            $fields['ReceiveNewslettersAndPromotions'] = 'true';
        }

        if ($fields['Country'] == 'RS') {
            // Provider uses wrong country codes for SERBIA AND MONTENEGRO (CS instead of standard RS)
            // Map from our standard ISO code to wrong code used by provider
            $fields['Country'] = 'CS';
            $this->logger->debug('Mapped standard country code "RS" to provider code "CS"');
        }

        foreach (self::$fieldMap as $awkey => $keys) {
            if (!isset($fields[$awkey])) {
                continue;
            }

            if (!is_array($keys)) {
                $keys = [$keys];
            }

            foreach ($keys as $k) {
                $this->http->SetInputValue($k, $fields[$awkey]);
            }
        }

        $encfs = $this->http->FindNodes('//input[@name = "encf"]/@value');
        $this->http->MultiValuedForms = true;
        $this->http->Form['encf'] = $encfs;

        $this->http->PostForm();
        $error = $this->http->FindSingleNode('//div[@class = "errorMessage"]');

        if ($error) {
            $this->http->Log('[INFO] invalid fields:');
            $this->http->Log("$error");

            if ($error == 'Please correct the following error(s): Sorry, the username is already taken. Please try with a different username.') {
                throw new \ProviderError($error);
            } else {
                throw new \UserInputError($error);
            } // Is it always user input error?
        }

        $success = $this->http->FindPreg('/Thanks so much for joining Wyndham Rewards!/iu');

        if ($success) {
            $acc = $this->http->FindSingleNode('//*[@id = "accountNumber"]/@value');
            $msg = 'Successfull registration.';

            if ($acc) {
                $msg = "$msg Your account number is $acc.";
            } else {
                $msg = "$msg Please obtain your account number manually.";
            }

            if (!$this->setSecurityAnswers($fields)) {
                $msg = "$msg Unfortunately you have to set security answers yourself.";
            } else {
                $msg = "$msg Security questions set.";
            }

            $this->http->Log($msg);
            $this->ErrorMessage = $msg;

            return true;
        }

        $this->http->Log('unknown error');

        return false;
    }

    public function setSecurityAnswers($fields)
    {
        $this->http->Log('[INFO] ' . __METHOD__);

        if (!isset($this->AccountFields)) {
            $this->AccountFields = [];
        }
        $this->AccountFields['Login'] = $fields['Username'];
        $this->AccountFields['Pass'] = $fields['Password'];
        $this->http->log('[INFO] AccountFields:');
        $this->http->log(print_r($this->AccountFields, true));

        $this->http->removeCookies();

        if (!$this->loadLoginForm() || !$this->http->postForm()) { // temp, should be login
            $this->http->log('[INFO] failed to log in');

            return false;
        }

        // security form
        if (!$this->http->parseForm('enrollForm')) {
            $this->http->log('failed to find security questions form');

            return false;
        }

        foreach (self::$securityFieldMap as $awkey => $key) {
            if (!isset($fields[$awkey])) {
                continue;
            }
            $this->http->setInputValue($key, $fields[$awkey]);
        }

        $this->http->postForm();
        $error = $this->http->findSingleNode('//div[@class = "errorMessage"]');

        if ($error) {
            $this->http->log('[INFO] invalid fields:');
            $this->http->log("$error");

            throw new \UserInputError($error); // Is it always user input error?
        }

        // confirm
        if (!$this->http->parseForm('enrollForm')) {
            $this->http->log('failed to find security confirmation form');

            return false;
        }
        $this->http->postForm();

        $this->http->getUrl('https://www.wyndhamrewards.com/trec/consumer/home.action?variant=');

        if (preg_match('/home[.]action/i', $this->http->currentUrl())) {
            return true;
        }

        $this->http->log('[INFO] setting security answers failed');

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
            'Email' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],

            // set to H
            // 'AddressType' =>
            // array (
            //   'Type' => 'string',
            //   'Caption' => 'Address Type',
            //   'Required' => true,
            //   'Options' => self::$addressTypes,
            // ),
            // 'Company' =>
            // array (
            //   'Type' => 'string',
            //   'Caption' => 'Company',
            //   'Required' => false,
            // ),

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
            'AddressLine3' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Address Line 3',
                'Required' => false,
            ],
            'Country' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Options'  => self::$countries,
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
                'Caption'  => 'State or Province (required for USA and Canada)',
                'Required' => false,
            ],

            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Postal Code',
                'Required' => true,
            ],
            'PhoneNumber' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Phone Number',
                'Required' => true,
            ],
            'PhoneType' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Phone Type',
                'Required' => true,
                'Options'  => self::$phoneTypes,
            ],

            'Username' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Username (must be between 8 and 16 characters)',
                'Required' => true,
            ],
            'Password' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Password (must be between 8 and 16 characters and contain at least 1 number and 1 letter)',
                'Required' => true,
            ],

            // set to P
            // 'EarningPreference' =>
            // array (
            //   'Type' => 'string',
            //   'Caption' => 'Earning Preference',
            //   'Required' => true,
            //   'Options' => self::$earnings,
            // ),
            // 'FrequentTravelerPartner' =>
            // array (
            // 	'Type' => 'integer',
            // 	'Caption' => 'Frequent Traveler Partner',
            // 	'Required' => false,
            // 	'Options' => self::$partners,
            // ),
            // 'FrequentTravelerNumber' =>
            // array (
            //   'Type' => 'string',
            //   'Caption' => 'Frequent Traveler Number',
            //   'Required' => false,
            // ),
            // 'FrequentTravelerFirstName' =>
            // array (
            //   'Type' => 'string',
            //   'Caption' => 'Frequent Traveler First Name',
            //   'Required' => false,
            // ),
            // 'FrequentTravelerLastName' =>
            // array (
            //   'Type' => 'string',
            //   'Caption' => 'Frequent Traveler Last Name',
            //   'Required' => false,
            // ),

            'ReceiveNewslettersAndPromotions' =>
            [
                'Type'     => 'boolean',
                'Caption'  => 'Receive Newsletters and Promotions',
                'Required' => false,
            ],

            'SecurityQuestionType1' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Security Question 1',
                'Required' => true,
                'Options'  => self::$securityQuestions,
            ],
            'SecurityQuestionAnswer1' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Security Answer 1',
                'Required' => true,
            ],
            'SecurityQuestionType2' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Security Question 2',
                'Required' => true,
                'Options'  => self::$securityQuestions,
            ],
            'SecurityQuestionAnswer2' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Security Answer 2',
                'Required' => true,
            ],
            'SecurityQuestionType3' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Security Question 3',
                'Required' => true,
                'Options'  => self::$securityQuestions,
            ],
            'SecurityQuestionAnswer3' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Security Answer 3',
                'Required' => true,
            ],
        ];
    }

    public static function states()
    {
        $statesPlain = [];

        foreach (self::$statesByCountry as $s) {
            $statesPlain = array_merge($statesPlain, $s);
        }

        return $statesPlain;
    }
}
