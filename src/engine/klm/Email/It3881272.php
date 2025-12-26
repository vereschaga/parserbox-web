<?php

namespace AwardWallet\Engine\klm\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\ItineraryArrays\AirTripSegment;

class It3881272 extends \TAccountChecker
{
    use \PriceTools;

    public $mailFiles = "klm/it-33659052.eml, klm/it-43734847.eml, klm/it-4798882.eml, klm/it-4800709.eml, klm/it-4828678.eml, klm/it-4847897.eml, klm/it-4860867.eml, klm/it-4895354.eml, klm/it-4896110.eml, klm/it-4897619.eml, klm/it-4975679.eml, klm/it-5025031.eml, klm/it-5079493.eml, klm/it-50815829.eml, klm/it-5709734.eml, klm/it-5864470.eml, klm/it-7735704.eml, klm/it-8063607.eml"; // +1 bcdtravel(html)[de]

    public $reSubject = [
        "nl"       => "Ticket voor",
        "multiple" => "Ticket for",
    ];

    public $reBody = 'KLM.com';

    public $reBody2 = [
        "en" => ["Flight schedule", 'You can find more details in your ticket (attached) and in My Trip on KLM.com'],
        "nl" => ["Nummer van frequent flyer programma", "Naam van passagier", "U vindt meer gegevens op uw ticket (zie bijlage) en onder Mijn Reis op KLM.com"],
        "da" => ["Sådan ændrer eller annullerer du din billet", "Du kan finde flere oplysninger i din billet (vedhæftet) og i Min Rejse på KLM.com"],
        "ru" => ["Расписание рейса", "Вы можете найти подробные данные в Вашем билете (во вложении) или в разделе «Мое Путешествие» на сайте KLM.com"],
        "pt" => ["Horário de voo", "Pode encontrar mais detalhes no seu bilhete (em anexo) e em A Minha Viagem em KLM.com", "Você pode encontrar mais dados em seu bilhete (em anexo) e em Minha Viagem, em KLM.com"],
        "es" => ["Horario de vuelo", "Encontrará más detalles en su billete (adjunto) y en Mi Viaje, en KLM.com"],
        "it" => ["Orario del volo", "Trova maggiori informazioni direttamente sul biglietto (allegato) e nella sezione Il Mio Viaggio su KLM.com"],
        "no" => ["Flyinformasjon", "Du finner flere opplysninger på billetten din (vedlagt) og i Min Reise på KLM.com"],
        "fr" => ["Horaires de vol", 'Numéro programme de fidélité ', "Vous trouverez plus de détails sur votre billet (ci-joint) et dans Mon Voyage sur KLM.com"],
        "pl" => ["Plan lotu", "Więcej szczegółów znajdziesz na załączonym bilecie oraz na Moja Podróż na KLM.com"],
        "sv" => ["Resplan", "Du hittar fler uppgifter på din biljett (bifogad) och på Min Resa på KLM.com"],
        "de" => ["Flugplan", "Nähere Informationen finden Sie auf Ihrem Ticket (im Anhang) und unter Meine Reise auf KLM.com"],
        'ja' => ['運航スケジュール', "詳細は、航空券（添付）およびKLM.comの「お客様の予約」に記載されています"],
        'fi' => ['Kirjaudu sisään KLM.com-sivuston Matkani-palveluun', "Löydät lisää tietoja lipustasi (liitteenä) sekä KLM.com-sivuston Matkani-palvelusta"],
    ];

