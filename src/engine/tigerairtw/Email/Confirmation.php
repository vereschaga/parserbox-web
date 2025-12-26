<?php

namespace AwardWallet\Engine\tigerairtw\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "tigerairtw/it-241923417.eml, tigerairtw/it-243760428-2.eml, tigerairtw/it-243760428.eml, tigerairtw/it-247931505.eml, tigerairtw/it-806981126.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            // "Booking Reference:" => '', // + html
            // "Issue Date:" => '', // + html
            // "Passenger name:" => '', // only html
            // "Booking Status:" => '',
            // "Price Summary" => '',
            // "Fare" => '',
            // "total" => '',
            // "Total amount" => '',
            // "Passenger(s) information" => '',
            // "Contact Information" => '',
        ],
        'zh' => [
            "Booking Reference:"       => '訂位代號:', // + html
            "Issue Date:"              => '訂位日期:', // + html
            "Passenger name:"          => '旅客姓名', // only html
            "Booking Status:"          => '訂位狀態:',
            "Price Summary"            => '價格摘要',
            "Fare"                     => '票價',
            "total"                    => '小計',
            "Total amount"             => '總計金額',
            "Passenger(s) information" => '詳細訊息',
            "Contact Information"      => '聯絡資料',
        ],
    ];

    private $detectFrom = ".tigerairtw.com";
    private $detectSubject = [
        'Tigerair Taiwan Confirmation',
        '台灣虎航行程確認單',
    ];

    private $detectBody = [
        'en' => [
            'Flight Details',
        ],
        'zh' => [
            '航班資訊',
        ],
    ];

    private $detectBodyPdf = [
        'en' => [
            'Price Summary',
        ],
        'zh' => [
            '價格摘要',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && stripos($headers["subject"], 'Tigerair Taiwan') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.tigerairtw.com'], '@href')}]")->length > 0
            || $this->http->XPath->query("//*[{$this->contains(['choosing Tigerair Taiwan', '感謝您選擇台灣虎航'])}]")->length > 0
        ) {
            foreach ($this->detectBody as $detectBody) {
                if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                    return true;
                }
            }
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'Tigerair Taiwan') === false) {
                continue;
            }

            foreach ($this->detectBodyPdf as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($text, $dBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = '';
        $foundPdf = false;
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'Tigerair Taiwan') === false
                && mb_strpos($text, '台灣虎航') === false
            ) {
                continue;
            }

            foreach ($this->detectBodyPdf as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (mb_strpos($text, $dBody) !== false) {
                        $type = 'Pdf';
                        $foundPdf = true;
                        $this->lang = $lang;
                        $this->parseEmailPdf($email, $text);
                        // $this->logger->debug('$text = '.print_r( $text,true));
                        break 2;
                    }
                }
            }
        }

        if ($foundPdf === false) {
            foreach ($this->detectBody as $lang => $detectBody) {
                if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                    $this->lang = $lang;

                    break;
                }
            }
            $type = 'Html';
            $this->parseEmailHtml($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

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

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t("Booking Reference:"))}]",
                null, true, "/:\s*([A-Z\d]{5,7})$/"))
            ->travellers(preg_replace("/^\s*(Ms|Mr|Mrst|Mrs|Dr)\s+/i", "",
                $this->http->FindNodes("//text()[{$this->eq($this->t("Passenger name:"))}]/ancestor::table[1][descendant::tr[not(.//tr)][normalize-space()][1][{$this->eq($this->t("Passenger name:"))}]]/descendant::tr[not(.//tr)][normalize-space()][position() > 1]")))
            ->date(strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t("Issue Date:"))}]",
                null, true, "/:\s*(.+)$/")))
        ;

        // Segments
        $xpath = "//text()[contains(translate(normalize-space(.),'0123456789','dddddddddd'),'dd:dd')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if (substr_count($root->nodeValue, ':') != 2) {
                continue;
            }
            $s = $f->addSegment();

            // Airline
            $node = $this->http->FindSingleNode("preceding::tr[not(.//tr)][normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5})\s*$/", $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            $node = implode("\n", $this->http->FindNodes("preceding::tr[not(.//tr)][normalize-space()][2]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<dCode>[A-Z]{3})\s+(?<dName>.+)\s*\n\s*(?<aCode>[A-Z]{3})\s+(?<aName>.+)\s*$/", $node, $m)) {
                if (preg_match("/(.+) - (.*\bterminal\b.*)/i", $m['dName'], $mt)) {
                    $m['dName'] = $mt[1];
                    $s->departure()
                        ->terminal(preg_replace("/\s*Terminal\s*/i", '', $mt[2]));
                }
                $s->departure()
                    ->code($m['dCode'])
                    ->name($m['dName']);

                if (preg_match("/(.+) - (.*\bterminal\b.*)/i", $m['aName'], $mt)) {
                    $m['aName'] = $mt[1];
                    $s->arrival()
                        ->terminal(preg_replace("/\s*Terminal\s*/i", '', $mt[2]));
                }
                $s->arrival()
                    ->code($m['aCode'])
                    ->name($m['aName']);
            }

            $node = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<dTime>\d{2}:\d{2})\s*-\s*(?<aTime>\d{2}:\d{2})(?<overnight>\s*[-+] ?\d)?\s+(?<date>.+)\s*$/", $node, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['dTime']));
                $s->arrival()
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['aTime']));

                if ($s->getArrDate() && !empty($m['overnight'])) {
                    $s->arrival()->date(strtotime(trim($m['overnight']) . " days", $s->getArrDate()));
                }
            }
        }

        return true;
    }

    private function parseEmailPdf(Email $email, $text)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re("/{$this->opt($this->t("Booking Reference:"))}:* *([A-Z\d]{5,7})(?:\s{3,}|\n)/u", $text))
            ->date(strtotime($this->re("/{$this->opt($this->t("Issue Date:"))}:* *(.+?)(?:\s{3,}|\n)/u", $text)))
            ->status(trim($this->re("/{$this->opt($this->t("Booking Status:"))}:* *(.+?)(?:\s{3,}|\n)/u", $text), ':'))
        ;

        $travellers = [];
        $travellersText = $this->re("/\n *{$this->opt($this->t("Passenger(s) information"))}.*\n((?:.*\n+)+) *{$this->opt($this->t("Contact Information"))}/", $text);

        if (preg_match_all("/^ *(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d{1,5} *(?:MS|MR|MSTR|MRS|DR) +(.+?) {2,}/m", $travellersText, $m)) {
            $travellers = array_unique($m[1]);
        }
        $f->general()
            ->travellers($travellers, true);

        // Program
        if (!empty($travellers) && preg_match_all("/.* (?<name>{$this->opt($travellers)})\b.*\n.*\((?<title>member ID) (?<number>[\dA-Z]{5,})\)/m", $travellersText, $m)) {
            foreach ($m[0] as $i => $v) {
                if (!in_array($m['number'][$i], array_column($f->getAccountNumbers(), 0))) {
                    $f->program()
                        ->account($m['number'][$i], true, $m['name'][$i], $m['title'][$i]);
                }
            }
        }

        $pricePos = mb_strlen($this->re("/\n(.*) {$this->opt($this->t("Price Summary"))}/u", $text));

        if (empty($pricePos)) {
            return false;
        }

        // Segments
        $itineraryText = mb_substr($text, 0, $this->strposAll($text, $this->t("Passenger(s) information")));
        $segments = $this->split("/\n( {0,10}[A-Z]{3} \S.+? {3,}[A-Z]{3} \S.+?)/u", $itineraryText);
        // $this->logger->debug('$segments = '.print_r( $segments,true));
        foreach ($segments as $sText) {
            $sText = preg_replace("/^(.{{$pricePos}})(.+)/mu", '$1', $sText);
            // $this->logger->debug('$sText = '.print_r( $sText,true));

            $s = $f->addSegment();

            // Airline
            $airportsText = '';

            if (preg_match("/^(?<airportsText>[\S\s]+?)\n {10,}(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5})\s*\n\s*\d+:\d+/u", $sText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
                $airportsText = $m['airportsText'];
            }

            $airports = $this->splitCols($airportsText, $this->TableHeadPos($this->inOneRow($airportsText)));
            $airports = preg_replace("/\s*\n\s*/", ' ', $airports);

            if (count($airports) == 2) {
                $re = "/^\s*(?<code>[A-Z]{3})\s+(?<name>.+?)(?:\s+-\s+(?<terminal>.*Terminal.*))?$/ui";

                if (preg_match($re, $airports[0], $m)) {
                    $s->departure()
                        ->code($m['code'])
                        ->name($m['name'])
                        ->terminal(preg_replace("/Terminal/", '', $m['terminal'] ?? null), true, true);
                }

                if (preg_match($re, $airports[1], $m)) {
                    $s->arrival()
                        ->code($m['code'])
                        ->name($m['name'])
                        ->terminal(preg_replace("/Terminal/", '', $m['terminal'] ?? ''), true, true);
                }
            }

            if (preg_match("/\n +(?<dTime>\d{2}:\d{2}) *- *(?<aTime>\d{2}:\d{2}) *(?<overnight> [-+] ?\d)? *\n *(?<date>.+)\n/u", $sText, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['dTime']));
                $s->arrival()
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['aTime']));

                if ($s->getArrDate() && !empty($m['overnight'])) {
                    $s->arrival()->date(strtotime(trim($m['overnight']) . " days", $s->getArrDate()));
                }
            }

            if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber())
                && preg_match_all("/^ *{$s->getAirlineName()}{$s->getFlightNumber()} *(?:MS|MR|MSTR|MRS|DR) +(?<name>(\S ?)+?) {2,}(?:\S ?)+? {2,}(?:\S ?)+? {2,}(?<seat>\d{1,3}[A-Z])\b/m", $travellersText, $m)
            ) {
                foreach ($m[0] as $i => $v) {
                    $s->extra()
                        ->seat($m['seat'][$i], true, true, $m['name'][$i]);
                }
            }
        }

        $priceText = mb_substr($text, 0, 300 + $this->strposAll($text, $this->t("Total amount")));
        $priceText = mb_substr($priceText, $this->strposAll($priceText, $this->t("Price Summary")));
        $priceText = preg_replace(["/^.{0,{$pricePos}}$/mu", "/^.{{$pricePos}}(.+)/mu"], ['', '$1'], $priceText);

        $total = $this->re("/\n\s*{$this->opt($this->t("Total amount"))}\s*(.+)/u", $priceText);

        if (preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d., ]*)\s*$/u", $total, $matches)
            || preg_match("/^\s*(?<amount>\d[\d., ]*?)\s*(?<currency>[A-Z]{3})\s*$/u", $total, $matches)
        ) {
            $currency = $matches['currency'];
            $f->price()
                ->currency($currency)
                ->total(PriceHelper::parse($matches['amount'], $currency));

            $totalPartsText = $this->re("/^([\s\S]+)\n\s*{$this->opt($this->t("Total amount"))}/", $priceText);
            $totalParts = $this->split("/(?:^|\n)( {0,2}\S+)/", $totalPartsText);
            $totalParts = preg_replace("/\s*\n\s*/", '', $totalParts);

            $fare = 0.0;
            $fees = [];

            foreach ($totalParts as $row) {
                if (!preg_match("/\d/", $row)) {
                    continue;
                }

                if (preg_match("/^\s*{$this->opt($this->t("total"))} {2,}.+/", $row, $m)) {
                    continue;
                }

                if (isset($fare) && preg_match("/^\s*{$this->opt($this->t("Fare"))} {2,}(.+)/", $row, $mf)) {
                    if ($mf[1] && (preg_match("/^\s*{$currency}\s*(?<amount>\d[\d., ]*)\s*$/", $mf[1], $m)
                        || preg_match("/^\s*(?<amount>\d[\d., ]*?)\s*{$currency}\s*$/", $mf[1], $m))
                    ) {
                        $fare += PriceHelper::parse($m['amount'], $currency);

                        continue;
                    } else {
                        unset($fare);
                    }
                }

                if (isset($fees) && preg_match("/^\s*(.+?) {2,}(.+)/", $row, $mf)) {
                    if ($mf[1] && (preg_match("/^\s*{$currency}\s*(?<amount>\d[\d., ]*)\s*$/", $mf[2], $m)
                        || preg_match("/^\s*(?<amount>\d[\d., ]*?)\s*{$currency}\s*$/", $mf[2], $m))
                    ) {
                        $fees[] = ['name' => $mf[1], 'charge' => PriceHelper::parse($m['amount'], $currency)];

                        continue;
                    } else {
                        unset($fees);
                    }
                }
            }

            if (isset($fare)) {
                $f->price()
                    ->cost($fare);
            }

            if (isset($fees)) {
                foreach ($fees as $fee) {
                    $f->price()
                        ->fee($fee['name'], $fee['charge']);
                }
            }
        } else {
            $f->price()
                ->total(null);
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            //            // 31 January 2023 (Tue), 15:00
            '/^\s*(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s*\([[:alpha:]]+\)[\s,]*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('date replace = ' . print_r( $date, true));
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }
//        $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
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

    private function TableHeadPos($row)
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

    private function SplitCols($text, $pos = false, $isTrim = true)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $text = mb_substr($row, $p, null, 'UTF-8');

                if ($isTrim) {
                    $text = trim($text);
                }
                $cols[$k][] = $text;
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
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

    private function strposAll($text, $needle)
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                $pos = mb_strpos($text, $n);

                if ($pos !== false) {
                    return $pos;
                }
            }
        } elseif (is_string($needle)) {
            return mb_strpos($text, $needle);
        }

        return false;
    }
}
