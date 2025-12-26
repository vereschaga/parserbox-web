<?php

namespace AwardWallet\Engine\yatra\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightCancelled extends \TAccountChecker
{
    public $mailFiles = "yatra/it-129990609.eml";
    public $subjects = [
        'Flight booking has been cancelled for',
        'Flight cancellation request received for',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your flight booking has been cancelled'                            => ['Your flight booking has been cancelled', 'Your Flight Cancellation Request'],
            'As per your action we have cancelled flight booking for following' => ['As per your action we have cancelled flight booking for following', 'As per your action we have taken the cancellation request for following'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@yatra.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'yatra')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Your flight booking has been cancelled'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('As per your action we have cancelled flight booking for following'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]yatra\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        if ($this->http->XPath->query("//text()[normalize-space()='Your flight booking has been cancelled']")->length > 0) {
            $f->general()
                ->cancelled()
                ->status('cancelled');
        } else {
            $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Flight')]", null, true, "/{$this->opt($this->t('Your Flight'))}\s*(\D+)/");

            if (!empty($status)) {
                $f->general()
                    ->status($status);
            }
        }

        $f->general()
            ->travellers(array_unique($this->http->FindNodes("//text()[normalize-space()='Sector']/ancestor::table[1]/descendant::tr[not(contains(normalize-space(), 'Sector'))]/descendant::td[3]")), true)
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Ref. :')]", null, true, "/{$this->opt($this->t('Booking Ref. :'))}\s*(\d{6,})/"))
            ->date(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking date :')]", null, true, "/{$this->opt($this->t('Booking date :'))}\s*(.+)/")));

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Sector']/ancestor::table[1]/descendant::tr[not(contains(normalize-space(), 'Sector'))]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root, true, "/\s([A-Z\d]{2})\s*$/"))
                ->noNumber();

            $flightText = $this->http->FindSingleNode("./descendant::td[normalize-space()][2]", $root);

            if (preg_match("/\((.+)\s*\-\s*(.+)\)/su", $flightText, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->date(strtotime($this->http->FindSingleNode("./descendant::td[normalize-space()][6]", $root)))
                    ->noCode();

                $s->arrival()
                    ->name($m[2])
                    ->noCode()
                    ->noDate();
            }

            $s->setConfirmation($this->http->FindSingleNode("./descendant::td[normalize-space()][5]", $root, true, "/^([A-Z\d]{6})$/"));
            $f->setTicketNumbers($this->http->FindNodes("./descendant::td[normalize-space()][4]", $root, "/^([A-Z\d]{6,})$/"), false);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

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
            // Wed, 15 Dec, 2021
            "#^\w+\,\s*(\d+)\s*(\w+)\,\s*(\d{4})$#i",
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
}
