<?php

namespace AwardWallet\Engine\spg\Email;

class YourReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n\s*From:\s+<?(?:(?:ThePhoenician|FourPointsBySheratonPeoriaDowntown|SheratonattheFallsHotelNiagaraFall|SheratonSiouxFalls)@starwoodhotels\.com)#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#(?:ElementMiamiInternationalAirport|SheratonSanJose|TheWestinCincinnati|ThePhoenician|SheratonGrandSacramentoHotel|AloftTucsonUniversity|FourPointsBySheratonPeoriaDowntown|SheratonattheFallsHotelNiagaraFall|SheratonSiouxFalls|TheWestinPrinceton)@starwoodhotels\.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#starwoodhotels\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "6710";
    public $upDate = "24.04.2015, 14:38";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "spg/it-1913013.eml, spg/it-1919354.eml, spg/it-2116176.eml, spg/it-2116177.eml, spg/it-2229108.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $regex = '#';
                        $regex .= 'Reservation\s+Number:\s+(?P<ConfirmationNumber>[A-Z\d\-]+)\s+';
                        $regex .= 'Arrival\s+Date:\s+(?P<CheckInDate>\d+-\d+-\d{4})\s+';
                        $regex .= 'Guest\s+Name\(s\)[:\s]+(?P<GuestNames>.*)\s+Arrival\s+Flight:\s*.*?\s+';
                        $regex .= 'Arrival\s+Time:.*?\s+';
                        $regex .= 'Company\s+Name:.*?\s+';
                        $regex .= 'Departure\s+Date:\s+(?P<CheckOutDate>\d+-\d+-\d{4})\s+';
                        $regex .= '(?:Room\s+Type|Accommodation):\s+(?P<RoomType>.*?)\s+';
                        $regex .= 'Number\s+of\s+Rooms:\s+(?P<Rooms>\d+)\s+';
                        $regex .= 'Daily\s+Room\s+Rate:\s+(?P<Rate>.*?)\s+';
                        $regex .= 'Number\s+of\s+Guests:\s+(?P<Guests>\d+)';
                        $regex .= '#';

                        if (preg_match($regex, $text, $m)) {
                            foreach (['CheckIn', 'CheckOut'] as $key) {
                                $res[$key . 'Date'] = strtotime(str_replace('-', '/', $m[$key . 'Date']));
                            }
                            $keys = [
                                'ConfirmationNumber',
                                'GuestNames',
                                'RoomType',
                                'Rooms',
                                'Rate',
                                'Guests',
                            ];
                            copyArrayValues($res, nice($m), $keys);
                        }

                        return $res;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $hotels = [
                            [
                                'email' => 'SheratonGrandSacramentoHotel@starwoodhotels.com',
                                'hotel' => 'Sheraton Grand Sacramento Hotel',
                            ],
                            [
                                'email' => 'FourPointsBySheratonPeoriaDowntown@starwoodhotels.com',
                                'hotel' => 'Four Points By Sheraton Peoria Downtown',
                            ],
                            [
                                'email' => 'SheratonattheFallsHotelNiagaraFall@starwoodhotels.com',
                                'hotel' => 'Sheraton at the Falls Hotel Niagara Fall',
                            ],
                            [
                                'email' => 'SheratonSiouxFalls@starwoodhotels.com',
                                'hotel' => 'Sheraton Sioux Falls',
                            ],
                            [
                                'email' => 'AloftTucsonUniversity@starwoodhotels.com',
                                'hotel' => 'Aloft Tucson University',
                            ],
                            [
                                'email' => 'ThePhoenician@starwoodhotels.com',
                                'hotel' => 'The Phoenician',
                            ],
                            [
                                'email' => 'TheWestinCincinnati@starwoodhotels.com',
                                'hotel' => 'The Westin Cincinnati',
                            ],
                            [
                                'email' => 'TheWestinPrinceton@starwoodhotels.com',
                                'hotel' => 'The Westin Princeton',
                            ],
                            [
                                'email' => '<SheratonSanJose@starwoodhotels.com>',
                                'hotel' => 'Sheraton San Jose',
                            ],
                            [
                                'email' => 'ElementMiamiInternationalAirport@starwoodhotels.com',
                                'hotel' => 'Element Miami International Airport',
                            ],
                        ];
                        $from = $this->parser->getHeader('From');
                        $returnPath = $this->parser->getHeader('return-path');
                        $returnTo = is_array($returnPath) ? $returnPath[1] : $returnPath;
                        $fromText = nice(re('#\n\s*From:\s+<?(.*?@.*?)[><]#'));
                        $searchIn = [
                            $from,
                            $returnTo,
                            $fromText,
                        ];

                        foreach ($hotels as $h) {
                            if (in_array($h['email'], $searchIn)) {
                                return $h['hotel'];
                            }
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $regex = '#';
                        $regex .= '\n\s*\n(.*\s+.*)\s+';
                        $regex .= 'Phone:\s+(.*?)';
                        $regex .= '#';
                        $name = $it['HotelName'];
                        $addr = $name;

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'Address' => orval(
                                    nice($m[1], ','),
                                    $name
                                ),
                                'Phone' => $m[2],
                            ];
                        }

                        return $it['HotelName'];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re('#It\s+is\s+a\s+pleasure\s+to\s+confirm\s+your\s+reservation\s+as\s+follows#i')) {
                            return 'Confirmed';
                        }

                        return trim(re("#\s+Status:([^\n]+)#"));
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
