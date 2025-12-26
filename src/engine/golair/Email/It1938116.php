<?php

namespace AwardWallet\Engine\golair\Email;

class It1938116 extends \TAccountCheckerExtended
{
    public $mailFiles = "golair/it-1.eml, golair/it-1594108.eml, golair/it-1916900.eml, golair/it-1916905.eml, golair/it-1938116.eml, golair/it-2921187.eml, golair/it-2931381.eml, golair/it-2931503.eml, golair/it-2972504.eml, golair/it-3057135.eml, golair/it-3129283.eml, golair/it-3230859.eml, golair/it-4226584.eml, golair/it-4226598.eml, golair/it-5186609.eml, golair/it-5189902.eml, golair/it-5233061.eml, golair/it-6170272.eml";

    public $reHtml = "";
    public $rePDF = "";
    public $fnLanguage = "";
    public $isAggregator = "";
    public $caseReference = "";
    public $xPath = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";
    public $cancelled;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // dictionary for parsing

                    $this->lang = 'en';
                    $this->dictionary = [
                        'en' => [
                            'langDetect'      => 'Thank you for choosing GOL',
                            'RecordLocator'   => '(?:locator|RESERVATION\s+CODE\s*\(locator\))[:\s]*',
                            'Date'            => ['Date:', 'Subject:', '/\s+at\s+/'], 'FLoc' => 'LOCATOR',
                            'Passengers'      => ['Passengers', 'Name Flight Seat'],
                            'Status'          => ['Ticket status:', 'Payment status:'],
                            'ReservationDate' => ['Purchase date:', 'Ticket status:'],
                            'segmentsTrip'    => ['Itinerary', '(?:Your +flight|Passengers\s+Name\s+Flight)', 'flight'], 'FlightNumber' => 'flight',
                            'FlightLocator'   => 'Operated by:?', 'TotalCharge' => 'TOTAL TRIP AMOUNT',
                            'SeatsNA'         => 'N\/A',
                            'Cancelled'       => 'Cancelled', //maybe need correct, not met yet
                        ],
                        'pt' => [
                            'langDetect'      => 'Obrigado por escolher voar GOL',
                            'RecordLocator'   => '(?:CÓDIGO\s+DE\s+RESERVA\s*\(localizador\)|LOCALIZADOR\s+GOL|Localizador)[:\s]*',
                            'Date'            => ['Data:', 'Assunto:', '/\s+de\s+/'],
                            'FLoc'            => 'LOCALIZADOR',
                            'Passengers'      => ['Passageiros', 'Nome Voo Poltrona'],
                            'Status'          => ['Situação da Passagem:', 'Situação do Pagamento:'],
                            'ReservationDate' => ['Data da compra:', 'Situação da Passagem:'],
                            'segmentsTrip'    => ['Itinerário', '(?:Seu\s+voo|Passageiros\s+Nome\s+Voo)', 'voo'],
                            'FlightNumber'    => 'voo',
                            'FlightLocator'   => 'Operado por:?',
                            'TotalCharge'     => 'TOTAL DA VIAGEM',
                            'SeatsNA'         => 'Não +marcado',
                            'Cancelled'       => 'Cancelada',
                        ],
                        'es' => [
                            'langDetect'      => 'Gracias por elegir volar por GOL',
                            'RecordLocator'   => '(?:CÓDIGO\s+DE\s+RESERVA\s*\(localizador\)|LOCALIZADOR\s+GOL|Localizador)[:\s]*',
                            'Date'            => ['Date:', 'Subject:', '/\s+at\s+/'], 'FLoc' => 'LOCALIZADOR',
                            'Passengers'      => ['Pasajeros', 'Nombre Vuelo Asiento'],
                            'Status'          => ['Situación del pasaje:', 'Situación del pago:'],
                            'ReservationDate' => ['Fecha de la compra:', 'Situación del pasaje:'],
                            'segmentsTrip'    => ['Itinerario', '(?:Su +vuelo|Pasajeros\s+Nombre\s+Vuelo)', 'vuelo'], 'FlightNumber' => 'vuelo',
                            'FlightLocator'   => 'Operado por:?', 'TotalCharge' => 'TOTAL DEL VIAJE',
                            'SeatsNA'         => 'Sin marcar',
                            'Cancelled'       => 'Cancelado', //maybe need correct, not met yet
                        ],
                    ];

