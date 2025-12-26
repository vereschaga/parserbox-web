<?php

namespace AwardWallet\Engine\scandichotels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3867386 extends \TAccountCheckerExtended
{
    public $mailFiles = "scandichotels/it-33443910.eml, scandichotels/it-33564041.eml, scandichotels/it-40181199.eml, scandichotels/it-6453415.eml, scandichotels/it-98226866.eml";
    public $lang = "en";

    public static $dictionary = [
        "en" => [ // it-40181199.eml, it-6453415.eml
            "baseFare"           => "Totalsum:",
            "tax"                => "MVA:",
            "cancellation"       => [
                "Bookings can be cancelled", "Bookings can be canceled",
                "You can change and cancell the reservation", "You can change and cancel the reservation",
            ],
        ],
        "da" => [
            "Reservation number" => "Bookingnummer",
            "Hotel"              => "Hotel",
            "Arrival date"       => "Ankomstdato",
            "Departure date"     => "Afrejsedato",
            "No. of rooms"       => "Antal værelser",
            "Occupants"          => "Beboere",
            "Total"              => "I alt",
            // "baseFare"           => "",
            // "tax"                => "",
            "Room type"          => "Værelsestype",
            "Price"              => "Pris",
            "Adult"              => "Voks(?:ne|en)", //regexp
            "children"           => "børn", //regexp
            "Visiting address"   => "Beliggenhed og kontaktinformation",
            "Phone"              => "Telefon",
            "Fax"                => "Fax",
            "Guest"              => "Kontaktperson",
            "cancellation"       => "Foretag ændringer eller annuller reservationen",
        ],
        "de" => [
            "Reservation number" => "Reservierungsnummer",
            // "Hotel"              => "",
            "Arrival date"       => "Anreisedatum",
            "Departure date"     => "Abreisedatum",
            "No. of rooms"       => "Anzahl der Zimmer",
            "Occupants"          => "Belegung",
            "Total"              => "Gesamt",
            "baseFare"           => "Zwischensumme:",
            "tax"                => "MwSt.:",
            "Room type"          => "Zimmertyp",
            "Price"              => "Preis",
            "Adult"              => "Erwachsen(?:er|e)", //regexp
            "children"           => "Kinder", //regexp
            "Visiting address"   => "Besuchsadresse",
            "Phone"              => "Telefon",
            // "Fax"                => "",
            "Guest"              => "Ansprechpartner",
            "cancellation"       => "geändert oder storniert werden",
        ],
        "no" => [ // it-33443910.eml, it-33564041.eml
            "Reservation number" => "Bestillingsnummer",
            "Hotel"              => "Hotell",
            "Arrival date"       => "Ankomstdato",
            "Departure date"     => "Avreisedato",
            "No. of rooms"       => "Antall rom",
            "Occupants"          => "Gjester",
            "Total"              => "Totalt",
            // "baseFare"           => "",
            // "tax"                => "",
            "Room type"          => "Romtype",
            "Price"              => "Pris",
            "Adult"              => "Voks(?:ne|en)", //regexp
            "children"           => "barn", //regexp
            "Visiting address"   => "Beliggenhet og kontaktinformasjon",
            "Phone"              => "Telefon",
            "Fax"                => "Telefaks",
            "Guest"              => "Gjest",
            "cancellation"       => ["refunderes eller avbestilles", "Reservasjonen kan endres og avbestilles"],
        ],
        "fi" => [
            "Reservation number" => "Varausnumero",
            "Hotel"              => "Hotelli",
            "Arrival date"       => "Saapumispäivä",
            "Departure date"     => "Lähtöpäivä",
            "No. of rooms"       => "Huoneita",
            "Occupants"          => "Henkilöiden lukumäärä",
            //			"Total" => "",
            // "baseFare"           => "",
            // "tax"                => "",
            "Room type" => "Huonetyyppi",
            //			"Price" => "",
            "Adult" => "Aikuista", //regexp
            //			"children" => "", //regexp
            "Visiting address" => "Sijainti ja yhteystiedot",
            "Phone"            => "Puhelin", //to ckeck
            //            "Fax" => "",
            "Guest" => "Yhteyshenkilö",
            // "cancellation"       => "",
        ],
        "sv" => [ // it-98226866.eml
            "Reservation number" => "Bokningsnummer",
            "Hotel"              => "Hotell",
            "Arrival date"       => "Ankomstdag",
            "Departure date"     => "Avresedag",
            "No. of rooms"       => "Antal rum",
            "Occupants"          => "Gäster",
            "Total"              => "Totalt",
            "baseFare"           => "Pris exklusive moms:",
            "tax"                => "Moms:",
            "Room type"          => "Rumstyp",
            "Price"              => "Pris",
            "Adult"              => "vux(?:en|na)", //regexp
            "children"           => "barn", //regexp
            "Visiting address"   => "Adress",
            "Phone"              => "Telefon",
            "Fax"                => "Fax",
            "Guest"              => "Gäst",
            // "cancellation"       => "",
        ],
    ];
    private $detectFrom = "scandichotels.";
    private $detectSubject = [
        "en" => "Confirmation - Thanks for your booking - Scandic Hotels",
        "sv" => "Tack för din bokning hos Scandic!",
        "da" => "Tak for din booking – Scandic Hotels",
        "de" => "Dank für Ihre Buchung",
        "no" => "Takk for din bestilling – Scandic Hotels",
    ];

