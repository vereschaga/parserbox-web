<?php

namespace AwardWallet\Engine\saudisrabianairlin\Email;

class It5835688 extends \TAccountChecker
{
    public $mailFiles = "saudisrabianairlin/it-11538026.eml, saudisrabianairlin/it-2432848.eml, saudisrabianairlin/it-2432849.eml, saudisrabianairlin/it-2432850.eml, saudisrabianairlin/it-3317392.eml, saudisrabianairlin/it-3990911.eml, saudisrabianairlin/it-5800270.eml, saudisrabianairlin/it-5800275.eml, saudisrabianairlin/it-5835684.eml, saudisrabianairlin/it-5835688.eml, saudisrabianairlin/it-6546671.eml, saudisrabianairlin/it-7178862.eml, saudisrabianairlin/it-7213339.eml, saudisrabianairlin/it-7245043.eml";

    public $reFrom = '@saudiairlines.com';

    public $reSubject = [
        'en' => ['Payment reminder', 'Booking confirmation'],
        'fr' => ['Confirmation de votre réservation'],
        'de' => ['Buchungsbestätigung'],
        'it' => ['Conferma della prenotazione'],
    ];

    public $reBody = [
        'en' => 'Booking Details',
        'fr' => 'Informations de réservation',
        'de' => 'Buchungsinformationen',
        'it' => 'Dettagli della prenotazione',
    ];

    public static $dictionary = [
        "en" => [
            "Booking date" => ["Booking date", "Booking Date:"],
            "Date"         => ["Date:", "Date :", "Date"],
            "Flight"       => ["Flight:", "Flight :", "Flight"],
            "overnight"    => "Next day arrival",
            "Baggage"      => ["Baggage", "Extras"],
            "Total amount" => ["Total amount", "Total Paid"],
        ],
        "fr" => [
            "Booking date"      => "Date de réservation:",
            "Booking reference" => "Référence de réservation",
            "Date"              => ["Date", "Date :", "Date:"],
            "Departure"         => "Départ",
            "Arrival"           => ["Arrivée :", "Arrivée:"],
            "Flight"            => ["Vol", "Vol :", "Vol:"],
            "Duration"          => "Durée",
            "Fare class"        => "Classe tarifaire",
            "overnight"         => "Arrivée le jour suivant",
            "E-ticket document" => "Billet électronique",
            "Baggage"           => "Bagages",
            "to"                => "à",
            "Seat"              => "Siège",
            "Total amount"      => "Montant total",
        ],
        "de" => [
            "Booking date"      => "Buchungsdatum:",
            "Booking reference" => "Buchungsnummer",
            "Date"              => ["Datum", "Datum :", "Datum:"],
            "Departure"         => "Abflug:",
            "Arrival"           => ["Ankunft :", "Ankunft:"],
            "Flight"            => ["Flug", "Flug :", "Flug:"],
            "Duration"          => "Dauer",
            "Fare class"        => "Tarifklasse",
            "overnight"         => "Ankunft am nächsten Tag",
            "E-ticket document" => "E-Ticket Dokument",
            "Baggage"           => "Extras",
            "to"                => "zu",
            "Seat"              => "Sitz",
            "Total amount"      => "Gesamtbetrag",
        ],
        "it" => [
            "Booking date"      => "Data della prenotazione:",
            "Booking reference" => "Codice di prenotazione",
            "Date"              => ["Data", "Data :", "Data:"],
            "Departure"         => ["Partenza:", "Partenza :"],
            "Arrival"           => ["Arrivo :", "Arrivo:"],
            "Flight"            => ["Volo", "Volo :", "Volo:"],
            "Duration"          => "Durata",
            "Fare class"        => "Classe della tariffa",
            "overnight"         => "Arrivo il giorno successivo",
            "E-ticket document" => "Documento E-ticket",
            "Baggage"           => ["Extra", "Bagagli"],
            "to"                => "a",
            "Seat"              => ["Sede", "Posto"],
            "Total amount"      => "Importo totale",
        ],
    ];

    public $lang = 'en';

