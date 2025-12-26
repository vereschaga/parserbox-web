<?php

namespace AwardWallet\Engine\tport\Email;

class It2479744 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?travelport#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#travelport#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#travelport#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "18.02.2015, 19:08";
    public $crDate = "17.02.2015, 14:46";
    public $xPath = "";
    public $mailFiles = "tport/it-2479744.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("//*[normalize-space(text()) = 'Hotel' or normalize-space(text()) = 'Flight']/ancestor-or-self::div[2]");
                },

                "#^Hotel#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $hotelName = re("#\n\s*([^\n]+)\s+Address:#", implode("\n", nodes(".//text()[normalize-space()!='']")));

                        return re("#\n\s*{$hotelName}\s+Confirmation\s+Number:\s*([A-Z\d\-]+)#i", $this->text());
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*([^\n]+)\s+Address:#", implode("\n", nodes(".//text()[normalize-space()!='']")));
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\n\s*Check in\s*:\s*\w+\s+(\d+\s+\w+),\s+(\d{4})#i", implode("\n", nodes(".//text()[normalize-space()!='']"))) . ' ' . re(2));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\n\s*Check out\s*:\s*\w+\s+(\d+\s+\w+),\s+(\d{4})#i", implode("\n", nodes(".//text()[normalize-space()!='']"))) . ' ' . re(2));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $info = implode("\n", nodes(".//*[contains(normalize-space(text()), 'Address:')]/ancestor-or-self::dt[1]/following::dd[1]//text()[normalize-space()]"));

                        return [
                            'Phone'   => detach("#\n\s*([\d\(\)\-+ ]{5,})$#", $info),
                            'Address' => nice($info),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*Traveller\s+Name[:\s]+([^\n]+)#i", $this->text())];
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Nightly\s+Rate\s*:\s*([^\n]+)#", implode("\n", nodes(".//text()[normalize-space()!='']")));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room Type\s*:\s*([^\n]+)#", implode("\n", nodes(".//text()[normalize-space()!='']"))); // == 'N/A' ? null : re(1);
                    },
                ],

                "#^Flight#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Supplier Confirmation Number[^:]+:\s*([A-Z\d\-]+)#ix", $this->text());
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*Traveller\s+Name[:\s]+([^\n]+)#i", $this->text())];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Subtotal Price\s*:\s*([^\n]+)#", implode("\n", nodes('.//text()[normalize-space()]'))));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Base Fare\s*:\s*([^\n]+)#", implode("\n", nodes('.//text()[normalize-space()]'))));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes & Carrier Imposed Fees\s*:\s*([^\n]+)#ix", implode("\n", nodes('.//text()[normalize-space()]'))));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath(".//*[normalize-space(text()) = 'Leave' or normalize-space(text()) = 'Return']/ancestor-or-self::div[2]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*(.*?)\s+(\d+)\s+Depart:#", implode("\n", nodes('.//text()[normalize-space()]'))),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = clear("#,#", re("#^\w+\s+([^\n]+)#", implode("\n", nodes('.//text()[normalize-space()]'))));

                            $dep = $date . ',' . uberTime(1);

                            if (re("#Arrives\s+(\d+)/(\d+)/(\d+)#")) {
                                $date = re(3) . '-' . re(2) . '-' . re(1);
                            }
                            $arr = $date . ',' . uberTime(2);

                            return [
                                'DepDate' => totime($dep),
                                'ArrDate' => totime($arr),
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", 2);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            $info = text(xpath(".//*[contains(normalize-space(text()), 'Seat:')]/ancestor-or-self::div[1]"));

                            return [
                                'BookingClass'  => detach("#\(([A-Z])\s+Class\)#", $info),
                                'Duration'      => detach("#(\d+\s*hrs\s*\d+\s*mins?)#", $info),
                                'TraveledMiles' => detach("#(\d+\s*miles?)#", $info),
                                'Seats'         => re("#^\d+[A-Z]+#", detach("#Seat\s*:\s*([^\n]+)#", $info)),
                                'Aircraft'      => nice($info),
                            ];
                        },
                    ],
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
