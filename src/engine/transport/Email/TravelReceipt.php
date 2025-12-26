<?php

namespace AwardWallet\Engine\transport\Email;

class TravelReceipt extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#From[:\s]+[^\n]*?@tandt#is', 'us', ''],
    ];
    public $reHtml = [
        ['#Travel and Transport#ims', 'us', '15000'],
    ];
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#tandt#i', 'us', ''],
    ];
    public $reProvider = [
        ['#tandt#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "8367";
    public $upDate = "25.02.2016, 16:03";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "bcd/it-1804333.eml, transport/it-1654069.eml, transport/it-1654070.eml, transport/it-1654071.eml, transport/it-1654072.eml, transport/it-1877889.eml, transport/it-2095903.eml, transport/it-2134402.eml, transport/it-2134406.eml, transport/it-2134410.eml, transport/it-2134411.eml, transport/it-2134412.eml, transport/it-2171521.eml, transport/it-2227887.eml, transport/it-2234456.eml, transport/it-3558424.eml, transport/it-3559545.eml, transport/it-3562791.eml, transport/it-3563235.eml, transport/it-3563579.eml, transport/it-3564439.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $to = $this->parser->getFrom() + [""];
                    $to = array_shift($to);

                    if (preg_match("/([^<]+@\w+\.[^>]+)/", $to, $m)) {
                        $userEmail = $m[1];
                    }

                    if (isset($userEmail)) {
                        $this->parsedValue('userEmail', $userEmail);
                    }

                    $total = reni('Total Invoice Amount : (.+?) \n');

                    if ($total) {
                        $total = total($total, 'Amount');
                        $this->parsedValue('TotalCharge', $total);
                    }

                    return xpath("//text()[
						contains(., 'Depart:') or
						contains(., 'Address:') or
						contains(., 'Pick Up:') or
						contains(., 'Vendor:')
					]/ancestor::table[3]
					");
                },

                ".//text()[contains(., 'Address:')]" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return reni('Confirmation :  (\d+)');
                    },

                    "ConfirmationNumbers" => function ($text = '', $node = null, $it = null) {
                        return cell("Frequent Guest ID:", +1, 0);
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:^|\n)\s*(.*?)\n\s*Address:#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(cell('Check In', +1)));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(cell('Check In', +1), 2));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            cell('Address:', +1),
                            $it['HotelName']
                        );
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return cell('Tel', +1);
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return cell('Fax', +1);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            nodes("//tr[normalize-space(.) = 'Traveller' or normalize-space(.) = 'Traveler']/following-sibling::tr[string-length(normalize-space(.)) > 1 and following-sibling::tr[contains(., 'Reference number by')]]"),
                            nodes("//td[normalize-space(.) = 'Traveller' or normalize-space(.) = 'Traveler']/ancestor::tr[1]/following-sibling::tr[contains(., '/')][1]/td[1]"),
                            cell('Traveler Name:', +1, 0, null, null)
                        );
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell('Number of Persons:', +1);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return cell('Number of Rooms:', +1);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return cell('Rate per night:', +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return cell('Cancellation', +1);
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return cell('Description:', +1);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return cell('Status:', +1);
                    },
                ],

                "#Pick Up:#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return reni('Confirmation :  (\w+)');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $rental = re("#\n\s*([^\n]+)\s+Pick Up:#");
                        $info = cell("Pick Up:", +1, 0);
                        $phone = detach("#[;\s]*Tel:\s*([+\d\(\) \-]+)#", $info);
                        $fax = detach("#[;\s]*Fax:\s*([+\d\(\) \-]+)#", $info);
                        $location = re("#\d+/\d+/\d+\s+([A-Z]{3})\s+{$rental}#", $this->text());

                        return [
                            'PickupLocation' => orval($info, $location),
                            'PickupPhone'    => $phone,
                            'PickupFax'      => $fax,
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(node(".//*[contains(text(), 'Pick Up:')]/ancestor::tr[1]/following-sibling::tr[1]")));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $info = cell("Drop Off:", +1, 0);
                        $phone = detach("#[;\s]*Tel:\s*([+\d\(\) \-]+)#", $info);
                        $fax = detach("#[;\s]*Fax:\s*([+\d\(\) \-]+)#", $info);

                        return [
                            'DropoffLocation' => orval($info, $it['PickupLocation']),
                            'DropoffPhone'    => $phone,
                            'DropoffFax'      => $fax,
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(node(".//*[contains(text(), 'Drop Off:')]/ancestor::tr[1]/following-sibling::tr[1]")));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return node('
							.//*[contains(text(), "Pick Up:")]
							/preceding::*[normalize-space(text())][1]
						');
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return cell("Type:", +1, 0);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            implode(',', nodes("//tr[normalize-space(.) = 'Traveller' or normalize-space(.) = 'Traveler']/following-sibling::tr[string-length(normalize-space(.)) > 1 and following-sibling::tr[contains(., 'Reference number by')]]")),
                            implode(',', nodes("//td[normalize-space(.) = 'Traveller' or normalize-space(.) = 'Traveler']/ancestor::tr[1]/following-sibling::tr[contains(., '/')][1]/td[1]")),
                            cell('Traveler Name:', +1, 0, null, null)
                        );
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(cell(["Estimated Total:", "Total:"], +1, 0));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(cell(["Estimated Total:", "Total:"], +1, 0));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return cell("Status:", +1, 0);
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    /* disabled by traxo request
                    $it = correctItinerary($it, true);
                    */
                },

                "#Réservation Train#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Numéro de dossier(?:[\s:]+|SNCF:)+([A-Z\d\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Voyageur\s+([A-Z /\d]+)#", $this->text());
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        //return cost(re("#\n\s*Tarif\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Tarif\s*:\s*([^\n]+)#"));
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Départ\s*:\s*([^\n]+)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(en(uberDateTime(1), 'fr'));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Arrivée\s*:\s*([^\n]+)#");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(en(uberDateTime(2), 'fr'));
                        },

                        "Type" => function ($text = '', $node = null, $it = null) {
                            return re("#Numéro de train:\s*([^\s]+)#") . '-' . re("#(Voiture\s+[^\s]+)#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+Place\s+(\d+)\s+#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Temps de trajet\s*:\s*([^\n]+)#");
                        },
                    ],
                ],

                "#^Vendor:#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return cell("Confirmation Number:", +1, 0);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            nodes("//tr[normalize-space(.) = 'Traveller' or normalize-space(.) = 'Traveler']/following-sibling::tr[string-length(normalize-space(.)) > 1 and following-sibling::tr[contains(., 'Reference number by')]]"),
                            cell('Traveler Name:', +1, 0, null, null)
                        );
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*(?:Estimated\s+Trip\s+Total|Voraussichtlicher\s+Gesamtreisepreis|Total\s+Amount)\s*:\s*([^\n]+)#ms", $this->text()));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return cell("Status:", +1, 0);
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

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return cell("Pickup Location:", +1, 0);
                        },

                        "DepAddress" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*PICKUP\-(.+)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(cell("Pickup Date and Time:", +1, 0)));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return cell("Dropoff Location:", +1, 0);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "ArrAddress" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*DROPOFF\-(.+)#");
                        },

                        "Type" => function ($text = '', $node = null, $it = null) {
                            return cell("Vendor:", +1, 0);
                        },
                    ],
                ],

                ".//text()[contains(., 'AIR -')]" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#(.*)\s+-.*(?:Record\s+Locator|Booking\s+Reference):\s+([\w\-]+)#', cell('Status:', +1), $m)) {
                            return [
                                'Status'        => $m[1],
                                'RecordLocator' => $m[2],
                            ];
                        }
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            nodes("//tr[normalize-space(.) = 'Traveller' or normalize-space(.) = 'Traveler']/following-sibling::tr[string-length(normalize-space(.)) > 1 and following-sibling::tr[contains(normalize-space(.), 'Date')] and not(contains(., 'Reference'))]"),
                            cell('Traveler Name:', +1, 0, null, null),
                            nodes('//td[normalize-space(.) = "Traveler"]/ancestor::tr[1]/following-sibling::tr[following-sibling::tr[not(contains(., "Date"))] and string-length(normalize-space(.)) > 0]/td[1]')
                        );
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $fares = explode('+', cell('Fare:', +1, 0, null, null));
                        $total = 0;

                        foreach ($fares as $cost) {
                            $currency = currency($cost);
                            $total += cost($cost);
                        }

                        $value = re("#\n\s*(?:Estimated\s+Trip\s+Total|Voraussichtlicher\s+Gesamtreisepreis|Total\s+Amount|Total\s+Invoice\s+Amount)\s*:\s*([^\n]+)#ms", $this->text());

                        return total(orval($value, $total . " " . $currency));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*(?:Fare\s*:|Tarif\s*:|Fare\s+Taken|Ticket Amount:)\s*([^\n]+)#ms", $this->text()));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*(?:Tax|Taxes)\s*:\s*([A-Z]{3}\s*[.\d,]+)#ms", $this->text()));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#(?:^|\n).*?\s+Flight\s+([A-Z\d]{2})\s*(\d+)\s+(.*?)\s+(?:\(|Class)#"),
                                'FlightNumber' => re(2),
                                'Cabin'        => re(3),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            if (!isset($this->flights)) {
                                $this->flights = [];
                            }

                            if (!isset($this->flights[$it['AirlineName'] . $it['FlightNumber']])) {
                                $this->flights[$it['AirlineName'] . $it['FlightNumber']] = 0;
                            }
                            $this->flights[$it['AirlineName'] . $it['FlightNumber']]++;

                            $text = ure("#(\s+[A-Z]{3}\s*\-\s*[A-Z]{3}\s+{$it['AirlineName']}\s*{$it['FlightNumber']})#", $this->text(), $this->flights[$it['AirlineName'] . $it['FlightNumber']]);

                            return [
                                'DepCode' => re("#\s+([A-Z]{3})\s*\-\s*([A-Z]{3})\s+{$it['AirlineName']}\s*{$it['FlightNumber']}#", $text),
                                'ArrCode' => re(2),
                            ];
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return nice(cell('Depart:', +1, 0, '//text()[1]'));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 'Depart', 'Arr' => 'Arrive'] as $key => $value) {
                                $subj = cell($value . ':', 0, 0, '/ancestor::tr[1]/following-sibling::tr[1]');
                                $res[$key . 'Date'] = totime(uberDateTime($subj));
                            }

                            return $res;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return nice(cell('Arrive:', +1, 0, '//text()[1]'));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return nice(cell(['Equipment', 'Flugge'], +1));
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return cell('Distance:', +1);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            $class = cell("{$it['DepCode']}-{$it['ArrCode']}", +4, 0, null, null);

                            return re("#/\s*([A-Z])\b#", orval($class, $it['Cabin']));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re('#\d+\w#', cell('Seat:', +1));
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $subj = cell(['Duration', 'Dauer'], +1);
                            $regex = '#(\d+\s+\w+\(\w+\)(?:\s+\w+\s+\d+\s+\w+\(\w+\))?)\s*(non-stop)?#i';

                            if (preg_match($regex, $subj, $m)) {
                                return [
                                    'Duration' => $m[1],
                                    'Stops'    => (isset($m[2]) and strtolower($m[2]) == 'non-stop') ? 0 : null,
                                ];
                            }
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return cell('Meal:', +1);
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return re("#(?:Booking Reference|Record Locator)\s*:\s*([A-Z\d-]+)#");
                        },
                    ],
                ],

                "#RAIL -#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('Confirmation :  (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return reni('Frequent Flyer \#  (.+?) \s{2,}', $this->text());
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return cell('Depart:', +1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(2);
                            $time = uberTime(1);

                            $dt = strtotime($date);
                            $dt = strtotime($time, $dt);

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return cell('Arrive:', +1);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(3);
                            $time = uberTime(2);

                            $dt = strtotime($date);
                            $dt = strtotime($time, $dt);

                            return $dt;
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
