<?php

namespace AwardWallet\Engine\japanair\Transfer;

class Register extends \TAccountCheckerJapanair
{
    public static $titles = [
        'MR'   => 'Mr',
        'MS'   => 'Ms',
        'DR'   => 'Dr',
        'PROF' => 'Prof',
        'REV'  => 'Rev',
    ];

    public static $countries = [
        "ER" => [
            "ALBANIA"              => "ALBANIA",
            "ALGERIA"              => "ALGERIA",
            "ANDORRA"              => "ANDORRA",
            "ANGOLA"               => "ANGOLA",
            "ARMENIA"              => "ARMENIA",
            "AUSTRIA"              => "AUSTRIA",
            "AZERBAIJAN"           => "AZERBAIJAN",
            "BAHRAIN"              => "BAHRAIN",
            "BELARUS"              => "BELARUS",
            "BELGIUM"              => "BELGIUM",
            "BENIN"                => "BENIN",
            "BOSNIA HERZEGOVINA"   => "BOSNIA HERZEGOVINA",
            "BOTSWANA"             => "BOTSWANA",
            "BULGARIA"             => "BULGARIA",
            "BURKINA FASO"         => "BURKINA FASO",
            "BURUNDI"              => "BURUNDI",
            "CAMEROON"             => "CAMEROON",
            "CAPE VERDE"           => "CAPE VERDE",
            "CENTRAL AFRICAN REP"  => "CENTRAL AFRICAN REP",
            "CHAD"                 => "CHAD",
            "COMOROS"              => "COMOROS",
            "CONGO"                => "CONGO",
            "CONGO (DEM REP. OF) " => "CONGO (DEM REP. OF) ",
            "CROATIA"              => "CROATIA",
            "CYPRUS"               => "CYPRUS",
            "CZECH REPUBLIC"       => "CZECH REPUBLIC",
            "DENMARK"              => "DENMARK",
            "DJIBOUTI"             => "DJIBOUTI",
            "EGYPT"                => "EGYPT",
            "EQUATORIAL GUINEA"    => "EQUATORIAL GUINEA",
            "ERITREA"              => "ERITREA",
            "ESTONIA"              => "ESTONIA",
            "ETHIOPIA"             => "ETHIOPIA",
            "FINLAND"              => "FINLAND",
            "FRANCE"               => "FRANCE",
            "GABON"                => "GABON",
            "GAMBIA"               => "GAMBIA",
            "GEORGIA"              => "GEORGIA",
            "GERMANY"              => "GERMANY",
            "GHANA"                => "GHANA",
            "GIBRALTAR"            => "GIBRALTAR",
            "GREECE"               => "GREECE",
            "GUINEA"               => "GUINEA",
            "GUINEA BISSAU"        => "GUINEA BISSAU",
            "HUNGARY"              => "HUNGARY",
            "ICELAND"              => "ICELAND",
            "IRAN"                 => "IRAN",
            "IRAQ"                 => "IRAQ",
            "IRELAND"              => "IRELAND",
            "ISRAEL"               => "ISRAEL",
            "ITALY"                => "ITALY",
            "IVORY COAST"          => "IVORY COAST",
            "JORDAN"               => "JORDAN",
            "KAZAKHSTAN"           => "KAZAKHSTAN",
            "KENYA"                => "KENYA",
            "KUWAIT"               => "KUWAIT",
            "KYRGYZSTAN "          => "KYRGYZSTAN ",
            "LATVIA"               => "LATVIA",
            "LEBANON"              => "LEBANON",
            "LESOTHO"              => "LESOTHO",
            "LIBERIA"              => "LIBERIA",
            "LIBYA"                => "LIBYA",
            "LIECHTENSTEIN"        => "LIECHTENSTEIN",
            "LITHUANIA"            => "LITHUANIA",
            "LUXEMBOURG"           => "LUXEMBOURG",
            "MACEDONIA"            => "MACEDONIA",
            "MADAGASCAR"           => "MADAGASCAR",
            "MALAWI"               => "MALAWI",
            "MALI"                 => "MALI",
            "MALTA"                => "MALTA",
            "MAURITANIA"           => "MAURITANIA",
            "MAURITIUS"            => "MAURITIUS",
            "MOLDOVA"              => "MOLDOVA",
            "MONACO"               => "MONACO",
            "MONTENEGRO"           => "MONTENEGRO",
            "MOROCCO"              => "MOROCCO",
            "MOZAMBIQUE"           => "MOZAMBIQUE",
            "NAMIBIA"              => "NAMIBIA",
            "NETHERLANDS"          => "NETHERLANDS",
            "NIGER"                => "NIGER",
            "NIGERIA"              => "NIGERIA",
            "NORWAY"               => "NORWAY",
            "OMAN"                 => "OMAN",
            "POLAND"               => "POLAND",
            "PORTUGAL"             => "PORTUGAL",
            "QATAR"                => "QATAR",
            "REUNION"              => "REUNION",
            "ROMANIA"              => "ROMANIA",
            "RUSSIA"               => "RUSSIA",
            "RWANDA"               => "RWANDA",
            "SAN MARINO"           => "SAN MARINO",
            "SAO TOME PRINCIPE"    => "SAO TOME PRINCIPE",
            "SAUDI ARABIA"         => "SAUDI ARABIA",
            "SENEGAL"              => "SENEGAL",
            "SERBIA"               => "SERBIA",
            "SEYCHELLES"           => "SEYCHELLES",
            "SIERRA LEONE"         => "SIERRA LEONE",
            "SLOVAKIA"             => "SLOVAKIA",
            "SLOVENIA"             => "SLOVENIA",
            "SOMALIA"              => "SOMALIA",
            "SOUTH AFRICA"         => "SOUTH AFRICA",
            "SPAIN"                => "SPAIN",
            "SUDAN"                => "SUDAN",
            "SWAZILAND"            => "SWAZILAND",
            "SWEDEN"               => "SWEDEN",
            "SWITZERLAND"          => "SWITZERLAND",
            "SYRIA "               => "SYRIA ",
            "TAJIKISTAN"           => "TAJIKISTAN",
            "TANZANIA"             => "TANZANIA",
            "TOGO"                 => "TOGO",
            "TUNISIA"              => "TUNISIA",
            "TURKEY"               => "TURKEY",
            "TURKMENISTAN"         => "TURKMENISTAN",
            "UGANDA"               => "UGANDA",
            "UKRAINE"              => "UKRAINE",
            "UNITED ARAB EMIRATES" => "UNITED ARAB EMIRATES",
            "UNITED KINGDOM"       => "UNITED KINGDOM",
            "UZBEKISTAN"           => "UZBEKISTAN",
            "YEMEN"                => "YEMEN",
            "ZAMBIA"               => "ZAMBIA",
            "ZIMBABWE"             => "ZIMBABWE",
        ],
        "SR" => [
            "AFGHANISTAN"       => "AFGHANISTAN",
            "AUSTRALIA"         => "AUSTRALIA",
            "BANGLADESH"        => "BANGLADESH",
            "KINGDOM OF BHUTAN" => "BHUTAN",
            "BRUNEI"            => "BRUNEI",
            "CAMBODIA"          => "CAMBODIA",
            "DEM REP OF LAO"    => "DEM REP OF LAO",
            "FIJI"              => "FIJI",
            "FRENCH POLYNESIA"  => "FRENCH POLYNESIA",
            "HONG KONG"         => "HONG KONG",
            "INDEPENDENT SAMOA" => "INDEPENDENT SAMOA",
            "INDIA"             => "INDIA",
            "INDONESIA"         => "INDONESIA",
            "KIRIBATI"          => "KIRIBATI",
            "KOREA"             => "KOREA",
            "MACAU"             => "MACAU",
            "MALAYSIA"          => "MALAYSIA",
            "MALDIVES"          => "MALDIVES",
            "MONGOLIA"          => "MONGOLIA",
            "MYANMAR"           => "MYANMAR",
            "NAURU"             => "NAURU",
            "NEPAL"             => "NEPAL",
            "NEW CALEDONIA"     => "NEW CALEDONIA",
            "NEW ZEALAND"       => "NEW ZEALAND",
            "PAKISTAN"          => "PAKISTAN",
            "PAPUA NEW GUINEA"  => "PAPUA NEW GUINEA",
            "PEOPLES REP KOREA" => "PEOPLES REP KOREA",
            "PHILIPPINES"       => "PHILIPPINES",
            "SINGAPORE"         => "SINGAPORE",
            "SOLOMON ISLANDS"   => "SOLOMON ISLANDS",
            "SRI LANKA"         => "SRI LANKA",
            "TAIWAN"            => "TAIWAN",
            "THAILAND"          => "THAILAND",
            "TIMOR LESTE"       => "TIMOR LESTE",
            "TONGA"             => "TONGA",
            "TUVALU"            => "TUVALU",
            "VANUATU"           => "VANUATU",
            "VIETNAM"           => "VIETNAM",
        ],
        "AR" => [
            "ANGUILLA"                 => "ANGUILLA",
            "ANTIGUA &amp; BARBUDA"    => "ANTIGUA &amp; BARBUDA",
            "ARGENTINA"                => "ARGENTINA",
            "ARUBA"                    => "ARUBA",
            "BAHAMAS"                  => "BAHAMAS",
            "BARBADOS"                 => "BARBADOS",
            "BELIZE"                   => "BELIZE",
            "BERMUDA"                  => "BERMUDA",
            "BOLIVIA"                  => "BOLIVIA",
            "BRAZIL"                   => "BRAZIL",
            "BRITISH VIRGIN IS"        => "BRITISH VIRGIN IS",
            "CANADA"                   => "CANADA",
            "CAYMAN ISLANDS"           => "CAYMAN ISLANDS",
            "CHILE"                    => "CHILE",
            "COLOMBIA"                 => "COLOMBIA",
            "COSTA RICA"               => "COSTA RICA",
            "CUBA"                     => "CUBA",
            "DOMINICA"                 => "DOMINICA",
            "DOMINICAN REPUBLIC"       => "DOMINICAN REPUBLIC",
            "ECUADOR"                  => "ECUADOR",
            "EL SALVADOR"              => "EL SALVADOR",
            "FALKLAND ISLANDS"         => "FALKLAND ISLANDS",
            "FRENCH GUIANA"            => "FRENCH GUIANA",
            "GRENADA"                  => "GRENADA",
            "GUADELOUPE"               => "GUADELOUPE",
            "GUATEMALA"                => "GUATEMALA",
            "GUAM"                     => "GUAM (U.S.A)",
            "GUYANA"                   => "GUYANA",
            "HAITI"                    => "HAITI",
            "HONDURAS"                 => "HONDURAS",
            "JAMAICA"                  => "JAMAICA",
            "MARSHALL ISLANDS"         => "MARSHALL ISLANDS",
            "MARTINIQUE"               => "MARTINIQUE",
            "MEXICO"                   => "MEXICO",
            "MONTSERRAT"               => "MONTSERRAT",
            "NETHERLANDS ANTILLES"     => "NETHERLANDS ANTILLES",
            "NICARAGUA"                => "NICARAGUA",
            "N. MARIANA ISLANDS"       => "NORTHERN MARIANA ISLANDS",
            "PANAMA"                   => "PANAMA",
            "PALAU"                    => "PALAU",
            "PARAGUAY"                 => "PARAGUAY",
            "PERU"                     => "PERU",
            "PUERTO RICO"              => "PUERTO RICO",
            "ST KITTS &amp; NEVIS"     => "ST KITTS &amp; NEVIS",
            "ST LUCIA"                 => "ST LUCIA",
            "ST PIERRE &amp; MIQUELON" => "ST PIERRE &amp; MIQUELON",
            "ST VINCENT &amp; GRENADI" => "ST VINCENT &amp; GRENADI",
            "SURINAME"                 => "SURINAME",
            "TRINIDAD &amp; TOBAGO"    => "TRINIDAD &amp; TOBAGO",
            "TURKS &amp; CAICOS IS"    => "TURKS &amp; CAICOS IS",
            "URUGUAY"                  => "URUGUAY",
            "USA"                      => "USA",
            "VENEZUELA"                => "VENEZUELA",
        ],
        "JR" => [
            "JAPAN" => "JAPAN",
        ],
    ];

