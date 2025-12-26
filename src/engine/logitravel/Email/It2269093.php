<?php

namespace AwardWallet\Engine\logitravel\Email;

class It2269093 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Logitravel\s+le\s+informa#i', 'blank', '2000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]logitravel[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]logitravel[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "es";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "04.02.2015, 10:37";
    public $crDate = "27.01.2015, 10:21";
    public $xPath = "";
    public $mailFiles = "logitravel/it-2269093.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $date = rew('Salida: (.+? \d{4})');
                    $date = timestamp_from_format($date, 'd / m / Y|');
                    $this->anchor = $date;

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "C";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('Localizador:  (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//*[contains(text(), '(Adulto)')]");
                        $ppl = array_map(function ($x) { return reni('(.+?) -? \(', $x); }, $ppl);

                        return $ppl;
                    },

                    "ShipName" => function ($text = '', $node = null, $it = null) {
                        $info = node("//*[normalize-space(text()) = 'Información general']/preceding::span[1]");

                        return reni('- (.+)', $info);
                    },

                    "CruiseName" => function ($text = '', $node = null, $it = null) {
                        return reni('Descripción: (.+?) \n');
                    },

                    "RoomClass" => function ($text = '', $node = null, $it = null) {
                        $q = white('Categoria:
							(?P<RoomClass> .+?) \( (?P<RoomNumber> \d+) \)
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (reni('En primer lugar agradecerle que haya reservado con nosotros')) {
                            return 'confirmed';
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Precio total de la reserva  (.+?) \n');

                        return total($x);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[normalize-space(text()) = 'Puerto']/ancestor::tr[1]/following-sibling::tr");

                        return xpath("//*[normalize-space(text()) = 'Puerto']/ancestor::table[1]//td[
							contains(., ' h') and
							contains(., ':')
						]");
                    },

                    "TripSegments" => [
                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $s = node('./td[2]');

                            return nice($s);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $time1 = uberTime(node('./td[3]'));
                            $time2 = uberTime(node('./td[4]'));

                            $dt1 = $time1 ? date_carry($time1, $this->anchor) : $this->anchor;
                            $dt2 = date_carry($time2, $dt1);

                            $this->anchor = $dt2;

                            return [
                                'DepDate' => $dt2,
                                'ArrDate' => $dt1,
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
        return ["es"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
