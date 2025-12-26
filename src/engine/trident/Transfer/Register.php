<?php

namespace AwardWallet\Engine\trident\Transfer;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;

    public static $titles = [
        'Mr.'  => 'Mr.',
        'Ms.'  => 'Ms.',
        'Dr.'  => 'Dr.',
        'Mrs.' => 'Mrs.',
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
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AG' => 'Antigua And Barbuda',
        'AE' => 'Arab Emirates',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BZ' => 'Belize',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia',
        'BA' => 'Bosnia And Herzegovina',
        'BW' => 'Botswana',
        'BR' => 'Brazil',
        'BN' => 'Brunei',
        'BG' => 'Bulgaria',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'KY' => 'Cayman Islands',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China P.Rep.',
        'HK' => 'China,Hong Kong S.A.R.',
        'CC' => 'Cocos Islands',
        'CO' => 'Colombia',
        'CG' => 'Congo',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DM' => 'Dominica',
        'DO' => 'Dominican Repl.',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands (Malvinas)',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'PF' => 'Fr. Polynesia',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia (Republic Of)',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GR' => 'Greece',
        'GL' => 'Greenland',
        'GD' => 'Grenada',
        'GP' => 'Guadeloupe',
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HN' => 'Honduras',
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
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Laos',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LR' => 'Liberia',
        'LY' => 'Libya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macau',
        'MK' => 'Macedonia, Former Yugoslav Rep.',
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
        'MX' => 'Mexico',
        'MD' => 'Moldova, Republic Of',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar',
        'NA' => 'Namibia',
        'NP' => 'Nepal',
        'NL' => 'Netherlands',
        'NC' => 'New Caledonia',
        'PG' => 'New Guinea',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'NF' => 'Norfolk Island',
        'KP' => 'North Korea',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PA' => 'Panama',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'QA' => 'Qatar',
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'KN' => 'Saint Kitts And Nevis',
        'SM' => 'San Marino',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'ZA' => 'South Africa',
        'KR' => 'South Korea',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'LC' => 'St. Lucia',
        'VC' => 'St. Vincent/Grenadines',
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
        'BS' => 'The Bahamas',
        'TO' => 'Tonga',
        'TT' => 'Trinidad/Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TC' => 'Turks And Caicos Islands',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'GB' => 'United Kingdom',
        'UY' => 'Uruguay',
        'US' => 'US',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu (New Hebrides)',
        'VA' => 'Vatican City State (Holy See)',
        'VE' => 'Venezuela',
        'VN' => 'Viet Nam',
        'VG' => 'Virgin (British) Islands',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    public static $countriesMap = [
        'AF' => 1,
        'AL' => 6,
        'DZ' => 48,
        'AD' => 2,
        'AO' => 9,
        'AI' => 5,
        'AG' => 4,
        'AE' => 3,
        'AR' => 10,
        'AM' => 7,
        'AW' => 13,
        'AU' => 12,
        'AT' => 11,
        'AZ' => 14,
        'BH' => 20,
        'BD' => 17,
        'BB' => 16,
        'BY' => 27,
        'BE' => 18,
        'BZ' => 28,
        'BM' => 21,
        'BT' => 193,
        'BO' => 23,
        'BA' => 15,
        'BW' => 26,
        'BR' => 24,
        'BN' => 22,
        'BG' => 19,
        'BI' => 190,
        'KH' => 91,
        'CM' => 36,
        'CA' => 29,
        'KY' => 96,
        'TD' => 164,
        'CL' => 35,
        'CN' => 37,
        'HK' => 73,
        'CC' => 30,
        'CO' => 38,
        'CG' => 31,
        'CK' => 34,
        'CR' => 39,
        'HR' => 75,
        'CU' => 41,
        'CY' => 42,
        'CZ' => 43,
        'DK' => 45,
        'DM' => 46,
        'DO' => 47,
        'EC' => 49,
        'EG' => 51,
        'SV' => 160,
        'EE' => 50,
        'ET' => 53,
        'FK' => 56,
        'FO' => 57,
        'FJ' => 55,
        'FI' => 54,
        'PF' => 137,
        'FR' => 58,
        'GF' => 63,
        'GA' => 59,
        'GM' => 67,
        'GE' => 62,
        'DE' => 44,
        'GH' => 64,
        'GI' => 65,
        'GR' => 70,
        'GL' => 66,
        'GD' => 61,
        'GP' => 69,
        'GT' => 71,
        'GN' => 68,
        'GY' => 72,
        'HT' => 76,
        'HN' => 74,
        'HU' => 77,
        'IS' => 84,
        'IN' => 81,
        'ID' => 78,
        'IR' => 83,
        'IQ' => 82,
        'IE' => 79,
        'IL' => 80,
        'IT' => 85,
        'CI' => 33,
        'JM' => 86,
        'JP' => 88,
        'JO' => 87,
        'KZ' => 97,
        'KE' => 89,
        'KW' => 95,
        'KG' => 90,
        'LA' => 98,
        'LV' => 106,
        'LB' => 99,
        'LR' => 103,
        'LY' => 107,
        'LI' => 101,
        'LT' => 104,
        'LU' => 105,
        'MO' => 116,
        'MK' => 113,
        'MG' => 111,
        'MW' => 121,
        'MY' => 123,
        'MV' => 187,
        'ML' => 114,
        'MT' => 119,
        'MH' => 112,
        'MQ' => 117,
        'MR' => 118,
        'MU' => 120,
        'MX' => 122,
        'MD' => 110,
        'MC' => 109,
        'MN' => 192,
        'MA' => 108,
        'MZ' => 191,
        'MM' => 115,
        'NA' => 124,
        'NP' => 132,
        'NL' => 130,
        'NC' => 125,
        'PG' => 138,
        'NZ' => 133,
        'NI' => 129,
        'NE' => 126,
        'NG' => 128,
        'NF' => 127,
        'KP' => 93,
        'NO' => 131,
        'OM' => 134,
        'PK' => 140,
        'PW' => 143,
        'PA' => 135,
        'PY' => 144,
        'PE' => 136,
        'PH' => 139,
        'PL' => 141,
        'PT' => 142,
        'QA' => 145,
        'RO' => 146,
        'RU' => 147,
        'KN' => 92,
        'SM' => 156,
        'SA' => 148,
        'SN' => 157,
        'SC' => 194,
        'SL' => 155,
        'SG' => 152,
        'SK' => 154,
        'SI' => 153,
        'SB' => 149,
        'ZA' => 183,
        'KR' => 94,
        'ES' => 52,
        'LK' => 102,
        'LC' => 100,
        'VC' => 176,
        'SD' => 150,
        'SR' => 158,
        'SZ' => 162,
        'SE' => 151,
        'CH' => 32,
        'SY' => 161,
        'TW' => 169,
        'TJ' => 189,
        'TZ' => 170,
        'TH' => 165,
        'BS' => 25,
        'TO' => 188,
        'TT' => 168,
        'TN' => 166,
        'TR' => 167,
        'TC' => 163,
        'UG' => 172,
        'UA' => 171,
        'GB' => 60,
        'UY' => 173,
        'US' => 186,
        'UZ' => 174,
        'VU' => 180,
        'VA' => 175,
        'VE' => 177,
        'VN' => 179,
        'VG' => 178,
        'YE' => 181,
        'ZM' => 184,
        'ZW' => 185,
    ];

    public function InitBrowser()
    {
        $this->UseSelenium();
    }

    public function getRegisterFields()
    {
        return [
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email address',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password',
                'Required' => true,
            ],
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
            'MiddleName' => [
                'Type'     => 'string',
                'Caption'  => 'Middle Name',
                'Required' => false,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name',
                'Required' => true,
            ],
            'PreferredName' => [
                'Type'     => 'string',
                'Caption'  => 'Preferred name on membership card',
                'Required' => true,
            ],
            'Gender' => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => self::$genders,
            ],
            'Nationality' => [
                'Type'     => 'string',
                'Caption'  => 'Nationality',
                'Required' => true,
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
            'AddressType' => [
                'Type'     => 'string',
                'Caption'  => 'Mailing Address Type',
                'Required' => true,
                'Options'  => self::$addressTypes,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country code',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'StateOrProvince' => [
                'Type'     => 'string',
                'Caption'  => 'State/Province name',
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
                'Required' => true,
            ],
            'Company' => [
                'Type'     => 'string',
                'Caption'  => 'Company name, required for work address',
                'Required' => false,
            ],
            'JobTitle' => [
                'Type'     => 'string',
                'Caption'  => 'Job Title',
                'Required' => false,
            ],
            'PhoneCountryCodeNumeric' => [
                'Type'     => 'string',
                'Caption'  => 'Mobile phone country code',
                'Required' => true,
            ],
            'PhoneAreaCode' => [
                'Type'     => 'string',
                'Caption'  => 'Mobile phone area code',
                'Required' => true,
            ],
            'PhoneLocalNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Mobile phone number',
                'Required' => true,
            ],
        ];
    }

    public function registerAccount(array $fields)
    {
        $fields["CountryID"] = $this->regCountry($fields['Country']);
        $this->logger->debug('Mapped standard country code "' . $fields['Country'] . '" to provider code "' . $fields["CountryID"] . '"');
        $this->http->GetURL('http://www.tridentprivilege.com/Registration.aspx');
        $this->waitForElement(\WebDriverBy::xpath('//input[@name = "ctl00$ContentPlaceHolder1$txt_EmailID"]'))->sendKeys($fields['Email']);
        $this->waitForElement(\WebDriverBy::xpath('//input[@name = "ctl00$ContentPlaceHolder1$txt_RetypeEmailID"]'))->sendKeys($fields['Email']);
        $this->waitForElement(\WebDriverBy::xpath('//input[@name = "ctl00$ContentPlaceHolder1$btn_Go"]'))->click();
        sleep(2);

        try {
            $alert = $this->driver->switchTo()->alert();
            $error = $alert->getText();
            $alert->dismiss();

            throw new \UserInputError($error); // Is it always user input error?
        } catch (\NoAlertOpenException $e) {
        }

        if (!$this->waitForElement(\WebDriverBy::name('ctl00$ContentPlaceHolder1$ddl_Salutation'), 10)) {
            $this->saveResponse();

            if ($error = $this->http->FindSingleNode('//span[@id="ctl00_ContentPlaceHolder1_lbl_ErrorMsg"]')) {
                throw new \UserInputError($error);
            }
            $this->http->Log('didn\'t reach info form', LOG_LEVEL_ERROR);

            return false;
        }
        $fields['FullPhone'] = $fields['PhoneCountryCodeNumeric'] . $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];

        if ($fields['BirthMonth'] < 1 || $fields['BirthMonth'] > 12) {
            throw new \UserInputError('Invalid BirthMonth');
        }
        $fields['BirthMonth'] = \DateTime::createFromFormat('!m', $fields['BirthMonth'])->format('M');

        if (strlen($fields['BirthDay']) < 2) {
            $fields['BirthDay'] = '0' . $fields['BirthDay'];
        }

        foreach ([
            'ctl00$ContentPlaceHolder1$ddl_Salutation' => 'Title',
            'ctl00$ContentPlaceHolder1$txt_FirstName' => 'FirstName',
            'ctl00$ContentPlaceHolder1$txt_MiddleName' => 'MiddleName',
            'ctl00$ContentPlaceHolder1$txt_LastName' => 'LastName',
            'ctl00$ContentPlaceHolder1$txt_CardName' => 'PreferredName',
            'ctl00$ContentPlaceHolder1$txt_Organization' => 'Company',
            'ctl00$ContentPlaceHolder1$txt_Designation' => 'JobTitle',
            'ctl00$ContentPlaceHolder1$ddl_DOBDay' => 'BirthDay',
            'ctl00$ContentPlaceHolder1$ddl_DOBMon' => 'BirthMonth',
            'ctl00$ContentPlaceHolder1$ddl_DOBYear' => 'BirthYear',
            'ctl00$ContentPlaceHolder1$txt_Address1' => 'AddressLine1',
            'ctl00$ContentPlaceHolder1$txt_Address2' => 'AddressLine2',
            'ctl00$ContentPlaceHolder1$txt_Address3' => 'AddressLine3',
            'ctl00$ContentPlaceHolder1$txt_City' => 'City',
            'ctl00$ContentPlaceHolder1$txt_State' => 'StateOrProvince',
            'ctl00$ContentPlaceHolder1$ddl_Country' => 'CountryID',
            'ctl00$ContentPlaceHolder1$txt_PostalCode' => 'PostalCode',
            'ctl00$ContentPlaceHolder1$txt_Nationality' => 'Nationality',
            'ctl00$ContentPlaceHolder1$txt_Mobile' => 'FullPhone',
            'ctl00$ContentPlaceHolder1$txt_Password' => 'Password',
            'ctl00$ContentPlaceHolder1$txt_ConfirmPassword' => 'Password',
        ] as $n => $f) {
            if (strlen(trim($fields[$f])) > 0) {
                $element = $this->waitForElement(\WebDriverBy::name($n));

                if (strcasecmp($element->getTagName(), 'select') === 0) {
                    try {
                        (new \WebDriverSelect($element))->selectByValue(trim($fields[$f]));
                    } catch (\NoSuchElementException $e) {
                        throw new \UserInputError('Invalid ' . $f);
                    }
                } else {
                    $element->sendKeys($fields[$f]);
                }
                $this->http->Log('set input ' . $n . ' value to ' . $fields[$f]);
                $this->waitForElement(\WebDriverBy::name($n)); // Due to some bug value sometimes is not set if second waitForElement is not called
            }
        }

        foreach ([
            'Gender' => [
                'M' => 'ctl00_ContentPlaceHolder1_rbtl_Gender_0',
                'F' => 'ctl00_ContentPlaceHolder1_rbtl_Gender_1',
            ],
            'AddressType' => [
                'H' => 'ctl00_ContentPlaceHolder1_rdlMailingAdd_1',
                'B' => 'ctl00_ContentPlaceHolder1_rdlMailingAdd_0',
            ],
        ] as $f => $n) {
            $this->waitForElement(\WebDriverBy::id($n[$fields[$f]]))->click();
            $this->http->Log('clicked ' . $n[$fields[$f]]);
        }
        $this->waitForElement(\WebDriverBy::id('ctl00_ContentPlaceHolder1_chk_tnc'))->click();
        $this->saveResponse();
        $this->http->Log('clicking submit');
        $this->waitForElement(\WebDriverBy::id('ctl00_ContentPlaceHolder1_btn_Submit'))->click();
        sleep(2);

        try {
            $alert = $this->driver->switchTo()->alert();
            $error = $alert->getText();
            $alert->dismiss();

            throw new \UserInputError($error); // Is it always user input error?
        } catch (\NoAlertOpenException $e) {
        }

        if (!$skipButton = $this->waitForElement(\WebDriverBy::id('ctl00_ContentPlaceHolder1_btn_Skip'), 10)) {
            $this->saveResponse();

            if ($error = $this->http->FindSingleNode('//span[@class="errmessage"]/text()[1]')) {
                throw new \UserInputError($error);
            } // Is it always user input error?
            else {
                $this->http->Log('didn\'t get to preferences form', LOG_LEVEL_ERROR);

                return false;
            }
        }
        $this->http->Log('clicking skip preferences');
        $skipButton->click();

        if (!$this->waitForElement(\WebDriverBy::id('ctl00_ContentPlaceHolder1_lblembershipNo'), 10)) {
            $this->http->Log('didn\'t get to card info', LOG_LEVEL_ERROR);

            return false;
        }
        $this->saveResponse();

        if ($number = $this->http->FindSingleNode('//span[@id="ctl00_ContentPlaceHolder1_lblembershipNo"]')) {
            $this->http->Log('got membership number');
            $this->ErrorMessage = 'Welcome to Trident Privilege, the frequent guest programme of Trident Hotels. Your Membership Number is ' . $number;

            return true;
        }

        return false;
    }

    protected function regCountry($code)
    {
        // ids from registration form
        $cs = self::$countriesMap;

        if (isset($cs[$code])) {
            return $cs[$code];
        } else {
            throw new \UserInputError('Invalid country code');
        }
    }
}
