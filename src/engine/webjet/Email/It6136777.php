<?php

namespace AwardWallet\Engine\webjet\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It6136777 extends \TAccountChecker
{
    public $mailFiles = "webjet/it-74524978.eml";
    public $reFrom = "webjet@news.webjet.com.au";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";
    private $reSubject = [
        "en" => "Information for your",
    ];
    private $reBody = 'Webjet';
    private $reBody2 = [
        "en" => "Itinerary Summary",
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //		foreach($this->reBody2 as $lang => $re){
        //			if (strpos($this->http->Response["body"], $re) !== false){
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $this->parseHtml($email);

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

    private function parseHtml(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->nextText($this->t("Webjet Reference:")));

        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[" . $this->starts("Hi ") . "]", null, true,
                "/Hi ([[:alpha:] \-]+),$/"))
        ;

        $xpath = "//text()[" . $this->starts($this->t("Depart:")) . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./tr[2]", $root, true, "#\s+(\w{2})\d+$#"))
                ->number($this->http->FindSingleNode("./tr[2]", $root, true, "#\s+\w{2}(\d+)$#"))
            ;
            $conf = $this->nextText("Airline Reference:", $root);

            if (!empty($conf)) {
                $s->airline()
                    ->confirmation($conf);
            }

            // Departure
            $code = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Depart:")) . "]", $root, true, "#:\s*([A-Z]{3})\s*$#");

            if (!empty($code)) {
                $s->departure()->code($code);
            } else {
                $s->departure()->noCode();
            }
            $s->departure()
                ->name($this->http->FindSingleNode("./tr[1]", $root, true, "#(.*?)\s+to\s+.+#"))
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[3]/descendant::text()[normalize-space(.)][2]", $root))))
            ;

            // Arrival
            $code = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Arrive:")) . "]", $root, true, "#:\s*([A-Z]{3})\s*$#");

            if (!empty($code)) {
                $s->arrival()->code($code);
            } else {
                $s->arrival()->noCode();
            }
            $s->arrival()
                ->name($this->http->FindSingleNode("./tr[1]", $root, true, "#.*?\s+to\s+(.+)#"))
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[4]/descendant::text()[normalize-space(.)][2]", $root))))
            ;
        }
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
        $in = [
            "#^(\d+:\d+[AP]M)\s+[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#",
        ];
        $out = [
            "$2 $3 $4, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
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
}
