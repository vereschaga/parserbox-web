<?php

namespace AwardWallet\Engine\qantas\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "qantas/it-12900185.eml, qantas/it-2.eml, qantas/it-23012603.eml, qantas/it-23027622.eml, qantas/it-248275439.eml, qantas/it-26633107.eml, qantas/it-3.eml, qantas/it-3232794.eml, qantas/it-5.eml, qantas/it-550310591.eml, qantas/it-5830651.eml, qantas/it-5948132.eml, qantas/it-6672403.eml, qantas/it-833986940.eml, qantas/it-834933071.eml, qantas/it-887134386.eml"; // +1 bcdtravel(pdf)[en]

    public static $dictionary = [
        'es' => [
            'passengersStart'                 => ['Información sobre el pasaje'],
            'passengersEnd'                   => ['Total del Billete para todos los Pasajeros'],
            'Your Booking Reference'          => ['Your Booking Reference', 'La Referencia de su Reserva:'],
            'Ticket Total for all passengers' => 'Total del Billete para todos los Pasajeros',
            'Total Amount Payable'            => 'Importe total a pagar',
            'Amounts are displayed in'        => 'Todos los importes se muestran en',
            'Your Itinerary'                  => 'Su Itinerario',
            'Payment Details'                 => 'Detalles del Pago',
            'This may appear'                 => 'Es posible que esto aparezca como',
            'Amount'                          => 'Importe',
            'Issued'                          => 'Emitido',
            'Flight Number'                   => 'Número de Vuelo',
            'Departing'                       => 'Salida',
            //            'Terminal' => '',
            'Operated By'       => 'Operado por',
            'Aircraft Type:'    => 'Tipo de Aeronave:',
            'Est journey Time:' => 'Tiempo Estimado de Viaje:',
            'Non-Stop'          => 'Sin Escala',
        ],
        'fr' => [
            'passengersStart'        => ['Informations sur votre billet'],
            'passengersEnd'          => ["**Une partie/l'intégralité de cette Réservation payée en utilisant les points Frequent Flyer"],
            'Your Booking Reference' => ['Your Booking Reference', 'Votre Référence de Réservation'],
            //            'Ticket Total for all passengers' => '',
            //            'Amounts are displayed in' => '',
            //              'Total Amount Payable' => '',
            'Your Itinerary'                 => 'Votre Itinéraire',
            'Payment Details'                => 'Informations sur le Paiement',
            'This may appear'                => 'Ceci pourrait apparaître comme',
            'Amount'                         => 'Montant',
            'Issued'                         => 'Fourni',
            'Flight Number'                  => 'No. de Vol',
            'Departing'                      => 'Départ de',
            //            'Terminal' => '',
            'Operated By'       => 'Exploité par',
            'Aircraft Type:'    => "Type d'Avion:",
            'Est journey Time:' => 'Durée Estimée de Voyage:',
            'Non-Stop'          => 'Sans Escale',
        ],
        'en' => [
            'passengersStart' => ['Passenger Information', 'Passenger Ticket Information'],
            'passengersEnd'   => [
                'Ticket Total for all passengers',
                '**Some/all of this Booking paid for using Frequent Flyer Points',
            ],
            'Non-Stop' => ['Non-Stop', 'Non - Stop', 'Non Stop', 'NonStop'],
        ],
    ];

    public $lang = "en";

    private $reFrom = "qantas.com";
    private $reSubject = [
        "es" => "Información de Salida de Qantas - ",
        "fr" => "Informations sur les Départs de Qantas - ",
        "en" => "Confirmation and E-Ticket Flight Itinerary for",
    ];
    private $reBody = 'qantas.com';
    private $reBody2 = [
        "es"  => "Itinerario de Pasaje Electrónico",
        "fr"  => "Itinéraire-Reçu",
        "en"  => "E-Ticket Itinerary & Receipt",
        "en2" => "Itinerary Receipt",
    ];
    private $pdfPattern = ".*\.pdf.*";
    private $text = '';
    private $date = 0;

    private $patterns = [
        'namePrefixes' => '(?:Master|Mstr|Miss|Mrs|Mr|Ms|Dr)',
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'ffNumber' => '(?<name>[A-Z]{2})[ ]*(?<number>\d[A-Z\d]{4,})', // QF9365899
        'ffNumber2' => '(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]+(?<number>[A-Z\d]{5,})', // IB 15939663
        'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
    ];

    public function parsePdf(Email $email): void
    {
        $text = $this->text;

        $f = $email->add()->flight();

        // RecordLocator
        $rlTable = $this->splitCols($this->re("/(.*{$this->opt($this->t('Your Booking Reference'))}[\s\S]*)/u", $text));

        if (!empty($rlTable)) {
            $f->general()
                ->confirmation($this->re("/{$this->opt($this->t('Your Booking Reference'))}\s+([A-Z\d]{5,})\n/u",
                    $rlTable[count($rlTable) - 1]));
        }

        // Travellers
        $passengers = $infants = [];

        $passText = $this->re("/{$this->opt($this->t('passengersStart'))}\n(.*?)\n\s*{$this->opt($this->t('passengersEnd'))}/su",
            $text);

        if (empty($passText)) {
            $passText = $this->re("/{$this->opt($this->t('passengersStart'))}\n(.*?)\n\s*(?:{$this->opt($this->t('Your Itinerary'))}|Your Itinerary|\n\n\n)/su",
                $text);
        }

        if ($this->lang !== 'en') {
            $passText = preg_replace("/\s*{$this->opt(self::$dictionary['en']['passengersStart'])}\s*/", '',
                $passText); // remove main table header in English
            $passText = preg_replace("/.*Passenger Name.*\s*/i", '', $passText); // remove table headers in English
        }

        $cols = $this->rowColsPos($this->re("/^(.+)/", $passText));

        $passengerRows = $pRows = [];
        $passTextRows = preg_split('/\n+/', $this->re('/^.+\n+([\s\S]+)/', $passText));

        foreach ($passTextRows as $key => $row) {
            if ($key === 0) {
                $pRows[] = $row;

                continue;
            }

            if (preg_match("/^[ ]{0,5}{$this->patterns['namePrefixes']} /i", $row)
                || preg_match("/^.{20,} {$this->patterns['ffNumber']}(?:[ ]{2}|$)/", $row) // ffNumber
                || preg_match("/^.{20,} {$this->patterns['ffNumber2']}(?:[ ]{2}|$)/", $row) // ffNumber
                || preg_match("/^.{20,} {$this->patterns['eTicket']}(?:[ ]{2}|$)/", $row) // ticket
                || preg_match("/^.{20,} \d{2} [[:alpha:]]+ (?:\d{2}|\d{4})(?:[ ]{2}|$)/u", $row) // 02 Oct 15
                || preg_match("/^.{20,} \d[,\d]*\.\d{2}$/u", $row) // 1,504.98  |  0.00
            ) {
                $passengerRows[] = implode("\n", $pRows);
                $pRows = [];
            }

            $pRows[] = $row;
        }

        if (count($pRows) > 0) {
            $passengerRows[] = implode("\n", $pRows);
            $pRows = [];
        }

        $this->logger->info('$passengerRows $passText = ' . print_r($this->re("/^.+\n+([\s\S]+)/", $passText), true));
        $this->logger->debug('$passengerRows = ' . print_r($passengerRows, true));

        foreach ($passengerRows as $i => $pRow) {
            $rTable = $this->splitCols($pRow, $cols);
            $isInfant = false;

            if (count($rTable) > 0 && preg_match("/^\s*([\s\S]*?{$this->patterns['travellerName']})\n+[ ]{0,5}Infant[ ]*:/iu", $rTable[0], $m)) {
                // it-834933071.eml
                $isInfant = true;
                $rTable[0] = $m[1];
            }

            $rTable[0] = count($rTable) > 0 ? preg_replace('/\s+/', ' ', trim($rTable[0])) : '';

            if (preg_match("/^({$this->patterns['travellerName']})\s*(?:\(|$)/u", $rTable[0], $m)
                && strpos(trim($rTable[0]), ' ')
            ) {
                // Mr Jamie Norton    |    Miss Maddison Norton (Child)
                $traveller = $this->normalizeTraveller($m[1]);

                if ($isInfant) {
                    $infants[] = $traveller;
                } else {
                    $passengers[] = $traveller;
                }
            } else {
                $passengers = $infants = [];

                break;
            }

            if (count($rTable) > 1 && preg_match("/^\s*{$this->patterns['ffNumber']}[ ]*(?:\n|$)/", $rTable[1], $m)
                || count($rTable) > 1 && preg_match("/^\s*{$this->patterns['ffNumber2']}[ ]*(?:\n|$)/", $rTable[1], $m)
            ) {
                $f->program()
                    ->account($m['number'], false, $traveller, $m['name']);
            }

            if (count($rTable) > 2 && preg_match("/^\s*(?<ticket>{$this->patterns['eTicket']})[ ]*(?:\n|$)/", $rTable[2], $m)) {
                $f->issued()
                    ->ticket($m['ticket'], false, $traveller);
            }

            // ReservationDate
            if ($i === 0 && count($rTable) > 3 && preg_match("/^\s*\d{2} [[:alpha:]]+ (?:\d{2}|\d{4})\s*$/u", $rTable[3])) {
                $date = $this->normalizeDate($rTable[3]);

                if (!empty($date)) {
                    $f->general()
                        ->date($date);
                }
            }
        }

        if (count($infants) > 0) {
            $f->general()->infants($infants);
        }

        $f->general()
            ->travellers($passengers, true);

        // Price
        $totalAmount = $this->re("/{$this->opt($this->t('Ticket Total for all passengers'))}\D* (\d[\d\,\.]*)\D*\n/u", $text);
        $currencyCode = $this->re("/{$this->opt($this->t('Amounts are displayed in'))} .*\(?(\b[A-Z]{3}\b)\)?/u", $text)
            ?? $this->re("/{$this->opt($this->t('Ticket Total for all passengers'))}.*\(?(\b[A-Z]{3}\b)\)?/u", $text);

        if ($totalAmount !== null && !empty($currencyCode)) {
            $f->price()
                ->total(PriceHelper::parse($totalAmount, $currencyCode))
                ->currency($currencyCode);
        }
        // }
        //Fees
        //startText 0, endText 1;
        $feesBlock = [
            "1" => [
                "Additional\s*Ticket Charges\s*Charges\s*GST\s*Total\*",
                "\*Taxes\/Fees\/Carrier Charges may include non-refundable amounts",
            ],
            "2" => ["Ticket Charges\s*Charges\s*GST\s*Total\*\s*", "\*Includes Taxes\/Fees\/carrier Charges"],
        ];
        $feesName = ["Change Fee", "Card Payment Fee"];

        foreach ($feesBlock as $block) {
            if (preg_match('/(' . $block[0] . '(?:\n.*?)+)' . $block[1] . '/m',
                $text, $m)) {
                $tables = $this->splitCols($m[1]);

                $title = array_filter(array_map('trim', preg_split("/\n/m", $tables[0])));

                $charges = array_filter(array_map('trim',
                    preg_split("/\n/m", $tables[array_keys(preg_grep("/^Charges/", $tables))[0]])));

                foreach ($feesName as $v) {
                    if (!empty($title) && !empty($charges)) {
                        $num = array_keys(preg_grep("/^" . $v . "$/", $title));

                        if (!empty($num)) {
                            if (!empty($charges[$num[0]]) && $charges[$num[0]] !== '0.00') {
                                $f->price()->fee($v, PriceHelper::parse($charges[array_search($v, $title)], $currencyCode));
                            }
                        }
                    }
                }
                $gst = array_values(array_filter(array_map('trim',
                    preg_split("/\n/m", $tables[array_keys(preg_grep("/^GST/", $tables))[0]]))));

                if ((!empty($gst[0] && $gst[0] === 'GST') && (!empty($gst[1]) && count($gst) === 2))) {
                    if (preg_match("/(^\d[\d,.]+)$/", $gst[1], $m)) {
                        if ($m[1] !== '0.00') {
                            $f->price()->fee($gst[0], PriceHelper::parse($m[1], $currencyCode));
                        }
                    }
                }
            }
        }

        if (!empty($fee = $this->re("/{$this->opt($this->t('Includes your total credit card fee of'))}[ ]+(\d[\d\.]+)/",
            $text))
        ) {
            $f->price()->fee('credit card fee', PriceHelper::parse($fee, $currencyCode));
        }
        $textPay = $this->re("/(?:[ ]{2,}|\n[ ]*){$this->t('Payment Details')}(.+)/su", $text);

        if ($totalAmount === null && preg_match("/{$this->t('Amount')}\*\n(.+?)\n[^\n]*{$this->t('This may appear')}/us", $textPay, $m)
            && (preg_match_all("/xxxx-xxxx-xxxx.+?[ ]{2,}-?(?<amount>\d[,.\'\d]*)$/m", $m[1], $v, PREG_SET_ORDER)
                || preg_match_all("/^[ ]*\d{1,2}[- ]+[[:alpha:]]{3,}[- ]+\d{2,4}[ ]{2}[^\d\n]*[ ]{50,}-?(?<amount>\d[,.\'\d]*)$/mu", $m[1], $v, PREG_SET_ORDER)
            )
        ) {
            if (is_array($v) && count($v) > 1) {
                $this->logger->alert('new example payment');
            } else {
                $f->price()->total(PriceHelper::parse($v[0]['amount'], $currencyCode));
            }
        }

        if ($this->lang !== 'en') {
            $text = preg_replace("/.*Flight Number\s+Departing.*\s*/i", '', $text); // remove table headers in English
        }
        $flightsText = $this->re("/{$this->opt($this->t('Flight Number'))}[ ]+{$this->opt($this->t('Departing'))}[^\n]+([\n]+.*?)\n\n/su",
            $text);

        //30 Nov 19               QF10*
        $segments = $this->splitText($flightsText, '/^([ ]{0,5}\d{2} [[:alpha:]]+ (?:\d{2}|\d{4})[ ]+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d+[* ]+)/mu', true);

        foreach ($segments as $stext) {
            $table = $this->splitCols($stext, $this->colsPos($stext));

            if (count($table) == 5 && preg_match("#\w Confirmed#", $stext)) { //it-26633107.eml
                $stext = preg_replace("#^(.+\w)( Confirmed {5})(.+)#s", '$1    Confirmed  $3', $stext);
                $table = $this->splitCols($stext, $this->colsPos($stext));
            }

            if (count($table) !== 6) {
                $this->logger->debug("incorrect parse segment table");

                return;
            }

            if (preg_match('/^.+/', $table[0], $m)) {
                $date = $this->normalizeDate(trim($m[0]));

                if (empty($date)) {
                    $this->logger->alert('No date for the segment!');

                    return;
                }
            } else {
                $this->logger->alert('No date for the segment!');

                return;
            }

            $s = $f->addSegment();

            // AirlineName
            // FlightNumber
            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)/', trim($table[1]),
                $matches)) {
                if (!empty($matches['airline'])) {
                    $s->airline()
                        ->name($matches['airline']);
                }
                $s->airline()
                    ->number($matches['flightNumber']);
            }

            // Stops
            if ($this->re("/({$this->opt($this->t('Non-Stop'))})/i", $table[5])) {
                $s->extra()
                    ->stops(0);
            } elseif (($stops = $this->re('/Stopovers: (\d+)/', $table[5])) !== null) {
                $s->extra()
                    ->stops($stops);
            } else {
                $stopName = $this->re("/Stopover:\s*(\D+)\n/", $table[5]);
            }

            // DepName
            $depName = str_replace("\n", " ", trim($this->re("#(.*?)\d{4}#s", $table[2])));
            $depTime = $this->re("#(\d{4})#s", $table[2]);

            if (empty($depName)) {
                $depName = $this->re("/^(.+)\n\d+\:\d+A?P?M/ms", $table[2]);
                $depTime = $this->re("#.+\n(\d+\:\d+A?P?M)#", $table[2]);
            }

            $s->departure()
                ->name(str_replace("\n", " ", $depName))
                ->date($this->normalizeDate($depTime, $date))
                ->noCode();

            // DepartureTerminal
            $terminalDep = $this->re("/{$this->opt($this->t('Terminal'))} (.+)/", $table[2]);

            if ($terminalDep) {
                $s->departure()
                    ->terminal($terminalDep);
            }

            // Operator
            $operator = $this->re("/{$this->opt($this->t('Operated By'))}\s+(.+)/", $table[1]);

            if ($operator) {
                $s->airline()
                    ->operator(str_replace("\n", " ", trim($operator)));
            }

            $s->extra()
                ->aircraft($this->re("/{$this->opt($this->t('Aircraft Type:'))} (.+)/", $table[5]));

            $cabin = preg_replace('/(?:confirmed)/i', '', $this->re("#(.+)#", $table[4]));

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            if (!empty($stopName)) {
                $s->arrival()
                    ->name($stopName)
                    ->noCode()
                    ->noDate();

                $airlineName = $s->getAirlineName();
                $flightNumber = $s->getFlightNumber();

                $s = $f->addSegment();

                $s->extra()
                    ->aircraft($this->re("/{$this->opt($this->t('Aircraft Type:'))} (.+)/", $table[5]));

                $cabin = preg_replace('/(?:confirmed)/i', '', $this->re("#(.+)#", $table[4]));

                if (!empty($cabin)) {
                    $s->extra()
                        ->cabin($cabin);
                }

                $s->airline()
                    ->name($airlineName)
                    ->number($flightNumber);

                $s->departure()
                    ->name($stopName)
                    ->noDate()
                    ->noCode();

                $dateArr = $this->re("/\d+\:\d+A?P?M?\n(\d+\s*\w+\s*\d+)\n/", $table[3]);

                if (!empty($dateArr)) {
                    $date = $this->normalizeDate($dateArr);
                }

                $s->arrival()
                    ->name(str_replace("\n", " ", trim($this->re("#(.*?)\n(?:\d{4}|\d+\:\d+)#s", $table[3]))))
                    ->date($this->normalizeDate(str_replace("\n", " ",
                        trim($this->re("#((?:\d{4}|\d+\:\d+)[^\n]+\n[^\n]+)#s", $table[3]))), $date))
                    ->noCode();

                $stopName = '';
            } else {
                $dateArr = $this->re("/\d+\:\d+A?P?M?\n(\d+\s*\w+\s*\d+)\n/", $table[3]);

                if (!empty($dateArr)) {
                    $date = $this->normalizeDate($dateArr);
                }

                $arrName = str_replace("\n", " ", trim($this->re("#(.*?)\n\d{4}#s", $table[3])));
                $arrDate = $this->normalizeDate(str_replace("\n", " ",
                    trim($this->re("#(\d{4}[^\n]+\n[^\n]+)#s", $table[3]))), $date);

                if (empty($arrName)) {
                    $arrName = str_replace("\n", " ", trim($this->re("#^(.+)\n\d+\:\d+A?P?M#ms", $table[3])));
                    $arrDate = $this->normalizeDate(str_replace("\n", " ",
                        trim($this->re("#.+\n(\d+\:\d+A?P?M)#", $table[3]))), $date);
                }

                $s->arrival()
                    ->name($arrName)
                    ->date($arrDate)
                    ->noCode();

                // Duration
                $duration = $this->re("/{$this->opt($this->t('Est journey Time:'))} (\d.+)/", $table[5]);

                if ($duration) {
                    $s->extra()
                        ->duration($duration);
                }
            }

            // ArrivalTerminal
            $terminalArr = $this->re("/{$this->opt($this->t('Terminal'))} (.+)/", $table[3]);

            if ($terminalArr) {
                $s->arrival()
                    ->terminal($terminalArr);
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || strpos($textPdf, $this->reBody) === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = EmailDateHelper::calculateOriginalDate($this, $parser);
        //		$this->logger->debug('Relative date: '.date('r', $this->date));

        $this->http->FilterHTML = false;

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            $this->logger->debug('no pdf');

            return $email;
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->text = $textPdf;

                break;
            }
        }

        if (empty($this->text)) {
            $this->logger->debug('not detect');

            return $email;
        }

        $this->parsePdf($email);

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignLang($text): bool
    {
        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($text, $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($instr, $relDate = false)
    {
        //$this->logger->error($instr);
        if ($relDate === false) {
            $relDate = $this->date;
        }
        $in = [
            // 30 Nov 19
            '/^(\d+ [A-z]{3} \d+)$/',
            "#^(\d{2})(\d{2})$#", //0630
            "#^(\d{2})(\d{2}), \d+:\d+[AP]M (\d+) ([^\s\d]+) (\d{2})$#", //0630
        ];
        $out = [
            '$1',
            "$1:$2 ",
            "$3 $4 $5, $1:$2",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{2,4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }
        //$this->logger->debug("{$instr} -> {$str} -> ".strtotime($str, $relDate));
        return strtotime($str, $relDate);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];
        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);
            for ($i=0; $i < count($textFragments)-1; $i+=2)
                $result[] = $textFragments[$i] . $textFragments[$i+1];
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }
        return $result;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];
        if ($text === null)
            return $cols;
        $rows = explode("\n", $text);
        if ($pos === null || count($pos) === 0) $pos = $this->rowColsPos($rows[0]);
        arsort($pos);
        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);
        foreach ($cols as &$col) $col = implode("\n", $col);
        return $cols;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_replace('/[ ]+/', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        return preg_replace([
            "/^(?:{$this->patterns['namePrefixes']}[.\s]+)+(.{2,})$/is",
        ], [
            '$1',
        ], $s);
    }
}
