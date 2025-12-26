<?php

namespace AwardWallet\Engine\thaiair\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;
    public $timeout = 20;

    public static $inputFieldsMap = [
        'Title'           => 'Salutation',
        'FirstName'       => 'FName',
        'LastName'        => 'LName',
        'BirthMonth'      => 'mm',
        'BirthDay'        => 'dd',
        'BirthYear'       => 'yy',
        'Gender'          => 'Gender',
        'Nationality'     => 'Nationality',
        'AddressType'     => 'Specify',
        'AddressLine1'    => 'Addr1',
        'City'            => 'CityList',
        'OtherCity'       => 'OtherCity',
        'StateOrProvince' => 'StateList',
        'Country'         => 'CountryList',
        'PostalCode'      => 'PostalCode',
        'Email'           => ['emailAddr1', 'emailAddr2'],
        //		'PhoneType' => 'PhoneType1',
        'PhoneCountryCodeNumeric' => 'CountryCode1',
        'PhoneAreaCode'           => 'AreaCode1',
        'PhoneLocalNumber'        => 'PhoneNbr1',
        'Password'                => ['pin1', 'pin2'],
        'PreferredLanguage'       => 'LangPref',
        'JobTitle'                => 'Title',
        'Company'                 => 'Company1',
    ];

    public static $titles = [
        'Mr.'   => 'Mr.',
        'Mrs.'  => 'Mrs.',
        'Ms.'   => 'Ms.',
        'Miss'  => 'Miss',
        'Mstr.' => 'Mstr.',
        'Dr.'   => 'Dr.',
        'Other' => 'Other',
    ];

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $nationalities = [
        'TH' => 'Thai',
        'AD' => 'Andorran',
        'AE' => 'Emirati',
        'AF' => 'Afghan',
        'AG' => 'Antigua And Barbuda',
        'AI' => 'Anguilla',
        'AL' => 'Albanian',
        'AM' => 'Armenian',
        'AN' => 'Antilles Nether',
        'AO' => 'Angolan',
        'AQ' => 'Antarctica',
        'AR' => 'Argentinian',
        'AS' => 'American Samoa',
        'AT' => 'Austrian',
        'AU' => 'Australian',
        'AW' => 'Aruba',
        'AZ' => 'Azerbaijani',
        'BA' => 'Bosnian',
        'BB' => 'Barbadian',
        'BD' => 'Bangladeshi',
        'BE' => 'Belgian',
        'BF' => 'Burkinese',
        'BG' => 'Bulgarian',
        'BH' => 'Bahraini',
        'BI' => 'Burundian',
        'BJ' => 'Beninese',
        'BM' => 'Bermuda',
        'BN' => 'Bruneian',
        'BO' => 'Bolivian',
        'BR' => 'Brazilian',
        'BS' => 'Bahamian',
        'BT' => 'Bhutanese',
        'BV' => 'Bouvet Island',
        'BW' => 'Botswanan',
        'BY' => 'Belarusian',
        'BZ' => 'Belizean',
        'CA' => 'Canadian',
        'CC' => 'Cocos (Keeling) Island',
        'CD' => 'Congo Dem. Rep.',
        'CF' => 'Central Africa',
        'CG' => 'Congolese',
        'CH' => 'Swiss',
        'CI' => 'Cote D Ivorie',
        'CK' => 'Cook Island',
        'CL' => 'Chilean',
        'CM' => 'Cameroonian',
        'CN' => 'Chinese',
        'CO' => 'Colombian',
        'CR' => 'Costa Rican',
        'CS' => 'Serbia and Montenegro',
        'CT' => 'Canton and Enderbury Islands',
        'CU' => 'Cuban',
        'CV' => 'Cape Verdean',
        'CX' => 'Christmas Island',
        'CY' => 'Cypriot',
        'CZ' => 'Czech',
        'DE' => 'German',
        'DJ' => 'Djiboutian',
        'DK' => 'Danish',
        'DM' => 'Dominican',
        'DO' => 'Dominican',
        'DS' => 'South Georgia &amp; Sandwich Isl.',
        'DZ' => 'Algerian',
        'EC' => 'Ecuadorean',
        'EE' => 'Estonian',
        'EG' => 'Egyptian',
        'EH' => 'Western Sahara',
        'ER' => 'Eritrean',
        'ES' => 'Spanish',
        'ET' => 'Ethiopian',
        'FI' => 'Finnish',
        'FJ' => 'Fijian',
        'FK' => 'Falkland Island',
        'FM' => 'Micronesia',
        'FO' => 'Faroe Islands',
        'FQ' => 'French Southern and Antartic Territories',
        'FR' => 'French',
        'GA' => 'Gabonese',
        'GB' => 'British',
        'GD' => 'Grenadian',
        'GE' => 'Georgian',
        'GF' => 'French Guiana',
        'GH' => 'Ghanaian',
        'GI' => 'Gibraltar',
        'GL' => 'Greenland',
        'GM' => 'Gambian',
        'GN' => 'Guinean',
        'GP' => 'Guadeloupe',
        'GQ' => 'Eq Guinea',
        'GR' => 'Greek',
        'GT' => 'Guatemalan',
        'GU' => 'Guam',
        'GW' => 'Guinea Bissau',
        'GY' => 'Guyanese',
        'HK' => 'Hong Kong',
        'HM' => 'Heard and McDonald Terr.',
        'HN' => 'Honduran',
        'HR' => 'Croatian',
        'HT' => 'Haitian',
        'HU' => 'Hungarian',
        'ID' => 'Indonesian',
        'IE' => 'Irish',
        'IL' => 'Israel',
        'IN' => 'Indian',
        'IO' => 'British Indian Ocean Territory',
        'IQ' => 'Iraqi',
        'IR' => 'Iranian',
        'IS' => 'Icelandic',
        'IT' => 'Italian',
        'JM' => 'Jamaican',
        'JO' => 'Jordanian',
        'JP' => 'Japanese',
        'KE' => 'Kenyan',
        'KG' => 'Kyrgyzstan',
        'KH' => 'Cambodian',
        'KI' => 'Kiribati',
        'KM' => 'Comoros',
        'KN' => 'Saint Kitts and Nevis',
        'KP' => 'North Korean',
        'KR' => 'South Korean',
        'KW' => 'Kuwaiti',
        'KY' => 'Cayman Isl.',
        'KZ' => 'Kazakh',
        'LA' => 'Laotian',
        'LB' => 'Lebanese',
        'LC' => 'St.lucia',
        'LI' => 'Liechtenstein',
        'LK' => 'Sri Lankan',
        'LR' => 'Liberian',
        'LS' => 'Lesotho',
        'LT' => 'Lithuanian',
        'LU' => 'Luxembourg',
        'LV' => 'Latvian',
        'LY' => 'Libyan',
        'MA' => 'Moroccan',
        'MC' => 'Monacan',
        'MD' => 'Moldovan',
        'ME' => 'Montenegrin',
        'MG' => 'Madagascan',
        'MH' => 'Marshall Island',
        'MK' => 'Macedonian',
        'ML' => 'Malian',
        'MM' => 'Burmese',
        'MN' => 'Mongolian',
        'MO' => 'Macau',
        'MP' => 'Northern Mariana Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritanian',
        'MS' => 'Montserrat',
        'MT' => 'Maltese',
        'MU' => 'Mauritian',
        'MV' => 'Maldivian',
        'MW' => 'Malawian',
        'MX' => 'Mexican',
        'MY' => 'Malaysian',
        'MZ' => 'Mozambican',
        'NA' => 'Namibian',
        'NC' => 'New Caledonia',
        'NE' => 'Nigerien',
        'NF' => 'Norfolk Island',
        'NG' => 'Nigerian',
        'NI' => 'Nicaraguan',
        'NL' => 'Dutch',
        'NM' => 'Numan',
        'NO' => 'Norwegian',
        'NP' => 'Nepalese',
        'NR' => 'Nauru',
        'NU' => 'Niue',
        'NZ' => 'New Zealand',
        'OM' => 'Omani',
        'PA' => 'Panamanian',
        'PC' => 'Pacific Islands, Trust Territory of The',
        'PE' => 'Peruvian',
        'PF' => 'French Polynesia',
        'PG' => 'Papua New Guinean',
        'PH' => 'Philippine',
        'PK' => 'Pakistani',
        'PL' => 'Polish',
        'PM' => 'St Pierre And Miquelon',
        'PN' => 'Pitcairn',
        'PR' => 'Puerto Rico',
        'PS' => 'Palestine',
        'PT' => 'Portuguese',
        'PW' => 'Palau',
        'PY' => 'Paraguayan',
        'QA' => 'Qatari',
        'RE' => 'Reunion',
        'RO' => 'Romanian',
        'RS' => 'Serbian',
        'RU' => 'Russian',
        'RW' => 'Rwandan',
        'SA' => 'Saudi Arabian',
        'SB' => 'Solomon Islands',
        'SC' => 'Seychellois',
        'SD' => 'Sudanese',
        'SE' => 'Swedish',
        'SG' => 'Singaporean',
        'SH' => 'St. Helena',
        'SI' => 'Slovenian',
        'SJ' => 'Svalbard and Jan Mayen',
        'SK' => 'Slovak',
        'SL' => 'Sierra Leonian',
        'SM' => 'San Marino',
        'SN' => 'Senegalese',
        'SO' => 'Somali',
        'SQ' => 'Bonaire, Sint Eustatius and Saba',
        'SR' => 'Surinamese',
        'ST' => 'Sao Tome And Principe',
        'SV' => 'Salvadorean',
        'SY' => 'Syrian',
        'SZ' => 'Swazi',
        'TC' => 'Turks And Caicos Islands',
        'TD' => 'Chadian',
        'TF' => 'French Southern Terr.',
        'TG' => 'Togolese',
        'TJ' => 'Tajik',
        'TK' => 'Tokelau',
        'TM' => 'Turkmen',
        'TN' => 'Tunisian',
        'TO' => 'Tonga Island',
        'TP' => 'East Timor',
        'TR' => 'Turkish',
        'TT' => 'Trinidadian',
        'TV' => 'Tuvaluan',
        'TW' => 'Taiwanese',
        'TZ' => 'Tanzanian',
        'UA' => 'Ukrainian',
        'UG' => 'Ugandan',
        'UM' => 'Minor U.S. Outlying Islands',
        'US' => 'American',
        'UY' => 'Uruguayan',
        'UZ' => 'Uzbek',
        'VA' => 'Vatican',
        'VC' => 'St Vincent &amp; Grenadines',
        'VE' => 'Venezuelan',
        'VG' => 'British Virgin Islands',
        'VI' => 'Us Virgin Isl.',
        'VN' => 'Vietnamese',
        'VU' => 'Vanuatuan',
        'WF' => 'Wallis And Futuna Islands',
        'WK' => 'Wake Island',
        'WS' => 'Samoa',
        'XA' => 'Gaza',
        'XB' => 'Northern Ireland',
        'XH' => 'Held Territories',
        'XM' => 'Mayotte',
        'XU' => 'Khabarovsk Krai',
        'XX' => 'Other',
        'YD' => 'Yemeni',
        'YE' => 'Yemen',
        'YU' => 'Yugoslav',
        'ZA' => 'South African',
        'ZM' => 'Zambian',
        'ZR' => 'Za?rean',
        'ZW' => 'Zimbabwean',
    ];

    public static $addressTypes = [
        'H' => 'Home',
        'B' => 'Business',
    ];

    public static $cities = [
        'Amnajcharoen'         => 'Amnajcharoen',
        'Angthong'             => 'Angthong',
        'Ayuthaya'             => 'Ayuthaya',
        'Bangkok'              => 'Bangkok',
        'Bueng Kan'            => 'Bueng Kan',
        'Buriram'              => 'Buriram',
        'Chachoengsao'         => 'Chachoengsao',
        'Chainat'              => 'Chainat',
        'Chaiyaphum'           => 'Chaiyaphum',
        'Chanthaburi'          => 'Chanthaburi',
        'Chiangmai'            => 'Chiangmai',
        'Chiangrai'            => 'Chiangrai',
        'Chonburi'             => 'Chonburi',
        'Chumphon'             => 'Chumphon',
        'Kalasin'              => 'Kalasin',
        'Kamphaeng Phet'       => 'Kamphaeng Phet',
        'Kanchanaburi'         => 'Kanchanaburi',
        'Khon Kaen'            => 'Khon Kaen',
        'Krabi'                => 'Krabi',
        'Lampang'              => 'Lampang',
        'Lampoon'              => 'Lampoon',
        'Loei'                 => 'Loei',
        'Lopburi'              => 'Lopburi',
        'Mae Hong Sorn'        => 'Mae Hong Sorn',
        'Maha Sarakham'        => 'Maha Sarakham',
        'Mukdahan'             => 'Mukdahan',
        'Nakhon Nayok'         => 'Nakhon Nayok',
        'Nakhon Pathom'        => 'Nakhon Pathom',
        'Nakhon Phanom'        => 'Nakhon Phanom',
        'Nakorn Rajseema'      => 'Nakorn Rajseema',
        'Nakhon Sawan'         => 'Nakhon Sawan',
        'Nakhon Sri Thammarat' => 'Nakhon Sri Thammarat',
        'Nan'                  => 'Nan',
        'Narathiwat'           => 'Narathiwat',
        'Nongbualampoo'        => 'Nongbualampoo',
        'Nongkhai'             => 'Nongkhai',
        'Nonthaburi'           => 'Nonthaburi',
        'Pathum Thani'         => 'Pathum Thani',
        'Pattani'              => 'Pattani',
        'Petchaburi'           => 'Petchaburi',
        'Phangnga'             => 'Phangnga',
        'Phatthalung'          => 'Phatthalung',
        'Phayao'               => 'Phayao',
        'Phetchabun'           => 'Phetchabun',
        'Pichit'               => 'Pichit',
        'Phitsanulok'          => 'Phitsanulok',
        'Phrae'                => 'Phrae',
        'Phuket'               => 'Phuket',
        'Prachinburi'          => 'Prachinburi',
        'Prachuap Khiri Khan'  => 'Prachuap Khiri Khan',
        'Ranong'               => 'Ranong',
        'Ratchaburi'           => 'Ratchaburi',
        'Rayong'               => 'Rayong',
        'Roi Et'               => 'Roi Et',
        'Sakon Nakhon'         => 'Sakon Nakhon',
        'Samut Sakhon'         => 'Samut Sakhon',
        'Samut Prakarn'        => 'Samut Prakarn',
        'Samut Songkhram'      => 'Samut Songkhram',
        'Saraburi'             => 'Saraburi',
        'Satun'                => 'Satun',
        'Si Sa Ket'            => 'Si Sa Ket',
        'Singburi'             => 'Singburi',
        'Songkhla'             => 'Songkhla',
        'Srakaew'              => 'Srakaew',
        'Sukhothai'            => 'Sukhothai',
        'Suphanburi'           => 'Suphanburi',
        'Surat Thani'          => 'Surat Thani',
        'Surin'                => 'Surin',
        'Tak'                  => 'Tak',
        'Trang'                => 'Trang',
        'Trat'                 => 'Trat',
        'Ubon Ratchathani'     => 'Ubon Ratchathani',
        'Udon Thani'           => 'Udon Thani',
        'Uthai Thani'          => 'Uthai Thani',
        'Uttaradit'            => 'Uttaradit',
        'Yala'                 => 'Yala',
        'Yasothon'             => 'Yasothon',
        'Amsterdam'            => 'Amsterdam',
        'Athens'               => 'Athens',
        'Auckland'             => 'Auckland',
        'Bandar Seri Begawan'  => 'Bandar Seri Begawan',
        'Bangalore'            => 'Bangalore',
        'Beijing'              => 'Beijing',
        'Brisbane'             => 'Brisbane',
        'Calcutta'             => 'Calcutta',
        'Chennai'              => 'Chennai',
        'Chicago'              => 'Chicago',
        'Colombo'              => 'Colombo',
        'Copenhagen'           => 'Copenhagen',
        'Denpasar'             => 'Denpasar',
        'Dhaka'                => 'Dhaka',
        'Dubai'                => 'Dubai',
        'Frankfurt'            => 'Frankfurt',
        'Fukuoka'              => 'Fukuoka',
        'Gaya'                 => 'Gaya',
        'Guangzhou'            => 'Guangzhou',
        'Hanoi'                => 'Hanoi',
        'Ho Chi Minh City'     => 'Ho Chi Minh City',
        'Hong Kong'            => 'Hong Kong',
        'Hyderabad'            => 'Hyderabad',
        'Jakarta'              => 'Jakarta',
        'Johannesburg'         => 'Johannesburg',
        'Kaohsiung'            => 'Kaohsiung',
        'Kuala Lumpur'         => 'Kuala Lumpur',
        'Kunming'              => 'Kunming',
        'Lahore'               => 'Lahore',
        'London'               => 'London',
        'Los Angeles'          => 'Los Angeles',
        'Madrid'               => 'Madrid',
        'Manila'               => 'Manila',
        'Melbourne'            => 'Melbourne',
        'Milan'                => 'Milan',
        'Moscow'               => 'Moscow',
        'Mumbai'               => 'Mumbai',
        'Munich'               => 'Munich',
        'Muscat'               => 'Muscat',
        'Nagoya'               => 'Nagoya',
        'New Delhi'            => 'New Delhi',
        'New York'             => 'New York',
        'Osaka'                => 'Osaka',
        'Paris'                => 'Paris',
        'Penang'               => 'Penang',
        'Perth'                => 'Perth',
        'Phnom Penh'           => 'Phnom Penh',
        'Rome'                 => 'Rome',
        'Seattle'              => 'Seattle',
        'Seoul'                => 'Seoul',
        'Shanghai'             => 'Shanghai',
        'Singapore'            => 'Singapore',
        'Stockholm'            => 'Stockholm',
        'Surabaya'             => 'Surabaya',
        'Sydney'               => 'Sydney',
        'Taipei'               => 'Taipei',
        'Tokyo'                => 'Tokyo',
        'Varanasi'             => 'Varanasi',
        'Vientiane'            => 'Vientiane',
        'Washington, DC'       => 'Washington, DC',
        'Yangon'               => 'Yangon',
        'Zurich'               => 'Zurich',
        'Other'                => 'Other',
    ];

    public static $statesByCountry = [
        'AR' => [
            // Argentina
            'ARBA' => 'Buenos Aires',
            'ARCA' => 'Catamarca',
            'ARCB' => 'Chubut',
            'ARCD' => 'Cordoba',
            'ARCH' => 'Chaco',
            'ARCR' => 'Corrientes',
            'ARER' => 'Entre Rios',
            'ARFO' => 'Formosa',
            'ARLP' => 'La Pampa',
            'ARLR' => 'La Rioja',
            'ARMD' => 'Mendoza',
            'ARMI' => 'Misiones',
            'ARNE' => 'Neuguen',
            'ARPJ' => 'Provincia Jujuy',
            'ARRN' => 'Rio Negro',
            'ARSA' => 'Salta',
            'ARSC' => 'Santa Cruz',
            'ARSE' => 'Santiago des Estero',
            'ARSF' => 'Santa Fe',
            'ARSJ' => 'San Juan',
            'ARSL' => 'San Luis',
            'ARTF' => 'Tierra del Fuego',
            'ARTU' => 'Tucuman',
        ],
        'AU' => [
            // Australia
            'AUAC' => 'Capital Territory.',
            'AUNS' => 'New South Wales',
            'AUNT' => 'Northern Territory.',
            'AUQL' => 'Queensland',
            'AUSA' => 'South Australia',
            'AUTS' => 'Tasmania',
            'AUVI' => 'Victoria',
            'AUWA' => 'Western Australia.',
        ],
        'BR' => [
            // Brazil
            'BRAC' => 'Acre',
            'BRAL' => 'Alagoas',
            'BRAM' => 'Amazonas',
            'BRAP' => 'Amapa',
            'BRBA' => 'Bahia',
            'BRCE' => 'Ceara',
            'BRDF' => 'Federal District',
            'BRES' => 'Espirito Santo',
            'BRFN' => 'Fernando de Noronha',
            'BRGO' => 'Goias',
            'BRMA' => 'Maranhao',
            'BRMG' => 'Minas Gerais',
            'BRMS' => 'Mato Grosso do Sul',
            'BRMT' => 'Mato Grosso',
            'BRPA' => 'Para',
            'BRPB' => 'Paraiba',
            'BRPE' => 'Pernambuco',
            'BRPI' => 'Piaui',
            'BRPR' => 'Parana',
            'BRRJ' => 'Rio de Janeiro',
            'BRRN' => 'Rio Grande do Norte',
            'BRRS' => 'Rio Grande do Sul',
            'BRRO' => 'Rondonia',
            'BRRR' => 'Roraima',
            'BRSC' => 'Santa Catarina',
            'BRSE' => 'Sergipe',
            'BRSP' => 'Sao Paulo',
            'BRTO' => 'Tocantins',
        ],
        'CA' => [
            // Canada
            'CAAB' => 'Alberta',
            'CABC' => 'British Columbia',
            'CAMB' => 'Manitoba',
            'CANB' => 'New Brunswick',
            'CANF' => 'Newfoundland',
            'CANS' => 'Nova Scotia',
            'CANT' => 'NW Territories',
            'CAON' => 'Ontario',
            'CAPE' => 'Prince Edward Island.',
            'CAPQ' => 'Quebec',
            'CASK' => 'Saskatchewan',
            'CAYT' => 'Yukon Territory',
        ],
        'US' => [
            // USA
            'USAL' => 'Alabama',
            'USAK' => 'Alaska',
            'USAZ' => 'Arizona',
            'USAR' => 'Arkansas',
            'USCA' => 'California',
            'USCO' => 'Colorado',
            'USCT' => 'Connecticut',
            'USDE' => 'Delaware',
            'USDC' => 'District of Col',
            'USFL' => 'Florida',
            'USGA' => 'Georgia',
            'USHI' => 'Hawaii',
            'USID' => 'Idaho',
            'USIL' => 'Illinois',
            'USIN' => 'Indiana',
            'USIA' => 'Iowa',
            'USKS' => 'Kansas',
            'USKY' => 'Kentucky',
            'USLA' => 'Louisiana',
            'USME' => 'Maine',
            'USMD' => 'Maryland',
            'USMA' => 'Massachusetts',
            'USMI' => 'Michigan',
            'USMN' => 'Minnesota',
            'USMS' => 'Mississippi',
            'USMO' => 'Missouri',
            'USMT' => 'Montana',
            'USNE' => 'Nebraska',
            'USNV' => 'Nevada',
            'USNH' => 'New Hampshire',
            'USNJ' => 'New Jersey',
            'USNM' => 'New Mexico',
            'USNY' => 'New York',
            'USNC' => 'North Carolina',
            'USND' => 'North Dakota',
            'USOH' => 'Ohio',
            'USOK' => 'Oklahoma',
            'USOR' => 'Oregon',
            'USPA' => 'Pennsylvania',
            'USRI' => 'Rhode Island',
            'USSC' => 'South Carolina',
            'USSD' => 'South Dakota',
            'USTN' => 'Tennessee',
            'USTX' => 'Texas',
            'USUT' => 'Utah',
            'USVT' => 'Vermont',
            'USVA' => 'Virginia',
            'USWA' => 'Washington',
            'USWV' => 'West Virginia',
            'USWI' => 'Wisconsin',
            'USWY' => 'Wyoming',
        ],
    ];

    public static $countries = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AG' => 'Antigua/Barb',
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
        'BA' => 'Bosnia-Herzego',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'VG' => 'Br Virgin Is',
        'BR' => 'Brazil',
        'BN' => 'Brunei',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CV' => 'Cape Verde',
        'KY' => 'Cayman Islands',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Isl',
        'CC' => 'Cocos Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'CI' => 'Cote d\'Ivoire',
        'HR' => 'Croatia',
        'CF' => 'Ctl African Rep',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Rep',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equat. Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Isl',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'PF' => 'Fr Polynesia',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'TF' => 'French Southern',
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
        'HM' => 'Heard/McDon. Is',
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
        'KP' => 'Korea,Democrat',
        'KR' => 'Korea,Republic',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao Peo Dem Rep',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libyan Arab Jam',
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
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'MD' => 'Moldova,Rep of',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
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
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau Island',
        'PA' => 'Panama',
        'PG' => 'Papua NewGuinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn Iles',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russian Fed',
        'RW' => 'Rwanda',
        'WS' => 'Samoa(Indp St)',
        'AS' => 'Samoa-American',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome/Prin',
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
        'SH' => 'St. Helena',
        'LC' => 'St. Lucia',
        'PM' => 'St. Pierre/Miqu',
        'VC' => 'St. Vincent',
        'KN' => 'St.Kitts-Nevis',
        'SD' => 'Sudan',
        'SR' => 'Surinam, Rep of',
        'SJ' => 'Sval/JanMayenIs',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Rep',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad/Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks/Caicos Is',
        'TV' => 'Tuvalu',
        'US' => 'U.S.A.',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab',
        'GB' => 'United Kingdom',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'VI' => 'Virgin Islands',
        'WF' => 'Wallis/FutunaIs',
        'YE' => 'Yemen Republic',
        'CD' => 'Zaire',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'IO' => 'Br Indian Oc Tr',
    ];

    public static $phoneTypes = [
        'H' => 'Home',
        'B' => 'Business',
        'M' => 'Mobile',
    ];

    public static $preferredLanguages = [
        'th' => 'Thai',
        'en' => 'English',
    ];

    protected $fields;

    protected $languageMap = [
        'th' => 'T',
        'en' => 'E',
    ];
    private $retrying = 0;

    public function InitBrowser()
    {
        $this->useSelenium();
        $this->useChromium();
        $this->usePacFile();

//        $this->AccountFields['BrowserState'] = null;
        //		$this->http->TimeLimit = 500;
        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP());
        } else {
            $this->http->SetProxy('localhost:8000');
        }
    }

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;

        $fields['PreferredLanguage'] = $this->languageMap[$fields['PreferredLanguage']];
        $this->fields = $fields;
        $status = false;

        try {
            $this->processFields();
            $status = $this->sendValues();
        } catch (\CheckException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->http->log($e->getMessage(), LOG_LEVEL_ERROR);
            $this->http->log('Last page content:', LOG_LEVEL_ERROR);
            $this->saveResponse();

            return false;
        }

        return $status;
    }

    public function parseCaptcha()
    {
        $this->http->Log("parseCaptcha");
        $url = $this->waitForElement(\WebDriverBy::xpath("//img[@id = 'recaptcha_challenge_image']"), 0, true);

        if (!$url) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;

        try {
            $code = $recognizer->recognizeUrl($url->getAttribute('src'), "jpg");
        } catch (\CaptchaException $e) {
            $this->http->Log("exception: " . $e->getMessage());
            // Notifications
            if (strstr($e->getMessage(), "ERROR_ZERO_BALANCE")) {
                $this->sendNotification("WARNING! " . $recognizer->domain . " - balance is null");

                throw new \EngineError(self::CAPTCHA_ERROR_MSG);
            }
            // retries
            if ($e->getMessage() == 'server returned error: ERROR_CAPTCHA_UNSOLVABLE'
                || $e->getMessage() == 'timelimit (60) hit'
                || $e->getMessage() == 'slot not available') {
                $this->http->Log("parseCaptcha", LOG_LEVEL_ERROR);

                throw new \CheckRetryNeededException(3, 7);
            }

            return false;
        }

        return $code;
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
            'LastName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Family Name',
                'Required' => true,
            ],
            'BirthDay' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Day of Birth Date',
                'Required' => true,
            ],
            'BirthMonth' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Month of Birth Date',
                'Required' => true,
            ],
            'BirthYear' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Year of Birth Date',
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
                'Caption'  => 'Address ',
                'Required' => true,
            ],
            'City' =>
            [
                'Type'     => 'string',
                'Caption'  => 'City ',
                'Required' => true,
                'Options'  => self::$cities,
            ],
            'OtherCity' => [
                'Type'     => 'string',
                'Caption'  => 'Required for Other City',
                'Required' => false,
            ],
            'StateOrProvince' =>
            [
                'Type'     => 'string',
                'Caption'  => 'State/Province (required for Argentina, Australia, Brazil, Canada, USA)',
                'Required' => false,
                'Options'  => self::states(),
            ],
            'Country' =>
            [
                'Type'     => 'string',
                'Caption'  => '',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Postal Code',
                'Required' => true,
            ],
            'Email' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            //			'PhoneType' =>
            //			array (
            //				'Type' => 'string',
            //				'Caption' => 'Phone Type',
            //				'Required' => true,
            //				'Options' => self::$phoneTypes,
            //			),
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
            'Password' =>
            [
                'Type'     => 'string',
                'Caption'  => 'PIN (must contains 8 characters of number and alphabet with at least one number and one character)',
                'Required' => true,
            ],
            'PreferredLanguage' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Language Preference',
                'Required' => true,
                'Options'  => self::$preferredLanguages,
            ],
            'JobTitle' => [
                'Type'     => 'string',
                'Caption'  => 'Job title (required for Business Address Type)',
                'Required' => false,
            ],
            'Company' => [
                'Type'     => 'string',
                'Caption'  => 'Company (required for Business Address Type)',
                'Required' => false,
            ],
        ];
    }

    public static function states()
    {
        $statesPlain = [];

        foreach (self::$statesByCountry as $s) {
            $statesPlain = array_merge($statesPlain, $s);
        }

        return $statesPlain;
    }

    protected function sendValues()
    {
        $this->http->GetURL('http://www.thaiair.com/AIP_ROP/rop/enrolment.jsp');
        $this->checkSiteError();

        if (!$elem = $this->waitForElement(\WebDriverBy::xpath("//form[@name='mainForm']"))) {
            throw new \EngineError('Failed to load registration form');
        }

        $this->setValues();

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            throw new \EngineError('Error parse captcha');
        }

        $el = $this->waitForElement(\WebDriverBy::id('recaptcha_response_field'), $this->timeout, false);

        if (!$el) {
            throw new \EngineError('Failed to find recaptcha response field');
        }
