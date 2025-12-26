<?php

namespace AwardWallet\Engine\sae\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TicketConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "sae/it-126292815.eml, sae/it-156057853.eml, sae/it-157514844.eml, sae/it-158221095.eml, sae/it-715751686.eml, sae/it-730496106.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Booking reference #' => 'Booking reference #',
            // 'Passenger Name' => '',
            'Travel Itinerary' => 'Travel Itinerary',
            // 'Flight' => '',
            // 'From' => '',
            // 'To' => '',
            // 'Departure' => '',
            // 'Arrival' => '',
            // 'class' => '',
            // 'Fare Details' => '',
            // 'Base fare amount' => '',
            // 'Payment Receipt' => '',
            // 'Tax & surcharges' => '',
            // 'Total amount' => '',
            // 'Other fees' => '',
        ],
        'fr' => [
            'Booking reference #' => 'Dossier N°',
            'Passenger Name'      => 'Nom du passager',
            'Travel Itinerary'    => 'Itinéraire',
            'Flight'              => 'Vol',
            'From'                => 'De',
            'To'                  => 'A',
            'Departure'           => 'Départ',
            'Arrival'             => 'Arrivée',
            'class'               => 'Cabine',
            'Fare Details'        => 'Détails du tarif',
            'Payment Receipt'     => 'Reçu de paiement',
            'Base fare amount'    => 'Tarif HT',
            'Tax & surcharges'    => 'Taxes',
            'Total amount'        => 'Total',
            'Other fees'          => 'Autres',
        ],
        'es' => [
            'Booking reference #' => 'N° de reserva',
            'Passenger Name'      => 'Nombre del pasajero',
            'Travel Itinerary'    => 'Itinerario de viaje',
            'Flight'              => 'Vuelo',
            'From'                => 'De',
            'To'                  => 'A',
            'Departure'           => 'Salida',
            'Arrival'             => 'Llegada',
            'class'               => 'Cabina',
            'Fare Details'        => 'Detalles de la tarifa',
            'Payment Receipt'     => 'Recibo',
            'Base fare amount'    => ['Importe de la tarifa base', 'Importe de la tarifabase'],
            'Tax & surcharges'    => 'Impuestos y recargos',
            'Total amount'        => 'Importe total',
            'Other fees'          => 'Otros cargos',
        ],
    ];

    private $detectFrom = '@iflysouthern.com';
    private $detectSubject = [
        // en
        'Ticket Confirmation - Booking n°',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
            $text = html_entity_decode($text);
            $text = str_replace('­', '-', $text);
            $text = str_replace(';', ';', $text);
            $text = str_replace(chr(194) . chr(160), ' ', $text);
            $text = strip_tags($text);

            if ($this->detectPdf($text) == true) {
                $this->parseEmailPdf($email, $text);
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

    public function detectPdf($text)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (empty($dict['Travel Itinerary']) || empty($dict['Booking reference #'])) {
                continue;
            }
            $pos = $this->containsText($text, $dict['Travel Itinerary']);

            if ($pos === false && $this->containsText($text, $dict['Booking reference #']) === false) {
                continue;
            }

            $s = mb_substr($text, $pos, 300);
            $this->lang = $lang;

            if (preg_match("/^\s*{$this->opt($this->t('Travel Itinerary'))}\s*(?:\n {5,}.+){0,1}\n\s*{$this->opt($this->t('Flight'))}(?: +.*|(?:\n.+){0,4})\s+{$this->opt($this->t('From'))}\s+{$this->opt($this->t('To'))}\s+{$this->opt($this->t('Departure'))}\s+{$this->opt($this->t('Arrival'))}/u", $s)) {
                return true;
            }
        }

        $this->lang = null;

        return false;
    }

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        // $this->logger->debug('Pdf text = ' . print_r($textPdf, true));

        $f = $email->add()->flight();

        $conf = $this->re("/{$this->opt($this->t('Booking reference #'))} *([A-Z\d]{5,7})\s*\n/u", $textPdf);

        if (empty($conf)) {
            $conf = $this->re("/\n([A-Z\d]{5,7})\s*\n\s*{$this->opt($this->t('Booking reference #'))}\s*\n/u", $textPdf);
        }
        $f->general()
            ->confirmation($conf);

        $travellerText = mb_substr($textPdf, $this->containsText($textPdf, $this->t('Passenger Name')));

        if (preg_match_all("/\n(?:(?:Child|[[:alpha:]]{1,4}\.|Mme|Miss|Sr|Sra) )?(\S[[:alpha:] \-]+)\s+(\d{13})\n/u", $travellerText . "\n\n", $m)) {
            foreach ($m[0] as $i => $v) {
                $f->general()
                    ->traveller($m[1][$i], true);

                $f->issued()
                    ->ticket($m[2][$i], false, $m[1][$i]);
            }
        }

        // Segments
        $segmentsText = substr($textPdf, $this->containsText($textPdf, $this->t('Travel Itinerary')));
        $segmentsText = substr($segmentsText, 0, $this->containsText($segmentsText, $this->t('Fare Details')));
