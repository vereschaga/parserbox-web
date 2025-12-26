<?php

namespace AwardWallet\Engine\viarail\Email;

class It1704903 extends \TAccountCheckerExtended
{
    public $reFrom = "#viarail#i";
    public $reProvider = "#viarail#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?viarail#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "#viarail[.]ca/images#";
    public $reHtmlRange = "4000";
    public $xPath = "";
    public $mailFiles = "viarail/it-1704903.eml, viarail/it-1704904.eml, viarail/it-1704906.eml, viarail/it-1704908.eml";
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
                        return re("#Booking confirmation\s*:\s*([\w-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = re('/Name\s*:\s*(.+)\s{3,}/i');

                        return nice($name);
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Booking confirmation')]/ancestor-or-self::tr[1]/preceding-sibling::tr[1]");
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return cell('From', +1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dt = cell('Departure', +1);
                            $dt = preg_replace('/pm|am/i', '', $dt);

                            return totime(uberDateTime($dt));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return cell('To', +1);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $dt = cell('Arrival', +1);
                            $dt = preg_replace('/pm|am/i', '', $dt);

                            return totime(uberDateTime($dt));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return cell('Class', +1);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return cell('Seat', +1);
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
