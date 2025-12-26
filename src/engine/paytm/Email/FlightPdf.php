<?php

namespace AwardWallet\Engine\paytm\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Common\Shortcut\Price;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightPdf extends \TAccountChecker
{
    public $mailFiles = "paytm/it-30694772.eml, paytm/it-30509284.eml, paytm/it-29995264.eml, paytm/it-30765299.eml, paytm/it-31053995.eml";

    private $lang = '';

    private $subjects = [
        'en' => ['flight ticket'],
    ];

    private $pdfDetects = [
        'en' => ['Paytm Booking ID'],
    ];

    private static $dict = [
        'en' => [
            'travellersEnd' => ['Important', 'Baggage Allowance', 'Baggage Policy'],
            'Total Fare'    => ['Total Fare', 'Total Amount Paid:'],
            'Base Fare'     => ['Base Fare', 'Base Fare:'],
            'Total Tax'     => ['Total Tax', 'Total Tax:'],
            'Total Airfare' => ['Total Airfare', 'Air Fare:'],
        ],
    ];

    private $bookedDate = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);

                break; // TODO: make for parsing many PDF-attachments (example: it-30765299.eml)
            }
        }

        $cl = explode('\\', __CLASS__);
        $email->setType(end($cl) . ucfirst($this->lang));

        return $email;
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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (
                strpos($textPdf, ' paytm.com') === false
                && strpos($textPdf, 'Contact our Paytm flights customer care') === false
                && stripos($textPdf, 'Paytm Flight Support') === false
                && stripos($textPdf, 'please contact Paytm customer') === false
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@\.a-z]+paytm[a-z]*\.com/i', $from) > 0;
    }

    private function parsePdf(Email $email, string $text): void
    {
        $email->ota(); // because Paytm is not airline

        $header = preg_match("/({$this->opt($this->t('E-Ticket'))}.+?)(?:{$this->opt($this->t('Onward'))}|{$this->opt($this->t('Return'))})/s", $text, $m) ? $m[1] : '';

        if (preg_match("/(Paytm Booking ID)\s*:\s*(\d{5,})\b/", $header, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        $this->bookedDate = $this->re('/Booked on\s*:\s*(\d{1,2} \w+ \d{2,4})[ ]*\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?/', $header);

        // FLIGHTS
        $flights = $this->splitter('/^([ ]*(?:Onward|Return)\b.*)/m', $text);

        foreach ($flights as $flight) {
            $this->parseFlight($email->add()->flight(), $flight);
        }

        // PAYMENTS
        $farePaymentDetails = $this->cutText('Fare & Payment Details', 'Always carry ticket and your ID proof while travelling', $text);

        if (!$farePaymentDetails) {
            $farePaymentDetails = $this->cutText('Fare Breakup :', 'Always carry ticket and your ID proof while travelling', $text);
        }
        $this->parsePayments($email->price(), $farePaymentDetails);
    }

    private function parseFlight(Flight $f, string $text)
    {
        if ($this->bookedDate) {
            $f->setReservationDate(strtotime($this->bookedDate));
        }

        if (!preg_match("/^(?<segments>.+?)\n(?<travellers>[ ]*{$this->opt($this->t('Traveller'))}.*?){$this->opt($this->t('travellersEnd'))}/s", $text, $matches)) {
            $this->logger->alert('Wrong flight format!');

            return false;
        }

        $conf = $this->re('/PNR\s+.+\s+([A-Z\d]{5,9})\b/', $matches['segments']);

        if ($conf) {
            $f->general()->confirmation($conf);
        }

        // Detect segment type
        $airlinePos = preg_match("/^(.*?)(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])-\d+/m", $matches['segments'], $m) ? strlen($m[1]) : null;
        $airportPos = preg_match("/^(.*?)\b[A-Z]{3}[ ]+\d{1,2}:\d{2}/m", $matches['segments'], $m) ? strlen($m[1]) : null;

        if ($airlinePos === null || $airportPos === null) {
            $this->logger->alert('Wrong flight format!');

            return false;
        }

        if ($airportPos - $airlinePos > 3) {
            // it-30765299.eml, it-31053995.eml
            $segments = $this->splitter('/^(.{3,}\b[A-Z]{3}[ ]+\d{1,2}:\d{2}[ ]+[A-Z]{3}[ ]+\d{1,2}:\d{2}.*)/m', $matches['segments']);
            // conversion segment to general type
            $segments = array_map(function ($item) {
                $tablePos = [0];

                if (preg_match('/^(.{3,}?)\b[A-Z]{3}[ ]+\d{1,2}:\d{2}[ ]+/m', $item, $matches)) {
                    $tablePos[] = mb_strlen($matches[1]);
                }
                $table = $this->splitCols($item, $tablePos);

                if (count($table) !== 2) {
                    return $item;
                }

                return preg_replace('/\s+/', ' ', $table[0]) . "\n\n" . $table[1];
            }, $segments);
        } else {
            $segments = $this->splitter('/(.+\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])-\d+.+)/', $matches['segments']);
        }

        foreach ($segments as $segment) {
            $this->parseSegment($f->addSegment(), $segment);
        }

        $paxs = [];
        $namePrefixes = ['Mr.', 'Mr ', 'Ms.', 'Ms ', 'Mstr.', 'Mstr ', 'Mrs.', 'Mrs '];

        if (preg_match_all("/^(?:[ ]*#[ ]*\d+)?[ ]*({$this->opt($namePrefixes)}[ ]*[[:alpha:]][-.\'[:alpha:] ]*?[[:alpha:]])(?:[ ]{2}|$)/imu", $matches['travellers'], $m)) {
            $paxs = array_filter(array_unique($m[1]));
        }

        foreach ($paxs as $pax) {
            $f->addTraveller($pax);
        }
    }

    private function parsePayments(Price $price, string $farePaymentDetails): void
    {
        if (preg_match("/{$this->opt($this->t('Total Fare'))}\s+(\D+?)\s+(\d+)/ui", $farePaymentDetails, $m)) {
            $price
                ->currency($this->normalizeCurrency($m[1]))
                ->total($m[2])
            ;
        }

        $paytmFee = $this->re("/Paytm Convenience Fee(.+){$this->opt($this->t('Total Fare'))}/s", $farePaymentDetails);

        if (preg_match_all('/^[ ]{2,}([a-z\s\@\%\d]+)[ ]{2,}\D+?\s+(\d+)/im', $paytmFee, $m)) {
            foreach ($m[1] as $i => $nameTax) {
                $price->fee($nameTax, $m[2][$i]);
            }
        }

        $price->cost($this->re("/{$this->opt($this->t('Base Fare'))}\s+\D+?\s+(\d+)/", $farePaymentDetails));

        $tax = $this->re("/({$this->opt($this->t('Total Tax'))}.+?{$this->opt($this->t('Total Airfare'))})/s", $farePaymentDetails);

        if (preg_match_all('/^[ ]{2,}([A-Z][A-z\s]+)[ ]*:?[ ]{2,}\D+?\s+(\d+)/im', $tax, $m)) {
            foreach ($m[1] as $i => $nameTax) {
                $price->fee($nameTax, $m[2][$i]);
            }
        }
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'INR' => ['₹', 'Rs.'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|null
     */
    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})\s+([^\d\W]{3,})[,\s]+(\d{4})$/u', $string, $matches)) {
            // 16 Apr, 2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function parseSegment(FlightSegment $s, string $stext): void
    {
        if (preg_match('/.+\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])-(\d+)\b/', $stext, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
        }
        $dateDep = $dateArr = '';
        $patterns['date'] = '[^\d\W]{2,} (\d{1,2} [^\d\W]{3,}, \d{2,4})';

        if (preg_match("/{$patterns['date']}[ ]+(\d{1,2}h \d{1,2}m)?[ ]+{$patterns['date']}\b/iu", $stext, $m)) {
            $dateDep = $this->normalizeDate($m[1]);
            $s->extra()->duration($m[2], true);
            $dateArr = $this->normalizeDate($m[3]);
        }

        if (
            preg_match('/\b(?<depCode>[A-Z]{3})[ ]+(?<depTime>\d{1,2}:\d{2})[ ]+(?<arrTime>\d{1,2}:\d{2})[ ]+(?<arrCode>[A-Z]{3})\b/', $stext, $m)
            || preg_match('/\b(?<depCode>[A-Z]{3})[ ]+(?<depTime>\d{1,2}:\d{2})[ ]+(?<arrCode>[A-Z]{3})[ ]+(?<arrTime>\d{1,2}:\d{2})\b/', $stext, $m)
        ) {
            $s->departure()
                ->code($m['depCode'])
                ->date(strtotime($dateDep . ' ' . $m['depTime']));
            $s->arrival()
                ->code($m['arrCode'])
                ->date(strtotime($dateArr . ' ' . $m['arrTime']));
        }

        if (preg_match_all('/Terminal ([A-Z\d]{1,5})/', $stext, $m) && isset($m[1]) && 2 === count($m[1])) {
            $s->departure()->terminal($m[1][0]);
            $s->arrival()->terminal($m[1][1]);
        }
    }

    private function assignLang($text = ''): bool
    {
        foreach ($this->pdfDetects as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function cutText(string $start, $end, string $text): ?string
    {
        if (empty($start) || empty($end) || empty($text)) {
            return null;
        }

        if (is_array($end)) {
            $begin = stristr($text, $start);

            foreach ($end as $e) {
                if (stristr($begin, $e, true) !== false) {
                    return stristr($begin, $e, true) ?? null;
                }
            }
        }

        return stristr(stristr($text, $start), $end, true) ?? null;
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
}
