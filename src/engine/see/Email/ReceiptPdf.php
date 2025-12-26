<?php

namespace AwardWallet\Engine\see\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

// for PDF (for HTML look parser see/YourTickets)

class ReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "see/it-155715283.eml, see/it-295908863.eml, see/it-290624426.eml, see/it-287424247.eml, see/it-325969249-html-v1.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'pageDelimiter' => ['THIS IS YOUR TICKET'],
            'orderNumber'   => ['Order #', 'Order#'],
        ],
    ];

    private $subjects = [
        'en' => ['Here are your Tickets'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@seetickets.us') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (stripos($textPdf, 'support.seetickets.us') === false
                && stripos($textPdf, 'at seetickets.us/') === false
                && stripos($textPdf, 'See Tickets. All Rights Reserved') === false
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $textPdfFull = '';

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($textPdf)) {
                $textPdfFull .= $textPdf;
            }
        }

        if (!$textPdfFull) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('ReceiptPdf' . ucfirst($this->lang));

        $this->parsePdf($email, $textPdfFull);

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

    private function parsePdf(Email $email, string $text): void
    {
        $ticketsByOrderNo = [];
        $allTickets = $this->splitText($text, "/^[ ]{0,15}{$this->opt($this->t('pageDelimiter'))}.*\n/m");

        foreach ($allTickets as $tText) {
            $orderNo = $this->re("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('orderNumber'))}[ ]*[:]+[ ]*([-A-z\d]{4,})(?:[ ]{2}|$)/m", $tText);

            if (!$orderNo) {
                $email->add()->cruise(); // for 100% fail

                continue;
            }

            if (array_key_exists($orderNo, $ticketsByOrderNo)) {
                $ticketsByOrderNo[$orderNo][] = $tText;
            } else {
                $ticketsByOrderNo[$orderNo] = [$tText];
            }
        }

        foreach ($ticketsByOrderNo as $tickets) {
            $this->parseEvent($email, $tickets);
        }
    }

    private function parseEvent(Email $email, array $tickets): void
    {
        $ev = $email->add()->event();
        $ev->place()->type(Event::TYPE_EVENT);

        $seats = $travellers = $currencies = $amounts = [];

        foreach ($tickets as $i => $text) {
            $text = preg_replace("/^.*?\n{3,}(.+)$/s", '$1', $text);

            $tablePos = [0];

            if (preg_match("/^(.{2,} ){$this->opt($this->t('orderNumber'))}[ ]*:/m", $text, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($text, $tablePos);

            if (count($table) !== 2) {
                $email->add()->cruise(); // for 100% fail

                return;
            }

            // seat (start)
            $seatComponents = [];
            $seatsText = $this->re("/^([ ]*(?:{$this->opt($this->t('Section'))}|{$this->opt($this->t('Row'))}|{$this->opt($this->t('Seat'))}).*\n+[\s\S]+?)\n+[ ]*{$this->opt($this->t('Purchased by'))}[ ]*:/m", $table[0]);
            $seatTablePos = [0];

            if (preg_match("/^(.{2,} ){$this->opt($this->t('Section'))}\b/m", $seatsText, $matches)) {
                $seatTablePos[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^(.{2,} ){$this->opt($this->t('Row'))}\b/m", $seatsText, $matches)) {
                $seatTablePos[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^(.{2,} ){$this->opt($this->t('Seat'))}\b/m", $seatsText, $matches)) {
                $seatTablePos[] = mb_strlen($matches[1]);
            }

            $seatTable = $this->splitCols($seatsText, $seatTablePos);
            $seatsText = implode("\n\n\n\n\n\n\n", $seatTable);

            if (preg_match("/^[ ]*({$this->opt($this->t('Section'))})[: ]*\n{1,2}[ ]*([^:\n]+)$/m", $seatsText, $m)) {
                $seatComponents[] = $m[1] . ': ' . $m[2];
            }

            if (preg_match("/^[ ]*({$this->opt($this->t('Row'))})[: ]*\n{1,2}[ ]*([^:\n]+)$/m", $seatsText, $m)) {
                $seatComponents[] = $m[1] . ': ' . $m[2];
            }

            if (preg_match("/^[ ]*({$this->opt($this->t('Seat'))})[: ]*\n{1,2}[ ]*([^:\n]+)$/m", $seatsText, $m)) {
                $seatComponents[] = $m[1] . ': ' . $m[2];
            }

            if (count($seatComponents) > 0) {
                $seats[] = implode(', ', $seatComponents);
            }
            // seat (end)

            if (preg_match("/^[ ]*{$this->opt($this->t('Purchased by'))}[ ]*[:]+\s+([[:alpha:]][-.\'’[:alpha:]\s]*[[:alpha:]])\n+{$this->opt($this->t('Price'))}[ ]*:/mu", $table[0], $m)) {
                $travellers[] = preg_replace('/\s+/', ' ', $m[1]);
            }

            $totalPrice = $this->re("/^[ ]*{$this->opt($this->t('Price'))}[ ]*[:]+\s+(.*\d.*?)\s*$/mus", $table[0]);

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
                // $ 20.00
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $currencies[] = $matches['currency'];
                $amounts[] = PriceHelper::parse($matches['amount'], $currencyCode);
            }

            if ($i > 0) {
                continue;
            }

            $beforeOrderText = $this->re("/^(.{3,}?)\n+[ ]*{$this->opt($this->t('orderNumber'))}[ ]*:/s", $table[1]);
            $barCode = $this->re("/^.{40,}[ ]{3}([-A-Z\d]{7,14})$/m", $beforeOrderText);

            if ($barCode) {
                // remove barcode
                $beforeOrderText = preg_replace("/[ ]+{$this->opt($barCode)}$/m", '', $beforeOrderText);
            }

            // 4:19PM    |    4:19P    |    2:00 p. m.    |    3pm
            $patterns['time'] = '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]?\.?)?';

            $patterns['date'] = '(?:'
                . '[-[:alpha:]]+[, ]+[[:alpha:]]+[ ]+\d{1,2}[ ]*,[ ]*\d{2,4}' // Wed, Jun 8, 2022
                . '|[-[:alpha:]]+[, ]+\d{1,2}\/\d{1,2}\/\d{2,4}' // SUNDAY, 5/28/23
                . '|[[:alpha:]]{3,}[ ]*,[ ]*[[:alpha:]]+[ ]+\d{1,2}' // Sun, Jul 23
                . '|[[:alpha:]]{3,}[ ]*,[ ]*\d{1,2}[ ]+[[:alpha:]]+' // Sun, 23 Jul
            . ')';

            $dateStart = $dateEnd = $timeOpen = $timeStart = $timeEnd = null;

            if (preg_match("/^\s*{$this->opt($this->t('DOORS OPEN AT'))}\s*({$patterns['time']}).*\n+([\s\S]+)$/i", $beforeOrderText, $m)) {
                $timeOpen = $m[1];
                $beforeOrderText = $m[2];
            }

            if (preg_match("/^\s*(?<name>[\s\S]{2,}?)\n+[ ]*(?<date>.*{$patterns['date']}.*)(?:\n{1,2}(?<times>.{2,}))?\n+(?<address>[\s\S]{3,}?)\s*$/u", $beforeOrderText, $matches)) {
                $eventName = preg_replace([
                    '/\s+/',
                    '/^THIS IS YOUR TICKET[: ]+/i',
                    '/[ ]+PLEASE HAVE READY TO PRESENT AT GATE$/i',
                ], [
                    ' ',
                    '',
                    '',
                ], $matches['name']);

                if (preg_match("/^(.{2,}?)[ ]+[-–]+[ ]+({$patterns['time']})$/", $eventName, $m)) {
                    $eventName = $m[1];
                    $timeStart = $m[2];
                }

                $ev->place()->name($eventName)->address(preg_replace('/\s+/', ' ', $matches['address']));

                $timeOpen = (empty($matches['times']) ? null : $this->re("/\b{$this->opt($this->t('Doors'))}[:\s]*({$patterns['time']})\b/i", $matches['times'])) ?? $timeOpen;
                $timeStart = (empty($matches['times']) ? null : $this->re("/\b{$this->opt($this->t('Show'))}[:\s]*({$patterns['time']})\b/i", $matches['times'])) ?? $timeStart;
                $timeEnd = empty($matches['times']) ? null : $this->re("/\b{$this->opt($this->t('Ends'))}[:\s]*({$patterns['time']})\b/i", $matches['times']);

                if (!$timeStart) {
                    $timeStart = $timeOpen;
                }

                if (preg_match("/^(?<date1>{$patterns['date']})(?:[-–, ]+(?<time1>{$patterns['time']}))?\s+[-–]+\s+(?<date2>{$patterns['date']})(?:[-–, ]+(?<time2>{$patterns['time']}))?$/", $matches['date'], $m)) {
                    // Wed Feb 15, 2023 05:30PM - Sun Feb 19, 2023
                    $dateStart = $m['date1'];
                    $dateEnd = $m['date2'];

                    if (!empty($m['time1'])) {
                        $timeStart = $m['time1'];
                    }

                    if (!empty($m['time2'])) {
                        $timeEnd = $m['time2'];
                    }

                    if (!$timeEnd) {
                        $timeEnd = '23:59';
                    }
                } elseif (preg_match("/^(?<date>{$patterns['date']})[-–, ]+(?<time>{$patterns['time']})$/", $matches['date'], $m)) {
                    // Sun Mar 17, 2019 03:00PM
                    $dateStart = $dateEnd = $m['date'];
                    $timeStart = $m['time'];
                } elseif (preg_match("/^{$patterns['date']}$/", $matches['date'])) {
                    // Sun Mar 17, 2019
                    $dateStart = $dateEnd = $matches['date'];
                }

                $year = $this->re("/©\s*(\d{4})[,.\s]*See Tickets/i", $text);

                if ($dateStart && $timeStart) {
                    $ev->booked()->start(strtotime($this->normalizeTime($timeStart), $this->preprocessingDate($dateStart, $year)));
                } elseif ($dateStart && empty($matches['times'])) {
                    $ev->booked()->start($this->preprocessingDate($dateStart, $year));
                }

                if ($dateEnd && $timeEnd) {
                    $bookedEnd = strtotime($this->normalizeTime($timeEnd), $this->preprocessingDate($dateEnd, $year));
                    $ev->booked()->end($ev->getStartDate() && $bookedEnd && $ev->getStartDate() > $bookedEnd ? strtotime('+1 days', $bookedEnd) : $bookedEnd);
                } elseif (!$timeEnd) {
                    $ev->booked()->noEnd();
                }
            }

            if (preg_match("/(?:^[ ]*|[ ]{2})({$this->opt($this->t('orderNumber'))})[ ]*[:]+[ ]*([-A-z\d]{4,})(?:[ ]{2}|$)/m", $table[1], $m)) {
                $ev->general()->confirmation($m[2], $m[1]);
            }
        }

        if (count($seats) > 0) {
            $ev->booked()->seats(array_unique($seats));
        }

        if (count($travellers) > 0) {
            $ev->general()->travellers(array_unique($travellers), true);
        }

        if (in_array(null, $amounts, true)) {
            $amounts = [];
        }

        if (count($amounts) > 0 && count(array_unique($currencies)) === 1) {
            $email->price()->currency($currencies[0])->cost(array_sum($amounts));
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['pageDelimiter']) || empty($phrases['orderNumber'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['pageDelimiter']) !== false
                && $this->strposArray($text, $phrases['orderNumber']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function opt($field, bool $addSpaces = false): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) use ($addSpaces) {
            return $addSpaces ? $this->addSpacesWord($s) : preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function addSpacesWord(string $text): string
    {
        return preg_replace('/(\S)/u', '$1 *', preg_quote($text, '/'));
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
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

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    /**
     * Dependencies `$this->normalizeDate()`.
     *
     * @param string|null $d Unformatted string with date
     * @param string|null $y String with year
     *
     * @return int|false
     */
    private function preprocessingDate(?string $d, ?string $y)
    {
        if (preg_match("/^(?<wday>[-[:alpha:]]+)[,\s]+(?<date>[[:alpha:]]+[.\s]+\d{1,2}|\d{1,2}[.\s]+[[:alpha:]]+)$/u", $d, $m)) {
            $dateNormal = $this->normalizeDate($m['date']);
            $weekDateNumber = WeekTranslate::number1($m['wday']);

            return EmailDateHelper::parseDateUsingWeekDay($dateNormal . ' ' . $y, $weekDateNumber);
        }

        return strtotime($d);
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^([[:alpha:]]+)\s+(\d{1,2})$/u', $text, $m)) {
            // Jul 23
            $month = $m[1];
            $day = $m[2];
            $year = '';
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace('/(\d)[ ]*([AaPp])\.?$/', '$1 $2m', $s); // 9:30P    ->    9:30 PM

        return $s;
    }
}
