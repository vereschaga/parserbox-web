<?php

namespace AwardWallet\Engine\monarch\Email;

class ConfirmChanges extends \TAccountChecker
{
    public $mailFiles = "monarch/it-4445741.eml, monarch/it-4455950.eml";

    public $reBody = [
        'en' => ['Booking ', 'Reference'],
        'es' => ['reserva ', 'Referencia'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Record locator'  => 'Booking Reference',
            'Passengers'      => 'Passenger Information',
            'Terminals'       => 'AIRPORT TERMINALS:',
            'Flight Details'  => 'Flight Details',
            'Date of booking' => 'Date of booking',
            'Payment Details' => 'Payment Details',
            'Total'           => 'Total',
        ],
        'es' => [
            'Record locator'  => 'Referencia de la reserva',
            'Passengers'      => 'Información de Pasajero',
            'Terminals'       => 'TERMINALES AEROPORTUARIAS:',
            'Flight Details'  => 'Datos de vuelo',
            'Date of booking' => 'Fecha de reserva',
            'Payment Details' => 'Datos del pago',
            'Total'           => 'Cargos',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
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

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ConfirmChanges',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//a[contains(@href,".monarch.")]')->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'monarch.co.uk') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'monarch.co.uk') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function ParseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Record locator') . "')]/following-sibling::b[1]", null, true, "#[A-Z,0-9]+#");

        $node = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Date of booking') . "')]");

        if ($node == null) {
            $node = $this->http->FindSingleNode("//td[contains(text(),'" . $this->t('Date of booking') . "')]/following-sibling::td[1]");
        }

        if (preg_match("#:\s+(?<Day>\d+)\s*(?<Month>[A-z]+)\s*(?<Year>\d+)#", $node, $m)) {
            $it['ReservationDate'] = strtotime($m['Day'] . ' ' . $m['Month'] . ' ' . $m['Year'] . ' 00:00');
        }
        $it['Passengers'] = $this->http->FindNodes("(//b|//p)[contains(.,'" . $this->t('Passengers') . "')]/following-sibling::table[1]//td[not(b) and not(center)][1]");
        $dateDep = $this->http->FindNodes("(//b|//p)[contains(., '" . $this->t('Flight Details') . "')]/following-sibling::table[1]//tr[position()>1]//td[3]");
        $portDep = $this->http->FindNodes("(//b|//p)[contains(., '" . $this->t('Flight Details') . "')]/following-sibling::table[1]//tr[position()>1]//td[2]");
        $dateArr = $this->http->FindNodes("(//b|//p)[contains(., '" . $this->t('Flight Details') . "')]/following-sibling::table[1]//tr[position()>1]//td[5]");
        $portArr = $this->http->FindNodes("(//b|//p)[contains(., '" . $this->t('Flight Details') . "')]/following-sibling::table[1]//tr[position()>1]//td[4]");
        $flights = $this->http->FindNodes("(//b|//p)[contains(., '" . $this->t('Flight Details') . "')]/following-sibling::table[1]//tr[position()>1]//td[1]");

        foreach ($flights as $i => $flight) {
            $segs = [];

            if (preg_match("#(.+)\s*\(([A-Z]{3})\)#", trim($portDep[$i]), $m)) {
                $segs['DepCode'] = trim($m[2]);
                $segs['DepName'] = trim($m[1]);
            }

            if (preg_match("#(.+)\s*\(([A-Z]{3})\)#", trim($portArr[$i]), $m)) {
                $segs['ArrCode'] = trim($m[2]);
                $segs['ArrName'] = trim($m[1]);
            }

            if (preg_match("#([A-Z\d]{2})\s*([0-9]+)#", trim($flight), $m)) {
                $segs['FlightNumber'] = $m[2];
                $segs['AirlineName'] = $m[1];
            }

            if (preg_match("#(.+)\s+(?<Day>\d+)\s*(?<Month>[A-z]+)\s*(?<Year>\d+)\s+(?<Time>\d+:\d+)#", trim($dateDep[$i]), $m)) {
                $segs['DepDate'] = strtotime($m['Day'] . " " . $m['Month'] . " " . $m['Year'] . " " . $m['Time']);
            }

            if (preg_match("#(?<Day>\d+)\s*(?<Month>[A-z]+)\s*(?<Year>\d+)\s+(?<Time>\d+:\d+)#", trim($dateArr[$i]), $m)) {
                $segs['ArrDate'] = strtotime($m['Day'] . " " . $m['Month'] . " " . $m['Year'] . " " . $m['Time']);
            }

            $node = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Terminals') . "')]/following-sibling::b[text()='" . $segs['DepName'] . "']/following-sibling::text()[1]");

            if ($node != null) {
                if (preg_match("#(\w*\s+Terminal\s*\w*)#", $node, $m)) {
                    $segs['DepartureTerminal'] = $m[1];
                }
            }
            $node = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Terminals') . "')]/following-sibling::b[text()='" . $segs['ArrName'] . "']/following-sibling::text()[1]");

            if ($node != null) {
                if (preg_match("#(\w*\s+Terminal\s*\w*)#", $node, $m)) {
                    $segs['ArrivalTerminal'] = $m[1];
                }
            }

            $it['TripSegments'][] = $segs;
        }
        $node = $this->http->FindSingleNode("//p[contains(.,'" . $this->t('Payment Details') . "')]/following-sibling::table//tr[contains(.,'" . $this->t('Total') . "')]/td[2]");

        if ($node != null) {
            if (preg_match("#(.+)\s+(\d+\.\d+)#", $node, $m)) {
                $it['TotalCharge'] = trim($m[2]);
                $it['Currency'] = trim($m[1]);
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
}
