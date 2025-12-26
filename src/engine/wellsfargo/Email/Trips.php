<?php

namespace AwardWallet\Engine\wellsfargo\Email;

class Trips extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Thank\s+you\s+for\s+your\s+recent\s+\*?Wells\s+Fargo\s+Rewards.+?\s\*?AIR\*?\s.+?\s\*?Flight\s+Number\*?\s*:\s*\d+\s+Class\s*:#si', 'blank', '5000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Wells Fargo Rewards Travel Itinerary', 'blank', ''],
        ['Wells Fargo Rewards Travel Final Confirmation', 'blank', ''],
    ];
    public $reFrom = [
        ['#\bmywellsfargorewards\.com#', 'blank', ''],
    ];
    public $reProvider = [
        ['#\bmywellsfargorewards\.com#', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "23.09.2015, 15:10";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "wellsfargo/it-2595549.eml, wellsfargo/it-3008648.eml, wellsfargo/it-3043106.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $data['RecordLocator'] = node("//*[contains(text(), 'Agency Record Locator')]", null, true, "#Agency Record Locator:\s+([^\n]+)#ms");

                        return $data;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return explode(',', node("//*[contains(text(),'Passengers')]/parent::*/following-sibling::*/b"));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#Total charge to payment card\s*:\s*([^\n]+)#i"));
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#Total\s+Rewards\s*:\s*([^\n]+)#i");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $Status = nodes("//*[contains(text(), 'Status')]", null, "#Status:\s+([^\n]+)#ms");
                        end($Status);

                        return $Status[key($Status)];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        //$xpath = "//*[normalize-space(text())='AIR']/ancestor::tr[1]";
                        return splitter("#(\n\s*AIR\s*?\n\s*\w+,\s+\d+\s*\w+\s+\d+.+?Flight\s+Number[\s:]+\d+)#si", re("#Record\s+Locator\s*:(.+?)(?>For this itinerary, the following|Total charge to payment|Thank you for participating)#si"));
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#Flight\s+Number[\s:]+(\d+)#i");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $data['DepCode'] = re("#From\s*:\s*\(([A-Z]+)\)\s+([^\n]+)#");
                            $data['DepName'] = re(2);

                            return $data;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#AIR\s+\w+,\s*(\d+)\s*(\w+)\s+(\d+)#") . " " . re(2) . " " . re(3);
                            $data['DepDate'] = totime($date . ", " . re("#Depart\s*:\s*(\d+:\d+\s*\w*)#"));
                            $data['ArrDate'] = totime($date . ", " . re("#Arrive\s*:\s*(\d+:\d+\s*\w*)#"));
                            correctDates($data['DepDate'], $data['ArrDate']);

                            return $data;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $data['ArrCode'] = re("#\sTo\s*:\s*\(([A-Z]+)\)\s+([^\n]+)#");
                            $data['ArrName'] = re(2);

                            return $data;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#\((\w+)\)\s+(?>Operated\s+By\s*:\s*[^\n]+\s+)?Flight\s+Number\s*:#i");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#Equipment\s*:\s*([^\n]+)#i");
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return re("#\sMiles\s*:\s*(\d+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $data['BookingClass'] = re("#\sClass\s*:\s*(\w{1,2})\s*\-\s*(\w[^\n]+)#");
                            $data['Cabin'] = re(2);

                            return $data;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#\sSeats\s*:\s*(\d+[A-Z][^\n]*)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\sDuration\s*:\s*([^\n]+)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\sMEAL\s*:\s*([^\n]+)#i");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#\sStops\s*:\s*(\d+)#i");
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return re("#Confirmation\s+number\s+is\s+([\w-]+)#i");
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
