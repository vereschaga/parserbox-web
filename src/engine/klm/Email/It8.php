<?php

namespace AwardWallet\Engine\klm\Email;

class It8 extends \TAccountCheckerExtended
{
    public $reFrom = "#@klm.com#i";
    public $reProvider = "#[@.]klm.com#i";
    public $rePlain = "#From:\s*\"*KLM Reserveringen#i";
    public $typesCount = "1";
    public $langSupported = "nl";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function (&$text = '', &$node = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function (&$text = '', &$node = null) {
                        return "T";
                    },

                    "RecordLocator" => function (&$text = '', &$node = null) {
                        return re("#Boekingscode\s+([\d\w]+)#");
                    },

                    "Passengers" => function (&$text = '', &$node = null) {
                        return trim(re("#\s+Passagiers\s+([^\n\(]+)#"));
                    },

                    "TotalCharge" => function (&$text = '', &$node = null) {
                        return cost(re("#\s+Totaalprijs:\s*([^\n]+)#"));
                    },

                    "Currency" => function (&$text = '', &$node = null) {
                        return currency(re("#\s+Totaalprijs:\s*([^\n]+)#"));
                    },

                    "SegmentsSplitter" => function (&$text = '', &$node = null) {
                        return splitter("#(\n\s*(?:Vlucht|Terugreis)\s*:\s*)#ms");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function (&$text = '', &$node = null) {
                            return re("#Vluchtnummer:\s*(\w{2}\s*\d+)#");
                        },

                        "DepCode" => function (&$text = '', &$node = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function (&$text = '', &$node = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function (&$text = '', &$node = null) {
                            re("#\w+\s*(\d+\s*\w{3}\s*\d+)\s+(\d+:\d+)\s+(.*?)\n#");

                            return [
                                'DepName' => re(3),
                                'DepDate' => strtotime(en(re(1)) . ', ' . re(2)),
                            ];
                        },

                        "ArrName" => function (&$text = '', &$node = null) {
                            re("#\d+:\d+.*?\w+\s*(\d+\s*\w{3}\s*\d+)\s+(\d+:\d+)\s+(.*?)\n#ms");

                            return [
                                'ArrName' => re(3),
                                'ArrDate' => strtotime(en(re(1)) . ', ' . re(2)),
                            ];
                        },

                        "AirlineName" => function (&$text = '', &$node = null) {
                            return re("#Uitgevoerd door:\s*([^\n]+)#");
                        },

                        "Aircraft" => function (&$text = '', &$node = null) {
                            return re("#Vliegtuigtype:\s*([^\n\|]+)#");
                        },

                        "Duration" => function (&$text = '', &$node = null) {
                            return re("#Totale reistijd\s*([^\n\|]+)#");
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
        return ["nl"];
    }
}
