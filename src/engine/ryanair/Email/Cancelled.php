<?php

namespace AwardWallet\Engine\ryanair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Cancelled extends \TAccountChecker
{
    public $mailFiles = "ryanair/it-58296578.eml, ryanair/it-58311570.eml, ryanair/it-58395996.eml, ryanair/it-58407096.eml, ryanair/it-58485037.eml, ryanair/it-58506650.eml, ryanair/it-58534237.eml, ryanair/it-60344668.eml, ryanair/it-60376956.eml, ryanair/it-60493555.eml";

    public $emailSubject;

    public $lang = "";
    public static $dictionary = [
        "en" => [
            // type 1: with flights
            //            "Regarding your Ryanair Flight" => "",
            //            "cancel your flight(s)" => "",
            //            "from" => "",
            //            "to" => "",
            //            "on" => "",
            // type 2: with voucher and no flights
            //            "Our Ref:" => "Our Ref:",
            "Voucher Name:" => ["Voucher Name:", "Name:"],
            //            ", the full value of your unused booking." => "",
        ],
        "fr" => [
            // type 1: with flights
            "Regarding your Ryanair Flight" => "Regarding your Ryanair Flight",
            "cancel your flight(s)"         => ["d'annuler votre vol", "d’annuler votre ou vos vol(s)"],
            "from"                          => "au départ de",
            "to"                            => "et à destination de",
            "on"                            => "le",
            // type 2: with voucher and no flights
            ", the full value of your unused booking." => ", la valeur totale de votre réservation inutilisée.",
        ],
        "es" => [
            // type 1: with flights
            //            "Regarding your Ryanair Flight" => "",
            "cancel your flight(s)" => "ha visto obligado a cancelar tu(s) vuelo(s)",
            "from"                  => "de",
            "to"                    => "a",
            "on"                    => "el",
            // type 2: with voucher and no flights
            "Our Ref:"                                 => ["Our Ref:", "Nuestra referencia:"],
            "Voucher Name:"                            => ["Voucher Name:", "Nombre:"],
            ", the full value of your unused booking." => ", por el valor total de su reserva no utilizada.",
        ],
        "pl" => [
            // type 1: with flights
            "Regarding your Ryanair Flight" => "Regarding your Ryanair Flight",
            "cancel your flight(s)"         => "firma Ryanair musiała odwołać lot/loty",
            "from"                          => "from",
            "to"                            => "to",
            "on"                            => "z dn.",
            // type 2: with voucher and no flights
            "Our Ref:"                                 => ["Our Ref:", "Numer ref.:"],
            "Voucher Name:"                            => ["Voucher Name:", "Imię i nazwisko:"],
            ", the full value of your unused booking." => ", czyli pełną wartość Twojej niewykorzystanej rezerwacji.",
        ],
        "de" => [
            // type 1: with flights
            //            "Regarding your Ryanair Flight" => "",
            //            "cancel your flight(s)" => "",
            //            "from" => "",
            //            "to" => "",
            //            "on" => "",
            // type 2: with voucher and no flights
            "Our Ref:"                                 => "Our Ref:",
            "Voucher Name:"                            => "Voucher Name:",
            ", the full value of your unused booking." => ", im vollen Wert Ihrer nicht verwendeten Buchung.",
        ],
    ];

    private $detectFrom = "ryanair.com";

