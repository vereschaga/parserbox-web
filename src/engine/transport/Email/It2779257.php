<?php

namespace AwardWallet\Engine\transport\Email;

class It2779257 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]tandt[.]#i', 'blank', ''],
        ['#[@.]tandt[.]#', 'blank', '1000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]tandt[.]#i', 'blank', ''],
    ];
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "10.08.2015, 15:53";
    public $crDate = "03.06.2015, 11:31";
    public $xPath = "";
    public $mailFiles = "transport/it-2779257.eml, transport/it-2815480.eml, transport/it-2815484.eml, transport/it-2815486.eml, transport/it-2815488.eml, transport/it-2960774.eml, transport/it-2976319.eml, transport/it-2987436.eml, transport/it-2990481.eml, transport/it-2990579.eml, transport/it-3053185.eml, transport/it-3053190.eml, transport/it-3106366.eml, transport/it-3106426.eml, transport/it-3106955.eml, transport/it-3106996.eml, transport/it-3133442.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('text/plain');
                    $text = preg_replace("#\n>+#", "\n", $text);
                    $text = preg_replace("#(\([A-Z]+\)\s+at\s+\d+:\d+\s+[A-Z]+)\ +([^\n]+?\s+Flight\s+\d+\s*$)#mi", "\\1\n\\2", $text);

                    if ($email = re("#\n\s*((?:[a-z0-9_-]+\.)*[a-z0-9_-]+@[a-z0-9_-]+(?:\.[a-z0-9_-]+)*\.[a-z]{2,6})\s*\n#i", preg_replace("#<.*?>#", "", $text))) {
                        $this->parsedValue("userEmail", $email);
                    }

                    $date = rew('Invoice Date : (.+? \d{4})');
                    $date = totime($date);
                    $this->anchor = $date;

                    $this->passengers = null;

                    if (preg_match_all("#(?:^|\n)[\s>]*([^\n]+)#", re("#\n[\s>]*Travelers\s*:(\s*.+?\n)\s*[^\n\/]+\s*\n#si"), $m)) {
                        $this->passengers = $m[1];
                        $this->passengers = array_unique(array_map(function ($s) { $s = explode(" Tkt # ", $s);

return $s[0]; }, $this->passengers));
                    }

                    $total = re('#Total\s.+?Grand\s+Total\s+[^\n]*? *(\S+)\s*\n#si');

                    if ($total) {
                        $currency = currency($total);
                        $amount = cost(rew('(\d+.+)', $total));
                        $total = [
                            'Amount'   => $amount,
                            'Currency' => $currency,
                        ];
                        $this->parsedValue('TotalCharge', $total);
                    }

                    $this->locators = [];

                    $tour = re("#Tour Reservation\s+Vendor\s*:\s*(.*?)\s+Confirmation No\.#msi", $text);
                    // (?:\n Tour|) \n '.$tour.' \n Start Date: |

                    // not that bad
                    $q = white('
						\n \w+ Reservation \n |
						\n Flights \n [^\n]+ |
						\n Hotel .*? \n Check-in: |
						\n (?:Hotel \n|) [^\n]+ \n Check-in : |
						\n (?:Tour \n|) [^\n]+\n[^\n]+\n\s*Tour Name: |
						\n [^\n]+
						\n Start Date\s*:[^\n]*?End Date\s*:[^\n]*?
						\n Open ticket to visit the .* at anytime during your stay |
						\n Car \n |
						\n Cruise \n |
						\n NOTransportation \n |
						\n Rail \n
					');

                    $res = splitter("/($q)/msi", $text);

                    $this->segments = [];
                    $this->md5Links = [];
                    $this->tour = 0;
                    $this->cruise = 0;

                    foreach ($res as $k=>$c) {
                        if ((strpos($c, 'Tour Name:') !== false || strpos($c, 'Open ticket to visit the') !== false) && strpos($c, "\Tour\n") === false) {
                            $c = "\nTour\n" . $c;
                        }

                        if (strpos($c, 'Check-in:') !== false && strpos($c, "\nHotel\n") === false) {
                            $c = "\nHotel\n" . $c;
                        }

                        $this->segments[$k] = [
                            'content'=> $c,
                            'type'   => trim(re("#^\s+([^\n]+)#msi", $c)),
                        ];
                        $this->md5Links[md5($c)] = $k;
                    }

                    $res = [];

                    foreach ($this->segments as $k=>$data) {
                        if ($data['type'] == 'Tour Reservation') {
                            $this->tour = $k;
                        }

                        if ($data['type'] == 'Cruise Reservation') {
                            $this->cruise = $k;
                        }

                        if (strpos($data['type'], 'Reservation') === false) {
                            $res[] = $data['content'];
                        }
                    }

                    return $res;
                },

                "#^\s*Hotel\s+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $rText = $this->getReservationText($text);

                        if (!$rText) {
                            return CONFNO_UNKNOWN;
                        } else {
                            return [
                                "ConfirmationNumber" => re("#Confirmation No\.\s*:\s*(\w+)#", $rText),
                                "Status"             => re("#Booking Status\s*:\s*(\w+)#", $rText),
                                "Rooms"              => re("#No\.\s+of\s+Rooms\s*:\s*(\d+)#", $rText),
                                "Guests"             => re("#No\.\s+of\s+Travelers\s*:\s*(\d+)#", $rText),
                                "GuestNames"         => array_map(function ($s) { return re("#\w+/\w+#", $s); }, explode(";", re("#\n\s*Travelers\s*:\s*([^\n]+)#", $rText))),
                            ];
                        }
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        // with address
                        $q = white('\>* Hotel \n
							(?P<HotelName> .*?) \n
							(?P<Address> .*?)
							(?P<Phone> [\d\s-]+) \( Phone \)
						');
                        $res = re2dict($q, $text, 'is');

                        if ($res) {
                            $res['Address'] = $res['Address'];

                            return $res;
                        }

                        // without
                        $res = [];
                        $name = reni('Hotel \n (.+?) \n Check-in :');
                        $res['Address'] = $res['HotelName'] = clear('/>/', $name);

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('Check-in : (.+? \d{4})', $text, 1, 'is');
                        $time = uberTime(1);

                        $dt = totime($date);

                        if ($time) {
                            $dt = strtotime($time, $dt);
                        }

                        return $dt;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('Check-out : (.+? \d{4})', $text, 1, 'is');
                        $time = uberTime(2);

                        $dt = totime($date);

                        if ($time) {
                            $dt = strtotime($time, $dt);
                        }

                        return $dt;
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return reni('\n ([\d-\s]+) \( Fax \)', $text, 1, 'is');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $res["RoomType"] = re("#Room\s+Type\s*:\s*(.*?)\s*(?:Smoking:|\n)#");
                        $rText = $this->getReservationText($text);

                        if ($rText) {
                            $code = trim(re("#\n\s*Travelers\s*:\s*[^\n]+\n\s*([^\n]+)#", $rText));

                            if ($code) {
                                if ($type = trim(re("#{$code}\s+(.*?)\s+{$code}#msi", $rText))) {
                                    $typeParts = array_filter(explode(".", $type));

                                    if (!$res["RoomType"]) {
                                        $res["RoomType"] = $typeParts[0];
                                        unset($typeParts[0]);
                                        sort($typeParts);

                                        if (!empty($typeParts)) {
                                            $res["RoomTypeDescription"] = implode(".", $typeParts);
                                        }
                                    } else {
                                        $res["RoomTypeDescription"] = implode(".", $typeParts);
                                    }

                                    return $res;
                                }
                            }
                        }

                        return [
                            "RoomType"           => reni('Room Type : ([^\n]+)', $text, 1, 'is'),
                            "RoomTypeDescription"=> reni('Bedding : (.+?) \n', $text, 1, 'is'),
                        ];
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $total = re("#Reservation Amount\s*([^\n]+)#msi", $text);

                        if (strpos($text, "Base.......Tax.....Total") !== false) {
                            $prices = preg_split("#\.{2,}#", trim($total, ". "));

                            return [
                                'Cost'    => $prices[0],
                                'Taxes'   => $prices[1],
                                'Total'   => $prices[2],
                                'Currency'=> currency(re("#\n\s*Balance\s*:\s*([^\n]+)#", $this->text())),
                            ];
                        }
                    },
                ],

                "#^\s*Flights?\s+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $this->rText = $rText = $this->getReservationText($text);

                        if ($rText) {
                            return [
                                "RecordLocator"=> orval(
                                    re("#Record Locator\s*:\s*(\w+)#", $rText),
                                    // re("#Confirmation No\.\s*:\s*(\w+)#", $rText),
                                    CONFNO_UNKNOWN
                                ),
                                "Status"    => re("#Booking\s+Status\s*:\s*(\w+)#", $rText),
                                "Passengers"=> array_filter(array_map(
                                    function ($s) {
                                        $s = explode("Tkt #", $s);

                                        return trim($s[0]);
                                    },
                                    explode("\n", trim(re("#\n\s*Travelers\s*:(" .
                                        str_repeat("\s+[^\n]+", (int) re("#No\. of Travelers\s*:\s*(\d+)#", $rText)) .
                                    ")#msi", $rText)))
                                )),
                            ];
                        }

                        return CONFNO_UNKNOWN;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        preg_match_all("#([^\n]*?)\s*Flight\s+\d+#", $text, $m, PREG_PATTERN_ORDER);
                        $airlines = array_unique($m[1]);

                        if (count($airlines) == 1) {
                            $total = re("#Reservation Amount\s*([^\n]+)#msi", $text);

                            if (strpos($text, "Base.......Tax.....Total") !== false) {
                                $prices = preg_split("#\.{2,}#", trim($total, ". "));

                                return [
                                    'Tax'        => cost($prices[1]),
                                    'TotalCharge'=> cost($prices[2]),
                                    'Currency'   => currency(re("#\n\s*(?:Balance|Reservation\s+Totals)\s*:?\s*([^\s\.]+[^\n]+)#", $this->text())),
                                ];
                            } else {
                                return [
                                    "TotalCharge"=> cost(re("#Reservation Amount[\s\.]+(.+)#")),
                                    'Currency'   => currency(re("#\n\s*(?:Balance|Reservation\s+Totals)\s*:?\s*([^\s\.]+[^\n]+)#", $this->text())),
                                ];
                            }
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $q = "#((?:\n\s*[A-Z]+ +|)(?:(?:[A-Z]{1}|)[a-z]+ +|[A-Z]\.){1,}\s*Flight\s+\d+\s*\n\s*" .
                        "\d+/\d+/\d+\s+[^\n]*?\s+at\s+\d+:\d+\s+[AP]M\s*\n\s*" .
                        "\d+/\d+/\d+\s+[^\n]*?\s+at\s+\d+:\d+\s+[AP]M)#msu";
                        $res = splitter($q, $text);
                        // preg_match_all($q, $text, $m);
                        // foreach($res as $k=>$r)
                        // if(isset($m[0]) && isset($m[0][$k]))
                        // $res[$k] = $m[0][$k] . $r;
                        // print_r($res);
                        return $res;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (strpos($text, 'To Be Announced') || strpos($text, 'TBA Flight TBA')) {
                                return FLIGHT_NUMBER_UNKNOWN;
                            }

                            return reni('Flight (\d+)');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w{3}) \)');

                            return ure("/$q/isu", 1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dt = totime(preg_match_all("#\d+/\d+/\d+#", $text, $m) ? ($m[0][0] ?? '') : "");
                            $time = preg_match_all("#(\d+:\d+\s+[AP]M)#", $text, $m) ? ($m[0][0] ?? '') : "";

                            if ($time) {
                                $dt = strtotime($time, $dt);
                            }

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w{3}) \)');

                            return ure("/$q/isu", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $dt = totime(preg_match_all("#\d+/\d+/\d+#", $text, $m) ? (isset($m[0][0]) ? $m[0][1] : '') : "");
                            $time = preg_match_all("#(\d+:\d+\s+[AP]M)#", $text, $m) ? (isset($m[0][0]) ? $m[0][1] : '') : "";

                            if ($time) {
                                $dt = strtotime($time, $dt);
                            }

                            return $dt;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $q = white('Seat No \. : (\d+\w)');

                            if (preg_match_all("/$q/isu", $text, $m)) {
                                return implode(',', nice($m[1]));
                            }

                            return reni('seats : (.+?) \n');
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#Flight\s+Duration\s*:\s*(\d+\s+hours?\s+and\s+\d+\s+minutes?)#");
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            $data = [];
                            $data['AirlineName'] = trim(re('#((?:\n\s*[A-Z]+ +|)(?:(?:[A-Z]{1}|)[a-z]+ +|[A-Z]\.){1,}\s*)Flight\s*\d+#'));

                            if (isset($this->locators[$data['AirlineName']])) {
                                $data['FlightLocator'] = $this->locators[$data['AirlineName']];
                            } else {
                                $data['FlightLocator'] = reni('Locator : >? (\w+)');
                            }

                            return $data;
                        },
                        "Operator" => function ($text = '', $node = null, $it = null) {
                            $operated = re("#Operated\s+By\s*:?\s*([^\n]+)#");

                            if ($operated && $operated != $it['AirlineName']) {
                                return $operated;
                            }
                        },
                    ],
                ],

                "#^\s*Tour\s+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "E";
                    },

                    "ConfNo" => function ($text = '', $node = null, $it = null) {
                        $rText = $this->getReservationText($text);

                        if ($rText) {
                            return [
                                "ConfNo"   => re("#Confirmation No\.\s*:\s*(\w+)#", $rText),
                                "Status"   => re("#Booking Status\s*:\s*(\w+)#", $rText),
                                "Guests"   => re("#No\. of Travelers\s*:\s*(\d+)#", $rText),
                                "DinerName"=> ($m = explode(";", re("#\n\s*Travelers\s*:\s*([^\n]+)#", $rText))) ? trim($m[0]) : null,
                            ];
                        }

                        return CONFNO_UNKNOWN;
                    },

                    "Name" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Tour Name\s*:\s*(.*?)(?:\s+Description\s*:|\n|$)#"),
                            re("#\n\s*(.*?)\s*\n\s*Start Date:#")
                        );
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        $startDate = re("#Start Date\s*:\s*(.*?)\s+End Date\s*:\s*([^\n]+)#");
                        $endDate = re(2);

                        $startTime = uberTime(1);
                        $endTime = uberTime(2);

                        $start = totime($startDate);
                        $end = totime($endDate);

                        if ($startTime) {
                            $start = strtotime($startTime, $start);
                        }

                        if ($l = re("#lasts\s+([\,\.\d]+)\s+hours#")) {
                            $l = explode(".", $l);
                            $h = $l[0];
                            $m = 0;

                            if (isset($l[1])) {
                                $m = 0.6 * str_pad($l[1], 2, '0');
                            }
                            $end = strtotime($q = "+{$h} hours {$m} min", $start);
                            $endTime = false;
                        }

                        if ($endTime) {
                            $end = strtotime($endTime, $end);
                        }

                        $res['StartDate'] = $start;

                        if ($end != $start && $end > $start) {
                            $res['EndDate'] = $end;
                        }

                        return $res;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return $it['Name'];
                    },
                ],

                "#^\s*Cruise\s+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "C";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $rText = $this->getReservationText($text);

                        if ($rText) {
                            return [
                                "RecordLocator"=> re("#Confirmation No\.\s*:\s*(\w+)#", $rText),
                                "Status"       => re("#Booking Status\s*:\s*(\w+)#", $rText),
                                "Passengers"   => array_map("trim", explode(";", re("#\n\s*Travelers\s*:\s*([^\n]+)#", $rText))),
                                "Vendor"       => re("#Vendor\s*:\s*(.+?)\s*Confirmation No\.#", $rText),
                            ];
                        }

                        return CONFNO_UNKNOWN;
                    },

                    "ShipName" => function ($text = '', $node = null, $it = null) {
                        return reni('\n Cruise \n (.+?) \n');
                    },

                    "CruiseName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            reni('Description : (.+?) \n'),
                            $it['ShipName']
                        );
                    },

                    "Deck" => function ($text = '', $node = null, $it = null) {
                        return reni('Deck: (.+?) \n');
                    },

                    "RoomNumber" => function ($text = '', $node = null, $it = null) {
                        return reni('\n Cabin \/ Room : (\w+)');
                    },

                    "RoomClass" => function ($text = '', $node = null, $it = null) {
                        return reni('\n Category : (.+?) Deck:');
                    },

                    "Dining" => function ($text = '', $node = null, $it = null) {
                        return reni('Dining : (.+?) \n');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $this->curCruise = 0;

                        return [$text, $text];
                    },

                    "TripSegments" => [
                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $this->curCruise++;
                            $ports = str_replace(" and ", " ", orval(
                                trim(re("#ports of call include\s*:\s*(.*?)\s*Guided excursion in each port of call#msi")),
                                trim(re("#Itinerary includes\s*:\s+([^\n]+)#msi"))
                            ));

                            if (strpos($ports, "\n") !== false) {
                                $ports = explode("\n", $ports);
                            } else {
                                $ports = explode(",", $ports);
                            }

                            if (count($ports) > 0) {
                                if ($this->curCruise == 1) {
                                    $port = trim($ports[0], '- ');
                                } else {
                                    $port = trim($ports[count($ports) - 1], '- ');
                                }
                            } else {
                                $port = null;
                            }

                            return orval(
                                reni('Sail from (.+?) ,'),
                                reni('THIS CRUISE \. \n \n (.+?) to'),
                                $port
                            );
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = rew('Start Date : (.+? \d{4})');
                            $time = uberTime(1);

                            $dt = totime($date);
                            $time = rew('Sail from .+? (\d+:\d+ (?: pm | am ))');

                            if ($time) {
                                $dt = strtotime($time, $dt);
                            }

                            return $dt;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = rew('End Date : (.+? \d{4})');
                            $time = uberTime(1);

                            $dt = totime($date);

                            return $dt;
                        },
                    ],
                ],

                "#^\s*Car\s+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        $rText = $this->getReservationText($text);

                        if ($rText) {
                            $model = ($m = explode("\n", trim($rText))) && count($m) > 0 ? trim($m[count($m) - 1]) : null;
                            $description = trim(re("#Description\s*:\s*([^\n]+)#"));

                            return [
                                "Number" => orval(
                                    re("#Confirmation No\.\s*:\s*(\w+)#", $rText),
                                    re("#Record Locator\s*:\s*(\w+)#", $rText)
                                ),
                                "Status"        => re("#Booking Status\s*:\s*(\w+)#", $rText),
                                "RenterName"    => trim(re("#\nTravelers\s*:\s*([^\n]+)#", $rText)),
                                "RentalCompany" => trim(re("#Vendor\s*:\s*([^\n]+?)\s+Confirmation\s+No#", $rText)),
                                "CarModel"      => re("#(?:^|\s+)([a-z\s]+or\s+similar)#i", stripos($model, "Travelers:") === false ? $model : (stripos($description, ' or similar') !== false ? $description : null)),
                                "CarType"       => stripos($description, ' or similar') === false ? re("#[^\.]+#", $description) : null,
                            ];
                        }

                        return CONFNO_UNKNOWN;
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#^\s*Car\n\s*(.*?)\s*\n#i");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        if (re("#\n\s*([^\n]+)\n\s*([\d\-]+)\s+\(Phone\)#")) {
                            return [
                                "PickupLocation" => re(1),
                                "PickupPhone"    => re(2),
                            ];
                        }

                        if (re("#\n\s*Pick\-up\s+City\s*:\s*([^\n]+?)\s+(?:Drop\-off\s+City|Category)\s*:#i")) {
                            return re(1);
                        }
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\n\s*Pick\-up\s*:\s*([^\n]+?)\s+Drop\-off\s*:#"));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        if (re("#\n\s*([^\n]+)\n\s*([\d\-]+)\s+\(Phone\)#")) {
                            return [
                                "DropoffLocation" => re(1),
                                "DropoffPhone"    => re(2),
                            ];
                        }

                        if (re("#\s*Drop\-off\s+City\s*:\s*([^\n]+)#i")) {
                            return re(1);
                        }

                        return $it['PickupLocation'];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\s*Drop\-off\s*:\s*([/\d\s].*?)\s*\n#i"));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $total = re("#Reservation Amount\s*([^\n]+)#msi", $text);

                        if (strpos($text, 'Approximate total with tax') !== false) {
                            return [
                                "TotalCharge"=> cost(re("#Approximate total with tax[\s\.]+(.+)#")),
                                'Currency'   => currency(re("#\n\s*(?:Balance|Reservation\s+Totals)\s*:?\s*([^\s\.]+[^\n]+)#", $this->text())),
                            ];
                        } elseif (strpos($text, "Base.......Tax.....Total") !== false) {
                            $prices = preg_split("#\.{2,}#", trim($total, ". "));

                            return [
                                'TotalTaxAmount'=> cost($prices[1]),
                                'TotalCharge'   => cost($prices[2]),
                                'Currency'      => currency(re("#\n\s*(?:Balance|Reservation\s+Totals)\s*:?\s*([^\s\.]+[^\n]+)#", $this->text())),
                            ];
                        } else {
                            return [
                                "TotalCharge"=> cost(re("#Reservation Amount[\s\.]+(.+)#")),
                                'Currency'   => currency(re("#\n\s*(?:Balance|Reservation\s+Totals)\s*:?\s*([^\s\.]+[^\n]+)#", $this->text())),
                            ];
                        }
                    },
                ],

                "#^\s*NOTransportation\s+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRANSFER;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return FLIGHT_NUMBER_UNKNOWN;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                "DepCode" => TRIP_CODE_UNKNOWN,
                                "ArrCode" => TRIP_CODE_UNKNOWN,
                            ];
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            if (!re("#from\s+(.*?)\s+to\s+(.+)#i")) {
                                re("#Pick-up\s+City\s*:\s*([^/]*?)\s*Drop-off\s+City\s*:\s*([^/\n]+)#i");
                            }

                            return [
                                "DepName" => trim(re(1)),
                                "ArrName" => trim(re(2)),
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(re("#Pick-up\s*:\s*(\d+/\d+/\d+)#"));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },
                    ],
                ],

                "#^\s*Rail\s+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        // TODO: add parse for Rail Reservation segment
                        $rText = $this->getReservationText($text);

                        if ($rText) {
                            return [
                                "RecordLocator" => CONFNO_UNKNOWN,
                                "Passengers"    => array_map("trim", explode(";", re("#\n\s*Travelers\s*:\s*([^\n]+)#", $rText))),
                            ];
                        }

                        return CONFNO_UNKNOWN;
                    },
                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fn = re("#^\s*Rail\s*\n\s*.*?\s+(\w+)\s*\n#i");

                            return $fn == "TBA" ? FLIGHT_NUMBER_UNKNOWN : $fn;
                        },
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w{3}) \)');

                            return ure("/$q/isu", 1);
                        },
                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dt = totime(preg_match_all("#\d+/\d+/\d+#", $text, $m) ? ($m[0][0] ?? '') : "");
                            $time = preg_match_all("#(\d+:\d+\s+[AP]M)#", $text, $m) ? ($m[0][0] ?? '') : "";

                            if ($time) {
                                $dt = strtotime($time, $dt);
                            }

                            return $dt;
                        },
                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w{3}) \)');

                            return ure("/$q/isu", 2);
                        },
                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $dt = totime(preg_match_all("#\d+/\d+/\d+#", $text, $m) ? ($m[0][1] ?? '') : "");
                            $time = preg_match_all("#(\d+:\d+\s+[AP]M)#", $text, $m) ? ($m[0][1] ?? '') : "";

                            if ($time) {
                                $dt = strtotime($time, $dt);
                            }

                            return $dt;
                        },
                        "Type" => function ($text = '', $node = null, $it = null) {
                            return "Train";
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

    private function getReservationText($text)
    {
        $types = [
            'Car'    => 'Car Reservation',
            'Flights'=> 'Air Reservation',
            'Cruise' => 'Cruise Reservation',
            'Hotel'  => 'Hotel Reservation',
            'Tour'   => 'Tour Reservation',
            // TODO change value to Rail Reservation
            'Rail'=> 'Tour Reservation',
        ];

        $key = isset($this->md5Links[md5($text)]) ? $this->md5Links[md5($text)] : false;

        if ($key === false) {
            return false;
        }
        $segment = $this->segments[$key];

        if ($types[$segment['type']] == 'Tour Reservation') {
            $prevSegment = $this->segments[$this->tour];
        } elseif ($types[$segment['type']] == 'Cruise Reservation') {
            $prevSegment = $this->segments[$this->cruise];
        } else {
            $prevSegment = $this->segments[$key - 1] ?? false;
        }

        if ($prevSegment === false) {
            return false;
        }

        if ($prevSegment['type'] == $types[$segment['type']]) {
            return $prevSegment['content'];
        }

        return false;
    }
}
