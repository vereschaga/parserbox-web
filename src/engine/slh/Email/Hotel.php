<?php

namespace AwardWallet\Engine\slh\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Hotel extends \TAccountChecker
{
    public $mailFiles = "slh/it-171740116.eml, slh/it-172731043.eml, slh/it-341697139.eml, slh/it-639571759.eml";
    public $subjects = [
        'Booking Confirmation - ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'BOOKING CONFIRMATION'                                  => ['BOOKING CONFIRMATION', 'Confirmation Number:'],
            'MANAGE MY BOOKING'                                     => ['MANAGE MY BOOKING', 'Your Reservation'],
            'This reservation must be guaranteed with a valid card' => ['This reservation must be guaranteed with a valid card', 'night deposit is required at time of booking and it is refundable according to the rate cancellation policy'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@slh.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'slh.com')]")->length > 0
        || $this->http->XPath->query("//text()[contains(normalize-space(), 'www.lennoxmiamibeach.com')]")->length > 0
        || $this->http->XPath->query("//text()[contains(normalize-space(), 'We are delighted to confirm your reservation')]")->length > 0
        || $this->http->XPath->query("//text()[contains(normalize-space(), 'On behalf of the entire team, we look forward to welcoming you on the next occasion')]")->length > 0) {
            if ($this->http->XPath->query("//text()[{$this->contains($this->t('This reservation must be guaranteed with a valid card'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('BOOKING CONFIRMATION'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('MANAGE MY BOOKING'))}]")->length > 0) {
                return true;
            } elseif ($this->http->XPath->query("//text()[contains(normalize-space(), 'BOOKING CANCELLATION')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'Total price including tax:')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'Cancellation Number:')]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@slh.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $text = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Room Rate:']/ancestor::tr[1]/descendant::text()[normalize-space()]"));
        $countReservation = count($this->http->FindNodes("//text()[{$this->eq($this->t('Your Reservation'))}]"));

        for ($i = 1; $i <= $countReservation; $i++) {
            $xpath = "count(preceding::text()[{$this->eq($this->t('Your Reservation'))}])={$i} and count(following::text()[{$this->eq($this->t('Your Reservation'))}])=" . ($countReservation - $i) . "";
            $h = $email->add()->hotel();

            $travellerText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'First Name:')][{$xpath}]");

            if (preg_match("/First Name\:(\D+)Last Name\:(\D+)/u", $travellerText, $m)) {
                $h->general()
                    ->traveller($m[1] . '' . $m[2]);
            } elseif (!empty($traveller = $this->http->FindSingleNode("//text()[normalize-space()='Guest Name:']/following::text()[normalize-space()][1]"))) {
                $h->general()
                    ->traveller($traveller);
            }

            $conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation Number:')][{$xpath}]/following::text()[normalize-space()][1]");

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation Number:')]/following::text()[normalize-space()][1]");
            }
            $h->general()
                ->confirmation($conf);

            $cancellation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Cancellation / Deposit:')][{$xpath}]/following::text()[normalize-space()][1]");

            if (stripos($cancellation, 'Check-In Time:') !== false) {
                $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Policies']/following::text()[normalize-space()][1]", null, true, "/Cancellation[\s\/]*Deposit\:(.+)/");
            }

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation($cancellation);
            }

            $h->hotel()
                ->name($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Sincerely,')]/following::text()[string-length()>5][1]"));

            $address = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Sincerely,')]/following::text()[string-length()>5][not(contains(normalize-space(), 'MANAGE MY BOOKING'))][2]");

            if (preg_match("/^(?<address>.+)\s*\|\s*P\s*(?<phone>[\d\.]{10,})/", $address, $m)) {
                $h->hotel()
                    ->address($m['address'])
                    ->phone($m['phone']);
            } else {
                $h->hotel()
                    ->address($address);
            }

            $arrDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Arrival Date:')][{$xpath}]/following::text()[string-length()>2][1]");
            $arrTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-In Time:')][{$xpath}]/following::text()[string-length()>2][1]",
                null, true, "/^([\d\:]+)\s*\(/");

            if (empty($arrTime)) {
                $arrTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-In Time:')]", null, true, "/{$this->opt($this->t('Check-In Time:'))}\s*([\d\:]+)/");
            }

            $depDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Departure Date:')][{$xpath}]/following::text()[string-length()>2][1]");
            $depTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-Out Time:')][{$xpath}]/following::text()[string-length()>2][1]",
                null, true, "/^([\d\:]+)\s*\(/");

            if (empty($depTime)) {
                $depTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-Out Time:')]", null, true, "/{$this->opt($this->t('Check-Out Time:'))}\s*([\d\:]+)/");
            }

            $h->booked()
                ->checkIn(strtotime($arrDate . ', ' . $arrTime))
                ->checkOut(strtotime($depDate . ', ' . $depTime));

            $h->booked()
                ->guests($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Number of Adults / Children:')][{$xpath}]/following::text()[string-length()>2][1]",
                    null, true, "/^\s*(\d+)\s*\//"))
                ->kids($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Number of Adults / Children:')][{$xpath}]/following::text()[string-length()>2][1]",
                    null, true, "/^\s*\d+\s*\/\s*(\d+)/"));

            $roomType = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Room:')][{$xpath}]/following::text()[string-length()>2][1]");
            $rateType = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Room Rate:')][{$xpath}]/following::text()[string-length()>2][1]");

            if (preg_match("/\,\s*\d{4}\s*[A-Z]{3}\s+/", $rateType)) {
                $rateType = '';
            }

            $roomDescription = $this->http->FindSingleNode("//strong[normalize-space()='Room Description:']/following::text()[normalize-space()][1]");

            if (empty($roomDescription)) {
                $roomDescription = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Rate Details:')][{$xpath}]/following::text()[string-length()>2][1]");
            }

            $rate = implode(", ", explode("\n", $this->re("/(?:Room Rate)\:\n(.+)\nOccupancy Tax:/su", $text)));

            if (empty($rate)) {
                $rate = implode(', ',
                    $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Daily Rate Breakdown:')][{$xpath}]/following::text()[string-length()>2][1]/ancestor::*[1]/descendant::text()[contains(normalize-space(), ',')]"));
            }

            if (strlen($rate) >= 400) {
                $rate = $this->re("/(.+)Rate includes/", $rate);
            }

            if (!empty($roomType) || !empty($rateType) || !empty($roomDescription) || !empty($rate)) {
                $room = $h->addRoom();

                if (!empty($roomType)) {
                    $room->setType($roomType);
                }

                if (!empty($rateType)) {
                    $room->setRateType($rateType);
                }

                if (!empty($roomDescription)) {
                    $room->setDescription($roomDescription);
                }

                if (!empty($rate)) {
                    $room->setRate($rate);
                }
            }

            $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total price including tax:')][{$xpath}]/following::text()[string-length()>2][1]");

            if (preg_match("/^([A-Z]{3})\s*([\d\,\.]+)$/u", $price, $m)) {
                $h->price()
                    ->total(PriceHelper::parse($m[2], $m[1]))
                    ->currency($m[1]);
            }

            $this->detectDeadLine($h);

            if ($this->http->XPath->query("//text()[contains(normalize-space(), 'BOOKING CANCELLATION')]")->length > 0) {
                $h->general()
                    ->cancelled()
                    ->status('cancelled')
                    ->cancellationNumber($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Cancellation Number:')]/following::text()[string-length()>5][1]"));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/Reservations must be cancelled by (?<time>\d+\s*A?P?M) \(local time\) (?<days>\d+) days prior to arrival to avoid/ui',
            $cancellationText, $m)

        || preg_match('/Reservations must be cancelled by (?<time>\d+\s*A?P?M), local time, (?<days>\d+) days prior to arrival/ui',
                $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['days'] . ' days', $m['time']);
        }
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return $text . '="' . $s . '"'; }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