    public static $countriesMap = [
        'AI' => 'ANGUILLA',
        'AR' => 'ARGENTINA',
        'AW' => 'ARUBA',
        'BS' => 'BAHAMAS',
        'BB' => 'BARBADOS',
        'BZ' => 'BELIZE',
        'BM' => 'BERMUDA',
        'BO' => 'BOLIVIA',
        'BR' => 'BRAZIL',
        'VG' => 'BRITISH VIRGIN IS',
        'CA' => 'CANADA',
        'KY' => 'CAYMAN ISLANDS',
        'CL' => 'CHILE',
        'CO' => 'COLOMBIA',
        'CR' => 'COSTA RICA',
        'CU' => 'CUBA',
        'DM' => 'DOMINICA',
        'DO' => 'DOMINICAN REPUBLIC',
        'EC' => 'ECUADOR',
        'SV' => 'EL SALVADOR',
        'FK' => 'FALKLAND ISLANDS',
        'GF' => 'FRENCH GUIANA',
        'GD' => 'GRENADA',
        'GP' => 'GUADELOUPE',
        'GT' => 'GUATEMALA',
        'GY' => 'GUYANA',
        'HT' => 'HAITI',
        'HN' => 'HONDURAS',
        'JM' => 'JAMAICA',
        'MQ' => 'MARTINIQUE',
        'MX' => 'MEXICO',
        'MS' => 'MONTSERRAT',
        'NI' => 'NICARAGUA',
        'PA' => 'PANAMA',
        'PY' => 'PARAGUAY',
        'PE' => 'PERU',
        'PR' => 'PUERTO RICO',
        'KN' => 'ST KITTS & NEVIS',
        'LC' => 'ST LUCIA',
        'PM' => 'ST PIERRE & MIQUELON',
        'VC' => 'ST VINCENT & GRENADI',
        'SR' => 'SURINAME',
        'TT' => 'TRINIDAD & TOBAGO',
        'TC' => 'TURKS & CAICOS IS',
        'UY' => 'URUGUAY',
        'US' => 'USA',
        'VE' => 'VENEZUELA',
        'AG' => 'ANTIGUA &amp; BARBUDA',
    ];

