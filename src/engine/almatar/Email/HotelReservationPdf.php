<?php

namespace AwardWallet\Engine\almatar\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Common\Parser\Util\PriceHelper;

class HotelReservationPdf extends \TAccountChecker
{
    public $mailFiles = "almatar/it-650995617.eml, almatar/it-659611127.eml";

    private $subjects = [
        'en' => ['Booking Confirmation']
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'hotelStart' => ['Your Reservation details'],
            'checkIn' => ['Check-In'],
            'checkOut' => ['Check-Out'],
        ]
    ];

    private function parseHotelPdf(Email $email, string $text, string $textInvoice = ''): void
    {
        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $hotelRemarks = '';

        if (preg_match("/\n[ ]*{$this->opt($this->t('Hotel Remarks'))}[: ]*(?:[^:\s].*)?\n{1,5}[. ]*([\s\S]+?)(?:\n{2}|\s*$)/i", $text, $m)) {
            $hotelRemarks = preg_replace(['/-\n+/', '/\s+/'], ['-', ' '], $m[1]);
            $h->general()->notes($hotelRemarks);
        }

        $hotelText = $this->re("/^[ ]*{$this->opt($this->t('hotelStart'))}[: ]*\n+([\s\S]+?)\n+.*{$this->opt($this->t('checkIn'))}/m", $text) ?? '';

        // remove garbage
        $hotelText = preg_replace(["/Image not found or type unknown[ ]*/i", '/^(?:[ ]*\n)+/'], '', $hotelText);

        if (preg_match("/^[ ]*(\S.+)\n+.*\d[ ]*(?:{$this->opt($this->t('night'))}|{$this->opt($this->t('room'))})/i", $hotelText, $m)) {
            $h->hotel()->name($m[1])->noAddress();
        }

        if (preg_match("/\b(\d{1,3})[ ]*{$this->opt($this->t('room'))}/i", $hotelText, $m)) {
            $h->booked()->rooms($m[1]);
        }

        $hotelText2 = $this->re("/^(.*{$this->opt($this->t('checkIn'))}[\s\S]+?)\n+[ ]*{$this->opt($this->t('Name'))}[ ]*:/m", $text) ?? '';
        
        $tablePos = [0];

        if (preg_match("/^(.*?){$this->opt($this->t('checkIn'))}/m", $hotelText2, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.*?){$this->opt($this->t('Phone'))}/m", $hotelText2, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $table = $this->splitCols($hotelText2, $tablePos);

        if (count($table) > 0 && preg_match("/^\s*({$this->opt($this->t('Almatar ID'))})[:\s]+([-A-Z\d]{4,40})\s*$/", $table[0], $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
            $h->general()->noConfirmation();
        }

        $checkIn = $checkOut = null;

        if (count($table) > 1 && preg_match("/^\s*{$this->opt($this->t('checkIn'))}[:\s]+([\s\S]+?)[ ]*\n+[ ]*{$this->opt($this->t('checkOut'))}/", $table[1], $m)) {
            $checkIn = strtotime($this->normalizeDate(preg_replace('/\s+/', ' ', $m[1])));
        }

        if ($checkIn && $hotelRemarks && preg_match("/{$this->opt($this->t('Check-in hour'))}[:\s]+({$patterns['time']})/", $hotelRemarks, $m)) {
            $checkIn = strtotime($m[1], $checkIn);
        }
        $h->booked()->checkIn($checkIn);

        if (count($table) > 1 && preg_match("/(?:^\s*|\n[ ]*){$this->opt($this->t('checkOut'))}[:\s]+([\s\S]+?)\s*$/", $table[1], $m)) {
            $checkOut = strtotime($this->normalizeDate(preg_replace('/\s+/', ' ', $m[1])));
        }
        $h->booked()->checkOut($checkOut);

        if (count($table) > 2 && preg_match("/^\s*{$this->opt($this->t('Phone'))}[:\s]+({$patterns['phone']})(?:[ ]*\n+[ ]*{$this->opt($this->t('Fax'))}|\s*$)/", $table[2], $m)) {
            $h->hotel()->phone($m[1]);
        }

        if (count($table) > 2 && preg_match("/(?:^\s*|\n[ ]*){$this->opt($this->t('Fax'))}[:\s]+({$patterns['phone']})\s*$/", $table[2], $m)) {
            $h->hotel()->fax($m[1]);
        }

        $travellerName = $this->re("/^[ ]*{$this->opt($this->t('Name'))}[ ]*[:]+[ ]*({$patterns['travellerName']})(?:[ ]{2}|[ ]+{$this->opt($this->t('Phone'))}|[ ]+Email|$)/mu", $text);
        $h->general()->traveller($travellerName, true);

        if (preg_match($patternGuests = "/^.*{$this->opt($this->t('room'))}.+{$this->opt($this->t('night'))}.+-[ ]*(\d{1,3})[ ]*{$this->opt($this->t('guest'))}[[:alpha:]()]*(?:[ ]{2,}\S|$)/im", $text, $m)
            || preg_match($patternGuests, $textInvoice, $m)
        ) {
            $h->booked()->guests($m[1]);
        }

        $totalPrice = $this->re($patternPrice1 = "/^[ ]*{$this->opt($this->t('Total'))}[: ]{2,}([^: ].*)$/m", $text)
            ?? $this->re($patternPrice2 = "/^[ ]{2,}(\S.*)\n+[ ]*{$this->opt($this->t('Total'))}[: ]*$/m", $text)
            ?? $this->re($patternPrice1, $textInvoice)
            ?? $this->re($patternPrice2, $textInvoice)
        ;

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // SAR 1218
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        if (preg_match("/^[* ]*Your purchase is non-refundable ?[.!](?:[ ]{2}|$)/im", $text) // en
        ) {
            $h->booked()->nonRefundable();
        }

        if (preg_match("/^[ ]*(?<head>{$this->opt($this->t('Cancellation Fees'))})[: ]*\n{1,2}[ ]*(?<body>\S.{10,}?)[ ]*[.!]$/m", $text, $m)) {
            $h->general()->cancellation($m['head'] . ': ' . $m['body']);
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]almatar\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || !preg_match('/\bAlmatar\b/i', $headers['subject']))
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array)$phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false)
                    return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || strpos($textPdf, 'Almatar ID') === false) {
                continue;
            }

            if ($this->assignLangPdf($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $usingLangs = [];
        $pdfs = $parser->searchAttachmentByName('.*pdf');
        
        /* Step 1: find supported formats */

        $pdfsItinerary = [];

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ($this->assignLangPdf($textPdf)) {
                $pdfsItinerary[] = [
                    'lang' => $this->lang,
                    'text' => $textPdf,
                ];
            }
        }

        /* Step 2: find specific formats */

        $pdfsVoucher = $pdfsInvoice = [];

        foreach ($pdfsItinerary as $item) {
            if (preg_match("/^[ ]*{$this->opt($this->t('Hotel Remarks'))}(?:[ ]*:|[ ]{2}|$)/im", $item['text'])) {
                $pdfsVoucher[] = $item;
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('Total'))}(?:[ ]*:|[ ]{2}|$)/m", $item['text'])) {
                $pdfsInvoice[] = $item;
            }
        }

        /* Step 3: prioritization and targeting */

        $pdfsTarget = [];

        if (count($pdfsVoucher) > 0) {
            $pdfsTarget = $pdfsVoucher;
        } elseif (count($pdfsInvoice) > 0) {
            $pdfsTarget = $pdfsInvoice;
        } elseif (count($pdfsItinerary) > 0) {
            $pdfsTarget = $pdfsItinerary;
        }
        
        /* Step 4: parsing */

        foreach ($pdfsTarget as $item) {
            $usingLangs[] = $item['lang'];
            $textInvoice = count($pdfsVoucher) === 1 && count($pdfsInvoice) === 1 ? $pdfsInvoice[0]['text'] : '';
            $this->parseHotelPdf($email, $item['text'], $textInvoice);
        }

        if (count(array_unique($usingLangs)) === 1
            || count(array_unique(array_filter($usingLangs, function ($item) { return $item !== 'en'; }))) === 1
        ) {
            $email->setType('HotelReservationPdf' . ucfirst($usingLangs[0]));
        }

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

    private function assignLangPdf(?string $text): bool
    {
        if ( empty($text) || !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['hotelStart']) || empty($phrases['checkIn']) ) {
                continue;
            }
            if ($this->strposArray($text, $phrases['hotelStart']) !== false
                && $this->strposArray($text, $phrases['checkIn']) !== false
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`
     * @param string|null $text Unformatted string with date
     * @return string|null
     */
    private function normalizeDate(?string $text): ?string
    {
        if ( preg_match('/\b(\d{1,2})(?:[[:alpha:]]{2,3})?(?:\s+of)?[-,\s]+([[:alpha:]]+)[-,\s]+(\d{4})$/iu', $text, $m) ) {
            // Mon 29th of Apr 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }
        if ( isset($day, $month, $year) ) {
            if ( preg_match('/^\s*(\d{1,2})\s*$/', $month, $m) )
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            if ( ($monthNew = MonthTranslate::translate($month, $this->lang)) !== false )
                $month = $monthNew;
            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }
        return null;
    }
}
