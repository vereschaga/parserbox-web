<?php

namespace AwardWallet\Engine\hertz\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Carfirmation extends \TAccountChecker
{
    public $mailFiles = "hertz/statements/it-72893241.eml, hertz/statements/it-72904533.eml";

    public static $dictionary = [
        "en" => [
            'Dear '                                                      => ['Dear ', 'Hello '],
            'change preferences on your Hertz Gold Plus Rewards profile' => ['change preferences on your Hertz Gold Plus Rewards profile', 'change the preferences on your Hertz Gold Plus Rewards profile'],
        ],
    ];

    private $detectFrom = "reservations@emails.hertz.com";

    private $detectSubject = [
        "en" => "Hertz Carfirmation",
    ];
    private $detectCompany = "https://click.emails.hertz.com/";
    private $detectBody = [ //[1, 2, 3]
        // Your Reservation Details
        // Reserved Class: 	Fullsize
        // When You Arrive: 	Go to Gold zone
        "en1member" => ["Your Reservation Details", "Reserved Class:", "When You Arrive:"],
        //Rental Information
        //      Vehicle:              Color:
        //      TOYOTA COROLLA        BLACK
        "en2" => ["Rental Information", "Vehicle:", "Plate:"],
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $st = $email->add()->statement();

        $info = '';

        foreach ($this->detectBody as $key => $dBody) {
            if (
                $this->http->XPath->query("//text()[" . $this->eq($dBody[0]) . "]/following::td[not(.//td) and normalize-space()][1][" . $this->eq($dBody[1]) . "]/following::td[not(.//td) and normalize-space()][2][" . $this->eq($dBody[2]) . "]")->length > 0
                || $this->http->XPath->query("//text()[" . $this->eq($dBody[0]) . "]/following::text()[normalize-space()][1][" . $this->eq($dBody[1]) . "]/following::text()[normalize-space()][2][" . $this->eq($dBody[2]) . "]")->length > 0
            ) {
                $name = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t('Dear ')) . "])[1]", null, true,
                    "/" . $this->preg_implode($this->t("Dear ")) . "\s*([[:alpha:]][[:alpha:] \.\-]+),\s*$/");
                $st->addProperty('Name', $name);

                $this->lang = substr($key, 0, 2);
                $info = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Dear ')) . "]/preceding::td[not(.//td)][normalize-space()][position() < 3][contains(., '#')]");

                if (empty($info)
                    && !empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("change preferences on your Hertz Gold Plus Rewards profile")) . "])[1]"))) {
                    $st->setMembership(true);
                    $st->setNoBalance(true);

                    return $email;
                } elseif (!empty($info)) {
                    break;
                }
            }
        }

        if (preg_match("/^\s*([[:alpha:]][[:alpha:] \.\-]+)\s*\#\s*(\d{5,})\s*$/u", $info, $m)) {
            $st->setNumber($m[2]);
            $st->addProperty('Name', $m[1]);
            $st->setNoBalance(true);
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["subject"]) || empty($headers["from"])) {
            return false;
        }

        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (($this->http->XPath->query("//a[contains(@href,'" . $this->detectCompany . "')]")->length === 0)) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if (
                $this->http->XPath->query("//text()[" . $this->eq($dBody[0]) . "]/following::td[not(.//td) and normalize-space()][1][" . $this->eq($dBody[1]) . "]/following::td[not(.//td) and normalize-space()][2][" . $this->eq($dBody[2]) . "]")->length > 0
                || $this->http->XPath->query("//text()[" . $this->eq($dBody[0]) . "]/following::text()[normalize-space()][1][" . $this->eq($dBody[1]) . "]/following::text()[normalize-space()][2][" . $this->eq($dBody[2]) . "]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug($date);
        $in = [
            "#^\s*(\w+) (\d{1,2}) (\d{2}) (\d{1,2}:\d{2} [ap]m)b?\s*$#i", // Mar 28 14 6:30 PM; Mar 09 14 11:50 PMb
        ];
        $out = [
            "$2 $1 20$3, $4",
        ];
        $date = preg_replace($in, $out, $date);
//        $this->logger->debug($date);
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
