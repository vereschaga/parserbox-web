<?php

namespace AwardWallet\Engine\ryanair\Email;

class ReservationConfirmationPolish extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Dziękujemy za dokonanie rezerwacji biletów w liniach lotniczych Ryanair', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['nerary@ryanair.com', 'blank', ''],
    ];
    public $reProvider = [
        ['@ryanair.com', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "pl	";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "08.07.2016, 11:54";
    public $crDate = "08.07.2016, 09:54";
    public $xPath = "";
    public $mailFiles = "";
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
                        return re('#Nr rezerwacji:\s+(\w+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes('//tr[normalize-space(.) = "PASAŻER(-OWIE):"]/following-sibling::tr[1]//b');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Łącznie zapłacono', +1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Status:\s+(.*)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//img[contains(@src, "in.png") or contains(@src, "out.png")]/ancestor::table[1]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (re('#\s+(\w{2})(\d+)#i')) {
                                return [
                                    'AirlineName'  => re(1),
                                    'FlightNumber' => re(2),
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re('#WYLOT:\s*.*?\((\w{3})\)#', node('./following-sibling::table[1]'));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $d = re('#WYLOT:\s*.*?DATA:(\d+\s+\w+\s+\d+)#', node('./following-sibling::table[1]'));
                            $t = re('#WYLOT:\s*.*?GODZINA:(\d+:\d+)#', node('./following-sibling::table[1]'));

                            return strtotime($d . ', ' . $t);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re('#PRZYLOT:\s*.*?\((\w{3})\)#', node('./following-sibling::table[1]'));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $d = re('#PRZYLOT:\s*.*?DATA:(\d+\s+\w+\s+\d+)#', node('./following-sibling::table[1]'));
                            $t = re('#PRZYLOT:\s*.*?GODZINA:(\d+:\d+)#', node('./following-sibling::table[1]'));

                            return strtotime($d . ', ' . $t);
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
        return ["pl"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
