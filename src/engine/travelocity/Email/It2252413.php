<?php

namespace AwardWallet\Engine\travelocity\Email;

class It2252413 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?travelocity#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#travelocity#i";
    public $reProvider = "#travelocity#i";
    public $caseReference = "6914";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "travelocity/it-2240581.eml, travelocity/it-2252413.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("plain");

                    return splitter("#\n\s*((?:Flight:|Hotel:|Provided\s+By[^\n]*?Transfer))#", $text);
                },

                "#^Flight#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Confirmation\s+Code[:\s]+([A-Z\d-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = explode("\n", re("#\n\s*Itinerary for\s*:\s*([A-Za-z.,/\s]*?)\s{2,}#s", $this->text()));
                        $res = [];

                        foreach ($names as &$name) {
                            $name = niceName($name);

                            if (strlen($name) <= 1) {
                                continue;
                            }
                            $res[$name] = 1;
                        }

                        return array_keys($res);
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Freq[.\s]+Flyer\s*:\s*([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\s+Total\s*:\s*([A-Z\d.,]+)#", $this->text()));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        re("#\n\s*(\d+)\s+adults\s+([\d,.]+)\s+([\d,.]+)\s+([\d,.]+)#", $this->text());

                        return [
                            'BaseFare' => cost(re(1)) * cost(re(2)),
                            'Tax'      => cost(re(1)) * cost(re(3)),
                        ];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#^Flight:\s*(.*?)\s+flight\s+(\d+)#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepName' => re("#\n\s*Depart\s*:\s*(.*?)\s*\(([A-Z]{3})\)#"),
                                'DepCode' => re(2),
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = orval(
                                strtotime(uberDate(re("#Dates?\s*:\s*([^\n]+)#", $this->text()))),
                                strtotime($this->parser->getHeader("date"))
                            );
                            $d = orval(
                                uberDateTime(re("#\n\s*Depart\s*:\s*[^\n]*?\s{2,}([^\n]+)#")),
                                uberDateTime(re("#\n\s*Depart\s*:\s*[^\n]+\s+([^\n]+)#"))
                            );

                            return strtotime($d, $date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                'ArrName' => re("#\n\s*Arrive\s*:\s*(.*?)\s*\(([A-Z]{3})\)#"),
                                'ArrCode' => re(2),
                            ];
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = orval(
                                strtotime(uberDate(re("#Dates?\s*:\s*([^\n]+)#", $this->text()))),
                                strtotime($this->parser->getHeader("date"))
                            );
                            $d = orval(
                                uberDateTime(re("#\n\s*Arrive\s*:\s*[^\n]*?\s{2,}([^\n]+)#")),
                                uberDateTime(re("#\n\s*Arrive\s*:\s*[^\n]+\s+([^\n]+)#"))
                            );

                            return strtotime($d, $date);
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Seats\s*:.*?\((.*?)\)#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#\n\s*Seat\s*:\s*((?:\d+[A-Z]+\s*)+)#"));
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Flight\s+Time\s*:\s*([^\n]+)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Meal\s*:\s*([^\n]+)#");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#Non\-stop#i") ? 0 : null;
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
                },

                "#^Hotel#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation Code[:\s]+([A-Z\d\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#^Hotel\s*:\s*([^\n]+)#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = uberDate(re("#Dates?\s*:\s*([^\n]+)#", $this->text()));

                        return correctDate(uberDateTime(re("#\n\s*Check[\s-]*?in\s*:\s*([^\n]+)#i")), $date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = uberDate(re("#Dates?\s*:\s*([^\n]+)#", $this->text()));

                        return correctDate(uberDateTime(re("#\n\s*Check[\s-]*?out\s*:\s*([^\n]+)#i")), $date);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = re("#^Hotel\s*:\s*[^\n]+\s+[^\n]+\s+(.*?)\s*\nRoom:#ims");

                        return [
                            'Phone'   => detach("#\n\s*Tel:\s*([\d\-\(\)+.]{5,})#", $addr),
                            'Address' => nice($addr),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\d+\s*Adults?\s*\-\s*([^\n\)]+)#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s*Adults?#i");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s+room#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Resort Suite\s*:\s*([^\n]+)#");
                    },
                ],

                "#^Provided#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#Transfer\s+Confirmation[\-\s:]+([A-Z\d\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = explode("\n", re("#\n\s*Itinerary for\s*:\s*([A-Za-z.,/\s]*?)\s{2,}#s", $this->text()));
                        $res = [];

                        foreach ($names as &$name) {
                            $name = niceName($name);

                            if (strlen($name) <= 1) {
                                continue;
                            }
                            $res[$name] = 1;
                        }

                        return array_keys($res);
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_BUS;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepAddress' => re("#^Provided\s+By:\s*(.*?)\s+to\s+(.+)#"),
                                'ArrAddress' => re(2),
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(re("#\n\s*Date\s*:\s*([^\n]+)#"));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
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
