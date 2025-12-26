<?php

namespace AwardWallet\Engine\getaroom\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2 extends \TAccountChecker
{
    public $mailFiles = "getaroom/it-1731160.eml, getaroom/it-2.eml, getaroom/it-2219633.eml";

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
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Reservation Details')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'Hotel Details')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'Cancellation Policy')]")->length > 0;
        }
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->froms as $efrom) {
            if (stripos($from, $efrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function parseHotel(Email $email)
    {
        $email->ota()->confirmation(
            $this->http->FindSingleNode("//td[{$this->eq(['Conf. #', 'Booking Confirmation #'])}]/ancestor::tr[1]/descendant::td[2]")
        );

        $h = $email->add()->hotel();

        $conf = $this->http->FindSingleNode("//text()[{$this->eq(['Booking Ref. Number', 'Booking Ref. #', 'Booking Reference Number'])}]/following::text()[normalize-space()][1]");

        if (!empty($conf)) {
            $h->general()
                ->confirmation($conf);
        } else {
            $h->general()->noConfirmation();
        }
        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Customer']/ancestor::tr[1]/descendant::td[2]"))
            ->status($this->http->FindSingleNode("//text()[normalize-space()='Status']/ancestor::tr[1]/descendant::td[2]"))
            ->cancellation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Cancellation Policy')]/following::text()[normalize-space()][1]"));

        $this->detectDeadLine($h);

        $h->booked()
            ->guests($this->http->FindSingleNode("//text()[normalize-space()='Adults']/ancestor::tr[1]/descendant::td[2]"))
            ->kids($this->http->FindSingleNode("//text()[normalize-space()='Children']/ancestor::tr[1]/descendant::td[4]"));

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='Hotel']/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][1]"));

        $h->hotel()
            ->address($this->http->FindSingleNode("//text()[normalize-space()='Hotel']/ancestor::tr[1]/descendant::td[2]", null, true, "/{$h->getHotelName()}\s*(.+)/"));

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Arrival']/ancestor::tr[1]/descendant::td[2]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Departure']/ancestor::tr[1]/descendant::td[2]")));

        $rooms = $this->http->FindNodes("//text()[normalize-space()='Hotel Details']/following::table[1]/descendant::text()[normalize-space()='Rooms']/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Free'))]");

        if (!empty(count($rooms) > 0)) {
            $h->booked()
                ->rooms(count($rooms));
        }

        foreach ($rooms as $roomItem) {
            $room = $h->addRoom();
            $room->setType($roomItem);
        }

        $total = $this->getTotal($this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::td[2]"));

        if (!empty($total['amount']) && !empty($total['currency'])) {
            $h->price()
                ->total($total['amount'])
                ->currency($total['currency']);

            $cost = $this->getTotal($this->http->FindSingleNode("//text()[normalize-space()='Subtotal']/ancestor::tr[1]/descendant::td[2]"));

            if (!empty($cost['amount'])) {
                $h->price()
                    ->cost($cost['amount']);
            }

            $tax = $this->getTotal($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Tax')]/ancestor::tr[1]/descendant::td[2]"));

            if (!empty($tax['amount'])) {
                $h->price()
                    ->tax($tax['amount']);
            }
        }
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

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'USD' => ['US$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        // Cancellations before 12/23/2013, 06:00 PM (America/Los Angeles) are fully refundable
        if (preg_match('/Cancellations before\s*(\d+\/\d+\/\d{4}\,\s*[\d\:]+\s*[AP]M)\s*\([^)]+\)\s*are fully refundable/us', $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($m[1]));
        }
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
}
