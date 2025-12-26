<?php

namespace AwardWallet\Engine\tamair\Email;

class ConfirmationGrey extends \TAccountCheckerExtended
{
    public $mailFiles = "tamair/it-10847840.eml, tamair/it-11086000.eml, tamair/it-11102290.eml";

    public $reFrom = "site@tamviagens.com.br";
    public $reBody = [
        'pt' => 'INFORMAÇÕES DO(S) PASSAGEIRO(S)',
    ];
    public $reSubject = [
        'pt' => '| Confirmação de Reserva:',
    ];
    public $lang = '';
    public static $dict = [
        'pt' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = [];
        $flights = $this->parseEmailFlight();

        foreach ($flights as $flight) {
            $its[] = $flight;
        }
        $events = $this->parseEmailEvent();

        foreach ($events as $event) {
            $its[] = $event;
        }
        $hotels = $this->parseEmailHotel();

        foreach ($hotels as $hotel) {
            $its[] = $hotel;
        }

        $result = [
            'emailType'  => 'ConfirmationGrey' . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $its],
        ];

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Pagamentos recebidos']/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (!empty($total)) {
            if (preg_match("#^\s*(\d[\d., ]*\s*Pontos)\b(.*)#", $total, $m)) {
                $SpentAwards = $m[1];
                $total = trim($m[2], '+ ');
            }

            if (!empty($total)) {
                $TotalCharge = $this->normalizePrice($total);
                $Currency = $this->currency($total);
            }

            if (count($its) == 1 && ($its[0]['Kind'] == 'T') || $its[0]['Kind'] == 'E') {
                $its[0]['TotalCharge'] = $TotalCharge ?? null;
                $its[0]['Currency'] = $Currency ?? null;
                $its[0]['SpentAwards'] = $SpentAwards ?? null;
                $result['parsedData']['Itineraries'] = $its;
            }

            if (count($its) == 1 && $its[0]['Kind'] == 'R') {
                $its[0]['Total'] = $TotalCharge ?? null;
                $its[0]['Currency'] = $Currency ?? null;
                $its[0]['SpentAwards'] = $SpentAwards ?? null;
                $result['parsedData']['Itineraries'] = $its;
            }

