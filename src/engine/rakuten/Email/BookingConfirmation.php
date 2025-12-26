<?php

namespace AwardWallet\Engine\rakuten\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "rakuten/it-632559523.eml, rakuten/it-632776933.eml";
    public $subjects = [
        'Rakuten Travel | Booking Confirmation (Booking ID:',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail.travel.rakuten.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Rakuten Travel')]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('Reservation details'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('Canceled reservation details'))}]")->length > 0)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Price summary'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.travel\.rakuten\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->hotelHTML($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function hotelHTML(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Reservation number']/following::text()[normalize-space()][1]", null, true, "/^\s*(\d+)(?:（|\s|$)/u"))
            ->travellers($this->http->FindNodes("//text()[normalize-space()='Guest information']/following::table[1]/descendant::div", null, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s\(|$)/"));

        $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation policy']/following::text()[normalize-space()][1]");

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total Price']/ancestor::tr[1]/descendant::div[1]");

        if (preg_match("/^(?<currency>\D)(?<total>[\d\.\,]+)/", $price, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $discount = $this->http->FindSingleNode("//text()[normalize-space()='Coupon Use']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Coupon Use'))}\s*\-\D{1,3}([\d\.\,]+)/su");

            if (!empty($discount)) {
                $h->price()
                    ->discount(PriceHelper::parse($discount, $m['currency']));
            }

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Subtotal']/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Subtotal'))}\s*\D{1,3}\s*([\d\.\,]+)/");

            if (!empty($cost)) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='Address']/preceding::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[normalize-space()='Address']/following::text()[normalize-space()][1]"));

        $phone = $this->http->FindSingleNode("//text()[normalize-space()='Phone']/following::text()[normalize-space()][1]");

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $checkIn = $this->http->FindSingleNode("//text()[normalize-space()='Check-in']/following::text()[normalize-space()][1]", null, true, "/^(\w+.*\)\s*[\d\:]+\s*a?p?m?)/i");
        $checkOut = $this->http->FindSingleNode("//text()[normalize-space()='Check-out']/following::text()[normalize-space()][1]", null, true, "/^(\w+.*\)\s*[\d\:]+\s*a?p?m?)/i");

        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut));

        $roomDescription = $this->http->FindSingleNode("//text()[normalize-space()='Room']/following::text()[normalize-space()][1]/ancestor::th[1]");

        if (!empty($roomDescription)) {
            $h->addRoom()->setDescription($roomDescription);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Canceled reservation details'))}]")->length > 0) {
            $h->general()
                ->cancelled()
                ->status('cancelled');

            return $email;
        }

        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of rooms'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/"))
            ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of guests (per room)'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)\s*adult/"));

        $kids = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of guests (per room)'))}]/following::text()[normalize-space()][1]", null, true, "/(\d+)\s*child/");

        if ($kids !== null) {
            $h->booked()
                ->kids($kids);
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
        //$this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\s*\(\D*\)\s*([\d\:]+)\s*$#u", //March 12, 2024 (Tue) 16:30
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\s*([\d\:]+\s*A?P?M)$#u", //March 08, 2024 11:59 PM
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

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

        if (preg_match("/Free cancellation until\s*(\w+\s*\d+\,.*A?P?M?)\(/", $cancellationText, $m)) {
            $h->booked()
                ->deadline($this->normalizeDate($m[1]));
        }

        if (preg_match("/^(\d+\s*day)\(s\)\s*before your stay\s*\(from\s*([\d\:]+)\)/u", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1], $m[2]);
        }
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
