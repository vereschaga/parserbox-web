<?php

namespace AwardWallet\Engine\wagonlit\Email;

// parsers with similar formats: TripItinerary

class It2061322 extends \TAccountCheckerExtended
{
    public $mailFiles = "wagonlit/it-2055521.eml, wagonlit/it-2061322.eml, wagonlit/it-21811457.eml, wagonlit/it-2222287.eml, wagonlit/it-2222290.eml, wagonlit/it-2263673.eml, wagonlit/it-2269539.eml, wagonlit/it-2269614.eml, wagonlit/it-2269615.eml, wagonlit/it-2363799.eml, wagonlit/it-2367994.eml, wagonlit/it-2376396.eml, wagonlit/it-2384422.eml, wagonlit/it-2427991.eml, wagonlit/it-2479625.eml, wagonlit/it-2481210.eml, wagonlit/it-2562925.eml, wagonlit/it-2700250.eml, wagonlit/it-2700257.eml, wagonlit/it-2700264.eml, wagonlit/it-2703243.eml, wagonlit/it-2738315.eml, wagonlit/it-2786283.eml, wagonlit/it-3555185.eml, wagonlit/it-3555506.eml, wagonlit/it-6.eml, wagonlit/it-8829644.eml";

    private $ppl = [];
    private $reserv = [];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $texts = implode("\n", $this->parser->getRawBody());

                    if (substr_count($texts, 'Content-Type: text/html') > 1) {
                        $texts = preg_replace("#^(---*[a-z\d\-_]{25,60})$#m", "\n", $texts);
                        $posBegin1 = stripos($texts, "Content-Type: text/html");
                        $body = '';
                        $i = 0;

                        while ($posBegin1 !== false && $i < 30) {
                            $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                            $posEnd = stripos($texts, "\n\n", $posBegin);
                            $header = substr($texts, $posBegin1, $posBegin - $posBegin1);

                            if (preg_match("#name=.*\.htm.*base64#s", $header)) {
                                $t = substr($texts, $posBegin, $posEnd - $posBegin);
                                $body .= base64_decode($t);
                            } elseif (preg_match("#Encoding: quoted-printable#s", $header)) {
                                $t = substr($texts, $posBegin, $posEnd - $posBegin);
                                $body .= quoted_printable_decode($t);
                            }
                            $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                            $i++;
                        }
                        $this->http->SetEmailBody($body);
                    }

                    $ppl = nodes("//*[normalize-space(text()) = 'Traveler']/following::td[1]");
                    $ppl = array_map(function ($x) { return re_white('(.+?) (?:\s+ -|$)', $x); }, $ppl);
                    $this->ppl = $ppl;

                    $date = re_white('Locator: (?:\w+) Date: (\w+ \d+, \d+)');
                    $date = strtotime($date);
                    $this->reserv = $date;

                    $emails = nodes("//*[contains(text(), 'Traveler')]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>5][(contains(.,'*') or contains(.,'.') or contains(.,':')) and not(contains(., '**'))][1]");

                    foreach ($emails as $email) {
                        if (re("#^([a-zA-Z0-9_.+-]+[*:@][a-zA-Z0-9-.]+)$#", $email)) {
                            $email = clear("#[*:]#", $email, '@');
                            $this->parsedValue("userEmail", strtolower($email));

                            break;
                        }
                    }

                    $amount = cost(re("#\n\s*Total\s+Amount\s+([^\n]+)#", $this->text()));
                    $currency = orval(
                        currency(node("//*[contains(text(), 'Invoice / Ticket / Date')]/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>1][3]")),
                        currency(re("#\n\s*Approximate\s+Total\s+([A-Z]{3})#", $this->text())),
                        currency(re("#\n\s*APPROXIMATE ([A-Z]{3}) EQUIVALENT#x", $this->text()))
                    );

                    if ($amount) {
                        $this->parsedValue('TotalCharge', ['Amount' => $amount, 'Currency' => $currency]);
                    }

                    // with transfer (limo)
                    // return xpath("//*[contains(text(), 'Confirmation:')]/following::table[1]");

