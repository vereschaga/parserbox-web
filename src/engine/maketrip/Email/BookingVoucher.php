<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar formats: goibibo/HotelBookingVoucher, maketrip/HotelBooking

class BookingVoucher extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-153613355.eml";
    public $subjects = [
        'Your Booking Confirmation Voucher for',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'starts-cancellation' => ['Free Cancellation (100% refund)', 'This booking is non-refundable'],
            'Amount'              => ['Amount', 'Paid Amount'],
            'Modify Guests'       => ['Modify Guests', 'Add Guests'],
            'Children'            => ['Children', 'Child'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@makemytrip.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Team MakeMyTrip')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Voucher'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Important information'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Change Dates'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]makemytrip\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking ID:']/following::text()[normalize-space()][1]", null, true, "/^([\dA-Z]{15,})$/u"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Modify Guests'))}]/preceding::text()[contains(normalize-space(), 'Primary Guest')][1]/ancestor::td[1]", null, true, "/^(.+)\s*\({$this->opt($this->t('Primary Guest'))}/u"))
            ->date(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Booking ID:']/ancestor::tr[1]/following::text()[normalize-space()][1]", null, true, "/{$this->opt($this->t('Booked on'))}\s*(.+)\)/u")));

        $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Important information']/following::text()[{$this->starts($this->t('starts-cancellation'))}][1]");

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='DETAILS & Inclusions']/following::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[normalize-space()='DETAILS & Inclusions']/following::text()[normalize-space()][2]"))
            ->phone($this->http->FindSingleNode("//text()[normalize-space()='DETAILS & Inclusions']/following::text()[normalize-space()][3]", null, true, "/^\,?\s*([\d\-\s\+]+)\:*/u"));

        $checkIn = $this->http->FindSingleNode("//text()[normalize-space()='Change Dates']/preceding::text()[normalize-space()='Check-in']/ancestor::td[1]");

        if (preg_match("/{$this->opt($this->t('Check-in'))}\s*(.+){$this->opt($this->t('After'))}\s*([\d\:]+\s*A?P?M)/", $checkIn, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1] . ', ' . $m[2]));
        }

        $checkOut = $this->http->FindSingleNode("//text()[normalize-space()='Change Dates']/preceding::text()[normalize-space()='Check-out']/ancestor::td[1]");

        if (preg_match("/{$this->opt($this->t('Check-out'))}\s*(.+){$this->opt($this->t('Before'))}\s*([\d\:]+\s*A?P?M)/", $checkOut, $m)) {
            $h->booked()
                ->checkOut(strtotime($m[1] . ', ' . $m[2]));
        }

        $adults = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Modify Guests'))}]/preceding::text()[contains(normalize-space(), 'Adults')][1]/ancestor::td[1]", null, true, "/\s*(\d+)\s*{$this->opt($this->t('Adults'))}/u");

        if (!empty($adults)) {
            $h->booked()
                ->guests($adults);
        }

        $kids = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Modify Guests'))}]/preceding::text()[contains(normalize-space(), 'Adults')][1]/ancestor::td[1]", null, true, "/\s*(\d+)\s*{$this->opt($this->t('Children'))}/");

        if (!empty($kids)) {
            $h->booked()
                ->kids($kids);
        }

        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[{$this->starts($this->t('Modify Guests'))}]/following::text()[contains(normalize-space(), 'Room')][1]/ancestor::td[1]", null, true, "/\s*(\d+)\s*{$this->opt($this->t('Room'))}/"));

        $roomType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Modify Guests'))}]/following::text()[contains(normalize-space(), 'Room')][1]/following::td[1]/descendant::text()[normalize-space()][1]");
        $roomDesc = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Modify Guests'))}]/following::text()[contains(normalize-space(), 'Room')][1]/following::td[1]/descendant::text()[normalize-space()='Read more'][1]/preceding::text()[normalize-space()][1]");

        if (!empty($roomType) || !empty($roomDesc)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($roomDesc)) {
                $room->setDescription($roomDesc);
            }
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Amount'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^([A-Z]{3})\s*([\d\.\,]+)$/u", $price, $m)) {
            $h->price()
                ->currency($m[1])
                ->total(PriceHelper::parse($m[2], $m[1]));
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $otaConf = $this->http->FindSingleNode("//text()[normalize-space()='PNR:']/following::text()[normalize-space()][1]", null, true, "/^([\d\_]{7,})$/");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        $this->ParseHotel($email);

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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/Free Cancellation \(100[%] refund\) till (\w+\,\s*\d+\s*\w+\s*\d{4}\,\s*[\d\:]+\s*A?P?M)/ui',
            $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($m[1]));
        }

        if (preg_match('/This booking is non\-refundable/ui', $cancellationText, $m)) {
            $h->booked()->nonRefundable();
        }
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }
}
