<?php

namespace AwardWallet\Engine\golair\Email;

class It3010891 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#reserva\s+de\s+carro\s*VOOS\s*IDA\s*\w+\s+\d+\s*[^\n]{2,45}?\([A-Z]+\)\s*\d+\s*\S+\s*\-\s*\d+h\d+\s*Voo#i', 'blank', '4000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Gol Itinerary -', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]golair#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]golair#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "25.08.2015, 17:06";
    public $crDate = "25.08.2015, 13:17";
    public $xPath = "";
    public $mailFiles = "golair/it-3010891.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = \StringTools::htmlToTextLines($this->getDocument('view', 'source'), [["#<\/?\w+(?>[^>]*)>#", "\n"]]);

                    $this->defis = "_";
                    $this->seatsByFlight = [];
                    $seatsRaw = re("#\n\s*PASSAGEIROS\s*?(\n.+?\s*)(?:PAGAMENTO|Total|$)#si", $text);

                    if (preg_match_all("#\n\s*(?:Ida|Retorno)\s*?\n\s*(\w+) +(\d+)\s*?\n\s*(\d+[A-Z])#i", $seatsRaw, $m, PREG_SET_ORDER)) {
                        foreach ($m as $seat) {
                            $key1 = $seat[1] . $this->defis . $seat[2];

                            if (!isset($this->seatsByFlight[$key1])) {
                                $this->seatsByFlight[$key1] = [];
                            }
                            $this->seatsByFlight[$key1][] = $seat[3];
                        }
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#^\s*Localizador\s*:\s*([\w-]+)\b#miu");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all("#\n\s*([^\n]+)\s+(?>RECIBO\s*:|Trecho)\s*?\n#u", re("#\n\s*PASSAGEIROS\s*?(\n.+?\s*)(?:PAGAMENTO|Total|$)#sui"), $m)) {
                            return $m[1];
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#Total da viagem\s*:\s*([^\n]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Situação da passagem\s*:\s*([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(en(re("#Data da compra\s*:\s*.+?(\d+\s+\S+),\s*(\d+)#") . " " . re(2), "pt"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $itsRaw = re("#\n\s*VOOS\s*?(\n.+?\s+)(?:PASSAGEIROS|PAGAMENTO)\s*?\n#si");

                        return splitter("#(\n\s*(?:IDA|RETORNO)\s+\w+ +\d+\s*?\n\s*[^\n]+?\([A-Z]+\)\s+\d)#i", $itsRaw);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $data['AirlineName'] = re("#\n\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s?(\d{1,5})\s*\n+#u");
                            $data['FlightNumber'] = re(2);
                            $key1 = $data['AirlineName'] . $this->defis . $data['FlightNumber'];

                            if (isset($this->seatsByFlight[$key1])) {
                                $data['Seats'] = implode(", ", $this->seatsByFlight[$key1]);
                            }

                            return $data;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $data['DepName'] = re("#\n\s*([^\n]+?)\s+\(([A-Z]+)\)\s*?\n#u");
                            $data['DepCode'] = re(2);

                            return $data;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $letterDate = strtotime($this->parser->getHeader('date'));
                            $year = date("Y", $letterDate);
                            $data['DepDate'] = totime(en(re("#\([A-Z]+\)\s+(\d+\s+\S+)\s*\-\s*(\d+)[h:](\d+)#"), "pt") . " " . $year . ", " . re(2) . ":" . re(3));
                            $data['ArrDate'] = totime(en(re("#\-\s*\d+[h:]\d+\s.+?\([A-Z]+\)\s+(\d+\s+\S+)\s*\-\s*(\d+)[h:](\d+)#s"), "pt") . " " . $year . ", " . re(2) . ":" . re(3));
                            correctDates($data['DepDate'], $data['ArrDate']);

                            return $data;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $data['ArrName'] = re("#\-\s*\d+[h:]\d+.+?\n\s*([^\n]+?)\s+\(([A-Z]+)\)\s*?\n#su");
                            $data['ArrCode'] = re(2);

                            return $data;
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
