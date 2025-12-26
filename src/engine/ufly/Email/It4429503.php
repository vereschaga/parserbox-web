<?php

namespace AwardWallet\Engine\ufly\Email;

class It4429503 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Sun Country Reservations#im', 'us', ''],
    ];
    public $reHtml = '#Sun Country Reservations#im';
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#@suncountry\.#i', 'us', ''],
    ];
    public $reProvider = [
        ['#@suncountry\.#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "ufly/it-4410300.eml, ufly/it-4429503.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },
                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return node('//*[contains(normalize-space(text()), "Confirmation Code:")]/span');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return implode(",", array_unique(nodes('//span[contains(., "Passenger Information")]/..//td[@width="200"]')));
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        return array_unique(node("//td[contains(text(),'Ticket(s)')]/span/text()"));
                    },
                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $segments = splitter("#(AIRFARE\:.+?Flight Number.+?)\s#s", re('#Flight Itinerary(.*)Passenger Information#s'));

                        foreach (nodes("(//span[contains(text(), 'Passenger Information')]/ancestor-or-self::tr//tr[not(th) and not(td[@colspan='16'])])/td[10]") as $i => $v) {
                            if (isset($segments[$i])) {
                                $segments[$i] .= 'ParsedSeats:' . $v;
                            }
                        }

                        return $segments;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return reni('Flight Numbe .*? (\d+)');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            if (preg_match("#AIRFARE\:\s+(?<DepName>.+?),.+?\((?<DepCode>[A-Z]{3})\)\s+?to\s+(?<ArrName>.+?),.+?\((?<ArrCode>[A-Z]{3})\)#", $text, $m)) {
                                $r = [
                                    'DepCode' => $m['DepCode'],
                                    'ArrCode' => $m['ArrCode'],
                                    'DepName' => $m['DepName'],
                                    'ArrName' => $m['ArrName'],
                                ];

                                if (preg_match("#AIRFARE\:.+?\sto\s.+?(Terminal)\s*(?<Term>\S+).+?#i", $text, $m)) {
                                    $r['ArrivalTerminal'] = $m['Term'];
                                }

                                if (preg_match("#AIRFARE\:.+?(Terminal)\s*(?<Term>\S+).+?\sto\s#i", $text, $m)) {
                                    $r['DepartureTerminal'] = $m['Term'];
                                }

                                return $r;
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dt1 = strtotime(uberDateTime());
                            $date2 = uberDate();
                            $time2 = uberTime($text, 2);
                            $dt2 = strtotime($date2 . ' ' . $time2);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return reni('Flight Number: \b(\S{2})\b');
                        },
                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return reni('Flight Number:\s+\b\S{2}\b\s+\d+\s+	(\S+)');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return reni('ParsedSeats:(.+)');
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
