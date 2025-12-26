<?php

namespace AwardWallet\Engine\shangrila\Transfer;

class Register extends \TAccountCheckerShangrila
{
    public static $titles = [
        'Mr'        => 'Mr',
        'Ms'        => 'Ms',
        'Mrs'       => 'Mrs',
        'Sir'       => 'Sir',
        'Dr'        => 'Dr',
        'Mdm'       => 'Mdm',
        'Professor' => 'Professor',
    ];

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $addressTypes = [
        'H' => 'Home',
        'B' => 'Business',
    ];

    public static $countries = [
        'AF' => 'Afghanistan',
        'AX' => 'Aland Islands',
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
        'BQ' => 'Bonaire, Saint Eustatius and Saba',
        'BA' => 'Bosnia Herzigovina',
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
        'CF' => 'Central African Rep.',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (Keeling) Islands',
        'CO' => 'Columbia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CD' => 'Congo, Democratic Republic of',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'CI' => 'Cote D\'lvoire (Ivory Coast)',
        'HR' => 'Croatia (Hrvatska)',
        'CU' => 'Cuba',
        'CW' => 'Curaçao',
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
        'FK' => 'Falkland Islands (Malvinas)',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji Islands',
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
        'GP' => 'Guadaloupe',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GG' => 'Guernsey',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard and McDonald Islands',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong SAR',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran, Islamic Republic of',
        'IQ' => 'Iraq',
        'IE' => 'Ireland(Eire)',
        'IM' => 'Isle Of Man',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JE' => 'Jersey',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KP' => 'Korea, North',
        'KR' => 'Korea, South',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Laos',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libyan Arab Jamahiriya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macau SAR',
        'MK' => 'Macedonia, Former Yugoslav',
        'MG' => 'Madagascar',
        'CN' => 'Mainland China',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia, Federal State of',
        'MD' => 'Moldova, Republic of',
        'MC' => 'Monaco',
        'MN' => 'Mongolia (Outer)',
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
        'PW' => 'Palau Island',
        'PS' => 'Palestinian Territory, Occupied',
        'PA' => 'Panama',
        'PG' => 'Papua-New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'MV' => 'Republic of Maldives',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'BL' => 'Saint Barthélemy',
        'SH' => 'Saint Helena',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'MF' => 'Saint Martin (French part)',
        'PM' => 'Saint Pierre and Miguelon',
        'VC' => 'Saint-Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SX' => 'Sint Maarten',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'GS' => 'South Georgia and South Sandwich',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Surinam',
        'SJ' => 'Svalbard and Jan Mayen',
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
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UM' => 'United States Minor Outlying Islands',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'VG' => 'Virgin Islands, British',
        'VI' => 'Virgin Islands, U.S.',
        'WF' => 'Wallis and Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    public static $phoneTypes = [
        'H' => 'Home',
        'B' => 'Business',
        'M' => 'Mobile',
    ];

    public static $preferredLanguages = [
        'en' => 'English',
        'ja' => 'Japanese',
        'zh' => 'Chinese',
    ];

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->GetURL('https://www.shangri-la.com/corporate/golden-circle/joingc/');

