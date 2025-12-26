<?php

namespace AwardWallet\Engine\slh\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourBookingSummary extends \TAccountChecker
{
    public $mailFiles = "slh/it-16989313.eml, slh/it-17382887.eml, slh/it-32712315.eml";

    private $langDetectors = [
        'en' => ['Check Out', 'Check out:'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'afterBefore' => ['after', 'before'],
        ],
    ];

    private $patterns = [
        'phone'         => '[+-.\s\d)(]{7,}', // +377 (93) 15 48 52    |    713.680.2992 | 81-78-3711111
        'confNumber'    => '[A-Z\d]{5,}', // 11986371476    |    M5GPQK
        'time'          => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.    |    3pm
        'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@slh.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Your Booking Confirmation -') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query('//a[contains(@href,"//www.slh.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(.),"Copyright Small Luxury Hotels of the World") or contains(.,"@slh.com") or contains(.,"www.slh.com")]')->length === 0
        ) {
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

        $type = '';

        if ($this->http->XPath->query("//td[{$this->eq($this->t('Check Out'))}]")->length > 0) {
            // it-16989313.eml, it-17382887.eml
            $type = '1';
            $this->parseHotel1($email);
        } else {
            // it-32712315.eml
            $type = '2';
            $this->parseHotel2($email);
        }

        $email->setType('YourBookingSummary' . $type . ucfirst($this->lang));

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

    private function parseHotel1(Email $email)
    {
        $h = $email->add()->hotel();

        $xpathFragment1 = '(self::td or self::th)';

        $xpathFragmentConfNumber = '//text()[' . $this->starts($this->t('Reservation Number')) . ']';

        $xpathFragmentHotel = $xpathFragmentConfNumber . '/ancestor::*[' . $xpathFragment1 . '][ ./preceding-sibling::*[' . $xpathFragment1 . '] ][1]/preceding-sibling::*[' . $xpathFragment1 . '][normalize-space(.)][1]';

        // hotelName
        $h->hotel()->name($this->http->FindSingleNode($xpathFragmentHotel . '/descendant::text()[normalize-space(.)][1][ ./ancestor::*[self::strong or self::b] ]'));

        // address
        // phone
        $addressTexts = $this->http->FindNodes($xpathFragmentHotel . '/descendant::text()[normalize-space(.)][position()>1]');
        $addressText = implode(' ', $addressTexts);
        // 35 St James s Place, London, United Kingdom Tel 44-207-4914840
        if (preg_match('/^(.+?)\s*Tel\s*(' . $this->patterns['phone'] . ')/is', $addressText, $matches)) {
            $h->hotel()->address($matches[1]);
            $h->hotel()->phone($matches[2]);
        } elseif ($addressText) {
            $h->hotel()->address($addressText);
        }

        // confirmation number
        $confirmationCodeTitle = $this->http->FindSingleNode($xpathFragmentConfNumber);
        $confirmationCode = $this->http->FindSingleNode($xpathFragmentConfNumber . '/following::text()[normalize-space(.)][1]', null, true, "/^({$this->patterns['confNumber']})$/");
        $h->general()->confirmation($confirmationCode, $confirmationCodeTitle);

        $xpathFragment2 = '/ancestor::*[' . $xpathFragment1 . '][1]/following-sibling::*[' . $xpathFragment1 . '][normalize-space(.)][1]';

        // travellers
        $guestNames = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Guest Name/s')) . ']' . $xpathFragment2);
        $h->general()->travellers(array_filter(preg_split('/\s*,\s*/', $guestNames)));

        $patterns['dateTime'] = '/^(?<date>.+?)[,\s]+' . $this->opt($this->t('afterBefore')) . '\s+(?<time>' . $this->patterns['time'] . ')/i';

        // checkInDate
        $dateCheckInText = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Check In')) . ']' . $xpathFragment2);

        if (preg_match($patterns['dateTime'], $dateCheckInText, $matches)) {
            if ($dateCheckInNormal = $this->normalizeDate($matches['date'])) {
                $h->booked()->checkIn2($dateCheckInNormal . ', ' . $matches['time']);
            }
        } elseif ($dateCheckInText) {
            if ($dateCheckInNormal = $this->normalizeDate($dateCheckInText)) {
                $h->booked()->checkIn2($dateCheckInNormal);
            }
        }

        // checkOutDate
        $dateCheckOutText = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Check Out')) . ']' . $xpathFragment2);

        if (preg_match($patterns['dateTime'], $dateCheckOutText, $matches)) {
            if ($dateCheckOutNormal = $this->normalizeDate($matches['date'])) {
                $h->booked()->checkOut2($dateCheckOutNormal . ', ' . $matches['time']);
            }
        } elseif ($dateCheckOutText) {
            if ($dateCheckOutNormal = $this->normalizeDate($dateCheckOutText)) {
                $h->booked()->checkOut2($dateCheckOutNormal);
            }
        }

        // guestCount
        $h->booked()->guests($this->http->FindSingleNode('//text()[' . $this->eq($this->t('Adults')) . ']' . $xpathFragment2, null, true, '/^(\d{1,3})$/'));

        // kidsCount
        $h->booked()->kids($this->http->FindSingleNode('//text()[' . $this->eq($this->t('Children')) . ']' . $xpathFragment2, null, true, '/^(\d{1,3})$/'));

        $r = $h->addRoom();

        // r.type
        // r.rateType
        $roomRate = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Room & Rate')) . ']' . $xpathFragment2);

        if (preg_match('/^(.+?)\s*\(([^)(]+)\)$/', $roomRate, $matches)) {
            $r->setType($matches[1]);
            $r->setRateType($matches[2]);
        } else {
            $r->setType($roomRate);
        }

        // cancellation
        $ratePolicies = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Rate Policies')) . ']' . $xpathFragment2);

        if ($ratePolicies) {
            $ratePoliciesParts = preg_split('/\.\s*\b/', $ratePolicies);
            $ratePoliciesParts = array_filter($ratePoliciesParts, function ($item) {
                return stripos($item, 'cancel') !== false;
            });
            $cancellationText = implode('. ', $ratePoliciesParts);
            $h->general()->cancellation($cancellationText, true);
        }

        if (preg_match('/Cancel by (\d{1,2}\s?[AP]M) - local time - (\d{1,2} days) prior to arrival to avoid 100% of total stay penalty fee/i', $h->getCancellation(), $m)) {
            $h->booked()->deadlineRelative($m[2], $m[1]);
        }

        // p.currencyCode
        // p.total
        $totalPrice = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Total price including tax:')) . ']' . $xpathFragment2);

        if (preg_match('/^(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)/', $totalPrice, $matches)) {
            $h->price()->total($this->normalizeAmount($matches['amount']));
            $h->price()->currency($matches['currency']);
        }

        // r.rate
        $dailyRateBreakdownTexts = $this->http->FindNodes('//text()[' . $this->eq($this->t('Daily Rate Breakdown:')) . ']/ancestor::*[' . $xpathFragment1 . '][1]/descendant::text()[normalize-space(.)]');
        $dailyRateBreakdownText = implode(' ', $dailyRateBreakdownTexts);
        // Wednesday, July 25, 2018 GBP 305.00
        if (preg_match_all('/\b\d{2,4}\s+(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\d\s]*)(?:[^A-z]|\b)/', $dailyRateBreakdownText, $rateMatches)) {
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
    }

    private function parseHotel2(Email $email)
    {
        $xpathFragmentBold = '(self::b or self::strong)';

        $guestName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest name/s:'))}]/following::text()[normalize-space(.)][1][not({$xpathFragmentBold})]", null, true, "/^{$this->patterns['travellerName']}$/");

        $hotelsText = '';

        $hotelsContainers = $this->http->XPath->query("//text()[{$this->eq($this->t('Hotel details:'))}]/ancestor::tr[ descendant::text()[{$this->eq($this->t('Check in:'))}] ][1]");

        foreach ($hotelsContainers as $hotelsContainer) {
            $hotelsHtml = $hotelsContainer->ownerDocument->saveHTML($hotelsContainer);
            $hotelsText .= "\n" . $this->htmlToText($hotelsHtml);
        }
        //$this->logger->debug($hotelsText);
        $hotels = $this->splitText($hotelsText, "/\s+({$this->opt($this->t('Hotel details:'))})\s+/ms", true);

        foreach ($hotels as $hotel) {
            $h = $email->add()->hotel();

            $itineraryNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your itinerary number is'))}]");

            if (preg_match("/({$this->opt($this->t('Your itinerary number is'))})\s*({$this->patterns['confNumber']})/i", $itineraryNumber, $m)) {
                $h->addConfirmationNumber($m[2], $m[1]);
            }

            if ($guestName) {
                $h->general()->traveller($guestName);
            }

            if (preg_match("/{$this->opt($this->t('Hotel details:'))}\s*(.{3,})/", $hotel, $matches)) {
                if (preg_match("/^(?<name>[^,]{3,})\s*,\s*(?<address>.+?)\s*\b{$this->opt($this->t('Tel'))}\s*(?<phone>{$this->patterns['phone']})/", $matches[1], $m)) {
                    // Ca Sagredo Hotel, Campo Santa Sofia 4198 99, Ca d Oro, Venice, Italy. Tel 39-041-2413111
                    $h->hotel()
                        ->name($m['name'])
                        ->address(trim($m['address'], '. '))
                        ->phone($m['phone'])
                    ;
                } elseif (preg_match("/^(?<name>[^,]{3,})\s*,\s*(?<address>.{3,})/", $matches[1], $m)) {
                    $h->hotel()->address(trim($matches[1], '. '));
                }
            }

            if (preg_match("/({$this->opt($this->t('Reservation number:'))})\s*({$this->patterns['confNumber']})/", $hotel, $m)) {
                $h->addConfirmationNumber($m[2], preg_replace('/\s*:\s*$/', '', $m[1]));
            }

            if (preg_match("/{$this->opt($this->t('Check in:'))}\s*(.{6,})/", $hotel, $m)) {
                $h->booked()->checkIn2($m[1]);
            }

            if (preg_match("/{$this->opt($this->t('Check out:'))}\s*(.{6,})/", $hotel, $m)) {
                $h->booked()->checkOut2($m[1]);
            }

            if (preg_match("/{$this->opt($this->t('Adults:'))}\s*(\d{1,3})\b/", $hotel, $m)) {
                $h->booked()->guests($m[1]);
            }

            if (preg_match("/{$this->opt($this->t('Children:'))}\s*(\d{1,3})\b/", $hotel, $m)) {
                $h->booked()->kids($m[1]);
            }

            $r = $h->addRoom();

            if (preg_match("/{$this->opt($this->t('Room & rate:'))}\s*(.+)/", $hotel, $m)) {
                $r->setType($m[1]);
            }

            if (preg_match("/{$this->opt($this->t('Rate details:'))}\s*(.+)/", $hotel, $m)) {
                $r->setDescription($m[1]);
            }

            if (preg_match("/{$this->opt($this->t('Rate policies:'))}\s*(.*?cancel.*?)Special request/si", $hotel, $matches)) {
                $h->general()->cancellation($matches[1]);

                if (
                    preg_match("/Cancel by\s*(?<hour>{$this->patterns['time']})(?:\s*\(noon\))?\s*- local time -\s*(?<prior>\d{1,3})\s*days?\s*prior to arrival to avoid 100% of total stay penalty fee/i", $matches[1], $m) // en
                ) {
                    $h->booked()->deadlineRelative($m['prior'] . ' days -1 day', $m['hour']);
                }
            }

            if (preg_match("/{$this->opt($this->t('Total price including tax:'))}\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)/", $hotel, $matches)) {
                // EUR 1,155.00
                $h->price()
                    ->currency($matches['currency'])
                    ->total($this->normalizeAmount($matches['amount']))
                ;
            }
        }
    }

    private function htmlToText($s = '', $brConvert = true): string
    {
        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('#<[Bb][Rr]\b[ ]*/?>#', "\n", $s); // only <br> tags
        }
        $s = preg_replace('#<[A-z]+\b.*?/?>#', "\n", $s); // opening tags
        $s = preg_replace('#</[A-z]+\b[ ]*>#', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
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

    private function normalizeDate(string $string)
    {
        if (preg_match('/\b([^\d\W]{3,})\s+(\d{1,2})\s*,\s*(\d{4})$/u', $string, $matches)) { // Wednesday, July 25, 2018
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
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
