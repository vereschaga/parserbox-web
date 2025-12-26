<?php

namespace AwardWallet\Engine\klm\Email;

use AwardWallet\Schema\Parser\Email\Email;

// TODO: Also consider airfrance/It4020631
class FlightCancelled extends \TAccountChecker
{
    public $mailFiles = "klm/it-58060244.eml, klm/it-58181305.eml, klm/it-60034007.eml, klm/it-60213047.eml, klm/it-60233214.eml, klm/it-60274331.eml, klm/it-60455427.eml, klm/it-60579108.eml, klm/it-60603148.eml";

    public $code;

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            "Booking code:"             => ["Booking code:", "Booking reference:", "Reservation reference number:"],
            "Change"                    => ["your flight has been delayed"],
            "your flight was cancelled" => ["your flight was cancelled", "your flight has been cancelled", "flight has unfortunately been cancelled"],
            //            "Dear " => "",
        ],
        "nl" => [
            "Booking code:"             => ["Boekingscode:"],
            "Change"                    => ["uw vlucht vertraagd", 'uw vlucht is vertraagd'],
            "your flight was cancelled" => ["uw vlucht is geannuleerd"],
            "Dear "                     => ["Geachte Heer ", "Geachte Mevrouw ", "Geachte "],
            "Departure:"                => ['Vertrek:'],
            "Arrival:"                  => ['Aankomst:'],
        ],
        "fr" => [
            "Booking code:"             => ["Référence de réservation:"],
            "Change"                    => ["le départ de votre vol a changé"],
            "your flight was cancelled" => ["Pour cette raison, votre vol a été annulé et"],
            "Dear "                     => ["Cher Monsieur ", "Chère Madame ", "Chère Madame/Cher Monsieur "],
            "Departure:"                => ['Departure:', 'Départ:'],
            "Arrival:"                  => ['Arrival:', 'Arrivée:'],
        ],
        "de" => [
            "Booking code:"             => ["Buchungscode:"],
            "Change"                    => ["Ihr Flug verspätet"],
            "your flight was cancelled" => ["Wir bedauern sehr, dass Ihr Flug gestrichen wurde.", "Leider wurde Ihr Flug gestrichen"],
            "Dear "                     => ["Sehr geehrte Frau ", "Sehr geehrte ", "Sehr geehrter Herr "],
            "Departure:"                => ['Abflug:'],
            "Arrival:"                  => ['Ankunft:'],
        ],
        "pt" => [
            "Booking code:"             => ["Código da reserva:", "Código de reserva:", "Número de referência da reserva:"],
            "Change"                    => ["o seu voo está atrasado"],
            "your flight was cancelled" => ["o seu voo foi cancelado", "Infelizmente seu voo foi cancelado"],
            "Dear "                     => ["Prezado Senhor/Prezada Senhora ", 'Prezada Senhora ', 'Caro Senhor ', "Cara Senhora"],
            "Departure:"                => "Partida:",
            "Arrival:"                  => "Chegada:",
        ],
        "es" => [
            "Booking code:"                    => ["Código de reserva:", "Número de referencia de la reserva:"],
            "Change"                           => ["Sus datos del nuevo vuelo"],
            "your flight was cancelled"        => ["su vuelo ha sido cancelado"],
            "Dear "                            => ["Estimada Señora ", "Estimado Señor "],
            "Departure:"                       => "Salida:",
            "Arrival:"                         => "Llegada:",
        ],
    ];
    private static $providers = [
        'airfrance' => [
            'from' => ['Air France Flight Info', 'airfrance-klm@connect-passengers.com'],
            'subj' => [
                'en'  => 'Your flight is cancelled',
                'fr'  => 'Votre vol est annulé',
                'fr2' => 'Votre nouvel horaire de départ',
                'pt'  => 'O seu voo está cancelado',
                'es'  => 'Su vuelo ha sido cancelado',
            ],
            'body' => [
                'www.airfrance.com', '//img[contains(@src,"airfrance_logo")]',
            ],
        ],

        'klm' => [
            'from' => ['KLM Flight Info', 'airfrance-klm@connect-passengers.com'],
            'subj' => [
                'en'   => 'Your flight is cancelled',
                'en2'  => 'Your new scheduled departure',
                'nl'   => 'Uw vlucht is geannuleerd',
                'nl2'  => 'Uw nieuwe Vertrektijd',
                'de'   => 'Ihr Flug wurde storniert.',
                'de2'  => 'Ihre neue geplante Abflugzeit',
                'pt'   => 'O seu novo horário de partida',
                'pt2'  => 'Seu voo foi cancelado',
                'pt3'  => 'A sua nova partida agendada ',
                'es'   => 'Su nueva salida prevista',
            ],
            'body' => [
                'www.klm.com', '//img[contains(@src,"logo_klm")]',
            ],
        ],
    ];

    private static $detectBody = [
        'en' => ['your flight has been cancelled', 'your flight was cancelled', 'We are very sorry, but due to a flight disruption your flight has been delayed', 'sorry, your flight was cancelled', 'Your updated flight times'],
        'nl' => ['uw vlucht is geannuleerd', 'uw vlucht vertraagd', 'uw vlucht is vertraagd'],
        'fr' => ['Pour cette raison, votre vol a été annulé et', 'Votre carte d’embarquement actuelle reste valide', "le départ de votre vol a changé"],
        'de' => ['Wir bedauern sehr, dass Ihr Flug gestrichen wurde.', 'Ihr Flug wurde storniert', 'Leider wurde Ihr Flug gestrichen', 'Ihr Flug verspätet'],
        'pt' => ['Lamentamos muito, mas devido a uma circunstância extraordinária', 'Sentimos muito, o seu voo foi cancelado', 'Infelizmente seu voo foi cancelado', 'o seu voo está atrasado'],
        'es' => ['Sus datos del nuevo vuelo', "su vuelo ha sido cancelado"],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $code => $arr) {
            if (stripos($arr['from'][0], $from) !== false &
                stripos($arr['from'][1], $from) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($this->getProviderByBody())) {
            return false;
        }

        foreach (self::$detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($this->http->Response["body"], $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($this->http->Response["body"], $dBody) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $flight = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking code:")) . "]/following::text()[normalize-space()][1]", null, true, '/^([A-Z\d]{6})$/');

        if (!$conf) {
            $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Booking code:"))}][1]", null, true, '/:\s*([A-Z\d]{6})$/');
        }
        $flight->general()
            ->confirmation($conf,
                trim($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Booking code:")) . "]", null, true, "/({$this->opt($this->t('Booking code:'))})/"), ':'))
            ->traveller(preg_replace("#^(?:Mrs/Mr|Mrs|Mr|Heer|Mevr|Mrs\.\/Mr\.) #", '',
                $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true, '#^' . $this->preg_implode($this->t('Dear ')) . '(.+?)[,]\s*$#')), false)
        ;

        if (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('Change'))}]"))) {
            $flight->general()
                ->status('changed');
        }

        if (!empty($this->http->FindSingleNode("//text()[{$this->contains($this->t('Your updated flight times'))}]"))) {
            $flight->general()
                ->status('updated');
        }

        $statusCancelled = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("your flight was cancelled")) . "][1]");

        if (!empty($statusCancelled)) {
            $flight->general()
                ->cancelled()
                ->status("Cancelled");
        }

        $segment = $flight->addSegment();
        $node = join("\n", $this->http->FindNodes("//text()[{$this->starts($this->t("Booking code:"))}]/ancestor::td[1]//text()"));
        //$this->logger->debug($node);

        $pattern1 = '/(?<flightName>[A-Z\d]{2})(?<flightNumber>\d{1,5})\s+(?<depName>\D+)\s[-]\s+(?<arrName>\D+)\s+(?<dayDep>[\d\/]+)\s*' . $this->preg_implode($this->t("Booking code:")) . '/';
        $pattern2 = '/(?<flightName>[A-Z\d]{2})(?<flightNumber>\d{1,5})\s+(?<dayDep>[\d\/]+)\s+(?<depCode>[A-Z]{3})[\s\-]+(?<arrCode>[A-Z]{3})\s+' . $this->preg_implode($this->t("Booking code:")) . '/';
        /*
        AF0655
        Dubai (DXB) - Paris (CDG)
        Departure: 02:05 11/02/2020
        Arrival: 06:35 11/02/2020
        Booking reference: LIFZ5S
         */
        $pattern3 = '/(?<flightName>[A-Z\d]{2})(?<flightNumber>\d{1,5})\s+(?<depName>\D+)\s+\((?<depCode>[A-Z]{3})\)\s[-]\s+(?<arrName>\D+)\s+\((?<arrCode>[A-Z]{3})\)\s+'
            . "{$this->preg_implode($this->t("Departure:"))}(?<dateDep>[\d\/:\s]+)\s+{$this->preg_implode($this->t("Arrival:"))}(?<dateArr>[\d\/:\s]+)\s+"
            . "{$this->preg_implode($this->t("Booking code:"))}/";

        /*
        KL0792
        Sao Paulo - Amsterdao
        Partida: 15:05 07/06/2020
        Chegada: 07:35 08/06/2020
        Código da reserva: R3GE8M
         */
        $pattern4 = '/(?<flightName>[A-Z\d]{2})(?<flightNumber>\d{1,5})\s+(?<depName>\D+)\s[-]\s+(?<arrName>\D+)\s+'
            . "{$this->preg_implode($this->t("Departure:"))}(?<dateDep>[\d\/:\s]+)\s+{$this->preg_implode($this->t("Arrival:"))}(?<dateArr>[\d\/:\s]+)\s+"
            . "{$this->preg_implode($this->t("Booking code:"))}/";

        /*
        KL1834
        Berlin - Amsterdam
        Departure: 21:10 08/26/2019
        Booking code:M3EH5A
         */
        $pattern5 = '/(?<flightName>[A-Z\d]{2})(?<flightNumber>\d{1,5})\s+(?<depName>\D+)\s[-]\s+(?<arrName>\D+)\s+'
            . "{$this->preg_implode($this->t("Departure:"))}(?<dateDep>[\d\/:\s]+)\s+"
            . "{$this->preg_implode($this->t("Booking code:"))}/";

        /*
        KL1170
        05/11/2019
        18:45
        HEL - AMS

        Booking code:
        MHMDOO
         */
        $pattern6 = '/\b(?<flightName>[A-Z\d]{2})(?<flightNumber>\d{1,5})\s+(?<dateDep>[\d\/:\s]+)\s+'
            . '(?<depCode>[A-Z]{3})\s+[-]\s+(?<arrCode>[A-Z]{3})\s+'
            . "{$this->preg_implode($this->t("Booking code:"))}/";

        if (preg_match($pattern1, $node, $m) || preg_match($pattern2, $node, $m) || preg_match($pattern3, $node, $m)
            || preg_match($pattern4, $node, $m) || preg_match($pattern5, $node, $m) || preg_match($pattern6, $node, $m)) {
            $segment->airline()
                ->name($m['flightName'])
                ->number($m['flightNumber']);

            if (isset($m['depCode'])) {
                $segment->departure()->code($m['depCode']);
                $segment->arrival()->code($m['arrCode']);
            } else {
                $segment->departure()->noCode();
                $segment->arrival()->noCode();
            }

            if (isset($m['depName'])) {
                $segment->departure()->name($m['depName']);
                $segment->arrival()->name($m['arrName']);
            }

            if (isset($m['depName']) || isset($m['depCode'])) {
                if (!empty($m['dayArr'])) {
                    $segment->arrival()->day($this->normalizeDate($m['dayArr']));
                } elseif (!empty($m['dateArr'])) {
                    $segment->arrival()->date($this->normalizeDate($m['dateArr']));
                } else {
                    $segment->arrival()->noDate();
                }
            }

            // TODO: Date format is not good
            if (!empty($m['dayDep'])/* && !$statusCancelled*/) {
                $segment->departure()->day($this->normalizeDate($m['dayDep']));
            } elseif (!empty($m['dateDep'])) {
                $segment->departure()->date($this->normalizeDate($m['dateDep']));
            } else {
                $segment->departure()->noDate();
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (null !== ($code = $this->getProvider($parser))) {
            $email->setProviderCode($code);
        }

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

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        /*if (preg_match("//", $str, $m))*/
        /*$this->logger->warning($this->lang);
        $this->logger->debug("IN-" . $str);*/
        $in = [
            // 07:35 08/06/2020
            // 12:00 06/16/2020
            "#^\s*(\d+:\d+) (\d+)\/(\d+)\/(\d{4})$#",
            // 30/07/2020
            // 06/29/2020 - en
            // 16/07/2020 - en
            "#^(\d+)\/(\d+)\/(\d{4})$#",
        ];

        if ($this->lang == 'en') {
            $out = [
                "$4-$2-$3, $1",
                "$3-$1-$2",
            ];
        } else {
            $out = [
                "$4-$3-$2, $1",
                "$3-$2-$1",
            ];
        }
        $str = preg_replace($in, $out, $str);

        //After checking, if 2020-14-10 or 2020-14-10, 14:00
        if (preg_match("/^(\d{4})\-(\d+)\-(\d+)(?:\,\s*([\d\:]+)$|$)/", $str, $m)) {
            if ($m[2] > 12) {
                $in = [
                    "#^(\d{4})\-(\d+)\-(\d+)\,\s*([\d\:]+)$#",
                    "#^(\d{4})\-(\d+)\-(\d+)$#",
                ];
                $out = [
                    "$1-$3-$2, $1",
                    "$1-$3-$2",
                ];
                $str = preg_replace($in, $out, $str);
            }
        }

        return strtotime($str);
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByBody();
    }

    private function getProviderByBody()
    {
        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)) {
                        return $code;
                    }
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

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return $text . "=\"{$s}\""; }, $field));
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
