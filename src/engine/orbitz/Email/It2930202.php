<?php

namespace AwardWallet\Engine\orbitz\Email;

class It2930202 extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-2930202.eml";

    public $rePlain = [
        ['#I\s+just\s+booked\s+a\s+flight\s+using\s+the[^\n]*?Orbitz#i', 'us', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#orbitz#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "6711";
    public $upDate = "16.03.2015, 14:40";
    public $crDate = "";
    public $xPath = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath(".//text()[contains(., 'Depart:')]/ancestor::tbody[1]");
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                "DepCode" => re("#\(([A-Z]+)\)\s*(.+)#", node(".//*[contains(text(), 'Depart:')]/ancestor::tr/following-sibling::*[1]")),
                                "DepName" => re(2),
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(
                                re("#(\d+:\d+)([AP]M)\s+-\s+\w+,\s+(\w+)\s+(\d+),\s+(\d+)#", node(".//*[contains(text(), 'Depart:')]/following-sibling::*[1]"), 4) . ' ' .
                                re(3) . ' ' . re(5) . ', ' . re(1) . ' ' . re(2)
                            );
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                "ArrCode" => re("#\(([A-Z]+)\)\s*(.+)#", node(".//*[contains(text(), 'Arrive:')]/ancestor::tr/following-sibling::*[1]")),
                                "ArrName" => re(2),
                            ];
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(
                                re("#(\d+:\d+)([AP]M)\s+-\s+\w+,\s+(\w+)\s+(\d+),\s+(\d+)#", node(".//*[contains(text(), 'Arrive:')]/following-sibling::*[1]"), 4) . ' ' .
                                re(3) . ' ' . re(5) . ', ' . re(1) . ' ' . re(2)
                            );
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#(.*?)\s*\#(\d+)#", node("./preceding-sibling::*[1]")),
                                'FlightNumber' => re(2),
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
