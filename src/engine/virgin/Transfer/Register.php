<?php

namespace AwardWallet\Engine\virgin\Transfer;

class Register extends \TAccountCheckerVirgin
{
    public static $countries = [
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'AF' => 'Afghanistan',
        'AX' => 'Aland Islands',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AC' => 'Ascension',
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
        'BQ' => 'Bonaire Sint Eustatius & Saba',
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
        'SP' => 'Channel Islands',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Islands',
        'CC' => 'Cocos (Keeling) Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CW' => 'Curacao',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'CD' => 'Dem Rep of the Congo',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'TP' => 'East Timor',
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
        'PF' => 'French Polynesia',
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
        'GG' => 'Guernsey',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'VA' => 'Holy See (Vatican City)',
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
        'CI' => 'Ivory Coast',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JE' => 'Jersey',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
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
        'AN' => 'Netherlands Antilles',
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
        'RE' => 'Reunion Islands',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'BL' => 'Saint Barthelemy',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'SX' => 'Saint Maarten',
        'MF' => 'Saint Martin',
        'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'YU' => 'Serbia and Montenegro',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Soloman Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'GS' => 'South Georgia',
        'KR' => 'South Korea',
        'SS' => 'South Sudan',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SH' => 'St. Helena',
        'PM' => 'St. Pierre and Miquelon',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TL' => 'Timor-Leste',
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
        'UM' => 'United States Outlying Islands',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'VI' => 'Virgin Islands US',
        'WF' => 'Wallis and Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZR' => 'Zaire',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    public static $requiredState = ['US', 'BR', 'CA', 'CN', 'JP'];

    public static $requiredPostalCode = ['US', 'DZ', 'AR', 'AM', 'AT', 'AZ', 'BH', 'BD', 'BY', 'BE', 'BJ', 'BM', 'BA', 'BR', 'BG', 'BF', 'CA', 'CL', 'CN', 'HR', 'CY', 'CZ', 'DK', 'EC', 'EG', 'EE', 'FI', 'FR', 'GF', 'GE', 'DE', 'GR', 'GL', 'GP', 'GW', 'HT', 'VA', 'HU', 'IS', 'IN', 'IQ', 'IL', 'IT', 'CI', 'JP', 'JO', 'KZ', 'KW', 'KG', 'LV', 'LR', 'LI', 'LT', 'LU', 'MK', 'MG', 'MW', 'MT', 'MQ', 'MX', 'MD', 'MC', 'ME', 'MA', 'MZ', 'NP', 'NL', 'NG', 'NO', 'OM', 'PK', 'PE', 'PL', 'PT', 'RE', 'RO', 'RU', 'SM', 'SA', 'RS', 'SK', 'ZA', 'ES', 'PM', 'SZ', 'SE', 'CH', 'TJ', 'TN', 'TR', 'TM', 'UY', 'UZ', 'VE', 'EH'];

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;

        if (!isset($fields['SecurityQuestionType1'])) {
            $fields['SecurityQuestionType1'] = '';
        }

        if (!isset($fields['SecurityQuestionType2'])) {
            $fields['SecurityQuestionType2'] = '';
        }

        if (!isset(self::regTitle()[strtoupper($fields['Title'])])) {
            throw new \UserInputError('Invalid title.');
        }

        if (!isset(self::regSecurityQuestions()[$fields['SecurityQuestionType1']]) || !isset(self::regSecurityQuestions()[$fields['SecurityQuestionType2']])) {
            $randQuestionKeys = array_keys(self::regSecurityQuestions());
            shuffle($randQuestionKeys);
            $fields['SecurityQuestionType1'] = $randQuestionKeys[0];
            $fields['SecurityQuestionAnswer1'] = 'Some answer ' . rand(1, 50);
            $fields['SecurityQuestionType2'] = $randQuestionKeys[1];
            $fields['SecurityQuestionAnswer2'] = 'Some answer ' . rand(51, 100);
        }

        if (!preg_match('/^[A-z0-9]{6,20}$/', $fields['Password'])) {
            throw new \UserInputError('Password should be 6 to 20 characters long. No special characters.');
        }

        if ($fields['SecurityQuestionType1'] === $fields['SecurityQuestionType2']) {
            throw new \UserInputError('Security questions should be different.');
        }

        if ($fields['SecurityQuestionAnswer1'] === $fields['SecurityQuestionAnswer2']) {
            throw new \UserInputError('Security answers should be different.');
        }

        if (in_array($fields['Country'], self::$requiredState)) {
            if (!array_key_exists($fields['StateOrProvince'], self::regStateByCountry()[$fields['Country']])) {
                throw new \UserInputError('Invalid State.');
            }
        }

        if (in_array($fields['Country'], self::$requiredPostalCode)) {
            if (!preg_match('/^\S{2,12}$/', $fields['PostalCode'])) {
                throw new \UserInputError('Invalid Postal Code.');
            }
        }

        $this->http->FilterHTML = false;
        $this->http->GetURL('https://www.virginatlantic.com/profile/enrolllanding.action');

        if (!$this->http->ParseForm('formButton')) {
            $this->http->Log('Parse form error.');

            return false;
        }

        $fields['Title'] = strtoupper($fields['Title']);
        $fields['Gender'] = self::regGender()[$fields['Gender']];
        $fields['BirthMonth'] = (int) $fields['BirthMonth'] - 1;

        if (in_array($fields['AddressType'], self::regAddressType())) {
            $fields['AddressType'] = array_search($fields['AddressType'], self::regAddressType());
        }
        $fields['PhoneNumber'] = $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];
        $phoneCountryCodes = $this->http->FindNodes('//select[@id="phoneCountryCode"]/option/@value');

