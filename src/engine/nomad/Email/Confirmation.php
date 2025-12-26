<?php

namespace AwardWallet\Engine\nomad\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "nomad/it-165808534.eml, nomad/it-170567912.eml";
    public $detectSubjects = [
        'INFO SNCF NOMAD TRAIN – Confirmation de réservation pour le',
    ];

    public $lang = 'fr';

    public static $dictionary = [
        "fr" => [
            'Train N°' => 'Train N°',
            'Date du voyage' => 'Date du voyage',
            'Départ de' => 'Départ de',
            'Arrivée à' => 'Arrivée à',
            'Passager' => 'Passager',
            'Classe' => 'Classe',
            'Voiture' => 'Voiture',
            'avec votre référence de dossier de voyage* suivante' => 'avec votre référence de dossier de voyage* suivante',
        ],
        "en" => [
            'Train N°' => 'Train No.',
            'Date du voyage' => 'Date of travel',
            'Départ de' => 'Departure from',
            'Arrivée à' => 'Arrival at',
            'Passager' => 'Passenger',
            'Classe' => 'Class',
            'Voiture' => 'Coach',
            'avec votre référence de dossier de voyage* suivante' => 'at the self-service terminal with the following booking number*',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@cloud.sqills.com') !== false) {
            foreach ($this->detectSubjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'nomadtrain.sncf.com')] | //text()[contains(normalize-space(), 'SNCF NOMAD TRAIN TEAM')]")->length === 0) {
            return false;
        }
        foreach (self::$dictionary as $dict) {
            if (!empty($dict['avec votre référence de dossier de voyage* suivante']) && !empty($dict['Train N°']) && !empty($dict['Date du voyage'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['avec votre référence de dossier de voyage* suivante'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Train N°'])}]/following::text()[normalize-space()][normalize-space() != ':'][2][{$this->eq($dict['Date du voyage'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]cloud\.sqills\.com$/', $from) > 0;
    }

    public function ParseTrain(Email $email)
    {
        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[".$this->contains($this->t("avec votre référence de dossier de voyage* suivante"))."]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{6})\s*$/"))
            ->travellers(array_unique($this->http->FindNodes("//tr[not(.//tr)][*[1][".$this->eq($this->t("Passager"))."]][1]/following-sibling::tr/*[1]")));


        $xpath = "//text()[".$this->eq($this->t("Train N°"))."]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);


        foreach ($nodes as $root) {

            $s = $t->addSegment();

            $addXpath = "following::text()[normalize-space() and string-length(normalize-space()) > 1][position() < 10]";

            $s->setNumber($this->http->FindSingleNode("following::text()[normalize-space() and normalize-space() != ':'][1]", $root));

            $date = $this->http->FindSingleNode($addXpath . "[".$this->eq($this->t("Date du voyage"))."]/following::text()[normalize-space() and normalize-space() != ':'][1]", $root);

            $s->departure()
                ->name($this->http->FindSingleNode($addXpath . "[".$this->eq($this->t("Départ de"))."]/following::text()[normalize-space() and normalize-space() != ':'][1]", $root, true, "/(.+?)\s+".$this->opt($this->t("à"))."\s+\d+:\d+/"))
                ->date($this->normalizeDate($date . ', ' . $this->http->FindSingleNode($addXpath . "[".$this->eq($this->t("Départ de"))."]/following::text()[normalize-space() and normalize-space() != ':'][1]", $root, true, "/.+\s+".$this->opt($this->t("à"))."\s+(\d+:\d+.*)$/")));

            if (!empty($s->getDepName())) {
                $s->departure()->name( $s->getDepName() . ", Europe");
            }
            $s->arrival()
                ->name($this->http->FindSingleNode($addXpath . "[".$this->eq($this->t("Arrivée à"))."]/following::text()[normalize-space() and normalize-space() != ':'][1]", $root, true, "/(.+?)\s+".$this->opt($this->t("à"))."\s+\d+:\d+/"))
                ->date($this->normalizeDate($date . ', ' . $this->http->FindSingleNode($addXpath . "[".$this->eq($this->t("Arrivée à"))."]/following::text()[normalize-space() and normalize-space() != ':'][1]", $root, true, "/.+\s+".$this->opt($this->t("à"))."\s+(\d+:\d+.*)$/")));

            if (!empty($s->getArrName())) {
                $s->arrival()->name( $s->getArrName() . ", Europe");
            }


            $cabinCol = count($this->http->FindNodes("following::tr[not(.//tr)][*[1][".$this->eq($this->t("Passager"))."]][1]/*[".$this->eq($this->t("Classe"))."]/preceding-sibling::*", $root));
            if (!empty($cabinCol)) {
                $s->extra()
                    ->cabin(implode(', ', array_unique($this->http->FindNodes("following::tr[not(.//tr)][*[1][".$this->eq($this->t("Passager"))."]][1]/following-sibling::tr/*[".($cabinCol + 1)."]", $root))));
            }

            $carCol = count($this->http->FindNodes("following::tr[not(.//tr)][*[1][".$this->eq($this->t("Passager"))."]][1]/*[".$this->eq($this->t("Voiture"))."]/preceding-sibling::*", $root));
            if (!empty($carCol)) {
                $s->extra()
                    ->car(implode(", ", array_unique($this->http->FindNodes("following::tr[not(.//tr)][*[1][".$this->eq($this->t("Passager"))."]][1]/following-sibling::tr/*[".($carCol + 1)."]", $root))))
                ;
                $seats = array_filter($this->http->FindNodes("following::tr[not(.//tr)][*[1][".$this->eq($this->t("Passager"))."]][1]/following-sibling::tr/*[".($carCol + 2)."]", $root, "/.*\d.*/"));
                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats);
                }
            }
//            if (preg_match("/^Your\s*train\s*(?<number>\d{2,4})\s*on\s*(?<date>[\d\-]+)\s*will leave from\s*(?<depName>[A-Z\s]+)\s*at\s*(?<depTime>[\d\:]+)\s*and will arrive at\s*(?<arrTime>[\d\:]+)\s*at your destination\s*(?<arrName>[A-Z\s\']+)\.$/", $segInfo, $m)) {
//                if (preg_match("/coach\s*(?<car>\d+)\,\s*seat\s*(?<seat>\d+)$/", $accommodation, $m)) {
//                    $s->setCarNumber($m['car']);
//                    $s->extra()
//                        ->seat($m['seat']);
//                }
//            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['avec votre référence de dossier de voyage* suivante']) && !empty($dict['Train N°']) && !empty($dict['Date du voyage'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['avec votre référence de dossier de voyage* suivante'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Train N°'])}]/following::text()[normalize-space() != ':'][2][{$this->eq($dict['Date du voyage'])}]")->length > 0
            ) {
                $this->lang = $lang;
                break;
            }
        }

        $this->ParseTrain($email);

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
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

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));

        $in = [
            // Tue, 19 Nov 2019, 06: 40
            '/^\s*(\d{1,2})[\\/](\d{1,2})[\\/](\d{4}),\s*(\d+:\d+)\s*$/',
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
