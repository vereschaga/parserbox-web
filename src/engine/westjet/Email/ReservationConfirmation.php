<?php

namespace AwardWallet\Engine\westjet\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank you for choosing WestJet. Please read these important details carefully regarding your purchase and itinerary.#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#noreply@itinerary.westjet.com#i";
    public $reProvider = "#itinerary.westjet.com#i";
    public $xPath = "";
    public $mailFiles = "westjet/it-1585664.eml, westjet/it-1585678.eml, westjet/it-1874768.eml, westjet/it-5344192.eml";
    public $pdfRequired = "";

    public $reSubject = [
        "Reservation Confirmation",
    ];
    public $reBody = 'www.westjet.com';
    public $reBody2 = [
        ["1-888-WESTJET", "Booking Confirmation"],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false && ($this->http->XPath->query("//img[contains(@src,'{$this->reBody}')]")->length < 1)) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re[0]) !== false && stripos($body, $re[1]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Your reservation code is:')]/ancestor::td[1]/following-sibling::td[1]";

                        return node($xpath);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $prefix = '//text()[normalize-space(.) = "Guest"]/ancestor::*/following-sibling::table[1]';
                        $xpath = $prefix . '//td[1]';
                        $passengers = array_values(array_filter(nodes($xpath)));
                        $xpath = $prefix . '//td[contains(., "Seat")]/following-sibling::td[1]';
                        $this->seats = [];

                        foreach (nodes($xpath) as $seats) {
                            $i = 0;

                            foreach (explode(';', $seats) as $seat) {
                                $seatParsed = re('#:\s+(.*)#', $seat);

                                if ($seatParsed != '*') {
                                    $this->seats[$i][] = $seatParsed;
                                }
                                $i++;
                            }
                        }

                        return $passengers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//table[preceding-sibling::*[normalize-space(.) = "Total"] and following-sibling::*[normalize-space(.) = "WestJet offers"]]//tr[last()]/td[last()]';

                        return total(node($xpath));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#Total airfare:(.*)#"));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#Total taxes:(.*)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//table[preceding-sibling::*[contains(., "Air Itinerary Details")] and following-sibling::*[contains(., "Fare breakdown")]]//tr';

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\d+#", node('.//td[1]'));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 2, 'Arr' => 3] as $key => $value) {
                                $subj = implode("\n", nodes('.//td[' . $value . ']//text()'));
                                $regex = '#';
                                $regex .= '(?P<Name>.*)\s+';
                                $regex .= '\w+\s+(?P<Day>\d+)\s+(?P<Month>\w+),\s+(?P<Year>\d+)\s+';
                                $regex .= '(?P<Time>\d{2}:\d{2}\s*(?:AM|PM)?)';
                                $regex .= '#i';

                                if (preg_match($regex, $subj, $m)) {
                                    $res[$key . 'Name'] = $m['Name'];
                                    $s = $m['Day'] . ' ' . $m['Month'] . ' ' . $m['Year'] . ', ' . $m['Time'];
                                    $res[$key . 'Date'] = strtotime($s);
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#^\s*([A-Z\d]{2})\s*\d+#", node('.//td[1]'));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            if (isset($this->segmentIndex)) {
                                $this->segmentIndex++;
                            } else {
                                $this->segmentIndex = 0;
                            }

                            if (isset($this->seats[$this->segmentIndex])) {
                                return join(', ', $this->seats[$this->segmentIndex]);
                            }
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $subj = node('.//td[4]');

                            if (stripos($subj, 'Non-stop') !== false) {
                                return 0;
                            }
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
}
