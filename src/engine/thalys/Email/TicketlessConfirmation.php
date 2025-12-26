<?php

namespace AwardWallet\Engine\thalys\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parser thalys/ConfirmationHtml2016De (in favor of thalys/TicketlessConfirmation)

class TicketlessConfirmation extends \TAccountChecker
{
    public $mailFiles = "thalys/it-11447724.eml"; // +2 bcdtravel(html)[de,nl]

    public $reSubject = [
        "nl" => "Reserveringsbevestiging Ticketless Thalys",
        "fr" => "Confirmation de reservation Ticketless Thalys",
        "de" => "Bestätigung Ihrer Thalys-Reservierung",
        "en" => "Confirmation of Thalys Ticketless",
    ];
    public $reBody = 'Thalys';
    public $reBody2 = [
        'nl' => 'Hierbij uw ticket voor uw reis op',
        'fr' => 'Voici votre billet pour votre voyage du',
        'de' => 'Dies ist Ihr Ticket für Ihre Reise am',
        'en' => 'Here is your ticket for your journey on',
    ];

    public static $dictionary = [
        'nl' => [
            "DEAR " => "HALLO ",
            "TCN"   => ["TCN", "TCN/WIFI"],
        ],
        'fr' => [
            //            "DEAR " => "",
            "RESERVERINGSCODE" => ["RÉF. VOYAGE", "Rï¿½F. VOYAGE"],
            "NAAM EN VOORNAAM" => ["NOM ET PRÉNOM", "NOM ET PRï¿½NOM"],
            //			"TCN" => "",
            //			"My Thalys World number" => "",
            "PRIJS"       => "PRIX",
            "VAN"         => ["DÉPART À", "Dï¿½PART ï¿½"],
            "REISDATUM"   => "DATE DE VOYAGE",
            "TREINNUMMER" => ["N°DE TRAIN", "Nï¿½DE TRAIN"],
            "VOERTUIG"    => "VOITURE",
            "KLASSE"      => "CLASSE",
            "ZITPLAATS"   => ["SIÈGE", "SIï¿½GE"],
        ],
        'de' => [
            "DEAR "            => "GUTEN TAG,",
            "RESERVERINGSCODE" => "BUCHUNGSREF.",
            "NAAM EN VOORNAAM" => "NAME UND VORNAME",
            //			"TCN" => "",
            //			"My Thalys World number" => "",
            "PRIJS"       => "PREIS",
            "VAN"         => "ABFAHRT",
            "REISDATUM"   => "DATUM DER REISE",
            "TREINNUMMER" => "ZUG-NR.",
            "VOERTUIG"    => "WAGEN",
            "KLASSE"      => "KLASSE",
            "ZITPLAATS"   => "SITZ-NR.",
        ],
        'en' => [
            "DEAR "            => "DEAR ",
            "RESERVERINGSCODE" => "BOOKING REF.",
            "NAAM EN VOORNAAM" => ["SURNAME AND FIRST NAME", "FIRST NAME AND SURNAME"],
            //			"TCN" => "",
            //			"My Thalys World number" => "",
            "PRIJS"       => "PRICE",
            "VAN"         => "FROM",
            "REISDATUM"   => "DATE OF JOURNEY",
            "TREINNUMMER" => "TRAIN No.",
            "VOERTUIG"    => "CARRIAGE",
            "KLASSE"      => "CLASS",
            "ZITPLAATS"   => "SEAT",
        ],
    ];

    public $lang = '';

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Thalys Ticket') !== false
            || stripos($from, '@mail.thalysticketless.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
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
            if (strpos($body, $re) !== false || $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $re . '")]')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $t = $email->add()->train();

        // General
        $t->general()
            ->confirmation($this->getField($this->t("RESERVERINGSCODE")))
        ;

        $passenger = $this->getField($this->t("NAAM EN VOORNAAM"));

        if (!empty($passenger)) {
            $t->general()
                ->traveller($passenger);
        }

        // Ticket
        $ticket = $this->getField($this->t("TCN"));

        if (!empty($ticket)) {
            $t->addTicketNumber($ticket, false);
        }

        // Price
        $price = $this->getField($this->t("PRIJS"));

        if ($price !== null) {
            $t->price()
                ->total(cost($price))
                ->currency(currency($price))
            ;
        }

        // Account
        $account = $this->getField($this->t("My Thalys World number"));

        if (!empty($account)) {
            $t->program()->account($account, false);
        }

        // Segments
        $xpath = "//*[" . $this->eq($this->t("VAN")) . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->notice("segments root not found: $xpath");
        }

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $date = strtotime($this->normalizeDate($this->getField($this->t("REISDATUM"))));

            // Departure
            $s->departure()
                ->name($this->http->FindSingleNode("./tr[2]/td[1]", $root))
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[3]/td[1]//span[normalize-space()][2]", $root)), $date))
            ;

            // Arrival
            $s->arrival()
                ->name($this->http->FindSingleNode("./tr[2]/td[3]", $root))
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[3]/td[2]//span[normalize-space()][2]", $root)), $date))
            ;

            // Extra
            $s->extra()
                ->number($this->getField($this->t("TREINNUMMER")))
                ->cabin($this->getField($this->t("KLASSE")))
                ->duration($this->normalizeDate($this->http->FindSingleNode("./tr[2]/td[2]", $root)))
                ->car($this->getField($this->t("VOERTUIG")), true, true)
                ->seat($this->getField($this->t("ZITPLAATS")), true, true)
            ;
        }

        $account = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("DEAR ")) . "][1]/following::text()[normalize-space()][position()<5][" . $this->contains($this->t("My Thalys World number ")) . "]", null, true,
            "/" . $this->preg_implode($this->t("My Thalys World number")) . "\s+(\d{10,})\s*$/");

        if (!empty($account)) {
            $st = $email->add()->statement();
            $st
                ->setNumber($account)
                ->setNoBalance(true)
            ;
            $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("DEAR ")) . "][1]", null, true,
                "/^\s*([[:alpha:] \-]+)\s*$/u");
            $st->addProperty('Name', $name);
        }

        return $email;
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

    private function getField($field)
    {
        return $this->http->FindSingleNode('//text()[' . $this->eq($field) . ']/following::text()[normalize-space(.)][1]');
    }

    private function normalizeDate($str)
    {
        $in = [
            '#(\d{1,2})[UuHh](\d{1,2})#', // 07U58
            '#(\d+)/(\d+)/(\d{4})#',
        ];
        $out = [
            '$1:$2',
            "$1.$2.$3",
        ];

        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}

        return $str;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map('preg_quote', $field)) . ')';
    }
}