    public static $dictionary = [
        "en" => [],
        "nl" => [
            "Booking code:"      => "Boekingscode:",
            "Passenger name"     => "Naam van passagier",
            "Total ticket price" => "Totaalprijs van het ticket",
            "Flight"             => "Vlucht",
            "From"               => "Van",
            "To"                 => "Naar",
            'Ticket number'      => ['Ticket nummer', 'Ticketnummer'],
            'FrequentFlyer'      => 'Nummer van frequent flyer programma',
            //HTML
            'Dear' => 'Beste',
            'at'   => 'om',
        ],
        "da" => [
            "Booking code:"      => "Reservationskode:",
            "Passenger name"     => "Passagerens navn",
            "Total ticket price" => "Samlet billetpris",
            "Flight"             => "Flyvning",
            "From"               => "Fra",
            "To"                 => "Til",
            'Ticket number'      => 'Billetnummer',
            //HTML
            'Dear' => 'Kære',
            'at'   => 'på',
        ],
        "ru" => [
            "Booking code:"      => "Код бронирования:",
            "Passenger name"     => "Имя пассажира",
            "Total ticket price" => "Полная цена билета",
            "Flight"             => "Рейс",
            "From"               => "Из",
            "To"                 => "В",
            'Ticket number'      => 'Номер билета',
            'FrequentFlyer'      => 'Номер в программе для часто летающих пассажиров',
            //HTML
            'Dear' => 'Уважаемый (-ая)',
            'at'   => 'в',
        ],
        "pt" => [
            "Booking code:"      => ["Código de reserva:", "Código da reserva:"],
            "Passenger name"     => "Nome do passageiro",
            "Total ticket price" => "Preço total do bilhete",
            "Flight"             => "Voo",
            "From"               => "De",
            "To"                 => "Para",
            'Ticket number'      => 'Número do bilhete',
            //HTML
            'Dear' => 'Caro/a',
            'at'   => ['em', 'às'],
        ],
        "es" => [
            "Booking code:"      => "Código de reserva:",
            "Passenger name"     => "Nombre del pasajero",
            "Total ticket price" => "Precio total del billete",
            "Flight"             => "Vuelo",
            "From"               => "De",
            "To"                 => "A",
            'Ticket number'      => 'Número de billete',
            //HTML
            'Dear' => 'Estimado/a',
            'at'   => 'a las',
        ],
        "it" => [
            "Booking code:"      => "Codice di prenotazione:",
            "Passenger name"     => "Nome del passeggero",
            "Total ticket price" => "Prezzo totale del biglietto",
            "Flight"             => "Volo",
            "From"               => "Da",
            "To"                 => "A",
            'Ticket number'      => 'Numero di biglietto',
            //HTML
            'Dear' => 'Gentile',
            'at'   => 'alle ore',
        ],
        "no" => [
            "Booking code:"      => "Referansenummer:",
            "Passenger name"     => "Passasjernavn",
            "Total ticket price" => "Samlet billettpris",
            "Flight"             => "Rutenummer",
            "From"               => "Fra",
            "To"                 => "Til",
            'Ticket number'      => 'Billettnummer',
            'FrequentFlyer'      => 'Nummer i bonuspoengprogrammet',
            //HTML
            'Dear' => 'Kjære',
            'at'   => 'kl.',
        ],
        "fr" => [
            "Booking code:"      => ["Code de réservation:", "Code de réservation :"],
            "Passenger name"     => "Nom du passager",
            "Total ticket price" => "Prix total du billet",
            "Flight"             => "Vol",
            "From"               => "De",
            "To"                 => "Vers",
            'Ticket number'      => 'Numéro de billet',
            //HTML
            'Dear' => 'Cher/Chère',
            'at'   => 'à',
        ],
        "pl" => [
            "Booking code:"      => "Kod rezerwacji:",
            "Passenger name"     => "Nazwisko pasażera",
            "Total ticket price" => "Całkowita cena biletu",
            "Flight"             => "Lot",
            "From"               => "Z",
            "To"                 => "Do",
            'Ticket number'      => 'Numer biletu',
            //HTML
            'Dear' => 'Szanowny(-a)',
            'at'   => 'o',
        ],
        "sv" => [
            "Booking code:"      => "Bokningskod:",
            "Passenger name"     => "Passagerarnamn",
            "Total ticket price" => "Totalt biljettpris",
            "Flight"             => "Flyg",
            "From"               => "Från",
            "To"                 => "Till",
            'Ticket number'      => 'Biljettnummer',
            'FrequentFlyer'      => 'Flygbonusprogramnummer',
            //HTML
            'Dear' => 'Hej',
            'at'   => 'kl.',
        ],
        "de" => [
            "Booking code:"      => "Buchungscode:",
            "Passenger name"     => "Passagiername",
            "Total ticket price" => "Gesamtpreis des Tickets",
            "Flight"             => "Flug",
            "From"               => "Von",
            "To"                 => "Nach",
            'Ticket number'      => 'Ticketnummer',
            //HTML
            'Dear' => 'Sehr geehrte/Sehr geehrter',
            'at'   => 'um',
        ],
        'ja' => [
            "Booking code:"      => ["予約コード:", "予約コード："],
            "Passenger name"     => "搭乗者名",
            "Total ticket price" => "航空券の合計⾦額",
            "Flight"             => "便名",
            "From"               => "出発地",
            "To"                 => "到着地",
            'Ticket number'      => '航空券番号',
            'FrequentFlyer'      => 'マイレージプログラム会員番号',
            //HTML
            'Dear' => '様',
            'at'   => '時刻：',
        ],
        'fi' => [
            "Booking code:"      => "Varauskoodi:",
            "Passenger name"     => "Matkustajan nimi",
            "Total ticket price" => "Lipun kokonaishinta",
            "Flight"             => "Lento",
            "From"               => "Lähtöpaikka",
            "To"                 => "Kohde",
            'Ticket number'      => 'Lipun numero',
            //HTML
            'Dear' => 'Hyvä',
            'at'   => 'osoitteessa',
        ],
    ];