        if (count($phoneCountryCodes)) {
            $phoneCountryCodes[] = '1_US';

            foreach ($phoneCountryCodes as $value) {
                $this->http->Log($value);

                if (preg_match('/' . $fields['PhoneCountryCodeAlphabetic'] . '$/', trim($value))) {
                    $fields['PhoneCountryCodeAlphabetic'] = trim($value);

                    break;
                }
            }
        } else {
            throw new \UserInputError('Phone country codes not found on page.');
        }

        $inputFieldsMap = [
            'customerDo.name.title'                                => 'Title',
            'customerDo.name.firstName'                            => 'FirstName',
            'customerDo.name.middleName'                           => 'MiddleInitial',
            'customerDo.name.lastName'                             => 'LastName',
            'customerDo.gender'                                    => 'Gender',
            'dobDay'                                               => 'BirthDay',
            'dobMonth'                                             => 'BirthMonth',
            'dobYear'                                              => 'BirthYear',
            'customerDo.emails[0].emailAddress'                    => 'Email',
            'password'                                             => 'Password',
            'customerDo.securityQuestionsAndAnswers[0].questionId' => 'SecurityQuestionType1',
            'customerDo.securityQuestionsAndAnswers[0].answer'     => 'SecurityQuestionAnswer1',
            'customerDo.securityQuestionsAndAnswers[1].questionId' => 'SecurityQuestionType2',
            'customerDo.securityQuestionsAndAnswers[1].answer'     => 'SecurityQuestionAnswer2',
            'customerDo.addresses[0].type'                         => 'AddressType',
            'customerDo.addresses[0].addressLine1'                 => 'AddressLine1',
            'customerDo.addresses[0].addressLine4'                 => 'City',
            'customerDo.addresses[0].addressLine7'                 => 'StateOrProvince',
            'customerDo.addresses[0].addressLine8'                 => 'Country',
            'customerDo.addresses[0].addressLine9'                 => 'PostalCode',
            'phoneNumber'                                          => 'PhoneNumber',
            'customerDo.phones[0].countryCd'                       => 'PhoneCountryCodeAlphabetic',
        ];

        foreach ($inputFieldsMap as $provKeys => $awKey) {
            if (isset($fields[$awKey])) {
                $this->http->Form[$provKeys] = $fields[$awKey];
            }
        }
        $this->http->Form['termsAndCondUnchecked'] = 'true';

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($error = $this->http->FindSingleNode('//*[@id="profileEnrollLandingInlineerrors"]')) {
            throw new \UserInputError($error);
        }

        if (!$this->http->ParseForm('toLoginAppln')) {
            $this->http->Log('Parse form error.');

            return false;
        }

