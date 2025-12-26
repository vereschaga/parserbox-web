<?php

namespace AwardWallet\Engine\goldcrown\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountCheckerGoldcrown
{
    use ProxyList;
    use \SeleniumCheckerHelper;
    public static $countries = [
        'US' => 'United States',
        'CA' => 'Canada',
        'AF' => 'Afghanistan',
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
        'BA' => 'Bosnia-Herzegovina',
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
        'HR' => 'Croatia',
        'CU' => 'Cuba',
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
        'GW' => 'Guinea Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard and McDonald Islands',
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
        'KP' => 'Korea (North)',
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
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PS' => 'Palestine',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn Island',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'SH' => 'Saint Helena',
        'LC' => 'Saint Lucia',
        'PM' => 'Saint Pierre and Miquelon',
        'VC' => 'Saint Vincent and Grenadines',
        'SM' => 'San Marino',
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
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen Islands',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TJ' => 'Tadjikistan',
        'TW' => 'Taiwan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
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
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City State',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'VG' => 'Virgin Islands (British)',
        'WF' => 'Wallis and Futuna Islands',
        'EH' => 'Western Sahara',
        'WS' => 'Western Samoa',
        'YE' => 'Yemen',
        'CD' => 'Zaire',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'AZ' => 'Azerbaidjan',
        'GB' => 'Great Britain',
        'CI' => 'Ivory Coast (Cote D\'Ivoire)',
        'KR' => 'Korea (South)',
        'PF' => 'Polynesia (French)',
        'RE' => 'Reunion (French)',
        'GS' => 'S. Georgia and S. Sandwich Isls.',
        'KN' => 'Saint Kitts and Nevis Anguilla',
        'ST' => 'Saint Tome (Sao Tome) and Principe',
        'UM' => 'USA Minor Outlying Islands',
        'VI' => 'Virgin Islands (USA)',
    ];
    public static $states = [
        'US_AL' => 'Alabama',
        'US_AK' => 'Alaska',
        'US_AZ' => 'Arizona',
        'US_AR' => 'Arkansas',
        'US_AA' => 'Armed Forces America (AA)',
        'US_AX' => 'Armed Forces Europe (AX)',
        'US_AP' => 'Armed Forces Pacific (AP)',
        'US_CA' => 'California',
        'US_CO' => 'Colorado',
        'US_CT' => 'Connecticut',
        'US_DE' => 'Delaware',
        'US_DC' => 'District of Columbia',
        'US_FL' => 'Florida',
        'US_GA' => 'Georgia',
        'US_HI' => 'Hawaii',
        'US_ID' => 'Idaho',
        'US_IL' => 'Illinois',
        'US_IN' => 'Indiana',
        'US_IA' => 'Iowa',
        'US_KS' => 'Kansas',
        'US_KY' => 'Kentucky',
        'US_LA' => 'Louisiana',
        'US_ME' => 'Maine',
        'US_MD' => 'Maryland',
        'US_MA' => 'Massachusetts',
        'US_MI' => 'Michigan',
        'US_MN' => 'Minnesota',
        'US_MS' => 'Mississippi',
        'US_MO' => 'Missouri',
        'US_MT' => 'Montana',
        'US_NE' => 'Nebraska',
        'US_NV' => 'Nevada',
        'US_NH' => 'New Hampshire',
        'US_NJ' => 'New Jersey',
        'US_NM' => 'New Mexico',
        'US_NY' => 'New York',
        'US_NC' => 'North Carolina',
        'US_ND' => 'North Dakota',
        'US_OH' => 'Ohio',
        'US_OK' => 'Oklahoma',
        'US_OR' => 'Oregon',
        'US_PA' => 'Pennsylvania',
        'US_RI' => 'Rhode Island',
        'US_SC' => 'South Carolina',
        'US_SD' => 'South Dakota',
        'US_TN' => 'Tennessee',
        'US_TX' => 'Texas',
        'US_UT' => 'Utah',
        'US_VT' => 'Vermont',
        'US_VA' => 'Virginia',
        'US_WA' => 'Washington',
        'US_WV' => 'West Virginia',
        'US_WI' => 'Wisconsin',
        'US_WY' => 'Wyoming',
    ];
    public static $addressTypes = [
        'H' => 'Home',
        'B' => 'Business',
        'O' => 'Other',
    ];
    public static $phoneTypes = [
        'H' => 'Home',
        'B' => 'Business',
        'M' => 'Mobile',
    ];
    public static $primaryReasonsForTravel = [
        'Leisure'  => 'Leisure',
        'Business' => 'Business',
        'Both'     => 'Both',
    ];
    public static $inputFieldsMap = [
        'Email' =>
            [
                0 => 'emailAddress',
                1 => 'emailAddress2',
            ],
        'Password' =>
            [
                0 => 'password',
                1 => 'password2',
            ],
        'Country'                     => 'nativeCountry',
        'Title'                       => 'prefix',
        'FirstName'                   => 'firstName',
        'LastName'                    => 'lastName',
        'AddressLine1'                => 'address1',
        'AddressLine2'                => 'address2',
        'City'                        => 'city',
        'StateOrProvince'             => 'state',
        'PostalCode'                  => 'postalCode',
        'AddressType'                 => 'addressType',
        'JobTitle'                    => 'businessTitle',
        'Company'                     => 'companyName',
        'PhoneType'                   => 'telephoneType',
        'SendMeOffersAndUpdatesBySMS' => 'mobileTextOption',
        'PhoneCountryCodeNumeric'     => false,
        'PhoneAreaCode'               => false,
        'PhoneLocalNumber'            => false,
        'EarningPreference'           => 'pointsMiles',
        //		'PartnerRewardsID' => 'flyerNumber',
        //		'Airline' => 'airlines',
        'DontReceivePromotionsAndMarketingMaterials'           => 'commPrefCheck1',
        'DontReceiveThirdPartyPromotionsAndMarketingMaterials' => 'commPrefCheck2',
        'PreferredLanguage'                                    => 'languageCode',
        'PrimaryReasonForTravel'                               => 'travelType',
    ];
    public $timeout = 20;

