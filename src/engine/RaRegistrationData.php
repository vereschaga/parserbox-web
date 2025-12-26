<?php

namespace AwardWallet\Engine;

class RaRegistrationData
{
    public static $providers = [
        'tapportugal',
        'hawaiian',
        'singaporeair',
        'iberia',
        'qantas',
        'skywards',
        'israel',
        'korean',
        'turkish',
    ];

    public static $emails = [
        'allouncers@gmail.com',
    ];

    public static $accountsEmails = [
        'HobbinAntiplexer@gmail.com',
    ];

    public static $genderTitle = [
        'male'   => 'Mr',
        'female' => 'Mrs',
    ];

    public static $states = [
        'DC' => [
            'zipCode'  => '20011',
            'city'     => 'Washington',
            'areaCode' => '202',
        ],
        'AL' => [
            'zipCode'  => '35005',
            'city'     => 'Adamsville',
            'areaCode' => '205',
        ],
        'AK' => [
            'zipCode'  => '99501',
            'city'     => 'Anchorage',
            'areaCode' => '907',
        ],
        'AZ' => [
            'zipCode'  => '85002',
            'city'     => 'Phoenix',
            'areaCode' => '602',
        ],
        'AR' => [
            'zipCode'  => '71603',
            'city'     => 'Pine Bluff',
            'areaCode' => '870',
        ],
        'CA' => [
            'zipCode'  => '96106',
            'city'     => 'Clio',
            'areaCode' => '530',
        ],
        'CO' => [
            'zipCode'  => '80001',
            'city'     => 'Arvada',
            'areaCode' => '303',
        ],
        'CT' => [
            'zipCode'  => '06001',
            'city'     => 'Avon',
            'areaCode' => '860',
        ],
        'DE' => [
            'zipCode'  => '19701',
            'city'     => 'Bear',
            'areaCode' => '302',
        ],
        'FL' => [
            'zipCode'  => '32007',
            'city'     => 'Bostwick',
            'areaCode' => '706',
        ],
        'GA' => [
            'zipCode'  => '30002',
            'city'     => 'Avondale Estates',
            'areaCode' => '678',
        ],
        'HI' => [
            'zipCode'  => '96701',
            'city'     => 'Aiea',
            'areaCode' => '808',
        ],
        'ID' => [
            'zipCode'  => '83204',
            'city'     => 'Pocatello',
            'areaCode' => '208',
        ],
        'IN' => [
            'zipCode'  => '46001',
            'city'     => 'Alexandria',
            'areaCode' => '765',
        ],
        'IA' => [
            'zipCode'  => '50003',
            'city'     => 'Adel',
            'areaCode' => '229',
        ],
        'LA' => [
            'zipCode'  => '70001',
            'city'     => 'Metairie',
            'areaCode' => '504',
        ],
        'ME' => [
            'zipCode'  => '03901',
            'city'     => 'Berwick',
            'areaCode' => '207',
        ],
        'MD' => [
            'zipCode'  => '21930',
            'city'     => 'Georgetown',
            'areaCode' => '410',
        ],
        'MA' => [
            'zipCode'  => '01001',
            'city'     => 'Agawam',
            'areaCode' => '413',
        ],
        'MI' => [
            'zipCode'  => '48003',
            'city'     => 'Almont',
            'areaCode' => '810',
        ],
        'MN' => [
            'zipCode'  => '55001',
            'city'     => 'Afton',
            'areaCode' => '651',
        ],
        'MS' => [
            'zipCode'  => '38601',
            'city'     => 'Abbeville',
            'areaCode' => '662',
        ],
        'MO' => [
            'zipCode'  => '65899',
            'city'     => 'Springfield',
            'areaCode' => '417',
        ],
        'MT' => [
            'zipCode'  => '59001',
            'city'     => 'Absarokee',
            'areaCode' => '406',
        ],
        'NE' => [
            'zipCode'  => '68001',
            'city'     => 'Abie',
            'areaCode' => '402',
        ],
        'NV' => [
            'zipCode'  => '89830',
            'city'     => 'Montello',
            'areaCode' => '775',
        ],
        'NH' => [
            'zipCode'  => '03031',
            'city'     => 'Amherst',
            'areaCode' => '603',
        ],
        'NJ' => [
            'zipCode'  => '07001',
            'city'     => 'Avenel',
            'areaCode' => '732',
        ],
        'NM' => [
            'zipCode'  => '87005',
            'city'     => 'Bluewater',
            'areaCode' => '505',
        ],
        'NY' => [
            'zipCode'  => '14925',
            'city'     => 'Elmira',
            'areaCode' => '607',
        ],
        'NC' => [
            'zipCode'  => '27009',
            'city'     => 'Belews Creek',
            'areaCode' => '336',
        ],
        'ND' => [
            'zipCode'  => '58001',
            'city'     => 'Abercrombie',
            'areaCode' => '701',
        ],
        'OH' => [
            'zipCode'  => '43001',
            'city'     => 'Alexandria',
            'areaCode' => '740',
        ],
        'OK' => [
            'zipCode'  => '73001',
            'city'     => 'Albert',
            'areaCode' => '405',
        ],
        'OR' => [
            'zipCode'  => '97004',
            'city'     => 'Beavercreek',
            'areaCode' => '971',
        ],
        'PA' => [
            'zipCode'  => '15004',
            'city'     => 'Atlasburg',
            'areaCode' => '724',
        ],
        'RI' => [
            'zipCode'  => '02801',
            'city'     => 'Adamsville',
            'areaCode' => '401',
        ],
        'SC' => [
            'zipCode'  => '29001',
            'city'     => 'Alcolu',
            'areaCode' => '803',
        ],
        'SD' => [
            'zipCode'  => '57002',
            'city'     => 'Aurora',
            'areaCode' => '605',
        ],
        'TN' => [
            'zipCode'  => '37010',
            'city'     => 'Adams',
            'areaCode' => '615',
        ],
        'TX' => [
            'zipCode'  => '73301',
            'city'     => 'Austin',
            'areaCode' => '512',
        ],
        'UT' => [
            'zipCode'  => '84002',
            'city'     => 'Altonah',
            'areaCode' => '435',
        ],
        'VT' => [
            'zipCode'  => '05907',
            'city'     => 'Norton',
            'areaCode' => '802',
        ],
        'VA' => [
            'zipCode'  => '20101',
            'city'     => 'Dulles',
            'areaCode' => '571',
        ],
        'WA' => [
            'zipCode'  => '98002',
            'city'     => 'Auburn',
            'areaCode' => '253',
        ],
        'WV' => [
            'zipCode'  => '26886',
            'city'     => 'Onego',
            'areaCode' => '304',
        ],
        'WI' => [
            'zipCode'  => '53001',
            'city'     => 'Adell',
            'areaCode' => '920',
        ],
        'WY' => [
            'zipCode'  => '82001',
            'city'     => 'Cheyenne',
            'areaCode' => '307',
        ],
        'KS' => [
            'zipCode'  => '66025',
            'city'     => 'Eudora',
            'areaCode' => '785',
        ],
        'KY' => [
            'zipCode'  => '40022',
            'city'     => 'Finchville',
            'areaCode' => '502',
        ],
        'IL' => [
            'zipCode'  => '60042',
            'city'     => 'Island Lake',
            'areaCode' => '815',
        ],
    ];

    public static function isInAccountsEmails($email): bool
    {
        if (in_array($email, self::$accountsEmails)) {
            return true;
        }

        foreach (self::$accountsEmails as $accountEmail) {
            $parts = explode('@', $accountEmail);
            $re = "/^" . preg_replace("/([^\s\\\.])/u", "$1\.?", preg_quote($parts[0], '/')) . '@' . preg_quote($parts[1], '/') . "$/i";

            if (preg_match($re, $email)) {
                return true;
            }
        }

        return false;
    }
}
