<?php

namespace AwardWallet\Engine\trepublic\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Common\Parser\Util\PriceHelper;

class VoucherPdf extends \TAccountChecker
{
    public $mailFiles = "trepublic/it-861927579.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'flightStart' => ['Get ready to fly'],
            'hotelStart' => ['Your next adventure awaits'],
            'transferStart' => ['Big wheels keep on turning'],
            'flightDirection' => ['OutBound Flight', 'InBound Flight'],
        ]
    ];

    private $patterns = [
        // Fri 21 Feb 2025  |  21 Feb 2025
        'date' => '\b(?:[-[:alpha:]]+[,. ]+)?\d{1,2}[,. ]+[[:alpha:]]+[,. ]+\d{4}\b',
        // 4:19PM  |  2:00 p. m.
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
        // Mr. Hao-Li Huang
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
    ];

    private $otaConfNumbers = [], $airportEntry = null, $airportExit = null;

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]travelrepublic\.co\.uk$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return array_key_exists('subject', $headers)
            && stripos($headers['subject'], 'Travel Republic booking confirmation - BND') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf || stripos($textPdf, 'Your Travel Republic Reference') === false) {
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);

                break;
            }
        }
        
        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('VoucherPdf' . ucfirst($this->lang));

        return $email;
    }

    private function parsePdf(Email $email, string $text): void
    {
        $sections = $this->splitText($text, "/^[ ]*({$this->opt($this->t('flightStart'))}|{$this->opt($this->t('hotelStart'))}|{$this->opt($this->t('transferStart'))}|{$this->opt($this->t('Payment summary'))})/im", true);

        foreach ($sections as $secText) {
            if (preg_match("/^[ ]*(?:{$this->opt($this->t('Your'))}[ ]+)?({$this->opt($this->t('Travel Republic Reference'))})[: ]+(?-i)(?:[A-Z]+[ ]*\/[ ]*)?([-A-Z\d]{4,25})$/im", $secText, $m)
                && !in_array($m[2], $this->otaConfNumbers)
            ) {
                $email->ota()->confirmation($m[2], $m[1]);
                $this->otaConfNumbers[] = $m[2];
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('flightStart'))}/i", $secText)) {
                $this->parseFlight($email, $secText);
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('hotelStart'))}/i", $secText)) {
                $this->parseHotel($email, $secText);
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('transferStart'))}/i", $secText)) {
                $this->parseTransfer($email, $secText);
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('Payment summary'))}/i", $secText)) {
                $totalPrice = $this->re("/^[ ]*{$this->opt($this->t('Total holiday price'))}[: ]+(.*\d.*)$/m", $secText);

                if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.’‘\'\d ]*)$/u', $totalPrice, $matches)) {
                    // £ 1161.50
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                    $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
                }
            }
        }
    }

    private function parseFlight(Email $email, string $text): void
    {
        $f = $email->add()->flight();

        $confirmation = $this->re("/^[ ]*{$this->opt($this->t('Your'))}[ ]+{$this->opt($this->t('Travel Republic Reference'))}.+\n+[ ]*{$this->opt($this->t('Your'))}[ ]+\S.*?\S[ ]+{$this->opt($this->t('reference is'))}[: ]+(?-i)([A-Z\d][A-Z\d\/, ]{2,}[A-Z\d])$/im", $text);

        if ($confirmation) {
            $confNumbers = array_unique(preg_replace('/^[A-Z]+[ ]*\/[ ]*([A-Z\d]{3,30})$/', '$1', preg_split("/(?:\s*,\s*)+/", $confirmation)));
    
            foreach ($confNumbers as $confNo) {
                $f->general()->confirmation($confNo);
            }
        }

        $segmentsText = $this->re("/^([ ]*{$this->opt($this->t('flightDirection'))}[- ].+\n[\s\S]+?)\n+[ ]*{$this->opt($this->t('Who’s flying'))}[ ]*:/m", $text);

        $table = $this->splitCols($segmentsText, [0, 44]);

        foreach ($table as $i => $sText) {
            $s = $f->addSegment();

            $date = strtotime($this->re("/^[ ]*{$this->opt($this->t('flightDirection'))}[- ]+({$this->patterns['date']})\n/u", $sText));

            if (preg_match("/^[ ]*({$this->patterns['time']})[ ]*[-–]+[ ]*({$this->patterns['time']})\s/m", $sText, $m)) {
                $s->departure()->date(strtotime($m[1], $date));
                $s->arrival()->date(strtotime($m[2], $date));
            }

            $flight = $this->re("/\n[-– ]*(.{3,})\n+[ ]*{$this->patterns['time']}[ ]*[-–]+[ ]*{$this->patterns['time']}\s/", $sText);

            if ( preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3})\s*(?<number>\d+)$/", $flight, $m) ) {
                if (in_array($m['name'], ['EZY'])) {
                    $m['name'] = 'U2';
                } elseif (in_array($m['name'], ['TOM'])) {
                    $m['name'] = 'BY';
                }

                $s->airline()->name($m['name'])->number($m['number']);
            }

            $airports = $this->re("/^[ ]*{$this->patterns['time']}[ ]*[-–]+[ ]*{$this->patterns['time']}.*\n+[ ]*(\S.+)/m", $sText);

            $codeDep = $codeArr = null;

            if (preg_match("/^(.{2,})\s+{$this->opt($this->t('to'))}\s+(.{2,})$/", $airports, $matches)) {
                $codeDep = $this->re("/^([A-Z]{3})\b/", $matches[1]);
                $codeArr = $this->re("/^([A-Z]{3})\b/", $matches[2]);

                if ($codeDep) {
                    $s->departure()->code($codeDep);
                }

                if ($codeArr) {
                    $s->arrival()->code($codeArr);

                    if ($i === 0 && count($table) === 2) {
                        $this->airportEntry = $codeArr;
                    }
                }

                if ($matches[1] !== $codeDep) {
                    $s->departure()->name($matches[1]);
                }

                if ($matches[2] !== $codeArr) {
                    $s->arrival()->name($matches[2]);
                }
            }
        }

        if (count($table) === 2) {
            $this->airportExit = $codeDep;
        }

        $travellers = [];

        $travellersText = $this->re("/^[ ]*{$this->opt($this->t('Who’s flying'))}[ ]*[:]+\n{1,2}[ ]*(\S[\s\S]+?)\n(?:\n|[ ]*{$this->opt($this->t('Airline reference'))})/m", $text);
        $travellersRows = preg_split("/[ ]*\n[ ]*/", $travellersText);

        foreach ($travellersRows as $tRow) {
            if (preg_match("/^({$this->patterns['travellerName']})[ ]+(?i)(?:ADULT|CHILD)$/u", $tRow, $m)
                || preg_match("/^({$this->patterns['travellerName']})$/u", $tRow, $m)
            ) {
                $travellers[] = $m[1];
            } else {
                $travellers = [];

                break;
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, true);
        }
    }

    private function parseHotel(Email $email, string $text): void
    {
        $h = $email->add()->hotel();

        $confirmation = $this->re("/(?:^[ ]*|:\s*){$this->opt($this->t('Your Hotel reference is'))}[: ]+(?-i)([A-Z\d][A-Z\d\/, ]{2,}[A-Z\d])$/im", $text);

        if ($confirmation) {
            $confNumbers = array_unique(preg_replace('/^[A-Z]+[ ]*\/[ ]*([A-Z\d]{3,30})$/', '$1', preg_split("/(?:\s*,\s*)+/", $confirmation)));
    
            foreach ($confNumbers as $confNo) {
                $h->general()->confirmation($confNo);
            }
        } elseif (preg_match("/Your hotel reference number will be sent to you on/i", $text)
            || !preg_match("/{$this->opt($this->t('Your Hotel reference is'))}/i", $text)
            && !preg_match("/^[ ]*{$this->opt($this->t('My hotel reference'))}\s*:/i", $text)
        ) {
            $h->general()->noConfirmation();
        }

        if (preg_match("/\n{2}[ ]+(.{2,}(?:\n[ ]+.{2,})?)\n{1,3}.+,[ ]*\d{1,3}[ ]*{$this->opt($this->t('Night'))}/i", $text, $m)) {
            $hotelName = preg_replace('/\s+/', ' ', $m[1]);
        } else {
            $hotelName = null;
        }

        $roomsText = $this->re("/\n([ ]*{$this->opt($this->t('Room'))}[ ]*\d{1,3}\n[\s\S]+)\n[ ]*{$this->opt($this->t('Check in'))}[: ]+{$this->opt($this->t('Check out'))}/im", $text);

        $roomsSections = $this->splitText($roomsText, "/^[ ]*{$this->opt($this->t('Room'))}[ ]*\d{1,3}$/im");

        foreach ($roomsSections as $roomSection) {
            $room = $h->addRoom();
            $room->setType($this->re("/^\s*(\S.+)/", $roomSection));
        }

        $checkIn = $checkOut = null;

        if (preg_match("/\n[ ]*{$this->opt($this->t('Check in'))}[: ]+{$this->opt($this->t('Check out'))}[: ]*\n+[ ]*({$this->patterns['date']})[ ]+({$this->patterns['date']})\n/imu", $text, $m)) {
            $checkIn = strtotime($m[1]);
            $checkOut = strtotime($m[2]);
        }

        $h->booked()->checkIn($checkIn)->checkOut($checkOut);

        $guestsText = $this->re("/\n[ ]*{$this->opt($this->t('Check in'))}[: ]+{$this->opt($this->t('Check out'))}[: ]*((?:\n+.+){2,3})/i", $text);

        if (preg_match("/\b(\d{1,3})[ ]*{$this->opt($this->t('Adult'))}/i", $guestsText, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})[ ]*{$this->opt($this->t('Child'))}/i", $guestsText, $m)) {
            $h->booked()->kids($m[1]);
        }

        if (preg_match("/\n[ ]*{$this->opt($this->t('Hotel address'))}[ ]*[:]+[ ]*(.{3,}(?:\n.{2,})?)\n(?:[ ]*{$this->opt($this->t('My hotel reference'))}[ ]*:|\n)/i", $text, $m)) {
            $address = preg_replace(['/\s+/', '/(?:\s*,\s*)+/'], [' ', ', '], $m[1]);
        } else {
            $address = null;
        }

        $h->hotel()->name($hotelName)->address($address);

        if (preg_match("/^((?:\s*From {$this->patterns['date']} to {$this->patterns['date']} the cancell?ation charge is .+)+)/imu", $text, $m)) {
            $cancellation = preg_replace('/[ ]*\n+[ ]*/', '; ', trim($m[1]));
        } else {
            $cancellation = null;
        }

        $h->general()->cancellation($cancellation, false, true);
    }

    private function parseTransfer(Email $email, string $text): void
    {
        $confirmationTitle = $confirmation = null;

        if (preg_match("/^[ ]*({$this->opt($this->t('SUPPLIER REFERENCE'))})[: ]*\n{1,2}[ ]*(?:[A-Z]+[ ]*\/[ ]*)?([-A-Z\d]{4,30})$/m", $text, $m)) {
            $confirmationTitle = $m[1];
            $confirmation = $m[2];
        }

        $travellers = [];

        $travellersText = $this->re("/^[ ]*{$this->opt($this->t('Who’s riding'))}[ ]*[:]+\n{1,2}[ ]*(\S[\s\S]+?)\n(?:\n|[ ]*{$this->opt($this->t('Pick-up on arrival'))})/m", $text);
        $travellersRows = preg_split("/[ ]*\n[ ]*/", $travellersText);

        foreach ($travellersRows as $tRow) {
            if (preg_match("/^({$this->patterns['travellerName']})[ ]+(?i)(?:ADULT|CHILD)$/u", $tRow, $m)
                || preg_match("/^({$this->patterns['travellerName']})$/u", $tRow, $m)
            ) {
                $travellers[] = $m[1];
            } else {
                $travellers = [];

                break;
            }
        }

        $fromAirport = $backToAirport = null;

        if (preg_match("/^[ ]*{$this->opt($this->t('Pick-up on arrival'))}[ ]*[:]+\n{1,2}[ ]*(\S[\s\S]+?)\n(?:\n|[ ]*{$this->opt($this->t('Getting back to the airport'))})/m", $text, $m)) {
            $fromAirport = preg_replace('/\s+/', ' ', $m[1]);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Getting back to the airport'))}[ ]*[:]+\n{1,2}[ ]*(\S[\s\S]+?)\n(?:\n|[ ]*{$this->opt($this->t('Need to change or cancel your transfer booking'))})/m", $text, $m)) {
            $backToAirport = preg_replace('/\s+/', ' ', $m[1]);
        }

        if ($fromAirport !== null) {
            $t = $email->add()->transfer();
            $t->general()->confirmation($confirmation, $confirmationTitle);
    
            if (count($travellers) > 0) {
                $t->general()->travellers($travellers, true);
            }
    
            $s = $t->addSegment();

            if (preg_match("/^Your arrival transfer is booked for\s+(?<date>{$this->patterns['date']})\s*, from\s+(?<nameDep>.{3,135}?)[.\s]+This is to meet your flight arrival time of\s+(?<time>{$this->patterns['time']})\s*, with the reference\s+[A-Z\d][A-Z\d\/, ]{2,}[A-Z\d][.\s]+You’ll be dropped off at\s+(?<nameArr>.{3,135}?)[.\s]*$/iu", $fromAirport, $m)) {
                $dateDep = strtotime($m['date']);
                $s->departure()->date(strtotime($m['time'] . ' +30 minutes', $dateDep));
                
                $s->departure()->name($m['nameDep']);
                $s->arrival()->name($m['nameArr'])->noDate();

                if ($this->airportEntry) {
                    $s->departure()->code($this->airportEntry);
                }
            }
        }

        if ($backToAirport !== null) {
            $t = $email->add()->transfer();
            $t->general()->confirmation($confirmation, $confirmationTitle);
    
            if (count($travellers) > 0) {
                $t->general()->travellers($travellers, true);
            }
    
            $s = $t->addSegment();

            if (preg_match("/^Your departure transfer is booked for\s+(?<date>{$this->patterns['date']})\s*, heading to\s+(?<nameArr>.{3,135}?)[.\s]+You will be picked up from\s+(?<nameDep>.{3,135}?)[.\s]+This is for your flight at\s+(?<time>{$this->patterns['time']})\s*, with the reference\s+[A-Z\d][A-Z\d\/, ]{2,}[A-Z\d][.\s]*$/iu", $backToAirport, $m)) {
                $dateArr = strtotime($m['date']);
                $s->arrival()->date(strtotime($m['time'] . ' -3 hours', $dateArr));

                $s->departure()->name($m['nameDep'])->noDate();
                $s->arrival()->name($m['nameArr']);

                if ($this->airportExit) {
                    $s->arrival()->code($this->airportExit);
                }
            }
        }

        if (!isset($t)) {
            $email->add()->transfer(); // for 100% fail
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

    private function assignLang(?string $text): bool
    {
        if ( empty($text) || !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) ) {
                continue;
            }
            if (!empty($phrases['flightStart']) && preg_match("/^[ ]*{$this->opt($phrases['flightStart'])}/im", $text)
                || !empty($phrases['hotelStart']) && preg_match("/^[ ]*{$this->opt($phrases['hotelStart'])}/im", $text)
                || !empty($phrases['transferStart']) && preg_match("/^[ ]*{$this->opt($phrases['transferStart'])}/im", $text)
            ) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re(string $re, ?string $str, $c = 1): ?string
    {
        if (preg_match($re, $str ?? '', $m) && array_key_exists($c, $m)) {
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
            for ($i=0; $i < count($textFragments)-1; $i+=2)
                $result[] = $textFragments[$i] . $textFragments[$i+1];
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
}
