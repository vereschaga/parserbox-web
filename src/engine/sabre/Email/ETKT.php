<?php

namespace AwardWallet\Engine\sabre\Email;

class ETKT extends \TAccountCheckerExtended
{
    public $reFrom = "#sabre#i";
    public $reProvider = "#sabre#i";
    public $rePlain = "#Seu\s+organizador\s+de\s+viagens\s+tem\s+o\s+prazer\s+de\s+lhe\s+enviar\s+o seu\s+itiner.*?rio\s+completo\s+pelo\s+Sabre.*?\s+Virtually\s+There#i";
    public $rePlainRange = "/1";
    public $typesCount = "1";
    public $langSupported = "pt";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "sabre/it-1800379.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('plain');
                    $text = preg_replace('#>\s*#', '', $text);
                    $this->price = re('#\*\s+TARIFA.*#i');
                    $this->year = re('#Data:.*?\s+(\d{4})\s+#ui');

                    if (preg_match('#(.*)\s+Código\s+de\s+reserva:\s+([\w\-]+)#i', $text, $m)) {
                        $this->travellers = [nice($m[1])];
                        $this->confNo = $m[2];
                    }

                    if (preg_match_all('#\w+,\s+\w+\s+\d+(?:(?s).*?)Duração:\s+.*#i', $text, $m)) {
                        return $m[0];
                    }
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(re('#Confirmação\s+da\s+empresa\s+aérea:\s+([\w\-]+)#i'), $this->confNo);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->travellers;
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#TARIFA\s+(.*)\s+TX\s+(.*)\s+TTL\s+(.*)#', $this->price, $m)) {
                            return [
                                'BaseFare'    => cost($m[1]),
                                'Tax'         => cost($m[2]),
                                'TotalCharge' => cost($m[3]),
                                'Currency'    => currency($m[3]),
                            ];
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Status:\s+(.*)\s+Confirmação#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Vôos:.*\s+(\w{2})\s+(\d+)#i', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            if (preg_match('#\w+,\s+(\w+)\s+(\d+)#i', $text, $m)) {
                                $dateStr = $m[2] . ' ' . en($m[1]) . ' ' . $this->year;

                                foreach (['Dep' => 'De', 'Arr' => 'Para'] as $key => $value) {
                                    if (preg_match('#' . $value . ':\s+(.*)\s+\((\w{3})\).*:\s+(\d+:.*)#', $text, $m)) {
                                        $res[$key . 'Name'] = $m[1];
                                        $res[$key . 'Code'] = $m[2];
                                        $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[3]);
                                    }
                                }
                            }

                            return $res;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Aeronave:\s+(.*)\s+Milhagem:\s+(.*)#i', $text, $m)) {
                                return [
                                    'Aircraft'      => $m[1],
                                    'TraveledMiles' => $m[2],
                                ];
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re('#Duração:\s+(.*)#i');
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
}
