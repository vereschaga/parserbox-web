<?php

namespace AwardWallet\Engine\orbitz\Email;

class It1545676 extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-1545676.eml";

    public $reFrom = "#orbitz#i";
    public $reProvider = "#orbitz#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?orbitz#i";
    public $typesCount = "1";
    public $langSupported = "es";
    public $reSubject = "";
    public $reHtml = "#The Orbitz Travel Team#";
    public $xPath = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("
						//*[contains(text(), 'Salida') and not(contains(., 'Registro')) and not(contains(., 'Alert'))]/ancestor-or-self::tr[1][not(contains(.,'Tiempo'))]
					");
                },

                "#Hotel Information\s*(?!No)#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
                },

                "#Salida#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("td[4]"));
                        $airline = re("#^(.*?)\s+(\d+)\s+(\w+)\s*\|\s*([^\n\|]+)#ms", $text);
                        //print $airline."\n";
                        return orval(
                            re("#$airline\s+localizador de registro[\s:.]+([\d\w\-]+)#ims", $this->text()),
                            re("#Orbitz\s+localizador de registro[\s:.]+([\d\w\-]+)#i", $this->text())
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//*[contains(text(), 'Información del viajero')]/ancestor-or-self::table[1]"));
                        $names = [];

                        re("#\n\s*Pasajero\s+\d+\s+([^\n]+)#ms", function ($m) use (&$names) {
                            $names[] = $m[1];
                        }, $info);

                        return $names;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Importe total del viaje\s+([^\n]+)#", $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Importe total del viaje\s+([^\n]+)#", $this->text()));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = clear("#\s+de\s+#", re("#reserva se realizó el\s+[^\d]+\s+([^\n]+)#", $this->text()), ' ');
                        $date = clear("#\.#", $date);
                        $date = en($date);

                        $this->cache('reservationDate', $date);

                        return totime($date);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $text = text(xpath("td[4]"));

                            return [
                                'AirlineName'   => re("#^(.*?)\s+(\d+)\s+(\w+)\s*\|\s*([^\n\|]+)#ms", $text),
                                'FlightNumber'  => re(2),
                                'Cabin'         => re(3),
                                'Aircraft'      => re(4),
                                'TraveledMiles' => re("#([\d.,]+\s*mi\b)#i", $text),
                                'Duration'      => re("#(\d+\s*hr\s*\d+\s*min)#ims", $text),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#", text(xpath("following-sibling::tr[1]/td[2]")));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $index = re("#Stop#", node("following-sibling::tr[3]/td[1]")) ? 4 : 3;
                            $anchorDate = strtotime($this->cache('reservationDate'));

                            $date = en(uberDate(clear("#(^[^\d]+|\s+de\s+)#", text(xpath("preceding-sibling::tr[contains(.,'Salida')]")), ' ')), 'es');

                            $dep = $date . ', ' . uberTime(text(xpath("following-sibling::tr[1]/td[1]")));
                            $arr = $date . ', ' . uberTime(text(xpath("following-sibling::tr[$index]/td[1]")));

                            correctDates($dep, $arr, $anchorDate);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $index = re("#Stop#", text(xpath("following-sibling::tr[3]/td[1]"))) ? 4 : 3;

                            return [
                                'ArrCode' => re("#\(([A-Z]{3})\)#", text(xpath("following-sibling::tr[$index]/td[2]"))),
                                'Seats'   => orval(
                                    re("#Plazas:\s*(\d+[\-\s]*\w+)#", text(xpath("following-sibling::tr[" . ($index + 1) . "]/td[1]"))),
                                    re("#Plazas:\s*(\d+-*\w+)#")
                                ),
                            ];
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\sComida\s*\(si\s*disponible\)[.:\s]+([^\n]+)#i", $this->text());
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
        return ["es"];
    }
}
