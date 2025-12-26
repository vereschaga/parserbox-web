<?php

namespace AwardWallet\Engine\iberia\Email;

class It2072120 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*(?:From|De)\s*:[^\n]*?iberia#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#iberia#i";
    public $reProvider = "#@.*\biberia\b#i";
    public $caseReference = "6833";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "iberia/it-2072120.eml, iberia/it-4090203.eml, iberia/it-4107112.eml, iberia/it-4107113.eml, iberia/it-4688689.eml";
    public $pdfRequired = "0";

    public $lang = 'es';
    public $reBody = [//for lang
        'en' => ['Your flight', 'Reservation'],
        'es' => ['Su viaje', 'Reserva'],
    ];
    public static $dict = [
        'en' => [
            'Cód. Reserva'     => 'Reservation code',
            'Hola'             => 'Dear|Hello',
            'Salid'            => 'Departure date',
            'De'               => 'From',
            'a'                => 'to',
            'Tarjeta Clásica:' => 'Card Clásica:',
        ],
        'es' => [
            'Hola' => 'Hola|Estimado',
        ],
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//img[@src='http://avisos.iberia.com/ibcomv3/content/predeparture/info.png']")->length > 0 || $this->http->XPath->query("//a[contains(@href, 'http://avisos.iberia.com')]")->length > 0;
    }

    public function processors()
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

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
                        return node("(//*[contains(text(),'" . $this->t('Cód. Reserva') . "')]/ancestor::tr[1]/following-sibling::tr[1]/td[last()])[1]", null, true, "#^([A-Z\d\-]+)$#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re("#(?:" . $this->t('Hola') . ")\s+([^\n,:]+)#")];
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return [node("//text()[contains(.,'" . $this->t('Tarjeta Clásica:') . "')]/following::text()[normalize-space(.)][1]", null, true, "#^\s*(\d+)\s*$#")];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//img[contains(@src, 'destino.jpg')]/ancestor::tr[3]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return FLIGHT_NUMBER_UNKNOWN;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return AIRLINE_UNKNOWN;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepName' => re("#(?:^|\n)\s*" . $this->t('De') . "\s+(.*?)\s+" . $this->t('a') . "\s+([^\n]+)#"),
                                'ArrName' => re(2),
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(en(uberDate()) . ',' . uberTime(node(".//*[contains(text(), '" . $this->t('Salid') . "')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]")));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return node(".//*[contains(text(),'" . $this->t('Cód. Reserva') . "')]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]", $node, true, "#^([A-Z\d\-]+)$#");
                        },
                    ],
                ],
            ],
        ];
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

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        return true;
    }
}
