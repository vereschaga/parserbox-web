<?php

namespace AwardWallet\Engine\tripla\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelConfirmation extends \TAccountChecker
{
	public $mailFiles = "tripla/it-813313807.eml, tripla/it-816295144.eml, tripla/it-817009078.eml, tripla/it-818109351.eml, tripla/it-820368649.eml";
    public $subjects = [
        '[Confirmation]',
        '[Cancellation]',
        '【取消預約】',
        '【確認預約】'
    ];

    public $lang = '';

    public $detectLang = [
        'ja' => ['預約號碼'],
        'en' => ['Reservation number'],
    ];

    public static $dictionary = [
        'en' => [
            'detectPhrase' => ['The contents of the following reservation have been received.', 'The contents of the following reservation have been canceled.'],
            'Total price (Tax included)' => ['Total price (Tax included)', 'Total price（Tax included)'],
            'Payment amount (Tax included)' => ['Payment amount (Tax included)', 'Payment amount（Tax included)'],
            'Room type' => 'Room type',
            'Hotel:' => 'Hotel:',
            'cancelledPhrase' => ['The contents of the following reservation have been canceled.'],
        ],
        'ja' => [
            'detectPhrase' => ['以下為您的詳細預約內容。', '下述的預約內容已被取消。'],
            'Hotel:' => '飯店:',
            'Address:' => '地址:',
            'Room type' => '客房類型',
            'Reservation number' => '預約號碼',
            'TEL:' => '電話號:',
            'Guest name' => '入住者姓名',
            'Cancellation Policy:' => '取消政策:',
            'The contents of the following reservation have been received.' => '以下為您的詳細預約內容。',
            'Total price (Tax included)' => '總額（已含稅)',
            'Payment amount (Tax included)' => ['剩餘需付金額（已含稅)', '總計支付金額（已含稅)'],
            'Points discount' => '訂金金額（已含稅)',
            'Number of Rooms' => '客房數',
            'Number of Guests' => '預約人數',
            'adults' => '大人',
            'children' => '孩童',
            'Check-in date' => '入住日',
            'Check-out date' => '退房日',
            'cancelledPhrase' => ['下述的預約內容已被取消。'],
         ]
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tripla.jp') !== false) {
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
            $this->http->XPath->query("//text()[{$this->contains(['tripla'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['detectPhrase']) && $this->http->XPath->query("//*[{$this->contains($dict['detectPhrase'])}]")->length > 0
                && !empty($dict['Room type']) && $this->http->XPath->query("//*[{$this->contains($dict['Room type'])}]")->length > 0
                && !empty($dict['Hotel:']) && $this->http->XPath->query("//*[{$this->contains($dict['Hotel:'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tripla\.jp$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->assignLang();

        $this->HotelConfirmation($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function HotelConfirmation(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->noConfirmation();

        $h->obtainTravelAgency();
        $h->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation number'))}]", null, true, "/{$this->t('Reservation number')}\s*\:\s*([A-Z\d\-]+)$/"), $this->t('Reservation number'));

        if ($this->http->FindSingleNode("//text()[{$this->eq($this->t('The contents of the following reservation have been received.'))}]")){
            $h->setStatus($this->t('Confirmed'));
        } else if ($this->http->XPath->query("//text()[{$this->eq($this->t('cancelledPhrase'))}]")->length > 0){
            $h->setStatus($this->t('Cancelled'));
            $h->setCancelled(true);
        }

        $h->setTravellers($this->http->FindNodes("//text()[{$this->contains($this->t('Guest name'))}]", null, "/{$this->t('Guest name')}\:\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u"), true);

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel:'))}]", null, false, "/{$this->t('Hotel:')}\s*(.+)$/"))
            ->address($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Address:'))}])[1]", null, false, "/{$this->t('Address:')}\s*(.+)$/"))
            ->phone($this->http->FindSingleNode("//text()[{$this->contains($this->t('TEL:'))}]", null, false, "/{$this->t('TEL:')}\s*([\d\s\+\(\)\-]+)$/"));

        $checkinDate = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Check-in date'))}])[1]", null, false, "/{$this->t('Check-in date')}\:\s*(\d{4}\/\d+\/\d+)$/");
        $checkinTime = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Check-in time'))}])[1]", null, false, "/{$this->t('Check-in time')}\:\s*([\d\:]+\s*A?P?M?)$/");

        if ($checkinDate !== null && $checkinTime !== null){
            $h->booked()
                ->checkIn(strtotime($checkinDate . ' ' . $checkinTime));
        } else if ($checkinDate !== null) {
            $h->booked()
                ->checkIn(strtotime($checkinDate));
        }

        $checkoutDate = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Check-out date'))}])[1]", null, false, "/{$this->t('Check-out date')}\:\s*(\d{4}\/\d+\/\d+)$/");
        $checkoutTime = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Check-out time'))}])[1]", null, false, "/{$this->t('Check-out time')}\:\s*([\d\:]+\s*A?P?M?)$/");

        if ($checkoutDate !== null && $checkoutTime !== null){
            $h->booked()
                ->checkOut(strtotime($checkoutDate . ' ' . $checkoutTime));
        } else if ($checkoutDate !== null) {
            $h->booked()
                ->checkOut(strtotime($checkoutDate));
        }
        
        $roomsInfo = $this->http->XPath->query("//text()[{$this->contains($this->t('Room type'))}]");

        foreach ($roomsInfo as $room) {
            $r = $h->addRoom();

            $roomInfo = $this->http->FindSingleNode("./ancestor::*[1]", $room, false, "/^{$this->t('Room type')}\:(.+)$/");

            if (strlen($roomInfo) < 250){
                $r->setType($roomInfo);
            } else {
                $r->setDescription($roomInfo);
            }
        }

        $roomsCount = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Number of Rooms'))}]", null, false, "/{$this->t('Number of Rooms')}\:\s*(\d+)$/");

        if ($roomsCount !== null) {
            $h->booked()
                ->rooms($roomsCount);
        }

        $guestInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Number of Guests'))}][following::text()[{$this->contains($this->t('Number of Rooms'))}]] ", null, true, "/[\(\（]*(\d+)\s*{$this->t('adults')}\/\d+\s*{$this->t('children')}\)$/");

        if ($guestInfo !== null) {
            $h->booked()
                ->guests($guestInfo);
        }

        $kidsInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Number of Guests'))}][following::text()[{$this->contains($this->t('Number of Rooms'))}]] ", null, true, "/[\(\（]\d+\s*{$this->t('adults')}\/(\d+)\s*{$this->t('children')}\)$/");

        if ($kidsInfo !== null) {
            $h->booked()
                ->kids($kidsInfo);
        }

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Payment amount (Tax included)'))}]", null, true, "/{$this->opt($this->t('Payment amount (Tax included)'))}\:\s*(\D{1,3}\s*\d[\d\.\,\`]*)$/");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>\d[\d\.\,\`]*)$/", $priceInfo, $m)
            || preg_match("/^(?<price>\d[\d\.\,\`]*)\s*(?<currency>\D{1,3})$/", $priceInfo, $m) ) {
            $currency = $this->normalizeCurrency($m['currency']);

            $h->price()
                ->total(PriceHelper::parse($m['price'], $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total price (Tax included)'))}]", null, true, "/{$this->opt($this->t('Total price (Tax included)'))}\:\s*\D{1,3}\s*(\d[\d\.\,\`]*)$/");

            if ($cost !== null) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $discount = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Points discount'))}]", null, true, "/{$this->opt($this->t('Points discount'))}\:\s*\D{1,3}\s*(\d[\d\.\,\`]*)$/");

            if ($discount !== null) {
                $h->price()
                    ->discount(PriceHelper::parse($discount, $currency));
            }
        }

        $cancellationPolicy = $this->http->FindNodes("(//text()[{$this->eq($this->t('Cancellation Policy:'))}])[1]/following-sibling::text()");

        if ($cancellationPolicy !== null) {
            $h->general()
                ->cancellation(implode(' ', $cancellationPolicy));
        }

        $this->detectDeadLine($h);
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $dBody) {
            foreach ($dBody as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

        if (preg_match("/(\d+) days before check-in: free of charge/", $cancellation, $m)
            || preg_match("/入住前(\d+)日\:\s*0\%的費用/", $cancellation, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days');
        }
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s, '/'));
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
