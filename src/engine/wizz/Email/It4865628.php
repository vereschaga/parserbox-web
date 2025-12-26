<?php

namespace AwardWallet\Engine\wizz\Email;

class It4865628 extends \TAccountChecker
{
    public $mailFiles = "wizz/it-1.eml, wizz/it-12232054.eml, wizz/it-1636252.eml, wizz/it-1687840.eml, wizz/it-2.eml, wizz/it-3.eml, wizz/it-3986190.eml, wizz/it-4531829.eml, wizz/it-4531832.eml, wizz/it-4533408.eml, wizz/it-4587889.eml, wizz/it-4619655.eml, wizz/it-4627970.eml, wizz/it-4693584.eml, wizz/it-4865628.eml, wizz/it-4888613.eml, wizz/it-5253958.eml, wizz/it-5316372.eml, wizz/it-6737400.eml";

    public $reFrom = "reservations@wizzair.com";
    public $reSubject = [
        "multiple" => "Your itinerary:",
    ];
    public $reBody = 'Wizz Air';
    public $reBody2 = [
        "en" => "Flight details",
        "es" => "Datos del vuelo",
        "nl" => "Vluchtdetails",
        "ro" => "Detaliile zborului",
        "it" => "Dettagli del volo",
        "pl" => "Kod potwierdzenia:",
        "fr" => "Informations sur le vol",
        "no" => "Opplysninger om flyging",
        "hu" => "Járat adatai",
        "ru" => "Сведения о рейсе",
        "de" => "Fluginformationen",
        "lt" => "Skrydžio informacija",
        "lv" => "Lidojuma dati",
        "bg" => "Подробности за полета",
    ];

