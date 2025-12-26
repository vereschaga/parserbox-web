<?php

namespace AwardWallet\Engine\sunwing\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class eDoc extends \TAccountChecker
{
    public $mailFiles = "sunwing/it-185028451.eml, sunwing/it-185768447.eml, sunwing/it-351704155.eml, sunwing/it-676127133.eml, sunwing/it-875229533.eml, sunwing/it-878437543.eml";

    public static $dictionary = [
        "en" => [
        ],

        "fr" => [
            'Booking Details'                                               => 'Détails de la',
            'Advice to International Passengers on Limitation of Liability' => 'Conseils aux passagers internationaux concernant les limites de responsabilité',
            'Booking'                                                       => 'Réservation',

            'Flight Itinerary'  => ' Itinéraire de vol',
            'Hotel Information' => ' Information d’Hôtel',

            'Passenger' => 'Passagers',

            //Flight
            'Flight'     => 'Vol',
            'Departing:' => 'Départ:',
            'Arriving:'  => 'En arrivant:',
            'Airline'    => 'Compagnie aérienne',

            //Hotel
            'Address:'             => 'Adresse:',
            'Telephone:'           => 'Téléphone :',
            'Check In:'            => 'Enregistrement:',
            'Check Out:'           => 'Départ:',
            'Room '                => 'Chambre ',
            'Confirmation number:' => ' Numéro de confirmation :',
        ],
    ];

    private $pdfName = '.*';

    private $langDetectors = [
        'en' => ['Flight Itinerary'],
        'fr' => ['Itinéraire de vol'],
    ];

    private $subjects = [
        // en
        'Sunwing Vacations eDocuments -',
        '',
    ];

