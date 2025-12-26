<?php

namespace AwardWallet\Engine\ouigo\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TrainReservation extends \TAccountChecker
{
    public $mailFiles = "ouigo/it-121653771.eml, ouigo/it-121905573.eml, ouigo/it-805495587.eml";
    public $subjects = [
        'All good. Here is your OUIGO reservation',
        'Todo bien. Aquí tienes tu reserva',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['WE LOVE IT WHEN PLANS GO WELL'],
        'es' => ['NOS ENCANTA QUE LOS PLANES SALGAN BIEN'],
    ];

    public static $dictionary = [
        "en" => [
            'Tickets type' => ['Tickets type', 'Travellers and seating types'],
        ],

        "es" => [
            'WE LOVE IT WHEN PLANS GO WELL'   => ['NOS ENCANTA QUE LOS PLANES SALGAN BIEN'],
            'Your OUIGO booking is confirmed' => 'Tu reserva OUIGO está confirmada',
            'Tickets type'                    => ['Viajeros y tipo de plazas', 'Tipo de billetes'],
            'Train'                           => 'de tren',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@ouigo.es') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), '	Ouigo')]")) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('WE LOVE IT WHEN PLANS GO WELL'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ouigo\.es$/', $from) > 0;
    }

    public function ParseTrain(Email $email)
    {
        $xpath = "//img[contains(@src, 'Start_Icon')]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//text()[normalize-space()='We open doors 30 minutes before departure.']/ancestor::table[1]/descendant::td[1]");
        }

        foreach ($nodes as $root) {
            $t = $email->add()->train();

            $t->general()
                ->confirmation($this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('Your OUIGO booking is confirmed'))}][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Your OUIGO booking is confirmed'))}\s*\:\s*([A-Z\d]{5,})/"))
                ->travellers($this->http->FindNodes("./following::text()[{$this->starts($this->t('Tickets type'))}][1]/ancestor::tr[1]/following-sibling::tr[normalize-space()]/descendant::td[2]", $root), true);

            $price = $this->http->FindSingleNode("./preceding::text()[{$this->contains($this->t('Train'))}][1]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^([\d\,\.]+)(\D+)$/", $price, $m)) {
                $t->price()
                    ->total($m[1])
                    ->currency($this->normalizeCurrency($m[2]));
            }

            $s = $t->addSegment();

            $date = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);

            $s->extra()
                ->number($this->http->FindSingleNode("./preceding::text()[{$this->contains($this->t('Train'))}][1]", $root, true, "/{$this->opt($this->t('Train'))}\s*(\d+)\s*$/"))
                ->duration($this->http->FindSingleNode("./ancestor::tr[1]/following::text()[normalize-space()][1]", $root));

            $depInfo = $this->http->FindSingleNode("./ancestor::tr[1]", $root);

            if (preg_match("/^\s*([\d\:]+)[\s\-]+(.+)$/", $depInfo, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $m[1]))
                    ->name($m[2]);
            }

            $arrInfo = $this->http->FindSingleNode("./following::img[contains(@src, 'End_Icon')][1]/ancestor::tr[1]", $root);

            if (empty($arrInfo)) {
                $arrInfo = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':')][2]", $root);
            }

            if (preg_match("/^\s*([\d\:]+)[\s\-]+(.+)$/", $arrInfo, $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $m[1]))
                    ->name($m[2]);
            }

            $car = array_unique(array_filter($this->http->FindNodes("./following::text()[normalize-space()='Tickets type']/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), 'seat')]", $root, "/{$this->opt($this->t('Coach'))}\s*(.+)/")));

            if (count($car) === 1) {
                $s->extra()
                    ->car($car[0]);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();

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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $this->logger->debug($date);

        $in = [
            '#^(\w+)\s*(\d+)\D+\,\s*(\d{4})$#', //April 05 (Mon), 2021
        ];

        $out = [
            '$2 $1 $3',
        ];

        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }

    private function AssignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return true;
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
