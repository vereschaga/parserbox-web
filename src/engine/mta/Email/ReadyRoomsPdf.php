<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReadyRoomsPdf extends \TAccountChecker
{
    public $mailFiles = "mta/it-15910856.eml";

    public $reFrom = ["mtatravel.com.au", "@readyrooms.com.au"];
    public $reBody = [
        'en' => ['Hotel Voucher', 'Booking Reference'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'endCancellationPolicy' => [
                'Standardised check-in',
                'Emergency Customer Care',
                'Qantas Holidays',
                'Important Information',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    }
                    $this->parseEmail($text, $email);
                } else {
                    return null;
                }
            }
        } else {
            return null;
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

            if ((strpos($text, 'ReadyRooms') !== false || strpos($text, 'Qantas Holidays') !== false)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
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

    private function parseEmail($textPDF, Email $email)
    {
        $mainBlock = $this->findСutSection($textPDF, $this->t('Hotel Voucher'), $this->t('Special Requests'));

        if (empty($mainBlock)) {
            $this->logger->info('other format');

            return false;
        }

        $email->ota()
            ->confirmation($this->re("#{$this->opt($this->t('Booking Reference'))}[\s:]+([A-Z\d\/]{5,})#", $textPDF));

        $h = $email->add()->hotel();
        $node = $this->re("#{$this->replaceSpacesReg($this->opt($this->t('call ReadyRooms')))}[\s:]+(.+?)\.#s",
            $textPDF);
        $addedPhones = [];

        if (preg_match_all("#(.+?)\s+([\d \(\)\+\-]+)#", $node, $m, PREG_SET_ORDER)) {
            foreach ($m as $v) {
                $num = preg_replace(["#\s*\(\s*#", "#\s*\)\s*#"], ['(', ')'], trim($v[2], " ("));

                if (!in_array($num, $addedPhones)) {
                    $h->program()->phone($num, $this->t('call ReadyRooms') . ': ' . trim($v[1], " ."));
                    $addedPhones[] = $num;
                }
            }
        }

        $node = $this->re("#{$this->opt($this->t('Travellers'))}[^\n]+\n(.+)#s", $mainBlock);

        if (preg_match_all("#^(.+?)\s{4,}(?:Adult|Child)#m", $node, $m)) {
            $h->general()
                ->travellers($m[1]);
        }
        $h->general()
            ->confirmation($this->re("#{$this->opt($this->t('Reservation Number'))}[ :]+([\w\-]{5,})#", $mainBlock));

        $h->hotel()
            ->name(trim($this->re("#([^\n]+)\s+{$this->opt($this->t('Address'))}:#", $mainBlock)))
            ->address(preg_replace("#\s+#", ' ',
                $this->re("#{$this->opt($this->t('Address'))}:\s+(.+?)\s+{$this->opt($this->t('Contact'))}#s",
                    $mainBlock)))
            ->phone($this->re("#{$this->opt($this->t('Contact'))}:\s*([\d\-\(\)\+\s]{5,})#s", $mainBlock));

        $dateBlock = $this->re("#(^ *{$this->t('Check in')}.+?)\n\n#sm", $mainBlock);
        $rows = explode("\n", $dateBlock);
        $pos = strpos($rows[0], $this->t('Check out'));
        $pos = $pos > 10 ? $pos - 3 : 0;
        $table = $this->splitCols($dateBlock, [0, $pos]);

        if (count($table) !== 2) {
            $this->logger->debug('other format dateBlock');

            return false;
        }

        $checkIn = strtotime($this->re("#{$this->t('Check in')}[ :]+(.+)#", $table[0]));
        $time = $this->correctTimeString($this->re("#(\d+:\d+(?:\s*[ap]m)?)#i", $table[0]));

        if (!empty($time)) {
            $checkIn = strtotime($time, $checkIn);
        }
        $checkOut = strtotime($this->re("#{$this->t('Check out')}[ :]+(.+)#", $table[1]));
        $time = $this->correctTimeString($this->re("#(\d+:\d+(?:\s*[ap]m)?)#i", $table[1]));

        if (!empty($time)) {
            $checkOut = strtotime($time, $checkOut);
        }
        $h->booked()
            ->checkIn($checkIn)
            ->checkOut($checkOut);
        $h->setCancellation(trim(preg_replace("#\s+#", ' ',
                $this->re("#{$this->opt($this->t('Cancellation Policy'))}\s+(.+?)\n[^\n]*{$this->opt($this->t('endCancellationPolicy'))}#s", $textPDF))));

        if (preg_match("#If cancelled on or before (\d+/\d+/\d+) - no charge applies\.#i", $h->getCancellation(), $m)) {
            $h->booked()->deadline2($this->ModifyDateFormat($m[1]));
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function correctTimeString($time)
    {
        if (preg_match("#(\d+):(\d+)\s*([ap]m)#i", $time, $m)) {
            if (($m[1] == 0 && stripos($m[3], 'am') !== false) || $m[1] > 12) {
                return $m[1] . ":" . $m[2];
            }
        }

        return $time;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function replaceSpacesReg($str)
    {
        return preg_replace("#\s+#", '\s', $str);
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
}
