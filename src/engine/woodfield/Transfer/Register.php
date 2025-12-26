<?php

namespace AwardWallet\Engine\woodfield\Transfer;

use AwardWallet\Engine\ProxyList;

/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 27.07.15
 * Time: 13:19.
 */
class Register extends \TAccountChecker
{
    use ProxyList;

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
        'BQ' => 'Bonaire, Sint Eustatius and Saba',
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
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CW' => 'Curaçao',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'CI' => 'Côte d\'Ivoire',
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
        'FK' => 'Falkland Islands',
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
        'HM' => 'Heard Island And McDonald Islands',
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
        'MO' => 'Macao',
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
        'KP' => 'North Korea',
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
        'PN' => 'Pitcairn',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'BL' => 'Saint Barthélemy',
        'SH' => 'Saint Helena',
        'KN' => 'Saint Kitts And Nevis',
        'LC' => 'Saint Lucia',
        'MF' => 'Saint Martin',
        'PM' => 'Saint Pierre And Miquelon',
        'VC' => 'Saint Vincent And The Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome And Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SX' => 'Sint Maarten (Dutch part)',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'GS' => 'South Georgia And The South Sandwich Islands',
        'KR' => 'South Korea',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard And Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'CD' => 'The Democratic Republic Of Congo',
        'TL' => 'Timor-Leste',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks And Caicos Islands',
        'TV' => 'Tuvalu',
        'VI' => 'U.S. Virgin Islands',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UM' => 'United States Minor Outlying Islands',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'WF' => 'Wallis And Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'AX' => 'Åland Islands',
    ];

    public static $statesByCountry = [
        'US' => [
            // USA
            'AL' => 'Alabama',
            'AK' => 'Alaska',
            'AZ' => 'Arizona',
            'AR' => 'Arkansas',
            'CA' => 'California',
            'CO' => 'Coahuila',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'DC' => 'Dist. of Columbia',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'HI' => 'Hawaii',
            'ID' => 'Idaho',
            'IL' => 'Illinois',
            'IN' => 'Indiana',
            'IA' => 'Iowa',
            'KS' => 'Kansas',
            'KY' => 'Kentucky',
            'LA' => 'Louisiana',
            'ME' => 'Mexico State',
            'MD' => 'Maryland',
            'MA' => 'Massachusetts',
            'MI' => 'Michoacan',
            'MN' => 'Minnesota',
            'MS' => 'Mississippi',
            'MO' => 'Morelos',
            'MT' => 'Montana',
            'NE' => 'Nebraska',
            'NV' => 'Nevada',
            'NH' => 'New Hampshire',
            'NJ' => 'New Jersey',
            'NM' => 'New Mexico',
            'NY' => 'New York',
            'NC' => 'North Carolina',
            'ND' => 'North Dakota',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PA' => 'Pennsylvania',
            'PR' => 'Puerto Rico',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VT' => 'Vermont',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming',
            'AA' => 'Armed Forces Americas (AA)',
            'AE' => 'Armed Forces Europe (AE)',
            'AP' => 'Armed Forces Pacific (AP)',
        ],
        'MX' => [
            // Mexico
            'AG' => 'Aguascalientes',
            'BC' => 'British Columbia',
            'BS' => 'Baja California Sur',
            'CM' => 'Campeche',
            'CS' => 'Chiapas',
            'CH' => 'Chihuahua',
            'CL' => 'Colima',
            'DF' => 'Distrito Federal',
            'DG' => 'Durango',
            'GT' => 'Guanajuato',
            'GR' => 'Guerrero',
            'HG' => 'Hidalgo',
            'JA' => 'Jalisco',
            'NA' => 'Nayarit',
            'NL' => 'Nuevo Leon',
            'OA' => 'Oaxaca',
            'PU' => 'PUE',
            'QE' => 'Queretaro',
            'QR' => 'Quintana Roo',
            'SL' => 'San Luis Potosi',
            'SI' => 'Sinaloa',
            'SO' => 'Sonora',
            'TB' => 'Tabasco',
            'TM' => 'Tamaulipas',
            'TL' => 'Tlaxcala',
            'VE' => 'Veracruz',
            'YU' => 'Yucatan',
            'ZA' => 'Zacatecas',
        ],
        'CA' => [
            // Canada
            'AB' => 'Alberta',
            'MB' => 'Manitoba',
            'NB' => 'New Brunswick',
            'NF' => 'Newfoundland',
            'NT' => 'Northwest Territories',
            'NS' => 'Nova Scotia',
            'NU' => 'Nunavut',
            'ON' => 'Ontario',
            'PE' => 'Prince Edwards Island',
            'QC' => 'Quebec',
            'SK' => 'Saskatchewan',
            'YT' => 'Yukon',
        ],
    ];

    public static $phoneType = [
        'MOBILE' => 'Mobile',
        'HOME'   => 'Home',
        'WORK'   => 'Work',
    ];

    public static $inputFieldsMap = [
        'FirstName'       => 'firstName',
        'MiddleInitial'   => 'middleInitial',
        'LastName'        => 'lastName',
        'Email'           => 'email',
        'Password'        => 'password',
        'CompanyName'     => 'businessname',
        'AddressLine1'    => 'address1',
        'AddressLine2'    => 'address2',
        'Country'         => 'country',
        'City'            => 'city',
        'StateOrProvince' => 'state',
        'PostalCode'      => 'postal',
        'PrimaryPhone'    => 'primaryphone',
        'PhoneType'       => 'phoneType_1',
    ];

    public function InitBrowser()
    {
        $this->UseCurlBrowser();
        $this->AccountFields['BrowserState'] = null;
//        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION)
        $this->http->SetProxy($this->proxyDOP());
//        else
//            $this->http->SetProxy($this->proxyDOP()); // This provider should be tested via proxy even locally
    }

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;

        $this->checkValues($fields);

        $this->http->GetURL('https://www.lq.com/en/la-quinta-returns/join-now.html');
        $status = $this->http->ParseForm('requestReturnClubForm');

        if (!$status) {
            $this->http->Log('Failed to parse create account form');

            return false;
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

        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post create account form');

            return false;
        }

        if ($successMessage = $this->http->FindSingleNode("//div[@class='userRegistrationSuccessBlurb']", null, true, "/Your\s+member\s+number\s+is\s+[^\s]+/i")) {
            $this->ErrorMessage = $successMessage;
            $this->http->log($successMessage);

            return true;
        }

        if ($errMsg = $this->http->FindSingleNode("//div[@id='errmsg']")) {
            if ($errMsg == 'A La Quinta Returns account for this member already exists.') {
                throw new \ProviderError($errMsg);
            } else {
                throw new \UserInputError($errMsg);
            } // Is it always user input error?
        }

        return false;
    }

    public function checkValues($fields)
    {
        return true;
    }

    public static function states()
    {
        $statesPlain = [];

        foreach (self::$statesByCountry as $s) {
            $statesPlain = array_merge($statesPlain, $s);
        }

        return $statesPlain;
    }

    public function getRegisterFields()
    {
        return [
            'FirstName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'First Name',
                    'Required' => true,
                ],
            'MiddleInitial' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Middle Initial',
                    'Required' => false,
                ],
            'LastName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Last Name',
                    'Required' => true,
                ],
            'Email' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Email',
                    'Required' => true,
                ],
            'Password' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Password (5 - 10 Characters)',
                    'Required' => true,
                ],
            'CompanyName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Company Name',
                    'Required' => false,
                ],
            'AddressLine1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Line 1',
                    'Required' => true,
                ],
            'AddressLine2' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Line 2',
                    'Required' => false,
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
                    'Caption'  => 'State or Province (required for US, Canada and Mexico)',
                    'Required' => false,
                    'Options'  => self::states(),
                ],
            'PostalCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Postal Code',
                    'Required' => true,
                ],
            'PrimaryPhone' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Primary Phone',
                    'Required' => true,
                ],
            'PhoneType' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone Type',
                    'Required' => true,
                    'Options'  => self::$phoneType,
                ],
        ];
    }
}
