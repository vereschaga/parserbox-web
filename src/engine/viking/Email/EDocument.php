<?php

namespace AwardWallet\Engine\viking\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Cruise;
use AwardWallet\Schema\Parser\Email\Email;

class EDocument extends \TAccountChecker
{
    public $mailFiles = "viking/it-42076530.eml, viking/it-42128219.eml, viking/it-42168375.eml, viking/it-42292895.eml, viking/it-42351926.eml, viking/it-42862402.eml, viking/it-454008581.eml";

    public $reFrom = ["@vikingcruises.com", "@cruisemd.com"];

    public $reSubject = [
        'Important – Viking Cruises Final E-Document:', 'Important – Viking Final E-Document:',
    ];
    public $lang = '';
    public $pdfNamePattern = "EDOC.*pdf";
    public static $dict = [
        'en' => [
            'Guest:'                                => 'Guest:',
            'Booking Number:'                       => 'Booking Number:',
            'Your Post-Cruise Hotel Accommodations' => [
                'Your Post-Cruise Hotel Accommodations',
                'Your Pre-Cruise Hotel Accommodations',
            ],
            'night' => ['night', 'nights'],
        ],
    ];
    private $keywordProv = 'Viking Cruise';
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $i => $pdf) {
                if (!empty($text = \PDF::convertToText($parser->getAttachmentBody($pdf)))
                    && $this->assignLang($text)
                ) {
                    $this->logger->debug('parsing attachment-' . $i);
                    $this->date = strtotime($parser->getDate());
                    $this->parseEmailPdf($text, $email);
                }
            }
        } else {
            return $email;
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

            if (stripos($text, $this->keywordProv) !== false && $this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposArray($from, $this->reFrom);
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || $this->detectEmailFromProvider($headers['from']) !== true) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || $this->striposArray($headers["subject"], $this->keywordProv))
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

    private function parseEmailPdf($textPDF, Email $email): void
    {
        $parts = $this->splitter("#^[ ]*(YOUR JOURNEY SUMMARY|YOUR CRUISE ITINERARY|YOUR FLIGHTS|YOUR HOTELS|YOUR HOTELS & TRANSFERS|YOUR PRE-SELECTED TOURS|SHORE EXCURSIONS|YOUR PRE-SELECTED EXCURSIONS)\n#m",
            "Ctrlstr\n" . $textPDF);
        $cruiseText = '';

        foreach ($parts as $part) {
            if (preg_match('#^[ ]*YOUR JOURNEY SUMMARY#', $part) > 0
                || preg_match('#^[ ]*YOUR CRUISE ITINERARY#', $part) > 0
            ) {
                $cruiseText .= $part;
            } elseif (strpos($part, 'SHORE EXCURSIONS') !== false) {
                $this->logger->debug('SHORE EXCURSIONS - skip - not reservation yet');

                continue;
            } elseif (strpos($part, 'YOUR HOTELS & TRANSFERS') !== false) {
                $this->logger->debug('YOUR HOTELS & TRANSFERS - skip - not enough info');

                continue;
            } elseif (strpos($part, 'YOUR FLIGHTS') !== false) {
                if (strpos($part, 'Departs') !== false) {
                    $flights[] = $part;
                } else {
                    $this->logger->debug('skip flight add-info');

                    continue;
                }
            } elseif (strpos($part, 'YOUR HOTELS') !== false) {
                $hotels[] = $part;
            } elseif (strpos($part, 'YOUR PRE-SELECTED TOURS') !== false) {
                $tours[] = $part;
            } elseif (strpos($part, 'YOUR PRE-SELECTED EXCURSIONS') !== false) {
                $tours[] = $part;
            }
        }

        if (empty($cruiseText)) {
            $this->logger->debug('not found cruise. other format');

            return;
        }
        $date = strtotime($this->re("#Embarkation Date:[ ]+(.+?)[ ]{2,}#", $cruiseText));

        if (!empty($date)) {
            $this->date = $date;
        }
        $this->parseCruise($cruiseText, $email);

        if (isset($flights)) {
            $this->parseFlights($flights, $email);
        }

        if (isset($hotels)) {
            $this->parseHotels($hotels, $email);
        }

        if (isset($tours)) {
            $this->parseTours($tours, $email);
        }
    }

    private function parseCruise($text, Email $email): void
    {
        $r = $email->add()->cruise();

        $r->general()
            ->confirmation($this->re("#Booking Number:[ ]+(\d+)#", $text))
            ->traveller($this->re("#{$this->t('Guest:')}[ ]+(.+?)[ ]{3,}#", $text), true);

        $guestID = $this->re("#\n[ ]*{$this->opt($this->t('Guest ID:'))}[ ]{1,50}([A-Z\d]{7,20})(?:[ ]{2}|\n)#", $text);

        if ($guestID !== null && preg_match('/\d/', $guestID)) {
            $r->program()->account($guestID, false);
        }

        $r->details()
            ->ship($this->re("#\n[ ]*{$this->opt($this->t('Ship:'))}[ ]+(\S.+?\S)[ ]{3}#", $text))
            ->room($this->re("#(?:\n[ ]*|[ ]{2}){$this->opt($this->t('Stateroom:'))}[ ]+(.+)#", $text))
            ->roomClass($this->re("#(?:\n[ ]*|[ ]{2}){$this->opt($this->t('Category:'))}[ ]+(.+)#", $text))
            ->deck($this->re("#(?:\n[ ]*|[ ]{2}){$this->opt($this->t('Deck:'))}[ ]+(.+)#", $text), false, true);

        $table = $this->re("#\nDAY[ ]+DATE[ ]+PORT\n(.+?)\n\n#s", $text);

        if (!empty($table)) {
            $this->parseCruiseSegments_1($r, $table);
        } else {
            $table = $this->re("#\n(DAY[ ]+DATE[ ]+PORT[ ]+ARRIVE[ ]+DEPART\n.+?)\n\n#s", $text);
            $this->parseCruiseSegments_2($r, $table);
        }
    }

    private function parseCruiseSegments_1(Cruise $r, $table): void
    {
        $segments = $this->splitter("#\n(\w+[ ]+.+?[ ]{2,})#", "CtrlStr\n" . $table);
        $segmentCount = count($segments);

        foreach ($segments as $i => $segment) {
            if (preg_match("#[ ]{2,}(Embark in|At Sea|Sail the \S|Disembark in|Check in)#", $segment)
            ) {
                continue;
            }

            $s = $r->addSegment();

            if (preg_match("#(.+? \d{4})[ ]{2,}(.+)#s", $segment, $m)) {
                $date = strtotime($m[1]);
                $port = $this->nice($m[2]);

                if (count($r->getSegments()) === 1) {
                    $s->setAboard($date)
                        ->setName($port);
                } elseif ($i === $segmentCount - 1) {
                    $s->setAshore($date)
                        ->setName($port);
                } elseif ($i === $segmentCount - 2 && preg_match("#[ ]{2,}(Embark in|At Sea|Sail the \S|Disembark in|Check in)#",
                        $segments[$i + 1])
                ) {
                    $s->setAshore($date)
                        ->setName($port);
                } else {
                    $s->setAboard($date)
                        ->setAshore($date)
                        ->setName($port);
                }
            }
        }
    }

    private function parseCruiseSegments_2(Cruise $r, $table): void
    {
        $pos = $this->colsPos($table);

        if (count($pos) !== 5) {
            $this->logger->debug('other format parseCruiseSegments_2');

            return;
        }
        $segments = $this->splitter("#\n(\w+[ ]+.+?[ ]{2,})#", "CtrlStr\n" . $table);
        array_shift($segments); //del header;

        foreach ($segments as $segment) {
            $cols = $this->splitCols($segment, $pos);

            if (preg_match("#^(Embark in|At Sea|Sail the \S|Disembark in|Check in|Scenic Sailing: |Welland Canal|CROSSING )#",
                    $cols[2]) && empty($cols[3]) && empty($cols[4])
            ) {
                continue;
            }
            $s = $r->addSegment();

            $date = strtotime($cols[1]);
            $port = $this->nice($cols[2]);

            if (empty($cols[3])) {
                $s->setAboard(strtotime($cols[4], $date))
                    ->setName($port);
            } elseif (empty($cols[4])) {
                $s->setAshore(strtotime($cols[3], $date))
                    ->setName($port);
            } else {
                $s->setAboard(strtotime($cols[4], $date))
                    ->setAshore(strtotime($cols[3], $date))
                    ->setName($port);
            }
        }
    }

    private function parseFlights(array $flights, Email $email): void
    {
        $reservations = [];

        foreach ($flights as $flight) {
            $confNo = $this->re("#{$this->opt($this->t('Viking Air #:'))}[ ]+([A-Z\d]{5,6})\b#", $flight);
            $reservations[$confNo][] = $flight;
        }

        foreach ($reservations as $confNo => $reservation) {
            $text = implode("\n", $reservation);

            $r = $email->add()->flight();

            $r->ota()
                ->confirmation($this->re("#Booking Number:[ ]+(\d+)#", $text), 'Booking Number');
            $r->general()
                ->confirmation($confNo, trim($this->t('Viking Air #:'), ': '))
                ->traveller($this->re("#{$this->t('Guest:')}[ ]+(.+?)[ ]{3,}#", $text), true);

            if (preg_match_all("#{$this->opt($this->t('Airline Ticket #:'))}[ ]+(\d{10,})\b#", $text, $ticketMatches)) {
                $r->issued()->tickets(array_unique($ticketMatches[1]), false);
            }

            if (preg_match_all("#Flight[ ]+Departs[ ]+Arrives\n(.+?)(?:\n\n|\nFlight \d+\n)#s", $text, $segmentMatches)) {
                foreach ($segmentMatches[1] as $root) {
                    $s = $r->addSegment();
                    $table = $this->splitCols($root, $this->colsPos($root));

                    if (count($table) !== 3) {
                        $this->logger->debug('other format flight');

                        return;
                    }

                    if (preg_match("#^(.+)\s+Flight\s+(\d+)\s*(?:Operated by\s+(.+))?$#s", $table[0], $m)) {
                        $s->airline()
                            ->name($this->nice($m[1]))
                            ->number($m[2]);

                        if (isset($m[3]) && !empty($m[3])) {
                            $s->airline()->operator($this->re("#^(.+?)\s*(?:\bDBA\b|$)#", $this->nice($m[3])));
                        }
                    }

                    if (preg_match("#(.+)\s+\(([A-Z]{3})\)\s+(.+)\s+Travel Time:\s+(.+)\s+Seat:\s+(\d+[A-z]|[^\n]+)\s+{$this->opt($this->t('Airline Booking #:'))}\s+([A-Z\d]{5,6})$#s",
                        $table[1], $m)) {
                        $s->departure()
                            ->name($this->nice($m[1]))
                            ->code($m[2])
                            ->date(strtotime($m[3]));
                        $s->extra()
                            ->duration($m[4]);

                        if (preg_match("#^\d+[A-z]$#", $m[5])) {
                            $s->extra()->seat($m[5]);
                        }
                        $s->airline()
                            ->confirmation($m[6]);
                    }

                    if (preg_match("#^\s*(.{2,}?)\s*\(\s*([A-Z]{3})\s*\)\s+(.*\b\d{4}\b.*?)\s+Equipment ?:#s", $table[2], $m)) {
                        $s->arrival()
                            ->name($this->nice($m[1]))
                            ->code($m[2])
                            ->date(strtotime($m[3]));
                    }

                    if (preg_match("#\n[ ]*Equipment ?[:]+\s*(\S[^:]*?)\s*$#", $table[2], $m)) {
                        $s->extra()->aircraft($this->nice($m[1]));
                    }
                }
            }
        }
    }

    private function parseHotels(array $hotels, Email $email): void
    {
        foreach ($hotels as $hotel) {
            //it-454008581.eml
            if (preg_match_all("/^(.+\n+[[:alpha:]]+\s+\d+ ?,\s*\d{4} ?,\s*\d{1,3}\s*{$this->opt($this->t('night'))}\n+.+\n+[+\d\s\(\)\-]+)/mu", $hotel, $hMatches)) {
                foreach ($hMatches[1] as $segHotel) {
                    if (preg_match("/^(?<name>.+)\n+(?<inDate>[[:alpha:]]+\s+\d+ ?,\s*\d{4}) ?,\s*(?<night>\d{1,3})\s*{$this->opt($this->t('night'))}\n+(?<address>.+)\n+(?<phone>[+\d\s\(\)\-]+)/u", $segHotel, $match)) {
                        $r = $email->add()->hotel();

                        $r->ota()
                            ->confirmation($this->re("#Booking Number:[ ]+(\d+)#", $hotel), 'Booking Number');
                        $r->general()
                            ->noConfirmation()
                            ->traveller($this->re("#{$this->t('Guest:')}[ ]+(.+?)[ ]{3,}#", $hotel), true);
                        $r->hotel()
                            ->name($match['name'])
                            ->address($this->nice($match['address']));

                        if (!empty($match['phone']) && !preg_match("/^\s+$/s", $match['phone'])) {
                            $r->hotel()
                                ->phone($match['phone']);
                        }

                        $r->booked()
                            ->checkIn(strtotime($match['inDate']));

                        if ($r->getCheckInDate()) {
                            $r->booked()
                                ->checkOut(strtotime($match['night'] . ' days', $r->getCheckInDate()));
                        }
                    }
                }
            } elseif (preg_match("#{$this->opt($this->t('Your Post-Cruise Hotel Accommodations'))}\s+(?<name>[^\n]+)\n+(?<date>.+?),? (?<nights>\d+) {$this->opt($this->t('night'))}\s+(?<addr>.+?)\n(?<tel>[\d\+\- \(\)]+)\n#s",
                $hotel, $m)) {
                $r = $email->add()->hotel();
                $r->ota()
                    ->confirmation($this->re("#Booking Number:[ ]+(\d+)#", $hotel), 'Booking Number');
                $r->general()
                    ->noConfirmation()
                    ->traveller($this->re("#{$this->t('Guest:')}[ ]+(.+?)[ ]{3,}#", $hotel), true);
                $r->hotel()
                    ->name($m['name'])
                    ->address($this->nice($m['addr']))
                    ->phone($m['tel']);
                $r->booked()
                    ->checkIn(strtotime($m['date']));

                if ($r->getCheckInDate()) {
                    $r->booked()
                        ->checkOut(strtotime($m['nights'] . ' days', $r->getCheckInDate()));
                }
            }
        }
    }

    private function parseTours(array $tours, Email $email): void
    {
        foreach ($tours as $tour) {
            $table = $this->re("#\nDate[ ]+Day[ ]+Excursion[ ]+Duration[ ]+Time[ ]+Status\n(.+?)\n\n#s", $tour);
            $segments = $this->splitter("#\n(\w+[ ]+.+?[ ]{2,})#", "CtrlStr\n" . $table);

            foreach ($segments as $segment) {
                $r = $email->add()->event();
                $table = $this->splitCols($segment, $this->colsPos($segment));

                if (count($table) !== 6) {
                    $this->logger->debug("other format tour");

                    return;
                }
                $date = $this->normalizeDate(trim($table[1]) . ', ' . trim($table[0]));
                $r->setEventType(EVENT_EVENT);
                $r->ota()
                    ->confirmation($this->re("#Booking Number:[ ]+(\d+)#", $tour), 'Booking Number');
                $r->general()
                    ->noConfirmation()
                    ->status(trim($table[5]))
                    ->traveller($this->re("#{$this->t('Guest:')}[ ]+(.+?)[ ]{3,}#", $tour), true);

                $r->place()
                    ->name($this->nice($table[2]))
                    ->address($this->nice($table[2]));
                $r->booked()
                    ->start(strtotime(trim($table[4]), $date));

                if ($r->getStartDate()) {
                    $duration = (int) ($this->re("#^(\d+(?:\.\d+)?) Hours$#", trim($table[3])) * 60);
                    $r->booked()
                        ->end(strtotime('+' . $duration . ' minutes', $r->getStartDate()));
                }
            }
        }
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Mon, Jul 29
            '#^(\w+),\s+(\w+)\s+(\d+)$#u',
        ];
        $out = [
            '$3 $2 ' . $year,
        ];
        $outWeek = [
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

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Guest:'], $words['Booking Number:'])) {
                if (stripos($body, $words['Guest:']) !== false && stripos($body, $words['Booking Number:']) !== false) {
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '#'));
        }, $field)) . ')';
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

    private function striposArray($haystack, $arrayNeedle): bool
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
