<?php

namespace AwardWallet\Engine\qmiles\Email;

class BookingPlainText extends \TAccountCheckerExtended
{
    public $reFrom = "#ebooking@qatarairways\.com\.qa#i";
    public $reProvider = "#qatarairways\.com\.qa#i";
    public $rePlain = "#Qatar\s+Airways\s+Booking\s+Confirmation\s+\(Booking\s+Reference#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Your\s+Qatar\s+Airways\s+Booking\s+-\s+Reference#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "qmiles/it-1737322.eml, qmiles/it-1737332.eml, qmiles/it-1737429.eml";
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
                        return re('#Booking\s+Reference\s*:\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#Passenger\s+\d+\s+Type\s*:\s+.*\s+Name\s*:\s+(.*)#', $text, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#TOTAL\s+PRICE\s*:?\s+(.*)#i');

                        if ($subj) {
                            return [
                                'TotalCharge' => cost($subj),
                                'Currency'    => currency($subj),
                            ];
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#Flight\s+\d+.*?Travel\s+Class\s*:\s+[^\n]+#s', $text, $m)) {
                            $segments = $m[0];
                            $this->durations = null;

                            if (preg_match_all('#Total\s+Trav.l\s+Time\s*:\s+(.*)#', $text, $m)) {
                                if (count($segments) == count($m[1])) {
                                    $this->durations = $m[1];
                                }
                            }

                            return $segments;
                        }
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (isset($this->segmentIndex)) {
                                $this->segmentIndex++;
                            } else {
                                $this->segmentIndex = 0;
                            }

                            if (preg_match('#Flight\s*:\s+(.*)\s+\w{2}\s+(\d+)#', $text, $m)) {
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
                            $dateStr = re('#Flight\s+\d+\s+\w+,\s+(\d+\s+\w+\s+\d+)#');

                            foreach (['Dep' => 'Departure', 'Arr' => 'Arrival'] as $key => $value) {
                                $regex = '#' . $value . '\s*:\s+(\d+:\d+)(?:\s+([\+\-]\d\s+day)\(s\))?\s+(.*)#';

                                if (preg_match($regex, $text, $m)) {
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[1]);

                                    if ($key == 'Arr' and $m[2]) {
                                        $res[$key . 'Date'] = strtotime($m[2], $res[$key . 'Date']);
                                    }
                                    $res[$key . 'Name'] = $m[3];
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re('#Aircraft\s*:\s+(.*)#');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Travel\s+Class\s*:\s+(.*)#');
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            if ($this->durations) {
                                return $this->durations[$this->segmentIndex];
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
