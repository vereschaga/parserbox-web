<?php

namespace AwardWallet\Engine\goldpassport\Email;

use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourUpcomingStay extends \TAccountChecker
{
    public $mailFiles = "goldpassport/it-757348146.eml";
    public $subjects = [
        'Reservation Details for Your Upcoming Stay at',
    ];

    public $subject;

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@t1.hpe-esp.hyatt.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Hyatt')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Nightly rate per room'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]t1\.hpe-esp\.hyatt\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation #']/ancestor::td[1]", null, true, "/{$this->opt($this->t('Confirmation #'))}\s*(\d{5,})$/"))
            ->traveller(preg_replace("/^(?:Mrs\.|Mr\.|Ms\.)/", "", $this->http->FindSingleNode("//text()[normalize-space()='Guest Name']/ancestor::table[1]", null, true, "/^{$this->opt($this->t('Guest Name'))}\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/")))
            ->cancellation($this->http->FindSingleNode("//text()[normalize-space()='CANCELLATION POLICY']/following::tr[1]"));

        $hotelName = $this->re("/Reservation Details for Your Upcoming Stay at\s*(.+)/", $this->subject);

        if (empty($hotelName)) {
            $hotelName = $this->re("/Your Upcoming Stay at\s+(.+)\s+Has Been Updated/", $this->subject);
        }
        $h->hotel()
            ->name($hotelName)
            ->address($this->http->FindSingleNode("//text()[normalize-space()='Contact']/ancestor::tr[1]/following::img[1]/following::text()[normalize-space()][1]"));

        $phone = $this->http->FindSingleNode("//text()[normalize-space()='Contact']/ancestor::tr[1]/following::img[2]/following::text()[normalize-space()][1]", null, true, "#^[＋\-+()\dA-Z\s.,\\\/:]+\d+[-+()\dA-Z\s.,\\\/:]+$#");

        if (!empty($phone)) {
            $h->hotel()
                ->phone(str_replace('＋', '+', $phone));
        }

        $inText = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/ancestor::table[1]");
        $outText = $this->http->FindSingleNode("//text()[normalize-space()='Checkout']/ancestor::table[1]");
        $h->booked()
            ->checkIn($this->normalizeDate($inText))
            ->checkOut($this->normalizeDate($outText));

        $guests = $this->http->FindSingleNode("//text()[normalize-space()='Adults']/ancestor::table[1]", null, true, "/^Adults\s*(\d+)$/");

        if (!empty($guests)) {
            $h->setGuestCount($guests);
        }

        $kids = $this->http->FindSingleNode("//text()[normalize-space()='Children']/ancestor::table[1]", null, true, "/^Children\s*(\d+)$/");

        if ($kids !== null) {
            $h->setKidsCount($kids);
        }

        $rooms = $this->http->FindSingleNode("//text()[normalize-space()='Room(s) booked']/ancestor::table[1]", null, true, "/^Room\(s\) booked\s*(\d+)$/");

        if (!empty($rooms)) {
            $h->setRoomsCount($rooms);
        }

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Room type']/ancestor::table[1]", null, true, "/^Room type\s*(.+)$/");
        $roomDescription = $this->http->FindSingleNode("//text()[normalize-space()='Room description']/ancestor::table[1]", null, true, "/^Room description\s*(.+)$/");

        if (!empty($roomType) || !empty($roomDescription)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($roomDescription)) {
                $room->setDescription($roomDescription);
            }
        }

        $this->detectDeadLine($h);

        $accounts = array_unique(array_filter($this->http->FindNodes("//text()[normalize-space()='Membership #']/ancestor::table[1]", null, "/{$this->opt($this->t('Membership #'))}\s*([\*\d]+)/")));

        if (count($accounts) > 0) {
            foreach ($accounts as $account) {
                $pax = preg_replace("/^(?:Mrs\.|Mr\.|Ms\.)/", "", $this->http->FindSingleNode("//text()[normalize-space()='Membership #']/ancestor::table[1]/preceding::table[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Guest Name'))][1][not(contains(normalize-space(), 'Membership #'))]"));

                if (!empty($pax)) {
                    $h->addAccountNumber($account, true, $pax);
                } else {
                    $h->addAccountNumber($account, true);
                }
            }
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#(?:Check-in|Checkout)\s*(?:\w+\,\s*)?(\d+)\-(\w+)\-(\d{4})\s*(\d+\:\d+\s*A?P?M)$#u", //Check-in Thursday, 14-Nov-2024 03:00 PM
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^(?<time>\d+\:\d+A?P?M)\s+HOTEL TIME\s+(?<prior>\d+\s+(?:days?|DYS)) BFR ARRV?/iu", $cancellationText, $m)
        || preg_match("/PLEASE BE AWARE THAT CANCELLATIONS MADE LESS THAN (?<prior>\d+\s+\w+) BEFORE (?<time>\d+\:\d+) ON THE DAY OF ARRIVAL/iu", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['prior'], $m['time']);
        }

        if (preg_match("/RESERVATIONS CANCELLED WITHIN\s+(?<prior>\d+\s*\w+)\s+OF DAY\s+OF\s+ARRIVAL\s*WILL/iu", $cancellationText, $m)
        || preg_match("/RESERVATIONS CANCELLED WITHIN (?<prior>\d+\s*\w+) OF ARRIVAL/iu", $cancellationText, $m)
        || preg_match("/PLEASE BE AWARE THAT CANCELLATIONS MADE LESS THAN (?<prior>\d+\s*\w+) BEFORE ARRIVAL/iu", $cancellationText, $m)
        || preg_match("/^(?<prior>\d+\s*(?:HRS|DAYS))\s*PRIOR OR \d+\s*(?:NIGHT|NT) FEE/iu", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['prior']);
        }

        if (preg_match("/PLEASE BE AWARE THAT CANCELLATIONS MADE AFTER (?<time>\d+\:\d+) ON THE DAY OF ARRIVAL/iu", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative('0 day', $m['time']);
        }
    }
}