            if (count($its) > 1) {
                $result['TotalCharge']['Amount'] = $TotalCharge ?? null;
                $result['TotalCharge']['Currency'] = $Currency ?? null;
                $result['TotalCharge']['SpentAwards'] = $SpentAwards ?? null;
            }
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(),'TAM Viagens')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmailFlight()
    {
        $its = [];
        $xpath = "//text()[normalize-space() = '{$this->t('RESUMO DA VIAGEM')}']/ancestor::table[1]//text()[normalize-space() = '{$this->t('Voo')}']/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $this->http->FindSingleNode(".//text()[contains(normalize-space(), 'Confirmação da Agência')]/following::text()[normalize-space()][1]", $root, true, "#\s*([A-Z\d]+)\b#");
            $it['TripNumber'] = $this->http->FindSingleNode("(//text()[contains(normalize-space(), 'Número da reserva')]/following::text()[normalize-space()][1])[1]", null, true, "#([A-Z\d]{5,})#");
            $rootP = $this->http->XPath->query("//text()[contains(normalize-space(), 'Duração')]/ancestor::tr[1]")[0];

            for ($i = 1; $i < 10; $i++) {
                if ($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][{$i}][contains(.,'Partida')]", $rootP)
                        || empty($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][{$i}]", $rootP))) {
                    break;
                } else {
                    $it['Passengers'][] = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][{$i}]", $rootP);
                }
            }

            $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Data da Reserva')]", null, true, "#{$this->t('Data da Reserva')}\s+(.+)#")));

            $xpath2 = ".//text()[contains(normalize-space(), 'Partida')]/ancestor::tr[1]";
            $nodesSeg = $this->http->XPath->query($xpath2, $root);

            foreach ($nodesSeg as $rootSeg) {
                $seg = [];
                // FlightNumber
                // AirlineName
                // Cabin
                // BookingClass
                // Aircraft
                $node = $this->http->FindSingleNode("./td[2]", $rootSeg);

                if (preg_match("#.+?\s+([A-Z\d]{2})(\d{1,5})\s+(.+?)\(([A-Z]{1,2})\b.*\)\s*\|\s*Aeronave:\s*(.+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                    $seg['Cabin'] = trim($m[3]);
                    $seg['BookingClass'] = $m[4];
                    $seg['Aircraft'] = trim($m[5]);
                }
                // DepCode
                $seg['DepCode'] = $this->http->FindSingleNode("./td[3]", $rootSeg, null, "#.+\s*-\s*\(([A-Z]{3})\)#");

                // DepName
                $seg['DepName'] = trim($this->http->FindSingleNode("./td[3]", $rootSeg, null, "#(.+)\s*-\s*\([A-Z]{3}\)#"));

                // DepartureTerminal
                // DepDate
                $seg['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[5]", $rootSeg)));

                // ArrCode
                $seg['ArrCode'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[contains(., 'Chegada')]/preceding-sibling::td[normalize-space()][1]", $rootSeg, null, "#.+\s*-\s*\(([A-Z]{3})\)#");

                // ArrName
                $seg['ArrName'] = trim($this->http->FindSingleNode("./following-sibling::tr[1]/td[contains(., 'Chegada')]/preceding-sibling::td[normalize-space()][1]", $rootSeg, null, "#(.+)\s*-\s*\([A-Z]{3}\)#"));

                // ArrivalTerminal
                // ArrDate
                $seg['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[contains(., 'Chegada')]/following-sibling::td[normalize-space()][1]", $rootSeg)));

                // TraveledMiles
                // PendingUpgradeTo
                // Seats
                // Duration
                $seg['Duration'] = $this->http->FindSingleNode("./following-sibling::tr[2]/td[contains(., 'Duração')]/following-sibling::td[normalize-space()][1]", $rootSeg);

                // Meal
                // Smoking
                // Stops
                // Operator
                // Gate
                // ArrivalGate
                // BaggageClaim

                $it['TripSegments'][] = $seg;
            }

            $finded = false;

            foreach ($its as $key => $iter) {
                if (isset($it['RecordLocator']) && $iter['RecordLocator'] == $it['RecordLocator']) {
                    $its[$key]['TripSegments'] = array_merge($iter['TripSegments'], $it['TripSegments']);

                    if (isset($it['Passengers'])) {
                        $its[$key]['Passengers'] = array_unique(isset($its[$key]['Passengers']) ? array_merge($its[$key]['Passengers'], $it['Passengers']) : $it['Passengers']);
                    }
                    $finded = true;
                }
            }

            if ($finded === false) {
                $its[] = $it;
            }
        }

        return $its;
    }

    private function parseEmailHotel()
    {
        $its = [];
        $xpath = "//text()[normalize-space() = 'RESUMO DA VIAGEM']/ancestor::table[1]//text()[normalize-space() = 'HOSPEDAGEM']/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'R'];
            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->http->FindSingleNode(".//text()[contains(normalize-space(),'confirmação:')]/following::text()[normalize-space()][1]", $root, true, "#^\s*([A-Z\d ]+)\s*$#");

            if (empty($it['ConfirmationNumber']) && empty($this->http->FindSingleNode("(.//text()[contains(normalize-space(),'confirmação:')][1])[1]", $root))) {
                $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
            }
            // TripNumber
            $it['TripNumber'] = $this->http->FindSingleNode("(//text()[contains(normalize-space(), 'Número da reserva')]/following::text()[normalize-space()][1])[1]", null, true, "#([A-Z\d]{5,})#");
            // ConfirmationNumbers
            // HotelName
            $it['HotelName'] = $this->http->FindSingleNode(".//text()[contains(normalize-space(),'Tipo do apartamento')]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()][last()]", $root);
            // 2ChainName
            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[contains(normalize-space(),'Check-in')]/following::text()[normalize-space()][1]", $root)));
            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[contains(normalize-space(),'Check-out')]/following::text()[normalize-space()][1]", $root)));
            // Address
            // Phone
            $address = implode("\n", $this->http->FindNodes(".//text()[contains(normalize-space(),'Tipo do apartamento')]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()][last()-1]//text()", $root));

            if (preg_match("#([\s\S]+)\n([\d \-+\(\)]+)\s*$#", $address, $m)) {
                $it['Address'] = str_replace("\n", ' ', trim($m[1]));
                $it['Phone'] = trim($m[2]);
            } else {
                $it['Address'] = str_replace("\n", ' ', $address);
            }
            // DetailedAddress
            // Fax
            // Guests
            $it['Guests'] = $this->http->FindSingleNode(".//text()[contains(normalize-space(),'Hóspedes')]/following::text()[normalize-space()][1]", $root, true, "#(\d+)#");
            // GuestNames
            $nodesG = $this->http->XPath->query(".//*[contains(@class,'traveler_first_name')]", $root);

            foreach ($nodesG as $rootG) {
                $it['GuestNames'][] = $rootG->nodeValue . ' ' . $this->http->FindSingleNode("./following::*[position()<5][contains(@class, 'traveler_last_name')]", $rootG);
            }
            // Kids
            // Rooms
            // Rate
            // RateType
            // CancellationPolicy
            // RoomType
            $it['RoomType'] = $this->http->FindSingleNode(".//text()[contains(normalize-space(),'Tipo do apartamento')]/following::text()[normalize-space()][1]", $root);

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            // Currency
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // Cancelled
            // ReservationDate
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Data da Reserva')]", null, true, "#{$this->t('Data da Reserva')}\s+(.+)#")));

            // NoItineraries

            $its[] = $it;
        }

        return $its;
    }

    private function parseEmailEvent()
    {
        $its = [];
        $xpath = "//text()[normalize-space() = 'RESUMO DA VIAGEM']/ancestor::table[1]//text()[normalize-space() = 'ATIVIDADE']/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'E'];
            // ConfNo
            $it['ConfNo'] = CONFNO_UNKNOWN;
            // TripNumber
            $it['TripNumber'] = $this->http->FindSingleNode("(//text()[contains(normalize-space(), 'Número da reserva')]/following::text()[normalize-space()][1])[1]", null, true, "#([A-Z\d]{5,})#");
            // Name
            $it['Name'] = $this->http->FindSingleNode(".//text()[contains(.,'Imprima os detalhes')]/preceding::text()[normalize-space()][1]", $root);
            // StartDate
            $it['StartDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[contains(.,'data:')]/ancestor::*[1]", $root, null, "#data:\s*([\d.,/]+)\s*-\s*#")));
            // EndDate
            $it['EndDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[contains(.,'data:')]/ancestor::*[1]", $root, null, "#data:\s*[\d.,/ ]+\s*-\s*([\d.,/ ]+)#")));
            // Address
            $it['Address'] = $it['Name'];
            // Phone
            // DinerName
            $it['DinerName'] = $this->http->FindNodes(".//text()[starts-with(normalize-space(), 'Mr ') or starts-with(normalize-space(), 'Ms ') or starts-with(normalize-space(), 'Mrs ')]", $root);
            // Guests
            $it['Guests'] = count($it['DinerName']);

            // TotalCharge
            // Currency
            // Tax
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // Cancelled
            // ReservationDate
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Data da Reserva')]", null, true, "#{$this->t('Data da Reserva')}\s+(.+)#")));

            // NoItineraries
            // EventType
            $it['EventType'] = EVENT_EVENT;

            $its[] = $it;
        }

        return $its;
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
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody}')]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $in = [
            "#^.+\s(\d{1,2})/(\d{2})/(\d{2})\s+(\d+:\d+)$#", //Quinta-feira 09/06/16 06:54
            "#^(?:.+\s|\s*)(\d{1,2})/(\d{2})/(\d{2})\s*$#", //Quinta-feira 09/06/16
        ];
        $out = [
            "$1.$2.20$3 $4",
            "$1.$2.20$3",
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return $str;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function normalizePrice($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            'R$'=> 'BRL',
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