    // This code doesn't work fully because of some JS generated values or something else. Site shows registration
    // success message, but it is not possible to login with sent credentials. So Selenium registrator was used to get
    // round this issue.
    //	public function ___registerCurl(array $fields) {
    //		$this->ArchiveLogs = true;
//
    //		$this->http->GetURL('https://book.bestwestern.com/bestwestern/createAccountAction.do');
//
    //		$status = $this->http->ParseForm('profileForm');
    //		if (!$status) {
    //			$this->http->Log('Failed to parse create account form');
    //			return false;
    //		}
//
    ////		$this->http->Log('Registration fields:');
    ////		$this->http->Log(var_export($fields, true));
//
    //		$flags = [
    //			'SendMeOffersAndUpdatesBySMS',
    //			'DontReceivePromotionsAndMarketingMaterials',
    //			'DontReceiveThirdPartyPromotionsAndMarketingMaterials'
    //		];
    //		foreach ($flags as $key) {
    //			$awValue = $fields[$key];
    //			unset($fields[$key]);
    //			$provKey = self::$inputFieldsMap[$key];
    //			if ($awValue == '1')
    //				$this->http->SetInputValue($provKey, 'on');
    //			elseif ($awValue == '0')
    //				unset($this->http->Form[$provKey]);
    //			else
    //				throw new \UserInputError("Invalid value, $key field should be '1' or '0'");
    //		}
//
    //		// TODO: Move this loop to some generic method
    //		foreach (self::$inputFieldsMap as $awKey => $provKeys) {
    //			if (!isset($fields[$awKey]))
    //				continue;
    //			if (!is_array($provKeys)) $provKeys = [$provKeys];
    //			foreach ($provKeys as $provKey)
    //				$this->http->SetInputValue($provKey, $fields[$awKey]);
    //		}
//
    //		unset($this->http->Form['0']);
    //		$this->http->setDefaultHeader("Upgrade-Insecure-Requests", "1");
    //		$status = $this->http->PostForm();
    //		if (!$status) {
    //			$this->http->Log('Failed to post create account form');
    //			return false;
    //		}
//
    //		if ($s1 = $this->http->FindPreg('#Thank\s+you\s+for\s+Registering#i')
    //				and $s2 = $this->http->FindPreg('#Your\s+Profile\s+is\s+Complete#i')) {
    //			$this->ErrorMessage = $s1.'. '.$s2;
    //			return true;
    //		}
    //		return false;
    //	}
    protected $fields;
    private $loginUrl = 'https://book.bestwestern.com/bestwestern/createAccountAction.do?profileType=GCCI';

