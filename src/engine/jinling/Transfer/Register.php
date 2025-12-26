<?php

namespace AwardWallet\Engine\jinling\Transfer;

class Register extends \TAccountChecker
{
    public static $countries = [
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antiqua And Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Arminia',
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
        'BA' => 'Bosnia And Herzegowina',
        'BW' => 'Botswana',
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
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CK' => 'Cook Islands',
        'CR' => 'Costarica',
        'CI' => 'Cote d lvoir',
        'HR' => 'Croatia , Hrvatska',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czeck Republic',
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
        'EE' => 'Estonia (Baltic States)',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands (Malvinas)',
        'FO' => 'Faroa Islands',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'PF' => 'French Polynesia',
        'GF' => 'FrenchGuiana',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GR' => 'Greece',
        'GL' => 'Greenland',
        'GD' => 'Grenada',
        'GP' => 'Guadeluope',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
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
        'KZ' => 'Kazakhtan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KP' => 'Korea, Democratic People s',
        'KR' => 'Korea, Republic of',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao People s Demotratic Republic',
        'LV' => 'Latvia (Baltic State)',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libyan Arab Jamahiriya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania (Baltic State)',
        'LU' => 'Luxembourg',
        'MO' => 'Macau',
        'MK' => 'Macedonia',
        'MG' => 'Madacascar',
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
        'MD' => 'Moldova, Republic',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'MS' => 'Montserrat',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar',
        'NA' => 'Namibia',
        'NR' => 'Nauru',
        'NP' => 'Nepal',
        'NL' => 'Nethelands',
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
        'PN' => 'Pitcain',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'KN' => 'Saint Kitts And Nevis',
        'LC' => 'Saint Lucia',
        'VC' => 'Saint Vincent and The Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
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
        'SH' => 'St Helena',
        'PM' => 'St Pierre And Miquelon',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard And Jan Mayen Islands',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Rupublic',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirate',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City State (Holy Siege)',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'VG' => 'Virgin Islands (British)',
        'VI' => 'Virgin Islangs (US)',
        'WF' => 'Wallis And Futuna Islands',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'CD' => 'Zaire',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    protected $titles = [
        'Mr.'  => 1,
        'Ms.'  => 2,
        'Dr.'  => 5,
        'Miss' => 88,
        'Mrs.' => 89,
    ];

    public function InitBrowser()
    {
        $this->UseCurlBrowser();
    }

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->Log('jinling register account');
        $this->checkFields($fields);
        $this->http->GetURL('http://www.jinlingelite.com/english/CRSMember.aspx?type=GetCountryCode');
        $countries = @json_decode($this->http->Response['body']);

        if (empty($countries)) {
            $this->http->Log('did not get country list', LOG_LEVEL_ERROR);

            return false;
        }
        $fields['Country'] = strtoupper($fields['Country']);
        $countries = array_filter($countries, function ($obj) use ($fields) {
            return isset($obj->CC_Code) && $obj->CC_Code === $fields['Country'];
        });

        if (empty($countries)) {
            throw new \UserInputError('Invalid country code');
        }
        // Provider uses wrong country codes for:
        // - East Timor (TP instead of standard TL)
        // - Nicaragua (N1 instead of standard NI)
        // - Zaire (ZR instead of standard CD)
        // Map from our standard ISO code to wrong code used by provider
        $wrongCountryCodesFixingMap = [
            'NI' => 'N1',
            'TL' => 'TP',
            'CD' => 'ZR',
        ];

        if (isset($wrongCountryCodesFixingMap[$fields['Country']])) {
            $origCountryCode = $fields['Country'];
            $fields['Country'] = $wrongCountryCodesFixingMap[$fields['Country']];
            $this->logger->debug('Mapped standard country code "' . $origCountryCode . '" to provider code "' . $fields['Country'] . '"');
        }

        $this->http->GetURL('http://www.jinlingelite.com/english/Register.aspx');

        if (!$this->http->ParseForm('form1')) {
            return false;
        }
        $phone = sprintf('%s%s%s', $fields['PhoneCountryCodeNumeric'], $fields['PhoneAreaCode'], $fields['PhoneLocalNumber']);

        if (!preg_match('/^\d+$/', $phone)) {
            throw new \UserInputError('Invalid phone');
        }
        $random = RandomStr(ord('0'), ord('9'), 20);
        $time = time() * 1000 + 123;
        $this->http->GetURL(sprintf('http://www.jinlingelite.com/english/CRSMember.aspx?type=MobileOrEmailVerification&jsonpcallback=jQuery%s_%s&InputType=E&No=%s&_=%s', $random, $time, urlencode($fields['Email']), $time));
        $time++;
        $this->http->GetURL(sprintf('http://www.jinlingelite.com/english/CRSMember.aspx?type=MobileOrEmailVerification&jsonpcallback=jQuery%s_%s&InputType=P&No=%s&_=%s', $random, $time, $phone, $time));
        $this->http->GetURL('http://www.jinlingelite.com/english/CRSMember.aspx?type=MobileOrEmailVerification&InputType=P&No=' . $phone);
        $response = @json_decode($this->http->Response['body']);

        if (!$response) {
            return false;
        }

        if (!isset($response->RetCode) || $response->RetCode !== '0') {
            throw new \ProviderError('This phone number has been registered');
        } // text from page script
        $this->http->GetURL('http://www.jinlingelite.com/english/CRSMember.aspx?type=MobileOrEmailVerification&InputType=E&No=' . urlencode($fields['Email']));
        $response = @json_decode($this->http->Response['body']);

        if (!$response) {
            return false;
        }

        if (!isset($response->RetCode) || $response->RetCode !== '0') {
            throw new \UserInputError('Invalid email');
        } // couldnt pull error messages for emails
        $fields['CName'] = '';
        $fields['Title'] = $this->titles[$fields['Title']];
        $fields['Birthday'] = sprintf('%s-%s-%s', $fields['BirthYear'], $fields['BirthMonth'], $fields['BirthDay']);
        $fields['Phone'] = $phone;
        $fields['rType'] = '1';
        $fields['IsEmailVerification'] = '0';
        $fields[''] = '';

        foreach ([
            'CName' => 'CName',
            'Title' => 'Title',
            'LName' => 'LastName',
            'FName' => 'FirstName',
            'Name' => 'Username',
            'Birthday' => 'Birthday',
            'Password' => 'Password',
            'Password2' => 'Password',
            'Email' => 'Email',
            'Mobile' => 'Phone',
            'MobileCode' => '',
            'Country' => 'Country',
            'City' => '',
            'Address' => 'AddressLine1',
            'Zip' => '',
            'rType' => 'rType',
            'IsEmailVerification' => 'IsEmailVerification',
            'txt_VerifyCode' => '',
            'ffpList' => '',
        ] as $name => $key) {
            $this->http->Form[$name] = $fields[$key];
        }

        foreach ([
            'State',
            '',
            'AcceptNews',
            'AcceptAD',
        ] as $name) {
            unset($this->http->Form[$name]);
        }
        $this->http->FormURL = 'http://www.jinlingelite.com/english/CRSMember.aspx?type=MemberReg_EN';

        if (!$this->http->PostForm()) {
            return false;
        }
        $response = @json_decode($this->http->Response['body']);

        if (isset($response->RetCode) && $response->RetCode === '0') {
            $this->ErrorMessage = 'Email with activation link has been sent';

            return true;
        }

        if (isset($response->RetCode) && isset($response->RetDesc) && intval($response->RetCode) > 0) {
            throw new \CheckException($response->RetDesc);
        }
        $this->http->Log('unknown response', LOG_LEVEL_ERROR);

        return false;
    }

