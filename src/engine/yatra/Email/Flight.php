<?php

namespace AwardWallet\Engine\yatra\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "yatra/it-127804721.eml";
    public $subjects = [
        'Cancellation details for Yatra Ref',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
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
        if ($this->http->XPath->query("//text()[normalize-space()='Team Yatra']")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Your flight booking has been')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('details for booking reference number'))}]")->length > 0;
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

        $f->general()
            ->travellers(array_unique($this->http->FindNodes("//img[contains(@src, 'plane-icon')]/ancestor::tr[1]/descendant::td[normalize-space()][last()]/descendant::text()[normalize-space()]")), true)
            ->status($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]/following::text()[starts-with(normalize-space(), 'Your flight booking has been')]", null, true, "/{$this->opt($this->t('Your flight booking has been'))}\s*(.+)\./"))
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'booking reference number')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('booking reference number'))}\s*(\d{10,})/"));

        if (!empty($f->getStatus()) && $f->getStatus() == 'cancelled') {
            $f->general()
                ->cancelled();
        }

        $nodes = $this->http->XPath->query("//img[contains(@src, 'plane-icon')]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineText = $this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root);

            if (preg_match("/([A-Z\d]{2})\-(\d{4})/", $airlineText, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $flightText = $this->http->FindSingleNode("./descendant::td[normalize-space()][1]/following-sibling::td[1]", $root);
            //Mumbai - Nagpur Wed, 15 Dec, 2021
            if (preg_match("/^\s*(.+)\s*\-\s*(.+)\s+(\w+\,\s*\d+\s*\w+\,\s*\d{4})\s*$/su", $flightText, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->noCode()
                    ->day($this->normalizeDate($m[3]));

                $s->arrival()
                    ->name($m[2])
                    ->noCode()
                    ->noDate();
            }
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
