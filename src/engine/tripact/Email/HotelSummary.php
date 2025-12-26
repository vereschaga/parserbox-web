<?php

namespace AwardWallet\Engine\tripact\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelSummary extends \TAccountChecker
{
    public $mailFiles = "tripact/it-121389811.eml, tripact/it-123049766.eml";
    public $subjects = [
        '/(?:Canceled|Confirmed)\s*\-.+\s*\|\D+\s*\([A-Z\d]+\)/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tripactions.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'TripActions Inc')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Hotels Summary'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your room'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tripactions.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $confNo = $this->http->FindSingleNode("//text()[normalize-space()='Hotel confirmation:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Hotel confirmation:'))}\s*(\d+)/");

        if (!empty($confNo)) {
            $h->general()
                ->confirmation($confNo);
        } else {
            $h->general()
                ->noConfirmation();
        }

        if ($this->http->XPath->query("//text()[normalize-space()='Cancellation details']")->length > 0) {
            $h->general()
                ->status('cancelled')
                ->cancelled();
        }

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Guest:')]/ancestor::div[1]", null, true, "/{$this->opt($this->t('Guest:'))}\s*(.+)/"), true);

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hotels Summary')]/following::img[1]/following::text()[string-length()>3][1]"))
            ->address($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hotels Summary')]/following::img[1]/following::text()[string-length()>3][2]/ancestor::a[1]"))
            ->phone($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hotels Summary')]/following::img[1]/following::text()[string-length()>3][2]/ancestor::a[1]/following::text()[string-length()>5][1]"));

        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-in:')]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-out:')]/following::text()[normalize-space()][1]")))
            ->rooms($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Number of rooms')]/following::text()[normalize-space()][1]"));

        $roomType = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your room')]/following::text()[string-length()>3][1]");
        $roomDescription = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your room')]/following::text()[string-length()>3][2]");
        $rate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Price per night')]/following::text()[normalize-space()][1]/ancestor::div[1]", null, true, "/\s*([\d\.\,]+\s*[A-Z]{3})/");

        if (!empty($roomType) || !empty($roomDescription)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($roomDescription)) {
                $room->setDescription($roomDescription);
            }

            if (!empty($rate)) {
                $room->setRate($rate . ' / night');
            }
        }

        $cost = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Subtotal')]/following::text()[normalize-space()][1]");

        if (!empty($cost)) {
            $h->price()
                ->cost(PriceHelper::cost($cost, ',', '.'));
        }

        $tax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Taxes')]/following::text()[normalize-space()][1]");

        if (!empty($tax)) {
            $h->price()
                ->tax(PriceHelper::cost($tax, ',', '.'));
        }

        $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total')]/following::text()[normalize-space()][1]/ancestor::div[1]");

        if (preg_match("/^([\d\.\,]+)\s*([A-Z]{3})/", $price, $m)) {
            $h->price()
                ->total(PriceHelper::cost($m[1], ',', '.'))
                ->currency($m[2]);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (preg_match("/(?:Canceled|Confirmed)\s*\-.+\s*\|\D+\s*\(([A-Z\d]+)\)/", $parser->getSubject(), $m)) {
            $email->ota()->confirmation($m[1]);
        }

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

    private function normalizeDate($date)
    {
        $in = [
            '#^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*at\s*([\d\:]+\s*A?P?M)$#', //Sun, Nov 7, 2021 at 3:00PM
        ];
        $out = [
            '$2 $1 $3, $4',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }
}
