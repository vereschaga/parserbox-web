<?php

namespace AwardWallet\Engine\spirit\Email;

class It2783888 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Spirit Airlines#i', 'blank', '-2000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]spiritairlines[.]#i', 'blank', ''],
    ];
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "10.06.2015, 14:32";
    public $crDate = "10.06.2015, 14:24";
    public $xPath = "";
    public $mailFiles = "spirit/it-2783888.eml, spirit/it-2788867.eml";
    public $re_catcher = "#.*?#";
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
                        return reni('Confirmation Code: (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//*[contains(text(), 'Customer Name')]/following::font[1]");

                        return nice($ppl);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return FLIGHT_NUMBER_UNKNOWN;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            if (!empty(node("//text()[contains(normalize-space(), 'upcoming travel on Spirit Airlines')]"))) {
                                return 'NK';
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $info = cell('Departure City:', +1);

                            return reni('\( (\w{3}) \)', $info);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = cell('Flight Date:', +1);
                            $date = uberDate($date);

                            $time1 = uberTime(cell('Departure Time:', +1));
                            $time2 = uberTime(cell('Arrival Time:', +1));

                            $dt = strtotime($date);
                            $dt1 = strtotime($time1, $dt);
                            $dt2 = strtotime($time2, $dt);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $info = cell('Arrival City:', +1);

                            return reni('\( (\w{3}) \)', $info);
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
