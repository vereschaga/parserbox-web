<?php

namespace AwardWallet\Engine\getaroom\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "getaroom/it-302018772.eml, getaroom/it-302078512.eml";

    public $subjects = [
        "Reservation Confirmation #",
        "Reservation Confirmation#",
    ];

    public $lang = 'en';

    public $froms = [
        "@hotelvalues.com",
        "@getaroom.com",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->froms as $from) {
            if (isset($headers['from']) && stripos($headers['from'], $from) !== false) {
                foreach ($this->subjects as $subject) {
                    if (stripos($headers['subject'], $subject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src, 'getaroom')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains(['Travel Details', 'TRAVEL DETAILS'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains(['Room & Guest Details', 'ROOM & GUEST DETAILS'])}]")->length > 0;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'getaroom.com') !== false;
    }

    public function parseHotel(Email $email)
    {
        // Travel Agency
        $email->ota()->confirmation(
            $this->http->FindSingleNode("//text()[{$this->eq(['Booking Confirmation #'])}]/following::text()[normalize-space()][1]")
        );

        // Hotel
        $h = $email->add()->hotel();

        // General
        $conf = $this->http->FindSingleNode("(//text()[{$this->eq(['Booking Ref. #'])}])[1]/following::text()[normalize-space()][1]");

        if (!empty($conf)) {
            $h->general()
                ->confirmation($conf);
        } else {
            $h->general()->noConfirmation();
        }
        $h->general()
            ->travellers($this->http->FindNodes("//text()[{$this->eq(['Room & Guest Details', 'ROOM & GUEST DETAILS'])}]/following::text()[{$this->starts(['Guests'])}][1]/ancestor::td[1]//text()[normalize-space()][not({$this->starts(['Guests'])})]"), true)
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq(['Cancellation Policy', 'CANCELLATION POLICY'])}]/following::tr[not(.//tr)][normalize-space()][1]"));

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq(['Travel Details', 'TRAVEL DETAILS'])}]/following::tr[not(.//tr)][normalize-space()][1][following::tr[not(.//tr)][normalize-space()][2][{$this->starts(['Check-in:'])}]]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq(['Travel Details', 'TRAVEL DETAILS'])}]/following::tr[not(.//tr)][normalize-space()][2][following::tr[not(.//tr)][normalize-space()][1][{$this->starts(['Check-in:'])}]]"))
        ;

        $date = strtotime($this->http->FindSingleNode("//tr[{$this->starts(['Check-in:'])}]", null, true, '/:(.+)/'));
        $time = $this->http->FindSingleNode("//tr[{$this->starts(['Check-in Time:'])}]", null, true, '/:(.+)/');

        if (!empty($date) && !empty($time)) {
            $h->booked()
                ->checkIn(strtotime($time, $date));
        }
        $date = strtotime($this->http->FindSingleNode("//tr[{$this->starts(['Check-out:'])}]", null, true, '/:(.+)/'));
        $time = $this->http->FindSingleNode("//tr[{$this->starts(['Check-out Time:'])}]", null, true, '/:(.+)/');

        if (!empty($date) && !empty($time)) {
            $h->booked()
                ->checkOut(strtotime($time, $date));
        }

        $h->booked()
            ->guests($this->http->FindSingleNode("//tr[{$this->starts(['Check-in:'])}]/following::text()[normalize-space()][position() < 10][{$this->contains(['Adult'])}]",
                null, true, "/^\s*(\d+)\s*Adult/i"))
            ->kids($this->http->FindSingleNode("//tr[{$this->starts(['Check-in:'])}]/following::text()[normalize-space()][position() < 10][{$this->contains(['Child'])}]",
                null, true, "/^\s*(\d+)\s*Child/i"))
            ->rooms($this->http->FindSingleNode("//text()[{$this->eq(['Room & Guest Details', 'ROOM & GUEST DETAILS'])}]/following::text()[{$this->starts(['Rooms'])}][1]",
                null, true, "/Rooms\s*\(\s*(\d+)\s*\)/"))
        ;

        $types = $this->http->FindNodes("//text()[{$this->eq(['Room & Guest Details'])}]/following::text()[{$this->starts(['Rooms'])}][1]/ancestor::td[1]/descendant::text()[normalize-space()][position()>1]");

        if (count($types) === $h->getRoomsCount()) {
            foreach ($types as $type) {
                $h->addRoom()
                    ->setType($type);
            }
        } elseif (count($types) === 1) {
            for ($i = 1; $i <= $h->getRoomsCount(); $i++) {
                $h->addRoom()
                    ->setType($types[0]);
            }
        }

        $total = $this->getTotal($this->http->FindSingleNode("//text()[{$this->eq(['Amount Paid'])}]/ancestor::td[1]",
            null, true, "/{$this->opt('Amount Paid')}\s*(.+)/"));

        if (!empty($total['amount']) && !empty($total['currency'])) {
            $h->price()
                ->total($total['amount'])
                ->currency($total['currency']);

            $cost = $this->getTotal($this->http->FindSingleNode("//td[{$this->eq(['Subtotal'])}]/following-sibling::td[normalize-space()][1]"));

            if (!empty($cost['amount'])) {
                $h->price()
                    ->cost($cost['amount']);
            }

            $tax = $this->getTotal($this->http->FindSingleNode("//td[{$this->starts(['Tax Recovery Charges & Service Fees'])}]/following-sibling::td[normalize-space()][1]"));

            if (!empty($tax['amount'])) {
                $h->price()
                    ->tax($tax['amount']);
            }
        }
        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (stripos($parser->getSubject(), 'StudentUniverse') !== false) {
            $email->setProviderCode('stuniverse');
        } elseif (stripos($parser->getSubject(), 'getaroom') !== false) {
            $email->setProviderCode('getaroom');
        } elseif (stripos($parser->getSubject(), 'Guest Reservations') !== false) {
            $email->setProviderCode('guestres');
        }

        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['stuniverse', 'getaroom', 'guestres'];
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/Cancellations before\s*(\d+\/\d+\/\d{4}\,\s*[\d\:]+\s*[AP]M)\s*\([^)]+\)\s*are fully refundable/us', $cancellationText, $m)) {
            // Cancellations before 12/23/2013, 06:00 PM (America/Los Angeles) are fully refundable
            $h->booked()->deadline(strtotime($m[1]));
        } elseif (preg_match('/This reservation is non-refundable\./us', $cancellationText, $m)) {
            $h->booked()->nonRefundable();
        }
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'US$' => 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
