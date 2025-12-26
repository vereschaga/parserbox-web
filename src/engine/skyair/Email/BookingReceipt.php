<?php

namespace AwardWallet\Engine\skyair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingReceipt extends \TAccountChecker
{
    public $mailFiles = "skyair/it-27641939.eml, skyair/it-27726700.eml, skyair/it-36511603.eml";

    public $lang = '';

    public static $dict = [
        'es' => [
            //			"**** NO ES VÁLIDO COMO TARJETA" => "",
            //			"RESERVATION NUMBER" => "",
            //			"NOMBRE DEL PASAJERO" => "",
            "N° DE TICKET" => ["N° DE TICKET", "N? DE TICKET"],
            //			"DETALLES DE COMPRA" => "",
            //			"VUELO" => "",
            //			"SALIDA" => "",
            //			"INCLUIDO EN TU VUELO" => "",
            //			"ASIENTO" => "",
            //			"DETALLES DE PAGO" => "",
            //			"TARIFA" => "",
            //			"TOTAL" => "",
            //			"REGULACIÓN" => "",
        ],
        'en' => [
            "**** NO ES VÁLIDO COMO TARJETA" => "**** IT IS NOT VALID AS",
            //			"RESERVATION NUMBER" => "",
            "NOMBRE DEL PASAJERO"  => "PASSENGER",
            "N° DE TICKET"         => "TKT NUMBER",
            "DETALLES DE COMPRA"   => "BOOKING DETAILS",
            "VUELO"                => "FLIGHT",
            "SALIDA"               => "DEPARTURE",
            "INCLUIDO EN TU VUELO" => "INCLUDED IN YOUR FLIGHT",
            "ASIENTO"              => "SEAT",
            "DETALLES DE PAGO"     => "PAYMENT DETAILS",
            "TARIFA"               => "AIRFARE",
            //			"TOTAL" => "",
            "REGULACIÓN" => "REGULATION",
        ],
    ];

    private $detectFrom = 'skyairline.com';
    private $detectSubject = [
        'es' => 'Comprobante de compra',
        'en' => 'Your Booking Receipt',
    ];

    private $detectBody = [
        'es'  => ['Razón Social: SKY Airline', 'DETALLES DE COMPRA'],
        'es2' => ['NO ES VáLIDO COMO TARJETA DE EMBARQUE', 'DETALLES DE COMPRA'],
        'en'  => ['Razón Social: SKY Airline', 'BOOKING DETAILS'],
        'en2' => ['TERMS AND CONDITIONS TO SKY AIRLINE', 'BOOKING DETAILS'],
    ];

