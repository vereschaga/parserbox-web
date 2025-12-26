<?php

namespace AwardWallet\Engine\tamair\Email;

class It2123255 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#SAC\s+\w+\s+BRASIL#i', 'us', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#@tam[.]com[.]br#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]tam[.]com[.]br#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "20.05.2015, 09:07";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "tamair/it-2123255.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // disable text parser for html emails
                    if (nodes("//*[contains(text(), 'Saída/Chegada')]/ancestor::tr[1]/following-sibling::tr")) {
                        return null;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re_white('LOC \(Localizador da reserva\)  (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('
							Nome do Passageiro
							(.+?)
							(?: LOC \( | Número do bilhete)
						');

                        return [nice($name)];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('
							(?:Valor)? Total
							(.+? [\d.,]+)
						');

                        return total($x);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $x = between('Valor Tarifas', 'Taxa de embarque');

                        return cost($x);
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $x = between('Taxa de embarque', 'Total');

                        return cost($x);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $dt = between('Data de emissão', 'LOC');
                        $dt = uberDateTime($dt);

                        return strtotime($dt);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        // flight number ... dep code
                        $q = white('\w+ \s+ \d+ \s+ \w+ \s+ \w+ \s+ -');

                        return splitter("/($q)/isu");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $q = white('
								(?P<AirlineName> \w+) \s+ (?P<FlightNumber> \w+) \s+
								(?P<BookingClass> \w+) \s+
								(?P<DepCode> \w+) -
								.+?
								(?P<ArrCode> \w+) -
							');

                            $res = re2dict($q, $text);

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re_white('(\d+ / \d+ / \d{4})');
                            $time1 = uberTime(1);
                            $time2 = uberTime(2);

                            $date = timestamp_from_format($date, 'd/m/Y');
                            $dt1 = strtotime($time1, $date);
                            $dt2 = strtotime($time2, $date);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
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
        return ["pt"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
