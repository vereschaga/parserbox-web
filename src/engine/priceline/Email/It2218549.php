<?php

namespace AwardWallet\Engine\priceline\Email;

class It2218549 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?priceline#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#priceline#i";
    public $reProvider = "#priceline#i";
    public $caseReference = "6701";
    public $isAggregator = "0";
    public $fnDateFormat = "";
    public $xPath = "";
    public $mailFiles = "priceline/it-2218549.eml";
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
                        return re("#Priceline Ticket Request number is\s*([A-Z\d-]+)#ix");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = array_filter(explode("\n", re("#\n\s*Name of traveler\(s\)\s+(.*?)\n\s*Departure#is")));
                        $res = [];

                        foreach ($names as &$name) {
                            $res[] = nice($name);
                        }

                        return $res;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell("Total Charges", +1, 0));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Price per adult", +1, 0));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Taxes and surcharges", +1, 0));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#We have (\w+) your#ix");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Outbound Flight') or contains(text(), 'Return Flight')]/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node("following-sibling::tr[contains(., 'Flight number')][1]/td[last()]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node("following-sibling::tr[contains(., 'Departing airport or city')][1]/td[last()]");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(clear("#,#", node("following-sibling::tr[contains(., 'Date & time')][1]/td[last()]"))));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node("following-sibling::tr[contains(., 'Arriving airport or city')][1]/td[last()]");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(clear("#,#", node("following-sibling::tr[contains(., 'Date & time')][2]/td[last()]"))));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node("following-sibling::tr[contains(., 'Class')][1]/td[last()]");
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
