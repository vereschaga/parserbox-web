<?php

namespace AwardWallet\Engine\japanair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TicketHtml2015En extends \TAccountChecker
{
    public $mailFiles = "japanair/it-174412407.eml, japanair/it-4566894.eml, japanair/it-46645001.eml, japanair/it-500019838.eml, japanair/it-500494841.eml, japanair/it-501417020.eml, japanair/it-5200633.eml, japanair/it-5212879.eml, japanair/it-56570162.eml, japanair/it-7152127.eml";

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
            "segmentStatus" => ["/OK", "/SA", "/NS"],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        $emailRoots = $this->http->XPath->query("//tr[not(.//tr) and contains(normalize-space(),'ELECTRONIC TICKET ITINERARY/RECEIPT')]/ancestor::table[ descendant::text()[normalize-space()='TICKETING DATE'] ][1]");

        if ($emailRoots->length === 0) {
            $emailRoots = [null];
        }

        foreach ($emailRoots as $eRoot) {
            $this->parseFlight($email, $eRoot);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach (['Your Electronic Ticket Receipt', 'Your Electronic Ticket Itinerary'] as $phrase) {
            if (stripos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//*[contains(normalize-space(),"JAPAN AIRLINES")]')->length === 0) {
            return false;
        }

        return $this->http->XPath->query('//*[contains(normalize-space(),"ELECTRONIC TICKET ITINERARY/RECEIPT")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Japan Airlines') !== false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    private function parseFlight(Email $email, ?\DOMNode $eRoot): void
    {
        $f = $email->add()->flight();

        // General
        $resDate = $this->http->FindSingleNode('descendant::text()[contains(normalize-space(),"TICKETING DATE")]/ancestor::td[1]/following-sibling::td[1]', $eRoot);

        if (empty($resDate)) {
            $resDate = $this->http->FindSingleNode('descendant::text()[contains(normalize-space(),"TICKETING DATE")]/following::text()[normalize-space()][1]', $eRoot);
        }

        $f->general()
            ->noConfirmation()
            ->traveller(preg_replace("/\s*(MR|MS|MRS|MISS)$/", '', $this->http->FindSingleNode('descendant::text()[contains(normalize-space(),"NAME")]/ancestor::td[1]/following-sibling::td[2]', $eRoot)))
            ->date(strtotime($resDate))
        ;

        // Issued
        $f->issued()
            ->ticket($this->http->FindSingleNode('descendant::text()[contains(normalize-space(),"TICKET NUMBER")]/ancestor::td[1]/following-sibling::td[1]', $eRoot), false)
        ;

        /*
         * <thead>
         */

        $xpath = "(//text()[starts-with(normalize-space(), 'FB: ')]/ancestor::table[descendant::text()[{$this->contains($this->t('segmentStatus'))}]])[last()]/descendant::tr[*[normalize-space()][position() > 3][{$this->contains($this->t('segmentStatus'))}]]";
        $nodes = $this->http->XPath->query($xpath);
        //it-174412407.eml - only
        $nodesError = false;

        if ($nodes->length > 0) {
            foreach ($nodes as $root) {
                $nextTr = "following::text()[normalize-space()][1]/ancestor::tr[1][descendant::text()[contains(normalize-space(), '/')]]/";

                $s = $f->addSegment();

                $dNoTerminal = false;
                $aNoTerminal = false;

                // Airline
                $flight = $this->http->FindSingleNode("*[normalize-space()][4]", $root, true, "/^[A-Z\d]{2}\d{1,5}$/");

                if (empty($flight) && !empty($flight = $this->http->FindSingleNode("*[normalize-space()][3]", $root, true, "/^[A-Z\d]{2}\d{1,5}$/"))) {
                    $dNoTerminal = true;
                }

                if (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5})\s*$/", $flight, $m)) {
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn']);
                    $conf = $this->http->FindSingleNode("*[normalize-space()][last()]", $root, true, "/^([A-Z\d]{5,7})(?:\\/[A-Z\d]{2})?$/");

                    if (!empty($conf)) {
                        $s->airline()
                            ->confirmation($conf);
                    }

                    $operator = $this->http->FindSingleNode("following::tr[not(.//tr)][normalize-space()][2]/*[contains(normalize-space(), 'OPERATED BY')]",
                        $root, true, "/OPERATED BY[ ]*(.{2,})/");

                    if (!empty($operator)) {
                        $s->airline()
                            ->operator($operator);
                    }
                } else {
                    $nodesError = true;

                    break;
                }

                // Departure
                $date = $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, "/^\s*\d{2}[A-Z]{3}\s*\(.*/");
                $name = $this->http->FindSingleNode("*[normalize-space()][2]", $root, true, "/.+\\/.+/");
                $time = $this->http->FindSingleNode("*[normalize-space()][" . (6 + ($dNoTerminal ? -1 : 0)) . "]", $root, true, "/^\s*\d{4}.*/");

                if (empty($date) || empty($time) || empty($name)) {
                    $nodesError = true;

                    break;
                }

                $s->departure()
                    ->name($name)
                    ->noCode()
                    ->date($this->normalizeDate($date . ', ' . $time));

                $depTerminal = $this->http->FindSingleNode("*[normalize-space()][3]", $root);

                if ($dNoTerminal === false) {
                    $s->departure()
                        ->terminal($depTerminal);
                }

                // Arrival
                $aNoDate = false;
                $adate = $this->http->FindSingleNode($nextTr . "*[normalize-space()][1]", $root, true, "/^\s*\d{2}[A-Z]{3}\s*\(.*/");

                if (empty($adate)) {
                    $aNoDate = true;
                } else {
                    $date = $adate;
                }
                $time = $this->http->FindSingleNode($nextTr . "*[normalize-space()][" . (5 + ($aNoDate ? -1 : 0)) . "]", $root, true, "/^\s*\d{4}.*/");

                if (empty($time) && !empty($time = $this->http->FindSingleNode($nextTr . "*[normalize-space()][" . (4 + ($aNoDate ? -1 : 0)) . "]", $root, true, "/^\s*\d{4}.*/"))) {
                    $aNoTerminal = true;
                }

                $name = $this->http->FindSingleNode($nextTr . "*[normalize-space()][" . (2 + ($aNoDate ? -1 : 0)) . "]", $root, true, "/.+\\/.+/");

                if (empty($date) || empty($time) || empty($name)) {
                    $nodesError = true;

                    break;
                }

                $s->arrival()
                    ->name($name)
                    ->noCode()
                    ->date($this->normalizeDate($date . ', ' . $time));

                $arrTerminal = $this->http->FindSingleNode($nextTr . "*[normalize-space()][" . (3 + ($aNoDate ? -1 : 0)) . "]", $root);

                if ($aNoTerminal === false) {
                    $s->arrival()
                        ->terminal($arrTerminal);
                }

                $s->extra()
                    ->bookingCode($this->http->FindSingleNode("*[normalize-space()][" . (5 + ($dNoTerminal ? -1 : 0)) . "]", $root, true, "/^\s*(\D)\//"));

                $s->extra()
                    ->duration($this->http->FindSingleNode($nextTr . "*[normalize-space()][" . (6 + ($aNoDate ? -1 : 0) + ($aNoTerminal ? -1 : 0)) . "]", $root, true, "/^\s*\((\d{2}+H\d{2})\)\s*$/u"));
            }

            if ($nodesError === true) {
                foreach ($f->getSegments() as $s) {
                    // $f->removeSegment($s);
                }
            }
        }

