<?php

namespace AwardWallet\Engine\zipair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingInvoicePdf extends \TAccountChecker
{
    public $mailFiles = "zipair/it-476514234.eml, zipair/it-367579910-ja.eml";

    public $lang = '';

    public static $dictionary = [
        'ja' => [
            'confNumber'     => '予約番号',
            'BOOKING DATE'   => '予約日（GMT表示）',
            'RECEIVED FROM'  => '宛名',
            'THE SUM OF'     => '金額',
            'segmentsStart'  => 'お支払い情報',
            'segmentsEnd'    => ['合計', '合 計'],
            'Segment'        => ['旅程', '旅 程'],
            'paymentHeaders' => ['運賃', '諸税', '手数料', 'サービスパッケージ'],
            'TOTAL CHARGES'  => ['合計', '合 計'],
        ],
        'en' => [
            'confNumber' => 'CONFIRMATION NUMBER',
            // 'BOOKING DATE' => '',
            // 'RECEIVED FROM' => '',
            // 'THE SUM OF' => '',
            'segmentsStart' => 'Payment Information',
            'segmentsEnd'   => 'TOTAL CHARGES',
            // 'Segment' => '',
            'paymentHeaders' => ['FARE', 'TAXES', 'MEALS', 'SERVICES', 'FEE CHARGES', 'SEAT ASSIGNMENTS', 'SERVICE BUNDLES'],
            // 'TOTAL CHARGES' => '',
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@zipair.net') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'ZIPAIR - Booking Invoice #') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (stripos($textPdf, '.zipair.net/') === false
                && stripos($textPdf, 'www.zipair.net') === false
                && stripos($textPdf, '@zipair.net') === false
                && stripos($textPdf, 'ZIPAIR Tokyo Inc') === false
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

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);
            }
        }

        $email->setType('BookingInvoicePdf' . ucfirst($this->lang));

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
        $f = $email->add()->flight();

        if (preg_match("/^[➤> ]*({$this->opt($this->t('confNumber'))})[: ]+([A-Z\d]{5,15})[➤> ]+{$this->opt($this->t('BOOKING DATE'))}/m", $text, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        $bookingDate = strtotime($this->normalizeDate($this->re("/[ ]{2}[➤> ]*{$this->opt($this->t('BOOKING DATE'))}[ ]+(.*\b\d{4}\b.*)$/m", $text)));
        $f->general()->date($bookingDate);

        $travellerParts = [];

        if (preg_match("/\n[ ]*(.*)\n[➤> ]*{$this->opt($this->t('RECEIVED FROM'))}\s/", $text, $m)) {
            $travellerParts[] = $m[1];
        }

        if (preg_match("/\n[➤> ]*{$this->opt($this->t('RECEIVED FROM'))}\s+([\s\S]*?)\n+[➤> ]*{$this->opt($this->t('THE SUM OF'))}\s/", $text, $m)) {
            $travellerParts[] = $m[1];
        }

        $travellerText = preg_replace('/\s+/', ' ', implode(' ', array_filter($travellerParts)));
        $traveller = $this->re("/^\s*({$this->patterns['travellerName']})\s*$/u", $travellerText);
        $f->general()->traveller($traveller, true);

        $segmentsText = $this->re("/\n[ ]*{$this->opt($this->t('segmentsStart'))}\n+([\s\S]+)\n+[ ]*{$this->opt($this->t('segmentsEnd'))}/", $text);
        $segments = $this->splitText($segmentsText, "/^([ ]*{$this->opt($this->t('Segment'))}[ ]+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d+)$/m", true);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            $routeText = '';

            if (preg_match("/^[ ]*{$this->opt($this->t('Segment'))}[ ]+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<number>\d+)\n+(?<route>.+?)\n+[ ]*{$this->opt($this->t('paymentHeaders'))}/s", $sText, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
                $routeText = $m['route'];
            }

            $tablePos = [0];

            if (preg_match("/^(.+[ ]{2})[✈]+[ ]{2}.+$/m", $routeText, $m)) {
                $tablePos[] = mb_strlen($m[1]);
            }
            $table = $this->splitCols($routeText, $tablePos);

            if (count($table) !== 2) {
                $this->logger->debug('Wrong flight segment!');

                continue;
            }

            $table[1] = preg_replace('/^[✈]+ /m', '  ', $table[1]);

            if (preg_match($pattern = "/^\s*(?<airport>[\s\S]{3,}?)\n+[ ]*(?<dateTime>.*\b\d{4}\b.*?\s+{$this->patterns['time']})/", $table[0], $m)) {
                $s->departure()
                    ->name(preg_replace('/\s+/', ' ', $m['airport']))->noCode()
                    ->date(strtotime($this->normalizeDate($m['dateTime'])));
            }

            if (preg_match($pattern, $table[1], $m)) {
                $s->arrival()
                    ->name(preg_replace('/\s+/', ' ', $m['airport']))->noCode()
                    ->date(strtotime($this->normalizeDate($m['dateTime'])));
            }
        }

        if (!preg_match_all("/^[ ]*{$this->opt($this->t('TOTAL CHARGES'))}[: ]+(.*\d.*)$/m", $text, $totalMatches)) {
            return;
        }

        $currencies = $totalAmounts = [];

        foreach ($totalMatches[1] as $totalPrice) {
            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
                // ¥ 32,428    |    $ 2,097.46
                $currency = $this->normalizeCurrency($matches['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $currencies[] = $currency;
                $totalAmounts[] = PriceHelper::parse($matches['amount'], $currencyCode);
            }
        }

        if (count(array_unique($currencies)) === 1) {
            $f->price()->currency($currencies[0])->total(array_sum($totalAmounts));
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['segmentsStart'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['confNumber']) !== false
                && $this->strposArray($text, $phrases['segmentsStart']) !== false
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 2023/06/15 (THU) 04:29
            "/^(\d{4})[ ]*\/[ ]*(\d{1,2})[ ]*\/[ ]*(\d{1,2})[ ]*\([ ]*[-[:alpha:]]+[ ]*\)[ ]*({$this->patterns['time']})$/u",
        ];
        $out = [
            '$1-$2-$3 $4',
        ];

        return preg_replace($in, $out, $text);
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'SGD' => ['S$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
