<?php

namespace AwardWallet\Engine\tport\Email;

class It3284995 extends \TAccountCheckerExtended
{
    public $mailFiles = "tport/it-3284995.eml";

    private $detects = [
        'Thank you for submitting your flight details',
        'This itinerary has been brought to you by Travelport',
    ];

    private $prov = 'travelport';

    private $from = '@travelport.com';

    private $status = '';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
//                    $userEmail = strtolower(re("#\n\s*To\s*:[^\n]*?([^\s@\n]+@[\w\-.]+)#"));
//
//                    if (!$userEmail) {
//                        $userEmail = niceName(re("#\n\s*To\s*:\s*([^\n]+)#"));
//                    }
//
//                    if (!$userEmail) {
//                        $userEmail = strtolower(re("#([a-zA-Z0-9_.+-]+@[a-zA-Z0-9-.]+)#", $this->parser->getHeader("To")));
//                    }
//
//                    if (!$userEmail) {
//                        $userEmail = strtolower($this->parser->getHeader("To"));
//                    }
//
//                    if ($userEmail) {
//                        $this->parsedValue('userEmail', $userEmail);
//                    }

                    $html = $this->setDocument('application/pdf', 'text');

                    return splitter("#(\s\s*Flight\s+-\s+[^\n]+\(\w{2}\)\s+-\s+\d+\s)#", $html);
                },

                "#Flight\s*-#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\s+Confirmation Number:\s+([A-Z\d\-]+)#"),
                            re("#\n\s*Reservation Number[:\s]+([A-Z\d\-]+)#", $this->text())
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $res = explode(', ', trim(re("#\n\s*Seat\s*\n\s*Status\s*\n\s*Passenger\s*\n\s*[^\n]+\s*\n\s*[^\n]+\s*\n\s*([^\n]+)#")));

                        if (empty($res[0]) && preg_match('/Status\n\s+Passengers\n\s*Ticket Numbers \(E-tickets\)\n\s*(\w+)\s+\S+\n\s*(.+)\n\s*(\w+)/', $text, $m)) {
                            unset($res);
                            $this->status = $m[1];
                            $res['Passengers'][] = $m[2];
                            $res['TicketNumbers'][] = $m[3];
                        }

                        return $res;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $res = trim(re("#\n\s*Seat\s*\n\s*Status\s*\n\s*Passenger\s*\n\s*[^\n]+\s*\n\s*(\w+)#"));

                        if (empty($res) && !empty($this->status)) {
                            $res = $this->status;
                        }

                        return $res;
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

                        "DepartureTerminal" => function ($text = '', $node = null, $it = null) {
                            return re('/Depart:\s*\n.+\n.+?\s+\([A-Z]{3}\),\s+Terminal\s+([A-Z\d]{1,4})/');
                        },
                        "ArrivalTerminal" => function ($text = '', $node = null, $it = null) {
                            return re('/Arrive:\s*\n.+\n.+?\s+\([A-Z]{3}\),\s+Terminal\s+([A-Z\d]{1,4})/');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate();

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
                                'Cabin'        => trim(re("#\n\s*Class of Service\s*:\s*([^\n\(]+)#")),
                                'BookingClass' => re("#\n\s*Class of Service\s*:\s*[^\n\(]+\s*\(([A-Z])\)#"),
                            ];
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#\n\s*Seat\s*\n\s*Status\s*\n\s*Passenger\s*\n\s*([^\n\(]+)#"));
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Flying Time\s*:\s*([^\n]+)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Meal Service\s*:\s*([^\n]+)#");
                        },

                        "Smoking" => function ($text = '', $node = null, $it = null) {
                            return re("#Non-smoking#") ? false : null;
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#Non-stop#") ? 0 : null;
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() ?? $parser->getPlainBody();

        if (stripos($body, $this->prov) === false && stripos($body, 'Agustin Uzcanga') === false) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->from) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }
}
