<?php

namespace AwardWallet\Engine\reurope\Email;

class It1784938 extends \TAccountCheckerExtended
{
    public $reFrom = "#raileurope#i";
    public $reProvider = "#raileurope#i";
    public $rePlain = "#(?:rail product\(s\) through Rail Europe|complete the booking for one or more products in your cart)#i";
    public $rePlainRange = "5000";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Rail Europe Booking#";
    public $reHtml = "#Rail Europe Booking#i";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "reurope/it-15774248.eml, reurope/it-1653735.eml, reurope/it-1784938.eml, reurope/it-1827698.eml, reurope/it.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "TripNumber" => function ($text = '', $node = null, $it = null) {
                        return str_replace(' ', '', re("#booking\s*number[:]?\s*((\w )?[\w-]{5,})\s+#i"));
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $travelers = node('(//text()[starts-with(normalize-space(.), "Travelers:")]/ancestor::td[1])[1]/following::td[1]');
                        $travelers = preg_replace('#\s*[(].*?[)]\s*#', '', $travelers);
                        $travelers = explode(',', $travelers);

                        return array_map(function ($x) {
                            return trim($x);
                        }, $travelers);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Total', +2));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#booking\s*date[:]?\s*(\d+/\d+/\d+)#i");

                        if ($date) {
                            return totime(uberDateTime($date));
                        }
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//text()[contains(., 'From:')]/ancestor-or-self::table[1]");
                    },

                    "TripSegments" => [
                        "Type" => function ($text = '', $node = null, $it = null) {
                            return str_replace('#', ' ', cell('Departs:', +2) . cell('Departs:', +3));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return cell("From:", +1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(cell('Departs:', +1)));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return cell('To:', +1);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(cell('Arrives:', +1)));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return str_replace("_", "-", cell('Service:', +1));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $text = cell('Reserved in:', +1);
                            $coach = '';

                            if (preg_match("#coach\s*\#\s*(\d{1,3})\b#", $text, $m)) {
                                $coach = 'Coach ' . $m[1] . ', ';
                            }

                            if (preg_match_all('#seat\s*\#\s*([\dA-Z]{1,4})(?:\b|coach)#i', $text, $ms)) {
                                return array_map(function ($v) use ($coach) {return $coach . $v; }, $ms[1]);
                            }
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
}
