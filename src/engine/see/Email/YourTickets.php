<?php

namespace AwardWallet\Engine\see\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

// for HTML (for PDF look parser see/ReceiptPdf)

class YourTickets extends \TAccountChecker
{
    public $mailFiles = ""; // look examples in parser see/ReceiptPdf

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'orderNumber' => ['Order number'],
            'feeNames'    => ['Service fee', 'Facility fee', 'Tax'],
        ],
    ];

    private $subjects = [
        'en' => ['Here are your Tickets'],
    ];

    private $detectors = [
        'en' => ['Your Receipt'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@seetickets.us') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".seetickets.us/") or contains(@href,"mail.seetickets.us")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Sincerely, See Tickets") or contains(normalize-space(),"© See Tickets. All Rights Reserved")]')->length === 0
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
        $email->setType('YourTickets' . ucfirst($this->lang));

        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        ];

        $ev = $email->add()->event();
        $ev->place()->type(Event::TYPE_EVENT);

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Here are your tickets.'))}]/preceding::text()[normalize-space()][1]", null, true, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*,$/u");
        $ev->general()->traveller($traveller, false);

        $eventName = $address = $dateStart = $dateEnd = $timeOpen = $timeStart = $timeEnd = null;

        // v1 (start)
        $xpathMainRowV1 = "//tr/*[ count(p[normalize-space()])=3 and p[normalize-space()][3][descendant::img[contains(@src,'/loc-white.')] or descendant::a[contains(@href,'/wf/click?')]] ]";

        $eventName_temp = $this->http->FindSingleNode($xpathMainRowV1 . "/p[normalize-space()][1]");
        $dates_temp = $this->http->FindSingleNode($xpathMainRowV1 . "/p[normalize-space()][2]");
        $addressTexts = $this->http->FindNodes($xpathMainRowV1 . "/p[normalize-space()][3]/descendant::text()[normalize-space()]");

        if (!empty($addressTexts)) {
            $addressTexts[0] .= " (view on map)";
        }

        $address_temp = implode("\n", $addressTexts);
        // v1 (end)

        // v2 (start)
        $xpathMainRowV2 = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->contains($this->t('view on map'))}] ]";

        $cell1Rows = $this->http->FindNodes($xpathMainRowV2 . "/*[normalize-space()][1]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()]");

        if (empty($cell1Rows)) {
            $cell1Rows = [$eventName_temp, $dates_temp];
        }

        $cell2Text = implode("\n", $this->http->FindNodes($xpathMainRowV2 . "/*[normalize-space()][2]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()]"));

        if (empty($cell2Text)) {
            $cell2Text = $address_temp;
        }

        if (count($cell1Rows) === 2) {
            $eventName = $cell1Rows[0];

            if (preg_match("/^(.{2,}?)[ ]+[-–]+[ ]+({$patterns['time']})$/", $eventName, $m)) {
                // The NXT Show - 8:00pm
                $eventName = $m[1];
                $timeStart = $m[2];
            }

            if (preg_match("/^(?<date1>.{4,}?)(?:[, ]+(?<time1>{$patterns['time']}))?\s+[-–]+\s+(?<date2>.{4,}?)(?:[, ]+(?<time2>{$patterns['time']}))?$/", $cell1Rows[1], $m)) {
                // Wed Feb 15, 2023 05:30PM - Sun Feb 19, 2023
                $dateStart = $m['date1'];
                $dateEnd = $m['date2'];

                if (!empty($m['time1'])) {
                    $timeStart = $m['time1'];
                }

                if (!empty($m['time2'])) {
                    $timeEnd = $m['time2'];
                }

                if (!$timeEnd) {
                    $timeEnd = '23:59';
                }
            } elseif (preg_match("/^(?<date>.{4,})[-–, ]+(?<time>{$patterns['time']})$/", $cell1Rows[1], $m)) {
                // 02/29/2020 09:00PM
                $dateStart = $dateEnd = $m['date'];
                $timeStart = $m['time'];
            } elseif (!empty($cell1Rows[1])) {
                $dateStart = $dateEnd = $cell1Rows[1];
            }
        }

        $addressText = preg_replace("/\n+.*(?:{$this->opt($this->t('Doors'))}|{$this->opt($this->t('Show'))}|{$this->opt($this->t('Ends'))}).*/", '', $cell2Text);

        if (preg_match("/^(?<a1>.{2,}?)[( ]*{$this->opt($this->t('view on map'))}[ )]*(?:\n+(?<a2>.{2,}))?$/", $addressText, $m)) {
            $address = $m['a1'];

            if (!empty($m['a2'])) {
                $address .= ', ' . $m['a2'];
            }
        }

        if (preg_match("/{$this->opt($this->t('Doors'))}\s+({$patterns['time']})/", $cell2Text, $m)) {
            $timeOpen = $m[1];
        }

        if (preg_match("/{$this->opt($this->t('Show'))}\s+({$patterns['time']})/", $cell2Text, $m)) {
            $timeStart = $m[1];
        }

        if (preg_match("/{$this->opt($this->t('Ends'))}\s+({$patterns['time']})/", $cell2Text, $m)) {
            $timeEnd = $m[1];
        }
        // v2 (end)

        $ev->place()->name($eventName)->address($address);

        if (!$timeStart) {
            $timeStart = $timeOpen;
        }

        if ($dateStart && $timeStart) {
            $ev->booked()->start(strtotime($timeStart, strtotime($dateStart)));
        } elseif ($dateStart && !preg_match("/\n.*(?:{$this->opt($this->t('Doors'))}|{$this->opt($this->t('Show'))}|{$this->opt($this->t('Ends'))}).*/", $cell2Text)) {
            $ev->booked()->start2($dateStart);
        }

        if ($dateEnd && $timeEnd) {
            $bookedEnd = strtotime($timeEnd, strtotime($dateEnd));
            $ev->booked()->end($ev->getStartDate() && $bookedEnd && $ev->getStartDate() > $bookedEnd ? strtotime('+1 days', $bookedEnd) : $bookedEnd);
        } elseif (!$timeEnd) {
            $ev->booked()->noEnd();
        }

        $seats = [];
        $seatsRows = $this->http->XPath->query("//p[{$this->starts($this->t('Section'))} or {$this->starts($this->t('Row'))} or {$this->starts($this->t('Seat'))}]");

        foreach ($seatsRows as $sRow) {
            $sRowText = implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()]", $sRow));
            $seatComponents = [];

            if (preg_match("/\b({$this->opt($this->t('Section'))})[: ]+(.*?)[ ]*(?:\b{$this->opt($this->t('Row'))}|\b{$this->opt($this->t('Seat'))}|$)/i", $sRowText, $m)
                && !empty($m[2])
                && !preg_match("/^(?:{$this->opt($this->t('Row'))}|{$this->opt($this->t('Seat'))})$/i", $m[2])
            ) {
                $seatComponents[] = $m[1] . ': ' . $m[2];
            }

            if (preg_match("/\b({$this->opt($this->t('Row'))})[: ]+(.*?)[ ]*(?:\b{$this->opt($this->t('Section'))}|\b{$this->opt($this->t('Seat'))}|$)/i", $sRowText, $m)
                && !empty($m[2])
                && !preg_match("/^(?:{$this->opt($this->t('Section'))}|{$this->opt($this->t('Seat'))})$/i", $m[2])
            ) {
                $seatComponents[] = $m[1] . ': ' . $m[2];
            }

            if (preg_match("/\b({$this->opt($this->t('Seat'))})[: ]+(.*?)[ ]*(?:\b{$this->opt($this->t('Row'))}|\b{$this->opt($this->t('Section'))}|$)/i", $sRowText, $m)
                && !empty($m[2])
                && !preg_match("/^(?:{$this->opt($this->t('Row'))}|{$this->opt($this->t('Section'))})$/i", $m[2])
            ) {
                $seatComponents[] = $m[1] . ': ' . $m[2];
            }

            if (count($seatComponents) > 0) {
                $seats[] = implode(', ', $seatComponents);
            }
        }

        if (count($seats) > 0) {
            $ev->booked()->seats(array_unique($seats));
        }

        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('orderNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[-A-z\d]{4,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('orderNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $ev->general()->confirmation($confirmation, $confirmationTitle);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $113.94
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[preceding-sibling::tr[count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Billed to card ending in'))}]] and following-sibling::tr[count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('feeNames'))}]] and not(*[normalize-space()][1][{$this->eq($this->t('feeNames'))}]) and count(*[normalize-space()])=2]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m)) {
                $email->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('feeNames'))}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $email->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }

            $promoCode = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Promo Code'))}] ]/*[normalize-space()][2]", null, true, '/^.+\([ ]*(.*\d.*?)[ ]*\)$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $promoCode, $m)) {
                $email->price()->discount(PriceHelper::parse($m['amount'], $currencyCode));
            }
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
            if (!is_string($lang) || empty($phrases['orderNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['orderNumber'])}]")->length > 0) {
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
}
