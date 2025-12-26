<?php

namespace AwardWallet\Engine\hbooker\Email;

// TODO: similar hbooker:YourReservation
class It3714369 extends \TAccountCheckerExtended
{
    public $mailFiles = "hbooker/it-8567475.eml, hbooker/it-8892525.eml, hbooker/it-8959608.eml, hbooker/it-9078750.eml, hbooker/it-9081477.eml";

    public $reBody = "HOTEL DE";
    public $reBody2 = [
        "de" => ["Vielen Dank für Ihre Buchung!", "Hoteldaten"],
        "en" => "Thank you for your booking!",
        "sv" => "Tack för din bokning!",
        "zh" => "非常感谢您的预订",
        "nl" => "Hartelijk dank voor uw boeking!",
        "it" => "Grazie per la prenotazione!",
        "es" => "Muchas gracias por tu reserva",
        "pt" => "Muito obrigado pela sua reserva",
        "fi" => "Kiitos varauksestasi!",
    ];
    public $reFrom = "Confirmations@hotel.de";
    public $reSubject = [
        "de"  => "bei HOTEL DE für",
        "en"  => "at HOTEL INFO at",
        "sv"  => "hos bosch-hotels.com på",
        "zh"  => "您在HOTEL DE的",
        "nl"  => "bij bosch-hotels.com voor",
        "nl2" => "bij hotel.info voor",
        "it"  => "presso bosch-hotels.com per",
        "de2" => "Ihre Umbuchung",
        "es"  => "en HOTEL INFO para",
        "pt"  => "em HOTEL INFO para",
        "fi"  => "hotel.de-varauksesi",
        //        "de3" => "hotelbooker.org",//Your reservation|Ihre Reservierung ... hotelbooker.org
    ];