    private $pdfNamePattern = '.*pdf';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            if ($this->assignLang($text)) {
                $this->parseEmail($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

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

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->detectFrom) !== false && isset($headers['subject'])) {
            foreach ($this->detectSubject as $detectSubject) {
                if (stripos($headers['subject'], $detectSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email, string $text)
    {
        $end = strpos($text, $this->t("REGULACIÓN"));

        if (!empty($end)) {
            $text = substr($text, 0, $end);
        }

        $f = $email->add()->flight();

        // General
        if (preg_match("#" . $this->preg_implode($this->t("RESERVATION NUMBER")) . "\s*\n(?:.*\n)?.{70,}[ ]{3,}([A-Z\d]{5,7})\s*\n#", $text, $m)) {
            $f->general()->confirmation($m[1]);
        }

        if (preg_match_all("#\n([ ]*" . $this->preg_implode($this->t("NOMBRE DEL PASAJERO")) . " \d+.+[\s\S]+?)\n\n#", $text, $mat)) {
            foreach ($mat[1] as $travText) {
                $table = $this->SplitCols($travText, $this->TableHeadPos($this->inOneRow($travText)));

                if (preg_match("#^[\s\S]*?\d+.*\n+[ ]*([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])$#u", $table[0], $m)) {
                    $f->general()->traveller(preg_replace("#\s+#", ' ', $m[1]), true);
                }

                if (preg_match("#" . $this->preg_implode($this->t("N° DE TICKET")) . "\s*\n([\d\-]{7,})#s", $table[2], $m)) {
                    $f->issued()->ticket($m[1], false);
                }
            }
        }

        // Segments
        $segments = [];
        $sections = $this->cutSections($text, $this->t('DETALLES DE COMPRA'), [$this->t('**** NO ES VÁLIDO COMO TARJETA'), $this->t('DETALLES DE PAGO')]);

        foreach ($sections as $section) {
            $segments = array_merge($segments, $this->split("#(.+[ ]+(?:VUELO)\s*\n\s*(?:.*\n)?(?:.+\n.+\n)?(?:SALIDA))#", $section));
        }

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            $tableText = $this->re("#(.+?)\s+" . $this->preg_implode($this->t("INCLUIDO EN TU VUELO")) . "#s", $stext);

            if (preg_match("/(\s\d+\-\d+\-\d{4}[ ]{11,20}\d+\:\d+)/", $tableText)) {
                $tableText = preg_replace("/(\s\d+\-\d+\-\d{4})[ ]{11,20}(\d+\:\d+)/", "$1          $2         ", $tableText);
            }

            $pos = $this->TableHeadPos($this->inOneRow($tableText));
            $pos[0] = 0;
            $table = $this->SplitCols($tableText, $pos);

            if (count($table) !== 3) {
                $this->logger->debug("segment table was parsed incorrectly. Segment: $stext");

                break;
            }

            // Airline
            if (preg_match("#" . $this->preg_implode($this->t("VUELO")) . "\s+.*\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d{1,5})\s+#", $table[2], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $regexp = "#(?<name>.+)\n\s*\S.+?\s*\n\s*(?<date>.+\d+:\d+)#";
            // Departure
            if (preg_match($regexp, $table[0], $m)) {
                $s->departure()
                    ->noCode()
                    ->name(trim($m['name']))
                    ->date($this->normalizeDate($m['date']))
                ;
            }
            // Arrival
            if (preg_match($regexp, $table[1], $m)) {
                $s->arrival()
                    ->noCode()
                    ->name(trim($m['name']))
                    ->date($this->normalizeDate($m['date']))
                ;
            }

            // Extra
            if (preg_match("#" . $this->preg_implode($this->t("ASIENTO")) . "\s*\n*\s*(\d{1,3}[A-Z])\s+#", $stext, $m)) {
                $s->extra()->seat($m[1]);
            } elseif (preg_match("#" . $this->preg_implode($this->t("ASIENTO")) . "\D*\n*\s*(\d{1,3}[A-Z])\s+#", $stext, $m)) {
                $s->extra()->seat($m[1]);
            }

            $count = count($f->getSegments());

            foreach ($f->getSegments() as $key => $seg) {
                if ($key == $count - 1) {
                    continue;
                }

                if ($s->getAirlineName() == $seg->getAirlineName()
                        && $s->getFlightNumber() == $seg->getFlightNumber()
                        && $s->getDepDate() == $seg->getDepDate()) {
                    if (!empty($s->getSeats())) {
                        $seg->extra()->seats(array_unique(array_merge($seg->getSeats(), $s->getSeats())));
                    }
                    $f->removeSegment($s);
                }
            }
        }

        // Price
        $posTotal = strpos($text, $this->t("DETALLES DE PAGO"));

        if (!empty($posTotal)) {
            $totalText = preg_replace("#^\s*" . $this->preg_implode($this->t("DETALLES DE PAGO")) . ".*\s*\n([ ]*\S)#", '$1', substr($text, $posTotal));
            $table = $this->SplitCols($totalText);

            if (count($table) !== 2) {
                $this->logger->debug("price table was parsed incorrectly");
            } else {
                if (preg_match("#\n[ ]*" . $this->preg_implode($this->t("TARIFA")) . "[ ]{3,}(?<fare>.+)\n(?<taxes>(?:.*\n)+)?\s*" . $this->preg_implode($this->t("TOTAL")) . "[ ]{3,}(?<total>.+)#", $table[0], $m)) {
                    $f->price()
                        ->cost($this->getTotal($m['fare'])['Amount'])
                        ->total($this->getTotal($m['total'])['Amount'])
                        ->currency($this->getTotal($m['total'])['Currency'])
                    ;
                    $taxes = array_filter(explode("\n", $m['taxes']));

                    foreach ($taxes as $tax) {
                        if (preg_match("#(?<name>.+)[ ]{3,}(?<total>.+)#", $tax, $mat)) {
                            $f->price()->fee($mat['name'], $this->getTotal($mat['total'])['Amount']);
                        }
                    }
                }
            }
        }

        return $email;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d{1,2})-(\d{1,2})-(\d{4})\s+(\d+:\d+)\s*$#su', //19-11-2018	07:30
        ];
        $out = [
            '$1.$2.$3 $4',
        ];
        $date = preg_replace($in, $out, $date);
        //		if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
        //			if ($en = MonthTranslate::translate($m[1], $this->lang))
        //				$date = str_replace($m[1], $en, $date);
        //		}
        return strtotime($date);
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
        foreach ($this->detectBody as $lang => $dBody) {
            if (stripos($body, $dBody[0]) !== false && stripos($body, $dBody[1]) !== false) {
                $this->lang = substr($lang, 0, 2);

                return true;
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

    private function getTotal($price)
    {
        $result = ["Amount" => null, "Currency" => null];

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<total>\d[\d\., ]*)\s*$#", $price, $m)
                || preg_match("#^\s*(?<total>\d[\d\. ,]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $price, $m)) {
            $result = [
                "Amount"   => $this->amount($m['total']),
                "Currency" => $this->currency($m['curr']),
            ];
        }

        return $result;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
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

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
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

    private function strposAll($haystack, $needles)
    {
        if (is_string($needles)) {
            return strpos($haystack, $needles);
        }

        if (is_array($needles)) {
            foreach ($needles as $needle) {
                if (strpos($haystack, $needle) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function cutSections($text, $searchStart, $searchEnd)
    {
        if (empty($text) || empty($searchStart) || empty($searchEnd)) {
            return false;
        }
        $result = [];

        for ($i = 0; $i < 20; $i++) {
            $str = mb_strstr($text, $searchStart, false);

            if (!empty($str)) {
                $text = $str;
            } else {
                break;
            }

            if (is_array($searchEnd)) {
                foreach ($searchEnd as $end) {
                    $str = mb_strstr($text, $end, true);

                    if (!empty($str)) {
                        $result[] = $str;
                        $text = mb_strstr($text, $end, false);

                        continue 2;
                    } else {
                        continue;
                    }
                }

                return false;
            } else {
                $str = mb_strstr($text, $searchEnd, true);

                if (!empty($str)) {
                    $result[] = $str;
                    $text = mb_strstr($text, $searchEnd, false);
                } else {
                    return false;
                }
            }
        }

        return $result;
    }
}
