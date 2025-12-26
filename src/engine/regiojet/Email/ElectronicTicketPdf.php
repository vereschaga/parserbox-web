<?php

namespace AwardWallet\Engine\regiojet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ElectronicTicketPdf extends \TAccountChecker
{
    public $mailFiles = ""; // the same as ElectronicTicket

    public $pdfNamePattern = ".*\.pdf";

    public $operators = [
        'train' => [
            'RJ - RegioJet a.s.',
            'Ukz - Ukrzaliznycja',
            'OBB - Österreichische Bundesbahnen',
        ],
        'bus' => [
            'SA - STUDENT AGENCY k.s.',
        ],
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
            'Electronic ticket' => 'Electronic ticket',
            // 'Passengers:' => '',
            'Station/Transfer' => 'Station/Transfer',
            // 'Connection' => '',
            // 'Price:' => '',
            // 'Operators:' => '',
        ],
        'cs' => [
            'Electronic ticket' => 'Elektronická jízdenka č.',
            // 'Passengers:' => '',
            'Station/Transfer' => 'Zastávka/Přestup',
            'Connection'       => 'Spoj',
            'Price:'           => 'Cena:',
            'Operators:'       => 'Dopravci:',
        ],
        'de' => [
            'Electronic ticket' => 'Elektronischer Beförderungsausweis',
            'Passengers:'       => 'Fahrgäste:',
            'Station/Transfer'  => 'Haltestelle/Transfer',
            'Connection'        => 'Bus/Zug',
            'Price:'            => 'Preis:',
            'Operators:'        => 'Beförderer:',
        ],
        'sk' => [
            'Electronic ticket' => 'Elektronický lístok',
            // 'Passengers:' => ':',
            'Station/Transfer' => 'Zastávka/Prestup',
            'Connection'       => 'Spoj',
            'Price:'           => 'Cena:',
            'Operators:'       => 'Dopravcovia:',
        ],
        'uk' => [
            'Electronic ticket' => 'Електронний квиток №',
            'Passengers:'       => 'Пасажири:',
            'Station/Transfer'  => 'Зупинка/пересадка',
            'Connection'        => 'Автобус/потяг',
            'Price:'            => 'Ціна:',
            'Operators:'        => 'Перевізники:',
        ],
    ];

    private $detectFrom = "@regiojet.cz";
    private $detectSubject = [
        // en
        'RegioJet: Electronic ticket',
        // cs
        'RegioJet: Elektronická jízdenka',
        // de
        'RegioJet: Elektronischer Beförderungsausweis',
        // sk
        'RegioJet: Elektronický lístok',
        // uk
        'RegioJet: Електронний квиток',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]@regiojet\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'RegioJet') === false
        ) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text)
    {
        // detect provider
        if ($this->containsText($text, ['www.regiojet.cz']) === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Electronic ticket'])
                && $this->containsText($text, $dict['Electronic ticket']) === true
                && !empty($dict['Station/Transfer'])
                && $this->containsText($text, $dict['Station/Transfer']) === true
                && $this->http->XPath->query("//tr[*[{$this->eq($this->t('Station/Transfer'))}] and *[{$this->eq($this->t('Connection'))}]]")->length === 0
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
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                $this->parseEmailPdf($email, $text);
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

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        // Segments
        $operatorsText = $this->re("/\n *{$this->opt($this->t('Operators:'))}\n+((?: {10,}.+\n+)+)/u", $textPdf);
        $allOperators = preg_split("/\n+/", trim($operatorsText));
        $allOperators = preg_replace("/^\s*(.+?),.+/", '$1', $allOperators);
        $operators = [];

        foreach ($allOperators as $name) {
            if (in_array($name, $this->operators['train'])) {
                $operators[trim(strstr($name, '-', true))] = 'train';
            } elseif (in_array($name, $this->operators['bus'])) {
                $operators[trim(strstr($name, '-', true))] = 'bus';
            }
        }

        $segmentsText = $this->re("/{$this->opt($this->t('Station/Transfer'))}.+{$this->opt($this->t('Connection'))}.*(\n[\s\S]+?)\n *{$this->opt($this->t('Price:'))}/u", $textPdf);
        $headerText = $this->re("/\n(.+{$this->opt($this->t('Station/Transfer'))}.+{$this->opt($this->t('Connection'))}.*\n[\s\S]+?)\n *{$this->opt($this->t('Price:'))}/u", $textPdf);
        $headerPos = $this->columnPositions($this->inOneRow($headerText));

        $rows = $this->split("/^((?: {0,10}\S.*(?:\n.*)?)? {2,}\d{1,2}:\d{2}(?: ?[ap]m)?(?: {2,}|\n|$))/mi", "\n" . $segmentsText);
        $segments = [];

        foreach ($rows as $rText) {
            $table = $this->createTable($rText, $headerPos);

            $table = array_map('trim', $table);
            $table = preg_replace('/\s+/', ' ', $table);

            if (!empty($table[2])) {
                if (isset($seg) && empty($seg['arrTime'])) {
                    $seg = array_merge($seg, [
                        'arrDate'    => empty($table[0]) ? null : $table[0],
                        'arrStation' => $table[1],
                        'arrTime'    => $table[2],
                    ]);
                } else {
                    $seg = [];
                }

                $segments[] = $seg;
                $seg = [];
            }

            if (!empty($table[3])) {
                $seg = [
                    'depDate'    => $table[0],
                    'depStation' => $table[1],
                    'depTime'    => $table[3],
                    'arrDate'    => null,
                    'arrStation' => null,
                    'arrTime'    => null,
                    'info'       => $table[5],
                    'seats'      => $table[6],
                    'type'       => null,
                ];

                // Type
                $opCode = $this->re("/\(([[:alpha:]]+)\s*(?:,|\))/", $seg['info']);

                if (isset($operators[$opCode])) {
                    $seg['type'] = $operators[$opCode];
                } elseif (preg_match("/^\s*\d+\s*\\/\s*\d+\s*(,|$)/", $seg['seats'])) {
                    $seg['type'] = 'train';
                } elseif (preg_match("/^\s*\d+\s*(,|$)/", $seg['seats'])) {
                    $seg['type'] = 'bus';
                }
            }
        }

        foreach ($segments as $seg) {
            if (empty($seg['type'])) {
                $this->logger->debug('not detect Segment Type');
                $email->add()->flight();

                break;
            } elseif ($seg['type'] == 'train') {
                if (!isset($trains)) {
                    $trains = $email->add()->train();
                }
                $s = $trains->addSegment();

                // Extra
                $seats = array_filter(preg_split("/\s*,\s*/", $seg['seats']));
                $car = $seat = [];

                foreach ($seats as $stext) {
                    $car[] = $this->re('/^\s*(\d+)\s*\\/\s*\d+\s*$/', $stext);
                    $seat[] = $this->re('/^\s*\d+\s*\\/\s*(\d+)\s*$/', $stext);
                }

                if (!empty(array_filter($car)) && !empty(array_filter($seat))) {
                    $s->extra()
                        ->car(implode(', ', array_unique($car)))
                        ->seats($seat);
                }
            } elseif ($seg['type'] == 'bus') {
                if (!isset($buses)) {
                    $buses = $email->add()->bus();
                }
                $s = $buses->addSegment();

                // Extra
                $seats = array_filter(preg_split("/\s*,\s*/", $seg['seats']));

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            // Departure
            $s->departure()
                ->name($seg['depStation'])
                ->geoTip('europe')
                ->date($this->normalizeDate($seg['depDate'] . ', ' . $seg['depTime']))
            ;

            $seg['arrDate'] = $seg['arrDate'] ?? $seg['depDate'];

            // Arrival
            $s->arrival()
                ->name($seg['arrStation'])
                ->geoTip('europe')
                ->date($this->normalizeDate($seg['arrDate'] . ', ' . $seg['arrTime']))
            ;

            // Extra
            $number = $this->re("/\([[:alpha:]]+\s*,\s*[[:alpha:]]+\s+(\d.+)\)/", $seg['info']);

            if (!empty($number)) {
                $s->extra()
                    ->number($number);
            }
        }
        // General
        $confNo = $this->re("/^\s*{$this->opt($this->t('Electronic ticket'))} *(\d{5,})\s+/u", $textPdf);
        $confName = $this->re("/^\s*({$this->opt($this->t('Electronic ticket'))}) *\d{5,}\s+/u", $textPdf);
        $travellers = array_filter(preg_split('/\s*,\s*/',
            $this->re("/\n *{$this->opt($this->t('Passengers:'))} *(.+)/u", $textPdf)));

        if (isset($trains)) {
            $trains->general()
                ->confirmation($confNo, $confName);

            if (!empty($travellers)) {
                $trains->general()
                    ->travellers($travellers, true);
            }
        }

        if (isset($buses)) {
            $buses->general()
                ->confirmation($confNo, $confName);

            if (!empty($travellers)) {
                $buses->general()
                    ->travellers($travellers, true);
            }
        }

        // Price
        $price = $this->re("/\n *{$this->opt($this->t('Price:'))} *(.+)/u", $textPdf);

        if (preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $price, $m)
        ) {
            $m['amount'] = PriceHelper::parse($m['amount'], $m['currency']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $email->price()
                ->total($m['amount'])
                ->currency($m['currency'])
            ;
        } else {
            $email->price()
                ->total(null);
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
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
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    // additional methods

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

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $pos = [];
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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        $in = [
            // Sat 8/19/23, 8:30 PM
            '/^\s*[[:alpha:]]+\s+(\d{1,2}) ?\\/ ?(\d{1,2}) ?\\/ ?(\d{2})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
            // pá 01.09.23, 6:12
            // Di. 05.09.23, 06:12
            // po 11. 9. 2023, 5:57
            '/^\s*[[:alpha:]]+[.]?\s+(\d{1,2})\. ?(\d{1,2})\. ?(\d{2})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
            // po 11. 9. 2023, 5:57
            '/^\s*[[:alpha:]]+[.]?\s+(\d{1,2})\. ?(\d{1,2})\. ?(\d{4})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$2.$1.20$3, $4',
            '$1.$2.20$3, $4',
            '$1.$2.$3, $4',
        ];

        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function split($re, $text)
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }
}
