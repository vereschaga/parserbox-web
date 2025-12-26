<?php

namespace AwardWallet\Engine\monarch\Email;

class It1806788 extends \TAccountCheckerExtended
{
    public $mailFiles = "monarch/it-1806788.eml, monarch/it-1808288.eml, monarch/it-4455926.eml, monarch/it-4455940.eml, monarch/it-4493766.eml, monarch/it-5123616.eml, monarch/it-5168126.eml";

    public $reBody = [
        'es' => ['Pasajeros', 'Pago'],
        'en' => ['Passengers ', 'Payment'],
        'it' => ['Passeggeri ', 'Pagamento'],
    ];
    public $lang = '';
    public static $dict = [
        'es' => [
            'Record locator' => 'Tu\s*número\s*de\s*confirmación\s*es:',
            'Charged'        => ['Coste total del viaje', 'Total'],
            'Status'         => 'Status:',
            'Booking Date'   => 'Fecha\s*de\s*reserva:',
            'Total Price'    => 'Precio total:',
            'flight'         => 'vuelo',
            'flying out'     => 'viajar\s*desde',
            'flying back'    => 'vuelo\s*de\s*vuelta',
            'departing'      => 'salida',
            'arrive'         => 'llegada',
            'to'             => 'a',
            'Seat'           => 'Asiento',
        ],
        'en' => [
            'Record locator' => 'Your flight confirmation number is:',
            'Charged'        => 'Charged',
            'Status'         => 'Status:',
            'Booking Date'   => 'Booking\s*Date:',
            'Total Price'    => 'Total Price:',
            'flight'         => 'flight',
            'flying out'     => 'flying\s*out',
            'flying back'    => 'flying\s*back',
            'departing'      => 'departing',
            'arrive'         => 'arrive',
            'to'             => 'to',
            'Seat'           => 'Seat',
        ],
        'it' => [
            'Record locator' => 'Il\s+tuo\s+numero\s+di\s+conferma\s+volo\s+è\s*:',
            'Charged'        => 'Addebitato',
            'Status'         => 'Stato:',
            'Booking Date'   => 'Data\s*di\s*prenotazione:',
            //			'Total Price' => 'Total Price:',
            'flight'      => 'Volo',
            'flying out'  => 'Partenza',
            'flying back' => 'Volo\s*di\s*ritorno',
            'departing'   => 'Partenza',
            'arrive'      => 'Arrivo',
            'to'          => 'a',
            //			'Seat' => 'Sede'
        ],
    ];

    private $detectBody = [
        'Monarch se propone ayudar, en la medida de lo posible', // es
        'Monarch aims to accommodate, whenever possible', // en
        'Monarch si impegna ad assistere, qualora possibile', // it
    ];

    public function processors()
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

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
                        return re("#" . $this->t('Record locator') . "\s*([\w-]+)#u");
                    //return re("#Your flight confirmation number is:\s*([\w-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//*[contains(@src, "flg_confirmation/fly")]/ancestor::tr[1]/following-sibling::tr[1]//tr[position() > 1]/td[1]';
                        $a = nodes($xpath);

                        return array_values(array_unique($a));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell($this->t('Charged'), +1));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return re("##");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#" . $this->t('Status') . "\s*" . $this->t('Total Price') . "\s+(?:\d+\s+\w+\s+\d+)\s+(\w+)#i"); // after date
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $d = re([
                            "#" . $this->t('Status') . "\s*" . $this->t('Total Price') . "\s+(\d+\s+\w+\s+\d+)\s+#i",
                            "#" . $this->t('Booking Date') . "\s*" . $this->t('Status') . "\s+(\d+\s+\w+\s+\d+)\s+#i",
                        ]);

                        return totime(en(uberDateTime($d)));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//*[contains(@src, "flg_confirmation/fly")]/ancestor::tr[1]/following-sibling::tr[1][contains(., ":")]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re("#" . $this->t('flight') . "\s*([a-z\d]{2}\s*\d+)#i");

                            if ($fl === null) {
                                return FLIGHT_NUMBER_UNKNOWN;
                            }

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $info = node("./preceding-sibling::tr[1]");
                            //if (preg_match("#flying\s*(?:out|back)\s*(.+)\s+to\s+(.+)#i", $info, $ms)) {
                            if (preg_match("#(?:" . $this->t('flying out') . "|" . $this->t('flying back') . ")\s*(.+)\s+" . $this->t('to') . "\s+(.+)#i", $info, $ms)) {
                                return [
                                    'DepName' => $ms[1],
                                    'ArrName' => $ms[2],
                                ];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re('#(\S+\s+\d+\s+\S{3}\s+\d+)#');
                            $time1 = re("#" . $this->t('departing') . "\s*(\d+:\d+)#i");
                            $time2 = re("#" . $this->t('arrive') . "\s*(\d+:\d+)#i");

                            $dt1 = "$date, $time1";
                            $dt2 = "$date, $time2";

                            $dt1 = totime(en(uberDateTime($dt1)));
                            $dt2 = totime(en(uberDateTime($dt2)));

                            if ($dt2 < $dt1) {
                                $dt2 = strtotime('+1 day', $dt2);
                            }

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            if (preg_match_all("#" . $this->t('Seat') . "\s+([A-Z\d]+)\s*#", node('.'), $ms)) {
                                return implode(', ', $ms[1]);
                            }
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detectBody as $dt) {
            if (stripos($body, $dt) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'monarch.co') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'monarch.co') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
