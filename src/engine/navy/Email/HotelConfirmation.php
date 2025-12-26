<?php

namespace AwardWallet\Engine\navy\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelConfirmation extends \TAccountChecker
{
    public $mailFiles = "navy/it-701418209.eml, navy/it-701539221.eml";
    public $subjects = [
        'Navy Federal Rewards Hotel Reservation Summary',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your reservation number is' => ['Your reservation number is', 'Your NAVY reservation number is'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@rewards.navyfederal.org') !== false) {
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
        return preg_match('/[@.]navyfederal\.org$/', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Navy Federal Rewards'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Check-in'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Room'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Tax recovery charges'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        // collect reservation confirmation
        $confirmationInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your reservation number is'))}]/ancestor::td[normalize-space()][1]");

        if (preg_match("/^\s*.+?(?<status>{$this->opt($this->t('Booked'))})\s*\!\s*(?<desc>{$this->opt($this->t('Your reservation number is'))})\:\s*(?<number>\w+)\s*$/mi", $confirmationInfo, $m)) {
            $h->general()
                ->confirmation($m['number'], $m['desc'], true)
                ->status($m['status']);
        }

        // collect traveller
        $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for using'))}]/ancestor::td[1]/div[normalize-space()][1]", null, true, "/^\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\,\s*$/m");

        if (!empty($traveller)) {
            $h->addTraveller($traveller, true);
        }

        // collect hotel name
        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/preceding::text()[normalize-space()][2]");

        if (!empty($name)) {
            $h->setHotelName($name);
        }

        // collect address
        $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/preceding::text()[normalize-space()][1]");

        if (!empty($address)) {
            $h->setAddress($address);
        }

        // collect check-in and check-out dates
        $checkInDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/ancestor::td[normalize-space()][1]", null, true, "/^\s*{$this->opt($this->t('Check-in'))}\s+(.+?)\s*$/mi");

        if (!empty($checkInDate)) {
            $h->setCheckInDate($this->normalizeDate($checkInDate));
        }

        $checkOutDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/ancestor::td[normalize-space()][1]", null, true, "/^\s*{$this->opt($this->t('Check-out'))}\s+(.+?)\s*$/mi");

        if (!empty($checkOutDate)) {
            $h->setCheckOutDate($this->normalizeDate($checkOutDate));
        }

        $roomNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('STATUS'))}]/ancestor::td[normalize-space()][2]");

        $roomsCount = null;
        $guestCount = null;
        $currency = null;
        $cost = null;

        foreach ($roomNodes as $roomNode) {
            $room = $h->addRoom();

            // collect room info
            $roomInfo = $this->http->FindSingleNode("./descendant::div[normalize-space()][1]", $roomNode);

            if (preg_match("/^\s*(?:{$this->opt($this->t('Room'))}\,\s+\d+\s+)?(?<type>[\w\s]+?)\s+\((?<count>\d+)\s+{$this->opt($this->t('Room'))}.+$/m", $roomInfo, $m)) {
                $roomsCount += intval($m['count']);
                $room->setType($m['type']);
            }

            // get table with titles and values
            $columnTitles = $this->http->FindNodes("./descendant::tr[normalize-space()][last()-1]/td[normalize-space()]", $roomNode);
            $cellValues = $this->http->FindNodes("./descendant::tr[normalize-space()][last()]/td[normalize-space()]", $roomNode);

            if (empty($columnTitles) || empty($cellValues)) {
                continue;
            }

            // collect room confirmations
            $desk = $this->http->FindSingleNode("./descendant::tr[normalize-space()][last()-1]/td[{$this->eq($this->t('CONFIRMATION NUMBER'))}]", $roomNode);
            $cellNumber = array_search($desk, $columnTitles);
            $number = $this->re("/^\s*(\d+)\s*$/m", $cellValues[$cellNumber]);

            if (!empty($desk) && !empty($number)) {
                $h->general()
                    ->confirmation($number, $desk);
                $room->setConfirmation($number);
                $room->setConfirmationDescription($desk);
            }

            // collect guest count
            $roomTitle = $this->http->FindSingleNode("./descendant::tr[normalize-space()][last()-1]/td[{$this->eq($this->t('ROOM'))}]", $roomNode);
            $cellNumber = array_search($roomTitle, $columnTitles);
            $roomGuestsCount = $this->re("/^.+?(\d+)\s+{$this->opt($this->t('Adults'))}\s*$/m", $cellValues[$cellNumber]);

            if ($roomGuestsCount !== null) {
                $guestCount += intval($roomGuestsCount);
            }

            // collect cost
            $priceTitle = $this->http->FindSingleNode("./descendant::tr[normalize-space()][last()-1]/td[{$this->eq($this->t('PRICE'))}]", $roomNode);
            $cellNumber = array_search($priceTitle, $columnTitles);
            $priceInfo = $cellValues[$cellNumber];

            if (preg_match("/^\s*(?<currency>\D)\s*(?<cost>[\d\.\,\']+)\s*$/m", $priceInfo, $m)) {
                $currency = $this->normalizeCurrency($m['currency']);
                $h->price()
                    ->currency($currency);
                $cost += PriceHelper::parse($m['cost'], $currency);
            }
        }

        if ($roomsCount !== null) {
            $h->setRoomsCount($roomsCount);
        }

        if ($guestCount !== null) {
            $h->setGuestCount($guestCount);
        }

        if (!empty($currency) && $cost !== null) {
            $h->price()
                ->cost($cost);
        }

        // collect tax
        $tax = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Tax recovery charges and service fees'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*\D\s*([\d\.\,\']+)\s*$/m");

        if (!empty($currency) && $tax !== null) {
            $h->price()
                ->tax(PriceHelper::parse($tax, $currency));
        }

        // collect spent awards
        $spentAwards = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Points to be applied to this purchase'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*([\d\.\,\']+\s+{$this->opt($this->t('Points'))})\s*$/mi");

        if (!empty($spentAwards)) {
            $h->price()
                ->spentAwards($spentAwards);
        }

        // collect total
        $total = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your cost after rewards are applied'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*\D\s*([\d\.\,\']+)\s*$/m")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total reservation cost before rewards applied'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*\D\s*([\d\.\,\']+)\s*$/m");

        if (!empty($currency) && $total !== null) {
            $h->price()
                ->total(PriceHelper::parse($total, $currency));
        }

        // collect provider phone
        $phoneInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('If you have questions or need assistance with planning your trip'))}]/ancestor::td[1]");

        if (preg_match("/^.+?(?<desc>{$this->opt($this->t('If you have questions or need assistance with planning your trip'))}).+?(?<phone>\d[\d\-]+\d)\s*\.\s*$/m", $phoneInfo, $m)) {
            $h->addProviderPhone($m['phone'], $m['desc']);
        }

        // collect cancellation policy
        $cancellationPolicy = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy'))}]/ancestor::div[1]/following-sibling::div[normalize-space()][1]");

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

    private function normalizeDate($str)
    {
        $in = [
            "#^(\w+)\s+(\d+)\,\s+(\d{4})(?:\s*\,)?\s+(\d+(?:\s*\:\s*\d+)?\s*\w{2})$#u", // Aug 02, 2024 06:00 PM | Jan 19, 2025 , 11:00 AM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+(\D+)\s+\d{4}\,\s+\d+(?:\:\d+)?\s*\w{2}$#u", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
            '$'         => '$',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
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

        if (preg_match("/^.+?{$this->opt($this->t('Cancellations or changes made after'))}\s*(?<deadline>\d{4}\-\d+\-\d+\s+\d+\:\d+)\:.+$/m", $cancellationText, $m)) {
            $h->setDeadline($this->normalizeDate($m['deadline']));
            $h->setNonRefundable(false);

            return true;
        }

        if ($this->re("/({$this->opt($this->t('This rate is non-refundable'))})/", $cancellationText)) {
            $h->setNonRefundable(true);

            return true;
        }

        return false;
    }
}