    private $detectSubject = [
        "Important Information Regarding your Ryanair Flight", // en, fr, pl
        // en
        "Your recent correspondence with Ryanair",
        // es
        "Su reciente correspondencia con Ryanair",
        // pl
        "Państwa korespondencja z Ryanair.",
    ];
    // type 1: with flights
    private $detectBodyType1 = [
        "en" => [
            "has been forced to cancel your flight",
        ],
        "fr" => [
            "Ryanair a été obligé d'annuler votre vol",
            "Ryanair a été forcé d’annuler votre ou vos vol(s)",
        ],
        "pl" => [
            "firma Ryanair musiała odwołać lot/loty",
        ],
        "es" => [
            "Ryanair se ha visto obligado a cancelar tu(s) vuelo(s)",
        ],
    ];
    // type 2: with voucher and no flights
    private $detectBodyType2 = [
        "en" => [
            "forced the cancellation of your Ryanair flight(s)",
            "any inconvenience caused by your recent flight cancellation.",
        ],
        "fr" => [
            "nous avons dû annuler votre ou vos vols Ryanair",
        ],
        "es" => [
            "han forzado la cancelación de su (s) vuelo (s) de Ryanair",
            "por las molestias ocasionadas por la reciente cancelación de tu vuelo",
        ],
        "pl" => [
            "ograniczenia podróży zmusiły nas do odwołania Twojego lotu Ryanair",
            "wszelkie niedogodności spowodowane odwołaniem Twojego lotu",
        ],
        "de" => [
            "Reisebeschränkungen der Regierungen die Annullierung Ihrer Ryanair-Flüge",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBodyType1 as $lang => $body) {
            if ($this->http->XPath->query('//*[' . $this->contains($body) . ']')->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            foreach ($this->detectBodyType2 as $lang => $body) {
                if ($this->http->XPath->query('//*[' . $this->contains($body) . ']')->length > 0) {
                    $this->lang = $lang;

                    break;
                }
            }
        }
        $this->emailSubject = $parser->getSubject();
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
//        if (stripos($headers['from'], $this->detectFrom) !== false) {
//            return true;
//        }
//        foreach ($this->detectSubject as $dSubject) {
//            if (strpos($headers['subject'], $dSubject) !== false) {
//                return true;
//            }
//        }
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[' . $this->contains(['ryanair.com'], '@href') . '] | //img[' . $this->contains(['tripcase'], '@src') . ']')->length === 0) {
            return false;
        }

        foreach ($this->detectBodyType1 as $lang => $body) {
            if ($this->http->XPath->query('//*[' . $this->contains($body) . ']')->length > 0) {
                return true;
            }
        }

        foreach ($this->detectBodyType2 as $lang => $body) {
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
        if (preg_match("#" . $this->preg_implode($this->t("Regarding your Ryanair Flight")) . "\s*([A-Z\d]{5,7})(\s+|$)#",
            $this->emailSubject, $m)) {
            $conf = $m[1];
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Our Ref:")) . "][1]", null, true,
                "#" . $this->preg_implode($this->t("Our Ref:")) . "\s*([A-Z\d]{5,7})\b#u");
        }
        $f->general()
            ->confirmation($conf);

        $f->general()
            ->status('Cancelled')
            ->cancelled()
        ;

        $traveller = $this->http->FindSingleNode("//*[" . $this->eq($this->t("Voucher Name:")) . "][1]/following::text()[normalize-space()][1]");

        if (!empty($traveller)) {
            $f->general()
                ->traveller($traveller, true);
        }

        $total = $this->http->FindSingleNode("//text()[" . $this->contains($this->t(", the full value of your unused booking.")) . "][1]", null, true,
            "#.+\D (\d.+)" . $this->preg_implode($this->t(", the full value of your unused booking.")) . "#u");

        if (!empty($total)) {
            if (preg_match("#^\s*(?<curr>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[A-Z]{3})\s*$#", $total, $m)) {
                $f->price()
                    ->total($this->amount($m['amount']))
                    ->currency($m['curr']);
            }
        }

        $flightsText = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("cancel your flight(s)")) . "][1]", null, true, "#" . $this->preg_implode($this->t("cancel your flight(s)")) . "\s*(.+?)(?<!" . $this->preg_implode(preg_replace("#\.$#", '', $this->t("on"))) . ")\.(?:\s+|$)#u");
        // Segments
        if (!empty($flightsText)) {
            $flights = $this->split("#\b([A-Z\d][A-Z]|[A-Z][A-Z\d]\d{1,5} " . $this->preg_implode($this->t("from")) . ")#",
                $flightsText, $m);

            foreach ($flights as $flight) {
                $s = $f->addSegment();

                if (preg_match("#\b(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) " . $this->preg_implode($this->t("from")) . " (?<from>.+) " . $this->preg_implode($this->t("to")) . " (?<to>.+) " . $this->preg_implode($this->t("on")) . " (?<date>.+)#",
                    $flight, $m)) {
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn']);

                    if (preg_match('#(\S.+?)\s+([A-Z\d]{1,4})$#', $m['from'], $mat)) {
                        $s->departure()
                            ->noCode()
                            ->name($mat[1])
                            ->terminal($mat[2]);
                    } else {
                        $s->departure()
                            ->noCode()
                            ->name($m['from']);
                    }

                    if (preg_match('#(\S.+?)\s+([A-Z\d]{1,4})$#', $m['to'], $mat)) {
                        $s->arrival()
                            ->noCode()
                            ->name($mat[1])
                            ->terminal($mat[2]);
                    } else {
                        $s->arrival()
                            ->noCode()
                            ->name($m['to']);
                    }

                    $date = $this->normalizeDate($m['date']);

                    if (!empty($date)) {
                        $s->departure()
                            ->day($date)
                            ->noDate();
                        $s->arrival()
                            ->noDate();
                    }
                }
            }
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
            "#^\s*(\d{1,2})([^\d\s\.\,]+)[.]?(\d{2})\s*$#ui", //26Apr20; 24avr.20
        ];
        $out = [
            "$1 $2 20$3",
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
