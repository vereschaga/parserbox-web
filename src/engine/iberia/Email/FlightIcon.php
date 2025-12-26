<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightIcon extends \TAccountChecker
{
    public $mailFiles = "iberia/it-109238442.eml, iberia/it-110292615.eml, iberia/it-650713677.eml, iberia/it-653714190.eml, iberia/it-94838871.eml, iberia/it-94910209.eml";

    public $imgFlightSrc = [
        "fd2250f5-73de-4c8d-bdd4-8903b808651c.png",
        "fe8b13727d670c7d7d/m/19/5e495f66-5a52-4ff0-9e3e-1347aa9d2433.png",
        "fe8b13727d670c7d7d/m/22/5e65b0c9-4689-4d22-bdd3-e62ddbcc46e4.png",
        //        "f0a09bf6-eb9e-432a-ac20-d0b1e2b2fabc.png"
    ];
    public static $dictionary = [
        "fr" => [
            "Reserva"                => "embarque en porte", // subject
            "Código de reserva:"     => ["Numéro de réservation:", "Code de réservation:", 'Numéro de réservation:'],
            "Estimado señor/señora " => ["¡Hola!", "Très chers Monsieur/Madame", '¡Hola '],
            "dateRegexs"             => [
                "Votre vol du (?<date>.+?)(?: a las (?<time>\d{1,5})| est .*|\s*$)",
                "Tu vuelo del (?<date>.+?) tiene previsto embarcar a las (?<time>\d{1,2}:\d{2}) por la puerta",
            ],
            "Passengers:" => ["Passager:", '*Passagers de la réservation'],
        ],
        "pt" => [
            "Reserva"                => ["embarque pela porta", "Reserva"], // subject
            "Código de reserva:"     => ["Número de reserva:", 'Número da reserva:'],
            "Estimado señor/señora " => ["¡Hola!", "Olá ", 'Olá,'],
            "dateRegexs"             => [
                "O seu voo de (?<date>.+?)(?: a las (?<time>\d{1,5})|\s*$)",
                "O (?<date>[\d\/]+?) voará",
                "O seu voo de (?<date>.+?) tem o embarque previsto para as (?<time>\d{1,2}:\d{2}) na porta",
            ],
            "Passengers:" => ["Passageiro:", '*Passageiros da reserva'],
        ],
        "es" => [
            "Reserva"                => "Reserva", // subject
            "Código de reserva:"     => ["Código de reserva:", "Número de reserva:"],
            "Estimado señor/señora " => ["Estimado señor/señora ", "¡Hola!", "Hola ", 'Estimado/a'],
            "dateRegexs"             => [
                "Su vuelo del (?<date>.+?)(?: a las (?<time>\d{1,5})|\s*$)",
                "el (?<date>.+?) volará de ",
                "Tu vuelo del (?<date>.+?) tiene previsto embarcar a las (?<time>\d{1,2}:\d{2}) por la puerta",
            ],
            "Passengers:" => ["Pasajero:", '*Pasajeros de la reserva'],
        ],
        "en" => [
            "Reserva"                => "Booking", // subject
            "Código de reserva:"     => ["Booking Number:", 'Booking number:'],
            "Estimado señor/señora " => ["Hi ", "¡Hola!", 'Dear Sir/Madam '],
            "dateRegexs"             => [
                "On (?<date>.+?) you'll be flying from",
                "Your flight on (?<date>.+?)\s*$",
                "Your Flight of (?<date>.+?) at (?<time>\d{4})\s*$",
                "Your flight on (?<date>.+?) is scheduled to board at (?:\d{4}-\d{2}-\d{2} )?(?<time>\d{1,2}:\d{2})(?::00)? via Gate",
            ],
            "Passengers:" => ['Passengers:', '*Passengers on the booking'],
        ],
        "de" => [
            "Reserva"                => "Buchung", // subject :  Buchung WQ3GEP. Informationen für Ihre nächste Reise nach Panama
            "Código de reserva:"     => "Buchungsnummer:",
            "Estimado señor/señora " => ["¡Hola!", "Hallo "],
            "dateRegexs"             => [
                "Ihr Flug am (?<date>.+?)\s*$",
                "am (?<date>[\d\/]+?) fliegen",
            ],
            // "Passengers:" => '',
        ],
    ];

    public $lang = "en";

    private $reFrom = "noreply@iberia.es";

    private $date;
    private $emailSubject;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->emailSubject = $parser->getSubject();

        foreach (self::$dictionary as $lang => $dict) {
            if (
                ((isset($dict['Código de reserva:']) && $this->http->XPath->query("//text()[" . $this->contains($dict['Código de reserva:']) . "]")->length > 0)
                    || (isset($dict['Reserva']) && preg_match("/" . $this->opt($dict['Reserva']) . "/u", $this->emailSubject)))
                && isset($dict['Estimado señor/señora '])
                && $this->http->XPath->query("//text()[" . $this->contains($dict['Estimado señor/señora ']) . "]")->length > 0
            ) {
                if ($lang == 'pt' && $this->http->XPath->query("//text()[" . $this->contains('voo') . "]")->length == 0
                 && $this->http->XPath->query("//text()[" . $this->contains('vuelo') . "]")->length > 0) {
                    continue;
                }
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
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.iberia.com')]")->length < 3) {
            return false;
        }

        if ($this->http->XPath->query("//img[" . $this->contains($this->imgFlightSrc, '@src') . "]")->length > 0) {
            return true;
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

    private function parseHtml(Email $email)
    {
        $flight = $email->add()->flight();

        // General
        $confs = array_filter(explode("/", $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Código de reserva:")) . "]/following::text()[normalize-space(.)][1]")));

        if (empty($confs)) {
            $confs = array_filter(explode("/", $this->http->FindSingleNode("//text()[" . $this->contains(['/ Booking code:', '/Booking Number:']) . "]/following::text()[normalize-space(.)][1]", null, true, "/^\s*[\dA-Z]{5,7}[ \/\dA-Z]+$/")));
        }

        foreach ($confs as $conf) {
            $flight->general()
                ->confirmation($conf);
        }

        if (empty($confs) && preg_match("/" . $this->opt($this->t("Reserva")) . "\s*([A-Z\d]{5,7}) *\./u", $this->emailSubject, $m)) {
            $flight->general()
                ->confirmation($m[1]);
        }

        $travelers = $this->http->FindNodes("//text()[{$this->contains($this->t('Passengers:'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()]", null, "/(.+?)(?: (?:MRS|MS|MR))?\s*$/i");

        if (!empty($travelers)) {
            $flight->general()
                ->travellers($travelers, true);
        } else {
            $traveler = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Estimado señor/señora ")) . "][1]", null, true,
                "/" . $this->opt($this->t("Estimado señor/señora ")) . "\s*(.+)[:,]\s*$/u");

            if (!empty($traveler)
                || empty($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Estimado señor/señora ")) . "][1]"))
            ) {
                $flight->general()
                    ->traveller($traveler);
            }
        }

        // Segments
        $xpath = "//img[" . $this->contains($this->imgFlightSrc, '@src') . "]/ancestor::tr[normalize-space()][1][count(td[normalize-space()]) = 3]";
        $nodes = $this->http->XPath->query($xpath);
        $segment = [];

        foreach ($nodes as $root) {
            $s = $flight->addSegment();

            // Airline
            $node = $this->http->FindSingleNode("td[normalize-space()][2]", $root);

            if (preg_match("/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d{1,5})\s*$/", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("(./td[normalize-space()][1]/descendant::text()[normalize-space()][1])[1]", $root));

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("(./td[normalize-space()][3]/descendant::text()[normalize-space()][1])[1]", $root));

            $key = $s->getAirlineName() . $s->getFlightNumber() . $s->getDepCode() . $s->getArrCode();

            if (isset($segment[$key])) {
                $flight->removeSegment($s);

                continue;
            } else {
                $segment[$key] = $key;
            }

            $dateText = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]/ancestor::tr[1]", $root);

            $detectDate = false;

            foreach ((array) ($this->t("dateRegexs")) as $re) {
                if (preg_match("/" . $re . "/u", $dateText, $m) && isset($m['date'])) {
                    $date = $this->normalizeDate($m['date']);

                    $time = preg_replace('/^\s*(\d{1,2})(\d{2})\s*$/', '$1:$2', $m['time'] ?? null);

                    if (!empty($date)) {
                        if (!empty($time) && preg_match("/^\s*\d{1,2}:\d{2}\s*$/", $time)) {
                            $s->departure()
                                ->date(strtotime($time, $date));
                        } else {
                            $s->departure()
                                ->noDate()
                                ->day($date);
                        }
                        $s->arrival()
                            ->noDate();

                        break;
                        $detectDate = true;
                    }
                }
            }

            if ($detectDate === false && !empty($dateText)) {
                foreach (self::$dictionary as $lang => $dict) {
                    if ($lang !== $this->lang && isset($dict['dateRegexs']) && (
                        (isset($dict['Código de reserva:']) && $this->http->XPath->query("//text()[" . $this->contains($dict['Código de reserva:']) . "]")->length > 0)
                        || (isset($dict['Estimado señor/señora ']) && $this->http->XPath->query("//text()[" . $this->contains($dict['Estimado señor/señora ']) . "]")->length > 0)
                    )) {
                        foreach ((array) ($dict['dateRegexs']) as $re) {
                            if (preg_match("/" . $re . "/u", $dateText, $m) && isset($m['date'])) {
                                $date = $this->normalizeDate($m['date'], $lang);
                                $time = preg_replace('/^\s*(\d{1,2})(\d{2})\s*$/', '$1:$2', $m['time'] ?? null);

                                if (!empty($date)) {
                                    if (!empty($time) && preg_match("/^\s*\d{1,2}:\d{2}\s*$/", $time)) {
                                        $s->departure()
                                            ->date(strtotime($time, $date));
                                    } else {
                                        $s->departure()
                                            ->noDate()
                                            ->day($date);
                                    }
                                    $s->arrival()
                                        ->noDate();

                                    break;
                                    $detectDate = true;
                                }
                            }
                        }
                    }
                }
            }
        }

        return true;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str, $lang = null)
    {
        if (empty($lang)) {
            $lang = $this->lang;
        }
        $in = [
            "#^\s*(\d+)\s+de\s+([^\d\s]+)\s*$#", //10 de mayo
            "#^\s*(\d+)[.]([^\d\s]+)\s*$#", //10 de mayo
            "#^\s*([^\d\s]+)\s+(\d+)\s*$#", //May 20
            "#^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s*$#", //31/05/2021
        ];
        $out = [
            "$1 $2",
            "$1 $2",
            "$2 $1",
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d{4}#", $str, $m)) {
            if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
                if ($en = MonthTranslate::translate($m[2], $lang)) {
                    $str = str_replace($m[2], $en, $str);
                }
            }

            return strtotime($str);
        } elseif (preg_match("/^\s*(\d+)\s+([[:alpha:]]+)\s*$/u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $lang)) {
                $str = str_replace($m[2], $en, $str);
                $m[2] = $en;
            }
            $date = EmailDateHelper::parseDateRelative($m[1] . ' ' . $m[2], $this->date);

            if (abs($date - $this->date) < 60 * 60 * 24 * 30) {
                return $date;
            } else {
                $date = EmailDateHelper::parseDateRelative($m[1] . ' ' . $m[2], $this->date, false);

                if (abs($date - $this->date) < 60 * 60 * 24 * 30) {
                    return $date;
                }
            }
        }

        return null;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains(" . $text . ", \"{$s}\")"; }, $field));
    }
}
