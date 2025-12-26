<?php

namespace AwardWallet\Engine\royalcaribbean\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CruiseItinerary extends \TAccountChecker
{
    public $mailFiles = "royalcaribbean/it-355697921.eml, royalcaribbean/it-51155282.eml";
    public static $providers = [
        'royalcaribbean' => [
            'from' => 'documentation@rccl.com',
            'pdf'  => 'www.RoyalCaribbean.com',
        ],
        'celebritycruises' => [
            'from' => 'DONOTREPLY@celebritycruises.com',
            'pdf'  => 'www.celebritycruises.com',
        ],
    ];
    private static $detectors = [
        'en' => ["GUEST TICKET BOOKLET"],
    ];
    private static $dictionary = [
        'en' => [
            "RESERVATION ID:"   => "RESERVATION ID:",
            "MEMBERSHIP"        => ["CROWN & ANCHOR MEMBERSHIP", 'CAPTAIN’S CLUB LEVEL', 'CROWN & ANCHOR'],
            "SAILING FROM:"     => ["SAILING FROM:"],
            "DECK #:"           => "DECK #:",
            "STATEROOM #:"      => "STATEROOM #:",
            "CATEGORY:"         => "CATEGORY:",
            "BOARDING DATE:"    => "BOARDING DATE:",
        ],
    ];
    private $from = "documentation@rccl.com";
    private $subject = ["Your Guest Vacation Documents are now ready for Reservation ID:"];
    private $body = 'Cruise Summary';
    private $lang;
    private $pdfNamePattern = ".*pdf";
    private $year;
    private $providerCode;

