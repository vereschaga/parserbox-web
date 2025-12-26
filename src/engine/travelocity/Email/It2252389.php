<?php

namespace AwardWallet\Engine\travelocity\Email;

class It2252389 extends \TAccountCheckerExtended
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
    public $mailFiles = "travelocity/it-2252388.eml, travelocity/it-2252389.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("plain");

                    return splitter("#\n\s*(Flight:|Your\s+hotel\s+confirmation)#");
                },

                "#^Your\s+hotel#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#confirmation number is\s*:\s*([A-Z\d-]+)#ix");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#^Your hotel[^\n]+\s+([^\n\-\(]+)#i");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check[\s\-]*?in\s+Time\s*:\s*([^\n]+)#i"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check[\s\-]*?out\s+Time\s*:\s*([^\n]+)#i"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = trim(re("#^Your hotel[^\n]+\s+[^\n]+\s+(.*?)\n\s*Check[\s\-]*?in#is"));

                        return [
                            'Phone'   => clear("#\.#", detach("#\n\s*([\d\-+.\(\)]{5,})\s*$#s", $addr), '-'),
                            'Address' => nice($addr),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#You have been confirmed for \d+ person:\s*([^\n]+)#ix", $this->text());
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number\s+of\s+Rooms\s*:\s*(\d+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#^Your\s+hotel[^\n]+\s+[^\n-]+\-\s*([^\n\(]+)#is");
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#Room\s+amenities\s+include:\s*([^.]+)#si"));
                    },
                ],

                "#^Flight#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Your air confirmation number is\s*:\s*([A-Z\d\-]+)#ix", $this->text());
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#You have been confirmed for \d+ person:\s*([^\n]+)#ix", $this->text());
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Total package price\s*:\s*([^\n]+)#", $this->text()));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#^Flight\s*:\s*(.*?)\s+(\d+)(?:\s*\n|\s*\()#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*From\s*:\s*([^\n]+)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#\n\s*(\w+\s+\d+,\s*\d{4})\s*\-\s*\w+\s+\d+,\s*\d{4}\s*.*?\n\s*AIR\s+TRAVEL#s", $this->text());
                            $year = re("#\d{4}#", $date);

                            $dep = uberDateTime(re("#\n\s*Departs\s*:\s*([^\n]+)#"));
                            $arr = uberDateTime(re("#\n\s*Arrives\s*:\s*([^\n]+)#"));

                            correctDates($dep, $arr, $date);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*To\s*:\s*([^\n]+)#");
                        },
                    ],
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
        return false;
    }
}
