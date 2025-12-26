<?php

namespace AwardWallet\Engine\goibibo\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightTicket extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-125171164.eml, goibibo/it-158685516.eml";
    public $subjects = [
        '/Flight ticket/su',
    ];

    public $lang = 'en';
    public $year;
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@basantandco.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
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

            if (strpos($text, 'Gibibo Support') !== false && strpos($text, 'Booking Id:') !== false && strpos($text, 'CANCELLATION AND DATE CHANGE CHARGES') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]@basantandco\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        if (preg_match("/Booking\s*Id\:\s*[A-Z\d]+\n\s*\w+\n+\w+\,\s*(?<day>\d+\s*\w+)\s*\'(?<year>\d{2})[ ]{5,}\s*\D+TO/", $text, $m)) {
            $this->year = '20' . $m['year'];
            $f->general()
                ->date(strtotime($m['day'] . ' ' . $this->year));
        }

        //duration Booking\s*Id\:\s*[A-Z\d]+\n\s*\w+\n+.+[ ]{5,}\s*\D+(\d+h\s\d+m)\n

        $seats = [];

        if (preg_match_all("/\d\.\s*(?<pax>\D+)\,\s*(?:Adult|Child)\s*(?<conf>[A-Z\d]{6})\s*(?<ticket>[A-Z\d]{6})\s*(?<seats>\d+[A-Z])/", $text, $m)) {
            $f->general()
                ->travellers($m['pax'], true);

            $f->setTicketNumbers(array_unique($m['ticket']), false);

            $confs = array_unique(array_filter($m['conf']));

            foreach ($confs as $conf) {
                $f->general()
                    ->confirmation($conf);
            }

            $seats = $m['seats'];
        }

        $paxText = $this->re("/{$this->opt($this->t('Passenger Information'))}\n{$this->opt($this->t('First name(s) and name(s)'))}\n(.+)\nFlight Information/su", $text);

        if (preg_match_all("/(?:^|\n)\d\s*([[:alpha:]][-.'’[:alpha:] ]*[[:alpha:]])/", $paxText, $m)) {
            $f->general()
                ->travellers(str_replace(['MSTR', 'MRS', 'MR', 'MS'], '', $m[1]), true);
        }

        $flightText = $this->re("/\n{4,}(.+[A-Z]{3}\s+[A-Z]{3}\s+.+)\n{4,}PASSENGER NAME/s", $text);

        if (!empty($flightText)) {
            $s = $f->addSegment();

            $flightParts = $this->splitCols($flightText, [0, 20, 60, 75]);

            if (preg_match("/([A-Z\d]{2})\-(\d{2,4})/", $flightParts[0], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match("/(?<code>[A-Z]{3})\s*\D+\s*(?<date>[\d\:]+\D+\,\s*\d+\s*\w+)\s*\D*(?:Terminal\s*(?<terminal>.+))?$/s", $flightParts[1], $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['date'] . ' ' . $this->year))
                    ->code($m['code']);

                if (isset($m['terminal'])) {
                    $s->departure()
                        ->terminal($m['terminal']);
                }
            }

            if (preg_match("/\s*(\d+h\s*\d+m)\s*(\D+)/su", $flightParts[2], $m)) {
                $s->extra()
                    ->duration($m[1])
                    ->cabin($m[2]);
            }

            if (count($seats) > 0) {
                $s->extra()
                    ->seats($seats);
            }

            if (preg_match("/(?<code>[A-Z]{3})\s*\D+\s*(?<date>[\d\:]+\D+\,\s*\d+\s*\w+)\s*\D*(?:Terminal\s*(?<terminal>.+))?$/s", $flightParts[3], $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($m['date'] . ' ' . $this->year))
                    ->code($m['code']);

                if (isset($m['terminal'])) {
                    $s->arrival()
                        ->terminal($m['terminal']);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseFlightPDF($email, $text);
        }

        if (preg_match("/Total Invoice Amount\s*([\d\.]+)\s*([A-Z]{3})/u", $text, $m)) {
            $email->price()
                ->total($m[1])
                ->currency($m[2]);
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

    private function normalizeDate($date)
    {
        $in = [
            // 15:10 hrs, 11 May 2022
            "/^([\d\:]+)\s*\w+\,\s*(\d+)\s*(\w+)\s*(\d{4})$/iu",
        ];
        $out = [
            "$2 $3 $4, $1",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
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
}
