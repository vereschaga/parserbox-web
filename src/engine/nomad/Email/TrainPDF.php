<?php

namespace AwardWallet\Engine\nomad\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TrainPDF extends \TAccountChecker
{
    public $mailFiles = "nomad/it-12260609.eml, nomad/it-141086409.eml, nomad/it-235031586.eml, nomad/it-236899937.eml, nomad/it-237501066.eml";
    public $lang = 'fr';
    public $pdfNamePattern = ".*pdf";

    public $depDate = '';
    public $arrDate = '';

    public static $dictionary = [
        "fr" => [
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

            if (strpos($text, 'MON BILLET') !== false
                && strpos($text, 'Acheté le') !== false
                && strpos($text, 'ITINÉRAIRE') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function ParseTrainPDF(Email $email, $allText)
    {
        //$this->logger->debug($allText);
        $billets = $this->split("/(?:^|\n)( *MON BILLET\n+)/", $allText);

        foreach ($billets as $text) {
            unset($t);
            $conf = $this->re("/{$this->opt($this->t('REF :'))}\s*([A-Z\d]{6})\s*\n*/", $text);

            foreach ($email->getItineraries() as $it) {
                if (in_array($conf, array_column($it->getConfirmationNumbers(), 0))
                ) {
                    $t = $it;
                }
            }

            if (!isset($t)) {
                $t = $email->add()->train();

                $t->general()
                    ->confirmation($conf);
            }

            $pos = strlen($this->re("/\n(.+? {3}) Acheté le.*/", $text));

            if (empty($pos)) {
                $pos = 40;
            }
            $table = $this->splitCols($text, [0, $pos]);

            $cabin = null;

            if (preg_match("/^\s*(?<traveller>[A-Z\s]+)\s*\d+\/.+Classe\s*(?<class>.+)\n(?<total>\d+\,\d+)\s*(?<currency>\D)\n.+Acheté le\s*(?<dayPay>\d+\/\d+\/\d{4}\D+[\dh]+).+/su",
                $table[1], $match)) {
                $t->general()
                    ->date($this->normalizeDate($match['dayPay']));

                $traveller = trim(str_replace("\n", " ", $match['traveller']));

                if (!in_array($traveller, array_column($t->getTravellers(), 0))) {
                    $t->general()
                        ->traveller($traveller, true);
                }

                $cabin = $match['class'];

                $total = 0;

                if ($t->getPrice() && $t->getPrice()->getTotal()) {
                    $total = $t->getPrice()->getTotal();
                }
                $t->price()
                    ->total(PriceHelper::cost($match['total'], '.', ',') + $total)
                    ->currency($match['currency']);
            }

            $text = preg_replace("/^[\s\S]*?\nITINÉRAIRE(?: *ALLER)?\n/u", '', $table[0]);

            $regexp = "/(?:^|\n)\s*([[:alpha:]]+ *\d{1,2} *[[:alpha:]]+ *\d{4})/u";
            $dateSegments = $this->split($regexp, $text);
            $segments = [];

            foreach ($dateSegments as $dSegment) {
                $date = $this->re("/^\s*(.+)/", $dSegment);
                $dSegment = preg_replace("/^\s*(.+)\s*\n/", '', $dSegment);
                $regexp = "/(?:^|\n)\s*(\d{1,2}h\d{2}\s*[A-Z\s\-]+\n+\w+\s*\d{4,5}[\s\S]*?\n\s*\d{1,2}h\d{2}\n)/u";
                $segments = array_merge($segments,
                    $dSegment = preg_replace('/^\s*(.)/', $date . "\n" . "$1", $this->split($regexp, $dSegment)));
            }

            $regexp = "/^\s*(?<date>\w+\s*\d+\s*\w+\s*\d{4})\s*(?<depTime>\d{1,2}h\d{2})\s*(?<depName>[A-Z\s\-\d\.]+)\n+"
                . "(?<traintype>[[:alpha:] ]*)(?<number>[A-Z]{0,3}\d{2,7})\b(?<info>[\s\S]*?)"
                . "\n\s*(?<arrTime>\d{1,2}h\d{2})\s*(?<arrName>[A-Z\s\-\d\.]+)(?:\n{2,}|$)/su";

            foreach ($segments as $sText) {
                if (preg_match($regexp, $sText, $m)) {
                    $s = $t->addSegment();

                    $s->setNumber($m['number']);

                    if (preg_match("/Voiture\s*(?<car>\d+)[\s\-]+Place\s*(?<seat>\d+)\b/", $m['info'], $mat)) {
                        $s->setCarNumber($mat['car']);

                        $s->extra()
                            ->seat($mat['seat']);
                    }

                    if (trim($m['traintype']) == 'TER' || stripos(trim($m['traintype']), 'TER ') === 0) {
                        // there are no unique words and few examples of emails to determine more accurately
                        $email->setProviderCode('sncf');
                    }

                    $m['depName'] = preg_replace("/\s*\n\s*TRAIN\s*$/", '', $m['depName']);
                    $s->departure()
                        ->date($this->normalizeDate($m['date'] . ', ' . $m['depTime']))
                        ->name($m['depName']);

                    $s->arrival()
                        ->date($this->normalizeDate($m['date'] . ', ' . $m['arrTime']))
                        ->name($m['arrName']);

                    $s->extra()
                        ->cabin($cabin);
                }

                $segments = $t->getSegments();

                foreach ($segments as $segment) {
                    if ($segment->getId() !== $s->getId()) {
                        if (($segment->getDepName() === $s->getDepName())
                            && ($segment->getArrName() === $s->getArrName())
                            && ($segment->getDepDate() === $s->getDepDate())
                        ) {
                            if (!empty($s->getSeats())) {
                                $segment->extra()->seats(array_unique(array_merge($segment->getSeats(), $s->getSeats())));
                            }
                            $t->removeSegment($s);

                            break;
                        }
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'MON BILLET') !== false
                && strpos($text, 'Acheté le') !== false
                && strpos($text, 'ITINÉRAIRE') !== false
            ) {
                $this->ParseTrainPDF($email, $text);
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

    public static function getEmailProviders()
    {
        return ['nomad', 'sncf'];
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

    private function normalizeDate($date)
    {
        //$this->logger->debug('$date = '.$date);

        $in = [
            // JEUDI 17 FÉVRIER 2022, 17h40
            "/^\w+\s*(\d+\s*\w+\s*\d{4})\,\s*(\d+)h(\d+)$/iu",

            //16/02/2022 à 20h35
            "/^(\d+)\/(\d+)\/(\d{4})\D+(\d+)\D(\d+)$/iu",
        ];
        $out = [
            "$1, $2:$3",
            "$1.$2.$3, $4:$5",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
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
}
