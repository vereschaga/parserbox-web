<?php

namespace AwardWallet\Engine\breakers\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelConfirmation extends \TAccountChecker
{
    public $mailFiles = "breakers/it-762996751.eml, breakers/it-762996970.eml, breakers/it-786102750.eml, breakers/it-786958099.eml, breakers/it-786968682.eml";
    public $subjects = [
        'Welcome to The Breakers: Your Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@thebreakers.com') !== false) {
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
        return preg_match('/[@.]thebreakers\.com$/', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('THEBREAKERS.COM'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We look forward to welcoming you to'))}]")->length > 0
            && $this->http->XPath->query("//a[{$this->eq($this->t('reservation policies'))}]")->length > 0
            && $this->http->XPath->query("//a[{$this->eq($this->t('read more'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        // collect reservation confirmation
        $confirmations = [];
        $confirmationInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('My Breakers Confirmation'))}]/ancestor::div[normalize-space()][1]");

        if (preg_match("/^.*?(?<desc>{$this->opt($this->t('My Breakers Confirmation'))})\:\s*(?<number>[\d\-]+).*$/i", $confirmationInfo, $m)) {
            $confirmations[] = ['number' => $m['number'], 'desc' => $m['desc']];
        }

        $confirmationInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Online Reservation'))}]/ancestor::div[normalize-space()][1]");

        if (preg_match("/^.*?(?<desc>{$this->opt($this->t('Online Reservation'))})\:\s*(?<number>\w+).*$/i", $confirmationInfo, $m)) {
            $confirmations[] = ['number' => $m['number'], 'desc' => $m['desc']];
        }

        $confirmationInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Online Confirmation'))}]/ancestor::div[normalize-space()][1]");

        if (preg_match("/^.*?(?<desc>{$this->opt($this->t('Online Confirmation'))})\:\s*(?<number>\w+)\s*{$this->opt($this->t('Additional Room Occupants'))}.*$/i", $confirmationInfo, $m)) {
            $confirmations[] = ['number' => $m['number'], 'desc' => $m['desc']];
        }

        foreach ($confirmations as $number => $confirmation) {
            if (count($confirmations) > 1 && $number === 0) {
                $h->addConfirmationNumber($confirmation['number'], $confirmation['desc'], true);

                continue;
            }

            $h->addConfirmationNumber($confirmation['number'], $confirmation['desc']);
        }

        // collect traveller (second xpath use if email is forwarded)
        $travellerText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Welcome'))}]/ancestor::td[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('Welcome'))}]/ancestor::div[normalize-space()][1]");

        $traveller = $this->re("/^\s*{$this->opt($this->t('Welcome'))}\s+(?<traveller>[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\,.*$/", $travellerText);

        if (!empty($traveller)) {
            $h->addTraveller($traveller, true);
        }

        // arrival and departure days (second xpath use if email is forwarded)
        $datesInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Arrival Date'))}]/ancestor::td[normalize-space()][1]", null, true, "/^(.+?)\s*{$this->opt($this->t('My Breakers Confirmation'))}.*$/")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('Arrival Date'))}]/ancestor::p[normalize-space()][1]", null, true, "/^(.+?)\s*{$this->opt($this->t('Online Confirmation'))}.*$/");

        if (preg_match("/^.*?{$this->opt($this->t('Arrival Date'))}\:\s*(?<arrDay>.+?)\s*{$this->opt($this->t('Departure Date'))}\:\s*(?<depDay>.+?)$/", $datesInfo, $m)) {
            $arrDay = $m['arrDay'];
            $depDay = $m['depDay'];
        }

        // collect arrival and departure times (second and third xpath use if email is forwarded)
        $timesInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-In'))}]/ancestor::td[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-In'))}]/ancestor::p[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-In'))}]/ancestor::div[normalize-space()][2]");

        if (preg_match("/^.+?{$this->opt($this->t('Check-In'))}\:\s*(?<checkIn>\d+\s*(?:am|pm))\s*({$this->opt($this->t('Check-Out'))})\:\s*(?<checkOut>\d+\s*(?:am|pm)).+$/i", $timesInfo, $m)) {
            $checkIn = $m['checkIn'];
            $checkOut = $m['checkOut'];
        }

        if (!empty($checkIn) && !empty($checkOut)) {
            $h->booked()
                ->checkIn($this->normalizeDate($arrDay . ', ' . $checkIn))
                ->checkOut($this->normalizeDate($depDay . ', ' . $checkOut));
        }

        // collect hotel name
        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('We look forward to welcoming you to'))}]/ancestor::div[normalize-space()][1]", null, true, "/^\s*{$this->opt($this->t('We look forward to welcoming you to'))}(.+?)[\!\.]\s*$/mi");

        if (!empty($name)) {
            $h->setHotelName($name);
        }

        // collect address and phone
        $contactsInfo = $this->http->FindNodes("//text()[{$this->eq($this->t('read more'))}]/following::tr[1]/descendant::text()[normalize-space()]");

        // collect address and phone if email is forwarded
        if (empty($contactsInfo)) {
            $contactsInfo = $this->http->FindNodes("//text()[{$this->eq($this->t('read more'))}]/ancestor::div[1]/following-sibling::div[normalize-space()]");
        }

        if (!empty($contactsInfo)) {
            $contactsInfo = implode("\n", $contactsInfo);
        }

        if (preg_match("/^(.+\n)?(?<address>.+?)\n(?<phone>[\d\s\-\+\(\)]+?)\s*$/s", $contactsInfo, $m)) {
            $h->setAddress($m['address']);
            $h->setPhone($m['phone']);
        }

        // collect nodes with room info
        $roomNodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Room Type'))}]/ancestor::table[normalize-space()][1]");

        $reg = "/^\s*{$this->opt($this->t('Room Type'))}\s*\:\s*(?<type>.+?)\s*"
            . "{$this->opt($this->t('Bedding Type'))}\s*\:\s*(?<desc>.+?)\s*"
            . "{$this->opt($this->t('Number of Adults'))}\s*\:\s*(?<guestsCount>\d+)\s*"
            . "({$this->opt($this->t('Number of Children'))}\s*\:\s*(?<kidsCount>\d+)\s*)?"
            . ".*$/si";

        $guestsCount = null;
        $kidsCount = null;
        $roomsCount = null;

        foreach ($roomNodes as $roomNode) {
            $roomInfo = $this->http->FindSingleNode(".", $roomNode);

            if (preg_match($reg, $roomInfo, $m)) {
                $room = $h->addRoom();
                $roomsCount++;

                $room->setType($m['type']);
                $room->setDescription($m['desc']);
                $guestsCount += intval($m['guestsCount']);

                if (isset($m['kidsCount'])) {
                    $kidsCount += intval($m['kidsCount']);
                }
            }

            // collect rates
            $rateNodes = $this->http->XPath->query("./descendant::tr[{$this->contains($this->t('Night'))}]", $roomNode);

            if ($rateNodes->length === 0) {
                $rateNodes = $this->http->XPath->query("./ancestor::tr[1]/following-sibling::tr[{$this->contains($this->t('Night'))}]", $roomNode);
            }

            foreach ($rateNodes as $rateNode) {
                $nightsCount = $this->http->FindSingleNode("./td[normalize-space()][4]", $rateNode, true, "/\d+/");
                $cost = $this->http->FindSingleNode("./td[normalize-space()][5]", $rateNode, true, "/\s*\:\s*\D\s*([\d\.\,\']+\s*[[:alpha:]]{3})\s*$/mi");

                if (!empty($nightsCount)) {
                    foreach (range(1, $nightsCount) as $_) {
                        $room->addRate(preg_replace("/\s+/", '', $cost . '/night'));
                    }
                }
            }
        }

        // collect nodes with room info if email is forwarded
        if (empty($h->getRooms())) {
            $roomNodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Room Type'))}]/ancestor::p[normalize-space()][1]");

            foreach ($roomNodes as $roomNode) {
                $roomInfo = $this->http->FindSingleNode(".", $roomNode);

                if (preg_match("/^\s*{$this->opt($this->t('Room Type'))}\s*\:\s*(?<type>.+?)\s*{$this->opt($this->t('Rate'))}\s*\:.+$/", $roomInfo, $m)) {
                    $room = $h->addRoom();
                    $roomsCount++;
                    $room->setType($m['type']);
                }

                if (preg_match_all("/\D{3}\s*\d+\,\s*\d{4}\s*(\d+)\s*/", $roomInfo, $m)) {
                    $guestsCount = intval(max($m[1]));
                }
            }
        }

        if ($roomsCount !== null) {
            $h->setRoomsCount($roomsCount);
        }

        if ($guestsCount !== null) {
            $h->setGuestCount($guestsCount);
        }

        if ($kidsCount !== null) {
            $h->setKidsCount($kidsCount);
        }

        // collect prices
        $pricesInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total Room Charge'))}]/ancestor::table[normalize-space()][1]");

        if (preg_match("/^.+?{$this->opt($this->t('Estimated Tax'))}\:\s*(?<tax>[\d\.\,\']+)\s*(?<currency>[[:alpha:]]{3})\s+{$this->opt($this->t('Total Room Charge'))}\:\s*(?<total>[\d\.\,\']+)\s*[[:alpha:]]{3}.*$/", $pricesInfo, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $h->price()
                ->currency($currency)
                ->tax(PriceHelper::parse($m['tax'], $currency))
                ->total(PriceHelper::parse($m['total'], $currency));
        }

        // collect cancellation policy
        $cancellationPolicy = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Deposit is refundable'))}]/ancestor::span[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation Policy'))}]/following::p[normalize-space()][1]");

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
            "#^(\D+)\s+(\d+)\,\s+(\d{4})*\,?\s+(\d+?\s*(?:am|pm))$#ui", // March 08, 2025, 4 PM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+(\D+)\s+\d{4}#u", $str, $m)) {
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

        if (preg_match("#Deposit is refundable if reservation is cancelled (?<day>\d+) days? prior to arrival\.#ui", $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['day'] . ' day');

            return true;
        }

        if (preg_match("#Reservations may be canceled without penalty (?<day>\d+) days? prior to arrival\;#ui", $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['day'] . ' day');

            return true;
        }

        return false;
    }
}
