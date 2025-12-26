<?php

namespace AwardWallet\Engine\astana\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Common\Parser\Util\PriceHelper;

class AirTicketPdf extends \TAccountChecker
{
    public $mailFiles = "astana/it-792048038.eml, astana/it-793824748-ru.eml";

    public $lang = '';

    public static $dictionary = [
        'ru' => [
            'Departure' => ['Вылет'],
            'Arrival' => ['Прилет'],
            'Flight/Class' => 'Рейс/Класс',
            'Baggage' => 'Багаж',
            'receiptDetailsStart' => ['Данные о билете'],
            'receiptDetailsEnd' => ['Пассажиры'],
            'IATA code' => 'ИАТА код',
            'confNumber' => 'Номер брони',
            'Ticket number' => 'Номер билета',
            'passengerStart' => ['Пассажиры'],
            'passengerEnd' => ['Маршрут'],
            'Passenger name' => 'Имя пассажира',
            'Identification' => 'Документ',
            'Frequent flyer member number' => 'Номер участника Nomad Club',
            'segmentsStart' => ['Маршрут'],
            'segmentsEnd' => ['Ручная кладь', 'Данные об оплате'],
            'priceStart' => ['Данные об оплате'],
            'Ticket' => 'Билет',
            'totalPrice' => 'Итого, включая НДС',
            'Air fare' => 'Тариф',
            'Tax' => 'Сборы',
            'feeNames' => ['Сервисный сбор без НДС', 'НДС на сервисный сбор (12%)'],
        ],
        'en' => [
            'Departure' => ['Departure'],
            'Arrival' => ['Arrival'],
            // 'Flight/Class' => '',
            // 'Baggage' => '',
            'receiptDetailsStart' => ['Receipt details'],
            'receiptDetailsEnd' => ['Passenger'],
            // 'IATA code' => '',
            'confNumber' => 'Booking',
            // 'Ticket number' => '',
            'passengerStart' => ['Passenger'],
            'passengerEnd' => ['Route'],
            // 'Passenger name' => '',
            // 'Identification' => '',
            // 'Frequent flyer member number' => '',
            'segmentsStart' => ['Route'],
            'segmentsEnd' => ['Cabin baggage', 'Payment details'],
            'priceStart' => ['Payment details'],
            // 'Ticket' => '',
            'totalPrice' => 'Total including VAT',
            // 'Air fare' => '',
            // 'Tax' => '',
            'feeNames' => ['Fee without VAT', 'VAT on service fee (12%)'],
        ],
    ];

