<?php

namespace AwardWallet\Engine\msccruises\Email;

use AwardWallet\Schema\Parser\Common\Cruise;
use AwardWallet\Schema\Parser\Email\Email;

class CruiseTicketPdf extends \TAccountChecker
{
    public $mailFiles = "msccruises/it-698290014.eml, msccruises/it-706584565.eml";

    public $dateFormat = ''; // 'mdy' or 'dmy'

    public $detectSubjects = [
        // en, es, pt, de
        'Eticket - ',
    ];

    public $detectBody = [
        'en' => ['THIS FORM MUST BE PRINTED AND PRESENTED AT EMBARKATION'],
        'es' => ['ESTE FORMULARIO DEBE IMPRIMIRSE Y PRESENTARSE EN EL EMBARQUE'],
        'pt' => ['ESTE FORMULÁRIO DEVE SER APRESENTADO IMPRESSO NO EMBARQUE'],
        'de' => ['DIESES FORMULAR MUSS AUSGEDRUCKT UND BEI DER EINSCHIFFUNG VORGELEGT'],
    ];

    public $lang = '';
    public static $dictionary = [
        'en' => [
            // 'BOOKING NUMBER' => '',
            // 'Ship' => '',
            // 'Cabin' => '',
            // 'Cabin Type' => '',
            // 'First Name' => '',
            // 'Last Name' => '',
            // 'YOUR CRUISE' => '',
            // 'Day' => '',
            // 'Port' => '',
            'Arrival and departure times' => ['Arrival and departure times', 'Arrival and departure timings'],
            // 'LUGGAGE TAGS' => '',
            // 'Deck' => '',
        ],
        'es' => [
            'BOOKING NUMBER'              => 'NÚMERO DE RESERVA',
            'Ship'                        => 'Barco',
            'Cabin'                       => 'Camarote',
            'Cabin Type'                  => 'Tipo de Camarote',
            'First Name'                  => 'Nombre',
            'Last Name'                   => 'Apellido',
            'YOUR CRUISE'                 => 'TU CRUCERO',
            'Day'                         => 'Día',
            'Port'                        => 'Puerto',
            'Arrival and departure times' => 'Los horarios de llegada y salida',
            'LUGGAGE TAGS'                => 'ETIQUETAS DE EQUIPAJE',
            'Deck'                        => 'Deck',
        ],
        'pt' => [
            'BOOKING NUMBER'              => 'NÚMERO DA RESERVA',
            'Ship'                        => 'Navio',
            'Cabin'                       => 'Cabine',
            'Cabin Type'                  => 'Categoria de cabine',
            'First Name'                  => 'Primeiro nome',
            'Last Name'                   => 'Último nome',
            'YOUR CRUISE'                 => 'SEU CRUZEIRO',
            'Day'                         => 'Dia',
            'Port'                        => 'Porto',
            'Arrival and departure times' => 'Os horários de chegada e partida',
            'LUGGAGE TAGS'                => 'ETIQUETAS DE BAGAGENS',
            'Deck'                        => 'Deck',
        ],
        'de' => [
            'BOOKING NUMBER'              => 'BUCHUNGSNUMMER',
            'Ship'                        => ['Schiff', 'Schiﬀ'],
            'Cabin'                       => 'Kabine',
            'Cabin Type'                  => 'Kabinentyp',
            'First Name'                  => 'Vorname',
            'Last Name'                   => 'Nachname',
            'YOUR CRUISE'                 => 'IHRE KREUZFAHRT',
            'Day'                         => 'Tag',
            'Port'                        => 'Hafen',
            'Arrival and departure times' => 'Die Ankunfts- und Abfahrtszeiten',
            'LUGGAGE TAGS'                => 'GEPÄCKANHÄNGER',
            'Deck'                        => 'Deck',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@msccrociere.it') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
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

            if (!$textPdf) {
                continue;
            }

            if ($this->detectPdf($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf(?string $text): bool
    {
        if (empty($text)) {
            return false;
        }

        if (stripos($text, 'MSC for Me App') === false
            && stripos($text, 'Shore Excursions with MSC') === false
            && stripos($text, 'MSC for Me') === false
            && stripos($text, '@msccruises.com') === false
            && stripos($text, 'LADEN SIE UNSERE "MSC') === false
        ) {
            return false;
        }

        foreach ($this->detectBody as $lang => $phrases) {
            if ($this->strposArray($text, $phrases) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($textPdf)) {
                $this->parsePdf($email, $textPdf);
            }
        }

        $email->setType('BookingConfirmationPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, $pdfText): void
    {
        $parts = preg_split("/\n {0,5}(?:{$this->opt($this->t('YOUR CRUISE'))} *\||{$this->opt($this->t('LUGGAGE TAGS'))})/u", $pdfText);

        $cr = $email->add()->cruise();

        // General
        $cr->general()
            ->confirmation($this->re("/\n *{$this->opt($this->t('BOOKING NUMBER'))} *(\d{5,})\s*\n/u", $parts[0] ?? ''))
        ;

        if (preg_match_all("/^.*({$this->opt($this->t('First Name'))}|{$this->opt($this->t('Last Name'))}).*$/um", $parts[0] ?? '', $m)) {
            $travelText = ['col1' => '', 'col2' => ''];

            foreach ($m[0] as $row) {
                if (preg_match("/(.{20,}) {3,}((?:{$this->opt($this->t('First Name'))}|{$this->opt($this->t('Last Name'))}).*)/u", $row, $mat)) {
                    $travelText['col1'] .= "\n" . trim($mat[1]);
                    $travelText['col2'] .= "\n" . trim($mat[2]);
                } else {
                    $travelText['col1'] .= "\n" . trim($row);
                }
            }
            $travelText = $travelText['col1'] . "\n" . $travelText['col2'];

            if (preg_match_all("/\n *{$this->opt($this->t('First Name'))} *(.+)\n\s*{$this->opt($this->t('Last Name'))} +(.+)/u", "\n" . $travelText, $trM)) {
                foreach ($trM[0] as $i => $v) {
                    $cr->general()
                        ->traveller($trM[1][$i] . ' ' . $trM[2][$i], true);
                }
            }
        }

        // Details
        $cr->details()
            ->ship($this->re("/\n[ ]*{$this->opt($this->t('Ship'))} *(.+?)(?:[ ]{2}|\n)/u", $parts[0] ?? ''))
            ->room($this->re("/\n[ ]*{$this->opt($this->t('Cabin'))} *(.+?)(?:[ ]{2}|\n)/u", $parts[0] ?? ''))
            ->roomClass($this->re("/\n[ ]*{$this->opt($this->t('Cabin Type'))} *(.+?)(?:[ ]{2}|\n)/u", $parts[0] ?? ''))
        ;

        if (!empty($cr->getRoom())
            && preg_match("/\n *{$this->opt($this->t('Deck'))}\n\s*{$cr->getRoom()} *(\w{1,4})\n/u", $parts[2] ?? '', $m)
        ) {
            $cr->details()
                ->deck($m[1]);
        }

        // Segments
        $segmentsText = $this->re("/\n( *{$this->opt($this->t('Day'))} +{$this->opt($this->t('Port'))} +[\s\S]+)\n *{$this->opt($this->t('Arrival and departure times'))}/u", $parts[1] ?? '');

        $tablePos = $this->columnPositions($this->inOneRow($segmentsText));

        if (isset($tablePos[4])) {
            $table = $this->createTable($segmentsText, [0, $tablePos[4]], false);
            $segmentsText = $table[0];
        }

        // detect date format
        if (preg_match_all("/^.{0,7}\b(\d{2})\/(\d{2})\/(\d{2})\b/mu", $segmentsText, $m)
            && count($m[0]) > 1
        ) {
            if ($m[3][0] === $m[3][1]) { // year
                if ($m[1][0] === $m[1][1] && ((int) $m[2][0] + 1) == (int) $m[2][1]) {
                    $this->dateFormat = 'mdy';
                } elseif ($m[2][0] === $m[2][1] && ((int) $m[1][0] + 1) == (int) $m[1][1]) {
                    $this->dateFormat = 'dmy';
                }
            }
        }

        $rows = $this->split("/\n( {0,5}\S)/", $segmentsText);
        $emptyTime = '--:--';

        foreach ($rows as $i => $row) {
            $values = [];

            if (preg_match("/^ *(.*\d+\/\d+\/\d+) +(.+?) +(\d{1,2}:\d{2}.*?|--:--) +(\d{1,2}:\d{2}.*?|--:--)(\n\s*[\s\S]+)?\s*$/u", $row, $m)) {
                $values['date'] = $m[1];
                $values['name'] = $m[2] . ' ' . ($m[5] ?? '');
                $values['time1'] = $m[3];
                $values['time2'] = $m[4];
            } else {
                $cr->addSegment();

                break;
            }
            $values = preg_replace('/\s*\n\s*/', ' ', array_map('trim', $values));

            if ($values['time1'] == $emptyTime && $values['time2'] == $emptyTime) {
                continue;
            }

            if ($values['time1'] == $emptyTime) {
                $values['time1'] = null;
            }

            if ($values['time2'] == $emptyTime) {
                $values['time2'] = null;
            }

            if (empty($values['time1']) && isset($segment) && empty($segment->getAboard()) && $segment->getName() === $values['name']) {
                $segment->setAboard($this->normalizeDate($values['date'] . ', ' . $values['time2']));
            }

            $segment = $cr->addSegment();
            $segment
                ->setName($values['name']);

            if (!empty($values['time1'])) {
                $segment->setAshore($this->normalizeDate($values['date'] . ', ' . $values['time1']));
            }

            if (!empty($values['time2'])) {
                $segment->setAboard($this->normalizeDate($values['date'] . ', ' . $values['time2']));
            }
        }

        return;
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
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

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
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

    private function normalizeDate(?string $text)
    {
        if (!is_string($text) || empty($text)) {
            return null;
        }

        if (preg_match('/^\s*[[:alpha:]]+\s+(\d{2})\/(\d{2})\/(\d{2})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/ui', $text, $m)) {
            if ($this->dateFormat === 'dmy') {
                return strtotime($m[1] . '.' . $m[2] . '.20' . $m[3] . ', ' . $m[4]);
            } elseif ($this->dateFormat === 'mdy') {
                return strtotime($m[2] . '.' . $m[1] . '.20' . $m[3] . ', ' . $m[4]);
            }
        }

        return null;
    }

    private function columnPositions($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColumnPositions($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function createTable(?string $text, $pos = [], $trim = true): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $v = mb_substr($row, $p, null, 'UTF-8');

                if ($trim) {
                    $cols[$k][] = trim($v);
                } else {
                    $cols[$k][] = $v;
                }
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function split($re, $text, $shiftFirst = true)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            if ($shiftFirst == true || ($shiftFirst == false && empty($r[0]))) {
                array_shift($r);
            }

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
