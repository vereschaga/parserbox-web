<?php

namespace AwardWallet\Engine\tcase\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TripPdf extends \TAccountChecker
{
    public $mailFiles = "tcase/it-35371808.eml, tcase/it-35371970.eml";

    public $reFrom = ["@tripcase.com"];
    public $reBody = [
        'en' => ['Sabre Inc. All rights reserved', 'Confirmation Number:'],
        'es' => ['Sabre Inc. Todos los derechos reservados', 'Número de confirmación:'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'DEPARTS'       => 'DEPARTS',
            'TERMINAL/GATE' => 'TERMINAL/GATE',
            'regDate'       => '[ ]+\w+, \w+ \d+, \d{4}[ ]*\n',
            'regSegments'   => '.+? Flight \d+',
            // hotel
            'CHECK-IN'            => 'CHECK-IN',
            'Confirmation Number' => 'Confirmation Number',
        ],
        'es' => [
            'DEPARTS'          => 'PARTE',
            'TERMINAL/GATE'    => 'TERMINAL/PUERTA',
            'ARRIVES'          => 'ARRIBA',
            'ARRIVES NEXT DAY' => 'ARRIBA AL DÍA SIGUIENTE',
            'Flight Duration'  => 'Duración del vuelo',
            'regDate'          => '\w+, \d+ DE \w+ DE \d{4}\n',
            'regSegments'      => 'Vuelo \d+ de .+',
            'Flight'           => 'Vuelo',
            'to'               => 'a',
            'Not Entered'      => 'No ingresado',
            // hotel
            'CHECK-IN'                       => ['CHECK IN', 'COMIENZO'],
            'CHECK-OUT'                      => ['CHECK OUT', 'FIN'],
            'LOCAL TIME'                     => 'HORA LOCAL',
            'Confirmation Number'            => 'Número de confirmación',
            'Sabre Inc. All rights reserved' => 'Sabre Inc. Todos los derechos reservados',
        ],
    ];
    private $keywordProv = 'TripCase';

    private $year = 0;

