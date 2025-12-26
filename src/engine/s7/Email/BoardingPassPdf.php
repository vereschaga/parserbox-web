<?php

namespace AwardWallet\Engine\s7\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "s7/it-4004737.eml, s7/it-9229984.eml, s7/it-9654011.eml, s7/it-9693187.eml, s7/it-67342411.eml";

    public $from = "web-checkin@s7.ru";
    public $provider = "@s7.ru";
    public $reSubject = [
        "en" => "Your boarding pass",
        "ru" => "Ваш посадочный талон",
    ];
    public $reBody = 's7.ru';
    public $reBody2 = [
        "en" => "Boarding pass",
        "ru" => "Boarding pass",
    ];

    public $lang;
    public $detectLang = [
        "ru" => ["Что дальше", 'Не забудьте указать номер участника S7 Priority при онлайн регистрации и получите мили за ваш полет', "Ваш посадочный"],
        "en" => ["Next steps", 'Boarding pass'],
    ];

    public static $prov = [
        "s7"        => ['S7'],
        "cyprusair" => ['Cyprus Airways'],
    ];
    public $provCode;

    public $pdfPattern = '.*\.pdf';
    public $result;

    public static $dictionary = [
        "en" => [
            //			"Boarding pass" => "",
            //			"Next steps" => "",
            //			"Reservation" => "",
        ],
        "ru" => [
            "Boarding pass"  => "Посадочный талон",
            "Next steps"     => ["Что дальше?", "Next steps"],
            "Reservation"    => "Бронь",
        ],
    ];

    private $year = null;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!empty($this->provCode)) {
            $email->setProviderCode($this->provCode);
        }

        $this->year = date('Y', strtotime($parser->getDate()));
        $pdf = $parser->searchAttachmentByName($this->pdfPattern);

        $attached = false;

        if (count($pdf) > 0) {
            $arr = $parser->getAttachmentBody(array_shift($pdf));
            $body = \PDF::convertToText($arr, false);
            $attached = true;
        } else {
            $body = $parser->getHTMLBody();
        }

        foreach ($this->detectLang as $lang => $detect) {
            if (is_string($detect) && stripos($body, $detect) !== false) {
                $this->lang = $lang;

                break;
            } elseif (is_array($detect)) {
                foreach ($detect as $dt) {
                    if (stripos($body, $dt) !== false) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }
        }

        if ($attached) {
            $this->parsePdf($email, $body);
        } else {
            $this->parseEmail($email);
        }

        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->from) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdf) > 0) {
            $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));
        } else {
            $body = $parser->getHTMLBody();
        }

        foreach (self::$prov as $prov => $detects) {
            foreach ($detects as $detect) {
                if ($this->http->XPath->query("//text()[{$this->contains($detect)}]")->length > 0) {
                    $this->provCode = $prov;
                }
            }
        }

        if (strpos($body, $this->reBody) === false && empty($this->provCode)) {
            return false;
        }

        foreach ($this->detectLang as $detect) {
            if (is_string($detect) && strpos($body, $detect) !== false) {
                return true;
            } elseif (is_array($detect)) {
                foreach ($detect as $dt) {
                    if (stripos($body, $dt) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->provider) !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en', "ru"];
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$prov);
    }

    protected function parsePdf(Email $email, $text)
    {
//        $this->logger->debug($text);
        $f = $email->add()->flight();

        $f->general()->confirmation($this->http->FindSingleNode('//*[contains(text(), "' . $this->t('Reservation') . '")]/ancestor::tr[1]/following-sibling::tr[1]/td[1]',
            null, false, '/[A-Z\d]{5,6}/'));

        $seats = [];
        $segments = $this->split("#\n( *(?:{$this->opt($this->t('Boarding pass'))}|{$this->opt($this->t('Next steps'))})(?: {3,}|\n)(?:.*\n+){2})#u", "\n\n" . $text);

        foreach ($segments as $segment) {
            $s = $this->parseSegments($f, $segment);

            if (!$s) {
//                $s = $this->parseSegments2($f, $segment);
            }

            if (!$s) {
                $s = $f->addSegment();
            }

            if (!empty($s->getSeats()) && !empty($seats[$s->getAirlineName() . $s->getFlightNumber()])) {
                $seats[$s->getAirlineName() . $s->getFlightNumber()] = array_merge($seats[$s->getAirlineName() . $s->getFlightNumber()],
                    $s->getSeats());
            } else {
                if (!empty($s->getSeats())) {
                    $seats[$s->getAirlineName() . $s->getFlightNumber()] = $s->getSeats();
                }
            }
        }
        $segments = $f->getSegments();

        if (count($segments) > 1) {
            foreach ($segments as $key => $s) {
                foreach ($segments as $segResult) {
                    if ($s->getFlightNumber() == $segResult->getFlightNumber() && $s->getAirlineName() == $segResult->getAirlineName() && $s->getDepCode() == $segResult->getDepCode()) {
                        $segments = $f->removeSegment($segResult);

                        break 2;
                    }
                }
            }

            foreach ($f->getSegments() as $s) {
                if (!empty($seats[$s->getAirlineName() . $s->getFlightNumber()])) {
                    $s->setSeats($seats[$s->getAirlineName() . $s->getFlightNumber()]);
                }
            }
        }
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/FLIGHT\n(.*)Price details')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>FLIGHT</b> <i>cut text</i> <b>Price details</b>.
     *
     * @param string $input
     * @param string $searchStart
     * @param string $searchFinish
     *
     * @return string|bool
     */
    protected function findCutSection($input, $searchStart, $searchFinish)
    {
        $input = mb_stristr(mb_stristr($input, $searchStart), $searchFinish, true);

        if (!empty($searchFinish) && !empty($input)) {
            return mb_substr($input, mb_strlen($searchStart));
        }

        return false;
    }

    // it-67342411.eml
    private function parseSegments2(\AwardWallet\Schema\Parser\Common\Flight $f, $text)
    {
        $this->logger->notice(__METHOD__);

        if (preg_match('#/ Flight\s+([A-Z\d]{2})\s+(\d{3,4})#', $text, $matches)) {
            $s = $f->addSegment();
            $s->airline()->name($matches[1]);
            $s->airline()->number($matches[2]);
        } else {
            return null;
        }
        // 06 ОКТ 2020 DME
        if (preg_match('/(\d+ [[:upper:]]{3} \d{4})\s+([A-Z]{3})/u', $text, $matches)) {
            $dateDep = new \DateTime($this->normalizeDate($matches[1]));
            $s->departure()->code($matches[2]);
        }

        if (preg_match('/\d+ [[:upper:]]{3} \d{4}\s+[A-Z]{3}.+?(\d+ [[:upper:]]{3} \d{4})\s+([A-Z]{3})/su', $text, $matches)) {
            $dateArr = new \DateTime($this->normalizeDate($matches[1]));
            $s->arrival()->code($matches[2]);
        }

        if (!empty($dateDep) && preg_match('#/ Depart\s+(\d{2}):(\d{2})#i', $text, $matches)) {
            $dateDep->setTime($matches[1], $matches[2]);
            $s->departure()->date($dateDep->getTimestamp());
        }

        if (!empty($dateArr) && preg_match('#/ Arrive\s+(\d{2}):(\d{2})#i', $text, $matches)) {
            $dateArr->setTime($matches[1], $matches[2]);
            $s->arrival()->date($dateArr->getTimestamp());
        }

        if (preg_match('#/ Seat\s+([A-Z\d]{2,3})#', $text, $matches)) {
            $s->extra()->seat($matches[1]);
        }

        if (preg_match('#/ Class\s+([\w]+\b)\s*\(([A-Z]{1})\)#u', $text, $matches)) {
            $s->extra()->cabin($matches[1]);
            $s->extra()->bookingCode($matches[2]);
        }

        return $s;
    }

    private function parseSegments(\AwardWallet\Schema\Parser\Common\Flight $f, $text)
    {
        $col = 0;

        if (preg_match("/(?:^|\n)(.+ {3}){$this->opt($this->t('Next steps'))}/u", $text, $m)) {
            $col = mb_strlen($m[1]);
        }

        if (empty($col)) {
            return null;
        }

        $table = $this->SplitCols($text, [0, $col], false);

        $textInfo = $table[0] ?? '';
//        $this->logger->debug('$textInfo = '.print_r( $textInfo,true));

        if (preg_match('#\bSEQ\s*\d{1,4}\s*\n *([[:alpha:] ]{4,}?)(?: {3,}|\n)#', $textInfo, $matches)) {
            $f->general()->traveller($matches[1]);
        }

        if (preg_match('/\n\s*ETK:\s+([\dA-Z]{5,})(?:\s{2,}|\n)/', $textInfo, $matches)) {
            $f->issued()->ticket($matches[1], false);
        }

        if (preg_match('/\n\s*FFP\s+([\dA-Z]{5,})(?:\s{2,}|\n)/', $textInfo, $matches)) {
            $f->program()->account($matches[1], false);
        }

        if (preg_match('# {3}(?:\w+ / )?Flight\s*(?:\n.*){1,3}? {3}([A-Z\d]{2}) ?(\d{1,5})\s*\n#u', $textInfo, $matches)) {
            $s = $f->addSegment();
            $s->airline()->name($matches[1]);
            $s->airline()->number($matches[2]);
        } else {
            return null;
        }

        if (preg_match('#\n(?:\w+ / )?Seat(?:.*\n){1,3}? {0,10}(\d{1,3}[A-Z])(?: {3}|\n)#u', $textInfo, $matches)) {
            $s->extra()->seat($matches[1]);
        }

        if (preg_match('#(\n.+ Depart\s*(?:\n.*)+?)\n.+ Arrive\s*\n#u', $textInfo, $matches)) {
            $matches[1] = preg_replace("/^(.{8,}?) {3,}.*/m", '$1', $matches[1]);

            if (preg_match('/^\s*(.+)\s+([A-Z]{3})\s+([\w,\s+()\-.]+)\s*$/u', $matches[1], $m1)) {
                $dateDep = new \DateTime($this->normalizeDate($m1[1]));
                $s->departure()
                    ->code($m1[2])
                    ->name(preg_replace("/\s+/", ' ', $m1[3]));
            }
        }

        if (preg_match('#(\n.+ Arrive\s*(?:\n.*)+?)\n\s*ETK *:#u', $textInfo, $matches)) {
            $matches[1] = preg_replace("/^(.{8,}?) {3,}.*/m", '$1', $matches[1]);

            if (preg_match('/^\s*(.+)\s+([A-Z]{3})\s+([\w,\s+()\-.]+)\s*$/u', $matches[1], $m1)) {
                $dateArr = new \DateTime($this->normalizeDate($m1[1]));
                $s->arrival()
                    ->code($m1[2])
                    ->name(preg_replace("/\s+/", ' ', $m1[3]));
            }
        }

        if (!empty($dateDep) && preg_match('# {3}(?:\w+ / )?Depart\s*(?:\n+.*){1,4} {3}(\d{2}):(\d{2})\s*\n#u', $textInfo, $matches)) {
            $dateDep->setTime($matches[1], $matches[2]);
            $s->departure()->date($dateDep->getTimestamp());
        }

        if (!empty($dateDep) && preg_match('# {3}(?:\w+ / )?Arrive\s*(?:\n+.*){1,4} {3}(\d{2}):(\d{2})\s*\n#u', $textInfo, $matches)) {
            $dateArr->setTime($matches[1], $matches[2]);
            $s->arrival()->date($dateArr->getTimestamp());
        }

        if (preg_match('# {3}(?:\w+ / )?Class .+(?:\n+.*){1,4}?.+ {3,}(\w+) ?\(([A-Z]{1})\)(?: {3,}|\n)#u', $textInfo, $matches)) {
            $s->extra()->cabin($matches[1]);
            $s->extra()->bookingCode($matches[2]);
        }

        unset($dateDep, $dateArr);

        return $s;
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();
        $f->general()->confirmation($this->http->FindSingleNode("//td[contains(., 'Бронь') and not(.//td)]/following::td[1]"));
        $s = $f->addSegment();
        $flight = $this->http->FindSingleNode("//td[contains(., 'Рейс') and not(.//td)]/following::td[1]");

        if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flight, $m)) {
            $s->airline()->name($m[1]);
            $s->airline()->number($m[2]);
        }
        $info = $this->http->FindSingleNode("//td[contains(., 'Открыта регистрация на рейс') and not(.//td)]");

        if (preg_match('/следующего по маршруту\s+(.+)\s+([A-Z]{3})\s*[—,-]\s*(.+)\s+([A-Z]{3}),\s+вылетающего по расписанию\s+(\d+) (\w+)\s*в\s*(\d+:\d+)/iu', $info, $m)) {
            $s->departure()->name($m[1]);
            $s->departure()->code($m[2]);
            $s->arrival()->name($m[3]);
            $s->arrival()->code($m[4]);
            $s->departure()->date(strtotime($m[5] . ' ' . MonthTranslate::translate($m[6], $this->lang) . ' ' . $this->year . ', ' . $m[7]));
            $s->arrival()->noDate();
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function normalizeDate($str)
    {
        $in = [
            // 08 ОКТ 2020
            '#^\s*(\d+) (\w+)[.]? (\d{4})\s*$#u',
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("%", preg_replace("#\s{2,}#", "%", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false, $trim = true)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                if ($trim === true) {
                    $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                } else {
                    $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
                }
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $pos = [];
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

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
