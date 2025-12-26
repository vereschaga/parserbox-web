<?php

namespace AwardWallet\Engine\hollandamerica\Email;

class It1567285 extends \TAccountCheckerExtended
{
    public $reFrom = "#hollandamerica#i";
    public $reProvider = "#hollandamerica#i";
    public $rePlain = "#\n[>\s]*From\s*:[^\n]*?hollandamerica#i";
    public $mailFiles = "hollandamerica/it-1567285.eml, hollandamerica/it-1568997.eml";

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $body = $parser->getAttachmentBody($pdf);

            if (($html = \PDF::convertToText($body)) !== null) {
                if (stripos($html, 'Holland America Line') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->getDocument("application/pdf", "text");

                    if (!re("#Holland\s+America\s+Line#i", $text)) {
                        return [];
                    }

                    $this->setDocument("application/pdf", "simpletable");

                    return [
                        xPath("//*[contains(text(), 'Cruise Schedule')]/ancestor::tr[1]")->item(0),
                        xPath("//*[contains(text(), 'Carrier Name')]/ancestor::tr[1]")->item(0),
                    ];
                },

                ".//*[contains(., 'Cruise')]" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "C";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $text = glue(filter(nodes("//*[contains(text(), 'Booking #')]/ancestor-or-self::tr[1]/following-sibling::tr[1]/td")), "\n");

                        return [
                            'RecordLocator' => trim(re("#\n\s*[\d\w\-]{4,}\n#", $text)),
                            'BaseFare'      => cost(re("#Cruise/Tour\s*Fare\s+([\d.,]+)#", $text)),
                        ];
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        //return re("#Taxes/Fees\s+([\d.,]+)#", node("//tr[contains(., 'Taxes/Fees')]"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        //return nodes("//tr[contains(., 'Currency:')]/following-sibling::tr[1]/td[last()]");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $text = glue(filter(nodes("//tr[contains(., 'Carrier Name')]/preceding-sibling::tr[1]//text()")), "\n");
                        $names = [];

                        re("#\n\s*\d+\s+([^\n]+)#", function ($m) use (&$names) {
                            $names[] = $m[1];
                        }, "\n" . $text);

                        return $names;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $rows = xPath("//*[contains(text(), 'From Port')]/ancestor-or-self::tr[1]/following-sibling::tr");
                        $r = [];

                        foreach ($rows as $row) {
                            if (node("td[contains(.,'Mail Payments')]", $row)) {
                                break;
                            }

                            $res = filter(nodes('td[text()]', $row));

                            $r[] = reset($res);
                            $r[] = next($res);
                        }

                        return $r;
                    },

                    "TripSegments" => [
                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return $text;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = reset(filter(nodes("//*[contains(text(), 'From Port')]/ancestor-or-self::tr[1]/preceding-sibling::tr[1]/td")));
                            $date = re("#\d+\w{3}\d+#", $date);

                            return [
                                'DepDate' => strtotime($date),
                                'ArrDate' => strtotime($date),
                            ];
                        },
                    ],

                    "ShipName" => function ($text = '', $node = null, $it = null) {
                        $r = filter(nodes("//tr[contains(., 'Departure')]/preceding-sibling::tr[not(contains(., 'Taxes/Fees'))][1]/td[contains(., '/')]"));

                        return reset($r);
                    },

                    "CruiseName" => function ($text = '', $node = null, $it = null) {
                        return reset(filter(nodes("//tr[contains(., 'Departure')]/preceding-sibling::tr[not(contains(., 'Taxes/Fees'))][1]/td")));
                    },

                    "RoomNumber" => function ($text = '', $node = null, $it = null) {
                        $r = filter(nodes("//tr[contains(., 'From Port')]/preceding-sibling::tr[1]/td"));
                        array_shift($r);

                        return [
                            'RoomClass'  => re("#(\w+)\s*(\d+)#", reset($r)),
                            'RoomNumber' => re(2),
                        ];
                    },
                ],

                ".//*[contains(., 'Carrier')]" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $text = glue(filter(nodes("//tr[contains(., 'Carrier Name')]/preceding-sibling::tr[1]//text()")), "\n");
                        $names = [];

                        re("#\n\s*\d+\s+([^\n]+)#", function ($m) use (&$names) {
                            $names[] = $m[1];
                        }, "\n" . $text);

                    //return $names;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xPath("//tr[contains(., 'Carrier Name')]/following-sibling::tr[not(contains(.,'Operated By')) and contains(.,'m')]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $row = glue(filter(nodes("td")), "\n");
                            re("#([^\n]*?)\s+(\d+)\s+(.*?)\s+(\d+[A-Z]{3}\d+)\s+(\d+:\d+\s*\w+)\s+([^\n]+)\s+(\d+[A-Z]{3}\d+)\s+(\d+:\d+\s*\w+)(\s+[\d\w\-]+)*#", $row);
                            //print $row."\n\n";
                            return [
                                'AirlineName'  => re(1),
                                'FlightNumber' => re(2),
                                'DepName'      => re(3),
                                'DepDate'      => strtotime(re(4) . ', ' . re(5)),
                                'ArrName'      => re(6),
                                'ArrDate'      => strtotime(re(7) . ', ' . re(8)),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },
                    ],

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return node("//tr[contains(., 'Carrier Name')]/following-sibling::tr[1]/td[last()]");
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    //return correctItinerary($it);
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
