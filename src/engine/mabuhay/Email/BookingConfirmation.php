<?php

namespace AwardWallet\Engine\mabuhay\Email;

class BookingConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = "mabuhay/it-1780637.eml, mabuhay/it-1782471.eml, mabuhay/it-7017226.eml, mabuhay/it-7021474.eml, mabuhay/it-7073979.eml, mabuhay/it-8383447.eml";

    private $detectBody = [
        "#Thank\s+you\s+for\s+choosing\s+PHILIPPINE\s+AIRLINES#i",
    ];
    private $seats;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Reference:\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//text()[normalize-space(.) = "Passengers"]/ancestor::*/following-sibling::table[1]//tr/td[position() = 3 and string-length(normalize-space(.)) > 1 and contains(.,\'Seat\')]/following-sibling::td[1]';
                        $seats = nodes($xpath);

                        foreach ($seats as $s) {
                            $this->seats[] = explode(',', $s);
                        }

                        if (count($this->seats) === 1) {
                            $b = array_shift($this->seats);
                            $this->seats = [];

                            foreach ($b as $s) {
                                $this->seats[] = [$s];
                            }
                        } elseif (count($this->seats) > 1) {
                            array_unshift($this->seats, null);
                            $this->seats = call_user_func_array("array_map", $this->seats);
                        }
                        $xpath = '//text()[normalize-space(.) = "Passengers"]/ancestor::*/following-sibling::table[1]//tr/td[position() = 1 and string-length(normalize-space(.)) > 1]';

                        return nodes($xpath);
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//text()[normalize-space(.) = "Passengers"]/ancestor::*/following-sibling::table[1]//tr/td[position() = 3 and string-length(normalize-space(.)) > 1 and contains(.,\'Ticket\')]/following-sibling::td[1]';

                        return array_values(array_unique(array_filter(nodes($xpath, null, '#^\s*\d{5,}\s*$#'))));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(str_replace(',', '', re('#Total\s+air\s+fare:\s+(.*)#i')));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//text()[normalize-space(.) = "Air Itinerary Details"]/ancestor::*/following-sibling::table[contains(., "Flying Time:")]//tr[contains(., "Flying Time")]';

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./td[3]');
                            $seat = !empty($this->seats) ? array_shift($this->seats) : [];

                            if (preg_match('#(\w{2})\s*(\d+)#', $subj, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                    'Seats'        => $seat,
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 1, 'Arr' => 2] as $key => $value) {
                                $subj = node('./td[' . $value . ']');
                                $regex = '#^';
                                $regex .= '(?P<Name>.*)\((?P<Code>\w+)\),\s+';
                                $regex .= '.*\s*';
                                $regex .= '\w+,\s+(?P<Datetime>\d+\s+\w+\s+\d+,\s+\d+:\d+)\s*';
                                $regex .= '(?:\s*Terminal:\s+(?P<Terminal>.*))?';
                                $regex .= '$#';

                                if (preg_match($regex, $subj, $m)) {
                                    $res[$key . 'Code'] = $m['Code'];
                                    $res[$key . 'Name'] = $m['Name'];

                                    if (isset($m['Terminal'])) {
                                        $res[['Dep' => 'Departure', 'Arr' => 'Arrival'][$key] . 'Terminal'] = $m['Terminal'];
                                    }
                                    $res[$key . 'Date'] = strtotime($m['Datetime']);
                                }
                            }

                            return $res;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node('.//tr[2] | ./following-sibling::tr[1]');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Fare\s+Family:\s+(.*?)Fare#', node('./td[4]'));
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re('#Booking\s+Class:\s+(\w)#i');
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re('#Flying\s+Time:\s+(.*)#i');
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re('#Stops:\s+\((\d+)\)#');
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'philippineairlines.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'philippineairlines.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() ? $parser->getHTMLBody() : $parser->getPlainBody();

        foreach ($this->detectBody as $re) {
            if (preg_match($re, $body)) {
                return true;
            }
        }

        return false;
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