    public static $dictionary = [
        "de" => [
            "Room description" => "Zimmerbeschreibung",
        ],
        "en" => [
            "Buchungsnummer:"                                   => "Reservation Number:",
            "Hotelname:"                                        => "Name of hotel:",
            "Anreise:"                                          => "Arrival:",
            "Abreise:"                                          => "Departure:",
            "Straße:"                                           => "Street:",
            "PLZ/Ort:"                                          => "Postal code/City:",
            "Telefon:"                                          => "Telephone:",
            "Fax:"                                              => "Fax:",
            "Nachname"                                          => "Last Name",
            "Vorname"                                           => "First Name",
            "Personen:"                                         => "Persons:",
            "Anzahl Zimmer:"                                    => "No. of Rooms:",
            "Room description"                                  => "Room description",
            "Unverbindliche Mitteilungen/Wünsche an das Hotel:" => "Non-binding wishes and notifications for the attention of the hotel:",
            "Preis:"                                            => "Total price:",
            "Buchungsdatum:"                                    => "Reservation Date:",
            "Buchungs- und Stornierungsbedingungen:"            => "Booking and Cancellation Policy:",
        ],
        "sv" => [
            "Buchungsnummer:"                                   => "Bokningsnummer:",
            "Hotelname:"                                        => "Hotellets namn:",
            "Anreise:"                                          => "Ankomst:",
            "Abreise:"                                          => "Avresa:",
            "Straße:"                                           => "Gataß:",
            "PLZ/Ort:"                                          => "Postnummer/ort:",
            "Telefon:"                                          => "Telefon:",
            "Fax:"                                              => "Fax:",
            "Nachname"                                          => "Efternamn",
            "Vorname"                                           => "Förnamn",
            "Personen:"                                         => "Antal personer:",
            "Anzahl Zimmer:"                                    => "Antal rum:",
            "Unverbindliche Mitteilungen/Wünsche an das Hotel:" => "Oförbindliga meddelanden/üönskemål till hotellet:",
            "Preis:"                                            => "Pris, totalt:",
            "Buchungsdatum:"                                    => "Bokningsdatum:",
            //			"Buchungs- und Stornierungsbedingungen:" => "",
        ],
        "zh" => [
            "Buchungsnummer:"  => "预订号码",
            "Hotelname:"       => "酒店名称",
            "Anreise:"         => "入住",
            "Abreise:"         => "离店",
            "Straße:"          => "街道",
            "PLZ/Ort:"         => "邮政编码/地点",
            "Telefon:"         => "电话",
            "Fax:"             => "传真",
            "Nachname"         => "姓：",
            "Vorname"          => "名：",
            "Personen:"        => "入住总人数",
            "Anzahl Zimmer:"   => "客房数",
            "Room description" => "客房说明",
            "Preis:"           => "总价",
            //			"Preis:" => "总共",
            "Buchungsdatum:" => "预订日期",
            //			"Buchungs- und Stornierungsbedingungen:" => "",
        ],
        "nl" => [
            "Buchungsnummer:"  => "Reserveringsnummer:",
            "Hotelname:"       => "Naam hotel:",
            "Anreise:"         => "Aankomst:",
            "Abreise:"         => "Vertrek:",
            "Straße:"          => "Straat:",
            "PLZ/Ort:"         => "Postcode/woonplaats",
            "Telefon:"         => "Telefoon:",
            "Fax:"             => "Fax:",
            "Nachname"         => "Achternaam",
            "Vorname"          => "Voornaam",
            "Personen:"        => "Aantal personen:",
            "Anzahl Zimmer:"   => "Aantal kamers:",
            "Room description" => "Kamerbeschrijving",
            //"Unverbindliche Mitteilungen/Wünsche an das Hotel:" => "Non-binding wishes and notifications for the attention of the hotel:",
            "Preis:"                                 => "Totale prijs:",
            "Buchungsdatum:"                         => "Reserveringsdatum:",
            "Buchungs- und Stornierungsbedingungen:" => "Reserverings- en annuleringsvoorwaarden:",
        ],
        "it" => [
            "Buchungsnummer:"  => "Numero della prenotazione:",
            "Hotelname:"       => "Nome hotel:",
            "Anreise:"         => "Arrivo:",
            "Abreise:"         => "Partenza:",
            "Straße:"          => "Via:",
            "PLZ/Ort:"         => "CAP/Località:",
            "Telefon:"         => "Telefono:",
            "Fax:"             => "Fax:",
            "Nachname"         => "Cognome",
            "Vorname"          => "Nome",
            "Personen:"        => "Numero di persone:",
            "Anzahl Zimmer:"   => "Numero di camere:",
            "Room description" => "Descrizione camera",
            //"Unverbindliche Mitteilungen/Wünsche an das Hotel:" => "Non-binding wishes and notifications for the attention of the hotel:",
            "Preis:"         => "Prezzo totale:",
            "Buchungsdatum:" => "Data della prenotazione:",
            //			"Buchungs- und Stornierungsbedingungen:" => "",
        ],
        "es" => [
            "Buchungsnummer:"  => "Número de la reserva:",
            "Hotelname:"       => "Nombre del hotel:",
            "Anreise:"         => "Llegada:",
            "Abreise:"         => "Salida:",
            "Straße:"          => "Calle:",
            "PLZ/Ort:"         => "CP/Localidad:",
            "Telefon:"         => "Teléfono:",
            "Fax:"             => "Fax:",
            "Nachname"         => "Apellidos",
            "Vorname"          => "Nombre",
            "Personen:"        => "Personas:",
            "Anzahl Zimmer:"   => "Descripción de la habitación:",
            "Room description" => "Descripción de la habitación:",
            //			"Unverbindliche Mitteilungen/Wünsche an das Hotel:" => "",
            "Preis:"                                 => "Precio total:",
            "Buchungsdatum:"                         => "Fecha/Periodo de reserva:",
            "Buchungs- und Stornierungsbedingungen:" => "Condiciones de reserva y de cancelación:",
        ],
        "pt" => [
            "Buchungsnummer:"  => "N.º de reserva:",
            "Hotelname:"       => "Nome do hotel:",
            "Anreise:"         => "Chegada:",
            "Abreise:"         => "Partida:",
            "Straße:"          => "Rua:",
            "PLZ/Ort:"         => "Código postal/Localidade:",
            "Telefon:"         => "Telefone:",
            "Fax:"             => "Fax:",
            "Nachname"         => "Sobrenome",
            "Vorname"          => "Nome",
            "Personen:"        => "Pessoas:",
            "Anzahl Zimmer:"   => "N.º de quartos:",
            "Room description" => 'Descrição do quarto:',
            //			"Unverbindliche Mitteilungen/Wünsche an das Hotel:" => "",
            "Preis:"                                 => "Total:",
            "Buchungsdatum:"                         => "Data de reserva:",
            "Buchungs- und Stornierungsbedingungen:" => "Condições de reserva e de cancelamento da reserva:",
        ],
        "fi" => [
            "Buchungsnummer:"  => "Varausnumero:",
            "Hotelname:"       => "Hotellin nimi:",
            "Anreise:"         => "Tulopäivä:",
            "Abreise:"         => "Lähtöpäivä:",
            "Straße:"          => "Yritys/laitos:",
            "PLZ/Ort:"         => "Postinro/Paikkakunta:",
            "Telefon:"         => "Puhelin:",
            "Fax:"             => "Faksi:",
            "Nachname"         => "Sukunimi",
            "Vorname"          => "Etunimi",
            "Personen:"        => "Henkilömäärä:",
            "Anzahl Zimmer:"   => "Huoneiden lukumäärä:",
            "Room description" => 'Huonekuvaus:',
            //			"Unverbindliche Mitteilungen/Wünsche an das Hotel:" => "",
            "Preis:"                                 => "Kokonaishinta:",
            "Buchungsdatum:"                         => "Varauspäivä:",
            "Buchungs- und Stornierungsbedingungen:" => "Varaus- ja peruutusehdot:",
        ],
    ];

