<?php

namespace AwardWallet\Engine\sncf\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class MyTicketPdf extends \TAccountChecker
{
    public $mailFiles = "sncf/it-12299823.eml, sncf/it-421634067.eml, sncf/it-53080767.eml, sncf/it-78464962.eml";

    public $reFrom = ["sncf.com"];
    public $reBody = [
        'en'  => ['authorized SNCF travel', 'authorised SNCF travel', 'sncf app or print it at home', 'your ticket on SNCF Connect'],
        'fr'  => ['voyage agréée SNCF', '.sncf.com ou sur l\'appli SNCF.'],
        'fr2' => ['PRÊTS ? EMBARQUEZ !', 'BON À SAVOIR'],
        'it'  => ["un'agenzia viaggi autorizzata SNCF", "ll'applicazione SNCF Connect o da"],
        'es'  => ["modificación de tu billete en SNCF"],
        'de'  => ["Ich lade mein Ticket auf mein Smartphone"],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'Booking :'                   => 'Booking :',
            'First Name :'                => ['First Name :', 'First name :'],
            'Last Name :'                 => ['Last Name :', 'Last name :'],
            'Customer reference number :' => ['Customer reference number :', 'Customer reference :', 'Loyal customer reference :'],
            'Car'                         => ['Car', 'Coach'],
            'Place'                       => ['Place', 'Seat'],
        ],
        'fr' => [
            'Booking :'                   => ['Dossier Voyage :', 'Dossier voyage :'],
            'Last Name :'                 => 'Nom :',
            'First Name :'                => 'Prénom :',
            'E-ticket no. :'              => ['Nº e-billet :', 'N° e-billet :'],
            'Customer reference number :' => ['Référence Client :', 'Référence client fidélisé :', 'Référence client :'],
            'Price :'                     => 'Prix :',
            'Car'                         => 'Voiture',
            'Place'                       => 'Place',
            'GOOD TO KNOW'                => 'BON À SAVOIR',
        ],
        'it' => [ // it-78464962.eml
            'Booking :'                   => 'Riferimento pratica :',
            'Last Name :'                 => 'Cognome :',
            'First Name :'                => 'Nome :',
            'E-ticket no. :'              => 'N° e-ticket :',
            'Customer reference number :' => ['Riferimento cliente fedele :', 'Riferimento cliente :'],
            'Price :'                     => 'Prezzo :',
            'Car'                         => 'Carrozza',
            'Place'                       => 'Posto',
            'GOOD TO KNOW'                => 'BUONO A SAPERSI',
        ],
        'es' => [
            'Booking :'                   => 'Expediente de viaje :',
            'Last Name :'                 => 'Apellidos :',
            'First Name :'                => 'Nombre :',
            'E-ticket no. :'              => 'N.º de e-ticket :',
            'Customer reference number :' => ['Referencia del cliente :'],
            'Price :'                     => 'Importe :',
            'Car'                         => 'Coche',
            'Place'                       => 'Plaza',
            'GOOD TO KNOW'                => 'A TENER EN CUENTA',
        ],
        'de' => [
            'Booking :'                   => 'Buchung :',
            'Last Name :'                 => 'Nachname :',
            'First Name :'                => 'Vorname :',
            'E-ticket no. :'              => 'Ticket-Nr. :',
            'Customer reference number :' => ['Kundenreferenz :'],
            'Price :'                     => 'Preis :',
            'Car'                         => 'Wagen',
            'Place'                       => 'Platz',
            'GOOD TO KNOW'                => 'GUT ZU WISSEN',
        ],
    ];
    private $keywordProv = 'SNCF';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null
                    && $this->detectBody($text)
                ) {
                    if ($this->assignLang($text)) {
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

            if ((stripos($text, $this->keywordProv) !== false)
                && $this->detectBody($text) && $this->assignLang($text)
            ) {
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
//        $this->logger->debug('$textPDF = '.print_r( $textPDF,true));
        $tickets = $this->splitter("/\n[ ]*({$this->opt($this->t('Booking :'))}[ ]*\w+\s+{$this->opt($this->t('Last Name :'))})/",
            "CtrlStr\n" . $textPDF);

        foreach ($tickets as $ticket) {
            $r = $email->add()->train();
            $str = strstr($ticket, $this->t('GOOD TO KNOW'), true);

            if (!empty($str)) {
                $ticket = $str;
            }
            $pos[0] = 0;
            $pos[1] = mb_strlen($this->re("/\n(.+){$this->opt($this->t('Last Name :'))}/", $ticket)) - 3;
            $table = $this->splitCols($ticket, $pos);

            $r->general()
                ->traveller($this->re("/{$this->opt($this->t('First Name :'))}[ ]+(.+)/u",
                        $table[1]) . ' ' . $this->re("/{$this->opt($this->t('Last Name :'))}[ ]+(.+)/u", $table[1]),
                    true)
                ->confirmation($this->re("/{$this->opt($this->t('Booking :'))}[ ]+(.+)/", $table[0]));

            $r->setTicketNumbers([$this->re("/{$this->opt($this->t('E-ticket no. :'))}[ ]+(.+)/", $table[1])], false);

            $account = $this->re("/{$this->opt($this->t('Customer reference number :'))}[ ]+(.+)/", $table[1]);

            if (!empty($account)) {
                $r->program()
                    ->account($account, false);
            }

            $price = $this->re("/{$this->opt($this->t('Price :'))}[ ]+(.+)/", $table[1]);
            $price = $this->getTotalCurrency($price);

            if (!empty($price['Currency']) && $price['Total'] !== null) {
                $r->price()
                    ->total($price['Total'])
                    ->currency($price['Currency']);
            }

            if (preg_match("/{$this->opt($this->t('Booking :'))}[ ]+[^\n]+\s+(?<date>.+)/",
                $table[0], $m)) {
                $date = $this->normalizeDate($m['date']);

                $segments = $this->splitter("/\n([ ]*\d+[:h]\d+ .+\s+.+? \d+ \- )/", $table[0]);

                foreach ($segments as $segment) {
                    $s = $r->addSegment();
                    /* FE: several segments
6h02          CAMBRAI
TER 43311 - 2e classe

30 min      Voiture

16h32         DOUAI
19 min      Correspondance

16h51         DOUAI
TGV INOUI 7130 - 2e classe
01h26       Voiture 16            Place   47
place assise - fenetre duo
18h17          PARIS NORD

16h45         BORDEAUX ST JEAN
TER 66433 - 1e classe
Voiture

01h52


18h37          LABENNE

                     * */

                    // Voiture 2 Haut          - Place 104
                    $re1 = "/[ ]*(?<depTime>\d+[h:]\d+)[ ]+(?<depName>.+)\s+(?<type>.+?) (?<num>\d+) \-[ ]+(?<cabin>.+)\s+(?<dur>\d[\w ]+|\d{1,2}:\d{1,2})(?:[ ]{2,}|\s*\n\s*){$this->opt($this->t('Car'))}(?:[ ]+(?<car>\d+)(?: [[:upper:]][[:lower:]]+ +\-)?[ ]+{$this->opt($this->t('Place'))}[ ]+(?<seat>\d+)\s*(?:[^\n]+|\n*))?\s+(?<arrTime>\d+[h:]\d+)[ ]+(?<arrName>.+)/";
                    // re3 - when no car and seats
                    $re3 = "/[ ]*(?<depTime>\d+[h:]\d+)[ ]+(?<depName>.+)\s+(?<type>.+?) (?<num>\d+) \-[ ]+(?<cabin>.+)\s+(?<dur>\d[\w ]+|\d{1,2}:\d{1,2})(?:[ ]{2,}|\s*\n\s*)(?<dopInfo>[^\n]+)?\s+(?<arrTime>\d+[h:]\d+)[ ]+(?<arrName>.+)/";

                    $re2 = "/[ ]*(?<depTime>\d+[h:]\d+)[ ]+(?<depName>.+)\s+(?<type>.+?) (?<num>\d+) \-[ ]+(?<cabin>.+)\s+{$this->opt($this->t('Car'))}(?:[ ]+(?<car>\d+)[ ]{2,}{$this->opt($this->t('Place'))}[ ]+(?<seat>\d+)\s+[^\n]+)?\s+(?<dur>\d[\w ]+)\s+(?<arrTime>\d+[h:]\d+)[ ]+(?<arrName>.+)/";

                    if (preg_match($re1, $segment, $m) || preg_match($re2, $segment, $m) || preg_match($re3, $segment, $m)
                    ) {
                        $s->departure()
                            ->date(strtotime(str_replace('h', ':', $m['depTime']), $date))
                            ->name($m['depName'])
                            ->geoTip('Europe');
                        $s->arrival()
                            ->date(strtotime(str_replace('h', ':', $m['arrTime']), $date))
                            ->name($m['arrName'])
                            ->geoTip('Europe');
                        $s->extra()
                            ->type($m['type'])
                            ->number($m['num'])
                            ->cabin($m['cabin'])
                            ->duration($m['dur']);

                        if (isset($m['car']) && !empty($m['car'])) {
                            $s->extra()->car($m['car']);
                        }

                        if (isset($m['seat']) && !empty($m['seat'])) {
                            $s->extra()->seat($m['seat']);
                        }

                        if (!empty($m['dopInfo']) && !preg_match("/^\D+$/", $m['dopInfo'])) {
                            // ($re3) for error
                            // if preg_match "/^\D+$/" - no car and seats, otherwise, it is not clear what the numbers are means
                            $s->extra()->car(null);
                        }
                    }
                }
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //FRIDAY, JANUARY 31st 2020
            '#^\w+,\s+(\w+)\s+(\d+)\D*\s+(\d{4})$#u',
            //MARDI 18 FEVRIER 2020
            // LUNES 21 DE AGOSTO 2023
            '#^\w+\,?\s+(\d+)\.?\s+(?:DE\s+)?(\w+)\s+(\d{4})$#u',
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->stripos($body, $reBody)) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Booking :'], $words['Last Name :'])) {
                if ($this->stripos($body, $words['Booking :']) && $this->stripos($body, $words['Last Name :'])) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function getTotalCurrency($node): array
    {
        $node = str_replace(["€", "£", "₹"], ["EUR", "GBP", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#^(?<c>[^\s\d])\s*(?<t>\d[\.\d\,\s]*\d*)$#", trim($node), $m)
            || preg_match("#^(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[^\s\d])$#", trim($node), $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t'], '.', ',');
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function stripos($haystack, $arrayNeedle): bool
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
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
}