                    foreach ($this->dictionary as $lang => $dict) {
                        if (nodes('//*[contains(normalize-space(.),"' . $dict['langDetect'] . '")]')) {
                            $this->lang = $lang;

                            break;
                        }
                    }

                    if (!isset($this->dictionary[$this->lang])) {
                        return null;
                    }
                    $this->dict = $this->dictionary[$this->lang];
                    // end of the dictionary

                    $date = between($this->dict['Date'][0], $this->dict['Date'][1]);
                    $date = preg_replace($this->dict['Date'][2], ' ', $date);
                    $this->forward_date = strtotime($date);

                    // getting flight locators
                    $this->locators = [];

                    if (preg_match_all("#\n\s*" . $this->dict['FLoc'] . "\s+([^\n]+?)\s*:\s*([\w-]+)#", $text, $m, PREG_SET_ORDER)) {
                        foreach ($m as $massiv) {
                            $this->locators[$massiv[1]] = $massiv[2];
                        }
                    }

                    $this->seats = [];

                    if ($tot = re("#{$this->dict['TotalCharge']}\s+(\w+\s*[\d\,\.]+)#ms")) {
                        $this->parsedValue('TotalCharge', total($tot, 'Amount'));
                    }

                    if ($tot = cell($this->dict['TotalCharge'], +1)) {
                        $this->parsedValue('TotalCharge', total($tot, 'Amount'));
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*" . $this->dict['RecordLocator'] . "\s*([\w-]+)\s*?\n#i");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//*[contains(text(), '" . $this->dict['Passengers'][0] . "')]/following::table[1]//tr[contains(normalize-space(.), '" . $this->dict['Passengers'][1] . "')]/following-sibling::tr/td[string-length(normalize-space(.)) > 1][1]");

                        // seats
                        $flightNumbers = [];
                        $seats = [];
                        $raw = text(xpath("//*[contains(text(), '" . $this->dict['Passengers'][0] . "')]/following::table[1]//tr[contains(normalize-space(.), '" . $this->dict['Passengers'][1] . "')]/following-sibling::tr/td[string-length(normalize-space(.)) > 1][1]/.."));

                        if (preg_match_all("#\s([A-Z\d]{2}\d{1,5})\s#", $raw, $m)) {
                            $flightNumbers = $m[1];
                        }

                        if (preg_match_all("#\s(\d{1,3}[A-Z]|" . $this->dict['SeatsNA'] . ")\s#", $raw, $m)) {
                            $seats = $m[1];
                        }

                        if (count($flightNumbers) == count($seats)) {
                            for ($i = 0; $i < count($seats); $i++) {
                                if (preg_match("#^\d+[A-Z]$#", $seats[$i])) {
                                    $this->seats[$flightNumbers[$i]][] = $seats[$i];
                                }
                            }
                        }

                        return array_map(function ($val) { return beautifulName($val); }, $ppl);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $status = trim(between($this->dict['Status'][0], $this->dict['Status'][1]));

                        if ($status === $this->dict['Cancelled']) {
                            $this->cancelled = true;

                            return ["Status" => $status, 'Cancelled' => true];
                        } else {
                            $this->cancelled = false;
                        }

                        return $status;
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = between($this->dict['ReservationDate'][0], $this->dict['ReservationDate'][1]);
                        $date = strtotime(en($date, $this->lang));
                        $this->purchase_date = $date;

                        return $date;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        /*
                        $its = xpath("//img[contains(@src, 'icoAviao.jpg')]/ancestor::table[1]");
                        if($its->length > 0 && preg_match_all("#\n\s*".$this->dict['segmentsTrip']."\s+\w+[\s\-]+\d+\s*\n#i", text($its->item(0)), $m) && count($m[0]) > 1)
                            $its = xpath("//img[contains(@src, 'icoAviao.jpg')]/ancestor::table[1]/tbody/tr");
                            */
                        //						$itsRaw = re("#\n\s*".$this->dict['segmentsTrip'][0]."\s*?(\n\s*\d+\s+\S+\s*?\n.+?\n)\s*".$this->dict['segmentsTrip'][1]."\s#si");
                        $itsRaw = re("#\s*" . $this->dict['segmentsTrip'][0] . "\s*?(\s*\d+\s+\S+\s*?.+?)\s*" . $this->dict['segmentsTrip'][1] . "\s#si");

                        return splitter("#(\n\s*\d+\s+\S+\s+(?:[A-Z]{3}\s+[A-Z]{3}\s+" . $this->dict['segmentsTrip'][2] . "\s|" . $this->dict['segmentsTrip'][2] . "\s+\w{2}\s*\-\s*\d+\s*?\n))#i", $itsRaw);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $flnum = re('/' . $this->dict['FlightNumber'] . '\s+\w+\s*-\s*(\d+)/i');

                            if ($this->cancelled && empty($flnum)) {
                                $flnum = 'none';
                            } //it's only when reservation Cancelled

                            return $flnum;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $code = orval(
//								re("#\d+\s+\w+\s*\n\s*([A-Z]{3})\s*\n\s*([A-Z]{3})\s*\n\s*(?i:".$this->dict['FlightNumber'].")\s#"),
                                re("#\d+\s+\w+\s*([A-Z]{3})\s*([A-Z]{3})\s*(?i:" . $this->dict['FlightNumber'] . ")\s#"),
                                re("#\n\s*([A-Z]{3})\s*\n\s*[^\n]+?\s+\d+\/\d+\s+\d+:\d+\s+\w+#"),
                                re("#\n\s*([A-Z]{3})\s*\n?\s*[^\n]+?\s+\d+\/\d+\s+\d+:\d+\s+\w+#"),
                                re('/([A-Z]{3})\s+\D+\s+\d+\/\d+\s+\d+:\d+\s+([A-Z]{3})/')
                            );

                            if ($this->cancelled && empty($code)) {
                                $code = 'NNN';
                            } //it's only when reservation Cancelled

                            return nice($code);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $year = date('Y', $this->purchase_date);
                            $date = re('#\s(\d{2})/(\d{2})\s*(\d+:\d+ *+\w*)#', text($text), 2) . '/' . re(1) . '/' . $year . ', ' . re(3);
                            $date = strtotime($date);

                            if ($date < $this->purchase_date) {
                                $date = strtotime('+1 year', $date);
                            }

                            return $date;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $code = orval(
                                re("#\d+:\d+\s*\w*\s*\n\s*([A-Z]+)\s*\n\s*[^\n]+?\s+\d+\/\d+\s+\d+:\d+\s*\w*#"),
                                re("#" . $this->dict['segmentsTrip'][2] . "\s.+?[A-Z]{3}.+?([A-Z]{3})#s"),
                                re("#\d+\s+\w+\s*[A-Z]+\s*([A-Z]+)\s*(?i:" . $this->dict['FlightNumber'] . ")\s#s"),
                                re("#\d+:\d+\s*\w*\s*\n\s*([A-Z]+)\s*\n?\s*[^\n]+?\s+\d+\/\d+\s+\d+:\d+\s*\w*#")
                            );

                            if ($this->cancelled && empty($code)) {
                                $code = 'NNN';
                            }//it's only when reservation Cancelled

                            return nice($code);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $year = date('Y', $this->purchase_date);
                            $date = re('#\s\d{2}\/\d{2}\s*\d+:\d+.*?\s*(\d{2})\/(\d{2})\s*(\d+:\d+(\s*[AMP]{2})?)#s', text($text), 2) . '/' . re(1) . '/' . $year . ', ' . re(3);
                            $date = strtotime($date);

                            if ($date < $this->purchase_date) {
                                $date = strtotime('+1 year', $date);
                            }

                            return $date;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re('/' . $this->dict['FlightNumber'] . '\s+(\w+)\s*-\s*\d+/i');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $key = $it['AirlineName'] . $it['FlightNumber'];

                            return (isset($this->seats[$key])) ? $this->seats[$key] : null;
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            if (re("#\s" . $this->dict['FlightLocator'] . "\s*([^\n]+?)\s*?\n#")) {
                                if (isset($this->locators[re(1)])) {
                                    return $this->locators[re(1)];
                                }
                            }
                        },
                    ],
                ],
                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $itNew = [];

                    foreach ($it as $i) {
                        if (isset($i['Cancelled']) && $i['Cancelled']) {
                            unset($i['TripSegments']);
                        }
                        $itNew[] = $i;
                    }

                    return $itNew;
                },
            ],
        ];
    }

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'voegol@voegol.com.br') !== false
            && stripos($headers['subject'], 'Alerta GOL - Itinerário de Viagem') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $phrases = [
            'Thank you for choosing GOL',
            'Obrigado por escolher voar GOL',
            'Gracias por elegir volar por GOL',
        ];

        foreach ($phrases as $phrase) {
            if ($this->http->XPath->query('//node()[contains(.,"' . $phrase . '")]')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@voegol.com.br') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'pt', 'es'];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }
}
