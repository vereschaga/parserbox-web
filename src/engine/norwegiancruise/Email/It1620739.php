<?php

namespace AwardWallet\Engine\norwegiancruise\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class It1620739 extends \TAccountChecker
{
    public $mailFiles = "norwegiancruise/it-133843465.eml, norwegiancruise/it-135403581.eml, norwegiancruise/it-137039497.eml, norwegiancruise/it-137324189.eml, norwegiancruise/it-151342441.eml, norwegiancruise/it-151342959.eml, norwegiancruise/it-1620739.eml, norwegiancruise/it-2460968.eml, norwegiancruise/it-255462848.eml, norwegiancruise/it-255961233.eml, norwegiancruise/it-35923529.eml, norwegiancruise/it-476814713.eml, norwegiancruise/it-826757859.eml";

    public $lang = '';
    public $year = '';

    private $subjects = [
        'es' => ['Recibo para el pasajero - confirmación para el número de reserva'],
        'en' => ['Cruise Confirmation for Reservation #', 'Cancellation Confirmation for Reservation #'],
        'de' => ['Paymentreminder // Reisebestätigung/Rechnung für Reservierung'],
        'pt' => ['Confirmação de reserva #'],
        'it' => ['Prenotazione N°:'],
    ];

    private $pdfPattern = '.*confirmation.*pdf';
    private $anchor;

