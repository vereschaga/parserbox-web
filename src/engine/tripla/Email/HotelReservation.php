<?php

namespace AwardWallet\Engine\tripla\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReservation extends \TAccountChecker
{
	public $mailFiles = "tripla/it-802619886.eml, tripla/it-810921099.eml, tripla/it-813431253.eml, tripla/it-818700136.eml";
    public $subjects = [
        '[Reservation Confirmation]',
        '[Reservation Reminder]',
        '[Reservation Cancellation]'
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'detectPhrase' => ['Your reservation is confirmed.', 'Your reservation has been cancelled.'],
            'Reservation Details' => 'Reservation Details',
            'Guest & Room Details' => 'Guest & Room Details',
            'cancelledPhrase' => ['Your reservation has been cancelled.'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tripla.ai') !== false) {
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
        if (
            $this->http->XPath->query("//a[{$this->contains(['tripla.ai'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['detectPhrase']) && $this->http->XPath->query("//*[{$this->contains($dict['detectPhrase'])}]")->length > 0
                && !empty($dict['Reservation Details']) && $this->http->XPath->query("//*[{$this->contains($dict['Reservation Details'])}]")->length > 0
                && !empty($dict['Guest & Room Details']) && $this->http->XPath->query("//*[{$this->contains($dict['Guest & Room Details'])}]")->length > 0
            ) {
                return true;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tripla\.ai$/', $from) > 0;
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
            ->noConfirmation();

        $h->obtainTravelAgency();
        $h->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation number'))}]/following::text()[1]", null, true, "/^([A-Z\d\-]+)$/"), 'Reservation number');

        if ($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your reservation is confirmed.'))}]")){
            $h->setStatus($this->t('Confirmed'));
        } else if ($this->http->XPath->query("//text()[{$this->eq($this->t('cancelledPhrase'))}]")->length > 0){
            $h->setStatus($this->t('Cancelled'));
            $h->setCancelled(true);
        }

        $h->setTravellers($this->http->FindNodes("//text()[{$this->eq($this->t('Guest Name'))}]/following::text()[normalize-space()][1]", null, "/^[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]$/u"), true);

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Find out more about the hotel'))}]/preceding::text()[normalize-space()][4]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Find out more about the hotel'))}]/preceding::text()[normalize-space()][3]"));

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in date'))}]/ancestor::div[1]", null, false, "/^{$this->t('Check-in date')}\s*(\w+\,\s*\w+\s*\d+\,\s*\d{4}\s*(?:[\d\:]+\s*A?P?M?|$))/")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out date'))}]/ancestor::div[1]", null, false, "/^{$this->t('Check-out date')}\s*(\w+\,\s*\w+\s*\d+\,\s*\d{4}\s*(?:[\d\:]+\s*A?P?M?|$))/")));

        $roomsInfo = $this->http->XPath->query("//text()[{$this->eq($this->t('Room Type'))}]");

        foreach ($roomsInfo as $room) {
            $r = $h->addRoom();
            $roomInfo = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $room);

            if (strlen($roomInfo) < 250){
                $r->setType($roomInfo);
            } else {
                $r->setDescription($roomInfo);
            }
        }

        $roomsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of Rooms'))}]/following::text()[normalize-space()][1]", null, false, "/^(\d+)\s*{$this->t('Rooms')}/");

        if ($roomsCount !== null) {
            $h->booked()
                ->rooms($roomsCount);
        }

        $phoneInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Find out more about the hotel'))}]/preceding::text()[normalize-space()][1]", null, false, '/[\d\s\+\(\)\-]+$/');

        if ($phoneInfo !== null) {
            $h->hotel()
                ->phone($phoneInfo);
        }

        $guestInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of Guests'))}][following::text()[{$this->eq($this->t('Guest & Room Details'))}]]/following::text()[normalize-space()][1]", null, true, "/^(\d+)\s*{$this->t('adults')}\/\d+\s*{$this->t('children')}$/");

        if ($guestInfo !== null) {
            $h->booked()
                ->guests($guestInfo);
        }

        $kidsInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of Guests'))}][following::text()[{$this->eq($this->t('Guest & Room Details'))}]]/following::text()[normalize-space()][1]", null, true, "/^\d+\s*{$this->t('adults')}\/(\d+)\s*{$this->t('children')}$/");

        if ($kidsInfo !== null) {
            $h->booked()
                ->kids($kidsInfo);
        }

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment amount (Tax included)'))}]/following::text()[normalize-space()][1]", null, true, "/^(\D{1,3}\s*\d[\d\.\,\`]*)$/");

        $discountsArray[] = 0;

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>\d[\d\.\,\`]*)$/", $priceInfo, $m)
            || preg_match("/^(?<price>\d[\d\.\,\`]*)\s*(?<currency>\D{1,3})$/", $priceInfo, $m) ) {
            $currency = $this->normalizeCurrency($m['currency']);

            $h->price()
                ->total(PriceHelper::parse($m['price'], $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price (Tax included)'))}]/following::text()[normalize-space()][1]", null, true, "/^\D{1,3}\s*(\d[\d\.\,\`]*)$/");

            if ($cost !== null) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Accommodation Tax'))}]/following::text()[normalize-space()][1]", null, true, "/^\D{1,3}\s*(\d[\d\.\,\`]*)$/");

            if ($tax !== null) {
                $h->price()
                    ->fee($this->t('Accommodation Tax'), PriceHelper::parse($tax, $currency));
            }

            $discountNodes = $this->http->FindNodes("//text()[{$this->eq($this->t('Discounts:'))}]/ancestor::div[1]/descendant::div/descendant::text()[normalize-space()][2]", null, "/^\-\s*\D{1,3}\s*(\d[\d\.\,\`]*)$/");

            if (isset($discountNodes)){
                foreach ($discountNodes as $discount){
                    $discountsArray[] = PriceHelper::parse($discount, $currency);
                }
            }
        }

        if (array_sum($discountsArray) !== 0){
            $h->price()
                ->discount(array_sum($discountsArray));
        }

        $cancellationPolicy = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Cancellation Policy'))}])[1]/following::div[1]");

        if ($cancellationPolicy !== null) {
            $h->general()
                ->cancellation($cancellationPolicy);
        }

        $this->detectDeadLine($h);
    }

    private function normalizeCurrency($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'JPY' => ['¥'],
            'TWD' => ['NT$'],
        ];
        $string = trim($string);

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
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

        if (preg_match("/(\d+ days) before check-in: free of charge/", $cancellation, $m)) {
            $h->booked()
                ->deadlineRelative($m[1]);
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
