<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "mta/it-16100089.eml, mta/it-164069646.eml, mta/it-16938561.eml, mta/it-27211209.eml, mta/it-27527828.eml, mta/it-679358756-train.eml";

    public $reFrom = ["mtatravel.com.au"];
    public $reBody = [
        'en' => ['THIS E-TICKET ITINERARY/RECEIPT', 'Issuing Agency'],
    ];
    public $lang = '';
    public $reSubject = [
        'en' => '#AdviceNo [A-Z\d]{5,}: Reloc [A-Z\d]{5,}#',
    ];
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'Reservation Code' => ['Reservation Code', 'Reservation Number'],
        ],
    ];
    private $date;
    private $flightArray = [];
    private $knownProviders = [
        'MTA TRAVEL'         => 'mta', // For: MTA TRAVEL (07 55933322)
        'SAVENIO AFFILIATES' => 'savenio',
        //'JOURNEYS BY DESIGN' => '?????'
    ];
    private $flightsTexts = [];

    /**
     * @return array|Email|null
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            $text = '';

            foreach ($pdfs as $key => $pdf) {
                $text = $text . "\n" . \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (empty($text)) {
                    continue;
                }

                if (!$this->assignLang($text)) {
                    $this->logger->debug($key . "-PDF: can't determine a language!");

                    continue;
                }
            }
            $this->parseEmail($text, $email);
        } else {
            return $email;
        }

        if (count($this->flightsTexts) === 1) {
            $flightText = array_shift($this->flightsTexts);

            if (!empty($flightText)) {
                $this->parsePrice($email, $flightText);
            }
        } else {
            // it-679358756-train.eml
            $its = $email->getItineraries();

            foreach ($its as $flightIt) {
                /** @var Flight $flightIt */
                if ($flightIt->getType() === 'flight') {
                    $flightText = array_shift($this->flightsTexts);

                    if (strpos($flightText, '-=PLUS_TRAIN=-') === false && !empty($flightText)) {
                        $this->parsePrice($flightIt, $flightText);
                    }
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (
                (stripos($text, 'MTA TRAVEL') !== false
                    || stripos($text, 'SAVENIO AFFILIATES') !== false
                    || stripos($text, 'the Warsaw Convention may be applicable') !== false)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (preg_match($reSubject, $headers["subject"])) {
                        return true;
                    }
                }
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

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function findСutSection($input, ?string $searchStart, ?string $searchFinish): ?string
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return null;
                }
                $i++;
            }
        } else {
            if (!empty($searchFinish)) {
                $inputResult = mb_strstr($left, $searchFinish, true);
            } else {
                $inputResult = $left;
            }
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function splitter($regular, $text): array
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    /**
     * @param $textPDF
     *
     * @return bool
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail($textPDF, Email $email): void
    {
        $itBlock = $this->findСutSection($textPDF, 'DEPART', 'THIS E-TICKET ITINERARY/RECEIPT');

        if (empty($itBlock)) {
            $this->logger->debug('other format itBlock!');

            return;
        }

        $agency = $this->re("/{$this->opt($this->t('Issuing Agency'))}[ ]*:.+?[ ]{2}{$this->opt($this->t('For'))}[ ]*:[ ]*([^\n]+).+?{$this->opt($this->t('Reservation Number'))}[ ]*:/s",
            $textPDF);
        $finded = false;

        foreach ($this->knownProviders as $name => $prov) {
            if ($agency == $name || stripos($agency, $name) === 0) {
                $ta = $email->ota();
                $ta->code($prov);
                $finded = true;

                break;
            }
        }

        if ($finded == false) {
            $this->logger->debug('Unknown provider!');
        }

        $textPDF = preg_replace("/\n +Page *\d+ *of *\d+ *\n/", "\n", $textPDF);

        $f = $email->add()->flight();

        $pages = $this->splitter("#(?:^|\n)(.+ {2,}Ticket no.+?)#", "\n\n" . $textPDF); // it-27211209.eml

        foreach ($pages as $page) {
            $dateRes = strtotime($this->re("#{$this->opt($this->t('Date of Issue'))}[ :]+(.+)#", $page));

            if ($dateRes) {
                $this->date = $dateRes;
            }
            $traveller = preg_replace("/\s*(MR|MS|MRS|MISS|MSTR)\s*$/", '', $this->re("#^(.+)\s+{$this->opt($this->t('Ticket no.'))}#", $page));
            $confs[] = $this->re("#{$this->opt($this->t('Reservation Code'))}[ :]+([A-Z\d]{5,})#", $page);
            $f->general()
                ->traveller($traveller)
                /*->confirmation()*/
                ->date($dateRes);
            $f->issued()
                ->ticket($this->re("#{$this->opt($this->t('Ticket no.'))} +([\d\-]{5,})#", $page), false, $traveller);

            $accNums = array_filter(array_unique(array_map("trim", explode(',',
                $this->re("#{$this->opt($this->t('Frequent Flyer Membership'))}.+\n(.+?)\s{5,}#", $page)))));

            if (!empty($accNums)) {
                $accNums = array_map(function ($s) {
                    return trim(str_replace(' ', '-', $s));
                }, $accNums);

                foreach ($accNums as $accNum) {
                    $f->program()
                        ->account($accNum, false, $traveller);
                }
            }

            $recLocs = [];
            $rlBlock = $this->re("#[^\n]+\n(.+)#s",
                $this->findСutSection($page, 'Airline Booking Reference', 'Endorsements'));

            if (preg_match_all("/^(.+?)[ :]+([A-Z\d]{5,11})(?:[ ]{2}|$)/m", $rlBlock, $relocMatches, PREG_SET_ORDER)) {
                foreach ($relocMatches as $v) {
                    $recLocs[trim($v[1])] = $v[2];
                }
            }

            $itBlock = $this->findСutSection($page, 'DEPART', 'THIS E-TICKET ITINERARY/RECEIPT');

            if (empty($itBlock)) {
                $this->logger->debug('other format itBlock!');

                return;
            }

            $segs = $this->splitter("/^(.+?\s\/\s[A-Z]{3})\b/m", $itBlock);

            foreach ($segs as $i => $seg) {
                $table = $this->splitCols($seg, $this->colsPos($seg, 10));

                if (count($table) !== 4) {
                    $this->logger->debug("other format {$i}-segment!");
                    $f->addSegment(); // for 100% fail

                    return;
                }

                $duration = $this->re("/{$this->opt($this->t('Duration'))}[ :]+(\d.+)/", $table[3]);
                $aircraft = $meal = null;
                $rows = array_values(array_filter(preg_split("/[ ]*\n[ ]*/", $table[3])));

                switch (count($rows)) {
                    case 3:
                        $aircraft = $rows[2];

                        break;

                    case 4:
                        $meal = $rows[2];
                        $aircraft = $rows[3];

                        break;
                }

                if (preg_match("/^Train$/i", $aircraft)) {
                    if (!isset($trainIt)) {
                        $trainIt = $email->add()->train();
                        $trainIt = $trainIt->fromArray(array_diff_key($f->toArray(), ['segments' => '', '']));
                    }

                    $segType = 'TRAIN';
                    $s = $trainIt->addSegment();
                    $page .= "\n-=PLUS_TRAIN=-";
                } else {
                    $segType = 'FLIGHT';
                    $s = $f->addSegment();
                }
                $this->logger->debug('Segment type: ' . $segType);

                $depDate = $this->re("#^([\-\w]+,\s+\d+\s+\w+(?:\s+\d{4})?\s+\d+:\d+(?:\s*[ap]m)?)$#ium",
                    trim($table[0]));

                if (!empty($depDate)) {
                    $s->departure()
                        ->date($this->normalizeDate($depDate));
                } else {
                    $s->departure()->noDate();
                }
                $s->departure()->code($this->re("/\/\s*([A-Z]{3})\b/", $table[0]));
                $terminalDep = $this->re("/Terminal\s+(.+)/", $table[0]);

                $arrDate = $this->re("#^([\-\w]+,\s+\d+\s+\w+(?:\s+\d{4})?\s+\d+:\d+(?:\s*[ap]m)?)$#ium",
                    trim($table[2]));

                if (!empty($arrDate)) {
                    $s->arrival()
                        ->date($this->normalizeDate($arrDate));
                } else {
                    $s->arrival()->noDate(); //it-27211209
                }

                $s->arrival()->code($this->re("/\/\s*([A-Z]{3})\b/", $table[2]));
                $terminalArr = $this->re("/Terminal\s+(.+)/", $table[2]);

                $s->extra()->duration($duration, false, true)->meal($meal, false, true);

                $airlineConfirmation = $airline = $flightNumber = $operator = null;

                if (preg_match("#^\s*(.+?\s+)?([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,5}) *\n *(.+?)\n([^\n]+?)\s*(?:\(|$)#s", trim($table[1]), $m)) {
                    if (!empty(trim($m[1])) && isset($recLocs[trim($m[1])])) {
                        $airlineConfirmation = $recLocs[trim($m[1])];
                    }

                    $airline = $m[2];
                    $flightNumber = $m[3];

                    if (!empty($status = trim($m[5]))) {
                        $s->extra()
                            ->status($status);
                    }

                    //parse middle block
                    if (preg_match("#{$this->opt($this->t('Operated by'))}\s+(.+)#s", $m[4], $v)) {
                        $operator = preg_replace('/\s+/', ' ', $v[1]);
                    }

                    $node = $this->re("#^(.+?) *(?:,|\n|$)#", $m[4]);

                    if (empty($node)) {
                        $node = $this->re("#^\s*(\w+\s*\([A-Z]\))\s*$#", $m[4]);
                    }

                    // $node = preg_replace('/(^\s*Class\s+|\s+Class\s*$)/', '', $node);
                    if (preg_match("#(.*)\s*\(([A-Z]{1,2})\)#", $node, $v)) {
                        if (isset($v[1]) && !empty($v[1])) {
                            $s->extra()
                                ->cabin($v[1]);
                        }
                        $s->extra()
                            ->bookingCode($v[2]);
                    } else {
                        $s->extra()
                            ->cabin($node);
                    }

                    if (preg_match("#\(non[\s\-]*smoking.*\)#s", $m[4])) {
                        $s->extra()->smoking(false);
                    }

                    if ($segType !== 'TRAIN') {
                        $flightInfo = $airline . $flightNumber;

                        if (!empty($flightInfo) && in_array($flightInfo, $this->flightArray) === true) {
                            $f->removeSegment($s);

                            foreach ($f->getSegments() as $seg) {
                                if ($seg->getAirlineName() . $seg->getFlightNumber() === $flightInfo) {
                                    $s = $seg;
                                }
                            }
                        } else {
                            $this->flightArray[] = $flightInfo;
                        }
                    }

                    if (preg_match("#{$this->opt($this->t('Seat'))}[:\s]+(\d+[A-Z])\b#", $m[4], $v)) {
                        $s->extra()
                            ->seat($v[1], false, false, $traveller);
                    }
                }

                if ($segType === 'TRAIN') {
                    $s->extra()->service($airline)->number($flightNumber);
                } else {
                    /** @var FlightSegment $s */
                    $s->departure()->terminal($terminalDep, false, true);
                    $s->arrival()->terminal($terminalArr, false, true);
                    $s->extra()->aircraft($aircraft, false, true);

                    if ($airlineConfirmation) {
                        $s->airline()->confirmation($airlineConfirmation);
                    }

                    $s->airline()->name($airline)->number($flightNumber)->operator($operator, false, true);
                }
            }

            unset($trainIt);

            $this->flightsTexts[] = $page;
        }

        foreach (array_unique(array_filter($confs)) as $conf) {
            $f->general()
                ->confirmation($conf);
        }
    }

    private function parsePrice($obj, string $text): void
    {
        $tot = $this->getTotalCurrency($this->re("/{$this->replaceSpacesReg($this->opt($this->t('PAYMENT TOTAL')))}\s+(.+)/", $text));

        if ($tot['Currency'] !== '') {
            $obj->price()->currency($tot['Currency']);
        }

        if ($tot['Total'] !== '') {
            $obj->price()->total($tot['Total']);
        }
    }

    private function normalizeDate($date)
    {
        $date = preg_replace("#\s+#", ' ', $date);
        $year = date('Y', $this->date);
        $in = [
            //Tuesday, 03 July 03:45 PM
            '#^([\-\w]+),\s+(\d+)\s+(\w+)\s+(\d+:\d+(?:\s*[ap]m)?)$#iu',
            //Sat, 28 July 2018 07:25 AM
            '#^([\-\w]+),\s+(\d+)\s+(\w+)\s+(\d{4})\s+(\d+:\d+(?:\s*[ap]m)?)$#iu',
        ];
        $out = [
            '$2 $3 ' . $year . ' $4',
            '$2 $3 $4 $5',
        ];
        $outWeek = [
            '$1',
            '',
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

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function replaceSpacesReg($str)
    {
        return preg_replace("#\s+#", '\s', $str);
    }
}
