<?php

namespace AwardWallet\Engine\malaysia\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass2024Pdf extends \TAccountChecker
{
    public $mailFiles = "malaysia/it-778456477.eml, malaysia/it-778331107.eml";

    private $subjects = [
        'en' => ['Boarding Pass']
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'bpPhrases' => ['THIS IS YOUR BOARDING PASS'],
            'notBpPhrases' => ['THIS IS NOT A BOARDING PASS'],
            'Flight' => ['Flight'],
            'Boarding' => ['Boarding'],
            'flightEnd' => ['NEXT STEPS', 'TRAVEL INFORMATION'],
        ]
    ];

    private function parsePdf(Email $email, array $textsBP): void
    {
        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'travellerName2' => '[[:alpha:]]+(?: [[:alpha:]]+)*[ ]*\/[ ]*(?:[[:alpha:]]+ )*[[:alpha:]]+', // Swaminathan / Saravanan Mr
            'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
        ];

        $f = $email->add()->flight();
        $segObjects = $travellers = $bookingRefValues = $tickets = $accounts = [];

        foreach ($textsBP as $textBP) {
            $this->lang = $textBP['lang'];
            $text = preg_replace([
                "/^(.+?)\n+[ ]*{$this->opt($this->t('flightEnd'))}.*$/is",
                "/.*YOU ARE INVITED TO.*/i",
            ], [
                '$1',
                '',
            ], $textBP['text']);

            /* Step 1: get values */

            $it = [
                'traveller' => null,
                'dateDep' => 0, 'dateArr' => 0,
                'codeDep' => null, 'codeArr' => null,
                'terminalDep' => null, 'terminalArr' => null,
                'nameDep' => null, 'nameArr' => null,
                'airline' => null, 'flightNumber' => null,
                'pnr' => null, 'seat' => null, 'bookingCode' => null,
                'ticket' => null, 'account' => null,
            ];

            $it['traveller'] = $this->normalizeTraveller($this->re("/^[ ]*{$this->opt($this->t('Name'))}[ ]*[:]+[ ]*({$patterns['travellerName']}|{$patterns['travellerName2']})$/mu", $text));
            $table1Text = $this->re("/^([ ]*{$this->opt($this->t('DEPARTURE'))}[ ]+{$this->opt($this->t('FROM'))} .+?)\n+[ ]*{$this->opt($this->t('Flight'))}[ ]+{$this->opt($this->t('Boarding'))} /ims", $text) ?? '';
            $table1Pos = [0];

            if (preg_match("/^((([ ]*{$this->opt($this->t('DEPARTURE'))})[ ]+{$this->opt($this->t('FROM'))})[ ]+{$this->opt($this->t('TO'))}[ ]+){$this->opt($this->t('ARRIVAL'))}\n/i", $table1Text, $matches)) {
                $table1Pos[] = mb_strlen($matches[3]) + 3;
                $table1Pos[] = mb_strlen($matches[2]) + 3;
                $table1Pos[] = mb_strlen($matches[1]);
            }

            $table1 = $this->splitCols($table1Text, $table1Pos);
            $patternDate = "(?:E )?(?<time>{$patterns['time']}).*\n+[ ]*(?<date>[\s\S]{4,}?\b\d{4}\b)";
            $patternCodeName = "(?<code>[A-Z]{3})(?:\n+[ ]*(?<name>[\s\S]{2,}?))?";
            $patternTerminalName = "Terminal[ ]+(?<terminal>\S.*)\n+[ ]*(?<name>[\s\S]{2,}?)";

            if (count($table1) > 0 && preg_match("/^\s*{$this->opt($this->t('DEPARTURE'))}\s+{$patternDate}/", $table1[0], $m)) {
                $it['dateDep'] = strtotime($m['time'], strtotime(preg_replace('/\s+/', ' ', $m['date'])));
            }

            if (count($table1) > 1 && preg_match("/^\s*{$this->opt($this->t('FROM'))}\s+{$patternCodeName}\s*$/", $table1[1], $m)) {
                $it['codeDep'] = $m['code'];

                if (preg_match("/^{$patternTerminalName}\s*$/i", $m['name'], $m2)) {
                    $it['terminalDep'] = $m2['terminal'];
                    $it['nameDep'] = preg_replace('/\s+/', ' ', $m2['name']);
                } else {
                    $it['nameDep'] = preg_replace('/\s+/', ' ', $m['name']);
                }
            }

            if (count($table1) > 2 && preg_match("/^\s*{$this->opt($this->t('TO'))}\s+{$patternCodeName}\s*$/", $table1[2], $m)) {
                $it['codeArr'] = $m['code'];

                if (preg_match("/^{$patternTerminalName}\s*$/i", $m['name'], $m2)) {
                    $it['terminalArr'] = $m2['terminal'];
                    $it['nameArr'] = preg_replace('/\s+/', ' ', $m2['name']);
                } else {
                    $it['nameArr'] = preg_replace('/\s+/', ' ', $m['name']);
                }
            }

            if (count($table1) > 3 && preg_match("/^\s*{$this->opt($this->t('ARRIVAL'))}\s+{$patternDate}/", $table1[3], $m)) {
                $it['dateArr'] = strtotime($m['time'], strtotime(preg_replace('/\s+/', ' ', $m['date'])));
            }

            $table2Text = $this->re("/^([ ]*{$this->opt($this->t('Flight'))}[ ]+{$this->opt($this->t('Boarding'))} .+?)\n+[ ]*{$this->opt($this->t('E-Ticket No'))}[ ]+{$this->opt($this->t('Frequent Flyer No'))} /ims", $text) ?? '';
            $table2Text = preg_replace("/\n.*\bCODESHARE[ ]*:.*/i", '', $table2Text);
            $table2Pos = [0];

            if (preg_match("/^((((([ ]*{$this->opt($this->t('Flight'))}[ ]+){$this->opt($this->t('Boarding'))}[ ]+){$this->opt($this->t('Booking Ref'))}[ ]+){$this->opt($this->t('Seat'))}) .+ ){$this->opt($this->t('Class'))}\n/i", $table2Text, $matches)) {
                $table2Pos[] = mb_strlen($matches[5]);
                $table2Pos[] = mb_strlen($matches[4]);
                $table2Pos[] = mb_strlen($matches[3]);
                $table2Pos[] = mb_strlen($matches[2]) + 1;
                $table2Pos[] = mb_strlen($matches[1]);
            }

            $table2 = $this->splitCols($table2Text, $table2Pos);
            
            if (count($table2) > 0 && preg_match("/^\s*{$this->opt($this->t('Flight'))}\s+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\s*$/", $table2[0], $m)) {
                $it['airline'] = $m['name'];
                $it['flightNumber'] = $m['number'];
            }
            
            if (count($table2) > 2 && preg_match("/^\s*{$this->opt($this->t('Booking Ref'))}\s+([A-Z\d]{5,10})\s*$/", $table2[2], $m)) {
                $it['pnr'] = $m[1];
            }

            if (count($table2) > 3 && preg_match("/^\s*{$this->opt($this->t('Seat'))}\s+(\d+[A-Z])\s*$/", $table2[3], $m)) {
                $it['seat'] = $m[1];
            }

            if (count($table2) > 5 && preg_match("/^\s*{$this->opt($this->t('Class'))}\s+([A-Z]{1,2})\s*$/", $table2[5], $m)) {
                $it['bookingCode'] = $m[1];
            }

            $table3Text = $this->re("/^([ ]*{$this->opt($this->t('E-Ticket No'))}[ ]+{$this->opt($this->t('Frequent Flyer No'))} .+)/ims", $text) ?? '';
            $table3Pos = [0];

            if (preg_match("/^(([ ]*{$this->opt($this->t('E-Ticket No'))}[ ]+){$this->opt($this->t('Frequent Flyer No'))}[ ]+){$this->opt($this->t('Tier Level'))} /i", $table3Text, $matches)) {
                $table3Pos[] = mb_strlen($matches[2]);
                $table3Pos[] = mb_strlen($matches[1]) - 1;
            }

            $table3 = $this->splitCols($table3Text, $table3Pos);
            
            if (count($table3) > 0 && preg_match("/^\s*{$this->opt($this->t('E-Ticket No'))}\s+({$patterns['eTicket']})\s*$/", $table3[0], $m)) {
                $it['ticket'] = $m[1];
            }

            if (count($table3) > 1 && preg_match("/^\s*{$this->opt($this->t('Frequent Flyer No'))}\s+(\S.+\S)\s*$/", $table3[1], $m)) {
                $it['account'] = preg_replace('/\s/', '', $m[1]);
            }

            /* Step 2: save values */

            /* Boarding Pass */

            if ($textBP['isBoardingPass'] === true && $textBP['filename'] !== null) {
                $bp = $email->add()->bpass();
                $bp
                    ->setAttachmentName($textBP['filename'])
                    ->setTraveller($it['traveller'])
                    ->setDepDate($it['dateDep'])
                    ->setDepCode($it['codeDep'])
                    ->setFlightNumber($it['airline'] . ' ' . $it['flightNumber'])
                    ->setRecordLocator($it['pnr'])
                ;
            }
            
            /* Flight */

            if (!empty($it['traveller']) && !in_array($it['traveller'], $travellers)) {
                $f->general()->traveller($it['traveller'], true);
                $travellers[] = $it['traveller'];
            }

            if (!empty($it['pnr']) && !in_array($it['pnr'], $bookingRefValues)) {
                $f->general()->confirmation($it['pnr']);
                $bookingRefValues[] = $it['pnr'];
            }

            if (!empty($it['ticket']) && !in_array($it['ticket'], $tickets)) {
                $f->issued()->ticket($it['ticket'], false, $it['traveller']);
                $tickets[] = $it['ticket'];
            }

            if (!empty($it['account']) && !in_array($it['account'], $accounts)) {
                $f->program()->account($it['account'], false, $it['traveller']);
                $accounts[] = $it['account'];
            }
            
            if (empty($it['airline']) || empty($it['flightNumber']) || empty($it['codeDep']) || empty($it['dateDep'])) {
                $this->logger->debug('Required fields for flight segment is empty!');
                $f->addSegment(); // for 100% fail
                continue;
            }

            $segIndex = $it['airline'] . $it['flightNumber'] . '_' . $it['codeDep'] . '_' . $it['dateDep'];

            if (array_key_exists($segIndex, $segObjects)) {
                /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $s */
                $s = $segObjects[$segIndex];

                $seatsCurrent = $s->getSeats();

                if (count($seatsCurrent) === 0 && !empty($it['seat'])
                    || count($seatsCurrent) > 0 && !empty($it['seat']) && !in_array($it['seat'], $seatsCurrent)
                ) {
                    $s->extra()->seat($it['seat'], false, false, $it['traveller']);
                }

                $bookingCodeCurrent = $s->getBookingCode();

                if (empty($bookingCodeCurrent) && !empty($it['bookingCode'])) {
                    $s->extra()->bookingCode($it['bookingCode']);
                } elseif (!empty($bookingCodeCurrent) && !empty($it['bookingCode']) && $bookingCodeCurrent !== $it['bookingCode']) {
                    $s->extra()->bookingCode(null, false, true);
                }
            } else {
                $s = $f->addSegment();
                $segObjects[$segIndex] = $s;

                $s->departure()->date($it['dateDep'])->code($it['codeDep'])->terminal($it['terminalDep'], false, true)->name($it['nameDep']);
                $s->arrival()->date($it['dateArr'])->code($it['codeArr'])->terminal($it['terminalArr'], false, true)->name($it['nameArr']);
                $s->airline()->name($it['airline'])->number($it['flightNumber']);
                $s->extra()->bookingCode($it['bookingCode'], false, true);

                if (!empty($it['seat'])) {
                    $s->extra()->seat($it['seat'], false, false, $it['traveller']);
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]malaysiaairlines\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
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
        $detectProv = $this->detectEmailFromProvider( rtrim($parser->getHeader('from'), '> ') );

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || !$detectProv
                && strpos($textPdf, 'Proceed to Malaysia Airlines') === false
                && strpos($textPdf, 'For travel within Malaysia') === false
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

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
                $splittingText = $this->opt(array_merge((array) $this->t('bpPhrases'), (array) $this->t('notBpPhrases')));
                $pdfParts = $this->splitText($textPdf, "/^([ ]*{$splittingText}.*|.*{$splittingText})$/im", true);

                foreach ($pdfParts as $partText) {
                    $textsBP[] = [
                        'lang' => $this->lang,
                        'text' => $partText,
                        'isBoardingPass' => preg_match("/^.*{$this->opt($this->t('bpPhrases'))}/i", $partText) > 0,
                        'filename' => $fileName,
                    ];
                }
            }
        }

        /* Step 2: parsing */

        $this->parsePdf($email, $textsBP);

        if (count(array_unique($usingLangs)) === 1
            || count(array_unique(array_filter($usingLangs, function ($item) { return $item !== 'en'; }))) === 1
        ) {
            $email->setType('BoardingPass2024Pdf' . ucfirst($usingLangs[0]));
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
            if ( !is_string($lang) || empty($phrases['Flight']) || empty($phrases['Boarding']) ) {
                continue;
            }
            if (preg_match("/^[ ]*{$this->opt($phrases['Flight'])}[ ]+{$this->opt($phrases['Boarding'])} /im", $text)) {
                $this->lang = $lang;
                return true;
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

    private function getAttachmentName(\PlancakeEmailParser $parser, $pdf): ?string
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MSTR|MISS|MRS|MR|MS|DR)';

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
}