                    // without transfer
                    return xpath("//table[.//*[contains(text(), 'Confirmation:') or contains(text(), 'Confirmation ')]/following::table[1][not(contains(., 'Other Service') or contains(., 'Tour'))]]/following::table[1][.//img[contains(@src, 'Segment.png')]]");
                },

                "#^\s*Flight#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $info = node('./preceding::table[1]');

                        return orval(
                            re_white('Confirmation: \*?(\w+)', $info),
                            re("#\s+Locator\s*:\s*([A-Z\d\-]+)\s+Date\s*:#", $this->text()),
                            CONFNO_UNKNOWN
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->ppl;
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $info = node('./following::table[1]');

                        return re_white('Frequent Flyer (\w+)', $info);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $r = nodes("//*[contains(text(), 'Invoice / Ticket / Date')]/ancestor::tr[1]/following-sibling::tr/td[string-length(normalize-space(.))>1][3]");
                        $total = 0;

                        foreach ($r as $cost) {
                            $total += cost($cost);
                        }

                        return $total;
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return $this->reserv;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $info = node(".//*[contains(text(), 'DEPARTURE')]/preceding::td[1]");

                            return [
                                'AirlineName'  => re_white('Flight (.+?) \s+ (\d+)', $info),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $info = node(".//*[contains(text(), 'DEPARTURE')]/following::td[2]");

                            return re_white('^ ([A-Z]+)', $info);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dt = node(".//*[contains(text(), 'DEPARTURE')]/following::td[4]");
                            $dt = uberDateTime($dt);

                            return strtotime($dt);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $info = node(".//*[contains(text(), 'DEPARTURE')]/following::td[3]");

                            return re_white('^ ([A-Z]+)', $info);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $dt = node(".//*[contains(text(), 'DEPARTURE')]/following::td[5]");
                            $dt = uberDateTime($dt);

                            return strtotime($dt);
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $info = node('./following::table[1]');

                            return clear("#\([^\)]+\)$#", between('Equipment', 'Meal Service', $info));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $res['Cabin'] = re("#\n\s*Class[:\s]+(.*?)\s*\-\s*([A-Z])#", xpath('following::table[1]'));
                            $res['BookingClass'] = re(2);
                            $notes = implode("\n", nodes("./following::table[1]//*[contains(text(), 'Notes')]/ancestor::table[1]//text()[normalize-space()!='']"));
                            $notes = re("#Notes\s+(.+)#s", $notes);

                            if (!empty($notes)) {
                                $res['Operator'] = re("#OPERATED BY +(.+)#i", $notes);
                                $res['DepartureTerminal'] = re("#DEPARTS [A-Z]{3} TERMINAL +(\w+)#i", $notes);
                                $res['ArrivalTerminal'] = re("#ARRIVES [A-Z]{3} TERMINAL +(\w+)#i", $notes);
                            }

                            return array_filter($res);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $info = node('./following::table[1]');

                            return re_white('Reserved Seats (\w+) \s+', $info);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $info = node('./following::table[1]');

                            return re_white('Duration (\d+:\d+)', $info);
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Meal Service[:\s]+(.*?)\s{2,}#", xpath('following::table[1]'));
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return (int) re('#Non[\s-]*stop#i', node('following::table[1]')) ? 0 : null;
                        },
                    ],
                ],

                "#^\s*Car#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'L';
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re_white('Confirmation: (\w+)', node("preceding::table[1]")),
                            CONFNO_UNKNOWN
                        );
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $loc = node(".//*[contains(text(), 'PICK-UP')]/following::td[4]");

                        return nice($loc);
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $dt = node(".//*[contains(text(), 'PICK-UP')]/following::td[2]");
                        $dt = uberDateTime($dt);

                        return strtotime($dt);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $loc = orval(
                            trim(node(".//*[contains(text(), 'PICK-UP')]/following::td[5]")),
                            node(".//*[contains(text(), 'PICK-UP')]/following::td[4]")
                        );

                        return nice($loc);
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $dt = node(".//*[contains(text(), 'DROP-OFF')]/following::td[2]");
                        $dt = uberDateTime($dt);

                        return strtotime($dt);
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        $tel = node(".//*[contains(text(), 'PICK-UP')]/following::td[6]");

                        return nice($tel);
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        $info = node(".//*[contains(text(), 'PICK-UP')]/preceding::td[1]");
                        $name = re_white('^ Car (.+)', $info);

                        return nice($name);
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $info = node('./following::table[1]');

                        return between('Car Type', 'Rate', $info);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $info = node('./following::table[1]');

                        return between('Reserved For', 'Status', $info);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $info = node('./following::table[1]');
                        $tot = between('Approximate Total', 'Membership No', $info);
                        $tot = re_white('(.+?) \/', $tot);

                        return total($tot);
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Rate\s+([A-Z]{3})#", xpath("following::table[1]")));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return between('Status', 'Car Type', node('following::table[1]'));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return $this->reserv;
                    },
                ],

                "#^\s*Hotel#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'R';
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $info = node('./preceding::table[1]');

                        return orval(
                            re_white('Confirmation: (\w+)', $info),
                            re("#\n\s*Locator\s*:\s*([A-Z\d\-]+)#", $this->text()),
                            CONFNO_UNKNOWN
                        );
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node(".//*[contains(text(), 'LOCATION')]/preceding::td[1]");
                        $name = clear('/^\s*Hotel\s*/i', $name);

                        return nice($name);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $info = node('./following::table[1]');
                        $date = between('Check-In', 'Check-Out', $info);

                        return strtotime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $info = node('./following::table[1]');
                        $date = between('Check-Out', 'Number of Rooms', $info);

                        return strtotime($date);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $loc1 = node(".//*[contains(text(), 'LOCATION')]/following::td[2]");
                        $loc2 = node(".//*[contains(text(), 'LOCATION')]/following::td[4]");
                        $loc = "$loc1, $loc2";

                        return nice($loc);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re_white('\bTel ([\d-]{5,})');
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re_white('Fax ([\d-]+)');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $info = node('./following::table[1]');

                        return [between('Reserved For', 'Status', $info)];
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $info = node('./following::table[1]');

                        return re_white('Number of Rooms (\d+)', $info);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Rate\s+([^\n]+)#", xpath("following::table[1]"));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $info = node('following::table[1]');

                        return orval(
                            between('Cancellation Policy', 'Membership No', $info),
                            between('Cancellation Policy', 'Notes', $info),
                            re("#CXL\s*:\s*([^\n]+)#", $info),
                            re("#Cancellation Policy\s*([^\n]+)#", $info)
                        );
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Rate\s+([A-Z]{3})#", xpath("following::table[1]")));
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $info = node('./following::table[1]');

                        return re_white('Membership No (\w+)', $info);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $info = node('./following::table[1]');

                        return re_white('Status (\w+)', $info);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Date\s*:\s*([^\n]+)#", $this->text()));
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
                },

                "#Other\s+Service#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'B';
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node("following::table[1]//tr[contains(., 'Confirmation')]/td[2]"),
                            re("#CONF\-(\d+)#", node("following::table[1]")),
                            CONFNO_UNKNOWN
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->ppl;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return node("following::table[1]//tr[contains(., 'Status')]/td[2]");
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_BUS;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepAddress" => function ($text = '', $node = null, $it = null) {
                            return node("following::table[1]//tr[contains(.,'Pick-Up Location')]/td[2]");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(node("following::table[1]//tr[contains(.,'Departure')]/td[2]")));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node("following::table[1]//tr[contains(., 'Drop-Off Location')]/td[2]");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(node("following::table[1]//tr[contains(.,'Arrival')]/td[2]")));
                        },
                    ],
                ],

                "#^\s*(Rail|Train)\s+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $info = node('./preceding::table[1]');

                        return orval(
                            reni('Confirmation\s*:?\s*(\w+)', $info),
                            re("#\n\s*Locator\s*:\s*([A-Z\d\-]+)#", $this->text()),
                            CONFNO_UNKNOWN
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->ppl;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $info = node('./following::table[1]');

                        if (rew('Status  Confirmed', $info)) {
                            return 'confirmed';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return orval(re('/\d+/', node(".//*[contains(text(),'DEPARTURE')]/preceding::td[2]")), FLIGHT_NUMBER_UNKNOWN);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $subj = node('(.//*[contains(text(), "DEPARTURE")])[1]/following::td[2]');

                            if (preg_match("#(\d+:\d+.*?), (.+? \d{4})#", $subj, $m)) {
                                $subj = $m[2] . ', ' . $m[1];
                            }

                            if (strtotime($subj) !== false) {
                                $subj = implode(' ',
                                    nodes('(.//*[contains(text(), "DEPARTURE")])[1]/ancestor-or-self::tr[1]/following-sibling::tr[position()>1]/td[1]'));
                            }

                            return trim($subj);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node('(.//*[contains(text(), "DEPARTURE")])[1]/following::td[2]');

                            if (preg_match("#(\d+:\d+.*?), (.+? \d{4})#", $date, $m)) {
                                $date = $m[2] . ', ' . $m[1];
                            }
                            $dt = strtotime($date);

                            if ($dt === false) {
                                $date = uberDate(1);
                                $time = uberTime(1);
                                $dt = strtotime($date);

                                if ($time) {
                                    $dt = strtotime($time, $dt);
                                }
                            }

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $subj = node('(.//*[contains(text(), "DEPARTURE")])[1]/following::td[2]');

                            if (preg_match("#(\d+:\d+.*?), (.+? \d{4})#", $subj, $m)) {
                                $subj = $m[2] . ', ' . $m[1];
                            }

                            if (strtotime($subj) !== false) {
                                $subj = implode(' ',
                                    nodes('(.//*[contains(text(), "ARRIVAL")])[1]/ancestor-or-self::tr[1]/following-sibling::tr[position()>1]/td[2]'));
                            }

                            return trim($subj);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $subj = node('(.//*[contains(text(), "DEPARTURE")])[1]/following::td[2]');

                            if (preg_match("#(\d+:\d+.*?), (.+? \d{4})#", $subj, $m)) {
                                $subj = $m[2] . ', ' . $m[1];
                            }

                            if (strtotime($subj) !== false) {
                                $date = node('(.//*[contains(text(), "ARRIVAL")])[1]/following::td[2]');

                                if (preg_match("#(\d+:\d+.*?), (.+? \d{4})#", $date, $m)) {
                                    $date = $m[2] . ', ' . $m[1];
                                }
                                $dt = strtotime($date);
                            } else {
                                $date = uberDate(2);
                                $time = uberTime(2);
                                $dt = strtotime($date);

                                if ($time) {
                                    $dt = strtotime($time, $dt);
                                }
                            }
                            // when date in Notes. FE: 21811457.eml
                            if (isset($it['DepDate']) && $it['DepDate'] > $dt && date("H:i", $dt) == '00:00') {
                                $notes = implode(" ",
                                    nodes("./following::table[1]//*[contains(text(), 'Notes')]/ancestor::table[1]//text()[normalize-space()!='']"));
                                $notes = re("#Notes\s+(.+)#s", $notes);

                                if (!empty($notes)) {
                                    $name = node('.//*[contains(text(), "ARRIVAL")]/ancestor-or-self::tr[1]/following-sibling::tr[2]/td[2]');

                                    if (!empty($name) && strpos($it['ArrName'], $name) !== false) {
                                        $notes = preg_replace("#\s+#", ' ', str_replace('-', ' ', $notes));
                                        $name = preg_replace("#\s+#", ' ', str_replace('-', ' ', $name));

                                        if (preg_match("#AR (\d{2})(\d{2})\/AT {$name}#", $notes, $m)) {
                                            return strtotime($m[1] . ':' . $m[2], $dt);
                                        }
                                    }
                                }

                                return null; //to review email
                            }

                            return $dt;
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            $info = node('./following::table[1]');

                            return re_white('Class\s+([A-Z])', $info);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $info = node('./following::table[1]');

                            return re_white('Seat\s+([A-Z\d]+)', $info);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $info = node('./following::table[1]');

                            return re_white('Duration\s+(.*)', $info);
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from'])
        && (
            stripos($headers['from'], '@carlsonwagonlit.com') !== false
            || stripos($headers['from'], 'info@reservation.carlsonwagonlit.co.uk') !== false
        ) && isset($headers['subject'])
        && (
            stripos($headers['subject'], 'UNTICKETED ITINERARY:') !== false
            || stripos($headers['subject'], 'Travel Reservations for ') !== false
            || stripos($headers['subject'], 'Travel Document for:') !== false
            || stripos($headers['subject'], 'Travel documents for ') !== false
        );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $texts = implode("\n", $parser->getRawBody());

        if (substr_count($texts, 'Content-Type: text/html') > 1) {
            $texts = preg_replace("#^(---*[a-z\d\-_]{25,60})$#m", "\n", $texts);
            $posBegin1 = stripos($texts, "Content-Type: text/html");
            $body = '';
            $i = 0;

            while ($posBegin1 !== false && $i < 30) {
                $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                $posEnd = stripos($texts, "\n\n", $posBegin);
                $header = substr($texts, $posBegin1, $posBegin - $posBegin1);

                if (preg_match("#name=.*\.htm.*base64#s", $header)) {
                    $t = substr($texts, $posBegin, $posEnd - $posBegin);
                    $body .= base64_decode($t);
                } elseif (preg_match("#Encoding: quoted-printable#s", $header)) {
                    $t = substr($texts, $posBegin, $posEnd - $posBegin);
                    $body .= quoted_printable_decode($t);
                }
                $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                $i++;
            }
        }

        if (!isset($body)) {
            $body = $parser->getHTMLBody();
        }

        return (stripos($body, 'GENERAL INFORMATION') !== false || stripos($body, 'CWT') !== false)
            && stripos($body, 'Please do not reply to this email') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@reservation.carlsonwagonlit.co.uk') !== false
            || stripos($from, '@carlsonwagonlit.com') !== false;
    }

    public function IsEmailAggregator()
    {
        return true;
    }
}