//        $this->logger->debug('$segmentsText = ' . print_r($segmentsText, true));

        $segments = $this->split("/\n\s*((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5}.*(?:\s+\d{1,2}:\d{2}){0,2}\s+.+\s*.*\([A-Z]{3}\)\s+)/u", $segmentsText);
        // $this->logger->debug('$segments = ' . print_r($segments, true));
        foreach ($segments as $stext) {
//            $this->logger->debug('$stext = ' . print_r($stext, true));

            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->re("/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5}\s+/", $stext))
                ->number($this->re("/^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(\d{1,5})\s+/", $stext))
            ;

            if (preg_match("/^.+(?:\s+\d{1,2}:\d{2}){0,2}\s+(?<dname>\w.+?\s*.*)\((?<dcode>[A-Z]{3})\)(?:\s*\([A-Z]{3}\))?\s+(?<aname>\w.+?\s*.*)\((?<acode>[A-Z]{3})\)(?:\s*\([A-Z]{3}\))?/u", $stext, $m)) {
                $s->departure()
                    ->code($m['dcode'])
                    ->name(preg_replace('/\s+/', ' ', trim($m['dname'])))
                ;
                $s->arrival()
                    ->code($m['acode'])
                    ->name(preg_replace('/\s+/', ' ', trim($m['aname'])))
                ;
            }

            if (preg_match("/\s+(?<dd>\d{1,2}-\n?[[:alpha:]]+\.?\s*-\s*\d{2})\s+(?<dt>\d{2}:\d{2})\s+(?<ad>\d{1,2}-\n?[[:alpha:]]+\.?\s*-\s*\d{2})\s+(?<at>\d{2}:\d{2})\s+/u", $stext, $m)
            || preg_match("/\s*(?<dd>\d{1,2}-\n?[[:alpha:]]+\.?\s*-\s*\d{2})\n*\s*(?<dt>\d{1,2}:\d{2}\s*(?:[AP]M)?)\n*\s*(?<ad>\d{1,2}-\n?[[:alpha:]]+\.?\s*-\s*\n*\d{2})\n*\s*(?<at>\d{1,2}:\d{2}\s*A?P?M?)\n*\s*/u", $stext, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['dd'] . ', ' . $m['dt']));

                $s->arrival()
                    ->date($this->normalizeDate($m['ad'] . ', ' . $m['at']));
            }

            if (preg_match("/\n([A-Z][a-z ]+)\s+{$this->opt($this->t('class'))}\n/", $stext, $m)
                || preg_match("/\n{$this->opt($this->t('class'))}\s+([A-Z][a-z ]+)\n/", $stext, $m)
            ) {
                $s->extra()
                    ->cabin(trim($m[1]));
            }
        }

        // Price
        $priceText = mb_substr($textPdf, $this->containsText($textPdf, $this->t('Payment Receipt')));

        if (preg_match("/\s+{$this->opt($this->t('Base fare amount'))}\s+(?<amount>\d[\d\., ]*) *(?<currency>[A-Z]{3})\s*\n/u", $priceText, $m)) {
            $email->price()
                ->cost((float) PriceHelper::parse($m['amount'], $m['currency']))
            ;
        }

        if (preg_match("/{$this->opt($this->t('Base fare amount'))}[\s\S]*?\n\s*{$this->opt($this->t('Tax & surcharges'))}\s*\n(?<taxes>[\s\S]*?;\s*\n)?\s*\d[\d\., ]* *[A-Z]{3}\s*\n\s*(?<amount>\d[\d\., ]*) *(?<currency>[A-Z]{3})\s*\n\s*{$this->opt($this->t('Total amount'))}\s*\n\D+\n/u", $priceText, $m)) {
            $taxes = preg_split("/[;;]/", $m['taxes'] ?? '');

            foreach ($taxes as $tax) {
                if (preg_match("/(.+)\s*:\s*(\d[\d., ]*)\s*$/su", $tax, $tm)) {
                    $email->price()
                        ->fee(preg_replace("/\s+/", ' ', $tm[1]), (float) PriceHelper::parse($tm[2], $m['currency']));
                }
            }

            if (preg_match("/\s+{$this->opt($this->t('Other fees'))}\*\s+(?<amount>\d[\d\., ]*) *(?<currency>[A-Z]{3})\s*\n/u", $priceText, $fm)) {
                $email->price()
                    ->fee('Other fees', (float) PriceHelper::parse($fm['amount'], $fm['currency']))
                ;
            }
            $email->price()
                ->total((float) PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency'])
            ;
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function containsText($text, $needle)
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                $p = mb_stripos($text, $n);

                if ($p !== false) {
                    return $p;
                }
            }
        } elseif (is_string($needle)) {
            return mb_stripos($text, $needle);
        }

        return false;
    }

    private function columnPositions($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColumnPositions($row));
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

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
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

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColumnPositions($row));
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

    private function striposArray($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (mb_stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
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
        $str = str_replace("\n", " ", $str);
        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // 16-Dec-21, 07:40
            // 03 juil. 25, 10:05
            "/^\s*(\d+)\s*-\s*(\w+)\.?\s*-\s*(\d{2})[,\s]+(\d+:\d+\s*A?P?M?)\s*$/",
        ];
        $out = [
            "$1 $2 20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("/\d+\s+([^\d\s]+)\s+\d{4}/", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
