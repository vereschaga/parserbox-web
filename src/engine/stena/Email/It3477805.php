<?php

namespace AwardWallet\Engine\stena\Email;

class It3477805 extends \TAccountCheckerExtended
{
    public $mailFiles = "stena/it-10027545.eml, stena/it-3477805.eml, stena/it-6040500.eml, stena/it-6040515.eml, stena/it-6040531.eml";

    public $reFrom = "stenaline.com";
    public $reSubject = [
        "en" => "Stena Line E-ticket and Reservation Advice for booking reference",
        "nl" => "Welkom aan boord! Bevestiging van uw reservering",
        "da" => "Bekræftelse af bookingnummer",
        "sv" => "Bekräftelse/biljett Bokn.nr",
    ];
    public $reBody = "Stena Line";
    public $reBody2 = [
        "en" => "Booking Summary",
        "nl" => "Reserveringsoverzicht",
        "da" => "Oversigt",
        "sv" => "Bokningsöversikt",
    ];

    public static $dictionary = [
        "en" => [
            'addToName' => 'Port',
        ],
        "nl" => [
            'addToName'         => 'Haven',
            'Price Information' => 'Prijsoverzicht',
            "Booking Summary"   => "Reserveringsoverzicht",
            'FLEXI'             => 'Economy',
            'BOOKING REFERENCE' => 'RESERVERINGSNUMMER',
            'Name'              => 'Naam',
            'Ship'              => 'Schip',
            'Departs'           => 'Vertrek',
            'Arrives'           => ['Aankomst', 'Aank.'],
        ],
        "da" => [
            'addToName'         => 'Havn',
            'Price Information' => 'Prisinformation',
            "Booking Summary"   => "Oversigt",
            'FLEXI'             => 'Flexi',
            'BOOKING REFERENCE' => 'BOOKINGNUMMER',
            'Name'              => 'Kundens navn',
            'Ship'              => 'Færge',
            'Departs'           => 'Afgang',
            'Arrives'           => 'Ankomst',
        ],
        "sv" => [
            'addToName'         => 'Hamn',
            'Price Information' => 'Prisinformation',
            "Booking Summary"   => "Bokningsöversikt",
            'FLEXI'             => 'PREMIUM',
            'BOOKING REFERENCE' => 'BOKNINGSNUMMER',
            'Name'              => 'Namn',
            'Ship'              => 'Färja',
            'Departs'           => 'Avresa',
            'Arrives'           => 'Ankomst',
        ],
    ];

    public $lang = "en";
    private $num = 2;
    private $current;
    private $totals;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter"                => function ($text = '', $node = null, $it = null) {
                    foreach ($this->reBody2 as $lang => $re) {
                        if (strpos($this->http->Response["body"], $re) !== false) {
                            $this->lang = $lang;

                            break;
                        }
                    }
                    $this->totals = nodes("//*[normalize-space(text())='" . $this->t('Price Information') . "']/ancestor::table[1]/following::table[1]//*[name()='th' or name()='td'][contains(., '" . $this->t('FLEXI') . "')]/following-sibling::*[1]");
                    $this->current = 0;

                    if (xpath("//text()[contains(.,'" . $this->t("Booking Summary") . "')]/following::table[1]//img[contains(@src, 'img/HTMLItinerary/arrow-')]/ancestor::tr[1]/..")->length > 0) {
                        $this->num = 2;

                        return xpath("//text()[contains(.,'" . $this->t("Booking Summary") . "')]/following::table[1]//img[contains(@src, 'img/HTMLItinerary/arrow-')]/ancestor::tr[1]/..");
                    }

                    if (xpath("//text()[contains(.,'" . $this->t("Booking Summary") . "')]/following::table[count(descendant::table)=0 and contains(.,'" . $this->t('Departs') . "')]")->length > 0) {
                        $this->num = 1;

                        return xpath("//text()[contains(.,'" . $this->t("Booking Summary") . "')]/following::table[count(descendant::table)=0 and contains(.,'" . $this->t('Departs') . "')]");
                    }
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "C";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return node("//h2[contains(., '" . $this->t('BOOKING REFERENCE') . "')]/following-sibling::h1[1]");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        //return re('/\d+/i', cell('Passengers', +1));
                        return [$this->http->FindSingleNode("//*[name()='th' or name()='td'][contains(.,'" . $this->t('Name') . "')]/following-sibling::*[1]")];
                    },

                    "ShipName" => function ($text = '', $node = null, $it = null) {
                        return cell($this->t('Ship'), +1);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        if (isset($this->totals[$this->current])) {
                            $total = total($this->totals[$this->current]);
                            $this->current++;

                            return $total;
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $ports = node("./descendant::tr[{$this->num}]");
                        $portDep = trim(re('#^(.+)\s*-\s*.+$#i', $ports));
                        //add for correct google countryName
                        if (!empty($portDep) && stripos($portDep, $this->t('addToName')) === false) {
                            $portDep .= ' ' . $this->t('addToName');
                        }
                        $portArr = trim(re('#^.+\s*-\s*(.+)$#i', $ports));
                        //add for correct google countryName
                        if (!empty($portArr) && stripos($portArr, $this->t('addToName')) === false) {
                            $portArr .= ' ' . $this->t('addToName');
                        }

                        return [
                            [
                                'Port'    => $portDep,
                                'DepDate' => strtotime(str_replace("/", ".", re("#\d+[\/-]\d+[\/-]\d+\s+\d+:\d+#", cell($this->t("Departs"), +1)))),
                                'ArrDate' => null,
                            ],
                            [
                                'Port'    => $portArr,
                                'DepDate' => null,
                                'ArrDate' => strtotime(str_replace("/", ".", re("#\d+[\/-]\d+[\/-]\d+\s+\d+:\d+#", cell($this->t("Arrives"), +1)))),
                            ],
                        ];
                    },

                    "TripSegments" => [
                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return $node['Port'];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return $node['DepDate'];
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return $node['ArrDate'];
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
