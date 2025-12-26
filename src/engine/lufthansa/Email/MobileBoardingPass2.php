<?php

namespace AwardWallet\Engine\lufthansa\Email;

class MobileBoardingPass2 extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+travelling\s+with\s+Lufthansa#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#boardingpass@lufthansa.com#i";
    public $reProvider = "#lufthansa.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "lufthansa/it-2096851.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = preg_replace('#\n[\s>]+#i', "\n", $this->setDocument('plain'));

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Booking\s+reference:\s+(\S+)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Date:\s+(\w{2})(\d+)\s+(\w{3})-(\w{3})\s+(\d+)(\w+?)(\d+)\s+.*Seat:\s+(\d+:\d+)\s+\w+\s+(\d+\w)#i', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                    'DepCode'      => $m[3],
                                    'ArrCode'      => $m[4],
                                    'DepDate'      => strtotime($m[5] . ' ' . $m[6] . ' ' . (strlen($m[7]) == 2 ? '20' . $m[7] : $m[7]) . ', ' . $m[8]),
                                    'Seats'        => $m[9],
                                ];
                            }
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Status:\s+(Business|Economy)#i');
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
