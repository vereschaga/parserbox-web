<?php

namespace AwardWallet\Engine\indigo\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPassPdf3 extends \TAccountChecker
{
    public $mailFiles = "indigo/it-78143408.eml";

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [],
    ];

    private $detectSubject = [
        '/(?:^|:\s*)Boarding Pass for PNR (?-i)[A-Z\d]{5,10}$/i',
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($text === null) {
                continue;
            }

            if (!preg_match("/\s+6E \d{1,5}\s+/", $text)) {
                continue;
            }

            if (strpos($text, 'Boarding Pass (Web Check-in)') !== false
                || strpos($text, 'Boarding Pass (Web Check­in)') !== false
            ) {
                $this->parseBoardingPass($email, $text);
            }

            if (strpos($text, 'Self health declaration form') !== false) {
                $declarationPdfs[] = $text;
            }
        }

        if (empty($email->getItineraries()) && isset($declarationPdfs)) {
            foreach ($declarationPdfs as $text) {
                $this->parseDeclaration($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (array_key_exists('subject', $headers) && preg_match($dSubject, $headers['subject'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $body = $parser->getAttachmentBody($pdf);
            $text = \PDF::convertToText($body);

            if ($text == null) {
                continue;
            }

            if ((
                    // type 1
                    strpos($text, 'Boarding Pass (Web Check-in)') !== false
                    || strpos($text, 'Boarding Pass (Web Check­in)') !== false
                    // type 2
                    || strpos($text, 'Self health declaration form') !== false
                )
                && preg_match("/\s+6E \d{1,5}\s+/", $text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]goindigo\.in$/', $from) > 0;
    }

    public function splitText($pattern, $text)
    {
        if (empty($text)) {
            return $text;
        }

        $r = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
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

    private function parseBoardingPass(Email $email, string $text): void
    {
        $this->logger->debug(__FUNCTION__);
        $segmentsText = array_filter($this->splitText("/(Boarding Pass[ ]*\()/", $text));
//        $this->logger->debug('$segmentsText = '.print_r( $segmentsText,true));

        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation();

        foreach ($segmentsText as $key => $stext) {
            unset($table, $traveller);

            $stext = $this->re("/((?:.*\n)+.*  Seat .+)/", $stext);
            $stext = $this->re("/^.*\n+(?: {30,}.*\n+)*([\s\S]+)/", $stext);
            // $this->logger->debug('$stext = ' . "\n" . print_r($stext, true));

            if (preg_match("/(.*) {2}PNR\b/", $stext, $m)) {
                $pos = strlen($m[1]);
                $poses = $this->rowColsPos($this->inOneRow($stext));
                $headerPos = [0];

                foreach ($poses as $p) {
                    if (abs($p - $pos) < 5) {
                        $headerPos[] = $p;

                        break;
                    }
                }

                if (count($headerPos) == 2) {
                    $table = $this->SplitCols($stext, $headerPos);
                }
            } else {
                return;
            }

            if (empty($table)) {
                return;
            }

            $s = $f->addSegment();

            if (preg_match("/^[ ]*(?<name>\S.+?) {3,}(?<dep>.+) To (?<arr>.+)\s+Flight/", $table[0], $m)) {
                $traveller = $this->normalizeTraveller($m['name']);

                if (!in_array($traveller, array_column($f->getTravellers(), 0))) {
                    $f->general()
                        ->traveller($traveller, true);
                }

                $code = $this->getCode($m['dep']);

                if (!empty($code)) {
                    $s->departure()
                        ->code($code);
                } else {
                    $s->departure()
                        ->noCode();
                }

                if (preg_match("/(.+)\((.+)\)\s*$/", $m['dep'], $mat)) {
                    $s->departure()
                        ->name($mat[1])
                        ->terminal(trim(preg_replace(['/^\s*T(\d+)\s*$/', '/\s*Terminal\s*/'], ['$1', ' '], $mat[2])))
                    ;
                } else {
                    $s->departure()
                        ->name($m['dep'])
                    ;
                }

                $code = $this->getCode($m['arr']);

                if (!empty($code)) {
                    $s->arrival()
                        ->code($code);
                } else {
                    $s->arrival()
                        ->noCode();
                }

                if (preg_match("/(.+)\((.+)\)\s*$/", $m['arr'], $mat)) {
                    $s->arrival()
                        ->noCode()
                        ->name($mat[1])
                        ->terminal(trim(preg_replace(['/^\s*T(\d+)\s*$/', '/\s*Terminal\s*/'], ['$1', ' '], $mat[2])))
                    ;
                } else {
                    $s->arrival()
                        ->noCode()
                        ->name($m['arr'])
                    ;
                }
            }

            if (preg_match("/\n\s*PNR *([A-Z\d]{5,7})\n/", $table[1], $m)) {
                $s->airline()
                    ->confirmation($m[1]);
            }

            if (preg_match("/\n\s*Flight *([A-Z\d][A-Z]|[A-Z][A-Z\d]) *(\d{1,5})\s*\n/", $table[1], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }

            if (preg_match("/\n\s*Date *(.+?) {2,}Departure *(\d{1,2}:?\d{2}) *Hrs/", $table[0], $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m[1] . ' ' . $m[2]));
                $s->arrival()
                    ->noDate();
            }

            $bpURL = $this->http->FindSingleNode("//a[normalize-space()='View Boarding Pass']/@href");

            if (!empty($bpURL) && $s->getDepCode()) {
                $bp = $email->add()->bpass();
                $bp
                    ->setDepCode($s->getDepCode())
                    ->setFlightNumber($s->getFlightNumber())
                    ->setDepDate($s->getDepDate())
                    ->setRecordLocator($s->getConfirmation())
                    ->setTraveller($traveller)
                    ->setUrl($bpURL);
            }

            $seat = '';

            if (preg_match("/\n\s*Seat *(\d{1,3}[A-Z])(?:\n|$)/", $table[1], $m)) {
                $seat = $m[1];
            }
            $segments = $f->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (serialize(array_diff_key($segment->toArray(), ['seats' => []])) === serialize($s->toArray())) {
                        $segment->extra()->seat($seat);
                        $f->removeSegment($s);

                        continue 2;
                    }
                }
            }

            $s->extra()->seat($seat);
        }
    }

    private function getCode(string $name): ?string
    {
        $names = [ucfirst(strtolower($name)), ucwords(strtolower($name)), implode('(', array_map('ucfirst', explode('(', strtolower($name))))];
        $code = $this->http->FindSingleNode("//tr[td[normalize-space()][1][" . $this->eq(['From']) . "]][following-sibling::tr[2]/td[normalize-space()][1][" . $this->eq($names) . "]]"
            . "/following-sibling::tr[1]/td[normalize-space()][1]");

        if (empty($code)) {
            $code = $this->http->FindSingleNode("//tr[td[normalize-space()][last()][" . $this->eq(['To']) . "]][following-sibling::tr[2]/td[normalize-space()][last()][" . $this->eq($names) . "]]"
                . "/following-sibling::tr[1]/td[normalize-space()][last()]");
        }

        return $code;
    }

    private function parseDeclaration(Email $email, string $text): void
    {
        $this->logger->debug(__FUNCTION__);
        $segmentsText = array_filter($this->splitText("/\nSelf health declaration form\s*\n+(\s*[A-Z]{3} +[A-Z]{3}\n)/", $text));
//        $this->logger->debug('$segmentsText = '.print_r( $segmentsText,true));

        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation();

        foreach ($segmentsText as $key => $stext) {
//            $this->logger->debug('$stext = '."\n".print_r( $stext,true));

            $s = $f->addSegment();

            if (preg_match("/^\s*([A-Z]{3}) +([A-Z]{3})\n/", $stext, $m)) {
                $s->departure()
                    ->code($m[1]);
                $s->arrival()
                    ->code($m[2]);
            }

            if (preg_match("/^\s*[A-Z]{3} +[A-Z]{3}\n(?<names>.+?)\.(?<date>.*\d{1,2}:\d{2}.*?)\. *(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) *(?<fn>[\d ]{1,10}) *\. *PNR *[^\s\w]* *(?<pnr>[A-Z\d]{5,7})\s*\n/", $stext, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number(str_replace(' ', '', $m['fn']))
                    ->confirmation($m['pnr']);

                $s->departure()
                    ->date($this->normalizeDate($m['date']));
                $s->arrival()
                    ->noDate();

                if (preg_match("/\s*\w.*\( *(T\w+|.*Terminal.*) *\) \w+/i", $m['names'], $mat)) {
                    $s->departure()
                        ->terminal(trim(preg_replace(['/^\s*T(\d+)\s*$/', '/\s*Terminal\s*/'], ['$1', ' '], $mat[1])));
                }

                if (preg_match("/\s*\w.*\( *(T\w+|.*Terminal.*) *\)\s*$/i", $m['names'], $mat)) {
                    $s->arrival()
                        ->terminal(trim(preg_replace(['/^\s*T(\d+)\s*$/', '/\s*Terminal\s*/'], ['$1', ' '], $mat[1])));
                }
            }

            if (preg_match("/ *PNR *[^\s\w]* *[A-Z\d]{5,7}\s*\n\s*([[:alpha:]][\-[:alpha:]]+(?: [[:alpha:]][\-[:alpha:]]*){1,4})\n/i", $stext, $m)) {
                $traveller = $this->normalizeTraveller($m[1]);

                if (!in_array($traveller, array_column($f->getTravellers(), 0))) {
                    $f->general()->traveller($traveller, true);
                }
            }

            $segments = $f->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (serialize($segment->toArray()) === serialize($s->toArray())) {
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
        }
    }

    private function re(string $re, ?string $str, $c = 1): ?string
    {
        if (preg_match($re, $str ?? '', $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{1,2})\s+(\w+)\s+(\d{4})\s+(\d{1,2})(\d{2})\s*$#", // 02 Feb 2021 1830
            "#^\s*(\d{1,2})\s+(\w+)\s+(\d{2})\s+(\d{1,2}:\d{2})\s*$#", // 02 Feb 21 18:30
        ];
        $out = [
            "$1 $2 $3, $4:$5",
            "$1 $2 20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        return strtotime($str);
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

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;
        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }
        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];
        if ($text === null)
            return $cols;
        $rows = explode("\n", $text);
        if ($pos === null || count($pos) === 0) $pos = $this->rowColsPos($rows[0]);
        arsort($pos);
        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);
        foreach ($cols as &$col) $col = implode("\n", $col);
        return $cols;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MASTER|MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
            '/^([^\/]+?)(?:\s*[\/]+\s*)+([^\/]+)$/',
        ], [
            '$1',
            '$1',
            '$2 $1',
        ], $s);
    }
}
