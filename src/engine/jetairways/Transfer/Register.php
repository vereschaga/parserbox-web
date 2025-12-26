<?php

namespace AwardWallet\Engine\jetairways\Transfer;

class Register extends \TAccountChecker
{
    protected $requestFields = [
        '__EVENTTARGET'                                                 => '',
        '__EVENTARGUMENT'                                               => '',
        '__VIEWSTATE'                                                   => '',
        '__VIEWSTATEGENERATOR'                                          => '',
        'ctl00$SiteSearch$searchInput'                                  => '',
        'ctl00$SiteSearch$URL'                                          => '',
        'ctl00$IWOVID_item_1$hdnLanguageID'                             => '',
        'ctl00$IWOVID_item_1$ddlCountries'                              => '',
        'ctl00$IWOVID_item_1$ddlLanguages'                              => '',
        'ctl00$Login$hddnInvalidEmail'                                  => '',
        'ctl00$Login$hddnInvalidJPNumber'                               => '',
        'ctl00$Login$hddnInvalidMobileNumber'                           => '',
        'ctl00$Login$hddnFlashMessageDelay'                             => '',
        'ctl00$Login$txtHeaderJPNumber'                                 => '',
        'ctl00$Login$txtHeaderPassword'                                 => '',
        'ctl00$MainBody$IWOVID_item_1$txtPromotionCode'                 => '',
        'ctl00$MainBody$IWOVID_item_1$Preassigned'                      => '',
        'ctl00$MainBody$IWOVID_item_1$ddlsalutation'                    => '',
        'ctl00$MainBody$IWOVID_item_1$txtFname'                         => '',
        'ctl00$MainBody$IWOVID_item_1$txtMname'                         => '',
        'ctl00$MainBody$IWOVID_item_1$txtLname'                         => '',
        'ctl00$MainBody$IWOVID_item_1$gender'                           => '',
        'ctl00$MainBody$IWOVID_item_1$ddlCOR'                           => '',
        'ctl00$MainBody$IWOVID_item_1$ddlCitizen'                       => '',
        'ctl00$MainBody$IWOVID_item_1$txtDOB'                           => '',
        'ctl00$MainBody$IWOVID_item_1$txtMobileNumber'                  => '',
        'ctl00$MainBody$IWOVID_item_1$txtEmailAddr'                     => '',
        'ctl00$MainBody$IWOVID_item_1$txtPassword'                      => '',
        'ctl00$MainBody$IWOVID_item_1$txtRePassword'                    => '',
        'recaptcha_challenge_field'                                     => '',
        'recaptcha_response_field'                                      => '',
        'ctl00$MainBody$IWOVID_item_1$chkTermsAndCondition'             => '',
        'ctl00$MainBody$IWOVID_item_1$CommunicationAddress'             => '',
        'ctl00$MainBody$IWOVID_item_1$txtStateHome'                     => '',
        'ctl00$MainBody$IWOVID_item_1$ddlCityHome'                      => '',
        'ctl00$MainBody$IWOVID_item_1$txtOtherCityHome'                 => '',
        'ctl00$MainBody$IWOVID_item_1$txtPostcodeHome'                  => '',
        'ctl00$MainBody$IWOVID_item_1$txtAddr1Home'                     => '',
        'ctl00$MainBody$IWOVID_item_1$txtAddr2Home'                     => '',
        'ctl00$MainBody$IWOVID_item_1$txtAddr3Home'                     => '',
        'ctl00$MainBody$IWOVID_item_1$hndCityNameHome'                  => '',
        'ctl00$MainBody$IWOVID_item_1$hndStateNameHome'                 => '',
        'ctl00$MainBody$IWOVID_item_1$hdnPostalCodeErrorMessage'        => '',
        'ctl00$MainBody$IWOVID_item_1$hdnISDCode'                       => '',
        'ctl00$MainBody$IWOVID_item_1$hdnPhoneNo'                       => '',
        'ctl00$MainBody$IWOVID_item_1$hdnEnrollSecurePwd'               => '',
        'ctl00$MainBody$IWOVID_item_1$txtCompanyName'                   => '',
        'ctl00$MainBody$IWOVID_item_1$ddlBusinessCategory'              => '',
        'ctl00$MainBody$IWOVID_item_1$ddlJobTitle'                      => '',
        'ctl00$MainBody$IWOVID_item_1$txtDesignation'                   => '',
        'ctl00$MainBody$IWOVID_item_1$txtStateBusiness'                 => '',
        'ctl00$MainBody$IWOVID_item_1$ddlCityBusiness'                  => '',
        'ctl00$MainBody$IWOVID_item_1$txtOtherCityBusiness'             => '',
        'ctl00$MainBody$IWOVID_item_1$txtAddr1Business'                 => '',
        'ctl00$MainBody$IWOVID_item_1$txtAddr2Business'                 => '',
        'ctl00$MainBody$IWOVID_item_1$txtAddr3Business'                 => '',
        'ctl00$MainBody$IWOVID_item_1$hndCityNameBusiness'              => '',
        'ctl00$MainBody$IWOVID_item_1$hndStateNameBusiness'             => '',
        'ctl00$MainBody$IWOVID_item_1$txtPostcodeBusiness'              => '',
        'ctl00$MainBody$IWOVID_item_1$btnEnroll'                        => '',
        'ctl00$MainBody$IWOVID_item_1$ucTwoFAScreen$txtMobileNumberOTP' => '',
        'ctl00$MainBody$IWOVID_item_1$ucTwoFAScreen$txtNewEmailAddr'    => '',
        'ctl00$MainBody$IWOVID_item_1$ucTwoFAScreen$txtNewMobileNumber' => '',
    ];

