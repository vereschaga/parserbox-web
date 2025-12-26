<?php

namespace AwardWallet\Engine\tzell\Email;

class It2004350 extends \TAccountCheckerExtended
{
    public $rePlain = "#ResFAX\(r\)\s+Copyright#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#RESFAX@TZELL.COM#i";
    public $reProvider = "#TZELL.COM#i";
    public $caseReference = "9085";
    public $xPath = "";
    public $mailFiles = "tzell/it-2004350.eml, tzell/it-2004356.eml, tzell/it-2016694.eml, tzell/it-2016700.eml, tzell/it-2016711.eml, tzell/it-2016715.eml, tzell/it-2016731.eml, tzell/it-2037868.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));
                    $xpath = '//text()[contains(., "Arrival:")]/ancestor::table[1] | //text()[contains(., "Check Out:")]/ancestor::table[1] | //text()[contains(., "Pick Up:")]/ancestor::table[1]';

                    $nodes = nodes($xpath);

                    if (empty($nodes)) {
                        $xpath = '//text()[contains(., "Arrival:")]/ancestor::tr[1] | //text()[contains(., "Check Out:")]/ancestor::tr[1] | //text()[contains(., "Pick Up:")]/ancestor::tr[1]';
                        $text = $this->setDocument("application/pdf", "simpletable");
                        $this->pdftable = true;
                        $this->traveller = node("//tr[1]/td[3]");

                        return xpath($xpath);
                    } else {
                        $this->traveller = node("//*[contains(text(), 'Booking locator')]/ancestor-or-self::table[1]//tr[1]/td[1]");
                        $this->pdftable = false;

                        return xpath($xpath);
                    }
                },

                ".//text()[contains(., 'Arrival:')]" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            //$node = node("./following-sibling::tr[4]");
                            $node = node('(./following-sibling::tr[contains(., "locator")])[1]');
                            $test = re("#locator\s*:\s*([A-Z0-9]+)#", $node);

                            if ($test == null) {
                                $node = node("./following-sibling::tr[3]");
                            }

                            return trim(re("#locator\s*:\s*([A-Z0-9]+)#", $node));
                        } else {
                            $node = node("./following-sibling::table[1]");
                            $node = re("#locator\s*:\s*([A-Z0-9]+)#", $node);

                            return trim($node);
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->traveller;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            $node = node('(./preceding-sibling::tr[contains(., "Status:")])[last()]');
                            $node = re("#Status\s*:\s*([^\n]+)#", $node);

                            return trim($node);
                        } else {
                            return re("#Status:\s*([^\n]+)#");
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("./ancestor-or-self::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if ($this->pdftable) {
                                $node = node('(./preceding-sibling::tr[contains(., "Flight#")])[last()]');
                                $node = re("#Flight\#\s*([0-9]+)#", $node);
                            } else {
                                $node = node(".//td[contains(text(), 'Flight#')]");
                                $node = re("#Flight\#([0-9]+)#", $node);
                            }

                            return trim($node);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            if ($this->pdftable) {
                                $node = node('(./preceding-sibling::tr[contains(., "From:")])[last()]');
                                $node = re("#From\s*:\s*([^\n]+)To:#", $node);
                            } else {
                                $node = node(".//table[1]//td[2]");
                                $node = re("#From\s*:\s*([^\n]+)Meal:#", $node);
                            }

                            return trim($node);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            if ($this->pdftable) {
                                $day = node('(./preceding-sibling::tr[contains(., "Meal:")])[last()]/td[3]');
                                $time = node('(./preceding-sibling::tr[contains(., "Equip")])[last()]/td[3]');
                                $date = $day . " " . $time;
                                $date = uberDatetime($date);

                                return strtotime($date, $this->date);
                            } else {
                                $node = node(".//table[1]//td[1]");
                                $day = re("#([0-9]+)([A-Za-z]+)#", $node) . " " . re(2);
                                $time = re("#[0-9]+[A-Za-z]+\s*([^\n]+)#");

                                return strtotime($day . ", " . $time, $this->date);
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            if ($this->pdftable) {
                                $node = node('(./preceding-sibling::tr[contains(., "From:")])[last()]');
                                $node = re("#To\s*:\s*([^\n]+)#", $node);
                            } else {
                                $node = node(".//table[1]//td[3]");
                                $node = re("#To\s*:\s*([^\n]+)Status:#", $node);
                            }

                            return trim($node);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            if ($this->pdftable) {
                                $day = node("./td[19]");
                                $time = node("./td[last()]");
                                $date = uberDatetime($day . " " . $time);

                                return totime($date);
                            } else {
                                $node = node(".//table[1]//td[2]");
                                $node = re("#Arrival\s*:\s*([^\n]+)\s*Stops#", $node);
                                $day = re("#([0-9]+)([A-Za-z]+)#", $node) . " " . re(2);
                                $time = re("#[0-9]+[A-Za-z]+\s*([^\n]+)#", $node);

                                return strtotime($day . ", " . $time, $this->date);
                            }
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            if ($this->pdftable) {
                                $node = node('(./preceding-sibling::tr[contains(., "Flight#")])[last()]');
                                $node = re("#Air([^\n]+)Flight\##", $node);
                            } else {
                                $node = node(".//table[1]//td[2]");
                                $node = re("#Air([^\n]+)From#", $node);
                            }

                            return trim($node);
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            if ($this->pdftable) {
                                $node = node('(./preceding-sibling::tr[contains(., "Equip")])[last()]');

                                $node = re("#Equip\s*([^\n]+)Status#", $node);
                                $node = trim(str_replace(":", "", $node));
                            } else {
                                $node = node(".//table[1]//td[2]");
                                $node = re("#Equip\s*:\s*([^\n]+)Arrival#", $node);
                            }

                            return trim($node);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            if ($this->pdftable) {
                                $node = node('(./preceding-sibling::tr[contains(., "Flight#")])[last()]');
                                $node = re("#Class:([^\n]+)#", $node);
                            } else {
                                $node = node(".//table[1]//td[3]");
                                $node = re("#Class:([^\n]+) Seat#", $node);

                                if ($node == null) {
                                    $node = re("#Class:([^\n]+)To#", $node);
                                }
                            }

                            return trim($node);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            if ($this->pdftable) {
                                $node = node('(./preceding-sibling::tr[contains(., "Seats:")])[last()]');
                                $test = re("#Seat:([^\n]+)#", $node);

                                if ($test == null) {
                                    $node = re("#Seats:([^\n]+)#", $node);
                                    $node = str_replace("Seats:", "", $node);
                                } else {
                                    $node = $test;
                                }
                            } else {
                                $node = node(".//table[1]//td[3]");
                                $node = re("#Seat:([^\n]+)To:#", $node);
                            }

                            return trim($node);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            if ($this->pdftable) {
                                $node = node('(./following-sibling::tr[contains(., "Flight Duration:")])[1]');
                            } else {
                                $node = node(".//table[3]");
                            }
                            $node = str_replace("Flight Duration:", "", $node);

                            return trim($node);
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            if ($this->pdftable) {
                                $node = node('(./preceding-sibling::tr[contains(., "Meal:")])[last()]');
                                $test = re("#Meal\s*:\s*([^\n]+)Seats#", $node);

                                if ($test == null) {
                                    $node = re("#Meal\s*:\s*([^\n]+)#", $node);
                                } else {
                                    $node = $test;
                                }

                                $node = str_replace("Seats:", "", $node);
                            } else {
                                $node = node(".//table[1]//td[2]");
                                $node = re("#Meal\s*:\s*([^\n]+)Equip#", $node);
                            }

                            return trim($node);
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $node = node(".//table[1]//td[2]");
                            $node = re("#Stops:\s*([^\n]+)#", $node);

                            return trim($node);
                        },
                    ],
                ],

                ".//text()[contains(., 'Pick Up:')]" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            $node = node('./following-sibling::tr[1]');

                            $node = re("#\s*([0-9A-Za-z]+)\s*Rate:#", $node);

                            return trim($node);
                        } else {
                            $node = node("./td[2]");
                            $node = re("#Confirmation\#\s*:\s*([^\n]+)#");

                            return trim($node);
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            $node = node('(./preceding-sibling::tr[contains(., "Car")])[last()]/td[19]');
                            $pickup = re("#Car([^\n]+)#", $node);
                            $rental = str_replace($pickup, "", $node);

                            return trim($pickup);
                        } else {
                            $node = node("./td[2]");
                            $node = re("#Pick Up\s*:\s*([^\n]+)#");

                            return trim($node);
                        }
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            $node = node("./following-sibling::tr[1]/td[3]");
                            $node = $node . " 00:00";
                            $node = uberDatetime($node);

                            return strtotime($node, $this->date);
                        } else {
                            $node = node(".//td[1]");
                            $date = re("#(\d+)(\w+)#", $node) . " " . re(2);

                            return strtotime($date, $this->date);
                        }
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            $node = node('./following-sibling::tr[2]/td[last()]');

                            return trim($node);
                        } else {
                            $node = node("./td[2]");
                            $node = re("#Drop Off\s*:\s*[0-9][0-9][A-Za-z]+ [A-Za-z]+ ([^\n]+)#");

                            return $node;
                        }
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            $day = node("./following-sibling::tr[2]/td[19]");
                            $time = node('(./following-sibling::tr[contains(., "Dropoff Time:")])[1]');
                            $time = re("#Dropoff Time:\s*([^\n]+)#", $time);
                            $date = uberDatetime($day . " " . $time);

                            return strtotime($date, $this->date);
                        } else {
                            $node = re("#Drop Off\s*:\s*([0-9][0-9][A-Za-z]+) [A-Za-z]+ #");
                            $node2 = node("./following-sibling::table");
                            $node2 = re("#Dropoff Time:\s*([^\n]+)#", $node2);
                            $node = $node . " " . $node2;

                            $date = re("#(\d+)(\w+)\s+(\d+:\d+)#", $node) . " " . re(2) . ", " . re(3);

                            return strtotime($date, $this->date);
                        }
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            $node = node('(./preceding-sibling::tr[contains(., "Car")])[last()]/td[19]');
                            $pickup = re("#Car([^\n]+)#", $node);
                            $rental = str_replace($pickup, "", $node);

                            return trim($rental);
                        } else {
                            $node = node(".//td[2]");
                            $node = re("#Car\s*([^\n]+) Pick Up#", $node);

                            return $node;
                        }
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            $node = node('(./preceding-sibling::tr[contains(., "Car")])[last()]/td[last()]');

                            return trim($node);
                        } else {
                            $node = node(".//td[3]");
                            $node = re("#Type:\s*([^\n]+) Rate#", $node);

                            return $node;
                        }
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return $this->traveller;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            $node = node('(./following-sibling::tr[contains(., "Approximate Price:")])[1]/td[last()]');

                            return [
                                'TotalCharge' => cost($node),
                                'Currency'    => currency(re("#[0-9.]+([^\n]+)#", $node)),
                            ];
                        } else {
                            $node = node(".//td[3]");
                            $node = re("#Approximate Price:\s*([^\n]+)#", $node);

                            return [
                                'TotalCharge' => cost($node),
                                'Currency'    => currency(re("#[0-9.]+([^\n]+)#", $node)),
                            ];
                        }
                    },
                ],

                "#.*#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            $node = node('(./preceding-sibling::tr[contains(., "Confirmation:")])[last()]');
                            $node = re("#Confirmation:\s*([^\n]+) Room#", $node);

                            if ($node == null) {
                                $node = re("#Confirmation:\s*([^\n]+)#", $node);
                            }

                            return trim($node);
                        } else {
                            $node = re("#Confirmation\#\s*:\s*([^\n]+)#");

                            return trim($node);
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            $node = node('(./preceding-sibling::tr[contains(., "Hotel")])[last()]/td[last()]');
                            $address = node('(./preceding-sibling::tr[contains(., "Hotel")])[last()]/following-sibling::tr[1]');
                            $address = str_replace("Phone:", "", $address);

                            return [
                                'HotelName'=> trim($node),
                                'Address'  => trim($address),
                            ];
                        } else {
                            $node = re("#Hotel\s*([^\n]+)#");
                            $address = re("#" . $node . "\s*([^\n]+)#");

                            return [
                                'HotelName'=> $node,
                                'Address'  => $address,
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            $node = node('./preceding-sibling::tr[3]/td[3]');
                            $node = uberDatetime($node . " 00:00");

                            return strtotime($node, $this->date);
                        } else {
                            $node = node("(.//td[1])[1]");
                            $date = re("#(\d+)(\w+)#", $node) . " " . re(2);

                            return strtotime($date, $this->date);
                        }
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            $node = re("#Check Out:\s*([^\n]+)#");
                            $node = uberDatetime($node . " 00:00");

                            return strtotime($node, $this->date);
                        } else {
                            $node = node("(.//td[2])[1]");
                            $node = re("#Check Out:\s*([0-9][0-9][A-Za-z]+)#", $node);
                            $date = re("#(\d+)(\w+)#", $node) . " " . re(2);

                            return strtotime($date, $this->date);
                        }
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Phone\s*:\s*([^\n]+)#");

                        return trim($node);
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Fax\s*:\s*([^\n]+)#");

                        return trim($node);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return $this->traveller;
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            $node = node('(./preceding-sibling::tr[contains(., "Number of Rooms:")])[last()]');

                            return re("#Number of Rooms:\s*([0-9])#", $node);
                        } else {
                            return re("#Number of Rooms:\s*([^\n]+)#");
                        }
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        if ($this->pdftable) {
                            $node = node('(./preceding-sibling::tr[contains(., "Rate:")])[last()]/td[last()]');

                            return $node;
                        } else {
                            $node = re("#Rate\s*:\s*([^\n]+)#");

                            return trim($node);
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $node = re("#(Cancel [^\n]+)#");

                        return trim($node);
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it, true);
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
}
