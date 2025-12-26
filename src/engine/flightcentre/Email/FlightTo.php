<?php

namespace AwardWallet\Engine\flightcentre\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class FlightTo extends \TAccountChecker
{
    public $mailFiles = "flightcentre/it-12558992.eml, flightcentre/it-12572956.eml, flightcentre/it-59982847.eml, flightcentre/it-12583501.eml";
    public $reFrom = [
        "flightcentre.com.au",
    ];
    public $reSubject = [
        "Your Flight Centre Itinerary - ",
    ];

    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dictionary = [
        'en' => [
            'FLIGHT TO:'      => ['FLIGHT TO:', 'Flight to:'],
            'BOOKING DETAILS' => ['BOOKING DETAILS'],
            'confNumber'      => ['BOOKING #', 'CONFIRMATION #'],
            'flightStart'     => ['AIRLINE CODE & FLIGHT #', 'AIRLINE & FLIGHT #', 'AIRLINE & FLIGHT', 'AIRLINE &'],
            'flightEnd'       => 'PASSENGER',
            'passengersStart' => 'PASSENGER',
            'passengersEnd'   => 'BOOKING DETAILS',
            'class'           => ['CABIN CLASS', 'CLASS'],
            'ffNumber'        => ['FREQUENT FLYER', 'LOYALTY CARD #'],
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
    ];

    private $tickets = [];
    private $accounts = [];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $i => $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text) || !$this->assignLang($text)) {
                $this->logger->debug("can't determine a language in {$i}-attach");

                continue;
            }

            $this->parsePdf($email, $text);
        }

        $email->setType('FlightTo' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            if (stripos($text, '@flightcentre.com.au') === false
                && stripos($text, '.flightcentre.com.au/') === false
                && stripos($text, 'Flight Centre Online AU') === false
                && stripos($text, 'Flight Centre Cairns Central Cruise') === false
            ) {
                continue;
            }

            if ($this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    private function parsePdf(Email $email, $textPDF): void
    {
        $segments = $this->splitText($textPDF, "/(.*{$this->opt($this->t('FLIGHT TO:'))})/", true);

        foreach ($segments as $segText) {
            $f = $email->add()->flight();
            $seg = $f->addSegment();

            /* Wrapping table */

            $tablePos = [0];

            if (preg_match("/^(.*?){$this->opt($this->t('FLIGHT TO:'))}/m", $this->re("/^([\s\S]+?)\n *{$this->opt($this->t('PASSENGER'))}/", $segText), $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($segText, $tablePos);

            if (count($table) === 2) {
                $segText = preg_replace("/^[\s\S]+?(\n *{$this->opt($this->t('PASSENGER'))})/", $table[1] . '$1', $segText);
            }

            /* Flight table */

            $flightText = $this->re("/^([ ]*{$this->opt($this->t('flightStart'))}[\s\S]+?)\n+[ ]*{$this->opt($this->t('flightEnd'))}/m", $segText);

            $tablePos = [0];

            if (preg_match("/^(.{10,}?) {$this->opt($this->t('confNumber'))}(?: |$)/m", $flightText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^(.{21,}?) {$this->opt($this->t('DEPARTURE'))}(?: |$)/m", $flightText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            $tablePos3Values = [];

            if (preg_match("/^(.{32,}?) {$this->opt($this->t('ARRIVAL'))}(?: |$)/m", $flightText, $matches)) {
                $tablePos3Values[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^(.{21,}? [A-Z]{3}[ ]*[:]+.+?) [A-Z]{3}[ ]*:/m", $flightText, $matches)) {
                // it-12583501.eml
                $tablePos3Values[] = mb_strlen($matches[1]);
            }

            sort($tablePos3Values);

            if (count($tablePos3Values) > 0) {
                $tablePos[] = $tablePos3Values[0];
            }

            $flightTable = $this->splitCols($flightText, $tablePos);

            if (count($flightTable) !== 4) {
                $this->logger->debug('Wrong flight table!');
                $flightTable = ['', '', '', ''];
            }

            // remove garbage (it-12583501.eml)
            $flightTable[3] = preg_replace('/^([ ]{0,10}\S.*?|[ ]{12})[ ]{2,}\S.*$/m', '$1', $flightTable[3]);

            if (preg_match("/^[ ]*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s(?<number>\d{1,5})[ ]*$/m", $flightTable[0], $m)
                || preg_match("/{$this->opt($this->t('flightStart'))}\s*(?<name>[\s\S]+?)\\n(?<number>\d{1,5})\n/", $flightTable[0], $m)
            ) {
                $seg->airline()->name($m['name'])->number($m['number']);
            }

            if (preg_match("/({$this->opt($this->t('confNumber'))})\s+([A-Z\d]{5,10})[ ]*$/m", $flightTable[1], $m)
                || preg_match("/{$this->opt($this->t('PNR Reference'))}\s*[:]+\s*([A-Z\d]{5,10})(?:[ ]{2}.+|[ ]*$)/m", $segText, $m)
            ) {
                $f->general()->confirmation($m[2], preg_replace('/\s+/', ' ', $m[1]));
            }

            $tablePartDep = '';

            if (stripos($flightTable[1], $this->t('DEPARTURE')) !== false) {
                $tablePartDep = $flightTable[1];
            } elseif (stripos($flightTable[2], $this->t('DEPARTURE')) !== false) {
                $tablePartDep = $flightTable[2];
            }

            if (preg_match("/{$this->opt($this->t('DEPARTURE'))}\s+(?<time>{$this->patterns['time']})\s+(?<date>[-[:alpha:]]+\s+[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4})\s+(?<code>[A-Z]{3})\s*:/u", $tablePartDep, $m)) {
                $depDate = strtotime($this->normalizeDate(preg_replace('/\s+/', ' ', $m['date'])));
                $seg->departure()->date(strtotime($m['time'], $depDate))->code($m['code']);
            }

            $depTerminalVal = preg_match("/\n(?:\s*Terminal)+\s[ ]*([\s\S]+?)(?:\n\n\n|\s*$)/i", $tablePartDep, $m)
                ? preg_replace(['/\s+/', "/(?:\s*(?:TERMINAL|TERM))+$/i"], [' ', ''], $m[1]) : '';

            if ($depTerminalVal !== '') {
                $seg->departure()->terminal($depTerminalVal);
            }

            $tablePartArr = '';

            if (preg_match("/{$this->opt($this->t('ARRIVAL'))}/i", $flightTable[2])) {
                $tablePartArr = $flightTable[2];
            } elseif (preg_match("/{$this->opt($this->t('ARRIVAL'))}/i", $flightTable[3])) {
                $tablePartArr = $flightTable[3];
            }

            if (preg_match("/{$this->opt($this->t('ARRIVAL'))}\s+(?<time>{$this->patterns['time']})\s+(?<date>[-[:alpha:]]+\s+[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4})\s+(?<code>[A-Z]{3})\s*:/u", $tablePartArr, $m)) {
                $arrDate = strtotime($this->normalizeDate(preg_replace('/\s+/', ' ', $m['date'])));
                $seg->arrival()->date(strtotime($m['time'], $arrDate))->code($m['code']);
            } elseif (!empty($seg->getDepDate()) && !empty($seg->getDepCode())
                && preg_match("/{$this->opt($this->t('ARRIVAL'))}\s+(?<time>{$this->patterns['time']})\s+(?<date>[-[:alpha:]]+\s+[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4})\n/u", $tablePartArr, $m)
                && preg_match("/{$this->opt($this->t('DEPARTURE'))}\s+(?<time>{$this->patterns['time']})\s+(?<date>[-[:alpha:]]+\s+[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4})\n\s*[A-Z]{3}\s*:.+ {3,}(?<code>[A-Z]{3})\s*:/u", $tablePartDep, $md)
            ) {
                $arrDate = strtotime($this->normalizeDate(preg_replace('/\s+/', ' ', $m['date'])));
                $seg->arrival()
                    ->date(strtotime($m['time'], $arrDate))
                    ->code($md['code']);
            }

            $arrTerminalVal = preg_match("/\n(?:\s*Terminal)+\s[ ]*([\s\S]+?)(?:\n\n\n|\s*$)/i", $tablePartArr, $m)
                ? preg_replace(['/\s+/', "/(?:\s*(?:TERMINAL|TERM))+$/i"], [' ', ''], $m[1]) : '';

            if ($arrTerminalVal !== '') {
                $seg->arrival()->terminal($arrTerminalVal);
            }

            /* Passengers table */

            if (preg_match("/^[ ]*{$this->opt($this->t('PASSENGER'))}[ ]+{$this->opt($this->t('SEAT'))}\b/m", $segText)) {
                $this->parsePdfPassengers1($segText, $f, $seg);
            } else {
                $this->parsePdfPassengers2($segText, $f, $seg);
            }

            /* BOOKING DETAILS */

            $operator = $this->re("/\n[ ]*{$this->opt($this->t('Operated By'))}[ ]*[:]+\s\/?(\S.*)/", $segText);

            if (!empty($operator)) {
                if (strlen($operator) > 50) {
                    $operator = $this->re("/^(.+?)\s+(?:AS\b|-)/", $operator);
                }

                $seg->airline()->operator($operator);
            }

            $aircraft = $this->re("/^[ ]*{$this->opt($this->t('Aircraft'))}[ ]*[:]+\s*(.+?)[ ]*$/m", $segText);
            $seg->extra()->aircraft($aircraft, false, true);

            $duration = $this->re("/^[ ]*{$this->opt($this->t('Duration'))}[ ]*[:]+\s*(.+?)[ ]*$/m", $segText);
            $seg->extra()->duration($duration, false, true);

            $distance = $this->re("/^[ ]*{$this->opt($this->t('Distance'))}[ ]*[:]+\s*(\d+)[ ]*$/m", $segText);
            $seg->extra()->miles($distance, false, true);
        }
    }

    private function parsePdfPassengers1(string $segText, Flight $f, FlightSegment $seg): void
    {
        // examples: it-59982847.eml
        $this->logger->debug(__FUNCTION__);

        $passText = $this->re("/^([ ]*{$this->opt($this->t('passengersStart'))}[\s\S]+?)\n+[ ]*{$this->opt($this->t('passengersEnd'))}/m", $segText);
        $passTable = $this->splitCols($passText);

        if (count($passTable) !== 6) {
            $this->logger->debug('Wrong passengers table!');
            $passTable = ['', '', '', '', '', ''];
        }

        $paxText = $this->re("/^\s*{$this->opt($this->t('PASSENGER'))}\n+([\s\S]+?)(?:\n\n\n|\s*$)/", $passTable[0]) ?? '';
        $travellers = preg_split("/([ ]*\n+[ ]*)+/", $paxText);

        if (!empty($travellers)) {
            $f->general()->travellers($travellers, true);
        }

        $seat = $this->re("/{$this->opt($this->t('SEAT'))}\s+(\d+[A-Z])[ ]*$/m", $passTable[1]);
        $seg->extra()->seat($seat, false, true);

        $cabin = $this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('class'))}[ ]*\n+[ ]*([[:upper:]]+)(?:\n\n\n|\s*$)/u", $passTable[2])
            ?? $this->re("/^[ ]*{$this->opt($this->t('class'))}[ ]*[:]+\s*([^:\s][^:]*?)[ ]*$/im", $segText);
        $seg->extra()->cabin($cabin, false, true);

        if (preg_match_all("/^[ ]*({$this->patterns['eTicket']})[ ]*$/m", $passTable[3], $ticketMatches)) {
            $f->issued()->tickets(array_unique($ticketMatches[1]), false);
        }

        $meal = preg_match("/{$this->opt($this->t('MEAL'))}\n{1,2}[ ]*(\S[\s\S]+?)(?i)(?:\n{2}|\n.*\bPAGE\b.+|\s*$)/", $passTable[5], $m) ? preg_replace('/\s+/', ' ', $m[1]) : null;
        $seg->extra()->meal($meal, false, true);
    }

    private function parsePdfPassengers2(string $segText, Flight $f, FlightSegment $seg): void
    {
        // examples: it-12558992.eml
        $this->logger->debug(__FUNCTION__);

        $passText = $this->re("/^([ ]*{$this->opt($this->t('passengersStart'))}[\s\S]+?)\n+[ ]*{$this->opt($this->t('passengersEnd'))}/m", $segText);
        $passRows = $this->splitText($passText, "/^([ ]*{$this->opt($this->t('PASSENGER'))}[ :])/m", true);

        $cabinValues = $mealValues = [];

        foreach ($passRows as $pRow) {
            $passengerName = $this->re("/^[ ]*{$this->opt($this->t('PASSENGER'))}[ :]+({$this->patterns['travellerName']})(?:[ ]{2}|[ ]+{$this->opt($this->t('E-TICKET'))}|\n|\s*$)/u", $pRow);

            if (!in_array($passengerName, array_column($f->getTravellers(), 0))) {
                $f->general()->traveller($passengerName, true);
            }

            $ticket = $this->re("/ {$this->opt($this->t('E-TICKET'))}[ :]+({$this->patterns['eTicket']})$/m", $pRow);

            if ($ticket && !in_array($ticket, array_column($f->getTicketNumbers(), 0))) {
                $f->issued()->ticket($ticket, false, $passengerName);
                $this->tickets[] = $ticket;
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('class'))}[ :]+([^:\s][^:]*?)(?:[ ]{2}|[ ]+{$this->opt($this->t('SEAT'))}|$)/m", $pRow, $m)) {
                $cabinValues[] = $m[1];
            }

            if (preg_match("/ {$this->opt($this->t('SEAT'))}[ :]+([^:\s][^:]*)$/m", $pRow, $m)) {
                $seg->extra()->seat($m[1], false, false, $passengerName);
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('MEAL'))}[ :]+([^:\s][^:]*?)(?:[ ]{2}|[ ]+{$this->opt($this->t('ffNumber'))}|$)/m", $pRow, $m)) {
                $mealValues[] = $m[1];
            }

            if (preg_match("/ ({$this->opt($this->t('ffNumber'))})[ :]+([^:\s][^:]*)$/m", $pRow, $m)
                && !in_array($m[2], $this->accounts)
            ) {
                $f->program()->account($m[2], false, $passengerName, $m[1]);
            }
        }

        if (count(array_unique($cabinValues)) === 1) {
            $seg->extra()->cabin($cabinValues[0]);
        }

        if (count($mealValues) > 0) {
            $seg->extra()->meals(array_unique($mealValues));
        }
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

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['FLIGHT TO:']) || empty($phrases['BOOKING DETAILS'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['FLIGHT TO:']) !== false
                && $this->strposArray($text, $phrases['BOOKING DETAILS']) !== false
            ) {
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

    private function normalizeDate(?string $str): string
    {
        $in = [
            "/^[-[:alpha:]]+\s+([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})$/u", // Sat May 09, 2020
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re(string $re, ?string $str, $c = 1): ?string
    {
        if (preg_match($re, $str ?? '', $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field, $delimiter = '/'): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
