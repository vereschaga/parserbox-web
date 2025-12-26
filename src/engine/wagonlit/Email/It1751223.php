<?php

namespace AwardWallet\Engine\wagonlit\Email;

class It1751223 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?wagonlit#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = [
        ['#Wagonlit#', 'blank', '-1000'],
    ];
    public $reSubject = "";
    public $reFrom = [
        ['#wagonlit#i', 'us', ''],
    ];
    public $reProvider = [
        ['#wagonlit#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "1";
    public $caseReference = "";
    public $upDate = "17.03.2015, 14:42";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "wagonlit/it-1750442.eml, wagonlit/it-1751223.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("application/pdf", "text");

                    return splitter("#(\n\s*\w{3},\s*\d+\s+\w{3}\s+\d{4}\s+[^\n]*?\s+Ticket\s+No\s*:\s*|\n\s*Hotel\s*:\s*)#", $text);
                },

                "#Departure Date#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $air = re("#\d{4}\s+(.*?)\s*,\s*[A-Z\d]{2}\d+\s+Ticket#");

                        return orval(
                            re("#\n\s*$air\s+([A-Z\d\-]+)\s*\n#", $this->text()),
                            re("#\n\s*Reservation Code\s*:\s*([A-Z\d\-]+)#", $this->text())
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Traveler\s*:\s*([A-Z \d,.]+)#", $this->text());
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total\s*:\s*([^\n]+)#", $this->text()));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Fare\s*:\s*([^\n]+)#", $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes/Fees/Charges\s*:\s*([^\n]+)#", $this->text()));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Issued Date\s*:\s*([^\n]+)#", $this->text()));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#,\s*([A-Z\d]{2}\d+)\s+Ticket#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*From\s*:\s*([^\n]+)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(re("#\n\s*Departure Date\s*:\s*([^\n]+)#") . ',' . re("#\n\s*Departure Time\s*:\s*([^\n]+)#"));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*To\s*:\s*([^\n]+)#");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(re("#\n\s*Arrival Date\s*:\s*([^\n]+)#") . ',' . re("#\n\s*Arrival Time\s*:\s*([^\n]+)#"));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Aircraft Type\s*:\s*([^\n]+)#");
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return [
                                'BookingClass' => re("#\n\s*Booking Class\s*:\s*([A-Z])\s*\-\s*([^\n]+)#"),
                                'Cabin'        => re(2),
                            ];
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Seat Request\s*:\s*(\d+[A-Z]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Journey Time\s*:\s*([^\n]+)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Meal Request\s*:\s*([^\n]+)#");
                        },
                    ],
                ],

                "#Hotel\s*:\s*#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation\s*:\s*([^\n]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hotel\s*:\s*([^\n]+)#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check In\s*:\s*([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check Out\s*:\s*([^\n]+)#"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(clear("#(?:Duration|Check Out)\s*:\s*[^\n]+#", re("#\n\s*Address\s*:\s*(.*?)\s+Phone\s*:#ims"))));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Phone\s*:\s*([^\n]+)#");
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Fax\s*:\s*([^\n]+)#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room\(s\)\s*:\s*(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Rate\s*:\s*([^\n]+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hotel & Lodging Remarks\s+([^\n]+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return nice(clear("#Room\(s\)\s*:\s*[^\n]+#", re("#\n\s*Room Type\s*:\s*(.*?)\s+Rate\s*:#ims")));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Issued Date\s*:\s*([^\n]+)#", $this->text()));
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
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
        return true;
    }
}
