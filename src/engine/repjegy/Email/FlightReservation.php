<?php

namespace AwardWallet\Engine\repjegy\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightReservation extends \TAccountChecker
{
    public $mailFiles = "repjegy/it-38680774.eml, repjegy/it-38903043.eml, repjegy/it-62485850.eml";

    public $lang = "hu";
    public static $dictionary = [
        "hu" => [
            "Összesen:"  => ["Összesen:", "A fizetett összeg"],
            "járatszám:" => ["járatszám:", "turista osztály járatszám:"],
        ],
    ];

    private $detectFrom = "@repjegy.hu";
    private $detectSubject = [
        "hu" => "repjegy.hu repülőjegy foglalás",
    ];

    private $detectCompany = 'repjegy.hu';

    private $detectBody = [
        "hu" => "Repülőjegy visszaigazolás",
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        $body = $this->http->Response['body'];
//        foreach ($this->detectBody as $lang => $detectBody){
//            if (strpos($body, $detectBody) !== false) {
//                $this->lang = $lang;
//                break;
//            }
//        }

        // Travel Agency
        $email->obtainTravelAgency();
        $confs = array_filter(array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Foglalási azonosító:")) . "]/following::text()[normalize-space()][1]", null, "#^\s*([\dA-Z]{5,})\s*$#")));

        foreach ($confs as $conf) {
            $email->ota()
                ->confirmation($conf, "Foglalási azonosító");
        }

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
        if ($this->http->XPath->query("//a[contains(@href,'{$this->detectCompany}')] | //*[contains(.,'{$this->detectCompany}')]")->length === 0) {
            return false;
        }

        $body = $this->http->Response['body'];

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
            ->noConfirmation();

        $pXpath = "//td[not(.//td) and " . $this->eq($this->t("vezetéknév")) . " and following-sibling::td[" . $this->eq($this->t("keresztnév")) . "]]/following::tr[count(td) = 3 and starts-with(translate(normalize-space(), '123456789', '%%%%%%%%%'), '%.')][position()<10]";
        $pNodes = $this->http->XPath->query($pXpath);

        foreach ($pNodes as $root) {
            $f->general()->traveller($this->http->FindSingleNode("./td[2]/descendant::tr[1]", $root, true, "#^\s*(?:(?:Mr|Mrs|Ms)\. )?(\S.+)#"));
        }

        if (count($f->getTravellers()) == 0) {
            $travellers = $this->http->FindNodes("//text()[(starts-with(normalize-space(.),'Utas adatok'))]/ancestor::table[1]/following::table[contains(normalize-space(), 'utas')]/descendant::tr[starts-with(normalize-space(), 'Mr')]");

            if (count($travellers) > 0) {
                $f->general()
                    ->travellers($travellers, true);
            }
        }

        // Price
        $totalsText = $this->http->FindNodes("//td[not(.//td) and {$this->eq($this->t('Összesen:'))}]/following-sibling::td[normalize-space()][1]");
        $totals = [];

        foreach ($totalsText as $value) {
            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $value, $m)) {
                $currency = $this->currency($m['curr']);
                $totals[] = $this->amount($m['amount']);
            }
        }

        if (!empty($currency) && count($totals) == count(array_filter($totals))) {
            $f->price()
            ->total(array_sum($totals))
            ->currency($currency);
        }

        // Segments
        $xpath = "//text()[{$this->starts($this->t('járatszám:'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Departure
            $name = implode(", ", $this->http->FindNodes("td[2]/descendant::text()[normalize-space()][position()<last()]", $root));

            if (empty($name)) {
                $name = $this->http->FindSingleNode("./ancestor::table[1]/following::table[1]/descendant::span[normalize-space()][1]", $root);
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("td[2]/descendant::text()[normalize-space()][last()]", $root));

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./ancestor::table[1]/following::table[1]/descendant::span[normalize-space()][3]", $root));
            }

            $s->departure()
                ->noCode()
                ->name($name)
                ->date($date);

            // Arrival
            $name = implode(", ", $this->http->FindNodes("td[4]/descendant::text()[normalize-space()][position()<last()]", $root));

            if (empty($name)) {
                $name = $this->http->FindSingleNode("./ancestor::table[1]/following::table[4]/descendant::span[normalize-space()][1]", $root);
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("td[4]/descendant::text()[normalize-space()][last()]", $root));

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./ancestor::table[1]/following::table[4]/descendant::span[normalize-space()][3]", $root));
            }
            $s->arrival()
                ->noCode()
                ->name($name)
                ->date($date)
            ;

            $info = implode("\n", $this->http->FindNodes("td[1]//text()[normalize-space()]", $root));
            // Airline
            if (preg_match("#járatszám:\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s?(\d{1,5})\b#u", $info, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }

            if (preg_match("#üzemeltető:\s*(.+)#u", $info, $m)) {
                $s->airline()->operator($m[1]);
            }

            // Extra
            if (preg_match("#géptípus:\s*(.+)#u", $info, $m)) {
                $s->extra()->aircraft($m[1]);
            }

            $cabin = trim(preg_replace('#osztály\s*#', '', $this->http->FindSingleNode("td[1]//text()[" . $this->contains('osztály') . "][1]", $root)), ':');

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $duration = $this->http->FindSingleNode("td[3]", $root);

            if (empty($duration)) {
                $duration = $this->http->FindSingleNode("./ancestor::table[1]/following::text()[starts-with(normalize-space(), 'teljes menetidő:')]/following::text()[normalize-space()][1]", $root);
            }

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
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
        $in = [
            //            "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d{1,2})[a-z]{2}?,\s*(\d{4})\s*$#iu",// Friday, February 9th, 2018
        ];
        $out = [
            //            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

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
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'Ft' => 'HUF',
            '€'  => 'EUR',
            '$'  => 'USD',
            '£'  => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
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
