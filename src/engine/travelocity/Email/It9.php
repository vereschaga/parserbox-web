<?php

namespace AwardWallet\Engine\travelocity\Email;

class It9 extends \TAccountCheckerExtended
{
    public $reFrom = "#[@.]@travelocity.#i";
    public $reProvider = "#[@.]@travelocity.#i";
    public $rePlain = "#\n[>\s]*(Expéditeur|From)\s*:[^\n]*?Travelocity#i";
    public $typesCount = "";
    public $langSupported = "";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "travelocity/it-9.eml";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return splitter("#(\n\s*For your boarding pass, use reference)#");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\s*reference code\s*([\d\w\-]+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(\w{3},\s*\w{3}\s*\d+,\s*\d{4}\s*[^\n\(]*?\(\w{3}\)\s+to\s+[^\n\(]*?\(\w{3}\)\s+Depart:|\n\s*Depart:)#ms");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+Flight\s*(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $depTime = re("#Depart\s*:\s*(\d+:\d+\s*\w{2})#");
                            $arrTime = re("#Arrive\s*:\s*(\d+:\d+\s*\w{2})#");

                            // Fri, Apr 4, 2014 Los Angeles International Airport, (LAX) to Honolulu International Airport, (HNL)
                            re("#\w{3},\s*(\w{3}\s*\d+,\s*\d{4})\s*([^\n\(]*?)[,\s]*\((\w{3})\)\s+to\s+([^\n\(]*?)[,\s]*\((\w{3})\)#ms");

                            return [
                                'DepName' => re(2),
                                'DepCode' => re(3),
                                'ArrName' => re(4),
                                'ArrCode' => re(5),
                                'DepDate' => strtotime(re(1) . ', ' . $depTime),
                                'ArrDate' => strtotime(re(1) . ', ' . $arrTime),
                            ];
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            //  (LAX) United Arrive:
                            return re("#\(\w{3}\)\s*(.*?)\s*Arrive:#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#Total Travel Time:\s*((?:\d+\s*hrs\s*)*\d+\s*mins)#");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\(on\s*([^\)]+)\)#");
                        },
                    ],

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Passenger Name')]/ancestor-or-self::tr[1]/following-sibling::tr[1]/td[1]");
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
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
}
