<?php

namespace AwardWallet\Engine\flightcentre\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JetstarPdf extends \TAccountChecker
{
    public $mailFiles = "flightcentre/it-27816446.eml, flightcentre/it-27816501.eml, flightcentre/it-860896033.eml, flightcentre/it-867221252.eml";

    public $reBody = [
        'en'  => ['This is not a boarding pass', 'Your flight itinerary'],
        'en2' => ['Jetstar Flight Itinerary for', 'Your flight itinerary'],
        'en3' => ['Your Jetstar Itinerary', 'your flight itinerary'],
        'en4' => ['Reservation Number', 'Name of passenger/s'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'Booking reference'       => ['Booking reference', 'booking reference', 'Reservation Number'],
            'Booking Contact Details' => ['Booking Contact Details', 'booking details'],
        ],
    ];
    private $code;
    private $bodies = [
        'flightcentre' => [
            'flightcentre.com.au',
            'Flight Centre Travel',
        ],
        'jetstar' => [
            'Jetstar',
        ],
    ];
    private static $headers = [
        'flightcentre' => [
            'from' => ['flightcentre.com.au'],
            'subj' => [],
        ],
        'jetstar' => [
            'from' => ['jetstar.com'],
            'subj' => [],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    } else {
                        if (null !== ($this->code = $this->getProviderByText($text))) {
                            $code = $this->code;

                            if (!$this->parseEmailPdf($text, $email)) {
                                return null;
                            }
                        }
                    }
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        if (!isset($code)) {
            $code = $this->getProvider($parser);
        }

        if (isset($code)) {
            $email->setProviderCode($code);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (null !== ($code = $this->getProviderByText($text))) {
                if ($this->assignLang($text)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByText($this->http->Response['body']);
    }

    private function getProviderByText($text)
    {
        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (strpos($text, $search) !== false) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $textPDF = preg_replace("/^ *\d+\/\d+\/\d+ [^\n]+ \d+\/\d+\/\d+ *$/m", '', $textPDF);
        $textPDF = preg_replace("/^ *https?:\/\/[^\n]+ \d+\/\d+ *$/m", '', $textPDF);

        $infoBlock = $this->re("/( +{$this->opt($this->t('Booking reference'))}.+?){$this->opt($this->t('Booking Contact Details'))}/s",
            $textPDF);

        if (empty($infoBlock)) {
            $this->parseEmailPdf2($textPDF, $email);
            $this->logger->debug('other format - go to parseEmailPdf2');

            return $email;
        }
        $table = $this->splitCols($infoBlock, $this->colsPos($infoBlock));

        if (count($table) !== 2) {
            $this->parseEmailPdf2($textPDF, $email);
            $this->logger->debug('other format - go to parseEmailPdf2');

            return $email;
        }

        $r = $email->add()->flight();

        $dateReg = strtotime($this->re("/{$this->opt($this->t('Itinerary issue date'))}:\s+(.+)/", $table[0]));

        if (!empty($dateReg)) {
            $r->general()
                ->date($dateReg);
        }

        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('Booking reference'))}\s+([A-Z\d]+)/", $table[1]));

        $itBlock = strstr($textPDF, $this->t('Passenger:'), true);
        $itBlock = $this->re("/(Date +Flight number +[^\n]+.+)/si", $itBlock);
        $segments = $this->splitter("/(.+? \d{4} {2,}(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d+)/", $itBlock);

        if (count($segments) > 1) {
            $this->logger->debug('need to check format segments-seats-pax'); //TODO: need more examples

            return false;
        }

        $s = $r->addSegment();
        $paxBlock = strstr($textPDF, 'Times are local times at the relevant airport', true);
        $paxBlock = $this->re("/{$this->opt($this->t('Passenger:'))}[^\n]+\n(.+)/s", $paxBlock);

        if (preg_match_all("/^([A-Z ]+)\s+\((.+?)\).+?{$this->opt($this->t('Meal'))} +([^\n]+)/sm", $paxBlock, $m)) {
            $r->general()
                ->travellers($m[1]);
            $s->extra()
                ->seats(array_filter(array_map("trim", $m[2]), function ($s) {
                    return preg_match("/^\d+[A-Z]$/", $s);
                }))
                ->meal(preg_replace("/[^\w\s,\.\-\|]/", '', implode('|', array_unique(array_map("trim", $m[3])))));
        }

        if (preg_match_all("/Frequent\s+Flyer\s+number\s+([A-Z\d]+)/", $paxBlock, $m)) {
            $r->program()->accounts($m[1], false);
        }

        foreach ($segments as $segment) {
            $table = $this->splitCols($segment, $this->colsPos($segment));

            if (count($table) !== 4) {
                $this->logger->debug('other format segment');

                return false;
            }

            if (preg_match("/(?<name>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<num>\d+)\s+(?<aircraft>.+)/", $table[1], $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['num']);
                $s->extra()->aircraft($m['aircraft']);
            }
            $duration = $this->re("/{$this->opt($this->t('Flight duration'))}:\s+(.+)/", $table[1]);

            if (!empty($duration)) {
                $s->extra()->duration($duration);
            }

            if (preg_match("/(?<name>[^\n]+)\s+(?<date>[^\n]+)\s+.+? \/ (?<time>\d+:\d+)\s+(?<terminal>.+)/s",
                $table[2], $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m['name'])
                    ->date(strtotime($m['date'] . ' ' . $m['time']));

                if (preg_match("/(.+?)[­ ]{2,}T(\w+) Domestic/", $m['terminal'], $v)) {
                    $s->departure()
                        ->name($v[1])
                        ->terminal($v[2]);
                }
            }

            if (preg_match("/(?<name>[^\n]+)\s+(?<date>[^\n]+)\s+.+? \/ (?<time>\d+:\d+)\s+(?<terminal>.+)/s",
                $table[3], $m)) {
                $s->arrival()
                    ->noCode()
                    ->name($m['name'])
                    ->date(strtotime($m['date'] . ' ' . $m['time']));

                if (preg_match("/(.+?)[­ ]{2,}T(\w+) Domestic/", $m['terminal'], $v)) {
                    $s->arrival()
                        ->name($v[1])
                        ->terminal($v[2]);
                }
            }
        }

        return true;
    }

    private function parseEmailPdf2($textPDF, Email $email)
    {
        $f = $email->add()->flight();

        $travellers = [];

        if (preg_match("/{$this->opt($this->t('Booking reference'))}\s*([A-Z\d]{6})\n/", $textPDF, $m)
        || preg_match("/{$this->opt($this->t('Booking reference'))}.+\n+\s*([A-Z\d]{6})\s+/", $textPDF, $m)) {
            $f->general()
                ->confirmation($m[1]);
        }

        $travellerText = $this->re("/({$this->opt($this->t('Contact details'))}.+{$this->opt($this->t('your flight itinerary'))})/su", $textPDF);

        if (empty($travellerText)) {
            $travellerText = $this->re("/({$this->opt($this->t('Reservation Number'))}.+{$this->opt($this->t('Name'))})/su", $textPDF);
        }

        $travellerTable = $this->splitCols($travellerText);

        if (preg_match_all("/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\(/mu", $travellerTable[1], $match)
            || preg_match_all("/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/mu", $travellerTable[2], $match)) {
            $match[1] = array_filter(preg_replace("#Name of passenger/s#", "", $match[1]));

            $f->general()
                ->travellers($travellers = $this->niceTravellers($match[1]));
        }

        $segmentText = $this->re("/^[ ]+Date\s+Flight Number\s+Departing\s+Arriving\n(.+)\n+(?:Times|\s*All times)/msiu", $textPDF);
        $segemnts = $this->splitText($segmentText, "/(.+\n\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{2,4})/mu", true);

        foreach ($segemnts as $key => $segemnt) {
            $table = $this->splitCols($segemnt, $this->colsPos($segemnt));

            if (count($table) !== 4) {
                $this->logger->debug('other format segment');

                return $email;
            }

            $s = $f->addSegment();

            if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{2,4})/", $table[1], $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            if (preg_match("/^(?<depName>.+)\n*(?<depDate>.+\d{4})?\n+\d+\s*hr[\s\/]+(?<depTime>\d+\:\d+\s*a?p?m)\n(?<depTerminal>.+)$/", $table[2], $m)) {
                if (!isset($m['depDate'])) {
                    $depDate = $m['depDate'];
                } else {
                    $depDate = $this->re("/(.+\d{4})/", $table[0]);
                }

                $s->departure()
                    ->name($m['depName'])
                    ->date(strtotime($depDate . ', ' . $m['depTime']))
                    ->noCode();

                if (stripos($m['depTerminal'], "Terminal") !== false) {
                    $s->departure()
                        ->terminal(str_replace("Terminal", "", $m['depTerminal']));
                }

                if (stripos($m['depTerminal'], "- T") !== false) {
                    $s->departure()
                        ->terminal($this->re("/{$this->opt($this->t('- T'))}(.+)/", $m['depTerminal']));
                }
            }

            if (preg_match("/^(?<arrName>.+)\n*(?<arrDate>.+\d{4})?\n+\d+\s*hr[\s\/]+(?<arrTime>\d+\:\d+\s*a?p?m)\n(?<arrTerminal>.+)$/", $table[3], $m)) {
                if (!isset($m['arrDate'])) {
                    $arrDate = $m['arrDate'];
                } else {
                    $arrDate = $this->re("/(.+\d{4})/", $table[0]);
                }

                $s->arrival()
                    ->name($m['arrName'])
                    ->date(strtotime($arrDate . ', ' . $m['arrTime']))
                    ->noCode();

                if (stripos($m['arrTerminal'], "Terminal") !== false) {
                    $s->arrival()
                        ->terminal(str_replace("Terminal", "", $m['arrTerminal']));
                }

                if (stripos($m['arrTerminal'], "- T") !== false) {
                    $s->arrival()
                        ->terminal($this->re("/{$this->opt($this->t('- T'))}(.+)/", $m['arrTerminal']));
                }
            }

            foreach ($travellers as $traveller) {
                $seats = array_filter(explode(", ", $this->re("/{$traveller}\s*\(([A-Z\d\,\s]+)\)/", $textPDF)));

                if (count($seats) == count($segemnts)) {
                    $s->extra()
                        ->seat($seats[$key], true, true, $traveller);
                }
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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

    private function colsPos($table, $correct = 5)
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
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
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

    private function niceTravellers($travellers)
    {
        return preg_replace("/^(?:MRS|MR|MS|MISS)/", "", $travellers);
    }
}
