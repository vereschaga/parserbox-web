<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parser princess/Itinerary (in favor of princess/Itinerary)

// parsers with similar formats: royalcaribbean/It2, royalcaribbean/AgentGuestBooking, celebritycruises/InvoiceAgentGuestPdf

class POCruisesPdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $reBody = [
        'en'   => ['BOOKING CONFIRMATION', 'Passenger Copy'],
        'en2'  => ['UPGRADE NOTICE', 'Passenger Copy'],
        'en3'  => ['BOOKING CONFIRMATION', 'CANCELLATION SCHEDULE'],
        'en4'  => ['DEPOSIT CONFIRMATION', 'CANCELLATION SCHEDULE'],
    ];

    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'name' => ['Last Name:', 'Name:'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug("Can't determine a language!");

                        continue;
                    }
                    $this->parseEmail($text, $email);
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = $this->detectEmailFromProvider($parser->getCleanFrom());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (($detectProvider || stripos($text, 'mtatravel.com.au') !== false) && $this->assignLang($text)
                // below providers from parser princess/Itinerary
                && !preg_match('/[.@]princess\.com\b/i', $text) // princess
                && !preg_match('/[.@]carnival\.(com\.au|co\.nz)\b/i', $text) && strpos($text, 'Carnival Cruise Lines') === false // carnival
                && !preg_match('/[.@]pocruises\.(com\.au|co\.nz)\b/i', $text) && strpos($text, 'P&O Cruises') === false // pocruises
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]mtatravel\.(com\.au|co\.nz)$/i', $from) > 0;
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

    public function findСutSection($input, $searchStart, $searchFinish)
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
                    return false;
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

    private function parseEmail($textPDF, Email $email): void
    {
        $itBlock = $this->findСutSection($textPDF, 'BOOKING ITINERARY', 'COMMENTS');

        if (empty($itBlock)) {
            $this->logger->debug('other format itBlock');

            return;
        }
        $itBlock = $this->re("#(^ *Date.+)#sm", $itBlock);

        $infoBlock = $this->re("#(^.*{$this->opt($this->t('Voyage / Dest'))}[\s\S]+)#m", strstr($textPDF, $this->t('PASSENGERS'), true));
        $pos[] = 0;

        if (preg_match("#^((.+? )(?:{$this->opt($this->t('Voyage / Dest'))}|{$this->opt($this->t('Group'))}).+?){$this->opt($this->t('Dining Request'))}#", $this->re("#^(.+)#", $infoBlock), $m)) {
            $posCol2 = mb_strlen($m[2]);
            $pos[] = $posCol2;
            $posCol3 = mb_strlen($m[1]);
            $pos[] = $posCol3;
        } elseif (preg_match("#^(.+? )(?:{$this->opt($this->t('Voyage / Dest'))}|{$this->opt($this->t('Group'))})#", $this->re("#^(.+)#", $infoBlock), $m)) {
            $posCol2 = mb_strlen($m[1]);
            $pos[] = $posCol2;
        }

        if (count($pos) < 2) {
            $this->logger->debug("other format infoBlock");

            return;
        }
        $infoBlock = $this->splitCols($infoBlock, $pos);
        $infoBlock = $infoBlock[1];

        $priceBlock = $this->findСutSection($textPDF, 'PRICING DETAILS', 'CANCELLATION SCHEDULE');

        if (empty($priceBlock)) {
            $this->logger->debug('other format priceBlock');

            return;
        }

        $paxBlock = $this->findСutSection($textPDF, 'PASSENGERS', 'PRICING DETAILS');

        if (empty($paxBlock)) {
            $this->logger->debug('other format paxBlock');

            return;
        }
        $paxBlock = 'PASSENGERS' . "\n" . $paxBlock;
        $paxTables = $this->splitter("#(^ *{$this->opt($this->t('name'))})#m", $paxBlock);
        $pax = [];
        $acc = [];

        foreach ($paxTables as $paxTable) {
            $names = $this->re("#{$this->opt($this->t('name'))}[: ]*(.+?)(?:[ ]{3,}{$this->opt($this->t('Totals'))})?$#m", $paxTable);
            $names = array_filter(array_map('trim', explode("|", preg_replace("#\s{4,}#", "|", $names))));
            $pax = array_merge($pax, $names);
            $accNums = $this->re("#{$this->opt($this->t('Member Number:'))}[: ]*(.+?)(?:[ ]{3,}{$this->opt($this->t('Totals'))})?$#m", $paxTable);
            $accNums = array_filter(array_map('trim', explode("|", preg_replace("#\s{4,}#", "|", $accNums))));
            $acc = array_merge($acc, $accNums);
        }
        $pax = array_unique($pax);
        $acc = array_unique($acc);

        $r = $email->add()->cruise();

        if (preg_match("#^ *{$this->opt($this->t('BOOKING'))}\s+([A-Z\d]{5,}).+\s{5,}(.+\d{4}) *$#m", $textPDF, $m)) {
            $r->general()
                ->confirmation($m[1])
                ->date(strtotime($m[2]));
        }

        if (!empty($pax)) {
            $r->general()
                ->travellers($pax);
        }

        if (!empty($acc)) {
            $r->program()
                ->accounts($acc, false);
        }

        if (preg_match("#^[ ]*All amounts are quoted in\s*(.+?)\.?(?:[ ]{2}|[ ]*$)#m", $priceBlock, $m)) {
            $r->price()->currency($this->normalizeCurrency($m[1]));
        }

        $tot = $this->getTotalCurrency($this->re("#^ *Cruise:.+?\s{3,}([\d\.,]+)$#m", $priceBlock));

        if (!empty($tot['Total'])) {
            $r->price()
                ->cost($tot['Total']);
        }
        $tot = $this->getTotalCurrency($this->re("#^ *Total Fare:.+?\s{3,}([\d\.,]+)$#m", $priceBlock));

        if (!empty($tot['Total'])) {
            $r->price()
                ->total($tot['Total']);
        }
        $tot = $this->getTotalCurrency($this->re("#^ *Taxes, Fees & Port Expenses:.+?\s{3,}([\d\.,]+)$#m",
            $priceBlock));

        if (!empty($tot['Total'])) {
            $r->price()
                ->fee('Taxes, Fees & Port Expenses', $tot['Total']);
        }

        $r->details()
            ->description($this->re("#{$this->opt($this->t('Voyage / Dest'))} ?[:]+\s*([^\n\/]+?)\s*\/#", $infoBlock))
            ->ship($this->re("#{$this->opt($this->t('Ship / Registry'))} ?[:]+\s*([^\n\/]+?)\s*\/#", $infoBlock))
            ->room($this->re("#{$this->opt($this->t('Stateroom / Cat'))} ?[:]+\s*([^\n\/]+?)\s*\/#", $infoBlock))
            ->roomClass($this->re("#{$this->opt($this->t('Stateroom / Cat'))} ?[:]+\s*[^\n\/]+\s*/\s*(.+)#", $infoBlock));

        $rows = explode("\n", $itBlock);

        if (($pos = strpos($rows[0], 'Date', 40)) !== false) {
            $itTables = $this->splitCols($itBlock, [0, $pos - 6]);
            $itTmp = [];

            foreach ($itTables as $itTable) {
                $itTmp[] = $this->re("# *Date[^\n]+\n(.+)#s", $itTable);
            }
            $it = explode("\n", implode("\n", $itTmp));
        } else {
            $itTmp = $this->re("# *Date[^\n]+\n(.+)#s", $itBlock);
            $it = explode("\n", $itTmp);
        }

        $it = array_filter(array_map(function ($s) {
            if (preg_match("#^\w{3}\s+\d+#", $s) && strpos($s, 'At Sea') === false) {
                if (count($arr = explode("|", preg_replace("#\s{4,}#", "|", $s))) < 3) {
                    return null;
                } else {
                    return $arr;
                }
            } else {
                return null;
            }
        }, $it));

        $date = strtotime($this->re("#{$this->opt($this->t('Embarkation'))}: +(.+?\d{4})#", $infoBlock));

        $anchor = false;

        foreach ($it as $seg) {
            if (3 === count($seg) && false === strpos($seg[2], 'Depart') && false === strpos($seg[2], 'Arrive')) {
                $anchor = true;
            }
        }

        if ($anchor) {
            $string = $this->re('/(Date.+Depart)[ ]+Date/', $itBlock);
            $pos = $this->rowColsPos($string);
            $pos2 = [];

            foreach ($pos as $i => $po) {
                if (0 === $i) {
                    $pos2[] = end($pos) + 5;
                } else {
                    $pos2[] = $po * 2;
                }
            }
            $pos2 = array_map(function ($el) { return $el * 2; }, $pos);
            $pos = array_merge($pos, $pos2);
//            $pos = [0, 55, 76, 86, 152, 160]; // WTF?!!
            $tt = $this->splitCols($itBlock, $pos);

            $data = [];

            foreach ($tt as $i => $t) {
                if (preg_match('/Date[ ]+Description/', $t)) {
                    $lines = explode("\n", $t);
                    $lines = array_filter(array_map(function ($el) {
                        if (
                            !empty($el) && false === strpos($el, 'No Transfer To Ship') && false === strpos($el, 'At Sea')
                            && !preg_match('/Date[ ]+Description/', $el) && preg_match('/^\w+ \d{1,2}[ ]{2,}/', $el)
                            && false === strpos($el, 'No Transfer From Ship')
                        ) {
                            return $el;
                        }

                        return null;
                    }, $lines));

                    $arrLines = explode("\n", $tt[++$i]);
                    $depLines = explode("\n", $tt[++$i]);
                    $keys = array_keys($lines);

                    foreach ($keys as $j => $key) {
                        if (!empty($arrLines[$key])) {
                            $lines[$key] .= '  Arrive  ' . $arrLines[$key];
                        }

                        if (!empty($depLines[$key])) {
                            $lines[$key] .= '  Depart  ' . $depLines[$key];
                        }
                    }
                    $data = array_merge($data, $lines);
                }
            }
        }

        // for array_search function
        $dataTemp = array_map(function ($dat) { return trim(preg_replace(['/((?:Depart|Arrive).+)/', '/\s{2,}/'], ['', ' '], $dat)); }, $data);

        foreach ($it as $ii => $seg) {
            if (3 === count($seg) && false !== ($k = array_search("{$seg[0]} {$seg[1]}", $dataTemp))) {
                if (false === strpos($seg[2], 'Depart') && false === strpos($seg[2], 'Arrive')) {
                    $it[$ii] = preg_split('/\s{2,}/', $data[$k]);
                }
            }
        }

        foreach ($it as $seg) {
            $dateSeg = EmailDateHelper::parseDateRelative($seg[0], $date);
            $s = $r->addSegment();

            if (count($seg) < 3) {
                $this->logger->debug('Wrong segment table!');

                continue;
            }

            $s->setName($seg[1]);

            switch ($seg[2]) {
                case 'Depart':
                    $s->setAboard(empty($seg[3]) ? null : strtotime($seg[3], $dateSeg));

                    break;

                case 'Arrive':
                    $s->setAshore(empty($seg[3]) ? null : strtotime($seg[3], $dateSeg));

                    break;

                case 'Full Day':
                    $s->setAboard($dateSeg);
                    $s->setAshore($dateSeg);

                    break;

                default:
                    if (!empty($seg[3])) {
                        $s->setAshore(strtotime($seg[2], $dateSeg));
                        $s->setAboard(strtotime($seg[3], $dateSeg));
                    } elseif (count($r->getSegments()) < 2) {
                        $s->setAboard(strtotime($seg[2], $dateSeg));
                    } else {
                        $s->setAshore(strtotime($seg[2], $dateSeg));
                    }

                    break;
            }
        }
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
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'NZD' => ['New Zealand Dollars'],
            'SGD' => ['Singapore Dollar'],
            'AUD' => ['Australian Dollars'],
            'CAD' => ['Canadian Dollars'],
            'USD' => ['U.S. Dollars'],
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
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['c']) ? $m['c'] : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
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
            return str_replace(' ', '\s+', preg_quote($s));
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

    private function rowColsPos($row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{4,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }
}