    public static $states = [
        //		'US' => [
        'AL' => 'ALABAMA',
        'AK' => 'ALASKA',
        'AZ' => 'ARIZONA',
        'AR' => 'ARKANSAS',
        'CA' => 'CALIFORNIA',
        'CO' => 'COLORADO',
        'CT' => 'CONNECTICUT',
        'DE' => 'DELAWARE',
        'DC' => 'DIST OF COLUMBIA',
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
        'PR' => 'PUERTO RICO',
        'RI' => 'RHODE ISLAND',
        'SC' => 'SOUTH CAROLINA',
        'SD' => 'SOUTH DAKOTA',
        'TN' => 'TENNESSEE',
        'TX' => 'TEXAS',
        'UT' => 'UTAH',
        'VT' => 'VERMONT',
        'VI' => 'VIRGIN ISLANDS',
        'VA' => 'VIRGINIA',
        'WA' => 'WASHINGTON',
        'WV' => 'WEST VIRGINIA',
        'WI' => 'WISCONSIN',
        'WY' => 'WYOMING',
        //		],
        //		'CA' => [
        'AB' => 'ALBERTA',
        'BC' => 'BRITISH COLUMBIA',
        'MB' => 'MANITOBA',
        'NB' => 'NEW BRUNSWICK',
        'NF' => 'NEWFOUNDLAND',
        'NT' => 'NORTHWEST TERRITORIES',
        'NS' => 'NOVA SCOTIA',
        'NU' => 'NUNAVUT',
        'ON' => 'ONTARIO',
        'PE' => 'PRINCE EDWARD ISLAND',
        'QC' => 'QUEBEC',
        'SK' => 'SASKATCHEWAN',
        'YT' => 'YUKON',
        //		],
    ];