        if ($nodes->length == 0 || $nodesError === true) {
            $tableHeaders = [];
            $tableRows = $this->http->XPath->query("descendant::tr[ not(.//tr) and *[{$this->starts(['DATE', 'DIA', 'DATUM', '日期', '日付'])}] and *[{$this->starts(['FLIGHT', 'VUELO', 'FLUG', '航班', '便名', 'เที่ยวบิน'])}] ] | descendant::tr[ not(.//tr) and descendant::text()[{$this->eq('/NVA)')}] ]", $eRoot);

            foreach ($tableRows as $key => $tRow) {
                if ($key === 0) {
                    $cells = $this->http->XPath->query('*', $tRow);

                    foreach ($cells as $cell) {
                        $colspan = $this->http->FindSingleNode('@colspan', $cell);
                        $cellSize = empty($colspan) || !is_numeric($colspan) ? 1 : $colspan;
                        $tableHeaders[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $cell));

                        for ($i = 1; $i < $cellSize; $i++) {
                            $tableHeaders[] = null;
                        }
                    }
                } else {
                    $cells = $this->http->XPath->query('*', $tRow);

                    foreach ($cells as $cell) {
                        $cellText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $cell));
                        $leftShift = 0;

                        foreach ($this->http->XPath->query('preceding-sibling::*', $cell) as $preCell) {
                            $colspan = $this->http->FindSingleNode('@colspan', $preCell);
                            $cellSize = empty($colspan) || !is_numeric($colspan) ? 1 : $colspan;
                            $leftShift += $cellSize;
                        }
                        $tableHeaders[$leftShift] .= "\n" . $cellText;

                        $colspan = $this->http->FindSingleNode('@colspan', $cell);

                        if (is_numeric($colspan) && $colspan > 1) {
                            for ($i = 1; $i < $colspan; $i++) {
                                $tableHeaders[$leftShift + $i] .= "\n";
                            }
                        }
                    }
                }
            }

            /*
             * <tbody>
             */

            $tableBody = [];
            $xpathExceptions = "[not(descendant::text()[{$this->starts(['FB:', 'BGG:', 'NVB:', 'NVA:', 'NVB/NVA:'])}])]";
            $tableRows = $this->http->XPath->query("descendant::tr[ normalize-space() and preceding-sibling::tr[descendant::text()[{$this->eq('/NVA)')}]] and following-sibling::tr[descendant::text()[{$this->contains(['TARIF/TICKET INFORMATION', 'TARIFA/INFORMACIÓN', 'FARE/TICKET'])}]] ]" . $xpathExceptions, $eRoot);

            foreach ($tableRows as $tRow) {
                if (count(array_filter($this->http->FindNodes('*[normalize-space()]', $tRow, '/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+$/')))) {
                    // JL7081
                    $newTR = [];
                    $cells = $this->http->XPath->query('*', $tRow);

                    foreach ($cells as $cell) {
                        $colspan = $this->http->FindSingleNode('@colspan', $cell);
                        $cellSize = empty($colspan) || !is_numeric($colspan) ? 1 : $colspan;
                        $newTR[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $cell));

                        for ($i = 1; $i < $cellSize; $i++) {
                            $newTR[] = null;
                        }
                    }
                    $tableBody[] = $newTR;
                } elseif (isset($newTR)) {
                    $cells = $this->http->XPath->query('*', $tRow);

                    foreach ($cells as $cell) {
                        $cellText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $cell));
                        $leftShift = 0;

                        foreach ($this->http->XPath->query('preceding-sibling::*', $cell) as $preCell) {
                            $colspan = $this->http->FindSingleNode('@colspan', $preCell);
                            $cellSize = empty($colspan) || !is_numeric($colspan) ? 1 : $colspan;
                            $leftShift += $cellSize;
                        }
                        $tableBody[count($tableBody) - 1][$leftShift] .= "\n" . $cellText;

                        $colspan = $this->http->FindSingleNode('@colspan', $cell);

                        if (is_numeric($colspan) && $colspan > 1) {
                            for ($i = 1; $i < $colspan; $i++) {
                                $tableBody[count($tableBody) - 1][$leftShift + $i] .= "\n";
                            }
                        }
                    }
                }
            }

            $pos = [];

            foreach ($tableHeaders as $key => $cell) {
                if (preg_match("/^DATE$/m", $cell)) {
                    $pos['date'] = $key;
                } elseif (preg_match("/^CITY\b/m", $cell) || preg_match("/\bAIRPORT$/m", $cell)) {
                    $pos['airport'] = $key;
                } elseif (preg_match("/^TERMINAL$/m", $cell)) {
                    $pos['terminal'] = $key;
                } elseif (preg_match("/^FLIGHT$/m", $cell)) {
                    $pos['flight'] = $key;
                } elseif (preg_match("/^CLS\b/m", $cell)) {
                    $pos['class'] = $key;
                } elseif (preg_match("/^TIME$/m", $cell)) {
                    $pos['time'] = $key;
                } elseif (preg_match("/^REFERENCE$/m", $cell)) {
                    $pos['reference'] = $key;
                } elseif (preg_match("/^[\/ ]*BGG[ )]*$/m", $cell)) {
                    $pos['bgg'] = $key;
                } elseif (preg_match("/^[\/ ]*NVB[ )]*$/m", $cell)) {
                    $pos['nvb'] = $key;
                } elseif (preg_match("/^[\/ ]*NVA[ )]*$/m", $cell)) {
                    $pos['nva'] = $key;
                }
            }

            foreach ($tableBody as $cells) {
                $s = $f->addSegment();

                $dateDep = $dateArr = $timeDep = $timeArr = null;

                foreach ($cells as $key => $cell) {
                    if (empty(trim($cell))) {
                        continue;
                    }

                    if (!empty($pos['airport'])
                        && $key < $pos['airport']
                        && preg_match('/^(.{4,})[ ]*\([\w ]*\)\n+(?:(.{4,})[ ]*\([\w ]*\))?/', $cell, $m)
                        && !empty($f->getReservationDate())
                    ) {
                        $dateDep = EmailDateHelper::parseDateRelative($m[1], $f->getReservationDate());

                        if (!empty($m[2])) {
                            $dateArr = EmailDateHelper::parseDateRelative($m[2], $f->getReservationDate());
                        }
                    }

                    // Name, Code
                    // Operator
                    if (!empty($pos['airport']) && !empty($pos['terminal'])
                        && $key >= $pos['airport'] && $key < $pos['terminal']
                    ) {
                        if (preg_match('/^(.{3,})\n+(.{3,})/', $cell, $m)) {
                            $s->departure()
                                ->noCode()
                                ->name($m[1]);

                            $s->arrival()
                                ->noCode()
                                ->name($m[2]);
                        }

                        if (preg_match('/OPERATED BY[ ]*(.{2,})/', $cell, $m)) {
                            $s->airline()
                                ->operator($m[1]);
                        }

                        continue;
                    }

                    // Terminal
                    if (!empty($pos['terminal']) && !empty($pos['flight'])
                        && $key >= $pos['terminal'] && $key < $pos['flight']
                        && preg_match('/^([-A-Z\d ]+)?\n+([-A-Z\d ]+)?/', $cell, $m)
                    ) {
                        if (!empty($m[1])) {
                            $s->departure()->terminal($m[1]);
                        }

                        if (!empty($m[2])) {
                            $s->arrival()->terminal($m[2]);
                        }

                        continue;
                    }

                    // Airline
                    if (!empty($pos['terminal']) && !empty($pos['class'])
                        && $key > $pos['terminal'] && $key < $pos['class']
                        && preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:\n|$)/', $cell, $m)
                    ) {
                        $s->airline()
                            ->name($m['name'])
                            ->number($m['number'])
                        ;

                        continue;
                    }

                    // Extra
                    if (!empty($pos['class']) && !empty($pos['time'])
                        && $key >= $pos['class'] && $key < $pos['time']
                        && preg_match('/^([A-Z]{1,2})(?:[ ]*\/|\n|$)/', $cell, $m)
                    ) {
                        $s->extra()->bookingCode($m[1]);

                        continue;
                    }

                    // Duration
                    if (!empty($pos['class'])
                        && $key > $pos['class']
                        && preg_match('/^(\d{4})\n+(\d{4})(?:[ ]*\([ ]*(\d.+?)[ ]*\))?/', $cell, $m)
                    ) {
                        $s->departure()
                            ->date(strtotime($m[1], $dateDep));
                        $s->arrival()
                            ->date(strtotime($m[2], $dateArr ?? $dateDep));

                        if (!empty($m[3])) {
                            $s->extra()->duration($m[3]);
                        }
                    }

                    if (empty($seg['Duration'])
                        && !empty($pos['time'])
                        && $key > $pos['time']
                        && preg_match('/\n^\([ ]*(\d.+?)[ ]*\)$/m', $cell, $m)
                    ) {
                        $seg['Duration'] = $m[1];
                    }

                    // REFERENCE
                    if (!empty($pos['bgg']) && !empty($pos['nva'])
                        && $key >= $pos['bgg'] && $key <= $pos['nva']
                        && preg_match('/^([A-Z\d]{5,})(?:[ ]*\/|\n|$)/', $cell, $m)
                    ) {
                        $s->airline()->confirmation($m[1]);

                        continue;
                    }
                }
            }
        }
        $xpathPrice = "descendant::text()[contains(normalize-space(),'FORM OF PAYMENT')]/following::text()";

        // Price
        if ($this->http->FindSingleNode($xpathPrice . "[normalize-space()][1][normalize-space()='AWARD']/following::text()[normalize-space()][1][contains(normalize-space(),'FARE')]", $eRoot) === null) {
            // not only award
            $totalCharge = $this->http->FindSingleNode("({$xpathPrice}[contains(.,\"TOTAL\")])[1]/ancestor::td[1]/following-sibling::td[1]", $eRoot);

            if (preg_match('/^(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d ]*)A?$/', $totalCharge, $matches)) {
                $f->price()
                    ->currency($matches['currency'])
                    ->total($this->amount($matches['amount']))
                ;

                $fare = $this->http->FindSingleNode("({$xpathPrice}[contains(.,\"EQUIV FARE PAID\")])[1]/ancestor::td[1]/following-sibling::td[1]", $eRoot);

                if ($fare === null) {
                    $fare = $this->http->FindSingleNode("({$xpathPrice}[contains(.,\"FARE\")])[1]/ancestor::td[1]/following-sibling::td[1]", $eRoot);
                }

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?\s*(?<amount>\d[,.\'\d ]*)$/', $fare, $m)) {
                    $f->price()->cost($this->amount($m['amount']));
                }

                $taxesAr = $this->http->FindNodes("({$xpathPrice}[contains(.,'TAX/FEE/CHARGE')])[1]/ancestor::td[1]/following-sibling::td[normalize-space()]//text()[normalize-space()] | descendant::tr[ preceding-sibling::tr/*[normalize-space()][1][contains(normalize-space(),'TAX/FEE/CHARGE')] and following-sibling::tr/*[normalize-space()][1][contains(normalize-space(),'TOTAL')] ]/*[normalize-space()]", $eRoot);
                $taxesStr = implode(' ', $taxesAr);
                $taxes = $this->split("/\b([A-Z]{3}\s+(?:PD +)?\d)/", $taxesStr);

                foreach ($taxes as $tax) {
                    if (preg_match('/^\s*(?:' . preg_quote($matches['currency'], '/') . ')?\s*(?:PD +)?(?<amount>\d[,.\'\d ]*)\s+(?<name>[A-Z][A-Z\d])[ ]*$/', $tax, $m)) {
                        $f->price()->fee($m['name'], $this->amount($m['amount']));
                    }
                }
            }
        }
    }

    //========================================
    // Auxiliary methods
    //========================================

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        // $this->logger->debug('$str = ' . print_r($str, true));
        $in = [
            //24JUL (SUN), 1040
            "#^(\d+)(\w+)\s*\(([A-Z]{3})\)\,\s*(\d{2})(\d{2})$#i",
        ];
        $out = [
            "$3, $1 $2 $year, $4:$5",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
