<?php

namespace AwardWallet\Engine\kayak\Email;

class It1565159 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#booking on KAYAK#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#@kayak.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]kayak.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "28.08.2015, 15:12";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "kayak/it-1565159.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("plain");
                    $text = clear("#<[^>]+>#", $text);

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#confirmation number:\s*([\d\w\-]+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $r = splitter("#(\n\s*(?:Departure\s+|Return Flight\s+))#");
                        $out = [];
                        $date = "";
                        // construct segments with dates
                        foreach ($r as $flight) {
                            if (re("#(\n\s*(?:Departure[^\n]+|Return\s*Flight[^\n]+))#i", $flight)) {
                                $date = re(1);
                            }
                            $sub = splitter("#(\n\s*[^\n]+\d+\s+Take\-off:\s*\d+:\d+)#", $flight);

                            foreach ($sub as $item) {
                                $out[] = $date . "\n" . $item;
                            }
                        }

                        return $out;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $in = clear("#\n\s*(?:Departure|Return\s*Flight)\s+([^\n]+)#", $text);

                            return [
                                'AirlineName'  => re("#^\n\s*(.*?)\s+(\d+)#", $in),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $date = re("#\n\s*(?:Departure|Return\s*Flight)\s+([^\n]+)#");

                            if ($date) {
                                $date .= ', ' . $this->getEmailYear();
                            }
                            re("#\n\s*Take\-off:\s*(\d+:\d+[ap]+m*)\s+\b([A-Z]{3})\b:\s#");

                            $depTime = $date . ', ' . re(1);
                            $depCode = re(2);

                            re("#\n\s*Landing:\s*(\d+:\d+[ap]+m*)\s+\b([A-Z]{3})\b:\s#");

                            $arrTime = $date . ', ' . re(1);
                            $arrCode = re(2);

                            return [
                                'DepCode' => $depCode,
                                'DepDate' => totime($depTime),
                                'ArrCode' => $arrCode,
                                'ArrDate' => totime($arrTime),
                            ];
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#\s*\|\s*([^\n]+)#"));
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