        if (!$this->http->PostForm()) {
            return false;
        }

        if (!$this->http->ParseForm('smlogin_refresh')) {
            $this->http->Log('Parse form error.');

            return false;
        }

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($success = $this->http->FindSingleNode('//h4//text()[contains(normalize-space(.),"Hello, ' . $fields['FirstName'] . '!")]')) {
            $number = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"Membership no")]/following::text()[normalize-space(.)!=""][1]', null, true, '/(\d+)/');
            $this->ErrorMessage = $success . ' Your Account Membership Number: ' . $number;

            return true;
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            // Personal information

            "Title" => [
                "Caption"  => "Title",
                "Type"     => "string",
                "Required" => true,
                "Options"  => self::regTitle(),
            ],
            "FirstName" => [
                "Caption"  => "First Name",
                "Type"     => "string",
                "Required" => true,
            ],
            "MiddleInitial" => [
                "Caption"  => "Middle Initial",
                "Type"     => "string",
                "Required" => false,
            ],
            "LastName" => [
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Required" => true,
            ],
            "Gender" => [
                "Caption"  => "Gender",
                "Type"     => "string",
                "Required" => true,
                "Options"  => self::regGender(),
            ],
            "BirthDay" => [
                "Caption"  => "Birth date day",
                "Type"     => "integer",
                "Required" => true,
            ],
            "BirthMonth" => [
                "Caption"  => "Birth date month",
                "Type"     => "integer",
                "Required" => true,
            ],
            "BirthYear" => [
                "Caption"  => "Birth date year",
                "Type"     => "integer",
                "Required" => true,
            ],

            // Set up your log in details

            "Email" => [
                "Caption"  => "Email Address",
                "Type"     => "string",
                "Required" => true,
            ],
            "Password" => [
                "Caption"  => "Password, 6 to 20 characters long (no special characters)",
                "Type"     => "string",
                "Required" => true,
            ],
            "SecurityQuestionType1" => [
                "Type"     => "string",
                "Caption"  => "First Security Question",
                "Required" => ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION ? true : false,
                "Options"  => self::regSecurityQuestions(),
            ],
            "SecurityQuestionAnswer1" => [
                "Type"     => "string",
                "Caption"  => "Answer to the First Security Question",
                "Required" => ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION ? true : false,
            ],
            "SecurityQuestionType2" => [
                "Type"     => "string",
                "Caption"  => "Second Security Question",
                "Required" => ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION ? true : false,
                "Options"  => self::regSecurityQuestions(),
            ],
            "SecurityQuestionAnswer2" => [
                "Type"     => "string",
                "Caption"  => "Answer to the Second Security Question",
                "Required" => ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION ? true : false,
            ],

            // Address

            "AddressType" => [
                "Caption"  => "Address Type",
                "Type"     => "string",
                "Required" => true,
                "Options"  => self::regAddressType(),
            ],
            "Country" => [
                "Caption"  => "Country Code",
                "Type"     => "string",
                "Required" => true,
                "Options"  => self::$countries,
            ],
            "AddressLine1" => [
                "Caption"  => "Address Line",
                "Type"     => "string",
                "Required" => true,
            ],
            "City" => [
                "Caption"  => "City",
                "Type"     => "string",
                "Required" => true,
            ],
            "StateOrProvince" => [
                "Caption"  => 'State/County (required for ' . implode(', ', self::$requiredState) . ')',
                "Type"     => "string",
                "Required" => false,
            ],
            "PostalCode" => [
                "Caption"  => 'Zip Code (required for ' . implode(', ', self::$requiredPostalCode) . ')',
                "Type"     => "string",
                "Required" => false,
            ],

            // Contact number

            "PhoneCountryCodeAlphabetic" => [
                "Caption"  => "Phone Country Code (alphabetic)",
                "Type"     => "string",
                "Required" => true,
                "Options"  => self::regPhoneCode(),
            ],
            "PhoneAreaCode" => [
                "Caption"  => "Phone Area Code",
                "Type"     => "string",
                "Required" => true,
            ],
            "PhoneLocalNumber" => [
                "Caption"  => "Phone Local Number",
                "Type"     => "string",
                "Required" => true,
            ],
        ];
    }

    public static function regTitle()
    {
        return [
            'BRN' => 'BRN',
            'BRS' => 'BRS',
            'COT' => 'COT',
            'CPT' => 'CPT',
            'CTS' => 'CTS',
            'DME' => 'DME',
            'DR'  => 'DR',
            'LDY' => 'LDY',
            'LRD' => 'LRD',
            'MIS' => 'MIS',
            'MR'  => 'MR',
            'MRS' => 'MRS',
            'MS'  => 'MS',
            'MST' => 'MST',
            'PRF' => 'PRF',
            'SIR' => 'SIR',
            'SKH' => 'SKH',
            'VCS' => 'VCS',
            'VSN' => 'VSN',
        ];
    }

    public static function regGender()
    {
        return [
            'M' => 'Male',
            'F' => 'Female',
        ];
    }

    public static function regSecurityQuestions()
    {
        return [
            '1'  => 'What was the name of your childhood best friend?',
            '2'  => 'What was the make and model of your first car?',
            '3'  => 'Who is your favourite sports personality?',
            '4'  => 'What was the first concert/festival you attended?',
            '5'  => 'What is the name of the teacher/lecturer who gave you your best grade?',
            '6'  => 'What did you want to be when you grew up?',
            '7'  => 'Who was your childhood hero?',
            '8'  => 'What quality do you admire in a person?',
            '9'  => 'What is the most memorable landmark you\'ve ever visited?',
            '10' => 'What is the strangest food you\'ve ever eaten?',
            '11' => 'What was your favourite place to visit as a child?',
            '12' => 'Who was your first employer?',
            '13' => 'Who is your favourite Actor or Actress?',
            '14' => 'What is your favourite holiday activity?',
            '15' => 'What city or town did you first visit on your own?',
            '16' => 'What is your most memorable gift you\'ve ever received?',
            '17' => 'In what city or town did your mother and father meet?',
            '18' => 'What was the name of your first pet?',
        ];
    }

    public static function regAddressType()
    {
        return [
            'H' => 'HOME',
            'B' => 'WORK',
        ];
    }

    public static function regPhoneType()
    {
        return [
            '',
        ];
    }

    public static function regPhoneCode()
    {
        return [
            'AF' => 'Afghanistan (+93)',
            'AX' => 'Aland Islands (+358)',
            'AL' => 'Albania (+355)',
            'DZ' => 'Algeria (+213)',
            'AS' => 'American Samoa',
            'AD' => 'Andorra (+376)',
            'AO' => 'Angola (+244)',
            'AI' => 'Anguilla (+1)',
            'AQ' => 'Antarctica (+672)',
            'AG' => 'Antigua (+1)',
            'AR' => 'Argentina (+54)',
            'AM' => 'Armenia (+374)',
            'AW' => 'Aruba (+297)',
            'AC' => 'Ascension (+247)',
            'AU' => 'Australia (+61)',
            'AT' => 'Austria (+43)',
            'AZ' => 'Azerbaijan (+994)',
            'BS' => 'Bahamas (+1)',
            'BH' => 'Bahrain (+973)',
            'BD' => 'Bangladesh (+880)',
            'BB' => 'Barbados (+1)',
            'BY' => 'Belarus (+375)',
            'BE' => 'Belgium (+32)',
            'BZ' => 'Belize (+501)',
            'BJ' => 'Benin (+229)',
            'BM' => 'Bermuda (+1)',
            'BT' => 'Bhutan (+975)',
            'BO' => 'Bolivia (+591)',
            'BQ' => 'Bonaire Sint Eustatius & Saba (+599)',
            'BA' => 'Bosnia and Herzegovina (+387)',
            'BW' => 'Botswana (+267)',
            'BV' => 'Bouvet Island',
            'BR' => 'Brazil (+55)',
            'IO' => 'British Indian Ocean Territory',
            'VG' => 'British Virgin Islands (+1)',
            'BN' => 'Brunei (+673)',
            'BG' => 'Bulgaria (+359)',
            'BF' => 'Burkina Faso (+226)',
            'BI' => 'Burundi (+257)',
            'KH' => 'Cambodia (+855)',
            'CM' => 'Cameroon (+237)',
            'CA' => 'Canada (+1)',
            'CV' => 'Cape Verde (+238)',
            'KY' => 'Cayman Islands (+1)',
            'CF' => 'Central African Republic (+236)',
            'TD' => 'Chad (+235)',
            'SP' => 'Channel Islands',
            'CL' => 'Chile (+56)',
            'CN' => 'China (+86)',
            'CX' => 'Christmas Islands (+235)',
            'CC' => 'Cocos (Keeling) Islands (+61)',
            'CO' => 'Colombia (+57)',
            'KM' => 'Comoros (+269)',
            'CG' => 'Congo (+242)',
            'CK' => 'Cook Islands (+682)',
            'CR' => 'Costa Rica (+506)',
            'HR' => 'Croatia (+385)',
            'CU' => 'Cuba (+53)',
            'CW' => 'Curacao',
            'CY' => 'Cyprus (+357)',
            'CZ' => 'Czech Republic (+420)',
            'CD' => 'Dem Rep of the Congo (+243)',
            'DK' => 'Denmark (+45)',
            'DJ' => 'Djibouti (+253)',
            'DM' => 'Dominica (+1)',
            'DO' => 'Dominican Republic (+1)',
            'TP' => 'East Timor',
            'EC' => 'Ecuador (+593)',
            'EG' => 'Egypt (+20)',
            'SV' => 'El Salvador (+503)',
            'GQ' => 'Equatorial Guinea (+240)',
            'ER' => 'Eritrea (+291)',
            'EE' => 'Estonia (+372)',
            'ET' => 'Ethiopia (+251)',
            'FK' => 'Falkland Islands (+500)',
            'FO' => 'Faroe Islands (+298)',
            'FJ' => 'Fiji (+679)',
            'FI' => 'Finland (+358)',
            'FR' => 'France (+33)',
            'GF' => 'French Guiana (+594)',
            'PF' => 'French Polynesia (+689)',
            'GA' => 'Gabon (+241)',
            'GM' => 'Gambia (+220)',
            'GE' => 'Georgia (+995)',
            'DE' => 'Germany (+49)',
            'GH' => 'Ghana (+233)',
            'GI' => 'Gibraltar (+350)',
            'GR' => 'Greece (+30)',
            'GL' => 'Greenland (+299)',
            'GD' => 'Grenada (+1)',
            'GP' => 'Guadeloupe (+590)',
            'GU' => 'Guam (+1)',
            'GT' => 'Guatemala (+502)',
            'GG' => 'Guernsey',
            'GN' => 'Guinea (+224)',
            'GW' => 'Guinea-Bissau (+245)',
            'GY' => 'Guyana (+592)',
            'HT' => 'Haiti (+509)',
            'VA' => 'Holy See (Vatican City) (+39)',
            'HN' => 'Honduras (+504)',
            'HK' => 'Hong Kong (+852)',
            'HU' => 'Hungary (+36)',
            'IS' => 'Iceland (+354)',
            'IN' => 'India (+91)',
            'ID' => 'Indonesia (+62)',
            'IR' => 'Iran (+98)',
            'IQ' => 'Iraq (+964)',
            'IE' => 'Ireland (+353)',
            'IM' => 'Isle of Man (+44)',
            'IL' => 'Israel (+972)',
            'IT' => 'Italy (+39)',
            'CI' => 'Ivory Coast (+225)',
            'JM' => 'Jamaica (+1)',
            'JP' => 'Japan (+81)',
            'JE' => 'Jersey (+44)',
            'JO' => 'Jordan (+962)',
            'KZ' => 'Kazakhstan (+7)',
            'KE' => 'Kenya (+254)',
            'KI' => 'Kiribati (+686)',
            'KW' => 'Kuwait (+965)',
            'KG' => 'Kyrgyzstan (+996)',
            'LA' => 'Laos (+856)',
            'LV' => 'Latvia (+371)',
            'LB' => 'Lebanon (+961)',
            'LS' => 'Lesotho (+266)',
            'LR' => 'Liberia (+231)',
            'LY' => 'Libya (+218)',
            'LI' => 'Liechtenstein (+423)',
            'LT' => 'Lithuania (+370)',
            'LU' => 'Luxembourg (+352)',
            'MO' => 'Macau (+853)',
            'MK' => 'Macedonia (+389)',
            'MG' => 'Madagascar (+261)',
            'MW' => 'Malawi (+265)',
            'MY' => 'Malaysia (+60)',
            'MV' => 'Maldives (+960)',
            'ML' => 'Mali (+223)',
            'MT' => 'Malta (+356)',
            'MH' => 'Marshall Islands (+692)',
            'MQ' => 'Martinique (+596)',
            'MR' => 'Mauritania (+222)',
            'MU' => 'Mauritius (+230)',
            'YT' => 'Mayotte (+262)',
            'MX' => 'Mexico (+52)',
            'FM' => 'Micronesia (+691)',
            'MD' => 'Moldova (+373)',
            'MC' => 'Monaco (+377)',
            'MN' => 'Mongolia (+976)',
            'ME' => 'Montenegro (+382)',
            'MS' => 'Montserrat (+1)',
            'MA' => 'Morocco (+212)',
            'MZ' => 'Mozambique (+258)',
            'MM' => 'Myanmar (+95)',
            'NA' => 'Namibia (+264)',
            'NR' => 'Nauru (+674)',
            'NP' => 'Nepal (+977)',
            'NL' => 'Netherlands (+31)',
            'AN' => 'Netherlands Antilles (+1)',
            'NC' => 'New Caledonia (+687)',
            'NZ' => 'New Zealand (+64)',
            'NI' => 'Nicaragua (+505)',
            'NE' => 'Niger (+227)',
            'NG' => 'Nigeria (+234)',
            'NU' => 'Niue (+683)',
            'NF' => 'Norfolk Island (+672)',
            'KP' => 'North Korea (+850)',
            'MP' => 'Northern Mariana Islands (+1)',
            'NO' => 'Norway (+47)',
            'OM' => 'Oman (+968)',
            'PK' => 'Pakistan (+92)',
            'PW' => 'Palau',
            'PA' => 'Panama (+507)',
            'PG' => 'Papua New Guinea (+675)',
            'PY' => 'Paraguay (+595)',
            'PE' => 'Peru (+51)',
            'PH' => 'Philippines (+63)',
            'PN' => 'Pitcairn Island',
            'PL' => 'Poland (+48)',
            'PT' => 'Portugal (+351)',
            'PR' => 'Puerto Rico (+1)',
            'QA' => 'Qatar (+974)',
            'RE' => 'Reunion Islands (+262)',
            'RO' => 'Romania (+40)',
            'RU' => 'Russia (+7)',
            'RW' => 'Rwanda (+250)',
            'BL' => 'Saint Barthelemy (+590)',
            'KN' => 'Saint Kitts and Nevis (+1)',
            'LC' => 'Saint Lucia (+1)',
            'SX' => 'Saint Maarten (+721)',
            'MF' => 'Saint Martin (+599)',
            'VC' => 'Saint Vincent and the Grenadines (+1)',
            'WS' => 'Samoa (+685)',
            'SM' => 'San Marino (+378)',
            'ST' => 'Sao Tome and Principe (+239)',
            'SA' => 'Saudi Arabia (+966)',
            'SN' => 'Senegal (+221)',
            'RS' => 'Serbia (+381)',
            'YU' => 'Serbia and Montenegro (+381)',
            'SC' => 'Seychelles (+248)',
            'SL' => 'Sierra Leone (+232)',
            'SG' => 'Singapore (+65)',
            'SK' => 'Slovakia (+421)',
            'SI' => 'Slovenia (+386)',
            'SB' => 'Soloman Islands (+677)',
            'SO' => 'Somalia (+252)',
            'ZA' => 'South Africa (+27)',
            'GS' => 'South Georgia (+500)',
            'KR' => 'South Korea (+82)',
            'SS' => 'South Sudan (+211)',
            'ES' => 'Spain (+34)',
            'LK' => 'Sri Lanka (+94)',
            'SH' => 'St. Helena (+290)',
            'PM' => 'St. Pierre and Miquelon (+508)',
            'SD' => 'Sudan (+249)',
            'SR' => 'Suriname (+597)',
            'SZ' => 'Swaziland (+268)',
            'SE' => 'Sweden (+46)',
            'CH' => 'Switzerland (+41)',
            'SY' => 'Syrian Arab Republic (+963)',
            'TW' => 'Taiwan (+886)',
            'TJ' => 'Tajikistan (+992)',
            'TZ' => 'Tanzania (+255)',
            'TH' => 'Thailand (+66)',
            'TL' => 'Timor-Leste (+670)',
            'TG' => 'Togo (+228)',
            'TK' => 'Tokelau (+690)',
            'TO' => 'Tonga (+676)',
            'TT' => 'Trinidad and Tobago (+1)',
            'TN' => 'Tunisia (+216)',
            'TR' => 'Turkey (+90)',
            'TM' => 'Turkmenistan (+993)',
            'TC' => 'Turks and Caicos Islands (+1)',
            'TV' => 'Tuvalu (+688)',
            'UG' => 'Uganda (+256)',
            'UA' => 'Ukraine (+380)',
            'AE' => 'United Arab Emirates (+971)',
            'GB' => 'United Kingdom (+44)',
            'US' => 'United States (+1)',
            'UM' => 'United States Outlying Islands (+1)',
            'UY' => 'Uruguay (+598)',
            'UZ' => 'Uzbekistan (+998)',
            'VU' => 'Vanuatu (+678)',
            'VE' => 'Venezuela (+58)',
            'VN' => 'Vietnam (+84)',
            'VI' => 'Virgin Islands US (+1)',
            'WF' => 'Wallis and Futuna (+681)',
            'EH' => 'Western Sahara (+212)',
            'YE' => 'Yemen (+967)',
            'ZR' => 'Zaire (+243)',
            'ZM' => 'Zambia (+260)',
            'ZW' => 'Zimbabwe (+263)',
        ];
    }

    public static function regStateByCountry()
    {
        return [
            'US' => [
                'AL' => 'ALABAMA',
                'AK' => 'ALASKA',
                'AZ' => 'ARIZONA',
                'AR' => 'ARKANSAS',
                'AA' => 'ARMED FORCES AMERICAS (NOT CA)',
                'AP' => 'ARMED FORCES PACIFIC',
                'AE' => 'ARMED FORCES US,CA,EURO,AFRICA',
                'AS' => 'American Samoa',
                'CA' => 'CALIFORNIA',
                'CO' => 'COLORADO',
                'CT' => 'CONNECTICUT',
                'DE' => 'DELAWARE',
                'DC' => 'DISTRICT OF COLUMBIA',
                'FL' => 'FLORIDA',
                'GA' => 'GEORGIA',
                'GU' => 'Guam',
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
                'MH' => 'Marshall Islands',
                'FM' => 'Micronesia, Federated States o',
                'NE' => 'NEBRASKA',
                'NV' => 'NEVADA',
                'NH' => 'NEW HAMPSHIRE',
                'NJ' => 'NEW JERSEY',
                'NM' => 'NEW MEXICO',
                'NY' => 'NEW YORK',
                'NC' => 'NORTH CAROLINA',
                'ND' => 'NORTH DAKOTA',
                'MP' => 'NORTHERN MARIANA ISLANDS',
                'OH' => 'OHIO',
                'OK' => 'OKLAHOMA',
                'OR' => 'OREGON',
                'PA' => 'PENNSYLVANIA',
                'PR' => 'PUERTO RICO',
                'PW' => 'Palau',
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
            ],
            'BR' => [
                'AC' => 'Acre',
                'AL' => 'Alagoas',
                'AP' => 'Amapa',
                'AM' => 'Amazonas',
                'BA' => 'Bahia',
                'CE' => 'Ceara',
                'DF' => 'Distrito Federal',
                'ES' => 'Espirito Santo',
                'GO' => 'Goias',
                'MA' => 'Maranhao',
                'MT' => 'Mato Grosso',
                'MS' => 'Mato Grosso do Sul',
                'MG' => 'Minas Gerais',
                'PA' => 'Para',
                'PB' => 'Paraiba',
                'PR' => 'Parana',
                'PE' => 'Pernambuco',
                'PI' => 'Piaui',
                'RN' => 'Rio Grande do Norte',
                'RS' => 'Rio Grande do Sul',
                'RJ' => 'Rio de Janeiro',
                'RO' => 'Rondonia',
                'RR' => 'Roraima',
                'SC' => 'Santa Catarina',
                'SP' => 'Sao Paulo',
                'SE' => 'Sergipe',
                'TO' => 'Tocantins',
            ],
            'CA' => [
                'AB' => 'ALBERTA',
                'BC' => 'BRITISH COLUMBIA',
                'MB' => 'MANITOBA',
                'NB' => 'NEW BRUNSWICK',
                'NL' => 'NEWFOUNDLAND/LABRADOR',
                'NT' => 'NORTHWEST TERRITORIES',
                'NS' => 'NOVA SCOTIA',
                'NU' => 'NUNAVUT',
                'ON' => 'ONTARIO',
                'PE' => 'PRINCE EDWARD ISLAND',
                'QC' => 'QUEBEC',
                'SK' => 'SASKATCHEWAN',
                'YT' => 'YUKON TERRITORIES',
            ],
            'CN' => [
                'AH' => 'Anhui',
                'BJ' => 'Beijing',
                'CQ' => 'Chongqing',
                'FJ' => 'Fujian',
                'GS' => 'Gansu',
                'GD' => 'Guangdong',
                'GX' => 'Guangxi Zhuang',
                'GZ' => 'Guizhou',
                'HA' => 'Hainan',
                'HB' => 'Hebei',
                'HL' => 'Heilongjiang',
                'HE' => 'Henam',
                'HU' => 'Hubei',
                'HN' => 'Hunan',
                'JS' => 'Jiangsu',
                'JX' => 'Jiangxi',
                'JL' => 'Jilin',
                'LN' => 'Liaoning',
                'NM' => 'Nei Mongol',
                'NX' => 'Ningxia Hui',
                'QH' => 'Qinghai',
                'SA' => 'Shaanxi',
                'SD' => 'Shandong',
                'SH' => 'Shanghai',
                'SX' => 'Shanxi',
                'SC' => 'Sichuan',
                'TJ' => 'Tianjin',
                'XJ' => 'Xinjiang Uygur',
                'XZ' => 'Xizang',
                'YN' => 'Yunnan',
                'ZJ' => 'Zhejiang',
            ],
            'JP' => [
                'AI' => 'Aichi',
                'AK' => 'Akita',
                'AO' => 'Aomori',
                'CH' => 'Chiba',
                'EH' => 'Ehime',
                'FI' => 'Fukui',
                'FO' => 'Fukuoka',
                'FS' => 'Fukushima',
                'GF' => 'Gifu',
                'JS' => 'Gumma',
                'HS' => 'Hiroshima',
                'HK' => 'Hokkaido',
                'HG' => 'Hyogo',
                'IB' => 'Ibaraki',
                'IS' => 'Ishikawa',
                'IW' => 'Iwate',
                'KG' => 'Kagawa',
                'KS' => 'Kagoshima',
                'KN' => 'Kanagawa',
                'KC' => 'Kochi',
                'KM' => 'Kumamoto',
                'KY' => 'Kyoto',
                'ME' => 'Mie',
                'MG' => 'Miyagi',
                'MZ' => 'Miyazaki',
                'NN' => 'Nagano',
                'NS' => 'Nagasaki',
                'NR' => 'Nara',
                'NI' => 'Niigata',
                'OT' => 'Oita',
                'OY' => 'Okayama',
                'ON' => 'Okinawa',
                'OS' => 'Osaka',
                'SG' => 'Saga',
                'ST' => 'Saitama',
                'SH' => 'Shiga',
                'SM' => 'Shimane',
                'SZ' => 'Shizuoka',
                'TC' => 'Tochigi',
                'TS' => 'Tokushima',
                'TK' => 'Tokyo',
                'TT' => 'Tottori',
                'TY' => 'Toyama',
                'WK' => 'Wakayama',
                'YT' => 'Yamagata',
                'YC' => 'Yamaguchi',
                'YN' => 'Yamanashi',
            ],
        ];
    }
}