    private $detectCompany = 'scandichotels';
    private $detectBody = [
        "da" => "Tak for din booking",
        "de" => "Dank für Ihre Buchung",
        "no" => "Takk for din bestilling",
        "fi" => "Kiitos varauksestasi",
        "sv" => "Tack för din bokning",
        "en" => "for your booking",
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (empty($parser->getHTMLBody())) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $detectBody) {
            if (strpos($body, $detectBody) !== false) {
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
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Scandic Hotels') === false) {
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
        if (empty($parser->getHTMLBody())) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }
        $body = $this->http->Response['body'];

        if (strpos($body, $this->detectCompany) === false) {
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

    private function parseHtml(Email $email): void
    {
        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
        ];

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->getField($this->t('Reservation number')))
            ->travellers($this->http->FindNodes("//text()[" . $this->eq($this->t('Guest')) . "]/following::text()[normalize-space(.)][1]"))
        ;

        // Hotel
        $h->hotel()
            ->name($this->getField($this->t('Hotel')))
            ->address(trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Visiting address')) . "]/following::text()[normalize-space(.)][1]"), ', '))
        ;
        $phone = trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Visiting address')) . "]/following::text()[normalize-space(.)][2]", null, true, "#" . $this->preg_implode($this->t('Phone')) . "\s+([\d\+\-\(\) ]{5,})\s*,?\s*#"));

        if (empty($phone)) {
            $phone = trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Visiting address')) . "]/ancestor::td[1]//text()[" . $this->eq($this->t('Phone')) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*([\d\+\-\(\) ]{5,})\s*$#"));
        }

        if (empty($phone)) {
            $phone = trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Visiting address')) . "]/following::text()[normalize-space(.)][3]", null, true, "#^\s*([\d\+\-\(\) ]{5,})\s*$#"));
        }
        $h->hotel()->phone($phone, true);

        $fax = trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Visiting address')) . "]/following::text()[normalize-space(.)][3]", null, true, "#" . $this->preg_implode($this->t('Fax')) . "\s+([\d\+\-\(\) ]{5,}+)#"), ', ');

        if (empty($fax)) {
            $fax = trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Visiting address')) . "]/ancestor::td[1]//text()[" . $this->eq($this->t('Fax')) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*([\d\+\-\(\) ]{5,})\s*$#"));
        }

        if (empty($fax)) {
            $fax = trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Visiting address')) . "]/following::text()[normalize-space(.)][5]", null, true, "#^\s*([\d\+\-\(\) ]{5,})\s*$#"));
        }
        $h->hotel()->fax($fax, true);

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->getField($this->t("Arrival date"))))
            ->checkOut($this->normalizeDate($this->getField($this->t('Departure date'))))
            ->guests($this->getField($this->t('Occupants'), "#\b(\d+)\s*" . $this->t('Adult') . "#i"))
            ->kids($this->getField($this->t('Occupants'), "#\b(\d+)\s*" . $this->t('children') . "#i"), true, true)
            ->rooms($this->getField($this->t('No. of rooms')))
        ;

