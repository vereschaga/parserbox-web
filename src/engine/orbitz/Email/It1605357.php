<?php

namespace AwardWallet\Engine\orbitz\Email;

class It1605357 extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-4082802.eml, orbitz/it-1573388.eml, orbitz/it-1601887.eml, orbitz/it-1603495.eml, orbitz/it-1604423.eml, orbitz/it-1605357.eml, orbitz/it-1623570.eml, orbitz/it-1630926.eml, orbitz/it-1633382.eml, orbitz/it-1671371.eml, orbitz/it-1920755.eml, orbitz/it-2.eml, orbitz/it-2580486.eml, orbitz/it-4071870.eml, orbitz/it-4082802.eml, orbitz/it-4082818.eml, orbitz/it-4082819.eml, orbitz/it-4248849.eml, orbitz/it-5434254.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@orbitz.') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Flight Booking Request |') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'orbitz') !== false
                && strpos($parser->getHTMLBody(), 'To make changes to your trip, go to') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@orbitz.') !== false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $count = orval(
                        count(nodes("//*[contains(text(), 'Original Itinerary')]/ancestor::tr[1]/preceding-sibling::tr")),
                        100500
                    );

                    return xpath("
						//*[contains(text(), 'Depart') and not(contains(., 'Alert'))]/ancestor-or-self::tr[position()=1 and count(preceding-sibling::tr)<$count]
						| 
						//*[contains(text(), 'Car information')]/ancestor-or-self::table[1][not(contains(.,'No hotel selected'))][not(contains(.,'No car selected'))]
					");
                },

                "#Hotel Information\s*(?!No)#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
                },

                "#Car\s+information(?!\s+No car selected)#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Booking Reference:\s*([A-Z\d\-]+)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $up = re("#\n\s*Pick\-up:\s+(.*?)\s+Drop\-off:#ms");
                        $general = explode('|', $up);

                        return [
                            'PickupDatetime' => strtotime($general[0] . ', ' . $general[1]),
                            'PickupLocation' => nice(clear("#\n\s*Phone:.+#", $general[2])),
                            'PickupPhone'    => re("#\n\s*Phone:\s*([^\n]+)#", $up),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $off = re("#\n\s*Drop\-off:\s+(.*?)\n\s*(?:Cost and Billing Summary|$)#ms");
                        $general = explode('|', $off);

                        return [
                            'DropoffDatetime' => strtotime($general[0] . ', ' . $general[1]),
                            'DropoffLocation' => trim($general[2]),
                            'DropoffPhone'    => re("#\n\s*Phone:\s*([^\n]+)#", $off),
                        ];
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return [
                            'CarType'       => re("#\n\s*Rental:\s+([^\n]+)\n\s*([^\n]*?)\s+Booking Reference:#"),
                            'RentalCompany' => re(2),
                        ];
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*([^\n]*?)\s+or\s+similar\s+#");
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node(".//img[contains(@src, 'carImages')]/@src");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Car reservation under:\s*([^\n]+)#", $this->text());
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total car rental estimate\s*:\s*([^\n]+)#", $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Total car rental estimate\s*:\s*([^\n]+)#", $this->text()));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes and fees\s+([^\n]+)#", $this->text()));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("/reservation was made on\s+(.{6,})/", $this->text()));
                    },
                ],

                "#Depart#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("td[4]"));
                        $airline = re("#^(.*?)\s+(\d+)\s+([^|]+)\s*\|\s*([^\n\|]+)#ms", $text);

                        return orval(
                            re("/{$airline}[\s:.]+([-A-Z\d]{5,})[ ]*$/m", $this->text()),
                            re("/$airline\s+record\s+locator[\s:.]+([-A-Z\d]{5,})[ ]*$/m", $this->text()),
                            re("/Orbitz\s+record\s+locator[\s:.]+([-A-Z\d]{5,})[ ]*$/m", $this->text())
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//*[contains(text(), 'Traveler information')]/ancestor-or-self::table[1]"));
                        $names = [];

                        re("#\n\s*Traveler\s+\d+\s+([^\n]+)#ms", function ($m) use (&$names) {
                            $names[] = $m[1];
                        }, $info);

                        return $names;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total due at booking\s+([^\n]+)#", $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Total due at booking\s+([^\n]+)#", $this->text()));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Airfare taxes and fees\s+([^\n]+)#i", $this->text()));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(preg_replace("/(\w+\s+\d+),\s+(\d{4})\s+(\d+:\d+\s+[AP]M)/", "$1 $2, $3", re("/reservation was made on\s+\w+,\s+(\w+\s+\d+,\s+\d{4}\s+\d+:\d+\s+[AP]M)/", $this->text())));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $text = text(xpath("td[4]"));

                            return [
                                'AirlineName'   => re("#^(.*?)\s+(\d+)\s+([^|]+)\s*\|\s*([^\n\|]+)#ms", $text),
                                'FlightNumber'  => re(2),
                                'Cabin'         => trim(re(3)),
                                'Aircraft'      => re(4),
                                'TraveledMiles' => re("#([\d.,]+\s*mi\b)#i", $text),
                                'Duration'      => re("#(\d+\s*hr\s*\d+\s*min)#ims", $text),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#", text(xpath("following-sibling::tr[1]/td[2]")));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $year = re('#\d{4}#i', $this->parser->getHeader('date'));

                            if (!$year) {
                                return null;
                            }
                            $index = re("#Stop|Arrive#", node("following-sibling::tr[3]/td[1]")) ? 4 : 3;
                            $anchorDate = re("#(?:reservation was made on|Itinerary for .*?,)\s+([^\n]+)#", $this->text());

                            $date = uberDate(text(xpath("preceding-sibling::tr[contains(.,'Leave') or contains(., 'Return') or (contains(., 'Flight ') and not(contains(., 'Operated')))][1]/td[2]")));

                            if (empty($date)) {
                                $date = $this->date;
                            } else {
                                $this->date = $date;
                            }
                            $dep = $date . ' ' . $year . ', ' . nice(uberTime(text(xpath("following-sibling::tr[1]/td[1]"))));
                            $arr = $date . ' ' . $year . ', ' . nice(uberTime(text(xpath("following-sibling::tr[$index]/td[1]"))));
                            //correctDates($dep, $arr, $anchorDate);

                            return [
                                'DepDate' => totime($dep),
                                'ArrDate' => totime($arr),
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $index = re("#Stop|Arrive#", text(xpath("following-sibling::tr[3]/td[1]"))) ? 4 : 3;
                            $seat = orval(
                                re("/Seats:\s*(\d+[-\s]*\w+)/", text(xpath("following-sibling::tr[" . ($index + 1) . "]/td[1]"))),
                                re("/Seats:\s*(\d+-*\w+)/")
                            );

                            return [
                                'ArrCode' => re("#\(([A-Z]{3})\)#", text(xpath("following-sibling::tr[$index]/td[2]"))),
                                'Seats'   => $seat ? [preg_replace('/[-\s]+/', '', $seat)] : null,
                            ];
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Meal\s*\(if\s*available\)\s*:\s*([^\n]+)#", $this->text());
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
