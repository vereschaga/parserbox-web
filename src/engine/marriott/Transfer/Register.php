<?php

namespace AwardWallet\Engine\marriott\Transfer;

class Register extends \TAccountCheckerMarriott
{
    public const TIME_OUT = 32;

    public static $inputFieldsMap = [
        'Email'    => 'field-email',
        'Password' => [
            'field-password',
            'field-password-confirmation',
        ],
        'FirstName'               => 'field-first-name',
        'LastName'                => 'field-last-name',
        'Country'                 => 'field-country',
        'PostalCode'              => 'field-postal',
        'ReceiveMarriottEmails'   => 'field-email-promotional-opt-in',
        'ReceiveThirdPartyEmails' => 'field-email-third-party-opt-in',
    ];
    /*	static $inputFieldsMap = [
        'Email' => 'emailAddress',
        'Password' => [
            'password',
            'passwordConfirmation'
        ],
//		'EarningPreferences' => 'earningPreference',
        'ReceiveMarriottEmails' => 'marriotEmailOptIn',
        'ReceiveThirdPartyEmails' => 'thirdPartyEmailOptIn',
//		'Title' => 'title',
        'FirstName' => 'firstName',
///		'MiddleName' => 'middleName',
        'LastName' => 'lastName',
///		'Suffix' => 'suffix',
        'Country' => 'country',
//		'AddressType' => 'addressType',
///		'AddressLine1' => 'street1',
///		'AddressLine2' => 'street2',
//		'AddressLine3' => 'street3',
///		'City' => 'city',
///		'StateOrProvince' => 'stateProvince',
        'PostalCode' => 'postalCode',
//		'PhoneCountryCodeNumeric' => false,
//		'PhoneAreaCode' => false,
//		'PhoneLocalNumber' => false,
///		'PhoneType' => 'telephoneNumberType'
    ];
*/
    public static $salutations = [
        ''     => '',
        'MR'   => 'Mr.',
        'MRS'  => 'Mrs.',
        'DR'   => 'Dr.',
        'MS'   => 'Ms.',
        'MISS' => 'Miss',
    ];

    public static $suffixes = [
        ''    => '',
        'JR'  => 'Jr',
        'SR'  => 'Sr',
        'ESQ' => 'Esq',
        'II'  => 'II',
        'III' => 'III',
        'MD'  => 'MD',
    ];

