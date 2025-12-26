<?php

namespace AwardWallet\Engine\sncf\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BilletTER extends \TAccountChecker
{
    public $mailFiles = "sncf/it-65798392.eml";

    public $date;
    public $lang = "fr";
    public static $dictionary = [
        "fr" => [
            "route" => ["Aller", "Retour"],
            //            "" => "",
        ],
    ];

    private $detectFrom = "@ter-sncf.fr";
    private $detectSubject = [
        "fr" => "Confirmation de votre commande de billets TER",
    ];

    private $detectCompany = ['Votre équipe SNCF TER'];

    private $detectBody = [
        "fr" => [
            "Vous avez effectué une commande",
        ],
    ];

    private $detectLang = [
        "fr" => "votre voyage",
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];
//        foreach ($this->detectLang as $lang => $dBody){
//            if (strpos($body, $dBody) !== false || $this->http->XPath->query("//*[contains(normalize-space(.),'{$dBody}')]")->length > 0) {
//                $this->lang = $lang;
//                break;
//            }
//        }

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Aller simple")) . "]/ancestor::td[1]/following-sibling::td[1]", null, true,
            "/(.*\d.*) pour/");

        if (preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$/u", $total, $m)
            || preg_match("/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/u", $total, $m)
        ) {
            // 102 , 00 €    |    € 102,00
            $email->price()
                ->total($this->amount(str_replace(' ', '', $m['amount'])))
                ->currency($this->currency($m['curr']));
        }

        $this->parseTrain($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query("//a[{$this->contains($this->detectCompany, '@href')}] | //*[{$this->contains($this->detectCompany)}]")->length === 0) {
            return false;
        }

        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false || $this->http->XPath->query("//*[contains(normalize-space(.),'{$dBody}')]")->length > 0) {
                    return true;
                }
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

    private function parseTrain(Email $email)
    {
        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->http->FindSingleNode("//*[{$this->eq($this->t("Vos références de commande"))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/'))
            ->travellers($this->http->FindNodes("//a[" . $this->eq($this->t("imprimer ce billet")) . "]/ancestor::td[preceding-sibling::td[normalize-space()]][1]/ancestor::tr[1]/td[1]"))
        ;

        $xpath = "//text()[" . $this->starts($this->t("Départ")) . "]/ancestor::tr[1]";
        $segments = $this->http->XPath->query($xpath);

        // Segments
        foreach ($segments as $root) {
            $s = $t->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::text()[" . $this->starts($this->t("route")) . "][1]", $root, true,
                "/" . $this->preg_implode($this->t("route")) . "\s*(.+)/"));

            // Departure
            $s->departure()
                ->name($this->http->FindSingleNode("./td[normalize-space()][1]", $root, true,
                    "/^\s*(.+?)\s*" . $this->preg_implode($this->t("Départ")) . "/"))
            ;
            $time = $this->normalizeTime($this->http->FindSingleNode("./td[normalize-space()][1]", $root, true,
                "/" . $this->preg_implode($this->t("Départ")) . "\s*(.+)/"));

            if (!empty($date) && !empty($time)) {
                $s->departure()->date(strtotime($time, $date));
            }

            // Arrival
            $s->arrival()
                ->name($this->http->FindSingleNode("./td[normalize-space()][2]", $root, true,
                    "/^\s*(.+?)\s*" . $this->preg_implode($this->t("Arrivée")) . "/"))
            ;
            $time = $this->normalizeTime($this->http->FindSingleNode("./td[normalize-space()][2]", $root, true,
                "/" . $this->preg_implode($this->t("Arrivée")) . "\s*(.+)/"));

            if (!empty($date) && !empty($time)) {
                $s->arrival()->date(strtotime($time, $date));
            }

            $s->extra()
                ->noNumber();
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

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = ' . print_r($date, true));
        $in = [
            // Samedi 12 Septembre 2020
            '#^\s*[[:alpha:]]{2,}[,]?\s*(\d{1,2})\s+([[:alpha:]]{3,})\s+(\d{4})\s*$#iu',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([[:alpha:]]{3,})\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function normalizeTime(?string $time): string
    {
        $in = [
            '#^\s*(\d{1,2})[h](\d{2})\s*$#ui', //12h25
        ];
        $out = [
            '$1:$2',
        ];
        $time = preg_replace($in, $out, $time);

        return $time;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return $text . '="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'starts-with(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
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
        $price = trim($price);

        if (preg_match("#^([\d,. ]+)[.,](\d{2})$#", $price, $m)) {
            $price = str_replace([' ', ',', '.'], '', $m[1]) . '.' . $m[2];
        }

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
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
