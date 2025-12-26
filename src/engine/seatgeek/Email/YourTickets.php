<?php

namespace AwardWallet\Engine\seatgeek\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourTickets extends \TAccountChecker
{
    public $mailFiles = "seatgeek/it-126972593.eml, seatgeek/it-409482666.eml";
    public $subjects = [
        'Your tickets have been',
        'Your e-tickets are available',
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'SeatGeek') === false
        ) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".seatgeek.com/") or contains(@href,"links.seatgeek.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") or contains(normalize-space(),"SeatGeek, Inc")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//text()[{$this->contains($this->t('Order number'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Sale date'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@seatgeek.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('YourTickets' . ucfirst($this->lang));
        $this->date = strtotime($parser->getDate());

        $event = $email->add()->event();

        $eventName = $address = $dateStart = null;

        $event->general()
            ->date($this->normalizeDate($this->getField('Sale date')))
            ->traveller($this->getField('Customer Name', "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u"), true)
            ->confirmation($this->getField('Order number', "/^[-A-z\d ]{5,}$/"), 'Order number');

        $eventRows = $this->http->FindNodes("//tr[ preceding-sibling::tr[{$this->eq($this->t('Event'))}] and following-sibling::tr/descendant::text()[normalize-space()][1][{$this->eq($this->t('Section'))}] ]");

        if (count($eventRows) === 3) {
            // it-409482666.eml
            $eventName = $eventRows[0];
            $address = $eventRows[1];
            $dateStart = $eventRows[2];
        }

        $event
            ->setName($this->getField('Event') ?? $eventName)
            ->setAddress($this->getField('Venue') ?? $address)
            ->type()->event();

        $dateStart = $this->getField('Time') ?? $dateStart;

        $xpathSeats = "//tr[ count(*[normalize-space()])=3 and *[normalize-space()][1][{$this->starts($this->t('Section'))}] and *[normalize-space()][2][{$this->starts($this->t('Row'))}] and *[normalize-space()][3][{$this->starts($this->t('Seats'))}] ]"; // it-409482666.eml

        $section = $this->getField('Section') ?? $this->http->FindSingleNode($xpathSeats . "/*[normalize-space()][1]", null, true, "/^{$this->opt($this->t('Section'))}\s*(.+)$/");
        $row = $this->getField('Row') ?? $this->http->FindSingleNode($xpathSeats . "/*[normalize-space()][2]", null, true, "/^{$this->opt($this->t('Row'))}\s*(.+)$/");
        $seatsText = $this->http->FindSingleNode($xpathSeats . "/*[normalize-space()][3]", null, true, "/^{$this->opt($this->t('Seats'))}\s*(.+)$/");
        $seats = $seatsText === null ? [] : preg_split("/(\s*,\s*)+/", $seatsText);

        if (count($seats) === 0 && $section && $row) {
            // it-126972593.eml
            $event->booked()->seat($section . '/' . $row);
        } else {
            // it-409482666.eml
            foreach ($seats as $seat) {
                $seatParts = [];

                if ($section) {
                    $seatParts[] = $section;
                }

                if ($row) {
                    $seatParts[] = $row;
                }

                $seatParts[] = $seat;
                $event->booked()->seat(implode('/', $seatParts));
            }
        }

        $event->booked()
            ->start($this->normalizeDate($dateStart))
            ->noEnd()
            ->guests($this->getField('Quantity', "/^\d{1,3}$/"));

        $totalPrice = $this->getField('Total cost');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $220.07    |    US $1,214.75
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $event->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
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

    private function getField(string $name, ?string $re = null): ?string
    {
        return $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t($name))}] ]/*[normalize-space()][2]", null, true, $re)
            ?? $this->http->FindSingleNode("//tr[{$this->eq($this->t($name))}]/following-sibling::tr[normalize-space()][1]", null, true, $re);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function normalizeDate($date)
    {
        //$this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\s*([\d\:]+\s*A?P?M)$#", // November 16, 2021 11:55 AM
            "#^\s*\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*at\s*([\d\:]+\s*A?P?M)$#", //Fri, Dec 17, 2021 at 7:00PM
            "#^\s*\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*\,\s*Time\s*TBD$#", //Wed, Jun 1, 2022, Time TBD
            "#^(\w+)\,\s*(\w+)\s*(\d+)\s*at\s*([\d\:]+\s*a?p?m)$#", //Sat, Jan 1 at 7:30pm
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
            "$2 $1 $3",
            "$1, $3 $2 $year, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#^(\D*\d{1,2})\.(\d{1,2})\.(\d{4})\s*$#u", $date, $m)) {
            $date = $m[1] . ' ' . date("F", mktime(0, 0, 0, $m[2], 1, 2011)) . ' ' . $m[3];
        }

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$', 'US $'],
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
}
