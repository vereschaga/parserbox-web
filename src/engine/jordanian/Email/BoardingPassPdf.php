<?php

namespace AwardWallet\Engine\jordanian\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "jordanian/it-426602252.eml, jordanian/it-431123954.eml, jordanian/it-431123959.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'BOARDING PASS'             => 'BOARDING PASS',
        ],
    ];

    private $detectSubject = [
        // en
        'Boarding Pass Confirmation',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers['from'], 'noreply@amadeus.com') === false) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->containsText($text, 'Royal jordanian') === false) {
                continue;
            }

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['BOARDING PASS'])
                && $this->containsText($text, $dict['BOARDING PASS']) === true
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                $this->parsePdf($email, $text);
            }

//            $this->logger->debug('$text = ' . print_r($text, true));
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

    private function parsePdf(Email $email, ?string $text = null)
    {
        $f = $email->add()->flight();

        // General
        $confs = array_unique($this->res("/\n *PNR No {1,3}([A-Z\d]{5,7})\s+/", $text));

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }
        $f->general()
            ->travellers(array_unique($this->res("/\n *Name\W*\n([[:alpha:] \-]+\\/[[:alpha:] \-]+)\n/", $text)));

        $f->issued()
            ->tickets($this->res("/^ *ETKT +(\d{10,})\n/", $text), false);

        $bps = $this->split("/\n( *{$this->opt($this->t('BOARDING PASS'))}\n)/", "\n" . $text);
        // $this->logger->debug('$bps = ' . print_r("\n\n" . $text, true));

        foreach ($bps as $bpText) {
            $s = $f->addSegment();

            $re = "/\n *(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5}) +(?<class>[A-Z\d]{1,3}) {2,}(?<day>\d{1,2}) ?\\/ ?(?<month>\d{1,2}) +(?<time>\d{1,2}:\d{1,2}) +.*? +(?<seat>\d{1,3}[A-Z]|INF) +\d+\n/";

            if (preg_match($re, $bpText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                if ($m['class'] !== $m['seat']) {
                    $s->extra()
                        ->bookingCode($m['class']);
                }

                if ($m['seat'] !== 'INF') {
                    $s->extra()
                        ->seat($m['seat']);
                }

                $relativeDate = str_replace('/', '.', $this->http->FindSingleNode("(//tr[td[2][normalize-space() = 'From']]/following-sibling::tr[1]/td[2]/descendant::text()[normalize-space()][2])[1]",
                    null, true, "/(.+?)\s*-/"));
                $relativeDate = strtotime($relativeDate);

                if (!empty($relativeDate)) {
                    $s->departure()
                        ->date(EmailDateHelper::parseDateRelative($m['day'] . '.' . $m['month'] . '.' . date('Y', $relativeDate), strtotime('-2 day', $relativeDate)));
                    $s->arrival()
                        ->noDate();
                }
            }

            if (preg_match("/\n *From  +To\n+ *(?<dName>\S.+?) {3,}(?<aName>\S.+)/", $bpText, $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m['dName'])
                ;
                $s->arrival()
                    ->noCode()
                    ->name($m['aName'])
                ;
            }

            $segments = $f->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (serialize(array_diff_key($segment->toArray(),
                            ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                        if (!empty($s->getSeats())) {
                            $segment->extra()->seats(array_unique(array_merge($segment->getSeats(),
                                $s->getSeats())));
                        }
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // 22/03/2023, 07:30
            "/^\s*(\d{1,2})\\/(\d{2})\\/(\d{4})\s*,\s*(\d{1,2}:\d{2}(\s*[ap]m)?)$/i",
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
//                $str = str_replace($m[1], $en, $str);
//            }
//        }

        if (preg_match("/^\s*\d{1,2}\.\d{2}\.\d{4}\s*,\s*(\d{1,2}:\d{2}(?: *[ap]m)?)?\s*$/i", $str)) {
            return strtotime($str);
        }

        return null;
    }

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
