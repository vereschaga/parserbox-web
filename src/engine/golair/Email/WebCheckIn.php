<?php

namespace AwardWallet\Engine\golair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WebCheckIn extends \TAccountChecker
{
    public $mailFiles = "golair/it-33806049.eml";

    public $date;
    public $lang = "pt";
    public static $dictionary = [
        "pt" => [],
    ];

    private $detectFrom = "@voegol.com.br";
    private $detectSubject = [
        "pt" => "Lembrete WebCheck-In",
    ];

    private $detectCompany = 'www.voegol.com.br';

    private $detectBody = [
        "pt" => "Confira os dados da sua reserva",
    ];
    private $parser;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parser = $parser;
        $body = $this->http->Response['body'];

        if (empty($body) && stripos($parser->getPlainBody(), '<html') !== false) {
            $this->http->SetEmailBody($parser->getPlainBody());
            $body = $this->http->Response['body'];
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            if (strpos($body, $detectBody) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $this->date = strtotime($parser->getHeader('date'));

        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

        if (strpos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (empty($body) && stripos($parser->getPlainBody(), '<html') !== false) {
            $this->http->SetEmailBody($parser->getPlainBody());
            $body = $this->http->Response['body'];
        }

        if ($this->http->XPath->query("//a[contains(@href,'{$this->detectCompany}')] | //*[contains(.,'{$this->detectCompany}')]")->length === 0) {
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

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space() = 'LOCALIZADOR GOL:']/following::text()[normalize-space()][1]"), "LOCALIZADOR GOL")
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Olá,')]", null, true, "#Olá,\s*(.+?)\s*[.,!]\s*$#"))
        ;

        // Segment
        $xpath = "//td[normalize-space() = 'Data']/ancestor::tr[1][./td[normalize-space()='Origem']]/following::tr[not(.//tr)][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            unset($date);
            $node = $this->http->FindSingleNode("td[2]", $root);

            if (preg_match("#(.*?) - VOO (\d+)$#", $node, $m)) {
                $s->airline()
                    ->name('G3')
                    ->number($m[2])
                ;
                $date = $this->normalizeDate($m[1]);
            }

            // Departure
            $node = $this->http->FindSingleNode("td[3]", $root);

            if (preg_match("#(.+?)\s*\(([A-Z]{3})\)\s*$#", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                ;
            }

            $time = $this->http->FindSingleNode("td[5]", $root);

            if (!empty($time) && !empty($date)) {
                $s->departure()->date(strtotime($time, $date));
            }

            // Arrival
            $node = $this->http->FindSingleNode("td[4]", $root);

            if (preg_match("#(.+?)\s*\(([A-Z]{3})\)\s*$#", $node, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                ;
            }

            $time = $this->http->FindSingleNode("td[6]", $root);

            if (!empty($time) && !empty($date)) {
                $s->arrival()->date(strtotime($time, $date));
            }
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
//        $this->http->log($str);
//        $in = [
//            "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d{1,2})[a-z]{2}?,\s*(\d{4})\s*$#iu",// Friday, February 9th, 2018
//        ];
//        $out = [
//            "$2 $1 $3",
//        ];
//        $str = preg_replace($in, $out, $str);
        if (preg_match("#\s*\d+\s+([^\d\s]+)\s*$#", $str, $m)) {
            $str = EmailDateHelper::calculateDateRelative($str, $this, $this->parser);

            return $str;
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

    private function amount($price)
    {
        if (preg_match("#^\s*\d{1,3}(,\d{1,3})?\.\d{2}\s*$#", $price)) {
            $price = str_replace([',', ' '], '', $price);
        }

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
