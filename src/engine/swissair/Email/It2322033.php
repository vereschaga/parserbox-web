<?php

namespace AwardWallet\Engine\swissair\Email;

class It2322033 extends \TAccountCheckerExtended
{
    public $mailFiles = "";

    private $detectBody = [
        'Estimada clienta, estimado cliente',
        'Conformément à votre demande, voici votre confirmation d\'émission de billet électronique au format PDF',
        'Conformément à votre demande, voici votre confirmation',
        'ha effettuato la registrazione al check-in automatico',
        'You have registered for automated check-in',
    ];

    private $provider = 'swiss';

    private $year = 0;

    private $monthNames = [
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        'es' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
        'fr' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
        'it' => ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'],
    ];

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
                        $res = reni('Référence de réservation: (\w+)');

                        if (empty($res)) {
                            $res = CONFNO_UNKNOWN;
                        }

                        return $res;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [reni('(?:Nom du passager|Nombre de pasajero|Nominativo del passeggero)\s*:\s+(.+?) \n')];
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return reni('(?:Nº Passager Fréquent|Pasajero frecuente n°)\s*:\s+(\w+)');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = reni('([\d.,]+) Mode de paiement');

                        return cost($x);
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        $x = reni('(\w+) Tarif \d+');

                        return currency($x);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('Emis par: (.+? \d{4})');
                        $date = totime($date);
                        $this->anchor = $date;

                        return $date;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = substr($text, 0, strpos("Electronic ticket", $text));

                        $q = white('\d+\)\s+[^\d]+?\([A-Z]{3}\)');

                        return splitter("/($q)/isu", $info);
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $re = '/(?:De|Da)\s+(?<DepName>[^\(\)]+)\s+(?:\((?<DepCode>[A-Z]{3})\))?\s?(?:A|à|a|À)\s+';
                            $re .= '(?<ArrName>[^\(\)]+)\s*(?:\((?<ArrCode>[A-Z]{3})\))?/u';

                            if (preg_match($re, $text, $m)) {
                                return [
                                    'DepName' => $m['DepName'],
                                    'DepCode' => !empty($m['DepCode']) ? $m['DepCode'] : TRIP_CODE_UNKNOWN,
                                    'ArrName' => $m['ArrName'],
                                    'ArrCode' => !empty($m['ArrCode']) ? $m['ArrCode'] : TRIP_CODE_UNKNOWN,
                                ];
                            }

                            return null;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $re = '/(?<Day>\d{1,2})\s+(?<Month>\w+)(?:\,?\s+(?<Year>\d{4}))?\s+.*\s+';
                            $re .= '(?<DepTime>\d{1,2}:\d{2})\s+\D+\s+(?<ArrTime>\b\d{1,2}\b:\d{2})/u';
                            $date = '';

                            if (preg_match($re, $text, $m)) {
                                if (!empty($m['Year'])) {
                                    $date = $m['Day'] . ' ' . $this->monthNameToEn($m['Month']) . ' ' . $m['Year'];
                                } elseif (empty($m['Year']) && !empty($this->year)) {
                                    $date = $m['Day'] . ' ' . $this->monthNameToEn($m['Month']) . ' ' . $this->year;
                                }

                                return [
                                    'DepDate' => strtotime($date . ', ' . $m['DepTime']),
                                    'ArrDate' => strtotime($date . ', ' . $m['ArrTime']),
                                ];
                            }

                            return null;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return reni('(\w+) OK');
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('(?:V ol|vuelo|Volo n.) (\w+\d+)');

                            return uberAir($fl);
                        },
                    ],
                ],
            ],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = date('Y', strtotime($parser->getDate()));
        $result = parent::ParsePlanEmail($parser);

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detectBody as $detect) {
            if (stripos($body, $detect) !== false && stripos($body, $this->provider) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, $this->provider) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->provider) !== false;
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public static function getEmailLanguages()
    {
        return ['fr', 'es', 'it'];
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function monthNameToEn($monthNameInput)
    {
        if (empty($monthNameInput)) {
            return null;
        }

        foreach ($this->monthNames as $lang => $monthNames) {
            foreach ($monthNames as $number => $monthName) {
                if (stripos($monthName, $monthNameInput) !== false) {
                    return $this->monthNames['en'][$number];
                }
            }
        }

        return false;
    }
}