    public static $countries = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
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
        'BA' => 'Bosnia and Herzegovina',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'BR Indian Ocean Territories',
        'VG' => 'British Virgin Islands',
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
        'CC' => 'Cocos Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoro Islands',
        'CG' => 'Congo',
        'CD' => 'Congo, Dem. Republic',
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
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FO' => 'Faeroe Islands',
        'FK' => 'Falkland Islands',
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
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard & McDonald Islands',
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
        'KP' => 'Korea, Dem. Peoples',
        'KR' => 'Korea, Republic Of',
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
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'MD' => 'Moldova, Republic of',
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
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
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
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'LC' => 'Saint Lucia',
        'WS' => 'Samoa, Ind. State of',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome-Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'GS' => 'S Georgia-S Sandwich',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SH' => 'St Helena',
        'KN' => 'St Kitts-Nevis',
        'PM' => 'St Pierre & Miquelon',
        'VC' => 'St Vincent & The Grenadines',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard & Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikstan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TL' => 'Timor-Leste',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad & Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks & Caicos',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'UY' => 'Uruguay',
        'US' => 'USA',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'VI' => 'Virgin Islands (United States)',
        'WF' => 'Wallis & Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    public static $addressTypes = [
        'H' => 'Home',
        'B' => 'Business',
    ];

    public static $earningPreferences = [
        'HP' => 'Points',
        'MI' => 'Miles',
    ];

    public static $states = [
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
        'FM' => 'Federated States of Micronesia',
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
        'MH' => 'Marshall Islands',
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
        'PW' => 'Palau',
        'PA' => 'Pennsylvania',
        'PR' => 'Puerto Rico',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VI' => 'Virgin Islands',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
    ];

    public function InitBrowser()
    {
        $this->UseSelenium();
        $this->useChromium();

        if (ConfigValue(CONFIG_SITE_STATE) !== SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyDOP());
        }
    }

    public function registerAccount(array $fields)
    {
        $this->logger->notice(__METHOD__);

        if (!$this->checkPassword($fields['Password'])) {
            throw new \UserInputError('invalid password');
        }

        if (in_array($fields['Country'], ['US', 'CA']) && empty($fields['PostalCode'])) {
            throw new \UserInputError('Postal code is required!');
        }
        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
            [800, 600],
        ];
        $this->setScreenResolution($resolutions[array_rand($resolutions)]);
        // refs #14848
        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->useCache();
        }
        $this->disableImages();
        $this->http->start();
        $this->Start();
        $this->http->removeCookies();

        $this->http->GetURL('https://www.marriott.com/rewards/createAccount/createAccountPage1.mi?segmentId=elite.nonrewards');

        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($fields[$awKey]) || $provKeys === false) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                switch ($provKey) {
                    case 'field-country':
                        try {
                            (new \WebDriverSelect($this->waitForElement(\WebDriverBy::id('field-country'), self::TIME_OUT)))->selectByValue($fields['Country']);
                        } catch (\NoSuchElementException $e) {
                            return $this->fail($e->getMessage());
                        }

                        break;

                    case 'field-email-promotional-opt-in':
                    case 'field-email-third-party-opt-in':
                        if (isset($fields[$awKey]) && $fields[$awKey] === true) {
                            $this->logger->info("Setting '$awKey' to input '{$fields[$awKey]}' ...");
                            $this->logger->info("document.querySelector('#" . $provKey . "').checked = {$fields[$awKey]};");
                            $this->driver->executeScript("document.querySelector('#" . $provKey . "').checked = true;");
                        } else {
                            $this->logger->info("Setting '$awKey' to input false ...");
                            $this->logger->info("document.querySelector('#" . $provKey . "').checked = false;");
                            $this->driver->executeScript("document.querySelector('#" . $provKey . "').checked = false;");
                        }

                        break;

                    case 'field-postal':
                        if (in_array($fields['Country'], ['US', 'CA'])) {
                            $this->logger->info("Setting '$awKey' to input '{$fields[$awKey]}' ...");
                            $this->driver->findElement(\WebDriverBy::id($provKey))->sendKeys($fields[$awKey]);
                            $this->logger->info('Done.');
                        }

                        break;

                    default:
                        $this->logger->info("Setting '$awKey' to input '{$fields[$awKey]}' ...");
                        $this->driver->findElement(\WebDriverBy::id($provKey))->sendKeys($fields[$awKey]);
                        $this->logger->info('Done.');
                }
            }
        }

        if (!$button = $this->waitForElement(\WebDriverBy::xpath("//button[@type='submit' and contains(.,'Join')]"), self::TIME_OUT)) {
            throw new \CheckException("submit not found");
        }
        $this->http->SaveResponse();
        $button->click();
        $success = $this->waitForElement(\WebDriverBy::xpath("//text()[starts-with(normalize-space(.), 'Welcome to Marriott Rewards')]/ancestor::*[1]"), self::TIME_OUT);

        $this->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML'));
        $this->logger->info('after return html');
        $this->http->SaveResponse();

        $num = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.), 'Rewards number')]/following::text()[normalize-space(.)!=''][1])[1]");

        if ($success && $num) {
            $this->ErrorMessage = $success->getText() . ' Rewards number ' . $num;

            return true;
        }

        //errors
        if ($element = $this->waitForElement(\WebDriverBy::xpath("(//div[contains(@class,'-error-') and normalize-space(.)!=''])[1]"), self::TIME_OUT)) {
            throw new \UserInputError($element->getText());
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password (8-20 characters, must contain a lower case letter, an upper case letter, a number and a special character $ ! # & @ ? % = _ )',
                'Required' => true,
            ],
            /*
            'EarningPreferences' => [
                'Type' => 'string',
                'Caption' => 'Earning Preferences',
                'Required' => true,
                'Options' => self::$earningPreferences
            ],
            */

            'ReceiveMarriottEmails' => [
                'Type'     => 'boolean',
                'Caption'  => 'I would like to receive account updates, program news and offers via email and direct mail.',
                'Required' => true,
            ],
            'ReceiveThirdPartyEmails' => [
                'Type'     => 'boolean',
                'Caption'  => 'I would like to receive exclusive offers from select third parties.',
                'Required' => true,
            ],
            /*
            'Title' => [
                'Type' => 'string',
                'Caption' => 'Salutation',
                'Options' => self::$salutations,
                'Required' => false,
            ],
            */
            'FirstName' => [
                'Type'     => 'string',
                'Required' => true,
                'Caption'  => 'First Name',
            ],
            /*'MiddleName' => [
                'Type' => 'string',
                'Caption' => 'Middle Name',
                'Required' => false,
            ],
            */
            'LastName' => [
                'Type'     => 'string',
                'Required' => true,
                'Caption'  => 'Last Name',
            ],
            /*
            'Suffix' => [
                'Type' => 'string',
                'Caption' => 'Salutation',
                'Options' => self::$suffixes,
                'Required' => false,
            ],
            */
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            /*
            'AddressType' => [
                'Type' => 'string',
                'Caption' => 'Address Type',
                'Required' => true,
                'Options' => self::$addressTypes
            ],
            'AddressLine1' => [
                'Type' => 'string',
                'Caption' => 'Address Line 1',
                'Required' => true,
            ],
            'AddressLine2' => [
                'Type' => 'string',
                'Caption' => 'Address Line 2',
                'Required' => false,
            ],
            'AddressLine3' => [
                'Type' => 'string',
                'Caption' => 'Address Line 3',
                'Required' => false,
            ],
            'City' => [
                'Type' => 'string',
                'Caption' => 'City',
                'Required' => true,
            ],
            'StateOrProvince' => [
                'Type' => 'string',
                'Caption' => 'State/Province, required for Canada, China, Italy, Russia, US (please choose your state/province for China, Italy, Russia or US and type your province for Canada)',
                'Required' => false
            ],
*/
            'PostalCode' => [
                'Type'     => 'string',
                'Caption'  => 'Zip code/Postal code',
                'Required' => false,
            ],
        /*			'PhoneCountryCodeNumeric' => [
                            'Type' => 'string',
                            'Caption' => 'Phone Country Code (numeric)',
                            'Required' => false,
                        ],
                        'PhoneAreaCode' => [
                            'Type' => 'string',
                            'Caption' => 'Phone Area Code',
                            'Required' => false,
                        ],
                        'PhoneLocalNumber' => [
                            'Type' => 'string',
                            'Caption' => 'Phone Local Number',
                            'Required' => false,
                        ],
                        'PhoneType' => [
                            'Type' => 'string',
                            'Caption' => 'Phone Type',
                            'Options' => [
                                'H' => 'Home',
                                'B' => 'Business',
                                'M' => 'Mobile'
                            ],
                            'Required' => false,
                        ]
            */];
    }

    protected function fail($message = null)
    {
        if (isset($message)) {
            $this->logger->error($message);
        }
        $this->http->SaveResponse();

        return false;
    }

    /*
    registered creds: tohobagi@mailzi.ru/P4ssw0rd
    */

    private function checkPassword($pass)
    {
        if (strlen($pass) < 8 || strlen($pass) > 20 || !preg_match('#[0-9\!\@\#\$\%\&\_\=\?]#', $pass) || !preg_match('#[A-Z]#', $pass) || !preg_match('#[a-z]#', $pass)) {
            return false;
        }

        return true;
    }
}
