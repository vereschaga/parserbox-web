<?php

namespace AwardWallet\Engine\turkish\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use ProxyList;

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $addressTypes = [
        'H' => 'Home',
        'B' => 'Business',
    ];

    public static $countries = [
        'TR' => 'Turkey',
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua And Barbuda',
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
        'BA' => 'Bosna And Herzegovina',
        'BW' => 'Botswana',
        'BR' => 'Brazil',
        'BN' => 'Brunei Darussalam',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CV' => 'Cape Verde Islands',
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
        'CD' => 'Congo, Democratic Republic Of',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'CI' => 'Cote D\'Ivoire',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republc',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Island',
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
        'GN' => 'Guinea',
        'GW' => 'Guinea Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
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
        'KP' => 'Korea, Democratic Peoples Republic',
        'KR' => 'Korea, Republic Of',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao People Democratic Republic',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libyan Arab Jamahiriya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macao, (Sar) China',
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
        'FM' => 'Micronesia',
        'MD' => 'Moldovia',
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
        'PW' => 'Palau Islands',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion Island',
        'RO' => 'Roumania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'KN' => 'Saint Kitts And Nevis',
        'LC' => 'Saint Lucia',
        'VC' => 'Saint Vincent Ant The Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome And Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovak Republic',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SH' => 'St. Helena',
        'PM' => 'St. Pierre And Miquelon',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
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
        'TO' => 'Tonga Island',
        'TT' => 'Trinidad And Tobago',
        'TN' => 'Tunisia',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks And Caicos Islands',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States Of America',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'WF' => 'Wallis And Futuna Islands',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    public static $inputFieldsMap = [
        'Gender'                  => 'sex',
        'FirstName'               => 'name',
        'LastName'                => 'surname',
        'BirthDay'                => 'DOBDay',
        'BirthMonth'              => 'DOBMonth',
        'BirthYear'               => 'DOBYear',
        'PreferredLanguage'       => 'language_pref',
        'PIN'                     => ['pin_number', 'pin_confirm'],
        'SecurityQuestionType1'   => 'security_question',
        'SecurityQuestionAnswer1' => 'security_answer',
        'AddressType'             => 'communicationAddressPreferance',
        'AddressLine1'            => 'homeAddress',
        'Country'                 => 'homeCountryCode',
        'City'                    => 'homeCity',
        'District'                => 'homeDistrict', // other = 9999
        'PhoneCountryCodeNumeric' => ['hometelCountryCode', 'mobiletelCountryCode'],
        'PhoneAreaCode'           => ['hometelArea', 'mobiletelArea'],
        'PhoneLocalNumber'        => ['hometel', 'mobiletel'],
        'SMSNotification'         => 'wantSMS',
        'Email'                   => ['e_mail', 'e_mail_retype'],
    ];

    public function InitBrowser()
    {
        $this->UseCurlBrowser();
        $this->AccountFields['BrowserState'] = null;

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP());
        } else {
            $this->http->SetProxy('localhost:8000');
        } // This provider should be tested via proxy even locally
    }

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;

        $this->checkValues($fields);

        $this->http->GetURL('https://www4.thy.com/tkmiles/membersignin.tk?lang=en&method=initiation');
        $status = $this->http->ParseForm('memberSignInForm');

        if (!$status) {
            $this->http->Log('Failed to parse create account form');

            return false;
        }

        $fields['BirthDay'] = ($fields['BirthDay'] + 0) < 10 ? '0' . ($fields['BirthDay'] + 0) : $fields['BirthDay'];
        $fields['BirthMonth'] = ($fields['BirthMonth'] + 0) < 10 ? '0' . ($fields['BirthMonth'] + 0) : $fields['BirthMonth'];

        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
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
        unset($this->http->Form['wantSMS']);
        $additionalValues = [
            'language_pref'      => 'EN',
            'captcha_answer'     => $this->parseCaptcha(),
            'confirmMyInfo'      => '1',
            'termsandconditions' => '1',
            'nationality'        => 'XX',
        ];

        foreach ($additionalValues as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }

        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post create account form');

            return false;
        }

        if ($successMessage = $this->http->FindSingleNode("//font[contains(text(),'membership is created')]")) {
            $this->ErrorMessage = $this->http->FindSingleNode("//font[contains(text(),'send to e-mail address')]");

            return true;
        }

        if ($errors = $this->http->FindNodes("//div[contains(@class,'frm_alert_box')]//li")) {
            $errMsg = implode(',', $errors);

            throw new \UserInputError($errMsg); // Is it always user input error?
        }

        if ($errMsg = $this->http->FindSingleNode("//div[contains(@class,'frm_alert_box')]//p")) {
            throw new \UserInputError($errMsg);
        } // Is it always user input error?

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Gender' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Title',
                    'Required' => true,
                    'Options'  => self::$genders,
                ],
            'FirstName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Name',
                    'Required' => true,
                ],
            'LastName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Surname',
                    'Required' => true,
                ],
            'BirthDay' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Day of Birth Date',
                    'Required' => true,
                ],
            'BirthMonth' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Month of Birth Date',
                    'Required' => true,
                ],
            'BirthYear' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Year of Birth Date',
                    'Required' => true,
                ],
            'PIN' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'PIN (must be formed 6 digits, should not include numbers in the birthdate as sequence, the same consecutive and sequential 3 number and the same number 3 times or more)',
                    'Required' => true,
                ],
            'SecurityQuestionType1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Security Question',
                    'Required' => true,
                ],
            'SecurityQuestionAnswer1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Security Answer',
                    'Required' => true,
                ],
            'AddressType' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Type',
                    'Required' => true,
                    'Options'  => self::$addressTypes,
                ],
            'AddressLine1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address',
                    'Required' => true,
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
            'District' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'District (required for Turkey)',
                    'Required' => false,
                ],
            'PhoneCountryCodeNumeric' =>
                [
                    'Type'     => 'string',
                    'Caption'  => '1-3-number Phone Country Code',
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
                    'Caption'  => 'Email',
                    'Required' => true,
                ],
        ];
    }

    public static function cities()
    {
        $citiesPlain = [];

        foreach (self::citiesByCountry() as $c) {
            $citiesPlain = array_merge($citiesPlain, $c);
        }

        return $citiesPlain;
    }

    public static function citiesByCountry()
    {
        $path = dirname(__FILE__) . '/cities.json';

        if (!file_exists($path)) {
            return false;
        }
        $json = file_get_contents($path);
        $cities = json_decode($json, true);

        return $cities;
    }

    public static function districtsByCity()
    {
        $path = dirname(__FILE__) . '/districts.json';

        if (!file_exists($path)) {
            return false;
        }
        $json = file_get_contents($path);
        $cities = json_decode($json, true);

        return $cities;
    }

    protected function checkValues($fields)
    {
        if (!isset($fields['District'])) {
            $fields['District'] = '';
        }

        $fields['District'] = $fields['Country'] !== 'TR' ? '9999' : $fields['District'];

        if ($fields['Country'] === 'TR' && (!isset($fields['District']) || $fields['District'] === '')) {
            throw new \UserInputError('District is required field for Turkey');
        }
    }

    protected function parseCaptcha()
    {
        $captcha = $this->http->FindSingleNode("//img[contains(@src,'Captcha')]/@src");

        if (!$captcha) {
            return false;
        }
        $this->http->NormalizeURL($captcha);
        $file = $this->http->DownloadFile($captcha, "jpg");
//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);

        try {
            // parameters https://rucaptcha.com/api-rucaptcha
            $captcha = trim($this->recognizer->recognizeFile($file, ["regsense" => 1]));
        } catch (\CaptchaException $e) {
            $this->http->Log("exception: " . $e->getMessage());
            // Notifications
            if (strstr($e->getMessage(), "ERROR_ZERO_BALANCE")) {
                $this->sendNotification("WARNING! " . $this->recognizer->domain . " - balance is null");

                throw new \EngineError(self::CAPTCHA_ERROR_MSG);
            }

            if ($e->getMessage() == 'server returned error: ERROR_CAPTCHA_UNSOLVABLE'
                || $e->getMessage() == 'timelimit (60) hit'
                || $e->getMessage() == 'slot not available') {
                $this->http->Log("parseCaptcha", LOG_LEVEL_ERROR);
//                $this->retry();
            }

            return false;
        }
        unlink($file);

        return $captcha;
    }
}
