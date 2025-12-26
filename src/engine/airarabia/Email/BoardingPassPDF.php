<?php

namespace AwardWallet\Engine\airarabia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPassPDF extends \TAccountChecker
{
    public $mailFiles = "airarabia/it-706550795.eml, airarabia/it-707525312.eml, airarabia/it-840549049.eml";
    public $lang = '';
    public $pdfNamePattern = "Boarding\s*pass.*pdf";

    public $subjects = [
        'boarding pass confirmation',
    ];

    public $pdfFileName;
    public $currentFlight;
    public $currentSegment;
    public $flightArray = [];

    public static $dictionary = [
        "en" => [
            'splittingPhrases' => ['Flight Day Timings', 'Flight Day', 'Day Timings'],
            'Date' => ['Date'],
            'Departure' => ['Departure'],
            'Seat' => ['Seat'],
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || strpos($headers['subject'], 'Air Arabia') === false)
        ) {
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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectProv = $this->detectEmailFromProvider( rtrim($parser->getHeader('from'), '> ') )
            || $this->http->XPath->query("//text()[contains(normalize-space(),'Air Arabia')]")->length > 0;

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || !$detectProv) {
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
        return preg_match('/[@.]airarabia\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text): void
    {
        $f = '';

        $tablePos = [0];
        if (preg_match("/\n(.+[ ]{2}){$this->opt($this->t('Passenger'))}[: ]*\n/", $text, $matches)) {
            $tablePos[] = mb_strlen($matches[1]) - 5;
        } else {
            $tablePos[] = 50;
        }
        $flightText = $this->splitCols($text, $tablePos);

        $flightInfo = $this->re("/^((?:.*\n){1,10}?)\n+[ ]*{$this->opt($this->t('Date'))}[: ]/", $flightText[1]);

        $tablePos = [0];
        if (preg_match("/^((.{10,}[ ]{2})(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5})(?:[ ]{2}|$)/m", $flightInfo, $matches)) {
            $tablePos[] = mb_strlen($matches[2]);
            $tablePos[] = mb_strlen($matches[1]);
        } else {
            $tablePos[] = 40;
            $tablePos[] = 60;
        }
        $flightTable = $this->splitCols($flightInfo, $tablePos);

        $segConf = $this->re("/\-([A-Z\d]{6})\.pdf/", $this->pdfFileName);

        $flight = $this->re("/\s((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5})\s/", $text);

        if (in_array($flight, $this->flightArray) === false) {
            $f = $this->currentFlight = $email->add()->flight();

            $conf = $this->re("/Reservation number\n\s+(\d{5,})\n/", $text);
            $f->general()
                ->confirmation($conf);

            $traveller = $this->re("/Passenger\n\s*(.+)/", $flightText[1]);

            if (!empty($traveller)) {
                $f->general()->traveller($traveller, true);
            }

            $ticket = $this->re("/E-ticket[: ]*\n\s*({$this->patterns['eTicket']})\n/", $flightText[1]);

            if (!empty($ticket)) {
                $f->addTicketNumber($ticket, false, $traveller);
            }

            $s = $this->currentSegment = $f->addSegment();

            if (preg_match("/{$this->opt($this->t('Date'))}[:\s]+{$this->opt($this->t('Departure'))}[:\s]*{$this->opt($this->t('Seat'))}[: ]*(?:{$this->opt($this->t('Zone'))}[: ]*)?\n\s*(?<year>\d{4})-(?<month>\d{1,2})-(?<day>\d{1,2})\s*(?<time>{$this->patterns['time']})\s*(?<seat>\d+[A-Z])\b/", $flightText[1], $m)) {
                $s->departure()
                    ->date(strtotime($m['day'] . '.' . $m['month'] . '.' . $m['year'] . ', ' . $m['time']));

                $s->addSeat($m['seat'], false, false, $traveller);
            }

            $pattern = "/^\s*(?<name>.{2,}?)\s+(?<code>[A-Z]{3})\s*$/s";

            if (preg_match($pattern, $flightTable[0], $m)) {
                $s->departure()
                    ->name(preg_replace('/\s+/', ' ', $m['name']))
                    ->code($m['code']);
            }

            if (preg_match("/^\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])) ?(?<fNumber>\d{1,5})\s*$/m", $flightTable[1], $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $this->flightArray[] = $m['aName'] . $m['fNumber'];
            }

            if (preg_match($pattern, $flightTable[2], $m)) {
                $s->arrival()
                    ->name(preg_replace('/\s+/', ' ', $m['name']))
                    ->code($m['code'])
                    ->noDate();
            }
            $s->setConfirmation($segConf);
        } else {
            $f = $this->currentFlight;
            $traveller = $this->re("/Passenger\n\s*(.+)/", $flightText[1]);

            if (!empty($traveller)) {
                $f->general()->traveller($traveller, true);
            }

            $ticket = $this->re("/E-ticket[: ]*\n\s*({$this->patterns['eTicket']})\n/", $flightText[1]);

            if (!empty($ticket)) {
                $f->addTicketNumber($ticket, false, $traveller);
            }

            $s = $this->currentSegment;

            if (preg_match("/{$this->opt($this->t('Date'))}[:\s]+{$this->opt($this->t('Departure'))}[:\s]*{$this->opt($this->t('Seat'))}[: ]*(?:{$this->opt($this->t('Zone'))}[: ]*)?\n\s*\d{4}-\d{1,2}-\d{1,2}\s*{$this->patterns['time']}\s*(?<seat>\d+[A-Z])\b/", $flightText[1], $m)) {
                $s->addSeat($m['seat'], false, false, $traveller);
            }

            $s->setConfirmation($segConf);
        }

        $b = $email->add()->bpass();
        $b->setFlightNumber($s->getAirlineName() . $s->getFlightNumber());
        $b->setTraveller($traveller);
        $b->setDepDate($s->getDepDate());
        $b->setDepCode($s->getDepCode());
        $b->setRecordLocator($segConf);
        $b->setAttachmentName($this->pdfFileName);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || !$this->assignLang($textPdf)) {
                continue;
            }

            $this->pdfFileName = $this->getAttachmentName($parser, $pdf);
            $bPassArray = $this->splitText($textPdf, "/^\D*{$this->opt($this->t('splittingPhrases'))}$/mu");

            foreach ($bPassArray as $bPass) {
                $this->ParseFlightPDF($email, $bPass);
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

    private function re(string $re, ?string $str, $c = 1): ?string
    {
        if (preg_match($re, $str ?? '', $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function assignLang(?string $text): bool
    {
        if ( empty($text) || !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['Date']) || empty($phrases['Departure']) || empty($phrases['Seat']) ) {
                continue;
            }
            if (preg_match("/\s{$this->opt($phrases['Date'])}[ ]+{$this->opt($phrases['Departure'])}[ ]+{$this->opt($phrases['Seat'])}\s/", $text)) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
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

    private function getAttachmentName(PlancakeEmailParser $parser, $pdf): ?string
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
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
}
