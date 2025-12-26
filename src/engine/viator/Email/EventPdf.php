<?php

namespace AwardWallet\Engine\viator\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EventPdf extends \TAccountChecker
{
    public $mailFiles = "viator/it-167662848.eml, viator/it-236054160.eml, viator/it-40326553.eml, viator/it-50781622-pt.eml, viator/it-53088323.eml";

    public static $detectProvider = [
        'tripadvisor' => [
            'from' => '/[@\.]tripadvisor\.com/',
            'text' => 'tripadvisor',
        ],
        'viator' => [
            'from'  => '/[@\.]viator\.com/',
            'text'  => 'viator',
            'confs' => ['Tour operator confirmation no: VT-', 'Tour operator confirmation no: VIA-', "Tour operator confirmation no: francy"],
        ],
    ];

    public static $dictionary = [
        'pt' => [
            'Booking ref'                    => 'Referência da reserva / Booking ref',
            'Cancellation Policy'            => 'Política de cancelamento',
            'Tour operator:'                 => 'Operadora de turismo / Tour operator:',
            'Need to Make Changes or Cancel' => 'Precisa fazer alterações ou cancelamentos',
            'Adults'                         => ['adultos'],
            //            'Children' => [''],
            //            'Youths' => '',
            'pointPairs' => [
                ['start' => 'Ponto de partida', 'end' => 'Idioma da excursão'],
            ],
            // 'garbageStarting' => [''],
            'Departure Time' => 'Horário de funcionamento de',
        ],
        'en' => [
            'Booking ref'                    => ['Booking ref', 'Booking ref. / Référence de la réservation', 'Booking ref. / Referência da reserva', 'Booking ref. / Ref. de la reserva'],
            // 'Cancellation Policy' => '',
            'Tour operator:'                 => ['Tour operator:', 'Tour operator / Agent de voyage:', 'Tour operator confirmation no:',
                'Tour operator / Operadora de turismo:', 'Tour operator / Operador de la excursión:', ],
            'Need to Make Changes or Cancel' => 'Need to Make Changes or Cancel',
            'Adults'                         => ['Adults', 'Adult'],
            'Children'                       => ['Children', 'Child'],
            'Youths'                         => 'Youths',
            'pointPairs'                     => [
                ['start' => 'Meeting Point', 'end' => 'Start time'],
                ['start' => 'Pickup Point', 'end' => 'Start time'],
                ['start' => 'Departure Point', 'end' => 'Departure Point:'],
                ['start' => 'Departure Point', 'end' => 'Directions'],
                ['start' => 'Departure Point', 'end' => 'Tour Language'],
                ['start' => 'Departure Point', 'end' => 'Inclusions & Exclusions'],
                ['start' => 'Departure Point', 'end' => 'Before You Go'],
                ['start' => 'Pickup Point', 'end' => 'Opening hours'],
            ],
            'garbageStarting' => ['Departure Point:', 'Attention:', 'Please confirm', 'at the entrance', 'departing at', 'meet in'],
            // 'Departure Time' => '',
        ],
    ];

    private $lang = 'en';

    private $dateEventArray = [];

    private $detects = [
        'Tour Specific Inquiries',
        'Tour-specific inquiries',
        'Consultas específicas sobre a excursão',
        'Contact your travel agent or Viator customer care',
        '/^ {0,10}Need to Make Changes or Cancel\?[ ]{2,}Tour Specific Inquiries\n/',
        '/\n {0,10}Need to make[ ]{2,}Tour-specific enquiries\n/',
        '',
    ];

