<?php

namespace AwardWallet\Engine\skywards\Email;

class It2003604 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?emirates#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reBody = "emirates";
    public $reBody2 = [
        "fr" => ["Carte d’embarquement", "Merci d'avoir effectué votre réservation en ligne sur"],
        "it" => "Carta d'imbarco",
        "en" => ["Boarding Pass", "Itinerary Details"],
        "es" => ["Tarjeta de embarque", "Este itinerario ha sido enviado"],
        "zh" => "搭乗券",
    ];
    public $reSubject = [
        "Conferma della carta d'imbarco Emirates",
        "Emirates Airline Boarding Pass Confirmation",
        "Confirmation de la carte d’embarquement Emirates Airline",
        "Emirates boarding pass confirmation",
        "Confirmación de la tarjeta de embarque de Emirates Airline",
        "エミレーツ航空搭乗券の確認",
    ];
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#emirates#i";
    public $reProvider = "#emirates#i";
    public $caseReference = "6628";
    public $xPath = "";
    public $mailFiles = "skywards/it-17411608.eml, skywards/it-1989375.eml, skywards/it-2003604.eml, skywards/it-4237781.eml, skywards/it-4241062.eml, skywards/it-4262147.eml, skywards/it-4313841.eml, skywards/it-4396280.eml, skywards/it-4445758.eml, skywards/it-4685833.eml, skywards/it-5636467.eml, skywards/it-5660695.eml, skywards/it-6815161.eml";
    public $pdfRequired = "0";

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (preg_match("#{$re}#iu", $headers["subject"])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            $reB = (array) $re;

            foreach ($reB as $r) {
                if (strpos($body, $r) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $confNo = node("//*[
							contains(text(), 'BOOKING REFERENCE') or
							contains(text(), 'Booking Reference') or
							contains(text(), 'Booking reference') or
							contains(text(), 'Référence de réservation') or
							contains(text(), 'Référence de la réservation') or
							contains(text(), 'Referencia de la reserva') or
							contains(text(), 'Código de referencia de la reserva') or
							contains(text(), 'Codice prenotazione') or
							contains(text(), 'Codice di prenotazione') or
							contains(text(), 'Reservation reference') or
							contains(text(), '予約番号')
						]/ancestor::tr[1]/following-sibling::tr[1]", null, true, "#^\s*([A-Z\d\-]+)\s*$#");

                        if (empty($confNo)) {
                            $confNo = node("//text()[
							contains(normalize-space(.), 'BOOKING REFERENCE') or
							contains(normalize-space(.), 'Booking Reference') or
							contains(normalize-space(.), 'Booking reference') or
							contains(normalize-space(.), 'Référence de réservation') or
							contains(normalize-space(.), 'Référence de la réservation') or
							contains(normalize-space(.), 'Referencia de la reserva') or
							contains(normalize-space(.), 'Código de referencia de la reserva') or
							contains(normalize-space(.), 'Codice prenotazione') or
							contains(normalize-space(.), 'Codice di prenotazione') or
							contains(normalize-space(.), 'Reservation reference') or
							contains(normalize-space(.), '予約番号')
						]/following::text()[normalize-space(.)!=''][1]", null, true, "#^\s*([A-Z\d\-]+)\s*$#");
                        }

                        return $confNo;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $strs = nodes("//*[
							contains(text(), 'Passenger') or
							contains(text(), 'Passager') or
							contains(text(), 'Pasajero') or
							contains(text(), 'Passeggero') or
							contains(text(), 'ご搭乗者')
						]/ancestor::tr[1]/following-sibling::tr[1][count(descendant::td[normalize-space(.)])=1]");

                        if (count($strs) > 0) {
                            return $strs;
                        } else {
                            $strs = nodes("//*[
							contains(text(), 'Passenger') or
							contains(text(), 'Passager') or
							contains(text(), 'Pasajero') or
							contains(text(), 'Passeggero') or
							contains(text(), 'ご搭乗者')
						]/ancestor::tr[1][contains(.,'-')]", null, "#\d+[\s\-]+(.+)#");
                        }

                        if (count($strs) > 0) {
                            return $strs;
                        } else {
                            return nodes("//text()[normalize-space()='Flight Number']/ancestor::table[1]/preceding-sibling::table[1][count(descendant::td[normalize-space(.)!=''])=1]");
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[
							contains(text(), 'Arrive') or
							contains(text(), 'Arrivée') or
							contains(text(), 'Llegada') or
							contains(text(), 'Partenza') or
							contains(text(), '到着')
						]/ancestor::tr[
							contains(.,'Depart') or
							contains(., 'Départ') or
							contains(., 'Salida') or
							contains(., 'Arrivo') or
							contains(., '出発')
						][1]/following-sibling::tr[
							not(contains(., 'Connection')) and
							not(contains(., 'Conexión')) and
							not(contains(., 'Correspondance'))
						][string-length(.)>5][position() mod 2>0]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(text($text));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            if (!empty(node("following-sibling::tr[1]/td[string-length(normalize-space(.))>2][3]"))) {
                                $res['DepCode'] = re("#\(([A-Z]{3})\)#");
                                $res['DepName'] = trim(clear("#\([A-Z]{3}\)#",
                                    node("td[string-length(normalize-space(.))>2][4]")));

                                if (preg_match("#(.+)\s+Terminal\s+(.+)#i", $res['DepName'], $m)) {
                                    $res['DepName'] = $m[1];
                                    $res['DepartureTerminal'] = $m[2];
                                }
                                $date = node('td[string-length(normalize-space(.))>2][2]', $node, true,
                                    "#\d+\s+[^\d\s]+\s+\d+|[^\d\s]+\s+\d+\s+\d+\s+\d{2}#");
                                $res['DepDate'] = strtotime(node('td[string-length(normalize-space(.))>2][3]'),
                                    strtotime($this->normalizeDate($date)));

                                $res['ArrCode'] = re("#\(([A-Z]{3})\)#", xpath("following-sibling::tr[1]"));
                                $res['ArrName'] = trim(clear("#\([A-Z]{3}\)#",
                                    node("following-sibling::tr[1]/td[string-length(normalize-space(.))>2][3]")));

                                if (preg_match("#(.+)\s+Terminal\s+(.+)#i", $res['ArrName'], $m)) {
                                    $res['ArrName'] = $m[1];
                                    $res['ArrivalTerminal'] = $m[2];
                                }
                                $date = node('following-sibling::tr[1]/td[string-length(normalize-space(.))>2][1]',
                                    $node, true, "#\d+\s+[^\d\s]+\s+\d+|[^\d\s]+\s+\d+\s+\d+\s+\d{2}#");
                                $res['ArrDate'] = strtotime(node('following-sibling::tr[1]/td[string-length(normalize-space(.))>2][2]'),
                                    strtotime($this->normalizeDate($date)));
                            } else {
                                $res = null;
                                $subj = node("td[string-length(normalize-space(.))>2][4]");

                                if (preg_match("#Departure(?: Airport)?\s+(.+)\s+\(([A-Z]{3})\)\s+Arrival(?: Airport)?\s+(.+)\s+\(([A-Z]{3})\)#s",
                                    $subj, $m)) {
                                    $res['DepName'] = $m[1];
                                    $res['DepCode'] = $m[2];
                                    $res['ArrName'] = $m[3];
                                    $res['ArrCode'] = $m[4];
                                }
                                $subj = node('td[string-length(normalize-space(.))>2][2]');

                                if (preg_match("#Departure(?: date)?\s+(\w+\s+\d+\s+[^\d\s]+\s+\d+|[^\d\s]+\s+\d+\s+\d+\s+\d{2})\s+Arrival(?: date)?\s+(\w+\s+\d+\s+[^\d\s]+\s+\d+|[^\d\s]+\s+\d+\s+\d+\s+\d{2})#s",
                                    $subj, $m)) {
                                    $dateDep = $this->normalizeDate($m[1]);
                                    $dateArr = $this->normalizeDate($m[2]);
                                    $subj = node('td[string-length(normalize-space(.))>2][3]');

                                    if (preg_match("#Departure(?: time)?\s+(\d+:\d+(?:\s*[ap]m)?)\s+Arrival(?: time)?\s+(\d+:\d+(?:\s*[ap]m)?)#si",
                                        $subj, $m)) {
                                        $res['DepDate'] = strtotime($m[1], strtotime($dateDep));
                                        $res['ArrDate'] = strtotime($m[2], strtotime($dateArr));
                                    }
                                }
                            }

                            return $res;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            if (isset($it['FlightNumber'],$it['AirlineName'])) {
                                $flight = $it['AirlineName'] . $it['FlightNumber'];

                                return array_filter(nodes("//text()[normalize-space()='Flight Number']/ancestor::table[1]/descendant::td[normalize-space(.)='{$flight}']/following-sibling::td[last()]", null, "#^\s*(\d+[A-z])\s*$#"));
                            }
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            if (isset($it['FlightNumber'], $it['AirlineName'])) {
                                $flight = $it['AirlineName'] . $it['FlightNumber'];
                                $meal = implode(',',
                                    array_unique(array_filter(nodes("//text()[normalize-space()='Flight Number']/ancestor::table[1]/descendant::td[normalize-space(.)='{$flight}']/following-sibling::td[last()-1]",
                                        null), function ($s) {
                                            return strpos($s, 'Not available') === false;
                                        })));

                                if (!empty($meal)) {
                                    return $meal;
                                }
                            }
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $strs = nodes("td[string-length(normalize-space(.))>2][6]//text()[string-length(normalize-space(.))>2]");
                            $res = [];

                            if (count($strs) > 0) {
                                $res['Cabin'] = array_shift($strs);
                                $res['Aircraft'] = implode(" ", $strs);
                            }

                            return $res;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            if (!isset($it['Aircraft']) || empty($it['Aircraft'])) {
                                return node("following-sibling::tr[1]/td[string-length(normalize-space(.))>2][5]");
                            } else {
                                return $it['Aircraft'];
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $str = node("td[string-length(normalize-space(.))>2][5]");

                            if (preg_match("#^\s*(\d+\s*h.*?\s+\d+\s*m.*?)(?:\s+(\d+)\s+.+)?$#", $str, $m)) {
                                $res['Duration'] = $m[1];

                                if (isset($m[2])) {
                                    $res['Stops'] = $m[2];
                                }

                                return $res;
                            } else {
                                return $str;
                            }
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            if (!isset($it['Stops'])) {
                                $str = node("following-sibling::tr[1]/td[string-length(normalize-space(.))>2][4]");
                                $d = re("#(\d+).*#", $str);

                                return re("#Non\-stop#i", $str) ? 0 : ($d !== null ? $d : null);
                            } else {
                                return $it['Stops'];
                            }
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 5;
    }

    public static function getEmailLanguages()
    {
        return ["en", "fr", "es", "it", "zh"];
    }

    private function normalizeDate($date)
    {
        $in = [
            "#[^\d\s]+\s+(\d+)\s+(\d+)\s+(\d{2})#",
            '#^.*?(\d+)\s+(\w+)\.?+\s+(\d{2})\s*$#u',
            '#^.*?(\d+)\s+(\w+)\.?+\s+(\d{4})\s*$#u',
        ];
        $out = [
            "$1.$2.20$3",
            '$1 $2 20$3',
            '$1 $2 $3',
        ];
        $str = en(preg_replace($in, $out, $date));

        return $str;
    }
}
