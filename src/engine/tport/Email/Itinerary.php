<?php

namespace AwardWallet\Engine\tport\Email;

class Itinerary extends \TAccountCheckerExtended
{
    public $rePlain = "#From:.*youritinerary@worldspan.com|BLIK TRAVEL WISHES YOU A NICE TRIP|MYTRIPANDMORE.COM/BAGGAGEDETAIL.*.BAGG#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@figame[.]gr#i";
    public $reProvider = "#[@.]figame[.]gr#i";
    public $caseReference = "7863";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "tport/it-1984542.eml, tport/it-2052958.eml, tport/it-2053064.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->passengers = nodes("//*[contains(text(), 'Passenger Name')]/ancestor-or-self::tr[1]/following-sibling::tr/td[2]");

                    if (!$this->passengers) {
                        $this->passengers = [re('#Trip\s+Locator:\s+\S+\s+(.*)\s+\w+\s+\d+\s+\w+\s+\d{4}#i')];
                    }

                    if (!$this->passengers) {
                        $this->passengers = null;
                    }

                    $subj = nodes("//*[contains(text(), 'Passenger Name')]/ancestor-or-self::tr[1]/following-sibling::tr//td[not(.//td)][last() - 1]");

                    if (sizeof($subj) === 1) {
                        $this->total = cost(re('#(\d+[.]\d+)#', $subj[0]));
                    } else {
                        $this->total = null;
                    }

                    $subj = nodes("//*[contains(text(), 'Passenger Name')]/ancestor-or-self::tr[1]/following-sibling::tr//td[not(.//td)][last()]");

                    if (sizeof($subj) === 1) {
                        $this->currency = currency($subj[0]);
                    } else {
                        $this->currency = null;
                    }

                    return xpath('//*[contains(text(), "Depart:")]/ancestor::table[1]');
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Airline\s+Ref\s*:\s+([\w\-]+)#i');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        // it status via segment if only one segment
                        $st = nodes("//*[contains(text(), 'Status:')]/following::td[1]");

                        if (sizeof($st) == 1) {
                            return nice($st[0]);
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re_white('Flight (\w+ \d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $x = cell('Depart:', +1);

                            return nice($x);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node('.//tr[1]/td[1]');

                            $time1 = node(".//*[contains(text(), 'Depart:')]/ancestor::tr[1]/following-sibling::tr[2]/td[2]");
                            $a1 = re_white('αμ', $time1) ? 'am' : '';
                            $a1 = re_white('μμ', $time1) ? 'pm' : '';
                            $time1 = re_white('(\d+:\d+)', $time1);

                            $time2 = node(".//*[contains(text(), 'Arrive:')]/ancestor::tr[1]/following-sibling::tr[2]/td[2]");
                            $a2 = re_white('αμ', $time2) ? 'am' : '';
                            $a2 = re_white('μμ', $time2) ? 'pm' : '';
                            $time2 = re_white('(\d+:\d+)', $time2);

                            $dt1 = "$date $time1 $a1";
                            $dt2 = "$date $time2 $a2";
                            $dt1 = uberDateTime($dt1);
                            $dt2 = uberDateTime($dt2);

                            $dt1 = strtotime($dt1);
                            $dt2 = strtotime($dt2);

                            if ($dt2 < $dt1) {
                                $dt2 = strtotime('+1 day', $dt2);
                            }

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $x = cell('Arrive:', +1);

                            return nice($x);
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $x = cell('Aircraft:', +1);

                            return nice($x);
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            $x = cell('Mileage:', +1);

                            return nice($x);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $cls = cell('Class:', +1);

                            return re_white('-(.+)', $cls);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            $cls = cell('Class:', +1);

                            return re_white('(.+)-', $cls);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $x = cell('Seat:', +1);

                            return nice($x);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $x = cell('Travel Time:', +1);

                            return nice($x);
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            $x = cell('Meal:', +1);

                            return nice($x);
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $x = cell('Stopovers:', +1);

                            return nice($x);
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $itNew = uniteAirSegments($it);

                    if (count($itNew) == 1) {
                        $itNew[0]['TotalCharge'] = $this->total;
                        $itNew[0]['Currency'] = $this->currency;
                    }

                    return $itNew;
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
