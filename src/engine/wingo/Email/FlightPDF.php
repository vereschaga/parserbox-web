<?php

namespace AwardWallet\Engine\wingo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightPDF extends \TAccountChecker
{
    public $mailFiles = "wingo/it-833809908.eml";
    public $lang = 'es';
    public $pdfNamePattern = ".*pdf";
    public $flightOrder = 0;

    public static $dictionary = [
        "es" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'www.wingo.com') === false
                && stripos($text, 'Wingo') === false) {
                return false;
            }

            if (strpos($text, "Reservado para") !== false
                && strpos($text, "Ida:") !== false
                && (strpos($text, 'Vuelo No.') !== false)
                && (strpos($text, 'Pasajero(s)') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]vacv\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/{$this->opt($this->t('Número de Confirmación:'))}[\s\:]+([A-Z\d]+)\s/", $text))
            ->date($this->normalizeDate($this->re("#{$this->opt($this->t('Reservado para'))}\D+(\d+\s*\w+\s*\d{4})#", $text)));

        $priceText = $this->re("/^([ ]*\S\s+Resumen de Pago.+)\n+[ ]*Información importante/msu", $text);
        $priceTable = $this->splitCols($priceText);

        if (preg_match("/Tarifa Aérea:\s+(?<currency>[A-Z]{3})\s+(?<cost>[\d\.\,\']+)\n+(?<feeText>.+)\n+Precio Final:\s+([A-Z]{3})\s+(?<total>[\d\.\,\']+)/su", $priceTable[1], $m)) {
            $currency = $m['currency'];
            $f->price()
                ->cost(PriceHelper::parse($m['cost'], $currency))
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $feeRows = array_filter(explode("\n", $m['feeText']));

            foreach ($feeRows as $feeRow) {
                if (preg_match("/^(?<feeName>.+)\s+[A-Z]{3}\s*(?<feeSumm>[\d\.\,\']+)$/", $feeRow, $m)) {
                    $f->price()
                        ->fee(str_replace(':', '', $m['feeName']), PriceHelper::parse($m['feeSumm'], $currency));
                }
            }
        }

        $travellers = [];

        $segments = $this->splitText($text, "/((?: Ida:| Vuelta:))/", true);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $flightInfo = $this->re("/^[ ]+\S[ ]+{$this->opt($this->t('Vuelo No.'))}.+\n((?:.+\n){1,4})\n[ ]*\S\s*{$this->opt($this->t('Pasajero(s)'))}/mu", $segment);
            $flightTable = $this->splitCols($flightInfo);

            if (preg_match("/^\s*((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))[\s\-]+(\d{2,4})/", $flightTable[0], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match("/^(?<depName>.+)\n(?<depCode>[A-Z]{3})\s+(?<depTime>[\d\:]+)\s+(?<depDate>\d+\s*\w+\s*\d{4})/", $flightTable[1], $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']));
            }

            if (preg_match("/^(?<arrName>.+)\n(?<arrCode>[A-Z]{3})\s+(?<arrTime>[\d\:]+)\s+(?<arrDate>\d+\s*\w+\s*\d{4})/", $flightTable[2], $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']));
            }

            if (preg_match("/^(?<duration>(?:\d+h)?\s*(?:\d+m))\n/", $flightTable[3], $m)) {
                $s->extra()
                    ->duration($m['duration']);
            }

            $travellersText = $this->re("/{$this->opt($this->t('Cargos Opcionales'))}\n^[ ]*\S\s*{$this->opt($this->t('Pasajero(s)'))}.+{$this->opt($this->t('Valor'))}\n+(.+){$this->opt($this->t('Otros Cargos'))}\n/msu", $segment);
            $travellerRows = $this->splitText($travellersText, "/^(.+\s+(?:\d+[A-Z]|No Asignado).+)/m", true);
            $cabin = [];

            foreach ($travellerRows as $travellerRow) {
                $seat = $this->re("/^.+\s+(\d+[A-Z])\s+/u", $travellerRow);

                $travellerTable = $this->splitCols($travellerRow);

                if (preg_match("/^(?<traveller>[[:alpha:]][-.\/\'’[:alpha:],\n ]*[[:alpha:]])\s+(?:MRS|MR|MS)\s+Adulto\s+(?<cabin>\w+)/s", $travellerTable[0], $m)) {
                    $traveller = str_replace("\n", " ", $m['traveller']);

                    if (!in_array($traveller, $travellers)) {
                        $travellers[] = $traveller;
                    }

                    if (!empty($seat)) {
                        $s->extra()
                            ->seat($seat, true, true, $traveller);
                    }

                    if (!in_array($m['cabin'], $cabin)) {
                        $cabin[] = $m['cabin'];
                    }
                }
            }

            if (count($cabin) > 0) {
                $s->extra()
                    ->cabin(implode(', ', $cabin));
            }
        }

        if (count($travellers) > 0) {
            $f->general()
                ->travellers($travellers);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseFlightPDF($email, $text);
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

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\,\s*(\w+)\s(\d+)\,\s*(\d{4})\,\s*([\d\:]+)$#u", //Sunday, October 16, 2022, 13:15
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
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
