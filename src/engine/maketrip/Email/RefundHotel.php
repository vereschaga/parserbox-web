<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RefundHotel extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-172261677.eml";
    public $subjects = [
        'Refund Initiated for Your Booking at ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@makemytrip.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Refund Initiated by MakeMyTrip'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Refund Initiated for Cancelled Booking'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Property Details'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]makemytrip\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking ID:']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)$/"));

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Refund Initiated for Cancelled Booking')]")->length > 0) {
            $h->general()
                ->cancelled()
                ->status('Cancelled');
        }

        $hotelName = $this->http->FindSingleNode("//text()[normalize-space()='Property Details']/following::text()[normalize-space()][1]");

        if (!empty($hotelName)) {
            $hotel = explode(',', $hotelName);
            $h->hotel()
                ->name($hotel[0])
                ->address($hotel[1]);
        }

        $bookedDays = $this->http->FindSingleNode("//text()[normalize-space()='Property Details']/following::text()[normalize-space()][2]");

        if (!empty($bookedDays)) {
            $booked = explode(' - ', $bookedDays);
            $h->booked()
                ->checkIn($this->normalizeDate($booked[0]))
                ->checkOut($this->normalizeDate($booked[1]));
        }

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Property Details']/following::text()[normalize-space()][3]");

        if (!empty($roomType)) {
            $room = $h->addRoom();

            $room->setType($roomType);
        }

        $guestInfo = $this->http->FindSingleNode("//text()[normalize-space()='Property Details']/following::text()[contains(normalize-space(), 'Adult')][1]");

        if (preg_match("/^\s*(\d+)\s*{$this->opt($this->t('Adults'))}/", $guestInfo, $m)) {
            $h->booked()
                ->guests($m[1]);
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total Refund']/following::text()[normalize-space()][1]");

        if (preg_match("/^\s*(\S)\s*([\d\.\,]+)\s*$/u", $price, $m)) {
            $currency = $this->normalizeCurrency($m[1]);
            $h->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m[2], $currency));
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

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));

        $in = [
            "#^(\d+)\-(\w+)\-(\d{4})$#u", //01-Oct-2022
        ];
        $out = [
            "$1 $2 $3",
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

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs', '₹'],
            'AUD' => ['AU $'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
