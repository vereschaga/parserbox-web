<?php

namespace AwardWallet\Engine\tcase\Email;

class ItPlainText extends \TAccountCheckerExtended
{
    public $reFrom = "#no-reply@tripcase\.com#i";
    public $reProvider = "#tripcase\.com#i";
    public $rePlain = "#Access your trip details on www\.tripcase\.com#i";
    public $reHtml = "#Access your trip details on www\.tripcase\.com#i";
    public $typesCount = "2";
    public $langSupported = "en";
    public $reSubject = ""; //STEVEN MERCHANT, Flight to DALLAS FT WORTH, TX - FTRYUI
    public $xPath = "";
    public $mailFiles = "tcase/it-31083182.eml";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#Car[ ]*Rental#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#Reservation Code: (\S+)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $s = re("#Pick Up Time: (.*)Drop Off Time#s");

                        if (preg_match('#[a-z]+, ([0-9]+) ([a-z]+) ([0-9]+)\s+([0-9]+:[0-9]+)([a-z]{2})#is', $s, $m)) {
                            $day = $m[1];
                            $month = $m[2];
                            $year = $m[3];
                            $time = $m[4];
                            $ampm = $m[5];

                            return strtotime("$day $month $year $time $ampm");
                        } else {
                            return null;
                        }
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $s = re("#Drop Off Time: (.*)Confirmation#s");

                        if (preg_match('#[a-z]+, ([0-9]+) ([a-z]+) ([0-9]+)\s+([0-9]+:[0-9]+)([a-z]{2})#is', $s, $m)) {
                            $day = $m[1];
                            $month = $m[2];
                            $year = $m[3];
                            $time = $m[4];
                            $ampm = $m[5];

                            return strtotime("$day $month $year $time $ampm");
                        } else {
                            return null;
                        }
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#Pick Up Location: (.*)#");
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#Drop Off Location: (.*)#");
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#Car Rental: (.*)#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#Car Type: (.*)#");
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#Car Agency Phone: (.*)#");
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#Car Agency Phone: (.*)#");
                    },

                    "DropoffFax" => function ($text = '', $node = null, $it = null) {
                        return re("#Car Agency Fax: (.*)#");
                    },

                    "PickupFax" => function ($text = '', $node = null, $it = null) {
                        return re("#Car Agency Fax: (.*)#");
                    },
                ],
                "#Flight:#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $rl = re("#Airline Reservation Code:\s*([A-Z\d]{5,7})\b#");

                        if (empty($rl)) {
                            $rl = re("#Reservation Code:\s*([A-Z\d]{5,7})\b#");
                        }

                        return $rl;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        if (preg_match("#(?:^|:\s*)([A-Z ]{5,}), Flight to #", $this->parser->getSubject(), $m)) {
                            return [$m[1]];
                        }

                        return [];
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        return array_filter([re("#Ticket Number:\s*(\d{5,})\b#")]);
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return array_map('trim', explode(",", re("#Loyalty Number:\s*([\d,A-Z ]+)\s*\n#")));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return ["AirlineName" => re("#\s+Flight:\s*(?:.*?,)?[ ]*([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(\d{1,5})#"), "FlightNumber" => re(2)];
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return ["DepName" => re("#\n\s*From:\s*(.+?)[ ]*\(([A-Z]{3})\)#"), "DepCode" => re(2)];
                        },

                        "DepartureTerminal" => function ($text = '', $node = null, $it = null) {
                            $dep = re("#\n\s*Departing Terminal:\s*(.+)#");

                            if (preg_match("#Not\s*Available#si", $dep)) {
                                return null;
                            }

                            return $dep;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(re("#\n\s*Departs:\s*(.+)#"));
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return ["ArrName" => re("#\n\s*To:\s*(.+?)[ ]*\(([A-Z]{3})\)#"), "ArrCode" => re(2)];
                        },

                        "ArrivalTerminal" => function ($text = '', $node = null, $it = null) {
                            $arr = re("#\n\s*Arrival Terminal:\s*(.+)#");

                            if (preg_match("#Not\s*Available#si", $arr)) {
                                return null;
                            }

                            return $arr;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(re("#\n\s*Arrives:\s*(.+)#"));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Aircraft:\s*(.+)#");
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Distance \(in Miles\):\s*(\d+)\s+#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Class:\s*(.+)\s+#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return array_filter([re("#\n\s*Seat\(s\):\s*(\d{1,3}[A-Z])\s+#")]);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Duration:\s*(\d.+)\s+#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Meals:\s*(.+)#");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Stop\(s\):\s*(\d+)\s+#");
                        },

                        "Smoking" => function ($text = '', $node = null, $it = null) {
                            if (re("#\n\s*Smoking:\s*No\s+#")) {
                                return false;
                            }

                            return null;
                        },
                    ],
                ],

                //				"PostProcessing" => function($text = '', $node = null, $it = null){
                // This postprocessing joins air trip reservations with same record locator,
                // keeping all other itineraries unchanged
                //					$itMod = [];
                //					foreach ($it as $i) {
                //						if (isset($i['Kind']) && $i['Kind'] == 'T') {
                //							// If current reservation is Air Trip
                //							if (empty($itMod)) {
                //								// If this is first reservation - we copy 'as is', because there is
                //								// nothing to compare it with
                //								$itMod[] = $i;
                //							} else {
                //								// Else if we've already processed some records, test whether record
                //								// with same record locator already exist
                //								$targetIndex = null;
                //								$index = 0;
                //								foreach ($itMod as $im) {
                //									if ($im['Kind'] == 'T' && $i['RecordLocator'] == $im['RecordLocator']) {
                //										$targetIndex = $index;
                //										break;
                //									}
                //									$index++;
                //								}
                //								if ($targetIndex !== null) {
                //									// If $targetIndex (record with same record locator as $i) was found
                //									// copy all segments from $i to it
                //									foreach ($i['TripSegments'] as $ts)
                //										$itMod[$targetIndex]['TripSegments'][] = $ts;
                //								} else {
                //									// Else there is no previous reservations with same record locator
                //									// and we copy it 'as is'
                //									$itMod[] = $i;
                //								}
                //							}
                //						} else {
                //							// Else current reservation is not Air Trip - copy it without changes
                //							$itMod[] = $i;
                //						}
                //					}
                //					return $itMod;
                //				},
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
