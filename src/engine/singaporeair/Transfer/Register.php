<?php

namespace AwardWallet\Engine\singaporeair\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountCheckerSingaporeair
{
    use \SeleniumCheckerHelper {
        waitForElement as traitWaitForElement;
    }

    use ProxyList;

    public static $inputFieldsMap = [
        'Title'              => 'title',
        'FirstName'          => 'givenName',
        'LastName'           => 'familyName',
        'ParentTitle'        => 'guardianTitle',
        'ParentFirstName'    => 'guardianFirstName',
        'ParentLastName'     => 'guardianLastName',
        'ParentRelationship' => 'guardian-info-relationship',
        'ParentMemberShip'   => 'membership-number',
        'Gender'             => 'gender',
        'BirthDay'           => 'birthDay',
        'BirthMonth'         => 'birthMonth',
        'BirthYear'          => 'birthYear',
        'Nationality'        => 'nationalityInput',
        'Email'              =>
            [
                0 => 'email',
                1 => 'confirmationEmail',
            ],
        'PhoneType'                  => false,
        'PhoneCountryCodeAlphabetic' => false,
        // 'PhoneCountryCodeNumeric' => false,
        'PhoneAreaCode'    => false,
        'PhoneLocalNumber' => false,
        // 'NameOnCardFormat' => 'nameOnCard',
        'Password' =>
            [
                0 => 'pin',
                1 => 'confirmPin',
            ],
        'SecurityQuestionType1'   => 'securityQuestionl',
        'SecurityQuestionAnswer1' => 'securityAnswer',
        'AddressType'             => 'addressType',
        'AddressLine1'            => 'AddressPrimary',
        'AddressLine2'            => 'AddressSecondary',
        'AddressLine3'            => 'AddressTertiary',
        'AddressLine4'            => 'AddressQuaternary',
        'City'                    => 'city', // See comment to city input field above
        'Country'                 => 'countryInput',
        'PostalCode'              => 'postCode',
        'StateOrProvince'         => 'state',
        //		'ReceiveElectronicAccountStatements' => 'eNewsFlag',
        //		'ReceiveKrisFlyerElectronicNewsletter' => 'kfPromoFlag',
        //		'ReceiveSingaporeAirlinesAndKrisFlyerPromotions' => 'receiveSIAPromo',
        //		'ReceivePartnerPromotions' => 'partnerPromoFlag',
        'ReceiveElectronicAccountStatements'             => 'receiveEmailStatment',
        'ReceiveKrisFlyerElectronicNewsletter'           => 'eNewsFlag',
        'ReceiveSingaporeAirlinesAndKrisFlyerPromotions' => 'receiveSIAPromo',
        'ReceivePartnerPromotions'                       => 'partnerPromoFlag',
        'PromoCode'                                      => 'promoCode',
    ];
    public static $titles = [
        'Dr'   => 'Dr',
        'Mdm'  => 'Mdm',
        'Miss' => 'Miss',
        'Mr'   => 'Mr',
        'Mrs'  => 'Mrs',
        'Ms'   => 'Ms',
        'Mstr' => 'Mstr',
        'Prof' => 'Prof',
        // Too hard to match with gender, limit titles to only most common
        //		'Assoc Prof' => 'Assoc Prof',
        //		'Capt' => 'Capt',
        //		'Count' => 'Count',
        //		'Countess' => 'Countess',
        //		'Datin' => 'Datin',
        //		'Datin Seri' => 'Datin Seri',
        //		'Datin Sri' => 'Datin Sri',
        //		'Datin Wira' => 'Datin Wira',
        //		'Dato' => 'Dato',
        //		'Dato Seri' => 'Dato Seri',
        //		'Dato Sri' => 'Dato Sri',
        //		'Dato Wira' => 'Dato Wira',
        //		'Datuk' => 'Datuk',
        //		'Datuk Seri' => 'Datuk Seri',
        //		'Datuk Sri' => 'Datuk Sri',
        //		'Datuk Wira' => 'Datuk Wira',
        //		'Dtn Paduka' => 'Dtn Paduka',
        //		'Duchess' => 'Duchess',
        //		'Duke' => 'Duke',
        //		'Earl' => 'Earl',
        //		'Engku' => 'Engku',
        //		'Father' => 'Father',
        //		'HE' => 'HE',
        //		'HH' => 'HH',
        //		'Hon' => 'Hon',
        //		'HRH' => 'HRH',
        //		'King' => 'King',
        //		'Lady' => 'Lady',
        //		'Lord' => 'Lord',
        //		'Lt Gen' => 'Lt Gen',
        //		'Major' => 'Major',
        //		'President' => 'President',
        //		'Prince' => 'Prince',
        //		'Princess' => 'Princess',
        //		'Prof Dr' => 'Prof Dr',
        //		'Puan Sri' => 'Puan Sri',
        //		'Queen' => 'Queen',
        //		'Rev' => 'Rev',
        //		'Senator' => 'Senator',
        //		'Sir' => 'Sir',
        //		'Sultan' => 'Sultan',
        //		'Tan Sri' => 'Tan Sri',
        //		'TanSriDato' => 'Tan Sri Dato',
        //		'TanSri Dtk' => 'Tan Sri Dtk',
        //		'Tengku' => 'Tengku',
        //		'Toh Puan' => 'Toh Puan',
        //		'Tun' => 'Tun',
        //		'Tunku' => 'Tunku',
        //		'Venerable' => 'Venerable'
    ];
    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];
    public static $relationship = [
        'FATHR'  => 'Father',
        'GUARDN' => 'Guardian',
        'MOTHR'  => 'Mother',
    ];
    public static $nameOnCardFormats = [
        'FNLN' => 'First Name, Last Name',
        'LNFN' => 'Last Name, First Name',
    ];
    public static $securityQuestionTypes = [
        'BORNCITY' => 'In what city were you born?',
        'PET'      => 'What is the name of your first pet?',
        'MOTHER'   => 'What is your mother\'s name or maiden name?',
        'DAD'      => 'What is your father\'s name or middle name?',
        'CAR'      => 'What is the brand of your first car?',
        'COLOUR'   => 'What is your favourite colour?',
        'COUNTRY'  => 'What is your favourite country?',
        'SPORT'    => 'What is your favourite sport?',
        'BIRTHDAY' => 'When is your husband or wife\'s birthday (dd/mm/yyyy)?',
    ];
    public static $countries = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AG' => 'Antigua And Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan Republic',
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
        'BA' => 'Bosnia Herzegovina',
        'BW' => 'Botswana',
        'BR' => 'Brazil',
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
        'CC' => 'Cocos Island',
        'CK' => 'Cook Islands',
        'CO' => 'Colombia',
        'CD' => 'Congo (Kinshasa)',
        'CR' => 'Costa Rica',
        'CI' => 'Cote D\'Ivoire',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CW' => 'Curacao',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DO' => 'Dominican Republic',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
        'GM' => 'Gambia',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GR' => 'Greece',
        'GL' => 'Greenland',
        'GD' => 'Grenadines',
        'GP' => 'Guadeloupe',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong SAR',
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
        'LS' => 'Kingdom Of Lesotho',
        'KI' => 'Kiribati',
        'KP' => 'Korea (North)',
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
        'MK' => 'Macedonia',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'MD' => 'Moldova',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'ME' => 'Montenegro',
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
        'MP' => 'Northern Marianas Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'CN' => 'People\'s Republic Of China',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'SC' => 'Republic Of Seychelles',
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'LC' => 'Saint Lucia',
        'WS' => 'Samoa',
        'ST' => 'Sao Tome ',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SX' => 'Sint Maarten',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'KR' => 'South Korea',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'VC' => 'St Vincent And The Grenadines',
        'KN' => 'St. Kills and Nevis',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TJ' => 'Tajlkistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TL' => 'Timor Leste',
        'TG' => 'Togo',
        'TO' => 'Tonga',
        'TT' => 'Trinidad',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks and Caios Islands',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States Of America',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'VG' => 'Virgin Islands (GB)',
        'VI' => 'Virgin Islands(U.S.)',
        'WF' => 'Walls  Islands',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];
    protected $fields;
    protected $countriesStatesCitiesMap;
    private $timeout = 10;
    private $flExt;

    public function InitBrowser()
    {
        parent::InitBrowser(); //in parent use $this->setProxyBrightData();
        //$this->http->SetProxy($this->proxyStaticIpDOP());
        $this->UseSelenium();
        $this->http->saveScreenshots = true;
        $this->useFirefox();
        //$this->usePacFile(false);
    }

    public function registerAccount(array $fields)
    {
        $this->logger->notice('[INFO] ' . __METHOD__);

        $fields['PhoneType'] = 'M';

        $this->logger->notice('[INFO] initial fields:');
        $this->logger->notice(json_encode($fields, JSON_PRETTY_PRINT));

        $this->ArchiveLogs = true;
        $this->fields = $fields;

        $this->checkFields();

        $this->http->removeCookies();

        $this->http->GetURL('https://www.singaporeair.com/en_UK/ppsclub-krisflyer/registration-form/');

        $this->setEnglish();

        $this->saveResponse();

        $ids = $this->http->FindNodes("//input[starts-with(@id,'customSelect')]/@id");
        $startCnt = count($ids);

        if (count($ids) === 0) {
            throw new \EngineError("customSelects not found, site has changed");
        }

        $this->flExt = false;

        if ($fields['BirthYear'] > (date('Y') - 12)) {
            $this->flExt = true;
        }
        $this->logger->debug("detected need parents: " . ($this->flExt ? 'yes' : 'no'));

        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
            [1920, 1080],
        ];
        $this->setScreenResolution($resolutions[array_rand($resolutions)]);

        /* test
        $this->fillTextInputs(['Country']);
        $this->fillSelects();
        $this->fillTextInputs();
        //$this->fillTextInputs(['City']);
        $this->fillSelects(['StateOrProvince']);
        $this->fillSelects(['City']);
        if ($this->flExt){
            $this->fillTextInputs(['Nationality','Password']);
            $this->fillSelects(['City']);
        }
        */

        $this->fillSelects(['Title']);
        $this->fillTextInputs(['FirstName', 'LastName']);
        $this->fillRadioButtons();
        $this->fillTextInputs(['Nationality']);
        $this->fillSelects(['BirthDay', 'BirthMonth', 'BirthYear']);

        if ($this->flExt) {
            $this->fillSelects(['ParentTitle']);
            $this->fillTextInputs(['ParentFirstName', 'ParentLastName']);
            $this->fillSelects(['ParentRelationship']);
        }
        $this->fillTextInputs(['Email', 'PhoneCountryCodeAlphabetic', 'PhoneAreaCode', 'PhoneLocalNumber']);
        $this->fillSelects(['AddressType']);
        $this->fillTextInputs(['AddressLine1', 'AddressLine2', 'AddressLine3']);
        $this->fillTextInputs(['Country']);
        sleep(2);
        $this->fillSelects(['StateOrProvince']);
        sleep(2);
        $this->fillSelects(['City']);
        $this->fillTextInputs(['PostalCode', 'Password']);
        $this->fillSelects(['SecurityQuestionType1']);
        $this->fillTextInputs(['SecurityQuestionAnswer1', 'PromoCode']);
        //control shoot
        $ids = $this->http->FindNodes("//input[starts-with(@id,'customSelect')]/@id");

        if ($startCnt == count($ids)) {
            throw new \EngineError("some script at Site not worked, customSelects of state not created");
        }
        $this->fillSelects(['StateOrProvince']);
        $this->fillSelects(['City']);

        $this->saveResponse();
        /* test
                $this->fillRadioButtons();
        */
        // Click "accept user agreement" checkbox
        $this->driver->executeScript('document.querySelector("input[name=\'tncAggreement\']").click()');
        //		$this->driver->executeScript('$("input[name=tncAggreement]").click()');
        $this->saveResponse();
        $this->submit();

        return true;
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
                    'Caption'  => 'First/Given name',
                    'Required' => true,
                ],
            'LastName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Last/Family name ',
                    'Required' => true,
                ],
            'Gender' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Gender',
                    'Required' => true,
                    'Options'  => self::$genders,
                ],
            'BirthDay' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Birth Day',
                    'Required' => true,
                ],
            'BirthMonth' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Birth Month',
                    'Required' => true,
                ],
            'BirthYear' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Birth Year',
                    'Required' => true,
                ],
            'ParentTitle' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Parent Title (required for children under the age of 12)',
                    'Required' => false,
                    'Options'  => self::$titles,
                ],
            'ParentFirstName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Parent First/Given name (required for children under the age of 12)',
                    'Required' => false,
                ],
            'ParentLastName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Parent Last/Family name (required for children under the age of 12)',
                    'Required' => false,
                ],
            'ParentRelationship' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Relationship (required for children under the age of 12)',
                    'Required' => false,
                    'Options'  => self::$relationship,
                ],
            'ParentMembershipNumber' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'KrisFlyer membership number (required for children under the age of 12, an optional field)',
                    'Required' => false,
                ],
            'Nationality' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Nationality, country code',
                    'Required' => true,
                    'Options'  => self::$countries,
                ],
            'Email' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Email',
                    'Required' => true,
                ],
            // 'PhoneType' => array (
            // 	'Type' => 'string',
            // 	'Caption' => 'Phone Type',
            // 	'Options' => array (
            // 		// 'H' => 'Home',
            // 		// 'B' => 'Business',
            // 		'M' => 'Mobile'
            // 	),
            // 	'Required' => true,
            // ),
            'PhoneCountryCodeAlphabetic' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Country Code (alphabetic)',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            // 'PhoneCountryCodeNumeric' => array (
            // 	'Type' => 'string',
            // 	'Caption' => 'Phone Country Code (numeric)',
            // 	'Required' => true,
            // ),
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
            //'NameOnCardFormat' =>
            //	array(
            //		'Type' => 'string',
            //		'Caption' => 'Name to be displayed on KrisFlyer membership card',
            //		'Required' => true,
            //		'Options' => self::$nameOnCardFormats,
            //	),
            'Password' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => '6-digit PIN',
                    'Required' => true,
                ],
            'SecurityQuestionType1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Security Question Type',
                    'Required' => true,
                    'Options'  => self::$securityQuestionTypes,
                ],
            'SecurityQuestionAnswer1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Security Question Answer',
                    'Required' => true,
                ],
            'AddressType' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Type',
                    'Required' => true,
                    'Options'  =>
                        [
                            'H' => 'Home',
                            'B' => 'Business',
                            'O' => 'Other',
                        ],
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
            'AddressLine3' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Line 3',
                    'Required' => false,
                ],
            // See comment for City field
            //			'AddressLine4' =>
            //				array (
            //					'Type' => 'string',
            //					'Caption' => 'Address Line 4',
            //					'Required' => false,
            //				),
            'Country' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Country code',
                    'Required' => true,
                    'Options'  => self::$countries,
                ],
            'PostalCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Zip / postcode (mandatory for Singapore), without spaces',
                    'Required' => false,
                ],
            'StateOrProvince' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'State/Province (Not mandatory if the country has no State/Province)',
                    'Required' => false,
                ],
            'City' =>
            // Instead of storing 30k+ cities like Singapore site, we set provider City field to unknown and map
            // AW City field to provider AddressLine4 field (as requested by Giift).
                [
                    'Type'     => 'string',
                    'Caption'  => 'City',
                    'Required' => true,
                ],
            /*
            'ReceiveElectronicAccountStatements' =>
                array(
                    'Type' => 'boolean',
                    'Caption' => 'Receive electronic account statements',
                    'Required' => true,
                ),
            'ReceiveKrisFlyerElectronicNewsletter' =>
                array(
                    'Type' => 'boolean',
                    'Caption' => 'Receive KrisFlyer electronic newsletter',
                    'Required' => true,
                ),
            'ReceiveSingaporeAirlinesAndKrisFlyerPromotions' =>
                array(
                    'Type' => 'boolean',
                    'Caption' => 'Receive Singapore Airlines and KrisFlyer promotions',
                    'Required' => true,
                ),
            'ReceivePartnerPromotions' =>
                array(
                    'Type' => 'boolean',
                    'Caption' => 'Receive partner promotions',
                    'Required' => true,
                ),
            */
            'PromoCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Promotional code (if you have one)',
                    'Required' => false,
                ],
        ];
    }

    protected function checkFields()
    {
        $countriesStatesCitiesMapJson = file_get_contents(__DIR__ . '/CountriesStatesCities.json');

        if (!$countriesStatesCitiesMapJson) {
            throw new \EngineError('Failed to open countries/states/cities JSON');
        }

        if ((in_array($this->fields['Title'], ['Mdm', 'Miss', 'Mrs', 'Ms']) && $this->fields['Gender'] !== 'F')
            || (in_array($this->fields['Title'], ['Mr', 'Mstr']) && $this->fields['Gender'] !== 'M')
        ) {
            throw new \UserInputError('Discrepancy between Title and Gender');
        }

        $this->countriesStatesCitiesMap = json_decode($countriesStatesCitiesMapJson, true);

        if ($this->countriesStatesCitiesMap === null) {
            throw new \EngineError('Failed to open countries/states/cities JSON');
        }

        $birthDate = strtotime($this->fields['BirthYear'] . '-' . $this->fields['BirthMonth'] . '-' . $this->fields['BirthDay']);

        if (strtotime('+2 years', $birthDate) > strtotime(date('Y-m-d'))) {
            throw new \UserInputError("A KrisFlyer member must be at least 2 years old");
        }

        if ((int) $this->fields['BirthYear'] + 12 > date('Y')) {
            if (empty($this->fields['ParentTitle']) || empty($this->fields['ParentFirstName']) || empty($this->fields['ParentLastName']) || empty($this->fields['ParentRelationship'])) {
                throw new \UserInputError("To register kid as KrisFlyer member, you should enter 'Parent'-fields");
            }

            if ((in_array($this->fields['ParentTitle'], ['Mdm', 'Miss', 'Mrs', 'Ms']) && $this->fields['ParentRelationship'] !== 'MOTHR')
                || (in_array($this->fields['ParentTitle'], ['Mr', 'Mstr']) && $this->fields['ParentRelationship'] !== 'FATHR')
            ) {
                throw new \UserInputError('Discrepancy between ParentTitle and ParentRelationship');
            }
        }

        $country = $this->fields['Country'];
        $state = $this->fields['StateOrProvince'];

        if (!$country) {
            throw new \UserInputError('Country is required');
        }

        if (!isset(self::$countries[$country])) {
            throw new \UserInputError("Invalid country code \"$country\"");
        }

        if ($states = $this->statesByCountry($country)) {
            if (!$state) {
                throw new \UserInputError('State is required for country "' . self::$countries[$country] . '"');
            }

            if (!isset($states[$state])) {
                throw new \UserInputError('Invalid state code "' . $state . '" for country "' . self::$countries[$country] . '"');
            }
            $this->fields['StateOrProvinceTextValue'] = $states[$state];
        }
    }

    protected function statesByCountry($countryCode)
    {
        // TODO: Optimize
        if (!$this->countriesStatesCitiesMap) {
            throw new \EngineError('Countries/states/cities map is not loaded');
        }
        $targetIndex = false;

        foreach ($this->countriesStatesCitiesMap as $index => $value) {
            if ($value['Code'] == $countryCode) {
                $targetIndex = $index;

                break;
            }
        }

        if ($targetIndex === false) {
            throw new \EngineError("Country code \"$countryCode\" not found in countries/states/cities map");
        }

        return $this->countriesStatesCitiesMap[$targetIndex]['States'];
    }

    protected function fillTextInputs($textInputs = null)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice('$textInputs: ' . var_export($textInputs, true));

        if (!$textInputs) {
            $textInputs = [
                'FirstName',
                'LastName',
                'Nationality',
                //'Country',
                // 'StateOrProvince',
                'Email',
                'PhoneAreaCode',
                'PhoneLocalNumber',
                'Password',
                'SecurityQuestionAnswer1',
                'AddressLine1',
                'AddressLine2',
                'AddressLine3',
                // 'City',
                'PostalCode',
                'PromoCode',
            ];

            if ($this->flExt) {
                $textInputs = array_merge($textInputs,
                    [
                        'ParentFirstName',
                        'ParentLastName',
                        'ParentMemberShip',
                    ]);
            }
        }
        $phoneFieldsMap = [
            'M' => [
                'PhoneCountryCodeAlphabetic' => 'mobileNumberCountryInput',
                'PhoneAreaCode'              => 'mobileAreaCode',
                'PhoneLocalNumber'           => 'mobilePhoneNumber',
            ],
            'B' => [
                'PhoneCountryCodeAlphabetic' => 'businessNumberCountryInput',
                'PhoneAreaCode'              => 'businessNumberAreaCode',
                'PhoneLocalNumber'           => 'businessPhoneNumber',
            ],
            'H' => [
                'PhoneCountryCodeAlphabetic' => 'otherNumberCountryInput',
                'PhoneAreaCode'              => 'otherNumberAreaCode',
                'PhoneLocalNumber'           => 'otherPhoneNumber',
            ],
        ];

        foreach ($textInputs as $awKey) {
            $this->saveResponse();

            if (!isset(self::$inputFieldsMap[$awKey])) {
                throw new \EngineError("No input field map found for $awKey");
            }

            switch ($awKey) {
                case 'PhoneCountryCodeAlphabetic':
                    if (isset($this->fields['PhoneType'])) {
                        if (isset($phoneFieldsMap[$this->fields['PhoneType']][$awKey])) {
                            $provKeys = $phoneFieldsMap[$this->fields['PhoneType']][$awKey];
                        } else {
                            $this->logger->warning('No available variant in phone fields map for ' . $this->fields['PhoneType'] . ' and ' . $awKey);
                            $provKeys = false;
                        }
                    } else {
                        $this->logger->warning('PhoneType is not set');
                        $provKeys = false;
                    }

                    break;

                case 'PhoneAreaCode':
                case 'PhoneLocalNumber':
                    if (isset($this->fields['PhoneType'])) {
                        if (isset($phoneFieldsMap[$this->fields['PhoneType']][$awKey])) {
                            $provKeys = $phoneFieldsMap[$this->fields['PhoneType']][$awKey];
                        } else {
                            $this->logger->warning('No available variant in phone fields map for ' . $this->fields['PhoneType'] . ' and ' . $awKey);
                            $provKeys = false;
                        }
                    } else {
                        $this->logger->warning('PhoneType is not set');
                        $provKeys = false;
                    }

                    break;

                default:
                    $provKeys = self::$inputFieldsMap[$awKey];
            }
            // $this->log('val', $this->fields[$awKey]);
            if (!isset($this->fields[$awKey]) or $provKeys === false) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }
            $value = $this->fields[$awKey];

            switch ($awKey) {
                case 'Nationality':
                case 'Country':
                    $value = self::$countries[$value];

                    break;

                case 'PhoneCountryCodeAlphabetic':
                    $phoneCountry = self::$countries[$this->fields['PhoneCountryCodeAlphabetic']];
                    $xpath = sprintf('(//option[contains(text(), "%s (")]) [1]', $phoneCountry);
                    $elem = $this->waitForElement(\WebDriverBy::xpath($xpath), 15, false);

                    if (!$elem) {
                        throw new \UserInputError('Invalid country code');
                    }
                    $value = $elem->getAttribute('data-text');
                    $this->logger->notice(sprintf('[INFO] phone country value = %s', $value));

                    break;
            }
            $mover = new \MouseMover($this->driver);
            $mover->logger = $this->logger;
            $mover->duration = rand(90000, 120000);
            $mover->steps = rand(50, 70);

            foreach ($provKeys as $provKey) {
                $inputXpath = "//form[@id='form-registration']//input[@name = '$provKey' or @id  = '$provKey']";
                $elem = $this->waitForElement(\WebDriverBy::xpath($inputXpath));

                if (!$elem) {
                    throw new \EngineError('Failed to find input field with name "' . $provKey . '" for "' . $awKey . '"');
                }
                //$mover->moveToElement($elem);
                //$mover->click();
                if ($awKey == 'City') {
                    $id = $this->http->FindSingleNode("//select[@id = \"" . $provKey . "\"]/following-sibling::input[starts-with(@id,'customSelect')]/@id");

                    if (!empty($id)) {
                        $id = $this->http->FindPreg("#(customSelect\-\d+)\-#", false, $id);
                    }

                    if (!empty($id)) {
                        //$provKey = $id;

                        $scr = "if((opt=document.querySelectorAll('#$id-listbox>li[data-value]'))) opt.forEach(function (p) {if(p.innerText=='$value')p.click();});";
                        $this->logger->notice("City - $scr");
                        $this->driver->executeScript($scr);
                    }
                } else {
                    $elem->clear();
                    $this->logger->debug("Sending to: " . $provKey . ", value:" . $value);
                    //$mover->sendKeys($elem, $value, 10);
                    $elem->sendKeys($value);
                    $this->logger->debug("Done");

                    if (!in_array($provKey, ['nationalityInput', 'mobileNumberCountryInput', 'businessNumberCountryInput', 'otherNumberCountryInput'])) {
                        $script = "
							document.querySelector(\"input[name = '{$provKey}'],input[id ='{$provKey}']\").value;
						";
                        $result = $this->driver->executeScript($script);
                        $this->logger->error('checked value: ' . $result);

                        if ($result !== $value) {
                            //in_array($provKey,['givenName', 'familyName', 'guardianFirstName', 'guardianLastName','pin','confirmPin','mobilePhoneNumber','email','confirmationEmail'])) {
                            $script = "document.querySelector(\"input[name='{$provKey}'],input[id ='{$provKey}']\").value = '{$value}';";
                            $this->driver->executeScript($script);
                            $this->logger->debug("Executed script: [" . $script . "]");
                        }
                    }

                    if (in_array($provKey, ['countryInput', 'nationalityInput'])) {
                        $script = "
							option = document.querySelector(\"div[class*='jspPane']\");
							if (option)
								if((option2=option.querySelector(\"li[data-value = '" . $value . "']\")))
									option2.click();
					";
                        $this->logger->notice('now exec script: ' . $script);
                        $this->driver->executeScript($script);
                    }
                    //					$this->driver->executeScript("$('#{$provKey}').val('{$value}')");
                }
            }

            sleep(1);
        }
    }

    protected function waitForElement(\WebDriverBy $by, $timeout = 15, $visible = true)
    {
        return $this->traitWaitForElement($by, $timeout, $visible);
    }

    protected function fillSelects($selectInputs = null)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice('$selectInputs: ' . var_export($selectInputs, true));

        if (!$selectInputs) {
            $selectInputs = [
                'PhoneCountryCodeAlphabetic',
                // 'Country',
                'StateOrProvince',
                // 'City',
                'SecurityQuestionType1',
                // order matters?
                'Title',
                'BirthDay',
                'BirthMonth',
                'BirthYear',
            ];

            if ($this->flExt) {
                $selectInputs = array_merge($selectInputs,
                    [
                        'ParentTitle',
                        'ParentRelationship',
                    ]);
            }
        }
        $phoneFieldsMap = [
            'M' => 'mobileNumberCountryInput',
            'B' => 'businessNumberCountryInput',
            'H' => 'otherNumberCountryInput',
        ];

        if ($this->fields['Country'] == 'VC') {
            // Provider uses wrong country code for Saint Vincent and the Grenadines (ML instead of standard VC)
            // Map from our standard ISO code to wrong code used by provider
            $this->fields['Country'] = 'ML';
            $this->logger->debug('Mapped standard country code "VC" to provider code "ML"');
        }

        foreach ($selectInputs as $awKey) {
            $this->saveResponse();

            if (!isset(self::$inputFieldsMap[$awKey])) {
                throw new \EngineError("No input field map found for $awKey");
            }

            switch ($awKey) {
                case 'City':
                    $provKeys = 'city';

                    break;

                case 'PhoneCountryCodeAlphabetic':
                    if (isset($this->fields['PhoneType'])) {
                        if (isset($phoneFieldsMap[$this->fields['PhoneType']])) {
                            $provKeys = $phoneFieldsMap[$this->fields['PhoneType']];
                        } else {
                            $this->logger->warning('No available variant in phone fields map for ' . $this->fields['PhoneType'] . ' and ' . $awKey);
                            $provKeys = false;
                        }
                    } else {
                        $this->logger->warning('PhoneType is not set');
                        $provKeys = false;
                    }

                    break;

                default:
                    $provKeys = self::$inputFieldsMap[$awKey];

                    break;
            }

            if (!isset($this->fields[$awKey]) or $provKeys === false) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }
            $value = $this->fields[$awKey];

            switch ($awKey) {
                case 'StateOrProvince':
                    // if (isset($this->fields['StateOrProvinceTextValue'])) {
                    // 	$newValue = $this->fields['StateOrProvinceTextValue'];
                    // 	$this->logger->debug('State code "' . $value . '" converted to full state name "' . $newValue . '" to fill input field');
                    // 	$value = $newValue;
                    // }
                    break;

                case 'SecurityQuestionType1':
                    // $value = self::$securityQuestionTypes[$value];
                    break;

                case 'City':
                    $value = 'UNK';

                    break;

                case 'Country':
                    $value = self::$countries[$this->fields['PhoneCountryCodeAlphabetic']];

                    break;

                case 'PhoneCountryCodeAlphabetic':
                    $phoneCountry = self::$countries[$this->fields['PhoneCountryCodeAlphabetic']];
                    $xpath = sprintf('(//option[contains(text(), "%s (")]) [1]', $phoneCountry);
                    $elem = $this->waitForElement(\WebDriverBy::xpath($xpath), 15, false);

                    if (!$elem) {
                        throw new \UserInputError('Invalid country code');
                    }
                    $value = $elem->getAttribute('data-text');
                    $this->logger->notice(sprintf('[INFO] phone country value = %s', $value));

                    break;

                default:
            }

            foreach ($provKeys as $provKey) {
                // order matters
                $dropSel = sprintf('
					//input[@id = \'%s\']/ancestor::div[1] |
					//select[@id = \'%s\']/ancestor::div[1]
				', $provKey, $provKey);
                $drop = $this->waitForElement(\WebDriverBy::xpath($dropSel));

                if ($drop) {
                    $clicked = false;

                    for ($i = 1; $i <= 2; $i++) {
                        try {
                            $drop->click();
                            sleep(1);
                        } catch (\UnknownServerException $ex) {
                            $this->logger->debug("[try N" . $i . "] click: $dropSel  returned: " . $ex->getMessage());
                            $dropSel = sprintf('
								//input[@id = \'%s\'] |
								//select[@id = \'%s\']
							', $provKey, $provKey);
                            $drop = $this->waitForElement(\WebDriverBy::xpath($dropSel));

                            if (!$drop) {
                                break;
                            }

                            continue;
                        }
                        $this->logger->notice("[$dropSel  clicked]");
                        $clicked = true;

                        break;
                    }

                    if (!$clicked) {
                        //$dropSel = sprintf('
                        //	(//input[@id = "%s"] |
                        //	//select[@id = "%s"])[1]
                        //', $provKey, $provKey);
                        $script = "
							var option = document.evaluate(\"" . $dropSel . "\", document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
							if (option)
								option.click();
						";
                        $this->logger->notice("now exec script: " . $script);
                        $this->driver->executeScript($script);
                    }
                    $this->logger->debug("TRY TO CHECK VALUE id/name...");

                    if (in_array($provKey, ['state', 'city'])) {
                        $this->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML'));
                    }

                    $id = $this->http->FindSingleNode("(//select[@id = \"" . $provKey . "\"]/following-sibling::input[starts-with(@id,'customSelect') and not(starts-with(@id,'customSelect-7') or starts-with(@id,'customSelect-8'))]/@id)[1]");

                    if (empty($id)) {
                        $id = $this->http->FindSingleNode("(//select[@id=\"" . $provKey . "\"]/ancestor::div[1]/descendant::input[starts-with(@id,'customSelect') and not(starts-with(@id,'customSelect-7') or starts-with(@id,'customSelect-8'))]/@id)[1]");
                    }

                    if (!empty($id)) {
                        $id = $this->http->FindPreg("#(customSelect\-\d+)\-#", false, $id);
                    }

                    if (!empty($id)) {
                        //$provKey = $id;
                        $script = "
							var option = document.querySelector('#{$id}-listbox>li[data-value=\"{$value}\"]');
							if (option)
								option.click();
							else {
								if((option = document.querySelector(\"ul[class*='{$provKey}']\")))
									if((option2=option.querySelector(\"li[data-value='{$value}']\")))
										option2.click();
							}
						";
                        $this->logger->notice('now exec script: ' . $script);
                        $this->driver->executeScript($script);
                        $script = "
							return document.querySelector('#{$id}-combobox').value;
						";
                        $result = $this->driver->executeScript($script);
                        $this->logger->error('checked value: ' . $result);
                    } else {
                        $this->logger->notice('not found customSelect-id');

                        if ($provKey === 'countryInput' || $provKey === 'state') {
                            sleep(1);
                        }
                        $script = "
						var option = document.querySelector(\"div[class*='scroll-container']\");
						if (option) {
							if((option2=option.querySelector(\"li[data-value = '" . $value . "']\")))
								option2.click();
							else {
								option = document.querySelector(\"div[class*='jspPane']\");
								if (option)
									if((option2=option.querySelector(\"li[data-value = '" . $value . "']\")))
										option2.click();
							}
						} else {
							option = document.querySelector(\"div[class*='jspPane']\");
							if (option)
								if((option2=option.querySelector(\"li[data-value = '" . $value . "']\")))
									option2.click();
						}
					";
                        $this->logger->notice('now exec script: ' . $script);
                        $this->driver->executeScript($script);
                    }
                } else {
                    throw new \EngineError(sprintf('Element [%s] not found, site has changed', $dropSel));
                }

                //something like confirm checked value - again click
                ///--->
                $cntClick = 0;

                do {
                    $dropSel = sprintf('
					//input[@id = \'%s\']/ancestor::div[1] |
					//select[@id = \'%s\']/ancestor::div[1]
				', $provKey, $provKey);
                    $drop = $this->waitForElement(\WebDriverBy::xpath($dropSel));

                    if ($drop) {
                        $clicked = false;

                        for ($i = 1; $i <= 2; $i++) {
                            try {
                                $drop->click();
                                sleep(1);
                            } catch (\UnknownServerException $ex) {
                                $this->logger->debug("[try N" . $i . "] click: $dropSel  returned: " . $ex->getMessage());
                                $dropSel = sprintf('
								//input[@id = \'%s\'] |
								//select[@id = \'%s\']
							', $provKey, $provKey);
                                $drop = $this->waitForElement(\WebDriverBy::xpath($dropSel));

                                if (!$drop) {
                                    break;
                                }

                                continue;
                            }
                            $this->logger->notice("[$dropSel  clicked]");
                            $clicked = true;

                            break;
                        }

                        if (!$clicked) {
                            //$dropSel = sprintf('
                            //	(//input[@id = "%s"] |
                            //	//select[@id = "%s"])[1]
                            //', $provKey, $provKey);
                            $script = "
							var option = document.evaluate(\"" . $dropSel . "\", document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
							if (option)
								option.click();
						";
                            $this->logger->notice("now exec script: " . $script);
                            $this->driver->executeScript($script);
                        }
                    }
                    $cntClick++;
                } while ($cntClick < 2);
                ///<---
                if ($provKey === 'countryInput' || $provKey === 'state') {
                    sleep(1);
                }

                // $linkXpath = sprintf('
                // 	//select[@id = "%s"]/following::a[1] |
                // 	//input[@id = "%s"]
                // ', $provKey, $provKey);
                // $link = $this->waitForElement(\WebDriverBy::xpath($linkXpath));
                // $link->click();
                // $script = "
                // 	// var option = $('div.scroll-container li[data-value = \"$value\"]:visible');
                // 	// if (option.length > 0)
                // 	// 	option.click();
                // 	option = $('div.jspPane li[data-value = \"$value\"]:visible');
                // 	if (option.lenght > 0)
                // 		option.click();
                // ";
                // $this->driver->executeScript($script);

//				if ($elem = $this->waitForElement(\WebDriverBy::xpath("//form[@id='form-registration']//div[@id='jQform_$provKey']/.."))) {
//					$x = $elem->getLocation()->getX();
//					$y = $elem->getLocation()->getY() - 200;
//					$this->driver->executeScript("window.scrollBy($x, $y)");
//					sleep(1);
//				} else {
//					$this->logger->warning("Failed to find label for $awKey");
//				}

                // $this->driver->executeScript('
                // 		clicker = $("#form-registration #jQform_'.$provKey.' .jQclicker:visible");
                // 		if (typeof clicker !== "undefined") {
                // 			offset = clicker.offset();
                // 			if (offset !== null) {
                // 				t = offset.top;
                // 				$(document).scrollTop(t);
                // 			}
                // 		}
                // 	');

                // sleep(1);

                // $s = '
                // 		clicker = $("#form-registration #jQform_'.$provKey.' .jQclicker:visible");
                // 		clicker.click();
                // 	';
                // $this->logger->debug("Executing script $s");
                // $this->driver->executeScript($s);

//				$elem = $this->waitForElement(\WebDriverBy::xpath("//form[@id='form-registration']//div[@id='jQform_$provKey']//div[@class='jQclicker']"));
//				if (!$elem)
//					throw new \EngineError('Failed to find select button for "'.$awKey.'"');
//				$elem->click();

                // sleep(1);

                // $this->logger->info("Setting $awKey to '$value''");
                // $s = '
                // 		option = $(".jQsuggestionsList:visible li").filter(function() { return $(this).text() == "' . $value . '"; });
                // 		option.click();
                // 	';
                // $this->logger->debug("Executing script $s");
                // $this->driver->executeScript($s);
                // $this->logger->info("Done");
//				$xpath = "//div[contains(@class, 'jQsuggestionsList') and contains(@style, 'block')]//li[normalize-space(.)='".$value."']";
//				$elem = $this->waitForElement(\WebDriverBy::xpath($xpath));
//				if (!$elem)
//					throw new \EngineError('Failed to find select option for "'.$awKey.'"="'.$value.'"');
//				$elem->click();
            }
            sleep(1);
        }
    }

    protected function fillRadioButtons($radioButtonInputs = null)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice('$radioButtonInputs: ' . var_export($radioButtonInputs, true));

        if (!$radioButtonInputs) {
            $radioButtonInputs = [
                'Gender',
                // 'NameOnCardFormat',
                // 'AddressType',
            ];
        }

        foreach ($radioButtonInputs as $awKey) {
            if (!isset(self::$inputFieldsMap[$awKey])) {
                throw new \EngineError("No input field map found for $awKey");
            }
            $provKey = self::$inputFieldsMap[$awKey];

            if (!isset($this->fields[$awKey]) or $provKey === false) {
                continue;
            }
            $value = $this->fields[$awKey];

            if ($provKey === 'gender' && isset(self::$genders[$value])) {
                $provKey .= '-' . self::$genders[$value];
            }

            // $this->driver->executeScript('
            // 		clicker = $("input[name='.$provKey.']").prev();
            // 		if (typeof clicker !== "undefined") {
            // 			offset = clicker.offset();
            // 			if (offset !== null) {
            // 				t = offset.top;
            // 				$(document).scrollTop(t);
            // 			}
            // 		}
            // 	');

            // sleep(1);

            $this->logger->info("Setting $awKey to '$value''");
            $this->driver->executeScript('
					option = document.querySelector("input[id=\'' . $provKey . '\'][value=\'' . $value . '\']");
					if (option)
						option.click();
				');
            /*
                        $this->driver->executeScript('
                                option = $("input[name='.$provKey.'][value='.$value.']");
                                option.click();
                            ');
            */
            $this->logger->info("Done");
        }
    }

    protected function submit()
    {
        $this->logger->notice(__METHOD__);

        $captchaPassingAttempts = 3;

        for ($attempt = 1; $attempt <= $captchaPassingAttempts; $attempt++) {
            $this->passCaptcha();
            //			if ($attempt == 1) {
            //				$captchaResponseField = $this->waitForElement(\WebDriverBy::id('recaptcha_response_field'));
            //				if (!$captchaResponseField)
            //					throw new \EngineError('Failed to find captcha response field');
            //				$captchaResponseField->sendKeys(rand(111, 999));
            //			} elseif ($attempt == 3) {
            //				$this->passCaptcha();
            //			}

            // Submit
            $submitButton = $this->waitForElement(\WebDriverBy::xpath('//input[@type = "submit" and @value = "Continue"]'));

            if (!$submitButton) {
                throw new \EngineError('Failed to find submit button');
            }

            try {
                $submitButton->click();
            } catch (\WebDriverCurlException $e) {
                $msg = $e->getMessage();
                $this->http->log(sprintf('[INFO] exception: %s', $msg));

                throw new \ProviderError('Please check if a verification email has been sent to you');
            }

            $captchaError = $this->waitForElement(\WebDriverBy::xpath('//div[@class="alertMsg"]/p[1]'));

            sleep(5);
            $this->saveResponse();
            $errorNode = $this->http->FindSingleNode('(//div[contains(@class,\'alert\') and contains(@class,\'message\')][normalize-space(.)])[1]');

            if (!empty($errorNode)) {
                if (stripos($errorNode, 'already registered') !== false) {
                    throw new \UserInputError($errorNode);
                } else {
                    throw new \ProviderError($errorNode);
                }
            }

            $errorNodes = $this->http->XPath->query('//*[@class="text-error" and text()] | //*[contains(@id,\'-error\') and normalize-space(.)!=\'\']');

            if ($errorNodes->length > 0) {
                $errors = [];

                foreach ($errorNodes as $e) {
                    $s = trim($e->textContent);

                    if ($s == 'This field is required.') {
                        $s = $this->http->FindSingleNode('../preceding::label[1]', $e);

                        if (!$s) {
                            throw new \EngineError('Failed to find input field title for error message');
                        }
                        $s .= ' is required';
                    }
                    $this->logger->error($s);
                    $errors[] = $s;
                }

                throw new \UserInputError($errors[0]); // Is it always user input error?
            } elseif ($captchaError) {
                $this->logger->error($captchaError->getText());

                if ($attempt < $captchaPassingAttempts) {
                    $this->logger->error('Captcha passing failed, try again');
                    $this->fillTextInputs(['Password']);
                    $this->fillSelects(['SecurityQuestionType1', 'StateOrProvince', 'City']);
                    //$this->fillRadioButtons(['NameOnCardFormat']);
                    continue;
                } else {
                    throw new \EngineError('Captcha passing failed, attempts exceeded, terminating');
                }
            }

            $success = $this->http->FindPreg('/A\s+verification\s+email\s+has\s+been\s+sent\s+to\s+you/i');

            if ($success) {
                $this->logger->info('Registration succeeded');
                $this->ErrorMessage = sprintf('Successfull registration, %s', $success);

                return true;
            }

            if ($this->http->currentUrl() === 'https://www.singaporeair.com/en_UK/ppsclub-krisflyer/registration-form/') {
                $this->logger->error("No data in: " . var_export($this->driver->executeScript("return document.activeElement.getAttributeNode('id');"), true));
            }

            throw new \EngineError('Unsupported response to registration request received from site');
        }

        throw new \EngineError('This should never happen');
    }

    protected function passCaptcha()
    {
        sleep(2);
        $captchaImageElem = $this->waitForElement(\WebDriverBy::id('recaptcha_challenge_image'));

        if (!$captchaImageElem) {
            $this->logger->notice('Not found captcha image');

            $iframe = $this->waitForElement(\WebDriverBy::xpath("//iframe[@title='recaptcha challenge']"), 10, true);
            $cnt = 0;

            while ($iframe && $cnt < 3) {
                $key = $this->http->FindSingleNode('//div[@id=\'recaptcha\']/@data-sitekey');

                $http2 = clone $this->http;
                $captcha = $this->parseCaptchaNew($http2, $key);

                if ($captcha === false) {
                    return false;
                }

                //remove iframe
                $this->driver->executeScript("document.evaluate(\"//iframe[contains(@title,'recaptcha challenge')]/ancestor::div[2]\", document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue.remove();");
                //make submit enabled
                $className = $this->driver->executeScript("return document.querySelector(\"input#btnContinue\").className;");
                $className = trim(str_replace("disabled", "", $className));
                $this->driver->executeScript("document.querySelector(\"input#btnContinue\").className = \"{$className}\";");
                $this->driver->executeScript("document.querySelector(\"input#btnContinue\").disabled = false;");

                $this->driver->executeScript("document.getElementById(\"g-recaptcha-response\").innerHTML=\"" . $captcha . "\";");

                sleep(1);
                $iframe = $this->waitForElement(\WebDriverBy::xpath("//iframe[@title='recaptcha challenge']"), 10, true);

                if ($iframe) {
                    $cnt++;

                    continue;
                }

                return true;
            }

            if ($cnt === 0) {
                $this->logger->notice('Not found captcha iframe');
            }

            return true; //sometimes captcha is off
            //throw new \EngineError('Failed to find captcha image');
        }
        $recognizer = $this->getCaptchaRecognizer();

        //		$path = $this->takeScreenshotOfElement($captchaImageElem);
        //		if (!$path)
        //			throw new \EngineError('Failed to take screenshot of captcha');
        //		$url = $this->http->FindSingleNode("//img[@id='recaptcha_challenge_image']/@src");
        //		$captchaAnswer = $recognizer->recognizeFile($path);

        $captchaAnswer = $recognizer->recognizeUrl($captchaImageElem->getAttribute('src'));
        $captchaResponseField = $this->waitForElement(\WebDriverBy::id('recaptcha_response_field'));

        if (!$captchaResponseField) {
            throw new \EngineError('Failed to find captcha response field');
        }
        $captchaResponseField->sendKeys($captchaAnswer);

        return true;
        //		unlink($path);
    }

    protected function parseCaptchaNew($http2, $key = null)
    {
        $this->logger->notice(__METHOD__);
        /*
                if (!$key){
                    $src = $http2->http->FindSinleNode("(//iframe[contains(@src,'recaptcha/api2')]/@src)[1]");
                    $key = $this->http->FindPreg("#(?:anchor\?|\&)k=(.+?)(?:\&|\#)#", false, $src);
                }
        */
        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;

        $parameters = [
            "pageurl" => $http2->currentUrl(),
            "proxy"   => $http2->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }

    private function setEnglish()
    {
        $this->http->removeCookies();

        $cookies = [
            'AKAMAI_SAA_LOCALE_COOKIE'  => 'en_UK',
            'AKAMAI_HOME_PAGE_ACCESSED' => 'true',
            'AKAMAI_SAA_COUNTRY_COOKIE' => 'US',
            //'FARE_DEALS_LISTING_COOKIE' => 'false',
            'LOGIN_POPUP_COOKIE' => 'false',
            'SQCLOGIN_COOKIE'    => 'false',
            'LOGIN_COOKIE'       => 'false',
        ];

        foreach ($cookies as $name => $value) {
            $this->driver->manage()->addCookie([
                'name'   => "$name",
                'value'  => "$value",
                'domain' => 'www.singaporeair.com',
            ]);
        }
        $this->http->setCookie("insLanguage", "en_US", ".singaporeair.com");

        $this->http->GetURL('https://www.singaporeair.com/en_UK/ppsclub-krisflyer/registration-form/');
        sleep(15);
    }

    private function log($key, $value)
    {
        $this->http->log(sprintf('[INFO] %s:', $key));
        $this->http->log(print_r($value, true));
    }
}
