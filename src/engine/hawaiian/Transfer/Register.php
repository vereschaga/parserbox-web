<?php

// case #10623

namespace AwardWallet\Engine\hawaiian\Transfer;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \AwardWallet\Engine\ProxyList;

    public static $fieldMap = [
        'Username' => 'AccountDetail.UserName',
        'Email'    => 'AccountDetail.EmailAddress',
        'Password' => [
            'Password',
            'Password2',
        ],

        'FirstName'  => 'MemberPersonalInfo.FirstName',
        'LastName'   => 'MemberPersonalInfo.LastName',
        'Gender'     => 'MemberPersonalInfo_Gender',
        'BirthMonth' => 'dobMonth',
        'BirthDay'   => 'dobDay',

        'BirthYear' => 'dobYear',

        'Country'         => 'MemberAddress.CountryData',
        'PostalCode'      => 'MemberAddress.ZipCode',
        'AddressLine1'    => 'MemberAddress.Address1',
        'City'            => 'MemberAddress.CityData',
        'StateOrProvince' => 'MemberAddress.StateData',

        'PhoneType'                  => 'PhoneDetailList.PhoneDetails[0].Type',
        'PhoneCountryCodeAlphabetic' => 'PhoneDetails0',
        'Phone'                      => 'PhoneDetailList.PhoneDetails[0].Number',

        'SecurityQuestionType1'   => 'SecurityQuestions.SecurityQuestionAnswers[0].QuestionID',
        'SecurityQuestionAnswer1' => 'SecurityQuestions.SecurityQuestionAnswers[0].Answer',
        'SecurityQuestionType2'   => 'SecurityQuestions.SecurityQuestionAnswers[1].QuestionID',
        'SecurityQuestionAnswer2' => 'SecurityQuestions.SecurityQuestionAnswers[1].Answer',
        'SecurityQuestionType3'   => 'SecurityQuestions.SecurityQuestionAnswers[2].QuestionID',
        'SecurityQuestionAnswer3' => 'SecurityQuestions.SecurityQuestionAnswers[2].Answer',
    ];
    public static $phoneMap = [
        'H' => '1',
        'B' => '2',
        'M' => '3',
    ];
    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];
    public static $phoneTypes = [
        'H' => 'Home',
        'M' => 'Mobile',
        'B' => 'Work',
    ];
    public static $securityQuestions = [
        '1'  => 'What is my favorite color?',
        '2'  => 'Who did I take to my high school prom?',
        '3'  => 'What is the name of my favorite Hawaiian island?',
        '4'  => 'What is my favorite flower?',
        '5'  => 'What is my favorite sport team?',
        '6'  => 'Who is my favorite athlete?',
        '7'  => 'What is my favorite food?',
        '8'  => 'What is my favorite past time?',
        '9'  => 'What was my childhood pet?',
        '10' => 'Who was your best friend from childhood?',
    ];

    public static $countries = [
        'US' => 'United States',
        'AU' => 'Australia',
        'CN' => 'China',
        'JP' => 'Japan',
        'NZ' => 'New Zealand',
        'KR' => 'South Korea',
        'TW' => 'Taiwan',
        'AI' => 'Anguilla',
        'AG' => 'Antigua and Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
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
        'CX' => 'Christmas Island',
        'CC' => 'Cocos Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CD' => 'Congo D.R.',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'CI' => 'Cote d\'Ivoire',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CW' => 'Curacao',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'TL' => 'East Timor',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'PF' => 'French Polynesia',
        'TF' => 'French Southern Territories',
        'GA' => 'Gabon',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GR' => 'Greece',
        'GL' => 'Greenland',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GG' => 'Guernsey',
        'GN' => 'Guinea',
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
        'IM' => 'Isle of Man',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JE' => 'Jersey',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Laos',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'AX' => 'Aland',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macao',
        'MK' => 'Macedonia',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'UM' => 'Minor Outlying Islands',
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
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'NU' => 'Niue',
        'NF' => 'Norfolk Island',
        'KP' => 'North Korea',
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn Islands',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'SH' => 'Saint Helena',
        'MF' => 'Saint Martin',
        'PM' => 'Saint Pierre and Miquelon',
        'BL' => 'Saint-Barthelemy',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SX' => 'Sint Maarten',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'GS' => 'South Georgia and Sandwich Islands',
        'SS' => 'South Sudan',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TC' => 'Turks and Caicos Islands',
        'TV' => 'Tuvalu',
        'VI' => 'U.S. Virgin Islands',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'WF' => 'Wallis and Futuna',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'LY' => 'Libya',
    ];
    public static $phoneCountryCodesAlphabetic = [
        'USA' => 'USA',
        'AUS' => 'AUS',
        'CHN' => 'CHN',
        'JPN' => 'JPN',
        'KOR' => 'KOR',
        'NZL' => 'NZL',
        'TWN' => 'TWN',
        'ABW' => 'ABW',
        'AFG' => 'AFG',
        'AGO' => 'AGO',
        'AIA' => 'AIA',
        'ALA' => 'ALA',
        'ALB' => 'ALB',
        'AND' => 'AND',
        'ARE' => 'ARE',
        'ARG' => 'ARG',
        'ARM' => 'ARM',
        'ASM' => 'ASM',
        'ATF' => 'ATF',
        'ATG' => 'ATG',
        'AUT' => 'AUT',
        'AZE' => 'AZE',
        'BDI' => 'BDI',
        'BEL' => 'BEL',
        'BEN' => 'BEN',
        'BES' => 'BES',
        'BFA' => 'BFA',
        'BGD' => 'BGD',
        'BGR' => 'BGR',
        'BHR' => 'BHR',
        'BHS' => 'BHS',
        'BIH' => 'BIH',
        'BLM' => 'BLM',
        'BLR' => 'BLR',
        'BLZ' => 'BLZ',
        'BMU' => 'BMU',
        'BOL' => 'BOL',
        'BRA' => 'BRA',
        'BRB' => 'BRB',
        'BRN' => 'BRN',
        'BTN' => 'BTN',
        'BVT' => 'BVT',
        'BWA' => 'BWA',
        'CAF' => 'CAF',
        'CAN' => 'CAN',
        'CCK' => 'CCK',
        'CHE' => 'CHE',
        'CHL' => 'CHL',
        'CIV' => 'CIV',
        'CMR' => 'CMR',
        'COD' => 'COD',
        'COK' => 'COK',
        'COL' => 'COL',
        'COM' => 'COM',
        'CPV' => 'CPV',
        'CRI' => 'CRI',
        'CUB' => 'CUB',
        'CUW' => 'CUW',
        'CXR' => 'CXR',
        'CYM' => 'CYM',
        'CYP' => 'CYP',
        'CZE' => 'CZE',
        'DEU' => 'DEU',
        'DMA' => 'DMA',
        'DNK' => 'DNK',
        'DOM' => 'DOM',
        'DZA' => 'DZA',
        'ECU' => 'ECU',
        'EGY' => 'EGY',
        'ERI' => 'ERI',
        'ESP' => 'ESP',
        'EST' => 'EST',
        'ETH' => 'ETH',
        'FIN' => 'FIN',
        'FJI' => 'FJI',
        'FLK' => 'FLK',
        'FRA' => 'FRA',
        'FRO' => 'FRO',
        'FSM' => 'FSM',
        'GAB' => 'GAB',
        'GBR' => 'GBR',
        'GEO' => 'GEO',
        'GGY' => 'GGY',
        'GHA' => 'GHA',
        'GIB' => 'GIB',
        'GIN' => 'GIN',
        'GRC' => 'GRC',
        'GRL' => 'GRL',
        'GTM' => 'GTM',
        'GUM' => 'GUM',
        'GUY' => 'GUY',
        'HKG' => 'HKG',
        'HMD' => 'HMD',
        'HND' => 'HND',
        'HRV' => 'HRV',
        'HTI' => 'HTI',
        'HUN' => 'HUN',
        'IDN' => 'IDN',
        'IMN' => 'IMN',
        'IND' => 'IND',
        'IOT' => 'IOT',
        'IRL' => 'IRL',
        'IRN' => 'IRN',
        'IRQ' => 'IRQ',
        'ISL' => 'ISL',
        'ISR' => 'ISR',
        'ITA' => 'ITA',
        'JAM' => 'JAM',
        'JEY' => 'JEY',
        'JOR' => 'JOR',
        'KAZ' => 'KAZ',
        'KEN' => 'KEN',
        'KGZ' => 'KGZ',
        'KHM' => 'KHM',
        'KIR' => 'KIR',
        'LAO' => 'LAO',
        'LBN' => 'LBN',
        'LBR' => 'LBR',
        'LBY' => 'LBY',
        'LIE' => 'LIE',
        'LKA' => 'LKA',
        'LSO' => 'LSO',
        'LTU' => 'LTU',
        'LUX' => 'LUX',
        'LVA' => 'LVA',
        'MAC' => 'MAC',
        'MAF' => 'MAF',
        'MAR' => 'MAR',
        'MCO' => 'MCO',
        'MDA' => 'MDA',
        'MDG' => 'MDG',
        'MDV' => 'MDV',
        'MEX' => 'MEX',
        'MHL' => 'MHL',
        'MKD' => 'MKD',
        'MLI' => 'MLI',
        'MLT' => 'MLT',
        'MMR' => 'MMR',
        'MNE' => 'MNE',
        'MNG' => 'MNG',
        'MNP' => 'MNP',
        'MOZ' => 'MOZ',
        'MRT' => 'MRT',
        'MSR' => 'MSR',
        'MUS' => 'MUS',
        'MWI' => 'MWI',
        'MYS' => 'MYS',
        'NAM' => 'NAM',
        'NCL' => 'NCL',
        'NER' => 'NER',
        'NFK' => 'NFK',
        'NGA' => 'NGA',
        'NIC' => 'NIC',
        'NIU' => 'NIU',
        'NLD' => 'NLD',
        'NOR' => 'NOR',
        'NPL' => 'NPL',
        'NRU' => 'NRU',
        'PAK' => 'PAK',
        'PAN' => 'PAN',
        'PCN' => 'PCN',
        'PER' => 'PER',
        'PHL' => 'PHL',
        'PLW' => 'PLW',
        'PNG' => 'PNG',
        'POL' => 'POL',
        'PRI' => 'PRI',
        'PRK' => 'PRK',
        'PRT' => 'PRT',
        'PRY' => 'PRY',
        'PYF' => 'PYF',
        'QAT' => 'QAT',
        'ROU' => 'ROU',
        'RUS' => 'RUS',
        'RWA' => 'RWA',
        'SAU' => 'SAU',
        'SDN' => 'SDN',
        'SEN' => 'SEN',
        'SGP' => 'SGP',
        'SGS' => 'SGS',
        'SHN' => 'SHN',
        'SJM' => 'SJM',
        'SLB' => 'SLB',
        'SLE' => 'SLE',
        'SLV' => 'SLV',
        'SMR' => 'SMR',
        'SOM' => 'SOM',
        'SPM' => 'SPM',
        'SRB' => 'SRB',
        'SSD' => 'SSD',
        'SUR' => 'SUR',
        'SVK' => 'SVK',
        'SVN' => 'SVN',
        'SWE' => 'SWE',
        'SWZ' => 'SWZ',
        'SXM' => 'SXM',
        'SYR' => 'SYR',
        'TCA' => 'TCA',
        'TCD' => 'TCD',
        'TGO' => 'TGO',
        'THA' => 'THA',
        'TKL' => 'TKL',
        'TLS' => 'TLS',
        'TON' => 'TON',
        'TUN' => 'TUN',
        'TUR' => 'TUR',
        'TUV' => 'TUV',
        'TZA' => 'TZA',
        'UGA' => 'UGA',
        'UKR' => 'UKR',
        'UMI' => 'UMI',
        'URY' => 'URY',
        'UZB' => 'UZB',
        'VAT' => 'VAT',
        'VEN' => 'VEN',
        'VGB' => 'VGB',
        'VIR' => 'VIR',
        'VNM' => 'VNM',
        'VUT' => 'VUT',
        'WLF' => 'WLF',
        'WSM' => 'WSM',
        'YEM' => 'YEM',
        'ZAF' => 'ZAF',
        'ZMB' => 'ZMB',
        'ZWE' => 'ZWE',
    ];
    private $timeout = 10;
    private $registerUrl = 'https://beta.hawaiianairlines.com/my-account/beta/join-hawaiianmiles';

    public static function rand()
    {
        $keys = array_keys(self::$securityQuestions);
        shuffle($keys);

        return $keys;
    }

    public function InitBrowser()
    {
        $this->UseSelenium();
        $this->useChromium();
        $this->http->saveScreenshots = true;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyPurchase());
        }
    }

    public function registerAccount(array $fields)
    {
        $this->http->log('[INFO] ' . __METHOD__);
        $this->http->log('[INFO] initial fields:');
        $this->http->log(json_encode($fields, JSON_PRETTY_PRINT));

        $this->fields = $fields;

        $this->checkData();

        try {
            $this->http->getUrl($this->registerUrl);
        } catch (\WebDriverCurlException $e) {
            $msg = 'Site timed out, please try again later';
            $this->http->log('[INFO] possibly proxy issue');

            throw new \ProviderError($msg);
        }
        $submit = $this->waitForElement(\WebDriverBy::xpath("//button[@type = 'submit']"), $this->timeout);

        if (!$submit) {
            throw new \EngineError("Couldn't find submit button");
        }

        $this->modifyFields();
        $this->http->log('[INFO] modified fields:');
        $this->http->log(print_r($this->fields, true));

        // TODO: Check security questions count and accordance

        $this->fillAddressData($fields);
        $this->fillSelectInputs($fields);
        $this->fillTextInputs($fields);
        $this->checkAcceptTerms();
        $submit->click();

        $this->checkStatus();

        return true;
    }

    public function getRegisterFields()
    {
        return [
            'Username' => [
                'Type'     => 'string',
                'Caption'  => 'Username (must be at least 6 characters long and include one letter and one number)',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password (must be 10-15 characters long and contain one letter and one number)',
                'Required' => true,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name',
                'Required' => true,
            ],
            'Gender' => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => self::$genders,
            ],
            'BirthMonth' => [
                'Type'     => 'integer',
                'Caption'  => 'Month of Birth Date',
                'Required' => true,
            ],
            'BirthDay' => [
                'Type'     => 'integer',
                'Caption'  => 'Day of Birth Date',
                'Required' => true,
            ],
            'BirthYear' => [
                'Type'    => 'integer',
                'Caption' => sprintf('Year of Birth Date, between %s and %s',
                                        date('Y') - 99, date('Y')),
                'Required' => true,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country name',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'PostalCode' => [
                'Type'     => 'string',
                'Caption'  => 'Zip Code (not required for Hong Kong)',
                'Required' => true,
            ],
            'AddressLine1' => [
                'Type'     => 'string',
                'Caption'  => 'Address',
                'Required' => true,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'City',
                'Required' => true,
            ],
            'StateOrProvince' => [
                'Type'     => 'string',
                'Caption'  => 'State',
                'Required' => false,
            ],
            'PhoneType' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Type',
                'Required' => true,
                'Options'  => self::$phoneTypes,
            ],
            'PhoneCountryCodeAlphabetic' => [
                'Type'     => 'string',
                'Caption'  => '3-letter Phone Country Code',
                'Required' => true,
                'Options'  => self::$phoneCountryCodesAlphabetic,
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
            'SecurityQuestionType1' => [
                'Type'     => 'integer',
                'Caption'  => 'Security Question 1',
                'Required' => true,
                'Options'  => self::$securityQuestions,
            ],
            'SecurityQuestionAnswer1' => [
                'Type'     => 'string',
                'Caption'  => 'Answer 1',
                'Required' => true,
            ],
            'SecurityQuestionType2' => [
                'Type'     => 'integer',
                'Caption'  => 'Security Question 2',
                'Required' => true,
                'Options'  => self::$securityQuestions,
            ],
            'SecurityQuestionAnswer2' => [
                'Type'     => 'string',
                'Caption'  => 'Answer 2',
                'Required' => true,
            ],
            'SecurityQuestionType3' => [
                'Type'     => 'integer',
                'Caption'  => 'Security Question 3',
                'Required' => true,
                'Options'  => self::$securityQuestions,
            ],
            'SecurityQuestionAnswer3' => [
                'Type'     => 'string',
                'Caption'  => 'Answer 3',
                'Required' => true,
            ],
        ];
    }

    protected function modifyFields()
    {
        $this->fields['Phone'] = $this->getPhone();
        $this->fields['PhoneType'] = arrayVal(self::$phoneMap, $this->fields['PhoneType']);

        foreach (['BirthDay', 'BirthMonth', 'BirthYear'] as $key) {
            $this->fields[$key] = sprintf('string:%s', ltrim($this->fields[$key], ' 0'));
        }

        $this->fields['PhoneCountryCodeAlphabetic'] = $this->convertPhoneCountryToNumber($this->fields['PhoneCountryCodeAlphabetic']);
    }

    // corresponding names or _ids_

    protected function waitElement(\WebDriverBy $by, $timeout = null, $visible = true)
    {
        return $this->waitForElement($by, $timeout ? $timeout : $this->timeout, $visible);
    }

    private function checkData()
    {
        $this->http->log('[INFO] ' . __METHOD__);
        $current_year = date('Y');

        if ($this->fields['BirthYear'] < $current_year - 99 || $this->fields['BirthYear'] > $current_year) {
            throw new \UserInputError($this->getRegisterFields()['BirthYear']['Caption']);
        }
    }

    private function getPhone()
    {
        return sprintf('%s%s',
            $this->fields['PhoneAreaCode'],
            $this->fields['PhoneLocalNumber']
        );
    }

    private function convertPhoneCountryToNumber($country)
    {
        $xpath = sprintf('//select[@id = "PhoneDetails0"]/option[contains(text(), "%s")]', $country);
        $number = $this->waitForElement(\WebDriverBy::xpath($xpath));

        if (!$number) {
            throw new \UserInputError('Invalid phone country code');
        }

        return $number->getAttribute('value');
    }

    private function fillAddressData()
    {
        $this->http->log('[INFO] ' . __METHOD__);

        try {
            // weird-weird, but order matters
            // and by text / by value logic matters
            $c = self::$countries[$this->fields['Country']];
            $this->logger->debug('Provider expects full length country name, converting country code "' . $this->fields['Country'] . '" => "' . $c . '"');
            $countryCode = $this->findValueByText($c);
            $this->setInputValue(self::$fieldMap['Country'], $countryCode, true);

            $this->setInputValue(self::$fieldMap['PostalCode'], $this->fields['PostalCode'], true);

            if ($this->fields['StateOrProvince']) {
                try {
                    $state = $this->findValueByText($this->fields['StateOrProvince'], 30);
                } catch (\UserInputError $e) {
                    $msg = sprintf("State %s doesn't correspond to zip code %s", $this->fields['StateOrProvince'], $this->fields['PostalCode']);

                    throw new \UserInputError($msg);
                }
                $this->setInputValue(self::$fieldMap['StateOrProvince'], $state, true);
            }
            // sometimes zip code selects the field for the state or city
            $city = ($this->fields['City']) ? $this->fields['City'] : null;
            $js = $this->driver->executeScript("
					var e = jQuery('select[id=\"MemberAddress.CityData\"] option:selected').text();
					if( e === '$city' ){
						return 'Value is filled in automatically';
					} else {
						return null;
					}
			");
            $this->http->Log('LOG - ' . $js, LOG_LEVEL_NORMAL);

            if ($this->fields['City'] && empty($js)) {
                $this->fillCity();
            } elseif ($this->fields['City'] && !empty($js)) {
                $this->http->Log($js, LOG_LEVEL_NORMAL);
            }

            $this->setInputValue(self::$fieldMap['AddressLine1'], $this->fields['AddressLine1'], true);
        } catch (\InvalidElementStateException $e) {
            $this->http->log($e->getMessage());
        }
    }

    private function findValueByText($text, $timeout = false)
    {
        $timeout = $timeout ?: $this->timeout;
        // maybe select's id / name should be here as well
        $xpath = sprintf('//select/option[normalize-space(text()) = "%s"]', $text);
        $elem = $this->waitElement(\WebDriverBy::xpath($xpath), $timeout, false);

        if (!$elem) {
            throw new \UserInputError("Could not find text $text");
        }

        return $elem->getAttribute('value');
    }

    private function setInputValue($key, $value, $js = false)
    {
        $xpath = sprintf('//*[@id = "%s" or @name = "%s"]', $key, $key);
        $elem = $this->waitElement(\WebDriverBy::xpath($xpath));

        if (!$elem) {
            if ($key === 'MemberAddress.StateData' || $key === 'MemberAddress.ZipCode') {
                return;
            }

            throw new \EngineError("Could not find field $key");
        }

        // for some reason direct jq by id fails on country
        if ($js) {
            $this->driver->executeScript("
				var elem = document.getElementById('$key');
				if (elem) {
					$(elem).val('$value').change();
				} else {
					$('*[name = \"$key\"]').val('$value').change();
				}
			");
        } else {
            $elem->sendKeys($value);
        }
    }

    private function fillCity()
    {
        $this->http->log('[INFO] ' . __METHOD__);

        // city select
        $citySelectId = 'MemberAddress.CityData';
        $value = '';

        try {
            try {
                $value = $this->findValueByText($this->fields['City']);
            } catch (\UserInputError $e) {
                $msg = sprintf("City %s doesn't correspond to zip code %s", $this->fields['City'], $this->fields['PostalCode']);

                throw new \UserInputError($msg);
            }

            if ($value !== '') {
                $this->setInputValue($citySelectId, $value, true);

                return;
            }
        } catch (\CheckException $e) {
            $this->http->log('[INFO] select failed');
        }

        // or city input
        $cityInputName = 'MemberAddress.CityInput';
        $cityInputXpath = sprintf('//*[@name = "%s"]', $cityInputName);
        $elem = $this->waitElement(\WebDriverBy::xpath($cityInputXpath), 10);

        if ($elem) {
            $this->setInputValue($cityInputName, $this->fields['City'], true);

            return;
        }
        $this->http->log('[INFO] text input failed');

        throw new \UserInputError('Cannot find neither select nor input for city');
    }

    private function fillSelectInputs()
    {
        $this->http->log('[INFO] ' . __METHOD__);
        $selectInputs = [
            'Gender',
            'BirthMonth',
            'BirthDay',
            'BirthYear',

            'PhoneType',
            'PhoneCountryCodeAlphabetic',

            'SecurityQuestionType1',
            'SecurityQuestionType2',
            'SecurityQuestionType3',
        ];
        $this->fillInputs($selectInputs, true);
    }

    private function fillInputs($data, $js = false)
    {
        foreach ($data as $awkey) {
            if (!isset($this->fields[$awkey]) || trim($this->fields[$awkey]) === '') {
                continue;
            }

            $keys = self::$fieldMap[$awkey];

            if (!is_array($keys)) {
                $keys = [$keys];
            }
            $value = $this->fields[$awkey];

            foreach ($keys as $key) {
                $this->setInputValue($key, $value, $js);
            }
        }
    }

    private function fillTextInputs()
    {
        $this->http->log('[INFO] ' . __METHOD__);
        $textInputs = [
            'Username',
            'Email',
            'Password',
            'FirstName',
            'LastName',
            'Phone',
            'SecurityQuestionAnswer1',
            'SecurityQuestionAnswer3',
            'SecurityQuestionAnswer2',
        ];
        $this->fillInputs($textInputs, true);
    }

    private function checkAcceptTerms()
    {
        $this->http->log('[INFO] ' . __METHOD__);
        $xpath = '//*[@name = "launchTerms"]/following::label[1]';
        $elem = $this->waitForElement(\WebDriverBy::xpath($xpath), $this->timeout, false);

        if (!$elem) {
            throw new \EngineError("Could not check accept");
        }
        $elem->click();
    }

    private function checkStatus()
    {
        $this->http->log('[INFO] ' . __METHOD__);
        $xpath = '//*[contains(text(), "REGISTRATION SUCCESSFUL")]/following::p[1]';
        $success = $this->waitForElement(\WebDriverBy::xpath($xpath), $this->timeout, false);
        $this->saveResponse();
        $this->http->log('[INFO] response saved');

        if ($success) {
            if (preg_match('/is (\d+)/isu', $success->getText(), $m)) {
                $accInfo = sprintf('Your HawaiianMiles number is %s.', $m[1]);
            } else {
                $accInfo = 'Please check your email or profile for account number.';
            }
            $msg = "Successfull registration. $accInfo";
            $this->http->log('>>> ' . $msg);
            $this->ErrorMessage = $msg;

            return true;
        }

        $xpathErrors1 = '//input/following-sibling::p[contains(@class, "error") and not(contains(@class, "ng-hide"))]';
        $xpathLabels1 = '//input/following-sibling::p[contains(@class, "error") and not(contains(@class, "ng-hide"))]
						/preceding::span[contains(@class, "eyebrow")][1]';
        $xpathErrors2 = '//input/following-sibling::em[1]';
        $xpathLabels2 = '//input/following-sibling::em[1]
						/preceding-sibling::span[1]';
        $xpathErrors3 = '//select/following-sibling::em[1]';
        $xpathLabels3 = '//select/following-sibling::em[1]
						/preceding-sibling::span[1]';
        $xpathErrorsStatus = sprintf('%s | %s | %s', $xpathErrors1, $xpathErrors2, $xpathErrors3);

        if ($this->waitElement(\WebDriverBy::xpath($xpathErrorsStatus))) {
            // that way, because I'm not sure about xpath keeping order
            $errors1 = $this->driver->findElements(\WebDriverBy::xpath($xpathErrors1));
            $labels1 = $this->driver->findElements(\WebDriverBy::xpath($xpathLabels1));
            $errors2 = $this->driver->findElements(\WebDriverBy::xpath($xpathErrors2));
            $labels2 = $this->driver->findElements(\WebDriverBy::xpath($xpathLabels2));
            $errors3 = $this->driver->findElements(\WebDriverBy::xpath($xpathErrors3));
            $labels3 = $this->driver->findElements(\WebDriverBy::xpath($xpathLabels3));
            $errors = array_merge($errors1, $errors2, $errors3);
            $labels = array_merge($labels1, $labels2, $labels3);

            for ($i = 0; $i < sizeof($labels); $i++) {
                $labels[$i] = preg_replace('/[*:]/', '', $labels[$i]->getText());
                $errors[$i] = preg_replace('/[*:]/', '', $errors[$i]->getText());
            }

            $msg = [];

            foreach ($labels as $i => $label) {
                $msg[] = sprintf('%s (%s)', $label, $errors[$i]);
            }
            $msg = implode(', ', $msg);
            $msg = 'Invalid values in fields: ' . $msg;

            throw new \UserInputError($msg);
        }

        $passwordError = $this->waitElement(\WebDriverBy::cssSelector('.strength-indicator'), $this->timeout, true);

        if ($passwordError) {
            throw new \UserInputError($passwordError->getText());
        }

        $xpathSiteError = '//div[
			contains(@class, "alert-content-primary") and
			contains(text(), "Error occurred while inserting a record")]
		';
        $siteError = $this->waitForElement(\WebDriverBy::xpath($xpathSiteError), $this->timeout, true);

        if ($siteError) {
            throw new \UserInputError($siteError->getText());
        }

        throw new \EngineError('Unexpected response on account registration submit');
    }
}