    public static $dictionary = [
        "en" => [],
        "es" => [
            "Your confirmation code:" => "Su código de confirmación:",
            "First name"              => "Nombre",
            "Grand total"             => "Importe total",
            "Booking date:"           => "Fecha de reserva:",
            "Departs from"            => "Sale de",
        ],
        "nl" => [
            "Your confirmation code:" => "Uw bevestigingscode:",
            "First name"              => "Voornaam",
            "Grand total"             => "Eindtotaal",
            "Booking date:"           => "Boekingsdatum:",
            "Departs from"            => "Vertrekpunt",
        ],
        "ro" => [
            "Your confirmation code:" => "Codul dvs. de confirmare:",
            "First name"              => "Prenume",
            "Grand total"             => "Total general",
            "Booking date:"           => "Data rezervării:",
            "Departs from"            => "Pleacă din",
        ],
        "it" => [
            "Your confirmation code:" => "Codice di conferma:",
            "First name"              => "Nome",
            "Grand total"             => "Totale",
            "Booking date:"           => "Data della prenotazione:",
            "Departs from"            => "Luogo di partenza",
        ],
        "pl" => [
            "Your confirmation code:" => "Kod potwierdzenia:",
            "First name"              => "Imię",
            "Grand total"             => "Suma całkowita",
            "Booking date:"           => "Data rezerwacji:",
            "Departs from"            => "Wylot z",
        ],
        "fr" => [
            "Your confirmation code:" => "Votre code de confirmation :",
            "First name"              => "Prénom",
            "Grand total"             => "Total général",
            "Booking date:"           => "Date de réservation :",
            "Departs from"            => "Part de",
        ],
        "no" => [
            "Your confirmation code:" => "Din bekreftelseskode:",
            "First name"              => "Fornavn",
            "Grand total"             => "Totalsum",
            "Booking date:"           => "Bookingdato:",
            "Departs from"            => "Avgang fra",
        ],
        "hu" => [
            "Your confirmation code:" => "Ügyfél visszaigazoló kódja:",
            "First name"              => "Utónév",
            "Grand total"             => "Mindösszesen",
            "Booking date:"           => "Foglalás dátuma:",
            "Departs from"            => "Járat indulási helye",
        ],
        "ru" => [
            "Your confirmation code:" => "Код подтверждения:",
            "First name"              => "Имя",
            "Grand total"             => "Общая сумма",
            "Booking date:"           => "Дата бронирования:",
            "Departs from"            => "Аэропорт отправления",
            "Terminal"                => "Терминал",
        ],
        "de" => [
            "Your confirmation code:" => "Ihr Bestätigungscode:",
            "First name"              => "	Vorname",
            "Grand total"             => "Gesamtbetrag",
            "Booking date:"           => "Buchungsdatum:",
            "Departs from"            => "Abflug von",
        ],
        "lt" => [
            "Your confirmation code:" => "Jūsų patvirtinimo kodas:",
            "First name"              => "Vardas",
            "Grand total"             => "Bendroji suma",
            "Booking date:"           => "Užsakymo data:",
            "Departs from"            => "Išvyksta iš",
        ],
        "lv" => [
            "Your confirmation code:" => "Jūsu apstiprinājuma kods:",
            "First name"              => "Vārds",
            "Grand total"             => "Kopsumma",
            "Booking date:"           => "Rezervējuma datums:",
            "Departs from"            => "Izlidošanas vieta",
        ],
        "bg" => [
            "Your confirmation code:" => "Вашият код за потвърждение:",
            "First name"              => "Собствено име",
            "Grand total"             => "Обща сума",
            "Booking date:"           => "Дата на резервация:",
            "Departs from"            => "Тръгва от",
            "Terminal"                => "Терминал",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Your confirmation code:"));

        // Passengers
        foreach ($this->http->XPath->query("//text()[normalize-space(.)='" . $this->t("First name") . "']/ancestor::*[name()='table' or name()='tbody'][1]/tr[position()>1 and not(contains(.,'" . $this->t("First name") . "'))]") as $root) {
            $it['Passengers'][] = implode(" ", $this->http->FindNodes("./td", $root));
        }

        // Cancelled
        $node = $this->nextText($this->t("Grand total"));

        if (preg_match("#(\d[\d\.]*\d)\s+([A-Z]{3})#", $node, $m)) {
            // TotalCharge
            $it['TotalCharge'] = $m[1];

            // Currency
            $it['Currency'] = $m[2];
        }

        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Booking date:"))));

        $xpath = "//text()[starts-with(normalize-space(.), '" . $this->t("Departs from") . "')]/ancestor::*[name()='table' or name()='tbody'][1]/tr[./preceding::text()[starts-with(normalize-space(.), '" . $this->t("Departs from") . "')] and count(td)=5]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]", $root, true, "#^\w{2}\s*(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]", $root, true, "#(.*?)(?:\s+-|$)#");

            //DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[2]", $root, true, "#" . $this->t("Terminal") . "\s+(\d+)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[3]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[4]", $root, true, "#(.*?)(?:\s+-|$)#");

            //ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[4]", $root, true, "#" . $this->t("Terminal") . "\s+(\d+)#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[5]", $root)));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#^(\w{2})\s*\d+$#");

            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        $this->http->setBody('<?xml version="1.0" encoding="UTF-8"?>' . html_entity_decode($parser->getHTMLBody()));
        $body = $this->http->Response['body'];

        foreach ($this->reBody2 as $re) {
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
        $itineraries = [];
        $this->http->setBody('<?xml version="1.0" encoding="UTF-8"?>' . html_entity_decode($parser->getHTMLBody()));

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'Lang detect: ' . $this->lang,
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
        return $this->http->FindSingleNode("(.//text()[starts-with(normalize-space(.), '{$field}')])[{$n}]/following::text()[string-length(normalize-space(.))>1][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
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
        //		$year = date("Y", $this->date);
        $in = [
            "#^(\d+)\.\s+([^\d\s\.]+)\.?\s+(\d{4})$#",
            "#^(\d+)\.\s+([^\d\s\.]+)\.?\s+(\d{4})\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        //		print_r($str . "\n");
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
}
