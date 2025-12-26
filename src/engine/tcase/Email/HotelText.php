<?php

namespace AwardWallet\Engine\tcase\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class HotelText extends \TAccountChecker
{
    public $mailFiles = "tcase/it-58417099.eml";

    public $reFrom = "@accomplishedtraveler.com";

    public $reSubject = [
        "en" => "Travel Reservation to",
    ];

    public $reBody = ['will@accomplishedtraveler.com'];

    public $reBody2 = [
        "en" => [
            "Confirmed, Confirmation",
            "Passenger(s)",
            "Reservation code",
            "Terms",
            "will@accomplishedtraveler.com",
        ],
    ];

    public static $dictionary = [
        "en" => [
            "Reservation code"     => "Reservation code\:",
            "Confirmation Number:" => ["Confirmation#", "Confirmation Number:"],
            "Traveller"            => ["Traveller", "Passenger\(s\):"],
            "Phone"                => ["Phone", "Ph"],
            "Room"                 => "Room\(s\)\:",
            "Guests"               => "Guest\(s\)\:",
            "Room Details"         => "Room Details\:",
        ],
    ];

    public $lang = "en";

    public function parsePlain(Email $email)
    {
        $text = $this->http->Response['body'];

        $hotel = $email->add()->hotel();

        //Reservation code
        $reservationCode = trim($this->re("#{$this->opt($this->t('Reservation code'))}\s+([A-Z]{5})#", $text));

        if (!empty($reservationCode)) {
            $hotel->ota()
                ->confirmation($reservationCode, 'Reservation code');
        }

        //ConfirmationNumber
        $confirmation = $this->re("/{$this->opt($this->t('Confirmation Number:'))}\s?([A-Z\d]+)/u", $text);

        if (!empty($confirmation)) {
            $hotel->general()
                ->confirmation($confirmation, 'confirmation');
        }

        //Travellers
        $travellers = explode('/', $this->re("/{$this->opt($this->t('Traveller'))}\s+([^\n\r]+)/ms", $text));

        if (count($travellers) > 0) {
            $hotel->general()
                ->travellers($travellers, false);
        }

        // Hotel Name
        $hotelName = trim($this->re("#\s+(\D+Hotels\)?)#", $text));

        if (!empty($hotelName)) {
            $hotel->hotel()
                ->name($hotelName);
        }

        // Address
        $address = trim($this->re("#{$this->opt($this->t('Check Out:'))}\s+\w+[,]\s+\d{1,2}\s\w+\s+(.+){$this->opt($this->t('Phone'))}#s", $text));

        if (!empty($address)) {
            $hotel->hotel()
                ->address(preg_replace('/\r\n/', ' ', $address));
        }

        // Phone
        $phone = trim($this->re("#{$this->opt($this->t('Phone'))}\s*:\s+(.+)#", $text));

        if (!empty($phone)) {
            $hotel->hotel()
                ->phone($phone);
        }

        // CheckInDate
        $chechInDate = strtotime($this->normalizeDate($this->re("#Check In\s*:\s+(.+)#", $text)));

        if (!empty($chechInDate)) {
            $hotel->booked()
                ->checkIn($chechInDate);
        }

        // CheckOutDate
        $chechOutDate = strtotime($this->normalizeDate($this->re("#Check Out\s*:\s+(.+)#", $text)));

        if (!empty($chechOutDate)) {
            $hotel->booked()
                ->checkOut($chechOutDate);
        }

        // CancellationPolicy
        $cancellationPolicy = trim(str_replace(["\r", "\n"], '', preg_replace('#^\s*\*\s*#m', ' ', $this->re("#Cancellation policy\s*\n\s*(\*\s+Cancellation.+\n(\s*\*\s+.+\n){1,9})#", $text))));

        if (empty($cancellationPolicy)) {
            $cancellationPolicy = trim($this->re("/(Cancel\s\d+\sdays?\sprior\sto\sarrival)/us", $text));
        }

        if (!empty($cancellationPolicy)) {
            $hotel->general()->cancellation($cancellationPolicy);
            $this->detectDeadLine($hotel, $cancellationPolicy);
        }

        // Room Count
        $roomCount = trim($this->re("/{$this->opt($this->t('Room'))}\s+(\d+)/su", $text));

        if (!empty($roomCount)) {
            $hotel->booked()
                ->rooms($roomCount);
            $room = $hotel->addRoom();
        }

        // GuestCount
        $guestCount = trim($this->re("/{$this->opt($this->t('Guests'))}\s+(\d+)/su", $text));

        if (!empty($guestCount)) {
            $hotel->booked()
                ->guests($guestCount);
        }

        // RoomType
        $roomType = trim($this->re("#Room\s*:\s+(.+)#", $text));

        if (empty($roomType)) {
            $roomType = trim($this->re("#{$this->opt($this->t('Room Details'))}\s+(\D+)\s+Cancel#su", $text));
        }

        if (!empty($roomType)) {
            if (!isset($room)) {
                $room = $hotel->addRoom();
            }
            $room->setType($roomType);
        }

        // RoomTypeDescription
        $typeDescription = trim($this->re("#arrival\s+(.+){$this->opt($this->t('Room'))}#su", $text));

        if (!empty($typeDescription)) {
            if (!isset($room)) {
                $room = $hotel->addRoom();
            }
            $room->setDescription(preg_replace('/\r\n/', ', ', $typeDescription));
        }

        // Cost
        // Total
        $total = $this->amount($this->re("#Price\s*:\s+(.+)#", $text));

        if (!empty($total)) {
            $hotel->price()
                ->total($total)
                ->currency($this->currency($this->re("#Price\s*:\s+(.+)#", $text)));
        }

        // Status
        if ($this->http->FindSingleNode("//text()[" . $this->contains($this->t("changed")) . "]")) {
            $hotel->general()
                ->status('changed');
        } elseif ($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Confirmation Number:")) . "]")) {
            $hotel->general()
                ->status('confirmed');
        } elseif ($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Cancellation Number:")) . "]")) {
            $hotel->general()
                ->status('cancelled')
                ->cancelled();
        }

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        foreach ($this->reBody as $reBody) {
            if (strpos($body, $reBody) === false) {
                return false;
            }
        }

        foreach ($this->reBody2 as $lang) {
            foreach ($lang as $re) {
                if (strpos($body, $re) === false) {
                    return false;
                }
            }
        }

        return true;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->setBody($parser->getPlainBody());

        $this->parsePlain($email);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d+)/(\d+)/(\d{4})\s*$#", //21/06/2017
        ];
        $out = [
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $hotel, string $cancellationText)
    {
        if (
        preg_match("/Cancel\s(?<prior>\d+)\sdays?\sprior\sto\sarrival/",
            $cancellationText, $m)
        ) {
            $hotel->booked()->deadlineRelative($m['prior'] . ' days', '00:00');
        }
    }
}
