<?php

namespace AwardWallet\Engine\paisly\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "paisly/it-807389269.eml, paisly/it-808186760.eml, paisly/it-809553162.eml, paisly/it-810596680.eml";

    public $subjects = [
        'All set! Your hotel booking confirmation.',
        'Your hotel booking confirmation with Paisly.',
        'Your hotel has been cancelled.',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Order Confirmation' => ['Order Confirmation', 'Cancel confirmation'],
            'detectPhrase'       => ['Thanks for booking with Paisly.', 'Til next time'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'paisly.com') !== false) {
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
            $this->http->XPath->query("//a/@href[{$this->contains(['.paisly.com'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Thanks for booking with Paisly'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Order Confirmation']) && $this->http->XPath->query("//*[{$this->eq($dict['Order Confirmation'])}]")->length > 0
                && !empty($dict['Rate information']) && $this->http->XPath->query("//*[{$this->eq($dict['Rate information'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]paisly\.com$/', $from) > 0;
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

        $h->obtainTravelAgency();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('has been cancelled'))}]")->length > 0) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }

        $confNumber = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Paisly confirmation number'))}])[1]/ancestor::tr[1]/following-sibling::tr[2]/descendant::td[1]", null, false, "/^([\dA-Z\-]+)$/");

        if ($h->getCancelled() !== true) {
            $h->ota()
                ->confirmation($confNumber, 'Paisly confirmation number');
        }

        $reservationDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Paisly confirmation number'))}]/ancestor::tr[1]/following-sibling::tr[2]/descendant::td[2]", null, false, "/^(\d+\/\d+\/\d{4})$/");

        if ($reservationDate !== null) {
            $h->general()
                ->date(strtotime($reservationDate));
        }

        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Reservation details'))}]/following::tr[2]/descendant::td/descendant::text()[2]", null, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u");

        if ($h->getCancelled() !== true) {
            $h->setTravellers(array_unique($travellers), true);
        } else {
            $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Til next time'))}]", null, false, "/^\'{$this->t('Til next time')}\s*\,\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\.$/u");
            $h->addTraveller($traveller, false);
        }

        $address = $this->http->FindNodes("(//text()[{$this->eq($this->t('Location'))}])[1]/following::tr[normalize-space()][1]/descendant::td[2]/descendant::text()[not(contains(normalize-space(), 'Get directions'))]");

        if ($h->getCancelled() !== true) {
            $h->hotel()
                ->address(implode(" ", $address));
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Order Confirmation'))}]/following::tr[normalize-space()][1]/descendant::text()[1]"));

        $checkDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Order Confirmation'))}]/following::tr[normalize-space()][1]/descendant::text()[3]");

        if (preg_match("/^(?<checkIn>\w+\s*\,\s*\w+\s*\d+)\s*\-\s*(?<checkOut>\w+\s*\,\s*\w+\s*\d+)$/", $checkDate, $m)) {
            $checkInTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in'))}]", null, false, "/^{$this->t('Check-in')}\s*([\d\:]+\s*A?P?M?)\s*/");
            $checkOutTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-out'))}]", null, false, "/^{$this->t('Check-out')}\s*([\d\:]+\s*A?P?M?)$/");

            $h->booked()
                ->checkIn($this->normalizeDate($m['checkIn'] . ' ' . $checkInTime))
                ->checkOut($this->normalizeDate($m['checkOut'] . ' ' . $checkOutTime));
        }

        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[{$this->eq($this->t('Order Confirmation'))}]/following::tr[normalize-space()][1]/descendant::text()[5]", null, false, "/^(\d+)\s*rooms?/"));

        $roomsInfo = $this->http->XPath->query("//tr[td[1][{$this->eq($this->t('Reservation details'))}]]/following::tr[normalize-space()][1]/descendant::tr[1]/descendant::table[1]/descendant::td[{$this->contains($this->t('Room'))}]");

        foreach ($roomsInfo as $room) {
            $r = $h->addRoom();

            $r->setType($this->http->FindSingleNode("//text()[{$this->eq($this->t('Order Confirmation'))}]/following::tr[normalize-space()][1]/descendant::text()[4]"));

            $roomConfirmation = $this->http->FindSingleNode("./descendant::text()[last()]", $room, false, "/^{$this->t('Room confirmation')}\s*\#\:\s*([\dA-Z\-]+)$/");

            if ($roomConfirmation !== null) {
                $r->setConfirmation($roomConfirmation);
                $h->general()
                    ->confirmation($roomConfirmation, 'Room confirmation number');
            } else {
                $h->general()
                    ->noConfirmation();
            }

            $rateInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate information'))}]/following::td[2]/descendant::tr[{$this->contains($this->t('night'))} and not({$this->contains($this->t('%'))})]/descendant::text()[1]", null, false, "/^(\D{1,3}\s*\d[\d\.\,\']*)\s*x\s*\d*\s*nights?$/");

            if ($rateInfo !== null) {
                $r->setRate($rateInfo . " per night");
            }
        }

        $guestsCount = $this->http->FindNodes("//tr[td[1][{$this->eq($this->t('Reservation details'))}]]/following::tr[normalize-space()][1]/descendant::tr[1]/descendant::td/descendant::text()[3]", null, "/(\d+)\s*Adult/");

        if ($h->getCancelled() !== true) {
            $h->booked()
                ->guests(array_sum($guestsCount));
        } else {
            $guestsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Order Confirmation'))}]/following::tr[normalize-space()][1]/descendant::text()[5]", null, false, "/(\d+)\s*guests?/");
            $h->booked()
                ->guests($guestsCount);
        }

        $kidsCount = $this->http->FindNodes("//tr[td[1][{$this->eq($this->t('Reservation details'))}]]/following::tr[normalize-space()][1]/descendant::tr[1]/descendant::td/descendant::text()[3]", null, "/(\d+)\s*Child/");

        if (array_sum($kidsCount) !== 0) {
            $h->booked()
                ->kids(array_sum($kidsCount));
        }

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::td[1]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>\d[\d\.\,\']*)$/", $priceInfo, $m)
            || preg_match("/^(?<price>\d[\d\.\,\']*)\s*(?<currency>\D{1,3})$/", $priceInfo, $m)) {
            $h->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->cost(PriceHelper::parse($this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate information'))}]/following::td[2]/descendant::tr[{$this->contains($this->t('night'))} and not({$this->contains($this->t('%'))})]/descendant::text()[2]", null, false, "/(\d[\d\.\,\']*)/"), $m['currency']));

            $taxes = $this->http->XPath->query("//text()[{$this->eq($this->t('Rate information'))}]/following::td[2]/descendant::tr[not(contains(normalize-space(),'pts'))][not(contains(normalize-space(),'%'))][position() > 1]");

            foreach ($taxes as $tax) {
                $taxName = $this->http->FindSingleNode("./descendant::text()[1]", $tax);
                $taxPrice = $this->http->FindSingleNode("./descendant::text()[2]", $tax, false, '/^\D{1,3}\s*(\d[\d\.\,\']*)$/');

                if ($taxName !== null && $taxPrice !== null) {
                    $h->price()
                        ->fee($taxName, PriceHelper::parse($taxPrice, $m['currency']));
                }
            }

            $points = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate information'))}]/following::td[2]/descendant::tr[{$this->contains($this->t('pts'))}]", null, false, "/^\D*(\d+)\s*(?:TrueBlue )?pts\b/");

            if ($points !== null) {
                $h->ota()
                    ->earnedAwards($points . ' TrueBlue points');
            }
        }

        $accountNum = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('TrueBlue number'))}])[1]/ancestor::tr[1]/following-sibling::tr[2]/descendant::td[1]", null, false, "/^([\dA-Z\-]+)$/");

        if ($accountNum !== null) {
            $h->ota()
                ->account($accountNum, false, null, 'TrueBlue number');
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation policy'))}]/following::tr[2]");

        if ($cancellation !== null) {
            $h->general()
                ->cancellation($cancellation);
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

        if (preg_match("/Free cancellation until\s*(\w+\,\s*\w+\s*\d+\s*[\d\:]+\s*A?P?M?)/", $cancellation, $m)) {
            $h->booked()
                ->deadline($this->normalizeDate($m[1]));
        }

        if ($this->http->FindSingleNode("//text()[{$this->eq($this->t('Order Confirmation'))}]/following::tr[2]/descendant::text()[last()]", null, false, "/^Non\-refundable$/") !== null) {
            $h->booked()
                ->nonRefundable();
        }
    }

    private function normalizeDate($str)
    {
        if (preg_match("/^(?<week>\w+)\s*\,\s*(?<date>\w+\s*\d+\s*[\d\:]+\s*A?P?M?)$/", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        }

        return $str;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