    public $lang = "";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];
                $it['Kind'] = "R";

                // ConfirmationNumber
                $node = $this->getField($this->t("Buchungsnummer:"));

                if (preg_match("#([A-Z\d]+)\s*(?:\([A-Z\d]+\)|)#", $node, $m)) {
                    $it['ConfirmationNumber'] = $m[1];
                } else {
                    $it['ConfirmationNumber'] = $node;
                }

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = $this->getField($this->t("Hotelname:"));

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime($this->normalizeDate($this->getField($this->t("Anreise:"))));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->normalizeDate($this->getField($this->t("Abreise:"))));

                // Address
                $it['Address'] = $this->getField($this->t("Straße:")) . ', ' . $this->getField($this->t("PLZ/Ort:"));

                // DetailedAddress

                // Phone
                $it['Phone'] = $this->getField($this->t("Telefon:"));

                // Fax
                $it['Fax'] = $this->getField($this->t("Fax:"));

                // GuestNames
                $guests = [];

                foreach ($this->http->XPath->query("//*[contains(text(), '" . $this->t("Nachname") . "')]/ancestor::tr[1][contains(., '" . $this->t("Vorname") . "')]") as $root) {
                    $guests[] = $this->http->FindSingleNode('./td[1]', $root, null, "#" . $this->t("Nachname") . "\s*:?\s(.+)#") . ' ' . $this->http->FindSingleNode('./td[2]', $root, null, "#" . $this->t("Vorname") . "\s*:?\s(.+)#");
                }
                $it["GuestNames"] = $guests;

                // Guests

                $it['Guests'] = $this->getField($this->t("Personen:"));

                // Kids
                // Rooms
                $it['Rooms'] = $this->http->FindSingleNode("//*[contains(text(), '" . $this->t("Anzahl Zimmer:") . "')]/ancestor::tr[1]/following-sibling::tr[1][not(contains(.,'Descripción de la tarifa'))]/td[2]", null, true, "#^\s*(\d+)\s*$#");

                // Rate
                // RateType
                $it['RateType'] = $this->http->FindSingleNode("//*[contains(text(), '" . $this->t("Descripción de la tarifa:") . "')]/ancestor-or-self::td[1]/following-sibling::td[1]"); //es: Descripción de la tarifa

                if (null === $it['RateType']) {
                    unset($it['RateType']);
                }

                // CancellationPolicy
                $it['CancellationPolicy'] = $this->http->FindSingleNode("//text()[contains(normalize-space(), '" . $this->t("Buchungs- und Stornierungsbedingungen:") . "')]/ancestor::tr[1]/following-sibling::tr[2]");

                // RoomType
                $it['RoomType'] = $this->http->FindSingleNode("//*[contains(text(), '" . $this->t("Room description") . "')]/ancestor::tr[1]/td[2]");

                if (empty($it['RoomType'])) {
                    $it['RoomType'] = trim($this->http->FindSingleNode("//*[contains(text(), '" . $this->t("Unverbindliche Mitteilungen/Wünsche an das Hotel:") . "')]/ancestor::tr[1]/following-sibling::tr[1]"), ":; ");
                }

                if (strlen($it['RoomType']) > 200) {
                    $subj = substr($it['RoomType'], 0, 200);
                    $this->logger->warning($subj);
                    $len = max(strrpos($subj, ' '), strrpos($subj, ','), strrpos($subj, '.'));
                    $it['RoomType'] = substr($subj, 0, $len);
                }
                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                $it['Total'] = cost($this->getField($this->t("Preis:")));
                // Currency
                $it['Currency'] = currency($this->getField($this->t("Preis:")));

                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                // Cancelled

                if (!empty($this->getField($this->t("Stornierungscode:"))) && !empty($this->getField($this->t("Stornierung:")))) {
                    $it['Cancelled'] = true;
                }
                // ReservationDate
                $it['ReservationDate'] = strtotime($this->normalizeDate($this->getField($this->t("Buchungsdatum:"))));

                // NoItineraries
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $body .= $this->getAttachedHtml($parser->getRawBody());

        if (strpos($body, $this->reBody) === false) {
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

    public function getAttachedHtml($texts)
    {
        $body = "";
        $texts = implode("\n", $texts);
        $posBegin1 = stripos($texts, "Content-Type: text/html");
        $i = 0;

        while ($posBegin1 !== false && $i < 30) {
            $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
            $posEnd = stripos($texts, "\n\n", $posBegin);

            if (preg_match("#filename=.*\.htm.*base64#s", substr($texts, $posBegin1, $posBegin - $posBegin1))) {
                $t = substr($texts, $posBegin, $posEnd - $posBegin);
                $body .= base64_decode($t);
            }
            $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
            $i++;
        }

        if (stripos($body, "ISO-8859-1")) {
            $body = iconv("UTF-8", "ISO-8859-1//IGNORE", $body);
        }

        return $body;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $body = $parser->getHTMLBody();
        $body .= $this->getAttachedHtml($parser->getRawBody());
        $this->http->SetEmailBody($body);

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        foreach ($this->reBody2 as $lang => $re) {
            $reB = (array) $re;

            foreach ($reB as $r) {
                if (strpos($this->http->Response["body"], $r) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $itineraries = [];

        foreach ($this->processors as $re => $processor) {
            //			if (stripos($body, $re)) {
            $processor($itineraries);
            //				break;
//			}
        }

        $result = [
            'emailType'  => 'It3714369' . ucfirst($this->lang),
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

    private function getField($str)
    {
        return $this->http->FindSingleNode("(//td[not(.//td) and contains(., '{$str}')])[1]/following-sibling::td[1]");
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
        switch ($this->lang) {
            case "en":
            case "it":
            case "es":
            case "pt":
                $in = [
                    "#^(\d+)/(\d+)/(\d{4})$#",
                    "#^(\d+)/(\d+)/(\d{4})\s+(\d+:\d+)$#",
                ];

                if ($this->findDay() == 0) {
                    $out = [
                        "$3-$2-$1",
                        "$3-$2-$1 $4",
                    ];
                } else {
                    $out = [
                        "$3-$1-$2",
                        "$3-$1-$2 $4",
                    ];
                }

                break;

            default:
                $in = [
                    "#^(\d+)/(\d+)/(\d{4})\s+(\d+:\d+)$#",
                    "#(.+)#",
                ];
                $out = [
                    "$1.$2.$3, $4",
                    "$1",
                ];

                break;
        }

        return preg_replace($in, $out, $str);
    }

    private function findDay()
    {
        $first = explode("/", preg_replace("#(\d+/\d+/\d+).*#", "$1", $this->getField($this->t("Anreise:"))));
        $end = explode("/", preg_replace("#(\d+/\d+/\d+).*#", "$1", $this->getField($this->t("Abreise:"))));
        $diff = [];

        for ($i = 0; $i < 3; $i++) {
            if (isset($first[$i]) && isset($end[$i])) {
                $diff[$i] = abs($end[$i] - $first[$i]);
            }
        }
        /*		uksort($diff, function ($a, $b) {
                    if ($a == $b) return 0;
                    return ($a < $b) ? -1 : 1;
                });
                return array_keys($diff)[0];*/
        return array_keys($diff, max($diff))[0];
    }
}
