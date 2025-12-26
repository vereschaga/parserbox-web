<?php

namespace AwardWallet\Engine\tbrands\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class AgencyPrice extends \TAccountChecker
{
    public $mailFiles = "tbrands/it-281280367.eml, tbrands/it-49027538.eml";

    private $from = "@travelbrands.com";

    private $subject = [
        "Intair agency price -",
        "Intair Passenger Itinerary -",
    ];

    private $body = 'travelbrands';

    private $pdfName = '';
    private $lang;

    private $pdfNamePattern = ".*pdf";

    private static $detectors = [
        'en' => ["* The spelling of the passenger's names must be identical as the ones shown on their passport", "The spelling of the passenger's names must be identical as the ones shown on their passport"],
    ];
    private static $dictionary = [
        'en' => [
            "Record locator" => "Record locator :",
            "PASSENGER(S) :" => ["PASSENGER(S) :"],
            "Total amount"   => "Total amount",
            "ITINERARY"      => ["ITINERARY :"],
        ],
    ];

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $sub) {
            if (stripos($headers["subject"], $sub) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        $detectCompany = false;

        if ($this->http->XPath->query("//*[contains(., '@travelbrands.com')]")->length > 0
        || strpos($parser->getSubject(), 'Intair') !== false) {
            $detectCompany = true;
        }

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, $this->body) === false && $detectCompany === false) {
                return false;
            }
        }

        if ($this->detectBody($parser)) {
            return $this->assignLang($parser);
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang($parser)) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!empty($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $this->pdfName = $this->getAttachmentName($parser, $pdf);
                    $this->parseEmailPdf($email, $text);
                }
            }
        }
        $email->setType('AgencyPrice');

        return $email;
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach (self::$detectors as $lang => $phrases) {
                foreach ($phrases as $phrase) {
                    if (!empty(stripos($text, $phrase)) && !empty(stripos($text,
                            $phrase))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function parseEmailPdf(Email $email, $text)
    {
        $r = $email->add()->flight();

        if (preg_match('/Record locator :\s+([A-Z\d]+)/', $text, $m)
            || preg_match("/^[A-z_]+_([A-Z]{5,7})\.pdf/", $this->pdfName, $m)
            || preg_match('/^([A-Z\d]{6})\nTel/m', $text, $m)
        ) {
            $r->general()->confirmation($m[1], 'Record locator');
        }

        if (preg_match('/PASSENGER\(S\) :\s+(?:(.+\n.+(?:MRS|MR|MS)|.+))/', $text, $m)) {
            $m[1] = explode(',', $m[1]);
            $m[1] = preg_replace("/ (Miss|Mrs|Ms|Mr|Mstr|Dr)\s*$/i", '', $m[1]);
            $m[1] = preg_replace("/^\s*(.+?)\s*\\/\s*(.+?)\s*$/i", '$2 $1', $m[1]);
            $r->general()->travellers($m[1], true);
        }

        if (preg_match_all('/FARE\(S\) AND FEES :((?:\n.*)+)Important Notices :/m', $text, $blocks)) {
            if (preg_match('/Total.+: *(\d+).+[\s]{3,}.+?\s(\d+[\s.\d]+)([A-z]{3})/', $blocks[1][0], $m)) {
                $r->price()
                    ->total(str_replace(" ", "", $m[2]))
                    ->currency(strtoupper($m[3]));

                if (preg_match('/Taxes :.+\s+(\d+[.\d]+)[A-z]{3}/', $blocks[1][0], $mat)) {
                    $r->price()
                        ->tax($mat[1] * $m[1]);
                }

                if (preg_match('/(.*fees)\D*([\d\.\,]+)/', $blocks[1][0], $mat)) {
                    $r->price()
                        ->fee($mat[1], PriceHelper::parse($mat['2'], strtoupper($m[3])));
                }
            }
        }

        if (preg_match_all('/Class((?:\n.*)+?)\n\s*(?:FARE\(S\) AND FEES :|Important Notices :)/', $text, $blocks)) {
            foreach ($blocks[1] as $block) {
                $segments = array_filter(array_map('trim', preg_split("/\s\n/", $block)));

                foreach ($segments as $segment) {
                    if (stripos($segment, 'TERMINAL') === false
                        && stripos($segment, 'Operated by:') === false
                        && stripos($segment, 'Seat :') === false) {
                        continue;
                    }

                    $tSegment = $this->getTableSegment(preg_replace('/\n *\*.*?OPERATED BY[\s\S]+/', '', $segment));

                    if (count($tSegment) === 5) {
                        if (preg_match("/.+\n([A-Z\d]{6}\s+)/", $segment, $m)) {
                            $columnLenth = strlen($m[1]);
                            $segment = preg_replace("/^(.{{$columnLenth}})/", "$1 ", $segment);
                            $tSegment = $this->splitCols($segment);
                        }
                    }
                    $this->parseSegment($tSegment, $r, $segment);
                }
            }
        }

        return $email;
    }

    private function parseSegment($seg, Flight $r, $sText)
    {
        $s = $r->addSegment();

        if (preg_match('/\n(?<aN>(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])) ?(?<fN>\d{1,4})\n*(?:Operated by:(?<operator>.*\n*.*))?\n*(?:REF|Airline booking no)[\s]?:[\s]?(?<conf>[A-Z\d]+)/', $seg[0], $m)) {
            $s->airline()
                ->name($m['aN'])
                ->number($m['fN']);

            if (!in_array($m['conf'], array_column($r->getConfirmationNumbers(), 0))) {
                $s->airline()
                    ->confirmation($m['conf']);
            }

            if (isset($m['operator']) && !empty($m['operator'])) {
                $s->airline()
                    ->operator(str_replace("\n", " ", $m['operator']));
            }
        }

        if (preg_match("/OPERATED BY[ \\/]+(\S.+?)(?: {2,}|\s*\n|\s*$)/", $sText, $m)) {
            $s->airline()
                ->operator($m[1]);
        }

        if (preg_match('/(?<depName>.+)?\((?<depCode>[A-Z]{3})\)/', $seg[1], $m)) {
            $s->departure()
                ->code($m['depCode']);

            if (isset($m['depName']) && !empty($m['depName'])) {
                $s->departure()
                    ->name($m['depName']);
            }
        }

        if (preg_match('/- ([A-z\d ]*\bTerminal\b[A-z\d\s]*)/', $seg[1], $m)) {
            $s->departure()
                ->terminal(trim(preg_replace("/\s*\bterminal\b\s*/i", ' ', $m[1])));
        }

        if (preg_match('/(?:(\d{1,2}\s[A-z]+\s\d{4})\n(\d{1,2}(?:h|\:)\d{1,2})|(\d{1,2}\s[A-z]+\s\d{4}))/', $seg[1], $m)) {
            $s->departure()
                ->date(strtotime($m[1] . ", " . str_replace('h', ':', $m[2])));
        }

        if (preg_match('/(?<arrName>.+)?\((?<arrCode>[A-Z]{3})\)/', $seg[2], $m)) {
            $s->arrival()
                ->code($m['arrCode']);

            if (isset($m['arrName']) && !empty($m['arrName'])) {
                $s->arrival()
                    ->name($m['arrName']);
            }
        }

        if (preg_match('/- ([A-z\d ]*\bTerminal\b[A-z\d\s]*)/', $seg[2], $m)) {
            $s->arrival()
                ->terminal(trim(preg_replace("/\s*\bterminal\b\s*/i", ' ', $m[1])));
        }

        if (preg_match('/(?:(\d{1,2}\s[A-z]+\s\d{4})\n(\d{1,2}(?:h|\:)\d{1,2})|(\d{1,2}\s[A-z]+\s\d{4}))/', $seg[2], $m)) {
            $s->arrival()
                ->date(strtotime($m[1] . " " . str_replace('h', ':', $m[2])));
        }

        if (preg_match('/^([A-z\d]+)\n(?:.*\n)?Seat :/', $seg[3], $m)) {
            $s->extra()
                ->aircraft($m[1]);
        }

        if (preg_match('/\s+Seat : *(\d{1,3}[A-Z].*)\n/', $sText, $m)) {
            if (stripos(', ', $sText) !== false) {
                $symbol = ', ';
            } else {
                $symbol = ' ';
            }

            $s->extra()
                ->seats(explode($symbol, $m[1]));
        }

        if (preg_match('/\s+Meal : *(.+)/', $sText, $m)) {
            $s->extra()
                ->meal($m[1]);
        }

        if (preg_match('/(\d{1,2}h\s*\d{1,2}min)/', $seg[4], $m)
        || preg_match('/(\d{1,2}h\s*\d{1,2}min)/', $seg[5], $m)) {
            $s->extra()
                ->duration($m[1]);
        }

        if (preg_match('/(^([A-z]+))/', $seg[4], $m)) {
            $s->extra()
                ->status($m[1]);
        }

        if (preg_match('/([A-Z]{1})/', $seg[5], $m)
        || preg_match('/^\s*([A-Z])\s*$/s', $seg[6], $m)) {
            $s->extra()
                ->bookingCode($m[1]);
        }
    }

    private function assignLang(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach (self::$dictionary as $lang => $words) {
                if (!empty($words['PASSENGER(S) :']) && $this->containsText($text, $words['PASSENGER(S) :']) !== false
                    && !empty($words['ITINERARY']) && $this->containsText($text, $words['ITINERARY']) !== false
                ) {
                    $this->lang = $lang;

                    return true;
                }
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $currentPos = mb_strpos($row, $word, $lastpos, 'UTF-8');

            if ($currentPos > 0) {
                --$currentPos;
            }
            $pos[] = $currentPos;
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function getTableSegment($segment)
    {
        $table = $this->splitCols($segment);
        array_splice($table, 7);

        return $table;
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

    private function getAttachmentName(\PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }
}
