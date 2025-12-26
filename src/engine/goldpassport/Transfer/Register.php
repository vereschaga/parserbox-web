<?php

namespace AwardWallet\Engine\goldpassport\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;
    public const TIME_OUT = 12;

    public static $inputFieldsMap = [
        'Title'        => 'prefix',
        'FirstName'    => 'firstName',
        'LastName'     => 'lastName',
        'Country'      => 'country',
        'AddressLine1' => 'address1',
        'AddressLine2' => 'address2',
        'City'         => 'city',
        'PostalCode'   => 'postalCode',
        'Email'        => 'email',
        'Password'     => [
            'password',
            'confirmPassword',
        ],
    ];

    public static $titles = [
        'MR'   => 'Mr.',
        'MRS'  => 'Mrs.',
        'MISS' => 'Miss',
        'MS'   => 'Ms.',
        'DR'   => 'Dr.',
    ];

    public static $states = [
        //USA
        "AS" => "American Samoa",
        "AA" => "Armed Forces America",
        "AE" => "Armed Forces EUR/CAN",
        "AK" => "Alaska",
        "AL" => "Alabama",
        "AP" => "Armed Forces Pacific",
        "AR" => "Arkansas",
        "AZ" => "Arizona",
        "CA" => "California",
        "CO" => "Colorado",
        "CT" => "Connecticut",
        "DC" => "District Of Columbia",
        "DE" => "Delaware",
        "FL" => "Florida",
        "GA" => "Georgia",
        "GU" => "Guam",
        "HI" => "Hawaii",
        "IA" => "Iowa",
        "ID" => "Idaho",
        "IL" => "Illinois",
        "IN" => "Indiana",
        "KS" => "Kansas",
        "KY" => "Kentucky",
        "LA" => "Louisiana",
        "MA" => "Massachusetts",
        "MD" => "Maryland",
        "ME" => "Maine",
        "MI" => "Michigan",
        "MN" => "Minnesota",
        "MO" => "Missouri",
        "MP" => "Mariana Islands",
        "MS" => "Mississippi",
        "MT" => "Montana",
        "NC" => "North Carolina",
        "ND" => "North Dakota",
        "NE" => "Nebraska",
        "NH" => "New Hampshire",
        "NJ" => "New Jersey",
        "NM" => "New Mexico",
        "NV" => "Nevada",
        "NY" => "New York",
        "OH" => "Ohio",
        "OK" => "Oklahoma",
        "OR" => "Oregon",
        "PA" => "Pennsylvania",
        "RI" => "Rhode Island",
        "SC" => "South Carolina",
        "SD" => "South Dakota",
        "TN" => "Tennessee",
        "TX" => "Texas",
        "UT" => "Utah",
        "VA" => "Virginia",
        "VI" => "Vermont",
        "VT" => "Virgin Islands",
        "WA" => "Washington",
        "WI" => "Wisconsin",
        "WV" => "West Virginia",
        "WY" => "Wyoming",
        "XX" => "Not Applicable",
        //Canada
        "AB" => "Alberta",
        "BC" => "British Columbia",
        "MB" => "Manitoba",
        "NB" => "New Brunswick",
        "NL" => "Newfoundland &amp; Labrador",
        "NT" => "Northwest Territory",
        "NS" => "Nova Scotia",
        "ON" => "Ontario",
        "PE" => "Prince Edward Island",
        "QC" => "Quebec",
        "SK" => "Saskatchewan",
        "YT" => "Yukon Territory",
        "NU" => "Nunavut",
        //Australia
        "AC" => "Australian Capital Territory",
        "NW" => "New South Wales",
        "NT" => "Northern Territory",
        "QL" => "Queensland",
        "SA" => "South Australia",
        "TA" => "Tasmania",
        "VC" => "Victoria",
        "WA" => "Western Australia",
    ];

    public static $countries = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua/Barbuda',
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
        'BA' => 'Bosnia',
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
        'CA' => 'Canada',
        'CV' => 'Cape Verde',
        'KY' => 'Cayman Islands',
        'CF' => 'Central Africa',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (Keeling) Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CD' => 'Congo, Democratic Republic of',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'CI' => 'Cote D\'Ivoire',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CW' => 'Curacao',
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
        'FO' => 'Faeroe Islands',
        'FK' => 'Falkland Islands',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
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
        'GD' => 'Grenadines',
        'GP' => 'Guadeloupe',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GF' => 'Guiana',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard And Mcdonald Islands',
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
        'KR' => 'Korea',
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
        'PS' => 'Palestine Territory',
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
        'LC' => 'Saint Lucia',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome And Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovak Republic',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'GS' => 'South Georgia',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SH' => 'St. Helena',
        'KN' => 'St. Kitts & Nevis',
        'PM' => 'St. Pierre',
        'VC' => 'St. Vincent & The Grenadines',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard And Jan Mayen Islands',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad And Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks And Caicos Islands',
        'TV' => 'Tuvalu',
        'UM' => 'U.S. Minor Outlying Islands',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City State',
        'VE' => 'Venezuela',
        'VN' => 'Viet Nam',
        'VG' => 'Virgin Islands (British)',
        'VI' => 'Virgin Islands (U.S.)',
        'WF' => 'Wallis And Futuna Islands',
        'EH' => 'Western Sahara',
        'WS' => 'Western Samoa',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    //	static $phoneTypes = [
    //		'H' => 'Home',
    //		'B' => 'Business',
    //		'M' => 'Mobile'
    //	];

    public static $languages = [
        'en'      => 'English',
        'zh-Hans' => 'Simplified Chinese',
        'zh-Hant' => 'Traditional Chinese',
        'fr'      => 'French',
        'de'      => 'German',
        'ja'      => 'Japanese',
        'ko'      => 'Korean',
        'es'      => 'Spanish',
    ];

    public static $preferredEmailMessagesFormat = [
        'H' => 'HTML',
        'T' => 'Plain Text',
    ];

    protected $languageMap = [
        'en'      => 'EN',
        'zh-Hans' => 'CS',
        'zh-Hant' => 'CH',
        'fr'      => 'FR',
        'de'      => 'DE',
        'ja'      => 'JA',
        'ko'      => 'KO',
        'es'      => 'ES',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useChromium();

        if (ConfigValue(CONFIG_SITE_STATE) !== SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyDOP());
        }
    }

    public function registerAccount(array $fields)
    {
        if (!preg_match("#[a-z0-9]{8,35}#i", $fields['Password'])) {
            throw new \UserInputError('invalid password');
        }
        $this->http->GetURL('https://goldpassport.hyatt.com/content/gp/en/enroll.html');

        if (($fields['Country'] == 'US' || $fields['Country'] == 'CA' || $fields['Country'] == 'AU') && !$fields['StateOrProvince']) {
            throw new \UserInputError('State or province is required for USA, Canada and Australia');
        }

        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($fields[$awKey]) || $provKeys === false) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                switch ($provKey) {
                    case 'prefix':
                        $this->driver->executeScript(
                            "var el = jQuery('a[id^=\"sbSelector\"]:contains(\"Select\")').parent();
							el.find('[rel=\"string:" . $fields['Title'] . "\"]').click();"
                        );

                        break;

                    case 'country':
                        $this->driver->executeScript(
                            "var el1 = jQuery('a[id^=\"sbSelector\"]:contains(\"Country\")').parent();
							el1.find('[rel=\"string:" . $fields['Country'] . "\"]').click();"
                        );

                        break;

                    default:
                        if ($elem = $this->waitForElement(\WebDriverBy::xpath("//input[@ng-model='newUser." . $provKey . "']"), self::TIME_OUT)) {
                            $elem->sendKeys($fields[$awKey]);
                        }
                }
            }
        }

        if ($fields['Country'] == 'US' || $fields['Country'] == 'CA' || $fields['Country'] == 'AU') {
            $this->driver->executeScript(
                "var el = jQuery('a[id^=\"sbSelector\"]:contains(\"State / Province\")').parent();
				el.find('[rel=\"string:" . $fields['StateOrProvince'] . "\"]').click();"
            );
        }

        if (!$button = $this->waitForElement(\WebDriverBy::xpath("//input[starts-with(normalize-space(@ng-disabled),'enrollmentInProgress')]"), self::TIME_OUT)) {
            $this->ErrorMessage = "submit not found";

            return false;
        }
        $button->click();
        $success = $this->waitForElement(\WebDriverBy::xpath("//text()[contains(normalize-space(.), 'Hyatt Gold Passport Number')]/ancestor::div[1]"), self::TIME_OUT);

        if ($success) {
            $this->ErrorMessage = $success->getText();

            return true;
        }

        if ($this->waitForElement(\WebDriverBy::xpath("//text()[starts-with(normalize-space(.),'Welcome to World of Hyatt')]/ancestor::*[1]", self::TIME_OUT))) {
            $success = $this->waitForElement(\WebDriverBy::xpath("//text()[contains(normalize-space(.), 'Member #')]/ancestor::*[1]"), self::TIME_OUT);

            if ($success) {
                $this->ErrorMessage = 'Welcome to World of Hyatt.' . ' ' . str_replace("\n", " ", $success->getText());

                return true;
            }
        }

        //errors
        $xpath = "//label[contains(@class, 'error-')]";
        $element = $this->waitForElement(\WebDriverBy::xpath($xpath), self::TIME_OUT);

        if ($element && preg_match('#(?:([\*]+\w+)\n|([\*]+\w+\s+\/[\s\w]+?)\n)#', $element->getText(), $math)) {
            if (preg_match('#\S+\s+error-(\w+)#i', $element->getAttribute('class'), $m)) {
                if ($errMsg = $this->waitForElement(\WebDriverBy::xpath($xpath . "/following-sibling::span[contains(@class, 'message-" . $m[1] . "')]"), self::TIME_OUT)) {
                    throw new \UserInputError($math[1] . ': ' . $errMsg->getText());
                }
            }
        }

        if ($element = $this->waitForElement(\WebDriverBy::xpath("(//div[contains(@class, 'error-')])[1]"), self::TIME_OUT)) {
            throw new \UserInputError($element->getText());
        }

        if ($errReg = $this->waitForElement(\WebDriverBy::xpath("//div[contains(text(), 'sorry, your email or username is invalid or already in use')]"), self::TIME_OUT)) {
            throw new \UserInputError($errReg->getText());
        }

        if ($err = $this->waitForElement(\WebDriverBy::xpath("//*[contains(text(), 'The Hyatt Gold Passport System is temporarily offline for maintenance')]"), self::TIME_OUT)) {
            throw new \UserInputError($err->getText());
        }

        return false;
    }

    public function oldRegisterAccount(array $fields)
    {
        $this->http->Log('[INFO] initial fields:');
        $this->http->Log(json_encode($fields, JSON_PRETTY_PRINT));

        if (!in_array($fields['Country'], array_keys(self::$countries))) {
            throw new \UserInputError('Invalid country code');
        }

        // Provider uses wrong country codes for:
        // - East Timor (TP instead of standard TL)
        // - Grenadines (GC instead of standard GD)
        // Map from our standard ISO code to wrong code used by provider
        $wrongCountryCodesFixingMap = [
            'TL' => 'TP',
            'GD' => 'GC',
        ];

        if (isset($wrongCountryCodesFixingMap[$fields['Country']])) {
            $origCountryCode = $fields['Country'];
            $fields['Country'] = $wrongCountryCodesFixingMap[$fields['Country']];
            $this->logger->debug('Mapped standard country code "' . $origCountryCode . '" to provider code "' . $fields['Country'] . '"');
        }

        $this->http->GetURL('https://goldpassport.hyatt.com/content/gp/en/enroll.html'); //https://www.hyatt.com/gp/en/benefits/join.jsp

        $status = $this->http->ParseForm(null, 1, true, "//*[contains(@ng-submit, 'expressEnrollUser')]");

        if (!$status) {
            $this->http->Log('Failed to parse create account form', LOG_LEVEL_ERROR);

            return false;
        }

        if (($fields['Country'] == 'US' or $fields['Country'] == 'CA' or $fields['Country'] == 'AU')
            and !$fields['StateOrProvince']
        ) {
            throw new \UserInputError('State or province is required for USA, Canada and Australia');
        }

        $this->http->setDefaultHeader('Accept', 'application/json, text/plain, */*');
        $this->http->setDefaultHeader('Content-Type', 'application/json;charset=UTF-8');
        $this->http->setDefaultHeader('Origin', 'https://goldpassport.hyatt.com');
        $country = $fields['Country'];
        $regData = [
            'address1'        => $fields['AddressLine1'],
            'firstName'       => $fields['FirstName'],
            'email'           => $fields['Email'],
            'lastName'        => $fields['LastName'],
            'password'        => $fields['Password'],
            'postalCode'      => $fields['PostalCode'],
            'namePrefix'      => $fields['Title'],
            'confirmPassword' => $fields['Password'],
            'city'            => $fields['City'],
            'country'         => $country,
            'state'           => ($country === 'US' or $country === 'CA' or $country === 'AU') ? $fields['StateOrProvince'] : '',
            'address2'        => $fields['AddressLine2'] ?? '',
        ];

        $status = $this->http->PostURL('https://goldpassport.hyatt.com/bin/gp/enroll-user', json_encode($regData));

        if (!$status) {
            $msg = 'Failed to post create account form';
            $this->http->Log($msg);

            throw new \EngineError($msg);
        }
        $returnJsonObj = $this->http->JsonLog(null, true, true);
        $isSuccessful = ArrayVal($returnJsonObj, 'isSuccessful', false); //ищет в массиве значение isSuccessful, false - значение по умолчанию для элемента массива

        if ($isSuccessful === true) {
            $goldPassportId = ArrayVal($returnJsonObj, 'goldPassportId');
            $this->ErrorMessage = 'Hyatt Gold Passport Number ' . $goldPassportId;
            $this->http->Log($this->ErrorMessage);

            return true;
        }
        $errors = ArrayVal($returnJsonObj, 'enrollmentDetails');

        if ($errors) {
            $e = ArrayVal($errors, 'errors');
            $err = ArrayVal($e, 'default');

            throw new \CheckException($err);
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Title' => [
                'Type'     => 'string',
                'Caption'  => 'namePrefix',
                'Required' => true,
                'Options'  => self::$titles,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'firstName',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'lastName',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'password (Password must be between 8 and 35 characters long.)',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'email',
                'Required' => true,
            ],
            'AddressLine1' => [
                'Type'     => 'string',
                'Caption'  => 'address1',
                'Required' => true,
            ],
            'AddressLine2' => [
                'Type'     => 'string',
                'Caption'  => 'address2',
                'Required' => false,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'city',
                'Required' => true,
            ],
            'StateOrProvince' => [
                'Type'     => 'string',
                'Caption'  => 'State / Province',
                'Required' => false,
                'Options'  => self::$states,
            ],
            'PostalCode' => [
                'Type'     => 'string',
                'Caption'  => 'Zip/Postal Code (Required for U.S., Canada, and Australia) ',
                'Required' => false,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Options'  => self::$countries,
            ],
        ];
    }
}
