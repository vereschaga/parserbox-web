<?php

namespace AwardWallet\Engine\onehotel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "onehotel/it-312322982.eml, onehotel/it-313149681.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'          => ['CONFIRMATION NUMBER:', 'Confirmation Number:'],
            'dates'               => ['RESERVATION DATES:', 'Reservation Dates:'],
            'roomBooked'          => ['ROOM BOOKED:', 'ROOMS BOOKED:', 'Room Booked:'],
            'statusVariants'      => ['confirmed'],
            'feeNames'            => ['TAXES:', 'RESORT FEE:', 'Resort Fee:', 'Taxes:', 'Amenity Fee:', 'Service Charge:', 'SERVICE CHARGE:'],
            'Hello'               => ['Hello', 'Hi'],
            'DAILY RATE:'         => ['DAILY RATE:', 'Average Daily Rate:', 'Average Daily Rate (Including VAT):', 'AVERAGE DAILY RATE (Including VAT):'],
            "RATE DESCRIPTION:"   => ["RATE DESCRIPTION:", "Rate Description:"],
            'ADULTS:'             => ['ADULTS:', 'Adults:'],
            'TOTAL FOR THIS STAY' => ['TOTAL FOR THIS STAY', 'Total for This Stay*:', 'Room Total* (Including VAT):', 'ROOM TOTAL* (INCLUDING VAT):'],
        ],
    ];

    private $subjects = [
        'en' => ['Your reservation is confirmed'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '1hotels.com') !== false;
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
        if (/*$this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && */ $this->http->XPath->query('//a[normalize-space()="1HOTELS.COM" or normalize-space()="1Hotels.com" or normalize-space()="1hotels.com" or contains(normalize-space(), "1HOTELS.COM")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"booked at 1 Hotel")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourReservation' . ucfirst($this->lang));

        $xpathBold = '(self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold"))';

        $patterns = [
            'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        ];

        $h = $email->add()->hotel();

        if (preg_match("/{$this->opt($this->t('Your reservation is'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i", $parser->getSubject(), $m)) {
            $h->general()->status($m[1]);
        }

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hello'))}]", null, "/^{$this->opt($this->t('Hello'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $h->general()->traveller($traveller, true);

        $confirmation = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('confNumber'))}]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]*([-A-z\d]{5,})$/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $dateCheckIn = $dateCheckOut = null;
        $dates = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('dates'))}]", null, true, "/^{$this->opt($this->t('dates'))}[: ]*(.*\d.*)$/");
        $this->logger->debug($dates);

        if (preg_match("/^(.{3,}?)\s*-\s*(.{3,})$/", $dates, $m)) {
            $dateCheckIn = $this->normalizeDate($m[1]);
            $dateCheckOut = $this->normalizeDate($m[2]);
        }

        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-in time:'))}]", null, true, "/{$this->opt($this->t('Check-in time:'))}[: ]*({$patterns['time']})/");

        if ($timeCheckIn) {
            $dateCheckIn = strtotime($timeCheckIn, $dateCheckIn);
        }

        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-out time:'))}]", null, true, "/{$this->opt($this->t('Check-out time:'))}[: ]*({$patterns['time']})/");

        if ($timeCheckOut) {
            $dateCheckOut = strtotime($timeCheckOut, $dateCheckOut);
        }

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        $rateDesc = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('RATE DESCRIPTION:'))}]", null, true, "/^{$this->opt($this->t('RATE DESCRIPTION:'))}[: ]*(.{2,})$/");

        $roomType = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('roomBooked'))}]", null, true, "/^{$this->opt($this->t('roomBooked'))}[: ]*(.{2,})$/");

        $dailyRate = $this->http->FindNodes("//*[not(.//tr) and {$this->eq($this->t('DAILY RATE:'))}]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::tr[count(*[normalize-space()])=2]/*[normalize-space()][2]", null, "/^[^\-\d)(]+?[ ]*\d[,.‘\'\d ]*$/");

        if (count($dailyRate) === 0) {
            $dailyRate = $this->http->FindSingleNode("//*[not(.//tr) and {$this->eq($this->t('DAILY RATE:'))}]/following::text()[normalize-space()][1]", null, true, "/^[^\-\d)(]+?[ ]*\d[,.‘\'\d ]*$/");
        }

        $room = $h->addRoom();
        $room->setRateType($rateDesc)->setType($roomType);

        if (is_array($dailyRate)) {
            $room->setRates($dailyRate);
        } elseif (!empty($dailyRate)) {
            $room->setRate($dailyRate . ' / night');
        }

        $totalPrice = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('TOTAL FOR THIS STAY'))}]", null, true, "/^{$this->opt($this->t('TOTAL FOR THIS STAY'))}[*: ]+(.*\d.*)$/");

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $1,358.18
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($this->normalizeCurrency($matches['currency']))->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $roomTotal = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('ROOM TOTAL:'))}]", null, true, "/^{$this->opt($this->t('ROOM TOTAL:'))}\s*(.*\d.*)$/");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $roomTotal, $m)) {
                $h->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeRows = $this->http->XPath->query("//tr[not(.//tr) and {$this->starts($this->t('feeNames'))}]");

            foreach ($feeRows as $feeRow) {
                if (preg_match("/^({$this->opt($this->t('feeNames'))})\s*(.*\d.*)$/", $this->http->FindSingleNode('.', $feeRow), $m2)
                    && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $m2[2], $m)
                ) {
                    $h->price()->fee(rtrim($m2[1], ': '), PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }

        $xpathHotelName = "{$this->starts(['1 HOTEL', '1 hotel', '1 Hotel'])}";

        $hotelInfo = implode("\n", $this->http->FindNodes("//*[ *[normalize-space()][1][{$xpathHotelName}] and *[normalize-space()][2] ]/tr[normalize-space()]"));

        if (empty($hotelInfo)) {
            // it-313149681.eml
            $hotelInfo = $this->htmlToText($this->http->FindHTMLByXpath("descendant::tr[ not(.//tr[normalize-space()]) and descendant::text()[normalize-space()][1][ancestor::*[{$xpathBold}] and {$xpathHotelName}] ][1]"));
        }

        if (preg_match("/^(?<name>.{2,})(?<address>(?:\n+.{2,}){1,4}?)(?:\n+Reservations:|\n+T:|$)/", $hotelInfo, $m)) {
            $h->hotel()->name($m['name'])->address(preg_replace('/\s+/', ' ', trim($m['address'])));
        }

        if (preg_match("/^(?:T:|Reservations:)[ ]*({$patterns['phone']})$/m", $hotelInfo, $m)) {
            $h->hotel()->phone($m[1]);
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->contains($this->t('ADULTS:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('ADULTS:'))}\s*(\d+)/");

        if (!empty($guests)) {
            $h->booked()
                ->guests($guests);
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['dates'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['dates'])}]")->length > 0
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

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'SGD' => ['SG$'],
            'ZAR' => ['R'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^[\w\-]+\,\s*(\d+)\.?\s*(?:de\s+)?(\w+)(?:\s+de)?\s*(\d{4})$#u", //Miércoles, 19 de mayo de 2021
            "#^(\d+)\s*(\d+)\,\s*(\d{4})$#u", //03 13, 2024
        ];
        $out = [
            "$1 $2 $3",
            "$2.$1.$3",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
