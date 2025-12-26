<?php

namespace AwardWallet\Engine\tamair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar PDF-formats: airtransat/BoardingPass, asiana/BoardingPassPdf, aviancataca/BoardingPass, aviancataca/TicketDetails, czech/BoardingPass, lotpair/BoardingPass, sata/BoardingPass, tapportugal/AirTicket, luxair/YourBoardingPassNonPdf, saudisrabianairlin/BoardingPass

class BoardingPassPDF extends \TAccountChecker
{
    public $mailFiles = "tamair/it-12632152.eml, tamair/it-1904287.eml, tamair/it-2116069.eml, tamair/it-2294385.eml, tamair/it-2294387.eml, tamair/it-2762763.eml, tamair/it-4599189.eml, tamair/it-6193345.eml";

    private $reFrom = '@tam.com';
    private $reSubject = 'TAM';
    private $reSubject2 = [
        'en' => 'Boarding Pass',
        'pt' => 'Cartão de Embarque',
    ];
    private static $rePdf = [
        'tamair'  => ['tam.com.br', 'check-in Tam.', 'check in Tam.'],
        'finnair' => ['at www.finnair.com'],
    ];
    private $providerCode;
    private $rePdf2 = [
        'pt' => 'Cartão de Embarque',
        'en' => 'Boarding Pass',
    ];
    private static $dictionary = [
        "en" => [
            "BOOKING REFERENCE"  => ["BOOKING REFERENCE", "LOCALIZADOR"],
            "Boarding Pass"      => ['Boarding Pass', 'Cartão de Embarque'],
            "FLIGHT"             => ["FLIGHT", "VOO | FLIGHT"],
            "FROM"               => ["FROM", "DE | FROM"],
            "TO"                 => ["TO", "PARA | TO"],
            "CLASS OF TRAVEL"    => ["CLASS OF TRAVEL", "CLASSE DA VIAGEM"],
            "TRAVEL INFORMATION" => ["TRAVEL INFORMATION", "INFORMAÇÕES DE VIAGEM"],
            "Seat"               => ['Assento', 'Seat', 'SEAT'],
            "FREQUENT FLYER"     => ["FREQUENT FLYER", "PROGRAMA FIDELIZAÇÃO"],
        ],
        "pt" => [],
    ];
    private $pdfPattern = '.+\.pdf';
    private $lang = 'en';