    public static $preferredLanguages = [
        'ja' => 'Japanese',
        'en' => 'English',
    ];

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $inputFieldsMap = [
        'Title'             => 'honor',
        'FirstName'         => 'romajiForeName',
        'LastName'          => 'romajiFamilyName',
        'PreferredName'     => 'embossName',
        'Email'             => ['mail', 'mailConfirm'],
        'PreferredLanguage' => ['mailHopeLanguage', 'hopeLanguage'],
        'BirthMonth'        => 'birthMon',
        'BirthDay'          => 'birthDay',
        'BirthYear'         => 'birthYear',
        'Gender'            => 'gender',
        'Pin'               => ['pass', 'passConfirm'],
        'Password'          => ['webPass', 'webPassConfirm'],
    ];

    protected $region;

    protected $languageMap = [
        'ja' => 1,
        'en' => 2,
    ];

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;
        $fields['PreferredLanguage'] = $this->languageMap[$fields['PreferredLanguage']];

        //		$origCountryCode = $fields['Country'];
        //		$fields['Country'] = self::$countriesMap[$fields['Country']];
        //		$this->logger->debug('Mapped standard country code "'.$origCountryCode.'" to provider code "'.$fields['Country'].'"');
        foreach (self::$countries as $key => $list) {
            if (array_key_exists($fields['Country'], $list)) {
                $this->region = $key;

                break;
            }
        }

