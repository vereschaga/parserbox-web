<?php

namespace AwardWallet\Engine\norwegian\Email;

// TODO: it-4550800.eml, it-4439878.eml - not all segments in pdf
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelDocumentPdf2016En extends \TAccountChecker
{
    public const pdfNamePattern = '(?:(?:Boarding pass and travel document for|Boarding pass for|Travel document for|Reisedokument for|Rejsedokumenter for|Rejsekvittering|Document de voyage pour|Informacion de la reserva de|Resehandlingar|Tarjeta de embarque de|Carta d\'imbarco).+?|.*?Flight info|Matka-asiakirjat.+?|Documento di viaggio per.+?)\.pdf';

    public $mailFiles = "norwegian/it-2834179.eml,norwegian/it-27411815.eml, norwegian/it-2859795.eml, norwegian/it-2982593.eml, norwegian/it-3688531.eml, norwegian/it-3996490.eml, norwegian/it-4.eml, norwegian/it-4399894.eml, norwegian/it-4404804.eml, norwegian/it-4439878.eml, norwegian/it-4550800.eml, norwegian/it-4552532.eml, norwegian/it-5280458.eml";

    protected $result = [];
    protected $seats = [];
    protected $tickets = [];
    protected $travellers = [];

    protected $detects = [
        // it-4399894.eml, it-4404804.eml, it-4439878.eml, it-5280458.eml
        'en' => ['Thank you for flying Norwegian', 'Thank you for booking with Norwegian'],
        // it-3688531.eml, it-3996490.eml
        'no' => ['Takk for at du flyr med Norwegian', 'Takk for at du reiser med Norwegian'],
        // it-4.eml, it-4550800.eml
        'da' => ['Tak, fordi du flyver Norwegian', 'Tak, fordi du rejser med Norwegian'],
        // it-4552532.eml
        "it" => ["Grazie per avere prenotato con Norwegian", "Grazie per aver scelto di viaggiare con Norwegian"],
        // it-2834179.eml
        "sv" => ["Tack för att du bokat med Norwegian", "Tack för att du reser med Norwegian"],
        // it-27411815.eml
        'fr' => ["Merci d'avoir choisi Norwegian"],
        // it-2859795.eml
        'es' => ['Gracias por volar con Norwegian', 'Gracias por viajar con Norwegian'],
        // it-2982593.eml
        'fi' => ['Kiitos, kun valitsit Norwegianin', 'Kiitos, että lennät Norwegianilla'],
        'pt' => ['Documentos da viagem Ref.'],
        'pl' => ['Dokumenty podrózy,'],
    ];

    protected $lang = '';
    protected $pdfText = '';

    protected static $dictionary = [
        'en' => [
            // Html
            "DIN BOOKINGREFERANSE ER:" => ["YOUR BOOKING REFERENCE IS:", "YOUR BOOKING REFERENCE IS :", "Booking reference:"],
            "Passasjerer"              => "Passengers",
            "Flyinfo"                  => ["Flight info", "Flugdetails"],
            "Totalbeløp er"            => "Total amount is",
            'Important information'    => ['Informationen', 'Important information'],
        ],
        'no' => [
            'Important information' => 'Viktig Informasjon',
            'Flight'                => 'Flyvning',
            //			'Terminal' => '',
            'Booking reference' => ['Reisereferanse'],
            'Passenger'         => 'Reisende',
            'Seat'              => 'Sete',
            'Document number'   => 'Dokumentnummer',
            'Total'             => 'Total',
            'Total Amount'      => 'Totalbeløp',
            // Html
            "DIN BOOKINGREFERANSE ER:" => ["DIN BOOKINGREFERANSE ER:", "DIN BOOKINGREFERANSE ER :", "Bestillingsreferanse:"],
        ],
        'da' => [
            'Important information' => 'Vigtig information',
            'Flight'                => 'Flyvning',
            //			'Terminal' => '',
            'Booking reference' => 'Bookingreference',
            'Passenger'         => 'Passagerer',
            'Seat'              => 'Plads',
            'Document number'   => 'Dokumentnummer',
            'Total'             => 'I alt',
            'Total Amount'      => 'Beløb i alt',
            // Html
            "DIN BOOKINGREFERANSE ER:" => ["DIN BOOKINGREFERENCE ER:", "Bookingreference:"],
            "Passasjerer"              => "Passagerer",
            //"Flyinfo" => "Flyinfo",
            "Totalbeløp er" => "Beløb i alt",
        ],
        'it' => [
            'Important information' => 'Informazioni importanti',
            'Flight'                => 'Volo',
            'Terminal'              => 'Terminale',
            'Booking reference'     => 'Numero della prenotazione',
            'Passenger'             => 'Passeggero',
            'Seat'                  => 'Posto a sedere',
            'Document number'       => 'Numero documento',
            'Total'                 => 'Totale',
            'Total Amount'          => 'Importo totale',
            // Html
            "DIN BOOKINGREFERANSE ER:" => ["IL TUO CODICE DI PRENOTAZIONE È:", "IL TUO CODICE DI PRENOTAZIONE È :"],
            "Passasjerer"              => "Passeggeri",
            "Flyinfo"                  => ["Info volo", "Informazioni sul volo"],
            "Totalbeløp er"            => "L'importo totale è",
        ],
        'fr' => [
            'Important information' => 'Informations importantes',
            'Flight'                => 'Vol',
            //			'Terminal' => '',
            'Booking reference' => 'Référence de réservation',
            'Passenger'         => 'Passager',
            'Seat'              => 'Seat',
            'Document number'   => 'Document number',
            'Total'             => 'Total',
            'Total Amount'      => 'Montant total',
            // Html
            // it-27411815.eml
            "DIN BOOKINGREFERANSE ER:" => "SERVATION EST :",
            "Passasjerer"              => "Passagers",
            "Totalbeløp er"            => "Le montant total est de",
            "Flyinfo"                  => ["Informations sur le vol", "Informations de vol"],
        ],
        'es' => [
            'Important information' => 'Información importante',
            'Flight'                => 'Vuelo',
            //			'Terminal' => '',
            'Booking reference' => 'Número de reserva',
            'Passenger'         => 'Pasajero',
            'Seat'              => 'Asiento',
            'Document number'   => 'Número de documento',
            'Total'             => 'Total',
            'Total Amount'      => ['Montant total', 'Precio total'],
            // Html
            "DIN BOOKINGREFERANSE ER:" => ["TU NÚMERO DE RESERVA ES:"],
            "Passasjerer"              => "Pasajeros",
            "Flyinfo"                  => ["Información de vuelos", 'Información del vuelo'],
            "Totalbeløp er"            => "El precio total es",
        ],
        'sv' => [
            'Important information' => 'Viktig information',
            'Flight'                => 'Flygresa',
            //			'Terminal' => '',
            'Booking reference' => 'Bokningsreferens',
            'Passenger'         => 'Passagerare',
            'Seat'              => 'Plats',
            'Document number'   => 'Dokumentnummer',
            'Total'             => 'Totalt',
            'Total Amount'      => 'Totalbelopp',
            // Html
            "DIN BOOKINGREFERANSE ER:" => ["DIN BOKNINGSREFERENS ÄR:"],
            "Passasjerer"              => "Passagerare",
            "Flyinfo"                  => "Flyginformation",
            "Totalbeløp er"            => "Totala beloppet är",
        ],
        'fi' => [
            'Important information' => ['Important information', 'Tärkeää tietoa'],
            'Flight'                => 'Lento',
            //			'Terminal' => '',
            'Booking reference' => 'Varausnumero',
            'Passenger'         => 'Matkustaja',
            'Seat'              => 'Istuinpaikka',
            'Document number'   => 'Dokumenttinumero',
            'Total'             => 'Summa',
            'Total Amount'      => 'Kokonaissumma',
            // Html
            "DIN BOOKINGREFERANSE ER:" => ["SINUN VARAUSNUMEROSI ON:"],
            "Passasjerer"              => "Matkustaja",
            "Flyinfo"                  => ["Lennot", "Lennon tiedot"],
            "Totalbeløp er"            => "Kokonaissumma",
        ],
        'pl' => [
            // Html
            "DIN BOOKINGREFERANSE ER:" => ["TWÓJ NUMER REZERWACJI TO:"],
            "Passasjerer"              => "Pasazerowie",
            "Flyinfo"                  => "Informacje o locie",
            "Totalbeløp er"            => "Calkowita suma to",
        ],
    ];
    private $segmentPdf = [];

    public function parseHtml(Email $email, $segmentBody): void
    {
        $f = $email->add()->flight();
        $travellers = array_values(array_unique(array_filter($this->http->FindNodes("//td[{$this->eq($this->t("Passasjerer"))}]/following::td[1]//text()"))));

        if (count($travellers) === 0) {
            $travellers = array_values(array_unique(array_filter($this->http->FindNodes("//td[{$this->eq($this->t("Passasjerer"))}]/ancestor::table[1]/descendant::text()[string-length()>2][not({$this->contains($this->t("Passasjerer"))})]"))));
        }

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t("DIN BOOKINGREFERANSE ER:"))}]/following::text()[string-length(normalize-space(.))>1][1]"))
            ->travellers($travellers);

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t("Totalbeløp er"))}]/following::text()[normalize-space(.)!=''][1]"));

        if ($tot['Total'] === null) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t("Totalbeløp er"))}]"));
        }

        if ($tot['Total'] !== null) {
            $f->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        foreach ($segmentBody as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[string-length()>1][1]", $root)));

            $s = $f->addSegment();
            $s->airline()
                ->number($this->http->FindSingleNode("./td[string-length()>1][1]", $root, true, "/(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)/"))
                ->name($this->http->FindSingleNode("./td[string-length()>1][1]", $root, true, "/([A-Z][A-Z\d]|[A-Z\d][A-Z])\d+/"));

            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("(./td[string-length()>1][2]//text()[normalize-space(.)!=''])[1]", $root, true, "#\d+:\d+\s*(.+)#"))
                ->date(strtotime($this->http->FindSingleNode("(./td[string-length()>1][2]//text()[normalize-space(.)!=''])[1]", $root, true, "#\d+:\d+#"), $date));

            $arrDate = strtotime($this->http->FindSingleNode("(./td[string-length()>1][2]//text()[normalize-space(.)!=''])[2]", $root, true, "#\d+:\d+#"), $date);

            if ($arrDate < $s->getDepDate()) {
                $arrDate = strtotime("+1 day", $arrDate);
            }
            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("(./td[string-length()>1][2]//text()[normalize-space(.)!=''])[2]", $root, true, "#\d+:\d+\s*(.+)#"))
                ->date($arrDate);

            $cabin = $this->http->FindSingleNode("./td[string-length()>1][3]/descendant::text()[normalize-space(.)][1]", $root);

            if (!empty($cabin)) {
                $s->extra()->cabin($cabin);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang($parser)) {
            $this->logger->alert("Can't determine a language");
        }

        $xpath = "//td[{$this->eq($this->t("Flyinfo"))}]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':') or contains(normalize-space(), '.')][./td[4]][contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dddd')]";
        $segmentBody = $this->http->XPath->query($xpath);

        if ($segmentBody->length == 0) {
            $xpath = "//text()[{$this->eq($this->t('Flyinfo'))}]/following::table[1]//tr[./td[4]]";
            $segmentBody = $this->http->XPath->query($xpath);
        }

        $this->pdfText = '';
        $pdfs = $parser->searchAttachmentByName(self::pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $this->pdfText .= \PDF::convertToText($parser->getAttachmentBody($pdf));
            }
        }

        if (!empty($this->pdfText)) {
            //$this->logger->debug($this->pdfText);
            $segments = array_merge(
                $this->findCutSectionAll($this->pdfText, 'Boarding Pass', (array) $this->t('Important information')),
                $this->findCutSectionAll($this->pdfText, 'Travel Document', (array) $this->t('Important information'))
            );
            $this->parsePdf($parser, $email, $segments);
        }

        $this->logger->debug("Segments Pdf: " . count($this->segmentPdf));
        $this->logger->debug("Segments Body: {$segmentBody->length}");

        if (
            // it-5280458.eml
            $this->http->XPath->query("//text()[contains(.,'norwegian.')]")->length != 0
            // it-4439878.eml
            && $segmentBody->length != count($this->segmentPdf)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flyinfo'))}]")->length > 0
        ) {
            $this->logger->notice('Go to a parser Html...');

            foreach ($email->getItineraries() as $i) {
                $email->removeItinerary($i);
            }
            $this->parseHtml($email, $segmentBody);
        }

        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'norwegian.com') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->assignLang($parser);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'norwegian.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function parsePdf(PlancakeEmailParser $parser, Email $email, $array): void
    {
        $f = $email->add()->flight();
        $f->general()->noConfirmation();

        $this->logger->error(count($array));

        foreach ($array as $text) {
            $this->parseSegment($f, $text);
        }

        foreach ($f->getSegments() as $s) {
            if (isset($this->seats[$s->getConfirmation() . $s->getAirlineName() . $s->getFlightNumber()])) {
                $key = $s->getConfirmation() . $s->getAirlineName() . $s->getFlightNumber();

                foreach ($this->seats[$key] as $seat => $traveller) {
                    if (preg_match("/^(\d+[A-Z])$/", $seat)) {
                        $s->extra()
                            ->seat($seat, false, false, array_shift($traveller));
                    }
                }
            }
        }

        $f->general()->travellers(array_unique($this->travellers));

        if (count($this->tickets) > 0) {
            foreach ($this->tickets as $number => $traveller) {
                $f->addTicketNumber($number, false, $traveller[0]);
            }
        }

        if (count($email->getItineraries()) > 0) {
            $pdfs = $parser->searchAttachmentByName(".*\.pdf");

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (preg_match("#" . $this->t("Total") . "[\s]+\(([A-Z]{3})\)#", $text, $currency)
                    && preg_match("#" . $this->opt($this->t("Total Amount")) . "\s+.+?\s+(\d[\d.]+)\s*#", $text, $total)) {
                    $f->price()->total($total[1]);
                    $f->price()->currency($currency[1]);
                }
            }
        }
    }

    protected function parseSegment(Flight $f, $text): void
    {
        $ticket = '';
        $traveller = '';

        if (preg_match('/' . $this->t('Document number') . '\s+(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,2})/', $text, $m)) {
            $ticket = $m[1];
        }
        $re = '/' . $this->opt($this->t('Booking reference')) . '\s+([A-Z\d]{5,6})\s+.*\s*' . $this->t('Passenger') . '\s+(.+?)\n/s';

        if (preg_match($re, $text, $matches)) {
            $conf = $matches[1];

            $this->travellers[] = $traveller = $matches[2];

            if (!empty($ticket)) {
                $this->tickets[$ticket][] = $traveller;
            }
        }

        if (isset($conf) && preg_match("/{$this->t('Flight')}\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s*-\s*(.+?)\n/", $text, $matches)) {
            $unique = $conf . $matches[1] . $matches[2];

            if (preg_match('/' . $this->t('Seat') . '\s+(\d{1,3}[A-Z])/', $text, $m)) {
                $this->seats[$unique][] = $m[1];
                $this->seats[$unique][$m[1]][] = $traveller;
            }

            if (in_array($unique, $this->segmentPdf)) {
                return;
            }
            $this->segmentPdf[] = $unique;
            $s = $f->addSegment();
            $s->setConfirmation($conf);
            $s->airline()->name($matches[1]);
            $s->airline()->number($matches[2]);

            if ($this->lang == 'fi') {
                $date = str_replace(' ', '.', $matches[3]);
            } else {
                $date = $this->normalizeDate($matches[3]);
            }
        }

        if (!isset($s)) {
            return;
        }

        if (!empty($date) && (preg_match_all("/(\d+:\d+)\s*(?:\(\s*\+(\d+)[^)]+\)\s*)?(.+?)\s+\(([A-Z]{3})\)\s+(?:{$this->t('Terminal')} (\w+))?/",
                $text, $matches, PREG_SET_ORDER))
        || preg_match_all("/(\d+\.\d+)\s*(?:\(\s*\+(\d+)[^)]+\)\s*)?(.+?)\s+\(([A-Z]{3})\)\s+(?:{$this->t('Terminal')} (\w+))?/",
                $text, $matches, PREG_SET_ORDER)
        ) {
            $s->departure()->date(strtotime($date . ', ' . $matches[0][1]));

            if (!empty($matches[0][2])) {
                $matches[0][2] = str_replace('.', ':', $matches[0][2]);
                $s->departure()->date(strtotime("+{$matches[0][2]} day", $s->getDepDate()));
            }
            $s->departure()->name($matches[0][3]);
            $s->departure()->code($matches[0][4]);

            if (isset($matches[0][5])) {
                $s->departure()->terminal($matches[0][5]);
            }

            if (!empty($matches[1][1])) {
                $s->arrival()->date(strtotime($date . ', ' . $matches[1][1]));
            }

            if (!empty($matches[1][2])) {
                $s->arrival()->date(strtotime("+{$matches[1][2]}  day", $s->getArrDate()));
            }

            if (!empty($matches[1][3])) {
                $s->arrival()->name($matches[1][3]);
            }

            if (!empty($matches[1][4])) {
                $s->arrival()->code($matches[1][4]);
            }

            if (isset($matches[1][5])) {
                $s->arrival()->terminal($matches[1][5]);
            }
        }
    }

    //========================================
    // Auxiliary methods
    //========================================

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * <b>LEFT</b> <i>cut text2</i> <b>RIGHT2</b>.
     */
    protected function findCutSectionAll($input, $searchStart, $searchFinish): array
    {
        $array = [];

        while (empty($input) !== true) {
            $right = mb_strstr($input, $searchStart);

            foreach ($searchFinish as $value) {
                $left = mb_strstr($right, $value, true);

                if (!empty($left)) {
                    $input = mb_strstr($right, $value);
                    $array[] = mb_substr($left, mb_strlen($searchStart));

                    break;
                }
            }

            if (empty($left)) {
                $input = false;
            }
        }

        return $array;
    }

    private function assignLang(PlancakeEmailParser $parser): bool
    {
        // Html
        foreach ($this->detects as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }
        // PDF
        $pdfs = $parser->searchAttachmentByName(self::pdfNamePattern);

        if (count($pdfs) > 0) {
            $this->pdfText = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

            foreach ($this->detects as $lang => $lines) {
                foreach ($lines as $line) {
                    if (stripos($this->pdfText, $line) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate($str)
    {
        $in = [
            // DY4323 - 27 Aug 2016
            "#^\w{2}\d+\s*-\s*(\d+\s+\w+\s+\d{4})$#",
            // DN6063-2020 feb 21
            "#^\w{2}\d+\s*-\s*(\d{4})\s+(\w+\s+\d+)$#",
            // 17 9 2015
            "#^(\d+)\s+(\d+)\s+(\d{4})$#",
            // 2015 aug 13
            "#^(\d{4})\s+(\w+)\s+(\d+)$#",
        ];
        $out = [
            "$1",
            "$2, $1",
            "$2/$1/$3",
            "$3 $2 $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        //$node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[.\d,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[.\d,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>-*?)(?<t>\d[.\d,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function opt($field): string
    {
        if (!is_array($field)) {
            $field = (array) $this->t($field);
        }

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
