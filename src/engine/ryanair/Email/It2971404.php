<?php

namespace AwardWallet\Engine\ryanair\Email;

class It2971404 extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $reHtml = "";
    public $rePDF = [
        ['#\bRyanair\b.+?NOMBRE\s+DEL\s+CLIENTE\s*:.+?ITINERARIO\s+ORIGINAL\s+De\s+[^\n]+?\)\s+a\s+[^\n]+?\)\s*\n#si', 'blank', '10000'],
    ];
    public $reSubject = [
        ['Referencia de reserva Ryanair', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]ryanair#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]ryanair#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "es";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "11.08.2015, 16:52";
    public $crDate = "11.08.2015, 14:59";
    public $xPath = "";
    public $mailFiles = "ryanair/it-2971404.eml, ryanair/it-4224416.eml, ryanair/it-4294300.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'text');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:Código de reserve|Reserveringsnummer|Booking Reference)\s*:\s*([\w-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re("#(?:NOMBRE DEL CLIENTE|NAAM VAN DE KLANT\(EN\)|CUSTOMER NAME)\s*:\s*([^\n]+?)\s*(?:ITINERARIO|OORSPRONKELIJK|ORIGINAL SCHEDULE)#")];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(preg_replace("#(\d)(\D+)$#", "\\1 \\2", re("#(?:Coste\s+total|Total Price)\s*:(?>\s*\([^\n]+?\))?\s*([^\n]{1,33})\n#")));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $itsRaw = re("#(?:ITINERARIO\s+ORIGINAL|OORSPRONKELIJK REISSCHEMA|ORIGINAL SCHEDULE)\s*(\n.+?)(?:Coste\s+total|Total Price)\s*:#si");

                        return splitter("#(\n\s*[^\n]+?\s+\d+\D\d+\D\d+\s+(?:Vuelo|Vlucht|Flight)\s+\w+\s+(?:salida|vertrek|depart)\s)#i", $itsRaw);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\s(?:Vuelo|Vlucht|Flight)\s+(\d+)#");
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return 'FR';
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\s(?i:(?:salida|vertrek|depart))\s*([A-Z]{3,5})\s#");
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#^\s*(?:De|Van|From)\s+(.*?)\s*\(\w+\)\s*(?:a|naar|to)\s#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#(\d+\-\d+\-\d+)#");
                            $data = [];
                            $data['DepDate'] = totime($date . " " . re("#\s(?i:(?:salida|vertrek|depart)).+?\s*(\d+:\d+)#"));
                            $data['ArrDate'] = totime($date . " " . re("#\s(?i:(?:llegada|aankomst|arrive)).+?\s*(\d+:\d+)#"));

                            return $data;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\s(?i:(?:llegada|aankomst|arrive))\s*([A-Z]{3,5})\s#");
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#^\s*(?:De|Van|From)\s+.+?\s*\(\w+\)\s*(?:a|naar|to)\s+(.+?)\s*\(\w+\)\s+\d#");
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public static function getEmailLanguages()
    {
        return ["es", "nl", "en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