        if (empty($this->region)) {
            throw new \UserInputError('Invalid Country');
        }

        $this->http->GetURL("https://www121.jal.co.jp/JmbWeb/{$this->region}/EnrollProposal_en.do");

        if (!$this->processForm('Parse')) {
            $this->http->Log('Failed parse 1st agree form');

            return false;
        }
        $this->http->SetInputValue('isAgreementFlg', '1');

        if ($this->region !== 'JR') {
            $this->http->SetInputValue('homeCountry', $fields['Country']);
        }
        $this->http->SetInputValue('x', '18');
        $this->http->SetInputValue('y', '17');

        if (!$this->processForm('Post')) {
            $this->http->Log('Failed post 1st agree form');

            return false;
        }

        if (!$this->processForm('Parse')) {
            $this->http->Log('Failed parse step2 data form');

            return false;
        }
//      Home addr required
//        if ($fields['AddressType'] === 'B' and (!isset($fields['Company']) or trim($fields['Company']) === ''))
//            throw new \UserInputError('"Company" field is required for Business Address type');

        //state for US Canada
        if (in_array($fields['Country'], ['CANADA', 'USA']) and (!isset($fields['StateOrProvince']) or trim($fields['StateOrProvince']) === '')) {
            throw new \UserInputError('"State Code" field is required for US or Canada');
        }

