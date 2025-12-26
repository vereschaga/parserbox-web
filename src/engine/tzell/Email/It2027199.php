<?php

namespace AwardWallet\Engine\tzell\Email;

class It2027199 extends \TAccountCheckerExtended
{
    public $mailFiles = "tzell/it-2027199.eml";

    public $reBody = "tzell";
    public $reBody2 = "Here's your itinerary as discussed";

    public $reFrom = "@TZELL.COM";
    public $reSubject = "Travel Itinerary";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("text/plain", "plain");

                    return splitter("#(\d+[A-Za-z]+\d+\s+\d+:\d+[ap]m\s+\w+\s*\n\s*Air)#i", $text);
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#.*?\s+locator\s*:\s*(\w+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Status\s*:\s*(\w+)#");
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#Fare\s*:\s*.+#", $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#Fare\s*:\s*.+#", $this->text()));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (re("#Air\s+(.*?)\s+Flight\#\s+(\d+)\s+Class:(\w)\s+Seat:(\w+)#")) {
                                return [
                                    'AirlineName'  => re(1),
                                    'FlightNumber' => re(2),
                                    'BookingClass' => re(3),
                                    'Seats'        => re(4),
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            if (re("#From\s*:\s*(.*?)\s*(\d+)([a-zA-Z]+)(\d+)\s+(\d+:\d+[ap]m)#i")) {
                                return [
                                    "DepCode" => TRIP_CODE_UNKNOWN,
                                    'DepName' => re(1),
                                    'DepDate' => strtotime(re(2) . ' ' . re(3) . ' ' . re(4) . ', ' . re(5)),
                                ];
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            if (re("#To\s*:\s*(.*?)\s*(\d+)([a-zA-Z]+)(\d+)\s+(\d+:\d+[ap]m)#i")) {
                                return [
                                    "ArrCode" => TRIP_CODE_UNKNOWN,
                                    'ArrName' => re(1),
                                    'ArrDate' => strtotime(re(2) . ' ' . re(3) . ' ' . re(4) . ', ' . re(5)),
                                ];
                            }
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            if (re("#Meal\s*:\s*(.*?)\s*Equip\s*:\s*(.*?)\s*Status\s*:\s*(\w+)#i")) {
                                return [
                                    "Meal"     => re(1),
                                    'Aircraft' => re(2),
                                ];
                            }
                        },
                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#Flight Duration\s*:\s*([^\n]+)#");
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false && strpos($headers["from"], $this->reFrom) !== false;
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
