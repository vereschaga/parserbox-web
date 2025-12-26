<?php

namespace AwardWallet\Engine\lavoueu\Email;

class It1839083 extends \TAccountCheckerExtended
{
    public $reFrom = "#@lavoueuviagens[.]com[.]br#i";
    public $reProvider = "#@lavoueuviagens[.]com[.]br#i";
    public $rePlain = "#\n[>\s*]*De\s*:[^\n]*?@lavoueuviagens[.]com[.]br#i";
    public $rePlainRange = "1000";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "lavoueu/it-1839083.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#CÓDIGO\s*DA\s*RESERVA:\s*([\w-]+)#is");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = re("#NOME:(.+?)Data\s*de\s*emissão:#is");

                        return [nice($name)];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $tot = cell('TOTAL', +2);

                        return total($tot);
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cell('Taxas:', +4);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re('/Bilhete\s*Alterado/i')) {
                            return 'changed';
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = re("#Data\s*de\s*emissão:\s*(\w+)#");
                        $date = \DateTime::createFromFormat('dMy', $date);

                        if (!$date) {
                            return;
                        }
                        $date->setTime(0, 0);

                        return $date->getTimestamp();
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter('/(Data:)/');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re('/Vôo:\s*(.+)\s*-/i');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $date = re('/Data:\s*(.+)\s*Vôo:/is');

                            $time1 = re('/Saída:\s*(\d+:\d+)/i');
                            $dep = re('/Saída:\s*\d+:\d+\s*(.+)\s*Chegada:/is');

                            $time2 = re('/Chegada:\s*(\d+:\d+)/i');
                            $arr = re('/Chegada:\s*\d+:\d+\s*(.+)\s*Classe:/is');

                            $fmt = 'dM H:i Y';
                            $dt1 = nice("$date $time1 " . date("Y", $this->date));
                            $dt1 = \DateTime::createFromFormat($fmt, $dt1);
                            $dt2 = nice("$date $time2 " . date("Y", $this->date));
                            $dt2 = \DateTime::createFromFormat($fmt, $dt2);

                            return [
                                'DepName' => nice($dep),
                                'DepDate' => $dt1 ? $dt1->getTimestamp() : '',
                                'ArrName' => nice($arr),
                                'ArrDate' => $dt2 ? $dt2->getTimestamp() : '',
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#Aeronave:\s*(.+?)\s*-#is");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Cabin'        => re("#Classe:\s*(.+?)[(]#is"),
                                'BookingClass' => re("#Classe:\s*.+?[(](\s*\w+\s*)[)]#is"),
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
