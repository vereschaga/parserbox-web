<?php

namespace AwardWallet\Engine\supersaver\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Travelstart extends \TAccountChecker
{
    public $mailFiles = "supersaver/it-33277666.eml, supersaver/it-33704613.eml, supersaver/it-53828975.eml";

    public $reFrom = ["noreply@travelstart.ae"];
    public $reBody = [
        'en' => ['Thank you for booking your flight with Travelstart'],
    ];
    public $reSubject = [
        'Travelstart - Your Ticket for Booking',
    ];
    public $lang = '';

    public $otaConfOld = [];
    public $otaConf;
    public $otaConfDesc;

    public $travelOld = [];
    public $travel = [];

    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'Passengers'     => 'Passengers',
            'Itinerary'      => 'Itinerary',
            '/regReference/' => "#Your (Travelstart.+?reference): ([\w\-]+)#",
        ],
    ];
    private $keywordProv = 'Travelstart';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = 'Html';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->assignLang($text)) {
                        if ($this->parseEmailPdf($text, $email)) {
                            $type = 'Pdf';
                        }
                        $parsed = true;
                    }
                }
            }
        }

        if (!isset($parsed)) {
            if ($this->assignLang()) {
                if (!$this->parseEmailHtml($email)) {
                    return null;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.travelstart.')] | //img[contains(@src,'.travelstart.')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0 && $this->assignLang()) {
                    return true;
                }
            }
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, $this->keywordProv) !== false)) {
                foreach ($this->reBody as $lang => $reBody) {
                    if (($this->stripos($text, $reBody) !== false) && $this->assignLang($text)) {
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

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
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
        $formats = 2; // html | pdf
        $cnt = $formats * count(self::$dict);

        return $cnt;
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

    private function parseEmailPdf($textPDF, Email $email)
    {
        $itBlock = $this->findСutSection($textPDF, $this->t('Itinerary'), $this->t('Passengers'));

        if (empty($itBlock)) {
            return false;
        }

        //Delete junk
        $itBlock = preg_replace('/Baggage\s*for\s*.+\n\n.+traveller\n.+traveller/', '', $itBlock);

        //$segments = $this->splitter('#^([ ]*.+? ' . $this->t('to') . ' .+\n)#m', "CtrlStr\n" . $itBlock);
        $segments = $this->splitter('#(Check[-]in\sreference:.+)#m', "Check-in reference: VSTVFK/\n" . $itBlock);

        if (count($segments) === 0) {
            return false;
        }

        if (preg_match($this->t('/regReference/'), $textPDF, $m)) {
            $this->otaConf = $m[2];
            $this->otaConfDesc = $m[1];
        }

        $node = $this->re("#Passengers(.+?)(?:\n\n|Kind)#s", $textPDF);

        if (preg_match_all("#^(.+?)\(#m", $node, $m)) {
            $this->travel = $m[1];
        }

        if (in_array($this->otaConf, $this->otaConfOld, true)) {
            foreach ($this->travelOld as $travelArray) {
                if ($this->travel === $travelArray) {
                    return true;
                }
            }
        }

        $r = $email->add()->flight();

        $r->ota()
                ->confirmation($this->otaConf, $this->otaConfDesc);
        $this->otaConfOld[] = $this->otaConf;

        $r->general()
                ->travellers(preg_replace("/^(?:Mrs|Mr|Ms)/", "", $this->travel));
        $this->travelOld[] = $this->travel;

        $r->general()
            ->noConfirmation()
            ->date(strtotime($this->re("#Booked on: (.+)#", $textPDF)));

        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total amount'))}]", null, false,
            "#{$this->opt($this->t('Total amount'))}:(.+)#");

        if (!empty($node)) {
            $total = $this->getTotalCurrency($node);
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }

        if (preg_match("#Ticket number:\s*(.+)#", $textPDF, $m)) {
            $arr = explode(",", $m[1]);

            if (count($arr) > 0) {
                $r->issued()
                    ->tickets(array_filter(array_map("trim", $arr)), false);
            } elseif (!empty($m[1])) {
                $r->issued()
                    ->ticket(trim($m[1]), false);
            }
        }

        foreach ($segments as $i => $segment) {
            $s = $r->addSegment();
            $text = $this->re("#[^\n]+\n(.+)#s", $segment);
            $header = $this->re("#([^\n]+)\n.+#s", $segment);
            $points = array_map("trim", explode(' ' . $this->t('to') . ' ', $header));

            if (count($points) !== 2) {
                //$this->logger->debug('check points. segment ' . $i);
            }
            $confNo = $this->re("#{$this->opt($this->t('Check-in reference'))}:\s+([A-Z\d]{5,})#", $text);

            if ($confNo) {
                $s->airline()
                    ->confirmation($confNo);
                $text = strstr($text, 'Check-in reference', true);
            }
            $table = $this->splitCols($text, $this->colsPos($text, 15));

            if (count($table) !== 3) {
                //$this->logger->debug('other format segment ' . $i);

                return false;
            }

            $junk = $this->re("/(.+\s+to\s+.*\n)/", $table[0]);

            if (!empty($junk)) {
                $table[0] = preg_replace("/^(.+$junk)/su", "", $table[0]);
            }

            if (preg_match("#\n?\n?\n?(?<name>.+\n*.*)\n\D+\s?(?<date>\d+\s*\w+\s*\d{4}[,]?\s*[\d:]+)$#mu", $table[0], $m)) {
                $s->departure()
                    ->noCode()
                    ->name($this->nice($m['name']))
                    ->date(strtotime($m['date']));
            }

            if (preg_match("#.+(?:\s|\n)(?<airline>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<flight>\d+)\s*(?<cabin>.+)?\s*$#", $table[1],
                $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flight']);

                if (isset($m['cabin']) && !empty($m['cabin'])) {
                    $s->extra()->cabin($m['cabin']);
                }
            }

            if (preg_match("#\n?\n?\n?(?<name>.+\n*.*)\n\D+\s?(?<date>\d+\s*\w+\s*\d{4}[,]?\s*[\d:]+)#m", $table[2], $m)) {
                $s->arrival()
                    ->noCode()
                    ->name($this->nice($m['name']))
                    ->date(strtotime($m['date']));
            }
        }

        return true;
    }

    private function parseEmailHtml(Email $email)
    {
        $node = $this->http->FindSingleNode("//text()[({$this->starts($this->t('Your Travelstart'))}) and ({$this->contains($this->t('reference'))})]");

        if (preg_match($this->t('/regReference/'), $node, $m)) {
            $email->ota()
                ->confirmation($m[2], $m[1]);
        }
        $r = $email->add()->flight();

        $r->general()
            ->noConfirmation()
            ->travellers(preg_replace("/^(?:Mrs|Mr|Ms)/", "", $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::tr[1]/following-sibling::tr[count(.//td)=1 and contains(.,'(') and substring(normalize-space(),string-length(normalize-space()))=')']",
                null, "#(.+)\s*\(#")));

        $dateReserv = strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booked on'))}]", null,
            false, "#:\s*(.+)#"));

        if (!empty($dateReserv)) {
            $r->general()
                ->date($dateReserv);
        }

        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total amount'))}]", null, false,
            "#{$this->opt($this->t('Total amount'))}:(.+)#");

        if (!empty($node)) {
            $total = $this->getTotalCurrency($node);
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }

        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";
        $roots = $this->http->XPath->query("//text()[{$ruleTime}]/ancestor::tr[1][count(./descendant::text()[{$ruleTime}])=2]");

        foreach ($roots as $root) {
            $s = $r->addSegment();

            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("./td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][2]",
                    $root))
                ->date(strtotime($this->http->FindSingleNode("./td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][3]",
                    $root)));
            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("./td[normalize-space()!=''][3]/descendant::text()[normalize-space()!=''][2]",
                    $root))
                ->date(strtotime($this->http->FindSingleNode("./td[normalize-space()!=''][3]/descendant::text()[normalize-space()!=''][3]",
                    $root)));
            $node = $this->http->FindSingleNode("./td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][1]",
                $root);

            if (preg_match("#.+ (?<airline>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<flight>\d+)#", $node, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flight']);
            }
            $node = $this->http->FindSingleNode("./td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][2]",
                $root);

            if (!empty($node)) {
                $s->extra()->cabin($node);
            }

            $node = $this->http->FindSingleNode("./following::tr[normalize-space()!=''][position()<3][{$this->starts($this->t('Operated by'))}][1]",
                $root, false, "#{$this->opt($this->t('Operated by'))}:\s*(.+)#");

            if (!empty($node)) {
                $s->airline()->operator($node);
            }
            $node = $this->http->FindSingleNode("./following::tr[normalize-space()!=''][position()<3][{$this->starts($this->t('Check-in reference'))}][1]",
                $root, false, "#{$this->opt($this->t('Check-in reference'))}:\s*([A-Z\d]{5,})#");

            if (!empty($node)) {
                $s->airline()->confirmation($node);
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($text = null)
    {
        if (isset($text)) {
            foreach (self::$dict as $lang => $words) {
                if (isset($words["Passengers"], $words["Itinerary"])) {
                    if ($this->stripos($text, $words['Passengers']) !== false && $this->stripos($text,
                            $words['Itinerary']) !== false
                    ) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        } else {
            foreach (self::$dict as $lang => $words) {
                if (isset($words["Passengers"], $words["Itinerary"])) {
                    if ($this->http->XPath->query("//*[{$this->contains($words['Passengers'])}]")->length > 0
                        && $this->http->XPath->query("//*[{$this->contains($words['Itinerary'])}]")->length > 0
                    ) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
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

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
