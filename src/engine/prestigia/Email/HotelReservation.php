<?php

namespace AwardWallet\Engine\prestigia\Email;

use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "prestigia/it-788443934.eml, prestigia/it-792559706.eml";
    public $subjects = [
        'Prestigia.com : Booking confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Night from'   => ['Night from', 'Nights from'],
            'Cancellation' => ['Cancellation policies', 'Cancellation and payment policies'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@prestigia.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for choosing Prestigia'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Booking confirmation'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Booking management'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Room'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]prestigia\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->HotelReservation($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function HotelReservation(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking confirmation'))}]/ancestor::tr[1]", null, true, "/^Booking confirmation \s*([A-Z\d\-]+)$/"));

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for choosing Prestigia'))}]/ancestor::table[1]/descendant::td[1]/descendant::*[1]", null, false, "/^[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]$/u");
        $h->addTraveller(preg_replace("/\s{2,}/", " ", $traveller), true);

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for choosing Prestigia'))}]/ancestor::table[1]/following::p[1]/descendant::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for choosing Prestigia'))}]/ancestor::table[1]/following::text()[{$this->starts($this->t('Address'))}]/following::text()[normalize-space()][1]"));

        $reservationInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Night from'))}]/ancestor::p[1]");

        if (preg_match("/^\d+\s*Nights?\s*from\s*\w+\s*(?<checkIn>\d+\s*\w+\s*\d{4})\s*to\s*\w+\s*(?<checkOut>\d+\s*\w+\s*\d{4})$/", $reservationInfo, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['checkIn']))
                ->checkOut(strtotime($m['checkOut']));
        }

        $roomsInfo = $this->http->XPath->query("//text()[{$this->eq($this->t('Room'))}]/ancestor::tr[1]/following-sibling::tr");

        foreach ($roomsInfo as $room) {
            $r = $h->addRoom();

            $roomType = $this->http->FindSingleNode("./descendant::td[1]", $room);

            if (preg_match('/^(\d+)$/', $roomType)) {
                $roomType = $this->http->FindSingleNode("./descendant::td[2]", $room);
            }
            $r->setType($roomType);
        }

        $roomsCount = array_sum($this->http->FindNodes("//text()[{$this->eq($this->t('Room'))}]/ancestor::tr[1]/following-sibling::tr/descendant::td[last()]", null, "/^\s*(\d+)\s*/"));

        if ($roomsCount !== null) {
            $h->booked()
                ->rooms($roomsCount);
        }

        $phoneInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for choosing Prestigia'))}]/ancestor::table[1]/following::text()[{$this->starts($this->t('Telephone'))}]/following::text()[normalize-space()][1]", null, false, '/[\d\s\+\(\)\-]+$/');

        if ($phoneInfo !== null) {
            $h->hotel()
                ->phone($phoneInfo);
        }

        $guestInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Night from'))}]/ancestor::p[1]/following-sibling::table[1]/descendant::tr[position() > 1]/descendant::td[{$this->contains($this->t('pers'))}][1]", null, true, '/(\d+)\s*pers\./');

        if ($guestInfo !== null) {
            $h->booked()
                ->guests($guestInfo);
        }

        $kidsInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Night from'))}]/ancestor::p[1]/following-sibling::table[1]/descendant::tr[position() > 1]/descendant::td[{$this->contains($this->t('child'))}][1]", null, true, '/\d+\s*pers\.\,\s*(\d+)\s*\w+/');

        if ($kidsInfo !== null) {
            $h->booked()
                ->kids($kidsInfo);
        }

        $cancellationPolicy = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation'))}]/ancestor::*[2]/following-sibling::*[1]");

        if (preg_match('/to\s*\w+\s*(\d+\s*\w+\s*\d{4})\s*\,\s*([\d\:]+\s*A?P?M?)\s*/', $cancellationPolicy)
            || preg_match('/In\s*case\s*of\s*cancellation\,\s*no\-show\s*or\s*modification\,\s*the\s*total\s*amount\s*of\s*the\s*booking\s*is\s*not\s*refunded\./', $cancellationPolicy)) {
            $h->general()
                ->cancellation($cancellationPolicy);
        } else {
            $cancellationPolicy = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation'))}]/ancestor::*[2]");

            $h->general()
                ->cancellation($cancellationPolicy);
        }
        $this->detectDeadLine($h);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellation = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Before\s*\w+\s*(\d+\s*\w+\s*\d{4})\s*\,\s*([\d\:]+\s*A?P?M?)\s*\:\s*Free\s*cancellation/", $cancellation, $m)
            || preg_match("/to\s*\w+\s*(\d+\s*\w+\s*\d{4})\s*\,\s*([\d\:]+\s*A?P?M?)\s*/", $cancellation, $m)) {
            if (preg_match("/^00\:\d+\s*AM$/", $m[2])) {
                $m[2] = str_replace('AM', '', $m[2]);
            }

            $h->booked()
                ->deadline(strtotime($m[1] . ' ' . $m[2]));
        }

        if (preg_match("/In\s*case\s*of\s*cancellation\,\s*no\-show\s*or\s*modification\,\s*the\s*total\s*amount\s*of\s*the\s*booking\s*is\s*not\s*refunded\./", $cancellation)) {
            $h->booked()
                ->nonRefundable();
        }
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
