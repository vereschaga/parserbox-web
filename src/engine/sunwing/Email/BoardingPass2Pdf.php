<?php

namespace AwardWallet\Engine\sunwing\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass2Pdf extends \TAccountChecker
{
    public $mailFiles = "sunwing/it-30900134.eml";
    private $subjects = [
        'en' => ['Boarding Pass for'],
    ];
    private $langDetectors = [
        'en' => ['  Boarding  '],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'airportsEnd' => ['Flight', 'Date'],
            'flightEnd'   => ['Departs', 'Arrives'],
            'timesEnd'    => ['Gate', 'Boarding', 'Seat'],
            'seatEnd'     => ['Name', 'Seq.No', 'Seq. No'],
            'Seq.No'      => ['Seq.No', 'Seq. No'],
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.    |    3pm
        'travellerName' => '[[:alpha:]][-.\'\/[:alpha:] ]*[[:alpha:]]', // Mr. RYBANSKY / MICHAEL
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@sunwing.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true) {
            return false;
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
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
                $pdfFileName = $this->getAttachmentName($parser, $pdf);
                $this->parseEmailPdf($email, $textPdf, $pdfFileName);
            }
        }

        $email->setType('BoardingPass2Pdf' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmailPdf(Email $email, $text = '', $fileName = '')
    {
        $f = $email->add()->flight();

        $s = $f->addSegment();

        // depCode
        // arrCode
        $airportsText = preg_match("/^([\s\S]+?)\n+[ ]*{$this->opt($this->t('airportsEnd'))}(?:[ ]{2}|[ ]*\n)/", $text, $m) ? $m[1] : '';

        if (preg_match('/(?:^|\n)[ ]*([A-Z]{3})\s+([A-Z]{3})[ ]*(?:\n|$)/', $airportsText, $matches)) {
            $pos1 = strlen(preg_match("/^(.+?)\b{$matches[1]}\b/m", $airportsText, $m) ? $m[1] : '');
            $pos2 = strlen(preg_match("/^(.+?)\b{$matches[2]}\b/m", $airportsText, $m) ? $m[1] : '');

            if ($pos1 > $pos2) {
                $s->arrival()->code($matches[1]);
                $s->departure()->code($matches[2]);
            } else {
                $s->departure()->code($matches[1]);
                $s->arrival()->code($matches[2]);
            }
        }

        $flightText = preg_match("/\n[ ]*({$this->opt($this->t('airportsEnd'))}[\s\S]+?)\n+[ ]*{$this->opt($this->t('flightEnd'))}(?:[ ]{2}|[ ]*\n)/", $text, $m) ? $m[1] : '';
        $tablePos = [0];

        if (preg_match("/^(.*){$this->opt($this->t('Date'))}/m", $flightText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($flightText, $tablePos);

        if (count($table) !== 2) {
            $this->logger->alert('Flight table wrong!');

            return false;
        }

        // airlineName
        // flightNumber
        if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/m', $table[0], $m)) {
            $s->airline()
                ->name($m['airline'])
                ->number($m['flightNumber'])
            ;
        }

        $date = 0;

        if (preg_match("/{$this->opt($this->t('Date'))}\s+(.{6,})/", $table[1], $m)) {
            $date = $m[1];
        }

        $timesText = preg_match("/\n[ ]*({$this->opt($this->t('flightEnd'))}[\s\S]+?)\n+[ ]*{$this->opt($this->t('timesEnd'))}(?:[ ]{2}|[ ]*\n)/", $text, $m) ? $m[1] : '';
        $tablePos = [0];

        if (preg_match("/^(.*){$this->opt($this->t('Arrives'))}/m", $timesText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($timesText, $tablePos);

        if (count($table) !== 2) {
            $this->logger->alert('Times table wrong!');

            return false;
        }

        // depDate
        if ($date && preg_match("/{$this->opt($this->t('Departs'))}\s+({$this->patterns['time']})/", $table[0], $m)) {
            $s->departure()->date2($date . ' ' . $m[1]);
        }

        // arrDate
        if ($date && preg_match("/{$this->opt($this->t('Arrives'))}\s+({$this->patterns['time']})/", $table[1], $m)) {
            $s->arrival()->date2($date . ' ' . $m[1]);
        }

        $seatText = preg_match("/\n[ ]*({$this->opt($this->t('timesEnd'))}[\s\S]+?)\n+[ ]*{$this->opt($this->t('seatEnd'))}(?:[ ]{2}|[ ]*\n)/", $text, $m) ? $m[1] : '';
        $tablePos = [0];

        if (preg_match("/^(.*){$this->opt($this->t('Seat'))}/m", $seatText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($seatText, $tablePos);

        if (count($table) !== 2) {
            $this->logger->alert('Seat table wrong!');

            return false;
        }

        // seats
        if (preg_match("/{$this->opt($this->t('Seat'))}\s+(\d{1,5}[A-Z])\b/", $table[1], $m)) {
            $s->extra()->seat($m[1]);
        }

        $passengerText = preg_match("/\n[ ]*({$this->opt($this->t('seatEnd'))}[\s\S]+?)\n+[ ]*{$this->opt($this->t('Class'))}(?:[ ]{2}|[ ]*\n)/", $text, $m) ? $m[1] : '';
        $tablePos = [0];

        if (preg_match("/^(.*){$this->opt($this->t('Seq.No'))}/m", $passengerText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($passengerText, $tablePos);

        if (count($table) !== 2) {
            $this->logger->alert('Passenger table wrong!');

            return false;
        }

        // travellers
        if (preg_match("/{$this->opt($this->t('Name'))}\s+({$this->patterns['travellerName']})[ ]*$/m", $table[0], $m)) {
            $f->general()->traveller($m[1]);
        }

        // bookingCode
        if (preg_match("/^[ ]*{$this->opt($this->t('Class'))}\s+^[ ]*([A-Z]{1,2})$/m", $text, $m)) {
            $s->extra()->bookingCode($m[1]);
        }

        // confirmation number
        $f->general()->noConfirmation();

        // Boarding Pass
        $bp = $email->createBoardingPass();
        $bp->setAttachmentName($fileName);
        $bp->setDepCode($s->getDepCode());
        $bp->setFlightNumber($s->getFlightNumber());
        $bp->setDepDate($s->getDepDate());

        if (!empty($f->getTravellers()[0])) {
            $bp->setTraveller($f->getTravellers()[0][0]);
        }

        return true;
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang($text = ''): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (empty($text) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                } elseif (!empty($text) && strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getAttachmentName(\PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }
}
