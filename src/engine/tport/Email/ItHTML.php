<?php

namespace AwardWallet\Engine\tport\Email;

class ItHTML extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#2013 Travelport. All rights reserved#i', 'us', '/4'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = "";
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "14.04.2015, 20:35";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "tport/it-1586553.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->year = re('#\d{4}#i', $this->parser->getHeader('date'));

                    $userEmail = strtolower(re("#\n\s*To\s*:[^\n]*?([^\s@\n]+@[\w\-.]+)#"));

                    if (!$userEmail) {
                        $userEmail = niceName(re("#\n\s*To\s*:\s*([^\n]+)#"));
                    }

                    if (!$userEmail) {
                        $userEmail = strtolower(re("#([a-zA-Z0-9_.+-]+@[a-zA-Z0-9-.]+)#", $this->parser->getHeader("To")));
                    }

                    if (!$userEmail) {
                        $userEmail = strtolower($this->parser->getHeader("To"));
                    }

                    if ($userEmail) {
                        $this->parsedValue('userEmail', $userEmail);
                    }

                    $this->passengers = nodes("//text()[contains(., 'Traveler')]/ancestor::table[1]/following-sibling::table[1]//tr");

                    return $this->http->XPath->query("//img[contains(@src, 'wlair3.gif')]/ancestor::table[3]");
                },

                ".//text()[contains(., 'Flight')]" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return node(".//text()[contains(., 'Confirmation Number')]/ancestor::td[1]/following-sibling::td[1]");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\d+#", node(".//text()[contains(., 'Flight Number')]/ancestor::td[1]/following-sibling::td[1]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $s = node(".//text()[contains(., 'Depart')]/ancestor::td[1]/following-sibling::td[1]");
                            $t = node(".//text()[contains(., 'Depart')]/ancestor::tr[1]/following-sibling::tr[2]/td[2]");

                            if ($t) {
                                $s .= " ($t)";
                            }

                            return $s;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            if (!$this->year) {
                                return null;
                            }
                            $date = node(".//text()[contains(., 'Depart')]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]");
                            $date = re('#,\s(.*)#', $date);
                            $date .= ', ' . $this->year;
                            $time = node(".//text()[contains(., 'Depart')]/ancestor::tr[1]/td[last()]");

                            return strtotime("$date, $time");
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $s = node(".//text()[contains(., 'Arrive')]/ancestor::td[1]/following-sibling::td[1]");
                            $t = node(".//text()[contains(., 'Arrive')]/ancestor::tr[1]/following-sibling::tr[2]/td[2]");

                            if ($t) {
                                $s .= " ($t)";
                            }

                            return $s;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            if (!$this->year) {
                                return null;
                            }
                            $date = node(".//text()[contains(., 'Arrive')]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]");
                            $date = re('#,\s(.*)#', $date);
                            $date .= ', ' . $this->year;
                            $time = node(".//text()[contains(., 'Arrive')]/ancestor::tr[1]/td[last()]");

                            return strtotime("$date, $time");
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#Flight\s+-\s+(.*)#", node("./tbody/tr[1]//text()[contains(., 'Flight')]/ancestor::td[1]"));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node(".//text()[contains(., 'Aircraft')]/ancestor::td[1]/following-sibling::td[1]");
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return (float) node(".//text()[contains(., 'Mileage')]/ancestor::td[1]/following-sibling::td[1]");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node(".//text()[contains(., 'Class')]/ancestor::td[1]/following-sibling::td[1]");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return node(".//text()[contains(., 'Seat')]/ancestor::td[1]/following-sibling::td[1]");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return node(".//text()[contains(., 'Travel Time')]/ancestor::td[1]/following-sibling::td[1]");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return node(".//text()[contains(., 'Meal')]/ancestor::td[1]/following-sibling::td[1]");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return (int) node(".//text()[contains(., 'Stopovers')]/ancestor::td[1]/following-sibling::td[1]");
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    // This postprocessing joins air trip reservations with same record locator,
                    // keeping all other itineraries unchanged
                    $itMod = [];

                    foreach ($it as $i) {
                        if (isset($i['Kind']) && $i['Kind'] == 'T') {
                            // If current reservation is Air Trip
                            if (empty($itMod)) {
                                // If this is first reservation - we copy 'as is', because there is
                                // nothing to compare it with
                                $itMod[] = $i;
                            } else {
                                // Else if we've already processed some records, test whether record
                                // with same record locator already exist
                                $targetIndex = null;
                                $index = 0;

                                foreach ($itMod as $im) {
                                    if ($im['Kind'] == 'T' && $i['RecordLocator'] == $im['RecordLocator']) {
                                        $targetIndex = $index;

                                        break;
                                    }
                                    $index++;
                                }

                                if ($targetIndex !== null) {
                                    // If $targetIndex (record with same record locator as $i) was found
                                    // copy all segments from $i to it
                                    foreach ($i['TripSegments'] as $ts) {
                                        $itMod[$targetIndex]['TripSegments'][] = $ts;
                                    }
                                } else {
                                    // Else there is no previous reservations with same record locator
                                    // and we copy it 'as is'
                                    $itMod[] = $i;
                                }
                            }
                        } else {
                            // Else current reservation is not Air Trip - copy it without changes
                            $itMod[] = $i;
                        }
                    }

                    return $itMod;
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
