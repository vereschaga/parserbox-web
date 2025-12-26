<?php

namespace AwardWallet\Engine\almatar\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Common\Parser\Util\PriceHelper;

class FlightReservationPdf extends \TAccountChecker
{
    public $mailFiles = "almatar/it-658157482.eml, almatar/it-657068948.eml";

    private $subjects = [
        'en' => ['Booking Confirmation']
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            /* Itinerary */
            'otaConfNumber' => 'Almatar ID',
            'confNumber' => ['Airline PNR (SBR)', 'Airline PNR'],
            'direction' => ['Departure', 'Return'],
            'From' => ['From'],
            'Arrival' => ['Arrival'],

            /* Invoice */
            'Payment Summary' => ['Payment Summary'],
            'totalFare' => ['Total Fare (Including VAT)', 'Total Fare'],
            'totalPrice' => ['Total Amount Charged (Via Card)', 'Total Amount Charged'],
        ]
    ];

    private function parseFlightPdf(Email $email, string $text, string $textInvoice = ''): void
    {
        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
        ];

        if (preg_match("/^[ ]{0,10}({$this->opt($this->t('otaConfNumber'))})[: ]*(?:[ ]{2,}\S.*)?\n{1,2}[ ]{0,10}([-A-Z\d]{4,20})(?:[ ]{2}|$)/m", $text, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        $f = $email->add()->flight();

        if (preg_match("/^[ ]{0,10}{$this->opt($this->t('otaConfNumber'))}[: ]+({$this->opt($this->t('confNumber'))})[: ]*(?:[ ]{2,}\S.*)?\n{1,2}[ ]{0,10}(?:[-A-Z\d]{4,20}|[ ]{9,20})[ ]{2,}([A-Z\d]{5,10})(?:[ ]{2}|$)/m", $text, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        $travellers = $tickets = [];

        $flightDetails = $this->re("/^([ ]*{$this->opt($this->t('Departure'))}[ ]+{$this->opt($this->t('From'))} .+ {$this->opt($this->t('Arrival'))} [\s\S]+)/m", $text);

        // remove garbage
        $flightDetails = preg_replace("/^[ ]*{$this->opt($this->t('direction'))}$/m", '', $flightDetails);

        $flights = $this->splitText($flightDetails, "/^([ ]*{$this->opt($this->t('Departure'))}[ ]+{$this->opt($this->t('From'))} .+ {$this->opt($this->t('Arrival'))} )/m", true);

        foreach ($flights as $fText) {
            if (preg_match("/^(.+?)\n+([ ]*{$this->opt($this->t('Passenger'))}[- ]+\d.*)$/s", $fText, $m)) {
                $fText = $m[1];
                $passengersText = $m[2];
            } else {
                $passengersText = '';
            }

            // remove garbage
            $fText = preg_replace("/^[ ]*{$this->opt($this->t('Stop'))}[ ]*:.*/m", '', $fText);

            $segments = $this->splitText($fText, "/^(.{4,} [A-Z]{3} .{4,} [A-Z]{3}(?: |$))/m", true);

            foreach ($segments as $segText) {
                $s = $f->addSegment();

                $tablePos = [0];

                if (preg_match("/^([ ]{0,10}[-[:alpha:]]{3,20} \d{1,2} [[:alpha:]]{3,20} ?, ?\d{4}[ ]+)\S/mu", $segText, $matches) // Wednesday 10 Apr, 2024
                    || preg_match("/^([ ]{0,10}\d{1,2} [[:alpha:]]{3,20} ?, ?\d{4}[ ]+)\S/mu", $segText, $matches) // 10 Apr, 2024
                    || preg_match("/^([ ]{0,10}[-[:alpha:]]{3,20} \d{1,2} [[:alpha:]]{3,20} ?,[ ]+)\S/mu", $segText, $matches) // Wednesday 10 Apr,
                    || preg_match("/^([ ]{0,10}[-[:alpha:]]{3,20} \d{1,2}[ ]+)\S/mu", $segText, $matches) // Wednesday 10
                ) {
                    $tablePos[1] = mb_strlen($matches[1]);
                }

                if (preg_match("/^((.{30,} )[-[:alpha:]]{3,20} \d{1,2} [[:alpha:]]{3,20} ?, ?\d{4}[ ]+)\S/mu", $segText, $matches) // Wednesday 10 Apr, 2024
                    || preg_match("/^((.{30,} )\d{1,2} [[:alpha:]]{3,20} ?, ?\d{4}[ ]+)\S/mu", $segText, $matches) // 10 Apr, 2024
                    || preg_match("/^((.{30,} )[-[:alpha:]]{3,20} \d{1,2} [[:alpha:]]{3,20} ?,[ ]+)\S/mu", $segText, $matches) // Wednesday 10 Apr,
                    || preg_match("/^((.{30,} )[-[:alpha:]]{3,20} \d{1,2}[ ]+)\S/mu", $segText, $matches) // Wednesday 10
                ) {
                    $tablePos[3] = mb_strlen($matches[2]);
                    $tablePos[4] = mb_strlen($matches[1]);
                }

                if (preg_match("/^(.{50,}? )(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?- ?\d{1,5}(?: |$)/m", $segText, $matches)) {
                    $tablePos[5] = mb_strlen($matches[1]);
                }

                $table = $this->splitCols($segText, $tablePos);

                $patternDate = "/^\s*(?<time>{$patterns['time']})\n+[ ]*(?<date>.{4,}\b\d{4}\b)/su";

                if (count($table) > 0 && preg_match($patternDate, $table[0], $m)) {
                    $dateDep = strtotime(preg_replace('/\s+/', ' ', $m['date']));
                    $s->departure()->date(strtotime($m['time'], $dateDep));
                }

                if (count($table) > 1 && preg_match("/^(?<start>.*?)[ ]+(?<duration>(?:\d{1,3} ?[hrmin]+[ ]*)+)(?<end>\n.*|$)/is", $table[1], $m)) {
                    // 2hr 20 min
                    $s->extra()->duration(trim($m['duration']));
                    $table[1] = $m['start'] . $m['end'];
                    $table[2] = $m['duration'];
                    ksort($table);
                }

                $patternCode = "/^\s*(?<code>[A-Z]{3})(?:\n+[ ]*(?<airport>\S.*?)\s*|\s*)$/s";
                $patternName = "/^\s*(?<name>\S.*?\S)\s*Terminal/is";
                $patternTerminal = "/Terminal\s*(?<terminal>\S.*?)\s*$/is";

                if (count($table) > 1 && preg_match($patternCode, $table[1], $m)) {
                    $s->departure()->code($m['code']);

                    if (!empty($m['airport'])) {
                        if (preg_match($patternName, $m['airport'], $m2)) {
                            $s->departure()->name(preg_replace('/\s+/', ' ', $m2['name']));
                        }

                        if (preg_match($patternTerminal, $m['airport'], $m2)) {
                            $terminalDep = preg_replace('/\s+/', ' ', $m2['terminal']);
                            $s->departure()->terminal(preg_match("/^\s*N\s*\/\s*A\s*$/i", $terminalDep) ? null : $terminalDep, false, true);
                        }
                    }
                }

                if (count($table) > 3 && preg_match($patternDate, $table[3], $m)) {
                    $dateArr = strtotime(preg_replace('/\s+/', ' ', $m['date']));
                    $s->arrival()->date(strtotime($m['time'], $dateArr));
                }

                if (count($table) > 4) {
                    $table[4] = preg_replace("/[ ]{2,}\S.{0,10}$/m", '', $table[4]); // remove garbage

                    if (preg_match($patternCode, $table[4], $m)) {
                        $s->arrival()->code($m['code']);

                        if (!empty($m['airport'])) {
                            if (preg_match($patternName, $m['airport'], $m2)) {
                                $s->arrival()->name(preg_replace('/\s+/', ' ', $m2['name']));
                            }

                            if (preg_match($patternTerminal, $m['airport'], $m2)) {
                                $terminalArr = preg_replace('/\s+/', ' ', $m2['terminal']);
                                $s->arrival()->terminal(preg_match("/^\s*N\s*\/\s*A\s*$/i", $terminalArr) ? null : $terminalArr, false, true);
                            }
                        }
                    }
                }

                if (count($table) > 5 && preg_match("/(?:^\s*|\n[ ]*)(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?- ?(?<number>\d+)(?:\s+(?<cabin>\S.+?))?\s*$/s", $table[5], $m)) {
                    $s->airline()->name($m['name'])->number($m['number']);

                    if (!empty($m['cabin'])) {
                        $s->extra()->cabin(preg_replace('/\s+/', ' ', $m['cabin']));
                    }
                }
            }

            $passengerRows = $this->splitText($passengersText, "/^([ ]*{$this->opt($this->t('Passenger'))}[- ]+\d)/m", true);

            foreach ($passengerRows as $pText) {
                $passengerName = $this->re("/^[ ]*{$this->opt($this->t('Passenger'))}[- ]+\d+[ ]*[:]+[ ]*({$patterns['travellerName']})(?:\n|$)/u", $pText);

                if ($passengerName && !in_array($passengerName, $travellers)) {
                    $f->general()->traveller($passengerName, true);
                    $travellers[] = $passengerName;
                }

                if (preg_match("/\n[ ]{0,10}{$this->opt($this->t('Document'))}[ ]{2}.+\n{1,2}[ ]{0,10}({$patterns['eTicket']})(?:[ ]{2}|\n|$)/", $pText, $m)
                    && !in_array($m[1], $tickets)
                ) {
                    $f->issued()->ticket($m[1], false, $passengerName);
                    $tickets[] = $m[1];
                }
            }
        }

        if (empty($textInvoice)) {
            return;
        }

        $priceText = $this->re("/\n([ ]*{$this->opt($this->t('Payment Summary'))}(?:[ ]{2,}\S.*)?\n[\s\S]+)/", $textInvoice);
        $currencyCode = $this->re("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Currency'))}[: ]+([A-Z]{3})$/m", $priceText);

        $totalPrice = $this->re("/^[ ]*{$this->opt($this->t('totalPrice'))}[ ]{2,}(\S.*)$/m", $priceText);

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // 2234
            $f->price()->currency($currencyCode)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->re("/^[ ]*{$this->opt($this->t('Sub Total'))}[ ]{2,}(\S.*)$/m", $priceText);

            if ( preg_match('/^(?<amount>\d[,.‘\'\d ]*?)$/u', $baseFare, $m) ) {
                $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feesText = $this->re("/^[ ]*{$this->opt($this->t('Sub Total'))}[ ]{2,}\S.*\n+([\s\S]+?)\n+[ ]*(?:{$this->opt($this->t('totalFare'))}|{$this->opt($this->t('totalPrice'))})/m", $priceText) ?? '';
            $feesRows = preg_split("/\n+/", $feesText);
            
            foreach ($feesRows as $feeRow) {
                if (preg_match("/^[ ]*(?<name>\S.*?\S)[ ]{2,}(?<charge>\S.*)$/m", $feeRow, $m)) {
                    if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)$/u', $m['charge'], $m2)) {
                        $f->price()->fee($m['name'], PriceHelper::parse($m2['amount'], $currencyCode));
                    }
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]almatar\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || !preg_match('/\bAlmatar\b/i', $headers['subject']))
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array)$phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false)
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

            if (empty($textPdf) || strpos($textPdf, 'Almatar ID') === false) {
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');
        
        /* Step 1: find supported formats */
        
        $usingLangs = $pdfsItinerary = $pdfsInvoice = [];

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $usingLangs[] = $this->lang;
                $pdfsItinerary[] = [
                    'lang' => $this->lang,
                    'text' => $textPdf,
                ];
            } elseif ($this->assignLangInvoice($textPdf)) {
                $this->logger->debug('Found invoice PDF in language: ' . strtoupper($this->lang));

                $pdfsInvoice[] = [
                    'lang' => $this->lang,
                    'text' => $textPdf,
                ];
            }
        }

        /* Step 2: parsing */

        $textInvoice = count($pdfsItinerary) === 1 && count($pdfsInvoice) === 1 ? $pdfsInvoice[0]['text'] : '';

        foreach ($pdfsItinerary as $pdfItinerary) {
            $this->lang = $pdfItinerary['lang'];
            $this->parseFlightPdf($email, $pdfItinerary['text'], $textInvoice);
        }

        if (count(array_unique($usingLangs)) === 1
            || count(array_unique(array_filter($usingLangs, function ($item) { return $item !== 'en'; }))) === 1
        ) {
            $email->setType('FlightReservationPdf' . ucfirst($usingLangs[0]));
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

    private function assignLang(?string $text): bool
    {
        if ( empty($text) || !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['From']) || empty($phrases['Arrival']) ) {
                continue;
            }
            if ($this->strposArray($text, $phrases['From']) !== false
                && $this->strposArray($text, $phrases['Arrival']) !== false
            ) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function assignLangInvoice(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Payment Summary'])) {
                continue;
            }

            if (preg_match("/\n[ ]*{$this->opt($phrases['Payment Summary'])}(?:[ ]{2,}\S|\n)/", $text)) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
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
}