    private $lang = '';
    private $travellers = [];
    private $seats = [];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfName);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }
            $this->assignLang($text);
            $this->parsePdf($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Sunwing Vacations') === false) {
            return false;
        }

        foreach ($this->subjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfName);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            // Detect Provider
            if (stripos($text, '//www.sunwing.ca') === false) {
                continue;
            }

            // Detect Format
            if ($this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'support@sunwinginfo.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function niceTravellers($names)
    {
        return preg_replace("/^\s*(Mr|Mstr|Mrs|Miss|Ms) +/", '', $names);
    }

    private function parsePdf(Email $email, string $text): void
    {
        $email->obtainTravelAgency();

        //$this->logger->debug($text);

        $tripInfo = $this->cutText($this->t('Booking Details'), $this->t('Advice to International Passengers on Limitation of Liability'), $text);

        if (preg_match("/({$this->opt($this->t('Booking'))}[ ]*#)[ ]*:[ ]*(\w+)/", $tripInfo, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        $segmentsNames = [$this->t('Flight Itinerary'), $this->t('Hotel Information')];
        $passengersText = $this->cutText($this->t('Booking Details'), $segmentsNames, $text);

        if (preg_match("/\n[\s\W]*{$this->opt($this->t('Passenger'))}\(?s?\)?\s*\n *(\d+\..+(\s*\n\s* *\d+\..+\s*)*)\n/", $passengersText, $m)) {
            if (preg_match_all('/(?:^ *|\n *| {3,})\d+\. {0,5}([[:alpha:]]( ?[[:alpha:]\-\.]+)+)\b/', $m[1], $match)) {
                $this->travellers = $match[1];
            }
        } elseif (preg_match("/\n[\s\W]*({$this->opt($this->t('Passenger'))}\(?s?\)? {2,}.+(?:\n {30,}.*)?)\n([\s\S]+?)(?: {0,5}\*.*[\s\S]+)?\n[\s\W]*$/u", $passengersText, $m)) {
            $headerPos = $this->rowColsPos($this->inOneRow($m[1]));

            $airlines = $this->splitCols($m[1], $headerPos);

            foreach ($airlines as $i => $v) {
                if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\b/", $v, $mv)) {
                    $airlines[$i] = $mv['al'] . $mv['fn'];
                } else {
                    $airlines[$i] = null;
                }
            }

            $passengers = $this->splitter("/^( {0,5}\w+.+)/m", "\n\n" . $m[2] . "\n\n", true);

            foreach ($passengers as $p) {
                $table = $this->splitCols($p, $headerPos);

                $this->travellers[] = $table[0];

                for ($i = 1; $i < count($table); $i++) {
                    if (preg_match('/^\s*(\d{1,3}[A-Z])(?:\s*\\/.*)?\s*$/', $table[$i], $m)) {
                        $this->seats[trim($airlines[$i])][] = ['seat' => $m[1], 'traveller' => $this->niceTravellers($table[0])];
                    }
                }
            }
        }

        $this->travellers = $this->niceTravellers($this->travellers);
        $segments = $this->splitter("/\n *[^\w\s]? *({$this->opt($segmentsNames)} *\n)/u", $tripInfo, true);

        foreach ($segments as $sText) {
            if (preg_match("/^\W*{$this->opt($this->t('Flight Itinerary'))}/", $sText)) {
                $this->parseFlight($email, $sText);
            }

            if (preg_match("/^\W*{$this->opt($this->t('Hotel Information'))}/", $sText)) {
                $this->parseHotel($email, $sText);
            }
        }

        return;
    }

    private function parseFlight(Email $email, string $flightTexts): void
    {
        $f = $email->add()->flight();

        if (preg_match('/\n *\W? *PNR:/u', $flightTexts, $m)) {
            if (preg_match_all('/^ *\W? *PNR: {0,3}([A-Z\d]{5,7})$/mu', $flightTexts, $m)) {
                foreach (array_unique($m[1]) as $conf) {
                    $f->general()
                        ->confirmation($conf);
                }
            }
        } else {
            $f->general()
                ->noConfirmation();
        }
        $f->general()
            ->noConfirmation()
            ->travellers($this->travellers, true);

        // Segments
        $segRe = "/\n((?:"
                . " {0,5}{$this->opt($this->t('Flight'))} {0,3}\d+ .*\([A-Z]{3}\).*\([A-Z]{3}\).*"
            . "|"
                . " {10,}.*\([A-Z]{3}\).*\([A-Z]{3}\).*\n+ +\W\n+ {0,5}{$this->opt($this->t('Flight'))} {0,3}\d+"
            . ")\n)/u";
        $segments = $this->splitter($segRe, $flightTexts, true);
        // $this->logger->debug('$segRe = '.print_r( $segRe,true));
        // $this->logger->debug('$segments = '.print_r( $segments,true));

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            if (
                preg_match("/^ *{$this->opt($this->t('Flight'))} *\d+ +(?<dname>.+?) *\((?<dcode>[A-Z]{3})\)(?: - (?<dterm>\S(?: ?\S)*))? {1,}(?<aname>.+?) *\((?<acode>[A-Z]{3})\)(?: - (?<aterm>\S(?: ?\S)*))?(?: +\W)?\n/u", $sText, $m)
                || preg_match("/^ *(?<dname>.+?) *\((?<dcode>[A-Z]{3})\)(?: - (?<dterm>\S(?: ?\S)*))? {1,}(?<aname>.+?) *\((?<acode>[A-Z]{3})\)(?: - (?<aterm>\S(?: ?\S)*))?\n+ *\W*\n+ *{$this->opt($this->t('Flight'))} *\d+\n/", $sText, $m)
            ) {
                $s->departure()
                    ->name($m['dname'])
                    ->code($m['dcode'])
                    ->terminal($m['dterm'] ?? null, true, true)
                ;
                $s->arrival()
                    ->name($m['aname'])
                    ->code($m['acode'])
                    ->terminal($m['aterm'] ?? null, true, true)
                ;
            }

            if (preg_match("/\n\W*{$this->opt($this->t('Departing:'))} *(.+)/", $sText, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m[1]))
                ;
            }

            if (preg_match("/\n\W*{$this->opt($this->t('Arriving:'))} *(.+)/", $sText, $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($m[1]))
                ;
            }

            if (preg_match("/\n *{$this->opt($this->t('Airline'))} +{$this->opt($this->t('Flight'))} #.+\s*\n *(?<alfull>.*?) {2,}(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5}) {2,}(\S.*)? {2,}(?<aircraft>\S.+?) {2,}\d+( ?, ?\d+)*\s*\n/", $sText, $m)
                || preg_match("/\n *{$this->opt($this->t('Airline'))} +{$this->opt($this->t('Flight'))} #.+\s*\n *(?<alfull>.*?) {2,}(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5}) {2,}(\S.*)? {2,}(?<aircraft>\S.+?)? {2,}\d+( ?, ?\d+)*\s*\n/", $sText, $m)
            ) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                if (!empty($m['alfull']) && preg_match("/^ *\W? *{$m['alfull']} *{$this->opt($this->t('Reservation Code:'))} {0,5}([A-Z\d]{5,7})$/miu", $sText, $alm)) {
                    $s->airline()
                        ->confirmation($alm[1]);
                }

                if (!empty($this->seats[$m['al'] . $m['fn']])) {
                    foreach ($this->seats[$m['al'] . $m['fn']] as $value) {
                        $s->extra()
                            ->seat($value['seat'], true, true, $value['traveller']);
                    }
                }

                $s->extra()
                    ->aircraft($m['aircraft'] ?? null, true, true);
            }
        }
    }

    private function parseHotel(Email $email, string $text): void
    {
        $h = $email->add()->hotel();

        if (!preg_match("/{$this->opt($this->t('Confirmation number:'))}/u", $text)) {
            $h->general()
                ->noConfirmation();
        } else {
            $confs = [];

            if (preg_match_all("/{$this->opt($this->t('Confirmation number:'))}\s*([A-Za-z\d\-]{5,})\s*\n/", $text, $m)) {
                $confs = array_merge($confs, $m[1]);
            }

            if (preg_match_all("/{$this->opt($this->t('Confirmation number: '))}\s*\n.{40,} {3,}([A-Za-z\d\-]{5,})\s*\n/", $text, $m)) {
                $confs = array_merge($confs, $m[1]);
            }

            foreach (array_unique($confs) as $conf) {
                $h->general()
                    ->confirmation($conf);
            }
        }
        $h->general()
            ->travellers($this->travellers);

        if (preg_match('/^.+\s*(.+)/', $text, $m)) {
            $h->hotel()
                ->name($m[1])
            ;
        }

        if (preg_match("/\n *{$this->opt($this->t('Address:'))}(.+?(?:\n*.+?)?)[\s\-]+{$this->opt($this->t('Telephone:'))}\s*(.+)/", $text, $m)) {
            $h->hotel()
                ->address(preg_replace("/\s+/", ' ', trim($m[1])))
                ->phone($m[2])
            ;
        }

        if (preg_match("/\n\W{0,15}{$this->opt($this->t('Check In:'))}[ ]*(.+?)(?: {3,}|\n)/", $text, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m[1]));
        }

        if (preg_match("/\n\W{0,15}{$this->opt($this->t('Check Out:'))}[ ]*(.+?)(?: {3,}|\n)/", $text, $m)) {
            $h->booked()
                ->checkOut($this->normalizeDate($m[1]));
        }

        $rooms = $this->splitter("/\n {0,10}{$this->opt($this->t('Room '))}\d+ {2,}(\w.+)/", $text, true);

        foreach ($rooms as $rText) {
            $r = $h->addRoom();
            $r->setType($this->re("/^(.+)/", $rText));

            if (preg_match("/{$this->opt($this->t('Confirmation number:'))}\s*([A-Za-z\d]{5,})\s*\n/", $rText, $m)
                || preg_match("/{$this->opt($this->t('Confirmation number: '))}\s*\n.{40,} {3,}([A-Za-z\d]{5,})\s*\n/", $rText, $m)
            ) {
                $r->setConfirmation($m[1]);
            }
        }
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function cutText(string $start, $end, string $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        if (is_array($end)) {
            $begin = stristr($text, $start);

            foreach ($end as $e) {
                if (stristr($begin, $e, true) !== false) {
                    return stristr($begin, $e, true);
                }
            }

            return '';
        }

        return stristr(stristr($text, $start), $end, true);
    }

    private function splitter($regular, $text, $deleteFirst = false)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($deleteFirst === true) {
            array_shift($array);
        }

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function assignLang($text = ''): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (empty($text) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                } elseif (!empty($text) && strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date in = '.print_r( $date,true));
        $in = [
            // 15Aug2022 06:00:00 AM
            "#^\s*(\d{1,2})\s*([[:alpha:]]+)\s*(\d{4})\s+(\d{1,2}:\d{2}):\d{1,2} *(\s*[ap]m)\s*$#ui",
            // Mon, 15 August 2022 - 3:00 PM
            "#^\s*[[:alpha:]]+,\s*(\d{1,2})\s*([[:alpha:]]+)\s*(\d{4})\s*-\s*(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$#ui",
            // Mon, 03 March 2025 -
            "#^\s*[[:alpha:]]+,\s*(\d{1,2})\s*([[:alpha:]]+)\s*(\d{4})\s*-\s*$#ui",
        ];
        $out = [
            "$1 $2 $3, $4 $5",
            "$1 $2 $3, $4",
            "$1 $2 $3",
        ];
        $date = preg_replace($in, $out, $date);
        //$this->logger->debug('$date out = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
