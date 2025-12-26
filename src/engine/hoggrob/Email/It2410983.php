<?php

namespace AwardWallet\Engine\hoggrob\Email;

class It2410983 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From[*\s]*:[^\n]*?hrgworldwide#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#hrgworldwide#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#hrgworldwide#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "05.02.2015, 23:30";
    public $crDate = "03.02.2015, 17:04";
    public $xPath = "";
    public $mailFiles = "hoggrob/it-2410983.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // use pdf if it's attached (otherwise html/text)
                    if ($this->getDocument("application/pdf", "text")) {
                        $text = $this->setDocument("application/pdf", "text");
                    } else {
                        return null;
                    }

                    return [$text];
                },

                "#Train#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Booking reference\s*:\s*([A-Z\d\-]+)#ix");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Traveller\s*:\s*([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Total\s*amount\s*:\s*([^\n]+)#i"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Fare\s*accepted\s*:\s*([^\n]+)#i"));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $costs = explode(" ", clear("#[^\d.]#", re("#\n\s*Taxes and fees\s*:\s*([^\n]+)#ix")));
                        $tax = 0;

                        foreach ($costs as $cost) {
                            $tax += cost($cost);
                        }

                        return $tax;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\s+(\d+\s+\w+\s+\d{4})\s+THANK YOU FOR BOOKING#ix"));
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $text = clear('#\n\s*GENERAL\s+INFORMATION.+$#s', $text);

                        return splitter("#\n\s*([^\n]+\n\s*Train\s*\#\s*\d+)#", $text);
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            //return re("#\n\s*Departing.*?\(([A-Z]{3})\)\n\s*Date#s");
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            //return nice(re("#\n\s*Departing[:\s]+([^\(]+)#s"));
                            return nice(re("#\n\s*Departing[:\s]+([^\n]+)#"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(ure("#\n\s*Date/time\s*:\s*(\d+\s+\w+\s+\d+,\s*\d+:\d+)#i"));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            //return re("#\n\s*Arriving.*?\(([A-Z]{3})\)\n\s*Date#s");
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            //return nice(re("#\n\s*Arriving[:\s]+([^\(]+)#s"));
                            return nice(re("#\n\s*Arriving[:\s]+([^\n]+)#"));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(ure("#\n\s*Date/time\s*:\s*(\d+\s+\w+\s+\d+,\s*\d+:\d+)#i", 2));
                        },

                        "Type" => function ($text = '', $node = null, $it = null) {
                            return nice(clear("/#/", re("#^(.*?\n\s*Train\s*\#\s*\d+)#s")));
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
