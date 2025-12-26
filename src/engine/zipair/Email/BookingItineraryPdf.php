<?php

namespace AwardWallet\Engine\zipair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "zipair/it-464544971.eml, zipair/it-462977771.eml, zipair/it-477621788-ja.eml, zipair/it-365657448-th.eml";

    public $lang = '';

    public static $dictionary = [
        'ja' => [
            'confNumber'      => ['予約番号'],
            'segmentsStart'   => ['便情報'],
            'segmentsEnd'     => ['ご搭乗者情報'],
            'passengersStart' => ['ご搭乗者情報'],
            'passengersEnd'   => [
                '座席について',
                '手荷物について',
                '乗り継ぎ便について',
                'チェックインについて',
                'ご搭乗にあたっての注意事項',
            ],
            'Booking Date'        => '予約日',
            'Contact Information' => 'ご連絡先',
        ],
        'th' => [
            'confNumber'      => ['หมายเลขการจอง'],
            'segmentsStart'   => ['ข้อมูลเทียวบิน'],
            'segmentsEnd'     => ['ข้อมูลผู้โดยสาร'],
            'passengersStart' => ['ข้อมูลผู้โดยสาร'],
            'passengersEnd'   => [
                'สัมภาระ',
                'เกี ยวกับการเช็คอิน',
                'ข้อควรระวังบนเครื องบิน',
                'เกี ยวกับเที ยวบินเชื อมต่อ',
            ],
            'Booking Date'        => 'วันทีทําการจอง',
            'Contact Information' => 'สถานทีติดต่อ',
        ],
        'en' => [
            'confNumber'      => ['Confimation Number', 'Confirmation Number'],
            'segmentsStart'   => ['Flight Information'],
            'segmentsEnd'     => ['Passenger Information'],
            'passengersStart' => ['Passenger Information'],
            'passengersEnd'   => [
                'About Check-in',
                'About Baggage',
                'About Connecting Flights',
                'ZIPAIR Important Notice onboard our flight',
            ],
            // 'Booking Date' => '',
            // 'Contact Information' => '',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@zipair.net') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'ZIPAIR - Booking Itinerary #') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (stripos($textPdf, '.zipair.net/') === false
                && stripos($textPdf, 'www.zipair.net') === false
                && stripos($textPdf, '@zipair.net') === false
                && stripos($textPdf, 'ZIPAIR Tokyo Inc') === false
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

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);
            }
        }

        $email->setType('BookingItineraryPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, string $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $text = preg_replace("/^(.+?)\n+[ ]*{$this->opt($this->t('passengersEnd'))}[ :*]\n.*$/s", '$1', $text);

        $f = $email->add()->flight();

        $headerText = $this->re("/^(.+?)\n+[ ]*{$this->opt($this->t('segmentsStart'))}/s", $text);
        $segmentsText = $this->re("/\n[ ]*{$this->opt($this->t('segmentsStart'))}.*\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('segmentsEnd'))}/", $text);
        $passengersText = $this->re("/\n[ ]*{$this->opt($this->t('passengersStart'))}.*\n+([\s\S]+)$/", $text);

        $tablePos = [0];

        if (preg_match("/^(.+[ ]{2}){$this->opt($this->t('Contact Information'))}$/m", $headerText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]) - 2;
        }
        $table = $this->splitCols($headerText, $tablePos);

        if (count($table) === 2) {
            $headerText = implode("\n\n", $table);
        }

        if (preg_match("/^[ ]*({$this->opt($this->t('confNumber'))})[: ]*\n+[ ]*([A-Z\d]{5,})$/m", $headerText, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        $bookingDate = strtotime($this->normalizeDate($this->re("/^[ ]*{$this->opt($this->t('Booking Date'))}[: ]*\n+[ ]*(.*\b\d{4}\b.*)$/m", $headerText)));
        $f->general()->date($bookingDate);

        $segments = $this->splitText($segmentsText, "/^([ ]*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d+[ ]+\S.{6,})$/m", true);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            $tablePos = [0];

            if (preg_match("/^(([ ]*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<number>\d+)[ ]+)\S.+[ ]{2})\S.+\n/", $sText, $m)) {
                $tablePos[] = mb_strlen($m[2]);
                $tablePos[] = mb_strlen($m[1]);
                $s->airline()->name($m['name'])->number($m['number']);

                if (preg_match_all("/.{5}\S[ ]+{$this->opt([$m['name'] . $m['number'], $m['name'] . ' ' . $m['number']])}[ ]+(\d+ ?[A-Z])(?: |$)/m", $passengersText, $seatMatches)) {
                    // 22B    |    22 B
                    $s->extra()->seats(str_replace(' ', '', $seatMatches[1]));
                }
            }
            $table = $this->splitCols($sText, $tablePos);

            if (count($table) !== 3) {
                $this->logger->debug('Wrong flight segment!');

                continue;
            }

            if (preg_match($pattern = "/^\s*(?<airport>[\s\S]{3,}?)\n+[ ]*(?<date>.*\b\d{4}\b.*?)\s+(?<time>{$patterns['time']})/", $table[1], $m)) {
                $s->departure()
                    ->name(preg_replace('/\s+/', ' ', $m['airport']))->noCode()
                    ->date(strtotime($m['time'], strtotime($this->normalizeDate($m['date']))));
            }

            if (preg_match($pattern, $table[2], $m)) {
                $s->arrival()
                    ->name(preg_replace('/\s+/', ' ', $m['airport']))->noCode()
                    ->date(strtotime($m['time'], strtotime($this->normalizeDate($m['date']))));
            }
        }

        if (preg_match_all("/^[ ]*\d{1,3}[ ]+({$patterns['travellerName']})(?:[ ]{12}\D+)$/m", $passengersText, $paxMatches)
            && strpos(implode($paxMatches[1]), '  ') === false
        ) {
            $f->general()->travellers($paxMatches[1], true);
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['segmentsStart'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['confNumber']) !== false
                && $this->strposArray($text, $phrases['segmentsStart']) !== false
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{4}) *\/ *(\d{1,2}) *\/ *(\d{1,2})(?: *\( *[^)(\d]+ *\))?$/u', $text, $m)) {
            // 2023/10/25 (พุธ)    |    2023/08/20
            $year = $m[1];
            $month = $m[2];
            $day = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