        // Rooms
        $h->addRoom()
            ->setRateType(implode("|", $this->http->FindNodes("//text()[" . $this->eq($this->t('Price')) . "]/following::text()[normalize-space(.)][1]")))
            ->setType(implode("|", $this->http->FindNodes("//text()[" . $this->eq($this->t('Room type')) . "]/following::text()[normalize-space(.)][1]")))
        ;

        // Price
        $totalPrice = $this->http->FindSingleNode("(//text()[{$this->contains($this->t("Reservation number"))}]/following::text()[{$this->starts($this->t("Total"))}])[1]/ancestor::*[1]", null, true, "/^{$this->preg_implode($this->t("Total"))}\s+(.*\d.*)$/");

        if (stripos($totalPrice, 'point') !== false) {
            // it-40181199.eml
            $h->price()->spentAwards($totalPrice);
        } elseif (preg_match("/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+)$/", $totalPrice, $matches)) {
            // 1567,86 SEK
            $h->price()->total($this->normalizeAmount($matches['amount']))->currency($matches['currency']);

            $baseFare = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Total"))}]/following::text()[{$this->starts($this->t("baseFare"))}]/ancestor::div[1]", null, true, "/^{$this->preg_implode($this->t("baseFare"))}\s*(.*\d.*)$/");

            if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $baseFare, $m)) {
                $h->price()->cost($this->normalizeAmount($m['amount']));
            }

            $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Total"))}]/following::text()[{$this->starts($this->t("tax"))}]/ancestor::div[1]", null, true, "/^{$this->preg_implode($this->t("tax"))}\s*(.*\d.*)$/");

            if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $tax, $m)) {
                $h->price()->tax($this->normalizeAmount($m['amount']));
            }
        }

        $cancellation = $this->http->FindSingleNode("//li[{$this->contains($this->t("cancellation"))}]");
        $h->general()->cancellation($cancellation, false, true);

        if ($cancellation) {
            if (preg_match("/Bookings (?i)can be cancell?ed free of charge until (?<prior>\d{1,3} days?) prior the day of arrival\./", $cancellation, $m) // en
            ) {
                $h->booked()->deadlineRelative($m['prior'], '00:00');
            } elseif (preg_match("/You (?i)can change and cancell? the reservation until (?<hour>{$patterns['time']}) local time on the day of arrival/", $cancellation, $m) // en
                || preg_match("/Reservasjonen (?i)kan endres og avbestilles frem til kl\. (?<hour>{$patterns['time']}) lokal tid på ankomstdagen/", $cancellation, $m) // no
                || preg_match("/Foretag (?i)ændringer eller annuller reservationen indtil kl\. (?<hour>{$patterns['time']}) på ankomstdagen\./", $cancellation, $m) // da
                || preg_match("/Reservierungen (?i)können bis (?<hour>{$patterns['time']}) Uhr Ortszeit am Anreisetag geändert oder storniert werden/", $cancellation, $m) // de
            ) {
                $m['hour'] = preg_replace('/^(\d{1,2})$/', '$1:00', $m['hour']);
                $h->booked()->deadlineRelative('0 days', $m['hour']);
            } elseif (strpos($cancellation, 'Bestillingen kan ikke endres, refunderes eller avbestilles') === 0
            ) {
                $h->booked()->nonRefundable();
            }
        }
    }

    private function getField($field, $regexp = null, $n = 1)
    {
        return $this->http->FindSingleNode("(//text()[{$this->eq($field)}]/following::text()[normalize-space(.)][1])[{$n}]", null, true, $regexp);
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
            "#^[\w|\D]+\s+(\d+)\s+(\D+)\s+(\d{4})$#",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
