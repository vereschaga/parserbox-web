<?php

namespace AwardWallet\Engine\israel\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass2PDF extends \TAccountChecker
{
    public $mailFiles = "israel/it-786366415.eml, israel/it-792707548-he.eml, israel/it-795133506.eml, israel/it-794733787-he.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'BOARDING PASS' => ['BOARDING PASS', 'Boarding Pass'],
            'Flight'        => 'Flight',
            'Departure'     => 'Departure',
            'flightEnd' => ['Additional Information', 'Important Note', 'At the airport'],
        ],
        'he' => [
            'BOARDING PASS' => 'כרטיס עלייה למטוס',
            'Flight'        => 'Flight',
            'Departure'     => 'Departure',
            'flightEnd' => ['מידע נוסף', 'בשדה התעופה'],
        ],
    ];

    private $detectFrom = "@elal.co.il";
    private $detectSubject = [
        // en
        'Boarding Pass',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProv = $this->detectEmailFromProvider( rtrim($parser->getHeader('from'), '> ') ) === true;
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            // detect provider
            if (!$detectProv && !$this->containsText($textPdf, ['www.elal.com'])) {
                continue;
            }

            // detect format
            if ($this->detectPdf($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text): bool
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['BOARDING PASS'])
                && $this->containsText($text, $dict['BOARDING PASS']) === true
                && !empty($dict['Flight'])
                && $this->containsText($text, $dict['Flight']) === true
                && !empty($dict['Departure'])
                && $this->containsText($text, $dict['Departure']) === true
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($textPdf)) {
                $this->parsePdf($email, $textPdf);
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

    private function parsePdf(Email $email, ?string $textPdf = null): void
    {
        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        if ($this->lang === 'he') {
            $textPdf = str_replace([html_entity_decode("&#8234;"), html_entity_decode("&#8235;"), html_entity_decode("&#8236;")], '', $textPdf);
        }

        $f = $email->add()->flight();
        $travellers = $infants = $accounts = [];

        // General
        $f->general()
            ->noConfirmation();

        $bps = $this->split("/\n[ ]*({$this->opt($this->t('BOARDING PASS'))}\n)/u", "\n\n" . $textPdf);

        foreach ($bps as $bpText) {
            $bpText = preg_replace([
                "/^(.+?)\n+[ ]*(?:SEQ NUMBER[ ]*:|{$this->opt($this->t('flightEnd'))}).*$/is",
                "/[ ]+SEQ(?:[ ]*NUMBER)?[ ]*:.*/i",
            ], [
                '$1',
                '',
            ], $bpText);

            $passengerText = $this->re("/^\s*{$this->opt($this->t('BOARDING PASS'))}\n+[ ]*([\s\S]+?)\n+[ ]*(?:{$this->opt($this->t('Flight'))}[: ]+{$this->opt($this->t('From'))}|{$this->opt($this->t('From'))}[: ]+{$this->opt($this->t('To'))})[: ]/i", $bpText);

            if (preg_match("/^\s*(?<passenger>[\s\S]+?)\n+[ ]*(?<account>.*\d.*|FQTV[ ]*-.*)$/i", $passengerText, $m)) {
                $passengerText = preg_replace('/\s+/', ' ', $m['passenger']);
                $account = preg_replace('/^FQTV\s*-\s*/i', '', $m['account']);
            } else {
                $passengerText = preg_replace('/\s+/', ' ', $passengerText);
                $account = null;
            }

            $passengerName = preg_match("/^{$patterns['travellerName']}$/u", $passengerText) ? $this->normalizeTraveller($passengerText) : null;
            $isInfant = false;

            if ($account && !in_array($account, $accounts)) {
                $f->program()->account($account, false, $passengerName);
                $accounts[] = $account;
            }

            $s = $f->addSegment();

            $table1Text = $this->re("/\n([ ]{0,10}{$this->opt($this->t('Flight'))}[: ]{3,}\S.+\n[\S\s]+?)\n+[ ]{0,10}{$this->opt($this->t('Departure'))}[: ]/i", $bpText);
            $table1 = $this->createTable($table1Text);

            $table2Text = $this->re("/\n([ ]{0,10}{$this->opt($this->t('Departure'))}[: ]{3,}\S.+\n[\S\s]+?)(?:\n{3}|$)/i", $bpText);
            $table2 = $this->createTable($table2Text);

            if (count($table1) === 0 && count($table2) === 0) {
                // getting and transforming tables type-2 (examples: it-795133506.eml, it-794733787-he.eml)

                $table1Text = $this->re("/\n([ ]{0,10}{$this->opt($this->t('From'))}[: ]{3,}\S.+\n[\S\s]+?)\n+[ ]{0,10}{$this->opt($this->t('Class'))}[: ]/i", $bpText);
                $table1_temp = $this->createTable($table1Text);

                $table2Text = $this->re("/\n([ ]{0,10}{$this->opt($this->t('Class'))}[: ]{3,}\S.+\n[\S\s]+?)(?:\n{3}|$)/i", $bpText);
                $table2_temp = $this->createTable($table2Text);

                if (count($table1_temp) === 5 && count($table2_temp) > 0) {
                    $table1 = [$table1_temp[2], $table1_temp[0], $table1_temp[1]];
                    $table2 = [$table1_temp[3], $table2_temp[1], $table2_temp[0], $table1_temp[4]];
                }
            }

            if (count($table1) > 1 && preg_match("/^(\s*{$this->opt($this->t('Flight'))}[:\s]+(?-i)(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+)[ ]+(?<fromText>\S.*)([\s\S]*)$/i", $table1[0], $m)) {
                $this->logger->debug('Columns `Flight` and `From` is wrong! Fixing...');
                $table1[0] = $m[1] . $m[3];
                $table1[1] = preg_replace("/^(\s*{$this->opt($this->t('From'))}[: ]*\n[ ]*)([\s\S]*)$/", '$1' . $m['fromText'] . '$2', $table1[1]);
            }

            if (count($table1) > 0 && preg_match("/^\s*{$this->opt($this->t('Flight'))}[:\s]+(?-i)(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<fn>\d+)\s*$/i", $table1[0], $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            }

            $patternAirport = "(?<code>[A-Z]{3})(?:\n+[ ]*(?<terminal>\S.+?))?";

            if (count($table1) > 1 && preg_match("/^\s*{$this->opt($this->t('From'))}[:\s]+{$patternAirport}\s*$/s", $table1[1], $m)) {
                $s->departure()->code($m['code']);

                if (!empty($m['terminal'])) {
                    $terminalDep = trim(preg_replace("/\s*\bTerminal\b\s*/i", ' ', $m['terminal']));
                    $s->departure()->terminal($terminalDep === '' ? null : $terminalDep, false, true);
                }
            }

            if (count($table1) > 2 && preg_match("/^\s*{$this->opt($this->t('To'))}[:\s]+{$patternAirport}\s*$/s", $table1[2], $m)) {
                $s->arrival()->code($m['code']);

                if (!empty($m['terminal'])) {
                    $terminalArr = trim(preg_replace("/\s*\bTerminal\b\s*/i", ' ', $m['terminal']));
                    $s->arrival()->terminal($terminalArr === '' ? null : $terminalArr, false, true);
                }
            }

            $dateDep = $timeDep = null;

            if (count($table2) > 0 && preg_match("/^\s*{$this->opt($this->t('Departure'))}[:\s]+(?<time>{$patterns['time']}).*\n+[ ]*(?<date>[\s\S]{4,}?)\s*$/", $table2[0], $m)) {
                $timeDep = $m['time'];
                $m['date'] = preg_replace('/\s+/', ' ', $m['date']);

                if (preg_match("/^\d{1,2}[-,.\s]+[[:alpha:]]+[-,.\s]+\d{2,4}$/u", $m['date'])) {
                    $dateDep = strtotime($m['date']);
                }
            }

            if ($dateDep && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep));
                $s->arrival()->noDate();
            }

            if (count($table2) > 2 && preg_match("/^\s*{$this->opt($this->t('Class'))}[:\s]+(.+)/", $table2[2], $m)) {
                if (count($table2) > 1 && preg_match("/^\s*{$this->opt($this->t('Zone'))}[:\s]*$/", $table2[1])
                    && preg_match($pattern = '/(\S) [A-Z\d]$/m', $m[1])
                ) {
                    $this->logger->debug('Columns `Zone` and `Class` is wrong! Fixing...');
                    $m[1] = preg_replace($pattern, '$1', $m[1]); // remove garbage
                }

                $s->extra()->cabin($m[1]);
            }

            if (count($table2) > 3 && preg_match("/^\s*{$this->opt($this->t('Seat'))}[:\s]+([^:\s].*?)\s*$/is", $table2[3], $m)) {
                if (preg_match("/^(\d+[A-Z])(?:\n|$)/", $m[1], $m2)) {
                    // TODO: $s->extra()->seat($m2[1], false, false, $passengerName);
                    $s->extra()->seat($m2[1]);
                }

                if (preg_match("/(?:^|\n)INF\s*$/i", $m[1])) {
                    $isInfant = true;
                }
            }

            if ($passengerName) {
                if ($isInfant && !in_array($passengerName, $infants)) {
                    $f->general()->infant($passengerName, true);
                    $infants[] = $passengerName;
                } elseif (!in_array($passengerName, $travellers)) {
                    $f->general()->traveller($passengerName, true);
                    $travellers[] = $passengerName;
                }
            }

            $segments = $f->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (serialize(array_diff_key($segment->toArray(),
                            ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                        if (!empty($s->getSeats())) {
                            $segment->extra()->seats(array_unique(array_merge($segment->getSeats(),
                                $s->getSeats())));
                        }
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
        }
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MSTR|MISS|MRS|CHD|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
            '/^([^\/]+?)(?:\s*[\/]+\s*)+([^\/]+)$/',
        ], [
            '$1',
            '$1',
            '$2 $1',
        ], $s);
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re(string $re, ?string $str, $c = 1): ?string
    {
        if (preg_match($re, $str ?? '', $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (mb_strpos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && mb_strpos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    // additional methods

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
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

    private function split($re, $text): array
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
