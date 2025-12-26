<?php

namespace AwardWallet\Engine\onetravel\Email;

class It1833884 extends \TAccountCheckerExtended
{
    public $reFrom = "#onetravel#i";
    public $reProvider = "#onetravel#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?onetravel|booking with OneTravel#i";
    public $rePlainRange = "3000";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "onetravel/it-1833884.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Booking Number\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Traveler Name')]/ancestor::tr[1]/following-sibling::tr/td[2]");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Departs')]/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Flight\s*:\s*(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#^Departs\s+([A-Z]{3})[\s-]+#ms");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime());
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Arrives\s+([A-Z]{3})[\s-]+#ms");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(2));
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return node('(td[3]//strong[1])[1]');
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
}
