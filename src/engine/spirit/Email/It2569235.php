<?php

namespace AwardWallet\Engine\spirit\Email;

class It2569235 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?spirit#i', 'blank', ''],
        ['#Spirit\s+Airlines[,\s]+Inc#i', 'blank', '-500'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]spirit[\-.]#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]spirit[\-.]#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "19.03.2015, 16:42";
    public $crDate = "19.03.2015, 16:22";
    public $xPath = "";
    public $mailFiles = "spirit/it-2569235.eml";
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
                        return re("#\s+([A-Z\d-]{4,})\s+YOUR CONFIRMATION CODE#xi");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(normalize-space(text()), 'Customer Information')]/following::table[1]//tr[position()>1 and string-length(normalize-space(.))>5]/td[1]");
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return array_filter(nodes("//*[contains(normalize-space(text()), 'Customer Information')]/following::table[1]//tr[position()>1 and string-length(normalize-space(.))>5]/td[2]"));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $cost = text(xpath("//*[contains(normalize-space(text()), 'TOTAL')]/ancestor::tr[1]/td[last()]"));

                        return total(preg_replace_callback("#(\d+)\s+(\d+)#", function ($m) {
                            return $m[1] . '.' . $m[2];
                        }, $cost));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $cost = text(xpath("//*[contains(normalize-space(text()), 'FLIGHT PRICE')]/ancestor::tr[1]/td[last()]"));

                        return cost(preg_replace_callback("#(\d+)\s+(\d+)#", function ($m) {
                            return $m[1] . '.' . $m[2];
                        }, $cost));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*STATUS\s+([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*BOOKING\s+DATE\s+([^\n]+)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Departing:')]/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Flight\s*:\s*(\d+)#");
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            if (!empty(node("//text()[contains(normalize-space(), 'Spirit Airlines, Inc')]"))) {
                                return 'NK';
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDate(node('td[2]')) . ',' . uberTime(node('td[4]')));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDate(node('td[2]')) . ',' . uberTime(node('td[4]'), 2));
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Miles\s*:\s*([.,\d]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*(\d+\s*h\s*\d+\s*m)#");
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
