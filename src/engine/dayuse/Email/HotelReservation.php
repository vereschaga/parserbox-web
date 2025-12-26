<?php

namespace AwardWallet\Engine\dayuse\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "dayuse/it-858797275.eml, dayuse/it-860193194.eml, dayuse/it-883500344.eml";
    public $lang = 'en';

    public $detectSubjects = [
        'en' => [
            'Your booking is confirmed',
            'Booking cancellation',
        ],
    ];

    public $detectBody = [
        'en' => [],
    ];

    public static $dictionary = [
        "en" => [
            '★'     => ['★', '*'],
            'Total' => ['Total', 'Paid online', 'To be paid at the hotel', 'Free cancellation', 'To pay upon checkin'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]dayuse\.com$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        // detect Provider
        if (empty($headers['from']) || stripos($headers['from'], 'dayuse.com') === false) {
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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a/@href[{$this->contains('dayuse.com')}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->contains('Dayuse')}]")->length === 0
        ) {
            return false;
        }

        // detect Format
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Date:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Time slot:'))}]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->eq($this->t('Hotel:'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->eq($this->t('To pay upon checkin'))}]")->length > 0)
        ) {
            return true;
        }

        return false;
    }

    public function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        // collect reservation confirmation
        $confirmationText = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Dayuse reservation number:'))}])[1]/ancestor::tr[normalize-space()][1]");

        if (preg_match("/^\s*(?<desc>{$this->opt($this->t('Dayuse reservation number'))})[\:\s]*(?<number>\w+)\s*$/mi", $confirmationText, $m)) {
            $h->general()
                ->confirmation($m['number'], $m['desc']);
        }

        $confirmationStatus = $this->http->FindSingleNode("//text()[{$this->contains($this->t('your reservation is'))}]/ancestor::td[normalize-space()][1]", null, true, "/^.+?{$this->opt($this->t('reservation is'))}\s*(\w+).*/s")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('your booking has been'))}]/ancestor::td[normalize-space()][1]", null, true, "/^.+?{$this->opt($this->t('your booking has been'))}\s*(\w+)[!.]$/");

        if (!empty($confirmationStatus)) {
            $h->general()->status($confirmationStatus);
        }

        if (in_array($confirmationStatus, (array) $this->t('cancelled'))) {
            $h->setCancelled(true);
        }

        // collect traveller
        $traveller = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Guest:'))}])[1]/following::text()[normalize-space()][1]", null, true, "/^\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*$/");

        if (!empty($traveller)) {
            $h->general()->traveller($traveller, true);
        }

        // collect hotel name
        $hotelName = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Hotel:'))}])[1]/following::td[normalize-space()][1]", null, true, "/^\s*(.+?)[★*\s]+$/");

        if (!empty($hotelName)) {
            $h->hotel()->name($hotelName);
        }

        // collect phone
        $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('or by phone:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([+\-()\d\s]+?)\s*$/");

        // collect hotel main info
        $hotelText = implode("\n", $this->http->FindNodes("//text()[{$this->contains($this->t($phone))} and {$this->contains($this->t('★'))}][1]/ancestor::table[1]/descendant::text()[normalize-space()]"));

        // trim and save phone
        $h->hotel()->phone(preg_replace("/\s+/", '', $phone));

        // Hotel info example:
        // Hotel Amano Grand Central
        // ★ ★ ★ | * * *
        // Heidestraße 62, 10557 Berlin, Deutschland
        // 0800 724 5975

        $hotelPattern =
            "/^\s*(?<name>.+)\s*\n"
            . "[★*\s]+\n"
            . "\s*(?<address>.+)\s*\n"
            . "\s*[+(\d][-+.\s\d)(]{5,}[\d)]\s*(?:\n|$)/m";

        if (empty($hotelName) && preg_match($hotelPattern, $hotelText, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address($m['address']);
        }

        // collect check-in and check-out dates
        $timeOfClockPattern = "\d+(?:\s*\:\s*\d+)?\s*(?:[AP]M)?"; // use with 'insensitive' regex flag
        $date = strtotime($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Date:'))}])[1]/following::td[normalize-space()][1]", null, true, "/^\s*\w+\,\s*(\w+\s+\d+\,\s*\d{4})\s*$/"));
        $timeSlot = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Time slot:'))}])[1]/following::td[normalize-space()][1]");

        if (preg_match("/^\s*(?<timeStart>$timeOfClockPattern)\s*\-\s*(?<endStart>$timeOfClockPattern)\s*$/i", $timeSlot, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['timeStart'], $date))
                ->checkOut(strtotime($m['endStart'], $date));
        }

        // collect room info
        $roomType = $this->http->FindSingleNode("(//text()[normalize-space()='Time slot:'])[last()]/following::table[1]/descendant::tr[not(.//tr)][1]/td[normalize-space()][1]/descendant::text()[normalize-space()][1]");
        $roomDesc = $this->http->FindSingleNode("//text()[{$this->contains($roomType)}]/following::text()[normalize-space()][1]");

        if (!empty($roomType) && !empty($roomDesc)) {
            $r = $h->addRoom();
            $r->setType($roomType);
            $r->setDescription($roomDesc);
        }

        // collect pricing details
        $pricePattern = "(?<currency>[^\d\s]{1,3})\s*(?<amount>[\d\.\,\']+)"; // use with 'Unicode' regex flag

        $costText = $this->http->FindSingleNode("(//text()[normalize-space()='Time slot:'])[last()]/following::table[1]/descendant::tr[not(.//tr)][1]/td[normalize-space()][2]");

        if (preg_match("/^\s*{$pricePattern}\s*$/u", $costText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $h->price()
                ->currency($currency)
                ->cost(PriceHelper::parse($m['amount'], $currency));
        }

        $fees = $this->http->FindNodes("//text()[{$this->contains($this->t($roomType))}]/following::tr[count(td[normalize-space()])=2][not({$this->contains($this->t('Total'))})]");

        foreach ($fees as $fee) {
            if (preg_match("/^\s*(?<feeName>[^\:]+?)\s+{$pricePattern}\s*$/u", $fee, $m)) {
                if (empty($currency)) {
                    $currency = $this->normalizeCurrency($m['currency']);
                    $h->price()->currency($currency);
                }

                $h->price()->fee($m['feeName'], PriceHelper::parse($m['amount'], $m['currency']));
            }
        }

        // collect notes
        $notes = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Expected arrival time'))}])[1]/ancestor::tr[normalize-space()][1]");

        if (!empty($notes)) {
            $h->general()->notes($notes);
        }

        // collect cancellation policy
        $cancellationPolicy = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation terms:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (!empty($cancellationPolicy)) {
            $h->setCancellation($cancellationPolicy);
            $this->detectDeadLine($h);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHotel($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'          => 'EUR',
            'US dollars' => 'USD',
            '£'          => 'GBP',
            '₹'          => 'INR',
            'CA$'        => 'CAD',
            '$'          => '$',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3}\D)(?:$|\s)#", $s)) {
            return $code;
        }

        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return $s;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): bool
    {
        $cancellationText = $h->getCancellation();

        if (empty($cancellationText)) {
            return false;
        }

        if (stripos($cancellationText, 'Free cancellation up to last minute') !== false) {
            $h->parseDeadlineRelative('1 minute', null);

            return true;
        }

        return false;
    }
}
