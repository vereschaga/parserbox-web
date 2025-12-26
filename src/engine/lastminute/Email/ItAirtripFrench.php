<?php

namespace AwardWallet\Engine\lastminute\Email;

class ItAirtripFrench extends \TAccountCheckerExtended
{
    public $reFrom = "#lastminute.com-vols@fr.travel-agency.travel#i";
    public $reProvider = "#fr.travel-agency.travel#i";
    public $rePlain = [
        'LASTMINUTE - BILLET ELECTRONIQUE',
        'ACCUSE RECEPTION',
    ];
    public $reSubject = "#LASTMINUTE - BILLET ELECTRONIQUE#i";
    public $mailFiles = "lastminute/it-1541529.eml, lastminute/it-1541555.eml";

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
                        $res = re("#(?:Référence GDS|Votre numéro de dossier)\s+:\s+([\w\-]+)#");

                        return (!empty($res)) ? $res : CONFNO_UNKNOWN;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengers = nodes("//text()[contains(., 'NOM / Prénom')]/ancestor::table[1]//tr[position() > 1]/td[2]");
                        array_walk($passengers, function (&$value, $key) { $value = re('#[\w/]+#', $value); });

                        return $passengers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return $this->http->XPath->query("//text()[contains(., 'Départ')]/ancestor::table[1]/descendant::tr[not(contains(., 'Départ'))]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\d+#", node('./td[4]'));
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[3]');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node('./td[6]');
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return node('./td[5]');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $s = node('./td[1]');

                            if (preg_match('#(?<Day>\d+)\s+(?<Month>\S+)\s+(?<Year>\d+)\s+-\s+(?<DepTime>\d{1,2}:\d{2})\s*(?<DepName>.+)\s*(?:Aéroport|):\s+(?:.+ Terminal (?<DepTerm>\w+)|.+)#i', $s, $m)) {
                                [$day, $month, $year, $time, $name] = array_slice($m, 1);
                                $res['DepName'] = $name;
                                $month = en($month);
                                $res['DepDate'] = strtotime("$day $month $year, $time:00");
                            }

                            return $res;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $s = node('./td[2]');

                            if (preg_match('#(?<Day>\d+)\s+(?<Month>\S+)\s+(?<Year>\d+)\s+-\s+(?<DepTime>\d{1,2}:\d{2})\s*(?<DepName>.+)\s*(?:Aéroport|):\s+(?:.+ Terminal (?<DepTerm>\w+)|.+)#i', $s, $m)) {
                                [$day, $month, $year, $time, $name] = array_slice($m, 1);
                                $res['ArrName'] = $name;
                                $month = en($month);
                                $res['ArrDate'] = strtotime("$day $month $year, $time:00");
                            }

                            return $res;
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach ($this->rePlain as $item) {
            if (stripos($body, $item) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["fr"];
    }
}
