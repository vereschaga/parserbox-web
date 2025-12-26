<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelNotification extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-691999982.eml, maketrip/it-692432283.eml";
    public $subjects = [
        'Notification : Hotel Booking for Req No.',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@quest2travel.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Quest2Travel')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Hotel Booking details for'))}]")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='Hotel Details']/following::text()[normalize-space()='Hotel Name']")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]quest2travel\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHotel($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Guest Name']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Guest Name'))}\s*\:\s*(.+)/");

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking Ref. No']/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Booking Ref. No'))}\s*\:\s*(\d{12,})$/"))
            ->traveller(preg_replace("/^(?:MS|MR|MRS)\./", "", $traveller));

        $bookedOn = $this->http->FindSingleNode("//text()[normalize-space()='Booked On']/ancestor::tr[1]", null, true, "/\:\s*(\d+\-\w+\-\d{4}\s*[\d\:]+\s*A?P?M)$/");

        if (preg_match("/^(\d+)\-(\w+)\-(\d{4})\s*(\d+\:\d+)\:\d+\s*(A?P?M)$/", $bookedOn, $m)) {
            $h->general()
                ->date(strtotime($m[1] . '.' . $m[2] . '.' . $m[3] . ', ' . $m[4] . $m[5]));
        }

        $city = $this->http->FindSingleNode("//text()[normalize-space()='Hotel City']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Hotel City'))}\s*\:\s*(.+)/");
        $address = $this->http->FindSingleNode("//text()[normalize-space()='Hotel Address']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Hotel Address'))}\s*\:\s*(.+)/");

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='Hotel Name']/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Hotel Name'))}\s*\:\s*(.+)/"))
            ->address($city . ', ' . $address);

        $guests = $this->http->FindSingleNode("//text()[normalize-space()='Number of Adults']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Number of Adults'))}\s*\:*\s*(\d+)/us");

        if ($guests !== null) {
            $h->setGuestCount($guests);
        }

        $kids = $this->http->FindSingleNode("//text()[normalize-space()='Number of Children']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Number of Children'))}\s*\:*\s*(\d+)/");

        if ($kids !== null) {
            $h->setKidsCount($kids);
        }

        $inDate = $this->http->FindSingleNode("//text()[normalize-space()='Check-In Date']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check-In Date'))}\s*\:\s*(\d+\/\w+\/\d{4}\s*\d+\:\d+\:\d+\s*A?P?M)$/");

        if (preg_match("/^(\d+)\/(\w+)\/(\d{4})\s*\s*(\d+\:\d+)\:\d+\s*(A?P?M)$/us", $inDate, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1] . '.' . $m[2] . '.' . $m[3] . ', ' . $m[4] . $m[5]));
        }

        $outDate = $this->http->FindSingleNode("//text()[normalize-space()='Check-Out Date']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check-Out Date'))}\s*\:\s*(\d+\/\w+\/\d{4}\s*\d+\:\d+\:\d+\s*A?P?M)$/");

        if (preg_match("/^(\d+)\/(\w+)\/(\d{4})\s*\s*(\d+\:\d+)\:\d+\s*(A?P?M)$/us", $outDate, $m)) {
            $h->booked()
                ->checkOut(strtotime($m[1] . '.' . $m[2] . '.' . $m[3] . ', ' . $m[4] . $m[5]));
        }

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Room Type']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Room Type'))}\s*\:\s*(.+)/");

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);

            $rate = array_filter($this->http->FindNodes("//text()[normalize-space()='Room Rate Details']/ancestor::tr[1]/following-sibling::tr[not(contains(normalize-space(), 'Total'))]/descendant::td[3]"));

            if (count($rate) > 0) {
                $room->setRates($rate);
            }
        }

        $numberOfRooms = $this->http->FindSingleNode("//text()[normalize-space()='Number of Rooms']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Number of Rooms'))}\s*\:\s*(.+)/");

        if (preg_match("/^(\d+)\s*Room/u", $numberOfRooms, $m)) {
            $h->booked()
                ->rooms($m[1]);
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total Price']/ancestor::tr[1]");

        if (preg_match("/Total Price\s*\:\s*([\d\,]+)/", $price, $m)) {
            $h->price()
                ->currency('INR')
                ->total(PriceHelper::parse($m[1], 'INR'));

            $fee = $this->http->FindSingleNode("//text()[normalize-space()='GST']/ancestor::tr[1]");

            if (preg_match("/GST\s*\:\s*([\d\,]+)/", $fee, $m)) {
                $h->price()
                    ->fee('GST', PriceHelper::parse($m[1], 'INR'));
            }
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Your booking is cancelled successfully.')]")->length > 0) {
            $h->general()
                ->cancelled()
                ->status('cancelled');
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'INR' => ['Rs.'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
