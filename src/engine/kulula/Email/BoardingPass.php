<?php

namespace AwardWallet\Engine\kulula\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "kulula/it-40476582.eml, kulula/it-40826895.eml";

    public $reFrom = ["noreply.check-in@kulula.com"];
    public $reBody = [
        'en' => ['Boarding Pass', 'kulula flight'],
    ];
    public $reSubject = [
        'Boarding Pass - Flight from',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            // Html
            'Booking reference:' => 'Booking reference:',
            'Travellers:'        => ['Travellers:', 'Travelers:'],
            // Pdf
            'Passenger:' => 'Passenger:',
            'Flight:'    => 'Flight:',
        ],
    ];
    private $keywordProv = 'kulula';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = 'html';

        if ($this->assignLang()) {
            $this->parseEmail($email, $parser);
        } else {
            $this->logger->debug('can\'t determine a language [body]');
        }

        if (count($email->getItineraries()) === 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

            if (isset($pdfs) && count($pdfs) > 0) {
                foreach ($pdfs as $pdf) {
                    if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                        if ($this->detectBody($text) && $this->assignLang($text)) {
                            $this->parseEmailPdf($text, $email);
                            $type = 'pdf';
                        }
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($type) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'Kulula') or contains(@src,'.sabre.com')]")->length > 0
            && $this->detectBody($parser->getHTMLBody())
        ) {
            return $this->assignLang();
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, $this->keywordProv) !== false)
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
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) !== false)
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
        $formats = 2; // pdf | html
        $cnt = $formats * count(self::$dict);

        return $cnt;
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $bps = $this->splitter("#(Agent copy)#", "CtrlStr\n" . $textPDF);

        foreach ($bps as $bp) {
            $r = $email->add()->flight();
            $text = strstr($bp, $this->t('Checked Bags:'), true);

            if (empty($text)) {
                $this->logger->debug("other format pdf bp");

                return false;
            }
            $s = $r->addSegment();
            $s->extra()
                ->cabin($this->re("#\n\s*(.+)[ ]+{$this->t('BOARDING PASS')}#", $text));

            $table = $this->re("#{$this->t('BOARDING PASS')}(.+)\n\s*{$this->t('Flight:')}#s", $text);
            $pos[] = 0;

            if (preg_match("#\n(.+?[ ]{3,})(.+? {$this->t('to')} .+)#", $table, $m)) {
                $pos[] = mb_strlen($m[1]);

                if (preg_match("#\n(.+?[ ]{3,})({$this->t('eTicket:')})#", $table, $m)) {
                    $pos[] = mb_strlen($m[1]);
                }
            }

            if (count($pos) !== 3) {
                $this->logger->debug("other format pdf info");

                return false;
            }
            $tableInfo = $this->splitCols($table, $pos);
            $table = $this->re("#\n([ ]*{$this->t('Flight:')}.+)#s", $text);
            $pos = $this->colsPos($table);

            if (count($pos) !== 4) {
                $this->logger->debug("other format pdf info-detail");

                return false;
            }
            $tableInfoDet = $this->splitCols($table, $pos);

            $r->general()
                ->confirmation($this->re("#{$this->opt($this->t('Booking Ref(PNR):'))}\s+([A-Z\d]{5,6})#",
                    $tableInfo[2]))
                ->traveller($this->nice($this->re("#{$this->t('Passenger:')}[:\s]+(.+)#s", $tableInfo[0])), true);
            $r->issued()
                ->ticket($this->re("#{$this->opt($this->t('eTicket:'))}\s+([\d \-]+)#", $tableInfo[2]), false);

            if (preg_match("#([A-Z]{3})[ ]{2,}([A-Z]{3})\s+(.+)[ ]{$this->t('to')}[ ](.+?)\s+(\d+ \w+ \d{4})#s",
                $tableInfo[1], $m)) {
                $s->departure()
                    ->code($m[1])
                    ->name($this->nice($m[3]));
                $s->arrival()
                    ->noDate()
                    ->code($m[2])
                    ->name($this->nice($m[4]));
                $date = trim($m[5]);
            }

            if (preg_match("#^{$this->t('Flight:')}[:\s]*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", trim($tableInfoDet[0]),
                $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match("#^{$this->t('Terminal/Gate:')}[:\s]*(.*)\s*\/(.*)$#", trim($tableInfoDet[1]), $m)
                && !empty($m[1])
            ) {
                $s->departure()->terminal($m[1]);
            }

            if (isset($date)
                && preg_match("#{$this->t('Departure Time:')}[:\s]*(\d+:\d+)$#", trim($tableInfoDet[2]), $m)
                && !empty($m[1])
            ) {
                $s->departure()->date($this->normalizeDate($date . ' ' . $m[1]));
            }

            if (preg_match("#{$this->t('Seat:')}[:\s]*(\d+[A-z])$#", trim($tableInfoDet[3]), $m)) {
                $s->extra()->seat($m[1]);
            }
        }

        return true;
    }

    private function parseEmail(Email $email, \PlancakeEmailParser $parser)
    {
        $xpath = "//text()[{$this->starts($this->t('Itinerary details for'))}]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug("segments not found. try by pdf");

            return true;
        }
        $r = $email->add()->flight();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference:'))}]/following::text()[normalize-space()!=''][1]"),
                trim($this->t('Booking reference:'), ":"), true)
            ->travellers(array_map("trim", explode(",",
                $this->http->FindSingleNode("//text()[{$this->eq($this->t('Travellers:'))}]/following::text()[normalize-space()!=''][1]"))),
                true);

        foreach ($nodes as $root) {
            $s = $r->addSegment();
            $node = $this->http->FindSingleNode("./following::text()[normalize-space()!=''][1]", $root);

            if (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $node = $this->http->FindSingleNode("./following::text()[normalize-space()!=''][2]", $root);

            if (preg_match("#^[: ]*(.+)\s+\(([A-Z]{3})\)[ ]*\-[ ]*(.+)\s+\(([A-Z]{3})\)$#", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
                $s->arrival()
                    ->name($m[3])
                    ->code($m[4]);
            }
            $infoRoot = $this->http->XPath->query("./following::tr[normalize-space()!=''][1][{$this->contains($this->t('Departs'))}]",
                $root);

            if ($infoRoot->length == 1) {
                $infoRoot = $infoRoot->item(0);
            } else {
                $this->logger->debug("other format [body]");

                return false;
            }
            $node = implode("\n", $this->http->FindNodes("./td[1]//text()[normalize-space()!='']", $infoRoot));

            if (preg_match("#{$this->opt($this->t('Departs'))}\s+(.+)\s+{$this->opt($this->t('Arrives'))}\s+(.+)#s",
                $node, $m)) {
                $s->departure()->date($this->normalizeDate($m[1]));
                $s->arrival()->date($this->normalizeDate($m[2]));
            }
            $node = $this->http->FindSingleNode("./td[2]//text()[normalize-space()!=''][2]", $infoRoot, false,
                "#(.*?)\/.*#");

            if (!empty($node)) {
                $s->departure()->terminal($node);
            }
        }

        if ($nodes->length === 1) {
            $this->parseBoardingPass($email, $r);

            if (count($r->getTravellers()) === 1) {
                // get adding info from pdf, if other format => return true, just stop parsing from pdf
                $s = $r->getSegments()[0];
                $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

                if (isset($pdfs) && count($pdfs) == 1) {
                    if (($text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]))) !== null) {
                        if ($this->detectBody($text) && $this->assignLang($text)) {
                            $text = strstr($text, $this->t('Checked Bags:'), true);

                            if (empty($text)) {
                                return true;
                            }
                            $table = $this->re("#{$this->t('BOARDING PASS')}(.+)\n\s*{$this->t('Flight:')}#s", $text);
                            $pos[] = 0;

                            if (preg_match("#\n(.+?[ ]{3,})(.+? {$this->t('to')} .+)#", $table, $m)) {
                                $pos[] = mb_strlen($m[1]);

                                if (preg_match("#\n(.+?[ ]{3,})({$this->t('eTicket:')})#", $table, $m)) {
                                    $pos[] = mb_strlen($m[1]);
                                }
                            }

                            if (count($pos) !== 3) {
                                return true;
                            }
                            $tableInfo = $this->splitCols($table, $pos);
                            $table = $this->re("#\n([ ]*{$this->t('Flight:')}.+)#s", $text);
                            $pos = $this->colsPos($table);

                            if (count($pos) !== 4) {
                                return true;
                            }
                            $tableInfoDet = $this->splitCols($table, $pos);

                            $confNo = $this->re("#{$this->opt($this->t('Booking Ref(PNR):'))}\s+([A-Z\d]{5,6})#",
                                $tableInfo[2]);

                            if ($confNo === $r->getPrimaryConfirmationNumberKey()) {
                                $s->extra()
                                    ->cabin($this->re("#\n\s*(.+)[ ]+{$this->t('BOARDING PASS')}#", $text));

                                if (preg_match("#{$this->t('Seat:')}[:\s]*(\d+[A-z])$#", trim($tableInfoDet[3]), $m)) {
                                    $s->extra()->seat($m[1]);
                                }
                                $r->issued()
                                    ->ticket($this->re("#{$this->opt($this->t('eTicket:'))}\s+([\d \-]+)#",
                                        $tableInfo[2]), false);
                            }
                        }
                    }
                }
            }
        }

        return true;
    }

    private function parseBoardingPass(Email $email, Flight $f)
    {
        $xpath = "//text()[{$this->starts($this->t('Boarding pass for'))}]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $img = str_replace(' ', '%20',
                $this->http->FindSingleNode("./ancestor::div[1]/following-sibling::div[.//img][1]//img/@src", $root,
                    false, "#^https?:\/\/.+#"));

            if (empty($img)) {
                continue;
            }
            $bp = $email->add()->bpass();
            $pax = $this->http->FindSingleNode("./ancestor::div[1]", $root, false,
                "#{$this->opt($this->t('Boarding pass for'))}[ :]+(.+)#");
            $bp->setTraveller($pax)
                ->setUrl($img)
                ->setFlightNumber($f->getSegments()[0]->getFlightNumber())
                ->setRecordLocator($f->getPrimaryConfirmationNumberKey())
                ->setDepCode($f->getSegments()[0]->getDepCode())
                ->setDepDate($f->getSegments()[0]->getDepDate());
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //03 Jul 2019
            //18:30
            '#^(\d+)\s+(\w+)\s+(\d{4})\s+(\d+:\d+)$#u',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
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

    private function assignLang(?string $body = null)
    {
        if (isset($body)) {
            foreach (self::$dict as $lang => $words) {
                if (isset($words['Passenger:'], $words['Flight:'])) {
                    if (stripos($body, $words['Passenger:']) !== false && stripos($body, $words['Flight:']) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        } else {
            foreach (self::$dict as $lang => $words) {
                if (isset($words['Booking reference:'], $words['Travellers:'])) {
                    if ($this->http->XPath->query("//*[{$this->contains($words['Booking reference:'])}]")->length > 0
                        && $this->http->XPath->query("//*[{$this->contains($words['Travellers:'])}]")->length > 0
                    ) {
                        $this->lang = $lang;

                        return true;
                    }
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
        //		foreach ($pos as $i => $p) {
        //			if (isset($pos[$i], $pos[$i - 1]))
        //				if ($pos[$i] - $pos[$i - 1] < $correct)
        //					unset($pos[$i]);
        //		}
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function nice(?string $str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
