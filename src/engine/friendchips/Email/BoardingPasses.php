<?php

namespace AwardWallet\Engine\friendchips\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BoardingPasses extends \TAccountChecker
{
    public $mailFiles = "friendchips/it-324807577.eml, friendchips/it-796339482.eml, friendchips/it-789323373-nl.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang = '';
    public static $dictionary = [
        'nl' => [
            'FROM'               => 'VAN',
            'TO'                 => 'NAAR',
            'YOUR BOARDING PASS' => 'JOUW BOARDING PASS',
            'flightEnd'          => ['U HEEFT INGECHECKT, WAT NU'],
            'TRAVEL DATE'        => 'VLUCHTDATUM',
            'FLIGHT DEPARTS'     => 'VERTREKTIJD',
            'FLIGHT NUMBER'      => 'VLUCHTNUMMER',
            'PASSENGER'          => 'PASSAGIER',
            'SEAT NUMBER'        => 'STOELNUMMER',
            'BOOKING NUMBER'     => 'RESERVERINGSNUMMER',
            // 'CABIN' => '',
        ],
        'en' => [
            'FROM' => 'FROM',
            'TO'   => 'TO',
            // 'YOUR BOARDING PASS' => '',
            'flightEnd' => [
                "YOU'VE CHECKED-IN - WHAT NEXT",
                "YOU'VE CHECKED IN - WHAT'S NEXT",
                'YOU’VE CHECKED IN - WHAT’S NEXT',
            ],
            'TRAVEL DATE'    => ['TRAVEL DATE', 'FLIGHT DATE'],
            'FLIGHT DEPARTS' => ['FLIGHT DEPARTS', 'DEPARTURE TIME'],
            // 'FLIGHT NUMBER' => '',
            // 'PASSENGER' => '',
            // 'SEAT NUMBER' => '',
            // 'BOOKING NUMBER' => '',
            // 'CABIN' => '',
        ],
        'de' => [
            'FROM'               => 'VON',
            'TO'                 => 'NACH',
            'YOUR BOARDING PASS' => 'IHRE BORDKARTE',
            'flightEnd'          => ['SIE HABEN EINGECHECKT'],
            'TRAVEL DATE'        => 'FLUGDATUM',
            'FLIGHT DEPARTS'     => 'ABFLUG',
            'FLIGHT NUMBER'      => 'FLUG-NR.',
            'PASSENGER'          => 'PASSAGIER',
            'SEAT NUMBER'        => 'SITZPLATZ',
            'BOOKING NUMBER'     => 'BUCHUNGSNUMMER',
            // 'CABIN' => '',
        ],
    ];

    private $detectSubject = [
        'Your TUI Airways boarding passes for booking', 'Boarding pass TUI fly Services',
    ];

