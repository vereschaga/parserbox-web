<?php

namespace AwardWallet\Engine\mirage\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "mirage/it-2663060.eml, mirage/it-6937393.eml, mirage/it-26288209.eml";

    private $subjects = [
        'en' => ['Conf#', 'Conf #', 'Confirmation#', 'Confirmation #'],
    ];

    private $langDetectorsPdf = [
        'en' => ['Departure:', 'Departure :', 'Departure Date:', 'Departure Date :'],
    ];

    private $pdfPattern = '\d+_.*?_confirmation\d+.pdf';

    private static $dictionary = [
        "en" => [
            'Thank you for choosing' => ['Thank you for choosing', 'Thank you very much for choosing'],
            'Name:'                  => ['Name:', 'Name :'],
            'Confirmation Number:'   => ['Confirmation Number:', 'Confirmation Number :', 'Confirmation No.:', 'Confirmation No. :', 'Cancellation Number :'],
            'Arrival:'               => ['Arrival:', 'Arrival :', 'Arrival Date:', 'Arrival Date :'],
            'Departure:'             => ['Departure:', 'Departure :', 'Departure Date:', 'Departure Date :'],
            'Check in time is'       => ['Check in time is', 'Check-in is from'],
            'check out time is'      => ['check out time is', 'check-out is at'],
            'Number of Guests:'      => ['Number of Guests:', 'Number of Guests :', 'No. of Guests:', 'No. of Guests :'],
            'Room Type:'             => ['Room Type:', 'Room Type :', 'Accommodation:', 'Accommodation :'],
            'Rate:'                  => ['Rate:', 'Rate :', 'Room Rates:', 'Room Rates :', 'Rate Details:', 'Rate Details :'],
            'Deposit Amount:'        => ['Deposit Amount:', 'Deposit Amount :'],
            'Cancellation Policy:'   => ['Cancellation Policy:', 'Cancellation Policy :'],
        ],
    ];

    private $lang = '';
    private $providerCode = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@mgmresorts.com') !== false
            || stripos($from, '@lv.mgmgrand.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!empty($headers['from']) || !empty($headers['subject'])) {
            return false;
        }

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
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (empty($pdfs)) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

        if (empty($pdfs)) {
            return false;
        }

        $pdf = $pdfs[0];
        $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

        if ($text === null) {
            return false;
        }

        // Detecting Provider
        if ($this->assignProvider($text, $parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLangPdf($text);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (empty($pdfs)) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

        if (empty($pdfs)) {
            return $email;
        }

        $pdf = $pdfs[0];
        $this->text = \PDF::convertToText($parser->getAttachmentBody($pdf));

        if ($this->text === null) {
            return $email;
        }

        // Detecting Provider
        if (!$this->assignProvider($this->text, $parser->getHeaders())) {
            $this->logger->debug("Can't determine a provider!");

            return $email;
        }

        // Detecting Language
        if (!$this->assignLangPdf($this->text)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parsePdf($email);
        $email->setType('ConfirmationPdf' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

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

    private function parsePdf(Email $email)
    {
        $patterns = [
            'time'  => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
            'phone' => '[+)(\d][-.\s\d)(]{5,}[\d)(]', // +377 (93) 15 48 52    |    713.680.2992
        ];

        $text = $this->text;

        $h = $email->add()->hotel();

        // confirmation number
        if (preg_match('/(' . $this->opt($this->t('Confirmation Number:')) . ')[ ]*(.+)/', $text, $matches)) {
            $h->general()->confirmation($matches[2], preg_replace('/\s*:\s*$/', '', $matches[1]));
        }

        if (preg_match("/have been cancelled per your request/", $text, $m)) {
            $h->general()
                ->status('cancelled')
                ->cancelled()
            ;
        }

        // hotelName
        $hotelName = trim($this->re("#We look forward to welcoming you as a guest of MGM Grand.\s+(.+)#ms", $text));

        if (empty($hotelName)) {
            $hotelName = $this->re('/' . $this->opt($this->t('Thank you for choosing')) . '\s+(.+?)\s*(?:as your|\.)/i', $text);
        }

        if (empty($hotelName)) {
            $hotelName = $this->re('/Your room reservations at the (.+?) have been /', $text);
        }
        $h->hotel()->name($hotelName);

        // checkInDate
        $dateCheckIn = $this->re('/' . $this->opt($this->t('Arrival:')) . '[ ]*(.+)/', $text);

        if ($dateCheckIn && $dateCheckInNormal = $this->normalizeDate($dateCheckIn)) {
            $h->booked()->checkIn2($dateCheckInNormal);
        }
        $timeCheckIn = $this->re('/' . $this->opt($this->t('Check in time is')) . '[ ]+(' . $patterns['time'] . ')/', $text);

        if ($timeCheckIn && !empty($h->getCheckInDate())) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $h->getCheckInDate()));
        }

        // checkOutDate
        $dateCheckOut = $this->re('/' . $this->opt($this->t('Departure:')) . '[ ]*(.+)/', $text);

        if ($dateCheckOut && $dateCheckOutNormal = $this->normalizeDate($dateCheckOut)) {
            $h->booked()->checkOut2($dateCheckOutNormal);
        }
        $timeCheckOut = $this->re('/' . $this->opt($this->t('check out time is')) . '[ ]+(' . $patterns['time'] . ')/', $text);

        if ($timeCheckOut && !empty($h->getCheckOutDate())) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $h->getCheckOutDate()));
        }

        // address
        if (preg_match('/^[ ]*(.{3,})$\s+^.*\b(?:Tel|Fax)\b.+$\s+^[ ]*www\./m', $text, $matches)) {
            $h->hotel()->address($matches[1]);
        } elseif (!empty($h->getHotelName())) {
            $h->hotel()->noAddress();
        }

        // phone
        // fax
        if (preg_match('/^[ ]*Tel[ ]+(?<phone>' . $patterns['phone'] . ')[ ]+Fax[ ]+(?<fax>' . $patterns['phone'] . ')$\s+^[ ]*www\./m', $text, $matches)) {
            $h->hotel()
                ->phone($matches['phone'])
                ->fax($matches['fax']);
        } elseif (preg_match('/^[ ]*Tel[ ]+(?<phone>' . $patterns['phone'] . ')$\s+^[ ]*www\./m', $text, $matches)) {
            $h->hotel()->phone($matches['phone']);
        } elseif (preg_match('/^[ ]*Fax[ ]+(?<fax>' . $patterns['phone'] . ')$\s+^[ ]*www\./m', $text, $matches)) {
            $h->hotel()->fax($matches['fax']);
        }

        // travellers
        $h->general()->traveller($this->re('/' . $this->opt($this->t('Name:')) . '[ ]*(.+)/', $text));

        // guestCount
        $guestsText = $this->re('/' . $this->opt($this->t('Number of Guests:')) . '[ ]*(.+)/', $text);

        if (preg_match('/^(\d{1,3})$/', $guestsText, $matches)) {
            $h->booked()->guests($matches[1]);
        } elseif (preg_match('/\b(\d{1,3})[ ]+Adult/i', $guestsText, $matches)) {
            // 2 Adult(s) / 0 Children
            $h->booked()->guests($matches[1]);
        }

        // kidsCount
        if (preg_match('/\b(\d{1,3})[ ]+Child/i', $guestsText, $matches)) {
            $h->booked()->kids($matches[1]);
        }

        $r = $h->addRoom();

        // r.rate
        $rateRowsText = $this->re('/' . $this->opt($this->t('Rate:')) . '[ ]*(.+?)(?:\s+' . $this->opt($this->t('Deposit Amount:')) . '|\n\n)/s', $text);
        $rateRows = array_filter(preg_split('/\s*\n\s*/', $rateRowsText));

        if (count($rateRows) === 1) {
            $r->setRate($rateRows[0]);
        } elseif (count($rateRows) > 1) {
            $rateText = '';

            foreach ($rateRows as $rateRow) {
                if (
                    preg_match('/^(?<date>\d{1,2}\/\d{1,2}\/\d{2,4})[ ]+(?<currency>\D+)(?<amount>\d[,.\'\d ]*?)(?:[ ]{2}|$)/', $rateRow, $matches) // 07/07/2017    $168.00    plus applicable tax per night*
                    || preg_match('/^(?<date>[^\d\W]{3,}[ ]+\d{1,2}[^\d\W]{0,2})[ ]+(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[A-Z]{3})\b/u', $rateRow, $matches) // September 29th    61,000 JPY
                ) {
                    $rateText .= "\n" . $matches['currency'] . ' ' . $matches['amount'] . ' from ' . $matches['date'];
                }
            }
            $rateRange = $this->parseRateRange($rateText);

            if ($rateRange !== null) {
                $r->setRate($rateRange);
            }
        }

        // r.type
        $r->setType($this->re('/' . $this->opt($this->t('Room Type:')) . '[ ]*(.+)/', $text));

        // cancellation
        if (preg_match('/' . $this->opt($this->t('Cancellation Policy:')) . '[ ]*(.+?)\n\n/s', $text, $matches)) {
            $h->general()->cancellation(preg_replace('/\s+/', ' ', $matches[1]));
        }

        // deadline
        if (preg_match('/may be made without penalty by (' . $patterns['time'] . ')(?: JST)? the (day) before arrival/', $h->getCancellation(), $matches)) {
            $daysBefore = preg_replace('/^day$/i', '1 day', $matches[2]);
            $h->booked()->deadlineRelative($daysBefore, $matches[1]);
        }
    }

    private function assignProvider($text = '', $headers): bool
    {
        $condition1 = strpos($headers['from'], 'Palace Hotel Tokyo') !== false || stripos($headers['from'], '@palacehotel.jp') !== false;
        $condition2 = strpos($text, 'Thank you very much for choosing Palace Hotel Tokyo') !== false || stripos($text, '.palacehoteltokyo.com') !== false || stripos($text, '@palacehotel.jp') !== false;

        if ($condition1 || $condition2) {
            $this->providerCode = 'leadinghotels';

            return true;
        }

        $condition1 = self::detectEmailFromProvider($headers['from']) === true;
        $condition2 = strpos($text, 'Thank you for choosing MGM Grand') !== false || stripos($text, 'www.mgmresorts.com') !== false || strpos($text, 'you as a guest of MGM Grand') !== false;
        $condition3 = strpos($text, 'Thank you for choosing Bellagio') !== false || strpos($text, 'Bellagio is pleased to offer you') !== false;

        if ($condition1 || $condition2 || $condition3) {
            $this->providerCode = 'mirage';

            return true;
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return ['leadinghotels', 'mirage'];
    }

    private function assignLangPdf($text = '')
    {
        foreach ($this->langDetectorsPdf as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/', $string, $matches)) { // 05/04/15
            $month = $matches[1];
            $day = $matches[2];
            $year = '20' . $matches[3];
        } elseif (preg_match('/^([^\d\W]{3,})\s+(\d{1,2})[,\s]+(\d{4})/u', $string, $matches)) { // September 29, 2018 Saturday
            $month = $matches[1];
            $day = $matches[2];
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

        return false;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function TableHeadPos($row)
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function parseRateRange($string = '')
    {
        if (
            preg_match_all('/(?:^\s*|\b\s+)(?<currency>[^\d\s]\D{0,2}?)[ ]*(?<amount>\d[,.\'\d ]*)[ ]+from[ ]+\b/', $string, $rateMatches) // $239.20 from August 15
        ) {
            if (count(array_unique($rateMatches['currency'])) === 1) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return (float) $this->normalizeAmount($item);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    return number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                }
            }
        }

        return null;
    }
}
