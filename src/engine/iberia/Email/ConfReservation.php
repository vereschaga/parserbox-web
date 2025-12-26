<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Engine\MonthTranslate;

class ConfReservation extends \TAccountChecker
{
    public $mailFiles = "iberia/it-10002357.eml, iberia/it-10084933.eml, iberia/it-1820988.eml, iberia/it-4432598.eml, iberia/it-8004724.eml, iberia/it-8509375.eml, iberia/it-8537573.eml, iberia/it-8553036.eml, iberia/it-8558117.eml, iberia/it-8583949.eml, iberia/it-8637234.eml, iberia/it-8672239.eml";
    public $monthNames = [
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        'es' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
        'pt' => ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'],
    ];

    public $reBody = [
        'en'  => ['Your trip', 'Confirmation code'],
        'en2' => ['Your seat selection', 'Your purchase has been successfully'],
        'pt'  => ['A sua viagem', 'Código de confirmação'],
        'es'  => ['Tu viaje', 'Código de confirmación'],
        'es2' => ['Salida', 'Tu compra se ha realizado correctamente'],
    ];
    public $reSubject = [
        'en' => ['Seat booking confirmation', 'The receipts of your purchase for the booking'],
        'pt' => ['Confirmação de reserva de lugares'],
        'es' => ['Confirmación de reserva asientos', 'Los recibos de su compra para la reserva'],
    ];
    public $pdfPattern = "\d+\.pdf";
    public $pdfInfo = [];