        if (!$this->http->ParseForm('aspnetForm')) {
            return false;
        }
        $types = ['H' => 'HOME', 'B' => 'BUSINESS', 'M' => 'MOBILE'];
        $fields['PhoneType'] = $types[$fields['PhoneType']];
        $fields['AddressType'] = $types[$fields['AddressType']];
        $fields['FullPhone'] = $fields['PhoneCountryCodeNumeric'] . $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];
        $langs = ['en' => 'English', 'ja' => 'Japanese', 'zh' => 'Chinese'];
        $fields['PrefLanguage'] = $langs[$fields['PreferredLanguage']];
        $genders = ['M' => 'MALE', 'F' => 'FEMALE'];
        $fields['Gender'] = $genders[$fields['Gender']];

        foreach ([
            'AddressLine1',
            'AddressLine2',
            'AddressLine3',
            'City',
            'Company',
            'JobTitle',
        ] as $f) {
            $fields[$f] = ucwords($fields[$f]);
        }
        $map = [
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$ddlTitle'               => 'Title',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$tbLname'                => 'LastName',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$tbFname'                => 'FirstName',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$tbNameOnCard'           => 'PreferredName',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$pdcDOBCalendar$ddDay'   => 'BirthDay',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$pdcDOBCalendar$ddMonth' => 'BirthMonth',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$pdcDOBCalendar$ddYear'  => 'BirthYear',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$tbEmail1'               => 'Email',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$tbEmail2'               => 'Email',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$tbAddress1'             => 'AddressLine1',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$tbAddress2'             => 'AddressLine2',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$tbAddress3'             => 'AddressLine3',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$tbCity'                 => 'City',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$ddlCountry'             => 'Country',
            //			'ctl00$ContentPlaceHolder1$ctlGCEnrol$ddlState' => 'StateOrProvince',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$tbZip'          => 'PostalCode',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$rbAddressType'  => 'AddressType',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$tbTel1'         => 'FullPhone',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$ddlTeltype1'    => 'PhoneType',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$tbPwd'          => 'Password',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$tbPwd2'         => 'Password',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$rbPrefLanguage' => 'PrefLanguage',
            'ctl00$ContentPlaceHolder1$ctlGCEnrol$rbGender'       => 'Gender',
        ];

        if ($fields['AddressType'] == 'BUSINESS') {
            $map += [
                'ctl00$ContentPlaceHolder1$ctlGCEnrol$tbCname'    => 'Company',
                'ctl00$ContentPlaceHolder1$ctlGCEnrol$tbPosition' => 'JobTitle',
            ];
        }

        foreach ($map as $n => $f) {
            $this->http->Form[$n] = $fields[$f];
        }
        unset($this->http->Form['ctl00$ContentPlaceHolder1$ctlGCEnrol$cbGCNewsletter']);
        unset($this->http->Form['ctl00$ContentPlaceHolder1$ctlGCEnrol$cbNonGCNewsletter']);
        unset($this->http->Form['ctl00$ContentPlaceHolder1$ctlGCEnrol$cbGCStatement']);
        $form = $this->http->Form;

        // getting states
        $this->http->Form['__EVENTTARGET'] = 'ctl00$ContentPlaceHolder1$ctlGCEnrol$ddlCountry';
        $this->http->Form['__ASYNCPOST'] = 'true';
        $this->http->Form['ctl00$ScriptManager1'] = 'ctl00$ContentPlaceHolder1$ctlGCEnrol$upPnlCountryState|ctl00$ContentPlaceHolder1$ctlGCEnrol$ddlCount';

        if (!$this->http->PostForm()) {
            return false;
        }
        $states = $this->http->FindNodes('//select[@id="ctl00_ContentPlaceHolder1_ctlGCEnrol_ddlState"]/option[@value!=""]/@value');

        if (count($states) > 0 && !in_array($fields['StateOrProvince'], $states)) {
            throw new \UserInputError('Invalid state/province code');
        }
        $form['__VIEWSTATE'] = $this->http->FindPreg('/__VIEWSTATE\|([^\|]+)\|/');
        $form['__EVENTVALIDATION'] = $this->http->FindPreg('/__EVENTVALIDATION\|([^\|]+)\|/');
        $this->http->Form = $form;
        $this->http->Form['ctl00$ContentPlaceHolder1$ctlGCEnrol$ddlState'] = $fields['StateOrProvince'];

        $this->http->Form['__EVENTTARGET'] = 'ctl00$ContentPlaceHolder1$ctlGCEnrol$btnStep1Next';

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($error = $this->http->FindSingleNode('//div[@id="ctl00_ContentPlaceHolder1_ctlGCEnrol_ValSumMessagesPart1"]/text()[1]')) {
            throw new \UserInputError($error);
        } // Is it always user input error?
        $retries = 3;

        do {
            $retries--;

            if (!$this->http->FindSingleNode('//h2[contains(text(), "Golden Circle Enrolment")]') || !$this->http->ParseForm('aspnetForm')) {
                return false;
            }
            $this->http->Form['ctl00$ContentPlaceHolder1$ctlGCEnrol$cbAgreeTerms'] = 'on';
            $this->http->Form['ctl00$ContentPlaceHolder1$ctlGCEnrol$cbAgreePersonalData'] = 'on';
            $captcha = $this->parseRegCaptcha();

            if (empty($captcha)) {
                return false;
            }
            $this->http->Form['ctl00$ContentPlaceHolder1$ctlGCEnrol$CaptchaControl1'] = $captcha;
            $this->http->Form['__EVENTTARGET'] = 'ctl00$ContentPlaceHolder1$ctlGCEnrol$btnEnroll';
            $this->http->FormURL = 'https://www.shangri-la.com/corporate/golden-circle/joingc/';

            if (!$this->http->PostForm()) {
                return false;
            }

            if ($this->http->FindSingleNode('//div[contains(text(), "The characters you have entered do not match the image.")]')) {
                $this->http->Log('invalid captcha', LOG_LEVEL_ERROR);

                continue;
            }
            $success = 'Thank you for joining our Golden Circle family.';

            if ($this->http->FindSingleNode('//span[contains(text(), "' . $success . '")]')) {
                $number = $this->http->FindSingleNode('//span[@id="ctl00_ContentPlaceHolder1_lblMemberId"]');
                $this->http->Log('success. found number ' . $number);
                $this->ErrorMessage = $success . ' Membership number: ' . $number;

                return true;
            }

            return false;
        } while ($retries > 0);
        $this->http->Log('ran out of retries', LOG_LEVEL_ERROR);

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Title' => [
                'Type'     => 'string',
                'Caption'  => 'Title',
                'Required' => true,
                'Options'  => self::$titles,
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
            'PreferredName' => [
                'Type'     => 'string',
                'Caption'  => 'Preferred Name on Membership Card',
                'Required' => true,
            ],
            'Gender' => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => self::$genders,
            ],
            'BirthDay' => [
                'Type'     => 'integer',
                'Caption'  => 'Day of Birth Date',
                'Required' => true,
            ],
            'BirthMonth' => [
                'Type'     => 'integer',
                'Caption'  => 'Month of Birth Date',
                'Required' => true,
            ],
            'BirthYear' => [
                'Type'     => 'integer',
                'Caption'  => 'Year of Birth Date',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'AddressType' => [
                'Type'     => 'string',
                'Caption'  => 'Mailing Address Type',
                'Required' => true,
                'Options'  => self::$addressTypes,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => '2 letter country code',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'StateOrProvince' => [
                'Type'     => 'string',
                'Caption'  => 'State/Province, required for United States, Canada, United Kingdom, Australia, China, India, Ireland, Japan, Malaysia, Russia, United Arab Emirates',
                'Required' => false,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'City',
                'Required' => true,
            ],
            'AddressLine1' => [
                'Type'     => 'string',
                'Caption'  => 'Address Line 1',
                'Required' => true,
            ],
            'AddressLine2' => [
                'Type'     => 'string',
                'Caption'  => 'Address Line 2',
                'Required' => false,
            ],
            'AddressLine3' => [
                'Type'     => 'string',
                'Caption'  => 'Address Line 3',
                'Required' => false,
            ],
            'PostalCode' => [
                'Type'     => 'string',
                'Caption'  => 'Postal Code',
                'Required' => false,
            ],
            'Company' => [
                'Type'     => 'string',
                'Caption'  => 'Company name for business mailing address',
                'Required' => false,
            ],
            'JobTitle' => [
                'Type'     => 'string',
                'Caption'  => 'Job Title for business mailing address',
                'Required' => false,
            ],
            'PhoneType' => [
                'Type'     => 'string',
                'Caption'  => 'Primary phone type',
                'Required' => true,
                'Options'  => self::$phoneTypes,
            ],
            'PhoneCountryCodeNumeric' => [
                'Type'     => 'string',
                'Caption'  => 'Phone country code',
                'Required' => true,
            ],
            'PhoneAreaCode' => [
                'Type'     => 'string',
                'Caption'  => 'Phone area code',
                'Required' => true,
            ],
            'PhoneLocalNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone number',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password, at least six characters, with a combination of English letters (a - z, A - Z) and numeric digits (0 - 9). At least one of the characters must be a numeric digit',
                'Required' => true,
            ],
            'PreferredLanguage' => [
                'Type'     => 'string',
                'Caption'  => 'Preferred language',
                'Required' => true,
                'Options'  => self::$preferredLanguages,
            ],
        ];
    }

    protected function parseRegCaptcha()
    {
        $this->http->Log('parsing captcha');
        $captcha = null;

        if ($src = $this->http->FindSingleNode('//img[contains(@src, "CaptchaImage.aspx")]/@src')) {
            $this->http->NormalizeURL($src);
            $captcha = null;
            $this->http->Log("Loading IMG...");
            $file = $this->http->DownloadFile($src, "jpg");
            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);

            try {
                $captcha = trim($recognizer->recognizeFile($file));
            } catch (\CaptchaException $e) {
                $this->http->Log("exception: " . $e->getMessage(), LOG_LEVEL_ERROR);

                if (strstr($e->getMessage(), "ERROR_ZERO_BALANCE")) {
                    $this->sendNotification("WARNING! " . $recognizer->domain . " - balance is null");
                }

                throw new \EngineError(self::CAPTCHA_ERROR_MSG);
            }
            unlink($file);
        }

        return $captcha;
    }
}
