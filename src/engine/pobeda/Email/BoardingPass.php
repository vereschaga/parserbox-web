<?php

namespace AwardWallet\Engine\pobeda\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "pobeda/it-80057299.eml";

    public $detectFrom = "reports@pobeda.aero";
    public $detectSubject = [
        'Посадочный талон по билету',
    ];

    public $detectProvider = ['.pobeda.aero'];

    public $detectBodyHtml = [
        'ru' => ['Во вложении вы найдете посадочный талон'],
    ];
    public $lang = 'ru';
    public $pdfNamePattern = "BoardingPass\.pdf";
    public static $dict = [
        'ru' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                $fileName = $this->getAttachmentName($parser, $pdf);

                if (empty($fileName)) {
                    continue;
                }

                $regexp = "/(?<text>(?:^|\n)(?<pos> *(?<dCode>[A-Z]{3}) {3,}(?<aCode>[A-Z]{3}) {3,}(?<flight>(?:[A-Z\d][A-Z\d]|[A-Z\d][A-Z\d])\d{1,5})\s*\d+)[\s\S]+)"
                    . "\n.+ {3,}(?<date>\d{1,2} [A-Z]{3,4} \d{2}) {3,}(?<rl>[A-Z\d]{5,7})\s*\n\s*"
                    . ".*\d{1,2}:\d{2} {3,}(?<seat>\d{1,3}[A-Z]) {3,}(?<time>\d{1,2}:\d{2})\s*\n/";

                if (preg_match($regexp, $text, $m)) {
                    $bp = $email->add()->bpass();

                    $bp
                        ->setRecordLocator($m['rl'])
                        ->setDepCode($m['dCode'])
                        ->setDepDate(strtotime($m['date'] . ', ' . $m['time']))
                        ->setFlightNumber($m['flight'])
                        ->setAttachmentName($fileName)
                    ;

                    $table = $this->splitCols($m['text'], [0, strlen($m['pos'])]);

                    if (isset($table[1]) && preg_match("/^[\sa-z\-]+$/i", $table[1])) {
                        $bp->setTraveller($this->nice($table[1]));
                    }

                    $seats[$m['flight']][] = $m['seat'];
                }
            }
        }
        $this->parseEmail($email, $seats ?? []);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->striposAll($text, $this->detectProvider) === false) {
                continue;
            }

            if ($this->detectBody($text)) {
                return true;
            }
        }

        if (empty($pdfs)) {
            if ($this->http->XPath->query("//text()[" . $this->contains($this->detectProvider) . "]")->length > 0
                && $this->detectBody('', false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->detectSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
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
        // 2 pdf + 1 html
        return 1;
    }

    private function parseEmail(Email $email, $seats)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Код бронирования:")) . "]", null, true, "/:\s*\s*([A-Z\d]{5,7})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Клиент:")) . "]", null, true, "/:\s*([[:alpha:] \-]+)$/u"))
        ;

        // Issued
        $f->issued()
            ->ticket($this->http->FindSingleNode("//text()[" . $this->contains($this->t("электронному билету номер")) . "]", null, true, "/" . $this->opt($this->t("электронному билету номер")) . "\s*(\d{13})\b/"), false)
        ;

        // Segments
        $s = $f->addSegment();

        $s->airline()
            ->name($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Рейс:")) . "]", null, true, "/:\s*([A-Z\d][A-Z\d]|[A-Z\d][A-Z\d])\d{1,5}\s*$/"))
            ->number($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Рейс:")) . "]", null, true, "/:\s*(?:[A-Z\d][A-Z\d]|[A-Z\d][A-Z\d])(\d{1,5})\s*$/"))
        ;

        $s->departure()
            ->code($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Откуда:")) . "]", null, true, "/\(([A-Z]{3})\)\s*$/"))
            ->name($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Откуда:")) . "]", null, true, "/:\s*(.+?)\s*\(([A-Z]{3})\)\s*$/"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Вылет:")) . "]", null, true, "/:\s*(.+)/")))
        ;

        $s->arrival()
            ->code($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Куда:")) . "]", null, true, "/\(([A-Z]{3})\)\s*$/"))
            ->name($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Куда:")) . "]", null, true, "/:\s*(.+?)\s*\(([A-Z]{3})\)\s*$/"))
            ->noDate()
        ;

        if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber()) && isset($seats[$s->getAirlineName() . $s->getFlightNumber()])) {
            $s->extra()->seats($seats[$s->getAirlineName() . $s->getFlightNumber()]);
        }

        return $email;
    }

    private function getAttachmentName(\PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Disposition');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $matches)) {
            return $matches[1];
        }

        return false;
    }

    private function detectBody($body, $isPdf = true)
    {
        if ($isPdf) {
            foreach ($this->detectBodyPdf as $lang => $reBody) {
                if ($this->stripos($body, $reBody)) {
                    $this->lang = $lang;

                    return true;
                }
            }
        } else {
            foreach ($this->detectBodyHtml as $lang => $reBody) {
                if ($this->http->XPath->query("//text()[" . $this->contains($reBody) . "]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 17.09.2020 в 08:10 по местному времени
            '/^\s*(\d{1,2})\.(\d{1,2})\.(\d{4})\s+в\s+(\d{1,2}:\d{2})\D*$/u',
        ];
        $out = [
            '$1.$2.$3, $4',
        ];
//        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        $str = strtotime(preg_replace($in, $out, $date));

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
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
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function nice($str)
    {
        return preg_replace("/\s+/", ' ', trim($str));
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
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

    private function eq($field, $node = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return $node . '="' . $s . '"';
        }, $field)) . ')';
    }
}
