<?php

namespace AwardWallet\Engine\jetstar\Email;

class It2096850 extends \TAccountCheckerExtended
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
                        return re("#Reservation number:\s*([A-Z\d-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#Hello\s*([A-Za-z\s*]+)\n#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Departing')]/ancestor-or-self::table[3]";

                        return xpath($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $node = node(".//td[1]//b");

                            return uberAir($node);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $node = node(".//*[contains(text(), 'Departing:')]/ancestor-or-self::tr[2]//b");

                            return $node;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(node("(.//td[2])[1]"));
                            $time = uberTime(node(".//*[contains(text(), 'Departing')]/ancestor-or-self::tr[2]"));
                            $date = $date . " " . $time;

                            return totime($date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $node = node(".//*[contains(text(), 'Arriving:')]/ancestor-or-self::tr[2]//b");

                            return $node;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDateTime(node(".//*[contains(text(), 'Arriving:')]/ancestor-or-self::tr[2]"));

                            return totime($date);
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
