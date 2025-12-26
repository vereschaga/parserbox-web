<?php

namespace AwardWallet\Engine\tamair\Email;

class It1676684 extends \TAccountCheckerExtended
{
    public $mailFiles = "tamair/it-1839114.eml, tamair/it-1839119.eml, tamair/it-3050356.eml, tamair/it-3110566.eml, tamair/it-3185062.eml, tamair/it-4688710.eml, tamair/it-6110117.eml, tamair/it-6599405.eml";

    public $reFrom = 'no-reply@tam.com.br';
    public $reSubject = [
        'pt'  => 'Confirmação de pedido',
        'pt2' => 'Confirmação de seu pedido de voo com a LATAM',
    ];
    public $reBody = [
        'Confirmación de la Reserva',
        'Confirmação de reserva',
        'Confirmação de pedido',
        'Confirmation de votre réservation',
        'e-Mail Confirmação do pedido',
        'Obrigado por escolher a LATAM Airlines Brasil',
    ];
    public $seatSegments;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\n\s*Your order code is\s*([\d\w\-]+)#"),
                            re("#\n\s*Código do pedido:\s*([A-Z\d\-]+)#"),
                            re("#\n\s*Código de reserva\s*:?\s*([A-Z\d\-]+)#"),
                            re("#\n\s*Votre code de réservation est\s*:?\s*([A-Z\d\-]+)#"),
                            re("#Confirmação de seu pedido de voo com.+?([A-Z\d\-]+)\s*$#", $this->parser->getSubject())
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengers = [];
                        $this->seatSegments = [];

                        $passengerSegments = $this->http->XPath->query('//tr[(contains(.,"Passenger") or contains(.,"Pasajero") or contains(.,"Passager") or contains(.,"Passageiro")) and (contains(.,"Assento") or contains(.,"Asiento") or contains(.,"Siège")) and not(.//tr)]');

                        foreach ($passengerSegments as $passengerSegment) {
                            $seats = [];
                            $passengerRows = $this->http->XPath->query('./following-sibling::tr[string-length(normalize-space(.))>1]', $passengerSegment);

                            foreach ($passengerRows as $passengerRow) {
                                if ($this->http->XPath->query('(.)[count(./td)>2]', $passengerRow)->length > 0) {
                                    $passengers[] = $this->http->FindSingleNode('./td[1]', $passengerRow);

                                    if ($seat = $this->http->FindSingleNode('./td[3]', $passengerRow, true, '/^\s*([\dA-Z]{2,3})\s*/')) {
                                        $seats[] = $seat;
                                    }
                                } else {
                                    $this->seatSegments[] = $seats;

                                    continue 2;
                                }
                            }
                            $this->seatSegments[] = $seats;
                        }

                        if (empty($passengers[0])) {
                            $passengers = $this->http->FindNodes('(//table[(starts-with(normalize-space(.),"Passageiro") or starts-with(normalize-space(.),"Pasajero") or starts-with(normalize-space(.),"Passager")) and count(.//tr[normalize-space(.)!=""])=1 and not(.//table)]/following-sibling::table[1]//td)[1]//tr[not(.//tr)]//span[1]');
                        }

                        return array_unique($passengers);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $value = orval(
                            re("#\n\s*Total:\s+For\s+all\s+passengers\s+including\s+taxes\s+and\s+fees:\s+([^\n]+)#"),
                            re("#\n\s*Total:\s*Para\s*todos\s*os\s*passageiros,\s*incluindo\s*taxas\s*([^\n]+)#"),
                            re("#\n\s*Total\s*:\s*Taxes comprises pour tous les passagers\s*([^\n]+)#"),
                            node("//*[normalize-space(text())='Total Pago']/following-sibling::td[1]/span[2]"),
                            node("//text()[contains(normalize-space(.),'Dados do Pagamento')]/following::text()[normalize-space(.)][1]")
                        );

                        if (preg_match('/\+\s*([^\n]+)$/', $value, $m)) {
                            $value = $m[1];
                        }
                        $result = total($value);

                        if (preg_match('/([^\d]+)[,.\d\s]+$/', $value, $matches)) {
                            $currency = trim($matches[1]);
                        } elseif (preg_match('/^[,.\d\s]+([^\d]+)/', $value, $matches)) {
                            $currency = trim($matches[1]);
                        }

                        $baseFare_temp = node("//*[contains(text(),'Total travelers:') or contains(text(),'Total Geral:') or contains(text(),'Total général')]/ancestor-or-self::td[1]/following-sibling::td[last()]");

                        if (preg_match('/[' . $currency . ']+([,.\d\s]+)$/', $baseFare_temp, $matches)) {
                            $result['BaseFare'] = cost($matches[1]);
                        } elseif (preg_match('/^([,.\d\s]+)[' . $currency . ']+/', $baseFare_temp, $matches)) {
                            $result['BaseFare'] = cost($matches[1]);
                        }

