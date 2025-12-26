<?php

namespace AwardWallet\Engine\premierinn\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Invoice extends \TAccountChecker
{
    public $mailFiles = "premierinn/it-31753315.eml";

    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "@piconfirmations.";

    private $detectSubject = [
        "en" => "Invoice from your Premier Inn stay",
    ];

    private $detectCompany = "Premier Inn";
    private $detectBody = [
        "en" => "Your invoice",
    ];

    private $lang = "en";

    public function parseEmail(Email $email)
    {
        $text = implode("\n", $this->http->FindNodes("//text()[normalize-space() = 'Your invoice']/ancestor::tr[1][normalize-space()]/following-sibling::tr//text()"));

        // HOTEL
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->re("#Reservation : ([A-Z\d]{5,})\s+#", $text))
            ->travellers(explode("\n", trim($this->re("#\n\s*Names\s+INVOICE.*\n\s*([\s\S]*)\n\s*Address\s+#", $text))))
        ;

        // Hotel
        $h->hotel()
            ->name($this->re("#^\s*(.+)#", $text))
            ->address(preg_replace('#\s*\n\s*#', ', ', trim($this->re("#^\s*.+\n\s*([\S\s]+)\s+Tel\s+#", $text))))
            ->phone($this->re("#\s*Tel ([\d\(\) \+\-]{5,})\s+#", $text))
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->re("#Arrive : (.+)#", $text)))
            ->checkOut($this->normalizeDate($this->re("#Depart : (.+)#", $text)))
            ->guests($this->re("#Guests : (\d+)\s+#", $text))
        ;

        $priceText = $this->re("#Debit\s+Credit\s*([\s\S]+?)\s+Balance Due\s+#", $text);

        if (!empty($priceText) && stripos($priceText, " Refund") === false
                && preg_match("#\n\s*([A-Z]{3})[ \-]{5,}\n\s*(\d+\.\d{2}) \d+\.\d{2}#", $priceText, $m)) {
            $h->price()
                ->total($this->amount($m[2]))
                ->currency($m[1])
            ;
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //		$body = html_entity_decode($this->http->Response["body"]);
        //		foreach($this->detectBody as $lang => $dBody){
        //			if (stripos($body, $dBody) !== false) {
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
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
        $body = $this->http->Response['body'];

        if ($this->http->XPath->query("//*[contains(.,'{$this->detectCompany}')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if (strpos($body, $detectBody) !== false) {
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
        return count(self::$dictionary);
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
        //		 $this->http->log($str);
        $in = [
            "#^\s*(\d{1,2})/(\d{1,2})/(\d{2})\s*$#iu", //02/01/19
        ];
        $out = [
            "$1.$2.20$3",
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
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

    private function amount($price)
    {
        if (is_numeric($price)) {
            return (float) $price;
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
