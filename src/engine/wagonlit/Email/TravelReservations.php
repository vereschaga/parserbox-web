<?php

namespace AwardWallet\Engine\wagonlit\Email;

class TravelReservations extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#CWT MUST BE NOTIFIED#i', 'us', '1000'],
        ['#CWT Itinerary attached#i', 'us', '1000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#rmtraveladvisor@carlsonwagonlit\.com#i', 'us', ''],
        ['#noreply@carlsonwagonlit\.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#carlsonwagonlit\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "1";
    public $caseReference = "";
    public $upDate = "11.05.2016, 14:19";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "wagonlit/it-1691910.eml, wagonlit/it-1692439.eml, wagonlit/it-1695700.eml, wagonlit/it-1695774.eml, wagonlit/it-1695789.eml, wagonlit/it-1695815.eml, wagonlit/it-1805528.eml, wagonlit/it-1805598.eml, wagonlit/it-1805695.eml, wagonlit/it-1806784.eml, wagonlit/it-1808815.eml, wagonlit/it-1809217.eml, wagonlit/it-1809335.eml, wagonlit/it-3789245.eml, wagonlit/it-3808627.eml, wagonlit/it-3810046.eml, wagonlit/it-3812735.eml, wagonlit/it-3814005.eml, wagonlit/it-4819794.eml, wagonlit/it-4827952.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("text/rtf", "text");

                    $emailRegex = '([\w\.]+(?://|:)[\w\.]+)';
                    $email = orval(re('#GENERAL\s+INFORMATION\s+' . $emailRegex . '#i'), re('#ATTN:\s+' . $emailRegex . '\s+Agent#i'));
                    $email = strtolower(clear("#//|:#", $email, '@'));
                    $this->parsedValue("userEmail", $email);

                    $this->travellers = [re('#\n\s*Traveler\s*\n\s*(.*?)\s*-#i')];

                    $reservations = splitter("#(\n\s*DEPARTURE\s+Flight|\n\s*[A-Z\d\- *]+\n\s*Hotel\s+|\n\s*DROP[\s\-]+OFF\s*\n)#ms");

                    return $reservations;
                },

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    // return $it;
                    $itNew = uniteAirSegments($it);

                    if (count($itNew) == 1 && !empty($itNew[0])) {
                        $itNew[0]['Currency'] = currency(re("#\s+([A-Z]{3})\s+\d+\.\d+#", $this->text()));
                        $total = cost(re('#(.*)\s+Total\s+Amount#i', $this->text()));

                        if (isset($itNew[0]['Kind'])) {
                            switch ($itNew[0]['Kind']) {
                                case 'L':
                                case 'T':
                                    $itNew[0]['TotalCharge'] = $total;

                                    break;

                                case 'R':
                                    $itNew[0]['Total'] = $total;

                                    break;
                            }
                        }
                    }

                    return $itNew;
                },

                "#\s+DEPARTURE\s+Flight#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $this->currentFlightSegmentInfo = null;
                        $regex = '#';
                        $regex .= '\n\s*Flight\s+(?P<AirlineName>.*)\s+(?P<FlightNumber>\d+)\s*';
                        $regex .= '\n\s*(?P<DepCode>\w{3})\s+-\s+(?P<DepName>.*)\s*';
                        $regex .= '\n\s*(?P<DepTime>\d+:\d+\s+(?:am|pm)),\s+(?P<DepDate>\w+\s+\d+,\s+\d+)\s*';
                        $regex .= '\n\s*(?P<ArrCode>\w{3})\s+-\s+(?P<ArrName>.*)\s*';
                        $regex .= '\n\s*(?P<ArrTime>\d+:\d+\s+(?:am|pm)),\s+(?P<ArrDate>\w+\s+\d+,\s+\d+)\s*';
                        $regex .= '\n\s*(?P<RecordLocator>.*)';
                        $regex .= '#i';

                        if (preg_match($regex, $text, $m)) {
                            foreach (['Dep', 'Arr'] as $key) {
                                $s = $m[$key . 'Date'] . ', ' . $m[$key . 'Time'];
                                $this->currentFlightSegmentInfo[$key . 'Date'] = strtotime($s);
                            }
                            $keys = ['DepCode', 'DepName', 'ArrCode', 'ArrName', 'AirlineName', 'FlightNumber'];
                            copyArrayValues($this->currentFlightSegmentInfo, $m, $keys);
                            $res = $m['RecordLocator'];
                        }

                        return $res;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->travellers;
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re('#Frequent\s+Flyer\s+([\w\-]+)#');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Status\s+(.*)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return $this->currentFlightSegmentInfo;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Equipment\s+([^\n]+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#\n\s*Class\s+(.*?)\s*\-\s*(\w)#', $text, $m)) {
                                return [
                                    'Cabin'        => $m[1],
                                    'BookingClass' => $m[2],
                                ];
                            }
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re('#Reserved\s+Seats\s+(\d+\w)#i');
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Duration\s+(\d+:\d+)\s+(.*)#i', $text, $m)) {
                                return [
                                    'Duration' => $m[1],
                                    'Stops'    => ($m[2] == '(Non-stop)') ? 0 : null,
                                ];
                            }
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Meal Service\s*([^\n]+)#");
                        },
                    ],
                ],

                "#\s+Hotel\s+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $regex = '#^\s*([\w\-]+?)\s*(?:NON\s*SMKING\s*CONF\s*|\s+.*)?\*?';
                        $regex .= '\n\s*Hotel\s+(.*)\s*';
                        $regex .= '\n\s*((?s).*?)\s*';
                        $regex .= '\n\s*Tel\s+(.*)\s*';
                        $regex .= '\n\s*Fax\s+(.*)';
                        $regex .= '#i';

                        $regex2 = '/^\s+([\w-]{4,})\s+Hotel\s+(.+?)\n\s*(.+?)\s+Tel\s+([+\d\s()-]{7,})\s+(?:Fax\s+([+\d\s()-]{7,})\s+)?/s';

                        if (preg_match($regex2, $text, $m) || preg_match($regex, $text, $m)) {
                            $res = [
                                'ConfirmationNumber' => $m[1],
                                'HotelName'          => trim($m[2]),
                                'Address'            => nice($m[3], ','),
                                'Phone'              => trim($m[4]),
                                'Fax'                => isset($m[5]) ? trim($m[5]) : '',
                            ];
                        }

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#\n\s*Check[\s\-]In\s+(.*)#i'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#\n\s*Check[\s\-]Out\s+(.*)#i'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reserved For\s*\n\s*(.*)#i");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number of Rooms\s*(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Rate\s+([^\n]+)#");
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re('#Membership\s+No\s+([\w\-]+)#i');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s+([^\n]+)#");
                    },
                ],

                "#DROP[\s\-]+OFF#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $regex = '#';
                        $regex .= '\n\s*Car\s+(?P<RentalCompany>.*)\s*';
                        $regex .= '\n\s*(?:(?P<PickupTime>\d+:\d+\s*(?:am|pm)),\s+)?(?P<PickupDate>\w+\s+\d+,\s+\d+)\s*';
                        $regex .= '\n\s*(?P<Number>[\w\-]+)(?:\s+\w+)?\s*\*?\s*';
                        $regex .= '\n\s*(?P<Info>(?s).*?)\s*';
                        $regex .= '\n\s*(?:(?P<DropoffTime>\d+:\d+\s*(?:am|pm)),\s+)?(?P<DropoffDate>\w+\s+\d+,\s+\d+)\s*';
                        $regex .= '#i';

                        if (preg_match($regex, $text, $m)) {
                            foreach (['Pickup', 'Dropoff'] as $key) {
                                $s = $m[$key . 'Date'] . (($m[$key . 'Time']) ? ', ' . $m[$key . 'Time'] : '');
                                $res[$key . 'Datetime'] = strtotime($s);
                            }
                            $locations = explode("\n", $m['Info']);
                            array_walk($locations, function (&$value, $key) use (&$res) {
                                if (preg_match('#^[\d\s\-]+$#i', $value)) {
                                    if (isset($res['PickupPhone'])) {
                                        $res['DropoffPhone'] = nice($value);
                                    } else {
                                        $res['PickupPhone'] = nice($value);
                                    }
                                    $value = '';
                                }
                            });
                            $locationsArr = $locations;
                            $locations = implode("\n", array_filter(nice($locations)));

                            if (preg_match_all('#(?:.*\s+)?.*,\s+\w+#i', $locations, $m2)) {
                                if (count($m2[0]) == 1) {
                                    $res['PickupLocation'] = $res['DropoffLocation'] = nice($m2[0][0], ',');
                                } elseif (count($m2[0]) == 2) {
                                    $res['PickupLocation'] = nice($m2[0][0], ',');
                                    $res['DropoffLocation'] = nice($m2[0][1], ',');
                                }
                            } elseif (nice($locations)) {
                                $res['PickupLocation'] = $res['DropoffLocation'] = implode(',', array_slice($locationsArr, 0, 2));
                            }

                            copyArrayValues($res, $m, ['RentalCompany', 'Number']);
                        }

                        return nice($res);
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Car Type\s*([^\n]+)#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reserved For\s*([^\n]+)#");
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re('#Membership\s+No\s+([\w\-]+)#i');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*([^\n]+)#");
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
        return true;
    }
}
