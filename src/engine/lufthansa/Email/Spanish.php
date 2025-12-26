<?php

namespace AwardWallet\Engine\lufthansa\Email;

class Spanish extends \TAccountCheckerExtended
{
    use \DateTimeTools;

    public $mailFiles = "lufthansa/it-1615989.eml, lufthansa/it-1615998.eml, lufthansa/it-1747191.eml, lufthansa/it-3.eml, lufthansa/it-4038039.eml, lufthansa/it-4310626.eml, lufthansa/it-4415183.eml, lufthansa/it-5080563.eml";
    private static $detectBody = [
        'es' => 'Para ponerse en contacto con Lufthansa',
        'en' => 'Carriage is subject to Lufthansa\'s Condition of Carriage',
        'pt' => 'Lufthansa Linhas Aéreas Alemãs',
        'pl' => 'Niemieckie Linie Lotnicze Lufthansa',
    ];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->year = re('#\d{4}#i', $this->parser->getHeader('date'));

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Código\s+(?:de|da)\s+reserva|Kod\s+rezerwacji|Reservation code):\s+([\w\-]+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re('#(?:Fechas\s+del\s+viaje\s+para|Datas\s+de\s+viagem|Dane\s+Pasażera|Travel\s+dates\s+for):\s+([^\n(]+)#')];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Precio total para todos los pasajeros') or contains(., 'Cena całkowita dla wszystkich pasażerów') or contains(., 'Total Price for all passengers')]/ancestor::tr[1]";
                        $subj = re('#\s+=\s+(.*)#', node($xpath));

                        return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Su itinerario de vuelo') or contains(., 'Os detalhes do seu voo') or 
						contains(., 'Twoja rezerwacja lotów') or contains(., 'Your flight itinerary')]/ancestor::
						table[1]/following-sibling::table[preceding-sibling::table[contains(., 'Vuelo') or 
						contains(., 'Número do voo') or contains(., 'Numer rejsu') or contains(., 'Flight')] and 
						following-sibling::table[contains(., 'Precio') or contains(., 'Preço') or contains(., 'Cena') or 
						contains(., 'Total Price')]]//tr[contains(., 'Asiento') or contains(., 'Lugar') or contains(., 'TERMINAL')]";

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./td[2]');

                            if (preg_match('#(\D{2})\s*(\d+)\s*(?:operado\s+por:|obsługiwany\s+przez|operated by:)\s*.*#i', $subj, $m)) {
                                return ['FlightNumber' => $m[2], 'AirlineName' => $m[1]];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = node('./td[4]');

                            if (preg_match('#(.+(?=INTL|AIRPORT|INTERNATIONAL|TERMINAL))[TERMINAL]+:\s+(\d{1})#', $res, $m)) {
                                return [
                                    'DepName'           => $m[1],
                                    'DepartureTerminal' => $m[2],
                                ];
                            }

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            if (!$this->year) {
                                return null;
                            }
                            $dateStr = '';
                            $subj = preg_replace('#\xe2\x80\x8b#', '', node('./td[3]')); //replace an empty sumbol
                            $subj = preg_match('#(\d{1,2}[\.\s]*(?:\w+|\d{2})[-\d]*)#ui', $subj, $math) ? $math[1] : null;

                            if (preg_match('#(\d{1,2})[\.\s]+(\w+)[-]*(\d*)#ui', $subj, $m)) { //selection sequence in a regular expression is important
                                $dateStr = $m[1] . ' ' . $this->monthNameToEnglish($m[2]) . ' ' . $this->year;
                            }

                            if (preg_match('#(\d+)\.\s*(\d{2})([-\d]*)#u', $subj, $mathec)) {
                                $dateStr = $mathec[2] . '/' . $mathec[1] . '/' . $this->year;
                            }
                            $res = [];

                            foreach (['Dep' => 6, 'Arr' => 7] as $key => $value) {
                                $timeStr = re('#\d+:\d+#', preg_replace('/[\x00-\x1F\x80-\xFF]/', '', node('./td[' . $value . ']')));

                                if (empty($m[3])) {
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $timeStr);
                                } else {
                                    $date = strtotime($dateStr . ', ' . $timeStr);
                                    $res['DepDate'] = $date;
                                    $res['ArrDate'] = strtotime('+1 day', $date);
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $res = node('./td[5]');

                            if (preg_match('#([\w\s]+(?:INTL|AIRPORT|INTERNATIONAL|TERMINAL))[TERMINAL]+:\s+(\d{1})#', $res, $m)) {
                                return [
                                    'ArrName'         => $m[1],
                                    'ArrivalTerminal' => $m[2],
                                ];
                            }

                            return $res;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./td[8]');

                            if (preg_match('#(\w+)\s+\((\w)\).*[:]*\s?(\d+\w+)?#', $subj, $m)) {
                                return ['Cabin' => $m[1], 'BookingClass' => $m[2], 'Seats' => $m[3]];
                            }
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'lufthansa') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'lufthansa.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach (self::$detectBody as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectBody);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectBody);
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
