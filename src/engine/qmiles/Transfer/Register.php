<?php

// case #12104
// TODO: refactor out retry logic

namespace AwardWallet\Engine\qmiles\Transfer;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper {
        waitForElement as traitWaitForElement;
    }

    public static $titles = [
        'BRIG' => 'Brigadier',
        'CAPT' => 'Captain',
        'COL'  => 'Colonel',
        'DR'   => 'Dr.',
        'GEN'  => 'General',
        'MISS' => 'Miss',
        'MR'   => 'Mr',
        'MRS'  => 'Mrs',
        'MS'   => 'Ms',
        'MSTR' => 'MSTR',
        'PROF' => 'Professor',
        'SHK'  => 'Sheikh',
        'SHKA' => 'SHKA',
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
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla Leeward Island',
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
        'BA' => 'Bosnia Herzegovina',
        'BW' => 'Botswana',
        'BR' => 'Brazil',
        'VG' => 'British Virgin Islands',
        'BN' => 'Brunei Darussalam',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia Riel',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CV' => 'Cape Verde Isl.',
        'KY' => 'Cayman Isl.',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (Keeling) Island',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CD' => 'Congo Dem. Rep.',
        'CK' => 'Cook Island',
        'CR' => 'Costa Rica',
        'CI' => 'Cote D Ivoire',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CW' => 'CW',
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
        'KP' => 'Korea North',
        'KR' => 'Korea South',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao P.Dem.Rep.',
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
        'MH' => 'Marshall Island',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'UM' => 'Minor U.S. Outlying Islands',
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
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'KV' => 'Republic of Kosova',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'KN' => 'Saint Kitts and Nevis',
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
        'SS' => 'South Sudan',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SX' => 'SX',
        'PM' => 'St Pierre And Miquelon',
        'VC' => 'St Vincent & Grenadines',
        'SH' => 'SH',
        'LC' => 'St. Lucia',
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
        'TL' => 'Timor-Leste',
        'TG' => 'Togo',
        'TO' => 'Tonga',
        'TT' => 'Trinidad And Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks And Caicos Islands',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States of America',
        'UY' => 'Uruguay',
        'VI' => 'Us Virgin Isl.',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'WF' => 'Wallis And Futuna Islands',
        'YE' => 'Yemen',
        'ZR' => 'Zaire',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    protected $TIMEOUT = 10;
    protected $REGISTER_URL = 'https://qmiles.qatarairways.com/ffponline/ffp-online/registration.jsf?hdnLang=en';
    protected $fields;
    protected $SELENIUM_RETRY = 5;

    protected static $fieldMap1 = [
        'Title'           => 'personalDetailsId:titleId',
        'FirstName'       => 'personalDetailsId:firstNameId',
        'LastName'        => 'personalDetailsId:lastNameId',
        'Gender'          => 'personalDetailsId:genderDetailsId',
        'BirthDay'        => 'personalDetailsId:birthDayDayId',
        'BirthMonth'      => 'personalDetailsId:birthDayMonthId',
        'BirthYear'       => 'personalDetailsId:birthDayYearId',
        'Password'        => 'personalDetailsId:passwordId',
        'ConfirmPassword' => 'personalDetailsId:rePasswordId',
    ];
    protected static $fieldMap2 = [
        'AddressType'  => 'contactDetailsId:homeAddress',
        'Country'      => 'contactDetailsId:countryId',
        'AddressLine1' => 'contactDetailsId:addressLine1Id',
        'Email'        => 'contactDetailsId:emailId',
        'ConfirmEmail' => 'contactDetailsId:reEmailId',
        // 'PhoneType' => '',
        // 'PhoneCountryCodeNumeric' => '',
        // 'PhoneAreaCode' => '',
        // 'PhoneLocalNumber' => '',
        'Company'    => 'contactDetailsId:companyId',
        'Department' => 'contactDetailsId:departmentId',

        // sometimes city gets cleared, moved
        'City' => 'contactDetailsId:cityOrTownIDPage',
    ];
    protected static $fieldMap3 = [
    ];
    protected static $fieldMap4 = [
    ];

    protected static $gender2id = [
        'M' => 'personalDetailsId:genderDetailsId:0',
        'F' => 'personalDetailsId:genderDetailsId:1',
    ];

    protected static $addressType2id = [
        'H' => 'contactDetailsId:homeAddress:0',
        'B' => 'contactDetailsId:homeAddress:1',
    ];

    public function initBrowser()
    {
        $this->useSelenium();
        $this->http->saveScreenshots = true;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->keepSession(false); //no need true
        } elseif (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $this->keepSession(false);
        }
    }

    public function registerAccount(array $fields)
    {
        $this->http->Log('[INFO] ' . __METHOD__);

        $this->fields = $fields;
        $this->checkFields();
        $this->modifyFields();
        $this->getUrlRetry($this->REGISTER_URL);
        $this->step1();
        $this->step2();
        $this->step3();
        $this->step4();

        return $this->checkStatus();
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
                'Type'     => 'integer',
                'Caption'  => 'Year of Birth Date',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password (should be 6-12 characters consisting of atleast 1 number and all lower case letters, without any special characters)',
                'Required' => true,
            ],
            'AddressType' => [
                'Type'     => 'string',
                'Caption'  => 'Address Type',
                'Required' => true,
                'Options'  => self::$addressTypes,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Optionis' => self::$countries,
            ],
            'AddressLine1' => [
                'Type'     => 'string',
                'Caption'  => 'Address Line',
                'Required' => true,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'City',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            // 'PhoneType' => [
            // 	'Type' => 'string',
            //     'Caption' => 'PhoneType',
            //     'Required' => true,
            // ],
            // 'PhoneCountryCodeNumeric' => [
            // 	'Type' => 'string',
            //     'Caption' => 'Phone Country Code',
            //     'Required' => true,
            // ],
            // 'PhoneAreaCode' => [
            // 	'Type' => 'string',
            //     'Caption' => 'Phone Area Code',
            //     'Required' => true,
            // ],
            // 'PhoneLocalNumber' => [
            // 	'Type' => 'string',
            //     'Caption' => 'Phone Number',
            //     'Required' => true,
            // ],
            'Company' => [
                'Type'     => 'string',
                'Caption'  => 'Company Name (required for Business Address)',
                'Required' => false,
            ],
            'Department' => [
                'Type'     => 'string',
                'Caption'  => 'Department (required for Business Address)',
                'Required' => false,
            ],
        ];
    }

    protected function checkFields()
    {
        $this->http->Log('[INFO] ' . __METHOD__);
    }

    protected function checkStatus()
    {
        $successSel = sprintf('//*[
			contains(text(), "Membership number") or
			contains(text(), "Membership Tier") or
			contains(text(), "Logout")
		]');
        $success1 = $this->waitForElement(\WebDriverBy::xpath($successSel), 20);
        $success2 = $this->http->findPreg('/Membership\s*number/i');
        $this->saveResponse();

        if ($success1 || $success2) {
            $acc = $this->http->findPreg('/Membership\s*number\s*:\s*(\w+)/i');
            $msg = sprintf('Successfull registration, membership number: %s', $acc);
            $this->ErrorMessage = $msg;

            return true;
        }
        $this->checkValidation();

        return false;
    }

    protected function modifyFields()
    {
        $this->http->Log('[INFO] ' . __METHOD__);

        $this->fields['ConfirmPassword'] = $this->fields['Password'];
        $this->fields['ConfirmEmail'] = $this->fields['Email'];

        $this->fields['BirthMonth'] = sprintf('%02s', $this->fields['BirthMonth']);

        // hackish way for setting radio inputs uniformly with others
        self::$fieldMap1['Gender'] = self::$gender2id[$this->fields['Gender']];
        self::$fieldMap2['AddressType'] = self::$addressType2id[$this->fields['AddressType']];
    }

    protected function step1()
    {
        $this->http->Log('[INFO] ' . __METHOD__);
        $submitSel = \WebDriverBy::id('personalDetailsId:savebuttonId');
        $submit = $this->waitForElement($submitSel);
        $this->saveResponse();
        $this->populate(self::$fieldMap1);
        $this->clickRetry($submitSel);
    }

    protected function step2()
    {
        $this->http->Log('[INFO] ' . __METHOD__);
        $submitSel = \WebDriverBy::id('contactDetailsId:savebuttonContactDetailsId');
        $submit = $this->waitForElement($submitSel);

        if (!$submit) {
            $this->checkValidation();
        }
        $this->saveResponse();
        $this->populate(self::$fieldMap2);
        $this->clickRetry($submitSel);
    }

    protected function step3()
    {
        $this->http->Log('[INFO] ' . __METHOD__);
        $submitSel = \WebDriverBy::id('preferenceDetailsId:savebuttonPreferenceDetailsId');
        $submit = $this->waitForElement($submitSel);

        if (!$submit) {
            $this->checkValidation();
        }
        $this->saveResponse();
        $this->clickRetry($submitSel);
    }

    protected function step4()
    {
        $this->http->Log('[INFO] ' . __METHOD__);
        $submitSel = \WebDriverBy::xpath(sprintf('//a[contains(text(), "Submit & Join")]'));
        $submit = $this->waitForElement($submitSel);

        if (!$submit) {
            $this->checkValidation();
        }
        $this->saveResponse();
        $this->clickRetry($submitSel);
    }

    protected function populate($fieldMap)
    {
        foreach ($fieldMap as $awkey => $key) {
            if (!arrayVal($this->fields, $awkey)) {
                continue;
            }
            $value = $this->fields[$awkey];
            $this->setInputValue($key, $value);
            // hack, setting country clears city
            if ($awkey === 'Country') {
                $this->waitForCountryJs();
            }
            $this->http->log(sprintf(
                '[DEBUG] set input value: awkey = "%s", key = "%s", value = "%s"',
            $awkey, $key, $value));
        }
    }

    protected function waitForCountryJs()
    {
        sleep(3);
        // we can do better, not just one field
        // $sel = sprintf('//*[
        // 	@id = "contactDetailsId:mobileCountryId" and normalize-space(text())
        // ]');
        // $this->waitForElement(\WebDriverBy::xpath($sel));
    }

    protected function checkValidation()
    {
        $error = $this->waitForElement(\WebDriverBy::cssSelector('.validationalert'));

        if ($error) {
            $msg = $error->getText();
            $msg = preg_replace('/\r|\n/', ' ', $msg);

            throw new \UserInputError(trim($msg));
        }
    }

    protected function setInputValue($key, $value)
    {
        for ($i = 0; $i < $this->SELENIUM_RETRY; $i++) {
            try {
                $this->setInputValuePlain($key, $value);

                return true;
            } catch (\StaleElementReferenceException $e) {
                $this->http->log('[ERROR] ' . $e->getMessage());
            }
        }
        $msg = 'Site error, please try again later';

        throw new \ProviderError($msg);
    }

    protected function setInputValuePlain($key, $value)
    {
        // $key is id here for now
        $elem = $this->waitForElement(\WebDriverBy::id($key));
        $type = $elem->getAttribute('type');
        $tagName = $elem->getTagName();

        if ($tagName === 'input' && $type === 'radio') {
            $elem->click();
        } elseif ($tagName === 'input') {
            $elem->clear();
            $elem->sendKeys($value);
        } elseif ($tagName === 'select') {
            $select = new \WebDriverSelect($elem);
            $select->selectByValue($value);
        }
    }

    protected function waitForElement($selector, $timeout = null, $visible = true)
    {
        for ($i = 0; $i < $this->SELENIUM_RETRY; $i++) {
            try {
                $elem = $this->waitForElementPlain($selector, $timeout, $visible);

                return $elem;
            } catch (\StaleElementReferenceException $e) {
                $this->http->log('[ERROR] ' . $e->getMessage());
            }
        }
        $msg = 'Site error, please try again later';

        throw new \ProviderError($msg);
    }

    protected function clickRetry($buttonSel)
    {
        for ($i = 0; $i < $this->SELENIUM_RETRY; $i++) {
            try {
                // should it be plain instead?
                $button = $this->waitForElement($buttonSel);
                $button->click();

                return true;
            } catch (\StaleElementReferenceException $e) {
                $this->http->log('[ERROR] ' . $e->getMessage());
            }
        }
        $msg = 'Site error, please try again later';

        throw new \ProviderError($msg);
    }

    protected function getUrlRetry($url)
    {
        for ($i = 0; $i < $this->SELENIUM_RETRY; $i++) {
            try {
                $this->http->getUrl($url);

                return true;
            } catch (\WebDriverCurlException $e) {
                $this->http->log('[ERROR] ' . $e->getMessage());
            }
        }
        $msg = 'Site error, please try again later';

        throw new \ProviderError($msg);
    }

    protected function waitForElementPlain($selector, $timeout = null, $visible = true)
    {
        if ($timeout === null) {
            $timeout = $this->TIMEOUT;
        }
        $elem = $this->traitWaitForElement($selector, $timeout, $visible);

        return $elem;
    }
}
