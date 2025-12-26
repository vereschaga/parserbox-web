<?php

namespace AwardWallet\Engine\obb\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TicketPdf extends \TAccountChecker
{
    public $mailFiles = "obb/it-12567513.eml, obb/it-12583187.eml, obb/it-848746818.eml";

    public $lang;
    public static $dictionary = [
        'de' => [
            'FAHRSCHEIN + RESERVIERUNG' => ['FAHRSCHEIN + RESERVIERUNG', 'RESERVIERUNG', 'FAHRSCHEIN'],
        ],
    ];

    private $detectFrom = "tickets@oebb.at";
    private $detectSubject = [
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        // if ($this->detectEmailFromProvider($headers['from']) !== true && stripos($headers["subject"], 'ÖBB')) {
        //     return false;
        // }
        //
        // foreach ($this->detectSubject as $dSubject) {
        //     if (stripos($headers["subject"], $dSubject) !== false) {
        //         return true;
        //     }
        // }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".*\.pdf");

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->containsText($text, 'ÖBB') !== true && $this->containsText($text, '.oebb.at') !== true) {
                continue;
            }

            foreach (self::$dictionary as $dict) {
                if (!empty($dict['FAHRSCHEIN + RESERVIERUNG']) && $this->containsText($text, $dict['FAHRSCHEIN + RESERVIERUNG'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*\.pdf");

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->containsText($text, 'ÖBB') !== true && $this->containsText($text, '.oebb.at') !== true) {
                continue;
            }

            foreach (self::$dictionary as $lang => $dict) {
                if (!empty($dict['FAHRSCHEIN + RESERVIERUNG']) && $this->containsText($text, $dict['FAHRSCHEIN + RESERVIERUNG'])
                    && preg_match("/{$this->opt($dict['FAHRSCHEIN + RESERVIERUNG'])} {2,}[[:alpha:]]+/u", $text)
                ) {
                    $this->lang = $lang;
                    $this->parseEmailPdf($email, $text);

                    break;
                }
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

    protected function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmailPdf(Email $email, $text)
    {
        $tickets = $this->split("/(?:^|\n) *({$this->opt($this->t('FAHRSCHEIN + RESERVIERUNG'))} {2,}[[:alpha:]]+)/", $text);

        foreach ($tickets as $sText) {
            unset($t);
            $conf = str_replace(' ', '',
                $this->re("/\n *{$this->opt($this->t("Ticketcode"))}[ \d]+{$this->opt($this->t("zur Buchung"))}\s*(\d+(?: ?\d+)*)(?: {2,}|\s*\n)/", $sText));

            foreach ($email->getItineraries() as $it) {
                if (in_array($conf, array_column($it->getConfirmationNumbers(), 0))) {
                    $t = $it;

                    break;
                }
            }

            if (!isset($t)) {
                $t = $email->add()->train();

                $t->general()
                    ->confirmation($conf);
            }

            // General
            $traveller = $this->re("/{$this->opt($this->t("FAHRSCHEIN + RESERVIERUNG"))} {2,}([[:alpha:] \-]+)\n/u", $sText);

            if (!in_array($traveller, array_column($t->getTravellers(), 0))) {
                $t->general()
                    ->traveller($traveller);
            }

            $purchaseDate = null;

            if (preg_match("/ +{$this->opt($this->t('PREIS'))} +(?:.*\n){1,7} +(\d{2})(\d{2})(\d{2}) {2,}\d{1,2}:\d{2} {2,}/",
                $sText, $m)) {
                $purchaseDate = strtotime($m[1] . '.' . $m[2] . '.' . '20' . $m[3]);
            }

            $routeText = $this->re("/\n *{$this->opt($this->t('DATUM'))} +{$this->opt($this->t('ZEIT'))} +{$this->opt($this->t('VON'))} +.+\s*\n(.+)/", $sText);

            $re = "/^\s*(?<dDate>\d+\.\d+) +(?<dTime>\d+:\d+) +(?<dName>.+?) +-> *(?<aName>.+?) +(?<aDate>\d+\.\d+) +(?<aTime>\d+:\d+) +(?<class>.+)/";

            if (preg_match("/\n( *{$this->opt($this->t('Haltestelle'))} {2,}{$this->opt($this->t('Datum'))} {2,}{$this->opt($this->t('Zeit'))} +[\s\S]+?)(( {80,}.*\n|\n){2,}|$)/", $sText, $m)) {
                $st = $m[1];
                $cabin = null;

                if (preg_match($re, $routeText, $m)) {
                    $cabin = $m['class'];
                }

                if (preg_match_all("/^ *(?<dName>\S( ?\S)+) {2,}(?<dDate>\d[\d\.]+) {2,}(?<dTime>\d{1,2}:\d{1,2}) {2,20}(?<service>\w+(?: ?\S)*) (?<number>\d+)\b.*"
                    . "\n *(?<aName>\S( ?\S)+) {2,}(?<aDate>\d[\d\.]+) {2,}(?<aTime>\d{1,2}:\d{1,2})/m", $st, $m)
                ) {
                    foreach ($m[0] as $i => $v) {
                        $s = $t->addSegment();

                        $s->departure()
                            ->name($m['dName'][$i])
                            ->date(strtotime($m['dDate'][$i] . ', ' . $m['dTime'][$i]));
                        $s->arrival()
                            ->name($m['aName'][$i])
                            ->date(strtotime($m['aDate'][$i] . ', ' . $m['aTime'][$i]));

                        $s->extra()
                            ->cabin($cabin)
                            ->number($m['number'][$i])
                            ->service($m['service'][$i]);
                    }
                }
            } else {
                $s = $t->addSegment();

                if (preg_match($re, $routeText, $m)) {
                    $s->departure()
                        ->name($m['dName'])
                        ->date($this->normalizeDate($m['dDate'] . ', ' . $m['dTime'], $purchaseDate));
                    $s->arrival()
                        ->name($m['aName'])
                        ->date($this->normalizeDate($m['aDate'] . ', ' . $m['aTime'], $purchaseDate));
                    $s->extra()
                        ->cabin($m['class']);
                }

                $re = "/\n *{$this->opt($this->t('ZUG'))} {2,}(?<number>\d+) +(?<service>\w+(?: ?\S)*) {2,}{$this->opt($this->t('WAGEN'))} +(\w+) +.*\s*\n"
                    . "(?: +[^\d\n]*\n)? +\S.+? {2,}(?<seats>\w( ?\S)*)\n+.*/ui";

                if (preg_match($re, $sText, $m) || preg_match($re2, $sText, $m)) {
                    $s->extra()
                        ->number($m['number'])
                        ->service($m['service'])
                        ->seats(explode(' ', $m['seats']));
                }
            }

            $segments = $t->getSegments();

            foreach ($segments as $seg) {
                if ($seg->getId() === $s->getId()) {
                    continue;
                }

                if ($s->getNumber() == $seg->getNumber()
                    && $s->getDepDate() == $seg->getDepDate()
                    && $s->getArrDate() == $seg->getArrDate()
                ) {
                    if (!empty($s->getSeats())) {
                        $seg->extra()->seats(array_unique(array_merge($seg->getSeats(), $s->getSeats())));
                    }
                    $t->removeSegment($s);

                    continue 2;
                }
            }

            $total = $this->getTotal($this->re("/ +{$this->opt($this->t('PREIS'))} {2,}(.+)/", $sText));
            $tTotal = $t->getPrice() ? $t->getPrice()->getTotal() : 0.0;
            $t->price()
                ->currency($total['currency'])
                ->total($tTotal + $total['amount']);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function normalizeDate($str, $dateRelative = null)
    {
        // $this->logger->debug('$date = '.print_r( $str,true));
        $in = [
            '/^\s*(\d{2})\.(\d{2})\s*,\s*(\d{1,2}:\d{2})\s*$/',
        ];
        $out = [
            '$1.$2.%year%, $3',
        ];

        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$date Replace = '.print_r( $str,true));
        // $this->logger->debug('$dateRelative = '.print_r( $dateRelative,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // $this->logger->debug('$date Translate = '.print_r( $str,true));

        if (!empty($dateRelative) && strpos($str, '%year%') !== false
            && preg_match('/^\s*(?<date>\d+[ \.](?<month>\w+))[ \.]%year%(?:\s*,\s(?<time>\d{1,2}:\d{1,2}.*))?$/', $str, $m)) {
            // $this->logger->debug('$date (no week, no year) = '.print_r( $m['date'],true));
            $str = EmailDateHelper::parseDateRelative($m['date'], $dateRelative, true, (is_numeric($m['month'])) ? '%D%.%Y%' : '%D% %Y%');

            if (!empty($str) && !empty($m['time'])) {
                return strtotime($m['time'], $str);
            }

            return $str;
        } elseif (preg_match("/^\d+ [[:alpha:]]+ \d{4}$/", $str)) {
            // $this->logger->debug('$date (year) = '.print_r( $str,true));
            return strtotime($str);
        } else {
            return null;
        }

        return null;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
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

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'   => 'EUR',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function columnPositions($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColumnPositions($row));
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