    private $aboard = false;

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function detectEmailByHeaders(array $headers)
    {
        $detect = false;

        foreach (self::$providers as $code => $detect) {
            if (stripos($headers["from"], $detect['from']) !== false) {
                $detect = true;
            }
        }

        if ($detect === false) {
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

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, $this->body) !== false) {
                return true;
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
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf),
                        2)) !== null && ($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $this->parseEmailPdf($email, $html, $text);
                }
            }
        }

        foreach (self::$providers as $code => $detect) {
            if (stripos($parser->getCleanFrom(), $detect['from']) !== false) {
                $this->providerCode = $code;

                break;
            }

            if (stripos($text, $detect['pdf']) !== false) {
                $this->providerCode = $code;

                break;
            }
        }
        $email->setProviderCode($this->providerCode);
        $email->setType('CruiseItinerary');

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

    private function assignLang(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $html = \PDF::convertToHtml($parser->getAttachmentBody($pdf[0]), 2);
            $http1 = clone $this->http;
            $http1->SetBody($html);

            foreach (self::$dictionary as $lang => $words) {
                if ($http1->XPath->query("//*[{$this->contains($words["RESERVATION ID:"])}]")->length > 0
                    && $http1->XPath->query("//*[{$this->contains($words["SAILING FROM:"])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function parseEmailPdf(Email $email, $html, $text)
    {
        $httpc = clone $this->http;
        $httpc->SetBody($html);

        $r = $email->add()->cruise();

        $year = $this->getValue($httpc, "BOARDING DATE:", "/^\d{1,2}\s[A-z]{3}\s(\d{2,4})$/");

        if (!empty($year)) {
            $this->year = $year;
        }

        $confNo = $this->getValue($httpc, "RESERVATION ID");

        if (!empty($confNo)) {
            $r->general()->confirmation($confNo, trim($this->t('RESERVATION ID:', ':')));
        }

        $deck = $this->getValue($httpc, "DECK #:");

        if (!empty($deck) && stripos($deck, ':') === false) {
            $r->details()->deck($deck);
        }

        $room = $this->getValue($httpc, "STATEROOM #:");

        if (!empty($room)) {
            $r->details()->room($room);
        }

        $ship = $this->getValue($httpc, "SHIP NAME:");

        if (!empty($ship)) {
            $r->details()->ship($ship);
        }

        $category = $this->getValue($httpc, "CATEGORY:");

        if (!empty($category)) {
            $r->details()->roomClass($category);
        }

        //Pax
        $rePax = "/THIS BOOKLET HAS BEEN PREPARED FOR +{$this->opt($this->t('MEMBERSHIP'))}.*(?:\n {30}.*)?" .
            "\n\s*([\s\S]+?)\n{3,}/";

        if (preg_match($rePax, $text, $m)) {
            $pax = preg_replace("/\n\n\n.+$/s", "", $m[1]);

            if (preg_match_all("/^ *([A-Za-z][A-Za-z ]+?)\s{3,}/m", $pax, $m)) {
                if (!empty($m[1])) {
                    $r->general()->travellers(array_unique($m[1]), true);
                }
            }
        } else {
            $this->logger->alert("no passengers, need to check rePax!");
            $r->general()->travellers([]);
        }

        //Segment
        if (preg_match("/Travel Summary\n(?:[\s\S]*?\n)? *Cruise Itinerary((?:\n.*?)+?)\n *(?:Travel Documents|Port Directions)/", $text, $match)) {
            preg_match("/DAY\s*DATE\s*PORTS-OF-CALL\s*DOCK\s*OR.*\s*ARRIVE\s*DEPART(?:\n {40,}.*)?((?:\n.*?)+?)\n\s*(?:\d{1}\s\n|Post Cruise Air Arrangements|For Any Day Of Travel Concerns|Post-Cruise Ground Arrangements|$)/",
                $match[1], $m);
            $segments = array_values(array_filter(array_map('trim', preg_split("/\n\s/", $m[1]))));

            foreach ($segments as $segment) {
                $ts = $this->getTableSegment($segment);

                if (!preg_match("/(\d+\:\d+)/", $segment)) {
                    continue;
                }

                if (preg_match("/^\s*\d+\:\d+/", $ts[4]) && preg_match("/^\s*\d+\:\d+/", $ts[5])) {
                    $s = $r->addSegment();
                    $s->setName($ts[2]);
                    $s->setAboard($this->normalizeDate($ts[1], $this->year, $ts[0], $ts[5]));
                    $s->setAshore($this->normalizeDate($ts[1], $this->year, $ts[0], $ts[4]));
                } elseif (preg_match("/^(?<week>\w+)\s+(?<day>\d+\s*\w+)\s*(?<name>.+)\b[ ]{2,}(\S)\s+(?<time>[\d\:]+\s*A?P?M?)$/", $segment, $m) && $this->aboard === false) {
                    $s = $r->addSegment();
                    $s->setName($m['name']);
                    $s->setAboard($this->normalizeDate($m['day'], $this->year, $m['week'], $m['time']));
                    $this->aboard = true;
                } elseif (preg_match("/^(?<week>\w+)\s+(?<day>\d+\s*\w+)\s*(?<name>.+)\b[ ]{2,}(\S)\s+(?<time>[\d\:]+\s*A?P?M?)$/", $segment, $m) && $this->aboard === true) {
                    $s = $r->addSegment();
                    $s->setName($ts[2]);
                    $s->setAshore($this->normalizeDate($ts[1], $this->year, $ts[0], $ts[4]));
                    $this->aboard = false;
                }
            }
        }

        return $email;
    }

    private function getValue($httpc, $field, $re = null)
    {
        return $httpc->FindSingleNode("(//p[" . $this->starts($this->t($field)) . "]/following-sibling::p[1])[1]", null,
            true, $re);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function getTableSegment($segment)
    {
        if ($result = preg_replace('/\s([A-Z]{1})(\s{15,})(\d{1,2}:\d{1,2}\s*(?:A|P)M)$/i', '$1   null   $3',
            $segment)) {
            $segment = $result;
        }

        if ($result = preg_replace('/\s([A-Z]{1})(\s{0,12})(\d{1,2}:\d{1,2}\s*(?:A|P)M)$/i', '$1   $3   null',
            $segment)) {
            $segment = $result;
        }
        $table = $this->splitCols($segment);

        return $table;
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
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function normalizeDate($parsedDate, $parsedYear, $week, $time)
    {
        if ($time === 'null') {
            return null;
        }
        $weekDateNumber = WeekTranslate::number1($week, 'en');

        return EmailDateHelper::parseDateUsingWeekDay($time . " " . $parsedDate . ' ' . $parsedYear, $weekDateNumber);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
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
}
