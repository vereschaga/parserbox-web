<?php

// case #10322

namespace AwardWallet\Engine\ichotelsgroup\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use ProxyList;

    //	private function checkAdditionalInfo($fields) {
    //		// TODO: Add debugging output
    //		if (!isset(self::$additionalInfo[$fields['Country']]))
    //			return true;
//
    //		$additionalInfoForUserCountry = self::$additionalInfo[$fields['Country']];
    //		foreach ($additionalInfoForUserCountry['AdditionalRequiredFields'] as $arf)
    //			if (!isset($fields[$arf])) {
    //				$msg = $arf.' is required for '.self::$countries[$fields['Country']];
    //				if (isset($additionalInfoForUserCountry['Options'][$arf]))
    //					$msg .= '. Variants: '.implode(', ', array_keys($additionalInfoForUserCountry['Options'][$arf]));
    //				throw new \UserInputError($msg);
    //			}
//
    //		return true;
    //	}

    /*old Fields
        static $inputFieldsMap = array (
            'Title' => 'title',
            'FirstName' => 'firstName',
            'LastName' => 'lastName',
            'Email' =>
                array (
                    0 => 'emailAddress',
                    1 => 'confirmEmailAddress',
                ),
            'Password' =>
                array (
                    0 => 'pin',
                    1 => 'verifyPin',
                ),
            'AddressType' => 'addressType',
            'Country' => ['country', 'shadowCacheCountry'],

            'City' => 'city',
            'StateOrProvince' => ['state', 'shadowCacheState'],
            'PostalCode' => 'postalCode',
        );
    */
    public static $inputFieldsMap = [
        'Title'     => 'title',
        'FirstName' => 'firstName',
        'LastName'  => 'lastName',
        'Email'     =>
            [
                0 => 'emailAddress',
                1 => 'confirmEmailAddress',
            ],
        'Password' =>
            [
                0 => 'pin',
                1 => 'verifyPin',
            ],
        'AddressType' => 'addressType',

        'Country'         => 'mailingAddress.country.gsaCode',
        'City'            => 'mailingAddress.city',
        'StateOrProvince' => 'mailingAddress.state.code',
        'PostalCode'      => 'mailingAddress.postalCode',
    ];

    public static $addressTypes = [
        'H' => 'Residence',
        'B' => 'Business',
    ];

    public static $titles = [
        'Miss' => 'Miss',
        'Mr.'  => 'Mr.',
        'Mrs.' => 'Mrs.',
        'Ms.'  => 'Ms.',
        'Dr.'  => 'Dr.',
    ];

    public static $countries = [
        'US' => 'United States',
        'GB' => 'United Kingdom',
        'AF' => 'Afghanistan',
        'AX' => 'Aland Islands',
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
        'BA' => 'Bosnia And Herzegovina',
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
        'CN' => 'China, People\'s Republic Of',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (Keeling) Islands',
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
        'GW' => 'Guinea Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard Island And Mcdonald Islands',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran, Islamic Republic Of',
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
        'KP' => 'Korea, Democratic People\'s Republic Of',
        'KR' => 'Korea, Republic Of',
        'KW' => 'Kuwait',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macau',
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
        'FM' => 'Micronesia, Federated States Of',
        'MD' => 'Moldova, Republic Of',
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
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'BL' => 'Saint Barthelemy',
        'SH' => 'Saint Helena',
        'KN' => 'Saint Kitts And Nevis',
        'LC' => 'Saint Lucia',
        'PM' => 'Saint Pierre And Miquelon',
        'VC' => 'Saint Vincent And The Grenadines',
        'SM' => 'San Marino',
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
        'GS' => 'South Georgia And The South Sandwich Islands',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard And Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania, United Republic Of',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
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
        'UM' => 'United States Minor Outlying Islands',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City State',
        'VN' => 'Vietnam',
        'VG' => 'Virgin Islands, British',
        'VI' => 'Virgin Islands, U.S.',
        'WF' => 'Wallis And Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'BO' => 'Bolivia, Plurinational State Of',
        'BQ' => 'Bonaire Saint Eustatius And Saba',
        'CI' => 'Côte D\'ivoire, Republic Of',
        'KI' => 'Kiribati, Republic Of',
        'KG' => 'Kyrgystan',
        'LA' => 'Laos, People\'s Democratic Republic Of',
        'MK' => 'Macedonia, Republic Of',
        'PS' => 'Palestinian National Authority',
        'MF' => 'Saint Martin (French)',
        'WS' => 'Samoa, Independent State Of',
        'ST' => 'Sao Tome Principe',
        'SX' => 'Sint Maarten (Dutch)',
        'TL' => 'Timor-Leste, Democratic Republic Of',
        'VE' => 'Venezuela, Bolivarian Republic Of',
    ];

    public static $countriesMap = [
        'US' => '0001',
        'GB' => '0925',
        'AF' => '0110',
        'AX' => '0117',
        'AL' => '0120',
        'DZ' => '0125',
        'AS' => '0060',
        'AD' => '0140',
        'AO' => '0141',
        'AI' => '0142',
        'AQ' => '0143',
        'AG' => '0149',
        'AR' => '0150',
        'AM' => '0135',
        'AW' => '0100',
        'AU' => '0160',
        'AT' => '0165',
        'AZ' => '0115',
        'BS' => '0180',
        'BH' => '0181',
        'BD' => '0182',
        'BB' => '0184',
        'BY' => '0211',
        'BE' => '0190',
        'BZ' => '0227',
        'BJ' => '0197',
        'BM' => '0195',
        'BT' => '0200',
        'BA' => '0185',
        'BW' => '0210',
        'BV' => '0208',
        'BR' => '0220',
        'IO' => '0230',
        'BN' => '0232',
        'BG' => '0245',
        'BF' => '0927',
        'BI' => '0252',
        'KH' => '0255',
        'CM' => '0257',
        'CA' => '0260',
        'CV' => '0264',
        'KY' => '0268',
        'CF' => '0269',
        'TD' => '0273',
        'CL' => '0275',
        'CN' => '0280',
        'CX' => '0283',
        'CC' => '0502',
        'CO' => '0285',
        'KM' => '0286',
        'CG' => '0290',
        'CK' => '0293',
        'CR' => '0295',
        'HR' => '0296',
        'CU' => '0300',
        'CW' => '0303',
        'CY' => '0305',
        'CZ' => '0310',
        'DK' => '0315',
        'DJ' => '0317',
        'DM' => '0318',
        'DO' => '0320',
        'EC' => '0325',
        'EG' => '0922',
        'SV' => '0330',
        'GQ' => '0332',
        'ER' => '0327',
        'EE' => '0331',
        'ET' => '0335',
        'FK' => '0337',
        'FO' => '0336',
        'FJ' => '0338',
        'FI' => '0340',
        'FR' => '0350',
        'GF' => '0355',
        'PF' => '0367',
        'TF' => '0370',
        'GA' => '0388',
        'GM' => '0389',
        'GE' => '0390',
        'DE' => '0394',
        'GH' => '0396',
        'GI' => '0397',
        'GR' => '0400',
        'GL' => '0405',
        'GD' => '0406',
        'GP' => '0407',
        'GU' => '0066',
        'GT' => '0415',
        'GG' => '0410',
        'GN' => '0417',
        'GW' => '0737',
        'GY' => '0418',
        'HT' => '0420',
        'HM' => '0424',
        'HN' => '0430',
        'HK' => '0435',
        'HU' => '0445',
        'IS' => '0450',
        'IN' => '0455',
        'ID' => '0458',
        'IR' => '0460',
        'IQ' => '0465',
        'IE' => '0470',
        'IM' => '0472',
        'IL' => '0475',
        'IT' => '0480',
        'JM' => '0487',
        'JP' => '0490',
        'JE' => '0497',
        'JO' => '0500',
        'KZ' => '0525',
        'KE' => '0505',
        'KP' => '0514',
        'KR' => '0515',
        'KW' => '0520',
        'LV' => '0531',
        'LB' => '0540',
        'LS' => '0543',
        'LR' => '0545',
        'LY' => '0550',
        'LI' => '0553',
        'LT' => '0532',
        'LU' => '0570',
        'MO' => '0573',
        'MG' => '0575',
        'MW' => '0577',
        'MY' => '0580',
        'MV' => '0583',
        'ML' => '0585',
        'MT' => '0590',
        'MH' => '0073',
        'MQ' => '0591',
        'MR' => '0592',
        'MU' => '0593',
        'YT' => '0594',
        'MX' => '0595',
        'FM' => '0063',
        'MD' => '0576',
        'MC' => '0607',
        'MN' => '0608',
        'ME' => '0612',
        'MS' => '0609',
        'MA' => '0610',
        'MZ' => '0615',
        'MM' => '0250',
        'NA' => '0821',
        'NR' => '0621',
        'NP' => '0625',
        'NL' => '0630',
        'NC' => '0645',
        'NZ' => '0660',
        'NI' => '0665',
        'NE' => '0667',
        'NG' => '0670',
        'NU' => '0666',
        'NF' => '0683',
        'MP' => '0069',
        'NO' => '0685',
        'OM' => '0616',
        'PK' => '0700',
        'PW' => '0075',
        'PA' => '0710',
        'PG' => '0712',
        'PY' => '0715',
        'PE' => '0720',
        'PH' => '0725',
        'PN' => '0727',
        'PL' => '0730',
        'PT' => '0735',
        'PR' => '0072',
        'QA' => '0747',
        'RE' => '0750',
        'RO' => '0755',
        'RU' => '0825',
        'RW' => '0758',
        'BL' => '0762',
        'SH' => '0765',
        'KN' => '0763',
        'LC' => '0770',
        'PM' => '0773',
        'VC' => '0775',
        'SM' => '0782',
        'SA' => '0785',
        'SN' => '0787',
        'RS' => '0810',
        'SC' => '0788',
        'SL' => '0790',
        'SG' => '0795',
        'SK' => '0796',
        'SI' => '0797',
        'SB' => '0229',
        'SO' => '0800',
        'ZA' => '0801',
        'GS' => '0953',
        'ES' => '0830',
        'LK' => '0272',
        'SD' => '0835',
        'SR' => '0840',
        'SJ' => '0488',
        'SZ' => '0847',
        'SE' => '0850',
        'CH' => '0855',
        'SY' => '0858',
        'TW' => '0281',
        'TJ' => '0784',
        'TZ' => '0865',
        'TH' => '0875',
        'TG' => '0883',
        'TK' => '0885',
        'TO' => '0886',
        'TT' => '0887',
        'TN' => '0890',
        'TR' => '0905',
        'TM' => '0909',
        'TC' => '0906',
        'TV' => '0908',
        'UG' => '0910',
        'UA' => '0804',
        'AE' => '0888',
        'UM' => '0074',
        'UY' => '0930',
        'UZ' => '0931',
        'VU' => '0651',
        'VA' => '0934',
        'VN' => '0945',
        'VG' => '0231',
        'VI' => '0078',
        'WF' => '0950',
        'EH' => '0960',
        'YE' => '0965',
        'ZM' => '0990',
        'ZW' => '0818',
        'BO' => '0205',
        'BQ' => '0206',
        'CI' => '0485',
        'KI' => '0398',
        'KG' => '0510',
        'LA' => '0530',
        'MK' => '0574',
        'PS' => '0705',
        'MF' => '0772',
        'WS' => '0963',
        'ST' => '0783',
        'SX' => '0771',
        'TL' => '0738',
        'VE' => '0940',
    ];

    public static $states = [
        'GB' => [
            'Avon'               => 'Avon',
            'Bedfordshire'       => 'Bedfordshire',
            'Berkshire'          => 'Berkshire',
            'Buckinghamshire'    => 'Buckinghamshire',
            'Cambridgeshire'     => 'Cambridgeshire',
            'Cheshire'           => 'Cheshire',
            'Cleveland'          => 'Cleveland',
            'Cornwall'           => 'Cornwall',
            'Cumberland'         => 'Cumberland',
            'Cumbria'            => 'Cumbria',
            'Derbyshire'         => 'Derbyshire',
            'Devon'              => 'Devon',
            'Dorset'             => 'Dorset',
            'County Durham'      => 'County Durham',
            'Essex'              => 'Essex',
            'Gloucestershire'    => 'Gloucestershire',
            'Greater London'     => 'Greater London',
            'Greater Manchester' => 'Greater Manchester',
            'Hampshire'          => 'Hampshire',
            'Herefordshire'      => 'Herefordshire',
            'Hertfordshire'      => 'Hertfordshire',
            'Humberside'         => 'Humberside',
            'Huntingdonshire'    => 'Huntingdonshire',
            'Isle of Wight'      => 'Isle of Wight',
            'Kent'               => 'Kent',
            'Lancashire'         => 'Lancashire',
            'Leicestershire'     => 'Leicestershire',
            'Lincolnshire'       => 'Lincolnshire',
            'London'             => 'London',
            'Merseyside'         => 'Merseyside',
            'Middlesex'          => 'Middlesex',
            'Norfolk'            => 'Norfolk',
            'Northamptonshire'   => 'Northamptonshire',
            'Northumberland'     => 'Northumberland',
            'Nottinghamshire'    => 'Nottinghamshire',
            'Oxfordshire'        => 'Oxfordshire',
            'Rutland'            => 'Rutland',
            'Shropshire'         => 'Shropshire',
            'Somerset'           => 'Somerset',
            'Staffordshire'      => 'Staffordshire',
            'Suffolk'            => 'Suffolk',
            'Surrey'             => 'Surrey',
            'Sussex'             => 'Sussex',
            'Tyne and Wear'      => 'Tyne and Wear',
            'Warwickshire'       => 'Warwickshire',
            'West Midlands'      => 'West Midlands',
            'Westmorland'        => 'Westmorland',
            'Wiltshire'          => 'Wiltshire',
            'Worcestershire'     => 'Worcestershire',
            'Yorkshire'          => 'Yorkshire',
            'County Antrim'      => 'County Antrim',
            'County Armagh'      => 'County Armagh',
            'County Londonderry' => 'County Londonderry',
            'County Down'        => 'County Down',
            'County Fermanagh'   => 'County Fermanagh',
            'County Tyrone'      => 'County Tyrone',
            'Aberdeenshire'      => 'Aberdeenshire',
            'Angus'              => 'Angus',
            'Argyll'             => 'Argyll',
            'Ayrshire'           => 'Ayrshire',
            'Banffshire'         => 'Banffshire',
            'Berwickshire'       => 'Berwickshire',
            'Buteshire'          => 'Buteshire',
            'Caithness'          => 'Caithness',
            'Clackmannanshire'   => 'Clackmannanshire',
            'Cromartyshire'      => 'Cromartyshire',
            'Dumfriesshire'      => 'Dumfriesshire',
            'Dunbartonshire'     => 'Dunbartonshire',
            'East Lothian'       => 'East Lothian',
            'Fife'               => 'Fife',
            'Inverness-shire'    => 'Inverness-shire',
            'Kinross-shire'      => 'Kinross-shire',
            'Kirkcudbrightshire' => 'Kirkcudbrightshire',
            'Lanarkshire'        => 'Lanarkshire',
            'Midlothian'         => 'Midlothian',
            'Morayshire'         => 'Morayshire',
            'Nairnshire'         => 'Nairnshire',
            'Orkney'             => 'Orkney',
            'Peeblesshire'       => 'Peeblesshire',
            'Perthshire'         => 'Perthshire',
            'Renfrewshire'       => 'Renfrewshire',
            'Ross-shire'         => 'Ross-shire',
            'Roxburghshire'      => 'Roxburghshire',
            'Selkirkshire'       => 'Selkirkshire',
            'Shetland'           => 'Shetland',
            'Stirlingshire'      => 'Stirlingshire',
            'Sutherland'         => 'Sutherland',
            'West Lothian'       => 'West Lothian',
            'Wigtownshire'       => 'Wigtownshire',
            'Anglesey'           => 'Anglesey',
            'Brecknockshire'     => 'Brecknockshire',
            'Caernarfonshire'    => 'Caernarfonshire',
            'Cardiganshire'      => 'Cardiganshire',
            'Carmarthenshire'    => 'Carmarthenshire',
            'Clwyd'              => 'Clwyd',
            'Denbighshire'       => 'Denbighshire',
            'Dyfed'              => 'Dyfed',
            'Flintshire'         => 'Flintshire',
            'Glamorgan'          => 'Glamorgan',
            'Gwent'              => 'Gwent',
            'Gwynedd'            => 'Gwynedd',
            'Merionethshire'     => 'Merionethshire',
            'Monmouthshire'      => 'Monmouthshire',
            'Montgomeryshire'    => 'Montgomeryshire',
            'Pembrokeshire'      => 'Pembrokeshire',
            'Powys'              => 'Powys',
            'Radnorshire'        => 'Radnorshire',
        ],
        'CN' => [
            'Beijing Municipality'                    => 'Beijing Municipality',
            'Tianjin Municipality'                    => 'Tianjin Municipality',
            'Hebei Province'                          => 'Hebei Province',
            'Shanxi Province'                         => 'Shanxi Province',
            'Inner Mongolia Autonomous Region'        => 'Inner Mongolia Autonomous Region',
            'Liaoning Province'                       => 'Liaoning Province',
            'Jilin Province'                          => 'Jilin Province',
            'Heilongjiang Province'                   => 'Heilongjiang Province',
            'Shanghai Municipality'                   => 'Shanghai Municipality',
            'Jiangsu Province'                        => 'Jiangsu Province',
            'Zhejiang Province'                       => 'Zhejiang Province',
            'Anhui Province'                          => 'Anhui Province',
            'Fujian Province'                         => 'Fujian Province',
            'Jiangxi Province'                        => 'Jiangxi Province',
            'Shandong Province'                       => 'Shandong Province',
            'Henan Province'                          => 'Henan Province',
            'Hubei Province'                          => 'Hubei Province',
            'Hunan Province'                          => 'Hunan Province',
            'Guangdong Province'                      => 'Guangdong Province',
            'Guangxi Zhuang Autonomous Region'        => 'Guangxi Zhuang Autonomous Region',
            'Hainan Province'                         => 'Hainan Province',
            'Chongqing Municipality'                  => 'Chongqing Municipality',
            'Sichuan Province'                        => 'Sichuan Province',
            'Guizhou Province'                        => 'Guizhou Province',
            'Yunnan Province'                         => 'Yunnan Province',
            'Tibet Autonomous Region'                 => 'Tibet Autonomous Region',
            'Shaanxi Province'                        => 'Shaanxi Province',
            'Gansu Province'                          => 'Gansu Province',
            'Qinghai Province'                        => 'Qinghai Province',
            'Ningxia Hui Autonomous Region'           => 'Ningxia Hui Autonomous Region',
            'Xinjiang Uyghur Autonomous Region'       => 'Xinjiang Uyghur Autonomous Region',
            'Hong Kong Special Administrative Region' => 'Hong Kong Special Administrative Region',
            'Macau Special Administrative Region'     => 'Macau Special Administrative Region',
            'Taiwan Province'                         => 'Taiwan Province',
        ],
        'JP' => [
            'Aichi'     => 'Aichi',
            'Akita'     => 'Akita',
            'Aomori'    => 'Aomori',
            'Chiba'     => 'Chiba',
            'Ehime'     => 'Ehime',
            'Fukui'     => 'Fukui',
            'Fukuoka'   => 'Fukuoka',
            'Fukushima' => 'Fukushima',
            'Gifu'      => 'Gifu',
            'Gunma'     => 'Gunma',
            'Hiroshima' => 'Hiroshima',
            'Hokkaidō'  => 'Hokkaidō',
            'Hyōgo'     => 'Hyōgo',
            'Ibaraki'   => 'Ibaraki',
            'Ishikawa'  => 'Ishikawa',
            'Iwate'     => 'Iwate',
            'Kagawa'    => 'Kagawa',
            'Kagoshima' => 'Kagoshima',
            'Kanagawa'  => 'Kanagawa',
            'Kōchi'     => 'Kōchi',
            'Kumamoto'  => 'Kumamoto',
            'Kyōto'     => 'Kyōto',
            'Mie'       => 'Mie',
            'Miyagi'    => 'Miyagi',
            'Miyazaki'  => 'Miyazaki',
            'Nagano'    => 'Nagano',
            'Nagasaki'  => 'Nagasaki',
            'Nara'      => 'Nara',
            'Niigata'   => 'Niigata',
            'Ōita'      => 'Ōita',
            'Okayama'   => 'Okayama',
            'Okinawa'   => 'Okinawa',
            'Ōsaka'     => 'Ōsaka',
            'Saga'      => 'Saga',
            'Saitama'   => 'Saitama',
            'Shiga'     => 'Shiga',
            'Shimane'   => 'Shimane',
            'Shizuoka'  => 'Shizuoka',
            'Tochigi'   => 'Tochigi',
            'Tokushima' => 'Tokushima',
            'Tōkyō'     => 'Tōkyō',
            'Tottori'   => 'Tottori',
            'Toyama'    => 'Toyama',
            'Wakayama'  => 'Wakayama',
            'Yamagata'  => 'Yamagata',
            'Yamaguchi' => 'Yamaguchi',
            'Yamanashi' => 'Yamanashi',
        ],
        'US' => [
            'AL' => 'Alabama',
            'AK' => 'Alaska',
            'AZ' => 'Arizona',
            'AR' => 'Arkansas',
            'AA' => 'Armed Forces - Amer',
            'AE' => 'Armed Forces - Ejea',
            'AP' => 'Armed Forces Pacific',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'DC' => 'District Of Columbia',
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
            'ME' => 'Maine',
            'MP' => 'Mariana Islands',
            'MH' => 'Marshal Islands',
            'MD' => 'Maryland',
            'MA' => 'Massachusetts',
            'MI' => 'Michigan',
            'MN' => 'Minnesota',
            'MS' => 'Mississippi',
            'MO' => 'Missouri',
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
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VT' => 'Vermont',
            'VI' => 'Virgin Islands',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming',
        ],
        'CA' => [
            'AB' => 'Alberta',
            'BC' => 'British Columbia',
            'MB' => 'Manitoba',
            'NB' => 'New Brunswick',
            'NL' => 'Newfoundland/labrador',
            'NT' => 'Northwest Territories',
            'NS' => 'Nova Scotia',
            'NU' => 'Nunavut',
            'ON' => 'Ontario',
            'PE' => 'Prince Edward Island',
            'QC' => 'Quebec',
            'SK' => 'Saskatchewan',
            'YT' => 'Yukon',
        ],
    ];

    public static $additionalInfo = [
        'US' => [
            // United States
            'AdditionalRequiredFields' => [
                'StateOrProvince',
                'PostalCode',
            ],
            'Options' => [
                'StateOrProvince' => [
                    'AL' => 'Alabama',
                    'AK' => 'Alaska',
                    'AZ' => 'Arizona',
                    'AR' => 'Arkansas',
                    'AA' => 'Armed Forces - Amer',
                    'AE' => 'Armed Forces - Ejea',
                    'AP' => 'Armed Forces Pacific',
                    'CA' => 'California',
                    'CO' => 'Colorado',
                    'CT' => 'Connecticut',
                    'DE' => 'Delaware',
                    'DC' => 'District Of Columbia',
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
                    'ME' => 'Maine',
                    'MP' => 'Mariana Islands',
                    'MH' => 'Marshal Islands',
                    'MD' => 'Maryland',
                    'MA' => 'Massachusetts',
                    'MI' => 'Michigan',
                    'MN' => 'Minnesota',
                    'MS' => 'Mississippi',
                    'MO' => 'Missouri',
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
                ],
            ],
        ],
        'GB' => [
            // United Kingdom
            'AdditionalRequiredFields' => [
                'StateOrProvince',
                'PostalCode',
            ],
        ],
        'CA' => [
            // Canada
            'AdditionalRequiredFields' => [
                'StateOrProvince',
                'PostalCode',
            ],
            'Options' => [
                'StateOrProvince' => [
                    'AB' => 'Alberta',
                    'BC' => 'British Columbia',
                    'MB' => 'Manitoba',
                    'NB' => 'New Brunswick',
                    'NL' => 'Newfoundland/labrador',
                    'NT' => 'Northwest Territories',
                    'NS' => 'Nova Scotia',
                    'NU' => 'Nunavut',
                    'ON' => 'Ontario',
                    'PE' => 'Prince Edward Island',
                    'QC' => 'Quebec',
                    'SK' => 'Saskatchewan',
                    'YT' => 'Yukon',
                ],
            ],
        ],
        'CN' => [
            // China
            'AdditionalRequiredFields' => [
                'StateOrProvince',
                'PostalCode',
            ],
        ],
        'DE' => [
            // Germany
            'AdditionalRequiredFields' => [
                'PostalCode',
            ],
            'UnusedFields' => [
                'StateOrProvince',
            ],
        ],
        'JP' => [
            // Japan
            'AdditionalRequiredFields' => [
                'StateOrProvince',
                'PostalCode',
            ],
        ],
    ];

    public function registerAccount(array $fields)
    {
        $this->http->log('[INFO] initial fields:');
        $this->http->log(json_encode($fields, JSON_PRETTY_PRINT));

        if (isset(self::$states[$fields['Country']])) {
            $states = self::$states[$fields['Country']];

            if (!isset($states[$fields['StateOrProvince']])) {
                throw new \UserInputError('Unavailable StateOrProvince option for chosen country');
            }
        }
        $addrTypes = ['H' => 'RESIDENCE', 'B' => 'BUSINESS'];
        $fields['AddressType'] = $addrTypes[$fields['AddressType']];

        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;

        //		if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG)
        //			$this->http->setDefaultHeader("User-Agent", \HttpBrowser::PROXY_USER_AGENT);
        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->http->SetProxy('localhost:8000');
        } else {
            $this->http->SetProxy($this->proxyDOP());
        }
        // $this->http->GetURL('https://www.ihg.com/rewardsclub/us/en/account/home');
        $this->http->GetURL('https://www.ihg.com/rewardsclub/us/en/join/join');

        $status = $this->http->ParseForm('memberRegistration');

        if (!$status) {
            $this->http->Log('Failed to parse registration form');

            return false;
        }

        //		if (isset(self::$additionalInfo[$fields['Country']]))
        //			$this->checkAdditionalInfo($fields);

        $countryCode = self::$countriesMap[$fields['Country']];
        $this->logger->debug('Mapped standard country code ' . $fields['Country'] . ' to provider code ' . $countryCode);
        $fields['Country'] = $countryCode;
        // $fields['PostalCode'] = '';
        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if ($awKey == 'AddressLine1' or $awKey == 'Company') {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                if (isset($fields[$awKey])) {
                    $this->http->SetInputValue($provKey, $fields[$awKey]);
                }
            }
        }

        if (isset($fields['AddressType'])) {
            if ($fields['AddressType'] == 'RESIDENCE') {
                if (isset($fields['AddressLine1'])) {
                    $this->http->SetInputValue('mailingAddress.address1', $fields['AddressLine1']);
                }
                //					$this->http->SetInputValue('address1', $fields['AddressLine1']);
                if (isset($fields['AddressLine2'])) {
                    $this->http->SetInputValue('mailingAddress.address2', $fields['AddressLine2']);
                }
                //					$this->http->SetInputValue('address2', $fields['AddressLine2']);
            } elseif ($fields['AddressType'] == 'BUSINESS') {
                if (isset($fields['Company'])) {
                    $this->http->SetInputValue('mailingAddress.address1', $fields['Company']);
                }
                //					$this->http->SetInputValue('address1', $fields['Company']);
                if (isset($fields['AddressLine1'])) {
                    $this->http->SetInputValue('mailingAddress.address2', $fields['AddressLine1']);
                }
                //					$this->http->SetInputValue('address2', $fields['AddressLine1']);
                if (isset($fields['AddressLine2'])) {
                    $this->http->SetInputValue('mailingAddress.address3', $fields['AddressLine2']);
                }
                //					$this->http->SetInputValue('address3', $fields['AddressLine2']);
            }
        }

        $this->http->SetInputValue('acceptTermsAndConditions', 'true');
        $this->http->SetInputValue('_acceptTermsAndConditions', 'on');
        $this->http->SetInputValue('acceptPaymentTermsAndConditions', 'true');
        $this->http->SetInputValue('enrollmentjoinnow', 'JOIN NOW');

        // very important!
        $this->http->setCookie('country_language', '"ru$:en"', 'www.ihg.com');

        // unset($this->http->Form['']);
        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to parse registration form');

            return false;
        }

        $xpath = '//*[contains(@id, "Error") and string-length(normalize-space(./text())) > 1]';
        $inputErrors = $this->http->FindNodes($xpath);

        if ($inputErrors) {
            if (count($inputErrors) > 1) {
                $i = 1;

                foreach ($inputErrors as &$ie) {
                    $ie = "$i) $ie";
                    $i++;
                }
                $err = 'Input errors: ' . implode('; ', $inputErrors);
            } else {
                $err = $inputErrors[0];
            }

            throw new \UserInputError($err); // Is it always user input error?
        }

        $xpath = '//text()[contains(normalize-space(.),\'Your PIN is not strong enough. You must change your PIN.\')]';
        $inputErrors = $this->http->FindSingleNode($xpath);

        if ($inputErrors) {
            throw new \UserInputError($inputErrors);
        }

        //		if ($this->http->FindSingleNode('//a[normalize-space(.) = "Sign Out"]')) {
        if ($this->http->FindSingleNode('//a[contains(.,"Access Your Card")]')) {
            $msg = 'Registration succeeded';
            //			$cardNumber = $this->http->FindSingleNode('//*[contains(., "Rewards Club Number")]/following-sibling::td[1]');
            $cardNumber = $this->http->FindSingleNode('//text()[normalize-space(.)="Member #"]/ancestor::div[1]', null, true, "#Member\s+\#\s*(.+)#");

            if ($cardNumber) {
                $msg .= ", IHG Rewards Club Number $cardNumber";
            } else {
                $this->http->Log('Seems that registration succeeded, but couldn\'t get membership number');

                return false;
            }
            $this->http->Log($msg);
            $this->ErrorMessage = $msg;

            return true;
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Title' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Title',
                    'Required' => false,
                    'Options'  => self::$titles,
                ],
            'FirstName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'First Name',
                    'Required' => true,
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
                    'Caption'  => 'Email Address',
                    'Required' => true,
                ],
            'Password' =>
                [
                    'Type'     => 'string',
                    'Caption'  => '4-digit PIN',
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
                    'Caption'  => 'Address Line 1',
                    'Required' => true,
                ],
            'AddressLine2' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Line 2',
                    'Required' => false,
                ],
            'Company' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Company name (required only for "Business" address type)',
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
                    'Caption'  => 'City/Town',
                    'Required' => true,
                ],
            'StateOrProvince' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'County/Province/State (required for USA, UK, Canada, China, Japan, not used for Germany)',
                    'Required' => false,
                ],
            'PostalCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Postal Code (required for US, UK, Canada, China, Germany, Japan)',
                    'Required' => false,
                ],
        ];
    }
}
