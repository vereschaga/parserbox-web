<?php

namespace AwardWallet\Engine\jetstar\Email;

class It2096742 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?jetstar#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#jetstar#i";
    public $reProvider = "#jetstar#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "";
    public $pdfRequired = "0";

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
                        return re("#Reservation no.\s*([A-Z\d-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#Hi\s*([A-Za-z\s*]+)\n#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'FLIGHT')]/ancestor-or-self::table[2]";

                        return xpath($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node(".//table//b[2]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $node = node(".//tr[4]//span[1]");

                            return $node;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $node = nodes(".//tr[4]//b");
                            $node = uberDatetime($node[0] . " " . $node[1]);

                            return totime($node);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $node = node(".//tr[6]//span[1]");

                            return $node;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $node = nodes(".//tr[6]//b");
                            $node = uberDatetime($node[0] . " " . $node[1]);

                            return totime($node);
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