        $maping = $this->processAddrTypeFields('H');
        $fields['Phone'] = $fields['PhoneCountryCodeNumeric'] . $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];
        $fields['BirthDay'] = ($fields['BirthDay'] + 0) < 10 ? '0' . ($fields['BirthDay'] + 0) : $fields['BirthDay'];
        $fields['BirthMonth'] = ($fields['BirthMonth'] + 0) < 10 ? '0' . ($fields['BirthMonth'] + 0) : $fields['BirthMonth'];
        $fields['City'] = strtoupper($fields['City']);

        foreach ($maping as $awKey => $provKeys) {
            if (!isset($fields[$awKey])) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $this->http->SetInputValue($provKey, $fields[$awKey]);
            }
        }

        if ($this->region === 'ER') {
            $this->http->SetInputValue('countryHomeTel', $fields['PhoneCountryCodeNumeric']);
            $this->http->SetInputValue('homeTel1', $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber']);
        }

        if ($this->region === 'JR') {
            $this->http->SetInputValue('homePostalCode1', substr($fields['PostalCode'], 0, 3));
            $this->http->SetInputValue('homePostalCode2', substr($fields['PostalCode'], 3));

            $this->http->SetInputValue('homeTel3', substr($fields['Phone'], -4));
        }
        $this->http->SetInputValue('x', '18');
        $this->http->SetInputValue('y', '17');

        if (!$this->processForm('Post')) {
            $this->http->Log('Failed post step2 data form');

            return false;
        }

        if ($errorMessage = $this->http->FindSingleNode("//div[contains(@class, 'error')]/ul/li[1]")) {
            throw new \UserInputError($errorMessage);
        }

        if ($this->http->FindSingleNode("//span[contains(text(), 'Please select')]")) {
            $this->http->Log('Choose region form step');

            if (!$this->processForm('Parse')) {
                return false;
            }
            $select = $this->http->FindSingleNode("//select[@name='sszssu']/option[1]/@value");
            $this->http->SetInputValue('sszssu', $select);
            $this->http->SetInputValue('submit.x', '18');
            $this->http->SetInputValue('submit.y', '17');

            if (!$this->processForm('Post')) {
                return false;
            }
        }

        if (!$this->http->FindSingleNode("//p[contains(text(),'In accordance with the detailes below')]")) {
            $this->http->Log('Failed step3 confirm information');

            return false;
        }

        // step 2 confirm details
        if (!$this->processForm('Parse')) {
            return false;
        }

        if (!$this->processForm('Post')) {
            return false;
        }

        if ($errorMessage = $this->http->FindSingleNode("//div[contains(@class, 'error')]/ul/li[1]")) {
            throw new \UserInputError($errorMessage);
        } // Is it always user input error

        if ($successMessage = $this->http->FindSingleNode("//td[em[contains(text(),'Your membership number')]]/following-sibling::td/em")) {
            $this->ErrorMessage = 'Your membership number: ' . $successMessage;
            $this->http->log($this->ErrorMessage);

            return true;
        }

        return false;
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
                'Caption'  => 'Given Name',
                'Required' => true,
            ],
            'LastName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Family Name',
                'Required' => true,
            ],
            'PreferredName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Name as it will appear on Membership Card (must be within 23 characters)',
                'Required' => true,
            ],
            'Country' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Options'  => array_merge(self::$countries["AR"], self::$countries["ER"], self::$countries["SR"], self::$countries["JR"]),
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
                'Caption'  => ' State/Province (required for US and Canada)',
                'Required' => false,
                'Options'  => self::$states,
            ],
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Postal Code (cannot contain spaces)',
                'Required' => true,
            ],
            'PhoneCountryCodeNumeric' =>
            [
                'Type'     => 'string',
                'Caption'  => '1-3 - number Phone Country Code',
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
            'Email' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Email ',
                'Required' => true,
            ],
            'PreferredLanguage' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Language Preference',
                'Required' => true,
                'Options'  => self::$preferredLanguages,
            ],
            'BirthMonth' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Date Of Birth',
                'Required' => true,
            ],
            'BirthDay' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Date Of Birth',
                'Required' => true,
            ],
            'BirthYear' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Date Of Birth',
                'Required' => true,
            ],
            'Gender' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => self::$genders,
            ],
            'Pin' =>
            [
                'Type'     => 'string',
                'Caption'  => 'PIN (must be 6 numeric charactors)',
                'Required' => true,
            ],
            'Password' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Web Password (must be between 8 and 16 digits in length and contain characters from at least three of the following character classes: uppercase/lowercase English characters (A-Z, a-z), numbers (0-9), and symbols)',
                'Required' => true,
            ],
        ];
    }

    protected function processForm($methodName)
    {
        $prefix = $this->region === 'JR' ? 'JR' : '';
        $status = $methodName === 'Post' ? $this->http->PostForm() : $this->http->ParseForm("Enroll{$prefix}ActionForm_en");

        if (!$status) {
            $this->http->Log('Failed to ' . strtolower($methodName) . ' create account form');

            return false;
        }

        return true;
    }

    protected function processAddrTypeFields($type)
    {
        $addrTypeFieds = [
            'Country'         => 'Country',
            'AddressLine1'    => 'Address1',
            'City'            => 'CityName',
            'StateOrProvince' => 'StateCode1',
            'PostalCode'      => 'PostalCode1',
            'Phone'           => "Tel1",
        ];

        $prefixFieds = $type === 'B' ? 'business' : 'home';
        $result = self::$inputFieldsMap;

        foreach ($addrTypeFieds as $key => $row) {
            $result[$key] = $prefixFieds . $row;
        }

        return $result;
    }
}
