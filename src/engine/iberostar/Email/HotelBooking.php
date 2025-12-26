<?php

namespace AwardWallet\Engine\iberostar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use DateTime;
use PlancakeEmailParser;

class HotelBooking extends \TAccountChecker
{
    public $mailFiles = "iberostar/it-768725221.eml, iberostar/it-776795603.eml";
    public $subjects = [
        'BOOKING CONFIRMATION',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Adults' => ['Adults', 'Adult'],
            'Nights' => ['Nights', 'Night'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@iberostar.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]iberostar\.com$/', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('IBEROSTAR Hotels & Resorts'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Confirmation of Booking Number'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('No of people'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('CANCELLATION AND NO SHOW POLICY'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        // collect booking confirmation
        $bookingInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking code'))}]/ancestor::td[normalize-space()][1]");

        if (preg_match("/^\s*(?<desc>{$this->opt($this->t('Booking code'))})\:\s*(?<number>\w+)\s*$/mi", $bookingInfo, $m)) {
            $h->general()
                ->confirmation($m['number'], $m['desc']);
        }

        // collect hotel name
        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('#showPH#'))}]/ancestor::td[1]/descendant::tr[normalize-space()][1]");

        if (!empty($name)) {
            $h->setHotelName($name);
        }

        // collect address
        $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('#showPH#'))}]/ancestor::td[1]/descendant::tr[normalize-space()][2]");

        if (!empty($address)) {
            $h->setAddress($address);
        }

        // collect phone
        $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('#showPH#'))}]/ancestor::td[1]/descendant::tr[normalize-space()][3]", null, true, "/^.+?(\+?\s*\d[\d\-\s\(\)]+\d)\s*$/m");

        if (!empty($phone)) {
            $h->setPhone($phone);
        }

        // collect check-in and check-out dates
        $checkInOutDates = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Period'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        // collect check-in, check-out and nights count
        if (preg_match("/^\s*(?<checkIn>\d+\/\d+\/\d{4})\s+\-\s+(?<checkOut>\d+\/\d+\/\d{4})\s*$/mi", $checkInOutDates, $m)) {
            $checkIn = $m['checkIn'];
            $checkOut = $m['checkOut'];
        }

        $nightsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Stay'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*(\d+)\s+{$this->opt($this->t('Nights'))}.+$/mi");

        // parse and set check-in and check-out dates
        if (!empty($checkIn) && !empty($checkOut) && !empty($nightsCount)) {
            $this->setDates($checkIn, $checkOut, $nightsCount, $h);
        }

        // collect rooms count
        $roomsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rooms'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*(\d+)\s*$/m");

        if (!empty($roomsCount)) {
            $h->setRoomsCount($roomsCount);
        }

        // collect guests count
        $guestsCount = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('No of people'))}])[1]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*(\d+)\s+{$this->opt($this->t('Adults'))}.+$/m");

        if (!empty($guestsCount)) {
            $h->setGuestCount($guestsCount);
        }

        // collect total
        $totalText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total amount'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,\']+)\s*$/m", $totalText, $m)
            || preg_match("/^\s*(?<total>[\d\.\,\']+)\s*(?<currency>[A-Z]{3})\s*$/m", $totalText, $m)) {
            $h->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $m['currency']));
        }

        // collect rate type
        $rateType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        // collect room types
        $roomTypes = $this->http->FindNodes("//text()[{$this->contains($this->t('Room type'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, "/^\s*(.+?)\s*{$this->opt($this->t('Change your booking'))}?\s*$/m");

        foreach ($roomTypes as $roomType) {
            $room = $h->addRoom();

            $room->setType($roomType);
            $room->setRateType($rateType);
        }

        // collect travellers
        $travellerNodes = $this->http->FindNodes("//text()[{$this->eq($this->t('Name'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");
        $travellers = [];

        foreach ($travellerNodes as $travellerNode) {
            if (preg_match_all("/\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s+\(Adult\)/", $travellerNode, $m)) {
                $travellers = array_merge($travellers, $m[1]);
            }
        }

        $travellers = array_unique(array_filter($travellers));

        if (!empty($travellers)) {
            $h->setTravellers($travellers, true);
        }

        // collect cancellation policy
        $cancellationPolicy = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CANCELLATION AND NO SHOW POLICY'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]");

        if (stripos($cancellationPolicy, 'Cancellation policy:') !== false) {
            $cancellationPolicy = $this->re("/^.+?{$this->opt($this->t('Cancellation policy'))}\:\s*(.+?)\s*$/i", $cancellationPolicy);
        }

        if (!empty($cancellationPolicy)) {
            $h->setCancellation($cancellationPolicy);
            $this->detectDeadLine($h);
        }

        // collect provider phone
        $providerPhone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('IBEROSTAR HOTELES Y APARTAMENTOS'))}]/ancestor::td[normalize-space()][1]", null, true, "/^.+?(\+?\s*\d[\d\-\s\(\)]+\d)\s*$/m");

        if (!empty($providerPhone)) {
            $h->addProviderPhone($providerPhone);
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

    private function setDates($checkInStr, $checkOutStr, $nightsCount, \AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        $checkInOutDates = [];

        // parse and set check-in and check-out dates
        // check USA (m/d/Y) or other (d/m/Y) date notation
        $checkInOutDates[] = [DateTime::createFromFormat('m/d/Y H:i:s', $checkInStr . ' 00:00:00'),
            DateTime::createFromFormat('m/d/Y H:i:s', $checkOutStr . ' 00:00:00'), ];
        $checkInOutDates[] = [DateTime::createFromFormat('d/m/Y H:i:s', $checkInStr . ' 00:00:00'),
            DateTime::createFromFormat('d/m/Y H:i:s', $checkOutStr . ' 00:00:00'), ];

        foreach ($checkInOutDates as [$checkInDate, $checkOutDate]) {
            if ($checkInDate && $checkOutDate) {
                $dayDiff = $checkInDate->diff($checkOutDate)->format('%a');

                if ($dayDiff == $nightsCount) {
                    $h->setCheckInDate($checkInDate->getTimestamp());
                    $h->setCheckOutDate($checkOutDate->getTimestamp());

                    return true;
                }
            }
        }

        return false;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): bool
    {
        $cancellationText = $h->getCancellation();

        if (empty($cancellationText)) {
            return false;
        }

        if (preg_match("#{$this->opt('If notice of cancellation is received more than')}\s*(?<day>\d+)\s*{$this->opt('days before arrival/check-in date, 0 nights’ accommodation will be charged.')}#i", $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['day'] . ' day');
            $h->setNonRefundable(false);

            return true;
        }

        if ($this->re("#(The rate you are about to book is not eligible for changes or cancellations\.)#i", $cancellationText)) {
            $h->setNonRefundable(true);

            return true;
        }

        if ($this->re("#(This rate does not allow for cancellations\.)#i", $cancellationText)) {
            $h->setNonRefundable(true);

            return true;
        }

        return false;
    }
}
