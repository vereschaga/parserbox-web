<?php

namespace AwardWallet\Engine\hoggrob\Email;

class Hrg1882895 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?hrgworldwide.com#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = [
        ['#hrgworldwide#i', 'us', '2000'],
    ];
    public $reSubject = "";
    public $reFrom = [
        ['#@hrgworldwide.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#hrgworldwide#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "20.03.2015, 17:00";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "hoggrob/hrg-1882895.eml, hoggrob/it-2579390.eml, hoggrob/it-2579391.eml, hoggrob/it-5504991.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$this->setDocument("application/pdf", "text")];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Booking ref\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*Passenger\s*:\s*([^\n]*?)\s{2,}#")];
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Frequent Flyer\s*:\s*([^\n;]*?)\s{2,}Quantifier#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total\s*:\s*([A-Z]{3}\s+[\d.,]+)#"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Fare\s*:\s*([A-Z]{3}\s+[\d.,]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $r = filter(explode(" ", clear("#[^\d.]#", re("#\n\s*Taxes,\s*(.*?)\n\s*Total\s*:#ims"), ' ')));
                        $total = 0;

                        foreach ($r as $cost) {
                            $total += $cost;
                        }

                        return $total;
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#/#", re("#\n\s*Date of issue\s*:\s*([^\n]+)#"), '-'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*([A-Z\d]{2}\s*\d+\s+[A-Z]\s+\d+[\/\.]\d+\s+\d+:\d+\s+[A-Z]{3})#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#^[A-Z\d]{2}\s*\d+#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+([A-Z]{3})\s+#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(re("#\s+(\d+)[\/\.](\d+)\s+(\d+:\d+)#", $text, 2) . '/' . re(1) . ',' . re(3), strtotime($this->parser->getHeader('date')));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\d+:\d+\s+([A-Z]{3})\s+#", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(re("#\s+(\d+)[\/\.](\d+)[\/\.](\d+)\s+(\d+:\d+)#", $text, 2) . '/' . re(1) . '/' . re(3) . ',' . re(4), strtotime($this->parser->getHeader('date')));
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re("#^[A-Z\d]{2}\d+\s+([A-Z])\s+#");
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
