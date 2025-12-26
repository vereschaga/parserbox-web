<?php

namespace AwardWallet\Engine\iberia\Email;

class ChangesFlight extends \TAccountChecker
{
    public $mailFiles = "iberia/it-10238417.eml, iberia/it-10267840.eml, iberia/it-10448022.eml, iberia/it-4691712.eml, iberia/it-4691713.eml, iberia/it-4728670.eml, iberia/it-4800149.eml, iberia/it-4800150.eml, iberia/it-4800151.eml, iberia/it-4800152.eml, iberia/it-5877939.eml, iberia/it-6935098.eml, iberia/it-9119289.eml";
    public $reBody = [
        'en' => ['Reservation code', 'Some changes have been made'],
        'pt' => ['Código de reserva', 'correram alterações no seu voo'],
        'es' => ['Código de reserva', 'modificaciones en su vuelo'],
    ];
    public $reSubject = [
        'en' => 'Changes in your reservation',
        'pt' => 'Alterações em sua reserva',
        'es' => 'Cambios en tu reserva',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'MAINSECTION' => ['Flight change', 'Change of time', 'Change of Time', 'Modifications'],
            'passenger'   => 'Dear',
            //			'Cancelled' => '',
        ],
        'pt' => [
            'MAINSECTION'      => ['Mudança de Horário', 'Mudança de horário'],
            'Reservation code' => 'Código de reserva',
            //'Change of seat' => '',
            'passenger' => 'Estimad',
            'Origin'    => 'Origem',
            'New'       => 'Novo',
            //			'Cancelled' => '',
        ],
        'es' => [
            'MAINSECTION'      => ['Cambio de horario', 'Cambio de vuelo', 'Cancelación'],
            'Reservation code' => 'Código de reserva',
            'Change of seat'   => 'Cambio de asiento',
            'passenger'        => 'Estimad', //Estimado, Estimada
            'Origin'           => 'Anterior',
            'New'              => 'Nuevo',
            'Cancelled'        => 'Cancelado',
        ],
    ];
    private $regExpTime = [
        '2' => ['#[A-Z\d]{2}\s*\d+#'],                            //Flight
        '3' => ['#.+#'],                                        //Operated by
        '4' => ['#[A-Z]{3}#'],                                    //Origin
        '5' => ['#[A-Z]{3}#'],                                    //Destination
        '6' => ['#^\s*\d+\s*\w+\s*\d+\s*$#',                    //Date
            '#^\s*\d+\s*\w+\s*\d+\s+\d{1,2}\:\d{2}\s*$#', ],    //Departure
        '7' => ['#^\s*\d{1,2}\:\d{2}#',                        //Local time
            '#^\s*\d+\s*\w+\s*\d+\s+\d{1,2}\:\d{2}\s*$#', ],    //Arrival
    ];
    private $regExpSeat = [
        '2' => ['#[A-Z\d]{2}\s*\d+#'],                    //Flight
        '3' => ['#[A-Z]{3}#'],                            //Origin
        '4' => ['#[A-Z]{3}#'],                            //Destination
        '5' => ['#\d+\s*\w+\s*\d+#'],                    //Date
        '6' => ['#\d+\s*\w+#'],                            //Seat
    ];

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }

            if ($this->lang === "pt" || $this->lang === "en") {
                if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, "es")) {
                    return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
                }
            }
        }

        return $date;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->AssignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $lang => $reSubject) {
            if (strpos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "iberia.com") !== false;
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
        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Reservation code') . "')]/strong");

        $nodes = array_unique($this->http->FindNodes("//span[contains(text(),'" . $this->t('Change of seat') . "')]/following::table[1]//td[@colspan=6 and string-length(normalize-space(.))>1]"));

        if (isset($nodes) && count($nodes) > 0) {
            $it['Passengers'] = $nodes;
        } else {
            $it['Passengers'] = $this->http->FindNodes("//text()[starts-with(normalize-space(.), '{$this->t('passenger')}')]", null, "/{$this->t('passenger')}.*?\s*([A-Z]{2,}[A-Z\s]{2,}):?/");
        }

        $it['AccountNumbers'] = array_filter($this->http->FindNodes("//text()[contains(normalize-space(), 'Iberia Plus')][1]/ancestor::td[1]/descendant::text()[last()]", null, "/:\s*(\d{4,})\s*$/"));

        $main = $this->t("MAINSECTION");

        if (!is_array($main)) {
            $main = [$main];
        }
        $rule = implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(text()), '{$s}')";
        }, $main));

        if ($this->http->FindSingleNode("//span[{$rule}]")) {
            $rows = $this->http->XPath->query("//span[{$rule}]/following::table[1]//*[(local-name()='th' or local-name()='td') and contains(.,'" . $this->t('Origin') . "') and not(.//td)]/ancestor::table[1]//tr[td[position()=1 and contains(.,'" . $this->t('New') . "')]]");

            if ($rows->length == 0) {
                $rows = $this->http->XPath->query("//span[{$rule}]/following::table[1]//tr[ contains(.,'" . $this->t('Cancelled') . "') and not(.//tt)]");

                if ($rows->length > 0) {
                    $it['Cancelled'] = true;
                    $it['Status'] = 'Cancelled';
                }
            }

            foreach ($rows as $row) {
                $seg = [];

                foreach ($this->regExpTime as $i => $value) {
                    switch ($i) {
                        case '2':
                            $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value[0]);

                            if (($node != null) && (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m))) {
                                $seg['AirlineName'] = $m[1];
                                $seg['FlightNumber'] = $m[2];

                                if (isset($it['Passengers'])) {
                                    $seg['Seats'] = implode(",", $this->http->FindNodes("//span[contains(text(),'" . $this->t('Change of seat') . "')]/following::table[1]//tr[contains(.,'" . $this->t('New') . "') and contains(.,'" . $node . "')]/td[6]"));
                                }
                            }

                            break;

                        case '3':
                            $seg['Operator'] = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value[0]);

                            break;

                        case '4':
                            $seg['DepCode'] = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value[0]);

                            break;

                        case '5':
                            $seg['ArrCode'] = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value[0]);

                            break;

                        case '6':
                            $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value[0]);

                            if ($node !== null) {
                                $seg['DepDate'] = strtotime($this->dateStringToEnglish($node));
                                $seg['ArrDate'] = MISSING_DATE;
                            } else {
                                $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value[1]);
                                $seg['DepDate'] = strtotime($this->dateStringToEnglish($node));
                            }

                            break;

                        case '7':
                            $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value[0]);

                            if ($node !== null && isset($seg['DepDate'])) {
                                $seg['DepDate'] = strtotime($this->dateStringToEnglish($node), $seg['DepDate']);
                            } else {
                                $node = $this->http->FindSingleNode("./td[" . $i . "]", $row, true, $value[1]);
                                $seg['ArrDate'] = strtotime($this->dateStringToEnglish($node));
                            }

                            break;
                    }
                }
                $it['TripSegments'][] = $seg;
            }
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'$reBody[0]')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'$reBody[1]')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
