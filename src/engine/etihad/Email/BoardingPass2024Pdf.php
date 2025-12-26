<?php

namespace AwardWallet\Engine\etihad\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Common\Parser\Util\EmailDateHelper;

class BoardingPass2024Pdf extends \TAccountChecker
{
    public $mailFiles = "etihad/it-810294191.eml";

    private $subjects = [
        'en' => ['Your boarding pass for your flight on']
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            /* Html */
            // 'Ticket number' => '',

            /* Pdf */
            // 'Name' => '',
            // 'Frequent flyer number' => '',
            // 'Cabin' => '',
            // 'Fare' => '',
            'Booking reference' => ['Booking reference'],
            'Date' => ['Date'],
            // 'Seat' => '',
            'flightEnd' => ['Arrive', 'Go to gate', 'Gate closes', 'Download our app', 'Explore more for less'],
        ]
    ];

    private function parsePdf(Email $email, array $textsBP, ?int $emailDate): void
    {
        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:]\s]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
        ];

        $f = $email->add()->flight();
        $segObjects = $travellers = $bookingRefValues = $accounts = $tickets = [];

        foreach ($textsBP as $textBP) {
            $this->lang = $textBP['lang'];
            $text = preg_replace([
                "/^(.+?)\n+[ ]*{$this->opt($this->t('flightEnd'))}.*$/is",
            ], [
                '$1',
            ], $textBP['text']);

            /* Step 1: get values */

            $it = [
                'airline' => null, 'flightNumber' => null,
                'traveller' => null,
                'account' => null,
                'codeDep' => null, 'codeArr' => null,
                'nameDep' => null, 'nameArr' => null,
                'terminalDep' => null, 'terminalArr' => null,
                'cabin' => null, 'pnr' => null,
                'dateDep' => 0, 'dateArr' => 0,
                'seat' => null,
            ];

            if (preg_match("/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<number>\d+)(?:[^\w\n].*)?\n/", $text, $m)) {
                $it['airline'] = $m['name'];
                $it['flightNumber'] = $m['number'];
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Name'))}[: ]*\n+[ ]*({$patterns['travellerName']})\n+[ ]*(?:{$this->opt($this->t('Frequent flyer number'))}|{$patterns['time']})/mu", $text, $m)) {
                $it['traveller'] = $this->normalizeTraveller(preg_replace('/\s+/', ' ', $m[1]));
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Frequent flyer number'))}[: ]+([-A-Z\d ]{4,40})$/m", $text, $m)) {
                $it['account'] = $m[1];
            }

            $timeDep = $timeArr = null;

            $table1Text = $this->re("/^([ ]*{$patterns['time']}[\s\S]+?)\n+.+ {$this->opt($this->t('Booking reference'))}[: ]+{$this->opt($this->t('Date'))}/im", $text) ?? '';
            $table1Pos = [0];

            $table1Lengths = array_map(function ($item) {
                return ceil(mb_strlen($item) / 2);
            }, preg_split("/\n+/", $table1Text));
            rsort($table1Lengths);
            
            if (count($table1Lengths) > 0) {
                $table1Pos[] = $table1Lengths[0];
            }

            $table1 = $this->splitCols($table1Text, $table1Pos);

            /*
                15:25
                JFK
                New York
                Terminal 4
            */
            $pattern = "/^\s*(?<time>{$patterns['time']}).*\n+[ ]*(?<code>[A-Z]{3})\n+[ ]*(?<city>[\s\S]{2,}?)\n+[ ]*(?i)(?<terminal>Terminal[\s\S]*|-\s*$)/";

            if (count($table1) > 0 && preg_match($pattern, $table1[0], $m)) {
                $timeDep = $m['time'];
                $it['codeDep'] = $m['code'];
                $it['nameDep'] = preg_replace('/\s+/', ' ', $m['city']);
                $terminalDep = preg_replace([
                    "/^(?:Terminal[-\s]*)+/i",
                    "/(?:[-\s]*Terminal)+$/i"
                ], '', trim(preg_replace('/\s+/', ' ', $m['terminal']), '- '));

                if ($terminalDep !== '') {
                    $it['terminalDep'] = $terminalDep;
                }
            }

            if (count($table1) > 1 && preg_match($pattern, $table1[1], $m)) {
                $timeArr = $m['time'];
                $it['codeArr'] = $m['code'];
                $it['nameArr'] = preg_replace('/\s+/', ' ', $m['city']);
                $terminalArr = preg_replace([
                    "/^(?:Terminal[-\s]*)+/i",
                    "/(?:[-\s]*Terminal)+$/i"
                ], '', trim(preg_replace('/\s+/', ' ', $m['terminal']), '- '));

                if ($terminalArr !== '') {
                    $it['terminalArr'] = $terminalArr;
                }
            }

            $table2Text = $this->re("/\n(.+ {$this->opt($this->t('Booking reference'))}[: ]+{$this->opt($this->t('Date'))}[: ]*\n[\s\S]+?)\n+.+ {$this->opt($this->t('Seat'))}[: ]*\n/i", $text) ?? '';
            $table2Pos = [0];

            if (preg_match("/^((([ ]*{$this->opt($this->t('Cabin'))}[: ]+){$this->opt($this->t('Fare'))}[: ]+){$this->opt($this->t('Booking reference'))}[: ]*?)[ ]+{$this->opt($this->t('Date'))}[: ]*\n/i", $table2Text, $matches)) {
                $table2Pos[] = mb_strlen($matches[3]);
                $table2Pos[] = mb_strlen($matches[2]);
                $table2Pos[] = mb_strlen($matches[1]);
            }

            $table2 = $this->splitCols($table2Text, $table2Pos);
            
            if (count($table2) > 0 && preg_match("/^\s*{$this->opt($this->t('Cabin'))}[:\s]+([^:\s][\s\S]*?)\s*$/", $table2[0], $m)) {
                $it['cabin'] = preg_replace('/\s+/', ' ', $m[1]);
            }

            if (count($table2) > 2 && preg_match("/^\s*{$this->opt($this->t('Booking reference'))}[:\s]+([A-Z\d]{5,10})\s*$/", $table2[2], $m)) {
                $it['pnr'] = $m[1];
            }

            $dateVal = null;

            if (count($table2) > 3 && preg_match("/^\s*{$this->opt($this->t('Date'))}[:\s]+([^:\s][\s\S]*?)\s*$/", $table2[3], $m)) {
                $dateVal = preg_replace('/\s+/', ' ', $m[1]);
            }

            $date = 0;

            if (preg_match("/^.{4,}\b\d{4}$/", $dateVal)) {
                // 04 Dec 2024
                $date = strtotime($dateVal);
            } elseif (preg_match("/^\d{1,2}[,.\s]+[[:alpha:]]+$/u", $dateVal) && $emailDate) {
                // 04 Dec
                $date = EmailDateHelper::parseDateRelative($dateVal, $emailDate);
            }

            if ($date && $timeDep) {
                $it['dateDep'] = strtotime($timeDep, $date);
            }

            if ($date && $timeArr) {
                $it['dateArr'] = strtotime($timeArr, $date);
            }

            $table3Text = $this->re("/\n(.+ {$this->opt($this->t('Seat'))}[: ]*\n+[\s\S]+)/", $text) ?? '';
            $table3Pos = [0];

            if (preg_match("/^(.+? ){$this->opt($this->t('Seat'))}[: ]*\n/i", $table3Text, $matches)) {
                $table3Pos[] = mb_strlen($matches[1]);
            }

            $table3 = $this->splitCols($table3Text, $table3Pos);
            
            if (count($table3) > 1 && preg_match("/^\s*{$this->opt($this->t('Seat'))}[:\s]+(?-i)(\d+[A-Z])\s*$/i", $table3[1], $m)) {
                $it['seat'] = $m[1];
            }

            /* Step 2: save values */

            /* Boarding Pass */

            if ($textBP['filename'] !== null) {
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

                $cabinCurrent = $s->getCabin();

                if (empty($cabinCurrent) && !empty($it['cabin'])) {
                    $s->extra()->cabin($it['cabin']);
                } elseif (!empty($cabinCurrent) && !empty($it['cabin']) && $cabinCurrent !== $it['cabin']) {
                    $s->extra()->cabin(null, false, true);
                }
            } else {
                $s = $f->addSegment();
                $segObjects[$segIndex] = $s;

                $s->departure()->date($it['dateDep'])->code($it['codeDep'])->terminal($it['terminalDep'], false, true)->name($it['nameDep']);
                $s->arrival()->date($it['dateArr'])->code($it['codeArr'])->terminal($it['terminalArr'], false, true)->name($it['nameArr']);
                $s->airline()->name($it['airline'])->number($it['flightNumber']);
                $s->extra()->cabin($it['cabin'], false, true);

                if (!empty($it['seat'])) {
                    $s->extra()->seat($it['seat'], false, false, $it['traveller']);
                }
            }
        }

        /* Tickets (html) */
        
        $ticketsNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Ticket number'), "translate(.,':','')")}]");

        foreach ($ticketsNodes as $tktRoot) {
            $passengerName = $this->normalizeTraveller($this->http->FindSingleNode("ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][1]", $tktRoot, true, "/^{$patterns['travellerName']}$/u"));
            $ticket = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $tktRoot, true, "/^{$patterns['eTicket']}$/");

            if ($passengerName && $ticket && in_array($passengerName, $travellers) && !in_array($ticket, $tickets)) {
                $f->issued()->ticket($ticket, false, $passengerName);
                $tickets[] = $ticket;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]etihad\.com$/i', $from) > 0;
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
                && strpos($textPdf, 'Download the Etihad Airways app') === false
                && strpos($textPdf, 'Did you know, your Etihad Airways boarding') === false
                && strpos($textPdf, 'Find out more at etihad.com') === false
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
                $pdfParts = $this->splitText($textPdf, "/(^[ ]*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d+(?:[^\w\n].*)?\n+[ ]*(?i){$this->opt($this->t('Name'))}[: ]*\n)/m", true);

                foreach ($pdfParts as $partText) {
                    $textsBP[] = [
                        'lang' => $this->lang,
                        'text' => $partText,
                        'filename' => $fileName,
                    ];
                }
            }
        }

        /* Step 2: parsing */

        $emailDate = EmailDateHelper::getDateFromHeaders($parser);
        $this->parsePdf($email, $textsBP, $emailDate);

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
            if ( !is_string($lang) || empty($phrases['Booking reference']) || empty($phrases['Date']) ) {
                continue;
            }
            if (preg_match("/ {$this->opt($phrases['Booking reference'])}[: ]+{$this->opt($phrases['Date'])}[: ]*(?:[ ]{2}|$)/im", $text)) {
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

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
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
