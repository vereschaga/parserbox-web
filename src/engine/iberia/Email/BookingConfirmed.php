<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmed extends \TAccountChecker
{
    public $mailFiles = "iberia/it-120021098.eml, iberia/it-184918192.eml, iberia/it-185741594.eml, iberia/it-93958490.eml, iberia/it-94438498.eml";
    public $subjects = [
        '/^Booking Confirmed/', // en
        '/^Reserva confirmada/', // es
        '/Réservation confirmée/', // fr
    ];

    public $lang = '';
    public $subject;

    public $detectLang = [
        'en' => ['Flight operated by'],
        'es' => ['Vuelo operado por', 'TU RESERVA A'],
        //        'pt' => ['Dados dos passageiros'],
        'de' => ['BUCHUNGSCODE'],
        'fr' => ['Numéro de référence de réservation'],
    ];

    public static $dictionary = [
        "en" => [
            //            "HAS BEEN CONFIRMED" => "",
            //            "LOCATOR" => "",
            //            "FLIGHT " => "",
            //            "PASSENGERS" => "",
            //            "TOTAL" => "",
        ],
        "es" => [
            "HAS BEEN CONFIRMED" => "HA SIDO CONFIRMADA",
            "LOCATOR"            => "LOCALIZADOR",
            "FLIGHT "            => "VUELO ",
            "PASSENGERS"         => "PASAJEROS",
            "TOTAL"              => "TOTAL",
            'Fare Express'       => 'Tarifa Express',
            'Hello'              => 'Hola',
        ],

        "de" => [
            "HAS BEEN CONFIRMED" => "WURDE BESTÄTIGT",
            "LOCATOR"            => "BUCHUNGSCODE",
            "FLIGHT "            => "FLUG ",
            "PASSENGERS"         => "PASSAGIERE",
            "TOTAL"              => "TOTAL",
        ],

        "fr" => [
            "HAS BEEN CONFIRMED" => "EST CONFIRMÉE",
            "LOCATOR"            => "Numéro de référence de réservation",
            "FLIGHT "            => "VOL ",
            "PASSENGERS"         => "Passager",
            "TOTAL"              => "TOTAL",
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'iberiaexpress.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(),'Iberia Express')]")->length > 0
            && $this->detectLang() === true) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/\biberiaexpress\.com$/', $from) > 0;
    }

    public function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('LOCATOR'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('LOCATOR'))}\s*([A-Z\d]{5,})/");

        if (empty($confirmation)) {
            $confirmation = $this->re("/{$this->opt($this->t('FLIGHT '))}[A-Z\d]{2}\s*([A-Z\d]{5,})\s\d+/iu", $this->subject);
        }

        $f->general()
            ->confirmation($confirmation);

        $xpath = "//text()[{$this->starts($this->t('FLIGHT '))}]/ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::tr[.//img][1]";

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('PASSENGERS'))}]/ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::tr[1]/ancestor::*[1]/tr/td[1]", null, "/^\s*([[:alpha:] \-]+)\s*$/"));

        if (count($travellers) === 0) {
            $travellers = $this->http->FindNodes($xpath . "/following::text()[{$this->eq($this->t('Fare Express'))}]/ancestor::table[1]/descendant::img[contains(@src, 'seats')]/preceding::text()[string-length()>3][1]");
        }

        if (count($travellers) === 0) {
            $travellers[] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Hello'))}\s*(.+)/");
        }

        $f->general()
            ->travellers($travellers, true);

        if (!empty($this->http->FindSingleNode("//text()[{$this->contains($this->t('LOCATOR'))}]/preceding::text()[{$this->contains($this->t('HAS BEEN CONFIRMED'))}]"))) {
            $f->general()
                ->status('confirmed');
        }

        $f->program()
            ->accounts($this->http->FindNodes("//text()[{$this->eq($this->t('PASSENGERS'))}]/ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::tr[1]/ancestor::*[1]/tr/td[last()]", null, "/^\s*(\d{13})\s*$/"), false);

        $totalText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('PASSENGERS'))}]/following::text()[{$this->eq($this->t('TOTAL'))}][1]/ancestor::td[1]/following-sibling::td[normalize-space()][last()]");

        if (preg_match("/^\s*([\d\.\, ]+)\s*(\S{1})\s*$/u", $totalText, $m)) {
            $f->price()
                ->total($this->normalizeAmount($m[1]))
                ->currency($this->currency($m[2]));
        }

        $xpath = "//text()[{$this->starts($this->t('FLIGHT '))}]/ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::tr[.//img][1]";
        $node = $this->http->XPath->query($xpath);

        foreach ($node as $root) {
            $s = $f->addSegment();

            $airlineText = $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('FLIGHT '))}][1]", $root);

            if (preg_match("/{$this->opt($this->t('FLIGHT '))}([A-Z\d]{2})(\d{2,4})$/", $airlineText, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            /*
                16:25
                Terça-feira 31 Agosto 2021
                Madrid Adolfo Suarez-Barajas (MAD)
                Terminal 4S
            */
            $pattern = "/^\s*(?<code>[A-Z]{3})\s*\n\s*(?<name>\S.{3,})\s*\n\s*"
                . "(?<datetime>[\d\/]{6,}\s*\d{1,2}:\d{2})H"
                . "\s*$/";

            $departureText = implode("\n", $this->http->FindNodes("td[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match($pattern, $departureText, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date2($this->normalizeDate($m['datetime']));
            }

            $arrivalText = implode("\n", $this->http->FindNodes("td[normalize-space()][last()]//text()[normalize-space()]", $root));

            if (preg_match($pattern, $arrivalText, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date2($this->normalizeDate($m['datetime']));
            }

            $seats = $this->http->FindNodes("./following::text()[{$this->eq($this->t('Fare Express'))}]/ancestor::table[1]/descendant::img[contains(@src, 'seats')]/following::text()[string-length()>1][1]", $root);

            if (count($seats) > 0) {
                $s->extra()
                    ->seats($seats);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectLang() == false) {
            $this->logger->debug('Can\'t determine a language!');

            return $email;
        }

        $this->subject = $parser->getSubject();

        $this->parseFlight($email);

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

    private function detectLang(): bool
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectLang as $lang => $detects) {
            foreach ($detects as $detect) {
                if (stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str): string
    {
        $in = [
            // 17/09/2021 19:10H
            "/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}:\d{2})\s*$/u",
        ];
        $out = [
            "$1.$2.$3 $4",
        ];
        $str = preg_replace($in, $out, $str);

//        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }
        return $str;
    }

    private function normalizeAmount(?string $s)
    {
        return PriceHelper::cost($s, ',', '.');
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }
}