    private $from = '/[@.]viator\.com$/i';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');
        $headerAttach = '';

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->detects as $detect) {
                if (stripos($detect, '/') === 0 && preg_match($detect, $textPdf)
                ) {
                    if ($this->assignLang($textPdf)) {
                        $this->parseEmail($email, $textPdf);
                    }

                    if (isset($parser->getAttachments()[$pdf]['headers'])) {
                        $headerAttach .= "\n" . implode('\n', (array) $parser->getAttachments()[$pdf]['headers']);
                    }
                } elseif (false !== stripos($textPdf, $detect)) {
                    $checkText = strstr($textPdf, $detect, true);

                    if (strpos($checkText, 'Transfer from') !== false || strpos($checkText, 'Transfer to') !== false) {
                        continue 2;
                    }

                    if ($this->assignLang($textPdf)) {
                        $this->parseEmail($email, $textPdf, $this->re("/filename\=[\"']*(.+)\.pdf\S*\s*(?:application|$)/", implode(' ', $parser->getAttachments()[$pdf]['headers'])));
                    }

                    if (isset($parser->getAttachments()[$pdf]['headers'])) {
                        $headerAttach .= "\n" . implode('\n', (array) $parser->getAttachments()[$pdf]['headers']);
                    }
                }
            }
        }

        $email->setType('EventPdf' . ucfirst($this->lang));

        if (count($pdfs) === 0) {
            $this->logger->debug('Pdfs not found!');

            return $email;
        }

        foreach (self::$detectProvider as $code => $provider) {
            if (!empty($provider['text'])) {
                if (false !== stripos($parser->getPlainBody(), $provider['text'])
                    || false !== stripos($textPdf, $provider['text'])
                    || false !== stripos($headerAttach, $provider['text'])
                    || false !== stripos($parser->getSubject(), $provider['text'])
                ) {
                    $email->setProviderCode($code);
                }
            }
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProvider as $provider) {
            if (!empty($provider['from']) && array_key_exists('from', $headers)
                && preg_match($provider['from'], $headers['from']) > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (isset($parser->getAttachments()[$pdf]['headers'])) {
                $headerAttach = implode('\n', (array) $parser->getAttachments()[$pdf]['headers']);
            } else {
                $headerAttach = '';
            }

            foreach (self::$detectProvider as $provider) {
                if (!empty($provider['text'])) {
                    if (false !== stripos($parser->getPlainBody(), $provider['text'])
                        || false !== stripos($textPdf, $provider['text'])
                        || false !== stripos($headerAttach, $provider['text'])
                        || false !== stripos($parser->getSubject(), $provider['text'])
                    ) {
                        foreach ($this->detects as $detect) {
                            if (false !== stripos($textPdf, $detect) && $this->assignLang($textPdf)) {
                                return true;
                            }
                        }
                    }
                }

                if (isset($provider['confs']) && count($provider['confs']) > 0) {
                    foreach ($provider['confs'] as $conf) {
                        if ($this->stripos($textPdf, $conf) !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
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
        return array_keys(self::$detectProvider);
    }

    private function parseEmail(Email $email, string $pdfText, $fileName = null): void
    {
        $tickets = $this->split("/\n *({$this->opt($this->t('Booking ref'))})/", "\n\n" . $pdfText);

        foreach ($tickets as $text) {
            if (!preg_match("/({$this->opt($this->t('Booking ref'))})/", $text)) {
                continue;
            }
            $traveller = null;
            $e = $email->add()->event();

            $e->type()
                ->event();

            if (preg_match("/({$this->opt($this->t('Booking ref'))}\.?)[ ]*:[ ]*([A-Z]*\-\s*\d+)\n/s", $text, $m)) {
                $confirmation = preg_replace("/(\s+)/s", "", $m[2]);
                $e->general()->confirmation($confirmation, $m[1]);
            }

            $mailTicketText = $this->re("/{$this->opt($this->t('Booking ref'))}.*\n([\s\S]+)\n\s*{$this->opt($this->t('Need to Make Changes or Cancel'))}/i",
                $text);

            if (empty($mailTicketText)) {
                $mailTicketText = $this->re("/{$this->opt($this->t('Booking ref'))}.*\n([\s\S]+)\n {0,5}Need to make {2,}Tour-specific enquiries/i",
                    $text);
            }
            $mailTicket = $this->splitCols($mailTicketText, $this->rowColsPos($this->inOneRow($mailTicketText)));
            $dateRe = "((?:\d{4}|\d{1,2}|(?:de )?[[:alpha:]\-]{1,10}(?: de)?)\b[\s,.]{0,3}?){4}(?:\d{1,2}:\d{2}.*?)?";
            $re = "/^\s*(.+)\n\s*([\S\s]+)\n\n\n(?<date>{$dateRe}(?:\s*\/\s*{$dateRe})?)\n/u";

            if (preg_match($re, $mailTicket[0] ?? '', $m)) {
                // $e->general()
                //     ->traveller($m[1]);
                $traveller = $m[1];

                if (!empty($fileName) && preg_match("/^\s*" . preg_replace("/ /", '\s+',
                            preg_quote($fileName)) . "(\n|$)/", $m[2])) {
                    $mainName = $fileName;
                } else {
                    $namesRows = array_filter(explode("\n", $m[2]));
                    $rlen = strlen($namesRows[0]);
                    $mainName = $namesRows[0];

                    foreach ($namesRows as $i => $row) {
                        if ($i === 0) {
                            continue;
                        }

                        if (isset($namesRows[$i + 1]) && (strlen($row) + strlen($this->re("/^( *\S+)/",
                                    $namesRows[$i + 1]))) < $rlen) {
                            $mainName .= "\n" . $namesRows[$i];

                            break;
                        }

                        if (!isset($namesRows[$i + 1]) && !preg_match("/\d{1,2}:\d{2}/", $namesRows[$i])) {
                            $mainName .= "\n" . $namesRows[$i];

                            break;
                        }
                    }
                }

                $e->place()
                    ->name(preg_replace('/\s+/', ' ', $mainName));

                $date = $m[3];

                if ($this->lang == 'en') {
                    $date = preg_replace("/^(.+)\\/.+/s", '$1', $date);
                } else {
                    $date = preg_replace("/^.*\\/\s*(.+)/s", '$1', $date);
                }
                $e->booked()
                    ->start(strtotime($date))
                    ->noEnd();

                if (empty($time = $this->re('/[ ]+(?:\d+:\d+ )?\((?:DEFAULT|TG\d|\d+)~(\d+:\d+)\)\s+[ ]+((?:\w+[ ,.]+)?(?:\w+[ ,.]+\d+|\d+[ ,.]+\w+)[ ,.]+\d{4})[ ]*/',
                    $text))) {
                    if (empty($time = $this->re('/[ ]+(\d+:\d+)\s+[ ]+(\w+ \d{1,2} \w+ \d{4})[ ]*/', $text))) {
                        $time = $this->re("/{$this->opt($this->t('Departure Time'))}.*\n+(?: {40,}.*\n+){0,2}(\d+:\d+(?:\s*[ap]m)?)/i",
                            $text);
                    }
                }

                if (!empty($time)) {
                    $e->booked()->start(strtotime($time, $e->getStartDate()));
                }
            }

            $adults = $this->re("/(\d{1,2}) {$this->opt($this->t('Adults'))}/", $mailTicket[0] ?? '') ?? 0;
            $kids = $this->re("/(\d{1,2}) {$this->opt($this->t('Children'))}/", $mailTicket[0] ?? '') ?? 0;
            $youths = $this->re("/(\d{1,2}) {$this->opt($this->t('Youths'))}/", $mailTicket[0] ?? '') ?? 0;

            /* address */

            $departureText = '';

            foreach ((array) $this->t('pointPairs') as $pair) {
                if (preg_match("/((?:^[ ]*|.+[ ]{2}){$this->opt($pair['start'])}.*\n[\s\S]+?)\s+(?:^[ ]*|.+[ ]{2}){$this->opt($pair['end'])}/im", $text, $matches)) {
                    $departureTable = $this->splitCols($matches[1], $this->rowColsPos($this->inOneRow($matches[1])));

                    if (count($departureTable) > 0 && preg_match("/{$this->opt($pair['start'])}.*\n+([ ]*\S[\S\s]{2,})$/i", $departureTable[0], $m)
                        || count($departureTable) > 1 && preg_match("/{$this->opt($pair['start'])}.*\n+([ ]*\S[\S\s]{2,})$/i", $departureTable[1], $m)
                    ) {
                        $departureText = $m[1];
                    }

                    break;
                }
            }

            if (preg_match("/^(\s*\S[\s\S]+?\S)(?:\n+.*{$this->opt($this->t('garbageStarting'))}|\n{6})/i", $departureText, $m)) {
                $departureText = $m[1];
            }

            // cut direct address
            $patternRegions = "(?:USA|Paris\s*[,]+\s*France|Roma(?:\s+RM)?\s*[,]+\s*Italy|Barcelona\s*[,]+\s*Spain)";
            $departureText = preg_replace("/^(.+?[,\s]+\d{5}[,\s]+{$patternRegions})\s*\n.*$/s", '$1', $departureText);

            // remove duplicate rows
            $departureText = implode("\n", array_unique(preg_split('/\n+/', $departureText)));

            $e->place()->address(preg_replace(['/\([^)(]*\)/', '/\s+/', '/(?:\s*,\s*)+/'], [' ', ' ', ', '], trim($departureText)));

            /* phone */

            if ($phone = $this->re('/(?:Tour Specific Inquiries|Tour-specific inquiries).+?(\+[\-\d()]{9,14})/s',
                $text)) {
                $e->place()->phone($phone);
            }

            $allEvents = $email->getItineraries();
            $fountEvent = false;

            if (count($this->dateEventArray) == 0) {
                $this->dateEventArray[] = $e->getStartDate();
            } elseif (!empty($e->getStartDate()) && !in_array($e->getStartDate(), $this->dateEventArray)) {
                $this->dateEventArray[] = $e->getStartDate();
            } else {
                $email->removeItinerary($e);
            }

            foreach ($allEvents as $event) {
                /** @var \AwardWallet\Schema\Parser\Common\Event $event */
                if ($event->getId() !== $e->getId()) {
                    if (serialize(array_diff_key($event->toArray(), ['travellers' => [], 'guestCount' => [], 'kidsCount' => []])) === serialize($e->toArray())) {
                        $fountEvent = true;
                        $event->booked()
                            ->guests($event->getGuestCount() + (int) $adults)
                            ->kids($event->getKidsCount() + (int) $kids + (int) $youths)
                        ;
                    }

                    if (!preg_match("/^\s*passenger .*/i", $traveller)
                        && !in_array($traveller, array_column($event->getTravellers(), 0))
                    ) {
                        $event->general()
                            ->traveller($traveller, true);
                    }

                    //$email->removeItinerary($e);
                }
            }

            if ($fountEvent === false) {
                $e->booked()
                    ->guests($adults)
                    ->kids((int) $kids + (int) $youths)
                ;

                if (!preg_match("/^\s*passenger .*/i", $traveller)) {
                    $event->general()
                        ->traveller($traveller, true);
                }
            }
        }
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
            foreach ($rows as $row) {
                if (preg_match('/^[\S]+.+?\s{2,}[\S]+.+$/', $row)) {
                    $pos = $this->rowColsPos($row);

                    break;
                }
            }

            if (empty($pos)) {
                $pos = $this->rowColsPos($rows[0]);
            }
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function assignLang($body): bool
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words['Tour operator:'], $words['Booking ref'])) {
                if ($this->stripos($body, $words['Tour operator:']) && $this->stripos($body, $words['Booking ref'])) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
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

    private function split($re, $text): array
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
