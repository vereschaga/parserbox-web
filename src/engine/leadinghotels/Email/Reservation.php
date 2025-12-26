<?php

namespace AwardWallet\Engine\leadinghotels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "leadinghotels/it-17897106.eml";

    private $langDetectors = [
        'en' => ['Date of departure:', 'Date of departure :'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'Date:'               => ['Date:', 'Date :'],
            'Reservation number:' => ['Reservation number:', 'Reservation number :'],
            'Date of arrival:'    => ['Date of arrival:', 'Date of arrival :'],
            'Date of departure:'  => ['Date of departure:', 'Date of departure :'],
            'roomTypeKeywords'    => ['double', 'room', 'king'],
        ],
    ];

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Reservation confirmation') !== false
            && stripos($headers['subject'], 'Royal Riviera') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"We thank you for your request and for choosing the Royal-Riviera") or contains(normalize-space(.),"The Royal-Riviera is pleased to introduce you") or contains(.,"www.royal-riviera.com") or contains(.,"@royal-riviera.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//img[contains(@src,"/20x0_LHWSimplifiedGrisfondtransparent--136.")]')->length === 0;

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

        $email->setType('Reservation' . ucfirst($this->lang));
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
            'confNumber'    => '[A-Z\d]{5,}', // 11986371476    |    M5GPQK
            'travellerName' => '[A-z][-.\'A-z ]*[A-z]', // Mr. Hao-Li Huang
            'phone'         => '[+)(\d][-\s\d)(]{5,}[\d)(]', // +377 (93) 15 48 52
        ];

        $h = $email->add()->hotel();

        $xpathFragmentReservationNumber = '//text()[' . $this->starts($this->t('Reservation number:')) . ']';

        // reservationDate
        $reservationDate = $this->http->FindSingleNode($xpathFragmentReservationNumber . '/preceding::text()[normalize-space(.)][1]', null, true, '/' . $this->opt($this->t('Date:')) . '\s*(.{6,})/');

        if ($reservationDate && $reservationDateNormal = $this->normalizeDate($reservationDate)) {
            $h->general()->date2($reservationDateNormal);
        }

        // confirmation number
        $confirmationCodeTitle = $this->http->FindSingleNode($xpathFragmentReservationNumber, null, true, '/(' . $this->opt($this->t('Reservation number:')) . ')/');
        $confirmationCode = $this->http->FindSingleNode($xpathFragmentReservationNumber, null, true, '/' . $this->opt($this->t('Reservation number:')) . '\s*(' . $patterns['confNumber'] . ')/');
        $h->general()->confirmation($confirmationCode, preg_replace('/\s*:\s*/', '', $confirmationCodeTitle));

        // travellers
        $h->general()->traveller($this->http->FindSingleNode($xpathFragmentReservationNumber . '/following::text()[' . $this->starts($this->t('Dear')) . '][1]', null, true, '/' . $this->opt($this->t('Dear')) . '\s+(' . $patterns['travellerName'] . ')(?:,|$)/m'));

        // hotelName
        $h->hotel()->name($this->http->FindSingleNode('//text()[' . $this->contains($this->t('We thank you for your request and for choosing the')) . ']', null, true, '/' . $this->opt($this->t('We thank you for your request and for choosing the')) . '\s+(.+?)(?:[,.!]|$)/mu'));

        // status
        $status = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('We are pleased to')) . ']', null, true, '/' . $this->opt($this->t('We are pleased to')) . '\s+(\w+)/u');

        if ($status) {
            $h->general()->status($status);
        }

        // checkInDate
        // checkOutDate
        $datesTexts = $this->http->FindNodes('//td[not(.//td) and ' . $this->contains($this->t('Date of arrival:')) . ' and ' . $this->contains($this->t('Date of departure:')) . ']');
        $datesText = implode(' ', $datesTexts);
        $patterns['dates'] = '/'
            . $this->opt($this->t('Date of arrival:')) . '\s*(.{6,})?' // Date of arrival:    Thursday, July 26th 2018
            . $this->opt($this->t('Date of departure:')) . '\s*(.{6,})?' // Date of departure:    Sunday, July 29th 2018
            . '/s';

        if (preg_match($patterns['dates'], $datesText, $matches)) {
            $dateCheckIn = preg_replace('/\b2(\d{2})\b/', '20$1', $matches[1]);

            if ($dateCheckInNormal = $this->normalizeDate($dateCheckIn)) {
                $h->booked()->checkIn2($dateCheckInNormal);
            }

            $dateCheckOut = preg_replace('/\b2(\d{2})\b/', '20$1', $matches[2]);

            if ($dateCheckOutNormal = $this->normalizeDate($dateCheckOut)) {
                $h->booked()->checkOut2($dateCheckOutNormal);
            }
        }

        $r = $h->addRoom();

        // r.type
        $r->setType($this->http->FindSingleNode('//text()[ ./preceding::text()[' . $this->eq($this->t('ACCOMMODATION')) . '] and ./following::text()[' . $this->contains($this->t('ROOM RATE:')) . '] ][1]', null, true, '/.*' . $this->opt($this->t('roomTypeKeywords')) . '.*/'));

        // r.rate
        $r->setRate($this->http->FindSingleNode('//text()[' . $this->eq($this->t('ACCOMMODATION')) . ']/following::text()[' . $this->contains($this->t('ROOM RATE:')) . ']', null, true, '/' . $this->opt($this->t('ROOM RATE:')) . '\s*(.+)/'));

        // address
        // phone
        if (!empty($h->getHotelName())) {
            $xpathFragmentHotel = $this->contains([
                $h->getHotelName(),
                strtoupper($h->getHotelName()),
            ]);
            $addressTexts = $this->http->FindNodes('//text()[' . $xpathFragmentHotel . '][ ./ancestor::*[self::b or self::strong] ]/ancestor::td[1]/descendant::text()[normalize-space(.)]');
            $addressText = implode("\n", $addressTexts);

            $patterns['hotel'] = $this->opt([
                $h->getHotelName(),
                strtoupper($h->getHotelName()),
            ]);
            $patterns['addressPhone'] = '/'
                . $patterns['hotel'] . '[^\n]*$'
                . '\s+^(?<address>.+?)$'
                . '\s+^\s*(?<phone>' . $patterns['phone'] . ')'
                . '/ms';

            if (preg_match($patterns['addressPhone'], $addressText, $matches)) {
                $h->hotel()->address($matches['address']);
                $h->hotel()->phone($matches['phone']);
            }
        }
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('/([^\d\W]{3,})\s+(\d{1,2})(?:\s*[Tt][Hh])?\s+(\d{4})$/u', $string, $matches)) { // Thursday, July 26th 2018
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
