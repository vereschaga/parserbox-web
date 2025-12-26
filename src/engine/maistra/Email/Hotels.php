<?php

namespace AwardWallet\Engine\maistra\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Hotels extends \TAccountChecker
{
    public $mailFiles = "maistra/it-107613293.eml";
    public $subjects = [
        '/\D+\: code[\s\-A-Z\d]+PH10849361/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'thousands' => '.',
            'decimals'  => ',',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@phobs.net') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'www.maistra.com')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'hello@maistra.hr')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Reservation code:')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation created on:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation holder:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]phobs\.net$/', $from) > 0;
    }

    public function ParseHotels(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Reservation holder:']/following::text()[normalize-space()='User:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('User:'))}\s*(\D+)$/"))
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Reservation code:']/ancestor::p[1]", null, true, "/{$this->opt($this->t('Reservation code:'))}\s*([A-Z-\d]{6,})/"), 'Reservation code')
            ->date(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Reservation code:']/following::text()[starts-with(normalize-space(), 'Reservation created on:')]", null, true, "/{$this->opt($this->t('Reservation created on:'))}\s*(.+)/")))
            ->cancellation($this->http->FindSingleNode("//text()[normalize-space()='Cancellation policy']/ancestor::tr[1]/following::tr[1]"));

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'RESERVATION CONFIRMATION -')]/ancestor::tr[1]/following::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'RESERVATION CONFIRMATION -')]/ancestor::tr[1]/following::text()[normalize-space()][1]/following::text()[normalize-space()][1]"))
            ->phone($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'RESERVATION CONFIRMATION -')]/ancestor::tr[1]/following::text()[normalize-space()][1]/following::text()[normalize-space()][2]", null, true, "/{$this->opt($this->t('Tel:'))}\s*([+][\d\s]+)/"));

        $dateIn = $this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Check in date:']/ancestor::*[1]", null, true, "/{$this->opt($this->t('Check in date:'))}\s*(.+)/"));
        $dateOut = $this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Check out date:']/ancestor::*[1]", null, true, "/{$this->opt($this->t('Check out date:'))}\s*(.+)/"));

        $timeIn = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-in time is after')]", null, true, "/{$this->opt($this->t('Check-in time is after'))}\s*([\d\:]+\s*A?P?M?)/");
        $timeOut = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-out time is before')]", null, true, "/{$this->opt($this->t('Check-out time is before'))}\s*([\d\:]+\s*A?P?M?)/");

        $h->booked()
            ->checkIn(strtotime($timeIn, $dateIn))
            ->checkOut(strtotime($timeOut, $dateOut));

        $guests_count = 0;
        $kids_count = 0;
        $nodes = $this->http->XPath->query("//text()[contains(normalize-space(), 'Accommodation type')]/ancestor::tr[1]/following-sibling::tr/descendant::td[normalize-space()][4]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $room = $h->addRoom();

            $room->setType($this->http->FindSingleNode("./descendant::td[normalize-space()][2]", $root))
                ->setDescription($this->http->FindSingleNode("./descendant::td[normalize-space()][3]", $root));

            $guests = $this->http->FindSingleNode("./descendant::td[normalize-space()][4]", $root, true, "/^\s*(\d+)\s*$/");

            if ($guests !== null) {
                $guests_count += $guests;
            }

            $kids = $this->http->FindSingleNode("../descendant::td[normalize-space()][5]", $root, true, "/^\s*(\d+)\s*$/");

            if ($kids !== null) {
                $kids_count += $kids;
            }
        }

        $h->booked()
            ->guests($guests_count)
            ->kids($kids_count);

        $total = $this->http->FindSingleNode("//text()[normalize-space()='TOTAL PRICE:']/ancestor::tr[1]/descendant::td[last()]", null, true, "/[A-Z]{3}\s*([\d\,\.]+)/");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='TOTAL PRICE:']/ancestor::tr[1]/descendant::td[last()]", null, true, "/([A-Z]{3})\s*[\d\,\.]+/");

        if (!empty($total) && !empty($currency)) {
            $h->price()
                ->total(PriceHelper::cost($total, $this->t('thousands'), $this->t('decimals')))
                ->currency($currency);
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotels($email);

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
            "#^(\d+)\.\s*(\w+)\,\s*(\d{4})$#", //02Jun
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/The reservation can be cancelled online no less than\s(\d+\s*day)\(s\)\s*prior to arrival/', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1], '-1 hour');
        }
    }
}
