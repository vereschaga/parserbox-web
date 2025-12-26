<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourFlightItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-759023360-es.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            /* Itinerary */
            'otaConfNumber'             => ['N.º de reserva'],
            // 'Date of Booking' => '',
            'Name'                      => 'Nombre',
            'Class'                     => 'Clase',
            'Ticket No.'                => 'N.º de billete',
            'Airline Booking Reference' => ['Localizador de la reserva', 'Localizador de la', 'Localizador de', 'Localizador'],
            'firstname'                 => 'Nombre',
            'surname'                   => 'Apellidos',
            'passengersEnd'             => ['Información de vuelo'],
            'segmentsStart'             => ['Información de vuelo'],
            'segmentsEnd'               => ['Equipaje permitido', 'Información importante', 'Información sobre equipaje'],
            'Departure'                 => ['Salida'],
            'Arrival'                   => ['Llegada'],
            'Airline'                   => ['Aerolínea'],
            'cabinValues'               => ['Turista'],

            /* Receipt */
            'Price Summary' => ['Desglose del precio'],
            'Amount'        => ['Importe'],
            'priceEnd'      => ['Este recibo se ha generado automáticamente'],
            'Total'         => 'Total',

            /* Html */
            'totalStart' => ['Total:', 'Total：'],
        ],
        'en' => [
            /* Itinerary */
            'otaConfNumber' => ['Booking No.'],
            // 'Date of Booking' => '',
            // 'Name' => '',
            // 'Class' => '',
            // 'Ticket No.'                => '',
            'Airline Booking Reference' => ['Airline Booking Reference', 'Airline Booking', 'Airline'],
            'firstname'                 => ['Given names', 'First name'],
            'surname'                   => ['Surname', 'Last name'],
            'passengersEnd'             => ['Flight Information'],
            'segmentsStart'             => ['Flight Information'],
            'segmentsEnd'               => ['Baggage Allowance', 'Important information', 'Baggage Information'],
            'Departure'                 => ['Departure'],
            'Arrival'                   => ['Arrival'],
            'Airline'                   => ['Airline'],
            'cabinValues'               => ['Economy'],

            /* Receipt */
            'Price Summary' => ['Price Summary'],
            'Amount'        => ['Amount'],
            'priceEnd'      => ['This receipt is automatically generated'],
            'Total'         => 'Total',

            /* Html */
            'totalStart' => ['Total:', 'Total：'],
        ],
        'fr' => [
            /* Itinerary */
            'otaConfNumber' => ['Nº de réservation'],
            // 'Date of Booking' => '',
            'Name'                      => 'Nom',
            'Class'                     => 'Classe',
            'Ticket No.'                => 'Nº de billet',
            'Airline Booking Reference' => ['Numéro du dossier'],
            'firstname'                 => 'Prénoms',
            'surname'                   => 'Nom',
            'passengersEnd'             => ['Informations sur les vols'],
            'segmentsStart'             => ['Informations sur les vols'],
            'segmentsEnd'               => ['Franchise bagage'],
            'Departure'                 => ['Départ'],
            'Arrival'                   => ['Arrivée'],
            'Airline'                   => ['Compagnie aérienne'],
            'cabinValues'               => ['Economy'],

            /* Receipt */
            'Price Summary' => ['Détails du prix'],
            'Amount'        => ['Montant'],
            'priceEnd'      => ['Ce reçu est généré automatiquement'],
            'Total'         => 'Total',

            /* Html */
            'totalStart' => ['Total'],
        ],
        'ja' => [
            /* Itinerary */
            'otaConfNumber' => ['予約番号'],
            // 'Date of Booking' => '',
            'Name'                      => '搭乗者名',
            'Class'                     => 'クラス',
            'Ticket No.'                => '航空券番号',
            'Airline Booking Reference' => ['航空会社予約番号（'],
            'firstname'                 => '姓',
            'surname'                   => '名（下の名前）',
            'passengersEnd'             => ['フライト情報'],
            'segmentsStart'             => ['フライト情報'],
            'segmentsEnd'               => ['手荷物情報'],
            'Departure'                 => ['出発'],
            'Arrival'                   => ['到着'],
            'Airline'                   => ['航空会社'],
            'cabinValues'               => ['エコノミー'],

            /* Receipt */
            'Price Summary' => ['価格明細'],
            'Amount'        => ['金額'],
            'priceEnd'      => ['この領収書は自動的に生成されたものです。'],
            'Total'         => '合計',

            /* Html */
            'totalStart' => ['合計'],
        ],
    ];

    private $otaConfNumbers = [];

    private $patterns = [
        'date'          => '.{4,}?\b\d{4}\b', // November 7, 2024  |  7 de noviembre de 2024
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]trip\.com$/i', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = false;

        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) === true
            || stripos($parser->getSubject(), '[Trip.com]') !== false
            || $this->http->XPath->query('//a[contains(@href,".trip.com/") or contains(@href,"www.trip.com")]')->length > 0
            || $this->http->XPath->query('//text()[(starts-with(normalize-space(),"©") or starts-with(normalize-space(),"Copyright ©")) and contains(normalize-space(),"Trip.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Trip.com")]')->length > 0
        ) {
            $detectProvider = true;
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)
                || !$detectProvider
                && stripos($textPdf, 'Trip.com no asumirá ninguna') === false // es
                && stripos($textPdf, 'Trip.com bears no responsibility') === false // en
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $usingLangs = $pdfsItinerary = $pdfsReceipt = [];
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            // remove page numbers
            $textPdf = preg_replace('/\n[ ]{0,20}\d{1,2}$/m', "\n", $textPdf);

            if ($this->assignLang($textPdf)) {
                $usingLangs[] = $this->lang;
                $pdfsItinerary[] = [
                    'lang' => $this->lang,
                    'text' => $textPdf,
                ];
            } elseif ($this->assignLangReceipt($textPdf)) {
                $this->logger->debug('Found receipt PDF in language: ' . strtoupper($this->lang));

                $pdfsReceipt[] = [
                    'lang' => $this->lang,
                    'text' => $textPdf,
                ];
            }
        }

        /* Step 1/2: Parse Itineraries */

        foreach ($pdfsItinerary as $pdfItinerary) {
            $this->lang = $pdfItinerary['lang'];

            if (preg_match("/^[ ]*({$this->opt($this->t('otaConfNumber'))})[: ]+([-A-Z\d]{4,40})(?: {$this->opt($this->t('Date of Booking'))}|[ ]{2}|$)/m", $pdfItinerary['text'], $m)
                && !in_array($m[2], $this->otaConfNumbers)
            ) {
                $email->ota()->confirmation($m[2], $m[1]);
                $this->otaConfNumbers[] = $m[2];
            }

            $this->parseFlightPdf($email, $pdfItinerary['text']);
        }

        if (count(array_unique($usingLangs)) === 1
            || count(array_unique(array_filter($usingLangs, function ($item) { return $item !== 'en'; }))) === 1
        ) {
            $email->setType('YourFlightItineraryPdf' . ucfirst($usingLangs[0]));
        }

        /* Step 2/2: Parse Receipts */

        $amountValues = $currencyValues = [];

        foreach ($pdfsReceipt as $pdfReceipt) {
            $this->lang = $pdfReceipt['lang'];
            $this->parsePricePdf($pdfReceipt['text'], $amountValues, $currencyValues);
        }

        if (count(array_unique($currencyValues)) === 1) {
            $email->price()->total(array_sum($amountValues))->currency($currencyValues[0]);
        }

        if (empty($email->getPrice())) {
            $this->parsePriceHtml($email);
        }

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

    private function parseFlightPdf(Email $email, string $text): void
    {
        $f = $email->add()->flight();

        $travellers = $tickets = $airlineBookingRefs = [];
        $passengersText = $this->re("/^([ ]*{$this->opt($this->t('Name'))}[ ]+{$this->opt($this->t('Class'))}(?: .+)?\n+[\s\S]+?)\n+[ ]*{$this->opt($this->t('passengersEnd'))}(?:[ ]{2,}.+|[ ]*[,.;!?]+)?$/m", $text);
        $passengersSections = $this->splitText($passengersText, "/^([ ]*{$this->opt($this->t('Name'))}[ ]+{$this->opt($this->t('Class'))}(?: .+)?\n)/m", true);

        foreach ($passengersSections as $psText) {
            if (preg_match("/^([\s\S]+?)\n{2,}[ ]{0,20}(\S.*\S - \S.*\S)\s*$/", $psText, $m)
                && !preg_match("/[ ]{2}/", $m[2])
            ) {
                // remove garbage
                $psText = $m[1];
            }

            // fixing last column
            $psText = preg_replace("/^(.{30,}{$this->patterns['eTicket']}) ([A-Z\d]{5,10})$/m", '$1                          $2', $psText);

            $firstRow = $this->re('/(.+)/', $psText);
            $tablePos = [0];

            if (preg_match("/^([ ]*{$this->opt($this->t('Name'))}[ ]+){$this->opt($this->t('Class'))}/", $firstRow, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^([ ]*{$this->opt($this->t('Name'))}[ ]+{$this->opt($this->t('Class'))}[ ]+){$this->opt($this->t('Ticket No.'))}/", $firstRow, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^([ ]*{$this->opt($this->t('Name'))}[ ]+{$this->opt($this->t('Class'))}(?: .+)?[ ]+){$this->opt($this->t('Airline Booking Reference'))}$/", $firstRow, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            if (count($tablePos) < 3) {
                $this->logger->debug('Found wrong passengers table!');

                continue;
            }

            $passengersRows = $this->splitText($psText, "/\n\n([ ]{0,20}[[:upper:]].+)/u", true);

            foreach ($passengersRows as $pRow) {
                $table = $this->splitCols($pRow, $tablePos);
                $table = array_map('trim', $table);

                $passengerVal = preg_replace('/\s+/', ' ', $table[0]);
                $passengerParts = [];

                if (preg_match("/(?:^|\)\s*)([^)(]{2,}?)\s*\(\s*{$this->opt($this->t('firstname'))}\s*\)/", $passengerVal, $m)) {
                    $passengerParts[] = $m[1];
                }

                if (preg_match("/(?:^|\)\s*)([^)(]{2,}?)\s*\(\s*{$this->opt($this->t('surname'))}\s*\)/", $passengerVal, $m)) {
                    $passengerParts[] = $m[1];
                }

                $passengerName = count($passengerParts) > 0 ? implode(' ', $passengerParts) : $passengerVal;

                if (!preg_match("/^{$this->patterns['travellerName']}$/u", $passengerName)) {
                    $passengerName = null;
                }

                $travellers[] = $passengerName;

                $ticketValues = preg_split("/\s*[,;\n]\s*/", preg_replace('/(\S)(?:[ ]*-\n+)+[ ]*(\S)/', '$1-$2', $table[2]));

                foreach ($ticketValues as $tktVal) {
                    if (preg_match("/^{$this->patterns['eTicket']}$/", $tktVal) && !in_array($tktVal, $tickets)) {
                        $f->issued()->ticket($tktVal, false, $passengerName);
                        $tickets[] = $tktVal;
                    }
                }

                if (count($table) === 3) {
                    $pnrVal = $table[2];
                } elseif (count($table) > 3) {
                    $pnrVal = $table[3];
                } else {
                    $pnrVal = '';
                }

                if (preg_match("/^[A-Z\d]{5,8}$/", $pnrVal) && !in_array($pnrVal, $airlineBookingRefs)) {
                    $f->general()->confirmation($pnrVal);
                    $airlineBookingRefs[] = $pnrVal;
                }
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers(array_unique($travellers), true);
        }

        $segmentsText = $this->re("/^[ ]*{$this->opt($this->t('segmentsStart'))}(?:[ ]{2,}.+|[ ]*[,.;!?]+)?\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('segmentsEnd'))}(?:[ ]{2,}.+|[ ]*[,.;!?]+)?$/m", $text);
        $segments = $this->splitText($segmentsText, "/^([ ]*{$this->opt($this->t('Departure'))}\s)/m", true);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            // 7 de noviembre de 2024, 22:15 Aeropuerto de Barcelona-El Prat, T1
            $pattern1 = "/^(?<date>{$this->patterns['date']})[, ]+(?<time>{$this->patterns['time']})[, ]+(?<airport>.{3,})$/";
            // 22:15, November 7, 2024 Barcelona Airport, T1
            $pattern2 = "/^(?<time>{$this->patterns['time']})[, ]+(?<date>{$this->patterns['date']})[, ]+(?<airport>.{3,})$/";
            // Barcelona Airport, T1
            $pattern3 = "/^(?<name>.{2,}?)(?:[ ]*[,]+[ ]*)+T[- ]*(?<terminal>[A-Z]|\d+)$/";

            $dateDep = $timeDep = $airportDep = null;
            $departureVal = preg_replace('/\s+/', ' ', $this->re("/^[ ]*{$this->opt($this->t('Departure'))}\s+([\s\S]{5,}?)\n+[ ]*{$this->opt($this->t('Arrival'))}\s/", $sText) ?? '');

            if (preg_match($pattern1, $departureVal, $m) || preg_match($pattern2, $departureVal, $m)) {
                $dateDep = strtotime($this->normalizeDate($m['date']));
                $timeDep = $m['time'];
                $airportDep = $m['airport'];
            }

            if ($dateDep && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep));
            }

            if (preg_match($pattern3, $airportDep, $m)) {
                $airportDep = $m['name'];
                $s->departure()->terminal($m['terminal']);
            }

            if ($airportDep) {
                $s->departure()->name($airportDep)->noCode();
            }

            $dateArr = $timeArr = $airportArr = null;
            $arrivalVal = preg_replace('/\s+/', ' ', $this->re("/\n[ ]*{$this->opt($this->t('Arrival'))}\s+([\s\S]{5,}?)\n+[ ]*{$this->opt($this->t('Airline'))}\s/u", $sText) ?? '');

            if (preg_match($pattern1, $arrivalVal, $m) || preg_match($pattern2, $arrivalVal, $m)) {
                $dateArr = strtotime($this->normalizeDate($m['date']));
                $timeArr = $m['time'];
                $airportArr = $m['airport'];
            }

            if ($dateArr && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $dateArr));
            }

            if (preg_match($pattern3, $airportArr, $m)) {
                $airportArr = $m['name'];
                $s->arrival()->terminal($m['terminal']);
            }

            if ($airportArr) {
                $s->arrival()->name($airportArr)->noCode();
            }

            // Korean Air KE6875    |     Korean Air KE6875 / Delta Air Lines DL537 (Code share)
            // Air Europa UX088 Transfer: Madrid | Madrid Barajas Airport T2 | 3hr 15mins Baggage checked through Madrid - Milan
            $airlineVal = preg_replace('/\s+/', ' ', $this->re("/\n[ ]*{$this->opt($this->t('Airline'))}\s+([\s\S]{2,}?)\s*(?:\n {0,10}[[:alpha:]]+|$)/u", $sText) ?? '');
            // $this->logger->debug('$airlineVal = '.print_r( $airlineVal,true));

            if (preg_match("/^(?:[^\/]+ )?(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<number>\d+)(?:\s*\/|$)/", $airlineVal, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            if (preg_match("/^[^\/]*\/(?:[^\/]+ )?(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<number>\d+)(?:\s*\(\D*\))?$/", $airlineVal, $m)) {
                $s->airline()->carrierName($m['name'])->carrierNumber($m['number']);
            }

            $classVal = preg_replace('/\s+/', ' ', $this->re("/\n[ ]*{$this->opt($this->t('Class'))}\s+([\s\S]{2,}?)\s*$/", $sText) ?? '');

            if (preg_match("/^({$this->opt($this->t('cabinValues'))})(?:[ ]*\||\n|$)/i", $classVal, $m)) {
                $s->extra()->cabin($m[1]);
            }
        }

        if (count($airlineBookingRefs) === 0 && count($this->otaConfNumbers) > 0
            && !preg_match("/^[ ]*{$this->opt($this->t('Name'))}[ ]+{$this->opt($this->t('Class'))}\s/m", $text)
        ) {
            $f->general()->noConfirmation();
        }
    }

    private function parsePricePdf(string $text, array &$amountValues, array &$currencyValues): void
    {
        if (count($this->otaConfNumbers) === 0) {
            $this->logger->debug('Parse receipt(pdf) is stopped: otaConfNumbers(pdf) is empty.');

            return;
        }

        $textReceipt = $this->re("/^([ ]*{$this->opt($this->t('otaConfNumber'))}[: ]+{$this->opt($this->otaConfNumbers)}(?:[ ]{2}.+)?\n[\s\S]+?)\n+[ ]*{$this->opt($this->t('priceEnd'))}/mu", $text);
        $textPrice = $this->re("/\n([ ]*{$this->opt($this->t('Price Summary'))}[ ]+{$this->opt($this->t('Amount'))}\n[\s\S]+)$/u", $textReceipt);
        $totalPrice = $this->re("/^[ ]{0,20}{$this->opt($this->t('Total'))}(?: ?\((?:\S ?)+\))?[ ]{2,}(\S.*)$/mu", $textPrice);

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)
        ) {
            // Mex$1,268  |  6,233 MXN  |  1.120,20 €
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $amountValues[] = PriceHelper::parse($matches['amount'], $currencyCode);
            $currencyValues[] = $currency;
        }
    }

    private function parsePriceHtml(Email $email): void
    {
        if (count($this->otaConfNumbers) === 0) {
            $this->logger->debug('Parse price(html) is stopped: otaConfNumbers(pdf) is empty!');

            return;
        }

        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('otaConfNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{4,40}$/');

        if (!$otaConfirmation) {
            $this->logger->debug('Parse price(html) is stopped: otaConfNumber(html) not found!');

            return;
        }

        if (!in_array($otaConfirmation, $this->otaConfNumbers)) {
            $this->logger->debug('Parse price(html) is stopped: otaConfNumbers(pdf+html) not match!');

            return;
        }

        $totalPrice = $this->http->FindSingleNode("//tr[not(.//tr[normalize-space()]) and {$this->starts($this->t('totalStart'))}]", null, true, "/^{$this->opt($this->t('totalStart'))}[:\s]*(.*\d.*)$/u");

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // AUD 3048.8  |  USD 3449.74
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Departure']) || empty($phrases['Arrival']) || empty($phrases['Airline'])) {
                continue;
            }

            if (preg_match("/\n[ ]*{$this->opt($phrases['Departure'])}\s.*\n[ ]*{$this->opt($phrases['Arrival'])}\s.*\n[ ]*{$this->opt($phrases['Airline'])}\s/s", $text)) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function assignLangReceipt(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Price Summary']) || empty($phrases['Amount'])) {
                continue;
            }

            if (preg_match("/\n[ ]*{$this->opt($phrases['Price Summary'])}[ ]+{$this->opt($phrases['Amount'])}\n/", $text)) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re(string $re, ?string $str, $c = 1): ?string
    {
        if (preg_match($re, $str ?? '', $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/\b(\d{1,2})\s+(?:de\s+)?([[:alpha:]]{3,30})\s+(?:de\s+)?(\d{4})$/u', $text, $m)) {
            // 7 de noviembre de 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/\b([[:alpha:]]{3,30})[-,.\s]+(\d{1,2})[,.\s]+(\d{4})$/u', $text, $m)) {
            // November 7, 2024
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
            'MXN' => ['Mex$'],
            'EUR' => ['€'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