    private $otaConfNumbers = [];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tui.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || !preg_match('/\b(?:TUI Airways|TUI fly)\b/', $headers['subject']))
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
        $detectProvider = $this->detectEmailFromProvider($parser->getCleanFrom()) === true
            || preg_match('/\b(?:TUI Airways|TUI fly)\b/', $parser->getSubject()) > 0;

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || !$detectProvider && strpos($textPdf, 'TUI') === false) {
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
        if (preg_match("/Your TUI Airways boarding passes for booking\s+(\d{5,})\s*$/", $parser->getSubject(), $m)
            || preg_match("/Check-in confirmation for booking\s+(\d{5,})\s*$/", $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Check-in confirmation for booking')]") ?? '', $m)
        ) {
            if (!in_array($m[1], $this->otaConfNumbers)) {
                $email->ota()->confirmation($m[1]);
                $this->otaConfNumbers[] = $m[1];
            }
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        /* Step 1: find supported formats */

        $usingLangs = $textsBP = [];

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $usingLangs[] = $this->lang;
                $fileName = $this->getAttachmentName($parser, $pdf);
                $pdfParts = $this->split("/^([ ]*{$this->opt($this->t('YOUR BOARDING PASS'))})$/m", $textPdf);

                foreach ($pdfParts as $partText) {
                    $textsBP[] = [
                        'lang'     => $this->lang,
                        'text'     => $partText,
                        'filename' => $fileName,
                    ];
                }

                if (preg_match('/^BoardingPass-(\d{5,})\.pdf/i', $fileName, $m)
                    && !in_array($m[1], $this->otaConfNumbers)
                ) {
                    $email->ota()->confirmation($m[1]);
                    $this->otaConfNumbers[] = $m[1];
                }
            }
        }

        /* Step 2: parsing */

        $this->parsePdf($email, $textsBP);

        if (count(array_unique($usingLangs)) === 1
            || count(array_unique(array_filter($usingLangs, function ($item) { return $item !== 'en'; }))) === 1
        ) {
            $email->setType('BoardingPasses' . ucfirst($usingLangs[0]));
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
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['FROM']) || empty($phrases['TO'])) {
                continue;
            }

            if (preg_match("/^.* {$this->opt($phrases['FROM'])}[: ]+{$this->opt($phrases['TO'])}[: ]*(?:[ ]{2}|$)/im", $text)) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parsePdf(Email $email, array $textsBP): void
    {
        $f = $email->add()->flight();
        $segObjects = $travellers = $infants = $bookingNoValues = [];

        foreach ($textsBP as $textBP) {
            $this->lang = $textBP['lang'];
            $text = preg_replace([
                "/^(.+?)\n+[ ]*{$this->opt($this->t('flightEnd'))}.*$/is",
                "/[ ]+boarding sequence number.{0,12}$/im",
                "/[ ]+Infant\s+Ticket$/im",
                "/[ ]{5,}Infant(\n.*) {5,}Ticket\n/i",
            ], [
                '$1',
                '',
                '',
                "\n" . '$1' . "\n",
            ], $textBP['text']);

            /* Step 1: get values */

            $it = [
                'dateDep'   => 0,
                'airline'   => null, 'flightNumber' => null,
                'nameDep'   => null, 'nameArr' => null,
                'traveller' => null, 'isInfant' => false,
                'seat'      => null, 'pnr' => null, 'cabin' => null,
            ];

            // table 1

            $table1Text = $this->re("/(?:^|\n)([ ]*{$this->opt($this->t('TRAVEL DATE'))}[:\s][\s\S]+?)(?:\n{4}|$)/", $text);
            $table1Pos = [0];

            if (preg_match("/^(.+ ){$this->opt($this->t('FLIGHT NUMBER'))}[:\s]/m", $table1Text, $matches)) {
                $table1Pos[] = mb_strlen($matches[1]);
            }

            $table1 = $this->createTable($table1Text, $table1Pos);

            // table 2

            $table2Text = count($table1) > 1 && preg_match("/^([\s\S]+?)\n+[ ]*{$this->opt($this->t('PASSENGER'))}[:\s]/", $table1[1], $m) ? $m[1] : '';

            $table2Pos = [0];
            $pos2List = [];

            if (preg_match("/^(.+? ){$this->opt($this->t('SEAT NUMBER'))}[: ]*$/m", $table2Text, $matches)) {
                $pos2List[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^([ ]*{$this->opt($this->t('FROM'))}[: ]+){$this->opt($this->t('TO'))}[: ]*$/m", $table2Text, $matches)) {
                $pos2List[] = mb_strlen($matches[1]);
            }

            if (count($pos2List) > 0) {
                sort($pos2List);
                $table2Pos[] = $pos2List[0];
            }

            $table2 = $this->createTable($table2Text, $table2Pos);

            // table 3

            $table3Text = count($table1) > 1 && preg_match("/\n([ ]*{$this->opt($this->t('PASSENGER'))}[:\s][\s\S]+?)\n+[ ]*{$this->opt($this->t('BOOKING NUMBER'))}[:\s]/", $table1[1], $m) ? $m[1] : '';

            $table3Pos = [0];

            if (preg_match("/^(.+? ){$this->opt($this->t('SEAT NUMBER'))}[: ]*$/m", $table3Text, $matches)) {
                $table3Pos[] = mb_strlen($matches[1]);
            }

            $table3 = $this->createTable($table3Text, $table3Pos);

            // using tables

            if (count($table2) > 0 && preg_match("/{$this->opt($this->t('FLIGHT NUMBER'))}[:\s]+(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]|TOM)[ ]*(?<fn>\d+)$/m", $table2[0], $m)) {
                $it['airline'] = $m['al'] === 'TOM' ? 'BY' : $m['al'];
                $it['flightNumber'] = $m['fn'];
            }

            $bookingNoVal = count($table1) > 1 && preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('BOOKING NUMBER'))}[: ]+([^:\s].*?)[ ]*$/m", $table1[1], $m) ? $m[1] : '';

            if (preg_match("/^[\/\s]*([A-Z\d]{5,8})[\/\s]*$/", $bookingNoVal, $m)) {
                $it['pnr'] = $m[1];
            } elseif (preg_match("/^(\d{5,})\s*\/\s*([A-Z\d]{5,8})$/", $bookingNoVal, $m)) {
                if (!in_array($m[1], $this->otaConfNumbers)) {
                    $email->ota()->confirmation($m[1]);
                    $this->otaConfNumbers[] = $m[1];
                }
                $it['pnr'] = $m[2];
            }

            $dateDep = $timeDep = null;

            if (count($table1) > 0 && preg_match("/^[ ]*{$this->opt($this->t('TRAVEL DATE'))}[:\s]+([^:\s].+)/m", $table1[0], $m)) {
                $dateDep = strtotime($this->normalizeDate($m[1]));
            }

            if (count($table1) > 0 && preg_match("/^[ ]*{$this->opt($this->t('FLIGHT DEPARTS'))}[:\s]+([^:\s].+)/m", $table1[0], $m)) {
                $timeDep = $m[1];
            }

            if ($dateDep && $timeDep) {
                $it['dateDep'] = strtotime($timeDep, $dateDep);
            }

            if (count($table2) > 0 && preg_match("/^[ ]*{$this->opt($this->t('FROM'))}[:\s]+([^:\s].+)/ms", $table2[0], $m)) {
                $it['nameDep'] = preg_replace('/\s+/', ' ', $m[1]);
            }

            if (count($table2) > 1 && preg_match("/^[ ]*{$this->opt($this->t('TO'))}[:\s]+([^:\s].+)/ms", $table2[1], $m)) {
                $it['nameArr'] = preg_replace('/\s+/', ' ', $m[1]);
            }

            if (count($table1) > 1 && preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('CABIN'))}[: ]+([^:\s].+)$/m", $table1[1], $m)) {
                $it['cabin'] = $m[1];
            }

            $patternInfant = "/^[ ]*{$this->opt($this->t('SEAT NUMBER'))}[:\s]+INF[ ]*$/m";
            $patternSeat = "/^[ ]*{$this->opt($this->t('SEAT NUMBER'))}[:\s]+(\d+[A-Z])[ ]*$/m";

            if (count($table3) > 1 && preg_match($patternInfant, $table3[1])
                || count($table2) > 1 && preg_match($patternInfant, $table2[1])
            ) {
                $it['isInfant'] = true;
            } elseif (count($table3) > 1 && preg_match($patternSeat, $table3[1], $m)
                || count($table2) > 1 && preg_match($patternSeat, $table2[1], $m)
            ) {
                $it['seat'] = $m[1];
            }

            if (count($table3) > 0 && preg_match("/^\s*{$this->opt($this->t('PASSENGER'))}[:\s]+([^:\s].+\S)\s*$/s", $table3[0], $m)) {
                $it['traveller'] = preg_replace('/\s+/', ' ', $m[1]);
            }

            /* Step 2: save values */

            if (!empty($it['traveller'])) {
                if ($it['isInfant'] && !in_array($it['traveller'], $infants)) {
                    $f->general()->infant($it['traveller'], true);
                    $infants[] = $it['traveller'];
                } elseif (!$it['isInfant'] && !in_array($it['traveller'], $travellers)) {
                    $f->general()->traveller($it['traveller'], true);
                    $travellers[] = $it['traveller'];
                }
            }

            if (!empty($it['pnr']) && !in_array($it['pnr'], $bookingNoValues)) {
                $f->general()->confirmation($it['pnr']);
                $bookingNoValues[] = $it['pnr'];
            }

            if (empty($it['airline']) || empty($it['flightNumber']) || empty($it['dateDep']) || empty($it['nameDep'])) {
                $this->logger->debug('$it = ' . print_r($it, true));
                $this->logger->debug('Required fields for flight segment is empty!');
                $f->addSegment(); // for 100% fail

                continue;
            }

            $segIndex = $it['airline'] . $it['flightNumber'] . '_' . $it['dateDep'] . '_' . $it['nameDep'];

            if (array_key_exists($segIndex, $segObjects)) {
                /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $s */
                $s = $segObjects[$segIndex];

                $seatsCurrent = $s->getSeats();

                if (count($seatsCurrent) === 0 && !empty($it['seat'])
                    || count($seatsCurrent) > 0 && !empty($it['seat']) && !in_array($it['seat'], $seatsCurrent)
                ) {
                    $s->extra()->seat($it['seat'], false, false, $it['traveller']);
                }

                $cabinCurrent = $s->getCabin();

                if (empty($cabinCurrent) && !empty($it['cabin'])) {
                    $s->extra()->cabin($it['cabin']);
                } elseif (!empty($cabinCurrent) && !empty($it['cabin']) && $cabinCurrent !== $it['cabin']) {
                    $s->extra()->cabin(null, false, true);
                }
            } else {
                $s = $f->addSegment();
                $segObjects[$segIndex] = $s;

                $s->departure()->date($it['dateDep'])->name($it['nameDep'])->noCode();
                $s->arrival()->noDate()->name($it['nameArr'])->noCode();
                $s->airline()->name($it['airline'])->number($it['flightNumber']);
                $s->extra()->cabin($it['cabin'], false, true);

                if (!empty($it['seat'])) {
                    $s->extra()->seat($it['seat'], false, false, $it['traveller']);
                }
            }
        }
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

    private function re(string $re, ?string $str, $c = 1): ?string
    {
        if (preg_match($re, $str ?? '', $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
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

    private function getAttachmentName(\PlancakeEmailParser $parser, $pdf): ?string
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }

    private function normalizeDate($str): string
    {
        $in = [
            // 22/03/2023    |    22-03-2023
            "/^\s*(\d{1,2})\s*[-\/]\s*(\d{1,2})\s*[-\/]\s*(\d{4})\s*$/",
        ];
        $out = [
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }
}
