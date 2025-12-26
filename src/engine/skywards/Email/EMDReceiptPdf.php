<?php

namespace AwardWallet\Engine\skywards\Email;

use AwardWallet\Schema\Parser\Email\Email;

class EMDReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "skywards/it-35109891.eml";
    private $subjects = [
        'en' => ['Your seat purchase is confirmed'],
    ];
    private $langDetectorsPdf = [
        'en' => ['PASSENGER AND EMD INFORMATION', 'EMD COUPON DETAILS'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'fareTable2' => ['FORM OF PAYMENT', 'ISSUED IN EXCHANGE FOR EMD', 'ORIGINAL ISSUE EMD'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@emirates.com') !== false
            || stripos($from, '@emirates.email') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
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

            if (stripos($textPdf, 'You booked an itinerary via emirates.com') === false && stripos($textPdf, 'Emirates. All rights reserved') === false) {
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

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);
            }
        }

        if (!$this->lang) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }

        $email->setType('EMDReceiptPdf' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parsePdf(Email $email, $text)
    {
        $f = $email->add()->flight();

        $passengerInfo = preg_match("/{$this->opt($this->t('PASSENGER AND EMD INFORMATION'))}\s+(.+?)\s+{$this->opt($this->t('EMD COUPON DETAILS'))}/s", $text, $m) ? $m[1] : '';

//        $this->logger->debug('$text = ' . print_r($text, true));

        // travellers
        $passengerName = preg_match("/^[ ]*{$this->opt($this->t('PASSENGER NAME'))}[ ]+([[:alpha:]][-.\'\/[:alpha:] ]*[[:alpha:]])$/m", $passengerInfo, $m) ? $m[1] : '';

        if (empty($passengerName)) {
            $passengerName = preg_replace('/\s+/', ' ', trim(preg_match("/^[ ]*{$this->opt($this->t('PASSENGER NAME'))}[ ]+([[:alpha:]][-.[:alpha:] ]+[[:alpha:]]\/\n {30,}[[:alpha:]][-.\'\/[:alpha:] ]*[[:alpha:]])$/m", $passengerInfo, $m) ? $m[1] : ''));
        }
        $f->general()->traveller($passengerName);

        // accountNumbers
        $ffNumber = preg_match("/^[ ]*{$this->opt($this->t('FREQUENT FLYER'))}[ ]+([A-Z\d][-A-Z\d]{5,}[A-Z\d])$/m", $passengerInfo, $m) ? $m[1] : '';

        if ($ffNumber) {
            $f->program()->account($ffNumber, false);
        }

        // confirmation number
        if (preg_match("/^[ ]*({$this->opt($this->t('BOOKING REFERENCE'))})[ ]+([A-Z\d]{5,})$/m", $passengerInfo, $m)) {
            $f->general()->confirmation($m[2], preg_replace('/\s*:\s*$/', '', $m[1]));
        }

        $couponDetails = preg_match("/{$this->opt($this->t('EMD COUPON DETAILS'))}\n+(.+?)\s+{$this->opt($this->t('FARE AND ADDITIONAL INFORMATION'))}/s", $text, $m) ? $m[1] : '';

        $tablePos = [0];

        if (!preg_match('/^(((.+)' . implode('[ ]+)', ['CARRIER', 'FROM', 'TO']) . '/m', $couponDetails, $matches)) {
            $this->logger->alert('Segment table headers not found! (1)');

            return false;
        }
        unset($matches[0]);
        asort($matches);

        foreach ($matches as $textHeaders) {
            $tablePos[] = mb_strlen($textHeaders);
        }

        if (preg_match("/^(.+?){$this->opt($this->t('FLIGHT NO'))}/m", $couponDetails, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+?){$this->opt($this->t('REASON FOR'))}/m", $couponDetails, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (count($tablePos) !== 6) {
            $this->logger->alert('Segment table headers not found! (2)');

            return false;
        }

        // it-31614850.eml
        $segments = $this->splitText($couponDetails, '/^([ ]{17,}(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*\d+(?:[ ]{2}|$))/m', true);

        if (count($segments) === 0) {
            // it-30815912.eml
            $segments = $this->splitText($couponDetails, '/^([ ]*\d{1,3}\b.*)/m', true);
        }

        foreach ($segments as $key => $segment) {
            $s = $f->addSegment();

            $table = $this->splitCols($segment, $tablePos);

            if (count($table) !== 6) {
                $this->logger->alert("Segment-$key is wrong!");

                return false;
            }

            // airlineName
            if (preg_match('/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*$/', $table[1], $m)) {
                $s->airline()->name($m[1]);
            }

            // depCode
            if (preg_match('/^\s*([A-Z]{3})\s*$/', $table[2], $m)) {
                $s->departure()->code($m[1]);
            }

            // arrCode
            if (preg_match('/^\s*([A-Z]{3})\s*$/', $table[3], $m)) {
                $s->arrival()->code($m[1]);
            }

            // flightNumber
            // depDate
            // arrDate
            if (preg_match('/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])?(\d+)[\s\/]+(.{6,}?)\s*$/', $table[4], $m)) {
                // EK0571 10AUG18    |    0206 / 03APR19
                if (!empty($m[1])) {
                    $s->airline()->name($m[1]);
                }

                $s->airline()->number($m[2]);

                $s->departure()
                    ->day2($m[3])
                    ->noDate()
                ;
                $s->arrival()->noDate();
            }
        }
        /*
         * // price only for seats
                $fareInformation = preg_match("/{$this->opt($this->t('FARE AND ADDITIONAL INFORMATION'))}\n+(.+?)\s+{$this->opt($this->t('TERMS AND CONDITIONS'))}/s", $text, $m) ? $m[1] : '';

                // p.currencyCode
                // p.cost
                // p.tax
                // p.total
                $total = preg_match("/^[ ]*{$this->opt($this->t('TOTAL'))}[ ]{2,}(.+?)(?:[ ]{2}|$)/m", $fareInformation, $m) ? $m[1] : '';
                if ( preg_match('/^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d ]*)[\D\S]*$/', $total, $matches) ) {
                    // USD50    |    INR220-K3
                    $f->price()
                        ->currency($matches['currency'])
                        ->total( $this->normalizeAmount($matches['amount']) )
                    ;

                    $fare = preg_match("/^[ ]*{$this->opt($this->t('FARE'))}[ ]{2,}(.+?)(?:[ ]{2}|$)/m", $fareInformation, $m) ? $m[1] : '';
                    if ( preg_match('/^\s*' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d ]*)[\D\S]*$/', $fare, $m) )
                        $f->price()->cost( $this->normalizeAmount($m['amount']) );

                    $taxes = preg_match("/^[ ]*{$this->opt($this->t('TAXES/FEES/CHARGES'))}[ ]{2,}(.+?)(?:[ ]{2}|$)/m", $fareInformation, $m) ? $m[1] : '';
                    if ( preg_match('/^\s*' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d ]*)[\D\S]*$/', $taxes, $m) )
                        $f->price()->tax( $this->normalizeAmount($m['amount']) );
                }
        */
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function rowColsPos($row): array
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

    private function splitCols($text, $pos = false): array
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

    private function splitText($textSource = '', string $pattern, $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang($text = ''): bool
    {
        foreach ($this->langDetectorsPdf as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (empty($text) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                } elseif (!empty($text) && strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
