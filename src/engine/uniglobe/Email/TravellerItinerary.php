<?php

namespace AwardWallet\Engine\uniglobe\Email;

class TravellerItinerary extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\r\n]*?uniglobe#i";
    public $rePlainRange = "";
    public $reHtml = "#http://www\.uniglobeltw\.com#i";
    public $reHtmlRange = "/1";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#uniglobe#i";
    // var $reProvider = "#uniglobe#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "uniglobe/it-2050317.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (stripos($text, "AMADEUS.COM") !== false) {
                        return null;
                    }

                    return xpath('//text()[contains(., "Departure:")]/ancestor::tr[2][contains(., "Arrival:")]');
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Airline\s+Booking\s+Reference:\s+(\w[\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return node('.//tr[contains(., "Passenger Name")]/following-sibling::tr[1]//tr/td[1]');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\w{2})(\d+)#i', cell('Airline/Flight Number', +1), $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 'Departure', 'Arr' => 'Arrival'] as $key => $value) {
                                $date = cell($value . ' Date:', +1);
                                $time = cell($value . ':', +1);

                                if ($date and $time) {
                                    $res[$key . 'Date'] = strtotime($date . ', ' . $time);
                                }
                                $res[$key . 'Name'] = nice(cell($value . ':', +2));
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return cell('Aircraft:', +1);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\w)\s+/\s+(.*)#i', cell('Cabin:', +1), $m)) {
                                return [
                                    'BookingClass' => $m[1],
                                    'Cabin'        => $m[2],
                                ];
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return cell('Duration:', +1);
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return node('.//tr[contains(., "Meal Info")]/following-sibling::tr[1]//tr/td[last() - 1]');
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
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
}
