<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class HolidayInnPdf extends \TAccountChecker
{
    public $mailFiles = "ichotelsgroup/it-51743220.eml";

    public $reFrom = ['@holidayinnyakima.com'];
    public $reBody = [
        'en' => ['RATE INFORMATION', 'RESERVATION INFORMATION'],
    ];
    public $reSubject = [
        'Confirmation',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [],
    ];
    private $keywordProv = 'Holiday Inn';
    private $pattern = [
        'time' => '(?:\d+:\d+(?:\s*[aApP][mM])?|\d+[aApP][mM])',
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->assignLang($text)) {
                        $this->parseEmailPdf($text, $email);
                    }
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

            if ((stripos($text, $this->keywordProv) !== false) && $this->assignLang($text)) {
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
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
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

    private function parseEmailPdf($textPDF, Email $email)
    {
        $topBlock = strstr($textPDF, $this->t('GUEST INFORMATION AND BOOKING REQUIREMENTS'), true);

        if (empty($topBlock)) {
            $this->logger->debug("other format");

            return false;
        }
        $detailsBlock = strstr($textPDF, $this->t('GUEST INFORMATION AND BOOKING REQUIREMENTS'));
        $this->logger->critical($topBlock);
        $this->logger->alert($detailsBlock);

        $pos[] = 0;
        $pos[] = mb_strlen($this->re("/^(.+?){$this->opt($this->t('RATE INFORMATION'))}/", $topBlock));
        $table = $this->splitCols($topBlock, $pos);
        $r = $email->add()->hotel();

        $r->general()
            ->confirmation($this->nextText($this->t('Confirmation Number'), $table[0]))
            ->traveller($this->nextText($this->t('Name'), $detailsBlock));

        $regExp = "/{$this->t('Reservations Office')}[ ]*(?:\n[ ]*){3,}(.+)\n([\s\S]+?)\n" .
            "[ ]*{$this->t('Telephone')}:\s+([ \d\+\-\(\)]+?)\s*" .
            "(?:{$this->t('Fax')}:[ ]*([ \d\+\-\(\)]+?))(?:\n|$)/";

        if (preg_match($regExp, $detailsBlock, $m)) {
            $r->hotel()
                ->name($m[1])
                ->address(preg_replace("/\s+/", ' ', $m[2]))
                ->phone(trim($m[3]));

            if (isset($m[4]) && !empty(trim($m[4]))) {
                $r->hotel()->fax(trim($m[4]));
            }
        }

        $r->booked()
            ->checkIn($this->normalizeDate($this->nextText($this->t('Arrival'), $table[0])))
            ->checkOut($this->normalizeDate($this->nextText($this->t('Departure'), $table[0])))
            ->rooms($this->nextText($this->opt($this->t('No. of Rooms')), $table[0]));

        $node = $this->nextText($this->opt($this->t('No. of Guests')), $table[0]);

        if (preg_match("/^(\d+)\s*\/\s*(\d+)$/", trim($node), $m)) {
            $r->booked()
                ->guests($m[1])
                ->kids($m[2]);
        } elseif (preg_match("/^(\d+)$/", trim($node), $m)) {
            $r->booked()
                ->guests($m[1]);
        }

        $room = $r->addRoom();
        $room
            ->setDescription($this->nextText($this->t('Room Description'), $table[1]))
            ->setRate($this->nextText($this->t('Rate Plan'), $table[1]));

        $acc = $this->nextText($this->t('Membership Number'), $detailsBlock);

        if (!empty($acc)) {
            $r->program()->account($acc, preg_match("/^(X{4,}|\*{4,})/i", $acc) !== 0);
        }

        if ($r->getCheckInDate()
            && preg_match("/{$this->t('Our Checkin time is')} ({$this->pattern['time']})/", $detailsBlock, $m)
        ) {
            $r->booked()->checkIn(strtotime($m[1], $r->getCheckInDate()));
        }

        if ($r->getCheckOutDate()
            && preg_match("/{$this->t('Our Checkout time is')} ({$this->pattern['time']})/", $detailsBlock, $m)
        ) {
            $r->booked()->checkOut(strtotime($m[1], $r->getCheckOutDate()));
        }

        if (preg_match("/\n\n[ ]*(Please cancel by .+?)\n\n/s", $detailsBlock, $m)) {
            $r->general()->cancellation(preg_replace("/\s+/", ' ', $m[1]));
        }

        $this->detectDeadLine($r);

        return true;
    }

    private function nextText($field, $block)
    {
        return trim($this->re("/^[ ]*{$field}[ ]+(.+?)(?:[ ]{2,}|$)/m", $block));
    }

    private function normalizeDate($date)
    {
        $in = [
            //05-01-20
            //05-03-20
            '#^(\d{2})\-(\d{2})\-(\d{2})$#u',
        ];
        $out = [
            '20$3-$1-$2',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Please cancel by (?<time>{$this->pattern['time']}) the day before you are due to arrive to avoid penalty charges/i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative('1 day', $m['time']);
        }
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
                if (strpos($body, $reBody[0]) !== false && strpos($body, $reBody[1]) !== false) {
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
}
