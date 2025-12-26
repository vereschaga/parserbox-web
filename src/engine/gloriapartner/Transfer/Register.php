<?php

namespace AwardWallet\Engine\gloriapartner\Transfer;

class Register extends \TAccountCheckerGloriapartner
{
    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $nationalities = [
        'CN'  => 'China',
        'AL'  => 'Albania',
        'DZ'  => 'Algeria',
        'AF'  => 'Afghanistan',
        'AR'  => 'Argentina',
        'EM'  => 'United Arab Emirates',
        'AW'  => 'Aruba(Bonaire-Curca)',
        'OM'  => 'Oman',
        'EG'  => 'Egypt',
        'ET'  => 'Ethiopia',
        'IE'  => 'Ireland',
        'EE'  => 'Estonia',
        'AD'  => 'Andorra',
        'AO'  => 'Angola',
        'AI'  => 'Anguilla-St. Kitts',
        'AT'  => 'Austria',
        'AU'  => 'Australia',
        'BB'  => 'Barbados',
        'PG'  => 'Papua New Guinea',
        'BS'  => 'Bahamas',
        'PK'  => 'Pakistan',
        'PY'  => 'Paraguay',
        'PA'  => 'Panama',
        'BR'  => 'Brazil',
        'BY'  => 'Belarus',
        'BM'  => 'Bermuda',
        'BG'  => 'Bulgaria',
        'MP'  => 'Northern Mariana Ile',
        'BN'  => 'Benin',
        'BE'  => 'Belgium',
        'IC'  => 'Iceland',
        'PR'  => 'Puerto Rico',
        'PL'  => 'Poland',
        'BA'  => 'Bosnia',
        'BO'  => 'Bolivia',
        'BZ'  => 'Belize',
        'BW'  => 'Botswana',
        'BT'  => 'Bhutan',
        'BF'  => 'Bourkina Fasso',
        'BD'  => 'Burundi',
        'KP'  => 'North Korea',
        'GQ'  => 'Equatorial Guinea',
        'DK'  => 'Denmark',
        'GE'  => 'Germany',
        'TE'  => 'East Timor',
        'TG'  => 'Togo',
        'DM'  => 'Dominica',
        'DO'  => 'Dominican Republic',
        'RU'  => 'Russia',
        'EC'  => 'Ecuador',
        'ER'  => 'Eritrea',
        'FR'  => 'France',
        'FO'  => 'Faeroe Islands',
        'PF'  => 'French Polynesia',
        'GF'  => 'French Guinea',
        'PH'  => 'Philippines',
        'FJ'  => 'Fiji Island',
        'FL'  => 'Finland',
        'CV'  => 'Cape Verde',
        'FK'  => 'Falkland Islands',
        'GM'  => 'Gambia',
        'CG'  => 'Congo',
        'CO'  => 'Colombia',
        'CR'  => 'Costa Rica',
        'GD'  => 'Grenada',
        'GL'  => 'Greenland',
        'GG'  => 'Georgia',
        'CU'  => 'Cuba',
        'GP'  => 'Guadeloupe',
        'GU'  => 'Guam',
        'GY'  => 'Guyana',
        'KK'  => 'Kazakhstan',
        'HT'  => 'Haiti',
        'KO'  => 'South Korea',
        'NL'  => 'Netherlands',
        'HN'  => 'Honduras',
        'KI'  => 'Kiribati',
        'DJ'  => 'Djibouti',
        'KG'  => 'Kyrgyzstan',
        'GN'  => 'Guinea',
        'GW'  => 'Guinea Bissau',
        'CA'  => 'Canada',
        'GH'  => 'Ghana',
        'GA'  => 'Gabon',
        'XA'  => 'Gaza',
        'KH'  => 'Cambodia',
        'CZ'  => 'Czech Republic',
        'ZW'  => 'Zimbabwe',
        'CM'  => 'Cameroon',
        'QA'  => 'Qatar',
        'KY'  => 'Cayman Islands',
        'KM'  => 'Comoros Islands',
        'KT'  => 'Kuwait',
        'CC'  => 'Coco(Keeling)Islands',
        'HR'  => 'Croatia',
        'KE'  => 'Kenya',
        'CK'  => 'Cook Islands',
        'LV'  => 'Latvia',
        'LS'  => 'Lesotho',
        'LA'  => 'Laos',
        'LB'  => 'Lebanon',
        'LT'  => 'Lithuania',
        'LR'  => 'Liberia',
        'LY'  => 'Libya',
        'LI'  => 'Lichetenstein',
        'RE'  => 'Reunion Island',
        'LU'  => 'Luxembourg',
        'RW'  => 'Rwanda',
        'RO'  => 'Romania',
        'MG'  => 'Madagascar',
        'MV'  => 'Maldives',
        'MT'  => 'Malta',
        'MW'  => 'Malawi',
        'MA'  => 'Malaysia',
        'MK'  => 'Macedonia',
        'MH'  => 'Marshall Islands',
        'MQ'  => 'Martinique',
        'YT'  => 'Mayotte',
        'MU'  => 'Mauritius',
        'MR'  => 'Mauritania',
        'US'  => 'United States',
        'VI'  => 'US Virgin Islands',
        'MN'  => 'Mongolia',
        'MS'  => 'Montserrat',
        'BJ'  => 'Bangladesh',
        'PE'  => 'Peru',
        'FM'  => 'Miconesia',
        'MM'  => 'Myhanmar(Burma)',
        'MD'  => 'Moldova',
        'RC'  => 'Morocco',
        'MC'  => 'Monaco',
        'MZ'  => 'Mozambique',
        'ME'  => 'Mexico',
        'NA'  => 'Namibia',
        'SA'  => 'South Africa',
        'NR'  => 'Nauru',
        'NP'  => 'Nepal',
        'NI'  => 'Nicaragua',
        'NE'  => 'Niger',
        'NG'  => 'Nigeria',
        'NU'  => 'Niue',
        'NO'  => 'Norway',
        'NF'  => 'Norfolk Island',
        'PW'  => 'Palau',
        'PN'  => 'Pitcairn Island',
        'PT'  => 'Portugal',
        'JP'  => 'Japan',
        'SE'  => 'Sweden',
        'SW'  => 'Switzerland',
        'SV'  => 'El Salvador',
        'AZ'  => 'American Samoa Isles',
        'YU'  => 'Serbia',
        'SL'  => 'Sierra Leone',
        'SN'  => 'Senegal',
        'CY'  => 'Cyprus',
        'SC'  => 'Seychelles',
        'SJ'  => 'Saudi Arabia',
        'CX'  => 'Christmas Island',
        'ST'  => 'Sao Tome Principe',
        'SH'  => 'St. Helena',
        'LC'  => 'Saint Lucia',
        'SM'  => 'San Marino',
        'PM'  => 'St.Pierre &amp; Miguelon',
        'VC'  => 'Saint Vincent And The Grenadines',
        'SR'  => 'Sri Lanka',
        'SK'  => 'Slovakia',
        'SI'  => 'Slovenia',
        'SZ'  => 'Swaziland',
        'SD'  => 'Sudan',
        'SU'  => 'Suriname',
        'SB'  => 'Solomon Islands',
        'TJ'  => 'Tajilistan',
        'TH'  => 'Thailand',
        'TO'  => 'Tonga',
        'TC'  => 'Turks &amp; Caicos Isles',
        'TN'  => 'Tunisia',
        'TV'  => 'Tuvalu',
        'TR'  => 'Turkey',
        'TM'  => 'Turkmenistan',
        'TK'  => 'Tokelau',
        'WF'  => 'Wallis &amp; Futuna Isle',
        'OTH' => 'Vanuatu',
        'GT'  => 'Guatemala',
        'VE'  => 'Venezuela',
        'UC'  => 'Unkown Country',
        'BU'  => 'Brunei',
        'UG'  => 'Uganda',
        'UA'  => 'Ukraine',
        'UY'  => 'Uruguay',
        'UZ'  => 'Uzbekistan',
        'SP'  => 'Spain',
        'WS'  => 'Samoa Western',
        'GR'  => 'Greece',
        'SG'  => 'Singapore',
        'NC'  => 'New Caledonia',
        'NZ'  => 'New Zealand',
        'HU'  => 'Hungary',
        'SY'  => 'Syria',
        'JM'  => 'Jamaica',
        'AM'  => 'Armenia',
        'IQ'  => 'Iraq',
        'IR'  => 'Iran',
        'IL'  => 'Israel',
        'IT'  => 'Italy',
        'IN'  => 'India',
        'IA'  => 'Indonesia',
        'XI'  => 'Indian Ocean Islands',
        'UK'  => 'United Kingdom',
        'VG'  => 'British Virgin Isles',
        'JO'  => 'Jordan',
        'ZM'  => 'Zambia',
        'ZR'  => 'Zaire',
        'TD'  => 'Chad',
        'GI'  => 'Gibraltar',
        'CL'  => 'Chile',
        'CF'  => 'Central African',
        'MO'  => 'Macau',
        'TW'  => 'Taiwan',
        'HK'  => 'Hong Kong',
        'MI'  => 'Midway Islands',
    ];

