<?php

namespace AwardWallet\Engine\thalys\Email;

use AwardWallet\Engine\thalys\Email\Statement\Account;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourTrip extends \TAccountCheckerExtended
{
    public $mailFiles = "thalys/it-136357577.eml, thalys/it-60647692.eml, thalys/it-61353061.eml, thalys/it-67975176.eml, thalys/it-95282995.eml";

    public $detectFrom = ".thalys.com";
    public $detectSubject = [
        // en
        "Confirmation of Thalys booking",
        "Confirmation of cancellation of a Thalys journey",
        // fr
        "Votre e-Ticket Thalys",
        "Confirmation d'annulation de voyage Thalys :",
        // nl
        "Reserveringsbevestiging Thalys",
        // de
        "Bestätigung Ihrer Thalys-Reservierung",
    ];
    public $detectCompany = '.thalys.com';
    public $detectBody = [
        'en' => ['happy to welcome you soon on board of our train'],
        'fr' => ['heureux de vous accueillir prochainement à bord de notre train', "Nous vous confirmons l'annulation de votre réservation"],
        'nl' => 'We zijn blij om u binnenkort te verwelkomen aan boord van onze trein',
        'de' => ['wir freuen uns, Sie bald an Bord unseres Zuges zu begrüßen.']
    ];

    public static $dictionary = [
        'en' => [
            //            "Travel date" => "",
            //            "Travel reference / PNR" => "",
            //            "Hello " => "",
            //            "Departure" => "",
            //            "Arrival" => "",
            "Cancellation Text" =>  ["confirm you the cancellation of your booking", "CANCELLATION OF YOUR TRIP", "Confirmation of cancellation of a Thalys journey"],
        ],
        'fr' => [
            "Travel date"            => "Date de voyage",
            "Travel reference / PNR" => ["Réf. voyage", "Réf. voyage / PNR"],
            "Hello "                 => "Bonjour ",
            "Departure"              => "Départ à",
            "Arrival"                => "Arrivée à",
            "Cancellation Text" =>  ["ANNULATION DE VOTRE VOYAGE", "Confirmation d'annulation de voyage", "Nous vous confirmons l'annulation de votre réservation"],
        ],
        'nl' => [
            "Travel date"            => "Reisdatum",
            "Travel reference / PNR" => "Boekingsnummer / PNR",
            "Hello "                 => "Beste ",
            "Departure"              => "Vertrek",
            "Arrival"                => "Aankomst",
//            "Cancellation Text" =>  [""],
        ],
        'de' => [
            "Travel date"            => "Reisedatum",
            "Travel reference / PNR" => "Buchungsreferenz / PNR",
            "Hello "                 => "Hallo ",
            "Departure"              => "Abfahrt um",
            "Arrival"                => "Ankunft um",
//            "Cancellation Text" =>  [""],
        ],
    ];

    public $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains($this->detectCompany, '@href') . "]")->length === 0
            && $this->http->XPath->query("//text()[" . $this->contains("L'équipe Thalys") . "]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query('//*[' . $this->contains($detectBody) . ']')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query('//*[' . $this->contains($detectBody) . ']')->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseEmail($email);

        // use statement/Account
        Account::parseStatement($email, $this);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['thalys', 'sncb'];
    }

    private function parseEmail(Email $email)
    {
        $t = $email->add()->train();

        // General
        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Travel reference / PNR")) . "]/following::text()[normalize-space()][1]"))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hello ")) . "]", null, true, "#" . $this->preg_implode($this->t("Hello ")) . "\s*(.+),#"), false)
        ;

        if (!empty($this->http->FindSingleNode("(//*[".$this->contains($this->t("Cancellation Text"))."])[1]"))) {
            $t->general()
                ->cancelled()
                ->status('Cancelled');
        }
        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Travel date")) . "]/following::text()[normalize-space()][1]"));

        // Segments
        $s = $t->addSegment();

        // Departure
        $info = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Departure")) . "]/ancestor::*[self::td or self::th][1]//text()[normalize-space()]"));

        if (preg_match("#" . $this->preg_implode($this->t("Departure")) . "\s*(?<name>.+)\s+(?<time>\d{1,2}:\d{2})\s*$#", $info, $m)) {
            $s->departure()
                ->name($m['name'])
                ->date((!empty($date)) ? strtotime($m['time'], $date) : null)
            ;
        }

        // Arrival
        $info = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Arrival")) . "]/ancestor::*[self::td or self::th][1]//text()[normalize-space()]"));

        if (preg_match("#" . $this->preg_implode($this->t("Arrival")) . "\s*(?<name>.+)\s+(?<time>\d{1,2}:\d{2})\s*$#", $info, $m)) {
            $s->arrival()
                ->name($m['name'])
                ->date((!empty($date)) ? strtotime($m['time'], $date) : null)
            ;
        }

        // Extra
        $s->extra()->noNumber();
    }

    private function normalizeDate($str)
    {
        $in = [
            // 31/08/2020
            '#(\d+)/(\d+)/(\d{4})#',
        ];
        $out = [
            "$1.$2.$3",
        ];

        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
//            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

        return strtotime($str);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) use ($text) { return "contains(" . $text . ", \"{$s}\")"; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