    public function getRegisterFields()
    {
        $titles = [];

        foreach ($this->titles as $t => $i) {
            $titles[$t] = $t;
        }

        return [
            'Title' => [
                'Type'     => 'string',
                'Caption'  => 'Title',
                'Required' => true,
                'Options'  => $titles,
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
            'Username' => [
                'Type'     => 'string',
                'Caption'  => 'Username',
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
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password - 8 numbers which cannot be repeated',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'PhoneCountryCodeNumeric' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Country Code',
                'Required' => true,
            ],
            'PhoneAreaCode' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Area Code',
                'Required' => true,
            ],
            'PhoneLocalNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Local Number',
                'Required' => true,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country Code',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'AddressLine1' => [
                'Type'     => 'string',
                'Caption'  => 'Address',
                'Required' => true,
            ],
        ];
    }

    protected function checkFields($fields)
    {
        if (!preg_match('/^\d{8}$/', $fields['Password'])) {
            throw new \UserInputError('Password should be 8 numbers which cannot be repeated');
        }

        switch ($fields['Password']) { // from page script
            case "01234567":
            case "76543210":
            case "12345678":
            case "87654321":
            case "23456789":
            case "98765432":
                throw new \UserInputError('Password should be 8 numbers which cannot be repeated，or in ascending/descending order');
        }
        $split = str_split($fields['Password']);

        if (count(array_unique($split)) === 1) {
            throw new \UserInputError('Password should be 8 numbers which cannot be repeated');
        }
    }
}