    protected static $countries = [
        'AF' => 'Afghanistan',
        'AX' => 'Aland Islands',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antartica',
        'AG' => 'Antigua and Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AC' => 'Ascension Island',
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
        'BA' => 'Bosnia n Herzegovina',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'BRITISH INDIAN OCEAN TERRITORY',
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
        'CX' => 'Christmas Islands',
        'CC' => 'Cocos Keeling Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CD' => 'CONGO',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'CI' => 'COTE D\'IVOIRE',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'KP' => 'DEMOCRATIC PEOPLE\'S REPUBLIC OF KOREA',
        'ZR' => 'DEMOCRATIC REPUBLIC OF THE CONGO',
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
        'FK' => 'FALKLAND ISLAND (MALVINAS)',
        'FO' => 'Faroe Islands',
        'FM' => 'FEDERATED STATES OF MICRONESIA',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
        'TF' => 'FRENCH SOUTHERN TERRITORIES',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GP' => 'Gaudeloupe',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GR' => 'Greece',
        'GL' => 'Greenland',
        'GD' => 'Grenada',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GG' => 'Guernsey',
        'GN' => 'Guinea',
        'GW' => 'Guinea Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'HEARD AND MCDONALD ISLANDS',
        'HN' => 'Honduras',
        'HK' => 'HONG KONG (SAR)',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran',
        'IQ' => 'Iraq',
        'IM' => 'Isle of Mann',
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
        'KG' => 'Kyrgystan',
        'LA' => 'LAO PEOPLE\'S DEMOCRATIC REPUBLIC',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'LIBYA',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'MACAU (SAR)',
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
        'MI' => 'MIDWAY ISLAND',
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
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PS' => 'PALESTINIAN TERRITORIES OCCUPIED',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'CN' => 'People\'s Republic of China',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn Island',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'TW' => 'REPUBLIC OF CHINA (TAIWAN)',
        'HR' => 'REPUBLIC OF CROATIA',
        'IE' => 'Republic of Ireland',
        'KR' => 'REPUBLIC OF KOREA (SOUTH)',
        'MK' => 'REPUBLIC OF MACEDONIA',
        'MD' => 'REPUBLIC OF MOLDOVA',
        'CG' => 'REPUBLIC OF THE CONGO',
        'YE' => 'REPUBLIC OF YEMEN',
        'RE' => 'Reunion Island',
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'SH' => 'SAINT HELENA',
        'LC' => 'Saint Lucia',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'SERBIA',
        'CS' => 'SERBIA AND MONTENEGRO',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'GS' => 'South Georgia',
        'ES' => 'Spain and Canary Islands',
        'LK' => 'Sri Lanka',
        'KN' => 'St Kitts and Nevis',
        'PM' => 'St Pierre and Miquelon',
        'VC' => 'ST. VINCENT AND THE GRENADINES',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'SVALBARD AND JAN MAYEN ISLANDS',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
        'TJ' => 'Tajikistan',
        'TH' => 'Thailand',
        'TL' => 'Timor Leste',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks and Caicos Islands',
        'TV' => 'Tuvalu',
        'UM' => 'U.S. MINOR OUTLYING ISLANDS',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'TZ' => 'UNITED REPUBLIC OF TANZANIA',
        'US' => 'United States of America',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City State',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'VG' => 'VIRGIN ISLANDS (BRITISH)',
        'VI' => 'VIRGIN ISLANDS (U.S.)',
        'WF' => 'Wallis and Futuna Islands',
        'EH' => 'Western Sahara',
        'WS' => 'Western Samoa',
        'YU' => 'Yugoslavia',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    protected static $titleMap = [
        'Mr'   => 'M_Mr',
        'Mrs'  => 'F_Mrs',
        'Ms'   => 'F_Ms',
        'Mstr' => 'M_Master',
        'Miss' => 'F_Miss',
        'Pt'   => 'M_Pundit',
        'Shri' => 'M_Shri',
    ];

    protected static $businessTypes = [
        'ADVTPR' => 'Advertising/Event Management/PR',
        'AGRICT' => 'Agriculture',
        'ARCHTS' => 'Architechtural Services',
        'ARTDSG' => 'Art/Designing',
        'AUTOMB' => 'Automobile',
        'AVITON' => 'Aviation',
        'BKNGFS' => 'Banking/Financial Services',
        'BIOTEC' => 'Biotechnology',
        'CRGCUS' => 'Cargo and Courier Services',
        'CHEMCL' => 'Chemicals',
        'CONSMT' => 'Construction/Metals',
        'CONSUL' => 'Consulting',
        'CONDRB' => 'Consumer Durables',
        'EDUTRG' => 'Education/Training',
        'ELCTRN' => 'Electrical and Electronics',
        'ENGGRN' => 'Engineering',
        'ENTMED' => 'Entertainment/Media',
        'ENTRPN' => 'Entrepreneur',
        'ENVIRN' => 'Environment',
        'FASHON' => 'Fashion',
        'FILM'   => 'Film Industry',
        'FMCG'   => 'FMCG',
        'GMTTXL' => 'Garment/Textiles',
        'GEMJWL' => 'Gems and Jewellery',
        'GOVT'   => 'Government',
        'HLTHMD' => 'Healthcare and Medicine',
        'HEVIND' => 'Heavy Industry',
        'HOSTNT' => 'Hospitality/Travel and Tourism',
        'INSRNC' => 'Insurance',
        'INTTRD' => 'International Trade',
        'ITITES' => 'IT/IT Enabled services',
        'LEGAL'  => 'Law',
        'MFGTRN' => 'Manufacturing',
        'MARINE' => 'Marine',
        'MINING' => 'Mining',
        'POLGAS' => 'Oil/Gas/Petroleum/',
        'OTHS'   => 'Others',
        'PLYRBR' => 'Polymer/Rubber',
        'PRNPCK' => 'Printing/Packaging',
        'POINPR' => 'Projects/Infrastructure/Power',
        'PSU'    => 'Public Sector Undertaking',
        'RND'    => 'R & D',
        'REALTY' => 'Real Estate/Property',
        'RETAIL' => 'Retail',
        'SCTECH' => 'Science and Technology',
        'SHPNG'  => 'Shipping',
        'SME'    => 'Small and Medium Enterprise',
        'SOCSVC' => 'Social Services',
        'SPORTS' => 'Sports',
        'TELCOM' => 'Telecommunications',
        'TBCO'   => 'Tobacco',
        'TRDHSE' => 'Trading Houses',
        'TRNSPT' => 'Transportation',
        'WDFBRE' => 'Wood and Fibre',
    ];

    protected static $jobTitles = [
        1  => 'Asst. Mgr',
        2  => 'CEO',
        3  => 'Chairman',
        4  => 'Chief Minister',
        5  => 'Chief Secretary',
        6  => 'Department Head',
        7  => 'Diplomats',
        8  => 'Director',
        9  => 'Executive Assistant',
        10 => 'General Manager',
        11 => 'Governor',
        12 => 'Manager',
        13 => 'Member of Parliament',
        14 => 'Minister of State',
        15 => 'Othr Professionals',
        16 => 'Owners',
        17 => 'President',
        18 => 'Sr. General Mgr',
        19 => 'Trainee',
        20 => 'Union Minister',
        21 => 'Vice President',
    ];

    public function InitBrowser()
    {
        $this->UseCurlBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->http->setDefaultHeader("User-Agent", \HttpBrowser::PROXY_USER_AGENT);
        }
    }

