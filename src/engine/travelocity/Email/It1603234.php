<?php

namespace AwardWallet\Engine\travelocity\Email;

class It1603234 extends \TAccountCheckerExtended
{
    public $reFrom = "#travelocity#i";
    public $reProvider = "#travelocity#i";
    public $rePlain = "#\n[>\s*]*(?:Expéditeur|From)\s*:[^\n]*?travelocity#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "travelocity/it-1603234.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("//*[contains(text(), 'Depart')]/ancestor-or-self::td[1]");
                },

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
                },

                "//*[1]" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $text = node("ancestor::tr[1]/preceding-sibling::tr[contains(.,'use reference code')]");

                        return re("#reference code\s+([\dA-Z\-]+)\s+#", $text);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Passenger Name')]/ancestor-or-self::tr[1]/following-sibling::tr/td[1]");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total:\s*([^\n]+)#", $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Total:\s*([^\n]+)#", $this->text()));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes[\+\s]+Airline[^aA\n]+Agency\s+Fees\s*:\s+([^\n]+)#msi", $this->text()));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(clear("#\s+at\s+#", re("#\s+issued on\s+([^\n]+)#", $this->text()), ' '));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $info = glue(filter(nodes('following-sibling::td[2]//text()')), "\n");

                            return [
                                'AirlineName'  => re("#^(.*?)\s+Flight\s+(\d+)\s+#ms", $info),
                                'FlightNumber' => re(2),
                                'Aircraft'     => re("#\(\s*on\s+([^\)]+)\s*\)#", $info),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#", node('following-sibling::td[1]'));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+to\s+.*?\(([A-Z]{3})\)#", node('following-sibling::td[1]'));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = orval(
                                node('ancestor::tr[1]/preceding-sibling::tr[1]/td[1]'),
                                $this->cache('lastDate')
                            );

                            $dep = strtotime($date . ', ' . uberTime(node('.'), 1));
                            $arr = strtotime($date . ', ' . uberTime(node('.'), 2));

                            if ($dep > $arr) {
                                $arr = strtotime('+1 day', $arr);
                            }

                            $this->cache('lastDate', date('Y-m-d', $arr));

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
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