    public static function getEmailProviders()
    {
        return array_keys(self::$rePdf);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $textPdf = '';

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->rePdf2 as $re) {
                if (strpos($text, $re) !== false) {
                    $textPdf .= "\n" . $text;

                    break;
                }
            }
        }

        if (!empty($textPdf)) {
            $this->flight($email, $textPdf);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (isset($this->providerCode) && $this->providerCode != 'tamair') {
            $email->setProviderCode($this->providerCode);
        }

        return $email;
    }

    public function flight(Email $email, string $text)
    {
        $f = $email->add()->flight();

        // General
        $f->general()->noConfirmation();

        if (preg_match_all("#" . $this->preg_implode($this->t('Boarding Pass')) . ".*\n\s*(.+)\s*\n.*" . $this->preg_implode($this->t('FLIGHT')) . "#", $text, $m)) {
            $f->general()->travellers(array_unique(array_filter($m[1])));
        }

        $segments = $this->split("#" . $this->preg_implode($this->t('Boarding Pass')) . ".*\s*.*\s*\n(.*" . $this->preg_implode($this->t('FROM')) . ".+)#", $text);

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            if (preg_match("#([\s\S]+)\n.*" . $this->preg_implode($this->t('TRAVEL INFORMATION')) . ".*\n([\s\S]+)#", $stext, $m)) {
                $flight = $m[1];
                $info = $m[2];
            } else {
                continue;
            }

            // Info
            if (preg_match("#(\n.*) (?:" . $this->preg_implode($this->t('BOOKING REFERENCE')) . "|" . $this->preg_implode($this->t('CLASS OF TRAVEL')) . ")#", $info, $m)) {
                $tableInfo = $this->SplitCols($info, [0, mb_strlen($m[1])]);
            } else {
                continue;
            }

            if (!empty($tableInfo[1])) {
                if (preg_match("#" . $this->preg_implode($this->t('FREQUENT FLYER')) . "\s*(?:JJ|LA)(\d{5,})\s*\n#", $tableInfo[1], $m)) {
                    if (!in_array($m[1], array_column($f->getAccountNumbers(), 0))) {
                        $f->program()->account($m[1], false);
                    }
                }

                if (preg_match("#" . $this->preg_implode($this->t('BOOKING REFERENCE')) . "\s*([A-Z\d]{5,7})\s*\n#", $tableInfo[1], $m)) {
                    $s->airline()->confirmation($m[1]);
                }

                if (preg_match("#" . $this->preg_implode($this->t('CLASS OF TRAVEL')) . "\s*(?:([A-Z]{1,2})|(\S.+))\s*\n\s*" . $this->preg_implode($this->t('BOOKING REFERENCE')) . "#", $tableInfo[1], $m)) {
                    if (!empty($m[1])) {
                        $s->extra()->bookingCode($m[1]);
                    }

                    if (!empty($m[2])) {
                        $s->extra()->cabin($m[2]);
                    }
                }

                if (preg_match("#ETKT\s*(\d{9,})\s*\n#", $tableInfo[1], $m)) {
                    if (!in_array($m[1], array_column($f->getTicketNumbers(), 0))) {
                        $f->issued()->ticket($m[1], false);
                    }
                }
            }

            // Flight
            $table = $this->SplitCols($flight);

            // Airline
            if (!empty($table[0]) && preg_match("#" . $this->preg_implode($this->t('Seat')) . ".*\s+(?<al>[A-Z\d]{2})(?<fn>\d{1,5})\s*(?<seat>\d{1,3}[A-Z])#", $table[0], $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
                $s->extra()->seat($m['seat']);
            } elseif (!empty($table[0]) && preg_match("#" . $this->preg_implode($this->t('Seat')) . "#", $table[0]) == false) {
                $s->airline()
                    ->noName()
                    ->noNumber();
            }

            // Departure
            if (!empty($table[1]) && preg_match("#^.+\s+(?<name>[\s\S]+?)(?<term>\s+.*Terminal.*)?\n\s*(?<date>.*\d{4})\s*(?<time>\d+:\d+)#", $table[1], $m)) {
                $s->departure()
                    ->noCode()
                    ->name(preg_replace("#\s+#", ' ', $m['name']))
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['time']));

                if (!empty($m['term'])) {
                    $s->departure()
                        ->terminal(trim(str_ireplace('Terminal', '', $m['term'])), true, true);
                }
            }
            // Arrival
            if (!empty($table[1]) && preg_match("#^.+\s+(?<name>[\s\S]+?)(?<term>\s+.*Terminal.*)?\n\s*(?<date>.*\d{4})\s*(?<time>\d+:\d+)#", $table[2], $m)) {
                $s->arrival()
                    ->noCode()
                    ->name(preg_replace("#\s+#", ' ', $m['name']))
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['time']));

                if (!empty($m['term'])) {
                    $s->arrival()
                        ->terminal(trim(str_ireplace('Terminal', '', $m['term'])), true, true);
                }
            }

            if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber())) {
                $flightHtml = $this->http->FindSingleNode('(//text()[starts-with(normalize-space(.),"' . $s->getAirlineName() . '") and contains(.,"' . $s->getFlightNumber() . '") and contains(.,"-") and contains(.,"(") and contains(.,")")])[1]');

                if (preg_match('/-\s+.*\(([A-Z]{3})\)\s+-\s+.*\(([A-Z]{3})\)/', $flightHtml, $matches)) {
                    $s->departure()->code($matches[1]);
                    $s->arrival()->code($matches[2]);
                }
            }

            $count = count($f->getSegments());

            foreach ($f->getSegments() as $key => $seg) {
                if ($key == $count - 1) {
                    continue;
                }

                if ($s->getAirlineName() == $seg->getAirlineName()
                        && $s->getFlightNumber() == $seg->getFlightNumber()
                        && $s->getDepName() == $seg->getDepName()
                        && $s->getDepDate() == $seg->getDepDate()) {
                    if (!empty($s->getSeats())) {
                        $seg->extra()->seats(array_unique(array_merge($seg->getSeats(), $s->getSeats())));
                    }
                    $f->removeSegment($s);
                }
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->reFrom) === false && stripos($headers['subject'], $this->reSubject) === false) {
            return false;
        }

        foreach ($this->reSubject2 as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'<>?~`!@\#$%^&*\[\]=\(\)\-{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);
            $check1 = false;

            foreach (self::$rePdf as $provider => $value) {
                foreach ($value as $phrase1) {
                    if (stripos($textPdf, $phrase1) !== false) {
                        $check1 = true;
                        $this->providerCode = $provider;

                        break;
                    }
                }
            }

            if (!$check1) {
                continue;
            }

            foreach ($this->rePdf2 as $phrase2) {
                if (strpos($textPdf, $phrase2) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'pt'];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            //			"#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s+(\d{1,2}:\d{1,2})\s*$#",// 08/01/2018 18:30
        ];
        $out = [
            //			"$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        //		if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
        //			if ($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
