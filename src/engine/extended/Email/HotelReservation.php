<?php

namespace AwardWallet\Engine\extended\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReservation extends \TAccountChecker
{
	public $mailFiles = "extended/it-874541968.eml";
    public $subjects = [
        'Booking confirmation for ESA',
        'Booking confirmation for Extended Stay America',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Confirmation Letter' => 'Confirmation Letter',
            'Room Type Details' => 'Room Type Details',
            'Reservation Details' => 'Reservation Details',
            'Reservations must be cancelled by' => ['Reservation must be cancelled by', 'Reservations must be cancelled by']
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'extendedstay.com') !== false) {
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
        if (stripos($parser->getHeader('from'), 'extendedstay.com') === false
            && $this->http->XPath->query("//*[{$this->contains(['Extended Stay America', '@extendedstay.com', 'www.extendedstayamerica.com'])}]")->length === false
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Confirmation Letter']) && $this->http->XPath->query("//*[{$this->contains($dict['Confirmation Letter'])}]")->length > 0
                && !empty($dict['Room Type Details']) && $this->http->XPath->query("//*[{$this->contains($dict['Room Type Details'])}]")->length > 0
                && !empty($dict['Reservation Details']) && $this->http->XPath->query("//*[{$this->contains($dict['Reservation Details'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]extendedstay\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
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
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation No'))}]", null, false, "/^{$this->opt($this->t('Confirmation No'))}[ ]*\#[ ]*([A-Z\d]+)$/"))
            ->traveller($this->http->FindSingleNode("//td[{$this->eq($this->t('Guest Name'))}]/following-sibling::*[normalize-space()][1]", null, false, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/"), true);

        $h->hotel()
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Details'))}]/ancestor::table[1]/preceding-sibling::table[1]/descendant::td[1]/descendant::text()[normalize-space()][2]"))
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Details'))}]/ancestor::table[1]/preceding-sibling::table[1]/descendant::td[1]/descendant::text()[normalize-space()][1]"));

        $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Details'))}]/ancestor::table[1]/preceding-sibling::table[1]/descendant::td[2]//descendant::text()[{$this->eq($this->t('Phone'))}]/following::text()[normalize-space()][1]", null, false, "/^([\+\(\-\)\d ]+)$/");

        if ($phone !== null){
            $h->hotel()
                ->phone($phone);
        }

        $checkInDate = $this->http->FindSingleNode("//td[{$this->eq($this->t('Check In Date'))}]/following-sibling::*[normalize-space()][1]", null, false, "/^([[:alpha:]]+[ ]+\d{1,2}\,[ ]+\d{4})$/");
        $checkInTime = $this->http->FindSingleNode("//td[{$this->eq($this->t('Check In Time'))}]/following-sibling::*[normalize-space()][1]", null, false, "/^([0-9]{1,2}\:[0-9]{2}\:[0-9]{2}[ ]*A?P?M?)$/");

        if ($checkInDate !== null && $checkInTime !== null){
            $h->booked()
                ->checkIn(strtotime($checkInDate . ' ' . $checkInTime));
        } else {
            $h->booked()
                ->checkIn(strtotime($checkInDate));
        }

        $h->booked()
            ->checkOut(strtotime($this->http->FindSingleNode("//td[{$this->eq($this->t('Check Out Date'))}]/following-sibling::*[normalize-space()][1]", null, false, "/^([[:alpha:]]+[ ]+\d{1,2}\,[ ]+\d{4})$/")))
            ->guests($this->http->FindSingleNode("//td[{$this->eq($this->t('Guests'))}]/following-sibling::*[normalize-space()][1]", null, false, "/^([0-9]+)\/[0-9]+$/"));

        $infants = $this->http->FindSingleNode("//td[{$this->eq($this->t('Guests'))}]/following-sibling::*[normalize-space()][1]", null, false, "/^[0-9]+\/([0-9]+)$/");

        if ($infants !== null) {
            $h->booked()
                ->kids($infants);
        }

        //Start Room Info
        $r = $h->addRoom();

        $r->setType($this->http->FindSingleNode("//td[{$this->eq($this->t('Room Type'))}]/following-sibling::*[normalize-space()][1]"));

        $description = $this->http->FindSingleNode("//td[{$this->eq($this->t('Description'))}]/following-sibling::*[normalize-space()][1]");

        if ($description !== null){
            $r->setDescription($description);
        }

        $ratesInfo = $this->http->FindNodes("//tr[./th[{$this->eq($this->t('Date Range'))}] and count(./th) = 8 or ./td[{$this->eq($this->t('Date Range'))}] and count(./td) = 8]/following-sibling::tr/descendant::td[1]");

        $rateNum = 1;

        $rateArray = [];

        foreach ($ratesInfo as $rate){
            $rateValue = $this->http->FindSingleNode("//tr[./th[{$this->eq($this->t('Date Range'))}] and count(./th) = 8 or ./td[{$this->eq($this->t('Date Range'))}] and count(./td) = 8]/following-sibling::tr[$rateNum]/descendant::td[7]");

            if ($rate !== null && $rateValue !== null){
               $rateArray[] =  $rate . ': ' . $rateValue;
            }

            $rateNum++;
        }

        if (!empty($rateArray)){
            $r->setRate(implode(". ", $rateArray));
        }
        //End Room Info


        $priceInfo = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total'))}]/following-sibling::td[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>\d[\d\.\,\']*)$/", $priceInfo, $m) ||
            preg_match("/^(?<price>\d[\d\.\,\']*)\s*(?<currency>\D{1,3})$/", $priceInfo, $m) ) {
            $currency = $m['currency'];

            $h->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['price'], $currency));

            $costArray = [];

            $costs = $this->http->FindNodes("//tr[./th[{$this->eq($this->t('Date Range'))}] and count(./th) = 8 or ./td[{$this->eq($this->t('Date Range'))}] and count(./td) = 8]/following-sibling::tr/descendant::td[5]");

            foreach ($costs as $cost) {
                if (preg_match("/^\D{1,3}\s*(\d[\d\.\,\']*)$/", $cost, $m)){
                    $costArray[] = PriceHelper::parse($m[1], $currency);
                }
            }

            if (!empty($costArray)){
                $h->price()
                    ->cost(array_sum($costArray));
            }

            $taxArray = [];

            $taxes = $this->http->FindNodes("//tr[./th[{$this->eq($this->t('Date Range'))}] and count(./th) = 8 or ./td[{$this->eq($this->t('Date Range'))}] and count(./td) = 8]/following-sibling::tr/descendant::td[6]");

            foreach ($taxes as $tax) {
                if (preg_match("/^\D{1,3}\s*(\d[\d\.\,\']*)$/", $tax, $m)
                    || preg_match("/^(\d[\d\.\,\']*)\s*\D{1,3}$/", $tax, $m)){

                    $taxArray[] = PriceHelper::parse($m[1], $currency);
                }
            }

            if (!empty($taxArray)){
                $h->price()
                    ->tax(array_sum($taxArray));
            }

            $feeNodes = $this->http->XPath->query("//tr[./th[{$this->eq($this->t('Amount'))}] and count(./th) = 2]/following-sibling::tr[preceding-sibling::tr[{$this->starts($this->t('Estimated Room Rent Charges'))}] and following-sibling::tr[{$this->starts($this->t('Total'))}]]");

            foreach ($feeNodes as $feeNode){
                $feeName = $this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $feeNode);
                $feeValue = $this->http->FindSingleNode("./descendant::td[normalize-space()][2]", $feeNode);

                if ($feeName !== null && $feeValue !== null
                    && (preg_match("/^\D{1,3}\s*(\d[\d\.\,\']*)$/", $feeValue, $m)
                        || preg_match("/^(\d[\d\.\,\']*)\s*\D{1,3}$/", $feeValue, $m))){
                    $h->price()
                        ->fee($feeName, PriceHelper::parse($m[1], $currency));
                }
            }
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy'))}]/following::text()[normalize-space()][1][following::text()[{$this->eq($this->t('General Policy'))}][1]]");

        if ($cancellation !== null) {
            $h->general()
                ->cancellation($cancellation);
        }

        $this->detectDeadLine($h, $checkInDate);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s, '/'));
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

    private function detectDeadLine(Hotel $h, $checkInDate)
    {
        if (empty($cancellation = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^{$this->opt($this->t('Reservations must be cancelled by'))}[ ]+([0-9]{1,2}[AaPp]+[Mm])[ ]+{$this->opt($this->t('on the day of arrival to avoid cancellation fees'))}$/", $cancellation, $m)) {
            $h->booked()
                ->deadline(strtotime($checkInDate . $m[1]));
        }
    }


    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
