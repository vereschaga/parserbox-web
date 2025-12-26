<?php

namespace AwardWallet\Engine\transaero\Email;

class It3023031 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\btransaero\b.+?Itinerary\b.+?Flight\b.+?Departure\b.+?Arrival\b.+?Cabin\b#si', 'blank', '20000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['квитанция/Itinerary receipt', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]transaero#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]transaero#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "31.08.2015, 19:34";
    public $crDate = "31.08.2015, 15:45";
    public $xPath = "";
    public $mailFiles = "transaero/it-3023031.eml";
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
                        return re("#Reservation\s+number\s+([\w-]+)#iu");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nice(clear("#\/#", nodes("//tr[contains(normalize-space(./td[2]), 'Passengers')]/following-sibling::tr[count(./td) > 5]/td[2]")));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $field = xpath("//td[not(.//td) and contains(., 'Total')]/../following-sibling::tr[count(./td) > 3]");
                        $data = [];

                        if ($field->length == 1) {
                            $data = array_merge($data, total(node("td[4]", $field->item(0))));
                        } else {
                            $data = array_merge($data, total(re("#Total\s+price\s+for\s+all\s+passengers\s*:\s*([^\n]+)#i")));
                        }

                        return $data;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $its = xpath("//*[normalize-space(text())='Itinerary']/ancestor::tr[1]/following-sibling::tr[count(./td) > 4]");

                        return $its;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\b(\d+)\b#", node("td[2]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $data['DepName'] = re("#\n\s*([^\n]+?)\s+\-\s+([^\n]+?)\s*$#", text(xpath("td[1]")));
                            $data['ArrName'] = re(2);

                            return $data;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(node("td[3]"));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(node("td[4]"));
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#(\w+)\s+\d+\b#", node("td[2]"));
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re("#\b[A-Z]\b#", node("td[5]"));
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