    public $lang = 'nl';

    /** @var \HttpBrowser */
    private $pdf = null;

    public function unique_multidim_array($array, $key)
    {
        $temp_array = [];
        $i = 0;
        $key_array = [];

        foreach ($array as $val) {
            if (!in_array($val[$key], $key_array)) {
                $key_array[$i] = $val[$key];
                $temp_array[$i] = $val;
            }
            $i++;
        }

        return $temp_array;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@ticket.klm.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'noreply@ticket.klm.com') === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>?~`!@\#$%^&*\[\]=\(\)\-–{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if (stripos($textPdf, $this->reBody) === false) {
                continue;
            }

            foreach ($this->reBody2 as $phrases) {
                foreach ($phrases as $phrase) {
                    if (stripos($textPdf, $phrase) !== false) {
                        return true;
                    }
                }
            }
        }
        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[contains(normalize-space(),\"" . $phrase . "\")]")->length > 0
                    || stripos($body, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;

        if (!$this->tablePdf($parser)) {
            $it = [$this->parseEmail()];
        } else {
            $textPdf = text($this->pdf->Response['body']);

            foreach ($this->reBody2 as $lang => $phrases) {
                foreach ($phrases as $phrase) {
                    if (strpos($textPdf, $phrase) !== false) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }

            $it = $this->parsePdf();
            $it = $this->busRescue($it);
        }

        return [
            'parsedData' => ['Itineraries' => $it],
            'emailType'  => 'Reservations' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $types = 2; // html | pdf
        $cnt = $types * count(self::$dictionary);

        return $cnt;
    }

    private function parsePdf()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        //		$field = $this->t('Booking code:');
        //		if ( !is_array($field) ) $field = [$field];
        //		$rule = implode(' or ', array_map(function($s){ return "starts-with(normalize-space(.),'{$s}')"; }, $field));
        $it['RecordLocator'] = $this->pdf->FindSingleNode("//text()[{$this->starts($this->t('Booking code:'))}]", null, true, "#{$this->opt($this->t('Booking code:'))}\s*(\w+)#u");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->pdf->FindSingleNode("//text()[{$this->starts($this->t('Booking code:'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "/\b([A-Z\d]{5,8})\b/");
        }

        // Passengers
        $it['Passengers'] = array_filter(array_map('trim', $this->pdf->FindNodes("//text()[normalize-space(.)='" . $this->t('Passenger name') . "']/following::text()[string-length(normalize-space(.))>1][1]")));

        $it['AccountNumbers'] = array_filter(array_map('trim', $this->pdf->FindNodes("//text()[normalize-space(.)='" . $this->t('FrequentFlyer') . "']/following::text()[string-length(normalize-space(.))>1][1]")));

        // TotalCharge
        $it['TotalCharge'] = $this->cost($this->pdf->FindSingleNode($q = "//text()[starts-with(normalize-space(.),'" . $this->t('Total ticket price') . "')]/following::text()[string-length(normalize-space(.))>1][1]"));

        // Currency
        $it['Currency'] = $this->currency($this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'" . $this->t('Total ticket price') . "')]/following::text()[string-length(normalize-space(.))>1][1]"));

        $ticketNumber = $this->pdf->FindSingleNode("//text()[{$this->starts($this->t('Ticket number'))}]/following::text()[string-length(normalize-space(.))>1][1]", null, true, '/([\d\-]+)/');

        if (empty($ticketNumber)) {
            $ticketNumber = $this->pdf->FindSingleNode("//text()[{$this->starts($this->t('Ticket number'))}][1]", null, true, '/([\d\-]+)/');
        }
        $it['TicketNumbers'][] = $ticketNumber;

        // TripSegments
        $flightBlocks = $this->pdf->XPath->query("//text()[normalize-space(.)='" . $this->t("Flight") . "']/ancestor::tr[1]");

        if ($flightBlocks->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . "//text()[normalize-space(.)='" . $this->t("Flight") . "']/ancestor::tr[1]");

            return false;
        }

        foreach ($flightBlocks as $root) {
            $this->logger->debug('flightBlock');
            $tableHeaders = $this->pdf->XPath->query("./td[normalize-space(.)='" . $this->t('From') . "' or normalize-space(.)='" . $this->t('To') . "' or normalize-space(.)='" . $this->t('Flight') . "']", $root);

            $cols = [];

            foreach ($tableHeaders as $node) {
                $cols[$this->pdf->FindSingleNode('.', $node)] = $node;
            }

            if (!isset($cols[$this->t('From')]) || !isset($cols[$this->t('To')]) || !isset($cols[$this->t('Flight')])) {
                return [];
            }

            if ($itsegment = $this->ParseSegment($cols, 0)) {
                $it['TripSegments'][] = $itsegment;
            }

            $y = 4;

            while (($c1 = $this->re('#( - )#', $this->cell($cols[$this->t('From')], 0, $y))) || ($c2 = $this->re('#( - )#', $this->cell($cols[$this->t('From')], 0, $y + 1)))) {
                if (!empty($c1)) {
                    $this->logger->notice('c1 ->' . $y);

                    if ($itsegment = $this->ParseSegment($cols, $y)) {
                        $it['TripSegments'][] = $itsegment;
                    }
                    // Example: it-43734847.eml
                    elseif ($itsegment = $this->ParseSegment($cols, $y + 1)) {
                        $it['TripSegments'][] = $itsegment;
                    }
                } elseif (!empty($c2)) {
                    $this->logger->notice('c2 ->' . ($y + 1));

                    if ($itsegment = $this->ParseSegment($cols, $y + 1)) {
                        $it['TripSegments'][] = $itsegment;
                    }
                    // Example: it-43734847.eml
                    elseif ($itsegment = $this->ParseSegment($cols, $y + 2)) {
                        $it['TripSegments'][] = $itsegment;
                    }
                }
                $y += 4;
            }
        }
        $it['TripSegments'] = $this->unique_multidim_array($it['TripSegments'], 'FlightNumber');

        return $it;
    }

    private function busRescue($it)
    {
        if (empty($it['TripSegments'])) {
            return [$it];
        }
        $bus = $it;
        $bus['TripCategory'] = TRIP_CATEGORY_BUS;

        foreach ($bus['TripSegments'] as $key => &$value) {
            if (isset($value['_type'])) {
                if ($value['_type'] != 'bus') {
                    unset($bus['TripSegments'][$key]);
                } else {
                    $value['FlightNumber'] = $value['AirlineName'] . ' ' . $value['FlightNumber'];
                    unset($value['_type'], $value['AirlineName']);
                }
            }
        }

        foreach ($it['TripSegments'] as $key => &$value) {
            if (isset($value['_type'])) {
                if ($value['_type'] != 'flight') {
                    unset($it['TripSegments'][$key]);
                } else {
                    unset($value['_type']);
                }
            }
        }
        $it['TripSegments'] = array_values($it['TripSegments']);

        if (empty($bus['TripSegments'])) {
            return [$it];
        }
        $bus['TripSegments'] = array_values($bus['TripSegments']);

        return [$it, $bus];
    }

    private function parseEmail(): ?array
    {
        foreach ($this->reBody2 as $lang=>$phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[contains(normalize-space(),\"" . $phrase . "\")]")->length > 0) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $it = ['Kind' => 'T'];

        $it['Passengers'][] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}][1]", null, true, "/{$this->opt($this->t('Dear'))}[ ]*(.+?)(?:,|$|:|!)/");

        $it['RecordLocator'] = $this->http->FindSingleNode("//tr[({$this->starts($this->t('Booking code:'))}) and not(.//tr)][1]", null, true, "/{$this->opt($this->t('Booking code:'))}[ ]*(\w+)/");

        $xpath = "//tr[({$this->starts($this->t('Booking code:'))}) and not(.//tr)][1]/ancestor::table[1]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");

            return null;
        }

        foreach ($roots as $root) {
            /** @var AirTripSegment $seg */
            $seg = [];
            $flightInfo = $this->http->FindSingleNode("descendant::text()[normalize-space(.)!=''][contains(normalize-space(.), '-')][1]", $root);

            if (preg_match('/(.+)\s+\(([A-Z]{3})\)\s*\-\s*(.+)\s+\(([A-Z]{3})\)/u', $flightInfo, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
                $seg['ArrName'] = $m[3];
                $seg['ArrCode'] = $m[4];
            }
            $textAt = array_map(function ($s) {return ' ' . $s . ' '; }, (array) $this->t('at'));
            $depDate = str_replace($textAt, ', ', $this->http->FindSingleNode("descendant::text()[normalize-space(.)!=''][position()>1][{$this->contains($textAt)}][1]", $root));
            $seg['DepDate'] = strtotime($this->normalizeDate($depDate));
            $seg['ArrDate'] = MISSING_DATE;
            $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            $seg['AirlineName'] = AIRLINE_UNKNOWN;
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    private function ParseSegment($cols, $offset = 0)
    {
        //print_r($this->pdf->Response['body']);
        $patterns = [
            'code'    => '/\(([A-Z]{3})\)/',
            'airline' => '/^(\w{2})\s+(\d+)$/',
            'number'  => '/^\w{2}\s+(\d+)$/',
        ];

        $itsegment = [];

        $itsegment['DepDate'] = strtotime($this->normalizeDate($this->cell($cols[$this->t('From')], 0, $offset + 1)) . ',' . $this->cell($cols[$this->t('From')], 0, $offset + 1, '/following::text()[string-length(.)>1][1]'));

        $itsegment['DepCode'] = $this->re($patterns['code'], $this->cell($cols[$this->t('From')], 0, $offset + 2));

        if (!$itsegment['DepCode']) {
            $itsegment['DepCode'] = $this->re($patterns['code'], $this->cell($cols[$this->t('From')], 0, $offset + 3));
        }

        if (!$itsegment['DepCode']) {
            $itsegment['DepCode'] = $this->re($patterns['code'], $this->cell($cols[$this->t('From')], 0, $offset + 4));
        }

        $codes = [
            'Departure' => [
                $this->cell($cols[$this->t('From')], 0, $offset + 2),
                $this->cell($cols[$this->t('From')], 0, $offset + 3),
                $this->cell($cols[$this->t('From')], 0, $offset + 4),
            ],
            'Arrival' => [
                $this->cell($cols[$this->t('To')], 0, $offset + 2),
                $this->cell($cols[$this->t('To')], 0, $offset + 3),
                $this->cell($cols[$this->t('To')], 0, $offset + 4),
            ],
        ];
        $reTerm = '/\([A-Z]{3}\)\s*\w+\s+([A-Z\d]{1,3})/u';
        array_walk($codes, function ($vals, $key) use (&$itsegment, $reTerm) {
            foreach ($vals as $val) {
                if (preg_match($reTerm, $val, $m)) {
                    $itsegment[$key . 'Terminal'] = $m[1];
                }
            }
        });

        $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->cell($cols[$this->t('To')], 0, $offset + 1)) . ',' . $this->cell($cols[$this->t('To')], 0, $offset + 1, '/following::text()[string-length(.)>1][1]'));

        $itsegment['ArrCode'] = $this->re($patterns['code'], $this->cell($cols[$this->t('To')], 0, $offset + 2));

        if (!$itsegment['ArrCode']) {
            $itsegment['ArrCode'] = $this->re($patterns['code'], $this->cell($cols[$this->t('To')], 0, $offset + 3));
        }

        if (!$itsegment['ArrCode']) {
            $itsegment['ArrCode'] = $this->re($patterns['code'], $this->cell($cols[$this->t('To')], 0, $offset + 4));
        }

        if (!$itsegment['AirlineName'] = $this->re($patterns['airline'], $this->cell($cols[$this->t('Flight')], 0, $offset + 1))) {
            $itsegment['AirlineName'] = $this->re($patterns['airline'], $this->cell($cols[$this->t('Flight')], 0, $offset + 2));
        }

        if (!$itsegment['FlightNumber'] = $this->re($patterns['number'], $this->cell($cols[$this->t('Flight')], 0, $offset + 1))) {
            $itsegment['FlightNumber'] = $this->re($patterns['number'], $this->cell($cols[$this->t('Flight')], 0, $offset + 2));
        }
        $type = $this->pdf->FindSingleNode("//text()[contains(normalize-space(.), '{$itsegment['FlightNumber']}')]/ancestor::td[1]/following-sibling::td[contains(text(),': bus')][1]");
        $itsegment['_type'] = stripos($type, 'bus') !== false ? 'bus' : 'flight';

//        $type = $this->pdf->FindSingleNode("//text()[contains(normalize-space(.), '{$itsegment['FlightNumber']}')]/following::text()[starts-with(normalize-space(.), 'Operated by')][1]");
//        if( false !== stripos($type, 'Train'))
//            return null;

        foreach ($itsegment as $key => $field) {
            if (empty($field)) {
                return false;
            }
        }

        return $itsegment;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($string)
    {
        if (preg_match('/^[^\d\s]{2,}\s+(\d{1,2})\s+([^\d\s]{3,})\s+(\d{4})(.*)$/u', $string, $matches) || preg_match('/(\d{1,2})\s+(\d{1,2})\s*\D+\s*(\d{2,4})/u', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];

            if (isset($matches[4]) && !empty($matches[4])) {
                $time = $this->re("/(\d+:\d+)$/", $matches[4]);
            }

            // if ($day && $month && $year) {
            if (preg_match('/^\s*\d{1,2}\s*$/', $month)) {
                return $day . '.' . $month . '.' . $year . (isset($time) ? ', ' . $time : '');
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year . (isset($time) ? ', ' . $time : '');
            // }
        }

        return false;
    }

    private function re($re, $str = null, $c = 1)
    {
        if (is_int($re) && $str === null) {
            if (isset($this->lastre[$re])) {
                return $this->lastre[$re];
            } else {
                return null;
            }
        }
        preg_match($re, $str, $m);
        $this->lastre = $m;

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function tablePdf(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetEmailBody($html);
        $html = '';

        $pages = $this->pdf->XPath->query("//div[starts-with(@id,'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $cols = [];
            $grid = [];

            foreach ($nodes as $node) {
                $text = $this->pdf->FindSingleNode(".", $node);
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $cols[round($left / 10)] = round($left / 10);
                $grid[$top][round($left / 10)] = $text;
            }

            ksort($cols);

            // group rows by -8px;
            foreach ($grid as $row=>$c) {
                for ($i = $row - 8; $i < $row; $i++) {
                    if (isset($grid[$i])) {
                        foreach ($grid[$row] as $k=>$v) {
                            $grid[$i][$k] = $v;
                        }
                        unset($grid[$row]);

                        break;
                    }
                }
            }

            ksort($grid);

            $html .= "<table border='1'>";

            foreach ($grid as $row=>$c) {
                $html .= "<tr>";

                foreach ($cols as $col) {
                    $html .= "<td>" . ($c[$col] ?? "&nbsp;") . "</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</table>";
        }
        $NBSP = chr(194) . chr(160);
        //print str_replace($NBSP, ' ', html_entity_decode($html));
        $this->pdf->SetEmailBody(str_replace($NBSP, ' ', html_entity_decode($html)));

        return true;
    }

    private function cell($node, $x = 0, $y = 0, $q = "")
    {
        $n = count($this->pdf->FindNodes("./preceding-sibling::td", $node)) + 1;

        if ($y > 0) {
            return $this->pdf->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[" . abs($y) . "]/td[" . ($n + $x) . "]" . $q, $node);
        } elseif ($y < 0) {
            return $this->pdf->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[" . abs($y) . "]/td[" . ($n + $x) . "]" . $q, $node);
        } else {
            return $this->pdf->FindSingleNode("./ancestor::tr[1]/td[" . ($n + $x) . "]" . $q, $node);
        }
    }
}
