<?php

namespace AwardWallet\Engine\tapportugal\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourFlight extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-688824843.eml, tapportugal/it-689093912.eml, tapportugal/it-771303063.eml, tapportugal/it-772608085.eml";

    public static $providers = [
        'tapportugal' => [
            'from'    => '@my-notification.flytap.com',
            'subject' => [
                'Important: Your TAP Air Portugal flight reference',
            ],
            'bodyText' => ['TAP Air Portugal'],
        ],
        'aireuropa' => [
            'from'    => 'aireuropa@info.aireuropa.com',
            'subject' => [
                // es
                'Cambios en su reserva',
                // pt
                'Obrigado por confirmar seu voo',
            ],
            'bodyText' => ['AIR EUROPA LÍNEAS AÉREAS'],
        ],
    ];

    public $providerCode;
    public $lang = '';

    public static $dictionary = [
        "en" => [
            'Changes to your booking' => ['Changes to your booking', 'find below the confirmation of your new flight:'],
            'confNumber'              => 'Booking reference',
            // 'Dear ' => '',
            'Your Previous Itinerary' => ['Your Previous Itinerary'],
            'Duration:'               => ['Duration:', 'Duration'],
            'Your new itinerary'      => ['Your new itinerary', 'YOUR NEW ITINERARY', 'Your itinerary', 'YOUR ITINERARY'],
        ],
        "pt" => [
            'Changes to your booking' => ['Alterações à sua reserva', 'Alterações no seu voo', 'enviamos-lhe a confirmação do seu novo voo:'],
            'confNumber'              => ['Código de Reserva', 'Código da reserva'],
            'Dear '                   => ['Caro/a ', 'Dear ', 'Prezado / Prezada '],
            'Your Previous Itinerary' => ['O seu itinerário anterior', 'Seu itinerário anterior'],
            'Duration:'               => ['Duração:', 'Duração', 'Duration:'],
            'Your new itinerary'      => ['Seu novo itinerário', 'SEU NOVO ITINERÁRIO', 'O seu itinerário', 'O SEU ITINERÁRIO'],
        ],
        "es" => [
            'Changes to your booking' => ['Cambios en su reserva', 'enviamos la confirmación de su nuevo vuelo:'],
            'confNumber'              => 'Referencia reserva',
            'Dear '                   => ['Estimado / Estimada '],
            'Your Previous Itinerary' => ['Su Itinerario anterior'],
            'Duration:'               => ['Duration:'],
            'Your new itinerary'      => ['Su nuevo itinerario', 'SU NUEVO ITINERARIO', 'Su itinerario', 'SU ITINERARIO'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $provider) {
            if (empty($provider['from']) || stripos($headers['from'], $provider['from']) === false) {
                continue;
            }

            if (empty($provider['subject'])) {
                continue;
            }

            foreach ($provider['subject'] as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    $this->providerCode = $code;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectedProvider = false;

        foreach (self::$providers as $code => $provider) {
            if (!empty($provider['bodyText']) && $this->http->XPath->query("//text()[{$this->contains($provider['bodyText'])}]")->length > 0) {
                $detectedProvider = true;
                $this->providerCode = $code;

                break;
            }
        }

        if ($detectedProvider === false) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Changes to your booking'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Changes to your booking'])}]")->length > 0
                && !empty($dict['Your new itinerary'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Your new itinerary'])}]")->length > 0
                && !empty($dict['confNumber'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['confNumber'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]my\-notification\.flytap\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['confNumber'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['confNumber'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->ParseFlight($email);

        if (empty($this->providerCode)) {
            foreach (self::$providers as $code => $provider) {
                if (!empty($provider['from']) && stripos($parser->getCleanFrom(), $provider['from']) !== false) {
                    $this->providerCode = $code;

                    break;
                }

                if (!empty($provider['bodyText']) && $this->http->XPath->query("//text()[{$this->contains($provider['bodyText'])}]")->length > 0) {
                    $this->providerCode = $code;

                    break;
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/ancestor::tr[1]", null, true, "/:\s*([A-Z\d]{6})$/u"));

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, true,
            "/{$this->opt($this->t('Dear '))}\s*([[:alpha:] \-]+?)[,]?\s*$/u");

        if (!empty($traveller) && !preg_match("/^\s*{$this->opt(['Customer', 'Cliente'])}\s*$/ui", $traveller)) {
            $f->general()
                ->traveller($traveller);
        }

        $xpath = "//text()[{$this->starts($this->t('Duration:'))}][not(following::text()[{$this->eq($this->t('Your new itinerary'))}])]/following::img[contains(@src, 'itinerary-plane-black')][1]/ancestor::table[1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));

        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root, true, "/^((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*\d{1,4}$/"))
                ->number($this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,4})$/"));

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::tr[2]/td[1]", $root, true, "/^([A-Z]{3})$/"));

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::tr[2]/td[last()]", $root, true, "/^([A-Z]{3})$/"));

            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Duration:'))}]", $root, true, "/{$this->opt($this->t('Duration:'))}\s*(.+)$/"));

            $depInfo = $this->http->FindSingleNode("./following::table[1]/descendant::tr[2]/descendant::td[1]", $root);

            if (preg_match("/^\w+\.?\s*(?<depDate>\d+.*\d{4}\s*\d+\:\d+)\s+(?<depName>.+?)\s*(?:\((?<terminal>\w[\w ]*)\))?\s*$/u", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->date($this->normalizeDate($m['depDate']))
                    ->terminal($m['terminal'] ?? null, true, true)
                ;
            }

            $arrInfo = $this->http->FindSingleNode("./following::table[1]/descendant::tr[2]/descendant::td[1]/following::td[1]", $root);

            if (preg_match("/^\w+\.?\s*(?<arrDate>\d+.*\d{4}\s*\d+\:\d+)\s+(?<arrName>.+?)\s*(?:\((?<terminal>\w[\w ]*)\))?\s*$/u", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->date($this->normalizeDate($m['arrDate']))
                    ->terminal($m['terminal'] ?? null, true, true)
                ;
            }
        }
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\s+(\w+)\s+(\d{4})\s*(\d+\:\d+)$#u", //18 agosto 2024 05:20
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
