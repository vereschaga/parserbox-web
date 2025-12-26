<?php

namespace AwardWallet\Engine\israel\Email;

class BoardingPass extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $rePlainRange = "";
    public $reHtml = "#FLIGHT:.*?www.elal.co.il#is";
    public $reHtmlRange = "/1";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#system@elal\.co\.il#i";
    public $reProvider = "#elal\.co\.il#i";
    public $xPath = "";
    public $mailFiles = "israel/it-1894984.eml";
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
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return [beautifulName(re('#PASSENGER\s+NAME:\s+(.*)#i'))];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#FLIGHT:\s+(\w{2})\s+(\d+)#i', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#FROM:\s+(\w{3})\s+TO\s+(\w{3})#i', $text, $m)) {
                                return [
                                    'DepCode' => $m[1],
                                    'ArrCode' => $m[2],
                                ];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#DATE:\s+(\d+)/(\d+)/(\d+)\s+(\d+:\d+\s*(?:am|pm)?)#i', $text, $m)) {
                                $s = $m[1] . '.' . $m[2] . '.' . (strlen($m[3]) == 2 ? '20' . $m[3] : $m[3]) . ', ' . $m[4];

                                return strtotime($s);
                            }
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re('#CLASS:\s+(\w)\s+#');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $x = '(//tr[.//img[contains(@src, "checkInWeb/img/bpImages/headers.jpg")] and not(.//tr)])[1]//following-sibling::tr[1]//tr[count(./td) = 3]/td[1]';

                            return re('#^\d+\w$#i', node($x));
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
