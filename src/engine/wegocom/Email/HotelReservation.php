<?php

namespace AwardWallet\Engine\wegocom\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReservation extends \TAccountChecker
{
	public $mailFiles = "wegocom/it-808502716.eml, wegocom/it-808502723.eml, wegocom/it-808532144.eml";
    public $subjects = [
        'Booking Confirmed',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'detectPhrase' => ['Your booking is confirmed!'],
            'from' => ['from', 'after', 'before', 'until']
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'wego.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('detectPhrase'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Wego Reservation ID'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Check-in'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]wego\.com$/', $from) > 0;
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

        $h->ota()
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Wego Reservation ID'))}])[1]/following::text()[1]", null, false, "/^([\dA-Z\-]+)$/"), 'Wego Reservation ID');

        $h->general()
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Partner Reservation ID'))}])[1]/following::text()[1]", null, false, "/^([\dA-Z\-]+)$/"), 'Partner Reservation ID');

        $reservationDate = $this->http->FindSingleNode("//tr[td[{$this->eq($this->t('Payment made on'))}]]/following::tr[normalize-space()][1]/descendant::td[2]", null, false, "/^(\d+\s*\w+\s*\d{4}\s*\,\s*[\d\:]+)\s*/");

        if ($reservationDate !== null) {
            $h->general()
                ->date(strtotime($reservationDate));
        }

        $h->addTraveller($this->http->FindSingleNode("//tr[td[{$this->eq($this->t('Guests'))}]]/following::tr[normalize-space()][1]/descendant::tr[1]", null, false, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u"), true);

        $h->hotel()
            ->address($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Property details'))}])[1]/preceding::tr[1]"))
            ->phone($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Property details'))}])[1]/following::tr[1]", null, false, '/^[\d\s\(\)\+\-]+$/'))
            ->name($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Property details'))}])[1]/preceding::tr[2]"));

        $checkinDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/following::tr[normalize-space()][1]/descendant::td[1]", null, false, "/^\w*\s*\,\s*(\d+\s*\w+\s*\d{4})$/");

        $checkInTime = $this->http->FindSingleNode("///text()[{$this->eq($this->t('Check-in'))}]/following::tr[normalize-space()][2]/descendant::td[1]", null, false, "/^{$this->opt($this->t('from'))}\s*([\d\:]+\s*A?P?M?)$/");

        if ($checkinDate !== null && $checkInTime !== null) {
            $h->booked()
                ->checkIn(strtotime($checkinDate . ' '  . $checkInTime));
        }

        $checkOutDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/following::tr[normalize-space()][1]/descendant::td[2]", null, false, "/^\w*\s*\,\s*(\d+\s*\w+\s*\d{4})$/");

        $checkOutTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/following::tr[normalize-space()][2]/descendant::td[2]", null, false, "/^{$this->opt($this->t('from'))}\s*([\d\:]+\s*A?P?M?)$/");

        if ($checkOutDate !== null && $checkOutTime !== null) {
            $h->booked()
                ->checkOut(strtotime($checkOutDate . ' '  . $checkOutTime));
        }

        $roomsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Order Confirmation'))}]/following::tr[normalize-space()][1]/descendant::text()[5]", null, false, "/^(\d+)\s*rooms?/");

        if ($roomsCount !== null){
            $h->booked()
                ->rooms($roomsCount);
        }

        $guestsInfo = $this->http->FindSingleNode("//tr[td[{$this->eq($this->t('Guests'))}]]/following::tr[normalize-space()][1]/descendant::tr[2]", null, false, "/^Booking\s*for\s*(\d+)\s*adults?$/");

        if ($guestsInfo !== null){
            $h->booked()
                ->guests($guestsInfo);
        }

        $roomsInfo = $this->http->XPath->query("//tr[td[{$this->eq($this->t('Check-in'))}]]/ancestor::table[3][{$this->starts($this->t('Check-in'))}]/following-sibling::table[1]");

        foreach ($roomsInfo as $room){
            $r = $h->addRoom();

            $r->setType($this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $room));
        }

        $priceInfo = $this->http->FindSingleNode("//tr[td[{$this->eq($this->t('Total charges'))}]]/descendant::td[2]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>\d[\d\.\,\']*)$/", $priceInfo, $m)||
            preg_match("/^(?<price>\d[\d\.\,\']*)\s*(?<currency>\D{1,3})$/", $priceInfo, $m)) {
            $h->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->tax(PriceHelper::parse($this->http->FindSingleNode("//tr[td[{$this->eq($this->t('Total charges'))}]]/preceding::tr[1]/descendant::td[2]", null, false, "/^\D{1,3}\s*(\d[\d\.\,\']*)$/"), $m['currency']))
                ->cost(PriceHelper::parse($this->http->FindSingleNode("//tr[td[{$this->eq($this->t('Payment summary'))}]]/following::tr[2]/descendant::tr[1]/descendant::td[last()]", null, false, "/^\D{1,3}\s*(\d[\d\.\,\']*)$/"), $m['currency']));
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation policy'))}]/following::tr[2]");

        if ($cancellation !== null){
            $h->general()
                ->cancellation($cancellation);
        }

        $this->detectDeadLine($h);

    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellation = $h->getCancellation())) {
            return;
        }

        if (preg_match("/FREE cancellation until\s*(\d+\s*\w+\s*\d{4}\s*\,\s*[\d\:]+)/", $cancellation, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1]));
        }

        if (preg_match("/{$this->t('This is a non-refundable booking.')}/", $cancellation, $m)) {
            $h->booked()
                ->nonRefundable();
        }
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s, '/'));
            }, $field)) . ')';
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