    public function InitBrowser()
    {
        $this->UseSelenium();
        $this->useChromium();
        $this->http->driver->showImages = false;
        $this->log('Setting proxy for Selenium secondary checker');

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->http->SetProxy('localhost:8000');
        } else { // $this->http->setExternalProxy();
            $this->setProxyBrightData();
        }

        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;
    }

    public function registerAccount(array $fields)
    {
        $fields['PreferredLanguage'] = 'ENGLISH'; // See comment in getRegisterFields
        $fields['EarningPreference'] = 'P'; // See comment in getRegisterFields
        $this->fields = $fields;

        try {
            $this->registerInternal();
            //			$this->http->cleanup();
            return true;
        } catch (\CheckException $e) {
            $this->saveResponse();
            //			$this->http->cleanup();
            throw $e;
        } catch (\Exception $e) {
            $this->log($e->getMessage(), LOG_LEVEL_ERROR);
            $this->saveResponse();
            //			$this->http->cleanup();
            return false;
        }
    }

    public static function countriesWithRequiredTitle()
    {
        $requiredTitlesJson = file_get_contents(__DIR__ . '/requiredTitles.json');

        if ($requiredTitlesJson === false) {
            throw new \EngineError('Failed to open requiredTitles.json');
        }
        $requiredTitles = json_decode($requiredTitlesJson, true);

        if ($requiredTitles === null) {
            throw new \EngineError('Failed to json-decode required titles list');
        }

        return $requiredTitles;
    }

    public static function titlesByCountry()
    {
        $titlesJson = file_get_contents(__DIR__ . '/titles.json');

        if ($titlesJson === false) {
            throw new \EngineError('Failed to open titles.json');
        }
        $titles = json_decode($titlesJson, true);

        if ($titles === null) {
            throw new \EngineError('Failed to json-decode titles list');
        }

        return $titles;
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
                    'Caption'  => 'Password (8-15 Characters, 1 letter, 1 number, no spaces or symbols)',
                    'Required' => true,
                ],
            'Country' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Country',
                    'Required' => true,
                    'Options'  => self::$countries,
                ],
            'Title' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Title (required for some countries)',
                    'Required' => false,
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
                    'Caption'  => 'State or Province (required for US)',
                    'Required' => false,
                    'Options'  => self::$states,
                ],
            'PostalCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Postal Code',
                    'Required' => true,
                ],
            'AddressType' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Type',
                    'Required' => true,
                    'Options'  => self::$addressTypes,
                ],
            'JobTitle' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Business Title',
                    'Required' => false,
                ],
            'Company' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Company Name',
                    'Required' => false,
                ],
            'PhoneType' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone Type',
                    'Required' => true,
                    'Options'  => self::$phoneTypes,
                ],
            'SendMeOffersAndUpdatesBySMS' =>
                [
                    'Type'     => 'boolean',
                    'Caption'  => 'Send me offers and updates by sms.',
                    'Required' => false,
                ],
            'PhoneCountryCodeNumeric' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone Country Code (numeric)',
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
                    'Caption'  => 'Phone Local Number',
                    'Required' => true,
                ],
            // Giift wants only 'P' => 'Points'
            //			'EarningPreference' =>
            //				array (
            //					'Type' => 'string',
            //					'Caption' => 'Earning Preference',
            //					'Required' => true,
            //					'Options' => self::$earningPreferenceTypes,
            //				),
            //			'PartnerRewardsID' =>
            //				array (
            //					'Type' => 'string',
            //					'Caption' => 'Partner Rewards ID',
            //					'Required' => false,
            //				),
            //			'Airline' =>
            //				array (
            //					'Type' => 'string',
            //					'Caption' => 'Airline',
            //					'Required' => false,
            //				),
            'DontReceivePromotionsAndMarketingMaterials' =>
                [
                    'Type'     => 'boolean',
                    'Caption'  => 'I do not want to receive promotions and marketing materials from Best Western',
                    'Required' => true,
                ],
            'DontReceiveThirdPartyPromotionsAndMarketingMaterials' =>
                [
                    'Type'     => 'boolean',
                    'Caption'  => 'I do not want to receive promotions and marketing materials from Best Western\'s third-party business partners',
                    'Required' => true,
                ],
            // Depends on country selection, too hard to implement it, set English for all
            //			'PreferredLanguage' =>
            //				array (
            //					'Type' => 'string',
            //					'Caption' => 'Preferred Language',
            //					'Required' => true,
            //					'Options' => self::$preferredLanguages,
            //				),
            'PrimaryReasonForTravel' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Primary Reason For Travel',
                    'Required' => true,
                    'Options'  => self::$primaryReasonsForTravel,
                ],
        ];
    }

    private function log($msg, $loglevel = null)
    {
        $this->http->Log($msg, $loglevel);
    }

    //	static $earningPreferenceTypes = [
    //		'P' => 'Points',
    //		'M' => 'Airline/Partner Rewards',
    //	];

    // See comment in getRegisterFields
    //	static $preferredLanguages = [
    //		'ENGLISH' => 'ENGLISH',
    //		'JAPANESE' => 'JAPANESE',
    //		'KOREAN' => 'KOREAN',
    //		'SIMPLIFIED CHINESE' => 'SIMPLIFIED CHINESE',
    //		'THAI' => 'THAI',
    //		'TRADITIONAL CHINESE' => 'TRADITIONAL CHINESE',
    //	];

    private function registerInternal()
    {
        $this->http->GetURL($this->loginUrl);

        if ($elem = $this->waitForElement(\WebDriverBy::xpath('//h1[contains(., "Access Denied")]'), $this->timeout)) {
            throw new \EngineError($elem->getText());
        }
        $this->checkFields();
        $this->fillRadiobuttons();
        $this->fillTextInputs();
        $this->fillSelects();
        $this->fillCheckboxes();
        $this->submit();
    }

    private function checkFields()
    {
        if (!isset(self::$countries[$this->fields['Country']])) {
            throw new \UserInputError('Invalid country code ' . $this->fields['Country']);
        }

        if ($this->fields['AddressType'] !== 'B') {
            unset($this->fields['Company']);
            unset($this->fields['JobTitle']);
        }

        $requiredTitles = self::countriesWithRequiredTitle();
        $titles = self::titlesByCountry();

        if (in_array($this->fields['Country'], $requiredTitles) and !$this->fields['Title']) {
            $e = 'Title field is required for country ' . self::$countries[$this->fields['Country']];
            $e .= '. Available variants: "' . implode('", "', $titles[$this->fields['Country']]) . '"';

            throw new \UserInputError($e);
        } elseif (in_array($this->fields['Country'], $requiredTitles)
                    and isset($titles[$this->fields['Country']])
                    and !in_array($this->fields['Title'], $titles[$this->fields['Country']])) {
            $e = 'Invalid title value "' . $this->fields['Title'] . '" for country ' . self::$countries[$this->fields['Country']];
            $e .= '. Available variants: "' . implode('", "', $titles[$this->fields['Country']]) . '"';

            throw new \UserInputError($e);
        }

        // Provider uses wrong country codes for:
        // - East Timor (TP instead of standard TL)
        // - Serbia and Montenegro (CS instead of standard RS)
        // - Zaire (ZR instead of standard CD)
        // Map from our standard ISO code to wrong code used by provider
        $wrongCountryCodesFixingMap = [
            'RS' => 'CS',
            'CD' => 'ZR',
            'TL' => 'TP',
        ];

        if (isset($wrongCountryCodesFixingMap[$this->fields['Country']])) {
            $origCountryCode = $this->fields['Country'];
            $this->fields['Country'] = $wrongCountryCodesFixingMap[$this->fields['Country']];
            $this->logger->debug('Mapped standard country code "' . $origCountryCode . '" to provider code "' . $this->fields['Country'] . '"');
        }
    }

    private function fillRadiobuttons()
    {
        $radiobuttonInputFields = [
            'AddressType',
            'EarningPreference',
            'PrimaryReasonForTravel',
        ];

        foreach ($radiobuttonInputFields as $awKey) {
            if (!isset($this->fields[$awKey])) {
                continue;
            }
            $key = self::$inputFieldsMap[$awKey];
            $value = $this->fields[$awKey];
            $xpath = '//form[@name="profileForm"]//input[@name="' . $key . '" and @value="' . $value . '"]';

            if ($elem = $this->waitForElement(\WebDriverBy::xpath($xpath), $this->timeout)) {
                $elem->click();
            } else {
                throw new \EngineError("Could not find input field for $awKey value");
            }
        }
        //		$this->driver->executeScript('document.getElementsByName("addressSelected")[0].setAttribute("value","B")');
//		if ($this->waitForElement(WebDriverBy::xpath('//form[@name="profileForm"]//input[@name="addressSelected"]', $this->timeout)))
//			$this->driver->executeScript('document.getElementsByName("addressSelected")[0].setAttribute("value","B")');
//		else
//			throw new \EngineError("Could not find input field for AddressType value");
    }

    private function fillTextInputs()
    {
        $textInputFields = [
            'Email',
            'Password',
            'FirstName',
            'LastName',
            'AddressLine1',
            'AddressLine2',
            'City',
            'PostalCode',
            'JobTitle',
            'Company',
            //			'PartnerRewardsID',
            //			'Airline',
        ];

        foreach ($textInputFields as $awKey) {
            if (!isset($this->fields[$awKey]) or $this->fields[$awKey] === '') {
                continue;
            }
            $keys = self::$inputFieldsMap[$awKey];

            if (!is_array($keys)) {
                $keys = [$keys];
            }

            foreach ($keys as $key) {
                $xpath = '//form[@name="profileForm"]//input[@name="' . $key . '"]';

                if ($elem = $this->waitForElement(\WebDriverBy::xpath($xpath), $this->timeout)) {
                    $elem->sendKeys($this->fields[$awKey]);
                } else {
                    throw new \EngineError("Could not find input field for $awKey value");
                }
            }
        }

        $phone = $this->fields['PhoneCountryCodeNumeric'] . $this->fields['PhoneAreaCode'] . $this->fields['PhoneLocalNumber'];
        $xpath = '//form[@name="profileForm"]//input[@name="telephone"]';

        if ($elem = $this->waitForElement(\WebDriverBy::xpath($xpath), $this->timeout)) {
            $elem->sendKeys($phone);
        } else {
            throw new \EngineError("Could not find input field for phone number");
        }
    }

    private function fillSelects()
    {
        switch ($this->fields['PhoneType']) {
            // Convert AW phone type codes to provider codes
            case 'H':
                $phoneType = 'Home';

                break;

            case 'B':
                $phoneType = 'Work';

                break;

            case 'M':
                $phoneType = 'Mobile';

                break;

            default:
                throw new \EngineError("Invalid phone type code {$this->fields['PhoneType']}");
        }
        $key = self::$inputFieldsMap['PhoneType'];
        $value = $phoneType;
        $xpath = '//form[@name="profileForm"]//select[@name="' . $key . '"]';
        $select = new \WebDriverSelect($this->driver->findElement(\WebDriverBy::xpath($xpath)));
        $select->selectByValue($value);

        $selectInputFields = [
            'Country',
            'Title',
            'StateOrProvince',
            'PreferredLanguage',
        ];

        foreach ($selectInputFields as $awKey) {
            if (!isset($this->fields[$awKey])) {
                continue;
            }
            $key = self::$inputFieldsMap[@$awKey];
            $value = $this->fields[$awKey];
            $xpath = '//form[@name="profileForm"]//select[@name="' . $key . '"]';
            $select = new \WebDriverSelect($this->driver->findElement(\WebDriverBy::xpath($xpath)));
            $select->selectByValue($value);
        }
    }

    private function fillCheckboxes()
    {
        $checkboxInputFields = [
            'SendMeOffersAndUpdatesBySMS',
            'DontReceivePromotionsAndMarketingMaterials',
            'DontReceiveThirdPartyPromotionsAndMarketingMaterials',
        ];

        foreach ($checkboxInputFields as $awKey) {
            if (!isset($this->fields[$awKey])) {
                continue;
            }

            if ($awKey == 'SendMeOffersAndUpdatesBySMS' and $this->fields['PhoneType'] != 'M') {
                continue;
            }
            $key = self::$inputFieldsMap[$awKey];
            $value = $this->fields[$awKey];
            $xpath = '//form[@name="profileForm"]//input[@name="' . $key . '"]';

            if ($elem = $this->waitForElement(\WebDriverBy::xpath($xpath), $this->timeout)) {
                $alreadyChecked = $elem->getAttribute('checked');

                if ($alreadyChecked and !$value or !$alreadyChecked and $value) {
                    $elem->click();
                }
            } else {
                throw new \EngineError("Could not find input field for $awKey value");
            }
        }
    }

    private function submit()
    {
        $createAccountButton = $this->waitForElement(\WebDriverBy::id('createAccount'));

        if (!$createAccountButton) {
            throw new \EngineError('Failed to find "create account" button');
        }
        $createAccountButton->click();
        $successXpath1 = '//strong[contains(., "Your Best Western")]';
        $successXpath2 = '//strong[contains(., "Your Best Western")]/following-sibling::span[1]';
        $errorsXpath = '//span[@class="error" and string-length(normalize-space(.)) > 1]';

        if ($elem = $this->waitForElement(\WebDriverBy::xpath(implode('|', [$successXpath1, $errorsXpath])))) {
            if ($elem2 = $this->waitForElement(\WebDriverBy::xpath($successXpath2), 1)) {
                // Your Best Western Rewards account Number is: \d+
                $successMsg = $elem->getText() . ' ' . $elem2->getText();
                $this->http->Log($successMsg);
                $this->ErrorMessage = $successMsg;
            } else {
                $errorElements = $this->driver->findElements(\WebDriverBy::xpath($errorsXpath));
                $errors = [];

                foreach ($errorElements as $ee) {
                    $errors[] = $ee->getText();
                }
                $error = implode(' ', $errors);

                throw new \UserInputError($error);
            }
        } else {
            throw new \EngineError('Unexpected response on account registration submit');
        }
    }
}
