<?php

namespace AwardWallet\Engine\dohop\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightPDF extends \TAccountChecker
{
    public $mailFiles = "dohop/it-731001576.eml, dohop/it-731290119.eml, dohop/it-732864421.eml, dohop/it-733771823.eml, dohop/it-738999604.eml, dohop/it-740548585.eml, dohop/it-747107512.eml, dohop/it-752262812.eml, dohop/it-762350177.eml, dohop/it-766160697.eml, dohop/it-886006935.eml, dohop/it-886177614.eml";
    public $pdfNamePattern = ".*pdf";

    public $lang = '';
    public $date = null;
    public $year = null;

    public $reBody = [
        'en' => ['your journey', 'Flight 1 of'],
        'pt' => ['Obrigado', 'Voo 1 de'],
        'es' => ['su viaje', 'Gracias'],
        'fr' => ['votre voyage', 'Vol 1 sur'],
    ];

    public static $dictionary = [
        'en' => [
            'Collect your luggage' => ['Collect your luggage', 'Collect your bags'],
        ],
        'pt' => [
            'Booking reference'              => ['Referência da reserva'],
            'Manage Booking'                 => ['Gerenciar Reserva'],
            'To check in'                    => ['Para fazer o check -in'],
            'your journey.'                  => ['sua jornada.'],
            'Airline details and management' => ['Detalhes e gestão da companhia aérea'],
            'Collect your luggage'           => ['Recolha a sua bagagem'],
            'Seats'                          => ['Lugares'],
            'Flight'                         => ['Voo'],
            'See details'                    => ['Ver detalhes'],
        ],
        'es' => [
            'Booking reference'    => ['Referencia de la reserva'],
            'To check in'          => ['Para registrarse'],
            'Manage Booking'       => ['Gestionar Reserva'],
            'Dohop support page'   => ['Página de soporte de Dohop'],
            'your journey.'        => ['su viaje.'],
            'Collect your luggage' => ['Recoja su equipaje'],
            'Date of birth'        => ['Fecha de nacimiento'],
            'See details'          => ['Tu viaje'],
        ],
        'fr' => [
            'Booking reference'            => ['Référence de réservation', 'Numéro de confirmation'],
            'To check in'                  => ['Pour vous enregistrer'],
            'Manage Booking'               => ['Gérer la Réservation'],
            'your journey.'                => ['votre voyage.'],
            'Collect your luggage'         => ['Récupérez vos bagages'],
            'Seats'                        => ['Sièges'],
            'Flight'                       => ['Vol'],
            'stop'                         => ['correspondance'],
            'You will arrive the next day' => ['Vous arriverez le lendemain'],
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

            $this->assignLang($text);

            if ((stripos($text, 'Dohop') !== false || $this->http->XPath->query("//text()[{$this->contains($this->t('Dohop'))}]")->length > 0)
                && $this->re("/{$this->preg_implode($this->t('Manage Booking'), true)}/s", $text) !== false
                && $this->re("/{$this->preg_implode($this->t('Collect your luggage'), true)}/s", $text) !== false
                && $this->re("/\n\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*\n\s*(?:{$this->preg_implode($this->t('Date of birth'), true)})?[\d\s]+\/[\d\s]+\/[\d\s]{4,}/s", $text) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]dohop\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = EmailDateHelper::getEmailDate($this, $parser) - 86400;

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->assignLang($text);

            $this->ParseFlightPDF($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if ($this->date === null && $this->year === null){
            $this->logger->info('Year not found');
        }

        return $email;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        // collect reservation confirmations
        $confirmationsText = $this->re("/\n((?:.*\n){4}(?:.* )?{$this->preg_implode($this->t('Booking reference'), true)}[\s\S]+{$this->preg_implode($this->t('Manage Booking'), true)})/", $text);

        $confBlock = [];

        if (preg_match_all("/^ *(?:.* )?({$this->preg_implode($this->t('Booking reference'), true)}|\([A-Z]{3}\)|[A-Z\d]{6,10})(?: .*)? *$/um", $confirmationsText, $m)) {
            $rtext = '';

            foreach ($m[0] as $v) {
                $ttext = $rtext . "\n" . $v;
                $table = $this->createTable($ttext, $this->rowColumnPositions($this->inOneRow($ttext)));
                preg_match_all("/{$this->preg_implode($this->t('Booking reference'), true)}/", $ttext, $m);

                if (preg_match_all("/{$this->preg_implode($this->t('Booking reference'), true)}/", $ttext, $m)
                    && count($m[0]) == count($table)
                ) {
                    $rightCols = 0;

                    foreach ($table as $td) {
                        if (preg_match("/({$this->preg_implode($this->t('Booking reference'), true)})\s+([A-Z\d]{6,10})(?:\s+|$)/u", $td)
                         && preg_match_all("/{$this->preg_implode($this->t('Booking reference'), true)}/u", $td, $m)
                        && count($m[0]) === 1
                        ) {
                            $rightCols++;
                        }
                    }

                    if ($rightCols == count($table)) {
                        $confBlock = array_merge($confBlock, $table);
                        $rtext = '';

                        continue;
                    }
                }
                $rtext = $rtext . "\n" . $v;
            }
        }
        $confirmations = $confBlock;

        // collect ota confirmation
        foreach ($confirmations as $confirmation) {
            if (!$this->re("/(\([A-Z]{3}\))/s", $confirmation)
                && preg_match("/(?:^|\s+)({$this->preg_implode($this->t('Booking reference'), true)}).*\s*([A-Z\d]{6,10})(?:\s+|$)/um", $confirmation, $m)
            ) {
                $email->ota()->confirmation($m[2], trim($m[1]));
            }
        }

        $f->setNoConfirmationNumber(true);

        // collect travellers
        if (preg_match_all("/\n\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*\n\s*(?:{$this->preg_implode($this->t('Date of birth'), true)})?[\d\s]+\/[\d\s]+\/[\d\s]{4,}/s", $text, $matches)) {
            $f->general()->travellers($matches[1], true);
        }

        // collect segments
        $segmentsText = $this->re("/(?:{$this->preg_implode($this->t('your journey.'), true)}|{$this->preg_implode($this->t('Check here and learn how to apply'), true)}|{$this->preg_implode($this->t('and learn how to apply'), true)}|{$this->preg_implode($this->t('See details'), true)})\s*(.+?)\s*(?:{$this->preg_implode($this->t('Airline details and management'), true)}|{$this->preg_implode($this->t('Booking reference'), true)})/s", $text);

        //it-886006935.eml
        if ($this->re("/({$this->preg_implode($this->t('Dohop connection service'), true)})/s", $segmentsText)){
            $segmentsText = $this->re("/(?:{$this->preg_implode($this->t('Your journey'), true)})\s*(.+?)\s*(?:{$this->preg_implode($this->t('Passengers'), true)})/s", $text);

            $segmentsText = preg_replace("/^(.*?)(?=\n[ ]*{$this->preg_implode($this->t('Depart'), true)})/s", "", $segmentsText);
            //it-886006935.eml
            if ($this->re("/({$this->preg_implode($this->t('Depart'), true)}.+?{$this->preg_implode($this->t('Return'), true)})/s", $segmentsText)) {
                $cols = $this->createTable($segmentsText, $this->rowColumnPositions($this->inOneRow($segmentsText)));
            } else {
                $cols = [$segmentsText];
            }
        } else if ($this->re("/({$this->preg_implode($this->t('Depart'), true)}.+?{$this->preg_implode($this->t('Return'), true)})/s", $segmentsText)) {
            $cols = $this->splitCols($segmentsText, [0, 48]);
        } else {
            $cols = [$segmentsText];
        }

        #$this->logger->debug(var_export($cols, true));

        // first segment type
        /* 20:25 Sat, 21 September
           Orlando (MCO)
           8h 35m flight
           Z0784
           10:00 Sun, 22 September
           London (LGW)*/

        $reg = "/([ ]+\d+\s*\:\s*\d+.+\n+"
            . "[ ]+.+\s+\([A-Z]{3}\)\n+"
            . "[ ]+.+\s*\w+\n+"
            . "[ ]+.+\d{1,4}[\[\]\D]+?\n+"
            . "[ ]+\d+\s*\:\s*\d+.+\n+"
            . "\s+.+\s+\([A-Z]{3}\))/m";

        // second segment type
        /* Bost on (BOS)
           22 Sept, 19:15       + 1 day
           5h 30m PLAY OG112
           Reykjavik (KEF)
           23 Sept, 04:45       + 1 day*/

        $reg2 = "/([ ]+.+\s+\([A-Z]{3}\)\n+"
            . "[ ]+.*\d+.+\d+\s*\:\s*\d+\s+(?:\s+[+]\s*\d+\s*day)?\n+"
            . "[ ]+.+\s*\w+\n+"
            . "[ ]+.+\s+\([A-Z]{3}\)\n+"
            . "[ ]+.*\d+.+\d+\s*\:\s*\d+(?:\s+[+]\s*\d+\s*day)?)(?:\n|$)/m";

        // third segment type
        /* Milan (MXP)
           Feb 05, 07:10
           1h 55m easyJet U28302
           London (LGW)
           Feb 05, 08:05*/

        $reg3 = "/([ ]*.+\s+\([A-Z]{3}\)\n+"
            . "[ ]*.+\d+\s*\:\s*\d+\n+"
            . "[ ]*.+\s*\w+[ ](?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,4}\n+"
            . "[ ]*.+\s+\([A-Z]{3}\)\n+"
            . "[ ]*.+\d+\s*\:\s*\d+)/m";

        // split columns into segments
        $segments = [];

        foreach ($cols as $col) {
            if (preg_match_all($reg, $col, $matches)
                || preg_match_all($reg2, $col, $matches)
                || preg_match_all($reg3, $col, $matches)) {
                $segments = array_merge($segments, $matches[1]);
            }
        }

        // collect segments
        foreach (array_filter($segments) as $segment) {
            $s = $f->addSegment();

            // collect department and arrival points
            if (preg_match_all("/[ ]*(?<pointName>.+?)\s*\((?<pointCode>[A-Z]{3})\)\s*(?:\n|$)/", $segment, $m, PREG_SET_ORDER)) {
                $s->departure()
                    ->name($m[0]['pointName'])
                    ->code($m[0]['pointCode']);

                $s->arrival()
                    ->name($m[1]['pointName'])
                    ->code($m[1]['pointCode']);
            }

            // collect dates
            $yearPattern = "/(?:^|\n).+?\s*[\,\.]+[\w\s]+\,?\s+(\d{4})\n+(?:.*?\n+)+?\s*{$this->opt([$segment])}/si";
            $datePattern = "/(?:^|\n).*[\.\,]+(\d+\s*[\w\s]+\,?\s+\d{4}|[\w\s]+\d+\,\s*\d{4})(?:.*?\n+)+?\s*{$this->opt([$segment])}/si";

            $this->logger->debug($datePattern);
            foreach ($cols as $col) {
                if (preg_match($yearPattern, $col, $matches)) {
                    $this->year = $matches[1];

                    if ($this->date === null){
                        if (preg_match($datePattern, $col, $matches)) {
                            $this->date = $this->normalizeDate(preg_replace("/(^\s|\n)/", ' ', $matches[1])) - 86400;
                        }
                    }

                    break;
                }
            }

            if (preg_match_all("/(?:^|\n)\s*(?<dateTime>.+?\d+\s*\:\d*\d+|\d+\s*\:\d*\d+[^\-()]+?)(?:\s+[+]\s*\d+\s*day)?\s*(?:\n+|$)/iu", $segment, $m) && ($this->date !== null || $this->year !== null)) {
                $depDate = $m['dateTime'][0];
                $arrDate = $m['dateTime'][1];

                $s->departure()
                    ->date($this->normalizeDate($depDate));
                $s->arrival()
                    ->date($this->normalizeDate($arrDate));
            }

            // collect air info
            if (preg_match("/\n\s*(?<duration>(?:\d+\s*h)?\s*(?:\d+\s*m)?)\s*.+?\,?\s+(?<aName>[A-Z]\s?[A-Z\d]|[A-Z\d]\s?[A-Z])\s*(?<fNumber>[\d\s]+)\s*\n/i", $segment, $m)) {
                $s->airline()
                    ->name(str_replace(' ', '', $m['aName']))
                    ->number(str_replace(' ', '', $m['fNumber']));
                $s->extra()
                    ->duration($m['duration']);
            }

            // collect segment confirmations
            foreach ($confirmations as $confirmation) {
                if (preg_match("/({$this->addSpacesWord($s->getDepName())}\s*\((?<dCode>[A-Z]{3})\)\W*{$this->addSpacesWord($s->getArrName())})\s*\((?<aCode>[A-Z]{3})\)/s", $confirmation, $m)) {
                    $s->departure()->code($m['dCode']);
                    $s->arrival()->code($m['aCode']);
                    $s->setConfirmation($this->re("/(?:^|\s+){$this->preg_implode($this->t('Booking reference'), true)}.*\s*([A-Z\d]{6,10})(?:\s+|$)/u", $confirmation));
                }
            }

            // collect seats
            foreach ($f->getTravellers() as $traveller) {
                $seatsText = $this->re("/{$this->addSpacesWord($traveller[0])}.+?{$this->preg_implode($this->t('Seats'), true)}(.+?)(?:\d+\s*\/\s*\d+\s*\/\s*\d{4}|$)/s", $text);

                // split text into columns
                $pos1 = strlen($this->re("/\n([a-z\s\-]+){$this->addSpacesWord($s->getDepName())}\D+?{$this->addSpacesWord($s->getArrName())}/si", $seatsText)) - 1;
                $pos2 = strlen($this->re("/\n([a-z\s\-]+{$this->addSpacesWord($s->getDepName())}\D+?{$this->addSpacesWord($s->getArrName())})/si", $seatsText));

                $cols = $this->splitCols($seatsText, [$pos1, $pos2]);

                $seats = [];

                // split columns into seats
                foreach ($cols as $col) {
                    $seats = array_merge($seats, preg_split("/{$this->preg_implode($this->t('Flight'), true)}/s", $col, -1));
                }

                // collect seatNumbers
                foreach (array_filter($seats) as $seat) {
                    if ($this->re("/({$this->addSpacesWord($s->getDepName())}.+?{$this->addSpacesWord($s->getArrName())})/s", $seat)) {
                        $seatNumber = $this->re("/\s(\d+\s?[A-Z])\s/s", $seat);

                        if (!empty($seatNumber)) {
                            $seatNumber = str_replace(' ', '', $seatNumber);
                            $s->addSeat($seatNumber, false, false, $traveller[0]);
                        }

                        break;
                    }
                }
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang($text): bool
    {
        foreach ($this->reBody as $lang => $words) {
            if (!empty($this->re("/({$this->addSpacesWord($words[0])})/s", $text))
                && !empty($this->re("/({$this->addSpacesWord($words[1])})/s", $text))) {
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

    private function addSpacesWord(string $text): string
    {
        return preg_replace('/(\S)/u', '$1 *', preg_quote($text, '#'));
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function preg_implode($field, bool $addSpaces = false): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode('|', array_map(function ($v) use ($addSpaces) {
            return $addSpaces ? $this->addSpacesWord($v) : preg_quote($v, '#');
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function normalizeDate(string $string)
    {
        $year = $this->year ?? date("Y", $this->date);

        $in = [
            "#^(\d+)\s+(?:de\s+)?([a-z\s]+?)\.?\s*\,\s+(\d+)\s*\:\s*(\d+)$#iu", // 17 de nov., 08:20 | 20 Jun, 17:20
            "#^(\d+)\s*\:\s*(\d+)\s+([a-z ]+)\s*[\,\.]\s+(\d+)\s+(?:de\s+)?(.+)$#iu", // 21:50 sex., 18 de abril
            "#^([a-z\s]+)\s+(\d+)\s*\,\s+(\d+)\s*\:\s*(\d+)$#iu", // Sep 27, 07:10
            "#^(\d+)\s*\:\s*(\d+)\s+([a-z ]+)[\,\.]\s+(\d+)\s+([a-z]+)$#iu", // 17:45 Tue, 24 September | 11:35 dim. 6 juillet
            "#^(\d+)\s*\:\s*(\d+)\s+([a-z ]+)[\,\.]\s+([a-z]+)\s+(\d+)$#iu", //17:20 Mon, October 14
        ];

        // %year% - for date without year and without week

        $out = [
            "$1 $2 %year%, $3:$4",
            "$3, $4 $5 {$year}, $1:$2",
            "$2 $1 %year%, $3:$4",
            "$3, $4 $5 {$year}, $1:$2",
            "$3, $5 $4 {$year}, $1:$2",
        ];

        $string = preg_replace($in, $out, trim($string));

        if (preg_match("#\d+\s+(\w+)\s*(?:%year%|\d{4}|\,)#", $string, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $string = str_replace($m[1], $en, $string);
            }
        }

        if (!empty($this->date) && $this->date > strtotime('01.01.2000') && strpos($string, '%year%') !== false && preg_match('/^\s*(?<date>\d+ \w+) %year%(?:\s*,\s(?<time>\d{1,2}:\d{1,2}.*))?$/', $string, $m)) {
            $string = EmailDateHelper::parseDateRelative($m['date'], $this->date);

            if (!empty($string) && !empty($m['time'])) {
                return strtotime($m['time'], $string);
            }

            return $string;
        } elseif ($year > 2000 && preg_match("/^(?<week>\w+), (?<date>\d+ \w+ \d{4}, .+)$/u", $string, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } else {
            return null;
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

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
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
}