                        $tax_temp = re("#\n\s*(?:Total em Taxas|Total fees):\s*.*?\=\s*(.*?[\d.,]+)#");

                        if (preg_match('/[' . $currency . ']+([,.\d\s]+)$/', $tax_temp, $matches)) {
                            $result['Tax'] = cost($matches[1]);
                        } elseif (preg_match('/^([,.\d\s]+)[' . $currency . ']+/', $tax_temp, $matches)) {
                            $result['Tax'] = cost($matches[1]);
                        }

                        return $result;
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        $value = orval(
                            re("#\n\s*Total:\s+For\s+all\s+passengers\s+including\s+taxes\s+and\s+fees:\s+([^\n]+)#"),
                            re("#\n\s*Total:\s*Para\s*todos\s*os\s*passageiros,\s*incluindo\s*taxas\s*([^\n]+)#")
                        );

                        if (re("#^(.*?)\+\s*([^\n]+)$#", $value)) {
                            return nice(re(1));
                        }

                        if ($points = node("(//*[normalize-space(text())='Total de Tarifas']/following-sibling::td[1])[1]")) {
                            return $points;
                        }

                        return null;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $this->seatSegmentsCounter = -1;

                        return xpath("//*[normalize-space(text())='Partida:' or normalize-space(text())='Saída:' or normalize-space(text())='Embarque:' or normalize-space(text())='Aller:' or normalize-space(text())='Partida']/ancestor::tr[2]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node(".//*[normalize-space(text())='Número do Voo' or normalize-space(text())='Número do Voo:' or normalize-space(text())='Número de Vuelo:' or normalize-space(text())='Numéro du vol :']/following::*[1]"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $header = node("./preceding-sibling::tr[contains(.,'IDA') or contains(.,'Ida') or contains(.,'Trecho') or contains(.,'Seu próximo voo decola em') or contains(.,'Llegada') or contains(.,'Retorno') or contains(.,'RETORNO') or contains(.,'VOLTA') or contains(.,'Vers:')][1]");

                            if (preg_match('/:([^:]+)\(([A-Z]{3})\)[^:]+:([^:]+)\(([A-Z]{3})\)[^:]+$/', $header, $matches)) {
                                $cityDep = mb_strtolower(trim($matches[1]), 'UTF-8');
                                $codeDep = $matches[2];
                                $cityArr = mb_strtolower(trim($matches[3]), 'UTF-8');
                                $codeArr = $matches[4];
                            }
                            $depName = nice(cell(['Outbound:', 'Saída:', 'Embarque:', 'Partida:', 'Aller', 'Partida'], +1, 0));
                            $arrName = nice(cell(['Arrival:', 'Chegada:', 'Llegada', 'Chegada', 'Arrivée'], +1, 0));
                            $depCity = mb_strtolower(trim(explode(',', $depName)[0]), 'UTF-8');
                            $arrCity = mb_strtolower(trim(explode(',', $arrName)[0]), 'UTF-8');

                            if ($cityDep === $depCity) {
                                $depCode = $codeDep;
                            } else {
                                $depCode = TRIP_CODE_UNKNOWN;
                            }

                            if ($cityArr === $arrCity) {
                                $arrCode = $codeArr;
                            } else {
                                $arrCode = TRIP_CODE_UNKNOWN;
                            }

                            $date = en(uberDate($header));

                            if (!$date) {
                                return;
                            }
                            $depDate = strtotime($date . ', ' . uberTime(1), $this->date);
                            $arrDate = strtotime($date . ', ' . uberTime(2), $this->date);
                            correctDates($depDate, $arrDate);

                            return [
                                'DepName' => $depName,
                                'ArrName' => $arrName,
                                'DepCode' => $depCode,
                                'ArrCode' => $arrCode,
                                'DepDate' => $depDate,
                                'ArrDate' => $arrDate,
                            ];
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*(?:Aircraft|Aeronave|Aéronef)\s*:?\s*([^\n]+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*(?:Cabin|Classe de Serviço|Clase de Servicio|Classe)\s*:?\s*([^\n]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*(?:Duration|Duração do Voo|Duração total da viagem|Duración del Vuelo|Durée totale)[:\s]+([^\n]+)#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $this->seatSegmentsCounter++;

                            return implode(', ', $this->seatSegments[$this->seatSegmentsCounter]);
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->reFrom) !== false) {
            return true;
        }

        if (strpos($headers['subject'], 'TAM') === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//a[contains(@href,".latam.com/") or contains(@href,".tam.com.br/")]')->length === 0;
        $condition2 = $this->http->XPath->query('//node()[contains(.,"TAM Airlines") or contains(.,"TAM Linhas")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        foreach ($this->reBody as $re) {
            if ($this->http->XPath->query('//node()[contains(.,"' . $re . '")]')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tam.com.br') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['pt', 'es', 'fr'];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }
}
