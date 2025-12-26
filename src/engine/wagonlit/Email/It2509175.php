<?php

namespace AwardWallet\Engine\wagonlit\Email;

class It2509175 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?contactcwt#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#contactcwt#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#contactcwt#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "28.02.2015, 05:46";
    public $crDate = "28.02.2015, 05:11";
    public $xPath = "";
    public $mailFiles = "wagonlit/it-2509175.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("//*[normalize-space(text()) = 'Flight']/ancestor-or-self::td[following-sibling::td[1][normalize-space(.)=':']]/ancestor::table[1]");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return cell("Airline Booking Reference", +2);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Lead Traveller\s*:\s*([^\n]+)#", $this->text());
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total\s*:\s*[^\d]+([\d.,]+)#", $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#\n\s*Taxes and Levies\s*:\s*([^\d]+)[\d.,]+#", $this->text()));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes and Levies\s*:\s*([^\n]+)#", $this->text()));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Subject:\s*(\w+) Confirmation#x", $this->text()),
                            re("#^(\w+) Confirmation#x", $this->parser->getSubject())
                        );
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\[([A-Z\d]{2})\]\s*(\d+)#", cell("Flight", +2)),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#[^\n]+#", cell("Depart", +2));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDateTime(cell("Departure Date", +2));

                            if (!totime($date)) {
                                $date = clear("#[APM]+$#", $date);
                            }

                            return totime($date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#[^\n]+#", cell("Arriving", +2));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDateTime(cell("Arrival Date", +2));

                            if (!totime($date)) {
                                $date = clear("#[APM]+$#", $date);
                            }

                            return totime($date);
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return cell("Aircraft", +2);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return cell("Cabin Class", +2);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return cell("Booking Class", +2);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return node("following::table[1][contains(., 'Duration')]", null, true, "#Duration\s*:\s*(\d+:\d+)#");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return cell("Stops", +2);
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
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
