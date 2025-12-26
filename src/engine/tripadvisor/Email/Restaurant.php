<?php

namespace AwardWallet\Engine\tripadvisor\Email;

use AwardWallet\Engine\MonthTranslate;

class Restaurant extends \TAccountChecker
{
    public $mailFiles = "tripadvisor/it-1.eml";

    public $reSubject = [
        "en"=> "Confirmation of your booked table at",
        "fr"=> "Confirmation de votre réservation au restaurant",
    ];
    public $reBody = 'TripAdvisor';
    public $reBody2 = [
        "en"=> "Booking number",
        "fr"=> "Numéro de réservatio",
    ];

    public static $dictionary = [
        "en" => [],
        "fr" => [
            "Booking number"       => "Numéro de réservation",
            "For"                  => "Le",
            "Restaurant :"         => "Restaurant :",
            "Dear "                => "Bonjour ",
            "You will earn"        => "NOTTRANSLATED",
            "#Booking (confirmed)#"=> "#Réservation (confirmée)#",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = "E";

        // ConfNo
        $it["ConfNo"] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking number")) . "]", null, true, "#:\s*(.+)#");

        // TripNumber
        // Name
        $it["Name"] = $this->nextText("Restaurant :");

        // StartDate
        $firstItem = $this->http->FindSingleNode("//text()[{$this->eq("■")}]/ancestor::tr[1]/../tr[1]/td[2]", null, true, "#^(?:{$this->t("For")}\s+)?(.{6,})$#");
        $it["StartDate"] = strtotime($this->normalizeDate($firstItem));

        // Address
        $it["Address"] = implode(" ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Restaurant :")) . "]/following::text()[normalize-space(.)][position()=2 or position()=3]"));

        // Phone
        $it["Phone"] = $this->http->FindSingleNode("//img[contains(@src, '/icon-phone.png')]/ancestor::td[1]/following-sibling::td[1]");

        // DinerName
        $it["DinerName"] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true, "#" . $this->t("Dear ") . "(.*?),#");

        // Guests
        $it["Guests"] = $this->http->FindSingleNode("//text()[" . $this->eq("■") . "]/ancestor::tr[1]/../tr[3]/td[2]", null, true, "#\d+#");

        // TotalCharge
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        $it["EarnedAwards"] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("You will earn")) . "]/following::text()[normalize-space(.)][1]");

        // AccountNumbers
        // Status
        $it["Status"] = $this->http->FindSingleNode("//img[contains(@src, '/icon-check.png')]/following::text()[normalize-space(.)][1]", null, true, $this->t("#Booking (confirmed)#"));

        // Cancelled
        // ReservationDate
        // NoItineraries
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'TripAdvisor') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
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

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
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
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+, (\d+ [^\s\d]+ \d{4}) at (\d+:\d+)$#", //Sunday, 30 November 2014 at 19:00
            "#^[^\s\d]+ (\d+ [^\s\d]+ \d{4}) à (\d+:\d+)$#", //vendredi 29 septembre 2017 à 21:30
        ];
        $out = [
            "$1, $2",
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
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
