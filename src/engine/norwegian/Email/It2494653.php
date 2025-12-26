<?php

namespace AwardWallet\Engine\norwegian\Email;

class It2494653 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*(?:From|Fra)\s*:[^\n]*?norwegian\.#i', 'us', ''],
        ['#\n[>\s*]*Från\s*:[^\n]*?norwegian\.#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]norwegian\.#i', 'us', ''],
    ];
    public $reProvider = [
        ['#@.]norwegian\.#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en,nl";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "10.03.2015, 15:13";
    public $crDate = "28.02.2015, 05:32";
    public $xPath = "";
    public $mailFiles = "norwegian/it-2494653.eml, norwegian/it-2834165.eml, norwegian/it-2998846.eml, norwegian/it-3129396.eml, norwegian/it-3130398.eml, norwegian/it-3185237.eml, norwegian/it-3445675.eml, norwegian/it-4264159.eml, norwegian/it-4373694.eml, norwegian/it-4414636.eml, norwegian/it-4443960.eml, norwegian/it-4534098.eml, norwegian/it-4584260.eml, norwegian/it-4675237.eml";
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
                        return re([
                            "#Booking reference\s*:\s*([A-Z\d\-]+)#",
                            "#DIN BOOKINGREFERANSE ER\s*:\s*([A-Z\d\-]+)#",
                            "#Bokningsreferens\s*:\s*([A-Z\d\-]+)#ms",
                            "#Bookingreference\s*:\s*([A-Z\d\-]+)#ms",
                            "#Bookingreferanse\s*:\s*([A-Z\d\-]+)#ms",
                            "#Número de reserva\s*:\s*([A-Z\d\-]+)#ms",
                            "#Bookingreferanse\s*:\s*([A-Z\d\-]+)#ms",
                            "#Varausnumero\s*:\s*([A-Z\d\-]+)#ms",
                        ]);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node("//*[contains(text(), 'Passagerer')]/ancestor::tr[2]/following-sibling::tr[1]"),
                            nodes("//*[normalize-space(text()) = 'Passengers']/ancestor-or-self::tr[2]/following-sibling::tr"),
                            nodes("//*[normalize-space(text())='Passasjerer']/ancestor::tr[2]/following-sibling::tr[1]//text()[string-length(normalize-space(.))>1]"),
                            nodes("//*[normalize-space(text())='Pasajeros']/ancestor::tr[2]/following-sibling::tr[1]//text()[string-length(normalize-space(.))>1]"),
                            re("#\n\s*Passasjerer\s+([A-Z/,. ]+)#"),
                            array_filter(explode("\n", trim(re("#\n\s*Passagerare\s+(.*?)Hotelltilbud#ms"))))
                        );
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Totalbeløp er\s+([^\n]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Takk for at du (.*?) hos Norwegian#ix"),
                            re("#Tak fordi du har (.*?) hos Norwegian#ix")
                        );
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $r = xpath("//*[
							normalize-space(text()) = 'Flight info' or
							normalize-space(text()) = 'Flyinfo' or
							normalize-space(text()) = 'Flyginformation' or 
							normalize-space(text()) = 'Flightinformasjon' or
							normalize-space(text()) = 'Información de vuelos' or
							normalize-space(text()) = 'Flight informasjon' or
							normalize-space(text()) = 'Fly information' or
							normalize-space(text()) = 'Info de vuelos' or
							normalize-space(text()) = 'Lennon tiedot'
						]/ancestor-or-self::tr[2]/following-sibling::tr[1]/td/table/tbody/tr[contains(., ':')]/td[1]");

                        if (!$r->length) { // no tbody
                            $r = xpath("//*[
								normalize-space(text()) = 'Flight info' or
								normalize-space(text()) = 'Flyinfo' or
								normalize-space(text()) = 'Flyginformation' or 
								normalize-space(text()) = 'Flightinformasjon' or
								normalize-space(text()) = 'Información de vuelos' or
								normalize-space(text()) = 'Flight informasjon' or
								normalize-space(text()) = 'Fly information' or
								normalize-space(text()) = 'Info de vuelos' or
								normalize-space(text()) = 'Lennon tiedot'
							]/ancestor-or-self::tr[2]/following-sibling::tr[1]/td/table/tr[contains(., ':')]/td[1]");
                        }

                        return $r;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#^([A-Z\d]{2}\s*\d+)#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*\d+:\d+\s+([^\n]+)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = orval(
                                preg_replace("#\.\s+|\s+\.#", " ", re([
                                    "#\d+\s*\.\s*\d+\s*\.\s*\d+#",
                                    "#\d+\s+\d+\s+\d{4}#",
                                    "#\d+\s+\w+\.?\s+\d{4}#u",
                                    "#\d{4}\s+\w+\.?\s+\d{1,2}#u",
                                ])),
                                uberDate(1)
                            );
                            $this->http->log('$text = ' . print_r($text, true));
                            $this->http->log('$date = ' . print_r($date, true));
                            $date = preg_replace([
                                "#^(\d{4})\s+([^\d\s]+)\.?\s+(\d+)$#",
                                "#^(\d+)\s+(\d+)\s+(\d{4})$#",
                            ],
                            [
                                "$3 $2 $1",
                                "$1.$2.$3",
                            ],
                            $date);
                            $time = ure("#(\d+:\d+)\s+#");
                            $date = strtotime(en($date) . ', ' . $time);

                            return $date;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return ure("#\n\s*\d+:\d+\s+([^\n]+)#", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = orval(
                                preg_replace("#\.\s+|\s+\.#", " ", re([
                                    "#\d+\s*\.\s*\d+\s*\.\s*\d+#",
                                    "#\d+\s+\d+\s+\d{4}#",
                                    "#\d{1,2}\s+\w+\.\s+\d{4}#u",
                                    "#\d{4}\s+\w+\.?\s+\d{1,2}#u",
                                ])),
                                uberDate(1)
                            );
                            $date = preg_replace([
                                "#^(\d{4})\s+([^\d\s]+)\.?\s+(\d{1,2})$#",
                                "#^(\d+)\s+(\d+)\s+(\d{4})$#",
                            ],
                            [
                                "$3 $2 $1",
                                "$1.$2.$3",
                            ],
                            $date);
                            $time = ure("#(\d+:\d+)\s+#", 2);
                            $date = strtotime(en($date) . ', ' . $time);

                            if ($date < $it['DepDate']) {
                                $date = strtotime('+1 day', $date);
                            }

                            return $date;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#^([^\n]+)#", xpath('td[3]'));
                        },
                    ],
                ],
            ],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = parent::ParsePlanEmail($parser);

        return $result;
    }

    public static function getEmailTypesCount()
    {
        return 6;
    }

    public static function getEmailLanguages()
    {
        return ["en", "nl", "sv", "es", "no", "fi"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
