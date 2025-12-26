<?php

namespace AwardWallet\Engine\etihad\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\dicks\Email\Statement\LoginOnly;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightPDF extends \TAccountChecker
{
	public $mailFiles = "etihad/it-889084664.eml";
    public $subjects = [
        'Your Etihad Airways booking confirmation, reference',
    ];

    public $pdfNamePattern = ".*pdf";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'etihad.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, $this->t('Etihad Airways')) === false
                || strpos($text, $this->t('etihad.com')) === false) {
                return false;
            }

            if (strpos($text, $this->t('Electronic ticket receipt')) !== false
                && strpos($text, $this->t('Flight number')) !== false
                && strpos($text, $this->t('Flight details')) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]etihad\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $text = '';
        foreach ($pdfs as $pdf) {
            $text .= \PDF::convertToText($parser->getAttachmentBody($pdf)) . "\n\n";
        }

        $this->FlightPDF($email, $text);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function FlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        preg_match_all("/{$this->opt($this->t('Booking reference'))}\:\s*([A-Z\d]+)[\n ]+/", $text, $conf);

        foreach (array_unique($conf[1]) as $confNum){
            $f->general()
                ->confirmation($confNum, 'Booking reference');
        }

        preg_match_all("/([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])[\n ]+(?:[\n ]+{$this->opt($this->t('Frequent flyer number'))}.+[\n ]+)?{$this->opt($this->t('Booking reference'))}/u", $text, $travs);

        foreach (array_unique($travs[1]) as $trav){
            $f->general()
                ->traveller(preg_replace("/\b(Mr|Mrs|Ms|Miss|Mx)\b[ ]+/", '', $trav), false);
        }

        preg_match_all("/{$this->opt($this->t('Ticket number'))}\:\s*(\d{3}[ \-]+\d+)[\n ]+/", $text, $tickets);

        foreach (array_unique($tickets[1]) as $ticketNum){
            $traveller = $this->re("/[\n ]*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])(?:[\n ]+{$this->opt($this->t('Frequent flyer number'))}.+[\n ]+)?[\n ]+{$this->opt($this->t('Booking reference'))}.+[\n ]+{$this->opt($this->t('Ticket number'))}\:\s*{$ticketNum}/u", $text);

            if ($traveller !== null){
                $f->issued()
                    ->ticket($ticketNum, false, preg_replace("/\b(Mr|Mrs|Ms|Miss|Mx)\b[ ]+/", '', $traveller));
            } else {
                $f->issued()
                    ->ticket($ticketNum, false);
            }
        }

        preg_match_all("/{$this->opt($this->t('Frequent flyer number'))}\:\s*([-A-Z\d ]+)[\n ]+/", $text, $accounts);

        foreach (array_unique($accounts[1]) as $account){
            $traveller = $this->re("/[\n ]*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])[\n ]+{$this->opt($this->t('Frequent flyer number'))}\:\s*{$account}[ ]*.+?[\n ]+/u", $text);

            if ($traveller !== null){
                $f->program()
                    ->account($account, false, preg_replace("/\b(Mr|Mrs|Ms|Miss|Mx)\b[ ]+/", '', $traveller), 'Frequent flyer number');
            } else {
                $f->program()
                    ->account($account, false, null, 'Frequent flyer number');
            }
        }

        $f->general()
            ->date(strtotime($this->re("/{$this->opt($this->t('Issue date'))}\:\s*(\d{1,2}\s*\w+\s*\d{4})\s/", $text)));

        preg_match_all("/{$this->opt($this->t('Flight details'))}[ ]*{$this->opt($this->t('Issue date'))}.+?\n+(.+?\n)[\n ]+\_?[ ]*{$this->opt($this->t('Baggage Policy'))}/s", $text, $flightInfo);

        if (empty($flightInfo[1])){
            preg_match_all("/{$this->opt($this->t('Flight details'))}[ ]*{$this->opt($this->t('Issue date'))}.+?\n+(.+?\n)[\n ]+\_?[ ]*{$this->opt($this->t('Payment details'))}/s", $text, $flightInfo);
        }

        foreach ($flightInfo[1] as $flightNode){
            $flightNode = str_replace("_", ' ', $flightNode);

            $flightNodes = preg_split("/{$this->t('Fare basis')}.+([\n\s]+)/m", $flightNode, null, PREG_SPLIT_NO_EMPTY);

            foreach ($flightNodes as $node) {

                $infoNodes = preg_split("/([\n ]+)(?={$this->t('Flight number')})/", $node);

                if (count($infoNodes) === 2){
                    $s = $f->addSegment();

                    $infoArray = $this->splitCols($infoNodes[1]);

                    if (preg_match("/^[\n ]*{$this->opt($this->t('Flight number'))}\:[ ]*(?<code>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d{1,5})[\n ]+{$this->opt($this->t('Class'))}\:[ ]*(?<class>\w+)?\,?[ ]+(?<classCode>[A-Z]+)[\n ]+{$this->opt($this->t('Baggage'))}\:[ ]*.+[\n ]+{$this->opt($this->t('Seat'))}\:[ ]*(?<seat>.+)[\n ]*$/", $infoArray[0], $m)){
                        $s->airline()
                            ->name($m['code'])
                            ->number($m['number']);


                        if (!empty($m['class'])){
                            $s->extra()
                                ->cabin($m['class']);
                        }

                        if (!empty($m['classCode'])){
                            $s->extra()
                                ->bookingCode($m['classCode']);
                        }

                        if (preg_match("/^([0-9]+[ ]*[A-Z]+)$/u", $m['seat'], $seat)){
                            $s->extra()
                                ->seat($seat[0]);
                        }
                    }

                    $infoNode = preg_replace("/(?=\n|^)([ ]{3,8})/m", "", $infoNodes[0]);

                    $flightArray = $this->createTable($infoNode, $this->rowColumnPositions($this->inOneRow($infoNode)));

                    if (preg_match("/^[\n ]*(?:(?<code>[A-Z]{3})[\n ]+(?<time>[0-9]{1,2}\:[0-9]{2})|(?<time2>[0-9]{1,2}\:[0-9]{2})[\n ]+(?<code2>[A-Z]{3}))[\n ]+(?<date>[0-9]{1,2}[ ]+\w+[ ]+[0-9]{4})[\n ]+(?<name>.+?)[\n ]*(?:Terminal[ ]*(?<terminal>[A-Z0-9]+))?$/s", $flightArray[0], $m)) {
                        $s->departure()
                            ->name(str_replace("\n", ", ", $m['name']));

                        if (!empty($m['code2'])){
                            $s->departure()
                                ->code($m['code2']);
                        } else if (!empty($m['code'])){
                            $s->departure()
                                ->code($m['code']);
                        }

                        if (!empty($m['time2'])){
                            $s->departure()
                                ->date(strtotime($m['date'] . ' ' . $m['time2']));
                        } else if (!empty($m['time'])){
                            $s->departure()
                                ->date(strtotime($m['date'] . ' ' . $m['time']));
                        }

                        if (!empty($m['terminal'])){
                            $s->departure()
                                ->terminal($m['terminal']);
                        }
                    }

                    if (preg_match("/^[\n ]*(?:(?<code>[A-Z]{3})[\n ]+(?<time>[0-9]{1,2}\:[0-9]{2})|(?<time2>[0-9]{1,2}\:[0-9]{2})[\n ]+(?<code2>[A-Z]{3}))[\n ]+(?<date>[0-9]{1,2}[ ]+\w+[ ]+[0-9]{4})[\n ]+(?<name>.+?)[\n ]*(?:Terminal[ ]*(?<terminal>[A-Z0-9]+))?$/s", $flightArray[2], $m)) {
                        $s->arrival()
                            ->name(str_replace("\n", ", ", $m['name']));

                        if (!empty($m['code2'])){
                            $s->arrival()
                                ->code($m['code2']);
                        } else if (!empty($m['code'])){
                            $s->arrival()
                                ->code($m['code']);
                        }

                        if (!empty($m['time2'])){
                            $s->arrival()
                                ->date(strtotime($m['date'] . ' ' . $m['time2']));
                        } else if (!empty($m['time'])){
                            $s->arrival()
                                ->date(strtotime($m['date'] . ' ' . $m['time']));
                        }

                        if (!empty($m['terminal'])){
                            $s->arrival()
                                ->terminal($m['terminal']);
                        }
                    }

                    if (preg_match("/^[\n ]*(\d+h[ ]+\d+m)[\n ]*$/", $flightArray[1],$m)){
                        $s->extra()
                            ->duration($m[1]);
                    }

                    $operator = $this->re("/{$this->opt($this->t('Operated by'))}\:[ ]*(.+)[\n ]*{$this->opt($this->t('Marketed by'))}/us", $infoArray[1]);

                    if ($operator !== null){
                        $s->airline()
                            ->operator(preg_replace("/(\n*\bAS\b.+)/s", ' ', $operator));
                    }

                    foreach ($f->getSegments() as $key => $seg) {
                        if ($seg->getId() !== $s->getId()) {
                            if (serialize(array_diff_key($seg->toArray(),
                                    ['seats' => [], 'assignedSeats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => [], 'assignedSeats' => []]))) {
                                if (!empty($s->getAssignedSeats())) {
                                    foreach ($s->getAssignedSeats() as $seat) {
                                        $seg->extra()
                                            ->seat($seat[0], false, false, $seat[1]);
                                    }
                                } elseif (!empty($s->getSeats())) {
                                    $seg->extra()->seats(array_unique(array_merge($seg->getSeats(),
                                        $s->getSeats())));
                                }
                                $f->removeSegment($s);

                                break;
                            }
                        }
                    }
                }
            }
        }

        preg_match_all("/[ ]*\n+[ ]*({$this->opt($this->t('Payment details'))}.*?{$this->opt($this->t('Total Amount'))}.*?)[ ]*\n+[ ]*/s", $text, $fares);

        $totalArray = [];
        $costArray = [];

        foreach ($fares[1] as $fare){
            $fareCols = $this->splitCols($fare);

            if (preg_match("/{$this->opt($this->t('Total Amount'))}\:[ ]*(?<currency>\D{1,3})[ ]*(?<amount>\d[\,\.\'\d ]*)(?:[\n ]+|$)/", $fareCols[1], $matches)
                || preg_match("/{$this->opt($this->t('Total Amount'))}\:[ ]*(?<amount>\d[\,\.\'\d ]*)\s*(?<currency>\D{1,3})(?:[\n ]+|$)/", $fareCols[1], $matches)) {
                $currency = $this->normalizeCurrency($matches['currency']);

                $f->price()
                    ->currency($currency);

                $totalArray[] = PriceHelper::parse($matches['amount'], $currency);
                $costArray[] = PriceHelper::parse($this->re("/[ ]*\n+[ ]*{$this->opt($this->t('Fare'))}\:[ ]*\D{1,3}?[ ]*(\d[\,\.\'\d ]*)[ ]*\D{1,3}?[ ]*\n+[ ]*/", $fareCols[1]), $currency);

                $fees = $this->re("/\n+[ ]*{$this->opt($this->t('Carrier fees'))}\:[ ]*\D{1,3}[\d\D]+?[ ]+(\d[\,\.\'\d ]*)[\d\D]{2}\n/", $fareCols[1]);

                if ($fees !== null){
                    $f->price()
                        ->fee("Carrier fees", PriceHelper::parse($fees, $currency));
                }

                $taxesText = $this->re("/\n+[ ]*{$this->opt($this->t('Taxes'))}\:[ ]*(.*?)(?:{$this->opt($this->t('Carrier fees'))}|{$this->opt($this->t('Total Amount'))})/s", $fareCols[1]);

                if ($taxesText !== null){
                    $taxesArray = preg_split("/(\n+)/", $taxesText, null, PREG_SPLIT_NO_EMPTY);

                    foreach ($taxesArray as $tax){
                        if (preg_match("/^\D{1,3}[\d\D]+?[ ]+(?<value>\d[\,\.\'\d ]*)(?<name>[\d\D]{2})$/", $tax, $t)){
                            $f->price()
                                ->fee($t['name'], PriceHelper::parse($t['value'], $currency));
                        }
                    }
                }
            }
        }

        if (!empty($totalArray)){
            $f->price()
                ->total(array_sum($totalArray));
        }

        if (!empty($costArray)){
            $f->price()
                ->cost(array_sum($costArray));
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

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'SGD' => ['SG$'],
            'ZAR' => ['R'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s, '/'));
            }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
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
