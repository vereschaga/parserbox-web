<?php

namespace AwardWallet\Engine\orbitz\Email;

class YourItinerary extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-2143607.eml, orbitz/it-2156319.eml";

    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?orbitz#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#orbitz#i', 'us', ''],
    ];
    public $reProvider = [
        ['#orbitz#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "22.01.2015, 10:39";
    public $crDate = "";
    public $xPath = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $reservations = [];

                    $this->passengers = array_values(array_filter(nodes('//tr[contains(., "Traveler(s)")]/following-sibling::tr/td[1]')));
                    $this->recordLocators = [];

                    $flightReservations = xpath('//div[normalize-space(.) = "Flight reservation"]/following-sibling::div[1]');

                    foreach ($flightReservations as $fr) {
                        $date = re('#\w+,\s+(\w+\s+\d+,\s+\d{4})#i', $fr->nodeValue);
                        $recordLocatorsInfo = re('#Airline\s+record\s+locator:\s+.*\s+Ticket\s+numbers:#is', $fr->nodeValue);

                        if (preg_match_all('#(.*)\s+-\s+([\w\-]+)#i', $recordLocatorsInfo, $matches, PREG_SET_ORDER)) {
                            foreach ($matches as $m) {
                                $an = strtolower(nice($m[1]));

                                if ($an == strtolower('Air China')) {
                                    $an = strtolower('China Eastern Airlines');
                                }
                                $this->recordLocators[$an] = nice($m[2]);
                            }
                        }

                        $r = '#(';
                        $r .= '(?:Flight\s+\d+:\s+.*\s+)?';
                        $r .= '.*\s+(?:Food\s+for\s+purchase|Breakfast|Lunch|Dinner)?\s*\|\s+\d*\s*hr\s+\d+\s*min\s+\|\s+[\d,.]+\s+miles\s+(?:Operated\s+by.*\s+)?Depart\s*:';
                        $r .= ')#iu';

                        $flightSubReservations = splitter($r, $fr->nodeValue);

                        foreach ($flightSubReservations as $fsr) {
                            $s = $fsr;

                            if ($d = re('#Flight\s+\d+:\s+\w+,\s+(\w+\s+\d+,\s+\d+)#i', $fsr)) {
                                $s = preg_replace('#Flight\s+\d+.*#', '', $s);
                                $s .= "\nDate " . $d;
                            } else {
                                $s .= "\nDate " . $date;
                            }

                            $reservations[] = $s;
                        }
                    }

                    if (!$this->recordLocators) {
                        $this->orbitzRecordLocator = re('#Orbitz\s+record\s+locator:\s+([\w\-]+)#i');
                    }

                    $hotelReservations = xpath('//div[normalize-space(.) = "Hotel reservation"]/following-sibling::div[1]');

                    foreach ($hotelReservations as $hr) {
                        $reservations[] = $hr;
                    }

                    return $reservations;
                },

                "#Depart:#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $an = re('#\s*(.*?)\s+(\d+)\s+.*?\s+\|#iu');

                        if ($an) {
                            $an = strtolower(nice($an));
                        }

                        if (isset($this->recordLocators[$an])) {
                            return $this->recordLocators[$an];
                        } elseif ($this->orbitzRecordLocator) {
                            return $this->orbitzRecordLocator;
                        }
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $dateStr = re('#Date\s+(\w+\s+\d+,\s+\d+)#i');

                            if (!$dateStr) {
                                return null;
                            }
                            $res = null;
                            $r = '#';
                            $r .= '\s*(?P<AirlineName>.*?)\s+(?P<FlightNumber>\d+)\s+';
                            $r .= '(?P<Cabin>[^\|]+)\s+\|\s+';
                            $r .= '(?P<Aircraft>[^\|]+)\s+\|\s+';
                            $r .= '(?:(?P<Meal>[^\|]+)\s+\|\s+)?';
                            $r .= '(?P<Duration>\d+\s*hr\s+\d+\s*min)\s+\|\s+';
                            $r .= '(?P<TraveledMiles>.*\s+miles)\s+';
                            $r .= '(?:Operated\s+by.*\s+)?';
                            $r .= 'Depart:\s+(?P<DepTime>\d+:\d+\s*(?:am|pm))\s+(?P<DepName>.*)\s+\((?P<DepCode>\w{3})\)\s+';
                            $r .= 'Arrive:\s+(?P<ArrTime>\d+:\d+\s*(?:am|pm))\s+(?P<ArrName>.*)\s+\((?P<ArrCode>\w{3})\)\s+';
                            $r .= '(?:Seat:\s+(?P<Seats>(?:\d+\w,?\s*)+)\s+\|\s+Your\s+flight\s+is\s+(?P<Status>confirmed))?';
                            $r .= '#iu';

                            if (preg_match($r, $text, $m)) {
                                $keys = [
                                    'AirlineName', 'FlightNumber', 'Cabin', 'Aircraft', 'Meal', 'Duration',
                                    'TraveledMiles', 'DepCode', 'DepName', 'ArrCode', 'ArrName', 'Seats', 'Status',
                                ];

                                foreach ($keys as $k) {
                                    if (isset($m[$k]) and $m[$k]) {
                                        $res[$k] = nice($m[$k]);
                                    }
                                }

                                foreach (['Dep', 'Arr'] as $key) {
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[$key . 'Time']);
                                }

                                if (isset($res['DepDate']) and isset($res['ArrDate'])) {
                                    if ($dayShift = re('#This\s+flight\s+arrives\s+(\w+)\s+days\s+later#i')) {
                                        $m = [
                                            'one' => 1,
                                            'two' => 2,
                                        ];
                                        $res['ArrDate'] = strtotime('+ ' . $m[$dayShift] . ' day', $res['ArrDate']);
                                    } else {
                                        correctDates($res['DepDate'], $res['ArrDate']);
                                    }
                                }
                            }

                            return $res;
                        },
                    ],
                ],

                "#Check-in:#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $cn = re('#Confirmation\s+number\s*:\s+([\w\-]+)#');

                        if (!$cn) {
                            $cn = CONFNO_UNKNOWN;
                        }

                        return $cn;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $hn = node('.//tr[contains(., "Phone:") and not(.//tr)]/td[1]');

                        if ($hn) {
                            $hn = preg_replace('#\s+-\s+Adults\s+Only\s+All\s+Inclusive#i', '', $hn);
                        }

                        return $hn;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        if (re('#Check-in\s*:\s+\w+,\s+(\w+\s+\d+,\s+\d+)\s+(\d{1,2})(\d{2})#i')) {
                            return strtotime(re(1) . ', ' . re(2) . ':' . re(3));
                        }
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        if (re('#Check-out\s*:\s+\w+,\s+(\w+\s+\d+,\s+\d+)\s+(\d{1,2})(\d{2})#i')) {
                            return strtotime(re(1) . ', ' . re(2) . ':' . re(3));
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return node('.//tr[contains(., "Phone:") and not(.//tr)]/td[2]');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Phone\s*:\s+([\d\-\s]+)#i', node('.//tr[contains(., "Phone:") and not(.//tr)]/td[3]')));
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Fax\s*:\s+([\d\-\s]+)#i', node('.//tr[contains(., "Phone:") and not(.//tr)]/td[3]')));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Reservation\s+made\s+for\s*:\s+(.*)#')];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#Total\s+guests\s*:\s+(\d+)#i');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#Total\s+rooms\s*:\s+(\d+)#i');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#cancellation\s+policy\s*:\s+(.*)#i');
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\s+description\s*:\s+(.*)#i');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total\s+charges\s*:\s+(.*)#i'), 'Total');
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Reservation\s+Made\s*:\s+(.*)#', $this->text()));
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return $it;
                    $itNew = uniteAirSegments($it);

                    if (count($itNew) == 1) {
                        if ($totalStr = re('#Total\s+trip\s+cost\s+(.*)#i', $this->text())) {
                            $itNew[0]['Currency'] = currency($totalStr);

                            switch ($itNew[0]['Kind']) {
                            case 'R':
                                $itNew[0]['Total'] = cost($totalStr);

                                break;

                            case 'T':
                                $itNew[0]['TotalCharge'] = cost($totalStr);

                                break;
                            }
                        } elseif ($totalStr = re('#Total\s+flight\s+cost:\s+(.*)#', $this->text())) {
                            $itNew[0]['TotalCharge'] = cost($totalStr);
                            $itNew[0]['Currency'] = currency($totalStr);
                        }
                    } else {
                        $reservationsCount = null;

                        for ($i = 0; $i < count($itNew); $i++) {
                            if (isset($itNew[$i]['Kind']) and $i['Kind']) {
                                $reservationsCount[$itNew[$i]['Kind']]['Count']++;
                                $reservationsCount[$itNew[$i]['Kind']]['Indices'][] = $i;
                            }
                        }

                        if (isset($reservationsCount['T']) and $reservationsCount['T'] == 1) {
                            $totalStr = re('#Total\s+flight\s+cost:\s+(.*)#', $this->text());
                            $i = $reservationsCount['T']['Indices'][0];
                            $itNew[$i]['TotalCharge'] = cost($totalStr);
                            $itNew[$i]['Currency'] = currency($totalStr);
                        }
                    }

                    return $itNew;
                },

                "#.*#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },
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
