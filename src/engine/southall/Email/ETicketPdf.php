<?php

namespace AwardWallet\Engine\southall\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "southall/it-31097525.eml";

    public $reFrom = ["@southalltravel.com"];
    public $reBody = [
        'en' => ['ELECTRONIC TICKET RECORD', 'Ticket Issue Agency'],
    ];
    public $reSubject = [
        'E tickets',
        'E TICEKT & INVOICE',
        'YOUR ETKT ATTACHED',
        'Your ticket and invoice',
    ];
    public $lang = '';
    /** @var \HttpBrowser */
    public $pdf;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [],
    ];
    private $keywordProv = ['SOUTHALL TRAVEL', 'southalltravel.com'];
    private $date;
    private $otaRefs = [];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $i => $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language in ' . $i . '-PDF');

                        continue;
                    }
                    $this->tablePdf($parser, $pdf);
//                    echo $this->pdf->Response['body'];
//                    echo "<br><br>" . "=====================================" . "<br><br>";
                    $this->parseEmailPdf($text, $email);
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

            if (((stripos($text, $this->keywordProv[0]) !== false)
                    || (stripos($text, $this->keywordProv[1]) !== false)
                    || (stripos($parser->getHTMLBody(), $this->keywordProv[0]) !== false)
                    || (stripos($parser->getHTMLBody(), $this->keywordProv[1]) !== false)
                    || (stripos($parser->getPlainBody(), $this->keywordProv[0]) !== false)
                    || (stripos($parser->getPlainBody(), $this->keywordProv[1]) !== false))
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
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || stripos($headers["subject"], $this->keywordProv[0]) !== false
                    || stripos($headers["subject"], $this->keywordProv[1]) !== false
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

    private function parseEmailPdf($textPDF, Email $email)
    {
        $r = $email->add()->flight();
        $cntPrevTR2 = $this->pdf->XPath->query("//text()[{$this->eq($this->t('Passenger Name(s)'))}]/ancestor::tr[1]/preceding-sibling::tr")->length;
        $cntPrevTR1 = $this->pdf->XPath->query("//text()[{$this->starts($this->t('E-Ticket Number'))}]/ancestor::tr[1]/preceding-sibling::tr")->length;
        $cntPax = $cntPrevTR1 - $cntPrevTR2 - 1;

        if ($cntPax > 0) {
            $pax = $this->pdf->FindNodes("//text()[{$this->eq($this->t('Passenger Name(s)'))}]/ancestor::tr[1]/following-sibling::tr[position()<={$cntPax}]/td[1]");
        }
        $otaRef = $this->pdf->FindSingleNode("//text()[{$this->starts($this->t('Booking Ref'))}]/ancestor::td[1]/following-sibling::td[1]");

        if (isset($pax)) {
            $r->general()
                ->travellers($pax);

            if (!in_array($otaRef, $this->otaRefs)) {
                $email->ota()->confirmation($otaRef, $this->t('Booking Ref') . ' (' . implode(",", $pax) . ')');
            }
        }
        $r->general()
            ->confirmation($this->pdf->FindSingleNode("(//text()[{$this->starts($this->t('Airline Ref'))}])[1]/ancestor::td[1]/following-sibling::td[1]"),
                $this->t('Airline Ref'), true)
            ->date($this->normalizeDate($this->pdf->FindSingleNode("//text()[{$this->starts($this->t('Ticket Issue Date'))}]/ancestor::td[1]/following-sibling::td[1]")));

        $r->issued()
            ->ticket($this->pdf->FindSingleNode("//text()[{$this->starts($this->t('E-Ticket Number'))}]", null, false,
                "/{$this->opt($this->t('E-Ticket Number'))}[ :]+(\d+)/"), false);

        $xpath = "//text()[{$this->eq($this->t('Flight Information'))}]/ancestor::tr[1]/following-sibling::tr[./td[1][translate(.,'0123456789','dddddddddd')='dddd']]";
        $this->logger->debug("[XPATH]: " . $xpath);
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $r->addSegment();

            $s->departure()
                ->noCode()
                ->name($this->pdf->FindSingleNode("./following-sibling::tr[1]/td[1]", $root))
                ->terminal($this->pdf->FindSingleNode("./following-sibling::tr[1]/td[2]", $root), true);

            $s->arrival()
                ->noCode()
                ->name($this->pdf->FindSingleNode("./following-sibling::tr[1]/td[3]", $root))
                ->terminal($this->pdf->FindSingleNode("./following-sibling::tr[1]/td[4]", $root), true);

            // parser Dates
            $timeDep = $this->pdf->FindSingleNode("./td[1]", $root);

            if ($this->pdf->XPath->query("./following-sibling::tr[1]/td", $root)->length === 6
                && $this->pdf->XPath->query("./td", $root)->length === 2
            ) {
                $timeArr = $this->pdf->FindSingleNode("./td[2]", $root);
                $s->extra()->status($this->pdf->FindSingleNode("./following-sibling::tr[1]/td[6]", $root));
            } else {
                $timeArr = $this->pdf->FindSingleNode("./following-sibling::tr[1]/td[5]", $root);
                $s->extra()->status($this->pdf->FindSingleNode("./following-sibling::tr[1]/td[7]", $root));
            }
            $dateDep = $this->pdf->FindSingleNode("./following-sibling::tr[2]/td[1]", $root);
            $nextDay = !empty($this->pdf->FindSingleNode("./following-sibling::tr[2]/td[2][{$this->contains($this->t('Next Day'))}]",
                $root));
            $s->departure()
                ->date($this->normalizeDate($dateDep . ', ' . $timeDep));
            $s->arrival()
                ->date($this->normalizeDate($dateDep . ', ' . $timeArr));

            if ($nextDay) {
                $s->arrival()->date(strtotime("+1 day", $s->getArrDate()));
            }

            // parse airline|flight info
            $node = $this->pdf->FindSingleNode("./following-sibling::tr[3]/td[1]", $root);

            if (preg_match("/^{$this->opt($this->t('Airline'))}[ :]+.*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)/", $node,
                $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $node = $this->pdf->FindSingleNode("./following-sibling::tr[3]/td[2]", $root);

            if (preg_match("/^{$this->opt($this->t('Airline Ref. No'))}[ :]+([A-Z\d]{5,6})$/", $node, $m)) {
                $s->airline()
                    ->confirmation($m[1]);
            }

            $node = $this->pdf->FindSingleNode("./following-sibling::tr[3]/td[3]", $root);

            if (preg_match("/^{$this->opt($this->t('Stop'))}[ :]+(\d+)$/", $node, $m)) {
                $s->extra()
                    ->status($m[1]);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Tuesday , February 6
            '#^([\-\w]+)\s*,\s+(\w+)\s+(\d+)$#u',
            //Thu , March 08, 1035
            '#^([\-\w]+)\s*,\s+(\w+)\s+(\d+),\s+(\d{2})(\d{2})$#u',
        ];
        $out = [
            '$3 $2 ' . $year,
            '$3 $2 ' . $year . ', $4:$5',
        ];
        $outWeek = [
            '$1',
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
                if (mb_stripos($body, $reBody[0]) !== false && mb_stripos($body, $reBody[1]) !== false) {
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function tablePdf(\PlancakeEmailParser $parser, int $num)
    {
        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($num), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetBody($html);
        $html = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $grid = [];
            $prevTop = null;

            foreach ($nodes as $node) {
                $text = $this->pdf->FindSingleNode(".", $node);
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");

                if (isset($prevTop) && abs($prevTop - $top) < 2) {
                    $top = $prevTop;
                }
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $grid[$top][$left] = $text;
                $prevTop = $top;
            }

            ksort($grid);

            $html .= "<table border='1'>";

            foreach ($grid as $row => $c) {
                ksort($c);
                $html .= "<tr>";

                foreach ($c as $col) {
                    $html .= "<td>" . $col . "</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</table>";
        }
        $this->pdf->SetBody($html);

        return true;
    }
}
