<?php

namespace AwardWallet\Engine\qmiles\Email;

class It2943177 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#YOUR\s+FLIGHT\s+DETAILS.+?qatarairways\.com#is', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Your flight confirmation for', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]qatarairways\.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]qatarairways\.com#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "01.08.2015, 21:57";
    public $crDate = "01.08.2015, 13:33";
    public $xPath = "";
    public $mailFiles = "qmiles/it-2943177.eml, qmiles/it-2943184.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->seatsByFlight = [];
                    $xSeats = xpath("//tr[not(.//tr) and normalize-space(.)='Seat Preference:']/following-sibling::tr[1]//tr[position() mod 2 = 1]");

                    for ($i = 0; $i < $xSeats->length; $i++) {
                        foreach (nodes("./th|td", $xSeats->item($i)) as $fl) {
                            if (preg_match("#Flight\s+([A-Z]+)\s*(\d+)#i", $fl, $m1)
                              && preg_match_all("#\b(\d+[A-Z]+)\b#", node("./following-sibling::tr[1]/td[" . ($i + 1) . "]", $xSeats->item($i)), $m2)) {
                                $key = $m1[1] . $m1[1];

                                if (!isset($this->seatsByFlight[$key])) {
                                    $this->seatsByFlight[$key] = [];
                                }
                                $this->seatsByFlight[$key] = array_merge($this->seatsByFlight[$key], $m2[1]);
                            }
                        }
                    }

                    $this->flightsLocators = [];

                    if (re("#Your\s+airline\s+confirmation\s+number\s*:\s*([\w-]+)\s+\-\s+([A-Z]+)#si")) {
                        $this->flightsLocators[re(2)] = re(1);
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#(?>trip|confirmation)\s+number\s*:\s*([\w-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $pr = re("#PASSENGER\s+INFORMATION(.+?)Seat\s+Preference:#is");

                        if (preg_match_all("#\n\s*([^\n]+)\s+(?>\d+\D\d+\D\d+\s+\w+)#", $pr, $m)) {
                            return $m[1];
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#CHARGES\s+SUMMARY\s.+?Total(?>\s*due\s+at\s+booking)\s*([^\n]+?)\s*(?:\(|\n)#si"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        if (re("#Number\s+of\s+Tickets\s+1#")) {
                            return cost(re("#Base Ticket Price\s+([^\n]+?)\s*(?:per|\n)#"));
                        }
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#Points Earned\s+(\d[\d,.]*)#i");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $itsRaw = re("#\n\s*YOUR FLIGHT DETAILS\b(.+?)(?>CHARGES\s+SUMMARY|PASSENGER\s+INFORMATION)#si");

                        return splitter("#(\n\s*[^\n]+?\(\w+\)\s+\-\s+\d+\s*\n)#", $itsRaw);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $data = [];

                            if (re("#\(([A-Z]+)\)\s*\-\s*(\d+)#")) {
                                $data['AirlineName'] = re(1);

                                if (isset($this->flightsLocators[re(1)])) {
                                    $data['FlightLocator'] = $this->flightsLocators[re(1)];
                                }
                                $data['FlightNumber'] = re(2);

                                return $data;
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $data = [];

                            if (re("#\n\s*Take\s+off\s+\w+,\s*(\d+\D\d+\D\d+,[^\n]*?)\s*\n\s*(?>\d+\s+day\s+later\s+)?[^\n]+?\s+\(([A-Z]+)\)\s+\-"
                                  . ".*?\n\s*Landing\s+\w+,\s*(\d+\D\d+\D\d+,[^\n]*?)\s*\n\s*(?>\d+\s+day\s+later\s+)?[^\n]+?\s+\(([A-Z]+)\)\s+\-#s")) {
                                $data['DepCode'] = re(2);
                                $data['ArrCode'] = re(4);
                                $data['DepDate'] = timestamp_from_format(re(1), "m/d/Y, H:ia");
                                $data['ArrDate'] = timestamp_from_format(re(3), "m/d/Y, H:ia");

                                return $data;
                            }
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\([A-Z]+\)\s+\-\s+\d+\s*\n\s*[^\n]+?\s+Class,\s+([^\n]+?)\s*\n\s*\d+h#i");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#\([A-Z]+\)\s+\-\s+\d+\s*\n\s*([^\n]+?)\s+Class,#i");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            if (isset($this->seatsByFlight[$it['AirlineName'] . $it['FlightNumber']])) {
                                return implode(", ", $this->seatsByFlight[$it['AirlineName'] . $it['FlightNumber']]);
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#(\d+h[^\n]*?)\s+Take\s+off\s+#");
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