    public function parseHtml(&$itineraries)
    {
        $patterns = [
            'timeAirport' => '/^(\d{1,2}:\d{2})\s+-\s+(.+)$/',
            'cityAirport' => '/^([^(]+)\(([^)]+)\)$/',
        ];

        $it = [];
        $it['Kind'] = 'T';

        $it['ReservationDate'] = strtotime($this->normalizeDate($this->re('/(\d{1,2}\s+[^\d\s]{3,}\s+\d{2,4})/', $this->nextText($this->t("Booking date")))));

        $it['RecordLocator'] = $this->re("#(\w+)#", $this->nextText($this->t("Booking reference")));

        $xpath = "//text()[" . $this->eq($this->t("Flight")) . "]/ancestor::tr[" . $this->contains($this->t("Departure")) . "][1]/..";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($segments as $root) {
            $itsegment = [];

            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::text()[" . $this->eq($this->t("Date")) . "][1]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", $root));

            $departure = $this->getField($this->t("Departure"), $root);

            if (preg_match($patterns['timeAirport'], $departure, $matches)) {
                $itsegment['DepDate'] = strtotime($date . ', ' . $matches[1]);

                if (preg_match($patterns['cityAirport'], $matches[2], $m)) {
                    $cityDep = trim($m[1]);
                    $airportDep = $m[2];
                    $itsegment['DepName'] = $cityDep . ', ' . $airportDep;
                } else {
                    $itsegment['DepName'] = $matches[2];
                }
            }

            $itsegment['DepartureTerminal'] = $this->re("#^Terminal\s+(\w+)$#", $this->getField($this->t("Departure"), $root, 2));

            $arrival = $this->getField($this->t("Arrival"), $root);

            if (preg_match($patterns['timeAirport'], $arrival, $matches)) {
                $itsegment['ArrDate'] = strtotime($date . ', ' . $matches[1]);

                if ($itsegment['ArrDate'] < $itsegment['DepDate'] && stripos($root->nodeValue, $this->t("overnight")) !== false) {
                    $itsegment['ArrDate'] = strtotime('+1 days', $itsegment['ArrDate']);
                }

                if (preg_match($patterns['cityAirport'], $matches[2], $m)) {
                    $cityArr = trim($m[1]);
                    $airportArr = $m[2];
                    $itsegment['ArrName'] = $cityArr . ', ' . $airportArr;
                } else {
                    $itsegment['ArrName'] = $matches[2];
                }
            }

            $itsegment['ArrivalTerminal'] = $this->re("#^Terminal\s+(\w+)$#", $this->getField($this->t("Arrival"), $root, 2));

            $flight = $this->getField($this->t("Flight"), $root);

            if (preg_match('/^([A-Z\d]{2})(\d+)$/', $flight, $matches)) {
                $itsegment['AirlineName'] = $matches[1];
                $itsegment['FlightNumber'] = $matches[2];
            }

            $itsegment['Duration'] = $this->getField($this->t("Duration"), $root);

            $itsegment['BookingClass'] = $this->getField($this->t("Fare class"), $root);

            if ($cityDep && $cityArr) {
                $xpathFragment1 = $this->starts($cityDep . chr(194) . chr(160) . $this->t("to") . chr(194) . chr(160) . $cityArr);
                $seatRows = $this->http->FindNodes('//tr[' . $xpathFragment1 . ']/following-sibling::tr[normalize-space(.)][position()<3]', null, '/^' . $this->preg_implode($this->t("Seat")) . '[:\s]+(\d{1,2}[A-Z])/i');
                $itsegment['Seats'] = array_values(array_filter($seatRows));
            }

            $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            $it['TripSegments'][] = $itsegment;
        }

        $passengers = [];
        $ticketNumbers = [];
        $baggageRows = $this->http->XPath->query('//text()[' . $this->eq($this->t("Baggage")) . ']/ancestor::tr[1]');

        foreach ($baggageRows as $baggageRow) {
            if ($passenger = $this->http->FindSingleNode('./preceding-sibling::tr[normalize-space(.) and not(contains(.,":"))][1]', $baggageRow)) {
                $passengers[] = $passenger;
            }

            if ($ticketNumber = $this->http->FindSingleNode('./preceding-sibling::tr[normalize-space(.)][position()<4 and ' . $this->starts($this->t("E-ticket document")) . ']/td/descendant::span[normalize-space(.)]', $baggageRow, true, '/([-\d]+)$/')) {
                $ticketNumbers[] = $ticketNumber;
            }
        }

        if (!empty($passengers[0])) {
            $it['Passengers'] = $passengers;
        }

        if (!empty($ticketNumbers[0])) {
            $it['TicketNumbers'] = $ticketNumbers;
        }

        $payment = $this->nextText($this->t("Total amount"));

        if (preg_match('/^([,.\d\s]+)([A-Z]{3})/', $payment, $matches)) {
            $it['TotalCharge'] = $this->normalizePrice($matches[1]);
            $it['Currency'] = $matches[2];
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Saudi Arabian Airlines") or contains(normalize-space(.),"Saudia Airlines")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"saudiairlines.com/")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        foreach ($this->reBody as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $this->http->setBody($parser->getHTMLBody());
        $itineraries = [];

        foreach ($this->reBody as $lang => $re) {
            if (strpos($this->http->Response['body'], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'BookingConfirmation_' . $this->lang,
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function getField($field, $root, $r = 1)
    {
        $c = count($this->http->FindNodes(".//text()[" . $this->starts($field) . "]/ancestor::td[1]/preceding-sibling::td", $root)) + 1;

        if (count($this->http->FindNodes(".//text()[" . $this->starts($field) . "]/ancestor::tr[1]/following-sibling::tr[{$r}]/td[{$c}]", $root)) > 0) {
            return $this->http->FindSingleNode(".//text()[" . $this->starts($field) . "]/ancestor::tr[1]/following-sibling::tr[{$r}]/td[{$c}]", $root);
        } else {
            return $this->http->FindSingleNode(".//text()[" . $this->starts($field) . "]/ancestor::tr[1]/following-sibling::tr[{$r}]/td[last()]", $root);
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#",
        ];
        $out = [
            "$1 $2 $3",
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizePrice($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return (float) $string;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map('preg_quote', $field));
    }
}