    private static $dictionary = [
        'es' => [
            'ITINERARY'            => ['ITINERARIO'],
            'DISEMBARKATION:'      => ['DESEMBARQUE:'],
            'RESERVATION'          => 'RESERVA',
            'GUESTS'               => 'PASAJEROS',
            'BOOKING COMPONENTS'   => 'COMPONENTES DE LA RESERVA',
            'travellersEnd'        => ['COMPONENTES DE LA RESERVA', 'PRECIO DE'],
            'SHIP'                 => 'BARCO',
            'SAILING'              => 'SALIDA',
            'CATEGORY / STATEROOM' => 'CATEGORIA / CAMAROTE',
            'CONFIRMATION'         => 'CONFIRMACION',
            'Gross Total'          => 'Total Bruto',
            'Guest Fare'           => 'Precio del crucero',
            'Gov Tax'              => 'Tasas e impuestos',
            'BOOKING DATE'         => 'FECHA DE LA RESERVA',
            'itineraryEnd'         => 'Travel Visa Requirements',
            'AT SEA'               => 'ALTA MAR',
            // 'SHIP DEPARTS'         => '',
            // 'SHIP ARRIVES AT'      => '',
        ],
        'de' => [
            'ITINERARY'            => ['Route'],
            'DISEMBARKATION:'      => ['Reiseverlauf'],
            'RESERVATION'          => 'Reservierungs Nr',
            'GUESTS'               => ['Passagiername/n', 'Passagiername(n)'],
            'BOOKING COMPONENTS'   => 'Preisübersicht',
            'travellersEnd'        => 'Preisübersicht',
            'SHIP'                 => 'Schiff',
            'SAILING'              => 'Daten',
            'CATEGORY / STATEROOM' => 'Kabinennummer',
            //            'CONFIRMATION'         => 'CONFIRMACION',
            'Gross Total'          => ['Kreuzfahrt und', 'Gesamt   '],
            'AMOUNT'               => 'Betrag',
            'Guest Fare'           => 'Kreuzfahrtpreis',
            'Gov Tax'              => 'Steuern und Gebühren',
            'BOOKING DATE'         => 'Buchungsdatum',
            'itineraryEnd'         => 'Bitte beachten Sie:',
            'AT SEA'               => 'AT SEA',
            // 'SHIP DEPARTS'         => '',
            // 'SHIP ARRIVES AT'      => '',
        ],
        'pt' => [
            'ITINERARY'            => ['ITINERÁRIO', 'Itinerário'],
            'DISEMBARKATION:'      => ['DESEMBARQUE:'],
            'RESERVATION'          => ['ORIGEM DA RESERVA', 'RESERVA'],
            'GUESTS'               => ['HÓSPEDES'],
            'BOOKING COMPONENTS'   => 'Valores por pessoa',
            'travellersEnd'        => 'Valores por pessoa',
            'SHIP'                 => 'NAVIO',
            'SAILING'              => 'SAIDA',
            'CATEGORY / STATEROOM' => 'CATEGORIA / CABINE',
            //            'CONFIRMATION'         => 'CONFIRMACION',
            'Gross Total'          => 'Valot total bruto',
            'AMOUNT'               => 'VALOR TOTAL',
            'Guest Fare'           => 'Tarifa',
            'Gov Tax'              => 'Taxas portuárias e governam',
            'BOOKING DATE'         => 'DATA DA RESERVA',
            'itineraryEnd'         => '* A Norwegian tem',
            'AT SEA'               => 'NAVEGAÇÃO',
            'SHIP DEPARTS'         => 'O NAVIO PARTE DE',
            'SHIP ARRIVES AT'      => 'O NAVIO CHEGA EM',
        ],
        'it' => [
            'ITINERARY'            => ['Itinerario'],
            // 'DISEMBARKATION:'      => ['DESEMBARQUE:'],
            'RESERVATION'          => ['Prenotazione N°'],
            'GUESTS'               => ['Nome(i) del/dei passeggero(i)'],
            'BOOKING COMPONENTS'   => 'Quadro prezzi',
            'travellersEnd'        => ['Quadro prezzi', 'Avviso importante'],
            'SHIP'                 => 'Nave',
            'SAILING'              => 'Periodo',
            'CATEGORY / STATEROOM' => 'Kabinennummer',
            //            'CONFIRMATION'         => 'CONFIRMACION',
            'Gross Total'          => 'Totale e servizi extra',
            // 'AMOUNT'               => 'VALOR TOTAL',
            'Guest Fare'                => 'Prezzo crociera',
            'Gov Tax'                   => 'Tasse e imposte',
            'BOOKING DATE'              => 'Data di prenotazione',
            'itineraryEnd'              => 'Requisiti per la sua crociera',
            'AT SEA'                    => 'AT SEA',
            'SHIP DEPARTS'              => 'SHIP DEPARTS',
            'SHIP ARRIVES AT'           => 'SHIP ARRIVES AT',
            'CANCELLATION INVOICE'      => 'CONFERMA DI CANCELLAZIONE',
        ],
        'en' => [
            'ITINERARY'            => ['ITINERARY'],
            'RESERVATION'          => ['RESERVATION', 'RESERVATION / INVOICE NO.'],
            'DISEMBARKATION:'      => ['DISEMBARKATION:', 'DEBARK PORT:'],
            'travellersEnd'        => ['Important Notice:', 'BOOKING COMPONENTS', 'FARES AS AGREED', "\n\n\n", "Guest Name(s)"],
            'itineraryEnd'         => ['TRAVEL AGENT/CONTACT', 'Requirements For Your Cruise:', 'AIR ITINERARY', ''], // '' is not error
            'Gross Total'          => ['Gross Total', 'Invoice Total'],
            'Guest Fare'           => ['Guest Fare', 'Admin Fee'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = false;
        $detectLanguage = false;

        // Detect Provider (HTML)
        if ($this->http->XPath->query("//node()[{$this->contains([
            'Gracias por elegir Norwegian Cruise Line', // es
            'Thank you for choosing Norwegian Cruise Line', // en
            'Sinceramente, Norwegian Cruise Line', // es
            'Sincerely, Norwegian Cruise Line', // en
            'für die Buchung bei Norwegian Cruise Line', // de
            'Il Suo team di Norwegian Cruise Line', // it
            'Sua richiesta e l\'interesse per Norwegian Cruise Line', // it
            '@ncl.com', 'www.ncl.com',
        ])}]")->length > 0) {
            $detectProvider = true;
        }

        // Detect Language (HTML)
        $detectLanguage = $this->http->XPath->query("//node()[{$this->contains([
            'Adjunto encontrará una', // es
            'Attached you will find', // en
            'Im Anhang an diese E-Mail', // de
            'Por favor, não responda este email', // pt
            'In allegato trova la Sua conferma', // it
        ])}]")->length > 0;

        if ($detectProvider && $detectLanguage) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            $detectProvider = false;
            $detectLanguage = false;

            // Detect Provider (PDF)
            if (!$detectProvider) {
                $detectProvider = stripos($textPdf, 'www.ncl.com') !== false
                    || stripos($textPdf, 'por favor contacte a Norwegian Cruise') !== false // es
                    || stripos($textPdf, 'which trades as Norwegian Cruise Line') !== false // es
                    || stripos($textPdf, 'NORWEGIAN DAWN') !== false // es
                    || stripos($textPdf, 'please contact Norwegian Cruise') !== false; // en
            }

            // Detect Language (PDF)
            if (!$detectLanguage) {
                $detectLanguage = $this->assignLang($textPdf);
            }

