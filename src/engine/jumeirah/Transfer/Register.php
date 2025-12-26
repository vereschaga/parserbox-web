<?php

// case #10478

namespace AwardWallet\Engine\jumeirah\Transfer;

class Register extends \TAccountCheckerJumeirah
{
    public static $phoneTypeName = [
        'M' => 'Mobile number',
        'B' => 'Work number',
        'H' => 'Home number',
    ];

    public static $inputFieldsMap = [
        'Title'           => 'guestTitle',
        'FirstName'       => 'f_name',
        'MiddleName'      => 'm_name',
        'LastName'        => 'l_name',
        'Gender'          => 'gender',
        'Email'           => ['email_address', 'email_address_confirm'],
        'Country'         => 'country',
        'City'            => 'city',
        'StateOrProvince' => 'state',
        'PostalCode'      => 'zipCode',
        'AddressLine1'    => 'address_line1',
        'AddressLine2'    => 'address_line2',
        'AddressLine3'    => 'address_line3',
    ];

    public static $titles = [
        'Brigadier'            => 'Brigadier',
        'Captain'              => 'Captain',
        'Chancellor'           => 'Chancellor',
        'Colonel'              => 'Colonel',
        'Commander'            => 'Commander',
        'Count'                => 'Count',
        'Countess'             => 'Countess',
        'Dr.'                  => 'Dr.',
        'Duchess'              => 'Duchess',
        'Duke'                 => 'Duke',
        'Earl'                 => 'Earl',
        'Engineer'             => 'Engineer',
        'General'              => 'General',
        'H.E. Ambassador'      => 'H.E. Ambassador',
        'H.E. Sheikh'          => 'H.E. Sheikh',
        'H.E. Sheikha'         => 'H.E. Sheikha',
        'H.H. Sheikh'          => 'H.H. Sheikh',
        'H.H. Sheikha'         => 'H.H. Sheikha',
        'H.M. King'            => 'H.M. King',
        'H.M. Queen'           => 'H.M. Queen',
        'H.R.H. Prince'        => 'H.R.H. Prince',
        'H.R.H. Princess'      => 'H.R.H. Princess',
        'Her Excellency'       => 'Her Excellency',
        'His Excellency'       => 'His Excellency',
        'Judge'                => 'Judge',
        'Lady'                 => 'Lady',
        'Lieutenant'           => 'Lieutenant',
        'Lieutenant Commander' => 'Lieutenant Commander',
        'Lieutenant General'   => 'Lieutenant General',
        'Lord'                 => 'Lord',
        'Madam'                => 'Madam',
        'Major'                => 'Major',
        'Master'               => 'Master',
        'Minister'             => 'Minister',
        'Miss'                 => 'Miss',
        'Mr.'                  => 'Mr.',
        'Mrs.'                 => 'Mrs.',
        'Ms.'                  => 'Ms.',
        'President'            => 'President',
        'Prime Minister'       => 'Prime Minister',
        'Professor'            => 'Professor',
        'Rear Admiral'         => 'Rear Admiral',
        'Senator'              => 'Senator',
        'Sir'                  => 'Sir',
    ];

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
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
        'BA' => 'Bosnia and Herzegovina',
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
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (Keeling) Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CD' => 'Congo, The Democratic Republic of the',
        'CR' => 'Costa Rica',
        'CI' => 'Cote D\'Ivoire',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
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
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GG' => 'Guernsey',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard Island and McDonald Islands',
        'VA' => 'Holy See (Vatican City State)',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
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
        'KP' => 'Korea, Democratic People\'s Republic of',
        'KR' => 'Korea, Republic of',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao People\'s Democratic Republic',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libyan Arab Jamahiriya',
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
        'RE' => 'Réunion',
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'BL' => 'Saint Barthélemy',
        'SH' => 'Saint Helena',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'PM' => 'Saint Pierre and Miquelon',
        'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'GS' => 'South Georgia and the South Sandwich Islands',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
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
        'VE' => 'Venezuela',
        'VN' => 'Viet Nam',
        'VG' => 'Virgin Islands, British',
        'VI' => 'Virgin Islands, U.S.',
        'WF' => 'Wallis and Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'CK' => 'Cook Island',
        'PS' => 'Palestine',
        'TW' => 'Taiwan, Province of China',
    ];

    public static $phoneTypes = [
        'H' => 'Home number',
        'B' => 'Work number',
        'M' => 'Mobile number',
    ];

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;

        sleep(2);

        $this->validateData($fields);

        $this->http->GetURL('https://www.jumeirah.com/en/jumeirah-sirius/join-now/');
        $status = $this->http->ParseForm('aspnetForm');

        if (!$status) {
            $this->http->Log('Failed to parse create account form');

            return false;
        }

        // Provider uses wrong country codes for: United Kingdom (UK instead of standard GB)
        // Map from our standard ISO code to wrong code used by provider
        if ($fields['Country'] == 'GB') {
            $fields['Country'] = 'UK';
            $this->logger->debug('Mapped standard country code "GB" to provider code "UK"');
        }

        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($fields[$awKey]) or $provKeys === false) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $this->http->SetInputValue($provKey, $fields[$awKey]);
            }
        }

        $this->modifyPostDataConst();

        // phone field variants for POST
        $phoneTypesMap = [
            'H' => 'homeNumber',
            'B' => 'workNumber',
            'M' => 'mobileNumber',
        ];
        $phone = $fields['PhoneCountryCodeNumeric'] . $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];
        // format date of birth
        $bDay = ($fields['BirthDay'] + 0) < 10 ? '0' . $fields['BirthDay'] : $fields['BirthDay'];
        $bMonth = ($fields['BirthMonth'] + 0) < 10 ? '0' . $fields['BirthMonth'] : $fields['BirthMonth'];
        // add values
        $additionalValues = [
            // for some reason they do ask for date of birth, but ignore it in post
            // 'sdob' => $bDay.'/'.$bMonth.'/'.$fields['BirthYear'],
            'agree'                              => 'on',
            $phoneTypesMap[$fields['PhoneType']] => $phone,
            'preferredNumber'                    => self::$phoneTypeName[arrayVal($fields, 'PhoneType')],
        ];

        foreach ($additionalValues as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }

        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post create account form');

            return false;
        }

        $successPath = "//div[contains(@class, 'transactionData-wrapper') and contains(.,'Your profile information has been added successfully')]";

        if ($successMessage = $this->http->FindSingleNode($successPath)) {
            $this->http->log(sprintf('[INFO] %s', $successMessage));
            $this->ErrorMessage = $successMessage;

            return true;
        }

        $errorPath = "//div[contains(@class, 'transactionData-wrapper')]";

        if ($error = $this->http->FindSingleNode($errorPath, null, true, '/(.*)Continue\s+Browsing/ims')) {
            throw new \UserInputError($error);
        } // Is it always user input error?

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Title' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Title',
                'Required' => true,
                'Options'  => self::$titles,
            ],
            'FirstName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'First Name',
                'Required' => true,
            ],
            'MiddleName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Middle Name',
                'Required' => false,
            ],
            'LastName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Last Name',
                'Required' => true,
            ],
            'Gender' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => false,
                'Options'  => self::$genders,
            ],
            'Email' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'BirthMonth' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Birth Month',
                'Required' => true,
            ],
            'BirthDay' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Birth Day',
                'Required' => true,
            ],
            'BirthYear' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Birth Year',
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
            'StateOrProvince' =>
            [
                'Type'     => 'string',
                'Caption'  => 'State Or Province',
                'Required' => false,
            ],
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Postal Code',
                'Required' => true,
            ],
            'AddressLine1' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Address Line 1 (please provide your full postal address)',
                'Required' => true,
            ],
            'AddressLine2' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Address Line 2',
                'Required' => false,
            ],
            'AddressLine3' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Address Line 3',
                'Required' => false,
            ],
            'PhoneType' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Phone Type',
                'Required' => true,
                'Options'  => self::$phoneTypes,
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
        ];
    }

    private function validateData($fields)
    {
        if (!arrayVal($fields, 'City')) {
            throw new \UserInputError('City cannot be empty');
        }

        $EMAIL_RE = '/^[\S]+@[\S]+[.][\S]+$/i';

        if (!preg_match($EMAIL_RE, $fields['Email'])) {
            throw new \UserInputError('Invalid email address');
        }
    }

    private function modifyPostDataConst()
    {
        $unsetPost = [
            '', 'ctl00$MainSectionRegion$f_name', 'ctl00$MainSectionRegion$hiddenFirstName', 'ctl00$MainSectionRegion$m_name', 'ctl00$MainSectionRegion$l_name', 'ctl00$MainSectionRegion$hiddenLastName',
            'ctl00$MainSectionRegion$hiddenGender', 'ctl00$MainSectionRegion$email_address', 'ctl00$MainSectionRegion$sdob', 'ctl00$MainSectionRegion$hiddenDateOfBirth', 'ctl00$MainSectionRegion$companyName',
            'ctl00$MainSectionRegion$Jobtitle', 'ctl00$MainSectionRegion$pobox', 'ctl00$MainSectionRegion$address_line1', 'ctl00$MainSectionRegion$address_line2', 'ctl00$MainSectionRegion$city',
            'ctl00$MainSectionRegion$state', 'ctl00$MainSectionRegion$txtzipCode', 'ctl00$MainSectionRegion$mobileNumber', 'ctl00$MainSectionRegion$workNumber', 'ctl00$MainSectionRegion$homeNumber',
            'ctl00$MainSectionRegion$faxNumber', 'ctl00$GlobalHead$ctl00$currencies', 'ctl00$MainSectionRegion$mediaSurvey', 'ctl00$MainSectionRegion$guestTitle', 'ctl00$MainSectionRegion$gender',
            'ctl00$MainSectionRegion$nationality', 'ctl00$MainSectionRegion$country', 'ctl00$MainSectionRegion$preferredNumber', 'ctl00$MainSectionRegion$preferredMethod', 'ctl00$GlobalHead$ctl00$ctl00$LanguageDropDown',

            'ctl00$CarouselArea$SiriusSecondaryNavigation1$SiriusBookNowModule$destinationDropDown',
            'ctl00$MainSectionRegion$address_line3',
            'ctl00$MainSectionRegion$hiddenAddressId',
            'ctl00$MainSectionRegion$hiddenCountryCode',
            'ctl00$MainSectionRegion$hiddenEmailId',
            'ctl00$MainSectionRegion$HiddenHomeNumberId',
            'ctl00$MainSectionRegion$HiddenMobileNumberId',
            'ctl00$MainSectionRegion$hiddenState',
            'ctl00$MainSectionRegion$hiddenWorkNumberId',
            'ctl00$MainSectionRegion$select_state',
            'ctl00$MainSectionRegion$zipCode',

            'siriusMem_ID',
            'Remember',
            'password',
            'from',
            'to',
        ];

        foreach ($unsetPost as $key) {
            unset($this->http->Form[$key]);
        }

        $tosetPost = [
            'address_line2' => '',
            'address_line3' => '',

            'cmCategoryId'          => 'Home:Jumeirah Sirius',
            'cmOverridePageViewTag' => 'false',
            'cmPageId'              => 'Home:Jumeirah Sirius:Join Now',
            'cmReferrerPageId'      => '',

            'ctl00_GlobalHead_ctl00_ctl00_LanguageDropDown' => 'en',
            'ctl00_MainSectionRegion_hiddenAddressId'       => '',
            'ctl00_MainSectionRegion_hiddenEmailId'         => '',
            'ctl00_MainSectionRegion_HiddenHomeNumberId'    => '',
            'ctl00_MainSectionRegion_HiddenMobileNumberId'  => '',
            'ctl00_MainSectionRegion_hiddenWorkNumberId'    => '',

            'currencies'          => 'AED',
            'destinationDropDown' => '-1',

            'hdnNewProfile'     => '--',
            'hiddenCountryCode' => '',
            'hiddenDateOfBirth' => '',
            'hiddenFirstName'   => '',
            'hiddenGender'      => '',
            'hiddenLastName'    => '',
            'hiddenState'       => '',
            'homeNumber'        => '',
            'hotelDropDown'     => '-1',
            'm_name'            => '',

            'sdob'        => '',
            'txtLoginId'  => '',
            'txtPassword' => '',
            'workNumber'  => '',
        ];

        foreach ($tosetPost as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }
    }
}
