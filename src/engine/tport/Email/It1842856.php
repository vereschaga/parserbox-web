<?php

namespace AwardWallet\Engine\tport\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;

class It1842856 extends \TAccountCheckerExtended
{
    public $mailFiles = "tport/it-1842856.eml, tport/it-5935768.eml";

    public $reBody = 'Travelport ViewTrip';
    public $reBody2 = [
        'en' => 'Itinerary Information',
    ];
    private $anchorDate;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
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

                    //TODO: go to parse ItineraryDetailed.php maybe it better merge parsers
                    if (xpath("//img[contains(@src, 'wlAir3.gif') or contains(@src, 'wlPkg.gif')]/ancestor::table[2]")->length > 0) {
                        return null;
                    }
                    $this->anchorDate = strtotime($this->http->FindSingleNode('//text()[starts-with(normalize-space(),"Today\'s Date:")]', null, false, "/Today's Date: (.+)/"));
                    $html = $this->setDocument("text/html", "html");

                    return xpath("//img[contains(@src, 'wlAir3.gif') or contains(@src, 'wlPkg.gif')]/ancestor::table[2]");
                },

                "#Flight\s*-#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('/\s+Confirmation\s+Number[:\s]+([A-Z\d]{5,7})/i'),
                            re('/\n\s*Reservation\s+Number[:\s]+([A-Z\d]{5,7})/i', $this->text())
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes('//table//tr[.//img and (contains(.,"Travellers") or contains(.,"Travelers")) and position()=1]/following-sibling::tr[normalize-space(.)!=""][1]//tr[not(.//tr)]//*[(name()="b" or name()="strong") and normalize-space(.)!=""][1]');
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $r = nodes("//*[contains(text(), 'Frequent Traveler')]/ancestor::tr[1]/following-sibling::tr[contains(., ' ')]/td//table/tr/td[1]");
                        $res = [];

                        foreach ($r as $item) {
                            $res[$item] = 1;
                        }

                        return array_keys($res);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return node("(//*[contains(text(), 'Passenger')]/ancestor::tr[1][contains(., 'Seat')]/following-sibling::tr//table//td[2])[1]");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\(([A-Z\d]{2})\)\s*\-\s*(\d+)#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Depart:.*?\(([A-Z]{3})\)#ms");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate();

                            if (!empty($this->anchorDate)) {
                                $date = date('m/d/Y', EmailDateHelper::parseDateRelative($date, $this->anchorDate));
                            }

                            $dep = $date . ',' . uberTime();
                            $arr = $date . ',' . uberTime(2);

                            correctDates($dep, $arr, $date);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Arrive:.*?\(([A-Z]{3})\)#ms");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Equipment\s*:\s*([^\n\t]+)#msi");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Cabin'        => re("#\n\s*Class of Service\s*:\s*(.*?)\s*\(([A-Z])\)#"),
                                'BookingClass' => re(2),
                            ];
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seats = nodes("//*[contains(text(), 'Passenger')]/ancestor::tr[1][contains(., 'Seat')]/following-sibling::tr//table//td[1]");
                            $res = [];

                            foreach ($seats as &$seat) {
                                $seat = re("#^(\d+[A-Z]+)#", $seat);

                                if ($seat) {
                                    $res[$seat] = 1;
                                }
                            }

                            return implode(',', array_keys($res));
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Flying Time\s*:\s*([^\n]+)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Meal Service\s*:\s*([^\n]+)#");
                        },

                        "Smoking" => function ($text = '', $node = null, $it = null) {
                            return re('/In-Flight\s+Services:[^:]*Non-smoking/i') ? false : null;
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re('/Flight\s+\d+\s+Non-stop\s*\n/i') ? 0 : null;
                        },
                    ],
                ],

                "#Tour\s*\-#" => [
                    "ConfNo" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reservation Number[:\s]+([A-Z\d\-]+)#", $this->text());
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "E";
                    },

                    "DinerName" => function ($text = '', $node = null, $it = null) {
                        $pax = nodes('//table//tr[.//img and (contains(.,"Travellers") or contains(.,"Travelers")) and position()=1]/following-sibling::tr[normalize-space(.)!=""][1]//tr[not(.//tr)]//*[(name()="b" or name()="strong") and normalize-space(.)!=""][1]');

                        if (count($pax) > 0) {
                            return array_shift($pax);
                        }

                        return [];
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Location\s*:\s*(.*?\s+\([A-Z]{3}\))#");
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Departure Date\s*:\s*([^\n]+)#"));
                    },

                    "Name" => function ($text = '', $node = null, $it = null) {
                        return re("#(Tour\s+\([A-Z]{2}\))#");
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
                },
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'viewtrip-admin@travelport.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty(trim($parser->getHTMLBody())) !== true) {
            $body = text($parser->getHTMLBody());
        } else {
            $body = text($parser->getPlainBody());
        }
        $body = str_replace(["\n", '  '], ' ', $body);

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@travelport.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }
}
