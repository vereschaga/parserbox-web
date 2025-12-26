<?php

namespace AwardWallet\Engine\volotea\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "volotea/it-41652951.eml, volotea/it-41697410.eml, volotea/it-42207882.eml";

    public $reFrom = [".volotea.com"];
    public $reBodyPdf = [
        'en' => ['Keep this boarding card until you arrive'],
        'it' => ['Conservala fino all’arrivo a destinazione'],
        'es' => ['Conserva esta tarjeta hasta tu destino'],
        'pt' => ['Guarde este cartão de embarque até'],
        'fr' => ['Conservez cette carte jusqu’à votre'],
    ];
    public $reSubject = [
        'Volotea · Carta d\'imbarco',
        'Volotea · Tarjeta de embarque',
        'Volotea · Carte d\'embarquement',
        'Volotea Cartão Embarque',
        'Volotea · Boarding pass',
        'Boarding pass Volotea',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'Download your boarding pass' => ['Download your boarding pass', 'Download your boaring pass'],
            'YOUR BOARDING CARD'          => 'YOUR BOARDING CARD',
            'DEPARTURE'                   => 'DEPARTURE',
            //            'IT\'S MORE HANDY IF YOU FOLD IT!'=>'',
            // body
            'Record Locator:' => 'Record Locator:',
            'Origin:'         => 'Origin:',
        ],
        'it' => [
            'Download your boarding pass'      => ['Download your boarding pass', 'Download your boaring pass'],
            'YOUR BOARDING CARD'               => 'LA TUA CARTA D’IMBARCO',
            'FLIGHT'                           => 'VOLO',
            'DEPARTURE'                        => 'PARTENZA',
            'IT\'S MORE HANDY IF YOU FOLD IT!' => 'PIÙ COMODO SE LA PIEGHI!',
            'CONFIRMATION NO.:'                => 'N. DI CONFERMA:',
            'SEAT'                             => 'POSTO',
            'YOUR FLIGHT SCHEDULE...'          => 'GLI ORARI DEL TUO VOLO...',
        ],
        'es' => [
            'Download your boarding pass'      => ['Download your boarding pass', 'Download your boaring pass'],
            'YOUR BOARDING CARD'               => 'TU TARJETA DE EMBARQUE',
            'FLIGHT'                           => 'VUELO',
            'DEPARTURE'                        => 'SALIDA',
            'IT\'S MORE HANDY IF YOU FOLD IT!' => '¡MÁS CÓMODO SI LA DOBLAS!',
            'CONFIRMATION NO.:'                => 'Nº DE CONFIRMACIÓN:',
            'SEAT'                             => 'ASIENTO',
            'YOUR FLIGHT SCHEDULE...'          => 'LOS HORARIOS DE TU VUELO...',
        ],
        'pt' => [
            'Download your boarding pass'      => ['Download your boarding pass', 'Download your boaring pass'],
            'YOUR BOARDING CARD'               => 'O SEU CARTÃO DE EMBARQUE',
            'FLIGHT'                           => 'VOO',
            'DEPARTURE'                        => 'PARTIDA',
            'IT\'S MORE HANDY IF YOU FOLD IT!' => 'É MAIS PRÁTICO SE OS DOBRAR!',
            'CONFIRMATION NO.:'                => 'N.º DE CONFIRMAÇÃO:',
            'SEAT'                             => 'LUGAR',
            'YOUR FLIGHT SCHEDULE...'          => 'O HORÁRIO DO SEU VOO...',
        ],
        'fr' => [
            'Download your boarding pass'      => ['Download your boarding pass', 'Download your boaring pass'],
            'YOUR BOARDING CARD'               => 'VOTRE CARTE D’EMBARQUEMENT',
            'FLIGHT'                           => 'VOL',
            'DEPARTURE'                        => 'DÉPART',
            'IT\'S MORE HANDY IF YOU FOLD IT!' => 'EN LA PLIANT, ELLE SERA',
            'CONFIRMATION NO.:'                => 'N ° DE CONFIRMATION:',
            'SEAT'                             => 'SIÈGE',
            'YOUR FLIGHT SCHEDULE...'          => 'VOICI LES HORAIRES DE VOTRE VOL...',
        ],
    ];
    private $keywordProv = 'Volotea';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = 'Pdf';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectPdf($text) && $this->assignLang($text)) {
                        $this->parseEmailPdf($text, $email);
                    }
                }
            }
        }

        if (count($email->getItineraries()) === 0) {
            if ($this->assignLangBody($this->http->Response['body'])) {
                $href = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Download your boarding pass'))}]/ancestor::td[1]//a/@href");
                $this->logger->debug($href);

                if (!empty($href) && $this->http->FindPreg("#pdf#i", false, $href)) {
                    $http2 = clone $this->http;
                    $http2->RetryCount = 0;
                    $file = $http2->DownloadFile($href);
                    unlink($file);
                    $pdf = $http2->Response['body'];

                    if (($text = \PDF::convertToText($pdf)) !== null) {
                        if ($this->detectPdf($text) && $this->assignLang($text)) {
                            $this->parseEmailPdf($text, $email);
                            $type = 'LinkPdf';
                        }
                    }
                }
            }

            if (count($email->getItineraries()) === 0) {
                //check format -> mark junk
                if (isset($href) && !empty($href) && $this->detectBody()) {
                    $email->setIsJunk(true);
                    $type = 'Junk';
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($type) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) && $this->assignLang($text)) {
                return true;
            }
        }

        return $this->detectBody();
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
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
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
        $formats = 2; // pdf/link | junk
        $cnt = $formats * count(self::$dict);

        return $cnt;
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $reservations = $this->splitter("#\n([ ]*{$this->t('YOUR BOARDING CARD')})#", "CtrlStr\n" . $textPDF);

        foreach ($reservations as $reservation) {
            $r = $email->add()->flight();
            $pos[] = 0;

            if (preg_match("#\n(.+?){$this->t('IT\'S MORE HANDY IF YOU FOLD IT!')}#u", $reservation, $m)) {
                $pos[] = mb_strlen($m[1]);
            } else {
                $this->logger->debug('other format');

                return false;
            }

            if (!empty($str = mb_strstr($reservation, $this->t('YOUR FLIGHT SCHEDULE...'), true))) {
                $reservation = $str;
            }
            $table = $this->splitCols($reservation, $pos);
            $text = $table[0];
            $s = $r->addSegment();
            $r->general()
                ->traveller($this->re("#\n(.+)\n+{$this->t('FLIGHT')}[ ]{2,}#", $text), true)
                ->confirmation($confNo = $this->re("#\n+{$this->t('CONFIRMATION NO.:')}[ ]*([A-Z\d]{5,6})#", $text),
                    trim($this->t('CONFIRMATION NO.:'), ":"));

            if ($confNo === $this->http->FindSingleNode("//text()[{$this->starts('Record Locator:')}]/following::text()[normalize-space()!=''][1]",
                    null, false,
                    "#^[A-Z\d]{5,6}$#") && $this->http->XPath->query("//text()[{$this->starts('Origin:')}]")->length === 1
            ) {
                $s->departure()
                    ->code($this->http->FindSingleNode("//text()[{$this->starts('Origin:')}]/following::text()[normalize-space()!=''][1]"));
                $s->arrival()
                    ->code($this->http->FindSingleNode("//text()[{$this->starts('Destination:')}]/following::text()[normalize-space()!=''][1]"));
            } else {
                $s->departure()->noCode();
                $s->arrival()->noCode();
            }

            if (preg_match("#\n([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d+)[ ]{2,}.+\n+{$this->t('SEAT')}#", $text, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match("#\n+{$this->opt($this->t('SEAT'))}[ ]{2,}{$this->t('DEPARTURE')}\n+(?<seat>\d+[A-z])[ ]{2,}.+\n+(?<depName>.+)\n+(?<depDate>.+)\n+(?<arrName>.+)\n+(?<arrDate>.+)\n+{$this->t('CONFIRMATION NO.:')}#",
                $text, $m)) {
                // BORDEAUX · TERMINAL A
                if (preg_match("#^(.+)[ ]*·[ ]*TERMINAL[ ]+(.+)$#", $m['depName'], $v)) {
                    $s->departure()
                        ->name($v[1])
                        ->terminal($v[2]);
                } else {
                    $s->departure()
                        ->name($m['depName']);
                }
                $s->departure()->date($this->normalizeDate($m['depDate']));

                if (preg_match("#^(.+)[ ]*·[ ]*TERMINAL[ ]+(.+)$#", $m['arrName'], $v)) {
                    $s->arrival()
                        ->name($v[1])
                        ->terminal($v[2]);
                } else {
                    $s->arrival()
                        ->name($m['arrName']);
                }
                $s->arrival()->date($this->normalizeDate($m['arrDate']));
                $s->extra()->seat($m['seat']);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //06:15 JUL 25, 2019
            '#^(\d+:\d+)\s+(\D+)\s+(\d+),\s+(\d{4})$#u',
            //10:50 27 LUG, 2019
            '#^(\d+:\d+)\s+(\d+)\s+(\D+),\s+(\d{4})$#u',
        ];
        $out = [
            '$3 $2 $4, $1',
            '$2 $3 $4, $1',
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

    private function detectPdf($body)
    {
        if (stripos($body, $this->keywordProv) === false) {
            return false;
        }

        if (isset($this->reBodyPdf)) {
            foreach ($this->reBodyPdf as $lang => $reBody) {
                if ($this->stripos($body, $reBody)) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function detectBody()
    {
        if ($this->http->XPath->query("//a[contains(@href,'volotea.com')]")->length > 0) {
            $condition1 = $this->http->XPath->query("//text()[{$this->starts('Origin:')}]/following::text()[normalize-space()!=''][2][{$this->starts('Destination:')}]/following::text()[normalize-space()!=''][2][{$this->starts('Passenger ')}]")->length > 0;
            $condition2 = $this->http->XPath->query("//text()[{$this->starts('Passenger ')}]/following::text()[normalize-space()!=''][1][{$this->starts('Name:')}]/following::text()[normalize-space()!=''][2][{$this->starts('Last name')}]")->length > 0;

            if ($condition1 && $condition2) {
                return true;
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['YOUR BOARDING CARD'], $words['DEPARTURE'])) {
                if (stripos($body, $words['YOUR BOARDING CARD']) !== false
                    && stripos($body, $words['DEPARTURE']) !== false
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLangBody($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Record Locator:'], $words['Origin:'])) {
                if (stripos($body, $words['Record Locator:']) !== false
                    && stripos($body, $words['Origin:']) !== false
                ) {
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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
