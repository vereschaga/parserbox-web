<?php

namespace AwardWallet\Engine\singaporeair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPassPdf2 extends \TAccountChecker
{
    public $mailFiles = "singaporeair/it-305324909.eml";
    public $subjects = [
        'Your boarding pass ',
    ];

    public $lang = 'en';
    public $subject = '';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@singaporeair.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'Singapore Airlines') !== false && strpos($text, 'Airline use') !== false && strpos($text, 'GROUP') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]singaporeair\.com$/', $from) > 0;
    }

    public function BoardingPassPDF(Email $email, $text, $fileName)
    {
        $traveller = '';

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/{$this->opt($this->t('Your boarding pass'))}\s*([A-Z\d]{6})$/", $this->subject));

        $s = $f->addSegment();

        $name = $this->re("/^((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*\d{2,4}\n{5,}/", $text);
        $number = $this->re("/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{2,4})\n{5,}/", $text);

        $s->airline()
            ->name($name)
            ->number($number)
            ->operator($this->re("/Operating airline\n(.+)/", $text));

        $s->departure()
            ->code($this->re("/([A-Z]{3})\s*[A-Z]{3}\n/", $text))
            ->date(strtotime($this->re("/Scheduled time of departure\n(.+)/", $text)));

        $s->arrival()
            ->code($this->re("/[A-Z]{3}\s*([A-Z]{3})\n/", $text))
            ->date(strtotime($this->re("/Scheduled time of arrival\n(.+)/", $text)));

        $s->extra()
            ->aircraft($this->re("/Aircraft type\n(.+)/", $text));

        if (preg_match_all("/SEAT\n+.+\-?\s+(\d+[A-Z])\n/u", $text, $m)) {
            $s->extra()
                ->seats($m[1]);
        }

        $cabin = $this->re("/\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{2,4}\s+[•].+[•]\s*(\D{5,})\n{3,}/", $text);

        if (!empty($cabin)) {
            $s->extra()
                ->cabin($cabin);
        }

        $terminalText = $this->re("/({$s->getDepCode()}\s*{$s->getArrCode()}\n(?:.+\n){3,})\n{3,}/u", $text);
        $terminalTable = $this->splitCols($terminalText, [0, 25]);
        $depTerminal = $this->re("/Terminal\s*(.+)/", $terminalTable[0]);

        if (!empty($depTerminal)) {
            $s->departure()
                ->terminal($depTerminal);
        }

        $arrTerminal = $this->re("/Terminal\s*(.+)/", $terminalTable[1]);

        if (!empty($arrTerminal)) {
            $s->arrival()
                ->terminal($arrTerminal);
        }

        if (preg_match_all("/(?:[A-Z]{4})?\n+\s+(.+)\n{2,}[A-Z]{3}\s*[A-Z]{3}\n/", $text, $m)) {
            foreach ($m[1] as $pax) {
                $f->general()
                    ->traveller($pax);

                $b = $email->add()->bpass();

                $b->setTraveller($pax)
                    ->setFlightNumber($s->getAirlineName() . ' ' . $s->getFlightNumber())
                    ->setDepCode($this->re("/([A-Z]{3})\s*[A-Z]{3}\n/", $text))
                    ->setRecordLocator($this->re("/{$this->opt($this->t('Your boarding pass'))}\s*([A-Z\d]{6})$/", $this->subject))
                    ->setAttachmentName($fileName);

                if (preg_match("/BOARDING.*\n+\s*(?<time>[\d\:]+).*\s*(?<date>\d+\s*\w+)\n+Scheduled time of departure\n.*\s(?<year>\d{4})/", $text, $m)) {
                    $b->setDepDate(strtotime($m['date'] . ' ' . $m['year'] . ', ' . $m['time']));
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $fileName = $this->getAttachmentName($parser, $pdf);

            $this->BoardingPassPDF($email, $text, $fileName);
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

    protected function getAttachmentName(PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $matches)) {
            return $matches[1];
        }

        return false;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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
}
