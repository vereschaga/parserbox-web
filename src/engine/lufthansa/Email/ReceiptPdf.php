<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-41565802.eml";

    public $reFrom = ["lufthansa.com>"];
    public $reBody = [
        'en' => ['Itinerary Receipt', 'ItineraryReceipt'],
    ];
    public $reSubject = [
        'Lufthansa receipt',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'Flight / Flug'                                  => 'Flight / Flug',
            'Departure / Abflug'                             => 'Departure / Abflug',
            'Passenger Information / Additional Information' => [
                'Passenger Information / Additional Information',
                'PassengerInformation / Additional Information',
                'Passenger Information / Zusätzliche Kundeninformation',
            ],
            'Passenger / ItineraryReceipt' => [
                'Passenger / ItineraryReceipt',
                'Passenger / Itinerary Receipt',
            ],
            'fees' => [
                'Airline Service Fees / Airline Service Fees',
                'National/international Charge / Nationaler/Internationaler Zuschlag',
            ],
        ],
    ];
    private $keywordProv = 'Lufthansa';
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectBody($text) && $this->assignLang($text)) {
                        if (!$this->parseEmailPdf($text, $email)) {
                            return $email;
                        }
                    }
                }
            }
        }

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
        if ($this->stripos($from, $this->reFrom)) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = false;

        if (array_key_exists('from', $headers) && $this->detectEmailFromProvider($headers['from']) === true) {
            $fromProv = true;
        }

        if (array_key_exists('subject', $headers)) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || $this->stripos($headers["subject"], $this->keywordProv))
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

    private function parseEmailPdf($textPDF, Email $email): bool
    {
        // remove garbage
        $textPDF = preg_replace([
            "#^(?:.+[ ]{2})?[ ]*{$this->opt('Page / Seite')}[ ]*\d.*$#im",
            "#^[ ]*Deutsche Lufthansa.*\n+.*\d{5}.*\n+.+\d\.\d{2,4}$#im",
        ], "\n", $textPDF);

        // get Blocks and check format
        $itBlock = strstr($textPDF, $this->t('Payment details / Zahlungsinformationen'), true);

        if (empty($itBlock)) {
            $this->logger->alert("other format itBlock");

            return true;
        }

        $addBlock = $this->re("#{$this->opt($this->t('Validating data / Ausstellungsdaten'))}.*\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('Passenger Information / Additional Information'))}#", $textPDF);

        if (preg_match("#^(.+?)(?:\n+[ ]*DE: |\n{5}[ ]{0,10}\d{1,2} ?[[:upper:]].+)#su", $addBlock, $m)) {
            $addBlock = $m[1];
        }
        $addBlock = $this->splitCols($addBlock, $this->colsPos($addBlock));

        if (count($addBlock) !== 2 && count($addBlock) !== 4) {
            $this->logger->alert("other format addBlock 2");

            return true;
        }

        $fareBlock = strstr($textPDF, $this->t('Fare details / Preisberechnung'));

        if ($str = strstr($fareBlock, $this->t('Further information / Weitere Informationen'), true)) {
            $fareBlock = $str;
        }

        if ($str = strstr($fareBlock, $this->t('Important Notice / Wichtiger Hinweis'), true)) {
            $fareBlock = $str;
        }

        if (preg_match("#(.+(?:{$this->t('Taxes and Fees')}|{$this->t('Grand Total / Gesamtbetrag')})[^\n]*)#s",
            $fareBlock,
            $m)) {
            $fareBlock = $m[1];
        }

        // start parsing
        $r = $email->add()->flight();

        if ($fareBlock && $pos = mb_strlen($this->re("#(.+[ ]{3,}){$this->t('Total / Total')}#", $fareBlock))) {
            $table = $this->splitCols($fareBlock, [0, $pos]);
            $fare = $this->getTotalCurrency($this->re("#(.+)\s+{$this->t('Fare / Tarif')}#", $table[0]));

            if (!empty($fare['Total'])) {
                $r->price()
                    ->cost($fare['Total'])
                    ->currency($fare['Currency']);
            }
            $total = $this->getTotalCurrency($this->re("#(.+)\s+{$this->t('Grand Total / Gesamtbetrag')}#", $table[1]));

            if (!empty($total['Total'])) {
                $r->price()
                    ->total($total['Total'])
                    ->currency($total['Currency']);
            }
            $fees = (array) $this->t('fees');

            foreach ($fees as $fee) {
                foreach ($table as $col) {
                    $sum = $this->re("#(.+)\s+{$this->opt($fee)}#", $col);

                    if ($sum) {
                        $sum = $this->getTotalCurrency($sum);

                        if (!empty($sum['Total'])) {
                            $arr = explode(" / ", $fee);
                            $descr = trim(array_shift($arr));
                            $r->price()
                                ->fee($descr, $sum['Total']);
                        }
                    }
                }
            }

            $taxes = $this->re("#\n\n(.+)\s+{$this->opt($this->t('Taxes and Fees / Steuern und Gebühren'))}#s",
                $table[0]);

            if (preg_match_all("#\b([A-Z]{3}[ ][\d\.]+)([A-Z]+)\b#", $taxes, $m, PREG_SET_ORDER)) {
                foreach ($m as $mm) {
                    $sum = $this->getTotalCurrency($this->nice($mm[1]));

                    if (!empty($sum['Total'])) {
                        $r->price()
                            ->fee($mm[2], $sum['Total']);
                    }
                }
            }
        }

        $issueDate = $this->normalizeDate($this->re("#(.+)\s+{$this->t('Date of issue')}#", $addBlock[1]));

        if ($issueDate) {
            $this->date = $issueDate;
        }

        $info = $this->re("#{$this->opt($this->t('Passenger / ItineraryReceipt'))}\s*\n(.+?)\n\n\n#s", $itBlock);
        $table = $this->splitCols($itBlock, $this->colsPos($info));

        if (count($table) !== 3 && (count($table) !== 4 || !preg_match("/\d{3}[\-]?\d{10}/", $table[2]))) {
            $this->logger->debug("other format pax-block");

            return true;
        }

        $tickets = explode(",",
            $this->re("#^\s*([\d\-,\s]{5,})\s+{$this->opt($this->t('Ticket number / Ticketnummer'))}#u", $table[2]));

        if (!empty($tickets)) {
            $r->issued()
                ->tickets($tickets, false);
        }
        $r->general()
            ->confirmation($this->re("#^\s*([A-Z\d]{5,6})\s+{$this->opt($this->t('Booking reference / Buchungscode'))}#u",
                $table[1]))
            ->traveller(preg_replace("/\s*(Mr|Mrs|Miss|Mstr|Ms)\s*$/", '', $this->re("#(.+)\s+{$this->t('Travel data for / Reisedaten für')}#u", $table[0])), true);

        $segments = $this->splitter("#(.+\n\s*{$this->t('Flight / Flug')})#", $itBlock);

        foreach ($segments as $segment) {
            if (!preg_match("#(.+)\n\n\s*?([^\n]+\n[\s\*]*{$this->t('operated by / operated by')}.+)#s", $segment, $m)) {
                $this->logger->debug("other format segment");

                return false;
            }

            $tableUp = $m[1];
            $tableDown = $m[2];
            $s = $r->addSegment();
            $tableUp = $this->splitCols($tableUp, $this->colsPos($tableUp));

            if (count($tableUp) === 8 && strpos($tableUp[5], $this->t('To / nach')) !== false) {// FE: it-41565802.eml
                $tableUp[4] = $this->mergeCols($tableUp[4], $tableUp[5]);
                unset($tableUp[5]);
                $tableUp = array_values($tableUp);
            }

            if (count($tableUp) !== 7) {
                $this->logger->debug("other format segment table");

                return false;
            }

            if (preg_match("#^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d+)[\s\*]+{$this->t('Flight / Flug')}#",
                $tableUp[0], $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }
            $date = $this->normalizeDate($this->re("#(.+)\s*{$this->t('Date / Datum')}#s", $tableUp[1]));

            $tableDown = $this->splitCols($tableDown, $this->colsPos($tableDown));
            $s->departure()
                ->noCode()
                ->date(strtotime($this->re("#^\s*(\d+:\d+)\s+{$this->t('Departure / Abflug')}#", $tableUp[2]), $date))
                ->name($this->nice($this->re("#(.+?)\s*{$this->opt($this->t('From / von'))}#s", $tableUp[3])));
            $s->arrival()
                ->noCode()
                ->noDate()
                ->name($this->nice($this->re("#(.+?)\s*{$this->opt($this->t('To / nach'))}#s", $tableUp[4])));
            $s->extra()
                ->status($this->nice($this->re("#(.+?)\s*{$this->opt($this->t('Status / Status'))}#s", $tableUp[5])))
                ->bookingCode($this->re("#^([A-Z]{1,2})\s*{$this->opt($this->t('Class / Klasse'))}#", $tableUp[6]));

            $operator = $this->re("#^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])[\s\*]+{$this->opt($this->t('operated by / operated by'))}#",
                $tableDown[0]);

            if (!empty($operator)) {
                $s->airline()->operator($operator);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //17MAY19
            '#^\s*(\d+)\s*(\D+)\s*(\d{2})\s*$#u',
            //28.June
            '#^\s*(\d{1,2})\.\s*(\w+)\s*$#',
        ];
        $out = [
            '$1 $2 20$3',
            '$1 $2',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        if (!preg_match("#\d{4}#", $str)) {
            $str = EmailDateHelper::parseDateRelative($str, $this->date);
        } else {
            $str = strtotime($str);
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

    private function detectBody($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->stripos($body, $reBody)) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Flight / Flug'], $words['Departure / Abflug'])) {
                if (stripos($body, $words['Flight / Flug']) !== false && stripos($body,
                        $words['Departure / Abflug']) !== false
                ) {
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

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
                $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
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

    private function mergeCols($col1, $col2)
    {
        $rows1 = explode("\n", $col1);
        $rows2 = explode("\n", $col2);
        $newRows = [];

        foreach ($rows1 as $i => $row) {
            if (isset($rows2[$i])) {
                $newRows[] = $row . $rows2[$i];
            } else {
                $newRows[] = $row;
            }
        }

        if (($i = count($rows1)) > count($rows2)) {
            for ($j = $i; $j < count($rows2); $j++) {
                $newRows[] = $rows2[$j];
            }
        }

        return implode("\n", $newRows);
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
