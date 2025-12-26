<?php

namespace AwardWallet\Engine\lufthansa\Email;

class It2020887 extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-1618508.eml, lufthansa/it-1648235.eml, lufthansa/it-1649535.eml, lufthansa/it-1654148.eml, lufthansa/it-1658024.eml, lufthansa/it-1664004.eml, lufthansa/it-1735584.eml, lufthansa/it-1797865.eml, lufthansa/it-2020887.eml, lufthansa/it-2026936.eml, lufthansa/it-2120891.eml, lufthansa/it-2232005.eml, lufthansa/it-5947405.eml, lufthansa/it-5947492.eml, lufthansa/it-5967414.eml, lufthansa/it-7.eml, lufthansa/it-8.eml";
    private $lang = '';
    private static $detectBody = [
        'en' => ['Receipt and additional documents', 'Lufthansa'],
        'it' => ['Il suo codice di prenotazione', 'lufthansa'],
        'fr' => ['Déroulement de votre voyage', 'lufthansa'],
        'pt' => ['O seu itinerário', 'lufthansa'],
        'ru' => ['Ваш маршрут', 'lufthansa'],
        'de' => ['Ihr Reiseverlauf', 'lufthansa'],
        'es' => ['Datos del viaje', 'lufthansa'],
    ];
    private static $dict = [
        'en' => [
            'Ticket details & travel information' => ['Ticket details & travel information', 'Ticket details & rebooking information'],
        ],
        'it' => [
            'Your booking code'                   => 'Il suo codice di prenotazione',
            "Your booking codes:"                 => "NOTTRANSLATED",
            "operated by:"                        => "operato da",
            'Ticket details & travel information' => 'Informazioni sulla prenotazione',
            'Status'                              => 'Situazione volo',
            'Total Price for all passengers'      => 'prezzo totale per tutti I passeggeri',
//            'Ticket no.:'      => '',
        ],
        'fr' => [
            'Your booking code'                   => "Votre code de réservation:",
            "Your booking codes:"                 => "Vos codes de réservation:",
            "operated by:"                        => "opéré par :",
            'Ticket details & travel information' => 'Détails du billet & informations sur le voyage',
            'Status'                              => 'Statut',
            'Total Price for all passengers'      => 'Prix total pour tous les passagers',
            'Seats'      => 'Sièges',
            'Ticket no.:'      => 'N° de billet:',
        ],
        'pt' => [
            'Your booking code'                   => 'O seu código de reserva:',
            "Your booking codes:"                 => "NOTTRANSLATED",
            "operated by:"                        => "operado por:",
            'Ticket details & travel information' => 'Informação acerca da sua viagem',
            'Status'                              => 'Estatuto',
            'Total Price for all passengers'      => 'Preço total para todos os passageiros',
//            'Ticket no.:'      => '',
        ],
        'ru' => [
            'Your booking code'                   => 'Код Вашего бронирования:',
            "Your booking codes:"                 => "NOTTRANSLATED",
            "operated by:"                        => "выполняется",
            'Ticket details & travel information' => 'Дополнительная информация по билету и маршруту',
            'Status'                              => 'Статус',
            'Class'                               => 'Класс',
            'Total Price for all passengers'      => 'общая сумма за всех пассажиров',
//            'Ticket no.:'      => '',
        ],
        'de' => [
            'No code'                             => 'Ihrem Wunsch entsprechend wird Ihr Buchungscode nicht angezeigt',
            'Your booking code'                   => 'Ihr Buchungscode:',
            "Your booking codes:"                 => "NOTTRANSLATED",
            "operated by:"                        => "durchgeführt von",
            'Ticket details & travel information' => ['Passagierinformationen', 'Buchungsänderung', 'Flugscheindetails & Reiseinformationen'],
            'Status'                              => 'Status',
            'Class'                               => ['Klasse/Tarif:', 'Buchungsklasse'],
            'Total Price for all passengers'      => 'Gesamtpreis für alle Reisenden',
            'Seats'      => 'Sitzplatz',
            'Ticket no.:'      => 'Ticket Nr.:',
        ],
        'es' => [
//            'No code'                             => '',
            'Your booking code'                   => 'Su código de reserva:',
//            "Your booking codes:"                 => "NOTTRANSLATED",
            "operated by:"                        => "operado por:",
            'Ticket details & travel information' => ['Datos del viaje'],
            'Status'                              => 'Estado',
            'Class'                               => 'Clase de reserva',
            'Total Price for all passengers'      => 'Precio total para todos los pasajeros',
            'Seats'      => 'Asientos',
//            'Ticket no.:'      => '',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBodyAndAcceptLang();
        $its = $this->parseEmail();

        return [
            'emailType'  => 'Flight'.ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBodyAndAcceptLang();
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'lufthansa.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'lufthansa.com') !== false;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $its = [];

        $rls = [];

        foreach ($this->http->FindNodes("//text()[" . $this->eq($this->t("Your booking codes:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]") as $node) {
            if (preg_match("#^(.*?):\s+(\w+)$#", $node, $m)) {
                $rls[trim(strtolower($m[1]))] = $m[2];
            }
        }

        $xpath = "//text()[starts-with(normalize-space(.), '" . $this->t("operated by:") . "') or starts-with(normalize-space(.), '" . $this->t("operatedby2") . "')]/ancestor::table[3]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $airline = strtolower(trim($this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), '" . $this->t("operated by:") . "') or starts-with(normalize-space(.), '" . $this->t("operatedby2") . "')]", $root, true, "#(?:" . $this->t("operated by:") . "|" . $this->t("operatedby2") . "):?\s+(.+)#u")));

            if (isset($rls[$airline])) {
                $airs[$rls[$airline]][] = $root;
            }
        }

        if (empty($airs) && $rl = $this->http->FindSingleNode("//text()[contains(normalize-space(.), '" . $this->t('Your booking code') . "')]/following-sibling::*[1]")) {
            $airs[$rl] = $nodes;
        }

        if (count($airs) < count($rls) && isset($rls['lufthansa'])) {
            $airs[$rls['lufthansa']] = $nodes;
        }

        if (count($rls) === 0 && $this->http->XPath->query("//td[contains(normalize-space(.),'" . $this->t('No code') . "')]")->length > 0) {
            $airs[CONFNO_UNKNOWN] = $nodes;
        }

        foreach ($airs as $rl => $roots) {
            $it = [];
            $it['Kind'] = 'T';

            $it['RecordLocator'] = $rl;
            $it['Passengers'] = $this->orval(
                $this->http->FindNodes("//td[" . $this->contains($this->t('Ticket details & travel information'), 'p') . "]/ancestor::table[1]/following-sibling::table[descendant::b and contains(., '/')]"),
                $this->http->FindNodes("//td[" . $this->contains($this->t('Ticket details & travel information'), 'text()') . "]/ancestor::table[1]/following-sibling::table[descendant::b and contains(., '/')]"),
                $this->http->FindNodes("//td[" . $this->contains($this->t('Ticket details & travel information'), 'text()') . "]/ancestor::table[1]/following::table[descendant::b and contains(., '/')][1]/descendant::td[count(descendant::td)=0 and contains(., '/')]")
            );

            $it['TicketNumbers'] = array_filter(
                $this->http->FindNodes("//td[not(.//td) and " . $this->starts($this->t('Ticket no.:')) . "]", null, "/:\s*([\d\-]{13,})\b/")
            );

            $total = $this->http->FindSingleNode("//*[contains(normalize-space(text()), '" . $this->t('Total Price for all passengers') . "')]/ancestor-or-self::td[1]/following-sibling::td[1]");
            //$total = $this->http->FindSingleNode("//tr[count(td)=2]/descendant::*[(name(descendant::b) or name(descendant::p)) and contains(., '". $this->t('Total Price for all passengers') ."')]/following-sibling::td[1]");
            $total = str_replace("€", "EUR", $total);

            if (preg_match('/([\d\.]*)\s+(\w+)/', $total, $m)) {
                $it['TotalCharge'] = $m[1];
                $it['Currency'] = $m[2];
            }

            foreach ($roots as $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                $flight = $this->http->FindSingleNode('descendant::table[2]/descendant::tr[1]', $root);

                if (preg_match('/(\w{2})\s+(\d+)/', $flight, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                //Seats 92K/92H
                $seats = $this->http->FindSingleNode('descendant::table[2]/descendant::tr[1]/following-sibling::tr['.$this->contains($this->t("Seats")).']', $root);
                if (preg_match('/'.$this->opt($this->t("Seats")).' ?([\d\/a-z]*)/i', $seats, $m)) {
                    $seg['Seats'] = (stripos($m[1], '/') !== false) ? str_replace('/', ', ', $m[1]) : $m[1];
                }

                $status = $this->http->FindSingleNode('descendant::table[2]/following-sibling::table[1]/descendant::tr[contains(., "' . $this->t('Status') . '")]', $root);

                if (preg_match('/(?:status|Situazione volo|Statut|Статус)\s*((?:confirmed|canceled|confermato|confirmé|подтвержден|bestätigt))/i', $status, $m)) {
                    $it['Status'] = $m[1];
                }

                $cabin = $this->orval(
                    $this->http->FindSingleNode('descendant::table[2]/following-sibling::table[1]/descendant::tr[contains(., "Class")]', $root),
                    $this->http->FindSingleNode("descendant::table[2]/descendant::tr[1]/following-sibling::tr[contains(., 'Classe')]", $root),
                    $this->http->FindSingleNode(".//text()[normalize-space(.)='Classe de réservation']/ancestor::tr[./descendant::text()[normalize-space(.)][2]][1]", $root),
                    $this->http->FindSingleNode(".//text()[" . $this->eq($this->t('Class')) . "]/ancestor::tr[./descendant::text()[normalize-space(.)][2]][1]", $root)
                );

                if (preg_match('/(?:class|Classe di prenotazione|Classe de réservation|' . $this->opt($this->t('Class')) . ')\s+(.+)\s+\((\w{1})\)/i', $cabin, $m)) {
                    $seg['Cabin'] = $m[1];
                    $seg['BookingClass'] = $m[2];
                }

                $date = $this->orval(
                    $this->http->FindSingleNode('preceding-sibling::table[5]/descendant::tr[2]', $root),
                    $this->http->FindSingleNode('descendant::table[1]', $root)//bcd
                );
                //Wed. 24 December 2014: SAN FRANCISCO CA - FRANKFURT DE, lun. 13 Ottobre 2014: BOGOTA CO - FRANKFURT DE
                if (preg_match('/(\d{2})\.?\s+([^\d\s]+)\s+(\d{4})/', $date, $m)) {
                    $dateTime = $m[1] . ' ' . \AwardWallet\Engine\MonthTranslate::translate($m[2], $this->lang) . ' ' . $m[3];
                    $dateTime = \DateTime::createFromFormat('d M Y', $dateTime);
                }

                $depInfo = $this->orval(
                    $this->getInfo($this->clearEmptySymbol($this->http->FindSingleNode('preceding-sibling::table[4]', $root))),
                    $this->getInfo($this->clearEmptySymbol($this->http->FindSingleNode('descendant::table[1]/following-sibling::table[1]/descendant::table[count(descendant::table)=0]/descendant::tr[normalize-space(.)][1]', $root)))//bcd
                );

                if (is_array($depInfo) && count($depInfo) >= 3 && (isset($dateTime) && is_object($dateTime))) {
                    $seg['DepName'] = $depInfo['Name'];
                    if (!empty($depInfo['Code'])) {
                        $seg['DepCode'] = $depInfo['Code'];
                    } else {
                        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    }

                    $seg['DepartureTerminal'] = $depInfo['Term'];
                    $depDate = clone $dateTime;

                    if (empty($depInfo['AddOneDay'])) {
                        $depDate->modify($depInfo['Time']);
                    } else {
                        $depDate->modify($depInfo['Time']);
                        $depDate->modify('+' . $depInfo['AddOneDay'] . ' day');
                    }
                    $seg['DepDate'] = $depDate->getTimestamp();
                }

                $arrInfo = $this->orval(
                    $this->getInfo($this->clearEmptySymbol($this->http->FindSingleNode('preceding-sibling::table[2]', $root))),
                    $this->getInfo($this->clearEmptySymbol($this->http->FindSingleNode('descendant::table[1]/following-sibling::table[1]/descendant::table[count(descendant::table)=0]/descendant::tr[normalize-space(.)][2]', $root)))
                );

                if (is_array($arrInfo) && count($arrInfo) >= 3 && (isset($dateTime) && is_object($dateTime))) {
                    $seg['ArrName'] = $arrInfo['Name'];
                    if (!empty($arrInfo['Code'])) {
                        $seg['ArrCode'] = $arrInfo['Code'];
                    } else {
                        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    }

                    $seg['ArrivalTerminal'] = $arrInfo['Term'];
                    $arrDate = clone $dateTime;

                    if (empty($arrInfo['AddOneDay'])) {
                        $arrDate->modify($arrInfo['Time']);
                    } else {
                        $arrDate->modify($arrInfo['Time']);
                        $arrDate->modify('+' . $arrInfo['AddOneDay'] . ' day');
                    }
                    $seg['ArrDate'] = $arrDate->getTimestamp();
                }

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
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
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function clearEmptySymbol($text)
    {
        return preg_replace('/\xe2\x80\x8b/', '', $text);
    }

    private function getInfo($text)
    {
        $res = null;
        $re = '/(?<Time>\d+:\d+) [^\d\s]+(?:(?<AddOneDay>\+\d))?\s+(?<Name>.+)\s+\((?<Code>[A-Z]{3})\)\s?(?:TERMINAL(?:E|):\s*(?<Term>\w))?/';
        $re2 = '/(?<Time>\d+:\d+) [^\d\s]+(?:(?<AddOneDay>\+\d))?\s+(?<Name>.+)\s+?(?:TERMINAL(?:E|):\s*(?<Term>\w))?/';

        if (preg_match($re, $text, $m) || preg_match($re2, $text, $m)) {
            $res = [
                'Time'      => $m['Time'],
                'AddOneDay' => (!empty($m['AddOneDay'])) ? $m['AddOneDay'] : null,
                'Name'      => $m['Name'],
                'Code'      => $m['Code'] ?? null,
                'Term'      => (!empty($m['Term'])) ? $m['Term'] : null,
            ];
        }

        return $res;
    }

    private function detectBodyAndAcceptLang()
    {
        $body = $this->http->Response['body'];

        foreach (self::$detectBody as $lang => $detect) {
            if (is_array($detect) && count($detect) === 2) {
                if (stripos($body, $detect[0]) !== false && stripos($body, $detect[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }

            if (is_string($detect) && stripos($body, $detect) !== false) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function eq($field
    ) {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return "false()";
        }

        return "(" . implode(" or ", array_map(function ($s) use ($text) { return "contains(".$text.",\"" . $s . "\")"; }, $field)) . ")";
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return preg_quote($s, '/');
            }, $field)) . ')';
    }
}
