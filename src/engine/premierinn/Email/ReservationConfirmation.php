<?php

namespace AwardWallet\Engine\premierinn\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "premierinn/it-28531620.eml";
    private $subjects = [
        'en' => ['Reservation Confirmation'],
    ];
    private $langDetectors = [
        'en' => ['Number of rooms:'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'Your reservation number is' => ['Your reservation number is', 'your reservation number is'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]premierinn\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
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
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"your reservation at Premier Inn") or contains(.,"@mena.premierinn.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//global.premierinn.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('ReservationConfirmation' . ucfirst($this->lang));

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
        $h = $email->add()->hotel();

        // hotelName
        // address
        // travellers
        $intro = $this->http->FindSingleNode("//text()[{$this->contains($this->t('We are pleased to confirm your reservation at'))}]/ancestor::*[1]");

        if (preg_match("/{$this->opt($this->t('We are pleased to confirm your reservation at'))}\s*(.+?)\s*{$this->opt($this->t('for'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\./", $intro, $m)) {
            $h->hotel()
                ->name($m[1])
                ->noAddress();
            $h->general()->traveller($m[2]);
        } else {
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Thank you for choosing to stay with us at'))}]/following::text()[normalize-space()][1][following::text()[normalize-space()][1][{$this->starts('.')}]]");

            if (strlen($name) < 50 && stripos($name, 'Premier Inn') !== false) {
                $h->hotel()
                    ->name($name)
                    ->noAddress();
                $h->general()
                    ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Dear'))}]/following::text()[normalize-space()][1][following::text()[normalize-space()][1][{$this->eq(',')}]]",
                        null, true, "/^\s*([A-Z][A-Za-z \-]+?)\s*$/"));
            }
        }

        // confirmation number
        $confNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your reservation number is'))}]/ancestor::*[1]", null, true, "/{$this->opt($this->t('Your reservation number is'))}\s*([A-Z\d]{7,})[.!]*$/");
        $h->general()->confirmation($confNumber);

        $patterns['time'] = '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon|\s*午[前後])?'; // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon    |    3:10 午後

        // checkInDate
        $dateCheckIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival date:'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]");
        $h->booked()->checkIn2($dateCheckIn);
        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check in time is from'))}]", null, true, "/{$this->opt($this->t('Check in time is from'))}\s*({$patterns['time']})/");

        if ($timeCheckIn && !empty($h->getCheckInDate())) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $h->getCheckInDate()));
        }

        // checkOutDate
        $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure date:'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]");
        $h->booked()->checkOut2($dateCheckOut);
        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check out time is by'))}]", null, true, "/{$this->opt($this->t('Check out time is by'))}\s*({$patterns['time']})/");

        if ($timeCheckOut && !empty($h->getCheckOutDate())) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $h->getCheckOutDate()));
        }

        // roomsCount
        $roomsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of rooms:'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]", null, true, '/^(\d{1,3})$/');
        $h->booked()->rooms($roomsCount);

        // guestCount
        $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of persons:'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]", null, true, '/^(\d{1,3})$/');
        $h->booked()->guests($guests);

        // kidsCount
        $kids = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of children:'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]", null, true, '/^(\d{1,3})$/');
        $h->booked()->kids($kids);

        $r = $h->addRoom();

        // r.rateType
        $rateType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate:'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]");
        $r->setRateType($rateType);

        // r.type
        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room type:'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]");
        $r->setType($roomType);

        // r.description
//        $roomDesc = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room requests:'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]");
//        $r->setDescription($roomDesc, true);

        // r.rate
        $rateText = '';
        $rateRows = $this->http->FindNodes("//text()[{$this->eq($this->t('Daily rate:'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]/descendant::text()[normalize-space(.)]");

        foreach ($rateRows as $rateRow) {
            if (preg_match("/^(?<date>.{6,}?)\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d ]*)$/", $rateRow, $m)) {
                // Thursday, January 10, 2019 AED 261.75
                $rateText .= "\n" . $m['currency'] . $m['amount'] . ' from ' . $m['date'];
            }
        }
        $rateRange = $this->parseRateRange($rateText);

        if ($rateRange !== null) {
            $r->setRate($rateRange);
        }

        // p.currencyCode
        // p.total
        // p.tax
        $payment = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Stay:'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]");

        if (preg_match('/^(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d ]*)/', $payment, $matches)) {
            // AED 598.12
            $h->price()
                ->currency($matches['currency'])
                ->total($this->normalizeAmount($matches['amount']));

            $taxes = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes / Service:'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]");

            if (preg_match('/^' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d ]*)/', $taxes, $m)) {
                $h->price()->tax($this->normalizeAmount($m['amount']));
            }
        }

        // cancellation
        $cancellationText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guarantee and cancellation policies:'))}]/following::text()[normalize-space(.)][1][not(./ancestor::*[self::b or self::strong])]");
        $h->general()->cancellation($cancellationText);

        // nonRefundable
        $h->booked()->parseNonRefundable('Non-refundable booking.');

        // phone
        $contacts = $this->http->FindSingleNode("//text()[{$this->contains($this->t('you can contact us by phone on'))}]/ancestor::*[1]");

        if (preg_match("/{$this->opt($this->t('you can contact us by phone on'))}\s*([+)(\d][-.\s\d)(]{5,}[\d)(])\s*{$this->opt($this->t('or by email at reservations.dia@'))}/", $contacts, $m)) {
            $h->hotel()->phone($m[1]);
        }
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
                    return number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / day';
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / day';
                }
            }
        }

        return null;
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
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