    public static $phoneTypes = [
        'H' => 'Home',
        'B' => 'Business',
        'M' => 'Mobile',
    ];

    public static $inputFieldsMap = [
        'LastName'         => 'txtlastNameCN',
        'FirstName'        => 'txtfirstNameCN',
        'Gender'           => 'sex',
        'Nationality'      => 'txtCountry',
        'PhoneLocalNumber' => 'txtMobile',
        'Email'            => 'txtEmail',
        'AddressLine1'     => 'txtAddress',
        'PostalCode'       => 'txtPost',
    ];

    public function registerAccount(array $fields)
    {
        $this->http->removeCookies();
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;

        $this->http->GetURL('http://member.gloriahotels.com/en/Register.aspx');

        if (!$captcha = $this->parseCaptcha()) {
            $this->logger->error('Failed to parse Captcha');

            return false;
        }

        $status = $this->http->ParseForm('form1');

        if (!$status) {
            $this->http->Log('Failed to parse create account form');

            return false;
        }

        $genderValues = ['M' => 'sexMan', 'F' => 'sexWoman'];
        $fields['Gender'] = $genderValues[$fields['Gender']];
        $fields['PhoneLocalNumber'] = $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];

        while (strlen($fields['Nationality']) < 4) {
            $fields['Nationality'] .= ' ';
        }

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

