<?php

namespace AwardWallet\Engine\nwa\Email;

class ScheduleChange extends \TAccountCheckerExtended
{
    public $reFrom = "#schedule\.waitlist_chg@nwa\.com#i";
    public $reProvider = "#nwa\.com#i";
    public $rePlain = "#Your\s+trip\s+has\s+been\s+adjusted\s+to\s+the\s+itinerary\s+below\s+due\s+to\s+a\s+change\s+in\s+our\s+flight\s+schedules.\s+Please\s+contact\s+Northwest#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Northwest\s+Airlines\s+Schedule\s+Change#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "nwa/it-1735039.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->year = re('#Date:.*(\d{4})#');

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s+Number\s*:\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re('#Passenger\s+Names:\s+(.*)#')];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//tr[contains(., "Airline") and contains(., "Equip")]/following-sibling::tr[string-length(normalize-space(.)) > 1]';

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\d+)(\w)#', node('./td[6]'), $m)) {
                                return [
                                    'FlightNumber' => (int) $m[1],
                                    'BookingClass' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $subj = implode("\n", nodes('./td[3]//text()'));

                            if (preg_match('#Leave\s+(.*)\s+Arrive\s+(.*)\s+(.*)#', $subj, $m)) {
                                return [
                                    'DepName' => $m[1],
                                    'ArrName' => $m[2],
                                    'Meal'    => $m[3],
                                ];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#^\s*(\d+)(\w+?)\s*$#', node('./td[2]'), $matches)) {
                                $dateStr = $matches[1] . ' ' . $matches[2];
                                $subj = implode("\n", nodes('./td[4]//text()'));

                                if (preg_match_all('#(\d{1,2})(\d{2})([AP])#i', $subj, $matches, PREG_SET_ORDER)) {
                                    $res = null;

                                    foreach (['Dep' => 0, 'Arr' => 1] as $key => $index) {
                                        $m = $matches[$index];
                                        $s = $dateStr . ' ' . $this->year . ', ' . $m[1] . ':' . $m[2] . $m[3] . 'M';
                                        $res[$key . 'Date'] = strtotime($s);
                                    }

                                    return $res;
                                }
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[5]');
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node('./td[10]');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return node('./td[9]');
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
