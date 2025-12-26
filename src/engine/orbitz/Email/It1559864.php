<?php

namespace AwardWallet\Engine\orbitz\Email;

class It1559864 extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-1559860.eml, orbitz/it-1559864.eml, orbitz/it-2191436.eml";

    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?orbitz#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#orbitz#i";
    public $reProvider = "#orbitz#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = clear("#Previous Itinerary.+#ms", $text);

                    return splitter("#(\n\s*\w+,\s*\w+\s+\d+,\s+\d{4}\s+[^\n]*?\s+\#\s*\d+)#", $text);
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $airline = orval(re("#\n\s*\w+,\s*\w+\s+\d+,\s+\d{4}\s+([^\n]*?)\s+\#\s*\d+#"), 'absent');

                        return orval(
                            re("#\n\s*$airline\s+record\s+locator\s*:\s*([\w\d\-]+)#", $this->text()),
                            re_white('Orbitz record locator : ([\w-]+)', $this->text())
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = re_white('Passenger\(s\): (.+?) (?: Ticket type requested: | [^\n]*? record locator)', $this->text());

                        return preg_split('#\s*,\s*\n\s*#', trim($info));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*\w+,\s*\w+\s+\d+,\s+\d{4}\s+([^\n]*?)\s+\#\s*(\d+)#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#Departure[:\s]*\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $year = re("#\w+\s+\d+,\s*(\d{4})#");

                            $dep = strtotime(re("#Departure[:\s]*\([A-Z]{3}\):\s*(\w+\s+\d+),\s*(\d+:\d+\s*[APM]{2})#") . ' ' . $year . ', ' . re(2));
                            $arr = strtotime(re("#Arrival[:\s]*\([A-Z]{3}\):\s*(\w+\s+\d+),\s*(\d+:\d+\s*[APM]{2})#") . ' ' . $year . ', ' . re(2));

                            if ($dep > $arr) {
                                $arr = strtotime('+1 day', $arr);
                            }

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Arrival[:\s]*\(([A-Z]{3})\)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Class[:\s]+([^\n]+)#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#\n\s*Seat[:\s]*([^\n|]+)#"));
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    // return $it;
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
