<?php

namespace AwardWallet\Engine\hotwire\Email;

use AwardWallet\Schema\Parser\Common\Rental;
use AwardWallet\Schema\Parser\Email\Email;

class GetReadyForTripRental extends \TAccountChecker
{
    public $mailFiles = "hotwire/it-111952781.eml, hotwire/it-113565337.eml";

    public $detectFrom = "hotwiretripreminder@e.hotwire.com";
    public $detectSubject = [
        // en
        "Get ready for your trip to ",
    ];
    private $detectBody = [
        "en" => [
            "trip is coming up",
        ],
    ];

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            "confirmation number is" => [
                "confirmation number is",
                "itinerary number is",
            ],
            "Pick-up"        => "Pick-up",
            "Drop-off"       => "Drop-off",
            "CompanyRegexp"       => "Your (.+) (?:confirmation|itinerary) number is",
        ],
    ];


    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.hotwire.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])
            || stripos($headers["from"], $this->detectFrom) === false
        ) {
            return false;
        }
        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.hotwire.com')]")->length == 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($dBody)}]")->length > 0) {
                return $this->assignLang();
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

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
        $otaConfNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotwire Itinerary #'))}]/ancestor::tr[1]",
            null, true, "/{$this->opt($this->t('Hotwire Itinerary #'))}\s*(\d{5,})$/");

        $email->ota()
            ->confirmation($otaConfNo, 'Hotwire itinerary #');

        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[".$this->contains($this->t("confirmation number is"))."]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([\dA-Z]{5,})\s*$/"));

        $xPath = "//tr[not(.//tr)][*[1]/descendant::text()[normalize-space()][1][{$this->eq($this->t("Pick-up"))}] and *[2]/descendant::text()[normalize-space()][1][{$this->eq($this->t("Drop-off"))}]]";

        $r->pickup()
            ->date($this->normalizeDate($this->http->FindSingleNode($xPath."/*[1]", null, true,
                "/{$this->opt($this->t("Pick-up"))}\s*(.+)/")))
            ->location(implode(', ', $this->http->FindNodes($xPath . "/following::tr[not(.//tr)][normalize-space()][1][count(*) = 1 or count(*) = 2]/*[1]/descendant::text()[normalize-space()][position()>1]")))
        ;
        $r->dropoff()
            ->date($this->normalizeDate($this->http->FindSingleNode($xPath."/*[2]", null, true,
                "/{$this->opt($this->t("Drop-off"))}\s*(.+)/")));
        if ($this->http->FindSingleNode($xPath . "/following::tr[not(.//tr)][normalize-space()][1][count(*) = 1]")) {
            $r->dropoff()->same();
        } else {
            $r->dropoff()
                ->location(implode(', ', $this->http->FindNodes($xPath . "/following::tr[not(.//tr)][normalize-space()][1][count(*) = 1 or count(*) = 2]/*[2]/descendant::text()[normalize-space()][position()>1]")));
        }

        // Extra
        $r->extra()
            ->company($this->http->FindSingleNode("//text()[".$this->contains($this->t("confirmation number is"))."]",
                null, true, "/".$this->t("CompanyRegexp")."/"));

   }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Pick-up"]) && $this->http->XPath->query("//*[{$this->contains($words["Pick-up"])}]")->length > 0) {
                $this->lang = $lang;
                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
//            "#^\s*([^\d\s]+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+[AP]M)$#", // Mar 18, 2017, 4:30PM
//            "#^\s*([^\d\s]+)\s+(\d+),\s+(\d{4})$#", // Feb 17, 2017
        ];
        $out = [
//            "$2 $1 $3, $4",
//            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
