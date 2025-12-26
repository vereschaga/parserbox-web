<?php

namespace AwardWallet\Engine\itcviajes\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "itcviajes/it-12542672.eml, itcviajes/it-12542938.eml, itcviajes/it-770536712.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            // 'BOOKING REFERENCE:' => '',
            'PASSENGER:' => 'PASSENGER:',
            // 'Ticket:' => '',
            // 'Frequent Flyer:' => '',
            // 'Departure:' => '',
            // 'Arrival:' => '',
            // 'Duration:' => '',
            'Flight:'    => 'Flight:',
            'Book. Ref:' => 'Book. Ref:',
            // 'Reservation status:' => '',
            // 'Class:' => '',
            // 'Meal:' => '',
            // 'Seat' => '',
            // 'for' => '',
            // 'Equipment:' => '',
            // 'Operated by:' => '',
            // '(continued)' => '',
        ],
        'es' => [
            'BOOKING REFERENCE:'  => 'LOCALIZADOR DE AMADEUS:',
            'PASSENGER:'          => 'PASAJERO:',
            'Ticket:'             => 'Billete Emitido:',
            'Frequent Flyer:'     => 'P. Frecuente:',
            'Departure:'          => 'Salida:',
            'Arrival:'            => 'Llegada:',
            'Duration:'           => 'Duración:',
            'Flight:'             => 'Vuelo:',
            'Book. Ref:'          => 'Reserva:',
            'Reservation status:' => 'Estado de la reserva:',
            'Class:'              => 'Clase:',
            'Meal:'               => 'Comida:',
            'Seat'                => 'Asiento',
            'for'                 => 'para',
            'Equipment:'          => 'Equipo:',
            'Operated by:'        => 'Operado por:',
            // '(continued)' => '',
        ],
    ];

    private $detectFrom = "itcviajes.com";

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]itcviajes\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->logger->debug($text);

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['PASSENGER:'])
                && $this->strposAll($text, $dict['PASSENGER:']) !== false
                && !empty($dict['Book. Ref:'])
                && $this->strposAll($text, $dict['Book. Ref:']) !== false
                && !empty($dict['Flight:'])
            ) {
                $pos = $this->strposAll($text, $dict['Book. Ref:']);
                $part = '';

                if ($pos > 100) {
                    $part = mb_substr($text, $pos - 100, 200);
                }

                if (preg_match("/\n *{$this->opt($dict['Flight:'])}.+\n *{$this->opt($dict['Book. Ref:'])}.+/", $part)) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

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

    public function niceTravellers($name)
    {
        return preg_replace(["/\s+(Mr|Ms|Mstr|Miss|Mrs)\s*$/i", "/^\s*(.+?)\s*\/\s*(.+?)\s*$/"],
            ['', '$2 $1'], $name);
    }

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        // $this->logger->debug('Pdf text = ' . print_r($textPdf, true));

        $email->obtainTravelAgency();
        $conf = $this->re("/\n *{$this->opt($this->t('BOOKING REFERENCE:'))} *([A-Z\d]{5,7})\n[\s\S]*{$this->opt($this->t('PASSENGER:'))}/", $textPdf);
        $confName = $this->re("/\n *({$this->opt($this->t('BOOKING REFERENCE:'))}) *[A-Z\d]{5,7}\n[\s\S]*{$this->opt($this->t('PASSENGER:'))}/", $textPdf);
        $email->ota()
            ->confirmation($conf, trim($confName, ':'));

        $f = $email->add()->flight();

        $traveller = $this->niceTravellers(
            $this->re("/\n *{$this->opt($this->t('PASSENGER:'))}\s*\n\s*([A-Z\- \'\/]+)\n\s*{$this->opt($this->t('Ticket:'))}/", $textPdf));
        $f->general()
            ->noConfirmation()
            ->traveller($traveller)
        ;

        $this->logger->error("/\n *{$this->opt($this->t('Ticket:'))} *[A-Z\d]{2}\/ETKT *(.+)/");

        // Issued
        $f->issued()
            ->ticket($this->re("/\n *{$this->opt($this->t('Ticket:'))} *(?:[A-Z\d]{2}\/ETKT *)?((?:[\d\s]+|[\d\-\s]+))\n/", $textPdf), false, $traveller);

        // Program
        $account = $this->re("/\n *{$this->opt($this->t('Frequent Flyer:'))} *(.+)/", $textPdf);

        if (!empty($account)) {
            $f->program()
                ->account($account, false, $traveller);
        }

        // Segments
        $segments = $this->split("/\n(.*\d{4}.*(?:\n.*){1,2}\n *{$this->opt($this->t('Departure:'))})/", $textPdf);

        foreach ($segments as $sText) {
            // $this->logger->debug('$sText = ' . print_r($sText, true));

            $s = $f->addSegment();

            // Airline
            if (preg_match("/{$this->opt($this->t('Flight:'))} *([A-Z][A-Z\d]|[A-Z\d][A-Z]) *(\d{1,4})[\s\-]+/", $sText, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $s->airline()
                ->operator($this->re("/\n *{$this->opt($this->t('Operated by:'))} *(.+)/", $sText));

            $dateRelative = $this->normalizeDate($this->re("/^\s*(.+)/", $sText), null);

            $route = array_map('trim', explode(' - ', preg_replace('/\s+/', ' ', $this->re("/^\s*.+\n([\s\S]+?)\n *{$this->opt($this->t('Departure:'))}/", $sText))));

            if (count($route) !== 2) {
                $route = [];
            }
            $re = "/^\s*(?<date>\w+ +\w+ +\d+:\d+(?: *[ap]m)?)\s*-\s*(?<name>.+?)(?:(?:\s*,)?\s+T?ERMINAL\s*(?<terminal>[\w\-]+[\w\- ]*?))?\s*$/ui";
            // $this->logger->debug('$re = ' . print_r($re, true));

            // Departure
            $departure = $this->re("/\n *{$this->opt($this->t('Departure:'))} *([\s\S]+?)\n\s*({$this->opt($this->t('Arrival:'))}|{$this->opt($this->t('(continued)'))})/", $sText);

            if (preg_match($re, $departure, $m)) {
                if (!empty($route[0])) {
                    if (stripos($m['name'], $route[0]) === false) {
                        $m['name'] = $route[0] . ' ' . $m['name'];
                    }
                }
                $s->departure()
                    ->name(ucwords(strtolower($m['name'])))
                    ->noCode()
                    ->date(!empty($dateRelative) ? $this->normalizeDate($m['date'], $dateRelative) : null);
            }

            // Arrival
            $arrival = $this->re("/\n *{$this->opt($this->t('Arrival:'))} *([\s\S]+?)\n\s*({$this->opt($this->t('Duration:'))}|{$this->opt($this->t('(continued)'))})/", $sText);

            if (preg_match($re, $arrival, $m)) {
                if (!empty($route[1])) {
                    if (stripos($m['name'], $route[1]) === false) {
                        $m['name'] = $route[1] . ' ' . $m['name'];
                    }
                }
                $s->arrival()
                    ->name(ucwords(strtolower($m['name'])))
                    ->noCode()
                    ->date(!empty($dateRelative) ? $this->normalizeDate($m['date'], $dateRelative) : null);
            }

            // Extra
            $s->extra()
                ->duration($this->re("/\n *{$this->opt($this->t('Duration:'))} *(.+)/u", $sText))
                ->status($this->re("/\n *{$this->opt($this->t('Reservation status:'))} *(.+)/u", $sText))
                ->cabin($this->re("/\n *{$this->opt($this->t('Class:'))} *(.+)/u", $sText))
                ->meal($this->re("/\n *{$this->opt($this->t('Meal:'))} *(.+)/u", $sText))
                ->aircraft($this->re("/\n *{$this->opt($this->t('Equipment:'))} *(.+)/u", $sText))
            ;

            if (preg_match_all("/\n *{$this->opt($this->t('Seat'))} *(\d{1,3}[A-Z]) .+ {$this->opt($this->t('for'))} (.+)/u", $sText, $m)) {
                foreach ($m[0] as $i => $v) {
                    $s->extra()
                        ->seat($m[1][$i], true, true, $this->niceTravellers($m[2][$i]));
                }
            }
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

    private function strposAll($text, $needle)
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                $pos = mb_strpos($text, $n);

                if ($pos !== false) {
                    return $pos;
                }
            }
        } elseif (is_string($needle)) {
            return mb_strpos($text, $needle);
        }

        return false;
    }

    private function normalizeDate(?string $date, $relativeDate): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        $year = date("Y", $relativeDate);

        $in = [
            // 18 SEP 10:15am
            '/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d+:\d+(?:\s*[ap]m)?)\s*$/ui',
            // LUNES, 28 DE OCTUBRE DE 2024
            '/^\s*[[:alpha:]\-]+,\s*(\d{1,2})\s+(?:de\s+)?([[:alpha:]]+)\s+(?:de\s+)?(\d{4})\s*$/ui',
        ];
        $out = [
            '$1 $2 %year%, $3',
            '$1 $2 $3',
        ];

        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('date replace = ' . print_r( $date, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (!empty($relativeDate) && $relativeDate > strtotime('01.01.2000') && strpos($date, '%year%') !== false && preg_match('/^\s*(?<date>\d+ \w+) %year%(?:\s*,\s(?<time>\d{1,2}:\d{1,2}.*))?$/', $date, $m)) {
            // $this->logger->debug('$date (no week, no year) = '.print_r( $m['date'],true));
            $date = EmailDateHelper::parseDateRelative($m['date'], $relativeDate);

            if (!empty($date) && !empty($m['time'])) {
                return strtotime($m['time'], $date);
            }

            return $date;
        } elseif ($year > 2000 && preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $date, $m)) {
            // $this->logger->debug('$date (week no year) = '.print_r( $date,true));
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/^\d+ [[:alpha:]]+ \d{4}(,\s*\d{1,2}:\d{2}(?: ?[ap]m)?)?$/ui", $date)) {
            // $this->logger->debug('$date (year) = '.print_r( $date,true));
            return strtotime($date);
        } else {
            return null;
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
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