    public $lang = 'en';
    public $dateLang = ['ru', 'it', 'fr', 'de', 'ca']; // Languages on which there may be a date, and which are not in the $dictionary
    public static $dict = [
        'en' => [
            'Confirmation code:' => 'Confirmation code:',
            'Depart'             => 'Departure',
            'Operated by'        => 'Flight operated by',
            'Seat'               => 'Seat',
            'Cabin:'             => ['Cabin:', 'Cabin :'],
            'Class upgrading:'   => 'Class upgrading:',
            'Arrival'            => 'Arrival',
        ],
        'pt' => [
            'Confirmation code:' => 'Código de confirmação:',
            'Depart'             => 'Saída',
            'Operated by'        => 'Voo operado por',
            'Seat'               => 'Assento',
            //			'Cabin:' => '',
            //			'Class upgrading:' => '',
            'Arrival' => '',
        ],
        'es' => [
            'Confirmation code:' => 'Código de confirmación:',
            'Depart'             => 'Salida',
            'Operated by'        => 'Vuelo operado por',
            'Seat'               => 'Asiento',
            'Cabin:'             => ['Cabina:', 'Cabina :'],
            //			'Class upgrading:' => '',
            'Arrival' => 'Llegada',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if ($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) {
                $pos = strpos($text, 'Código de Reserva / Booking code');
                $info = substr($text, $pos, 200);

                if (preg_match("#Código de Reserva / Booking code\s+.*\s([A-Z\d]+)\s*\n#", $info, $m)) {
                    $this->pdfInfo["RecordLocator"] = $m[1];
                }
                $pos = strpos($text, 'Número de EMD / EMD number');
                $info = substr($text, $pos, 200);

                if (preg_match("#Número de EMD / EMD number\s+.*\n\s*([\d\-]+)\s*\n#", $info, $m)) {
                    $this->pdfInfo["TicketNumbers"][] = $m[1];
                }
            }
        }
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ConfReservation_" . $this->lang,
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, "iberia.com") === false) {
            return false;
        }
        $this->AssignLang($body);

        return $this->AssignLang($body);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject) && isset($headers['subject'])) {
            foreach ($this->reSubject as $lang => $reSubject) {
                foreach ($reSubject as $re) {
                    if (stripos($headers['subject'], $re) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "iberia") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->nextText($this->t("Confirmation code:"));

        if (empty($this->http->FindSingleNode("//text()[contains(.,'" . $this->t("Confirmation code:") . "')]"))) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        if ($it['RecordLocator'] == CONFNO_UNKNOWN && isset($this->pdfInfo["RecordLocator"])) {
            $it['RecordLocator'] = $this->pdfInfo["RecordLocator"];
        }

        if (isset($this->pdfInfo["TicketNumbers"])) {
            $it['TicketNumbers'] = array_unique($this->pdfInfo["TicketNumbers"]);
        }
        $it['Passengers'] = array_unique($this->http->FindNodes("//img[contains(@src,'pasajero.png')]/ancestor::td[1]/following-sibling::td"));
        $rows = $this->http->XPath->query("//*[contains(text(),'" . $this->t('Depart') . "')]/ancestor::tr[1][contains(.,'" . $this->t('Arrival') . "')]");

        foreach ($rows as $row) {
            $seg = [];
            //        FlightNumber, AirlineName
            $node = implode(" ", $this->http->FindNodes("./td[2]//text()[string-length(normalize-space(.))>1]", $row));

            if (preg_match("#(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)(?:\s+" . $this->t('Operated by') . ")?\s+(?<Operator>.+)#i", $node, $m)) {
                $seg['AirlineName'] = $m['AirlineName'];
                $seg['FlightNumber'] = $m['FlightNumber'];
                $seg['Operator'] = $m['Operator'];
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::img[@alt='icono vuelo' or contains(@src, 'destino.png')][1]/ancestor::td[1]/following::td[1]/descendant::text()[normalize-space(.)][last()]", $row));

            if (!strtotime($date)) {
                $date = $this->normalizeDate($this->checkDate(implode(" ", $this->http->FindNodes("./preceding::img[@alt='icono vuelo' or contains(@src, 'destino.png')][1]/ancestor::td[1]/following::td[1]/descendant::text()", $row))));

                if (!strtotime($date)) {
                    $this->logger->info("Date not found or not translated");

                    continue;
                }
            }
            $node = implode(" ", $this->http->FindNodes("./td[3]//text()[string-length(normalize-space(.))>1]", $row));

            if (preg_match("#(?<DepTime>\d{1,2}\:\d{2})\s*(?:h|)\s*(?<DepName>.+)(?:\s+\((?<DepCode>[A-Z]{3})\))?$#U", $node, $m)) {
                $seg['DepDate'] = strtotime($date . " " . $m['DepTime']);
                $seg['DepName'] = trim($m['DepName']);

                if (!empty($m['DepCode'])) {
                    $seg['DepCode'] = $m['DepCode'];
                } else {
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }
            }
            $node = implode(" ", $this->http->FindNodes("./td[4]//text()[string-length(normalize-space(.))>1]", $row));

            if (preg_match("#(?<ArrTime>\d{1,2}\:\d{2})\s*(?:h|)\s*(?<ArrName>.+)(?:\s+\((?<ArrCode>[A-Z]{3})\))?$#U", $node, $m)) {
                $seg['ArrDate'] = strtotime($date . " " . $m['ArrTime']);
                $seg['ArrName'] = trim($m['ArrName']);

                if (!empty($m['ArrCode'])) {
                    $seg['ArrCode'] = $m['ArrCode'];
                } else {
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
            }

            //        Seats
            $seatsNodes = $this->http->XPath->query("./ancestor::table[2]/ancestor::tr[1]/following-sibling::tr", $row);
            $seats = [];

            foreach ($seatsNodes as $sRoot) {
                if ($this->http->XPath->query(".//text()[normalize-space() = '" . $this->t('Depart') . "'] | .//text()[normalize-space() = '" . $this->t('Arrival') . "']", $sRoot)->length > 1
                ) {
                    break;
                }
                $seats = array_merge($seats, $this->http->FindNodes(".//text()[contains(.,'" . $this->t('Seat') . "')][1]", $sRoot, "#" . $this->t('Seat') . "\s+(\d+[A-Z])\s+\(#"));

                $Cabin = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $sRoot));

                if (empty($seg['Cabin'])) {
                    if (preg_match("#" . $this->opt($this->t('Cabin:')) . "\s+(.*)\n#u", $Cabin, $m)) {
                        $seg['Cabin'] = $m[1];
                    } elseif (preg_match("#" . $this->t('Seat') . ".*\n\s*(.*)\s*$#", $Cabin, $m)) {
                        $seg['Cabin'] = $a[count($a = explode(" ¦ ", $m[1])) - 1];
                    } else {
                        $seg['Cabin'] = $this->http->FindSingleNode("./ancestor::table[2]/ancestor::tr[1]/following-sibling::tr//text()[contains(.,'" . $this->t('Class upgrading:') . "')]",
                            $row, true, "#" . $this->opt($this->t('Class upgrading:')) . "?\s+(.*)#i");
                    }
                }
            }
            $seats = array_filter($seats);

            if (!empty($seats)) {
                $seg['Seats'] = $seats;
            }

            // $seg['Seats'] = array_unique($this->http->FindNodes("./ancestor::table[2]/ancestor::tr[1]/following-sibling::tr//text()[contains(.,'" . $this->t('Seat') . "')][1]", $row, "#" . $this->t('Seat') . "\s+(\d+[A-Z])\s+\(#"));
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function checkDate($str)
    {
        $in = [
            "#(?:^|.*\s)([^\d\s]{3,},\s+[^\d\s]{3,}\s+\d{1,2},\s+\d{4})(?:$|\s.*)#u", //Sunday, May 4, 2014
            "#(?:^|.*\s)([^\d\s]{3,},?\s+\d{1,2}\s+[^\d\s]{3,}\s+\d{4})(?:$|\s.*)#u", //Saturday, 21 February 2015, vendredi 22 septembre 2017
            "#(?:^|.*\s)([^\d\s]{3,}\s+\d{1,2}\s+de\s+[^\d\s]{3,}\s+de\s+\d{4})(?:$|\s.*)#u", //lunes 29 de febrero de 2016
        ];
        $out = [
            "$1",
            "$1",
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }

    private function normalizeDate($str)
    {
        // $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#u", //Sunday, May 4, 2014
            "#^[^\d\s]+,?\s+(\d+)\.?\s+([^\d\s]+)\s+(\d{4})$#u", //Saturday, 21 February 2015; vendredi 22 septembre 2017
            "#^[^\d\s]+\s+(\d+)\s+de\s+([^\d\s]+)\s+de\s+(\d{4})$#u", //lunes 29 de febrero de 2016
        ];
        $out = [
            "$2 $1 $3",
            "$1 $2 $3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } else {
                $langAll = array_merge(array_keys(self::$dict), $this->dateLang);

                foreach ($langAll as $lang) {
                    if ($en = MonthTranslate::translate($m[1], $lang)) {
                        $str = str_replace($m[1], $en, $str);

                        break;
                    }
                }
            }
        }

        return $str;
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
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
