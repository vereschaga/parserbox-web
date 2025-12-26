<?php

namespace AwardWallet\Engine\singaporeair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightRescheduledPlain extends \TAccountChecker
{
    public $mailFiles = "singaporeair/it-17114013.eml";
    private $detectFrom = "@singaporeair.com.sg";
    private $detectSubject = [
        "en" => "Your Flight Has Been Rescheduled - Booking Ref: TB2T66",
    ];
    private $detectCompany = 'singaporeair.com';
    private $detectBody = [
        "en" => "has been rescheduled",
    ];

    private static $dictionary = [
        "en" => [],
    ];

    private $lang = "en";

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if (strpos($this->http->Response['body'], $detectBody) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->flight($email);

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

    private function flight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re("#Booking ref:\s+([A-Z\d]+)#", $this->http->Response['body']), 'Booking ref');

        // Segments
        $s = $f->addSegment();

        if (preg_match("#has been rescheduled to\s+(?<al>[A-Z\d]{2})(?<fn>\d{1,5})\s*/\s*(?<dCode>[A-Z]{3})\s*-\s*(?<aCode>[A-Z]{3})\s+departing\s+(?<date>.+?\d+:\d+)#", strip_tags($this->http->Response['body']), $m)) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn']);

            $s->departure()
                ->code($m['dCode'])
                ->date($this->normalizeDate($m['date']));

            $s->arrival()
                ->code($m['aCode'])
                ->noDate();
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //		$this->http->log($str);
        $in = [
            "#^\s*(\d+)\s+([^\s\d]+)\s+(\d{4})\s+at\s+(\d+:\d+)\s*$#", //13 Feb 2019 at 15:15
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
