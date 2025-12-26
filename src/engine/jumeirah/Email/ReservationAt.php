<?php

namespace AwardWallet\Engine\jumeirah\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationAt extends \TAccountChecker
{
    public $mailFiles = "jumeirah/it-378682633.eml, jumeirah/it-387621308.eml, jumeirah/it-699309534.eml";
    public $subjects = [
        'Your reservation at',
    ];

    public $lang = 'en';
    public $subject;

    public static $dictionary = [
        "en" => [
            'Cancel Policy:'     => ['Cancel Policy:', 'Cancellation Policy:', 'Cancellation policy'],
            'VIEW MY BOOKING'    => ['VIEW MY BOOKING', 'View My Booking', 'View reservation'],
            'jumeirah.com'       => ['jumeirah.com', 'jumeirah.com/bahrain'],
            'Check in'           => ['Check in', 'CHECK IN'],
            'Check out'          => ['Check out', 'CHECK OUT'],
            'Room Type:'         => ['Room Type:', 'Room type:'],
            'Nightly Room Rate:' => ['Nightly Room Rate:', 'Average nightly room rate'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'jumeirah.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Jumeirah')]")->length > 0) {
            return ($this->http->XPath->query("//text()[{$this->contains($this->t('VIEW MY BOOKING'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('Cancellation number:'))}]")->length > 0)
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Room Type:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Airport transfers:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@\.]jumeirah\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $hotelName = $this->re("/{$this->opt($this->t('Your reservation at'))}\s*(.+)/", $this->subject);

        if (stripos($hotelName, 'is cancelled') !== false) {
            $h->general()
                ->cancelled();
            $hotelName = $this->re("/^(.+)\s+{$this->opt($this->t('is cancelled'))}/", $hotelName);
            $h->general()
                ->cancellationNumber($this->http->FindSingleNode("//text()[normalize-space()='Cancellation number:']/ancestor::td[1]", null, true, "/{$this->opt($this->t('Cancellation number:'))}\s*([\dA-Z]+)/su"))
                ->noConfirmation();
        } else {
            $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Booking number:']/following::text()[string-length()>2][1]", null, true, "/^([A-Z\d]{6,})$/");
            $h->general()
                ->confirmation($confirmation);
        }

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Guest name:']/ancestor::tr[1]/descendant::td[2]"))
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancel Policy:'))}]/following::text()[normalize-space()][1]"));

        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('jumeirah.com'))}]/preceding::text()[{$this->starts($hotelName)}][1]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

        if (empty($hotelInfo)) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->contains($this->t('jumeirah.com'))}]/following::text()[{$this->starts($hotelName)}][1]/ancestor::tr[1]/descendant::text()[normalize-space()]"));
        }

        if (empty($hotelInfo)) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->contains($this->t('jumeirah.com'))}]/following::text()[{$this->contains('Jumeirah Resort')}][1]/ancestor::tr[1]/descendant::text()[normalize-space()]"));
        }

        $address = str_replace("\n", " ", $this->re("/$hotelName(.+){$this->opt('jumeirah.com')}/su", $hotelInfo));

        if (empty($address)) {
            $address = str_replace("\n", " ", $this->re("/$hotelName\,\s+(.+)/su", $hotelInfo));
        }

        if (empty($address)) {
            $address = str_replace("\n", " ", $this->re("/^.*Jumeirah Resort\,\s+(.+)/su", $hotelInfo));
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address);

        $checkInDate = implode(" ", $this->http->FindNodes("//text()[{$this->eq($this->t('Check in'))}]/ancestor::table[1]/descendant::text()[normalize-space()][not({$this->contains($this->t('Check in'))})]"));
        $checkOutDate = implode(" ", $this->http->FindNodes("//text()[{$this->eq($this->t('Check out'))}]/ancestor::table[1]/descendant::text()[normalize-space()][not({$this->contains($this->t('Check out'))})]"));

        $h->booked()
            ->checkIn($this->normalizeDate($checkInDate))
            ->checkOut($this->normalizeDate($checkOutDate))
            ->guests($this->http->FindSingleNode("//text()[normalize-space()='Number of adults:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\d+)$/"))
            ->rooms($this->http->FindSingleNode("//text()[normalize-space()='Number of rooms:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\d+)$/"));

        $checkInTime = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'check-in time is')]", null, true, "/{$this->opt($this->t('check-in time is'))}\s*([\d\:]+)(?:hrs)?/");

        if (!empty($checkInTime)) {
            $h->booked()
                ->checkIn(strtotime($checkInTime, $h->getCheckInDate()));
        }

        $checkOutTime = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'check-out time is')]", null, true, "/{$this->opt($this->t('check-out time is'))}\s*([\d\:]+)(?:hrs)?/");

        if (!empty($checkOutTime)) {
            $h->booked()
                ->checkOut(strtotime($checkOutTime, $h->getCheckOutDate()));
        }

        $kids = $this->http->FindSingleNode("//text()[normalize-space()='Number of children:']/ancestor::tr[1]/descendant::td[2]");

        if ($kids !== null) {
            $h->booked()
                ->kids($kids);
        }

        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type:'))}]/ancestor::tr[1]/descendant::td[2]");
        $roomRate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Nightly Room Rate:'))}]/following::text()[normalize-space()][1]");

        if (!empty($roomType) || !empty($roomRate)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($roomRate)) {
                $room->setRate($roomRate);
            }
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total Room Cost per Stay*']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)\s/", $total, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
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

        if (preg_match("/100[%] non\-refundable deposit will be charged 21 days prior to the arrival date/", $cancellationText)
        || preg_match("/Full prepayment at the time of booking\. No refunds for any cancellation\-modification or no\-show/", $cancellationText)) {
            $h->setNonRefundable(true);
        }

        if (preg_match("/Unless otherwise stated, guaranteed reservations may be cancelled up to (\d+) hours prior to the day of arrival at no charges/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' hours');
        }

        if (preg_match("/Reservation must be cancelled (\d+) days prior to arrival to avoid penalty of/", $cancellationText, $m)
        || preg_match("/Cancellations within (\d+) days of arrival charged/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days');
        }
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+\s*\w+\s*\d{4})\D+(\d+\:\d+)\)$#u", //13 DECEMBER 2022 (Check In Time: From 15:00)
            "#^\w+\s+(\d+)\w*\s*of\s*(\w+)\,\s*(\d{4})$#u", //Monday 29th of July, 2024
        ];
        $out = [
            "$1, $2",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
