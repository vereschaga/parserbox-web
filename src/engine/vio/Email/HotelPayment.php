<?php
namespace AwardWallet\Engine\vio\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelPayment extends \TAccountChecker
{
    public $mailFiles = "vio/it-796250192.eml, vio/it-807526071.eml, vio/it-832575435.eml";

    public $subjects = [
        'Vio.com Payment successful. Your stay at',
        'Vio.com Booking confirmed. Your stay at',
        'Vio.com Payment failed. Your stay at'
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Payment successful' => ['Your booking is confirmed' , 'Payment successful', 'Your bookings are confirmed', 'Scheduled payment failed'],
            'Booking' => 'Booking'
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail.vio.com') !== false) {
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
            $this->http->XPath->query("//a/@href[{$this->contains(['vio.com'])}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->contains(['Vio.com'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Payment successful']) && $this->http->XPath->query("//*[{$this->contains($dict['Payment successful'])}]")->length > 0
                && !empty($dict['Booking']) && $this->http->XPath->query("//*[{$this->contains($dict['Booking'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.vio\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $segmentsInfo = $this->http->XPath->query("//text()[normalize-space() = 'Your reservation']/ancestor::td[7]");

        if ($segmentsInfo->length > 1) {
            $this->HotelPayment($email, $segmentsInfo);
        } else {
            $this->HotelPayment2($email);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        return $email;
    }

    public function HotelPayment(Email $email, \DOMNodeList $segmentsInfo)
    {
        foreach ($segmentsInfo as $segment){
            $h = $email->add()->hotel();

            if ($this->http->XPath->query("//text()[{$this->contains($this->t('Payment failed'))}]")->length > 0){
                $h->setStatus('Payment failed');
            }

            $h->general()
                ->confirmation($this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Booking ID'))}][3]/descendant::text()[3]", $segment, true, "/^([A-Z\d\-\#]+)$/"), "Booking ID", null, ['regexp' => "/^[\w\-\/\\\.\?\#]+$/u"]);

            $traveller = $this->http->FindSingleNode("./descendant::tr[{$this->starts($this->t('Reserved for'))}][3]/following::tr[1]", $segment, false, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u");
            $h->addTraveller($traveller, true);

            $h->hotel()
                ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Get directions'))}]/preceding::a[1]"))
                ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Get directions'))}]/preceding::tr[1]"));

            $priceInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price details'))}]/ancestor::div[2]/following-sibling::div[{$this->starts($this->t('Total'))}]", null, true, "/{$this->t('Total')}\s*(\D{1,3}\s*[\d\.\,\`]+)$/");

            if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>[\d\.\,\']+)$/", $priceInfo, $m)) {
                $h->price()
                    ->total(PriceHelper::parse($m['price'], $m['currency']))
                    ->currency($m['currency']);

                $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price details'))}]/following::div[1]", null, true, "/.*\s+\D{1,3}\s*([\d\.\,\`]+)$/");

                if ($cost !== null) {
                    $h->price()
                        ->cost(PriceHelper::parse($cost, $m['currency']));
                }

                $feesNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Price details'))}]/ancestor::div[2]/following-sibling::div[position() > 1 and position() < 4 and not({$this->contains($this->t('Total'))})]");

                if ($feesNodes !== null) {
                    foreach ($feesNodes as $root) {
                        $feeName = $this->http->FindSingleNode("./descendant::div[2]", $root);
                        $feeSum = $this->http->FindSingleNode("./descendant::div[4]", $root, true, '/^\D{1,3}\s*([\d\.\,\`]+)/');

                        if ($feeName !== null && $feeSum !== null) {
                            $h->price()
                                ->fee($feeName, PriceHelper::parse($feeSum, $m['currency']));
                        }
                    }
                }
            }

            $checkinInfo = $this->http->FindSingleNode("./descendant::tr[{$this->eq($this->t('Check-in'))}]/following::tr[1]", $segment);

            if (preg_match("/^(?<date>\w+\s*\d+\,\s*\d{4})\s*from\s*(?<time>[\d\:]+)$/", $checkinInfo, $m)){
                $h->booked()
                    ->checkIn(strtotime($m['date'] . $m['time']));
            }

            $checkoutInfo = $this->http->FindSingleNode("./descendant::tr[{$this->eq($this->t('Check-out'))}]/following::tr[1]", $segment);

            if (preg_match("/^(?<date>\w+\s*\d+\,\s*\d{4})\s*(?:before|from)\s*(?<time>[\d\:]+)$/", $checkoutInfo, $m)){
                $h->booked()
                    ->checkOut(strtotime($m['date'] . $m['time']));
            }

            $roomRate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price details'))}]/following::div[1]/descendant::div[2]", null, true, '/^(\D{1,3}\s*[\d\.\,\`]+)\s*x\s*\d*\s*nights?\s*x\s*\d*\s*rooms?$/');

            if ($roomRate !== null) {
                $r = $h->addRoom();
                $r->setRate($roomRate . '/night');
            }

            $roomsCount = $this->http->FindSingleNode("./descendant::tr[{$this->eq($this->t('Your reservation'))}]/following::tr[1]", $segment, false, "/(\d+)\s*rooms?/");
            if ($roomsCount !== null){
                $h->booked()
                    ->rooms($roomsCount);
            }

            $phoneInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Call property'))}]/ancestor::div[3][1]",null, false, '/^([\d\s\+\(\)\-]+)\s*Call\s*property$/');
            if ($phoneInfo !== null){
                $h->hotel()
                    ->phone($phoneInfo);
            }

            $guestInfo = $this->http->FindSingleNode("./descendant::tr[{$this->eq($this->t('Guests'))}]/following::tr[1]", $segment, false, '/(\d+)\s*adults?\s*/');
            if ($guestInfo !== null){
                $h->booked()
                    ->guests($guestInfo);
            }

            $kidsInfo = $this->http->FindSingleNode("./descendant::tr[{$this->eq($this->t('Guests'))}]/following::tr[1]", $segment, false, '/adults?\s*(\d+)\s*child/');
            if ($kidsInfo !== null){
                $h->booked()
                    ->kids($kidsInfo);
            }

            $cancellationPolicy = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('How much will it cost to cancel this booking?'))}]/ancestor::div[2]/following-sibling::div"));

            if ($cancellationPolicy !== null){
                $h->general()
                    ->cancellation($cancellationPolicy);
            }

            $this->detectDeadLine($h);
        }
    }
    public function HotelPayment2(Email $email)
    {
        $h = $email->add()->hotel();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Payment failed'))}]")->length > 0){
            $h->setStatus('Payment failed');
        }

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking ID'))}]/preceding::tr[1]", null, true, "/^([A-Z\d\-\#]+)$/"), "Booking ID", null, ['regexp' => "/^[\w\-\/\\\.\?\#]+$/u"]);

        $traveller = $this->http->FindNodes("//text()[{$this->eq($this->t('Reserved for'))}]/following::tr[1]", null, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u");
        $h->setTravellers(array_unique($traveller), true);

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Get directions'))}]/preceding::a[1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Get directions'))}]/preceding::tr[1]"));

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price details'))}]/ancestor::div[2]/following-sibling::div[{$this->starts($this->t('Total'))}]", null, true, "/{$this->t('Total')}\s*(\D{1,3}\s*[\d\.\,\`]+)$/");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>[\d\.\,\']+)$/", $priceInfo, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price details'))}]/following::div[1]", null, true, "/.*\s+\D{1,3}\s*([\d\.\,\`]+)$/");

            if ($cost !== null) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $feesNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Price details'))}]/ancestor::div[2]/following-sibling::div[position() > 1 and position() < 4 and not({$this->contains($this->t('Total'))})]");

            if ($feesNodes !== null) {
                foreach ($feesNodes as $root) {
                    $feeName = $this->http->FindSingleNode("./descendant::div[2]", $root);
                    $feeSum = $this->http->FindSingleNode("./descendant::div[4]", $root, true, '/^\D{1,3}\s*([\d\.\,\`]+)/');

                    if ($feeName !== null && $feeSum !== null) {
                        $h->price()
                            ->fee($feeName, PriceHelper::parse($feeSum, $m['currency']));
                    }
                }
            }
        }

        $checkinInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/following::tr[1]");

        if (preg_match("/^\w+\s*\,\s*(?<date>\w+\s*\d+\,\s*\d{4})\s*from\s*(?<time>[\d\:]+)$/", $checkinInfo, $m)){
            $h->booked()
                ->checkIn(strtotime($m['date'] . $m['time']));
        }

        $checkoutInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/following::tr[1]");

        if (preg_match("/^\w+\s*\,\s*(?<date>\w+\s*\d+\,\s*\d{4})\s*(?:before|from)\s*(?<time>[\d\:]+)$/", $checkoutInfo, $m)){
            $h->booked()
                ->checkOut(strtotime($m['date'] . $m['time']));
        }

        $r = $h->addRoom();

        $roomRate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price details'))}]/following::div[1]/descendant::div[2]", null, true, '/^(\D{1,3}\s*[\d\.\,\`]+)\s*x\s*\d*\s*nights?\s*x\s*\d*\s*rooms?$/');

        if ($roomRate !== null) {
            $r->setRate($roomRate . '/night');
        }

        $roomsCount = $this->http->FindNodes("//text()[{$this->eq($this->t('Booking overview'))}]/following::div[1]/descendant::td[5]", null, "/(\d+)\s*rooms?/");
        if (!empty($roomsCount)){
            $h->booked()
                ->rooms(array_sum($roomsCount));
        }

        $phoneInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Call property'))}]/ancestor::div[3][1]",null, false, '/^([\d\s\+\(\)\-]+)\s*Call\s*property$/');
        if ($phoneInfo !== null){
            $h->hotel()
                ->phone($phoneInfo);
        }

        $guestInfo = $this->http->FindNodes("//text()[{$this->eq($this->t('Guests'))}]/following::tr[1]", null, '/(\d+)\s*adults?\s*/');
        if (!empty($guestInfo)){
            $h->booked()
                ->guests(array_sum($guestInfo));
        }

        $kidsInfo = $this->http->FindNodes("//text()[{$this->eq($this->t('Guests'))}]/following::tr[1]", null, '/adults?\,?\s*(\d+)\s*child/');
        if (!empty($kidsInfo) && array_sum($kidsInfo) !== 0){
            $h->booked()
                ->kids(array_sum($kidsInfo));
        }

        $cancellationPolicy = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('How much will it cost to cancel this booking?'))}]/ancestor::div[2]/following-sibling::div"));

        if ($cancellationPolicy !== null){
            $h->general()
                ->cancellation($cancellationPolicy);
        }

        $this->detectDeadLine($h);
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellation = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Before\s*(\w+\s*\d+\s*\,\s*\d{4}\s*[\d\:]+\s*A?P?M?).+Free cancellation/", $cancellation, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1]));
        }

        if (preg_match("/This\s*rate\s*is\s*non\-refundable\./", $cancellation)
            || preg_match("/\s*hotel\s*reserves\s*the\s*right\s*to\s*cancel\s*your\s*reservation\s*and\s*you\s*may\s*be\s*charged\s*for\s*the\s*full\s*amount\.$/", $cancellation)) {
            $h->booked()
                ->nonRefundable();
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