    public function registerAccount(array $fields)
    {
        $this->http->FilterHTML = false;

        if ($fields['AddressType'] === 'B') {
            foreach (['Company', 'BusinessCategory', 'JobTitle', 'Designation'] as $f) {
                if (empty($fields[$f])) {
                    throw new \UserInputError(sprintf('Field %s is required for business addresses', $f));
                }
            }
        }

        /* @var \stdClass $city */
        $city = $this->getCity($fields['Country'], $fields['City']);

        if (!isset($city)) {
            throw new \UserInputError('Invalid city');
        } elseif (false === $city) {
            return false;
        }
        $fields['CityName'] = $city->CityName;
        $fields['CityCode'] = $city->CityCode;

        $this->http->GetURL('http://www.jetairways.com/EN/US/JetPrivilege/enrol-now.aspx');

        if (!$this->http->ParseForm()) {
            return false;
        }
        $intersect = array_intersect_key($this->http->Form, $this->requestFields);
        $this->http->Form = array_merge($this->requestFields, $intersect);

        if (isset(self::$titleMap[$fields['Title']])) {
            $fields['Title'] = self::$titleMap[$fields['Title']];
        } else {
            $fields['Title'] = '0';
        }
        $fields['Gender'] = ['M' => '1', 'F' => '2'][$fields['Gender']];
        $fields['AddressType'] = ['B' => '0', 'H' => '1'][$fields['AddressType']];
        $fields['FullNumber'] = sprintf('+%s%s%s', $fields['PhoneCountryCodeNumeric'], $fields['PhoneAreaCode'], $fields['PhoneLocalNumber']);
        $fields['ShortNumber'] = sprintf('%s%s', $fields['PhoneAreaCode'], $fields['PhoneLocalNumber']);
        $fields['DOB'] = sprintf('%d-%s-%d', $fields['BirthDay'], \DateTime::createFromFormat('!m', $fields['BirthMonth'])->format('M'), $fields['BirthYear']);

        foreach ([
            'ddlsalutation'        => 'Title',
            'txtFname'             => 'FirstName',
            'txtMname'             => 'MiddleName',
            'txtLname'             => 'LastName',
            'gender'               => 'Gender',
            'ddlCOR'               => 'Country',
            'ddlCitizen'           => 'Nationality',
            'txtDOB'               => 'DOB',
            'txtMobileNumber'      => 'FullNumber',
            'txtEmailAddr'         => 'Email',
            'txtPassword'          => 'Password',
            'txtRePassword'        => 'Password',
            'hdnPhoneNo'           => 'ShortNumber',
            'hdnISDCode'           => 'PhoneCountryCodeNumeric',
            'CommunicationAddress' => 'AddressType',
        ] as $name => $field) {
            if (strlen($fields[$field]) > 0) {
                $this->http->Form['ctl00$MainBody$IWOVID_item_1$' . $name] = $fields[$field];
            }
        }

        if ('1' === $fields['AddressType']) {
            // home
            $map = [
                'txtStateHome'    => 'StateOrProvince',
                'ddlCityHome'     => 'CityCode',
                'txtPostcodeHome' => 'PostalCode',
                'txtAddr1Home'    => 'AddressLine1',
                'txtAddr2Home'    => 'AddressLine2',
                'txtAddr3Home'    => 'AddressLine3',
                'hndCityNameHome' => 'CityName',
            ];
        } else {
            $map = [
                'txtStateBusiness'    => 'StateOrProvince',
                'ddlCityBusiness'     => 'CityCode',
                'txtAddr1Business'    => 'AddressLine1',
                'txtAddr2Business'    => 'AddressLine2',
                'txtAddr3Business'    => 'AddressLine3',
                'hndCityNameBusiness' => 'CityName',
                'txtPostcodeBusiness' => 'PostalCode',
                'txtCompanyName'      => 'Company',
                'ddlBusinessCategory' => 'BusinessCategory',
                'ddlJobTitle'         => 'JobTitle',
                'txtDesignation'      => 'Designation',
            ];
        }

        foreach ($map as $name => $field) {
            if (strlen($fields[$field]) > 0) {
                $this->http->Form['ctl00$MainBody$IWOVID_item_1$' . $name] = $fields[$field];
            }
        }

        $this->http->Form['ctl00$MainBody$IWOVID_item_1$chkTermsAndCondition'] = 'on';
        $this->http->Form['ctl00$MainBody$IWOVID_item_1$btnEnroll'] = 'Enrol';

        $this->http->FormURL = 'http://www.jetairways.com/EN/US/JetPrivilege/enrol-now.aspx';

        if (!$this->http->PostForm()) {
            return false;
        }

        $errors = $this->http->FindNodes('//div[@id="MainBody_IWOVID_item_1_dvlblErrorMsg"]/span');

        if (count($errors) > 0) {
            throw new \UserInputError(implode('. ', $errors));
        }

        $number = $this->http->FindSingleNode('//*[@id="MainBody_IWOVID_item_1_lblJPMembershipNumber"]');

        if (!empty($number)) {
            $this->http->Log('found number: ' . $number);
            $this->ErrorMessage = $this->http->FindSingleNode('//div[@class="successicon"]/parent::div');

            return true;
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Title' => [
                'Caption'  => 'Title',
                'Type'     => 'string',
                'Required' => true,
                'Options'  => [
                    'Mr'     => 'Mr',
                    'Mrs'    => 'Mrs',
                    'Ms'     => 'Ms',
                    'Mstr'   => 'Master',
                    'Miss'   => 'Miss',
                    'Pt'     => 'Pundit',
                    'Shri'   => 'Shri',
                    'Brig'   => 'Brig',
                    'Capt'   => 'Capt',
                    'Col'    => 'Col',
                    'Jstc'   => 'Jstc',
                    'Lt Col' => 'Lt Col',
                    'Mjr'    => 'Mjr',
                    'Prof'   => 'Prof',
                ],
            ],
            'FirstName' => [
                'Caption'  => 'First Name (as it appears on your passport / driving license / election photo ID or a photo credit card)',
                'Type'     => 'string',
                'Required' => true,
            ],
            'MiddleName' => [
                'Caption'  => 'Middle Name (as it appears on your passport / driving license / election photo ID or a photo credit card)',
                'Type'     => 'string',
                'Required' => false,
            ],
            'LastName' => [
                'Caption'  => 'Last Name (as it appears on your passport / driving license / election photo ID or a photo credit card)',
                'Type'     => 'string',
                'Required' => true,
            ],
            'BirthDay' => [
                'Caption'  => 'Day of Birth Date',
                'Type'     => 'integer',
                'Required' => true,
            ],
            'BirthMonth' => [
                'Caption'  => 'Month of Birth Date',
                'Type'     => 'integer',
                'Required' => true,
            ],
            'BirthYear' => [
                'Caption'  => 'Year of Birth Date',
                'Type'     => 'integer',
                'Required' => true,
            ],
            'Gender' => [
                'Caption'  => 'Gender',
                'Type'     => 'string',
                'Required' => true,
                'Options'  => [
                    'M' => 'Male',
                    'F' => 'Female',
                ],
            ],
            'PhoneCountryCodeNumeric' => [
                'Caption'  => 'Country phone code, mobile',
                'Type'     => 'string',
                'Required' => true,
            ],
            'PhoneAreaCode' => [
                'Caption'  => 'Phone Area Code, mobile',
                'Type'     => 'string',
                'Required' => true,
            ],
            'PhoneLocalNumber' => [
                'Caption'  => 'Phone Number, mobile',
                'Type'     => 'string',
                'Required' => true,
            ],
            'Country' => [
                'Caption'  => 'Country Code',
                'Type'     => 'string',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'Nationality' => [
                'Caption'  => 'Citizenship',
                'Type'     => 'string',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'Email' => [
                'Caption'  => 'Email',
                'Type'     => 'string',
                'Required' => true,
            ],
            'Password' => [
                'Caption'  => 'Password (must be 7-8 characters long and consist of atleast 1 lowercase character and 1 numeric digit)',
                'Type'     => 'string',
                'Required' => true,
            ],
            'AddressType' => [
                'Caption'  => 'Address Type',
                'Type'     => 'string',
                'Required' => true,
                'Options'  => [
                    'H' => 'Home',
                    'B' => 'Business',
                ],
            ],
            'Company' => [
                'Caption'  => 'Company (required for business address type)',
                'Type'     => 'string',
                'Required' => false,
            ],
            'BusinessCategory' => [
                'Caption'  => 'Business Category (required for business address type)',
                'Type'     => 'string',
                'Required' => false,
                'Options'  => self::$businessTypes,
            ],
            'JobTitle' => [
                'Caption'  => 'Job Title (required for business address type)',
                'Type'     => 'string',
                'Required' => false,
                'Options'  => self::$jobTitles,
            ],
            'Designation' => [
                'Caption'  => 'Designation (required for business address type)',
                'Type'     => 'string',
                'Required' => false,
            ],
            'StateOrProvince' => [
                'Caption'  => 'State',
                'Type'     => 'string',
                'Required' => true,
            ],
            'City' => [
                'Caption'  => 'City (name or code)',
                'Type'     => 'string',
                'Required' => true,
            ],
            'PostalCode' => [
                'Caption'  => 'Postal Code',
                'Type'     => 'string',
                'Required' => true,
            ],
            'AddressLine1' => [
                'Caption'  => 'Address Line 1 (House Number, Street Name, Street Type, Street Direction, Building, Floor. Home Address Line 1 should not start with a number, special character or blank space or it has some invalid characters)',
                'Type'     => 'string',
                'Required' => true,
            ],
            'AddressLine2' => [
                'Caption'  => 'Address Line 2 (Apartment)',
                'Type'     => 'string',
                'Required' => true,
            ],
            'AddressLine3' => [
                'Caption'  => 'Address Line 3 (Locality Province Abbreviation)',
                'Type'     => 'string',
                'Required' => false,
            ],
        ];
    }

    protected function getCity($country, $search)
    {
        $http = new \HttpBrowser('none', new \CurlDriver());
        $http->setDefaultHeader('X-Requested-With', 'XMLHttpRequest');
        $http->setDefaultHeader('Content-Type', 'application/json; charset=UTF-8');
        $http->setDefaultHeader('Accept', 'application/json, text/javascript, */*; q=0.01');
        $http->setDefaultHeader('Origin', 'http://www.jetairways.com');
        $http->PostURL('http://www.jetairways.com/JPEnhancementGetData.aspx/GetAllCitiesByCountryCode', sprintf('{countrycode: \'%s\'}', $country));
        $cities = @json_decode($http->Response['body']);

        if ($cities === false || !isset($cities->d) || !is_array($cities->d)) {
            $this->http->Log('invalid data from cities request: ' . json_encode($cities), LOG_LEVEL_ERROR);

            return false;
        }

        foreach ($cities->d as $city) {
            if ($city->CityID === $search || $city->CityName === $search) {
                return $city;
            }
        }

        return null;
    }

    /* registered creds: 201964254/p4ssw0rd, 201973380/p4ssw0rd */
}
