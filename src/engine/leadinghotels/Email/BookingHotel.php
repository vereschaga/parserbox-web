<?php

namespace AwardWallet\Engine\leadinghotels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingHotel extends \TAccountChecker
{
    public $mailFiles = "leadinghotels/it-17397812.eml";
    private $langDetectors = [
        'en' => ['Checkout date:'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'Total amount of the booking:' => ['Total amount of the booking:', 'Total amount of the booking :'],
            'addressEnd'                   => ['Tel:', 'mail:'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Leading Hotels of the World') !== false
            || preg_match('/[.@]lhw\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Booking confirmation Majestic Hotel & SPA') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for choosing Majestic Hotel & Spa") or contains(normalize-space(.),"We wish you a fantastic stay at Majestic Hotel & Spa") or contains(.,"@hotelmajestic.es")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.hotelmajestic.es")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('BookingHotel' . ucfirst($this->lang));
        $this->parseEmail($email);

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

    private function parseEmail(Email $email)
    {
        $patterns = [
            'confNumber' => '[A-Z\d]{5,}', // 11986371476    |    M5GPQK
            'fieldValue' => '/^[^:]+:\s*(.+)$/',
            'phone'      => '[+)(\d][-\s\d)(]{5,}[\d)(]', // +377 (93) 15 48 52
            'time'       => '\d{1,2}[.:]\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.
        ];

        $email->ota(); // because "Leading Hotels of the World" is not "Majestic Hotel Group"

        $h = $email->add()->hotel();

        // chainName
        if ($this->http->XPath->query('//text()[' . $this->eq($this->t('Reservation department')) . ']/following::text()[contains(normalize-space(.),"@majestichotelgroup.com")]')->length > 0) {
            $h->hotel()->chain('Majestic Hotel Group');
        }

        // hotelName
        $h->hotel()->name($this->http->FindSingleNode('//text()[contains(normalize-space(.),"Thank you for choosing") and contains(normalize-space(.),"for your next stay")]', null, true, '/Thank you for choosing\s*(.+?)\s*for your next stay/is'));

        // confirmation number
        $confirmationCodeText = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Your booking:')) . ']/following::text()[' . $this->starts($this->t('Your booking number:')) . '][1]');

        if (preg_match('/^([^:]+?)\s*:\s*(' . $patterns['confNumber'] . ')$/', $confirmationCodeText, $matches)) {
            $h->general()->confirmation($matches[2], $matches[1]);
        }

        // status
        $h->general()->status($this->http->FindSingleNode('//text()[' . $this->starts($this->t('Booking status:')) . ']', null, true, $patterns['fieldValue']));

        // travellers
        $guestFirstName = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('First name:')) . ']', null, true, $patterns['fieldValue']);
        $guestLastName = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Last name:')) . ']', null, true, $patterns['fieldValue']);
        $guestFullName = $guestFirstName && $guestLastName ? $guestFirstName . ' ' . $guestLastName : '';
        $h->general()->traveller($guestFullName, true);

        $r = $h->addRoom();

        // r.type
        $r->setType($this->http->FindSingleNode('//text()[' . $this->starts($this->t('Room booked:')) . ']', null, true, $patterns['fieldValue']));

        // checkInDate
        $dateCheckIn = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Checkin date:')) . ']', null, true, $patterns['fieldValue']);

        if ($dateCheckIn) {
            if ($dateCheckInNormal = $this->normalizeDate($dateCheckIn)) {
                $h->booked()->checkIn2($dateCheckInNormal);
            }
        }

        // checkOutDate
        $dateCheckOut = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Checkout date:')) . ']', null, true, $patterns['fieldValue']);

        if ($dateCheckOut) {
            if ($dateCheckOutNormal = $this->normalizeDate($dateCheckOut)) {
                $h->booked()->checkOut2($dateCheckOutNormal);
            }
        }

        // guestCount
        $h->booked()->guests($this->http->FindSingleNode('//text()[' . $this->starts($this->t('Adults:')) . ']', null, true, '/^[^:]+:\s*(\d{1,3})$/'));

        // kidsCount
        $h->booked()->kids($this->http->FindSingleNode('//text()[' . $this->starts($this->t('Kids')) . ']', null, true, '/^[^:]+:\s*(\d{1,3})$/'), false, true);

        // r.rate
        $rateBookedTexts = $this->http->FindNodes('//text()[ ./preceding::text()[' . $this->starts($this->t('Rate booked:')) . '] and ./following::text()[' . $this->eq($this->t('Total amount of the booking:')) . '] ]');
        $rateBookedText = implode(' ', $rateBookedTexts);
        // Day: 04/08/18    Price: 299.00€
        if (preg_match_all('/\b\d{2,4}\s+Price:\s*(?<amount>\d[,.\'\d]*)\s*(?<currency>[^,.\'\d)(]+?)\s*(?:Day:|\b|$)/', $rateBookedText, $rateMatches)) {
            if (count(array_unique($rateMatches['currency'])) === 1) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return (float) $this->normalizeAmount($item);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    $r->setRate($rateMatches['amount'][0] . ' ' . $rateMatches['currency'][0] . ' / day');
                } else {
                    $r->setRate($rateMin . '-' . $rateMax . ' ' . $rateMatches['currency'][0] . ' / day');
                }
            }
        }

        // p.total
        // p.currencyCode
        $totalPrice = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Total amount of the booking:')) . ']/following::text()[normalize-space(.)][1]');
        // 598.00€
        if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[^\d)(]+)/', $totalPrice, $matches)) {
            $h->price()->total($this->normalizeAmount($matches['amount']));
            $h->price()->currency($this->normalizeCurrency($matches['currency']));
        }

        // cancellation
        $cancellationTexts = $this->http->FindNodes('//text()[' . $this->eq($this->t('Guarantee and cancelation policy:')) . ']/ancestor::td[1]/descendant::text()[normalize-space(.)]');
        $cancellationText = implode(' ', $cancellationTexts);

        if (preg_match('/' . $this->opt($this->t('Guarantee and cancelation policy:')) . '\s*(.+)/s', $cancellationText, $matches)) {
            if (mb_strlen($matches[1]) > 1000) {
                for ($i = 0; $i < 20; $i++) {
                    $matches[1] = preg_replace('/^(.+\w\s*\.).+?\.$/s', '$1', $matches[1]);

                    if (mb_strlen($matches[1]) < 1001) {
                        break;
                    }
                }
            }
            $h->booked()->cancellation($matches[1]);
        }

        $xpathFragment1 = $this->contains($this->t('addressEnd'));
        $xpathFragment2 = '//text()[' . $this->eq($this->t('Hotel info:')) . "]/ancestor::table[ ./descendant::text()[{$xpathFragment1}] ][1]";

        // address
        // phone
        $addressTexts = $this->http->FindNodes($xpathFragment2 . "/descendant::span[{$xpathFragment1}][1]/descendant::text()[normalize-space(.)]");
        $addressText = implode(', ', $addressTexts);

        if (preg_match('/^(?<address>.+?)\s*Tel:\s*(?<phone>' . $patterns['phone'] . ')/i', $addressText, $matches)) {
            $h->hotel()->address(trim($matches[1], ', '));
            $h->hotel()->phone($matches[2]);
        } elseif ($addressText) {
            $h->hotel()->address($addressText);
        }

        // checkInDate
        $checkInTime = $this->http->FindSingleNode($xpathFragment2 . '/descendant::text()[' . $this->starts($this->t('Check in time from')) . ']', null, true, '/' . $this->opt($this->t('Check in time from')) . '\s*(' . $patterns['time'] . ')/');

        if (!empty($h->getCheckInDate()) && $checkInTime) {
            $h->booked()->checkIn(strtotime($checkInTime, $h->getCheckInDate()));
        }

        // checkOutDate
        $checkOutTime = $this->http->FindSingleNode($xpathFragment2 . '/descendant::text()[' . $this->starts($this->t('Check out time until')) . ']', null, true, '/' . $this->opt($this->t('Check out time until')) . '\s*(' . $patterns['time'] . ')/');

        if (!empty($h->getCheckOutDate()) && $checkOutTime) {
            $h->booked()->checkOut(strtotime($checkOutTime, $h->getCheckOutDate()));
        }
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})\b/', $string, $matches)) { // 16/08/18
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
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
    private function normalizeAmount(string $string): string
    {
        $string = preg_replace('/\s+/', '', $string);             // 11 507.00  ->  11507.00
        $string = preg_replace('/[,.\'](\d{3})/', '$1', $string); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);    // 18800,00   ->  18800.00

        return $string;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
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

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
