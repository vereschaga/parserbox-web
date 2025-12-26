<?php

namespace AwardWallet\Engine\hoggrob\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicketPDF extends \TAccountChecker
{
    public $mailFiles = "hoggrob/it-4614099.eml, hoggrob/it-6234509.eml";

    private $langDetectorsPdf = [
        'en' => ['AIRPORT/TERMINAL'],
    ];

    private $lang = '';
    private static $dict = [
        'en' => [
            'DEPART/ARRIVE' => ['DEPART/ARRIVE', 'DEPARTURE/ARRIVAL'],
            'classVariants' => ['Economy', 'Business'], // hard-code
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $i => $pdf) {
            $textPdf = substr(\PDF::convertToText($parser->getAttachmentBody($pdf)), 0, 10000);

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parseEmail($email, $textPdf);
            }
        }

        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (strpos($textPdf, ' HRG -') === false && stripos($textPdf, '@dnata.com') === false
                && stripos($textPdf, 'dnatatravel.com') === false && stripos($textPdf, 'dnata International') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@uae.hrgworldwide.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                $its[$j]['Passengers'] = array_merge($its[$j]['Passengers'], $its[$i]['Passengers']);
                $its[$j]['Passengers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['Passengers'])));
                unset($its[$i]);
            }
        }

        return $its;
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if ($g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
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

    private function parseEmail(Email $email, $text)
    {
        $f = $email->add()->flight();
        $patterns = [
            'time'          => '\d{1,2}:?\d{2}(?:[ ]*?[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.    |    0720
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*?[[:alpha:]]', // Mr. Hao-Li Huang
            'terminal'      => '/^(?:TERMINAL[ ]+)?([A-z \d]+)$/i',
            'notAvialable'  => '/^NA$/i',
            'eTicket'       => '\d{3}[-\s]*\d{7,} ?\d', // 826-850001986 8
        ];

        $pax = $this->re("#Passenger Name[ ]+({$patterns['travellerName']} ?/ ?{$patterns['travellerName']})(?:[ ]{2}|$)#mu", $text);
        $dateRes = strtotime($this->normalizeDate($this->re("#Date Of Issue[ ]+(.{6,})$#m", $text)));

        if (
            preg_match('/FF No[ ]+(.*?)[ ]+GDS/', $text, $m)
            && !empty($m[1])
            && !preg_match($patterns['notAvialable'], $m[1])
        ) {
            $ffNumber = $m[1];
        }

        $ticketNumber = $this->re("#e-Ticket Number[ ]+({$patterns['eTicket']})(?:[ ]{2}|$)#im", $text);
        $tripNum = $this->re("#GDS Reservation Number[ ]+([-A-Z\d]{5,})$#m", $text);
        $textIt = $this->findCutSection($text, null, ['Endorsement Information', 'Additional Information']);
        $f->general()->confirmation($tripNum);
        $f->general()->date($dateRes);
        $f->general()->traveller($pax);

        if (isset($ffNumber)) {
            $f->program()->account($ffNumber, false);
        }

        if (!empty($ticketNumber)) {
            $f->issued()->ticket(str_replace(' ', '', $ticketNumber), false);
        }

        $segments = $this->splitter("#^([ ]*{$this->opt($this->t('FLIGHT'))}[ ]+{$this->opt($this->t('DEPART/ARRIVE'))}[ ]+{$this->opt($this->t('AIRPORT/TERMINAL'))}[ ]+{$this->opt($this->t('CLASS'))}[ ]+{$this->opt($this->t('COUPON VALIDITY'))})#m", $textIt);

        foreach ($segments as $i => $segText) {
            $s = $f->addSegment();

            if (!preg_match("/^(?<top>.+)\n+(?<bottom>[^\n]+[ ]{2}Status:[^\n]+.*)$/s", $segText, $vParts)) {
                $this->logger->alert("Segment-{$i} is invalid!");

                return false;
            }

            if ($conf = $this->re("#Airline Reference:[ ]*([A-Z\d]{5,})$#m", $vParts['bottom'])) {
                $s->setConfirmation($conf);
            } else {
                $s->setConfirmation($tripNum);
            }
            $f->general()->status($this->re('/Status:[ ]*(.+)$/m', $vParts['bottom']));

            $tablePos = [0];

            if (!preg_match('/^((((([ ]+)' . implode('[ ]+)', [$this->opt($this->t('FLIGHT')), $this->opt($this->t('DEPART/ARRIVE')), $this->opt($this->t('AIRPORT/TERMINAL')), $this->opt($this->t('CLASS')), $this->opt($this->t('COUPON VALIDITY'))]) . '/m', $vParts['top'], $matches)) {
                $this->logger->alert("Segment-{$i}: Table headers not found!");

                return false;
            }
            unset($matches[0]);
            asort($matches);

            foreach ($matches as $textHeaders) {
                $tablePos[] = mb_strlen($textHeaders);
            }
            $tablePos[1] -= 2;
            $tablePos[2] -= 2;
            $tablePos[3] -= 2;
            $table = $this->splitCols($vParts['top'], $tablePos);

            // AirlineName
            // FlightNumber
            if (preg_match('/^[ ]*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<flightNumber>\d+)[ ]*$/m', $table[1], $m)) {
                $s->airline()->name($m['airline']);
                $s->airline()->number($m['flightNumber']);
            }

            // DepDate
            // ArrDate
            if (preg_match_all("/^[ ]*(?<date>.{6,}?)\s+(?<time>{$patterns['time']})[ ]*$/m", $table[2], $m, PREG_SET_ORDER) && count($m) === 2) {
                $dateDep = strtotime($this->normalizeDate($m[0]['date']));

                if ($dateDep) {
                    $s->departure()->date(strtotime($m[0]['time'], $dateDep));
                }
                $dateArr = strtotime($this->normalizeDate($m[1]['date']));

                if ($dateArr) {
                    $s->arrival()->date(strtotime($m[1]['time'], $dateArr));
                }
            }

            // DepCode
            // DepartureTerminal
            // ArrCode
            // ArrivalTerminal
            if (preg_match_all("/[\s\S]+?\(\s*(?<airport>[A-Z]{3})\s*\)\s*(?:Terminal:[ ]*(?<terminal>.*))?/", $table[3], $m, PREG_SET_ORDER) && count($m) === 2) {
                $s->departure()->code($m[0]['airport']);
                $s->arrival()->code($m[1]['airport']);

                if (
                    !empty($m[0]['terminal'])
                    && preg_match($patterns['terminal'], $m[0]['terminal'], $matches)
                    && !preg_match($patterns['notAvialable'], $matches[1])
                ) {
                    $s->departure()->terminal($matches[1]);
                }

                if (
                    !empty($m[1]['terminal'])
                    && preg_match($patterns['terminal'], $m[1]['terminal'], $matches)
                    && !preg_match($patterns['notAvialable'], $matches[1])
                ) {
                    $s->arrival()->terminal($matches[1]);
                }
            }

            // Cabin
            // BookingClass
            if (preg_match("/^[ ]*(?<cabin>{$this->opt($this->t('classVariants'))})(?:\s*\(\s*(?<class>[A-Z]{1,2})\s*\))?(?:[ ]{2}|[ ]*$)/m", $table[4], $m)) {
                $s->extra()->cabin($m['cabin']);

                if (!empty($m['class'])) {
                    $s->extra()->bookingCode($m['class']);
                }
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#[\S\s]*(\d{2})[\.\/]*(\d{2})[\.\/]*(\d{2})#',
            '#[\S\s]*(\d{2})-(\D{3,})-(\d{2})[.]*#',
        ];
        $out = [
            '$2/$1/$3',
            '$2 $1 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($text): bool
    {
        foreach ($this->langDetectorsPdf as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function rowColsPos($row): array
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

    private function splitCols($text, $pos = false): array
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
}
