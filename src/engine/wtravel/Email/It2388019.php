<?php

namespace AwardWallet\Engine\wtravel\Email;

class It2388019 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?worldtrav#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = [
        ['#worldtrav#i', 'us', '/1'],
    ];
    public $reSubject = "";
    public $reFrom = [
        ['#worldtrav#i', 'us', ''],
    ];
    public $reProvider = [
        ['#worldtrav#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "20.01.2015, 18:33";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "wtravel/it-2388019.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $userEmail = re("#PLEASE DO NOT REPLY TO THIS EMAIL\.\s+([\w\d\._\-]+@[\w\d\._\-]+\.(?:[\w\d\._\-]+)+)#");

                    if ($userEmail) {
                        $this->parsedValue('userEmail', strtolower($userEmail));
                    }

                    $text = $this->setDocument("application/pdf", "text");

                    return splitter("#\n\s*((?:HOTEL|CAR|AIR)[\-\s]+\w+,\s*\w+\s+\d+\s+\d{4})#", $text);
                },

                "#^HOTEL#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        //print $text."====================";
                        return re("#\n\s*Confirmation\s*:\s*([A-Z\d\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*([A-Za-z,. ]+)\n\s*Address\s*:#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return [
                            'CheckInDate'  => totime(re("#\n\s*Check\s+In/Check\s+Out\s*:\s*(\w+,\s*\w+\s+\d+\s+\d+)[-\s]+(\w+,\s*\w+\s+\d+\s+\d+)#")),
                            'CheckOutDate' => totime(re(2)),
                        ];
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Address\s*:\s*([^\n]+)#");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Tel[.\s]*:\s*([^\n]+)#");
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Fax[.\s]*:\s*([^\n]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Traveler\s+([A-Z/ ]+)#", $this->text());
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number\s+of\s+Persons\s*:\s*(\d+)#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number\s+of\s+Rooms\s*:\s*(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Rate\s+per\s+night\s*:\s*([^\n]+)#");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total\s+Amount\s*:\s*([^\n]+)#", $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*([^\n]+)#");
                    },
                ],

                "#^CAR#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation\s*:\s*([A-Z\d\-]+)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $addr = re("#\n\s*Pick[\s\-]+Up[:\s]+(.*?)\s+Drop[\s\-]+Off#ims");

                        return [
                            'PickupPhone'    => trim(detach("#Tel\s*:\s*([+\d \(\)\-]+)#", $addr)),
                            'PickupFax'      => trim(detach("#Fax\s*:\s*([+\d \(\)\-]+)#", $addr)),
                            'PickupDatetime' => totime(uberDateTime(detach("#\d+:\d+\s*[APMapm]{2}\s+\w+,\s*\w+\s+\d+\s+\d+#", $addr))),
                            'PickupLocation' => nice($addr),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $addr = re("#\n\s*Drop[\s\-]+Off[:\s]+(.*?)\s+Type\s*:#ims");

                        return [
                            'DropoffPhone'    => trim(detach("#Tel\s*:\s*([+\d \(\)\-]+)#", $addr)),
                            'DropoffFax'      => trim(detach("#Fax\s*:\s*([+\d \(\)\-]+)#", $addr)),
                            'DropoffDatetime' => totime(uberDateTime(detach("#(?:\d+:\d+\s*[APMapm]{2})?\s+\w+,\s*\w+\s+\d+\s+\d+#", $addr))),
                            'DropoffLocation' => nice($addr),
                        ];
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#([^\n]+)\s+Pick[\s\-]+Up#"));
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Type\s*:\s*([^\n]+)#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Traveler|Frequent\s+Flyer\s+\#)\s+([A-Z /.,]{3,})#", $this->text());
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*(?:Estimated\s+Total|Total)\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Frequent\s+Renter\s+ID\s*:\s*([A-Z\d-]+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*([^\n]+)#");
                    },
                ],

                "#^AIR#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+(?:Booking\s+Reference|Record\s+Locator|Confirmation)\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Traveler|Frequent\s+Flyer\s+\#)\s+([A-Z /.,]{3,})#", $this->text());
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*(?:Total\s+Invoice\s+Amount|Total\s+Amount)[\s:]+([^\n]+)#i", $this->text()));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Ticket\s+Amount[\s:]+([^\n]+)#", $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*(\w+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#\s+Flight\s+([A-Z\d]{2}\s*\d+)\s+#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepCode' => orval(
                                    re("#\s+([A-Z]{3})\s*\-?\s*([A-Z]{3})\s+{$it['AirlineName']}\s*{$it['FlightNumber']}\s+\w+\s+[\d:\sAPM/]+(?:\w+\s*/\s*([A-Z]))?#", $this->text()),
                                    TRIP_CODE_UNKNOWN
                                ),
                                'ArrCode' => orval(
                                    re(2),
                                    TRIP_CODE_UNKNOWN
                                ),
                                'BookingClass' => re(3),
                            ];
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Depart\s*:\s*([^\n]+)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(re("#Depart\s*:(.*?)Arrive\s*:#ims")));
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Arrive\s*:\s*([^\n]+)#");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(re("#Arrive\s*:(.*?)Duration\s*:#ims")));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Equipment\s*:\s*([^\n]+)#");
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#\n\s*Distance\s*:\s*([^\n/]+)#"));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#(\w+)\s+(?:\(\w+\s+Select\)|Class)\n#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Seat\s*:\s*(\d+[A-Z]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Duration\s*:\s*([^\n]+)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Meal\s*:\s*([^\n]+)#");
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    //return $it;
                    $it = correctItinerary($it, true);

                    // check if there are only one air reservation and assign "Ticket amount" for it
                    $count = [];

                    foreach ($it as $i => &$cur) {
                        if (isset($cur['Kind']) && $cur['Kind'] == 'T') {
                            $count[$cur['RecordLocator']] = &$cur;
                        }
                    }

                    $keys = array_keys($count);

                    if (count($keys) == 1) {
                        $cur = $count[reset($keys)];
                        $cur['TotalCharge'] = cost(re("#\n\s*Ticket\s+Amount\s*:\s*([^\n]+)#", $this->text()));
                        $cur['Currency'] = currency(re(1));
                    }

                    return $it;
                },
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