//            $this->year = $this->re("/{$this->opt($this->t('SAILING'))}\:\s*\d+\-\w+\-(\d{4})/u", $textPdf);

            if ($detectProvider && $detectLanguage) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Norwegian Cruise Line') !== false
            || stripos($from, '@ncl.com') !== false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);

                $flightText = $this->re("/^([ ]*{$this->opt($this->t('AIR ITINERARY'))}\s*{$this->opt($this->t('PNR Record Locator:'))}.+)\n\n*[ ]*{$this->opt($this->t('For details on our cancellation policy please go to'))}/msu", $textPdf);

                if (!empty($flightText)) {
                    $this->parseFlightPdf($email, $flightText);
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function parseFlightPdf(Email $email, $text): void
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/{$this->opt($this->t('PNR Record Locator:'))}\s*([A-Z\d]{6})\s+/", $text));

        if (preg_match_all("/^([ ]*(?:[A-Z\d]{6})?\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d{2,4}.+\([A-Z]{3}\))$/mu", $text, $m)) {
            foreach ($m[1] as $seg) {
                if (preg_match("/^[ ]*(?<segConf>[A-Z\d]{6})?\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s+(?<fNumber>\d{2,4})\s+(?<cabin>\w+)\s*(?<depDate>\d+\w+\d{2}\s*\d+\:\d+)\s+(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\s+(?<arrDate>\d+\w+\d{2}\s+\d+\:\d+)\s+(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)$/mu", $seg, $match)) {
                    $s = $f->addSegment();

                    if (isset($match['segConf']) && !empty($match['segConf'])) {
                        $s->setConfirmation($match['segConf']);
                    }

                    $s->airline()
                        ->name($match['aName'])
                        ->number($match['fNumber']);

                    $s->departure()
                        ->name($match['depName'])
                        ->code($match['depCode'])
                        ->date($this->normalizeDate($match['depDate'], false));

                    $s->arrival()
                        ->name($match['arrName'])
                        ->code($match['arrCode'])
                        ->date($this->normalizeDate($match['arrDate'], false));
                }
            }
        }
    }

    public function parsePdf(Email $email, $text): void
    {
        $taPos = strpos($text, 'TRAVEL AGENT COPY');
        $gPos = strpos($text, 'GUEST COPY');

        if ($taPos !== false && $taPos < 3000 && $gPos !== false && $gPos > 6000) {
            $text = $this->findСutSection($text, 0, $this->t('GUEST COPY'));
        }
        $c = $email->add()->cruise();

        $grossTotalName = null;
        $pos = $this->strposArray($text, $this->t('Gross Total'), $grossTotalName);

        if ($pos !== false) {
            $header = substr($text, 0, $pos + 100);
        } else {
            $header = $text;
        }
        // General
        $c->general()
            ->confirmation($this->re("/(?:\n *|[ ]{2}){$this->opt($this->t('RESERVATION'))} *: *(\w+)/", $header))
        ;
        $date = strtotime($this->re("/{$this->opt($this->t('BOOKING DATE'))} *: *(.+?\d{4})/", $header));

        if (!empty($date)) {
            $this->anchor = $date;
            $c->general()
                ->date($date);
        }

        if ($this->strposArray($header, $this->t("CANCELLATION INVOICE")) !== false) {
            $c->general()
                ->cancelled()
                ->status('Cancelled');
        }
        $this->anchor = $this->normalizeDate($this->re("/{$this->opt($this->t('SAILING'))}\:\s*(\d+\-\w+\-\d{4})/u", $text), false);

        $travellers = [];
        $travellerText = $this->re("/{$this->opt($this->t('GUESTS'))} .*\n([\s\S]+?)\s+{$this->opt($this->t('travellersEnd'))}/", $header);
        $travellerText = preg_replace("/((?:.*\n){2,})\n\s*{$this->opt($this->t('GUESTS'))}[\s\S]*/", '$1', $travellerText);

        if (empty($travellerText) && $c->getCancelled()) {
            $travellerText = $this->re("/{$this->opt($this->t('GUESTS'))} .*\n([\s\S]+?)\s+(?:{$this->opt($this->t('Guest Name(s)'))}|{$this->opt($this->t('PAYMENTS'))}|{$this->opt($this->t('Air tickets'))})/", $header);
        }

        $travellerRows = $this->splitText($travellerText, '/^[ ]{0,10}\d{1,3}[ ]+([[:alpha:]][-.\'[:alpha:] ]*?[[:alpha:]](?: |$).*)/mu', true);

        foreach ($travellerRows as $row) {
            if (preg_match_all('/^[ ]{0,10}([[:alpha:]][-.\'[:alpha:] ]*?[[:alpha:]])(?:[ ]{2}|[ ]*[^\-.\'[:alpha:] ]|$)/mu', $row, $travellerMatches)) {
                // for multiline passenger names
                $travellers[] = implode(' ', $travellerMatches[1]);
            }
        }

        $c->general()
            ->travellers($travellers, true);

        if (preg_match("/\s+ {$this->opt($this->t('CONFIRMATION'))} \s+/", $header)) {
            $c->general()
                ->status('confirmed');
        }

        // Details
        $desc = $this->re("/{$this->opt($this->t('ITINERARY'))}.*?:(.*\n.*)/u", $text);
        $desc = preg_replace(
            ["/^ {5,}.*/um", "/^ {0,5}(\S.*?)( {2,}.*)/m", "/\n *\w+( \w+)*:.*/", '/\s+/'],
            ['', '$1', '', ' '],
            trim($desc));

        $c->details()
            ->description($desc)
            ->ship($this->re("/{$this->opt($this->t('SHIP'))} *: *(.+?)(?: {2,}|\n)/", $header))
            ->roomClass($this->re("/{$this->opt($this->t('CATEGORY / STATEROOM'))} *: *(\w+) *\/ *\d+\s*\n/", $header), true, true)
            ->room($this->re("/{$this->opt($this->t('CATEGORY / STATEROOM'))} *: *\w+ *\/ *(\d+)\s*\n/", $header), true, true)
        ;

        if ($c->getCancelled()) {
            return;
        }
        // Price
        $currency = $this->re("/\s+{$this->opt($this->t('AMOUNT'))} *\(([A-Z]{3})\)/", $header);

        if (empty($currency)) {
            $currency = $this->re("/\n[ ]*{$this->opt($this->t('BOOKING COMPONENTS'))}.* ([A-Z]{3})/", $header);
        }

        if (!empty($currency)) {
            $c->price()
                ->currency($currency)
                ->total(PriceHelper::parse(trim($this->re("/\n *(?:{$this->opt($grossTotalName ?? $this->t('Gross Total'))}|Total {2,}) *(\d[\d., ]*?)\s{2,}/", $header)), $currency))
                ->cost(PriceHelper::parse(trim($this->re("/{$this->opt($this->t('Guest Fare'))} *(\d[\d., ]*?)\s{2,}/", $header)), $currency))
            ;

            if (preg_match("/{$this->opt($this->t('Guest Fare'))}.+\n([\s\S]+?)\n\s*{$this->opt($grossTotalName ?? $this->t('Gross Total'))} {2,}/", $header, $m)) {
                $feesRows = explode("\n", $m[1]);

                foreach ($feesRows as $row) {
                    if (preg_match("/^ *Gesamt {2,}/", $row, $mat)) {
                        continue;
                    }

                    if (preg_match("/^ *(\S.+?) +-(\d[\d,. ]*?)\s{2,}/", $row, $mat)) {
                        $discount = $discount ?? 0.0;
                        $discount += PriceHelper::parse(trim($mat[2]), $currency);
                    } elseif (preg_match("/^ *(\S.+?) +(\d[\d,. ]*?)\s{2,}/", $row, $mat)) {
                        $c->price()->fee($mat[1], PriceHelper::parse(trim($mat[2]), $currency));
                    }
                }

                if (!empty($discount)) {
                    $c->price()
                        ->discount($discount);
                }
            }
        }

        // Segments
        $segments = [];
        $info = $this->findСutSection($text, preg_replace('/(.+)/', '$1' . "\n", $this->t('ITINERARY')), $this->t('itineraryEnd'));

        if (empty($info) || stripos($info, $this->t('Dock')) == false) {
            $info = $this->findСutSection($text, preg_replace('/(.+)/', "\n" . '$1', $this->t('ITINERARY')),
                $this->t('itineraryEnd'));
        }

        if (preg_match_all("/[[:alpha:]]{3}\s+\d+-[[:alpha:]]{3}\s+.*?[A-Z]{2,}.+?$/ums", $info, $m)) {
            $segments = $m[0];
        }

        $patterns['portName'] = '/([A-Z]{2,}\s*(?: \(.+?\) )?.+?)$/mu';

        // step 1/3: detecting start & end segments

        $firstSegments = $lastSegments = [];

        foreach ($segments as $i => $row) {
            $name = $this->re($patterns['portName'], $row);

            if (preg_match("/^{$this->opt($this->t('SHIP DEPARTS'))}[ ]+\S.{2}/", $name)) {
                $firstSegments[] = $i;
            }

            if (preg_match("/^{$this->opt($this->t('SHIP ARRIVES AT'))}[ ]+\S.{2}/", $name)) {
                $lastSegments[] = $i;
            }
        }

        // step 2/3: filtering segments

        if (count($lastSegments) === 1) {
            $segments = array_slice($segments, 0, $lastSegments[0] + 1);
        }

        if (count($firstSegments) === 1) {
            $segments = array_slice($segments, $firstSegments[0]);
        }

        // step 3/3: parsing segments

        foreach ($segments as $i => $row) {
            if (preg_match("/(?:[ ]{2}|.{3},[ ]*){$this->opt($this->t('AT SEA'))}[ ;\d]*$/", $row)) {
                continue;
            }

            $s = $c->addSegment();

            $name = $this->re($patterns['portName'], $row);
            $name = preg_replace("/.*({$this->opt($this->t('SHIP DEPARTS'))}|{$this->opt($this->t('SHIP ARRIVES AT'))}|OVERNIGHT IN|NAVIO PARTE DE|NAVIO CHEGA EM|DISEMBARK SHIP IN)\s+/", '', $name);
            $name = preg_replace("/^(.+?) {3,}.+/", '$1', $name);
            $s->setName($name);

            if (preg_match('/^\s*\w+\s*(.+?\s+\d+:\d+\s*(?:[ap]m|h))\s+\w+\s+(\d+-[[:alpha:]]+\s+\d+:\d+\s*(?:[ap]m|h))/su', $row, $matches)) {
                $dt1 = $this->normalizeDate($matches[1]);
                $dt2 = $this->normalizeDate($matches[2]);
                $s->setAshore($dt1);
                $s->setAboard($dt2);
            } elseif (preg_match('/^\s*\w+\s*(\d+\-\w+)\s+\w+\s+(\d+-[[:alpha:]]+\s+\d+:\d+\s*(?:[ap]m|h))/su', $row, $matches)) {
                $dt1 = $this->normalizeDate($matches[1] . ' 00:00');
                $dt2 = $this->normalizeDate($matches[2]);
                $s->setAshore($dt1);
                $s->setAboard($dt2);
            } elseif (preg_match('/^\s*\w+\s*(.+?\s+\d+:\d+\s*(?:[ap]m|h))\b/su', $row, $matches)) {
                $dt1 = $this->normalizeDate($matches[1]);

                if ($i === 0) {
                    $s->setAboard($dt1);
                } else {
                    $s->setAshore($dt1);
                }
            } elseif (preg_match('/^\s*\w+\W*\w+\W*\w+\s+(\w+\W*\w+\W*\w+\s+)?[A-Z]+/su', $row, $matches)) {
                // Thu    06-Jun        CRUISE GLACIER BAY    1;2
                $c->removeSegment($s);
            }
        }
    }

    private function normalizeDate(?string $str, $correct = true)
    {
//        $this->logger->debug('$date = '.print_r( $str,true));
        $year = date("Y", $this->anchor);
        $in = [
            '/(\d+)-([[:alpha:]]+)\s*(\d+:\d+)\s*([ap]m)?$/u',
            // 15-Jun         7:00 h
            '/(\d+)-([[:alpha:]]+)\s*(\d+:\d+)\s*h/u',
        ];
        $out = [
            "$1 $2 {$year}, $3 $4",
            "$1 $2 {$year}, $3",
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if ($correct === true) {
            $str = EmailDateHelper::parseDateRelative($str, $this->anchor);
        } else {
            $str = strtotime($str);
        }

        return $str;
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['ITINERARY']) || empty($phrases['RESERVATION'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['ITINERARY']) !== false
                && $this->strposArray($text, $phrases['RESERVATION']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray($text, $phrases, &$foundValue = null)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = strpos($text, $phrase);

            if ($result !== false) {
                $foundValue = $phrase;

                return $result;
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

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function splitText($textSource = '', string $pattern, $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if (empty($searchStart)) {
            $left = $input;
        } elseif (is_array($searchStart)) {
            foreach ($searchStart as $ss) {
                $left = mb_strstr($input, $ss);

                if (!empty($left)) {
                    $left = mb_substr($left, mb_strlen($ss));

                    break;
                }
            }
        } else {
            $left = mb_strstr($input, $searchStart);
            $left = mb_substr($left, mb_strlen($searchStart));
        }

        if (is_array($searchFinish)) {
            foreach ($searchFinish as $sf) {
                if (!empty($searchFinish)) {
                    $inputResult = $left;
                } else {
                    $ir = mb_strstr($left, $sf, true);

                    if (!empty($ir)) {
                        $inputResult = $ir;

                        break;
                    }
                }
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return $inputResult;
    }
}
