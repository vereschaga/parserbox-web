<?php

namespace AwardWallet\Engine\sabre\Email;

class It1637752 extends \TAccountCheckerExtended
{
    public $reFrom = "#sabre#i";
    public $reProvider = "#sabre#i";
    public $rePlain = "# Sabre® Virtually There#i";
    public $typesCount = "1";
    public $langSupported = "es";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "sabre/it-1637752.eml, sabre/it-1637761.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return splitter("#(\n\s*Confirmación de aerolínea:)#");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmación de aerolínea:\s*([^\n]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Pasajero:\s*([^\n\[]+)#", $this->text());
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*\d+\s+\w+\s+\d{4}\s+[^\n]*?\s+([A-Z\d]{2})\s*(\d+)#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Desde:\s*([^\n]+)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dep = uberDate() . ', ' . uberTime();
                            $arr = uberDate() . ', ' . uberTime(2);

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Hasta:\s*([^\n]+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Sale:\s*[^\s]+\s+([^\s]+)#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Asiento:\s*([^\n]+)#");
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
        return ["es"];
    }
}
