<?php

namespace AwardWallet\Engine\sixt\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class RentalAgreement extends \TAccountChecker
{
    public $mailFiles = "sixt/it-426564035.eml, sixt/it-58769555.eml";
    public $reFrom = ["@sixt.com"];
    public $reBody = [
        'en' => ['Driver'],
        'nl' => ['Bestuurder'],
        'it' => ['Conducente'],
        'fr' => ['Conducteur'],
    ];
    public $reSubject = [
        'Rental agreement',
    ];
    public $lang = '';
    public $oldConfirmation = '';
    public $pdfNamePattern = ".*pdf";

    public static $dict = [
        'en' => [
            'Reservation No'   => ['Res No:', 'Customer No:'],
            'PickUp'           => ['Time out:', 'Start subsc. period:'],
            'DropOff'          => ['Due in:', 'Next renewal:'],
            //            'Return address:' => '',
            'ReturnAddressEnd' => ['ml Level:'],
            //            'Registr No:' => '',
            //            'Veh. Type:',
            'Driver'        => 'Driver',
            //            'Second Driver:' => '',
            'TravelTextEnd'    => ['Rate', 'CHELSEA', 'NO ADDITIONAL', 'ABI'],
            'Cost'             => 'Subtotal net Renter 1',
            'Tax'              => 'A1 Sales Tax 6,50%',
            'Total'            => 'Total',
        ],
        'nl' => [
            'Reservation No'   => ['Res No:'],
            'PickUp'           => ['Vertrek:'],
            'DropOff'          => ['Terugkomst:'],
            'Return address:'  => 'Inleveradres:',
            //            'Registr No:' => '',
            'ReturnAddressEnd' => ['Km-stand:'],
            'Veh. Type:'       => 'Type auto:',
            //                        'Second Driver:' => '',
            'Driver'        => 'Bestuurder',
            'TravelTextEnd' => ['BTW-nummer', 'Tarief'],
            //            'Cost'             => '',
            'Tax'              => 'A1 BTW 21,00%',
            'Total'            => 'Totaal',
        ],
        'it' => [
            'Reservation No'   => ['Res No:'],
            'PickUp'           => ['Uscita:'],
            'DropOff'          => ['Rientro:'],
            'Return address:'  => 'Indirizzo ritorno:',
            //            'Registr No:' => '',
            'ReturnAddressEnd' => ['Km:'],
            'Veh. Type:'       => 'Tipo vettura:',
            'Second Driver:'   => 'Conducente add:',
            'Driver'           => 'Conducente',
            'TravelTextEnd'    => ['Tariffa:'],
            //            'Cost'             => '',
            'Tax'              => 'A1 IVA 22,00%',
            'Total'            => 'Totale',
        ],
        'fr' => [
            'Reservation No'   => ['No. client:'],
            'PickUp'           => ['Depart:'],
            'DropOff'          => ['Retour:'],
            'Return address:'  => 'Adresse de retour:',
            //            'Registr No:' => '',
            'ReturnAddressEnd' => ['kms:'],
            'Veh. Type:'       => 'Type vehic.:',
            //'Second Driver:'   => 'Conducente add:',
            'Driver'           => 'Conducteur',
            'TravelTextEnd'    => ['ID. TVA:'],
            //            'Cost'             => '',
            'Tax'              => 'A1 TVA 20,00%',
            'Total'            => 'Montant',
        ],
    ];
    private $keywordProv = 'Sixt';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectBody($text) && $this->assignLang($text)) {
                        $this->parseEmailPdf($text, $email);
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectBody($text) && $this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("/\b{$this->opt($this->keywordProv)}\b/i", $headers["subject"]) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
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
        return count(self::$dict);
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $confirmation = $this->re("/(?:\n\s*| {5,}){$this->opt($this->t('Reservation No'))}\s+(\d{10})\s+/", $textPDF);

        if ($confirmation !== $this->oldConfirmation) {
            $r = $email->add()->rental();

            //Confirmation
            $confirmation = $this->re("/(?:\n\s*| {5,}){$this->opt($this->t('Reservation No'))}\s+(\d{10})\s+/", $textPDF);
            $r->general()
                ->confirmation($confirmation);
            $this->oldConfirmation = $confirmation;

            //Travellers
            $travelText = $this->cutText("{$this->t('Driver')}", ["{$this->t('Second Driver:')}"], $textPDF);

            if (empty($travelText)) {
                $travelText = $this->cutText("{$this->t('Driver')}", $this->t('TravelTextEnd'), $textPDF);
            }

            $ptable = preg_replace("#^\s*\n#", "", $this->re("/^({$this->opt($this->t('Driver'))}.+)/ms", $travelText));
            $pos = $this->TableHeadPos(explode("\n", $ptable)[0]);
            $pos[] = 0;
            sort($pos);
            $pos = array_merge([], $pos);
            $ptable = $this->splitCols($ptable, $pos);

            $travellers = [];

            if (preg_match("/{$this->opt($this->t('Driver'))}\n(\D+)\s*{$this->opt($this->t('TravelTextEnd'))}/", $ptable[0], $m)) {
                $traveller = preg_replace('/\s+/', ' ', $m[1]);
                $travellers[] = $traveller;
            }

            if (preg_match("/{$this->opt($this->t('Driver'))}\n(\D+)\s*\n\s*\d{2,}/", $ptable[0], $m)) {
                $traveller = preg_replace('/\s+/', ' ', $m[1]);
                $travellers[] = $traveller;
            }

            $secondTravellers = explode(',', $this->re("/{$this->opt($this->t('Second Driver:'))}\s+([A-Z\,\s]+)\s+{$this->opt($this->t('TravelTextEnd'))}/su", $textPDF));

            if (count($secondTravellers) > 0 & count($travellers) > 0) {
                $travellers = array_merge($secondTravellers, $travellers);
            }

            if (count(array_filter($travellers)) > 0) {
                $r->general()
                    ->travellers(array_unique(array_filter($travellers)));
            }

            //PickUp
            if (preg_match("/{$this->opt($this->t('PickUp'))}\s+(\d+\/?\.?\d+\/?\.?\d{4}\s+\d+\.\d+) +(\w.+?)( {2,}|$)/u", $textPDF, $m)) {
                $r->pickup()
                    ->location(trim($m[2]))
                    ->date($this->normalizeDate($m[1]));
            }

            //DropOff
            if (preg_match("/{$this->opt($this->t('DropOff'))}\s+(\d+\/?\.?\d+\/?\.?\d{4}\s+\d+\.\d+) +(\w.+?)( {2,}|$)/u", $textPDF, $m)) {
                $r->dropoff()
                    ->location(trim($m[2]))
                    ->date($this->normalizeDate($m[1]));
            }

            $returnAddress = $this->re("/{$this->opt($this->t('Return address:'))}\s+(.+){$this->opt($this->t('ReturnAddressEnd'))}/s", $textPDF);

            if (!empty($returnAddress)) {
                $r->dropoff()
                    ->location($r->getDropOffLocation() . ', ' . preg_replace("/\s{4,}/", " ", $returnAddress));
            }

            //Car Type
            $r->car()
                ->type($this->re("/{$this->opt($this->t('Veh. Type:'))}\s+([A-Z\s\d]+)\s+[A-Za-z]/", $textPDF));

            //Price
            $cost = $this->re("/{$this->opt($this->t('Cost'))}\s+([\d\.\,]+)/", $textPDF);
            $tax = $this->re("/{$this->opt($this->t('Tax'))}\s+([\d\.\,]+)/", $textPDF);
            $total = $this->re("/{$this->opt($this->t('Total'))}\s+([\d\.\,]+)/", $textPDF);
            $currency = $this->re("/{$this->opt($this->t('Total'))}\s+[\d\.\,]+\s+([A-Z]{3})/", $textPDF);

            if (!empty($tax)) {
                $r->price()
                    ->tax($this->normalizePrice($tax));
            }

            if (!empty($cost)) {
                $r->price()
                    ->cost($this->normalizePrice($cost));
            }

            if (!empty($total)) {
                $r->price()
                    ->total($this->normalizePrice($total))
                    ->currency($currency);
            }
        }

        return true;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Driver"], $words["PickUp"])) {
                if ($this->stripos($body, $words["Driver"]) && $this->stripos($body, $words["PickUp"])) {
                    $this->lang = $lang;
                    //$this->logger->debug('Lang '.$this->lang);
                    return true;
                }
            }
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            if ($this->stripos($body, $this->keywordProv)) {
                foreach ($this->reBody as $lang => $reBody) {
                    if ($this->stripos($body, $reBody)) {
                        $this->lang = $lang;

                        return true;
                    }
                }
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

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function cutText(string $start = '', array $ends = [], string $text = '')
    {
        if (!empty($start) && 0 < count($ends) && !empty($text)) {
            foreach ($ends as $end) {
                if (($cuttedText = stristr(stristr($text, $start), $end, true)) && is_string($cuttedText) && 0 < strlen($cuttedText)) {
                    break;
                }
            }

            return substr($cuttedText, 0);
        }

        return null;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$date IN = '.print_r( $str, true));
        $in = [
            "#^(\d+)\/(\d+)\/(\d{4})\s+(\d+)\.(\d+)$#", // 02/03/2020 23.09
            "#^(\d+)\.(\d+)\.(\d{4})\s+(\d+)\.(\d+)$#", //26.07.2019 13.00
        ];
        $out = [
            "$2.$1.$3, $4:$5",
            "$1.$2.$3, $4:$5",
        ];
        $str = preg_replace($in, $out, $str);

        if ($this->lang !== 'en' && preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if (($en = MonthTranslate::translate($m[1], $this->lang)) || ($en = MonthTranslate::translate($m[1], 'da')) || ($en = MonthTranslate::translate($m[1], 'no'))) {
                $str = str_replace($m[1], $en, $str);
            }
        }

//        $this->logger->debug('$date OUT = '.print_r($str, true));
        return strtotime($str);
    }

    private function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);           // 11 507.00    ->    11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string); // 2,790        ->    2790    |    4.100,00    ->    4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);  // 18800,00     ->    18800.00

        return $string;
    }

    private function TableHeadPos($row)
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
                $str = mb_substr($row, $p, null, 'UTF-8');

                if ($trim) {
                    $str = trim($str);
                }
                $cols[$k][] = $str;
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }
}
