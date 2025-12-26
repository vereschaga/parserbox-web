<?php

namespace AwardWallet\Engine\hertz\Email;

use PlancakeEmailParser;

class It2311293 extends \TAccountCheckerExtended
{
    public $reSubject = [
        ['#Hertz Reservation#', 'us', ''], ['#Reserva Hertz#', 'us', ''], ['#La mia prenotazione Hertz#', 'us', ''],
    ];

    public $reFrom = "#hertz#i";

    public $mailFiles = "";

    public $reBody = [
        'es' => ['Recogida', 'Dirección'],
        'it' => ['Ritiro', 'Indirizzo'],
    ];
    public $lang = '';
    public static $dict = [
        'es' => [
        ],
        'it' => [
            "confirmación es el siguiente"      => "tuo numero di prenotazione è",
            "Localidad de Recogida y Devolución"=> "Agenzia di ritiro",
            "Dirección"                         => "Indirizzo",
            "Horarios de Atención"              => "Orario d'apertura",
            "Recogida"                          => "Ritiro",
            "Devolución"                        => "Consegna",
            "Teléfono"                          => "Numero di telefono",
            "Fax"                               => "Numero di Fax",
            "Vehículo"                          => "Il tuo veicolo",
            "Total"                             => "Totale",
            "Tax"                               => "Tasse",
        ],
    ];

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (0 === $this->http->XPath->query("//node()[contains(., 'hertz.com')]")->length
            && 0 === $this->http->XPath->query("//a[contains(@href, 'hertz.com')]")->length
        ) {
            return false;
        }
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $reBody) {
            if (false !== stripos($body, $reBody[0]) && false !== stripos($body, $reBody[1])) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->AssignLang($text);

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#" . $this->t('confirmación es el siguiente') . "\s*:\s*([A-Z\d\-]+)#ix");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//*[contains(text(), '" . $this->t('Localidad de Recogida y Devolución') . "')]/ancestor-or-self::td[1]"));
                        $addr = node("//*[contains(text(), '" . $this->t('Localidad de Recogida y Devolución') . "')]/ancestor-or-self::td[1]//text()[contains(., '" . $this->t('Dirección') . "')]/ancestor::td[1]", null, true, "#^" . $this->t('Dirección') . "\s+(.+)#s");

                        if (empty($addr)) {
                            $addr = node("//*[contains(text(), '" . $this->t('Localidad de Recogida y Devolución') . "')]/ancestor-or-self::td[1]/following::text()[contains(., '" . $this->t('Dirección') . "')]/ancestor::td[1]", null, true, "#^" . $this->t('Dirección') . "\s+(.+)#s");
                        }

                        return re("#^[^\n]+\s+([^\n]+)#", $text) . ', ' . $addr;
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        if ($tmp = $this->http->FindSingleNode('//*[name() = "label" or name() = "p"][contains(normalize-space(.), "' . $this->t('Recogida') . '")]/following-sibling::div[1]')) {
                            //sáb, 19 nov, 2016  a la(s) 22:00
                            if (preg_match('#,\s+(?<DayM>\d+\s+[\S]+)\,\s+(?<Year>\d+).+?(?<Time>\d+\:\d+)#', $tmp, $m)) {
                                return strtotime(en($m['DayM'] . ' ' . $m['Year'] . ' ' . $m['Time']));
                            }
                            //jue, jul 18, 2019 a la(s) 11:30 PM
                            if (preg_match('#,\s+(?<Month>[\S]+)\s+(?<Day>\d+)\,\s+(?<Year>\d+).+?(?<Time>\d+\:\d+(?:\s*[ap]m)?)#iu', $tmp, $m)) {
                                return strtotime(en($m['Day'] . ' ' . $m['Month'] . ' ' . $m['Year'] . ' ' . $m['Time']));
                            }
                        }

                        return strtotime(en(re("#(\d+\s+\w+),(\s+\d+)\s+a\s+la\(s\)\s+(\d+:\d+\s+[AP]M)#i", cell($this->t('Recogida'), 0, +1)) . re(2) . ', ' . re(3)));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return $it['PickupLocation'];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        if ($tmp = $this->http->FindSingleNode('//*[name() = "label" or name() = "p"][starts-with(normalize-space(.), "' . $this->t('Devolución') . '")]/following-sibling::div[1]')) {
                            //sáb, 19 nov, 2016  a la(s) 22:00
                            if (preg_match('#(?<DayM>\d+\s+\S+),\s+(?<Year>\d+).+?(?<Time>\d+:\d+)#', $tmp, $m)) {
                                return strtotime(en($m['DayM'] . ' ' . $m['Year'] . ' ' . $m['Time']));
                            }
                            //jue, jul 18, 2019 a la(s) 11:30 PM
                            if (preg_match('#,\s+(?<Month>[\S]+)\s+(?<Day>\d+)\,\s+(?<Year>\d+).+?(?<Time>\d+\:\d+(?:\s*[ap]m)?)#iu', $tmp, $m)) {
                                return strtotime(en($m['Day'] . ' ' . $m['Month'] . ' ' . $m['Year'] . ' ' . $m['Time']));
                            }
                        }

                        return strtotime(en(re("#(\d+\s+\w+),(\s+\d+)\s+a\s+(?:la\(s\)\s+)?(\d+:\d+\s+[AP]M)#i", cell($this->t('Devolución'), 0, +1)) . re(2) . ', ' . re(3)));
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*" . $this->t('Teléfono') . "[:\s]+([\d\-\(\)+ ]+)#");
                    },

                    "PickupFax" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*" . $this->t('Fax') . "[:\s]+([\d\-\(\)+ ]+)#");
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*" . $this->t('Horarios de Atención') . "\s*:\s*([^\n]+)#");
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        return $it['PickupPhone'];
                    },

                    "DropoffHours" => function ($text = '', $node = null, $it = null) {
                        return $it['PickupHours'];
                    },

                    "DropoffFax" => function ($text = '', $node = null, $it = null) {
                        return $it['PickupFax'];
                    },

                    //					"RentalCompany" => function ($text = '', $node = null, $it = null) {
                    //						return 'Hertz Rent a Car';//re("#viajar a la velocidad de ([^\n,.]+),#ix");
                    //					},

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        if (($text = xpath("//*[contains(text(), '" . $this->t('Vehículo') . "')]/ancestor::tr[1]/following::tr[1]//img[1]/ancestor::td[1]/following-sibling::td[1]")->length === 0)) {
                            if (($text = nodes("//*[normalize-space(.)='" . $this->t('Vehículo') . "']/ancestor::tr[1]/following::tr"))) {
                                return [
                                    'CarType'  => $text[0],
                                    'CarModel' => $text[1],
                                ];
                            }
                        }

                        return [
                            'CarType'  => re("#^([^\n]+)\n\s*([^\n]+)#", $text),
                            'CarModel' => re(2),
                        ];
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        $src = node("//*[contains(text(), '" . $this->t('Vehículo') . "')]/ancestor::tr[1]/following::tr[1]//img[1]/@src");

                        if (empty($src)) {
                            $src = node("//*[contains(text(), '" . $this->t('Vehículo') . "')]/ancestor::tr[1]/preceding::tr[1]//img[1]/@src");
                        }

                        return $src;
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//text()[contains(.,'" . $this->t('confirmación es el siguiente') . "')]/preceding::text()[normalize-space(.)][1]");

                        if ($name !== 'View in browser') {
                            $name = re("#(?:.+,|^)\s*(.+?)$#", $name);
                        } else {
                            $name = re("#velocidad de Hertz, ([^\n]+)\s*\n#ix");
                        }

                        return $name;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*" . $this->t('Total') . "\s+([\d.,]+\s*[^\n]+)#"));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        if (!($tax = re("#\n\s*IMPUESTO\s+([^\n]+)#"))) {
                            $tax = node("//td[normalize-space(.)='" . $this->t('Tax') . "']/following-sibling::td[1]");
                        }

                        return cost($tax);
                    },

                    "ServiceLevel" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*" . $this->t('Tipo de Servicio Gold') . "\s*:\s*([^\n]+)#ix");
                    },
                ],
            ],
        ];
    }

    /*
    public function dateStringToEnglish($date){
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)){
            $monthNameOriginal = $m[0];
            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);   }
        }
        return $date;
    }

    var $dateTimeToolsMonths = [
        "en" => [
            "january" => 0,
            "february" => 1,
            "march" => 2,
            "april" => 3,
            "may" => 4,
            "june" => 5,
            "july" => 6,
            "august" => 7,
            "september" => 8,
            "october" => 9,
            "november" => 10,
            "december" => 11,
        ],
        "es" => [
            "enero" => 0,
            "feb" => 1, "febrero" => 1,
            "marzo" => 2,
            "abr" => 3, "abril" => 3,
            "mayo" => 4,
            "jun" => 5, "junio" => 5,
            "julio" => 6, "jul" => 6,
            "agosto" => 7,
            "sept" => 8, "septiembre" => 8,
            "oct" => 9, "octubre" => 9,
            "nov" => 10, "noviembre" => 10,
            "dic" => 11, "diciembre" => 11,
        ],
        "it" => [
            "gen" => 0, "gennaio" => 0,
            "feb" => 1, "febbraio" => 1,
            "marzo" => 2, "mar" => 2,
            "apr" => 3, "aprile" => 3,
            "maggio" => 4, "mag" => 4,
            "giu" => 5, "giugno" => 5,
            "luglio" => 6, "lug" => 6,
            "ago" => 7, "agosto" => 7,
            "settembre" => 8, "set" => 8,
            "ott" => 9, "ottobre" => 9,
            "novembre" => 10, "nov" => 10,
            "dic" => 11, "dicembre" => 11,
        ],
    ];
    var $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    function translateMonth($month, $lang){
        $month = mb_strtolower(trim($month), 'UTF-8');
        if(isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month]))
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        return false;
    }
    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'hertz')]")->length > 0){
            $body = $parser->getHTMLBody();
            return $this->AssignLang($body);
        }
        return false;
    }
*/
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

                    return true;
                }
            }
        }

        return false;
    }
}
