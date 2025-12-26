<?php

namespace AwardWallet\Engine\jetstar\Email;

class It2583145 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*(?:From|Von)\s*:[^\n]*?jetstar\.com#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]jetstar#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]jetstar#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "23.03.2015, 19:00";
    public $crDate = "23.03.2015, 18:43";
    public $xPath = "";
    public $mailFiles = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->parser->getHtmlBody();
                    $texts = implode("\n", $this->parser->getRawBody());
                    $posBegin1 = stripos($texts, "Content-Type: text/html");
                    $i = 0;

                    while ($posBegin1 !== false && $i < 30) {
                        $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                        $posEnd = stripos($texts, "\n\n", $posBegin);

                        if (preg_match("#filename=.*\.htm.*base64#s", substr($texts, $posBegin1, $posBegin - $posBegin1))) {
                            $t = substr($texts, $posBegin, $posEnd - $posBegin);
                            $text .= base64_decode($t);
                        }
                        $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                        $i++;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Booking\s+Reference\s+([A-Z\d-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = nodes("//*[contains(text(), 'Passenger')]/ancestor::tr[1][contains(., 'Seat')]/following-sibling::tr/td[1]");
                        $numbers = [];

                        foreach ($names as &$name) {
                            $number = re("#number\s+(\d+)$#", $name);

                            if ($number) {
                                $numbers[] = $number;
                            }
                            $name = clear("#Qantas.+#i", $name);
                        }

                        return [
                            "Passengers"     => $names,
                            "AccountNumbers" => implode(',', $numbers),
                        ];
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\s+Booking\s+Date\s*:\s*([^\n]*?)\s{2,}#i"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Departing')]/ancestor::tr[1][contains(., 'Arriving')]/following-sibling::tr");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node("td[2]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $info = text(xpath("following::table[1]//text()[contains(., 'Flight')]/ancestor::tr[1]/td[2]"));

                            return [
                                'DepName' => re("#\n\s*([^\n]+)$#", $info),
                                'DepDate' => totime(uberDateTime($info)),
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $info = text(xpath("following::table[1]//text()[contains(., 'Flight')]/ancestor::tr[1]/td[3]"));

                            return [
                                'ArrName' => re("#\n\s*([^\n]+)$#", $info),
                                'ArrDate' => totime(uberDateTime($info)),
                            ];
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $info = text(xpath("following::table[1]//text()[contains(., 'Flight')]/ancestor::tr[1]/td[1]"));

                            return [
                                'Aircraft' => re("#^([^\n]+)#", $info),
                                'Duration' => re("#\n\s*Flight\s*Duration[:\s]+([^\n]+)#", $info),
                            ];
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seats = nodes("//*[contains(text(), 'Passenger')]/ancestor::tr[1][contains(., 'Seat')]/following-sibling::tr/td[2]");

                            foreach ($seats as &$seat) {
                                $seat = clear("#[\(\)]#", $seat);
                            }

                            return implode(',', $seats);
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
