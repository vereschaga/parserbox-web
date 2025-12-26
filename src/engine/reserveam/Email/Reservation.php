<?php

namespace AwardWallet\Engine\reserveam\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "reserveam/it-592674389.eml, reserveam/it-601814972.eml, reserveam/it-606149420-cancelled.eml, reserveam/it-630202812-recreation.eml, reserveam/it-630592843.eml, reserveam/it-851106950.eml, reserveam/it-856061433.eml, reserveam/it-865856206.eml, reserveam/it-867255744.eml";

    public $providerCode = '';

    public $lang = '';

    public $detectSubjects = [
        'en' => [
            'Your Reservation Confirmation',
            'Confirmation Letter Email',
        ],
    ];

    public $detectBody = [
        'en' => [
            'Important Billing Information:',
        ],
    ];

    public static $dictionary = [
        'en' => [
            'confNumber'                => ['Your Reservation Number:', 'Your Reservation Number :', 'Reservation #'],
            'statusVariants'            => ['Cancelled', 'Canceled'],
            'cancelledStatus'           => ['Cancelled', 'Canceled'],
            'checkIn'                   => ['Arrival Date:', 'Arrival Date :', 'Date:'],
            'checkOut'                  => ['Departure Date:', 'Departure Date :'],
            'timeSubstitute'            => ['Sunset'],
            'occupants'                 => ['# of Occupants', 'Occupants'],
            'totalPrice'                => ['Total:', 'Total :'],
            'baseFare'                  => ['Use Fee:', 'Use Fee :'],
            'car'                       => ['Car', 'Truck'],
            'Site:'                     => ['Site:'],
            'Gay City Day Use Entrance' => ['Gay City Day Use Entrance'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]reserveamerica\.com$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        // detect Provider
        if (empty($headers['from']) || stripos($headers['from'], 'reserveamerica.com') === false) {
            return false;
        }

        // detect Format
        foreach ($this->detectSubjects as $detectSubjects) {
            foreach ($detectSubjects as $dSubjects) {
                if (stripos($headers['subject'], $dSubjects) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider and Format
        return $this->assignProvider($parser->getHeaders()) && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider($parser->getHeaders());
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('Reservation' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        $patterns = [
            'date'          => '.{4,}\b\d{4}\b', // Fri Dec 1 2023
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'’`[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();
        $h->hotel()->house();

        $status = null;

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^([-A-Z\d]{5,})(?:\s*\(\s*(?<status>.*?)\s*\))?$/", $confirmation, $m)) {
            // 2-13596724 (Cancelled)
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, "/^(?:{$this->opt($this->t('Your'))}\s+)?(.{2,}?)[\s:：]*$/u");
            $h->general()->confirmation($m[1], $confirmationTitle);

            if (!empty($m['status']) && preg_match("/^{$this->opt($this->t('statusVariants'))}$/i", $m['status'])) {
                $status = $m['status'];
            }
        }

        if ($status) {
            $h->general()->status($status);
        }

        if (preg_match("/^{$this->opt($this->t('cancelledStatus'))}$/i", $status)) {
            $h->general()->cancelled();
        }

        $section1Text = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('Sales Location'))}]/ancestor::*[ descendant::text()[{$this->starts($this->t('Sales Location Address'))}] ][1]"));
        $section2Text = $this->htmlToText($this->http->FindHTMLByXpath("(//text()[{$this->starts($this->t('checkOut'))}]/ancestor::*[ descendant::text()[{$this->starts($this->t('Site'))}] ][1][not(following::*[{$this->eq($this->t('Transfer To'))}])])[last()]"));

        // example: it-851106950.eml
        if (empty($section2Text)) {
            $section2Text = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('confNumber'))}]/ancestor::tr[1][normalize-space()]"));
        }

        $parkName = $this->re("/^[ ]*{$this->opt($this->t('Sales Location'))}\s*[:]+[ ]*(.{2,}?)[ ]*$/m", $section1Text)
            ?? $this->re("/^(?!\s*{$this->opt($this->t('checkIn'))})[ ]*(.{2,}?)[ ]*\n+[ ]*{$this->opt($this->t('Site'))}\s*:/m", $section2Text)
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('RESERVATION DETAILS'))}]/preceding::text()[normalize-space()][1][parent::a]");
        $address = $this->re("/^[ ]*{$this->opt($this->t('Sales Location Address'))}\s*[:]+[ ]*(.{3,}?)[ ]*$/m", $section1Text);

        $h->hotel()->name($parkName ? 'Stay in a ' . $parkName : null);

        // example: it-865856206.eml
        $state = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Day Use Reservation Confirmation'))}]/preceding::text()[normalize-space()][1]", null, true, "/^\s*(.+?)\s*{$this->opt($this->t('State Parks'))}\s*$/");

        if (empty($address) && !empty($state)) {
            $address = $parkName . ', ' . $state;
        }

        if (empty($address) && (
                $this->http->XPath->query("//text()[{$this->starts($this->t('Sales Location'))} or {$this->starts($this->t('Sales Location Address'))}]")->length === 0 // it-630202812-recreation.eml
                || preg_match("/^[ ]*{$this->opt($this->t('Sales Location Address'))}\s*[:]+[ ]*\n+[ ]*{$this->opt($this->t('Order Date/Time'))}\s*:/m", $section1Text) > 0
            )
        ) {
            $h->hotel()->address($parkName);
        } else {
            $h->hotel()->address($address);
        }

        $siteParams = [];

        if (preg_match("/^[ ]*{$this->opt($this->t('Site'))}\s*[:]+[ ]*(.+?)[ ]*$/m", $section2Text, $m)) {
            $siteParams[] = $m[1];
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Site Type'))}\s*[:]+[ ]*(.+?)[ ]*$/m", $section2Text, $m)) {
            $siteParams[] = $m[1];
        }

        $roomType = implode(', ', $siteParams);

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        $dateStart = strtotime($this->re("/^[ ]*{$this->opt($this->t('checkIn'))}[: ]*({$patterns['date']})[ ]*$/m", $section2Text));
        $timeStart = $this->re("/^[ ]*{$this->opt($this->t('Check-in Time'))}\s*[:]+[ ]*({$patterns['time']})/m", $section2Text);
        $dateEnd = strtotime($this->re("/^[ ]*{$this->opt($this->t('checkOut'))}[: ]*({$patterns['date']})[ ]*$/m", $section2Text));
        $timeEnd = $this->re("/^[ ]*{$this->opt($this->t('Check-out Time'))}\s*[:]+[ ]*({$patterns['time']}|{$this->opt($this->t('timeSubstitute'))})/im", $section2Text);

        // example: it-851106950.eml
        if (empty($timeStart)) {
            $timeStart = $this->re("/^\s*{$this->opt($this->t('Site'))}\s*\:.*?({$patterns['time']})\s/m", $section2Text);
        }

        if (empty($dateEnd)) {
            $dateEnd = $dateStart;
        }

        if (empty($timeStart)) {
            $timeStart = '00:00';
        }

        if (empty($timeEnd)) {
            $timeEnd = '00:00';
        }

        $checkIn = strtotime($timeStart, $dateStart);
        $checkOut = strtotime($timeEnd, $dateEnd);

        $h->booked()->checkIn($checkIn);

        if (preg_match("/^{$this->opt($this->t('timeSubstitute'))}$/i", $timeEnd)
            || !empty($h->getCheckInDate()) && $checkIn >= $checkOut
        ) {
            $h->booked()->noCheckOut();
        } else {
            $h->booked()->checkOut($checkOut);
        }

        $traveller = $this->re("/^[ ]*{$this->opt($this->t('Primary Occupant'))}\s*[:]+[ ]*({$patterns['travellerName']})[ ]*$/mu", $section2Text);
        $h->general()->traveller($traveller, true);

        $occupantsVal = $this->re("/^[ ]*{$this->opt($this->t('occupants'))}\s*[:]+[ ]*([\s\S]+?)[ ]*\n+[ ]*{$this->opt($this->t('# of Vehicles'))}\s*:/m", $section2Text)
        ?? $this->re("/^[ ]*{$this->opt($this->t('occupants'))}\s*[:]+[ ]*(.+?)[ ]*$/m", $section2Text);

        if (preg_match_all("/^[ ]*(\d{1,3})\s*{$this->opt($this->t('Adult'))}(?:\s*\(|[ ]*$)/im", $section2Text, $adultMatches)) {
            $h->booked()->guests(array_sum($adultMatches[1]));
        } elseif (preg_match('/^\d{1,3}$/', $occupantsVal)) {
            // it-630202812-recreation.eml
            $h->booked()->guests($occupantsVal);
        } elseif (preg_match("/^(\d{1,3})[-\s]*(?:{$this->opt($this->t('Adult'))}|{$this->opt($this->t('Senior'))})[^\n,;]*$/i", $occupantsVal, $m)) {
            // 1 Senior (Jan 4-Jan 31)
            $h->booked()->guests($m[1]);
        }

        if (preg_match_all("/^[ ]*(\d{1,3})\s*{$this->opt($this->t('Child'))}(?:\s*\(|[ ]*$)/im", $section2Text, $childMatches)) {
            $h->booked()->kids(array_sum($childMatches[1]));
        }

        if ($h->getCancelled()) {
            return $email;
        }

        $xpathTotal = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPrice'))}]";
        $totalPrice = $this->http->FindSingleNode("//tr[{$xpathTotal}]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $749.88
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($this->normalizeAmount($matches['amount'], $matches['currency']), $currencyCode));

            $xpathCost = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('baseFare'))}]";
            $baseFare = $this->http->FindSingleNode("//tr[{$xpathCost}]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m)) {
                $h->price()->cost(PriceHelper::parse($this->normalizeAmount($m['amount'], $matches['currency']), $currencyCode));
            }

            $discountAmounts = [];

            $feeRows = $this->http->XPath->query("//tr[{$xpathCost}]/following-sibling::tr[ *[normalize-space()][2] and following::tr[{$xpathTotal}] ]");

            // example: it-851106950.eml
            if (count($feeRows) === 0) {
                $feeRows = $this->http->XPath->query("//tr[{$this->starts($this->t('car'))}]/following-sibling::tr[ *[normalize-space()][2] and following::tr[{$xpathTotal}] ]");
            }

            foreach ($feeRows as $i => $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?\s*\(\s*(?<amount>\d[,.‘\'\d ]*?)\s*\)$/u', $feeCharge, $m)) {
                    // $(16.00)
                    $discountAmounts[] = PriceHelper::parse($this->normalizeAmount($m['amount'], $matches['currency']), $currencyCode);
                } elseif (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');

                    if (preg_match("/^{$this->opt($this->t('Total Tax'))}$/i", $feeName)
                        && $i === $feeRows->length - 1 && count($h->getPrice()->getFees()) > 0
                    ) {
                        continue;
                    }

                    $h->price()->fee($feeName, PriceHelper::parse($this->normalizeAmount($m['amount'], $matches['currency']), $currencyCode));
                }
            }

            if (count($discountAmounts) > 0) {
                $h->price()->discount(array_sum($discountAmounts));
            }
        }

        $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('PARK PHONE NUMBER'))}]/following::text()[normalize-space()][1]", null, true, '/^\s*([+\-()\d\s]+?)\s*$/');

        if (!empty($phone)) {
            $h->hotel()->phone($phone);
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

    public static function getEmailProviders()
    {
        return ['reserveam', 'recreation'];
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@reserveamerica.com') !== false
            || $this->http->XPath->query('//a[contains(@href,".reserveamerica.com/")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"ReserveAmerica.com has limited functionality for")]')->length > 0
        ) {
            $this->providerCode = 'reserveam';

            return true;
        }

        if (stripos($headers['from'], '@recreation.gov') !== false
            || $this->http->XPath->query('//a[contains(@href,".recreation.gov/") or contains(@href,"www.recreation.gov")]')->length > 0
        ) {
            $this->providerCode = 'recreation';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0
                && !empty(self::$dictionary[$lang]) && !empty(self::$dictionary[$lang]['confNumber'])
                && !empty(self::$dictionary[$lang]['checkIn']) && !empty(self::$dictionary[$lang]['Site:'])
                && $this->http->XPath->query("//text()[{$this->contains(self::$dictionary[$lang]['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains(self::$dictionary[$lang]['checkIn'])}]")->length > 0
                && ($this->http->XPath->query("//text()[{$this->eq(self::$dictionary[$lang]['Site:'])}]")->length > 0
                    || $this->http->XPath->query("//text()[{$this->eq(self::$dictionary[$lang]['Gay City Day Use Entrance'])}]")->length > 0)
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

    /**
     * @param string|null $s Unformatted string with amount
     * @param string|null $c String with currency
     */
    private function normalizeAmount(?string $s, ?string $c = null): ?string
    {
        if (in_array($c, ['$'])) {
            $s = preg_replace('/[^.\d]/', '', $s); // 1,532.091  ->  1532.091
            $s = (string) round($s, 2); // 1532.091  ->  1532.09
        }

        return $s;
    }
}
