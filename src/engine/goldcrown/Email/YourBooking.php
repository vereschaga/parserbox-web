<?php

namespace AwardWallet\Engine\goldcrown\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "goldcrown/it-160120004.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'checkIn'    => ['Check-in', 'Check-In'],
            'checkOut'   => ['Check-out', 'Check-Out'],
            'confNumber' => ['Booking number', 'Booking Number'],
            'rateType'   => ['Rate type', 'Rate Type'],
            'whoStaying' => ["Who's staying", "Who's Staying"],
            'guestNames' => ['Guest names', 'Guest Names'],
            'totalPrice' => ['Total Price', 'Total price', 'TOTAL PRICE'],
        ],
    ];

    private $subjects = [
        'en' => ['Your booking has been confirmed with'],
    ];

    private $detectors = [
        'en' => ['Booking Information'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@news.bestwestern.co.uk') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Best Western') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".bestwestern.co.uk/") or contains(@href,"confirmations.bestwestern.co.uk")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Visit bestwestern.co.uk") or contains(normalize-space(),"Best Western International. Inc. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourBooking' . ucfirst($this->lang));

        $this->parseHotel($email);

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

    private function parseHotel(Email $email): void
    {
        $patterns = [
            'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        ];

        $h = $email->add()->hotel();

        $addressTexts = [];
        $addressRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->starts($this->t('checkIn'))}] ]/*[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()]");

        foreach ($addressRows as $aRow) {
            $addressTexts[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $aRow));
        }
        $addressText = preg_replace("/^([\s\S]{3,}?)[ ]*\n+[ ]*{$this->opt($this->t('Sat Nav Postcode'))}[\s\S]*$/", '$1', implode("\n", $addressTexts));

        if (preg_match("/^(?<name>.{2,}?)[ ]*\n+[ ]*(?<address>[\s\S]{3,}?)\s*$/", $addressText, $m)) {
            $h->hotel()->name($m['name'])->address(preg_replace('/\s+/', ' ', $m['address']));
        }

        $datesText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('checkIn'))}]/ancestor::*[ descendant::text()[{$this->starts($this->t('checkOut'))}] ][1]"));

        $checkIn = $this->re("/^[ ]*{$this->opt($this->t('checkIn'))}[: ]*\n+[ ]*(.*\d.*?)[ ]*$/m", $datesText);
        $checkOut = $this->re("/^[ ]*{$this->opt($this->t('checkOut'))}[: ]*\n+[ ]*(.*\d.*?)[ ]*$/m", $datesText);
        $h->booked()
            ->checkIn2($this->normalizeDate($checkIn))
            ->checkOut2($this->normalizeDate($checkOut))
        ;

        $phone = $this->re("/^[ ]*{$this->opt($this->t('Contact Number'))}[: ]*\n+[ ]*({$patterns['phone']})[ ]*$/m", $datesText);
        $h->hotel()->phone($phone);

        $bookingTexts = [];
        $bookingRows = $this->http->XPath->query("//text()[{$this->starts($this->t('whoStaying'))}]/ancestor::*[ descendant::text()[{$this->starts($this->t('Room #'))}] and descendant::text()[{$this->starts($this->t('Room Details For'))}] ][1]/descendant::tr[not(.//tr) and normalize-space()]");

        foreach ($bookingRows as $bRow) {
            $bookingTexts[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $bRow));
        }
        $bookingText = implode("\n", $bookingTexts);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        } elseif (preg_match("/^({$this->opt($this->t('confNumber'))})[: ]+([-A-Z\d]{5,})$/", $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]"), $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        $adultValues = $childValues = $guestNames = $detailsValues = [];

        $roomsTexts = $this->splitText($bookingText, "/^([ ]*{$this->opt($this->t('Room #'))} \d.*)/m", true);

        foreach ($roomsTexts as $roomText) {
            $room = $h->addRoom();

            $rNum = $this->re("/^[ ]*{$this->opt($this->t('Room #'))} (\d+)(?: |\n)/", $roomText);

            $roomType = $this->re("/^[ ]*{$this->opt($this->t('Room #'))} \d+[ ]+-[ ]+(.{2,}?)[ ]*\n/", $roomText);
            $room->setType($roomType);

            $rateType = $this->re("/^[ ]*{$this->opt($this->t('rateType'))}[ ]*[:]+[ ]*(.{2,}?)[ ]*$/m", $roomText);
            $room->setRateType($rateType);

            $whoStayingValue = $this->re("/^[ ]*{$this->opt($this->t('whoStaying'))}[ ]*[:]+[ ]*(.{2,}?)[ ]*$/m", $roomText);
            $adults = $this->re("/\b(\d{1,3})\s*{$this->opt($this->t('adult'))}/i", $whoStayingValue);

            if ($adults !== null) {
                $adultValues[] = $adults;
            }
            $childs = $this->re("/\b(\d{1,3})\s*{$this->opt($this->t('child'))}/i", $whoStayingValue);

            if ($childs !== null) {
                $childValues[] = $childs;
            }

            $gNames = $this->re("/^[ ]*{$this->opt($this->t('guestNames'))}[ ]*[:]+[ ]*((?:[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]][, ]*)+)[ ]*$/m", $roomText);
            $guestNames = array_merge($guestNames, preg_split('/\s*[,]+\s*/', $gNames));

            $rDetails = $this->re("/^[ ]*{$this->opt($this->t('Room Details For Room'))} {$rNum}[ ]*\n+[ ]*(.{2,}?)[ ]*$/im", $roomText);

            if ($rDetails !== null) {
                $detailsValues[] = $rDetails;
            }
        }

        if (count($adultValues) > 0) {
            $h->booked()->guests(array_sum($adultValues));
        }

        if (count($childValues) > 0) {
            $h->booked()->kids(array_sum($childValues));
        }

        if (count($guestNames) > 0) {
            $h->general()->travellers(array_unique($guestNames), true);
        }

        $roomDetails = count(array_unique($detailsValues)) === 1 ? $detailsValues[0] : null;

        if (preg_match("/To (?i)avoid charge, cancell? by (?<time>{$patterns['time']}) hotel time on (?<date>.+? \d{4}) Guaranteed for one night's room costs against/", $roomDetails, $m)
        ) {
            $h->booked()->deadline(strtotime($m['time'], strtotime($m['date'])));
        } elseif (preg_match("/This (?i)rate cannot be cancell?ed and is non-amendable and non-refundable\./", $roomDetails)
        ) {
            $h->booked()->nonRefundable();
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPrice'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // £ 99.75
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['checkIn']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
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

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // Saturday, 25/06/2022
            '/^[-[:alpha:]]+[ ]*,[ ]*(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/u',
        ];
        $out = [
            '$2/$1/$3',
        ];

        return preg_replace($in, $out, $text);
    }
}
