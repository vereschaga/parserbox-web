<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FiveStartReceipt extends \TAccountChecker
{
    public $mailFiles = "aa/it-628745982.eml, aa/it-739092117.eml";
    public $subjects = [
        'Five-Star Receipt for Booking',
    ];

    public $lang = 'en';

    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (stripos($text, 'American Airlines Five Star Service') !== false
                    && (stripos($text, 'Connection Assistance') !== false || stripos($text, 'Arrival Assistance') !== false || stripos($text, 'Departure Assistance') !== false)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aa\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                $this->ParseEventPDF($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseEventPDF(Email $email, string $text): void
    {
        $patterns = [
            'date' => '\b\d{1,2}-[[:alpha:]]+-\d{4}\b', // 04-SEP-2024
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        ];

        $event = $email->add()->event();
        $event->type()->event();

        $event->general()
            ->confirmation($this->re("/^[ ]*Booking[#\:\s]*([-\d]{5,25}?)(?:[ ]{2}|$)/m", $text));

        $price = $this->re("/(?:\n[ ]*|[ ]{2}){$this->opt($this->t('Total:'))}[ ]*(\S{1,3}[ ]+\d[\d.,]*)\n/", $text);

        if (preg_match("/^\s*(?<currency>\S{1,3})\s*(?<total>\d[\d\.\,]*)\s*$/", $price, $m)) {
            $event->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $travellersText = $this->re("/\n([ ]*Passenger Details[:\s]+(?:.+\n+){1,20}?)[ ]*Airport Details/u", $text);
        $paxTable = $this->splitCols($travellersText, [0, 50]);
        $event->general()
            ->travellers(array_filter(explode("\n", $this->re("/Passenger Details[\:\s]+(.+)/s", $paxTable[0]))));

        $segText = $this->re("/(\w+ Assistance[ ]*:.+\n[ ]*(?:Arrival Time|Departure Time)\s*:.+)/s", $text);

        if (!$segText) {
            $this->logger->debug('Event info not found!');

            return;
        }

        $dateStart = $timeStart = $dateEnd = $timeEnd = null;

        preg_match_all("/(?:Connection Assistance|Arrival Assistance|Departure Assistance)[ ]*[:]+.*\n+.+[ ]{5}({$patterns['date']})(?:[ ]{2}|\n)/u", $segText, $dateMatches);

        if (count($dateMatches[1]) > 0) {
            $dateStart = $dateMatches[1][0];
        }

        if (count($dateMatches[1]) > 1) {
            $dateEnd = $dateMatches[1][count($dateMatches[1]) - 1];
        } elseif (count($dateMatches[1]) === 1) {
            $dateEnd = $dateStart;
        }

        preg_match_all("/(?:Arrival Time|Departure Time)[ ]*[:]+[ ]*({$patterns['time']})/", $segText, $timeMatches);

        if (count($timeMatches[1]) > 0) {
            $timeStart = $timeMatches[1][0];
        }

        if (count($timeMatches[1]) > 1) {
            $timeEnd = $timeMatches[1][count($timeMatches[1]) - 1];
        }

        if ($dateStart && $timeStart) {
            $event->booked()->start(strtotime($timeStart, strtotime($dateStart)));
        }

        if ($dateEnd && $timeEnd) {
            $event->booked()->end(strtotime($timeEnd, strtotime($dateEnd)));
        } elseif (!$timeEnd
            && (!preg_match("/Arrival Time[ ]*:/", $segText) || !preg_match("/Departure Time[ ]*:/", $segText))
        ) {
            $event->booked()->noEnd();
        }

        $event->setName('American Airlines Five Star Service');

        $guestsCount = $this->re("/Five Star Service Desk at.*\n[ ]*Adults?[ ]*:\s*(\d{1,3})(?:\b|\D)/", $text)
            ?? $this->re("/Five Star Service.*\n.*Adults?[ ]*:\s*(\d{1,3})(?:\b|\D)/", $text);
        $event->booked()->guests($guestsCount);

        $kidsCount = $this->re("/\n.*[ ]{2}Adults?[ ]*:(?:.*\n){1,2}.*[ ]{2}Children[ ]*:\s*(\d{1,3})(?:\b|\D)/", $text);

        if ($kidsCount !== null) {
            $event->booked()->kids($kidsCount);
        }

        if (preg_match("/\w Assistance[ ]*:.*\n+[ ]*(.{2,}?)(?:[ ]{5}|\n)/", $segText, $m)) {
            $addressParts = [];

            if (preg_match("/^([A-Z]{3})\s*-\s*(.{2,})$/", $m[1], $m2)) {
                $addressParts[] = $m2[1];
                $m[1] = $m2[2];
            } else {
                $addressParts[] = $this->re("/Airport Code[ ]*[:]+[ ]*([A-Z]{3})\b.*\n+[ ]*Airport Type[ ]*[:]+[ ]*(?i)Connect/", $segText);
            }

            $addressParts[] = $m[1];
            $event->place()->address(implode(', ', array_unique(array_filter($addressParts))));
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
