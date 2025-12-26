<?php

namespace AwardWallet\Engine\lastminute\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "lastminute/it-11688418.eml, lastminute/it-13040800.eml, lastminute/it-13243554.eml, lastminute/it-13244166.eml, lastminute/it-6079814.eml, lastminute/it-6901613.eml, lastminute/it-7348051.eml, lastminute/it-8191523.eml, lastminute/it-8198747.eml, lastminute/it-8244131.eml, lastminute/it-8245108.eml, lastminute/it-8257838.eml, lastminute/it-8279053.eml";

    public static $froms = [
        'bravofly'   => ['bravofly.'],
        'rumbo'      => ['rumbo.'],
        'volagratis' => ['volagratis.'],
        'lastminute' => ["@lastminute.com"],
        ''           => [".customer-travel-care.com"],
    ];

    private $reSubject = [
        "es" => " - Check-in en linea: tarjeta de embarque",
        "it" => " - Check-in online: carta d'imbarco",
        "en" => " - Online check-in: boarding pass",
        "fr" => " - Enregistrement en ligne: carte d'embarquement",
        "de" => " - Online check-in: Bordkarte",
        "pt" => " - Check-in online: cartão de embarque",
        "hu" => " - Internetes check-in: beszállókártya",
        "no" => " - På nett Sjekke inn: boardingpass",
        "sv" => " - Online Incheckning: boarding pass",
        "fi" => " - Lähtöselvitys verkossa: maihinnousukortti",
        "da" => " - Online check-in: boardingkort",
    ];
    private $logo = [
        'bravofly'   => ['bravofly', 'logo-BF', 'BRAVOFLY'],
        'rumbo'      => ['rumbo', 'RUMBO'],
        'volagratis' => ['logo-VG', 'volagratis', 'VOLAGRATIS'],
        'lastminute' => ['lastminute', 'LASTMINUTE'],
    ];

    private $reBody = [
        'bravofly'   => ['bravofly'],
        'rumbo'      => ['rumbo'],
        'volagratis' => ['volagratis'],
        'lastminute' => ['lastminute'], // the last
    ];

    private $reBody2 = [
        "es" => "Vuelo",
        "it" => "Volo ",
        "en" => "Flight",
        "fr" => "Vol ",
        "de" => "Flug",
        "pt" => "Voo ",
        "hu" => "Járat",
        "no" => "Flyvning",
        "sv" => "Flyg",
        "fi" => "Lento",
        "da" => "Fly ",
    ];

    private static $dictionary = [
        "es" => [
            //			'Reservas y asistencia' => '',
            'ID Booking' => ['la referencia de tu reserva:', 'ID Booking'],
            //			'Vuelo' => '',
            'Tarjeta de embarque adjuntada' => ['Tarjeta de embarque adjuntada', 'Estado de la facturación: pendiente'],
            //			'Estimado/a' => '',
            //			'Salida' => '',
        ],
        "it" => [
            'Reservas y asistencia'         => 'Prenotazioni e assistenza',
            'ID Booking'                    => ['ID di prenotazione', 'ID Booking'],
            'Vuelo'                         => 'Volo',
            'Tarjeta de embarque adjuntada' => ["Carta d'imbarco allegata", "Check-in in attesa"],
            'Estimado/a'                    => 'Gentile',
            'Salida'                        => 'Partenza',
        ],
        "en" => [
            'Reservas y asistencia'         => 'Reservations and assistance',
            'ID Booking'                    => ['booking ID'],
            'Vuelo'                         => 'Flight',
            'Tarjeta de embarque adjuntada' => ["Boarding pass attached", "Check-in pending", "Check-in at airport"],
            'Estimado/a'                    => 'Dear',
            //			'Salida' => '',
        ],
        "fr" => [
            'Reservas y asistencia'         => 'Réservations et service clients',
            'ID Booking'                    => ['lD booking avec vous lorsque vous nous contactez.'],
            'Vuelo'                         => 'Vol',
            'Tarjeta de embarque adjuntada' => ["Carte d'embarquement attaché", "Statut de votre enregistrement: en attente", "Enregistrement à l'aéroport"],
            'Estimado/a'                    => 'Cher/Chère',
            'Salida'                        => 'Départ',
        ],
        "de" => [
            //			'Reservas y asistencia' => '',
            'ID Booking'                    => ['Booking ID bereit:'],
            'Vuelo'                         => 'Flug',
            'Tarjeta de embarque adjuntada' => ["Boardkarte im Anhang", "Check-in ausstehend"], //Check-in ausstehend - to check
            'Estimado/a'                    => 'Hallo',
            //			'Salida' => '',
        ],
        "pt" => [
            //			'Reservas y asistencia' => '',
            'ID Booking'                    => ['ID de Reserva.'],
            'Vuelo'                         => 'Voo',
            'Tarjeta de embarque adjuntada' => ["Cartão de embarque em anexo"],
            //			'Estimado/a' => '',
            //			'Salida' => '',
        ],
        "hu" => [
            'Reservas y asistencia'         => 'Foglalások és támogatás',
            'ID Booking'                    => ['készítse elő foglalási számát!'],
            'Vuelo'                         => 'Járat',
            'Tarjeta de embarque adjuntada' => ["Beszállókártya csatolva"],
            'Estimado/a'                    => 'Tisztelt',
            //			'Salida' => '',
        ],
        "no" => [
            //			'Reservas y asistencia' => '',
            'ID Booking'                    => ['Booking ID-en din klar.'],
            'Vuelo'                         => 'Flyvning',
            'Tarjeta de embarque adjuntada' => ["Innsjekking på flyplassen", "Boardingpass vedlagt"],
            //			'Estimado/a' => '',
            //			'Salida' => '',
        ],
        "sv" => [
            'Reservas y asistencia'         => 'Reservationer och assistans',
            'ID Booking'                    => ['ditt boknings-id redo.'],
            'Vuelo'                         => 'Flyg',
            'Tarjeta de embarque adjuntada' => ["Boarding pass bifogat"],
            'Estimado/a'                    => 'Kära',
            'Salida'                        => 'Avgående',
        ],
        "fi" => [
            'Reservas y asistencia' => 'Varaukset ja tuki',
            'ID Booking'            => ['sinulla on varaustunnuksesi'],
            'Vuelo'                 => 'Lento',
            //			'Tarjeta de embarque adjuntada' => ["Tarkastuskortti liitteenä", "Lähtöselvityksen tila: pidossa"],
            //			'Estimado/a' => '',
            //			'Salida' => '',
        ],
        "da" => [
            'Reservas y asistencia' => 'Booking ikke fundet',
            'ID Booking'            => ['booking-ID ved hånden'],
            'Vuelo'                 => 'Fly',
            //			'Tarjeta de embarque adjuntada' => ["Tarkastuskortti liitteenä", "Lähtöselvityksen tila: pidossa"],
            //			'Estimado/a' => '',
            //			'Salida' => '',
        ],
    ];

    private $lang = "es";
    private $date;
    private $codeProvider = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = \AwardWallet\Common\Parser\Util\EmailDateHelper::calculateOriginalDate($this, $parser);

        if ($this->date == null) {
            $this->date = strtotime("-10 day", strtotime($parser->getHeader('date')));
        }

        if ($this->date == null) {
            return $email;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!empty($this->codeProvider)) {
            $codeProvider = $this->codeProvider;
        } else {
            $codeProvider = $this->getProvider();
        }

        if (!empty($codeProvider)) {
            $email->setProviderCode($codeProvider);
            $email->ota()->code($codeProvider);
        } else {
            $email->ota()->code('lastminute');
        }

        $tripNumber = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("ID Booking")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        if (empty($tripNumber)) {
            $tripNumber = $this->re("#(?:Booking ID|ID Booking)\s+(\d+)#i", $parser->getSubject());
        }

        if (!empty($tripNumber)) {
            $email->ota()->confirmation($tripNumber);
        }
        $phone = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservas y asistencia")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*([\d\+\- \(\).]{5,})\s*$#");

        if (!empty($phone)) {
            if (is_array($this->t("Reservas y asistencia"))) {
                $email->ota()->phone($phone, $this->t("Reservas y asistencia")[0]);
            } else {
                $email->ota()->phone($phone, $this->t("Reservas y asistencia"));
            }
        }
        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$froms as $froms) {
            foreach ($froms as $value) {
                if (stripos($from, $value) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $head = false;

        foreach (self::$froms as $prov => $froms) {
            foreach ($froms as $value) {
                if (strpos($headers["from"], $value) !== false) {
                    $head = true;
                    $this->codeProvider = $prov;

                    break 2;
                }
            }
        }

        if ($head === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = html_entity_decode($parser->getHTMLBody());

        $head = false;

        foreach ($this->reBody as $prov => $froms) {
            foreach ($froms as $from) {
                if ($this->http->XPath->query('//a[contains(@href, "' . $from . '")]')->length > 0 || stripos($body, $from) !== false) {
                    $head = true;

                    break;
                }
            }
        }

        if ($head === false) {
            return false;
        }

        foreach ($this->reBody2 as $reBody) {
            if (stripos($body, $reBody) !== false) {
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

    public static function getEmailProviders()
    {
        return array_filter(array_keys(self::$froms));
    }

    private function flight(Email $email)
    {
        $f = $email->add()->flight();
        $f->general()->noConfirmation();

        $passengers = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t("Vuelo")) . "]/ancestor::tr[1]/following-sibling::tr[count(*)=1]/*[(count(./p) = 2) and contains(p[2]/@style,'color:')]/p[1]")));

        if (!empty($passengers)) {
            $f->general()->travellers($passengers, true);
        }

        if (empty($passengers)) {
            $passengers = $this->http->FindNodes("//text()[" . $this->starts($this->t("Estimado/a")) . "]", null, "#" . $this->t("Estimado/a") . "\s+(.+),$#");

            if (!empty($passengers)) {
                $f->general()->travellers($passengers, false);
            }
        }

        $xpath = "//img[contains(@src, '/icon-takeoff.png')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./preceding-sibling::tr[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#\s+(\w{2})\d+(?:$|\s*" . $this->t('Salida') . ".*)#"))
                ->number($this->http->FindSingleNode("./preceding-sibling::tr[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#\s+\w{2}(\d+)(?:$|\s*" . $this->t('Salida') . ".*)#"));

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("./td[2]", $root, true, "#\(([A-Z]{3})\)#"))
                ->name($this->http->FindSingleNode("./td[2]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#"));

            $date = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[1]/descendant::text()[normalize-space(.)][1]", $root));

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./td[2]/p[normalize-space(.)][2]", $root));
            }
            $s->departure()->date($date);

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("./td[4]", $root, true, "#\(([A-Z]{3})\)#"))
                ->name($this->http->FindSingleNode("./td[4]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#"));

            $date = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[2]/descendant::text()[normalize-space(.)][1]", $root));

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./td[4]/p[normalize-space(.)][2]", $root));
            }
            $s->arrival()->date($date);
        }

        return $email;
    }

    private function getProvider()
    {
        foreach ($this->logo as $prov => $paths) {
            foreach ($paths as $path) {
                if ($this->http->XPath->query('//img[contains(@src, "' . $path . '") and contains(@src, "logo")]')->length > 0) {
                    return $prov;
                }
            }
        }
        $body = $this->http->Response['body'];

        foreach ($this->reBody as $prov => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    return $prov;
                }
            }
        }

        return null;
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
        if (empty($str)) {
            return false;
        }
        $in = [
            "#^\s*(\d+)\s+([^\d\s\.]+)[.]?\s+(\d+:\d+)\s*$#", //04 gen 12:15
        ];
        $out = [
            "$3 $1 $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } else {
                foreach (self::$dictionary as $lang => $value) {
                    if ($en = MonthTranslate::translate($m[1], $lang)) {
                        $str = str_replace($m[1], $en, $str);

                        break;
                    }
                }
            }
        }

        return \AwardWallet\Common\Parser\Util\EmailDateHelper::parseDateRelative($str, $this->date);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
