<?php

namespace AwardWallet\Engine\iberostar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use DateTime;
use PlancakeEmailParser;

class HotelConfirmation extends \TAccountChecker
{
    public $mailFiles = "iberostar/it-772548957.eml, iberostar/it-777197644.eml, iberostar/it-779711441.eml, iberostar/it-790754676.eml";
    public $subjects = [
        'BOOKING CONFIRMATION',
        // de
        'BESTÄTIGUNG DER RESERVIERUNG',
    ];

    public $lang = '';

    public $reBody = [
        'en' => ['Name and surname', 'Booking number'],
        'de' => ['Name und Nachname', 'Reservierungsnummer'],
    ];

    public static $dictionary = [
        'en' => [
            'Adults'   => ['Adults', 'Adult'],
            'Children' => ['Children', 'Baby', 'Child'],
        ],
        'de' => [
            'Name and surname'      => ['Name und Nachname'],
            'Booking number'        => ['Reservierungsnummer'],
            'BOOKING INFORMATION'   => ['BUCHUNGSDATEN'],
            'Go to Online Check-in' => ['Gehen Sie zum Online-Check-in'],
            'your booking has been' => ['Ihre Reservierung wurde erfolgreich'],
            'Address'               => ['Adresse'],
            'How to go there'       => ['Anfahrt'],
            'Contact information'   => ['Kontaktdaten'],
            'Arrival'               => ['Check-in'],
            'Check out'             => ['Abreise'],
            'Nights'                => ['Übernachtungen'],
            'Room'                  => ['Zimmer'],
            'Rooms'                 => ['Zimmer'],
            'People'                => ['Personen'],
            'Adults'                => ['Erwachsene'],
            'Children'              => ['Kind'],
            'Rate'                  => ['Tarif'],
            'Amount'                => ['Betrag'],
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
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('IBEROSTAR HOTELS & RESORT'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Name and surname'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('BOOKING INFORMATION'))}]")->length > 0
            && $this->http->XPath->query("//a[{$this->eq($this->t('Go to Online Check-in'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        // collect reservation confirmation
        $bookingDesc = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking number'))}]");
        $bookingNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking number'))}]/ancestor::tr[normalize-space()][2]/following-sibling::tr[normalize-space()][1]/descendant::td[not(table)][2]", null, true, "/^\s*(\w+)\s*$/mu");

        if (!empty($bookingDesc) && !empty($bookingNumber)) {
            $h->general()
                ->confirmation($bookingNumber, $bookingDesc);
        }

        $bookingStatus = $this->http->FindSingleNode("//text()[{$this->contains($this->t('your booking has been'))}]", null, true, "/^.+?{$this->opt($this->t('your booking has been'))}\s+(\w+)[!.]\s*$/mu");

        if (!empty($bookingStatus)) {
            $h->setStatus(ucfirst($bookingStatus));
        }

        // collect traveller
        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Name and surname'))}]/ancestor::tr[normalize-space()][2]/following-sibling::tr[normalize-space()][1]/descendant::td[not(table)][1]", null, true, "/^\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*$/mu");

        if (!empty($traveller)) {
            $h->addTraveller($traveller, true);
        }

        // collect hotel name
        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/ancestor::tr[2]/preceding-sibling::tr[normalize-space()][1]/descendant::td[not(table)][normalize-space()][1]");

        if (!empty($name)) {
            $h->setHotelName($name);
        }

        // collect address
        $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/ancestor::table[normalize-space()][1]", null, true, "/^\s*{$this->opt($this->t('Address'))}\s+(.+?)\s+{$this->opt($this->t('How to go there'))}\s*$/miu");

        if (!empty($address)) {
            $h->setAddress($address);
        }

        // collect phone
        $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Contact information'))}]/ancestor::tr[normalize-space()][1]/following-sibling::tr[normalize-space()][1]", null, true, "/^\s*(\+?\s*\d[\d\-\s\(\)]+\d)\s*$/m");

        if (!empty($phone)) {
            $h->setPhone($phone);
        }

        // collect fax
        $fax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Contact information'))}]/ancestor::tr[normalize-space()][1]/following-sibling::tr[normalize-space()][2]", null, true, "/^\s*{$this->opt($this->t('Fax'))}\:\s*(\+?\s*\d[\d\-\s\(\)]+\d)\s*$/miu");

        if (!empty($fax)) {
            $h->setFax($fax);
        }

        // collect check-in and check-out dates, nights count
        $checkIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]", null, true, "/^\s*(\d+\/\d+\/\d{4})\s*$/m");
        $checkOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check out'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]", null, true, "/^\s*(\d+\/\d+\/\d{4})\s*$/m");
        $nightsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Nights'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]", null, true, "/^\s*(\d+)\s*$/m");

        // parse and set check-in and check-out dates
        if (!empty($checkIn) && !empty($checkOut) && !empty($nightsCount)) {
            $this->setDates($checkIn, $checkOut, $nightsCount, $h);
        }

        // collect rooms count
        $roomsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rooms'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]", null, true, "/^\s*(\d+)\s*$/m");

        if (!empty($roomsCount)) {
            $h->setRoomsCount($roomsCount);
        }

        // collect guests and kids count
        $guestsText = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('People'))}])[1]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]");

        if (preg_match("/^\s*(?<guestsCount>\d+)\s*{$this->opt($this->t('Adults'))}\s*(?:(?<kidsCount>\d+)\s*{$this->opt($this->t('Children'))}.+)?.*$/miu", $guestsText, $m)) {
            $h->setGuestCount($m['guestsCount']);

            if (isset($m['kidsCount'])) {
                $h->setKidsCount($m['kidsCount']);
            }
        }

        // collect rate type
        $rateType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]");

        if (preg_match("/^Non refundable$/i", $rateType)) {
            $h->setNonRefundable(true);
            $rateType = null;
        }

        // collect rooms info
        $roomNodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Room'))}]/ancestor::tr[2][not({$this->contains($this->t('Arrival'))})]");

        foreach ($roomNodes as $roomNode) {
            $room = $h->addRoom();

            // collect room type
            $roomType = $this->http->FindSingleNode("./descendant::tr[{$this->contains($this->t('Room'))}]/following-sibling::tr[normalize-space()][1]", $roomNode);

            if ($this->re("/^(\d+)$/m", $roomType)) {
                $desc = $this->http->FindSingleNode("./descendant::table[{$this->contains($this->t('Room'))}][1]", $roomNode);
                $roomType = $this->http->FindSingleNode("./descendant::table[{$this->contains($this->t('Room'))}]/following-sibling::table[normalize-space()][1]", $roomNode);
            }

            if (!empty($desc)) {
                $room->setDescription($desc);
            }

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($rateType)) {
                $room->setRateType($rateType);
            }
        }

        // collect notes
        $notes = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Before travelling to'))}]/ancestor::td[normalize-space()][1]");

        if (!empty($notes)) {
            $h->setNotes($notes);
        }

        // collect total
        $totalText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Amount'))}][1]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]");

        if (preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,\']+)\s*$/m", $totalText, $m)
            || preg_match("/^\s*(?<total>[\d\.\,\']+)\s*(?<currency>[A-Z]{3})\s*$/m", $totalText, $m)) {
            $h->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $m['currency']));
        }

        // collect provider phone
        $providerPhone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('IBEROSTAR HOTELS & RESORT'))}]/ancestor::td[normalize-space()][1]", null, true, "/^.+?(\+?\s*\d[\d\-\s\(\)]+\d)\s*$/m");

        if (!empty($providerPhone)) {
            $h->addProviderPhone($providerPhone);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
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

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $words) {
            if ($this->http->XPath->query("//*[{$this->contains($words[0])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($words[1])}]")->length > 0) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
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
}