    private function parsePdf(Email $email, string $text): void
    {
        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:]\s]*[[:alpha:]]', // Mr. Hao-Li Huang
            'travellerName2' => '[[:alpha:]]+(?:\s+[[:alpha:]]+)*\s*\/\s*(?:[[:alpha:]]+\s+)*[[:alpha:]]+', // Chaaibi/Said MR
            'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
        ];

        $f = $email->add()->flight();

        $detailsSrc = $this->re("/^[ ]*{$this->opt($this->t('receiptDetailsStart'))}(?:[ ]{2,}\S.*)?\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('receiptDetailsEnd'))}(?:[ ]{2,}\S|$)/m", $text);
        
        $tablePos = [0];
        if (preg_match("/^(.{40,} ){$this->opt($this->t('IATA code'))}/m", $detailsSrc, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($detailsSrc, $tablePos);
        $details = implode("\n\n", $table);

        if (preg_match("/^[ ]*({$this->opt($this->t('confNumber'))})[: ]+([A-Z\d]{5,10})$/m", $details, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        $ticket = $this->re("/^[ ]*{$this->opt($this->t('Ticket number'))}[: ]+({$patterns['eTicket']})$/m", $details);

        $passengerText = $this->re("/^[ ]*{$this->opt($this->t('passengerStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('passengerEnd'))}$/m", $text);

        $tablePos = [0];
        if (preg_match("/^(([ ]*{$this->opt($this->t('Passenger name'))}[: ]+){$this->opt($this->t('Identification'))}[: ]+){$this->opt($this->t('Frequent flyer member number'))}[: ]*\n/i", $passengerText, $matches)) {
            $tablePos[] = mb_strlen($matches[2]);
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($passengerText, $tablePos);

        $passengerName = count($table) > 0 && preg_match("/^\s*{$this->opt($this->t('Passenger name'))}[:\s]+({$patterns['travellerName']}|{$patterns['travellerName2']})\s*$/u", $table[0], $m)
            ? $this->normalizeTraveller(preg_replace('/\s+/', ' ', $m[1])) : null;
        $f->general()->traveller($passengerName, true);

        if (count($table) > 2 && preg_match("/^\s*({$this->opt($this->t('Frequent flyer member number'))})[:\s]+([^:\s].+\S)\s*$/", $table[2], $m)) {
            $f->program()->account($m[2], false, $passengerName, preg_replace('/\s+/', ' ', $m[1]));
        }

        if ($ticket) {
            $f->issued()->ticket($ticket, false, $passengerName);
        }

        $segmentsText = $this->re("/^[ ]*{$this->opt($this->t('segmentsStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('segmentsEnd'))}(?:[ ]{2}|$)/m", $text);
        $segments = $this->splitText($segmentsText, "/^([ ]*{$this->opt($this->t('Departure'))}[ ]+{$this->opt($this->t('Arrival'))} )/im", true);

        foreach ($segments as $segText) {
            $s = $f->addSegment();

            $tablePos = [0];
            if (preg_match("/^((([ ]*{$this->opt($this->t('Departure'))}[: ]+){$this->opt($this->t('Arrival'))}[: ]+){$this->opt($this->t('Flight/Class'))}[: ]+){$this->opt($this->t('Baggage'))}[: ]*\n/i", $segText, $matches)) {
                $tablePos[] = mb_strlen($matches[3]);
                $tablePos[] = mb_strlen($matches[2]);
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($segText, $tablePos);

            $pattern = "(?<time>{$patterns['time']}).*\n+[ ]*(?<date>.{4,}\b\d{4})\n+[ ]*(?<airport>\S[\s\S]+?)";

            if (count($table) > 0 && preg_match("/{$this->opt($this->t('Departure'))}[:\s]+{$pattern}(?:\n\n|\s*$)/i", $table[0], $m)) {
                $s->departure()->date(strtotime($m['time'], strtotime($m['date'])))->name(preg_replace('/\s+/', ' ', $m['airport']))->noCode();
            }

            if (count($table) > 1 && preg_match("/{$this->opt($this->t('Arrival'))}[:\s]+{$pattern}(?:\n\n|\s*$)/i", $table[1], $m)) {
                $s->arrival()->date(strtotime($m['time'], strtotime($m['date'])))->name(preg_replace('/\s+/', ' ', $m['airport']))->noCode();
            }

            if (count($table) > 2 && preg_match("/{$this->opt($this->t('Flight/Class'))}[:\s]+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)(?<class>\n+[ ]*[A-Z]{1,2})?\s*$/i", $table[2], $m)) {
                $s->airline()->name($m['name'])->number($m['number']);

                if (!empty($m['class'])) {
                    $s->extra()->bookingCode($m['class']);
                }
            }
        }

        $priceText = $this->re("/^[ ]*{$this->opt($this->t('priceStart'))}(?:\n+[ ]*{$this->opt($this->t('Ticket'))})?\n+([\s\S]+\n+[ ]*{$this->opt($this->t('totalPrice'))}.*)/m", $text);
        $totalPrice = $this->re("/\n[ ]*{$this->opt($this->t('totalPrice'))}[: ]{3,}([^: ].*)$/", $priceText);
        
        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // KZT 442556.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->re("/^\s*{$this->opt($this->t('Air fare'))}[: ]{3,}([^: ].*)\n/", $priceText);

            if ( preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m) ) {
                $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $taxText = $this->re("/^[ ]*{$this->opt($this->t('Tax'))}[: ]{3,}([^: ].*(?:\n+[ ]{30,}[^: ].*)*)\n/m", $priceText) ?? '';
            
            preg_match_all('/\s+(?<currency>[A-Z]{3})\s+(?<amount>\d[,.‘\'\d ]*?) ?(?<name>[A-Z][A-Z\d])\b/', ' ' . $taxText, $taxMatches, PREG_SET_ORDER);

            foreach ($taxMatches as $m) {
                if ($m['currency'] === $matches['currency']) {
                    $f->price()->fee($m['name'], PriceHelper::parse($m['amount'], $currencyCode));
                }
            }

            preg_match_all("/^[ ]*(?<name>{$this->opt($this->t('feeNames'))})[: ]{3,}(?<charge>[^: ].*)$/m", $priceText, $feeMatches, PREG_SET_ORDER);

            foreach ($feeMatches as $m) {
                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $m['charge'], $m2)) {
                    $f->price()->fee($m['name'], PriceHelper::parse($m2['amount'], $currencyCode));
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]airastana\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }
        return array_key_exists('subject', $headers) && preg_match('/Әуебилет\s*\/\s*Авиабилет\s*\/\s*Ticket/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProv = $this->detectEmailFromProvider( rtrim($parser->getHeader('from'), '> ') );

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || !$detectProv
                && stripos($textPdf, 'www.airastana.com') === false
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || !$this->assignLang($textPdf)) {
                continue;
            }

            $this->parsePdf($email, $textPdf);
        }

        $email->setType('AirTicketPdf' . ucfirst($this->lang));
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

    private function assignLang(?string $text): bool
    {
        if ( empty($text) || !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['Departure']) || empty($phrases['Arrival']) ) {
                continue;
            }
            if (preg_match("/^[ ]*{$this->opt($phrases['Departure'])}[ ]+{$this->opt($phrases['Arrival'])} /im", $text)) {
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

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MSTR|MISS|MRS|MR|MS|DR)';

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