//        $this->driver->executeScript('$(arguments[0]).trigger("focus")', [$el]);
        $el->sendKeys($captcha);
//        $el->click()->sendKeys($captcha);
//        $this->driver->executeScript('$(arguments[0]).trigger("change")', [$el]);

        if (!$elem = $this->waitForElement(\WebDriverBy::className('btnAgree'), $this->timeout, true)) {
            if ($errorMsg = $this->http->FindSingleNode("//table[contains(@class,'table_error')]/descendant::h1")) {
                throw new \UserInputError($errorMsg);
            } // Is it always user input error?

            throw new \EngineError('Agree button click error');
        }
        $elem->click();

        try {
            $alert = $this->driver->switchTo()->alert();
            $error = $alert->getText();
            $alert->dismiss();

            throw new \UserInputError($error); // Is it always user input error?
        } catch (\NoAlertOpenException $e) {
        }

        $this->checkSiteError();

        if (!$elem = $this->waitForElement(\WebDriverBy::className('btnSubmit'), $this->timeout, true)) {
            throw new \EngineError('Submit button click error');
        }
        $elem->click();

        if ($elem = $this->waitForElement(\WebDriverBy::className('btnDownloadmembercard'), $this->timeout, false)) {
            if (!$success = $this->waitForElement(\WebDriverBy::xpath("(//table[@class='table_form']//p[@class='first_txt'])[2]"))) {
                throw new \EngineError('CardNumber not found');
            }

            $this->ErrorMessage = $success->getText();
            $this->saveResponse();
            $this->http->log($this->ErrorMessage);

            return true;
        }

        $this->saveResponse();

        if ($errorMsg = $this->waitForElement(\WebDriverBy::xpath("//table[contains(@class,'table_error')]/descendant::h1"))) {
            throw new \UserInputError($errorMsg->getText());
        } // Is it always user input error?

        return false;
    }

    protected function setValues()
    {
        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($this->fields[$awKey])) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $this->http->log("Set Value \"{$provKey}\" = \"{$this->fields[$awKey]}\"");

                // radio
                if (in_array($awKey, ['Gender', 'AddressType', 'PreferredLanguage'])) {
                    $value = $this->fields[$awKey];
                    $el = $this->waitForElement(\WebDriverBy::xPath(sprintf('
                		//input[@name = "%s" and @value = "%s"]
                	', $provKey, $value)), 0);

                    if (!$el) {
                        throw new \EngineError("Failed to find input field for $provKey");
                    }
                    $el->click();

                    continue;
                }

                // else
                $el = $this->waitForElement(\WebDriverBy::xPath("//input[@name='{$provKey}'] | //select[@name='{$provKey}']"), 0);

                if (!$el) {
                    throw new \EngineError("Failed to find input field for $provKey");
                }
//                $this->driver->executeScript('$(arguments[0]).trigger("focus")', [$el]);
                $el->sendKeys($this->fields[$awKey]);
//                $el->click()->sendKeys($this->fields[$awKey]);
//                $this->driver->executeScript('$(arguments[0]).trigger("change")', [$el]);
            }
        }

        $this->driver->executeScript("
            $('select[name=dd]').trigger('focus')
            $('select[name=dd]').val({$this->fields['BirthDay']});
            $('select[name=dd]').trigger('change')
            $('select[name=mm]').trigger('focus')
            $('select[name=mm]').val({$this->fields['BirthMonth']});
            $('select[name=mm]').trigger('change')
            $('select[name=StateList]').trigger('focus')
            $('select[name=StateList]').val('{$this->fields['StateOrProvince']}');
            $('select[name=StateList]').trigger('change')
        ");
    }

    protected function processFields()
    {
        $countriesList = ['AR', 'AU', 'BR', 'CA', 'US'];
        $errorFields = [];

        // Provider uses wrong country codes for:
        // - Czech Republic (CS instead of standard CZ)
        // - French Southern (FQ instead of standard TF)
        // - Mayotte (XM instead of standard YT)
        // - Palau Island (PC instead of standard PW)
        // - Zaire (ZR instead of standard CD)
        // Map from our standard ISO code to wrong code used by provider
        $wrongCountryCodesFixingMap = [
            'CZ' => 'CS',
            'TF' => 'FQ',
            'YT' => 'XM',
            'PW' => 'PC',
            'CD' => 'ZR',
        ];

        if (isset($wrongCountryCodesFixingMap[$this->fields['Country']])) {
            $origCountryCode = $this->fields['Country'];
            $this->fields['Country'] = $wrongCountryCodesFixingMap[$this->fields['Country']];
            $this->logger->debug('Mapped standard country code "' . $origCountryCode . '" to provider code "' . $fields['Country'] . '"');
        }

        if (in_array($this->fields['Country'], $countriesList)) {
            if ((!isset($this->fields['StateOrProvince']) or trim($this->fields['StateOrProvince']) === '')) {
                $errorFields[] = 'StateOrProvince required to fill';
            }

            if (substr($this->fields['StateOrProvince'], 0, 2) !== $this->fields['Country']) {
                $errorFields[] = 'The province you selected is not in Country you selected';
            }
        }

        if (strpos($this->fields['Email'], '+') !== false) {
            $errorFields[] = 'Email field error (contains \'+\')';
        }

        if ($this->fields['City'] === 'Other' and (!isset($this->fields['OtherCity']) or trim($this->fields['OtherCity']) === '')) {
            $errorFields[] = 'OtherCity required to fill';
        }

        $this->fields['PhoneType'] = 'H';
//        if ($this->fields['PhoneType'] !== 'H')
//            $errorFields[] = 'Needs Home phone type';

        if ($this->fields['AddressType'] === 'B') {
            if (!isset($this->fields['JobTitle']) or trim($this->fields['JobTitle']) === '') {
                $errorFields[] = 'JobTitle required to fill';
            }

            if (!isset($this->fields['Company']) or trim($this->fields['Company']) === '') {
                $errorFields[] = 'Company required to fill';
            }
        }

        if ($this->fields['AddressType'] === 'H') {
            if (isset($this->fields['JobTitle'])) {
                unset($this->fields['JobTitle']);
            }

            if (isset($this->fields['Company'])) {
                unset($this->fields['Company']);
            }
        }

        if (strlen($this->fields['Password']) !== 8
            or preg_match('/[A-Za-z]+/', $this->fields['Password']) !== 1
            or preg_match('/\d+/', $this->fields['Password']) !== 1
        ) {
            $errorFields[] = 'Password must 8 alpha and numeric characters PIN with at least 1 number and 1 character';
        }

        if (!empty($errorFields)) {
            throw new \UserInputError(implode($errorFields, "; \n"));
        }
    }

    private function checkSiteError()
    {
        $message = 'The server is temporarily unavailable. You may choose to resubmit the request, but be aware that the request might have already been processed.';
        $page = $this->driver->executeScript('return document.documentElement.innerHTML');

        if (preg_match("/(Due to a temporary error the request could not be serviced)/i", $page, $m)) {
            throw new \ProviderError($message);
        }
    }
}
