<?php

namespace AwardWallet\Engine\slh\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmed extends \TAccountChecker
{
    public $mailFiles = "slh/it-172874228.eml";
    public $subjects = [
        'Your INVITED booking is confirmed:',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@emails.slh.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Small Luxury Hotels of the World')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Number:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('If you need to make a change to your reservation or have any pre-stay queries'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]emails\.slh\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Reservation Number:']/ancestor::tr[1]/descendant::td[2]"))
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(.+)\,/"));

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your booking at')]/following::text()[normalize-space()][1]"));

        $hotelAdressText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hotel')]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/{$h->getHotelName()}\s*\,\s*(.+)/", $hotelAdressText, $m)) {
            $h->hotel()
                ->address($m[1]);
        }

        $checkIn = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-in:')]/ancestor::tr[1]/descendant::td[2]");
        $checkOut = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-out:')]/ancestor::tr[1]/descendant::td[2]");

        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut));

        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Reservation:')]/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\d+)\s*{$this->opt($this->t('room'))}/"));
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
        $in = [
            "#^(\d+)(?:th|nd)\s*(\w+)\s*(\d{4})$#u", //06th October 2022
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
