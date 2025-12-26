<?php

namespace AwardWallet\Engine\tapportugal\Email;

class FlightBookingConf extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "tapportugal/it-4849789.eml, tapportugal/it-5449361.eml";

    public $reBody = [
        'it' => ['Comunica sempre questo codice di prenotazione quando devi contattare TAM', 'Grazie! La tua richiesta'],
        'pt' => ['Outras solicitações em trechos operados pela TAM', 'Cadastre-se já no Programa TAM Fidelidade.'],
        'fr' => 'Merci! Votre demande a été réalisée avec succès',
        'es' => 'de alta en el Programa TAM Fidelidade',
    ];
    public $reSubject = [
        'it' => ['Conferma prenotazione volo TAM'],
        'pt' => ['Confirmação de seu pedido de voo com a TAM'],
    ];
    public $lang = 'it';
    public $pdf;
    public static $dict = [
        'it' => [
            'RecordLocator' => 'Riferimento prenotazione',
            'FindPass'      => 'Informazioni Secure Flight',
            'Payment'       => 'Dettagli prezzo',
            'BaseFare'      => 'Totale passeggeri',
            'Tax'           => 'Totale tasse',
            'Total'         => 'Totale:',
            'Flights'       => 'Riepilogo volo',
            'FlightNum'     => 'Numero di volo',
            'Operator'      => 'Operato da',
            'Depart'        => 'Partenza',
            'Arrive'        => 'Arrivo',
            'Cabin'         => 'Cabina',
            'Aircraft'      => 'Aeromobile',
            'AtTime'        => 'Ora',
            'Flight'        => 'ANDATA',
        ],
        'pt' => [
            'FindPass'  => 'Prog. de fidelização',
            'Payment'   => 'Dados do Pagamento',
            'Total'     => 'Total:',
            'Flights'   => 'Resumo da Viagem',
            'FlightNum' => 'Número do Voo',
            'Operator'  => '	Operado por',
            'Depart'    => 'Saída',
            'Arrive'    => 'Chegada',
            'Cabin'     => 'Classe de Serviço',
            'Aircraft'  => 'Aeronave',
            'AtTime'    => 'Horário',
            'Flight'    => 'IDA',
        ],
        'fr' => [
            'RecordLocator' => 'Code de réservation',
            'FindPass'      => 'Passager',
            'Payment'       => 'Détails sur le tarif',
            'Tax'           => 'Total en taxes',
            'Total'         => 'Total :',
            'Flights'       => 'Récapitulatif du voyage',
            'FlightNum'     => 'Numéro du vol',
            'Operator'      => 'Exploité par',
            'Depart'        => 'Aller',
            'Arrive'        => 'Arrivée',
            'Cabin'         => 'Classe',
            'Aircraft'      => 'Aéronef',
            'AtTime'        => 'Horaire',
            'Flight'        => 'Départ',
        ],
        'es' => [
            'RecordLocator' => 'Código de orden',
            'FindPass'      => 'Información de vuelo seguro',
            'Payment'       => 'Detalles de la Tarifa',
            'BaseFare'      => 'Total General',
            'Tax'           => 'Total de Tasas y Servicios',
            'Total'         => 'Total pagado',
            'Flights'       => 'Resumen del Viaje',
            'FlightNum'     => 'Número de Vuelo',
            'Operator'      => 'Operdao por',
            'Depart'        => 'Embarque',
            'Arrive'        => 'Llegada',
            'Cabin'         => 'Clase de Servicio',
            'Aircraft'      => 'Aeronave',
            'AtTime'        => 'Hora',
            'Flight'        => ['Ida', 'Llegada'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "FlightBookingConf",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return $this->AssignLang($body);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject) && isset($headers['subject'])) {
            foreach ($this->reSubject as $lang => $reSubject) {
                if (stripos($headers['subject'], $reSubject[0]) !== false) {// || stripos($headers['subject'], $reSubject[1]) !== false
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "jetblue.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function getMonth($nodeForMonth)
    {
        $month = $this->dateTimeToolsMonthNames['en'];
        $monthLang = $this->dateTimeToolsMonthNames[$this->lang];

        $res = $nodeForMonth;

        for ($i = 0; $i < 12; $i++) {
            if (strtolower(substr($monthLang[$i], 0, 3)) == strtolower(substr(trim($nodeForMonth), 0, 3))) {
                $res = $month[$i];
            }
        }

        return $res;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->orval(
            $this->http->FindSingleNode("(//*[contains(text(),'" . $this->t('RecordLocator') . "')])[1]", null, true, "#" . $this->t('RecordLocator') . "[:]?\s+(.+)#"),
        $this->http->FindSingleNode("//span[contains(text(), 'Guarde este código!')]//*[1]"),
            CONFNO_UNKNOWN
        );

        $it['Passengers'] = array_unique($this->http->FindNodes("//span[contains(text(),'" . $this->t('FindPass') . "')]/preceding::span[1]"));

        $it['TicketNumbers'] = array_unique($this->http->FindNodes("//th[contains(.,'" . $this->t('e-Ticket') . "')]/following::span[contains(text(),'" . $this->t('e-Ticket') . "')]", null, "#[\(]*" . $this->t('e-Ticket') . "[\)]*\s+(.+)#"));

        $it['BaseFare'] = cost($this->http->FindSingleNode("//th[contains(.,'" . $this->t('Payment') . "')]/following::td[contains(text(),'" . $this->t('BaseFare') . "')]/following-sibling::td[3]"));

        $it['Tax'] = cost($this->http->FindSingleNode("//th[contains(.,'" . $this->t('Payment') . "')]/following::td[contains(text(),'" . $this->t('Tax') . "')]/following-sibling::td[3]"));

        $it['TotalCharge'] = cost($this->http->FindSingleNode("//th[contains(.,'" . $this->t('Payment') . "')]/following::td[span[contains(.,'" . $this->t('Total') . "')]]/following-sibling::td[1]"));

        $it['Currency'] = currency($this->http->FindSingleNode("//th[contains(.,'" . $this->t('Payment') . "')]/following::td[span[contains(.,'" . $this->t('Total') . "')]]/following-sibling::td[1]"));

        if (empty($it['TotalCharge']) && empty($it['Currency'])) {
            $total = $this->http->FindSingleNode("//th[contains(.,'" . $this->t('Payment') . "')]/following::td[1]");

            if (preg_match('/.*(\D{1})\s+(\d+\,\d+)/', $total, $m)) {
                $it['TotalCharge'] = (stripos($m[2], ',') !== false) ? str_replace(',', '.', $m[2]) : $m[2];
                $it['Currency'] = ('$' === $m[1]) ? 'USD' : null;
            }
        }

        $xpath = "//th[contains(.,'" . $this->t('Flights') . "')]/following::*[contains(text(), '" . $this->t('FlightNum') . "')]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);
        $this->logger->info('Segments found by: ' . $xpath);

        if ($roots->length === 0) {
            $this->logger->info("Segments not found: {$xpath}");
        }

        foreach ($roots as $root) {
            $seg = [];

            $seg['DepName'] = $this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.),'" . $this->t('Depart') . "')]/descendant::td[normalize-space(.)][2]", $root);
            $shortDepName = $this->getShortName($seg['DepName']);

            $seg['ArrName'] = $this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.),'" . $this->t('Arrive') . "')]/descendant::td[normalize-space(.)][2]", $root);
            $shortArrName = $this->getShortName($seg['ArrName']);

            $flightInfo = $this->t('Flight');
            $resFlight = 'contains(., "' . $this->t('Flight') . '")';

            if (is_array($flightInfo)) {
                $resFlight = implode(' or ', array_map(function ($e) { return 'contains(., "' . $e . '")'; }, $flightInfo));
            }
            $xpathForNode = '(ancestor::tr[1]/preceding-sibling::tr[' . $resFlight . ']/descendant::span[normalize-space(.)][2])[1]';
            $this->logger->info('[INFO]: ' . $xpathForNode);
            $node = $this->http->FindSingleNode($xpathForNode, $root);

            $dateFly = null; //date("d m Y");

            $re = '/';
            $re .= '(?<infoDep>.+)\((?<DepCode>[A-Z]{3})\)(?<infoArr>.+)\((?<ArrCode>[A-Z]{3})\)\s*-\s*(?<dayweek>\w+)[\.]?\s*(?<day>\d+)\s+';
            $re .= '(?:(?<month>\w+\s+))?(?:(?<timeDep>\d+\:\d+(?:\s*[AP]M)?)\s+)?(?<year>\d{4})';
            $re .= '/u';

            if (isset($node) && preg_match($re, $node, $m)) {
                if (stripos($m['infoDep'], $shortDepName) !== false) {
                    $seg['DepCode'] = $m['DepCode'];
                } else {
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                if (stripos($m['infoArr'], $shortArrName) !== false) {
                    $seg['ArrCode'] = $m['ArrCode'];
                } else {
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }

                if (isset($m['month']) && !empty($m['month'])) {
                    $dateFly = $m['day'] . ' ' . trim($this->getMonth($m['month'])) . ' ' . $m['year'];
                }
            }

            $flight = $this->http->FindSingleNode("descendant::td[normalize-space(.)][2]", $root);

            if (preg_match("#(\w{2})\s*(\d+)#", $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $seg['Operator'] = $this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.),'" . $this->t('Operator') . "')]/descendant::td[normalize-space(.)][2]", $root);

            if (isset($dateFly)) {
                $times = $this->http->FindNodes("following-sibling::tr[contains(normalize-space(.),'" . $this->t('AtTime') . "')]//td[normalize-space(.)][2]", $root);

                if (isset($times) && count($times) == 2) {
                    $seg['DepDate'] = strtotime($dateFly . ', ' . $times[0]);
                    $seg['ArrDate'] = strtotime($dateFly . ', ' . $times[1]);
                }
            } else {  //this is not a plug for the hole, it really is necessary to
                $seg['DepDate'] = MISSING_DATE;
                $seg['ArrDate'] = MISSING_DATE;
            }

            $seg['Aircraft'] = $this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.),'" . $this->t('Aircraft') . "')]//td[normalize-space(.)][2]", $root);

            $seg['Cabin'] = $this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.),'" . $this->t('Cabin') . "')]//td[normalize-space(.)][2]", $root);

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getShortName($fullName)
    {
        if (!empty($fullName) && preg_match('/([a-z\s]+)\s*,\s*/i', $fullName, $m)) {
            return $m[1];
        }

        return null;
    }

    private function orval(...$arr)
    {
        foreach ($arr as $item) {
            if (!empty($item)) {
                return $item;
            }
        }

        return null;
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
                if (is_array($reBody) && (stripos($body, $reBody[0]) !== false || stripos($body, $reBody[1]) !== false)) {
                    $this->lang = $lang;

                    return true;
                } elseif (is_string($reBody) && stripos($body, $reBody) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