        $addValues = [
            'code'           => $captcha,
            'agree'          => '1',
            'ImageButton1.x' => '67',
            'ImageButton1.y' => '12',
        ];

        foreach ($addValues as $row => $val) {
            $this->http->SetInputValue($row, $val);
        }

        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post create account form');

            return false;
        }

        if ($successMessage = $this->http->FindSingleNode("//p[@class='dh' and contains(text(),'Registration Successful')]")) {
            $cardNum = $this->http->FindSingleNode("//span[@id='Label1']");
            $pass = $this->http->FindSingleNode("//span[@id='Label2']");
            $this->ErrorMessage = $successMessage . " Your card number:$cardNum, Password:$pass, Please remember your card number, and Change Passwordto your familiared password.";
            $this->http->Log($this->ErrorMessage);

            return true;
        }

        if ($message = $this->http->FindSingleNode("//script[contains(text(), 'alert')][1]", null, true, '/alert\("(.*)"\)/ims')) {
            throw new \UserInputError($message);
        } // Is it always user input error?

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'LastName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Last Name',
                    'Required' => true,
                ],
            'FirstName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'First Name',
                    'Required' => true,
                ],
            'Gender' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Gender',
                    'Required' => true,
                    'Options'  => self::$genders,
                ],
            'Nationality' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Nationality',
                    'Required' => true,
                    'Options'  => self::$nationalities,
                ],
            'PhoneType' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone Type (mobile phone is required)',
                    'Required' => true,
                    'Options'  => self::$phoneTypes,
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
            'AddressLine1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => ' Detailed address',
                    'Required' => true,
                ],
            'PostalCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Zip',
                    'Required' => true,
                ],
        ];
    }
}
