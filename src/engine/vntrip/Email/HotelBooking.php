<?php

namespace AwardWallet\Engine\vntrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelBooking extends \TAccountChecker
{
    public $mailFiles = "vntrip/it-127993101.eml";
    public $subjects = [
        'Đặt phòng thành công',
    ];

    public $lang = 'vi';

    public static $dictionary = [
        "vi" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@vntrip.vn') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'VNTRIP')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Bạn có một đơn hàng tại VNTRIP với thông tin như sau:')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]vntrip\.vn$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Mã đơn hàng:']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6,}$)/"), 'Mã đơn hàng')
            ->cancellation($this->http->FindSingleNode("//text()[normalize-space()='Thông tin phòng']/ancestor::table[1]/descendant::tr[not(contains(normalize-space(), 'Thông tin phòng'))]/descendant::td[1]/descendant::text()[normalize-space()][3]"));

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='Ngày nhận phòng/ trả phòng']/preceding::text()[normalize-space()][2]"))
            ->address($this->http->FindSingleNode("//text()[normalize-space()='Ngày nhận phòng/ trả phòng']/preceding::text()[normalize-space()][1]"));

        $dateText = $this->http->FindSingleNode("//text()[normalize-space()='Ngày nhận phòng/ trả phòng']/following::text()[normalize-space()][1]");

        if (preg_match("/^(\d+\/\d+\/\d{4})[\s\D]+(\d+\/\d+\/\d{4})/", $dateText, $m)) {
            $h->booked()
                ->checkIn(strtotime(str_replace('/', '.', $m[1])))
                ->checkOut(strtotime(str_replace('/', '.', $m[2])));
        }

        $roomXpath = "//text()[normalize-space()='Thông tin phòng']/ancestor::table[1]/descendant::tr[not(contains(normalize-space(), 'Thông tin phòng'))]";

        if ($this->http->XPath->query("$roomXpath")->length == 1) {
            $room = $h->addRoom();

            $roomTypeText = $this->http->FindSingleNode($roomXpath . "/descendant::td[1]/descendant::text()[normalize-space()][1]");

            if (preg_match("/[x](\d)[\s\–]+(.+)/", $roomTypeText, $m)) {
                $h->booked()
                    ->rooms($m[1]);

                $room->setType($m[2]);
            }

            $roomDescriptionText = $this->http->FindSingleNode($roomXpath . "/descendant::td[1]/descendant::text()[normalize-space()][2]");

            if (!empty($roomDescriptionText)) {
                $room->setDescription(trim($roomDescriptionText, '()'));
            }

            $rate = $this->http->FindSingleNode($roomXpath . "/descendant::td[2]/descendant::text()[normalize-space()][1]");

            if (!empty($rate)) {
                $room->setRate($rate);
            }
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Tổng giá tiền (đã bao gồm thuế & phí)']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (preg_match("/^\s*([\d\.]+)\s*(\D)$/us", $total, $m)) {
            $h->price()
                ->total(PriceHelper::cost($m[1], '.', ','))
                ->currency($this->normalizeCurrency($m[2]));
        }

        $this->detectDeadLine($h);
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

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'VND' => ['₫'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/Không hoàn hủy/', $cancellationText, $m)) {
            $h->booked()->nonRefundable();
        }
    }
}
