<?php

namespace AwardWallet\Engine\golair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ImportantInformation extends \TAccountChecker
{
    public $mailFiles = "golair/it-76142146.eml";

    public $lang = "";
    public static $dictionary = [
        "pt" => [
            "Informação importante!" => ["Informação importante!", "Esclarecimentos sobre seu voo GOL", "Informação Importante!",
                "Aviso importante para você!", ],
        ],
    ];

    private $detectFrom = "avisogol@drc.voegol.com.br";

    private $detectSubject = [
        // pt
        " Informação importante sobre sua viagem com a GOL",
    ];

    private $detectBody = [
        "pt" => [
            "Informação importante!", "Esclarecimentos sobre seu voo GOL",
            "Informação Importante!", "Aviso importante para você!",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $body) {
            if ($this->http->XPath->query('//*[' . $this->contains($body) . ']')->length > 0) {
                $this->lang = $lang;

                break;
            }
        }
        $this->parseHtml($email);

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
        if (stripos($headers['from'], $this->detectFrom) !== false) {
            return true;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[' . $this->contains(['.voegol.com.br'], '@href') . ']')->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $body) {
            if ($this->http->XPath->query('//*[' . $this->contains($body) . ']')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Localizador:")) . "]/following::text()[normalize-space()][1]", null, true,
            "#^\s*([A-Z\d]{5,7})$#u");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Localizador:")) . "][1]", null,
                true, "#:\s*([A-Z\d]{5,7})\s*$#u");
        }
        $f->general()
            ->confirmation($conf)
            ->traveller($this->http->FindSingleNode("//*[" . $this->eq($this->t("Informação importante!")) . "][1]/following::text()[normalize-space()][1]", null, true,
                "/^\s*([[:alpha:]\-]+),$/"), false)
        ;
//        $xpath = "//tr[td[1]//*[".$this->eq($this->t("Data do Voo"))."] and td[2]//*[".$this->eq($this->t("Horário"))."]]";
        $xpath = "//text()[" . $this->eq($this->t("Data do Voo")) . "]/ancestor::*[.//text()[" . $this->eq($this->t("Horário")) . "]][1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug($xpath);

        foreach ($nodes as $root) {
            $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::td[not(.//td)][normalize-space()][2]", $root));

            $s = $f->addSegment();
            //   /descendant::td[not(.//td)][8]

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::td[not(.//td)][normalize-space()][6]", $root, true, "/^\s*(\w+) \d{1,5}\s*$/"))
                ->number($this->http->FindSingleNode("./descendant::td[not(.//td)][normalize-space()][6]", $root, true, "/^\s*\w+ (\d{1,5})\s*$/"))
            ;

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::td[not(.//td)][normalize-space()][4]", $root, true, "/\(([A-Z]{3})\)\s*$/"))
                ->name($this->http->FindSingleNode("./descendant::td[not(.//td)][normalize-space()][4]", $root))
                ->date(strtotime($this->http->FindSingleNode("./descendant::td[not(.//td)][normalize-space()][8]", $root), $date))
            ;

            // Arrival
            $s->arrival()
                ->noCode()
                ->noDate()
            ;
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return preg_quote($s); }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug("Date: {$date}");
        $in = [
            // 06/12/2020
            "/^\s*(\d{1,2})\/(\d{2})\/(\d{4})\s*$/",
        ];
        $out = [
            "$1.$2.$3",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = trim($r[$i] . $r[$i + 1]);
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }
}