    private $hotelConfs = [];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->year = date('Y', strtotime($parser->getDate()));
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectBody($text)) {
                        $this->assignLang($text);

                        if (!$this->assignLang($text)) {
                            $this->logger->debug('can\'t determine a language');

                            continue;
                        }
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
                && $this->detectBody($text)
                && $this->assignLang($text)
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
        $types = 2; // flight | hotel
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        //delete garbage strings
        //3/24/2019                                                                      TripCase
        $textPDF = preg_replace("/^[ ]*\d+\/\d+\/\d+\s+TripCase[ ]*/m", '', $textPDF);
        //https://www.tripcase.com/web2/trips/326786157/itinerary/print                                                     1/1
        $textPDF = preg_replace("/^[ ]*https:\/\/www.tripcase.com\S+[ ]+\d+\/\d+/m", '', $textPDF);
        //                    © 2008–2019 Sabre Inc. All rights reserved. TripCase is a servicemark of Sabre Inc.
        $textPDF = preg_replace("/^[^\n]+{$this->opt($this->t('Sabre Inc. All rights reserved'))}[^\n]+/mu", '',
            $textPDF);

        $itineraryParts = [];
        $sections = $this->splitter("/^({$this->t('regDate')})/miu", "ControlStr\n" . $textPDF);

        foreach ($sections as $section) {
            // get date row
            $dateStr = $this->re("/(.+)/", $section);
            // insert date row
            $newRoot = preg_replace("/(.+)\s+(^ *(?:DELTA VACATIONS|{$this->t('regSegments')})[ ]*)/mu",
                '$1' . "\n{$dateStr}\n" . '$2', $section);
            // remove double date
            $newRoot = preg_replace("/({$dateStr})\n+{$dateStr}/", '$1', $newRoot);

            $itineraryParts = array_merge($itineraryParts,
                $this->splitter("/^({$this->t('regDate')})/mu", "ControlStr\n" . $newRoot));
        }
        $hotels = [];

        foreach ($itineraryParts as $itinerary) {
            if (strpos($itinerary, 'DELTA VACATIONS') !== false) {
                $this->logger->debug('skip DELTA VACATIONS');

                continue;
            }

            if (preg_match("/{$this->opt($this->t('CHECK-IN'))}/", $itinerary) > 0) {
                $this->logger->debug('try parse hotel');
                $roots = $this->splitter("/(.+\n.+\n{2}[ ]+{$this->opt($this->t('CHECK-IN'))}[ ]+{$this->t('LOCAL TIME')}[\s\S]+?{$this->opt($this->t('CHECK-OUT'))}[ ]+{$this->t('LOCAL TIME')}[\s\S]+?{$this->t('Confirmation Number')}[ ]*\:[ ]*(?:\d+|{$this->t('Not Entered')}))/u",
                    $itinerary);

                foreach ($roots as $root) {
                    $root = preg_replace(['/\d{1,2}\/\d{1,2}\/\d{4}/', '/TripCase/'], ['', ''], $root);
                    $hotels[] = $this->parseHotel($root);
                }
            }

            if (strpos($itinerary, $this->t('Flight')) !== false) {
                $this->logger->debug('try parse flight');

                if ($this->parseFlight($itinerary, $email) === false) {
                    return false;
                }
            }
        }
        $resHotels = $this->compareHotels($hotels);

        foreach ($resHotels as $i => $hotel) {
            $h = $email->add()->hotel();

            if (!empty($this->hotelConfs)) {
                $this->hotelConfs = array_filter(array_unique($this->hotelConfs));

                foreach ($this->hotelConfs as $hotelConf) {
                    if ($hotelConf !== CONFNO_UNKNOWN) {
                        $h->general()->confirmation($hotelConf);
                    } else {
                        $confNoUnknown = true;
                    }
                }

                if (count($h->getConfirmationNumbers()) === 0 && isset($confNoUnknown)) {
                    $h->general()->noConfirmation();
                }
            }

            if (6 !== count($hotel)) {
                continue;
            }

            if (preg_match("/^\s*{$hotel['name']}\s+(.+)/ui", $hotel['address'], $m)) {
                $hotel['address'] = $m[1];
            }
            $h->hotel()
                ->name($hotel['name'])
                ->address($hotel['address']);

            if (preg_match("/^[\d\+\- \(\)]+$/", trim($hotel['phone']))) {
                $h->hotel()->phone($hotel['phone']);
            }

            if (preg_match("/^[\d\+\- \(\)]+$/", trim($hotel['fax']))) {
                $h->hotel()->fax($hotel['fax']);
            }
            $h->booked()
                ->checkIn($hotel['checkIn'])
                ->checkOut($hotel['checkOut']);
        }

        return true;
    }

    private function compareHotels(array $hotels = []): array
    {
        if (1 === count($hotels) || 0 === count($hotels)) {
            return $hotels;
        }

        foreach ($hotels as $i => $hotel) {
            if (!empty($hotel['confirmation'])) {
                $this->hotelConfs[$i] = $hotel['confirmation'];
                unset($hotels[$i]['confirmation']);
            }
        }
        $res = array_map("unserialize", array_unique(array_map("serialize", $hotels)));

        return $res;
    }

    private function normalizeDate($date)
    {
        $in = [
            //SATURDAY, MARCH 30, 2019
            '/^\s*\w+,\s+(\w+)\s+(\d+),\s+(\d{4})$/u',
            //VIERNES, 15 DE NOVIEMBRE DE 2019
            '/^\s*[\-\w]+,\s+(\d+)\s+de\s+(\w+)\s+de\s+(\d{4})$/ui',
            //VIERNES, 15 NOVIEMBRE, 12:00
            '/^\s*([\-\w]+),\s+(\d+)\s+(\w+),\s+(\d+:\d+(?:\s*[ap]m)?)$/ui',
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
            '$2 $3 ' . $this->year . ', ' . '$4',
        ];
        $outWeek = [
            '',
            '',
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseHotel(string $textPDF): array
    {
        $h = [];

        if (preg_match("/(.+)\n.+\s+{$this->opt($this->t('CHECK-IN'))}/u", $textPDF, $m)) {
            $h['name'] = trim($m[1]);
        }

        $reEn = '/(?<week>\w+), (?<month>\w+) (?<day>\d{1,2})[ ]+(?<time>\d{1,2}:\d{2} [AP]M)\s+(?<phone>[\d\-]+)/';
        $reEs = '/(?<week>[\-\w]+)\., (?<day>\d{1,2}) (?<month>\w+)\. [ ]+(?<time>\d{1,2}:\d{2}(?: [AP]M)?)\s+(?<phone>[\d\-]+|.+|)/ui';

        if (preg_match($reEn, $textPDF, $m) || preg_match($reEs, $textPDF, $m)) {
            $h['phone'] = $m['phone'];

            if (!empty($this->year)) {
                $h['checkIn'] = $this->normalizeDate($m['week'] . ', ' . $m['day'] . ' ' . $m['month'] . ', ' . $m['time']);
            }
        }
        $reEn2 = "/{$this->t('LOCAL TIME')}[\s\S]+?{$this->t('LOCAL TIME')}\s+(?<fax>[\d\-]*)\s*(?<week>[\-\w]+), (?<month>\w+) (?<day>\d{1,2})[ ]+(?<time>\d{1,2}:\d{2} [AP]M)\s+(?<address>[\s\S]+?){$this->t('Confirmation Number')}[ ]*\:[ ]*(?<number>(?:\d+|{$this->t('Not Entered')}))/";
        $reEs2 = "/{$this->t('LOCAL TIME')}[\s\S]+?{$this->t('LOCAL TIME')}\s+(?<fax>[\d\-]*)\s*(?<week>[\-\w]+)\., (?<day>\d{1,2}) (?<month>\w+)\.[ ]+(?<time>\d{1,2}:\d{2}(?: [AP]M)?)\s+(?<address>[\s\S]+?){$this->t('Confirmation Number')}[ ]*\:[ ]*(?<number>(?:\d+|{$this->t('Not Entered')}))/iu";

        if (preg_match($reEn2, $textPDF, $m) || preg_match($reEs2, $textPDF, $m)) {
            if ($m['number'] !== $this->t('Not Entered')) {
                $h['confirmation'] = $m['number'];
            } else {
                $h['confirmation'] = CONFNO_UNKNOWN;
            }
            $h['fax'] = $m['fax'];

            if (!empty($this->year)) {
                $h['checkOut'] = $this->normalizeDate($m['week'] . ', ' . $m['day'] . ' ' . $m['month'] . ', ' . $m['time']);
            }
            $address = preg_replace('/\s+/', ' ', $m['address']);
            $h['address'] = trim($address);
        }

        return $h;
    }

    private function parseFlight($textPDF, Email $email)
    {
        $r = $email->add()->flight();

        $date = $this->normalizeDate($this->re("/(.+)/", $textPDF));

        $conf = $this->re("/{$this->opt($this->t('Confirmation Number'))}[:\s]+([A-Z\d]{5,6})/u", $textPDF);

        if (empty($conf) && preg_match("/{$this->opt($this->t('Confirmation Number'))}[:\s]+({$this->t('Not Entered')})/u",
                $textPDF) > 0
        ) {
            $r->general()->noConfirmation();
        } else {
            $r->general()->confirmation($conf);
        }

        $s = $r->addSegment();

        if (preg_match("/[^\n]+\n\s*{$this->t('Flight')}\s+(?<number>\d+)\s+de (?<airline>[^\n]+?)\n\s*(?<points>.+?)\n(?<data>\s*{$this->t('DEPARTS')}.+?)\n\s*{$this->t('Confirmation Number')}/siu",
                $textPDF, $m)
            || preg_match("/[^\n]+\n\s*(?<airline>[^\n]+?)\s+{$this->t('Flight')}\s+(?<number>\d+)\s+(?<points>.+?)\n(?<data>\s*{$this->t('DEPARTS')}.+?)\n\s*{$this->t('Confirmation Number')}/s",
                $textPDF, $m)
        ) {
            $s->airline()
                ->name($m['airline'])
                ->number($m['number']);

            if (preg_match("/(.+)\s+\(([A-Z]{3})\)\s+{$this->t('to')}\s+(.+)\s+\(([A-Z]{3})\)/su", $m['points'], $v)) {
                $s->departure()
                    ->name($this->nice($v[1]))
                    ->code($v[2]);
                $s->arrival()
                    ->name($this->nice($v[3]))
                    ->code($v[4]);
            }
            /* FE: es
                     PARTE                            TERMINAL/PUERTA           United States
                     13:25 (-03)                      A conf                    888-238-7672
                                                                                Argentina
                     ARRIBA AL DÍA SIGUIENTE          TERMINAL/PUERTA
                                                                                0810 222 4546
                     5:10 (CET)                       A conf                    Spain
            */
            $table = $this->splitCols($m['data'], $this->colsPos($m['data']));

            if (count($table) !== 3) {
                $this->logger->debug('other format');

                return false;
            }

            $timeDep = $this->re("/{$this->t('DEPARTS')}\s+(\d+:\d+(?:\s*[AaPp][Mm])?)/", $table[0]);
            $s->departure()
                ->date(strtotime($timeDep, $date));

            if (strpos($table[0], $this->t('ARRIVES NEXT DAY')) !== false) {
                $date = strtotime("+1 day", $date);
            }

            $timeArr = $this->re("/{$this->t('ARRIVES')}[^\n]*\s+(\d+:\d+(?:\s*[AaPp][Mm])?)/", $table[0]);
            $s->arrival()
                ->date(strtotime($timeArr, $date));
        }
        $s->extra()->duration($this->re("/{$this->t('Flight Duration')}[:\s]+(.+?)\s{2,}/", $textPDF));

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["DEPARTS"], $words["TERMINAL/GATE"])) {
                if (
                    (false !== stripos($body, $words["DEPARTS"]) && false !== stripos($body,
                            $words["TERMINAL/GATE"]))
                    || (false !== stripos($body, $words['Confirmation Number']) && false !== stripos($body,
                            $words['CHECK-IN']))
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function nice($str)
    {
        return trim(preg_replace("/\s+/", ' ', $str));
    }
}
