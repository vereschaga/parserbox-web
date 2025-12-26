<?php

namespace AwardWallet\Engine\omega\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "omega/it-26746935.eml";

    public $reFrom = ['@omegaflightstore.com'];
    public $reBody = [
        'en' => ['Receipt No:', 'E-Ticket Notice:'],
    ];
    public $reSubject = [
        '',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'taNumbers' => ['Receipt No:', 'Booking No:'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        continue;
                    }
                    $this->parseEmailPdf($text, $email);
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

            if ((stripos($text, 'omegaflightstore.com') !== false)
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
                    if (stripos($headers["subject"], $reSubject) !== false) {
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

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $infoBlock = strstr($textPDF, $this->t('Air:'), true);
        $mainBlock = strstr($textPDF, $this->t('E-Ticket Notice:'), true);

        if (empty($infoBlock) || empty($mainBlock)) {
            $this->logger->debug('other format (blocks)');

            return false;
        }

        if (preg_match_all("#{$this->opt($this->t('PNR Ref:'))} +([A-Z\d]+)#", $textPDF, $m)) {
            $nodes = array_unique($m[1]);

            if (count($nodes) !== 1) {
                $this->logger->debug('other format pdf (pnr)');

                return false;
            } else {
                $pnr = array_shift($nodes);
            }
        }

        if (isset($pnr)) {
            $email->ota()
                ->confirmation($pnr, trim($this->t('PNR Ref:'), " :"), true);
        }

        $f = $email->add()->flight();

        if (preg_match_all("#{$this->opt($this->t('Ticket Number:'))} *(\d[\d\- ]+)\.#", $infoBlock, $m)) {
            $f->issued()
                ->tickets($m[1], false);
        }

        $descrs = (array) $this->t('taNumbers');

        foreach ($descrs as $descr) {
            $confNo = $this->re("#{$this->opt($descr)} +(\d{5,})#", $infoBlock);
            $email->ota()
                ->confirmation($confNo, trim($descr, ":"));
        }

        $nodes = explode(",", $this->re("#{$this->opt($this->t('Passenger(s):'))} +(.+)#", $infoBlock));
        $pax = array_map(function ($s) {
            return $this->re("#^\s*(.+?)\s*(?:\(|$)#", $s);
        }, $nodes);

        $f->general()
            ->noConfirmation()
            ->travellers($pax);

        if (preg_match_all("#{$this->opt($this->t('Fare'))} +([A-Z]{3}) [^\n]+ {3,}(\d[\d\.]+)\n#", $infoBlock, $m)) {
            $f->price()
                ->currency($m[1][0])
                ->cost(array_sum($m[2]));
        }

        if (preg_match_all("#{$this->opt($this->t('Airport Taxes'))} +([A-Z]{3}) [^\n]+ {3,}(\d[\d\.]+)\n#", $infoBlock,
            $m)) {
            $f->price()
                ->currency($m[1][0])
                ->tax(array_sum($m[2]));
        }

        $total = $this->re("#{$this->opt($this->t('Total for Services'))} +(\d[\d\.,]+)\n#", $textPDF);

        if (!empty($total)) {
            $f->price()
                ->total(PriceHelper::cost($total));
        } elseif (preg_match_all("#{$this->opt($this->t('Total Amount Paid to Date'))} +(\d[\d\.]+)\n#", $infoBlock,
            $m)) {
            $f->price()
                ->total(array_sum($m[1]));
        }

        $date = strtotime($this->re("#{$this->opt($this->t('Receipt Date:'))} +(.+)#", $infoBlock));

        if (empty($date)) {
            $date = strtotime($this->re("#\n *(.+) *\n *{$this->opt($this->t('Air:'))}#", $textPDF));
        }

        $segments = $this->splitter("#^( *{$this->opt($this->t('Air:'))})#m", $mainBlock);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $status = $this->re("#{$this->opt($this->t('Air:'))} .+? {$this->opt($this->t('Status:'))} (.+)#",
                $segment);

            if (!empty($status)) {
                $s->extra()
                    ->status($status);
            }

            $node = trim($this->re("#{$this->opt($this->t('Air:'))} (.+?) *(?:{$this->opt($this->t('Status:'))}|\n)#",
                $segment), " .");

            if (preg_match("#([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d+) (.+) {$this->t('to')} (.+)#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
                $s->departure()
                    ->name($m[3])
                    ->noCode();
                $s->arrival()
                    ->name($m[4])
                    ->noCode();
            }

            $pnr = $this->re("#{$this->opt($this->t('Airline Ref:'))} ([A-Z\d]{5,})#", $segment);

            if (!empty($pnr)) {
                $s->airline()
                    ->confirmation($pnr);
            }

            $duration = $this->re("#{$this->opt($this->t('Flight duration'))} (.+?)\.\n#", $segment);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $class = $this->re("#{$this->opt($this->t('Seats:'))} \d+ x ([A-Z]{1,2}) {$this->opt($this->t('Class'))}\n#",
                $segment);

            if (!empty($class)) {
                $s->extra()
                    ->bookingCode($class);
            }

            // Sat, 17 Nov at 20:40. Terminal: EM | Sat, 17 Nov at 20:40
            $node = trim($this->re("#{$this->opt($this->t('Depart:'))} (.+?)\.\n#", $segment));

            if (preg_match("#(.+ \d+:\d+)(?:\. Terminal: (.+))? *$#", $node, $m)) {
                $s->departure()->date($this->normalizeDate($m[1], date('Y', $date)));

                if (isset($m[2]) && !empty($m[2])) {
                    $s->departure()->terminal($m[2]);
                }
            }

            // Arrive: Mon, 10 Dec at 18:58. Terminal: EM (non-stop). | Arrive: Mon, 10 Dec at 18:58. (non-stop).
            $node = trim($this->re("#{$this->opt($this->t('Arrive:'))} (.+?)\.\n#", $segment));

            if (preg_match("#(.+ \d+:\d+)(?:\. Terminal: (.+?))?(?:\.? \((.+)\))? *$#", $node, $m)) {
                $s->arrival()->date($this->normalizeDate($m[1], date('Y', $date)));

                if (isset($m[2]) && !empty($m[2])) {
                    $s->arrival()->terminal($m[2]);
                }

                if (isset($m[3]) && !empty($m[3])) {
                    if ($m[3] == 'non-stop') {
                        $s->extra()->stops(0);
                    }
                }
            }
        }

        return true;
    }

    private function normalizeDate($date, $year = null)
    {
        $in = [
            //Sat, 08 Sep at 21:35
            '#^(\w+),\s+(\d+)\s+(\w+)\s+at\s+(\d+:\d+)\s*$#u',
        ];
        $out = [
            '$2 $3 ' . $year . ', $4',
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

    private function assignLang($body)
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
